<?php
/**
 * Dark Theme - Header with Sidebar
 */
?>
<!DOCTYPE html>
<html lang="tr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Müşteri Paneli' ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/templates/suspended/assets/css/style.css">
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h1>🚀 <?= SITE_NAME ?></h1>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
                <span class="icon">📊</span> Dashboard
            </a>
            <a href="services.php" class="menu-item <?= ($currentPage ?? '') === 'services' ? 'active' : '' ?>">
                <span class="icon">📦</span> Hizmetlerim
            </a>
            <a href="invoices.php" class="menu-item <?= ($currentPage ?? '') === 'invoices' ? 'active' : '' ?>">
                <span class="icon">📄</span> Faturalarım
            </a>
            <a href="domains.php" class="menu-item <?= ($currentPage ?? '') === 'domains' ? 'active' : '' ?>">
                <span class="icon">🌐</span> Domainlerim
            </a>
            <a href="tickets.php" class="menu-item <?= ($currentPage ?? '') === 'tickets' ? 'active' : '' ?>">
                <span class="icon">🎫</span> Destek
            </a>
            <a href="order.php" class="menu-item <?= ($currentPage ?? '') === 'order' ? 'active' : '' ?>">
                <span class="icon">🛒</span> Yeni Sipariş
            </a>
            
            <div class="menu-divider"></div>
            
            <a href="profile.php" class="menu-item <?= ($currentPage ?? '') === 'profile' ? 'active' : '' ?>">
                <span class="icon">👤</span> Profilim
            </a>
            <a href="security.php" class="menu-item <?= ($currentPage ?? '') === 'security' ? 'active' : '' ?>">
                <span class="icon">🔒</span> Güvenlik
            </a>
            <a href="?logout=1" class="menu-item logout">
                <span class="icon">🚪</span> Çıkış Yap
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($currentClient['first_name'] ?? 'M', 0, 1)) ?></div>
                <div class="user-details">
                    <strong><?= htmlspecialchars(($currentClient['first_name'] ?? '') . ' ' . ($currentClient['last_name'] ?? '')) ?></strong>
                    <small>💰 <?= number_format((float)($currentClient['credit_balance'] ?? 0), 2) ?> ₺</small>
                </div>
            </div>
        </div>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <header class="top-header">
            <h2><?= $pageTitle ?? 'Dashboard' ?></h2>
        </header>
        
        <div class="content">

