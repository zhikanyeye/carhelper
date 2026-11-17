<?php
// pages/view_messages.php
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

// 防御性检查：确保用户数据和数据库连接正确
if (!$user || !is_array($user)) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log("View_messages error: Invalid user data - " . gettype($user) . " for session user_id: " . ($_SESSION['user_id'] ?? 'undefined'));
    }
    
    // 显示用户友好的错误页面
    $page_title = '数据访问错误';
    ?>
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h4 class="mb-0"><i class="bi bi-exclamation-triangle"></i> 数据访问错误</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger">
                        <p><strong>无法获取用户数据，可能的原因：</strong></p>
                        <ul class="mb-3">
                            <li>用户账号已被删除</li>
                            <li>数据库连接中断</li>
                            <li>数据表结构损坏</li>
                        </ul>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="<?php echo url('logout'); ?>" class="btn btn-primary">
                            <i class="bi bi-box-arrow-right"></i> 退出并重新登录
                        </a>
                        <a href="<?php echo url('diagnostic/status.php'); ?>" class="btn btn-outline-info">
                            <i class="bi bi-clipboard-check"></i> 系统状态
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    exit;
}

$success_message = '';
$error_message = '';

// 处理标记已读请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = '安全验证失败，请重试';
    } else {
        $message_id = (int)($_POST['message_id'] ?? 0);
        
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$message_id, $user['id']])) {
            $success_message = '已标记为已读';
        }
    }
}

// 处理删除留言请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = '安全验证失败，请重试';
    } else {
        $message_id = (int)($_POST['message_id'] ?? 0);
        
        $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$message_id, $user['id']])) {
            $success_message = '留言已删除';
        }
    }
}

// 处理批量操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = '安全验证失败，请重试';
    } else {
        $action = $_POST['batch_action'];
        $message_ids = $_POST['message_ids'] ?? [];
        
        if (!empty($message_ids) && is_array($message_ids)) {
            $placeholders = str_repeat('?,', count($message_ids) - 1) . '?';
            $params = array_merge($message_ids, [$user['id']]);
            
            if ($action === 'mark_read') {
                $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id IN ($placeholders) AND user_id = ?");
                if ($stmt->execute($params)) {
                    $success_message = '已批量标记为已读';
                }
            } elseif ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM messages WHERE id IN ($placeholders) AND user_id = ?");
                if ($stmt->execute($params)) {
                    $success_message = '已批量删除留言';
                }
            }
        }
    }
}

// 获取筛选和分页参数（filter 白名单校验，防止反射型 XSS）
$allowed_filters = ['all', 'unread', 'read'];
$filter = in_array($_GET['filter'] ?? 'all', $allowed_filters, true) ? $_GET['filter'] : 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// 构建查询条件
$where_clause = "WHERE user_id = ?";
$params = [$user['id']];

if ($filter === 'unread') {
    $where_clause .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $where_clause .= " AND is_read = 1";
}

// 获取总数
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM messages $where_clause");
$count_stmt->execute($params);
$total_messages = $count_stmt->fetchColumn();
$total_pages = ceil($total_messages / $per_page);

// 获取留言列表
$stmt = $pdo->prepare("SELECT * FROM messages $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?");
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$messages = $stmt->fetchAll();

// 设置页面标题
$page_title = '我的留言';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-envelope"></i> 我的留言</h2>
    <div class="d-flex gap-2">
        <!-- 筛选按钮 -->
        <div class="btn-group" role="group">
            <a href="?filter=all" class="btn btn-outline-primary <?php echo $filter === 'all' ? 'active' : ''; ?>">
                全部 (<?php echo get_user_message_count($user['id']); ?>)
            </a>
            <a href="?filter=unread" class="btn btn-outline-warning <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                未读 (<?php echo get_unread_message_count($user['id']); ?>)
            </a>
            <a href="?filter=read" class="btn btn-outline-success <?php echo $filter === 'read' ? 'active' : ''; ?>">
                已读
            </a>
        </div>
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

