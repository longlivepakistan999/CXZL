<?php
namespace App\Controllers;

use App\Models\Tag;

/**
 * 标签控制器
 */
class TagController extends BaseController
{
    /**
     * 标签列表
     */
    public function index(): void
    {
        $tags = Tag::getAll();
        success($tags);
    }

    /**
     * 创建标签
     */
    public function store(): void
    {
        $data = $this->validate([
            'name' => 'required|max:100',
        ]);

        $color = input('color', '#3B82F6');

        // 检查名称是否存在
        if (Tag::exists(['name' => $data['name']])) {
            error('标签名称已存在');
        }

        $tagId = Tag::create([
            'name' => $data['name'],
            'color' => $color,
        ]);

        success(['id' => $tagId], '标签创建成功');
    }

    /**
     * 更新标签
     */
    public function update(int $id): void
    {
        $tag = Tag::find($id);
        if (!$tag) {
            error('标签不存在', 404);
        }

        $data = $this->validate([
            'name' => 'required|max:100',
        ]);

        // 检查名称是否被其他标签使用
        $existing = Tag::findBy(['name' => $data['name']]);
        if ($existing && $existing['id'] !== $id) {
            error('标签名称已存在');
        }

        Tag::update($id, [
            'name' => $data['name'],
            'color' => input('color', $tag['color']),
        ]);

        success(null, '标签更新成功');
    }

    /**
     * 删除标签
     */
    public function destroy(int $id): void
    {
        $tag = Tag::find($id);
        if (!$tag) {
            error('标签不存在', 404);
        }

        Tag::delete($id);
        success(null, '标签删除成功');
    }
}
