<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Shanghai');

const APP_ROOT    = __DIR__ . '/app';
const DATA_DIR    = __DIR__ . '/data';
const STICKER_DIR = __DIR__ . '/data/jpg/sticker';
const STATS_FILE  = DATA_DIR . '/stats.log';
const INDEX_FILE  = DATA_DIR . '/index.json';
const ACTIVE_FILE = DATA_DIR . '/active.json';
const TOKEN_TTL   = 86400;
const SETTINGS_FILE = DATA_DIR . '/settings.json';
const HOME_CFG_DIR  = DATA_DIR . '/Configuration';
const HOME_CFG_FILE = HOME_CFG_DIR . '/home.json';
const ITEM_META_FILE = HOME_CFG_DIR . '/items.json';
const ITEM_LOCK_FILE = HOME_CFG_DIR . '/locks.json';

/**
 * 首页配置（首页开关 + 首页字体缩放）。
 * 首次运行若无配置文件，自动创建 data/Configuration/home.json 并写入默认值（兜底），
 * 避免"系统依赖配置文件运行却不存在"。
 * 返回结构：['enabled'=>bool, 'font_scale'=>float 0.5~2.0]
 */
function home_config_load(): array
{
    $defaults = ['enabled' => true, 'font_scale' => 1.0, 'menu_round' => 0];
    $raw      = @file_get_contents(HOME_CFG_FILE);
    if ($raw === false || $raw === '') {
        home_config_save($defaults);
        return $defaults;
    }
    $d = json_decode($raw, true);
    if (!is_array($d)) {
        home_config_save($defaults);
        return $defaults;
    }
    $d['enabled']    = (bool) ($d['enabled'] ?? $defaults['enabled']);
    $d['font_scale'] = (float) ($d['font_scale'] ?? $defaults['font_scale']);
    if ($d['font_scale'] < 0.5) $d['font_scale'] = 0.5;
    if ($d['font_scale'] > 2.0) $d['font_scale'] = 2.0;
    $d['menu_round'] = (int) ($d['menu_round'] ?? $defaults['menu_round']);
    if ($d['menu_round'] < 0) $d['menu_round'] = 0;
    if ($d['menu_round'] > 2) $d['menu_round'] = 2;
    return array_merge($defaults, $d);
}

