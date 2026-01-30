<?php
namespace App\Models;

use App\Helpers\Database;

/**
 * 资产模型
 */
class Asset extends BaseModel
{
    protected static string $table = 'assets';

    protected static array $fillable = [
        'project_id', 'domain', 'ip', 'is_cf', 'is_wp', 'components', 'component_count',
        'sidesite_count', 'wp_sidesite_count', 'scan_status', 'scan_error', 'retry_count',
        'tags', 'remark', 'imported_at', 'scanned_at'
    ];

    protected static array $casts = [
        'id' => 'int',
        'project_id' => 'int',
        'is_cf' => 'int',
        'is_wp' => 'int',
        'component_count' => 'int',
        'sidesite_count' => 'int',
        'wp_sidesite_count' => 'int',
        'scan_status' => 'int',
        'retry_count' => 'int',
        'components' => 'array',
        'tags' => 'array',
    ];

    // 扫描状态常量
    const STATUS_PENDING = 0;
    const STATUS_QUEUED = 1;
    const STATUS_SCANNING = 2;
    const STATUS_COMPLETED = 3;
    const STATUS_FAILED = 4;

    const STATUS_LABELS = [
        self::STATUS_PENDING => '待扫描',
        self::STATUS_QUEUED => '队列中',
        self::STATUS_SCANNING => '扫描中',
        self::STATUS_COMPLETED => '已完成',
        self::STATUS_FAILED => '失败',
    ];

