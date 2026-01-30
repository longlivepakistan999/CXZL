<?php
$pageTitle = '仪表盘';
$currentPage = 'dashboard';
ob_start();
?>

<div class="stats-grid" id="stats-container">
    <div class="loading"><div class="spinner"></div></div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">扫描队列状态</h3>
    </div>
    <div class="card-body" id="queue-status">
        <div class="loading"><div class="spinner"></div></div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">组件使用排行 TOP 10</h3>
        </div>
        <div class="card-body" id="top-components">
            <div class="loading"><div class="spinner"></div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">项目排行</h3>
        </div>
        <div class="card-body" id="project-rankings">
            <div class="loading"><div class="spinner"></div></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">最近活动</h3>
    </div>
    <div class="card-body" id="recent-activities">
        <div class="loading"><div class="spinner"></div></div>
    </div>
</div>

<script>
async function loadDashboard() {
    try {
        const data = await api.get('/api/dashboard');

        // 渲染统计卡片
        const stats = data.stats;
        document.getElementById('stats-container').innerHTML = `
            <div class="stat-card">
                <div class="stat-value">${formatNumber(stats.project_count)}</div>
                <div class="stat-label">项目总数</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">${formatNumber(stats.asset_count)}</div>
                <div class="stat-label">资产总数</div>
            </div>
            <div class="stat-card info">
                <div class="stat-value">${formatNumber(stats.cf_count)}</div>
                <div class="stat-label">CF资产</div>
            </div>
            <div class="stat-card success">
                <div class="stat-value">${formatNumber(stats.wp_count)}</div>
                <div class="stat-label">WP资产</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-value">${formatNumber(stats.sidesite_count)}</div>
                <div class="stat-label">旁站总数</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">${formatNumber(stats.wp_sidesite_count)}</div>
                <div class="stat-label">WP旁站</div>
            </div>
        `;

        // 渲染队列状态
        const queue = data.scan_status;
        document.getElementById('queue-status').innerHTML = `
            <div class="stats-grid" style="margin-bottom: 0;">
                <div class="stat-card warning">
                    <div class="stat-value">${formatNumber(queue.pending)}</div>
                    <div class="stat-label">待扫描</div>
                </div>
                <div class="stat-card info">
                    <div class="stat-value">${formatNumber(queue.running)}</div>
                    <div class="stat-label">扫描中</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-value">${formatNumber(queue.success)}</div>
                    <div class="stat-label">已完成</div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-value">${formatNumber(queue.failed)}</div>
                    <div class="stat-label">失败</div>
                </div>
            </div>
        `;

        // 渲染组件排行
        if (data.top_components && data.top_components.length > 0) {
            const componentsHtml = data.top_components.map((item, index) => `
                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
                    <span>${index + 1}. ${item.component_name}</span>
                    <span class="text-muted">${formatNumber(parseInt(item.asset_count) + parseInt(item.sidesite_count))} 次使用</span>
                </div>
            `).join('');
            document.getElementById('top-components').innerHTML = componentsHtml;
        } else {
            document.getElementById('top-components').innerHTML = '<div class="text-muted">暂无数据</div>';
        }

        // 渲染项目排行
        if (data.project_rankings && data.project_rankings.length > 0) {
            const rankingsHtml = data.project_rankings.map((item, index) => `
                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
                    <a href="/projects/${item.id}">${index + 1}. ${item.name}</a>
                    <span>
                        <span class="badge badge-gray">${formatNumber(item.asset_count)} 资产</span>
                        <span class="badge badge-success">${item.wp_ratio || 0}% WP</span>
                    </span>
                </div>
            `).join('');
            document.getElementById('project-rankings').innerHTML = rankingsHtml;
        } else {
            document.getElementById('project-rankings').innerHTML = '<div class="text-muted">暂无数据</div>';
        }

        // 渲染最近活动
        const activities = data.recent_activities;
        let activitiesHtml = '<div class="tabs">';
        activitiesHtml += '<div class="tab-item active" onclick="showActivityTab(\'projects\')">最近项目</div>';
        activitiesHtml += '<div class="tab-item" onclick="showActivityTab(\'assets\')">最近资产</div>';
        activitiesHtml += '<div class="tab-item" onclick="showActivityTab(\'scans\')">最近扫描</div>';
        activitiesHtml += '<div class="tab-item" onclick="showActivityTab(\'failures\')">最近失败</div>';
        activitiesHtml += '</div>';

        activitiesHtml += `<div id="activity-projects" class="activity-content">
            ${activities.recent_projects.map(p => `
                <div style="padding: 8px 0; border-bottom: 1px solid #eee;">
                    <a href="/projects/${p.id}">${p.name}</a>
                    <span class="text-muted" style="float: right;">${formatDate(p.created_at)}</span>
                </div>
            `).join('') || '<div class="text-muted">暂无数据</div>'}
        </div>`;

        activitiesHtml += `<div id="activity-assets" class="activity-content" style="display: none;">
            ${activities.recent_assets.map(a => `
                <div style="padding: 8px 0; border-bottom: 1px solid #eee;">
                    <span>${a.domain}</span>
                    <span class="badge badge-gray">${a.project_name}</span>
                    <span class="text-muted" style="float: right;">${formatDate(a.imported_at)}</span>
                </div>
            `).join('') || '<div class="text-muted">暂无数据</div>'}
        </div>`;

        activitiesHtml += `<div id="activity-scans" class="activity-content" style="display: none;">
            ${activities.recent_scans.map(s => `
                <div style="padding: 8px 0; border-bottom: 1px solid #eee;">
                    <span>${s.domain}</span>
                    <span class="badge ${s.status == 2 ? 'badge-success' : 'badge-danger'}">${s.status == 2 ? '成功' : '失败'}</span>
                    <span class="text-muted" style="float: right;">${formatDate(s.finished_at)}</span>
                </div>
            `).join('') || '<div class="text-muted">暂无数据</div>'}
        </div>`;

        activitiesHtml += `<div id="activity-failures" class="activity-content" style="display: none;">
            ${activities.recent_failures.map(f => `
                <div style="padding: 8px 0; border-bottom: 1px solid #eee;">
                    <span>${f.domain}</span>
                    <span class="text-danger">${f.failure_reason}</span>
                    <span class="text-muted" style="float: right;">${formatDate(f.created_at)}</span>
                </div>
            `).join('') || '<div class="text-muted">暂无数据</div>'}
        </div>`;

        document.getElementById('recent-activities').innerHTML = activitiesHtml;

    } catch (error) {
        toast.error('加载仪表盘数据失败: ' + error.message);
    }
}

function showActivityTab(tab) {
    document.querySelectorAll('.activity-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tabs .tab-item').forEach(el => el.classList.remove('active'));

    document.getElementById('activity-' + tab).style.display = 'block';
    event.target.classList.add('active');
}

// 页面加载
loadDashboard();

// 每30秒刷新一次
setInterval(loadDashboard, 30000);
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/main.php';
?>
