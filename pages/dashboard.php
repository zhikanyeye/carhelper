<?php
// pages/dashboard.php
// 检查是否已安装
if (!file_exists(__DIR__ . '/../config/installed.lock')) {
    header('Location: ' . (defined('SITE_URL') ? SITE_URL : '') . '/install.php');
    exit;
}

// 检查是否登录
if (!is_logged_in()) {
    header('Location: ' . (defined('SITE_URL') ? SITE_URL : '') . '/login');
    exit;
}

// 检查邮箱验证状态（除非邮件系统未配置）
if (function_exists('isEmailConfigured') && isEmailConfigured() && needs_email_verification()) {
    header('Location: ' . (defined('SITE_URL') ? SITE_URL : '') . '/resend_verification');
    exit;
}

// 确保数据库连接可用
ensure_database_connection();

$user = get_current_system_user();

// 防御性检查：确保用户数据格式正确
if (!$user || !is_array($user)) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log("Dashboard error: Invalid user data - " . gettype($user) . " for session user_id: " . ($_SESSION['user_id'] ?? 'undefined'));
    }
    
    // 显示用户友好的错误页面
    $page_title = '登录状态异常';
    ?>
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0"><i class="bi bi-exclamation-triangle"></i> 登录状态异常</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <p><strong>检测到登录状态异常，可能的原因：</strong></p>
                        <ul class="mb-3">
                            <li>用户数据已被删除或损坏</li>
                            <li>数据库连接问题</li>
                            <li>Session配置异常</li>
                        </ul>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="<?php echo defined('SITE_URL') ? SITE_URL : ''; ?>/pages/logout.php" class="btn btn-primary">
                            <i class="bi bi-box-arrow-right"></i> 重新登录
                        </a>
                        <a href="<?php echo defined('SITE_URL') ? SITE_URL : ''; ?>/diagnostic/status.php" class="btn btn-outline-secondary">
                            <i class="bi bi-clipboard-check"></i> 系统状态检查
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    exit;
}

// 确保用户有UUID，如果没有则生成一个
if (empty($user['uuid'])) {
    $user_uuid = generate_uuid();
    try {
        $stmt = $pdo->prepare("UPDATE users SET uuid = ? WHERE id = ?");
        $stmt->execute([$user_uuid, $user['id']]);
        $user['uuid'] = $user_uuid; // 更新当前用户数据
        
        // 成功生成UUID，可以显示一个提示信息
        $success_message = 'UUID已自动生成，系统已更新';
    } catch (PDOException $e) {
        // 如果uuid字段不存在，尝试添加字段
        if (strpos($e->getMessage(), 'Unknown column') !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
            try {
                // 尝试添加uuid字段
                $pdo->exec("ALTER TABLE users ADD COLUMN uuid VARCHAR(36) UNIQUE");
                
                // 重试更新UUID
                $stmt = $pdo->prepare("UPDATE users SET uuid = ? WHERE id = ?");
                $stmt->execute([$user_uuid, $user['id']]);
                $user['uuid'] = $user_uuid;
                
                $success_message = 'UUID字段已创建并生成，系统已更新';
            } catch (PDOException $alter_e) {
                // 如果添加字段也失败，记录错误但继续运行
                error_log("Failed to add UUID column: " . $alter_e->getMessage());
                error_log("Original UUID update error: " . $e->getMessage());
                
                // 使用临时UUID继续运行，但不保存到数据库
                $user['uuid'] = $user_uuid;
                $error_message = 'UUID字段添加失败，使用临时UUID。建议联系管理员检查数据库权限。';
            }
        } else {
            // 其他数据库错误，记录但继续运行
            error_log("UUID update failed: " . $e->getMessage());
            
            // 使用临时UUID继续运行
            $user['uuid'] = $user_uuid;
            $error_message = 'UUID更新失败，使用临时UUID。建议联系管理员。';
        }
    }
}

$success_message = '';
$error_message = '';

