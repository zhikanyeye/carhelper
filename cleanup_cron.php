<?php
/**
 * 自动清理定时任务脚本
 * 
 * 使用方法：
 * 1. 在服务器上设置cron任务：
 *    0 2 * * * /usr/bin/php /path/to/cleanup_cron.php
 * 
 * 2. 或者通过curl访问API：
 *    0 2 * * * curl -s "https://your-domain.com/api/admin.php?action=auto_cleanup"
 */

// 防止通过浏览器直接访问
if (php_sapi_name() !== 'cli') {
    // 如果不是命令行执行，检查是否是本地请求
    $allowed_ips = ['127.0.0.1', '::1'];
    if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowed_ips)) {
        http_response_code(403);
        die('Access denied');
    }
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/email.php';

echo "=== 自动清理任务开始 ===\n";
echo "执行时间: " . date('Y-m-d H:i:s') . "\n";

// 检查是否启用自动清理
$cleanup_enabled = get_setting('auto_cleanup', 0);
if (!$cleanup_enabled) {
    echo "自动清理功能未启用，任务结束\n";
    exit(0);
}

// 获取清理周期设置
$cleanup_days = get_setting('cleanup_days', 90);
echo "清理周期: {$cleanup_days} 天\n";

// 1. 执行数据库数据清理
$result = cleanup_old_data($cleanup_days);

if ($result) {
    $message = "自动清理执行成功，清理了 {$cleanup_days} 天前的数据";
    echo "✓ " . $message . "\n";
    
    // 清理过期的密码重置记录
    $reset_cleanup_count = cleanup_expired_password_resets();
    if ($reset_cleanup_count > 0) {
        echo "✓ 清理了 {$reset_cleanup_count} 条过期的密码重置记录\n";
    }
    
    // 清理过期的邮箱验证记录
    $email_cleanup_count = cleanup_expired_email_verifications();
    if ($email_cleanup_count > 0) {
        echo "✓ 清理了 {$email_cleanup_count} 条过期的邮箱验证记录\n";
    }
    
    // 2. 清理过期邮件队列
    if (function_exists('cleanupEmailQueue')) {
        echo "开始清理邮件队列...\n";
        $email_queue_cleaned = cleanupEmailQueue();
        if ($email_queue_cleaned > 0) {
            echo "✓ 清理了 {$email_queue_cleaned} 个过期邮件队列文件\n";
            // 估算节省的磁盘空间
            $space_saved = $email_queue_cleaned * 2; // 假设每个文件平均2KB
            echo "  估算节省磁盘空间: ~{$space_saved}KB\n";
        } else {
            echo "✓ 邮件队列无需清理\n";
        }
        
        // 检查剩余队列并尝试处理
        if (function_exists('getQueuedEmailCount')) {
            $remaining_count = getQueuedEmailCount();
            if ($remaining_count > 0) {
                echo "发现 {$remaining_count} 封待发送邮件，尝试处理...\n";
                
                // 尝试启动邮件处理脚本
                $process_script = __DIR__ . '/includes/process_emails.php';
                if (file_exists($process_script) && function_exists('exec')) {
                    if (PHP_OS_FAMILY === 'Windows') {
                        $cmd = "start /B php \"{$process_script}\" > NUL 2>&1";
                    } else {
                        $cmd = "php \"{$process_script}\" > /dev/null 2>&1 &";
                    }
                    @exec($cmd);
                    echo "✓ 已启动邮件处理任务\n";
                } else {
                    echo "⚠ 邮件处理脚本不可用，请手动处理\n";
                }
            }
        }
    } else {
        echo "⚠ 邮件队列清理功能不可用\n";
    }
    
    // 数据库优化（每周执行一次）
    $day_of_week = date('w'); // 0 = 周日
    if ($day_of_week == 0) { // 周日执行数据库优化
        echo "执行数据库优化...\n";
        $optimized_tables = optimize_database_tables();
        if ($optimized_tables > 0) {
            echo "✓ 优化了 {$optimized_tables} 个数据库表\n";
        }
    }
    
    // 记录日志（如果可能的话）
    try {
        $log_message = $message . " | 邮件队列清理: " . ($email_queue_cleaned ?? 0) . " 个文件";
        log_admin_action('auto_cleanup', $log_message, 0); // user_id = 0 表示系统自动执行
    } catch (Exception $e) {
        echo "日志记录失败: " . $e->getMessage() . "\n";
    }
} else {
    echo "✗ 自动清理执行失败\n";
    exit(1);
}

echo "=== 自动清理任务完成 ===\n";
?>
