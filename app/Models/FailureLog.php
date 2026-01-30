<?php
namespace App\Models;

use App\Helpers\Database;

/**
 * 失败日志模型
 */
class FailureLog extends BaseModel
{
    protected static string $table = 'failure_logs';

    protected static array $fillable = [
        'project_id', 'asset_id', 'sidesite_id', 'domain',
        'failure_type', 'failure_reason', 'retry_count', 'is_ignored'
    ];

    protected static array $casts = [
        'id' => 'int',
        'project_id' => 'int',
        'asset_id' => 'int',
        'sidesite_id' => 'int',
        'retry_count' => 'int',
        'is_ignored' => 'bool',
    ];

    // 失败类型
    const TYPE_DNS_ERROR = 'dns_error';
    const TYPE_TIMEOUT = 'timeout';
    const TYPE_API_LIMIT = 'api_limit';
    const TYPE_NO_RESPONSE = 'no_response';
    const TYPE_OTHER = 'other';

    const TYPE_LABELS = [
        self::TYPE_DNS_ERROR => 'DNS解析失败',
        self::TYPE_TIMEOUT => '网络超时',
        self::TYPE_API_LIMIT => 'API限流',
        self::TYPE_NO_RESPONSE => '目标无响应',
        self::TYPE_OTHER => '其他',
    ];

    /**
     * 记录失败
     */
    public static function log(int $projectId, string $domain, string $type, string $reason, int $assetId = null, int $sidesiteId = null): int
    {
        return static::create([
            'project_id' => $projectId,
            'asset_id' => $assetId,
            'sidesite_id' => $sidesiteId,
            'domain' => $domain,
            'failure_type' => $type,
            'failure_reason' => $reason,
        ]);
    }

    /**
     * 获取失败日志列表
     */
    public static function getList(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = ['`is_ignored` = 0'];
        $params = [];

        if (isset($filters['project_id'])) {
            $where[] = "`project_id` = ?";
            $params[] = $filters['project_id'];
        }

        if (!empty($filters['failure_type'])) {
            $where[] = "`failure_type` = ?";
            $params[] = $filters['failure_type'];
        }

        if (!empty($filters['start_date'])) {
            $where[] = "`created_at` >= ?";
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $where[] = "`created_at` <= ?";
            $params[] = $filters['end_date'] . ' 23:59:59';
        }

        if (!empty($filters['search'])) {
            $where[] = "`domain` LIKE ?";
            $params[] = "%{$filters['search']}%";
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT f.*, p.name as project_name
                FROM `failure_logs` f
                LEFT JOIN `projects` p ON f.project_id = p.id
                {$whereClause}
                ORDER BY f.`id` DESC
                LIMIT {$perPage} OFFSET {$offset}";

        return Database::query($sql, $params);
    }

    /**
     * 统计失败日志
     */
    public static function countByFilters(array $filters): int
    {
        $where = ['`is_ignored` = 0'];
        $params = [];

        if (isset($filters['project_id'])) {
            $where[] = "`project_id` = ?";
            $params[] = $filters['project_id'];
        }

        if (!empty($filters['failure_type'])) {
            $where[] = "`failure_type` = ?";
            $params[] = $filters['failure_type'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM `failure_logs` {$whereClause}";

        return (int)Database::queryScalar($sql, $params);
    }

    /**
     * 忽略失败记录
     */
    public static function ignore(int $logId): bool
    {
        return Database::execute(
            "UPDATE `failure_logs` SET `is_ignored` = 1 WHERE `id` = ?",
            [$logId]
        ) > 0;
    }

    /**
     * 批量忽略
     */
    public static function batchIgnore(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return Database::execute(
            "UPDATE `failure_logs` SET `is_ignored` = 1 WHERE `id` IN ({$placeholders})",
            $ids
        );
    }

    /**
     * 清空全部失败记录
     */
    public static function clearAll(int $projectId = null): int
    {
        if ($projectId) {
            return Database::execute(
                "DELETE FROM `failure_logs` WHERE `project_id` = ?",
                [$projectId]
            );
        }
        return Database::execute("TRUNCATE TABLE `failure_logs`");
    }

    /**
     * 获取失败统计
     */
    public static function getStats(int $projectId = null): array
    {
        $where = '`is_ignored` = 0';
        $params = [];

        if ($projectId) {
            $where .= ' AND `project_id` = ?';
            $params[] = $projectId;
        }

        return Database::query(
            "SELECT `failure_type`, COUNT(*) as count
            FROM `failure_logs`
            WHERE {$where}
            GROUP BY `failure_type`",
            $params
        );
    }
}
