<?php
// pages/resend_verification.php
// 重新发送验证邮件页面

// 确保邮件配置可用
if (file_exists(__DIR__ . '/../config/email.php')) {
    require_once __DIR__ . '/../config/email.php';
}

// 检查是否登录
if (!is_logged_in()) {
    header('Location: ' . (defined('SITE_URL') ? SITE_URL : '') . '/login');
    exit;
}

$success_message = '';
$error_message = '';

// 处理重新发送请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = '安全验证失败，请重试';
    } else {
        // 检查邮件配置
        if (!function_exists('isEmailConfigured') || !isEmailConfigured()) {
            $error_message = '系统邮件功能暂未配置完成，请联系管理员配置邮件服务后再进行邮箱验证。';
        } else {
            if (resend_verification_email($_SESSION['user_id'])) {
                $success_message = '验证邮件已重新发送！请检查您的邮箱（包括垃圾邮件文件夹）';
            } else {
                $error_message = '发送失败，请稍后重试或联系管理员';
            }
        }
    }
}

// 获取用户信息
global $pdo;
$stmt = $pdo->prepare("SELECT username, email, email_verified FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// 如果已验证，重定向到面板
if ($user && $user['email_verified']) {
    header('Location: ' . (defined('SITE_URL') ? SITE_URL : '') . '/dashboard');
    exit;
}
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header text-center">
                <h4><i class="bi bi-envelope-arrow-up"></i> 重新发送验证邮件</h4>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
                </div>
                
                <div class="text-center mb-4">
                    <i class="bi bi-envelope-check text-success" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">邮件已发送</h5>
                    <p class="text-muted">请查收您的邮箱并点击验证链接</p>
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> 
                    <strong>查收提示：</strong>
                    <ul class="mb-0 mt-2">
                        <li>请检查收件箱和垃圾邮件文件夹</li>
                        <li>验证链接24小时内有效</li>
                        <li>如仍未收到，可再次发送</li>
                    </ul>
                </div>
                
                <?php elseif ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo $error_message; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($user): ?>
                <div class="mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <i class="bi bi-person-circle text-muted" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="mb-1"><?php echo htmlspecialchars($user['username']); ?></h6>
                            <p class="mb-0 text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                        <div class="flex-shrink-0">
                            <span class="badge bg-warning">
                                <i class="bi bi-exclamation-triangle"></i> 未验证
                            </span>
                        </div>
                    </div>
                </div>
                
                <?php if (!$success_message): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> 
                        <strong>您的邮箱尚未验证</strong><br>
                        为了确保账户安全和正常接收留言通知，请验证您的邮箱地址。
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-send"></i> 发送验证邮件
                        </button>
                    </div>
                </form>
                <?php endif; ?>
                <?php endif; ?>
                
                <div class="mt-4 text-center">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <?php if (!function_exists('isEmailConfigured') || !isEmailConfigured()): ?>
                        <!-- 如果邮件未配置，允许跳过验证 -->
                        <a href="<?php echo url('dashboard'); ?>" class="btn btn-outline-primary">
                            <i class="bi bi-speedometer2"></i> 暂时跳过验证
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo url(''); ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-house"></i> 返回首页
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 验证说明 -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-question-circle"></i> 为什么需要验证邮箱？</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="d-flex">
                            <i class="bi bi-shield-check text-success me-2 mt-1"></i>
                            <div>
                                <strong>账户安全</strong>
                                <small class="d-block text-muted">确认邮箱地址，提高账户安全性</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex">
                            <i class="bi bi-bell text-primary me-2 mt-1"></i>
                            <div>
                                <strong>消息通知</strong>
                                <small class="d-block text-muted">接收新留言的邮件通知</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex">
                            <i class="bi bi-key text-warning me-2 mt-1"></i>
                            <div>
                                <strong>密码找回</strong>
                                <small class="d-block text-muted">忘记密码时用于重置密码</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex">
                            <i class="bi bi-patch-check text-info me-2 mt-1"></i>
                            <div>
                                <strong>完整功能</strong>
                                <small class="d-block text-muted">验证后享受所有系统功能</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
