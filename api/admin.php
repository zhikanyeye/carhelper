<?php
// 管理员API接口
// 智能路径解析
$config_path = file_exists('../config/config.php') ? '../config/config.php' : __DIR__ . '/../config/config.php';
$functions_path = file_exists('../includes/functions.php') ? '../includes/functions.php' : __DIR__ . '/../includes/functions.php';

require_once $config_path;
require_once $functions_path;

// 检查权限
if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => '权限不足']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'users':
        $users = get_users_list();
        echo json_encode(['html' => render_users_table($users)]);
        break;
        
    case 'messages':
        $messages = get_recent_messages();
        echo json_encode(['html' => render_messages_table($messages)]);
        break;
        
    case 'auto_cleanup':
        // 自动清理接口
        try {
            $cleanup_enabled = get_setting('auto_cleanup', 0);
            if (!$cleanup_enabled) {
                echo json_encode(['success' => false, 'message' => '自动清理功能未启用']);
                break;
            }
            
            $cleanup_days = get_setting('cleanup_days', 90);
            $result = cleanup_old_data($cleanup_days);
            
            if ($result) {
                // 记录清理日志
                $log_message = "自动清理执行成功，清理了 {$cleanup_days} 天前的数据";
                log_admin_action('auto_cleanup', $log_message);
                echo json_encode(['success' => true, 'message' => $log_message]);
            } else {
                echo json_encode(['success' => false, 'message' => '自动清理执行失败，请检查日志']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => '清理过程出现错误: ' . $e->getMessage()]);
        }
        break;
        
    case 'test_email':
        if (function_exists('testEmailConnection')) {
            $result = testEmailConnection();
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => '邮件测试功能不可用']);
        }
        break;
        
    case 'verify_user_email':
        $user_id = $_POST['user_id'] ?? 0;
        if ($user_id && verify_user_email_admin($user_id)) {
            echo json_encode(['success' => true, 'message' => '用户邮箱已标记为已验证']);
        } else {
            echo json_encode(['success' => false, 'message' => '操作失败']);
        }
        break;
        
    case 'delete_user':
        $user_id = $_POST['user_id'] ?? 0;
        $admin_id = get_current_system_user()['id'] ?? null;
        
        if ($user_id) {
            $result = admin_delete_user($user_id, $admin_id);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => '无效的用户ID']);
        }
        break;
        
    case 'blacklist_user':
        $user_id = $_POST['user_id'] ?? 0;
        $reason = $_POST['reason'] ?? '违规行为';
        $admin_id = get_current_system_user()['id'] ?? null;
        
        if ($user_id) {
            $result = admin_blacklist_user($user_id, $reason, $admin_id);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => '无效的用户ID']);
        }
        break;
        
    case 'blacklist_list':
        $blacklist = get_blacklist();
        echo json_encode(['html' => render_blacklist_table($blacklist)]);
        break;
        
    case 'add_blacklist':
        $email = $_POST['email'] ?? '';
        $reason = $_POST['reason'] ?? '管理员拉黑';
        $admin_id = get_current_system_user()['id'] ?? null;
        
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result = add_email_to_blacklist($email, $reason, $admin_id);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => '请输入有效的邮箱地址']);
        }
        break;
        
    case 'remove_blacklist':
        $email = $_POST['email'] ?? '';
        
        if ($email) {
            $result = remove_email_from_blacklist($email);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => '无效的邮箱地址']);
        }
        break;
        
    case 'blacklist_count':
        $count = get_blacklist_count();
        echo json_encode(['count' => $count]);
        break;
        
    case 'email_queue_status':
        require_once '../config/email.php';
        
        $queue_count = function_exists('getQueuedEmailCount') ? getQueuedEmailCount() : 0;
        $email_configured = function_exists('isEmailConfigured') ? isEmailConfigured() : false;
        
        // 获取最近的邮件队列信息
        $recent_html = '';
        if (defined('EMAIL_QUEUE_DIR') && file_exists(EMAIL_QUEUE_DIR) && $queue_count > 0) {
            $files = glob(EMAIL_QUEUE_DIR . '/*.json');
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            $recent_html = '<h6>最近队列状态（最多5条）</h6><div class="table-responsive"><table class="table table-sm">';
            $recent_html .= '<thead><tr><th>收件人</th><th>主题</th><th>时间</th><th>状态</th></tr></thead><tbody>';
            
            foreach (array_slice($files, 0, 5) as $file) {
                $data = json_decode(file_get_contents($file), true);
                if ($data) {
                    $recent_html .= '<tr>';
                    $recent_html .= '<td>' . htmlspecialchars($data['to']) . '</td>';
                    $recent_html .= '<td>' . htmlspecialchars(mb_substr($data['subject'], 0, 20) . '...') . '</td>';
                    $recent_html .= '<td>' . date('m-d H:i', $data['created_at']) . '</td>';
                    $recent_html .= '<td><span class="badge badge-' . ($data['status'] === 'sent' ? 'success' : 'warning') . '">' . $data['status'] . '</span></td>';
                    $recent_html .= '</tr>';
                }
            }
            $recent_html .= '</tbody></table></div>';
        }
        
        echo json_encode([
            'success' => true,
            'queue_count' => $queue_count,
            'email_configured' => $email_configured,
            'html' => $recent_html
        ]);
        break;
        
    case 'process_email_queue':
        require_once '../config/email.php';
        
        $scriptPath = __DIR__ . '/../includes/process_emails.php';
        if (file_exists($scriptPath)) {
            if (PHP_OS_FAMILY === 'Windows') {
                $cmd = "start /B php \"$scriptPath\" > NUL 2>&1";
            } else {
                $cmd = "php \"$scriptPath\" > /dev/null 2>&1 &";
            }
            $result = @exec($cmd);
            echo json_encode(['success' => true, 'message' => '邮件处理任务已启动']);
        } else {
            echo json_encode(['success' => false, 'message' => '邮件处理脚本不存在']);
        }
        break;
        
    case 'clean_email_queue':
        require_once '../config/email.php';
        
        if (function_exists('cleanupEmailQueue')) {
            $cleaned = cleanupEmailQueue();
            echo json_encode(['success' => true, 'cleaned' => $cleaned, 'message' => "已清理 {$cleaned} 个过期文件"]);
        } else {
            echo json_encode(['success' => false, 'message' => '清理函数不可用']);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => '无效的操作']);
}

