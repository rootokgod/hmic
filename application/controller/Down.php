<?php
declare(strict_types=1);

namespace app\controller;

/**
 * 文件下载（复用站点根 download.php 的完整逻辑）
 * 路由：/download.php、/d/:token
 */
class Down
{
    public function index(): void
    {
        require dirname(__DIR__, 2) . '/download.php';
        exit;
    }
}
