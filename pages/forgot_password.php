<?php
// 安全启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 智能路径解析
$config_path = file_exists('../config/config.php') ? '../config/config.php' : __DIR__ . '/../config/config.php';
$functions_path = file_exists('../includes/functions.php') ? '../includes/functions.php' : __DIR__ . '/../includes/functions.php';
$email_config_path = file_exists('../config/email.php') ? '../config/email.php' : __DIR__ . '/../config/email.php';

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

// 检查邮件配置
if (file_exists($email_config_path)) {
    require_once $email_config_path;
}

$message = '';
$error = '';

// 处理表单提交
if ($_POST) {
    // CSRF 校验（支持表单字段或 X-CSRF-Token 请求头）
    $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = '安全验证失败，请刷新页面重试';
    } else {
        process_password_reset_request();
    }
}

function process_password_reset_request() {
    global $pdo, $message, $error;
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = '请输入邮箱地址';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '请输入有效的邮箱地址';
    } else {
        try {
            // 检查用户是否存在
            $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && !empty($user['username']) && !empty($user['email'])) {
                // 检查频率限制 - 5分钟内只能发送一次
                $stmt = $pdo->prepare("SELECT created_at FROM password_resets WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
                $stmt->execute([$email]);
                $recent_request = $stmt->fetch();
                
                if ($recent_request) {
                    $error = '重置邮件发送过于频繁，请5分钟后再试';
                } else {
                    // 生成重置令牌
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                    
                    // 清理旧令牌
                    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
                    
                    // 插入新令牌
                    $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, email, reset_token, expires_at, ip_address) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$user['id'], $email, $token, $expires, $ip_address]);
                    
                    // 构建重置URL
                    $reset_url = SITE_URL . '/pages/reset_password.php?token=' . $token;
                    
                    // 发送邮件
                    $subject = "密码重置验证 - 车友帮讯联";
                    $body = createPasswordResetEmail($user, $reset_url);
                    
                    if (function_exists('isEmailConfigured') && isEmailConfigured()) {
                        // 使用异步邮件发送，不阻塞用户界面
                        if (function_exists('queueEmail')) {
                            queueEmail($email, $subject, $body, 'high'); // 密码重置优先级较高
                            $message = '重置链接已发送到您的邮箱，请查收！（请检查垃圾邮件文件夹）';
                        } else if (sendEmailQuick($email, $subject, $body)) {
                            $message = '重置链接已发送到您的邮箱，请查收！（请检查垃圾邮件文件夹）';
                        } else {
                            $error = '邮件发送失败，请稍后重试';
                        }
                    } else {
                        $error = '邮件系统未配置，无法发送重置邮件';
                    }
                }
            } else {
                // 即使用户不存在也显示成功消息（安全考虑）
                $message = '如果该邮箱存在，重置链接已发送！';
            }
        } catch (Exception $e) {
            $error = '系统错误，请稍后重试';
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
    <title>忘记密码 - 车友帮讯联</title>
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
                            <i class="fas fa-key fa-3x text-primary mb-3"></i>
                            <h4>忘记密码</h4>
                            <p class="text-muted">输入您的邮箱地址</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($message): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                                <hr>
                                <div class="d-grid gap-2">
                                    <a href="<?php echo SITE_URL; ?>/login" class="btn btn-success">返回登录</a>
                                    <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">重新发送</button>
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <div class="mb-3">
                                    <label for="email" class="form-label">邮箱地址</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                           placeholder="请输入您的邮箱" required>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>我们将向此邮箱发送密码重置链接
                                    </div>
                                </div>
                                
                                <div class="mb-3 p-3 bg-light rounded">
                                    <h6 class="text-primary mb-2">
                                        <i class="fas fa-clock me-1"></i>重置说明
                                    </h6>
                                    <ul class="mb-0 small text-muted">
                                        <li>重置链接有效期：1小时</li>
                                        <li>发送频率限制：每个邮箱5分钟一次</li>
                                        <li>请检查垃圾邮件文件夹</li>
                                        <li>确保邮箱地址准确无误</li>
                                    </ul>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i>发送重置链接
                                    </button>
                                </div>
                            </form>
                            
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
</body>
</html>
