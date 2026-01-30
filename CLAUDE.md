# CLAUDE.md - AI Assistant Guidelines for CXZL

This document provides guidance for AI assistants working with the CXZL (资产探测系统 / Asset Detection System) repository.

## Repository Overview

- **Repository**: CXZL
- **Project Name**: 资产探测系统 (Asset Detection System)
- **Tech Stack**: PHP 8+ / MySQL 8 (compatible with 5.7+)
- **Purpose**: Batch domain/IP asset management with Cloudflare detection, WordPress detection, and side-site discovery

## Project Structure

```
CXZL/
├── app/
│   ├── bootstrap.php          # Application bootstrap and autoloader
│   ├── Controllers/           # API controllers
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
│   ├── Models/                # Data models
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
│   ├── Services/              # Business logic services
│   │   ├── DnsService.php
│   │   ├── CloudflareService.php
│   │   ├── ViewDnsService.php
│   │   ├── WordPressService.php
│   │   ├── ScannerService.php
│   │   ├── ImportService.php
│   │   └── ExportService.php
│   └── Helpers/
│       ├── Database.php       # PDO database wrapper
│       └── functions.php      # Global helper functions
├── config/
│   └── app.php               # Application configuration
├── cron/
│   └── scheduler.php         # Scheduled task runner
├── database/
│   └── schema.sql            # Database schema
├── public/
│   ├── index.php             # Application entry point
│   ├── css/style.css         # Frontend styles
│   └── js/app.js             # Frontend JavaScript
├── queue/
│   └── worker.php            # Queue consumer for scanning tasks
├── resources/
│   └── views/                # PHP view templates
│       ├── layouts/main.php
│       └── pages/
├── storage/
│   ├── logs/                 # Application logs
│   ├── cache/                # Cache files
│   └── exports/              # Export files
└── CLAUDE.md                 # This file
```

## Core Features

1. **Project Management**: Organize assets by projects
2. **Asset Import**: Batch import domains via text, CSV, or Excel
3. **Scanning Pipeline**:
   - DNS resolution → IP extraction
   - Cloudflare detection (via IP range matching)
   - Side-site discovery (via ViewDNS API for non-CF assets)
   - WordPress detection (via /wp-json/ endpoint)
   - Component extraction (from wp-json namespaces)
4. **Queue System**: Background task processing with priority levels
5. **Scheduled Tasks**: Cron-based automated rescanning
6. **Export**: Multi-format export (XLSX, CSV, JSON, TXT)

## Commands Reference

### Start Queue Worker
```bash
# Foreground
php queue/worker.php

# Background (daemon)
nohup php queue/worker.php > /dev/null 2>&1 &
```

### Run Scheduled Tasks
```bash
# Add to crontab (run every minute)
* * * * * php /path/to/cron/scheduler.php >> /path/to/storage/logs/cron.log 2>&1
```

### Initialize Database
```bash
mysql -u root -p database_name < database/schema.sql
```

### PHP Development Server
```bash
php -S localhost:8000 -t public
```

## Configuration

### Environment Variables
Set these in your environment or use defaults in `config/app.php`:
- `DB_HOST` - Database host (default: 127.0.0.1)
- `DB_PORT` - Database port (default: 3306)
- `DB_DATABASE` - Database name (default: asset_detector)
- `DB_USERNAME` - Database user (default: root)
- `DB_PASSWORD` - Database password
- `VIEWDNS_API_KEY` - ViewDNS API key for side-site lookup

### Key Settings (in database `settings` table)
- `concurrent_tasks` - Max concurrent scan tasks (default: 50)
- `viewdns_rate_limit` - API calls per second (default: 10)
- `scan_timeout` - Request timeout in seconds (default: 30)
- `retry_count` - Auto-retry attempts (default: 3)
- `rescan_interval` - Min minutes between rescans (default: 5)

## API Endpoints

