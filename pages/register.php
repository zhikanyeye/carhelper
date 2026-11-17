<?php
// pages/register.php

// 确保邮件配置可用
if (file_exists(__DIR__ . '/../config/email.php')) {
    require_once __DIR__ . '/../config/email.php';
}

$error_message = '';
$success_message = '';

// 如果已经登录，跳转到面板
if (is_logged_in()) {
    header('Location: ' . SITE_URL . '/dashboard');
    exit;
}

// 处理注册请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = '安全验证失败，请重试';
    } else {
        $username = sanitize_input($_POST['username'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // 验证输入
        if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
            $error_message = '请填写所有字段';
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $error_message = '用户名长度必须在3-50个字符之间';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = '请输入有效的邮箱地址';
        } elseif (strlen($password) < 6) {
            $error_message = '密码长度至少6个字符';
        } elseif ($password !== $confirm_password) {
            $error_message = '两次输入的密码不一致';
        } else {
            // 检查邮箱是否在黑名单中
            if (is_email_blacklisted($email)) {
                $error_message = '该邮箱已被禁用，无法注册';
            } else {
                // 检查用户名和邮箱是否已存在
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                
                if ($stmt->fetch()) {
                    $error_message = '用户名或邮箱已存在';
                } else {
                    // 注册用户
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $user_uuid = generate_uuid(); // 生成UUID
                
                $stmt = $pdo->prepare("INSERT INTO users (uuid, username, email, password) VALUES (?, ?, ?, ?)");
                
                if ($stmt->execute([$user_uuid, $username, $email, $hashed_password])) {
                    $user_id = $pdo->lastInsertId();
                    
                    // 发送验证邮件
                    if (function_exists('isEmailConfigured') && isEmailConfigured()) {
                        if (send_verification_email($user_id, $email, $username)) {
                            $success_message = '注册成功！验证邮件已发送到您的邮箱，请查收并验证后再登录';
                        } else {
                            $success_message = '注册成功！请登录您的账号（邮件发送失败，您可以稍后重新发送验证邮件）';
                        }
                    } else {
                        $success_message = '注册成功！请登录您的账号';
                    }
                    
                    // 清空表单
                    $_POST = [];
                } else {
                    $error_message = '注册失败，请重试';
                }
                }
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card">
            <div class="card-header text-center">
                <h4><i class="bi bi-person-plus"></i> 用户注册</h4>
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
                    <div class="mt-2">
                        <a href="<?php echo SITE_URL; ?>/login" class="btn btn-sm btn-success">前往登录</a>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" id="registerForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">用户名</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                   minlength="3" maxlength="50" required>
                        </div>
                        <div class="form-text">3-50个字符，建议使用字母、数字或下划线</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">邮箱地址 <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                        <div class="alert alert-warning mt-2 py-2">
                            <i class="bi bi-exclamation-triangle"></i> 
                            <strong>重要提醒：</strong>请务必填写真实有效的邮箱地址！系统会通过邮件通知您收到的留言，如果邮箱错误将无法收到通知。
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">密码</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   minlength="6" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength mt-2" id="passwordStrength"></div>
                        <div class="form-text">至少6个字符，建议包含大小写字母、数字和特殊字符</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">确认密码</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" class="form-control" id="confirm_password" 
                                   name="confirm_password" required>
                        </div>
                        <div class="invalid-feedback" id="passwordMatch"></div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" required>
                        <label class="form-check-label" for="terms">
                            我同意遵守系统使用条款和隐私政策
                        </label>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="bi bi-person-plus"></i> 注册账号
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">
                    已有账号？ 
                    <a href="<?php echo SITE_URL; ?>/login" class="text-decoration-none">立即登录</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const toggleButton = document.getElementById('togglePassword');
    const strengthDiv = document.getElementById('passwordStrength');
    const matchDiv = document.getElementById('passwordMatch');
    const submitBtn = document.getElementById('submitBtn');
    
    // 密码显示/隐藏切换
    toggleButton.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        const icon = this.querySelector('i');
        icon.className = type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
    
    // 密码强度检查
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        const strength = checkPasswordStrength(password);
        
        let strengthText = '';
        let strengthClass = '';
        
        if (password.length === 0) {
            strengthText = '';
        } else if (strength.score <= 2) {
            strengthText = '密码强度：弱';
            strengthClass = 'text-danger';
        } else if (strength.score <= 4) {
            strengthText = '密码强度：中等';
            strengthClass = 'text-warning';
        } else {
            strengthText = '密码强度：强';
            strengthClass = 'text-success';
        }
        
        strengthDiv.textContent = strengthText;
        strengthDiv.className = 'password-strength mt-2 ' + strengthClass;
    });
    
    // 密码确认检查
    function checkPasswordMatch() {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        
        if (confirmPassword.length === 0) {
            confirmPasswordInput.classList.remove('is-invalid', 'is-valid');
            matchDiv.textContent = '';
        } else if (password === confirmPassword) {
            confirmPasswordInput.classList.remove('is-invalid');
            confirmPasswordInput.classList.add('is-valid');
            matchDiv.textContent = '';
        } else {
            confirmPasswordInput.classList.remove('is-valid');
            confirmPasswordInput.classList.add('is-invalid');
            matchDiv.textContent = '密码不一致';
        }
    }
    
    confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    passwordInput.addEventListener('input', checkPasswordMatch);
    
    // 表单提交验证
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        const email = document.getElementById('email').value;
        
        let isValid = true;
        
        if (!validateForm('registerForm')) {
            isValid = false;
        }
        
        if (!validateEmail(email)) {
            isValid = false;
            showNotification('请输入有效的邮箱地址', 'error');
        }
        
        if (password !== confirmPassword) {
            isValid = false;
            showNotification('两次输入的密码不一致', 'error');
        }
        
        if (password.length < 6) {
            isValid = false;
            showNotification('密码长度至少6个字符', 'error');
        }
        
        if (!isValid) {
            e.preventDefault();
        } else {
            submitBtn.innerHTML = '<span class="loading"></span> 注册中...';
            submitBtn.disabled = true;
        }
    });
});
</script>
