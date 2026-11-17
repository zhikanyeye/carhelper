// 车友帮讯联系统 - 优化版脚本
(function() {
    'use strict';

    // ===== 页面加载完成后初始化 =====
    document.addEventListener('DOMContentLoaded', function() {
        initAlerts();
        initAnimations();
        initFormValidation();
    });

    // ===== 自动隐藏提示消息 =====
    function initAlerts() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            // 只有不包含按钮的成功/信息提示才自动隐藏
            if ((alert.classList.contains('alert-success') || alert.classList.contains('alert-info'))
                && !alert.querySelector('button, .btn, a[class*="btn"]')) {
                setTimeout(function() {
                    fadeOut(alert, 500);
                }, 3000);
            }
        });
    }

    // ===== 添加淡入动画 =====
    function initAnimations() {
        const cards = document.querySelectorAll('.card, .message-card');
        cards.forEach(function(card, index) {
            card.style.animationDelay = (index * 0.1) + 's';
            card.classList.add('fade-in-up');
        });
    }

    // ===== 表单验证初始化 =====
    function initFormValidation() {
        const forms = document.querySelectorAll('form[data-validate]');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                if (!validateForm(form)) {
                    e.preventDefault();
                }
            });
        });
    }

    // ===== 复制到剪贴板 =====
    window.copyToClipboard = function(text) {
        const copyBtn = event.target.closest('button');

        if (copyBtn) {
            disableButton(copyBtn, '复制中');
        }

        if (navigator.clipboard) {
            navigator.clipboard.writeText(text)
                .then(function() {
                    showNotification('分享链接已复制到剪贴板！📋', 'success');
                    if (copyBtn) {
                        showButtonSuccess(copyBtn);
                    }
                })
                .catch(function() {
                    fallbackCopyTextToClipboard(text, copyBtn);
                });
        } else {
            fallbackCopyTextToClipboard(text, copyBtn);
        }
    };

    // ===== 备用复制方法 =====
    function fallbackCopyTextToClipboard(text, copyBtn) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.cssText = 'position:fixed;top:0;left:0;opacity:0;';

        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            const successful = document.execCommand('copy');
            if (successful) {
                showNotification('分享链接已复制到剪贴板！📋', 'success');
                if (copyBtn) {
                    showButtonSuccess(copyBtn);
                }
            } else {
                showNotification('复制失败，请手动选择并复制链接', 'error');
            }
        } catch (err) {
            showNotification('复制功能不支持，请手动选择并复制链接', 'error');
        }

        document.body.removeChild(textArea);
    }

    // ===== 按钮状态管理 =====
    function disableButton(btn, text) {
        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.dataset.originalText = originalText;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> ' + text;
        btn.classList.add('btn-secondary');
        btn.classList.remove('btn-outline-primary');
    }

    function showButtonSuccess(btn) {
        btn.classList.remove('btn-secondary');
        btn.classList.add('btn-success');
        btn.innerHTML = '<i class="bi bi-check-circle"></i> 已复制';

        setTimeout(function() {
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-primary');
            btn.innerHTML = btn.dataset.originalText || '<i class="bi bi-copy"></i> 复制';
            btn.disabled = false;
        }, 800);
    }

    // ===== 显示通知 =====
    window.showNotification = function(message, type) {
        type = type || 'info';

        // 移除已存在的通知
        const existingAlerts = document.querySelectorAll('.notification-alert');
        existingAlerts.forEach(function(alert) {
            alert.remove();
        });

        const alertClass = type === 'error' ? 'alert-danger' : 'alert-' + type;
        const iconClass = type === 'success' ? 'bi-check-circle' :
                         type === 'error' ? 'bi-exclamation-triangle' : 'bi-info-circle';

        const alertHtml =
            '<div class="alert ' + alertClass + ' alert-dismissible fade show notification-alert" role="alert" style="margin-bottom:1rem;animation:slideInDown 0.3s ease-out;">' +
                '<i class="bi ' + iconClass + ' me-2"></i>' + message +
                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
            '</div>';

        const container = document.querySelector('.container');
        if (container) {
            container.insertAdjacentHTML('afterbegin', alertHtml);

            const alertElement = container.querySelector('.notification-alert');
            if (alertElement) {
                const autoCloseTime = (type === 'error') ? 5000 : 3000;

                setTimeout(function() {
                    fadeOut(alertElement, 500);
                }, autoCloseTime);
            }
        }
    };

    // ===== 表单验证 =====
    window.validateForm = function(formId) {
        const form = typeof formId === 'string' ? document.getElementById(formId) : formId;
        if (!form) return false;

        const inputs = form.querySelectorAll('input[required], textarea[required]');
        let isValid = true;

        inputs.forEach(function(input) {
            if (!input.value.trim()) {
                input.classList.add('is-invalid');
                isValid = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });

        return isValid;
    };

    // ===== 邮箱验证 =====
    window.validateEmail = function(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    };

    // ===== 密码强度检查 =====
    window.checkPasswordStrength = function(password) {
        const minLength = 6;
        const hasUpperCase = /[A-Z]/.test(password);
        const hasLowerCase = /[a-z]/.test(password);
        const hasNumbers = /\d/.test(password);
        const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);

        let strength = 0;
        if (password.length >= minLength) strength++;
        if (hasUpperCase) strength++;
        if (hasLowerCase) strength++;
        if (hasNumbers) strength++;
        if (hasSpecial) strength++;

        return {
            score: strength,
            isValid: password.length >= minLength
        };
    };

    // ===== 工具函数：淡出动画 =====
    function fadeOut(element, duration) {
        if (!element) return;

        element.style.transition = 'opacity ' + duration + 'ms ease-out, transform ' + duration + 'ms ease-out';
        element.style.opacity = '0';
        element.style.transform = 'translateY(-20px)';

        setTimeout(function() {
            if (element && element.parentNode) {
                element.remove();
            }
        }, duration);
    }

    // ===== 防抖函数 =====
    window.debounce = function(func, wait) {
        let timeout;
        return function executedFunction() {
            const context = this;
            const args = arguments;
            const later = function() {
                clearTimeout(timeout);
                func.apply(context, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };

    // ===== 节流函数 =====
    window.throttle = function(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(function() {
                    inThrottle = false;
                }, limit);
            }
        };
    };

})();


