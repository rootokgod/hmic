<?php
// 路由配置
return [
    // 强制使用路由定义，未定义的路由直接 404
    'url_route_must'    => true,

    // 路由是否完全匹配（避免 admin 前缀匹配到 admin/login 等）
    'route_complete_match' => true,

    // 默认控制器/操作
    'default_controller' => 'Index',
    'default_action'     => 'index',

    // 路由变量自动转为参数
    'url_common_param'  => true,

    // 是否使用控制器后缀
    'controller_suffix' => false,
];
