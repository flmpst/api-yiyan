<h1>内容列表</h1>
<hr>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>内容</th>
            <th>类型</th>
            <th>用户</th>
            <th>时间</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($quotes as $quote): ?>
            <tr>
                <td>
                    <?= $quote['id'] ?>
                </td>
                <td>
                    <?php if ($quote['content_type'] == 'text'): ?>
                        <?= mb_strlen($quote['content']) > 30 ? mb_substr($quote['content'], 0, 30) . '...' : $quote['content'] ?>
                    <?php else: ?>
                        <img src="<?= $current_domain . '/data/image/' . $quote['content'] ?>" alt="">
                    <?php endif; ?>
                </td>
                <td>
                    <?= $quote['content_type'] ?>
                </td>
                <td>
                    <?= $quote['user_name'] ?>
                </td>
                <td>
                    <?= date('Y-m-d H:i', $quote['add_time']) ?>
                </td>
                <td>
                    <a href="/admin/edit/<?= $quote['id'] ?>">编辑</a>
                    <hr>
                    <a href="/admin/delete/<?= $quote['id'] ?>" onclick="return confirm('确定删除吗？')">删除</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>