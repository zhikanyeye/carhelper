<?php
// 安全启动session，避免重复启动
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查数据库配置文件是否存在
if (file_exists(__DIR__ . '/../config/database.php')) {
    require_once __DIR__ . '/../config/database.php';
}

// 确保邮件配置可用（智能加载）
$email_config_path = __DIR__ . '/../config/email.php';
if (file_exists($email_config_path)) {
    // 首先检查是否已经定义了基本常量
    if (!defined('SMTP_HOST')) {
        require_once $email_config_path;
    }
    // 如果常量已定义但函数未定义，重新加载
    elseif (defined('SMTP_HOST') && !function_exists('isEmailConfigured')) {
        require_once $email_config_path;
    }
}

// URL生成函数，自动处理子目录并支持中文路径
function url($path = '') {
    // 定义英文到中文的路由映射
    $english_to_chinese = [
        'home' => '首页',
        'login' => '登录',
        'register' => '注册', 
        'dashboard' => '个人面板',
        'view_messages' => '查看留言',
        'leave_message' => '留言板',
        'verify_email' => '邮箱验证',
        'resend_verification' => '重发验证',
        'forgot_password' => '忘记密码',
        'reset_password' => '重置密码',
        'admin' => '管理员后台',
        'status' => '系统诊断',
        'logout' => '退出'
    ];
    
    // 解析路径和查询参数
    $parts = explode('?', $path);
    $route_path = $parts[0];
    $query_string = isset($parts[1]) ? '?' . $parts[1] : '';
    
    // 转换英文路由为中文（如果匹配）
    if (isset($english_to_chinese[$route_path])) {
        $route_path = $english_to_chinese[$route_path];
    }
    
    // 重新组合路径
    $path = $route_path . $query_string;
    
    // 如果配置文件已加载
    if (defined('SITE_URL')) {
        return SITE_URL . '/' . ltrim($path, '/');
    }
    
    // 如果配置未加载，手动检测
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    
    // 自动检测子目录
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $dir_name = dirname($script_name);
    $base_path = ($dir_name === '/' || $dir_name === '\\') ? '' : $dir_name;
    
    return $protocol . $host . $base_path . '/' . ltrim($path, '/');
}

// Session安全管理函数
function ensure_session_started() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// 安全重启session
function safe_session_restart() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// 数据库连接检查函数
function ensure_database_connection() {
    global $pdo;
    
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Database connection not available");
        }
        
        // 检查是否已安装
        $installed_lock = __DIR__ . '/../config/installed.lock';
        $database_config = __DIR__ . '/../config/database.php';
        
        if (!file_exists($installed_lock) || !file_exists($database_config)) {
            // 系统未安装，重定向到安装页面
            if (!headers_sent()) {
                header('Location: ' . (defined('SITE_URL') ? SITE_URL : '') . '/install.php');
                exit;
            } else {
                die('<script>window.location.href="install.php";</script>');
            }
        } else {
            // 系统已安装但数据库连接失败
            die("
                <div style='text-align:center; padding:50px; font-family:Arial,sans-serif;'>
                    <h2>数据库连接失败</h2>
                    <p>系统无法连接到数据库，请检查配置。</p>
                    <p><a href='install.php'>重新运行安装程序</a> | <a href='status.php'>检查系统状态</a></p>
                </div>
            ");
        }
    }
}

// 安全函数
function sanitize_input($data) {
    // 处理null和空值
    if ($data === null || $data === '') {
        return '';
    }
    return htmlspecialchars(strip_tags(trim($data)));
}

// 生成UUID v4
function generate_uuid() {
    // 优先使用PHP的uuid扩展
    if (extension_loaded('uuid')) {
        // 检查uuid_create函数是否存在
        if (function_exists('uuid_create')) {
            // UUID扩展方式1：如果有UUID_TYPE_RANDOM常量
            if (defined('UUID_TYPE_RANDOM')) {
                return call_user_func('uuid_create', constant('UUID_TYPE_RANDOM'));
            }
            // UUID扩展方式2：使用数字常量 (UUID v4)
            return call_user_func('uuid_create', 4);
        }
    }
    
    // 备用方案1：使用random_bytes（PHP 7.0+）
    if (function_exists('random_bytes')) {
        try {
            $data = random_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        } catch (Exception $e) {
            // 如果random_bytes失败，使用备用方案
        }
    }
    
    // 备用方案2：使用mt_rand
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// 根据UUID获取用户信息
function get_user_by_uuid($uuid) {
    global $pdo;
    
    try {
        ensure_database_connection();
        
        // 首先尝试使用UUID字段查询
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE uuid = ?");
            $stmt->execute([$uuid]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user !== false && is_array($user)) {
                return $user;
            }
        } catch (PDOException $e) {
            // 记录UUID查询错误
            error_log("get_user_by_uuid() database error: " . $e->getMessage());
        }
        
        // 如果没有找到用户，返回null
        return null;
    } catch (Exception $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("get_user_by_uuid() database error: " . $e->getMessage());
        }
        return null;
    }
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// 检查用户是否登录
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// 获取当前用户信息
if (!function_exists('get_current_system_user')) {
    function get_current_system_user() {
        global $pdo;
        if (!is_logged_in()) {
            return null;
        }
        
        try {
            // 确保数据库连接可用
            if (!isset($pdo) || !($pdo instanceof PDO)) {
                ensure_database_connection();
            }
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 验证返回的数据格式
            if ($user === false || !is_array($user)) {
                // 数据库中没有找到用户或数据格式错误
                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    error_log("get_current_system_user(): User not found or invalid data format for user_id: " . $_SESSION['user_id']);
                }
                return null;
            }
            
            return $user;
        } catch (Exception $e) {
            // 数据库查询异常
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("get_current_system_user() database error: " . $e->getMessage());
            }
            return null;
        }
    }
}

// 安全策略：不重新定义可能冲突的函数
// 使用 get_current_system_user() 作为主要函数  
// 避免与系统或框架的 get_current_user() 函数冲突

// 兼容性函数：安全获取当前用户
if (!function_exists('get_current_user_safe')) {
    function get_current_user_safe() {
        return get_current_system_user();
    }
}

