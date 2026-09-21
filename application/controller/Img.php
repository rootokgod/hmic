<?php
declare(strict_types=1);

namespace app\controller;

/**
 * 图片令牌代理（复用站点根 img.php）
 * 路由：/img.php
 */
class Img
{
    public function index(): void
    {
        require dirname(__DIR__, 2) . '/img.php';
        exit;
    }
}
