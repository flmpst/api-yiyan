<h1>管理员登录</h1>
<hr>
<form method="post" action="/admin/login">
    <div>
        <label for="username">用户名:</label>
        <input type="text" id="username" name="username" required>
    </div>
    <div>
        <label for="password">密码:</label>
        <input type="password" id="password" name="password" required>
    </div>
    <button type="submit">登录</button>
</form>

<?php if (isset($error)): ?>
    <div class="error">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>