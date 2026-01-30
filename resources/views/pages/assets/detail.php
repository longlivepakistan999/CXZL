<?php
$assetId = $params['id'] ?? 0;
$pageTitle = '资产详情';
$currentPage = 'projects';
ob_start();
?>

<div id="asset-info" class="card mb-3">
    <div class="loading"><div class="spinner"></div></div>
</div>

<div class="stats-grid" id="asset-stats" style="display: none;"></div>

<div id="sidesites-section" style="display: none;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">旁站列表</h3>
            <div class="btn-group">
                <button class="btn btn-outline" onclick="batchScanSidesites()">批量扫描</button>
            </div>
        </div>

        <div class="tabs" style="padding: 16px 20px 0;">
            <div class="tab-item active" onclick="filterSidesites('')">全部</div>
            <div class="tab-item" onclick="filterSidesites('wp')">WP</div>
            <div class="tab-item" onclick="filterSidesites('non_wp')">非WP</div>
            <div class="tab-item" onclick="filterSidesites('failed')">失败</div>
        </div>

        <div id="sidesite-list">
            <div class="loading"><div class="spinner"></div></div>
        </div>

        <div id="sidesite-pagination"></div>
    </div>
</div>

<script>
const assetId = <?= $assetId ?>;
let assetData = null;
let currentFilter = '';
let currentPage = 1;
const perPage = 20;

const scanStatusMap = {
    0: { label: '待扫描', class: 'badge-gray' },
    1: { label: '队列中', class: 'badge-warning' },
    2: { label: '扫描中', class: 'badge-info' },
    3: { label: '已完成', class: 'badge-success' },
    4: { label: '失败', class: 'badge-danger' },
};

async function loadAssetInfo() {
    try {
        assetData = await api.get('/api/assets/' + assetId);

        // 渲染基本信息
        let componentsHtml = '';
        if (assetData.components && assetData.components.length > 0) {
            componentsHtml = assetData.components.map(c => `<span class="tag" style="background-color: #DBEAFE; color: #1E40AF;">${c}</span>`).join('');
        } else {
            componentsHtml = '<span class="text-muted">无</span>';
        }

        let tagsHtml = '';
        if (assetData.tag_details && assetData.tag_details.length > 0) {
            tagsHtml = assetData.tag_details.map(t => `<span class="tag" style="background-color: ${t.color}20; color: ${t.color};">${t.name}</span>`).join('');
        } else {
            tagsHtml = '<span class="text-muted">无标签</span>';
        }

        document.getElementById('asset-info').innerHTML = `
            <div class="card-body">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                            <h2>${assetData.domain}</h2>
                            ${assetData.is_cf == 1 ? '<span class="badge badge-info">CF</span>' : ''}
                            ${assetData.is_wp == 1 ? '<span class="badge badge-success">WP</span>' : ''}
                            ${renderStatusBadge(assetData.scan_status, scanStatusMap)}
                        </div>

                        <table style="width: 100%; max-width: 600px;">
                            <tr>
                                <td class="text-muted" style="padding: 8px 16px 8px 0; width: 100px;">所属项目</td>
                                <td><a href="/projects/${assetData.project_id}">${assetData.project?.name || '-'}</a></td>
                            </tr>
                            <tr>
                                <td class="text-muted" style="padding: 8px 16px 8px 0;">IP地址</td>
                                <td>${assetData.ip || '-'} <button class="btn btn-sm btn-outline" onclick="copyToClipboard('${assetData.ip}')" style="margin-left: 8px;">复制</button></td>
                            </tr>
                            <tr>
                                <td class="text-muted" style="padding: 8px 16px 8px 0;">是否CF</td>
                                <td>${assetData.is_cf == 1 ? '是' : assetData.is_cf == 0 ? '否' : '未检测'}</td>
                            </tr>
                            <tr>
                                <td class="text-muted" style="padding: 8px 16px 8px 0;">是否WP</td>
                                <td>${assetData.is_wp == 1 ? '是' : assetData.is_wp == 0 ? '否' : '未检测'}</td>
                            </tr>
                            <tr>
                                <td class="text-muted" style="padding: 8px 16px 8px 0;">组件列表</td>
                                <td>${componentsHtml}</td>
                            </tr>
                            <tr>
                                <td class="text-muted" style="padding: 8px 16px 8px 0;">标签</td>
                                <td>${tagsHtml}</td>
                            </tr>
                            <tr>
                                <td class="text-muted" style="padding: 8px 16px 8px 0;">导入时间</td>
                                <td>${formatDate(assetData.imported_at)}</td>
                            </tr>
                            <tr>
                                <td class="text-muted" style="padding: 8px 16px 8px 0;">扫描时间</td>
                                <td>${formatDate(assetData.scanned_at)}</td>
                            </tr>
                            ${assetData.remark ? `
                            <tr>
                                <td class="text-muted" style="padding: 8px 16px 8px 0;">备注</td>
                                <td>${assetData.remark}</td>
                            </tr>
                            ` : ''}
                        </table>
                    </div>
                    <div class="btn-group" style="flex-direction: column; gap: 8px;">
                        <button class="btn btn-primary" onclick="scanAsset()">重新扫描</button>
                        <a href="https://${assetData.domain}" target="_blank" class="btn btn-outline">访问网站</a>
                        <button class="btn btn-outline" onclick="copyToClipboard('${assetData.domain}')">复制域名</button>
                        <button class="btn btn-danger" onclick="deleteAsset()">删除</button>
                    </div>
                </div>
            </div>
        `;

        // 如果非CF,显示旁站统计和列表
        if (assetData.is_cf != 1) {
            document.getElementById('sidesites-section').style.display = 'block';

            const stats = assetData.sidesite_stats;
            document.getElementById('asset-stats').style.display = 'grid';
            document.getElementById('asset-stats').innerHTML = `
                <div class="stat-card">
                    <div class="stat-value">${formatNumber(stats.total)}</div>
                    <div class="stat-label">旁站总数</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-value">${formatNumber(stats.wp_count)}</div>
                    <div class="stat-label">WP旁站</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${formatNumber(stats.non_wp_count)}</div>
                    <div class="stat-label">非WP旁站</div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-value">${formatNumber(stats.failed_count)}</div>
                    <div class="stat-label">扫描失败</div>
                </div>
            `;

            loadSidesites();
        }

    } catch (error) {
        toast.error('加载资产信息失败: ' + error.message);
    }
}

