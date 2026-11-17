<?php
// ============================================
// 邮件配置 - 请根据你使用的邮箱进行配置
// ============================================

// 🔧 配置说明：
// 1. 选择你要使用的邮箱类型，取消对应的注释
// 2. 填入你的真实邮箱和密码/授权码
// 3. 保存文件即可

// 🚗 CarCall专用邮箱配置（推荐，系统专用）
// 请填写您的CarCall邮箱信息
define('SMTP_HOST', 'mail.carcall.endl.site');   // CarCall邮箱SMTP服务器
define('SMTP_PORT', 587);                         // SMTP端口
define('SMTP_USERNAME', 'your@carcall.endl.site'); // 🔴 请填写您的CarCall邮箱地址
define('SMTP_PASSWORD', 'your_carcall_password'); // 🔴 请填写您的CarCall邮箱密码
define('SMTP_FROM_NAME', '车友帮讯联');          // 发件人显示名称（邮件中显示的名称）

// 📮 QQ邮箱配置（备选方案1）
// 步骤：QQ邮箱 → 设置 → 账户 → 开启POP3/SMTP服务 → 获取授权码
/*
define('SMTP_HOST', 'smtp.qq.com');              // QQ邮箱SMTP服务器
define('SMTP_PORT', 587);                        // SMTP端口
define('SMTP_USERNAME', 'your_qq@qq.com');       // 🔴 改成你的QQ邮箱
define('SMTP_PASSWORD', 'your_authorization_code'); // 🔴 改成QQ邮箱授权码（不是QQ密码！）
define('SMTP_FROM_NAME', '车友帮讯联');          // 发件人名称（可自定义）
*/

// 📨 163邮箱配置（备选方案2）
// 步骤：163邮箱 → 设置 → POP3/SMTP/IMAP → 开启POP3/SMTP服务 → 设置客户端授权密码
/*
define('SMTP_HOST', 'smtp.163.com');
define('SMTP_PORT', 25);
define('SMTP_USERNAME', 'your_name@163.com');    // 🔴 改成你的163邮箱
define('SMTP_PASSWORD', 'your_client_password');  // 🔴 改成客户端授权密码
define('SMTP_FROM_NAME', '车友帮讯联');
*/

// 📧 Gmail邮箱配置（备选方案3，需要翻墙）
// 步骤：开启两步验证 → 生成应用专用密码
/*
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your_email@gmail.com'); // 🔴 改成你的Gmail邮箱
define('SMTP_PASSWORD', 'your_app_password');    // 🔴 改成应用专用密码
define('SMTP_FROM_NAME', '车友帮讯联');
*/

// ============================================
// 邮件发送函数 - SMTP发送
// ============================================

