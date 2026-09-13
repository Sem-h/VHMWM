/**
 * WHMVM - Frontend Main JavaScript
 * Site ön yüz için ana JS dosyası
 */

document.addEventListener('DOMContentLoaded', function() {
    initTheme();
    initMobileNav();
    initBackToTop();
    initDropdowns();
    initScrollAnimations();
    initStickyHeader();
});

/**
 * Theme System
 */
function initTheme() {
    // Check for system preference if theme is set to auto
    const savedTheme = getCookie('whmvm_theme');
    
    if (savedTheme === 'auto' || !savedTheme) {
        // Check system preference
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const systemTheme = prefersDark ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', systemTheme);
    }
    
    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
        const savedTheme = getCookie('whmvm_theme');
        if (savedTheme === 'auto' || !savedTheme) {
            document.documentElement.setAttribute('data-theme', e.matches ? 'dark' : 'light');
        }
    });
}

/**
 * Toggle Theme
 */
function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme') || 'dark';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    // Apply new theme
    html.setAttribute('data-theme', newTheme);
    
    // Save to cookie (30 days)
    setCookie('whmvm_theme', newTheme, 30);
    
    // Show notification
    const themeNames = {
        'dark': 'Koyu Tema',
        'light': 'Açık Tema'
    };
    showToast(`${themeNames[newTheme]} aktif edildi`, 'success', 2000);
}

/**
 * Get Current Theme
 */
function getCurrentTheme() {
    return document.documentElement.getAttribute('data-theme') || 'dark';
}

/**
 * Set Theme
 */
function setTheme(theme) {
    if (theme === 'auto') {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
    } else {
        document.documentElement.setAttribute('data-theme', theme);
    }
    setCookie('whmvm_theme', theme, 30);
}

/**
 * Cookie Helpers
 */
function setCookie(name, value, days) {
    const expires = new Date();
    expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
    document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/;SameSite=Lax`;
}

function getCookie(name) {
    const nameEQ = name + "=";
    const ca = document.cookie.split(';');
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
}

/**
 * Mobile Navigation
 */
function initMobileNav() {
    // Toggle function for mobile nav
    window.toggleMobileNav = function() {
        const nav = document.getElementById('mainNav');
        const overlay = document.getElementById('navOverlay');
        
        if (nav) {
            nav.classList.toggle('open');
        }
        if (overlay) {
            overlay.classList.toggle('show');
        }
        
        // Prevent body scroll when nav is open
        document.body.style.overflow = nav && nav.classList.contains('open') ? 'hidden' : '';
    };
    
    // Handle dropdown clicks on mobile
    const dropdownItems = document.querySelectorAll('.has-dropdown > a');
    dropdownItems.forEach(function(item) {
        item.addEventListener('click', function(e) {
            if (window.innerWidth <= 992) {
                e.preventDefault();
                this.parentElement.classList.toggle('open');
            }
        });
    });
    
    // Close nav on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const nav = document.getElementById('mainNav');
            if (nav && nav.classList.contains('open')) {
                toggleMobileNav();
            }
        }
    });
}

/**
 * Back to Top Button
 */
function initBackToTop() {
    const backToTop = document.getElementById('backToTop');
    
    if (!backToTop) return;
    
    // Show/hide button based on scroll position
    window.addEventListener('scroll', function() {
        if (window.scrollY > 500) {
            backToTop.classList.add('show');
        } else {
            backToTop.classList.remove('show');
        }
    });
    
    // Scroll to top on click
    backToTop.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

/**
 * Desktop Dropdowns (Mega Menu)
 */
function initDropdowns() {
    const dropdowns = document.querySelectorAll('.has-dropdown');
    
    dropdowns.forEach(function(dropdown) {
        let timeout;
        
        dropdown.addEventListener('mouseenter', function() {
            clearTimeout(timeout);
            // Close other dropdowns
            dropdowns.forEach(function(d) {
                if (d !== dropdown) {
                    d.classList.remove('hover');
                }
            });
            this.classList.add('hover');
        });
        
        dropdown.addEventListener('mouseleave', function() {
            const self = this;
            timeout = setTimeout(function() {
                self.classList.remove('hover');
            }, 100);
        });
    });
}

/**
 * Scroll Animations
 */
function initScrollAnimations() {
    const animatedElements = document.querySelectorAll('[data-animate]');
    
    if (animatedElements.length === 0) return;
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                const delay = entry.target.dataset.delay || 0;
                setTimeout(function() {
                    entry.target.classList.add('animated');
                }, delay);
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });
    
    animatedElements.forEach(function(el) {
        observer.observe(el);
    });
}

/**
 * Sticky Header
 */
function initStickyHeader() {
    const header = document.querySelector('.main-header');
    
    if (!header) return;
    
    let lastScroll = 0;
    
    window.addEventListener('scroll', function() {
        const currentScroll = window.scrollY;
        
        if (currentScroll > 100) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
        
        // Hide/show on scroll direction
        if (currentScroll > lastScroll && currentScroll > 200) {
            header.classList.add('hidden');
        } else {
            header.classList.remove('hidden');
        }
        
        lastScroll = currentScroll;
    });
}

/**
 * Format Price
 */
function formatPrice(price, currency = '₺') {
    return new Intl.NumberFormat('tr-TR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(price) + ' ' + currency;
}

/**
 * Smooth Scroll to Element
 */
function scrollToElement(selector, offset = 100) {
    const element = document.querySelector(selector);
    if (element) {
        const top = element.getBoundingClientRect().top + window.scrollY - offset;
        window.scrollTo({
            top: top,
            behavior: 'smooth'
        });
    }
}

/**
 * Show Loading Overlay
 */
function showLoading(message = 'Yükleniyor...') {
    const overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.innerHTML = `
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <p>${message}</p>
        </div>
    `;
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(15, 23, 42, 0.95);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    `;
    
    const style = document.createElement('style');
    style.textContent = `
        .loading-content {
            text-align: center;
        }
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(99, 102, 241, 0.2);
            border-top-color: #6366f1;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        .loading-content p {
            color: white;
            font-size: 16px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    `;
    
    document.head.appendChild(style);
    document.body.appendChild(overlay);
}

/**
 * Hide Loading Overlay
 */
function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.remove();
    }
}

/**
 * Toast Notification
 */
function showToast(message, type = 'info', duration = 3000) {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#0ea5e9'
    };
    
    toast.innerHTML = `
        <i class="fas ${icons[type]}"></i>
        <span>${message}</span>
    `;
    
    toast.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        padding: 16px 24px;
        background: ${colors[type]};
        color: white;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        z-index: 9999;
        animation: slideInRight 0.3s ease;
    `;
    
    // Add animation keyframes
    if (!document.getElementById('toastStyles')) {
        const style = document.createElement('style');
        style.id = 'toastStyles';
        style.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            @keyframes slideOutRight {
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
    }
    
    document.body.appendChild(toast);
    
    setTimeout(function() {
        toast.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(function() {
            toast.remove();
        }, 300);
    }, duration);
}

/**
 * Copy to Clipboard
 */
function copyToClipboard(text, successMessage = 'Kopyalandı!') {
    navigator.clipboard.writeText(text).then(function() {
        showToast(successMessage, 'success');
    }).catch(function() {
        showToast('Kopyalanamadı', 'error');
    });
}

/**
 * Debounce Function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = function() {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Throttle Function
 */
function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(function() {
                inThrottle = false;
            }, limit);
        }
    };
}
