<?php
// 存储配置
$config = [
    'storage' => [
        'type' => 'json',
        'config' => [
            // JSON 配置
            'path' => __DIR__ . '/data/7026c04d-662c-4af5-8a23-2661409e50a2.json',
        ]
    ],

    // 图片存储配置
    'image_storage' => [
        'path' => __DIR__ . '/data/image'
    ]
];

return $config;
