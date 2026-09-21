<?php
// +----------------------------------------------------------------------
// | 根入口：安装分发 + 伪静态探针 + 转发 public/
// | 首次访问（未完成安装）进入程序安装向导；安装完成后转发至 public 前端控制器。
// +----------------------------------------------------------------------
declare(strict_types=1);

// 安装向导探针：环境检测页用于在线判定「伪静态 / 友好路由」是否生效
$__iwPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if ($__iwPath === '/__install__/ping') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'pkg' => 'install-wizard', 't' => time()]);
    exit;
}

// 未检测到安装完成标记 -> 运行程序安装向导
$__iwLock = __DIR__ . '/data/Configuration/install.lock';
if (!is_file($__iwLock) || !filesize($__iwLock)) {
    require __DIR__ . '/install/index.php';
    exit;
}

// 已安装 -> 转发至 public 单入口
require __DIR__ . '/public/index.php';