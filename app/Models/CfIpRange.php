<?php
namespace App\Models;

use App\Helpers\Database;

/**
 * Cloudflare IP段模型
 */
class CfIpRange extends BaseModel
{
    protected static string $table = 'cf_ip_ranges';

    protected static array $fillable = ['ip_range', 'ip_version', 'ip_start', 'ip_end'];

    protected static array $casts = [
        'id' => 'int',
        'ip_version' => 'int',
    ];

    /**
     * 检查IP是否属于Cloudflare
     */
    public static function isCloudflareIp(string $ip): bool
    {
        $binary = ipToBinary($ip);
        if ($binary === null) {
            return false;
        }

        $version = isIPv4($ip) ? 4 : 6;

        $result = Database::queryScalar(
            "SELECT 1 FROM `cf_ip_ranges`
            WHERE `ip_version` = ? AND `ip_start` <= ? AND `ip_end` >= ?
            LIMIT 1",
            [$version, $binary, $binary]
        );

        return $result !== false;
    }

    /**
     * 更新CF IP段缓存
     */
    public static function updateFromCloudflare(): array
    {
        $config = config('api.cloudflare');
        $stats = ['v4' => 0, 'v6' => 0, 'errors' => []];

        // 获取IPv4段
        $ipv4Response = @file_get_contents($config['ipv4_url']);
        if ($ipv4Response !== false) {
            $ipv4Ranges = array_filter(array_map('trim', explode("\n", $ipv4Response)));
            $stats['v4'] = static::saveRanges($ipv4Ranges, 4);
        } else {
            $stats['errors'][] = '获取IPv4段失败';
        }

        // 获取IPv6段
        $ipv6Response = @file_get_contents($config['ipv6_url']);
        if ($ipv6Response !== false) {
            $ipv6Ranges = array_filter(array_map('trim', explode("\n", $ipv6Response)));
            $stats['v6'] = static::saveRanges($ipv6Ranges, 6);
        } else {
            $stats['errors'][] = '获取IPv6段失败';
        }

        // 更新时间记录
        updateSetting('cf_ip_updated_at', date('Y-m-d H:i:s'));

        return $stats;
    }

    /**
     * 保存IP段
     */
    private static function saveRanges(array $ranges, int $version): int
    {
        if (empty($ranges)) {
            return 0;
        }

        // 清除旧数据
        Database::execute(
            "DELETE FROM `cf_ip_ranges` WHERE `ip_version` = ?",
            [$version]
        );

        $insertData = [];
        $now = date('Y-m-d H:i:s');

        foreach ($ranges as $range) {
            $parsed = parseCIDR($range);
            if ($parsed === null) {
                continue;
            }

            $insertData[] = [
                'ip_range' => $range,
                'ip_version' => $version,
                'ip_start' => $parsed['start'],
                'ip_end' => $parsed['end'],
                'updated_at' => $now,
            ];
        }

        if (empty($insertData)) {
            return 0;
        }

        $columns = ['ip_range', 'ip_version', 'ip_start', 'ip_end', 'updated_at'];
        return Database::batchInsert('cf_ip_ranges', $columns, $insertData);
    }

    /**
     * 检查是否需要更新
     */
    public static function needsUpdate(): bool
    {
        $lastUpdate = setting('cf_ip_updated_at');
        if (!$lastUpdate) {
            return true;
        }

        $interval = config('api.cloudflare.update_interval', 86400);
        return time() - strtotime($lastUpdate) > $interval;
    }

    /**
     * 获取统计信息
     */
    public static function getStats(): array
    {
        $stats = Database::query(
            "SELECT `ip_version`, COUNT(*) as count FROM `cf_ip_ranges` GROUP BY `ip_version`"
        );

        $result = ['v4' => 0, 'v6' => 0, 'updated_at' => setting('cf_ip_updated_at')];
        foreach ($stats as $row) {
            if ($row['ip_version'] == 4) {
                $result['v4'] = (int)$row['count'];
            } else {
                $result['v6'] = (int)$row['count'];
            }
        }

        return $result;
    }
}
