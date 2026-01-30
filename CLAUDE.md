# CLAUDE.md - AI Assistant Guidelines for CXZL

This document provides guidance for AI assistants working with the CXZL (资产探测系统 / Asset Detection System) repository.

## Repository Overview

- **Repository**: CXZL
- **Project Name**: 资产探测系统 (Asset Detection System)
- **Tech Stack**: PHP 8+ / MySQL 8 (compatible with 5.7+)
- **Purpose**: Batch domain/IP asset management with Cloudflare detection, WordPress detection, and side-site discovery

## Quick Start (快速开始)

### 1. 创建数据库

```bash
# 登录MySQL
mysql -u root -p

# 创建数据库
CREATE DATABASE asset_detector CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 导入表结构
mysql -u root -p asset_detector < database/schema.sql
```

### 2. 配置数据库连接

**方式一: 环境变量 (推荐生产环境)**

```bash
# Linux/Mac - 添加到 ~/.bashrc 或 ~/.zshrc
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_DATABASE=asset_detector
export DB_USERNAME=root
export DB_PASSWORD=your_password
export VIEWDNS_API_KEY=your_api_key

# 使配置生效
source ~/.bashrc
```

```bash
# Windows - 系统环境变量或PowerShell
$env:DB_HOST="127.0.0.1"
$env:DB_PORT="3306"
$env:DB_DATABASE="asset_detector"
$env:DB_USERNAME="root"
$env:DB_PASSWORD="your_password"
$env:VIEWDNS_API_KEY="your_api_key"
```

**方式二: 直接修改配置文件 (开发环境)**

编辑 `config/app.php`:

```php
'database' => [
    'host' => '127.0.0.1',      // 数据库地址
    'port' => 3306,              // 端口
    'database' => 'asset_detector', // 数据库名
    'username' => 'root',        // 用户名
    'password' => 'your_password', // 密码
],

'api' => [
    'viewdns' => [
        'key' => 'your_viewdns_api_key', // ViewDNS API密钥
    ],
],
```

### 3. 创建存储目录

```bash
# 创建必要的目录并设置权限
mkdir -p storage/{logs,cache,exports}
mkdir -p public/uploads
chmod -R 755 storage public/uploads
```

### 4. 启动服务

```bash
# 启动PHP开发服务器
php -S localhost:8000 -t public

# 访问 http://localhost:8000
```

### 5. 启动队列消费者 (处理扫描任务)

```bash
# 前台运行 (调试用)
php queue/worker.php

# 后台运行 (生产环境)
nohup php queue/worker.php > storage/logs/worker.log 2>&1 &

# 查看是否运行
ps aux | grep worker.php
```

### 6. 配置定时任务 (可选)

```bash
# 编辑crontab
crontab -e

# 添加以下行 (每分钟执行一次)
* * * * * php /path/to/CXZL/cron/scheduler.php >> /path/to/CXZL/storage/logs/cron.log 2>&1
```

## Configuration Reference (配置说明)

### config/app.php 完整配置

| 配置项 | 说明 | 默认值 |
|--------|------|--------|
| **数据库配置** | | |
| `database.host` | 数据库地址 | `127.0.0.1` |
| `database.port` | 数据库端口 | `3306` |
| `database.database` | 数据库名 | `asset_detector` |
| `database.username` | 用户名 | `root` |
| `database.password` | 密码 | `''` |
| **扫描配置** | | |
| `scanner.concurrent_tasks` | 并发扫描任务数 | `50` |
| `scanner.viewdns_rate_limit` | ViewDNS API限流(次/秒) | `10` |
| `scanner.wp_check_interval` | WP检测间隔(秒) | `1` |
| `scanner.scan_timeout` | 扫描超时(秒) | `30` |
| `scanner.retry_count` | 失败重试次数 | `3` |
| `scanner.retry_interval` | 重试间隔(秒) | `5` |
| `scanner.rescan_interval` | 重复扫描保护(分钟) | `5` |
| **API配置** | | |
| `api.viewdns.key` | ViewDNS API密钥 | `''` |
| `api.cloudflare.update_interval` | CF IP段更新间隔(秒) | `86400` |
| **导入配置** | | |
| `import.batch_size` | 批量导入每批数量 | `1000` |
| `import.max_file_size` | 最大上传文件(字节) | `52428800` (50MB) |
| **导出配置** | | |
| `export.expire_hours` | 导出文件过期时间(小时) | `24` |

