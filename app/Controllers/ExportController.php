<?php
namespace App\Controllers;

use App\Models\ExportRecord;
use App\Services\ExportService;

/**
 * 导出控制器
 */
class ExportController extends BaseController
{
    /**
     * 导出记录列表
     */
    public function index(): void
    {
        list($page, $perPage) = $this->getPagination();

        $records = ExportRecord::getList($page, $perPage);
        $total = ExportRecord::count();

        // 添加状态标签
        foreach ($records as &$record) {
            $record['status_label'] = ExportRecord::STATUS_LABELS[$record['status']] ?? '未知';
        }

        $this->paginatedResponse($records, $total, $page, $perPage);
    }

    /**
     * 导出资产
     */
    public function exportAssets(): void
    {
        $projectId = inputInt('project_id');
        if (!$projectId) {
            error('请选择项目');
        }

        $filters = [
            'is_cf' => input('is_cf'),
            'is_wp' => input('is_wp'),
            'scan_status' => input('scan_status'),
            'tag_id' => inputInt('tag_id'),
            'asset_ids' => inputArray('asset_ids'),
        ];

        $filters = array_filter($filters, function ($v) {
            return $v !== null && $v !== '' && $v !== [];
        });

        $fields = inputArray('fields');
        $format = input('format', 'xlsx');

        $result = ExportService::exportAssets($projectId, $filters, $fields, $format);

        if ($result['success']) {
            success([
                'record_id' => $result['record_id'],
                'count' => $result['count'],
            ], '导出完成');
        } else {
            error($result['error']);
        }
    }

    /**
     * 导出旁站
     */
    public function exportSidesites(): void
    {
        $projectId = inputInt('project_id');
        if (!$projectId) {
            error('请选择项目');
        }

        $filters = [
            'asset_id' => inputInt('asset_id'),
            'is_wp' => input('is_wp'),
        ];

        $filters = array_filter($filters, function ($v) {
            return $v !== null && $v !== '';
        });

        $format = input('format', 'xlsx');

        $result = ExportService::exportSidesites($projectId, $filters, $format);

        if ($result['success']) {
            success([
                'record_id' => $result['record_id'],
                'count' => $result['count'],
            ], '导出完成');
        } else {
            error($result['error']);
        }
    }

    /**
     * 快速导出
     */
    public function quickExport(): void
    {
        $projectId = inputInt('project_id');
        $type = input('type');

        if (!$projectId || !$type) {
            error('参数不完整');
        }

        $result = ExportService::quickExport($projectId, $type);

        if ($result['success']) {
            // 直接返回文件内容(小文件)
            $content = file_get_contents($result['file_path']);

            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
            header('Content-Length: ' . strlen($content));

            echo $content;
            exit;
        } else {
            error($result['error']);
        }
    }

    /**
     * 下载导出文件
     */
    public function download(int $id): void
    {
        $record = ExportRecord::find($id);

        if (!$record) {
            error('导出记录不存在', 404);
        }

        if ($record['status'] !== ExportRecord::STATUS_COMPLETED) {
            error('文件尚未生成完成或已过期');
        }

        if (!$record['file_path'] || !file_exists($record['file_path'])) {
            error('文件不存在');
        }

        $filename = basename($record['file_path']);
        $mimeTypes = [
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
            'json' => 'application/json',
            'txt' => 'text/plain',
        ];

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($record['file_path']));

        readfile($record['file_path']);
        exit;
    }

    /**
     * 删除导出记录
     */
    public function destroy(int $id): void
    {
        $record = ExportRecord::find($id);

        if (!$record) {
            error('导出记录不存在', 404);
        }

        // 删除文件
        if ($record['file_path'] && file_exists($record['file_path'])) {
            @unlink($record['file_path']);
        }

        ExportRecord::delete($id);
        success(null, '删除成功');
    }
}
