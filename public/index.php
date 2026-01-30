<?php
/**
 * 应用入口文件
 * 资产探测系统
 */

session_start();

// 加载引导文件
$config = require_once __DIR__ . '/../app/bootstrap.php';

// 简单路由
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// 规范化URI
$uri = '/' . trim($uri, '/');
if ($uri !== '/') {
    $uri = rtrim($uri, '/');
}

// API路由
$apiRoutes = [
    // 项目
    'GET /api/projects' => ['App\\Controllers\\ProjectController', 'index'],
    'POST /api/projects' => ['App\\Controllers\\ProjectController', 'store'],
    'GET /api/projects/{id}' => ['App\\Controllers\\ProjectController', 'show'],
    'PUT /api/projects/{id}' => ['App\\Controllers\\ProjectController', 'update'],
    'DELETE /api/projects/{id}' => ['App\\Controllers\\ProjectController', 'destroy'],
    'POST /api/projects/{id}/scan' => ['App\\Controllers\\ProjectController', 'scan'],

    // 资产
    'GET /api/assets' => ['App\\Controllers\\AssetController', 'index'],
    'POST /api/assets' => ['App\\Controllers\\AssetController', 'store'],
    'POST /api/assets/import' => ['App\\Controllers\\AssetController', 'import'],
    'POST /api/assets/import/preview' => ['App\\Controllers\\AssetController', 'importPreview'],
    'GET /api/assets/{id}' => ['App\\Controllers\\AssetController', 'show'],
    'PUT /api/assets/{id}' => ['App\\Controllers\\AssetController', 'update'],
    'DELETE /api/assets/{id}' => ['App\\Controllers\\AssetController', 'destroy'],
    'POST /api/assets/{id}/scan' => ['App\\Controllers\\AssetController', 'scan'],
    'POST /api/assets/batch-scan' => ['App\\Controllers\\AssetController', 'batchScan'],
    'POST /api/assets/batch-delete' => ['App\\Controllers\\AssetController', 'batchDelete'],
    'POST /api/assets/batch-tag' => ['App\\Controllers\\AssetController', 'batchTag'],
    'POST /api/assets/rescan-non-wp' => ['App\\Controllers\\AssetController', 'rescanNonWp'],
    'POST /api/assets/rescan-failed' => ['App\\Controllers\\AssetController', 'rescanFailed'],
    'POST /api/assets/rescan-pending' => ['App\\Controllers\\AssetController', 'rescanPending'],
    'GET /api/assets/export-wp' => ['App\\Controllers\\AssetController', 'exportWpAssets'],

    // 旁站
    'GET /api/sidesites' => ['App\\Controllers\\SidesiteController', 'index'],
    'GET /api/sidesites/{id}' => ['App\\Controllers\\SidesiteController', 'show'],
    'GET /api/sidesites/project/{id}/stats' => ['App\\Controllers\\SidesiteController', 'projectStats'],
    'GET /api/sidesites/project/{id}/export' => ['App\\Controllers\\SidesiteController', 'exportProject'],
    'GET /api/sidesites/asset/{id}/stats' => ['App\\Controllers\\SidesiteController', 'assetStats'],
    'GET /api/sidesites/asset/{id}/export' => ['App\\Controllers\\SidesiteController', 'exportAsset'],
    'POST /api/sidesites/{id}/scan' => ['App\\Controllers\\SidesiteController', 'scan'],
    'POST /api/sidesites/batch-scan' => ['App\\Controllers\\SidesiteController', 'batchScan'],

    // 扫描任务
    'GET /api/tasks' => ['App\\Controllers\\TaskController', 'index'],
    'GET /api/tasks/stats' => ['App\\Controllers\\TaskController', 'stats'],
    'POST /api/tasks/{id}/cancel' => ['App\\Controllers\\TaskController', 'cancel'],
    'POST /api/tasks/{id}/retry' => ['App\\Controllers\\TaskController', 'retry'],
    'POST /api/tasks/{id}/prioritize' => ['App\\Controllers\\TaskController', 'prioritize'],

    // 定时任务
    'GET /api/scheduled-tasks' => ['App\\Controllers\\ScheduledTaskController', 'index'],
    'POST /api/scheduled-tasks' => ['App\\Controllers\\ScheduledTaskController', 'store'],
    'PUT /api/scheduled-tasks/{id}' => ['App\\Controllers\\ScheduledTaskController', 'update'],
    'DELETE /api/scheduled-tasks/{id}' => ['App\\Controllers\\ScheduledTaskController', 'destroy'],
    'POST /api/scheduled-tasks/{id}/toggle' => ['App\\Controllers\\ScheduledTaskController', 'toggle'],
    'POST /api/scheduled-tasks/{id}/run' => ['App\\Controllers\\ScheduledTaskController', 'run'],

    // 失败日志
    'GET /api/failures' => ['App\\Controllers\\FailureController', 'index'],
    'POST /api/failures/{id}/retry' => ['App\\Controllers\\FailureController', 'retry'],
    'POST /api/failures/{id}/ignore' => ['App\\Controllers\\FailureController', 'ignore'],
    'POST /api/failures/batch-retry' => ['App\\Controllers\\FailureController', 'batchRetry'],
    'POST /api/failures/batch-ignore' => ['App\\Controllers\\FailureController', 'batchIgnore'],
    'DELETE /api/failures' => ['App\\Controllers\\FailureController', 'clear'],

    // 导出
    'GET /api/exports' => ['App\\Controllers\\ExportController', 'index'],
    'POST /api/exports/assets' => ['App\\Controllers\\ExportController', 'exportAssets'],
    'POST /api/exports/sidesites' => ['App\\Controllers\\ExportController', 'exportSidesites'],
    'POST /api/exports/quick' => ['App\\Controllers\\ExportController', 'quickExport'],
    'GET /api/exports/{id}/download' => ['App\\Controllers\\ExportController', 'download'],
    'DELETE /api/exports/{id}' => ['App\\Controllers\\ExportController', 'destroy'],

    // 标签
    'GET /api/tags' => ['App\\Controllers\\TagController', 'index'],
    'POST /api/tags' => ['App\\Controllers\\TagController', 'store'],
    'PUT /api/tags/{id}' => ['App\\Controllers\\TagController', 'update'],
    'DELETE /api/tags/{id}' => ['App\\Controllers\\TagController', 'destroy'],

    // 设置
    'GET /api/settings' => ['App\\Controllers\\SettingController', 'index'],
    'PUT /api/settings' => ['App\\Controllers\\SettingController', 'update'],
    'POST /api/settings/test-viewdns' => ['App\\Controllers\\SettingController', 'testViewDns'],
    'POST /api/settings/update-cf-ips' => ['App\\Controllers\\SettingController', 'updateCfIps'],

    // 仪表盘
    'GET /api/dashboard' => ['App\\Controllers\\DashboardController', 'index'],
    'GET /api/dashboard/stats' => ['App\\Controllers\\DashboardController', 'stats'],

    // 组件管理
    'GET /api/components' => ['App\\Controllers\\ComponentController', 'index'],
    'GET /api/components/{component}/assets' => ['App\\Controllers\\ComponentController', 'assets'],
    'GET /api/components/{component}/sidesites' => ['App\\Controllers\\ComponentController', 'sidesites'],
    'GET /api/components/{component}/export-assets' => ['App\\Controllers\\ComponentController', 'exportAssets'],
    'GET /api/components/{component}/export-sidesites' => ['App\\Controllers\\ComponentController', 'exportSidesites'],
    'POST /api/components/refresh' => ['App\\Controllers\\ComponentController', 'refresh'],
];

