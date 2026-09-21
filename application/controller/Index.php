<?php
declare(strict_types=1);

namespace app\controller;

use think\Request;
use think\Response;

/**
 * 首页 / 数据 API
 * 业务逻辑复用站点根 common.php
 */
class Index
{
    public function index(Request $request): Response
    {
        require_once dirname(__DIR__, 2) . '/common.php';

        // 首页开关：关闭时展示维护提示页（不记录访问）
        $cfg = home_config_load();
        if (!$cfg['enabled']) {
            $html = '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8">'
                . '<meta name="viewport" content="width=device-width,initial-scale=1">'
                . '<title>维护中</title>'
                . '<style>'
                . '*{box-sizing:border-box;margin:0;padding:0}'
                . 'body{min-height:100vh;display:flex;align-items:center;justify-content:center;'
                . 'background:#f8fafc;font-family:system-ui,sans-serif;color:#1f2937}'
                . '.box{text-align:center;padding:48px 56px;background:#fff;border:1px solid #e5e7eb;'
                . 'border-radius:16px;box-shadow:0 10px 40px rgba(37,99,235,.08)}'
                . '.dot{width:46px;height:46px;margin:0 auto 18px;display:flex;align-items:center;'
                . 'justify-content:center;border-radius:50%;background:#eff6ff;color:#2563eb;'
                . 'font-size:24px;font-weight:700}'
                . 'h1{font-size:18px;margin-bottom:8px}'
                . 'p{font-size:13px;color:#6b7280}'
                . '</style></head><body>'
                . '<div class="box"><div class="dot">!</div>'
                . '<h1>管理员正在做调整，暂时关闭</h1>'
                . '<p>请稍后访问，感谢理解</p>'
                . '</div></body></html>';

            return Response::create($html, 'html', 503, ['Cache-Control' => 'no-store', 'Retry-After' => '3600']);
        }

        $isApi = isset($_GET['api']);
        $isPreview = isset($_GET['preview']);
        [$ip, $ipt] = client_info();

        if (!$isApi) {
            // 预览模式（后台可视化编辑 iframe）不记录访问统计，避免污染数据
            if (!$isPreview) {
                $vuri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
                if ($vuri === '') {
                    $vuri = '/';
                }
                with_stats(static function ($d) use ($ip, $ipt, $vuri) {
                    log_push($d, 'view', $vuri, 0, $ip, $ipt);
                    return $d;
                });
            }

            $page = dirname(__DIR__, 2) . '/public/page.html';
            $html = is_readable($page) ? (string) file_get_contents($page) : '<h1>Not Found</h1>';

            // 首页字体缩放（整页 zoom 等比缩放字体/布局）
            // zoom 仅在非默认缩放值时才注入：避免 `html{zoom:1}` 触发浏览器在空白处显示闪烁文本光标的已知怪癖
            $scale = (float) ($cfg['font_scale'] ?? 1.0);
            if ($scale !== 1.0 && stripos($html, '</head>') !== false) {
                $css   = '<style id="home-font">html{zoom:' . $scale . '}</style>';
                $html  = str_ireplace('</head>', $css . '</head>', $html);
            }

            // 公告栏（跑马灯）：开启且内容非空才输出（整条 HTML），关闭则输出空串、不占页面位置
            $html = str_replace('<!--PHP_INDEX_NOTICE_BANNER-->', notice_banner_html(), $html);

            // 全局站点名称 / 标语 / 标题（settings.json 可后台修改）
            $st = settings_load();
            $siteTitle = $st['site_name'] . ($st['site_slogan'] !== '' ? ' - ' . $st['site_slogan'] : '');
            $html = str_replace('<title>God Supremus 的工具站 - 杀毒工具分享</title>', '<title>' . htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') . '</title>', $html);
            $html = str_replace('God Supremus 的工具站', htmlspecialchars($st['site_name'], ENT_QUOTES, 'UTF-8'), $html);
            $html = str_replace('杀毒 · 安全 · 应急工具分享', htmlspecialchars($st['site_slogan'], ENT_QUOTES, 'UTF-8'), $html);
            $html = str_replace('>God Supremus<', '>' . htmlspecialchars($st['site_name'], ENT_QUOTES, 'UTF-8') . '<', $html);

            // 功能开关：关闭项整块隐藏（不占位置）
            $featHide = '';
            if (empty($st['donate_enabled']))      $featHide .= '.sp-donate{display:none!important}';
            if (empty($st['netpanel_enabled']))    $featHide .= '.sp-speed{display:none!important}';
            if (empty($st['mas_enabled']))         $featHide .= '.sp-mas{display:none!important}';
            if (empty($st['alt_enabled']))         $featHide .= '#ipSwitch{display:none!important}';
            if (empty($st['recent_enabled']))      $featHide .= '.recent{display:none!important}';
            if ($featHide !== '') {
                $html = str_ireplace('</head>', '<style id="feat-hide">' . $featHide . '</style></head>', $html);
            }

            // 可视化编辑：按钮可见性 + 摆放顺序（flex order）+ 自定义文字
            $lay = $st['layout'];
            if (is_array($lay)) {
                $layCss = layout_css($lay);
                if ($layCss !== '') {
                    $html = str_ireplace('</head>', '<style id="layout-edit">' . $layCss . '</style></head>', $html);
                }
                foreach (['support', 'speed', 'mas'] as $lid) {
                    $blk = $lay[$lid] ?? null;
                    if (!is_array($blk) || empty($blk['label'])) {
                        continue;
                    }
                    $def = layout_blocks()[$lid]['label'] ?? '';
                    if ($def !== '' && $blk['label'] !== $def) {
                        $html = str_replace($def, htmlspecialchars($blk['label'], ENT_QUOTES, 'UTF-8'), $html);
                    }
                }

                // 自定义按钮（可视化编辑「添加按钮」）：桌面顶栏 + 移动端菜单
                $om           = layout_order_map($lay);
                $custDesktop  = '';
                $custMobile   = '';
                foreach ($lay as $id => $b) {
                    if (!is_array($b) || empty($b['show']) || !is_custom_block_id((string) $id)) {
                        continue;
                    }
                    $label = trim((string) ($b['label'] ?? ''));
                    if ($label === '') {
                        $label = '提示';
                    }
                    $labelE = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
                    $style  = layout_style_ok($b['style'] ?? '');
                    $order  = $om[(string) $id] ?? 1;
                    $url    = custom_url_safe($b['url'] ?? '');
                    $info   = (($b['kind'] ?? 'btn') === 'info') || $url === '';
                    if ($info) {
                        $class = 'act-btn desktop-only spx spx-' . $style . ' nolink';
                        $classM = 'act-btn spx spx-' . $style . ' nolink';
                        $custDesktop .= '<span class="' . $class . '" style="order:' . $order . '">' . $labelE . '</span>';
                        $custMobile  .= '<span class="' . $classM . '">' . $labelE . '</span>';
                    } else {
                        $urlE = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
                        $tgt  = stripos($url, 'http') === 0 ? ' target="_blank" rel="noopener"' : '';
                        $custDesktop .= '<a class="act-btn desktop-only spx spx-' . $style . '" href="' . $urlE . '" style="order:' . $order . '"' . $tgt . '>' . $labelE . '</a>';
                        $custMobile  .= '<a class="act-btn spx spx-' . $style . '" href="' . $urlE . '"' . $tgt . '>' . $labelE . '</a>';
                    }
                }
                if ($custDesktop !== '') {
                    $html = str_replace('<!--PB_CUSTOM_DESKTOP-->', $custDesktop, $html);
                }
                if ($custMobile !== '') {
                    $html = str_replace('<!--PB_CUSTOM_MOBILE-->', $custMobile, $html);
                }
            }

            // 贴纸：插入 </body> 前
            $stkHtml = sticker_inline($st['stickers'] ?? []);
            if ($stkHtml !== '') {
                $html = str_ireplace('</body>', $stkHtml . '</body>', $html);
            }

            return Response::create($html, 'html', 200, ['Cache-Control' => 'no-store']);
        }

        $secret = secret_quick();
        $stats  = with_stats(static fn($d) => $d);

        $dir = trim((string) ($_GET['dir'] ?? ''), '/');
        $q   = trim((string) ($_GET['q'] ?? ''));

        $dirRel = safe_rel($dir);
        if ($dirRel === null) {
            $dirRel = '';
        }

        // 密码保护：目标目录被锁且未解锁 → 返回 locked，不泄露条目
        if ($q === '') {
            $guard = lock_guard($dirRel);
            if ($guard !== null) {
                $tokens = lock_tokens_from_query((string) ($_GET['ul'] ?? ''));
                if (!lock_rel_unlocked($dirRel, $tokens, $secret)) {
                    return json([
                        'ok'      => false,
                        'locked'  => true,
                        'guard'   => $guard,
                        'msg'     => '该目录已设置密码保护，请输入密码',
                        'dir'     => $dirRel,
                        'summary' => null,
                        'entries' => [],
                    ], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
                }
            }
        }

        if ($q !== '') {
            $entries    = search_entries($q, $stats, $secret);
            $breadcrumb = null;
        } else {
            $entries    = scan_entries($dirRel, $stats, $secret);
            $breadcrumb = breadcrumb_of($dirRel);
        }

        $map = build_map_cached();
        $fileCount = 0;
        foreach ($map as $rel) {
            if (!is_dir(APP_ROOT . '/' . $rel)) {
                $fileCount++;
            }
        }

        $st    = settings_load();
        $alt = $ipt === 'v6'
            ? ['now' => 'IPv6', 'label' => '切换到 IPv4 站点', 'url' => alt_v4_url()]
            : ['now' => 'IPv4', 'label' => '切换到 IPv6 站点', 'url' => alt_v6_url()];
        if (empty($st['alt_enabled'])) {
            $alt = null;
        }

        $recent = array_map(static function ($l) {
            $l['ip'] = mask_ip((string) $l['ip'], (string) $l['ipt']);
            return $l;
        }, array_slice($stats['logs'] ?? [], 0, 15));

        $payload = [
            'ok'      => true,
            'ip'      => ['addr' => mask_ip($ip, $ipt), 'type' => $ipt],
            'alt'     => $alt,
            'summary' => [
                'todayBytes' => (int) ($stats['today']['bytes'] ?? 0),
                'todayCount' => (int) ($stats['today']['count'] ?? 0),
                'totalBytes' => (int) ($stats['total']['bytes'] ?? 0),
                'totalCount' => (int) ($stats['total']['count'] ?? 0),
                'files'      => $fileCount,
                'dirs'       => count(top_dirs()),
            ],
            'dir'        => $dirRel,
            'q'          => $q,
            'guard'      => $q === '' ? lock_guard($dirRel) : null,
            'topdirs'    => top_dirs(),
            'breadcrumb' => $breadcrumb,
            'entries'    => $entries,
            'recent'     => $recent,
        ];

        return json($payload, 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    /**
     * 解锁（POST /api/unlock 或 /?api=1&action=unlock）：body {path, pwd}
     * 密码正确返回 2 小时有效、绑定当前 IP 的一次性解锁令牌。
     */
    public function unlock(Request $request): Response
    {
        $body = [];
        $raw  = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $dec = json_decode($raw, true);
            if (is_array($dec)) {
                $body = $dec;
            }
        }
        $path = trim(str_replace('\\', '/', (string) ($body['path'] ?? ($_POST['path'] ?? ''))), '/');
        $pwd  = (string) ($body['pwd'] ?? ($_POST['pwd'] ?? ''));
        if ($path === '') {
            return json(['ok' => false, 'msg' => '路径为空'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }
        $guard = lock_guard($path);
        if ($guard === null) {
            return json(['ok' => false, 'msg' => '该路径未设置密码保护'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }
        if (!lock_verify_pwd($guard, $pwd)) {
            return json(['ok' => false, 'msg' => '密码错误'], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        }
        $token = lock_token_create($guard, secret_quick());
        return json(['ok' => true, 'token' => $token, 'guard' => $guard], 200, ['Cache-Control' => 'no-store'], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }
}
