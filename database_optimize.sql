-- =====================================================
-- 车友帮讯联系统 - 数据库性能优化脚本
-- =====================================================
-- 
-- 推荐使用方法：管理员后台优化
-- 1. 登录管理员账户
-- 2. 进入"数据库清理"选项卡
-- 3. 点击"立即优化数据库"按钮
-- 
-- 备用方法：phpMyAdmin 导入
-- 1. 登录 phpMyAdmin
-- 2. 选择数据库
-- 3. 导入此文件
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;

-- 检查并添加 messages 表索引
-- 检查 idx_user_created 索引是否存在
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'messages' 
     AND index_name = 'idx_user_created') > 0,
    'SELECT "Index idx_user_created already exists" AS msg',
    'ALTER TABLE `messages` ADD INDEX `idx_user_created` (`user_id`, `created_at`)'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查 idx_is_read 索引是否存在
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'messages' 
     AND index_name = 'idx_is_read') > 0,
    'SELECT "Index idx_is_read already exists" AS msg',
    'ALTER TABLE `messages` ADD INDEX `idx_is_read` (`is_read`)'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查 idx_user_read 索引是否存在
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'messages' 
     AND index_name = 'idx_user_read') > 0,
    'SELECT "Index idx_user_read already exists" AS msg',
    'ALTER TABLE `messages` ADD INDEX `idx_user_read` (`user_id`, `is_read`)'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加 users 表索引
-- 检查 idx_email_verified 索引是否存在
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'users' 
     AND index_name = 'idx_email_verified') > 0,
    'SELECT "Index idx_email_verified already exists" AS msg',
    'ALTER TABLE `users` ADD INDEX `idx_email_verified` (`email_verified`)'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查 idx_is_admin 索引是否存在
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'users' 
     AND index_name = 'idx_is_admin') > 0,
    'SELECT "Index idx_is_admin already exists" AS msg',
    'ALTER TABLE `users` ADD INDEX `idx_is_admin` (`is_admin`)'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查 idx_created_at 索引是否存在 (users表)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'users' 
     AND index_name = 'idx_created_at') > 0,
    'SELECT "Index idx_created_at already exists" AS msg',
    'ALTER TABLE `users` ADD INDEX `idx_created_at` (`created_at`)'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加 email_verifications 表索引
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'email_verifications' 
     AND index_name = 'idx_user_expires_opt') > 0,
    'SELECT "Index idx_user_expires_opt already exists" AS msg',
    'ALTER TABLE `email_verifications` ADD INDEX `idx_user_expires_opt` (`user_id`, `expires_at`)'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加 password_resets 表索引
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'password_resets' 
     AND index_name = 'idx_user_expires_opt') > 0,
    'SELECT "Index idx_user_expires_opt already exists" AS msg',
    'ALTER TABLE `password_resets` ADD INDEX `idx_user_expires_opt` (`user_id`, `expires_at`)'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'password_resets' 
     AND index_name = 'idx_used_expires') > 0,
    'SELECT "Index idx_used_expires already exists" AS msg',
    'ALTER TABLE `password_resets` ADD INDEX `idx_used_expires` (`used`, `expires_at`)'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 优化所有表
OPTIMIZE TABLE `users`;
OPTIMIZE TABLE `messages`;
OPTIMIZE TABLE `email_verifications`;
OPTIMIZE TABLE `password_resets`;
OPTIMIZE TABLE `settings`;
OPTIMIZE TABLE `email_blacklist`;

COMMIT;

-- 显示优化结果统计
SELECT 
    'Database optimization completed successfully!' AS Status,
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name IN ('users', 'messages', 'email_verifications', 'password_resets')
     AND index_name LIKE 'idx_%') AS 'Total Indexes Added',
    NOW() AS 'Completed At';

-- 显示当前所有索引
SELECT 
    table_name AS 'Table',
    index_name AS 'Index Name',
    column_name AS 'Column',
    seq_in_index AS 'Position'
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE table_schema = DATABASE() 
  AND table_name IN ('users', 'messages', 'email_verifications', 'password_resets')
  AND index_name LIKE 'idx_%'
ORDER BY table_name, index_name, seq_in_index;