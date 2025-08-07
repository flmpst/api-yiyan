<?php
require_once __DIR__ . '/init.php';

try {
    $storage = createStorageStrategy($config);

    // 处理API数据提交
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$show_docs) {
        validateApiCredentials();

        // 如果是文件上传，使用 $_POST 和 $_FILES
        if (!empty($_FILES['image_file'])) {
            $input = $_POST;
            $input['content_type'] = 'image';
        } else {
            // 否则处理 JSON 输入
            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON input', 400);
            }
        }

        // 验证必填字段
        $required = ['content', 'user_name'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                throw new Exception("Missing required field: $field", 400);
            }
        }

        // 准备数据 - API提交的内容默认隐藏
        $data = [
            'content' => $input['content'],
            'content_type' => $input['content_type'] ?? 'text',
            'user_name' => $input['user_name'],
            'quote_source' => $input['quote_source'] ?? 'API提交',
            'is_hidden' => 1, // API提交的内容默认隐藏
            'add_time' => time()
        ];

        // 处理图片内容
        if ($data['content_type'] === 'image') {
            if (!isset($_FILES['image_file'])) {
                throw new Exception('Missing image file for image type', 400);
            }

            $file = $_FILES['image_file'];

            // 检查上传错误
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload error: ' . $file['error'], 400);
            }

            // 确保图片目录存在
            if (!is_dir($config['image_storage']['path'])) {
                mkdir($config['image_storage']['path'], 0755, true);
            }

            // 获取图片信息
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);

            // 检查图片类型
            if (!isset($config['image_storage']['allowed_types'][$mime])) {
                throw new Exception('Unsupported image type: ' . $mime, 400);
            }

            // 检查图片大小
            if ($file['size'] > $config['image_storage']['max_size']) {
                throw new Exception('Image size exceeds maximum allowed size', 400);
            }

            // 生成文件名
            $imageData = file_get_contents($file['tmp_name']);
            $imageHash = md5($imageData);
            $ext = $config['image_storage']['allowed_types'][$mime];
            $imagePath = $config['image_storage']['path'] . '/' . $imageHash . $ext;

            // 保存图片
            if (!move_uploaded_file($file['tmp_name'], $imagePath)) {
                throw new Exception('Failed to save image', 500);
            }

            $data['content'] = $imageHash . $ext;
        }

        // 保存到数据库
        if ($storage instanceof SqliteStorage) {
            try {
                $stmt = $storage->getDb()->prepare('INSERT INTO main (content, content_type, user_name, quote_source, is_hidden, add_time) 
                                     VALUES (:content, :content_type, :user_name, :quote_source, :is_hidden, :add_time)');
                $stmt->execute($data);

                $id = $storage->getDb()->lastInsertId();
                $data['id'] = $id;

                // 返回完整的URL路径
                if ($data['content_type'] === 'image') {
                    $data['content_url'] = $current_domain . '/data/image/' . $data['content'];
                }

                apiResponse($data, '数据提交成功', 201);
            } catch (PDOException $e) {
                throw new Exception('Database error: ' . $e->getMessage(), 500);
            }
        }
    }

    // 获取所有引用内容
    $quotes = $storage->getQuotes();
    $visible_quotes = array_filter($quotes, function ($quote) {
        return empty($quote['is_hidden']);
    });

    $lastUpdate = $storage->getLastUpdateTime();

    if ($show_all) {
        if (empty($visible_quotes)) {
            throw new Exception("No quotes available", 404);
        }

        // 分页处理
        $total = count($visible_quotes);
        $total_pages = ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;
        $paged_quotes = array_slice($visible_quotes, $offset, $per_page);

        // 处理数据
        $quotes_data = [];
        foreach ($paged_quotes as $quote) {
            $content = $quote['content'];
            if ($quote["content_type"] === 'image') {
                $content = $current_domain . '/data/image/' . $content;
            }

            $quotes_data[] = [
                'id' => $quote['id'],
                'content' => $content,
                'description' => $quote['quote_source'],
                'content_type' => $quote['content_type'],
                'add_time' => (int)$quote['add_time'],
                'user_name' => $quote['user_name']
            ];
        }

        if ($show_docs) {
            // HTML响应
            $content_html = '';
            foreach ($quotes_data as $item) {
                $display_content = $item['content_type'] === 'image'
                    ? "<img src='{$item['content']}' alt='Quote image' style='max-width: 100%;'>"
                    : "<p>" . nl2br(htmlspecialchars($item['content'])) . "</p>";

                $content_html .= renderTemplate('quote_item.php', [
                    'display_content' => $display_content,
                    'user_qq' => htmlspecialchars($item['user_name']),
                    'add_time' => date('Y-m-d H:i:s', $item['add_time'])
                ]);
            }

            $content = renderTemplate('all_quotes.php', [
                'content_html' => $content_html,
                'current_page' => $page,
                'total_pages' => $total_pages,
                'per_page' => $per_page
            ]);

            echo renderTemplate('base.php', [
                'title' => 'All Quotes',
                'content' => $content,
                'last_update_time' => date('Y-m-d H:i:s', (int)$lastUpdate)
            ]);
        } else {
            // JSON响应
            $pagination = [
                'total' => $total,
                'per_page' => $per_page,
                'current_page' => $page,
                'total_pages' => $total_pages,
                'has_more' => $page < $total_pages
            ];

            apiResponse([
                'quotes' => $quotes_data,
                'pagination' => $pagination,
                'last_update' => $lastUpdate
            ], '成功检索');
        }
    } else {
        // 随机一条非隐藏内容
        if (empty($visible_quotes)) {
            throw new Exception("No quotes available", 404);
        }

        $quote = $visible_quotes[array_rand($visible_quotes)];

        // 处理图片路径
        $content = $quote['content'];
        if ($quote["content_type"] === 'image') {
            $quote['content'] = $current_domain . '/data/image/' . $content;
        }

        // 格式化数据
        $response_data = [
            'id' => (int)$quote['id'],
            'content' => $quote['content'],
            'description' => $quote['quote_source'],
            'content_type' => $quote['content_type'],
            'add_time' => (int)$quote['add_time'],
            'user_name' => $quote['user_name']
        ];

        if ($show_docs) {
            // HTML响应
            $display_content = $quote['content_type'] === 'image'
                ? "<img src='{$quote['content']}' alt='Quote image' style='max-width: 100%;'>"
                : "<p>" . nl2br(htmlspecialchars($quote['content'])) . "</p>";

            $api_example = json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            $content = renderTemplate('single_quote.php', [
                'display_content' => $display_content,
                'user_qq' => htmlspecialchars($quote['user_name']),
                'add_time' => date('Y-m-d H:i:s', (int)$quote['add_time']),
                'api_example' => $api_example
            ]);

            echo renderTemplate('base.php', [
                'title' => 'Random Quote',
                'content' => $content,
                'last_update_time' => date('Y-m-d H:i:s', (int)$lastUpdate)
            ]);
        } else {
            // JSON响应
            apiResponse($response_data, '成功检索随机引用');
        }
    }
} catch (Throwable $e) {
    $code = method_exists($e, 'getCode') ? $e->getCode() : 500;
    $code = $code >= 100 && $code < 600 ? $code : 500;

    if ($show_docs || $show_all) {
        header('Content-Type: text/html; charset=utf-8');
        http_response_code($code);
        echo renderTemplate('base.php', [
            'title' => 'Error',
            'content' => "<div class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>",
            'last_update_time' => date('Y-m-d H:i:s', time())
        ]);
    } else {
        apiResponse(null, $e->getMessage(), $code, ['details' => $e->getMessage()]);
    }
}
