<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';

header('X-Content-Type-Options: nosniff');

$action = $_GET['action'] ?? '';
$secret = secret_quick();

function img_token_create(string $relPath, string $secret): array
{
    $token = bin2hex(random_bytes(12));
    $expiry = time() + 3600;
    $sig = hash_hmac('sha256', $relPath . '|' . $expiry, $secret);
    $file = DATA_DIR . '/img_tokens.json';
    $d = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        if ($raw !== false && $raw !== '') $d = json_decode($raw, true) ?: [];
    }
    $now = time();
    foreach ($d as $k => $v) {
        if (($v['expiry'] ?? 0) < $now) unset($d[$k]);
    }
    $d[$token] = [
        'path'    => $relPath,
        'expiry'  => $expiry,
        'sig'     => $sig,
        'created' => $now,
    ];
    @file_put_contents($file, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    return ['token' => $token, 'expires' => $expiry];
}

function img_token_validate(string $token, string $secret): ?string
{
    $file = DATA_DIR . '/img_tokens.json';
    if (!is_file($file)) return null;
    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') return null;
    $d = json_decode($raw, true);
    if (!is_array($d) || !isset($d[$token])) return null;
    $link = $d[$token];
    if (($link['expiry'] ?? 0) < time()) return null;
    $sig = hash_hmac('sha256', $link['path'] . '|' . $link['expiry'], $secret);
    if (!hash_equals($sig, (string)($link['sig'] ?? ''))) return null;
    return $link['path'] ?? null;
}

if ($action === 'get') {
    header('Content-Type: application/json; charset=utf-8');
    $path = trim($_GET['path'] ?? '', '/');
    if (!preg_match('#^data/jpg/[a-zA-Z0-9_]+\.jpg$#', $path)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid path']);
        exit;
    }
    $r = img_token_create('/' . $path, $secret);
    echo json_encode(['ok' => true, 'url' => '/img.php?token=' . $r['token'], 'expires' => $r['expires']]);
    exit;
}

$token = $_GET['token'] ?? '';
if ($token === '') {
    http_response_code(400);
    exit('Missing token');
}

$imgPath = img_token_validate($token, $secret);
if ($imgPath === null) {
    http_response_code(403);
    exit('Invalid or expired token');
}

$fullPath = __DIR__ . $imgPath;
if (!is_file($fullPath)) {
    http_response_code(404);
    exit('Image not found');
}

header('Content-Type: image/jpeg');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
readfile($fullPath);