// 生成二维码
function generate_qr_code($user_identifier) {
    global $pdo;
    
    // 如果传入的是数字ID，尝试获取UUID；否则直接使用传入的值
    if (is_numeric($user_identifier)) {
        try {
            $stmt = $pdo->prepare("SELECT uuid FROM users WHERE id = ?");
            $stmt->execute([$user_identifier]);
            $user_uuid = $stmt->fetchColumn();
            
            if ($user_uuid) {
                $user_identifier = $user_uuid;
            } else {
                // 如果没有UUID，生成一个并尝试更新
                $user_uuid = generate_uuid();
                try {
                    $update_stmt = $pdo->prepare("UPDATE users SET uuid = ? WHERE id = ?");
                    $update_stmt->execute([$user_uuid, $user_identifier]);
                    $user_identifier = $user_uuid;
                } catch (PDOException $e) {
                    // 如果uuid字段不存在，尝试添加字段
                    if (strpos($e->getMessage(), 'Unknown column') !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
                        try {
                            // 尝试添加uuid字段
                            $pdo->exec("ALTER TABLE users ADD COLUMN uuid VARCHAR(36) UNIQUE");
                            
                            // 重试更新UUID
                            $update_stmt = $pdo->prepare("UPDATE users SET uuid = ? WHERE id = ?");
                            $update_stmt->execute([$user_uuid, $user_identifier]);
                            $user_identifier = $user_uuid;
                        } catch (PDOException $alter_e) {
                            // 如果添加字段也失败，使用临时UUID继续
                            error_log("Failed to add UUID column in generate_qr_code: " . $alter_e->getMessage());
                            $user_identifier = $user_uuid; // 使用生成的UUID继续
                        }
                    } else {
                        // 其他数据库错误，使用临时UUID继续
                        error_log("UUID update failed in generate_qr_code: " . $e->getMessage());
                        $user_identifier = $user_uuid; // 使用生成的UUID继续
                    }
                }
            }
        } catch (PDOException $e) {
            // 如果查询失败，生成一个临时UUID继续
            error_log("UUID query failed in generate_qr_code: " . $e->getMessage());
            $user_identifier = generate_uuid();
        }
    }
    
    // 验证是否有有效的UUID（不再拒绝数字）
    if (!$user_identifier) {
        error_log("Invalid user identifier: " . $user_identifier);
        return false;
    }
    
    // 使用UUID生成二维码URL，提高安全性
    $site_url = defined('SITE_URL') ? SITE_URL : 'https://' . $_SERVER['HTTP_HOST'];
    $qr_data = $site_url . "/leave_message?uuid=" . $user_identifier;
    $qr_filename = "qr_" . $user_identifier . "_" . time() . ".png";
    
    // 创建qrcodes目录
    $qrcodes_dir = __DIR__ . '/../qrcodes';
    if (!file_exists($qrcodes_dir)) {
        if (!mkdir($qrcodes_dir, 0755, true)) {
            error_log("Failed to create qrcodes directory: " . $qrcodes_dir);
            return false;
        }
    }
    
    // 检查目录是否可写
    if (!is_writable($qrcodes_dir)) {
        error_log("qrcodes directory is not writable: " . $qrcodes_dir);
        return false;
    }
    
    // 尝试多个二维码服务（按优先级排序）
    $qr_services = [
        // Google Charts API - 使用国内可访问的新域名
        'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . urlencode($qr_data),
        // 备用：国内优化版本
        'https://chart.gstatic.com/chart?chs=200x200&cht=qr&chl=' . urlencode($qr_data),
        // 国际二维码服务
        'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($qr_data),
        // 其他备用服务
        'https://qr-generator.qrcode.studio/qr/custom?download=true&file=png&data=' . urlencode($qr_data) . '&size=200',
    ];
    
    $qr_content = false;
    $successful_service = null;
    
    foreach ($qr_services as $service_url) {
        // 使用cURL下载二维码（更可靠，支持超时设置）
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $service_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15); // 15秒超时
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10秒连接超时
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; CarFriend QR Generator)');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $qr_content = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($qr_content !== false && $http_code === 200 && strlen($qr_content) > 100) {
                $successful_service = $service_url;
                break;
            } else {
                error_log("QR service failed: $service_url, HTTP Code: $http_code, Error: $curl_error");
                $qr_content = false;
            }
        } else {
            // 回退到file_get_contents（带超时设置）
            $context = stream_context_create([
                'http' => [
                    'timeout' => 15,
                    'user_agent' => 'Mozilla/5.0 (compatible; CarFriend QR Generator)'
                ]
            ]);
            $qr_content = @file_get_contents($service_url, false, $context);
            
            if ($qr_content !== false && strlen($qr_content) > 100) {
                $successful_service = $service_url;
                break;
            } else {
                error_log("QR service failed (file_get_contents): $service_url");
                $qr_content = false;
            }
        }
    }
    
    // 如果所有在线服务都失败，生成本地SVG二维码
    if ($qr_content === false) {
        error_log("All QR services failed, trying local SVG generation");
        return generate_qr_code_local_svg($user_uuid, $qr_data);
    }
    
    // 保存二维码文件
    if ($qr_content !== false && strlen($qr_content) > 0) {
        $file_path = $qrcodes_dir . '/' . $qr_filename;
        if (file_put_contents($file_path, $qr_content) !== false) {
            // 验证生成的文件
            if (file_exists($file_path) && filesize($file_path) > 0) {
                error_log("QR code generated successfully using: " . $successful_service);
                return $qr_filename;
            } else {
                error_log("QR code file was not created properly: " . $file_path);
                return false;
            }
        } else {
            error_log("Failed to save QR code to file: " . $file_path);
            return false;
        }
    }
    
    error_log("Failed to generate QR code for user: " . $user_identifier);
    return false;
}

// 本地SVG二维码生成（备用方案）
function generate_qr_code_local_svg($user_uuid, $qr_data) {
    $qr_filename = "qr_local_" . $user_uuid . "_" . time() . ".svg";
    $qrcodes_dir = __DIR__ . '/../qrcodes';
    
    // 简化的SVG二维码（用于紧急情况）
    $svg_content = '<?xml version="1.0" encoding="UTF-8"?>
<svg width="200" height="200" xmlns="http://www.w3.org/2000/svg">
    <rect width="200" height="200" fill="white"/>
    <text x="100" y="100" text-anchor="middle" dominant-baseline="middle" font-family="Arial" font-size="12" fill="black">
        <tspan x="100" dy="-20">扫码访问:</tspan>
        <tspan x="100" dy="15">' . htmlspecialchars(substr($qr_data, 0, 30)) . '</tspan>
        <tspan x="100" dy="15">' . htmlspecialchars(substr($qr_data, 30)) . '</tspan>
    </text>
    <rect x="10" y="10" width="30" height="30" fill="black"/>
    <rect x="160" y="10" width="30" height="30" fill="black"/>
    <rect x="10" y="160" width="30" height="30" fill="black"/>
    <rect x="85" y="85" width="30" height="30" fill="black"/>
</svg>';
    
    $file_path = $qrcodes_dir . '/' . $qr_filename;
    if (file_put_contents($file_path, $svg_content)) {
        error_log("Local SVG QR code generated: " . $qr_filename);
        return $qr_filename;
    }
    
    return false;
}

