<?php
namespace App\Controllers;

use App\Models\Sidesite;
use App\Models\Asset;
use App\Services\ScannerService;
use App\Helpers\Database;

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

        $filters = [];

        // 整数过滤器 - 0 表示未设置
        $assetId = inputInt('asset_id');
        $projectId = inputInt('project_id');
        if ($assetId > 0) $filters['asset_id'] = $assetId;
        if ($projectId > 0) $filters['project_id'] = $projectId;

        // 字符串过滤器
        $isWp = input('is_wp');
        $scanStatus = input('scan_status');
        $component = input('component');
        $search = input('search');

        if ($isWp !== null && $isWp !== '') $filters['is_wp'] = $isWp;
        if ($scanStatus !== null && $scanStatus !== '') $filters['scan_status'] = $scanStatus;
        if ($component) $filters['component'] = $component;
        if ($search) $filters['search'] = $search;

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
     * 项目旁站统计
     */
    public function projectStats(int $projectId): void
    {
        // 基础统计
        $stats = Database::queryOne(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN is_wp = 1 THEN 1 ELSE 0 END) as wp_count,
                SUM(CASE WHEN is_wp = 0 THEN 1 ELSE 0 END) as non_wp_count,
                SUM(CASE WHEN is_wp = -1 THEN 1 ELSE 0 END) as unknown_count
            FROM sidesites WHERE project_id = ?",
            [$projectId]
        );

        // 组件统计
        $components = Database::query(
            "SELECT
                j.component as name,
                COUNT(*) as count
            FROM sidesites s,
            JSON_TABLE(s.components, '\$[*]' COLUMNS (component VARCHAR(100) PATH '\$')) j
            WHERE s.project_id = ? AND s.is_wp = 1 AND s.components IS NOT NULL
            GROUP BY j.component
            ORDER BY count DESC
            LIMIT 50",
            [$projectId]
        );

        // 添加插件名称映射
        $pluginMap = \App\Services\WordPressService::getCommonPluginNamespaces();
        foreach ($components as &$comp) {
            $comp['plugin_name'] = $pluginMap[$comp['name']] ?? null;
            $comp['count'] = (int)$comp['count'];
        }

        success([
            'total' => (int)($stats['total'] ?? 0),
            'wp_count' => (int)($stats['wp_count'] ?? 0),
            'non_wp_count' => (int)($stats['non_wp_count'] ?? 0),
            'unknown_count' => (int)($stats['unknown_count'] ?? 0),
            'components' => $components,
        ]);
    }

    /**
     * 资产旁站统计
     */
    public function assetStats(int $assetId): void
    {
        // 基础统计
        $stats = Database::queryOne(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN is_wp = 1 THEN 1 ELSE 0 END) as wp_count,
                SUM(CASE WHEN is_wp = 0 THEN 1 ELSE 0 END) as non_wp_count,
                SUM(CASE WHEN is_wp = -1 THEN 1 ELSE 0 END) as unknown_count
            FROM sidesites WHERE asset_id = ?",
            [$assetId]
        );

        // 组件统计
        $components = Database::query(
            "SELECT
                j.component as name,
                COUNT(*) as count
            FROM sidesites s,
            JSON_TABLE(s.components, '\$[*]' COLUMNS (component VARCHAR(100) PATH '\$')) j
            WHERE s.asset_id = ? AND s.is_wp = 1 AND s.components IS NOT NULL
            GROUP BY j.component
            ORDER BY count DESC
            LIMIT 50",
            [$assetId]
        );

        // 添加插件名称映射
        $pluginMap = \App\Services\WordPressService::getCommonPluginNamespaces();
        foreach ($components as &$comp) {
            $comp['plugin_name'] = $pluginMap[$comp['name']] ?? null;
            $comp['count'] = (int)$comp['count'];
        }

        success([
            'total' => (int)($stats['total'] ?? 0),
            'wp_count' => (int)($stats['wp_count'] ?? 0),
            'non_wp_count' => (int)($stats['non_wp_count'] ?? 0),
            'unknown_count' => (int)($stats['unknown_count'] ?? 0),
            'components' => $components,
        ]);
    }

    /**
     * 导出资产旁站
     */
    public function exportAsset(int $assetId): void
    {
        $type = input('type', 'all'); // all, wp, non_wp
        $component = input('component'); // 按组件筛选
        $format = input('format', 'txt');

        $params = [$assetId];
        $whereParts = ['asset_id = ?'];

        if ($type === 'wp') {
            $whereParts[] = 'is_wp = 1';
        } elseif ($type === 'non_wp') {
            $whereParts[] = 'is_wp = 0';
        }

        if ($component) {
            $whereParts[] = 'JSON_CONTAINS(components, ?)';
            $params[] = json_encode($component);
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereParts);

        $domains = Database::query(
            "SELECT domain, protocol FROM sidesites {$whereClause}",
            $params
        );

        // 构建URL列表
        $urlList = array_map(function($row) {
            return ($row['protocol'] ?? 'https') . '://' . $row['domain'];
        }, $domains);

        $filename = "sidesites_asset_{$assetId}_{$type}_" . date('YmdHis');

        if ($format === 'json') {
            header('Content-Type: application/json');
            header("Content-Disposition: attachment; filename=\"{$filename}.json\"");
            echo json_encode($urlList, JSON_UNESCAPED_UNICODE);
        } else {
            header('Content-Type: text/plain');
            header("Content-Disposition: attachment; filename=\"{$filename}.txt\"");
            echo implode("\n", $urlList);
        }
        exit;
    }

    /**
     * 导出项目旁站
     */
    public function exportProject(int $projectId): void
    {
        $type = input('type', 'all'); // all, wp, non_wp
        $component = input('component'); // 按组件筛选
        $format = input('format', 'txt');

        $params = [$projectId];
        $whereParts = ['project_id = ?'];

        if ($type === 'wp') {
            $whereParts[] = 'is_wp = 1';
        } elseif ($type === 'non_wp') {
            $whereParts[] = 'is_wp = 0';
        }

        if ($component) {
            $whereParts[] = 'JSON_CONTAINS(components, ?)';
            $params[] = json_encode($component);
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereParts);

        $domains = Database::query(
            "SELECT domain, protocol FROM sidesites {$whereClause}",
            $params
        );

        // 构建URL列表
        $urlList = array_map(function($row) {
            return ($row['protocol'] ?? 'https') . '://' . $row['domain'];
        }, $domains);

        $filename = "sidesites_project_{$projectId}_{$type}_" . date('YmdHis');

        if ($format === 'json') {
            header('Content-Type: application/json');
            header("Content-Disposition: attachment; filename=\"{$filename}.json\"");
            echo json_encode($urlList, JSON_UNESCAPED_UNICODE);
        } else {
            header('Content-Type: text/plain');
            header("Content-Disposition: attachment; filename=\"{$filename}.txt\"");
            echo implode("\n", $urlList);
        }
        exit;
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