function home_config_save(array $d): void
{
    if (!is_dir(HOME_CFG_DIR)) {
        @mkdir(HOME_CFG_DIR, 0755, true);
    }
    @file_put_contents(HOME_CFG_FILE, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

const NOTICE_CFG_FILE = HOME_CFG_DIR . '/notice.json';
const NOTICE_DEFAULT  = '针对银狐病毒导致无法连接 360 官网的情况,请直接从本站下载急救箱与银狐清理脚本';

/**
 * 首页公告配置。返回 ['enabled'=>bool, 'text'=>string]
 */
function notice_config_load(): array
{
    $defaults = ['enabled' => true, 'text' => NOTICE_DEFAULT];
    $raw      = @file_get_contents(NOTICE_CFG_FILE);
    if ($raw === false || $raw === '') {
        notice_config_save($defaults);
        return $defaults;
    }
    $d = json_decode($raw, true);
    if (!is_array($d)) {
        notice_config_save($defaults);
        return $defaults;
    }
    $d['enabled'] = (bool) ($d['enabled'] ?? $defaults['enabled']);
    $text         = trim((string) ($d['text'] ?? ''));
    if ($text === '') {
        $text = $defaults['text'];
    }
    $d['text'] = $text;
    return $d;
}

function notice_config_save(array $d): void
{
    if (!is_dir(HOME_CFG_DIR)) {
        @mkdir(HOME_CFG_DIR, 0755, true);
    }
    $save             = [];
    $save['enabled']  = (bool) ($d['enabled'] ?? true);
    $text             = trim((string) ($d['text'] ?? ''));
    if ($text === '') {
        $text = NOTICE_DEFAULT;
    }
    if (mb_strlen($text, 'UTF-8') > 200) {
        $text = mb_substr($text, 0, 200, 'UTF-8');
    }
    $save['text'] = $text;
    @file_put_contents(NOTICE_CFG_FILE, json_encode($save, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

/**
 * 公告栏 HTML。关闭或无内容时返回空串（不渲染、不占位）。
 */
function notice_banner_html(): string
{
    $cfg = notice_config_load();
    if (empty($cfg['enabled'])) {
        return '';
    }
    $txt = trim((string) $cfg['text']);
    if ($txt === '') {
        return '';
    }
    $safe = htmlspecialchars($txt, ENT_QUOTES, 'UTF-8');
    $ico  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'
        . '<path d="M12 9v4"/><path d="M12 17h.01"/>'
        . '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>';

    return '<div class="notice"><div class="marquee-track" id="marqueeTrack">'
        . '<span class="marquee-content">' . $ico . '<span>' . $safe . '</span></span>'
        . '<span class="marquee-content" aria-hidden="true">' . $ico . '<span>' . $safe . '</span></span>'
        . '</div></div>';
}

/**
 * 单个条目（app/ 下目录/文件）的元数据覆盖：架构、系统、是否打包 zip。
 * 键为相对 app/ 的路径字符串；不覆盖某字段表示按名称自动推断。
 * 前端列表也会读取这些覆盖，从而同步"是否可打包下载/架构徽标"。
 */
function item_meta_load(): array
{
    if (!array_key_exists('__item_meta_cache', $GLOBALS) || $GLOBALS['__item_meta_cache'] === null) {
        $raw = @file_get_contents(ITEM_META_FILE);
        $GLOBALS['__item_meta_cache'] = (is_string($raw) && $raw !== '') ? (json_decode($raw, true) ?: []) : [];
    }
    return $GLOBALS['__item_meta_cache'];
}

function item_meta_get(string $rel): array
{
    $m    = item_meta_load();
    $r    = $m[$rel] ?? [];
    $zip  = array_key_exists('zip', $r) ? (bool) $r['zip'] : null;
    return [
        'arch'   => (string) ($r['arch'] ?? ''),
        'os'     => (string) ($r['os'] ?? ''),
        'zip'    => $zip,
        'hidden' => (bool) ($r['hidden'] ?? false),
    ];
}

function item_meta_save(array $map): void
{
    if (!is_dir(HOME_CFG_DIR)) {
        @mkdir(HOME_CFG_DIR, 0755, true);
    }
    $tmp = ITEM_META_FILE . '.tmp';
    if (@file_put_contents($tmp, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false) {
        @rename($tmp, ITEM_META_FILE);
    }
    $GLOBALS['__item_meta_cache'] = $map;
}

function item_meta_set(string $rel, string $arch, string $os, ?bool $zip, ?bool $hidden = null): void
{
    $m = item_meta_load();
    $arch = trim($arch);
    $os   = trim($os);
    $cur  = $m[$rel] ?? [];
    $old  = (bool) ($cur['hidden'] ?? false);
    $h    = $hidden ?? $old;
    if ($arch === '' && $os === '' && $zip === null && $h === false) {
        unset($m[$rel]);
    } else {
        $m[$rel] = ['arch' => $arch, 'os' => $os, 'zip' => $zip, 'hidden' => $h];
    }
    item_meta_save($m);
}

function item_meta_move(string $from, string $to): void
{
    $m = item_meta_load();
    if ($from !== $to && isset($m[$from])) {
        $m[$to] = $m[$from];
        unset($m[$from]);
        item_meta_save($m);
    }
}

function item_meta_drop(string $rel): void
{
    $m = item_meta_load();
    if (isset($m[$rel])) {
        unset($m[$rel]);
        item_meta_save($m);
    }
}

// ===================== 密码保护（目录/文件密码锁） =====================
// 存储：locks.json，键为相对 app/ 的路径，值为 ['hash' => bcrypt, 'set' => 时间戳]。
// 密码只存单向 hash，且仅在服务端校验；前端仅用于展示"已加锁"状态。

function item_lock_load(): array
{
    if (!array_key_exists('__item_lock_cache', $GLOBALS) || $GLOBALS['__item_lock_cache'] === null) {
        $raw = @file_get_contents(ITEM_LOCK_FILE);
        $GLOBALS['__item_lock_cache'] = (is_string($raw) && $raw !== '') ? (json_decode($raw, true) ?: []) : [];
    }
    return $GLOBALS['__item_lock_cache'];
}

function item_lock_save(array $map): void
{
    if (!is_dir(dirname(ITEM_LOCK_FILE))) {
        @mkdir(dirname(ITEM_LOCK_FILE), 0755, true);
    }
    $tmp = ITEM_LOCK_FILE . '.tmp';
    if (@file_put_contents($tmp, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false) {
        @rename($tmp, ITEM_LOCK_FILE);
    }
    $GLOBALS['__item_lock_cache'] = $map;
}

/** 取某路径自身的锁 hash（无锁返回空串）。 */
function item_lock_get(string $rel): string
{
    $m = item_lock_load();
    return (string) ($m[$rel]['hash'] ?? '');
}

function item_lock_set(string $rel, string $pwd): void
{
    $m = item_lock_load();
    $m[$rel] = ['hash' => password_hash($pwd, PASSWORD_DEFAULT), 'set' => time()];
    item_lock_save($m);
}

function item_lock_clear(string $rel): void
{
    $m = item_lock_load();
    if (isset($m[$rel])) {
        unset($m[$rel]);
        item_lock_save($m);
    }
}

/** 重命名后迁移锁：目录重命名会把整个前缀子树迁移。 */
function item_lock_move(string $from, string $to): void
{
    $m  = item_lock_load();
    $fx = rtrim($from, '/');
    $tx = rtrim($to, '/');
    $n  = [];
    foreach ($m as $k => $v) {
        if ($k === $fx) {
            $n[$tx] = $v;
        } elseif ($fx !== '' && str_starts_with($k, $fx . '/')) {
            $n[$tx . '/' . substr($k, strlen($fx) + 1)] = $v;
        } else {
            $n[$k] = $v;
        }
    }
    if ($n !== $m) {
        item_lock_save($n);
    }
}

/** 删除条目时清理自身及子树锁。 */
function item_lock_drop(string $rel): void
{
    $m  = item_lock_load();
    $rx = rtrim($rel, '/');
    $n  = [];
    foreach ($m as $k => $v) {
        if ($k === $rx || ($rx !== '' && str_starts_with($k, $rx . '/'))) {
            continue;
        }
        $n[$k] = $v;
    }
    if ($n !== $m) {
        item_lock_save($n);
    }
}

/**
 * 路径守卫：返回自身或最近一个加了锁的祖先路径（相对 app/），无锁返回 null。
 * 顶层 app 根永远不算守卫（根目录不能被整体锁住，防止误把所有内容锁死）。
 */
function lock_guard(string $rel): ?string
{
    $rel = trim(str_replace('\\', '/', $rel), '/');
    $parts = $rel === '' ? [] : explode('/', $rel);
    while (true) {
        $k = implode('/', $parts);
        if ($k !== '' && item_lock_get($k) !== '') {
            return $k;
        }
        if ($parts === []) {
            return null;
        }
        array_pop($parts);
    }
}

/** 校验密码是否匹配某守卫路径的锁（服务端唯一验证点之一）。 */
function lock_verify_pwd(string $guard, string $pwd): bool
{
    $hash = item_lock_get($guard);
    if ($hash === '' || $pwd === '') {
        return false;
    }
    return password_verify($pwd, $hash);
}

/**
 * 签发一次性解锁令牌（无状态，HMAC 签名 + 有效期 + 绑定 IP）。
 * 返回格式：urlsafe(guard).urlsafe(ip).exp.hmac
 */
function lock_token_create(string $guard, string $secret): string
{
    $ip = client_info()[0];
    $exp = time() + 7200; // 2 小时
    $b64 = static function (string $v): string {
        return rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
    };
    $sig = hash_hmac('sha256', $guard . '|' . $ip . '|' . $exp, $secret);
    return $b64($guard) . '.' . $b64($ip) . '.' . $exp . '.' . $sig;
}

/** 校验解锁令牌是否有效且覆盖目标守卫路径。 */
function lock_token_ok(string $guard, string $token, string $secret): bool
{
    if ($token === '') {
        return false;
    }
    $parts = explode('.', $token);
    if (count($parts) !== 4) {
        return false;
    }
    [$g64, $i64, $exp, $sig] = $parts;
    $ip  = client_info()[0];
    $b64 = static function (string $v): string {
        return rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
    };
    $gv = base64_decode(strtr($g64, '-_', '+/'));
    $iv = base64_decode(strtr($i64, '-_', '+/'));
    if (!is_string($gv) || !is_string($iv) || !hash_equals($guard, $gv)) {
        return false;
    }
    $toUnix = (int) $exp;
    if ($toUnix <= 0 || $toUnix < time()) {
        return false;
    }
    if ($iv === '' || !hash_equals($b64($ip), $i64)) {
        return false;
    }
    return hash_equals(hash_hmac('sha256', $guard . '|' . $ip . '|' . $toUnix, $secret), $sig);
}

/** 合并解析 ?ul= 中的令牌列表（逗号/空格分隔）。 */
function lock_tokens_from_query(string $raw): array
{
    $out = [];
    foreach (preg_split('/[,\s]+/', trim((string) $raw)) ?: [] as $t) {
        $t = trim($t);
        if ($t !== '' && !in_array($t, $out, true)) {
            $out[] = $t;
        }
    }
    return $out;
}

/**
 * 判断一组 tokens 是否已解锁给定 rel 的守卫。
 * 兼容性：任一 token 覆盖守卫即可。
 */
function lock_rel_unlocked(string $rel, array $tokens, string $secret): bool
{
    $guard = lock_guard($rel);
    if ($guard === null) {
        return true;
    }
    foreach ($tokens as $t) {
        if (lock_token_ok($guard, $t, $secret)) {
            return true;
        }
    }
    return false;
}

/**
 * 递归删除文件/目录（含空目录清理）。安全前提由调用方保证：$path 已校验位于 APP_ROOT 内。
 */
function rm_recursive(string $path): bool
{
    if (is_link($path)) {
        return @unlink($path);
    }
    if (is_dir($path)) {
        $items = @scandir($path);
        if ($items === false) {
            return false;
        }
        foreach ($items as $it) {
            if ($it === '.' || $it === '..') {
                continue;
            }
            if (!rm_recursive($path . '/' . $it)) {
                return false;
            }
        }
        return @rmdir($path);
    }
    if (is_file($path)) {
        return @unlink($path);
    }
    return false;
}

function settings_load(): array
{
    $defaults = [
        'v4_url'          => 'https://ipv4.20070622.xyz',
        'v6_url'          => 'https://ipv6.20070622.xyz',
        'site_name'       => 'God Supremus 的工具站',
        'site_slogan'     => '杀毒 · 安全 · 应急工具分享',
        'alt_enabled'     => true,
        'donate_enabled'  => true,
        'donate_image'    => '',
        'netpanel_enabled'=> true,
        'mas_enabled'     => true,
        'recent_enabled'  => true,
        'admin_path'      => 'admin',
    ];
    $raw = @file_get_contents(SETTINGS_FILE);
    $d = [];
    if ($raw !== false && $raw !== '') {
        $j = json_decode($raw, true);
        if (is_array($j)) {
            $d = $j;
        }
    }
    $d['v4_url']          = trim((string) ($d['v4_url'] ?? $defaults['v4_url']));
    $d['v6_url']          = trim((string) ($d['v6_url'] ?? $defaults['v6_url']));
    if ($d['v4_url'] === '') $d['v4_url'] = $defaults['v4_url'];
    if ($d['v6_url'] === '') $d['v6_url'] = $defaults['v6_url'];
    $d['site_name']       = trim((string) ($d['site_name'] ?? $defaults['site_name']));
    $d['site_slogan']     = trim((string) ($d['site_slogan'] ?? $defaults['site_slogan']));
    if ($d['site_name'] === '') $d['site_name'] = $defaults['site_name'];
    if ($d['site_slogan'] === '') $d['site_slogan'] = $defaults['site_slogan'];
    $d['alt_enabled']     = (bool) ($d['alt_enabled'] ?? true);
    $d['donate_enabled']  = (bool) ($d['donate_enabled'] ?? true);
    $d['donate_image']    = trim((string) ($d['donate_image'] ?? ''));
    $d['netpanel_enabled']= (bool) ($d['netpanel_enabled'] ?? true);
    $d['mas_enabled']     = (bool) ($d['mas_enabled'] ?? true);
    $d['recent_enabled']  = (bool) ($d['recent_enabled'] ?? true);
    $d['layout']          = layout_normalize($d['layout'] ?? null);
    $d['stickers']        = stickers_normalize($d['stickers'] ?? null);
    return array_merge($defaults, $d);
}

function settings_save(array $s): void
{
    @file_put_contents(SETTINGS_FILE, json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * Windows 激活工具静态页开关：关闭即把 public/activation.html 改名为 activation.html.off，
 * nginx 直接静态 404；开启则改回。
 */
function mas_asset_sync(bool $enabled): void
{
    $on  = __DIR__ . '/public/activation.html';
    $off = __DIR__ . '/public/activation.html.off';
    if ($enabled) {
        if (!file_exists($on) && file_exists($off)) {
            @rename($off, $on);
        }
    } else {
        if (file_exists($on) && !file_exists($off)) {
            @rename($on, $off);
        }
    }
}

/**
 * 测速面板目录同步：关闭即把 public/networkpanel 重命名为 networkpanel.off（nginx 无法再静态提供，直接 404），
 * 开启则改回。便于“关闭后即使有人直接访问路径也不可访问”。
 */
function netpanel_sync(bool $enabled): void
{
    $on  = __DIR__ . '/public/networkpanel';
    $off = __DIR__ . '/public/networkpanel.off';
    if ($enabled) {
        if (!is_dir($on) && is_dir($off)) {
            @rename($off, $on);
        }
    } else {
        if (is_dir($on) && !is_dir($off)) {
            @rename($on, $off);
        }
    }
}

/**
 * 可视化编辑：首页顶部按钮「积木」定义。
 * sel=排序用的选择器；hides=隐藏时命中的选择器（含移动端菜单副本）；
 * label=可自定义按钮文字（null 表示不可改）；name=后台显示名。
 */
function layout_blocks(): array
{
    return [
        'support' => ['name' => '支持作者',        'sel' => '.support-wrap',  'hides' => ['.support-wrap', '.sp-donate'], 'label' => '支持作者（赞助）'],
        'speed'   => ['name' => '测速面板',        'sel' => '.sp-speed',      'hides' => ['.sp-speed'],                   'label' => '测速面板'],
        'mas'     => ['name' => 'Windows 激活工具','sel' => '.sp-mas',        'hides' => ['.sp-mas'],                     'label' => 'Windows激活工具'],
        'stats'   => ['name' => '统计信息',        'sel' => '.tb-stats',      'hides' => ['.tb-stats'],                   'label' => null],
        'ip'      => ['name' => 'IPv4/IPv6 切换',  'sel' => '#ipSwitch',      'hides' => ['#ipSwitch'],                   'label' => null],
        'theme'   => ['name' => '深浅模式',        'sel' => '.theme-toggle',  'hides' => ['.theme-toggle'],               'label' => null],
    ];
}

/**
 * 自定义按钮的预设样式（「添加按钮」可选，样式越多越好）
 * key 会用于 CSS 类 spx-<key> 与布局配置中的 style 字段。
 */
function layout_styles(): array
{
    return [
        ['key' => 'ghost',     'name' => '幽灵 · 透明描边'],
        ['key' => 'line',      'name' => '描边 · 蓝色细边'],
        ['key' => 'solid',     'name' => '实心 · 主蓝'],
        ['key' => 'grad',      'name' => '渐变 · 紫色'],
        ['key' => 'green',     'name' => '实心 · 墨绿'],
        ['key' => 'blue',      'name' => '实心 · 宝蓝'],
        ['key' => 'orange',    'name' => '实心 · 橙'],
        ['key' => 'red',       'name' => '实心 · 红'],
        ['key' => 'link',      'name' => '文字链接'],
        ['key' => 'soft-blue', 'name' => '信息 · 浅蓝'],
        ['key' => 'soft-green','name' => '信息 · 浅绿'],
        ['key' => 'soft-amber','name' => '信息 · 浅黄'],
        ['key' => 'soft-red',  'name' => '信息 · 浅红'],
    ];
}

function layout_style_ok(string $style): string
{
    foreach (layout_styles() as $s) {
        if ($s['key'] === $style) {
            return $style;
        }
    }
    return 'ghost';
}

function is_custom_block_id(string $id): bool
{
    return strncmp($id, 'c_', 2) === 0 && strlen($id) > 2;
}

function layout_is_builtin(string $id): bool
{
    return isset(layout_blocks()[$id]);
}

/**
 * 自定义按钮链接白名单：只允许 http(s)://、/、#、?、./ 等，拒绝 javascript:/data: 等注入
 */
function custom_url_safe($url): string
{
    $url = trim((string) ($url ?? ''));
    if ($url === '') {
        return '';
    }
    if (preg_match('~^(https?://|mailto:)~i', $url)) {
        return mb_substr($url, 0, 500, 'UTF-8');
    }
    if ($url[0] === '/' || $url[0] === '#' || $url[0] === '?' || strncmp($url, './', 2) === 0 || strncmp($url, '../', 3) === 0) {
        return mb_substr($url, 0, 500, 'UTF-8');
    }
    return '';
}

function layout_defaults(): array
{
    $out = [];
    foreach (layout_blocks() as $id => $b) {
        $item = ['show' => true];
        if ($b['label'] !== null) {
            $item['label'] = $b['label'];
        }
        $out[$id] = $item;
    }
    return $out;
}

/**
 * 归一化：保留内置块 + 自定义按钮（c_ 前缀），保留用户顺序、补齐缺失内置块。
 * 自定义按钮额外归一化 url / style 字段。
 */
function layout_normalize($raw): array
{
    $defs = layout_defaults();
    $out  = [];
    if (is_array($raw)) {
        foreach ($raw as $id => $v) {
            $id = (string) $id;
            if (isset($out[$id]) || (!isset($defs[$id]) && !is_custom_block_id($id))) {
                continue;
            }
            $show = is_array($v) ? !empty($v['show']) : (bool) $v;
            $item = ['show' => $show];
            if (isset($defs[$id]['label'])) {
                $lab = is_array($v) ? trim((string) ($v['label'] ?? '')) : '';
                if ($lab === '') {
                    $lab = $defs[$id]['label'];
                }
                $item['label'] = mb_substr($lab, 0, 24, 'UTF-8');
            } elseif (is_custom_block_id($id)) {
                $lab = is_array($v) ? trim((string) ($v['label'] ?? '')) : '';
                if ($lab === '') {
                    $lab = '新按钮';
                }
                $item['label'] = mb_substr($lab, 0, 24, 'UTF-8');
                $item['url']   = custom_url_safe(is_array($v) ? ($v['url'] ?? '') : '/');
                $item['style'] = layout_style_ok(is_array($v) ? (string) ($v['style'] ?? '') : '');
                $kind          = is_array($v) ? (string) ($v['kind'] ?? 'btn') : 'btn';
                $item['kind']  = $kind === 'info' ? 'info' : 'btn';
            }
            $out[$id] = $item;
        }
    }
    foreach ($defs as $id => $d) {
        if (!isset($out[$id])) {
            $out[$id] = $d;
        }
    }
    return $out;
}

/**
 * 可见块 → 全局顺序号（内置 + 自定义统一编号，供 flex order 与自定义按钮内联 order 使用）
 */
function layout_order_map(array $layout): array
{
    $map = [];
    $n   = 1;
    foreach ($layout as $id => $b) {
        if (!empty($b['show'])) {
            $map[(string) $id] = $n++;
        }
    }
    return $map;
}

/**
 * 生成注入首页 <head> 的布局 CSS：内置可见块按顺序设置 flex order，隐藏块 display:none
 * 自定义按钮由 Index 直接输出内联 order，无需 CSS。
 */
function layout_css(array $layout): string
{
    $map = layout_blocks();
    $css = '';
    $order = 1;
    foreach ($layout as $id => $b) {
        if (empty($b['show'])) {
            if (isset($map[$id])) {
                foreach ($map[$id]['hides'] as $h) {
                    $css .= $h . '{display:none!important}';
                }
            }
            continue;
        }
        if (isset($map[$id])) {
            $css .= $map[$id]['sel'] . '{order:' . $order . '}';
        }
        $order++;
    }
    // 移动端汉堡菜单永远排在最后
    $css .= '.hamburger-wrap{order:99}';
    return $css;
}

function alt_v4_url(): string { return settings_load()['v4_url']; }
function alt_v6_url(): string { return settings_load()['v6_url']; }

/* ==================== 贴纸 ==================== */

function sticker_ext_ok(string $name): bool
{
    return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'webp'], true);
}

function sticker_safe_name(string $name): string
{
    $name = basename((string) $name);
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?? '';
    if ($name === '' || $name[0] === '.') {
        return '';
    }
    return sticker_ext_ok($name) ? mb_substr($name, 0, 80, 'UTF-8') : '';
}

/**
 * 贴纸自动去白底：把"白底 JPG/WEBP/无透明 PNG"改造成透明 PNG，
 * 切深色模式后四周不再出现白色边缘。纯白判定：RGB 全部 ≥225 且饱和度低；
 * 从四边做种子（连通区域）填充，只清掉与边缘相连的白色背景，内容不动。
 * 成功转换返回最终 basename（可能从 .jpg 变 .png），无需转换返回原 basename。
 */
function sticker_autoclear(string $path): string
{
    $name = basename((string) $path);
    if (!is_file($path)) {
        return $name;
    }
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'webp', 'png'], true)) {
        return $name;
    }
    if (!function_exists('imagecreatefrompng') || !function_exists('imagepng')) {
        return $name;
    }

    $im = null;
    try {
        if ($ext === 'png') {
            $im = function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false;
        } elseif ($ext === 'webp') {
            $im = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
        } else {
            $im = function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false;
        }
    } catch (\Throwable $e) {
        $im = false;
    }
    if (!$im) {
        return $name;
    }

    $w = (int) imagesx($im);
    $h = (int) imagesy($im);
    if ($w < 2 || $h < 2 || $w > 3000 || $h > 3000) {
        @imagedestroy($im);
        return $name;
    }

    // 统一转成真彩色，方便逐像素读 RGB
    if (!imageistruecolor($im)) {
        $tmp = imagecreatetruecolor($w, $h);
        imagealphablending($tmp, false);
        imagesavealpha($tmp, true);
        imagecopy($tmp, $im, 0, 0, 0, 0, $w, $h);
        @imagedestroy($im);
        $im = $tmp;
    }

    // PNG 已带真实透明：直接跳过（避免把用户故意做的半透明图清掉）
    if ($ext === 'png') {
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if (((imagecolorat($im, $x, $y) >> 24) & 0x7F) > 0) {
                    @imagedestroy($im);
                    return $name;
                }
            }
        }
    }

    $isWhite = static function ($c): bool {
        $r = ($c >> 16) & 0xFF;
        $g = ($c >> 8) & 0xFF;
        $b = $c & 0xFF;
        return $r >= 225 && $g >= 225 && $b >= 225 && (max($r, $g, $b) - min($r, $g, $b)) <= 24;
    };

    // BFS 从四边出发，只淹过"与边缘相连的白色"
    $visited = str_repeat("\0", $w * $h);
    $stack = [];
    $push = static function (int $x, int $y) use (&$stack, &$visited, $w, $h): void {
        if ($x < 0 || $y < 0 || $x >= $w || $y >= $h) {
            return;
        }
        $i = $y * $w + $x;
        if ($visited[$i] !== "\0") {
            return;
        }
        $visited[$i] = "\1";
        $stack[] = $i;
    };
    for ($x = 0; $x < $w; $x++) {
        $push($x, 0);
        $push($x, $h - 1);
    }
    for ($y = 0; $y < $h; $y++) {
        $push(0, $y);
        $push($w - 1, $y);
    }
    $clearCount = 0;
    while ($stack) {
        $i = array_pop($stack);
        $py = intdiv($i, $w);
        $px = $i % $w;
        if ($isWhite(imagecolorat($im, $px, $py))) {
            $visited[$i] = "\2";
            $clearCount++;
            if ($px > 0) $push($px - 1, $py);
            if ($px < $w - 1) $push($px + 1, $py);
            if ($py > 0) $push($px, $py - 1);
            if ($py < $h - 1) $push($px, $py + 1);
        }
    }

    // 没清出东西（或背景占比过低）：保持原样
    $minClear = max(64, (int) ceil($w * $h * 0.01));
    if ($clearCount < $minClear) {
        @imagedestroy($im);
        return $name;
    }

    // 清出的白色置透明；紧邻透明的像素做 1/3 半透明羽化，减轻硬边
    $out = imagecreatetruecolor($w, $h);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    for ($py = 0; $py < $h; $py++) {
        for ($px = 0; $px < $w; $px++) {
            $i = $py * $w + $px;
            if ($visited[$i] === "\2") {
                $alpha = 127;
            } else {
                $near = ($px > 0 && $visited[$i - 1] === "\2")
                    || ($px < $w - 1 && $visited[$i + 1] === "\2")
                    || ($py > 0 && $visited[$i - $w] === "\2")
                    || ($py < $h - 1 && $visited[$i + $w] === "\2");
                $alpha = $near ? 86 : 0;
            }
            $c = imagecolorat($im, $px, $py);
            imagesetpixel($out, $px, $py, imagecolorallocatealpha(
                $out,
                ($c >> 16) & 0xFF,
                ($c >> 8) & 0xFF,
                $c & 0xFF,
                $alpha
            ));
        }
    }

    $final = pathinfo($name, PATHINFO_FILENAME) . '.png';
    $finalPath = dirname((string) $path) . '/' . $final;
    $ok = @imagepng($out, $finalPath);
    @imagedestroy($out);
    @imagedestroy($im);
    if (!$ok) {
        if (is_file($finalPath)) {
            @unlink($finalPath);
        }
        return $name;
    }
    if ($finalPath !== $path) {
        @unlink($path);
    }
    return $final;
}

/**
 * 归一化贴纸配置：{id,file,x,y,fixed(跟随上下),mobile(手机端显示),opacity,size,z}
 */
function stickers_normalize($raw): array
{
    $out = [];
    if (!is_array($raw)) {
        return $out;
    }
    $n = 0;
    foreach ($raw as $v) {
        if (!is_array($v) || $n >= 50) {
            continue;
        }
        $file = sticker_safe_name((string) ($v['file'] ?? ''));
        if ($file === '') {
            continue;
        }
        $n++;
        $id = trim((string) ($v['id'] ?? ''));
        if ($id === '') {
            $id = 's_' . $n;
        }
        $out[$id] = [
            'id'      => $id,
            'file'    => $file,
            'x'       => min(135, max(-35, (float) ($v['x'] ?? 50))),
            'y'       => min(135, max(-35, (float) ($v['y'] ?? 50))),
            'fixed'   => !empty($v['fixed']),
            'mobile'  => !empty($v['mobile']),
            'opacity' => min(100, max(10, (int) ($v['opacity'] ?? 100))),
            'size'    => min(900, max(20, (int) ($v['size'] ?? 90))),
            'z'       => min(999, max(1, (int) ($v['z'] ?? $n))),
        ];
    }
    return $out;
}

/**
 * 生成首页贴纸 HTML（插入 </body> 前）；文件不存在则跳过
 */
function sticker_inline(array $stickers): string
{
    if (!$stickers) {
        return '';
    }
    if (!is_dir(STICKER_DIR)) {
        @mkdir(STICKER_DIR, 0755, true);
    }
    $css = '<style id="stk-css">.stk{position:absolute;-webkit-user-select:none;user-select:none;pointer-events:none}'
        . '.stk.fixed{position:fixed}'
        . '@media(max-width:820px){.stk.nomobile{display:none!important}}</style>';
    $html = '';
    foreach ($stickers as $s) {
        $full = STICKER_DIR . '/' . $s['file'];
        if (!is_file($full)) {
            continue;
        }
        $cls = 'stk' . ($s['fixed'] ? ' fixed' : '') . ($s['mobile'] ? '' : ' nomobile');
        $html .= '<img class="' . $cls . '" src="/stk/' . rawurlencode($s['file']) . '" alt="" loading="lazy" '
            . 'style="left:' . round($s['x'], 2) . '%;top:' . round($s['y'], 2) . '%;width:' . (int) $s['size'] . 'px;'
            . 'opacity:' . round($s['opacity'] / 100, 3) . ';z-index:' . (int) $s['z'] . '">';
    }
    return $html === '' ? $css : $css . $html;
}
const SITE_NAME   = 'God Supremus 的工具站';
const ADMIN_USER  = 'god';
const ADMIN_HASH_FILE = DATA_DIR . '/admin_hash.json';
const LOG_DIR     = DATA_DIR . '/logs';
const LOGIN_GUARD_FILE = DATA_DIR . '/login_guard.json';

function admin_hash_init(): void
{
    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0755, true);
    }
    if (!is_file(ADMIN_HASH_FILE)) {
        $h = password_hash('admin', PASSWORD_BCRYPT);
        @file_put_contents(ADMIN_HASH_FILE, json_encode(['user' => ADMIN_USER, 'hash' => $h], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

function admin_verify(string $user, string $pass): bool
{
    admin_hash_init();
    $raw = @file_get_contents(ADMIN_HASH_FILE);
    if ($raw === false || $raw === '') return false;
    $d = json_decode($raw, true);
    if (!is_array($d)) return false;
    return $user === ($d['user'] ?? '') && password_verify($pass, (string)($d['hash'] ?? ''));
}

function admin_set_password(string $user, string $newPass): void
{
    admin_hash_write($user, password_hash($newPass, PASSWORD_BCRYPT));
}

function admin_hash_write(string $user, string $hash): void
{
    admin_hash_init();
    @file_put_contents(ADMIN_HASH_FILE, json_encode(['user' => $user, 'hash' => $hash], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * 登录限流：同一 IP 连续失败 5 次锁定 10 分钟。
 */
function login_guard_state(): array
{
    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0755, true);
    }
    $raw = @file_get_contents(LOGIN_GUARD_FILE);
    $d = [];
    if ($raw !== false && $raw !== '') {
        $t = json_decode($raw, true);
        if (is_array($t)) {
            $d = $t;
        }
    }
    $now = time();
    $g = $d[$_SERVER['REMOTE_ADDR'] ?? ''] ?? null;
    if (is_array($g)) {
        $until = (int) ($g['locked_until'] ?? 0);
        if ($until > $now) {
            return ['locked' => true, 'retry_after' => $until - $now];
        }
        if (($g['first'] ?? 0) < $now - 3600) {
            unset($d[$_SERVER['REMOTE_ADDR'] ?? '']);
            login_guard_write($d);
        }
    }
    return ['locked' => false, 'retry_after' => 0];
}

function login_guard_write(array $d): void
{
    @file_put_contents(LOGIN_GUARD_FILE, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function login_guard_fail(): array
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0755, true);
    }
    $raw = @file_get_contents(LOGIN_GUARD_FILE);
    $d = [];
    if ($raw !== false && $raw !== '') {
        $t = json_decode($raw, true);
        if (is_array($t)) {
            $d = $t;
        }
    }
    $now = time();
    $g = $d[$ip] ?? ['fail' => 0, 'first' => $now];
    if (($g['first'] ?? 0) < $now - 3600) {
        $g = ['fail' => 0, 'first' => $now];
    }
    $g['fail'] = (int) ($g['fail'] ?? 0) + 1;
    $d[$ip] = $g;
    login_guard_write($d);

    if ($g['fail'] >= 5) {
        $d[$ip]['locked_until'] = $now + 600;
        $d[$ip]['fail'] = 0;
        login_guard_write($d);
        return ['locked' => true, 'retry_after' => 600];
    }
    return ['locked' => false, 'fails' => $g['fail']];
}

function login_guard_clear(): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '') {
        return;
    }
    $raw = @file_get_contents(LOGIN_GUARD_FILE);
    $d = [];
    if ($raw !== false && $raw !== '') {
        $t = json_decode($raw, true);
        if (is_array($t)) {
            $d = $t;
        }
    }
    if (isset($d[$ip])) {
        unset($d[$ip]);
        login_guard_write($d);
    }
}

/* ==================== 系统日志（登录 / 操作） ==================== */

function log_file(string $kind, string $date = ''): string
{
    if ($date === '') {
        $date = date('Y-m-d');
    }
    $stamp = date('Ymd', strtotime($date));
    return LOG_DIR . '/' . $kind . '-' . $stamp . '.json';
}

function log_read(string $kind, string $date): array
{
    $f = log_file($kind, $date);
    if (!is_file($f)) {
        return ['date' => $date, 'entries' => []];
    }
    $raw = @file_get_contents($f);
    if ($raw === false || $raw === '') {
        return ['date' => $date, 'entries' => []];
    }
    $d = json_decode($raw, true);
    if (!is_array($d)) {
        return ['date' => $date, 'entries' => []];
    }
    $d['date'] = (string) ($d['date'] ?? $date);
    $d['entries'] = isset($d['entries']) && is_array($d['entries']) ? $d['entries'] : [];
    return $d;
}

function log_add(string $kind, array $entry, int $cap = 500): void
{
    if (!is_dir(LOG_DIR)) {
        @mkdir(LOG_DIR, 0755, true);
    }
    $date = date('Y-m-d');
    $f = log_file($kind, $date);
    $d = [];
    $raw = @file_get_contents($f);
    if ($raw !== false && $raw !== '') {
        $t = json_decode($raw, true);
        if (is_array($t)) {
            $d = $t;
        }
    }
    $entries = isset($d['entries']) && is_array($d['entries']) ? $d['entries'] : [];
    array_unshift($entries, $entry);
    $entries = array_slice($entries, 0, $cap);
    @file_put_contents($f, json_encode(['date' => $date, 'entries' => $entries], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function admin_login_log(string $user, bool $ok, string $detail): void
{
    [$ip, $ipt] = client_info();
    log_add('login', [
        't'      => time(),
        'th'     => date('m-d H:i'),
        'ip'     => $ip,
        'ipt'    => $ipt,
        'user'   => mb_substr($user, 0, 40),
        'ok'     => $ok,
        'detail' => mb_substr($detail, 0, 120),
        'ua'     => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120),
    ]);
}

function admin_op_log(string $user, string $act, string $detail): void
{
    [$ip, $ipt] = client_info();
    log_add('operation', [
        't'      => time(),
        'th'     => date('m-d H:i'),
        'ip'     => $ip,
        'ipt'    => $ipt,
        'user'   => mb_substr($user, 0, 40),
        'act'    => mb_substr($act, 0, 40),
        'detail' => mb_substr($detail, 0, 160),
        'ua'     => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120),
    ], 300);
}

/* ==================== 后台安全路径 / 后台访问日志 ==================== */

/**
 * 当前后台访问前缀（默认 admin，可在系统设置中修改）。
 * 直接读取 settings.json，避免依赖 settings_load 的完整合并。
 */
function admin_base(): string
{
    $raw = @file_get_contents(SETTINGS_FILE);
    if ($raw !== false && $raw !== '') {
        $d = json_decode($raw, true);
        if (is_array($d) && is_string($d['admin_path'] ?? null)) {
            $p = trim($d['admin_path']);
            if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{1,31}$/', $p)) {
                return $p;
            }
        }
    }
    return 'admin';
}

/** 后台路径合法性校验（格式 + 不与既有前端路由/静态目录冲突） */
function admin_path_valid(string $path): bool
{
    $path = trim($path);
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{1,31}$/', $path)) {
        return false;
    }
    $reserved = ['d', 'stk', 'img', 'download.php', 'mas_ps1.php', 'mas.ps1', 'index.php', 'api', 'assets', 'networkpanel', 'data', 'app', 'vendor', 'runtime'];
    if (in_array(strtolower($path), $reserved, true)) {
        return false;
    }
    return true;
}

/** 生成 6 位随机英文字母（小写） */
function admin_path_gen(): string
{
    $alpha = 'abcdefghijklmnopqrstuvwxyz';
    $out = '';
    for ($i = 0; $i < 6; $i++) {
        $out .= $alpha[random_int(0, 25)];
    }
    return $out;
}

/** 后台访问日志：记录所有进入后台的请求（IP / 路径 / 时间 / User-Agent / 账号） */
function admin_access_log(): void
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    if ($uri === '') {
        $uri = '/';
    }
    if (str_contains($uri, '?')) {
        $uri = (string) strstr($uri, '?', true);
    }
    $base = admin_base();
    $rel = str_starts_with($uri, '/' . $base) ? substr($uri, strlen($base) + 1) : '';
    if (str_starts_with($rel, 'api') || str_contains($rel, '/api')) {
        $note = '接口';
    } elseif ($rel === '' || $rel === 'index') {
        $note = '仪表盘';
    } elseif (str_starts_with($rel, 'login')) {
        $note = '登录';
    } elseif (str_starts_with($rel, 'captcha')) {
        $note = '验证码';
    } elseif (str_starts_with($rel, 'logout')) {
        $note = '退出';
    } else {
        $note = '页面';
    }
    if ($base !== 'admin' && str_starts_with($uri, '/admin')) {
        $note = '旧路径访问';
    }
    [$ip, $ipt] = client_info();
    $user = '';
    if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
        $user = (string) ($_SESSION['admin_user'] ?? '');
    }
    log_add('access', [
        't'      => time(),
        'th'     => date('m-d H:i'),
        'ip'     => $ip,
        'ipt'    => $ipt,
        'method' => mb_substr((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), 0, 10),
        'path'   => mb_substr($uri, 0, 200),
        'note'   => mb_substr($note, 0, 40),
        'user'   => mb_substr($user, 0, 40),
        'ua'     => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
    ], 800);
}

