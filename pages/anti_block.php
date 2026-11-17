<?php
// pages/anti_block.php
// 防封中间页 - 引导用户使用外部浏览器

// 启动 session 并加载配置
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 加载配置和函数
$config_path = file_exists('../config/config.php') ? '../config/config.php' : __DIR__ . '/../config/config.php';
$functions_path = file_exists('../includes/functions.php') ? '../includes/functions.php' : __DIR__ . '/../includes/functions.php';
require_once $config_path;
require_once $functions_path;

// 记录访问日志
log_browser_access('anti_block_page');

/**
 * 校验跳转目标：仅允许本站 URL 或站内相对路径
 * 防止开放重定向（//evil.com、http://evil.com）与 javascript: 等危险 scheme
 */
function is_safe_redirect_target($url) {
    if (!is_string($url) || $url === '') {
        return false;
    }
    // 显式拒绝 script 可执行类 scheme
    if (preg_match('/^\s*(javascript|data|vbscript)\s*:/i', $url)) {
        return false;
    }
    if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $url)) {
        // 带协议的完整 URL：必须与本站同域
        $host = parse_url($url, PHP_URL_HOST);
        $self_host = $_SERVER['HTTP_HOST'] ?? '';
        return $host !== null && $self_host !== '' && strcasecmp($host, $self_host) === 0;
    }
    // 相对路径：必须以单个 / 开头（拒绝 //evil.com 协议相对形式）
    return $url[0] === '/' && ($url[1] ?? '') !== '/';
}

// 获取目标URL并校验，不合法时回退到站点首页
$target_url = $_GET['target'] ?? '';
$uuid = $_GET['uuid'] ?? '';

$decoded_target = urldecode($target_url);
if (!is_safe_redirect_target($decoded_target)) {
    $decoded_target = defined('SITE_URL') ? SITE_URL : '/';
}

// 如果不是内置浏览器，直接跳转（目标已校验）
if (!needs_anti_block_redirect()) {
    header("Location: " . $decoded_target);
    exit;
}

// 获取浏览器名称
$browser_name = get_browser_name();

// 检测操作系统
$is_ios = strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone') !== false || 
          strpos($_SERVER['HTTP_USER_AGENT'], 'iPad') !== false;
$is_android = strpos($_SERVER['HTTP_USER_AGENT'], 'Android') !== false;

