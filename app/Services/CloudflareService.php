<?php
namespace App\Services;

use App\Models\CfIpRange;

/**
 * Cloudflare检测服务
 */
class CloudflareService
{
    /**
     * 检查IP是否为Cloudflare
     */
    public static function isCloudflare(string $ip): bool
    {
        // 确保CF IP段数据已更新
        if (CfIpRange::needsUpdate()) {
            static::updateIpRanges();
        }

        return CfIpRange::isCloudflareIp($ip);
    }

    /**
     * 检查域名是否使用Cloudflare
     * 综合检测:IP + HTTP头
     */
    public static function checkDomain(string $domain): array
    {
        $result = [
            'is_cf' => false,
            'ip' => null,
            'detection_method' => null,
        ];

        // 先解析IP
        $dnsResult = DnsService::resolve($domain);
        if (!$dnsResult['success']) {
            return $result;
        }

        $result['ip'] = $dnsResult['ip'];

        // 检查IP是否在CF范围内
        if (static::isCloudflare($dnsResult['ip'])) {
            $result['is_cf'] = true;
            $result['detection_method'] = 'ip_range';
            return $result;
        }

        // 可选:通过HTTP头检测
        $headers = static::getHttpHeaders($domain);
        if ($headers && static::hasCfHeaders($headers)) {
            $result['is_cf'] = true;
            $result['detection_method'] = 'http_header';
        }

        return $result;
    }

    /**
     * 获取HTTP响应头
     */
    private static function getHttpHeaders(string $domain): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'timeout' => 5,
                'follow_location' => 1,
                'max_redirects' => 3,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        // 尝试HTTPS
        $headers = @get_headers("https://{$domain}", true, $context);
        if ($headers) {
            return $headers;
        }

        // 尝试HTTP
        $headers = @get_headers("http://{$domain}", true, $context);
        return $headers ?: null;
    }

    /**
     * 检查是否有CF特征头
     */
    private static function hasCfHeaders(array $headers): bool
    {
        $cfIndicators = [
            'cf-ray',
            'cf-cache-status',
            'cf-request-id',
            'server' => 'cloudflare',
        ];

        foreach ($headers as $key => $value) {
            $key = strtolower($key);

            // 检查CF特征头是否存在
            if (in_array($key, ['cf-ray', 'cf-cache-status', 'cf-request-id'])) {
                return true;
            }

            // 检查Server头
            if ($key === 'server') {
                $serverValue = is_array($value) ? end($value) : $value;
                if (stripos($serverValue, 'cloudflare') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 更新CF IP段
     */
    public static function updateIpRanges(): array
    {
        return CfIpRange::updateFromCloudflare();
    }

    /**
     * 获取CF IP段统计
     */
    public static function getIpRangeStats(): array
    {
        return CfIpRange::getStats();
    }

    /**
     * 批量检查
     */
    public static function batchCheck(array $ips): array
    {
        // 确保数据已更新
        if (CfIpRange::needsUpdate()) {
            static::updateIpRanges();
        }

        $results = [];
        foreach ($ips as $ip) {
            $results[$ip] = CfIpRange::isCloudflareIp($ip);
        }
        return $results;
    }
}
