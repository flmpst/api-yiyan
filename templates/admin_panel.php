<h1>管理面板</h1>
<hr>
<div class="stats-container">
    <div class="stat-card">
        <h3>总内容数</h3>
        <p><?= $total_quotes ?></p>
    </div>
    <div class="stat-card">
        <h3>文本内容</h3>
        <p><?= $text_quotes ?></p>
    </div>
    <div class="stat-card">
        <h3>图片内容</h3>
        <p><?= $image_quotes ?></p>
    </div>
    <div class="stat-card">
        <h3>隐藏内容</h3>
        <p><?= $hidden_quotes ?></p>
    </div>
</div>
<hr>
<div class="admin-actions">
    <a href="/?docs=true">查看前台HTML</a>
    <a href="/admin/add">添加新内容</a>
    <a href="/admin/list">管理现有内容</a>
    <a href="/admin/logs">查看操作日志</a>
    <a href="/admin/logout">退出登录</a>
</div>