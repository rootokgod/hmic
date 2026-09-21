<?php
declare(strict_types=1);

namespace app\controller;

/**
 * 贴纸图片公开服务 /stk/:file
 * 仅限 data/jpg/sticker 目录下的 png/jpg/jpeg/webp，basename 白名单，可公开缓存
 */
class Sticker
{
    public function index(string $file): void
    {
        require_once dirname(__DIR__, 2) . '/common.php';

        $name = sticker_safe_name((string) $file);
        if ($name === '') {
            http_response_code(400);
            exit('Bad file');
        }
        $full = STICKER_DIR . '/' . $name;
        if (!is_file($full)) {
            http_response_code(404);
            exit('Not found');
        }

        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'][$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        readfile($full);
        exit;
    }
}