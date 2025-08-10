<h1>管理面板</h1>
<hr>
<div class="admin-actions">
    <a href="/?docs=true">查看前台HTML</a>
    <a href="/admin/add">添加新内容</a>
    <a href="/admin/list">管理现有内容</a>
    <a href="/admin/logs">查看操作日志</a>
</div>
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
<div id="chart-container" style="height: 300px; margin: 20px 0;"></div>

<script>
    var chart = echarts.init(document.getElementById('chart-container'));
    var option = {
        title: {
            text: '内容分布统计',
            left: 'center'
        },
        tooltip: {
            trigger: 'item'
        },
        legend: {
            orient: 'vertical',
            left: 'left'
        },
        series: [{
            name: '内容分布',
            type: 'pie',
            radius: '50%',
            data: [{
                    value: <?= $text_quotes ?>,
                    name: '文本内容'
                },
                {
                    value: <?= $image_quotes ?>,
                    name: '图片内容'
                },
                {
                    value: <?= $hidden_quotes ?>,
                    name: '隐藏内容'
                }
            ],
            emphasis: {
                itemStyle: {
                    shadowBlur: 10,
                    shadowOffsetX: 0,
                    shadowColor: 'rgba(0, 0, 0, 0.5)'
                }
            }
        }]
    };

    chart.setOption(option);

    // 动态跳转大小
    window.addEventListener('resize', function() {
        chart.resize();
    });
</script>