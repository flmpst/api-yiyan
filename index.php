<?php
date_default_timezone_set('Asia/Shanghai');
session_start();

// API配置常量
define('API_VERSION', '1.0.0');
define('DEFAULT_PER_PAGE', 20);
define('MAX_PER_PAGE', 100);

$current_domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$show_docs = isset($_GET['docs']) && $_GET['docs'] === 'true';
$show_all = isset($_GET['all']) && $_GET['all'] === 'true';

// 分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? min(MAX_PER_PAGE, max(1, intval($_GET['per_page']))) : DEFAULT_PER_PAGE;

// 加载配置文件
$config_file = __DIR__ . '/config.php';
if (!file_exists($config_file)) {
    die(json_encode([
        'meta' => [
            'version' => API_VERSION,
            'timestamp' => time(),
            'status' => 'error'
        ],
        'data' => null,
        'message' => '找不到配置文件',
    ]));
}
require_once $config_file;

// 模板渲染函数
function renderTemplate($template, $data = [])
{
    $templatePath = __DIR__ . '/templates/' . $template;
    if (!file_exists($templatePath)) {
        throw new Exception("Template file not found: " . $template, 500);
    }
    extract($data);
    ob_start();
    include $templatePath;
    $content = ob_get_clean();
    foreach ($data as $key => $value) {
        $content = str_replace('{' . $key . '}', $value, $content);
    }
    return $content;
}

// 存储策略接口
interface StorageStrategy
{
    public function getQuotes();
    public function getLastUpdateTime();
    public function getTotalCount();
}

// JSON 存储策略
class JsonStorage implements StorageStrategy
{
    private $data_file;

    public function __construct($config)
    {
        $this->data_file = $config['path'] ?? __DIR__ . '/data/quotes.json';
        $this->initDataFile();
    }

    private function initDataFile()
    {
        if (!file_exists($this->data_file)) {
            $dir = dirname($this->data_file);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($this->data_file, json_encode(['quotes' => []]));
        }
    }

    public function getQuotes()
    {
        $data = json_decode(file_get_contents($this->data_file), true);
        return $data ? $data['quotes'] : [];
    }

    public function getLastUpdateTime()
    {
        $quotes = $this->getQuotes();
        $lastUpdate = 0;
        foreach ($quotes as $quote) {
            if ($quote['add_time'] > $lastUpdate) {
                $lastUpdate = $quote['add_time'];
            }
        }
        return $lastUpdate;
    }

    public function getTotalCount()
    {
        $quotes = $this->getQuotes();
        return count($quotes);
    }
}

// 根据配置创建存储策略实例
function createStorageStrategy($config)
{
    switch ($config['storage']['type']) {
        case 'json':
            return new JsonStorage($config['storage']['config']);
        default:
            throw new Exception("Unsupported storage type: " . $config['storage']['type'], 400);
    }
}

// 标准API响应函数
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
        'data' => $data,
        'message' => $message
    ];

    if (!empty($errors)) {
        $response['errors'] = $errors;
    }

    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 主逻辑
try {
    // 创建存储策略实例
    $storage = createStorageStrategy($config);

    // 获取所有引用内容
    $quotes = $storage->getQuotes();

    // 过滤掉隐藏内容
    $visible_quotes = array_filter($quotes, function ($quote) {
        return empty($quote['is_hidden']);
    });

    // 获取最后更新时间
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
            // HTML响应格式显示所有内容
            header('Content-Type: text/html; charset=utf-8');

            $content_html = '';
            foreach ($quotes_data as $item) {
                $display_content = $item['content_type'] === 'image'
                    ? "<img src='{$item['content']}' alt='Quote image' style='max-width: 100%;'>"
                    : "<p>" . nl2br(htmlspecialchars($item['content'])) . "</p>";

                $content_html .= renderTemplate('quote_item.html', [
                    'display_content' => $display_content,
                    'user_qq' => htmlspecialchars($item['user_name']),
                    'add_time' => date('Y-m-d H:i:s', $item['add_time'])
                ]);
            }

            $content = renderTemplate('all_quotes.html', [
                'content_html' => $content_html,
                'current_page' => $page,
                'total_pages' => $total_pages,
                'per_page' => $per_page
            ]);

            echo renderTemplate('base.html', [
                'title' => 'All Quotes',
                'content' => $content,
                'last_update_time' => date('Y-m-d H:i:s', (int)$lastUpdate)
            ]);
        } else {
            // JSON响应格式返回所有数据
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
            // HTML响应格式
            header('Content-Type: text/html; charset=utf-8');

            $display_content = $quote['content_type'] === 'image'
                ? "<img src='{$quote['content']}' alt='Quote image' style='max-width: 100%;'>"
                : "<p>" . nl2br(htmlspecialchars($quote['content'])) . "</p>";

            $api_example = json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            $content = renderTemplate('single_quote.html', [
                'display_content' => $display_content,
                'user_qq' => htmlspecialchars($quote['user_name']),
                'add_time' => date('Y-m-d H:i:s', (int)$quote['add_time']),
                'api_example' => $api_example
            ]);

            echo renderTemplate('base.html', [
                'title' => 'Random Quote',
                'content' => $content,
                'last_update_time' => date('Y-m-d H:i:s', (int)$lastUpdate)
            ]);
        } else {
            // JSON响应格式
            apiResponse($response_data, '成功检索随机引用');
        }
    }
} catch (Exception $e) {
    $code = method_exists($e, 'getCode') ? $e->getCode() : 500;
    $code = $code >= 100 && $code < 600 ? $code : 500;

    if ($show_docs || $show_all) {
        header('Content-Type: text/html; charset=utf-8');
        http_response_code($code);
        echo renderTemplate('base.html', [
            'title' => 'Error',
            'content' => "<div class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>",
            'last_update_time' => date('Y-m-d H:i:s', time())
        ]);
    } else {
        apiResponse(null, $e->getMessage(), $code, ['details' => $e->getMessage()]);
    }
}
