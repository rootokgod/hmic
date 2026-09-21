<?php
declare(strict_types=1);

// 应用公共函数统一存放在站点根目录 common.php（各控制器已 require_once）。
// ThinkPHP8 启动时会自动加载 application/common.php，此处仅作桥接，
// 避免同一批常量/函数被声明两遍导致 "Cannot redeclare" 致命错误。
require_once dirname(__DIR__) . '/common.php';