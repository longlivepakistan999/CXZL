<?php
namespace App\Controllers;

use App\Models\Sidesite;
use App\Models\Asset;
use App\Services\ScannerService;

/**
 * 旁站控制器
 */
class SidesiteController extends BaseController
{
    /**
     * 旁站列表
     */
    public function index(): void
    {
        list($page, $perPage) = $this->getPagination();

        $filters = [
            'asset_id' => inputInt('asset_id'),
            'project_id' => inputInt('project_id'),
            'is_wp' => input('is_wp'),
            'scan_status' => input('scan_status'),
            'component' => input('component'),
            'search' => input('search'),
        ];

        $filters = array_filter($filters, function ($v) {
            return $v !== null && $v !== '';
        });

        $sidesites = Sidesite::getList($filters, $page, $perPage);
        $total = Sidesite::countByFilters($filters);

        $this->paginatedResponse($sidesites, $total, $page, $perPage);
    }

    /**
     * 旁站详情
     */
    public function show(int $id): void
    {
        $sidesite = Sidesite::find($id);

        if (!$sidesite) {
            error('旁站不存在', 404);
        }

        // 获取所属资产
        $sidesite['asset'] = Asset::find($sidesite['asset_id']);

        success($sidesite);
    }

    /**
     * 扫描单个旁站
     */
    public function scan(int $id): void
    {
        $sidesite = Sidesite::find($id);
        if (!$sidesite) {
            error('旁站不存在', 404);
        }

        $result = ScannerService::scanSidesite($id);

        if ($result['success']) {
            success($result, '扫描完成');
        } else {
            error($result['error']);
        }
    }

    /**
     * 批量扫描旁站
     */
    public function batchScan(): void
    {
        $sidesiteIds = inputArray('sidesite_ids');
        $assetId = inputInt('asset_id');

        if (empty($sidesiteIds) && !$assetId) {
            error('请选择要扫描的旁站');
        }

        // 获取旁站
        if (!empty($sidesiteIds)) {
            $sidesites = Sidesite::whereIn('id', $sidesiteIds);
        } else {
            $sidesites = Sidesite::getList(['asset_id' => $assetId], 1, 10000);
        }

        if (empty($sidesites)) {
            error('没有符合条件的旁站');
        }

        $success = 0;
        $failed = 0;

        foreach ($sidesites as $sidesite) {
            $result = ScannerService::scanSidesite($sidesite['id']);
            if ($result['success']) {
                $success++;
            } else {
                $failed++;
            }
        }

        success([
            'total' => count($sidesites),
            'success' => $success,
            'failed' => $failed,
        ], "扫描完成: {$success}成功, {$failed}失败");
    }
}
