<?php
/**
 * Default Theme - Header
 */
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Müşteri Paneli' ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/templates/default/assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-inner">
            <a href="dashboard.php" class="navbar-brand">
                🚀 <?= SITE_NAME ?>
            </a>
            
            <div class="navbar-menu">
                <a href="dashboard.php" class="nav-link <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                <a href="services.php" class="nav-link <?= ($currentPage ?? '') === 'services' ? 'active' : '' ?>">Hizmetlerim</a>
                <a href="invoices.php" class="nav-link <?= ($currentPage ?? '') === 'invoices' ? 'active' : '' ?>">Faturalarım</a>
                <a href="domains.php" class="nav-link <?= ($currentPage ?? '') === 'domains' ? 'active' : '' ?>">Domainlerim</a>
                <a href="tickets.php" class="nav-link <?= ($currentPage ?? '') === 'tickets' ? 'active' : '' ?>">Destek</a>
            </div>
            
            <div class="navbar-user">
                <div class="user-balance">
                    💰 <?= number_format((float)($currentClient['credit_balance'] ?? 0), 2) ?> ₺
                </div>
                
                <div class="user-dropdown">
                    <button class="user-btn" onclick="toggleDropdown()">
                        <div class="user-avatar"><?= strtoupper(substr($currentClient['first_name'] ?? 'M', 0, 1)) ?></div>
                        <span><?= htmlspecialchars(($currentClient['first_name'] ?? '') . ' ' . ($currentClient['last_name'] ?? '')) ?></span>
                    </button>
                    <div id="userDropdown" class="dropdown-menu">
                        <a href="profile.php" class="dropdown-item">👤 Profilim</a>
                        <a href="security.php" class="dropdown-item">🔒 Güvenlik</a>
                        <div class="dropdown-divider"></div>
                        <a href="?logout=1" class="dropdown-item logout">🚪 Çıkış Yap</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container">

