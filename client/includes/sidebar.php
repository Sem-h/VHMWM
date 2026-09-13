<?php
/**
 * Müşteri Panel - Sidebar Menü
 */

// Sidebar için gerekli veriler (header.php'den geliyor)
$currentPage = $currentPage ?? '';
$currentClient = $currentClient ?? [];
$activeServicesCount = $activeServicesCount ?? 0;
$pendingInvoicesCount = $pendingInvoicesCount ?? 0;
$openTicketsCount = $openTicketsCount ?? 0;

// Logo'yu doğrudan veritabanından çek
try {
    $siteLogo = Database::fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'site_logo'");
} catch (Exception $e) {
    $siteLogo = '';
}

// Base URL hesapla (localhost veya dış IP fark etmez)
$baseUrl = '../';
?>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="sidebar-logo">
            <?php if (!empty($siteLogo)): ?>
                <img src="<?= $baseUrl . htmlspecialchars($siteLogo) ?>" alt="<?= SITE_NAME ?>" class="sidebar-logo-img dark-invert" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="sidebar-logo-fallback" style="display: none;">
                    <div class="sidebar-logo-icon"><i class="fas fa-server"></i></div>
                    <span class="sidebar-logo-text"><?= SITE_NAME ?></span>
                </div>
            <?php else: ?>
                <div class="sidebar-logo-icon"><i class="fas fa-server"></i></div>
                <span class="sidebar-logo-text"><?= SITE_NAME ?></span>
            <?php endif; ?>
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <!-- Ana Menü -->
        <div class="nav-section">
            <div class="nav-section-title">Ana Menü</div>
            <a href="dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-home"></i> 
                <span>Dashboard</span>
            </a>
        </div>
        
        <!-- Hizmetler -->
        <div class="nav-section">
            <div class="nav-section-title">Hizmetler</div>
            <a href="services.php" class="nav-item <?= $currentPage === 'services' ? 'active' : '' ?>">
                <i class="fas fa-server"></i> 
                <span>Hizmetlerim</span>
                <?php if ($activeServicesCount > 0): ?>
                    <span class="nav-badge success"><?= $activeServicesCount ?></span>
                <?php endif; ?>
            </a>
            <a href="domains.php" class="nav-item <?= $currentPage === 'domains' ? 'active' : '' ?>">
                <i class="fas fa-globe"></i> 
                <span>Domainler</span>
            </a>
        </div>
        
        <!-- Finans -->
        <div class="nav-section">
            <div class="nav-section-title">Finans</div>
            <a href="invoices.php" class="nav-item <?= $currentPage === 'invoices' ? 'active' : '' ?>">
                <i class="fas fa-file-invoice-dollar"></i> 
                <span>Faturalar</span>
                <?php if ($pendingInvoicesCount > 0): ?>
                    <span class="nav-badge"><?= $pendingInvoicesCount ?></span>
                <?php endif; ?>
            </a>
        </div>
        
        <!-- Destek -->
        <div class="nav-section">
            <div class="nav-section-title">Destek</div>
            <a href="tickets.php" class="nav-item <?= $currentPage === 'tickets' ? 'active' : '' ?>">
                <i class="fas fa-headset"></i> 
                <span>Destek Talepleri</span>
                <?php if ($openTicketsCount > 0): ?>
                    <span class="nav-badge warning"><?= $openTicketsCount ?></span>
                <?php endif; ?>
            </a>
            <a href="tickets-new.php" class="nav-item <?= $currentPage === 'tickets-new' ? 'active' : '' ?>">
                <i class="fas fa-plus-circle"></i> 
                <span>Yeni Talep</span>
            </a>
        </div>
        
        <!-- Hesap -->
        <div class="nav-section">
            <div class="nav-section-title">Hesap</div>
            <a href="profile.php" class="nav-item <?= $currentPage === 'profile' ? 'active' : '' ?>">
                <i class="fas fa-user"></i> 
                <span>Profilim</span>
            </a>
            <a href="security.php" class="nav-item <?= $currentPage === 'security' ? 'active' : '' ?>">
                <i class="fas fa-shield-alt"></i> 
                <span>Güvenlik</span>
            </a>
        </div>
        
        <!-- Sipariş -->
        <div class="nav-section">
            <div class="nav-section-title">Sipariş</div>
            <a href="order.php" class="nav-item nav-item-highlight <?= $currentPage === 'order' ? 'active' : '' ?>">
                <i class="fas fa-shopping-cart"></i> 
                <span>Yeni Sipariş</span>
            </a>
        </div>
    </nav>
    
    <!-- Sidebar Footer - Kullanıcı Bilgisi -->
    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar">
                <?= strtoupper(substr($currentClient['first_name'] ?? 'M', 0, 1)) ?>
            </div>
            <div class="user-info">
                <h4><?= htmlspecialchars(($currentClient['first_name'] ?? '') . ' ' . ($currentClient['last_name'] ?? '')) ?></h4>
                <span><?= htmlspecialchars($currentClient['email'] ?? '') ?></span>
            </div>
        </div>
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Çıkış Yap
        </a>
    </div>
</aside>