function get_users_list() {
    global $pdo;
    $stmt = $pdo->query("SELECT id, username, email, is_admin, email_verified, created_at, 
                        (SELECT COUNT(*) FROM messages WHERE user_id = users.id) as message_count
                        FROM users ORDER BY created_at DESC LIMIT 20");
    return $stmt->fetchAll();
}

function get_recent_messages() {
    global $pdo;
    $stmt = $pdo->query("SELECT m.*, u.username 
                        FROM messages m 
                        JOIN users u ON m.user_id = u.id 
                        ORDER BY m.created_at DESC LIMIT 20");
    return $stmt->fetchAll();
}

function render_users_table($users) {
    if (empty($users)) {
        return '<p class="text-center text-muted">暂无用户数据</p>';
    }
    
    $html = '<div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户名</th>
                            <th>邮箱</th>
                            <th>角色</th>
                            <th>邮箱验证</th>
                            <th>留言数</th>
                            <th>注册时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>';
    
    foreach ($users as $user) {
        $verify_btn = '';
        $delete_btn = '';
        $blacklist_btn = '';
        
        // 邮箱验证按钮
        if (!$user['email_verified']) {
            $verify_btn = '<button class="btn btn-sm btn-success me-1" onclick="verifyUserEmail(' . $user['id'] . ')" title="验证邮箱">
                             <i class="bi bi-check-circle"></i>
                           </button>';
        }
        
        // 删除用户按钮（不能删除管理员）
        if (!$user['is_admin']) {
            $delete_btn = '<button class="btn btn-sm btn-warning me-1" onclick="deleteUser(' . $user['id'] . ', \'' . htmlspecialchars($user['username']) . '\')" title="删除用户">
                             <i class="bi bi-person-dash"></i>
                           </button>';
            
            $blacklist_btn = '<button class="btn btn-sm btn-danger" onclick="blacklistUser(' . $user['id'] . ', \'' . htmlspecialchars($user['username']) . '\')" title="拉入黑名单">
                               <i class="bi bi-person-x"></i>
                             </button>';
        }
        
        $html .= '<tr>
                    <td>' . $user['id'] . '</td>
                    <td>' . htmlspecialchars($user['username']) . '</td>
                    <td>' . htmlspecialchars($user['email']) . '</td>
                    <td>' . ($user['is_admin'] ? '<span class="badge bg-danger">管理员</span>' : '<span class="badge bg-secondary">用户</span>') . '</td>
                    <td>' . ($user['email_verified'] ? '<span class="badge bg-success">已验证</span>' : '<span class="badge bg-warning">未验证</span>') . '</td>
                    <td>' . $user['message_count'] . '</td>
                    <td>' . date('Y-m-d H:i', strtotime($user['created_at'])) . '</td>
                    <td class="text-nowrap">' . $verify_btn . $delete_btn . $blacklist_btn . '</td>
                  </tr>';
    }
    
    $html .= '</tbody></table></div>';
    return $html;
}

function render_messages_table($messages) {
    if (empty($messages)) {
        return '<p class="text-center text-muted">暂无留言数据</p>';
    }
    
    $html = '<div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>接收用户</th>
                            <th>留言内容</th>
                            <th>状态</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody>';
    
    foreach ($messages as $message) {
        $content = mb_strlen($message['message']) > 50 ? 
                   mb_substr($message['message'], 0, 50) . '...' : 
                   $message['message'];
        
        $html .= '<tr>
                    <td>' . $message['id'] . '</td>
                    <td>' . htmlspecialchars($message['username']) . '</td>
                    <td>' . htmlspecialchars($content) . '</td>
                    <td>' . ($message['is_read'] ? '<span class="badge bg-success">已读</span>' : '<span class="badge bg-warning">未读</span>') . '</td>
                    <td>' . time_ago($message['created_at']) . '</td>
                  </tr>';
    }
    
    $html .= '</tbody></table></div>';
    return $html;
}

// 管理员验证用户邮箱
function verify_user_email_admin($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE users SET email_verified = 1 WHERE id = ?");
    return $stmt->execute([$user_id]);
}

// 获取黑名单列表
function get_blacklist() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM email_blacklist ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

// 获取黑名单数量
function get_blacklist_count() {
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) FROM email_blacklist");
    return $stmt->fetchColumn();
}

// 渲染黑名单表格
function render_blacklist_table($blacklist) {
    if (empty($blacklist)) {
        return '<p class="text-center text-muted">暂无黑名单邮箱</p>';
    }
    
    $html = '<div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>邮箱地址</th>
                            <th>拉黑原因</th>
                            <th>添加时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>';
    
    foreach ($blacklist as $item) {
        $html .= '<tr>
                    <td>' . $item['id'] . '</td>
                    <td>' . htmlspecialchars($item['email']) . '</td>
                    <td>' . htmlspecialchars($item['reason']) . '</td>
                    <td>' . date('Y-m-d H:i', strtotime($item['created_at'])) . '</td>
                    <td>
                        <button class="btn btn-sm btn-success" onclick="removeFromBlacklist(\'' . htmlspecialchars($item['email']) . '\')" title="移出黑名单">
                            <i class="bi bi-check-circle"></i> 移除
                        </button>
                    </td>
                  </tr>';
    }
    
    $html .= '</tbody></table></div>';
    return $html;
}
?>
