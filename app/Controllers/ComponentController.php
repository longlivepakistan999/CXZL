<?php
namespace App\Controllers;

use App\Models\Asset;
use App\Models\Sidesite;
use App\Helpers\Database;

/**
 * 组件控制器
 */
class ComponentController extends BaseController
{
    /**
     * 组件列表(全局统计)
     */
    public function index(): void
    {
        $projectId = inputInt('project_id');
        $search = input('search', '');
        list($page, $perPage) = $this->getPagination();

        $params = [];
        $whereParts = [];

        if ($projectId) {
            $whereParts[] = "project_id = ?";
            $params[] = $projectId;
        }

        if ($search) {
            $whereParts[] = "component_name LIKE ?";
            $params[] = "%{$search}%";
        }

        $whereClause = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        // 统计总数
        $total = (int)Database::queryScalar(
            "SELECT COUNT(DISTINCT component_name) FROM component_stats {$whereClause}",
            $params
        );

        // 获取组件列表
        $offset = ($page - 1) * $perPage;
        $components = Database::query(
            "SELECT
                component_name,
                SUM(asset_count) as asset_count,
                SUM(sidesite_count) as sidesite_count
            FROM component_stats
            {$whereClause}
            GROUP BY component_name
            ORDER BY asset_count + sidesite_count DESC
            LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        // 添加插件名称映射
        $pluginMap = \App\Services\WordPressService::getCommonPluginNamespaces();
        foreach ($components as &$comp) {
            $comp['plugin_name'] = $pluginMap[$comp['component_name']] ?? null;
            $comp['asset_count'] = (int)$comp['asset_count'];
            $comp['sidesite_count'] = (int)$comp['sidesite_count'];
            $comp['total_count'] = $comp['asset_count'] + $comp['sidesite_count'];
        }

        $this->paginatedResponse($components, $total, $page, $perPage);
    }

    /**
     * 获取使用某个组件的资产列表
     */
    public function assets(string $component): void
    {
        $component = urldecode($component);
        $projectId = inputInt('project_id');
        list($page, $perPage) = $this->getPagination();

        $params = [$component];
        $whereParts = ["JSON_CONTAINS(components, ?)"];
        $params[0] = json_encode($component);

        if ($projectId) {
            $whereParts[] = "project_id = ?";
            $params[] = $projectId;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereParts);

        // 统计总数
        $total = (int)Database::queryScalar(
            "SELECT COUNT(*) FROM assets {$whereClause}",
            $params
        );

        // 获取资产列表
        $offset = ($page - 1) * $perPage;
        $assets = Database::query(
            "SELECT a.*, p.name as project_name
            FROM assets a
            LEFT JOIN projects p ON a.project_id = p.id
            {$whereClause}
            ORDER BY a.id DESC
            LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        foreach ($assets as &$asset) {
            $asset['components'] = $asset['components'] ? json_decode($asset['components'], true) : [];
        }

        $this->paginatedResponse($assets, $total, $page, $perPage);
    }

    /**
     * 获取使用某个组件的旁站列表
     */
    public function sidesites(string $component): void
    {
        $component = urldecode($component);
        $projectId = inputInt('project_id');
        list($page, $perPage) = $this->getPagination();

        $params = [json_encode($component)];
        $whereParts = ["JSON_CONTAINS(components, ?)"];

        if ($projectId) {
            $whereParts[] = "project_id = ?";
            $params[] = $projectId;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereParts);

        // 统计总数
        $total = (int)Database::queryScalar(
            "SELECT COUNT(*) FROM sidesites {$whereClause}",
            $params
        );

        // 获取旁站列表
        $offset = ($page - 1) * $perPage;
        $sidesites = Database::query(
            "SELECT s.*, a.domain as asset_domain, p.name as project_name
            FROM sidesites s
            LEFT JOIN assets a ON s.asset_id = a.id
            LEFT JOIN projects p ON s.project_id = p.id
            {$whereClause}
            ORDER BY s.id DESC
            LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        foreach ($sidesites as &$sidesite) {
            $sidesite['components'] = $sidesite['components'] ? json_decode($sidesite['components'], true) : [];
        }

        $this->paginatedResponse($sidesites, $total, $page, $perPage);
    }

    /**
     * 导出使用某个组件的资产
     */
    public function exportAssets(string $component): void
    {
        $component = urldecode($component);
        $projectId = inputInt('project_id');
        $format = input('format', 'txt');

        $params = [json_encode($component)];
        $whereParts = ["JSON_CONTAINS(components, ?)"];

        if ($projectId) {
            $whereParts[] = "project_id = ?";
            $params[] = $projectId;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereParts);

        $domains = Database::query(
            "SELECT domain FROM assets {$whereClause}",
            $params
        );

        $domainList = array_column($domains, 'domain');

        $filename = 'component_' . preg_replace('/[^a-zA-Z0-9]/', '_', $component) . '_assets_' . date('YmdHis');

        if ($format === 'json') {
            header('Content-Type: application/json');
            header("Content-Disposition: attachment; filename=\"{$filename}.json\"");
            echo json_encode($domainList, JSON_UNESCAPED_UNICODE);
        } else {
            header('Content-Type: text/plain');
            header("Content-Disposition: attachment; filename=\"{$filename}.txt\"");
            echo implode("\n", $domainList);
        }
        exit;
    }

    /**
     * 导出使用某个组件的旁站
     */
    public function exportSidesites(string $component): void
    {
        $component = urldecode($component);
        $projectId = inputInt('project_id');
        $format = input('format', 'txt');

        $params = [json_encode($component)];
        $whereParts = ["JSON_CONTAINS(components, ?)"];

        if ($projectId) {
            $whereParts[] = "project_id = ?";
            $params[] = $projectId;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereParts);

        $domains = Database::query(
            "SELECT domain FROM sidesites {$whereClause}",
            $params
        );

        $domainList = array_column($domains, 'domain');

        $filename = 'component_' . preg_replace('/[^a-zA-Z0-9]/', '_', $component) . '_sidesites_' . date('YmdHis');

        if ($format === 'json') {
            header('Content-Type: application/json');
            header("Content-Disposition: attachment; filename=\"{$filename}.json\"");
            echo json_encode($domainList, JSON_UNESCAPED_UNICODE);
        } else {
            header('Content-Type: text/plain');
            header("Content-Disposition: attachment; filename=\"{$filename}.txt\"");
            echo implode("\n", $domainList);
        }
        exit;
    }

    /**
     * 刷新组件统计
     */
    public function refresh(): void
    {
        $projectId = inputInt('project_id');

        // 清空旧统计
        if ($projectId) {
            Database::execute("DELETE FROM component_stats WHERE project_id = ?", [$projectId]);
        } else {
            Database::execute("TRUNCATE TABLE component_stats");
        }

        // 从资产表统计
        $assetSql = "SELECT project_id, component_name, COUNT(*) as cnt
            FROM assets, JSON_TABLE(components, '\$[*]' COLUMNS(component_name VARCHAR(100) PATH '\$')) as jt
            WHERE components IS NOT NULL AND JSON_LENGTH(components) > 0";

        if ($projectId) {
            $assetSql .= " AND project_id = {$projectId}";
        }
        $assetSql .= " GROUP BY project_id, component_name";

        $assetStats = Database::query($assetSql);

        // 从旁站表统计
        $sidesiteSql = "SELECT project_id, component_name, COUNT(*) as cnt
            FROM sidesites, JSON_TABLE(components, '\$[*]' COLUMNS(component_name VARCHAR(100) PATH '\$')) as jt
            WHERE components IS NOT NULL AND JSON_LENGTH(components) > 0";

        if ($projectId) {
            $sidesiteSql .= " AND project_id = {$projectId}";
        }
        $sidesiteSql .= " GROUP BY project_id, component_name";

        $sidesiteStats = Database::query($sidesiteSql);

        // 合并统计
        $stats = [];
        foreach ($assetStats as $row) {
            $key = $row['project_id'] . '_' . $row['component_name'];
            $stats[$key] = [
                'project_id' => $row['project_id'],
                'component_name' => $row['component_name'],
                'asset_count' => (int)$row['cnt'],
                'sidesite_count' => 0,
            ];
        }

        foreach ($sidesiteStats as $row) {
            $key = $row['project_id'] . '_' . $row['component_name'];
            if (isset($stats[$key])) {
                $stats[$key]['sidesite_count'] = (int)$row['cnt'];
            } else {
                $stats[$key] = [
                    'project_id' => $row['project_id'],
                    'component_name' => $row['component_name'],
                    'asset_count' => 0,
                    'sidesite_count' => (int)$row['cnt'],
                ];
            }
        }

        // 插入统计数据
        if (!empty($stats)) {
            $columns = ['project_id', 'component_name', 'asset_count', 'sidesite_count'];
            Database::batchInsert('component_stats', $columns, array_values($stats));
        }

        success(['count' => count($stats)], '组件统计已刷新');
    }
}
