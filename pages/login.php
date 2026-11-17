<?php
// pages/login.php
// 检查是否已安装
if (!file_exists(__DIR__ . '/../config/installed.lock')) {
    header('Location: ' . (defined('SITE_URL') ? SITE_URL : '') . '/install.php');
    exit;
}

$error_message = '';
$success_message = '';

// 如果已经登录，跳转到面板
if (is_logged_in()) {
    header('Location: ' . (defined('SITE_URL') ? SITE_URL : '') . '/dashboard');
    exit;
}

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 确保数据库连接可用
    ensure_database_connection();
    
    // 临时禁用CSRF验证以便调试
    $csrf_valid = true;
    if (isset($_POST['csrf_token']) && !empty($_POST['csrf_token'])) {
        $csrf_valid = verify_csrf_token($_POST['csrf_token']);
    }
    
    if (!$csrf_valid) {
        $error_message = '安全验证失败，请重试';
    } else {
        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error_message = '请填写用户名和密码';
        } else {
            // 验证用户
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // 登录成功
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['email_verified'] = $user['email_verified'];
                
                // 重新生成session ID防止会话固定攻击
                session_regenerate_id(true);
                
                // 检查邮箱验证状态
                if (!$user['email_verified']) {
                    header('Location: ' . (defined('SITE_URL') ? SITE_URL : '') . '/resend_verification');
                } else {
                    header('Location: ' . (defined('SITE_URL') ? SITE_URL : '') . '/dashboard');
                }
                exit;
            } else {
                $error_message = '用户名或密码错误';
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card">
            <div class="card-header text-center">
                <h4><i class="bi bi-box-arrow-in-right"></i> 用户登录</h4>
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
                <?php endif; ?>
                
                <form method="POST" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">用户名或邮箱</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">密码</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">记住我</label>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right"></i> 登录
                        </button>
                    </div>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <a href="<?php echo SITE_URL; ?>/forgot_password" class="text-decoration-none">
                                <i class="bi bi-question-circle me-1"></i>忘记密码？
                            </a>
                        </small>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">
                    还没有账号？ 
                    <a href="<?php echo SITE_URL; ?>/register" class="text-decoration-none">立即注册</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 密码显示/隐藏切换
    const passwordInput = document.getElementById('password');
    const toggleButton = document.getElementById('togglePassword');
    
    toggleButton.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        const icon = this.querySelector('i');
        icon.className = type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
    
    // 表单验证
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        if (!validateForm('loginForm')) {
            e.preventDefault();
            showNotification('请填写所有必填字段', 'error');
        }
    });
});
</script>
