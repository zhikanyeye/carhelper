<?php
// pages/home.php
// 首页内容
?>
<div class="hero-section">
    <div class="container text-center">
        <!-- 品牌Logo展示 -->
        <div class="mb-4">
            <img src="<?php echo defined('SITE_URL') ? SITE_URL : ''; ?>/assets/logo.svg" width="80" height="80" alt="车友帮讯联" style="filter: brightness(0) invert(1);">
        </div>
        <h1 class="display-4 fw-bold mb-4">车友帮讯联</h1>
        <p class="lead mb-4">扫码留言，匿名安全，让车友间的沟通更轻松</p>
        <?php if (!is_logged_in()): ?>
        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
            <a href="register" class="btn btn-light btn-lg me-md-2">
                <i class="bi bi-person-plus"></i> 立即注册
            </a>
            <a href="login" class="btn btn-outline-light btn-lg">
                <i class="bi bi-box-arrow-in-right"></i> 用户登录
            </a>
        </div>
        <?php else: ?>
        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
            <a href="dashboard" class="btn btn-light btn-lg me-md-2">
                <i class="bi bi-speedometer2"></i> 我的面板
            </a>
            <a href="view_messages" class="btn btn-outline-light btn-lg">
                <i class="bi bi-envelope"></i> 查看留言
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="container">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <h2 class="text-center mb-5">功能特点</h2>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="bi bi-qr-code-scan text-primary" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="card-title">二维码生成</h5>
                            <p class="card-text">自动生成专属二维码，方便其他人快速找到你的留言页面</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="bi bi-chat-left-text text-success" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="card-title">匿名留言</h5>
                            <p class="card-text">支持匿名留言功能，保护留言者隐私，沟通更自由</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="bi bi-envelope-check text-warning" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="card-title">邮件通知</h5>
                            <p class="card-text">收到新留言时自动发送邮件通知，不错过任何重要信息</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="bi bi-shield-check text-info" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="card-title">安全可靠</h5>
                            <p class="card-text">采用现代化安全技术，保护用户数据安全和隐私</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-5">
        <div class="col-lg-8 mx-auto">
            <h2 class="text-center mb-5">如何使用</h2>
            
            <div class="row g-4">
                <div class="col-md-4 text-center">
                    <div class="mb-3">
                        <span class="badge bg-primary rounded-pill fs-4">1</span>
                    </div>
                    <h5>注册账号</h5>
                    <p class="text-muted">创建你的专属账号，设置邮箱接收通知</p>
                </div>
                
                <div class="col-md-4 text-center">
                    <div class="mb-3">
                        <span class="badge bg-primary rounded-pill fs-4">2</span>
                    </div>
                    <h5>生成二维码</h5>
                    <p class="text-muted">在个人面板生成专属二维码，贴在车上或物品上</p>
                </div>
                
                <div class="col-md-4 text-center">
                    <div class="mb-3">
                        <span class="badge bg-primary rounded-pill fs-4">3</span>
                    </div>
                    <h5>接收留言</h5>
                    <p class="text-muted">他人扫码留言，你将收到邮件通知</p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (is_logged_in()): ?>
    <div class="row mt-5">
        <div class="col-lg-6 mx-auto">
            <!-- 管理员专属提示 -->
            <?php if (is_admin()): ?>
            <div class="alert alert-danger mb-4">
                <div class="d-flex align-items-center">
                    <i class="bi bi-shield-check-fill me-2"></i>
                    <div class="flex-grow-1">
                        <strong>管理员权限</strong><br>
                        <small>您拥有系统管理权限，可以管理用户和系统设置</small>
                    </div>
                    <a href="<?php echo url('管理员后台'); ?>" class="btn btn-sm btn-danger">
                        <i class="bi bi-gear"></i> 管理后台
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 邮箱验证提示 -->
            <?php if (needs_email_verification()): ?>
            <div class="alert alert-warning mb-4">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <div class="flex-grow-1">
                        <strong>邮箱尚未验证</strong><br>
                        <small>为了确保您能正常接收留言通知，请验证您的邮箱地址</small>
                    </div>
                    <a href="<?php echo url('重发验证'); ?>" class="btn btn-sm btn-warning">
                        <i class="bi bi-envelope-check"></i> 立即验证
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">欢迎回来，<?php echo htmlspecialchars($_SESSION['username']); ?>！</h5>
                    <p class="card-text">
                        您目前有 <strong><?php echo get_user_message_count($_SESSION['user_id']); ?></strong> 条留言，
                        其中 <strong><?php echo get_unread_message_count($_SESSION['user_id']); ?></strong> 条未读
                    </p>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <a href="dashboard" class="btn btn-primary">
                            <i class="bi bi-speedometer2"></i> 前往面板
                        </a>
                        <a href="view_messages" class="btn btn-outline-primary">
                            <i class="bi bi-envelope"></i> 查看留言
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
