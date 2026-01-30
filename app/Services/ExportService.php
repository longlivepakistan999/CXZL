<?php
namespace App\Services;

use App\Models\Asset;
use App\Models\Sidesite;
use App\Models\ExportRecord;
use App\Helpers\Database;

/**
 * 导出服务
 */
class ExportService
{
    /**
     * 导出资产
     */
    public static function exportAssets(int $projectId, array $filters = [], array $fields = [], string $format = 'xlsx'): array
    {
        // 创建导出记录
        $recordId = ExportRecord::createExport($projectId, 'assets', json_encode($filters), $format);

        try {
            // 获取数据
            $filters['project_id'] = $projectId;
            $data = static::fetchAssets($filters, $fields);

            // 生成文件
            $filePath = static::generateFile($data, $format, 'assets');
            $fileSize = filesize($filePath);

            // 更新记录
            ExportRecord::complete($recordId, $filePath, count($data), $fileSize);

            return [
                'success' => true,
                'record_id' => $recordId,
                'file_path' => $filePath,
                'count' => count($data),
            ];

        } catch (\Exception $e) {
            ExportRecord::markFailed($recordId);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 导出旁站
     */
    public static function exportSidesites(int $projectId, array $filters = [], string $format = 'xlsx'): array
    {
        $recordId = ExportRecord::createExport($projectId, 'sidesites', json_encode($filters), $format);

        try {
            $filters['project_id'] = $projectId;
            $data = static::fetchSidesites($filters);
            $filePath = static::generateFile($data, $format, 'sidesites');
            $fileSize = filesize($filePath);

            ExportRecord::complete($recordId, $filePath, count($data), $fileSize);

            return [
                'success' => true,
                'record_id' => $recordId,
                'file_path' => $filePath,
                'count' => count($data),
            ];

        } catch (\Exception $e) {
            ExportRecord::markFailed($recordId);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 快速导出(仅域名)
     */
    public static function quickExport(int $projectId, string $type, array $filters = []): array
    {
        $filters['project_id'] = $projectId;

        switch ($type) {
            case 'all_assets':
                $domains = Database::query(
                    "SELECT `domain` FROM `assets` WHERE `project_id` = ?",
                    [$projectId]
                );
                break;

            case 'wp_assets':
                $domains = Database::query(
                    "SELECT `domain` FROM `assets` WHERE `project_id` = ? AND `is_wp` = 1",
                    [$projectId]
                );
                break;

            case 'non_wp_assets':
                $domains = Database::query(
                    "SELECT `domain` FROM `assets` WHERE `project_id` = ? AND `is_wp` = 0",
                    [$projectId]
                );
                break;

            case 'cf_assets':
                $domains = Database::query(
                    "SELECT `domain` FROM `assets` WHERE `project_id` = ? AND `is_cf` = 1",
                    [$projectId]
                );
                break;

            case 'non_cf_assets':
                $domains = Database::query(
                    "SELECT `domain` FROM `assets` WHERE `project_id` = ? AND `is_cf` = 0",
                    [$projectId]
                );
                break;

            case 'all_sidesites':
                $domains = Database::query(
                    "SELECT `domain` FROM `sidesites` WHERE `project_id` = ?",
                    [$projectId]
                );
                break;

            case 'wp_sidesites':
                $domains = Database::query(
                    "SELECT `domain` FROM `sidesites` WHERE `project_id` = ? AND `is_wp` = 1",
                    [$projectId]
                );
                break;

            default:
                return ['success' => false, 'error' => '未知导出类型'];
        }

        $domainList = array_column($domains, 'domain');

        // 生成TXT文件
        $filename = $type . '_' . date('YmdHis') . '.txt';
        $filePath = config('paths.exports') . $filename;

        file_put_contents($filePath, implode("\n", $domainList));

        return [
            'success' => true,
            'file_path' => $filePath,
            'filename' => $filename,
            'count' => count($domainList),
        ];
    }

    /**
     * 获取资产数据
     */
    private static function fetchAssets(array $filters, array $fields = []): array
    {
        $where = [];
        $params = [];

        if (isset($filters['project_id'])) {
            $where[] = "`project_id` = ?";
            $params[] = $filters['project_id'];
        }

        if (isset($filters['is_cf']) && $filters['is_cf'] !== '') {
            $where[] = "`is_cf` = ?";
            $params[] = (int)$filters['is_cf'];
        }

        if (isset($filters['is_wp']) && $filters['is_wp'] !== '') {
            $where[] = "`is_wp` = ?";
            $params[] = (int)$filters['is_wp'];
        }

        if (isset($filters['asset_ids']) && !empty($filters['asset_ids'])) {
            $placeholders = implode(',', array_fill(0, count($filters['asset_ids']), '?'));
            $where[] = "`id` IN ({$placeholders})";
            $params = array_merge($params, $filters['asset_ids']);
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // 使用无缓冲查询处理大数据
        $sql = "SELECT * FROM `assets` {$whereClause}";

        $data = [];
        foreach (Database::unbufferedQuery($sql, $params) as $row) {
            $data[] = static::formatAssetRow($row, $fields);
        }

        return $data;
    }

    /**
     * 获取旁站数据
     */
    private static function fetchSidesites(array $filters): array
    {
        $where = [];
        $params = [];

        if (isset($filters['project_id'])) {
            $where[] = "`project_id` = ?";
            $params[] = $filters['project_id'];
        }

        if (isset($filters['asset_id'])) {
            $where[] = "`asset_id` = ?";
            $params[] = $filters['asset_id'];
        }

        if (isset($filters['is_wp']) && $filters['is_wp'] !== '') {
            $where[] = "`is_wp` = ?";
            $params[] = (int)$filters['is_wp'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT s.*, a.domain as asset_domain
                FROM `sidesites` s
                LEFT JOIN `assets` a ON s.asset_id = a.id
                {$whereClause}";

        return Database::query($sql, $params);
    }

    /**
     * 格式化资产行
     */
    private static function formatAssetRow(array $row, array $fields = []): array
    {
        $allFields = [
            'domain' => $row['domain'],
            'ip' => $row['ip'] ?? '',
            'is_cf' => $row['is_cf'] == 1 ? '是' : '否',
            'is_wp' => $row['is_wp'] == 1 ? '是' : '否',
            'components' => is_string($row['components']) ? $row['components'] : json_encode($row['components'] ?? []),
            'component_count' => $row['component_count'] ?? 0,
            'sidesite_count' => $row['sidesite_count'] ?? 0,
            'wp_sidesite_count' => $row['wp_sidesite_count'] ?? 0,
            'scan_status' => Asset::STATUS_LABELS[$row['scan_status']] ?? '未知',
            'scanned_at' => $row['scanned_at'] ?? '',
            'imported_at' => $row['imported_at'] ?? '',
            'remark' => $row['remark'] ?? '',
        ];

        if (empty($fields)) {
            return $allFields;
        }

        return array_intersect_key($allFields, array_flip($fields));
    }

    /**
     * 生成导出文件
     */
    private static function generateFile(array $data, string $format, string $type): string
    {
        $filename = $type . '_' . date('YmdHis');
        $exportPath = config('paths.exports');

        if (!is_dir($exportPath)) {
            mkdir($exportPath, 0755, true);
        }

        switch ($format) {
            case 'csv':
                return static::generateCsv($data, $exportPath . $filename . '.csv');

            case 'json':
                return static::generateJson($data, $exportPath . $filename . '.json');

            case 'txt':
                return static::generateTxt($data, $exportPath . $filename . '.txt');

            case 'xlsx':
            default:
                return static::generateXlsx($data, $exportPath . $filename . '.xlsx');
        }
    }

    /**
     * 生成CSV
     */
    private static function generateCsv(array $data, string $filePath): string
    {
        $handle = fopen($filePath, 'w');

        // BOM for Excel UTF-8
        fwrite($handle, "\xEF\xBB\xBF");

        if (!empty($data)) {
            // 写入表头
            fputcsv($handle, array_keys($data[0]));

            // 写入数据
            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
        }

        fclose($handle);
        return $filePath;
    }

    /**
     * 生成JSON
     */
    private static function generateJson(array $data, string $filePath): string
    {
        file_put_contents($filePath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $filePath;
    }

    /**
     * 生成TXT(仅域名)
     */
    private static function generateTxt(array $data, string $filePath): string
    {
        $domains = array_column($data, 'domain');
        file_put_contents($filePath, implode("\n", $domains));
        return $filePath;
    }

    /**
     * 生成简单XLSX
     */
    private static function generateXlsx(array $data, string $filePath): string
    {
        // 简单的XLSX生成(不依赖第三方库)
        $zip = new \ZipArchive();
        $zip->open($filePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        // [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
            <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
            <Default Extension="xml" ContentType="application/xml"/>
            <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
            <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
            <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
        </Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
            <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
        </Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // xl/_rels/workbook.xml.rels
        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
            <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
            <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
        </Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);

        // xl/workbook.xml
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
            <sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>
        </workbook>';
        $zip->addFromString('xl/workbook.xml', $workbook);

        // 构建共享字符串和工作表
        $sharedStrings = [];
        $sheetRows = [];

        if (!empty($data)) {
            // 表头
            $headerCells = [];
            $col = 0;
            foreach (array_keys($data[0]) as $header) {
                $ssIndex = count($sharedStrings);
                $sharedStrings[] = htmlspecialchars($header);
                $colLetter = static::getColumnLetter($col);
                $headerCells[] = "<c r=\"{$colLetter}1\" t=\"s\"><v>{$ssIndex}</v></c>";
                $col++;
            }
            $sheetRows[] = '<row r="1">' . implode('', $headerCells) . '</row>';

            // 数据行
            $rowNum = 2;
            foreach ($data as $row) {
                $cells = [];
                $col = 0;
                foreach ($row as $value) {
                    $colLetter = static::getColumnLetter($col);

                    if (is_numeric($value) && !is_string($value)) {
                        $cells[] = "<c r=\"{$colLetter}{$rowNum}\"><v>{$value}</v></c>";
                    } else {
                        $ssIndex = count($sharedStrings);
                        $sharedStrings[] = htmlspecialchars((string)$value);
                        $cells[] = "<c r=\"{$colLetter}{$rowNum}\" t=\"s\"><v>{$ssIndex}</v></c>";
                    }
                    $col++;
                }
                $sheetRows[] = "<row r=\"{$rowNum}\">" . implode('', $cells) . '</row>';
                $rowNum++;
            }
        }

        // xl/sharedStrings.xml
        $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($sharedStrings) . '" uniqueCount="' . count($sharedStrings) . '">';
        foreach ($sharedStrings as $str) {
            $ssXml .= '<si><t>' . $str . '</t></si>';
        }
        $ssXml .= '</sst>';
        $zip->addFromString('xl/sharedStrings.xml', $ssXml);

        // xl/worksheets/sheet1.xml
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
            <sheetData>' . implode('', $sheetRows) . '</sheetData>
        </worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);

        $zip->close();

        return $filePath;
    }

    /**
     * 获取Excel列字母
     */
    private static function getColumnLetter(int $col): string
    {
        $letter = '';
        while ($col >= 0) {
            $letter = chr(65 + ($col % 26)) . $letter;
            $col = intval($col / 26) - 1;
        }
        return $letter;
    }
}
