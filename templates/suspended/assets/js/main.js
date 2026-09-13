/**
 * Dark Theme - JavaScript
 * WHMVM Template System
 */

// Confirm Delete
function confirmDelete(message, url) {
    if (confirm(message || 'Bu işlemi gerçekleştirmek istediğinizden emin misiniz?')) {
        window.location.href = url;
    }
}

// Toast Notifications
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = message;
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 15px 25px;
        border-radius: 12px;
        color: white;
        font-weight: 600;
        z-index: 9999;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.3s;
    `;
    
    const colors = {
        success: '#22c55e',
        error: '#ef4444',
        warning: '#eab308',
        info: '#3b82f6'
    };
    
    toast.style.background = colors[type] || colors.info;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.transform = 'translateY(0)';
        toast.style.opacity = '1';
    }, 100);
    
    setTimeout(() => {
        toast.style.transform = 'translateY(100px)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Mobile Sidebar Toggle
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('show');
}

console.log('WHMVM Dark Theme loaded.');

