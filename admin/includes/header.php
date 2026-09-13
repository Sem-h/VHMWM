<?php
/**
 * Admin Panel Header - Ortak kullanım için
 */

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// Çıkış işlemi
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Admin' ?> - <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --dark: #0f172a;
            --sidebar: #1e293b;
            --light: #f8fafc;
            --gray: #64748b;
            --border: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 260px;
            background: var(--sidebar);
            color: white;
            overflow-y: auto;
            z-index: 100;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h1 {
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-menu {
            padding: 15px 0;
        }

        .menu-section {
            padding: 10px 20px 5px;
            font-size: 11px;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 1px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: #94a3b8;
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .menu-item:hover,
        .menu-item.active {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border-left-color: var(--primary);
        }

        .menu-item .icon {
            width: 20px;
            text-align: center;
        }

        /* Mega Menu / Dropdown Styles */
        .menu-dropdown {
            position: relative;
        }

        .menu-dropdown-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            color: #94a3b8;
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
            cursor: pointer;
        }

        .menu-dropdown-toggle:hover {
            background: rgba(255, 255, 255, 0.05);
            color: white;
        }

        .menu-dropdown-toggle .left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .menu-dropdown-toggle .arrow {
            font-size: 10px;
            transition: transform 0.3s;
        }

        .menu-dropdown.open .menu-dropdown-toggle {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border-left-color: var(--primary);
        }

        .menu-dropdown.open .arrow {
            transform: rotate(180deg);
        }

        .menu-dropdown-content {
            display: none;
            background: rgba(0, 0, 0, 0.2);
            padding: 5px 0;
        }

        .menu-dropdown.open .menu-dropdown-content {
            display: block;
        }

        .menu-dropdown-content .menu-item {
            padding-left: 52px;
            font-size: 13px;
        }

        .menu-dropdown-content .menu-item .icon {
            font-size: 14px;
        }

        .main {
            margin-left: 260px;
            min-height: 100vh;
        }

        .header {
            background: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .header-left h2 {
            font-size: 20px;
            color: var(--dark);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            background: #f1f5f9;
            border-radius: 10px;
            text-decoration: none;
            color: var(--dark);
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .content {
            padding: 30px;
        }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .card-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            font-size: 16px;
            color: var(--dark);
        }

        .card-body {
            padding: 20px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        .table th {
            font-weight: 600;
            color: var(--gray);
            font-size: 12px;
            text-transform: uppercase;
            background: #f8fafc;
        }

        .table tr:hover {
            background: #f8fafc;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-gray {
            background: #f1f5f9;
            color: #475569;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-outline {
            background: white;
            color: var(--dark);
            border: 2px solid var(--border);
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: var(--gray);
        }

        .empty-state .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .pagination {
            display: flex;
            gap: 5px;
            justify-content: center;
            margin-top: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
            color: var(--dark);
            background: white;
            border: 1px solid var(--border);
        }

        .pagination a:hover,
        .pagination .active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .actions {
            display: flex;
            gap: 5px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray);
        }
    </style>
</head>

<body>
    <aside class="sidebar">
        <?php
        require_once dirname(dirname(__DIR__)) . '/includes/Settings.php';
        $sidebarLogo = Settings::getLogo();
        ?>
        <div class="sidebar-header">
            <a href="dashboard.php" style="text-decoration:none; color:inherit;">
                <?php if ($sidebarLogo): ?>
                    <img src="/<?= htmlspecialchars($sidebarLogo) ?>" alt="<?= SITE_NAME ?>"
                        style="max-height: 40px; filter: brightness(0) invert(1);">
                <?php else: ?>
                    <h1>🚀 <?= SITE_NAME ?></h1>
                <?php endif; ?>
            </a>
        </div>

        <nav class="sidebar-menu">
            <?php
            // Admin klasörünün tam yolu - modüllerden de çalışması için
            $adminPath = '/admin/';
            ?>
            <div class="menu-section">Ana Menü</div>
            <a href="<?= $adminPath ?>dashboard.php"
                class="menu-item <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
                <span class="icon">📊</span> Dashboard
            </a>

            <div class="menu-section">Müşteriler</div>
            <a href="<?= $adminPath ?>clients.php"
                class="menu-item <?= ($currentPage ?? '') === 'clients' ? 'active' : '' ?>">
                <span class="icon">👥</span> Müşteriler
            </a>
            <a href="<?= $adminPath ?>services.php"
                class="menu-item <?= ($currentPage ?? '') === 'services' ? 'active' : '' ?>">
                <span class="icon">📦</span> Hizmetler
            </a>

            <div class="menu-section">Fatura İşlemleri</div>
            <div
                class="menu-dropdown <?= in_array(($currentPage ?? ''), ['orders', 'invoices', 'transactions', 'proposals']) ? 'open' : '' ?>">
                <div class="menu-dropdown-toggle" onclick="this.parentElement.classList.toggle('open')">
                    <span class="left">
                        <span class="icon">💰</span> Fatura İşlemleri
                    </span>
                    <span class="arrow">▼</span>
                </div>
                <div class="menu-dropdown-content">
                    <a href="<?= $adminPath ?>orders.php" class="menu-item <?= ($currentPage ?? '') === 'orders' ? 'active' : '' ?>">
                        <span class="icon">🛒</span> Siparişler
                    </a>
                    <a href="<?= $adminPath ?>invoices.php" class="menu-item <?= ($currentPage ?? '') === 'invoices' ? 'active' : '' ?>">
                        <span class="icon">📄</span> Faturalar
                    </a>
                    <a href="<?= $adminPath ?>transactions.php"
                        class="menu-item <?= ($currentPage ?? '') === 'transactions' ? 'active' : '' ?>">
                        <span class="icon">💳</span> İşlemler
                    </a>
                    <a href="<?= $adminPath ?>proposals.php"
                        class="menu-item <?= ($currentPage ?? '') === 'proposals' ? 'active' : '' ?>">
                        <span class="icon">📜</span> Teklifler
                    </a>
                </div>
            </div>

            <div class="menu-section">Ürün İşlemleri</div>
            <div class="menu-dropdown">
                <div class="menu-dropdown-toggle" onclick="this.parentElement.classList.toggle('open')">
                    <span class="left">
                        <span class="icon">📦</span> Ürün İşlemleri
                    </span>
                    <span class="arrow">▼</span>
                </div>
                <div class="menu-dropdown-content">
                    <a href="<?= $adminPath ?>products.php" class="menu-item <?= ($currentPage ?? '') === 'products' ? 'active' : '' ?>">
                        <span class="icon">📋</span> Ürünler
                    </a>
                    <a href="<?= $adminPath ?>product-groups.php"
                        class="menu-item <?= ($currentPage ?? '') === 'product-groups' ? 'active' : '' ?>">
                        <span class="icon">📁</span> Ürün Grupları
                    </a>
                    <a href="<?= $adminPath ?>product-types.php"
                        class="menu-item <?= ($currentPage ?? '') === 'product-types' ? 'active' : '' ?>">
                        <span class="icon">🏷️</span> Ürün Türleri
                    </a>
                    <a href="<?= $adminPath ?>config-options.php"
                        class="menu-item <?= ($currentPage ?? '') === 'config-options' ? 'active' : '' ?>">
                        <span class="icon">⚙️</span> Yapılandırma Seçenekleri
                    </a>
                    <a href="<?= $adminPath ?>vds-pricing.php"
                        class="menu-item <?= ($currentPage ?? '') === 'vds-pricing' ? 'active' : '' ?>">
                        <span class="icon">💰</span> VDS Fiyatlandırma
                    </a>
                    <a href="<?= $adminPath ?>servers.php" class="menu-item <?= ($currentPage ?? '') === 'servers' ? 'active' : '' ?>">
                        <span class="icon">🖥️</span> Sunucular
                    </a>
                    <a href="<?= $adminPath ?>domains.php" class="menu-item <?= ($currentPage ?? '') === 'domains' ? 'active' : '' ?>">
                        <span class="icon">🌐</span> Alan Adları
                    </a>
                    <a href="<?= $adminPath ?>domain-fiyatlari.php"
                        class="menu-item <?= ($currentPage ?? '') === 'domain-fiyatlari' ? 'active' : '' ?>">
                        <span class="icon">🏷️</span> Alan Adı Fiyatları
                    </a>
                </div>
            </div>

            <div class="menu-section">Destek</div>
            <a href="<?= $adminPath ?>tickets.php" class="menu-item <?= ($currentPage ?? '') === 'tickets' ? 'active' : '' ?>">
                <span class="icon">🎫</span> Destek Talepleri
            </a>
            <a href="<?= $adminPath ?>cancellations.php"
                class="menu-item <?= ($currentPage ?? '') === 'cancellations' ? 'active' : '' ?>">
                <span class="icon">🚫</span> İptal Talepleri
            </a>
            <a href="<?= $adminPath ?>kesif-talepleri.php"
                class="menu-item <?= ($currentPage ?? '') === 'kesif-talepleri' ? 'active' : '' ?>">
                <span class="icon">📍</span> Keşif Talepleri
            </a>
            <a href="<?= $adminPath ?>iletisim-mesajlari.php"
                class="menu-item <?= ($currentPage ?? '') === 'iletisim-mesajlari' ? 'active' : '' ?>">
                <span class="icon">✉️</span> İletişim Mesajları
            </a>
            <a href="<?= $adminPath ?>marka-talepleri.php"
                class="menu-item <?= ($currentPage ?? '') === 'marka-talepleri' ? 'active' : '' ?>">
                <span class="icon">®️</span> Marka Tescil Talepleri
            </a>
            <a href="<?= $adminPath ?>sla.php"
                class="menu-item <?= ($currentPage ?? '') === 'sla' ? 'active' : '' ?>">
                <span class="icon">📄</span> SLA Yönetimi
            </a>
            <a href="<?= $adminPath ?>bilgi-bankasi.php"
                class="menu-item <?= ($currentPage ?? '') === 'bilgi-bankasi' ? 'active' : '' ?>">
                <span class="icon">📚</span> Bilgi Bankası
            </a>
            <a href="<?= $adminPath ?>hizmet-bolgeleri.php"
                class="menu-item <?= ($currentPage ?? '') === 'hizmet-bolgeleri' ? 'active' : '' ?>">
                <span class="icon">🗺️</span> Hizmet Bölgeleri
            </a>

            <div class="menu-section">Satış Ortaklığı</div>
            <a href="<?= $adminPath ?>affiliates.php" class="menu-item <?= ($currentPage ?? '') === 'affiliates' ? 'active' : '' ?>">
                <span class="icon">🤝</span> Ortaklar
            </a>
            <a href="<?= $adminPath ?>affiliate-withdrawals.php"
                class="menu-item <?= ($currentPage ?? '') === 'affiliate-withdrawals' ? 'active' : '' ?>">
                <span class="icon">💸</span> Çekim Talepleri
            </a>

            <div class="menu-section">İçerik</div>
            <a href="<?= $adminPath ?>references.php" class="menu-item <?= ($currentPage ?? '') === 'references' ? 'active' : '' ?>">
                <span class="icon">🏢</span> Referanslar
            </a>

            <div class="menu-section">Ayarlar</div>
            <div
                class="menu-dropdown <?= in_array(($currentPage ?? ''), ['settings', 'integrations', 'menus', 'admins', 'email-templates', 'modules', 'seo-manager', 'updates', 'builder']) ? 'open' : '' ?>">
                <div class="menu-dropdown-toggle" onclick="this.parentElement.classList.toggle('open')">
                    <span class="left">
                        <span class="icon">⚙️</span> Genel Ayarlar
                    </span>
                    <span class="arrow">▼</span>
                </div>
                <div class="menu-dropdown-content">
                    <a href="<?= $adminPath ?>settings.php" class="menu-item <?= ($currentPage ?? '') === 'settings' ? 'active' : '' ?>">
                        <span class="icon">🔧</span> Site Ayarları
                    </a>
                    <a href="<?= $adminPath ?>modules.php" class="menu-item <?= ($currentPage ?? '') === 'modules' ? 'active' : '' ?>">
                        <span class="icon">🧩</span> Modüller
                    </a>
                    <a href="<?= $adminPath ?>updates.php" class="menu-item <?= ($currentPage ?? '') === 'updates' ? 'active' : '' ?>">
                        <span class="icon">🔄</span> Sistem Güncelleme
                    </a>
                    <a href="<?= $adminPath ?>email-templates.php"
                        class="menu-item <?= ($currentPage ?? '') === 'email-templates' ? 'active' : '' ?>">
                        <span class="icon">📧</span> E-posta Şablonları
                    </a>
                    <a href="<?= $adminPath ?>integrations.php"
                        class="menu-item <?= ($currentPage ?? '') === 'integrations' ? 'active' : '' ?>">
                        <span class="icon">🔌</span> Entegrasyonlar
                    </a>
                    <a href="<?= $adminPath ?>menus.php" class="menu-item <?= ($currentPage ?? '') === 'menus' ? 'active' : '' ?>">
                        <span class="icon">📋</span> Menü Yönetimi
                    </a>
                    <a href="<?= $adminPath ?>languages.php"
                        class="menu-item <?= ($currentPage ?? '') === 'languages' ? 'active' : '' ?>">
                        <span class="icon">🌐</span> Dil Yönetimi
                    </a>
                    <a href="<?= $adminPath ?>admins.php" class="menu-item <?= ($currentPage ?? '') === 'admins' ? 'active' : '' ?>">
                        <span class="icon">👤</span> Yöneticiler
                    </a>
                    <a href="<?= $adminPath ?>sistem-kayitlari.php"
                        class="menu-item <?= ($currentPage ?? '') === 'sistem-kayitlari' ? 'active' : '' ?>">
                        <span class="icon">📜</span> Sistem Kayıtları
                    </a>
                    <a href="<?= $adminPath ?>builder.php" class="menu-item <?= ($currentPage ?? '') === 'builder' ? 'active' : '' ?>">
                        <span class="icon">📦</span> Paket Oluşturucu
                    </a>
                </div>
            </div>
        </nav>
    </aside>

    <main class="main">
        <header class="header">
            <div class="header-left">
                <h2><?= $pageTitle ?? 'Admin Panel' ?></h2>
            </div>
            <div class="header-right">
                <a href="?logout=1" class="user-menu">
                    <div class="user-avatar"><?= strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)) ?></div>
                    <span><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span>
                </a>
            </div>
        </header>

        <div class="content">