<?php
namespace App\Models;

use App\Helpers\Database;

/**
 * 项目模型
 */
class Project extends BaseModel
{
    protected static string $table = 'projects';

    protected static array $fillable = [
        'name', 'description', 'asset_count', 'cf_count', 'wp_count', 'sidesite_count'
    ];

    protected static array $casts = [
        'id' => 'int',
        'asset_count' => 'int',
        'cf_count' => 'int',
        'wp_count' => 'int',
        'sidesite_count' => 'int',
    ];

    /**
     * 获取项目列表(带统计)
     */
    public static function getList(int $page = 1, int $perPage = 20, string $search = '', array $orderBy = ['id' => 'DESC']): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [];
        $whereClause = '';

        if ($search) {
            $whereClause = "WHERE `name` LIKE ?";
            $params[] = "%{$search}%";
        }

        $orderParts = [];
        foreach ($orderBy as $col => $dir) {
            $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
            $orderParts[] = "`{$col}` {$dir}";
        }
        $orderClause = 'ORDER BY ' . implode(', ', $orderParts);

        $sql = "SELECT * FROM `projects` {$whereClause} {$orderClause} LIMIT {$perPage} OFFSET {$offset}";
        $rows = Database::query($sql, $params);

        // 计算扫描状态
        foreach ($rows as &$row) {
            $row = static::castAttributes($row);
            $row['scan_status'] = static::getScanStatus($row['id']);
        }

        return $rows;
    }

    /**
     * 获取项目扫描状态
     */
    public static function getScanStatus(int $projectId): string
    {
        $stats = Database::queryOne(
            "SELECT
                SUM(CASE WHEN scan_status = 2 THEN 1 ELSE 0 END) as scanning,
                SUM(CASE WHEN scan_status = 0 THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN scan_status = 3 THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN scan_status = 4 THEN 1 ELSE 0 END) as failed,
                COUNT(*) as total
            FROM `assets` WHERE `project_id` = ?",
            [$projectId]
        );

        if ($stats['total'] == 0) {
            return '无资产';
        }
        if ($stats['scanning'] > 0) {
            return '扫描中';
        }
        if ($stats['pending'] > 0) {
            return '待扫描';
        }
        if ($stats['failed'] > 0 && $stats['completed'] > 0) {
            return '部分完成';
        }
        if ($stats['completed'] == $stats['total']) {
            return '全部完成';
        }
        return '待扫描';
    }

    /**
     * 更新项目统计缓存
     */
    public static function refreshStats(int $projectId): void
    {
        $stats = Database::queryOne(
            "SELECT
                COUNT(*) as asset_count,
                SUM(CASE WHEN is_cf = 1 THEN 1 ELSE 0 END) as cf_count,
                SUM(CASE WHEN is_wp = 1 THEN 1 ELSE 0 END) as wp_count,
                SUM(sidesite_count) as sidesite_count
            FROM `assets` WHERE `project_id` = ?",
            [$projectId]
        );

        Database::execute(
            "UPDATE `projects` SET
                `asset_count` = ?,
                `cf_count` = ?,
                `wp_count` = ?,
                `sidesite_count` = ?
            WHERE `id` = ?",
            [
                $stats['asset_count'] ?? 0,
                $stats['cf_count'] ?? 0,
                $stats['wp_count'] ?? 0,
                $stats['sidesite_count'] ?? 0,
                $projectId
            ]
        );
    }

    /**
     * 获取项目详情(带完整统计)
     */
    public static function getDetail(int $projectId): ?array
    {
        $project = static::find($projectId);
        if (!$project) {
            return null;
        }

        // 获取资产统计
        $project['stats'] = Database::queryOne(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN is_cf = 1 THEN 1 ELSE 0 END) as cf_count,
                SUM(CASE WHEN is_cf = 0 THEN 1 ELSE 0 END) as non_cf_count,
                SUM(CASE WHEN is_wp = 1 THEN 1 ELSE 0 END) as wp_count,
                SUM(CASE WHEN is_wp = 0 THEN 1 ELSE 0 END) as non_wp_count,
                SUM(sidesite_count) as sidesite_total,
                SUM(wp_sidesite_count) as wp_sidesite_total,
                SUM(CASE WHEN scan_status = 0 THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN scan_status = 2 THEN 1 ELSE 0 END) as scanning,
                SUM(CASE WHEN scan_status = 3 THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN scan_status = 4 THEN 1 ELSE 0 END) as failed
            FROM `assets` WHERE `project_id` = ?",
            [$projectId]
        );

        // 获取组件排行
        $project['top_components'] = Database::query(
            "SELECT `component_name`, `asset_count`, `sidesite_count`
            FROM `component_stats`
            WHERE `project_id` = ?
            ORDER BY `asset_count` + `sidesite_count` DESC
            LIMIT 10",
            [$projectId]
        );

        return $project;
    }

    /**
     * 删除项目(级联删除)
     */
    public static function deleteWithAssets(int $projectId): bool
    {
        try {
            Database::beginTransaction();

            // 删除旁站
            Database::execute("DELETE FROM `sidesites` WHERE `project_id` = ?", [$projectId]);

            // 删除资产
            Database::execute("DELETE FROM `assets` WHERE `project_id` = ?", [$projectId]);

            // 删除扫描任务
            Database::execute("DELETE FROM `scan_tasks` WHERE `project_id` = ?", [$projectId]);

            // 删除失败日志
            Database::execute("DELETE FROM `failure_logs` WHERE `project_id` = ?", [$projectId]);

            // 删除组件统计
            Database::execute("DELETE FROM `component_stats` WHERE `project_id` = ?", [$projectId]);

            // 删除项目
            Database::execute("DELETE FROM `projects` WHERE `id` = ?", [$projectId]);

            Database::commit();
            return true;
        } catch (\Exception $e) {
            Database::rollback();
            logError('删除项目失败', ['project_id' => $projectId, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
