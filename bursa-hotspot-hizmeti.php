<?php
/**
 * WHMVM - Bursa Hotspot & İnternet Hizmeti sayfası
 *
 * Paketler veritabanından (product_groups.slug = 'hotspot-hizmeti') okunur.
 * Hizmet iki parçadan oluşur: fiber internet hattı + 5651 uyumlu hotspot sistemi.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Bursa Hotspot ve İnternet Hizmeti - Nilüfer & Osmangazi';
$pageDescription = 'Bursa Nilüfer ve Osmangazi bölgesindeki işletmelere fiber internet hattı, '
    . 'hotspot donanımı ve 5651 sayılı kanuna uygun log kaydı tek pakette.';

/* Paketleri çek; veritabanı hazır değilse sayfa boş listeyle açılır */
$hsProducts = [];
try {
    $hsGroup = Database::fetch(
        "SELECT id FROM product_groups WHERE slug = ? LIMIT 1",
        ['hotspot-hizmeti']
    );
    if (!empty($hsGroup['id'])) {
        $hsProducts = Database::fetchAll(
            "SELECT * FROM products
              WHERE group_id = ? AND is_active = 1 AND is_hidden = 0
              ORDER BY order_priority, price_monthly",
            [(int) $hsGroup['id']]
        );
    }
} catch (Throwable $e) {
    $hsProducts = [];
}

/**
 * Açıklamanın ilk satırları vitrin kutucuğuna, kalanı özellik listesine gider.
 * Değer baştadır, etiket sonda: "100 Mbps İnternet Hızı" -> değer "100 Mbps".
 */
$hsSpecTypes = [
    'hiz' => ['label' => 'İnternet', 'icon' => 'fa-gauge-high', 'match' => '/i̇nternet hız|internet hız|mbps|gbps/iu'],
    'ap' => ['label' => 'Erişim Noktası', 'icon' => 'fa-wifi', 'match' => '/erişim nokta/iu'],
    'kullanici' => ['label' => 'Eşzamanlı', 'icon' => 'fa-users', 'match' => '/eşzamanlı/iu'],
    'log' => ['label' => 'Log Saklama', 'icon' => 'fa-clock-rotate-left', 'match' => '/log sakla/iu'],
];

$hsSpecValue = static function (string $line): string {
    if (preg_match('/\b(limitsiz|sınırsız|unlimited)\b/iu', $line)) {
        return 'Limitsiz';
    }
    // Türkçe büyük İ (U+0130) /i bayrağıyla küçük i'ye katlanmadığı için sınıf kullanılır
    $deger = preg_replace('/\s*([iİ]nternet|[eE]rişim|[eE]şzamanlı|[lL]og|[aA]det).*$/u', '', $line);
    $deger = trim((string) $deger);
    return $deger !== '' ? $deger : trim($line);
};

require_once __DIR__ . '/theme/includes/header.php';
?>

