<?php
$pageTitle = '组件管理';
$currentPage = 'components';
ob_start();
?>

<div class="stats-grid" id="stats-grid">
    <div class="stat-card">
        <div class="stat-value" id="stat-total">-</div>
        <div class="stat-label">组件总数</div>
    </div>
    <div class="stat-card success">
        <div class="stat-value" id="stat-assets">-</div>
        <div class="stat-label">使用组件的资产</div>
    </div>
    <div class="stat-card info">
        <div class="stat-value" id="stat-sidesites">-</div>
        <div class="stat-label">使用组件的旁站</div>
    </div>
</div>

<div class="card">
    <div class="filter-bar">
        <div class="search-box">
            <input type="text" class="form-control" id="search-input" placeholder="搜索组件名称...">
            <button class="btn btn-primary" onclick="loadComponents()">搜索</button>
        </div>
        <div class="btn-group" style="margin-left: auto;">
            <select class="form-control form-select" id="filter-project" onchange="loadComponents()">
                <option value="">全部项目</option>
            </select>
            <button class="btn btn-outline" onclick="refreshStats()">刷新统计</button>
        </div>
    </div>

    <div id="component-list">
        <div class="loading"><div class="spinner"></div></div>
    </div>

    <div id="pagination"></div>
</div>

<script>
let currentPage = 1;
const perPage = 20;

// 常见插件名称映射
const pluginNames = {
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
    'wordfence/v1': 'Wordfence',
    'redirection/v1': 'Redirection',
    'flavor/developer': 'flavor',
};

async function loadProjects() {
    try {
        const data = await api.get('/api/projects', { per_page: 1000 });
        const select = document.getElementById('filter-project');
        data.items.forEach(p => {
            const option = document.createElement('option');
            option.value = p.id;
            option.textContent = p.name;
            select.appendChild(option);
        });
    } catch (error) {
        console.error('加载项目列表失败', error);
    }
}

async function loadComponents(page = 1) {
    currentPage = page;

    const params = {
        page,
        per_page: perPage,
        search: document.getElementById('search-input').value,
        project_id: document.getElementById('filter-project').value,
    };

    try {
        const data = await api.get('/api/components', params);
        renderComponentList(data.items);
        document.getElementById('pagination').innerHTML = renderPagination(data.pagination, 'loadComponents');

        // 更新统计
        let totalAssets = 0, totalSidesites = 0;
        data.items.forEach(c => {
            totalAssets += c.asset_count;
            totalSidesites += c.sidesite_count;
        });

        document.getElementById('stat-total').textContent = formatNumber(data.pagination.total);
        document.getElementById('stat-assets').textContent = formatNumber(totalAssets);
        document.getElementById('stat-sidesites').textContent = formatNumber(totalSidesites);

    } catch (error) {
        toast.error('加载组件列表失败: ' + error.message);
    }
}

