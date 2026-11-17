<?php
// 确保 SITE_URL 常量已定义，如果没有则设置默认值
if (!defined('SITE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $dir_name = dirname($script_name);
    $base_path = ($dir_name === '/' || $dir_name === '\\') ? '' : $dir_name;
    define('SITE_URL', $protocol . $host . $base_path);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : '车友帮讯联 - 便捷的车友留言平台'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo SITE_URL; ?>/css/style.css" rel="stylesheet">
    
    <!-- 网站图标 -->
    <link rel="icon" type="image/svg+xml" href="<?php echo SITE_URL; ?>/assets/logo.svg">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="<?php echo SITE_URL; ?>/">
                <img src="<?php echo SITE_URL; ?>/assets/logo.svg" width="24" height="24" class="me-2" alt="Logo">
                车友帮讯联
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo url('首页'); ?>">首页</a>
                    </li>
                    <?php if (is_logged_in()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo url('个人面板'); ?>">我的面板</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo url('查看留言'); ?>">
                            查看留言
                            <?php 
                            $unread_count = get_unread_message_count($_SESSION['user_id']);
                            if ($unread_count > 0): 
                            ?>
                            <span class="badge bg-danger"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if (is_logged_in()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                            <?php if (is_admin()): ?>
                            <span class="badge bg-danger ms-1">管理员</span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo url('个人面板'); ?>">
                                <i class="bi bi-speedometer2"></i> 个人中心
                            </a></li>
                            <?php if (is_admin()): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header text-danger">
                                <i class="bi bi-shield-check"></i> 管理员功能
                            </h6></li>
                            <li><a class="dropdown-item" href="<?php echo url('管理员后台'); ?>">
                                <i class="bi bi-gear"></i> 管理后台
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo url('系统诊断'); ?>">
                                <i class="bi bi-clipboard-check"></i> 系统状态
                            </a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo url('退出'); ?>">
                                <i class="bi bi-box-arrow-right"></i> 退出登录
                            </a></li>
                        </ul>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo url('登录'); ?>">登录</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo url('注册'); ?>">注册</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <main class="container mt-4">
