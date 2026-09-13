<?php
/**
 * Müşteri Panel Header - Top Navbar Layout
 */

require_once dirname(__DIR__, 2) . '/includes/Settings.php';

if (!isset($_SESSION['client_id'])) {
    header('Location: index.php');
    exit;
}

$siteLogo = Settings::getLogo();
$clientId = $_SESSION['client_id'];

// Müşteri bilgilerini çek
$db = Database::getInstance();
$clientStmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
$clientStmt->execute([$clientId]);
$currentClient = $clientStmt->fetch();

// İstatistikler
$activeServicesCount = (int) Database::fetchColumn("SELECT COUNT(*) FROM services WHERE client_id = ? AND status = 'active'", [$clientId]);
$pendingInvoicesCount = (int) Database::fetchColumn("SELECT COUNT(*) FROM invoices WHERE client_id = ? AND status = 'unpaid'", [$clientId]);
$openTicketsCount = (int) Database::fetchColumn("SELECT COUNT(*) FROM tickets WHERE client_id = ? AND status IN ('open', 'customer-reply')", [$clientId]);
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Müşteri Paneli' ?> - <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --secondary: #1e293b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f1f5f9;
            --border: #e2e8f0;
            --card-bg: rgba(255, 255, 255, 0.05);
            --text-primary: #fff;
            --text-secondary: #e2e8f0;
            --text-muted: #94a3b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            color: #fff;
            min-height: 100vh;
        }

        /* Top Navbar */
        .top-navbar {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 30px;
        }

        .navbar-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 70px;
        }

        .navbar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #fff;
        }

        .navbar-logo img {
            max-height: 60px;
            max-width: 220px;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }

        .navbar-logo-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .navbar-logo-text {
            font-size: 20px;
            font-weight: 700;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .navbar-user {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            cursor: pointer;
            position: relative;
        }

        .navbar-user:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-size: 14px;
            font-weight: 600;
        }

        .user-email {
            font-size: 11px;
            color: var(--text-muted);
        }

        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 10px;
            background: #1e293b;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            min-width: 200px;
            display: none;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .user-dropdown.show {
            display: block;
            animation: fadeIn 0.2s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s;
        }

        .dropdown-item:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary-light);
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
        }

        .dropdown-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 5px 0;
        }

        .dropdown-item.logout {
            color: var(--danger);
        }

        .dropdown-item.logout:hover {
            background: rgba(239, 68, 68, 0.1);
        }

        /* Main Navigation */
        .navbar-nav {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 10px 0;
            overflow-x: auto;
        }

        .navbar-nav::-webkit-scrollbar {
            display: none;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-radius: 10px;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
        }

        .nav-link.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
        }

        .nav-link i {
            font-size: 16px;
        }

        .nav-badge {
            background: var(--danger);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
        }

        .nav-badge.success {
            background: var(--success);
        }

        .nav-badge.warning {
            background: var(--warning);
        }

        .nav-link-order {
            background: linear-gradient(135deg, var(--success), #059669);
            color: #fff !important;
        }

        .nav-link-order:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(16, 185, 129, 0.3);
        }

        /* Mobile Menu */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: #fff;
            font-size: 24px;
            cursor: pointer;
            padding: 10px;
        }

        /* Main Content */
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header h1 i {
            color: var(--primary-light);
        }

        .page-header p {
            color: var(--text-muted);
            margin-top: 5px;
        }

        /* Cards */
        .card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            overflow: hidden;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header h3 {
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 24px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary);
        }

        /* Form Elements */
        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: #fff;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        select.form-control {
            cursor: pointer;
        }

        select.form-control option {
            background: #1e293b;
            color: #fff;
        }

        /* Alert */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fcd34d;
        }

        /* Badge */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.2);
            color: #6ee7b7;
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.2);
            color: #fcd34d;
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }

        .badge-info {
            background: rgba(59, 130, 246, 0.2);
            color: #93c5fd;
        }

        .badge-gray {
            background: rgba(148, 163, 184, 0.2);
            color: #cbd5e1;
        }

        /* Tables */
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            text-align: left;
            padding: 14px 16px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .table td {
            padding: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 14px;
        }

        .table tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .navbar-nav {
                display: none;
                position: absolute;
                top: 70px;
                left: 0;
                right: 0;
                background: rgba(15, 23, 42, 0.98);
                flex-direction: column;
                padding: 15px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }

            .navbar-nav.show {
                display: flex;
            }

            .nav-link {
                width: 100%;
                justify-content: flex-start;
            }

            .mobile-toggle {
                display: block;
            }

            .user-info {
                display: none;
            }

            .main-content {
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .navbar-container {
                padding: 0 15px;
            }

            .page-header h1 {
                font-size: 22px;
            }

            .card-body {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

    <!-- Top Navbar -->
    <nav class="top-navbar">
        <div class="navbar-container">
            <div class="navbar-top">
                <a href="dashboard.php" class="navbar-logo">
                    <?php if (!empty($siteLogo)): ?>
                        <img src="../<?= htmlspecialchars($siteLogo) ?>" alt="<?= SITE_NAME ?>">
                    <?php else: ?>
                        <div class="navbar-logo-icon"><i class="fas fa-server"></i></div>
                        <span class="navbar-logo-text"><?= SITE_NAME ?></span>
                    <?php endif; ?>
                </a>

                <button class="mobile-toggle" onclick="toggleMobileMenu()">
                    <i class="fas fa-bars"></i>
                </button>

                <div class="navbar-right">
                    <div class="navbar-user" onclick="toggleUserMenu()">
                        <div class="user-avatar">
                            <?= strtoupper(substr($currentClient['first_name'] ?? 'M', 0, 1)) ?>
                        </div>
                        <div class="user-info">
                            <span
                                class="user-name"><?= htmlspecialchars(($currentClient['first_name'] ?? '') . ' ' . ($currentClient['last_name'] ?? '')) ?></span>
                            <span class="user-email"><?= htmlspecialchars($currentClient['email'] ?? '') ?></span>
                        </div>
                        <i class="fas fa-chevron-down dropdown-arrow"
                            style="font-size: 12px; color: var(--text-muted); transition: transform 0.2s;"></i>

                        <!-- Overlay to close menu on mobile/desktop -->
                        <div class="dropdown-overlay"
                            style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 90;"
                            onclick="event.stopPropagation(); toggleUserMenu()"></div>

                        <div class="user-dropdown">
                            <a href="profile.php" class="dropdown-item">
                                <i class="fas fa-user"></i> Profilim
                            </a>
                            <a href="security.php" class="dropdown-item">
                                <i class="fas fa-shield-alt"></i> Güvenlik
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="logout.php" class="dropdown-item logout">
                                <i class="fas fa-sign-out-alt"></i> Çıkış Yap
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="navbar-nav" id="navbarMenu">
                <a href="dashboard.php" class="nav-link <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="services.php" class="nav-link <?= ($currentPage ?? '') === 'services' ? 'active' : '' ?>">
                    <i class="fas fa-server"></i> Hizmetlerim
                    <?php if ($activeServicesCount > 0): ?>
                        <span class="nav-badge success"><?= $activeServicesCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="domains.php" class="nav-link <?= ($currentPage ?? '') === 'domains' ? 'active' : '' ?>">
                    <i class="fas fa-globe"></i> Domainler
                </a>
                <a href="invoices.php" class="nav-link <?= ($currentPage ?? '') === 'invoices' ? 'active' : '' ?>">
                    <i class="fas fa-file-invoice-dollar"></i> Faturalar
                    <?php if ($pendingInvoicesCount > 0): ?>
                        <span class="nav-badge"><?= $pendingInvoicesCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="tickets.php" class="nav-link <?= ($currentPage ?? '') === 'tickets' ? 'active' : '' ?>">
                    <i class="fas fa-headset"></i> Destek
                    <?php if ($openTicketsCount > 0): ?>
                        <span class="nav-badge warning"><?= $openTicketsCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="emails.php" class="nav-link <?= ($currentPage ?? '') === 'emails' ? 'active' : '' ?>">
                    <i class="fas fa-envelope"></i> E-Postalarım
                </a>
                <a href="affiliate.php" class="nav-link <?= ($currentPage ?? '') === 'affiliate' ? 'active' : '' ?>">
                    <i class="fas fa-handshake"></i> Satış Ortaklığı
                </a>
                <a href="order.php"
                    class="nav-link nav-link-order <?= ($currentPage ?? '') === 'order' ? 'active' : '' ?>">
                    <i class="fas fa-shopping-cart"></i> Yeni Sipariş
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">

        <script>
            function toggleUserMenu() {
                const dropdown = document.querySelector('.user-dropdown');
                const arrow = document.querySelector('.dropdown-arrow');
                dropdown.classList.toggle('show');

                if (dropdown.classList.contains('show')) {
                    arrow.style.transform = 'rotate(180deg)';
                } else {
                    arrow.style.transform = 'rotate(0deg)';
                }
            }

            function toggleMobileMenu() {
                document.getElementById('navbarMenu').classList.toggle('show');
            }

            // Dışarı tıklayınca kapat
            document.addEventListener('click', function (event) {
                const userMenu = document.querySelector('.navbar-user');
                const dropdown = document.querySelector('.user-dropdown');
                const arrow = document.querySelector('.dropdown-arrow');

                if (dropdown && dropdown.classList.contains('show') && !userMenu.contains(event.target)) {
                    dropdown.classList.remove('show');
                    if (arrow) arrow.style.transform = 'rotate(0deg)';
                }
            });
        </script>