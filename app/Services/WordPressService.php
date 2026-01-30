<?php
namespace App\Services;

/**
 * WordPress检测服务
 */
class WordPressService
{
    // WP核心namespace(需要过滤)
    private static array $coreNamespaces = [
        'wp/v2',
        'wp-site-health/v1',
        'wp-block-editor/v1',
        'oembed/1.0',
        'wp-abilities/v1',
    ];

    /**
     * 检测是否为WordPress站点
     */
    public static function detect(string $domain): array
    {
        $domain = cleanDomain($domain);

        $result = [
            'is_wp' => false,
            'components' => [],
            'error' => null,
        ];

        // 请求 /wp-json/
        $wpJsonUrl = "https://{$domain}/wp-json/";
        $response = static::httpGet($wpJsonUrl);

        // 如果HTTPS失败,尝试HTTP
        if ($response === false) {
            $wpJsonUrl = "http://{$domain}/wp-json/";
            $response = static::httpGet($wpJsonUrl);
        }

        if ($response === false) {
            $result['error'] = '无法连接到目标站点';
            return $result;
        }

        // 检查响应
        if ($response['code'] !== 200) {
            // 非200状态码,不是WP或wp-json被禁用
            return $result;
        }

        // 尝试解析JSON
        $data = json_decode($response['body'], true);

        if (!$data || !is_array($data)) {
            // 无效JSON,不是WP
            return $result;
        }

        // 检查是否有namespaces字段
        if (!isset($data['namespaces']) || !is_array($data['namespaces'])) {
            // 可能是WP但没有标准格式
            // 检查是否有其他WP特征
            if (isset($data['name']) || isset($data['home']) || isset($data['gmt_offset'])) {
                $result['is_wp'] = true;
            }
            return $result;
        }

        // 是WordPress站点
        $result['is_wp'] = true;

        // 提取组件(过滤掉核心namespace)
        $namespaces = $data['namespaces'];
        $coreNamespaces = self::$coreNamespaces;

        // 从配置加载(如果有自定义)
        $configCore = config('wp_core_namespaces', []);
        if (!empty($configCore)) {
            $coreNamespaces = $configCore;
        }

        $components = [];
        foreach ($namespaces as $namespace) {
            // 过滤核心namespace
            if (!in_array($namespace, $coreNamespaces)) {
                $components[] = $namespace;
            }
        }

        $result['components'] = $components;

        return $result;
    }

    /**
     * 批量检测
     */
    public static function batchDetect(array $domains, int $interval = 1): array
    {
        $results = [];

        foreach ($domains as $domain) {
            $results[$domain] = static::detect($domain);

            // 请求间隔
            if ($interval > 0) {
                sleep($interval);
            }
        }

        return $results;
    }

    /**
     * HTTP GET请求
     */
    private static function httpGet(string $url): array|false
    {
        $timeout = (int)setting('scan_timeout', 30);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'follow_location' => 1,
                'max_redirects' => 5,
                'ignore_errors' => true,
                'header' => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept: application/json',
                ],
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            return false;
        }

        // 获取响应码
        $code = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/^HTTP\/\d+\.\d+\s+(\d+)/', $header, $matches)) {
                    $code = (int)$matches[1];
                }
            }
        }

        return [
            'code' => $code,
            'body' => $body,
        ];
    }

    /**
     * 获取WP站点详细信息
     */
    public static function getWpInfo(string $domain): ?array
    {
        $domain = cleanDomain($domain);
        $wpJsonUrl = "https://{$domain}/wp-json/";

        $response = static::httpGet($wpJsonUrl);
        if ($response === false) {
            $wpJsonUrl = "http://{$domain}/wp-json/";
            $response = static::httpGet($wpJsonUrl);
        }

        if ($response === false || $response['code'] !== 200) {
            return null;
        }

        $data = json_decode($response['body'], true);
        if (!$data) {
            return null;
        }

        return [
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'url' => $data['url'] ?? null,
            'home' => $data['home'] ?? null,
            'gmt_offset' => $data['gmt_offset'] ?? null,
            'timezone_string' => $data['timezone_string'] ?? null,
            'namespaces' => $data['namespaces'] ?? [],
        ];
    }

    /**
     * 检查特定插件是否存在
     */
    public static function hasPlugin(string $domain, string $pluginNamespace): bool
    {
        $result = static::detect($domain);
        if (!$result['is_wp']) {
            return false;
        }

        // 在组件列表中查找
        foreach ($result['components'] as $component) {
            if (stripos($component, $pluginNamespace) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取常见WP插件对应的namespace
     */
    public static function getCommonPluginNamespaces(): array
    {
        return [
            'yoast/v1' => 'Yoast SEO',
            'elementor/v1' => 'Elementor',
            'wpml/v1' => 'WPML',
            'wc/v3' => 'WooCommerce',
            'wc/v2' => 'WooCommerce',
            'wc/v1' => 'WooCommerce',
            'jetpack/v4' => 'Jetpack',
            'acf/v3' => 'Advanced Custom Fields',
            'rankmath/v1' => 'Rank Math',
            'contact-form-7/v1' => 'Contact Form 7',
            'wp-graphql/v1' => 'WPGraphQL',
            'tribe/events/v1' => 'The Events Calendar',
            'meow/v1' => 'Meow Apps',
            'redirection/v1' => 'Redirection',
            'wordfence/v1' => 'Wordfence',
        ];
    }
}