// 获取用户留言数量
function get_user_message_count($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// 获取用户未读留言数量
function get_unread_message_count($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// 检查用户二维码是否真实存在
function user_qr_code_exists($user_id) {
    global $pdo;
    
    try {
        // 获取用户的UUID和二维码文件名
        $stmt = $pdo->prepare("SELECT qr_code, uuid FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || empty($user['uuid'])) {
            return false;
        }
        
        // 优先检查基于UUID的文件（新系统）
        $qr_filename = $user['uuid'] . '.svg';
        $qr_path = __DIR__ . '/../qrcodes/' . $qr_filename;
        if (file_exists($qr_path)) {
            return true;
        }
        
        // 如果UUID文件不存在，但数据库中有二维码记录，检查那个文件
        if (!empty($user['qr_code'])) {
            $qr_path = __DIR__ . '/../qrcodes/' . $user['qr_code'];
            return file_exists($qr_path);
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Error checking QR code existence: " . $e->getMessage());
        return false;
    }
}

// 格式化时间
function time_ago($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return '刚刚';
    if ($time < 3600) return floor($time/60) . '分钟前';
    if ($time < 86400) return floor($time/3600) . '小时前';
    if ($time < 2592000) return floor($time/86400) . '天前';
    if ($time < 31536000) return floor($time/2592000) . '个月前';
    return floor($time/31536000) . '年前';
}

// 检查用户是否为管理员
function is_admin() {
    global $pdo;
    if (!is_logged_in()) {
        return false;
    }
    
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetchColumn();
    return $result == 1;
}

// 获取管理员统计数据
function get_admin_stats() {
    global $pdo;
    
    $stats = [];
    
    // 总用户数
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $stats['total_users'] = $stmt->fetchColumn();
    
    // 总留言数
    $stmt = $pdo->query("SELECT COUNT(*) FROM messages");
    $stats['total_messages'] = $stmt->fetchColumn();
    
    // 未读留言数
    $stmt = $pdo->query("SELECT COUNT(*) FROM messages WHERE is_read = 0");
    $stats['unread_messages'] = $stmt->fetchColumn();
    
    // 今日留言数
    $stmt = $pdo->query("SELECT COUNT(*) FROM messages WHERE DATE(created_at) = CURDATE()");
    $stats['today_messages'] = $stmt->fetchColumn();
    
    // 本周新用户
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['week_users'] = $stmt->fetchColumn();
    
    // 本月新用户
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['month_users'] = $stmt->fetchColumn();
    
    // 活跃用户（最近30天有留言的用户）
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['active_users'] = $stmt->fetchColumn();
    
    // 本周留言数
    $stmt = $pdo->query("SELECT COUNT(*) FROM messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['week_messages'] = $stmt->fetchColumn();
    
    // 本月留言数
    $stmt = $pdo->query("SELECT COUNT(*) FROM messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['month_messages'] = $stmt->fetchColumn();
    
    return $stats;
}

// 清理旧数据
function cleanup_old_data($days) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // 清理已读的旧留言
        $stmt = $pdo->prepare("DELETE FROM messages WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$days]);
        $cleaned_messages = $stmt->rowCount();
        
        $pdo->commit();
        
        // 清理各种过期和临时文件（不需要事务）
        $cleaned_files = 0;
        $cleanup_results = [];
        
        try {
            $cleaned_files += cleanup_old_qrcode_files($days);
            $cleanup_results[] = "QR码文件清理完成";
        } catch (Exception $e) {
            $cleanup_results[] = "QR码文件清理失败: " . $e->getMessage();
        }
        
        try {
            $cleaned_files += cleanup_old_log_files($days);
            $cleanup_results[] = "日志文件清理完成";
        } catch (Exception $e) {
            $cleanup_results[] = "日志文件清理失败: " . $e->getMessage();
        }
        
        try {
            $cleaned_files += cleanup_upload_temp_files(1); // 清理1天前的临时文件
            $cleanup_results[] = "临时文件清理完成";
        } catch (Exception $e) {
            $cleanup_results[] = "临时文件清理失败: " . $e->getMessage();
        }
        
        try {
            $cleaned_files += cleanup_old_sessions(7); // 清理7天前的会话文件
            $cleanup_results[] = "会话文件清理完成";
        } catch (Exception $e) {
            $cleanup_results[] = "会话文件清理跳过: " . $e->getMessage();
        }
        
        // 记录清理结果
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("cleanup_old_data: Cleaned {$cleaned_messages} messages and {$cleaned_files} files");
            foreach ($cleanup_results as $result) {
                error_log("cleanup_old_data: " . $result);
            }
        }
        
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("cleanup_old_data() error: " . $e->getMessage());
        }
        return false;
    }
}

// 获取系统设置
function get_setting($key, $default = '') {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetchColumn();
    
    return $result !== false ? $result : $default;
}

// 更新系统设置
function update_setting($key, $value) {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                          ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
    return $stmt->execute([$key, $value, $value]);
}

// 获取数据库大小
function get_database_size() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'DB Size in MB' 
                            FROM information_schema.tables 
                            WHERE table_schema = DATABASE()");
        $size = $stmt->fetchColumn();
        return $size ? $size . ' MB' : '未知';
    } catch (Exception $e) {
        return '未知';
    }
}

// 获取磁盘使用情况
function get_disk_usage() {
    $bytes = disk_free_space(".");
    $total = disk_total_space(".");
    
    if ($bytes && $total) {
        $used = $total - $bytes;
        $percent = round(($used / $total) * 100, 1);
        return number_format($used / 1024 / 1024, 2) . ' MB (' . $percent . '%)';
    }
    
    return '未知';
}

// ============================================
// 邮箱验证功能
// ============================================

// 生成邮箱验证码
function generate_verification_code() {
    return md5(uniqid(mt_rand(), true));
}

