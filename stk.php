<?php
/**
 * 贴纸图片服务：/stk/<file> → nginx rewrite → /stk.php?f=<file>
 * 仅服务 data/jpg/sticker 下的 png/jpg/jpeg/webp，文件名白名单，支持公开缓存
 */
$f = basename((string) ($_GET['f'] ?? ''));
if (!preg_match('/^[A-Za-z0-9._-]+$/', $f)) {
    http_response_code(400);
    exit('Bad file');
}
$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
$mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'][$ext] ?? '';
if ($mime === '') {
    http_response_code(400);
    exit('Bad file');
}
$full = __DIR__ . '/data/jpg/sticker/' . $f;
if (!is_file($full)) {
    http_response_code(404);
    exit('Not found');
}
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($full);
exit;