<?php
// ============================================
// 后台邮件处理脚本
// ============================================

// 设置为后台运行，不输出到浏览器
if (php_sapi_name() !== 'cli') {
    // 设置内容类型避免浏览器等待
    header('Content-Type: text/plain');
    echo "Email processing started...\n";
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}

// 加载邮件配置（本文件位于 includes/ 下，配置在上级 config/ 目录）
require_once __DIR__ . '/../config/email.php';

// worker 进程独立运行，不经过 config.php，需自行设置时区
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Shanghai');
}

// 设置执行时间限制
set_time_limit(60); // 最多运行60秒

// 处理邮件队列
function processEmailQueue() {
    // 统一使用 email.php 中定义的队列目录（根目录 cache/email_queue/）
    $queueDir = defined('EMAIL_QUEUE_DIR') ? EMAIL_QUEUE_DIR : __DIR__ . '/../cache/email_queue';
    $processedCount = 0;
    $maxEmails = 10; // 每次最多处理10封邮件

    if (!is_dir($queueDir)) {
        return 0;
    }

    // 获取所有待处理的邮件文件
    $emailFiles = glob($queueDir . '/*.json');
    
    if (empty($emailFiles)) {
        return 0;
    }
    
    // 按时间排序，优先处理较早的邮件
    usort($emailFiles, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    
    foreach ($emailFiles as $emailFile) {
        if ($processedCount >= $maxEmails) {
            break; // 达到处理上限
        }
        
        try {
            // 读取邮件数据
            $emailData = json_decode(file_get_contents($emailFile), true);
            
            if (!$emailData) {
                // 无效文件，删除
                unlink($emailFile);
                continue;
            }
            
            // 检查是否过期（超过24小时的邮件放弃发送）
            // queueEmail 写入的字段是 created_at，兼容旧的 timestamp 字段
            $queuedAt = $emailData['created_at'] ?? $emailData['timestamp'] ?? filemtime($emailFile);
            if (time() - (int)$queuedAt > 86400) {
                error_log("邮件已过期，放弃发送：" . $emailData['to']);
                unlink($emailFile);
                continue;
            }
            
            // 检查重试次数
            if ($emailData['attempts'] >= 3) {
                error_log("邮件发送失败次数过多，放弃发送：" . $emailData['to']);
                unlink($emailFile);
                continue;
            }
            
            // 尝试发送邮件
            if (sendEmailQuick($emailData['to'], $emailData['subject'], $emailData['message'])) {
                // 发送成功，删除文件
                unlink($emailFile);
                $processedCount++;
                error_log("后台邮件发送成功：" . $emailData['to']);
            } else {
                // 发送失败，增加重试次数
                $emailData['attempts']++;
                $emailData['last_attempt'] = time();
                file_put_contents($emailFile, json_encode($emailData));
                error_log("后台邮件发送失败，重试次数：" . $emailData['attempts'] . "，收件人：" . $emailData['to']);
            }
            
            // 避免发送过快被限制
            usleep(100000); // 0.1秒延迟
            
        } catch (Exception $e) {
            error_log("处理邮件文件时出错：" . $e->getMessage());
            // 出错的文件也删除，避免重复处理
            if (file_exists($emailFile)) {
                unlink($emailFile);
            }
        }
    }
    
    return $processedCount;
}

// 清理过期的邮件文件
function cleanupExpiredEmails() {
    $queueDir = defined('EMAIL_QUEUE_DIR') ? EMAIL_QUEUE_DIR : __DIR__ . '/../cache/email_queue';
    $cleanedCount = 0;

    if (!is_dir($queueDir)) {
        return 0;
    }

    $emailFiles = glob($queueDir . '/*.json');
    
    foreach ($emailFiles as $emailFile) {
        // 删除超过24小时的文件
        if (time() - filemtime($emailFile) > 86400) {
            unlink($emailFile);
            $cleanedCount++;
        }
    }
    
    return $cleanedCount;
}

// 主执行逻辑
try {
    // 处理邮件队列
    $processed = processEmailQueue();
    
    // 清理过期文件
    $cleaned = cleanupExpiredEmails();
    
    if (php_sapi_name() === 'cli') {
        echo "处理完成：发送邮件 {$processed} 封，清理过期文件 {$cleaned} 个\n";
    }
    
} catch (Exception $e) {
    error_log("后台邮件处理脚本异常：" . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        echo "处理异常：" . $e->getMessage() . "\n";
    }
}
?>