### 环境变量对照表

| 环境变量 | 对应配置 | 必填 |
|----------|----------|------|
| `DB_HOST` | database.host | 否 |
| `DB_PORT` | database.port | 否 |
| `DB_DATABASE` | database.database | 否 |
| `DB_USERNAME` | database.username | 否 |
| `DB_PASSWORD` | database.password | **是** |
| `VIEWDNS_API_KEY` | api.viewdns.key | 是(旁站查询需要) |

### ViewDNS API 获取

1. 访问 https://viewdns.info/api/
2. 注册账号并订阅API服务
3. 获取API Key填入配置

> **注意**: 没有ViewDNS API Key时,旁站查询功能将不可用,但CF检测和WP检测正常工作。

## Project Structure (项目结构)

```
CXZL/
├── app/
│   ├── bootstrap.php          # 应用引导和自动加载
│   ├── Controllers/           # API控制器
│   │   ├── BaseController.php
│   │   ├── ProjectController.php
│   │   ├── AssetController.php
│   │   ├── SidesiteController.php
│   │   ├── TaskController.php
│   │   ├── DashboardController.php
│   │   ├── SettingController.php
│   │   ├── TagController.php
│   │   ├── ExportController.php
│   │   ├── FailureController.php
│   │   └── ScheduledTaskController.php
│   ├── Models/                # 数据模型
│   │   ├── BaseModel.php
│   │   ├── Project.php
│   │   ├── Asset.php
│   │   ├── Sidesite.php
│   │   ├── ScanTask.php
│   │   ├── ScheduledTask.php
│   │   ├── Tag.php
│   │   ├── FailureLog.php
│   │   ├── ExportRecord.php
│   │   └── CfIpRange.php
│   ├── Services/              # 业务逻辑服务
│   │   ├── DnsService.php
│   │   ├── CloudflareService.php
│   │   ├── ViewDnsService.php
│   │   ├── WordPressService.php
│   │   ├── ScannerService.php
│   │   ├── ImportService.php
│   │   └── ExportService.php
│   └── Helpers/
│       ├── Database.php       # PDO数据库封装
│       └── functions.php      # 全局辅助函数
├── config/
│   └── app.php               # 应用配置文件
├── cron/
│   └── scheduler.php         # 定时任务调度器
├── database/
│   └── schema.sql            # 数据库表结构
├── public/
│   ├── index.php             # 应用入口
│   ├── css/style.css         # 前端样式
│   └── js/app.js             # 前端脚本
├── queue/
│   └── worker.php            # 队列消费者
├── resources/
│   └── views/                # PHP视图模板
│       ├── layouts/main.php
│       └── pages/
├── storage/
│   ├── logs/                 # 应用日志
│   ├── cache/                # 缓存文件
│   └── exports/              # 导出文件
└── CLAUDE.md                 # 本文件
```

## Core Features (核心功能)

1. **项目管理**: 按项目组织资产
2. **资产导入**: 支持文本粘贴、CSV、Excel批量导入
3. **扫描流程**:
   - DNS解析 → IP提取
   - Cloudflare检测 (IP段匹配)
   - 旁站发现 (ViewDNS API, 非CF站点)
   - WordPress检测 (/wp-json/ 端点)
   - 组件提取 (wp-json namespaces)
4. **队列系统**: 后台任务处理,支持优先级
5. **定时任务**: 基于Cron的自动重扫描
6. **导出功能**: XLSX、CSV、JSON、TXT多格式

## API Endpoints (API接口)

### Projects (项目)
- `GET /api/projects` - 项目列表
- `POST /api/projects` - 创建项目
- `GET /api/projects/{id}` - 项目详情
- `PUT /api/projects/{id}` - 更新项目
- `DELETE /api/projects/{id}` - 删除项目
- `POST /api/projects/{id}/scan` - 扫描项目所有资产

