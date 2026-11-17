<?php
// pages/print_notice.php
// 挪车/临时停车通知单打印页面

// 启动 session 并加载配置
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
// 通过路由系统访问时，配置文件已经加载
// 如果直接访问，则需要加载配置
if (!defined('SITE_URL')) {
  $config_path = file_exists('../config/config.php') ? '../config/config.php' : __DIR__ . '/../config/config.php';
  $functions_path = file_exists('../includes/functions.php') ? '../includes/functions.php' : __DIR__ . '/../includes/functions.php';
  require_once $config_path;
  require_once $functions_path;
}

// 权限校验，必须登录
if (!isset($_SESSION['user_id'])) {
  if (defined('SITE_URL')) {
    header('Location: ' . SITE_URL . '/login');
  } else {
    header('Location: ../login');
  }
  exit;
}

$user_id = $_SESSION['user_id'];

$user = get_current_system_user();
if (!$user) {
  die('用户信息获取失败');
}

// 二维码图片路径 - 修复路径匹配问题
$qrcode_path = '';
$qr_exists = false;

// 首先检查基于UUID的文件（SVG格式）
if (!empty($user['uuid'])) {
  $uuid_file = $user['uuid'] . '.svg';
  if (file_exists(__DIR__ . '/../qrcodes/' . $uuid_file)) {
    $qr_exists = true;
    $qrcode_path = (defined('SITE_URL') ? SITE_URL : '') . '/qrcodes/' . $uuid_file;
  }
}

// 如果UUID文件不存在，检查数据库中记录的文件
if (!$qr_exists && !empty($user['qr_code']) && file_exists(__DIR__ . '/../qrcodes/' . $user['qr_code'])) {
  $qr_exists = true;
  $qrcode_path = (defined('SITE_URL') ? SITE_URL : '') . '/qrcodes/' . $user['qr_code'];
}

// 如果都没找到，尝试查找任何以UUID开头的二维码文件
if (!$qr_exists && !empty($user['uuid'])) {
  $qrcodes_dir = __DIR__ . '/../qrcodes/';
  if (is_dir($qrcodes_dir)) {
    $files = glob($qrcodes_dir . 'qr_' . $user['uuid'] . '_*.png');
    if (!empty($files)) {
      $qr_exists = true;
      $qrcode_path = (defined('SITE_URL') ? SITE_URL : '') . '/qrcodes/' . basename($files[0]);
    }
  }
}

if (!$qr_exists) {
  die('二维码未生成，请先在个人面板中生成二维码');
}

// 用户信息
$username = htmlspecialchars($user['username']);