/** 返回系统日志可选日期列表（倒序），跨 login/operation 与 daily 汇总 */
function system_log_dates(): array
{
    $dates = [];
    if (is_dir(LOG_DIR)) {
        foreach (glob(LOG_DIR . '/{login,operation,access}-*.json', GLOB_BRACE) ?: [] as $f) {
            if (preg_match('/(?:login|operation|access)-(\d{8})\.json$/', $f, $m)) {
                $d = substr($m[1], 0, 4) . '-' . substr($m[1], 4, 2) . '-' . substr($m[1], 6, 2);
                if (is_string($d) && $d !== '') {
                    $dates[$d] = true;
                }
            }
        }
    }
    foreach (list_daily_files() as $stamp) {
        $d = substr($stamp, 0, 4) . '-' . substr($stamp, 4, 2) . '-' . substr($stamp, 6, 2);
        $dates[$d] = true;
    }
    krsort($dates);
    return array_keys($dates);
}


function stats_defaults(): array
{
    return [
        'secret' => bin2hex(random_bytes(24)),
        'today'  => ['date' => date('Y-m-d'), 'bytes' => 0, 'count' => 0],
        'total'  => ['bytes' => 0, 'count' => 0],
        'items'  => [],
        'logs'   => [],
    ];
}

function index_defaults(): array
{
    return [
        'secret' => bin2hex(random_bytes(24)),
        'total'  => ['bytes' => 0, 'count' => 0],
        'items'  => [],
    ];
}