function renderComponentList(components) {
    if (!components || components.length === 0) {
        document.getElementById('component-list').innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">&#128268;</div>
                <div class="empty-state-title">暂无组件数据</div>
                <p class="text-muted">请先扫描WP站点以获取组件信息</p>
            </div>
        `;
        return;
    }

    const html = `
        <table class="table">
            <thead>
                <tr>
                    <th>组件名称</th>
                    <th>插件/主题</th>
                    <th>资产数</th>
                    <th>旁站数</th>
                    <th>总计</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                ${components.map(c => `
                    <tr>
                        <td><code>${c.component_name}</code></td>
                        <td>${c.plugin_name || pluginNames[c.component_name] || '<span class="text-muted">未知</span>'}</td>
                        <td>${formatNumber(c.asset_count)}</td>
                        <td>${formatNumber(c.sidesite_count)}</td>
                        <td><strong>${formatNumber(c.total_count)}</strong></td>
                        <td>
                            <div class="table-actions">
                                <button class="btn btn-sm btn-outline" onclick="showAssets('${encodeURIComponent(c.component_name)}')">查看资产</button>
                                <button class="btn btn-sm btn-outline" onclick="showSidesites('${encodeURIComponent(c.component_name)}')">查看旁站</button>
                                <div class="dropdown" style="display: inline-block;">
                                    <button class="btn btn-sm btn-primary" onclick="toggleDropdown(this)">导出 ▾</button>
                                    <div class="dropdown-menu">
                                        <a href="#" onclick="exportAssets('${encodeURIComponent(c.component_name)}'); return false;">导出资产域名</a>
                                        <a href="#" onclick="exportSidesites('${encodeURIComponent(c.component_name)}'); return false;">导出旁站域名</a>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    document.getElementById('component-list').innerHTML = html;
}

async function showAssets(component) {
    const projectId = document.getElementById('filter-project').value;
    let url = `/api/components/${component}/assets?per_page=50`;
    if (projectId) url += `&project_id=${projectId}`;

    try {
        const data = await api.get(url);

        let content = '';
        if (data.items.length === 0) {
            content = '<p class="text-muted">暂无资产</p>';
        } else {
            content = `
                <table class="table">
                    <thead>
                        <tr>
                            <th>域名</th>
                            <th>项目</th>
                            <th>IP</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.items.map(a => `
                            <tr>
                                <td><a href="/assets/${a.id}" target="_blank">${a.domain}</a></td>
                                <td>${a.project_name || '-'}</td>
                                <td>${a.ip || '-'}</td>
                                <td>
                                    <a href="https://${a.domain}" target="_blank" class="btn btn-sm btn-outline">访问</a>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
                ${data.pagination.total > 50 ? `<p class="text-muted">显示前50条，共${data.pagination.total}条</p>` : ''}
            `;
        }

        modal.open({
            title: `使用 ${decodeURIComponent(component)} 的资产`,
            content,
            size: 'lg',
            footer: '<button class="btn btn-outline" onclick="modal.close()">关闭</button>',
        });
    } catch (error) {
        toast.error(error.message);
    }
}

async function showSidesites(component) {
    const projectId = document.getElementById('filter-project').value;
    let url = `/api/components/${component}/sidesites?per_page=50`;
    if (projectId) url += `&project_id=${projectId}`;

    try {
        const data = await api.get(url);

        let content = '';
        if (data.items.length === 0) {
            content = '<p class="text-muted">暂无旁站</p>';
        } else {
            content = `
                <table class="table">
                    <thead>
                        <tr>
                            <th>旁站域名</th>
                            <th>所属资产</th>
                            <th>项目</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.items.map(s => `
                            <tr>
                                <td>${s.domain}</td>
                                <td>${s.asset_domain || '-'}</td>
                                <td>${s.project_name || '-'}</td>
                                <td>
                                    <a href="https://${s.domain}" target="_blank" class="btn btn-sm btn-outline">访问</a>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
                ${data.pagination.total > 50 ? `<p class="text-muted">显示前50条，共${data.pagination.total}条</p>` : ''}
            `;
        }

        modal.open({
            title: `使用 ${decodeURIComponent(component)} 的旁站`,
            content,
            size: 'lg',
            footer: '<button class="btn btn-outline" onclick="modal.close()">关闭</button>',
        });
    } catch (error) {
        toast.error(error.message);
    }
}

function exportAssets(component) {
    const projectId = document.getElementById('filter-project').value;
    let url = `/api/components/${component}/export-assets?format=txt`;
    if (projectId) url += `&project_id=${projectId}`;
    window.open(url, '_blank');
}

function exportSidesites(component) {
    const projectId = document.getElementById('filter-project').value;
    let url = `/api/components/${component}/export-sidesites?format=txt`;
    if (projectId) url += `&project_id=${projectId}`;
    window.open(url, '_blank');
}

async function refreshStats() {
    const projectId = document.getElementById('filter-project').value;

    try {
        toast.info('正在刷新统计...');
        const result = await api.post('/api/components/refresh', { project_id: projectId || 0 });
        toast.success(`统计已刷新，共${result.count}个组件`);
        loadComponents(currentPage);
    } catch (error) {
        toast.error(error.message);
    }
}

// 初始加载
document.addEventListener('DOMContentLoaded', function() {
    loadProjects();
    loadComponents();

    document.getElementById('search-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') loadComponents();
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/main.php';
?>
