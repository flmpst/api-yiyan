<?php
session_start();

define('API_VERSION', '1.0.0');

// 加载配置
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/class/SqliteStorage.php';
require_once __DIR__ . '/class/WebSecurity.php';
require_once __DIR__ . '/function.php';

initWebSecurity();

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 获取当前域名
$current_domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";

// 解析请求参数
$show_all = isset($_GET['all']);
$show_docs = isset($_GET['docs']);
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;

// 管理员路由处理
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
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
                $_SESSION['admin_username'] = $username;
                logAdminAction('用户登录');
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
        logAdminAction('用户退出');
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
        $quotes = $storage->getQuotes();
        $total_quotes = count($quotes);
        $text_quotes = count(array_filter($quotes, function ($quote) {
            return $quote['content_type'] === 'text';
        }));
        $image_quotes = count(array_filter($quotes, function ($quote) {
            return $quote['content_type'] === 'image';
        }));
        $hidden_quotes = count(array_filter($quotes, function ($quote) {
            return $quote['is_hidden'];
        }));

        echo renderTemplate('base.php', [
            'title' => '管理面板',
            'content' => renderTemplate('admin_panel.php', [
                'total_quotes' => $total_quotes,
                'text_quotes' => $text_quotes,
                'image_quotes' => $image_quotes,
                'hidden_quotes' => $hidden_quotes
            ]),
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
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                die('CSRF token validation failed');
            }

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
                $id = $storage->getDb()->lastInsertId();
                logAdminAction('添加内容', 'ID: ' . $id);
            }

            header('Location: /admin/list');
            exit;
        }
    }

    // 内容列表
    if ($request_uri === '/admin/list') {
        $search = $_GET['search'] ?? '';
        $content_type = $_GET['content_type'] ?? '';
        $visibility = $_GET['visibility'] ?? '';

        $quotes = $storage->getQuotes();

        // 应用筛选
        if (!empty($search)) {
            $quotes = array_filter($quotes, function ($quote) use ($search) {
                return stripos($quote['content'], $search) !== false ||
                    stripos($quote['user_name'], $search) !== false;
            });
        }

        if (!empty($content_type)) {
            $quotes = array_filter($quotes, function ($quote) use ($content_type) {
                return $quote['content_type'] === $content_type;
            });
        }

        if (!empty($visibility)) {
            $quotes = array_filter($quotes, function ($quote) use ($visibility) {
                return ($visibility === 'visible' && !$quote['is_hidden']) ||
                    ($visibility === 'hidden' && $quote['is_hidden']);
            });
        }

        // 分页
        $total = count($quotes);
        $total_pages = ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;
        $paged_quotes = array_slice($quotes, $offset, $per_page);

        echo renderTemplate('base.php', [
            'title' => '内容列表',
            'content' => renderTemplate('admin_list.php', [
                'quotes' => $paged_quotes,
                'current_domain' => $current_domain,
                'total_pages' => $total_pages,
                'page' => $page
            ]),
            'last_update_time' => date('Y-m-d H:i:s', time())
        ]);
        exit;
    }

    // 批量操作
    if ($request_uri === '/admin/batch_action') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                die('CSRF token validation failed');
            }

            $ids = $_POST['ids'] ?? [];
            $action = $_POST['batch_action'] ?? '';

            if (!empty($ids) && $action) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));

                switch ($action) {
                    case 'show':
                        $stmt = $storage->getDb()->prepare("UPDATE main SET is_hidden = 0 WHERE id IN ($placeholders)");
                        $stmt->execute($ids);
                        logAdminAction('批量显示内容', 'ID: ' . implode(',', $ids));
                        break;
                    case 'hide':
                        $stmt = $storage->getDb()->prepare("UPDATE main SET is_hidden = 1 WHERE id IN ($placeholders)");
                        $stmt->execute($ids);
                        logAdminAction('批量隐藏内容', 'ID: ' . implode(',', $ids));
                        break;
                    case 'delete':
                        $stmt = $storage->getDb()->prepare("DELETE FROM main WHERE id IN ($placeholders)");
                        $stmt->execute($ids);
                        logAdminAction('批量删除内容', 'ID: ' . implode(',', $ids));
                        break;
                }
            }

            header('Location: /admin/list');
            exit;
        }
    }

    // 删除内容
    if (preg_match('#^/admin/delete/(\d+)$#', $request_uri, $matches)) {
        $id = $matches[1];
        if ($storage instanceof SqliteStorage) {
            $stmt = $storage->getDb()->prepare('DELETE FROM main WHERE id = ?');
            $stmt->execute([$id]);
            logAdminAction('删除内容', 'ID: ' . $id);
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
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                die('CSRF token validation failed');
            }

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
                logAdminAction('更新内容', 'ID: ' . $id);
            }

            header('Location: /admin/list');
            exit;
        }
    }

    // 日志查看路由
    if ($request_uri === '/admin/logs') {
        echo renderTemplate('base.php', [
            'title' => '管理员日志',
            'content' => renderTemplate('admin_log.php', [
                'config' => $config,
            ]),
            'last_update_time' => date('Y-m-d H:i:s', time())
        ]);
        exit;
    }

    // 日志下载路由
    if ($request_uri === '/admin/logs/download') {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="admin_actions_' . date('Ymd_His') . '.log"');
        readfile($config['admin']['log_file']);
        exit;
    }
}
