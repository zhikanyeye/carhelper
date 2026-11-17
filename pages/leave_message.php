<?php
// pages/leave_message.php

// 首先加载配置和函数
$config_path = file_exists(__DIR__ . '/../config/config.php') ? __DIR__ . '/../config/config.php' : '../config/config.php';
$functions_path = file_exists(__DIR__ . '/../includes/functions.php') ? __DIR__ . '/../includes/functions.php' : '../includes/functions.php';
require_once $config_path;
require_once $functions_path;

// 防封检测 - 如果是内置浏览器，重定向到防封页面
if (needs_anti_block_redirect()) {
    $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                   '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $encoded_url = urlencode($current_url);
    $anti_block_url = (defined('SITE_URL') ? SITE_URL : './') . '/pages/anti_block.php?target=' . $encoded_url;
    
    // 记录访问日志
    log_browser_access('leave_message');
    
    header("Location: " . $anti_block_url);
    exit;
}

// 确保邮件配置可用
if (file_exists(__DIR__ . '/../config/email.php')) {
    require_once __DIR__ . '/../config/email.php';
}

$error_message = '';
$success_message = '';
$user_id = null;
$user_info = null;

// 获取用户信息 - 支持UUID和向后兼容uid
if (isset($_GET['uuid']) && !empty($_GET['uuid'])) {
    // 新的UUID方式
    $uuid = sanitize_input($_GET['uuid']);
    $user_info = get_user_by_uuid($uuid);
    
    if ($user_info) {
        $user_id = $user_info['id'];
    } else {
        $error_message = '用户不存在或链接已失效';
    }
} elseif (isset($_GET['uid']) && is_numeric($_GET['uid'])) {
    // 兼容旧的uid方式
    $user_id = (int)$_GET['uid'];
    
    // 获取用户信息
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch();
    
    if (!$user_info) {
        $error_message = '用户不存在';
    }
} else {
    $error_message = '无效的链接';
}

