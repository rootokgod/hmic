<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';

set_time_limit(0);
ignore_user_abort(true);
while (ob_get_level() > 0) {
    ob_end_clean();
}

function fail_page(int $code, string $title, string $msg): void
{
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $m = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>下载失败 - {$t}</title>
<style>
body{font-family:system-ui,"PingFang SC","Microsoft YaHei",sans-serif;background:#fff;color:#1f2937;
display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{text-align:center;padding:40px;border:1px solid #e5e7eb;border-radius:14px;max-width:460px}
h1{font-size:20px;color:#1d4ed8;margin:0 0 10px}
p{color:#6b7280;font-size:14px;line-height:1.7;margin:0 0 22px}
a{display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:9px 22px;
border-radius:8px;font-size:14px}
</style>
</head>
<body>
<div class="box">
<h1>{$t}</h1>
<p>{$m}</p>
<a href="./">返回首页</a>
</div>
</body>
</html>
HTML;
    exit;
}

// ============================================================
// 密码保护：被锁路径的前置校验与密码表单
// ============================================================

/** 拼接追加/合并 ul 令牌后的当前 URL（保留已知参数）。 */
function lock_merge_ul(array $tokens): string
{
    $base = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $pos  = strpos($base, '?');
    $path = $pos === false ? $base : substr($base, 0, $pos);
    $qs   = $pos === false ? '' : substr($base, $pos + 1);
    parse_str($qs, $q);
    unset($q['ul']);
    $q['ul'] = implode(',', $tokens);
    return $path . '?' . http_build_query($q);
}

/** 403 页面：提示输入密码（POST 回当前 URL）。 */
function lock_page(string $guard, string $target, bool $wrongPwd = false): void
{
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    $g = htmlspecialchars($guard, ENT_QUOTES, 'UTF-8');
    $t = htmlspecialchars($target, ENT_QUOTES, 'UTF-8');
    $act = htmlspecialchars((string) ($_SERVER['REQUEST_URI'] ?? '/'), ENT_QUOTES, 'UTF-8');
    $err = $wrongPwd ? '<div class="err">密码错误，请重试</div>' : '';
    echo <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>需要密码 - 资源受保护</title>
<style>
body{font-family:system-ui,"PingFang SC","Microsoft YaHei",sans-serif;background:#f7f8fa;color:#1f2937;
display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{text-align:center;padding:44px 40px;background:#fff;border:1px solid #e5e7eb;border-radius:16px;max-width:430px;box-shadow:0 8px 30px rgba(0,0,0,.06)}
.ic{width:52px;height:52px;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;
border-radius:50%;background:#fef3c7;color:#d97706;font-size:24px}
h1{font-size:19px;margin:0 0 8px}
p{color:#6b7280;font-size:13.5px;line-height:1.7;margin:0 0 18px}
.err{color:#dc2626;font-size:13px;margin-bottom:14px;background:#fef2f2;border:1px solid #fecaca;
border-radius:8px;padding:8px 10px}
input[type=password]{width:100%;padding:11px 13px;border:1px solid #d1d5db;border-radius:9px;font-size:14px;
outline:none;box-sizing:border-box;margin-bottom:12px}
input[type=password]:focus{border-color:#2563eb}
button{width:100%;padding:11px;background:#2563eb;color:#fff;border:none;border-radius:9px;font-size:14px;
cursor:pointer}
button:hover{background:#1d4ed8}
a{display:inline-block;margin-top:16px;color:#9ca3af;font-size:12.5px;text-decoration:none}
</style>
</head>
<body>
<div class="box">
<div class="ic">&#128274;</div>
<h1>该资源受密码保护</h1>
<p>路径 <b>{$g}</b><br>请向站点管理员获取访问密码后输入解锁。</p>
{$err}
<form method="post" action="{$act}">
<label style="display:block;text-align:left;font-size:12.5px;color:#6b7280;margin-bottom:6px">访问密码</label>
<input type="password" name="pwd" autocomplete="off" required autofocus>
<button type="submit">解锁并继续</button>
</form>
<a href="./">返回首页</a>
</div>
</body>
</html>
HTML;
    exit;
}

// ============================================================
// Route token resolution
// Supports both new route token (/d/{token}) and legacy format
// ============================================================

$path = null;
$secret = secret_quick();
$dlTokens = lock_tokens_from_query((string) ($_GET['ul'] ?? ''));

// New route token mode: download.php?token=xxx (set by Nginx rewrite /d/:token)
// Token already contains path + signature - no need to scan file map
$routeToken = (string)($_GET['token'] ?? '');
if ($routeToken !== '' && preg_match('/^[a-f0-9]{24}$/', $routeToken)) {
    $link = link_lookup($routeToken);
    if ($link === null) {
        fail_page(410, '链接已过期', '下载链接已过期(有效期 24 小时),请返回站点刷新页面,重新点击下载按钮获取新链接。');
    }
    // 批量打包下载（后台勾选多项后由服务器合为一个 zip）
    if (!empty($link['batch'])) {
        handle_batch_download((array) ($link['items'] ?? []));
        exit;
    }
    $path = $link['path'];
    // Validate path is within app/ directory
    $real = realpath(APP_ROOT . '/' . $path);
    if ($real === false || ($real !== APP_ROOT && !str_starts_with($real, APP_ROOT . '/'))) {
        fail_page(404, '链接无效', '未找到对应资源,文件可能已被移动或删除。');
    }
} else {
    // Legacy mode: download.php?i=xxx&e=xxx&s=xxx (requires file map)
    $map = build_map_cached();

    $i = (string)($_GET['i'] ?? '');
    $e = (int)($_GET['e'] ?? 0);
    $sig = (string)($_GET['s'] ?? '');

    if (!preg_match('/^[a-f0-9]{32}$/', $i) || $e <= 0 || !preg_match('/^[a-f0-9]{64}$/', $sig)) {
        fail_page(404, '链接无效', '下载链接格式不正确,请从站点页面重新获取。');
    }

    $path = $map[$i] ?? null;
    if ($path === null) {
        fail_page(404, '链接无效', '未找到对应资源,文件可能已被移动或删除。');
    }

    $expect = hash_hmac('sha256', $path . '|' . $e, $secret);
    if (!hash_equals($expect, $sig)) {
        fail_page(403, '签名校验失败', '下载链接签名不正确,请刷新页面重新获取。');
    }
    if (time() > $e) {
        fail_page(410, '链接已过期', '下载链接仅 24 小时内有效,请返回站点刷新页面,重新点击下载按钮获取新链接。');
    }
}

$abs = APP_ROOT . '/' . $path;
if (!is_readable($abs)) {
    fail_page(404, '资源不可用', '文件不存在或暂时无法读取。');
}

// -------- 密码保护：单文件/目录 zip 下载强制校验 --------
$guard = lock_guard($path);
if ($guard !== null && !lock_rel_unlocked($path, $dlTokens, $secret)) {
    $pwd = (string) ($_POST['pwd'] ?? '');
    if ($pwd !== '' && lock_verify_pwd($guard, $pwd)) {
        $dlTokens[] = lock_token_create($guard, $secret);
        header('Location: ' . lock_merge_ul($dlTokens));
        exit;
    }
    lock_page($guard, $path, $pwd !== '');
}

[$ip, $ipt] = client_info();
$recorded = false;
$sent = 0;
$dlKey = '';

$shutdown = static function () use (&$recorded, &$sent, &$dlKey, $path, $ip, $ipt, $routeToken): void {
    if ($dlKey !== '') {
        active_download_end($dlKey);
        $dlKey = '';
    }
    if ($routeToken !== '' && $sent > 0) {
        link_add_download($routeToken, (int)$sent);
    }
    if ($recorded) {
        return;
    }
    $recorded = true;
    with_stats(static function ($d) use ($path, $ip, $ipt, $sent) {
        record_download($d, $path, (int)max($sent, 0), $ip, $ipt);
        return $d;
    });
};
register_shutdown_function($shutdown);

header('X-Accel-Buffering: no');
header('Cache-Control: no-store');

if (is_file($abs)) {
    $name = basename($path);
    $fileSize = (int)filesize($abs);
    header('Content-Type: ' . mime_of($name));
    header('Content-Length: ' . (string)$fileSize);
    send_disposition($name);

    $dlKey = active_download_start($path, $ip, $ipt, $fileSize);

    $fp = fopen($abs, 'rb');
    if ($fp === false) {
        fail_page(500, '读取失败', '服务器打开文件失败,请稍后重试。');
    }
    while (!feof($fp)) {
        $buf = fread($fp, 1024 * 1024);
        if ($buf === false || $buf === '') {
            break;
        }
        echo $buf;
        $sent += strlen($buf);
        if ($dlKey !== '') {
            active_download_update($dlKey, $sent);
        }
        flush();
    }
    fclose($fp);
    $shutdown();
    exit;
}

// 批量打包下载：把多个目录/文件合成一个 zip（STORE 模式），结果缓存于 data/zipcache
function handle_batch_download(array $items): void
{
    global $dlTokens, $secret;

    $c = [];
    foreach ($items as $r) {
        $abs = realpath(APP_ROOT . '/' . $r);
        if ($abs === false || ($abs !== APP_ROOT && !str_starts_with($abs, APP_ROOT . '/'))) {
            continue;
        }
        if (!is_readable($abs)) {
            continue;
        }
        $c[] = ['rel' => $r, 'abs' => $abs];
    }
    // 密码保护：批量任一条目被锁且未解锁 → 要求密码（逐个守卫解锁）
    foreach ($c as $item) {
        $guard = lock_guard($item['rel']);
        if ($guard === null || lock_rel_unlocked($item['rel'], $dlTokens, $secret)) {
            continue;
        }
        $pwd = (string) ($_POST['pwd'] ?? '');
        if ($pwd !== '' && lock_verify_pwd($guard, $pwd)) {
            $dlTokens[] = lock_token_create($guard, $secret);
            header('Location: ' . lock_merge_ul($dlTokens));
            exit;
        }
        lock_page($guard, $item['rel'], $pwd !== '');
    }

    if ($c === []) {
        fail_page(404, '链接无效', '未找到可打包的资源,勾选项可能已被删除。');
    }

    // 缓存键：勾选项集合 + 各顶层条目修改时间
    $keyParts = ['batch'];
    foreach ($c as $item) {
        $keyParts[] = $item['rel'] . '@' . (int) @filemtime($item['abs']);
    }
    $cacheKey = md5(implode('|', $keyParts));
    $cacheDir = DATA_DIR . '/zipcache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    $cachedZip = $cacheDir . '/' . $cacheKey . '.zip';

    [$ip, $ipt] = client_info();

    $stream = static function (string $file, int $size) use ($ip, $ipt, $c): void {
        header('Content-Type: application/zip');
        header('Content-Length: ' . (string) $size);
        send_disposition('批量打包.zip');
        header('X-Accel-Buffering: no');
        header('Cache-Control: no-store');
        $fp = fopen($file, 'rb');
        if ($fp === false) {
            fail_page(500, '读取失败', '读取压缩包失败,请稍后重试。');
        }
        $sent = 0;
        while (!feof($fp)) {
            $buf = fread($fp, 1024 * 1024);
            if ($buf === false || $buf === '') {
                break;
            }
            echo $buf;
            $sent += strlen($buf);
            flush();
        }
        fclose($fp);
        with_stats(static function ($d) use ($ip, $ipt, $sent, $c) {
            record_download($d, '批量打包(' . count($c) . '项)', (int) max($sent, 0), $ip, $ipt);
            return $d;
        });
    };

    if (is_readable($cachedZip) && filesize($cachedZip) > 0) {
        $stream($cachedZip, (int) filesize($cachedZip));
        exit;
    }

    $tmpZip = $cachedZip . '.tmp';
    $zip = new ZipArchive();
    if ($zip->open($tmpZip, ZipArchive::CREATE) !== true) {
        fail_page(500, '打包失败', '创建压缩包失败,请稍后重试。');
    }
    try {
        foreach ($c as $item) {
            $abs  = $item['abs'];
            $base = basename($item['rel']);
            if (is_file($abs)) {
                $zip->addFile($abs, $base);
                $zip->setCompressionName($base, ZipArchive::CM_STORE);
                continue;
            }
            $zip->addEmptyDir($base);
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($it as $f) {
                $inside = $base . '/' . ltrim(str_replace($abs, '', $f->getPathname()), '/');
                if ($f->isDir()) {
                    $zip->addEmptyDir($inside);
                } else {
                    $zip->addFile($f->getPathname(), $inside);
                    $zip->setCompressionName($inside, ZipArchive::CM_STORE);
                }
            }
        }
    } catch (Throwable $e) {
        $zip->close();
        @unlink($tmpZip);
        fail_page(500, '打包失败', '读取勾选资源失败,请稍后重试。');
    }
    $zip->close();

    if (!is_readable($tmpZip)) {
        fail_page(500, '打包失败', '压缩包生成失败,请稍后重试。');
    }
    @rename($tmpZip, $cachedZip);

    $stream($cachedZip, (int) filesize($cachedZip));
    exit;
}

$base = basename($path);
$zipName = (preg_replace('/-zip$/u', '', $base) ?: $base) . '.zip';

// Cache: use directory modification time as cache key
$dirMtime = (int)filemtime($abs);
$cacheKey = md5($path . '|' . $dirMtime);
$cacheDir = DATA_DIR . '/zipcache';
$cachedZip = $cacheDir . '/' . $cacheKey . '.zip';

// Try cached zip first - instant response
if (is_readable($cachedZip) && filemtime($cachedZip) >= $dirMtime) {
    $zipSize = (int)filesize($cachedZip);
    header('Content-Type: application/zip');
    header('Content-Length: ' . (string)$zipSize);
    send_disposition($zipName);
    header('X-Zip-Cache: HIT');

    $dlKey = active_download_start($path, $ip, $ipt, $zipSize);
    $fp = fopen($cachedZip, 'rb');
    if ($fp !== false) {
        while (!feof($fp)) {
            $buf = fread($fp, 1024 * 1024);
            if ($buf === false || $buf === '') break;
            echo $buf;
            $sent += strlen($buf);
            if ($dlKey !== '') {
                active_download_update($dlKey, $sent);
            }
            flush();
        }
        fclose($fp);
    }
    $shutdown();
    exit;
}

// Create zip using PHP ZipArchive with STORE mode (no compression - files are already compressed)
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

$tmpZip = $cacheDir . '/' . $cacheKey . '.tmp';

$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::CREATE) !== true) {
    fail_page(500, '打包失败', '创建压缩包失败,请稍后重试。');
}

// Use STORE mode (no compression) for individual files for maximum speed
try {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $zip->addEmptyDir($base);
    foreach ($it as $f) {
        $inside = $base . '/' . ltrim(str_replace($abs, '', $f->getPathname()), '/');
        if ($f->isDir()) {
            $zip->addEmptyDir($inside);
        } else {
            $zip->addFile($f->getPathname(), $inside);
            // Set individual file compression to STORE for speed
            $zip->setCompressionName($inside, ZipArchive::CM_STORE);
        }
    }
} catch (Throwable $e) {
    $zip->close();
    @unlink($tmpZip);
    fail_page(500, '打包失败', '读取目录失败,请稍后重试。');
}
$zip->close();

if (!is_readable($tmpZip)) {
    fail_page(500, '打包失败', '压缩包生成失败,请稍后重试。');
}

// Rename tmp to final cache name
@rename($tmpZip, $cachedZip);

$zipSize = (int)filesize($cachedZip);
header('Content-Type: application/zip');
header('Content-Length: ' . (string)$zipSize);
send_disposition($zipName);
header('X-Zip-Cache: MISS');

$dlKey = active_download_start($path, $ip, $ipt, $zipSize);

$fp = fopen($cachedZip, 'rb');
if ($fp !== false) {
    while (!feof($fp)) {
        $buf = fread($fp, 1024 * 1024);
        if ($buf === false || $buf === '') break;
        echo $buf;
        $sent += strlen($buf);
        if ($dlKey !== '') {
            active_download_update($dlKey, $sent);
        }
        flush();
    }
    fclose($fp);
}
// Keep cached zip for future downloads (don't delete)
$shutdown();
exit;
