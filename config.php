<?php
// 存储配置
$config = [
    'debug' => true,
    'storage' => [
        'type' => 'sqlite',
        'config' => [
            // JSON 配置
            'path' => __DIR__ . '/data/data.db',
        ]
    ],

    // 图片存储配置
    'image_storage' => [
        'path' => __DIR__ . '/data/image',
        'max_size' => 5 * 1024 * 1024, // 5MB
        'allowed_types' => [
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/gif' => '.gif',
            'image/webp' => '.webp'
        ]
    ],

    'api' => [
        'token' => [
            '调用方ID' => [
                '调用方应用ID' => '调用方Token'
            ]
        ]
    ],

    'admin' => [
        'name' => '管理员用户名',
        'password' => '管理员密码',
        'log_file' => __DIR__ . '/data/log/admin_actions.log'
    ]
];

return $config;