### Assets (资产)
- `GET /api/assets` - 资产列表 (支持筛选)
- `POST /api/assets` - 添加单个资产
- `POST /api/assets/import` - 批量导入
- `GET /api/assets/{id}` - 资产详情
- `POST /api/assets/{id}/scan` - 扫描单个资产
- `POST /api/assets/batch-scan` - 批量扫描

### Sidesites (旁站)
- `GET /api/sidesites` - 旁站列表
- `POST /api/sidesites/{id}/scan` - 扫描旁站

### Tasks (任务)
- `GET /api/tasks` - 任务列表
- `GET /api/tasks/stats` - 队列统计
- `POST /api/tasks/{id}/cancel` - 取消任务
- `POST /api/tasks/{id}/retry` - 重试任务

### Settings (设置)
- `GET /api/settings` - 获取设置
- `PUT /api/settings` - 更新设置
- `POST /api/settings/update-cf-ips` - 刷新CF IP段

## Database Schema (数据库表)

### 核心表
| 表名 | 说明 |
|------|------|
| `projects` | 项目管理 |
| `assets` | 域名/IP资产 |
| `sidesites` | 旁站 (反查IP结果) |
| `scan_tasks` | 扫描任务队列 |
| `scheduled_tasks` | 定时任务配置 |
| `tags` | 资产标签 |
| `failure_logs` | 失败日志 |
| `export_records` | 导出记录 |
| `cf_ip_ranges` | Cloudflare IP缓存 |
| `component_stats` | 组件统计 (预计算) |
| `settings` | 系统设置 |

## Production Deployment (生产部署)

### Nginx 配置示例

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/CXZL/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(git|env) {
        deny all;
    }
}
```

### Supervisor 管理队列进程

```ini
# /etc/supervisor/conf.d/cxzl-worker.conf
[program:cxzl-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/CXZL/queue/worker.php
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/CXZL/storage/logs/worker.log
```

```bash
# 启用并启动
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start cxzl-worker:*
```

## AI Assistant Guidelines (AI助手指南)

### 开发原则

1. **遵循架构**: 使用现有的MVC模式
2. **无框架**: 项目刻意避免Vue/React,保持纯PHP
3. **性能优先**: 考虑大数据量 (10万+记录)
4. **SQL安全**: 始终使用预处理语句
5. **命名规范**: 域相关的可使用中文注释

### 常见修改

1. **添加API接口**: 在 `app/Controllers/` 创建/修改控制器,在 `public/index.php` 添加路由
2. **添加模型**: 继承 `BaseModel`,定义 `$table`, `$fillable`, `$casts`
3. **添加页面**: 在 `resources/views/pages/` 创建视图,在 `public/index.php` 添加路由
4. **添加服务**: 在 `app/Services/` 创建类,使用静态方法模式

### 禁止事项

- 不要添加JS框架 (Vue, React)
- 不要使用ORM库 (Eloquent, Doctrine)
- 不要添加Composer依赖
- 不要更改数据库字符集 (必须utf8mb4)
- 不要移除安全措施 (预处理语句、输入验证)

## Troubleshooting (故障排查)

### 队列不处理任务
1. 检查 `queue/worker.php` 是否运行: `ps aux | grep worker`
2. 检查数据库连接: `php -r "require 'app/bootstrap.php';"`
3. 查看日志: `tail -f storage/logs/app.log`

### ViewDNS API错误
1. 检查API Key是否正确
2. 检查限流设置 (默认10次/秒)
3. API可能封禁了某些IP

### CF检测不准确
1. 刷新CF IP段: `POST /api/settings/update-cf-ips`
2. 检查 `cf_ip_ranges` 表是否有数据

### 导入文件失败
1. 检查文件大小限制 (默认50MB)
2. 检查文件格式 (支持 txt, csv, xlsx)
3. 检查 `storage/logs/` 错误日志

---

*Last updated: 2026-01-30*
*Data volume target: 100,000+ records*
