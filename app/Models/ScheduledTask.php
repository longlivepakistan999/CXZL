<?php
namespace App\Models;

use App\Helpers\Database;

/**
 * 定时任务模型
 */
class ScheduledTask extends BaseModel
{
    protected static string $table = 'scheduled_tasks';

    protected static array $fillable = [
        'name', 'project_ids', 'scan_scope', 'scan_type',
        'cron_expression', 'is_enabled', 'last_run_at', 'next_run_at'
    ];

    protected static array $casts = [
        'id' => 'int',
        'scan_type' => 'int',
        'is_enabled' => 'bool',
        'project_ids' => 'array',
    ];

    /**
     * 获取待执行的定时任务
     */
    public static function getDueTasks(): array
    {
        $now = date('Y-m-d H:i:s');

        $tasks = Database::query(
            "SELECT * FROM `scheduled_tasks` WHERE `is_enabled` = 1 AND `next_run_at` <= ?",
            [$now]
        );

        return array_map([static::class, 'castAttributes'], $tasks);
    }

    /**
     * 更新下次执行时间
     */
    public static function updateNextRun(int $taskId, string $cronExpression): void
    {
        $nextRun = static::calculateNextRun($cronExpression);

        Database::execute(
            "UPDATE `scheduled_tasks` SET `last_run_at` = NOW(), `next_run_at` = ? WHERE `id` = ?",
            [$nextRun, $taskId]
        );
    }

    /**
     * 计算下次执行时间
     */
    public static function calculateNextRun(string $cronExpression): string
    {
        // 简化的cron解析,支持常用格式
        $parts = explode(' ', trim($cronExpression));

        if (count($parts) !== 5) {
            // 默认每天0点
            return date('Y-m-d 00:00:00', strtotime('+1 day'));
        }

        list($minute, $hour, $dayOfMonth, $month, $dayOfWeek) = $parts;

        $now = time();
        $nextRun = $now;

        // 简化处理:只支持固定时间的每天/每周/每月
        if ($minute !== '*' && $hour !== '*') {
            $targetTime = sprintf('%02d:%02d:00', $hour, $minute);
            $today = date('Y-m-d') . ' ' . $targetTime;

            if (strtotime($today) > $now) {
                $nextRun = strtotime($today);
            } else {
                // 明天同一时间
                $nextRun = strtotime('+1 day', strtotime($today));
            }

            // 处理周几
            if ($dayOfWeek !== '*') {
                while (date('w', $nextRun) != $dayOfWeek) {
                    $nextRun = strtotime('+1 day', $nextRun);
                }
            }

            // 处理月日
            if ($dayOfMonth !== '*') {
                while (date('j', $nextRun) != $dayOfMonth) {
                    $nextRun = strtotime('+1 day', $nextRun);
                }
            }
        } else {
            // 默认1小时后
            $nextRun = strtotime('+1 hour');
        }

        return date('Y-m-d H:i:s', $nextRun);
    }

    /**
     * 启用/禁用任务
     */
    public static function toggle(int $taskId): bool
    {
        $task = static::find($taskId);
        if (!$task) {
            return false;
        }

        $newStatus = $task['is_enabled'] ? 0 : 1;

        Database::execute(
            "UPDATE `scheduled_tasks` SET `is_enabled` = ? WHERE `id` = ?",
            [$newStatus, $taskId]
        );

        // 如果启用,计算下次执行时间
        if ($newStatus) {
            $nextRun = static::calculateNextRun($task['cron_expression']);
            Database::execute(
                "UPDATE `scheduled_tasks` SET `next_run_at` = ? WHERE `id` = ?",
                [$nextRun, $taskId]
            );
        }

        return true;
    }
}