function daily_defaults(): array
{
    return [
        'date'  => date('Y-m-d'),
        'bytes' => 0,
        'count' => 0,
        'logs'  => [],
    ];
}

function daily_file(string $date = ''): string
{
    if ($date === '') {
        $date = date('Y-m-d');
    }
    $stamp = date('Ymd', strtotime($date));
    return DATA_DIR . '/' . $stamp . '.json';
}

function migrate_stats(): void
{
    if (is_file(INDEX_FILE)) {
        return;
    }
    if (!is_file(STATS_FILE)) {
        return;
    }
    $raw = @file_get_contents(STATS_FILE);
    if ($raw === false || $raw === '') {
        return;
    }
    $d = json_decode($raw, true);
    if (!is_array($d)) {
        return;
    }
    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0755, true);
    }
    $idx = [
        'secret' => $d['secret'] ?? bin2hex(random_bytes(24)),
        'total'  => $d['total'] ?? ['bytes' => 0, 'count' => 0],
        'items'  => $d['items'] ?? [],
    ];
    @file_put_contents(INDEX_FILE, json_encode($idx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $today = date('Y-m-d');
    $daily = [
        'date'  => $today,
        'bytes' => $d['today']['bytes'] ?? 0,
        'count' => $d['today']['count'] ?? 0,
        'logs'  => $d['logs'] ?? [],
    ];
    @file_put_contents(daily_file($today), json_encode($daily, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    @rename(STATS_FILE, STATS_FILE . '.bak');
}

function with_stats(callable $fn): array
{
    migrate_stats();

    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0755, true);
    }

    $idx_fp = @fopen(INDEX_FILE, 'c+');
    if ($idx_fp === false) {
        $rawIdx = @file_get_contents(INDEX_FILE);
        $rawDay = @file_get_contents(daily_file());
        $idx = ($rawIdx !== false && $rawIdx !== '') ? json_decode($rawIdx, true) : null;
        if (!is_array($idx)) {
            $idx = index_defaults();
        }
        $idx += index_defaults();
        $daily = ($rawDay !== false && $rawDay !== '') ? json_decode($rawDay, true) : null;
        if (!is_array($daily)) {
            $daily = daily_defaults();
        }
        $daily += daily_defaults();
        if (!is_string($idx['secret']) || $idx['secret'] === '') {
            $idx['secret'] = bin2hex(random_bytes(24));
        }
        $d = [
            'secret' => $idx['secret'],
            'today'  => [
                'date'  => date('Y-m-d'),
                'bytes' => $daily['bytes'] ?? 0,
                'count' => $daily['count'] ?? 0,
            ],
            'total'  => $idx['total'],
            'items'  => $idx['items'],
            'logs'   => $daily['logs'] ?? [],
        ];
        $out = $fn($d);
        if (!is_array($out)) {
            $out = $d;
        }
        return $out;
    }
    flock($idx_fp, LOCK_EX);
    $idx_raw = stream_get_contents($idx_fp);
    $idx = ($idx_raw !== '' && $idx_raw !== false) ? json_decode($idx_raw, true) : null;
    if (!is_array($idx)) {
        $idx = index_defaults();
    }
    $idx += index_defaults();
    if (!is_string($idx['secret']) || $idx['secret'] === '') {
        $idx['secret'] = bin2hex(random_bytes(24));
    }

    $today = date('Y-m-d');
    $dFile = daily_file($today);
    $d_fp = @fopen($dFile, 'c+');
    if ($d_fp === false) {
        $daily = daily_defaults();
    } else {
        flock($d_fp, LOCK_EX);
        $d_raw = stream_get_contents($d_fp);
        $daily = ($d_raw !== '' && $d_raw !== false) ? json_decode($d_raw, true) : null;
        if (!is_array($daily)) {
            $daily = daily_defaults();
        }
        $daily += daily_defaults();
    }

    $d = [
        'secret' => $idx['secret'],
        'today'  => [
            'date'  => $today,
            'bytes' => $daily['bytes'] ?? 0,
            'count' => $daily['count'] ?? 0,
        ],
        'total'  => $idx['total'],
        'items'  => $idx['items'],
        'logs'   => $daily['logs'] ?? [],
    ];

    $out = $fn($d);
    if (!is_array($out)) {
        $out = $d;
    }

    if ($d_fp !== false) {
        $new_daily = [
            'date'  => $today,
            'bytes' => $out['today']['bytes'],
            'count' => $out['today']['count'],
            'logs'  => $out['logs'],
        ];
        ftruncate($d_fp, 0);
        rewind($d_fp);
        fwrite($d_fp, json_encode($new_daily, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($d_fp);
        flock($d_fp, LOCK_UN);
        fclose($d_fp);
    }

    $new_idx = [
        'secret' => $out['secret'],
        'total'  => $out['total'],
        'items'  => $out['items'],
    ];
    ftruncate($idx_fp, 0);
    rewind($idx_fp);
    fwrite($idx_fp, json_encode($new_idx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($idx_fp);
    flock($idx_fp, LOCK_UN);
    fclose($idx_fp);

    return $out;
}

function secret_quick(): string
{
    migrate_stats();
    $raw = @file_get_contents(INDEX_FILE);
    if ($raw !== false && $raw !== '') {
        $d = json_decode($raw, true);
        if (is_array($d) && !empty($d['secret'])) {
            return (string)$d['secret'];
        }
    }
    $d = with_stats(static fn($d) => $d);
    return $d['secret'] ?? '';
}

function client_info(): array
{
    // 头来源优先级：CF-Connecting-IP -> X-Forwarded-For -> X-Real-IP -> REMOTE_ADDR
    // 每级只接受“可用”IP，专门排除保留/非公网段（如 255.0.0.0/8、127.0.0.0/8、
    // 0.0.0.0/8、169.254.0.0/16、224.0.0.0/4），防止被伪造保留地址污染日志
    $ip = '';
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $k) {
        $raw = (string)($_SERVER[$k] ?? '');
        foreach (array_map('trim', explode(',', $raw)) as $cand) {
            if (is_usable_client_ip($cand)) {
                $ip = $cand;
                break 2;
            }
        }
    }
    if ($ip === '') {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    }
    if (preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $m)) {
        $ip = $m[1];
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = '0.0.0.0';
    }
    $type = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'v6' : 'v4';
    return [$ip, $type];
}

/** 仅接受真实可用的客户端 IP，排除保留/特殊段 */
function is_usable_client_ip(string $ip): bool
{
    $ip = trim($ip);
    if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }
    if (preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $m)) {
        $ip = $m[1];
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        if (strtolower(substr($ip, 0, 3)) === 'ff:') {
            return false; // IPv6 多播 ff00::/8
        }
        return true;
    }
    $n = (int) sprintf('%u', ip2long($ip));
    if ($n === 0) {
        return false; // 0.0.0.0/8
    }
    if (($n & 0xFF000000) === 0x7F000000) {
        return false; // 127.0.0.0/8 回环
    }
    if (($n & 0xFFFF0000) === 0xA9FE0000) {
        return false; // 169.254.0.0/16 链路本地
    }
    if (($n & 0xF0000000) === 0xE0000000) {
        return false; // 224.0.0.0/4 多播
    }
    if (($n & 0xF0000000) === 0xF0000000) {
        return false; // 240.0.0.0/4 保留（含 255.0.0.0/8）
    }
    return true;
}

function log_push(array &$d, string $act, string $target, int $bytes, string $ip, string $ipt): void
{
    array_unshift($d['logs'], [
        't'     => time(),
        'th'    => date('m-d H:i'),
        'ip'    => $ip,
        'ipt'   => $ipt,
        'act'   => $act,
        'target' => mb_substr($target, 0, 160),
        'bytes' => $bytes,
        'ua'    => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120),
    ]);
    $d['logs'] = array_slice($d['logs'], 0, 500);
}

