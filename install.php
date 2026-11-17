<?php
// 安装向导 - 类似WordPress的便捷安装界面
// 安全启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查是否已经安装
if (file_exists('config/installed.lock')) {
    header('Location: /');
    exit;
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 2: // 数据库配置
            $db_host = trim($_POST['db_host'] ?? '');
            $db_name = trim($_POST['db_name'] ?? '');
            $db_user = trim($_POST['db_user'] ?? '');
            $db_pass = $_POST['db_pass'] ?? '';
            
            if (empty($db_host) || empty($db_name) || empty($db_user)) {
                $error = '请填写所有必填项';
            } else {
                // 测试数据库连接
                try {
                    $test_pdo = new PDO(
                        "mysql:host={$db_host};charset=utf8mb4",
                        $db_user,
                        $db_pass,
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );
                    
                    // 检查或创建数据库
                    $test_pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $test_pdo->exec("USE `{$db_name}`");
                    
                    // 保存数据库配置到session
                    $_SESSION['db_config'] = [
                        'host' => $db_host,
                        'name' => $db_name,
                        'user' => $db_user,
                        'pass' => $db_pass
                    ];
                    
                    header('Location: install.php?step=3');
                    exit;
                    
                } catch (PDOException $e) {
                    $error_msg = $e->getMessage();
                    $error_code = $e->getCode();
                    
                    // 提供具体的错误信息和解决建议
                    if ($error_code == 1045) {
                        $error = '数据库访问被拒绝：用户名或密码错误。<br><strong>快速解决：</strong><a href="db_fix.php" target="_blank" class="btn btn-sm btn-danger ms-2"><i class="bi bi-tools"></i> 数据库修复工具</a>';
                    } elseif ($error_code == 2002) {
                        $error = '无法连接到数据库服务器，请检查服务器地址是否正确';
                    } elseif ($error_code == 1049) {
                        $error = '数据库不存在，请先在主机控制面板中创建数据库';
                    } else {
                        $error = '数据库连接失败: ' . $error_msg;
                    }
                }
            }
            break;
            
        case 3: // 邮件配置
            $email_type = $_POST['email_type'] ?? '';
            $smtp_user = trim($_POST['smtp_user'] ?? '');
            $smtp_pass = trim($_POST['smtp_pass'] ?? '');
            
            // 验证基本字段
            if (empty($email_type) || empty($smtp_user) || empty($smtp_pass)) {
                $error = '请填写所有邮件配置项';
            } else {
                // 构建邮件配置数组
                $email_config = [
                    'type' => $email_type,
                    'user' => $smtp_user,
                    'pass' => $smtp_pass
                ];
                
                // 如果是自定义邮箱，处理额外的配置
                if ($email_type === 'custom') {
                    $custom_host = trim($_POST['custom_smtp_host'] ?? '');
                    $custom_port = (int)($_POST['custom_smtp_port'] ?? 587);
                    $custom_encryption = $_POST['custom_smtp_encryption'] ?? 'tls';
                    $custom_from_name = trim($_POST['custom_from_name'] ?? '车友帮讯联');
                    $custom_auth = isset($_POST['custom_smtp_auth']) ? 'on' : 'off';
                    
                    // 验证自定义配置
                    if (empty($custom_host)) {
                        $error = '请填写SMTP服务器地址';
                    } elseif ($custom_port < 1 || $custom_port > 65535) {
                        $error = '请输入有效的端口号 (1-65535)';
                    } else {
                        // 添加自定义配置到邮件配置数组
                        $email_config['custom'] = [
                            'host' => $custom_host,
                            'port' => $custom_port,
                            'encryption' => $custom_encryption,
                            'from_name' => $custom_from_name,
                            'auth' => $custom_auth
                        ];
                    }
                }
                
                // 如果没有错误，保存配置并跳转
                if (empty($error)) {
                    $_SESSION['email_config'] = $email_config;
                    header('Location: install.php?step=4');
                    exit;
                }
            }
            break;
            
        case 4: // 管理员账号
            $admin_username = trim($_POST['admin_username'] ?? '');
            $admin_email = trim($_POST['admin_email'] ?? '');
            $admin_password = trim($_POST['admin_password'] ?? '');
            $confirm_password = trim($_POST['confirm_password'] ?? '');
            
            if (empty($admin_username) || empty($admin_email) || empty($admin_password)) {
                $error = '请填写所有字段';
            } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
                $error = '请输入有效的邮箱地址';
            } elseif (strlen($admin_password) < 6) {
                $error = '密码至少6个字符';
            } elseif ($admin_password !== $confirm_password) {
                $error = '两次输入的密码不一致';
            } else {
                $_SESSION['admin_config'] = [
                    'username' => $admin_username,
                    'email' => $admin_email,
                    'password' => $admin_password
                ];
                
                header('Location: install.php?step=5');
                exit;
            }
            break;
            
        case 5: // 执行安装
            $install_result = performInstallation();
            if ($install_result === true) {
                header('Location: install.php?step=6');
                exit;
            } else {
                $error = '安装过程中出现错误：' . $install_result;
            }
            break;
    }
}

