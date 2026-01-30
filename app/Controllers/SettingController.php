<?php
namespace App\Controllers;

use App\Services\ViewDnsService;
use App\Services\CloudflareService;

/**
 * 设置控制器
 */
class SettingController extends BaseController
{
    /**
     * 获取所有设置
     */
    public function index(): void
    {
        $settings = [
            'viewdns_api_key' => setting('viewdns_api_key', ''),
            'concurrent_tasks' => (int)setting('concurrent_tasks', 50),
            'viewdns_rate_limit' => (int)setting('viewdns_rate_limit', 10),
            'wp_check_interval' => (int)setting('wp_check_interval', 1),
            'scan_timeout' => (int)setting('scan_timeout', 30),
            'retry_count' => (int)setting('retry_count', 3),
            'retry_interval' => (int)setting('retry_interval', 5),
            'rescan_interval' => (int)setting('rescan_interval', 5),
            'cf_ip_stats' => CloudflareService::getIpRangeStats(),
        ];

        // 隐藏API Key中间部分
        if ($settings['viewdns_api_key']) {
            $key = $settings['viewdns_api_key'];
            $len = strlen($key);
            if ($len > 8) {
                $settings['viewdns_api_key_masked'] = substr($key, 0, 4) . str_repeat('*', $len - 8) . substr($key, -4);
            } else {
                $settings['viewdns_api_key_masked'] = str_repeat('*', $len);
            }
        }

        success($settings);
    }

    /**
     * 更新设置
     */
    public function update(): void
    {
        $allowedSettings = [
            'viewdns_api_key',
            'concurrent_tasks',
            'viewdns_rate_limit',
            'wp_check_interval',
            'scan_timeout',
            'retry_count',
            'retry_interval',
            'rescan_interval',
        ];

        $updated = [];

        foreach ($allowedSettings as $key) {
            $value = input($key);
            if ($value !== null) {
                updateSetting($key, $value);
                $updated[] = $key;
            }
        }

        if (empty($updated)) {
            error('没有需要更新的设置');
        }

        success(['updated' => $updated], '设置已更新');
    }

    /**
     * 测试ViewDNS连接
     */
    public function testViewDns(): void
    {
        $result = ViewDnsService::testConnection();

        if ($result['success']) {
            success(null, 'API连接正常');
        } else {
            error($result['error']);
        }
    }

    /**
     * 更新CF IP段
     */
    public function updateCfIps(): void
    {
        $result = CloudflareService::updateIpRanges();

        if (empty($result['errors'])) {
            success([
                'v4_count' => $result['v4'],
                'v6_count' => $result['v6'],
            ], "CF IP段已更新: IPv4 {$result['v4']}条, IPv6 {$result['v6']}条");
        } else {
            error(implode('; ', $result['errors']));
        }
    }
}
