<?php
namespace App\Models;

use App\Helpers\Database;

/**
 * 扫描任务模型
 */
class ScanTask extends BaseModel
{
    protected static string $table = 'scan_tasks';

    protected static array $fillable = [
        'project_id', 'asset_id', 'sidesite_id', 'domain', 'task_type',
        'priority', 'status', 'error_message', 'retry_count', 'started_at', 'finished_at'
    ];

    protected static array $casts = [
        'id' => 'int',
        'project_id' => 'int',
        'asset_id' => 'int',
        'sidesite_id' => 'int',
        'task_type' => 'int',
        'priority' => 'int',
        'status' => 'int',
        'retry_count' => 'int',
    ];

    // 任务类型
    const TYPE_FIRST_SCAN = 1;      // 首次扫描
    const TYPE_RESCAN = 2;          // 重新扫描
    const TYPE_SIDESITE_SCAN = 3;   // 旁站扫描
    const TYPE_REFRESH_IP = 4;      // 仅刷新IP
    const TYPE_REFRESH_CF = 5;      // 仅刷新CF
    const TYPE_REFRESH_SIDESITE = 6; // 仅刷新旁站
    const TYPE_REFRESH_WP = 7;      // 仅刷新WP

    const TYPE_LABELS = [
        self::TYPE_FIRST_SCAN => '首次扫描',
        self::TYPE_RESCAN => '重新扫描',
        self::TYPE_SIDESITE_SCAN => '旁站扫描',
        self::TYPE_REFRESH_IP => '仅刷新IP',
        self::TYPE_REFRESH_CF => '仅刷新CF',
        self::TYPE_REFRESH_SIDESITE => '仅刷新旁站',
        self::TYPE_REFRESH_WP => '仅刷新WP',
    ];

    // 任务状态
    const STATUS_PENDING = 0;
    const STATUS_RUNNING = 1;
    const STATUS_SUCCESS = 2;
    const STATUS_FAILED = 3;
    const STATUS_CANCELLED = 4;

    const STATUS_LABELS = [
        self::STATUS_PENDING => '待扫描',
        self::STATUS_RUNNING => '扫描中',
        self::STATUS_SUCCESS => '成功',
        self::STATUS_FAILED => '失败',
        self::STATUS_CANCELLED => '已取消',
    ];

    // 优先级
    const PRIORITY_LOW = 1;
    const PRIORITY_NORMAL = 5;
    const PRIORITY_HIGH = 10;

    /**
     * 创建扫描任务
     */
    public static function createTask(int $projectId, int $assetId, string $domain, int $taskType = self::TYPE_FIRST_SCAN, int $priority = self::PRIORITY_NORMAL): int
    {
        // 检查是否已存在相同任务
        $existing = Database::queryOne(
            "SELECT `id` FROM `scan_tasks` WHERE `asset_id` = ? AND `status` IN (?, ?) LIMIT 1",
            [$assetId, self::STATUS_PENDING, self::STATUS_RUNNING]
        );

        if ($existing) {
            return 0; // 已有任务在队列中
        }

        $taskId = static::create([
            'project_id' => $projectId,
            'asset_id' => $assetId,
            'domain' => $domain,
            'task_type' => $taskType,
            'priority' => $priority,
            'status' => self::STATUS_PENDING,
        ]);

        // 更新资产状态为队列中
        Asset::update($assetId, ['scan_status' => Asset::STATUS_QUEUED]);

        return $taskId;
    }

    /**
     * 批量创建扫描任务
     */
    public static function batchCreate(int $projectId, array $assets, int $taskType = self::TYPE_FIRST_SCAN, int $priority = self::PRIORITY_NORMAL): int
    {
        if (empty($assets)) {
            return 0;
        }

        $assetIds = array_column($assets, 'id');

        // 获取已在队列中的资产
        $placeholders = implode(',', array_fill(0, count($assetIds), '?'));
        $existing = Database::query(
            "SELECT DISTINCT `asset_id` FROM `scan_tasks` WHERE `asset_id` IN ({$placeholders}) AND `status` IN (?, ?)",
            array_merge($assetIds, [self::STATUS_PENDING, self::STATUS_RUNNING])
        );
        $existingIds = array_column($existing, 'asset_id');

        // 准备任务数据
        $insertData = [];
        $now = date('Y-m-d H:i:s');

        foreach ($assets as $asset) {
            if (in_array($asset['id'], $existingIds)) {
                continue;
            }

            $insertData[] = [
                'project_id' => $projectId,
                'asset_id' => $asset['id'],
                'domain' => $asset['domain'],
                'task_type' => $taskType,
                'priority' => $priority,
                'status' => self::STATUS_PENDING,
                'created_at' => $now,
            ];
        }

        if (empty($insertData)) {
            return 0;
        }

        $columns = ['project_id', 'asset_id', 'domain', 'task_type', 'priority', 'status', 'created_at'];
        $inserted = Database::batchInsert('scan_tasks', $columns, $insertData, 1000);

        // 批量更新资产状态
        $insertedIds = array_column($insertData, 'asset_id');
        if (!empty($insertedIds)) {
            $placeholders = implode(',', array_fill(0, count($insertedIds), '?'));
            Database::execute(
                "UPDATE `assets` SET `scan_status` = ? WHERE `id` IN ({$placeholders})",
                array_merge([Asset::STATUS_QUEUED], $insertedIds)
            );
        }

        return $inserted;
    }