function record_download(array &$d, string $path, int $bytes, string $ip, string $ipt): void
{
    $id = md5($path);
    if (!isset($d['items'][$id])) {
        $d['items'][$id] = ['path' => $path, 'count' => 0, 'bytes' => 0];
    }
    $d['items'][$id]['count']++;
    $d['items'][$id]['bytes'] += $bytes;
    $d['total']['bytes'] += $bytes;
    $d['total']['count']++;
    $d['today']['bytes'] += $bytes;
    $d['today']['count']++;
    if (count($d['items']) > 200) {
        uasort($d['items'], static fn($a, $b) => $a['count'] <=> $b['count']);
        $d['items'] = array_slice($d['items'], -200, null, true);
    }
    log_push($d, 'download', $path, $bytes, $ip, $ipt);
}

function read_daily(string $date): array
{
    $f = daily_file($date);
    if (!is_file($f)) {
        return [];
    }
    $raw = @file_get_contents($f);
    if ($raw === false || $raw === '') {
        return [];
    }
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

function list_daily_files(): array
{
    $out = [];
    if (!is_dir(DATA_DIR)) {
        return $out;
    }
    foreach (glob(DATA_DIR . '/*.json') as $f) {
        $name = basename($f, '.json');
        if (preg_match('/^\\d{8}$/', $name)) {
            $out[] = $name;
        }
    }
    rsort($out);
    return $out;
}

function read_index(): array
{
    migrate_stats();
    $raw = @file_get_contents(INDEX_FILE);
    if ($raw === false || $raw === '') {
        return index_defaults();
    }
    $d = json_decode($raw, true);
    return is_array($d) ? $d : index_defaults();
}

function active_download_start(string $path, string $ip, string $ipt, int $totalBytes): string
{
    $fp = @fopen(ACTIVE_FILE, 'c+');
    if ($fp === false) return '';
    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $d = ($raw !== '' && $raw !== false) ? json_decode($raw, true) : null;
    if (!is_array($d)) $d = [];
    $now = time();
    foreach ($d as $k => $v) {
        if (($v['started'] ?? 0) < $now - 300) {
            unset($d[$k]);
        }
    }
    $key = bin2hex(random_bytes(8));
    $d[$key] = [
        'path'   => $path,
        'ip'     => $ip,
        'ipt'    => $ipt,
        'started' => $now,
        'total'  => $totalBytes,
    ];
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $key;
}

function active_download_update(string $key, int $sent): void
{
    if ($key === '') return;
    $fp = @fopen(ACTIVE_FILE, 'c+');
    if ($fp === false) return;
    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $d = ($raw !== '' && $raw !== false) ? json_decode($raw, true) : [];
    if (!is_array($d)) $d = [];
    if (isset($d[$key])) {
        $d[$key]['sent'] = $sent;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($fp);
    }
    flock($fp, LOCK_UN);
    fclose($fp);
}

function active_download_end(string $key): void
{
    if ($key === '') return;
    $fp = @fopen(ACTIVE_FILE, 'c+');
    if ($fp === false) return;
    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $d = ($raw !== '' && $raw !== false) ? json_decode($raw, true) : null;
    if (!is_array($d)) $d = [];
    unset($d[$key]);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

function get_active_downloads(): array
{
    $fp = @fopen(ACTIVE_FILE, 'c+');
    if ($fp === false) return [];
    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    if ($raw === false || $raw === '') return [];
    $d = json_decode($raw, true);
    if (!is_array($d)) return [];
    $now = time();
    $out = [];
    foreach ($d as $k => $v) {
        if (($v['started'] ?? 0) >= $now - 300) {
            $v['elapsed'] = $now - (int)($v['started']);
            $v['sent'] = (int)($v['sent'] ?? 0);
            $v['key'] = $k;
            $out[] = $v;
        }
    }
    return $out;
}

function arch_of(string $name): string
{
    $n = strtolower($name);
    if ($n === '64' || str_starts_with($n, 'win-x64') || str_contains($n, 'x64')
        || str_contains($n, 'amd64') || str_contains($n, 'x86_64') || str_contains($n, '64位')) {
        return 'x64';
    }
    if ($n === '32' || str_starts_with($n, 'win-x86') || str_contains($n, 'x86')
        || str_contains($n, 'win32') || str_contains($n, 'i386') || str_contains($n, '32位')) {
        return 'x86';
    }
    if (str_contains($n, 'arm64') || str_contains($n, 'aarch64')) {
        return 'arm64';
    }
    if (str_contains($n, 'arm')) {
        return 'arm';
    }
    return '';
}

function dir_size(string $dir): int
{
    $s = 0;
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $f) {
            if ($f->isFile()) {
                $s += (int)$f->getSize();
            }
        }
    } catch (Throwable $e) {
    }
    return $s;
}

