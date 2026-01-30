<?php
$pageTitle = '标签管理';
$currentPage = 'tags';
$headerActions = '<button class="btn btn-primary" onclick="showCreateModal()">+ 新建标签</button>';
ob_start();
?>

<div class="card">
    <div id="tag-list">
        <div class="loading"><div class="spinner"></div></div>
    </div>
</div>

<script>
async function loadTags() {
    try {
        const tags = await api.get('/api/tags');
        renderTagList(tags);
    } catch (error) {
        toast.error('加载标签失败: ' + error.message);
    }
}

function renderTagList(tags) {
    if (!tags || tags.length === 0) {
        document.getElementById('tag-list').innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">&#9873;</div>
                <div class="empty-state-title">暂无标签</div>
                <p class="text-muted">点击上方按钮创建标签</p>
            </div>
        `;
        return;
    }

    const html = `
        <table class="table">
            <thead>
                <tr>
                    <th>标签名称</th>
                    <th>颜色</th>
                    <th>关联资产数</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                ${tags.map(t => `
                    <tr>
                        <td>
                            <span class="tag" style="background-color: ${t.color}20; color: ${t.color}; border: 1px solid ${t.color};">
                                ${t.name}
                            </span>
                        </td>
                        <td>
                            <span style="display: inline-block; width: 24px; height: 24px; background-color: ${t.color}; border-radius: 4px;"></span>
                            <code style="margin-left: 8px;">${t.color}</code>
                        </td>
                        <td>${formatNumber(t.asset_count)}</td>
                        <td>${formatDate(t.created_at)}</td>
                        <td>
                            <div class="table-actions">
                                <button class="btn btn-sm btn-outline" onclick="showEditModal(${t.id}, '${t.name}', '${t.color}')">编辑</button>
                                <button class="btn btn-sm btn-danger" onclick="deleteTag(${t.id})">删除</button>
                            </div>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    document.getElementById('tag-list').innerHTML = html;
}

function showCreateModal() {
    modal.open({
        title: '新建标签',
        content: `
            <form id="tag-form">
                <div class="form-group">
                    <label class="form-label">标签名称 *</label>
                    <input type="text" class="form-control" name="name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">标签颜色</label>
                    <input type="color" class="form-control" name="color" value="#3B82F6" style="height: 40px; padding: 4px;">
                </div>
            </form>
        `,
        footer: `
            <button class="btn btn-outline" onclick="modal.close()">取消</button>
            <button class="btn btn-primary" onclick="createTag()">创建</button>
        `,
    });
}

async function createTag() {
    const form = document.getElementById('tag-form');
    const data = serializeForm(form);

    try {
        await api.post('/api/tags', data);
        toast.success('标签创建成功');
        modal.close();
        loadTags();
    } catch (error) {
        toast.error(error.message);
    }
}

function showEditModal(id, name, color) {
    modal.open({
        title: '编辑标签',
        content: `
            <form id="tag-form">
                <div class="form-group">
                    <label class="form-label">标签名称 *</label>
                    <input type="text" class="form-control" name="name" value="${name}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">标签颜色</label>
                    <input type="color" class="form-control" name="color" value="${color}" style="height: 40px; padding: 4px;">
                </div>
            </form>
        `,
        footer: `
            <button class="btn btn-outline" onclick="modal.close()">取消</button>
            <button class="btn btn-primary" onclick="updateTag(${id})">保存</button>
        `,
    });
}

async function updateTag(id) {
    const form = document.getElementById('tag-form');
    const data = serializeForm(form);

    try {
        await api.put('/api/tags/' + id, data);
        toast.success('标签更新成功');
        modal.close();
        loadTags();
    } catch (error) {
        toast.error(error.message);
    }
}

function deleteTag(id) {
    modal.confirm('确定要删除这个标签吗？关联的资产将取消此标签。', async () => {
        try {
            await api.delete('/api/tags/' + id);
            toast.success('标签已删除');
            loadTags();
        } catch (error) {
            toast.error(error.message);
        }
    });
}

// 初始加载
document.addEventListener('DOMContentLoaded', function() {
    loadTags();
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/main.php';
?>