### Projects
- `GET /api/projects` - List projects
- `POST /api/projects` - Create project
- `GET /api/projects/{id}` - Project detail
- `PUT /api/projects/{id}` - Update project
- `DELETE /api/projects/{id}` - Delete project
- `POST /api/projects/{id}/scan` - Scan all assets in project

### Assets
- `GET /api/assets` - List assets (with filters)
- `POST /api/assets` - Add single asset
- `POST /api/assets/import` - Batch import
- `GET /api/assets/{id}` - Asset detail
- `POST /api/assets/{id}/scan` - Scan single asset
- `POST /api/assets/batch-scan` - Batch scan

### Sidesites
- `GET /api/sidesites` - List sidesites
- `POST /api/sidesites/{id}/scan` - Scan sidesite

### Tasks
- `GET /api/tasks` - List scan tasks
- `GET /api/tasks/stats` - Queue statistics
- `POST /api/tasks/{id}/cancel` - Cancel pending task
- `POST /api/tasks/{id}/retry` - Retry failed task

### Settings
- `GET /api/settings` - Get all settings
- `PUT /api/settings` - Update settings
- `POST /api/settings/update-cf-ips` - Refresh Cloudflare IP ranges

## Database Schema

### Core Tables
- `projects` - Project management
- `assets` - Domain/IP assets
- `sidesites` - Side-sites (reverse IP lookup results)
- `scan_tasks` - Task queue
- `scheduled_tasks` - Cron jobs
- `tags` - Asset tagging
- `failure_logs` - Error tracking
- `export_records` - Export history
- `cf_ip_ranges` - Cloudflare IP cache
- `component_stats` - Pre-computed component statistics
- `settings` - System configuration

### Key Indexes for Large Data
- Composite indexes on `project_id` + common filters
- Index on `scan_status` for queue processing
- Index on `domain` for deduplication checks

## Code Conventions

### PHP
- PSR-4 autoloading with `App\` namespace
- Static methods for model operations
- JSON columns for arrays (components, tags)
- Prepared statements for all queries

### Frontend
- Vanilla JavaScript (no frameworks)
- CSS variables for theming
- Async/await for API calls
- Server-side rendered PHP templates

### Performance Considerations
- Batch inserts for large imports (1000 per batch)
- Unbuffered queries for exports
- Pre-computed statistics in `component_stats` table
- Cached counts in parent tables (`asset_count`, `sidesite_count`)

## AI Assistant Guidelines

### When Working on This Repository

1. **Respect the architecture**: Follow the existing MVC-like pattern
2. **No frameworks**: The project deliberately avoids Vue/React; keep it simple PHP
3. **Performance first**: Consider large data volumes (100k+ records)
4. **SQL safety**: Always use prepared statements
5. **Follow naming**: Use Chinese comments where helpful for domain context

### Common Modifications

1. **Adding a new API endpoint**:
   - Create/update controller in `app/Controllers/`
   - Add route in `public/index.php` (apiRoutes array)

2. **Adding a new model**:
   - Extend `BaseModel` in `app/Models/`
   - Define `$table`, `$fillable`, `$casts`

3. **Adding a frontend page**:
   - Create view in `resources/views/pages/`
   - Add route in `public/index.php` (pageRoutes array)

4. **Adding a new service**:
   - Create class in `app/Services/`
   - Follow static method pattern

### What to Avoid

- Don't add JavaScript frameworks (Vue, React)
- Don't use ORM libraries (Eloquent, Doctrine)
- Don't add PHP Composer dependencies
- Don't change database character set (must be utf8mb4)
- Don't remove security measures (prepared statements, input validation)

## Troubleshooting

### Queue Not Processing
1. Check if `queue/worker.php` is running
2. Verify database connection in `config/app.php`
3. Check `storage/logs/` for errors

### ViewDNS API Errors
1. Verify API key in settings
2. Check rate limiting (default 10/sec)
3. API may block certain IPs

### CF Detection Issues
1. Run `POST /api/settings/update-cf-ips` to refresh IP ranges
2. Check `cf_ip_ranges` table has data

---

*Last updated: 2026-01-30*
*Data volume target: 100,000+ records*
