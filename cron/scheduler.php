<?php
/**
 * 定时任务调度器
 * 建议通过crontab每分钟执行一次:
 * * * * * * php /path/to/cron/scheduler.php >> /path/to/storage/logs/cron.log 2>&1
 */

require_once __DIR__ . '/../app/bootstrap.php';

use App\Models\ScheduledTask;
use App\Models\Asset;
use App\Models\ScanTask;
use App\Models\ExportRecord;
use App\Services\CloudflareService;

echo "[" . date('Y-m-d H:i:s') . "] 定时任务调度器启动\n";

/**
 * 执行到期的定时任务
 */
function runScheduledTasks(): void
{
    $tasks = ScheduledTask::getDueTasks();

    foreach ($tasks as $task) {
        echo "[" . date('Y-m-d H:i:s') . "] 执行定时任务: {$task['name']}\n";

        try {
            // 获取目标项目
            $projectIds = $task['project_ids'];
            if (empty($projectIds)) {
                $projects = \App\Models\Project::all(1, 10000);
                $projectIds = array_column($projects, 'id');
            }

            $totalTasks = 0;

            foreach ($projectIds as $projectId) {
                $filters = ['project_id' => $projectId];

                switch ($task['scan_scope']) {
                    case 'failed':
                        $filters['scan_status'] = Asset::STATUS_FAILED;
                        break;
                    case 'outdated':
                        // 可以根据需要调整天数
                        break;
                }

                $assets = Asset::where($filters, 1, 100000);

                if (!empty($assets)) {
                    $count = ScanTask::batchCreate($projectId, $assets, $task['scan_type']);
                    $totalTasks += $count;
                }
            }

            echo "[" . date('Y-m-d H:i:s') . "] 创建了 {$totalTasks} 个扫描任务\n";

            // 更新下次执行时间
            ScheduledTask::updateNextRun($task['id'], $task['cron_expression']);

        } catch (\Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] 任务执行失败: {$e->getMessage()}\n";
            logError('定时任务执行失败', ['task_id' => $task['id'], 'error' => $e->getMessage()]);
        }
    }
}

/**
 * 更新CF IP段(每天)
 */
function updateCfIps(): void
{
    if (\App\Models\CfIpRange::needsUpdate()) {
        echo "[" . date('Y-m-d H:i:s') . "] 更新CF IP段\n";

        try {
            $result = CloudflareService::updateIpRanges();
            echo "[" . date('Y-m-d H:i:s') . "] CF IP段更新完成: IPv4 {$result['v4']}条, IPv6 {$result['v6']}条\n";
        } catch (\Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] CF IP段更新失败: {$e->getMessage()}\n";
        }
    }
}

/**
 * 清理过期导出
 */
function cleanExpiredExports(): void
{
    $cleaned = ExportRecord::cleanExpired();
    if ($cleaned > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] 清理了 {$cleaned} 个过期导出\n";
    }
}

/**
 * 清理旧任务记录
 */
function cleanOldTasks(): void
{
    // 每天凌晨清理
    if (date('H:i') === '03:00') {
        $cleaned = ScanTask::cleanOldTasks(30);
        if ($cleaned > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] 清理了 {$cleaned} 条旧任务记录\n";
        }
    }
}

// 执行各项任务
runScheduledTasks();
updateCfIps();
cleanExpiredExports();
cleanOldTasks();

echo "[" . date('Y-m-d H:i:s') . "] 定时任务调度器完成\n";
