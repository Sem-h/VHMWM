<?php
/**
 * WHMVM - Müşteri Panel Menü Sistemi
 * Tüm client sayfalarında kullanılacak merkezi menü
 */

// Müşteri verilerini çek (eğer henüz çekilmediyse)
if (!isset($menuClient)) {
    try {
        $menuClient = Database::fetch("SELECT * FROM clients WHERE id = ?", [$_SESSION['client_id'] ?? 0]);
    } catch (Exception $e) {
        $menuClient = null;
    }
}

// Menü istatistikleri
if (!isset($menuStats)) {
    try {
        $menuStats = [
            'services' => (int)Database::fetchColumn("SELECT COUNT(*) FROM services WHERE client_id = ? AND status = 'active'", [$_SESSION['client_id'] ?? 0]),
            'invoices' => (int)Database::fetchColumn("SELECT COUNT(*) FROM invoices WHERE client_id = ? AND status = 'unpaid'", [$_SESSION['client_id'] ?? 0]),
            'tickets' => (int)Database::fetchColumn("SELECT COUNT(*) FROM tickets WHERE client_id = ? AND status IN ('open', 'customer-reply')", [$_SESSION['client_id'] ?? 0]),
            'domains' => (int)Database::fetchColumn("SELECT COUNT(*) FROM domains WHERE client_id = ?", [$_SESSION['client_id'] ?? 0]),
        ];
    } catch (Exception $e) {
        $menuStats = ['services' => 0, 'invoices' => 0, 'tickets' => 0, 'domains' => 0];
    }
}

// Menü yapılandırması
$menuItems = [
    'main' => [
        'title' => 'Ana Menü',
        'items' => [
            [
                'id' => 'dashboard',
                'icon' => 'fas fa-home',
                'label' => 'Dashboard',
                'url' => 'dashboard.php',
                'badge' => null
            ],
        ]
    ],
    'services' => [
        'title' => 'Hizmetler',
        'items' => [
            [
                'id' => 'services',
                'icon' => 'fas fa-server',
                'label' => 'Hizmetlerim',
                'url' => 'services.php',
                'badge' => $menuStats['services'] > 0 ? $menuStats['services'] : null,
                'badge_type' => 'success'
            ],
            [
                'id' => 'domains',
                'icon' => 'fas fa-globe',
                'label' => 'Domainler',
                'url' => 'domains.php',
                'badge' => $menuStats['domains'] > 0 ? $menuStats['domains'] : null,
                'badge_type' => 'info'
            ],
        ]
    ],
    'finance' => [
        'title' => 'Finans',
        'items' => [
            [
                'id' => 'invoices',
                'icon' => 'fas fa-file-invoice-dollar',
                'label' => 'Faturalar',
                'url' => 'invoices.php',
                'badge' => $menuStats['invoices'] > 0 ? $menuStats['invoices'] : null,
                'badge_type' => 'danger'
            ],
        ]
    ],
    'support' => [
        'title' => 'Destek',
        'items' => [
            [
                'id' => 'tickets',
                'icon' => 'fas fa-headset',
                'label' => 'Destek Talepleri',
                'url' => 'tickets.php',
                'badge' => $menuStats['tickets'] > 0 ? $menuStats['tickets'] : null,
                'badge_type' => 'warning'
            ],
            [
                'id' => 'announcements',
                'icon' => 'fas fa-bullhorn',
                'label' => 'Duyurular',
                'url' => 'announcements.php',
                'badge' => null
            ],
        ]
    ],
    'account' => [
        'title' => 'Hesap',
        'items' => [
            [
                'id' => 'profile',
                'icon' => 'fas fa-user-circle',
                'label' => 'Profil',
                'url' => 'profile.php',
                'badge' => null
            ],
            [
                'id' => 'security',
                'icon' => 'fas fa-shield-alt',
                'label' => 'Güvenlik',
                'url' => 'security.php',
                'badge' => null
            ],
            [
                'id' => 'emails',
                'icon' => 'fas fa-envelope',
                'label' => 'E-posta Geçmişi',
                'url' => 'emails.php',
                'badge' => null
            ],
        ]
    ],
    'order' => [
        'title' => 'Sipariş',
        'items' => [
            [
                'id' => 'order',
                'icon' => 'fas fa-shopping-cart',
                'label' => 'Yeni Sipariş',
                'url' => 'order.php',
                'badge' => null,
                'highlight' => true
            ],
        ]
    ],
];

/**
 * Sidebar HTML'i render et
 */
