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
                                <button class="btn btn-sm btn-outline" onclick="showSidesitesModal(${p.id}, '${p.name}')" ${p.sidesite_count > 0 ? '' : 'disabled'}>旁站</button>
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

// 旁站弹窗
let currentSidesiteProjectId = null;
let sidesitePage = 1;
let sidesiteFilter = 'all';

async function showSidesitesModal(projectId, projectName) {
    currentSidesiteProjectId = projectId;
    sidesitePage = 1;
    sidesiteFilter = 'all';

    try {
        const stats = await api.get('/api/sidesites/project/' + projectId + '/stats');

        modal.open({
            title: `旁站管理 - ${projectName}`,
            width: '900px',
            content: `
                <div class="stats-grid" style="margin-bottom: 16px;">
                    <div class="stat-card">
                        <div class="stat-value">${formatNumber(stats.total)}</div>
                        <div class="stat-label">总旁站</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value">${formatNumber(stats.wp_count)}</div>
                        <div class="stat-label">WP旁站</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-value">${formatNumber(stats.non_wp_count)}</div>
                        <div class="stat-label">非WP旁站</div>
                    </div>
                    <div class="stat-card gray">
                        <div class="stat-value">${formatNumber(stats.unknown_count)}</div>
                        <div class="stat-label">未检测</div>
                    </div>
                </div>

                <div class="filter-bar" style="margin-bottom: 16px;">
                    <select class="form-control form-select" id="sidesite-filter" onchange="filterSidesites()" style="width: 150px;">
                        <option value="all">全部旁站</option>
                        <option value="wp">WP旁站</option>
                        <option value="non_wp">非WP旁站</option>
                    </select>
                    <select class="form-control form-select" id="sidesite-component" onchange="filterSidesites()" style="width: 200px;">
                        <option value="">全部组件</option>
                        ${stats.components.map(c => `<option value="${c.name}">${c.plugin_name || c.name} (${c.count})</option>`).join('')}
                    </select>
                    <div class="dropdown" style="margin-left: auto;">
                        <button class="btn btn-outline" onclick="toggleDropdown(this)">导出 ▾</button>
                        <div class="dropdown-menu">
                            <a href="#" onclick="exportSidesites('all'); return false;">导出全部旁站</a>
                            <a href="#" onclick="exportSidesites('wp'); return false;">导出WP旁站</a>
                            <a href="#" onclick="exportSidesites('non_wp'); return false;">导出非WP旁站</a>
                            <a href="#" onclick="exportSidesitesByComponent(); return false;">导出当前筛选</a>
                        </div>
                    </div>
                </div>

                ${stats.components.length > 0 ? `
                <div style="margin-bottom: 16px;">
                    <strong>WP组件/插件统计:</strong>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px;">
                        ${stats.components.slice(0, 20).map(c => `
                            <span class="badge badge-info" style="cursor: pointer;" onclick="filterByComponent('${c.name}')">
                                ${c.plugin_name || c.name}: ${c.count}
                            </span>
                        `).join('')}
                    </div>
                </div>
                ` : ''}

                <div id="sidesite-list">
                    <div class="loading"><div class="spinner"></div></div>
                </div>
                <div id="sidesite-pagination"></div>
            `,
            footer: `
                <button class="btn btn-outline" onclick="modal.close()">关闭</button>
            `,
        });

        loadSidesites();
    } catch (error) {
        toast.error(error.message);
    }
}

async function loadSidesites() {
    const filter = document.getElementById('sidesite-filter').value;
    const component = document.getElementById('sidesite-component').value;

    const params = {
        project_id: currentSidesiteProjectId,
        page: sidesitePage,
        per_page: 15,
    };

    if (filter === 'wp') params.is_wp = 1;
    else if (filter === 'non_wp') params.is_wp = 0;

    if (component) params.component = component;

    try {
        const data = await api.get('/api/sidesites', params);
        renderSidesiteList(data.items);
        document.getElementById('sidesite-pagination').innerHTML = renderPagination(data.pagination, 'goSidesitePage');
    } catch (error) {
        document.getElementById('sidesite-list').innerHTML = `<div class="text-danger">加载失败: ${error.message}</div>`;
    }
}

function renderSidesiteList(sidesites) {
    if (!sidesites || sidesites.length === 0) {
        document.getElementById('sidesite-list').innerHTML = '<div class="empty-state"><div class="empty-state-title">暂无旁站</div></div>';
        return;
    }

    const html = `
        <table class="table">
            <thead>
                <tr>
                    <th>域名</th>
                    <th>IP</th>
                    <th>WP</th>
                    <th>组件数</th>
                    <th>组件</th>
                </tr>
            </thead>
            <tbody>
                ${sidesites.map(s => `
                    <tr>
                        <td><a href="${s.protocol || 'https'}://${s.domain}" target="_blank">${s.domain}</a></td>
                        <td>${s.ip || '-'}</td>
                        <td>${s.is_wp === 1 ? '<span class="badge badge-success">是</span>' : s.is_wp === 0 ? '<span class="badge badge-gray">否</span>' : '<span class="badge badge-warning">未检测</span>'}</td>
                        <td>${s.component_count || 0}</td>
                        <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            ${s.components && s.components.length > 0 ? s.components.slice(0, 3).join(', ') + (s.components.length > 3 ? '...' : '') : '-'}
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    document.getElementById('sidesite-list').innerHTML = html;
}

function filterSidesites() {
    sidesitePage = 1;
    loadSidesites();
}

function filterByComponent(component) {
    document.getElementById('sidesite-component').value = component;
    document.getElementById('sidesite-filter').value = 'wp';
    filterSidesites();
}

function goSidesitePage(page) {
    sidesitePage = page;
    loadSidesites();
}

function exportSidesites(type) {
    const component = document.getElementById('sidesite-component').value;
    let url = `/api/sidesites/project/${currentSidesiteProjectId}/export?type=${type}`;
    if (component) url += `&component=${encodeURIComponent(component)}`;
    window.open(url, '_blank');
}

function exportSidesitesByComponent() {
    const type = document.getElementById('sidesite-filter').value;
    const component = document.getElementById('sidesite-component').value;
    let url = `/api/sidesites/project/${currentSidesiteProjectId}/export?type=${type === 'all' ? 'all' : type}`;
    if (component) url += `&component=${encodeURIComponent(component)}`;
    window.open(url, '_blank');
}

// 初始加载
document.addEventListener('DOMContentLoaded', function() {
    loadProjects();

    // 搜索回车
    document.getElementById('search-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') loadProjects();
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/main.php';
?>