// 发送邮箱验证码
function send_verification_email($user_id, $email, $username) {
    global $pdo;
    
    // 生成验证码
    $verification_code = generate_verification_code();
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // 删除旧的验证码
    $stmt = $pdo->prepare("DELETE FROM email_verifications WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // 插入新的验证码
    $stmt = $pdo->prepare("INSERT INTO email_verifications (user_id, email, verification_code, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $email, $verification_code, $expires_at]);
    
    // 发送邮件
    $subject = "邮箱验证 - 车友帮讯联";
    $verification_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/verify_email?code=' . $verification_code;
    
    $message = "
    <!DOCTYPE html>
    <html lang='zh-CN'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>邮箱验证</title>
        <style>
            body { font-family: 'Microsoft YaHei', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f5f5f5; }
            .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #e3f2fd, #bbdefb); color: #1565c0; padding: 30px 20px; text-align: center; border-bottom: 3px solid #1976d2; }
            .content { padding: 30px 20px; }
            .button { display: inline-block; background: linear-gradient(45deg, #e3f2fd, #bbdefb, #90caf9); color: #0d47a1; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; margin: 20px 0; border: 3px solid #1976d2; box-shadow: 0 6px 20px rgba(33, 150, 243, 0.4); transition: all 0.3s ease; }
            .info { background: linear-gradient(135deg, #e3f2fd, #bbdefb); border: 1px solid #2196f3; padding: 15px; border-radius: 8px; margin: 20px 0; box-shadow: 0 4px 12px rgba(33, 150, 243, 0.2); }
            .footer { background: linear-gradient(135deg, #e8eaf6, #c5cae9); padding: 20px; text-align: center; color: #3f51b5; font-size: 12px; border-top: 3px solid #3f51b5; }
            .url-box { background: linear-gradient(135deg, #f5f5f5, #e0e0e0); padding: 10px; border-radius: 6px; word-break: break-all; font-family: monospace; margin: 10px 0; border: 2px dashed #9e9e9e; color: #424242; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1); }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin: 0; font-size: 24px;'><span style='mso-line-height-rule:exactly; line-height:1.2;'>📧</span> 邮箱验证</h1>
                <p style='margin: 10px 0 0 0; opacity: 0.9;'>车友帮讯联系统</p>
            </div>
            <div class='content'>
                <p>亲爱的 <strong>{$username}</strong>，</p>
                <p>欢迎加入车友帮讯联！为了确保您的账户安全，请验证您的邮箱地址：</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$verification_url}' class='button'>
                        <span style='mso-line-height-rule:exactly; line-height:1.2;'>✅</span> 立即验证邮箱
                    </a>
                </div>
                
                <p>如果按钮无法点击，请复制以下链接到浏览器中打开：</p>
                <div class='url-box'>{$verification_url}</div>
                
                <div class='info'>
                    <p style='color: #0c5460; margin: 0; font-size: 14px;'>
                        <strong><span style='mso-line-height-rule:exactly; line-height:1.2;'>📋</span> 账户信息：</strong><br>
                        • 用户名：{$username}<br>
                        • 注册邮箱：{$email}<br>
                        • 注册时间：" . date('Y-m-d H:i:s') . "<br>
                        • 验证有效期：24小时
                    </p>
                </div>
                
                <div style='background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; font-size: 14px; color: #6c757d;'>
                    <strong><span style='mso-line-height-rule:exactly; line-height:1.2;'>💡</span> 温馨提示：</strong><br>
                    • 请在24小时内完成邮箱验证<br>
                    • 未验证的账户将无法正常使用<br>
                    • 如果没有收到邮件，请检查垃圾邮件文件夹<br>
                    • 验证后即可开始使用所有功能
                </div>
            </div>
            <div class='footer'>
                <p>此邮件由车友帮讯联系统自动发送，请勿回复</p>
                <p>&copy; " . date('Y') . " 车友帮讯联 v2.1.1</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // 优先使用异步邮件发送
    if (function_exists('queueEmail')) {
        return queueEmail($email, $subject, $message, 'high'); // 验证邮件优先级较高
    } else if (function_exists('sendEmailQuick')) {
        return sendEmailQuick($email, $subject, $message);
    } else {
        return sendEmail($email, $subject, $message);
    }
}

// 验证邮箱验证码
function verify_email_code($code) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT user_id, email FROM email_verifications WHERE verification_code = ? AND expires_at > NOW()");
    $stmt->execute([$code]);
    $verification = $stmt->fetch();
    
    if ($verification) {
        // 更新用户邮箱验证状态
        $stmt = $pdo->prepare("UPDATE users SET email_verified = 1 WHERE id = ?");
        $stmt->execute([$verification['user_id']]);
        
        // 删除验证记录
        $stmt = $pdo->prepare("DELETE FROM email_verifications WHERE verification_code = ?");
        $stmt->execute([$code]);
        
        return $verification;
    }
    
    return false;
}

// 重新发送验证邮件
function resend_verification_email($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT username, email, email_verified FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user && !$user['email_verified']) {
        return send_verification_email($user_id, $user['email'], $user['username']);
    }
    
    return false;
}

// 检查是否需要邮箱验证提示
function needs_email_verification() {
    if (!is_logged_in()) {
        return false;
    }
    
    global $pdo;
    $stmt = $pdo->prepare("SELECT email_verified FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    return $user && !$user['email_verified'];
}

// 记录管理员操作日志
function log_admin_action($action, $message, $user_id = null) {
    global $pdo;
    
    if ($user_id === null && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO admin_logs (user_id, action, message, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        return $stmt->execute([$user_id, $action, $message, $ip_address]);
    } catch (Exception $e) {
        // 如果admin_logs表不存在，可以忽略错误或创建表
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("log_admin_action() error: " . $e->getMessage());
        }
        return true;
    }
}

// 获取管理员操作日志
function get_admin_logs($limit = 20) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT al.*, u.username 
                              FROM admin_logs al 
                              LEFT JOIN users u ON al.user_id = u.id 
                              ORDER BY al.created_at DESC 
                              LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// 邮件功能包装器函数
// 确保邮件函数在系统诊断时可以被正确检测
// 注意：这些函数在 config/email.php 中已定义，这里仅作为备用

if (!function_exists('isEmailConfigured')) {
    function isEmailConfigured() {
        // 备用实现：如果 email.php 未加载，提供基本检查
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
}

if (!function_exists('testEmailConnection')) {
    function testEmailConnection() {
        // 备用实现：如果 email.php 未加载，提供基本测试
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
}

// 黑名单管理函数
// 检查邮箱是否在黑名单中
function is_email_blacklisted($email) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM email_blacklist WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("is_email_blacklisted() error: " . $e->getMessage());
        }
        return false;
    }
}

// 将邮箱加入黑名单
function add_email_to_blacklist($email, $reason = '管理员拉黑', $admin_id = null) {
    global $pdo;
    
    try {
        // 检查是否已在黑名单中
        if (is_email_blacklisted($email)) {
            return ['success' => false, 'message' => '该邮箱已在黑名单中'];
        }
        
        $stmt = $pdo->prepare("INSERT INTO email_blacklist (email, reason, admin_id) VALUES (?, ?, ?)");
        $result = $stmt->execute([$email, $reason, $admin_id]);
        
        if ($result) {
            return ['success' => true, 'message' => '邮箱已加入黑名单'];
        } else {
            return ['success' => false, 'message' => '加入黑名单失败'];
        }
    } catch (Exception $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("add_email_to_blacklist() error: " . $e->getMessage());
        }
        return ['success' => false, 'message' => '数据库操作失败：' . $e->getMessage()];
    }
}

// 从黑名单中移除邮箱
function remove_email_from_blacklist($email) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM email_blacklist WHERE email = ?");
        $result = $stmt->execute([$email]);
        
        if ($result && $stmt->rowCount() > 0) {
            return ['success' => true, 'message' => '邮箱已从黑名单中移除'];
        } else {
            return ['success' => false, 'message' => '邮箱不在黑名单中或移除失败'];
        }
    } catch (Exception $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("remove_email_from_blacklist() error: " . $e->getMessage());
        }
        return ['success' => false, 'message' => '数据库操作失败：' . $e->getMessage()];
    }
}

// 管理员删除用户（保留注册权限）
function admin_delete_user($user_id, $admin_id = null) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // 获取用户信息
        $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $pdo->rollBack();
            return ['success' => false, 'message' => '用户不存在'];
        }
        
        // 删除用户的留言（CASCADE会自动处理）
        $stmt = $pdo->prepare("DELETE FROM messages WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $deleted_messages = $stmt->rowCount();
        
        // 删除邮箱验证记录
        $stmt = $pdo->prepare("DELETE FROM email_verifications WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 删除用户
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $result = $stmt->execute([$user_id]);
        
        if ($result && $stmt->rowCount() > 0) {
            $pdo->commit();
            
            // 记录管理员操作日志
            $log_message = "管理员删除用户: {$user['username']} ({$user['email']})，清理了 {$deleted_messages} 条留言";
            log_admin_action('delete_user', $log_message, $admin_id);
            
            return ['success' => true, 'message' => "用户删除成功，已清理 {$deleted_messages} 条留言"];
        } else {
            $pdo->rollBack();
            return ['success' => false, 'message' => '用户删除失败'];
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("admin_delete_user() error: " . $e->getMessage());
        }
        return ['success' => false, 'message' => '数据库操作失败：' . $e->getMessage()];
    }
}

// 管理员将用户拉入黑名单（删除用户并禁止邮箱）
function admin_blacklist_user($user_id, $reason = '违规行为', $admin_id = null) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // 获取用户信息
        $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $pdo->rollBack();
            return ['success' => false, 'message' => '用户不存在'];
        }
        
        // 检查邮箱是否已在黑名单中
        if (is_email_blacklisted($user['email'])) {
            $pdo->rollBack();
            return ['success' => false, 'message' => '该邮箱已在黑名单中'];
        }
        
        // 删除用户的留言
        $stmt = $pdo->prepare("DELETE FROM messages WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $deleted_messages = $stmt->rowCount();
        
        // 删除邮箱验证记录
        $stmt = $pdo->prepare("DELETE FROM email_verifications WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 删除用户
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // 将邮箱加入黑名单
        $stmt = $pdo->prepare("INSERT INTO email_blacklist (email, reason, admin_id) VALUES (?, ?, ?)");
        $stmt->execute([$user['email'], $reason, $admin_id]);
        
        $pdo->commit();
        
        // 记录管理员操作日志
        $log_message = "管理员拉黑用户: {$user['username']} ({$user['email']})，原因: {$reason}，清理了 {$deleted_messages} 条留言";
        log_admin_action('blacklist_user', $log_message, $admin_id);
        
        return ['success' => true, 'message' => "用户已拉入黑名单，清理了 {$deleted_messages} 条留言"];
    } catch (Exception $e) {
        $pdo->rollBack();
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("admin_blacklist_user() error: " . $e->getMessage());
        }
        return ['success' => false, 'message' => '数据库操作失败：' . $e->getMessage()];
    }
}

// ============================================
// 密码重置功能
// ============================================

// 生成密码重置令牌
function generate_password_reset_token() {
    return bin2hex(random_bytes(32));
}

