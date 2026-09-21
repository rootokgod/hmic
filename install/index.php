<?php
/**
 * 程序安装向导 - 入口
 * ----------------------------------------------------------------
 * 首次访问（未检测到安装完成标记）时，根入口 index.php 与 public/index.php
 * 都会引导至本文件。首次初始化不预置任何数据，站点所需 app/ 目录与
 * data/ 下的 json 基础配置均由向导在「创建配置」步骤自动生成。
 * 适用框架：ThinkPHP 兼容路由；推荐运行环境：PHP ~8.5（>= 8.1 可用）。
 */
if (PHP_VERSION_ID < 80100) {
    $iwMsg = htmlspecialchars('当前 PHP 版本过旧（' . PHP_VERSION . '），安装向导需要 PHP 8.1 及以上版本。', ENT_QUOTES, 'UTF-8');
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><title>运行环境不符</title></head>'
        . '<body style="font-family:system-ui;background:#111;color:#eee;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0">'
        . '<div style="max-width:520px;text-align:center;padding:40px"><h1>无法运行安装向导</h1>'
        . '<p style="color:#aaa">' . $iwMsg . '</p>'
        . '<p style="color:#666;font-size:12px">推荐部署环境：PHP 8.5（支持 8.1 及以上）</p></div></body></html>';
    return;
}

require __DIR__ . '/lib/boot.php';
require __DIR__ . '/lib/view.php';
require __DIR__ . '/lib/wizard.php';

iw_session();

// 已安装完成：拒绝再次写入（除非手动删除安装完成标记）
if (iw_installed()) {
    iw_render_installed();
    return;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    iw_handle_post();
    return;
}

iw_dispatch();