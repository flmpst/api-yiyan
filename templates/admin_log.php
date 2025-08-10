<h1>管理员活动日志</h1>
<hr>
<div class="admin-actions">
    <a href="/admin" class="back-btn">返回管理面板</a>
</div>
<hr>
<div class="log-actions">
    <a href="/admin/logs/download" class="btn-primary">下载日志</a>
</div>
<div class="log-container">
    <pre><?= htmlspecialchars(file_get_contents($config['admin']['log_file'])) ?></pre>
</div>