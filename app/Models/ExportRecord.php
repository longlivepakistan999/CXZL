<?php
namespace App\Models;

use App\Helpers\Database;

/**
 * 导出记录模型
 */
class ExportRecord extends BaseModel
{
    protected static string $table = 'export_records';

    protected static array $fillable = [
        'project_id', 'export_type', 'export_scope', 'record_count',
        'file_format', 'file_path', 'file_size', 'status', 'expired_at'
    ];

    protected static array $casts = [
        'id' => 'int',
        'project_id' => 'int',
        'record_count' => 'int',
        'file_size' => 'int',
        'status' => 'int',
    ];

    // 状态
    const STATUS_GENERATING = 0;
    const STATUS_COMPLETED = 1;
    const STATUS_EXPIRED = 2;
    const STATUS_FAILED = 3;

    const STATUS_LABELS = [
        self::STATUS_GENERATING => '生成中',
        self::STATUS_COMPLETED => '已完成',
        self::STATUS_EXPIRED => '已过期',
        self::STATUS_FAILED => '失败',
    ];

    /**
     * 创建导出任务
     */
    public static function createExport(int $projectId = null, string $type, string $scope, string $format = 'xlsx'): int
    {
        $expireHours = (int)config('export.expire_hours', 24);

        return static::create([
            'project_id' => $projectId,
            'export_type' => $type,
            'export_scope' => $scope,
            'file_format' => $format,
            'status' => self::STATUS_GENERATING,
            'expired_at' => date('Y-m-d H:i:s', strtotime("+{$expireHours} hours")),
        ]);
    }

    /**
     * 完成导出
     */
    public static function complete(int $recordId, string $filePath, int $recordCount, int $fileSize): void
    {
        static::update($recordId, [
            'file_path' => $filePath,
            'record_count' => $recordCount,
            'file_size' => $fileSize,
            'status' => self::STATUS_COMPLETED,
        ]);
    }

    /**
     * 标记失败
     */
    public static function markFailed(int $recordId): void
    {
        static::update($recordId, ['status' => self::STATUS_FAILED]);
    }

    /**
     * 获取导出记录列表
     */
    public static function getList(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT e.*, p.name as project_name
                FROM `export_records` e
                LEFT JOIN `projects` p ON e.project_id = p.id
                ORDER BY e.`id` DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $rows = Database::query($sql);

        return array_map(function ($row) {
            $row = static::castAttributes($row);
            $row['file_size_formatted'] = $row['file_size'] ? formatFileSize($row['file_size']) : '-';
            return $row;
        }, $rows);
    }

    /**
     * 清理过期导出
     */
    public static function cleanExpired(): int
    {
        $now = date('Y-m-d H:i:s');

        // 获取过期记录
        $expired = Database::query(
            "SELECT `id`, `file_path` FROM `export_records` WHERE `expired_at` < ? AND `status` = ?",
            [$now, self::STATUS_COMPLETED]
        );

        // 删除文件
        foreach ($expired as $record) {
            if ($record['file_path'] && file_exists($record['file_path'])) {
                @unlink($record['file_path']);
            }
        }

        // 更新状态
        return Database::execute(
            "UPDATE `export_records` SET `status` = ? WHERE `expired_at` < ? AND `status` = ?",
            [self::STATUS_EXPIRED, $now, self::STATUS_COMPLETED]
        );
    }
}
