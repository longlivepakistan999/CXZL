<?php
namespace App\Controllers;

use App\Models\Asset;
use App\Models\Project;
use App\Models\ScanTask;
use App\Models\Sidesite;
use App\Services\ImportService;

/**
 * 资产控制器
 */
class AssetController extends BaseController
{
    /**
     * 资产列表
     */
    public function index(): void
    {
        list($page, $perPage) = $this->getPagination();

        $filters = [
            'project_id' => inputInt('project_id'),
            'is_cf' => input('is_cf'),
            'is_wp' => input('is_wp'),
            'scan_status' => input('scan_status'),
            'tag_id' => inputInt('tag_id'),
            'component' => input('component'),
            'search' => input('search'),
            'start_date' => input('start_date'),
            'end_date' => input('end_date'),
            'date_field' => input('date_field', 'imported_at'),
            'has_sidesite' => input('has_sidesite'),
        ];

        // 清理空值
        $filters = array_filter($filters, function ($v) {
            return $v !== null && $v !== '';
        });

        $orderBy = $this->getOrderBy(['id', 'domain', 'ip', 'component_count', 'sidesite_count', 'imported_at', 'scanned_at']);

        $assets = Asset::getList($filters, $page, $perPage, $orderBy);
        $total = Asset::countByFilters($filters);

        $this->paginatedResponse($assets, $total, $page, $perPage);
    }

    /**
     * 添加单个资产
     */
    public function store(): void
    {
        $data = $this->validate([
            'project_id' => 'required|integer',
            'domain' => 'required|max:255',
        ]);

        $project = Project::find($data['project_id']);
        if (!$project) {
            error('项目不存在');
        }

        $domain = cleanDomain($data['domain']);
        if (!isValidDomain($domain)) {
            error('无效的域名格式');
        }

        // 检查是否已存在
        if (Asset::exists(['project_id' => $data['project_id'], 'domain' => $domain])) {
            error('该资产已存在');
        }

        $assetId = Asset::create([
            'project_id' => $data['project_id'],
            'domain' => $domain,
            'is_cf' => -1,
            'is_wp' => -1,
            'tags' => input('tags') ? json_encode(inputArray('tags')) : null,
            'remark' => input('remark', ''),
        ]);

        // 自动开始扫描
        if (input('auto_scan')) {
            ScanTask::createTask($data['project_id'], $assetId, $domain, ScanTask::TYPE_FIRST_SCAN);
        }

        Project::refreshStats($data['project_id']);

        success(['id' => $assetId], '资产添加成功');
    }

