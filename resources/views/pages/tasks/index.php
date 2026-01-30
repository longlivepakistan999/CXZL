<?php
$pageTitle = '扫描任务';
$currentPage = 'tasks';
ob_start();
?>

<div class="stats-grid" id="queue-stats">
    <div class="loading"><div class="spinner"></div></div>
</div>

<!-- 批量重扫操作 -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header" style="padding: 15px 20px; border-bottom: 1px solid var(--border-color);">
        <strong>批量重扫操作</strong>
    </div>
    <div class="filter-bar" style="flex-wrap: wrap; gap: 10px;">
        <select class="form-control form-select" id="rescan-project" style="width: 200px;">
            <option value="">全部项目</option>
        </select>
        <button class="btn btn-primary" onclick="rescanPending()">扫描未扫描资产</button>
        <button class="btn btn-warning" onclick="rescanNonWp()">重扫非WP资产</button>
        <button class="btn btn-danger" onclick="rescanFailed()">重扫失败资产</button>
        <div class="dropdown" style="display: inline-block;">
            <button class="btn btn-outline" onclick="toggleDropdown(this)">导出 ▾</button>
            <div class="dropdown-menu">
                <a href="#" onclick="exportWpAssets('txt'); return false;">导出WP资产 (TXT)</a>
                <a href="#" onclick="exportWpAssets('json'); return false;">导出WP资产 (JSON)</a>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="filter-bar">
        <select class="form-control form-select" id="filter-project" onchange="loadTasks()">
            <option value="">全部项目</option>
        </select>
        <select class="form-control form-select" id="filter-status" onchange="loadTasks()">
            <option value="">全部状态</option>
            <option value="0">待扫描</option>
            <option value="1">扫描中</option>
            <option value="2">成功</option>
            <option value="3">失败</option>
            <option value="4">已取消</option>
        </select>
    </div>

    <div id="task-list">
        <div class="loading"><div class="spinner"></div></div>
    </div>

    <div id="pagination"></div>
</div>

<script>
let currentPage = 1;
const perPage = 20;

const statusMap = {
    0: { label: '待扫描', class: 'badge-warning' },
    1: { label: '扫描中', class: 'badge-info' },
    2: { label: '成功', class: 'badge-success' },
    3: { label: '失败', class: 'badge-danger' },
    4: { label: '已取消', class: 'badge-gray' },
};

async function loadStats() {
    try {
        const stats = await api.get('/api/tasks/stats');
        document.getElementById('queue-stats').innerHTML = `
            <div class="stat-card warning">
                <div class="stat-value">${formatNumber(stats.pending)}</div>
                <div class="stat-label">待扫描</div>
            </div>
            <div class="stat-card info">
                <div class="stat-value">${formatNumber(stats.running)}</div>
                <div class="stat-label">扫描中</div>
            </div>
            <div class="stat-card success">
                <div class="stat-value">${formatNumber(stats.success)}</div>
                <div class="stat-label">成功</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-value">${formatNumber(stats.failed)}</div>
                <div class="stat-label">失败</div>
            </div>
        `;
    } catch (error) {
        console.error(error);
    }
}

async function loadProjects() {
    try {
        const data = await api.get('/api/projects', { per_page: 1000 });
        const select = document.getElementById('filter-project');
        const rescanSelect = document.getElementById('rescan-project');
        data.items.forEach(p => {
            const option = document.createElement('option');
            option.value = p.id;
            option.textContent = p.name;
            select.appendChild(option);

            const option2 = document.createElement('option');
            option2.value = p.id;
            option2.textContent = p.name;
            rescanSelect.appendChild(option2);
        });
    } catch (error) {
        console.error(error);
    }
}

async function loadTasks(page = 1) {
    currentPage = page;

    const params = {
        page,
        per_page: perPage,
        project_id: document.getElementById('filter-project').value,
        status: document.getElementById('filter-status').value,
    };

    try {
        const data = await api.get('/api/tasks', params);
        renderTaskList(data.items);
        document.getElementById('pagination').innerHTML = renderPagination(data.pagination, 'loadTasks');
    } catch (error) {
        toast.error('加载任务列表失败: ' + error.message);
    }
}

