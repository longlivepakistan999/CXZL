<?php
namespace App\Services;

/**
 * ViewDNS API服务 - 旁站查询
 */
class ViewDnsService
{
    private static ?string $apiKey = null;
    private static float $lastRequestTime = 0;
    private static int $rateLimit = 10; // 默认每秒10次

    /**
     * 初始化API Key
     */
    private static function init(): void
    {
        if (self::$apiKey === null) {
            self::$apiKey = setting('viewdns_api_key', config('api.viewdns.key', ''));
            self::$rateLimit = (int)setting('viewdns_rate_limit', 10);
        }
    }

    /**
     * 查询旁站(Reverse IP)
     */
    public static function reverseLookup(string $ip): array
    {
        self::init();

        if (empty(self::$apiKey)) {
            logError("ViewDNS API Key未配置");
            return [
                'success' => false,
                'domains' => [],
                'error' => 'ViewDNS API Key未配置',
            ];
        }

        // 限流控制
        static::rateLimit();

        $url = sprintf(
            'https://api.viewdns.info/reverseip/?host=%s&apikey=%s&output=json',
            urlencode($ip),
            urlencode(self::$apiKey)
        );

        logInfo("ViewDNS API请求: {$ip}");

        $response = static::httpGet($url);

        if ($response === false) {
            logError("ViewDNS网络请求失败: {$ip}");
            return [
                'success' => false,
                'domains' => [],
                'error' => '网络请求失败',
            ];
        }

        $data = json_decode($response, true);

        if (!$data) {
            logError("ViewDNS JSON解析失败: {$ip}", ['response' => substr($response, 0, 500)]);
            return [
                'success' => false,
                'domains' => [],
                'error' => 'JSON解析失败',
            ];
        }

        // 检查API响应状态
        if (isset($data['response']['error'])) {
            logError("ViewDNS API错误: {$ip}", ['error' => $data['response']['error']]);
            return [
                'success' => false,
                'domains' => [],
                'error' => $data['response']['error'],
            ];
        }

        // 提取域名列表
        $domains = [];
        if (isset($data['response']['domains']) && is_array($data['response']['domains'])) {
            foreach ($data['response']['domains'] as $item) {
                if (isset($item['name'])) {
                    $domain = cleanDomain($item['name']);
                    if (isValidDomain($domain)) {
                        $domains[] = $domain;
                    }
                }
            }
        }

        $uniqueDomains = array_unique($domains);
        logInfo("ViewDNS查询成功: {$ip}", ['域名数' => count($uniqueDomains)]);

        return [
            'success' => true,
            'domains' => $uniqueDomains,
            'count' => count($uniqueDomains),
            'error' => null,
        ];
    }

    /**
     * 查询IP历史
     */
    public static function ipHistory(string $domain): array
    {
        self::init();

        if (empty(self::$apiKey)) {
            return [
                'success' => false,
                'history' => [],
                'error' => 'ViewDNS API Key未配置',
            ];
        }

        static::rateLimit();

        $url = sprintf(
            'https://api.viewdns.info/iphistory/?domain=%s&apikey=%s&output=json',
            urlencode($domain),
            urlencode(self::$apiKey)
        );

        $response = static::httpGet($url);

        if ($response === false) {
            return [
                'success' => false,
                'history' => [],
                'error' => '网络请求失败',
            ];
        }

        $data = json_decode($response, true);

        if (!$data || isset($data['response']['error'])) {
            return [
                'success' => false,
                'history' => [],
                'error' => $data['response']['error'] ?? 'API错误',
            ];
        }

        $history = [];
        if (isset($data['response']['records']) && is_array($data['response']['records'])) {
            foreach ($data['response']['records'] as $record) {
                $history[] = [
                    'ip' => $record['ip'] ?? '',
                    'location' => $record['location'] ?? '',
                    'owner' => $record['owner'] ?? '',
                    'last_seen' => $record['lastseen'] ?? '',
                ];
            }
        }

        return [
            'success' => true,
            'history' => $history,
            'error' => null,
        ];
    }

    /**
     * 限流控制
     */
    private static function rateLimit(): void
    {
        $minInterval = 1000000 / self::$rateLimit; // 微秒
        $elapsed = (microtime(true) - self::$lastRequestTime) * 1000000;

        if ($elapsed < $minInterval) {
            usleep((int)($minInterval - $elapsed));
        }

        self::$lastRequestTime = microtime(true);
    }

    /**
     * HTTP GET请求
     */
    private static function httpGet(string $url): string|false
    {
        $timeout = (int)setting('scan_timeout', 30);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'ignore_errors' => true,
                'header' => [
                    'User-Agent: AssetDetector/1.0',
                    'Accept: application/json',
                ],
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        return @file_get_contents($url, false, $context);
    }

    /**
     * 测试API连接
     */
    public static function testConnection(): array
    {
        self::init();

        if (empty(self::$apiKey)) {
            return [
                'success' => false,
                'error' => 'API Key未配置',
            ];
        }

        // 用一个知名IP测试
        $result = static::reverseLookup('8.8.8.8');

        return [
            'success' => $result['success'],
            'error' => $result['error'],
        ];
    }

    /**
     * 设置API Key
     */
    public static function setApiKey(string $key): void
    {
        self::$apiKey = $key;
        updateSetting('viewdns_api_key', $key);
    }
}
