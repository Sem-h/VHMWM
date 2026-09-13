<?php
/**
 * WHMVM - Mağaza Sayfası
 * linux-hosting.php tasarımı referans alınarak
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

$groupSlug = $_GET['group'] ?? '';
$group = null;
$products = [];
$allGroups = [];

// Tür konfigürasyonları
$typeConfig = [
    'hosting' => ['icon' => 'fa-globe', 'color' => '#fb923c', 'gradient' => 'linear-gradient(135deg, #fb923c 0%, #f97316 100%)'],
    'vps' => ['icon' => 'fa-server', 'color' => '#10b981', 'gradient' => 'linear-gradient(135deg, #10b981 0%, #059669 100%)'],
    'vds' => ['icon' => 'fa-database', 'color' => '#6366f1', 'gradient' => 'linear-gradient(135deg, #6366f1 0%, #4f46e5 100%)'],
    'dedicated' => ['icon' => 'fa-building', 'color' => '#8b5cf6', 'gradient' => 'linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%)'],
    'domain' => ['icon' => 'fa-link', 'color' => '#0ea5e9', 'gradient' => 'linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%)'],
    'ssl' => ['icon' => 'fa-shield-alt', 'color' => '#22c55e', 'gradient' => 'linear-gradient(135deg, #22c55e 0%, #16a34a 100%)'],
    'email' => ['icon' => 'fa-envelope', 'color' => '#f59e0b', 'gradient' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)'],
    'other' => ['icon' => 'fa-box', 'color' => '#64748b', 'gradient' => 'linear-gradient(135deg, #64748b 0%, #475569 100%)']
];

try {
    $allGroups = Database::fetchAll("SELECT * FROM product_groups WHERE is_hidden = 0 ORDER BY order_priority");

    if ($groupSlug) {
        $group = Database::fetch("SELECT * FROM product_groups WHERE slug = ?", [$groupSlug]);
        if ($group) {
            $products = Database::fetchAll(
                "SELECT * FROM products WHERE group_id = ? AND is_active = 1 ORDER BY order_priority, price_monthly",
                [$group['id']]
            );
        }
    } else {
        $products = Database::fetchAll(
            "SELECT p.*, g.name as group_name, g.type as group_type FROM products p 
             LEFT JOIN product_groups g ON p.group_id = g.id 
             WHERE p.is_active = 1 ORDER BY g.order_priority, p.order_priority"
        );
    }
} catch (Exception $e) {
    $products = [];
}

$currentType = $group['type'] ?? 'hosting';
$config = $typeConfig[$currentType] ?? $typeConfig['hosting'];

// Özel sayfa kontrolleri
$isArchivePage = ($groupSlug === 'arsiv-hosting');
$isWindowsPage = ($groupSlug === 'windows-hosting');
$isLinuxPage = ($groupSlug === 'linux-hosting');
$isBtkPage = ($groupSlug === 'btk-log-sunucu');
$isDedicatedPage = ($groupSlug === 'fiziksel-sunucu');
$isGpuPage = ($groupSlug === 'ekran-kartli-sunucu');
$isKurumsalPage = ($groupSlug === 'kurumsal-hosting');
$isWpPage = ($groupSlug === 'wordpress-hosting');
$isBuilderPage = ($groupSlug === 'website-builder');

if ($isBtkPage) {
    $config = [
        'icon' => 'fa-shield-alt',
        'color' => '#dc2626',
        'gradient' => 'linear-gradient(135deg, #dc2626 0%, #b91c1c 100%)'
    ];
}

if ($isArchivePage) {
    $config = [
        'icon' => 'fa-cloud-upload-alt',
        'color' => '#0ea5e9',
        'gradient' => 'linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%)'
    ];
}

if ($isWindowsPage) {
    $config = [
        'icon' => 'fa-windows',
        'color' => '#0078d4',
        'gradient' => 'linear-gradient(135deg, #0078d4 0%, #005a9e 100%)'
    ];
}

if ($isLinuxPage) {
    $config = [
        'icon' => 'fa-linux',
        'color' => '#f97316',
        'gradient' => 'linear-gradient(135deg, #f97316 0%, #ea580c 100%)'
    ];
}

if ($isWpPage) {
    $config = [
        'icon' => 'fa-wordpress',
        'color' => '#21759b',
        'gradient' => 'linear-gradient(135deg, #21759b 0%, #16537a 100%)'
    ];
}

if ($isBuilderPage) {
    $config = [
        'icon' => 'fa-wand-magic-sparkles',
        'color' => '#8b5cf6',
        'gradient' => 'linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%)'
    ];
}

if ($isDedicatedPage) {
    $config = [
        'icon' => 'fa-server',
        'color' => '#8b5cf6',
        'gradient' => 'linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%)'
    ];
}

if ($isGpuPage) {
    $config = [
        'icon' => 'fa-microchip',
        'color' => '#10b981',
        'gradient' => 'linear-gradient(135deg, #10b981 0%, #059669 100%)'
    ];
}

require_once __DIR__ . '/theme/includes/header.php';
?>

<?php if ($isBtkPage): ?>

    <?php
    /**
     * BTK Log Sunucu sayfası - ürün açıklamasından teknik özellikleri ayıkla.
     * "1000 MB Nvme Disk Alanı" gibi satırları vitrin kutucuğuna,
     * kalanları özellik listesine yollar.
     */
    $blSpecTypes = [
        'kabinet' => ['label' => 'Sunucu Alanı', 'icon' => 'fa-server', 'match' => '/sunucu\s?barındırma/iu'],
        'firewall' => ['label' => 'Firewall Alanı', 'icon' => 'fa-shield-halved', 'match' => '/firewall/iu'],
        'ip' => ['label' => 'Local IP', 'icon' => 'fa-network-wired', 'match' => '/ip\s?adres/iu'],
        'vpn' => ['label' => 'VPN Tüneli', 'icon' => 'fa-lock', 'match' => '/vpn/iu'],
    ];

    /** Satırdan değeri çek: "1U", "1 Adet", "Limitsiz" */
    $blSpecValue = static function (string $line): string {
        if (preg_match('/\b(limitsiz|sınırsız|unlimited)\b/iu', $line)) {
            return 'Limitsiz';
        }
        // Rack birimi bitişik yazılır: "1U", "2U"
        if (preg_match('/(\d+)\s*U\b/u', $line, $m)) {
            return $m[1] . 'U';
        }
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(TB|GB|MB|KB|Core|Adet|vCPU)?/iu', $line, $m)) {
            return trim($m[1] . ' ' . ($m[2] ?? ''));
        }
        return '';
    };
    ?>

    <style>
        /* ==========================================
           BTK Log Sunucu - sayfaya özel değişkenler
           Vurgu rengi ve yüzeyler global tema
           token'larından gelir.
           ========================================== */
        .bl {
            --bl-accent: var(--primary);
            --bl-accent-light: var(--primary);
            --bl-accent-dark: var(--primary-dark);
            --bl-accent-soft: color-mix(in srgb, var(--primary) 12%, transparent);
            --bl-accent-line: color-mix(in srgb, var(--primary) 28%, transparent);
            --bl-gradient: var(--gradient-primary);
        }

        /* ===== Hero ===== */
        .bl-hero {
            position: relative;
            overflow: hidden;
            background: var(--gradient-hero);
            padding: var(--space-6) 0 0;
        }

        .bl-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 55% 50% at 12% 30%, color-mix(in srgb, var(--primary) 22%, transparent) 0%, transparent 62%),
                radial-gradient(ellipse 45% 45% at 88% 10%, color-mix(in srgb, var(--secondary) 16%, transparent) 0%, transparent 58%);
            pointer-events: none;
        }

        .bl-hero>.container {
            position: relative;
            z-index: 1;
        }

        /* Tek kolon, ortalanmis: hero'da ayrica gorsel yok */
        .bl-hero-grid {
            max-width: 780px;
            margin: 0 auto;
            padding-bottom: var(--space-6);
            text-align: center;
        }

        .bl-hero-text .bl-lead {
            margin-left: auto;
            margin-right: auto;
        }

        .bl-hero-text .bl-stack {
            justify-content: center;
        }

        .bl-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 50px;
            background: var(--bl-accent-soft);
            border: 1px solid var(--bl-accent-line);
            color: var(--bl-accent-light);
            font-size: var(--text-sm);
            font-weight: 600;
            margin-bottom: var(--space-4);
        }

        .bl-title {
            /* Ürün sayfası; anasayfa kadar iri olmasına gerek yok */
            font-size: clamp(32px, 3.2vw + 16px, 50px);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -0.035em;
            margin-bottom: var(--space-3);
            color: var(--text-primary);
            text-wrap: balance;
        }

        .bl-title span {
            background: var(--bl-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .bl-lead {
            font-size: var(--text-md);
            color: var(--text-muted);
            line-height: 1.65;
            max-width: 48ch;
            margin-bottom: var(--space-5);
            text-wrap: pretty;
        }

        /* Teknoloji rozetleri - 2x2 kutu yerine tek sıra çip;
           hero yüksekliğini ~110px kısaltır, detaylar aşağıdaki
           teknoloji bölümünde zaten anlatılıyor. */
        .bl-stack {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .bl-chip {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 7px 14px 7px 7px;
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .bl-chip:hover {
            background: var(--surface-2);
            border-color: var(--bl-accent-line);
        }

        .bl-chip i {
            width: 26px;
            height: 26px;
            flex-shrink: 0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #fff;
        }

        .bl-chip i.is-cpanel {
            background: linear-gradient(135deg, #ff6c2c, #e8590c);
        }

        .bl-chip i.is-litespeed {
            background: linear-gradient(135deg, #22c55e, #15803d);
        }

        .bl-chip i.is-jetbackup {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .bl-chip i.is-alma {
            background: linear-gradient(135deg, #38bdf8, #0369a1);
        }

        /* ===== Terminal ===== */
        /* Surum listesi: cubuk sutunu yok */
        .bl-bars.is-stack .bl-bar-row {
            grid-template-columns: minmax(0, 1fr) auto;
        }

        /* BTK log hizmeti cip renkleri */
        .bl-chip i.is-devre {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
        }

        .bl-chip i.is-kabinet {
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
        }

        .bl-chip i.is-firewall {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .bl-chip i.is-vpn {
            background: linear-gradient(135deg, #22c55e, #15803d);
        }

        /* ===== Kategori sekmeleri ===== */
        .bl-tabs-wrap {
            position: relative;
            border-top: 1px solid var(--border-color);
            background: color-mix(in srgb, var(--bg-primary) 65%, transparent);
            backdrop-filter: blur(12px);
        }

        .bl-tabs {
            display: flex;
            gap: var(--space-2);
            overflow-x: auto;
            padding: var(--space-3) var(--space-4);
            scrollbar-width: none;
            /* Dokunmatik ekranda sekmeler hizaya otursun */
            scroll-snap-type: x proximity;
        }

        .bl-tabs::-webkit-scrollbar {
            display: none;
        }

        .bl-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            scroll-snap-align: start;
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            background: var(--surface-1);
            color: var(--text-muted);
            font-size: var(--text-sm);
            font-weight: 600;
            white-space: nowrap;
            transition: all var(--transition-normal) var(--ease-out);
        }

        .bl-tab:hover {
            color: var(--text-primary);
            border-color: var(--bl-accent-line);
            background: var(--surface-2);
        }

        .bl-tab.is-active {
            background: var(--bl-gradient);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 6px 18px color-mix(in srgb, var(--primary) 35%, transparent);
        }

        /* ===== Bölüm başlıkları ===== */
        .bl-section {
            padding: var(--section-padding) 0;
        }

        /* Paketler bölümü hemen sekme şeridinin altında;
           sekmeler zaten ayırıcı, üstte tam boşluğa gerek yok */
        .bl-hero+.bl-section {
            padding-top: var(--space-7);
        }

        .bl-section.is-alt {
            background: var(--bg-primary);
        }

        .bl-head {
            text-align: center;
            max-width: 680px;
            margin: 0 auto var(--space-7);
        }

        .bl-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 50px;
            background: var(--bl-accent-soft);
            border: 1px solid var(--bl-accent-line);
            color: var(--bl-accent-light);
            font-size: var(--text-sm);
            font-weight: 600;
            margin-bottom: var(--space-4);
        }

        .bl-head h2 {
            font-size: var(--text-3xl);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.15;
            color: var(--text-primary);
            text-wrap: balance;
        }

        .bl-head h2 span {
            background: var(--bl-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .bl-head p {
            margin-top: var(--space-3);
            font-size: var(--text-md);
            color: var(--text-muted);
            text-wrap: pretty;
        }

        /* ===== Paket kartları ===== */
        .bl-plans {
            display: grid;
            /* Sutun sayisi paket adedinden gelir; auto-fit reflow yapmaz */
            grid-template-columns: repeat(var(--plan-cols, 4), minmax(0, 1fr));
            gap: var(--space-5);
            /* Kartlar eşit yükseklikte olsun, sipariş butonları hizalansın */
            align-items: stretch;
        }

        .bl-plans[data-count="1"] {
            --plan-cols: 1;
            max-width: 420px;
            margin-inline: auto;
        }

        .bl-plans[data-count="2"] {
            --plan-cols: 2;
        }

        .bl-plans[data-count="3"] {
            --plan-cols: 3;
        }

        @media (max-width: 1180px) {
            .bl-plans[data-count] {
                --plan-cols: 2;
            }
        }

        @media (max-width: 680px) {
            .bl-plans[data-count] {
                --plan-cols: 1;
            }
        }

        /* Az paket varsa ortala, kartlar aşırı genişlemesin */
        .bl-plans[data-count="1"] {
            grid-template-columns: minmax(300px, 420px);
            justify-content: center;
        }

        .bl-plans[data-count="2"] {
            grid-template-columns: repeat(2, minmax(300px, 430px));
            justify-content: center;
        }

        .bl-plans[data-count="3"] {
            grid-template-columns: repeat(3, minmax(280px, 400px));
            justify-content: center;
        }

        .bl-plan {
            position: relative;
            display: flex;
            flex-direction: column;
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: transform var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .bl-plan:hover {
            transform: translateY(-6px);
            border-color: var(--bl-accent-line);
            box-shadow: var(--shadow-lg);
        }

        .bl-plan.is-popular {
            border-color: var(--bl-accent);
            box-shadow: 0 0 0 1px var(--bl-accent), 0 24px 48px -18px color-mix(in srgb, var(--primary) 45%, transparent);
        }

        .bl-ribbon {
            position: absolute;
            top: 0;
            right: var(--space-5);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 0 0 10px 10px;
            background: var(--bl-gradient);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .bl-plan-top {
            padding: var(--space-6) var(--space-5) var(--space-5);
            border-bottom: 1px solid var(--border-color);
        }

        .bl-plan-name {
            font-size: var(--text-xl);
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .bl-plan-group {
            font-size: var(--text-sm);
            color: var(--text-muted);
            margin-bottom: var(--space-5);
        }

        .bl-price {
            display: flex;
            align-items: baseline;
            gap: 4px;
            /* Rakamlar kartlar arasında hizalı dursun */
            font-variant-numeric: tabular-nums;
        }

        .bl-price .cur {
            font-size: var(--text-lg);
            font-weight: 700;
            color: var(--bl-accent);
        }

        .bl-price .val {
            font-size: clamp(34px, 3vw + 22px, 46px);
            font-weight: 800;
            letter-spacing: -0.04em;
            line-height: 1;
            color: var(--text-primary);
        }

        .bl-price .per {
            font-size: var(--text-base);
            color: var(--text-muted);
            font-weight: 500;
        }

        .bl-price-ask {
            font-size: var(--text-xl);
            font-weight: 700;
            color: var(--text-primary);
        }

        .bl-price-note {
            margin-top: var(--space-2);
            font-size: var(--text-sm);
            color: var(--text-muted);
        }

        .bl-save {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: var(--space-3);
            padding: 5px 12px;
            border-radius: 50px;
            background: color-mix(in srgb, #22c55e 15%, transparent);
            border: 1px solid color-mix(in srgb, #22c55e 32%, transparent);
            color: #4ade80;
            font-size: var(--text-xs);
            font-weight: 700;
        }

        /* Yıllık indirimi olmayan pakette yeri boş dursun ki
           kartlar arasında teknik kutular aynı hizada başlasın */
        .bl-save.is-placeholder {
            visibility: hidden;
        }

        /* Teknik özet kutucukları */
        .bl-specs {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1px;
            background: var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .bl-spec {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-4);
            background: var(--bg-primary);
        }

        .bl-spec i {
            width: 30px;
            height: 30px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: var(--bl-accent-soft);
            color: var(--bl-accent-light);
            font-size: 13px;
        }

        .bl-spec b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.25;
        }

        .bl-spec span {
            font-size: 11px;
            color: var(--text-muted);
        }

        .bl-plan-body {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: var(--space-5);
        }

        .bl-features {
            list-style: none;
            margin: 0 0 var(--space-5);
            padding: 0;
            display: grid;
            gap: 2px;
        }

        .bl-features li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 7px 0;
            font-size: var(--text-sm);
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .bl-features li i {
            margin-top: 3px;
            font-size: 11px;
            color: #22c55e;
            flex-shrink: 0;
        }

        /* Uzun listelerde kart şişmesin: fazlası gizli, düğmeyle açılır */
        .bl-features.is-clipped li:nth-child(n+7) {
            display: none;
        }

        .bl-more {
            align-self: flex-start;
            margin: calc(var(--space-5) * -1 + 4px) 0 var(--space-5);
            padding: 6px 0;
            background: none;
            border: none;
            color: var(--bl-accent-light);
            font-family: inherit;
            font-size: var(--text-sm);
            font-weight: 600;
            cursor: pointer;
        }

        .bl-more:hover {
            text-decoration: underline;
        }

        .bl-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            /* Buton kartın en altına yapışsın, kartlar eşit hizalansın */
            margin-top: auto;
            padding: 15px 24px;
            border-radius: var(--radius-md);
            border: 1px solid transparent;
            background: var(--bl-gradient);
            color: #fff;
            font-size: var(--text-base);
            font-weight: 700;
            box-shadow: 0 8px 22px color-mix(in srgb, var(--primary) 30%, transparent);
            transition: transform var(--transition-fast) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .bl-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 34px color-mix(in srgb, var(--primary) 48%, transparent);
        }

        .bl-cta:active {
            transform: translateY(0) scale(0.99);
        }

        /* Boş durum */
        .bl-empty {
            text-align: center;
            padding: var(--space-9) var(--space-5);
            border: 1px dashed var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--surface-1);
        }

        .bl-empty i {
            font-size: 42px;
            color: var(--bl-accent);
            margin-bottom: var(--space-4);
        }

        .bl-empty h3 {
            font-size: var(--text-xl);
            color: var(--text-primary);
            margin-bottom: var(--space-2);
        }

        .bl-empty p {
            color: var(--text-muted);
        }

        /* ===== "Her pakette standart" paneli =====
           8 ayrı kart yerine tek panel: hepsinin dahil olduğu
           tek bir vaat gibi okunur, dikey yer de yarıya iner. */
        .bl-included {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--surface-1);
            overflow: hidden;
        }

        .bl-included-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1px;
            background: var(--border-color);
        }

        .bl-inc {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            background: var(--bg-primary);
            transition: background-color var(--transition-normal) var(--ease-out);
        }

        .bl-inc:hover {
            background: color-mix(in srgb, var(--bl-accent) 6%, var(--bg-primary));
        }

        .bl-inc i {
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: var(--bl-accent-soft);
            color: var(--bl-accent-light);
            font-size: 14px;
        }

        .bl-inc b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .bl-inc span {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        /* ===== Teknoloji: bir büyük vitrin + üç kart ===== */
        .bl-spotlight {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 0.85fr);
            gap: var(--space-7);
            align-items: center;
            padding: var(--space-7);
            margin-bottom: var(--space-5);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background:
                radial-gradient(ellipse 60% 90% at 100% 50%, color-mix(in srgb, #22c55e 10%, transparent) 0%, transparent 70%),
                var(--surface-1);
        }

        .bl-spotlight-tag {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 14px;
            border-radius: 50px;
            background: color-mix(in srgb, #22c55e 14%, transparent);
            border: 1px solid color-mix(in srgb, #22c55e 30%, transparent);
            color: #4ade80;
            font-size: var(--text-xs);
            font-weight: 700;
            margin-bottom: var(--space-4);
        }

        .bl-spotlight h3 {
            font-size: var(--text-2xl);
            font-weight: 800;
            letter-spacing: -0.025em;
            color: var(--text-primary);
            margin-bottom: var(--space-3);
        }

        .bl-spotlight p {
            font-size: var(--text-md);
            color: var(--text-muted);
            line-height: 1.7;
            margin-bottom: var(--space-5);
            max-width: 46ch;
        }

        .bl-spotlight-points {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .bl-spotlight-points li {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 13px;
            border-radius: 50px;
            background: var(--surface-2);
            border: 1px solid var(--border-color);
            font-size: var(--text-xs);
            font-weight: 600;
            color: var(--text-secondary);
        }

        .bl-spotlight-points i {
            color: #4ade80;
            font-size: 10px;
        }

        /* Hız karşılaştırma çubukları */
        .bl-bars {
            display: grid;
            gap: var(--space-4);
        }

        .bl-bar-row {
            display: grid;
            grid-template-columns: 78px minmax(0, 1fr) 42px;
            align-items: center;
            gap: var(--space-3);
        }

        .bl-bar-label {
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--text-muted);
        }

        .bl-bar {
            height: 14px;
            border-radius: 50px;
            background: var(--surface-3);
            overflow: hidden;
        }

        .bl-bar span {
            display: block;
            height: 100%;
            border-radius: 50px;
            background: var(--text-gray);
        }

        .bl-bar-val {
            font-size: var(--text-base);
            font-weight: 800;
            color: var(--text-muted);
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .bl-bar-row.is-lead .bl-bar-label,
        .bl-bar-row.is-lead .bl-bar-val {
            color: var(--text-primary);
        }

        .bl-bar-row.is-lead .bl-bar span {
            background: linear-gradient(90deg, #22c55e, #15803d);
        }

        .bl-bar-note {
            font-size: var(--text-xs);
            color: var(--text-gray);
            line-height: 1.5;
            margin: 0;
        }

        .bl-tech {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: var(--space-5);
        }

        .bl-tech-card {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: var(--space-4);
            padding: var(--space-5);
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: transform var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .bl-tech-card::before {
            content: '';
            position: absolute;
            inset: 0 0 auto 0;
            height: 3px;
            background: var(--tint, var(--bl-gradient));
            transform: scaleX(0);
            transition: transform var(--transition-normal) var(--ease-out);
        }

        .bl-tech-card:hover {
            transform: translateY(-4px);
            background: var(--surface-2);
        }

        .bl-tech-card:hover::before {
            transform: scaleX(1);
        }

        .bl-tech-icon {
            width: 46px;
            height: 46px;
            flex-shrink: 0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            color: #fff;
            background: var(--tint, var(--bl-gradient));
        }

        .bl-tech-card h4 {
            font-size: var(--text-lg);
            font-weight: 700;
            letter-spacing: -0.015em;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .bl-tech-card p {
            font-size: var(--text-sm);
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* ===== Sık sorulan sorular ===== */
        /* Solda görsel + başlık, sağda soru listesi */
        .bl-faq-layout {
            display: grid;
            grid-template-columns: minmax(0, 330px) minmax(0, 1fr);
            gap: var(--space-8);
            /* Sol sutun listeyle ayni yuksekligi alsin */
            align-items: stretch;
        }

        .bl-faq-aside {
            display: flex;
            flex-direction: column;
        }

        .bl-head.is-left {
            text-align: left;
            max-width: 100%;
            margin: 0 0 var(--space-5);
        }

        .bl-faq-visual {
            /* Sabit yukseklik yerine kalan bosluk: liste uzayip kisaldikca hizali kalir */
            flex: 1 1 0;
            min-height: 120px;
            width: 100%;
            max-width: 330px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-4) 0;
        }

        .bl-faq-visual svg {
            width: 100%;
            height: 100%;
            max-height: 240px;
            display: block;
        }

        /* "Sorunuz yoksa bize yazın" kutusu */
        .bl-faq-help {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin-top: var(--space-5);
            padding: var(--space-4);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--surface-1);
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .bl-faq-help:hover {
            border-color: var(--bl-accent-line);
            background: var(--surface-2);
        }

        .bl-faq-help i {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--bl-gradient);
            color: #fff;
            font-size: 15px;
        }

        .bl-faq-help b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .bl-faq-help span {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        .bl-faq {
            display: grid;
            gap: var(--space-3);
        }

        .bl-faq-item {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--surface-1);
            overflow: hidden;
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .bl-faq-item[open] {
            border-color: var(--bl-accent-line);
            background: var(--surface-2);
        }

        .bl-faq-item summary {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            cursor: pointer;
            font-size: var(--text-md);
            font-weight: 600;
            color: var(--text-primary);
            list-style: none;
        }

        /* Soru ikonu - konuya özel satır içi SVG */
        .bl-faq-icon {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--bl-accent-soft);
            border: 1px solid var(--bl-accent-line);
            color: var(--bl-accent-light);
            transition: background-color var(--transition-normal) var(--ease-out),
                color var(--transition-normal) var(--ease-out);
        }

        .bl-faq-icon svg {
            width: 19px;
            height: 19px;
            display: block;
        }

        .bl-faq-item[open] .bl-faq-icon {
            background: var(--bl-gradient);
            border-color: transparent;
            color: #fff;
        }

        /* Soru metni ile artı işareti arasını doldurur */
        .bl-faq-q {
            flex: 1;
            min-width: 0;
        }

        /* Tarayıcının varsayılan üçgen işaretini kaldır */
        .bl-faq-item summary::-webkit-details-marker {
            display: none;
        }

        .bl-faq-item summary::marker {
            content: '';
        }

        .bl-faq-item summary:hover {
            color: var(--bl-accent-light);
        }

        .bl-faq-item summary:focus-visible {
            outline: none;
            box-shadow: var(--focus-ring);
        }

        .bl-faq-sign {
            width: 28px;
            height: 28px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--bl-accent-soft);
            color: var(--bl-accent-light);
            font-size: 12px;
            transition: transform var(--transition-normal) var(--ease-out);
        }

        .bl-faq-item[open] .bl-faq-sign {
            transform: rotate(45deg);
        }

        .bl-faq-body {
            /* Cevap, sorunun metniyle aynı hizadan başlasın (ikon + boşluk kadar içeride) */
            padding: 0 var(--space-5) var(--space-5) calc(var(--space-5) + 38px + var(--space-3));
            font-size: var(--text-base);
            color: var(--text-muted);
            line-height: 1.75;
            max-width: 72ch;
        }

        /* ===== Kapanış çağrısı ===== */
        .bl-final {
            position: relative;
            overflow: hidden;
            padding: var(--space-8) 0;
            background: var(--bl-gradient);
            color: #fff;
            text-align: center;
        }

        .bl-final::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 100% at 50% 0%, rgba(255, 255, 255, 0.2) 0%, transparent 62%),
                radial-gradient(ellipse 50% 80% at 88% 100%, rgba(0, 0, 0, 0.18) 0%, transparent 60%);
            pointer-events: none;
        }

        .bl-final>.container {
            position: relative;
            z-index: 1;
        }

        .bl-final h3 {
            font-size: var(--text-2xl);
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: var(--space-3);
            text-wrap: balance;
        }

        .bl-final p {
            font-size: var(--text-md);
            opacity: 0.92;
            margin-bottom: var(--space-6);
        }

        .bl-final-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: var(--space-3);
        }

        .bl-final-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 32px;
            border-radius: var(--radius-md);
            background: #fff;
            color: var(--bl-accent-dark);
            font-size: var(--text-md);
            font-weight: 700;
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.2);
            transition: transform var(--transition-fast) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .bl-final-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.28);
        }

        .bl-final-link {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 15px 26px;
            border-radius: var(--radius-md);
            border: 1px solid rgba(255, 255, 255, 0.45);
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            font-size: var(--text-md);
            font-weight: 600;
            transition: background-color var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out);
        }

        .bl-final-link:hover {
            background: rgba(255, 255, 255, 0.22);
            border-color: #fff;
        }

        /* ===== Duyarlılık ===== */
        @media (max-width: 1100px) {
            .bl-included-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .bl-spotlight {
                grid-template-columns: 1fr;
                gap: var(--space-6);
                padding: var(--space-6);
            }

            .bl-spotlight p {
                max-width: 100%;
            }
        }

        @media (max-width: 992px) {
            .bl-lead {
                max-width: 100%;
            }

            .bl-tech {
                grid-template-columns: 1fr;
            }

            /* Görsel ve başlık üste, sorular altına */
            .bl-faq-layout {
                grid-template-columns: 1fr;
                gap: var(--space-6);
                justify-items: center;
            }

            .bl-faq-aside {
                max-width: 460px;
                width: 100%;
            }

            .bl-head.is-left {
                text-align: center;
            }

            .bl-faq-visual {
                flex: 0 0 auto;
                margin: 0 auto;
            }

            .bl-faq-visual svg {
                height: auto;
            }

            .bl-faq {
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            .bl-plans,
            .bl-plans[data-count="2"],
            .bl-plans[data-count="3"] {
                grid-template-columns: 1fr;
            }

            .bl-included-grid {
                grid-template-columns: 1fr;
            }

            .bl-bar-row {
                grid-template-columns: 66px minmax(0, 1fr) 34px;
                gap: var(--space-2);
            }

            .bl-final-actions {
                flex-direction: column;
            }

            .bl-final-actions>* {
                width: 100%;
                justify-content: center;
            }

            /* Dar ekranda cevabı ikon hizasında girintilemek yer israfı olur */
            .bl-faq-body {
                padding-left: var(--space-5);
            }

            .bl-faq-item summary {
                padding-left: var(--space-4);
                padding-right: var(--space-4);
            }
        }
    </style>

    <div class="bl">

        <!-- Hero -->
        <section class="bl-hero">
            <div class="container">
                <div class="bl-hero-grid">
                    <div class="bl-hero-text">
                        <span class="bl-eyebrow"><i class="fas fa-shield-halved"></i> BTK Uyumlu Log Kayıt Altyapısı</span>
                        <h1 class="bl-title"><span>BTK Log Sunucu</span> Paketleri</h1>
                        <p class="bl-lead">
                            BTK ile noktadan noktaya devre, veri merkezi kabinet alanı ve şifreli VPN
                            tüneli tek pakette. Kurulum ve danışmanlık her pakette ücretsiz.
                        </p>

                        <div class="bl-stack">
                            <span class="bl-chip"><i class="fas fa-network-wired is-devre"></i> N/N Devre</span>
                            <span class="bl-chip"><i class="fas fa-server is-kabinet"></i> Kabinet Alanı</span>
                            <span class="bl-chip"><i class="fas fa-shield-halved is-firewall"></i> Firewall</span>
                            <span class="bl-chip"><i class="fas fa-lock is-vpn"></i> VPN Tüneli</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kategori sekmeleri -->
            <div class="bl-tabs-wrap">
                <div class="container">
                    <div class="bl-tabs">
                        <a href="store.php" class="bl-tab"><i class="fas fa-th-large"></i> Tüm Ürünler</a>
                        <a href="store.php?group=btk-log-sunucu" class="bl-tab is-active"><i class="fas fa-shield-halved"></i> BTK Log
                            Sunucu</a>
                        <?php foreach ($allGroups as $g):
                            if ($g['slug'] === 'btk-log-sunucu') {
                                continue;
                            }
                            $gCount = Database::fetchColumn("SELECT COUNT(*) FROM products WHERE group_id = ? AND is_active = 1", [$g['id']]);
                            if ($gCount <= 0) {
                                continue;
                            }
                            ?>
                            <a href="store.php?group=<?= htmlspecialchars($g['slug']) ?>" class="bl-tab">
                                <i class="fas <?= $typeConfig[$g['type'] ?? 'hosting']['icon'] ?? 'fa-box' ?>"></i>
                                <?= htmlspecialchars($g['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Paketler -->
        <section class="bl-section" id="paketler">
            <div class="container">
                <div class="bl-head">
                    <span class="bl-badge"><i class="fas fa-shield-halved"></i> BTK Log Sunucu Paketleri</span>
                    <h2>Kurumunuza Uygun <span>Log Planı</span></h2>
                    <p>Tüm paketlerde BTK ile N/N devre, VPN tüneli, ücretsiz kurulum ve danışmanlık standarttır.</p>
                </div>

                <?php if (empty($products)): ?>
                    <div class="bl-empty">
                        <i class="fas fa-shield-halved"></i>
                        <h3>Ürün Bulunamadı</h3>
                        <p>Bu kategoride henüz ürün bulunmuyor.</p>
                    </div>
                <?php else:
                    $productCount = count($products);

                    // En az bir pakette yıllık indirim varsa, olmayanlarda rozet
                    // yüksekliği kadar boşluk bırakılır; kartlar hizalı kalır.
                    $anySaving = false;
                    foreach ($products as $p) {
                        $m = (float) ($p['price_monthly'] ?? 0);
                        $a = (float) ($p['price_annually'] ?? 0);
                        if ($m > 0 && $a > 0 && $a < $m * 12) {
                            $anySaving = true;
                            break;
                        }
                    }
                    ?>
                    <div class="bl-plans" data-count="<?= $productCount ?>">
                        <?php
                        // Öne çıkan ürün işaretliyse onu, değilse ortadaki paketi vurgula
                        $popularIndex = -1;
                        foreach ($products as $i => $p) {
                            if (!empty($p['is_featured'])) {
                                $popularIndex = $i;
                                break;
                            }
                        }
                        if ($popularIndex === -1 && $productCount > 2) {
                            $popularIndex = (int) floor(($productCount - 1) / 2);
                        }

                        foreach ($products as $index => $product):
                            $isPopular = ($index === $popularIndex);
                            $price = (float) ($product['price_monthly'] ?? 0);
                            $annual = (float) ($product['price_annually'] ?? 0);
                            $setup = (float) ($product['setup_fee'] ?? 0);

                            // Açıklama satırlarını teknik özet ve özellik listesi olarak ayır
                            $lines = [];
                            foreach (preg_split('/\r\n|\r|\n/', (string) ($product['description'] ?? '')) as $line) {
                                $line = trim($line);
                                if ($line !== '') {
                                    $lines[] = $line;
                                }
                            }

                            $specs = [];
                            $usedLines = [];
                            foreach ($blSpecTypes as $key => $spec) {
                                if (count($specs) >= 4) {
                                    break;
                                }
                                foreach ($lines as $li => $line) {
                                    if (isset($usedLines[$li]) || !preg_match($spec['match'], $line)) {
                                        continue;
                                    }
                                    $value = $blSpecValue($line);
                                    if ($value === '') {
                                        continue;
                                    }
                                    $specs[] = ['label' => $spec['label'], 'icon' => $spec['icon'], 'value' => $value];
                                    $usedLines[$li] = true;
                                    break;
                                }
                            }

                            $features = [];
                            foreach ($lines as $li => $line) {
                                if (!isset($usedLines[$li])) {
                                    $features[] = $line;
                                }
                            }
                            if (empty($features) && empty($specs)) {
                                $features = [
                                    'BTK ile N/N Devre',
                                    'VPN Tüneli',
                                    'Ücretsiz Kurulum',
                                    'Ücretsiz SSL Sertifikası',
                                    '7/24 Teknik Destek',
                                ];
                            }

                            // Yıllık ödemede aylığa göre kazanç
                            $savePercent = 0;
                            if ($price > 0 && $annual > 0 && $annual < $price * 12) {
                                $savePercent = (int) round((1 - ($annual / ($price * 12))) * 100);
                            }

                            $clip = count($features) > 6;
                            $listId = 'lx-feat-' . (int) $product['id'];
                            ?>
                            <article class="bl-plan <?= $isPopular ? 'is-popular' : '' ?>">
                                <?php if ($isPopular): ?>
                                    <div class="bl-ribbon"><i class="fas fa-fire"></i> En Popüler</div>
                                <?php endif; ?>

                                <div class="bl-plan-top">
                                    <div class="bl-plan-name"><?= htmlspecialchars($product['name']) ?></div>
                                    <div class="bl-plan-group">BTK Log Sunucu</div>

                                    <?php if ($price > 0): ?>
                                        <div class="bl-price">
                                            <span class="cur">₺</span>
                                            <span class="val"><?= number_format(floor($price), 0, ',', '.') ?></span>
                                            <span class="per">/ay</span>
                                        </div>
                                        <?php if ($setup > 0): ?>
                                            <div class="bl-price-note">
                                                + ₺<?= number_format($setup, 0, ',', '.') ?> tek seferlik kurulum
                                            </div>
                                        <?php else: ?>
                                            <div class="bl-price-note">Kurulum ücreti yok</div>
                                        <?php endif; ?>
                                        <?php if ($savePercent > 0): ?>
                                            <span class="bl-save">
                                                <i class="fas fa-tag"></i> Yıllık ödemede %<?= $savePercent ?> indirim
                                            </span>
                                        <?php elseif ($anySaving): ?>
                                            <span class="bl-save is-placeholder" aria-hidden="true">
                                                <i class="fas fa-tag"></i> &nbsp;
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="bl-price-ask">Fiyat için görüşelim</div>
                                        <div class="bl-price-note">İhtiyacınıza göre özel teklif hazırlıyoruz</div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($specs)): ?>
                                    <div class="bl-specs">
                                        <?php foreach ($specs as $spec): ?>
                                            <div class="bl-spec">
                                                <i class="fas <?= $spec['icon'] ?>"></i>
                                                <div>
                                                    <b><?= htmlspecialchars($spec['value']) ?></b>
                                                    <span><?= htmlspecialchars($spec['label']) ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="bl-plan-body">
                                    <?php if (!empty($features)): ?>
                                        <ul class="bl-features <?= $clip ? 'is-clipped' : '' ?>" id="<?= $listId ?>">
                                            <?php foreach ($features as $feature): ?>
                                                <li><i class="fas fa-check"></i> <?= htmlspecialchars($feature) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php if ($clip): ?>
                                            <button type="button" class="bl-more" data-target="<?= $listId ?>"
                                                aria-expanded="false" aria-controls="<?= $listId ?>">
                                                + <?= count($features) - 6 ?> özellik daha
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <a class="bl-cta" href="/client/order-configure.php?id=<?= (int) $product['id'] ?>">
                                        <i class="fas fa-shopping-cart"></i> Sipariş Ver
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Her pakette standart -->
        <section class="bl-section is-alt">
            <div class="container">
                <div class="bl-head">
                    <span class="bl-badge"><i class="fas fa-check-double"></i> Pakete Dahil</span>
                    <h2>Hepsi <span>Her Pakette Standart</span></h2>
                    <p>Aşağıdakiler için ek ücret ödemezsiniz; en küçük pakette de aynen geçerli.</p>
                </div>

                <div class="bl-included">
                    <div class="bl-included-grid">
                        <div class="bl-inc">
                            <i class="fas fa-network-wired"></i>
                            <div>
                                <b>BTK ile N/N Devre</b>
                                <span>Noktadan noktaya bağlantı</span>
                            </div>
                        </div>
                        <div class="bl-inc">
                            <i class="fas fa-server"></i>
                            <div>
                                <b>Sunucu Barındırma</b>
                                <span>Veri merkezi kabinetinde</span>
                            </div>
                        </div>
                        <div class="bl-inc">
                            <i class="fas fa-shield-halved"></i>
                            <div>
                                <b>Firewall Alanı</b>
                                <span>1U firewall barındırma</span>
                            </div>
                        </div>
                        <div class="bl-inc">
                            <i class="fas fa-lock"></i>
                            <div>
                                <b>VPN Tüneli</b>
                                <span>Şifreli uzak erişim</span>
                            </div>
                        </div>
                        <div class="bl-inc">
                            <i class="fas fa-diagram-project"></i>
                            <div>
                                <b>Local IP Adresi</b>
                                <span>Kurum içi adresleme</span>
                            </div>
                        </div>
                        <div class="bl-inc">
                            <i class="fas fa-screwdriver-wrench"></i>
                            <div>
                                <b>Ücretsiz Kurulum</b>
                                <span>Devreye almayı biz yaparız</span>
                            </div>
                        </div>
                        <div class="bl-inc">
                            <i class="fas fa-comments"></i>
                            <div>
                                <b>Ücretsiz Danışmanlık</b>
                                <span>Süreç boyunca yanınızdayız</span>
                            </div>
                        </div>
                        <div class="bl-inc">
                            <i class="fas fa-headset"></i>
                            <div>
                                <b>7/24 Destek</b>
                                <span>Uzman teknik ekip</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Teknoloji altyapısı -->
        <section class="bl-section">
            <div class="container">
                <div class="bl-head">
                    <span class="bl-badge"><i class="fas fa-microchip"></i> Teknoloji Altyapısı</span>
                    <h2>Devre ve <span>Kabinet</span></h2>
                    <p>Paketleri ayıran şey kabinet alanı; devre, VPN ve destek her pakette aynı.</p>
                </div>

                <!-- Vitrin: devre -->
                <div class="bl-spotlight">
                    <div class="bl-spotlight-text">
                        <span class="bl-spotlight-tag"><i class="fas fa-network-wired"></i> Bağlantı</span>
                        <h3>BTK ile N/N Devre</h3>
                        <p>
                            Log trafiği internet üzerinden değil, BTK ile aranızdaki noktadan
                            noktaya devre üzerinden taşınır.
                        </p>
                        <ul class="bl-spotlight-points">
                            <li><i class="fas fa-check"></i> Noktadan noktaya devre</li>
                            <li><i class="fas fa-check"></i> Şifreli VPN tüneli</li>
                            <li><i class="fas fa-check"></i> Local IP adresleme</li>
                            <li><i class="fas fa-check"></i> Firewall arkasında</li>
                        </ul>
                    </div>

                    <div class="bl-bars is-stack">
                        <div class="bl-bar-row"><span class="bl-bar-label">Devre</span><span class="bl-bar-val">BTK ile N/N</span></div>
                        <div class="bl-bar-row"><span class="bl-bar-label">Barındırma</span><span class="bl-bar-val">Veri merkezi kabineti</span></div>
                        <div class="bl-bar-row"><span class="bl-bar-label">Erişim</span><span class="bl-bar-val">VPN tüneli</span></div>
                        <div class="bl-bar-row"><span class="bl-bar-label">Kurulum</span><span class="bl-bar-val">Ücretsiz</span></div>
                        <p class="bl-bar-note">
                            Kabinet alanı pakete göre değişir; devre ve erişim bütün paketlerde aynıdır.
                        </p>
                    </div>
                </div>

                <div class="bl-tech">
                    <div class="bl-tech-card" style="--tint: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                        <div class="bl-tech-icon"><i class="fas fa-server"></i></div>
                        <div>
                            <h4>Kabinet Barındırma</h4>
                            <p>Sunucunuz ve firewall'unuz veri merkezimizde; kesintisiz güç ve iklimlendirme altında.</p>
                        </div>
                    </div>
                    <div class="bl-tech-card" style="--tint: linear-gradient(135deg, #22c55e, #15803d);">
                        <div class="bl-tech-icon"><i class="fas fa-lock"></i></div>
                        <div>
                            <h4>VPN Tüneli</h4>
                            <p>Sunucunuza şifreli tünel üzerinden bağlanırsınız; yönetim arayüzü dışarı açılmaz.</p>
                        </div>
                    </div>
                    <div class="bl-tech-card" style="--tint: linear-gradient(135deg, #0ea5e9, #0284c7);">
                        <div class="bl-tech-icon"><i class="fas fa-screwdriver-wrench"></i></div>
                        <div>
                            <h4>Kurulum ve Danışmanlık</h4>
                            <p>Devre başvurusundan devreye almaya kadar süreci ücretsiz olarak biz yürütürüz.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sık sorulanlar -->
        <section class="bl-section is-alt">
            <div class="container">
                <div class="bl-faq-layout">
                    <div class="bl-faq-aside">
                        <div class="bl-head is-left">
                            <span class="bl-badge"><i class="fas fa-circle-question"></i> Sık Sorulanlar</span>
                            <h2>Aklınıza <span>Takılanlar</span></h2>
                            <p>Satın almadan önce en çok merak edilenleri derledik.</p>
                        </div>

                        <div class="bl-faq-visual">
                            <!-- Soru-cevap balonları; renkler tema değişkenlerinden gelir -->
                            <svg viewBox="0 0 320 272" fill="none" aria-hidden="true">
                                <defs>
                                    <linearGradient id="lxQmark" x1="0" y1="0" x2="1" y2="1">
                                        <stop offset="0" stop-color="var(--primary-light)" />
                                        <stop offset="1" stop-color="var(--primary-dark)" />
                                    </linearGradient>
                                    <radialGradient id="lxHalo" cx="0.5" cy="0.5" r="0.5">
                                        <stop offset="0" stop-color="var(--primary)" stop-opacity="0.20" />
                                        <stop offset="1" stop-color="var(--primary)" stop-opacity="0" />
                                    </radialGradient>
                                </defs>

                                <ellipse cx="160" cy="136" rx="152" ry="122" fill="url(#lxHalo)" />

                                <!-- Cevap balonu (arkada) -->
                                <path d="M276 242 v22 l-26 -22 z" fill="var(--surface-2)" stroke="var(--border-color)"
                                    stroke-width="2" stroke-linejoin="round" />
                                <rect x="104" y="150" width="200" height="92" rx="22" fill="var(--surface-2)"
                                    stroke="var(--border-color)" stroke-width="2" />
                                <rect x="132" y="172" width="140" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.55" />
                                <rect x="132" y="194" width="118" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.4" />
                                <rect x="132" y="216" width="78" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.28" />

                                <!-- Soru balonu (önde) -->
                                <path d="M44 126 v22 l26 -22 z" fill="color-mix(in srgb, var(--primary) 12%, transparent)" stroke="var(--primary)"
                                    stroke-width="2" stroke-linejoin="round" />
                                <rect x="16" y="26" width="196" height="100" rx="24" fill="color-mix(in srgb, var(--primary) 12%, transparent)"
                                    stroke="var(--primary)" stroke-width="2" />
                                <rect x="44" y="56" width="112" height="9" rx="4.5" fill="var(--primary-light)" opacity="0.85" />
                                <rect x="44" y="78" width="140" height="9" rx="4.5" fill="var(--primary-light)" opacity="0.6" />
                                <rect x="44" y="100" width="84" height="9" rx="4.5" fill="var(--primary-light)" opacity="0.4" />

                                <!-- Soru işareti rozeti -->
                                <circle cx="234" cy="44" r="28" fill="url(#lxQmark)" />
                                <text x="234" y="45" text-anchor="middle" dominant-baseline="central" fill="#fff"
                                    font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="34"
                                    font-weight="800">?</text>

                                <!-- Serpiştirilmiş noktalar -->
                                <circle cx="292" cy="104" r="5" fill="var(--primary)" opacity="0.5" />
                                <circle cx="306" cy="126" r="3" fill="var(--primary)" opacity="0.3" />
                                <circle cx="24" cy="186" r="4" fill="var(--primary)" opacity="0.35" />
                                <circle cx="44" cy="210" r="6" fill="var(--primary)" opacity="0.2" />
                            </svg>
                        </div>

                        <a href="contact.php" class="bl-faq-help">
                            <i class="fas fa-headset"></i>
                            <div>
                                <b>Sorunuz listede yok mu?</b>
                                <span>Destek ekibimize yazın, aynı gün dönelim.</span>
                            </div>
                        </a>
                    </div>

                <div class="bl-faq">
                    <details class="bl-faq-item">
                        <summary>
                            <span class="bl-faq-icon">
                                <!-- Kontrol paneli: bölmeli ekran -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="3.5" width="18" height="17" rx="2.5" />
                                    <path d="M3 9.5h18" />
                                    <path d="M9.5 20.5v-11" />
                                </svg>
                            </span>
                            <span class="bl-faq-q">Bu hizmet kimler için gerekli?</span>
                            <span class="bl-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="bl-faq-body">
                            5651 sayılı kanun kapsamında internet erişimi sunan kurumlar — otel, AVM, kafe,
                            hastane, kamu kurumları — erişim kayıtlarını saklamakla yükümlüdür.
                        </div>
                    </details>

                    <details class="bl-faq-item">
                        <summary>
                            <span class="bl-faq-icon">
                                <!-- Yedekleme: geri sarma oku + saat ibresi -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3.2 12a8.8 8.8 0 1 0 2.9-6.5" />
                                    <path d="M3 4.2v4.6h4.6" />
                                    <path d="M12 8.2V12l2.9 1.7" />
                                </svg>
                            </span>
                            <span class="bl-faq-q">Sunucu donanımını ben mi sağlıyorum?</span>
                            <span class="bl-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="bl-faq-body">
                            Paketler kabinet alanı, devre ve erişim barındırmasını içerir. Sunucu ve firewall
                            donanımını siz sağlarsınız; tedarik konusunda da danışmanlık veriyoruz.
                        </div>
                    </details>

                    <details class="bl-faq-item">
                        <summary>
                            <span class="bl-faq-icon">
                                <!-- SSL: asma kilit -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="4" y="10.2" width="16" height="10.3" rx="2.4" />
                                    <path d="M8 10.2V7.1a4 4 0 0 1 8 0v3.1" />
                                    <path d="M12 14.4v2.2" />
                                </svg>
                            </span>
                            <span class="bl-faq-q">Kurulum için ayrıca ödeme yapacak mıyım?</span>
                            <span class="bl-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="bl-faq-body">
                            Hayır. Kurulum ve danışmanlık bütün paketlerde ücretsizdir; paket bedeli dışında
                            tek seferlik bir ücret çıkmaz.
                        </div>
                    </details>

                    <details class="bl-faq-item">
                        <summary>
                            <span class="bl-faq-icon">
                                <!-- Erişim: şifreli tünel -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="4" y="10.4" width="16" height="10" rx="2.4" />
                                    <path d="M8.2 10.4V7.8a3.8 3.8 0 0 1 7.6 0v2.6" />
                                    <path d="M12 14.4v2.2" />
                                </svg>
                            </span>
                            <span class="bl-faq-q">Kayıtlarıma nasıl erişiyorum?</span>
                            <span class="bl-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="bl-faq-body">
                            VPN tüneli üzerinden kendi sunucunuza bağlanırsınız. Yönetim arayüzü internete
                            açık değildir, yalnızca tünel içinden erişilir.
                        </div>
                    </details>

                    <details class="bl-faq-item">
                        <summary>
                            <span class="bl-faq-icon">
                                <!-- İşletim sistemi: sunucu rafı -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="4" width="18" height="7" rx="2.2" />
                                    <rect x="3" y="13" width="18" height="7" rx="2.2" />
                                    <path d="M7 7.5h.01" />
                                    <path d="M7 16.5h.01" />
                                </svg>
                            </span>
                            <span class="bl-faq-q">Kabinet alanını sonradan büyütebilir miyim?</span>
                            <span class="bl-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="bl-faq-body">
                            Evet. Üst pakete geçerek ek U alanı alabilirsiniz; devre ve VPN yapılandırmanız
                            aynı kalır, yalnızca kabinet alanı artar.
                        </div>
                    </details>

                    <details class="bl-faq-item">
                        <summary>
                            <span class="bl-faq-icon">
                                <!-- Taşıma: karşılıklı transfer okları -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4 9h15" />
                                    <path d="m15.5 5.5 3.5 3.5-3.5 3.5" />
                                    <path d="M20 15H5" />
                                    <path d="M8.5 11.5 5 15l3.5 3.5" />
                                </svg>
                            </span>
                            <span class="bl-faq-q">Devre başvurusunu kim yapıyor?</span>
                            <span class="bl-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="bl-faq-body">
                            Başvuru ve devreye alma sürecini biz yürütürüz. Sizden yalnızca kurum bilgileri
                            ve imza gereken evraklar istenir.
                        </div>
                    </details>
                    </div>
                </div>
            </div>
        </section>

        <!-- Kapanış -->
        <section class="bl-final">
            <div class="container">
                <h3><i class="fas fa-shield-halved"></i> BTK Log Sunucu ile Başlayın</h3>
                <p>N/N devre, kabinet alanı ve VPN tüneli tek pakette</p>
                <div class="bl-final-actions">
                    <a href="#paketler" class="bl-final-btn">
                        <i class="fas fa-rocket"></i> Paketleri İncele
                    </a>
                    <a href="contact.php" class="bl-final-link">
                        <i class="fas fa-comments"></i> Önce Soru Sormak İsterim
                    </a>
                </div>
            </div>
        </section>
    </div>

    <script>
        // "+N özellik daha" düğmesi: listedeki gizli satırları açar
        document.querySelectorAll('.bl-more').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var list = document.getElementById(btn.dataset.target);
                if (!list) return;
                var expanded = list.classList.toggle('is-clipped') === false;
                btn.setAttribute('aria-expanded', String(expanded));
                btn.textContent = expanded
                    ? 'Daha az göster'
                    : '+ ' + (list.children.length - 6) + ' özellik daha';
            });
        });
    </script>

    <?php require_once __DIR__ . '/theme/includes/footer.php'; ?>

<?php elseif ($isDedicatedPage): ?>

    <?php
    /**
     * Fiziksel Sunucu sayfası - ürün açıklamasından teknik özellikleri ayıkla.
     * "1000 MB Nvme Disk Alanı" gibi satırları vitrin kutucuğuna,
     * kalanları özellik listesine yollar.
     */
    $fzSpecTypes = [
        'cpu' => ['label' => 'İşlemci', 'icon' => 'fa-microchip', 'match' => '/çekirdek|\bcpu\b|\bcore\b/iu'],
        'ram' => ['label' => 'Bellek', 'icon' => 'fa-memory', 'match' => '/\bram\b|bellek/iu'],
        'disk' => ['label' => 'Disk', 'icon' => 'fa-hard-drive', 'match' => '/\bdisk\b/iu'],
        'traffic' => ['label' => 'Trafik', 'icon' => 'fa-right-left', 'match' => '/trafik|bant\s?geniş/iu'],
    ];

    /**
     * Donanım satırından değeri çek. Etiket sözcüğü zaten kutucuğun altında
     * yazdığı için satırın sonundaki tekrar atılır:
     * "24 Çekirdek İşlemci" -> "24 Çekirdek", "2x 480 GB SSD Disk" -> "2x 480 GB SSD"
     */
    $fzSpecValue = static function (string $line): string {
        if (preg_match('/\b(limitsiz|sınırsız|limitlendirilmemiş|unlimited)\b/iu', $line)) {
            return 'Limitsiz';
        }
        $deger = preg_replace('/\s*([iİ]şlemci|cpu|ram|bellek|disk|trafik)\b.*$/iu', '', $line);
        $deger = trim($deger);
        return $deger !== '' ? $deger : trim($line);
    };

    /**
     * Donanım fotoğrafı: theme/assets/img/donanim/<ad>.(webp|jpg|jpeg|png)
     * Dosya varsa fotoğraf basılır, yoksa sayfadaki SVG çizim gösterilir.
     * Böylece fotoğrafı klasöre atmak yeterli, kod değişikliği gerekmez.
     */
    $fzGorsel = static function (string $ad): ?string {
        foreach (['webp', 'jpg', 'jpeg', 'png'] as $uzanti) {
            $yol = '/theme/assets/img/donanim/' . $ad . '.' . $uzanti;
            if (is_file(__DIR__ . $yol)) {
                return $yol . '?v=' . filemtime(__DIR__ . $yol);
            }
        }
        return null;
    };
    ?>

    <style>
        /* ==========================================
           Fiziksel Sunucu - sayfaya özel değişkenler
           Vurgu rengi ve yüzeyler global tema
           token'larından gelir.
           ========================================== */
        .fz {
            --fz-accent: var(--primary);
            --fz-accent-light: var(--primary);
            --fz-accent-dark: var(--primary-dark);
            --fz-accent-soft: color-mix(in srgb, var(--primary) 12%, transparent);
            --fz-accent-line: color-mix(in srgb, var(--primary) 28%, transparent);
            --fz-gradient: var(--gradient-primary);
        }

        /* ===== Hero ===== */
        .fz-hero {
            position: relative;
            overflow: hidden;
            background: var(--gradient-hero);
            padding: var(--space-6) 0 0;
        }

        .fz-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 55% 50% at 12% 30%, color-mix(in srgb, var(--primary) 22%, transparent) 0%, transparent 62%),
                radial-gradient(ellipse 45% 45% at 88% 10%, color-mix(in srgb, var(--secondary) 16%, transparent) 0%, transparent 58%);
            pointer-events: none;
        }

        .fz-hero>.container {
            position: relative;
            z-index: 1;
        }

        /* Tek kolon, ortalanmis: hero'da ayrica gorsel yok */
        .fz-hero-grid {
            max-width: 780px;
            margin: 0 auto;
            padding-bottom: var(--space-6);
            text-align: center;
        }

        .fz-hero-text .fz-lead {
            margin-left: auto;
            margin-right: auto;
        }

        .fz-hero-text .fz-stack {
            justify-content: center;
        }

        .fz-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 50px;
            background: var(--fz-accent-soft);
            border: 1px solid var(--fz-accent-line);
            color: var(--fz-accent-light);
            font-size: var(--text-sm);
            font-weight: 600;
            margin-bottom: var(--space-4);
        }

        .fz-title {
            /* Ürün sayfası; anasayfa kadar iri olmasına gerek yok */
            font-size: clamp(32px, 3.2vw + 16px, 50px);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -0.035em;
            margin-bottom: var(--space-3);
            color: var(--text-primary);
            text-wrap: balance;
        }

        .fz-title span {
            background: var(--fz-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .fz-lead {
            font-size: var(--text-md);
            color: var(--text-muted);
            line-height: 1.65;
            max-width: 48ch;
            margin-bottom: var(--space-5);
            text-wrap: pretty;
        }

        /* Teknoloji rozetleri - 2x2 kutu yerine tek sıra çip;
           hero yüksekliğini ~110px kısaltır, detaylar aşağıdaki
           teknoloji bölümünde zaten anlatılıyor. */
        .fz-stack {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .fz-chip {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 7px 14px 7px 7px;
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .fz-chip:hover {
            background: var(--surface-2);
            border-color: var(--fz-accent-line);
        }

        .fz-chip i {
            width: 26px;
            height: 26px;
            flex-shrink: 0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #fff;
        }

        .fz-chip i.is-cpanel {
            background: linear-gradient(135deg, #ff6c2c, #e8590c);
        }

        .fz-chip i.is-litespeed {
            background: linear-gradient(135deg, #22c55e, #15803d);
        }

        .fz-chip i.is-jetbackup {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .fz-chip i.is-alma {
            background: linear-gradient(135deg, #38bdf8, #0369a1);
        }

        /* ===== Terminal ===== */
        /* Surum listesi: cubuk sutunu yok */
        .fz-bars.is-stack .fz-bar-row {
            grid-template-columns: minmax(0, 1fr) auto;
        }

        /* Windows teknoloji cip renkleri */
        .fz-chip i.is-cpu {
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
        }

        .fz-chip i.is-ram {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
        }

        .fz-chip i.is-disk {
            background: linear-gradient(135deg, #22c55e, #15803d);
        }

        .fz-chip i.is-idrac {
            background: linear-gradient(135deg, #f59e0b, #b45309);
        }

        /* ===== Kategori sekmeleri ===== */
        .fz-tabs-wrap {
            position: relative;
            border-top: 1px solid var(--border-color);
            background: color-mix(in srgb, var(--bg-primary) 65%, transparent);
            backdrop-filter: blur(12px);
        }

        .fz-tabs {
            display: flex;
            gap: var(--space-2);
            overflow-x: auto;
            padding: var(--space-3) var(--space-4);
            scrollbar-width: none;
            /* Dokunmatik ekranda sekmeler hizaya otursun */
            scroll-snap-type: x proximity;
        }

        .fz-tabs::-webkit-scrollbar {
            display: none;
        }

        .fz-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            scroll-snap-align: start;
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            background: var(--surface-1);
            color: var(--text-muted);
            font-size: var(--text-sm);
            font-weight: 600;
            white-space: nowrap;
            transition: all var(--transition-normal) var(--ease-out);
        }

        .fz-tab:hover {
            color: var(--text-primary);
            border-color: var(--fz-accent-line);
            background: var(--surface-2);
        }

        .fz-tab.is-active {
            background: var(--fz-gradient);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 6px 18px color-mix(in srgb, var(--primary) 35%, transparent);
        }

        /* ===== Bölüm başlıkları ===== */
        .fz-section {
            padding: var(--section-padding) 0;
        }

        /* Paketler bölümü hemen sekme şeridinin altında;
           sekmeler zaten ayırıcı, üstte tam boşluğa gerek yok */
        .fz-hero+.fz-section {
            padding-top: var(--space-7);
        }

        .fz-section.is-alt {
            background: var(--bg-primary);
        }

        .fz-head {
            text-align: center;
            max-width: 680px;
            margin: 0 auto var(--space-7);
        }

        .fz-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 50px;
            background: var(--fz-accent-soft);
            border: 1px solid var(--fz-accent-line);
            color: var(--fz-accent-light);
            font-size: var(--text-sm);
            font-weight: 600;
            margin-bottom: var(--space-4);
        }

        .fz-head h2 {
            font-size: var(--text-3xl);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.15;
            color: var(--text-primary);
            text-wrap: balance;
        }

        .fz-head h2 span {
            background: var(--fz-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .fz-head p {
            margin-top: var(--space-3);
            font-size: var(--text-md);
            color: var(--text-muted);
            text-wrap: pretty;
        }

        /* ===== Paket kartları ===== */
        .fz-plans {
            display: grid;
            /* Sutun sayisi paket adedinden gelir; auto-fit reflow yapmaz */
            grid-template-columns: repeat(var(--plan-cols, 4), minmax(0, 1fr));
            gap: var(--space-5);
            /* Kartlar eşit yükseklikte olsun, sipariş butonları hizalansın */
            align-items: stretch;
        }

        .fz-plans[data-count="1"] {
            --plan-cols: 1;
            max-width: 420px;
            margin-inline: auto;
        }

        .fz-plans[data-count="2"] {
            --plan-cols: 2;
        }

        .fz-plans[data-count="3"] {
            --plan-cols: 3;
        }

        @media (max-width: 1180px) {
            .fz-plans[data-count] {
                --plan-cols: 2;
            }
        }

        @media (max-width: 680px) {
            .fz-plans[data-count] {
                --plan-cols: 1;
            }
        }

        /* Az paket varsa ortala, kartlar aşırı genişlemesin */
        .fz-plans[data-count="1"] {
            grid-template-columns: minmax(300px, 420px);
            justify-content: center;
        }

        .fz-plans[data-count="2"] {
            grid-template-columns: repeat(2, minmax(300px, 430px));
            justify-content: center;
        }

        .fz-plans[data-count="3"] {
            grid-template-columns: repeat(3, minmax(280px, 400px));
            justify-content: center;
        }

        .fz-plan {
            position: relative;
            display: flex;
            flex-direction: column;
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: transform var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .fz-plan:hover {
            transform: translateY(-6px);
            border-color: var(--fz-accent-line);
            box-shadow: var(--shadow-lg);
        }

        .fz-plan.is-popular {
            border-color: var(--fz-accent);
            box-shadow: 0 0 0 1px var(--fz-accent), 0 24px 48px -18px color-mix(in srgb, var(--primary) 45%, transparent);
        }

        .fz-ribbon {
            position: absolute;
            top: 0;
            right: var(--space-5);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 0 0 10px 10px;
            background: var(--fz-gradient);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .fz-plan-top {
            padding: var(--space-6) var(--space-5) var(--space-5);
            border-bottom: 1px solid var(--border-color);
        }

        .fz-plan-name {
            font-size: var(--text-xl);
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .fz-plan-group {
            font-size: var(--text-sm);
            color: var(--text-muted);
            margin-bottom: var(--space-5);
        }

        .fz-price {
            display: flex;
            align-items: baseline;
            gap: 4px;
            /* Rakamlar kartlar arasında hizalı dursun */
            font-variant-numeric: tabular-nums;
        }

        .fz-price .cur {
            font-size: var(--text-lg);
            font-weight: 700;
            color: var(--fz-accent);
        }

        .fz-price .val {
            font-size: clamp(34px, 3vw + 22px, 46px);
            font-weight: 800;
            letter-spacing: -0.04em;
            line-height: 1;
            color: var(--text-primary);
        }

        .fz-price .per {
            font-size: var(--text-base);
            color: var(--text-muted);
            font-weight: 500;
        }

        .fz-price-ask {
            font-size: var(--text-xl);
            font-weight: 700;
            color: var(--text-primary);
        }

        .fz-price-note {
            margin-top: var(--space-2);
            font-size: var(--text-sm);
            color: var(--text-muted);
        }

        .fz-save {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: var(--space-3);
            padding: 5px 12px;
            border-radius: 50px;
            background: color-mix(in srgb, #22c55e 15%, transparent);
            border: 1px solid color-mix(in srgb, #22c55e 32%, transparent);
            color: #4ade80;
            font-size: var(--text-xs);
            font-weight: 700;
        }

        /* Yıllık indirimi olmayan pakette yeri boş dursun ki
           kartlar arasında teknik kutular aynı hizada başlasın */
        .fz-save.is-placeholder {
            visibility: hidden;
        }

        /* Teknik özet kutucukları */
        .fz-specs {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1px;
            background: var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .fz-spec {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-4);
            background: var(--bg-primary);
        }

        .fz-spec i {
            width: 30px;
            height: 30px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: var(--fz-accent-soft);
            color: var(--fz-accent-light);
            font-size: 13px;
        }

        .fz-spec b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.25;
        }

        .fz-spec span {
            font-size: 11px;
            color: var(--text-muted);
        }

        .fz-plan-body {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: var(--space-5);
        }

        .fz-features {
            list-style: none;
            margin: 0 0 var(--space-5);
            padding: 0;
            display: grid;
            gap: 2px;
        }

        .fz-features li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 7px 0;
            font-size: var(--text-sm);
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .fz-features li i {
            margin-top: 3px;
            font-size: 11px;
            color: #22c55e;
            flex-shrink: 0;
        }

        /* Uzun listelerde kart şişmesin: fazlası gizli, düğmeyle açılır */
        .fz-features.is-clipped li:nth-child(n+7) {
            display: none;
        }

        .fz-more {
            align-self: flex-start;
            margin: calc(var(--space-5) * -1 + 4px) 0 var(--space-5);
            padding: 6px 0;
            background: none;
            border: none;
            color: var(--fz-accent-light);
            font-family: inherit;
            font-size: var(--text-sm);
            font-weight: 600;
            cursor: pointer;
        }

        .fz-more:hover {
            text-decoration: underline;
        }

        .fz-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            /* Buton kartın en altına yapışsın, kartlar eşit hizalansın */
            margin-top: auto;
            padding: 15px 24px;
            border-radius: var(--radius-md);
            border: 1px solid transparent;
            background: var(--fz-gradient);
            color: #fff;
            font-size: var(--text-base);
            font-weight: 700;
            box-shadow: 0 8px 22px color-mix(in srgb, var(--primary) 30%, transparent);
            transition: transform var(--transition-fast) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .fz-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 34px color-mix(in srgb, var(--primary) 48%, transparent);
        }

        .fz-cta:active {
            transform: translateY(0) scale(0.99);
        }

        /* Boş durum */
        .fz-empty {
            text-align: center;
            padding: var(--space-9) var(--space-5);
            border: 1px dashed var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--surface-1);
        }

        .fz-empty i {
            font-size: 42px;
            color: var(--fz-accent);
            margin-bottom: var(--space-4);
        }

        .fz-empty h3 {
            font-size: var(--text-xl);
            color: var(--text-primary);
            margin-bottom: var(--space-2);
        }

        .fz-empty p {
            color: var(--text-muted);
        }

        /* ===== "Her pakette standart" paneli =====
           8 ayrı kart yerine tek panel: hepsinin dahil olduğu
           tek bir vaat gibi okunur, dikey yer de yarıya iner. */
        .fz-included {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--surface-1);
            overflow: hidden;
        }

        .fz-included-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1px;
            background: var(--border-color);
        }

        .fz-inc {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            background: var(--bg-primary);
            transition: background-color var(--transition-normal) var(--ease-out);
        }

        .fz-inc:hover {
            background: color-mix(in srgb, var(--fz-accent) 6%, var(--bg-primary));
        }

        .fz-inc i {
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: var(--fz-accent-soft);
            color: var(--fz-accent-light);
            font-size: 14px;
        }

        .fz-inc b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .fz-inc span {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        /* ===== Teknoloji: bir büyük vitrin + üç kart ===== */
        .fz-spotlight {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 0.85fr);
            gap: var(--space-7);
            align-items: center;
            padding: var(--space-7);
            margin-bottom: var(--space-5);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background:
                radial-gradient(ellipse 60% 90% at 100% 50%, color-mix(in srgb, #22c55e 10%, transparent) 0%, transparent 70%),
                var(--surface-1);
        }

        .fz-spotlight-tag {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 14px;
            border-radius: 50px;
            background: color-mix(in srgb, #22c55e 14%, transparent);
            border: 1px solid color-mix(in srgb, #22c55e 30%, transparent);
            color: #4ade80;
            font-size: var(--text-xs);
            font-weight: 700;
            margin-bottom: var(--space-4);
        }

        .fz-spotlight h3 {
            font-size: var(--text-2xl);
            font-weight: 800;
            letter-spacing: -0.025em;
            color: var(--text-primary);
            margin-bottom: var(--space-3);
        }

        .fz-spotlight p {
            font-size: var(--text-md);
            color: var(--text-muted);
            line-height: 1.7;
            margin-bottom: var(--space-5);
            max-width: 46ch;
        }

        .fz-spotlight-points {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .fz-spotlight-points li {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 13px;
            border-radius: 50px;
            background: var(--surface-2);
            border: 1px solid var(--border-color);
            font-size: var(--text-xs);
            font-weight: 600;
            color: var(--text-secondary);
        }

        .fz-spotlight-points i {
            color: #4ade80;
            font-size: 10px;
        }

        /* Hız karşılaştırma çubukları */
        .fz-bars {
            display: grid;
            gap: var(--space-4);
        }

        .fz-bar-row {
            display: grid;
            grid-template-columns: 78px minmax(0, 1fr) 42px;
            align-items: center;
            gap: var(--space-3);
        }

        .fz-bar-label {
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--text-muted);
        }

        .fz-bar {
            height: 14px;
            border-radius: 50px;
            background: var(--surface-3);
            overflow: hidden;
        }

        .fz-bar span {
            display: block;
            height: 100%;
            border-radius: 50px;
            background: var(--text-gray);
        }

        .fz-bar-val {
            font-size: var(--text-base);
            font-weight: 800;
            color: var(--text-muted);
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .fz-bar-row.is-lead .fz-bar-label,
        .fz-bar-row.is-lead .fz-bar-val {
            color: var(--text-primary);
        }

        .fz-bar-row.is-lead .fz-bar span {
            background: linear-gradient(90deg, #22c55e, #15803d);
        }

        .fz-bar-note {
            font-size: var(--text-xs);
            color: var(--text-gray);
            line-height: 1.5;
            margin: 0;
        }

        .fz-tech {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: var(--space-5);
        }

        .fz-tech-card {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: var(--space-4);
            padding: var(--space-5);
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: transform var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .fz-tech-card::before {
            content: '';
            position: absolute;
            inset: 0 0 auto 0;
            height: 3px;
            background: var(--tint, var(--fz-gradient));
            transform: scaleX(0);
            transition: transform var(--transition-normal) var(--ease-out);
        }

        .fz-tech-card:hover {
            transform: translateY(-4px);
            background: var(--surface-2);
        }

        .fz-tech-card:hover::before {
            transform: scaleX(1);
        }

        .fz-tech-icon {
            width: 46px;
            height: 46px;
            flex-shrink: 0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            color: #fff;
            background: var(--tint, var(--fz-gradient));
        }

        .fz-tech-card h4 {
            font-size: var(--text-lg);
            font-weight: 700;
            letter-spacing: -0.015em;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .fz-tech-card p {
            font-size: var(--text-sm);
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* ===== Donanım çizimleri =====
           Dell R730 ve EMC VNX7600 ön panelleri SVG olarak çizilir.
           Bütün renkler tema token'larına bağlıdır; açık/koyu temada
           ayrı bir görsel gerekmez. */
        .fz-spotlight.is-flip {
            grid-template-columns: minmax(0, 0.85fr) minmax(0, 1fr);
            background:
                radial-gradient(ellipse 60% 90% at 0% 50%, color-mix(in srgb, #22c55e 10%, transparent) 0%, transparent 70%),
                var(--surface-1);
        }

        .fz-spotlight.is-flip .fz-spotlight-text {
            order: 2;
        }

        .fz-spotlight.is-flip .fz-hw {
            order: 1;
        }

        .fz-hw {
            margin: 0;
            min-width: 0;
        }

        /* Fotoğraf varsa o basılır; yoksa aynı yere SVG çizim gelir.
           Çizim kendi renklerini taşır, temayla değişmez. */
        .fz-hw img {
            display: block;
            width: 100%;
            height: auto;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            background: var(--surface-2);
        }

        .fz-hw svg {
            display: block;
            width: 100%;
            height: auto;
        }

        .fz-hw figcaption {
            margin-top: var(--space-4);
            text-align: center;
            font-size: var(--text-xs);
            color: var(--text-muted);
            letter-spacing: 0.01em;
        }

        @media (max-width: 900px) {
            .fz-spotlight.is-flip {
                grid-template-columns: 1fr;
            }

            .fz-spotlight.is-flip .fz-spotlight-text {
                order: 1;
            }

            .fz-spotlight.is-flip .fz-hw {
                order: 2;
            }
        }

        /* ===== Sık sorulan sorular ===== */
        /* Solda görsel + başlık, sağda soru listesi */
        .fz-faq-layout {
            display: grid;
            grid-template-columns: minmax(0, 330px) minmax(0, 1fr);
            gap: var(--space-8);
            /* Sol sutun listeyle ayni yuksekligi alsin */
            align-items: stretch;
        }

        .fz-faq-aside {
            display: flex;
            flex-direction: column;
        }

        .fz-head.is-left {
            text-align: left;
            max-width: 100%;
            margin: 0 0 var(--space-5);
        }

        .fz-faq-visual {
            /* Sabit yukseklik yerine kalan bosluk: liste uzayip kisaldikca hizali kalir */
            flex: 1 1 0;
            min-height: 120px;
            width: 100%;
            max-width: 330px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-4) 0;
        }

        .fz-faq-visual svg {
            width: 100%;
            height: 100%;
            max-height: 240px;
            display: block;
        }

        /* "Sorunuz yoksa bize yazın" kutusu */
        .fz-faq-help {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin-top: var(--space-5);
            padding: var(--space-4);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--surface-1);
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .fz-faq-help:hover {
            border-color: var(--fz-accent-line);
            background: var(--surface-2);
        }

        .fz-faq-help i {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--fz-gradient);
            color: #fff;
            font-size: 15px;
        }

        .fz-faq-help b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .fz-faq-help span {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        .fz-faq {
            display: grid;
            gap: var(--space-3);
        }

        .fz-faq-item {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--surface-1);
            overflow: hidden;
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .fz-faq-item[open] {
            border-color: var(--fz-accent-line);
            background: var(--surface-2);
        }

        .fz-faq-item summary {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            cursor: pointer;
            font-size: var(--text-md);
            font-weight: 600;
            color: var(--text-primary);
            list-style: none;
        }

        /* Soru ikonu - konuya özel satır içi SVG */
        .fz-faq-icon {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--fz-accent-soft);
            border: 1px solid var(--fz-accent-line);
            color: var(--fz-accent-light);
            transition: background-color var(--transition-normal) var(--ease-out),
                color var(--transition-normal) var(--ease-out);
        }

        .fz-faq-icon svg {
            width: 19px;
            height: 19px;
            display: block;
        }

        .fz-faq-item[open] .fz-faq-icon {
            background: var(--fz-gradient);
            border-color: transparent;
            color: #fff;
        }

        /* Soru metni ile artı işareti arasını doldurur */
        .fz-faq-q {
            flex: 1;
            min-width: 0;
        }

        /* Tarayıcının varsayılan üçgen işaretini kaldır */
        .fz-faq-item summary::-webkit-details-marker {
            display: none;
        }

        .fz-faq-item summary::marker {
            content: '';
        }

        .fz-faq-item summary:hover {
            color: var(--fz-accent-light);
        }

        .fz-faq-item summary:focus-visible {
            outline: none;
            box-shadow: var(--focus-ring);
        }

        .fz-faq-sign {
            width: 28px;
            height: 28px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--fz-accent-soft);
            color: var(--fz-accent-light);
            font-size: 12px;
            transition: transform var(--transition-normal) var(--ease-out);
        }

        .fz-faq-item[open] .fz-faq-sign {
            transform: rotate(45deg);
        }

        .fz-faq-body {
            /* Cevap, sorunun metniyle aynı hizadan başlasın (ikon + boşluk kadar içeride) */
            padding: 0 var(--space-5) var(--space-5) calc(var(--space-5) + 38px + var(--space-3));
            font-size: var(--text-base);
            color: var(--text-muted);
            line-height: 1.75;
            max-width: 72ch;
        }

        /* ===== Kapanış çağrısı ===== */
        .fz-final {
            position: relative;
            overflow: hidden;
            padding: var(--space-8) 0;
            background: var(--fz-gradient);
            color: #fff;
            text-align: center;
        }

        .fz-final::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 100% at 50% 0%, rgba(255, 255, 255, 0.2) 0%, transparent 62%),
                radial-gradient(ellipse 50% 80% at 88% 100%, rgba(0, 0, 0, 0.18) 0%, transparent 60%);
            pointer-events: none;
        }

        .fz-final>.container {
            position: relative;
            z-index: 1;
        }

        .fz-final h3 {
            font-size: var(--text-2xl);
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: var(--space-3);
            text-wrap: balance;
        }

        .fz-final p {
            font-size: var(--text-md);
            opacity: 0.92;
            margin-bottom: var(--space-6);
        }

        .fz-final-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: var(--space-3);
        }

        .fz-final-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 32px;
            border-radius: var(--radius-md);
            background: #fff;
            color: var(--fz-accent-dark);
            font-size: var(--text-md);
            font-weight: 700;
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.2);
            transition: transform var(--transition-fast) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .fz-final-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.28);
        }

        .fz-final-link {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 15px 26px;
            border-radius: var(--radius-md);
            border: 1px solid rgba(255, 255, 255, 0.45);
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            font-size: var(--text-md);
            font-weight: 600;
            transition: background-color var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out);
        }

        .fz-final-link:hover {
            background: rgba(255, 255, 255, 0.22);
            border-color: #fff;
        }

        /* ===== Duyarlılık ===== */
        @media (max-width: 1100px) {
            .fz-included-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .fz-spotlight {
                grid-template-columns: 1fr;
                gap: var(--space-6);
                padding: var(--space-6);
            }

            .fz-spotlight p {
                max-width: 100%;
            }
        }

        @media (max-width: 992px) {
            .fz-lead {
                max-width: 100%;
            }

            .fz-tech {
                grid-template-columns: 1fr;
            }

            /* Görsel ve başlık üste, sorular altına */
            .fz-faq-layout {
                grid-template-columns: 1fr;
                gap: var(--space-6);
                justify-items: center;
            }

            .fz-faq-aside {
                max-width: 460px;
                width: 100%;
            }

            .fz-head.is-left {
                text-align: center;
            }

            .fz-faq-visual {
                flex: 0 0 auto;
                margin: 0 auto;
            }

            .fz-faq-visual svg {
                height: auto;
            }

            .fz-faq {
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            .fz-plans,
            .fz-plans[data-count="2"],
            .fz-plans[data-count="3"] {
                grid-template-columns: 1fr;
            }

            .fz-included-grid {
                grid-template-columns: 1fr;
            }

            .fz-bar-row {
                grid-template-columns: 66px minmax(0, 1fr) 34px;
                gap: var(--space-2);
            }

            .fz-final-actions {
                flex-direction: column;
            }

            .fz-final-actions>* {
                width: 100%;
                justify-content: center;
            }

            /* Dar ekranda cevabı ikon hizasında girintilemek yer israfı olur */
            .fz-faq-body {
                padding-left: var(--space-5);
            }

            .fz-faq-item summary {
                padding-left: var(--space-4);
                padding-right: var(--space-4);
            }
        }
    </style>

    <div class="fz">

        <!-- Hero -->
        <section class="fz-hero">
            <div class="container">
                <div class="fz-hero-grid">
                    <div class="fz-hero-text">
                        <span class="fz-eyebrow"><i class="fas fa-server"></i> Dell PowerEdge Donanım</span>
                        <h1 class="fz-title"><span>Fiziksel Sunucu</span> Paketleri</h1>
                        <p class="fz-lead">
                            Kaynağı kimseyle paylaşmayan, size ayrılmış Dell PowerEdge sunucular.
                            iDRAC erişimi, donanımsal RAID ve ücretsiz kurulum her pakette standart.
                        </p>

                        <div class="fz-stack">
                            <span class="fz-chip"><i class="fas fa-microchip is-cpu"></i> Intel Xeon</span>
                            <span class="fz-chip"><i class="fas fa-memory is-ram"></i> DDR4 ECC</span>
                            <span class="fz-chip"><i class="fas fa-hard-drive is-disk"></i> SSD RAID</span>
                            <span class="fz-chip"><i class="fas fa-sliders is-idrac"></i> iDRAC</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kategori sekmeleri -->
            <div class="fz-tabs-wrap">
                <div class="container">
                    <div class="fz-tabs">
                        <a href="store.php" class="fz-tab"><i class="fas fa-th-large"></i> Tüm Ürünler</a>
                        <a href="store.php?group=fiziksel-sunucu" class="fz-tab is-active"><i class="fas fa-server"></i> Fiziksel
                            Sunucu</a>
                        <?php foreach ($allGroups as $g):
                            if ($g['slug'] === 'fiziksel-sunucu') {
                                continue;
                            }
                            $gCount = Database::fetchColumn("SELECT COUNT(*) FROM products WHERE group_id = ? AND is_active = 1", [$g['id']]);
                            if ($gCount <= 0) {
                                continue;
                            }
                            ?>
                            <a href="store.php?group=<?= htmlspecialchars($g['slug']) ?>" class="fz-tab">
                                <i class="fas <?= $typeConfig[$g['type'] ?? 'hosting']['icon'] ?? 'fa-box' ?>"></i>
                                <?= htmlspecialchars($g['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Paketler -->
        <section class="fz-section" id="paketler">
            <div class="container">
                <div class="fz-head">
                    <span class="fz-badge"><i class="fas fa-server"></i> Fiziksel Sunucu Paketleri</span>
                    <h2>Yükünüze Uygun <span>Donanım</span></h2>
                    <p>Tüm paketlerde iDRAC erişimi, donanımsal RAID ve ücretsiz kurulum standart olarak sunulur.</p>
                </div>

                <?php if (empty($products)): ?>
                    <div class="fz-empty">
                        <i class="fas fa-server"></i>
                        <h3>Ürün Bulunamadı</h3>
                        <p>Bu kategoride henüz ürün bulunmuyor.</p>
                    </div>
                <?php else:
                    $productCount = count($products);

                    // En az bir pakette yıllık indirim varsa, olmayanlarda rozet
                    // yüksekliği kadar boşluk bırakılır; kartlar hizalı kalır.
                    $anySaving = false;
                    foreach ($products as $p) {
                        $m = (float) ($p['price_monthly'] ?? 0);
                        $a = (float) ($p['price_annually'] ?? 0);
                        if ($m > 0 && $a > 0 && $a < $m * 12) {
                            $anySaving = true;
                            break;
                        }
                    }
                    ?>
                    <div class="fz-plans" data-count="<?= $productCount ?>">
                        <?php
                        // Öne çıkan ürün işaretliyse onu, değilse ortadaki paketi vurgula
                        $popularIndex = -1;
                        foreach ($products as $i => $p) {
                            if (!empty($p['is_featured'])) {
                                $popularIndex = $i;
                                break;
                            }
                        }
                        if ($popularIndex === -1 && $productCount > 2) {
                            $popularIndex = (int) floor(($productCount - 1) / 2);
                        }

                        foreach ($products as $index => $product):
                            $isPopular = ($index === $popularIndex);
                            $price = (float) ($product['price_monthly'] ?? 0);
                            $annual = (float) ($product['price_annually'] ?? 0);
                            $setup = (float) ($product['setup_fee'] ?? 0);

                            // Açıklama satırlarını teknik özet ve özellik listesi olarak ayır
                            $lines = [];
                            foreach (preg_split('/\r\n|\r|\n/', (string) ($product['description'] ?? '')) as $line) {
                                $line = trim($line);
                                if ($line !== '') {
                                    $lines[] = $line;
                                }
                            }

                            $specs = [];
                            $usedLines = [];
                            foreach ($fzSpecTypes as $key => $spec) {
                                if (count($specs) >= 4) {
                                    break;
                                }
                                foreach ($lines as $li => $line) {
                                    if (isset($usedLines[$li]) || !preg_match($spec['match'], $line)) {
                                        continue;
                                    }
                                    $value = $fzSpecValue($line);
                                    if ($value === '') {
                                        continue;
                                    }
                                    $specs[] = ['label' => $spec['label'], 'icon' => $spec['icon'], 'value' => $value];
                                    $usedLines[$li] = true;
                                    break;
                                }
                            }

                            $features = [];
                            foreach ($lines as $li => $line) {
                                if (!isset($usedLines[$li])) {
                                    $features[] = $line;
                                }
                            }
                            if (empty($features) && empty($specs)) {
                                $features = [
                                    'iDRAC Uzaktan Yönetim',
                                    'Donanımsal RAID',
                                    'Ücretsiz Kurulum',
                                    'Ücretsiz SSL Sertifikası',
                                    '7/24 Teknik Destek',
                                ];
                            }

                            // Yıllık ödemede aylığa göre kazanç
                            $savePercent = 0;
                            if ($price > 0 && $annual > 0 && $annual < $price * 12) {
                                $savePercent = (int) round((1 - ($annual / ($price * 12))) * 100);
                            }

                            $clip = count($features) > 6;
                            $listId = 'lx-feat-' . (int) $product['id'];
                            ?>
                            <article class="fz-plan <?= $isPopular ? 'is-popular' : '' ?>">
                                <?php if ($isPopular): ?>
                                    <div class="fz-ribbon"><i class="fas fa-fire"></i> En Popüler</div>
                                <?php endif; ?>

                                <div class="fz-plan-top">
                                    <div class="fz-plan-name"><?= htmlspecialchars($product['name']) ?></div>
                                    <div class="fz-plan-group">Fiziksel Sunucu</div>

                                    <?php if ($price > 0): ?>
                                        <div class="fz-price">
                                            <span class="cur">₺</span>
                                            <span class="val"><?= number_format(floor($price), 0, ',', '.') ?></span>
                                            <span class="per">/ay</span>
                                        </div>
                                        <?php if ($setup > 0): ?>
                                            <div class="fz-price-note">
                                                + ₺<?= number_format($setup, 0, ',', '.') ?> tek seferlik kurulum
                                            </div>
                                        <?php else: ?>
                                            <div class="fz-price-note">Kurulum ücreti yok</div>
                                        <?php endif; ?>
                                        <?php if ($savePercent > 0): ?>
                                            <span class="fz-save">
                                                <i class="fas fa-tag"></i> Yıllık ödemede %<?= $savePercent ?> indirim
                                            </span>
                                        <?php elseif ($anySaving): ?>
                                            <span class="fz-save is-placeholder" aria-hidden="true">
                                                <i class="fas fa-tag"></i> &nbsp;
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="fz-price-ask">Fiyat için görüşelim</div>
                                        <div class="fz-price-note">İhtiyacınıza göre özel teklif hazırlıyoruz</div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($specs)): ?>
                                    <div class="fz-specs">
                                        <?php foreach ($specs as $spec): ?>
                                            <div class="fz-spec">
                                                <i class="fas <?= $spec['icon'] ?>"></i>
                                                <div>
                                                    <b><?= htmlspecialchars($spec['value']) ?></b>
                                                    <span><?= htmlspecialchars($spec['label']) ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="fz-plan-body">
                                    <?php if (!empty($features)): ?>
                                        <ul class="fz-features <?= $clip ? 'is-clipped' : '' ?>" id="<?= $listId ?>">
                                            <?php foreach ($features as $feature): ?>
                                                <li><i class="fas fa-check"></i> <?= htmlspecialchars($feature) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php if ($clip): ?>
                                            <button type="button" class="fz-more" data-target="<?= $listId ?>"
                                                aria-expanded="false" aria-controls="<?= $listId ?>">
                                                + <?= count($features) - 6 ?> özellik daha
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <a class="fz-cta" href="/client/order-configure.php?id=<?= (int) $product['id'] ?>">
                                        <i class="fas fa-shopping-cart"></i> Sipariş Ver
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Her pakette standart -->
        <section class="fz-section is-alt">
            <div class="container">
                <div class="fz-head">
                    <span class="fz-badge"><i class="fas fa-check-double"></i> Pakete Dahil</span>
                    <h2>Hepsi <span>Her Pakette Standart</span></h2>
                    <p>Aşağıdakiler için ek ücret ödemezsiniz; en küçük pakette de aynen geçerli.</p>
                </div>

                <div class="fz-included">
                    <div class="fz-included-grid">
                        <div class="fz-inc">
                            <i class="fas fa-sliders"></i>
                            <div>
                                <b>iDRAC Erişimi</b>
                                <span>Sunucuyu uzaktan yönetin</span>
                            </div>
                        </div>
                        <div class="fz-inc">
                            <i class="fas fa-layer-group"></i>
                            <div>
                                <b>Donanımsal RAID</b>
                                <span>RAID 0 / 1 / 5 / 10</span>
                            </div>
                        </div>
                        <div class="fz-inc">
                            <i class="fas fa-shield-halved"></i>
                            <div>
                                <b>DDoS Koruması</b>
                                <span>Şebeke seviyesinde filtre</span>
                            </div>
                        </div>
                        <div class="fz-inc">
                            <i class="fas fa-plug-circle-bolt"></i>
                            <div>
                                <b>Yedekli Güç</b>
                                <span>Çift PSU, jeneratör desteği</span>
                            </div>
                        </div>
                        <div class="fz-inc">
                            <i class="fas fa-network-wired"></i>
                            <div>
                                <b>1 Gbps Port</b>
                                <span>Limitlendirilmemiş trafik</span>
                            </div>
                        </div>
                        <div class="fz-inc">
                            <i class="fas fa-screwdriver-wrench"></i>
                            <div>
                                <b>Ücretsiz Kurulum</b>
                                <span>İşletim sistemi bizden</span>
                            </div>
                        </div>
                        <div class="fz-inc">
                            <i class="fas fa-location-dot"></i>
                            <div>
                                <b>Türkiye Lokasyonu</b>
                                <span>Kendi veri merkezimizde</span>
                            </div>
                        </div>
                        <div class="fz-inc">
                            <i class="fas fa-headset"></i>
                            <div>
                                <b>7/24 Destek</b>
                                <span>Uzman teknik ekip</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Teknoloji altyapısı -->
        <section class="fz-section">
            <div class="container">
                <div class="fz-head">
                    <span class="fz-badge"><i class="fas fa-microchip"></i> Kabinetteki Donanım</span>
                    <h2>Kullandığımız <span>Donanım</span></h2>
                    <p>Paketlerin altındaki fiziksel donanım; kiralanan makine birebir bu.</p>
                </div>

                <!-- Vitrin: Dell PowerEdge R730 -->
                <div class="fz-spotlight">
                    <div class="fz-spotlight-text">
                        <span class="fz-spotlight-tag"><i class="fas fa-server"></i> 2U Sunucu</span>
                        <h3>Dell PowerEdge R730</h3>
                        <p>
                            Çift soketli 2U gövde: iki adet Xeon E5-2600 v4 işlemci, 24 DDR4 yuvası ve
                            PERC donanımsal RAID denetleyici. Ön panelde 8 adet 3,5&quot; disk yuvası bulunur.
                        </p>
                        <ul class="fz-spotlight-points">
                            <li><i class="fas fa-check"></i> 2x Xeon E5-2600 v4</li>
                            <li><i class="fas fa-check"></i> 24 DDR4 yuvası</li>
                            <li><i class="fas fa-check"></i> PERC RAID denetleyici</li>
                            <li><i class="fas fa-check"></i> iDRAC 8 Enterprise</li>
                        </ul>
                    </div>

                    <figure class="fz-hw">
                        <?php $fzFoto = $fzGorsel('r730'); ?>
                        <?php if ($fzFoto !== null): ?>
                            <img src="<?= htmlspecialchars($fzFoto) ?>"
                                alt="Dell PowerEdge R730 sunucu" loading="lazy" decoding="async">
                        <?php else: ?>
                            <svg viewBox="0 0 600 160" role="img"
                                aria-label="Dell PowerEdge R730 2U sunucunun ön paneli: sekiz disk yuvası, kumanda paneli ve havalandırma ızgarası">
                                <defs>
                                    <linearGradient id="fzGovde" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0" stop-color="#3c414a" />
                                        <stop offset="0.06" stop-color="#2c3038" />
                                        <stop offset="0.92" stop-color="#1c1f25" />
                                        <stop offset="1" stop-color="#121419" />
                                    </linearGradient>
                                    <linearGradient id="fzTepe" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0" stop-color="#4a505a" />
                                        <stop offset="1" stop-color="#343941" />
                                    </linearGradient>
                                    <linearGradient id="fzKulak" x1="0" y1="0" x2="1" y2="0">
                                        <stop offset="0" stop-color="#33383f" />
                                        <stop offset="0.5" stop-color="#272b31" />
                                        <stop offset="1" stop-color="#1a1d22" />
                                    </linearGradient>
                                    <linearGradient id="fzKizak" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0" stop-color="#454b54" />
                                        <stop offset="0.5" stop-color="#31363d" />
                                        <stop offset="1" stop-color="#212429" />
                                    </linearGradient>
                                    <linearGradient id="fzMandal" x1="0" y1="0" x2="1" y2="0">
                                        <stop offset="0" stop-color="#e6e9ee" />
                                        <stop offset="0.45" stop-color="#aab0b9" />
                                        <stop offset="1" stop-color="#7d838c" />
                                    </linearGradient>
                                    <radialGradient id="fzYesil" cx="0.5" cy="0.4" r="0.6">
                                        <stop offset="0" stop-color="#9dffc0" />
                                        <stop offset="0.45" stop-color="#2fd96b" />
                                        <stop offset="1" stop-color="#12813c" />
                                    </radialGradient>
                                    <radialGradient id="fzSari" cx="0.5" cy="0.4" r="0.6">
                                        <stop offset="0" stop-color="#ffe3a3" />
                                        <stop offset="0.45" stop-color="#f6a723" />
                                        <stop offset="1" stop-color="#a96806" />
                                    </radialGradient>
                                    <pattern id="fzDelik" width="5" height="5" patternUnits="userSpaceOnUse">
                                        <rect width="5" height="5" fill="#171a1f" />
                                        <circle cx="2.5" cy="2.5" r="1.35" fill="#0a0c0f" />
                                        <circle cx="2.5" cy="1.9" r="0.55" fill="#40454d" opacity="0.55" />
                                    </pattern>
                                    <filter id="fzGolge" x="-20%" y="-40%" width="140%" height="220%">
                                        <feDropShadow dx="0" dy="10" stdDeviation="12" flood-color="#0b0e13"
                                            flood-opacity="0.34" />
                                    </filter>
                                </defs>

                                <g filter="url(#fzGolge)">
                                    <!-- Üst yüzey: hafif perspektif -->
                                    <path d="M40 12 L560 12 L574 26 L26 26 Z" fill="url(#fzTepe)" />

                                    <!-- Ön yüz -->
                                    <rect x="26" y="26" width="548" height="116" rx="3" fill="url(#fzGovde)" />
                                    <rect x="26.5" y="26.5" width="547" height="1.6" fill="#6b7280" opacity="0.5" />

                                    <!-- Rack kulakları -->
                                    <rect x="26" y="26" width="26" height="116" rx="3" fill="url(#fzKulak)" />
                                    <rect x="548" y="26" width="26" height="116" rx="3" fill="url(#fzKulak)" />
                                    <?php foreach ([44, 84, 124] as $fzY): ?>
                                        <circle cx="39" cy="<?= $fzY ?>" r="4.2" fill="#0c0e12" />
                                        <circle cx="39" cy="<?= $fzY - 0.8 ?>" r="4.2" fill="#5c626b" opacity="0.4" />
                                        <circle cx="39" cy="<?= $fzY ?>" r="2.6" fill="#07080a" />
                                        <circle cx="561" cy="<?= $fzY ?>" r="4.2" fill="#0c0e12" />
                                        <circle cx="561" cy="<?= $fzY - 0.8 ?>" r="4.2" fill="#5c626b" opacity="0.4" />
                                        <circle cx="561" cy="<?= $fzY ?>" r="2.6" fill="#07080a" />
                                    <?php endforeach; ?>

                                    <!-- 8 x 3,5&quot; disk kızağı: 2 sıra, 4 sütun -->
                                    <?php for ($fzR = 0; $fzR < 2; $fzR++): ?>
                                        <?php for ($fzC = 0; $fzC < 4; $fzC++):
                                            $bx = 58 + $fzC * 104;
                                            $by = 34 + $fzR * 50;
                                            ?>
                                            <g>
                                                <rect x="<?= $bx ?>" y="<?= $by ?>" width="100" height="46" rx="2.5"
                                                    fill="url(#fzKizak)" stroke="#0d0f13" stroke-width="1" />
                                                <rect x="<?= $bx + 1.5 ?>" y="<?= $by + 1.5 ?>" width="97" height="1.4"
                                                    fill="#7b828c" opacity="0.45" />
                                                <!-- Serbest bırakma mandalı -->
                                                <rect x="<?= $bx + 5 ?>" y="<?= $by + 6 ?>" width="11" height="34" rx="2.5"
                                                    fill="url(#fzMandal)" />
                                                <rect x="<?= $bx + 8 ?>" y="<?= $by + 12 ?>" width="2" height="22" rx="1"
                                                    fill="#5e646d" opacity="0.7" />
                                                <!-- Delikli havalandırma paneli -->
                                                <rect x="<?= $bx + 22 ?>" y="<?= $by + 8 ?>" width="62" height="30" rx="2"
                                                    fill="url(#fzDelik)" />
                                                <!-- Durum ışıkları -->
                                                <circle cx="<?= $bx + 92 ?>" cy="<?= $by + 12 ?>" r="3.4" fill="url(#fzYesil)" />
                                                <circle cx="<?= $bx + 92 ?>" cy="<?= $by + 34 ?>" r="3.4" fill="url(#fzSari)" />
                                            </g>
                                        <?php endfor; ?>
                                    <?php endfor; ?>

                                    <!-- Kumanda paneli -->
                                    <rect x="478" y="34" width="64" height="26" rx="3" fill="#0b0f14" stroke="#0a0c10" />
                                    <rect x="483" y="39" width="54" height="16" rx="2" fill="#123a63" />
                                    <rect x="487" y="43" width="30" height="2.4" rx="1.2" fill="#5aa9f5" opacity="0.85" />
                                    <rect x="487" y="48" width="42" height="2.4" rx="1.2" fill="#5aa9f5" opacity="0.55" />

                                    <circle cx="490" cy="76" r="9" fill="#2a2e35" stroke="#13161a" />
                                    <circle cx="490" cy="76" r="6.4" fill="none" stroke="#98e0b4" stroke-width="1.6"
                                        stroke-dasharray="26 6" transform="rotate(-90 490 76)" />
                                    <rect x="489" y="70" width="2" height="6" rx="1" fill="#98e0b4" />

                                    <rect x="508" y="69" width="16" height="7" rx="1.5" fill="#0d1014" />
                                    <rect x="509.5" y="71" width="13" height="3" rx="1" fill="#3d434b" />
                                    <rect x="508" y="80" width="16" height="7" rx="1.5" fill="#0d1014" />
                                    <rect x="509.5" y="82" width="13" height="3" rx="1" fill="#3d434b" />

                                    <rect x="478" y="94" width="64" height="38" rx="2" fill="url(#fzDelik)" />
                                </g>
                            </svg>
                        <?php endif; ?>
                        <figcaption>Dell PowerEdge R730 &mdash; 2U gövde, 8 x 3,5&quot; disk yuvası</figcaption>
                    </figure>
                </div>

                <!-- Vitrin: EMC VNX7600 -->
                <div class="fz-spotlight is-flip">
                    <div class="fz-spotlight-text">
                        <span class="fz-spotlight-tag"><i class="fas fa-hard-drive"></i> Depolama Ünitesi</span>
                        <h3>EMC VNX7600</h3>
                        <p>
                            Sunucunun kendi diski yetmediğinde devreye giren blok depolama ünitesi.
                            Çift storage processor, fibre channel bağlantı ve raf başına 15 disk yuvası.
                        </p>
                        <ul class="fz-spotlight-points">
                            <li><i class="fas fa-check"></i> Çift storage processor</li>
                            <li><i class="fas fa-check"></i> 8 Gbps fibre channel</li>
                            <li><i class="fas fa-check"></i> 15 x 3,5&quot; disk yuvası</li>
                            <li><i class="fas fa-check"></i> RAID 5 / 6 / 10</li>
                        </ul>
                    </div>

                    <figure class="fz-hw">
                        <?php $fzFoto = $fzGorsel('vnx7600'); ?>
                        <?php if ($fzFoto !== null): ?>
                            <img src="<?= htmlspecialchars($fzFoto) ?>"
                                alt="EMC VNX7600 disk rafı" loading="lazy" decoding="async">
                        <?php else: ?>
                            <svg viewBox="0 0 600 190" role="img"
                                aria-label="EMC VNX7600 disk rafının ön paneli: yan yana dizili on beş dikey disk kızağı">
                                <defs>
                                    <linearGradient id="fzVGovde" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0" stop-color="#39414d" />
                                        <stop offset="0.06" stop-color="#282f39" />
                                        <stop offset="0.92" stop-color="#191d25" />
                                        <stop offset="1" stop-color="#101319" />
                                    </linearGradient>
                                    <linearGradient id="fzVTepe" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0" stop-color="#48515e" />
                                        <stop offset="1" stop-color="#323a45" />
                                    </linearGradient>
                                    <linearGradient id="fzVKulak" x1="0" y1="0" x2="1" y2="0">
                                        <stop offset="0" stop-color="#33383f" />
                                        <stop offset="0.5" stop-color="#272b31" />
                                        <stop offset="1" stop-color="#1a1d22" />
                                    </linearGradient>
                                    <radialGradient id="fzVYesil" cx="0.5" cy="0.4" r="0.6">
                                        <stop offset="0" stop-color="#9dffc0" />
                                        <stop offset="0.45" stop-color="#2fd96b" />
                                        <stop offset="1" stop-color="#12813c" />
                                    </radialGradient>
                                    <radialGradient id="fzVSari" cx="0.5" cy="0.4" r="0.6">
                                        <stop offset="0" stop-color="#ffe3a3" />
                                        <stop offset="0.45" stop-color="#f6a723" />
                                        <stop offset="1" stop-color="#a96806" />
                                    </radialGradient>
                                    <linearGradient id="fzVKizak" x1="0" y1="0" x2="1" y2="0">
                                        <stop offset="0" stop-color="#454d59" />
                                        <stop offset="0.4" stop-color="#2e343d" />
                                        <stop offset="1" stop-color="#1d2128" />
                                    </linearGradient>
                                    <linearGradient id="fzVMandal" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0" stop-color="#e2e6ec" />
                                        <stop offset="0.5" stop-color="#a5abb5" />
                                        <stop offset="1" stop-color="#787e88" />
                                    </linearGradient>
                                    <filter id="fzVGolge" x="-20%" y="-40%" width="140%" height="220%">
                                        <feDropShadow dx="0" dy="10" stdDeviation="12" flood-color="#0b0e13" flood-opacity="0.34" />
                                    </filter>
                                    <pattern id="fzVDelik" width="4.5" height="4.5" patternUnits="userSpaceOnUse">
                                        <rect width="4.5" height="4.5" fill="#15181e" />
                                        <circle cx="2.25" cy="2.25" r="1.2" fill="#090b0e" />
                                        <circle cx="2.25" cy="1.75" r="0.5" fill="#3d434c" opacity="0.5" />
                                    </pattern>
                                </defs>

                                <g filter="url(#fzVGolge)">
                                    <!-- Üst yüzey -->
                                    <path d="M40 10 L560 10 L574 24 L26 24 Z" fill="url(#fzVTepe)" />

                                    <!-- Ön yüz -->
                                    <rect x="26" y="24" width="548" height="148" rx="3" fill="url(#fzVGovde)" />
                                    <rect x="26.5" y="24.5" width="547" height="1.6" fill="#6b7280" opacity="0.45" />

                                    <!-- Rack kulakları -->
                                    <rect x="26" y="24" width="26" height="148" rx="3" fill="url(#fzVKulak)" />
                                    <rect x="548" y="24" width="26" height="148" rx="3" fill="url(#fzVKulak)" />
                                    <?php foreach ([44, 98, 152] as $fzY): ?>
                                        <circle cx="39" cy="<?= $fzY ?>" r="4.2" fill="#0c0e12" />
                                        <circle cx="39" cy="<?= $fzY - 0.8 ?>" r="4.2" fill="#5c626b" opacity="0.4" />
                                        <circle cx="39" cy="<?= $fzY ?>" r="2.6" fill="#07080a" />
                                        <circle cx="561" cy="<?= $fzY ?>" r="4.2" fill="#0c0e12" />
                                        <circle cx="561" cy="<?= $fzY - 0.8 ?>" r="4.2" fill="#5c626b" opacity="0.4" />
                                        <circle cx="561" cy="<?= $fzY ?>" r="2.6" fill="#07080a" />
                                    <?php endforeach; ?>

                                    <!-- Uç kapakları: tutamak ve durum ışıkları -->
                                    <?php foreach ([['x' => 58, 'tut' => 66], ['x' => 494, 'tut' => 526]] as $fzK): ?>
                                        <rect x="<?= $fzK['x'] ?>" y="32" width="48" height="132" rx="3"
                                            fill="url(#fzVKizak)" stroke="#0d0f13" />
                                        <rect x="<?= $fzK['x'] + 1.5 ?>" y="33.5" width="45" height="1.4"
                                            fill="#7b828c" opacity="0.4" />
                                        <rect x="<?= $fzK['tut'] ?>" y="56" width="9" height="86" rx="4.5"
                                            fill="url(#fzVMandal)" />
                                        <circle cx="<?= $fzK['x'] + 24 ?>" cy="42" r="3.4" fill="url(#fzVYesil)" />
                                        <circle cx="<?= $fzK['x'] + 24 ?>" cy="52" r="3.4" fill="url(#fzVSari)" />
                                    <?php endforeach; ?>

                                    <!-- 15 dikey disk kızağı -->
                                    <?php for ($fzI = 0; $fzI < 15; $fzI++):
                                        $dx = 114 + $fzI * 25;
                                        ?>
                                        <g>
                                            <rect x="<?= $dx ?>" y="32" width="22" height="132" rx="2.5"
                                                fill="url(#fzVKizak)" stroke="#0d0f13" stroke-width="1" />
                                            <rect x="<?= $dx + 1.2 ?>" y="33.2" width="19.6" height="1.3"
                                                fill="#7b828c" opacity="0.4" />
                                            <rect x="<?= $dx + 3 ?>" y="38" width="16" height="8" rx="2"
                                                fill="url(#fzVMandal)" />
                                            <rect x="<?= $dx + 4 ?>" y="52" width="14" height="92" rx="2"
                                                fill="url(#fzVDelik)" />
                                            <circle cx="<?= $dx + 11 ?>" cy="154" r="3" fill="url(#fzVYesil)" />
                                        </g>
                                    <?php endfor; ?>
                                </g>
                            </svg>
                        <?php endif; ?>
                        <figcaption>EMC VNX7600 &mdash; 3U disk rafı, 15 x 3,5&quot; yuva</figcaption>
                    </figure>
                </div>

                <div class="fz-tech">
                    <div class="fz-tech-card" style="--tint: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                        <div class="fz-tech-icon"><i class="fas fa-sliders"></i></div>
                        <div>
                            <h4>iDRAC Uzaktan Yönetim</h4>
                            <p>Sunucuyu açıp kapatın, konsola bağlanın, ISO takın. Fiziksel erişime ihtiyaç duymazsınız.</p>
                        </div>
                    </div>
                    <div class="fz-tech-card" style="--tint: linear-gradient(135deg, #22c55e, #15803d);">
                        <div class="fz-tech-icon"><i class="fas fa-layer-group"></i></div>
                        <div>
                            <h4>Donanımsal RAID</h4>
                            <p>PERC denetleyici ile RAID 0, 1, 5 ve 10. Disk arızasında sistem çalışmaya devam eder.</p>
                        </div>
                    </div>
                    <div class="fz-tech-card" style="--tint: linear-gradient(135deg, #0ea5e9, #0284c7);">
                        <div class="fz-tech-icon"><i class="fas fa-screwdriver-wrench"></i></div>
                        <div>
                            <h4>Kurulum ve Teslim</h4>
                            <p>İşletim sistemi kurulumu, ağ ayarları ve RAID yapılandırması ücretsizdir.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sık sorulanlar -->
        <section class="fz-section is-alt">
            <div class="container">
                <div class="fz-faq-layout">
                    <div class="fz-faq-aside">
                        <div class="fz-head is-left">
                            <span class="fz-badge"><i class="fas fa-circle-question"></i> Sık Sorulanlar</span>
                            <h2>Aklınıza <span>Takılanlar</span></h2>
                            <p>Satın almadan önce en çok merak edilenleri derledik.</p>
                        </div>

                        <div class="fz-faq-visual">
                            <!-- Soru-cevap balonları; renkler tema değişkenlerinden gelir -->
                            <svg viewBox="0 0 320 272" fill="none" aria-hidden="true">
                                <defs>
                                    <linearGradient id="lxQmark" x1="0" y1="0" x2="1" y2="1">
                                        <stop offset="0" stop-color="var(--primary-light)" />
                                        <stop offset="1" stop-color="var(--primary-dark)" />
                                    </linearGradient>
                                    <radialGradient id="lxHalo" cx="0.5" cy="0.5" r="0.5">
                                        <stop offset="0" stop-color="var(--primary)" stop-opacity="0.20" />
                                        <stop offset="1" stop-color="var(--primary)" stop-opacity="0" />
                                    </radialGradient>
                                </defs>

                                <ellipse cx="160" cy="136" rx="152" ry="122" fill="url(#lxHalo)" />

                                <!-- Cevap balonu (arkada) -->
                                <path d="M276 242 v22 l-26 -22 z" fill="var(--surface-2)" stroke="var(--border-color)"
                                    stroke-width="2" stroke-linejoin="round" />
                                <rect x="104" y="150" width="200" height="92" rx="22" fill="var(--surface-2)"
                                    stroke="var(--border-color)" stroke-width="2" />
                                <rect x="132" y="172" width="140" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.55" />
                                <rect x="132" y="194" width="118" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.4" />
                                <rect x="132" y="216" width="78" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.28" />

                                <!-- Soru balonu (önde) -->
                                <path d="M44 126 v22 l26 -22 z" fill="color-mix(in srgb, var(--primary) 12%, transparent)" stroke="var(--primary)"
                                    stroke-width="2" stroke-linejoin="round" />
                                <rect x="16" y="26" width="196" height="100" rx="24" fill="color-mix(in srgb, var(--primary) 12%, transparent)"
                                    stroke="var(--primary)" stroke-width="2" />
                                <rect x="44" y="56" width="112" height="9" rx="4.5" fill="var(--primary-light)" opacity="0.85" />
                                <rect x="44" y="78" width="140" height="9" rx="4.5" fill="var(--primary-light)" opacity="0.6" />
                                <rect x="44" y="100" width="84" height="9" rx="4.5" fill="var(--primary-light)" opacity="0.4" />

                                <!-- Soru işareti rozeti -->
                                <circle cx="234" cy="44" r="28" fill="url(#lxQmark)" />
                                <text x="234" y="45" text-anchor="middle" dominant-baseline="central" fill="#fff"
                                    font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="34"
                                    font-weight="800">?</text>

                                <!-- Serpiştirilmiş noktalar -->
                                <circle cx="292" cy="104" r="5" fill="var(--primary)" opacity="0.5" />
                                <circle cx="306" cy="126" r="3" fill="var(--primary)" opacity="0.3" />
                                <circle cx="24" cy="186" r="4" fill="var(--primary)" opacity="0.35" />
                                <circle cx="44" cy="210" r="6" fill="var(--primary)" opacity="0.2" />
                            </svg>
                        </div>

                        <a href="contact.php" class="fz-faq-help">
                            <i class="fas fa-headset"></i>
                            <div>
                                <b>Sorunuz listede yok mu?</b>
                                <span>Destek ekibimize yazın, aynı gün dönelim.</span>
                            </div>
                        </a>
                    </div>

                <div class="fz-faq">
                    <details class="fz-faq-item">
                        <summary>
                            <span class="fz-faq-icon">
                                <!-- Kontrol paneli: bölmeli ekran -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="3.5" width="18" height="17" rx="2.5" />
                                    <path d="M3 9.5h18" />
                                    <path d="M9.5 20.5v-11" />
                                </svg>
                            </span>
                            <span class="fz-faq-q">Sunucuyu kimseyle paylaşıyor muyum?</span>
                            <span class="fz-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="fz-faq-body">
                            Hayır. Fiziksel makinenin tamamı size ayrılır; işlemci, bellek ve disk başka bir
                            müşteriyle paylaşılmaz. Sanallaştırma katmanı yoktur.
                        </div>
                    </details>

                    <details class="fz-faq-item">
                        <summary>
                            <span class="fz-faq-icon">
                                <!-- Yedekleme: geri sarma oku + saat ibresi -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3.2 12a8.8 8.8 0 1 0 2.9-6.5" />
                                    <path d="M3 4.2v4.6h4.6" />
                                    <path d="M12 8.2V12l2.9 1.7" />
                                </svg>
                            </span>
                            <span class="fz-faq-q">Sunucuya uzaktan nasıl erişiyorum?</span>
                            <span class="fz-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="fz-faq-body">
                            iDRAC arayüzü üzerinden sunucuyu açıp kapatabilir, konsola bağlanabilir ve ISO
                            takarak işletim sistemini kendiniz kurabilirsiniz.
                        </div>
                    </details>

                    <details class="fz-faq-item">
                        <summary>
                            <span class="fz-faq-icon">
                                <!-- SSL: asma kilit -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="4" y="10.2" width="16" height="10.3" rx="2.4" />
                                    <path d="M8 10.2V7.1a4 4 0 0 1 8 0v3.1" />
                                    <path d="M12 14.4v2.2" />
                                </svg>
                            </span>
                            <span class="fz-faq-q">Kurulum için ayrıca ödeme yapacak mıyım?</span>
                            <span class="fz-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="fz-faq-body">
                            Hayır. İşletim sistemi kurulumu, RAID yapılandırması ve ağ ayarları bütün
                            paketlerde ücretsizdir; tek seferlik kurulum bedeli alınmaz.
                        </div>
                    </details>

                    <details class="fz-faq-item">
                        <summary>
                            <span class="fz-faq-icon">
                                <!-- İşletim sistemi: disk -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="12" cy="12" r="8.6" />
                                    <circle cx="12" cy="12" r="2.6" />
                                    <path d="M17.4 6.6 13.9 10.1" />
                                </svg>
                            </span>
                            <span class="fz-faq-q">İşletim sistemini kendim seçebilir miyim?</span>
                            <span class="fz-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="fz-faq-body">
                            Evet. Linux dağıtımları, Windows Server, VMware ESXi ya da Proxmox kurabiliriz.
                            Dilerseniz iDRAC üzerinden kendi imajınızı da yükleyebilirsiniz.
                        </div>
                    </details>

                    <details class="fz-faq-item">
                        <summary>
                            <span class="fz-faq-icon">
                                <!-- İşletim sistemi: sunucu rafı -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="4" width="18" height="7" rx="2.2" />
                                    <rect x="3" y="13" width="18" height="7" rx="2.2" />
                                    <path d="M7 7.5h.01" />
                                    <path d="M7 16.5h.01" />
                                </svg>
                            </span>
                            <span class="fz-faq-q">Donanımı sonradan değiştirebilir miyim?</span>
                            <span class="fz-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="fz-faq-body">
                            Bellek ve disk eklemesi talep üzerine yapılabilir. VNX7600 depolama ünitesi ile
                            kapasiteyi sunucuyu değiştirmeden büyütebilirsiniz.
                        </div>
                    </details>

                    <details class="fz-faq-item">
                        <summary>
                            <span class="fz-faq-icon">
                                <!-- Taşıma: karşılıklı transfer okları -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4 9h15" />
                                    <path d="m15.5 5.5 3.5 3.5-3.5 3.5" />
                                    <path d="M20 15H5" />
                                    <path d="M8.5 11.5 5 15l3.5 3.5" />
                                </svg>
                            </span>
                            <span class="fz-faq-q">Teslim süresi ne kadar?</span>
                            <span class="fz-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="fz-faq-body">
                            Stokta hazır konfigürasyonlar aynı gün teslim edilir. Özel donanım taleplerinde
                            süre, tedarik durumuna göre destek ekibimiz tarafından bildirilir.
                        </div>
                    </details>
                    </div>
                </div>
            </div>
        </section>

        <!-- Kapanış -->
        <section class="fz-final">
            <div class="container">
                <h3><i class="fas fa-server"></i> Fiziksel Sunucu ile Başlayın</h3>
                <p>Size ayrılmış Dell PowerEdge donanımı, kendi veri merkezimizde</p>
                <div class="fz-final-actions">
                    <a href="#paketler" class="fz-final-btn">
                        <i class="fas fa-rocket"></i> Paketleri İncele
                    </a>
                    <a href="contact.php" class="fz-final-link">
                        <i class="fas fa-comments"></i> Önce Soru Sormak İsterim
                    </a>
                </div>
            </div>
        </section>
    </div>

    <script>
        // "+N özellik daha" düğmesi: listedeki gizli satırları açar
        document.querySelectorAll('.fz-more').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var list = document.getElementById(btn.dataset.target);
                if (!list) return;
                var expanded = list.classList.toggle('is-clipped') === false;
                btn.setAttribute('aria-expanded', String(expanded));
                btn.textContent = expanded
                    ? 'Daha az göster'
                    : '+ ' + (list.children.length - 6) + ' özellik daha';
            });
        });
    </script>

    <?php require_once __DIR__ . '/theme/includes/footer.php'; ?>

<?php elseif ($isGpuPage): ?>
    <!-- ===== EKRAN KARTLI SUNUCU ÖZEL TASARIM ===== -->
    <style>
        /* GPU Server Theme Colors */
        :root {
            --gpu-green: #10b981;
            --gpu-green-dark: #059669;
            --gpu-green-light: #34d399;
            --gpu-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --gpu-cyan: #06b6d4;
            --gpu-purple: #8b5cf6;
            --nvidia-green: #76b900;
        }

        /* GPU Hero */
        .gpu-hero {
            background: linear-gradient(135deg, #0a0a1a 0%, #0f1628 50%, #0a0f1a 100%);
            padding: 80px 0 60px;
            position: relative;
            overflow: hidden;
        }

        .gpu-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(ellipse at 20% 30%, rgba(16, 185, 129, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 70%, rgba(6, 182, 212, 0.10) 0%, transparent 40%);
        }

        /* Animated GPU Grid Pattern */
        .gpu-hero::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image:
                linear-gradient(rgba(16, 185, 129, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(16, 185, 129, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: gridMove 20s linear infinite;
        }

        @keyframes gridMove {
            0% {
                transform: translate(0, 0);
            }

            100% {
                transform: translate(50px, 50px);
            }
        }

        .gpu-hero .container {
            position: relative;
            z-index: 2;
        }

        .gpu-hero-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .gpu-hero-text h1 {
            font-size: 48px;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff 0%, #34d399 50%, #10b981 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .gpu-hero-text p {
            font-size: 18px;
            color: #94a3b8;
            line-height: 1.8;
            margin-bottom: 30px;
        }

        .gpu-badge-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .gpu-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0.1) 100%);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 30px;
            padding: 10px 18px;
            font-size: 13px;
            font-weight: 600;
            color: var(--gpu-green-light);
        }

        .gpu-badge i {
            font-size: 14px;
        }

        .gpu-badge.nvidia {
            background: linear-gradient(135deg, rgba(118, 185, 0, 0.15) 0%, rgba(118, 185, 0, 0.05) 100%);
            border-color: rgba(118, 185, 0, 0.3);
            color: var(--nvidia-green);
        }

        /* GPU Visual - Premium RTX Style */
        .gpu-hero-visual {
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        /* Glow Effect Behind */
        .gpu-hero-visual::before {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.3) 0%, transparent 70%);
            animation: glowPulse 4s ease-in-out infinite;
            z-index: 0;
        }

        @keyframes glowPulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 0.5;
            }

            50% {
                transform: scale(1.2);
                opacity: 0.8;
            }
        }

        .gpu-card-3d {
            width: 420px;
            height: 320px;
            perspective: 1500px;
            position: relative;
            z-index: 1;
        }

        .gpu-card-inner {
            width: 100%;
            height: 100%;
            position: relative;
            transform-style: preserve-3d;
            animation: gpuFloat 8s ease-in-out infinite;
        }

        @keyframes gpuFloat {

            0%,
            100% {
                transform: rotateY(-8deg) rotateX(5deg) translateY(0);
            }

            25% {
                transform: rotateY(0deg) rotateX(2deg) translateY(-10px);
            }

            50% {
                transform: rotateY(8deg) rotateX(-3deg) translateY(0);
            }

            75% {
                transform: rotateY(0deg) rotateX(0deg) translateY(-5px);
            }
        }

        .gpu-card-body {
            position: absolute;
            width: 100%;
            height: 100%;
            background: linear-gradient(160deg, #1a1a2e 0%, #12121f 40%, #0a0a14 100%);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 16px;
            box-shadow:
                0 30px 80px rgba(0, 0, 0, 0.5),
                0 0 100px rgba(16, 185, 129, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
            overflow: hidden;
        }

        /* Top RGB Strip */
        .gpu-card-body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 20px;
            right: 20px;
            height: 4px;
            background: linear-gradient(90deg,
                    #10b981, #06b6d4, #8b5cf6, #ec4899, #f59e0b, #10b981);
            background-size: 200% 100%;
            animation: rgbFlow 3s linear infinite;
            border-radius: 0 0 4px 4px;
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.5), 0 0 40px rgba(16, 185, 129, 0.3);
        }

        @keyframes rgbFlow {
            0% {
                background-position: 0% 0%;
            }

            100% {
                background-position: 200% 0%;
            }
        }

        /* Backplate Pattern */
        .gpu-card-body::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                repeating-linear-gradient(90deg,
                    transparent 0px,
                    transparent 8px,
                    rgba(16, 185, 129, 0.02) 8px,
                    rgba(16, 185, 129, 0.02) 9px);
            pointer-events: none;
        }

        /* GPU Fans Container */
        .gpu-fans {
            display: flex;
            justify-content: center;
            gap: 15px;
            padding: 25px 20px 20px;
            position: relative;
        }

        /* Individual Fan */
        .gpu-fan {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(145deg, #0f0f1a 0%, #1a1a2e 100%);
            border: 3px solid rgba(16, 185, 129, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            box-shadow:
                inset 0 0 30px rgba(0, 0, 0, 0.5),
                0 5px 15px rgba(0, 0, 0, 0.3);
        }

        /* Fan Blades */
        .gpu-fan::before {
            content: '';
            position: absolute;
            width: 85px;
            height: 85px;
            background:
                conic-gradient(from 0deg,
                    transparent 0deg, transparent 20deg,
                    rgba(16, 185, 129, 0.6) 20deg, rgba(16, 185, 129, 0.6) 40deg,
                    transparent 40deg, transparent 60deg,
                    rgba(16, 185, 129, 0.6) 60deg, rgba(16, 185, 129, 0.6) 80deg,
                    transparent 80deg, transparent 100deg,
                    rgba(16, 185, 129, 0.6) 100deg, rgba(16, 185, 129, 0.6) 120deg,
                    transparent 120deg, transparent 140deg,
                    rgba(16, 185, 129, 0.6) 140deg, rgba(16, 185, 129, 0.6) 160deg,
                    transparent 160deg, transparent 180deg,
                    rgba(16, 185, 129, 0.6) 180deg, rgba(16, 185, 129, 0.6) 200deg,
                    transparent 200deg, transparent 220deg,
                    rgba(16, 185, 129, 0.6) 220deg, rgba(16, 185, 129, 0.6) 240deg,
                    transparent 240deg, transparent 260deg,
                    rgba(16, 185, 129, 0.6) 260deg, rgba(16, 185, 129, 0.6) 280deg,
                    transparent 280deg, transparent 300deg,
                    rgba(16, 185, 129, 0.6) 300deg, rgba(16, 185, 129, 0.6) 320deg,
                    transparent 320deg, transparent 340deg,
                    rgba(16, 185, 129, 0.6) 340deg, rgba(16, 185, 129, 0.6) 360deg);
            border-radius: 50%;
            animation: fanSpin 0.8s linear infinite;
            filter: blur(0.5px);
        }

        /* Center Hub */
        .gpu-fan::after {
            content: '';
            position: absolute;
            width: 28px;
            height: 28px;
            background: linear-gradient(145deg, #2a2a3e, #1a1a2e);
            border: 2px solid rgba(16, 185, 129, 0.4);
            border-radius: 50%;
            box-shadow:
                0 0 15px var(--gpu-green),
                inset 0 0 10px rgba(16, 185, 129, 0.3);
        }

        /* Different fan speeds */
        .gpu-fan:nth-child(1)::before {
            animation-duration: 0.7s;
        }

        .gpu-fan:nth-child(2)::before {
            animation-duration: 0.8s;
            animation-direction: reverse;
        }

        .gpu-fan:nth-child(3)::before {
            animation-duration: 0.75s;
        }

        @keyframes fanSpin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        /* RGB Light Strip */
        .gpu-leds {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 4px;
            padding: 20px 40px;
            position: relative;
        }

        .gpu-leds::before {
            content: 'GeForce RTX';
            position: absolute;
            left: 30px;
            font-size: 11px;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.4);
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .gpu-led {
            width: 4px;
            height: 25px;
            background: linear-gradient(180deg, var(--gpu-green), var(--gpu-cyan));
            border-radius: 2px;
            box-shadow:
                0 0 10px var(--gpu-green),
                0 0 20px rgba(16, 185, 129, 0.5);
            animation: ledWave 2s ease-in-out infinite;
        }

        .gpu-led:nth-child(1) {
            animation-delay: 0s;
            height: 18px;
        }

        .gpu-led:nth-child(2) {
            animation-delay: 0.1s;
            height: 22px;
        }

        .gpu-led:nth-child(3) {
            animation-delay: 0.2s;
            height: 28px;
        }

        .gpu-led:nth-child(4) {
            animation-delay: 0.3s;
            height: 32px;
        }

        .gpu-led:nth-child(5) {
            animation-delay: 0.4s;
            height: 35px;
        }

        .gpu-led:nth-child(6) {
            animation-delay: 0.5s;
            height: 32px;
        }

        .gpu-led:nth-child(7) {
            animation-delay: 0.6s;
            height: 28px;
        }

        .gpu-led:nth-child(8) {
            animation-delay: 0.7s;
            height: 22px;
        }

        .gpu-led:nth-child(9) {
            animation-delay: 0.8s;
            height: 18px;
        }

        .gpu-led:nth-child(10) {
            animation-delay: 0.9s;
            height: 22px;
        }

        .gpu-led:nth-child(11) {
            animation-delay: 1s;
            height: 28px;
        }

        .gpu-led:nth-child(12) {
            animation-delay: 1.1s;
            height: 32px;
        }

        @keyframes ledWave {

            0%,
            100% {
                opacity: 0.4;
                transform: scaleY(0.7);
                filter: brightness(0.7);
            }

            50% {
                opacity: 1;
                transform: scaleY(1.2);
                filter: brightness(1.3);
            }
        }

        /* GPU Info Bar - Premium */
        .gpu-info-bar {
            background: linear-gradient(90deg,
                    rgba(16, 185, 129, 0.05) 0%,
                    rgba(6, 182, 212, 0.08) 50%,
                    rgba(16, 185, 129, 0.05) 100%);
            padding: 18px 25px;
            display: flex;
            justify-content: space-around;
            align-items: center;
            border-top: 1px solid rgba(16, 185, 129, 0.15);
            position: relative;
        }

        .gpu-info-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60%;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--gpu-green), transparent);
        }

        .gpu-info-item {
            text-align: center;
            position: relative;
            padding: 0 15px;
        }

        .gpu-info-item:not(:last-child)::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 1px;
            height: 30px;
            background: linear-gradient(180deg, transparent, rgba(16, 185, 129, 0.3), transparent);
        }

        .gpu-info-item span {
            display: block;
            font-size: 20px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--gpu-green) 0%, var(--gpu-cyan) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 30px rgba(16, 185, 129, 0.5);
        }

        .gpu-info-item small {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 2px;
            display: block;
        }

        /* Use Cases Section */
        .gpu-usecases {
            background: linear-gradient(180deg, #0a0f1a 0%, #0f1628 100%);
            padding: 80px 0;
            position: relative;
        }

        .gpu-section-title {
            text-align: center;
            margin-bottom: 60px;
        }

        .gpu-section-title h2 {
            font-size: 36px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 15px;
        }

        .gpu-section-title p {
            color: #94a3b8;
            font-size: 18px;
        }

        .gpu-usecases-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
        }

        .gpu-usecase-card {
            background: linear-gradient(145deg, rgba(30, 30, 50, 0.8) 0%, rgba(15, 15, 25, 0.9) 100%);
            border: 1px solid rgba(16, 185, 129, 0.15);
            border-radius: 20px;
            padding: 35px 25px;
            text-align: center;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .gpu-usecase-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gpu-gradient);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .gpu-usecase-card:hover {
            transform: translateY(-10px);
            border-color: var(--gpu-green);
            box-shadow: 0 25px 50px rgba(16, 185, 129, 0.2);
        }

        .gpu-usecase-card:hover::before {
            opacity: 1;
        }

        .gpu-usecase-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0.1) 100%);
            border: 2px solid rgba(16, 185, 129, 0.3);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            color: var(--gpu-green);
            transition: all 0.4s ease;
        }

        .gpu-usecase-card:hover .gpu-usecase-icon {
            background: var(--gpu-gradient);
            color: #fff;
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 15px 35px rgba(16, 185, 129, 0.4);
        }

        .gpu-usecase-card h3 {
            font-size: 18px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 10px;
        }

        .gpu-usecase-card p {
            font-size: 14px;
            color: #94a3b8;
            line-height: 1.6;
        }

        /* Products Section - Corporate Style */
        .gpu-products {
            background: linear-gradient(180deg, #0a0f1a 0%, #0f1628 100%);
            padding: 80px 0;
        }

        .gpu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            max-width: 1300px;
            margin: 0 auto;
        }

        .gpu-grid.cols-1 {
            grid-template-columns: minmax(320px, 420px);
            justify-content: center;
        }

        .gpu-grid.cols-2 {
            grid-template-columns: repeat(2, minmax(320px, 1fr));
            max-width: 850px;
        }

        .gpu-grid.cols-3 {
            grid-template-columns: repeat(3, minmax(300px, 1fr));
            max-width: 1100px;
        }

        .gpu-grid.cols-4 {
            grid-template-columns: repeat(4, minmax(280px, 1fr));
            max-width: 1300px;
        }

        /* Corporate Card */
        .gpu-card {
            background: linear-gradient(180deg, #151d2b 0%, #0f1520 100%);
            border: 1px solid rgba(16, 185, 129, 0.1);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }

        .gpu-card:hover {
            transform: translateY(-8px);
            border-color: rgba(16, 185, 129, 0.3);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        /* Popular Badge */
        .gpu-card-badge {
            background: var(--gpu-gradient);
            color: #fff;
            text-align: center;
            padding: 10px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        /* Card Inner */
        .gpu-card>.gpu-card-inner {
            padding: 30px;
            width: auto;
            height: auto;
            position: relative;
            transform-style: flat;
            animation: none;
            background: transparent;
        }

        /* Card Header */
        .gpu-card-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .gpu-card-gpu-icon {
            width: 70px;
            height: 70px;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 16px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .gpu-card:hover .gpu-card-gpu-icon {
            background: var(--gpu-gradient);
        }

        .gpu-card-gpu-icon i {
            font-size: 28px;
            color: var(--gpu-green);
            transition: color 0.3s ease;
        }

        .gpu-card:hover .gpu-card-gpu-icon i {
            color: #fff;
        }

        .gpu-card-name {
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 5px;
        }

        .gpu-card-desc {
            font-size: 13px;
            color: #64748b;
        }

        /* Price */
        .gpu-card-price {
            text-align: center;
            padding: 20px;
            margin: 0 -30px;
            background: rgba(16, 185, 129, 0.05);
            border-top: 1px solid rgba(16, 185, 129, 0.1);
            border-bottom: 1px solid rgba(16, 185, 129, 0.1);
        }

        .gpu-card-price .amount {
            font-size: 42px;
            font-weight: 800;
            color: var(--gpu-green);
        }

        .gpu-card-price .currency {
            font-size: 20px;
            color: var(--gpu-green);
            vertical-align: top;
        }

        .gpu-card-price .period {
            font-size: 14px;
            color: #64748b;
        }

        /* Card Body */
        .gpu-card>.gpu-card-inner>.gpu-card-body {
            padding: 25px 0 0 0;
            position: relative;
            width: auto;
            height: auto;
            background: transparent;
            border: none;
            border-radius: 0;
            box-shadow: none;
            overflow: visible;
        }

        /* Features List */
        .gpu-card-features {
            list-style: none;
            margin: 0 0 25px 0;
            padding: 0;
        }

        .gpu-card-features li {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            font-size: 14px;
            color: #cbd5e1;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .gpu-card-features li:last-child {
            border-bottom: none;
        }

        .gpu-card-features li::before {
            content: '✓';
            color: var(--gpu-green);
            font-weight: bold;
            font-size: 14px;
            width: 20px;
            height: 20px;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .gpu-card-features li i {
            display: none;
        }

        /* CTA Button */
        .gpu-card-btn {
            display: block;
            width: 100%;
            padding: 15px;
            background: var(--gpu-gradient);
            border: none;
            border-radius: 10px;
            color: #fff;
            text-align: center;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .gpu-card-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }

        .gpu-card-btn i {
            margin-right: 8px;
        }

        /* Empty State */
        .gpu-empty-state {
            grid-column: 1/-1;
            text-align: center;
            padding: 60px;
            background: rgba(16, 185, 129, 0.03);
            border-radius: 16px;
            border: 1px dashed rgba(16, 185, 129, 0.2);
        }

        .gpu-empty-state i {
            font-size: 50px;
            color: var(--gpu-green);
            opacity: 0.3;
            margin-bottom: 15px;
            display: block;
        }

        .gpu-empty-state p {
            color: #64748b;
            font-size: 16px;
        }

        /* GPU Specs Section */
        .gpu-specs {
            background: linear-gradient(180deg, #0a0f1a 0%, #0f1628 100%);
            padding: 80px 0;
        }

        .gpu-specs-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .gpu-spec-card {
            background: linear-gradient(145deg, rgba(30, 30, 50, 0.7) 0%, rgba(15, 15, 25, 0.8) 100%);
            border: 1px solid rgba(16, 185, 129, 0.15);
            border-radius: 20px;
            padding: 35px;
            transition: all 0.3s ease;
        }

        .gpu-spec-card:hover {
            border-color: var(--gpu-green);
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(16, 185, 129, 0.15);
        }

        .gpu-spec-card h3 {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 20px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 20px;
        }

        .gpu-spec-card h3 i {
            width: 45px;
            height: 45px;
            background: var(--gpu-gradient);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .gpu-spec-list {
            list-style: none;
            padding: 0;
        }

        .gpu-spec-list li {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(16, 185, 129, 0.1);
            font-size: 14px;
        }

        .gpu-spec-list li:last-child {
            border-bottom: none;
        }

        .gpu-spec-list li span:first-child {
            color: #94a3b8;
        }

        .gpu-spec-list li span:last-child {
            color: #fff;
            font-weight: 600;
        }

        /* CTA Section */
        .gpu-cta {
            background: linear-gradient(180deg, #0f1628 0%, #0a0a15 100%);
            padding: 80px 0;
            text-align: center;
        }

        .gpu-cta h2 {
            font-size: 36px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 15px;
        }

        .gpu-cta p {
            color: #94a3b8;
            font-size: 18px;
            margin-bottom: 35px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .gpu-cta-btn {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 18px 45px;
            background: var(--gpu-gradient);
            color: #fff;
            font-weight: 600;
            font-size: 18px;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .gpu-cta-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(16, 185, 129, 0.4);
        }

        /* GPU FAQ Section */
        .gpu-faq {
            background: linear-gradient(180deg, #0a0f1a 0%, #0c1220 50%, #0f1628 100%);
            padding: 100px 0;
            position: relative;
        }

        .gpu-faq::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(ellipse at 10% 20%, rgba(16, 185, 129, 0.08) 0%, transparent 40%),
                radial-gradient(ellipse at 90% 80%, rgba(6, 182, 212, 0.05) 0%, transparent 40%);
        }

        .gpu-faq .container {
            position: relative;
            z-index: 2;
        }

        .gpu-faq-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .gpu-faq-header-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0.1) 100%);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 30px;
            padding: 8px 20px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 600;
            color: var(--gpu-green-light);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .gpu-faq-header h2 {
            font-size: 42px;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff 0%, #34d399 50%, #10b981 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
        }

        .gpu-faq-header p {
            color: #94a3b8;
            font-size: 18px;
        }

        .gpu-faq-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .gpu-faq-card {
            background: linear-gradient(145deg, rgba(25, 25, 45, 0.9) 0%, rgba(15, 15, 30, 0.95) 100%);
            border: 1px solid rgba(16, 185, 129, 0.12);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }

        .gpu-faq-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--gpu-gradient);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .gpu-faq-card:hover {
            border-color: rgba(16, 185, 129, 0.3);
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3), 0 0 30px rgba(16, 185, 129, 0.1);
        }

        .gpu-faq-card.active {
            border-color: var(--gpu-green);
            box-shadow: 0 25px 50px rgba(16, 185, 129, 0.2);
        }

        .gpu-faq-card-header {
            display: flex;
            align-items: flex-start;
            gap: 18px;
            padding: 25px;
            transition: all 0.3s ease;
        }

        .gpu-faq-card-icon {
            width: 50px;
            height: 50px;
            min-width: 50px;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.1) 100%);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--gpu-green);
            transition: all 0.3s ease;
        }

        .gpu-faq-card.active .gpu-faq-card-icon,
        .gpu-faq-card:hover .gpu-faq-card-icon {
            background: var(--gpu-gradient);
            color: #fff;
            border-color: transparent;
        }

        .gpu-faq-card-content {
            flex: 1;
        }

        .gpu-faq-card-question {
            font-size: 16px;
            font-weight: 600;
            color: #e2e8f0;
            margin-bottom: 5px;
            line-height: 1.4;
        }

        .gpu-faq-card:hover .gpu-faq-card-question,
        .gpu-faq-card.active .gpu-faq-card-question {
            color: #fff;
        }

        .gpu-faq-card-hint {
            font-size: 13px;
            color: #64748b;
        }

        .gpu-faq-card-toggle {
            width: 36px;
            height: 36px;
            min-width: 36px;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gpu-green);
            font-size: 14px;
            transition: all 0.3s ease;
            align-self: center;
        }

        .gpu-faq-card.active .gpu-faq-card-toggle {
            background: var(--gpu-gradient);
            color: #fff;
            border-color: transparent;
            transform: rotate(45deg);
        }

        .gpu-faq-card-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .gpu-faq-card.active .gpu-faq-card-answer {
            max-height: 400px;
        }

        .gpu-faq-card-answer-inner {
            padding: 0 25px 25px 93px;
        }

        .gpu-faq-card-answer-inner p {
            color: #94a3b8;
            font-size: 14px;
            line-height: 1.9;
            padding: 20px;
            background: rgba(16, 185, 129, 0.05);
            border-radius: 12px;
            border-left: 3px solid var(--gpu-green);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .gpu-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                max-width: 100% !important;
            }

            .gpu-usecases-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .gpu-specs-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .gpu-hero-content {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .gpu-hero-text h1 {
                font-size: 36px;
            }

            .gpu-badge-row {
                justify-content: center;
            }

            .gpu-card-3d {
                width: 300px;
                height: 220px;
            }

            .gpu-fan {
                width: 70px;
                height: 70px;
            }

            .gpu-fan::before {
                width: 55px;
                height: 55px;
            }

            .gpu-faq-grid {
                grid-template-columns: 1fr;
            }

            .gpu-faq-header h2 {
                font-size: 32px;
            }
        }

        @media (max-width: 768px) {
            .gpu-hero {
                padding: 60px 0 40px;
            }

            .gpu-hero-text h1 {
                font-size: 28px;
            }

            .gpu-grid {
                grid-template-columns: 1fr !important;
            }

            .gpu-usecases-grid {
                grid-template-columns: 1fr;
            }

            .gpu-specs-grid {
                grid-template-columns: 1fr;
            }

            .gpu-hero-visual {
                display: none;
            }

            .gpu-faq {
                padding: 60px 0;
            }

            .gpu-faq-card-header {
                padding: 20px;
            }

            .gpu-faq-card-answer-inner {
                padding: 0 20px 20px 20px;
            }

            .gpu-card>.gpu-card-inner {
                padding: 25px;
            }

            .gpu-card-price {
                margin: 0 -25px;
            }
        }
    </style>

    <!-- Hero Section -->
    <section class="gpu-hero">
        <div class="container">
            <div class="gpu-hero-content">
                <div class="gpu-hero-text">
                    <div class="gpu-badge-row">
                        <span class="gpu-badge nvidia">
                            <i class="fas fa-microchip"></i> NVIDIA GPU
                        </span>
                        <span class="gpu-badge">
                            <i class="fas fa-bolt"></i> Yüksek Performans
                        </span>
                    </div>
                    <h1>Ekran Kartlı<br>Sunucu Hizmeti</h1>
                    <p>
                        NVIDIA ekran kartlı sunucularımız ile oyun sunucuları, canlı yayın,
                        video render, yapay zeka ve makine öğrenimi projeleriniz için
                        maksimum GPU performansı elde edin.
                    </p>
                </div>
                <div class="gpu-hero-visual">
                    <div class="gpu-card-3d">
                        <div class="gpu-card-inner">
                            <div class="gpu-card-body">
                                <div class="gpu-fans">
                                    <div class="gpu-fan"></div>
                                    <div class="gpu-fan"></div>
                                    <div class="gpu-fan"></div>
                                </div>
                                <div class="gpu-leds">
                                    <div class="gpu-led"></div>
                                    <div class="gpu-led"></div>
                                    <div class="gpu-led"></div>
                                    <div class="gpu-led"></div>
                                    <div class="gpu-led"></div>
                                    <div class="gpu-led"></div>
                                    <div class="gpu-led"></div>
                                    <div class="gpu-led"></div>
                                    <div class="gpu-led"></div>
                                    <div class="gpu-led"></div>
                                    <div class="gpu-led"></div>
                                    <div class="gpu-led"></div>
                                </div>
                                <div class="gpu-info-bar">
                                    <div class="gpu-info-item">
                                        <span>24GB</span>
                                        <small>VRAM</small>
                                    </div>
                                    <div class="gpu-info-item">
                                        <span>384-bit</span>
                                        <small>Bus</small>
                                    </div>
                                    <div class="gpu-info-item">
                                        <span>450W</span>
                                        <small>TDP</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Products Section - Moved to top -->
    <section class="gpu-products">
        <div class="container">
            <div class="gpu-section-title">
                <h2>GPU Sunucu Paketleri</h2>
                <p>İhtiyacınıza uygun ekran kartlı sunucu seçenekleri</p>
            </div>
            <?php
            $productCount = count($products);
            $gridClass = 'cols-' . min($productCount, 4);
            ?>
            <div class="gpu-grid <?= $gridClass ?>">
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $index => $product): ?>
                        <?php
                        $features = [];
                        if (!empty($product['description'])) {
                            $features = array_filter(array_map('trim', explode("\n", strip_tags($product['description']))));
                            $features = array_slice($features, 0, 5);
                        }
                        $isPopular = ($index === 1 && $productCount > 2);
                        ?>
                        <div class="gpu-card">
                            <?php if ($isPopular): ?>
                                <div class="gpu-card-badge">EN POPÜLER</div>
                            <?php endif; ?>
                            <div class="gpu-card-inner">
                                <div class="gpu-card-header">
                                    <div class="gpu-card-gpu-icon">
                                        <i class="fas fa-microchip"></i>
                                    </div>
                                    <h3 class="gpu-card-name"><?= htmlspecialchars($product['name']) ?></h3>
                                    <p class="gpu-card-desc">GPU SERVER</p>
                                </div>
                                <div class="gpu-card-price">
                                    <span class="amount"><?= number_format((float) $product['price_monthly'], 0) ?></span>
                                    <span class="currency">₺</span>
                                    <span class="period">/ Aylık</span>
                                </div>
                                <div class="gpu-card-body">
                                    <?php if (!empty($features)): ?>
                                        <ul class="gpu-card-features">
                                            <?php foreach ($features as $feature): ?>
                                                <li><?= htmlspecialchars($feature) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <a href="/client/order-configure.php?id=<?= $product['id'] ?>" class="gpu-card-btn">
                                        <i class="fas fa-bolt"></i> SİPARİŞ VER
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="gpu-empty-state">
                        <i class="fas fa-microchip"></i>
                        <p>Henüz bu kategoride ürün bulunmamaktadır.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Use Cases Section -->
    <section class="gpu-usecases">
        <div class="container">
            <div class="gpu-section-title">
                <h2>Kullanım Alanları</h2>
                <p>GPU gücünü nerede kullanabilirsiniz?</p>
            </div>
            <div class="gpu-usecases-grid">
                <div class="gpu-usecase-card">
                    <div class="gpu-usecase-icon">
                        <i class="fas fa-gamepad"></i>
                    </div>
                    <h3>Oyun Sunucuları</h3>
                    <p>CS2, Minecraft, FiveM ve daha fazlası için yüksek FPS ve düşük gecikme</p>
                </div>
                <div class="gpu-usecase-card">
                    <div class="gpu-usecase-icon">
                        <i class="fas fa-video"></i>
                    </div>
                    <h3>Canlı Yayın</h3>
                    <p>Twitch, YouTube ve platformlar için GPU ile hızlı encoding</p>
                </div>
                <div class="gpu-usecase-card">
                    <div class="gpu-usecase-icon">
                        <i class="fas fa-film"></i>
                    </div>
                    <h3>Video Render</h3>
                    <p>After Effects, Premiere, DaVinci Resolve ile hızlı render</p>
                </div>
                <div class="gpu-usecase-card">
                    <div class="gpu-usecase-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <h3>Yapay Zeka / ML</h3>
                    <p>TensorFlow, PyTorch ile model eğitimi ve inference</p>
                </div>
                <div class="gpu-usecase-card">
                    <div class="gpu-usecase-icon">
                        <i class="fas fa-cube"></i>
                    </div>
                    <h3>3D Modelleme</h3>
                    <p>Blender, Maya, 3ds Max ile GPU render</p>
                </div>
                <div class="gpu-usecase-card">
                    <div class="gpu-usecase-icon">
                        <i class="fas fa-vr-cardboard"></i>
                    </div>
                    <h3>VR / Metaverse</h3>
                    <p>Sanal gerçeklik ve metaverse uygulamaları</p>
                </div>
                <div class="gpu-usecase-card">
                    <div class="gpu-usecase-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h3>Veri Analizi</h3>
                    <p>Büyük veri işleme ve GPU hızlandırmalı analiz</p>
                </div>
                <div class="gpu-usecase-card">
                    <div class="gpu-usecase-icon">
                        <i class="fas fa-cloud"></i>
                    </div>
                    <h3>Cloud Gaming</h3>
                    <p>Uzaktan oyun oynama ve streaming servisleri</p>
                </div>
            </div>
        </div>
    </section>

    <!-- GPU Specs Section -->
    <section class="gpu-specs">
        <div class="container">
            <div class="gpu-section-title">
                <h2>Teknik Özellikler</h2>
                <p>Sunucularımızda kullanılan GPU ve donanım özellikleri</p>
            </div>
            <div class="gpu-specs-grid">
                <div class="gpu-spec-card">
                    <h3><i class="fas fa-microchip"></i> GPU Modelleri</h3>
                    <ul class="gpu-spec-list">
                        <li><span>NVIDIA RTX 4090</span><span>24GB GDDR6X</span></li>
                        <li><span>NVIDIA RTX 4080</span><span>16GB GDDR6X</span></li>
                        <li><span>NVIDIA RTX A6000</span><span>48GB GDDR6</span></li>
                        <li><span>NVIDIA Tesla T4</span><span>16GB GDDR6</span></li>
                    </ul>
                </div>
                <div class="gpu-spec-card">
                    <h3><i class="fas fa-server"></i> Sunucu Donanımı</h3>
                    <ul class="gpu-spec-list">
                        <li><span>İşlemci</span><span>Intel Xeon / AMD EPYC</span></li>
                        <li><span>RAM</span><span>64GB - 512GB DDR4</span></li>
                        <li><span>Depolama</span><span>NVMe SSD</span></li>
                        <li><span>Ağ</span><span>10 Gbps</span></li>
                    </ul>
                </div>
                <div class="gpu-spec-card">
                    <h3><i class="fas fa-code"></i> Yazılım Desteği</h3>
                    <ul class="gpu-spec-list">
                        <li><span>CUDA</span><span>12.x</span></li>
                        <li><span>cuDNN</span><span>8.x</span></li>
                        <li><span>TensorRT</span><span>8.x</span></li>
                        <li><span>Driver</span><span>En Güncel</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="gpu-faq">
        <div class="container">
            <div class="gpu-faq-header">
                <div class="gpu-faq-header-badge">
                    <i class="fas fa-question-circle"></i>
                    Sıkça Sorulan Sorular
                </div>
                <h2>Merak Ettikleriniz</h2>
                <p>GPU sunucu hizmetimiz hakkında en çok sorulan sorular</p>
            </div>

            <div class="gpu-faq-grid">
                <div class="gpu-faq-card" onclick="toggleGpuFaq(this)">
                    <div class="gpu-faq-card-header">
                        <div class="gpu-faq-card-icon">
                            <i class="fas fa-microchip"></i>
                        </div>
                        <div class="gpu-faq-card-content">
                            <h4 class="gpu-faq-card-question">Hangi ekran kartı modellerini sunuyorsunuz?</h4>
                            <span class="gpu-faq-card-hint"><i class="fas fa-mouse-pointer"></i> Detay için tıklayın</span>
                        </div>
                        <div class="gpu-faq-card-toggle">
                            <i class="fas fa-plus"></i>
                        </div>
                    </div>
                    <div class="gpu-faq-card-answer">
                        <div class="gpu-faq-card-answer-inner">
                            <p>NVIDIA RTX 4090, RTX 4080, RTX A6000, Tesla T4 ve V100 gibi profesyonel ve oyuncu
                                segmentlerinden çeşitli GPU modelleri sunuyoruz. İhtiyacınıza göre VRAM kapasitesi, CUDA
                                çekirdek sayısı ve hesaplama gücü açısından size en uygun modeli seçebilirsiniz.</p>
                        </div>
                    </div>
                </div>

                <div class="gpu-faq-card" onclick="toggleGpuFaq(this)">
                    <div class="gpu-faq-card-header">
                        <div class="gpu-faq-card-icon">
                            <i class="fas fa-gamepad"></i>
                        </div>
                        <div class="gpu-faq-card-content">
                            <h4 class="gpu-faq-card-question">Oyun sunucusu için GPU şart mı?</h4>
                            <span class="gpu-faq-card-hint"><i class="fas fa-mouse-pointer"></i> Detay için tıklayın</span>
                        </div>
                        <div class="gpu-faq-card-toggle">
                            <i class="fas fa-plus"></i>
                        </div>
                    </div>
                    <div class="gpu-faq-card-answer">
                        <div class="gpu-faq-card-answer-inner">
                            <p>Çoğu oyun sunucusu CPU tabanlı çalışır, ancak FiveM, Minecraft shader'lı sunucular, cloud
                                gaming ve bazı özel modlar GPU gerektirir. Canlı yayın yapacaksanız GPU ile donanımsal
                                encoding çok daha verimli sonuçlar verir.</p>
                        </div>
                    </div>
                </div>

                <div class="gpu-faq-card" onclick="toggleGpuFaq(this)">
                    <div class="gpu-faq-card-header">
                        <div class="gpu-faq-card-icon">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div class="gpu-faq-card-content">
                            <h4 class="gpu-faq-card-question">Yapay zeka projeleri için uygun mu?</h4>
                            <span class="gpu-faq-card-hint"><i class="fas fa-mouse-pointer"></i> Detay için tıklayın</span>
                        </div>
                        <div class="gpu-faq-card-toggle">
                            <i class="fas fa-plus"></i>
                        </div>
                    </div>
                    <div class="gpu-faq-card-answer">
                        <div class="gpu-faq-card-answer-inner">
                            <p>Kesinlikle! Sunucularımız CUDA, cuDNN ve TensorRT önceden yüklenmiş olarak gelir. TensorFlow,
                                PyTorch, Keras gibi popüler ML framework'leri sorunsuz çalışır. Model eğitimi, inference ve
                                fine-tuning için idealdir.</p>
                        </div>
                    </div>
                </div>

                <div class="gpu-faq-card" onclick="toggleGpuFaq(this)">
                    <div class="gpu-faq-card-header">
                        <div class="gpu-faq-card-icon">
                            <i class="fas fa-video"></i>
                        </div>
                        <div class="gpu-faq-card-content">
                            <h4 class="gpu-faq-card-question">Canlı yayın için hangi paketi önerirsiniz?</h4>
                            <span class="gpu-faq-card-hint"><i class="fas fa-mouse-pointer"></i> Detay için tıklayın</span>
                        </div>
                        <div class="gpu-faq-card-toggle">
                            <i class="fas fa-plus"></i>
                        </div>
                    </div>
                    <div class="gpu-faq-card-answer">
                        <div class="gpu-faq-card-answer-inner">
                            <p>1080p yayın için RTX 4080 yeterlidir. 4K yayın veya çoklu platform streaming için RTX 4090
                                öneriyoruz. NVENC encoder sayesinde CPU'ya yük bindirmeden yüksek kaliteli encoding
                                yapabilirsiniz.</p>
                        </div>
                    </div>
                </div>

                <div class="gpu-faq-card" onclick="toggleGpuFaq(this)">
                    <div class="gpu-faq-card-header">
                        <div class="gpu-faq-card-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="gpu-faq-card-content">
                            <h4 class="gpu-faq-card-question">Kurulum ne kadar sürer?</h4>
                            <span class="gpu-faq-card-hint"><i class="fas fa-mouse-pointer"></i> Detay için tıklayın</span>
                        </div>
                        <div class="gpu-faq-card-toggle">
                            <i class="fas fa-plus"></i>
                        </div>
                    </div>
                    <div class="gpu-faq-card-answer">
                        <div class="gpu-faq-card-answer-inner">
                            <p>Stokta bulunan GPU'lar için sunucunuz 2-6 saat içinde hazır olur. NVIDIA driver'ları ve temel
                                ML kütüphaneleri önceden kurulu olarak teslim edilir. Özel konfigürasyonlar 24-48 saat
                                sürebilir.</p>
                        </div>
                    </div>
                </div>

                <div class="gpu-faq-card" onclick="toggleGpuFaq(this)">
                    <div class="gpu-faq-card-header">
                        <div class="gpu-faq-card-icon">
                            <i class="fas fa-plug"></i>
                        </div>
                        <div class="gpu-faq-card-content">
                            <h4 class="gpu-faq-card-question">Birden fazla GPU kullanabilir miyim?</h4>
                            <span class="gpu-faq-card-hint"><i class="fas fa-mouse-pointer"></i> Detay için tıklayın</span>
                        </div>
                        <div class="gpu-faq-card-toggle">
                            <i class="fas fa-plus"></i>
                        </div>
                    </div>
                    <div class="gpu-faq-card-answer">
                        <div class="gpu-faq-card-answer-inner">
                            <p>Evet, multi-GPU konfigürasyonları destekliyoruz. 2x, 4x ve 8x GPU seçenekleri mevcuttur.
                                NVLink destekli sunucularla GPU'lar arası yüksek hızlı iletişim sağlanır. Büyük model
                                eğitimi için idealdir.</p>
                        </div>
                    </div>
                </div>

                <div class="gpu-faq-card" onclick="toggleGpuFaq(this)">
                    <div class="gpu-faq-card-header">
                        <div class="gpu-faq-card-icon">
                            <i class="fab fa-linux"></i>
                        </div>
                        <div class="gpu-faq-card-content">
                            <h4 class="gpu-faq-card-question">Hangi işletim sistemleri destekleniyor?</h4>
                            <span class="gpu-faq-card-hint"><i class="fas fa-mouse-pointer"></i> Detay için tıklayın</span>
                        </div>
                        <div class="gpu-faq-card-toggle">
                            <i class="fas fa-plus"></i>
                        </div>
                    </div>
                    <div class="gpu-faq-card-answer">
                        <div class="gpu-faq-card-answer-inner">
                            <p>Ubuntu 20.04/22.04, Debian 11/12, CentOS, AlmaLinux ve Windows Server 2019/2022
                                desteklenmektedir. ML projeleri için Ubuntu öneriyoruz. NVIDIA driver'ları tüm sistemlerde
                                sorunsuz çalışır.</p>
                        </div>
                    </div>
                </div>

                <div class="gpu-faq-card" onclick="toggleGpuFaq(this)">
                    <div class="gpu-faq-card-header">
                        <div class="gpu-faq-card-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <div class="gpu-faq-card-content">
                            <h4 class="gpu-faq-card-question">Teknik destek sağlıyor musunuz?</h4>
                            <span class="gpu-faq-card-hint"><i class="fas fa-mouse-pointer"></i> Detay için tıklayın</span>
                        </div>
                        <div class="gpu-faq-card-toggle">
                            <i class="fas fa-plus"></i>
                        </div>
                    </div>
                    <div class="gpu-faq-card-answer">
                        <div class="gpu-faq-card-answer-inner">
                            <p>7/24 teknik destek sunuyoruz. NVIDIA driver sorunları, CUDA kurulumu, performans
                                optimizasyonu konularında yardımcı oluyoruz. Ayrıca ML framework kurulumu için dokümantasyon
                                ve rehberler sağlıyoruz.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="gpu-cta">
        <div class="container">
            <h2>GPU Gücüyle Projelerinizi Hızlandırın</h2>
            <p>
                Yüksek performanslı NVIDIA ekran kartlı sunucularımız ile
                işlerinizi bir üst seviyeye taşıyın.
            </p>
            <a href="#" class="gpu-cta-btn" onclick="window.scrollTo({top: 800, behavior: 'smooth'}); return false;">
                <i class="fas fa-rocket"></i> Hemen Başlayın
            </a>
        </div>
    </section>

    <script>
        function toggleGpuFaq(card) {
            const isActive = card.classList.contains('active');

            document.querySelectorAll('.gpu-faq-card').forEach(faq => {
                faq.classList.remove('active');
            });

            if (!isActive) {
                card.classList.add('active');
            }
        }
    </script>

    <?php require_once __DIR__ . '/theme/includes/footer.php'; ?>

<?php elseif ($isLinuxPage): ?>

    <?php
    /**
     * Linux Hosting sayfası - ürün açıklamasından teknik özellikleri ayıkla.
     * "1000 MB Nvme Disk Alanı" gibi satırları vitrin kutucuğuna,
     * kalanları özellik listesine yollar.
     */
    $lxSpecTypes = [
        'disk' => ['label' => 'Disk Alanı', 'icon' => 'fa-hard-drive', 'match' => '/disk/iu'],
        'traffic' => ['label' => 'Aylık Trafik', 'icon' => 'fa-right-left', 'match' => '/trafik|bant\s?geniş/iu'],
        'ram' => ['label' => 'RAM', 'icon' => 'fa-memory', 'match' => '/\bram\b/iu'],
        'cpu' => ['label' => 'İşlemci', 'icon' => 'fa-microchip', 'match' => '/\bcpu\b|çekirdek|\bcore\b/iu'],
        'email' => ['label' => 'E-posta', 'icon' => 'fa-envelope', 'match' => '/e-?posta|\bmail\b/iu'],
        'db' => ['label' => 'Veritabanı', 'icon' => 'fa-database', 'match' => '/veri\s?taban|mysql|mariadb/iu'],
    ];

    /** Satırdan sayısal değeri + birimini çek: "1024 MB", "1 Core", "Limitsiz" */
    $lxSpecValue = static function (string $line): string {
        if (preg_match('/\b(limitsiz|sınırsız|unlimited)\b/iu', $line)) {
            return 'Limitsiz';
        }
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(TB|GB|MB|KB|Core|Adet|vCPU)?/iu', $line, $m)) {
            return trim($m[1] . ' ' . ($m[2] ?? ''));
        }
        return '';
    };
    ?>

    <style>
        /* ==========================================
           Linux Hosting - sayfaya özel değişkenler
           Turuncu Linux kimliği korunur, yüzeyler
           global tema token'larından gelir.
           ========================================== */
        .lx {
            --lx-accent: var(--primary);
            --lx-accent-light: var(--primary);
            --lx-accent-dark: var(--primary-dark);
            --lx-accent-soft: color-mix(in srgb, var(--primary) 12%, transparent);
            --lx-accent-line: color-mix(in srgb, var(--primary) 28%, transparent);
            --lx-gradient: var(--gradient-primary);
        }

        /* ===== Hero ===== */
        .lx-hero {
            position: relative;
            overflow: hidden;
            background: var(--gradient-hero);
            padding: var(--space-6) 0 0;
        }

        .lx-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 55% 50% at 12% 30%, color-mix(in srgb, var(--primary) 22%, transparent) 0%, transparent 62%),
                radial-gradient(ellipse 45% 45% at 88% 10%, color-mix(in srgb, var(--secondary) 16%, transparent) 0%, transparent 58%);
            pointer-events: none;
        }

        .lx-hero>.container {
            position: relative;
            z-index: 1;
        }

        /* Tek kolon, ortalanmis: hero'da ayrica gorsel yok */
        .lx-hero-grid {
            max-width: 780px;
            margin: 0 auto;
            padding-bottom: var(--space-6);
            text-align: center;
        }

        .lx-hero-text .lx-lead {
            margin-left: auto;
            margin-right: auto;
        }

        .lx-hero-text .lx-stack {
            justify-content: center;
        }

        .lx-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 50px;
            background: var(--lx-accent-soft);
            border: 1px solid var(--lx-accent-line);
            color: var(--lx-accent-light);
            font-size: var(--text-sm);
            font-weight: 600;
            margin-bottom: var(--space-4);
        }

        .lx-title {
            /* Ürün sayfası; anasayfa kadar iri olmasına gerek yok */
            font-size: clamp(32px, 3.2vw + 16px, 50px);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -0.035em;
            margin-bottom: var(--space-3);
            color: var(--text-primary);
            text-wrap: balance;
        }

        .lx-title span {
            background: var(--lx-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .lx-lead {
            font-size: var(--text-md);
            color: var(--text-muted);
            line-height: 1.65;
            max-width: 48ch;
            margin-bottom: var(--space-5);
            text-wrap: pretty;
        }

        /* Teknoloji rozetleri - 2x2 kutu yerine tek sıra çip;
           hero yüksekliğini ~110px kısaltır, detaylar aşağıdaki
           teknoloji bölümünde zaten anlatılıyor. */
        .lx-stack {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .lx-chip {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 7px 14px 7px 7px;
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .lx-chip:hover {
            background: var(--surface-2);
            border-color: var(--lx-accent-line);
        }

        .lx-chip i {
            width: 26px;
            height: 26px;
            flex-shrink: 0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #fff;
        }

        .lx-chip i.is-cpanel {
            background: linear-gradient(135deg, #ff6c2c, #e8590c);
        }

        .lx-chip i.is-litespeed {
            background: linear-gradient(135deg, #22c55e, #15803d);
        }

        .lx-chip i.is-jetbackup {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .lx-chip i.is-alma {
            background: linear-gradient(135deg, #38bdf8, #0369a1);
        }

        /* ===== Terminal ===== */
        /* ===== Kategori sekmeleri ===== */
        .lx-tabs-wrap {
            position: relative;
            border-top: 1px solid var(--border-color);
            background: color-mix(in srgb, var(--bg-primary) 65%, transparent);
            backdrop-filter: blur(12px);
        }

        .lx-tabs {
            display: flex;
            gap: var(--space-2);
            overflow-x: auto;
            padding: var(--space-3) var(--space-4);
            scrollbar-width: none;
            /* Dokunmatik ekranda sekmeler hizaya otursun */
            scroll-snap-type: x proximity;
        }

        .lx-tabs::-webkit-scrollbar {
            display: none;
        }

        .lx-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            scroll-snap-align: start;
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            background: var(--surface-1);
            color: var(--text-muted);
            font-size: var(--text-sm);
            font-weight: 600;
            white-space: nowrap;
            transition: all var(--transition-normal) var(--ease-out);
        }

        .lx-tab:hover {
            color: var(--text-primary);
            border-color: var(--lx-accent-line);
            background: var(--surface-2);
        }

        .lx-tab.is-active {
            background: var(--lx-gradient);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 6px 18px color-mix(in srgb, var(--primary) 35%, transparent);
        }

        /* ===== Bölüm başlıkları ===== */
        .lx-section {
            padding: var(--section-padding) 0;
        }

        /* Paketler bölümü hemen sekme şeridinin altında;
           sekmeler zaten ayırıcı, üstte tam boşluğa gerek yok */
        .lx-hero+.lx-section {
            padding-top: var(--space-7);
        }

        .lx-section.is-alt {
            background: var(--bg-primary);
        }

        .lx-head {
            text-align: center;
            max-width: 680px;
            margin: 0 auto var(--space-7);
        }

        .lx-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 50px;
            background: var(--lx-accent-soft);
            border: 1px solid var(--lx-accent-line);
            color: var(--lx-accent-light);
            font-size: var(--text-sm);
            font-weight: 600;
            margin-bottom: var(--space-4);
        }

        .lx-head h2 {
            font-size: var(--text-3xl);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.15;
            color: var(--text-primary);
            text-wrap: balance;
        }

        .lx-head h2 span {
            background: var(--lx-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .lx-head p {
            margin-top: var(--space-3);
            font-size: var(--text-md);
            color: var(--text-muted);
            text-wrap: pretty;
        }

        /* ===== Paket kartları ===== */
        .lx-plans {
            display: grid;
            /* Sutun sayisi paket adedinden gelir; auto-fit reflow yapmaz */
            grid-template-columns: repeat(var(--plan-cols, 4), minmax(0, 1fr));
            gap: var(--space-5);
            /* Kartlar eşit yükseklikte olsun, sipariş butonları hizalansın */
            align-items: stretch;
        }

        .lx-plans[data-count="1"] {
            --plan-cols: 1;
            max-width: 420px;
            margin-inline: auto;
        }

        .lx-plans[data-count="2"] {
            --plan-cols: 2;
        }

        .lx-plans[data-count="3"] {
            --plan-cols: 3;
        }

        @media (max-width: 1180px) {
            .lx-plans[data-count] {
                --plan-cols: 2;
            }
        }

        @media (max-width: 680px) {
            .lx-plans[data-count] {
                --plan-cols: 1;
            }
        }

        /* Az paket varsa ortala, kartlar aşırı genişlemesin */
        .lx-plans[data-count="1"] {
            grid-template-columns: minmax(300px, 420px);
            justify-content: center;
        }

        .lx-plans[data-count="2"] {
            grid-template-columns: repeat(2, minmax(300px, 430px));
            justify-content: center;
        }

        .lx-plans[data-count="3"] {
            grid-template-columns: repeat(3, minmax(280px, 400px));
            justify-content: center;
        }

        .lx-plan {
            position: relative;
            display: flex;
            flex-direction: column;
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: transform var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .lx-plan:hover {
            transform: translateY(-6px);
            border-color: var(--lx-accent-line);
            box-shadow: var(--shadow-lg);
        }

        .lx-plan.is-popular {
            border-color: var(--lx-accent);
            box-shadow: 0 0 0 1px var(--lx-accent), 0 24px 48px -18px color-mix(in srgb, var(--primary) 45%, transparent);
        }

        .lx-ribbon {
            position: absolute;
            top: 0;
            right: var(--space-5);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 0 0 10px 10px;
            background: var(--lx-gradient);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .lx-plan-top {
            padding: var(--space-6) var(--space-5) var(--space-5);
            border-bottom: 1px solid var(--border-color);
        }

        .lx-plan-name {
            font-size: var(--text-xl);
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .lx-plan-group {
            font-size: var(--text-sm);
            color: var(--text-muted);
            margin-bottom: var(--space-5);
        }

        .lx-price {
            display: flex;
            align-items: baseline;
            gap: 4px;
            /* Rakamlar kartlar arasında hizalı dursun */
            font-variant-numeric: tabular-nums;
        }

        .lx-price .cur {
            font-size: var(--text-lg);
            font-weight: 700;
            color: var(--lx-accent);
        }

        .lx-price .val {
            font-size: clamp(34px, 3vw + 22px, 46px);
            font-weight: 800;
            letter-spacing: -0.04em;
            line-height: 1;
            color: var(--text-primary);
        }

        .lx-price .per {
            font-size: var(--text-base);
            color: var(--text-muted);
            font-weight: 500;
        }

        .lx-price-ask {
            font-size: var(--text-xl);
            font-weight: 700;
            color: var(--text-primary);
        }

        .lx-price-note {
            margin-top: var(--space-2);
            font-size: var(--text-sm);
            color: var(--text-muted);
        }

        .lx-save {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: var(--space-3);
            padding: 5px 12px;
            border-radius: 50px;
            background: color-mix(in srgb, #22c55e 15%, transparent);
            border: 1px solid color-mix(in srgb, #22c55e 32%, transparent);
            color: #4ade80;
            font-size: var(--text-xs);
            font-weight: 700;
        }

        /* Yıllık indirimi olmayan pakette yeri boş dursun ki
           kartlar arasında teknik kutular aynı hizada başlasın */
        .lx-save.is-placeholder {
            visibility: hidden;
        }

        /* Teknik özet kutucukları */
        .lx-specs {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1px;
            background: var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .lx-spec {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-4);
            background: var(--bg-primary);
        }

        .lx-spec i {
            width: 30px;
            height: 30px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: var(--lx-accent-soft);
            color: var(--lx-accent-light);
            font-size: 13px;
        }

        .lx-spec b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.25;
        }

        .lx-spec span {
            font-size: 11px;
            color: var(--text-muted);
        }

        .lx-plan-body {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: var(--space-5);
        }

        .lx-features {
            list-style: none;
            margin: 0 0 var(--space-5);
            padding: 0;
            display: grid;
            gap: 2px;
        }

        .lx-features li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 7px 0;
            font-size: var(--text-sm);
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .lx-features li i {
            margin-top: 3px;
            font-size: 11px;
            color: #22c55e;
            flex-shrink: 0;
        }

        /* Uzun listelerde kart şişmesin: fazlası gizli, düğmeyle açılır */
        .lx-features.is-clipped li:nth-child(n+7) {
            display: none;
        }

        .lx-more {
            align-self: flex-start;
            margin: calc(var(--space-5) * -1 + 4px) 0 var(--space-5);
            padding: 6px 0;
            background: none;
            border: none;
            color: var(--lx-accent-light);
            font-family: inherit;
            font-size: var(--text-sm);
            font-weight: 600;
            cursor: pointer;
        }

        .lx-more:hover {
            text-decoration: underline;
        }

        .lx-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            /* Buton kartın en altına yapışsın, kartlar eşit hizalansın */
            margin-top: auto;
            padding: 15px 24px;
            border-radius: var(--radius-md);
            border: 1px solid transparent;
            background: var(--lx-gradient);
            color: #fff;
            font-size: var(--text-base);
            font-weight: 700;
            box-shadow: 0 8px 22px color-mix(in srgb, var(--primary) 30%, transparent);
            transition: transform var(--transition-fast) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .lx-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 34px color-mix(in srgb, var(--primary) 48%, transparent);
        }

        .lx-cta:active {
            transform: translateY(0) scale(0.99);
        }

        /* Boş durum */
        .lx-empty {
            text-align: center;
            padding: var(--space-9) var(--space-5);
            border: 1px dashed var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--surface-1);
        }

        .lx-empty i {
            font-size: 42px;
            color: var(--lx-accent);
            margin-bottom: var(--space-4);
        }

        .lx-empty h3 {
            font-size: var(--text-xl);
            color: var(--text-primary);
            margin-bottom: var(--space-2);
        }

        .lx-empty p {
            color: var(--text-muted);
        }

        /* ===== "Her pakette standart" paneli =====
           8 ayrı kart yerine tek panel: hepsinin dahil olduğu
           tek bir vaat gibi okunur, dikey yer de yarıya iner. */
        .lx-included {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--surface-1);
            overflow: hidden;
        }

        .lx-included-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1px;
            background: var(--border-color);
        }

        .lx-inc {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            background: var(--bg-primary);
            transition: background-color var(--transition-normal) var(--ease-out);
        }

        .lx-inc:hover {
            background: color-mix(in srgb, var(--lx-accent) 6%, var(--bg-primary));
        }

        .lx-inc i {
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: var(--lx-accent-soft);
            color: var(--lx-accent-light);
            font-size: 14px;
        }

        .lx-inc b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .lx-inc span {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        /* ===== Teknoloji: bir büyük vitrin + üç kart ===== */
        .lx-spotlight {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 0.85fr);
            gap: var(--space-7);
            align-items: center;
            padding: var(--space-7);
            margin-bottom: var(--space-5);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background:
                radial-gradient(ellipse 60% 90% at 100% 50%, color-mix(in srgb, #22c55e 10%, transparent) 0%, transparent 70%),
                var(--surface-1);
        }

        .lx-spotlight-tag {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 14px;
            border-radius: 50px;
            background: color-mix(in srgb, #22c55e 14%, transparent);
            border: 1px solid color-mix(in srgb, #22c55e 30%, transparent);
            color: #4ade80;
            font-size: var(--text-xs);
            font-weight: 700;
            margin-bottom: var(--space-4);
        }

        .lx-spotlight h3 {
            font-size: var(--text-2xl);
            font-weight: 800;
            letter-spacing: -0.025em;
            color: var(--text-primary);
            margin-bottom: var(--space-3);
        }

        .lx-spotlight p {
            font-size: var(--text-md);
            color: var(--text-muted);
            line-height: 1.7;
            margin-bottom: var(--space-5);
            max-width: 46ch;
        }

        .lx-spotlight-points {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .lx-spotlight-points li {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 13px;
            border-radius: 50px;
            background: var(--surface-2);
            border: 1px solid var(--border-color);
            font-size: var(--text-xs);
            font-weight: 600;
            color: var(--text-secondary);
        }

        .lx-spotlight-points i {
            color: #4ade80;
            font-size: 10px;
        }

        /* Hız karşılaştırma çubukları */
        .lx-bars {
            display: grid;
            gap: var(--space-4);
        }

        .lx-bar-row {
            display: grid;
            grid-template-columns: 78px minmax(0, 1fr) 42px;
            align-items: center;
            gap: var(--space-3);
        }

        .lx-bar-label {
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--text-muted);
        }

        .lx-bar {
            height: 14px;
            border-radius: 50px;
            background: var(--surface-3);
            overflow: hidden;
        }

        .lx-bar span {
            display: block;
            height: 100%;
            border-radius: 50px;
            background: var(--text-gray);
        }

        .lx-bar-val {
            font-size: var(--text-base);
            font-weight: 800;
            color: var(--text-muted);
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .lx-bar-row.is-lead .lx-bar-label,
        .lx-bar-row.is-lead .lx-bar-val {
            color: var(--text-primary);
        }

        .lx-bar-row.is-lead .lx-bar span {
            background: linear-gradient(90deg, #22c55e, #15803d);
        }

        .lx-bar-note {
            font-size: var(--text-xs);
            color: var(--text-gray);
            line-height: 1.5;
            margin: 0;
        }

        .lx-tech {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: var(--space-5);
        }

        .lx-tech-card {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: var(--space-4);
            padding: var(--space-5);
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: transform var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .lx-tech-card::before {
            content: '';
            position: absolute;
            inset: 0 0 auto 0;
            height: 3px;
            background: var(--tint, var(--lx-gradient));
            transform: scaleX(0);
            transition: transform var(--transition-normal) var(--ease-out);
        }

        .lx-tech-card:hover {
            transform: translateY(-4px);
            background: var(--surface-2);
        }

        .lx-tech-card:hover::before {
            transform: scaleX(1);
        }

        .lx-tech-icon {
            width: 46px;
            height: 46px;
            flex-shrink: 0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            color: #fff;
            background: var(--tint, var(--lx-gradient));
        }

        .lx-tech-card h4 {
            font-size: var(--text-lg);
            font-weight: 700;
            letter-spacing: -0.015em;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .lx-tech-card p {
            font-size: var(--text-sm);
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* ===== Sık sorulan sorular ===== */
        /* Solda görsel + başlık, sağda soru listesi */
        .lx-faq-layout {
            display: grid;
            grid-template-columns: minmax(0, 330px) minmax(0, 1fr);
            gap: var(--space-8);
            /* Sol sutun listeyle ayni yuksekligi alsin */
            align-items: stretch;
        }

        .lx-faq-aside {
            display: flex;
            flex-direction: column;
        }

        .lx-head.is-left {
            text-align: left;
            max-width: 100%;
            margin: 0 0 var(--space-5);
        }

        .lx-faq-visual {
            /* Sabit yukseklik yerine kalan bosluk: liste uzayip kisaldikca hizali kalir */
            flex: 1 1 0;
            min-height: 120px;
            width: 100%;
            max-width: 330px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-4) 0;
        }

        .lx-faq-visual svg {
            width: 100%;
            height: 100%;
            max-height: 240px;
            display: block;
        }

        /* "Sorunuz yoksa bize yazın" kutusu */
        .lx-faq-help {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin-top: var(--space-5);
            padding: var(--space-4);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--surface-1);
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .lx-faq-help:hover {
            border-color: var(--lx-accent-line);
            background: var(--surface-2);
        }

        .lx-faq-help i {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--lx-gradient);
            color: #fff;
            font-size: 15px;
        }

        .lx-faq-help b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .lx-faq-help span {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        .lx-faq {
            display: grid;
            gap: var(--space-3);
        }

        .lx-faq-item {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--surface-1);
            overflow: hidden;
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .lx-faq-item[open] {
            border-color: var(--lx-accent-line);
            background: var(--surface-2);
        }

        .lx-faq-item summary {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            cursor: pointer;
            font-size: var(--text-md);
            font-weight: 600;
            color: var(--text-primary);
            list-style: none;
        }

        /* Soru ikonu - konuya özel satır içi SVG */
        .lx-faq-icon {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--lx-accent-soft);
            border: 1px solid var(--lx-accent-line);
            color: var(--lx-accent-light);
            transition: background-color var(--transition-normal) var(--ease-out),
                color var(--transition-normal) var(--ease-out);
        }

        .lx-faq-icon svg {
            width: 19px;
            height: 19px;
            display: block;
        }

        .lx-faq-item[open] .lx-faq-icon {
            background: var(--lx-gradient);
            border-color: transparent;
            color: #fff;
        }

        /* Soru metni ile artı işareti arasını doldurur */
        .lx-faq-q {
            flex: 1;
            min-width: 0;
        }

        /* Tarayıcının varsayılan üçgen işaretini kaldır */
        .lx-faq-item summary::-webkit-details-marker {
            display: none;
        }

        .lx-faq-item summary::marker {
            content: '';
        }

        .lx-faq-item summary:hover {
            color: var(--lx-accent-light);
        }

        .lx-faq-item summary:focus-visible {
            outline: none;
            box-shadow: var(--focus-ring);
        }

        .lx-faq-sign {
            width: 28px;
            height: 28px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--lx-accent-soft);
            color: var(--lx-accent-light);
            font-size: 12px;
            transition: transform var(--transition-normal) var(--ease-out);
        }

        .lx-faq-item[open] .lx-faq-sign {
            transform: rotate(45deg);
        }

        .lx-faq-body {
            /* Cevap, sorunun metniyle aynı hizadan başlasın (ikon + boşluk kadar içeride) */
            padding: 0 var(--space-5) var(--space-5) calc(var(--space-5) + 38px + var(--space-3));
            font-size: var(--text-base);
            color: var(--text-muted);
            line-height: 1.75;
            max-width: 72ch;
        }

        /* ===== Kapanış çağrısı ===== */
        .lx-final {
            position: relative;
            overflow: hidden;
            padding: var(--space-8) 0;
            background: var(--lx-gradient);
            color: #fff;
            text-align: center;
        }

        .lx-final::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 100% at 50% 0%, rgba(255, 255, 255, 0.2) 0%, transparent 62%),
                radial-gradient(ellipse 50% 80% at 88% 100%, rgba(0, 0, 0, 0.18) 0%, transparent 60%);
            pointer-events: none;
        }

        .lx-final>.container {
            position: relative;
            z-index: 1;
        }

        .lx-final h3 {
            font-size: var(--text-2xl);
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: var(--space-3);
            text-wrap: balance;
        }

        .lx-final p {
            font-size: var(--text-md);
            opacity: 0.92;
            margin-bottom: var(--space-6);
        }

        .lx-final-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: var(--space-3);
        }

        .lx-final-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 32px;
            border-radius: var(--radius-md);
            background: #fff;
            color: var(--lx-accent-dark);
            font-size: var(--text-md);
            font-weight: 700;
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.2);
            transition: transform var(--transition-fast) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .lx-final-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.28);
        }

        .lx-final-link {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 15px 26px;
            border-radius: var(--radius-md);
            border: 1px solid rgba(255, 255, 255, 0.45);
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            font-size: var(--text-md);
            font-weight: 600;
            transition: background-color var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out);
        }

        .lx-final-link:hover {
            background: rgba(255, 255, 255, 0.22);
            border-color: #fff;
        }

        /* ===== Duyarlılık ===== */
        @media (max-width: 1100px) {
            .lx-included-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .lx-spotlight {
                grid-template-columns: 1fr;
                gap: var(--space-6);
                padding: var(--space-6);
            }

            .lx-spotlight p {
                max-width: 100%;
            }
        }

        @media (max-width: 992px) {
            .lx-lead {
                max-width: 100%;
            }

            .lx-tech {
                grid-template-columns: 1fr;
            }

            /* Görsel ve başlık üste, sorular altına */
            .lx-faq-layout {
                grid-template-columns: 1fr;
                gap: var(--space-6);
                justify-items: center;
            }

            .lx-faq-aside {
                max-width: 460px;
                width: 100%;
            }

            .lx-head.is-left {
                text-align: center;
            }

            .lx-faq-visual {
                flex: 0 0 auto;
                margin: 0 auto;
            }

            .lx-faq-visual svg {
                height: auto;
            }

            .lx-faq {
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            .lx-plans,
            .lx-plans[data-count="2"],
            .lx-plans[data-count="3"] {
                grid-template-columns: 1fr;
            }

            .lx-included-grid {
                grid-template-columns: 1fr;
            }

            .lx-bar-row {
                grid-template-columns: 66px minmax(0, 1fr) 34px;
                gap: var(--space-2);
            }

            .lx-final-actions {
                flex-direction: column;
            }

            .lx-final-actions>* {
                width: 100%;
                justify-content: center;
            }

            /* Dar ekranda cevabı ikon hizasında girintilemek yer israfı olur */
            .lx-faq-body {
                padding-left: var(--space-5);
            }

            .lx-faq-item summary {
                padding-left: var(--space-4);
                padding-right: var(--space-4);
            }
        }
    </style>

    <div class="lx">

        <!-- Hero -->
        <section class="lx-hero">
            <div class="container">
                <div class="lx-hero-grid">
                    <div class="lx-hero-text">
                        <span class="lx-eyebrow"><i class="fab fa-linux"></i> AlmaLinux 9 Altyapısı</span>
                        <h1 class="lx-title"><span>Linux Hosting</span> Paketleri</h1>
                        <p class="lx-lead">
                            cPanel/WHM, LiteSpeed ve JetBackup ile güçlendirilmiş NVMe hosting.
                            Imunify360 koruması ve ücretsiz SSL her pakette standart.
                        </p>

                        <div class="lx-stack">
                            <span class="lx-chip"><i class="fas fa-cogs is-cpanel"></i> cPanel/WHM</span>
                            <span class="lx-chip"><i class="fas fa-bolt is-litespeed"></i> LiteSpeed</span>
                            <span class="lx-chip"><i class="fas fa-cloud-arrow-up is-jetbackup"></i> JetBackup</span>
                            <span class="lx-chip"><i class="fab fa-linux is-alma"></i> AlmaLinux 9</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kategori sekmeleri -->
            <div class="lx-tabs-wrap">
                <div class="container">
                    <div class="lx-tabs">
                        <a href="store.php" class="lx-tab"><i class="fas fa-th-large"></i> Tüm Ürünler</a>
                        <a href="store.php?group=linux-hosting" class="lx-tab is-active"><i class="fab fa-linux"></i> Linux
                            Hosting</a>
                        <?php foreach ($allGroups as $g):
                            if ($g['slug'] === 'linux-hosting') {
                                continue;
                            }
                            $gCount = Database::fetchColumn("SELECT COUNT(*) FROM products WHERE group_id = ? AND is_active = 1", [$g['id']]);
                            if ($gCount <= 0) {
                                continue;
                            }
                            ?>
                            <a href="store.php?group=<?= htmlspecialchars($g['slug']) ?>" class="lx-tab">
                                <i class="fas <?= $typeConfig[$g['type'] ?? 'hosting']['icon'] ?? 'fa-box' ?>"></i>
                                <?= htmlspecialchars($g['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Paketler -->
        <section class="lx-section" id="paketler">
            <div class="container">
                <div class="lx-head">
                    <span class="lx-badge"><i class="fab fa-linux"></i> Linux Hosting Paketleri</span>
                    <h2>Projenize Uygun <span>Hosting Planı</span></h2>
                    <p>Tüm paketlerde cPanel, ücretsiz SSL ve günlük yedekleme standart olarak sunulur.</p>
                </div>

                <?php if (empty($products)): ?>
                    <div class="lx-empty">
                        <i class="fab fa-linux"></i>
                        <h3>Ürün Bulunamadı</h3>
                        <p>Bu kategoride henüz ürün bulunmuyor.</p>
                    </div>
                <?php else:
                    $productCount = count($products);

                    // En az bir pakette yıllık indirim varsa, olmayanlarda rozet
                    // yüksekliği kadar boşluk bırakılır; kartlar hizalı kalır.
                    $anySaving = false;
                    foreach ($products as $p) {
                        $m = (float) ($p['price_monthly'] ?? 0);
                        $a = (float) ($p['price_annually'] ?? 0);
                        if ($m > 0 && $a > 0 && $a < $m * 12) {
                            $anySaving = true;
                            break;
                        }
                    }
                    ?>
                    <div class="lx-plans" data-count="<?= $productCount ?>">
                        <?php
                        // Öne çıkan ürün işaretliyse onu, değilse ortadaki paketi vurgula
                        $popularIndex = -1;
                        foreach ($products as $i => $p) {
                            if (!empty($p['is_featured'])) {
                                $popularIndex = $i;
                                break;
                            }
                        }
                        if ($popularIndex === -1 && $productCount > 2) {
                            $popularIndex = (int) floor(($productCount - 1) / 2);
                        }

                        foreach ($products as $index => $product):
                            $isPopular = ($index === $popularIndex);
                            $price = (float) ($product['price_monthly'] ?? 0);
                            $annual = (float) ($product['price_annually'] ?? 0);
                            $setup = (float) ($product['setup_fee'] ?? 0);

                            // Açıklama satırlarını teknik özet ve özellik listesi olarak ayır
                            $lines = [];
                            foreach (preg_split('/\r\n|\r|\n/', (string) ($product['description'] ?? '')) as $line) {
                                $line = trim($line);
                                if ($line !== '') {
                                    $lines[] = $line;
                                }
                            }

                            $specs = [];
                            $usedLines = [];
                            foreach ($lxSpecTypes as $key => $spec) {
                                if (count($specs) >= 4) {
                                    break;
                                }
                                foreach ($lines as $li => $line) {
                                    if (isset($usedLines[$li]) || !preg_match($spec['match'], $line)) {
                                        continue;
                                    }
                                    $value = $lxSpecValue($line);
                                    if ($value === '') {
                                        continue;
                                    }
                                    $specs[] = ['label' => $spec['label'], 'icon' => $spec['icon'], 'value' => $value];
                                    $usedLines[$li] = true;
                                    break;
                                }
                            }

                            $features = [];
                            foreach ($lines as $li => $line) {
                                if (!isset($usedLines[$li])) {
                                    $features[] = $line;
                                }
                            }
                            if (empty($features) && empty($specs)) {
                                $features = [
                                    'cPanel Kontrol Paneli',
                                    'LiteSpeed Web Server',
                                    'JetBackup Yedekleme',
                                    'Ücretsiz SSL Sertifikası',
                                    '7/24 Teknik Destek',
                                ];
                            }

                            // Yıllık ödemede aylığa göre kazanç
                            $savePercent = 0;
                            if ($price > 0 && $annual > 0 && $annual < $price * 12) {
                                $savePercent = (int) round((1 - ($annual / ($price * 12))) * 100);
                            }

                            $clip = count($features) > 6;
                            $listId = 'lx-feat-' . (int) $product['id'];
                            ?>
                            <article class="lx-plan <?= $isPopular ? 'is-popular' : '' ?>">
                                <?php if ($isPopular): ?>
                                    <div class="lx-ribbon"><i class="fas fa-fire"></i> En Popüler</div>
                                <?php endif; ?>

                                <div class="lx-plan-top">
                                    <div class="lx-plan-name"><?= htmlspecialchars($product['name']) ?></div>
                                    <div class="lx-plan-group">Linux Hosting</div>

                                    <?php if ($price > 0): ?>
                                        <div class="lx-price">
                                            <span class="cur">₺</span>
                                            <span class="val"><?= number_format(floor($price), 0, ',', '.') ?></span>
                                            <span class="per">/ay</span>
                                        </div>
                                        <?php if ($setup > 0): ?>
                                            <div class="lx-price-note">
                                                + ₺<?= number_format($setup, 0, ',', '.') ?> tek seferlik kurulum
                                            </div>
                                        <?php else: ?>
                                            <div class="lx-price-note">Kurulum ücreti yok</div>
                                        <?php endif; ?>
                                        <?php if ($savePercent > 0): ?>
                                            <span class="lx-save">
                                                <i class="fas fa-tag"></i> Yıllık ödemede %<?= $savePercent ?> indirim
                                            </span>
                                        <?php elseif ($anySaving): ?>
                                            <span class="lx-save is-placeholder" aria-hidden="true">
                                                <i class="fas fa-tag"></i> &nbsp;
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="lx-price-ask">Fiyat için görüşelim</div>
                                        <div class="lx-price-note">İhtiyacınıza göre özel teklif hazırlıyoruz</div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($specs)): ?>
                                    <div class="lx-specs">
                                        <?php foreach ($specs as $spec): ?>
                                            <div class="lx-spec">
                                                <i class="fas <?= $spec['icon'] ?>"></i>
                                                <div>
                                                    <b><?= htmlspecialchars($spec['value']) ?></b>
                                                    <span><?= htmlspecialchars($spec['label']) ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="lx-plan-body">
                                    <?php if (!empty($features)): ?>
                                        <ul class="lx-features <?= $clip ? 'is-clipped' : '' ?>" id="<?= $listId ?>">
                                            <?php foreach ($features as $feature): ?>
                                                <li><i class="fas fa-check"></i> <?= htmlspecialchars($feature) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php if ($clip): ?>
                                            <button type="button" class="lx-more" data-target="<?= $listId ?>"
                                                aria-expanded="false" aria-controls="<?= $listId ?>">
                                                + <?= count($features) - 6 ?> özellik daha
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <a class="lx-cta" href="/client/order-configure.php?id=<?= (int) $product['id'] ?>">
                                        <i class="fas fa-shopping-cart"></i> Sipariş Ver
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Her pakette standart -->
        <section class="lx-section is-alt">
            <div class="container">
                <div class="lx-head">
                    <span class="lx-badge"><i class="fas fa-check-double"></i> Pakete Dahil</span>
                    <h2>Hepsi <span>Her Pakette Standart</span></h2>
                    <p>Aşağıdakiler için ek ücret ödemezsiniz; en küçük pakette de aynen geçerli.</p>
                </div>

                <div class="lx-included">
                    <div class="lx-included-grid">
                        <div class="lx-inc">
                            <i class="fas fa-cogs"></i>
                            <div>
                                <b>cPanel Paneli</b>
                                <span>Tanıdık, kolay yönetim</span>
                            </div>
                        </div>
                        <div class="lx-inc">
                            <i class="fas fa-certificate"></i>
                            <div>
                                <b>Ücretsiz SSL</b>
                                <span>Let's Encrypt, otomatik yenileme</span>
                            </div>
                        </div>
                        <div class="lx-inc">
                            <i class="fas fa-shield-halved"></i>
                            <div>
                                <b>Imunify360</b>
                                <span>Zararlı yazılım taraması</span>
                            </div>
                        </div>
                        <div class="lx-inc">
                            <i class="fas fa-clock-rotate-left"></i>
                            <div>
                                <b>Günlük Yedek</b>
                                <span>JetBackup ile tek tık geri dönüş</span>
                            </div>
                        </div>
                        <div class="lx-inc">
                            <i class="fab fa-php"></i>
                            <div>
                                <b>PHP Selector</b>
                                <span>7.4 – 8.3 arası seçim</span>
                            </div>
                        </div>
                        <div class="lx-inc">
                            <i class="fas fa-database"></i>
                            <div>
                                <b>MySQL / MariaDB</b>
                                <span>phpMyAdmin erişimi</span>
                            </div>
                        </div>
                        <div class="lx-inc">
                            <i class="fab fa-wordpress"></i>
                            <div>
                                <b>Softaculous</b>
                                <span>1 tıkla WordPress kurulumu</span>
                            </div>
                        </div>
                        <div class="lx-inc">
                            <i class="fas fa-headset"></i>
                            <div>
                                <b>7/24 Destek</b>
                                <span>Uzman teknik ekip</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Teknoloji altyapısı -->
        <section class="lx-section">
            <div class="container">
                <div class="lx-head">
                    <span class="lx-badge"><i class="fas fa-microchip"></i> Teknoloji Altyapısı</span>
                    <h2>Hızın Geldiği <span>Yer</span></h2>
                    <p>Paketleri ayıran şey disk alanı değil, altındaki yazılım yığını.</p>
                </div>

                <!-- Vitrin: LiteSpeed -->
                <div class="lx-spotlight">
                    <div class="lx-spotlight-text">
                        <span class="lx-spotlight-tag"><i class="fas fa-bolt"></i> Web Sunucusu</span>
                        <h3>LiteSpeed Web Server</h3>
                        <p>
                            Apache ile aynı yapılandırmayı okur, ama istekleri olay tabanlı işler.
                            Aynı donanımda çok daha fazla eşzamanlı ziyaretçi karşılar.
                        </p>
                        <ul class="lx-spotlight-points">
                            <li><i class="fas fa-check"></i> HTTP/3 &amp; QUIC</li>
                            <li><i class="fas fa-check"></i> LSCache</li>
                            <li><i class="fas fa-check"></i> .htaccess uyumlu</li>
                            <li><i class="fas fa-check"></i> Brotli sıkıştırma</li>
                        </ul>
                    </div>

                    <div class="lx-bars">
                        <div class="lx-bar-row">
                            <span class="lx-bar-label">Apache</span>
                            <div class="lx-bar"><span style="width: 17%"></span></div>
                            <span class="lx-bar-val">1×</span>
                        </div>
                        <div class="lx-bar-row is-lead">
                            <span class="lx-bar-label">LiteSpeed</span>
                            <div class="lx-bar"><span style="width: 100%"></span></div>
                            <span class="lx-bar-val">6×</span>
                        </div>
                        <p class="lx-bar-note">
                            Statik içerik sunumunda karşılaştırmalı istek/saniye değerleri.
                            Gerçek kazanç sitenizin yapısına göre değişir.
                        </p>
                    </div>
                </div>

                <div class="lx-tech">
                    <div class="lx-tech-card" style="--tint: linear-gradient(135deg, #ff6c2c, #e8590c);">
                        <div class="lx-tech-icon"><i class="fas fa-cogs"></i></div>
                        <div>
                            <h4>cPanel / WHM</h4>
                            <p>Dosya yöneticisi, e-posta hesapları, veritabanları ve cron görevleri tek panelden.</p>
                        </div>
                    </div>
                    <div class="lx-tech-card" style="--tint: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                        <div class="lx-tech-icon"><i class="fas fa-cloud-arrow-up"></i></div>
                        <div>
                            <h4>JetBackup</h4>
                            <p>Otomatik günlük yedekleme. Tek dosyayı da tüm hesabı da tek tıkla geri yükleyin.</p>
                        </div>
                    </div>
                    <div class="lx-tech-card" style="--tint: linear-gradient(135deg, #38bdf8, #0369a1);">
                        <div class="lx-tech-icon"><i class="fab fa-linux"></i></div>
                        <div>
                            <h4>AlmaLinux 9</h4>
                            <p>CentOS'un ikili uyumlu halefi. Uzun destek ömrü, kurumsal seviye kararlılık.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sık sorulanlar -->
        <section class="lx-section is-alt">
            <div class="container">
                <div class="lx-faq-layout">
                    <div class="lx-faq-aside">
                        <div class="lx-head is-left">
                            <span class="lx-badge"><i class="fas fa-circle-question"></i> Sık Sorulanlar</span>
                            <h2>Aklınıza <span>Takılanlar</span></h2>
                            <p>Satın almadan önce en çok merak edilenleri derledik.</p>
                        </div>

                        <div class="lx-faq-visual">
                            <!-- Soru-cevap balonları; renkler tema değişkenlerinden gelir -->
                            <svg viewBox="0 0 320 272" fill="none" aria-hidden="true">
                                <defs>
                                    <linearGradient id="lxQmark" x1="0" y1="0" x2="1" y2="1">
                                        <stop offset="0" stop-color="var(--primary-light)" />
                                        <stop offset="1" stop-color="var(--primary-dark)" />
                                    </linearGradient>
                                    <radialGradient id="lxHalo" cx="0.5" cy="0.5" r="0.5">
                                        <stop offset="0" stop-color="var(--primary)" stop-opacity="0.20" />
                                        <stop offset="1" stop-color="var(--primary)" stop-opacity="0" />
                                    </radialGradient>
                                </defs>

                                <ellipse cx="160" cy="136" rx="152" ry="122" fill="url(#lxHalo)" />

                                <!-- Cevap balonu (arkada) -->
                                <path d="M276 242 v22 l-26 -22 z" fill="var(--surface-2)" stroke="var(--border-color)"
                                    stroke-width="2" stroke-linejoin="round" />
                                <rect x="104" y="150" width="200" height="92" rx="22" fill="var(--surface-2)"
                                    stroke="var(--border-color)" stroke-width="2" />
                                <rect x="132" y="172" width="140" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.55" />
                                <rect x="132" y="194" width="118" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.4" />
                                <rect x="132" y="216" width="78" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.28" />

                                <!-- Soru balonu (önde) -->
                                <path d="M44 126 v22 l26 -22 z" fill="color-mix(in srgb, var(--primary) 12%, transparent)" stroke="var(--primary)"
                                    stroke-width="2" stroke-linejoin="round" />
                                <rect x="16" y="26" width="196" height="100" rx="24" fill="color-mix(in srgb, var(--primary) 12%, transparent)"
                                    stroke="var(--primary)" stroke-width="2" />
                                <rect x="44" y="56" width="112" height="9" rx="4.5" fill="var(--primary-light)" opacity="0.85" />
                                <rect x="44" y="78" width="140" height="9" rx="4.5" fill="var(--primary-light)" opacity="0.6" />
                                <rect x="44" y="100" width="84" height="9" rx="4.5" fill="var(--primary-light)" opacity="0.4" />

                                <!-- Soru işareti rozeti -->
                                <circle cx="234" cy="44" r="28" fill="url(#lxQmark)" />
                                <text x="234" y="45" text-anchor="middle" dominant-baseline="central" fill="#fff"
                                    font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="34"
                                    font-weight="800">?</text>

                                <!-- Serpiştirilmiş noktalar -->
                                <circle cx="292" cy="104" r="5" fill="var(--primary)" opacity="0.5" />
                                <circle cx="306" cy="126" r="3" fill="var(--primary)" opacity="0.3" />
                                <circle cx="24" cy="186" r="4" fill="var(--primary)" opacity="0.35" />
                                <circle cx="44" cy="210" r="6" fill="var(--primary)" opacity="0.2" />
                            </svg>
                        </div>

                        <a href="contact.php" class="lx-faq-help">
                            <i class="fas fa-headset"></i>
                            <div>
                                <b>Sorunuz listede yok mu?</b>
                                <span>Destek ekibimize yazın, aynı gün dönelim.</span>
                            </div>
                        </a>
                    </div>

                <div class="lx-faq">
                    <details class="lx-faq-item">
                        <summary>
                            <span class="lx-faq-icon">
                                <!-- Kontrol paneli: bölmeli ekran -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="3.5" width="18" height="17" rx="2.5" />
                                    <path d="M3 9.5h18" />
                                    <path d="M9.5 20.5v-11" />
                                </svg>
                            </span>
                            <span class="lx-faq-q">Hangi kontrol panelini kullanacağım?</span>
                            <span class="lx-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="lx-faq-body">
                            Tüm Linux hosting paketlerinde cPanel kullanılır. Dosya yöneticisi, e-posta hesapları,
                            veritabanı yönetimi, cron görevleri ve SSL ayarları aynı panelden yönetilir.
                        </div>
                    </details>

                    <details class="lx-faq-item">
                        <summary>
                            <span class="lx-faq-icon">
                                <!-- Yedekleme: geri sarma oku + saat ibresi -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3.2 12a8.8 8.8 0 1 0 2.9-6.5" />
                                    <path d="M3 4.2v4.6h4.6" />
                                    <path d="M12 8.2V12l2.9 1.7" />
                                </svg>
                            </span>
                            <span class="lx-faq-q">Yedeklerim ne sıklıkla alınıyor?</span>
                            <span class="lx-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="lx-faq-body">
                            JetBackup ile günlük otomatik yedek alınır. Tek bir dosyayı, bir veritabanını ya da
                            tüm hesabı panel üzerinden tek tıkla geri yükleyebilirsiniz.
                        </div>
                    </details>

                    <details class="lx-faq-item">
                        <summary>
                            <span class="lx-faq-icon">
                                <!-- SSL: asma kilit -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="4" y="10.2" width="16" height="10.3" rx="2.4" />
                                    <path d="M8 10.2V7.1a4 4 0 0 1 8 0v3.1" />
                                    <path d="M12 14.4v2.2" />
                                </svg>
                            </span>
                            <span class="lx-faq-q">SSL sertifikası için ayrıca ödeme yapacak mıyım?</span>
                            <span class="lx-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="lx-faq-body">
                            Hayır. Let's Encrypt SSL sertifikası bütün paketlerde ücretsiz olarak sunulur ve
                            süresi dolmadan otomatik yenilenir.
                        </div>
                    </details>

                    <details class="lx-faq-item">
                        <summary>
                            <span class="lx-faq-icon">
                                <!-- PHP: kod ayraçları -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M8.4 8.2 4.2 12l4.2 3.8" />
                                    <path d="M15.6 8.2 19.8 12l-4.2 3.8" />
                                    <path d="M13.4 5.4 10.6 18.6" />
                                </svg>
                            </span>
                            <span class="lx-faq-q">PHP sürümünü kendim değiştirebilir miyim?</span>
                            <span class="lx-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="lx-faq-body">
                            Evet. CloudLinux PHP Selector ile PHP 7.4 – 8.3 arasındaki sürümler arasında geçiş
                            yapabilir, eklentileri kendi hesabınız için ayrı ayrı açıp kapatabilirsiniz.
                        </div>
                    </details>

                    <details class="lx-faq-item">
                        <summary>
                            <span class="lx-faq-icon">
                                <!-- İşletim sistemi: sunucu rafı -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="4" width="18" height="7" rx="2.2" />
                                    <rect x="3" y="13" width="18" height="7" rx="2.2" />
                                    <path d="M7 7.5h.01" />
                                    <path d="M7 16.5h.01" />
                                </svg>
                            </span>
                            <span class="lx-faq-q">Sitem hangi işletim sisteminde çalışacak?</span>
                            <span class="lx-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="lx-faq-body">
                            Sunucular AlmaLinux 9 üzerinde çalışır. Disk tarafında NVMe SSD, güvenlik tarafında
                            Imunify360 zararlı yazılım koruması kullanılır.
                        </div>
                    </details>

                    <details class="lx-faq-item">
                        <summary>
                            <span class="lx-faq-icon">
                                <!-- Taşıma: karşılıklı transfer okları -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4 9h15" />
                                    <path d="m15.5 5.5 3.5 3.5-3.5 3.5" />
                                    <path d="M20 15H5" />
                                    <path d="M8.5 11.5 5 15l3.5 3.5" />
                                </svg>
                            </span>
                            <span class="lx-faq-q">Mevcut sitemi siz taşıyor musunuz?</span>
                            <span class="lx-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="lx-faq-body">
                            P3 ve P4 paketlerinde ücretsiz site taşıma hizmeti dahildir. Diğer paketler için
                            taşıma talebinizi destek ekibimize iletebilirsiniz.
                        </div>
                    </details>
                    </div>
                </div>
            </div>
        </section>

        <!-- Kapanış -->
        <section class="lx-final">
            <div class="container">
                <h3><i class="fab fa-linux"></i> Linux Hosting ile Başlayın</h3>
                <p>cPanel + LiteSpeed + JetBackup ile profesyonel hosting</p>
                <div class="lx-final-actions">
                    <a href="#paketler" class="lx-final-btn">
                        <i class="fas fa-rocket"></i> Paketleri İncele
                    </a>
                    <a href="contact.php" class="lx-final-link">
                        <i class="fas fa-comments"></i> Önce Soru Sormak İsterim
                    </a>
                </div>
            </div>
        </section>
    </div>

    <script>
        // "+N özellik daha" düğmesi: listedeki gizli satırları açar
        document.querySelectorAll('.lx-more').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var list = document.getElementById(btn.dataset.target);
                if (!list) return;
                var expanded = list.classList.toggle('is-clipped') === false;
                btn.setAttribute('aria-expanded', String(expanded));
                btn.textContent = expanded
                    ? 'Daha az göster'
                    : '+ ' + (list.children.length - 6) + ' özellik daha';
            });
        });
    </script>

    <?php require_once __DIR__ . '/theme/includes/footer.php'; ?>

<?php elseif ($isWindowsPage): ?>

    <?php
    /**
     * Windows Hosting sayfası - ürün açıklamasından teknik özellikleri ayıkla.
     * "1000 MB Nvme Disk Alanı" gibi satırları vitrin kutucuğuna,
     * kalanları özellik listesine yollar.
     */
    $wnSpecTypes = [
        'disk' => ['label' => 'Disk Alanı', 'icon' => 'fa-hard-drive', 'match' => '/disk/iu'],
        'traffic' => ['label' => 'Aylık Trafik', 'icon' => 'fa-right-left', 'match' => '/trafik|bant\s?geniş/iu'],
        'ram' => ['label' => 'RAM', 'icon' => 'fa-memory', 'match' => '/\bram\b/iu'],
        'cpu' => ['label' => 'İşlemci', 'icon' => 'fa-microchip', 'match' => '/\bcpu\b|çekirdek|\bcore\b/iu'],
        'email' => ['label' => 'E-posta', 'icon' => 'fa-envelope', 'match' => '/e-?posta|\bmail\b/iu'],
        'db' => ['label' => 'Veritabanı', 'icon' => 'fa-database', 'match' => '/veri\s?taban|mysql|mariadb/iu'],
    ];

    /** Satırdan sayısal değeri + birimini çek: "1024 MB", "1 Core", "Limitsiz" */
    $wnSpecValue = static function (string $line): string {
        if (preg_match('/\b(limitsiz|sınırsız|unlimited)\b/iu', $line)) {
            return 'Limitsiz';
        }
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(TB|GB|MB|KB|Core|Adet|vCPU)?/iu', $line, $m)) {
            return trim($m[1] . ' ' . ($m[2] ?? ''));
        }
        return '';
    };
    ?>

    <style>
        /* ==========================================
           Windows Hosting - sayfaya özel değişkenler
           Vurgu rengi ve yüzeyler global tema
           token'larından gelir.
           ========================================== */
        .wn {
            --wn-accent: var(--primary);
            --wn-accent-light: var(--primary);
            --wn-accent-dark: var(--primary-dark);
            --wn-accent-soft: color-mix(in srgb, var(--primary) 12%, transparent);
            --wn-accent-line: color-mix(in srgb, var(--primary) 28%, transparent);
            --wn-gradient: var(--gradient-primary);
        }

        /* ===== Hero ===== */
        .wn-hero {
            position: relative;
            overflow: hidden;
            background: var(--gradient-hero);
            padding: var(--space-6) 0 0;
        }

        .wn-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 55% 50% at 12% 30%, color-mix(in srgb, var(--primary) 22%, transparent) 0%, transparent 62%),
                radial-gradient(ellipse 45% 45% at 88% 10%, color-mix(in srgb, var(--secondary) 16%, transparent) 0%, transparent 58%);
            pointer-events: none;
        }

        .wn-hero>.container {
            position: relative;
            z-index: 1;
        }

        /* Tek kolon, ortalanmis: hero'da ayrica gorsel yok */
        .wn-hero-grid {
            max-width: 780px;
            margin: 0 auto;
            padding-bottom: var(--space-6);
            text-align: center;
        }

        .wn-hero-text .wn-lead {
            margin-left: auto;
            margin-right: auto;
        }

        .wn-hero-text .wn-stack {
            justify-content: center;
        }

        .wn-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 50px;
            background: var(--wn-accent-soft);
            border: 1px solid var(--wn-accent-line);
            color: var(--wn-accent-light);
            font-size: var(--text-sm);
            font-weight: 600;
            margin-bottom: var(--space-4);
        }

        .wn-title {
            /* Ürün sayfası; anasayfa kadar iri olmasına gerek yok */
            font-size: clamp(32px, 3.2vw + 16px, 50px);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -0.035em;
            margin-bottom: var(--space-3);
            color: var(--text-primary);
            text-wrap: balance;
        }

        .wn-title span {
            background: var(--wn-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .wn-lead {
            font-size: var(--text-md);
            color: var(--text-muted);
            line-height: 1.65;
            max-width: 48ch;
            margin-bottom: var(--space-5);
            text-wrap: pretty;
        }

        /* Teknoloji rozetleri - 2x2 kutu yerine tek sıra çip;
           hero yüksekliğini ~110px kısaltır, detaylar aşağıdaki
           teknoloji bölümünde zaten anlatılıyor. */
        .wn-stack {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .wn-chip {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 7px 14px 7px 7px;
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .wn-chip:hover {
            background: var(--surface-2);
            border-color: var(--wn-accent-line);
        }

        .wn-chip i {
            width: 26px;
            height: 26px;
            flex-shrink: 0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #fff;
        }

        .wn-chip i.is-cpanel {
            background: linear-gradient(135deg, #ff6c2c, #e8590c);
        }

        .wn-chip i.is-litespeed {
            background: linear-gradient(135deg, #22c55e, #15803d);
        }

        .wn-chip i.is-jetbackup {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .wn-chip i.is-alma {
            background: linear-gradient(135deg, #38bdf8, #0369a1);
        }

        /* ===== Terminal ===== */
        /* Surum listesi: cubuk sutunu yok */
        .wn-bars.is-stack .wn-bar-row {
            grid-template-columns: minmax(0, 1fr) auto;
        }

        /* Windows teknoloji cip renkleri */
        .wn-chip i.is-plesk {
            background: linear-gradient(135deg, #52bad5, #2b7f95);
        }

        .wn-chip i.is-iis {
            background: linear-gradient(135deg, #0078d4, #005a9e);
        }

        .wn-chip i.is-mssql {
            background: linear-gradient(135deg, #c94f4f, #a4373a);
        }

        .wn-chip i.is-win {
            background: linear-gradient(135deg, #0078d4, #00a4ef);
        }

        /* ===== Kategori sekmeleri ===== */
        .wn-tabs-wrap {
            position: relative;
            border-top: 1px solid var(--border-color);
            background: color-mix(in srgb, var(--bg-primary) 65%, transparent);
            backdrop-filter: blur(12px);
        }

        .wn-tabs {
            display: flex;
            gap: var(--space-2);
            overflow-x: auto;
            padding: var(--space-3) var(--space-4);
            scrollbar-width: none;
            /* Dokunmatik ekranda sekmeler hizaya otursun */
            scroll-snap-type: x proximity;
        }

        .wn-tabs::-webkit-scrollbar {
            display: none;
        }

        .wn-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            scroll-snap-align: start;
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            background: var(--surface-1);
            color: var(--text-muted);
            font-size: var(--text-sm);
            font-weight: 600;
            white-space: nowrap;
            transition: all var(--transition-normal) var(--ease-out);
        }

        .wn-tab:hover {
            color: var(--text-primary);
            border-color: var(--wn-accent-line);
            background: var(--surface-2);
        }

        .wn-tab.is-active {
            background: var(--wn-gradient);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 6px 18px color-mix(in srgb, var(--primary) 35%, transparent);
        }

        /* ===== Bölüm başlıkları ===== */
        .wn-section {
            padding: var(--section-padding) 0;
        }

        /* Paketler bölümü hemen sekme şeridinin altında;
           sekmeler zaten ayırıcı, üstte tam boşluğa gerek yok */
        .wn-hero+.wn-section {
            padding-top: var(--space-7);
        }

        .wn-section.is-alt {
            background: var(--bg-primary);
        }

        .wn-head {
            text-align: center;
            max-width: 680px;
            margin: 0 auto var(--space-7);
        }

        .wn-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 50px;
            background: var(--wn-accent-soft);
            border: 1px solid var(--wn-accent-line);
            color: var(--wn-accent-light);
            font-size: var(--text-sm);
            font-weight: 600;
            margin-bottom: var(--space-4);
        }

        .wn-head h2 {
            font-size: var(--text-3xl);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.15;
            color: var(--text-primary);
            text-wrap: balance;
        }

        .wn-head h2 span {
            background: var(--wn-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .wn-head p {
            margin-top: var(--space-3);
            font-size: var(--text-md);
            color: var(--text-muted);
            text-wrap: pretty;
        }

        /* ===== Paket kartları ===== */
        .wn-plans {
            display: grid;
            /* Sutun sayisi paket adedinden gelir; auto-fit reflow yapmaz */
            grid-template-columns: repeat(var(--plan-cols, 4), minmax(0, 1fr));
            gap: var(--space-5);
            /* Kartlar eşit yükseklikte olsun, sipariş butonları hizalansın */
            align-items: stretch;
        }

        .wn-plans[data-count="1"] {
            --plan-cols: 1;
            max-width: 420px;
            margin-inline: auto;
        }

        .wn-plans[data-count="2"] {
            --plan-cols: 2;
        }

        .wn-plans[data-count="3"] {
            --plan-cols: 3;
        }

        @media (max-width: 1180px) {
            .wn-plans[data-count] {
                --plan-cols: 2;
            }
        }

        @media (max-width: 680px) {
            .wn-plans[data-count] {
                --plan-cols: 1;
            }
        }

        /* Az paket varsa ortala, kartlar aşırı genişlemesin */
        .wn-plans[data-count="1"] {
            grid-template-columns: minmax(300px, 420px);
            justify-content: center;
        }

        .wn-plans[data-count="2"] {
            grid-template-columns: repeat(2, minmax(300px, 430px));
            justify-content: center;
        }

        .wn-plans[data-count="3"] {
            grid-template-columns: repeat(3, minmax(280px, 400px));
            justify-content: center;
        }

        .wn-plan {
            position: relative;
            display: flex;
            flex-direction: column;
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: transform var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .wn-plan:hover {
            transform: translateY(-6px);
            border-color: var(--wn-accent-line);
            box-shadow: var(--shadow-lg);
        }

        .wn-plan.is-popular {
            border-color: var(--wn-accent);
            box-shadow: 0 0 0 1px var(--wn-accent), 0 24px 48px -18px color-mix(in srgb, var(--primary) 45%, transparent);
        }

        .wn-ribbon {
            position: absolute;
            top: 0;
            right: var(--space-5);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 0 0 10px 10px;
            background: var(--wn-gradient);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .wn-plan-top {
            padding: var(--space-6) var(--space-5) var(--space-5);
            border-bottom: 1px solid var(--border-color);
        }

        .wn-plan-name {
            font-size: var(--text-xl);
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .wn-plan-group {
            font-size: var(--text-sm);
            color: var(--text-muted);
            margin-bottom: var(--space-5);
        }

        .wn-price {
            display: flex;
            align-items: baseline;
            gap: 4px;
            /* Rakamlar kartlar arasında hizalı dursun */
            font-variant-numeric: tabular-nums;
        }

        .wn-price .cur {
            font-size: var(--text-lg);
            font-weight: 700;
            color: var(--wn-accent);
        }

        .wn-price .val {
            font-size: clamp(34px, 3vw + 22px, 46px);
            font-weight: 800;
            letter-spacing: -0.04em;
            line-height: 1;
            color: var(--text-primary);
        }

        .wn-price .per {
            font-size: var(--text-base);
            color: var(--text-muted);
            font-weight: 500;
        }

        .wn-price-ask {
            font-size: var(--text-xl);
            font-weight: 700;
            color: var(--text-primary);
        }

        .wn-price-note {
            margin-top: var(--space-2);
            font-size: var(--text-sm);
            color: var(--text-muted);
        }

        .wn-save {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: var(--space-3);
            padding: 5px 12px;
            border-radius: 50px;
            background: color-mix(in srgb, #22c55e 15%, transparent);
            border: 1px solid color-mix(in srgb, #22c55e 32%, transparent);
            color: #4ade80;
            font-size: var(--text-xs);
            font-weight: 700;
        }

        /* Yıllık indirimi olmayan pakette yeri boş dursun ki
           kartlar arasında teknik kutular aynı hizada başlasın */
        .wn-save.is-placeholder {
            visibility: hidden;
        }

        /* Teknik özet kutucukları */
        .wn-specs {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1px;
            background: var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .wn-spec {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-4);
            background: var(--bg-primary);
        }

        .wn-spec i {
            width: 30px;
            height: 30px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: var(--wn-accent-soft);
            color: var(--wn-accent-light);
            font-size: 13px;
        }

        .wn-spec b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.25;
        }

        .wn-spec span {
            font-size: 11px;
            color: var(--text-muted);
        }

        .wn-plan-body {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: var(--space-5);
        }

        .wn-features {
            list-style: none;
            margin: 0 0 var(--space-5);
            padding: 0;
            display: grid;
            gap: 2px;
        }

        .wn-features li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 7px 0;
            font-size: var(--text-sm);
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .wn-features li i {
            margin-top: 3px;
            font-size: 11px;
            color: #22c55e;
            flex-shrink: 0;
        }

        /* Uzun listelerde kart şişmesin: fazlası gizli, düğmeyle açılır */
        .wn-features.is-clipped li:nth-child(n+7) {
            display: none;
        }

        .wn-more {
            align-self: flex-start;
            margin: calc(var(--space-5) * -1 + 4px) 0 var(--space-5);
            padding: 6px 0;
            background: none;
            border: none;
            color: var(--wn-accent-light);
            font-family: inherit;
            font-size: var(--text-sm);
            font-weight: 600;
            cursor: pointer;
        }

        .wn-more:hover {
            text-decoration: underline;
        }

        .wn-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            /* Buton kartın en altına yapışsın, kartlar eşit hizalansın */
            margin-top: auto;
            padding: 15px 24px;
            border-radius: var(--radius-md);
            border: 1px solid transparent;
            background: var(--wn-gradient);
            color: #fff;
            font-size: var(--text-base);
            font-weight: 700;
            box-shadow: 0 8px 22px color-mix(in srgb, var(--primary) 30%, transparent);
            transition: transform var(--transition-fast) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .wn-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 34px color-mix(in srgb, var(--primary) 48%, transparent);
        }

        .wn-cta:active {
            transform: translateY(0) scale(0.99);
        }

        /* Boş durum */
        .wn-empty {
            text-align: center;
            padding: var(--space-9) var(--space-5);
            border: 1px dashed var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--surface-1);
        }

        .wn-empty i {
            font-size: 42px;
            color: var(--wn-accent);
            margin-bottom: var(--space-4);
        }

        .wn-empty h3 {
            font-size: var(--text-xl);
            color: var(--text-primary);
            margin-bottom: var(--space-2);
        }

        .wn-empty p {
            color: var(--text-muted);
        }

        /* ===== "Her pakette standart" paneli =====
           8 ayrı kart yerine tek panel: hepsinin dahil olduğu
           tek bir vaat gibi okunur, dikey yer de yarıya iner. */
        .wn-included {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--surface-1);
            overflow: hidden;
        }

        .wn-included-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1px;
            background: var(--border-color);
        }

        .wn-inc {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            background: var(--bg-primary);
            transition: background-color var(--transition-normal) var(--ease-out);
        }

        .wn-inc:hover {
            background: color-mix(in srgb, var(--wn-accent) 6%, var(--bg-primary));
        }

        .wn-inc i {
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: var(--wn-accent-soft);
            color: var(--wn-accent-light);
            font-size: 14px;
        }

        .wn-inc b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .wn-inc span {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        /* ===== Teknoloji: bir büyük vitrin + üç kart ===== */
        .wn-spotlight {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 0.85fr);
            gap: var(--space-7);
            align-items: center;
            padding: var(--space-7);
            margin-bottom: var(--space-5);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background:
                radial-gradient(ellipse 60% 90% at 100% 50%, color-mix(in srgb, #22c55e 10%, transparent) 0%, transparent 70%),
                var(--surface-1);
        }

        .wn-spotlight-tag {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 14px;
            border-radius: 50px;
            background: color-mix(in srgb, #22c55e 14%, transparent);
            border: 1px solid color-mix(in srgb, #22c55e 30%, transparent);
            color: #4ade80;
            font-size: var(--text-xs);
            font-weight: 700;
            margin-bottom: var(--space-4);
        }

        .wn-spotlight h3 {
            font-size: var(--text-2xl);
            font-weight: 800;
            letter-spacing: -0.025em;
            color: var(--text-primary);
            margin-bottom: var(--space-3);
        }

        .wn-spotlight p {
            font-size: var(--text-md);
            color: var(--text-muted);
            line-height: 1.7;
            margin-bottom: var(--space-5);
            max-width: 46ch;
        }

        .wn-spotlight-points {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .wn-spotlight-points li {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 13px;
            border-radius: 50px;
            background: var(--surface-2);
            border: 1px solid var(--border-color);
            font-size: var(--text-xs);
            font-weight: 600;
            color: var(--text-secondary);
        }

        .wn-spotlight-points i {
            color: #4ade80;
            font-size: 10px;
        }

        /* Hız karşılaştırma çubukları */
        .wn-bars {
            display: grid;
            gap: var(--space-4);
        }

        .wn-bar-row {
            display: grid;
            grid-template-columns: 78px minmax(0, 1fr) 42px;
            align-items: center;
            gap: var(--space-3);
        }

        .wn-bar-label {
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--text-muted);
        }

        .wn-bar {
            height: 14px;
            border-radius: 50px;
            background: var(--surface-3);
            overflow: hidden;
        }

        .wn-bar span {
            display: block;
            height: 100%;
            border-radius: 50px;
            background: var(--text-gray);
        }

        .wn-bar-val {
            font-size: var(--text-base);
            font-weight: 800;
            color: var(--text-muted);
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .wn-bar-row.is-lead .wn-bar-label,
        .wn-bar-row.is-lead .wn-bar-val {
            color: var(--text-primary);
        }

        .wn-bar-row.is-lead .wn-bar span {
            background: linear-gradient(90deg, #22c55e, #15803d);
        }

        .wn-bar-note {
            font-size: var(--text-xs);
            color: var(--text-gray);
            line-height: 1.5;
            margin: 0;
        }

        .wn-tech {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: var(--space-5);
        }

        .wn-tech-card {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: var(--space-4);
            padding: var(--space-5);
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: transform var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .wn-tech-card::before {
            content: '';
            position: absolute;
            inset: 0 0 auto 0;
            height: 3px;
            background: var(--tint, var(--wn-gradient));
            transform: scaleX(0);
            transition: transform var(--transition-normal) var(--ease-out);
        }

        .wn-tech-card:hover {
            transform: translateY(-4px);
            background: var(--surface-2);
        }

        .wn-tech-card:hover::before {
            transform: scaleX(1);
        }

        .wn-tech-icon {
            width: 46px;
            height: 46px;
            flex-shrink: 0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            color: #fff;
            background: var(--tint, var(--wn-gradient));
        }

        .wn-tech-card h4 {
            font-size: var(--text-lg);
            font-weight: 700;
            letter-spacing: -0.015em;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .wn-tech-card p {
            font-size: var(--text-sm);
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* ===== Sık sorulan sorular ===== */
        /* Solda görsel + başlık, sağda soru listesi */
        .wn-faq-layout {
            display: grid;
            grid-template-columns: minmax(0, 330px) minmax(0, 1fr);
            gap: var(--space-8);
            /* Sol sutun listeyle ayni yuksekligi alsin */
            align-items: stretch;
        }

        .wn-faq-aside {
            display: flex;
            flex-direction: column;
        }

        .wn-head.is-left {
            text-align: left;
            max-width: 100%;
            margin: 0 0 var(--space-5);
        }

        .wn-faq-visual {
            /* Sabit yukseklik yerine kalan bosluk: liste uzayip kisaldikca hizali kalir */
            flex: 1 1 0;
            min-height: 120px;
            width: 100%;
            max-width: 330px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-4) 0;
        }

        .wn-faq-visual svg {
            width: 100%;
            height: 100%;
            max-height: 240px;
            display: block;
        }

        /* "Sorunuz yoksa bize yazın" kutusu */
        .wn-faq-help {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin-top: var(--space-5);
            padding: var(--space-4);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--surface-1);
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .wn-faq-help:hover {
            border-color: var(--wn-accent-line);
            background: var(--surface-2);
        }

        .wn-faq-help i {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--wn-gradient);
            color: #fff;
            font-size: 15px;
        }

        .wn-faq-help b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .wn-faq-help span {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        .wn-faq {
            display: grid;
            gap: var(--space-3);
        }

        .wn-faq-item {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--surface-1);
            overflow: hidden;
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .wn-faq-item[open] {
            border-color: var(--wn-accent-line);
            background: var(--surface-2);
        }

        .wn-faq-item summary {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            cursor: pointer;
            font-size: var(--text-md);
            font-weight: 600;
            color: var(--text-primary);
            list-style: none;
        }

        /* Soru ikonu - konuya özel satır içi SVG */
        .wn-faq-icon {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--wn-accent-soft);
            border: 1px solid var(--wn-accent-line);
            color: var(--wn-accent-light);
            transition: background-color var(--transition-normal) var(--ease-out),
                color var(--transition-normal) var(--ease-out);
        }

        .wn-faq-icon svg {
            width: 19px;
            height: 19px;
            display: block;
        }

        .wn-faq-item[open] .wn-faq-icon {
            background: var(--wn-gradient);
            border-color: transparent;
            color: #fff;
        }

        /* Soru metni ile artı işareti arasını doldurur */
        .wn-faq-q {
            flex: 1;
            min-width: 0;
        }

        /* Tarayıcının varsayılan üçgen işaretini kaldır */
        .wn-faq-item summary::-webkit-details-marker {
            display: none;
        }

        .wn-faq-item summary::marker {
            content: '';
        }

        .wn-faq-item summary:hover {
            color: var(--wn-accent-light);
        }

        .wn-faq-item summary:focus-visible {
            outline: none;
            box-shadow: var(--focus-ring);
        }

        .wn-faq-sign {
            width: 28px;
            height: 28px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--wn-accent-soft);
            color: var(--wn-accent-light);
            font-size: 12px;
            transition: transform var(--transition-normal) var(--ease-out);
        }

        .wn-faq-item[open] .wn-faq-sign {
            transform: rotate(45deg);
        }

        .wn-faq-body {
            /* Cevap, sorunun metniyle aynı hizadan başlasın (ikon + boşluk kadar içeride) */
            padding: 0 var(--space-5) var(--space-5) calc(var(--space-5) + 38px + var(--space-3));
            font-size: var(--text-base);
            color: var(--text-muted);
            line-height: 1.75;
            max-width: 72ch;
        }

        /* ===== Kapanış çağrısı ===== */
        .wn-final {
            position: relative;
            overflow: hidden;
            padding: var(--space-8) 0;
            background: var(--wn-gradient);
            color: #fff;
            text-align: center;
        }

        .wn-final::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 100% at 50% 0%, rgba(255, 255, 255, 0.2) 0%, transparent 62%),
                radial-gradient(ellipse 50% 80% at 88% 100%, rgba(0, 0, 0, 0.18) 0%, transparent 60%);
            pointer-events: none;
        }

        .wn-final>.container {
            position: relative;
            z-index: 1;
        }

        .wn-final h3 {
            font-size: var(--text-2xl);
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: var(--space-3);
            text-wrap: balance;
        }

        .wn-final p {
            font-size: var(--text-md);
            opacity: 0.92;
            margin-bottom: var(--space-6);
        }

        .wn-final-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: var(--space-3);
        }

        .wn-final-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 32px;
            border-radius: var(--radius-md);
            background: #fff;
            color: var(--wn-accent-dark);
            font-size: var(--text-md);
            font-weight: 700;
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.2);
            transition: transform var(--transition-fast) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .wn-final-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.28);
        }

        .wn-final-link {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 15px 26px;
            border-radius: var(--radius-md);
            border: 1px solid rgba(255, 255, 255, 0.45);
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            font-size: var(--text-md);
            font-weight: 600;
            transition: background-color var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out);
        }

        .wn-final-link:hover {
            background: rgba(255, 255, 255, 0.22);
            border-color: #fff;
        }

        /* ===== Duyarlılık ===== */
        @media (max-width: 1100px) {
            .wn-included-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .wn-spotlight {
                grid-template-columns: 1fr;
                gap: var(--space-6);
                padding: var(--space-6);
            }

            .wn-spotlight p {
                max-width: 100%;
            }
        }

        @media (max-width: 992px) {
            .wn-lead {
                max-width: 100%;
            }

            .wn-tech {
                grid-template-columns: 1fr;
            }

            /* Görsel ve başlık üste, sorular altına */
            .wn-faq-layout {
                grid-template-columns: 1fr;
                gap: var(--space-6);
                justify-items: center;
            }

            .wn-faq-aside {
                max-width: 460px;
                width: 100%;
            }

            .wn-head.is-left {
                text-align: center;
            }

            .wn-faq-visual {
                flex: 0 0 auto;
                margin: 0 auto;
            }

            .wn-faq-visual svg {
                height: auto;
            }

            .wn-faq {
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            .wn-plans,
            .wn-plans[data-count="2"],
            .wn-plans[data-count="3"] {
                grid-template-columns: 1fr;
            }

            .wn-included-grid {
                grid-template-columns: 1fr;
            }

            .wn-bar-row {
                grid-template-columns: 66px minmax(0, 1fr) 34px;
                gap: var(--space-2);
            }

            .wn-final-actions {
                flex-direction: column;
            }

            .wn-final-actions>* {
                width: 100%;
                justify-content: center;
            }

            /* Dar ekranda cevabı ikon hizasında girintilemek yer israfı olur */
            .wn-faq-body {
                padding-left: var(--space-5);
            }

            .wn-faq-item summary {
                padding-left: var(--space-4);
                padding-right: var(--space-4);
            }
        }
    </style>

    <div class="wn">

        <!-- Hero -->
        <section class="wn-hero">
            <div class="container">
                <div class="wn-hero-grid">
                    <div class="wn-hero-text">
                        <span class="wn-eyebrow"><i class="fab fa-windows"></i> Windows Server 2022 Altyapısı</span>
                        <h1 class="wn-title"><span>Windows Hosting</span> Paketleri</h1>
                        <p class="wn-lead">
                            Plesk paneli, IIS ve MSSQL ile ASP.NET projeleriniz için Windows barındırma.
                            Günlük yedekleme ve ücretsiz SSL her pakette standart.
                        </p>

                        <div class="wn-stack">
                            <span class="wn-chip"><i class="fas fa-cogs is-plesk"></i> Plesk Paneli</span>
                            <span class="wn-chip"><i class="fas fa-server is-iis"></i> IIS 10</span>
                            <span class="wn-chip"><i class="fas fa-database is-mssql"></i> MSSQL</span>
                            <span class="wn-chip"><i class="fab fa-windows is-win"></i> Windows Server</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kategori sekmeleri -->
            <div class="wn-tabs-wrap">
                <div class="container">
                    <div class="wn-tabs">
                        <a href="store.php" class="wn-tab"><i class="fas fa-th-large"></i> Tüm Ürünler</a>
                        <a href="store.php?group=windows-hosting" class="wn-tab is-active"><i class="fab fa-windows"></i> Windows
                            Hosting</a>
                        <?php foreach ($allGroups as $g):
                            if ($g['slug'] === 'windows-hosting') {
                                continue;
                            }
                            $gCount = Database::fetchColumn("SELECT COUNT(*) FROM products WHERE group_id = ? AND is_active = 1", [$g['id']]);
                            if ($gCount <= 0) {
                                continue;
                            }
                            ?>
                            <a href="store.php?group=<?= htmlspecialchars($g['slug']) ?>" class="wn-tab">
                                <i class="fas <?= $typeConfig[$g['type'] ?? 'hosting']['icon'] ?? 'fa-box' ?>"></i>
                                <?= htmlspecialchars($g['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Paketler -->
        <section class="wn-section" id="paketler">
            <div class="container">
                <div class="wn-head">
                    <span class="wn-badge"><i class="fab fa-windows"></i> Windows Hosting Paketleri</span>
                    <h2>Projenize Uygun <span>Windows Planı</span></h2>
                    <p>Tüm paketlerde Plesk, ücretsiz SSL ve günlük yedekleme standart olarak sunulur.</p>
                </div>

                <?php if (empty($products)): ?>
                    <div class="wn-empty">
                        <i class="fab fa-windows"></i>
                        <h3>Ürün Bulunamadı</h3>
                        <p>Bu kategoride henüz ürün bulunmuyor.</p>
                    </div>
                <?php else:
                    $productCount = count($products);

                    // En az bir pakette yıllık indirim varsa, olmayanlarda rozet
                    // yüksekliği kadar boşluk bırakılır; kartlar hizalı kalır.
                    $anySaving = false;
                    foreach ($products as $p) {
                        $m = (float) ($p['price_monthly'] ?? 0);
                        $a = (float) ($p['price_annually'] ?? 0);
                        if ($m > 0 && $a > 0 && $a < $m * 12) {
                            $anySaving = true;
                            break;
                        }
                    }
                    ?>
                    <div class="wn-plans" data-count="<?= $productCount ?>">
                        <?php
                        // Öne çıkan ürün işaretliyse onu, değilse ortadaki paketi vurgula
                        $popularIndex = -1;
                        foreach ($products as $i => $p) {
                            if (!empty($p['is_featured'])) {
                                $popularIndex = $i;
                                break;
                            }
                        }
                        if ($popularIndex === -1 && $productCount > 2) {
                            $popularIndex = (int) floor(($productCount - 1) / 2);
                        }

                        foreach ($products as $index => $product):
                            $isPopular = ($index === $popularIndex);
                            $price = (float) ($product['price_monthly'] ?? 0);
                            $annual = (float) ($product['price_annually'] ?? 0);
                            $setup = (float) ($product['setup_fee'] ?? 0);

                            // Açıklama satırlarını teknik özet ve özellik listesi olarak ayır
                            $lines = [];
                            foreach (preg_split('/\r\n|\r|\n/', (string) ($product['description'] ?? '')) as $line) {
                                $line = trim($line);
                                if ($line !== '') {
                                    $lines[] = $line;
                                }
                            }

                            $specs = [];
                            $usedLines = [];
                            foreach ($wnSpecTypes as $key => $spec) {
                                if (count($specs) >= 4) {
                                    break;
                                }
                                foreach ($lines as $li => $line) {
                                    if (isset($usedLines[$li]) || !preg_match($spec['match'], $line)) {
                                        continue;
                                    }
                                    $value = $wnSpecValue($line);
                                    if ($value === '') {
                                        continue;
                                    }
                                    $specs[] = ['label' => $spec['label'], 'icon' => $spec['icon'], 'value' => $value];
                                    $usedLines[$li] = true;
                                    break;
                                }
                            }

                            $features = [];
                            foreach ($lines as $li => $line) {
                                if (!isset($usedLines[$li])) {
                                    $features[] = $line;
                                }
                            }
                            if (empty($features) && empty($specs)) {
                                $features = [
                                    'Plesk Kontrol Paneli',
                                    'IIS Web Sunucusu',
                                    'MSSQL Veritabanı',
                                    'Ücretsiz SSL Sertifikası',
                                    '7/24 Teknik Destek',
                                ];
                            }

                            // Yıllık ödemede aylığa göre kazanç
                            $savePercent = 0;
                            if ($price > 0 && $annual > 0 && $annual < $price * 12) {
                                $savePercent = (int) round((1 - ($annual / ($price * 12))) * 100);
                            }

                            $clip = count($features) > 6;
                            $listId = 'lx-feat-' . (int) $product['id'];
                            ?>
                            <article class="wn-plan <?= $isPopular ? 'is-popular' : '' ?>">
                                <?php if ($isPopular): ?>
                                    <div class="wn-ribbon"><i class="fas fa-fire"></i> En Popüler</div>
                                <?php endif; ?>

                                <div class="wn-plan-top">
                                    <div class="wn-plan-name"><?= htmlspecialchars($product['name']) ?></div>
                                    <div class="wn-plan-group">Windows Hosting</div>

                                    <?php if ($price > 0): ?>
                                        <div class="wn-price">
                                            <span class="cur">₺</span>
                                            <span class="val"><?= number_format(floor($price), 0, ',', '.') ?></span>
                                            <span class="per">/ay</span>
                                        </div>
                                        <?php if ($setup > 0): ?>
                                            <div class="wn-price-note">
                                                + ₺<?= number_format($setup, 0, ',', '.') ?> tek seferlik kurulum
                                            </div>
                                        <?php else: ?>
                                            <div class="wn-price-note">Kurulum ücreti yok</div>
                                        <?php endif; ?>
                                        <?php if ($savePercent > 0): ?>
                                            <span class="wn-save">
                                                <i class="fas fa-tag"></i> Yıllık ödemede %<?= $savePercent ?> indirim
                                            </span>
                                        <?php elseif ($anySaving): ?>
                                            <span class="wn-save is-placeholder" aria-hidden="true">
                                                <i class="fas fa-tag"></i> &nbsp;
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="wn-price-ask">Fiyat için görüşelim</div>
                                        <div class="wn-price-note">İhtiyacınıza göre özel teklif hazırlıyoruz</div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($specs)): ?>
                                    <div class="wn-specs">
                                        <?php foreach ($specs as $spec): ?>
                                            <div class="wn-spec">
                                                <i class="fas <?= $spec['icon'] ?>"></i>
                                                <div>
                                                    <b><?= htmlspecialchars($spec['value']) ?></b>
                                                    <span><?= htmlspecialchars($spec['label']) ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="wn-plan-body">
                                    <?php if (!empty($features)): ?>
                                        <ul class="wn-features <?= $clip ? 'is-clipped' : '' ?>" id="<?= $listId ?>">
                                            <?php foreach ($features as $feature): ?>
                                                <li><i class="fas fa-check"></i> <?= htmlspecialchars($feature) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php if ($clip): ?>
                                            <button type="button" class="wn-more" data-target="<?= $listId ?>"
                                                aria-expanded="false" aria-controls="<?= $listId ?>">
                                                + <?= count($features) - 6 ?> özellik daha
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <a class="wn-cta" href="/client/order-configure.php?id=<?= (int) $product['id'] ?>">
                                        <i class="fas fa-shopping-cart"></i> Sipariş Ver
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Her pakette standart -->
        <section class="wn-section is-alt">
            <div class="container">
                <div class="wn-head">
                    <span class="wn-badge"><i class="fas fa-check-double"></i> Pakete Dahil</span>
                    <h2>Hepsi <span>Her Pakette Standart</span></h2>
                    <p>Aşağıdakiler için ek ücret ödemezsiniz; en küçük pakette de aynen geçerli.</p>
                </div>

                <div class="wn-included">
                    <div class="wn-included-grid">
                        <div class="wn-inc">
                            <i class="fas fa-cogs"></i>
                            <div>
                                <b>Plesk Paneli</b>
                                <span>Windows için standart panel</span>
                            </div>
                        </div>
                        <div class="wn-inc">
                            <i class="fas fa-certificate"></i>
                            <div>
                                <b>Ücretsiz SSL</b>
                                <span>Let's Encrypt, otomatik yenileme</span>
                            </div>
                        </div>
                        <div class="wn-inc">
                            <i class="fas fa-shield-halved"></i>
                            <div>
                                <b>Güvenlik Duvarı</b>
                                <span>Zararlı yazılım taraması</span>
                            </div>
                        </div>
                        <div class="wn-inc">
                            <i class="fas fa-clock-rotate-left"></i>
                            <div>
                                <b>Günlük Yedek</b>
                                <span>Günlük yedek, tek tık geri dönüş</span>
                            </div>
                        </div>
                        <div class="wn-inc">
                            <i class="fas fa-code"></i>
                            <div>
                                <b>.NET Sürüm Seçimi</b>
                                <span>.NET 8 ve 4.8 desteği</span>
                            </div>
                        </div>
                        <div class="wn-inc">
                            <i class="fas fa-database"></i>
                            <div>
                                <b>MSSQL / MySQL</b>
                                <span>Plesk üzerinden yönetim</span>
                            </div>
                        </div>
                        <div class="wn-inc">
                            <i class="fab fa-wordpress"></i>
                            <div>
                                <b>Softaculous</b>
                                <span>1 tıkla uygulama kurulumu</span>
                            </div>
                        </div>
                        <div class="wn-inc">
                            <i class="fas fa-headset"></i>
                            <div>
                                <b>7/24 Destek</b>
                                <span>Uzman teknik ekip</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Teknoloji altyapısı -->
        <section class="wn-section">
            <div class="container">
                <div class="wn-head">
                    <span class="wn-badge"><i class="fas fa-microchip"></i> Teknoloji Altyapısı</span>
                    <h2>Windows <span>Yığını</span></h2>
                    <p>Bütün paketler aynı sürüm yığını üzerinde çalışır; fark yalnızca kaynaklarda.</p>
                </div>

                <!-- Vitrin: IIS -->
                <div class="wn-spotlight">
                    <div class="wn-spotlight-text">
                        <span class="wn-spotlight-tag"><i class="fas fa-server"></i> Web Sunucusu</span>
                        <h3>IIS 10 ve ASP.NET</h3>
                        <p>
                            ASP.NET, .NET Core ve klasik ASP uygulamaları doğrudan çalışır.
                            web.config yapılandırmanız olduğu gibi geçerlidir.
                        </p>
                        <ul class="wn-spotlight-points">
                            <li><i class="fas fa-check"></i> ASP.NET / .NET Core</li>
                            <li><i class="fas fa-check"></i> MSSQL veritabanı</li>
                            <li><i class="fas fa-check"></i> web.config uyumlu</li>
                            <li><i class="fas fa-check"></i> URL Rewrite modülü</li>
                        </ul>
                    </div>

                    <div class="wn-bars is-stack">
                        <div class="wn-bar-row"><span class="wn-bar-label">İşletim sistemi</span><span class="wn-bar-val">Windows Server 2022</span></div>
                        <div class="wn-bar-row"><span class="wn-bar-label">Web sunucusu</span><span class="wn-bar-val">IIS 10</span></div>
                        <div class="wn-bar-row"><span class="wn-bar-label">Çalışma zamanı</span><span class="wn-bar-val">.NET 8 / 4.8</span></div>
                        <div class="wn-bar-row"><span class="wn-bar-label">Veritabanı</span><span class="wn-bar-val">MSSQL 2022</span></div>
                        <p class="wn-bar-note">
                            Bütün paketler aynı sürüm yığını üzerinde çalışır.
                        </p>
                    </div>
                </div>

                <div class="wn-tech">
                    <div class="wn-tech-card" style="--tint: linear-gradient(135deg, #ff6c2c, #e8590c);">
                        <div class="wn-tech-icon"><i class="fas fa-cogs"></i></div>
                        <div>
                            <h4>Plesk Obsidian</h4>
                            <p>Dosya yöneticisi, e-posta hesapları, MSSQL veritabanları ve zamanlanmış görevler tek panelden.</p>
                        </div>
                    </div>
                    <div class="wn-tech-card" style="--tint: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                        <div class="wn-tech-icon"><i class="fas fa-cloud-arrow-up"></i></div>
                        <div>
                            <h4>MSSQL 2022</h4>
                            <p>Uzaktan bağlantı açıktır; veritabanınızı SQL Server Management Studio ile yönetebilirsiniz.</p>
                        </div>
                    </div>
                    <div class="wn-tech-card" style="--tint: linear-gradient(135deg, #38bdf8, #0369a1);">
                        <div class="wn-tech-icon"><i class="fab fa-windows"></i></div>
                        <div>
                            <h4>Windows Server 2022</h4>
                            <p>Güncel sürüm, uzun destek ömrü. Microsoft teknolojileriyle tam uyum.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sık sorulanlar -->
        <section class="wn-section is-alt">
            <div class="container">
                <div class="wn-faq-layout">
                    <div class="wn-faq-aside">
                        <div class="wn-head is-left">
                            <span class="wn-badge"><i class="fas fa-circle-question"></i> Sık Sorulanlar</span>
                            <h2>Aklınıza <span>Takılanlar</span></h2>
                            <p>Satın almadan önce en çok merak edilenleri derledik.</p>
                        </div>

                        <div class="wn-faq-visual">
                            <!-- Soru-cevap balonları; renkler tema değişkenlerinden gelir -->
                            <svg viewBox="0 0 320 272" fill="none" aria-hidden="true">
                                <defs>
                                    <linearGradient id="lxQmark" x1="0" y1="0" x2="1" y2="1">
                                        <stop offset="0" stop-color="var(--primary-light)" />
                                        <stop offset="1" stop-color="var(--primary-dark)" />
                                    </linearGradient>
                                    <radialGradient id="lxHalo" cx="0.5" cy="0.5" r="0.5">
                                        <stop offset="0" stop-color="var(--primary)" stop-opacity="0.20" />
                                        <stop offset="1" stop-color="var(--primary)" stop-opacity="0" />
                                    </radialGradient>
                                </defs>

                                <ellipse cx="160" cy="136" rx="152" ry="122" fill="url(#lxHalo)" />

                                <!-- Cevap balonu (arkada) -->
                                <path d="M276 242 v22 l-26 -22 z" fill="var(--surface-2)" stroke="var(--border-color)"
                                    stroke-width="2" stroke-linejoin="round" />
                                <rect x="104" y="150" width="200" height="92" rx="22" fill="var(--surface-2)"
                                    stroke="var(--border-color)" stroke-width="2" />
                                <rect x="132" y="172" width="140" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.55" />
                                <rect x="132" y="194" width="118" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.4" />
                                <rect x="132" y="216" width="78" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.28" />

                                <!-- Soru balonu (önde) -->
                                <path d="M44 126 v22 l26 -22 z" fill="color-mix(in srgb, var(--primary) 12%, transparent)" stroke="var(--primary)"
                                    stroke-width="2" stroke-linejoin="round" />
                                <rect x="16" y="26" width="196" height="100" rx="24" fill="color-mix(in srgb, var(--primary) 12%, transparent)"
                                    stroke="var(--primary)" stroke-width="2" />
                                <rect x="44" y="56" width="112" height="9" rx="4.5" fill="var(--primary-light)" opacity="0.85" />
                                <rect x="44" y="78" width="140" height="9" rx="4.5" fill="var(--primary-light)" opacity="0.6" />
                                <rect x="44" y="100" width="84" height="9" rx="4.5" fill="var(--primary-light)" opacity="0.4" />

                                <!-- Soru işareti rozeti -->
                                <circle cx="234" cy="44" r="28" fill="url(#lxQmark)" />
                                <text x="234" y="45" text-anchor="middle" dominant-baseline="central" fill="#fff"
                                    font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="34"
                                    font-weight="800">?</text>

                                <!-- Serpiştirilmiş noktalar -->
                                <circle cx="292" cy="104" r="5" fill="var(--primary)" opacity="0.5" />
                                <circle cx="306" cy="126" r="3" fill="var(--primary)" opacity="0.3" />
                                <circle cx="24" cy="186" r="4" fill="var(--primary)" opacity="0.35" />
                                <circle cx="44" cy="210" r="6" fill="var(--primary)" opacity="0.2" />
                            </svg>
                        </div>

                        <a href="contact.php" class="wn-faq-help">
                            <i class="fas fa-headset"></i>
                            <div>
                                <b>Sorunuz listede yok mu?</b>
                                <span>Destek ekibimize yazın, aynı gün dönelim.</span>
                            </div>
                        </a>
                    </div>

                <div class="wn-faq">
                    <details class="wn-faq-item">
                        <summary>
                            <span class="wn-faq-icon">
                                <!-- Kontrol paneli: bölmeli ekran -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="3.5" width="18" height="17" rx="2.5" />
                                    <path d="M3 9.5h18" />
                                    <path d="M9.5 20.5v-11" />
                                </svg>
                            </span>
                            <span class="wn-faq-q">Hangi kontrol panelini kullanacağım?</span>
                            <span class="wn-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="wn-faq-body">
                            Tüm Windows hosting paketlerinde Plesk kullanılır. Dosya yöneticisi, e-posta hesapları,
                            MSSQL yönetimi, zamanlanmış görevler ve SSL ayarları aynı panelden yönetilir.
                        </div>
                    </details>

                    <details class="wn-faq-item">
                        <summary>
                            <span class="wn-faq-icon">
                                <!-- Yedekleme: geri sarma oku + saat ibresi -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3.2 12a8.8 8.8 0 1 0 2.9-6.5" />
                                    <path d="M3 4.2v4.6h4.6" />
                                    <path d="M12 8.2V12l2.9 1.7" />
                                </svg>
                            </span>
                            <span class="wn-faq-q">Yedeklerim ne sıklıkla alınıyor?</span>
                            <span class="wn-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="wn-faq-body">
                            Günlük otomatik yedek alınır. Tek bir dosyayı, bir veritabanını ya da tüm hesabı
                            panel üzerinden tek tıkla geri yükleyebilirsiniz.
                        </div>
                    </details>

                    <details class="wn-faq-item">
                        <summary>
                            <span class="wn-faq-icon">
                                <!-- SSL: asma kilit -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="4" y="10.2" width="16" height="10.3" rx="2.4" />
                                    <path d="M8 10.2V7.1a4 4 0 0 1 8 0v3.1" />
                                    <path d="M12 14.4v2.2" />
                                </svg>
                            </span>
                            <span class="wn-faq-q">SSL sertifikası için ayrıca ödeme yapacak mıyım?</span>
                            <span class="wn-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="wn-faq-body">
                            Hayır. Let's Encrypt SSL sertifikası bütün paketlerde ücretsiz olarak sunulur ve
                            süresi dolmadan otomatik yenilenir.
                        </div>
                    </details>

                    <details class="wn-faq-item">
                        <summary>
                            <span class="wn-faq-icon">
                                <!-- PHP: kod ayraçları -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M8.4 8.2 4.2 12l4.2 3.8" />
                                    <path d="M15.6 8.2 19.8 12l-4.2 3.8" />
                                    <path d="M13.4 5.4 10.6 18.6" />
                                </svg>
                            </span>
                            <span class="wn-faq-q">.NET sürümünü kendim seçebilir miyim?</span>
                            <span class="wn-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="wn-faq-body">
                            Evet. Plesk üzerinden .NET Framework 4.8 ile .NET 8 arasında geçiş yapabilir,
                            uygulama havuzunuzun ayarlarını kendiniz düzenleyebilirsiniz.
                        </div>
                    </details>

                    <details class="wn-faq-item">
                        <summary>
                            <span class="wn-faq-icon">
                                <!-- İşletim sistemi: sunucu rafı -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="4" width="18" height="7" rx="2.2" />
                                    <rect x="3" y="13" width="18" height="7" rx="2.2" />
                                    <path d="M7 7.5h.01" />
                                    <path d="M7 16.5h.01" />
                                </svg>
                            </span>
                            <span class="wn-faq-q">Sitem hangi işletim sisteminde çalışacak?</span>
                            <span class="wn-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="wn-faq-body">
                            Sunucular Windows Server 2022 üzerinde çalışır. Disk tarafında SSD, veritabanı
                            tarafında MSSQL 2022 kullanılır.
                        </div>
                    </details>

                    <details class="wn-faq-item">
                        <summary>
                            <span class="wn-faq-icon">
                                <!-- Taşıma: karşılıklı transfer okları -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4 9h15" />
                                    <path d="m15.5 5.5 3.5 3.5-3.5 3.5" />
                                    <path d="M20 15H5" />
                                    <path d="M8.5 11.5 5 15l3.5 3.5" />
                                </svg>
                            </span>
                            <span class="wn-faq-q">Mevcut sitemi siz taşıyor musunuz?</span>
                            <span class="wn-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="wn-faq-body">
                            P3 ve P4 paketlerinde ücretsiz site taşıma hizmeti dahildir. Diğer paketler için
                            taşıma talebinizi destek ekibimize iletebilirsiniz.
                        </div>
                    </details>
                    </div>
                </div>
            </div>
        </section>

        <!-- Kapanış -->
        <section class="wn-final">
            <div class="container">
                <h3><i class="fab fa-windows"></i> Windows Hosting ile Başlayın</h3>
                <p>Plesk + IIS + MSSQL ile profesyonel Windows barındırma</p>
                <div class="wn-final-actions">
                    <a href="#paketler" class="wn-final-btn">
                        <i class="fas fa-rocket"></i> Paketleri İncele
                    </a>
                    <a href="contact.php" class="wn-final-link">
                        <i class="fas fa-comments"></i> Önce Soru Sormak İsterim
                    </a>
                </div>
            </div>
        </section>
    </div>

    <script>
        // "+N özellik daha" düğmesi: listedeki gizli satırları açar
        document.querySelectorAll('.wn-more').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var list = document.getElementById(btn.dataset.target);
                if (!list) return;
                var expanded = list.classList.toggle('is-clipped') === false;
                btn.setAttribute('aria-expanded', String(expanded));
                btn.textContent = expanded
                    ? 'Daha az göster'
                    : '+ ' + (list.children.length - 6) + ' özellik daha';
            });
        });
    </script>

    <?php require_once __DIR__ . '/theme/includes/footer.php'; ?>

<?php elseif ($isWpPage): ?>

    <?php
    /**
     * WordPress Hosting sayfası - ürün açıklamasından teknik özellikleri ayıkla.
     * "1000 MB Nvme Disk Alanı" gibi satırları vitrin kutucuğuna,
     * kalanları özellik listesine yollar.
     */
    $wpSpecTypes = [
        'disk' => ['label' => 'Disk Alanı', 'icon' => 'fa-hard-drive', 'match' => '/disk/iu'],
        'traffic' => ['label' => 'Aylık Trafik', 'icon' => 'fa-right-left', 'match' => '/trafik|bant\s?geniş/iu'],
        'ram' => ['label' => 'RAM', 'icon' => 'fa-memory', 'match' => '/\bram\b/iu'],
        'cpu' => ['label' => 'İşlemci', 'icon' => 'fa-microchip', 'match' => '/\bcpu\b|çekirdek|\bcore\b/iu'],
        'email' => ['label' => 'E-posta', 'icon' => 'fa-envelope', 'match' => '/e-?posta|\bmail\b/iu'],
        'db' => ['label' => 'Veritabanı', 'icon' => 'fa-database', 'match' => '/veri\s?taban|mysql|mariadb/iu'],
    ];

    /** Satırdan sayısal değeri + birimini çek: "1024 MB", "1 Core", "Limitsiz" */
    $wpSpecValue = static function (string $line): string {
        if (preg_match('/\b(limitsiz|sınırsız|unlimited)\b/iu', $line)) {
            return 'Limitsiz';
        }
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(TB|GB|MB|KB|Core|Adet|vCPU)?/iu', $line, $m)) {
            return trim($m[1] . ' ' . ($m[2] ?? ''));
        }
        return '';
    };
    ?>

    <style>
        /* ==========================================
           WordPress Hosting - sayfaya özel değişkenler
           Vurgu rengi ve yüzeyler global tema
           token'larından gelir.
           ========================================== */
        .wp {
            --wp-accent: var(--primary);
            --wp-accent-light: var(--primary);
            --wp-accent-dark: var(--primary-dark);
            --wp-accent-soft: color-mix(in srgb, var(--primary) 12%, transparent);
            --wp-accent-line: color-mix(in srgb, var(--primary) 28%, transparent);
            --wp-gradient: var(--gradient-primary);
        }

        /* ===== Hero ===== */
        .wp-hero {
            position: relative;
            overflow: hidden;
            background: var(--gradient-hero);
            padding: var(--space-6) 0 0;
        }

        .wp-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 55% 50% at 12% 30%, color-mix(in srgb, var(--primary) 22%, transparent) 0%, transparent 62%),
                radial-gradient(ellipse 45% 45% at 88% 10%, color-mix(in srgb, var(--secondary) 16%, transparent) 0%, transparent 58%);
            pointer-events: none;
        }

        .wp-hero>.container {
            position: relative;
            z-index: 1;
        }

        /* Tek kolon, ortalanmis: hero'da ayrica gorsel yok */
        .wp-hero-grid {
            max-width: 780px;
            margin: 0 auto;
            padding-bottom: var(--space-6);
            text-align: center;
        }

        .wp-hero-text .wp-lead {
            margin-left: auto;
            margin-right: auto;
        }

        .wp-hero-text .wp-stack {
            justify-content: center;
        }

        .wp-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 50px;
            background: var(--wp-accent-soft);
            border: 1px solid var(--wp-accent-line);
            color: var(--wp-accent-light);
            font-size: var(--text-sm);
            font-weight: 600;
            margin-bottom: var(--space-4);
        }

        .wp-title {
            /* Ürün sayfası; anasayfa kadar iri olmasına gerek yok */
            font-size: clamp(32px, 3.2vw + 16px, 50px);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -0.035em;
            margin-bottom: var(--space-3);
            color: var(--text-primary);
            text-wrap: balance;
        }

        .wp-title span {
            background: var(--wp-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .wp-lead {
            font-size: var(--text-md);
            color: var(--text-muted);
            line-height: 1.65;
            max-width: 48ch;
            margin-bottom: var(--space-5);
            text-wrap: pretty;
        }

        /* Teknoloji rozetleri - 2x2 kutu yerine tek sıra çip;
           hero yüksekliğini ~110px kısaltır, detaylar aşağıdaki
           teknoloji bölümünde zaten anlatılıyor. */
        .wp-stack {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .wp-chip {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 7px 14px 7px 7px;
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .wp-chip:hover {
            background: var(--surface-2);
            border-color: var(--wp-accent-line);
        }

        .wp-chip i {
            width: 26px;
            height: 26px;
            flex-shrink: 0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #fff;
        }

        .wp-chip i.is-cpanel {
            background: linear-gradient(135deg, #ff6c2c, #e8590c);
        }

        .wp-chip i.is-litespeed {
            background: linear-gradient(135deg, #22c55e, #15803d);
        }

        .wp-chip i.is-jetbackup {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .wp-chip i.is-wpcore {
            background: linear-gradient(135deg, #21759b, #16537a);
        }

        /* ===== Terminal ===== */
        /* ===== Kategori sekmeleri ===== */
        .wp-tabs-wrap {
            position: relative;
            border-top: 1px solid var(--border-color);
            background: color-mix(in srgb, var(--bg-primary) 65%, transparent);
            backdrop-filter: blur(12px);
        }

        .wp-tabs {
            display: flex;
            gap: var(--space-2);
            overflow-x: auto;
            padding: var(--space-3) var(--space-4);
            scrollbar-width: none;
            /* Dokunmatik ekranda sekmeler hizaya otursun */
            scroll-snap-type: x proximity;
        }

        .wp-tabs::-webkit-scrollbar {
            display: none;
        }

        .wp-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            scroll-snap-align: start;
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            background: var(--surface-1);
            color: var(--text-muted);
            font-size: var(--text-sm);
            font-weight: 600;
            white-space: nowrap;
            transition: all var(--transition-normal) var(--ease-out);
        }

        .wp-tab:hover {
            color: var(--text-primary);
            border-color: var(--wp-accent-line);
            background: var(--surface-2);
        }

        .wp-tab.is-active {
            background: var(--wp-gradient);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 6px 18px color-mix(in srgb, var(--primary) 35%, transparent);
        }

        /* ===== Bölüm başlıkları ===== */
        .wp-section {
            padding: var(--section-padding) 0;
        }

        /* Paketler bölümü hemen sekme şeridinin altında;
           sekmeler zaten ayırıcı, üstte tam boşluğa gerek yok */
        .wp-hero+.wp-section {
            padding-top: var(--space-7);
        }

        .wp-section.is-alt {
            background: var(--bg-primary);
        }

        .wp-head {
            text-align: center;
            max-width: 680px;
            margin: 0 auto var(--space-7);
        }

        .wp-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 50px;
            background: var(--wp-accent-soft);
            border: 1px solid var(--wp-accent-line);
            color: var(--wp-accent-light);
            font-size: var(--text-sm);
            font-weight: 600;
            margin-bottom: var(--space-4);
        }

        .wp-head h2 {
            font-size: var(--text-3xl);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.15;
            color: var(--text-primary);
            text-wrap: balance;
        }

        .wp-head h2 span {
            background: var(--wp-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .wp-head p {
            margin-top: var(--space-3);
            font-size: var(--text-md);
            color: var(--text-muted);
            text-wrap: pretty;
        }

        /* ===== Paket kartları ===== */
        .wp-plans {
            display: grid;
            /* Sutun sayisi paket adedinden gelir; auto-fit reflow yapmaz */
            grid-template-columns: repeat(var(--plan-cols, 4), minmax(0, 1fr));
            gap: var(--space-5);
            /* Kartlar eşit yükseklikte olsun, sipariş butonları hizalansın */
            align-items: stretch;
        }

        .wp-plans[data-count="1"] {
            --plan-cols: 1;
            max-width: 420px;
            margin-inline: auto;
        }

        .wp-plans[data-count="2"] {
            --plan-cols: 2;
        }

        .wp-plans[data-count="3"] {
            --plan-cols: 3;
        }

        @media (max-width: 1180px) {
            .wp-plans[data-count] {
                --plan-cols: 2;
            }
        }

        @media (max-width: 680px) {
            .wp-plans[data-count] {
                --plan-cols: 1;
            }
        }

        /* Az paket varsa ortala, kartlar aşırı genişlemesin */
        .wp-plans[data-count="1"] {
            grid-template-columns: minmax(300px, 420px);
            justify-content: center;
        }

        .wp-plans[data-count="2"] {
            grid-template-columns: repeat(2, minmax(300px, 430px));
            justify-content: center;
        }

        .wp-plans[data-count="3"] {
            grid-template-columns: repeat(3, minmax(280px, 400px));
            justify-content: center;
        }

        .wp-plan {
            position: relative;
            display: flex;
            flex-direction: column;
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: transform var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .wp-plan:hover {
            transform: translateY(-6px);
            border-color: var(--wp-accent-line);
            box-shadow: var(--shadow-lg);
        }

        .wp-plan.is-popular {
            border-color: var(--wp-accent);
            box-shadow: 0 0 0 1px var(--wp-accent), 0 24px 48px -18px color-mix(in srgb, var(--primary) 45%, transparent);
        }

        .wp-ribbon {
            position: absolute;
            top: 0;
            right: var(--space-5);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 0 0 10px 10px;
            background: var(--wp-gradient);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .wp-plan-top {
            padding: var(--space-6) var(--space-5) var(--space-5);
            border-bottom: 1px solid var(--border-color);
        }

        .wp-plan-name {
            font-size: var(--text-xl);
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .wp-plan-group {
            font-size: var(--text-sm);
            color: var(--text-muted);
            margin-bottom: var(--space-5);
        }

        .wp-price {
            display: flex;
            align-items: baseline;
            gap: 4px;
            /* Rakamlar kartlar arasında hizalı dursun */
            font-variant-numeric: tabular-nums;
        }

        .wp-price .cur {
            font-size: var(--text-lg);
            font-weight: 700;
            color: var(--wp-accent);
        }

        .wp-price .val {
            font-size: clamp(34px, 3vw + 22px, 46px);
            font-weight: 800;
            letter-spacing: -0.04em;
            line-height: 1;
            color: var(--text-primary);
        }

        .wp-price .per {
            font-size: var(--text-base);
            color: var(--text-muted);
            font-weight: 500;
        }

        .wp-price-ask {
            font-size: var(--text-xl);
            font-weight: 700;
            color: var(--text-primary);
        }

        .wp-price-note {
            margin-top: var(--space-2);
            font-size: var(--text-sm);
            color: var(--text-muted);
        }

        .wp-save {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: var(--space-3);
            padding: 5px 12px;
            border-radius: 50px;
            background: color-mix(in srgb, #22c55e 15%, transparent);
            border: 1px solid color-mix(in srgb, #22c55e 32%, transparent);
            color: #4ade80;
            font-size: var(--text-xs);
            font-weight: 700;
        }

        /* Yıllık indirimi olmayan pakette yeri boş dursun ki
           kartlar arasında teknik kutular aynı hizada başlasın */
        .wp-save.is-placeholder {
            visibility: hidden;
        }

        /* Teknik özet kutucukları */
        .wp-specs {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1px;
            background: var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .wp-spec {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-4);
            background: var(--bg-primary);
        }

        .wp-spec i {
            width: 30px;
            height: 30px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: var(--wp-accent-soft);
            color: var(--wp-accent-light);
            font-size: 13px;
        }

        .wp-spec b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.25;
        }

        .wp-spec span {
            font-size: 11px;
            color: var(--text-muted);
        }

        .wp-plan-body {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: var(--space-5);
        }

        .wp-features {
            list-style: none;
            margin: 0 0 var(--space-5);
            padding: 0;
            display: grid;
            gap: 2px;
        }

        .wp-features li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 7px 0;
            font-size: var(--text-sm);
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .wp-features li i {
            margin-top: 3px;
            font-size: 11px;
            color: #22c55e;
            flex-shrink: 0;
        }

        /* Uzun listelerde kart şişmesin: fazlası gizli, düğmeyle açılır */
        .wp-features.is-clipped li:nth-child(n+7) {
            display: none;
        }

        .wp-more {
            align-self: flex-start;
            margin: calc(var(--space-5) * -1 + 4px) 0 var(--space-5);
            padding: 6px 0;
            background: none;
            border: none;
            color: var(--wp-accent-light);
            font-family: inherit;
            font-size: var(--text-sm);
            font-weight: 600;
            cursor: pointer;
        }

        .wp-more:hover {
            text-decoration: underline;
        }

        .wp-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            /* Buton kartın en altına yapışsın, kartlar eşit hizalansın */
            margin-top: auto;
            padding: 15px 24px;
            border-radius: var(--radius-md);
            border: 1px solid transparent;
            background: var(--wp-gradient);
            color: #fff;
            font-size: var(--text-base);
            font-weight: 700;
            box-shadow: 0 8px 22px color-mix(in srgb, var(--primary) 30%, transparent);
            transition: transform var(--transition-fast) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .wp-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 34px color-mix(in srgb, var(--primary) 48%, transparent);
        }

        .wp-cta:active {
            transform: translateY(0) scale(0.99);
        }

        /* Boş durum */
        .wp-empty {
            text-align: center;
            padding: var(--space-9) var(--space-5);
            border: 1px dashed var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--surface-1);
        }

        .wp-empty i {
            font-size: 42px;
            color: var(--wp-accent);
            margin-bottom: var(--space-4);
        }

        .wp-empty h3 {
            font-size: var(--text-xl);
            color: var(--text-primary);
            margin-bottom: var(--space-2);
        }

        .wp-empty p {
            color: var(--text-muted);
        }

        /* ===== "Her pakette standart" paneli =====
           8 ayrı kart yerine tek panel: hepsinin dahil olduğu
           tek bir vaat gibi okunur, dikey yer de yarıya iner. */
        .wp-included {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--surface-1);
            overflow: hidden;
        }

        .wp-included-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1px;
            background: var(--border-color);
        }

        .wp-inc {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            background: var(--bg-primary);
            transition: background-color var(--transition-normal) var(--ease-out);
        }

        .wp-inc:hover {
            background: color-mix(in srgb, var(--wp-accent) 6%, var(--bg-primary));
        }

        .wp-inc i {
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: var(--wp-accent-soft);
            color: var(--wp-accent-light);
            font-size: 14px;
        }

        .wp-inc b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .wp-inc span {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        /* ===== Teknoloji: bir büyük vitrin + üç kart ===== */
        .wp-spotlight {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 0.85fr);
            gap: var(--space-7);
            align-items: center;
            padding: var(--space-7);
            margin-bottom: var(--space-5);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background:
                radial-gradient(ellipse 60% 90% at 100% 50%, color-mix(in srgb, #22c55e 10%, transparent) 0%, transparent 70%),
                var(--surface-1);
        }

        .wp-spotlight-tag {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 14px;
            border-radius: 50px;
            background: color-mix(in srgb, #22c55e 14%, transparent);
            border: 1px solid color-mix(in srgb, #22c55e 30%, transparent);
            color: #4ade80;
            font-size: var(--text-xs);
            font-weight: 700;
            margin-bottom: var(--space-4);
        }

        .wp-spotlight h3 {
            font-size: var(--text-2xl);
            font-weight: 800;
            letter-spacing: -0.025em;
            color: var(--text-primary);
            margin-bottom: var(--space-3);
        }

        .wp-spotlight p {
            font-size: var(--text-md);
            color: var(--text-muted);
            line-height: 1.7;
            margin-bottom: var(--space-5);
            max-width: 46ch;
        }

        .wp-spotlight-points {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .wp-spotlight-points li {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 13px;
            border-radius: 50px;
            background: var(--surface-2);
            border: 1px solid var(--border-color);
            font-size: var(--text-xs);
            font-weight: 600;
            color: var(--text-secondary);
        }

        .wp-spotlight-points i {
            color: #4ade80;
            font-size: 10px;
        }

        /* Hız karşılaştırma çubukları */
        .wp-bars {
            display: grid;
            gap: var(--space-4);
        }

        .wp-bar-row {
            display: grid;
            grid-template-columns: 78px minmax(0, 1fr) 42px;
            align-items: center;
            gap: var(--space-3);
        }

        .wp-bar-label {
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--text-muted);
        }

        .wp-bar {
            height: 14px;
            border-radius: 50px;
            background: var(--surface-3);
            overflow: hidden;
        }

        .wp-bar span {
            display: block;
            height: 100%;
            border-radius: 50px;
            background: var(--text-gray);
        }

        .wp-bar-val {
            font-size: var(--text-base);
            font-weight: 800;
            color: var(--text-muted);
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .wp-bar-row.is-lead .wp-bar-label,
        .wp-bar-row.is-lead .wp-bar-val {
            color: var(--text-primary);
        }

        .wp-bar-row.is-lead .wp-bar span {
            background: linear-gradient(90deg, #22c55e, #15803d);
        }

        .wp-bar-note {
            font-size: var(--text-xs);
            color: var(--text-gray);
            line-height: 1.5;
            margin: 0;
        }

        .wp-tech {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: var(--space-5);
        }

        .wp-tech-card {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: var(--space-4);
            padding: var(--space-5);
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: transform var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .wp-tech-card::before {
            content: '';
            position: absolute;
            inset: 0 0 auto 0;
            height: 3px;
            background: var(--tint, var(--wp-gradient));
            transform: scaleX(0);
            transition: transform var(--transition-normal) var(--ease-out);
        }

        .wp-tech-card:hover {
            transform: translateY(-4px);
            background: var(--surface-2);
        }

        .wp-tech-card:hover::before {
            transform: scaleX(1);
        }

        .wp-tech-icon {
            width: 46px;
            height: 46px;
            flex-shrink: 0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            color: #fff;
            background: var(--tint, var(--wp-gradient));
        }

        .wp-tech-card h4 {
            font-size: var(--text-lg);
            font-weight: 700;
            letter-spacing: -0.015em;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .wp-tech-card p {
            font-size: var(--text-sm);
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* ===== Sık sorulan sorular ===== */
        /* Solda görsel + başlık, sağda soru listesi */
        .wp-faq-layout {
            display: grid;
            grid-template-columns: minmax(0, 330px) minmax(0, 1fr);
            gap: var(--space-8);
            /* Sol sutun listeyle ayni yuksekligi alsin */
            align-items: stretch;
        }

        .wp-faq-aside {
            display: flex;
            flex-direction: column;
        }

        .wp-head.is-left {
            text-align: left;
            max-width: 100%;
            margin: 0 0 var(--space-5);
        }

        .wp-faq-visual {
            /* Sabit yukseklik yerine kalan bosluk: liste uzayip kisaldikca hizali kalir */
            flex: 1 1 0;
            min-height: 120px;
            width: 100%;
            max-width: 330px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-4) 0;
        }

        .wp-faq-visual svg {
            width: 100%;
            height: 100%;
            max-height: 240px;
            display: block;
        }

        /* "Sorunuz yoksa bize yazın" kutusu */
        .wp-faq-help {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin-top: var(--space-5);
            padding: var(--space-4);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--surface-1);
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .wp-faq-help:hover {
            border-color: var(--wp-accent-line);
            background: var(--surface-2);
        }

        .wp-faq-help i {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--wp-gradient);
            color: #fff;
            font-size: 15px;
        }

        .wp-faq-help b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .wp-faq-help span {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        .wp-faq {
            display: grid;
            gap: var(--space-3);
        }

        .wp-faq-item {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--surface-1);
            overflow: hidden;
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .wp-faq-item[open] {
            border-color: var(--wp-accent-line);
            background: var(--surface-2);
        }

        .wp-faq-item summary {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            cursor: pointer;
            font-size: var(--text-md);
            font-weight: 600;
            color: var(--text-primary);
            list-style: none;
        }

        /* Soru ikonu - konuya özel satır içi SVG */
        .wp-faq-icon {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--wp-accent-soft);
            border: 1px solid var(--wp-accent-line);
            color: var(--wp-accent-light);
            transition: background-color var(--transition-normal) var(--ease-out),
                color var(--transition-normal) var(--ease-out);
        }

        .wp-faq-icon svg {
            width: 19px;
            height: 19px;
            display: block;
        }

        .wp-faq-item[open] .wp-faq-icon {
            background: var(--wp-gradient);
            border-color: transparent;
            color: #fff;
        }

        /* Soru metni ile artı işareti arasını doldurur */
        .wp-faq-q {
            flex: 1;
            min-width: 0;
        }

        /* Tarayıcının varsayılan üçgen işaretini kaldır */
        .wp-faq-item summary::-webkit-details-marker {
            display: none;
        }

        .wp-faq-item summary::marker {
            content: '';
        }

        .wp-faq-item summary:hover {
            color: var(--wp-accent-light);
        }

        .wp-faq-item summary:focus-visible {
            outline: none;
            box-shadow: var(--focus-ring);
        }

        .wp-faq-sign {
            width: 28px;
            height: 28px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--wp-accent-soft);
            color: var(--wp-accent-light);
            font-size: 12px;
            transition: transform var(--transition-normal) var(--ease-out);
        }

        .wp-faq-item[open] .wp-faq-sign {
            transform: rotate(45deg);
        }

        .wp-faq-body {
            /* Cevap, sorunun metniyle aynı hizadan başlasın (ikon + boşluk kadar içeride) */
            padding: 0 var(--space-5) var(--space-5) calc(var(--space-5) + 38px + var(--space-3));
            font-size: var(--text-base);
            color: var(--text-muted);
            line-height: 1.75;
            max-width: 72ch;
        }

        /* ===== Kapanış çağrısı ===== */
        .wp-final {
            position: relative;
            overflow: hidden;
            padding: var(--space-8) 0;
            background: var(--wp-gradient);
            color: #fff;
            text-align: center;
        }

        .wp-final::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 100% at 50% 0%, rgba(255, 255, 255, 0.2) 0%, transparent 62%),
                radial-gradient(ellipse 50% 80% at 88% 100%, rgba(0, 0, 0, 0.18) 0%, transparent 60%);
            pointer-events: none;
        }

        .wp-final>.container {
            position: relative;
            z-index: 1;
        }

        .wp-final h3 {
            font-size: var(--text-2xl);
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: var(--space-3);
            text-wrap: balance;
        }

        .wp-final p {
            font-size: var(--text-md);
            opacity: 0.92;
            margin-bottom: var(--space-6);
        }

        .wp-final-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: var(--space-3);
        }

        .wp-final-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 32px;
            border-radius: var(--radius-md);
            background: #fff;
            color: var(--wp-accent-dark);
            font-size: var(--text-md);
            font-weight: 700;
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.2);
            transition: transform var(--transition-fast) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .wp-final-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.28);
        }

        .wp-final-link {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 15px 26px;
            border-radius: var(--radius-md);
            border: 1px solid rgba(255, 255, 255, 0.45);
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            font-size: var(--text-md);
            font-weight: 600;
            transition: background-color var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out);
        }

        .wp-final-link:hover {
            background: rgba(255, 255, 255, 0.22);
            border-color: #fff;
        }

        /* ===== Duyarlılık ===== */
        @media (max-width: 1100px) {
            .wp-included-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .wp-spotlight {
                grid-template-columns: 1fr;
                gap: var(--space-6);
                padding: var(--space-6);
            }

            .wp-spotlight p {
                max-width: 100%;
            }
        }

        @media (max-width: 992px) {
            .wp-lead {
                max-width: 100%;
            }

            .wp-tech {
                grid-template-columns: 1fr;
            }

            /* Görsel ve başlık üste, sorular altına */
            .wp-faq-layout {
                grid-template-columns: 1fr;
                gap: var(--space-6);
                justify-items: center;
            }

            .wp-faq-aside {
                max-width: 460px;
                width: 100%;
            }

            .wp-head.is-left {
                text-align: center;
            }

            .wp-faq-visual {
                flex: 0 0 auto;
                margin: 0 auto;
            }

            .wp-faq-visual svg {
                height: auto;
            }

            .wp-faq {
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            .wp-plans,
            .wp-plans[data-count="2"],
            .wp-plans[data-count="3"] {
                grid-template-columns: 1fr;
            }

            .wp-included-grid {
                grid-template-columns: 1fr;
            }

            .wp-bar-row {
                grid-template-columns: 66px minmax(0, 1fr) 34px;
                gap: var(--space-2);
            }

            .wp-final-actions {
                flex-direction: column;
            }

            .wp-final-actions>* {
                width: 100%;
                justify-content: center;
            }

            /* Dar ekranda cevabı ikon hizasında girintilemek yer israfı olur */
            .wp-faq-body {
                padding-left: var(--space-5);
            }

            .wp-faq-item summary {
                padding-left: var(--space-4);
                padding-right: var(--space-4);
            }
        }
    </style>

    <div class="wp">

        <!-- Hero -->
        <section class="wp-hero">
            <div class="container">
                <div class="wp-hero-grid">
                    <div class="wp-hero-text">
                        <span class="wp-eyebrow"><i class="fab fa-wordpress"></i> WordPress 6.x İçin Ayarlandı</span>
                        <h1 class="wp-title"><span>WordPress Hosting</span> Paketleri</h1>
                        <p class="wp-lead">
                            WP Toolkit ile kurulum, güncelleme ve deneme kopyası tek panelden.
                            LiteSpeed Cache, günlük yedek ve ücretsiz SSL her pakette standart.
                        </p>

                        <div class="wp-stack">
                            <span class="wp-chip"><i class="fas fa-toolbox is-cpanel"></i> WP Toolkit</span>
                            <span class="wp-chip"><i class="fas fa-bolt is-litespeed"></i> LiteSpeed Cache</span>
                            <span class="wp-chip"><i class="fas fa-cloud-arrow-up is-jetbackup"></i> JetBackup</span>
                            <span class="wp-chip"><i class="fab fa-wordpress is-wpcore"></i> WordPress 6.x</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kategori sekmeleri -->
            <div class="wp-tabs-wrap">
                <div class="container">
                    <div class="wp-tabs">
                        <a href="store.php" class="wp-tab"><i class="fas fa-th-large"></i> Tüm Ürünler</a>
                        <a href="store.php?group=wordpress-hosting" class="wp-tab is-active"><i class="fab fa-wordpress"></i> Wordpress
                            Hosting</a>
                        <?php foreach ($allGroups as $g):
                            if ($g['slug'] === 'wordpress-hosting') {
                                continue;
                            }
                            $gCount = Database::fetchColumn("SELECT COUNT(*) FROM products WHERE group_id = ? AND is_active = 1", [$g['id']]);
                            if ($gCount <= 0) {
                                continue;
                            }
                            ?>
                            <a href="store.php?group=<?= htmlspecialchars($g['slug']) ?>" class="wp-tab">
                                <i class="fas <?= $typeConfig[$g['type'] ?? 'hosting']['icon'] ?? 'fa-box' ?>"></i>
                                <?= htmlspecialchars($g['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Paketler -->
        <section class="wp-section" id="paketler">
            <div class="container">
                <div class="wp-head">
                    <span class="wp-badge"><i class="fab fa-wordpress"></i> Wordpress Hosting Paketleri</span>
                    <h2>Sitenize Uygun <span>WordPress Planı</span></h2>
                    <p>Tüm paketlerde WP Toolkit, ücretsiz SSL ve günlük yedekleme standart olarak sunulur.</p>
                </div>

                <?php if (empty($products)): ?>
                    <div class="wp-empty">
                        <i class="fab fa-wordpress"></i>
                        <h3>Ürün Bulunamadı</h3>
                        <p>Bu kategoride henüz ürün bulunmuyor.</p>
                    </div>
                <?php else:
                    $productCount = count($products);

                    // En az bir pakette yıllık indirim varsa, olmayanlarda rozet
                    // yüksekliği kadar boşluk bırakılır; kartlar hizalı kalır.
                    $anySaving = false;
                    foreach ($products as $p) {
                        $m = (float) ($p['price_monthly'] ?? 0);
                        $a = (float) ($p['price_annually'] ?? 0);
                        if ($m > 0 && $a > 0 && $a < $m * 12) {
                            $anySaving = true;
                            break;
                        }
                    }
                    ?>
                    <div class="wp-plans" data-count="<?= $productCount ?>">
                        <?php
                        // Öne çıkan ürün işaretliyse onu, değilse ortadaki paketi vurgula
                        $popularIndex = -1;
                        foreach ($products as $i => $p) {
                            if (!empty($p['is_featured'])) {
                                $popularIndex = $i;
                                break;
                            }
                        }
                        if ($popularIndex === -1 && $productCount > 2) {
                            $popularIndex = (int) floor(($productCount - 1) / 2);
                        }

                        foreach ($products as $index => $product):
                            $isPopular = ($index === $popularIndex);
                            $price = (float) ($product['price_monthly'] ?? 0);
                            $annual = (float) ($product['price_annually'] ?? 0);
                            $setup = (float) ($product['setup_fee'] ?? 0);

                            // Açıklama satırlarını teknik özet ve özellik listesi olarak ayır
                            $lines = [];
                            foreach (preg_split('/\r\n|\r|\n/', (string) ($product['description'] ?? '')) as $line) {
                                $line = trim($line);
                                if ($line !== '') {
                                    $lines[] = $line;
                                }
                            }

                            $specs = [];
                            $usedLines = [];
                            foreach ($wpSpecTypes as $key => $spec) {
                                if (count($specs) >= 4) {
                                    break;
                                }
                                foreach ($lines as $li => $line) {
                                    if (isset($usedLines[$li]) || !preg_match($spec['match'], $line)) {
                                        continue;
                                    }
                                    $value = $wpSpecValue($line);
                                    if ($value === '') {
                                        continue;
                                    }
                                    $specs[] = ['label' => $spec['label'], 'icon' => $spec['icon'], 'value' => $value];
                                    $usedLines[$li] = true;
                                    break;
                                }
                            }

                            $features = [];
                            foreach ($lines as $li => $line) {
                                if (!isset($usedLines[$li])) {
                                    $features[] = $line;
                                }
                            }
                            if (empty($features) && empty($specs)) {
                                $features = [
                                    'WP Toolkit Yönetimi',
                                    'LiteSpeed Cache',
                                    'JetBackup Yedekleme',
                                    'Ücretsiz SSL Sertifikası',
                                    '7/24 Teknik Destek',
                                ];
                            }

                            // Yıllık ödemede aylığa göre kazanç
                            $savePercent = 0;
                            if ($price > 0 && $annual > 0 && $annual < $price * 12) {
                                $savePercent = (int) round((1 - ($annual / ($price * 12))) * 100);
                            }

                            $clip = count($features) > 6;
                            $listId = 'wp-feat-' . (int) $product['id'];
                            ?>
                            <article class="wp-plan <?= $isPopular ? 'is-popular' : '' ?>">
                                <?php if ($isPopular): ?>
                                    <div class="wp-ribbon"><i class="fas fa-fire"></i> En Popüler</div>
                                <?php endif; ?>

                                <div class="wp-plan-top">
                                    <div class="wp-plan-name"><?= htmlspecialchars($product['name']) ?></div>
                                    <div class="wp-plan-group">Wordpress Hosting</div>

                                    <?php if ($price > 0): ?>
                                        <div class="wp-price">
                                            <span class="cur">₺</span>
                                            <span class="val"><?= number_format(floor($price), 0, ',', '.') ?></span>
                                            <span class="per">/ay</span>
                                        </div>
                                        <?php if ($setup > 0): ?>
                                            <div class="wp-price-note">
                                                + ₺<?= number_format($setup, 0, ',', '.') ?> tek seferlik kurulum
                                            </div>
                                        <?php else: ?>
                                            <div class="wp-price-note">Kurulum ücreti yok</div>
                                        <?php endif; ?>
                                        <?php if ($savePercent > 0): ?>
                                            <span class="wp-save">
                                                <i class="fas fa-tag"></i> Yıllık ödemede %<?= $savePercent ?> indirim
                                            </span>
                                        <?php elseif ($anySaving): ?>
                                            <span class="wp-save is-placeholder" aria-hidden="true">
                                                <i class="fas fa-tag"></i> &nbsp;
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="wp-price-ask">Fiyat için görüşelim</div>
                                        <div class="wp-price-note">İhtiyacınıza göre özel teklif hazırlıyoruz</div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($specs)): ?>
                                    <div class="wp-specs">
                                        <?php foreach ($specs as $spec): ?>
                                            <div class="wp-spec">
                                                <i class="fas <?= $spec['icon'] ?>"></i>
                                                <div>
                                                    <b><?= htmlspecialchars($spec['value']) ?></b>
                                                    <span><?= htmlspecialchars($spec['label']) ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="wp-plan-body">
                                    <?php if (!empty($features)): ?>
                                        <ul class="wp-features <?= $clip ? 'is-clipped' : '' ?>" id="<?= $listId ?>">
                                            <?php foreach ($features as $feature): ?>
                                                <li><i class="fas fa-check"></i> <?= htmlspecialchars($feature) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php if ($clip): ?>
                                            <button type="button" class="wp-more" data-target="<?= $listId ?>"
                                                aria-expanded="false" aria-controls="<?= $listId ?>">
                                                + <?= count($features) - 6 ?> özellik daha
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <a class="wp-cta" href="/client/order-configure.php?id=<?= (int) $product['id'] ?>">
                                        <i class="fas fa-shopping-cart"></i> Sipariş Ver
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Her pakette standart -->
        <section class="wp-section is-alt">
            <div class="container">
                <div class="wp-head">
                    <span class="wp-badge"><i class="fas fa-check-double"></i> Pakete Dahil</span>
                    <h2>Hepsi <span>Her Pakette Standart</span></h2>
                    <p>Aşağıdakiler için ek ücret ödemezsiniz; en küçük pakette de aynen geçerli.</p>
                </div>

                <div class="wp-included">
                    <div class="wp-included-grid">
                        <div class="wp-inc">
                            <i class="fas fa-cogs"></i>
                            <div>
                                <b>WP Toolkit</b>
                                <span>Kurulum, güncelleme, staging</span>
                            </div>
                        </div>
                        <div class="wp-inc">
                            <i class="fas fa-certificate"></i>
                            <div>
                                <b>Ücretsiz SSL</b>
                                <span>Let's Encrypt, otomatik yenileme</span>
                            </div>
                        </div>
                        <div class="wp-inc">
                            <i class="fas fa-shield-halved"></i>
                            <div>
                                <b>Imunify360</b>
                                <span>WordPress zafiyet taraması</span>
                            </div>
                        </div>
                        <div class="wp-inc">
                            <i class="fas fa-clock-rotate-left"></i>
                            <div>
                                <b>Günlük Yedek</b>
                                <span>JetBackup ile tek tık geri dönüş</span>
                            </div>
                        </div>
                        <div class="wp-inc">
                            <i class="fab fa-php"></i>
                            <div>
                                <b>PHP Selector</b>
                                <span>7.4 – 8.3 arası seçim</span>
                            </div>
                        </div>
                        <div class="wp-inc">
                            <i class="fas fa-database"></i>
                            <div>
                                <b>MySQL / MariaDB</b>
                                <span>phpMyAdmin erişimi</span>
                            </div>
                        </div>
                        <div class="wp-inc">
                            <i class="fab fa-wordpress"></i>
                            <div>
                                <b>Otomatik Güncelleme</b>
                                <span>Çekirdek, tema ve eklenti</span>
                            </div>
                        </div>
                        <div class="wp-inc">
                            <i class="fas fa-headset"></i>
                            <div>
                                <b>7/24 Destek</b>
                                <span>Uzman teknik ekip</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Teknoloji altyapısı -->
        <section class="wp-section">
            <div class="container">
                <div class="wp-head">
                    <span class="wp-badge"><i class="fas fa-microchip"></i> Teknoloji Altyapısı</span>
                    <h2>Hızın Geldiği <span>Yer</span></h2>
                    <p>Aynı WordPress, önbellek katmanıyla bambaşka bir hızda çalışıyor.</p>
                </div>

                <!-- Vitrin: LiteSpeed -->
                <div class="wp-spotlight">
                    <div class="wp-spotlight-text">
                        <span class="wp-spotlight-tag"><i class="fas fa-bolt"></i> Önbellek Katmanı</span>
                        <h3>LiteSpeed Cache</h3>
                        <p>
                            Sunucu seviyesinde çalışan önbellek. Sayfayı her istekte yeniden üretmek
                            yerine hazır halini sunar; PHP ve veritabanı yükü belirgin şekilde düşer.
                        </p>
                        <ul class="wp-spotlight-points">
                            <li><i class="fas fa-check"></i> HTTP/3 &amp; QUIC</li>
                            <li><i class="fas fa-check"></i> Nesne önbelleği</li>
                            <li><i class="fas fa-check"></i> Görsel optimizasyonu</li>
                            <li><i class="fas fa-check"></i> Kritik CSS üretimi</li>
                        </ul>
                    </div>

                    <div class="wp-bars">
                        <div class="wp-bar-row">
                            <span class="wp-bar-label">Önbelleksiz</span>
                            <div class="wp-bar"><span style="width: 17%"></span></div>
                            <span class="wp-bar-val">1×</span>
                        </div>
                        <div class="wp-bar-row is-lead">
                            <span class="wp-bar-label">LiteSpeed Cache</span>
                            <div class="wp-bar"><span style="width: 100%"></span></div>
                            <span class="wp-bar-val">6×</span>
                        </div>
                        <p class="wp-bar-note">
                            Varsayılan tema kurulumunda ölçülen karşılaştırmalı istek/saniye değerleri.
                            Gerçek kazanç eklenti sayınıza ve tema yapınıza göre değişir.
                        </p>
                    </div>
                </div>

                <div class="wp-tech">
                    <div class="wp-tech-card" style="--tint: linear-gradient(135deg, #f97316, #ea580c);">
                        <div class="wp-tech-icon"><i class="fas fa-cogs"></i></div>
                        <div>
                            <h4>WP Toolkit</h4>
                            <p>Kurulum, sürüm yönetimi, eklenti güncellemesi ve deneme kopyası tek ekrandan.</p>
                        </div>
                    </div>
                    <div class="wp-tech-card" style="--tint: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                        <div class="wp-tech-icon"><i class="fas fa-cloud-arrow-up"></i></div>
                        <div>
                            <h4>JetBackup</h4>
                            <p>Otomatik günlük yedekleme. Tek dosyayı da tüm hesabı da tek tıkla geri yükleyin.</p>
                        </div>
                    </div>
                    <div class="wp-tech-card" style="--tint: linear-gradient(135deg, #21759b, #16537a);">
                        <div class="wp-tech-icon"><i class="fab fa-wordpress"></i></div>
                        <div>
                            <h4>WordPress 6.x</h4>
                            <p>Güncel çekirdek sürümü, PHP 8.3 uyumu ve otomatik güvenlik güncellemeleri.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sık sorulanlar -->
        <section class="wp-section is-alt">
            <div class="container">
                <div class="wp-faq-layout">
                    <div class="wp-faq-aside">
                        <div class="wp-head is-left">
                            <span class="wp-badge"><i class="fas fa-circle-question"></i> Sık Sorulanlar</span>
                            <h2>Aklınıza <span>Takılanlar</span></h2>
                            <p>Satın almadan önce en çok merak edilenleri derledik.</p>
                        </div>

                        <div class="wp-faq-visual">
                            <!-- Soru-cevap balonları; renkler tema değişkenlerinden gelir -->
                            <svg viewBox="0 0 320 272" fill="none" aria-hidden="true">
                                <defs>
                                    <linearGradient id="wpQmark" x1="0" y1="0" x2="1" y2="1">
                                        <stop offset="0" stop-color="var(--primary-light)" />
                                        <stop offset="1" stop-color="var(--primary-dark)" />
                                    </linearGradient>
                                    <radialGradient id="wpHalo" cx="0.5" cy="0.5" r="0.5">
                                        <stop offset="0" stop-color="var(--primary)" stop-opacity="0.20" />
                                        <stop offset="1" stop-color="var(--primary)" stop-opacity="0" />
                                    </radialGradient>
                                </defs>

                                <ellipse cx="160" cy="136" rx="152" ry="122" fill="url(#wpHalo)" />

                                <!-- Cevap balonu (arkada) -->
                                <path d="M276 242 v22 l-26 -22 z" fill="var(--surface-2)" stroke="var(--border-color)"
                                    stroke-width="2" stroke-linejoin="round" />
                                <rect x="104" y="150" width="200" height="92" rx="22" fill="var(--surface-2)"
                                    stroke="var(--border-color)" stroke-width="2" />
                                <rect x="132" y="172" width="140" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.55" />
                                <rect x="132" y="194" width="118" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.4" />
                                <rect x="132" y="216" width="78" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.28" />

                                <!-- Soru balonu (önde) -->
                                <path d="M44 126 v22 l26 -22 z" fill="color-mix(in srgb, var(--primary) 12%, transparent)" stroke="var(--primary)"
                                    stroke-width="2" stroke-linejoin="round" />
                                <rect x="16" y="26" width="196" height="100" rx="24" fill="color-mix(in srgb, var(--primary) 12%, transparent)"
                                    stroke="var(--primary)" stroke-width="2" />
                                <rect x="44" y="56" width="112" height="9" rx="4.5" fill="var(--primary-light)" opacity="0.85" />
                                <rect x="44" y="78" width="140" height="9" rx="4.5" fill="var(--primary-light)" opacity="0.6" />
                                <rect x="44" y="100" width="84" height="9" rx="4.5" fill="var(--primary-light)" opacity="0.4" />

                                <!-- Soru işareti rozeti -->
                                <circle cx="234" cy="44" r="28" fill="url(#wpQmark)" />
                                <text x="234" y="45" text-anchor="middle" dominant-baseline="central" fill="#fff"
                                    font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="34"
                                    font-weight="800">?</text>

                                <!-- Serpiştirilmiş noktalar -->
                                <circle cx="292" cy="104" r="5" fill="var(--primary)" opacity="0.5" />
                                <circle cx="306" cy="126" r="3" fill="var(--primary)" opacity="0.3" />
                                <circle cx="24" cy="186" r="4" fill="var(--primary)" opacity="0.35" />
                                <circle cx="44" cy="210" r="6" fill="var(--primary)" opacity="0.2" />
                            </svg>
                        </div>

                        <a href="contact.php" class="wp-faq-help">
                            <i class="fas fa-headset"></i>
                            <div>
                                <b>Sorunuz listede yok mu?</b>
                                <span>Destek ekibimize yazın, aynı gün dönelim.</span>
                            </div>
                        </a>
                    </div>

                <div class="wp-faq">
                    <details class="wp-faq-item">
                        <summary>
                            <span class="wp-faq-icon">
                                <!-- Kontrol paneli: bölmeli ekran -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="3.5" width="18" height="17" rx="2.5" />
                                    <path d="M3 9.5h18" />
                                    <path d="M9.5 20.5v-11" />
                                </svg>
                            </span>
                            <span class="wp-faq-q">WordPress kurulumunu siz mi yapıyorsunuz?</span>
                            <span class="wp-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="wp-faq-body">
                            WP Toolkit üzerinden tek tıkla kurabilirsiniz; isterseniz hesap açılışında biz kuralım.
                            Tema, eklenti ve kullanıcı ayarları kurulumdan sonra tamamen sizin kontrolünüzdedir.
                        </div>
                    </details>

                    <details class="wp-faq-item">
                        <summary>
                            <span class="wp-faq-icon">
                                <!-- Yedekleme: geri sarma oku + saat ibresi -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3.2 12a8.8 8.8 0 1 0 2.9-6.5" />
                                    <path d="M3 4.2v4.6h4.6" />
                                    <path d="M12 8.2V12l2.9 1.7" />
                                </svg>
                            </span>
                            <span class="wp-faq-q">Yedeklerim ne sıklıkla alınıyor?</span>
                            <span class="wp-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="wp-faq-body">
                            JetBackup ile günlük otomatik yedek alınır. Tek bir dosyayı, veritabanını ya da tüm
                            WordPress kurulumunu panel üzerinden tek tıkla geri yükleyebilirsiniz.
                        </div>
                    </details>

                    <details class="wp-faq-item">
                        <summary>
                            <span class="wp-faq-icon">
                                <!-- SSL: asma kilit -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="4" y="10.2" width="16" height="10.3" rx="2.4" />
                                    <path d="M8 10.2V7.1a4 4 0 0 1 8 0v3.1" />
                                    <path d="M12 14.4v2.2" />
                                </svg>
                            </span>
                            <span class="wp-faq-q">SSL sertifikası için ayrıca ödeme yapacak mıyım?</span>
                            <span class="wp-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="wp-faq-body">
                            Hayır. Let's Encrypt SSL sertifikası bütün paketlerde ücretsiz olarak sunulur ve
                            süresi dolmadan otomatik yenilenir.
                        </div>
                    </details>

                    <details class="wp-faq-item">
                        <summary>
                            <span class="wp-faq-icon">
                                <!-- Eklenti: yapboz parçası -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M10 4.2h4a1 1 0 0 1 1 1v1.3a1.7 1.7 0 1 0 3.4 0V5.2h.4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1h-1.3a1.7 1.7 0 1 0 0 3.4h1.3a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-1.3a1.7 1.7 0 1 0-3.4 0v1.3a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1h1.3a1.7 1.7 0 1 0 0-3.4H4a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1h4" />
                                </svg>
                            </span>
                            <span class="wp-faq-q">Eklenti ve tema kurmama sınır var mı?</span>
                            <span class="wp-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="wp-faq-body">
                            Hayır, dilediğiniz tema ve eklentiyi kurabilirsiniz. Yalnızca sunucu güvenliğini
                            riske atan ya da zararlı yazılım barındıran eklentiler engellenir.
                        </div>
                    </details>

                    <details class="wp-faq-item">
                        <summary>
                            <span class="wp-faq-icon">
                                <!-- İşletim sistemi: sunucu rafı -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="4" width="18" height="7" rx="2.2" />
                                    <rect x="3" y="13" width="18" height="7" rx="2.2" />
                                    <path d="M7 7.5h.01" />
                                    <path d="M7 16.5h.01" />
                                </svg>
                            </span>
                            <span class="wp-faq-q">Sitem hangi altyapıda çalışacak?</span>
                            <span class="wp-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="wp-faq-body">
                            AlmaLinux 9 ve LiteSpeed üzerinde, NVMe SSD disklerle. PHP 8.3 varsayılan sürümdür,
                            güvenlik tarafında Imunify360 zararlı yazılım koruması çalışır.
                        </div>
                    </details>

                    <details class="wp-faq-item">
                        <summary>
                            <span class="wp-faq-icon">
                                <!-- Taşıma: karşılıklı transfer okları -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4 9h15" />
                                    <path d="m15.5 5.5 3.5 3.5-3.5 3.5" />
                                    <path d="M20 15H5" />
                                    <path d="M8.5 11.5 5 15l3.5 3.5" />
                                </svg>
                            </span>
                            <span class="wp-faq-q">Mevcut sitemi siz taşıyor musunuz?</span>
                            <span class="wp-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="wp-faq-body">
                            P3 ve P4 paketlerinde ücretsiz site taşıma hizmeti dahildir. Diğer paketler için
                            taşıma talebinizi destek ekibimize iletebilirsiniz.
                        </div>
                    </details>
                    </div>
                </div>
            </div>
        </section>

        <!-- Kapanış -->
        <section class="wp-final">
            <div class="container">
                <h3><i class="fab fa-wordpress"></i> WordPress Hosting ile Başlayın</h3>
                <p>WP Toolkit + LiteSpeed Cache + JetBackup ile hızlı WordPress</p>
                <div class="wp-final-actions">
                    <a href="#paketler" class="wp-final-btn">
                        <i class="fas fa-rocket"></i> Paketleri İncele
                    </a>
                    <a href="contact.php" class="wp-final-link">
                        <i class="fas fa-comments"></i> Önce Soru Sormak İsterim
                    </a>
                </div>
            </div>
        </section>
    </div>

    <script>
        // "+N özellik daha" düğmesi: listedeki gizli satırları açar
        document.querySelectorAll('.wp-more').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var list = document.getElementById(btn.dataset.target);
                if (!list) return;
                var expanded = list.classList.toggle('is-clipped') === false;
                btn.setAttribute('aria-expanded', String(expanded));
                btn.textContent = expanded
                    ? 'Daha az göster'
                    : '+ ' + (list.children.length - 6) + ' özellik daha';
            });
        });
    </script>

    <?php require_once __DIR__ . '/theme/includes/footer.php'; ?>

<?php elseif ($isBuilderPage): ?>

    <?php
    /**
     * Web Site Builder sayfası - ürün açıklamasından teknik özellikleri ayıkla.
     * "1000 MB Nvme Disk Alanı" gibi satırları vitrin kutucuğuna,
     * kalanları özellik listesine yollar.
     */
    $wbSpecTypes = [
        'site' => ['label' => 'Web Sitesi', 'icon' => 'fa-globe', 'match' => '/web\s?site|site\s?sayı/iu'],
        'sayfa' => ['label' => 'Sayfa Hakkı', 'icon' => 'fa-file-lines', 'match' => '/sayfa/iu'],
        'disk' => ['label' => 'Disk Alanı', 'icon' => 'fa-hard-drive', 'match' => '/disk|depolama/iu'],
        'traffic' => ['label' => 'Aylık Trafik', 'icon' => 'fa-right-left', 'match' => '/trafik|bant\s?geniş/iu'],
    ];

    /** Satırdan sayısal değeri + birimini çek: "1024 MB", "1 Core", "Limitsiz" */
    $wbSpecValue = static function (string $line): string {
        if (preg_match('/\b(limitsiz|sınırsız|unlimited)\b/iu', $line)) {
            return 'Limitsiz';
        }
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(TB|GB|MB|KB|Core|Adet|vCPU)?/iu', $line, $m)) {
            return trim($m[1] . ' ' . ($m[2] ?? ''));
        }
        return '';
    };
    ?>

    <style>
        /* ==========================================
           Web Site Builder - sayfaya özel değişkenler
           Vurgu rengi ve yüzeyler global tema
           token'larından gelir.
           ========================================== */
        .wb {
            --wb-accent: var(--primary);
            --wb-accent-light: var(--primary);
            --wb-accent-dark: var(--primary-dark);
            --wb-accent-soft: color-mix(in srgb, var(--primary) 12%, transparent);
            --wb-accent-line: color-mix(in srgb, var(--primary) 28%, transparent);
            --wb-gradient: var(--gradient-primary);
        }

        /* ===== Hero ===== */
        .wb-hero {
            position: relative;
            overflow: hidden;
            background: var(--gradient-hero);
            padding: var(--space-6) 0 0;
        }

        .wb-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 55% 50% at 12% 30%, color-mix(in srgb, var(--primary) 22%, transparent) 0%, transparent 62%),
                radial-gradient(ellipse 45% 45% at 88% 10%, color-mix(in srgb, var(--secondary) 16%, transparent) 0%, transparent 58%);
            pointer-events: none;
        }

        .wb-hero>.container {
            position: relative;
            z-index: 1;
        }

        /* Tek kolon, ortalanmis: hero'da ayrica gorsel yok */
        .wb-hero-grid {
            max-width: 780px;
            margin: 0 auto;
            padding-bottom: var(--space-6);
            text-align: center;
        }

        .wb-hero-text .wb-lead {
            margin-left: auto;
            margin-right: auto;
        }

        .wb-hero-text .wb-stack {
            justify-content: center;
        }

        .wb-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 50px;
            background: var(--wb-accent-soft);
            border: 1px solid var(--wb-accent-line);
            color: var(--wb-accent-light);
            font-size: var(--text-sm);
            font-weight: 600;
            margin-bottom: var(--space-4);
        }

        .wb-title {
            /* Ürün sayfası; anasayfa kadar iri olmasına gerek yok */
            font-size: clamp(32px, 3.2vw + 16px, 50px);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -0.035em;
            margin-bottom: var(--space-3);
            color: var(--text-primary);
            text-wrap: balance;
        }

        .wb-title span {
            background: var(--wb-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .wb-lead {
            font-size: var(--text-md);
            color: var(--text-muted);
            line-height: 1.65;
            max-width: 48ch;
            margin-bottom: var(--space-5);
            text-wrap: pretty;
        }

        /* Teknoloji rozetleri - 2x2 kutu yerine tek sıra çip;
           hero yüksekliğini ~110px kısaltır, detaylar aşağıdaki
           teknoloji bölümünde zaten anlatılıyor. */
        .wb-stack {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .wb-chip {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 7px 14px 7px 7px;
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .wb-chip:hover {
            background: var(--surface-2);
            border-color: var(--wb-accent-line);
        }

        .wb-chip i {
            width: 26px;
            height: 26px;
            flex-shrink: 0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #fff;
        }

        .wb-chip i.is-cpanel {
            background: linear-gradient(135deg, #ff6c2c, #e8590c);
        }

        .wb-chip i.is-litespeed {
            background: linear-gradient(135deg, #22c55e, #15803d);
        }

        .wb-chip i.is-jetbackup {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .wb-chip i.is-alma {
            background: linear-gradient(135deg, #38bdf8, #0369a1);
        }

        /* ===== Terminal ===== */
        /* Surum listesi: cubuk sutunu yok */
        .wb-bars.is-stack .wb-bar-row {
            grid-template-columns: minmax(0, 1fr) auto;
        }

        /* Site kurucu cip renkleri */
        .wb-chip i.is-editor {
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
        }

        .wb-chip i.is-sablon {
            background: linear-gradient(135deg, #ec4899, #be185d);
        }

        .wb-chip i.is-mobil {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
        }

        .wb-chip i.is-ssl {
            background: linear-gradient(135deg, #22c55e, #15803d);
        }

        /* ===== Kategori sekmeleri ===== */
        .wb-tabs-wrap {
            position: relative;
            border-top: 1px solid var(--border-color);
            background: color-mix(in srgb, var(--bg-primary) 65%, transparent);
            backdrop-filter: blur(12px);
        }

        .wb-tabs {
            display: flex;
            gap: var(--space-2);
            overflow-x: auto;
            padding: var(--space-3) var(--space-4);
            scrollbar-width: none;
            /* Dokunmatik ekranda sekmeler hizaya otursun */
            scroll-snap-type: x proximity;
        }

        .wb-tabs::-webkit-scrollbar {
            display: none;
        }

        .wb-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            scroll-snap-align: start;
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            background: var(--surface-1);
            color: var(--text-muted);
            font-size: var(--text-sm);
            font-weight: 600;
            white-space: nowrap;
            transition: all var(--transition-normal) var(--ease-out);
        }

        .wb-tab:hover {
            color: var(--text-primary);
            border-color: var(--wb-accent-line);
            background: var(--surface-2);
        }

        .wb-tab.is-active {
            background: var(--wb-gradient);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 6px 18px color-mix(in srgb, var(--primary) 35%, transparent);
        }

        /* ===== Bölüm başlıkları ===== */
        .wb-section {
            padding: var(--section-padding) 0;
        }

        /* Paketler bölümü hemen sekme şeridinin altında;
           sekmeler zaten ayırıcı, üstte tam boşluğa gerek yok */
        .wb-hero+.wb-section {
            padding-top: var(--space-7);
        }

        .wb-section.is-alt {
            background: var(--bg-primary);
        }

        .wb-head {
            text-align: center;
            max-width: 680px;
            margin: 0 auto var(--space-7);
        }

        .wb-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 50px;
            background: var(--wb-accent-soft);
            border: 1px solid var(--wb-accent-line);
            color: var(--wb-accent-light);
            font-size: var(--text-sm);
            font-weight: 600;
            margin-bottom: var(--space-4);
        }

        .wb-head h2 {
            font-size: var(--text-3xl);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.15;
            color: var(--text-primary);
            text-wrap: balance;
        }

        .wb-head h2 span {
            background: var(--wb-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .wb-head p {
            margin-top: var(--space-3);
            font-size: var(--text-md);
            color: var(--text-muted);
            text-wrap: pretty;
        }

        /* ===== Paket kartları ===== */
        .wb-plans {
            display: grid;
            /* Sutun sayisi paket adedinden gelir; auto-fit reflow yapmaz */
            grid-template-columns: repeat(var(--plan-cols, 4), minmax(0, 1fr));
            gap: var(--space-5);
            /* Kartlar eşit yükseklikte olsun, sipariş butonları hizalansın */
            align-items: stretch;
        }

        .wb-plans[data-count="1"] {
            --plan-cols: 1;
            max-width: 420px;
            margin-inline: auto;
        }

        .wb-plans[data-count="2"] {
            --plan-cols: 2;
        }

        .wb-plans[data-count="3"] {
            --plan-cols: 3;
        }

        @media (max-width: 1180px) {
            .wb-plans[data-count] {
                --plan-cols: 2;
            }
        }

        @media (max-width: 680px) {
            .wb-plans[data-count] {
                --plan-cols: 1;
            }
        }

        /* Az paket varsa ortala, kartlar aşırı genişlemesin */
        .wb-plans[data-count="1"] {
            grid-template-columns: minmax(300px, 420px);
            justify-content: center;
        }

        .wb-plans[data-count="2"] {
            grid-template-columns: repeat(2, minmax(300px, 430px));
            justify-content: center;
        }

        .wb-plans[data-count="3"] {
            grid-template-columns: repeat(3, minmax(280px, 400px));
            justify-content: center;
        }

        .wb-plan {
            position: relative;
            display: flex;
            flex-direction: column;
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: transform var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .wb-plan:hover {
            transform: translateY(-6px);
            border-color: var(--wb-accent-line);
            box-shadow: var(--shadow-lg);
        }

        .wb-plan.is-popular {
            border-color: var(--wb-accent);
            box-shadow: 0 0 0 1px var(--wb-accent), 0 24px 48px -18px color-mix(in srgb, var(--primary) 45%, transparent);
        }

        .wb-ribbon {
            position: absolute;
            top: 0;
            right: var(--space-5);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 0 0 10px 10px;
            background: var(--wb-gradient);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .wb-plan-top {
            padding: var(--space-6) var(--space-5) var(--space-5);
            border-bottom: 1px solid var(--border-color);
        }

        .wb-plan-name {
            font-size: var(--text-xl);
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .wb-plan-group {
            font-size: var(--text-sm);
            color: var(--text-muted);
            margin-bottom: var(--space-5);
        }

        .wb-price {
            display: flex;
            align-items: baseline;
            gap: 4px;
            /* Rakamlar kartlar arasında hizalı dursun */
            font-variant-numeric: tabular-nums;
        }

        .wb-price .cur {
            font-size: var(--text-lg);
            font-weight: 700;
            color: var(--wb-accent);
        }

        .wb-price .val {
            font-size: clamp(34px, 3vw + 22px, 46px);
            font-weight: 800;
            letter-spacing: -0.04em;
            line-height: 1;
            color: var(--text-primary);
        }

        .wb-price .per {
            font-size: var(--text-base);
            color: var(--text-muted);
            font-weight: 500;
        }

        .wb-price-ask {
            font-size: var(--text-xl);
            font-weight: 700;
            color: var(--text-primary);
        }

        .wb-price-note {
            margin-top: var(--space-2);
            font-size: var(--text-sm);
            color: var(--text-muted);
        }

        .wb-save {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: var(--space-3);
            padding: 5px 12px;
            border-radius: 50px;
            background: color-mix(in srgb, #22c55e 15%, transparent);
            border: 1px solid color-mix(in srgb, #22c55e 32%, transparent);
            color: #4ade80;
            font-size: var(--text-xs);
            font-weight: 700;
        }

        /* Yıllık indirimi olmayan pakette yeri boş dursun ki
           kartlar arasında teknik kutular aynı hizada başlasın */
        .wb-save.is-placeholder {
            visibility: hidden;
        }

        /* Teknik özet kutucukları */
        .wb-specs {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1px;
            background: var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .wb-spec {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-4);
            background: var(--bg-primary);
        }

        .wb-spec i {
            width: 30px;
            height: 30px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: var(--wb-accent-soft);
            color: var(--wb-accent-light);
            font-size: 13px;
        }

        .wb-spec b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.25;
        }

        .wb-spec span {
            font-size: 11px;
            color: var(--text-muted);
        }

        .wb-plan-body {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: var(--space-5);
        }

        .wb-features {
            list-style: none;
            margin: 0 0 var(--space-5);
            padding: 0;
            display: grid;
            gap: 2px;
        }

        .wb-features li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 7px 0;
            font-size: var(--text-sm);
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .wb-features li i {
            margin-top: 3px;
            font-size: 11px;
            color: #22c55e;
            flex-shrink: 0;
        }

        /* Uzun listelerde kart şişmesin: fazlası gizli, düğmeyle açılır */
        .wb-features.is-clipped li:nth-child(n+7) {
            display: none;
        }

        .wb-more {
            align-self: flex-start;
            margin: calc(var(--space-5) * -1 + 4px) 0 var(--space-5);
            padding: 6px 0;
            background: none;
            border: none;
            color: var(--wb-accent-light);
            font-family: inherit;
            font-size: var(--text-sm);
            font-weight: 600;
            cursor: pointer;
        }

        .wb-more:hover {
            text-decoration: underline;
        }

        .wb-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            /* Buton kartın en altına yapışsın, kartlar eşit hizalansın */
            margin-top: auto;
            padding: 15px 24px;
            border-radius: var(--radius-md);
            border: 1px solid transparent;
            background: var(--wb-gradient);
            color: #fff;
            font-size: var(--text-base);
            font-weight: 700;
            box-shadow: 0 8px 22px color-mix(in srgb, var(--primary) 30%, transparent);
            transition: transform var(--transition-fast) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .wb-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 34px color-mix(in srgb, var(--primary) 48%, transparent);
        }

        .wb-cta:active {
            transform: translateY(0) scale(0.99);
        }

        /* Boş durum */
        .wb-empty {
            text-align: center;
            padding: var(--space-9) var(--space-5);
            border: 1px dashed var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--surface-1);
        }

        .wb-empty i {
            font-size: 42px;
            color: var(--wb-accent);
            margin-bottom: var(--space-4);
        }

        .wb-empty h3 {
            font-size: var(--text-xl);
            color: var(--text-primary);
            margin-bottom: var(--space-2);
        }

        .wb-empty p {
            color: var(--text-muted);
        }

        /* ===== "Her pakette standart" paneli =====
           8 ayrı kart yerine tek panel: hepsinin dahil olduğu
           tek bir vaat gibi okunur, dikey yer de yarıya iner. */
        .wb-included {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--surface-1);
            overflow: hidden;
        }

        .wb-included-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1px;
            background: var(--border-color);
        }

        .wb-inc {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            background: var(--bg-primary);
            transition: background-color var(--transition-normal) var(--ease-out);
        }

        .wb-inc:hover {
            background: color-mix(in srgb, var(--wb-accent) 6%, var(--bg-primary));
        }

        .wb-inc i {
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: var(--wb-accent-soft);
            color: var(--wb-accent-light);
            font-size: 14px;
        }

        .wb-inc b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .wb-inc span {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        /* ===== Teknoloji: bir büyük vitrin + üç kart ===== */
        .wb-spotlight {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 0.85fr);
            gap: var(--space-7);
            align-items: center;
            padding: var(--space-7);
            margin-bottom: var(--space-5);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background:
                radial-gradient(ellipse 60% 90% at 100% 50%, color-mix(in srgb, #22c55e 10%, transparent) 0%, transparent 70%),
                var(--surface-1);
        }

        .wb-spotlight-tag {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 14px;
            border-radius: 50px;
            background: color-mix(in srgb, #22c55e 14%, transparent);
            border: 1px solid color-mix(in srgb, #22c55e 30%, transparent);
            color: #4ade80;
            font-size: var(--text-xs);
            font-weight: 700;
            margin-bottom: var(--space-4);
        }

        .wb-spotlight h3 {
            font-size: var(--text-2xl);
            font-weight: 800;
            letter-spacing: -0.025em;
            color: var(--text-primary);
            margin-bottom: var(--space-3);
        }

        .wb-spotlight p {
            font-size: var(--text-md);
            color: var(--text-muted);
            line-height: 1.7;
            margin-bottom: var(--space-5);
            max-width: 46ch;
        }

        .wb-spotlight-points {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .wb-spotlight-points li {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 13px;
            border-radius: 50px;
            background: var(--surface-2);
            border: 1px solid var(--border-color);
            font-size: var(--text-xs);
            font-weight: 600;
            color: var(--text-secondary);
        }

        .wb-spotlight-points i {
            color: #4ade80;
            font-size: 10px;
        }

        /* Hız karşılaştırma çubukları */
        .wb-bars {
            display: grid;
            gap: var(--space-4);
        }

        .wb-bar-row {
            display: grid;
            grid-template-columns: 78px minmax(0, 1fr) 42px;
            align-items: center;
            gap: var(--space-3);
        }

        .wb-bar-label {
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--text-muted);
        }

        .wb-bar {
            height: 14px;
            border-radius: 50px;
            background: var(--surface-3);
            overflow: hidden;
        }

        .wb-bar span {
            display: block;
            height: 100%;
            border-radius: 50px;
            background: var(--text-gray);
        }

        .wb-bar-val {
            font-size: var(--text-base);
            font-weight: 800;
            color: var(--text-muted);
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .wb-bar-row.is-lead .wb-bar-label,
        .wb-bar-row.is-lead .wb-bar-val {
            color: var(--text-primary);
        }

        .wb-bar-row.is-lead .wb-bar span {
            background: linear-gradient(90deg, #22c55e, #15803d);
        }

        .wb-bar-note {
            font-size: var(--text-xs);
            color: var(--text-gray);
            line-height: 1.5;
            margin: 0;
        }

        .wb-tech {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: var(--space-5);
        }

        .wb-tech-card {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: var(--space-4);
            padding: var(--space-5);
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: transform var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .wb-tech-card::before {
            content: '';
            position: absolute;
            inset: 0 0 auto 0;
            height: 3px;
            background: var(--tint, var(--wb-gradient));
            transform: scaleX(0);
            transition: transform var(--transition-normal) var(--ease-out);
        }

        .wb-tech-card:hover {
            transform: translateY(-4px);
            background: var(--surface-2);
        }

        .wb-tech-card:hover::before {
            transform: scaleX(1);
        }

        .wb-tech-icon {
            width: 46px;
            height: 46px;
            flex-shrink: 0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            color: #fff;
            background: var(--tint, var(--wb-gradient));
        }

        .wb-tech-card h4 {
            font-size: var(--text-lg);
            font-weight: 700;
            letter-spacing: -0.015em;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .wb-tech-card p {
            font-size: var(--text-sm);
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* ===== Sık sorulan sorular ===== */
        /* Solda görsel + başlık, sağda soru listesi */
        .wb-faq-layout {
            display: grid;
            grid-template-columns: minmax(0, 330px) minmax(0, 1fr);
            gap: var(--space-8);
            /* Sol sutun listeyle ayni yuksekligi alsin */
            align-items: stretch;
        }

        .wb-faq-aside {
            display: flex;
            flex-direction: column;
        }

        .wb-head.is-left {
            text-align: left;
            max-width: 100%;
            margin: 0 0 var(--space-5);
        }

        .wb-faq-visual {
            /* Sabit yukseklik yerine kalan bosluk: liste uzayip kisaldikca hizali kalir */
            flex: 1 1 0;
            min-height: 120px;
            width: 100%;
            max-width: 330px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-4) 0;
        }

        .wb-faq-visual svg {
            width: 100%;
            height: 100%;
            max-height: 240px;
            display: block;
        }

        /* "Sorunuz yoksa bize yazın" kutusu */
        .wb-faq-help {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin-top: var(--space-5);
            padding: var(--space-4);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--surface-1);
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .wb-faq-help:hover {
            border-color: var(--wb-accent-line);
            background: var(--surface-2);
        }

        .wb-faq-help i {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--wb-gradient);
            color: #fff;
            font-size: 15px;
        }

        .wb-faq-help b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .wb-faq-help span {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        .wb-faq {
            display: grid;
            gap: var(--space-3);
        }

        .wb-faq-item {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--surface-1);
            overflow: hidden;
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .wb-faq-item[open] {
            border-color: var(--wb-accent-line);
            background: var(--surface-2);
        }

        .wb-faq-item summary {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            cursor: pointer;
            font-size: var(--text-md);
            font-weight: 600;
            color: var(--text-primary);
            list-style: none;
        }

        /* Soru ikonu - konuya özel satır içi SVG */
        .wb-faq-icon {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--wb-accent-soft);
            border: 1px solid var(--wb-accent-line);
            color: var(--wb-accent-light);
            transition: background-color var(--transition-normal) var(--ease-out),
                color var(--transition-normal) var(--ease-out);
        }

        .wb-faq-icon svg {
            width: 19px;
            height: 19px;
            display: block;
        }

        .wb-faq-item[open] .wb-faq-icon {
            background: var(--wb-gradient);
            border-color: transparent;
            color: #fff;
        }

        /* Soru metni ile artı işareti arasını doldurur */
        .wb-faq-q {
            flex: 1;
            min-width: 0;
        }

        /* Tarayıcının varsayılan üçgen işaretini kaldır */
        .wb-faq-item summary::-webkit-details-marker {
            display: none;
        }

        .wb-faq-item summary::marker {
            content: '';
        }

        .wb-faq-item summary:hover {
            color: var(--wb-accent-light);
        }

        .wb-faq-item summary:focus-visible {
            outline: none;
            box-shadow: var(--focus-ring);
        }

        .wb-faq-sign {
            width: 28px;
            height: 28px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--wb-accent-soft);
            color: var(--wb-accent-light);
            font-size: 12px;
            transition: transform var(--transition-normal) var(--ease-out);
        }

        .wb-faq-item[open] .wb-faq-sign {
            transform: rotate(45deg);
        }

        .wb-faq-body {
            /* Cevap, sorunun metniyle aynı hizadan başlasın (ikon + boşluk kadar içeride) */
            padding: 0 var(--space-5) var(--space-5) calc(var(--space-5) + 38px + var(--space-3));
            font-size: var(--text-base);
            color: var(--text-muted);
            line-height: 1.75;
            max-width: 72ch;
        }

        /* ===== Kapanış çağrısı ===== */
        .wb-final {
            position: relative;
            overflow: hidden;
            padding: var(--space-8) 0;
            background: var(--wb-gradient);
            color: #fff;
            text-align: center;
        }

        .wb-final::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 100% at 50% 0%, rgba(255, 255, 255, 0.2) 0%, transparent 62%),
                radial-gradient(ellipse 50% 80% at 88% 100%, rgba(0, 0, 0, 0.18) 0%, transparent 60%);
            pointer-events: none;
        }

        .wb-final>.container {
            position: relative;
            z-index: 1;
        }

        .wb-final h3 {
            font-size: var(--text-2xl);
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: var(--space-3);
            text-wrap: balance;
        }

        .wb-final p {
            font-size: var(--text-md);
            opacity: 0.92;
            margin-bottom: var(--space-6);
        }

        .wb-final-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: var(--space-3);
        }

        .wb-final-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 32px;
            border-radius: var(--radius-md);
            background: #fff;
            color: var(--wb-accent-dark);
            font-size: var(--text-md);
            font-weight: 700;
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.2);
            transition: transform var(--transition-fast) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .wb-final-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.28);
        }

        .wb-final-link {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 15px 26px;
            border-radius: var(--radius-md);
            border: 1px solid rgba(255, 255, 255, 0.45);
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            font-size: var(--text-md);
            font-weight: 600;
            transition: background-color var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out);
        }

        .wb-final-link:hover {
            background: rgba(255, 255, 255, 0.22);
            border-color: #fff;
        }

        /* ===== Duyarlılık ===== */
        @media (max-width: 1100px) {
            .wb-included-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .wb-spotlight {
                grid-template-columns: 1fr;
                gap: var(--space-6);
                padding: var(--space-6);
            }

            .wb-spotlight p {
                max-width: 100%;
            }
        }

        @media (max-width: 992px) {
            .wb-lead {
                max-width: 100%;
            }

            .wb-tech {
                grid-template-columns: 1fr;
            }

            /* Görsel ve başlık üste, sorular altına */
            .wb-faq-layout {
                grid-template-columns: 1fr;
                gap: var(--space-6);
                justify-items: center;
            }

            .wb-faq-aside {
                max-width: 460px;
                width: 100%;
            }

            .wb-head.is-left {
                text-align: center;
            }

            .wb-faq-visual {
                flex: 0 0 auto;
                margin: 0 auto;
            }

            .wb-faq-visual svg {
                height: auto;
            }

            .wb-faq {
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            .wb-plans,
            .wb-plans[data-count="2"],
            .wb-plans[data-count="3"] {
                grid-template-columns: 1fr;
            }

            .wb-included-grid {
                grid-template-columns: 1fr;
            }

            .wb-bar-row {
                grid-template-columns: 66px minmax(0, 1fr) 34px;
                gap: var(--space-2);
            }

            .wb-final-actions {
                flex-direction: column;
            }

            .wb-final-actions>* {
                width: 100%;
                justify-content: center;
            }

            /* Dar ekranda cevabı ikon hizasında girintilemek yer israfı olur */
            .wb-faq-body {
                padding-left: var(--space-5);
            }

            .wb-faq-item summary {
                padding-left: var(--space-4);
                padding-right: var(--space-4);
            }
        }
    </style>

    <div class="wb">

        <!-- Hero -->
        <section class="wb-hero">
            <div class="container">
                <div class="wb-hero-grid">
                    <div class="wb-hero-text">
                        <span class="wb-eyebrow"><i class="fas fa-wand-magic-sparkles"></i> Kodsuz Web Sitesi Kurucu</span>
                        <h1 class="wb-title"><span>Web Site Builder</span> Paketleri</h1>
                        <p class="wb-lead">
                            Sürükle-bırak editörle kod yazmadan site kurun. Hazır şablonlar, mobil
                            uyumlu tasarım ve ücretsiz SSL her pakette standart.
                        </p>

                        <div class="wb-stack">
                            <span class="wb-chip"><i class="fas fa-arrow-pointer is-editor"></i> Sürükle-Bırak</span>
                            <span class="wb-chip"><i class="fas fa-palette is-sablon"></i> Hazır Şablon</span>
                            <span class="wb-chip"><i class="fas fa-mobile-screen is-mobil"></i> Mobil Uyumlu</span>
                            <span class="wb-chip"><i class="fas fa-certificate is-ssl"></i> Ücretsiz SSL</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kategori sekmeleri -->
            <div class="wb-tabs-wrap">
                <div class="container">
                    <div class="wb-tabs">
                        <a href="store.php" class="wb-tab"><i class="fas fa-th-large"></i> Tüm Ürünler</a>
                        <a href="store.php?group=website-builder" class="wb-tab is-active"><i class="fas fa-wand-magic-sparkles"></i> Web Site
                            Builder</a>
                        <?php foreach ($allGroups as $g):
                            if ($g['slug'] === 'website-builder') {
                                continue;
                            }
                            $gCount = Database::fetchColumn("SELECT COUNT(*) FROM products WHERE group_id = ? AND is_active = 1", [$g['id']]);
                            if ($gCount <= 0) {
                                continue;
                            }
                            ?>
                            <a href="store.php?group=<?= htmlspecialchars($g['slug']) ?>" class="wb-tab">
                                <i class="fas <?= $typeConfig[$g['type'] ?? 'hosting']['icon'] ?? 'fa-box' ?>"></i>
                                <?= htmlspecialchars($g['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Paketler -->
        <section class="wb-section" id="paketler">
            <div class="container">
                <div class="wb-head">
                    <span class="wb-badge"><i class="fas fa-wand-magic-sparkles"></i> Web Site Builder Paketleri</span>
                    <h2>İşinize Uygun <span>Site Planı</span></h2>
                    <p>Tüm paketlerde sürükle-bırak editör, hazır şablonlar ve ücretsiz SSL standart olarak sunulur.</p>
                </div>

                <?php if (empty($products)): ?>
                    <div class="wb-empty">
                        <i class="fas fa-wand-magic-sparkles"></i>
                        <h3>Ürün Bulunamadı</h3>
                        <p>Bu kategoride henüz ürün bulunmuyor.</p>
                    </div>
                <?php else:
                    $productCount = count($products);

                    // En az bir pakette yıllık indirim varsa, olmayanlarda rozet
                    // yüksekliği kadar boşluk bırakılır; kartlar hizalı kalır.
                    $anySaving = false;
                    foreach ($products as $p) {
                        $m = (float) ($p['price_monthly'] ?? 0);
                        $a = (float) ($p['price_annually'] ?? 0);
                        if ($m > 0 && $a > 0 && $a < $m * 12) {
                            $anySaving = true;
                            break;
                        }
                    }
                    ?>
                    <div class="wb-plans" data-count="<?= $productCount ?>">
                        <?php
                        // Öne çıkan ürün işaretliyse onu, değilse ortadaki paketi vurgula
                        $popularIndex = -1;
                        foreach ($products as $i => $p) {
                            if (!empty($p['is_featured'])) {
                                $popularIndex = $i;
                                break;
                            }
                        }
                        if ($popularIndex === -1 && $productCount > 2) {
                            $popularIndex = (int) floor(($productCount - 1) / 2);
                        }

                        foreach ($products as $index => $product):
                            $isPopular = ($index === $popularIndex);
                            $price = (float) ($product['price_monthly'] ?? 0);
                            $annual = (float) ($product['price_annually'] ?? 0);
                            $setup = (float) ($product['setup_fee'] ?? 0);

                            // Açıklama satırlarını teknik özet ve özellik listesi olarak ayır
                            $lines = [];
                            foreach (preg_split('/\r\n|\r|\n/', (string) ($product['description'] ?? '')) as $line) {
                                $line = trim($line);
                                if ($line !== '') {
                                    $lines[] = $line;
                                }
                            }

                            $specs = [];
                            $usedLines = [];
                            foreach ($wbSpecTypes as $key => $spec) {
                                if (count($specs) >= 4) {
                                    break;
                                }
                                foreach ($lines as $li => $line) {
                                    if (isset($usedLines[$li]) || !preg_match($spec['match'], $line)) {
                                        continue;
                                    }
                                    $value = $wbSpecValue($line);
                                    if ($value === '') {
                                        continue;
                                    }
                                    $specs[] = ['label' => $spec['label'], 'icon' => $spec['icon'], 'value' => $value];
                                    $usedLines[$li] = true;
                                    break;
                                }
                            }

                            $features = [];
                            foreach ($lines as $li => $line) {
                                if (!isset($usedLines[$li])) {
                                    $features[] = $line;
                                }
                            }
                            if (empty($features) && empty($specs)) {
                                $features = [
                                    'Sürükle-Bırak Editör',
                                    'Hazır Şablonlar',
                                    'Mobil Uyumlu Tasarım',
                                    'Ücretsiz SSL Sertifikası',
                                    '7/24 Teknik Destek',
                                ];
                            }

                            // Yıllık ödemede aylığa göre kazanç
                            $savePercent = 0;
                            if ($price > 0 && $annual > 0 && $annual < $price * 12) {
                                $savePercent = (int) round((1 - ($annual / ($price * 12))) * 100);
                            }

                            $clip = count($features) > 6;
                            $listId = 'lx-feat-' . (int) $product['id'];
                            ?>
                            <article class="wb-plan <?= $isPopular ? 'is-popular' : '' ?>">
                                <?php if ($isPopular): ?>
                                    <div class="wb-ribbon"><i class="fas fa-fire"></i> En Popüler</div>
                                <?php endif; ?>

                                <div class="wb-plan-top">
                                    <div class="wb-plan-name"><?= htmlspecialchars($product['name']) ?></div>
                                    <div class="wb-plan-group">Web Site Builder</div>

                                    <?php if ($price > 0): ?>
                                        <div class="wb-price">
                                            <span class="cur">₺</span>
                                            <span class="val"><?= number_format(floor($price), 0, ',', '.') ?></span>
                                            <span class="per">/ay</span>
                                        </div>
                                        <?php if ($setup > 0): ?>
                                            <div class="wb-price-note">
                                                + ₺<?= number_format($setup, 0, ',', '.') ?> tek seferlik kurulum
                                            </div>
                                        <?php else: ?>
                                            <div class="wb-price-note">Kurulum ücreti yok</div>
                                        <?php endif; ?>
                                        <?php if ($savePercent > 0): ?>
                                            <span class="wb-save">
                                                <i class="fas fa-tag"></i> Yıllık ödemede %<?= $savePercent ?> indirim
                                            </span>
                                        <?php elseif ($anySaving): ?>
                                            <span class="wb-save is-placeholder" aria-hidden="true">
                                                <i class="fas fa-tag"></i> &nbsp;
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="wb-price-ask">Fiyat için görüşelim</div>
                                        <div class="wb-price-note">İhtiyacınıza göre özel teklif hazırlıyoruz</div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($specs)): ?>
                                    <div class="wb-specs">
                                        <?php foreach ($specs as $spec): ?>
                                            <div class="wb-spec">
                                                <i class="fas <?= $spec['icon'] ?>"></i>
                                                <div>
                                                    <b><?= htmlspecialchars($spec['value']) ?></b>
                                                    <span><?= htmlspecialchars($spec['label']) ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="wb-plan-body">
                                    <?php if (!empty($features)): ?>
                                        <ul class="wb-features <?= $clip ? 'is-clipped' : '' ?>" id="<?= $listId ?>">
                                            <?php foreach ($features as $feature): ?>
                                                <li><i class="fas fa-check"></i> <?= htmlspecialchars($feature) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php if ($clip): ?>
                                            <button type="button" class="wb-more" data-target="<?= $listId ?>"
                                                aria-expanded="false" aria-controls="<?= $listId ?>">
                                                + <?= count($features) - 6 ?> özellik daha
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <a class="wb-cta" href="/client/order-configure.php?id=<?= (int) $product['id'] ?>">
                                        <i class="fas fa-shopping-cart"></i> Sipariş Ver
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Her pakette standart -->
        <section class="wb-section is-alt">
            <div class="container">
                <div class="wb-head">
                    <span class="wb-badge"><i class="fas fa-check-double"></i> Pakete Dahil</span>
                    <h2>Hepsi <span>Her Pakette Standart</span></h2>
                    <p>Aşağıdakiler için ek ücret ödemezsiniz; en küçük pakette de aynen geçerli.</p>
                </div>

                <div class="wb-included">
                    <div class="wb-included-grid">
                        <div class="wb-inc">
                            <i class="fas fa-arrow-pointer"></i>
                            <div>
                                <b>Sürükle-Bırak Editör</b>
                                <span>Kod bilgisi gerekmez</span>
                            </div>
                        </div>
                        <div class="wb-inc">
                            <i class="fas fa-palette"></i>
                            <div>
                                <b>Hazır Şablonlar</b>
                                <span>Sektöre göre tasarımlar</span>
                            </div>
                        </div>
                        <div class="wb-inc">
                            <i class="fas fa-mobile-screen"></i>
                            <div>
                                <b>Mobil Uyumlu</b>
                                <span>Telefon ve tablette düzgün</span>
                            </div>
                        </div>
                        <div class="wb-inc">
                            <i class="fas fa-certificate"></i>
                            <div>
                                <b>Ücretsiz SSL</b>
                                <span>Let's Encrypt, otomatik yenileme</span>
                            </div>
                        </div>
                        <div class="wb-inc">
                            <i class="fas fa-magnifying-glass"></i>
                            <div>
                                <b>SEO Ayarları</b>
                                <span>Başlık, açıklama, site haritası</span>
                            </div>
                        </div>
                        <div class="wb-inc">
                            <i class="fas fa-globe"></i>
                            <div>
                                <b>Alan Adı Bağlama</b>
                                <span>Kendi alan adınızla yayın</span>
                            </div>
                        </div>
                        <div class="wb-inc">
                            <i class="fas fa-images"></i>
                            <div>
                                <b>Medya Kütüphanesi</b>
                                <span>Görsel ve video yönetimi</span>
                            </div>
                        </div>
                        <div class="wb-inc">
                            <i class="fas fa-headset"></i>
                            <div>
                                <b>7/24 Destek</b>
                                <span>Uzman teknik ekip</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Teknoloji altyapısı -->
        <section class="wb-section">
            <div class="container">
                <div class="wb-head">
                    <span class="wb-badge"><i class="fas fa-microchip"></i> Teknoloji Altyapısı</span>
                    <h2>Site Kurma <span>Süreci</span></h2>
                    <p>Bütün paketler aynı editörü kullanır; fark şablon ve sayfa kotasında.</p>
                </div>

                <!-- Vitrin: editör -->
                <div class="wb-spotlight">
                    <div class="wb-spotlight-text">
                        <span class="wb-spotlight-tag"><i class="fas fa-arrow-pointer"></i> Editör</span>
                        <h3>Sürükle-Bırak Editör</h3>
                        <p>
                            Bölümü seçin, yerine sürükleyin, metni değiştirin. Yaptığınız her
                            değişikliği yayına almadan önce önizleyebilirsiniz.
                        </p>
                        <ul class="wb-spotlight-points">
                            <li><i class="fas fa-check"></i> Hazır bölümler</li>
                            <li><i class="fas fa-check"></i> Canlı önizleme</li>
                            <li><i class="fas fa-check"></i> Mobil görünüm ayarı</li>
                            <li><i class="fas fa-check"></i> Tek tıkla yayına alma</li>
                        </ul>
                    </div>

                    <div class="wb-bars is-stack">
                        <div class="wb-bar-row"><span class="wb-bar-label">Editör</span><span class="wb-bar-val">Sürükle-bırak</span></div>
                        <div class="wb-bar-row"><span class="wb-bar-label">Şablon</span><span class="wb-bar-val">150+ hazır tasarım</span></div>
                        <div class="wb-bar-row"><span class="wb-bar-label">Mobil</span><span class="wb-bar-val">Otomatik uyum</span></div>
                        <div class="wb-bar-row"><span class="wb-bar-label">Yayına alma</span><span class="wb-bar-val">Tek tıkla</span></div>
                        <p class="wb-bar-note">
                            Şablon sayısı pakete göre değişir; editör bütün paketlerde aynıdır.
                        </p>
                    </div>
                </div>

                <div class="wb-tech">
                    <div class="wb-tech-card" style="--tint: linear-gradient(135deg, #ec4899, #be185d);">
                        <div class="wb-tech-icon"><i class="fas fa-palette"></i></div>
                        <div>
                            <h4>Hazır Şablonlar</h4>
                            <p>Sektöre göre ayrılmış tasarımlar; renk, yazı tipi ve bölümleri kendinize göre değiştirin.</p>
                        </div>
                    </div>
                    <div class="wb-tech-card" style="--tint: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                        <div class="wb-tech-icon"><i class="fas fa-cart-shopping"></i></div>
                        <div>
                            <h4>E-Ticaret Modülü</h4>
                            <p>P3 ve P4 paketlerinde ürün, sepet ve ödeme adımlarını kod yazmadan kurabilirsiniz.</p>
                        </div>
                    </div>
                    <div class="wb-tech-card" style="--tint: linear-gradient(135deg, #0ea5e9, #0284c7);">
                        <div class="wb-tech-icon"><i class="fas fa-globe"></i></div>
                        <div>
                            <h4>Alan Adı Bağlama</h4>
                            <p>Kendi alan adınızı bağlayın; SSL sertifikası otomatik kurulur ve yenilenir.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sık sorulanlar -->
        <section class="wb-section is-alt">
            <div class="container">
                <div class="wb-faq-layout">
                    <div class="wb-faq-aside">
                        <div class="wb-head is-left">
                            <span class="wb-badge"><i class="fas fa-circle-question"></i> Sık Sorulanlar</span>
                            <h2>Aklınıza <span>Takılanlar</span></h2>
                            <p>Satın almadan önce en çok merak edilenleri derledik.</p>
                        </div>

                        <div class="wb-faq-visual">
                            <!-- Soru-cevap balonları; renkler tema değişkenlerinden gelir -->
                            <svg viewBox="0 0 320 272" fill="none" aria-hidden="true">
                                <defs>
                                    <linearGradient id="lxQmark" x1="0" y1="0" x2="1" y2="1">
                                        <stop offset="0" stop-color="var(--primary-light)" />
                                        <stop offset="1" stop-color="var(--primary-dark)" />
                                    </linearGradient>
                                    <radialGradient id="lxHalo" cx="0.5" cy="0.5" r="0.5">
                                        <stop offset="0" stop-color="var(--primary)" stop-opacity="0.20" />
                                        <stop offset="1" stop-color="var(--primary)" stop-opacity="0" />
                                    </radialGradient>
                                </defs>

                                <ellipse cx="160" cy="136" rx="152" ry="122" fill="url(#lxHalo)" />

                                <!-- Cevap balonu (arkada) -->
                                <path d="M276 242 v22 l-26 -22 z" fill="var(--surface-2)" stroke="var(--border-color)"
                                    stroke-width="2" stroke-linejoin="round" />
                                <rect x="104" y="150" width="200" height="92" rx="22" fill="var(--surface-2)"
                                    stroke="var(--border-color)" stroke-width="2" />
                                <rect x="132" y="172" width="140" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.55" />
                                <rect x="132" y="194" width="118" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.4" />
                                <rect x="132" y="216" width="78" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.28" />

                                <!-- Soru balonu (önde) -->
                                <path d="M44 126 v22 l26 -22 z" fill="color-mix(in srgb, var(--primary) 12%, transparent)" stroke="var(--primary)"
                                    stroke-width="2" stroke-linejoin="round" />
                                <rect x="16" y="26" width="196" height="100" rx="24" fill="color-mix(in srgb, var(--primary) 12%, transparent)"
                                    stroke="var(--primary)" stroke-width="2" />
                                <rect x="44" y="56" width="112" height="9" rx="4.5" fill="var(--primary-light)" opacity="0.85" />
                                <rect x="44" y="78" width="140" height="9" rx="4.5" fill="var(--primary-light)" opacity="0.6" />
                                <rect x="44" y="100" width="84" height="9" rx="4.5" fill="var(--primary-light)" opacity="0.4" />

                                <!-- Soru işareti rozeti -->
                                <circle cx="234" cy="44" r="28" fill="url(#lxQmark)" />
                                <text x="234" y="45" text-anchor="middle" dominant-baseline="central" fill="#fff"
                                    font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="34"
                                    font-weight="800">?</text>

                                <!-- Serpiştirilmiş noktalar -->
                                <circle cx="292" cy="104" r="5" fill="var(--primary)" opacity="0.5" />
                                <circle cx="306" cy="126" r="3" fill="var(--primary)" opacity="0.3" />
                                <circle cx="24" cy="186" r="4" fill="var(--primary)" opacity="0.35" />
                                <circle cx="44" cy="210" r="6" fill="var(--primary)" opacity="0.2" />
                            </svg>
                        </div>

                        <a href="contact.php" class="wb-faq-help">
                            <i class="fas fa-headset"></i>
                            <div>
                                <b>Sorunuz listede yok mu?</b>
                                <span>Destek ekibimize yazın, aynı gün dönelim.</span>
                            </div>
                        </a>
                    </div>

                <div class="wb-faq">
                    <details class="wb-faq-item">
                        <summary>
                            <span class="wb-faq-icon">
                                <!-- Kontrol paneli: bölmeli ekran -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="3.5" width="18" height="17" rx="2.5" />
                                    <path d="M3 9.5h18" />
                                    <path d="M9.5 20.5v-11" />
                                </svg>
                            </span>
                            <span class="wb-faq-q">Kod bilgisi olmadan site kurabilir miyim?</span>
                            <span class="wb-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="wb-faq-body">
                            Evet. Hazır bir şablon seçip metinleri ve görselleri değiştirmeniz yeterli. Bölümleri
                            sürükleyerek sıralar, sonucu yayına almadan önce önizlersiniz.
                        </div>
                    </details>

                    <details class="wb-faq-item">
                        <summary>
                            <span class="wb-faq-icon">
                                <!-- Yedekleme: geri sarma oku + saat ibresi -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3.2 12a8.8 8.8 0 1 0 2.9-6.5" />
                                    <path d="M3 4.2v4.6h4.6" />
                                    <path d="M12 8.2V12l2.9 1.7" />
                                </svg>
                            </span>
                            <span class="wb-faq-q">Şablonu sonradan değiştirebilir miyim?</span>
                            <span class="wb-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="wb-faq-body">
                            Evet. Şablonu istediğiniz zaman değiştirebilirsiniz; yazdığınız metinler ve
                            yüklediğiniz görseller korunur, yalnızca tasarım değişir.
                        </div>
                    </details>

                    <details class="wb-faq-item">
                        <summary>
                            <span class="wb-faq-icon">
                                <!-- SSL: asma kilit -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="4" y="10.2" width="16" height="10.3" rx="2.4" />
                                    <path d="M8 10.2V7.1a4 4 0 0 1 8 0v3.1" />
                                    <path d="M12 14.4v2.2" />
                                </svg>
                            </span>
                            <span class="wb-faq-q">SSL sertifikası için ayrıca ödeme yapacak mıyım?</span>
                            <span class="wb-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="wb-faq-body">
                            Hayır. Let's Encrypt SSL sertifikası bütün paketlerde ücretsiz olarak sunulur ve
                            süresi dolmadan otomatik yenilenir.
                        </div>
                    </details>

                    <details class="wb-faq-item">
                        <summary>
                            <span class="wb-faq-icon">
                                <!-- Alan adı: küre -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="12" cy="12" r="8.6" />
                                    <path d="M3.4 12h17.2" />
                                    <path d="M12 3.4a13 13 0 0 1 0 17.2a13 13 0 0 1 0-17.2" />
                                </svg>
                            </span>
                            <span class="wb-faq-q">Kendi alan adımı bağlayabilir miyim?</span>
                            <span class="wb-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="wb-faq-body">
                            Evet. Alan adınızı bize yönlendirmeniz yeterli; SSL sertifikası otomatik kurulur.
                            Alan adınız yoksa sipariş sırasında yanında satın alabilirsiniz.
                        </div>
                    </details>

                    <details class="wb-faq-item">
                        <summary>
                            <span class="wb-faq-icon">
                                <!-- İşletim sistemi: sunucu rafı -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="4" width="18" height="7" rx="2.2" />
                                    <rect x="3" y="13" width="18" height="7" rx="2.2" />
                                    <path d="M7 7.5h.01" />
                                    <path d="M7 16.5h.01" />
                                </svg>
                            </span>
                            <span class="wb-faq-q">Sitem mobilde nasıl görünecek?</span>
                            <span class="wb-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="wb-faq-body">
                            Bütün şablonlar mobil uyumludur. Editörde telefon görünümüne geçip yazı boyutu,
                            boşluk ve bölüm sırasını mobile özel ayarlayabilirsiniz.
                        </div>
                    </details>

                    <details class="wb-faq-item">
                        <summary>
                            <span class="wb-faq-icon">
                                <!-- Taşıma: karşılıklı transfer okları -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4 9h15" />
                                    <path d="m15.5 5.5 3.5 3.5-3.5 3.5" />
                                    <path d="M20 15H5" />
                                    <path d="M8.5 11.5 5 15l3.5 3.5" />
                                </svg>
                            </span>
                            <span class="wb-faq-q">Site üzerinden satış yapabilir miyim?</span>
                            <span class="wb-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="wb-faq-body">
                            E-ticaret modülü P3 ve P4 paketlerinde yer alır. Ürün listesi, sepet ve ödeme
                            adımlarını kod yazmadan kurabilirsiniz.
                        </div>
                    </details>
                    </div>
                </div>
            </div>
        </section>

        <!-- Kapanış -->
        <section class="wb-final">
            <div class="container">
                <h3><i class="fas fa-wand-magic-sparkles"></i> Web Site Builder ile Başlayın</h3>
                <p>Sürükle-bırak editörle dakikalar içinde yayında</p>
                <div class="wb-final-actions">
                    <a href="#paketler" class="wb-final-btn">
                        <i class="fas fa-rocket"></i> Paketleri İncele
                    </a>
                    <a href="contact.php" class="wb-final-link">
                        <i class="fas fa-comments"></i> Önce Soru Sormak İsterim
                    </a>
                </div>
            </div>
        </section>
    </div>

    <script>
        // "+N özellik daha" düğmesi: listedeki gizli satırları açar
        document.querySelectorAll('.wb-more').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var list = document.getElementById(btn.dataset.target);
                if (!list) return;
                var expanded = list.classList.toggle('is-clipped') === false;
                btn.setAttribute('aria-expanded', String(expanded));
                btn.textContent = expanded
                    ? 'Daha az göster'
                    : '+ ' + (list.children.length - 6) + ' özellik daha';
            });
        });
    </script>

    <?php require_once __DIR__ . '/theme/includes/footer.php'; ?>

<?php elseif ($isArchivePage): ?>

    <?php
    /**
     * Arşiv Hosting sayfası - ürün açıklamasından teknik özellikleri ayıkla.
     * "1000 MB Nvme Disk Alanı" gibi satırları vitrin kutucuğuna,
     * kalanları özellik listesine yollar.
     */
    $arvSpecTypes = [
        'depolama' => ['label' => 'Depolama Alanı', 'icon' => 'fa-hard-drive', 'match' => '/disk|yedekleme\s?alan|depolama|saklama/iu'],
        'traffic' => ['label' => 'Aylık Trafik', 'icon' => 'fa-right-left', 'match' => '/trafik|bant\s?geniş/iu'],
        'hiz' => ['label' => 'Bağlantı', 'icon' => 'fa-gauge-high', 'match' => '/mbps|gbps|internet\s?hız|port\s?hız/iu'],
        'kullanici' => ['label' => 'Kullanıcı', 'icon' => 'fa-users', 'match' => '/kullanıcı|hesap\s?sayı/iu'],
    ];

    /** Satırdan sayısal değeri + birimini çek: "1 TB", "1000 Mbps", "Limitsiz" */
    $arvSpecValue = static function (string $line): string {
        if (preg_match('/\b(limitsiz|sınırsız|unlimited)\b/iu', $line)) {
            return 'Limitsiz';
        }
        // Mbps/Gbps, MB/GB'den önce denenir; yoksa "1000 Mbps" -> "1000 MB" olur
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(Mbps|Gbps|TB|GB|MB|KB|Core|Adet|vCPU)?/iu', $line, $m)) {
            return trim($m[1] . ' ' . ($m[2] ?? ''));
        }
        return '';
    };
    ?>

    <style>
        /* ==========================================
           Arşiv Hosting - sayfaya özel değişkenler
           Vurgu rengi ve yüzeyler global tema
           token'larından gelir.
           ========================================== */
        .arv {
            --arv-accent: var(--primary);
            --arv-accent-light: var(--primary);
            --arv-accent-dark: var(--primary-dark);
            --arv-accent-soft: color-mix(in srgb, var(--primary) 12%, transparent);
            --arv-accent-line: color-mix(in srgb, var(--primary) 28%, transparent);
            --arv-gradient: var(--gradient-primary);
        }

        /* ===== Hero ===== */
        .arv-hero {
            position: relative;
            overflow: hidden;
            background: var(--gradient-hero);
            padding: var(--space-6) 0 0;
        }

        .arv-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 55% 50% at 12% 30%, color-mix(in srgb, var(--primary) 22%, transparent) 0%, transparent 62%),
                radial-gradient(ellipse 45% 45% at 88% 10%, color-mix(in srgb, var(--secondary) 16%, transparent) 0%, transparent 58%);
            pointer-events: none;
        }

        .arv-hero>.container {
            position: relative;
            z-index: 1;
        }

        /* Tek kolon, ortalanmis: hero'da ayrica gorsel yok */
        .arv-hero-grid {
            max-width: 780px;
            margin: 0 auto;
            padding-bottom: var(--space-6);
            text-align: center;
        }

        .arv-hero-text .arv-lead {
            margin-left: auto;
            margin-right: auto;
        }

        .arv-hero-text .arv-stack {
            justify-content: center;
        }

        .arv-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 50px;
            background: var(--arv-accent-soft);
            border: 1px solid var(--arv-accent-line);
            color: var(--arv-accent-light);
            font-size: var(--text-sm);
            font-weight: 600;
            margin-bottom: var(--space-4);
        }

        .arv-title {
            /* Ürün sayfası; anasayfa kadar iri olmasına gerek yok */
            font-size: clamp(32px, 3.2vw + 16px, 50px);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -0.035em;
            margin-bottom: var(--space-3);
            color: var(--text-primary);
            text-wrap: balance;
        }

        .arv-title span {
            background: var(--arv-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .arv-lead {
            font-size: var(--text-md);
            color: var(--text-muted);
            line-height: 1.65;
            max-width: 48ch;
            margin-bottom: var(--space-5);
            text-wrap: pretty;
        }

        /* Teknoloji rozetleri - 2x2 kutu yerine tek sıra çip;
           hero yüksekliğini ~110px kısaltır, detaylar aşağıdaki
           teknoloji bölümünde zaten anlatılıyor. */
        .arv-stack {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .arv-chip {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 7px 14px 7px 7px;
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .arv-chip:hover {
            background: var(--surface-2);
            border-color: var(--arv-accent-line);
        }

        .arv-chip i {
            width: 26px;
            height: 26px;
            flex-shrink: 0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #fff;
        }

        .arv-chip i.is-cpanel {
            background: linear-gradient(135deg, #ff6c2c, #e8590c);
        }

        .arv-chip i.is-litespeed {
            background: linear-gradient(135deg, #22c55e, #15803d);
        }

        .arv-chip i.is-jetbackup {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .arv-chip i.is-alma {
            background: linear-gradient(135deg, #38bdf8, #0369a1);
        }

        /* ===== Terminal ===== */
        /* Surum listesi: cubuk sutunu yok */
        .arv-bars.is-stack .arv-bar-row {
            grid-template-columns: minmax(0, 1fr) auto;
        }

        /* Arsiv hizmeti cip renkleri */
        .arv-chip i.is-ftp {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
        }

        .arv-chip i.is-dav {
            background: linear-gradient(135deg, #6366f1, #4338ca);
        }

        .arv-chip i.is-sifre {
            background: linear-gradient(135deg, #22c55e, #15803d);
        }

        .arv-chip i.is-raid {
            background: linear-gradient(135deg, #f59e0b, #b45309);
        }

        /* ===== Kategori sekmeleri ===== */
        .arv-tabs-wrap {
            position: relative;
            border-top: 1px solid var(--border-color);
            background: color-mix(in srgb, var(--bg-primary) 65%, transparent);
            backdrop-filter: blur(12px);
        }

        .arv-tabs {
            display: flex;
            gap: var(--space-2);
            overflow-x: auto;
            padding: var(--space-3) var(--space-4);
            scrollbar-width: none;
            /* Dokunmatik ekranda sekmeler hizaya otursun */
            scroll-snap-type: x proximity;
        }

        .arv-tabs::-webkit-scrollbar {
            display: none;
        }

        .arv-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            scroll-snap-align: start;
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            background: var(--surface-1);
            color: var(--text-muted);
            font-size: var(--text-sm);
            font-weight: 600;
            white-space: nowrap;
            transition: all var(--transition-normal) var(--ease-out);
        }

        .arv-tab:hover {
            color: var(--text-primary);
            border-color: var(--arv-accent-line);
            background: var(--surface-2);
        }

        .arv-tab.is-active {
            background: var(--arv-gradient);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 6px 18px color-mix(in srgb, var(--primary) 35%, transparent);
        }

        /* ===== Bölüm başlıkları ===== */
        .arv-section {
            padding: var(--section-padding) 0;
        }

        /* Paketler bölümü hemen sekme şeridinin altında;
           sekmeler zaten ayırıcı, üstte tam boşluğa gerek yok */
        .arv-hero+.arv-section {
            padding-top: var(--space-7);
        }

        .arv-section.is-alt {
            background: var(--bg-primary);
        }

        .arv-head {
            text-align: center;
            max-width: 680px;
            margin: 0 auto var(--space-7);
        }

        .arv-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 50px;
            background: var(--arv-accent-soft);
            border: 1px solid var(--arv-accent-line);
            color: var(--arv-accent-light);
            font-size: var(--text-sm);
            font-weight: 600;
            margin-bottom: var(--space-4);
        }

        .arv-head h2 {
            font-size: var(--text-3xl);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.15;
            color: var(--text-primary);
            text-wrap: balance;
        }

        .arv-head h2 span {
            background: var(--arv-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .arv-head p {
            margin-top: var(--space-3);
            font-size: var(--text-md);
            color: var(--text-muted);
            text-wrap: pretty;
        }

        /* ===== Paket kartları ===== */
        .arv-plans {
            display: grid;
            /* Sutun sayisi paket adedinden gelir; auto-fit reflow yapmaz */
            grid-template-columns: repeat(var(--plan-cols, 4), minmax(0, 1fr));
            gap: var(--space-5);
            /* Kartlar eşit yükseklikte olsun, sipariş butonları hizalansın */
            align-items: stretch;
        }

        .arv-plans[data-count="1"] {
            --plan-cols: 1;
            max-width: 420px;
            margin-inline: auto;
        }

        .arv-plans[data-count="2"] {
            --plan-cols: 2;
        }

        .arv-plans[data-count="3"] {
            --plan-cols: 3;
        }

        @media (max-width: 1180px) {
            .arv-plans[data-count] {
                --plan-cols: 2;
            }
        }

        @media (max-width: 680px) {
            .arv-plans[data-count] {
                --plan-cols: 1;
            }
        }

        /* Az paket varsa ortala, kartlar aşırı genişlemesin */
        .arv-plans[data-count="1"] {
            grid-template-columns: minmax(300px, 420px);
            justify-content: center;
        }

        .arv-plans[data-count="2"] {
            grid-template-columns: repeat(2, minmax(300px, 430px));
            justify-content: center;
        }

        .arv-plans[data-count="3"] {
            grid-template-columns: repeat(3, minmax(280px, 400px));
            justify-content: center;
        }

        .arv-plan {
            position: relative;
            display: flex;
            flex-direction: column;
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: transform var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .arv-plan:hover {
            transform: translateY(-6px);
            border-color: var(--arv-accent-line);
            box-shadow: var(--shadow-lg);
        }

        .arv-plan.is-popular {
            border-color: var(--arv-accent);
            box-shadow: 0 0 0 1px var(--arv-accent), 0 24px 48px -18px color-mix(in srgb, var(--primary) 45%, transparent);
        }

        .arv-ribbon {
            position: absolute;
            top: 0;
            right: var(--space-5);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 0 0 10px 10px;
            background: var(--arv-gradient);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .arv-plan-top {
            padding: var(--space-6) var(--space-5) var(--space-5);
            border-bottom: 1px solid var(--border-color);
        }

        .arv-plan-name {
            font-size: var(--text-xl);
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .arv-plan-group {
            font-size: var(--text-sm);
            color: var(--text-muted);
            margin-bottom: var(--space-5);
        }

        .arv-price {
            display: flex;
            align-items: baseline;
            gap: 4px;
            /* Rakamlar kartlar arasında hizalı dursun */
            font-variant-numeric: tabular-nums;
        }

        .arv-price .cur {
            font-size: var(--text-lg);
            font-weight: 700;
            color: var(--arv-accent);
        }

        .arv-price .val {
            font-size: clamp(34px, 3vw + 22px, 46px);
            font-weight: 800;
            letter-spacing: -0.04em;
            line-height: 1;
            color: var(--text-primary);
        }

        .arv-price .per {
            font-size: var(--text-base);
            color: var(--text-muted);
            font-weight: 500;
        }

        .arv-price-ask {
            font-size: var(--text-xl);
            font-weight: 700;
            color: var(--text-primary);
        }

        .arv-price-note {
            margin-top: var(--space-2);
            font-size: var(--text-sm);
            color: var(--text-muted);
        }

        .arv-save {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: var(--space-3);
            padding: 5px 12px;
            border-radius: 50px;
            background: color-mix(in srgb, #22c55e 15%, transparent);
            border: 1px solid color-mix(in srgb, #22c55e 32%, transparent);
            color: #4ade80;
            font-size: var(--text-xs);
            font-weight: 700;
        }

        /* Yıllık indirimi olmayan pakette yeri boş dursun ki
           kartlar arasında teknik kutular aynı hizada başlasın */
        .arv-save.is-placeholder {
            visibility: hidden;
        }

        /* Teknik özet kutucukları */
        .arv-specs {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1px;
            background: var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .arv-spec {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-4);
            background: var(--bg-primary);
        }

        .arv-spec i {
            width: 30px;
            height: 30px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: var(--arv-accent-soft);
            color: var(--arv-accent-light);
            font-size: 13px;
        }

        .arv-spec b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.25;
        }

        .arv-spec span {
            font-size: 11px;
            color: var(--text-muted);
        }

        .arv-plan-body {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: var(--space-5);
        }

        .arv-features {
            list-style: none;
            margin: 0 0 var(--space-5);
            padding: 0;
            display: grid;
            gap: 2px;
        }

        .arv-features li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 7px 0;
            font-size: var(--text-sm);
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .arv-features li i {
            margin-top: 3px;
            font-size: 11px;
            color: #22c55e;
            flex-shrink: 0;
        }

        /* Uzun listelerde kart şişmesin: fazlası gizli, düğmeyle açılır */
        .arv-features.is-clipped li:nth-child(n+7) {
            display: none;
        }

        .arv-more {
            align-self: flex-start;
            margin: calc(var(--space-5) * -1 + 4px) 0 var(--space-5);
            padding: 6px 0;
            background: none;
            border: none;
            color: var(--arv-accent-light);
            font-family: inherit;
            font-size: var(--text-sm);
            font-weight: 600;
            cursor: pointer;
        }

        .arv-more:hover {
            text-decoration: underline;
        }

        .arv-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            /* Buton kartın en altına yapışsın, kartlar eşit hizalansın */
            margin-top: auto;
            padding: 15px 24px;
            border-radius: var(--radius-md);
            border: 1px solid transparent;
            background: var(--arv-gradient);
            color: #fff;
            font-size: var(--text-base);
            font-weight: 700;
            box-shadow: 0 8px 22px color-mix(in srgb, var(--primary) 30%, transparent);
            transition: transform var(--transition-fast) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .arv-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 34px color-mix(in srgb, var(--primary) 48%, transparent);
        }

        .arv-cta:active {
            transform: translateY(0) scale(0.99);
        }

        /* Boş durum */
        .arv-empty {
            text-align: center;
            padding: var(--space-9) var(--space-5);
            border: 1px dashed var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--surface-1);
        }

        .arv-empty i {
            font-size: 42px;
            color: var(--arv-accent);
            margin-bottom: var(--space-4);
        }

        .arv-empty h3 {
            font-size: var(--text-xl);
            color: var(--text-primary);
            margin-bottom: var(--space-2);
        }

        .arv-empty p {
            color: var(--text-muted);
        }

        /* ===== "Her pakette standart" paneli =====
           8 ayrı kart yerine tek panel: hepsinin dahil olduğu
           tek bir vaat gibi okunur, dikey yer de yarıya iner. */
        .arv-included {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--surface-1);
            overflow: hidden;
        }

        .arv-included-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1px;
            background: var(--border-color);
        }

        .arv-inc {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            background: var(--bg-primary);
            transition: background-color var(--transition-normal) var(--ease-out);
        }

        .arv-inc:hover {
            background: color-mix(in srgb, var(--arv-accent) 6%, var(--bg-primary));
        }

        .arv-inc i {
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: var(--arv-accent-soft);
            color: var(--arv-accent-light);
            font-size: 14px;
        }

        .arv-inc b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .arv-inc span {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        /* ===== Teknoloji: bir büyük vitrin + üç kart ===== */
        .arv-spotlight {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 0.85fr);
            gap: var(--space-7);
            align-items: center;
            padding: var(--space-7);
            margin-bottom: var(--space-5);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background:
                radial-gradient(ellipse 60% 90% at 100% 50%, color-mix(in srgb, #22c55e 10%, transparent) 0%, transparent 70%),
                var(--surface-1);
        }

        .arv-spotlight-tag {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 14px;
            border-radius: 50px;
            background: color-mix(in srgb, #22c55e 14%, transparent);
            border: 1px solid color-mix(in srgb, #22c55e 30%, transparent);
            color: #4ade80;
            font-size: var(--text-xs);
            font-weight: 700;
            margin-bottom: var(--space-4);
        }

        .arv-spotlight h3 {
            font-size: var(--text-2xl);
            font-weight: 800;
            letter-spacing: -0.025em;
            color: var(--text-primary);
            margin-bottom: var(--space-3);
        }

        .arv-spotlight p {
            font-size: var(--text-md);
            color: var(--text-muted);
            line-height: 1.7;
            margin-bottom: var(--space-5);
            max-width: 46ch;
        }

        .arv-spotlight-points {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .arv-spotlight-points li {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 13px;
            border-radius: 50px;
            background: var(--surface-2);
            border: 1px solid var(--border-color);
            font-size: var(--text-xs);
            font-weight: 600;
            color: var(--text-secondary);
        }

        .arv-spotlight-points i {
            color: #4ade80;
            font-size: 10px;
        }

        /* Hız karşılaştırma çubukları */
        .arv-bars {
            display: grid;
            gap: var(--space-4);
        }

        .arv-bar-row {
            display: grid;
            grid-template-columns: 78px minmax(0, 1fr) 42px;
            align-items: center;
            gap: var(--space-3);
        }

        .arv-bar-label {
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--text-muted);
        }

        .arv-bar {
            height: 14px;
            border-radius: 50px;
            background: var(--surface-3);
            overflow: hidden;
        }

        .arv-bar span {
            display: block;
            height: 100%;
            border-radius: 50px;
            background: var(--text-gray);
        }

        .arv-bar-val {
            font-size: var(--text-base);
            font-weight: 800;
            color: var(--text-muted);
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .arv-bar-row.is-lead .arv-bar-label,
        .arv-bar-row.is-lead .arv-bar-val {
            color: var(--text-primary);
        }

        .arv-bar-row.is-lead .arv-bar span {
            background: linear-gradient(90deg, #22c55e, #15803d);
        }

        .arv-bar-note {
            font-size: var(--text-xs);
            color: var(--text-gray);
            line-height: 1.5;
            margin: 0;
        }

        .arv-tech {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: var(--space-5);
        }

        .arv-tech-card {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: var(--space-4);
            padding: var(--space-5);
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: transform var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .arv-tech-card::before {
            content: '';
            position: absolute;
            inset: 0 0 auto 0;
            height: 3px;
            background: var(--tint, var(--arv-gradient));
            transform: scaleX(0);
            transition: transform var(--transition-normal) var(--ease-out);
        }

        .arv-tech-card:hover {
            transform: translateY(-4px);
            background: var(--surface-2);
        }

        .arv-tech-card:hover::before {
            transform: scaleX(1);
        }

        .arv-tech-icon {
            width: 46px;
            height: 46px;
            flex-shrink: 0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            color: #fff;
            background: var(--tint, var(--arv-gradient));
        }

        .arv-tech-card h4 {
            font-size: var(--text-lg);
            font-weight: 700;
            letter-spacing: -0.015em;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .arv-tech-card p {
            font-size: var(--text-sm);
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* ===== Sık sorulan sorular ===== */
        /* Solda görsel + başlık, sağda soru listesi */
        .arv-faq-layout {
            display: grid;
            grid-template-columns: minmax(0, 330px) minmax(0, 1fr);
            gap: var(--space-8);
            /* Sol sutun listeyle ayni yuksekligi alsin */
            align-items: stretch;
        }

        .arv-faq-aside {
            display: flex;
            flex-direction: column;
        }

        .arv-head.is-left {
            text-align: left;
            max-width: 100%;
            margin: 0 0 var(--space-5);
        }

        .arv-faq-visual {
            /* Sabit yukseklik yerine kalan bosluk: liste uzayip kisaldikca hizali kalir */
            flex: 1 1 0;
            min-height: 120px;
            width: 100%;
            max-width: 330px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-4) 0;
        }

        .arv-faq-visual svg {
            width: 100%;
            height: 100%;
            max-height: 240px;
            display: block;
        }

        /* "Sorunuz yoksa bize yazın" kutusu */
        .arv-faq-help {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin-top: var(--space-5);
            padding: var(--space-4);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--surface-1);
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .arv-faq-help:hover {
            border-color: var(--arv-accent-line);
            background: var(--surface-2);
        }

        .arv-faq-help i {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--arv-gradient);
            color: #fff;
            font-size: 15px;
        }

        .arv-faq-help b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .arv-faq-help span {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        .arv-faq {
            display: grid;
            gap: var(--space-3);
        }

        .arv-faq-item {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--surface-1);
            overflow: hidden;
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .arv-faq-item[open] {
            border-color: var(--arv-accent-line);
            background: var(--surface-2);
        }

        .arv-faq-item summary {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            cursor: pointer;
            font-size: var(--text-md);
            font-weight: 600;
            color: var(--text-primary);
            list-style: none;
        }

        /* Soru ikonu - konuya özel satır içi SVG */
        .arv-faq-icon {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--arv-accent-soft);
            border: 1px solid var(--arv-accent-line);
            color: var(--arv-accent-light);
            transition: background-color var(--transition-normal) var(--ease-out),
                color var(--transition-normal) var(--ease-out);
        }

        .arv-faq-icon svg {
            width: 19px;
            height: 19px;
            display: block;
        }

        .arv-faq-item[open] .arv-faq-icon {
            background: var(--arv-gradient);
            border-color: transparent;
            color: #fff;
        }

        /* Soru metni ile artı işareti arasını doldurur */
        .arv-faq-q {
            flex: 1;
            min-width: 0;
        }

        /* Tarayıcının varsayılan üçgen işaretini kaldır */
        .arv-faq-item summary::-webkit-details-marker {
            display: none;
        }

        .arv-faq-item summary::marker {
            content: '';
        }

        .arv-faq-item summary:hover {
            color: var(--arv-accent-light);
        }

        .arv-faq-item summary:focus-visible {
            outline: none;
            box-shadow: var(--focus-ring);
        }

        .arv-faq-sign {
            width: 28px;
            height: 28px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--arv-accent-soft);
            color: var(--arv-accent-light);
            font-size: 12px;
            transition: transform var(--transition-normal) var(--ease-out);
        }

        .arv-faq-item[open] .arv-faq-sign {
            transform: rotate(45deg);
        }

        .arv-faq-body {
            /* Cevap, sorunun metniyle aynı hizadan başlasın (ikon + boşluk kadar içeride) */
            padding: 0 var(--space-5) var(--space-5) calc(var(--space-5) + 38px + var(--space-3));
            font-size: var(--text-base);
            color: var(--text-muted);
            line-height: 1.75;
            max-width: 72ch;
        }

        /* ===== Kapanış çağrısı ===== */
        .arv-final {
            position: relative;
            overflow: hidden;
            padding: var(--space-8) 0;
            background: var(--arv-gradient);
            color: #fff;
            text-align: center;
        }

        .arv-final::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 100% at 50% 0%, rgba(255, 255, 255, 0.2) 0%, transparent 62%),
                radial-gradient(ellipse 50% 80% at 88% 100%, rgba(0, 0, 0, 0.18) 0%, transparent 60%);
            pointer-events: none;
        }

        .arv-final>.container {
            position: relative;
            z-index: 1;
        }

        .arv-final h3 {
            font-size: var(--text-2xl);
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: var(--space-3);
            text-wrap: balance;
        }

        .arv-final p {
            font-size: var(--text-md);
            opacity: 0.92;
            margin-bottom: var(--space-6);
        }

        .arv-final-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: var(--space-3);
        }

        .arv-final-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 32px;
            border-radius: var(--radius-md);
            background: #fff;
            color: var(--arv-accent-dark);
            font-size: var(--text-md);
            font-weight: 700;
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.2);
            transition: transform var(--transition-fast) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .arv-final-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.28);
        }

        .arv-final-link {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 15px 26px;
            border-radius: var(--radius-md);
            border: 1px solid rgba(255, 255, 255, 0.45);
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            font-size: var(--text-md);
            font-weight: 600;
            transition: background-color var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out);
        }

        .arv-final-link:hover {
            background: rgba(255, 255, 255, 0.22);
            border-color: #fff;
        }

        /* ===== Duyarlılık ===== */
        @media (max-width: 1100px) {
            .arv-included-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .arv-spotlight {
                grid-template-columns: 1fr;
                gap: var(--space-6);
                padding: var(--space-6);
            }

            .arv-spotlight p {
                max-width: 100%;
            }
        }

        @media (max-width: 992px) {
            .arv-lead {
                max-width: 100%;
            }

            .arv-tech {
                grid-template-columns: 1fr;
            }

            /* Görsel ve başlık üste, sorular altına */
            .arv-faq-layout {
                grid-template-columns: 1fr;
                gap: var(--space-6);
                justify-items: center;
            }

            .arv-faq-aside {
                max-width: 460px;
                width: 100%;
            }

            .arv-head.is-left {
                text-align: center;
            }

            .arv-faq-visual {
                flex: 0 0 auto;
                margin: 0 auto;
            }

            .arv-faq-visual svg {
                height: auto;
            }

            .arv-faq {
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            .arv-plans,
            .arv-plans[data-count="2"],
            .arv-plans[data-count="3"] {
                grid-template-columns: 1fr;
            }

            .arv-included-grid {
                grid-template-columns: 1fr;
            }

            .arv-bar-row {
                grid-template-columns: 66px minmax(0, 1fr) 34px;
                gap: var(--space-2);
            }

            .arv-final-actions {
                flex-direction: column;
            }

            .arv-final-actions>* {
                width: 100%;
                justify-content: center;
            }

            /* Dar ekranda cevabı ikon hizasında girintilemek yer israfı olur */
            .arv-faq-body {
                padding-left: var(--space-5);
            }

            .arv-faq-item summary {
                padding-left: var(--space-4);
                padding-right: var(--space-4);
            }
        }
    </style>

    <div class="arv">

        <!-- Hero -->
        <section class="arv-hero">
            <div class="container">
                <div class="arv-hero-grid">
                    <div class="arv-hero-text">
                        <span class="arv-eyebrow"><i class="fas fa-cloud-arrow-up"></i> Yedekleme ve Dosya Barındırma</span>
                        <h1 class="arv-title"><span>Arşiv Hosting</span> Paketleri</h1>
                        <p class="arv-lead">
                            Yedeklerinizi, arşiv kayıtlarınızı ve büyük dosyalarınızı web sunucunuzdan
                            ayrı bir alanda saklayın. FTP, SFTP ve WebDAV ile her cihazdan erişin.
                        </p>

                        <div class="arv-stack">
                            <span class="arv-chip"><i class="fas fa-folder-open is-ftp"></i> FTP / SFTP</span>
                            <span class="arv-chip"><i class="fas fa-hard-drive is-dav"></i> WebDAV</span>
                            <span class="arv-chip"><i class="fas fa-lock is-sifre"></i> AES-256</span>
                            <span class="arv-chip"><i class="fas fa-layer-group is-raid"></i> RAID 10</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kategori sekmeleri -->
            <div class="arv-tabs-wrap">
                <div class="container">
                    <div class="arv-tabs">
                        <a href="store.php" class="arv-tab"><i class="fas fa-th-large"></i> Tüm Ürünler</a>
                        <a href="store.php?group=arsiv-hosting" class="arv-tab is-active"><i class="fas fa-cloud-arrow-up"></i> Arşiv
                            Hosting</a>
                        <?php foreach ($allGroups as $g):
                            if ($g['slug'] === 'arsiv-hosting') {
                                continue;
                            }
                            $gCount = Database::fetchColumn("SELECT COUNT(*) FROM products WHERE group_id = ? AND is_active = 1", [$g['id']]);
                            if ($gCount <= 0) {
                                continue;
                            }
                            ?>
                            <a href="store.php?group=<?= htmlspecialchars($g['slug']) ?>" class="arv-tab">
                                <i class="fas <?= $typeConfig[$g['type'] ?? 'hosting']['icon'] ?? 'fa-box' ?>"></i>
                                <?= htmlspecialchars($g['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Paketler -->
        <section class="arv-section" id="paketler">
            <div class="container">
                <div class="arv-head">
                    <span class="arv-badge"><i class="fas fa-cloud-arrow-up"></i> Arşiv Hosting Paketleri</span>
                    <h2>İhtiyacınıza Uygun <span>Arşiv Planı</span></h2>
                    <p>Tüm paketlerde FTP/SFTP erişimi, şifreli aktarım ve sürüm geçmişi standart olarak sunulur.</p>
                </div>

                <?php if (empty($products)): ?>
                    <div class="arv-empty">
                        <i class="fas fa-cloud-arrow-up"></i>
                        <h3>Ürün Bulunamadı</h3>
                        <p>Bu kategoride henüz ürün bulunmuyor.</p>
                    </div>
                <?php else:
                    $productCount = count($products);

                    // En az bir pakette yıllık indirim varsa, olmayanlarda rozet
                    // yüksekliği kadar boşluk bırakılır; kartlar hizalı kalır.
                    $anySaving = false;
                    foreach ($products as $p) {
                        $m = (float) ($p['price_monthly'] ?? 0);
                        $a = (float) ($p['price_annually'] ?? 0);
                        if ($m > 0 && $a > 0 && $a < $m * 12) {
                            $anySaving = true;
                            break;
                        }
                    }
                    ?>
                    <div class="arv-plans" data-count="<?= $productCount ?>">
                        <?php
                        // Öne çıkan ürün işaretliyse onu, değilse ortadaki paketi vurgula
                        $popularIndex = -1;
                        foreach ($products as $i => $p) {
                            if (!empty($p['is_featured'])) {
                                $popularIndex = $i;
                                break;
                            }
                        }
                        if ($popularIndex === -1 && $productCount > 2) {
                            $popularIndex = (int) floor(($productCount - 1) / 2);
                        }

                        foreach ($products as $index => $product):
                            $isPopular = ($index === $popularIndex);
                            $price = (float) ($product['price_monthly'] ?? 0);
                            $annual = (float) ($product['price_annually'] ?? 0);
                            $setup = (float) ($product['setup_fee'] ?? 0);

                            // Açıklama satırlarını teknik özet ve özellik listesi olarak ayır
                            $lines = [];
                            foreach (preg_split('/\r\n|\r|\n/', (string) ($product['description'] ?? '')) as $line) {
                                $line = trim($line);
                                if ($line !== '') {
                                    $lines[] = $line;
                                }
                            }

                            $specs = [];
                            $usedLines = [];
                            foreach ($arvSpecTypes as $key => $spec) {
                                if (count($specs) >= 4) {
                                    break;
                                }
                                foreach ($lines as $li => $line) {
                                    if (isset($usedLines[$li]) || !preg_match($spec['match'], $line)) {
                                        continue;
                                    }
                                    $value = $arvSpecValue($line);
                                    if ($value === '') {
                                        continue;
                                    }
                                    $specs[] = ['label' => $spec['label'], 'icon' => $spec['icon'], 'value' => $value];
                                    $usedLines[$li] = true;
                                    break;
                                }
                            }

                            $features = [];
                            foreach ($lines as $li => $line) {
                                if (!isset($usedLines[$li])) {
                                    $features[] = $line;
                                }
                            }
                            // Ürün açıklamasındaki satırların tamamı vitrin kutucuklarına
                            // gitmiş olabilir; liste boş kalmasın diye standartlar yazılır.
                            if (empty($features)) {
                                $features = [
                                    'FTP / SFTP / FTPS Erişimi',
                                    'WebDAV ile Sürücü Bağlama',
                                    'TLS + AES-256 Şifreleme',
                                    '30 Gün Sürüm Geçmişi',
                                    'RAID 10 Depolama',
                                    '7/24 Teknik Destek',
                                ];
                            }

                            // Yıllık ödemede aylığa göre kazanç
                            $savePercent = 0;
                            if ($price > 0 && $annual > 0 && $annual < $price * 12) {
                                $savePercent = (int) round((1 - ($annual / ($price * 12))) * 100);
                            }

                            $clip = count($features) > 6;
                            $listId = 'lx-feat-' . (int) $product['id'];
                            ?>
                            <article class="arv-plan <?= $isPopular ? 'is-popular' : '' ?>">
                                <?php if ($isPopular): ?>
                                    <div class="arv-ribbon"><i class="fas fa-fire"></i> En Popüler</div>
                                <?php endif; ?>

                                <div class="arv-plan-top">
                                    <div class="arv-plan-name"><?= htmlspecialchars($product['name']) ?></div>
                                    <div class="arv-plan-group">Arşiv Hosting</div>

                                    <?php if ($price > 0): ?>
                                        <div class="arv-price">
                                            <span class="cur">₺</span>
                                            <span class="val"><?= number_format(floor($price), 0, ',', '.') ?></span>
                                            <span class="per">/ay</span>
                                        </div>
                                        <?php if ($setup > 0): ?>
                                            <div class="arv-price-note">
                                                + ₺<?= number_format($setup, 0, ',', '.') ?> tek seferlik kurulum
                                            </div>
                                        <?php else: ?>
                                            <div class="arv-price-note">Kurulum ücreti yok</div>
                                        <?php endif; ?>
                                        <?php if ($savePercent > 0): ?>
                                            <span class="arv-save">
                                                <i class="fas fa-tag"></i> Yıllık ödemede %<?= $savePercent ?> indirim
                                            </span>
                                        <?php elseif ($anySaving): ?>
                                            <span class="arv-save is-placeholder" aria-hidden="true">
                                                <i class="fas fa-tag"></i> &nbsp;
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="arv-price-ask">Fiyat için görüşelim</div>
                                        <div class="arv-price-note">İhtiyacınıza göre özel teklif hazırlıyoruz</div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($specs)): ?>
                                    <div class="arv-specs">
                                        <?php foreach ($specs as $spec): ?>
                                            <div class="arv-spec">
                                                <i class="fas <?= $spec['icon'] ?>"></i>
                                                <div>
                                                    <b><?= htmlspecialchars($spec['value']) ?></b>
                                                    <span><?= htmlspecialchars($spec['label']) ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="arv-plan-body">
                                    <?php if (!empty($features)): ?>
                                        <ul class="arv-features <?= $clip ? 'is-clipped' : '' ?>" id="<?= $listId ?>">
                                            <?php foreach ($features as $feature): ?>
                                                <li><i class="fas fa-check"></i> <?= htmlspecialchars($feature) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php if ($clip): ?>
                                            <button type="button" class="arv-more" data-target="<?= $listId ?>"
                                                aria-expanded="false" aria-controls="<?= $listId ?>">
                                                + <?= count($features) - 6 ?> özellik daha
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <a class="arv-cta" href="/client/order-configure.php?id=<?= (int) $product['id'] ?>">
                                        <i class="fas fa-shopping-cart"></i> Sipariş Ver
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Her pakette standart -->
        <section class="arv-section is-alt">
            <div class="container">
                <div class="arv-head">
                    <span class="arv-badge"><i class="fas fa-check-double"></i> Pakete Dahil</span>
                    <h2>Hepsi <span>Her Pakette Standart</span></h2>
                    <p>Aşağıdakiler için ek ücret ödemezsiniz; en küçük pakette de aynen geçerli.</p>
                </div>

                <div class="arv-included">
                    <div class="arv-included-grid">
                        <div class="arv-inc">
                            <i class="fas fa-folder-open"></i>
                            <div>
                                <b>FTP / SFTP</b>
                                <span>Her cihazdan şifreli erişim</span>
                            </div>
                        </div>
                        <div class="arv-inc">
                            <i class="fas fa-hard-drive"></i>
                            <div>
                                <b>WebDAV</b>
                                <span>Sürücü olarak bağlama</span>
                            </div>
                        </div>
                        <div class="arv-inc">
                            <i class="fas fa-lock"></i>
                            <div>
                                <b>AES-256 Şifreleme</b>
                                <span>Aktarımda ve diskte</span>
                            </div>
                        </div>
                        <div class="arv-inc">
                            <i class="fas fa-clock-rotate-left"></i>
                            <div>
                                <b>Sürüm Geçmişi</b>
                                <span>Eski kopyalara geri dönüş</span>
                            </div>
                        </div>
                        <div class="arv-inc">
                            <i class="fas fa-layer-group"></i>
                            <div>
                                <b>RAID 10 Depolama</b>
                                <span>Disk arızasına dayanıklı</span>
                            </div>
                        </div>
                        <div class="arv-inc">
                            <i class="fas fa-calendar-check"></i>
                            <div>
                                <b>Zamanlanmış Yedek</b>
                                <span>Otomatik yedek planı</span>
                            </div>
                        </div>
                        <div class="arv-inc">
                            <i class="fas fa-link"></i>
                            <div>
                                <b>Paylaşım Bağlantısı</b>
                                <span>Süreli, şifre korumalı</span>
                            </div>
                        </div>
                        <div class="arv-inc">
                            <i class="fas fa-headset"></i>
                            <div>
                                <b>7/24 Destek</b>
                                <span>Uzman teknik ekip</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Teknoloji altyapısı -->
        <section class="arv-section">
            <div class="container">
                <div class="arv-head">
                    <span class="arv-badge"><i class="fas fa-microchip"></i> Teknoloji Altyapısı</span>
                    <h2>Depolama <span>Altyapısı</span></h2>
                    <p>Bütün paketler aynı depolama altyapısı üzerinde çalışır; fark yalnızca kotada.</p>
                </div>

                <!-- Vitrin: artımlı yedekleme -->
                <div class="arv-spotlight">
                    <div class="arv-spotlight-text">
                        <span class="arv-spotlight-tag"><i class="fas fa-clock-rotate-left"></i> Yedekleme Yöntemi</span>
                        <h3>Artımlı Yedekleme</h3>
                        <p>
                            İlk aktarımdan sonra yalnızca değişen dosyalar gönderilir. Günlük yedek
                            penceresi kısalır, bant genişliği boşa harcanmaz.
                        </p>
                        <ul class="arv-spotlight-points">
                            <li><i class="fas fa-check"></i> rclone / restic uyumlu</li>
                            <li><i class="fas fa-check"></i> Zamanlanmış görevler</li>
                            <li><i class="fas fa-check"></i> Sürüm geçmişi</li>
                            <li><i class="fas fa-check"></i> Şifreli aktarım</li>
                        </ul>
                    </div>

                    <div class="arv-bars is-stack">
                        <div class="arv-bar-row"><span class="arv-bar-label">Erişim</span><span class="arv-bar-val">FTP / SFTP / FTPS / WebDAV</span></div>
                        <div class="arv-bar-row"><span class="arv-bar-label">Şifreleme</span><span class="arv-bar-val">TLS + AES-256</span></div>
                        <div class="arv-bar-row"><span class="arv-bar-label">Disk yapısı</span><span class="arv-bar-val">RAID 10</span></div>
                        <div class="arv-bar-row"><span class="arv-bar-label">Sürüm geçmişi</span><span class="arv-bar-val">30 gün</span></div>
                        <p class="arv-bar-note">
                            Bütün paketler aynı depolama altyapısı üzerinde çalışır.
                        </p>
                    </div>
                </div>

                <div class="arv-tech">
                    <div class="arv-tech-card" style="--tint: linear-gradient(135deg, #0ea5e9, #0284c7);">
                        <div class="arv-tech-icon"><i class="fas fa-network-wired"></i></div>
                        <div>
                            <h4>FTP / SFTP / WebDAV</h4>
                            <p>Sunucudan, bilgisayardan ya da NAS cihazından aynı alana bağlanın; ayrı istemci gerekmez.</p>
                        </div>
                    </div>
                    <div class="arv-tech-card" style="--tint: linear-gradient(135deg, #22c55e, #15803d);">
                        <div class="arv-tech-icon"><i class="fas fa-lock"></i></div>
                        <div>
                            <h4>AES-256 Şifreleme</h4>
                            <p>Aktarım TLS ile korunur, veriler diskte AES-256 ile şifreli olarak saklanır.</p>
                        </div>
                    </div>
                    <div class="arv-tech-card" style="--tint: linear-gradient(135deg, #f59e0b, #b45309);">
                        <div class="arv-tech-icon"><i class="fas fa-layer-group"></i></div>
                        <div>
                            <h4>RAID 10 Depolama</h4>
                            <p>Disk arızasında kopya diskten kesintisiz devam edilir; veri kaybı yaşanmaz.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sık sorulanlar -->
        <section class="arv-section is-alt">
            <div class="container">
                <div class="arv-faq-layout">
                    <div class="arv-faq-aside">
                        <div class="arv-head is-left">
                            <span class="arv-badge"><i class="fas fa-circle-question"></i> Sık Sorulanlar</span>
                            <h2>Aklınıza <span>Takılanlar</span></h2>
                            <p>Satın almadan önce en çok merak edilenleri derledik.</p>
                        </div>

                        <div class="arv-faq-visual">
                            <!-- Soru-cevap balonları; renkler tema değişkenlerinden gelir -->
                            <svg viewBox="0 0 320 272" fill="none" aria-hidden="true">
                                <defs>
                                    <linearGradient id="lxQmark" x1="0" y1="0" x2="1" y2="1">
                                        <stop offset="0" stop-color="var(--primary-light)" />
                                        <stop offset="1" stop-color="var(--primary-dark)" />
                                    </linearGradient>
                                    <radialGradient id="lxHalo" cx="0.5" cy="0.5" r="0.5">
                                        <stop offset="0" stop-color="var(--primary)" stop-opacity="0.20" />
                                        <stop offset="1" stop-color="var(--primary)" stop-opacity="0" />
                                    </radialGradient>
                                </defs>

                                <ellipse cx="160" cy="136" rx="152" ry="122" fill="url(#lxHalo)" />

                                <!-- Cevap balonu (arkada) -->
                                <path d="M276 242 v22 l-26 -22 z" fill="var(--surface-2)" stroke="var(--border-color)"
                                    stroke-width="2" stroke-linejoin="round" />
                                <rect x="104" y="150" width="200" height="92" rx="22" fill="var(--surface-2)"
                                    stroke="var(--border-color)" stroke-width="2" />
                                <rect x="132" y="172" width="140" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.55" />
                                <rect x="132" y="194" width="118" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.4" />
                                <rect x="132" y="216" width="78" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.28" />

                                <!-- Soru balonu (önde) -->
                                <path d="M44 126 v22 l26 -22 z" fill="color-mix(in srgb, var(--primary) 12%, transparent)" stroke="var(--primary)"
                                    stroke-width="2" stroke-linejoin="round" />
                                <rect x="16" y="26" width="196" height="100" rx="24" fill="color-mix(in srgb, var(--primary) 12%, transparent)"
                                    stroke="var(--primary)" stroke-width="2" />
                                <rect x="44" y="56" width="112" height="9" rx="4.5" fill="var(--primary-light)" opacity="0.85" />
                                <rect x="44" y="78" width="140" height="9" rx="4.5" fill="var(--primary-light)" opacity="0.6" />
                                <rect x="44" y="100" width="84" height="9" rx="4.5" fill="var(--primary-light)" opacity="0.4" />

                                <!-- Soru işareti rozeti -->
                                <circle cx="234" cy="44" r="28" fill="url(#lxQmark)" />
                                <text x="234" y="45" text-anchor="middle" dominant-baseline="central" fill="#fff"
                                    font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="34"
                                    font-weight="800">?</text>

                                <!-- Serpiştirilmiş noktalar -->
                                <circle cx="292" cy="104" r="5" fill="var(--primary)" opacity="0.5" />
                                <circle cx="306" cy="126" r="3" fill="var(--primary)" opacity="0.3" />
                                <circle cx="24" cy="186" r="4" fill="var(--primary)" opacity="0.35" />
                                <circle cx="44" cy="210" r="6" fill="var(--primary)" opacity="0.2" />
                            </svg>
                        </div>

                        <a href="contact.php" class="arv-faq-help">
                            <i class="fas fa-headset"></i>
                            <div>
                                <b>Sorunuz listede yok mu?</b>
                                <span>Destek ekibimize yazın, aynı gün dönelim.</span>
                            </div>
                        </a>
                    </div>

                <div class="arv-faq">
                    <details class="arv-faq-item">
                        <summary>
                            <span class="arv-faq-icon">
                                <!-- Kontrol paneli: bölmeli ekran -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="3.5" width="18" height="17" rx="2.5" />
                                    <path d="M3 9.5h18" />
                                    <path d="M9.5 20.5v-11" />
                                </svg>
                            </span>
                            <span class="arv-faq-q">Bu alanda web sitesi barındırabilir miyim?</span>
                            <span class="arv-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="arv-faq-body">
                            Hayır. Arşiv Hosting yalnızca dosya saklama ve yedekleme içindir; PHP, veritabanı ve
                            web yayını çalıştırılmaz. Site için Linux veya Windows Hosting paketlerine bakabilirsiniz.
                        </div>
                    </details>

                    <details class="arv-faq-item">
                        <summary>
                            <span class="arv-faq-icon">
                                <!-- Yedekleme: geri sarma oku + saat ibresi -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3.2 12a8.8 8.8 0 1 0 2.9-6.5" />
                                    <path d="M3 4.2v4.6h4.6" />
                                    <path d="M12 8.2V12l2.9 1.7" />
                                </svg>
                            </span>
                            <span class="arv-faq-q">Yanlışlıkla sildiğim dosyayı geri alabilir miyim?</span>
                            <span class="arv-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="arv-faq-body">
                            Evet. Sürüm geçmişi 30 gün saklanır; silinen ya da üzerine yazılan dosyanın önceki
                            kopyasına panel üzerinden dönebilirsiniz.
                        </div>
                    </details>

                    <details class="arv-faq-item">
                        <summary>
                            <span class="arv-faq-icon">
                                <!-- SSL: asma kilit -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="4" y="10.2" width="16" height="10.3" rx="2.4" />
                                    <path d="M8 10.2V7.1a4 4 0 0 1 8 0v3.1" />
                                    <path d="M12 14.4v2.2" />
                                </svg>
                            </span>
                            <span class="arv-faq-q">Dosyalarım şifreleniyor mu?</span>
                            <span class="arv-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="arv-faq-body">
                            Aktarım TLS ile korunur, veriler diskte AES-256 ile şifreli tutulur. İsterseniz
                            yükleme öncesi kendi tarafınızda ayrıca şifreleyebilirsiniz.
                        </div>
                    </details>

                    <details class="arv-faq-item">
                        <summary>
                            <span class="arv-faq-icon">
                                <!-- Aktarım: buluta yükleme oku -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M6.5 18.5a4 4 0 0 1-.4-8 5.6 5.6 0 0 1 10.8-1.2 3.6 3.6 0 0 1 .6 7.1" />
                                    <path d="M12 20.5v-8.4" />
                                    <path d="m8.8 15.3 3.2-3.2 3.2 3.2" />
                                </svg>
                            </span>
                            <span class="arv-faq-q">Yedeklerimi buraya nasıl aktarırım?</span>
                            <span class="arv-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="arv-faq-body">
                            FileZilla gibi bir FTP/SFTP istemcisiyle, WebDAV üzerinden sürücü bağlayarak ya da
                            rclone, restic, Duplicati gibi araçlarla zamanlanmış olarak aktarabilirsiniz.
                        </div>
                    </details>

                    <details class="arv-faq-item">
                        <summary>
                            <span class="arv-faq-icon">
                                <!-- İşletim sistemi: sunucu rafı -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="4" width="18" height="7" rx="2.2" />
                                    <rect x="3" y="13" width="18" height="7" rx="2.2" />
                                    <path d="M7 7.5h.01" />
                                    <path d="M7 16.5h.01" />
                                </svg>
                            </span>
                            <span class="arv-faq-q">Disk arızasında verilerime ne olur?</span>
                            <span class="arv-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="arv-faq-body">
                            Depolama RAID 10 üzerinde çalışır. Bir disk arızalandığında kopya diskten kesintisiz
                            devam edilir, veri kaybı yaşanmaz.
                        </div>
                    </details>

                    <details class="arv-faq-item">
                        <summary>
                            <span class="arv-faq-icon">
                                <!-- Taşıma: karşılıklı transfer okları -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4 9h15" />
                                    <path d="m15.5 5.5 3.5 3.5-3.5 3.5" />
                                    <path d="M20 15H5" />
                                    <path d="M8.5 11.5 5 15l3.5 3.5" />
                                </svg>
                            </span>
                            <span class="arv-faq-q">Alanı sonradan büyütebilir miyim?</span>
                            <span class="arv-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="arv-faq-body">
                            Evet. Paketinizi üst plana yükseltebilirsiniz; dosyalarınız taşınmaz, aynı alanda
                            kalır ve yalnızca kotanız artar.
                        </div>
                    </details>
                    </div>
                </div>
            </div>
        </section>

        <!-- Kapanış -->
        <section class="arv-final">
            <div class="container">
                <h3><i class="fas fa-cloud-arrow-up"></i> Arşiv Hosting ile Başlayın</h3>
                <p>FTP, SFTP ve WebDAV ile şifreli yedek alanı</p>
                <div class="arv-final-actions">
                    <a href="#paketler" class="arv-final-btn">
                        <i class="fas fa-rocket"></i> Paketleri İncele
                    </a>
                    <a href="contact.php" class="arv-final-link">
                        <i class="fas fa-comments"></i> Önce Soru Sormak İsterim
                    </a>
                </div>
            </div>
        </section>
    </div>

    <script>
        // "+N özellik daha" düğmesi: listedeki gizli satırları açar
        document.querySelectorAll('.arv-more').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var list = document.getElementById(btn.dataset.target);
                if (!list) return;
                var expanded = list.classList.toggle('is-clipped') === false;
                btn.setAttribute('aria-expanded', String(expanded));
                btn.textContent = expanded
                    ? 'Daha az göster'
                    : '+ ' + (list.children.length - 6) + ' özellik daha';
            });
        });
    </script>

    <?php require_once __DIR__ . '/theme/includes/footer.php'; ?>

<?php elseif ($isKurumsalPage): ?>
    <?php
    /**
     * Kurumsal Hosting - linux-hosting sayfasıyla aynı bileşen düzeni.
     * Vurgu rengi olarak sitenin ana rengi kullanılır (Linux sayfası turuncu).
     */

    /** "500 MB Disk Alanı" -> ['value' => '500 MB', 'label' => 'Disk Alanı'] */
    $khAyristir = static function (string $line): array {
        $line = trim($line);
        if (preg_match('/^(\d[\d.,]*\s*(?:MB|GB|TB|Adet)?)\s+(.+)$/iu', $line, $m)) {
            return ['value' => trim($m[1]), 'label' => trim($m[2])];
        }
        if (preg_match('/^(ücretsiz|limitsiz|sınırsız)\s+(.+)$/iu', $line, $m)) {
            return ['value' => mb_convert_case($m[1], MB_CASE_TITLE, 'UTF-8'), 'label' => trim($m[2])];
        }
        return ['value' => '', 'label' => $line];
    };

    /** Etikete göre vitrin ikonu */
    $khIkon = static function (string $label): string {
        if (preg_match('/disk|alan/iu', $label))
            return 'fa-hard-drive';
        if (preg_match('/trafik|bant/iu', $label))
            return 'fa-right-left';
        if (preg_match('/e-?posta|mail/iu', $label))
            return 'fa-envelope';
        if (preg_match('/ftp/iu', $label))
            return 'fa-folder-open';
        if (preg_match('/ssl|sertifika/iu', $label))
            return 'fa-lock';
        if (preg_match('/veri\s?taban|mysql/iu', $label))
            return 'fa-database';
        return 'fa-check';
    };

    // Paketleri ayrıştır
    $khPaketler = [];
    foreach ($products as $p) {
        $satirlar = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) ($p['description'] ?? '')) as $line) {
            if (trim($line) !== '') {
                $satirlar[] = $khAyristir($line);
            }
        }
        // İlk dördü vitrin kutusuna, kalanı özellik listesine
        $khPaketler[] = [
            'urun' => $p,
            'specs' => array_slice(array_filter($satirlar, fn($o) => $o['value'] !== ''), 0, 4),
            'tumu' => $satirlar,
        ];
    }

    $khAdet = count($khPaketler);

    // Disk büyüklüklerini MB cinsinden karşılaştır (vitrin çubukları için)
    $khDiskMb = static function (array $paket): float {
        foreach ($paket['tumu'] as $o) {
            if (preg_match('/disk/iu', $o['label']) && preg_match('/^([\d.,]+)\s*(MB|GB|TB)/iu', $o['value'], $m)) {
                $n = (float) str_replace(',', '.', $m[1]);
                $birim = strtoupper($m[2]);
                return $birim === 'TB' ? $n * 1024 * 1024 : ($birim === 'GB' ? $n * 1024 : $n);
            }
        }
        return 0;
    };
    $khEnBuyukDisk = 0;
    foreach ($khPaketler as $pk) {
        $khEnBuyukDisk = max($khEnBuyukDisk, $khDiskMb($pk));
    }
    ?>

    <style>
        /* ==========================================
           Kurumsal Hosting
           Düzen ve bileşenler linux-hosting ile aynı;
           vurgu rengi sitenin ana rengidir.
           ========================================== */
        .kh {
            --kh-accent: var(--primary);
            --kh-accent-light: var(--primary-light);
            --kh-accent-dark: var(--primary-dark);
            --kh-soft: color-mix(in srgb, var(--primary) 12%, transparent);
            --kh-line: color-mix(in srgb, var(--primary) 28%, transparent);
            --kh-gradient: var(--gradient-primary);
        }

        /* ===== Hero ===== */
        .kh-hero {
            position: relative;
            overflow: hidden;
            background: var(--gradient-hero);
            padding: var(--space-6) 0 0;
        }

        .kh-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 55% 50% at 12% 30%, color-mix(in srgb, var(--primary) 22%, transparent) 0%, transparent 62%),
                radial-gradient(ellipse 45% 45% at 88% 10%, color-mix(in srgb, var(--secondary) 16%, transparent) 0%, transparent 58%);
            pointer-events: none;
        }

        .kh-hero>.container {
            position: relative;
            z-index: 1;
        }

        /* Tek kolon, ortalanmis: hero'da ayrica gorsel yok */
        .kh-hero-grid {
            max-width: 780px;
            margin: 0 auto;
            padding-bottom: var(--space-6);
            text-align: center;
        }

        .kh-hero-text .kh-lead {
            margin-left: auto;
            margin-right: auto;
        }

        .kh-hero-text .kh-stack {
            justify-content: center;
        }

        .kh-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 50px;
            background: var(--kh-soft);
            border: 1px solid var(--kh-line);
            color: var(--kh-accent-light);
            font-size: var(--text-sm);
            font-weight: 600;
            margin-bottom: var(--space-4);
        }

        .kh-title {
            font-size: clamp(32px, 3.2vw + 16px, 50px);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -0.035em;
            margin-bottom: var(--space-3);
            color: var(--text-primary);
            text-wrap: balance;
        }

        .kh-title span {
            background: var(--kh-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .kh-lead {
            font-size: var(--text-md);
            color: var(--text-muted);
            line-height: 1.65;
            max-width: 48ch;
            margin-bottom: var(--space-5);
            text-wrap: pretty;
        }

        .kh-stack {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .kh-chip {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 7px 14px 7px 7px;
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
        }

        .kh-chip i {
            width: 26px;
            height: 26px;
            flex-shrink: 0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #fff;
        }

        .kh-chip i.is-mail {
            background: linear-gradient(135deg, #6366f1, #4338ca);
        }

        .kh-chip i.is-ssl {
            background: linear-gradient(135deg, #22c55e, #15803d);
        }

        .kh-chip i.is-disk {
            background: linear-gradient(135deg, #0ea5e9, #0369a1);
        }

        .kh-chip i.is-support {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        /* ===== Hero görseli: tarayıcı penceresi ===== */
        /* ===== Kategori sekmeleri ===== */
        .kh-tabs-wrap {
            position: relative;
            border-top: 1px solid var(--border-color);
            background: color-mix(in srgb, var(--bg-primary) 65%, transparent);
            backdrop-filter: blur(12px);
        }

        .kh-tabs {
            display: flex;
            gap: var(--space-2);
            overflow-x: auto;
            padding: var(--space-3) var(--space-4);
            scrollbar-width: none;
            scroll-snap-type: x proximity;
        }

        .kh-tabs::-webkit-scrollbar {
            display: none;
        }

        .kh-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            scroll-snap-align: start;
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            background: var(--surface-1);
            color: var(--text-muted);
            font-size: var(--text-sm);
            font-weight: 600;
            white-space: nowrap;
            transition: all var(--transition-normal) var(--ease-out);
        }

        .kh-tab:hover {
            color: var(--text-primary);
            border-color: var(--kh-line);
            background: var(--surface-2);
        }

        .kh-tab.is-active {
            background: var(--kh-gradient);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 6px 18px color-mix(in srgb, var(--primary) 32%, transparent);
        }

        /* ===== Bölümler ===== */
        .kh-section {
            padding: var(--section-padding) 0;
        }

        .kh-hero+.kh-section {
            padding-top: var(--space-7);
        }

        .kh-section.is-alt {
            background: var(--bg-primary);
        }

        .kh-head {
            text-align: center;
            max-width: 680px;
            margin: 0 auto var(--space-7);
        }

        .kh-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 50px;
            background: var(--kh-soft);
            border: 1px solid var(--kh-line);
            color: var(--kh-accent-light);
            font-size: var(--text-sm);
            font-weight: 600;
            margin-bottom: var(--space-4);
        }

        .kh-head h2 {
            font-size: var(--text-3xl);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.15;
            color: var(--text-primary);
            text-wrap: balance;
        }

        .kh-head h2 span {
            background: var(--kh-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .kh-head p {
            margin-top: var(--space-3);
            font-size: var(--text-md);
            color: var(--text-muted);
            text-wrap: pretty;
        }

        /* ===== Paket kartları ===== */
        .kh-plans {
            display: grid;
            /* Sutun sayisi paket adedinden gelir; auto-fit reflow yapmaz */
            grid-template-columns: repeat(var(--plan-cols, 4), minmax(0, 1fr));
            gap: var(--space-5);
            /* Kartlar eşit yükseklikte olsun, sipariş butonları hizalansın */
            align-items: stretch;
        }

        .kh-plans[data-count="1"] {
            --plan-cols: 1;
            max-width: 420px;
            margin-inline: auto;
        }

        .kh-plans[data-count="2"] {
            --plan-cols: 2;
        }

        .kh-plans[data-count="3"] {
            --plan-cols: 3;
        }

        @media (max-width: 1180px) {
            .kh-plans[data-count] {
                --plan-cols: 2;
            }
        }

        @media (max-width: 680px) {
            .kh-plans[data-count] {
                --plan-cols: 1;
            }
        }

        .kh-plans[data-count="1"] {
            grid-template-columns: minmax(300px, 420px);
            justify-content: center;
        }

        .kh-plans[data-count="2"] {
            grid-template-columns: repeat(2, minmax(300px, 430px));
            justify-content: center;
        }

        .kh-plans[data-count="3"] {
            grid-template-columns: repeat(3, minmax(280px, 400px));
            justify-content: center;
        }

        .kh-plan {
            position: relative;
            display: flex;
            flex-direction: column;
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: transform var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .kh-plan:hover {
            transform: translateY(-6px);
            border-color: var(--kh-line);
            box-shadow: var(--shadow-lg);
        }

        .kh-plan.is-popular {
            border-color: var(--kh-accent);
            box-shadow: 0 0 0 1px var(--kh-accent),
                0 24px 48px -18px color-mix(in srgb, var(--primary) 45%, transparent);
        }

        .kh-ribbon {
            position: absolute;
            top: 0;
            right: var(--space-5);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 0 0 10px 10px;
            background: var(--kh-gradient);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .kh-plan-top {
            padding: var(--space-6) var(--space-5) var(--space-5);
            border-bottom: 1px solid var(--border-color);
        }

        .kh-plan-name {
            font-size: var(--text-xl);
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .kh-plan-group {
            font-size: var(--text-sm);
            color: var(--text-muted);
            margin-bottom: var(--space-5);
        }

        .kh-price {
            display: flex;
            align-items: baseline;
            gap: 4px;
            font-variant-numeric: tabular-nums;
        }

        .kh-price .cur {
            font-size: var(--text-lg);
            font-weight: 700;
            color: var(--kh-accent);
        }

        .kh-price .val {
            font-size: clamp(34px, 3vw + 22px, 46px);
            font-weight: 800;
            letter-spacing: -0.04em;
            line-height: 1;
            color: var(--text-primary);
        }

        .kh-price .per {
            font-size: var(--text-base);
            color: var(--text-muted);
            font-weight: 500;
        }

        .kh-price-note {
            margin-top: var(--space-2);
            font-size: var(--text-sm);
            color: var(--text-muted);
        }

        .kh-save {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: var(--space-3);
            padding: 5px 12px;
            border-radius: 50px;
            background: color-mix(in srgb, #22c55e 15%, transparent);
            border: 1px solid color-mix(in srgb, #22c55e 32%, transparent);
            color: #4ade80;
            font-size: var(--text-xs);
            font-weight: 700;
        }

        .kh-save.is-placeholder {
            visibility: hidden;
        }

        /* Teknik özet kutucukları */
        .kh-specs {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1px;
            background: var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .kh-spec {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4);
            background: var(--bg-primary);
        }

        .kh-spec i {
            width: 30px;
            height: 30px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: var(--kh-soft);
            color: var(--kh-accent-light);
            font-size: 13px;
        }

        .kh-spec b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.25;
        }

        .kh-spec span {
            font-size: 11px;
            color: var(--text-muted);
        }

        .kh-plan-body {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: var(--space-5);
        }

        .kh-features {
            list-style: none;
            margin: 0 0 var(--space-5);
            padding: 0;
            display: grid;
            gap: 2px;
        }

        .kh-features li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 7px 0;
            font-size: var(--text-sm);
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .kh-features li i {
            margin-top: 3px;
            font-size: 11px;
            color: #22c55e;
            flex-shrink: 0;
        }

        .kh-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: auto;
            padding: 15px 24px;
            border-radius: var(--radius-md);
            border: 1px solid transparent;
            background: var(--kh-gradient);
            color: #fff;
            font-size: var(--text-base);
            font-weight: 700;
            box-shadow: 0 8px 22px color-mix(in srgb, var(--primary) 28%, transparent);
            transition: transform var(--transition-fast) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .kh-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 34px color-mix(in srgb, var(--primary) 45%, transparent);
        }

        .kh-empty {
            text-align: center;
            padding: var(--space-9) var(--space-5);
            border: 1px dashed var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--surface-1);
            color: var(--text-muted);
        }

        /* ===== Her pakette standart ===== */
        .kh-included {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--surface-1);
            overflow: hidden;
        }

        .kh-included-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1px;
            background: var(--border-color);
        }

        .kh-inc {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            background: var(--bg-primary);
            transition: background-color var(--transition-normal) var(--ease-out);
        }

        .kh-inc:hover {
            background: color-mix(in srgb, var(--primary) 6%, var(--bg-primary));
        }

        .kh-inc i {
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: var(--kh-soft);
            color: var(--kh-accent-light);
            font-size: 14px;
        }

        .kh-inc b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .kh-inc span {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        /* ===== Vitrin: disk karşılaştırma ===== */
        .kh-spotlight {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 0.85fr);
            gap: var(--space-7);
            align-items: center;
            padding: var(--space-7);
            margin-bottom: var(--space-5);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background:
                radial-gradient(ellipse 60% 90% at 100% 50%, color-mix(in srgb, var(--primary) 10%, transparent) 0%, transparent 70%),
                var(--surface-1);
        }

        .kh-spotlight-tag {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 14px;
            border-radius: 50px;
            background: var(--kh-soft);
            border: 1px solid var(--kh-line);
            color: var(--kh-accent-light);
            font-size: var(--text-xs);
            font-weight: 700;
            margin-bottom: var(--space-4);
        }

        .kh-spotlight h3 {
            font-size: var(--text-2xl);
            font-weight: 800;
            letter-spacing: -0.025em;
            color: var(--text-primary);
            margin-bottom: var(--space-3);
        }

        .kh-spotlight p {
            font-size: var(--text-md);
            color: var(--text-muted);
            line-height: 1.7;
            margin-bottom: var(--space-5);
            max-width: 46ch;
        }

        .kh-spotlight-points {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .kh-spotlight-points li {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 13px;
            border-radius: 50px;
            background: var(--surface-2);
            border: 1px solid var(--border-color);
            font-size: var(--text-xs);
            font-weight: 600;
            color: var(--text-secondary);
        }

        .kh-spotlight-points i {
            color: #4ade80;
            font-size: 10px;
        }

        .kh-bars {
            display: grid;
            gap: var(--space-4);
        }

        .kh-bar-row {
            display: grid;
            grid-template-columns: 108px minmax(0, 1fr) 64px;
            align-items: center;
            gap: var(--space-3);
        }

        .kh-bar-label {
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--text-muted);
        }

        .kh-bar {
            height: 14px;
            border-radius: 50px;
            background: var(--surface-3);
            overflow: hidden;
        }

        .kh-bar span {
            display: block;
            height: 100%;
            border-radius: 50px;
            background: var(--text-gray);
        }

        .kh-bar-val {
            font-size: var(--text-sm);
            font-weight: 700;
            color: var(--text-muted);
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .kh-bar-row.is-lead .kh-bar-label,
        .kh-bar-row.is-lead .kh-bar-val {
            color: var(--text-primary);
        }

        .kh-bar-row.is-lead .kh-bar span {
            background: var(--kh-gradient);
        }

        .kh-bar-note {
            font-size: var(--text-xs);
            color: var(--text-gray);
            line-height: 1.5;
            margin: 0;
        }

        /* ===== Teknoloji kartları ===== */
        .kh-tech {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: var(--space-5);
        }

        .kh-tech-card {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: var(--space-4);
            padding: var(--space-5);
            background: var(--surface-1);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: transform var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .kh-tech-card::before {
            content: '';
            position: absolute;
            inset: 0 0 auto 0;
            height: 3px;
            background: var(--tint, var(--kh-gradient));
            transform: scaleX(0);
            transition: transform var(--transition-normal) var(--ease-out);
        }

        .kh-tech-card:hover {
            transform: translateY(-4px);
            background: var(--surface-2);
        }

        .kh-tech-card:hover::before {
            transform: scaleX(1);
        }

        .kh-tech-icon {
            width: 46px;
            height: 46px;
            flex-shrink: 0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            color: #fff;
            background: var(--tint, var(--kh-gradient));
        }

        .kh-tech-card h4 {
            font-size: var(--text-lg);
            font-weight: 700;
            letter-spacing: -0.015em;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .kh-tech-card p {
            font-size: var(--text-sm);
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* ===== SSS ===== */
        .kh-faq-layout {
            display: grid;
            grid-template-columns: minmax(0, 330px) minmax(0, 1fr);
            gap: var(--space-8);
            align-items: start;
        }

        .kh-head.is-left {
            text-align: left;
            max-width: 100%;
            margin: 0 0 var(--space-5);
        }

        .kh-faq-visual {
            width: 100%;
            max-width: 330px;
        }

        .kh-faq-visual svg {
            width: 100%;
            height: auto;
            display: block;
        }

        .kh-faq-help {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin-top: var(--space-5);
            padding: var(--space-4);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--surface-1);
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .kh-faq-help:hover {
            border-color: var(--kh-line);
            background: var(--surface-2);
        }

        .kh-faq-help i {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--kh-gradient);
            color: #fff;
            font-size: 15px;
        }

        .kh-faq-help b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .kh-faq-help span {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        .kh-faq {
            display: grid;
            gap: var(--space-3);
        }

        .kh-faq-item {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--surface-1);
            overflow: hidden;
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .kh-faq-item[open] {
            border-color: var(--kh-line);
            background: var(--surface-2);
        }

        .kh-faq-item summary {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            cursor: pointer;
            font-size: var(--text-md);
            font-weight: 600;
            color: var(--text-primary);
            list-style: none;
        }

        .kh-faq-item summary::-webkit-details-marker {
            display: none;
        }

        .kh-faq-item summary::marker {
            content: '';
        }

        .kh-faq-item summary:hover {
            color: var(--kh-accent-light);
        }

        .kh-faq-item summary:focus-visible {
            outline: none;
            box-shadow: var(--focus-ring);
        }

        .kh-faq-icon {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--kh-soft);
            border: 1px solid var(--kh-line);
            color: var(--kh-accent-light);
            transition: background-color var(--transition-normal) var(--ease-out),
                color var(--transition-normal) var(--ease-out);
        }

        .kh-faq-icon svg {
            width: 19px;
            height: 19px;
            display: block;
        }

        .kh-faq-item[open] .kh-faq-icon {
            background: var(--kh-gradient);
            border-color: transparent;
            color: #fff;
        }

        .kh-faq-q {
            flex: 1;
            min-width: 0;
        }

        .kh-faq-sign {
            width: 28px;
            height: 28px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--kh-soft);
            color: var(--kh-accent-light);
            font-size: 12px;
            transition: transform var(--transition-normal) var(--ease-out);
        }

        .kh-faq-item[open] .kh-faq-sign {
            transform: rotate(45deg);
        }

        .kh-faq-body {
            padding: 0 var(--space-5) var(--space-5) calc(var(--space-5) + 38px + var(--space-3));
            font-size: var(--text-base);
            color: var(--text-muted);
            line-height: 1.75;
            max-width: 72ch;
        }

        /* ===== Kapanış ===== */
        .kh-final {
            position: relative;
            overflow: hidden;
            padding: var(--space-8) 0;
            background: var(--kh-gradient);
            color: #fff;
            text-align: center;
        }

        .kh-final::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 100% at 50% 0%, rgba(255, 255, 255, 0.18) 0%, transparent 62%),
                radial-gradient(ellipse 50% 80% at 88% 100%, rgba(0, 0, 0, 0.16) 0%, transparent 60%);
            pointer-events: none;
        }

        .kh-final>.container {
            position: relative;
            z-index: 1;
        }

        .kh-final h3 {
            font-size: var(--text-2xl);
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: var(--space-3);
            text-wrap: balance;
        }

        .kh-final p {
            font-size: var(--text-md);
            opacity: 0.92;
            margin-bottom: var(--space-6);
        }

        .kh-final-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: var(--space-3);
        }

        .kh-final-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 32px;
            border-radius: var(--radius-md);
            background: #fff;
            color: var(--kh-accent-dark);
            font-size: var(--text-md);
            font-weight: 700;
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.2);
            transition: transform var(--transition-fast) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .kh-final-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.28);
        }

        .kh-final-link {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 15px 26px;
            border-radius: var(--radius-md);
            border: 1px solid rgba(255, 255, 255, 0.45);
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            font-size: var(--text-md);
            font-weight: 600;
            transition: background-color var(--transition-normal) var(--ease-out),
                border-color var(--transition-normal) var(--ease-out);
        }

        .kh-final-link:hover {
            background: rgba(255, 255, 255, 0.22);
            border-color: #fff;
        }

        /* ===== Duyarlılık ===== */
        @media (max-width: 1100px) {
            .kh-included-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .kh-spotlight {
                grid-template-columns: 1fr;
                gap: var(--space-6);
                padding: var(--space-6);
            }

            .kh-spotlight p {
                max-width: 100%;
            }
        }

        @media (max-width: 992px) {
            .kh-lead {
                max-width: 100%;
            }

            .kh-tech {
                grid-template-columns: 1fr;
            }

            .kh-faq-layout {
                grid-template-columns: 1fr;
                gap: var(--space-6);
                justify-items: center;
            }

            .kh-faq-aside {
                max-width: 460px;
                width: 100%;
            }

            .kh-head.is-left {
                text-align: center;
            }

            .kh-faq-visual {
                margin: 0 auto;
            }

            .kh-faq {
                width: 100%;
            }
        }

        @media (max-width: 640px) {

            .kh-plans,
            .kh-plans[data-count="2"],
            .kh-plans[data-count="3"] {
                grid-template-columns: 1fr;
            }

            .kh-included-grid {
                grid-template-columns: 1fr;
            }

            .kh-bar-row {
                grid-template-columns: 88px minmax(0, 1fr) 56px;
                gap: var(--space-2);
            }

            .kh-final-actions {
                flex-direction: column;
            }

            .kh-final-actions>* {
                width: 100%;
                justify-content: center;
            }

            .kh-faq-body {
                padding-left: var(--space-5);
            }

            .kh-faq-item summary {
                padding-left: var(--space-4);
                padding-right: var(--space-4);
            }
        }
    </style>

    <div class="kh">

        <!-- Hero -->
        <section class="kh-hero">
            <div class="container">
                <div class="kh-hero-grid">
                    <div class="kh-hero-text">
                        <span class="kh-eyebrow"><i class="fas fa-building"></i> Kurumsal Barındırma</span>
                        <h1 class="kh-title"><span>Kurumsal Hosting</span> Paketleri</h1>
                        <p class="kh-lead">
                            Şirket siteniz ve kurumsal e-posta hesaplarınız tek pakette. Sunucu yönetimi
                            bizde, ücretsiz SSL ve günlük yedekleme standart.
                        </p>

                        <div class="kh-stack">
                            <span class="kh-chip"><i class="fas fa-envelope is-mail"></i> Kurumsal e-posta</span>
                            <span class="kh-chip"><i class="fas fa-lock is-ssl"></i> Ücretsiz SSL</span>
                            <span class="kh-chip"><i class="fas fa-hard-drive is-disk"></i> NVMe SSD</span>
                            <span class="kh-chip"><i class="fas fa-headset is-support"></i> 7/24 destek</span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Kategori sekmeleri -->
            <div class="kh-tabs-wrap">
                <div class="container">
                    <div class="kh-tabs">
                        <a href="store.php" class="kh-tab"><i class="fas fa-th-large"></i> Tüm Ürünler</a>
                        <a href="store.php?group=kurumsal-hosting" class="kh-tab is-active"><i
                                class="fas fa-building"></i> Kurumsal Hosting</a>
                        <?php foreach ($allGroups as $g):
                            if ($g['slug'] === 'kurumsal-hosting') {
                                continue;
                            }
                            $gCount = Database::fetchColumn("SELECT COUNT(*) FROM products WHERE group_id = ? AND is_active = 1", [$g['id']]);
                            if ($gCount <= 0) {
                                continue;
                            }
                            ?>
                            <a href="store.php?group=<?= htmlspecialchars($g['slug']) ?>" class="kh-tab">
                                <i class="fas <?= $typeConfig[$g['type'] ?? 'hosting']['icon'] ?? 'fa-box' ?>"></i>
                                <?= htmlspecialchars($g['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Paketler -->
        <section class="kh-section" id="paketler">
            <div class="container">
                <div class="kh-head">
                    <span class="kh-badge"><i class="fas fa-building"></i> Kurumsal Hosting Paketleri</span>
                    <h2>Şirketinize Uygun <span>Hosting Planı</span></h2>
                    <p>Tüm paketlerde kurulum ücreti alınmaz, SSL sertifikası ücretsizdir.</p>
                </div>

                <?php if (empty($khPaketler)): ?>
                    <div class="kh-empty">Bu kategoride henüz ürün bulunmuyor.</div>
                <?php else:
                    // En az bir pakette yıllık indirim varsa, olmayanlarda rozet
                    // yüksekliği kadar boşluk bırakılır; kartlar hizalı kalır.
                    $khAnySaving = false;
                    foreach ($products as $p) {
                        $m = (float) ($p['price_monthly'] ?? 0);
                        $a = (float) ($p['price_annually'] ?? 0);
                        if ($m > 0 && $a > 0 && $a < $m * 12) {
                            $khAnySaving = true;
                            break;
                        }
                    }

                    // Öne çıkan ürün işaretliyse onu, değilse ortadaki paketi vurgula
                    $khPopular = -1;
                    foreach ($products as $i => $p) {
                        if (!empty($p['is_featured'])) {
                            $khPopular = $i;
                            break;
                        }
                    }
                    if ($khPopular === -1 && $khAdet > 2) {
                        $khPopular = (int) floor(($khAdet - 1) / 2);
                    }
                    ?>
                    <div class="kh-plans" data-count="<?= $khAdet ?>">
                        <?php foreach ($khPaketler as $index => $pk):
                            $urun = $pk['urun'];
                            $isPopular = ($index === $khPopular);
                            $price = (float) ($urun['price_monthly'] ?? 0);
                            $annual = (float) ($urun['price_annually'] ?? 0);
                            $setup = (float) ($urun['setup_fee'] ?? 0);

                            $savePercent = 0;
                            if ($price > 0 && $annual > 0 && $annual < $price * 12) {
                                $savePercent = (int) round((1 - ($annual / ($price * 12))) * 100);
                            }

                            // Vitrin kutusuna girmeyen satırlar özellik listesine
                            $specLabels = array_map(fn($s) => $s['label'], $pk['specs']);
                            $features = [];
                            foreach ($pk['tumu'] as $o) {
                                if (!in_array($o['label'], $specLabels, true)) {
                                    $features[] = ($o['value'] !== '' ? $o['value'] . ' ' : '') . $o['label'];
                                }
                            }
                            ?>
                            <article class="kh-plan <?= $isPopular ? 'is-popular' : '' ?>">
                                <?php if ($isPopular): ?>
                                    <div class="kh-ribbon"><i class="fas fa-star"></i> En Popüler</div>
                                <?php endif; ?>

                                <div class="kh-plan-top">
                                    <div class="kh-plan-name"><?= htmlspecialchars($urun['name']) ?></div>
                                    <div class="kh-plan-group">Kurumsal Hosting</div>

                                    <div class="kh-price">
                                        <span class="cur">₺</span>
                                        <span class="val"><?= number_format(floor($price), 0, ',', '.') ?></span>
                                        <span class="per">/ay</span>
                                    </div>
                                    <div class="kh-price-note">
                                        <?= $setup > 0
                                            ? '+ ₺' . number_format($setup, 0, ',', '.') . ' tek seferlik kurulum'
                                            : 'Kurulum ücreti yok' ?>
                                    </div>
                                    <?php if ($savePercent > 0): ?>
                                        <span class="kh-save"><i class="fas fa-tag"></i> Yıllık ödemede %<?= $savePercent ?>
                                            indirim</span>
                                    <?php elseif ($khAnySaving): ?>
                                        <span class="kh-save is-placeholder" aria-hidden="true"><i class="fas fa-tag"></i>
                                            &nbsp;</span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($pk['specs'])): ?>
                                    <div class="kh-specs">
                                        <?php foreach ($pk['specs'] as $s): ?>
                                            <div class="kh-spec">
                                                <i class="fas <?= $khIkon($s['label']) ?>"></i>
                                                <div>
                                                    <b><?= htmlspecialchars($s['value']) ?></b>
                                                    <span><?= htmlspecialchars($s['label']) ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="kh-plan-body">
                                    <ul class="kh-features">
                                        <?php foreach ($features as $f): ?>
                                            <li><i class="fas fa-check"></i> <?= htmlspecialchars($f) ?></li>
                                        <?php endforeach; ?>
                                        <li><i class="fas fa-check"></i> Günlük yedekleme</li>
                                        <li><i class="fas fa-check"></i> 7/24 teknik destek</li>
                                    </ul>

                                    <a class="kh-cta" href="/client/order-configure.php?id=<?= (int) $urun['id'] ?>">
                                        <i class="fas fa-shopping-cart"></i> Sipariş Ver
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Her pakette standart -->
        <section class="kh-section is-alt">
            <div class="container">
                <div class="kh-head">
                    <span class="kh-badge"><i class="fas fa-check-double"></i> Pakete Dahil</span>
                    <h2>Hepsi <span>Her Pakette Standart</span></h2>
                    <p>Aşağıdakiler için ek ücret ödemezsiniz; en küçük pakette de aynen geçerli.</p>
                </div>

                <div class="kh-included">
                    <div class="kh-included-grid">
                        <div class="kh-inc">
                            <i class="fas fa-lock"></i>
                            <div>
                                <b>Ücretsiz SSL</b>
                                <span>Let's Encrypt, otomatik yenileme</span>
                            </div>
                        </div>
                        <div class="kh-inc">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <b>Kurumsal E-posta</b>
                                <span>Kendi alan adınızla</span>
                            </div>
                        </div>
                        <div class="kh-inc">
                            <i class="fas fa-clock-rotate-left"></i>
                            <div>
                                <b>Günlük Yedek</b>
                                <span>Talep üzerine geri yükleme</span>
                            </div>
                        </div>
                        <div class="kh-inc">
                            <i class="fas fa-shield-halved"></i>
                            <div>
                                <b>DDoS Koruması</b>
                                <span>Ağ seviyesinde filtreleme</span>
                            </div>
                        </div>
                        <div class="kh-inc">
                            <i class="fas fa-database"></i>
                            <div>
                                <b>MySQL / MariaDB</b>
                                <span>Veritabanı yönetimi</span>
                            </div>
                        </div>
                        <div class="kh-inc">
                            <i class="fas fa-folder-open"></i>
                            <div>
                                <b>FTP Erişimi</b>
                                <span>Çoklu kullanıcı desteği</span>
                            </div>
                        </div>
                        <div class="kh-inc">
                            <i class="fas fa-gauge-high"></i>
                            <div>
                                <b>NVMe SSD</b>
                                <span>RAID korumalı disk</span>
                            </div>
                        </div>
                        <div class="kh-inc">
                            <i class="fas fa-headset"></i>
                            <div>
                                <b>7/24 Destek</b>
                                <span>Uzman teknik ekip</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Altyapı -->
        <section class="kh-section">
            <div class="container">
                <div class="kh-head">
                    <span class="kh-badge"><i class="fas fa-server"></i> Altyapı</span>
                    <h2>Paketler Arasındaki <span>Fark</span></h2>
                    <p>Aynı altyapı, farklı kapasite. Şirketiniz büyüdükçe üst pakete geçebilirsiniz.</p>
                </div>

                <?php if ($khEnBuyukDisk > 0): ?>
                    <div class="kh-spotlight">
                        <div class="kh-spotlight-text">
                            <span class="kh-spotlight-tag"><i class="fas fa-hard-drive"></i> Disk Kapasitesi</span>
                            <h3>İhtiyacınız kadar alan</h3>
                            <p>
                                Tüm paketler aynı NVMe SSD altyapısında çalışır; aralarındaki fark
                                ayrılan kapasitedir. Paket yükseltmede site taşınmaz, kaynak artırılır.
                            </p>
                            <ul class="kh-spotlight-points">
                                <li><i class="fas fa-check"></i> NVMe SSD</li>
                                <li><i class="fas fa-check"></i> RAID koruması</li>
                                <li><i class="fas fa-check"></i> Kesintisiz yükseltme</li>
                            </ul>
                        </div>

                        <div class="kh-bars">
                            <?php foreach ($khPaketler as $i => $pk):
                                $mb = $khDiskMb($pk);
                                $oran = $khEnBuyukDisk > 0 ? max(4, ($mb / $khEnBuyukDisk) * 100) : 4;
                                $etiket = '';
                                foreach ($pk['tumu'] as $o) {
                                    if (preg_match('/disk/iu', $o['label'])) {
                                        $etiket = $o['value'];
                                        break;
                                    }
                                }
                                ?>
                                <div class="kh-bar-row <?= ($i === $khPopular) ? 'is-lead' : '' ?>">
                                    <span class="kh-bar-label"><?= htmlspecialchars($pk['urun']['name']) ?></span>
                                    <div class="kh-bar"><span style="width: <?= round($oran, 1) ?>%"></span></div>
                                    <span class="kh-bar-val"><?= htmlspecialchars($etiket) ?></span>
                                </div>
                            <?php endforeach; ?>
                            <p class="kh-bar-note">Paketlerin disk kapasitesi karşılaştırması.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="kh-tech">
                    <div class="kh-tech-card" style="--tint: linear-gradient(135deg, #6366f1, #4338ca);">
                        <div class="kh-tech-icon"><i class="fas fa-envelope"></i></div>
                        <div>
                            <h4>Kurumsal e-posta</h4>
                            <p>Kendi alan adınızla hesaplar, webmail ve POP3/IMAP erişimi.</p>
                        </div>
                    </div>
                    <div class="kh-tech-card" style="--tint: linear-gradient(135deg, #22c55e, #15803d);">
                        <div class="kh-tech-icon"><i class="fas fa-lock"></i></div>
                        <div>
                            <h4>Ücretsiz SSL</h4>
                            <p>Let's Encrypt sertifikası tüm paketlerde dahil, süresi dolmadan yenilenir.</p>
                        </div>
                    </div>
                    <div class="kh-tech-card" style="--tint: linear-gradient(135deg, #0ea5e9, #0369a1);">
                        <div class="kh-tech-icon"><i class="fas fa-clock-rotate-left"></i></div>
                        <div>
                            <h4>Günlük yedekleme</h4>
                            <p>Sunucu yedeği düzenli alınır; talebiniz üzerine geri yükleme yapılır.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sık sorulanlar -->
        <section class="kh-section is-alt">
            <div class="container">
                <div class="kh-faq-layout">
                    <div class="kh-faq-aside">
                        <div class="kh-head is-left">
                            <span class="kh-badge"><i class="fas fa-circle-question"></i> Sık Sorulanlar</span>
                            <h2>Aklınıza <span>Takılanlar</span></h2>
                            <p>Satın almadan önce en çok merak edilenleri derledik.</p>
                        </div>

                        <div class="kh-faq-visual">
                            <svg viewBox="0 0 320 272" fill="none" aria-hidden="true">
                                <defs>
                                    <linearGradient id="khQmark" x1="0" y1="0" x2="1" y2="1">
                                        <stop offset="0" stop-color="#818cf8" />
                                        <stop offset="1" stop-color="#4338ca" />
                                    </linearGradient>
                                    <radialGradient id="khHalo" cx="0.5" cy="0.5" r="0.5">
                                        <stop offset="0" stop-color="#6366f1" stop-opacity="0.20" />
                                        <stop offset="1" stop-color="#6366f1" stop-opacity="0" />
                                    </radialGradient>
                                </defs>

                                <ellipse cx="160" cy="136" rx="152" ry="122" fill="url(#khHalo)" />

                                <path d="M276 242 v22 l-26 -22 z" fill="var(--surface-2)" stroke="var(--border-color)"
                                    stroke-width="2" stroke-linejoin="round" />
                                <rect x="104" y="150" width="200" height="92" rx="22" fill="var(--surface-2)"
                                    stroke="var(--border-color)" stroke-width="2" />
                                <rect x="132" y="172" width="140" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.55" />
                                <rect x="132" y="194" width="118" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.4" />
                                <rect x="132" y="216" width="78" height="8" rx="4" fill="var(--text-gray)"
                                    opacity="0.28" />

                                <path d="M44 126 v22 l26 -22 z" fill="rgba(99,102,241,0.12)" stroke="#6366f1"
                                    stroke-width="2" stroke-linejoin="round" />
                                <rect x="16" y="26" width="196" height="100" rx="24" fill="rgba(99,102,241,0.12)"
                                    stroke="#6366f1" stroke-width="2" />
                                <rect x="44" y="56" width="112" height="9" rx="4.5" fill="#818cf8" opacity="0.85" />
                                <rect x="44" y="78" width="140" height="9" rx="4.5" fill="#818cf8" opacity="0.6" />
                                <rect x="44" y="100" width="84" height="9" rx="4.5" fill="#818cf8" opacity="0.4" />

                                <circle cx="234" cy="44" r="28" fill="url(#khQmark)" />
                                <text x="234" y="45" text-anchor="middle" dominant-baseline="central" fill="#fff"
                                    font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="34"
                                    font-weight="800">?</text>

                                <circle cx="292" cy="104" r="5" fill="#6366f1" opacity="0.5" />
                                <circle cx="306" cy="126" r="3" fill="#6366f1" opacity="0.3" />
                                <circle cx="24" cy="186" r="4" fill="#6366f1" opacity="0.35" />
                                <circle cx="44" cy="210" r="6" fill="#6366f1" opacity="0.2" />
                            </svg>
                        </div>

                        <a href="contact.php" class="kh-faq-help">
                            <i class="fas fa-headset"></i>
                            <div>
                                <b>Sorunuz listede yok mu?</b>
                                <span>Destek ekibimize yazın, aynı gün dönelim.</span>
                            </div>
                        </a>
                    </div>

                    <div class="kh-faq">
                        <details class="kh-faq-item">
                            <summary>
                                <span class="kh-faq-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="3" y="5" width="18" height="14" rx="2.5" />
                                        <path d="m3.5 7 8.5 6 8.5-6" />
                                    </svg>
                                </span>
                                <span class="kh-faq-q">Kendi alan adımla e-posta hesabı açabilir miyim?</span>
                                <span class="kh-faq-sign"><i class="fas fa-plus"></i></span>
                            </summary>
                            <div class="kh-faq-body">
                                Evet. Paketinizdeki hesap sayısı kadar <strong>ad@sirketiniz.com.tr</strong> biçiminde
                                kurumsal e-posta adresi oluşturabilirsiniz. Webmail üzerinden ya da Outlook gibi
                                programlarla POP3/IMAP ile erişebilirsiniz.
                            </div>
                        </details>

                        <details class="kh-faq-item">
                            <summary>
                                <span class="kh-faq-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="4" y="10.2" width="16" height="10.3" rx="2.4" />
                                        <path d="M8 10.2V7.1a4 4 0 0 1 8 0v3.1" />
                                        <path d="M12 14.4v2.2" />
                                    </svg>
                                </span>
                                <span class="kh-faq-q">SSL sertifikası için ayrıca ödeme yapacak mıyım?</span>
                                <span class="kh-faq-sign"><i class="fas fa-plus"></i></span>
                            </summary>
                            <div class="kh-faq-body">
                                Hayır. Let's Encrypt SSL sertifikası bütün paketlerde ücretsiz sunulur ve süresi
                                dolmadan otomatik yenilenir.
                            </div>
                        </details>

                        <details class="kh-faq-item">
                            <summary>
                                <span class="kh-faq-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M3.2 12a8.8 8.8 0 1 0 2.9-6.5" />
                                        <path d="M3 4.2v4.6h4.6" />
                                        <path d="M12 8.2V12l2.9 1.7" />
                                    </svg>
                                </span>
                                <span class="kh-faq-q">Yedeklerim ne sıklıkla alınıyor?</span>
                                <span class="kh-faq-sign"><i class="fas fa-plus"></i></span>
                            </summary>
                            <div class="kh-faq-body">
                                Sunucu yedekleri düzenli olarak alınır. Geri yükleme ihtiyacınız olduğunda destek
                                ekibimize talep açmanız yeterlidir.
                            </div>
                        </details>

                        <details class="kh-faq-item">
                            <summary>
                                <span class="kh-faq-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M4 9h15" />
                                        <path d="m15.5 5.5 3.5 3.5-3.5 3.5" />
                                        <path d="M20 15H5" />
                                        <path d="M8.5 11.5 5 15l3.5 3.5" />
                                    </svg>
                                </span>
                                <span class="kh-faq-q">Sonradan üst pakete geçebilir miyim?</span>
                                <span class="kh-faq-sign"><i class="fas fa-plus"></i></span>
                            </summary>
                            <div class="kh-faq-body">
                                Evet. Tüm paketler aynı altyapıda çalıştığı için yükseltmede siteniz taşınmaz,
                                yalnızca ayrılan kaynak artırılır. Talebinizi destek ekibimize iletebilirsiniz.
                            </div>
                        </details>

                        <details class="kh-faq-item">
                            <summary>
                                <span class="kh-faq-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="3" y="4" width="18" height="7" rx="2.2" />
                                        <rect x="3" y="13" width="18" height="7" rx="2.2" />
                                        <path d="M7 7.5h.01" />
                                        <path d="M7 16.5h.01" />
                                    </svg>
                                </span>
                                <span class="kh-faq-q">Disk ve trafik limitimi aşarsam ne olur?</span>
                                <span class="kh-faq-sign"><i class="fas fa-plus"></i></span>
                            </summary>
                            <div class="kh-faq-body">
                                Limite yaklaştığınızda sizi bilgilendiririz. İhtiyacınıza göre ek kaynak
                                tanımlanabilir ya da üst pakete geçiş yapılabilir.
                            </div>
                        </details>
                    </div>
                </div>
            </div>
        </section>

        <!-- Kapanış -->
        <section class="kh-final">
            <div class="container">
                <h3><i class="fas fa-building"></i> Kurumsal Hosting ile Başlayın</h3>
                <p>Şirket siteniz ve e-posta hesaplarınız tek pakette, tek faturada</p>
                <div class="kh-final-actions">
                    <a href="#paketler" class="kh-final-btn">
                        <i class="fas fa-rocket"></i> Paketleri İncele
                    </a>
                    <a href="contact.php" class="kh-final-link">
                        <i class="fas fa-comments"></i> Önce Soru Sormak İsterim
                    </a>
                </div>
            </div>
        </section>
    </div>

    <script>
        // FAQ dışında JS gerekmiyor; <details> yerel olarak çalışır.
    </script>

    <?php require_once __DIR__ . '/theme/includes/footer.php'; ?>
<?php else: ?>
    <!-- ===== STANDART MAĞAZA TASARIMI ===== -->
    <style>
        /* Page Hero */
        .page-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            padding: 30px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .page-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(ellipse at 30% 50%,
                    <?= $config['color'] ?>
                    20 0%, transparent 50%),
                radial-gradient(ellipse at 70% 30%,
                    <?= $config['color'] ?>
                    15 0%, transparent 40%);
        }

        .page-hero .container {
            position: relative;
            z-index: 1;
        }

        .page-hero h1 {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 20px;
            color: #fff;
        }

        .page-hero h1 span {
            background:
                <?= $config['gradient'] ?>
            ;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .page-hero p {
            font-size: 18px;
            color: var(--gray-light);
            max-width: 600px;
            margin: 0 auto 30px;
        }

        /* Category Tabs */
        .category-tabs {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .category-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 12px 24px;
            border-radius: 30px;
            color: #94a3b8;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }

        .category-tab:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .category-tab.active {
            background:
                <?= $config['gradient'] ?>
            ;
            border-color: transparent;
            color: #fff;
        }

        /* Products Section */
        .products-section {
            padding: 40px 0 80px;
            background: var(--bg-body);
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .products-grid.cols-1 {
            grid-template-columns: minmax(280px, 400px);
            justify-content: center;
        }

        .products-grid.cols-2 {
            grid-template-columns: repeat(2, minmax(280px, 1fr));
            max-width: 800px;
        }

        .products-grid.cols-3 {
            grid-template-columns: repeat(3, minmax(280px, 1fr));
            max-width: 1100px;
        }

        .products-grid.cols-4 {
            grid-template-columns: repeat(4, minmax(260px, 1fr));
            max-width: 1400px;
        }

        .products-grid.cols-5,
        .products-grid.cols-6 {
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        }

        /* Product Card */
        .product-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: all 0.4s ease;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-10px);
            border-color:
                <?= $config['color'] ?>
                50;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .product-card.popular {
            border-color:
                <?= $config['color'] ?>
            ;
            transform: scale(1.05);
        }

        .product-card.popular:hover {
            transform: scale(1.05) translateY(-10px);
        }

        .popular-badge {
            background:
                <?= $config['gradient'] ?>
            ;
            color: white;
            text-align: center;
            padding: 10px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .product-header {
            padding: 35px 25px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }

        .product-icon {
            width: 60px;
            height: 60px;
            background:
                <?= $config['gradient'] ?>
            ;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 28px;
            color: white;
        }

        .product-header h3 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .product-group-tag {
            display: inline-block;
            background:
                <?= $config['color'] ?>
                15;
            color:
                <?= $config['color'] ?>
            ;
            font-size: 12px;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 20px;
        }

        .product-price {
            display: flex;
            align-items: baseline;
            justify-content: center;
            gap: 5px;
        }

        .product-price .currency {
            font-size: 24px;
            font-weight: 700;
            color:
                <?= $config['color'] ?>
            ;
        }

        .product-price .amount {
            font-size: 48px;
            font-weight: 800;
            color:
                <?= $config['color'] ?>
            ;
        }

        .product-price .period {
            font-size: 14px;
            color: var(--text-muted);
        }

        .product-body {
            padding: 30px 25px;
        }

        .product-features {
            margin-bottom: 30px;
            list-style: none;
        }

        .product-features li {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-secondary);
            font-size: 14px;
        }

        .product-features li:last-child {
            border-bottom: none;
        }

        .product-features li i {
            color: #10b981;
            font-size: 14px;
        }

        .product-card .btn {
            width: 100%;
        }

        .btn-accent {
            background:
                <?= $config['gradient'] ?>
            ;
            color: white;
            box-shadow: 0 4px 20px
                <?= $config['color'] ?>
                40;
        }

        .btn-accent:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px
                <?= $config['color'] ?>
                50;
        }

        /* Features Section */
        .features-section {
            padding: 80px 0;
            background: var(--bg-secondary);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
        }

        .feature-box {
            text-align: center;
            padding: 40px 25px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            transition: all 0.3s ease;
        }

        .feature-box:hover {
            border-color:
                <?= $config['color'] ?>
                50;
            background:
                <?= $config['color'] ?>
                08;
        }

        .feature-box-icon {
            width: 70px;
            height: 70px;
            background:
                <?= $config['gradient'] ?>
            ;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 28px;
            color: white;
        }

        .feature-box h4 {
            font-size: 18px;
            margin-bottom: 12px;
            color: var(--text-primary);
        }

        .feature-box p {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            grid-column: 1 / -1;
        }

        .empty-state-icon {
            width: 100px;
            height: 100px;
            background: var(--bg-secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 40px;
            color: var(--text-muted);
        }

        .empty-state h3 {
            font-size: 24px;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--text-muted);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .products-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                max-width: 100% !important;
            }

            .product-card.popular {
                transform: scale(1);
            }

            .product-card.popular:hover {
                transform: translateY(-10px);
            }

            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: 1fr !important;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .page-hero h1 {
                font-size: 36px;
            }

            .category-tabs {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>

    <!-- Page Hero -->
    <section class="page-hero">
        <div class="container">
        </div>
    </section>

    <!-- Products -->
    <section class="products-section">
        <div class="container">
            <?php if (empty($products)): ?>
                <div class="products-grid">
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <h3>Ürün Bulunamadı</h3>
                        <p>Bu kategoride henüz ürün bulunmuyor.</p>
                    </div>
                </div>
            <?php else:
                $productCount = count($products);
                $gridClass = 'cols-' . min($productCount, 6);
                ?>
                <div class="products-grid <?= $gridClass ?>">
                    <?php
                    $popularIndex = $productCount > 2 ? floor($productCount / 2) : -1;
                    foreach ($products as $index => $product):
                        $isPopular = ($index == $popularIndex);
                        $price = (float) ($product['price_monthly'] ?? 0);
                        $priceInt = floor($price);

                        $pType = $product['group_type'] ?? $group['type'] ?? 'hosting';
                        $pConfig = $typeConfig[$pType] ?? $typeConfig['hosting'];

                        $features = [];
                        if (!empty($product['description'])) {
                            foreach (explode("\n", $product['description']) as $line) {
                                $line = trim($line);
                                if ($line)
                                    $features[] = $line;
                            }
                        }
                        if (empty($features)) {
                            $features = ['Yüksek Performans', '7/24 Teknik Destek', 'Ücretsiz SSL', 'Günlük Yedekleme', 'Kolay Yönetim Paneli'];
                        }
                        ?>
                        <div class="product-card <?= $isPopular ? 'popular' : '' ?>">
                            <?php if ($isPopular): ?>
                                <div class="popular-badge">En Popüler</div>
                            <?php endif; ?>

                            <div class="product-header">
                                <div class="product-icon" style="background: <?= $pConfig['gradient'] ?>;">
                                    <i class="fas <?= $pConfig['icon'] ?>"></i>
                                </div>

                                <?php if (!$group && !empty($product['group_name'])): ?>
                                    <span class="product-group-tag"
                                        style="background: <?= $pConfig['color'] ?>15; color: <?= $pConfig['color'] ?>;">
                                        <?= htmlspecialchars($product['group_name']) ?>
                                    </span>
                                <?php endif; ?>

                                <h3><?= htmlspecialchars($product['name']) ?></h3>

                                <div class="product-price">
                                    <?php if ($price > 0): ?>
                                        <span class="currency" style="color: <?= $pConfig['color'] ?>;">₺</span>
                                        <span class="amount"
                                            style="color: <?= $pConfig['color'] ?>;"><?= number_format($priceInt, 0) ?></span>
                                        <span class="period">/ay</span>
                                    <?php else: ?>
                                        <span class="amount" style="font-size: 24px; color: <?= $pConfig['color'] ?>;">Fiyat
                                            Sorunuz</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="product-body">
                                <ul class="product-features">
                                    <?php foreach (array_slice($features, 0, 5) as $feature): ?>
                                        <li><i class="fas fa-check"></i> <?= htmlspecialchars($feature) ?></li>
                                    <?php endforeach; ?>
                                </ul>

                                <a href="/client/order-configure.php?id=<?= $product['id'] ?>"
                                    class="btn <?= $isPopular ? 'btn-accent' : 'btn-outline' ?>"
                                    style="<?= $isPopular ? 'background: ' . $pConfig['gradient'] . '; box-shadow: 0 4px 20px ' . $pConfig['color'] . '40;' : '' ?>">
                                    Sipariş Ver
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Features -->
    <section class="features-section">
        <div class="container">
            <div class="section-header">
                <span class="section-badge" style="background: <?= $config['color'] ?>15; color: <?= $config['color'] ?>;">
                    <i class="fas fa-star"></i>
                    Avantajlar
                </span>
                <h2 class="section-title">Neden <span
                        style="background: <?= $config['gradient'] ?>; -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Bizi
                        Tercih Etmelisiniz?</span></h2>
            </div>

            <div class="features-grid">
                <div class="feature-box">
                    <div class="feature-box-icon" style="background: <?= $config['gradient'] ?>;">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h4>Yüksek Performans</h4>
                    <p>NVMe SSD diskler ve optimize altyapı ile maksimum hız.</p>
                </div>

                <div class="feature-box">
                    <div class="feature-box-icon" style="background: <?= $config['gradient'] ?>;">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h4>Güvenli Altyapı</h4>
                    <p>DDoS koruması ve gelişmiş güvenlik duvarı.</p>
                </div>

                <div class="feature-box">
                    <div class="feature-box-icon" style="background: <?= $config['gradient'] ?>;">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h4>7/24 Destek</h4>
                    <p>Uzman teknik ekibimiz her zaman yanınızda.</p>
                </div>

                <div class="feature-box">
                    <div class="feature-box-icon" style="background: <?= $config['gradient'] ?>;">
                        <i class="fas fa-sync"></i>
                    </div>
                    <h4>%99.9 Uptime</h4>
                    <p>Kesintisiz hizmet garantisi sunuyoruz.</p>
                </div>
            </div>
        </div>
    </section>

    <?php require_once __DIR__ . '/theme/includes/footer.php'; ?>
<?php endif; // End of standard store design ?>