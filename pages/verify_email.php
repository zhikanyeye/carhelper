<?php
// pages/verify_email.php
// 邮箱验证页面

$success_message = '';
$error_message = '';
$verification_code = $_GET['code'] ?? '';

if ($verification_code) {
    $verification = verify_email_code($verification_code);
    
    if ($verification) {
        $success_message = '邮箱验证成功！您现在可以正常使用所有功能了。';
        
        // 如果用户已登录且验证的是当前用户，更新session
        if (is_logged_in() && $_SESSION['user_id'] == $verification['user_id']) {
            $_SESSION['email_verified'] = true;
        }
    } else {
        $error_message = '验证码无效或已过期，请重新获取验证邮件。';
    }
} else {
    $error_message = '缺少验证码参数。';
}
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header text-center">
                <h4><i class="bi bi-envelope-check"></i> 邮箱验证</h4>
            </div>
            <div class="card-body text-center">
                <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill"></i> <?php echo $success_message; ?>
                </div>
                
                <div class="mb-4">
                    <i class="bi bi-patch-check-fill text-success" style="font-size: 4rem;"></i>
                </div>
                
                <h5 class="text-success mb-3">验证成功！</h5>
                <p class="text-muted mb-4">您的邮箱已成功验证，现在可以享受完整的服务功能。</p>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                    <?php if (is_logged_in()): ?>
                    <a href="<?php echo url('个人面板'); ?>" class="btn btn-primary">
                        <i class="bi bi-speedometer2"></i> 前往面板
                    </a>
                    <a href="<?php echo url('查看留言'); ?>" class="btn btn-outline-primary">
                        <i class="bi bi-envelope"></i> 查看留言
                    </a>
                    <?php else: ?>
                    <a href="<?php echo url('登录'); ?>" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right"></i> 立即登录
                    </a>
                    <a href="<?php echo url('首页'); ?>" class="btn btn-outline-primary">
                        <i class="bi bi-house"></i> 返回首页
                    </a>
                    <?php endif; ?>
                </div>
                
                <?php elseif ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo $error_message; ?>
                </div>
                
                <div class="mb-4">
                    <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 4rem;"></i>
                </div>
                
                <h5 class="text-warning mb-3">验证失败</h5>
                <p class="text-muted mb-4">验证码可能已过期或无效，请尝试重新获取验证邮件。</p>
                
                <?php if (is_logged_in()): ?>
                <form method="POST" action="<?php echo url('重发验证'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <button type="submit" class="btn btn-primary mb-3">
                        <i class="bi bi-arrow-clockwise"></i> 重新发送验证邮件
                    </button>
                </form>
                <?php endif; ?>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                    <?php if (is_logged_in()): ?>
                    <a href="<?php echo url('dashboard'); ?>" class="btn btn-outline-primary">
                        <i class="bi bi-speedometer2"></i> 返回面板
                    </a>
                    <?php else: ?>
                    <a href="<?php echo url('login'); ?>" class="btn btn-outline-primary">
                        <i class="bi bi-box-arrow-in-right"></i> 去登录
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo url(''); ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-house"></i> 返回首页
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 使用说明 -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle"></i> 邮箱验证说明</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="d-flex">
                            <i class="bi bi-shield-check text-success me-2 mt-1"></i>
                            <div>
                                <strong>账户安全</strong>
                                <small class="d-block text-muted">验证邮箱确保账户安全</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex">
                            <i class="bi bi-bell text-primary me-2 mt-1"></i>
                            <div>
                                <strong>消息通知</strong>
                                <small class="d-block text-muted">验证后可接收留言邮件通知</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex">
                            <i class="bi bi-clock text-warning me-2 mt-1"></i>
                            <div>
                                <strong>有效期限</strong>
                                <small class="d-block text-muted">验证链接24小时内有效</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex">
                            <i class="bi bi-arrow-clockwise text-info me-2 mt-1"></i>
                            <div>
                                <strong>重新发送</strong>
                                <small class="d-block text-muted">可重新发送验证邮件</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
