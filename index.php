<?php
// 检查是否已安装
if (!file_exists('config/installed.lock')) {
    header('Location: install.php');
    exit;
}

// 加载配置文件
if (file_exists('config/config.php')) {
    require_once 'config/config.php';
} else {
    // 如果配置文件不存在，重定向到安装页面
    header('Location: install.php');
    exit;
}

require_once 'includes/functions.php';

// 防封检测 - 仅对特定页面进行检测
$need_anti_block_check = false;
$current_route = '';

// 简单的路由逻辑
// 优先从URL参数获取路由（.htaccess重写）
if (isset($_GET['url'])) {
    $route = trim($_GET['url'], '/');
} else {
    // 备用：从REQUEST_URI解析路由
    $request_uri = $_SERVER['REQUEST_URI'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    
    // 计算基础路径
    $base_path = dirname($script_name);
    if ($base_path === '/' || $base_path === '\\') {
        $base_path = '';
    }
    
    // 移除基础路径和查询字符串
    $route = strtok(substr($request_uri, strlen($base_path)), '?');
    $route = trim($route, '/');
}

// 如果路由为空，默认为首页
if (empty($route)) {
    $route = 'home';
}

$current_route = $route;

// 定义中英文路由映射
$route_mapping = [
    // 中文路由 => 英文页面文件
    '首页' => 'home',
    '登录' => 'login', 
    '注册' => 'register',
    '个人面板' => 'dashboard',
    '我的面板' => 'dashboard',
    '查看留言' => 'view_messages',
    '我的留言' => 'view_messages',
    '留言板' => 'leave_message',
    '给我留言' => 'leave_message',
    '邮箱验证' => 'verify_email',
    '重发验证' => 'resend_verification',
    '忘记密码' => 'forgot_password',
    '重置密码' => 'reset_password',
    '管理员后台' => 'admin',
    '后台管理' => 'admin',
    '防封管理' => 'anti_block_admin',
    '防封系统管理' => 'anti_block_admin',
    '系统诊断' => 'status',
    '系统状态' => 'status',
    '挪车通知单' => 'print_notice',
    
    // 保持英文路由的兼容性
    'home' => 'home',
    'login' => 'login',
    'register' => 'register', 
    'dashboard' => 'dashboard',
    'view_messages' => 'view_messages',
    'leave_message' => 'leave_message',
    'verify_email' => 'verify_email',
    'resend_verification' => 'resend_verification',
    'forgot_password' => 'forgot_password',
    'reset_password' => 'reset_password',
    'admin' => 'admin',
    'anti_block_admin' => 'anti_block_admin',
    'status' => 'status',
    'print_notice' => 'print_notice'
];

// URL解码以支持中文路径
$route = urldecode($route);

// 防封检测 - 使用配置文件中的设置
if (defined('ANTI_BLOCK_ENABLED') && ANTI_BLOCK_ENABLED && 
    needs_anti_block_redirect() && 
    defined('ANTI_BLOCK_ROUTES') && 
    in_array($route, ANTI_BLOCK_ROUTES)) {
    
    $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                   '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $encoded_url = urlencode($current_url);
    $anti_block_url = SITE_URL . '/pages/anti_block.php?target=' . $encoded_url;
    
    // 记录访问日志
    log_browser_access('route_' . $route);
    
    header("Location: " . $anti_block_url);
    exit;
}

// 特殊路由处理（登出）
if ($route === 'logout' || $route === '登出' || $route === '退出') {
    // 直接处理登出
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_destroy();
    header('Location: ' . SITE_URL . '/首页');
    exit;
}

// 获取实际的页面文件名
$actual_page = isset($route_mapping[$route]) ? $route_mapping[$route] : null;

// 特殊处理系统诊断页面
if ($route === '系统诊断' || $route === '系统状态' || $route === 'status') {
    header('Location: diagnostic/status.php');
    exit;
}

// 特殊处理挪车通知单页面（独立HTML页面）
if ($route === '挪车通知单' || $route === 'print_notice') {
    require_once 'pages/print_notice.php';
    exit;
}

// 定义允许的页面，防止任意文件包含
$allowed_pages = [
    'home',
    'login',
    'register',
    'dashboard',
    'view_messages',
    'leave_message',
    'verify_email',
    'resend_verification',
    'forgot_password',
    'reset_password',
    'admin',
    'anti_block_admin'
];

// 在输出任何内容之前，先执行页面的处理逻辑
if ($actual_page && in_array($actual_page, $allowed_pages)) {
    $page_file = 'pages/' . $actual_page . '.php';
    if (file_exists($page_file)) {
        // 先包含页面文件以执行其中的处理逻辑（如重定向）
        // 使用 require_once 而不是 include，这样可以在重定向时正常退出
        ob_start();
        require_once $page_file;
        $page_content = ob_get_clean();
        
        // 如果执行到这里，说明没有重定向，可以正常显示页面
        if (!is_ajax_request()) {
            // 设置中文页面标题
            $chinese_titles = [
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
                'anti_block_admin' => '防封系统管理',
                'print_notice' => '挪车通知单'
            ];
            $page_title = isset($chinese_titles[$actual_page]) ? $chinese_titles[$actual_page] : ucfirst($actual_page);
            include 'includes/header.php';
        }
        
        echo $page_content;
        
        if (!is_ajax_request()) {
            include 'includes/footer.php';
        }
    } else {
        // 文件不存在，显示404
        http_response_code(404);
        if (!is_ajax_request()) {
            include 'includes/header.php';
        }
        echo '<div class="container"><p>页面未找到。</p></div>';
        if (!is_ajax_request()) {
            include 'includes/footer.php';
        }
    }
} else {
    // 路由不允许，显示404
    http_response_code(404);
    if (!is_ajax_request()) {
        include 'includes/header.php';
    }
    echo '<div class="container"><p>页面未找到。</p></div>';
    if (!is_ajax_request()) {
        include 'includes/footer.php';
    }
}

// 检查是否是AJAX请求的辅助函数
function is_ajax_request() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}