// 请求密码重置
function request_password_reset($email) {
    global $pdo;
    
    try {
        // 检查用户是否存在
        $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // 为了安全起见，即使用户不存在也返回成功消息
            return ['success' => true, 'message' => '如果该邮箱存在于系统中，重置链接已发送到您的邮箱'];
        }
        
        // 生成重置令牌
        $reset_token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // 清理该用户所有过期的重置记录
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ? AND expires_at < NOW()");
        $stmt->execute([$email]);
        
        // 插入新的重置记录
        $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, email, reset_token, expires_at, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user['id'], $email, $reset_token, $expires_at, $ip_address]);
        
        // 构建重置URL
        $reset_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/reset_password?token=' . $reset_token;
        
        // 发送重置邮件
        $subject = "【车友帮讯联】密码重置验证";
        $message = "
        <!DOCTYPE html>
        <html lang='zh-CN'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>密码重置</title>
            <style>
                body { font-family: 'Microsoft YaHei', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f5f5f5; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #e3f2fd, #bbdefb); color: #1565c0; padding: 30px 20px; text-align: center; border-bottom: 3px solid #1976d2; }
                .content { padding: 30px 20px; }
                .button { display: inline-block; background: linear-gradient(45deg, #e3f2fd, #bbdefb, #90caf9); color: #0d47a1; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; margin: 20px 0; border: 3px solid #1976d2; box-shadow: 0 6px 20px rgba(33, 150, 243, 0.4); transition: all 0.3s ease; }
                .warning { background: linear-gradient(135deg, #fff3e0, #ffe0b2); border: 1px solid #ff9800; padding: 15px; border-radius: 8px; margin: 20px 0; box-shadow: 0 4px 12px rgba(255, 152, 0, 0.2); border-left: 5px solid #ff9800; }
                .footer { background: linear-gradient(135deg, #e8eaf6, #c5cae9); padding: 20px; text-align: center; color: #3f51b5; font-size: 12px; border-top: 3px solid #3f51b5; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin: 0; font-size: 24px;'><span style='mso-line-height-rule:exactly; line-height:1.2;'>🔑</span> 密码重置</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>车友帮讯联系统</p>
                </div>
                <div class='content'>
                    <p>亲爱的 <strong>{$user['username']}</strong>，</p>
                    <p>我们收到了您的密码重置请求。请点击下方按钮重置您的密码：</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$reset_url}' class='button'>
                            <span style='mso-line-height-rule:exactly; line-height:1.2;'>🔄</span> 立即重置密码
                        </a>
                    </div>
                    
                    <div class='warning'>
                        <strong><span style='mso-line-height-rule:exactly; line-height:1.2;'>⚠️</span> 安全提醒：</strong>
                        <ul style='margin: 10px 0 0 20px;'>
                            <li>此链接将在 <strong>1小时后</strong> 自动失效</li>
                            <li>如果您没有请求重置密码，请忽略此邮件</li>
                            <li>请勿将此链接分享给他人</li>
                        </ul>
                    </div>
                    
                    <p style='color: #6c757d; font-size: 14px; margin-top: 30px;'>
                        如果按钮无法点击，请复制以下链接到浏览器地址栏：<br>
                        <span style='word-break: break-all; background: #f8f9fa; padding: 5px; border-radius: 3px; font-family: monospace;'>{$reset_url}</span>
                    </p>
                </div>
                <div class='footer'>
                    <p>此邮件由系统自动发送，请勿回复</p>
                    <p>© " . date('Y') . " 车友帮讯联系统 - 安全可靠的消息管理平台</p>
                </div>
            </div>
        </body>
        </html>";
        
        // 优先使用异步邮件发送
        if (function_exists('queueEmail')) {
            $result = queueEmail($email, $subject, $message, 'high'); // 密码重置优先级较高
        } else if (function_exists('sendEmailQuick')) {
            $result = sendEmailQuick($email, $subject, $message);
        } else {
            $result = sendEmail($email, $subject, $message);
        }
        
        if ($result) {
            return ['success' => true, 'message' => '重置链接已发送到您的邮箱，请查收（包括垃圾邮件文件夹）'];
        } else {
            throw new Exception('邮件发送失败，请稍后再试');
        }
        
    } catch (Exception $e) {
        error_log("密码重置发送失败: " . $e->getMessage());
        return ['success' => false, 'message' => '发送失败：' . $e->getMessage()];
    }
}

// 验证密码重置令牌
function validate_password_reset_token($token) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT pr.*, u.username, u.email 
                              FROM password_resets pr 
                              JOIN users u ON pr.user_id = u.id 
                              WHERE pr.reset_token = ? AND pr.expires_at > NOW() AND pr.used = 0");
        $stmt->execute([$token]);
        $reset_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($reset_data) {
            return [
                'success' => true,
                'user_id' => $reset_data['user_id'],
                'email' => $reset_data['email'],
                'username' => $reset_data['username'],
                'expires_at' => $reset_data['expires_at']
            ];
        } else {
            return ['success' => false, 'message' => '重置链接无效或已过期'];
        }
        
    } catch (Exception $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("validate_password_reset_token() error: " . $e->getMessage());
        }
        return ['success' => false, 'message' => '验证失败，请重新申请密码重置'];
    }
}

// 重置密码
function reset_password($token, $new_password) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // 验证令牌
        $validation = validate_password_reset_token($token);
        if (!$validation['success']) {
            $pdo->rollBack();
            return $validation;
        }
        
        $user_id = $validation['user_id'];
        $email = $validation['email'];
        
        // 验证新密码强度
        if (strlen($new_password) < 6) {
            $pdo->rollBack();
            return ['success' => false, 'message' => '密码长度至少6位'];
        }
        
        // 加密新密码
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // 更新用户密码
        $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $update_result = $stmt->execute([$hashed_password, $user_id]);
        
        if (!$update_result) {
            $pdo->rollBack();
            return ['success' => false, 'message' => '密码更新失败'];
        }
        
        // 标记重置令牌为已使用
        $stmt = $pdo->prepare("UPDATE password_resets SET used = 1, used_at = NOW() WHERE reset_token = ?");
        $stmt->execute([$token]);
        
        // 清理该用户所有其他未使用的重置令牌（一次重置，全部失效）
        $stmt = $pdo->prepare("UPDATE password_resets SET used = 1, used_at = NOW() WHERE user_id = ? AND used = 0 AND reset_token != ?");
        $stmt->execute([$user_id, $token]);
        
        $pdo->commit();
        
        // 记录安全日志
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $log_message = "用户通过邮箱重置密码: {$email}, IP: {$ip_address}";
        log_admin_action('password_reset', $log_message, $user_id);
        
        return ['success' => true, 'message' => '密码重置成功，请使用新密码登录'];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("reset_password() error: " . $e->getMessage());
        }
        return ['success' => false, 'message' => '密码重置失败，请重试'];
    }
}

// 清理过期的密码重置记录
function cleanup_expired_password_resets() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE expires_at < NOW()");
        $stmt->execute();
        $deleted_count = $stmt->rowCount();
        
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Cleaned up {$deleted_count} expired password reset records");
        }
        
        return $deleted_count;
    } catch (Exception $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("cleanup_expired_password_resets() error: " . $e->getMessage());
        }
        return 0;
    }
}

// 清理过期的邮箱验证记录
function cleanup_expired_email_verifications() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM email_verifications WHERE expires_at < NOW()");
        $stmt->execute();
        $deleted_count = $stmt->rowCount();
        
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Cleaned up {$deleted_count} expired email verification records");
        }
        
        return $deleted_count;
    } catch (Exception $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("cleanup_expired_email_verifications() error: " . $e->getMessage());
        }
        return 0;
    }
}

