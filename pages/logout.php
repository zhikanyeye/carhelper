<?php
// 智能路径解析
$config_path = file_exists('../config/config.php') ? '../config/config.php' : __DIR__ . '/../config/config.php';
require_once $config_path;

// 安全启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_destroy();
header('Location: ' . SITE_URL . '/home');
exit;
?>
