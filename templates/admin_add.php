<h1>添加新内容</h1>
<hr>
<form method="post" action="/admin/save" class="form-container">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
    <div>
        <label for="content">内容:</label>
        <textarea id="content" name="content" rows="5" required></textarea>
    </div>
    <div>
        <label for="content_type">内容类型:</label>
        <select id="content_type" name="content_type">
            <option value="text">文本</option>
            <option value="image">图片</option>
        </select>
    </div>
    <div>
        <label for="user_name">用户名:</label>
        <input type="text" id="user_name" name="user_name" required>
    </div>
    <div>
        <label for="quote_source">来源描述:</label>
        <input type="text" id="quote_source" name="quote_source">
    </div>
    <div>
        <label for="is_hidden">是否隐藏:</label>
        <input type="checkbox" id="is_hidden" name="is_hidden" value="1">
    </div>
    <button type="submit">保存</button>
    <a href="/admin">返回</a>
</form>