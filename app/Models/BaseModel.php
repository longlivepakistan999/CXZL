<?php
namespace App\Models;

use App\Helpers\Database;

/**
 * 基础模型类
 * 提供通用的CRUD操作,针对大数据量优化
 */
abstract class BaseModel
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [];
    protected static array $casts = [];

    /**
     * 根据ID查找记录
     */
    public static function find(int $id): ?array
    {
        $sql = "SELECT * FROM `" . static::$table . "` WHERE `" . static::$primaryKey . "` = ? LIMIT 1";
        $result = Database::queryOne($sql, [$id]);
        return $result ? static::castAttributes($result) : null;
    }

    /**
     * 根据条件查找单条记录
     */
    public static function findBy(array $conditions): ?array
    {
        $where = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            if ($value === null) {
                $where[] = "`{$key}` IS NULL";
            } else {
                $where[] = "`{$key}` = ?";
                $params[] = $value;
            }
        }

        $sql = "SELECT * FROM `" . static::$table . "` WHERE " . implode(' AND ', $where) . " LIMIT 1";
        $result = Database::queryOne($sql, $params);
        return $result ? static::castAttributes($result) : null;
    }

    /**
     * 查询所有记录(带分页)
     */
    public static function all(int $page = 1, int $perPage = 20, array $orderBy = []): array
    {
        $offset = ($page - 1) * $perPage;
        $orderClause = static::buildOrderClause($orderBy);

        $sql = "SELECT * FROM `" . static::$table . "`{$orderClause} LIMIT {$perPage} OFFSET {$offset}";
        $rows = Database::query($sql);

        return array_map([static::class, 'castAttributes'], $rows);
    }

    /**
     * 条件查询
     */
    public static function where(array $conditions, int $page = 1, int $perPage = 20, array $orderBy = []): array
    {
        $where = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            if (is_array($value)) {
                // 支持操作符 ['field' => ['>', 10]]
                $operator = $value[0];
                $operand = $value[1];
                $where[] = "`{$key}` {$operator} ?";
                $params[] = $operand;
            } elseif ($value === null) {
                $where[] = "`{$key}` IS NULL";
            } else {
                $where[] = "`{$key}` = ?";
                $params[] = $value;
            }
        }

        $offset = ($page - 1) * $perPage;
        $orderClause = static::buildOrderClause($orderBy);
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT * FROM `" . static::$table . "` {$whereClause}{$orderClause} LIMIT {$perPage} OFFSET {$offset}";
        $rows = Database::query($sql, $params);

        return array_map([static::class, 'castAttributes'], $rows);
    }

    /**
     * IN查询
     */
    public static function whereIn(string $column, array $values, int $page = 1, int $perPage = 20): array
    {
        if (empty($values)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM `" . static::$table . "` WHERE `{$column}` IN ({$placeholders}) LIMIT {$perPage} OFFSET {$offset}";
        $rows = Database::query($sql, $values);

        return array_map([static::class, 'castAttributes'], $rows);
    }

    /**
     * 统计数量
     */
    public static function count(array $conditions = []): int
    {
        $where = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            if (is_array($value)) {
                $operator = $value[0];
                $operand = $value[1];
                $where[] = "`{$key}` {$operator} ?";
                $params[] = $operand;
            } elseif ($value === null) {
                $where[] = "`{$key}` IS NULL";
            } else {
                $where[] = "`{$key}` = ?";
                $params[] = $value;
            }
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT COUNT(*) FROM `" . static::$table . "` {$whereClause}";

        return (int)Database::queryScalar($sql, $params);
    }

    /**
     * 创建记录
     */
    public static function create(array $data): int
    {
        $data = static::filterFillable($data);

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = "INSERT INTO `" . static::$table . "` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        Database::execute($sql, array_values($data));

        return (int)Database::lastInsertId();
    }

    /**
     * 更新记录
     */
    public static function update(int $id, array $data): int
    {
        $data = static::filterFillable($data);

        $setParts = [];
        $params = [];

        foreach ($data as $key => $value) {
            $setParts[] = "`{$key}` = ?";
            $params[] = $value;
        }

        $params[] = $id;

        $sql = "UPDATE `" . static::$table . "` SET " . implode(', ', $setParts) . " WHERE `" . static::$primaryKey . "` = ?";
        return Database::execute($sql, $params);
    }

    /**
     * 批量更新
     */
    public static function updateWhere(array $conditions, array $data): int
    {
        $data = static::filterFillable($data);

        $setParts = [];
        $params = [];

        foreach ($data as $key => $value) {
            $setParts[] = "`{$key}` = ?";
            $params[] = $value;
        }

        $where = [];
        foreach ($conditions as $key => $value) {
            if (is_array($value)) {
                $operator = $value[0];
                $operand = $value[1];
                $where[] = "`{$key}` {$operator} ?";
                $params[] = $operand;
            } elseif ($value === null) {
                $where[] = "`{$key}` IS NULL";
            } else {
                $where[] = "`{$key}` = ?";
                $params[] = $value;
            }
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "UPDATE `" . static::$table . "` SET " . implode(', ', $setParts) . " {$whereClause}";

        return Database::execute($sql, $params);
    }

    /**
     * 删除记录
     */
    public static function delete(int $id): int
    {
        $sql = "DELETE FROM `" . static::$table . "` WHERE `" . static::$primaryKey . "` = ?";
        return Database::execute($sql, [$id]);
    }

    /**
     * 批量删除
     */
    public static function deleteWhere(array $conditions): int
    {
        $where = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            if (is_array($value)) {
                $operator = $value[0];
                $operand = $value[1];
                $where[] = "`{$key}` {$operator} ?";
                $params[] = $operand;
            } elseif ($value === null) {
                $where[] = "`{$key}` IS NULL";
            } else {
                $where[] = "`{$key}` = ?";
                $params[] = $value;
            }
        }

        if (empty($where)) {
            return 0; // 防止误删全表
        }

        $sql = "DELETE FROM `" . static::$table . "` WHERE " . implode(' AND ', $where);
        return Database::execute($sql, $params);
    }

    /**
     * 批量删除(IN)
     */
    public static function deleteIn(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $sql = "DELETE FROM `" . static::$table . "` WHERE `" . static::$primaryKey . "` IN ({$placeholders})";

        return Database::execute($sql, $ids);
    }

    /**
     * 检查记录是否存在
     */
    public static function exists(array $conditions): bool
    {
        $where = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            if ($value === null) {
                $where[] = "`{$key}` IS NULL";
            } else {
                $where[] = "`{$key}` = ?";
                $params[] = $value;
            }
        }

        $sql = "SELECT 1 FROM `" . static::$table . "` WHERE " . implode(' AND ', $where) . " LIMIT 1";
        return Database::queryScalar($sql, $params) !== false;
    }

    /**
     * 增量更新字段
     */
    public static function increment(int $id, string $column, int $amount = 1): int
    {
        $sql = "UPDATE `" . static::$table . "` SET `{$column}` = `{$column}` + ? WHERE `" . static::$primaryKey . "` = ?";
        return Database::execute($sql, [$amount, $id]);
    }

    /**
     * 批量增量更新
     */
    public static function incrementWhere(array $conditions, string $column, int $amount = 1): int
    {
        $where = [];
        $params = [$amount];

        foreach ($conditions as $key => $value) {
            $where[] = "`{$key}` = ?";
            $params[] = $value;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "UPDATE `" . static::$table . "` SET `{$column}` = `{$column}` + ? {$whereClause}";

        return Database::execute($sql, $params);
    }

    /**
     * 过滤可填充字段
     */
    protected static function filterFillable(array $data): array
    {
        if (empty(static::$fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip(static::$fillable));
    }

    /**
     * 类型转换
     */
    protected static function castAttributes(array $data): array
    {
        foreach (static::$casts as $key => $type) {
            if (!isset($data[$key])) {
                continue;
            }

            switch ($type) {
                case 'int':
                case 'integer':
                    $data[$key] = (int)$data[$key];
                    break;
                case 'float':
                case 'double':
                    $data[$key] = (float)$data[$key];
                    break;
                case 'bool':
                case 'boolean':
                    $data[$key] = (bool)$data[$key];
                    break;
                case 'array':
                case 'json':
                    $data[$key] = $data[$key] ? json_decode($data[$key], true) : [];
                    break;
            }
        }
        return $data;
    }

    /**
     * 构建排序子句
     */
    protected static function buildOrderClause(array $orderBy): string
    {
        if (empty($orderBy)) {
            return '';
        }

        $parts = [];
        foreach ($orderBy as $column => $direction) {
            $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
            $parts[] = "`{$column}` {$direction}";
        }

        return ' ORDER BY ' . implode(', ', $parts);
    }

    /**
     * 执行原始SQL查询
     */
    public static function raw(string $sql, array $params = []): array
    {
        return Database::query($sql, $params);
    }

    /**
     * 执行原始SQL(非查询)
     */
    public static function rawExecute(string $sql, array $params = []): int
    {
        return Database::execute($sql, $params);
    }
}