function sendEmail($to, $subject, $message) {
    // 检查是否配置了邮件
    if (!isEmailConfigured()) {
        error_log("邮件发送失败：邮件未配置");
        return false;
    }
    
    try {
        // 优化：减少超时时间到10秒，提高响应速度
        $smtp = fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 10);
        if (!$smtp) {
            error_log("SMTP连接失败：$errstr ($errno)");
            return false;
        }
        
        // 设置流超时为5秒，避免长时间等待
        stream_set_timeout($smtp, 5);
        
        // 读取服务器响应
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '220') {
            error_log("SMTP初始响应错误：$response");
            fclose($smtp);
            return false;
        }
        
        // EHLO
        fputs($smtp, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
        $response = fgets($smtp, 515);
        
        // STARTTLS
        if (SMTP_PORT == 587 || SMTP_PORT == 25) {
            fputs($smtp, "STARTTLS\r\n");
            $response = fgets($smtp, 515);
            if (substr($response, 0, 3) != '220') {
                error_log("STARTTLS失败：$response");
                fclose($smtp);
                return false;
            }
            
            // 启用加密
            if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log("TLS加密启用失败");
                fclose($smtp);
                return false;
            }
            
            // 重新EHLO
            fputs($smtp, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
            $response = fgets($smtp, 515);
        }
        
        // 身份验证
        fputs($smtp, "AUTH LOGIN\r\n");
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '334') {
            error_log("AUTH LOGIN失败：$response");
            fclose($smtp);
            return false;
        }
        
        // 发送用户名
        fputs($smtp, base64_encode(SMTP_USERNAME) . "\r\n");
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '334') {
            error_log("用户名验证失败：$response");
            fclose($smtp);
            return false;
        }
        
        // 发送密码
        fputs($smtp, base64_encode(SMTP_PASSWORD) . "\r\n");
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '235') {
            error_log("密码验证失败：$response");
            fclose($smtp);
            return false;
        }
        
        // MAIL FROM
        fputs($smtp, "MAIL FROM: <" . SMTP_USERNAME . ">\r\n");
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '250') {
            error_log("MAIL FROM失败：$response");
            fclose($smtp);
            return false;
        }
        
        // RCPT TO
        fputs($smtp, "RCPT TO: <$to>\r\n");
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '250') {
            error_log("RCPT TO失败：$response");
            fclose($smtp);
            return false;
        }
        
        // DATA
        fputs($smtp, "DATA\r\n");
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '354') {
            error_log("DATA命令失败：$response");
            fclose($smtp);
            return false;
        }
        
        // 构建邮件头和内容
        $headers = "";
        $headers .= "From: " . mb_encode_mimeheader(SMTP_FROM_NAME, 'UTF-8') . " <" . SMTP_USERNAME . ">\r\n";
        $headers .= "Reply-To: " . SMTP_USERNAME . "\r\n";
        $headers .= "To: " . $to . "\r\n";
        $headers .= "Subject: " . mb_encode_mimeheader($subject, 'UTF-8') . "\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "Message-ID: <" . md5(time() . rand() . $to) . "@" . $_SERVER['HTTP_HOST'] . ">\r\n";
        $headers .= "X-Mailer: CarFriend-System\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: quoted-printable\r\n";
        $headers .= "\r\n";
        
        $email_content = $headers . quoted_printable_encode($message) . "\r\n.\r\n";
        
        // 发送邮件内容
        fputs($smtp, $email_content);
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '250') {
            error_log("邮件发送失败：$response");
            fclose($smtp);
            return false;
        }
        
        // QUIT
        fputs($smtp, "QUIT\r\n");
        fclose($smtp);
        
        // 记录成功日志
        error_log("邮件发送成功：TO={$to}, SUBJECT={$subject}");
        return true;
        
    } catch (Exception $e) {
        error_log("邮件发送异常：" . $e->getMessage());
        return false;
    }
}

// 备用简单邮件发送函数（使用系统mail函数）
function sendEmailSimple($to, $subject, $message) {
    $headers = array(
        'From' => mb_encode_mimeheader(SMTP_FROM_NAME, 'UTF-8') . ' <' . SMTP_USERNAME . '>',
        'Reply-To' => SMTP_USERNAME,
        'Content-Type' => 'text/html; charset=UTF-8',
        'X-Mailer' => 'PHP/' . phpversion()
    );
    
    $header_string = '';
    foreach ($headers as $key => $value) {
        $header_string .= $key . ': ' . $value . "\r\n";
    }
    
    return mail($to, $subject, $message, $header_string);
}

