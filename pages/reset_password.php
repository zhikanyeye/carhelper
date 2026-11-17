<?php
// 安全启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 智能路径解析
$config_path = file_exists('../config/config.php') ? '../config/config.php' : __DIR__ . '/../config/config.php';
$functions_path = file_exists('../includes/functions.php') ? '../includes/functions.php' : __DIR__ . '/../includes/functions.php';

// 添加调试信息（仅在错误时显示）
if (!file_exists($config_path)) {
    die("配置文件未找到: " . $config_path);
}

require_once $config_path;
require_once $functions_path;

// 确保 SITE_URL 常量已定义
if (!defined('SITE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $dir_name = dirname($script_name);
    
    // 排除 pages 目录，使用根路径
    if (strpos($dir_name, '/pages') !== false) {
        $base_path = '';
    } else {
        $base_path = ($dir_name === '/' || $dir_name === '\\') ? '' : $dir_name;
    }
    
    define('SITE_URL', $protocol . $host . $base_path);
}

$message = '';
$error = '';
$token = $_GET['token'] ?? '';
$valid_token = false;
$user_data = null;

// 验证令牌
if ($token) {
    try {
        $stmt = $pdo->prepare("
            SELECT pr.user_id, pr.email, u.username, pr.used 
            FROM password_resets pr 
            JOIN users u ON pr.user_id = u.id 
            WHERE pr.reset_token = ? AND pr.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $user_data = $stmt->fetch();
        
        if ($user_data) {
            if ($user_data['used']) {
                $error = '此重置链接已使用过，请重新申请密码重置';
            } else {
                $valid_token = true;
            }
        } else {
            $error = '重置链接无效或已过期，请重新申请密码重置';
        }
    } catch (Exception $e) {
        $error = '系统错误';
        error_log("Token validation error: " . $e->getMessage());
    }
} else {
    $error = '缺少重置令牌，请通过忘记密码页面重新申请';
}

// 处理密码重置
if ($_POST && $valid_token) {
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password)) {
        $error = '请输入新密码';
    } elseif (strlen($new_password) < 6) {
        $error = '密码至少需要6个字符';
    } elseif ($new_password !== $confirm_password) {
        $error = '两次输入的密码不一致';
    } else {
        try {
            // 开始事务
            $pdo->beginTransaction();
            
            // 更新密码
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashed_password, $user_data['user_id']]);
            
            // 标记令牌为已使用
            $stmt = $pdo->prepare("UPDATE password_resets SET used = 1, used_at = NOW() WHERE reset_token = ?");
            $stmt->execute([$token]);
            
            // 删除该用户的其他重置令牌
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ? AND reset_token != ?");
            $stmt->execute([$user_data['user_id'], $token]);
            
            // 提交事务
            $pdo->commit();
            
            $message = '密码重置成功！您现在可以使用新密码登录。';
            $valid_token = false; // 防止再次提交
            
        } catch (Exception $e) {
            // 回滚事务
            $pdo->rollBack();
            $error = '密码重置失败，请重试';
            error_log("Password reset error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>重置密码 - 车友帮讯联</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo SITE_URL; ?>/css/style.css" rel="stylesheet">
    
    <!-- 网站图标 -->
    <link rel="icon" type="image/svg+xml" href="<?php echo SITE_URL; ?>/assets/logo.svg">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <i class="fas fa-lock fa-3x text-success mb-3"></i>
                            <h4>重置密码</h4>
                            <?php if ($user_data): ?>
                                <p class="text-muted">为用户: <?php echo htmlspecialchars($user_data['username']); ?></p>
                            <?php endif; ?>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                                <hr>
                                <a href="<?php echo SITE_URL; ?>/forgot_password" class="btn btn-outline-primary">重新申请重置</a>
                            </div>
                        <?php endif; ?>

                        <?php if ($message): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                                <hr>
                                <div class="d-grid gap-2">
                                    <a href="<?php echo SITE_URL; ?>/login" class="btn btn-success">立即登录</a>
                                    <a href="<?php echo SITE_URL; ?>/" class="btn btn-outline-secondary">返回首页</a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($valid_token && !$message): ?>
                            <form method="POST" id="resetForm">
                                <div class="mb-3">
                                    <label for="password" class="form-label">新密码</label>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="请输入新密码" required minlength="6">
                                    <div class="form-text">密码至少6个字符</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">确认密码</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           placeholder="请再次输入密码" required>
                                    <div class="invalid-feedback">
                                        密码不一致
                                    </div>
                                </div>
                                
                                <div class="mb-3 p-3 bg-light rounded">
                                    <h6 class="text-success mb-2">
                                        <i class="fas fa-shield-alt me-1"></i>密码安全建议
                                    </h6>
                                    <ul class="mb-0 small text-muted">
                                        <li>使用至少6个字符</li>
                                        <li>包含大小写字母、数字和特殊符号</li>
                                        <li>避免使用常见密码</li>
                                        <li>定期更换密码</li>
                                    </ul>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success" id="submitBtn">
                                        <i class="fas fa-save me-2"></i>重置密码
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <?php if (!$message): ?>
                        <div class="text-center mt-3">
                            <a href="<?php echo SITE_URL; ?>/login" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>返回登录
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo SITE_URL; ?>/js/script.js"></script>
    <script>
        // 密码确认验证
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const submitBtn = document.getElementById('submitBtn');
            
            function validatePassword() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('密码不一致');
                    confirmPassword.classList.add('is-invalid');
                    confirmPassword.classList.remove('is-valid');
                } else {
                    confirmPassword.setCustomValidity('');
                    confirmPassword.classList.remove('is-invalid');
                    if (confirmPassword.value.length >= 6) {
                        confirmPassword.classList.add('is-valid');
                    }
                }
            }
            
            function validateStrength() {
                const pwd = password.value;
                if (pwd.length >= 6) {
                    password.classList.add('is-valid');
                    password.classList.remove('is-invalid');
                } else if (pwd.length > 0) {
                    password.classList.add('is-invalid');
                    password.classList.remove('is-valid');
                } else {
                    password.classList.remove('is-valid', 'is-invalid');
                }
            }
            
            if (password && confirmPassword) {
                password.addEventListener('input', function() {
                    validateStrength();
                    validatePassword();
                });
                confirmPassword.addEventListener('input', validatePassword);
                
                // 防止重复提交
                document.getElementById('resetForm').addEventListener('submit', function() {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>处理中...';
                });
            }
        });
    </script>
</body>
</html>