    /**
     * 获取资产列表(支持复杂筛选)
     */
    public static function getList(array $filters, int $page = 1, int $perPage = 20, array $orderBy = ['id' => 'DESC']): array
    {
        $where = [];
        $params = [];

        // 项目筛选(必须)
        if (isset($filters['project_id'])) {
            $where[] = "`project_id` = ?";
            $params[] = $filters['project_id'];
        }

        // CF状态筛选
        if (isset($filters['is_cf']) && $filters['is_cf'] !== '') {
            $where[] = "`is_cf` = ?";
            $params[] = (int)$filters['is_cf'];
        }

        // WP状态筛选
        if (isset($filters['is_wp']) && $filters['is_wp'] !== '') {
            $where[] = "`is_wp` = ?";
            $params[] = (int)$filters['is_wp'];
        }

        // 扫描状态筛选
        if (isset($filters['scan_status']) && $filters['scan_status'] !== '') {
            $where[] = "`scan_status` = ?";
            $params[] = (int)$filters['scan_status'];
        }

        // 标签筛选
        if (!empty($filters['tag_id'])) {
            $where[] = "JSON_CONTAINS(`tags`, ?)";
            $params[] = json_encode((int)$filters['tag_id']);
        }

        // 组件筛选
        if (!empty($filters['component'])) {
            $where[] = "JSON_CONTAINS(`components`, ?)";
            $params[] = json_encode($filters['component']);
        }

        // 域名/IP搜索
        if (!empty($filters['search'])) {
            $where[] = "(`domain` LIKE ? OR `ip` LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        // 时间范围筛选
        if (!empty($filters['start_date'])) {
            $dateField = $filters['date_field'] ?? 'imported_at';
            $where[] = "`{$dateField}` >= ?";
            $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $dateField = $filters['date_field'] ?? 'imported_at';
            $where[] = "`{$dateField}` <= ?";
            $params[] = $filters['end_date'] . ' 23:59:59';
        }

        // 有旁站/无旁站
        if (isset($filters['has_sidesite'])) {
            if ($filters['has_sidesite']) {
                $where[] = "`sidesite_count` > 0";
            } else {
                $where[] = "`sidesite_count` = 0";
            }
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // 排序
        $orderParts = [];
        foreach ($orderBy as $col => $dir) {
            $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
            $orderParts[] = "`{$col}` {$dir}";
        }
        $orderClause = 'ORDER BY ' . implode(', ', $orderParts);

        // 分页
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM `assets` {$whereClause} {$orderClause} LIMIT {$perPage} OFFSET {$offset}";
        $rows = Database::query($sql, $params);

        return array_map([static::class, 'castAttributes'], $rows);
    }

    /**
     * 统计资产数量
     */
    public static function countByFilters(array $filters): int
    {
        $where = [];
        $params = [];

        if (isset($filters['project_id'])) {
            $where[] = "`project_id` = ?";
            $params[] = $filters['project_id'];
        }

        if (isset($filters['is_cf']) && $filters['is_cf'] !== '') {
            $where[] = "`is_cf` = ?";
            $params[] = (int)$filters['is_cf'];
        }

        if (isset($filters['is_wp']) && $filters['is_wp'] !== '') {
            $where[] = "`is_wp` = ?";
            $params[] = (int)$filters['is_wp'];
        }

        if (isset($filters['scan_status']) && $filters['scan_status'] !== '') {
            $where[] = "`scan_status` = ?";
            $params[] = (int)$filters['scan_status'];
        }

        if (!empty($filters['tag_id'])) {
            $where[] = "JSON_CONTAINS(`tags`, ?)";
            $params[] = json_encode((int)$filters['tag_id']);
        }

        if (!empty($filters['component'])) {
            $where[] = "JSON_CONTAINS(`components`, ?)";
            $params[] = json_encode($filters['component']);
        }

        if (!empty($filters['search'])) {
            $where[] = "(`domain` LIKE ? OR `ip` LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT COUNT(*) FROM `assets` {$whereClause}";

        return (int)Database::queryScalar($sql, $params);
    }

    /**
     * 批量导入资产
     */
    public static function batchImport(int $projectId, array $domains, bool $skipDuplicates = true, array $tagIds = []): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        // 获取已存在的域名
        $existingDomains = [];
        if ($skipDuplicates) {
            $placeholders = implode(',', array_fill(0, count($domains), '?'));
            $existing = Database::query(
                "SELECT `domain` FROM `assets` WHERE `project_id` = ? AND `domain` IN ({$placeholders})",
                array_merge([$projectId], $domains)
            );
            $existingDomains = array_column($existing, 'domain');
        }

        // 准备批量插入数据
        $insertData = [];
        $now = date('Y-m-d H:i:s');

        foreach ($domains as $domain) {
            $domain = cleanDomain($domain);

            if (!isValidDomain($domain)) {
                $errors[] = "无效域名: {$domain}";
                continue;
            }

            if ($skipDuplicates && in_array($domain, $existingDomains)) {
                $skipped++;
                continue;
            }

            $insertData[] = [
                'project_id' => $projectId,
                'domain' => $domain,
                'is_cf' => -1,
                'is_wp' => -1,
                'tags' => !empty($tagIds) ? json_encode($tagIds) : null,
                'imported_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // 批量插入
        if (!empty($insertData)) {
            $columns = ['project_id', 'domain', 'is_cf', 'is_wp', 'tags', 'imported_at', 'created_at', 'updated_at'];
            $imported = Database::batchInsert('assets', $columns, $insertData, 1000);
        }

        // 更新项目统计
        Project::refreshStats($projectId);

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * 获取资产详情
     */
    public static function getDetail(int $assetId): ?array
    {
        $asset = static::find($assetId);
        if (!$asset) {
            return null;
        }

        // 获取项目信息
        $asset['project'] = Project::find($asset['project_id']);

        // 获取旁站统计
        $asset['sidesite_stats'] = Database::queryOne(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN is_wp = 1 THEN 1 ELSE 0 END) as wp_count,
                SUM(CASE WHEN is_wp = 0 THEN 1 ELSE 0 END) as non_wp_count,
                SUM(CASE WHEN scan_status = 4 THEN 1 ELSE 0 END) as failed_count
            FROM `sidesites` WHERE `asset_id` = ?",
            [$assetId]
        );

        // 获取旁站组件统计
        $asset['sidesite_components'] = Database::query(
            "SELECT
                j.component,
                COUNT(*) as count
            FROM `sidesites` s,
            JSON_TABLE(s.components, '$[*]' COLUMNS (component VARCHAR(100) PATH '$')) j
            WHERE s.asset_id = ? AND s.is_wp = 1
            GROUP BY j.component
            ORDER BY count DESC
            LIMIT 20",
            [$assetId]
        );

        // 获取标签详情
        if (!empty($asset['tags'])) {
            $placeholders = implode(',', array_fill(0, count($asset['tags']), '?'));
            $asset['tag_details'] = Database::query(
                "SELECT * FROM `tags` WHERE `id` IN ({$placeholders})",
                $asset['tags']
            );
        } else {
            $asset['tag_details'] = [];
        }

        return $asset;
    }

    /**
     * 更新资产扫描结果
     */
    public static function updateScanResult(int $assetId, array $result): void
    {
        $updateData = [
            'scan_status' => $result['status'],
            'scanned_at' => date('Y-m-d H:i:s'),
        ];

        if (isset($result['ip'])) {
            $updateData['ip'] = $result['ip'];
        }
        if (isset($result['is_cf'])) {
            $updateData['is_cf'] = $result['is_cf'];
        }
        if (isset($result['is_wp'])) {
            $updateData['is_wp'] = $result['is_wp'];
        }
        if (isset($result['components'])) {
            $updateData['components'] = json_encode($result['components']);
            $updateData['component_count'] = count($result['components']);
        }
        if (isset($result['error'])) {
            $updateData['scan_error'] = $result['error'];
        }

        static::update($assetId, $updateData);

        // 更新缓存统计
        $asset = static::find($assetId);
        if ($asset) {
            static::refreshSidesiteStats($assetId);
            Project::refreshStats($asset['project_id']);
        }
    }

    /**
     * 刷新旁站统计
     */
    public static function refreshSidesiteStats(int $assetId): void
    {
        $stats = Database::queryOne(
            "SELECT
                COUNT(*) as sidesite_count,
                SUM(CASE WHEN is_wp = 1 THEN 1 ELSE 0 END) as wp_sidesite_count
            FROM `sidesites` WHERE `asset_id` = ?",
            [$assetId]
        );

        Database::execute(
            "UPDATE `assets` SET `sidesite_count` = ?, `wp_sidesite_count` = ? WHERE `id` = ?",
            [$stats['sidesite_count'] ?? 0, $stats['wp_sidesite_count'] ?? 0, $assetId]
        );
    }

    /**
     * 检查是否可以重新扫描(防重复)
     */
    public static function canRescan(int $assetId): bool
    {
        $interval = (int)setting('rescan_interval', 5);

        $asset = Database::queryOne(
            "SELECT `scan_status`, `scanned_at` FROM `assets` WHERE `id` = ?",
            [$assetId]
        );

        if (!$asset) {
            return false;
        }

        // 正在扫描中不允许重复
        if (in_array($asset['scan_status'], [self::STATUS_QUEUED, self::STATUS_SCANNING])) {
            return false;
        }

        // 检查时间间隔
        if ($asset['scanned_at']) {
            $lastScan = strtotime($asset['scanned_at']);
            if (time() - $lastScan < $interval * 60) {
                return false;
            }
        }

        return true;
    }

    /**
     * 获取项目内的组件列表
     */
    public static function getProjectComponents(int $projectId): array
    {
        return Database::query(
            "SELECT DISTINCT j.component
            FROM `assets` a,
            JSON_TABLE(a.components, '$[*]' COLUMNS (component VARCHAR(100) PATH '$')) j
            WHERE a.project_id = ? AND a.components IS NOT NULL
            ORDER BY j.component",
            [$projectId]
        );
    }
}
