<?php
namespace App\Services;

use App\Models\Asset;
use App\Models\Project;
use App\Models\ScanTask;

/**
 * 导入服务
 */
class ImportService
{
    /**
     * 解析导入内容
     */
    public static function parseContent(string $content): array
    {
        $lines = preg_split('/[\r\n]+/', $content);
        $domains = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // 清理域名
            $domain = cleanDomain($line);
            if (isValidDomain($domain)) {
                $domains[] = $domain;
            }
        }

        return array_unique($domains);
    }

    /**
     * 解析CSV文件
     */
    public static function parseCsv(string $filePath, string $column = 'domain'): array
    {
        $domains = [];

        if (($handle = fopen($filePath, 'r')) !== false) {
            $header = fgetcsv($handle);
            $columnIndex = array_search($column, $header);

            if ($columnIndex === false) {
                // 如果没有找到列名,假设第一列是域名
                $columnIndex = 0;
                rewind($handle);
            }

            while (($row = fgetcsv($handle)) !== false) {
                if (isset($row[$columnIndex])) {
                    $domain = cleanDomain($row[$columnIndex]);
                    if (isValidDomain($domain)) {
                        $domains[] = $domain;
                    }
                }
            }

            fclose($handle);
        }

        return array_unique($domains);
    }

    /**
     * 解析TXT文件
     */
    public static function parseTxt(string $filePath): array
    {
        $content = file_get_contents($filePath);
        return static::parseContent($content);
    }

    /**
     * 解析Excel文件 (简单的xlsx解析)
     */
    public static function parseExcel(string $filePath): array
    {
        $domains = [];

        // 使用ZipArchive读取xlsx
        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            return $domains;
        }

        // 读取共享字符串
        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml) {
            $xml = simplexml_load_string($sharedStringsXml);
            if ($xml) {
                foreach ($xml->si as $si) {
                    $sharedStrings[] = (string)$si->t;
                }
            }
        }

        // 读取第一个工作表
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml) {
            $xml = simplexml_load_string($sheetXml);
            if ($xml && isset($xml->sheetData)) {
                foreach ($xml->sheetData->row as $row) {
                    foreach ($row->c as $cell) {
                        $value = '';

                        // 检查单元格类型
                        $type = (string)$cell['t'];
                        if ($type === 's') {
                            // 共享字符串
                            $index = (int)$cell->v;
                            $value = $sharedStrings[$index] ?? '';
                        } else {
                            $value = (string)$cell->v;
                        }

                        $domain = cleanDomain($value);
                        if (isValidDomain($domain)) {
                            $domains[] = $domain;
                        }
                    }
                }
            }
        }

        $zip->close();

        return array_unique($domains);
    }

    /**
     * 处理上传文件
     */
    public static function parseUploadedFile(array $file): array
    {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        switch ($extension) {
            case 'txt':
                return static::parseTxt($file['tmp_name']);

            case 'csv':
                return static::parseCsv($file['tmp_name']);

            case 'xlsx':
            case 'xls':
                return static::parseExcel($file['tmp_name']);

            default:
                return [];
        }
    }

    /**
     * 执行导入
     */
    public static function import(int $projectId, array $domains, array $options = []): array
    {
        $skipDuplicates = $options['skip_duplicates'] ?? true;
        $tagIds = $options['tag_ids'] ?? [];
        $autoScan = $options['auto_scan'] ?? false;
        $batchSize = (int)config('import.batch_size', 1000);

        // 分批处理大文件
        $totalDomains = count($domains);
        $imported = 0;
        $skipped = 0;
        $errors = [];

        $chunks = array_chunk($domains, $batchSize);

        foreach ($chunks as $chunk) {
            $result = Asset::batchImport($projectId, $chunk, $skipDuplicates, $tagIds);
            $imported += $result['imported'];
            $skipped += $result['skipped'];
            $errors = array_merge($errors, $result['errors']);
        }

        // 如果启用自动扫描,创建扫描任务
        if ($autoScan && $imported > 0) {
            // 获取新导入的资产
            $newAssets = Asset::where([
                'project_id' => $projectId,
                'scan_status' => Asset::STATUS_PENDING,
            ], 1, $imported);

            ScanTask::batchCreate($projectId, $newAssets, ScanTask::TYPE_FIRST_SCAN);
        }

        return [
            'total' => $totalDomains,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * 预览导入
     */
    public static function preview(int $projectId, array $domains): array
    {
        $total = count($domains);

        // 检查重复
        $existingCount = 0;
        if (!empty($domains)) {
            $placeholders = implode(',', array_fill(0, count($domains), '?'));
            $existingCount = (int)\App\Helpers\Database::queryScalar(
                "SELECT COUNT(*) FROM `assets` WHERE `project_id` = ? AND `domain` IN ({$placeholders})",
                array_merge([$projectId], $domains)
            );
        }

        // 验证格式
        $invalid = 0;
        foreach ($domains as $domain) {
            if (!isValidDomain($domain)) {
                $invalid++;
            }
        }

        return [
            'total' => $total,
            'valid' => $total - $invalid,
            'invalid' => $invalid,
            'duplicates' => $existingCount,
            'new' => $total - $invalid - $existingCount,
        ];
    }
}