function safe_rel(string $rel): ?string
{
    $rel = trim($rel, '/');
    if ($rel === '') {
        return '';
    }
    $abs = realpath(APP_ROOT . '/' . $rel);
    if ($abs === false || ($abs !== APP_ROOT && !str_starts_with($abs, APP_ROOT . '/'))) {
        return null;
    }
    if (!is_dir($abs)) {
        return null;
    }
    return ltrim(str_replace(APP_ROOT, '', $abs), '/');
}

function fmt_bytes(int $n): string
{
    if ($n < 1024) {
        return $n . ' B';
    }
    $v = (float)$n;
    foreach (['KB', 'MB', 'GB'] as $u) {
        $v /= 1024;
        if ($v < 1024) {
            return sprintf('%.2f %s', $v, $u);
        }
    }
    $v /= 1024;
    return sprintf('%.2f TB', $v);
}

function build_map(): array
{
    $map = [];
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(APP_ROOT, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $f) {
            $rel = ltrim(str_replace(APP_ROOT . '/', '', $f->getPathname()), '/');
            $map[md5($rel)] = $rel;
        }
    } catch (Throwable $e) {
    }
    return $map;
}

function build_map_cached(int $ttl = 300): array
{
    $cacheFile = DATA_DIR . '/filemap.json';
    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        $raw = @file_get_contents($cacheFile);
        if ($raw !== false && $raw !== '') {
            $cached = json_decode($raw, true);
            if (is_array($cached)) {
                return $cached;
            }
        }
    }
    $map = build_map();
    if (is_dir(DATA_DIR)) {
        @file_put_contents($cacheFile, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    return $map;
}

function dl_link(string $relPath, string $secret): string
{
    $token = link_create($relPath, $secret);
    if ($token !== '') {
        return 'download.php?token=' . urlencode($token);
    }
    $e = time() + TOKEN_TTL;
    $sig = hash_hmac('sha256', $relPath . '|' . $e, $secret);
    return 'download.php?i=' . md5($relPath) . '&e=' . $e . '&s=' . $sig;
}


function aggregate_child_count(string $dirRel, array $stats): int
{
    $total = 0;
    $prefix = $dirRel === '' ? '' : $dirRel . '/';
    foreach ($stats['items'] ?? [] as $item) {
        $p = $item['path'] ?? '';
        if ($prefix !== '' && str_starts_with($p, $prefix)) {
            $total += (int)($item['count'] ?? 0);
        }
    }
    return $total;
}

function entry_of(string $relPath, bool $isDir, int $size, array $stats, string $secret): array
{
    $name = basename($relPath);
    $parent = basename(dirname($relPath));
    $meta = item_meta_get($relPath);
    $arch = $meta['arch'] !== '' ? $meta['arch'] : (arch_of($name) ?: arch_of($parent));
    // 是否 zip 打包：优先用人工覆盖（items.json），否则按命名词尾推断
    $isZipFolder = $isDir && ($meta['zip'] !== null ? $meta['zip'] : str_ends_with($name, '-zip'));
    $id = md5($relPath);
    if ($isDir) {
        $count = aggregate_child_count($relPath, $stats) + (int)($stats['items'][$id]['count'] ?? 0);
    } else {
        $count = (int)($stats['items'][$id]['count'] ?? 0);
    }
    $mt = (int)@filemtime(APP_ROOT . '/' . $relPath);
    return [
        'name'  => $name,
        'path'  => $relPath,
        'type'  => $isDir ? 'dir' : 'file',
        'size'  => $size,
        'size_h' => $size > 0 ? fmt_bytes($size) : '--',
        'arch'  => $arch ?: '--',
        'zip'   => $isZipFolder,
        'hidden'=> (bool) ($meta['hidden'] ?? false),
        'count' => $count,
        'mtime' => $mt,
        'mtime_h' => $mt > 0 ? date('Y-m-d H:i', $mt) : '--',
        'link'  => (!$isDir || $isZipFolder) ? dl_link($relPath, $secret) : null,
        'lock'  => lock_guard($relPath),
    ];
}

function scan_entries(string $dirRel, array $stats, string $secret): array
{
    $abs = APP_ROOT . ($dirRel === '' ? '' : '/' . $dirRel);
    $parentArch = arch_of(basename($dirRel));
    $entries = [];
    foreach (scandir($abs) as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $rel = ($dirRel === '' ? '' : $dirRel . '/') . $name;
        $p = $abs . '/' . $name;
        $isDir = is_dir($p);
        $size = $isDir ? dir_size($p) : (int)@filesize($p);
        $e = entry_of($rel, $isDir, $size, $stats, $secret);
        if ($e['hidden']) {
            continue;
        }
        if ($e['arch'] === '--' && $parentArch !== '') {
            $e['arch'] = $parentArch;
        }
        $entries[] = $e;
    }
    usort($entries, static function ($a, $b) {
        if ($a['type'] !== $b['type']) {
            return $a['type'] === 'dir' ? -1 : 1;
        }
        return strcmp($a['name'], $b['name']);
    });
    return $entries;
}

function search_entries(string $q, array $stats, string $secret): array
{
    $out = [];
    $ql = mb_strtolower($q);
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(APP_ROOT, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $f) {
            $rel = ltrim(str_replace(APP_ROOT . '/', '', $f->getPathname()), '/');
            $nl = mb_strtolower($rel);
            if (!str_contains($nl, $ql)) {
                continue;
            }
            $isDir = $f->isDir();
            $size = $isDir ? dir_size($f->getPathname()) : (int)$f->getSize();
            $e = entry_of($rel, $isDir, $size, $stats, $secret);
            if ($e['hidden']) {
                continue;
            }
            // 密码保护：被锁条目不允许出现在搜索结果（防止信息泄露）
            if ($e['lock'] !== null) {
                continue;
            }
            $out[] = $e;
            if (count($out) >= 300) {
                break;
            }
        }
    } catch (Throwable $e) {
    }
    return $out;
}

