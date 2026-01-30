<?php
$pageTitle = '失败日志';
$currentPage = 'failures';
ob_start();
?>

<div class="card">
    <div class="filter-bar">
        <div class="btn-group">
            <select class="form-control form-select" id="filter-type" onchange="loadFailures()">
                <option value="">全部类型</option>
                <option value="dns_error">DNS错误</option>
                <option value="timeout">超时</option>
                <option value="api_limit">API限流</option>
                <option value="no_response">无响应</option>
                <option value="other">其他</option>
            </select>
            <select class="form-control form-select" id="filter-ignored" onchange="loadFailures()">
                <option value="0">未忽略</option>
                <option value="1">已忽略</option>
                <option value="">全部</option>
            </select>
        </div>
        <div class="btn-group" style="margin-left: auto;">
            <button class="btn btn-outline" onclick="batchRetry()">批量重试</button>
            <button class="btn btn-outline" onclick="batchIgnore()">批量忽略</button>
            <button class="btn btn-danger" onclick="clearAll()">清空日志</button>
        </div>
    </div>

    <div id="failure-list">
        <div class="loading"><div class="spinner"></div></div>
    </div>

    <div id="pagination"></div>
</div>

<script>
let currentPage = 1;
const perPage = 20;
let selectedIds = [];

const typeLabels = {
    'dns_error': 'DNS错误',
    'timeout': '超时',
    'api_limit': 'API限流',
    'no_response': '无响应',
    'other': '其他',
};

const typeClasses = {
    'dns_error': 'badge-warning',
    'timeout': 'badge-danger',
    'api_limit': 'badge-info',
    'no_response': 'badge-gray',
    'other': 'badge-gray',
};

async function loadFailures(page = 1) {
    currentPage = page;

    const params = {
        page,
        per_page: perPage,
        failure_type: document.getElementById('filter-type').value,
        is_ignored: document.getElementById('filter-ignored').value,
    };

    try {
        const data = await api.get('/api/failures', params);
        renderFailureList(data.items);
        document.getElementById('pagination').innerHTML = renderPagination(data.pagination, 'loadFailures');
    } catch (error) {
        toast.error('加载失败日志失败: ' + error.message);
    }
}

function renderFailureList(failures) {
    if (!failures || failures.length === 0) {
        document.getElementById('failure-list').innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">&#10004;</div>
                <div class="empty-state-title">暂无失败日志</div>
            </div>
        `;
        return;
    }

    const html = `
        <table class="table">
            <thead>
                <tr>
                    <th><input type="checkbox" onchange="toggleSelectAll(this)"></th>
                    <th>域名</th>
                    <th>失败类型</th>
                    <th>失败原因</th>
                    <th>重试次数</th>
                    <th>时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                ${failures.map(f => `
                    <tr class="${f.is_ignored ? 'text-muted' : ''}">
                        <td><input type="checkbox" value="${f.id}" onchange="toggleSelect(${f.id})"></td>
                        <td>${f.domain}</td>
                        <td><span class="badge ${typeClasses[f.failure_type] || 'badge-gray'}">${typeLabels[f.failure_type] || f.failure_type}</span></td>
                        <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">${f.failure_reason}</td>
                        <td>${f.retry_count}</td>
                        <td>${formatDate(f.created_at)}</td>
                        <td>
                            <div class="table-actions">
                                ${!f.is_ignored ? `
                                    <button class="btn btn-sm btn-primary" onclick="retryFailure(${f.id})">重试</button>
                                    <button class="btn btn-sm btn-outline" onclick="ignoreFailure(${f.id})">忽略</button>
                                ` : '<span class="text-muted">已忽略</span>'}
                            </div>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    document.getElementById('failure-list').innerHTML = html;
}

function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('tbody input[type="checkbox"]');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
        toggleSelect(parseInt(cb.value), checkbox.checked);
    });
}

function toggleSelect(id, checked = null) {
    if (checked === null) {
        const checkbox = document.querySelector(`tbody input[value="${id}"]`);
        checked = checkbox.checked;
    }

    if (checked) {
        if (!selectedIds.includes(id)) selectedIds.push(id);
    } else {
        selectedIds = selectedIds.filter(i => i !== id);
    }
}

async function retryFailure(id) {
    try {
        await api.post('/api/failures/' + id + '/retry');
        toast.success('已加入重试队列');
        loadFailures(currentPage);
    } catch (error) {
        toast.error(error.message);
    }
}

async function ignoreFailure(id) {
    try {
        await api.post('/api/failures/' + id + '/ignore');
        toast.success('已忽略');
        loadFailures(currentPage);
    } catch (error) {
        toast.error(error.message);
    }
}

async function batchRetry() {
    if (selectedIds.length === 0) {
        toast.warning('请先选择记录');
        return;
    }

    try {
        await api.post('/api/failures/batch-retry', { ids: selectedIds });
        toast.success('批量重试已加入队列');
        selectedIds = [];
        loadFailures(currentPage);
    } catch (error) {
        toast.error(error.message);
    }
}

async function batchIgnore() {
    if (selectedIds.length === 0) {
        toast.warning('请先选择记录');
        return;
    }

    try {
        await api.post('/api/failures/batch-ignore', { ids: selectedIds });
        toast.success('批量忽略成功');
        selectedIds = [];
        loadFailures(currentPage);
    } catch (error) {
        toast.error(error.message);
    }
}

function clearAll() {
    modal.confirm('确定要清空所有失败日志吗？', async () => {
        try {
            await api.delete('/api/failures');
            toast.success('日志已清空');
            loadFailures();
        } catch (error) {
            toast.error(error.message);
        }
    });
}

// 初始加载
document.addEventListener('DOMContentLoaded', function() {
    loadFailures();
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/main.php';
?>
