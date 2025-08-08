<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= htmlspecialchars($title ?? '一言') ?>
    </title>
    <link rel="stylesheet" href="/style.css?v=0.2.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/nprogress/0.2.0/nprogress.css" integrity="sha512-DanfxWBasQtq+RtkNAEDTdX4Q6BPCJQ/kexi/RftcP0BcA4NIJPSi7i31Vl+Yl5OCfgZkdJmCqz+byTOIIRboQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="/StaticResources/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/nprogress/0.2.0/nprogress.min.js" integrity="sha512-bUg5gaqBVaXIJNuebamJ6uex//mjxPk8kljQTdM1SwkNrQD7pjS+PerntUSD+QRWPNJ0tq54/x4zRV8bLrLhZg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.pjax/2.0.1/jquery.pjax.min.js" integrity="sha512-7G7ueVi8m7Ldo2APeWMCoGjs4EjXDhJ20DrPglDQqy8fnxsFQZeJNtuQlTT0xoBQJzWRFp4+ikyMdzDOcW36kQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        NProgress.configure({
            minimum: 0.08,
            showSpinner: true,
        })
        // 监听页面加载事件
        window.addEventListener('load', () => {
            NProgress.done();
            mdui.mutation();
        });
        // 监听资源加载事件
        document.addEventListener('readystatechange', () => {
            if (document.readyState === 'interactive') {
                NProgress.start();
                mdui.mutation();
            } else if (document.readyState === 'complete') {
                NProgress.done();
                mdui.mutation();
            }
        });
    </script>
</head>

<body>
    <div style="text-align: right; margin-bottom: 20px;">
        <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']): ?>
            <a href="/admin">进入管理面板</a>
        <?php endif ?>
    </div>
    <main>
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
    </main>
    <hr>
    <footer>
        <p>数据最后更新时间：<?= $last_update_time ?></p>
        &copy; 花枫工作室 2025 | 开源地址：<a href="https://github.com/flmpst/api-yiyan">https://github.com/flmpst/api-yiyan/</a>
    </footer>
    <script>
        // Pjax 初始化以及相关配置
        $(document).pjax('a:not(a[target="_blank"],a[no-pjax])', {
            container: 'main',
            fragment: 'main',
            timeout: 20000
        });

        // Pjax 请求发送时显示进度条
        $(document).on('pjax:send', function() {
            NProgress.start();
        });

        // Pjax 请求结束时隐藏进度条并重新绑定表单事件
        $(document).on('pjax:end', function() {
            NProgress.done();
            mdui.mutation();
        });
    </script>
</body>

</html>