/**
 * Default Theme - JavaScript
 * WHMVM Template System
 */

// Dropdown Toggle
function toggleDropdown() {
    document.getElementById('userDropdown').classList.toggle('show');
}

// Close dropdown when clicking outside
window.onclick = function(e) {
    if (!e.target.matches('.user-btn') && !e.target.closest('.user-btn')) {
        var dropdown = document.getElementById('userDropdown');
        if (dropdown && dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
        }
    }
}

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
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 100);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Form Validation
document.querySelectorAll('form[data-validate]').forEach(form => {
    form.addEventListener('submit', function(e) {
        let valid = true;
        form.querySelectorAll('[required]').forEach(input => {
            if (!input.value.trim()) {
                valid = false;
                input.classList.add('error');
            } else {
                input.classList.remove('error');
            }
        });
        
        if (!valid) {
            e.preventDefault();
            showToast('Lütfen tüm zorunlu alanları doldurun.', 'error');
        }
    });
});

console.log('WHMVM Default Theme loaded.');

