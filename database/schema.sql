-- 资产探测系统数据库结构
-- 支持 MySQL 8.x 和 5.7+
-- 针对几十万级数据量优化

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 项目表
-- ----------------------------
DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL COMMENT '项目名称',
    `description` TEXT COMMENT '项目描述',
    `asset_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '资产数量(缓存)',
    `cf_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'CF资产数量(缓存)',
    `wp_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'WP资产数量(缓存)',
    `sidesite_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '旁站数量(缓存)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_name` (`name`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目表';

-- ----------------------------
-- 资产表 - 核心表，需要重点优化
-- ----------------------------
DROP TABLE IF EXISTS `assets`;
CREATE TABLE `assets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` INT UNSIGNED NOT NULL COMMENT '所属项目ID',
    `domain` VARCHAR(255) NOT NULL COMMENT '域名',
    `protocol` VARCHAR(10) NOT NULL DEFAULT 'https' COMMENT '协议: http或https',
    `ip` VARCHAR(45) DEFAULT NULL COMMENT '解析IP(支持IPv6)',
    `is_cf` TINYINT(1) NOT NULL DEFAULT -1 COMMENT '是否CF: 0-否, 1-是, -1-未检测',
    `is_wp` TINYINT(1) NOT NULL DEFAULT -1 COMMENT '是否WP: 0-否, 1-是, -1-未检测',
    `components` JSON COMMENT '组件列表',
    `component_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '组件数量',
    `sidesite_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '旁站数量(缓存)',
    `wp_sidesite_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'WP旁站数量(缓存)',
    `scan_status` TINYINT NOT NULL DEFAULT 0 COMMENT '扫描状态: 0-待扫描, 1-队列中, 2-扫描中, 3-已完成, 4-失败',
    `scan_error` VARCHAR(500) DEFAULT NULL COMMENT '扫描错误信息',
    `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '重试次数',
    `tags` JSON COMMENT '标签ID列表',
    `remark` TEXT COMMENT '备注',
    `imported_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '导入时间',
    `scanned_at` DATETIME DEFAULT NULL COMMENT '最后扫描时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_project_domain` (`project_id`, `domain`),
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_domain` (`domain`),
    INDEX `idx_ip` (`ip`),
    INDEX `idx_is_cf` (`is_cf`),
    INDEX `idx_is_wp` (`is_wp`),
    INDEX `idx_scan_status` (`scan_status`),
    INDEX `idx_scanned_at` (`scanned_at`),
    INDEX `idx_imported_at` (`imported_at`),
    INDEX `idx_component_count` (`component_count`),
    -- 复合索引优化常用查询
    INDEX `idx_project_cf` (`project_id`, `is_cf`),
    INDEX `idx_project_wp` (`project_id`, `is_wp`),
    INDEX `idx_project_status` (`project_id`, `scan_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='资产表';

-- ----------------------------
-- 旁站表
-- ----------------------------
DROP TABLE IF EXISTS `sidesites`;
CREATE TABLE `sidesites` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `asset_id` INT UNSIGNED NOT NULL COMMENT '所属资产ID',
    `project_id` INT UNSIGNED NOT NULL COMMENT '所属项目ID(冗余,优化查询)',
    `domain` VARCHAR(255) NOT NULL COMMENT '旁站域名',
    `protocol` VARCHAR(10) NOT NULL DEFAULT 'https' COMMENT '协议: http或https',
    `ip` VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
    `is_wp` TINYINT(1) NOT NULL DEFAULT -1 COMMENT '是否WP: 0-否, 1-是, -1-未检测',
    `components` JSON COMMENT '组件列表',
    `component_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '组件数量',
    `scan_status` TINYINT NOT NULL DEFAULT 3 COMMENT '扫描状态: 3-已完成, 4-失败',
    `scan_error` VARCHAR(500) DEFAULT NULL COMMENT '失败原因',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_project_domain` (`project_id`, `domain`),
    INDEX `idx_asset_id` (`asset_id`),
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_domain` (`domain`),
    INDEX `idx_is_wp` (`is_wp`),
    INDEX `idx_scan_status` (`scan_status`),
    INDEX `idx_asset_wp` (`asset_id`, `is_wp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='旁站表';