// 发送留言通知邮件
function sendMessageNotification($userEmail, $username, $messageContent, $senderContact = '') {
    if (!isEmailConfigured()) {
        error_log("邮件通知发送失败：邮件未配置");
        return false;
    }
    
    $subject = "【车友帮讯联】您收到了一条新留言";
    
    // 生成简化的HTML邮件内容
    $htmlMessage = "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>新留言通知</title>
    <style>
        body { 
            margin: 0; 
            padding: 0; 
            font-family: Arial, sans-serif; 
            line-height: 1.6; 
            color: #333333; 
            background-color: #f4f4f4; 
        }
        .email-container { 
            max-width: 600px; 
            margin: 0 auto; 
            background-color: #ffffff; 
            border-radius: 8px; 
            overflow: hidden; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
        }
        .header { 
            background: #007bff; 
            color: #ffffff; 
            padding: 30px 20px; 
            text-align: center; 
        }
        .header h1 { 
            margin: 0; 
            font-size: 24px; 
            font-weight: 600; 
        }
        .content { 
            padding: 30px 20px; 
        }
        .message-card { 
            background-color: #f8f9fa; 
            border: 1px solid #e9ecef; 
            border-left: 4px solid #007bff; 
            border-radius: 4px; 
            padding: 20px; 
            margin: 20px 0; 
        }
        .contact-info { 
            background-color: #e3f2fd; 
            border: 1px solid #bbdefb; 
            border-radius: 4px; 
            padding: 15px; 
            margin: 15px 0; 
            font-size: 14px; 
        }
        .action-button { 
            display: inline-block; 
            background-color: #007bff; 
            color: #ffffff; 
            text-decoration: none; 
            padding: 12px 24px; 
            border-radius: 4px; 
            font-weight: 600; 
            margin: 20px 0; 
        }
        .footer { 
            background-color: #6c757d; 
            color: #ffffff; 
            padding: 20px; 
            text-align: center; 
            font-size: 12px; 
        }
    </style>
</head>
<body>
    <div class='email-container'>
        <div class='header'>
            <h1>🚗 车友帮讯联</h1>
            <p>您收到了一条新留言！</p>
        </div>
        
        <div class='content'>
            <div>亲爱的 <strong>" . htmlspecialchars($username) . "</strong>，</div>
            
            <p>有车友给您留言了！</p>
            
            <div class='message-card'>
                <div><strong>📝 留言内容</strong></div>
                <div>" . nl2br(htmlspecialchars($messageContent)) . "</div>
            </div>";
            
    if ($senderContact) {
        $htmlMessage .= "
            <div class='contact-info'>
                <strong>📞 留言者联系方式：</strong><br>
                " . htmlspecialchars($senderContact) . "
            </div>";
    }
    
    $viewUrl = (defined('SITE_URL') ? SITE_URL : 'http://carcall.endl.site') . '/view_messages';
    
    $htmlMessage .= "
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$viewUrl}' class='action-button'>
                    👀 立即查看留言
                </a>
            </div>
            
            <p style='font-size: 14px; color: #6c757d;'>
                请及时查看并回复留言，保持良好的沟通体验。
            </p>
        </div>
        
        <div class='footer'>
            <p>此邮件由车友帮讯联系统自动发送，请勿直接回复此邮件。</p>
            <p>© " . date('Y') . " 车友帮讯联</p>
        </div>
    </div>
</body>
</html>";

    // 使用简化的邮件发送函数
    $result = sendEmail($userEmail, $subject, $htmlMessage);
    
    if (!$result) {
        error_log("留言通知邮件发送失败 - 收件人: $userEmail, 用户: $username");
        // 尝试备用方法
        $result = sendEmailSimple($userEmail, $subject, strip_tags($htmlMessage));
        if ($result) {
            error_log("留言通知邮件通过备用方法发送成功");
        }
    } else {
        error_log("留言通知邮件发送成功 - 收件人: $userEmail");
    }
    
    return $result;
}

// ============================================
// 配置验证 - 无需修改
// ============================================

// 检查邮件配置是否完整
function isEmailConfigured() {
    // 检查必要的常量是否定义
    if (!defined('SMTP_HOST') || !defined('SMTP_USERNAME') || !defined('SMTP_PASSWORD') || !defined('SMTP_PORT')) {
        return false;
    }
    
    // 检查是否是默认占位符
    $defaultValues = [
        'your@carcall.endl.site',
        'your_qq@qq.com', 
        'your_name@163.com',
        'your_email@gmail.com',
        'your_carcall_password',
        'your_authorization_code',
        'your_client_password',
        'your_app_password'
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

// 快速邮件发送 - 异步方式（推荐）
function sendEmailFast($to, $subject, $message) {
    if (!isEmailConfigured()) {
        error_log("邮件发送失败：邮件未配置");
        return false;
    }
    
    // 将邮件信息保存到临时文件，由后台脚本异步处理
    $emailData = [
        'to' => $to,
        'subject' => $subject,
        'message' => $message,
        'timestamp' => time(),
        'attempts' => 0
    ];
    
    $emailFile = __DIR__ . '/../cache/email_' . md5($to . $subject . time()) . '.json';
    
    // 确保cache目录存在
    $cacheDir = dirname($emailFile);
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    if (file_put_contents($emailFile, json_encode($emailData))) {
        // 触发后台处理（非阻塞方式）
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request(); // FastCGI环境下立即返回响应
        }
        
        // 尝试异步执行邮件发送
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows环境
            pclose(popen('start /B php "' . __DIR__ . '/../process_emails.php"', 'r'));
        } else {
            // Linux/Unix环境
            exec('php "' . __DIR__ . '/../process_emails.php" > /dev/null 2>&1 &');
        }
        
        return true;
    }
    
    return false;
}

