<?php
/**
 * WHMVM - Frontend Header
 * Site üst kısım ve navigasyon
 */

// Config dosyasını dahil et
require_once __DIR__ . '/config.php';

// Settings sınıfını dahil et
require_once dirname(__DIR__, 2) . '/includes/Settings.php';

// Dil katmanı
require_once dirname(__DIR__, 2) . '/includes/Lang.php';
Lang::init();

// SEO Helper'ı dahil et (modül yüklüyse)
$seoHelperPath = dirname(__DIR__, 2) . '/includes/SeoHelper.php';
if (file_exists($seoHelperPath)) {
    require_once $seoHelperPath;
}

// Aktif sayfa
$currentFile = basename($_SERVER['PHP_SELF']);

// Ayarlardan değerleri al
$siteName = Settings::get('site_name', $siteConfig['name']);
$siteLogo = Settings::getLogo();
$siteFavicon = Settings::getFavicon();
$companyPhone = Settings::get('company_phone', $siteConfig['phone']);
$companyEmail = Settings::get('company_email', $siteConfig['email']);
$primaryColor = Settings::get('site_primary_color', '#6366f1');
$secondaryColor = Settings::get('site_secondary_color', '#0ea5e9');
$defaultTheme = Settings::get('default_theme', 'dark');
$allowThemeSwitch = Settings::get('allow_theme_switch', '1') === '1';

