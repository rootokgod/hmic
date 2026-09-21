<?php
// 缓存配置（ORM/Model 服务启动需要默认驱动）
return [
    'default' => 'file',
    'stores'  => [
        'file' => [
            'type'       => 'File',
            'path'       => '',
            'prefix'     => '',
            'expire'     => 0,
            'tag_prefix' => 'tag:',
            'serialize'  => [],
        ],
    ],
];
