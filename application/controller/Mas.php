<?php
declare(strict_types=1);

namespace app\controller;

/**
 * MAS 激活脚本生成（复用站点根 mas_ps1.php）
 * 路由：/mas.ps1、/mas_ps1.php
 * Windows 激活工具开关关闭时，此处直接拒绝（403），配合首页入口隐藏
 */
class Mas
{
    public function index(): void
    {
        require_once dirname(__DIR__, 2) . '/common.php';
        $st = settings_load();
        if (empty($st['mas_enabled'])) {
            http_response_code(403);
            exit('403 Forbidden');
        }
        require dirname(__DIR__, 2) . '/mas_ps1.php';
        exit;
    }
}