function renderSidebar(array $menuItems, array $menuClient, string $currentPage): string {
    $html = '';
    
    // Logo'yu veritabanından çek
    try {
        $siteLogo = Database::fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'site_logo'");
    } catch (Exception $e) {
        $siteLogo = '';
    }
    
    // Sidebar Header
    $html .= '<div class="sidebar-header">';
    $html .= '<a href="../index.php" class="sidebar-logo">';
    
    if (!empty($siteLogo)) {
        $html .= '<img src="../' . htmlspecialchars($siteLogo) . '" alt="' . SITE_NAME . '" class="sidebar-logo-img dark-invert" style="max-height: 50px; max-width: 200px; object-fit: contain;">';
    } else {
        $html .= '<div class="sidebar-logo-icon"><i class="fas fa-server"></i></div>';
        $html .= '<span class="sidebar-logo-text">' . SITE_NAME . '</span>';
    }
    
    $html .= '</a>';
    $html .= '</div>';
    
    // Navigation
    $html .= '<nav class="sidebar-nav">';
    
    foreach ($menuItems as $section) {
        $html .= '<div class="nav-section">';
        $html .= '<div class="nav-section-title">' . htmlspecialchars($section['title']) . '</div>';
        
        foreach ($section['items'] as $item) {
            $isActive = $currentPage === $item['id'];
            $activeClass = $isActive ? ' active' : '';
            $highlightClass = isset($item['highlight']) && $item['highlight'] ? ' highlight' : '';
            
            $html .= '<a href="' . htmlspecialchars($item['url']) . '" class="nav-item' . $activeClass . $highlightClass . '">';
            $html .= '<i class="' . htmlspecialchars($item['icon']) . '"></i>';
            $html .= '<span>' . htmlspecialchars($item['label']) . '</span>';
            
            if (!empty($item['badge'])) {
                $badgeType = $item['badge_type'] ?? 'primary';
                $html .= '<span class="nav-badge ' . $badgeType . '">' . $item['badge'] . '</span>';
            }
            
            $html .= '</a>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '</nav>';
    
    // Sidebar Footer (User Info)
    $html .= '<div class="sidebar-footer">';
    $html .= '<div class="user-info">';
    $html .= '<div class="user-avatar">';
    $html .= strtoupper(substr($menuClient['first_name'] ?? 'U', 0, 1) . substr($menuClient['last_name'] ?? 'U', 0, 1));
    $html .= '</div>';
    $html .= '<div class="user-details">';
    $html .= '<h4>' . htmlspecialchars(($menuClient['first_name'] ?? '') . ' ' . ($menuClient['last_name'] ?? '')) . '</h4>';
    $html .= '<span>' . htmlspecialchars($menuClient['email'] ?? '') . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<a href="logout.php" class="logout-btn">';
    $html .= '<i class="fas fa-sign-out-alt"></i>';
    $html .= '<span>Çıkış Yap</span>';
    $html .= '</a>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Top Header HTML'i render et
 */
function renderTopHeader(string $pageTitle, string $pageIcon, array $breadcrumbs = [], string $extraHtml = ''): string {
    $html = '<header class="top-header">';
    $html .= '<div class="page-title">';
    $html .= '<div>';
    $html .= '<h1><i class="' . htmlspecialchars($pageIcon) . '"></i> ' . htmlspecialchars($pageTitle) . '</h1>';
    
    if (!empty($breadcrumbs)) {
        $html .= '<div class="breadcrumb">';
        $lastKey = array_key_last($breadcrumbs);
        foreach ($breadcrumbs as $key => $crumb) {
            if ($key === $lastKey) {
                $html .= '<span>' . htmlspecialchars($crumb['label']) . '</span>';
            } else {
                $html .= '<a href="' . htmlspecialchars($crumb['url']) . '">' . htmlspecialchars($crumb['label']) . '</a>';
                $html .= '<i class="fas fa-chevron-right"></i>';
            }
        }
        $html .= '</div>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    if (!empty($extraHtml)) {
        $html .= '<div class="header-actions">' . $extraHtml . '</div>';
    }
    
    $html .= '</header>';
    
    return $html;
}

/**
 * Sidebar CSS stillerini döndür
 */
function getSidebarStyles(): string {
    return '
    <style>
        :root {
            --primary: #2474f5;
            --primary-dark: #1b5ed4;
            --primary-light: #6ba3fb;
            --secondary: #0ea5e9;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --dark: #1e293b;
            --darker: #0f172a;
            --gray: #64748b;
            --light: #f1f5f9;
            --white: #ffffff;
            --sidebar-width: 280px;
            --header-height: 70px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            color: var(--dark);
            overflow-x: hidden;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255,255,255,0.1);
            z-index: 100;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
        }
        
        .sidebar-header {
            padding: 20px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            text-decoration: none;
            width: 100%;
        }
        
        .sidebar-logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
        }
        
        .sidebar-logo-text {
            font-size: 22px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--white), var(--gray));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .sidebar-logo-img {
            max-height: 70px;
            max-width: 220px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        
        /* Dark tema için logoyu beyaz yap */
        .sidebar-logo-img.dark-invert {
            filter: brightness(0) invert(1);
            transition: filter 0.3s ease;
        }
        
        /* Light tema için normal logo */
        body.light-theme .sidebar-logo-img.dark-invert {
            filter: none;
        }
        
        .sidebar-nav {
            flex: 1;
            padding: 20px 15px;
            overflow-y: auto;
        }
        
        .sidebar-nav::-webkit-scrollbar {
            width: 5px;
        }
        
        .sidebar-nav::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
        }
        
        .nav-section {
            margin-bottom: 25px;
        }
        
        .nav-section-title {
            font-size: 11px;
            font-weight: 700;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0 15px;
            margin-bottom: 10px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 15px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 5px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .nav-item:hover {
            background: rgba(36, 116, 245, 0.1);
            color: var(--white);
        }
        
        .nav-item.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            box-shadow: 0 4px 15px rgba(36, 116, 245, 0.3);
        }
        
        .nav-item.highlight {
            background: linear-gradient(135deg, var(--success), #059669);
            color: var(--white);
        }
        
        .nav-item.highlight:hover {
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
            transform: translateX(5px);
        }
        
        .nav-item i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }
        
        .nav-badge {
            margin-left: auto;
            color: white;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
            min-width: 22px;
            text-align: center;
        }
        
        .nav-badge.danger { background: var(--danger); }
        .nav-badge.success { background: var(--success); }
        .nav-badge.warning { background: var(--warning); }
        .nav-badge.info { background: var(--info); }
        .nav-badge.primary { background: var(--primary); }
        
        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            margin-bottom: 15px;
        }
        
        .user-avatar {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
        }
        
        .user-details h4 {
            color: var(--white);
            font-size: 14px;
            font-weight: 600;
        }
        
        .user-details span {
            color: var(--gray);
            font-size: 12px;
        }
        
        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--danger);
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: var(--danger);
            color: white;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            background: linear-gradient(180deg, transparent 0%, rgba(241, 245, 249, 0.03) 100%);
        }
        
        /* Top Header */
        .top-header {
            height: var(--header-height);
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        
        .page-title h1 {
            color: var(--white);
            font-size: 22px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-title h1 i {
            color: var(--primary-light);
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--gray);
            font-size: 14px;
            margin-top: 5px;
        }
        
        .breadcrumb a {
            color: var(--gray);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .breadcrumb a:hover {
            color: var(--primary-light);
        }
        
        .breadcrumb i {
            font-size: 10px;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        /* Content Area */
        .content-area {
            padding: 30px;
        }
        
        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 200;
            background: var(--primary);
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 18px;
            box-shadow: 0 4px 15px rgba(36, 116, 245, 0.3);
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .sidebar { 
                transform: translateX(-100%);
            }
            .sidebar.open { 
                transform: translateX(0);
            }
            .main-content { 
                margin-left: 0;
            }
            .mobile-menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .top-header {
                padding-left: 70px;
            }
        }
        
        @media (max-width: 768px) {
            .content-area { 
                padding: 20px; 
            }
            .top-header {
                padding: 0 15px 0 70px;
            }
            .page-title h1 {
                font-size: 18px;
            }
        }
        
        /* Sidebar Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 99;
        }
        
        .sidebar-overlay.show {
            display: block;
        }
    </style>
    ';
}

/**
 * Sidebar JavaScript'i döndür
 */
function getSidebarScript(): string {
    return '
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const sidebar = document.querySelector(".sidebar");
            const overlay = document.querySelector(".sidebar-overlay");
            const menuToggle = document.querySelector(".mobile-menu-toggle");
            
            function toggleSidebar() {
                sidebar.classList.toggle("open");
                overlay.classList.toggle("show");
            }
            
            if (menuToggle) {
                menuToggle.addEventListener("click", toggleSidebar);
            }
            
            if (overlay) {
                overlay.addEventListener("click", toggleSidebar);
            }
            
            // Close sidebar on escape key
            document.addEventListener("keydown", function(e) {
                if (e.key === "Escape" && sidebar.classList.contains("open")) {
                    toggleSidebar();
                }
            });
        });
    </script>
    ';
}

