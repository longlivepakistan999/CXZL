<?php
$projectId = $params['id'] ?? 0;
$pageTitle = '项目详情';
$currentPage = 'projects';
ob_start();
?>

<div id="project-info" class="card mb-3">
    <div class="loading"><div class="spinner"></div></div>
</div>

<div class="stats-grid" id="project-stats">
    <div class="loading"><div class="spinner"></div></div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">资产列表</h3>
        <div class="btn-group">
            <button class="btn btn-primary" onclick="showImportModal()">+ 导入资产</button>
            <button class="btn btn-outline" onclick="batchScan()">批量扫描</button>
            <button class="btn btn-outline" onclick="showExportModal()">导出</button>
        </div>
    </div>

    <div class="filter-bar">
        <select class="form-control form-select" id="filter-cf" onchange="loadAssets()">
            <option value="">CF状态: 全部</option>
            <option value="1">是CF</option>
            <option value="0">非CF</option>
        </select>
        <select class="form-control form-select" id="filter-wp" onchange="loadAssets()">
            <option value="">WP状态: 全部</option>
            <option value="1">是WP</option>
            <option value="0">非WP</option>
        </select>
        <select class="form-control form-select" id="filter-status" onchange="loadAssets()">
            <option value="">扫描状态: 全部</option>
            <option value="0">待扫描</option>
            <option value="1">队列中</option>
            <option value="2">扫描中</option>
            <option value="3">已完成</option>
            <option value="4">失败</option>
        </select>
        <div class="search-box">
            <input type="text" class="form-control" id="search-input" placeholder="搜索域名/IP...">
            <button class="btn btn-outline" onclick="loadAssets()">搜索</button>
        </div>
    </div>

    <div id="asset-list">
        <div class="loading"><div class="spinner"></div></div>
    </div>

    <div id="pagination"></div>
</div>

<script>
const projectId = <?= $projectId ?>;
let currentPage = 1;
const perPage = 20;
let selectedIds = [];
let projectData = null;

const scanStatusMap = {
    0: { label: '待扫描', class: 'badge-gray' },
    1: { label: '队列中', class: 'badge-warning' },
    2: { label: '扫描中', class: 'badge-info' },
    3: { label: '已完成', class: 'badge-success' },
    4: { label: '失败', class: 'badge-danger' },
};

async function loadProjectInfo() {
    try {
        projectData = await api.get('/api/projects/' + projectId);

        document.getElementById('project-info').innerHTML = `
            <div class="card-body">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <h2 style="margin-bottom: 8px;">${projectData.name}</h2>
                        <p class="text-muted">${projectData.description || '暂无描述'}</p>
                        <p class="text-muted" style="font-size: 12px;">创建于: ${formatDate(projectData.created_at)}</p>
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-outline" onclick="showEditProjectModal()">编辑</button>
                        <button class="btn btn-primary" onclick="scanAll()">全量扫描</button>
                    </div>
                </div>
            </div>
        `;

        // 渲染统计
        const stats = projectData.stats;
        document.getElementById('project-stats').innerHTML = `
            <div class="stat-card">
                <div class="stat-value">${formatNumber(stats.total)}</div>
                <div class="stat-label">资产总数</div>
            </div>
            <div class="stat-card info">
                <div class="stat-value">${formatNumber(stats.cf_count)}</div>
                <div class="stat-label">CF资产</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">${formatNumber(stats.non_cf_count)}</div>
                <div class="stat-label">非CF资产</div>
            </div>
            <div class="stat-card success">
                <div class="stat-value">${formatNumber(stats.wp_count)}</div>
                <div class="stat-label">WP资产</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-value">${formatNumber(stats.sidesite_total)}</div>
                <div class="stat-label">旁站总数</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">${formatNumber(stats.wp_sidesite_total)}</div>
                <div class="stat-label">WP旁站</div>
            </div>
        `;

    } catch (error) {
        toast.error('加载项目信息失败: ' + error.message);
    }
}

async function loadAssets(page = 1) {
    currentPage = page;

    const params = {
        project_id: projectId,
        page,
        per_page: perPage,
        is_cf: document.getElementById('filter-cf').value,
        is_wp: document.getElementById('filter-wp').value,
        scan_status: document.getElementById('filter-status').value,
        search: document.getElementById('search-input').value,
    };

    try {
        const data = await api.get('/api/assets', params);
        renderAssetList(data.items);
        document.getElementById('pagination').innerHTML = renderPagination(data.pagination, 'loadAssets');
    } catch (error) {
        toast.error('加载资产列表失败: ' + error.message);
    }
}