// 备用：立即发送邮件（简化版）
function sendEmailQuick($to, $subject, $message) {
    if (!isEmailConfigured()) {
        return false;
    }
    
    try {
        // 使用更短的超时时间
        $smtp = fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 5);
        if (!$smtp) {
            return false;
        }
        
        stream_set_timeout($smtp, 3); // 3秒超时
        
        // 简化的SMTP通信（减少步骤）
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '220') {
            fclose($smtp);
            return false;
        }
        
        // EHLO
        fputs($smtp, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
        fgets($smtp, 515);
        
        // 跳过STARTTLS以加速（仅在安全环境下）
        if (SMTP_PORT == 587) {
            fputs($smtp, "STARTTLS\r\n");
            $response = fgets($smtp, 515);
            if (substr($response, 0, 3) == '220') {
                stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                fputs($smtp, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
                fgets($smtp, 515);
            }
        }
        
        // 快速认证
        fputs($smtp, "AUTH LOGIN\r\n");
        fgets($smtp, 515);
        fputs($smtp, base64_encode(SMTP_USERNAME) . "\r\n");
        fgets($smtp, 515);
        fputs($smtp, base64_encode(SMTP_PASSWORD) . "\r\n");
        $response = fgets($smtp, 515);
        
        if (substr($response, 0, 3) != '235') {
            fclose($smtp);
            return false;
        }
        
        // 发送邮件
        fputs($smtp, "MAIL FROM: <" . SMTP_USERNAME . ">\r\n");
        fgets($smtp, 515);
        fputs($smtp, "RCPT TO: <$to>\r\n");
        fgets($smtp, 515);
        fputs($smtp, "DATA\r\n");
        fgets($smtp, 515);
        
        // 简化的邮件头
        $email_content = "From: " . SMTP_FROM_NAME . " <" . SMTP_USERNAME . ">\r\n";
        $email_content .= "To: $to\r\n";
        $email_content .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $email_content .= "Content-Type: text/html; charset=UTF-8\r\n";
        $email_content .= "\r\n";
        $email_content .= $message . "\r\n.\r\n";
        
        fputs($smtp, $email_content);
        fgets($smtp, 515);
        fputs($smtp, "QUIT\r\n");
        fclose($smtp);
        
        return true;
        
    } catch (Exception $e) {
        return false;
    }
}

// 测试邮件配置连接
function testEmailConnection() {
    if (!isEmailConfigured()) {
        return ['success' => false, 'message' => '邮件配置不完整'];
    }
    
    try {
        $smtp = fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 10);
        if (!$smtp) {
            return ['success' => false, 'message' => "无法连接到SMTP服务器：$errstr"];
        }
        
        $response = fgets($smtp, 515);
        fclose($smtp);
        
        if (substr($response, 0, 3) == '220') {
            return ['success' => true, 'message' => 'SMTP服务器连接正常'];
        } else {
            return ['success' => false, 'message' => "SMTP服务器响应异常：$response"];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => '连接测试异常：' . $e->getMessage()];
    }
}

// ===============================================
// 邮件队列和异步发送功能
// ===============================================

// 邮件队列目录
if (!defined('EMAIL_QUEUE_DIR')) {
    define('EMAIL_QUEUE_DIR', __DIR__ . '/../cache/email_queue');
}

// 确保邮件队列目录存在
if (!file_exists(EMAIL_QUEUE_DIR)) {
    @mkdir(EMAIL_QUEUE_DIR, 0755, true);
}