?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>联系挪车通知单</title>
  <!-- PDF生成库 -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <style>
    /* CSS变量系统 - 简化版 */
    :root {
      /* 主色调系统 */
      --primary-color: #667eea;
      --primary-dark: #5a6fd8;
      --primary-light: #8fa4f3;

      /* 功能色系统 */
      --success-color: #4caf50;
      --warning-color: #ff9800;
      --error-color: #f44336;
      --info-color: #2196f3;

      /* 中性色系统 */
      --text-primary: #2c3e50;
      --text-secondary: #7f8c8d;
      --text-muted: #95a5a6;
      --background-primary: #ffffff;
      --background-secondary: #f8f9fa;
      --background-tertiary: #ecf0f1;
      --border-color: #e9ecef;
      --border-light: #f1f3f4;

      /* 字体系统 */
      --font-family-primary: 'Microsoft YaHei', 'PingFang SC', 'Helvetica Neue', Arial, sans-serif;
      --font-size-xs: 0.75rem;
      --font-size-sm: 0.875rem;
      --font-size-base: 1rem;
      --font-size-lg: 1.125rem;
      --font-size-xl: 1.25rem;
      --font-size-2xl: 1.5rem;
      --font-size-3xl: 1.875rem;

      /* 间距系统 */
      --spacing-xs: 0.25rem;
      --spacing-sm: 0.5rem;
      --spacing-md: 1rem;
      --spacing-lg: 1.5rem;
      --spacing-xl: 2rem;
      --spacing-2xl: 3rem;
      --spacing-3xl: 4rem;

      /* 阴影系统 */
      --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
      --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
      --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
      --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.15);

      /* 圆角系统 */
      --radius-sm: 0.25rem;
      --radius-md: 0.5rem;
      --radius-lg: 0.75rem;
      --radius-xl: 1rem;

      /* 动画系统 */
      --transition-base: 300ms ease-out;
      --transition-fast: 150ms ease-out;
      --transition-slow: 500ms ease-out;

      /* 缓动函数 */
      --ease-out-quart: cubic-bezier(0.25, 1, 0.5, 1);
      --ease-in-out-quart: cubic-bezier(0.76, 0, 0.24, 1);
      --ease-out-back: cubic-bezier(0.34, 1.56, 0.64, 1);
      --ease-spring: cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }

    /* 全局样式重置和基础设置 */
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 0;
      font-family: var(--font-family-primary);
      background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
      line-height: 1.6;
      color: var(--text-primary);
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* 页面加载动画 */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
      }
      to {
        opacity: 1;
      }
    }

    @keyframes slideInDown {
      from {
        opacity: 0;
        transform: translateY(-30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes scaleIn {
      from {
        opacity: 0;
        transform: scale(0.9);
      }
      to {
        opacity: 1;
        transform: scale(1);
      }
    }

    @keyframes pulse {
      0%, 100% {
        transform: scale(1);
      }
      50% {
        transform: scale(1.05);
      }
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      25% { transform: translateX(-5px); }
      75% { transform: translateX(5px); }
    }

    @keyframes ripple {
      0% {
        transform: scale(0);
        opacity: 1;
      }
      100% {
        transform: scale(2);
        opacity: 0;
      }
    }

    /* 页面容器 - 简化设计 */
    .page-container {
      max-width: 1000px;
      margin: var(--spacing-lg) auto;
      background: var(--background-primary);
      min-height: calc(100vh - 3rem);
      display: flex;
      flex-direction: column;
      border-radius: var(--radius-xl);
      box-shadow: var(--shadow-md);
      overflow: hidden;
      position: relative;
      animation: fadeInUp 0.6s var(--ease-out-quart);
    }

    /* 简化工具栏设计 */
    .edit-toolbar {
      background: var(--primary-color);
      color: white;
      padding: var(--spacing-lg) var(--spacing-xl);
      box-shadow: var(--shadow-md);
      display: flex;
      align-items: center;
      gap: var(--spacing-lg);
      flex-wrap: wrap;
      position: relative;
      animation: slideInDown 0.5s var(--ease-out-quart);
    }

    .toolbar-title {
      font-size: var(--font-size-xl);
      font-weight: 700;
      margin-right: auto;
      display: flex;
      align-items: center;
      gap: var(--spacing-sm);
    }

    .toolbar-section {
      display: flex;
      align-items: center;
      gap: var(--spacing-sm);
      padding: var(--spacing-sm) var(--spacing-md);
      background: rgba(255, 255, 255, 0.1);
      border-radius: var(--radius-lg);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .btn {
      padding: var(--spacing-sm) var(--spacing-md);
      border: 1px solid rgba(255, 255, 255, 0.3);
      border-radius: var(--radius-md);
      cursor: pointer;
      font-size: var(--font-size-sm);
      font-weight: 500;
      transition: all var(--transition-base);
      background: rgba(255, 255, 255, 0.1);
      color: white;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: var(--spacing-xs);
      white-space: nowrap;
      position: relative;
      overflow: hidden;
    }

    /* 按钮波纹效果 */
    .btn::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.3);
      transform: translate(-50%, -50%);
      transition: width 0.6s, height 0.6s;
    }

    .btn:active::before {
      width: 300px;
      height: 300px;
      transition: width 0s, height 0s;
    }

    .btn:hover {
      background: rgba(255, 255, 255, 0.25);
      color: white;
      text-decoration: none;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
      border-color: rgba(255, 255, 255, 0.5);
      animation: pulse 0.6s ease-in-out;
    }

    .btn:active {
      transform: translateY(0) scale(0.98);
      transition: transform 0.1s;
    }

    .btn-active {
      background: rgba(255, 255, 255, 0.3);
      border-color: rgba(255, 255, 255, 0.6);
      box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
      animation: pulse 0.3s ease-in-out;
    }

    /* 内容区域 */
    .content-area {
      flex: 1;
      padding: var(--spacing-3xl) var(--spacing-2xl);
      background: var(--background-primary);
      position: relative;
      animation: fadeIn 0.8s var(--ease-out-quart) 0.2s both;
    }

    /* 系统Logo */
    .system-logo {
      text-align: center;
      margin-bottom: var(--spacing-2xl);
      padding-bottom: var(--spacing-lg);
      position: relative;
    }

    .system-logo::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 80px;
      height: 3px;
      background: var(--primary-color);
      border-radius: var(--radius-sm);
    }

    .system-logo img {
      width: 80px;
      height: 80px;
      margin-bottom: var(--spacing-md);
      filter: drop-shadow(var(--shadow-md));
      transition: transform var(--transition-base);
      animation: scaleIn 0.5s var(--ease-out-back) 0.3s both;
    }

    .system-logo img:hover {
      transform: scale(1.1) rotate(5deg);
    }

    .system-name {
      font-size: var(--font-size-xl);
      font-weight: 700;
      color: var(--primary-color);
      margin-bottom: var(--spacing-xs);
    }

    .notice-title {
      font-size: var(--font-size-3xl);
      color: var(--primary-color);
      margin-bottom: var(--spacing-2xl);
      font-weight: 800;
      text-align: center;
      position: relative;
    }

    .notice-content {
      font-size: var(--font-size-lg);
      color: var(--text-primary);
      line-height: 1.8;
      margin-bottom: var(--spacing-2xl);
      text-align: left;
      max-width: 700px;
      margin-left: auto;
      margin-right: auto;
      min-height: 150px;
      padding: var(--spacing-xl);
      border: 2px solid transparent;
      border-radius: var(--radius-xl);
      transition: all var(--transition-base);
      background: var(--background-secondary);
      position: relative;
    }

    .notice-content.editable {
      border: 2px dashed var(--primary-color);
      background: var(--background-primary);
      cursor: text;
      box-shadow: var(--shadow-md);
      animation: pulse 0.5s ease-in-out;
    }

    .notice-content.editable:focus {
      outline: none;
      background: var(--background-primary);
      box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1), var(--shadow-lg);
      border-color: var(--primary-color);
      animation: none;
    }

    /* 内容更新动画 */
    .notice-content.updating {
      animation: pulse 0.3s ease-in-out;
    }

    .desc {
      font-weight: 700;
      margin-bottom: var(--spacing-md);
      color: var(--primary-color);
    }

    /* 二维码区域 */
    .qr-section {
      margin: var(--spacing-2xl) 0;
      text-align: center;
    }

    .qr-instruction {
      font-size: var(--font-size-xl);
      font-weight: 700;
      color: var(--primary-color);
      margin-bottom: var(--spacing-lg);
      position: relative;
    }

    .qrcode-area {
      display: inline-block;
      padding: var(--spacing-xl);
      background: var(--background-primary);
      border: 3px solid var(--primary-color);
      border-radius: var(--radius-xl);
      box-shadow: var(--shadow-md);
      position: relative;
      transition: all var(--transition-base);
      animation: scaleIn 0.6s var(--ease-out-back) 0.5s both;
    }

    .qrcode-area:hover {
      transform: translateY(-2px) scale(1.02);
      box-shadow: var(--shadow-lg);
    }

    .qrcode-area img {
      width: 200px;
      height: 200px;
      display: block;
      border-radius: var(--radius-md);
    }

    /* 页脚设计 */
    .notice-footer {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      margin-top: var(--spacing-3xl);
      padding-top: var(--spacing-xl);
      border-top: 2px solid var(--border-light);
      font-size: var(--font-size-sm);
      color: var(--text-secondary);
      position: relative;
    }

    .notice-footer::before {
      content: '';
      position: absolute;
      top: -2px;
      left: 0;
      width: 100px;
      height: 2px;
      background: var(--primary-color);
      border-radius: var(--radius-sm);
    }

    .watermark {
      text-align: left;
      padding: var(--spacing-md);
      background: var(--background-secondary);
      border-radius: var(--radius-md);
      border-left: 4px solid var(--primary-color);
    }

    .owner-info {
      text-align: right;
      padding: var(--spacing-md);
      background: var(--background-secondary);
      border-radius: var(--radius-md);
      border-right: 4px solid var(--accent-color);
    }

    .hr-divider {
      border: none;
      height: 2px;
      background: linear-gradient(90deg, transparent, var(--primary-color), transparent);
      margin: var(--spacing-xl) 0;
      border-radius: var(--radius-sm);
    }

    /* 打印样式优化 */
    @media print {
      body {
        background: #fff !important;
        margin: 0 !important;
      }

      .edit-toolbar {
        display: none !important;
      }

      .page-container {
        max-width: none !important;
        box-shadow: none !important;
        width: 210mm !important;
        height: 297mm !important;
        margin: 0 !important;
        border-radius: 0 !important;
      }

      .content-area {
        padding: 20mm !important;
        page-break-inside: avoid !important;
        width: 170mm !important;
        height: 257mm !important;
      }

      .system-logo img {
        width: 18mm !important;
        height: 18mm !important;
        filter: none !important;
      }

      .system-name {
        font-size: 14pt !important;
        color: #333 !important;
      }

      .notice-title {
        font-size: 18pt !important;
        color: #333 !important;
      }

      .notice-content {
        font-size: 12pt !important;
        border: none !important;
        background: transparent !important;
        box-shadow: none !important;
      }

      .qrcode-area {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
      }

      .qrcode-area img {
        width: 35mm !important;
        height: 35mm !important;
      }
    }

    /* 响应式设计 */
    @media (max-width: 1024px) {
      .page-container {
        margin: var(--spacing-md);
        border-radius: var(--radius-lg);
      }

      .content-area {
        padding: var(--spacing-2xl) var(--spacing-xl);
      }
    }

    @media (max-width: 768px) {
      .page-container {
        margin: var(--spacing-sm);
        border-radius: var(--radius-md);
      }

      .edit-toolbar {
        padding: var(--spacing-md);
        flex-direction: column;
        align-items: stretch;
        gap: var(--spacing-md);
      }

      .toolbar-title {
        margin-right: 0;
        text-align: center;
        font-size: var(--font-size-lg);
      }

      .toolbar-section {
        justify-content: center;
        flex-wrap: wrap;
        gap: var(--spacing-xs);
      }

      .content-area {
        padding: var(--spacing-xl) var(--spacing-lg);
      }

      .notice-title {
        font-size: var(--font-size-2xl);
      }

      .notice-content {
        font-size: var(--font-size-base);
        padding: var(--spacing-lg);
      }

      .qrcode-area img {
        width: 160px;
        height: 160px;
      }

      .notice-footer {
        flex-direction: column;
        gap: var(--spacing-md);
        text-align: center;
      }

      .watermark,
      .owner-info {
        text-align: center;
        border-left: none;
        border-right: none;
        border-bottom: 4px solid var(--primary-color);
      }

      .btn-text {
        display: none;
      }

      .btn {
        min-width: 40px;
        padding: var(--spacing-sm);
      }
    }

    @media (max-width: 480px) {
      .edit-toolbar {
        padding: var(--spacing-sm);
      }

      .content-area {
        padding: var(--spacing-lg) var(--spacing-md);
      }

      .system-logo img {
        width: 60px;
        height: 60px;
      }

      .notice-title {
        font-size: var(--font-size-xl);
      }

      .qrcode-area {
        padding: var(--spacing-lg);
      }

      .qrcode-area img {
        width: 140px;
        height: 140px;
      }

      .btn {
        padding: var(--spacing-xs) var(--spacing-sm);
        font-size: var(--font-size-xs);
      }

      .toolbar-section {
        flex-wrap: wrap;
        justify-content: center;
      }
    }

    /* 必要的UI组件样式 */
    .btn-icon {
      font-size: var(--font-size-base);
      margin-right: var(--spacing-xs);
    }

    .btn-text {
      font-size: var(--font-size-sm);
      font-weight: 500;
    }

    .btn-divider {
      width: 1px;
      height: 20px;
      background: rgba(255, 255, 255, 0.3);
      margin: 0 var(--spacing-xs);
    }

    /* 加载动画 */
    .loading-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(255, 255, 255, 0.9);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      opacity: 0;
      visibility: hidden;
      transition: all var(--transition-base);
    }

    .loading-overlay.show {
      opacity: 1;
      visibility: visible;
    }

    .loading-spinner {
      width: 50px;
      height: 50px;
      border: 4px solid var(--border-color);
      border-top: 4px solid var(--primary-color);
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    /* 通知提示样式 */
    .notification {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: var(--spacing-md) var(--spacing-lg);
      background: var(--background-primary);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-md);
      border-left: 4px solid var(--success-color);
      z-index: 1000;
      transform: translateX(400px);
      transition: transform var(--transition-base);
      max-width: 300px;
    }

    .notification.show {
      transform: translateX(0);
    }

    .notification.error {
      border-left-color: var(--error-color);
    }

    .notification.warning {
      border-left-color: var(--warning-color);
    }

    .notification.info {
      border-left-color: var(--info-color);
    }

    .format-btn.active {
      background: rgba(255, 255, 255, 0.4);
      border-color: rgba(255, 255, 255, 0.7);
      box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .print-btn:hover {
      background: rgba(76, 175, 80, 0.2);
      border-color: rgba(76, 175, 80, 0.5);
    }

    .pdf-btn:hover {
      background: rgba(244, 67, 54, 0.2);
      border-color: rgba(244, 67, 54, 0.5);
    }

    .back-btn:hover {
      background: rgba(255, 152, 0, 0.2);
      border-color: rgba(255, 152, 0, 0.5);
    }

    /* 新增文本样式 */
    .notice-content h3 {
      font-size: var(--font-size-xl);
      font-weight: 700;
      color: var(--primary-color);
      margin: var(--spacing-md) 0;
      border-bottom: 2px solid var(--primary-color);
      padding-bottom: var(--spacing-xs);
    }

    .notice-content .indent {
      text-indent: 2em;
    }

    .notice-content .line-break {
      margin-bottom: var(--spacing-md);
    }

    /* 工具提示样式 */
    .btn[title]:hover::after {
      content: attr(title);
      position: absolute;
      bottom: -35px;
      left: 50%;
      transform: translateX(-50%);
      background: rgba(0, 0, 0, 0.8);
      color: white;
      padding: var(--spacing-xs) var(--spacing-sm);
      border-radius: var(--radius-sm);
      font-size: var(--font-size-xs);
      white-space: nowrap;
      z-index: 1000;
      pointer-events: none;
    }

    /* 格式化内容样式 */
    .notice-content strong {
      font-weight: 700;
      color: var(--primary-color);
    }

    .notice-content em {
      font-style: italic;
      color: var(--secondary-color);
    }

    .notice-content u {
      text-decoration: underline;
      text-decoration-color: var(--accent-color);
    }

    .notice-content .text-center {
      text-align: center;
    }

    .notice-content .text-left {
      text-align: left;
    }

    .notice-content .text-right {
      text-align: right;
    }

    /* 选择文本样式 */
    .notice-content::selection {
      background: rgba(102, 126, 234, 0.2);
      color: var(--text-primary);
    }

    /* 编辑模式特殊样式 */
    .notice-content.editable {
      position: relative;
    }

    /* 响应式工具栏优化 */
    @media (max-width: 768px) {
      .btn-text {
        display: none;
      }

      .btn {
        min-width: 40px;
        padding: var(--spacing-sm);
      }

      .toolbar-section {
        gap: var(--spacing-xs);
      }

      .btn[title]:hover::after {
        display: none;
      }
    }

    @media (max-width: 480px) {
      .toolbar-section {
        flex-wrap: wrap;
        justify-content: center;
      }

      .emoji-btn {
        min-width: 35px;
        font-size: var(--font-size-base);
      }
    }

    /* 无障碍访问优化 */
    .sr-only {
      position: absolute;
      width: 1px;
      height: 1px;
      padding: 0;
      margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap;
      border: 0;
    }

    /* 焦点指示器 */
    .btn:focus,
    .notice-content:focus {
      outline: 2px solid var(--primary-color);
      outline-offset: 2px;
    }

    /* 高对比度模式支持 */
    @media (prefers-contrast: high) {
      :root {
        --primary-color: #000080;
        --text-primary: #000000;
        --background-primary: #ffffff;
        --border-color: #000000;
      }
    }

    /* 减少动画模式支持 */
    @media (prefers-reduced-motion: reduce) {

      *,
      *::before,
      *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
      }
    }
  </style>