// 清理旧的二维码文件
function cleanup_old_qrcode_files($days = 30) {
    $deleted_count = 0;
    $qrcodes_dir = __DIR__ . '/../qrcodes';
    
    if (!is_dir($qrcodes_dir)) {
        return $deleted_count;
    }
    
    try {
        $files = glob($qrcodes_dir . '/qr_*.{png,svg}', GLOB_BRACE);
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (file_exists($file) && filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }
        
        if (defined('DEBUG_MODE') && DEBUG_MODE && $deleted_count > 0) {
            error_log("Cleaned up {$deleted_count} old QR code files");
        }
        
        return $deleted_count;
    } catch (Exception $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("cleanup_old_qrcode_files() error: " . $e->getMessage());
        }
        return 0;
    }
}

// 清理旧的日志文件
function cleanup_old_log_files($days = 90) {
    $deleted_count = 0;
    $logs_dir = __DIR__ . '/../logs';
    
    if (!is_dir($logs_dir)) {
        return $deleted_count;
    }
    
    try {
        $files = glob($logs_dir . '/*.log');
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (file_exists($file) && filemtime($file) < $cutoff_time) {
                // 检查文件大小，如果太大也清理
                $file_size = filesize($file);
                if ($file_size > 50 * 1024 * 1024) { // 大于50MB
                    if (unlink($file)) {
                        $deleted_count++;
                    }
                } elseif (filemtime($file) < $cutoff_time) {
                    if (unlink($file)) {
                        $deleted_count++;
                    }
                }
            }
        }
        
        if (defined('DEBUG_MODE') && DEBUG_MODE && $deleted_count > 0) {
            error_log("Cleaned up {$deleted_count} old log files");
        }
        
        return $deleted_count;
    } catch (Exception $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("cleanup_old_log_files() error: " . $e->getMessage());
        }
        return 0;
    }
}

// 清理临时会话数据（如果使用文件存储session）
function cleanup_old_sessions($days = 7) {
    $deleted_count = 0;
    
    try {
        $session_path = session_save_path() ?: sys_get_temp_dir();
        
        // 检查是否受open_basedir限制
        $open_basedir = ini_get('open_basedir');
        if (!empty($open_basedir)) {
            $allowed_paths = explode(':', $open_basedir);
            $path_allowed = false;
            foreach ($allowed_paths as $allowed_path) {
                if (strpos($session_path, trim($allowed_path)) === 0) {
                    $path_allowed = true;
                    break;
                }
            }
            
            // 如果session路径不在允许范围内，跳过清理
            if (!$path_allowed) {
                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    error_log("Session cleanup skipped: path '$session_path' not allowed by open_basedir");
                }
                return $deleted_count;
            }
        }
        
        if (!is_dir($session_path)) {
            return $deleted_count;
        }
        
        $files = glob($session_path . '/sess_*');
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (file_exists($file) && filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }
        
        if (defined('DEBUG_MODE') && DEBUG_MODE && $deleted_count > 0) {
            error_log("Cleaned up {$deleted_count} old session files");
        }
        
        return $deleted_count;
    } catch (Exception $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("cleanup_old_sessions() error: " . $e->getMessage());
        }
        return 0;
    }
}

// 清理上传目录中的临时文件
function cleanup_upload_temp_files($days = 1) {
    $deleted_count = 0;
    $upload_dir = __DIR__ . '/../uploads';
    
    if (!is_dir($upload_dir)) {
        return $deleted_count;
    }
    
    try {
        $files = glob($upload_dir . '/tmp_*');
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (file_exists($file) && filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }
        
        if (defined('DEBUG_MODE') && DEBUG_MODE && $deleted_count > 0) {
            error_log("Cleaned up {$deleted_count} temporary upload files");
        }
        
        return $deleted_count;
    } catch (Exception $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("cleanup_upload_temp_files() error: " . $e->getMessage());
        }
        return 0;
    }
}

// 优化数据库表（重建索引，清理碎片）
function optimize_database_tables() {
    global $pdo;
    
    try {
        $tables = ['users', 'messages', 'email_verifications', 'password_resets', 'settings', 'email_blacklist'];
        $optimized_count = 0;
        
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("OPTIMIZE TABLE `{$table}`");
            if ($stmt->execute()) {
                $optimized_count++;
            }
        }
        
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Optimized {$optimized_count} database tables");
        }
        
        return $optimized_count;
    } catch (Exception $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("optimize_database_tables() error: " . $e->getMessage());
        }
        return 0;
    }
}

// 执行数据库性能索引优化
function optimize_database_indexes() {
    global $pdo;
    
    try {
        $indexes_added = 0;
        
        // 为 messages 表添加性能索引
        $indexes = [
            "ALTER TABLE `messages` ADD INDEX `idx_user_created` (`user_id`, `created_at`)",
            "ALTER TABLE `messages` ADD INDEX `idx_is_read` (`is_read`)",
            "ALTER TABLE `messages` ADD INDEX `idx_user_read` (`user_id`, `is_read`)",
            "ALTER TABLE `users` ADD INDEX `idx_email_verified` (`email_verified`)",
            "ALTER TABLE `users` ADD INDEX `idx_is_admin` (`is_admin`)",
            "ALTER TABLE `users` ADD INDEX `idx_created_at` (`created_at`)",
            "ALTER TABLE `email_verifications` ADD INDEX `idx_user_expires` (`user_id`, `expires_at`)",
            "ALTER TABLE `password_resets` ADD INDEX `idx_user_expires` (`user_id`, `expires_at`)",
            "ALTER TABLE `password_resets` ADD INDEX `idx_used_expires` (`used`, `expires_at`)"
        ];
        
        foreach ($indexes as $sql) {
            try {
                $pdo->exec($sql);
                $indexes_added++;
            } catch (PDOException $e) {
                // 索引可能已存在，继续执行
                if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                    error_log("Index creation warning: " . $e->getMessage());
                }
            }
        }
        
        return $indexes_added;
    } catch (Exception $e) {
        error_log("optimize_database_indexes() error: " . $e->getMessage());
        return 0;
    }
}

