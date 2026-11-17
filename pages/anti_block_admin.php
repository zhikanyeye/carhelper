<?php
// pages/anti_block_admin.php
// 防封系统管理页面

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 加载配置
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// 权限检查 - 只有管理员可以访问
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

// 处理配置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_config') {
        // 这里可以添加动态更新配置的逻辑
        $success_message = '配置更新成功';
    } elseif ($_POST['action'] === 'clear_logs') {
        // 清空日志
        $log_dir = __DIR__ . '/../logs/';
        $files = glob($log_dir . 'browser_access_*.log');
        $deleted_count = 0;
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted_count++;
            }
        }
        $success_message = "已清空 {$deleted_count} 个日志文件";
    }
}

// 获取最近的访问日志
function get_recent_access_logs($limit = 100) {
    $log_dir = __DIR__ . '/../logs/';
    $log_files = glob($log_dir . 'browser_access_*.log');
    
    // 按日期排序，最新的在前
    usort($log_files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    $logs = [];
    $count = 0;
    
    foreach ($log_files as $file) {
        if ($count >= $limit) break;
        
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines = array_reverse($lines); // 最新的在前
        
        foreach ($lines as $line) {
            if ($count >= $limit) break;
            
            // 解析日志行
            if (preg_match('/\[(.*?)\] IP:(.*?) \| Page:(.*?) \| Browser:(.*?) \| NeedsRedirect:(.*?) \| UA:(.*?)$/', $line, $matches)) {
                $logs[] = [
                    'timestamp' => $matches[1],
                    'ip' => $matches[2],
                    'page' => $matches[3],
                    'browser' => $matches[4],
                    'needs_redirect' => $matches[5] === 'YES',
                    'user_agent' => $matches[6]
                ];
                $count++;
            }
        }
    }
    
    return $logs;
}

// 统计数据
function get_access_stats() {
    $logs = get_recent_access_logs(1000); // 分析最近1000条记录
    
    $stats = [
        'total_visits' => count($logs),
        'blocked_visits' => 0,
        'browser_stats' => [],
        'page_stats' => [],
        'today_visits' => 0,
        'today_blocked' => 0
    ];
    
    $today = date('Y-m-d');
    
    foreach ($logs as $log) {
        if ($log['needs_redirect']) {
            $stats['blocked_visits']++;
        }
        
        // 统计浏览器
        $browser = $log['browser'];
        if (!isset($stats['browser_stats'][$browser])) {
            $stats['browser_stats'][$browser] = ['total' => 0, 'blocked' => 0];
        }
        $stats['browser_stats'][$browser]['total']++;
        if ($log['needs_redirect']) {
            $stats['browser_stats'][$browser]['blocked']++;
        }
        
        // 统计页面
        $page = $log['page'];
        if (!isset($stats['page_stats'][$page])) {
            $stats['page_stats'][$page] = ['total' => 0, 'blocked' => 0];
        }
        $stats['page_stats'][$page]['total']++;
        if ($log['needs_redirect']) {
            $stats['page_stats'][$page]['blocked']++;
        }
        
        // 今日统计
        if (strpos($log['timestamp'], $today) === 0) {
            $stats['today_visits']++;
            if ($log['needs_redirect']) {
                $stats['today_blocked']++;
            }
        }
    }
    
    return $stats;
}

$recent_logs = get_recent_access_logs(50);
$stats = get_access_stats();

?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>防封系统管理 - 车友帮讯联系系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .stats-card {
            border-left: 4px solid #007bff;
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
        .blocked-stat {
            border-left-color: #dc3545;
        }
        .success-stat {
            border-left-color: #28a745;
        }
        .warning-stat {
            border-left-color: #ffc107;
        }
        .log-entry {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            border-left: 3px solid #dee2e6;
            padding-left: 10px;
            margin-bottom: 5px;
        }
        .log-blocked {
            border-left-color: #dc3545;
            background-color: #fff5f5;
        }
        .log-normal {
            border-left-color: #28a745;
            background-color: #f8fff8;
        }
        .browser-tag {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .browser-wechat { background: #1aad19; color: white; }
        .browser-qq { background: #1296db; color: white; }
        .browser-weibo { background: #e6162d; color: white; }
        .browser-other { background: #6c757d; color: white; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center py-3">
                    <h1><i class="bi bi-shield-check"></i> 防封系统管理</h1>
                    <div>
                        <a href="<?php echo SITE_URL; ?>/admin" class="btn btn-outline-secondary">返回管理后台</a>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#configModal">
                            <i class="bi bi-gear"></i> 系统配置
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- 统计卡片 -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted">总访问量</h6>
                                <h3 class="text-primary"><?php echo number_format($stats['total_visits']); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-eye fs-1 text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card blocked-stat">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted">防封拦截</h6>
                                <h3 class="text-danger"><?php echo number_format($stats['blocked_visits']); ?></h3>
                                <small class="text-muted">
                                    <?php echo $stats['total_visits'] > 0 ? round($stats['blocked_visits'] / $stats['total_visits'] * 100, 1) : 0; ?>%
                                </small>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-shield-x fs-1 text-danger"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card warning-stat">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted">今日访问</h6>
                                <h3 class="text-warning"><?php echo number_format($stats['today_visits']); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-calendar-day fs-1 text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card success-stat">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted">今日拦截</h6>
                                <h3 class="text-success"><?php echo number_format($stats['today_blocked']); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-shield-check fs-1 text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- 浏览器统计 -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-browser-chrome"></i> 浏览器统计</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($stats['browser_stats'] as $browser => $data): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <span class="browser-tag browser-<?php echo strtolower($browser); ?>"><?php echo htmlspecialchars($browser); ?></span>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">
                                    访问: <?php echo $data['total']; ?> | 
                                    拦截: <span class="text-danger"><?php echo $data['blocked']; ?></span>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- 页面统计 -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-file-earmark"></i> 页面统计</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($stats['page_stats'] as $page => $data): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <code><?php echo htmlspecialchars($page); ?></code>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">
                                    访问: <?php echo $data['total']; ?> | 
                                    拦截: <span class="text-danger"><?php echo $data['blocked']; ?></span>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 访问日志 -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="bi bi-list-ul"></i> 最近访问日志</h5>
                        <div>
                            <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise"></i> 刷新
                            </button>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="clear_logs">
                                <button type="submit" class="btn btn-sm btn-outline-danger" 
                                        onclick="return confirm('确定要清空所有日志吗？')">
                                    <i class="bi bi-trash"></i> 清空日志
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                        <?php if (empty($recent_logs)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-1"></i>
                            <p>暂无访问日志</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($recent_logs as $log): ?>
                        <div class="log-entry <?php echo $log['needs_redirect'] ? 'log-blocked' : 'log-normal'; ?>">
                            <strong><?php echo htmlspecialchars($log['timestamp']); ?></strong> | 
                            IP: <code><?php echo htmlspecialchars($log['ip']); ?></code> | 
                            页面: <code><?php echo htmlspecialchars($log['page']); ?></code> | 
                            <span class="browser-tag browser-<?php echo strtolower($log['browser']); ?>">
                                <?php echo htmlspecialchars($log['browser']); ?>
                            </span>
                            <?php if ($log['needs_redirect']): ?>
                            <span class="badge bg-danger">已拦截</span>
                            <?php else: ?>
                            <span class="badge bg-success">正常</span>
                            <?php endif; ?>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars($log['user_agent']); ?></small>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 配置模态框 -->
    <div class="modal fade" id="configModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">防封系统配置</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">防封功能状态</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="antiBlockEnabled" 
                                   <?php echo (defined('ANTI_BLOCK_ENABLED') && ANTI_BLOCK_ENABLED) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="antiBlockEnabled">
                                启用防封功能
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">日志记录状态</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="logEnabled" 
                                   <?php echo (defined('LOG_BROWSER_ACCESS') && LOG_BROWSER_ACCESS) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="logEnabled">
                                启用访问日志记录
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">防封检测页面</label>
                        <textarea class="form-control" rows="3" readonly>
<?php echo defined('ANTI_BLOCK_ROUTES') ? implode(', ', ANTI_BLOCK_ROUTES) : '未定义'; ?>
                        </textarea>
                        <small class="form-text text-muted">这些页面将进行防封检测</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                    <button type="button" class="btn btn-primary">保存配置</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 自动刷新页面
        setInterval(() => {
            location.reload();
        }, 30000); // 30秒刷新一次
    </script>
</body>
</html>