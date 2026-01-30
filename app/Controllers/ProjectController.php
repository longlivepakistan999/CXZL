<?php
namespace App\Controllers;

use App\Models\Project;
use App\Models\Asset;
use App\Models\ScanTask;

/**
 * 项目控制器
 */
class ProjectController extends BaseController
{
    /**
     * 项目列表
     */
    public function index(): void
    {
        list($page, $perPage) = $this->getPagination();
        $search = input('search', '');
        $orderBy = $this->getOrderBy(['id', 'name', 'asset_count', 'created_at']);

        $projects = Project::getList($page, $perPage, $search, $orderBy);
        $total = Project::count($search ? ['name' => ['LIKE', "%{$search}%"]] : []);

        $this->paginatedResponse($projects, $total, $page, $perPage);
    }

    /**
     * 创建项目
     */
    public function store(): void
    {
        $data = $this->validate([
            'name' => 'required|max:255',
            'description' => 'max:1000',
        ]);

        // 检查名称是否已存在
        if (Project::exists(['name' => $data['name']])) {
            error('项目名称已存在');
        }

        $projectId = Project::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
        ]);

        success(['id' => $projectId], '项目创建成功');
    }

    /**
     * 项目详情
     */
    public function show(int $id): void
    {
        $project = Project::getDetail($id);

        if (!$project) {
            error('项目不存在', 404);
        }

        success($project);
    }

    /**
     * 更新项目
     */
    public function update(int $id): void
    {
        $project = Project::find($id);
        if (!$project) {
            error('项目不存在', 404);
        }

        $data = $this->validate([
            'name' => 'required|max:255',
            'description' => 'max:1000',
        ]);

        // 检查名称是否被其他项目使用
        $existing = Project::findBy(['name' => $data['name']]);
        if ($existing && $existing['id'] !== $id) {
            error('项目名称已存在');
        }

        Project::update($id, [
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
        ]);

        success(null, '项目更新成功');
    }

    /**
     * 删除项目
     */
    public function destroy(int $id): void
    {
        $project = Project::find($id);
        if (!$project) {
            error('项目不存在', 404);
        }

        if (Project::deleteWithAssets($id)) {
            success(null, '项目删除成功');
        } else {
            error('删除失败');
        }
    }

    /**
     * 全量扫描项目
     */
    public function scan(int $id): void
    {
        $project = Project::find($id);
        if (!$project) {
            error('项目不存在', 404);
        }

        $scanType = inputInt('scan_type', ScanTask::TYPE_RESCAN);
        $priority = inputInt('priority', ScanTask::PRIORITY_NORMAL);

        // 获取所有资产
        $assets = Asset::where(['project_id' => $id], 1, 1000000);

        if (empty($assets)) {
            error('项目没有资产');
        }

        $taskCount = ScanTask::batchCreate($id, $assets, $scanType, $priority);

        success([
            'task_count' => $taskCount,
            'asset_count' => count($assets),
        ], "已创建 {$taskCount} 个扫描任务");
    }
}
