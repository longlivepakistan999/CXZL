<?php
namespace App\Controllers;

use App\Models\Project;
use App\Models\Asset;
use App\Models\Sidesite;
use App\Models\ScanTask;
use App\Helpers\Database;

/**
 * 仪表盘控制器
 */
class DashboardController extends BaseController
{
    /**
     * 仪表盘概览
     */
    public function index(): void
    {
        $data = [
            'stats' => $this->getGlobalStats(),
            'scan_status' => ScanTask::getQueueStats(),
            'top_components' => $this->getTopComponents(),
            'recent_activities' => $this->getRecentActivities(),
            'project_rankings' => $this->getProjectRankings(),
        ];

        success($data);
    }

    /**
     * 全局统计
     */
    public function stats(): void
    {
        success($this->getGlobalStats());
    }

    /**
     * 获取全局统计数据
     */
    private function getGlobalStats(): array
    {
        $stats = Database::queryOne(
            "SELECT
                (SELECT COUNT(*) FROM `projects`) as project_count,
                (SELECT COUNT(*) FROM `assets`) as asset_count,
                (SELECT SUM(CASE WHEN is_cf = 1 THEN 1 ELSE 0 END) FROM `assets`) as cf_count,
                (SELECT SUM(CASE WHEN is_cf = 0 THEN 1 ELSE 0 END) FROM `assets`) as non_cf_count,
                (SELECT SUM(CASE WHEN is_wp = 1 THEN 1 ELSE 0 END) FROM `assets`) as wp_count,
                (SELECT COUNT(*) FROM `sidesites`) as sidesite_count,
                (SELECT SUM(CASE WHEN is_wp = 1 THEN 1 ELSE 0 END) FROM `sidesites`) as wp_sidesite_count"
        );

        return [
            'project_count' => (int)($stats['project_count'] ?? 0),
            'asset_count' => (int)($stats['asset_count'] ?? 0),
            'cf_count' => (int)($stats['cf_count'] ?? 0),
            'non_cf_count' => (int)($stats['non_cf_count'] ?? 0),
            'wp_count' => (int)($stats['wp_count'] ?? 0),
            'sidesite_count' => (int)($stats['sidesite_count'] ?? 0),
            'wp_sidesite_count' => (int)($stats['wp_sidesite_count'] ?? 0),
        ];
    }

    /**
     * 获取全局TOP组件
     */
    private function getTopComponents(int $limit = 10): array
    {
        return Database::query(
            "SELECT `component_name`, SUM(`asset_count`) as asset_count, SUM(`sidesite_count`) as sidesite_count
            FROM `component_stats`
            WHERE `project_id` IS NOT NULL
            GROUP BY `component_name`
            ORDER BY asset_count + sidesite_count DESC
            LIMIT {$limit}"
        );
    }

    /**
     * 获取最近活动
     */
    private function getRecentActivities(): array
    {
        // 最近创建的项目
        $recentProjects = Database::query(
            "SELECT `id`, `name`, `created_at` FROM `projects` ORDER BY `created_at` DESC LIMIT 5"
        );

        // 最近导入的资产
        $recentAssets = Database::query(
            "SELECT a.`id`, a.`domain`, a.`imported_at`, p.`name` as project_name
            FROM `assets` a
            LEFT JOIN `projects` p ON a.project_id = p.id
            ORDER BY a.`imported_at` DESC LIMIT 5"
        );

        // 最近完成的扫描
        $recentScans = Database::query(
            "SELECT t.`domain`, t.`finished_at`, t.`status`, p.`name` as project_name
            FROM `scan_tasks` t
            LEFT JOIN `projects` p ON t.project_id = p.id
            WHERE t.`status` IN (2, 3)
            ORDER BY t.`finished_at` DESC LIMIT 5"
        );

        // 最近失败的任务
        $recentFailures = Database::query(
            "SELECT `domain`, `failure_reason`, `created_at`
            FROM `failure_logs`
            WHERE `is_ignored` = 0
            ORDER BY `created_at` DESC LIMIT 5"
        );

        return [
            'recent_projects' => $recentProjects,
            'recent_assets' => $recentAssets,
            'recent_scans' => $recentScans,
            'recent_failures' => $recentFailures,
        ];
    }

    /**
     * 获取项目排行
     */
    private function getProjectRankings(int $limit = 10): array
    {
        return Database::query(
            "SELECT
                p.`id`,
                p.`name`,
                p.`asset_count`,
                p.`wp_count`,
                ROUND(p.`wp_count` * 100.0 / NULLIF(p.`asset_count`, 0), 1) as wp_ratio
            FROM `projects` p
            WHERE p.`asset_count` > 0
            ORDER BY p.`asset_count` DESC
            LIMIT {$limit}"
        );
    }
}
