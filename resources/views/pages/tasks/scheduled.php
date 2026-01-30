<?php
$pageTitle = '定时任务';
$currentPage = 'scheduled';
$headerActions = '<button class="btn btn-primary" onclick="showCreateModal()">+ 新建任务</button>';
ob_start();
?>

<div class="card">
    <div id="task-list">
        <div class="loading"><div class="spinner"></div></div>
    </div>
</div>

<script>
const scanTypeLabels = {
    1: '首次扫描',
    2: '完整扫描',
    4: '仅刷新IP',
    5: '仅刷新CF',
    6: '仅刷新旁站',
    7: '仅刷新WP',
};

const scopeLabels = {
    'all': '全部资产',
    'failed': '仅失败资产',
    'outdated': '超期未扫描',
};

async function loadTasks() {
    try {
        const tasks = await api.get('/api/scheduled-tasks');
        renderTaskList(tasks);
    } catch (error) {
        toast.error('加载定时任务失败: ' + error.message);
    }
}

function renderTaskList(tasks) {
    if (!tasks || tasks.length === 0) {
        document.getElementById('task-list').innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">&#8635;</div>
                <div class="empty-state-title">暂无定时任务</div>
                <p class="text-muted">点击上方按钮创建定时任务</p>
            </div>
        `;
        return;
    }

    const html = `
        <table class="table">
            <thead>
                <tr>
                    <th>任务名称</th>
                    <th>Cron表达式</th>
                    <th>扫描类型</th>
                    <th>扫描范围</th>
                    <th>状态</th>
                    <th>上次执行</th>
                    <th>下次执行</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                ${tasks.map(t => `
                    <tr>
                        <td>${t.name}</td>
                        <td><code>${t.cron_expression}</code></td>
                        <td>${scanTypeLabels[t.scan_type] || '完整扫描'}</td>
                        <td>${scopeLabels[t.scan_scope] || t.scan_scope}</td>
                        <td>
                            <span class="badge ${t.is_enabled ? 'badge-success' : 'badge-gray'}">
                                ${t.is_enabled ? '已启用' : '已禁用'}
                            </span>
                        </td>
                        <td>${t.last_run_at ? formatDate(t.last_run_at) : '-'}</td>
                        <td>${t.next_run_at ? formatDate(t.next_run_at) : '-'}</td>
                        <td>
                            <div class="table-actions">
                                <button class="btn btn-sm btn-primary" onclick="runTask(${t.id})">立即执行</button>
                                <button class="btn btn-sm btn-outline" onclick="toggleTask(${t.id})">${t.is_enabled ? '禁用' : '启用'}</button>
                                <button class="btn btn-sm btn-outline" onclick="showEditModal(${t.id})">编辑</button>
                                <button class="btn btn-sm btn-danger" onclick="deleteTask(${t.id})">删除</button>
                            </div>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    document.getElementById('task-list').innerHTML = html;
}

function showCreateModal() {
    modal.open({
        title: '新建定时任务',
        content: `
            <form id="task-form">
                <div class="form-group">
                    <label class="form-label">任务名称 *</label>
                    <input type="text" class="form-control" name="name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Cron表达式 *</label>
                    <input type="text" class="form-control" name="cron_expression" placeholder="0 2 * * *" required>
                    <small class="text-muted">例: 0 2 * * * (每天凌晨2点)</small>
                </div>
                <div class="form-group">
                    <label class="form-label">扫描类型</label>
                    <select class="form-control form-select" name="scan_type">
                        <option value="2">完整扫描</option>
                        <option value="4">仅刷新IP</option>
                        <option value="5">仅刷新CF状态</option>
                        <option value="6">仅刷新旁站</option>
                        <option value="7">仅刷新WP状态</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">扫描范围</label>
                    <select class="form-control form-select" name="scan_scope">
                        <option value="all">全部资产</option>
                        <option value="failed">仅失败资产</option>
                        <option value="outdated">超期未扫描</option>
                    </select>
                </div>
            </form>
        `,
        footer: `
            <button class="btn btn-outline" onclick="modal.close()">取消</button>
            <button class="btn btn-primary" onclick="createTask()">创建</button>
        `,
    });
}

async function createTask() {
    const form = document.getElementById('task-form');
    const data = serializeForm(form);

    try {
        await api.post('/api/scheduled-tasks', data);
        toast.success('定时任务创建成功');
        modal.close();
        loadTasks();
    } catch (error) {
        toast.error(error.message);
    }
}

async function showEditModal(id) {
    try {
        const tasks = await api.get('/api/scheduled-tasks');
        const task = tasks.find(t => t.id === id);
        if (!task) return;

        modal.open({
            title: '编辑定时任务',
            content: `
                <form id="task-form">
                    <div class="form-group">
                        <label class="form-label">任务名称 *</label>
                        <input type="text" class="form-control" name="name" value="${task.name}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cron表达式 *</label>
                        <input type="text" class="form-control" name="cron_expression" value="${task.cron_expression}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">扫描类型</label>
                        <select class="form-control form-select" name="scan_type">
                            <option value="2" ${task.scan_type == 2 ? 'selected' : ''}>完整扫描</option>
                            <option value="4" ${task.scan_type == 4 ? 'selected' : ''}>仅刷新IP</option>
                            <option value="5" ${task.scan_type == 5 ? 'selected' : ''}>仅刷新CF状态</option>
                            <option value="6" ${task.scan_type == 6 ? 'selected' : ''}>仅刷新旁站</option>
                            <option value="7" ${task.scan_type == 7 ? 'selected' : ''}>仅刷新WP状态</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">扫描范围</label>
                        <select class="form-control form-select" name="scan_scope">
                            <option value="all" ${task.scan_scope === 'all' ? 'selected' : ''}>全部资产</option>
                            <option value="failed" ${task.scan_scope === 'failed' ? 'selected' : ''}>仅失败资产</option>
                            <option value="outdated" ${task.scan_scope === 'outdated' ? 'selected' : ''}>超期未扫描</option>
                        </select>
                    </div>
                </form>
            `,
            footer: `
                <button class="btn btn-outline" onclick="modal.close()">取消</button>
                <button class="btn btn-primary" onclick="updateTask(${id})">保存</button>
            `,
        });
    } catch (error) {
        toast.error(error.message);
    }
}

async function updateTask(id) {
    const form = document.getElementById('task-form');
    const data = serializeForm(form);

    try {
        await api.put('/api/scheduled-tasks/' + id, data);
        toast.success('定时任务更新成功');
        modal.close();
        loadTasks();
    } catch (error) {
        toast.error(error.message);
    }
}

async function toggleTask(id) {
    try {
        await api.post('/api/scheduled-tasks/' + id + '/toggle');
        toast.success('状态已更新');
        loadTasks();
    } catch (error) {
        toast.error(error.message);
    }
}

async function runTask(id) {
    try {
        const result = await api.post('/api/scheduled-tasks/' + id + '/run');
        toast.success(`已创建 ${result.task_count} 个扫描任务`);
    } catch (error) {
        toast.error(error.message);
    }
}

function deleteTask(id) {
    modal.confirm('确定要删除这个定时任务吗？', async () => {
        try {
            await api.delete('/api/scheduled-tasks/' + id);
            toast.success('定时任务已删除');
            loadTasks();
        } catch (error) {
            toast.error(error.message);
        }
    });
}

// 初始加载
document.addEventListener('DOMContentLoaded', function() {
    loadTasks();
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/main.php';
?>