async function loadSidesites(page = 1) {
    currentPage = page;

    const params = {
        asset_id: assetId,
        page,
        per_page: perPage,
    };

    if (currentFilter === 'wp') {
        params.is_wp = 1;
    } else if (currentFilter === 'non_wp') {
        params.is_wp = 0;
    } else if (currentFilter === 'failed') {
        params.scan_status = 4;
    }

    try {
        const data = await api.get('/api/sidesites', params);
        renderSidesiteList(data.items);
        document.getElementById('sidesite-pagination').innerHTML = renderPagination(data.pagination, 'loadSidesites');
    } catch (error) {
        toast.error('加载旁站列表失败: ' + error.message);
    }
}

function renderSidesiteList(sidesites) {
    if (!sidesites || sidesites.length === 0) {
        document.getElementById('sidesite-list').innerHTML = `
            <div class="empty-state">
                <div class="empty-state-title">暂无旁站</div>
            </div>
        `;
        return;
    }

    const showComponents = currentFilter === 'wp';
    const showError = currentFilter === 'failed';

    let headers = '<th>旁站域名</th><th>IP</th>';
    if (showComponents) {
        headers += '<th>组件数</th>';
    } else if (showError) {
        headers += '<th>失败原因</th>';
    } else {
        headers += '<th>WP状态</th>';
    }
    headers += '<th>操作</th>';

    const rows = sidesites.map(s => {
        let middleCell = '';
        if (showComponents) {
            middleCell = `<td>${s.component_count || 0}</td>`;
        } else if (showError) {
            middleCell = `<td class="text-danger">${s.scan_error || '-'}</td>`;
        } else {
            middleCell = `<td>${s.is_wp == 1 ? '<span class="badge badge-success">WP</span>' : s.is_wp == 0 ? '否' : '-'}</td>`;
        }

        return `
            <tr>
                <td>${s.domain}</td>
                <td>${s.ip || '-'}</td>
                ${middleCell}
                <td>
                    <div class="table-actions">
                        <a href="https://${s.domain}" target="_blank" class="btn btn-sm btn-outline">访问</a>
                        <button class="btn btn-sm btn-outline" onclick="copyToClipboard('${s.domain}')">复制</button>
                        ${s.is_wp == 1 ? `<button class="btn btn-sm btn-outline" onclick="showComponentsModal(${s.id})">组件</button>` : ''}
                        <button class="btn btn-sm btn-primary" onclick="scanSidesite(${s.id})">扫描</button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    document.getElementById('sidesite-list').innerHTML = `
        <table class="table">
            <thead><tr>${headers}</tr></thead>
            <tbody>${rows}</tbody>
        </table>
    `;
}

function filterSidesites(filter) {
    currentFilter = filter;
    document.querySelectorAll('.tabs .tab-item').forEach(el => el.classList.remove('active'));
    event.target.classList.add('active');
    loadSidesites(1);
}

async function scanAsset() {
    try {
        await api.post('/api/assets/' + assetId + '/scan', { scan_type: 2, priority: 10 });
        toast.success('扫描任务已创建');
        setTimeout(loadAssetInfo, 1000);
    } catch (error) {
        toast.error(error.message);
    }
}

async function scanSidesite(id) {
    try {
        await api.post('/api/sidesites/' + id + '/scan');
        toast.success('扫描完成');
        loadSidesites(currentPage);
    } catch (error) {
        toast.error(error.message);
    }
}

async function batchScanSidesites() {
    try {
        const result = await api.post('/api/sidesites/batch-scan', { asset_id: assetId });
        toast.success(`扫描完成: ${result.success}成功, ${result.failed}失败`);
        loadAssetInfo();
    } catch (error) {
        toast.error(error.message);
    }
}

function deleteAsset() {
    modal.confirm('确定要删除这个资产吗？所有关联的旁站也将被删除。', async () => {
        try {
            await api.delete('/api/assets/' + assetId);
            toast.success('资产已删除');
            window.location.href = '/projects/' + assetData.project_id;
        } catch (error) {
            toast.error(error.message);
        }
    });
}

async function showComponentsModal(sidesiteId) {
    try {
        const sidesite = await api.get('/api/sidesites/' + sidesiteId);

        let componentsHtml = '<div class="text-muted">暂无组件</div>';
        if (sidesite.components && sidesite.components.length > 0) {
            componentsHtml = '<ul style="list-style: none; padding: 0;">' +
                sidesite.components.map(c => `<li style="padding: 8px 0; border-bottom: 1px solid #eee;">${c}</li>`).join('') +
                '</ul>';
        }

        modal.open({
            title: '组件列表 - ' + sidesite.domain,
            content: componentsHtml,
            footer: '<button class="btn btn-outline" onclick="modal.close()">关闭</button>',
        });
    } catch (error) {
        toast.error(error.message);
    }
}

// 初始加载
loadAssetInfo();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/main.php';
?>
