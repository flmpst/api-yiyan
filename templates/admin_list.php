<h1>内容列表</h1>
<hr>
<form method="get" action="/admin/list" class="search-form">
    <div>
        <input type="text" name="search" placeholder="搜索内容..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
        <select name="content_type">
            <option value="">所有类型</option>
            <option value="text" <?= ($_GET['content_type'] ?? '') === 'text' ? 'selected' : '' ?>>文本</option>
            <option value="image" <?= ($_GET['content_type'] ?? '') === 'image' ? 'selected' : '' ?>>图片</option>
        </select>
        <select name="visibility">
            <option value="">所有状态</option>
            <option value="visible" <?= ($_GET['visibility'] ?? '') === 'visible' ? 'selected' : '' ?>>显示</option>
            <option value="hidden" <?= ($_GET['visibility'] ?? '') === 'hidden' ? 'selected' : '' ?>>隐藏</option>
        </select>
        <button type="submit">搜索</button>
    </div>
</form>

<form method="post" action="/admin/batch_action">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
    <div class="batch-actions">
        <select name="batch_action">
            <option value="">批量操作</option>
            <option value="show">设为显示</option>
            <option value="hide">设为隐藏</option>
            <option value="delete">删除</option>
        </select>
        <button type="submit">应用</button>
    </div>
    <table>
        <thead>
            <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>ID</th>
                <th>内容</th>
                <th>类型</th>
                <th>用户</th>
                <th>状态</th>
                <th>时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($quotes as $quote): ?>
                <tr>
                    <td><input type="checkbox" name="ids[]" value="<?= $quote['id'] ?>"></td>
                    <td><?= $quote['id'] ?></td>
                    <td>
                        <?php if ($quote['content_type'] == 'text'): ?>
                            <?= mb_strlen($quote['content']) > 30 ? mb_substr($quote['content'], 0, 30) . '...' : $quote['content'] ?>
                        <?php else: ?>
                            <img src="<?= $current_domain . '/data/image/' . $quote['content'] ?>" alt="" style="max-height: 50px;">
                        <?php endif; ?>
                    </td>
                    <td><?= $quote['content_type'] ?></td>
                    <td><?= $quote['user_name'] ?></td>
                    <td><?= $quote['is_hidden'] ? '隐藏' : '显示' ?></td>
                    <td><?= date('Y-m-d H:i', $quote['add_time']) ?></td>
                    <td>
                        <a href="/admin/edit/<?= $quote['id'] ?>">编辑</a>
                        <hr>
                        <a href="/admin/delete/<?= $quote['id'] ?>" onclick="return confirm('确定删除吗？')">删除</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</form>

<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="?page=1<?= !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?><?= !empty($_GET['content_type']) ? '&content_type=' . urlencode($_GET['content_type']) : '' ?><?= !empty($_GET['visibility']) ? '&visibility=' . urlencode($_GET['visibility']) : '' ?>">首页</a>
        <a href="?page=<?= $page - 1 ?><?= !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?><?= !empty($_GET['content_type']) ? '&content_type=' . urlencode($_GET['content_type']) : '' ?><?= !empty($_GET['visibility']) ? '&visibility=' . urlencode($_GET['visibility']) : '' ?>">上一页</a>
    <?php endif; ?>

    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
        <a href="?page=<?= $i ?><?= !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?><?= !empty($_GET['content_type']) ? '&content_type=' . urlencode($_GET['content_type']) : '' ?><?= !empty($_GET['visibility']) ? '&visibility=' . urlencode($_GET['visibility']) : '' ?>" <?= $i == $page ? 'class="active"' : '' ?>><?= $i ?></a>
    <?php endfor; ?>

    <?php if ($page < $total_pages): ?>
        <a href="?page=<?= $page + 1 ?><?= !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?><?= !empty($_GET['content_type']) ? '&content_type=' . urlencode($_GET['content_type']) : '' ?><?= !empty($_GET['visibility']) ? '&visibility=' . urlencode($_GET['visibility']) : '' ?>">下一页</a>
        <a href="?page=<?= $total_pages ?><?= !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?><?= !empty($_GET['content_type']) ? '&content_type=' . urlencode($_GET['content_type']) : '' ?><?= !empty($_GET['visibility']) ? '&visibility=' . urlencode($_GET['visibility']) : '' ?>">末页</a>
    <?php endif; ?>
</div>
<script>
    // 全选/取消全选功能
    document.getElementById('select-all').addEventListener('change', function(e) {
        const checkboxes = document.querySelectorAll('input[name="ids[]"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = e.target.checked;
        });
    });

    // 批量操作确认
    document.querySelector('form[action="/admin/batch_action"]').addEventListener('submit', function(e) {
        const action = this.elements['batch_action'].value;
        const checked = document.querySelectorAll('input[name="ids[]"]:checked').length;

        if (!action) {
            e.preventDefault();
            alert('请选择批量操作类型');
            return;
        }

        if (checked === 0) {
            e.preventDefault();
            alert('请至少选择一项内容');
            return;
        }

        if (action === 'delete' && !confirm(`确定要删除选中的 ${checked} 项内容吗？`)) {
            e.preventDefault();
        }
    });
</script>