<?php
/**
 * 应用配置文件
 * 资产探测系统
 */

return [
    // 应用配置
    'app' => [
        'name' => '资产探测系统',
        'version' => '1.0.0',
        'debug' => true,
        'timezone' => 'Asia/Shanghai',
        'locale' => 'zh_CN',
    ],

    // 数据库配置
    'database' => [
        'driver' => 'mysql',
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: 3306,
        'database' => getenv('DB_DATABASE') ?: 'asset_detector',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        // 连接池配置
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
        ],
    ],

    // 扫描配置
    'scanner' => [
        'concurrent_tasks' => 50,          // 并发扫描任务数
        'viewdns_rate_limit' => 10,        // ViewDNS API限流(次/秒)
        'wp_check_interval' => 1,          // WP检测间隔(秒)
        'scan_timeout' => 30,              // 扫描超时(秒)
        'retry_count' => 3,                // 失败重试次数
        'retry_interval' => 5,             // 重试间隔(秒)
        'rescan_interval' => 5,            // 重复扫描保护间隔(分钟)
    ],

    // API配置
    'api' => [
        'viewdns' => [
            'key' => getenv('VIEWDNS_API_KEY') ?: '',
            'base_url' => 'https://api.viewdns.info/',
        ],
        'cloudflare' => [
            'ipv4_url' => 'https://www.cloudflare.com/ips-v4',
            'ipv6_url' => 'https://www.cloudflare.com/ips-v6',
            'update_interval' => 86400,    // 更新间隔(秒),默认1天
        ],
    ],

    // 导入导出配置
    'import' => [
        'batch_size' => 1000,              // 批量导入每批数量
        'max_file_size' => 50 * 1024 * 1024, // 最大上传文件大小(50MB)
        'allowed_extensions' => ['txt', 'csv', 'xlsx', 'xls'],
    ],

    'export' => [
        'chunk_size' => 100000,            // 大导出分片大小
        'expire_hours' => 24,              // 导出文件过期时间(小时)
        'storage_path' => __DIR__ . '/../storage/exports/',
    ],

    // WP核心namespace(需要过滤)
    'wp_core_namespaces' => [
        'wp/v2',
        'wp-site-health/v1',
        'wp-block-editor/v1',
        'oembed/1.0',
        'wp-abilities/v1',
    ],

    // 路径配置
    'paths' => [
        'storage' => __DIR__ . '/../storage/',
        'logs' => __DIR__ . '/../storage/logs/',
        'cache' => __DIR__ . '/../storage/cache/',
        'exports' => __DIR__ . '/../storage/exports/',
        'uploads' => __DIR__ . '/../public/uploads/',
    ],

    // 分页配置
    'pagination' => [
        'default_per_page' => 20,
        'max_per_page' => 100,
    ],
];