function renderTaskList(tasks) {
    if (!tasks || tasks.length === 0) {
        document.getElementById('task-list').innerHTML = `
            <div class="empty-state">
                <div class="empty-state-title">暂无任务</div>
            </div>
        `;
        return;
    }

    const html = `
        <table class="table">
            <thead>
                <tr>
                    <th>所属项目</th>
                    <th>域名</th>
                    <th>任务类型</th>
                    <th>状态</th>
                    <th>开始时间</th>
                    <th>耗时</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                ${tasks.map(t => `
                    <tr>
                        <td>${t.project_name || '-'}</td>
                        <td>${t.domain}</td>
                        <td>${t.type_label}</td>
                        <td>${renderStatusBadge(t.status, statusMap)}</td>
                        <td>${formatDate(t.started_at)}</td>
                        <td>${t.duration ? t.duration + '秒' : '-'}</td>
                        <td>
                            <div class="table-actions">
                                ${t.status == 0 ? `
                                    <button class="btn btn-sm btn-outline" onclick="prioritizeTask(${t.id})">优先处理</button>
                                    <button class="btn btn-sm btn-danger" onclick="cancelTask(${t.id})">取消</button>
                                ` : ''}
                                ${t.status == 3 ? `
                                    <button class="btn btn-sm btn-primary" onclick="retryTask(${t.id})">重试</button>
                                ` : ''}
                            </div>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    document.getElementById('task-list').innerHTML = html;
}

async function cancelTask(id) {
    try {
        await api.post('/api/tasks/' + id + '/cancel');
        toast.success('任务已取消');
        loadStats();
        loadTasks(currentPage);
    } catch (error) {
        toast.error(error.message);
    }
}

async function retryTask(id) {
    try {
        await api.post('/api/tasks/' + id + '/retry');
        toast.success('任务已加入队列');
        loadStats();
        loadTasks(currentPage);
    } catch (error) {
        toast.error(error.message);
    }
}

async function prioritizeTask(id) {
    try {
        await api.post('/api/tasks/' + id + '/prioritize');
        toast.success('已提升优先级');
        loadTasks(currentPage);
    } catch (error) {
        toast.error(error.message);
    }
}

async function rescanPending() {
    const projectId = document.getElementById('rescan-project').value;
    modal.confirm('确定要扫描所有未扫描的资产吗？', async () => {
        try {
            toast.info('正在创建扫描任务...');
            const result = await api.post('/api/assets/rescan-pending', { project_id: projectId || 0 });
            toast.success(result.message);
            loadStats();
            loadTasks();
        } catch (error) {
            toast.error(error.message);
        }
    });
}

async function rescanNonWp() {
    const projectId = document.getElementById('rescan-project').value;
    modal.confirm('确定要重扫所有非WP资产吗？这将重新检测这些资产的WP状态。', async () => {
        try {
            toast.info('正在创建扫描任务...');
            const result = await api.post('/api/assets/rescan-non-wp', { project_id: projectId || 0 });
            toast.success(result.message);
            loadStats();
            loadTasks();
        } catch (error) {
            toast.error(error.message);
        }
    });
}

async function rescanFailed() {
    const projectId = document.getElementById('rescan-project').value;
    modal.confirm('确定要重扫所有失败的资产吗？', async () => {
        try {
            toast.info('正在创建扫描任务...');
            const result = await api.post('/api/assets/rescan-failed', { project_id: projectId || 0 });
            toast.success(result.message);
            loadStats();
            loadTasks();
        } catch (error) {
            toast.error(error.message);
        }
    });
}

function exportWpAssets(format) {
    const projectId = document.getElementById('rescan-project').value;
    let url = '/api/assets/export-wp?format=' + format;
    if (projectId) url += '&project_id=' + projectId;
    window.open(url, '_blank');
}

// 初始加载
document.addEventListener('DOMContentLoaded', function() {
    loadProjects();
    loadStats();
    loadTasks();

    // 自动刷新
    setInterval(() => {
        loadStats();
        loadTasks(currentPage);
    }, 10000);
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/main.php';
?>
