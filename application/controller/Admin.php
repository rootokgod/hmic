<?php
declare(strict_types=1);

namespace app\controller;

use think\Request;
use think\Response;
use think\facade\Session;

/**
 * 后台管理（/admin）
 * - /admin            仪表盘（需登录，由 AdminAuth 中间件保护）
 * - /admin/api/dashboard  仪表盘数据（JSON）
 * - /admin/api/links      有效下载链接（JSON，分页 20/页）
 * - /admin/api/logs       今日访问 / 下载日志（JSON，分页 20/页）
 * - /admin/home       首页管理（首页开关）
 * - /admin/api/home   保存首页开关（JSON）
 * - /admin/login      登录（GET 表单 / POST 校验）
 * - /admin/logout     退出登录
 */
class Admin
{
    private function bootCommon(): void
    {
        require_once dirname(__DIR__, 2) . '/common.php';
    }

    private function render(string $tpl, array $data = []): string
    {
        $file = dirname(__DIR__) . '/view/admin/' . $tpl . '.php';
        if (!is_file($file)) {
            return '<h1>View not found: ' . htmlspecialchars($tpl, ENT_QUOTES, 'UTF-8') . '</h1>';
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        $html = (string) ob_get_clean();
        // 后台安全路径：视图内写死的 /admin 前缀统一替换为当前后台前缀
        $base = admin_base();
        if ($base !== 'admin') {
            $html = str_replace('"/admin/', '"/' . $base . '/', $html);
            $html = str_replace("'/admin/", "'/" . $base . '/', $html);
            $html = str_replace('"/admin"', '"/' . $base . '"', $html);
            $html = str_replace("'/admin'", "'/" . $base . "'", $html);
        }
        return $html;
    }

    private function collect(): array
    {
        $stats = with_stats(static fn($d) => $d);

        $map = build_map_cached();
        $fileCount = 0;
        foreach ($map as $rel) {
            if (!is_dir(APP_ROOT . '/' . $rel)) {
                $fileCount++;
            }
        }

        $daily = [];
        foreach (array_slice(list_daily_files(), 0, 14) as $date) {
            $d = read_daily($date);
            $daily[] = [
                'date'  => $date,
                'count' => (int) ($d['count'] ?? 0),
                'bytes' => (int) ($d['bytes'] ?? 0),
            ];
        }

        return [
            'today'   => $stats['today'] ?? ['bytes' => 0, 'count' => 0],
            'total'   => $stats['total'] ?? ['bytes' => 0, 'count' => 0],
            'files'   => $fileCount,
            'dirs'    => count(top_dirs()),
            'daily'   => $daily,
            'cmp'     => $this->comparePrevDay($stats, $daily),
        ];
    }

    /**
     * 今日 与 前一日（昨天）对比
     * 昨天没有数据文件时返回 null（前端不显示涨跌）
     */
    private function comparePrevDay(array $stats, array $daily): array
    {
        $prevDate = date('Ymd', strtotime('-1 day'));
        $prev = null;
        foreach ($daily as $d) {
            if ($d['date'] === $prevDate) {
                $prev = $d;
                break;
            }
        }
        if ($prev === null) {
            return ['count' => null, 'bytes' => null];
        }

        return [
            'count' => (int) ($stats['today']['count'] ?? 0) - (int) $prev['count'],
            'bytes' => (int) ($stats['today']['bytes'] ?? 0) - (int) $prev['bytes'],
        ];
    }

    // ---------- 仪表盘 ----------
    public function index(Request $request): Response
    {
        $this->bootCommon();

        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return redirect('/' . admin_base() . '/login');
        }

        $data = $this->collect();
        $data['user']      = $user;
        $data['login']     = (int) Session::get('admin_login_at', 0);
        $data['now']       = date('Y-m-d H:i:s');
        $data['fmt']       = 'fmt_bytes';
        $data['pageTitle'] = '仪表盘';
        $data['activeNav'] = 'dashboard';
        $data['site']      = SITE_NAME;

        // 地图数据 + 中国 GeoJSON 直接内联进页面，首屏即刻渲染，无需逐条加载
        require_once dirname(__DIR__) . '/geo.php';
        $data['visitMap'] = $this->visitmapPayload(3);
        $geoFile = dirname(__DIR__, 2) . '/public/assets/map/china.json';
        $rawG = @file_get_contents($geoFile);
        $data['chinaGeo'] = ($rawG !== false && $rawG !== '') ? json_decode($rawG, true) : null;

        $html = $this->render('dashboard', $data);

        return Response::create($html, 'html', 200, ['Cache-Control' => 'no-store']);
    }

