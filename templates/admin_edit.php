<h1>编辑内容</h1>
<hr>
<form method="post" action="/admin/update/<?= $quote['id'] ?>" class="form-container">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
    <div>
        <label for="content">内容:</label>
        <textarea id="content" name="content" rows="5" required><?= htmlspecialchars($quote['content']) ?></textarea>
        <?php if ($quote['content_type'] === 'image'): ?>
            <div class="image-preview">
                <img src="<?= $current_domain . '/data/image/' . $quote['content'] ?>" alt="当前图片" style="max-width: 300px; margin-top: 10px;">
            </div>
        <?php endif; ?>
    </div>
    <div>
        <label for="content_type">内容类型:</label>
        <select id="content_type" name="content_type">
            <option value="text" <?= $quote['content_type'] === 'text' ? 'selected' : '' ?>>文本</option>
            <option value="image" <?= $quote['content_type'] === 'image' ? 'selected' : '' ?>>图片</option>
        </select>
    </div>
    <div>
        <label for="user_name">用户名:</label>
        <input type="text" id="user_name" name="user_name" value="<?= htmlspecialchars($quote['user_name']) ?>" required>
    </div>
    <div>
        <label for="quote_source">来源描述:</label>
        <input type="text" id="quote_source" name="quote_source" value="<?= htmlspecialchars($quote['quote_source'] ?? '') ?>">
    </div>
    <div>
        <label for="is_hidden">是否隐藏:</label>
        <input type="checkbox" id="is_hidden" name="is_hidden" value="1" <?= $quote['is_hidden'] ? 'checked' : '' ?>>
    </div>
    <button type="submit">保存</button>
    <a href="/admin/list" class="blue-btn">返回列表</a>
</form>