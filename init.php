<?php
session_start();

define('API_VERSION', '1.0.0');

// 加载配置
$config = require __DIR__ . '/config.php';

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 创建存储策略
function createStorageStrategy($config)
{
    if ($config['storage']['type'] === 'sqlite') {
        require_once __DIR__ . '/class/SqliteStorage.php';
        return new SqliteStorage($config['storage']['config']);
    }
    throw new Exception('Unsupported storage type');
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
    http_response_code($code);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// API认证函数
function validateApiCredentials()
{
    global $config;

    // 检查配置是否存在
    if (!isset($config['api']['token']) || empty($config['api']['token'])) {
        throw new Exception('API token configuration is missing', 500);
    }

    // 检查必要的参数
    // 方式1: 从请求头获取
    $clientId = $_SERVER['HTTP_X_CLIENT_ID'] ?? null;
    $appId = $_SERVER['HTTP_X_APP_ID'] ?? null;

    // 检查Authorization头是否存在
    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        throw new Exception('Missing authorization token', 401);
    }

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

    // 验证Bearer令牌格式
    if (!preg_match('/^Bearer\s+([a-zA-Z0-9\-]+)$/', $authHeader, $matches)) {
        throw new Exception('Invalid authorization format', 401);
    }

    $token = trim($matches[1]);

    // 验证必要参数是否存在
    if (empty($clientId)) {
        throw new Exception('Missing client ID', 401);
    }

    if (empty($appId)) {
        throw new Exception('Missing application ID', 401);
    }

    if (empty($token)) {
        throw new Exception('Empty token provided', 401);
    }

    // 验证客户端ID是否存在于配置中
    if (!isset($config['api']['token'][$clientId])) {
        throw new Exception('Invalid client ID', 403);
    }

    // 验证应用ID是否存在于该客户端下
    if (!isset($config['api']['token'][$clientId][$appId])) {
        throw new Exception('Invalid application ID for this client', 403);
    }

    // 验证令牌是否匹配
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

// 获取当前域名
$current_domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";

// 解析请求参数
$show_all = isset($_GET['all']);
$show_docs = isset($_GET['docs']);
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;

// 管理员路由处理
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// 管理员相关路由
if (strpos($request_uri, '/admin') === 0) {
    // 管理员登录检查
    $is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    $storage = createStorageStrategy($config);

    // 登录页面
    if ($request_uri === '/admin/login') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            if ($username === $config['admin']['name'] && $password === $config['admin']['password']) {
                $_SESSION['admin_logged_in'] = true;
                header('Location: /admin');
                exit;
            } else {
                $error = '用户名或密码错误';
            }
        }

        echo renderTemplate('base.php', [
            'title' => '管理员登录',
            'content' => renderTemplate('admin_login.php', ['error' => $error ?? null]),
            'last_update_time' => date('Y-m-d H:i:s', time())
        ]);
        exit;
    }

    // 退出登录
    if ($request_uri === '/admin/logout') {
        session_destroy();
        header('Location: /admin/login');
        exit;
    }

    // 检查是否已登录
    if (!$is_admin && $request_uri !== '/admin/login') {
        header('Location: /admin/login');
        exit;
    }

    // 管理面板首页
    if ($request_uri === '/admin') {
        echo renderTemplate('base.php', [
            'title' => '管理面板',
            'content' => renderTemplate('admin_panel.php'),
            'last_update_time' => date('Y-m-d H:i:s', time())
        ]);
        exit;
    }

    // 添加内容页面
    if ($request_uri === '/admin/add') {
        echo renderTemplate('base.php', [
            'title' => '添加内容',
            'content' => renderTemplate('admin_add.php'),
            'last_update_time' => date('Y-m-d H:i:s', time())
        ]);
        exit;
    }

    // 保存内容
    if ($request_uri === '/admin/save') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'content' => $_POST['content'],
                'content_type' => $_POST['content_type'] ?? 'text',
                'user_name' => $_POST['user_name'],
                'quote_source' => $_POST['quote_source'] ?? '',
                'is_hidden' => isset($_POST['is_hidden']) ? 1 : 0,
                'add_time' => time()
            ];

            if ($storage instanceof SqliteStorage) {
                $stmt = $storage->getDb()->prepare('INSERT INTO main (content, content_type, user_name, quote_source, is_hidden, add_time) 
                                     VALUES (:content, :content_type, :user_name, :quote_source, :is_hidden, :add_time)');
                $stmt->execute($data);
            }

            header('Location: /admin/list');
            exit;
        }
    }

    // 内容列表
    if ($request_uri === '/admin/list') {
        $quotes = $storage->getQuotes();

        echo renderTemplate('base.php', [
            'title' => '内容列表',
            'content' => renderTemplate('admin_list.php', ['quotes' => $quotes, 'current_domain' => $current_domain]),
            'last_update_time' => date('Y-m-d H:i:s', time())
        ]);
        exit;
    }

    // 删除内容
    if (preg_match('#^/admin/delete/(\d+)$#', $request_uri, $matches)) {
        $id = $matches[1];
        if ($storage instanceof SqliteStorage) {
            $stmt = $storage->getDb()->prepare('DELETE FROM main WHERE id = ?');
            $stmt->execute([$id]);
        }
        header('Location: /admin/list');
        exit;
    }

    // 编辑内容页面
    if (preg_match('#^/admin/edit/(\d+)$#', $request_uri, $matches)) {
        $id = $matches[1];
        $quote = $storage->getQuoteById($id);

        if (!$quote) {
            header('Location: /admin/list');
            exit;
        }

        echo renderTemplate('base.php', [
            'title' => '编辑内容',
            'content' => renderTemplate('admin_edit.php', ['quote' => $quote]),
            'last_update_time' => date('Y-m-d H:i:s', time())
        ]);
        exit;
    }

    // 更新内容
    if (preg_match('#^/admin/update/(\d+)$#', $request_uri, $matches)) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $matches[1];
            $data = [
                'id' => $id,
                'content' => $_POST['content'],
                'content_type' => $_POST['content_type'] ?? 'text',
                'user_name' => $_POST['user_name'],
                'quote_source' => $_POST['quote_source'] ?? '',
                'is_hidden' => isset($_POST['is_hidden']) ? 1 : 0
            ];

            if ($storage instanceof SqliteStorage) {
                $stmt = $storage->getDb()->prepare('UPDATE main SET 
                content = :content, 
                content_type = :content_type, 
                user_name = :user_name, 
                quote_source = :quote_source, 
                is_hidden = :is_hidden 
                WHERE id = :id');
                $stmt->execute($data);
            }

            header('Location: /admin/list');
            exit;
        }
    }
}