/**
 * 资产探测系统前端脚本
 */

// API请求封装
const api = {
    async request(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
            },
        };

        const response = await fetch(url, { ...defaultOptions, ...options });
        const data = await response.json();

        if (data.code !== 0) {
            throw new Error(data.message || '请求失败');
        }

        return data.data;
    },

    get(url, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const fullUrl = queryString ? `${url}?${queryString}` : url;
        return this.request(fullUrl);
    },

    post(url, data = {}) {
        return this.request(url, {
            method: 'POST',
            body: JSON.stringify(data),
        });
    },

    put(url, data = {}) {
        return this.request(url, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
    },

    delete(url) {
        return this.request(url, { method: 'DELETE' });
    },
};

// Toast通知
const toast = {
    container: null,

    init() {
        this.container = document.getElementById('toast-container');
    },

    show(message, type = 'info', duration = 3000) {
        if (!this.container) this.init();

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `<span>${message}</span>`;

        this.container.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideIn 0.3s ease reverse';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },

    success(message) { this.show(message, 'success'); },
    error(message) { this.show(message, 'error'); },
    warning(message) { this.show(message, 'warning'); },
    info(message) { this.show(message, 'info'); },
};

// 模态框
const modal = {
    container: null,

    init() {
        this.container = document.getElementById('modal-container');
    },

    open(options) {
        if (!this.container) this.init();

        const { title, content, footer, onClose, size = 'md' } = options;

        const sizeClass = size === 'lg' ? 'style="max-width: 800px"' : size === 'sm' ? 'style="max-width: 400px"' : '';

        const html = `
            <div class="modal-overlay">
                <div class="modal" ${sizeClass}>
                    <div class="modal-header">
                        <h3 class="modal-title">${title}</h3>
                        <button class="modal-close" onclick="modal.close()">&times;</button>
                    </div>
                    <div class="modal-body">${content}</div>
                    ${footer ? `<div class="modal-footer">${footer}</div>` : ''}
                </div>
            </div>
        `;

        this.container.innerHTML = html;
        this._onClose = onClose;

        // 点击遮罩关闭
        const overlay = this.container.querySelector('.modal-overlay');
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) this.close();
        });
    },

    close() {
        if (this._onClose) this._onClose();
        this.container.innerHTML = '';
    },

    confirm(message, onConfirm) {
        this.open({
            title: '确认操作',
            content: `<p>${message}</p>`,
            footer: `
                <button class="btn btn-outline" onclick="modal.close()">取消</button>
                <button class="btn btn-danger" onclick="modal.handleConfirm()">确认</button>
            `,
        });
        this._confirmCallback = onConfirm;
    },

    handleConfirm() {
        if (this._confirmCallback) this._confirmCallback();
        this.close();
    },
};

// 分页组件
function renderPagination(pagination, onPageChange) {
    const { current_page, total_pages, total, per_page } = pagination;
    const start = (current_page - 1) * per_page + 1;
    const end = Math.min(current_page * per_page, total);

    return `
        <div class="pagination">
            <div class="pagination-info">
                显示 ${start}-${end} 共 ${total} 条
            </div>
            <div class="pagination-buttons">
                <button class="btn btn-outline btn-sm"
                    ${current_page <= 1 ? 'disabled' : ''}
                    onclick="${onPageChange}(${current_page - 1})">
                    上一页
                </button>
                <span style="padding: 0 12px;">第 ${current_page} / ${total_pages} 页</span>
                <button class="btn btn-outline btn-sm"
                    ${current_page >= total_pages ? 'disabled' : ''}
                    onclick="${onPageChange}(${current_page + 1})">
                    下一页
                </button>
            </div>
        </div>
    `;
}

// 表格渲染助手
function renderTable(columns, data, options = {}) {
    const { emptyText = '暂无数据', selectable = false, onSelect } = options;

    if (!data || data.length === 0) {
        return `
            <div class="empty-state">
                <div class="empty-state-icon">&#128193;</div>
                <div class="empty-state-title">${emptyText}</div>
            </div>
        `;
    }

    const headerHtml = columns.map(col => `<th>${col.title}</th>`).join('');

    const bodyHtml = data.map((row, index) => {
        const cellsHtml = columns.map(col => {
            const value = col.render ? col.render(row[col.key], row, index) : row[col.key];
            return `<td>${value ?? '-'}</td>`;
        }).join('');

        return `<tr data-id="${row.id}">${cellsHtml}</tr>`;
    }).join('');

    return `
        <div class="table-container">
            <table class="table">
                <thead><tr>${headerHtml}</tr></thead>
                <tbody>${bodyHtml}</tbody>
            </table>
        </div>
    `;
}

// 状态徽章
function renderStatusBadge(status, statusMap) {
    const config = statusMap[status] || { label: '未知', class: 'badge-gray' };
    return `<span class="badge ${config.class}">${config.label}</span>`;
}

// 加载状态
function renderLoading() {
    return `
        <div class="loading">
            <div class="spinner"></div>
        </div>
    `;
}

// 格式化日期
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleString('zh-CN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

// 格式化数字
function formatNumber(num) {
    if (num === null || num === undefined) return '-';
    return num.toLocaleString();
}

// 复制到剪贴板
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        toast.success('已复制到剪贴板');
    } catch (err) {
        toast.error('复制失败');
    }
}

// 下拉菜单
function toggleDropdown(element) {
    const menu = element.nextElementSibling;
    const isOpen = menu.classList.contains('show');

    // 关闭所有下拉菜单
    document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));

    if (!isOpen) {
        menu.classList.add('show');
    }
}

// 点击外部关闭下拉菜单
document.addEventListener('click', (e) => {
    if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
    }
});

// 表单序列化
function serializeForm(form) {
    const formData = new FormData(form);
    const data = {};
    for (const [key, value] of formData.entries()) {
        if (data[key]) {
            if (!Array.isArray(data[key])) {
                data[key] = [data[key]];
            }
            data[key].push(value);
        } else {
            data[key] = value;
        }
    }
    return data;
}

// URL参数处理
function getUrlParams() {
    return Object.fromEntries(new URLSearchParams(window.location.search));
}

function updateUrlParams(params) {
    const url = new URL(window.location);
    Object.entries(params).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, value);
        }
    });
    window.history.pushState({}, '', url);
}

// 防抖函数
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// 文件上传处理
async function uploadFile(file, url, onProgress) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        const formData = new FormData();
        formData.append('file', file);

        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable && onProgress) {
                onProgress(Math.round((e.loaded / e.total) * 100));
            }
        });

        xhr.addEventListener('load', () => {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                if (response.code === 0) {
                    resolve(response.data);
                } else {
                    reject(new Error(response.message));
                }
            } else {
                reject(new Error('上传失败'));
            }
        });

        xhr.addEventListener('error', () => reject(new Error('网络错误')));

        xhr.open('POST', url);
        xhr.send(formData);
    });
}

// 初始化
document.addEventListener('DOMContentLoaded', () => {
    toast.init();
    modal.init();
});
