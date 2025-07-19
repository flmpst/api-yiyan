<?php
date_default_timezone_set('Asia/Shanghai');
session_start();

$current_domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$show_docs = isset($_GET['docs']) && $_GET['docs'] === 'true';
$show_all = isset($_GET['all']) && $_GET['all'] === 'true';

// 加载配置文件
$config_file = __DIR__ . '/config.php';
if (!file_exists($config_file)) {
    die("配置文件不存在，请创建 config.php 文件");
}
require_once $config_file;

// 模板渲染函数
function renderTemplate($template, $data = [])
{
    $templatePath = __DIR__ . '/templates/' . $template;
    if (!file_exists($templatePath)) {
        throw new Exception("Template file not found: " . $template);
    }
    // 提取变量到当前符号表
    extract($data);
    // 开始输出缓冲
    ob_start();
    // 包含模板文件
    include $templatePath;
    // 获取缓冲内容并清除缓冲区
    $content = ob_get_clean();
    // 替换剩余的变量（如果有）
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
}

// 根据配置创建存储策略实例
function createStorageStrategy($config)
{
    switch ($config['storage']['type']) {
        case 'json':
            return new JsonStorage($config['storage']['config']);
        default:
            throw new Exception("不支持的存储类型: " . $config['storage']['type']);
    }
}

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
            throw new Exception("没有可用的精选内容");
        }

        // 处理所有数据
        $all_data = [];
        foreach ($visible_quotes as $quote) {
            $content = $quote['content'];
            if ($quote["content_type"] === 'image') {
                $content = $current_domain . '/data/image/' . $content;
            }

            $all_data[] = [
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
            foreach ($all_data as $item) {
                $display_content = $item['content_type'] === 'image'
                    ? "<img src='{$item['content']}' alt='精选图片' style='max-width: 100%;'>"
                    : "<p>" . nl2br(htmlspecialchars($item['content'])) . "</p>";

                $content_html .= renderTemplate('quote_item.html', [
                    'display_content' => $display_content,
                    'user_qq' => htmlspecialchars($item['user_name']),
                    'add_time' => date('Y-m-d H:i:s', $item['add_time'])
                ]);
            }

            $content = renderTemplate('all_quotes.html', [
                'content_html' => $content_html
            ]);

            echo renderTemplate('base.html', [
                'title' => '精选内容',
                'content' => $content,
                'last_update_time' => date('Y-m-d H:i:s', (int)$lastUpdate)
            ]);
        } else {
            // JSON响应格式返回所有数据
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($all_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    } else {
        // 随机一条非隐藏内容
        if (empty($visible_quotes)) {
            throw new Exception("没有可用的精选内容");
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
        ];

        if ($show_docs) {
            // HTML响应格式
            header('Content-Type: text/html; charset=utf-8');

            $display_content = $quote['content_type'] === 'image'
                ? "<img src='{$quote['content']}' alt='精选图片' style='max-width: 100%;'>"
                : "<p>" . nl2br(htmlspecialchars($quote['content'])) . "</p>";

            $api_example = json_encode([
                'id' => $quote['id'],
                'content' => $quote['content'],
                'user_qq' => $quote['user_name'],
                'content_type' => $quote['content_type'],
                'add_time' => $quote['add_time']
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            $content = renderTemplate('single_quote.html', [
                'display_content' => $display_content,
                'user_qq' => htmlspecialchars($quote['user_name']),
                'add_time' => date('Y-m-d H:i:s', (int)$quote['add_time']),
                'api_example' => $api_example
            ]);

            echo renderTemplate('base.html', [
                'title' => '随机精选内容',
                'content' => $content,
                'last_update_time' => date('Y-m-d H:i:s', (int)$lastUpdate)
            ]);
        } else {
            // JSON响应格式
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($response_data, JSON_UNESCAPED_UNICODE);
        }
    }
} catch (Exception $e) {
    if ($show_docs || $show_all) {
        header('Content-Type: text/html; charset=utf-8');
        echo renderTemplate('base.html', [
            'title' => '错误',
            'content' => "<div class='error'>发生错误: " . htmlspecialchars($e->getMessage()) . "</div>",
            'last_update_time' => date('Y-m-d H:i:s', time())
        ]);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => true,
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}