-- ----------------------------
-- 扫描任务队列表
-- ----------------------------
DROP TABLE IF EXISTS `scan_tasks`;
CREATE TABLE `scan_tasks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` INT UNSIGNED NOT NULL COMMENT '所属项目ID',
    `asset_id` INT UNSIGNED DEFAULT NULL COMMENT '资产ID',
    `sidesite_id` INT UNSIGNED DEFAULT NULL COMMENT '旁站ID(旁站扫描时)',
    `domain` VARCHAR(255) NOT NULL COMMENT '待扫描域名',
    `task_type` TINYINT NOT NULL DEFAULT 1 COMMENT '任务类型: 1-首次扫描, 2-重新扫描, 3-旁站扫描, 4-仅刷新IP, 5-仅刷新CF, 6-仅刷新旁站, 7-仅刷新WP',
    `priority` TINYINT NOT NULL DEFAULT 5 COMMENT '优先级: 1-低, 5-普通, 10-高',
    `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态: 0-待扫描, 1-扫描中, 2-成功, 3-失败, 4-已取消',
    `error_message` VARCHAR(500) DEFAULT NULL COMMENT '错误信息',
    `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '重试次数',
    `started_at` DATETIME DEFAULT NULL COMMENT '开始时间',
    `finished_at` DATETIME DEFAULT NULL COMMENT '完成时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_status_priority` (`status`, `priority` DESC, `id`),
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_asset_id` (`asset_id`),
    INDEX `idx_domain` (`domain`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='扫描任务队列';

-- ----------------------------
-- 定时任务表
-- ----------------------------
DROP TABLE IF EXISTS `scheduled_tasks`;
CREATE TABLE `scheduled_tasks` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL COMMENT '任务名称',
    `project_ids` JSON COMMENT '项目ID列表,null表示全部',
    `scan_scope` VARCHAR(50) NOT NULL DEFAULT 'all' COMMENT '扫描范围: all-全部, failed-仅失败, outdated-超期未扫描',
    `scan_type` TINYINT NOT NULL DEFAULT 1 COMMENT '扫描类型: 1-完整扫描, 4-仅IP, 5-仅CF, 6-仅旁站, 7-仅WP',
    `cron_expression` VARCHAR(100) NOT NULL COMMENT 'Cron表达式',
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
    `last_run_at` DATETIME DEFAULT NULL COMMENT '上次执行时间',
    `next_run_at` DATETIME DEFAULT NULL COMMENT '下次执行时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_is_enabled` (`is_enabled`),
    INDEX `idx_next_run_at` (`next_run_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='定时任务';

-- ----------------------------
-- 标签表
-- ----------------------------
DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL COMMENT '标签名称',
    `color` VARCHAR(20) NOT NULL DEFAULT '#3B82F6' COMMENT '标签颜色',
    `asset_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联资产数(缓存)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='标签表';

-- ----------------------------
-- 导出记录表
-- ----------------------------
DROP TABLE IF EXISTS `export_records`;
CREATE TABLE `export_records` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` INT UNSIGNED DEFAULT NULL COMMENT '导出项目ID',
    `export_type` VARCHAR(50) NOT NULL COMMENT '导出类型: assets, sidesites, tasks, failures',
    `export_scope` VARCHAR(255) DEFAULT NULL COMMENT '导出范围描述',
    `record_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '导出数量',
    `file_format` VARCHAR(20) NOT NULL DEFAULT 'xlsx' COMMENT '文件格式',
    `file_path` VARCHAR(500) DEFAULT NULL COMMENT '文件路径',
    `file_size` BIGINT UNSIGNED DEFAULT NULL COMMENT '文件大小(字节)',
    `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态: 0-生成中, 1-已完成, 2-已过期, 3-失败',
    `expired_at` DATETIME DEFAULT NULL COMMENT '过期时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='导出记录';

-- ----------------------------
-- 失败日志表
-- ----------------------------
DROP TABLE IF EXISTS `failure_logs`;
CREATE TABLE `failure_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` INT UNSIGNED NOT NULL,
    `asset_id` INT UNSIGNED DEFAULT NULL,
    `sidesite_id` INT UNSIGNED DEFAULT NULL,
    `domain` VARCHAR(255) NOT NULL,
    `failure_type` VARCHAR(50) NOT NULL COMMENT '失败类型: dns_error, timeout, api_limit, no_response, other',
    `failure_reason` VARCHAR(500) NOT NULL,
    `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `is_ignored` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已忽略',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_failure_type` (`failure_type`),
    INDEX `idx_is_ignored` (`is_ignored`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='失败日志';

-- ----------------------------
-- 系统配置表
-- ----------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
    `key` VARCHAR(100) NOT NULL,
    `value` TEXT,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置';

-- ----------------------------
-- CF IP段缓存表
-- ----------------------------
DROP TABLE IF EXISTS `cf_ip_ranges`;
CREATE TABLE `cf_ip_ranges` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_range` VARCHAR(50) NOT NULL COMMENT 'IP段(CIDR)',
    `ip_version` TINYINT NOT NULL DEFAULT 4 COMMENT 'IP版本: 4或6',
    `ip_start` VARBINARY(16) NOT NULL COMMENT 'IP段起始(二进制,便于范围查询)',
    `ip_end` VARBINARY(16) NOT NULL COMMENT 'IP段结束(二进制)',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_ip_range` (`ip_range`),
    INDEX `idx_ip_version` (`ip_version`),
    INDEX `idx_ip_range_lookup` (`ip_version`, `ip_start`, `ip_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CF IP段缓存';

-- ----------------------------
-- 组件统计表 (预计算，提升性能)
-- ----------------------------
DROP TABLE IF EXISTS `component_stats`;
CREATE TABLE `component_stats` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` INT UNSIGNED DEFAULT NULL COMMENT '项目ID,NULL表示全局统计',
    `component_name` VARCHAR(100) NOT NULL COMMENT '组件名称',
    `asset_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '使用此组件的资产数',
    `sidesite_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '使用此组件的旁站数',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_project_component` (`project_id`, `component_name`),
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_asset_count` (`asset_count` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='组件统计(预计算)';

-- ----------------------------
-- 初始化默认配置
-- ----------------------------
INSERT INTO `settings` (`key`, `value`) VALUES
('viewdns_api_key', ''),
('concurrent_tasks', '50'),
('viewdns_rate_limit', '10'),
('wp_check_interval', '1'),
('scan_timeout', '30'),
('retry_count', '3'),
('retry_interval', '5'),
('rescan_interval', '5'),
('cf_ip_updated_at', ''),
('import_batch_size', '1000'),
('export_chunk_size', '100000');

SET FOREIGN_KEY_CHECKS = 1;
