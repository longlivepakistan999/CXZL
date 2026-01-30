<?php
namespace App\Controllers;

use App\Models\ScanTask;

/**
 * 扫描任务控制器
 */
class TaskController extends BaseController
{
    /**
     * 任务列表
     */
    public function index(): void
    {
        list($page, $perPage) = $this->getPagination();

        $filters = [
            'project_id' => inputInt('project_id'),
            'status' => input('status'),
            'task_type' => input('task_type'),
            'start_date' => input('start_date'),
            'end_date' => input('end_date'),
        ];

        $filters = array_filter($filters, function ($v) {
            return $v !== null && $v !== '';
        });

        $tasks = ScanTask::getList($filters, $page, $perPage);
        $total = ScanTask::count($filters);

        // 添加类型和状态标签
        foreach ($tasks as &$task) {
            $task['type_label'] = ScanTask::TYPE_LABELS[$task['task_type']] ?? '未知';
            $task['status_label'] = ScanTask::STATUS_LABELS[$task['status']] ?? '未知';

            // 计算耗时
            if ($task['started_at'] && $task['finished_at']) {
                $start = strtotime($task['started_at']);
                $end = strtotime($task['finished_at']);
                $task['duration'] = $end - $start;
            } else {
                $task['duration'] = null;
            }
        }

        $this->paginatedResponse($tasks, $total, $page, $perPage);
    }

    /**
     * 队列统计
     */
    public function stats(): void
    {
        $stats = ScanTask::getQueueStats();
        success($stats);
    }

    /**
     * 取消任务
     */
    public function cancel(int $id): void
    {
        if (ScanTask::cancel($id)) {
            success(null, '任务已取消');
        } else {
            error('无法取消该任务');
        }
    }

    /**
     * 重试任务
     */
    public function retry(int $id): void
    {
        if (ScanTask::retry($id)) {
            success(null, '任务已加入队列');
        } else {
            error('无法重试该任务');
        }
    }

    /**
     * 提升优先级
     */
    public function prioritize(int $id): void
    {
        if (ScanTask::prioritize($id)) {
            success(null, '已提升任务优先级');
        } else {
            error('无法提升优先级');
        }
    }
}
