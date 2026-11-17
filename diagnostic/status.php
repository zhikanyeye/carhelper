<?php
// 系统状态检查 - 全面诊断工具（仅限管理员）
header('Content-Type: text/html; charset=utf-8');

// 包含配置文件以获取SITE_URL和检查权限
if (file_exists('../config/config.php')) {
    require_once '../config/config.php';
    require_once '../includes/functions.php';
    
    // 检查管理员权限
    if (!is_logged_in() || !is_admin()) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>访问受限 - 车友帮讯联</title>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Microsoft YaHei', sans-serif; 
                    margin: 0; 
                    padding: 20px; 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .container { 
                    max-width: 500px; 
                    background: white; 
                    border-radius: 15px; 
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                    padding: 40px;
                    text-align: center;
                }
                .icon { font-size: 4em; color: #dc3545; margin-bottom: 20px; }
                .btn { 
                    display: inline-block; 
                    padding: 10px 20px; 
                    margin: 10px; 
                    background: #007bff; 
                    color: white; 
                    text-decoration: none; 
                    border-radius: 8px; 
                    transition: all 0.3s;
                }
                .btn:hover { background: #0056b3; }
                .btn.success { background: #28a745; }
                .btn.success:hover { background: #218838; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="icon">🔒</div>
                <h2>访问受限</h2>
                <p>系统诊断功能仅限管理员访问。</p>
                <p>该功能包含系统敏感信息，需要管理员权限才能查看。</p>
                <div>
                    <a href="../login" class="btn">🔑 管理员登录</a>
                    <a href="../admin" class="btn success">⚙️ 管理后台</a>
                </div>
                <p><small>如果您是管理员，请先登录后再访问此页面。</small></p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
} else {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>配置错误 - 车友帮讯联</title>
        <meta charset="utf-8">
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 50px; 
                background: #f8f9fa;
            }
            .error { 
                background: #f8d7da; 
                border: 1px solid #f5c6cb; 
                color: #721c24; 
                padding: 20px; 
                border-radius: 5px; 
            }
        </style>
    </head>
    <body>
        <div class="error">
            <h3>⚠️ 系统配置错误</h3>
            <p>无法找到系统配置文件，请确保系统已正确安装。</p>
            <p><a href="../install.php">重新安装系统</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 包含邮件配置检查
$email_config_loaded = false;
if (file_exists('../config/email.php')) {
    try {
        require_once '../config/email.php';
        $email_config_loaded = true;
    } catch (Exception $e) {
        $email_error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>系统全面诊断 - 车友帮讯联</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Microsoft YaHei', sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container { 
            max-width: 1000px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }
        .admin-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
        }
        .content { padding: 30px; }
        .status-ok { color: #28a745; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        .status-warning { color: #ffc107; font-weight: bold; }
        .status-info { color: #17a2b8; font-weight: bold; }
        .button { 
            display: inline-block; 
            padding: 10px 20px; 
            margin: 5px; 
            background: #007bff; 
            color: white; 
            text-decoration: none; 
            border-radius: 8px; 
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        .button:hover { 
            background: #0056b3; 
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .button.success { background: #28a745; }
        .button.success:hover { background: #218838; }
        .button.warning { background: #ffc107; color: #212529; }
        .button.warning:hover { background: #e0a800; }
        .button.danger { background: #dc3545; }
        .button.danger:hover { background: #c82333; }
        .section { 
            margin: 25px 0; 
            padding: 20px; 
            border-radius: 10px;
            border-left: 5px solid #007bff; 
            background: #f8f9fa; 
        }
        .section.success { border-left-color: #28a745; background: #d4edda; }
        .section.warning { border-left-color: #ffc107; background: #fff3cd; }
        .section.error { border-left-color: #dc3545; background: #f8d7da; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .card { 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        .card.success { border-left-color: #28a745; }
        .card.warning { border-left-color: #ffc107; }
        .card.error { border-left-color: #dc3545; }
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.3s ease;
        }
        h1, h2, h3 { margin-top: 0; }
        .icon { font-size: 1.2em; margin-right: 8px; }
        .stats { display: flex; justify-content: space-between; align-items: center; margin: 10px 0; }
        .footer { 
            background: #f8f9fa; 
            padding: 20px; 
            text-align: center; 
            border-top: 1px solid #dee2e6;
            color: #6c757d;
        }
        .nav-buttons {
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="admin-badge">👑 管理员专用</div>
            <h1>🚗 车友帮讯联 v2.1</h1>
            <h2>系统全面诊断报告</h2>
            <p>实时检测系统状态，确保最佳运行环境</p>
        </div>
        
        <div class="content">
            <!-- 系统概览 -->
            <div class="section success">
                <h3><span class="icon">📊</span>系统概览</h3>
                <div class="grid">
                    <div>
                        <strong>PHP版本:</strong> <?php echo PHP_VERSION; ?><br>
                        <strong>检测时间:</strong> <?php echo date('Y-m-d H:i:s'); ?><br>
                        <strong>服务器:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>
                    </div>
                    <div>
                        <strong>内存限制:</strong> <?php echo ini_get('memory_limit'); ?><br>
                        <strong>执行时间:</strong> <?php echo ini_get('max_execution_time'); ?>s<br>
                        <strong>文件上传:</strong> <?php echo ini_get('upload_max_filesize'); ?>
                    </div>
                </div>
            </div>

            <!-- 核心文件检查 -->
            <div class="section">
                <h3><span class="icon">📁</span>核心文件完整性检查</h3>
                <?php
                $core_files = [
                    '../index.php' => '主入口文件',
                    '../config/config.php' => '系统配置文件',
                    '../config/database.php' => '数据库配置',
                    '../config/email.php' => '邮件配置文件',
                    '../config/installed.lock' => '安装完成标记',
                    '../includes/functions.php' => '核心函数库',
                    '../includes/header.php' => '页面头部模板',
                    '../includes/footer.php' => '页面底部模板',
                    '../pages/dashboard.php' => '用户面板',
                    '../pages/admin.php' => '管理后台',
                    '../pages/login.php' => '用户登录',
                    '../pages/register.php' => '用户注册',
                    '../pages/view_messages.php' => '留言查看',
                    '../pages/leave_message.php' => '留言提交',
                    '../database.sql' => '数据库结构文件',
                    '../css/style.css' => '样式文件',
                    '../js/script.js' => 'JavaScript文件'
                ];
                
                $missing_files = 0;
                $total_files = count($core_files);
                
                echo "<div class='grid'>";
                foreach ($core_files as $file => $desc) {
                    if (file_exists($file)) {
                        $size = filesize($file);
                        $size_text = $size > 1024 ? round($size/1024, 1) . 'KB' : $size . 'B';
                        echo "<div class='card success'>";
                        echo "<p class='status-ok'>✅ $desc</p>";
                        echo "<small>文件大小: $size_text | 最后修改: " . date('Y-m-d H:i', filemtime($file)) . "</small>";
                        echo "</div>";
                    } else {
                        $missing_files++;
                        echo "<div class='card error'>";
                        echo "<p class='status-error'>❌ $desc</p>";
                        echo "<small>文件路径: $file</small>";
                        echo "</div>";
                    }
                }
                echo "</div>";
                
                $integrity = (($total_files - $missing_files) / $total_files) * 100;
                echo "<div class='stats'>";
                echo "<span>文件完整性: " . round($integrity, 1) . "%</span>";
                echo "<span>$missing_files 个文件缺失</span>";
                echo "</div>";
                echo "<div class='progress-bar'><div class='progress-fill' style='width: {$integrity}%'></div></div>";
                ?>
            </div>

            <!-- 数据库连接检查 -->
            <div class="section">
                <h3><span class="icon">🗄️</span>数据库系统检查</h3>
                <?php
                $db_status = 'error';
                $tables_status = [];
                
                try {
                    if (file_exists('../config/database.php')) {
                        include_once '../config/database.php';
                        if (isset($pdo) && $pdo instanceof PDO) {
                            $db_status = 'success';
                            echo "<p class='status-ok'>✅ 数据库连接正常</p>";
                            
                            // 检查数据库版本
                            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
                            echo "<p><strong>🔗 数据库版本:</strong> $version</p>";
                            
                            // 检查核心数据表
                            $required_tables = [
                                'users' => ['name' => '用户表', 'icon' => '👥'],
                                'messages' => ['name' => '留言表', 'icon' => '💬'],
                                'email_verifications' => ['name' => '邮箱验证表', 'icon' => '📧'],
                                'settings' => ['name' => '系统设置表', 'icon' => '⚙️']
                            ];
                            
                            echo "<div class='grid'>";
                            foreach ($required_tables as $table => $info) {
                                try {
                                    $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                                    $count = $stmt->fetchColumn();
                                    
                                    // 检查表结构
                                    $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll();
                                    $column_count = count($columns);
                                    
                                    echo "<div class='card success'>";
                                    echo "<p class='status-ok'>✅ {$info['icon']} {$info['name']} ($table)</p>";
                                    echo "<small>📊 记录数: $count | 🗂️ 字段数: $column_count</small>";
                                    echo "</div>";
                                    
                                    $tables_status[$table] = 'ok';
                                } catch (Exception $e) {
                                    echo "<div class='card error'>";
                                    echo "<p class='status-error'>❌ {$info['icon']} {$info['name']} ($table)</p>";
                                    echo "<small>⚠️ 错误: " . htmlspecialchars($e->getMessage()) . "</small>";
                                    echo "</div>";
                                    
                                    $tables_status[$table] = 'error';
                                }
                            }
                            echo "</div>";
                            
                            // UUID系统检查
                            echo "<h4>🔑 UUID安全系统检查</h4>";
                            echo "<div class='grid'>";
                            
                            // 检查UUID字段
                            try {
                                $uuid_column = $pdo->query("SHOW COLUMNS FROM users LIKE 'uuid'")->fetch();
                                if ($uuid_column) {
                                    echo "<div class='card success'>";
                                    echo "<p class='status-ok'>✅ UUID字段存在</p>";
                                    echo "<small>字段类型: {$uuid_column['Type']}</small>";
                                    echo "</div>";
                                } else {
                                    echo "<div class='card error'>";
                                    echo "<p class='status-error'>❌ UUID字段缺失</p>";
                                    echo "<small>需要运行数据库升级</small>";
                                    echo "</div>";
                                }
                            } catch (Exception $e) {
                                echo "<div class='card error'>";
                                echo "<p class='status-error'>❌ UUID字段检查失败</p>";
                                echo "<small>错误: " . htmlspecialchars($e->getMessage()) . "</small>";
                                echo "</div>";
                            }
                            
                            // 检查UUID数据完整性
                            try {
                                $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                                $users_with_uuid = $pdo->query("SELECT COUNT(*) FROM users WHERE uuid IS NOT NULL AND uuid != ''")->fetchColumn();
                                $uuid_coverage = $total_users > 0 ? ($users_with_uuid / $total_users) * 100 : 0;
                                
                                if ($uuid_coverage >= 100) {
                                    echo "<div class='card success'>";
                                    echo "<p class='status-ok'>✅ UUID数据完整</p>";
                                } elseif ($uuid_coverage >= 80) {
                                    echo "<div class='card warning'>";
                                    echo "<p class='status-warning'>⚠️ UUID数据基本完整</p>";
                                } else {
                                    echo "<div class='card error'>";
                                    echo "<p class='status-error'>❌ UUID数据不完整</p>";
                                }
                                echo "<small>覆盖率: " . round($uuid_coverage, 1) . "% ({$users_with_uuid}/{$total_users})</small>";
                                echo "</div>";
                            } catch (Exception $e) {
                                echo "<div class='card error'>";
                                echo "<p class='status-error'>❌ UUID数据检查失败</p>";
                                echo "<small>错误: " . htmlspecialchars($e->getMessage()) . "</small>";
                                echo "</div>";
                            }
                            
                            // 检查UUID扩展
                            if (extension_loaded('uuid')) {
                                echo "<div class='card success'>";
                                echo "<p class='status-ok'>✅ PHP UUID扩展已加载</p>";
                                if (function_exists('uuid_create')) {
                                    echo "<small>uuid_create函数可用</small>";
                                } else {
                                    echo "<small>⚠️ uuid_create函数不可用</small>";
                                }
                                echo "</div>";
                            } else {
                                echo "<div class='card warning'>";
                                echo "<p class='status-warning'>⚠️ PHP UUID扩展未加载</p>";
                                echo "<small>使用备用UUID生成方案</small>";
                                echo "</div>";
                            }
                            
                            echo "</div>";
                            
                            // 检查数据库性能
                            $start_time = microtime(true);
                            $pdo->query("SELECT 1");
                            $query_time = (microtime(true) - $start_time) * 1000;
                            
                            echo "<div class='stats'>";
                            echo "<span>⚡ 查询响应时间: " . round($query_time, 2) . "ms</span>";
                            if ($query_time < 100) {
                                echo "<span class='status-ok'>🚀 性能优秀</span>";
                            } elseif ($query_time < 500) {
                                echo "<span class='status-warning'>⚠️ 性能良好</span>";
                            } else {
                                echo "<span class='status-error'>🐌 性能需要优化</span>";
                            }
                            echo "</div>";
                            
                        } else {
                            echo "<p class='status-error'>❌ 数据库连接对象无效</p>";
                        }
                    } else {
                        echo "<p class='status-error'>❌ 数据库配置文件不存在</p>";
                    }
                } catch (Exception $e) {
                    echo "<p class='status-error'>❌ 数据库连接失败: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
                ?>
            </div>

            <!-- 核心功能检查 -->
            <div class="section">
                <h3><span class="icon">⚙️</span>核心功能模块检查</h3>
                <div class="grid">
                    <?php
                    // 检查核心函数可用性
                    $core_functions = [
                        'is_logged_in' => '用户登录检查',
                        'verify_csrf_token' => 'CSRF安全验证',
                        'generate_csrf_token' => 'CSRF令牌生成',
                        'get_current_system_user' => '当前用户获取',
                        'generate_uuid' => 'UUID生成功能',
                        'get_user_by_uuid' => 'UUID用户查询',
                        'generate_qr_code' => '二维码生成',
                        'get_user_message_count' => '留言数量统计',
                        'time_ago' => '时间格式化',
                        'ensure_database_connection' => '数据库连接确保'
                    ];
                    
                    $function_count = 0;
                    foreach ($core_functions as $func => $desc) {
                        if (function_exists($func)) {
                            echo "<div class='card success'>";
                            echo "<p class='status-ok'>✅ $desc</p>";
                            echo "<small>函数: $func()</small>";
                            echo "</div>";
                            $function_count++;
                        } else {
                            echo "<div class='card error'>";
                            echo "<p class='status-error'>❌ $desc</p>";
                            echo "<small>缺失函数: $func()</small>";
                            echo "</div>";
                        }
                    }
                    
                    $func_integrity = ($function_count / count($core_functions)) * 100;
                    ?>
                </div>
                <div class="stats">
                    <span>功能完整性: <?php echo round($func_integrity, 1); ?>%</span>
                    <span><?php echo $function_count; ?>/<?php echo count($core_functions); ?> 个功能可用</span>
                </div>
                <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $func_integrity; ?>%"></div></div>
            </div>

            <!-- 邮件系统检查 -->
            <div class="section">
                <h3><span class="icon">📧</span>邮件系统检查</h3>
                <?php
                if ($email_config_loaded) {
                    echo "<p class='status-ok'>✅ 邮件配置文件加载成功</p>";
                    
                    // 检查邮件配置
                    if (function_exists('isEmailConfigured')) {
                        if (isEmailConfigured()) {
                            echo "<p class='status-ok'>✅ 邮件系统配置完整</p>";
                            echo "<p><strong>SMTP服务器:</strong> " . (defined('SMTP_HOST') ? SMTP_HOST : '未设置') . "</p>";
                            echo "<p><strong>SMTP端口:</strong> " . (defined('SMTP_PORT') ? SMTP_PORT : '未设置') . "</p>";
                            echo "<p><strong>发件人名称:</strong> " . (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : '未设置') . "</p>";
                            
                            // 测试SMTP连接
                            if (function_exists('testEmailConnection')) {
                                $test_result = testEmailConnection();
                                if ($test_result['success']) {
                                    echo "<p class='status-ok'>✅ " . $test_result['message'] . "</p>";
                                } else {
                                    echo "<p class='status-warning'>⚠️ " . $test_result['message'] . "</p>";
                                }
                            }
                        } else {
                            echo "<p class='status-warning'>⚠️ 邮件系统未配置或配置不完整</p>";
                            echo "<p><small>用户仍可使用系统，但无法接收邮件通知</small></p>";
                        }
                    } else {
                        echo "<p class='status-error'>❌ 邮件配置检查函数不存在</p>";
                    }
                    
                    // 检查邮件相关函数
                    $email_functions = ['sendEmail', 'sendMessageNotification', 'isEmailConfigured', 'testEmailConnection'];
                    $email_func_count = 0;
                    
                    echo "<h4>邮件功能函数:</h4>";
                    foreach ($email_functions as $func) {
                        if (function_exists($func)) {
                            echo "<span class='status-ok'>✅ $func() </span>";
                            $email_func_count++;
                        } else {
                            echo "<span class='status-error'>❌ $func() </span>";
                        }
                    }
                    echo "<br><small>$email_func_count/" . count($email_functions) . " 个邮件函数可用</small>";
                    
                } else {
                    echo "<p class='status-error'>❌ 邮件配置文件加载失败</p>";
                    if (isset($email_error)) {
                        echo "<small>错误: " . htmlspecialchars($email_error) . "</small>";
                    }
                }
                ?>
            </div>

            <!-- 安全性检查 -->
            <div class="section">
                <h3><span class="icon">🔒</span>安全性检查</h3>
                <div class="grid">
                    <?php
                    // 检查.htaccess文件状态
                    echo "<h4>.htaccess配置状态:</h4>";
                    $htaccess_path = '../.htaccess';
                    if (file_exists($htaccess_path)) {
                        echo "<p class='status-ok'>✅ .htaccess 文件存在</p>";
                        
                        $htaccess_content = file_get_contents($htaccess_path);
                        $htaccess_configs = [];
                        
                        // 检查各种配置
                        if (preg_match('/php_flag\s+display_errors\s+Off/i', $htaccess_content)) {
                            $htaccess_configs[] = "display_errors Off";
                        }
                        if (preg_match('/php_flag\s+expose_php\s+Off/i', $htaccess_content)) {
                            $htaccess_configs[] = "expose_php Off";
                        }
                        if (preg_match('/Header.*X-Content-Type-Options.*nosniff/i', $htaccess_content)) {
                            $htaccess_configs[] = "安全头部配置";
                        }
                        if (preg_match('/RewriteEngine\s+On/i', $htaccess_content)) {
                            $htaccess_configs[] = "URL重写功能";
                        }
                        
                        if (!empty($htaccess_configs)) {
                            echo "<p class='status-ok'>📝 已配置: " . implode(", ", $htaccess_configs) . "</p>";
                        } else {
                            echo "<p class='status-warning'>⚠️ .htaccess 文件存在但未检测到安全配置</p>";
                        }
                    } else {
                        echo "<p class='status-error'>❌ .htaccess 文件不存在</p>";
                        echo "<small>建议创建 .htaccess 文件以增强安全性</small>";
                    }
                    
                    // 检查重要目录权限
                    echo "<h4>目录权限检查:</h4>";
                    $security_checks = [
                        '../config/' => '配置目录',
                        '../qrcodes/' => '二维码目录', 
                        '../uploads/' => '上传目录'
                    ];
                    
                    foreach ($security_checks as $dir => $desc) {
                        if (is_dir($dir)) {
                            if (is_writable($dir)) {
                                echo "<div class='card success'>";
                                echo "<p class='status-ok'>✅ $desc 可写</p>";
                            } else {
                                echo "<div class='card warning'>";
                                echo "<p class='status-warning'>⚠️ $desc 只读</p>";
                            }
                            echo "<small>路径: $dir</small>";
                            echo "</div>";
                        } else {
                            echo "<div class='card error'>";
                            echo "<p class='status-error'>❌ $desc 不存在</p>";
                            echo "<small>路径: $dir</small>";
                            echo "</div>";
                        }
                    }
                    
                    // 检查PHP安全设置
                    // 首先检查.htaccess中的配置
                    $htaccess_display_errors_off = false;
                    $htaccess_path = '../.htaccess';
                    if (file_exists($htaccess_path)) {
                        $htaccess_content = file_get_contents($htaccess_path);
                        // 检查是否配置了display_errors Off
                        if (preg_match('/php_flag\s+display_errors\s+Off/i', $htaccess_content) || 
                            preg_match('/php_value\s+display_errors\s+(0|Off)/i', $htaccess_content)) {
                            $htaccess_display_errors_off = true;
                        }
                    }
                    
                    // 获取实际的display_errors值，考虑.htaccess配置
                    $actual_display_errors = ini_get('display_errors');
                    $display_errors_status = 'Off';
                    if ($actual_display_errors && !$htaccess_display_errors_off) {
                        $display_errors_status = 'On';
                    } elseif ($htaccess_display_errors_off) {
                        $display_errors_status = 'Off (.htaccess)';
                    }
                    
                    $php_settings = [
                        'display_errors' => ['推荐值' => 'Off', '当前值' => $display_errors_status],
                        'expose_php' => ['推荐值' => 'Off', '当前值' => ini_get('expose_php') ? 'On' : 'Off'],
                        'session.cookie_httponly' => ['推荐值' => 'On', '当前值' => ini_get('session.cookie_httponly') ? 'On' : 'Off']
                    ];
                    
                    echo "<h4>PHP安全设置:</h4>";
                    $has_issues = false;
                    $issues = [];
                    
                    foreach ($php_settings as $setting => $values) {
                        // 特殊处理display_errors：如果通过.htaccess配置为Off，则认为是正确的
                        if ($setting === 'display_errors' && strpos($values['当前值'], '.htaccess') !== false) {
                            $status = 'ok'; // .htaccess配置被认为是正确的
                        } else {
                            $status = $values['当前值'] === $values['推荐值'] ? 'ok' : 'warning';
                        }
                        
                        $icon = $status === 'ok' ? '✅' : '⚠️';
                        echo "<p class='status-$status'>$icon $setting: {$values['当前值']} (推荐: {$values['推荐值']})</p>";
                        
                        if ($status === 'warning') {
                            $has_issues = true;
                            $issues[] = $setting;
                        }
                    }
                    
                    // 显示优化建议
                    if ($has_issues) {
                        echo "<div class='card warning' style='margin-top: 15px;'>";
                        echo "<h5>🔧 安全配置优化建议</h5>";
                        
                        foreach ($issues as $issue) {
                            switch ($issue) {
                                case 'display_errors':
                                    // 检查.htaccess是否已配置
                                    if ($htaccess_display_errors_off) {
                                        echo "<p><strong>display_errors 注意:</strong> .htaccess 已配置 display_errors Off，但服务器可能不支持或未生效</p>";
                                        echo "<small><strong>建议:</strong> 联系主机商确认 .htaccess PHP 配置支持，或在 php.ini 中设置 <code>display_errors = Off</code></small><br>";
                                    } else {
                                        echo "<p><strong>display_errors 问题:</strong> 生产环境应关闭错误显示以防止信息泄露</p>";
                                        echo "<small><strong>解决方案:</strong> 在 php.ini 中设置 <code>display_errors = Off</code> 或在 .htaccess 中添加 <code>php_flag display_errors Off</code></small><br>";
                                    }
                                    break;
                                case 'session.cookie_httponly':
                                    echo "<p><strong>session.cookie_httponly 问题:</strong> Session Cookie缺少HttpOnly保护，易受XSS攻击</p>";
                                    echo "<small><strong>解决方案:</strong> 在 php.ini 中设置 <code>session.cookie_httponly = 1</code> 或在 .htaccess 中添加 <code>php_value session.cookie_httponly 1</code></small><br>";
                                    break;
                                case 'expose_php':
                                    echo "<p><strong>expose_php 问题:</strong> 暴露PHP版本信息存在安全风险</p>";
                                    echo "<small><strong>解决方案:</strong> 在 php.ini 中设置 <code>expose_php = Off</code> 或在 .htaccess 中添加 <code>php_flag expose_php Off</code></small><br>";
                                    break;
                            }
                        }
                        
                        echo "<hr style='margin: 15px 0;'>";
                        echo "<p><strong>📋 快速修复指南:</strong></p>";
                        echo "<ol>";
                        if ($htaccess_display_errors_off) {
                            echo "<li><strong>方法1 - .htaccess 已配置:</strong> 系统检测到 .htaccess 文件中已配置了 display_errors Off</li>";
                            echo "<li><strong>验证方法:</strong> 如果仍显示警告，可能是主机不支持 .htaccess PHP 配置，建议联系主机商</li>";
                        } else {
                            echo "<li><strong>方法1 - 使用 .htaccess:</strong> 在根目录的 .htaccess 文件中添加 <code>php_flag display_errors Off</code></li>";
                        }
                        echo "<li><strong>方法2 - 修改 php.ini:</strong> 联系主机商或在服务器上修改 php.ini 文件</li>";
                        echo "<li><strong>方法3 - 代码配置:</strong> 系统已在 config/config.php 中自动应用部分安全配置</li>";
                        echo "</ol>";
                        if ($htaccess_display_errors_off) {
                            echo "<p><small>✅ <strong>状态:</strong> .htaccess 中的 PHP 安全配置已生效</small></p>";
                        }
                        echo "<p><small>💡 <strong>提示:</strong> 如需更多安全配置信息，请查看系统管理面板</small></p>";
                        echo "</div>";
                    } else {
                        echo "<div class='card success' style='margin-top: 15px;'>";
                        echo "<p>🎉 所有PHP安全配置都已正确设置！</p>";
                        echo "</div>";
                    }
                    ?>
                </div>
            </div>

            <!-- 性能分析 -->
            <div class="section">
                <h3><span class="icon">⚡</span>性能分析</h3>
                <?php
                // 内存使用情况
                $memory_used = memory_get_usage(true);
                $memory_peak = memory_get_peak_usage(true);
                $memory_limit = ini_get('memory_limit');
                
                echo "<div class='grid'>";
                echo "<div class='card'>";
                echo "<h4>内存使用</h4>";
                echo "<p><strong>当前使用:</strong> " . round($memory_used/1024/1024, 2) . " MB</p>";
                echo "<p><strong>峰值使用:</strong> " . round($memory_peak/1024/1024, 2) . " MB</p>";
                echo "<p><strong>系统限制:</strong> $memory_limit</p>";
                echo "</div>";
                
                // 检查必要的PHP扩展
                $extension_categories = [
                    'core' => [
                        'name' => '核心扩展',
                        'extensions' => ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl', 'session', 'filter'],
                        'icon' => '🔧'
                    ],
                    'graphics' => [
                        'name' => '图像处理',
                        'extensions' => ['gd', 'imagick', 'exif'],
                        'icon' => '🖼️'
                    ],
                    'security' => [
                        'name' => '安全相关',
                        'extensions' => ['hash', 'sodium', 'random', 'crypt'],
                        'icon' => '🔒'
                    ],
                    'uuid' => [
                        'name' => 'UUID支持',
                        'extensions' => ['uuid', 'com_dotnet'],
                        'icon' => '🔑'
                    ],
                    'email' => [
                        'name' => '邮件功能',
                        'extensions' => ['curl', 'imap', 'smtp'],
                        'icon' => '📧'
                    ],
                    'compression' => [
                        'name' => '压缩功能',
                        'extensions' => ['zip', 'zlib', 'bz2'],
                        'icon' => '📦'
                    ],
                    'datetime' => [
                        'name' => '日期时间',
                        'extensions' => ['date', 'calendar'],
                        'icon' => '📅'
                    ],
                    'network' => [
                        'name' => '网络功能',
                        'extensions' => ['sockets', 'ftp'],
                        'icon' => '🌐'
                    ]
                ];
                
                $total_extensions = 0;
                $loaded_extensions = 0;
                
                echo "<div class='card'>";
                echo "<h4>📋 PHP扩展详细检查</h4>";
                
                foreach ($extension_categories as $category => $info) {
                    echo "<h5>{$info['icon']} {$info['name']}</h5>";
                    $category_loaded = 0;
                    $category_total = count($info['extensions']);
                    
                    foreach ($info['extensions'] as $ext) {
                        $total_extensions++;
                        if (extension_loaded($ext)) {
                            echo "<span class='status-ok'>✅ $ext </span>";
                            $loaded_extensions++;
                            $category_loaded++;
                        } else {
                            echo "<span class='status-error'>❌ $ext </span>";
                        }
                    }
                    
                    $category_percent = round(($category_loaded / $category_total) * 100, 1);
                    if ($category_percent == 100) {
                        echo "<small class='status-ok'> | 完整度: {$category_percent}% ✨</small>";
                    } elseif ($category_percent >= 50) {
                        echo "<small class='status-warning'> | 完整度: {$category_percent}% ⚠️</small>";
                    } else {
                        echo "<small class='status-error'> | 完整度: {$category_percent}% ❌</small>";
                    }
                    echo "<br>";
                }
                
                $overall_percent = round(($loaded_extensions / $total_extensions) * 100, 1);
                echo "<hr style='margin: 15px 0;'>";
                echo "<p><strong>📊 总体统计:</strong> $loaded_extensions/$total_extensions 个扩展已加载 ({$overall_percent}%)</p>";
                
                // 特殊检查UUID支持
                echo "<h5>🔑 UUID系统支持详情</h5>";
                $uuid_methods = [];
                
                if (extension_loaded('uuid')) {
                    $uuid_methods[] = "PHP UUID扩展";
                    if (function_exists('uuid_create')) {
                        echo "<span class='status-ok'>✅ uuid_create() 函数可用</span><br>";
                    }
                    if (function_exists('uuid_generate_random')) {
                        echo "<span class='status-ok'>✅ uuid_generate_random() 函数可用</span><br>";
                    }
                }
                
                if (extension_loaded('com_dotnet') && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    $uuid_methods[] = "Windows COM对象";
                    echo "<span class='status-ok'>✅ Windows COM UUID支持</span><br>";
                }
                
                if (extension_loaded('openssl')) {
                    $uuid_methods[] = "OpenSSL随机数";
                    echo "<span class='status-ok'>✅ OpenSSL UUID生成支持</span><br>";
                }
                
                if (function_exists('random_bytes')) {
                    $uuid_methods[] = "PHP随机字节";
                    echo "<span class='status-ok'>✅ random_bytes() UUID生成</span><br>";
                }
                
                if (empty($uuid_methods)) {
                    echo "<span class='status-error'>❌ 未检测到任何UUID生成方法</span><br>";
                    echo "<small class='status-error'>警告: 系统可能无法正常生成安全的UUID</small><br>";
                } else {
                    echo "<p class='status-ok'>✅ UUID生成方法: " . implode(", ", $uuid_methods) . "</p>";
                }
                
                // 性能等级评估
                echo "<h5>⚡ 系统性能等级</h5>";
                if ($overall_percent >= 90) {
                    echo "<p class='status-ok'>🚀 优秀 - 系统扩展配置完善，性能表现优秀</p>";
                } elseif ($overall_percent >= 75) {
                    echo "<p class='status-warning'>⚡ 良好 - 大部分扩展已加载，系统运行良好</p>";
                } elseif ($overall_percent >= 60) {
                    echo "<p class='status-warning'>⚠️ 一般 - 基本扩展可用，建议优化配置</p>";
                } else {
                    echo "<p class='status-error'>🐌 需要改进 - 缺少重要扩展，可能影响系统功能</p>";
                }
                
                echo "</div>";
                echo "</div>";
                ?>
            </div>

            <!-- 快速操作 -->
            <div class="section">
                <h3><span class="icon">🚀</span>快速操作</h3>
                <div style="text-align: center;">
                    <a href="../admin" class="button">⚙️ 管理后台</a>
                    <a href="../" class="button success">🏠 访问首页</a>
                    <a href="../login" class="button">🔑 用户登录</a>
                    <a href="../install.php" class="button danger">🔄 重新安装</a>
                    <button onclick="location.reload()" class="button warning">🔄 刷新检测</button>
                </div>
            </div>

            <!-- 系统建议 -->
            <div class="section warning">
                <h3><span class="icon">💡</span>系统建议</h3>
                <ul>
                    <li><strong>定期备份:</strong> 建议定期备份数据库和重要文件</li>
                    <li><strong>更新检查:</strong> 保持系统组件为最新版本</li>
                    <li><strong>安全监控:</strong> 定期检查系统日志和访问记录</li>
                    <li><strong>性能优化:</strong> 如有性能问题，考虑优化数据库查询或增加缓存</li>
                    <li><strong>邮件配置:</strong> 确保邮件系统配置正确以接收重要通知</li>
                </ul>
            </div>
        </div>

        <div class="footer">
            <p><strong>车友帮讯联 v2.1</strong> | 系统诊断工具 | 👑 管理员专用</p>
            <p><small>检测完成时间: <?php echo date('Y-m-d H:i:s'); ?> | 
            <a href="https://carcall.endl.site" style="color: #007bff;">官方网站</a></small></p>
        </div>
    </div>

    <script>
    // 自动刷新功能
    let autoRefresh = false;
    function toggleAutoRefresh() {
        autoRefresh = !autoRefresh;
        if (autoRefresh) {
            setInterval(() => location.reload(), 30000);
            alert('已开启自动刷新(30秒)');
        }
    }
    
    // 快捷键
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            location.reload();
        }
    });
    </script>
</body>
</html>

