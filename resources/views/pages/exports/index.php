<?php
$pageTitle = '导出记录';
$currentPage = 'exports';
ob_start();
?>

<div class="card">
    <div class="filter-bar">
        <div class="btn-group">
            <select class="form-control form-select" id="filter-status" onchange="loadExports()">
                <option value="">全部状态</option>
                <option value="0">生成中</option>
                <option value="1">已完成</option>
                <option value="2">已过期</option>
                <option value="3">失败</option>
            </select>
        </div>
    </div>

    <div id="export-list">
        <div class="loading"><div class="spinner"></div></div>
    </div>

    <div id="pagination"></div>
</div>

<script>
let currentPage = 1;
const perPage = 20;

const statusMap = {
    0: { label: '生成中', class: 'badge-info' },
    1: { label: '已完成', class: 'badge-success' },
    2: { label: '已过期', class: 'badge-warning' },
    3: { label: '失败', class: 'badge-danger' },
};

const typeLabels = {
    'assets': '资产导出',
    'sidesites': '旁站导出',
    'tasks': '任务导出',
    'failures': '失败日志导出',
};

async function loadExports(page = 1) {
    currentPage = page;

    const params = {
        page,
        per_page: perPage,
        status: document.getElementById('filter-status').value,
    };

    try {
        const data = await api.get('/api/exports', params);
        renderExportList(data.items);
        document.getElementById('pagination').innerHTML = renderPagination(data.pagination, 'loadExports');
    } catch (error) {
        toast.error('加载导出记录失败: ' + error.message);
    }
}

function renderExportList(exports) {
    if (!exports || exports.length === 0) {
        document.getElementById('export-list').innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">&#8681;</div>
                <div class="empty-state-title">暂无导出记录</div>
                <p class="text-muted">在项目详情页面可以导出数据</p>
            </div>
        `;
        return;
    }

    const html = `
        <table class="table">
            <thead>
                <tr>
                    <th>导出类型</th>
                    <th>导出范围</th>
                    <th>记录数</th>
                    <th>文件格式</th>
                    <th>文件大小</th>
                    <th>状态</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                ${exports.map(e => `
                    <tr>
                        <td>${typeLabels[e.export_type] || e.export_type}</td>
                        <td>${e.export_scope || '-'}</td>
                        <td>${formatNumber(e.record_count)}</td>
                        <td>${e.file_format.toUpperCase()}</td>
                        <td>${e.file_size ? formatFileSize(e.file_size) : '-'}</td>
                        <td>${renderStatusBadge(e.status, statusMap)}</td>
                        <td>${formatDate(e.created_at)}</td>
                        <td>
                            <div class="table-actions">
                                ${e.status == 1 ? `
                                    <a href="/api/exports/${e.id}/download" class="btn btn-sm btn-primary" target="_blank">下载</a>
                                ` : ''}
                                <button class="btn btn-sm btn-danger" onclick="deleteExport(${e.id})">删除</button>
                            </div>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    document.getElementById('export-list').innerHTML = html;
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function deleteExport(id) {
    modal.confirm('确定要删除这个导出记录吗？', async () => {
        try {
            await api.delete('/api/exports/' + id);
            toast.success('导出记录已删除');
            loadExports(currentPage);
        } catch (error) {
            toast.error(error.message);
        }
    });
}

// 初始加载
document.addEventListener('DOMContentLoaded', function() {
    loadExports();
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/main.php';
?>
