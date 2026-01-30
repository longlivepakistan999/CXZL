<?php
namespace App\Models;

use App\Helpers\Database;

/**
 * 标签模型
 */
class Tag extends BaseModel
{
    protected static string $table = 'tags';

    protected static array $fillable = ['name', 'color', 'asset_count'];

    protected static array $casts = [
        'id' => 'int',
        'asset_count' => 'int',
    ];

    /**
     * 获取所有标签
     */
    public static function getAll(): array
    {
        return Database::query("SELECT * FROM `tags` ORDER BY `name`");
    }

    /**
     * 根据名称查找或创建
     */
    public static function findOrCreate(string $name, string $color = '#3B82F6'): int
    {
        $tag = static::findBy(['name' => $name]);
        if ($tag) {
            return $tag['id'];
        }

        return static::create([
            'name' => $name,
            'color' => $color,
        ]);
    }

    /**
     * 刷新标签资产统计
     */
    public static function refreshAssetCount(int $tagId): void
    {
        $count = Database::queryScalar(
            "SELECT COUNT(*) FROM `assets` WHERE JSON_CONTAINS(`tags`, ?)",
            [json_encode($tagId)]
        );

        Database::execute(
            "UPDATE `tags` SET `asset_count` = ? WHERE `id` = ?",
            [$count, $tagId]
        );
    }

    /**
     * 刷新所有标签统计
     */
    public static function refreshAllCounts(): void
    {
        $tags = static::getAll();
        foreach ($tags as $tag) {
            static::refreshAssetCount($tag['id']);
        }
    }
}
