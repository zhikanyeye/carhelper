# API 接口说明

## 📁 API 目录

此目录包含系统的所有API接口文件，用于提供数据服务和AJAX调用支持。

## 🔧 接口列表

### admin.php - 管理员API接口
- **功能**: 为管理员面板提供数据接口
- **权限**: 需要管理员登录
- **支持操作**:
  - `?action=users` - 获取用户列表
  - `?action=messages` - 获取最近留言
  - `?action=auto_cleanup` - 执行自动清理任务
  - `?action=verify_user_email` - 验证用户邮箱（POST）

#### 使用示例
```javascript
// 获取用户列表
fetch('/api/admin.php?action=users')
    .then(response => response.json())
    .then(data => console.log(data.html));

// 获取留言列表  
fetch('/api/admin.php?action=messages')
    .then(response => response.json())
    .then(data => console.log(data.html));

// 执行自动清理（写操作，必须 POST 且携带 CSRF token）
const formData = new FormData();
formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
fetch('/api/admin.php?action=auto_cleanup', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => console.log(data.message));
```

## 🛡️ 安全说明

- 所有API接口都包含权限验证（需管理员登录）
- 写操作（清理/删除/拉黑/邮箱验证等）要求 POST 请求并携带有效的 CSRF token（表单字段 `csrf_token` 或请求头 `X-CSRF-Token`）
- 返回JSON格式数据
- 错误时返回适当的HTTP状态码
- **定时清理请使用 `cleanup_cron.php`（CLI 或携带 `?key=CRON_SECRET` 的 HTTP 请求），不要用本接口**——cron 无法携带管理员会话

## 📝 开发规范

- API文件命名使用小写字母
- 返回数据统一使用JSON格式
- 包含适当的错误处理
- 遵循RESTful设计原则

---

**最后更新**: 2025年8月7日  
**版本**: v2.1