    /**
     * 批量导入
     */
    public function import(): void
    {
        $projectId = inputInt('project_id');
        if (!$projectId) {
            error('请选择项目');
        }

        $project = Project::find($projectId);
        if (!$project) {
            error('项目不存在');
        }

        $domains = [];

        // 文本内容
        $content = input('content');
        if ($content) {
            $domains = ImportService::parseContent($content);
        }

        // 文件上传
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $maxSize = config('import.max_file_size', 50 * 1024 * 1024);
            if ($_FILES['file']['size'] > $maxSize) {
                error('文件大小超过限制');
            }

            $allowedExtensions = config('import.allowed_extensions', ['txt', 'csv', 'xlsx']);
            $extension = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions)) {
                error('不支持的文件格式');
            }

            $domains = ImportService::parseUploadedFile($_FILES['file']);
        }

        if (empty($domains)) {
            error('没有有效的域名数据');
        }

        $result = ImportService::import($projectId, $domains, [
            'skip_duplicates' => input('skip_duplicates', true),
            'tag_ids' => inputArray('tag_ids'),
            'auto_scan' => input('auto_scan', false),
        ]);

        success($result, "导入完成: {$result['imported']}条成功, {$result['skipped']}条跳过");
    }

    /**
     * 导入预览
     */
    public function importPreview(): void
    {
        $projectId = inputInt('project_id');
        if (!$projectId) {
            error('请选择项目');
        }

        $domains = [];

        $content = input('content');
        if ($content) {
            $domains = ImportService::parseContent($content);
        }

        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $domains = ImportService::parseUploadedFile($_FILES['file']);
        }

        if (empty($domains)) {
            error('没有有效的域名数据');
        }

        $preview = ImportService::preview($projectId, $domains);
        success($preview);
    }

    /**
     * 资产详情
     */
    public function show(int $id): void
    {
        $asset = Asset::getDetail($id);

        if (!$asset) {
            error('资产不存在', 404);
        }

        success($asset);
    }

    /**
     * 更新资产
     */
    public function update(int $id): void
    {
        $asset = Asset::find($id);
        if (!$asset) {
            error('资产不存在', 404);
        }

        $updateData = [];

        if (input('tags') !== null) {
            $updateData['tags'] = json_encode(inputArray('tags'));
        }
        if (input('remark') !== null) {
            $updateData['remark'] = input('remark');
        }

        if (empty($updateData)) {
            error('没有需要更新的数据');
        }

        Asset::update($id, $updateData);
        success(null, '更新成功');
    }

    /**
     * 删除资产
     */
    public function destroy(int $id): void
    {
        $asset = Asset::find($id);
        if (!$asset) {
            error('资产不存在', 404);
        }

        // 删除旁站
        Sidesite::deleteByAsset($id);

        // 删除资产
        Asset::delete($id);

        // 更新项目统计
        Project::refreshStats($asset['project_id']);

        success(null, '删除成功');
    }

    /**
     * 扫描单个资产
     */
    public function scan(int $id): void
    {
        $asset = Asset::find($id);
        if (!$asset) {
            error('资产不存在', 404);
        }

        // 检查是否可以重扫
        if (!Asset::canRescan($id)) {
            error('资产正在扫描中或短时间内已扫描');
        }

        $scanType = inputInt('scan_type', ScanTask::TYPE_RESCAN);
        $priority = inputInt('priority', ScanTask::PRIORITY_HIGH);

        $taskId = ScanTask::createTask(
            $asset['project_id'],
            $id,
            $asset['domain'],
            $scanType,
            $priority
        );

        if ($taskId) {
            success(['task_id' => $taskId], '扫描任务已创建');
        } else {
            error('任务已存在于队列中');
        }
    }

    /**
     * 批量扫描
     */
    public function batchScan(): void
    {
        $assetIds = inputArray('asset_ids');
        $projectId = inputInt('project_id');

        if (empty($assetIds) && !$projectId) {
            error('请选择要扫描的资产');
        }

        $scanType = inputInt('scan_type', ScanTask::TYPE_RESCAN);
        $priority = inputInt('priority', ScanTask::PRIORITY_NORMAL);

        // 获取资产
        if (!empty($assetIds)) {
            $assets = Asset::whereIn('id', $assetIds);
            $projectId = $assets[0]['project_id'] ?? 0;
        } else {
            $filters = ['project_id' => $projectId];

            // 可选筛选条件
            if (input('is_cf') !== null) {
                $filters['is_cf'] = inputInt('is_cf');
            }
            if (input('is_wp') !== null) {
                $filters['is_wp'] = inputInt('is_wp');
            }
            if (input('scan_status') !== null) {
                $filters['scan_status'] = inputInt('scan_status');
            }

            $assets = Asset::getList($filters, 1, 100000);
        }

        if (empty($assets)) {
            error('没有符合条件的资产');
        }

        $taskCount = ScanTask::batchCreate($projectId, $assets, $scanType, $priority);

        success([
            'task_count' => $taskCount,
            'asset_count' => count($assets),
        ], "已创建 {$taskCount} 个扫描任务");
    }

    /**
     * 批量删除
     */
    public function batchDelete(): void
    {
        $assetIds = inputArray('asset_ids');

        if (empty($assetIds)) {
            error('请选择要删除的资产');
        }

        $assets = Asset::whereIn('id', $assetIds);
        $projectIds = array_unique(array_column($assets, 'project_id'));

        // 删除旁站
        foreach ($assetIds as $assetId) {
            Sidesite::deleteByAsset($assetId);
        }

        // 批量删除资产
        $deleted = Asset::deleteIn($assetIds);

        // 更新项目统计
        foreach ($projectIds as $projectId) {
            Project::refreshStats($projectId);
        }

        success(['deleted' => $deleted], "已删除 {$deleted} 个资产");
    }

    /**
     * 批量打标签
     */
    public function batchTag(): void
    {
        $assetIds = inputArray('asset_ids');
        $tagIds = inputArray('tag_ids');
        $mode = input('mode', 'add'); // add, remove, replace

        if (empty($assetIds)) {
            error('请选择资产');
        }

        $updated = 0;

        foreach ($assetIds as $assetId) {
            $asset = Asset::find($assetId);
            if (!$asset) {
                continue;
            }

            $currentTags = $asset['tags'] ?? [];

            switch ($mode) {
                case 'add':
                    $newTags = array_unique(array_merge($currentTags, $tagIds));
                    break;
                case 'remove':
                    $newTags = array_diff($currentTags, $tagIds);
                    break;
                case 'replace':
                    $newTags = $tagIds;
                    break;
                default:
                    $newTags = $currentTags;
            }

            Asset::update($assetId, ['tags' => json_encode(array_values($newTags))]);
            $updated++;
        }

        success(['updated' => $updated], "已更新 {$updated} 个资产的标签");
    }
}