// 获取密码重置统计信息（管理员用）
function get_password_reset_stats() {
    global $pdo;
    
    try {
        $stats = [];
        
        // 今日重置请求数
        $stmt = $pdo->query("SELECT COUNT(*) FROM password_resets WHERE DATE(created_at) = CURDATE()");
        $stats['today_requests'] = $stmt->fetchColumn();
        
        // 本周重置请求数
        $stmt = $pdo->query("SELECT COUNT(*) FROM password_resets WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['week_requests'] = $stmt->fetchColumn();
        
        // 成功重置数（本周）
        $stmt = $pdo->query("SELECT COUNT(*) FROM password_resets WHERE used = 1 AND used_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['week_successful'] = $stmt->fetchColumn();
        
        // 待处理重置请求（未过期且未使用）
        $stmt = $pdo->query("SELECT COUNT(*) FROM password_resets WHERE used = 0 AND expires_at > NOW()");
        $stats['pending_requests'] = $stmt->fetchColumn();
        
        return $stats;
    } catch (Exception $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("get_password_reset_stats() error: " . $e->getMessage());
        }
        return [
            'today_requests' => 0,
            'week_requests' => 0,
            'week_successful' => 0,
            'pending_requests' => 0
        ];
    }
}

// 检查用户是否可以请求密码重置（防止滥用）
function can_request_password_reset($email) {
    global $pdo;
    
    try {
        // 添加调试日志
        error_log("检查密码重置限制 - 邮箱: $email");
        
        // 检查过去24小时内的重置请求次数
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM password_resets WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute([$email]);
        $recent_requests = $stmt->fetchColumn();
        
        error_log("24小时内请求次数: $recent_requests");
        
        // 每24小时最多3次重置请求
        if ($recent_requests >= 3) {
            error_log("频率限制触发 - 邮箱: $email, 请求次数: $recent_requests");
            return ['success' => false, 'message' => '24小时内重置请求次数过多，请稍后再试'];
        }
        
        error_log("频率检查通过 - 邮箱: $email");
        return ['success' => true];
        
    } catch (Exception $e) {
        error_log("can_request_password_reset() error: " . $e->getMessage());
        return ['success' => true]; // 出错时允许请求，避免阻止正常用户
    }
}

// 创建密码重置邮件（HTML格式）
function createPasswordResetEmail($user, $reset_url) {
    // 安全地获取用户信息，避免空值错误
    $username = htmlspecialchars($user['username'] ?? 'Unknown User');
    $email = htmlspecialchars($user['email'] ?? 'unknown@example.com');
    
    // 验证必要参数
    if (empty($reset_url)) {
        throw new InvalidArgumentException('Reset URL cannot be empty');
    }
    
    return "
    <!DOCTYPE html>
    <html lang='zh-CN'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>【车友帮讯联】密码重置</title>
        <style>
            body { 
                font-family: 'Microsoft YaHei', 'Helvetica Neue', Arial, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                min-height: 100vh;
            }
            .email-container { 
                max-width: 650px; 
                margin: 40px auto; 
                background: white; 
                border-radius: 15px; 
                overflow: hidden; 
                box-shadow: 0 25px 50px rgba(0,0,0,0.15);
                border: 1px solid #e0e0e0;
            }
            .header { 
                background: linear-gradient(135deg, #e3f2fd, #bbdefb); 
                color: #1565c0; 
                padding: 40px 30px; 
                text-align: center;
                position: relative;
                overflow: hidden;
                border-bottom: 3px solid #1976d2;
            }
            .header::before {
                content: '';
                position: absolute;
                top: -50%;
                left: -50%;
                width: 200%;
                height: 200%;
                background: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><circle cx=\"50\" cy=\"50\" r=\"2\" fill=\"white\" opacity=\"0.1\"/></svg>') repeat;
                animation: float 20s infinite linear;
            }
            @keyframes float {
                0% { transform: translateX(-50%) translateY(-50%) rotate(0deg); }
                100% { transform: translateX(-50%) translateY(-50%) rotate(360deg); }
            }
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 700;
                position: relative;
                z-index: 1;
                color: #0d47a1;
            }
            .header p {
                margin: 10px 0 0 0;
                font-size: 16px;
                position: relative;
                z-index: 1;
                color: #1565c0;
                font-weight: 500;
            }
            .content { 
                padding: 40px 30px; 
                background: #fff;
            }
            .reset-button { 
                display: inline-block; 
                background: linear-gradient(45deg, #e3f2fd, #bbdefb, #90caf9); 
                color: #0d47a1; 
                padding: 18px 35px; 
                text-decoration: none; 
                border-radius: 50px; 
                font-weight: 700; 
                font-size: 16px;
                margin: 30px 0; 
                box-shadow: 0 8px 25px rgba(33, 150, 243, 0.4);
                transition: all 0.3s ease;
                text-align: center;
                letter-spacing: 0.5px;
                border: 3px solid #1976d2;
                position: relative;
                overflow: hidden;
            }
            .reset-button:hover {
                transform: translateY(-3px) scale(1.02);
                box-shadow: 0 15px 35px rgba(33, 150, 243, 0.6);
                text-decoration: none;
                color: #0d47a1;
                background: linear-gradient(45deg, #bbdefb, #90caf9, #64b5f6);
            }
            .warning-box { 
                background: linear-gradient(135deg, #fff3e0, #ffe0b2); 
                border-left: 5px solid #ff9800; 
                padding: 20px; 
                border-radius: 8px; 
                margin: 25px 0;
                box-shadow: 0 4px 15px rgba(255, 152, 0, 0.2);
                border: 1px solid #ffcc02;
            }
            .info-box {
                background: linear-gradient(135deg, #e3f2fd, #bbdefb);
                border-left: 5px solid #2196f3;
                padding: 20px;
                border-radius: 8px;
                margin: 25px 0;
                box-shadow: 0 4px 15px rgba(33, 150, 243, 0.2);
                border: 1px solid #2196f3;
            }
            .url-display { 
                background: linear-gradient(135deg, #f5f5f5, #e0e0e0); 
                padding: 15px; 
                border-radius: 8px; 
                word-break: break-all; 
                font-family: 'Courier New', monospace; 
                margin: 15px 0;
                border: 2px dashed #9e9e9e;
                font-size: 14px;
                color: #424242;
                box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
            }
            .footer { 
                background: linear-gradient(135deg, #e8eaf6, #c5cae9); 
                padding: 30px; 
                text-align: center; 
                color: #3f51b5; 
                font-size: 14px;
                border-top: 3px solid #3f51b5;
            }
            .footer p { 
                margin: 5px 0; 
                opacity: 0.9;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
                margin: 20px 0;
            }
            .stat-item {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 8px;
                text-align: center;
                border: 1px solid #e9ecef;
            }
            .stat-label {
                font-size: 12px;
                color: #6c757d;
                margin-bottom: 5px;
            }
            .stat-value {
                font-size: 16px;
                font-weight: bold;
                color: #495057;
            }
            .icon {
                font-size: 24px;
                margin-right: 10px;
                vertical-align: middle;
                line-height: 1.2;
                mso-line-height-rule: exactly;
                mso-text-raise: 0px;
                display: inline-block;
                font-family: 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji', sans-serif;
            }
            @media (max-width: 600px) {
                .email-container { 
                    margin: 20px; 
                    border-radius: 10px; 
                }
                .header, .content { 
                    padding: 30px 20px; 
                }
                .header h1 { 
                    font-size: 24px; 
                }
                .reset-button { 
                    padding: 15px 25px; 
                    font-size: 14px; 
                }
                .stats-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <h1><span class='icon' style='mso-line-height-rule:exactly; line-height:1.2;'>🔐</span>密码重置请求</h1>
                <p>车友帮讯联系统 - 安全验证</p>
            </div>
            
            <div class='content'>
                <p style='font-size: 18px; margin-bottom: 20px;'>
                    尊敬的 <strong style='color: #ff6b6b;'>{$username}</strong>，您好！
                </p>
                
                <p style='font-size: 16px; line-height: 1.8; margin-bottom: 25px;'>
                    我们收到了您的密码重置请求。为了保护您的账户安全，请点击下方按钮完成密码重置：
                </p>
                
                <div style='text-align: center; margin: 40px 0;'>
                    <a href='{$reset_url}' class='reset-button'>
                        <span class='icon' style='mso-line-height-rule:exactly; line-height:1.2;'>🔄</span>立即重置密码
                    </a>
                </div>
                
                <div class='info-box'>
                    <h3 style='color: #2c3e50; margin-top: 0; font-size: 16px;'>
                        <span class='icon' style='mso-line-height-rule:exactly; line-height:1.2;'>📋</span>重置信息
                    </h3>
                    <div class='stats-grid'>
                        <div class='stat-item'>
                            <div class='stat-label'>用户名</div>
                            <div class='stat-value'>{$username}</div>
                        </div>
                        <div class='stat-item'>
                            <div class='stat-label'>绑定邮箱</div>
                            <div class='stat-value'>{$email}</div>
                        </div>
                        <div class='stat-item'>
                            <div class='stat-label'>请求时间</div>
                            <div class='stat-value'>" . date('H:i:s') . "</div>
                        </div>
                        <div class='stat-item'>
                            <div class='stat-label'>有效期</div>
                            <div class='stat-value'>1小时</div>
                        </div>
                    </div>
                </div>
                
                <div class='warning-box'>
                    <h3 style='color: #d63031; margin-top: 0; font-size: 16px;'>
                        <span class='icon' style='mso-line-height-rule:exactly; line-height:1.2;'>⚠️</span>安全提醒
                    </h3>
                    <ul style='margin: 10px 0 0 20px; padding: 0; line-height: 1.8;'>
                        <li><strong>时效性：</strong>此重置链接将在 <span style='color: #d63031; font-weight: bold;'>1小时后</span> 自动失效</li>
                        <li><strong>唯一性：</strong>每个重置链接只能使用一次</li>
                        <li><strong>安全性：</strong>如非本人操作，请立即联系管理员</li>
                        <li><strong>保密性：</strong>请勿将此链接分享给任何人</li>
                    </ul>
                </div>
                
                <div style='background: #e8f4fd; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 5px solid #3498db;'>
                    <h4 style='color: #2980b9; margin-top: 0; font-size: 14px;'>
                        <span class='icon' style='mso-line-height-rule:exactly; line-height:1.2;'>🔗</span>备用访问方式
                    </h4>
                    <p style='margin: 10px 0; font-size: 14px; color: #34495e;'>
                        如果上方按钮无法点击，请复制以下链接到浏览器地址栏：
                    </p>
                    <div class='url-display'>{$reset_url}</div>
                </div>
                
                <div style='border-top: 2px solid #ecf0f1; padding-top: 25px; margin-top: 30px; text-align: center;'>
                    <p style='color: #7f8c8d; font-size: 14px; margin: 0;'>
                        <span class='icon' style='mso-line-height-rule:exactly; line-height:1.2;'>💡</span>
                        <strong>小贴士：</strong>建议使用包含大小写字母、数字和特殊字符的强密码
                    </p>
                </div>
            </div>
            
            <div class='footer'>
                <p style='font-weight: bold; font-size: 16px; margin-bottom: 10px;'>车友帮讯联系统</p>
                <p>此邮件由系统自动发送，请勿直接回复</p>
                <p>如有疑问，请联系系统管理员</p>
                <p style='margin-top: 15px; font-size: 12px; opacity: 0.8;'>
                    © " . date('Y') . " 车友帮讯联 v2.1.1 - 专业的消息管理平台
                </p>
            </div>
        </div>
    </body>
    </html>";
}

// 安全的文件上传验证函数 - 增强版
function validate_file_upload($file) {
    // 检查文件是否上传成功
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return '文件上传失败';
    }
    
    // 检查文件大小
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return '文件大小超过限制 (' . (UPLOAD_MAX_SIZE / 1024 / 1024) . 'MB)';
    }
    
    // 检查文件扩展名
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return '不支持的文件类型';
    }
    
    // 检查MIME类型
    if (defined('ALLOWED_MIME_TYPES')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, ALLOWED_MIME_TYPES)) {
            return 'Invalid file type detected';
        }
    }
    
    // 检查文件内容（基础图片验证）
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $image_info = getimagesize($file['tmp_name']);
        if ($image_info === false) {
            return '文件不是有效的图片';
        }
        
        // 检查图片尺寸限制
        if ($image_info[0] > 5000 || $image_info[1] > 5000) {
            return '图片尺寸过大 (最大5000x5000)';
        }
    }
    
    // 检查文件名安全性
    if (preg_match('/[<>:"|?*]/', $file['name'])) {
        return '文件名包含非法字符';
    }
    
    return true; // 验证通过
}

// 安全的文件名生成
function generate_safe_filename($original_name) {
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $safe_name = preg_replace('/[^a-zA-Z0-9\-_.]/', '_', pathinfo($original_name, PATHINFO_FILENAME));
    $unique_id = uniqid();
    return $safe_name . '_' . $unique_id . '.' . $extension;
}

/**
 * 防封系统 - 检测微信/QQ内置浏览器
 * 通过User-Agent识别各种内置浏览器环境
 */

// 检测是否为微信内置浏览器 - 增强版
function is_wechat_browser() {
    $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    
    // 检测多个微信特征
    $wechat_patterns = [
        'micromessenger',  // 标准微信标识
        'wechat',          // 微信英文名
        'weixin'           // 微信中文拼音
    ];
    
    foreach ($wechat_patterns as $pattern) {
        if (strpos($user_agent, $pattern) !== false) {
            return true;
        }
    }
    
    // 检测微信特有的请求头
    if (isset($_SERVER['HTTP_WX_OPENID']) || 
        isset($_SERVER['HTTP_WX_APPID']) ||
        isset($_SERVER['HTTP_X_WECHAT_OPENID'])) {
        return true;
    }
    
    // 检测微信小程序环境
    if (isset($_SERVER['HTTP_REFERER']) && 
        strpos(strtolower($_SERVER['HTTP_REFERER']), 'servicewechat.com') !== false) {
        return true;
    }
    
    return false;
}

// 检测是否为QQ内置浏览器 - 增强版
function is_qq_browser() {
    $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    
    // 检测多个QQ特征
    $qq_patterns = [
        'qq/',           // QQ浏览器
        'qzone/',        // QQ空间
        'qqbrowser',     // QQ浏览器
        'mqqbrowser',    // 手机QQ浏览器
        'qq_',           // QQ相关
        'qqtheme'        // QQ主题
    ];
    
    foreach ($qq_patterns as $pattern) {
        if (strpos($user_agent, $pattern) !== false) {
            return true;
        }
    }
    
    return false;
}

// 检测是否为微博内置浏览器
function is_weibo_browser() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return stripos($user_agent, 'weibo') !== false;
}

// 检测是否为钉钉内置浏览器
function is_dingtalk_browser() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return stripos($user_agent, 'dingtalk') !== false;
}

