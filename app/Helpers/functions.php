<?php
/**
 * 全局辅助函数
 */

use App\Helpers\Database;

/**
 * 获取配置值
 */
function config(string $key = null, $default = null)
{
    static $config = null;
    if ($config === null) {
        $config = require CONFIG_PATH . '/app.php';
    }

    if ($key === null) {
        return $config;
    }

    $keys = explode('.', $key);
    $value = $config;

    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return $default;
        }
        $value = $value[$k];
    }

    return $value;
}

/**
 * 获取系统设置
 */
function setting(string $key, $default = null)
{
    static $settings = null;

    if ($settings === null) {
        try {
            $rows = Database::query("SELECT `key`, `value` FROM `settings`");
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['key']] = $row['value'];
            }
        } catch (\Exception $e) {
            // 数据库未连接或表不存在时返回默认值
            $settings = [];
        }
    }

    return $settings[$key] ?? $default;
}

/**
 * 更新系统设置
 */
function updateSetting(string $key, $value): void
{
    Database::execute(
        "INSERT INTO `settings` (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?",
        [$key, $value, $value]
    );
}

/**
 * JSON响应
 */
function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 成功响应
 */
function success($data = null, string $message = 'success'): void
{
    jsonResponse([
        'code' => 0,
        'message' => $message,
        'data' => $data,
    ]);
}

/**
 * 错误响应
 */
function error(string $message, int $code = 1, $data = null): void
{
    jsonResponse([
        'code' => $code,
        'message' => $message,
        'data' => $data,
    ], $code >= 400 ? $code : 200);
}

/**
 * 获取请求参数
 */
function input(string $key = null, $default = null)
{
    static $input = null;

    if ($input === null) {
        $input = array_merge($_GET, $_POST);

        // 处理JSON请求体
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $jsonInput = json_decode(file_get_contents('php://input'), true);
            if (is_array($jsonInput)) {
                $input = array_merge($input, $jsonInput);
            }
        }
    }

    if ($key === null) {
        return $input;
    }

    return $input[$key] ?? $default;
}

/**
 * 获取整数参数
 */
function inputInt(string $key, int $default = 0): int
{
    return (int)(input($key) ?? $default);
}

/**
 * 获取数组参数
 */
function inputArray(string $key, array $default = []): array
{
    $value = input($key);
    if (is_array($value)) {
        return $value;
    }
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $default;
}

/**
 * 验证域名格式
 */
function isValidDomain(string $domain): bool
{
    // 移除协议前缀
    $domain = preg_replace('#^https?://#i', '', $domain);
    // 移除路径
    $domain = explode('/', $domain)[0];
    // 移除端口
    $domain = explode(':', $domain)[0];

    return (bool)preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain);
}

/**
 * 清理域名
 */
function cleanDomain(string $domain): string
{
    $domain = trim($domain);
    $domain = preg_replace('#^https?://#i', '', $domain);
    $domain = explode('/', $domain)[0];
    $domain = explode(':', $domain)[0];
    return strtolower($domain);
}

/**
 * 解析URL，提取域名和协议
 * @return array ['domain' => string, 'protocol' => string]
 */
function parseUrl(string $url): array
{
    $url = trim($url);

    // 提取协议
    $protocol = 'https'; // 默认https
    if (preg_match('#^(https?)://#i', $url, $matches)) {
        $protocol = strtolower($matches[1]);
    }

    // 提取域名
    $domain = preg_replace('#^https?://#i', '', $url);
    $domain = explode('/', $domain)[0];
    $domain = explode(':', $domain)[0];
    $domain = strtolower($domain);

    return [
        'domain' => $domain,
        'protocol' => $protocol,
    ];
}

/**
 * 构建完整URL
 */
function buildUrl(string $domain, string $protocol = 'https'): string
{
    $domain = cleanDomain($domain);
    return "{$protocol}://{$domain}";
}

/**
 * 验证IP地址
 */
function isValidIp(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

/**
 * 验证IPv4
 */
function isIPv4(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
}

/**
 * 验证IPv6
 */
function isIPv6(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
}

/**
 * IP转二进制(用于范围查询)
 */
function ipToBinary(string $ip): ?string
{
    $packed = inet_pton($ip);
    return $packed !== false ? $packed : null;
}

/**
 * 二进制转IP
 */
function binaryToIp(string $binary): ?string
{
    $ip = inet_ntop($binary);
    return $ip !== false ? $ip : null;
}

/**
 * 解析CIDR,返回起始和结束IP的二进制
 */
function parseCIDR(string $cidr): ?array
{
    $parts = explode('/', $cidr);
    if (count($parts) !== 2) {
        return null;
    }

    $ip = $parts[0];
    $prefix = (int)$parts[1];

    $packed = inet_pton($ip);
    if ($packed === false) {
        return null;
    }

    $isIPv6 = strlen($packed) === 16;
    $bits = $isIPv6 ? 128 : 32;

    if ($prefix < 0 || $prefix > $bits) {
        return null;
    }

    // 计算掩码
    $mask = str_repeat("\xff", (int)($prefix / 8));
    if ($prefix % 8 > 0) {
        $mask .= chr(0xff << (8 - ($prefix % 8)));
    }
    $mask = str_pad($mask, strlen($packed), "\x00");

    // 计算起始IP
    $start = $packed & $mask;

    // 计算结束IP
    $end = $packed | ~$mask;
    if ($isIPv6) {
        $end = $packed | str_pad(str_repeat("\xff", 16 - strlen($mask)), 16, "\x00", STR_PAD_LEFT);
    }

    return [
        'start' => $start,
        'end' => $end,
        'version' => $isIPv6 ? 6 : 4,
    ];
}

/**
 * 记录日志
 */
function logMessage(string $level, string $message, array $context = []): void
{
    $logPath = config('paths.logs') . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $logLine = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;

    file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);
}

/**
 * 快捷日志函数
 */
function logInfo(string $message, array $context = []): void
{
    logMessage('INFO', $message, $context);
}

function logError(string $message, array $context = []): void
{
    logMessage('ERROR', $message, $context);
}

function logDebug(string $message, array $context = []): void
{
    if (config('app.debug')) {
        logMessage('DEBUG', $message, $context);
    }
}

/**
 * 生成分页数据
 */
function paginate(int $total, int $page, int $perPage): array
{
    $totalPages = (int)ceil($total / $perPage);

    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $page,
        'total_pages' => $totalPages,
        'has_more' => $page < $totalPages,
    ];
}

/**
 * 格式化文件大小
 */
function formatFileSize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * 安全的HTML转义
 */
function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * CSRF Token生成
 */
function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 验证CSRF Token
 */
function verifyCsrfToken(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 获取当前URL
 */
function currentUrl(): string
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * 重定向
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}
