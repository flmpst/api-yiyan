<?php
$dataDir = __DIR__ . '/data';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// 数据库路径
$dbPath = $dataDir . '/data.db';

// 如果数据库文件不存在
if (!file_exists($dbPath)) {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 创建 main 表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS \"main\" (
            \"id\" INTEGER NOT NULL UNIQUE,
            \"content\" TEXT NOT NULL,
            \"content_type\" TEXT NOT NULL DEFAULT 'text',
            \"user_name\" TEXT NOT NULL,
            \"add_time\" TEXT NOT NULL,
            \"quote_source\" TEXT,
            \"is_hidden\" BLOB DEFAULT 'false',
            PRIMARY KEY(\"id\" AUTOINCREMENT)
        )
    ");
    
    // 创建 sqlean_define 表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sqlean_define(
            name text primary key, 
            type text, 
            body text
        )
    ");
    
    $pdo = null; // 关闭连接
}

// 日志文件路径
$logFile = $dataDir . '/log/admin_actions.log';
$logDir = dirname($logFile);

// 确保日志目录存在
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// 如果日志文件不存在
if (!file_exists($logFile)) {
    touch($logFile);
}

$config = [
    'debug' => true,
    'storage' => [
        'type' => 'sqlite',
        'config' => [
            // JSON 配置
            'path' => $dbPath,
        ]
    ],

    // 图片存储配置
    'image_storage' => [
        'path' => $dataDir . '/image',
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
        'log_file' => $logFile
    ]
];

return $config;