<?php
namespace App\Services;

use App\Models\Asset;
use App\Models\Sidesite;
use App\Models\ScanTask;
use App\Models\FailureLog;
use App\Models\Project;

/**
 * 扫描器服务 - 核心扫描流程
 */
class ScannerService
{
    /**
     * 执行完整扫描
     */
    public static function fullScan(int $assetId): array
    {
        $asset = Asset::find($assetId);
        if (!$asset) {
            return ['success' => false, 'error' => '资产不存在'];
        }

        $result = [
            'success' => true,
            'ip' => null,
            'is_cf' => false,
            'is_wp' => false,
            'components' => [],
            'sidesites' => 0,
            'error' => null,
        ];

        try {
            // 1. DNS解析
            logInfo("开始扫描: {$asset['domain']}");
            $dnsResult = DnsService::resolve($asset['domain']);

            if (!$dnsResult['success']) {
                throw new \Exception('DNS解析失败: ' . $dnsResult['error']);
            }

            $result['ip'] = $dnsResult['ip'];

            // 2. CF检测
            $result['is_cf'] = CloudflareService::isCloudflare($dnsResult['ip']);
            logInfo("CF检测: {$asset['domain']}", ['ip' => $dnsResult['ip'], 'is_cf' => $result['is_cf']]);

            // 3. 如果非CF,查询旁站
            if (!$result['is_cf']) {
                logInfo("开始查询旁站: {$asset['domain']}", ['ip' => $dnsResult['ip']]);
                $sidesiteResult = ViewDnsService::reverseLookup($dnsResult['ip']);

                if ($sidesiteResult['success'] && !empty($sidesiteResult['domains'])) {
                    // 过滤掉当前域名
                    $sidesiteDomains = array_filter($sidesiteResult['domains'], function ($d) use ($asset) {
                        return $d !== $asset['domain'];
                    });

                    // 入库旁站
                    if (!empty($sidesiteDomains)) {
                        $result['sidesites'] = Sidesite::batchInsert(
                            $assetId,
                            $asset['project_id'],
                            $sidesiteDomains,
                            $dnsResult['ip']
                        );
                    }
                }
            }

            // 4. WP检测(本站)
            $protocol = $asset['protocol'] ?? 'https';
            $wpResult = WordPressService::detect($asset['domain'], $protocol);
            $result['is_wp'] = $wpResult['is_wp'];
            $result['components'] = $wpResult['components'];

            // 5. 如果是非CF,检测旁站的WP
            if (!$result['is_cf']) {
                static::scanSidesitesWp($assetId);
            }

            // 6. 更新资产
            Asset::updateScanResult($assetId, [
                'status' => Asset::STATUS_COMPLETED,
                'ip' => $result['ip'],
                'is_cf' => $result['is_cf'] ? 1 : 0,
                'is_wp' => $result['is_wp'] ? 1 : 0,
                'components' => $result['components'],
            ]);

            logInfo("扫描完成: {$asset['domain']}", $result);

        } catch (\Exception $e) {
            $result['success'] = false;
            $result['error'] = $e->getMessage();

            // 记录失败日志
            FailureLog::log(
                $asset['project_id'],
                $asset['domain'],
                static::classifyError($e->getMessage()),
                $e->getMessage(),
                $assetId
            );

            // 更新资产状态
            Asset::updateScanResult($assetId, [
                'status' => Asset::STATUS_FAILED,
                'error' => $e->getMessage(),
            ]);

            logError("扫描失败: {$asset['domain']}", ['error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * 仅刷新IP
     */
    public static function refreshIp(int $assetId): array
    {
        $asset = Asset::find($assetId);
        if (!$asset) {
            return ['success' => false, 'error' => '资产不存在'];
        }

        $dnsResult = DnsService::resolve($asset['domain']);

        if ($dnsResult['success']) {
            Asset::update($assetId, [
                'ip' => $dnsResult['ip'],
                'scanned_at' => date('Y-m-d H:i:s'),
            ]);
            return ['success' => true, 'ip' => $dnsResult['ip']];
        }

        return ['success' => false, 'error' => $dnsResult['error']];
    }

    /**
     * 仅刷新CF状态
     */
    public static function refreshCf(int $assetId): array
    {
        $asset = Asset::find($assetId);
        if (!$asset) {
            return ['success' => false, 'error' => '资产不存在'];
        }

        // 如果没有IP,先解析
        $ip = $asset['ip'];
        if (!$ip) {
            $dnsResult = DnsService::resolve($asset['domain']);
            if (!$dnsResult['success']) {
                return ['success' => false, 'error' => 'DNS解析失败'];
            }
            $ip = $dnsResult['ip'];
        }

        $isCf = CloudflareService::isCloudflare($ip);

        Asset::update($assetId, [
            'ip' => $ip,
            'is_cf' => $isCf ? 1 : 0,
            'scanned_at' => date('Y-m-d H:i:s'),
        ]);

        return ['success' => true, 'is_cf' => $isCf];
    }

    /**
     * 仅刷新旁站
     */
    public static function refreshSidesites(int $assetId): array
    {
        $asset = Asset::find($assetId);
        if (!$asset) {
            return ['success' => false, 'error' => '资产不存在'];
        }

        // CF站点不查旁站
        if ($asset['is_cf'] == 1) {
            return ['success' => true, 'sidesites' => 0, 'message' => 'CF站点跳过旁站查询'];
        }

        // 获取IP
        $ip = $asset['ip'];
        if (!$ip) {
            $dnsResult = DnsService::resolve($asset['domain']);
            if (!$dnsResult['success']) {
                return ['success' => false, 'error' => 'DNS解析失败'];
            }
            $ip = $dnsResult['ip'];
        }

        // 清除旧旁站
        Sidesite::deleteByAsset($assetId);

        // 查询新旁站
        $result = ViewDnsService::reverseLookup($ip);

        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error']];
        }

        // 过滤当前域名
        $domains = array_filter($result['domains'], function ($d) use ($asset) {
            return $d !== $asset['domain'];
        });

        // 入库
        $count = 0;
        if (!empty($domains)) {
            $count = Sidesite::batchInsert($assetId, $asset['project_id'], $domains, $ip);
        }

        Asset::update($assetId, ['scanned_at' => date('Y-m-d H:i:s')]);
        Asset::refreshSidesiteStats($assetId);

        return ['success' => true, 'sidesites' => $count];
    }

    /**
     * 仅刷新WP状态
     */
    public static function refreshWp(int $assetId): array
    {
        $asset = Asset::find($assetId);
        if (!$asset) {
            return ['success' => false, 'error' => '资产不存在'];
        }

        $protocol = $asset['protocol'] ?? 'https';
        $wpResult = WordPressService::detect($asset['domain'], $protocol);

        Asset::update($assetId, [
            'is_wp' => $wpResult['is_wp'] ? 1 : 0,
            'components' => $wpResult['is_wp'] ? json_encode($wpResult['components']) : null,
            'component_count' => count($wpResult['components']),
            'scanned_at' => date('Y-m-d H:i:s'),
        ]);

        // 同时更新旁站的WP检测
        if (!$asset['is_cf']) {
            static::scanSidesitesWp($assetId);
        }

        return [
            'success' => true,
            'is_wp' => $wpResult['is_wp'],
            'components' => $wpResult['components'],
        ];
    }

    /**
     * 扫描旁站的WP状态
     */
    public static function scanSidesitesWp(int $assetId): void
    {
        $sidesites = Sidesite::where(['asset_id' => $assetId], 1, 10000);
        $interval = (int)setting('wp_check_interval', 1);

        foreach ($sidesites as $sidesite) {
            try {
                $protocol = $sidesite['protocol'] ?? 'https';
                $wpResult = WordPressService::detect($sidesite['domain'], $protocol);
                Sidesite::updateWpResult(
                    $sidesite['id'],
                    $wpResult['is_wp'],
                    $wpResult['components']
                );
            } catch (\Exception $e) {
                Sidesite::markFailed($sidesite['id'], $e->getMessage());
            }

            if ($interval > 0) {
                sleep($interval);
            }
        }

        // 更新统计
        Asset::refreshSidesiteStats($assetId);
    }

    /**
     * 扫描单个旁站
     */
    public static function scanSidesite(int $sidesiteId): array
    {
        $sidesite = Sidesite::find($sidesiteId);
        if (!$sidesite) {
            return ['success' => false, 'error' => '旁站不存在'];
        }

        try {
            $protocol = $sidesite['protocol'] ?? 'https';
            $wpResult = WordPressService::detect($sidesite['domain'], $protocol);
            Sidesite::updateWpResult($sidesiteId, $wpResult['is_wp'], $wpResult['components']);

            return [
                'success' => true,
                'is_wp' => $wpResult['is_wp'],
                'components' => $wpResult['components'],
            ];
        } catch (\Exception $e) {
            Sidesite::markFailed($sidesiteId, $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 分类错误类型
     */
    private static function classifyError(string $message): string
    {
        $message = strtolower($message);

        if (strpos($message, 'dns') !== false || strpos($message, '解析') !== false) {
            return FailureLog::TYPE_DNS_ERROR;
        }
        if (strpos($message, 'timeout') !== false || strpos($message, '超时') !== false) {
            return FailureLog::TYPE_TIMEOUT;
        }
        if (strpos($message, 'rate') !== false || strpos($message, 'limit') !== false || strpos($message, '限流') !== false) {
            return FailureLog::TYPE_API_LIMIT;
        }
        if (strpos($message, 'connect') !== false || strpos($message, '连接') !== false || strpos($message, '响应') !== false) {
            return FailureLog::TYPE_NO_RESPONSE;
        }

        return FailureLog::TYPE_OTHER;
    }

    /**
     * 执行任务
     */
    public static function executeTask(int $taskId): array
    {
        $task = ScanTask::find($taskId);
        if (!$task) {
            return ['success' => false, 'error' => '任务不存在'];
        }

        // 标记任务开始
        if (!ScanTask::start($taskId)) {
            return ['success' => false, 'error' => '任务状态异常'];
        }

        try {
            $result = [];

            switch ($task['task_type']) {
                case ScanTask::TYPE_FIRST_SCAN:
                case ScanTask::TYPE_RESCAN:
                    $result = static::fullScan($task['asset_id']);
                    break;

                case ScanTask::TYPE_REFRESH_IP:
                    $result = static::refreshIp($task['asset_id']);
                    break;

                case ScanTask::TYPE_REFRESH_CF:
                    $result = static::refreshCf($task['asset_id']);
                    break;

                case ScanTask::TYPE_REFRESH_SIDESITE:
                    $result = static::refreshSidesites($task['asset_id']);
                    break;

                case ScanTask::TYPE_REFRESH_WP:
                    $result = static::refreshWp($task['asset_id']);
                    break;

                case ScanTask::TYPE_SIDESITE_SCAN:
                    if ($task['sidesite_id']) {
                        $result = static::scanSidesite($task['sidesite_id']);
                    }
                    break;

                default:
                    $result = ['success' => false, 'error' => '未知任务类型'];
            }

            // 完成任务
            ScanTask::complete($taskId, $result['success'], $result['error'] ?? null);

            // 如果失败且可重试,加入重试
            if (!$result['success']) {
                $retryCount = (int)setting('retry_count', 3);
                if ($task['retry_count'] < $retryCount) {
                    $retryInterval = (int)setting('retry_interval', 5);
                    sleep($retryInterval);
                    ScanTask::retry($taskId);
                }
            }

            return $result;

        } catch (\Exception $e) {
            ScanTask::complete($taskId, false, $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
