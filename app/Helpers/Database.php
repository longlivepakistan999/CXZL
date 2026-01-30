<?php
namespace App\Helpers;

use PDO;
use PDOException;

/**
 * 数据库连接管理类
 * 支持连接池和大数据量优化
 */
class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    /**
     * 初始化数据库配置
     */
    public static function init(array $config): void
    {
        self::$config = $config;
    }

    /**
     * 获取数据库连接
     */
    public static function connection(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }
        return self::$instance;
    }

    /**
     * 创建新的数据库连接
     */
    private static function createConnection(): PDO
    {
        $config = self::$config;

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $config['driver'],
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']} COLLATE {$config['collation']}",
            // 大数据量优化
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ];

        try {
            $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
            return $pdo;
        } catch (PDOException $e) {
            throw new PDOException('数据库连接失败: ' . $e->getMessage());
        }
    }

    /**
     * 执行查询并返回所有结果
     */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 执行查询并返回单条结果
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * 执行查询并返回单个值
     */
    public static function queryScalar(string $sql, array $params = [])
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * 执行非查询语句(INSERT, UPDATE, DELETE)
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * 获取最后插入的ID
     */
    public static function lastInsertId(): string
    {
        return self::connection()->lastInsertId();
    }

    /**
     * 开始事务
     */
    public static function beginTransaction(): bool
    {
        return self::connection()->beginTransaction();
    }

    /**
     * 提交事务
     */
    public static function commit(): bool
    {
        return self::connection()->commit();
    }

    /**
     * 回滚事务
     */
    public static function rollback(): bool
    {
        return self::connection()->rollBack();
    }

    /**
     * 批量插入(大数据量优化)
     * @param string $table 表名
     * @param array $columns 列名数组
     * @param array $rows 数据行数组
     * @param int $batchSize 每批数量
     */
    public static function batchInsert(string $table, array $columns, array $rows, int $batchSize = 1000): int
    {
        if (empty($rows)) {
            return 0;
        }

        $totalInserted = 0;
        $chunks = array_chunk($rows, $batchSize);
        $columnList = implode('`, `', $columns);
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        foreach ($chunks as $chunk) {
            $values = [];
            $params = [];

            foreach ($chunk as $row) {
                $values[] = $placeholders;
                foreach ($columns as $col) {
                    $params[] = $row[$col] ?? null;
                }
            }

            $sql = "INSERT INTO `{$table}` (`{$columnList}`) VALUES " . implode(', ', $values);
            $totalInserted += self::execute($sql, $params);
        }

        return $totalInserted;
    }

    /**
     * 批量插入或更新(UPSERT)
     */
    public static function batchUpsert(string $table, array $columns, array $rows, array $updateColumns, int $batchSize = 1000): int
    {
        if (empty($rows)) {
            return 0;
        }

        $totalAffected = 0;
        $chunks = array_chunk($rows, $batchSize);
        $columnList = implode('`, `', $columns);
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        $updateParts = [];
        foreach ($updateColumns as $col) {
            $updateParts[] = "`{$col}` = VALUES(`{$col}`)";
        }
        $updateClause = implode(', ', $updateParts);

        foreach ($chunks as $chunk) {
            $values = [];
            $params = [];

            foreach ($chunk as $row) {
                $values[] = $placeholders;
                foreach ($columns as $col) {
                    $params[] = $row[$col] ?? null;
                }
            }

            $sql = "INSERT INTO `{$table}` (`{$columnList}`) VALUES " . implode(', ', $values);
            $sql .= " ON DUPLICATE KEY UPDATE {$updateClause}";
            $totalAffected += self::execute($sql, $params);
        }

        return $totalAffected;
    }

    /**
     * 使用游标分批处理大数据集
     * @param string $sql 查询SQL
     * @param array $params 参数
     * @param callable $callback 处理回调函数
     * @param int $batchSize 每批数量
     */
    public static function chunk(string $sql, array $params, callable $callback, int $batchSize = 1000): void
    {
        $offset = 0;

        do {
            $limitedSql = $sql . " LIMIT {$batchSize} OFFSET {$offset}";
            $rows = self::query($limitedSql, $params);

            if (empty($rows)) {
                break;
            }

            $callback($rows);
            $offset += $batchSize;

        } while (count($rows) === $batchSize);
    }

    /**
     * 使用无缓冲查询处理超大数据集
     */
    public static function unbufferedQuery(string $sql, array $params = []): \Generator
    {
        $pdo = self::createConnection();
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        while ($row = $stmt->fetch()) {
            yield $row;
        }
    }
}
