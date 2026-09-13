<?php
/**
 * WHMVM - SSL Sertifikası sayfası
 *
 * Sertifikalar veritabanından (product_groups.slug = 'ssl-sertifikasi') okunur.
 * SSL yıllık satıldığı için kartlarda price_annually gösterilir.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'SSL Sertifikası';
$pageDescription = 'DV, OV, EV ve Wildcard SSL sertifikaları. Ücretsiz kurulum, 256-bit şifreleme ve 7/24 destek.';

/* Sertifikaları çek; veritabanı hazır değilse sayfa boş listeyle açılır */
$sslProducts = [];
try {
    $sslGroup = Database::fetch(
        "SELECT id FROM product_groups WHERE slug = ? LIMIT 1",
        ['ssl-sertifikasi']
    );
    if (!empty($sslGroup['id'])) {
        $sslProducts = Database::fetchAll(
            "SELECT * FROM products
              WHERE group_id = ? AND is_active = 1 AND is_hidden = 0
              ORDER BY order_priority, price_annually",
            [(int) $sslGroup['id']]
        );
    }
} catch (Throwable $e) {
    $sslProducts = [];
}

/**
 * Ürün açıklamasının ilk satırları vitrin kutucuğuna, kalanı özellik listesine gider.
 * Değer baştadır, etiket sonda: "256-bit Şifreleme" -> değer "256-bit", etiket "Şifreleme".
 */
$slSpecTypes = [
    'dogrulama' => ['label' => 'Doğrulama', 'icon' => 'fa-user-check', 'match' => '/doğrulama/iu'],
    'kapsam' => ['label' => 'Kapsam', 'icon' => 'fa-globe', 'match' => '/kapsam/iu'],
    'sifreleme' => ['label' => 'Şifreleme', 'icon' => 'fa-lock', 'match' => '/şifreleme/iu'],
    'garanti' => ['label' => 'Garanti', 'icon' => 'fa-shield-halved', 'match' => '/garanti/iu'],
];

$slSpecValue = static function (string $line): string {
    // Türkçe büyük İ (U+0130) /i bayrağıyla küçük i'ye katlanmadığı için sınıf kullanılır
    $deger = preg_replace('/\s*([dD]oğrulama|[kK]apsam|[şŞ]ifreleme|[gG]aranti).*$/u', '', $line);
    $deger = trim((string) $deger);
    return $deger !== '' ? $deger : trim($line);
};

require_once __DIR__ . '/theme/includes/header.php';
?>