?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>请使用浏览器打开 - 车友帮讯联系系统</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Microsoft YaHei', 'PingFang SC', 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 100%;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #007bff, #28a745, #ffc107, #dc3545);
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: bold;
        }
        
        .title {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .subtitle {
            font-size: 16px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.5;
        }
        
        .browser-detect {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #ffc107;
        }
        
        .browser-name {
            font-size: 18px;
            color: #e67e22;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .browser-tips {
            font-size: 14px;
            color: #7f8c8d;
            line-height: 1.4;
        }
        
        .steps {
            text-align: left;
            margin-bottom: 30px;
        }
        
        .step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .step:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .step-number {
            background: #007bff;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            margin-right: 12px;
            flex-shrink: 0;
        }
        
        .step-content {
            flex: 1;
            font-size: 14px;
            color: #495057;
            line-height: 1.4;
        }
        
        .step-title {
            font-weight: 600;
            color: #212529;
            margin-bottom: 4px;
        }
        
        .buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 123, 255, 0.3);
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #495057;
            border: 2px solid #dee2e6;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        
        .url-display {
            background: #f8f9fa;
            border: 1px dashed #dee2e6;
            border-radius: 8px;
            padding: 12px;
            margin: 20px 0;
            font-family: monospace;
            font-size: 12px;
            color: #495057;
            word-break: break-all;
            position: relative;
        }
        
        .copy-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 4px 8px;
            font-size: 10px;
            cursor: pointer;
        }
        
        .animation {
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 12px;
            color: #adb5bd;
        }
        
        /* 移动端优化 */
        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
                margin: 10px;
            }
            
            .title {
                font-size: 20px;
            }
            
            .step {
                padding: 12px;
            }
            
            .buttons {
                gap: 10px;
            }
            
            .btn {
                padding: 14px 16px;
                font-size: 15px;
            }
        }
        
        /* 手机系统特定样式 */
        .ios-tips {
            background: linear-gradient(135deg, #ff6b6b, #ffa500);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .android-tips {
            background: linear-gradient(135deg, #4ecdc4, #00d2ff);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .alert-box {
            background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
            color: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            font-size: 14px;
            line-height: 1.6;
            box-shadow: 0 4px 12px rgba(255, 107, 107, 0.3);
        }
        
        .alert-box strong {
            display: block;
            font-size: 16px;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo animation">🚗</div>
        
        <h1 class="title">请使用浏览器打开</h1>
        <p class="subtitle">为了更好的体验，请在外部浏览器中打开此页面</p>
        
        <div class="browser-detect">
            <div class="browser-name">检测到您在 <?php echo htmlspecialchars($browser_name); ?> 中访问</div>
            <div class="browser-tips">系统检测到您当前使用的是内置浏览器，可能无法正常使用所有功能</div>
        </div>
        
        <?php if ($is_ios): ?>
        <div class="ios-tips">
            <strong>📱 iPhone用户操作指南</strong><br>
            由于iOS系统限制，需要手动操作才能在Safari中打开
        </div>
        <?php elseif ($is_android): ?>
        <div class="android-tips">
            <strong>🤖 Android用户福利</strong><br>
            点击下方按钮可自动跳转到默认浏览器
        </div>
        <?php endif; ?>
        
        <div class="steps">
            <?php if ($is_ios): ?>
            <!-- iOS 操作步骤 -->
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <div class="step-title">点击右上角菜单</div>
                    <div>在<?php echo htmlspecialchars($browser_name); ?>中找到"⋯"或"更多"按钮</div>
                </div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <div class="step-title">选择"在Safari中打开"</div>
                    <div>从弹出菜单中选择用Safari浏览器打开</div>
                </div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <div class="step-title">正常使用功能</div>
                    <div>在Safari中即可正常访问和使用所有功能</div>
                </div>
            </div>
            <?php else: ?>
            <!-- Android 操作步骤 -->
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <div class="step-title">点击下方"在浏览器中打开"</div>
                    <div>系统将自动跳转到您的默认浏览器</div>
                </div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <div class="step-title">或手动复制链接</div>
                    <div>复制下方链接到浏览器地址栏打开</div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="url-display">
            <button class="copy-btn" onclick="copyUrl()">复制</button>
            <div id="targetUrl"><?php echo htmlspecialchars($decoded_target); ?></div>
        </div>
        
        <div class="buttons">
            <?php if ($is_android): ?>
            <a href="<?php echo htmlspecialchars($decoded_target); ?>" class="btn btn-primary" id="openBtn">
                🌐 在浏览器中打开
            </a>
            <?php endif; ?>
            
            <button class="btn btn-secondary" onclick="copyUrl()">
                📋 复制链接地址
            </button>
            
            <!-- 移除直接访问按钮，强制用户使用外部浏览器 -->
            <div class="alert-box">
                <strong>⚠️ 安全提示</strong><br>
                为保护您的数据安全，请务必在外部浏览器中打开
            </div>
        </div>
        
        <div class="footer">
            <div>车友帮讯联系系统 - 安全访问提示</div>
            <div>为了保护您的隐私和数据安全</div>
        </div>
    </div>

    <script>
        // JavaScript增强检测 - 二次验证
        function detectWechatEnhanced() {
            // 检测1：微信JSSDK
            if (typeof WeixinJSBridge !== 'undefined') {
                return true;
            }
            
            // 检测2：User-Agent
            const ua = navigator.userAgent.toLowerCase();
            if (ua.indexOf('micromessenger') > -1 || 
                ua.indexOf('wechat') > -1 || 
                ua.indexOf('weixin') > -1) {
                return true;
            }
            
            // 检测3：微信小程序环境
            if (window.__wxjs_environment === 'miniprogram') {
                return true;
            }
            
            // 检测4：微信特有API
            if (typeof wx !== 'undefined' && wx.miniProgram) {
                return true;
            }
            
            return false;
        }
        
        // 检测QQ浏览器
        function detectQQBrowser() {
            const ua = navigator.userAgent.toLowerCase();
            return ua.indexOf('qq/') > -1 || 
                   ua.indexOf('qzone') > -1 || 
                   ua.indexOf('qqbrowser') > -1 ||
                   ua.indexOf('mqqbrowser') > -1;
        }
        
        // 综合检测
        function detectInAppBrowser() {
            return detectWechatEnhanced() || detectQQBrowser();
        }
        
        // 页面加载时进行检测
        (function() {
            // 如果不是内置浏览器，自动跳转
            if (!detectInAppBrowser()) {
                console.log('检测到外部浏览器，准备跳转...');
                setTimeout(() => {
                    window.location.href = <?php echo json_encode($decoded_target); ?>;
                }, 1000);
                
                // 显示跳转提示
                showMessage('✅ 检测到外部浏览器，正在跳转...');
            } else {
                console.log('检测到内置浏览器，显示引导页面');
                
                // 禁用可能的绕过方式
                document.addEventListener('contextmenu', function(e) {
                    e.preventDefault();
                });
                
                // 监控页面可见性，防止后台跳转
                document.addEventListener('visibilitychange', function() {
                    if (document.hidden) {
                        console.log('页面被隐藏，可能正在切换浏览器');
                    }
                });
            }
        })();
        
        // 复制链接功能
        function copyUrl() {
            const urlText = document.getElementById('targetUrl').textContent;
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(urlText).then(() => {
                    showMessage('链接已复制到剪贴板！');
                }).catch(() => {
                    fallbackCopy(urlText);
                });
            } else {
                fallbackCopy(urlText);
            }
        }
        
        // 备用复制方法
        function fallbackCopy(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                showMessage('链接已复制到剪贴板！');
            } catch (err) {
                showMessage('复制失败，请手动复制链接');
            }
            document.body.removeChild(textArea);
        }
        
        // 显示消息 - 增强版
        function showMessage(message, duration = 3000) {
            const messageDiv = document.createElement('div');
            const isError = message.indexOf('⚠️') > -1 || message.indexOf('❌') > -1;
            const isSuccess = message.indexOf('✅') > -1;
            
            let bgColor = '#28a745'; // 默认绿色
            if (isError) bgColor = '#ff6b6b';
            if (isSuccess) bgColor = '#4ecdc4';
            
            messageDiv.style.cssText = `
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: ${bgColor};
                color: white;
                padding: 12px 24px;
                border-radius: 8px;
                font-size: 14px;
                z-index: 9999;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                max-width: 90%;
                text-align: center;
                animation: slideDown 0.3s ease-out;
            `;
            messageDiv.textContent = message;
            document.body.appendChild(messageDiv);
            
            setTimeout(() => {
                messageDiv.style.animation = 'slideUp 0.3s ease-out';
                setTimeout(() => {
                    messageDiv.remove();
                }, 300);
            }, duration);
        }
        
        // 添加动画样式
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateX(-50%) translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateX(-50%) translateY(0);
                }
            }
            @keyframes slideUp {
                from {
                    opacity: 1;
                    transform: translateX(-50%) translateY(0);
                }
                to {
                    opacity: 0;
                    transform: translateX(-50%) translateY(-20px);
                }
            }
        `;
        document.head.appendChild(style);
        
        // Android增强跳转功能
        <?php if ($is_android): ?>
        document.getElementById('openBtn').addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetUrl = <?php echo json_encode($decoded_target); ?>;
            const host = targetUrl.replace(/^https?:\/\//, '');
            
            showMessage('正在尝试打开浏览器...');
            
            // 方案1：尝试Chrome浏览器
            setTimeout(() => {
                try {
                    window.location.href = 'googlechrome://navigate?url=' + encodeURIComponent(targetUrl);
                    console.log('尝试Chrome浏览器');
                } catch (e) {
                    console.log('Chrome跳转失败:', e);
                }
            }, 100);
            
            // 方案2：尝试通用Intent
            setTimeout(() => {
                try {
                    const intentUrl = 'intent://' + host + '#Intent;scheme=http;action=android.intent.action.VIEW;end';
                    window.location.href = intentUrl;
                    console.log('尝试Intent跳转');
                } catch (e) {
                    console.log('Intent跳转失败:', e);
                }
            }, 500);
            
            // 方案3：尝试Firefox浏览器
            setTimeout(() => {
                try {
                    window.location.href = 'firefox://open-url?url=' + encodeURIComponent(targetUrl);
                    console.log('尝试Firefox浏览器');
                } catch (e) {
                    console.log('Firefox跳转失败:', e);
                }
            }, 1000);
            
            // 方案4：直接跳转（最后的尝试）
            setTimeout(() => {
                try {
                    window.location.href = targetUrl;
                    console.log('尝试直接跳转');
                } catch (e) {
                    console.log('直接跳转失败:', e);
                }
            }, 1500);
            
            // 方案5：如果都失败，提示用户手动操作
            setTimeout(() => {
                showMessage('⚠️ 自动跳转可能失败，请手动复制链接到浏览器打开', 5000);
            }, 3000);
        });
        <?php endif; ?>
        
        // 页面加载完成后的自动操作
        document.addEventListener('DOMContentLoaded', function() {
            // 检测是否支持自动跳转
            <?php if ($is_android): ?>
            // Android设备延迟自动跳转
            setTimeout(() => {
                const autoJump = confirm('检测到Android设备，是否自动跳转到浏览器？');
                if (autoJump) {
                    document.getElementById('openBtn').click();
                }
            }, 2000);
            <?php endif; ?>
        });
    </script>
</body>
</html>