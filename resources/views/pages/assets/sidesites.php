<?php
$assetId = $params['id'] ?? 0;
$pageTitle = '资产旁站';
$currentPage = 'projects';
ob_start();
?>

<div id="asset-info" class="card mb-3">
    <div class="loading"><div class="spinner"></div></div>
</div>

<div class="stats-grid" id="sidesite-stats">
    <div class="loading"><div class="spinner"></div></div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">旁站列表</h3>
        <div class="btn-group">
            <button class="btn btn-outline" onclick="batchScanSidesites()">批量扫描WP</button>
            <div class="dropdown" style="display: inline-block;">
                <button class="btn btn-primary" onclick="toggleDropdown(this)">导出 ▾</button>
                <div class="dropdown-menu">
                    <a href="#" onclick="exportSidesites('all'); return false;">导出全部旁站</a>
                    <a href="#" onclick="exportSidesites('wp'); return false;">导出WP旁站</a>
                    <a href="#" onclick="exportSidesites('non_wp'); return false;">导出非WP旁站</a>
                </div>
            </div>
        </div>
    </div>

    <div class="filter-bar">
        <select class="form-control form-select" id="filter-wp" onchange="loadSidesites()" style="width: 150px;">
            <option value="">全部旁站</option>
            <option value="1">WP旁站</option>
            <option value="0">非WP旁站</option>
            <option value="-1">未检测</option>
        </select>
        <select class="form-control form-select" id="filter-component" onchange="loadSidesites()" style="width: 200px;">
            <option value="">全部组件</option>
        </select>
        <div class="search-box">
            <input type="text" class="form-control" id="search-input" placeholder="搜索域名...">
            <button class="btn btn-outline" onclick="loadSidesites()">搜索</button>
        </div>
    </div>

    <div id="sidesite-list">
        <div class="loading"><div class="spinner"></div></div>
    </div>

    <div id="pagination"></div>
</div>

<!-- 插件/主题统计表格 -->
<div class="card" id="component-stats-card" style="display: none;">
    <div class="card-header">
        <h3 class="card-title">WP插件/主题统计</h3>
        <span class="text-muted" id="wp-coverage-info"></span>
    </div>
    <div class="card-body">
        <table class="table" id="component-table">
            <thead>
                <tr>
                    <th>插件/主题</th>
                    <th>命名空间</th>
                    <th>使用数量</th>
                    <th>WP占比</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody id="component-list"></tbody>
        </table>
    </div>
</div>

<script>
const assetId = <?= $assetId ?>;
let currentPage = 1;
const perPage = 20;
let assetData = null;
let componentData = [];

const pluginNameMap = {
    'yoast/v1': 'Yoast SEO',
    'elementor/v1': 'Elementor',
    'wpml/v1': 'WPML',
    'wc/v3': 'WooCommerce',
    'wc/v2': 'WooCommerce',
    'wc/v1': 'WooCommerce',
    'jetpack/v4': 'Jetpack',
    'acf/v3': 'Advanced Custom Fields',
    'rankmath/v1': 'Rank Math',
    'contact-form-7/v1': 'Contact Form 7',
    'wp-graphql/v1': 'WPGraphQL',
    'tribe/events/v1': 'The Events Calendar',
    'meow/v1': 'Meow Apps',
    'redirection/v1': 'Redirection',
    'wordfence/v1': 'Wordfence',
};

async function loadAssetInfo() {
    try {
        assetData = await api.get('/api/assets/' + assetId);

        document.getElementById('asset-info').innerHTML = `
            <div class="card-body">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <h2 style="margin-bottom: 8px;">
                            <a href="${assetData.protocol || 'https'}://${assetData.domain}" target="_blank">${assetData.domain}</a>
                        </h2>
                        <p class="text-muted">
                            IP: ${assetData.ip || '-'} |
                            ${assetData.is_cf == 1 ? '<span class="badge badge-info">CF</span>' : '非CF'} |
                            ${assetData.is_wp == 1 ? '<span class="badge badge-success">WP</span>' : '非WP'}
                            ${assetData.component_count > 0 ? ' | 组件: ' + assetData.component_count : ''}
                        </p>
                        <p class="text-muted" style="font-size: 12px;">
                            所属项目: <a href="/projects/${assetData.project_id}">${assetData.project_name || '未知'}</a>
                        </p>
                    </div>
                    <div class="btn-group">
                        <a href="/assets/${assetId}" class="btn btn-outline">资产详情</a>
                        <a href="/projects/${assetData.project_id}" class="btn btn-outline">返回项目</a>
                    </div>
                </div>
            </div>
        `;

        // 加载旁站统计
        await loadSidesiteStats();

    } catch (error) {
        toast.error('加载资产信息失败: ' + error.message);
    }
}