// 处理留言提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_info) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = '安全验证失败，请重试';
    } else {
        $message = trim($_POST['message'] ?? '');
        $contact_info = trim($_POST['contact_info'] ?? '');
        
        if (empty($message)) {
            $error_message = '请输入留言内容';
        } elseif (strlen($message) > 1000) {
            $error_message = '留言内容不能超过1000个字符';
        } else {
            // 获取用户IP地址
            $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
            
            // 检查同一IP是否在短时间内重复留言（防刷）
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
            $stmt->execute([$user_id, $ip_address]);
            $recent_count = $stmt->fetchColumn();
            
            if ($recent_count > 0) {
                $error_message = '请稍后再试，不要频繁提交留言';
            } else {
                // 插入留言
                $stmt = $pdo->prepare("INSERT INTO messages (user_id, message, contact_info, ip_address) VALUES (?, ?, ?, ?)");
                
                if ($stmt->execute([$user_id, $message, $contact_info, $ip_address])) {
                    $success_message = '留言提交成功！';
                    
                    // 发送邮件通知（仅限已验证邮箱的用户）
                    $stmt = $pdo->prepare("SELECT email, email_verified FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user_data = $stmt->fetch();
                    
                    if ($user_data && $user_data['email_verified']) {
                        if (function_exists('isEmailConfigured') && isEmailConfigured()) {
                            // 使用异步邮件发送，不阻塞用户界面
                            if (function_exists('queueEmail')) {
                                queueEmail($user_data['email'], '您收到新留言 - 车友帮讯联', 
                                    createMessageNotificationEmail($user_info['username'], $message));
                                $success_message .= ' 邮件通知已加入发送队列';
                            } else if (sendMessageNotification($user_data['email'], $user_info['username'], $message)) {
                                $success_message .= ' 对方将收到邮件通知';
                            } else {
                                $success_message .= ' （邮件通知发送失败，但留言已保存）';
                            }
                        } else {
                            $success_message .= ' （系统邮件服务暂未配置）';
                        }
                    } else if ($user_data && !$user_data['email_verified']) {
                        $success_message .= ' （对方邮箱未验证，无法发送邮件通知）';
                    }
                    
                    // 清空表单
                    $_POST = [];
                } else {
                    $error_message = '留言提交失败，请重试';
                }
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <?php if ($user_info): ?>
        
        <div class="card">
            <div class="card-header text-center">
                <h4><i class="bi bi-chat-left-text"></i> 给 <?php echo htmlspecialchars($user_info['username']); ?> 留言</h4>
                <p class="text-muted mb-0">您的留言将以匿名方式发送</p>
            </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo $error_message; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
                </div>
                <?php else: ?>
                
                <form method="POST" id="messageForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">留言内容 <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="message" name="message" rows="6" 
                                  placeholder="请输入你要留言的内容..." maxlength="1000" required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        <div class="form-text">
                            <span id="charCount">0</span>/1000 字符
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="contact_info" class="form-label">联系方式 <small class="text-muted">(可选)</small></label>
                        <input type="text" class="form-control" id="contact_info" name="contact_info" 
                               placeholder="如需回复，请留下微信号、QQ号或电话等联系方式" 
                               maxlength="100" value="<?php echo htmlspecialchars($_POST['contact_info'] ?? ''); ?>">
                        <div class="form-text">此信息仅对车主可见，用于回复联系</div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        <strong>温馨提示：</strong>
                        <ul class="mb-0 mt-2">
                            <li>您的留言将匿名发送给车主</li>
                            <li>车主将通过邮件收到通知</li>
                            <li>请文明留言，友善沟通</li>
                            <li>如需回复，请留下联系方式</li>
                        </ul>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                            <i class="bi bi-send"></i> 发送留言
                        </button>
                    </div>
                </form>
                
                <?php endif; ?>
            </div>
        </div>
        
        <?php else: ?>
        
        <div class="card">
            <div class="card-body text-center">
                <i class="bi bi-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                <h4 class="mt-3">页面不存在</h4>
                <p class="text-muted"><?php echo $error_message; ?></p>
                <a href="<?php echo url(''); ?>" class="btn btn-primary">
                    <i class="bi bi-house"></i> 返回首页
                </a>
            </div>
        </div>
        
        <?php endif; ?>
        
        <!-- 使用说明 -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle"></i> 留言说明</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="d-flex">
                            <i class="bi bi-shield-check text-success me-2 mt-1"></i>
                            <div>
                                <strong>匿名安全</strong>
                                <small class="d-block text-muted">您的身份信息不会被泄露</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex">
                            <i class="bi bi-envelope text-primary me-2 mt-1"></i>
                            <div>
                                <strong>邮件通知</strong>
                                <small class="d-block text-muted">车主将及时收到留言通知</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex">
                            <i class="bi bi-chat-dots text-info me-2 mt-1"></i>
                            <div>
                                <strong>友善沟通</strong>
                                <small class="d-block text-muted">请保持礼貌和友善的态度</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex">
                            <i class="bi bi-reply text-warning me-2 mt-1"></i>
                            <div>
                                <strong>便于回复</strong>
                                <small class="d-block text-muted">留下联系方式便于对方回复</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const messageTextarea = document.getElementById('message');
    const charCount = document.getElementById('charCount');
    const submitBtn = document.getElementById('submitBtn');
    
    // 字符计数
    if (messageTextarea && charCount) {
        function updateCharCount() {
            const length = messageTextarea.value.length;
            charCount.textContent = length;
            
            if (length > 1000) {
                charCount.style.color = '#dc3545';
                submitBtn.disabled = true;
            } else {
                charCount.style.color = length > 800 ? '#fd7e14' : '#6c757d';
                submitBtn.disabled = false;
            }
        }
        
        messageTextarea.addEventListener('input', updateCharCount);
        updateCharCount(); // 初始化
    }
    
    // 表单提交验证
    const form = document.getElementById('messageForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const message = messageTextarea.value.trim();
            
            if (!message) {
                e.preventDefault();
                showNotification('请输入留言内容', 'error');
                messageTextarea.focus();
                return;
            }
            
            if (message.length > 1000) {
                e.preventDefault();
                showNotification('留言内容不能超过1000个字符', 'error');
                messageTextarea.focus();
                return;
            }
            
            // 防止重复提交
            submitBtn.innerHTML = '<span class="loading"></span> 发送中...';
            submitBtn.disabled = true;
        });
    }
});
</script>
