<?php
namespace App\Controllers;

use App\Models\FailureLog;
use App\Models\ScanTask;
use App\Models\Asset;

/**
 * 失败日志控制器
 */
class FailureController extends BaseController
{
    /**
     * 失败日志列表
     */
    public function index(): void
    {
        list($page, $perPage) = $this->getPagination();

        $filters = [
            'project_id' => inputInt('project_id'),
            'failure_type' => input('failure_type'),
            'start_date' => input('start_date'),
            'end_date' => input('end_date'),
            'search' => input('search'),
        ];

        $filters = array_filter($filters, function ($v) {
            return $v !== null && $v !== '';
        });

        $logs = FailureLog::getList($filters, $page, $perPage);
        $total = FailureLog::countByFilters($filters);

        // 添加类型标签
        foreach ($logs as &$log) {
            $log['type_label'] = FailureLog::TYPE_LABELS[$log['failure_type']] ?? '未知';
        }

        $this->paginatedResponse($logs, $total, $page, $perPage);
    }

    /**
     * 重试失败任务
     */
    public function retry(int $id): void
    {
        $log = FailureLog::find($id);
        if (!$log) {
            error('记录不存在', 404);
        }

        // 创建新的扫描任务
        if ($log['asset_id']) {
            $asset = Asset::find($log['asset_id']);
            if ($asset) {
                ScanTask::createTask(
                    $log['project_id'],
                    $log['asset_id'],
                    $log['domain'],
                    ScanTask::TYPE_RESCAN,
                    ScanTask::PRIORITY_HIGH
                );

                // 标记为已处理
                FailureLog::ignore($id);

                success(null, '已创建重试任务');
            }
        }

        error('无法创建重试任务');
    }

    /**
     * 忽略失败记录
     */
    public function ignore(int $id): void
    {
        if (FailureLog::ignore($id)) {
            success(null, '已忽略');
        } else {
            error('操作失败');
        }
    }

    /**
     * 批量重试
     */
    public function batchRetry(): void
    {
        $ids = inputArray('ids');
        if (empty($ids)) {
            error('请选择要重试的记录');
        }

        $success = 0;

        foreach ($ids as $id) {
            $log = FailureLog::find($id);
            if (!$log || !$log['asset_id']) {
                continue;
            }

            $asset = Asset::find($log['asset_id']);
            if (!$asset) {
                continue;
            }

            $taskId = ScanTask::createTask(
                $log['project_id'],
                $log['asset_id'],
                $log['domain'],
                ScanTask::TYPE_RESCAN,
                ScanTask::PRIORITY_NORMAL
            );

            if ($taskId) {
                FailureLog::ignore($id);
                $success++;
            }
        }

        success(['success' => $success], "已创建 {$success} 个重试任务");
    }

    /**
     * 批量忽略
     */
    public function batchIgnore(): void
    {
        $ids = inputArray('ids');
        if (empty($ids)) {
            error('请选择要忽略的记录');
        }

        $count = FailureLog::batchIgnore($ids);
        success(['ignored' => $count], "已忽略 {$count} 条记录");
    }

    /**
     * 清空失败记录
     */
    public function clear(): void
    {
        $projectId = inputInt('project_id');
        $count = FailureLog::clearAll($projectId ?: null);
        success(['deleted' => $count], '已清空失败记录');
    }
}
