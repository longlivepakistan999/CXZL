<?php
namespace App\Controllers;

use App\Models\ScheduledTask;
use App\Models\Asset;
use App\Models\ScanTask;

/**
 * 定时任务控制器
 */
class ScheduledTaskController extends BaseController
{
    /**
     * 定时任务列表
     */
    public function index(): void
    {
        $tasks = ScheduledTask::all(1, 1000, ['id' => 'DESC']);

        // 添加扫描类型标签
        foreach ($tasks as &$task) {
            $task['scan_type_label'] = ScanTask::TYPE_LABELS[$task['scan_type']] ?? '完整扫描';
        }

        success($tasks);
    }

    /**
     * 创建定时任务
     */
    public function store(): void
    {
        $data = $this->validate([
            'name' => 'required|max:255',
            'cron_expression' => 'required',
        ]);

        $projectIds = inputArray('project_ids');
        $scanScope = input('scan_scope', 'all');
        $scanType = inputInt('scan_type', ScanTask::TYPE_RESCAN);

        // 计算下次执行时间
        $nextRun = ScheduledTask::calculateNextRun($data['cron_expression']);

        $taskId = ScheduledTask::create([
            'name' => $data['name'],
            'project_ids' => !empty($projectIds) ? json_encode($projectIds) : null,
            'scan_scope' => $scanScope,
            'scan_type' => $scanType,
            'cron_expression' => $data['cron_expression'],
            'is_enabled' => 1,
            'next_run_at' => $nextRun,
        ]);

        success(['id' => $taskId], '定时任务创建成功');
    }

    /**
     * 更新定时任务
     */
    public function update(int $id): void
    {
        $task = ScheduledTask::find($id);
        if (!$task) {
            error('定时任务不存在', 404);
        }

        $data = $this->validate([
            'name' => 'required|max:255',
            'cron_expression' => 'required',
        ]);

        $projectIds = inputArray('project_ids');
        $scanScope = input('scan_scope', $task['scan_scope']);
        $scanType = inputInt('scan_type', $task['scan_type']);

        // 重新计算下次执行时间
        $nextRun = ScheduledTask::calculateNextRun($data['cron_expression']);

        ScheduledTask::update($id, [
            'name' => $data['name'],
            'project_ids' => !empty($projectIds) ? json_encode($projectIds) : null,
            'scan_scope' => $scanScope,
            'scan_type' => $scanType,
            'cron_expression' => $data['cron_expression'],
            'next_run_at' => $nextRun,
        ]);

        success(null, '定时任务更新成功');
    }

    /**
     * 删除定时任务
     */
    public function destroy(int $id): void
    {
        $task = ScheduledTask::find($id);
        if (!$task) {
            error('定时任务不存在', 404);
        }

        ScheduledTask::delete($id);
        success(null, '定时任务删除成功');
    }

    /**
     * 启用/禁用定时任务
     */
    public function toggle(int $id): void
    {
        if (ScheduledTask::toggle($id)) {
            success(null, '状态已更新');
        } else {
            error('操作失败');
        }
    }

    /**
     * 立即执行定时任务
     */
    public function run(int $id): void
    {
        $task = ScheduledTask::find($id);
        if (!$task) {
            error('定时任务不存在', 404);
        }

        // 获取目标项目
        $projectIds = $task['project_ids'];
        if (empty($projectIds)) {
            // 获取所有项目
            $projects = \App\Models\Project::all(1, 10000);
            $projectIds = array_column($projects, 'id');
        }

        $totalTasks = 0;

        foreach ($projectIds as $projectId) {
            // 根据扫描范围获取资产
            $filters = ['project_id' => $projectId];

            switch ($task['scan_scope']) {
                case 'failed':
                    $filters['scan_status'] = Asset::STATUS_FAILED;
                    break;
                case 'outdated':
                    // 超过7天未扫描的
                    $filters['scanned_at'] = ['<', date('Y-m-d H:i:s', strtotime('-7 days'))];
                    break;
            }

            $assets = Asset::where($filters, 1, 100000);

            if (!empty($assets)) {
                $count = ScanTask::batchCreate($projectId, $assets, $task['scan_type']);
                $totalTasks += $count;
            }
        }

        // 更新执行时间
        ScheduledTask::updateNextRun($id, $task['cron_expression']);

        success([
            'task_count' => $totalTasks,
        ], "已创建 {$totalTasks} 个扫描任务");
    }
}
