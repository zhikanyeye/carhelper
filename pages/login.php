<?php
// pages/login.php
// 检查是否已安装
if (!file_exists(__DIR__ . '/../config/installed.lock')) {
    header('Location: ' . (defined('SITE_URL') ? SITE_URL : '') . '/install.php');
    exit;
}

$error_message = '';
$success_message = '';

/**
 * 登录防爆破：按 IP+用户名 记录失败次数，存储于 cache/login_attempts/
 */
function login_attempt_file($username) {
    $ip = function_exists('get_real_ip') ? get_real_ip() : ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $dir = __DIR__ . '/../cache/login_attempts';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir . '/' . md5(strtolower($username) . '|' . $ip) . '.json';
}

// 返回剩余冷却秒数，0 表示可正常登录
function check_login_cooldown($username) {
    $file = login_attempt_file($username);
    if (!file_exists($file)) {
        return 0;
    }
    $data = json_decode((string)file_get_contents($file), true) ?: [];
    $now = time();
    // 仅统计冷却时间窗口内的失败记录
    $recent = array_values(array_filter($data, function($t) use ($now) {
        return is_int($t) && ($now - $t) < LOGIN_COOLDOWN;
    }));
    if (count($recent) >= MAX_LOGIN_ATTEMPTS) {
        $oldest = min($recent);
        return max(1, LOGIN_COOLDOWN - ($now - $oldest));
    }
    return 0;
}

function record_login_failure($username) {
    $file = login_attempt_file($username);
    $data = file_exists($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
    $data[] = time();
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

function clear_login_failures($username) {
    $file = login_attempt_file($username);
    if (file_exists($file)) {
        @unlink($file);
    }
}

// 如果已经登录，跳转到面板
if (is_logged_in()) {
    header('Location: ' . (defined('SITE_URL') ? SITE_URL : '') . '/dashboard');
    exit;
}

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 确保数据库连接可用
    ensure_database_connection();
    
    // CSRF 验证：token 必须存在且匹配
    $csrf_valid = !empty($_POST['csrf_token']) && verify_csrf_token($_POST['csrf_token']);

    if (!$csrf_valid) {
        $error_message = '安全验证失败，请重试';
    } else {
        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error_message = '请填写用户名和密码';
        } else {
            // 防爆破：失败次数达到上限时强制冷却
            $cooldown = check_login_cooldown($username);
            if ($cooldown > 0) {
                $error_message = '尝试次数过多，请 ' . max(1, (int)ceil($cooldown / 60)) . ' 分钟后再试';
            } else {
                // 验证用户
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // 登录成功，清除失败记录
                    clear_login_failures($username);
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
                    // 登录失败，记录失败次数用于防爆破
                    record_login_failure($username);
                    $error_message = '用户名或密码错误';
                }
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