// Kullanıcının tema tercihini çerezden al
$userTheme = $_COOKIE['whmvm_theme'] ?? $defaultTheme;
if ($userTheme === 'auto') {
    // Otomatik modda varsayılan dark kullan, JS ile sistem tercihi kontrol edilecek
    $userTheme = 'dark';
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(Lang::kod()) ?>" dir="<?= htmlspecialchars(Lang::yon()) ?>"
    data-theme="<?= htmlspecialchars($userTheme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <?php
    // SEO Helper kullanılabilirse SEO meta tag'lerini render et
    if (class_exists('SeoHelper')) {
        $currentPath = $_SERVER['REQUEST_URI'] ?? '/';
        $currentPath = strtok($currentPath, '?'); // Query string'i temizle
        echo SeoHelper::renderMetaTags($currentPath);
    } else {
        // SEO Helper yoksa varsayılan meta tag'ler
        ?>
        <meta name="description" content="<?= $pageDescription ?? 'WHMVM - Profesyonel hosting, VDS, cloud sunucu ve domain hizmetleri' ?>">
        <title><?= isset($pageTitle) ? $pageTitle . ' - ' : '' ?><?= $siteName ?></title>
        <?php
    }
    ?>
    
    <!-- Favicon -->
    <?php if (!empty($siteFavicon)): ?>
        <link rel="icon" href="/<?= htmlspecialchars($siteFavicon) ?>" type="image/<?= pathinfo($siteFavicon, PATHINFO_EXTENSION) === 'ico' ? 'x-icon' : 'png' ?>">
        <link rel="shortcut icon" href="/<?= htmlspecialchars($siteFavicon) ?>">
    <?php endif; ?>
    
    <!-- Preconnect -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Dynamic Colors -->
    <style>
        :root {
            --primary: <?= htmlspecialchars($primaryColor) ?>;
            --secondary: <?= htmlspecialchars($secondaryColor) ?>;
        }
    </style>
    
    <!-- Main CSS -->
    <link rel="stylesheet" href="theme/assets/css/main.css">
    
    <?php if (isset($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
            <link rel="stylesheet" href="<?= $css ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="container">
            <div class="top-left">
                <?php if (!empty($companyPhone)): ?>
                    <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $companyPhone)) ?>" dir="ltr">
                        <i class="fas fa-phone"></i>
                        <?= htmlspecialchars($companyPhone) ?>
                    </a>
                <?php endif; ?>
                <?php if (!empty($companyEmail)): ?>
                    <a href="mailto:<?= htmlspecialchars($companyEmail) ?>" dir="ltr">
                        <i class="fas fa-envelope"></i>
                        <?= htmlspecialchars($companyEmail) ?>
                    </a>
                <?php endif; ?>
                <a href="sistem-durumu.php" class="top-status">
                    <i class="fas fa-wave-square"></i>
                    <?= Lang::e('ustbar.sistem-durumu', 'Sistem Durumu') ?>
                    <span class="top-dot" aria-hidden="true"></span>
                </a>
            </div>
            <div class="top-right">
                <a href="referanslar.php">
                    <i class="fas fa-building"></i>
                    <?= Lang::e('ustbar.referanslar', 'Referanslar') ?>
                </a>
                <a href="client/tickets.php">
                    <i class="fas fa-headset"></i>
                    <?= Lang::e('ustbar.destek', 'Destek') ?>
                </a>
                <?php $diller = Lang::diller();
                if (count($diller) > 1):
                    $aktifDil = Lang::aktifDil(); ?>
                    <div class="lang-switch">
                        <button type="button" class="lang-current" aria-haspopup="true" aria-expanded="false"
                            onclick="this.parentElement.classList.toggle('is-open')">
                            <span class="lang-flag"><?= htmlspecialchars($aktifDil['flag'] ?? '') ?></span>
                            <?= htmlspecialchars(strtoupper($aktifDil['code'])) ?>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="lang-menu">
                            <?php foreach ($diller as $d): ?>
                                <a href="<?= htmlspecialchars(Lang::url($d['code'])) ?>"
                                    class="<?= $d['code'] === $aktifDil['code'] ? 'is-on' : '' ?>"
                                    lang="<?= htmlspecialchars($d['code']) ?>">
                                    <span class="lang-flag"><?= htmlspecialchars($d['flag'] ?? '') ?></span>
                                    <?= htmlspecialchars($d['native_name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($allowThemeSwitch): ?>
                    <button class="theme-toggle" onclick="toggleTheme()" title="<?= Lang::e('ortak.tema-degistir', 'Tema Değiştir') ?>">
                        <i class="fas fa-sun icon-sun"></i>
                        <i class="fas fa-moon icon-moon"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Main Header -->
    <header class="main-header">
        <div class="container">
            <!-- Logo -->
            <a href="index.php" class="logo">
                <?php if (!empty($siteLogo)): ?>
                    <img src="/<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($siteName) ?>" class="logo-image">
                <?php else: ?>
                    <span class="logo-mark" aria-hidden="true">
                        <svg viewBox="0 0 120 120" fill="none">
                            <rect width="120" height="120" rx="27" fill="var(--primary)"/>
                            <g stroke-width="12" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M28 26V94" stroke="#fff"/>
                                <path d="M92 26V94" stroke="#fff"/>
                                <path d="M28 80H92" stroke="#fff"/>
                                <path d="M28 26 60 62 92 26" stroke="#fff" stroke-opacity=".62"/>
                            </g>
                        </svg>
                    </span>
                    <span class="logo-text"><em>V</em>HM</span>
                <?php endif; ?>
            </a>
            
            <!-- Navigation -->
            <nav class="main-nav" id="mainNav">
                <div class="mobile-nav-header">
                    <a href="index.php" class="logo">
                        <?php if (!empty($siteLogo)): ?>
                            <img src="/<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($siteName) ?>" class="logo-image">
                        <?php else: ?>
                            <span class="logo-mark" aria-hidden="true">
                                <svg viewBox="0 0 120 120" fill="none">
                                    <rect width="120" height="120" rx="27" fill="var(--primary)"/>
                                    <g stroke-width="12" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M28 26V94" stroke="#fff"/>
                                        <path d="M92 26V94" stroke="#fff"/>
                                        <path d="M28 80H92" stroke="#fff"/>
                                        <path d="M28 26 60 62 92 26" stroke="#fff" stroke-opacity=".62"/>
                                    </g>
                                </svg>
                            </span>
                            <span class="logo-text"><em>V</em>HM</span>
                        <?php endif; ?>
                    </a>
                    <button class="close-nav" onclick="toggleMobileNav()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <ul class="nav-menu">
                    <?php foreach ($mainMenu as $item): ?>
                        <?php if (isset($item['submenu']) && !empty($item['submenu'])): ?>
                            <?php 
                            // Alt menü sayısını hesapla
                            $totalSubItems = 0;
                            foreach ($item['submenu'] as $section) {
                                $totalSubItems += count($section['items']);
                            }
                            $menuClass = $totalSubItems > 6 ? 'mega-menu-wide' : 'mega-menu-compact';
                            ?>
                            <li class="has-dropdown">
                                <a href="<?= htmlspecialchars($item['url']) ?>">
                                    <i class="fas <?= htmlspecialchars($item['icon']) ?>"></i>
                                    <?= htmlspecialchars(Lang::tv('menu', $item['label'])) ?>
                                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                                </a>
                                <div class="mega-menu <?= $menuClass ?>">
                                    <div class="mega-menu-inner">
                                        <?php foreach ($item['submenu'] as $section): ?>
                                            <div class="mega-section">
                                                <?php if (!empty($section['title']) && $section['title'] !== $item['label']): ?>
                                                    <h4><?= htmlspecialchars(Lang::tv('menu', $section['title'])) ?></h4>
                                                <?php endif; ?>
                                                <ul>
                                                    <?php foreach ($section['items'] as $subitem): ?>
                                                        <li>
                                                            <a href="<?= htmlspecialchars($subitem['url']) ?>">
                                                                <div class="menu-icon">
                                                                    <i class="fas <?= htmlspecialchars($subitem['icon'] ?? 'fa-angle-right') ?>"></i>
                                                                </div>
                                                                <div class="menu-info">
                                                                    <strong><?= htmlspecialchars(Lang::tv('menu', $subitem['label'])) ?></strong>
                                                                    <?php if (!empty($subitem['desc'])): ?>
                                                                        <span><?= htmlspecialchars(Lang::tv('menu', $subitem['desc'])) ?></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </li>
                        <?php else: ?>
                            <li class="<?= $currentFile === $item['url'] ? 'active' : '' ?>">
                                <a href="<?= htmlspecialchars($item['url']) ?>">
                                    <i class="fas <?= htmlspecialchars($item['icon']) ?>"></i>
                                    <?= htmlspecialchars(Lang::tv('menu', $item['label'])) ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </nav>
            
            <!-- Header Actions -->
            <div class="header-actions">
                <a href="sepet.php" class="icon-btn" title="<?= Lang::e('ortak.sepet', 'Sepet') ?>"
                    aria-label="<?= Lang::e('ortak.sepet', 'Sepet') ?>">
                    <i class="fas fa-shopping-cart"></i>
                </a>
                <?php if (isset($_SESSION['client_id'])): ?>
                    <a href="client/dashboard.php" class="btn btn-primary">
                        <i class="fas fa-user"></i>
                        <?= Lang::e('ortak.panelim', 'Panelim') ?>
                    </a>
                <?php else: ?>
                    <a href="client/index.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i>
                        <?= Lang::e('ortak.giris-yap', 'Giriş Yap') ?>
                    </a>
                <?php endif; ?>
                <button class="hamburger" onclick="toggleMobileNav()">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </header>
    
    <!-- Mobile Nav Overlay -->
    <div class="nav-overlay" id="navOverlay" onclick="toggleMobileNav()"></div>
    
    <!-- Main Content -->
    <main class="main-content">
