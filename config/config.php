<?php
// 定义安全访问常量
define('SECURE_ACCESS', true);

// ============================================
// PHP安全配置优化（生产环境必备）
// ============================================

// 检测是否为生产环境（通过环境变量或域名判断）
$is_production = !in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', '::1']) 
                 && !preg_match('/\.(local|dev|test)$/', $_SERVER['HTTP_HOST']);

if ($is_production) {
    // 生产环境：关闭错误显示
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    
    // 设置错误日志路径
    $error_log_path = __DIR__ . '/../logs/php_errors.log';
    if (!file_exists(dirname($error_log_path))) {
        @mkdir(dirname($error_log_path), 0755, true);
    }
    ini_set('error_log', $error_log_path);
} else {
    // 开发环境：显示错误便于调试
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// 版本信息
define('APP_VERSION', '2.1.1');
define('APP_NAME', '车友帮讯联');

// 先定义会话配置常量
define('SESSION_LIFETIME', 7200); // 会话超时时间（秒）

// Session安全配置 - 增强版
ini_set('session.cookie_httponly', 1);  // 防止XSS攻击
ini_set('session.use_only_cookies', 1); // 只通过cookies传递session ID
ini_set('session.cookie_lifetime', SESSION_LIFETIME); // 会话生命周期
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);  // 垃圾回收时间
ini_set('session.cookie_samesite', 'Strict'); // 防止CSRF攻击
ini_set('session.use_strict_mode', 1); // 严格模式
ini_set('session.sid_length', 48); // 增强session ID长度
ini_set('session.sid_bits_per_character', 6); // 增加熵值

// 如果是HTTPS环境，启用安全cookie
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

// 网站配置 - 虚拟主机环境优化
// 自动检测协议和主机
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];

// 智能检测子目录 - 虚拟主机优化
$script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$request_uri = $_SERVER['REQUEST_URI'];

// 获取当前脚本的目录
$script_dir = dirname($script_name);

// 排除特殊目录（diagnostic, api, ajax, pages等）避免错误检测
$excluded_dirs = ['/diagnostic', '/api', '/ajax', '/uploads', '/qrcodes', '/logs', '/pages', '/cache'];
$is_excluded = false;
foreach ($excluded_dirs as $excluded) {
    if (strpos($script_dir, $excluded) !== false) {
        $is_excluded = true;
        break;
    }
}

// 智能基础路径检测
if ($is_excluded) {
    // 如果在排除目录中，向上查找基础路径
    $base_path = '';
    $path_parts = explode('/', trim($script_dir, '/'));
    if (count($path_parts) > 1) {
        array_pop($path_parts); // 移除最后一个目录
        $base_path = '/' . implode('/', $path_parts);
    }
} else {
    // 标准检测
    $base_path = ($script_dir === '/' || $script_dir === '\\') ? '' : $script_dir;
}

// 如果检测到配置文件所在的config目录，向上一级
if (basename($script_dir) === 'config') {
    $base_path = dirname($script_dir);
    if ($base_path === '/' || $base_path === '\\') {
        $base_path = '';
    }
}

define('SITE_URL', $protocol . $host . $base_path);
define('SITE_TITLE', '车友帮讯联');
define('SITE_DESCRIPTION', '方便快捷的车友匿名留言平台');

// 安全配置（SESSION_LIFETIME已在上面定义）
define('MAX_LOGIN_ATTEMPTS', 5);  // 最大登录尝试次数
define('LOGIN_COOLDOWN', 300);    // 登录冷却时间（秒）

// 留言限制
define('MAX_MESSAGE_LENGTH', 1000);  // 最大留言长度
define('MAX_CONTACT_LENGTH', 100);   // 最大联系方式长度
define('MESSAGE_RATE_LIMIT', 60);    // 留言频率限制（秒）

// 文件上传配置 - 安全增强版
define('UPLOAD_MAX_SIZE', 5242880);  // 5MB (适合虚拟主机)
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']); // 添加webp支持
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']); // MIME类型验证
define('UPLOAD_MAX_FILES', 5); // 单次最大上传文件数
define('UPLOAD_SCAN_VIRUS', false); // 病毒扫描（虚拟主机通常不支持）

// 邮件配置
define('EMAIL_ENABLED', true);
define('EMAIL_QUEUE', false); // 是否使用邮件队列

// 调试配置 - 生产环境安全设置
define('DEBUG_MODE', false); // 生产环境必须设为 false
define('LOG_ERRORS', true);
define('LOG_FILE', 'logs/app.log');

// 缓存配置 - 虚拟主机优化
define('CACHE_ENABLED', true);  // 启用文件缓存以提升性能
define('CACHE_LIFETIME', 3600); // 缓存1小时

// API配置
define('API_RATE_LIMIT', 100); // 每小时API调用限制
define('QR_CODE_API', 'google'); // 二维码API提供商

// 安全头部
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// 错误报告
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 包含数据库配置（由安装程序生成）
if (file_exists(__DIR__ . '/database.php')) {
    require_once __DIR__ . '/database.php';
}

// ============================================
// 防封系统配置
// ============================================

// 启用防封功能
define('ANTI_BLOCK_ENABLED', true);

// 启用浏览器访问日志记录
define('LOG_BROWSER_ACCESS', true);

// 防封白名单 - 这些页面不进行防封检测
define('ANTI_BLOCK_WHITELIST', [
    'login', '登录', 
    'register', '注册',
    'verify_email', '邮箱验证',
    'resend_verification', '重发验证',
    'reset_password', '重置密码',
    'admin', '管理员后台',
    'dashboard', '个人面板',
    'view_messages', '查看留言',
    'print_notice', '打印通知',
    'anti_block', // 防封页面本身
    'anti_block_admin' // 防封管理页面
]);

// 防封检测页面 - 只有这些页面进行防封检测
// 主要用于留言页面和首页（根域名）
define('ANTI_BLOCK_ROUTES', [
    'home', '首页',              // 首页/根域名
    'leave_message', '留言板'    // 留言页面（扫码后的页面）
]);

// 包含邮件配置
if (file_exists(__DIR__ . '/email.php')) {
    require_once __DIR__ . '/email.php';
}

// 包含缓存系统
if (defined('CACHE_ENABLED') && CACHE_ENABLED && file_exists(__DIR__ . '/../includes/cache.php')) {
    require_once __DIR__ . '/../includes/cache.php';
}

// 自动加载
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
?>
