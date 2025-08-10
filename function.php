<?php

function initWebSecurity(){
    $security = new WebSecurity;
    $security->checkRequest();
}

// 创建存储策略
function createStorageStrategy($config)
{
    if ($config['storage']['type'] === 'sqlite') {
        return new SqliteStorage($config['storage']['config']);
    }
    throw new Exception('Unsupported storage type');
}

function purifyText($text)
{
    if (!is_string($text)) {
        return $text;
    }

    // 移除不可见字符
    $text = preg_replace('/[^\PC\s]/u', '', $text);

    // 转换特殊字符为HTML实体
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return $text;
}

function apiResponse($data = null, $message = '', $code = 200, $errors = [])
{
    $status = $code >= 200 && $code < 300 ? 'success' : 'error';

    $response = [
        'meta' => [
            'version' => API_VERSION,
            'timestamp' => time(),
            'status' => $status,
            'code' => $code
        ],
        'message' => $message,
        'data' => $data,
    ];

    if (!empty($errors)) {
        $response['errors'] = $errors;
    }

    header('Content-Type: application/json; charset=utf-8');
    ob_end_clean();
    http_response_code($code);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// API认证函数
function validateApiCredentials()
{
    global $config;

    if (!isset($config['api']['token']) || empty($config['api']['token'])) {
        throw new Exception('API token configuration is missing', 500);
    }

    $clientId = $_SERVER['HTTP_X_CLIENT_ID'] ?? null;
    $appId = $_SERVER['HTTP_X_APP_ID'] ?? null;

    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        throw new Exception('Missing authorization token', 401);
    }

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

    if (!preg_match('/^Bearer\s+([a-zA-Z0-9\-]+)$/', $authHeader, $matches)) {
        throw new Exception('Invalid authorization format', 401);
    }

    $token = trim($matches[1]);

    if (empty($clientId)) {
        throw new Exception('Missing client ID', 401);
    }

    if (empty($appId)) {
        throw new Exception('Missing application ID', 401);
    }

    if (empty($token)) {
        throw new Exception('Empty token provided', 401);
    }

    if (!isset($config['api']['token'][$clientId])) {
        throw new Exception('Invalid client ID', 403);
    }

    if (!isset($config['api']['token'][$clientId][$appId])) {
        throw new Exception('Invalid application ID for this client', 403);
    }

    if ($token !== $config['api']['token'][$clientId][$appId]) {
        throw new Exception('Invalid token for the provided client and application', 403);
    }
}

// 模板渲染函数
function renderTemplate($template, $data = [])
{
    extract($data);
    ob_start();
    include __DIR__ . '/templates/' . $template;
    return ob_get_clean();
}

// CSRF保护函数
function generateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 获取用户IP
 *
 * @return string
 */
function getIp(): string
{
    if (!empty($_SERVER['HTTP_CLIENT_IP']) && filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP)) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $ip) {
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    if (!empty($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
        return $_SERVER['REMOTE_ADDR'];
    }

    return 'unknown';
}

// 操作日志函数
function logAdminAction($action, $details = '')
{
    global $config;

    $logEntry = sprintf(
        "[%s] IP:%s | User:%s | Action:%s | Details:%s | UserAgent:%s\n",
        date('Y-m-d H:i:s'),
        getIp(),
        $_SESSION['admin_username'] ?? 'unknown',
        $action,
        $details,
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    );

    // 使用LOCK_EX防止并发写入问题
    file_put_contents($config['admin']['log_file'], $logEntry, FILE_APPEND | LOCK_EX);
}