    /**
     * 获取下一批待执行任务
     */
    public static function getNextBatch(int $limit = 50): array
    {
        // 按优先级降序、ID升序获取
        $sql = "SELECT * FROM `scan_tasks`
                WHERE `status` = ?
                ORDER BY `priority` DESC, `id` ASC
                LIMIT {$limit}";

        $tasks = Database::query($sql, [self::STATUS_PENDING]);
        return array_map([static::class, 'castAttributes'], $tasks);
    }

    /**
     * 开始执行任务
     */
    public static function start(int $taskId): bool
    {
        $affected = Database::execute(
            "UPDATE `scan_tasks` SET `status` = ?, `started_at` = ? WHERE `id` = ? AND `status` = ?",
            [self::STATUS_RUNNING, date('Y-m-d H:i:s'), $taskId, self::STATUS_PENDING]
        );

        if ($affected > 0) {
            $task = static::find($taskId);
            if ($task && $task['asset_id']) {
                Asset::update($task['asset_id'], ['scan_status' => Asset::STATUS_SCANNING]);
            }
            return true;
        }

        return false;
    }

    /**
     * 完成任务
     */
    public static function complete(int $taskId, bool $success = true, string $error = null): void
    {
        $status = $success ? self::STATUS_SUCCESS : self::STATUS_FAILED;

        Database::execute(
            "UPDATE `scan_tasks` SET `status` = ?, `error_message` = ?, `finished_at` = ? WHERE `id` = ?",
            [$status, $error, date('Y-m-d H:i:s'), $taskId]
        );

        // 更新资产状态
        $task = static::find($taskId);
        if ($task && $task['asset_id']) {
            Asset::update($task['asset_id'], [
                'scan_status' => $success ? Asset::STATUS_COMPLETED : Asset::STATUS_FAILED,
                'scan_error' => $error,
            ]);
        }
    }

    /**
     * 重试任务
     */
    public static function retry(int $taskId): bool
    {
        $task = static::find($taskId);
        if (!$task || $task['status'] != self::STATUS_FAILED) {
            return false;
        }

        $maxRetries = (int)setting('retry_count', 3);
        if ($task['retry_count'] >= $maxRetries) {
            return false;
        }

        Database::execute(
            "UPDATE `scan_tasks` SET `status` = ?, `retry_count` = `retry_count` + 1, `error_message` = NULL WHERE `id` = ?",
            [self::STATUS_PENDING, $taskId]
        );

        if ($task['asset_id']) {
            Asset::update($task['asset_id'], ['scan_status' => Asset::STATUS_QUEUED]);
        }

        return true;
    }

    /**
     * 取消任务
     */
    public static function cancel(int $taskId): bool
    {
        $task = static::find($taskId);
        if (!$task || $task['status'] != self::STATUS_PENDING) {
            return false;
        }

        Database::execute(
            "UPDATE `scan_tasks` SET `status` = ? WHERE `id` = ?",
            [self::STATUS_CANCELLED, $taskId]
        );

        if ($task['asset_id']) {
            Asset::update($task['asset_id'], ['scan_status' => Asset::STATUS_PENDING]);
        }

        return true;
    }

    /**
     * 提升任务优先级
     */
    public static function prioritize(int $taskId): bool
    {
        return Database::execute(
            "UPDATE `scan_tasks` SET `priority` = ? WHERE `id` = ? AND `status` = ?",
            [self::PRIORITY_HIGH, $taskId, self::STATUS_PENDING]
        ) > 0;
    }

    /**
     * 获取任务列表
     */
    public static function getList(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = [];
        $params = [];

        if (isset($filters['project_id'])) {
            $where[] = "`project_id` = ?";
            $params[] = $filters['project_id'];
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $where[] = "`status` = ?";
            $params[] = (int)$filters['status'];
        }

        if (isset($filters['task_type']) && $filters['task_type'] !== '') {
            $where[] = "`task_type` = ?";
            $params[] = (int)$filters['task_type'];
        }

        if (!empty($filters['start_date'])) {
            $where[] = "`created_at` >= ?";
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $where[] = "`created_at` <= ?";
            $params[] = $filters['end_date'] . ' 23:59:59';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT t.*, p.name as project_name
                FROM `scan_tasks` t
                LEFT JOIN `projects` p ON t.project_id = p.id
                {$whereClause}
                ORDER BY t.`id` DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $rows = Database::query($sql, $params);
        return array_map([static::class, 'castAttributes'], $rows);
    }

    /**
     * 获取队列统计
     */
    public static function getQueueStats(): array
    {
        return Database::queryOne(
            "SELECT
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as running,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled
            FROM `scan_tasks`",
            [
                self::STATUS_PENDING,
                self::STATUS_RUNNING,
                self::STATUS_SUCCESS,
                self::STATUS_FAILED,
                self::STATUS_CANCELLED
            ]
        );
    }

    /**
     * 清理旧任务记录
     */
    public static function cleanOldTasks(int $days = 30): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return Database::execute(
            "DELETE FROM `scan_tasks` WHERE `status` IN (?, ?, ?) AND `created_at` < ?",
            [self::STATUS_SUCCESS, self::STATUS_FAILED, self::STATUS_CANCELLED, $cutoff]
        );
    }
}