function renderAssetList(assets) {
    if (!assets || assets.length === 0) {
        document.getElementById('asset-list').innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">&#128193;</div>
                <div class="empty-state-title">暂无资产</div>
                <p class="text-muted">点击上方按钮导入资产</p>
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
                    <th>IP</th>
                    <th>CF</th>
                    <th>WP</th>
                    <th>组件数</th>
                    <th>旁站数</th>
                    <th>WP旁站</th>
                    <th>扫描状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                ${assets.map(a => `
                    <tr data-id="${a.id}">
                        <td><input type="checkbox" value="${a.id}" onchange="toggleSelect(${a.id})"></td>
                        <td><a href="/assets/${a.id}">${a.domain}</a></td>
                        <td>${a.ip || '-'}</td>
                        <td>${a.is_cf == 1 ? '<span class="badge badge-info">CF</span>' : a.is_cf == 0 ? '否' : '-'}</td>
                        <td>${a.is_wp == 1 ? '<span class="badge badge-success">WP</span>' : a.is_wp == 0 ? '否' : '-'}</td>
                        <td>${a.component_count || 0}</td>
                        <td>${a.sidesite_count || 0}</td>
                        <td>${a.wp_sidesite_count || 0}</td>
                        <td>${renderStatusBadge(a.scan_status, scanStatusMap)}</td>
                        <td>
                            <div class="table-actions">
                                <a href="/assets/${a.id}" class="btn btn-sm btn-outline">详情</a>
                                <button class="btn btn-sm btn-primary" onclick="scanAsset(${a.id})">扫描</button>
                                <button class="btn btn-sm btn-danger" onclick="deleteAsset(${a.id})">删除</button>
                            </div>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    document.getElementById('asset-list').innerHTML = html;
}

function showImportModal() {
    modal.open({
        title: '导入资产',
        content: `
            <form id="import-form">
                <div class="tabs" style="margin-bottom: 16px;">
                    <div class="tab-item active" onclick="switchImportTab('paste')">批量粘贴</div>
                    <div class="tab-item" onclick="switchImportTab('file')">文件上传</div>
                </div>

                <div id="import-paste">
                    <div class="form-group">
                        <label class="form-label">域名列表 (每行一个)</label>
                        <textarea class="form-control" name="content" rows="10" placeholder="example.com&#10;test.com&#10;demo.org"></textarea>
                    </div>
                </div>

                <div id="import-file" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">选择文件 (支持TXT/CSV/Excel)</label>
                        <input type="file" class="form-control" name="file" accept=".txt,.csv,.xlsx,.xls">
                    </div>
                </div>

                <div class="form-group">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" name="skip_duplicates" checked>
                        <span style="margin-left: 8px;">跳过已存在的重复资产</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" name="auto_scan">
                        <span style="margin-left: 8px;">自动开始扫描</span>
                    </label>
                </div>
            </form>
        `,
        footer: `
            <button class="btn btn-outline" onclick="modal.close()">取消</button>
            <button class="btn btn-primary" onclick="doImport()">导入</button>
        `,
        size: 'lg',
    });
}

function switchImportTab(tab) {
    document.querySelectorAll('.tabs .tab-item').forEach(el => el.classList.remove('active'));
    event.target.classList.add('active');

    document.getElementById('import-paste').style.display = tab === 'paste' ? 'block' : 'none';
    document.getElementById('import-file').style.display = tab === 'file' ? 'block' : 'none';
}

async function doImport() {
    const form = document.getElementById('import-form');
    const formData = new FormData(form);
    formData.append('project_id', projectId);

    try {
        const response = await fetch('/api/assets/import', {
            method: 'POST',
            body: formData,
        });
        const result = await response.json();

        if (result.code === 0) {
            toast.success(`导入完成: ${result.data.imported}条成功, ${result.data.skipped}条跳过`);
            modal.close();
            loadProjectInfo();
            loadAssets();
        } else {
            toast.error(result.message);
        }
    } catch (error) {
        toast.error('导入失败: ' + error.message);
    }
}

async function scanAsset(id) {
    try {
        await api.post('/api/assets/' + id + '/scan', { scan_type: 2, priority: 10 });
        toast.success('扫描任务已创建');
        loadAssets(currentPage);
    } catch (error) {
        toast.error(error.message);
    }
}

async function deleteAsset(id) {
    modal.confirm('确定要删除这个资产吗？', async () => {
        try {
            await api.delete('/api/assets/' + id);
            toast.success('资产已删除');
            loadProjectInfo();
            loadAssets(currentPage);
        } catch (error) {
            toast.error(error.message);
        }
    });
}

async function scanAll() {
    try {
        const result = await api.post('/api/projects/' + projectId + '/scan', { scan_type: 2 });
        toast.success(`已创建 ${result.task_count} 个扫描任务`);
    } catch (error) {
        toast.error(error.message);
    }
}

async function batchScan() {
    if (selectedIds.length === 0) {
        toast.warning('请先选择资产');
        return;
    }

    try {
        const result = await api.post('/api/assets/batch-scan', {
            asset_ids: selectedIds,
            scan_type: 2,
        });
        toast.success(`已创建 ${result.task_count} 个扫描任务`);
        selectedIds = [];
        loadAssets(currentPage);
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

function showExportModal() {
    modal.open({
        title: '导出资产',
        content: `
            <form id="export-form">
                <div class="form-group">
                    <label class="form-label">快捷导出</label>
                    <div class="btn-group" style="flex-wrap: wrap; gap: 8px;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="quickExport('all_assets')">全部资产</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="quickExport('wp_assets')">WP资产</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="quickExport('non_cf_assets')">非CF资产</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="quickExport('all_sidesites')">全部旁站</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="quickExport('wp_sidesites')">WP旁站</button>
                    </div>
                </div>
            </form>
        `,
        footer: `
            <button class="btn btn-outline" onclick="modal.close()">关闭</button>
        `,
    });
}

async function quickExport(type) {
    try {
        window.open(`/api/exports/quick?project_id=${projectId}&type=${type}`, '_blank');
        toast.success('导出已开始');
    } catch (error) {
        toast.error(error.message);
    }
}

// 初始加载
document.addEventListener('DOMContentLoaded', function() {
    loadProjectInfo();
    loadAssets();

    // 搜索回车
    document.getElementById('search-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') loadAssets();
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/main.php';
?>
