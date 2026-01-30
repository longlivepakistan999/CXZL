<?php
$pageTitle = '系统设置';
$currentPage = 'settings';
ob_start();
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">API配置</h3>
    </div>
    <div class="card-body">
        <form id="api-form">
            <div class="form-group">
                <label class="form-label">ViewDNS API Key</label>
                <div style="display: flex; gap: 8px;">
                    <input type="password" class="form-control" name="viewdns_api_key" id="viewdns_api_key" placeholder="输入API Key">
                    <button type="button" class="btn btn-outline" onclick="testViewDns()">测试连接</button>
                </div>
                <small class="text-muted">用于旁站查询,从 viewdns.info 获取</small>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">CF IP段</h3>
    </div>
    <div class="card-body">
        <div id="cf-stats">
            <div class="loading"><div class="spinner"></div></div>
        </div>
        <button class="btn btn-primary mt-2" onclick="updateCfIps()">手动更新</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">扫描配置</h3>
    </div>
    <div class="card-body">
        <form id="scan-form">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
                <div class="form-group">
                    <label class="form-label">并发数 (同时扫描任务数)</label>
                    <input type="number" class="form-control" name="concurrent_tasks" id="concurrent_tasks" min="1" max="100">
                </div>
                <div class="form-group">
                    <label class="form-label">ViewDNS限流 (次/秒)</label>
                    <input type="number" class="form-control" name="viewdns_rate_limit" id="viewdns_rate_limit" min="1" max="100">
                </div>
                <div class="form-group">
                    <label class="form-label">WP检测间隔 (秒)</label>
                    <input type="number" class="form-control" name="wp_check_interval" id="wp_check_interval" min="0" max="60">
                </div>
                <div class="form-group">
                    <label class="form-label">扫描超时 (秒)</label>
                    <input type="number" class="form-control" name="scan_timeout" id="scan_timeout" min="5" max="300">
                </div>
                <div class="form-group">
                    <label class="form-label">失败重试次数</label>
                    <input type="number" class="form-control" name="retry_count" id="retry_count" min="0" max="10">
                </div>
                <div class="form-group">
                    <label class="form-label">重试间隔 (秒)</label>
                    <input type="number" class="form-control" name="retry_interval" id="retry_interval" min="1" max="60">
                </div>
                <div class="form-group">
                    <label class="form-label">重复扫描保护间隔 (分钟)</label>
                    <input type="number" class="form-control" name="rescan_interval" id="rescan_interval" min="0" max="1440">
                </div>
            </div>
            <button type="button" class="btn btn-primary mt-2" onclick="saveSettings()">保存设置</button>
        </form>
    </div>
</div>

<script>
async function loadSettings() {
    try {
        const settings = await api.get('/api/settings');

        // 填充表单
        document.getElementById('viewdns_api_key').placeholder = settings.viewdns_api_key_masked || '未配置';
        document.getElementById('concurrent_tasks').value = settings.concurrent_tasks;
        document.getElementById('viewdns_rate_limit').value = settings.viewdns_rate_limit;
        document.getElementById('wp_check_interval').value = settings.wp_check_interval;
        document.getElementById('scan_timeout').value = settings.scan_timeout;
        document.getElementById('retry_count').value = settings.retry_count;
        document.getElementById('retry_interval').value = settings.retry_interval;
        document.getElementById('rescan_interval').value = settings.rescan_interval;

        // CF IP统计
        const cfStats = settings.cf_ip_stats;
        document.getElementById('cf-stats').innerHTML = `
            <div style="display: flex; gap: 24px;">
                <div>
                    <strong>IPv4段:</strong> ${cfStats.v4} 条
                </div>
                <div>
                    <strong>IPv6段:</strong> ${cfStats.v6} 条
                </div>
                <div>
                    <strong>上次更新:</strong> ${cfStats.updated_at || '从未更新'}
                </div>
            </div>
        `;

    } catch (error) {
        toast.error('加载设置失败: ' + error.message);
    }
}

async function saveSettings() {
    const data = {};

    const viewdnsKey = document.getElementById('viewdns_api_key').value;
    if (viewdnsKey) {
        data.viewdns_api_key = viewdnsKey;
    }

    data.concurrent_tasks = document.getElementById('concurrent_tasks').value;
    data.viewdns_rate_limit = document.getElementById('viewdns_rate_limit').value;
    data.wp_check_interval = document.getElementById('wp_check_interval').value;
    data.scan_timeout = document.getElementById('scan_timeout').value;
    data.retry_count = document.getElementById('retry_count').value;
    data.retry_interval = document.getElementById('retry_interval').value;
    data.rescan_interval = document.getElementById('rescan_interval').value;

    try {
        await api.put('/api/settings', data);
        toast.success('设置已保存');
        loadSettings();
    } catch (error) {
        toast.error(error.message);
    }
}

async function testViewDns() {
    try {
        await api.post('/api/settings/test-viewdns');
        toast.success('API连接正常');
    } catch (error) {
        toast.error(error.message);
    }
}

async function updateCfIps() {
    try {
        const result = await api.post('/api/settings/update-cf-ips');
        toast.success('CF IP段已更新');
        loadSettings();
    } catch (error) {
        toast.error(error.message);
    }
}

// 初始加载
loadSettings();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/main.php';
?>