async function loadSidesiteStats() {
    try {
        const stats = await api.get('/api/sidesites/asset/' + assetId + '/stats');

        document.getElementById('sidesite-stats').innerHTML = `
            <div class="stat-card">
                <div class="stat-value">${formatNumber(stats.total)}</div>
                <div class="stat-label">旁站总数</div>
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
        `;

        // 填充组件筛选
        componentData = stats.components || [];
        const wpCount = stats.wp_count || 0;
        const componentSelect = document.getElementById('filter-component');
        componentSelect.innerHTML = '<option value="">全部组件</option>';
        componentData.forEach(c => {
            const name = pluginNameMap[c.name] || c.name;
            componentSelect.innerHTML += `<option value="${c.name}">${name} (${c.count})</option>`;
        });

        // 显示插件/主题统计表格
        if (componentData.length > 0) {
            document.getElementById('component-stats-card').style.display = 'block';
            document.getElementById('wp-coverage-info').textContent = `共 ${wpCount} 个WP旁站`;

            document.getElementById('component-list').innerHTML = componentData.map(c => {
                const name = pluginNameMap[c.name] || c.plugin_name || null;
                const percentage = wpCount > 0 ? ((c.count / wpCount) * 100).toFixed(1) : 0;
                return `
                    <tr>
                        <td>
                            <strong>${name || '<span class="text-muted">未知插件</span>'}</strong>
                        </td>
                        <td><code>${c.name}</code></td>
                        <td><strong>${c.count}</strong></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 100px; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                                    <div style="width: ${percentage}%; height: 100%; background: #3b82f6;"></div>
                                </div>
                                <span>${percentage}%</span>
                            </div>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline" onclick="filterByComponent('${c.name}')">查看</button>
                            <button class="btn btn-sm btn-primary" onclick="exportByComponent('${c.name}')">导出</button>
                        </td>
                    </tr>
                `;
            }).join('');
        } else {
            document.getElementById('component-stats-card').style.display = 'none';
        }

    } catch (error) {
        console.error('加载旁站统计失败:', error);
    }
}

async function loadSidesites(page = 1) {
    currentPage = page;

    const params = {
        asset_id: assetId,
        page,
        per_page: perPage,
        search: document.getElementById('search-input').value,
    };

    const wpFilter = document.getElementById('filter-wp').value;
    if (wpFilter !== '') params.is_wp = wpFilter;

    const componentFilter = document.getElementById('filter-component').value;
    if (componentFilter) params.component = componentFilter;

    try {
        const data = await api.get('/api/sidesites', params);
        renderSidesiteList(data.items);
        document.getElementById('pagination').innerHTML = renderPagination(data.pagination, 'loadSidesites');
    } catch (error) {
        toast.error('加载旁站列表失败: ' + error.message);
    }
}

function renderSidesiteList(sidesites) {
    if (!sidesites || sidesites.length === 0) {
        document.getElementById('sidesite-list').innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">&#128269;</div>
                <div class="empty-state-title">暂无旁站</div>
            </div>
        `;
        return;
    }

    const assetIp = (assetData?.ip || '').trim();

    const html = `
        <table class="table">
            <thead>
                <tr>
                    <th>域名</th>
                    <th>协议</th>
                    <th>IP地址</th>
                    <th>IP匹配</th>
                    <th>WP状态</th>
                    <th>扫描状态</th>
                    <th>组件数</th>
                    <th>组件/插件</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                ${sidesites.map(s => {
                    const sideIp = (s.ip || '').trim();
                    const ipMatch = sideIp && assetIp && sideIp === assetIp;
                    return `
                    <tr>
                        <td><a href="${s.protocol || 'https'}://${s.domain}" target="_blank">${s.domain}</a></td>
                        <td>${s.protocol || 'https'}</td>
                        <td><code>${sideIp || '-'}</code></td>
                        <td>
                            ${!sideIp ? '<span class="badge badge-gray">未知</span>' :
                              ipMatch ? '<span class="badge badge-success">相同</span>' :
                              '<span class="badge badge-warning">不同</span>'}
                        </td>
                        <td>
                            ${s.is_wp === 1 ? '<span class="badge badge-success">WP</span>' :
                              s.is_wp === 0 ? '<span class="badge badge-gray">非WP</span>' :
                              '<span class="badge badge-warning">未检测</span>'}
                        </td>
                        <td>
                            ${s.scan_status === 3 ? '<span class="badge badge-success">成功</span>' :
                              s.scan_status === 4 ? '<span class="badge badge-danger" title="' + (s.scan_error || '') + '">失败</span>' :
                              '<span class="badge badge-gray">-</span>'}
                        </td>
                        <td>${s.component_count || 0}</td>
                        <td style="max-width: 300px;">
                            ${s.components && s.components.length > 0 ?
                                s.components.map(c => `<span class="badge badge-info" style="margin: 2px;">${pluginNameMap[c] || c}</span>`).join('') :
                                '-'}
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="scanSidesite(${s.id})">扫描</button>
                        </td>
                    </tr>
                `}).join('')}
            </tbody>
        </table>
    `;

    document.getElementById('sidesite-list').innerHTML = html;
}

async function scanSidesite(id) {
    try {
        const result = await api.post('/api/sidesites/' + id + '/scan');
        toast.success('扫描完成');
        loadSidesites(currentPage);
        loadSidesiteStats();
    } catch (error) {
        toast.error(error.message);
    }
}

async function batchScanSidesites() {
    modal.confirm('确定要扫描所有旁站的WP状态吗？这可能需要一些时间。', async () => {
        try {
            toast.info('开始批量扫描...');
            const result = await api.post('/api/sidesites/batch-scan', { asset_id: assetId });
            toast.success(result.message);
            loadSidesites(currentPage);
            loadSidesiteStats();
        } catch (error) {
            toast.error(error.message);
        }
    });
}

function filterByComponent(component) {
    document.getElementById('filter-component').value = component;
    document.getElementById('filter-wp').value = '1';
    loadSidesites();
}

function exportSidesites(type) {
    const component = document.getElementById('filter-component').value;
    let url = `/api/sidesites/asset/${assetId}/export?type=${type}`;
    if (component) url += `&component=${encodeURIComponent(component)}`;
    window.open(url, '_blank');
}

function exportByComponent(component) {
    const url = `/api/sidesites/asset/${assetId}/export?type=wp&component=${encodeURIComponent(component)}`;
    window.open(url, '_blank');
}

// 初始加载
document.addEventListener('DOMContentLoaded', async function() {
    // 先加载资产信息，确保 assetData 可用
    await loadAssetInfo();
    // 然后加载旁站列表
    loadSidesites();

    // 搜索回车
    document.getElementById('search-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') loadSidesites();
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/main.php';
?>