function top_dirs(): array
{
    $out = [];
    foreach (scandir(APP_ROOT) as $name) {
        if ($name === '.' || $name === '..' || !is_dir(APP_ROOT . '/' . $name)) {
            continue;
        }
        $out[] = $name;
    }
    sort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}

function breadcrumb_of(string $dirRel): array
{
    $bc = [['name' => 'app', 'dir' => '']];
    if ($dirRel === '') {
        return $bc;
    }
    $acc = '';
    foreach (explode('/', $dirRel) as $p) {
        $acc = $acc === '' ? $p : $acc . '/' . $p;
        $bc[] = ['name' => $p, 'dir' => $acc];
    }
    return $bc;
}

function mask_ip(string $ip, string $type): string
{
    if ($type === 'v4') {
        $p = explode('.', $ip);
        if (count($p) === 4) {
            return $p[0] . '.' . $p[1] . '.*.*';
        }
        return '*.*.*.*';
    }
    $g = explode(':', $ip);
    if (count($g) >= 4) {
        return implode(':', array_slice($g, 0, 2)) . ':****:' . implode(':', array_slice($g, -2));
    }
    return '****';
}

function mime_of(string $name): string
{
    $e = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
    return match ($e) {
        'zip', '7z', 'rar' => 'application/zip',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        default => 'application/octet-stream',
    };
}

