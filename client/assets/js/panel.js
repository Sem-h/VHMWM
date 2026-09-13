/**
 * WHMVM - Client Panel JavaScript
 * Müşteri Paneli için ana JS dosyası
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initSidebar();
    initFilterTabs();
    initDropdowns();
    initTooltips();
});

/**
 * Sidebar Toggle
 */
function initSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const menuToggle = document.querySelector('.mobile-menu-toggle');
    
    function toggleSidebar() {
        if (sidebar) sidebar.classList.toggle('open');
        if (overlay) overlay.classList.toggle('show');
    }
    
    if (menuToggle) {
        menuToggle.addEventListener('click', toggleSidebar);
    }
    
    if (overlay) {
        overlay.addEventListener('click', toggleSidebar);
    }
    
    // Close sidebar on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
            toggleSidebar();
        }
    });
    
    // Close sidebar when clicking on a link (mobile)
    const navLinks = document.querySelectorAll('.nav-item');
    navLinks.forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 1200 && sidebar && sidebar.classList.contains('open')) {
                toggleSidebar();
            }
        });
    });
}

/**
 * Filter Tabs
 */
function initFilterTabs() {
    const filterTabs = document.querySelectorAll('.filter-tab');
    
    filterTabs.forEach(function(tab) {
        tab.addEventListener('click', function(e) {
            // If it's a link, let it navigate
            if (this.tagName === 'A') return;
            
            e.preventDefault();
            const filter = this.dataset.filter;
            const targetTable = this.closest('.content-area')?.querySelector('table tbody');
            
            if (targetTable) {
                const rows = targetTable.querySelectorAll('tr');
                
                rows.forEach(function(row) {
                    const status = row.dataset.status;
                    row.style.display = !filter || status === filter ? '' : 'none';
                });
            }
            
            // Update active state
            filterTabs.forEach(function(t) {
                t.classList.remove('active');
            });
            this.classList.add('active');
        });
    });
}

/**
 * Dropdowns
 */
function initDropdowns() {
    const dropdownBtns = document.querySelectorAll('[data-dropdown]');
    
    dropdownBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdownId = this.dataset.dropdown;
            const dropdown = document.getElementById(dropdownId);
            
            if (dropdown) {
                // Close all other dropdowns
                document.querySelectorAll('.dropdown-menu.show').forEach(function(d) {
                    if (d !== dropdown) d.classList.remove('show');
                });
                
                dropdown.classList.toggle('show');
            }
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown-menu.show').forEach(function(d) {
            d.classList.remove('show');
        });
    });
}

/**
 * Tooltips
 */
function initTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(function(el) {
        el.addEventListener('mouseenter', function() {
            const text = this.dataset.tooltip;
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = text;
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.cssText = `
                position: fixed;
                top: ${rect.top - tooltip.offsetHeight - 8}px;
                left: ${rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2)}px;
                background: var(--dark);
                color: white;
                padding: 8px 12px;
                border-radius: 8px;
                font-size: 12px;
                z-index: 9999;
                pointer-events: none;
            `;
            
            this._tooltip = tooltip;
        });
        
        el.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                this._tooltip = null;
            }
        });
    });
}

/**
 * Format Currency
 */
function formatCurrency(amount, currency = 'TRY') {
    return new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency: currency
    }).format(amount);
}

/**
 * Format Date
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('tr-TR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

/**
 * Show Notification
 */
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        background: ${type === 'success' ? 'var(--success)' : type === 'error' ? 'var(--danger)' : 'var(--info)'};
        color: white;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 9999;
        animation: slideIn 0.3s ease;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(function() {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(function() {
            notification.remove();
        }, 300);
    }, 3000);
}

/**
 * Confirm Dialog
 */
function confirmDialog(message, callback) {
    const overlay = document.createElement('div');
    overlay.className = 'confirm-overlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    `;
    
    overlay.innerHTML = `
        <div class="confirm-dialog" style="
            background: var(--darker);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 30px;
            max-width: 400px;
            text-align: center;
        ">
            <i class="fas fa-question-circle" style="font-size: 50px; color: var(--warning); margin-bottom: 20px;"></i>
            <h3 style="color: white; margin-bottom: 10px;">Emin misiniz?</h3>
            <p style="color: var(--gray); margin-bottom: 25px;">${message}</p>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button class="btn btn-outline cancel-btn">İptal</button>
                <button class="btn btn-primary confirm-btn">Onayla</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(overlay);
    
    overlay.querySelector('.cancel-btn').addEventListener('click', function() {
        overlay.remove();
        if (callback) callback(false);
    });
    
    overlay.querySelector('.confirm-btn').addEventListener('click', function() {
        overlay.remove();
        if (callback) callback(true);
    });
    
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            overlay.remove();
            if (callback) callback(false);
        }
    });
}

// CSS Animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