</head>

<body>
  <div class="page-container">
    <!-- 实用编辑工具栏 -->
    <div class="edit-toolbar">
      <div class="toolbar-title">联系挪车通知单</div>

      <!-- 编辑控制区 -->
      <div class="toolbar-section">
        <button class="btn" onclick="toggleEditMode()" id="editBtn" title="切换编辑模式 (Ctrl+E)">
          <span class="btn-icon">✏️</span>
          <span class="btn-text">编辑内容</span>
        </button>
        <button class="btn" onclick="resetDefault()" title="恢复默认内容">
          <span class="btn-icon">🔄</span>
          <span class="btn-text">恢复默认</span>
        </button>
      </div>

      <!-- 文本格式化工具区 -->
      <div class="toolbar-section" id="formatSection" style="display: none;">
        <button class="btn format-btn" onclick="applyFormat('bold')" title="粗体 (Ctrl+B)">
          <span class="btn-icon">𝐁</span>
        </button>
        <button class="btn format-btn" onclick="applyFormat('italic')" title="斜体 (Ctrl+I)">
          <span class="btn-icon">𝐼</span>
        </button>
        <button class="btn format-btn" onclick="applyFormat('underline')" title="下划线 (Ctrl+U)">
          <span class="btn-icon">U̲</span>
        </button>
        <button class="btn format-btn" onclick="applyFormat('strikeThrough')" title="删除线">
          <span class="btn-icon">S̶</span>
        </button>
        <div class="btn-divider"></div>
        <button class="btn format-btn" onclick="applyFormat('justifyLeft')" title="左对齐">
          <span class="btn-icon">⬅️</span>
        </button>
        <button class="btn format-btn" onclick="applyFormat('justifyCenter')" title="居中对齐">
          <span class="btn-icon">↔️</span>
        </button>
        <button class="btn format-btn" onclick="applyFormat('justifyRight')" title="右对齐">
          <span class="btn-icon">➡️</span>
        </button>
      </div>

      <!-- 颜色和列表工具区 -->
      <div class="toolbar-section" id="colorSection" style="display: none;">
        <button class="btn" onclick="applyColor('#000000')" title="黑色文字">
          <span class="btn-icon" style="color: #000000;">●</span>
        </button>
        <button class="btn" onclick="applyColor('#667eea')" title="蓝色文字">
          <span class="btn-icon" style="color: #667eea;">●</span>
        </button>
        <button class="btn" onclick="applyColor('#dc3545')" title="红色文字">
          <span class="btn-icon" style="color: #dc3545;">●</span>
        </button>
        <button class="btn" onclick="applyColor('#28a745')" title="绿色文字">
          <span class="btn-icon" style="color: #28a745;">●</span>
        </button>
        <div class="btn-divider"></div>
        <button class="btn format-btn" onclick="applyFormat('insertUnorderedList')" title="无序列表">
          <span class="btn-icon">•</span>
          <span class="btn-text">列表</span>
        </button>
        <button class="btn format-btn" onclick="applyFormat('insertOrderedList')" title="有序列表">
          <span class="btn-icon">1.</span>
          <span class="btn-text">编号</span>
        </button>
        <button class="btn" onclick="insertHR()" title="分割线">
          <span class="btn-icon">─</span>
          <span class="btn-text">分割线</span>
        </button>
      </div>

      <!-- 实用文本编辑工具区 -->
      <div class="toolbar-section" id="textTools" style="display: none;">
        <button class="btn" onclick="addHeading('h3')" title="三级标题">
          <span class="btn-icon">H3</span>
          <span class="btn-text">标题</span>
        </button>
        <button class="btn" onclick="addIndent()" title="首行缩进">
          <span class="btn-icon">↹</span>
          <span class="btn-text">缩进</span>
        </button>
        <button class="btn" onclick="addLineBreak()" title="段落分行">
          <span class="btn-icon">⏎</span>
          <span class="btn-text">分行</span>
        </button>
        <div class="btn-divider"></div>
        <button class="btn" onclick="undo()" title="撤销 (Ctrl+Z)">
          <span class="btn-icon">↶</span>
          <span class="btn-text">撤销</span>
        </button>
        <button class="btn" onclick="redo()" title="重做 (Ctrl+Shift+Z)">
          <span class="btn-icon">↷</span>
          <span class="btn-text">重做</span>
        </button>
        <button class="btn" onclick="removeFormat()" title="清除格式">
          <span class="btn-icon">🧹</span>
          <span class="btn-text">清除</span>
        </button>
      </div>

      <!-- 输出操作区 -->
      <div class="toolbar-section">
        <button class="btn print-btn" onclick="window.print()" title="打印通知单 (Ctrl+P)">
          <span class="btn-icon">🖨️</span>
          <span class="btn-text">打印</span>
        </button>
        <button class="btn pdf-btn" onclick="downloadPDF()" title="下载PDF文件">
          <span class="btn-icon">📄</span>
          <span class="btn-text">下载PDF</span>
        </button>
      </div>

      <!-- 导航区 -->
      <div class="toolbar-section">
        <a href="<?php echo defined('SITE_URL') ? SITE_URL . '/dashboard' : '../dashboard'; ?>" class="btn back-btn" title="返回个人面板">
          <span class="btn-icon">←</span>
          <span class="btn-text">返回面板</span>
        </a>
      </div>
    </div>

    <!-- 内容区域 -->
    <div class="content-area" id="contentArea">
      <!-- 系统Logo -->
      <div class="system-logo">
        <img src="<?php echo (defined('SITE_URL') ? SITE_URL : '') . '/assets/logo.svg'; ?>" alt="车友帮讯联系统" />
        <div class="system-name">车友帮讯联系系统</div>
      </div>

      <!-- 通知单标题 -->
      <h1 class="notice-title">联系挪车通知单</h1>

      <!-- 通知内容 -->
      <div class="notice-content" id="noticeContent" contenteditable="false">
        <div class="desc">尊敬的朋友：</div>
        <div>因临时停车或特殊情况，给您带来不便敬请谅解。</div>
        <div>如需联系车主，请扫描下方二维码，系统将自动匿名转达您的留言，感谢您的理解与配合！</div>
      </div>

      <hr class="hr-divider">

      <!-- 二维码区域 -->
      <div class="qr-section">
        <div class="qr-instruction">请扫描下方二维码联系车主</div>
        <div class="qrcode-area">
          <img src="<?php echo $qrcode_path; ?>" alt="车主二维码">
        </div>
      </div>

      <!-- 页脚信息 -->
      <div class="notice-footer">
        <div class="watermark">
          <strong>车友帮讯联系系统</strong><br>
          本通知单由系统自动生成<br>
          严禁用于商业用途
        </div>
        <div class="owner-info">
          车主昵称：<?php echo $username; ?><br>
          生成时间：<?php echo date('Y年m月d日'); ?>
        </div>
      </div>
    </div>
  </div>

  <script>
    // 简化版编辑器 - 专注实用功能
    class NoticeEditor {
      constructor() {
        this.isEditMode = false;
        this.noticeContent = document.getElementById('noticeContent');
        this.editBtn = document.getElementById('editBtn');
        this.history = [];
        this.historyIndex = -1;
        this.maxHistory = 20;

        // 默认内容
        this.defaultContent = `
          <div class="desc">尊敬的朋友：</div>
          <div>因临时停车或特殊情况，给您带来不便敬请谅解。</div>
          <div>如需联系车主，请扫描下方二维码，系统将自动匿名转达您的留言，感谢您的理解与配合！</div>
        `;

        this.init();
      }

      init() {
        this.setupEventListeners();
        this.saveToHistory();
        this.setupAutoSave();
      }

      // 设置事件监听器
      setupEventListeners() {
        // 键盘快捷键
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));

        // 内容变化监听
        this.noticeContent.addEventListener('input', () => {
          this.debounce(() => this.saveToHistory(), 1000)();
          if (this.isEditMode) {
            this.updateFormatButtons();
          }
        });

        // 选择变化监听
        document.addEventListener('selectionchange', () => {
          if (this.isEditMode && document.activeElement === this.noticeContent) {
            this.updateFormatButtons();
          }
        });

        // 点击提示
        this.noticeContent.addEventListener('click', (e) => this.handleContentClick(e));
      }

      // 切换编辑模式
      toggleEditMode() {
        this.isEditMode = !this.isEditMode;

        if (this.isEditMode) {
          this.enterEditMode();
        } else {
          this.exitEditMode();
        }

        // 切换工具栏显示
        this.toggleToolbarSections();
      }

      // 切换工具栏区域显示
      toggleToolbarSections() {
        const formatSection = document.getElementById('formatSection');
        const colorSection = document.getElementById('colorSection');
        const textTools = document.getElementById('textTools');

        if (this.isEditMode) {
          formatSection.style.display = 'flex';
          colorSection.style.display = 'flex';
          textTools.style.display = 'flex';
          // 添加淡入动画
          [formatSection, colorSection, textTools].forEach((section, index) => {
            section.style.animation = `fadeIn 0.3s ease-out ${index * 0.1}s both`;
          });
        } else {
          formatSection.style.display = 'none';
          colorSection.style.display = 'none';
          textTools.style.display = 'none';
        }
      }

      // 应用格式化
      applyFormat(command, value = null) {
        if (!this.isEditMode) {
          this.showNotification('请先进入编辑模式', 'warning');
          return;
        }

        this.noticeContent.focus();

        try {
          document.execCommand(command, false, value);
          this.updateFormatButtons();
          this.saveToHistory();

          // 添加内容更新动画
          this.noticeContent.classList.add('updating');
          setTimeout(() => {
            this.noticeContent.classList.remove('updating');
          }, 300);

          this.showNotification('格式已应用', 'success');
        } catch (error) {
          console.error('格式化失败:', error);
          this.showNotification('格式化失败', 'error');
        }
      }

      // 应用颜色
      applyColor(color) {
        this.applyFormat('foreColor', color);
      }

      // 插入分割线
      insertHR() {
        if (!this.isEditMode) {
          this.showNotification('请先进入编辑模式', 'warning');
          return;
        }
        this.applyFormat('insertHorizontalRule');
      }

      // 撤销
      undo() {
        if (!this.isEditMode) {
          this.showNotification('请先进入编辑模式', 'warning');
          return;
        }

        if (this.historyIndex > 0) {
          this.historyIndex--;
          this.noticeContent.innerHTML = this.history[this.historyIndex];
          this.showNotification('已撤销', 'info');
        } else {
          this.showNotification('没有可撤销的操作', 'warning');
        }
      }

      // 重做
      redo() {
        if (!this.isEditMode) {
          this.showNotification('请先进入编辑模式', 'warning');
          return;
        }

        if (this.historyIndex < this.history.length - 1) {
          this.historyIndex++;
          this.noticeContent.innerHTML = this.history[this.historyIndex];
          this.showNotification('已重做', 'info');
        } else {
          this.showNotification('没有可重做的操作', 'warning');
        }
      }

      // 更新格式化按钮状态
      updateFormatButtons() {
        const commands = ['bold', 'italic', 'underline', 'strikeThrough', 'justifyLeft', 'justifyCenter', 'justifyRight', 'insertUnorderedList', 'insertOrderedList'];

        commands.forEach(command => {
          const button = document.querySelector(`[onclick*="applyFormat('${command}')"]`);
          if (button) {
            if (document.queryCommandState(command)) {
              button.classList.add('active');
            } else {
              button.classList.remove('active');
            }
          }
        });
      }

      // 进入编辑模式
      enterEditMode() {
        this.noticeContent.contentEditable = 'true';
        this.noticeContent.classList.add('editable');
        this.editBtn.innerHTML = '<span class="btn-icon">💾</span><span class="btn-text">保存内容</span>';
        this.editBtn.classList.add('btn-active');

        setTimeout(() => {
          this.noticeContent.focus();
          this.setCursorToEnd();
        }, 100);

        this.showNotification('✏️ 编辑模式已开启，使用工具栏美化您的通知单', 'info');
      }

      // 退出编辑模式
      exitEditMode() {
        this.noticeContent.contentEditable = 'false';
        this.noticeContent.classList.remove('editable');
        this.editBtn.innerHTML = '<span class="btn-icon">✏️</span><span class="btn-text">编辑内容</span>';
        this.editBtn.classList.remove('btn-active');
        this.noticeContent.blur();

        this.saveToHistory();
        this.showNotification('✅ 内容已保存，可以打印或下载PDF了', 'success');
      }

      // 设置光标到末尾
      setCursorToEnd() {
        const range = document.createRange();
        const sel = window.getSelection();
        range.selectNodeContents(this.noticeContent);
        range.collapse(false);
        sel.removeAllRanges();
        sel.addRange(range);
      }

      // 新增文本编辑功能
      addHeading(type) {
        if (!this.isEditMode) {
          this.showNotification('请先进入编辑模式', 'warning');
          return;
        }

        const selection = window.getSelection();
        if (selection.rangeCount > 0) {
          const range = selection.getRangeAt(0);
          const selectedText = range.toString() || '三级标题';
          
          const heading = document.createElement(type);
          heading.textContent = selectedText;
          
          range.deleteContents();
          range.insertNode(heading);
          
          this.saveToHistory();
          this.showNotification('标题已添加', 'success');
        }
      }

      addIndent() {
        if (!this.isEditMode) {
          this.showNotification('请先进入编辑模式', 'warning');
          return;
        }

        const selection = window.getSelection();
        if (selection.rangeCount > 0) {
          const range = selection.getRangeAt(0);
          const paragraph = range.startContainer.parentElement;
          
          if (paragraph.tagName === 'DIV' || paragraph.tagName === 'P') {
            paragraph.classList.add('indent');
            this.saveToHistory();
            this.showNotification('首行缩进已添加', 'success');
          }
        }
      }

      addLineBreak() {
        if (!this.isEditMode) {
          this.showNotification('请先进入编辑模式', 'warning');
          return;
        }

        const selection = window.getSelection();
        if (selection.rangeCount > 0) {
          const range = selection.getRangeAt(0);
          const br = document.createElement('br');
          const div = document.createElement('div');
          div.classList.add('line-break');
          
          range.insertNode(br);
          range.insertNode(div);
          
          this.saveToHistory();
          this.showNotification('段落分行已添加', 'success');
        }
      }

      removeFormat() {
        if (!this.isEditMode) {
          this.showNotification('请先进入编辑模式', 'warning');
          return;
        }

        try {
          document.execCommand('removeFormat', false, null);
          this.saveToHistory();
          this.showNotification('格式已清除', 'success');
        } catch (error) {
          this.showNotification('清除格式失败', 'error');
        }
      }

      // 恢复默认内容
      resetDefault() {
        if (confirm('确定要恢复默认内容吗？当前的编辑内容将会丢失。')) {
          this.noticeContent.innerHTML = this.defaultContent.trim();
          if (this.isEditMode) {
            this.toggleEditMode();
          }
          this.saveToHistory();
          this.showNotification('已恢复默认内容', 'success');
        }
      }

      // 下载PDF
      async downloadPDF() {
        const loadingOverlay = this.showLoadingOverlay('正在生成PDF...');

        try {
          if (typeof window.jspdf === 'undefined') {
            throw new Error('PDF库未加载');
          }

          const { jsPDF } = window.jspdf;

          if (typeof html2canvas === 'undefined') {
            throw new Error('Canvas库未加载');
          }

          const pdf = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a4'
          });

          // 临时隐藏编辑工具栏
          const toolbar = document.querySelector('.edit-toolbar');
          const originalDisplay = toolbar.style.display;
          toolbar.style.display = 'none';

          // 临时移除编辑样式
          const wasEditable = this.noticeContent.classList.contains('editable');
          if (wasEditable) {
            this.noticeContent.classList.remove('editable');
          }

          // 生成PDF
          const contentArea = document.getElementById('contentArea');
          const canvas = await html2canvas(contentArea, {
            scale: 1,
            useCORS: true,
            allowTaint: true,
            backgroundColor: '#ffffff',
            logging: false
          });

          // 恢复样式
          toolbar.style.display = originalDisplay;
          if (wasEditable) {
            this.noticeContent.classList.add('editable');
          }

          const imgData = canvas.toDataURL('image/png', 0.8);
          const imgWidth = 210;
          const pageHeight = 297;
          const imgHeight = (canvas.height * imgWidth) / canvas.width;
          let heightLeft = imgHeight;

          let position = 0;

          pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
          heightLeft -= pageHeight;

          while (heightLeft >= 0) {
            position = heightLeft - imgHeight;
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
          }

          const filename = `联系挪车通知单_${new Date().toISOString().split('T')[0]}.pdf`;
          pdf.save(filename);

          this.hideLoadingOverlay(loadingOverlay);
          this.showNotification('PDF生成成功！', 'success');

        } catch (error) {
          console.error('PDF生成失败:', error);
          this.hideLoadingOverlay(loadingOverlay);
          this.showNotification('PDF生成失败，请使用浏览器打印功能', 'error');
        }
      }

      // 显示加载遮罩
      showLoadingOverlay(message) {
        const overlay = document.createElement('div');
        overlay.className = 'loading-overlay show';
        overlay.innerHTML = `
          <div style="text-align: center;">
            <div class="loading-spinner"></div>
            <div style="margin-top: 1rem; font-size: 1.1rem; color: var(--text-primary);">${message}</div>
          </div>
        `;
        document.body.appendChild(overlay);
        return overlay;
      }

      // 隐藏加载遮罩
      hideLoadingOverlay(overlay) {
        overlay.classList.remove('show');
        setTimeout(() => overlay.remove(), 300);
      }

      // 显示通知
      showNotification(message, type = 'info') {
        // 移除已存在的通知
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(notif => {
          notif.classList.remove('show');
          setTimeout(() => notif.remove(), 300);
        });

        const notification = document.createElement('div');
        notification.className = `notification ${type}`;

        // 根据类型选择图标
        let icon = 'ℹ️';
        if (type === 'success') icon = '✅';
        else if (type === 'error') icon = '❌';
        else if (type === 'warning') icon = '⚠️';

        notification.innerHTML = `
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <span style="font-size: 1.2rem;">${icon}</span>
            <span style="flex: 1;">${message}</span>
            <button onclick="this.parentElement.parentElement.classList.remove('show')"
                    style="background: none; border: none; font-size: 1.5rem; cursor: pointer; opacity: 0.7; padding: 0; line-height: 1;">×</button>
          </div>
        `;

        document.body.appendChild(notification);

        // 触发动画
        setTimeout(() => notification.classList.add('show'), 10);

        // 自动消失
        setTimeout(() => {
          notification.classList.remove('show');
          setTimeout(() => notification.remove(), 300);
        }, 3000);
      }

      // 键盘事件处理
      handleKeyboard(e) {
        if (e.ctrlKey || e.metaKey) {
          switch (e.key.toLowerCase()) {
            case 'e':
              e.preventDefault();
              this.toggleEditMode();
              break;
            case 'p':
              // 允许默认打印行为
              break;
            case 's':
              if (this.isEditMode) {
                e.preventDefault();
                this.toggleEditMode();
              }
              break;
            case 'b':
              if (this.isEditMode) {
                e.preventDefault();
                this.applyFormat('bold');
              }
              break;
            case 'i':
              if (this.isEditMode) {
                e.preventDefault();
                this.applyFormat('italic');
              }
              break;
            case 'u':
              if (this.isEditMode) {
                e.preventDefault();
                this.applyFormat('underline');
              }
              break;
            case 'z':
              if (this.isEditMode) {
                e.preventDefault();
                if (e.shiftKey) {
                  this.redo();
                } else {
                  this.undo();
                }
              }
              break;
            case 'y':
              if (this.isEditMode) {
                e.preventDefault();
                this.redo();
              }
              break;
          }
        }

        if (e.key === 'Escape' && this.isEditMode) {
          this.toggleEditMode();
        }
      }

      // 内容点击处理
      handleContentClick(e) {
        if (!this.isEditMode) {
          // 添加抖动动画提示
          this.noticeContent.style.animation = 'shake 0.5s ease-in-out';
          setTimeout(() => {
            this.noticeContent.style.animation = '';
          }, 500);
          this.showNotification('💡 点击"编辑内容"按钮开始编辑', 'info');
        }
      }

      // 历史记录管理
      saveToHistory() {
        const content = this.noticeContent.innerHTML;
        this.history = this.history.slice(0, this.historyIndex + 1);
        this.history.push(content);

        if (this.history.length > this.maxHistory) {
          this.history.shift();
        } else {
          this.historyIndex++;
        }
      }

      // 自动保存设置
      setupAutoSave() {
        setInterval(() => {
          if (this.isEditMode) {
            localStorage.setItem('noticeContent', this.noticeContent.innerHTML);
          }
        }, 30000);

        const saved = localStorage.getItem('noticeContent');
        if (saved && saved !== this.defaultContent.trim()) {
          this.noticeContent.innerHTML = saved;
          this.saveToHistory();
        }
      }

      // 防抖函数
      debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
          const later = () => {
            clearTimeout(timeout);
            func(...args);
          };
          clearTimeout(timeout);
          timeout = setTimeout(later, wait);
        };
      }
    }

    // 全局函数
    let editor;

    function toggleEditMode() {
      editor.toggleEditMode();
    }

    function resetDefault() {
      editor.resetDefault();
    }

    async function downloadPDF() {
      await editor.downloadPDF();
    }

    function applyFormat(command, value) {
      editor.applyFormat(command, value);
    }

    function applyColor(color) {
      editor.applyColor(color);
    }

    function insertHR() {
      editor.insertHR();
    }

    function addHeading(type) {
      editor.addHeading(type);
    }

    function addIndent() {
      editor.addIndent();
    }

    function addLineBreak() {
      editor.addLineBreak();
    }

    function removeFormat() {
      editor.removeFormat();
    }

    function undo() {
      editor.undo();
    }

    function redo() {
      editor.redo();
    }

    // 页面加载完成后初始化
    document.addEventListener('DOMContentLoaded', function() {
      editor = new NoticeEditor();
      console.log('✅ 联系挪车通知单编辑器已加载');

      // 显示欢迎提示
      setTimeout(() => {
        editor.showNotification('👋 欢迎使用挪车通知单编辑器！点击"编辑内容"开始自定义', 'info');
      }, 1000);

      // 添加键盘快捷键提示
      console.log('⌨️ 快捷键提示:');
      console.log('  Ctrl+E - 切换编辑模式');
      console.log('  Ctrl+S - 保存内容');
      console.log('  Ctrl+B - 粗体');
      console.log('  Ctrl+I - 斜体');
      console.log('  Ctrl+U - 下划线');
      console.log('  Ctrl+Z - 撤销');
      console.log('  Ctrl+Shift+Z / Ctrl+Y - 重做');
      console.log('  Esc - 退出编辑模式');
    });
  </script>
</body>

</html>