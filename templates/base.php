<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= htmlspecialchars($title ?? '一言') ?>
    </title>
    <link rel="stylesheet" href="/style.css">
</head>

<body>
    <div style="text-align: right; margin-bottom: 20px;">
        <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']): ?>
            <a href="/admin">进入管理面板</a>
        <?php endif ?>
    </div>
    <?php
    // 主内容区域
    if (isset($content)) {
        if (is_callable($content)) {
            $content();
        } else {
            echo $content;
        }
    }
    ?>
    <hr>
    <footer>
        <p>数据最后更新时间：<?= $last_update_time ?></p>
        &copy; 花枫工作室 2025
    </footer>
</body>

</html>