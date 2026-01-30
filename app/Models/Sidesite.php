<?php
namespace App\Models;

use App\Helpers\Database;

/**
 * 旁站模型
 */
class Sidesite extends BaseModel
{
    protected static string $table = 'sidesites';

    protected static array $fillable = [
        'asset_id', 'project_id', 'domain', 'ip', 'is_wp', 'components',
        'component_count', 'scan_status', 'scan_error'
    ];

    protected static array $casts = [
        'id' => 'int',
        'asset_id' => 'int',
        'project_id' => 'int',
        'is_wp' => 'int',
        'component_count' => 'int',
        'scan_status' => 'int',
        'components' => 'array',
    ];

    const STATUS_COMPLETED = 3;
    const STATUS_FAILED = 4;

    /**
     * 获取旁站列表
     */
    public static function getList(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = [];
        $params = [];

        if (isset($filters['asset_id'])) {
            $where[] = "`asset_id` = ?";
            $params[] = $filters['asset_id'];
        }

        if (isset($filters['project_id'])) {
            $where[] = "`project_id` = ?";
            $params[] = $filters['project_id'];
        }

        if (isset($filters['is_wp']) && $filters['is_wp'] !== '') {
            $where[] = "`is_wp` = ?";
            $params[] = (int)$filters['is_wp'];
        }

        if (isset($filters['scan_status']) && $filters['scan_status'] !== '') {
            $where[] = "`scan_status` = ?";
            $params[] = (int)$filters['scan_status'];
        }

        if (!empty($filters['component'])) {
            $where[] = "JSON_CONTAINS(`components`, ?)";
            $params[] = json_encode($filters['component']);
        }

        if (!empty($filters['search'])) {
            $where[] = "`domain` LIKE ?";
            $params[] = "%{$filters['search']}%";
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM `sidesites` {$whereClause} ORDER BY `id` DESC LIMIT {$perPage} OFFSET {$offset}";
        $rows = Database::query($sql, $params);

        return array_map([static::class, 'castAttributes'], $rows);
    }

    /**
     * 统计旁站数量
     */
    public static function countByFilters(array $filters): int
    {
        $where = [];
        $params = [];

        if (isset($filters['asset_id'])) {
            $where[] = "`asset_id` = ?";
            $params[] = $filters['asset_id'];
        }

        if (isset($filters['project_id'])) {
            $where[] = "`project_id` = ?";
            $params[] = $filters['project_id'];
        }

        if (isset($filters['is_wp']) && $filters['is_wp'] !== '') {
            $where[] = "`is_wp` = ?";
            $params[] = (int)$filters['is_wp'];
        }

        if (isset($filters['scan_status']) && $filters['scan_status'] !== '') {
            $where[] = "`scan_status` = ?";
            $params[] = (int)$filters['scan_status'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT COUNT(*) FROM `sidesites` {$whereClause}";

        return (int)Database::queryScalar($sql, $params);
    }

    /**
     * 批量插入旁站(带去重)
     */
    public static function batchInsert(int $assetId, int $projectId, array $domains, string $ip = null): int
    {
        if (empty($domains)) {
            return 0;
        }

        // 获取已存在的旁站
        $existingDomains = [];
        $placeholders = implode(',', array_fill(0, count($domains), '?'));
        $existing = Database::query(
            "SELECT `domain` FROM `sidesites` WHERE `project_id` = ? AND `domain` IN ({$placeholders})",
            array_merge([$projectId], $domains)
        );
        $existingDomains = array_column($existing, 'domain');

        // 准备插入数据
        $insertData = [];
        $now = date('Y-m-d H:i:s');

        foreach ($domains as $domain) {
            $domain = cleanDomain($domain);

            if (!isValidDomain($domain) || in_array($domain, $existingDomains)) {
                continue;
            }

            $insertData[] = [
                'asset_id' => $assetId,
                'project_id' => $projectId,
                'domain' => $domain,
                'ip' => $ip,
                'is_wp' => -1,
                'scan_status' => self::STATUS_COMPLETED,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($insertData)) {
            return 0;
        }

        $columns = ['asset_id', 'project_id', 'domain', 'ip', 'is_wp', 'scan_status', 'created_at', 'updated_at'];
        $inserted = Database::batchInsert('sidesites', $columns, $insertData, 1000);

        // 更新资产旁站统计
        Asset::refreshSidesiteStats($assetId);

        return $inserted;
    }

    /**
     * 更新旁站WP检测结果
     */
    public static function updateWpResult(int $sidesiteId, bool $isWp, array $components = []): void
    {
        $updateData = [
            'is_wp' => $isWp ? 1 : 0,
            'components' => $isWp ? json_encode($components) : null,
            'component_count' => $isWp ? count($components) : 0,
            'scan_status' => self::STATUS_COMPLETED,
        ];

        static::update($sidesiteId, $updateData);

        // 更新资产WP旁站统计
        $sidesite = static::find($sidesiteId);
        if ($sidesite) {
            Asset::refreshSidesiteStats($sidesite['asset_id']);
        }
    }

    /**
     * 标记旁站扫描失败
     */
    public static function markFailed(int $sidesiteId, string $error): void
    {
        static::update($sidesiteId, [
            'scan_status' => self::STATUS_FAILED,
            'scan_error' => $error,
        ]);
    }

    /**
     * 获取资产的所有旁站域名
     */
    public static function getDomainsByAsset(int $assetId): array
    {
        $rows = Database::query(
            "SELECT `domain` FROM `sidesites` WHERE `asset_id` = ?",
            [$assetId]
        );
        return array_column($rows, 'domain');
    }

    /**
     * 删除资产的所有旁站
     */
    public static function deleteByAsset(int $assetId): int
    {
        return Database::execute(
            "DELETE FROM `sidesites` WHERE `asset_id` = ?",
            [$assetId]
        );
    }
}
