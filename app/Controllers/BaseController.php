<?php
namespace App\Controllers;

/**
 * 基础控制器
 */
abstract class BaseController
{
    /**
     * 获取分页参数
     */
    protected function getPagination(): array
    {
        $page = max(1, inputInt('page', 1));
        $perPage = inputInt('per_page', config('pagination.default_per_page', 20));
        $perPage = min($perPage, config('pagination.max_per_page', 100));

        return [$page, $perPage];
    }

    /**
     * 获取排序参数
     */
    protected function getOrderBy(array $allowed = ['id'], string $default = 'id', string $defaultDir = 'DESC'): array
    {
        $orderBy = input('order_by', $default);
        $orderDir = strtoupper(input('order_dir', $defaultDir));

        if (!in_array($orderBy, $allowed)) {
            $orderBy = $default;
        }

        if (!in_array($orderDir, ['ASC', 'DESC'])) {
            $orderDir = $defaultDir;
        }

        return [$orderBy => $orderDir];
    }

    /**
     * 验证必填参数
     */
    protected function validate(array $rules): array
    {
        $errors = [];
        $data = [];

        foreach ($rules as $field => $rule) {
            $value = input($field);
            $ruleList = is_array($rule) ? $rule : explode('|', $rule);

            foreach ($ruleList as $r) {
                if ($r === 'required' && ($value === null || $value === '')) {
                    $errors[$field] = "{$field}不能为空";
                    break;
                }

                if ($r === 'integer' && $value !== null && $value !== '' && !is_numeric($value)) {
                    $errors[$field] = "{$field}必须是数字";
                    break;
                }

                if ($r === 'array' && $value !== null && !is_array($value)) {
                    $decoded = json_decode($value, true);
                    if (!is_array($decoded)) {
                        $errors[$field] = "{$field}必须是数组";
                        break;
                    }
                    $value = $decoded;
                }

                if (strpos($r, 'max:') === 0) {
                    $max = (int)substr($r, 4);
                    if (strlen($value) > $max) {
                        $errors[$field] = "{$field}长度不能超过{$max}";
                        break;
                    }
                }

                if (strpos($r, 'min:') === 0) {
                    $min = (int)substr($r, 4);
                    if (strlen($value) < $min) {
                        $errors[$field] = "{$field}长度不能小于{$min}";
                        break;
                    }
                }
            }

            $data[$field] = $value;
        }

        if (!empty($errors)) {
            error(implode('; ', $errors), 400);
        }

        return $data;
    }

    /**
     * 返回分页列表
     */
    protected function paginatedResponse(array $items, int $total, int $page, int $perPage): void
    {
        success([
            'items' => $items,
            'pagination' => paginate($total, $page, $perPage),
        ]);
    }
}
