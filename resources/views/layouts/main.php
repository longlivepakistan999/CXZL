<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? '资产探测系统' ?></title>
    <link href="/css/style.css" rel="stylesheet">
    <script src="/js/app.js" defer></script>
</head>
<body>
    <div class="app-container">
        <!-- 侧边栏 -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1 class="logo">资产探测系统</h1>
            </div>
            <nav class="sidebar-nav">
                <a href="/dashboard" class="nav-item <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
                    <span class="nav-icon">&#9632;</span>
                    <span>仪表盘</span>
                </a>
                <a href="/projects" class="nav-item <?= ($currentPage ?? '') === 'projects' ? 'active' : '' ?>">
                    <span class="nav-icon">&#9633;</span>
                    <span>项目管理</span>
                </a>
                <a href="/tasks" class="nav-item <?= ($currentPage ?? '') === 'tasks' ? 'active' : '' ?>">
                    <span class="nav-icon">&#9654;</span>
                    <span>扫描任务</span>
                </a>
                <a href="/scheduled-tasks" class="nav-item <?= ($currentPage ?? '') === 'scheduled' ? 'active' : '' ?>">
                    <span class="nav-icon">&#8635;</span>
                    <span>定时任务</span>
                </a>
                <a href="/failures" class="nav-item <?= ($currentPage ?? '') === 'failures' ? 'active' : '' ?>">
                    <span class="nav-icon">&#10006;</span>
                    <span>失败日志</span>
                </a>
                <a href="/exports" class="nav-item <?= ($currentPage ?? '') === 'exports' ? 'active' : '' ?>">
                    <span class="nav-icon">&#8681;</span>
                    <span>导出记录</span>
                </a>
                <a href="/tags" class="nav-item <?= ($currentPage ?? '') === 'tags' ? 'active' : '' ?>">
                    <span class="nav-icon">&#9873;</span>
                    <span>标签管理</span>
                </a>
                <a href="/settings" class="nav-item <?= ($currentPage ?? '') === 'settings' ? 'active' : '' ?>">
                    <span class="nav-icon">&#9881;</span>
                    <span>系统设置</span>
                </a>
            </nav>
        </aside>

        <!-- 主内容区 -->
        <main class="main-content">
            <header class="page-header">
                <h2 class="page-title"><?= $pageTitle ?? '仪表盘' ?></h2>
                <div class="header-actions">
                    <?php if (isset($headerActions)): ?>
                        <?= $headerActions ?>
                    <?php endif; ?>
                </div>
            </header>

            <div class="page-content">
                <?= $content ?? '' ?>
            </div>
        </main>
    </div>

    <!-- 模态框容器 -->
    <div id="modal-container"></div>

    <!-- 通知容器 -->
    <div id="toast-container"></div>
</body>
</html>