// 页面路由
$pageRoutes = [
    '/' => 'pages/dashboard/index.php',
    '/dashboard' => 'pages/dashboard/index.php',
    '/projects' => 'pages/projects/index.php',
    '/projects/{id}' => 'pages/projects/detail.php',
    '/assets/{id}' => 'pages/assets/detail.php',
    '/assets/{id}/sidesites' => 'pages/assets/sidesites.php',
    '/tasks' => 'pages/tasks/index.php',
    '/scheduled-tasks' => 'pages/tasks/scheduled.php',
    '/failures' => 'pages/tasks/failures.php',
    '/exports' => 'pages/exports/index.php',
    '/components' => 'pages/components/index.php',
    '/tags' => 'pages/settings/tags.php',
    '/settings' => 'pages/settings/index.php',
];

/**
 * 匹配路由
 */
function matchRoute(string $method, string $uri, array $routes): ?array
{
    foreach ($routes as $route => $handler) {
        $parts = explode(' ', $route, 2);
        $routeMethod = $parts[0];
        $routePath = $parts[1] ?? $route;

        if ($routeMethod !== $method && strpos($route, ' ') !== false) {
            continue;
        }

        // 转换路由参数为正则
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $uri, $matches)) {
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            return ['handler' => $handler, 'params' => $params];
        }
    }
    return null;
}

// 处理API请求
if (strpos($uri, '/api/') === 0) {
    header('Content-Type: application/json; charset=utf-8');

    $route = matchRoute($method, $uri, $apiRoutes);

    if ($route) {
        try {
            $controller = new $route['handler'][0]();
            $action = $route['handler'][1];
            $params = $route['params'];

            // 调用控制器方法
            if (!empty($params)) {
                $controller->$action(...array_values($params));
            } else {
                $controller->$action();
            }
        } catch (\Exception $e) {
            error($e->getMessage(), 500);
        }
    } else {
        error('API接口不存在', 404);
    }
    exit;
}

// 处理页面请求
foreach ($pageRoutes as $routePath => $viewFile) {
    // 转换路由参数为正则
    $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $routePath);
    $pattern = '#^' . $pattern . '$#';

    if (preg_match($pattern, $uri, $matches)) {
        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        include __DIR__ . '/../resources/views/' . $viewFile;
        exit;
    }
}

// 静态文件处理
$staticExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf'];
$extension = pathinfo($uri, PATHINFO_EXTENSION);

if (in_array($extension, $staticExtensions)) {
    $filePath = __DIR__ . $uri;
    if (file_exists($filePath)) {
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
        ];
        header('Content-Type: ' . ($mimeTypes[$extension] ?? 'application/octet-stream'));
        readfile($filePath);
        exit;
    }
}

// 404页面
http_response_code(404);
include __DIR__ . '/../resources/views/pages/404.php';
