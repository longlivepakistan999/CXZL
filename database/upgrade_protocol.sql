-- 资产探测系统 - 协议字段升级脚本
-- 运行此脚本为已存在的数据库添加 protocol 字段
-- 用法: mysql -u root -p asset_detector < database/upgrade_protocol.sql

-- 为 assets 表添加 protocol 字段
ALTER TABLE `assets`
ADD COLUMN `protocol` VARCHAR(10) NOT NULL DEFAULT 'https' COMMENT '协议: http或https'
AFTER `domain`;

-- 为 sidesites 表添加 protocol 字段
ALTER TABLE `sidesites`
ADD COLUMN `protocol` VARCHAR(10) NOT NULL DEFAULT 'https' COMMENT '协议: http或https'
AFTER `domain`;

-- 完成
SELECT 'Protocol fields added successfully!' AS message;
