<?php
$pageTitle = '项目管理';
$currentPage = 'projects';
$headerActions = '<button class="btn btn-primary" onclick="showCreateModal()">+ 新建项目</button>';
ob_start();
?>

<div class="card">
    <div class="filter-bar">
        <div class="search-box">
            <input type="text" class="form-control" id="search-input" placeholder="搜索项目名称...">
            <button class="btn btn-primary" onclick="loadProjects()">搜索</button>
        </div>
        <div class="btn-group" style="margin-left: auto;">
            <button class="btn btn-outline" onclick="batchDelete()">批量删除</button>
            <button class="btn btn-outline" onclick="batchExport()">批量导出</button>
        </div>
    </div>

    <div id="project-list">
        <div class="loading"><div class="spinner"></div></div>
    </div>

    <div id="pagination"></div>
</div>

<script>
let currentPage = 1;
const perPage = 20;
let selectedIds = [];

const statusMap = {
    '全部完成': { label: '全部完成', class: 'badge-success' },
    '部分完成': { label: '部分完成', class: 'badge-warning' },
    '扫描中': { label: '扫描中', class: 'badge-info' },
    '待扫描': { label: '待扫描', class: 'badge-gray' },
    '无资产': { label: '无资产', class: 'badge-gray' },
};

async function loadProjects(page = 1) {
    currentPage = page;
    const search = document.getElementById('search-input').value;

    try {
        const data = await api.get('/api/projects', {
            page,
            per_page: perPage,
            search,
        });

        renderProjectList(data.items);
        document.getElementById('pagination').innerHTML = renderPagination(data.pagination, 'loadProjects');

    } catch (error) {
        toast.error('加载项目列表失败: ' + error.message);
    }
}

function renderProjectList(projects) {
    if (!projects || projects.length === 0) {
        document.getElementById('project-list').innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">&#128193;</div>
                <div class="empty-state-title">暂无项目</div>
                <p class="text-muted">点击上方按钮创建第一个项目</p>
            </div>
        `;
        return;
    }

    const html = `
        <table class="table">
            <thead>
                <tr>
                    <th><input type="checkbox" onchange="toggleSelectAll(this)"></th>
                    <th>项目名称</th>
                    <th>资产数</th>
                    <th>CF数</th>
                    <th>非CF数</th>
                    <th>WP数</th>
                    <th>旁站总数</th>
                    <th>扫描状态</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                ${projects.map(p => `
                    <tr data-id="${p.id}">
                        <td><input type="checkbox" value="${p.id}" onchange="toggleSelect(${p.id})"></td>
                        <td><a href="/projects/${p.id}">${p.name}</a></td>
                        <td>${formatNumber(p.asset_count)}</td>
                        <td>${formatNumber(p.cf_count)}</td>
                        <td>${formatNumber(p.asset_count - p.cf_count)}</td>
                        <td>${formatNumber(p.wp_count)}</td>
                        <td>${formatNumber(p.sidesite_count)}</td>
                        <td>${renderStatusBadge(p.scan_status, statusMap)}</td>
                        <td>${formatDate(p.created_at)}</td>
                        <td>
                            <div class="table-actions">
                                <a href="/projects/${p.id}" class="btn btn-sm btn-outline">进入</a>
                                <button class="btn btn-sm btn-outline" onclick="showEditModal(${p.id})">编辑</button>
                                <button class="btn btn-sm btn-primary" onclick="scanProject(${p.id})">扫描</button>
                                <button class="btn btn-sm btn-danger" onclick="deleteProject(${p.id})">删除</button>
                            </div>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    document.getElementById('project-list').innerHTML = html;
}

function showCreateModal() {
    modal.open({
        title: '新建项目',
        content: `
            <form id="create-form">
                <div class="form-group">
                    <label class="form-label">项目名称 *</label>
                    <input type="text" class="form-control" name="name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">项目描述</label>
                    <textarea class="form-control" name="description"></textarea>
                </div>
            </form>
        `,
        footer: `
            <button class="btn btn-outline" onclick="modal.close()">取消</button>
            <button class="btn btn-primary" onclick="createProject()">创建</button>
        `,
    });
}

async function createProject() {
    const form = document.getElementById('create-form');
    const data = serializeForm(form);

    try {
        await api.post('/api/projects', data);
        toast.success('项目创建成功');
        modal.close();
        loadProjects();
    } catch (error) {
        toast.error(error.message);
    }
}

async function showEditModal(id) {
    try {
        const project = await api.get('/api/projects/' + id);

        modal.open({
            title: '编辑项目',
            content: `
                <form id="edit-form">
                    <input type="hidden" name="id" value="${project.id}">
                    <div class="form-group">
                        <label class="form-label">项目名称 *</label>
                        <input type="text" class="form-control" name="name" value="${project.name}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">项目描述</label>
                        <textarea class="form-control" name="description">${project.description || ''}</textarea>
                    </div>
                </form>
            `,
            footer: `
                <button class="btn btn-outline" onclick="modal.close()">取消</button>
                <button class="btn btn-primary" onclick="updateProject(${id})">保存</button>
            `,
        });
    } catch (error) {
        toast.error(error.message);
    }
}

async function updateProject(id) {
    const form = document.getElementById('edit-form');
    const data = serializeForm(form);

    try {
        await api.put('/api/projects/' + id, data);
        toast.success('项目更新成功');
        modal.close();
        loadProjects(currentPage);
    } catch (error) {
        toast.error(error.message);
    }
}

function deleteProject(id) {
    modal.confirm('确定要删除这个项目吗？项目下的所有资产和旁站都将被删除。', async () => {
        try {
            await api.delete('/api/projects/' + id);
            toast.success('项目已删除');
            loadProjects(currentPage);
        } catch (error) {
            toast.error(error.message);
        }
    });
}

async function scanProject(id) {
    modal.open({
        title: '全量扫描',
        content: `
            <form id="scan-form">
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
                    <label class="form-label">优先级</label>
                    <select class="form-control form-select" name="priority">
                        <option value="1">低</option>
                        <option value="5" selected>普通</option>
                        <option value="10">高</option>
                    </select>
                </div>
            </form>
        `,
        footer: `
            <button class="btn btn-outline" onclick="modal.close()">取消</button>
            <button class="btn btn-primary" onclick="doScanProject(${id})">开始扫描</button>
        `,
    });
}

async function doScanProject(id) {
    const form = document.getElementById('scan-form');
    const data = serializeForm(form);

    try {
        const result = await api.post('/api/projects/' + id + '/scan', data);
        toast.success(`已创建 ${result.task_count} 个扫描任务`);
        modal.close();
    } catch (error) {
        toast.error(error.message);
    }
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

function batchDelete() {
    if (selectedIds.length === 0) {
        toast.warning('请先选择项目');
        return;
    }

    modal.confirm(`确定要删除选中的 ${selectedIds.length} 个项目吗？`, async () => {
        for (const id of selectedIds) {
            try {
                await api.delete('/api/projects/' + id);
            } catch (error) {
                console.error(error);
            }
        }
        toast.success('批量删除完成');
        selectedIds = [];
        loadProjects(currentPage);
    });
}

// 初始加载
loadProjects();

// 搜索回车
document.getElementById('search-input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') loadProjects();
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/main.php';
?>