// AJAX请求封装
function makeAjaxRequest(url, method = 'GET', data = null) {
    return new Promise(function(resolve, reject) {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url, true);

        if (method === 'POST') {
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            // 自动附带 CSRF token（服务端同时支持表单字段与该请求头）
            const tokenMeta = document.querySelector('meta[name="csrf-token"]');
            if (tokenMeta) {
                xhr.setRequestHeader('X-CSRF-Token', tokenMeta.getAttribute('content'));
            }
        }
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        resolve(response);
                    } catch (e) {
                        resolve(xhr.responseText);
                    }
                } else {
                    reject(new Error('请求失败: ' + xhr.status));
                }
            }
        };
        
        xhr.send(data);
    });
}

// 标记留言为已读
function markMessageAsRead(messageId) {
    makeAjaxRequest('ajax/mark_read.php', 'POST', `message_id=${messageId}`)
        .then(function(response) {
            const messageCard = document.querySelector(`[data-message-id="${messageId}"]`);
            if (messageCard) {
                messageCard.classList.remove('unread');
            }
        })
        .catch(function(error) {
            console.error('标记已读失败:', error);
        });
}

// 删除留言
function deleteMessage(messageId) {
    if (confirm('确定要删除这条留言吗？')) {
        makeAjaxRequest('ajax/delete_message.php', 'POST', `message_id=${messageId}`)
            .then(function(response) {
                if (response.success) {
                    const messageCard = document.querySelector(`[data-message-id="${messageId}"]`);
                    if (messageCard) {
                        messageCard.style.transition = 'opacity 0.5s, transform 0.5s';
                        messageCard.style.opacity = '0';
                        messageCard.style.transform = 'translateX(-100%)';
                        setTimeout(function() {
                            messageCard.remove();
                        }, 500);
                    }
                    showNotification('留言已删除', 'success');
                } else {
                    showNotification('删除失败', 'error');
                }
            })
            .catch(function(error) {
                showNotification('删除失败', 'error');
            });
    }
}
