-- 车友帮讯联数据库表结构（虚拟主机版本）
-- 注意：此版本不包含 CREATE DATABASE 语句，适用于虚拟主机用户
-- 
-- 使用说明：
-- 1. 登录您的主机控制面板，确认实际的数据库名称
-- 2. 通常数据库名与用户名相同，如：carcalle_chefriend
-- 3. 在 phpMyAdmin 中选择您的数据库
-- 4. 导入此文件
-- 5. 安装时使用正确的数据库名称

-- 用户表
CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `uuid` varchar(36) NOT NULL UNIQUE,
    `username` varchar(50) NOT NULL UNIQUE,
    `email` varchar(100) NOT NULL UNIQUE,
    `password` varchar(255) NOT NULL,
    `qr_code` varchar(255) DEFAULT NULL,
    `is_admin` tinyint(1) DEFAULT 0,
    `email_verified` tinyint(1) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_uuid` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 留言表
CREATE TABLE IF NOT EXISTS `messages` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `message` text NOT NULL,
    `contact_info` varchar(255) DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `is_read` tinyint(1) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 系统设置表
CREATE TABLE IF NOT EXISTS `settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(100) NOT NULL UNIQUE,
    `setting_value` text,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入默认设置
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', '车友帮讯联'),
('site_description', '方便快捷的车友匿名留言平台'),
('email_notifications', '1'),
('auto_cleanup', '1'),
('cleanup_days', '90'),
('blacklist_enabled', '1'),
('user_management_version', '2.1.1');

-- 邮箱验证码表
CREATE TABLE IF NOT EXISTS `email_verifications` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `email` varchar(100) NOT NULL,
    `verification_code` varchar(32) NOT NULL,
    `expires_at` timestamp NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_code` (`verification_code`),
    INDEX `idx_email` (`email`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 邮箱黑名单表
CREATE TABLE IF NOT EXISTS `email_blacklist` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `email` varchar(100) NOT NULL UNIQUE,
    `reason` varchar(255) DEFAULT '管理员拉黑',
    `admin_id` int(11) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 密码重置表
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `email` varchar(100) NOT NULL,
    `reset_token` varchar(64) NOT NULL UNIQUE,
    `expires_at` timestamp NOT NULL,
    `used` tinyint(1) DEFAULT 0,
    `ip_address` varchar(45) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `used_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_token` (`reset_token`),
    INDEX `idx_email` (`email`),
    INDEX `idx_expires` (`expires_at`),
    INDEX `idx_used` (`used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 管理员操作日志表
CREATE TABLE IF NOT EXISTS `admin_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `action` varchar(100) NOT NULL,
    `message` text NOT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_action` (`action`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 为已存在但没有UUID的用户生成UUID（如果数据库已有数据）
-- 注意：这个SQL语句在MySQL 8.0+和MariaDB 10.7+中工作
-- 对于较老版本的MySQL，安装程序会使用PHP代码处理
-- UPDATE `users` SET `uuid` = UUID() WHERE `uuid` IS NULL OR `uuid` = '';

-- 创建触发器，确保新插入的用户如果没有UUID则自动生成
-- 注意：只有在MySQL支持的情况下才创建
-- DELIMITER $$
-- CREATE TRIGGER IF NOT EXISTS `users_uuid_trigger` 
-- BEFORE INSERT ON `users` 
-- FOR EACH ROW 
-- BEGIN 
--     IF NEW.uuid IS NULL OR NEW.uuid = '' THEN 
--         SET NEW.uuid = UUID();
--     END IF;
-- END$$
-- DELIMITER ;

-- 创建索引优化查询性能
CREATE INDEX IF NOT EXISTS `idx_users_uuid` ON `users`(`uuid`);
CREATE INDEX IF NOT EXISTS `idx_users_created` ON `users`(`created_at`);
CREATE INDEX IF NOT EXISTS `idx_users_email_verified` ON `users`(`email_verified`);
CREATE INDEX IF NOT EXISTS `idx_messages_created` ON `messages`(`created_at`);
CREATE INDEX IF NOT EXISTS `idx_messages_read` ON `messages`(`is_read`);
CREATE INDEX IF NOT EXISTS `idx_messages_user_read` ON `messages`(`user_id`, `is_read`);
CREATE INDEX IF NOT EXISTS `idx_admin_logs_created` ON `admin_logs`(`created_at`);