// 处理生成二维码请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_qr'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = '安全验证失败，请重试';
    } else {
        // 直接生成二维码，generate_qr_code函数会处理UUID相关逻辑
        $qr_filename = generate_qr_code($user['uuid'] ?? $user['id']);
        
        if ($qr_filename) {
            // 更新数据库中的二维码文件名
            $stmt = $pdo->prepare("UPDATE users SET qr_code = ? WHERE id = ?");
            $stmt->execute([$qr_filename, $user['id']]);
            
            $success_message = '二维码生成成功！';
            $user['qr_code'] = $qr_filename;
        } else {
            $error_message = '二维码生成失败，请重试';
        }
    }
}

// 获取用户统计数据
$total_messages = get_user_message_count($user['id']);
$unread_messages = get_unread_message_count($user['id']);

// 获取最近的留言
$stmt = $pdo->prepare("SELECT * FROM messages WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user['id']]);
$recent_messages = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-speedometer2"></i> 个人面板</h2>
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <span class="text-muted">欢迎回来，</span>
                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                    <?php if ($user['is_admin']): ?>
                    <span class="badge bg-danger ms-2">
                        <i class="bi bi-shield-check"></i> 管理员
                    </span>
                    <?php endif; ?>
                </div>
                <?php if ($user['is_admin']): ?>
                <a href="<?php echo url('管理员后台'); ?>" class="btn btn-danger btn-sm">
                    <i class="bi bi-gear"></i> 管理后台
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle"></i> <?php echo $error_message; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($user['is_admin']): ?>
        <!-- 管理员身份标识 -->
        <div class="alert alert-info mb-4">
            <div class="d-flex align-items-center">
                <i class="bi bi-shield-check-fill me-3" style="font-size: 1.5rem;"></i>
                <div class="flex-grow-1">
                    <h6 class="mb-1"><strong>管理员用户</strong></h6>
                    <small>您拥有系统管理权限</small>
                </div>
                <div class="ms-3">
                    <a href="<?php echo url('管理员后台'); ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-gear"></i> 管理后台
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- 个人统计卡片 -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="h3 mb-0"><?php echo $total_messages; ?></div>
                    <div>我的留言数</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="h3 mb-0"><?php echo $unread_messages; ?></div>
                    <div>未读留言</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="h3 mb-0"><?php echo user_qr_code_exists($user['id']) ? '已生成' : '未生成'; ?></div>
                    <div>二维码状态</div>
                </div>
            </div>
        </div>
        
        <!-- 最近留言 -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-chat-left-text"></i> 最近留言</h5>
                <a href="<?php echo url('查看留言'); ?>" class="btn btn-sm btn-outline-primary">查看全部</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_messages)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                    <p class="mt-2">暂无留言</p>
                    <p>分享你的二维码给朋友，开始接收留言吧！</p>
                </div>
                <?php else: ?>
                <?php foreach ($recent_messages as $message): ?>
                <div class="message-card <?php echo $message['is_read'] ? '' : 'unread'; ?>" data-message-id="<?php echo $message['id']; ?>">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <small class="text-muted">
                            <i class="bi bi-clock"></i> <?php echo time_ago($message['created_at']); ?>
                        </small>
                        <?php if (!$message['is_read']): ?>
                        <span class="badge bg-danger">新</span>
                        <?php endif; ?>
                    </div>
                    <p class="mb-2"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                    <?php if ($message['contact_info']): ?>
                    <small class="text-muted">
                        <i class="bi bi-person"></i> 联系方式：<?php echo htmlspecialchars($message['contact_info']); ?>
                    </small>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- 二维码管理 -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-qr-code"></i> 我的二维码</h5>
            </div>
            <div class="card-body">
                <?php 
                // 检查二维码文件是否存在 - 优先检查UUID文件
                $qr_exists = false;
                $qr_file_path = '';
                
                // 首先检查基于UUID的文件
                if (!empty($user['uuid'])) {
                    $uuid_file = $user['uuid'] . '.svg';
                    if (file_exists("qrcodes/" . $uuid_file)) {
                        $qr_exists = true;
                        $qr_file_path = $uuid_file;
                    }
                }
                
                // 如果UUID文件不存在，检查数据库中记录的文件
                if (!$qr_exists && !empty($user['qr_code']) && file_exists("qrcodes/" . $user['qr_code'])) {
                    $qr_exists = true;
                    $qr_file_path = $user['qr_code'];
                }
                ?>
                
                <?php if ($qr_exists): ?>
                <div class="qr-code-container">
                    <div class="qr-code mb-3">
                        <img src="qrcodes/<?php echo $qr_file_path; ?>" alt="我的二维码" class="img-fluid">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">分享链接：</label>
                        <div class="input-group">
                        </div>
                    </div>
                    <div class="d-grid mb-2">
                        <a href="<?php echo url('挪车通知单'); ?>" target="_blank" class="btn btn-outline-primary">
                            <i class="bi bi-printer"></i> 挪车通知单
                        </a>
                    </div>
                            <input type="text" class="form-control form-control-sm" 
                                   value="<?php echo url('留言板?uuid=' . $user['uuid']); ?>" 
                                   id="shareLink" readonly>
                            <button class="btn btn-outline-primary btn-sm" type="button" 
                                    onclick="copyToClipboard(document.getElementById('shareLink').value)"
                                    title="点击复制分享链接">
                                <i class="bi bi-copy"></i> 复制
                            </button>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <a href="qrcodes/<?php echo $qr_file_path; ?>" download class="btn btn-success btn-sm">
                            <i class="bi bi-download"></i> 下载二维码
                        </a>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <button type="submit" name="generate_qr" class="btn btn-outline-primary btn-sm w-100">
                                <i class="bi bi-arrow-clockwise"></i> 重新生成
                            </button>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center">
                    <i class="bi bi-qr-code text-muted" style="font-size: 4rem;"></i>
                    <p class="text-muted mt-3">还没有生成二维码</p>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <button type="submit" name="generate_qr" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> 生成二维码
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 快捷操作 -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-lightning"></i> 快捷操作</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?php echo url('查看留言'); ?>" class="btn btn-outline-primary">
                        <i class="bi bi-envelope"></i> 查看所有留言
                        <?php if ($unread_messages > 0): ?>
                        <span class="badge bg-danger ms-1"><?php echo $unread_messages; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <a href="<?php echo url('留言板?uuid=' . $user['uuid']); ?>" class="btn btn-outline-success" target="_blank">
                        <i class="bi bi-eye"></i> 预览留言页面
                    </a>
                    
                    <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#helpModal">
                        <i class="bi bi-question-circle"></i> 使用帮助
                    </button>
                </div>
            </div>
        </div>
        
        <!-- 账户信息 -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person-gear"></i> 账户信息</h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-4">用户名：</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($user['username']); ?></dd>
                    
                    <dt class="col-sm-4">邮箱：</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($user['email']); ?></dd>
                    
                    <dt class="col-sm-4">注册时间：</dt>
                    <dd class="col-sm-8"><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<!-- 帮助模态框 -->
<div class="modal fade" id="helpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-question-circle"></i> 使用帮助</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>如何使用车友帮讯联匿名留言系统？</h6>
                <ol>
                    <li><strong>生成二维码：</strong>点击"生成二维码"按钮，系统会为你创建专属的二维码</li>
                    <li><strong>打印贴码：</strong>下载并打印二维码，贴在你的车上或需要留言的物品上</li>
                    <li><strong>接收留言：</strong>其他人扫描二维码后可以给你留言</li>
                    <li><strong>查看留言：</strong>在"查看留言"页面可以看到所有收到的留言</li>
                    <li><strong>邮件通知：</strong>收到新留言时，系统会自动发送邮件通知</li>
                </ol>
                
                <h6>注意事项：</h6>
                <ul>
                    <li>请确保邮箱地址正确，以便接收通知</li>
                    <li>二维码可以重新生成，但旧的二维码将失效</li>
                    <li>留言是匿名的，但留言者可以选择留下联系方式</li>
                    <li>请妥善保管你的账号信息</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
            </div>
        </div>
    </div>
</div>