<style>

        /* ==========================================
           SSL Sertifikası - sayfaya özel değişkenler
           Vurgu rengi ve yüzeyler global tema
           token'larından gelir.
           ========================================== */
        .sl {
            --sl-accent: var(--primary);
            --sl-accent-light: var(--primary);
            --sl-accent-dark: var(--primary-dark);
            --sl-accent-soft: color-mix(in srgb, var(--primary) 12%, transparent);
            --sl-accent-line: color-mix(in srgb, var(--primary) 28%, transparent);
            --sl-gradient: var(--gradient-primary);
        }

        /* ===== Hero ===== */
        .sl-hero {
            position: relative;
            overflow: hidden;
            background: var(--gradient-hero);
            padding: var(--space-6) 0 0;
        }

        .sl-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 55% 50% at 12% 30%, color-mix(in srgb, var(--primary) 22%, transparent) 0%, transparent 62%),
                radial-gradient(ellipse 45% 45% at 88% 10%, color-mix(in srgb, var(--secondary) 16%, transparent) 0%, transparent 58%);
            pointer-events: none;
        }

        .sl-hero>.container {
            position: relative;
            z-index: 1;
        }

        /* Tek kolon, ortalanmis: hero'da ayrica gorsel yok */
        .sl-hero-grid {
            max-width: 780px;
            margin: 0 auto;
            padding-bottom: var(--space-6);
            text-align: center;
        }

        .sl-hero-text .sl-lead {
            margin-left: auto;
            margin-right: auto;
        }

        .sl-hero-text .sl-stack {
            justify-content: center;
        }

        .sl-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 50px;
            background: var(--sl-accent-soft);
            border: 1px solid var(--sl-accent-line);
            color: var(--sl-accent-light);
            font-size: var(--text-sm);
            font-weight: 600;
            margin-bottom: var(--space-4);
        }

        .sl-title {
            /* Ürün sayfası; anasayfa kadar iri olmasına gerek yok */
            font-size: clamp(32px, 3.2vw + 16px, 50px);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -0.035em;
            margin-bottom: var(--space-3);
            color: var(--text-primary);
            text-wrap: balance;
        }

        .sl-title span {
            background: var(--sl-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .sl-lead {
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
        .sl-stack {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .sl-chip {
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

        .sl-chip:hover {
            background: var(--surface-2);
            border-color: var(--sl-accent-line);
        }

        .sl-chip i {
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
        .sl-bars.is-stack .sl-bar-row {
            grid-template-columns: minmax(0, 1fr) auto;
        }

        /* Windows teknoloji cip renkleri */
        .sl-chip i.is-dv {
            background: linear-gradient(135deg, #22c55e, #15803d);
        }

        .sl-chip i.is-wild {
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
        }

        .sl-chip i.is-kurulum {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
        }

        .sl-chip i.is-tarayici {
            background: linear-gradient(135deg, #f59e0b, #b45309);
        }

        /* ===== Bölüm başlıkları ===== */
        .sl-section {
            padding: var(--section-padding) 0;
        }

        /* Paketler bölümü hemen sekme şeridinin altında;
           sekmeler zaten ayırıcı, üstte tam boşluğa gerek yok */
        .sl-hero+.sl-section {
            padding-top: var(--space-7);
        }

        .sl-section.is-alt {
            background: var(--bg-primary);
        }

        .sl-head {
            text-align: center;
            max-width: 680px;
            margin: 0 auto var(--space-7);
        }

        .sl-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 50px;
            background: var(--sl-accent-soft);
            border: 1px solid var(--sl-accent-line);
            color: var(--sl-accent-light);
            font-size: var(--text-sm);
            font-weight: 600;
            margin-bottom: var(--space-4);
        }

        .sl-head h2 {
            font-size: var(--text-3xl);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.15;
            color: var(--text-primary);
            text-wrap: balance;
        }

        .sl-head h2 span {
            background: var(--sl-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .sl-head p {
            margin-top: var(--space-3);
            font-size: var(--text-md);
            color: var(--text-muted);
            text-wrap: pretty;
        }

        /* ===== Paket kartları ===== */
        .sl-plans {
            display: grid;
            /* Sutun sayisi paket adedinden gelir; auto-fit reflow yapmaz */
            grid-template-columns: repeat(var(--plan-cols, 4), minmax(0, 1fr));
            gap: var(--space-5);
            /* Kartlar eşit yükseklikte olsun, sipariş butonları hizalansın */
            align-items: stretch;
        }

        .sl-plans[data-count="1"] {
            --plan-cols: 1;
            max-width: 420px;
            margin-inline: auto;
        }

        .sl-plans[data-count="2"] {
            --plan-cols: 2;
        }

        .sl-plans[data-count="3"] {
            --plan-cols: 3;
        }

        @media (max-width: 1180px) {
            .sl-plans[data-count] {
                --plan-cols: 2;
            }
        }

        @media (max-width: 680px) {
            .sl-plans[data-count] {
                --plan-cols: 1;
            }
        }

        /* Az paket varsa ortala, kartlar aşırı genişlemesin */
        .sl-plans[data-count="1"] {
            grid-template-columns: minmax(300px, 420px);
            justify-content: center;
        }

        .sl-plans[data-count="2"] {
            grid-template-columns: repeat(2, minmax(300px, 430px));
            justify-content: center;
        }

        .sl-plans[data-count="3"] {
            grid-template-columns: repeat(3, minmax(280px, 400px));
            justify-content: center;
        }

        .sl-plan {
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

        .sl-plan:hover {
            transform: translateY(-6px);
            border-color: var(--sl-accent-line);
            box-shadow: var(--shadow-lg);
        }

        .sl-plan.is-popular {
            border-color: var(--sl-accent);
            box-shadow: 0 0 0 1px var(--sl-accent), 0 24px 48px -18px color-mix(in srgb, var(--primary) 45%, transparent);
        }

        .sl-ribbon {
            position: absolute;
            top: 0;
            right: var(--space-5);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 0 0 10px 10px;
            background: var(--sl-gradient);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .sl-plan-top {
            padding: var(--space-6) var(--space-5) var(--space-5);
            border-bottom: 1px solid var(--border-color);
        }

        .sl-plan-name {
            font-size: var(--text-xl);
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .sl-plan-group {
            font-size: var(--text-sm);
            color: var(--text-muted);
            margin-bottom: var(--space-5);
        }

        .sl-price {
            display: flex;
            align-items: baseline;
            gap: 4px;
            /* Rakamlar kartlar arasında hizalı dursun */
            font-variant-numeric: tabular-nums;
        }

        .sl-price .cur {
            font-size: var(--text-lg);
            font-weight: 700;
            color: var(--sl-accent);
        }

        .sl-price .val {
            font-size: clamp(34px, 3vw + 22px, 46px);
            font-weight: 800;
            letter-spacing: -0.04em;
            line-height: 1;
            color: var(--text-primary);
        }

        .sl-price .per {
            font-size: var(--text-base);
            color: var(--text-muted);
            font-weight: 500;
        }

        .sl-price-ask {
            font-size: var(--text-xl);
            font-weight: 700;
            color: var(--text-primary);
        }

        .sl-price-note {
            margin-top: var(--space-2);
            font-size: var(--text-sm);
            color: var(--text-muted);
        }

        .sl-save {
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
        .sl-save.is-placeholder {
            visibility: hidden;
        }

        /* Teknik özet kutucukları */
        .sl-specs {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1px;
            background: var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .sl-spec {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-4);
            background: var(--bg-primary);
            min-width: 0;
        }

        /* Uzun değerler (garanti tutarı, kapsam metni) hücreden taşmasın */
        .sl-spec>div {
            min-width: 0;
        }

        .sl-spec i {
            width: 30px;
            height: 30px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: var(--sl-accent-soft);
            color: var(--sl-accent-light);
            font-size: 13px;
        }

        .sl-spec b {
            display: block;
            font-size: var(--text-sm);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
            overflow-wrap: break-word;
            hyphens: none;
        }

        .sl-spec span {
            font-size: 11px;
            color: var(--text-muted);
        }

        .sl-plan-body {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: var(--space-5);
        }

        .sl-features {
            list-style: none;
            margin: 0 0 var(--space-5);
            padding: 0;
            display: grid;
            gap: 2px;
        }

        .sl-features li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 7px 0;
            font-size: var(--text-sm);
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .sl-features li i {
            margin-top: 3px;
            font-size: 11px;
            color: #22c55e;
            flex-shrink: 0;
        }

        /* Uzun listelerde kart şişmesin: fazlası gizli, düğmeyle açılır */
        .sl-features.is-clipped li:nth-child(n+7) {
            display: none;
        }

        .sl-more {
            align-self: flex-start;
            margin: calc(var(--space-5) * -1 + 4px) 0 var(--space-5);
            padding: 6px 0;
            background: none;
            border: none;
            color: var(--sl-accent-light);
            font-family: inherit;
            font-size: var(--text-sm);
            font-weight: 600;
            cursor: pointer;
        }

        .sl-more:hover {
            text-decoration: underline;
        }

        .sl-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            /* Buton kartın en altına yapışsın, kartlar eşit hizalansın */
            margin-top: auto;
            padding: 15px 24px;
            border-radius: var(--radius-md);
            border: 1px solid transparent;
            background: var(--sl-gradient);
            color: #fff;
            font-size: var(--text-base);
            font-weight: 700;
            box-shadow: 0 8px 22px color-mix(in srgb, var(--primary) 30%, transparent);
            transition: transform var(--transition-fast) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .sl-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 34px color-mix(in srgb, var(--primary) 48%, transparent);
        }

        .sl-cta:active {
            transform: translateY(0) scale(0.99);
        }

        /* Boş durum */
        .sl-empty {
            text-align: center;
            padding: var(--space-9) var(--space-5);
            border: 1px dashed var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--surface-1);
        }

        .sl-empty i {
            font-size: 42px;
            color: var(--sl-accent);
            margin-bottom: var(--space-4);
        }

        .sl-empty h3 {
            font-size: var(--text-xl);
            color: var(--text-primary);
            margin-bottom: var(--space-2);
        }

        .sl-empty p {
            color: var(--text-muted);
        }

        /* ===== "Her pakette standart" paneli =====
           8 ayrı kart yerine tek panel: hepsinin dahil olduğu
           tek bir vaat gibi okunur, dikey yer de yarıya iner. */
        .sl-included {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--surface-1);
            overflow: hidden;
        }

        .sl-included-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1px;
            background: var(--border-color);
        }

        .sl-inc {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            background: var(--bg-primary);
            transition: background-color var(--transition-normal) var(--ease-out);
        }

        .sl-inc:hover {
            background: color-mix(in srgb, var(--sl-accent) 6%, var(--bg-primary));
        }

        .sl-inc i {
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: var(--sl-accent-soft);
            color: var(--sl-accent-light);
            font-size: 14px;
        }

        .sl-inc b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .sl-inc span {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        /* ===== Teknoloji: bir büyük vitrin + üç kart ===== */
        .sl-spotlight {
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

        .sl-spotlight-tag {
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

        .sl-spotlight h3 {
            font-size: var(--text-2xl);
            font-weight: 800;
            letter-spacing: -0.025em;
            color: var(--text-primary);
            margin-bottom: var(--space-3);
        }

        .sl-spotlight p {
            font-size: var(--text-md);
            color: var(--text-muted);
            line-height: 1.7;
            margin-bottom: var(--space-5);
            max-width: 46ch;
        }

        .sl-spotlight-points {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .sl-spotlight-points li {
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

        .sl-spotlight-points i {
            color: #4ade80;
            font-size: 10px;
        }

        /* Hız karşılaştırma çubukları */
        .sl-bars {
            display: grid;
            gap: var(--space-4);
        }

        .sl-bar-row {
            display: grid;
            grid-template-columns: 78px minmax(0, 1fr) 42px;
            align-items: center;
            gap: var(--space-3);
        }

        .sl-bar-label {
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--text-muted);
        }

        .sl-bar {
            height: 14px;
            border-radius: 50px;
            background: var(--surface-3);
            overflow: hidden;
        }

        .sl-bar span {
            display: block;
            height: 100%;
            border-radius: 50px;
            background: var(--text-gray);
        }

        .sl-bar-val {
            font-size: var(--text-base);
            font-weight: 800;
            color: var(--text-muted);
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .sl-bar-row.is-lead .sl-bar-label,
        .sl-bar-row.is-lead .sl-bar-val {
            color: var(--text-primary);
        }

        .sl-bar-row.is-lead .sl-bar span {
            background: linear-gradient(90deg, #22c55e, #15803d);
        }

        .sl-bar-note {
            font-size: var(--text-xs);
            color: var(--text-gray);
            line-height: 1.5;
            margin: 0;
        }

        .sl-tech {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: var(--space-5);
        }

        .sl-tech-card {
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

        .sl-tech-card::before {
            content: '';
            position: absolute;
            inset: 0 0 auto 0;
            height: 3px;
            background: var(--tint, var(--sl-gradient));
            transform: scaleX(0);
            transition: transform var(--transition-normal) var(--ease-out);
        }

        .sl-tech-card:hover {
            transform: translateY(-4px);
            background: var(--surface-2);
        }

        .sl-tech-card:hover::before {
            transform: scaleX(1);
        }

        .sl-tech-icon {
            width: 46px;
            height: 46px;
            flex-shrink: 0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            color: #fff;
            background: var(--tint, var(--sl-gradient));
        }

        .sl-tech-card h4 {
            font-size: var(--text-lg);
            font-weight: 700;
            letter-spacing: -0.015em;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .sl-tech-card p {
            font-size: var(--text-sm);
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* ===== Donanım çizimleri =====
           Dell R730 ve EMC VNX7600 ön panelleri SVG olarak çizilir.
           Bütün renkler tema token'larına bağlıdır; açık/koyu temada
           ayrı bir görsel gerekmez. */
        .sl-spotlight.is-flip {
            grid-template-columns: minmax(0, 0.85fr) minmax(0, 1fr);
            background:
                radial-gradient(ellipse 60% 90% at 0% 50%, color-mix(in srgb, #22c55e 10%, transparent) 0%, transparent 70%),
                var(--surface-1);
        }

        .sl-spotlight.is-flip .sl-spotlight-text {
            order: 2;
        }

        .sl-spotlight.is-flip .sl-hw {
            order: 1;
        }

        /* ===== Sık sorulan sorular ===== */
        /* Solda görsel + başlık, sağda soru listesi */
        .sl-faq-layout {
            display: grid;
            grid-template-columns: minmax(0, 330px) minmax(0, 1fr);
            gap: var(--space-8);
            /* Sol sutun listeyle ayni yuksekligi alsin */
            align-items: stretch;
        }

        .sl-faq-aside {
            display: flex;
            flex-direction: column;
        }

        .sl-head.is-left {
            text-align: left;
            max-width: 100%;
            margin: 0 0 var(--space-5);
        }

        .sl-faq-visual {
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

        .sl-faq-visual svg {
            width: 100%;
            height: 100%;
            max-height: 240px;
            display: block;
        }

        /* "Sorunuz yoksa bize yazın" kutusu */
        .sl-faq-help {
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

        .sl-faq-help:hover {
            border-color: var(--sl-accent-line);
            background: var(--surface-2);
        }

        .sl-faq-help i {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--sl-gradient);
            color: #fff;
            font-size: 15px;
        }

        .sl-faq-help b {
            display: block;
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .sl-faq-help span {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        .sl-faq {
            display: grid;
            gap: var(--space-3);
        }

        .sl-faq-item {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--surface-1);
            overflow: hidden;
            transition: border-color var(--transition-normal) var(--ease-out),
                background-color var(--transition-normal) var(--ease-out);
        }

        .sl-faq-item[open] {
            border-color: var(--sl-accent-line);
            background: var(--surface-2);
        }

        .sl-faq-item summary {
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
        .sl-faq-icon {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--sl-accent-soft);
            border: 1px solid var(--sl-accent-line);
            color: var(--sl-accent-light);
            transition: background-color var(--transition-normal) var(--ease-out),
                color var(--transition-normal) var(--ease-out);
        }

        .sl-faq-icon svg {
            width: 19px;
            height: 19px;
            display: block;
        }

        .sl-faq-item[open] .sl-faq-icon {
            background: var(--sl-gradient);
            border-color: transparent;
            color: #fff;
        }

        /* Soru metni ile artı işareti arasını doldurur */
        .sl-faq-q {
            flex: 1;
            min-width: 0;
        }

        /* Tarayıcının varsayılan üçgen işaretini kaldır */
        .sl-faq-item summary::-webkit-details-marker {
            display: none;
        }

        .sl-faq-item summary::marker {
            content: '';
        }

        .sl-faq-item summary:hover {
            color: var(--sl-accent-light);
        }

        .sl-faq-item summary:focus-visible {
            outline: none;
            box-shadow: var(--focus-ring);
        }

        .sl-faq-sign {
            width: 28px;
            height: 28px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--sl-accent-soft);
            color: var(--sl-accent-light);
            font-size: 12px;
            transition: transform var(--transition-normal) var(--ease-out);
        }

        .sl-faq-item[open] .sl-faq-sign {
            transform: rotate(45deg);
        }

        .sl-faq-body {
            /* Cevap, sorunun metniyle aynı hizadan başlasın (ikon + boşluk kadar içeride) */
            padding: 0 var(--space-5) var(--space-5) calc(var(--space-5) + 38px + var(--space-3));
            font-size: var(--text-base);
            color: var(--text-muted);
            line-height: 1.75;
            max-width: 72ch;
        }

        /* ===== Kapanış çağrısı ===== */
        .sl-final {
            position: relative;
            overflow: hidden;
            padding: var(--space-8) 0;
            background: var(--sl-gradient);
            color: #fff;
            text-align: center;
        }

        .sl-final::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 100% at 50% 0%, rgba(255, 255, 255, 0.2) 0%, transparent 62%),
                radial-gradient(ellipse 50% 80% at 88% 100%, rgba(0, 0, 0, 0.18) 0%, transparent 60%);
            pointer-events: none;
        }

        .sl-final>.container {
            position: relative;
            z-index: 1;
        }

        .sl-final h3 {
            font-size: var(--text-2xl);
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: var(--space-3);
            text-wrap: balance;
        }

        .sl-final p {
            font-size: var(--text-md);
            opacity: 0.92;
            margin-bottom: var(--space-6);
        }

        .sl-final-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: var(--space-3);
        }

        .sl-final-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 32px;
            border-radius: var(--radius-md);
            background: #fff;
            color: var(--sl-accent-dark);
            font-size: var(--text-md);
            font-weight: 700;
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.2);
            transition: transform var(--transition-fast) var(--ease-out),
                box-shadow var(--transition-normal) var(--ease-out);
        }

        .sl-final-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.28);
        }

        .sl-final-link {
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

        .sl-final-link:hover {
            background: rgba(255, 255, 255, 0.22);
            border-color: #fff;
        }

        /* ===== Duyarlılık ===== */
        @media (max-width: 1100px) {
            .sl-included-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .sl-spotlight {
                grid-template-columns: 1fr;
                gap: var(--space-6);
                padding: var(--space-6);
            }

            .sl-spotlight p {
                max-width: 100%;
            }
        }

        @media (max-width: 992px) {
            .sl-lead {
                max-width: 100%;
            }

            .sl-tech {
                grid-template-columns: 1fr;
            }

            /* Görsel ve başlık üste, sorular altına */
            .sl-faq-layout {
                grid-template-columns: 1fr;
                gap: var(--space-6);
                justify-items: center;
            }

            .sl-faq-aside {
                max-width: 460px;
                width: 100%;
            }

            .sl-head.is-left {
                text-align: center;
            }

            .sl-faq-visual {
                flex: 0 0 auto;
                margin: 0 auto;
            }

            .sl-faq-visual svg {
                height: auto;
            }

            .sl-faq {
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            .sl-plans,
            .sl-plans[data-count="2"],
            .sl-plans[data-count="3"] {
                grid-template-columns: 1fr;
            }

            .sl-included-grid {
                grid-template-columns: 1fr;
            }

            .sl-bar-row {
                grid-template-columns: 66px minmax(0, 1fr) 34px;
                gap: var(--space-2);
            }

            .sl-final-actions {
                flex-direction: column;
            }

            .sl-final-actions>* {
                width: 100%;
                justify-content: center;
            }

            /* Dar ekranda cevabı ikon hizasında girintilemek yer israfı olur */
            .sl-faq-body {
                padding-left: var(--space-5);
            }

            .sl-faq-item summary {
                padding-left: var(--space-4);
                padding-right: var(--space-4);
            }
        }
</style>

<div class="sl">
    <!-- Hero -->
    <section class="sl-hero">
        <div class="container">
            <div class="sl-hero-grid">
                <div class="sl-hero-text">
                    <span class="sl-eyebrow"><i class="fas fa-lock"></i> 256-bit Şifreleme</span>
                    <h1 class="sl-title"><span>SSL Sertifikası</span> Çözümleri</h1>
                    <p class="sl-lead">
                        Ziyaretçinizle sunucunuz arasındaki trafiği şifreleyin. Kurulumu biz yapıyoruz,
                        yenileme hatırlatmasını biz takip ediyoruz.
                    </p>

                    <div class="sl-stack">
                        <span class="sl-chip"><i class="fas fa-user-check is-dv"></i> DV / OV / EV</span>
                        <span class="sl-chip"><i class="fas fa-asterisk is-wild"></i> Wildcard</span>
                        <span class="sl-chip"><i class="fas fa-screwdriver-wrench is-kurulum"></i> Ücretsiz Kurulum</span>
                        <span class="sl-chip"><i class="fas fa-globe is-tarayici"></i> Tarayıcı Uyumu</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Sertifikalar -->
    <section class="sl-section" id="paketler">
        <div class="container">
            <div class="sl-head">
                <span class="sl-badge"><i class="fas fa-lock"></i> SSL Sertifikaları</span>
                <h2>Sitenize Uygun <span>Sertifika</span></h2>
                <p>Bütün sertifikalarda ücretsiz kurulum, sınırsız yeniden düzenleme ve 7/24 destek standarttır.</p>
            </div>

            <?php if (empty($sslProducts)): ?>
                <div class="sl-empty">
                    <i class="fas fa-lock"></i>
                    <h3>Sertifika Bulunamadı</h3>
                    <p>Şu anda listelenecek SSL sertifikası bulunmuyor.</p>
                </div>
            <?php else:
                $sslCount = count($sslProducts);

                // Öne çıkan işaretliyse o, değilse ortadaki sertifika vurgulanır
                $populerIndex = -1;
                foreach ($sslProducts as $i => $p) {
                    if (!empty($p['is_featured'])) {
                        $populerIndex = $i;
                        break;
                    }
                }
                if ($populerIndex === -1 && $sslCount > 2) {
                    $populerIndex = (int) floor(($sslCount - 1) / 2);
                }
                ?>
                <div class="sl-plans" data-count="<?= $sslCount ?>">
                    <?php foreach ($sslProducts as $index => $product):
                        $isPopular = ($index === $populerIndex);
                        $yillik = (float) ($product['price_annually'] ?? 0);
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
                        foreach ($slSpecTypes as $spec) {
                            if (count($specs) >= 4) {
                                break;
                            }
                            foreach ($lines as $li => $line) {
                                if (isset($usedLines[$li]) || !preg_match($spec['match'], $line)) {
                                    continue;
                                }
                                $value = $slSpecValue($line);
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
                                'Ücretsiz kurulum ve yapılandırma',
                                'Sınırsız yeniden düzenleme',
                                'Bütün büyük tarayıcılarda geçerli',
                                '7/24 teknik destek',
                            ];
                        }

                        $clip = count($features) > 6;
                        $listId = 'sl-feat-' . (int) $product['id'];
                        ?>
                        <article class="sl-plan <?= $isPopular ? 'is-popular' : '' ?>">
                            <?php if ($isPopular): ?>
                                <div class="sl-ribbon"><i class="fas fa-fire"></i> En Çok Tercih Edilen</div>
                            <?php endif; ?>

                            <div class="sl-plan-top">
                                <div class="sl-plan-name"><?= htmlspecialchars($product['name']) ?></div>
                                <div class="sl-plan-group">SSL Sertifikası</div>

                                <?php if ($yillik > 0): ?>
                                    <div class="sl-price">
                                        <span class="cur">₺</span>
                                        <span class="val"><?= number_format(floor($yillik), 0, ',', '.') ?></span>
                                        <span class="per">/yıl</span>
                                    </div>
                                    <?php if ($kurulum > 0): ?>
                                        <div class="sl-price-note">
                                            + ₺<?= number_format($kurulum, 0, ',', '.') ?> tek seferlik kurulum
                                        </div>
                                    <?php else: ?>
                                        <div class="sl-price-note">Kurulum ücreti yok</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="sl-price-ask">Fiyat için görüşelim</div>
                                    <div class="sl-price-note">İhtiyacınıza göre teklif hazırlıyoruz</div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($specs)): ?>
                                <div class="sl-specs">
                                    <?php foreach ($specs as $spec): ?>
                                        <div class="sl-spec">
                                            <i class="fas <?= $spec['icon'] ?>"></i>
                                            <div>
                                                <b><?= htmlspecialchars($spec['value']) ?></b>
                                                <span><?= htmlspecialchars($spec['label']) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="sl-plan-body">
                                <ul class="sl-features <?= $clip ? 'is-clipped' : '' ?>" id="<?= $listId ?>">
                                    <?php foreach ($features as $feature): ?>
                                        <li><i class="fas fa-check"></i> <?= htmlspecialchars($feature) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if ($clip): ?>
                                    <button type="button" class="sl-more" data-target="<?= $listId ?>"
                                        aria-expanded="false" aria-controls="<?= $listId ?>">
                                        + <?= count($features) - 6 ?> özellik daha
                                    </button>
                                <?php endif; ?>

                                <a class="sl-cta" href="/client/order-configure.php?id=<?= (int) $product['id'] ?>">
                                    <i class="fas fa-shopping-cart"></i> Sipariş Ver
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Her sertifikada standart -->
    <section class="sl-section is-alt">
        <div class="container">
            <div class="sl-head">
                <span class="sl-badge"><i class="fas fa-check-double"></i> Sertifikaya Dahil</span>
                <h2>Hepsi <span>Her Sertifikada Standart</span></h2>
                <p>Aşağıdakiler için ek ücret ödemezsiniz; en uygun sertifikada da aynen geçerli.</p>
            </div>

            <div class="sl-included">
                <div class="sl-included-grid">
                    <div class="sl-inc">
                        <i class="fas fa-screwdriver-wrench"></i>
                        <div>
                            <b>Ücretsiz Kurulum</b>
                            <span>CSR'den kuruluma kadar biz</span>
                        </div>
                    </div>
                    <div class="sl-inc">
                        <i class="fas fa-lock"></i>
                        <div>
                            <b>256-bit Şifreleme</b>
                            <span>2048-bit RSA anahtar</span>
                        </div>
                    </div>
                    <div class="sl-inc">
                        <i class="fas fa-globe"></i>
                        <div>
                            <b>Tarayıcı Uyumu</b>
                            <span>Bütün büyük tarayıcılar</span>
                        </div>
                    </div>
                    <div class="sl-inc">
                        <i class="fas fa-rotate"></i>
                        <div>
                            <b>Sınırsız Düzenleme</b>
                            <span>Süre boyunca ücretsiz</span>
                        </div>
                    </div>
                    <div class="sl-inc">
                        <i class="fas fa-bell"></i>
                        <div>
                            <b>Yenileme Hatırlatma</b>
                            <span>Süre dolmadan haber veririz</span>
                        </div>
                    </div>
                    <div class="sl-inc">
                        <i class="fas fa-award"></i>
                        <div>
                            <b>Site Güven Mührü</b>
                            <span>Sitenize eklenebilir rozet</span>
                        </div>
                    </div>
                    <div class="sl-inc">
                        <i class="fas fa-chart-line"></i>
                        <div>
                            <b>SEO Katkısı</b>
                            <span>HTTPS bir sıralama sinyali</span>
                        </div>
                    </div>
                    <div class="sl-inc">
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

    <!-- Doğrulama seviyeleri -->
    <section class="sl-section">
        <div class="container">
            <div class="sl-head">
                <span class="sl-badge"><i class="fas fa-user-check"></i> Doğrulama</span>
                <h2>Hangi <span>Seviye</span> Size Uygun</h2>
                <p>Şifreleme gücü üçünde de aynı; fark, sertifikayı alanın ne kadar doğrulandığında.</p>
            </div>

            <div class="sl-spotlight">
                <div class="sl-spotlight-text">
                    <span class="sl-spotlight-tag"><i class="fas fa-user-check"></i> Seçim Rehberi</span>
                    <h3>Şifreleme Aynı, Güven Farklı</h3>
                    <p>
                        Üç seviye de trafiği aynı güçte şifreler. Ayrım, sertifikanın arkasındaki
                        kimliğin ne kadar doğrulandığında: yalnızca alan adı mı, yoksa şirketin
                        kendisi mi kontrol ediliyor.
                    </p>
                    <ul class="sl-spotlight-points">
                        <li><i class="fas fa-check"></i> Blog ve kişisel site: DV</li>
                        <li><i class="fas fa-check"></i> Kurumsal site: OV</li>
                        <li><i class="fas fa-check"></i> E-ticaret ve finans: EV</li>
                        <li><i class="fas fa-check"></i> Çok subdomain: Wildcard</li>
                    </ul>
                </div>

                <div class="sl-bars is-stack">
                    <div class="sl-bar-row">
                        <span class="sl-bar-label">DV &mdash; Alan adı</span>
                        <span class="sl-bar-val">Dakikalar</span>
                    </div>
                    <div class="sl-bar-row">
                        <span class="sl-bar-label">OV &mdash; Kuruluş</span>
                        <span class="sl-bar-val">1-3 iş günü</span>
                    </div>
                    <div class="sl-bar-row">
                        <span class="sl-bar-label">EV &mdash; Genişletilmiş</span>
                        <span class="sl-bar-val">3-5 iş günü</span>
                    </div>
                    <div class="sl-bar-row">
                        <span class="sl-bar-label">Wildcard &mdash; Alt alanlar</span>
                        <span class="sl-bar-val">Dakikalar</span>
                    </div>
                    <p class="sl-bar-note">
                        Süreler doğrulama evraklarının tarafınızdan iletilmesinden sonra başlar.
                    </p>
                </div>
            </div>

            <div class="sl-tech">
                <div class="sl-tech-card" style="--tint: linear-gradient(135deg, #22c55e, #15803d);">
                    <div class="sl-tech-icon"><i class="fas fa-user-check"></i></div>
                    <div>
                        <h4>DV &mdash; Domain Validation</h4>
                        <p>Yalnızca alan adı sahipliği doğrulanır. En hızlı ve en uygun seçenek; blog, tanıtım sitesi ve iç projeler için yeterli.</p>
                    </div>
                </div>
                <div class="sl-tech-card" style="--tint: linear-gradient(135deg, #0ea5e9, #0284c7);">
                    <div class="sl-tech-icon"><i class="fas fa-building"></i></div>
                    <div>
                        <h4>OV &mdash; Organization Validation</h4>
                        <p>Şirketin ticari varlığı da doğrulanır ve sertifika detayında şirket adı görünür. Kurumsal siteler için önerilir.</p>
                    </div>
                </div>
                <div class="sl-tech-card" style="--tint: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                    <div class="sl-tech-icon"><i class="fas fa-certificate"></i></div>
                    <div>
                        <h4>EV &mdash; Extended Validation</h4>
                        <p>En kapsamlı doğrulama. Ödeme alan ve kişisel veri toplayan siteler için en yüksek güven seviyesini sunar.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Sık sorulanlar -->
    <section class="sl-section is-alt">
        <div class="container">
            <div class="sl-faq-layout">
                <div class="sl-faq-aside">
                    <div class="sl-head is-left">
                        <span class="sl-badge"><i class="fas fa-circle-question"></i> Sık Sorulanlar</span>
                        <h2>Aklınıza <span>Takılanlar</span></h2>
                        <p>Satın almadan önce en çok merak edilenleri derledik.</p>
                    </div>

                    <a href="iletisim.php" class="sl-faq-help">
                        <i class="fas fa-headset"></i>
                        <div>
                            <b>Sorunuz listede yok mu?</b>
                            <span>Destek ekibimize yazın, aynı gün dönelim.</span>
                        </div>
                    </a>
                </div>

                <div class="sl-faq-list">
                    <details class="sl-faq-item">
                        <summary>
                            <span class="sl-faq-icon"><i class="fas fa-screwdriver-wrench"></i></span>
                            <span class="sl-faq-q">Kurulumu ben mi yapacağım?</span>
                            <span class="sl-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="sl-faq-body">
                            Hayır. CSR oluşturmadan sunucuya kuruluma kadar bütün adımları biz yapıyoruz.
                            Hosting hizmetiniz bizde değilse de kurulum desteği veriyoruz.
                        </div>
                    </details>

                    <details class="sl-faq-item">
                        <summary>
                            <span class="sl-faq-icon"><i class="fas fa-gift"></i></span>
                            <span class="sl-faq-q">Hosting paketimdeki ücretsiz SSL yetmez mi?</span>
                            <span class="sl-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="sl-faq-body">
                            Çoğu site için yeter. Ücretli sertifikaları ayıran şey şifreleme gücü değil;
                            kuruluş doğrulaması, maddi garanti ve site güven mührü gibi kurumsal ihtiyaçlar.
                        </div>
                    </details>

                    <details class="sl-faq-item">
                        <summary>
                            <span class="sl-faq-icon"><i class="fas fa-asterisk"></i></span>
                            <span class="sl-faq-q">Wildcard sertifika neyi kapsıyor?</span>
                            <span class="sl-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="sl-faq-body">
                            Tek bir sertifikayla alan adınızın bütün birinci seviye alt alan adlarını kapsar:
                            blog, shop, panel gibi. Her subdomain için ayrı sertifika almanız gerekmez.
                        </div>
                    </details>

                    <details class="sl-faq-item">
                        <summary>
                            <span class="sl-faq-icon"><i class="fas fa-clock"></i></span>
                            <span class="sl-faq-q">Sertifikam ne kadar sürede aktif olur?</span>
                            <span class="sl-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="sl-faq-body">
                            DV ve Wildcard sertifikalar dakikalar içinde. OV 1-3, EV 3-5 iş günü sürer;
                            bu süre doğrulama evraklarını ilettikten sonra başlar.
                        </div>
                    </details>

                    <details class="sl-faq-item">
                        <summary>
                            <span class="sl-faq-icon"><i class="fas fa-rotate"></i></span>
                            <span class="sl-faq-q">Alan adımı sonradan değiştirebilir miyim?</span>
                            <span class="sl-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="sl-faq-body">
                            Evet. Sertifika süresi boyunca sınırsız yeniden düzenleme hakkınız var;
                            alan adını değiştirip sertifikayı yeniden düzenletebilirsiniz.
                        </div>
                    </details>

                    <details class="sl-faq-item">
                        <summary>
                            <span class="sl-faq-icon"><i class="fas fa-bell"></i></span>
                            <span class="sl-faq-q">Süresi dolunca ne oluyor?</span>
                            <span class="sl-faq-sign"><i class="fas fa-plus"></i></span>
                        </summary>
                        <div class="sl-faq-body">
                            Süre dolmadan önce size hatırlatma gönderiyoruz. Yenilediğinizde yeni sertifikayı
                            biz kuruyoruz; sitenizde kesinti yaşanmıyor.
                        </div>
                    </details>
                </div>
            </div>
        </div>
    </section>

    <!-- Kapanış -->
    <section class="sl-final">
        <div class="container">
            <h3><i class="fas fa-lock"></i> Sitenizi Güvence Altına Alın</h3>
            <p>Hangi sertifikanın size uygun olduğundan emin değilseniz sorun, birlikte seçelim</p>
            <div class="sl-final-actions">
                <a href="#paketler" class="sl-final-btn">
                    <i class="fas fa-shield-halved"></i> Sertifikaları İncele
                </a>
                <a href="iletisim.php" class="sl-final-link">
                    <i class="fas fa-comments"></i> Önce Soru Sormak İsterim
                </a>
            </div>
        </div>
    </section>
</div>

<script>
    // "+N özellik daha" düğmesi: listedeki gizli satırları açar
    document.querySelectorAll('.sl-more').forEach(function (btn) {
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