// 执行安装
function performInstallation() {
    try {
        // 检查必要的目录
        if (!is_dir('config')) {
            return '配置目录(config)不存在，请检查文件上传是否完整';
        }
        
        if (!is_writable('config')) {
            return '配置目录(config)不可写，请设置目录权限为755或777';
        }
        
        if (!is_dir('qrcodes')) {
            return '二维码目录(qrcodes)不存在。请检查：1) 文件上传是否完整 2) 手动创建qrcodes目录 3) 重新下载v1.3完整版压缩包';
        }
        
        if (!is_writable('qrcodes')) {
            return '二维码目录(qrcodes)不可写，请设置目录权限为755或777';
        }
        
        // 检查uploads目录（可选，但建议存在）
        if (!is_dir('uploads')) {
            // 尝试创建uploads目录
            if (!mkdir('uploads', 0755, true)) {
                return 'uploads目录不存在且无法创建，请手动创建uploads目录并设置权限为755';
            }
        }
        
        if (!is_writable('uploads')) {
            return 'uploads目录不可写，请设置目录权限为755或777';
        }
        
        // 1. 写入数据库配置文件
        $db_config = $_SESSION['db_config'];
        $db_config_content = '<?php
// 数据库配置 - 由安装向导自动生成
define(\'DB_HOST\', \'' . addslashes($db_config['host']) . '\');
define(\'DB_USERNAME\', \'' . addslashes($db_config['user']) . '\');
define(\'DB_PASSWORD\', \'' . addslashes($db_config['pass']) . '\');
define(\'DB_NAME\', \'' . addslashes($db_config['name']) . '\');

// 创建数据库连接
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USERNAME,
        DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}
?>';
        
        if (!file_put_contents('config/database.php', $db_config_content)) {
            return '无法写入数据库配置文件，请检查config目录权限';
        }
        
        // 2. 写入邮件配置文件
        $email_config = $_SESSION['email_config'];
        
        // 预设SMTP配置
        $smtp_configs = [
            'carcall' => ['host' => 'mail.carcall.endl.site', 'port' => 587, 'encryption' => 'tls'],
            'qq' => ['host' => 'smtp.qq.com', 'port' => 587, 'encryption' => 'tls'],
            '163' => ['host' => 'smtp.163.com', 'port' => 25, 'encryption' => 'none'],
            'gmail' => ['host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls']
        ];
        
        // 确定SMTP配置
        if ($email_config['type'] === 'custom') {
            $smtp_info = [
                'host' => $email_config['custom']['host'],
                'port' => $email_config['custom']['port'],
                'encryption' => $email_config['custom']['encryption'],
                'from_name' => $email_config['custom']['from_name'],
                'auth' => $email_config['custom']['auth']
            ];
        } else {
            $smtp_info = $smtp_configs[$email_config['type']];
            $smtp_info['from_name'] = '车友帮讯联';
            $smtp_info['auth'] = 'on';
        }
        
        $email_config_content = '<?php
// 邮件配置 - 由安装向导自动生成
define(\'SMTP_HOST\', \'' . addslashes($smtp_info['host']) . '\');
define(\'SMTP_PORT\', ' . (int)$smtp_info['port'] . ');
define(\'SMTP_USERNAME\', \'' . addslashes($email_config['user']) . '\');
define(\'SMTP_PASSWORD\', \'' . addslashes($email_config['pass']) . '\');
define(\'SMTP_FROM_NAME\', \'' . addslashes($smtp_info['from_name']) . '\');
define(\'SMTP_ENCRYPTION\', \'' . addslashes($smtp_info['encryption']) . '\');
define(\'SMTP_AUTH\', \'' . addslashes($smtp_info['auth']) . '\');
define(\'EMAIL_TYPE\', \'' . addslashes($email_config['type']) . '\');

// 检查邮件配置是否完整
function isEmailConfigured() {
    // 检查必要的常量是否定义
    if (!defined(\'SMTP_HOST\') || !defined(\'SMTP_USERNAME\') || !defined(\'SMTP_PASSWORD\') || !defined(\'SMTP_PORT\')) {
        return false;
    }
    
    // 检查是否是默认占位符
    $defaultValues = [
        \'your@carcall.endl.site\',
        \'your_qq@qq.com\', 
        \'your_name@163.com\',
        \'your_email@gmail.com\',
        \'your_carcall_password\',
        \'your_authorization_code\',
        \'your_client_password\',
        \'your_app_password\'
    ];
    
    if (in_array(SMTP_USERNAME, $defaultValues) || in_array(SMTP_PASSWORD, $defaultValues)) {
        return false;
    }
    
    // 检查基本格式
    if (!filter_var(SMTP_USERNAME, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // 检查密码长度
    if (strlen(SMTP_PASSWORD) < 3) {
        return false;
    }
    
    return true;
}

// 测试邮件配置连接
function testEmailConnection() {
    if (!isEmailConfigured()) {
        return [\'success\' => false, \'message\' => \'邮件配置不完整\'];
    }
    
    try {
        $smtp = fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 10);
        if (!$smtp) {
            return [\'success\' => false, \'message\' => "无法连接到SMTP服务器：$errstr"];
        }
        
        $response = fgets($smtp, 515);
        fclose($smtp);
        
        if (substr($response, 0, 3) == \'220\') {
            return [\'success\' => true, \'message\' => \'SMTP服务器连接正常\'];
        } else {
            return [\'success\' => false, \'message\' => "SMTP服务器响应异常：$response"];
        }
    } catch (Exception $e) {
        return [\'success\' => false, \'message\' => \'连接测试异常：\' . $e->getMessage()];
    }
}

// 邮件发送函数
function sendEmail($to, $subject, $message) {
    if (!isEmailConfigured()) {
        error_log("邮件发送失败：邮件未配置");
        return false;
    }
    
    $headers = array(
        \'From\' => SMTP_FROM_NAME . \' <\' . SMTP_USERNAME . \'>\',
        \'Reply-To\' => SMTP_USERNAME,
        \'Content-Type\' => \'text/html; charset=UTF-8\',
        \'X-Mailer\' => \'PHP/\' . phpversion()
    );
    
    $header_string = \'\';
    foreach ($headers as $key => $value) {
        $header_string .= $key . \': \' . $value . "\\r\\n";
    }
    
    return mail($to, $subject, $message, $header_string);
}

function sendMessageNotification($userEmail, $username, $messageContent) {
    if (!isEmailConfigured()) {
        error_log("邮件通知发送失败：邮件未配置");
        return false;
    }
    
    $subject = "【车友帮讯联】您收到了一条新留言";
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset=\'UTF-8\'>
        <title>新留言通知</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #007bff; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background: #f8f9fa; padding: 20px; border: 1px solid #ddd; }
            .message-box { background: white; border: 1px solid #ddd; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
            .footer { background: #6c757d; color: white; padding: 15px; text-align: center; border-radius: 0 0 5px 5px; font-size: 12px; }
            .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class=\'container\'>
            <div class=\'header\'>
                <h2>🚗 车友帮讯联</h2>
                <p>您收到了一条新留言！</p>
            </div>
            <div class=\'content\'>
                <p>亲爱的 <strong>{$username}</strong>，</p>
                <p>有车友给您留言了！以下是留言内容：</p>
                
                <div class=\'message-box\'>
                    <h4>📝 留言内容：</h4>
                    <p>" . nl2br(htmlspecialchars($messageContent)) . "</p>
                </div>
                
                <p>
                    <a href=\'" . (defined(\'SITE_URL\') ? SITE_URL : \'http://carcall.endl.site\') . "/view_messages\' class=\'btn\'>
                        👀 立即查看留言
                    </a>
                </p>
                
                <p><small>提示：请及时登录系统查看并回复留言，保持良好的沟通体验。</small></p>
            </div>
            <div class=\'footer\'>
                <p>此邮件由车友帮讯联系统自动发送，请勿直接回复此邮件。</p>
                <p>如有问题，请访问系统或联系管理员。</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $result = sendEmail($userEmail, $subject, $message);
    
    if (!$result) {
        error_log("留言通知邮件发送失败 - 收件人: $userEmail, 用户: $username");
    } else {
        error_log("留言通知邮件发送成功 - 收件人: $userEmail");
    }
    
    return $result;
}
?>';
        
        if (!file_put_contents('config/email.php', $email_config_content)) {
            return '无法写入邮件配置文件，请检查config目录权限';
        }
        
        // 3. 导入数据库表结构
        $sql_file = 'database.sql';
        if (!file_exists($sql_file)) {
            return '数据库结构文件不存在，请检查 database.sql 文件是否上传完整';
        }
        
        require_once 'config/database.php';
        
        $sql_content = file_get_contents($sql_file);
        if ($sql_content === false) {
            return '无法读取数据库结构文件(' . $sql_file . ')';
        }
        
        // 分割SQL语句并执行
        // 特殊处理DELIMITER语句
        $sql_content = str_replace('DELIMITER $$', '', $sql_content);
        $sql_content = str_replace('DELIMITER ;', '', $sql_content);
        $sql_content = str_replace('$$', ';', $sql_content);
        
        $sql_statements = explode(';', $sql_content);
        foreach ($sql_statements as $sql) {
            $sql = trim($sql);
            if (!empty($sql)) {
                try {
                    // 跳过已存在表的创建错误和触发器已存在错误
                    $pdo->exec($sql);
                } catch (PDOException $e) {
                    // 如果是表已存在错误、触发器已存在错误或UUID()函数不支持，继续执行其他语句
                    $error_codes = ['42S01', '42000', 'HY000']; // 表已存在、语法错误、函数不支持
                    if (in_array($e->getCode(), $error_codes) || 
                        strpos($e->getMessage(), 'already exists') !== false ||
                        strpos($e->getMessage(), 'UUID') !== false) {
                        // 继续执行下一条语句
                        continue;
                    }
                    return '执行SQL语句失败: ' . $e->getMessage();
                }
            }
        }
        
        // 4. 创建管理员账号
        $admin_config = $_SESSION['admin_config'];
        $hashed_password = password_hash($admin_config['password'], PASSWORD_DEFAULT);
        
        // 确保includes/functions.php可用以使用generate_uuid函数
        if (!function_exists('generate_uuid')) {
            // 如果functions.php不可用，使用备用UUID生成方法
            function generate_uuid() {
                if (function_exists('random_bytes')) {
                    $data = random_bytes(16);
                    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
                    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
                    return sprintf('%08s-%04s-%04s-%04s-%12s',
                        bin2hex(substr($data, 0, 4)),
                        bin2hex(substr($data, 4, 2)),
                        bin2hex(substr($data, 6, 2)),
                        bin2hex(substr($data, 8, 2)),
                        bin2hex(substr($data, 10, 6))
                    );
                } else {
                    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                        mt_rand(0, 0xffff),
                        mt_rand(0, 0x0fff) | 0x4000,
                        mt_rand(0, 0x3fff) | 0x8000,
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                    );
                }
            }
        }
        
        try {
            // 先检查管理员是否已存在
            $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check_stmt->execute([$admin_config['username'], $admin_config['email']]);
            $existing_admin = $check_stmt->fetch();
            
            if ($existing_admin) {
                // 管理员已存在，更新为管理员权限
                $update_stmt = $pdo->prepare("UPDATE users SET is_admin = 1, email_verified = 1 WHERE id = ?");
                $update_stmt->execute([$existing_admin['id']]);
            } else {
                // 创建新管理员
                $admin_uuid = generate_uuid(); // 生成UUID
                $stmt = $pdo->prepare("INSERT INTO users (uuid, username, email, password, is_admin, email_verified) VALUES (?, ?, ?, ?, 1, 1)");
                $stmt->execute([$admin_uuid, $admin_config['username'], $admin_config['email'], $hashed_password]);
            }
        } catch (PDOException $e) {
            // 如果是重复条目错误，继续安装
            if ($e->getCode() == '23000') {
                // 用户已存在，尝试更新权限
                try {
                    $update_stmt = $pdo->prepare("UPDATE users SET is_admin = 1, email_verified = 1 WHERE username = ? OR email = ?");
                    $update_stmt->execute([$admin_config['username'], $admin_config['email']]);
                } catch (PDOException $update_e) {
                    // 更新失败，但不阻止安装继续
                }
            } else {
                return '创建管理员账号失败: ' . $e->getMessage();
            }
        }
        
        // 4.5. 为可能已存在但没有UUID的用户补充UUID（备用方案）
        try {
            $users_without_uuid = $pdo->query("SELECT id, username FROM users WHERE uuid IS NULL OR uuid = ''")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($users_without_uuid as $user) {
                $uuid = generate_uuid();
                $update_uuid_stmt = $pdo->prepare("UPDATE users SET uuid = ? WHERE id = ?");
                $update_uuid_stmt->execute([$uuid, $user['id']]);
            }
        } catch (PDOException $e) {
            // UUID补充失败不影响安装继续，因为新用户会有UUID
        }
        
        // 5. 创建安装锁定文件
        $install_info = [
            'installed_at' => date('Y-m-d H:i:s'),
            'version' => '1.0.0',
            'admin_email' => $admin_config['email']
        ];
        
        if (!file_put_contents('config/installed.lock', json_encode($install_info))) {
            return '无法创建安装锁定文件，请检查config目录权限';
        }
        
        // 6. 清理session数据
        unset($_SESSION['db_config'], $_SESSION['email_config'], $_SESSION['admin_config']);
        
        return true;
        
    } catch (Exception $e) {
        return '系统错误: ' . $e->getMessage() . ' (详细信息请查看服务器错误日志)';
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>车友帮讯联 - 安装向导</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .install-container { max-width: 600px; margin: 30px auto; }
        .step-indicator { display: flex; justify-content: center; margin-bottom: 30px; }
        .step-item { 
            width: 40px; height: 40px; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center;
            margin: 0 10px; color: white; font-weight: bold;
        }
        .step-item.active { background: #28a745; }
        .step-item.completed { background: #007bff; }
        .step-item.pending { background: #6c757d; }
        .step-line { width: 50px; height: 2px; background: #6c757d; margin-top: 19px; }
        .step-line.completed { background: #007bff; }
    </style>
</head>
<body>
    <div class="container install-container">
        <div class="card shadow">
            <div class="card-header text-center bg-primary text-white">
                <h3><i class="bi bi-gear"></i> 车友帮讯联安装向导</h3>
                <p class="mb-0">快速配置您的车友帮讯联系统</p>
            </div>
            
            <div class="card-body">
                <!-- 步骤指示器 -->
                <div class="step-indicator">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <?php if ($i > 1): ?>
                        <div class="step-line <?php echo $step > $i ? 'completed' : ''; ?>"></div>
                        <?php endif; ?>
                        <div class="step-item <?php 
                            echo $step == $i ? 'active' : ($step > $i ? 'completed' : 'pending'); 
                        ?>"><?php echo $i; ?></div>
                    <?php endfor; ?>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($step == 1): // 欢迎页面 ?>
                <div class="text-center">
                    <h4><i class="bi bi-house-heart"></i> 欢迎使用车友帮讯联</h4>
                    <p class="text-muted">车友帮讯联，这个安装向导将帮助您快速配置</p>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="bi bi-server text-primary" style="font-size: 2rem;"></i>
                                    <h6 class="mt-2">环境检查</h6>
                                    <small class="text-muted">PHP <?php echo PHP_VERSION; ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="bi bi-shield-check text-success" style="font-size: 2rem;"></i>
                                    <h6 class="mt-2">安全配置</h6>
                                    <small class="text-muted">自动安全设置</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h6>安装要求：</h6>
                        <ul class="text-start">
                            <li>✅ PHP 7.0+</li>
                            <li>✅ MySQL 5.7+</li>
                            <li>✅ 可写权限</li>
                            <li>✅ 邮件支持</li>
                        </ul>
                    </div>
                    
                    <a href="?step=2" class="btn btn-primary btn-lg mt-3">
                        <i class="bi bi-arrow-right"></i> 开始安装
                    </a>
                </div>
                
                <?php elseif ($step == 2): // 数据库配置 ?>
                <h4><i class="bi bi-database"></i> 数据库配置</h4>
                <p class="text-muted">请输入您的MySQL数据库信息</p>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">数据库主机 *</label>
                        <input type="text" class="form-control" name="db_host" 
                               value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" 
                               placeholder="通常是 localhost" required>
                        <small class="text-muted">一般情况下是 localhost</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">数据库名称 *</label>
                        <input type="text" class="form-control" name="db_name" 
                               value="<?php echo htmlspecialchars($_POST['db_name'] ?? ''); ?>" 
                               placeholder="例如: car_message_system" required>
                        <small class="text-muted">系统将自动创建此数据库（如果不存在）</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">数据库用户名 *</label>
                        <input type="text" class="form-control" name="db_user" 
                               value="<?php echo htmlspecialchars($_POST['db_user'] ?? ''); ?>" 
                               placeholder="数据库用户名" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">数据库密码</label>
                        <input type="password" class="form-control" name="db_pass" 
                               value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>" 
                               placeholder="数据库密码（可为空）">
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="?step=1" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> 上一步
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-arrow-right"></i> 下一步
                        </button>
                    </div>
                </form>
                
                <?php elseif ($step == 3): // 邮件配置 ?>
                <h4><i class="bi bi-envelope"></i> 邮件配置</h4>
                <p class="text-muted">配置邮件发送，用于通知新留言</p>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">邮箱类型 *</label>
                        <select class="form-select" name="email_type" required onchange="updateEmailPlaceholder()">
                            <option value="">请选择邮箱类型</option>
                            <option value="carcall" <?php echo ($_POST['email_type'] ?? '') == 'carcall' ? 'selected' : ''; ?>>
                                CarCall邮箱 (推荐，系统专用)
                            </option>
                            <option value="qq" <?php echo ($_POST['email_type'] ?? '') == 'qq' ? 'selected' : ''; ?>>
                                QQ邮箱
                            </option>
                            <option value="163" <?php echo ($_POST['email_type'] ?? '') == '163' ? 'selected' : ''; ?>>
                                163邮箱
                            </option>
                            <option value="gmail" <?php echo ($_POST['email_type'] ?? '') == 'gmail' ? 'selected' : ''; ?>>
                                Gmail邮箱
                            </option>
                            <option value="custom" <?php echo ($_POST['email_type'] ?? '') == 'custom' ? 'selected' : ''; ?>>
                                自定义邮箱 (高级用户)
                            </option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">邮箱地址 *</label>
                        <input type="email" class="form-control" name="smtp_user" 
                               value="<?php echo htmlspecialchars($_POST['smtp_user'] ?? ''); ?>" 
                               placeholder="your@carcall.endl.site" id="email-input" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">邮箱密码 *</label>
                        <input type="password" class="form-control" name="smtp_pass" 
                               value="<?php echo htmlspecialchars($_POST['smtp_pass'] ?? ''); ?>" 
                               placeholder="请输入邮箱密码" id="password-input" required>
                        <small class="text-muted" id="password-help">
                            请输入您的CarCall邮箱密码
                        </small>
                    </div>

                    <!-- 自定义邮箱配置字段 -->
                    <div id="custom-email-config" style="display: none;">
                        <div class="card border-primary mb-3">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-gear"></i> 自定义SMTP配置</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">快速配置</label>
                                    <div class="btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="fillEmailConfig('aliyun')">阿里云邮箱</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="fillEmailConfig('tencent')">腾讯企业邮箱</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="fillEmailConfig('outlook')">微软Outlook</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="fillEmailConfig('yahoo')">Yahoo邮箱</button>
                                    </div>
                                    <small class="text-muted d-block mt-1">点击按钮快速填写常见邮箱配置</small>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label class="form-label">SMTP服务器地址 *</label>
                                            <input type="text" class="form-control" name="custom_smtp_host" 
                                                   value="<?php echo htmlspecialchars($_POST['custom_smtp_host'] ?? ''); ?>" 
                                                   placeholder="例如: smtp.example.com">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">端口 *</label>
                                            <input type="number" class="form-control" name="custom_smtp_port" 
                                                   value="<?php echo htmlspecialchars($_POST['custom_smtp_port'] ?? '587'); ?>" 
                                                   placeholder="587" min="1" max="65535">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">加密方式</label>
                                            <select class="form-select" name="custom_smtp_encryption">
                                                <option value="tls" <?php echo ($_POST['custom_smtp_encryption'] ?? 'tls') == 'tls' ? 'selected' : ''; ?>>TLS (推荐)</option>
                                                <option value="ssl" <?php echo ($_POST['custom_smtp_encryption'] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                                <option value="none" <?php echo ($_POST['custom_smtp_encryption'] ?? '') == 'none' ? 'selected' : ''; ?>>无加密</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">发件人名称</label>
                                            <input type="text" class="form-control" name="custom_from_name" 
                                                   value="<?php echo htmlspecialchars($_POST['custom_from_name'] ?? '车友帮讯联'); ?>" 
                                                   placeholder="车友帮讯联">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="custom_smtp_auth" id="custom_smtp_auth" 
                                               <?php echo ($_POST['custom_smtp_auth'] ?? 'on') == 'on' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="custom_smtp_auth">
                                            需要SMTP身份验证 (推荐开启)
                                        </label>
                                    </div>
                                </div>

                                <div class="alert alert-warning">
                                    <h6><i class="bi bi-exclamation-triangle"></i> 注意事项：</h6>
                                    <ul class="mb-0">
                                        <li>请确保您的邮箱服务商支持SMTP发送</li>
                                        <li>某些邮箱需要开启"第三方应用授权"</li>
                                        <li>建议使用TLS加密方式，端口587</li>
                                        <li>如果连接失败，请联系您的邮箱服务商</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> 获取授权码方法：</h6>
                        <div id="email-help">
                            <strong>CarCall邮箱（推荐）：</strong>
                            <ol class="mb-0">
                                <li>使用您申请的CarCall邮箱账号</li>
                                <li>SMTP服务器：mail.carcall.endl.site</li>
                                <li>端口：587（安全连接）</li>
                                <li>请准备好您的邮箱地址和密码</li>
                            </ol>
                        </div>
                    </div>

                    <!-- 自定义邮箱配置示例 -->
                    <div id="custom-email-examples" style="display: none;">
                        <div class="alert alert-success">
                            <h6><i class="bi bi-lightbulb"></i> 常见邮箱服务商配置示例：</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>企业邮箱：</strong>
                                    <ul class="small">
                                        <li>阿里云邮箱：smtp.mxhichina.com:465 (SSL)</li>
                                        <li>腾讯企业邮箱：smtp.exmail.qq.com:587 (TLS)</li>
                                        <li>微软Outlook：smtp-mail.outlook.com:587 (TLS)</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <strong>国外邮箱：</strong>
                                    <ul class="small">
                                        <li>Yahoo：smtp.mail.yahoo.com:587 (TLS)</li>
                                        <li>Hotmail：smtp.live.com:587 (TLS)</li>
                                        <li>iCloud：smtp.mail.me.com:587 (TLS)</li>
                                    </ul>
                                </div>
                            </div>
                            <hr>
                            <small class="text-muted">
                                <strong>提示：</strong> 大部分现代邮箱服务推荐使用TLS加密和587端口。如果您不确定配置，请联系您的邮箱服务提供商。
                            </small>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="?step=2" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> 上一步
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-arrow-right"></i> 下一步
                        </button>
                    </div>
                </form>
                
                <?php elseif ($step == 4): // 管理员账号 ?>
                <h4><i class="bi bi-person-gear"></i> 创建管理员账号</h4>
                <p class="text-muted">创建第一个管理员账号</p>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">用户名 *</label>
                        <input type="text" class="form-control" name="admin_username" 
                               value="<?php echo htmlspecialchars($_POST['admin_username'] ?? ''); ?>" 
                               placeholder="管理员用户名" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">邮箱地址 *</label>
                        <input type="email" class="form-control" name="admin_email" 
                               value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>" 
                               placeholder="admin@example.com" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">密码 *</label>
                        <input type="password" class="form-control" name="admin_password" 
                               placeholder="至少6个字符" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">确认密码 *</label>
                        <input type="password" class="form-control" name="confirm_password" 
                               placeholder="再次输入密码" required>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="?step=3" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> 上一步
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-arrow-right"></i> 下一步
                        </button>
                    </div>
                </form>
                
                <?php elseif ($step == 5): // 安装确认 ?>
                <h4><i class="bi bi-gear"></i> 安装确认</h4>
                <p class="text-muted">请确认您的配置信息</p>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6><i class="bi bi-database"></i> 数据库配置</h6>
                            </div>
                            <div class="card-body">
                                <p><strong>主机:</strong> <?php echo htmlspecialchars($_SESSION['db_config']['host']); ?></p>
                                <p><strong>数据库:</strong> <?php echo htmlspecialchars($_SESSION['db_config']['name']); ?></p>
                                <p><strong>用户:</strong> <?php echo htmlspecialchars($_SESSION['db_config']['user']); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6><i class="bi bi-envelope"></i> 邮件配置</h6>
                            </div>
                            <div class="card-body">
                                <p><strong>类型:</strong> <?php 
                                    $type_names = [
                                        'carcall' => 'CarCall邮箱',
                                        'qq' => 'QQ邮箱',
                                        '163' => '163邮箱',
                                        'gmail' => 'Gmail邮箱',
                                        'custom' => '自定义邮箱'
                                    ];
                                    echo $type_names[$_SESSION['email_config']['type']] ?? strtoupper($_SESSION['email_config']['type']) . '邮箱';
                                ?></p>
                                <p><strong>邮箱:</strong> <?php echo htmlspecialchars($_SESSION['email_config']['user']); ?></p>
                                
                                <?php if ($_SESSION['email_config']['type'] === 'custom' && isset($_SESSION['email_config']['custom'])): ?>
                                <hr>
                                <h6 class="text-primary mb-2">自定义SMTP配置</h6>
                                <p class="mb-1"><strong>服务器:</strong> <?php echo htmlspecialchars($_SESSION['email_config']['custom']['host']); ?></p>
                                <p class="mb-1"><strong>端口:</strong> <?php echo (int)$_SESSION['email_config']['custom']['port']; ?></p>
                                <p class="mb-1"><strong>加密:</strong> <?php echo strtoupper($_SESSION['email_config']['custom']['encryption']); ?></p>
                                <p class="mb-1"><strong>发件人:</strong> <?php echo htmlspecialchars($_SESSION['email_config']['custom']['from_name']); ?></p>
                                <p class="mb-0"><strong>身份验证:</strong> <?php echo $_SESSION['email_config']['custom']['auth'] === 'on' ? '是' : '否'; ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h6><i class="bi bi-person-gear"></i> 管理员账号</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>用户名:</strong> <?php echo htmlspecialchars($_SESSION['admin_config']['username']); ?></p>
                        <p><strong>邮箱:</strong> <?php echo htmlspecialchars($_SESSION['admin_config']['email']); ?></p>
                    </div>
                </div>
                
                <div class="alert alert-warning mt-3">
                    <i class="bi bi-exclamation-triangle"></i> 
                    <strong>注意:</strong> 安装过程将会覆盖现有的配置文件，请确认无误后继续。
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-danger mt-3">
                    <h6><i class="bi bi-exclamation-triangle"></i> 安装错误</h6>
                    <p><?php echo htmlspecialchars($error); ?></p>
                    <div class="mt-3">
                        <a href="status.php" class="btn btn-info btn-sm" target="_blank">
                            <i class="bi bi-speedometer2"></i> 系统状态检查
                        </a>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <strong>常见解决方案：</strong><br>
                            • 检查文件权限（config和qrcodes目录需要可写权限）<br>
                            • 确保所有文件上传完整<br>
                            • 验证数据库用户权限<br>
                            • 查看服务器错误日志
                        </small>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="d-flex justify-content-between">
                        <a href="?step=4" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> 上一步
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-download"></i> 开始安装
                        </button>
                    </div>
                </form>
                
                <?php elseif ($step == 6): // 安装完成 ?>
                <div class="text-center">
                    <div class="mb-4">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h4 class="text-success">🎉 安装完成！</h4>
                    <p class="text-muted">车友帮讯联已成功安装并配置完成</p>
                    
                    <div class="row mt-4">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="bi bi-database-check text-success" style="font-size: 2rem;"></i>
                                    <h6 class="mt-2">数据库</h6>
                                    <small class="text-muted">已创建并初始化</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="bi bi-envelope-check text-success" style="font-size: 2rem;"></i>
                                    <h6 class="mt-2">邮件</h6>
                                    <small class="text-muted">已配置完成</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="bi bi-person-check text-success" style="font-size: 2rem;"></i>
                                    <h6 class="mt-2">管理员</h6>
                                    <small class="text-muted">账号已创建</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-success mt-4">
                        <h6><i class="bi bi-rocket"></i> 接下来您可以：</h6>
                        <ul class="mb-0 text-start">
                            <li>登录系统开始使用</li>
                            <li>测试邮件发送功能</li>
                            <li>生成您的专属二维码</li>
                            <li>开始接收车友留言</li>
                        </ul>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
                        <a href="/" class="btn btn-primary btn-lg">
                            <i class="bi bi-house"></i> 访问首页
                        </a>
                        <a href="/login" class="btn btn-outline-success">
                            <i class="bi bi-box-arrow-in-right"></i> 立即登录
                        </a>
                        <a href="/admin" class="btn btn-outline-info">
                            <i class="bi bi-gear"></i> 管理后台
                        </a>
                    </div>
                    
                    <div class="alert alert-info mt-4">
                        <h6><i class="bi bi-info-circle"></i> 重要提示</h6>
                        <p class="mb-2">为了系统安全，建议您：</p>
                        <ol class="mb-0">
                            <li>删除或重命名 install.php 文件</li>
                            <li>确保 config/ 目录的权限设置正确</li>
                            <li>定期备份数据库</li>
                        </ol>
                    </div>
                    
                    <div class="alert alert-light mt-3">
                        <small class="text-muted">
                            <i class="bi bi-clock"></i> 页面将在 <span id="countdown">8</span> 秒后自动跳转到首页，或点击上方按钮立即跳转
                        </small>
                    </div>
                </div>

                <script>
                // 自动跳转倒计时
                let countdown = 8;
                const countdownEl = document.getElementById('countdown');
                
                if (countdownEl) {
                    const timer = setInterval(() => {
                        countdown--;
                        countdownEl.textContent = countdown;
                        
                        if (countdown <= 0) {
                            clearInterval(timer);
                            window.location.href = '/';
                        }
                    }, 1000);
                    
                    // 防止用户快速离开页面时出现错误
                    window.addEventListener('beforeunload', () => {
                        clearInterval(timer);
                    });
                }
                </script>
                <?php endif; ?>
                
            </div>
        </div>
    </div>

<script>
function updateEmailPlaceholder() {
    const emailType = document.querySelector('select[name="email_type"]').value;
    const emailInput = document.getElementById('email-input');
    const passwordInput = document.getElementById('password-input');
    const passwordHelp = document.getElementById('password-help');
    const emailHelp = document.getElementById('email-help');
    const customConfig = document.getElementById('custom-email-config');
    const customExamples = document.getElementById('custom-email-examples');
    
    // 隐藏自定义配置区域和示例
    customConfig.style.display = 'none';
    customExamples.style.display = 'none';
    
    // 根据选择的邮箱类型设置自定义配置字段的required属性
    const customFields = customConfig.querySelectorAll('input[name^="custom_"], select[name^="custom_"]');
    customFields.forEach(field => {
        if (emailType === 'custom') {
            if (field.name === 'custom_smtp_host' || field.name === 'custom_smtp_port') {
                field.setAttribute('required', 'required');
            }
        } else {
            field.removeAttribute('required');
        }
    });
    
    switch(emailType) {
        case 'carcall':
            emailInput.placeholder = 'your@carcall.endl.site';
            passwordInput.placeholder = 'CarCall邮箱密码';
            passwordHelp.textContent = '请输入您的CarCall邮箱密码';
            emailHelp.innerHTML = `
                <strong>CarCall邮箱：</strong>
                <ol class="mb-0">
                    <li>使用您申请的CarCall邮箱账号</li>
                    <li>SMTP服务器：mail.carcall.endl.site</li>
                    <li>端口：587（安全连接）</li>
                    <li>请输入您的邮箱地址和密码</li>
                </ol>
            `;
            break;
        case 'qq':
            emailInput.placeholder = 'your@qq.com';
            passwordInput.placeholder = 'QQ邮箱授权码';
            passwordHelp.textContent = 'QQ邮箱请使用授权码，不是QQ密码';
            emailHelp.innerHTML = `
                <strong>QQ邮箱：</strong>
                <ol class="mb-0">
                    <li>登录QQ邮箱 → 设置 → 账户</li>
                    <li>开启"POP3/SMTP服务"</li>
                    <li>按提示发送短信获取授权码</li>
                </ol>
            `;
            break;
        case '163':
            emailInput.placeholder = 'your@163.com';
            passwordInput.placeholder = '客户端授权密码';
            passwordHelp.textContent = '163邮箱请使用客户端授权密码';
            emailHelp.innerHTML = `
                <strong>163邮箱：</strong>
                <ol class="mb-0">
                    <li>登录163邮箱 → 设置 → POP3/SMTP/IMAP</li>
                    <li>开启"POP3/SMTP服务"</li>
                    <li>设置客户端授权密码</li>
                </ol>
            `;
            break;
        case 'gmail':
            emailInput.placeholder = 'your@gmail.com';
            passwordInput.placeholder = '应用专用密码';
            passwordHelp.textContent = 'Gmail请使用应用专用密码，需开启两步验证';
            emailHelp.innerHTML = `
                <strong>Gmail邮箱：</strong>
                <ol class="mb-0">
                    <li>开启两步验证</li>
                    <li>生成应用专用密码</li>
                    <li>使用应用专用密码</li>
                </ol>
            `;
            break;
        case 'custom':
            emailInput.placeholder = 'your@yourserver.com';
            passwordInput.placeholder = '邮箱密码或授权码';
            passwordHelp.textContent = '请输入您的邮箱密码或第三方应用授权码';
            emailHelp.innerHTML = `
                <strong>自定义邮箱：</strong>
                <ol class="mb-0">
                    <li>请联系您的邮箱服务商获取SMTP配置信息</li>
                    <li>确保您的邮箱支持SMTP发送功能</li>
                    <li>某些邮箱可能需要开启"第三方应用授权"</li>
                    <li>如果连接失败，请检查防火墙设置</li>
                </ol>
            `;
            // 显示自定义配置区域和示例
            customConfig.style.display = 'block';
            customExamples.style.display = 'block';
            break;
        default:
            emailInput.placeholder = '请先选择邮箱类型';
            passwordInput.placeholder = '请先选择邮箱类型';
            passwordHelp.textContent = '';
            emailHelp.innerHTML = '';
            break;
    }
}

// 页面加载时初始化
document.addEventListener('DOMContentLoaded', function() {
    updateEmailPlaceholder();
});

// 快速填写邮箱配置
function fillEmailConfig(provider) {
    const configs = {
        'aliyun': {
            host: 'smtp.mxhichina.com',
            port: 465,
            encryption: 'ssl',
            name: '阿里云邮箱'
        },
        'tencent': {
            host: 'smtp.exmail.qq.com',
            port: 587,
            encryption: 'tls',
            name: '腾讯企业邮箱'
        },
        'outlook': {
            host: 'smtp-mail.outlook.com',
            port: 587,
            encryption: 'tls',
            name: '微软Outlook'
        },
        'yahoo': {
            host: 'smtp.mail.yahoo.com',
            port: 587,
            encryption: 'tls',
            name: 'Yahoo邮箱'
        }
    };
    
    if (configs[provider]) {
        const config = configs[provider];
        document.querySelector('input[name="custom_smtp_host"]').value = config.host;
        document.querySelector('input[name="custom_smtp_port"]').value = config.port;
        document.querySelector('select[name="custom_smtp_encryption"]').value = config.encryption;
        document.querySelector('input[name="custom_from_name"]').value = config.name;
        
        // 显示提示信息
        const hostInput = document.querySelector('input[name="custom_smtp_host"]');
        hostInput.focus();
        hostInput.style.backgroundColor = '#e8f5e8';
        setTimeout(() => {
            hostInput.style.backgroundColor = '';
        }, 2000);
    }
}
</script>

</body>
</html>
