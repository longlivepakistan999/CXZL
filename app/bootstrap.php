<?php
/**
 * 应用引导文件
 */

// 错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 定义根目录
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('PUBLIC_PATH', ROOT_PATH . '/public');

// 加载配置
$config = require CONFIG_PATH . '/app.php';

// 设置时区
date_default_timezone_set($config['app']['timezone']);

// 自动加载
spl_autoload_register(function ($class) {
    // 命名空间映射
    $prefixes = [
        'App\\Controllers\\' => APP_PATH . '/Controllers/',
        'App\\Models\\' => APP_PATH . '/Models/',
        'App\\Services\\' => APP_PATH . '/Services/',
        'App\\Middleware\\' => APP_PATH . '/Middleware/',
        'App\\Helpers\\' => APP_PATH . '/Helpers/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require $file;
                return true;
            }
        }
    }
    return false;
});

// 加载辅助函数
require_once APP_PATH . '/Helpers/functions.php';

// 初始化数据库连接
App\Helpers\Database::init($config['database']);

// 返回配置供其他文件使用
return $config;