<style>

        /* ==========================================
           Hotspot & İnternet - sayfaya özel değişkenler
           Vurgu rengi ve yüzeyler global tema
           token'larından gelir.
           ========================================== */
        .hs {
            --hs-accent: var(--primary);
            --hs-accent-light: var(--primary);
            --hs-accent-dark: var(--primary-dark);
            --hs-accent-soft: color-mix(in srgb, var(--primary) 12%, transparent);
            --hs-accent-line: color-mix(in srgb, var(--primary) 28%, transparent);
            --hs-gradient: var(--gradient-primary);
        }

        /* ===== Hero ===== */
        .hs-hero {
            position: relative;
            overflow: hidden;
            background: var(--gradient-hero);
            padding: var(--space-6) 0 0;
        }

        .hs-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 55% 50% at 12% 30%, color-mix(in srgb, var(--primary) 22%, transparent) 0%, transparent 62%),
                radial-gradient(ellipse 45% 45% at 88% 10%, color-mix(in srgb, var(--secondary) 16%, transparent) 0%, transparent 58%);
            pointer-events: none;
        }

        .hs-hero>.container {
            position: relative;
            z-index: 1;
        }

        /* Tek kolon, ortalanmis: hero'da ayrica gorsel yok */
        .hs-hero-grid {
            max-width: 780px;
            margin: 0 auto;
            padding-bottom: var(--space-6);
            text-align: center;
        }

        .hs-hero-text .hs-lead {
            margin-left: auto;
            margin-right: auto;
        }

        .hs-hero-text .hs-stack {
            justify-content: center;
        }

        .hs-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 50px;
            background: var(--hs-accent-soft);
            border: 1px solid var(--hs-accent-line);
            color: var(--hs-accent-light);
            font-size: var(--text-sm);
            font-weight: 600;
            margin-bottom: var(--space-4);
        }

        .hs-title {
            /* Ürün sayfası; anasayfa kadar iri olmasına gerek yok */
            font-size: clamp(32px, 3.2vw + 16px, 50px);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -0.035em;
            margin-bottom: var(--space-3);
            color: var(--text-primary);
            text-wrap: balance;
        }

        .hs-title span {
            background: var(--hs-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hs-lead {
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
        .hs-stack {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .hs-chip {
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

        .hs-chip:hover {
            background: var(--surface-2);
            border-color: var(--hs-accent-line);
        }

        .hs-chip i {
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





        /* ===== Terminal ===== */
        /* Surum listesi: cubuk sutunu yok */
        .hs-bars.is-stack .hs-bar-row {
            grid-template-columns: minmax(0, 1fr) auto;
        }

        /* Windows teknoloji cip renkleri */
        .hs-chip i.is-fiber {
            background: linear-gradient(135deg, #f59e0b, #b45309);
        }

        .hs-chip i.is-5651 {
            background: linear-gradient(135deg, #22c55e, #15803d);
        }

        .hs-chip i.is-sms {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
        }

        .hs-chip i.is-kurulum {
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
        }

        /* ===== Bölüm başlıkları ===== */
        .hs-section {
            padding: var(--section-padding) 0;
        }

        /* Paketler bölümü hemen sekme şeridinin altında;
           sekmeler zaten ayırıcı, üstte tam boşluğa gerek yok */
        .hs-hero+.hs-section {
            padding-top: var(--space-7);
        }

        .hs-section.is-alt {
            background: var(--bg-primary);
        }

        .hs-head {
            text-align: center;
            max-width: 680px;
            margin: 0 auto var(--space-7);
        }

        .hs-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 50px;
            background: var(--hs-accent-soft);
            border: 1px solid var(--hs-accent-line);
            color: var(--hs-accent-light);
            font-size: var(--text-sm);
            font-weight: 600;
            margin-bottom: var(--space-4);
        }

        .hs-head h2 {
            font-size: var(--text-3xl);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.15;
            color: var(--text-primary);
            text-wrap: balance;
        }

        .hs-head h2 span {
            background: var(--hs-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hs-head p {
            margin-top: var(--space-3);
            font-size: var(--text-md);
            color: var(--text-muted);
            text-wrap: pretty;
        }

        /* ===== Paket kartları ===== */
        .hs-plans {
            display: grid;
            /* Sutun sayisi paket adedinden gelir; auto-fit reflow yapmaz */
            grid-template-columns: repeat(var(--plan-cols, 4), minmax(0, 1fr));
            gap: var(--space-5);
            /* Kartlar eşit yükseklikte olsun, sipariş butonları hizalansın */
            align-items: stretch;
        }

        .hs-plans[data-count="1"] {
            --plan-cols: 1;
            max-width: 420px;
            margin-inline: auto;
        }

        .hs-plans[data-count="2"] {
            --plan-cols: 2;
        }

        .hs-plans[data-count="3"] {
            --plan-cols: 3;
        }

        @media (max-width: 1180px) {
            .hs-plans[data-count] {
                --plan-cols: 2;
            }
        }

        @media (max-width: 680px) {
            .hs-plans[data-count] {
                --plan-cols: 1;
            }
        }

        /* Az paket varsa ortala, kartlar aşırı genişlemesin */
        .hs-plans[data-count="1"] {
            grid-template-columns: minmax(300px, 420px);
            justify-content: center;
        }

        .hs-plans[data-count="2"] {
            grid-template-columns: repeat(2, minmax(300px, 430px));
            justify-content: center;
        }

        .hs-plans[data-count="3"] {
            grid-template-columns: repeat(3, minmax(280px, 400px));
            justify-content: center;
        }

        .hs-plan {
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

        .hs-plan:hover {
            transform: translateY(-6px);
            border-color: var(--hs-accent-line);
            box-shadow: var(--shadow-lg);
        }

        .hs-plan.is-popular {
            border-color: var(--hs-accent);
            box-shadow: 0 0 0 1px var(--hs-accent), 0 24px 48px -18px color-mix(in srgb, var(--primary) 45%, transparent);
        }

        .hs-ribbon {
            position: absolute;
            top: 0;
            right: var(--space-5);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 0 0 10px 10px;
            background: var(--hs-gradient);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .hs-plan-top {
            padding: var(--space-6) var(--space-5) var(--space-5);
            border-bottom: 1px solid var(--border-color);
        }

        .hs-plan-name {
            font-size: var(--text-xl);
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .hs-plan-group {
            font-size: var(--text-sm);
            color: var(--text-muted);
            margin-bottom: var(--space-5);
        }

        .hs-price {
            display: flex;
            align-items: baseline;
            gap: 4px;
            /* Rakamlar kartlar arasında hizalı dursun */
            font-variant-numeric: tabular-nums;
        }

        .hs-price .cur {
            font-size: var(--text-lg);
            font-weight: 700;
            color: var(--hs-accent);
        }

        .hs-price .val {
            font-size: clamp(34px, 3vw + 22px, 46px);
            font-weight: 800;
            letter-spacing: -0.04em;
            line-height: 1;
            color: var(--text-primary);
        }

        .hs-price .per {
            font-size: var(--text-base);
            color: var(--text-muted);
            font-weight: 500;
        }

        .hs-price-ask {
            font-size: var(--text-xl);
            font-weight: 700;
            color: var(--text-primary);
        }

        .hs-price-note {
            margin-top: var(--space-2);
            font-size: var(--text-sm);
            color: var(--text-muted);
        }

        .hs-save {
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
        .hs-save.is-placeholder {
            visibility: hidden;
        }

        /* Teknik özet kutucukları */
        .hs-specs {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1px;
            background: var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .hs-spec {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-4);
            background: var(--bg-primary);
        }

        .hs-spec i {
            width: 30px;
            height: 30px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: var(--hs-accent-soft);
            color: var(--hs-accent-light);
            font-size: 13px;
        }

        .hs-spec b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.25;
        }

        .hs-spec span {
            font-size: 11px;
            color: var(--text-muted);
        }

        .hs-plan-body {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: var(--space-5);
        }

        .hs-features {
            list-style: none;
            margin: 0 0 var(--space-5);
            padding: 0;
            display: grid;
            gap: 2px;
        }

        .hs-features li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 7px 0;
            font-size: var(--text-sm);
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .hs-features li i {
            margin-top: 3px;
            font-size: 11px;
            color: #22c55e;
            flex-shrink: 0;
        }

        /* Uzun listelerde kart şişmesin: fazlası gizli, düğmeyle açılır */
        .hs-features.is-clipped li:nth-child(n+7) {
            display: none;
        }

        .hs-more {
            align-self: flex-start;
            margin: calc(var(--space-5) * -1 + 4px) 0 var(--space-5);
            padding: 6px 0;
            background: none;
            border: none;
            color: var(--hs-accent-light);
            font-family: inherit;
            font-size: var(--text-sm);
            font-weight: 600;
            cursor: pointer;
        }

        .hs-more:hover {
            text-decoration: underline;
        }

        .hs-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            /* Buton kartın en altına yapışsın, kartlar eşit hizalansın */
            margin-top: auto;
            padding: 15px 24px;
            border-radius: var(--radius-md);
            border: 1px solid transparent;
            background: var(--hs-gradient);
            color: #fff;
            font-size: var(--text-base);
            font-weight: 700;
            box-shadow: 0 8px 22px color-mix(in srgb, var(--primary) 30%, transparent);
            transition: transform var(--transition-fast) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .hs-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 34px color-mix(in srgb, var(--primary) 48%, transparent);
        }

        .hs-cta:active {
            transform: translateY(0) scale(0.99);
        }

        /* Boş durum */
        .hs-empty {
            text-align: center;
            padding: var(--space-9) var(--space-5);
            border: 1px dashed var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--surface-1);
        }

        .hs-empty i {
            font-size: 42px;
            color: var(--hs-accent);
            margin-bottom: var(--space-4);
        }

        .hs-empty h3 {
            font-size: var(--text-xl);
            color: var(--text-primary);
            margin-bottom: var(--space-2);
        }

        .hs-empty p {
            color: var(--text-muted);
        }

        /* ===== "Her pakette standart" paneli =====
           8 ayrı kart yerine tek panel: hepsinin dahil olduğu
           tek bir vaat gibi okunur, dikey yer de yarıya iner. */
        .hs-included {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--surface-1);
            overflow: hidden;
        }

        .hs-included-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1px;
            background: var(--border-color);
        }

        .hs-inc {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            background: var(--bg-primary);
            transition: background-color var(--transition-normal) var(--ease-out);
        }

        .hs-inc:hover {
            background: color-mix(in srgb, var(--hs-accent) 6%, var(--bg-primary));
        }

        .hs-inc i {
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: var(--hs-accent-soft);
            color: var(--hs-accent-light);
            font-size: 14px;
        }

        .hs-inc b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .hs-inc span {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        /* ===== Teknoloji: bir büyük vitrin + üç kart ===== */
        .hs-spotlight {
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

        .hs-spotlight-tag {
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

        .hs-spotlight h3 {
            font-size: var(--text-2xl);
            font-weight: 800;
            letter-spacing: -0.025em;
            color: var(--text-primary);
            margin-bottom: var(--space-3);
        }

        .hs-spotlight p {
            font-size: var(--text-md);
            color: var(--text-muted);
            line-height: 1.7;
            margin-bottom: var(--space-5);
            max-width: 46ch;
        }

        .hs-spotlight-points {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .hs-spotlight-points li {
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

        .hs-spotlight-points i {
            color: #4ade80;
            font-size: 10px;
        }

        /* Hız karşılaştırma çubukları */
        .hs-bars {
            display: grid;
            gap: var(--space-4);
        }

        .hs-bar-row {
            display: grid;
            grid-template-columns: 78px minmax(0, 1fr) 42px;
            align-items: center;
            gap: var(--space-3);
        }

        .hs-bar-label {
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--text-muted);
        }

        .hs-bar {
            height: 14px;
            border-radius: 50px;
            background: var(--surface-3);
            overflow: hidden;
        }

        .hs-bar span {
            display: block;
            height: 100%;
            border-radius: 50px;
            background: var(--text-gray);
        }

        .hs-bar-val {
            font-size: var(--text-base);
            font-weight: 800;
            color: var(--text-muted);
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .hs-bar-row.is-lead .hs-bar-label,
        .hs-bar-row.is-lead .hs-bar-val {
            color: var(--text-primary);
        }

        .hs-bar-row.is-lead .hs-bar span {
            background: linear-gradient(90deg, #22c55e, #15803d);
        }

        .hs-bar-note {
            font-size: var(--text-xs);
            color: var(--text-gray);
            line-height: 1.5;
            margin: 0;
        }

        .hs-tech {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: var(--space-5);
        }

        .hs-tech-card {
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

        .hs-tech-card::before {
            content: '';
            position: absolute;
            inset: 0 0 auto 0;
            height: 3px;
            background: var(--tint, var(--hs-gradient));
            transform: scaleX(0);
            transition: transform var(--transition-normal) var(--ease-out);
        }

        .hs-tech-card:hover {
            transform: translateY(-4px);
            background: var(--surface-2);
        }

        .hs-tech-card:hover::before {
            transform: scaleX(1);
        }

        .hs-tech-icon {
            width: 46px;
            height: 46px;
            flex-shrink: 0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            color: #fff;
            background: var(--tint, var(--hs-gradient));
        }

        .hs-tech-card h4 {
            font-size: var(--text-lg);
            font-weight: 700;
            letter-spacing: -0.015em;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .hs-tech-card p {
            font-size: var(--text-sm);
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* ===== Donanım çizimleri =====
           Dell R730 ve EMC VNX7600 ön panelleri SVG olarak çizilir.
           Bütün renkler tema token'larına bağlıdır; açık/koyu temada
           ayrı bir görsel gerekmez. */
        .hs-spotlight.is-flip {
            grid-template-columns: minmax(0, 0.85fr) minmax(0, 1fr);
            background:
                radial-gradient(ellipse 60% 90% at 0% 50%, color-mix(in srgb, #22c55e 10%, transparent) 0%, transparent 70%),
                var(--surface-1);
        }

        .hs-spotlight.is-flip .hs-spotlight-text {
            order: 2;
        }

        .hs-spotlight.is-flip .hs-bars {
            order: 1;
        }

        @media (max-width: 900px) {
            .hs-spotlight.is-flip {
                grid-template-columns: 1fr;
            }

            .hs-spotlight.is-flip .hs-spotlight-text {
                order: 1;
            }

            .hs-spotlight.is-flip .hs-bars {
                order: 2;
            }
        }

        /* Ters çevrilmiş vitrin dar ekranda metin üstte kalsın */
        @media (max-width: 1100px) {
            .hs-spotlight.is-flip {
                grid-template-columns: 1fr;
            }

            .hs-spotlight.is-flip .hs-spotlight-text {
                order: 1;
            }

            .hs-spotlight.is-flip .hs-bars {
                order: 2;
            }
        }

        /* ===== Sık sorulan sorular ===== */
        /* Solda görsel + başlık, sağda soru listesi */
        .hs-faq-layout {
            display: grid;
            grid-template-columns: minmax(0, 330px) minmax(0, 1fr);
            gap: var(--space-8);
            /* Sol sutun listeyle ayni yuksekligi alsin */
            align-items: stretch;
        }

        .hs-faq-aside {
            display: flex;
            flex-direction: column;
        }

        .hs-head.is-left {
            text-align: left;
            max-width: 100%;
            margin: 0 0 var(--space-5);
        }

        .hs-faq-visual {
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

        .hs-faq-visual svg {
            width: 100%;
            height: 100%;
            max-height: 240px;
            display: block;
        }

        /* "Sorunuz yoksa bize yazın" kutusu */
        .hs-faq-help {
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

        .hs-faq-help:hover {
            border-color: var(--hs-accent-line);
            background: var(--surface-2);
        }

        .hs-faq-help i {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--hs-gradient);
            color: #fff;
            font-size: 15px;
        }

        .hs-faq-help b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .hs-faq-help span {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        .hs-faq {
            display: grid;
            gap: var(--space-3);
        }

        .hs-faq-item {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--surface-1);
            overflow: hidden;
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .hs-faq-item[open] {
            border-color: var(--hs-accent-line);
            background: var(--surface-2);
        }

        .hs-faq-item summary {
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
        .hs-faq-icon {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--hs-accent-soft);
            border: 1px solid var(--hs-accent-line);
            color: var(--hs-accent-light);
            transition: background-color var(--transition-normal) var(--ease-out),
                color var(--transition-normal) var(--ease-out);
        }

        .hs-faq-icon svg {
            width: 19px;
            height: 19px;
            display: block;
        }

        .hs-faq-item[open] .hs-faq-icon {
            background: var(--hs-gradient);
            border-color: transparent;
            color: #fff;
        }

        /* Soru metni ile artı işareti arasını doldurur */
        .hs-faq-q {
            flex: 1;
            min-width: 0;
        }

        /* Tarayıcının varsayılan üçgen işaretini kaldır */
        .hs-faq-item summary::-webkit-details-marker {
            display: none;
        }

        .hs-faq-item summary::marker {
            content: '';
        }

        .hs-faq-item summary:hover {
            color: var(--hs-accent-light);
        }

        .hs-faq-item summary:focus-visible {
            outline: none;
            box-shadow: var(--focus-ring);
        }

        .hs-faq-sign {
            width: 28px;
            height: 28px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--hs-accent-soft);
            color: var(--hs-accent-light);
            font-size: 12px;
            transition: transform var(--transition-normal) var(--ease-out);
        }

        .hs-faq-item[open] .hs-faq-sign {
            transform: rotate(45deg);
        }

        .hs-faq-body {
            /* Cevap, sorunun metniyle aynı hizadan başlasın (ikon + boşluk kadar içeride) */
            padding: 0 var(--space-5) var(--space-5) calc(var(--space-5) + 38px + var(--space-3));
            font-size: var(--text-base);
            color: var(--text-muted);
            line-height: 1.75;
            max-width: 72ch;
        }

        /* ===== Kapanış çağrısı ===== */
        .hs-final {
            position: relative;
            overflow: hidden;
            padding: var(--space-8) 0;
            background: var(--hs-gradient);
            color: #fff;
            text-align: center;
        }

        .hs-final::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 100% at 50% 0%, rgba(255, 255, 255, 0.2) 0%, transparent 62%),
                radial-gradient(ellipse 50% 80% at 88% 100%, rgba(0, 0, 0, 0.18) 0%, transparent 60%);
            pointer-events: none;
        }

        .hs-final>.container {
            position: relative;
            z-index: 1;
        }

        .hs-final h3 {
            font-size: var(--text-2xl);
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: var(--space-3);
            text-wrap: balance;
        }

        .hs-final p {
            font-size: var(--text-md);
            opacity: 0.92;
            margin-bottom: var(--space-6);
        }

        .hs-final-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: var(--space-3);
        }

        .hs-final-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 32px;
            border-radius: var(--radius-md);
            background: #fff;
            color: var(--hs-accent-dark);
            font-size: var(--text-md);
            font-weight: 700;
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.2);
            transition: transform var(--transition-fast) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .hs-final-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.28);
        }

        .hs-final-link {
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

        .hs-final-link:hover {
            background: rgba(255, 255, 255, 0.22);
            border-color: #fff;
        }

        /* ===== Duyarlılık ===== */
        @media (max-width: 1100px) {
            .hs-included-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .hs-spotlight {
                grid-template-columns: 1fr;
                gap: var(--space-6);
                padding: var(--space-6);
            }

            .hs-spotlight p {
                max-width: 100%;
            }
        }

        @media (max-width: 992px) {
            .hs-lead {
                max-width: 100%;
            }

            .hs-tech {
                grid-template-columns: 1fr;
            }

            /* Görsel ve başlık üste, sorular altına */
            .hs-faq-layout {
                grid-template-columns: 1fr;
                gap: var(--space-6);
                justify-items: center;
            }

            .hs-faq-aside {
                max-width: 460px;
                width: 100%;
            }

            .hs-head.is-left {
                text-align: center;
            }

            .hs-faq-visual {
                flex: 0 0 auto;
                margin: 0 auto;
            }

            .hs-faq-visual svg {
                height: auto;
            }

            .hs-faq {
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            .hs-plans,
            .hs-plans[data-count="2"],
            .hs-plans[data-count="3"] {
                grid-template-columns: 1fr;
            }

            .hs-included-grid {
                grid-template-columns: 1fr;
            }

            .hs-bar-row {
                grid-template-columns: 66px minmax(0, 1fr) 34px;
                gap: var(--space-2);
            }

            .hs-final-actions {
                flex-direction: column;
            }

            .hs-final-actions>* {
                width: 100%;
                justify-content: center;
            }

            /* Dar ekranda cevabı ikon hizasında girintilemek yer israfı olur */
            .hs-faq-body {
                padding-left: var(--space-5);
            }

            .hs-faq-item summary {
                padding-left: var(--space-4);
                padding-right: var(--space-4);
            }
        }
        /* ===== Keşif talebi penceresi ===== */
        .hs-cta {
            width: 100%;
            border: 0;
            font-family: inherit;
            cursor: pointer;
        }

        .hs-modal {
            position: fixed;
            inset: 0;
            z-index: 1200;
            display: none;
            align-items: flex-start;
            justify-content: center;
            padding: var(--space-6) var(--space-4);
            overflow-y: auto;
            background: color-mix(in srgb, #0b1020 72%, transparent);
            backdrop-filter: blur(4px);
        }

        .hs-modal.is-open {
            display: flex;
        }

        .hs-modal-card {
            width: 100%;
            max-width: 640px;
            margin: auto;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.28);
            overflow: hidden;
        }

        .hs-modal-head {
            display: flex;
            align-items: flex-start;
            gap: var(--space-4);
            padding: var(--space-6) var(--space-6) var(--space-4);
            border-bottom: 1px solid var(--border-light);
        }

        .hs-modal-head h3 {
            font-size: var(--text-xl);
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--text-primary);
            margin: 0 0 4px;
        }

        .hs-modal-head p {
            font-size: var(--text-sm);
            color: var(--text-muted);
            margin: 0;
        }

        .hs-modal-kapat {
            margin-left: auto;
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--surface-1);
            color: var(--text-muted);
            cursor: pointer;
            transition: background-color var(--transition-fast) var(--ease-out);
        }

        .hs-modal-kapat:hover {
            background: var(--surface-2);
            color: var(--text-primary);
        }

        .hs-modal-govde {
            padding: var(--space-6);
        }

        .hs-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: var(--space-4);
        }

        .hs-alan {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 0;
        }

        .hs-alan.is-full {
            grid-column: 1 / -1;
        }

        .hs-alan label {
            font-size: var(--text-xs);
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: var(--text-muted);
        }

        .hs-alan label .zorunlu {
            color: #ef4444;
        }

        .hs-alan input,
        .hs-alan select,
        .hs-alan textarea {
            width: 100%;
            padding: 10px 12px;
            font-family: inherit;
            font-size: var(--text-sm);
            color: var(--text-primary);
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            transition: border-color var(--transition-fast) var(--ease-out),
                box-shadow var(--transition-fast) var(--ease-out);
        }

        .hs-alan textarea {
            min-height: 84px;
            resize: vertical;
        }

        .hs-alan input:focus,
        .hs-alan select:focus,
        .hs-alan textarea:focus {
            outline: none;
            border-color: var(--hs-accent);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--hs-accent) 18%, transparent);
        }

        .hs-alan input:disabled,
        .hs-alan select:disabled {
            background: var(--surface-2);
            color: var(--text-muted);
            cursor: not-allowed;
        }

        .hs-alan.has-error input,
        .hs-alan.has-error select {
            border-color: #ef4444;
        }

        .hs-alan-hata {
            font-size: var(--text-xs);
            color: #ef4444;
            min-height: 0;
        }

        .hs-alan-ipucu {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        /* Bot tuzağı: ekran okuyucudan da gizli */
        .hs-tuzak {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(0 0 0 0);
            white-space: nowrap;
        }

        .hs-modal-alt {
            display: flex;
            align-items: center;
            gap: var(--space-4);
            flex-wrap: wrap;
            margin-top: var(--space-5);
            padding-top: var(--space-5);
            border-top: 1px solid var(--border-light);
        }

        .hs-modal-gonder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 44px;
            padding: 0 22px;
            border: 0;
            border-radius: var(--radius-md);
            background: var(--hs-gradient);
            color: #fff;
            font-family: inherit;
            font-size: var(--text-sm);
            font-weight: 700;
            cursor: pointer;
            transition: filter var(--transition-fast) var(--ease-out);
        }

        .hs-modal-gonder:hover:not(:disabled) {
            filter: brightness(1.08);
        }

        .hs-modal-gonder:disabled {
            opacity: 0.6;
            cursor: progress;
        }

        .hs-modal-vazgec {
            background: none;
            border: 0;
            padding: 0;
            font-family: inherit;
            font-size: var(--text-sm);
            color: var(--text-muted);
            cursor: pointer;
        }

        .hs-modal-vazgec:hover {
            color: var(--text-primary);
        }

        .hs-modal-durum {
            flex: 1 1 100%;
            font-size: var(--text-sm);
            color: var(--text-muted);
        }

        .hs-modal-durum.is-hata {
            color: #ef4444;
        }

        .hs-modal-basarili {
            display: none;
            padding: var(--space-7) var(--space-6);
            text-align: center;
        }

        .hs-modal-basarili>i {
            font-size: 42px;
            color: #22c55e;
            margin-bottom: var(--space-4);
        }

        .hs-modal-basarili h4 {
            font-size: var(--text-lg);
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 8px;
        }

        .hs-modal-basarili p {
            font-size: var(--text-sm);
            color: var(--text-muted);
            margin: 0 auto;
            max-width: 44ch;
        }

        .hs-modal.is-done .hs-modal-govde {
            display: none;
        }

        .hs-modal.is-done .hs-modal-basarili {
            display: block;
        }

        @media (max-width: 640px) {
            .hs-form-grid {
                grid-template-columns: 1fr;
            }

            .hs-modal-head,
            .hs-modal-govde {
                padding-left: var(--space-5);
                padding-right: var(--space-5);
            }
        }

</style>

<div class="hs">
    <!-- Hero -->
    <section class="hs-hero">
        <div class="container">
            <div class="hs-hero-grid">
                <div class="hs-hero-text">
                    <span class="hs-eyebrow"><i class="fas fa-wifi"></i> Bursa Nilüfer &amp; Osmangazi</span>
                    <h1 class="hs-title"><span>Hotspot ve İnternet</span> Hizmeti</h1>
                    <p class="hs-lead">
                        İnternet hattını da, misafir WiFi sistemini de biz veriyoruz. Fiber bağlantı,
                        hotspot donanımı ve 5651 uyumlu log kaydı tek fatura altında.
                    </p>

                    <div class="hs-stack">
                        <span class="hs-chip"><i class="fas fa-bolt is-fiber"></i> Fiber İnternet</span>
                        <span class="hs-chip"><i class="fas fa-shield-halved is-5651"></i> 5651 Uyumlu</span>
                        <span class="hs-chip"><i class="fas fa-comment-sms is-sms"></i> SMS Girişi</span>
                        <span class="hs-chip"><i class="fas fa-screwdriver-wrench is-kurulum"></i> Yerinde Kurulum</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Paketler -->
    <section class="hs-section" id="paketler">
        <div class="container">
            <div class="hs-head">
                <span class="hs-badge"><i class="fas fa-wifi"></i> Hotspot ve İnternet Paketleri</span>
                <h2>İşletmenize Uygun <span>Bağlantı</span></h2>
                <p>Paketleri ayıran şey internet hızı ve kapsanan alan; log kaydı ve destek hepsinde aynı.</p>
            </div>

            <?php if (empty($hsProducts)): ?>
                <div class="hs-empty">
                    <i class="fas fa-wifi"></i>
                    <h3>Paket Bulunamadı</h3>
                    <p>Şu anda listelenecek paket bulunmuyor.</p>
                </div>
            <?php else:
                $hsCount = count($hsProducts);

                $populerIndex = -1;
                foreach ($hsProducts as $i => $p) {
                    if (!empty($p['is_featured'])) {
                        $populerIndex = $i;
                        break;
                    }
                }
                if ($populerIndex === -1 && $hsCount > 2) {
                    $populerIndex = (int) floor(($hsCount - 1) / 2);
                }
                ?>
                <div class="hs-plans" data-count="<?= $hsCount ?>">
                    <?php foreach ($hsProducts as $index => $product):
                        $isPopular = ($index === $populerIndex);
                        $aylik = (float) ($product['price_monthly'] ?? 0);
                        $kurulum = (float) ($product['setup_fee'] ?? 0);

                        $lines = [];
                        foreach (preg_split('/\r\n|\r|\n/', (string) ($product['description'] ?? '')) as $line) {
                            $line = trim($line);
                            if ($line !== '') {
                                $lines[] = $line;
                            }
                        }

                        $specs = [];
                        $usedLines = [];
                        foreach ($hsSpecTypes as $spec) {
                            if (count($specs) >= 4) {
                                break;
                            }
                            foreach ($lines as $li => $line) {
                                if (isset($usedLines[$li]) || !preg_match($spec['match'], $line)) {
                                    continue;
                                }
                                $value = $hsSpecValue($line);
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
                        if (empty($features)) {
                            $features = [
                                'Fiber internet hattı dahil',
                                '5651 uyumlu kayıt sistemi',
                                'Ücretsiz keşif ve kurulum',
                                '7/24 teknik destek',
                            ];
                        }

                        $clip = count($features) > 6;
                        $listId = 'hs-feat-' . (int) $product['id'];
                        ?>
                        <article class="hs-plan <?= $isPopular ? 'is-popular' : '' ?>">
                            <?php if ($isPopular): ?>
                                <div class="hs-ribbon"><i class="fas fa-fire"></i> En Çok Tercih Edilen</div>
                            <?php endif; ?>

                            <div class="hs-plan-top">
                                <div class="hs-plan-name"><?= htmlspecialchars($product['name']) ?></div>
                                <div class="hs-plan-group">Hotspot &amp; İnternet</div>

                                <?php if ($aylik > 0): ?>
                                    <div class="hs-price">
                                        <span class="cur">₺</span>
                                        <span class="val"><?= number_format(floor($aylik), 0, ',', '.') ?></span>
                                        <span class="per">/ay</span>
                                    </div>
                                    <?php if ($kurulum > 0): ?>
                                        <div class="hs-price-note">
                                            + ₺<?= number_format($kurulum, 0, ',', '.') ?> tek seferlik kurulum
                                        </div>
                                    <?php else: ?>
                                        <div class="hs-price-note">Kurulum ve keşif ücretsiz</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="hs-price-ask">Fiyat için görüşelim</div>
                                    <div class="hs-price-note">Keşif sonrası teklif hazırlıyoruz</div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($specs)): ?>
                                <div class="hs-specs">
                                    <?php foreach ($specs as $spec): ?>
                                        <div class="hs-spec">
                                            <i class="fas <?= $spec['icon'] ?>"></i>
                                            <div>
                                                <b><?= htmlspecialchars($spec['value']) ?></b>
                                                <span><?= htmlspecialchars($spec['label']) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="hs-plan-body">
                                <ul class="hs-features <?= $clip ? 'is-clipped' : '' ?>" id="<?= $listId ?>">
                                    <?php foreach ($features as $feature): ?>
                                        <li><i class="fas fa-check"></i> <?= htmlspecialchars($feature) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if ($clip): ?>
                                    <button type="button" class="hs-more" data-target="<?= $listId ?>"
                                        aria-expanded="false" aria-controls="<?= $listId ?>">
                                        + <?= count($features) - 6 ?> özellik daha
                                    </button>
                                <?php endif; ?>

                                <button type="button" class="hs-cta" data-kesif-ac
                                    data-paket="<?= (int) $product['id'] ?>"
                                    data-paket-adi="<?= htmlspecialchars($product['name']) ?>">
                                    <i class="fas fa-phone"></i> Keşif Talep Et
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Her pakette standart -->
    <section class="hs-section is-alt">
        <div class="container">
            <div class="hs-head">
                <span class="hs-badge"><i class="fas fa-check-double"></i> Pakete Dahil</span>
                <h2>Hepsi <span>Her Pakette Standart</span></h2>
                <p>Aşağıdakiler için ek ücret ödemezsiniz; en küçük pakette de aynen geçerli.</p>
            </div>

            <div class="hs-included">
                <div class="hs-included-grid">
                    <div class="hs-inc">
                        <i class="fas fa-bolt"></i>
                        <div>
                            <b>Fiber İnternet Hattı</b>
                            <span>Hattı da biz sağlıyoruz</span>
                        </div>
                    </div>
                    <div class="hs-inc">
                        <i class="fas fa-router"></i>
                        <div>
                            <b>Hotspot Donanımı</b>
                            <span>Cihazlar bizden, kira dahil</span>
                        </div>
                    </div>
                    <div class="hs-inc">
                        <i class="fas fa-shield-halved"></i>
                        <div>
                            <b>5651 Uyumlu Kayıt</b>
                            <span>Loglar yasal süre boyunca</span>
                        </div>
                    </div>
                    <div class="hs-inc">
                        <i class="fas fa-comment-sms"></i>
                        <div>
                            <b>SMS Doğrulama</b>
                            <span>Misafir telefonuyla giriş</span>
                        </div>
                    </div>
                    <div class="hs-inc">
                        <i class="fas fa-palette"></i>
                        <div>
                            <b>Özel Karşılama Sayfası</b>
                            <span>Markanıza göre tasarım</span>
                        </div>
                    </div>
                    <div class="hs-inc">
                        <i class="fas fa-chart-line"></i>
                        <div>
                            <b>Ziyaretçi Raporları</b>
                            <span>Kullanım ve yoğunluk analizi</span>
                        </div>
                    </div>
                    <div class="hs-inc">
                        <i class="fas fa-screwdriver-wrench"></i>
                        <div>
                            <b>Ücretsiz Keşif</b>
                            <span>Yerinde bakar, projelendiririz</span>
                        </div>
                    </div>
                    <div class="hs-inc">
                        <i class="fas fa-headset"></i>
                        <div>
                            <b>7/24 Destek</b>
                            <span>Bursa içi yerinde müdahale</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Hizmet bölgeleri -->
    <section class="hs-section">
        <div class="container">
            <div class="hs-head">
                <span class="hs-badge"><i class="fas fa-location-dot"></i> Hizmet Bölgeleri</span>
                <h2>Bursa'da <span>Nerelerdeyiz</span></h2>
                <p>Nilüfer ve Osmangazi'de altyapımız hazır; keşif ve kurulum aynı hafta içinde.</p>
            </div>

            <div class="hs-spotlight">
                <div class="hs-spotlight-text">
                    <span class="hs-spotlight-tag"><i class="fas fa-location-dot"></i> Nilüfer</span>
                    <h3>Nilüfer Bölgesi</h3>
                    <p>
                        Bursa'nın modern yüzü. Alışveriş merkezleri, plazalar, kafeler ve üniversite
                        çevresindeki işletmeler için hotspot ve internet kurulumu yapıyoruz.
                    </p>
                    <ul class="hs-spotlight-points">
                        <li><i class="fas fa-check"></i> AVM ve plaza kurulumları</li>
                        <li><i class="fas fa-check"></i> Aynı gün teknik destek</li>
                        <li><i class="fas fa-check"></i> Çok katlı kapsama</li>
                    </ul>
                </div>

                <div class="hs-bars is-stack">
                    <div class="hs-bar-row">
                        <span class="hs-bar-label">Özlüce &amp; Beşevler</span>
                        <span class="hs-bar-val">AVM / plaza</span>
                    </div>
                    <div class="hs-bar-row">
                        <span class="hs-bar-label">İhsaniye &amp; Fethiye</span>
                        <span class="hs-bar-val">Kafe / restoran</span>
                    </div>
                    <div class="hs-bar-row">
                        <span class="hs-bar-label">Görükle &amp; Üniversite</span>
                        <span class="hs-bar-val">Öğrenci işletmeleri</span>
                    </div>
                    <div class="hs-bar-row">
                        <span class="hs-bar-label">Balat &amp; Konak</span>
                        <span class="hs-bar-val">Ofis / mağaza</span>
                    </div>
                    <p class="hs-bar-note">
                        Listede olmayan mahalleler için de keşif yapıyoruz; arayıp sorabilirsiniz.
                    </p>
                </div>
            </div>

            <div class="hs-spotlight is-flip">
                <div class="hs-spotlight-text">
                    <span class="hs-spotlight-tag"><i class="fas fa-location-dot"></i> Osmangazi</span>
                    <h3>Osmangazi Bölgesi</h3>
                    <p>
                        Bursa'nın tarihi merkezi. Oteller, restoranlar ve turistik işletmeler için
                        yüksek eşzamanlı kullanıcı kapasiteli sistemler kuruyoruz.
                    </p>
                    <ul class="hs-spotlight-points">
                        <li><i class="fas fa-check"></i> Otel ve konaklama tesisleri</li>
                        <li><i class="fas fa-check"></i> Yerinde kurulum ve destek</li>
                        <li><i class="fas fa-check"></i> Oda içi kapsama planı</li>
                    </ul>
                </div>

                <div class="hs-bars is-stack">
                    <div class="hs-bar-row">
                        <span class="hs-bar-label">Çekirge &amp; Termal</span>
                        <span class="hs-bar-val">Otel / konaklama</span>
                    </div>
                    <div class="hs-bar-row">
                        <span class="hs-bar-label">Setbaşı &amp; Altıparmak</span>
                        <span class="hs-bar-val">Restoran / kafe</span>
                    </div>
                    <div class="hs-bar-row">
                        <span class="hs-bar-label">Heykel &amp; Kent merkezi</span>
                        <span class="hs-bar-val">Mağaza / ofis</span>
                    </div>
                    <div class="hs-bar-row">
                        <span class="hs-bar-label">Santral Garaj çevresi</span>
                        <span class="hs-bar-val">Toplu kullanım alanı</span>
                    </div>
                    <p class="hs-bar-note">
                        Tarihi yapılarda kablolama kısıtlıysa mesh kurulum öneriyoruz.
                    </p>
                </div>
            </div>

            <div class="hs-tech">
                <div class="hs-tech-card" style="--tint: linear-gradient(135deg, #f59e0b, #b45309);">
                    <div class="hs-tech-icon"><i class="fas fa-bolt"></i></div>
                    <div>
                        <h4>İnternet Hattı Bizden</h4>
                        <p>Ayrı bir operatörle uğraşmazsınız. Hat, hotspot ve log kaydı tek sözleşme, tek fatura.</p>
                    </div>
                </div>
                <div class="hs-tech-card" style="--tint: linear-gradient(135deg, #22c55e, #15803d);">
                    <div class="hs-tech-icon"><i class="fas fa-shield-halved"></i></div>
                    <div>
                        <h4>5651 Uyumlu Log</h4>
                        <p>Misafir erişim kayıtları yasal süre boyunca saklanır; denetimde istenen çıktıyı biz veririz.</p>
                    </div>
                </div>
                <div class="hs-tech-card" style="--tint: linear-gradient(135deg, #0ea5e9, #0284c7);">
                    <div class="hs-tech-icon"><i class="fas fa-comment-sms"></i></div>
                    <div>
                        <h4>SMS ve Sosyal Giriş</h4>
                        <p>Misafir telefon numarasıyla ya da sosyal medya hesabıyla giriş yapar; kimlik kaydı otomatik tutulur.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Sık sorulanlar -->
    <section class="hs-section is-alt">
        <div class="container">
            <div class="hs-faq-layout">
                <div class="hs-faq-aside">
                    <div class="hs-head is-left">
                        <span class="hs-badge"><i class="fas fa-circle-question"></i> Sık Sorulanlar</span>
                        <h2>Aklınıza <span>Takılanlar</span></h2>
                        <p>Keşif öncesi en çok merak edilenleri derledik.</p>
                    </div>

                    <a href="iletisim.php" class="hs-faq-help">
                        <i class="fas fa-headset"></i>
                        <div>
                            <b>Sorunuz listede yok mu?</b>
                            <span>Bizi arayın, ücretsiz keşif planlayalım.</span>
                        </div>
                    </a>
                </div>

                <div class="hs-faq-list">
                    <details class="hs-faq-item">
                        <summary>
                            <span class="hs-faq-icon"><i class="fas fa-bolt"></i></span>
                            <span class="hs-faq-q">İnternet hattını da siz mi veriyorsunuz?</span>
                            <span class="hs-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="hs-faq-body">
                            Evet. Paketlerdeki hız, bizim sağladığımız fiber hattın hızıdır. Ayrı bir
                            operatörle sözleşme yapmanıza gerek kalmaz; hat, donanım ve log kaydı tek faturada.
                        </div>
                    </details>

                    <details class="hs-faq-item">
                        <summary>
                            <span class="hs-faq-icon"><i class="fas fa-plug"></i></span>
                            <span class="hs-faq-q">Mevcut internetimle de çalışır mı?</span>
                            <span class="hs-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="hs-faq-body">
                            Çalışır. Hattınızı değiştirmek istemiyorsanız yalnızca hotspot ve log sistemini
                            kurarız; bu durumda paket fiyatı hat bedeli düşülerek yeniden hesaplanır.
                        </div>
                    </details>

                    <details class="hs-faq-item">
                        <summary>
                            <span class="hs-faq-icon"><i class="fas fa-shield-halved"></i></span>
                            <span class="hs-faq-q">5651 yükümlülüğü beni kapsıyor mu?</span>
                            <span class="hs-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="hs-faq-body">
                            Müşterilerine internet erişimi sunan her işletme &mdash; kafe, restoran, otel,
                            AVM, ofis &mdash; toplu kullanım sağlayıcı sayılır ve erişim kayıtlarını
                            saklamakla yükümlüdür.
                        </div>
                    </details>

                    <details class="hs-faq-item">
                        <summary>
                            <span class="hs-faq-icon"><i class="fas fa-router"></i></span>
                            <span class="hs-faq-q">Cihazları satın almam gerekiyor mu?</span>
                            <span class="hs-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="hs-faq-body">
                            Hayır. Erişim noktaları ve hotspot cihazı pakete dahildir, mülkiyeti bizde kalır.
                            Arızalanan cihazı ücretsiz değiştiriyoruz.
                        </div>
                    </details>

                    <details class="hs-faq-item">
                        <summary>
                            <span class="hs-faq-icon"><i class="fas fa-clock"></i></span>
                            <span class="hs-faq-q">Kurulum ne kadar sürüyor?</span>
                            <span class="hs-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="hs-faq-body">
                            Keşiften sonra tipik bir kafe ya da restoran aynı gün içinde devreye alınır.
                            Otel ve AVM gibi çok katlı yerlerde kablolamaya göre birkaç gün sürebilir.
                        </div>
                    </details>

                    <details class="hs-faq-item">
                        <summary>
                            <span class="hs-faq-icon"><i class="fas fa-arrow-up-right-dots"></i></span>
                            <span class="hs-faq-q">Hızı sonradan yükseltebilir miyim?</span>
                            <span class="hs-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="hs-faq-body">
                            Evet. Üst pakete geçtiğinizde hat hızınız yükseltilir ve gerekirse ek erişim
                            noktası eklenir; kurulumu tekrar ücretlendirmiyoruz.
                        </div>
                    </details>
                </div>
            </div>
        </div>
    </section>

    <!-- Kapanış -->
    <section class="hs-final">
        <div class="container">
            <h3><i class="fas fa-wifi"></i> Nilüfer veya Osmangazi'de İşletmeniz mi Var?</h3>
            <p>Ücretsiz keşif ziyareti yapalım, yerinde ölçüp size uygun paketi birlikte belirleyelim</p>
            <div class="hs-final-actions">
                <a href="#paketler" class="hs-final-btn">
                    <i class="fas fa-wifi"></i> Paketleri İncele
                </a>
                <a href="iletisim.php" class="hs-final-link">
                    <i class="fas fa-phone"></i> Ücretsiz Keşif İsteyin
                </a>
            </div>
        </div>
    </section>
</div>

<!-- Keşif talebi penceresi -->
<div class="hs-modal" id="kesifModal" role="dialog" aria-modal="true" aria-labelledby="kesifBaslik" hidden>
    <div class="hs-modal-card">
        <div class="hs-modal-head">
            <div>
                <h3 id="kesifBaslik">Ücretsiz Keşif Talebi</h3>
                <p id="kesifPaketBilgi">İşletmenizin adresini seçin, keşif için sizi arayalım.</p>
            </div>
            <button type="button" class="hs-modal-kapat" data-kesif-kapat aria-label="Pencereyi kapat">
                <i class="fas fa-xmark"></i>
            </button>
        </div>

        <div class="hs-modal-govde">
            <form id="kesifForm" novalidate>
                <input type="hidden" name="paket_id" id="kesifPaketId" value="">
                <div class="hs-tuzak" aria-hidden="true">
                    <label for="kesifWebsite">Bu alanı boş bırakın</label>
                    <input type="text" id="kesifWebsite" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="hs-form-grid">
                    <div class="hs-alan">
                        <label for="kesifIl">İl</label>
                        <select id="kesifIl" disabled>
                            <option>Bursa</option>
                        </select>
                    </div>

                    <div class="hs-alan">
                        <label for="kesifIlce">İlçe <span class="zorunlu">*</span></label>
                        <select id="kesifIlce" name="ilce">
                            <option value="">Yükleniyor…</option>
                        </select>
                    </div>

                    <div class="hs-alan">
                        <label for="kesifMahalle">Mahalle <span class="zorunlu">*</span></label>
                        <select id="kesifMahalle" name="mahalle_id">
                            <option value="">Önce ilçe seçin</option>
                        </select>
                        <span class="hs-alan-hata" data-hata="mahalle_id"></span>
                    </div>

                    <div class="hs-alan">
                        <label for="kesifSokak">Sokak / Cadde</label>
                        <select id="kesifSokak" name="sokak_id">
                            <option value="">Önce mahalle seçin</option>
                        </select>
                        <input type="text" id="kesifSokakMetin" name="sokak" placeholder="Sokak veya cadde adı"
                            autocomplete="address-line1" hidden>
                        <span class="hs-alan-ipucu" id="kesifSokakIpucu" hidden>
                            Bu mahallenin sokak listesi henüz yüklenmedi, elle yazabilirsiniz.
                        </span>
                    </div>

                    <div class="hs-alan">
                        <label for="kesifBinaNo">Bina / Kapı No</label>
                        <input type="text" id="kesifBinaNo" name="bina_no" placeholder="Örn. 12/A"
                            autocomplete="address-line2">
                    </div>

                    <div class="hs-alan">
                        <label for="kesifIsletme">İşletme Türü</label>
                        <select id="kesifIsletme" name="isletme_turu">
                            <option value="">Seçiniz</option>
                            <option>Kafe</option>
                            <option>Restoran</option>
                            <option>Otel / Konaklama</option>
                            <option>AVM / Plaza</option>
                            <option>Ofis</option>
                            <option>Mağaza</option>
                            <option>Diğer</option>
                        </select>
                    </div>

                    <div class="hs-alan">
                        <label for="kesifAd">Ad Soyad <span class="zorunlu">*</span></label>
                        <input type="text" id="kesifAd" name="ad_soyad" autocomplete="name" required>
                        <span class="hs-alan-hata" data-hata="ad_soyad"></span>
                    </div>

                    <div class="hs-alan">
                        <label for="kesifTelefon">Telefon <span class="zorunlu">*</span></label>
                        <input type="tel" id="kesifTelefon" name="telefon" placeholder="532 123 45 67"
                            autocomplete="tel" inputmode="tel" dir="ltr" required>
                        <span class="hs-alan-hata" data-hata="telefon"></span>
                    </div>

                    <div class="hs-alan is-full">
                        <label for="kesifEmail">E-posta <span class="hs-alan-ipucu">(isteğe bağlı)</span></label>
                        <input type="email" id="kesifEmail" name="email" autocomplete="email" dir="ltr">
                        <span class="hs-alan-hata" data-hata="email"></span>
                    </div>

                    <div class="hs-alan is-full">
                        <label for="kesifAciklama">Eklemek istedikleriniz</label>
                        <textarea id="kesifAciklama" name="aciklama"
                            placeholder="Kaç katlı, kaç masa, mevcut internet var mı…"></textarea>
                    </div>
                </div>

                <div class="hs-modal-alt">
                    <button type="submit" class="hs-modal-gonder" id="kesifGonder">
                        <i class="fas fa-paper-plane"></i> Keşif Talebi Gönder
                    </button>
                    <button type="button" class="hs-modal-vazgec" data-kesif-kapat>Vazgeç</button>
                    <p class="hs-modal-durum" id="kesifDurum" role="status" aria-live="polite"></p>
                </div>
            </form>
        </div>

        <div class="hs-modal-basarili">
            <i class="fas fa-circle-check"></i>
            <h4>Talebiniz alındı</h4>
            <p id="kesifSonuc">Keşif için en kısa sürede sizi arayacağız.</p>
            <div class="hs-modal-alt" style="justify-content: center; border: 0;">
                <button type="button" class="hs-modal-gonder" data-kesif-kapat>
                    <i class="fas fa-check"></i> Kapat
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // "+N özellik daha" düğmesi: listedeki gizli satırları açar
    document.querySelectorAll('.hs-more').forEach(function (btn) {
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

    /* ===== Keşif talebi penceresi =====
       Adres listesi api/kesif.php'den gelir. Sokak listesi boşsa
       alan serbest metne düşer, form yine gönderilebilir. */
    (function () {
        var modal = document.getElementById('kesifModal');
        if (!modal) return;

        var form = document.getElementById('kesifForm');
        var ilceSec = document.getElementById('kesifIlce');
        var mahalleSec = document.getElementById('kesifMahalle');
        var sokakSec = document.getElementById('kesifSokak');
        var sokakMetin = document.getElementById('kesifSokakMetin');
        var sokakIpucu = document.getElementById('kesifSokakIpucu');
        var durum = document.getElementById('kesifDurum');
        var gonderBtn = document.getElementById('kesifGonder');
        var paketBilgi = document.getElementById('kesifPaketBilgi');
        var paketIdAlan = document.getElementById('kesifPaketId');
        var sonucMetin = document.getElementById('kesifSonuc');

        var mahalleler = null;
        var token = '';
        var sokakOnbellek = {};
        var sonOdak = null;

        function metin(el, deger) { if (el) el.textContent = deger; }

        function hatalariTemizle() {
            form.querySelectorAll('.hs-alan.has-error').forEach(function (a) { a.classList.remove('has-error'); });
            form.querySelectorAll('[data-hata]').forEach(function (s) { s.textContent = ''; });
            durum.textContent = '';
            durum.classList.remove('is-hata');
        }

        function hataGoster(alanlar) {
            Object.keys(alanlar || {}).forEach(function (ad) {
                var kutu = form.querySelector('[data-hata="' + ad + '"]');
                if (!kutu) return;
                kutu.textContent = alanlar[ad];
                var sarmal = kutu.closest('.hs-alan');
                if (sarmal) sarmal.classList.add('has-error');
            });
        }

        // bosMetin null ise yer tutucu eklenmez (tek seçenek varsa gereksiz)
        function secenekleriDoldur(sec, secenekler, bosMetin) {
            sec.innerHTML = '';
            if (bosMetin !== null) {
                var bos = document.createElement('option');
                bos.value = '';
                bos.textContent = bosMetin;
                sec.appendChild(bos);
            }
            secenekler.forEach(function (o) {
                var op = document.createElement('option');
                op.value = o.value;
                op.textContent = o.label;
                sec.appendChild(op);
            });
        }

        function ilceleriDoldur() {
            var gorulen = {};
            var liste = [];
            mahalleler.forEach(function (m) {
                if (!gorulen[m.ilce]) { gorulen[m.ilce] = true; liste.push({ value: m.ilce, label: m.ilce }); }
            });
            if (liste.length === 1) {
                secenekleriDoldur(ilceSec, liste, null);
                ilceSec.value = liste[0].value;
                mahalleleriDoldur(liste[0].value);
            } else {
                secenekleriDoldur(ilceSec, liste, 'Seçiniz');
            }
        }

        function mahalleleriDoldur(ilce) {
            var liste = mahalleler
                .filter(function (m) { return m.ilce === ilce; })
                .map(function (m) { return { value: m.id, label: m.mahalle }; });
            secenekleriDoldur(mahalleSec, liste, liste.length ? 'Seçiniz' : 'Bu ilçede kayıt yok');
            sokakSifirla('Önce mahalle seçin');
        }

        function sokakSifirla(bosMetin) {
            secenekleriDoldur(sokakSec, [], bosMetin);
            sokakSec.hidden = false;
            sokakMetin.hidden = true;
            sokakMetin.value = '';
            sokakIpucu.hidden = true;
        }

        function sokaklariYukle(mahalleId) {
            if (!mahalleId) { sokakSifirla('Önce mahalle seçin'); return; }

            function uygula(sokaklar) {
                if (sokaklar.length) {
                    secenekleriDoldur(sokakSec, sokaklar.map(function (s) {
                        return { value: s.id, label: s.sokak };
                    }), 'Seçiniz');
                    sokakSec.hidden = false;
                    sokakMetin.hidden = true;
                    sokakIpucu.hidden = true;
                } else {
                    // Liste henüz yüklenmemiş: serbest metne düş
                    sokakSec.hidden = true;
                    sokakSec.value = '';
                    sokakMetin.hidden = false;
                    sokakIpucu.hidden = false;
                }
            }

            if (sokakOnbellek[mahalleId]) { uygula(sokakOnbellek[mahalleId]); return; }

            secenekleriDoldur(sokakSec, [], 'Yükleniyor…');
            fetch('api/kesif.php?islem=sokaklar&mahalle=' + encodeURIComponent(mahalleId), {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    var liste = (d && d.ok && d.sokaklar) ? d.sokaklar : [];
                    sokakOnbellek[mahalleId] = liste;
                    uygula(liste);
                })
                .catch(function () { sokakOnbellek[mahalleId] = []; uygula([]); });
        }

        function adresleriYukle() {
            if (mahalleler) return Promise.resolve();
            return fetch('api/kesif.php?islem=mahalleler', { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d || !d.ok) throw new Error('liste alinamadi');
                    mahalleler = d.mahalleler || [];
                    token = d.token || '';
                    ilceleriDoldur();
                })
                .catch(function () {
                    secenekleriDoldur(ilceSec, [], 'Liste yüklenemedi');
                    durum.textContent = 'Adres listesi yüklenemedi. Lütfen bizi telefonla arayın.';
                    durum.classList.add('is-hata');
                });
        }

        function ac(btn) {
            sonOdak = btn || document.activeElement;
            hatalariTemizle();
            modal.classList.remove('is-done');
            modal.hidden = false;
            modal.classList.add('is-open');
            document.body.style.overflow = 'hidden';

            var paketId = btn ? btn.getAttribute('data-paket') : '';
            var paketAdi = btn ? btn.getAttribute('data-paket-adi') : '';
            paketIdAlan.value = paketId || '';
            metin(paketBilgi, paketAdi
                ? paketAdi + ' paketi için keşif talebi. Adresinizi seçin, sizi arayalım.'
                : 'İşletmenizin adresini seçin, keşif için sizi arayalım.');

            adresleriYukle().then(function () {
                var ilk = form.querySelector('select:not(:disabled), input:not([type=hidden])');
                if (ilk) ilk.focus();
            });
        }

        function kapat() {
            modal.classList.remove('is-open');
            modal.hidden = true;
            document.body.style.overflow = '';
            if (sonOdak && sonOdak.focus) sonOdak.focus();
        }

        document.querySelectorAll('.hs-cta[data-kesif-ac]').forEach(function (btn) {
            btn.addEventListener('click', function () { ac(btn); });
        });
        document.querySelectorAll('[data-kesif-kapat]').forEach(function (btn) {
            btn.addEventListener('click', kapat);
        });
        modal.addEventListener('click', function (e) { if (e.target === modal) kapat(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) kapat();
        });

        ilceSec.addEventListener('change', function () { mahalleleriDoldur(ilceSec.value); });
        mahalleSec.addEventListener('change', function () { sokaklariYukle(mahalleSec.value); });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            hatalariTemizle();

            var veri = {
                token: token,
                paket_id: paketIdAlan.value,
                website: document.getElementById('kesifWebsite').value,
                mahalle_id: mahalleSec.value,
                sokak_id: sokakSec.hidden ? '' : sokakSec.value,
                sokak: sokakSec.hidden ? sokakMetin.value : '',
                bina_no: document.getElementById('kesifBinaNo').value,
                isletme_turu: document.getElementById('kesifIsletme').value,
                ad_soyad: document.getElementById('kesifAd').value,
                telefon: document.getElementById('kesifTelefon').value,
                email: document.getElementById('kesifEmail').value,
                aciklama: document.getElementById('kesifAciklama').value
            };

            gonderBtn.disabled = true;
            durum.textContent = 'Gönderiliyor…';

            fetch('api/kesif.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(veri)
            })
                .then(function (r) { return r.json().then(function (d) { return { kod: r.status, veri: d }; }); })
                .then(function (c) {
                    if (c.veri && c.veri.ok) {
                        metin(sonucMetin, c.veri.mesaj || 'Keşif için en kısa sürede sizi arayacağız.');
                        modal.classList.add('is-done');
                        form.reset();
                        return;
                    }
                    durum.textContent = (c.veri && c.veri.hata) || 'Talep gönderilemedi.';
                    durum.classList.add('is-hata');
                    if (c.veri && c.veri.alanlar) hataGoster(c.veri.alanlar);
                })
                .catch(function () {
                    durum.textContent = 'Bağlantı kurulamadı. Lütfen bizi telefonla arayın.';
                    durum.classList.add('is-hata');
                })
                .finally(function () { gonderBtn.disabled = false; });
        });
    })();
</script>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>
