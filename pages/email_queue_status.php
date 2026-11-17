<?php
// 注意：不在此处提前 session_start()，由 functions.php 统一按 config.php 的安全配置启动
require_once '../config/database.php';
require_once '../config/email.php';
require_once '../includes/functions.php';

// 站点根地址（本页直接访问时可能位于 /pages/ 下，去掉该段得到根路径）
if (!defined('SITE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $dir_name = dirname(str_replace('\\', '/', $_SERVER['SCRIPT_NAME']));
    $base_path = ($dir_name === '/' || $dir_name === '\\') ? '' : str_replace('/pages', '', $dir_name);
    define('SITE_URL', $protocol . $host . $base_path);
}

// 检查是否为管理员
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

$stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !$user['is_admin']) {
    header('Location: ' . SITE_URL . '/dashboard');
    exit;
}

// 处理操作
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF 校验（支持表单字段或 X-CSRF-Token 请求头）
    $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $message = '安全验证失败，请刷新页面重试';
    } elseif (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'clean_queue':
                $cleaned = cleanupEmailQueue();
                $message = "已清理 {$cleaned} 个过期邮件队列文件";
                break;
            case 'process_queue':
                // 手动触发邮件处理
                $scriptPath = __DIR__ . '/../includes/process_emails.php';
                if (file_exists($scriptPath)) {
                    if (PHP_OS_FAMILY === 'Windows') {
                        $cmd = "start /B php \"$scriptPath\" > NUL 2>&1";
                    } else {
                        $cmd = "php \"$scriptPath\" > /dev/null 2>&1 &";
                    }
                    @exec($cmd);
                    $message = "邮件处理任务已启动";
                } else {
                    $message = "邮件处理脚本不存在";
                }
                break;
        }
    }
}

// 获取邮件队列状态
$queue_count = function_exists('getQueuedEmailCount') ? getQueuedEmailCount() : 0;
$email_configured = function_exists('isEmailConfigured') ? isEmailConfigured() : false;

// 获取最近的邮件队列文件
$recent_emails = [];
if (defined('EMAIL_QUEUE_DIR') && file_exists(EMAIL_QUEUE_DIR)) {
    $files = glob(EMAIL_QUEUE_DIR . '/*.json');
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    foreach (array_slice($files, 0, 10) as $file) { // 只显示最近10个
        $data = json_decode(file_get_contents($file), true);
        if ($data) {
            $recent_emails[] = $data;
        }
    }
}

include '../includes/header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>📧 邮件队列状态管理</h4>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    
                    <!-- 邮件系统状态 -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card <?php echo $email_configured ? 'bg-success' : 'bg-danger'; ?> text-white">
                                <div class="card-body text-center">
                                    <h3><?php echo $email_configured ? '✅' : '❌'; ?></h3>
                                    <p>邮件配置</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h3><?php echo $queue_count; ?></h3>
                                    <p>待发送邮件</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h3><?php echo defined('EMAIL_QUEUE_DIR') && file_exists(EMAIL_QUEUE_DIR) ? '✅' : '❌'; ?></h3>
                                    <p>队列目录</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-secondary text-white">
                                <div class="card-body text-center">
                                    <h3><?php echo file_exists(__DIR__ . '/../includes/process_emails.php') ? '✅' : '❌'; ?></h3>
                                    <p>处理脚本</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 操作按钮 -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="process_queue">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <button type="submit" class="btn btn-primary">🚀 手动处理邮件队列</button>
                            </form>

                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="clean_queue">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <button type="submit" class="btn btn-warning" onclick="return confirm('确定要清理过期的邮件队列吗？')">🧹 清理过期队列</button>
                            </form>
                            
                            <a href="admin.php" class="btn btn-secondary">📊 返回管理面板</a>
                        </div>
                    </div>
                    
                    <!-- 最近邮件队列 -->
                    <?php if (!empty($recent_emails)): ?>
                    <div class="table-responsive">
                        <h5>最近的邮件队列（最多显示10条）</h5>
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>收件人</th>
                                    <th>主题</th>
                                    <th>创建时间</th>
                                    <th>优先级</th>
                                    <th>状态</th>
                                    <th>尝试次数</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_emails as $email): ?>
                                <tr>
                                    <td>
                                        <small><?php echo htmlspecialchars(substr($email['id'], -8)); ?>...</small>
                                    </td>
                                    <td><?php echo htmlspecialchars($email['to']); ?></td>
                                    <td>
                                        <small><?php echo htmlspecialchars(mb_substr($email['subject'], 0, 30) . (mb_strlen($email['subject']) > 30 ? '...' : '')); ?></small>
                                    </td>
                                    <td>
                                        <small><?php echo date('m-d H:i', $email['created_at']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $email['priority'] === 'high' ? 'danger' : 
                                                ($email['priority'] === 'normal' ? 'primary' : 'secondary'); 
                                        ?>">
                                            <?php echo htmlspecialchars($email['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $email['status'] === 'sent' ? 'success' : 
                                                ($email['status'] === 'failed' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo htmlspecialchars($email['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $email['attempts']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 当前没有邮件队列文件
                    </div>
                    <?php endif; ?>
                    
                    <!-- 配置信息 -->
                    <div class="mt-4">
                        <h5>配置信息</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>SMTP主机</span>
                                        <span><?php echo defined('SMTP_HOST') ? (SMTP_HOST ?: '未配置') : '未定义'; ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>SMTP端口</span>
                                        <span><?php echo defined('SMTP_PORT') ? SMTP_PORT : '未定义'; ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>SMTP用户名</span>
                                        <span><?php echo defined('SMTP_USERNAME') ? (SMTP_USERNAME ? '已配置' : '未配置') : '未定义'; ?></span>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>队列目录</span>
                                        <span><?php echo defined('EMAIL_QUEUE_DIR') ? '已定义' : '未定义'; ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>处理脚本</span>
                                        <span><?php echo file_exists(__DIR__ . '/../includes/process_emails.php') ? '存在' : '不存在'; ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>PHP执行函数</span>
                                        <span><?php echo function_exists('exec') ? '可用' : '不可用'; ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px 10px 0 0 !important;
}

.table-responsive {
    border-radius: 8px;
    overflow: hidden;
}

.badge {
    font-size: 0.8em;
}

.list-group-item {
    border: none;
    padding: 0.5rem 0;
}
</style>

<?php include '../includes/footer.php'; ?>