// 检测是否为支付宝内置浏览器
function is_alipay_browser() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return stripos($user_agent, 'alipayclient') !== false ||
           stripos($user_agent, 'alipay') !== false;
}

// 综合检测：是否需要防封处理
function needs_anti_block_redirect() {
    return is_wechat_browser() || 
           is_qq_browser() || 
           is_weibo_browser() || 
           is_dingtalk_browser() || 
           is_alipay_browser();
}

// 获取当前浏览器类型名称
function get_browser_name() {
    if (is_wechat_browser()) return '微信';
    if (is_qq_browser()) return 'QQ';
    if (is_weibo_browser()) return '微博';
    if (is_dingtalk_browser()) return '钉钉';
    if (is_alipay_browser()) return '支付宝';
    return '内置浏览器';
}

// 生成防封跳转URL
function generate_anti_block_url($target_url) {
    // 添加时间戳和随机参数来避免缓存
    $separator = strpos($target_url, '?') !== false ? '&' : '?';
    $timestamp = time();
    $random = mt_rand(1000, 9999);
    return $target_url . $separator . "t={$timestamp}&r={$random}";
}

// 获取真实访问IP（考虑代理情况）
function get_real_ip() {
    $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

// 记录访问日志（用于分析封域情况）
function log_browser_access($page_type = 'unknown') {
    if (!defined('LOG_BROWSER_ACCESS') || !LOG_BROWSER_ACCESS) {
        return; // 如果未启用日志记录，直接返回
    }
    
    $log_dir = __DIR__ . '/../logs/';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . 'browser_access_' . date('Y-m-d') . '.log';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $ip = get_real_ip();
    $timestamp = date('Y-m-d H:i:s');
    $browser_name = get_browser_name();
    $needs_redirect = needs_anti_block_redirect() ? 'YES' : 'NO';
    
    $log_entry = "[{$timestamp}] IP:{$ip} | Page:{$page_type} | Browser:{$browser_name} | NeedsRedirect:{$needs_redirect} | UA:{$user_agent}" . PHP_EOL;
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}
?>