    public function dashboard(Request $request): Response
    {
        $this->bootCommon();

        $stats = with_stats(static fn($d) => $d);
        $map = build_map_cached();
        $fileCount = 0;
        foreach ($map as $rel) {
            if (!is_dir(APP_ROOT . '/' . $rel)) {
                $fileCount++;
            }
        }

        $daily = [];
        foreach (array_slice(list_daily_files(), 0, 14) as $date) {
            $d = read_daily($date);
            $daily[] = [
                'date'  => $date,
                'count' => (int) ($d['count'] ?? 0),
                'bytes' => (int) ($d['bytes'] ?? 0),
            ];
        }

        $payload = [
            'ok'    => true,
            'now'   => date('Y-m-d H:i:s'),
            'today' => [
                'bytes' => (int) ($stats['today']['bytes'] ?? 0),
                'count' => (int) ($stats['today']['count'] ?? 0),
            ],
            'total' => [
                'bytes' => (int) ($stats['total']['bytes'] ?? 0),
                'count' => (int) ($stats['total']['count'] ?? 0),
            ],
            'files' => $fileCount,
            'dirs'  => count(top_dirs()),
            'cmp'   => $this->comparePrevDay($stats, $daily),
        ];

        return json($payload, 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    /** 有效下载链接（分页，每页 20） */
    public function links(Request $request): Response
    {
        $this->bootCommon();

        $page = max(1, (int) $request->get('page', 1));
        [$page, $pages, $total, $rows] = $this->paginate(link_list(), $page);

        $rows = array_map(static function ($l) {
            return [
                'token'       => (string) ($l['token'] ?? ''),
                'short'       => substr((string) ($l['token'] ?? ''), 0, 8) . '…',
                'path'        => (string) ($l['path'] ?? ''),
                'remaining_h' => (string) ($l['remaining_h'] ?? ''),
                'downloaded'  => (string) fmt_bytes((int) ($l['downloaded'] ?? 0)),
            ];
        }, $rows);

        return json([
            'ok'    => true,
            'page'  => $page,
            'pages' => $pages,
            'total' => $total,
            'rows'  => array_values($rows),
        ], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    /** 今日访问 / 下载日志（分页，每页 20） */
    public function logs(Request $request): Response
    {
        $this->bootCommon();

        $page = max(1, (int) $request->get('page', 1));
        $daily = read_daily(date('Y-m-d'));
        [$page, $pages, $total, $rows] = $this->paginate($daily['logs'] ?? [], $page);

        $rows = array_map(static function ($l) {
            return [
                'th'     => (string) ($l['th'] ?? ''),
                'act'    => (string) ($l['act'] ?? ''),
                'target' => (string) ($l['target'] ?? ''),
                'bytes'  => (int) ($l['bytes'] ?? 0),
                'ip'     => mask_ip((string) ($l['ip'] ?? ''), (string) ($l['ipt'] ?? 'v4')),
            ];
        }, $rows);

        return json([
            'ok'    => true,
            'page'  => $page,
            'pages' => $pages,
            'total' => $total,
            'rows'  => array_values($rows),
        ], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    /** 访问地域分布统计（近 N 天，用于仪表盘节点地图；结果缓存 60 秒） */
    public function visitmap(Request $request): Response
    {
        $this->bootCommon();
        require_once dirname(__DIR__) . '/geo.php';

        $days = max(1, (int) $request->get('days', 90));
        $days = min($days, 365);

        return json($this->visitmapPayload($days), 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    /** 计算访问地域分布（带 60 秒磁盘缓存；供 API 与页面内联共用） */
    private function visitmapPayload(int $days): array
    {
        $cacheFile = DATA_DIR . '/visitmap.json';
        // 数据指纹：最新日志文件的 mtime。只要有新访问/新文件，指纹变化即重算，
        // 不再依赖 60 秒 TTL，解决"访问了一次地图计数不更新"的问题。
        $sig = 0;
        foreach (list_daily_files() as $f) {
            $m = (int) @filemtime(DATA_DIR . '/' . $f . '.json');
            if ($m > $sig) {
                $sig = $m;
            }
        }
        $cached = @file_get_contents($cacheFile);
        if ($cached !== false && $cached !== '') {
            $c = @json_decode($cached, true);
            if (is_array($c)
                && ($c['ok'] ?? false)
                && (int) ($c['days'] ?? 0) === $days
                && (int) ($c['sig'] ?? -1) === $sig
            ) {
                unset($c['ts'], $c['sig']);
                return $c;
            }
        }

        $cutoff = date('Ymd', strtotime('-' . $days . ' days'));
        $byIp = [];
        $totalVisits = 0;
        $totalDownloads = 0;

        foreach (list_daily_files() as $f) {
            if ($f < $cutoff) {
                continue;
            }
            $daily = read_daily($f);
            foreach ($daily['logs'] ?? [] as $l) {
                $ip = (string) ($l['ip'] ?? '');
                if ($ip === '' || $ip === '0.0.0.0') {
                    continue;
                }
                $act = (string) ($l['act'] ?? 'view');
                $ua = (string) ($l['ua'] ?? '');
                if (!isset($byIp[$ip])) {
                    $byIp[$ip] = ['visit' => 0, 'download' => 0, 'dev' => []];
                }
                if ($act === 'download') {
                    $byIp[$ip]['download']++;
                    $totalDownloads++;
                } else {
                    $byIp[$ip]['visit']++;
                    $totalVisits++;
                }
                $dev = device_info($ua)['device'];
                $byIp[$ip]['dev'][$dev] = ($byIp[$ip]['dev'][$dev] ?? 0) + 1;
            }
        }

        $regions = [];
        $foreign = [];
        $local = null;

        foreach ($byIp as $ip => $d) {
            $r = geo_lookup($ip, false);
            if ((geo_ip_class($ip) === 'local' && !geo_is_whitelisted($ip)) || $r['country'] === 'Reserved') {
                if ($local === null) {
                    $local = ['name' => '本机/内网', 'visits' => 0, 'ips' => [], 'downloads' => 0, 'dev' => []];
                }
                $bucket = &$local;
            } elseif ($r['country'] === '' || $r['country'] === '中国') {
                $prov = $r['province'] ?? '0';
                $name = geo_province_map_name($prov === '0' ? '' : $prov);
                if ($name === '') {
                    $name = '其他';
                }
                if (!isset($regions[$name])) {
                    $regions[$name] = ['name' => $name, 'visits' => 0, 'ips' => [], 'downloads' => 0, 'dev' => []];
                }
                $bucket = &$regions[$name];
            } else {
                $name = $r['country'];
                if (!isset($foreign[$name])) {
                    $foreign[$name] = ['name' => $name, 'visits' => 0, 'ips' => [], 'downloads' => 0, 'dev' => []];
                }
                $bucket = &$foreign[$name];
            }
            $bucket['visits'] += $d['visit'];
            $bucket['downloads'] += $d['download'];
            $bucket['ips'][$ip] = 1;
            foreach ($d['dev'] as $k => $v) {
                $bucket['dev'][$k] = ($bucket['dev'][$k] ?? 0) + $v;
            }
            unset($bucket);
        }

        $pack = static function (array $b): array {
            $dev = [];
            foreach ($b['dev'] as $k => $v) {
                $dev[] = [$k, $v];
            }
            usort($dev, static fn($a, $b) => $b[1] <=> $a[1]);
            return [
                'name'      => $b['name'],
                'visits'    => $b['visits'],
                'ips'       => count($b['ips']),
                'ips_list'  => array_keys($b['ips']),
                'downloads' => $b['downloads'],
                'devices'   => $dev,
            ];
        };

        $regionList = [];
        foreach ($regions as $b) {
            $regionList[] = $pack($b);
        }
        usort($regionList, static fn($a, $b) => $b['visits'] <=> $a['visits']);

        $foreignList = [];
        foreach ($foreign as $b) {
            $foreignList[] = $pack($b);
        }
        usort($foreignList, static fn($a, $b) => $b['visits'] <=> $a['visits']);

        $localInfo = ($local !== null) ? $pack($local) : null;

        geo_cache_save();

        $payload = [
            'ok'      => true,
            'days'    => $days,
            'total'   => [
                'visits'    => $totalVisits,
                'downloads' => $totalDownloads,
                'ips'       => count($byIp),
                'regions'   => count($regionList),
                'foreign'   => count($foreignList),
                'local'     => ($localInfo['visits'] ?? 0),
            ],
            'regions' => $regionList,
            'foreign' => $foreignList,
            'local'   => $localInfo,
        ];

        @file_put_contents($cacheFile, json_encode(array_merge(['ts' => time(), 'days' => $days, 'sig' => $sig], $payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);

        return $payload;
    }

    /** 客户端上报真实公网地理位置（pconline/ip.cn 类检测），写入 GeoIP 缓存并刷新地图 */
    public function reportIp(Request $request): Response
    {
        $this->bootCommon();
        require_once dirname(__DIR__) . '/geo.php';

        $ip = trim((string) $request->post('ip', ''));
        $pro = trim((string) $request->post('pro', ''));
        $proCode = trim((string) $request->post('proCode', ''));
        $city = trim((string) $request->post('city', ''));
        $addr = trim((string) $request->post('addr', ''));

        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return json(['ok' => false, 'msg' => 'ip 无效'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }

        if ((string) $proCode !== '' && (string) $proCode !== '0') {
            // 中国省级（pconline 的 proCode 如 450000=广西）：写省份覆盖
            $prov = geo_province_short_name($pro !== '' ? $pro : $addr);
            geo_override($ip, '中国', $prov, $city);
        } elseif ($pro !== '') {
            // 海外：粗略按 pconline 返回的国别/地区
            geo_override($ip, $pro, '', $city);
        } else {
            return json(['ok' => false, 'msg' => '无地理信息'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }

        // 地图统计缓存立即失效，下一次轮询即显示修正后的省份
        $cf = DATA_DIR . '/visitmap.json';
        if (is_file($cf)) {
            @unlink($cf);
        }

        return json(['ok' => true, 'ip' => $ip], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    /** 通用分页：每页 20（超出自动夹取页码） */
    private function paginate(array $all, int $page, int $size = 20): array
    {
        $total = count($all);
        $pages = max(1, (int) ceil($total / $size));
        $page  = min($page, $pages);
        $rows  = array_slice($all, ($page - 1) * $size, $size);

        return [$page, $pages, $total, $rows];
    }

    // ---------- 系统缓存 ----------
    public function cache(Request $request): Response
    {
        $this->bootCommon();

        $targets = $this->cacheTargets();

        if ($request->isPost()) {
            $items = $this->cachePurge($targets);
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            admin_op_log((string) Session::get('admin_user', ''), '清除缓存', '后台手动清理系统缓存');
        } else {
            $items = $this->cacheSize($targets);
        }

        $bytes = 0;
        $files = 0;
        foreach ($items as $it) {
            $bytes += $it['bytes'];
            $files += $it['files'];
        }

        $headers = ['Cache-Control' => 'no-store'];
        if ($request->isPost()) {
            // 通知浏览器清空本站缓存（HTTPS 下生效；HTTP 下前端另有 Cache Storage 清理 + 强制刷新）
            $headers['Clear-Site-Data'] = '"cache"';
        }

        return json([
            'ok'    => true,
            'bytes' => $bytes,
            'files' => $files,
            'human' => fmt_bytes($bytes),
            'items' => array_values(array_filter($items, static fn($it) => $it['bytes'] > 0)),
        ], 200, $headers, ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    /** 可清理的缓存目标（均为可自动重建；不含 session，避免被登出） */
    private function cacheTargets(): array
    {
        $root = dirname(__DIR__, 2);

        return [
            ['name' => '压缩包缓存（zip）', 'path' => $root . '/data/zipcache'],
            ['name' => '文件索引缓存',       'path' => $root . '/data/filemap.json'],
            ['name' => '框架数据缓存',       'path' => $root . '/runtime/cache'],
            ['name' => '框架运行日志',       'path' => $root . '/runtime/log'],
            ['name' => '框架临时文件',       'path' => $root . '/runtime/temp'],
        ];
    }

    private function cacheSize(array $targets): array
    {
        $out = [];
        foreach ($targets as $t) {
            [$bytes, $files] = $this->measurePath($t['path']);
            $out[] = ['name' => $t['name'], 'bytes' => $bytes, 'files' => $files, 'human' => fmt_bytes($bytes)];
        }
        return $out;
    }

    private function cachePurge(array $targets): array
    {
        $out = [];
        foreach ($targets as $t) {
            $p = $t['path'];
            $bytes = 0;
            $files = 0;

            if (is_file($p)) {
                $s = (int) @filesize($p);
                if (@unlink($p)) {
                    $bytes += $s;
                    $files++;
                }
            } elseif (is_dir($p)) {
                $it = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($p, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($it as $f) {
                    $path = $f->getPathname();
                    if ($f->isFile()) {
                        $s = (int) $f->getSize();
                        if (@unlink($path)) {
                            $bytes += $s;
                            $files++;
                        }
                    } elseif ($f->isDir()) {
                        @rmdir($path);
                    }
                }
            }

            $out[] = ['name' => $t['name'], 'bytes' => $bytes, 'files' => $files, 'human' => fmt_bytes($bytes)];
        }
        return $out;
    }

    private function measurePath(string $p): array
    {
        $bytes = 0;
        $files = 0;

        if (is_file($p)) {
            $bytes = (int) @filesize($p);
            $files = 1;
        } elseif (is_dir($p)) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($p, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $f) {
                if ($f->isFile()) {
                    $bytes += (int) $f->getSize();
                    $files++;
                }
            }
        }

        return [$bytes, $files];
    }

    // ---------- 图片验证码 ----------
    public function captcha(Request $request): Response
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $len   = strlen($chars);
        $code  = '';
        for ($i = 0; $i < 4; $i++) {
            $code .= $chars[random_int(0, $len - 1)];
        }

        Session::set('captcha_code', $code);
        Session::set('captcha_time', time());

        $w = 120;
        $h = 40;
        $im = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($im, 246, 246, 246);
        imagefilledrectangle($im, 0, 0, $w, $h, $bg);

        $line = imagecolorallocate($im, 214, 214, 214);
        for ($i = 0; $i < 5; $i++) {
            imageline($im, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $line);
        }
        $dot = imagecolorallocate($im, 205, 205, 205);
        for ($i = 0; $i < 100; $i++) {
            imagesetpixel($im, random_int(0, $w - 1), random_int(0, $h - 1), $dot);
        }

        // 字体放在站点内（open_basedir 仅允许站点目录与 /tmp）
        $font = null;
        foreach ([
            dirname(__DIR__) . '/data/captcha.ttf',
            '/tmp/captcha.ttf',
        ] as $f) {
            if (@is_file($f)) {
                $font = $f;
                break;
            }
        }

        for ($i = 0; $i < 4; $i++) {
            $color = imagecolorallocate($im, random_int(30, 80), random_int(30, 80), random_int(30, 80));
            $x = 12 + $i * 26;
            $y = 30 + random_int(-3, 3);
            if ($font !== null) {
                imagettftext($im, 19, random_int(-16, 16), $x, $y, $color, $font, $code[$i]);
            } else {
                imagestring($im, 5, $x, 11 + random_int(-3, 3), $code[$i], $color);
            }
        }

        ob_start();
        imagepng($im);
        $data = (string) ob_get_clean();

        return response($data, 200, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma'        => 'no-cache',
        ]);
    }

    // ---------- 登录 / 退出 ----------
    public function login(Request $request): Response
    {
        $this->bootCommon();

        if ((string) Session::get('admin_user', '') !== '') {
            return redirect('/' . admin_base());
        }

        $error = '';

        if ($request->isPost()) {
            $user = trim((string) $request->post('user', ''));
            $pass = (string) $request->post('pass', '');

            // 登录限流：同一 IP 连续失败 5 次锁定 10 分钟（前置拦截，锁定期间不消耗验证码）
            $guard = login_guard_state();
            if ($guard['locked']) {
                $error = '尝试次数过多，请 ' . (int) ceil($guard['retry_after'] / 60) . ' 分钟后重试';
                usleep(300000);
                admin_login_log($user, false, 'IP 已被临时锁定');
            } else {
                // 验证码校验：一次性使用，5 分钟有效，忽略大小写
                $input = strtoupper(trim((string) $request->post('captcha', '')));
                $saved = strtoupper((string) Session::get('captcha_code', ''));
                $ctime = (int) Session::get('captcha_time', 0);
                Session::delete('captcha_code');
                Session::delete('captcha_time');

                if ($saved === '' || $input === '' || $input !== $saved || (time() - $ctime) > 300) {
                    $error = '验证码错误或已过期';
                    usleep(300000);
                    admin_login_log($user, false, '验证码错误或已过期');
                } elseif (admin_verify($user, $pass)) {
                    login_guard_clear();
                    Session::set('admin_user', $user);
                    Session::set('admin_login_at', time());
                    admin_login_log($user, true, '登录成功');
                    return redirect('/' . admin_base());
                } else {
                    $g = login_guard_fail();
                    $error = $g['locked']
                        ? '连续失败次数过多，已临时锁定，请 ' . (int) ceil($g['retry_after'] / 60) . ' 分钟后重试'
                        : '用户名或密码错误';
                    usleep(400000);
                    admin_login_log($user, false, $g['locked'] ? '触发登录锁定' : '用户名或密码错误');
                }
            }
        }

        $html = $this->render('login', ['error' => $error, 'site' => SITE_NAME]);

        return Response::create($html, 'html', $error ? 401 : 200, ['Cache-Control' => 'no-store']);
    }

    public function password(Request $request): Response
    {
        $this->bootCommon();

        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return redirect('/' . admin_base() . '/login');
        }

        $msg = '';
        $ok  = false;

        if ($request->isPost()) {
            $new1 = (string) $request->post('new1', '');
            $new2 = (string) $request->post('new2', '');

            if (strlen($new1) < 6) {
                $msg = '新密码至少 6 位';
            } elseif ($new1 !== $new2) {
                $msg = '两次输入的密码不一致';
            } else {
                admin_set_password($user, $new1);
                admin_op_log($user, '修改密码', '旧密码页保存新密码');
                $ok  = true;
                $msg = '密码已更新，下次登录请使用新密码';
            }
        }

        $html = $this->render('password', ['msg' => $msg, 'ok' => $ok, 'user' => $user]);

        return Response::create($html, 'html', 200, ['Cache-Control' => 'no-store']);
    }

    // ---------- 系统日志 ----------
    public function systemLogs(Request $request): Response
    {
        $this->bootCommon();
        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return redirect('/' . admin_base() . '/login');
        }

        $html = $this->render('system-logs', [
            'user'      => $user,
            'pageTitle' => '系统日志',
            'activeNav' => 'system-logs',
        ]);

        return Response::create($html, 'html', 200, ['Cache-Control' => 'no-store']);
    }

    /**
     * 系统日志 API（GET admin/api/system-logs）
     * 参数：type=login|operation|visit|download；date=Y-m-d；page=1
     * login/operation 来自 logs/ 独立文件；visit/download 来自每日 stats 日志（act 过滤）
     */
    public function systemLogsApi(Request $request): Response
    {
        $this->bootCommon();
        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return $this->jsonOut(['ok' => false, 'msg' => '未登录']);
        }

        $type = (string) $request->get('type', 'access');
        if (!in_array($type, ['login', 'operation', 'visit', 'download', 'access'], true)) {
            $type = 'access';
        }

        $dateRaw = trim((string) $request->get('date', date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw)) {
            $dateRaw = date('Y-m-d');
        }
        $page = max(1, (int) $request->get('page', 1));

        if ($type === 'login' || $type === 'operation') {
            $entries = $this->normalizeSysLogs($type, log_read($type, $dateRaw)['entries']);
        } elseif ($type === 'access') {
            $entries = $this->normalizeAccessLogs(log_read('access', $dateRaw)['entries']);
        } else {
            $daily = read_daily($dateRaw);
            $logs  = [];
            foreach ($daily['logs'] ?? [] as $l) {
                $act = (string) ($l['act'] ?? 'view');
                if ($type === 'download' && $act !== 'download') {
                    continue;
                }
                if ($type === 'visit' && $act === 'download') {
                    continue;
                }
                $detail = (string) ($l['target'] ?? '');
                if ($type === 'download') {
                    if ($detail === '') {
                        $detail = (string) ($l['path'] ?? '');
                    }
                } elseif ($detail === '' || $detail === '页面访问') {
                    $detail = '/';
                }
                $logs[] = [
                    'th'     => (string) ($l['th'] ?? ''),
                    'ip'     => (string) ($l['ip'] ?? ''),
                    'user'   => '',
                    'act'    => $type === 'download' ? '下载' : '访问',
                    'detail' => $detail,
                    'bytes'  => (int) ($l['bytes'] ?? 0),
                    'ua'     => (string) ($l['ua'] ?? ''),
                ];
            }
            $entries = $logs;
        }

        [$page, $pages, $total, $rows] = $this->paginate($entries, $page);

        return $this->jsonOut([
            'ok'    => true,
            'type'  => $type,
            'date'  => $dateRaw,
            'dates' => system_log_dates(),
            'page'  => $page,
            'pages' => $pages,
            'total' => $total,
            'rows'  => array_values($rows),
        ]);
    }

    /** 归一化 login/operation 日志条目为表格行 */
    private function normalizeSysLogs(string $type, array $entries): array
    {
        $rows = [];
        foreach ($entries as $l) {
            if (!is_array($l)) {
                continue;
            }
            $ok = !empty($l['ok']);
            $rows[] = [
                'th'     => (string) ($l['th'] ?? ''),
                'ip'     => (string) ($l['ip'] ?? ''),
                'user'   => (string) ($l['user'] ?? ''),
                'act'    => $type === 'login' ? ($ok ? '成功' : '失败') : (string) ($l['act'] ?? ''),
                'detail' => $type === 'login' ? (string) ($l['detail'] ?? '') : (string) ($l['detail'] ?? ''),
                'bytes'  => 0,
                'ua'     => (string) ($l['ua'] ?? ''),
            ];
        }
        return $rows;
    }

    /** 归一化 access 日志条目为表格行 */
    private function normalizeAccessLogs(array $entries): array
    {
        $rows = [];
        foreach ($entries as $l) {
            if (!is_array($l)) {
                continue;
            }
            $rows[] = [
                'th'     => (string) ($l['th'] ?? ''),
                'method' => (string) ($l['method'] ?? ''),
                'path'   => (string) ($l['path'] ?? ''),
                'note'   => (string) ($l['note'] ?? ''),
                'user'   => (string) ($l['user'] ?? ''),
                'ip'     => (string) ($l['ip'] ?? ''),
                'ua'     => (string) ($l['ua'] ?? ''),
            ];
        }
        return $rows;
    }

    // ---------- 系统设置 ----------
    public function settings(Request $request): Response
    {
        $this->bootCommon();
        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return redirect('/' . admin_base() . '/login');
        }

        $raw = @file_get_contents(ADMIN_HASH_FILE);
        $storedUser = '';
        if ($raw !== false && $raw !== '') {
            $d = json_decode($raw, true);
            if (is_array($d)) {
                $storedUser = (string) ($d['user'] ?? '');
            }
        }

        $html = $this->render('settings', [
            'user'       => $user,
            'storedUser' => $storedUser !== '' ? $storedUser : $user,
            'adminBase'  => '/' . admin_base(),
            'pageTitle'  => '系统设置',
            'activeNav'  => 'settings',
        ]);

        return Response::create($html, 'html', 200, ['Cache-Control' => 'no-store']);
    }

    /**
     * 保存系统设置（POST admin/api/settings/account）
     * 参数：current_pass（必填）、new_user（可选）、new_pass/new_pass2（可选）
     * 密码以 bcrypt 哈希存储，登录走 password_verify，哈希值无法用于登录。
     */
    public function settingsAccount(Request $request): Response
    {
        $this->bootCommon();
        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return $this->jsonOut(['ok' => false, 'msg' => '未登录']);
        }

        $raw = @file_get_contents(ADMIN_HASH_FILE);
        $d = [];
        if ($raw !== false && $raw !== '') {
            $t = json_decode($raw, true);
            if (is_array($t)) {
                $d = $t;
            }
        }
        $storedUser = (string) ($d['user'] ?? '');
        $storedHash = (string) ($d['hash'] ?? '');

        $current = (string) $request->post('current_pass', '');
        $newUser = trim((string) $request->post('new_user', ''));
        $newPass = (string) $request->post('new_pass', '');
        $newPass2 = (string) $request->post('new_pass2', '');

        if ($current === '' || $storedUser === '' || $storedHash === '' || !password_verify($current, $storedHash)) {
            return $this->jsonOut(['ok' => false, 'msg' => '当前密码不正确']);
        }

        // 用户名变更（可为空 = 保持不变）
        $targetUser = $storedUser;
        if ($newUser !== '' && $newUser !== $storedUser) {
            if (!preg_match('/^[A-Za-z0-9_]{3,24}$/', $newUser)) {
                return $this->jsonOut(['ok' => false, 'msg' => '用户名需 3-24 位，仅限字母、数字、下划线']);
            }
            $targetUser = $newUser;
        }

        $targetHash = $storedHash;
        if ($newPass !== '') {
            if (strlen($newPass) < 8) {
                return $this->jsonOut(['ok' => false, 'msg' => '新密码至少 8 位']);
            }
            if (!preg_match('/[A-Za-z]/', $newPass) || !preg_match('/\d/', $newPass)) {
                return $this->jsonOut(['ok' => false, 'msg' => '新密码需同时包含字母和数字']);
            }
            if ($newPass === $targetUser) {
                return $this->jsonOut(['ok' => false, 'msg' => '新密码不能与用户名相同']);
            }
            if ($newPass !== $newPass2) {
                return $this->jsonOut(['ok' => false, 'msg' => '两次输入的新密码不一致']);
            }
            if ($newPass === $storedHash) {
                return $this->jsonOut(['ok' => false, 'msg' => '新密码不能使用当前存储的哈希值']);
            }
            $targetHash = password_hash($newPass, PASSWORD_BCRYPT);
        }

        if ($targetUser === $storedUser && $targetHash === $storedHash) {
            return $this->jsonOut(['ok' => false, 'msg' => '未检测到任何修改']);
        }

        admin_hash_write($targetUser, $targetHash);
        Session::set('admin_user', $targetUser);
        $changed = [];
        if ($targetUser !== $storedUser) {
            $changed[] = '用户名已更新为 ' . $targetUser;
        }
        if ($targetHash !== $storedHash) {
            $changed[] = '密码已更新';
        }
        admin_op_log($user, '系统设置', implode('；', $changed));

        return $this->jsonOut(['ok' => true, 'msg' => implode('；', $changed), 'user' => $targetUser]);
    }

    /** 安全路径：生成 6 位随机小写字母 */
    public function settingsPathGen(Request $request): Response
    {
        $this->bootCommon();
        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return $this->jsonOut(['ok' => false, 'msg' => '未登录']);
        }
        return $this->jsonOut(['ok' => true, 'path' => admin_path_gen()]);
    }

    /** 安全路径：保存自定义后台前缀（需验证当前密码） */
    public function settingsPath(Request $request): Response
    {
        $this->bootCommon();
        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return $this->jsonOut(['ok' => false, 'msg' => '未登录']);
        }

        $current = (string) $request->post('current_pass', '');
        $newPath = trim((string) $request->post('new_path', ''));

        $raw = @file_get_contents(ADMIN_HASH_FILE);
        $d = [];
        if ($raw !== false && $raw !== '') {
            $t = json_decode($raw, true);
            if (is_array($t)) {
                $d = $t;
            }
        }
        $storedHash = (string) ($d['hash'] ?? '');
        if ($storedHash === '' || !password_verify($current, $storedHash)) {
            return $this->jsonOut(['ok' => false, 'msg' => '当前密码不正确']);
        }

        if (!admin_path_valid($newPath)) {
            return $this->jsonOut(['ok' => false, 'msg' => '路径需 2-32 位，限字母/数字，首字符需为字母，且不能与内置路径冲突']);
        }
        if (strtolower($newPath) === admin_base()) {
            return $this->jsonOut(['ok' => false, 'msg' => '新路径与当前路径相同']);
        }

        $settings = settings_load();
        $settings['admin_path'] = $newPath;
        settings_save($settings);

        // 立即使旧会话失效，避免旧路径残留登录态
        Session::clear();

        return $this->jsonOut(['ok' => true, 'msg' => '后台路径已更新为 /' . $newPath, 'path' => $newPath, 'base' => '/' . $newPath]);
    }

    /**
     * 关于程序 - 作者信息（GET admin/about）
     */
    public function about(Request $request): Response
    {
        $this->bootCommon();
        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return redirect('/' . admin_base() . '/login');
        }

        $html = $this->render('about', [
            'user'      => $user,
            'pageTitle' => '关于程序',
            'activeNav' => 'about',
        ]);

        return Response::create($html, 'html', 200, ['Cache-Control' => 'no-store']);
    }

    /**
     * 关于程序 - 框架与引用（GET admin/about/framework）
     */
    public function aboutFramework(Request $request): Response
    {
        $this->bootCommon();
        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return redirect('/' . admin_base() . '/login');
        }

        // Php 版本
        $phpVersion = PHP_VERSION;

        // ThinkPHP 版本：优先读 vendor/composer/installed.json（真实安装版本），
        // 其次框架内置 VERSION 常量，最后兜底 8.x
        $thinkVersion = '';
        $inst = dirname(__DIR__) . '/../vendor/composer/installed.json';
        if (is_file($inst)) {
            $im = @json_decode((string) @file_get_contents($inst), true);
            $pkgs = is_array($im) ? (array) ($im['packages'] ?? []) : [];
            foreach ($pkgs as $pp) {
                if (!is_array($pp)) continue;
                if ((string) ($pp['name'] ?? '') === 'topthink/framework') {
                    $thinkVersion = (string) ($pp['version'] ?? '');
                    break;
                }
            }
        }
        if ($thinkVersion === '') {
            $tw = dirname(__DIR__) . '/../vendor/topthink/framework';
            if (is_file($tw . '/src/think/App.php')) {
                $src = (string) @file_get_contents($tw . '/src/think/App.php');
                if (preg_match('/VERSION\s*=\s*[\'"]([0-9][\w.\-]*)[\'"]/', $src, $mm)) {
                    $thinkVersion = $mm[1];
                }
            }
        }
        if ($thinkVersion === '') {
            $thinkVersion = '8.x';
        }
        if (strpos($thinkVersion, 'v') !== 0) {
            $thinkVersion = 'v' . $thinkVersion;
        }
        // Composer 依赖清单（本人项目直接依赖 + 间接依赖，合并去重排序）
        $deps = [];
        $lock = dirname(__DIR__) . '/../composer.lock';
        if (is_file($lock)) {
            $lm = @json_decode((string) @file_get_contents($lock), true);
            $list = is_array($lm) ? (array) ($lm['packages'] ?? []) : [];
            foreach ($list as $pkg) {
                if (!is_array($pkg)) continue;
                $n  = (string) ($pkg['name'] ?? '');
                $v  = (string) ($pkg['version'] ?? '');
                if ($n !== '') {
                    $deps[] = $n . ' ' . $v;
                }
            }
            sort($deps, SORT_STRING);
        }
        if ($deps === []) {
            $deps = ['topthink/framework ^8.0'];
        }

        // ECharts 本地版本（压缩文件头部为 Apache 注释，版本号一般紧随其后）
        $echartsVersion = '5.x';
        $echFile = dirname(__DIR__) . '/../public/assets/map/echarts.min.js';
        if (is_file($echFile)) {
            $fp = @fopen($echFile, 'rb');
            if ($fp) {
                $ech = (string) fread($fp, 150000);
                fclose($fp);
                if (preg_match('/([0-9]+\.[0-9]+\.[0-9]+)/', $ech, $mm)) {
                    // 优先找 ECharts 常见主版本号（跳过可能出现的无关构建号）
                    if (preg_match('/([2-6]\.[0-9]+\.[0-9]+)/', $ech, $m2)) {
                        $echartsVersion = $m2[1];
                    } else {
                        $echartsVersion = $mm[1];
                    }
                }
            }
        }

        $html = $this->render('about-framework', [
            'user'           => $user,
            'pageTitle'      => '框架与引用',
            'activeNav'      => 'about-framework',
            'phpVersion'     => $phpVersion,
            'thinkVersion'   => $thinkVersion,
            'deps'           => $deps,
            'echartsVersion' => $echartsVersion,
        ]);

        return Response::create($html, 'html', 200, ['Cache-Control' => 'no-store']);
    }

    // ---------- 首页管理 ----------
    public function home(Request $request): Response
    {
        $this->bootCommon();

        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return redirect('/' . admin_base() . '/login');
        }

        $cfg = home_config_load();
        $st  = settings_load();

        $html = $this->render('home', [
            'user'            => $user,
            'pageTitle'       => '首页管理',
            'activeNav'       => 'home',
            'homeEnabled'     => (bool) $cfg['enabled'],
            'fontScale'       => (float) $cfg['font_scale'],
            'siteName'        => (string) $st['site_name'],
            'siteSlogan'      => (string) $st['site_slogan'],
            'altEnabled'      => (bool) $st['alt_enabled'],
            'v4Url'           => (string) $st['v4_url'],
            'v6Url'           => (string) $st['v6_url'],
            'donateEnabled'   => (bool) $st['donate_enabled'],
            'netpanelEnabled' => (bool) $st['netpanel_enabled'],
            'netpanelOn'      => is_dir(dirname(__DIR__, 2) . '/public/networkpanel'),
            'masEnabled'      => (bool) $st['mas_enabled'],
            'recentEnabled'   => (bool) $st['recent_enabled'],
        ]);

        return Response::create($html, 'html', 200, ['Cache-Control' => 'no-store']);
    }

    public function homeStyle(Request $request): Response
    {
        $this->bootCommon();

        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return redirect('/' . admin_base() . '/login');
        }

        $cfg = home_config_load();

        $html = $this->render('home-style', [
            'user'       => $user,
            'pageTitle'  => '个性化',
            'activeNav'  => 'homeStyle',
            'homeEnabled'=> (bool) $cfg['enabled'],
            'fontScale'  => (float) $cfg['font_scale'],
            'menuRound'  => (int) $cfg['menu_round'],
        ]);

        return Response::create($html, 'html', 200, ['Cache-Control' => 'no-store']);
    }

    /**
     * 可视化编辑：拼积木式调整首页顶部按钮的可见性 / 顺序 / 文字（GET admin/visual）
     */
    public function visual(Request $request): Response
    {
        $this->bootCommon();

        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return redirect('/' . admin_base() . '/login');
        }

        $st  = settings_load();
        $cfg = home_config_load();

        $html = $this->render('visual', [
            'user'         => $user,
            'pageTitle'    => '可视化编辑',
            'activeNav'    => 'visual',
            'homeEnabled'  => (bool) $cfg['enabled'],
            'layout'       => $st['layout'],
            'blocks'       => layout_blocks(),
            'styles'       => layout_styles(),
            'layoutJson'   => json_encode($st['layout'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'blocksJson'   => json_encode(layout_blocks(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'stylesJson'   => json_encode(layout_styles(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'defaultsJson' => json_encode(layout_defaults(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'stickersJson' => json_encode($st['stickers'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return Response::create($html, 'html', 200, ['Cache-Control' => 'no-store']);
    }

    /**
     * 保存可视化编辑布局（POST admin/api/layout）
     * 请求体：{"blocks":[{"id":"support","show":true,"label":"..."}, ...]}（顺序即摆放顺序）
     */
    public function layoutSave(Request $request): Response
    {
        $this->bootCommon();

        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return $this->jsonOut(['ok' => false, 'msg' => '未登录']);
        }

        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data) || !isset($data['blocks'])) {
            $post = $request->post();
            $data = is_array($post) ? $post : [];
        }
        $blocks = $data['blocks'] ?? null;

        $in = [];
        if (is_array($blocks)) {
            foreach ($blocks as $b) {
                if (!is_array($b)) {
                    continue;
                }
                $id = (string) ($b['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $in[$id] = $b;
            }
        }
        $out = layout_normalize($in);

        $st           = settings_load();
        $st['layout'] = $out;
        if (array_key_exists('stickers', $data)) {
            $st['stickers'] = stickers_normalize($data['stickers']);
        }
        settings_save($st);

        admin_op_log($user, '布局保存', '保存首页布局与贴纸配置');

        return $this->jsonOut(['ok' => true, 'layout' => $out, 'stickers' => $st['stickers']]);
    }

    /**
     * 统一 JSON 输出（带 no-store）
     */
    private function jsonOut(array $payload): Response
    {
        return json($payload, 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    /**
     * 贴纸图库页（GET admin/sticker）
     */
    public function sticker(Request $request): Response
    {
        $this->bootCommon();
        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return redirect('/' . admin_base() . '/login');
        }

        $html = $this->render('sticker', [
            'user'      => $user,
            'pageTitle' => '贴纸图库',
            'activeNav' => 'files',
        ]);

        return Response::create($html, 'html', 200, ['Cache-Control' => 'no-store']);
    }

    /**
     * 贴纸列表（GET admin/api/stickers）
     */
    public function stickers(Request $request): Response
    {
        $this->bootCommon();
        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return $this->jsonOut(['ok' => false, 'msg' => '未登录']);
        }
        if (!is_dir(STICKER_DIR)) {
            @mkdir(STICKER_DIR, 0755, true);
        }
        $list = [];
        foreach (glob(STICKER_DIR . '/*') ?: [] as $f) {
            if (!is_file($f)) {
                continue;
            }
            $name = basename((string) $f);
            if (!sticker_ext_ok($name)) {
                continue;
            }
            $list[] = [
                'name' => $name,
                'url'  => '/stk/' . rawurlencode($name),
                'size' => (int) @filesize($f),
            ];
        }
        usort($list, static fn($a, $b) => strcmp($a['name'], $b['name']));
        return $this->jsonOut(['ok' => true, 'list' => $list]);
    }

    /**
     * 贴纸上传（POST admin/api/sticker/upload）— 单张图片
     */
    public function stickerUpload(Request $request): Response
    {
        $this->bootCommon();
        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return $this->jsonOut(['ok' => false, 'msg' => '未登录']);
        }
        if (!is_dir(STICKER_DIR)) {
            @mkdir(STICKER_DIR, 0755, true);
        }

        $file = $request->file('file');
        if (!$file || !is_object($file) || !method_exists($file, 'getOriginalName')) {
            return $this->jsonOut(['ok' => false, 'msg' => '未收到文件']);
        }
        if (method_exists($file, 'getSize') && (int) $file->getSize() > 8 * 1024 * 1024) {
            return $this->jsonOut(['ok' => false, 'msg' => '单张图片不能超过 8MB']);
        }
        if (method_exists($file, 'getError') && (int) $file->getError() !== UPLOAD_ERR_OK) {
            return $this->jsonOut(['ok' => false, 'msg' => '上传失败（文件无效或损坏）']);
        }
        $name = $this->cleanBaseName($file->getOriginalName());
        $name = sticker_safe_name($name);
        if ($name === '') {
            return $this->jsonOut(['ok' => false, 'msg' => '仅支持 png / jpg / webp 图片']);
        }
        $base = pathinfo($name, PATHINFO_FILENAME);
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $n    = 0;
        do {
            $cand   = $base . ($n > 0 ? '_' . $n : '') . '.' . $ext;
            $target = STICKER_DIR . '/' . $cand;
            $n++;
        } while (file_exists($target));

        try {
            $file->move(STICKER_DIR, basename($cand));
        } catch (\Throwable $e) {
            return $this->jsonOut(['ok' => false, 'msg' => '保存失败']);
        }
        // 白底图自动转透明 PNG（深色模式下不再"四周变白"）；转换成功则文件名变为 *.png
        $final = sticker_autoclear(STICKER_DIR . '/' . basename($cand));
        admin_op_log($user, '上传贴纸', '上传 ' . basename($final));
        return $this->jsonOut(['ok' => true, 'name' => $final, 'url' => '/stk/' . rawurlencode($final), 'renamed' => $final !== basename($cand)]);
    }

    /**
     * 贴纸删除（POST admin/api/sticker/delete）
     */
    public function stickerDelete(Request $request): Response
    {
        $this->bootCommon();
        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return $this->jsonOut(['ok' => false, 'msg' => '未登录']);
        }

        $data = json_decode((string) $request->getContent(), true);
        $raw  = $data['file'] ?? ($request->post('file') ?? '');
        $name = sticker_safe_name((string) $raw);
        if ($name === '') {
            return $this->jsonOut(['ok' => false, 'msg' => '名称无效']);
        }
        $full = STICKER_DIR . '/' . $name;
        if (is_file($full)) {
            @unlink($full);
        }
        admin_op_log($user, '删除贴纸', '删除 ' . $name);
        return $this->jsonOut(['ok' => true]);
    }

    public function files(Request $request): Response
    {
        $this->bootCommon();

        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return redirect('/' . admin_base() . '/login');
        }

        // 目录路径清洗：只允许 app 目录内的相对路径，出现 .. 直接整体拒绝
        $rawDir = str_replace('\\', '/', trim((string) $request->get('dir', '')));
        $parts = [];
        foreach (explode('/', $rawDir) as $seg) {
            $seg = trim($seg);
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                $parts = [];
                break;
            }
            $parts[] = $seg;
        }
        $dir = implode('/', $parts);
        $abs = APP_ROOT . ($dir === '' ? '' : '/' . $dir);
        if (!is_dir($abs)) {
            $dir = '';
            $abs = APP_ROOT;
            $parts = [];
        }

        $parent = $dir === '' ? '' : substr($dir, 0, (int) strrpos($dir, '/'));

        $crumbs = [['name' => 'app', 'dir' => '']];
        $acc = '';
        foreach ($parts as $p) {
            $acc = $acc === '' ? $p : $acc . '/' . $p;
            $crumbs[] = ['name' => $p, 'dir' => $acc];
        }

        $entries = [];
        $fileCount = 0;
        $dirCount = 0;
        $dirBytes = 0;
        $secret = secret_quick();

        // 清洗展示名：去掉 win-x64-/win-x86- 等前缀与 -zip 后缀；派生架构（arch_of）与系统
        $cleanName = static function (string $name): string {
            $n = preg_replace('/^(win|windows)[-_]?(x64|x86|arm64|arm)/i', '', $name);
            $n = preg_replace('/[-_ ]?zip$/i', '', (string)$n);
            $n = trim((string)$n, '-_ ');
            return $n === '' ? $name : (string)$n;
        };
        $osOf = static function (string $name): string {
            $n = strtolower($name);
            if (str_contains($n, 'win') || str_contains($n, 'windows')) {
                return 'Windows';
            }
            if (str_contains($n, 'linux')) {
                return 'Linux';
            }
            if (str_contains($n, 'mac')) {
                return 'macOS';
            }
            return '';
        };

        $names = @scandir($abs);
        if ($names !== false) {
            foreach ($names as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $p = $abs . '/' . $name;
                $isDir = is_dir($p);
                $size = $isDir ? dir_size($p) : (int) @filesize($p);
                $rel = $dir === '' ? $name : $dir . '/' . $name;
                $meta = item_meta_get($rel);
                $arch = $meta['arch'] !== '' ? $meta['arch'] : (arch_of($name) ?: '');
                $os   = $meta['os'] !== '' ? $meta['os'] : $osOf($name);
                $zip  = $meta['zip'] !== null ? $meta['zip'] : ($isDir && str_ends_with($name, '-zip'));
                if ($isDir) {
                    $dirCount++;
                } else {
                    $fileCount++;
                }
                $dirBytes += $size;
                $entries[] = [
                    'name'   => $name,
                    'dname'  => $cleanName($name),
                    'arch'   => $arch,
                    'os'     => $os,
                    'zip'    => (bool) $zip,
                    'hidden' => (bool) ($meta['hidden'] ?? false),
                    'dir'    => $isDir,
                    'rel'    => $rel,
                    'size'   => $size,
                    'size_h' => fmt_bytes($size),
                    'mtime'  => date('Y-m-d H:i', (int) @filemtime($p)),
                    'link'   => dl_link($rel, $secret),
                    'lock'   => lock_guard($rel) !== null,
                    'lockby' => lock_guard($rel),
                ];
            }
            usort($entries, static function ($a, $b) {
                if ($a['dir'] !== $b['dir']) {
                    return $a['dir'] ? -1 : 1;
                }
                return strnatcasecmp($a['name'], $b['name']);
            });
        }

        // 顶部"筛选/快捷跳转"用的顶级目录列表
        $top = [];
        $topNames = @scandir(APP_ROOT);
        if ($topNames !== false) {
            foreach ($topNames as $tn) {
                if ($tn === '.' || $tn === '..' || !is_dir(APP_ROOT . '/' . $tn)) {
                    continue;
                }
                $top[] = ['name' => $tn, 'dname' => $cleanName($tn)];
            }
            usort($top, static fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
        }

        $html = $this->render('files', [
            'user'      => $user,
            'pageTitle' => '文件管理',
            'activeNav' => 'files',
            'site'      => SITE_NAME,
            'dir'       => $dir,
            'parent'    => $parent,
            'crumbs'    => $crumbs,
            'entries'   => $entries,
            'top'       => $top,
            'dirCount'  => $dirCount,
            'fileCount' => $fileCount,
            'dirBytes'  => fmt_bytes($dirBytes),
        ]);

        return Response::create($html, 'html', 200, ['Cache-Control' => 'no-store']);
    }

    private function filesCat(array $post): array
    {
        // 与 files() 一致的目录路径清洗，返回 [dir, abs]
        $rawDir = str_replace('\\', '/', trim((string) ($post['dir'] ?? '')));
        $parts = [];
        foreach (explode('/', $rawDir) as $seg) {
            $seg = trim($seg);
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                $parts = [];
                break;
            }
            $parts[] = $seg;
        }
        $dir = implode('/', $parts);
        $abs = APP_ROOT . ($dir === '' ? '' : '/' . $dir);
        if (!is_dir($abs)) {
            $dir = '';
            $abs = APP_ROOT;
        }
        return [$dir, $abs];
    }

    private function cleanBaseName(string $name): string
    {
        $n = preg_replace('/[\/\\\\]/', '', trim($name));
        $n = trim((string) $n);
        if ($n === '' || $n === '.' || $n === '..') {
            return '';
        }
        return $n;
    }

    /**
     * 上传文件到当前目录（POST admin/api/files/upload）
     * 参数：dir（相对路径）、files[]（多文件）
     */
    public function filesUpload(Request $request): Response
    {
        $this->bootCommon();
        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return json(['ok' => false, 'msg' => '未登录'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }

        [$dir, $abs] = $this->filesCat($request->post());
        $done = [];
        $fail = [];

        $files = $request->file('files');
        if (!is_array($files)) {
            $files = $files !== null ? [$files] : [];
        }
        foreach ($files as $file) {
            try {
                if (!$file || !is_object($file) || !method_exists($file, 'getOriginalName')) {
                    throw new \Exception('无效文件对象');
                }
                $name = $this->cleanBaseName($file->getOriginalName());
                if ($name === '') {
                    throw new \Exception('文件名无效');
                }
                $base = pathinfo($name, PATHINFO_FILENAME);
                $ext  = pathinfo($name, PATHINFO_EXTENSION);
                $n = 0;
                do {
                    $cand   = $base . ($n > 0 ? " ($n)" : '') . ($ext !== '' ? '.' . $ext : '');
                    $target = $abs . '/' . $cand;
                    $n++;
                } while (file_exists($target));
                $file->move($abs, $cand);
                $done[] = $cand;
            } catch (\Throwable $e) {
                $fail[] = (is_object($file ?? null) && method_exists($file, 'getOriginalName')) ? $file->getOriginalName() : '未知文件';
            }
        }

        $combined = array_merge($done, $fail);
        if ($combined) {
            admin_op_log($user, '文件上传', $dir === '' ? ('上传 ' . implode('、', array_slice($done ?: $fail, 0, 5))) : ($dir . ' 上传 ' . implode('、', array_slice($done ?: $fail, 0, 5))));
        }

        return json(['ok' => count($fail) === 0, 'done' => $done, 'fail' => $fail, 'msg' => count($fail) > 0 ? '部分文件上传失败' : ''], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    /**
     * 重命名（POST admin/api/files/rename）
     * 参数：dir、name（原相对当前目录名）、newName
     */
    public function filesRename(Request $request): Response
    {
        $this->bootCommon();
        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return json(['ok' => false, 'msg' => '未登录'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }

        [$dir, $abs] = $this->filesCat($request->post());
        $name = $this->cleanBaseName((string) ($request->post('name') ?? ''));
        $new  = $this->cleanBaseName((string) ($request->post('newName') ?? ''));
        if ($name === '' || $new === '') {
            return json(['ok' => false, 'msg' => '名称无效'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }
        $src = $abs . '/' . $name;
        $dst = $abs . '/' . $new;
        if (!file_exists($src)) {
            return json(['ok' => false, 'msg' => '原项不存在'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }
        if (file_exists($dst)) {
            return json(['ok' => false, 'msg' => '新名称已存在'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }
        if (!@rename($src, $dst)) {
            return json(['ok' => false, 'msg' => '重命名失败'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }
        $relOld = $dir === '' ? $name : $dir . '/' . $name;
        $relNew = $dir === '' ? $new : $dir . '/' . $new;
        item_meta_move($relOld, $relNew);
        item_lock_move($relOld, $relNew);
        admin_op_log($user, '文件重命名', $relOld . ' → ' . $relNew);
        return json(['ok' => true, 'msg' => '已重命名'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    /**
     * 修改参数（POST admin/api/files/meta）— 单条或批量
     * 参数：items（JSON 相对路径数组）、arch、os、zip（'1'/'0'/''=自动）
     */
    /**
     * 设置/清除密码保护（POST admin/api/files/lock）
     * 参数：items（JSON 相对路径数组）、pwd（非空=设置/修改；空=清除）
     */
    public function filesLock(Request $request): Response
    {
        $this->bootCommon();
        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return json(['ok' => false, 'msg' => '未登录'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }

        $rawItems = $request->post('items');
        $items = is_array($rawItems) ? $rawItems : json_decode((string) $rawItems, true);
        if (!is_array($items) || $items === []) {
            return json(['ok' => false, 'msg' => '未选择任何项目'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }

        $pwd = (string) ($request->post('pwd') ?? '');
        $set = $pwd !== '';

        // 清除密码时要求密码留空即可；设置密码要求至少 4 位
        if ($set && strlen($pwd) < 4) {
            return json(['ok' => false, 'msg' => '密码至少 4 位'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }

        $count = 0;
        $failed = 0;
        foreach ($items as $rel) {
            $rel = trim((string) $rel);
            if ($rel === '') {
                continue;
            }
            $abs = realpath(APP_ROOT . '/' . $rel);
            if ($abs === false || ($abs !== APP_ROOT && !str_starts_with($abs, APP_ROOT . '/'))) {
                $failed++;
                continue;
            }
            if ($set) {
                item_lock_set($rel, $pwd);
            } else {
                item_lock_clear($rel);
            }
            $count++;
        }

        admin_op_log($user, $set ? '设置密码保护' : '清除密码保护', ($set ? '加密 ' : '解密 ') . $count . ' 项：' . mb_substr(implode('、', array_map('strval', array_slice($items, 0, 5))), 0, 120));

        return json([
            'ok'   => $failed === 0,
            'count' => $count,
            'fail' => $failed,
            'msg'  => $set ? "已为 {$count} 项设置密码保护" : "已清除 {$count} 项的密码保护",
        ], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    public function filesMeta(Request $request): Response
    {
        $this->bootCommon();
        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return json(['ok' => false, 'msg' => '未登录'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }

        $rawItems = $request->post('items');
        $items = is_array($rawItems) ? $rawItems : json_decode((string) $rawItems, true);
        if (!is_array($items) || $items === []) {
            return json(['ok' => false, 'msg' => '未选择任何项目'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }

        $arch = trim((string) ($request->post('arch') ?? ''));
        $os   = trim((string) ($request->post('os') ?? ''));
        $zipRaw = (string) ($request->post('zip') ?? '');
        $zip = $zipRaw === '' ? null : (($zipRaw === '1' || strtolower($zipRaw) === 'true') ? true : false);
        $hidRaw = (string) ($request->post('hidden') ?? '');
        $hidden = $hidRaw === '' ? null : (($hidRaw === '1' || strtolower($hidRaw) === 'true') ? true : false);

        $count = 0;
        foreach ($items as $rel) {
            $rel = trim((string) $rel);
            if ($rel === '') {
                continue;
            }
            $abs = realpath(APP_ROOT . '/' . $rel);
            if ($abs === false || ($abs !== APP_ROOT && !str_starts_with($abs, APP_ROOT . '/'))) {
                continue;
            }
            item_meta_set($rel, $arch, $os, $zip, $hidden);
            $count++;
        }

        return json(['ok' => true, 'count' => $count, 'msg' => "已更新 {$count} 项"], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    /**
     * 生成批量打包下载链接（POST admin/api/files/bulk-link）
     * 参数：items（JSON 相对路径数组）
     */
    public function filesBulkLink(Request $request): Response
    {
        $this->bootCommon();
        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return json(['ok' => false, 'msg' => '未登录'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }

        $rawItems = $request->post('items');
        $items = is_array($rawItems) ? $rawItems : json_decode((string) $rawItems, true);
        if (!is_array($items) || $items === []) {
            return json(['ok' => false, 'msg' => '未选择任何项目'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }

        $token = batch_link_create(array_map('strval', $items), secret_quick());
        if ($token === '') {
            return json(['ok' => false, 'msg' => '链接生成失败,所选项目可能无效'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }

        admin_op_log($user, '生成批量链接', '打包 ' . count($items) . ' 项：' . mb_substr(implode('、', array_map('strval', array_slice($items, 0, 5))), 0, 120));

        return json(['ok' => true, 'url' => 'download.php?token=' . urlencode($token)], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    /**
     * 删除文件/目录（单条或批量，POST admin/api/files/delete）
     * 参数：dir、items（JSON 数组，按目录内相对名）
     */
    public function filesDelete(Request $request): Response
    {
        $this->bootCommon();

        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return json(['ok' => false, 'msg' => '未登录'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }

        [$dir, $abs] = $this->filesCat($request->post());

        $rawItems = $request->post('items');
        $items = is_array($rawItems) ? $rawItems : json_decode((string) $rawItems, true);
        if (!is_array($items) || $items === []) {
            return json(['ok' => false, 'msg' => '未选择任何项目'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }

        $deleted = [];
        $failed  = [];
        $freed   = 0;
        foreach ($items as $name) {
            $name = $this->cleanBaseName((string) $name);
            if ($name === '' || $name === '.' || $name === '..') {
                $failed[] = '(无效名称)';
                continue;
            }
            $absT = realpath($abs . '/' . $name);
            if ($absT === false || $absT === APP_ROOT || !str_starts_with($absT, APP_ROOT . '/')) {
                $failed[] = $name;
                continue;
            }
            $freed += is_dir($absT) ? dir_size($absT) : (int) @filesize($absT);
            if (!rm_recursive($absT)) {
                $failed[] = $name;
                continue;
            }
            $rel = $dir === '' ? $name : $dir . '/' . $name;
            item_meta_drop($rel);
            item_lock_drop($rel);
            $deleted[] = $name;
        }

        admin_op_log($user, '删除文件', $dir === '' ? ('删除 ' . implode('、', array_slice($deleted, 0, 5))) : ($dir . ' 删除 ' . implode('、', array_slice($deleted, 0, 5))));

        return json([
            'ok'      => count($failed) === 0,
            'deleted' => $deleted,
            'fail'    => $failed,
            'freed'   => $freed,
            'msg'     => count($failed) > 0 ? '部分项目删除失败' : "已删除 " . count($deleted) . ' 项',
        ], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    /**
     * 计算待删除项目总大小（POST admin/api/files/size）
     * 参数：dir（相对路径）、items[]（项目名）
     */
    public function filesSize(Request $request): Response
    {
        $this->bootCommon();

        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return json(['ok' => false, 'msg' => '未登录'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }

        [$dir, $abs] = $this->filesCat($request->post());

        $rawItems = $request->post('items');
        $items = is_array($rawItems) ? $rawItems : json_decode((string) $rawItems, true);
        if (!is_array($items) || $items === []) {
            return json(['ok' => false, 'total' => 0], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }

        $total = 0;
        foreach ($items as $name) {
            $name = $this->cleanBaseName((string) $name);
            if ($name === '' || $name === '.' || $name === '..') {
                continue;
            }
            $absT = realpath($abs . '/' . $name);
            if ($absT === false || $absT === APP_ROOT || !str_starts_with($absT, APP_ROOT . '/')) {
                continue;
            }
            $total += is_dir($absT) ? dir_size($absT) : (int) @filesize($absT);
        }

        return json(['ok' => true, 'total' => $total], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    /**
     * 新建目录（POST admin/api/files/mkdir）
     * 参数：dir（相对路径）、name（目录名）、arch/os/zip/hidden（可选，不传则保持默认自动检测）
     */
    public function filesMkdir(Request $request): Response
    {
        $this->bootCommon();

        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return json(['ok' => false, 'msg' => '未登录'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }

        [$dir, $abs] = $this->filesCat($request->post());

        $name = $this->cleanBaseName((string) $request->post('name', ''));
        if ($name === '') {
            return json(['ok' => false, 'msg' => '请输入有效的目录名'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }
        $target = $abs . '/' . $name;
        if (is_dir($target) || file_exists($target)) {
            return json(['ok' => false, 'msg' => '同名项目已存在'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }

        if (!@mkdir($target, 0755, true)) {
            return json(['ok' => false, 'msg' => '目录创建失败（检查权限）'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }

        // 可选参数：只有显式传入才写 items.json，否则保持默认（自动检测）
        $rel  = $dir === '' ? $name : $dir . '/' . $name;
        $arch = trim((string) ($request->post('arch') ?? ''));
        $os   = trim((string) ($request->post('os') ?? ''));
        $zipRaw = (string) ($request->post('zip') ?? '');
        $zip = $zipRaw === '' ? null : (($zipRaw === '1' || strtolower($zipRaw) === 'true') ? true : false);
        $hidRaw = (string) ($request->post('hidden') ?? '');
        $hidden = ($hidRaw === '' || $hidRaw === 'keep') ? null : (($hidRaw === '1' || strtolower($hidRaw) === 'true') ? true : false);
        if ($arch !== '' || $os !== '' || $zip !== null || $hidden !== null) {
            item_meta_set($rel, $arch, $os, $zip, $hidden);
        }

        admin_op_log($user, '新建目录', '创建目录：' . $rel);

        return json([
            'ok'   => true,
            'rel'  => $rel,
            'name' => $name,
            'msg'  => '目录已创建' . ($dir === '' ? '' : '（当前目录：' . $dir . '）'),
        ], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    public function homeSave(Request $request): Response
    {
        $this->bootCommon();

        $s = home_config_load();

        $en = $request->post('enabled');
        if ($en !== null) {
            $s['enabled'] = ((int) $en === 1);
        }

        $fs = $request->post('font_scale');
        if ($fs !== null && $fs !== '') {
            $v = (float) $fs;
            $s['font_scale'] = ($v < 0.5) ? 0.5 : (($v > 2.0) ? 2.0 : $v);
        }

        $mr = $request->post('menu_round');
        if ($mr !== null && $mr !== '') {
            $v2 = (int) $mr;
            $s['menu_round'] = ($v2 < 0) ? 0 : (($v2 > 2) ? 2 : $v2);
        }

        home_config_save($s);

        admin_op_log((string) Session::get('admin_user', ''), '首页开关', '首页 ' . ($s['enabled'] ? '开启' : '关闭') . (isset($s['font_scale']) && $s['font_scale'] != 1.0 ? '，缩放 ' . $s['font_scale'] : ''));

        return json([
            'ok'         => true,
            'enabled'    => $s['enabled'],
            'font_scale' => $s['font_scale'],
        ], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    public function logout(Request $request): Response
    {
        Session::clear();

        return redirect('/' . admin_base() . '/login');
    }

    /**
     * 首页功能总开关（POST admin/api/feat）
     * 字段：site_name/site_slogan、alt_enabled+v4_url+v6_url、netpanel_enabled、mas_enabled、
     *      recent_enabled、donate_enabled、可选上传 donate_image（替换赞赏图）
     */
    public function featSave(Request $request): Response
    {
        $this->bootCommon();

        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return json(['ok' => false, 'msg' => '未登录'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }

        $st = settings_load();

        $post = $request->post();
        foreach (['alt_enabled', 'netpanel_enabled', 'mas_enabled', 'recent_enabled', 'donate_enabled'] as $k) {
            if (array_key_exists($k, $post) && !is_array($post[$k])) {
                $st[$k] = (((string) $post[$k]) === '1' || strtolower((string) $post[$k]) === 'true');
            }
        }
        foreach (['v4_url', 'v6_url', 'site_name', 'site_slogan'] as $k) {
            if (array_key_exists($k, $post) && !is_array($post[$k])) {
                $v = trim((string) $post[$k]);
                if ($k === 'site_name' && $v === '') $v = 'God Supremus 的工具站';
                if ($k === 'site_slogan' && $v === '') $v = '杀毒 · 安全 · 应急工具分享';
                $st[$k] = $v;
            }
        }

        // 测速面板目录物理开关（关闭后目录改名，直连路径即 404）
        netpanel_sync((bool) $st['netpanel_enabled']);

        // Windows 激活静态页物理开关（关闭后 activation.html 改名，直连路径即 404）
        mas_asset_sync((bool) $st['mas_enabled']);

        // 赞赏图替换：写入 data/jpg/zs.jpg，前端与后台预览均经 /img.php?token= 令牌路由读取（同源）
        $img = $request->file('donate_image');
        $fileTried = isset($_FILES['donate_image'])
            && ($_FILES['donate_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            && ($_FILES['donate_image']['tmp_name'] ?? '') !== '';
        if ($fileTried && ($img === null || !is_object($img) || !method_exists($img, 'getOriginalName'))) {
            return json(['ok' => false, 'msg' => '文件未通过上传校验（可能超过大小限制）'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }
        if ($img !== null && is_object($img) && method_exists($img, 'getOriginalName')) {
            $ext = strtolower(pathinfo((string) $img->getOriginalName(), PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp'], true)) {
                return json(['ok' => false, 'msg' => '仅支持 png/jpg/webp/gif 图片'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
            }
            $src = (string) $img->getPathname();
            if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
                $raw = @file_get_contents($src);
                $im  = ($raw !== false) ? @imagecreatefromstring($raw) : false;
                if ($im === false) {
                    return json(['ok' => false, 'msg' => '不是有效的图片文件，请重新选择'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
                }
                $ok = @imagejpeg($im, DATA_DIR . '/jpg/zs.jpg', 92);
                if (is_object($im) && $im instanceof \GdImage) {
                    @imagedestroy($im);
                }
                if (!$ok) {
                    return json(['ok' => false, 'msg' => '图片保存失败'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
                }
            } else {
                $finfo = function_exists('finfo_open') ? @finfo_open(FILEINFO_MIME_TYPE) : false;
                $mime  = $finfo ? @finfo_file($finfo, $src) : '';
                if ($finfo) {
                    @finfo_close($finfo);
                }
                if ($mime !== '' && strpos($mime, 'image/') !== 0) {
                    return json(['ok' => false, 'msg' => '不是有效的图片文件，请重新选择'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
                }
                $raw = @file_get_contents($src);
                if ($raw === false || @file_put_contents(DATA_DIR . '/jpg/zs.jpg', $raw) === false) {
                    return json(['ok' => false, 'msg' => '图片保存失败'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
                }
            }
        }

        settings_save($st);

        admin_op_log($user, '功能设置', implode('、', array_filter([
            isset($post['site_name']) ? '站点名' : '',
            isset($post['alt_enabled']) ? 'IPv4/IPv6 跳转' : '',
            isset($post['netpanel_enabled']) ? '测速面板' : '',
            isset($post['mas_enabled']) ? '激活工具' : '',
            isset($post['recent_enabled']) ? '最近操作' : '',
            isset($post['donate_enabled']) || $fileTried ? '支持作者/赞赏图' : '',
        ])));

        return json([
            'ok'          => true,
            'site_name'   => $st['site_name'],
            'site_slogan' => $st['site_slogan'],
        ], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    public function notice(Request $request): Response
    {
        $this->bootCommon();

        $user = (string) Session::get('admin_user', '');
        if ($user === '') {
            return redirect('/' . admin_base() . '/login');
        }

        $cfg = notice_config_load();

        $html = $this->render('notice', [
            'user'         => $user,
            'pageTitle'    => '跑马灯（首页顶部滚动横幅）',
            'activeNav'    => 'notice',
            'noticeEnabled'=> (bool) $cfg['enabled'],
            'noticeText'   => (string) $cfg['text'],
        ]);

        return Response::create($html, 'html', 200, ['Cache-Control' => 'no-store']);
    }

    public function noticeSave(Request $request): Response
    {
        $this->bootCommon();

        $s = notice_config_load();

        $en = $request->post('enabled');
        if ($en !== null) {
            $s['enabled'] = ((int) $en === 1);
        }

        $txt = (string) $request->post('text', '');
        if ($txt !== '') {
            $s['text'] = trim(preg_replace('/\s+/u', ' ', $txt));
        }

        notice_config_save($s);

        admin_op_log((string) Session::get('admin_user', ''), '跑马灯', '跑马灯 ' . ($s['enabled'] ? '开启' : '关闭') . (($txt ?? '') !== '' ? '，更新公告文字' : ''));

        return json([
            'ok'     => true,
            'enabled'=> $s['enabled'],
            'text'   => notice_config_load()['text'],
        ], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }
}