/**
 * 将邮件添加到发送队列（异步发送）
 * @param string $to 收件人邮箱
 * @param string $subject 邮件主题
 * @param string $message 邮件内容
 * @param string $priority 优先级 (high, normal, low)
 * @return bool 是否成功加入队列
 */
function queueEmail($to, $subject, $message, $priority = 'normal') {
    if (!isEmailConfigured()) {
        return false;
    }
    
    $emailData = [
        'id' => uniqid('email_', true),
        'to' => $to,
        'subject' => $subject,
        'message' => $message,
        'priority' => $priority,
        'created_at' => time(),
        'attempts' => 0,
        'status' => 'pending'
    ];
    
    $filename = EMAIL_QUEUE_DIR . '/' . $emailData['id'] . '.json';
    $result = file_put_contents($filename, json_encode($emailData, JSON_UNESCAPED_UNICODE));
    
    // 立即尝试发送（非阻塞）
    if ($result && function_exists('exec')) {
        $scriptPath = __DIR__ . '/../includes/process_emails.php';
        if (file_exists($scriptPath)) {
            // Windows 兼容的后台执行
            if (PHP_OS_FAMILY === 'Windows') {
                $cmd = "start /B php \"$scriptPath\" > NUL 2>&1";
            } else {
                $cmd = "php \"$scriptPath\" > /dev/null 2>&1 &";
            }
            @exec($cmd);
        }
    }
    
    return $result !== false;
}

/**
 * 创建消息通知邮件内容
 * @param string $username 发送者用户名
 * @param string $message 留言内容
 * @param string $senderContact 发送者联系方式
 * @return string HTML邮件内容
 */
function createMessageNotificationEmail($username, $message, $senderContact = '') {
    $subject = "您收到新留言 - 车友帮讯联";
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($subject) . '</title>
    <style>
        body { font-family: "Microsoft YaHei", Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
        .content { padding: 30px; }
        .message-box { background: #f8f9ff; border-left: 4px solid #667eea; padding: 15px; margin: 20px 0; border-radius: 0 4px 4px 0; }
        .footer { background: #f8f9fa; padding: 15px; text-align: center; color: #666; border-radius: 0 0 8px 8px; font-size: 12px; }
        .btn { display: inline-block; background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚗 车友帮讯联</h1>
            <p>您收到一条新留言</p>
        </div>
        <div class="content">
            <p>尊敬的用户，您好！</p>
            <p><strong>' . htmlspecialchars($username) . '</strong> 给您留了一条消息：</p>
            
            <div class="message-box">
                <strong>留言内容：</strong><br>
                ' . nl2br(htmlspecialchars($message)) . '
            </div>';
    
    if (!empty($senderContact)) {
        $html .= '<p><strong>联系方式：</strong> ' . htmlspecialchars($senderContact) . '</p>';
    }
    
    $html .= '
            <p>请及时查看并回复。</p>
            <div style="text-align: center; margin: 20px 0;">
                <a href="' . (defined('SITE_URL') ? SITE_URL : 'http://localhost') . '/pages/view_messages.php" class="btn">查看留言</a>
            </div>
        </div>
        <div class="footer">
            <p>此邮件由车友帮讯联系统自动发送，请勿直接回复</p>
            <p>如有问题请联系系统管理员</p>
        </div>
    </div>
</body>
</html>';

    return $html;
}

/**
 * 获取队列中待发送邮件数量
 * @return int 待发送邮件数量
 */
function getQueuedEmailCount() {
    if (!file_exists(EMAIL_QUEUE_DIR)) {
        return 0;
    }
    
    $files = glob(EMAIL_QUEUE_DIR . '/*.json');
    return count($files);
}

/**
 * 清理过期的邮件队列文件（超过24小时）
 * @return int 清理的文件数量
 */
function cleanupEmailQueue() {
    if (!file_exists(EMAIL_QUEUE_DIR)) {
        return 0;
    }
    
    $files = glob(EMAIL_QUEUE_DIR . '/*.json');
    $cleaned = 0;
    $expire_time = time() - (24 * 60 * 60); // 24小时前
    
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data && isset($data['created_at']) && $data['created_at'] < $expire_time) {
            unlink($file);
            $cleaned++;
        }
    }
    
    return $cleaned;
}
?>
