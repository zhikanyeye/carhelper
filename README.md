<div align="center">

<img src="assets/logo.svg" width="96" alt="车友帮讯联" />

# 车友帮讯联系统

**让车主与车主之间的沟通更简单！**

基于 PHP 8 + MySQL 的匿名留言与通知平台 · 扫码留窗口 · 邮件即时提醒

[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?logo=bootstrap&logoColor=white)](https://getbootstrap.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

</div>

---

## 📖 项目简介

车友帮讯联系统是一套专为车主设计的匿名留言与通知平台。每位车主生成专属二维码并张贴于车上，其他人扫码即可匿名留言，系统自动通过邮件通知车主。

无需绑定微信、不留电话号码，一条二维码就能解决「堵住别人车却联系不上人」的尴尬场景。支持后台管理、数据清理、系统诊断等全套功能，适合虚拟主机、VPS、云服务器等多种环境。

---

## ✨ 功能特性

### 🚗 核心功能
| 功能 | 说明 |
|------|------|
| 📱 扫码匿名留言 | 专属二维码，扫码即留言，留言者无需注册 |
| 📧 邮件通知 | 留言自动邮件提醒，原生 SMTP 实现，支持 QQ/163/企业邮箱 |
| 📄 挪车通知单 | 可视化富文本编辑器，实时预览，A4 专业打印排版 |
| 🔒 安全用户系统 | 注册、登录、邮箱验证、密码重置全流程 |

### 🎛️ 管理功能
- 👑 **管理员后台** — 用户 / 留言 / 系统状态一站式管理
- 🗑️ **数据清理** — 手动 + 定时任务自动清理，防止数据库膨胀
- 🩺 **系统诊断** — 一键检测环境、权限、数据库、邮件
- 🛡️ **安全防护** — CSRF Token、XSS 过滤、PDO 预处理防注入、登录频率限制

### 🎨 体验亮点
- 📱 响应式设计，桌面端 / 移动端完美适配
- 🎯 美观 URL，无文件后缀统一路由
- 🖨️ 一键打印 / 保存 PDF

---

## 🚀 快速开始

### 环境要求

| 项目 | 要求 |
|------|------|
| PHP | 8.0+（兼容 7.4+，推荐 8.x） |
| 数据库 | MySQL 5.7+ / MariaDB 10.2+ |
| Web 服务器 | Apache / Nginx（支持 `.htaccess` 重写） |
| PHP 扩展 | PDO、PDO_MySQL、mbstring、openssl |
| 目录权限 | `config/`、`qrcodes/`、`uploads/`、`logs/` 可写 |

### 安装步骤

```bash
# 1. 克隆或下载项目到 Web 服务器目录
git clone https://github.com/zhikanyeye/carhelper.git

# 2. 确保以下目录可写（Linux）
chmod 755 config/ qrcodes/ uploads/ logs/
```

3. 浏览器访问 `http://your-domain.com/install.php`，跟随安装向导：
   - 填写数据库连接信息（自动生成 `config/database.php`）
   - 配置 SMTP 邮件（可选，跳过后可在 `config/email.php` 中补充）
   - 创建管理员账号

4. **安装完成后务必删除或封锁 `install.php`**，避免重复安装风险

---

## ⚙️ 配置说明

### 数据库（`config/database.php`，由安装程序自动生成）

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database');
define('DB_USERNAME', 'your_user');
define('DB_PASSWORD', 'your_pass');
```

### 邮件（`config/email.php`）

```php
define('SMTP_HOST', 'smtp.qq.com');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', 'your@email.com');
define('SMTP_PASSWORD', 'your_authorization_code'); // QQ邮箱填授权码，不是登录密码
```

> 💡 QQ 邮箱需在「设置 → 账户」中开启 SMTP 并获取授权码；163 邮箱同理。

### 系统参数（`config/config.php`）

```php
define('DEBUG_MODE', false);          // 生产环境必须为 false
define('CACHE_ENABLED', true);        // 文件缓存
define('MESSAGE_RATE_LIMIT', 60);     // 留言频率限制（秒）
define('MAX_MESSAGE_LENGTH', 1000);   // 最大留言长度
define('LOGIN_COOLDOWN', 300);        // 登录失败冷却（秒）
```

### 数据库性能优化（推荐）

安装完成后，登录管理员后台 →「数据库清理」→「数据库性能优化」→ 点击**立即优化数据库**，可建立关键索引并提升查询速度。

---

## 📋 使用流程

<details>
<summary><b>🚗 车主</b>（点击展开）</summary>

1. 注册账号并完成邮箱验证
2. 在个人面板生成专属二维码
3. 使用可视化编辑器制作挪车通知单（支持格式化、颜色、对齐、列表）
4. 打印通知单张贴于车上
5. 有人扫码留言时自动收到邮件通知，登录系统查看管理

</details>

<details>
<summary><b>💬 留言者</b>（点击展开）</summary>

1. 手机扫描车上二维码
2. 自动进入对应留言页
3. 填写留言内容和联系方式（可选）
4. 提交即完成，全程匿名，无需注册

</details>

<details>
<summary><b>👑 管理员</b>（点击展开）</summary>

1. 访问 `/admin` 登录后台
2. 查看用户数、留言数等实时统计
3. 管理用户状态、监控留言内容
4. 执行数据清理、配置清理周期
5. 访问 `/diagnostic/status.php` 进行系统诊断

</details>

---

## 🗑️ 数据清理

- ✅ 后台手动一键清理已读留言和过期文件
- ✅ 清理周期可配置（30–365 天），不删除账号和未读留言
- ✅ 支持 crontab 定时任务：

```bash
# 每天凌晨 2 点自动清理
0 2 * * * /usr/bin/php /path/to/cleanup_cron.php
```

---

## 📁 项目结构

```
carhelper/
├── index.php                   # 主路由入口
├── install.php                 # 一键安装向导（装完请删除）
├── cleanup_cron.php            # 定时清理脚本
├── database.sql                # 数据库结构
├── pages/                      # 页面（登录/注册/留言/后台/通知单…）
├── api/ & ajax/                # 接口（管理员 API、留言已读/删除）
├── diagnostic/                 # 系统诊断工具
├── includes/                   # 公共库（functions.php 核心函数）
├── config/                     # 配置（database.php 安装后生成，已被 gitignore）
├── assets/ css/ js/            # 静态资源
└── qrcodes/ uploads/ logs/     # 运行时目录（内容不入库）
```

---

## ❓ 常见问题

<details>
<summary><b>邮件收不到？</b></summary>

检查 `config/email.php` 的 SMTP 配置：QQ/163 邮箱需开启 SMTP 服务并使用**授权码**（非登录密码），同时留意垃圾邮件箱。
</details>

<details>
<summary><b>二维码无法生成？</b></summary>

确保 `qrcodes/` 目录有写入权限（755），并检查服务器能否访问二维码 API。
</details>

<details>
<summary><b>安装时出现 HTTP 500？</b></summary>

查看服务器错误日志，通常是目录权限或 PHP 版本问题。
</details>

<details>
<summary><b>首页 / 重置密码链接错误？</b></summary>

v2.1.1 已修复，`SITE_URL` 自动适配任意部署目录；如仍有问题请检查 `.htaccess` 是否生效。
</details>

---

## 📊 更新日志

| 版本 | 日期 | 主要变更 |
|------|------|----------|
| v2.2.0 | 2025-08-14 | 挪车通知单全面升级：可视化编辑器、实时预览、美观 URL、移动端优化 |
| v2.1.1 | 2025-08-11 | SITE_URL 路径修复、邮件模板美化、安全增强 |
| v2.1.0 | 2025-08-07 | 系统诊断增强、数据清理优化、权限控制 |
| v2.0.0 | 2025-08-06 | 管理员后台、邮箱验证、品牌统一 |
| v1.0.0 | 2025-07-31 | 首次发布：用户系统、二维码、留言、邮件通知 |

---

## 🛠️ 技术栈

- **后端**：PHP 8.0+（原生，无框架依赖）
- **数据库**：MySQL 5.7+ / MariaDB 10.2+（PDO 预处理）
- **前端**：Bootstrap 5 + Bootstrap Icons + 原生 JavaScript
- **二维码**：Google Charts API（SVG 备用方案）
- **邮件**：原生 SMTP（支持 QQ / 163 / 企业邮箱）

---

## 🤝 参与贡献

欢迎提交 Issue 和 Pull Request！提交前请确保代码兼容 PHP 7.4+，并同步更新文档。

---

## 📄 开源协议

本项目基于 [MIT License](LICENSE) 开源，可自由使用、修改和分发。

---

<div align="center">

**车友帮讯联系统** —— 让车主沟通更简单！ 🚗💬

如果这个项目对你有帮助，欢迎点个 ⭐ Star

</div>