<?php if (empty($messages)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-inbox text-muted" style="font-size: 4rem;"></i>
        <h4 class="mt-3">
            <?php 
            if ($filter === 'unread') {
                echo '暂无未读留言';
            } elseif ($filter === 'read') {
                echo '暂无已读留言';
            } else {
                echo '暂无留言';
            }
            ?>
        </h4>
        <p class="text-muted">
            <?php if ($filter === 'all'): ?>
            分享你的二维码给朋友，开始接收留言吧！
            <br><a href="<?php echo defined('SITE_URL') ? SITE_URL : ''; ?>/dashboard" class="btn btn-primary mt-2">前往个人面板</a>
            <?php else: ?>
            <a href="?filter=all" class="btn btn-outline-primary">查看所有留言</a>
            <?php endif; ?>
        </p>
    </div>
</div>
<?php else: ?>

<!-- 批量操作工具栏 -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="POST" id="batchForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="d-flex align-items-center gap-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="selectAll">
                    <label class="form-check-label" for="selectAll">全选</label>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" name="batch_action" value="mark_read" class="btn btn-sm btn-outline-success" disabled id="markReadBtn">
                        <i class="bi bi-check"></i> 标记已读
                    </button>
                    <button type="submit" name="batch_action" value="delete" class="btn btn-sm btn-outline-danger" disabled id="deleteBtn" 
                            onclick="return confirm('确定要删除选中的留言吗？')">
                        <i class="bi bi-trash"></i> 删除
                    </button>
                </div>
                <span class="text-muted ms-auto">共 <?php echo $total_messages; ?> 条留言</span>
            </div>
        </form>
    </div>
</div>

<!-- 留言列表 -->
<?php foreach ($messages as $message): ?>
<div class="message-card <?php echo $message['is_read'] ? '' : 'unread'; ?>" data-message-id="<?php echo $message['id']; ?>">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div class="d-flex align-items-center gap-3">
            <div class="form-check">
                <input class="form-check-input message-checkbox" type="checkbox" name="message_ids[]" value="<?php echo $message['id']; ?>">
            </div>
            <div>
                <small class="text-muted">
                    <i class="bi bi-clock"></i> <?php echo time_ago($message['created_at']); ?>
                    <span class="ms-2"><?php echo date('Y-m-d H:i', strtotime($message['created_at'])); ?></span>
                </small>
                <?php if (!$message['is_read']): ?>
                <span class="badge bg-danger ms-2">新</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                操作
            </button>
            <ul class="dropdown-menu">
                <?php if (!$message['is_read']): ?>
                <li>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                        <button type="submit" name="mark_read" class="dropdown-item">
                            <i class="bi bi-check"></i> 标记已读
                        </button>
                    </form>
                </li>
                <?php endif; ?>
                <li>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                        <button type="submit" name="delete_message" class="dropdown-item text-danger" 
                                onclick="return confirm('确定要删除这条留言吗？')">
                            <i class="bi bi-trash"></i> 删除
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
    
    <div class="message-content mb-3">
        <p class="mb-0"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
    </div>
    
    <?php if ($message['contact_info']): ?>
    <div class="border-top pt-2">
        <small class="text-muted">
            <i class="bi bi-person"></i> 联系方式：
            <span class="text-primary"><?php echo htmlspecialchars($message['contact_info']); ?></span>
        </small>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<!-- 分页 -->
<?php if ($total_pages > 1): ?>
<nav aria-label="留言分页">
    <ul class="pagination justify-content-center">
        <?php if ($page > 1): ?>
        <li class="page-item">
            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $page - 1; ?>">上一页</a>
        </li>
        <?php endif; ?>
        
        <?php
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        ?>
        
        <?php if ($start_page > 1): ?>
        <li class="page-item">
            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=1">1</a>
        </li>
        <?php if ($start_page > 2): ?>
        <li class="page-item disabled"><span class="page-link">...</span></li>
        <?php endif; ?>
        <?php endif; ?>
        
        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
        </li>
        <?php endfor; ?>
        
        <?php if ($end_page < $total_pages): ?>
        <?php if ($end_page < $total_pages - 1): ?>
        <li class="page-item disabled"><span class="page-link">...</span></li>
        <?php endif; ?>
        <li class="page-item">
            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
        </li>
        <?php endif; ?>
        
        <?php if ($page < $total_pages): ?>
        <li class="page-item">
            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $page + 1; ?>">下一页</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const messageCheckboxes = document.querySelectorAll('.message-checkbox');
    const markReadBtn = document.getElementById('markReadBtn');
    const deleteBtn = document.getElementById('deleteBtn');
    
    // 全选/取消全选
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            messageCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBatchButtons();
        });
    }
    
    // 监听单个复选框变化
    messageCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // 更新全选状态
            const checkedCount = document.querySelectorAll('.message-checkbox:checked').length;
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = checkedCount === messageCheckboxes.length;
                selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < messageCheckboxes.length;
            }
            updateBatchButtons();
        });
    });
    
    // 更新批量操作按钮状态
    function updateBatchButtons() {
        const checkedCount = document.querySelectorAll('.message-checkbox:checked').length;
        const hasSelected = checkedCount > 0;
        
        if (markReadBtn) markReadBtn.disabled = !hasSelected;
        if (deleteBtn) deleteBtn.disabled = !hasSelected;
    }
    
    // 自动标记为已读（当用户查看留言时）
    const unreadMessages = document.querySelectorAll('.message-card.unread');
    if (unreadMessages.length > 0) {
        // 使用Intersection Observer来检测留言是否进入视窗
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const messageCard = entry.target;
                    const messageId = messageCard.dataset.messageId;
                    
                    // 延迟标记为已读
                    setTimeout(() => {
                        if (messageCard.classList.contains('unread')) {
                            markMessageAsRead(messageId);
                        }
                    }, 2000);
                    
                    observer.unobserve(messageCard);
                }
            });
        }, { threshold: 0.5 });
        
        unreadMessages.forEach(message => {
            observer.observe(message);
        });
    }
});
</script>