function send_disposition(string $name): void
{
    $fallback = preg_replace('/[^\x20-\x7E]/', '_', $name) ?: 'download';
    $fallback = str_replace('"', '_', $fallback);
    header("Content-Disposition: attachment; filename=\"{$fallback}\"; filename*=UTF-8''" . rawurlencode($name));
}

// ============================================================
// Link token management - ThinkPHP-style route token system
// ============================================================

const LINKS_FILE = DATA_DIR . '/links.json';

function links_defaults(): array
{
    return [];
}

function link_create(string $relPath, string $secret): string
{
    $token = bin2hex(random_bytes(12));
    $expiry = time() + TOKEN_TTL;
    $sig = hash_hmac('sha256', $relPath . '|' . $expiry, $secret);

    $fp = @fopen(LINKS_FILE, 'c+');
    if ($fp === false) return '';
    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $d = ($raw !== '' && $raw !== false) ? json_decode($raw, true) : null;
    if (!is_array($d)) $d = [];

    $now = time();
    $reuse = '';
    foreach ($d as $k => $v) {
        if (($v['expiry'] ?? 0) < $now) {
            unset($d[$k]);
            continue;
        }
        // 同一文件 24 小时内复用同一令牌，使「已下载」累计到同一链接
        if ($reuse === '' && ($v['path'] ?? '') === $relPath) {
            $reuse = $k;
        }
    }

    if ($reuse !== '') {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return $reuse;
    }

    $d[$token] = [
        'path'    => $relPath,
        'file_id' => md5($relPath),
        'expiry'  => $expiry,
        'sig'     => $sig,
        'created' => $now,
    ];

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return $token;
}

function batch_link_create(array $rels, string $secret): string
{
    $clean = [];
    foreach ($rels as $r) {
        $r = trim((string) $r);
        if ($r === '') {
            continue;
        }
        $abs = realpath(APP_ROOT . '/' . $r);
        if ($abs !== false && ($abs === APP_ROOT || str_starts_with($abs, APP_ROOT . '/'))) {
            $clean[] = $r;
        }
    }
    $clean = array_values(array_unique($clean));
    if ($clean === []) {
        return '';
    }
    sort($clean, SORT_STRING);

    $token  = bin2hex(random_bytes(12));
    $expiry = time() + TOKEN_TTL;
    $sig    = hash_hmac('sha256', 'batch|' . implode('|', $clean) . '|' . $expiry, $secret);

    $fp = @fopen(LINKS_FILE, 'c+');
    if ($fp === false) {
        return '';
    }
    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $d = ($raw !== '' && $raw !== false) ? json_decode($raw, true) : null;
    if (!is_array($d)) {
        $d = [];
    }
    $now = time();
    foreach ($d as $k => $v) {
        if (($v['expiry'] ?? 0) < $now) {
            unset($d[$k]);
            continue;
        }
    }
    $d[$token] = [
        'path'    => 'BATCH:' . implode('|', $clean),
        'batch'   => 1,
        'items'   => $clean,
        'expiry'  => $expiry,
        'sig'     => $sig,
        'created' => $now,
    ];
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return $token;
}

function link_lookup(string $token): ?array
{
    $raw = @file_get_contents(LINKS_FILE);
    if ($raw === false || $raw === '') return null;
    $d = json_decode($raw, true);
    if (!is_array($d) || !isset($d[$token])) return null;
    $link = $d[$token];
    if (($link['expiry'] ?? 0) < time()) return null;
    return $link;
}

function link_list(): array
{
    $raw = @file_get_contents(LINKS_FILE);
    if ($raw === false || $raw === '') return [];
    $d = json_decode($raw, true);
    if (!is_array($d)) return [];
    $now = time();
    $out = [];
    foreach ($d as $token => $v) {
        $remaining = max(0, (int)($v['expiry'] ?? 0) - $now);
        if ($remaining <= 0) continue;
        $out[] = [
            'token'      => $token,
            'path'       => $v['path'] ?? '',
            'file_id'    => $v['file_id'] ?? '',
            'route'      => '/d/' . $token,
            'expiry'     => (int)($v['expiry'] ?? 0),
            'created'    => (int)($v['created'] ?? 0),
            'last'       => (int)($v['last'] ?? $v['created'] ?? 0),
            'remaining'  => $remaining,
            'remaining_h' => gmdate('H:i:s', $remaining),
            'downloaded' => (int)($v['downloaded'] ?? 0),
        ];
    }
    // 已产生下载的链接排在最前，其余按最近活动/创建时间排序
    usort($out, static function ($a, $b) {
        if (($a['downloaded'] > 0) !== ($b['downloaded'] > 0)) {
            return $a['downloaded'] > 0 ? -1 : 1;
        }
        return $b['last'] <=> $a['last'];
    });
    return $out;
}

function link_cleanup(): void
{
    $fp = @fopen(LINKS_FILE, 'c+');
    if ($fp === false) return;
    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $d = ($raw !== '' && $raw !== false) ? json_decode($raw, true) : null;
    if (!is_array($d)) $d = [];
    $now = time();
    $changed = false;
    foreach ($d as $k => $v) {
        if (($v['expiry'] ?? 0) < $now) {
            unset($d[$k]);
            $changed = true;
        }
    }
    if ($changed) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($fp);
    }
    flock($fp, LOCK_UN);
    fclose($fp);
}

function link_add_download(string $token, int $bytes): void
{
    $fp = @fopen(LINKS_FILE, 'c+');
    if ($fp === false) return;
    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $d = ($raw !== '' && $raw !== false) ? json_decode($raw, true) : null;
    if (!is_array($d)) $d = [];
    if (isset($d[$token])) {
        $d[$token]['downloaded'] = (int)($d[$token]['downloaded'] ?? 0) + $bytes;
        $d[$token]['last'] = time();
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($fp);
    }
    flock($fp, LOCK_UN);
    fclose($fp);
}

// ============================================================
// Route table - ThinkPHP-compatible route definitions
// ============================================================

function route_table(): array
{
    return [
        [
            'method'  => 'GET',
            'pattern' => '/',
            'handler' => 'index.php',
            'desc'    => '首页 - 工具列表',
            'params'  => [],
        ],
        [
            'method'  => 'GET|POST',
            'pattern' => '/d/:token',
            'handler' => 'download.php',
            'desc'    => '文件下载 - 路由令牌模式(隐藏真实路径)',
            'params'  => ['token' => '短令牌(24位hex),有效期24小时'],
        ],
        [
            'method'  => 'GET|POST',
            'pattern' => '/3089337655',
            'handler' => '3089337655/index.php',
            'desc'    => '后台管理面板 - 登录+仪表盘',
            'params'  => [],
        ],
        [
            'method'  => 'GET',
            'pattern' => '/3089337655/?api=dashboard',
            'handler' => '3089337655/index.php',
            'desc'    => '后台API - 仪表盘数据',
            'params'  => [],
        ],
        [
            'method'  => 'GET',
            'pattern' => '/3089337655/?api=links',
            'handler' => '3089337655/index.php',
            'desc'    => '后台API - 链接管理(剩余时间)',
            'params'  => [],
        ],
        [
            'method'  => 'GET',
            'pattern' => '/3089337655/?api=routes',
            'handler' => '3089337655/index.php',
            'desc'    => '后台API - 路由表查看',
            'params'  => [],
        ],
        [
            'method'  => 'POST',
            'pattern' => '/3089337655/?api=upload',
            'handler' => '3089337655/index.php',
            'desc'    => '后台API - 文件上传(app目录)',
            'params'  => [],
        ],
    ];
}

function route_match(string $uri): ?array
{
    $routes = route_table();
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';

    foreach ($routes as $route) {
        $pattern = $route['pattern'];
        if (str_contains($pattern, ':token')) {
            $regex = '#^/d/([a-f0-9]{24})$#i';
            if (preg_match($regex, $path, $m)) {
                $route['matched_token'] = $m[1];
                return $route;
            }
        } elseif ($pattern === '/') {
            if ($path === '/' || $path === '') {
                return $route;
            }
        } elseif (str_starts_with($path, $pattern)) {
            return $route;
        }
    }
    return null;
}
