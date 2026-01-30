<?php
namespace App\Services;

/**
 * DNS解析服务
 */
class DnsService
{
    /**
     * 解析域名获取IP
     * @param string $domain 域名
     * @return array ['success' => bool, 'ip' => string|null, 'error' => string|null]
     */
    public static function resolve(string $domain): array
    {
        $domain = cleanDomain($domain);

        // 尝试获取A记录
        $records = @dns_get_record($domain, DNS_A);

        if ($records && !empty($records)) {
            return [
                'success' => true,
                'ip' => $records[0]['ip'],
                'error' => null,
            ];
        }

        // 尝试获取AAAA记录(IPv6)
        $records = @dns_get_record($domain, DNS_AAAA);

        if ($records && !empty($records)) {
            return [
                'success' => true,
                'ip' => $records[0]['ipv6'],
                'error' => null,
            ];
        }

        // 使用gethostbyname作为备选
        $ip = @gethostbyname($domain);
        if ($ip !== $domain && isValidIp($ip)) {
            return [
                'success' => true,
                'ip' => $ip,
                'error' => null,
            ];
        }

        return [
            'success' => false,
            'ip' => null,
            'error' => 'DNS解析失败',
        ];
    }

    /**
     * 获取所有DNS记录
     */
    public static function getAllRecords(string $domain): array
    {
        $domain = cleanDomain($domain);
        $result = [
            'A' => [],
            'AAAA' => [],
            'CNAME' => [],
            'MX' => [],
            'NS' => [],
            'TXT' => [],
        ];

        // A记录
        $records = @dns_get_record($domain, DNS_A);
        if ($records) {
            foreach ($records as $record) {
                $result['A'][] = $record['ip'];
            }
        }

        // AAAA记录
        $records = @dns_get_record($domain, DNS_AAAA);
        if ($records) {
            foreach ($records as $record) {
                $result['AAAA'][] = $record['ipv6'];
            }
        }

        // CNAME记录
        $records = @dns_get_record($domain, DNS_CNAME);
        if ($records) {
            foreach ($records as $record) {
                $result['CNAME'][] = $record['target'];
            }
        }

        // MX记录
        $records = @dns_get_record($domain, DNS_MX);
        if ($records) {
            foreach ($records as $record) {
                $result['MX'][] = [
                    'host' => $record['target'],
                    'priority' => $record['pri'],
                ];
            }
        }

        // NS记录
        $records = @dns_get_record($domain, DNS_NS);
        if ($records) {
            foreach ($records as $record) {
                $result['NS'][] = $record['target'];
            }
        }

        // TXT记录
        $records = @dns_get_record($domain, DNS_TXT);
        if ($records) {
            foreach ($records as $record) {
                $result['TXT'][] = $record['txt'];
            }
        }

        return $result;
    }

    /**
     * 批量解析域名
     */
    public static function batchResolve(array $domains): array
    {
        $results = [];
        foreach ($domains as $domain) {
            $results[$domain] = static::resolve($domain);
        }
        return $results;
    }

    /**
     * 反向DNS查询
     */
    public static function reverseLookup(string $ip): ?string
    {
        $host = @gethostbyaddr($ip);
        return ($host !== false && $host !== $ip) ? $host : null;
    }
}
