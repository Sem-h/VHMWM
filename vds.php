<?php
/**
 * WHMVM - VDS Sunucu Sayfası
 * Yapılandırıcı fiyatları vds_pricing tablosundan gelir; cart.php aynı tabloyu okur.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'VDS Sunucu Kiralama';
$pageDescription = 'Kurumsal projeleriniz için ölçeklenebilir VDS sunucu altyapısı. Tam root erişimi, NVMe disk, %99.9 uptime SLA ve 7/24 teknik destek.';

$vdsDefaults = [
    'base' => ['price' => 150, 'min' => 1, 'max' => 1, 'step' => 1, 'unit' => ''],
    'cpu' => ['price' => 25, 'min' => 1, 'max' => 16, 'step' => 1, 'unit' => 'Core'],
    'ram' => ['price' => 25, 'min' => 1, 'max' => 64, 'step' => 1, 'unit' => 'GB'],
    'disk' => ['price' => 3, 'min' => 25, 'max' => 1000, 'step' => 25, 'unit' => 'GB'],
    'bandwidth' => ['price' => 0, 'min' => 1, 'max' => 1, 'step' => 1, 'unit' => 'Gbps'],
    'ip' => ['price' => 25, 'min' => 1, 'max' => 5, 'step' => 1, 'unit' => 'Adet'],
];

$vdsPricing = $vdsDefaults;
try {
    foreach (Database::fetchAll("SELECT * FROM vds_pricing WHERE is_active = 1 ORDER BY sort_order") as $p) {
        $vdsPricing[$p['resource_type']] = [
            'price' => (float) $p['unit_price'],
            'min' => (int) $p['min_value'],
            'max' => (int) $p['max_value'],
            'step' => (int) $p['step_value'],
            'unit' => $p['unit_label'],
        ];
    }
} catch (Exception $e) {
    // Varsayılanlarla devam
}
foreach ($vdsDefaults as $key => $def) {
    $vdsPricing[$key] = array_merge($def, $vdsPricing[$key] ?? []);
}

// Fatura dönemi indirimleri - cart.php ile aynı olmalı
$vdsBilling = [
    1 => ['label' => '1 Ay', 'note' => 'Standart', 'discount' => 0],
    3 => ['label' => '3 Ay', 'note' => '%5 indirim', 'discount' => 0.05],
    6 => ['label' => '6 Ay', 'note' => '%10 indirim', 'discount' => 0.10],
    12 => ['label' => '12 Ay', 'note' => '%20 indirim', 'discount' => 0.20],
];

$vdsPresets = [
    ['id' => 'baslangic', 'name' => 'Başlangıç', 'desc' => 'Kurumsal web sitesi', 'cpu' => 2, 'ram' => 4, 'disk' => 50, 'ip' => 1],
    ['id' => 'gelistirici', 'name' => 'Standart', 'desc' => 'Uygulama ve test ortamı', 'cpu' => 4, 'ram' => 8, 'disk' => 100, 'ip' => 1],
    ['id' => 'kurumsal', 'name' => 'Kurumsal', 'desc' => 'Yoğun trafik ve veritabanı', 'cpu' => 8, 'ram' => 16, 'disk' => 250, 'ip' => 2],
];

$vdsRows = [
    ['key' => 'cpu', 'label' => 'İşlemci', 'sub' => 'Paylaşılmayan vCPU çekirdeği', 'default' => 4],
    ['key' => 'ram', 'label' => 'Bellek', 'sub' => 'DDR4 ECC', 'default' => 8],
    ['key' => 'disk', 'label' => 'Disk', 'sub' => 'NVMe SSD, RAID korumalı', 'default' => 100],
    ['key' => 'ip', 'label' => 'IP adresi', 'sub' => 'İlk IPv4 adresi ücretsizdir', 'default' => 1],
];

// Teknik özellikler - üç başlık altında toplanır
$vdsSpecGroups = [
    [
        'title' => 'Donanım',
        'icon' => 'fa-microchip',
        'items' => [
            ['Sanallaştırma', 'KVM tam sanallaştırma'],
            ['İşlemci', 'Intel Xeon, paylaşılmayan vCPU çekirdeği'],
            ['Bellek', 'DDR4 ECC, garantili tahsis'],
            ['Disk', 'NVMe SSD, donanımsal RAID koruması'],
        ],
    ],
    [
        'title' => 'Ağ ve Erişim',
        'icon' => 'fa-network-wired',
        'items' => [
            ['Bant genişliği', '1 Gbps port, sınırsız trafik'],
            ['IP adresi', 'IPv4 (ek adres talep edilebilir)'],
            ['Erişim', 'SSH üzerinden tam root yetkisi'],
            ['Güvenlik', 'Ağ seviyesinde DDoS filtreleme'],
        ],
    ],
    [
        'title' => 'Yönetim',
        'icon' => 'fa-sliders',
        'items' => [
            ['İşletim sistemi', 'Linux dağıtımları ve Windows Server'],
            ['Panel', 'Yeniden başlatma, yeniden kurulum, konsol'],
            ['Yedekleme', 'Talebe bağlı yedekleme seçenekleri'],
            ['Kurulum', 'Ödeme onayından sonra dakikalar içinde'],
        ],
    ],
];

/** Bir ürün grubundaki en düşük aylık fiyat; karşılaştırma tablosunda kullanılır */
$vdsCheapest = static function (string $slug): ?float {
    try {
        $row = Database::fetch(
            "SELECT MIN(p.price_monthly) AS fiyat
             FROM products p
             JOIN product_groups g ON g.id = p.group_id
             WHERE g.slug = ? AND p.is_active = 1 AND p.price_monthly > 0",
            [$slug]
        );
        return !empty($row['fiyat']) ? (float) $row['fiyat'] : null;
    } catch (Exception $e) {
        return null;
    }
};

// VDS'in en ucuz hâli: en küçük hazır yapılandırma
$vdsEnUcuz = $vdsPricing['base']['price']
    + $vdsPresets[0]['cpu'] * $vdsPricing['cpu']['price']
    + $vdsPresets[0]['ram'] * $vdsPricing['ram']['price']
    + $vdsPresets[0]['disk'] * $vdsPricing['disk']['price'];

$vdsCompareCols = [
    [
        'name' => 'Web Hosting',
        'sub' => 'Paylaşımlı kaynak',
        'price' => $vdsCheapest('linux-hosting'),
        'url' => 'store.php?group=linux-hosting',
        'cta' => 'Paketleri gör',
        'focus' => false,
    ],
    [
        'name' => 'VDS Sunucu',
        'sub' => 'Size ayrılmış kaynak',
        'price' => $vdsEnUcuz,
        'url' => '#yapilandir',
        'cta' => 'Yapılandır',
        'focus' => true,
    ],
    [
        'name' => 'Fiziksel Sunucu',
        'sub' => 'Özel donanım',
        'price' => $vdsCheapest('fiziksel-sunucu'),
        'url' => 'store.php?group=fiziksel-sunucu',
        'cta' => 'Paketleri gör',
        'focus' => false,
    ],
];

// Karşılaştırma satırları: [ölçüt, hosting, vds, fiziksel]
$vdsCompareRows = [
    ['Kaynaklar', 'Diğer sitelerle paylaşılır', 'Size ayrılmış, garantili', 'Tamamen size ait donanım'],
    ['Root erişimi', 'Yok', 'Tam root', 'Tam root'],
    ['Yazılım kurulumu', 'Panelin izin verdiği kadar', 'Sınırsız', 'Sınırsız'],
    ['Ölçeklenebilirlik', 'Paket değişikliği gerekir', 'Kaynak ekleyip çıkarma', 'Donanım değişimi gerekir'],
    ['Kurulum süresi', 'Anında', 'Dakikalar içinde', 'Tedarik süresine bağlı'],
    ['Yönetim sorumluluğu', 'Tamamen bizde', 'Sistem yönetimi sizde', 'Sistem yönetimi sizde'],
    ['Kullanım alanı', 'Kurumsal site, blog', 'Uygulama, e-ticaret, veritabanı', 'Yüksek ve sabit yük'],
];

require_once __DIR__ . '/theme/includes/header.php';
?>

<style>
    /* ==========================================
       VDS - kurumsal düzen
       Sade yüzeyler, net kenarlıklar, ölçülü renk.
       Gradyan ve animasyon yok; hiyerarşi tipografi
       ve boşlukla kuruluyor.
       ========================================== */
    .vc {
        --vc-line: var(--border-color);
        --vc-surface: var(--bg-primary);
        --vc-accent: var(--primary);
        --vc-radius: 10px;
    }

    .vc a {
        color: inherit;
    }

    /* ===== Üst bilgi ===== */
    .vc-hero {
        padding: var(--space-6) 0;
        border-bottom: 1px solid var(--vc-line);
    }

    .vc-hero-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.85fr);
        gap: var(--space-7);
        align-items: start;
    }

    .vc-kicker {
        display: block;
        font-size: var(--text-xs);
        font-weight: 700;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--vc-accent);
        margin-bottom: var(--space-3);
    }

    .vc-hero h1 {
        font-size: clamp(24px, 1.5vw + 16px, 32px);
        font-weight: 700;
        line-height: 1.25;
        letter-spacing: -0.02em;
        color: var(--text-primary);
        margin-bottom: var(--space-3);
        max-width: 26ch;
    }

    .vc-lead {
        font-size: var(--text-base);
        line-height: 1.65;
        color: var(--text-muted);
        max-width: 60ch;
        margin-bottom: var(--space-4);
    }

    .vc-actions {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-3);
        margin-bottom: var(--space-4);
    }

    .vc-btn {
        padding: 11px 22px;
    }

    .vc-btn {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        padding: 13px 26px;
        border-radius: var(--vc-radius);
        border: 1px solid transparent;
        font-family: inherit;
        font-size: var(--text-base);
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }

    .vc-btn-primary {
        background: var(--vc-accent);
        color: #fff;
    }

    .vc-btn-primary:hover {
        background: var(--primary-dark);
    }

    .vc-btn-outline {
        background: transparent;
        border-color: var(--vc-line);
        color: var(--text-primary);
    }

    .vc-btn-outline:hover {
        border-color: var(--vc-accent);
        color: var(--vc-accent);
    }

    /* Madde listesi - tek satırda akan kısa maddeler */
    .vc-points {
        display: flex;
        flex-wrap: wrap;
        gap: 8px var(--space-4);
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .vc-points li {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-size: var(--text-sm);
        color: var(--text-secondary);
        white-space: nowrap;
    }

    .vc-points i {
        font-size: 11px;
        color: var(--vc-accent);
        flex-shrink: 0;
    }

    /* Örnek yapılandırma kutusu */
    .vc-quote {
        border: 1px solid var(--vc-line);
        border-radius: var(--vc-radius);
        background: var(--vc-surface);
    }

    .vc-quote-head {
        padding: var(--space-3) var(--space-4);
        border-bottom: 1px solid var(--vc-line);
        font-size: var(--text-sm);
        font-weight: 600;
        color: var(--text-primary);
    }

    .vc-quote-body {
        padding: var(--space-3) var(--space-4);
    }

    .vc-spec-row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: var(--space-4);
        padding: 6px 0;
        font-size: var(--text-sm);
        border-bottom: 1px solid var(--border-light);
    }

    .vc-spec-row:last-child {
        border-bottom: none;
    }

    .vc-spec-row dt {
        color: var(--text-muted);
    }

    .vc-spec-row dd {
        margin: 0;
        font-weight: 600;
        color: var(--text-primary);
        font-variant-numeric: tabular-nums;
    }

    .vc-quote-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: var(--space-3);
        padding: var(--space-3) var(--space-4);
        border-top: 1px solid var(--vc-line);
    }

    .vc-quote-price {
        display: flex;
        align-items: baseline;
        gap: 4px;
        font-variant-numeric: tabular-nums;
    }

    .vc-quote-price b {
        font-size: 26px;
        font-weight: 700;
        letter-spacing: -0.03em;
        color: var(--text-primary);
    }

    .vc-quote-price span {
        font-size: var(--text-sm);
        color: var(--text-muted);
    }

    /* ===== Güven şeridi ===== */
    .vc-strip {
        border-bottom: 1px solid var(--vc-line);
        background: var(--vc-surface);
    }

    .vc-strip-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    /* Sayfadaki destek kutusuyla aynı biçim: ölçülü ikon + iki satır metin */
    .vc-metric {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-4) var(--space-5);
        border-right: 1px solid var(--vc-line);
    }

    .vc-metric:last-child {
        border-right: none;
    }

    .vc-metric i {
        width: 34px;
        height: 34px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        border: 1px solid var(--vc-line);
        color: var(--vc-accent);
        font-size: 13px;
    }

    .vc-metric b {
        display: block;
        font-size: var(--text-sm);
        font-weight: 600;
        color: var(--text-primary);
        line-height: 1.35;
    }

    .vc-metric span {
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    /* ===== Bölümler ===== */
    .vc-section {
        padding: var(--space-7) 0;
    }

    .vc-section.is-alt {
        background: var(--vc-surface);
        border-top: 1px solid var(--vc-line);
        border-bottom: 1px solid var(--vc-line);
    }

    .vc-head {
        max-width: 640px;
        margin-bottom: var(--space-4);
    }

    .vc-head h2 {
        font-size: clamp(20px, 1vw + 15px, 25px);
        font-weight: 700;
        letter-spacing: -0.02em;
        line-height: 1.3;
        color: var(--text-primary);
    }

    .vc-head p {
        margin-top: 6px;
        font-size: var(--text-sm);
        color: var(--text-muted);
        line-height: 1.6;
    }

    /* ===== Yapılandırıcı ===== */
    .vc-panel {
        border: 1px solid var(--vc-line);
        border-radius: var(--vc-radius);
        background: var(--vc-surface);
    }

    .vc-build {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 360px);
    }

    .vc-build-main {
        border-right: 1px solid var(--vc-line);
    }

    .vc-block {
        padding: var(--space-4) var(--space-5);
        border-bottom: 1px solid var(--vc-line);
    }

    .vc-block:last-child {
        border-bottom: none;
    }

    .vc-block-title {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: var(--space-3);
    }

    /* Hazır yapılandırmalar */
    .vc-presets {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: var(--space-3);
    }

    .vc-preset {
        padding: 11px 14px;
        border: 1px solid var(--vc-line);
        border-radius: var(--vc-radius);
        background: transparent;
        text-align: left;
        font-family: inherit;
        cursor: pointer;
        transition: border-color 0.15s ease, background-color 0.15s ease;
    }

    .vc-preset:hover {
        border-color: var(--vc-accent);
    }

    .vc-preset.is-active {
        border-color: var(--vc-accent);
        background: color-mix(in srgb, var(--primary) 8%, transparent);
    }

    .vc-preset b {
        display: block;
        font-size: var(--text-sm);
        font-weight: 600;
        color: var(--text-primary);
    }

    .vc-preset em {
        display: block;
        font-style: normal;
        font-size: 11px;
        font-weight: 500;
        color: var(--text-muted);
        font-variant-numeric: tabular-nums;
    }

    .vc-preset.is-active em {
        color: var(--vc-accent);
    }

    /* Kaynak satırları */
    /* Etiket, açıklama, değer ve tutar tek satırda; satır yüksekliği yarıya iner */
    .vc-row {
        padding: 14px 0;
        border-bottom: 1px solid var(--border-light);
    }

    .vc-row:first-of-type {
        padding-top: 0;
    }

    .vc-row:last-of-type {
        border-bottom: none;
        padding-bottom: 0;
    }

    .vc-row-head {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: var(--space-4);
        margin-bottom: 10px;
    }

    .vc-row-label b {
        font-size: var(--text-sm);
        font-weight: 600;
        color: var(--text-primary);
    }

    .vc-row-label span {
        margin-left: 8px;
        font-size: var(--text-xs);
        color: var(--text-gray);
    }

    .vc-row-out {
        text-align: right;
        white-space: nowrap;
    }

    .vc-row-out b {
        font-size: var(--text-md);
        font-weight: 700;
        color: var(--text-primary);
        font-variant-numeric: tabular-nums;
    }

    .vc-row-out span {
        margin-left: 8px;
        font-size: var(--text-xs);
        color: var(--text-muted);
        font-variant-numeric: tabular-nums;
    }

    .vc-scale {
        margin-top: 6px;
    }

    /* Sürgü */
    .vc-range {
        -webkit-appearance: none;
        appearance: none;
        width: 100%;
        height: 6px;
        border-radius: 3px;
        outline: none;
        cursor: pointer;
        background: linear-gradient(to right,
                var(--vc-accent) var(--fill, 0%),
                var(--surface-3) var(--fill, 0%));
    }

    .vc-range::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: var(--vc-surface);
        border: 2px solid var(--vc-accent);
        cursor: pointer;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }

    .vc-range::-moz-range-thumb {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: var(--vc-surface);
        border: 2px solid var(--vc-accent);
        cursor: pointer;
    }

    .vc-range:focus-visible {
        box-shadow: var(--focus-ring);
    }

    .vc-scale {
        display: flex;
        justify-content: space-between;
        margin-top: 8px;
        font-size: var(--text-xs);
        color: var(--text-gray);
        font-variant-numeric: tabular-nums;
    }

    .vc-included-note {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: var(--space-4);
        font-size: var(--text-sm);
    }

    .vc-included-note b {
        color: var(--text-primary);
        font-weight: 600;
    }

    .vc-included-note span {
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    .vc-included-note em {
        font-style: normal;
        font-size: var(--text-sm);
        font-weight: 600;
        color: var(--vc-accent);
    }

    /* ===== Özet ===== */
    .vc-side-inner {
        position: sticky;
        top: 100px;
    }

    .vc-line {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: var(--space-3);
        padding: 8px 0;
        font-size: var(--text-sm);
        border-bottom: 1px solid var(--border-light);
    }

    .vc-line:last-child {
        border-bottom: none;
    }

    .vc-line dt {
        color: var(--text-muted);
    }

    .vc-line dd {
        margin: 0;
        font-weight: 600;
        color: var(--text-primary);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .vc-periods {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--space-2);
    }

    .vc-period {
        padding: 10px 8px;
        border: 1px solid var(--vc-line);
        border-radius: var(--vc-radius);
        background: transparent;
        font-family: inherit;
        cursor: pointer;
        text-align: center;
        transition: border-color 0.15s ease, background-color 0.15s ease;
    }

    .vc-period:hover {
        border-color: var(--vc-accent);
    }

    .vc-period.is-active {
        border-color: var(--vc-accent);
        background: color-mix(in srgb, var(--primary) 8%, transparent);
    }

    .vc-period b {
        display: block;
        font-size: var(--text-sm);
        font-weight: 600;
        color: var(--text-primary);
        line-height: 1.3;
    }

    .vc-period span {
        font-size: 11px;
        color: var(--text-muted);
    }

    .vc-period.is-active span {
        color: var(--vc-accent);
    }

    .vc-total-row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: var(--space-3);
        margin-bottom: var(--space-2);
    }

    .vc-total-row span {
        font-size: var(--text-sm);
        color: var(--text-muted);
    }

    .vc-amount {
        display: flex;
        align-items: baseline;
        gap: 4px;
        font-variant-numeric: tabular-nums;
    }

    .vc-amount b {
        font-size: 38px;
        font-weight: 700;
        letter-spacing: -0.03em;
        line-height: 1;
        color: var(--text-primary);
    }

    /* Yalnızca doğrudan çocuk; tutar <b> içindeki span'i küçültmesin */
    .vc-amount>span {
        font-size: var(--text-sm);
        color: var(--text-muted);
    }

    .vc-saving {
        display: none;
        margin-top: var(--space-3);
        font-size: var(--text-xs);
        color: var(--vc-accent);
        font-weight: 600;
    }

    .vc-saving.is-on {
        display: block;
    }

    .vc-order {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        width: 100%;
        margin-top: var(--space-4);
        padding: 14px 24px;
        border: none;
        border-radius: var(--vc-radius);
        background: var(--vc-accent);
        color: #fff;
        font-family: inherit;
        font-size: var(--text-base);
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.15s ease;
    }

    .vc-order:hover {
        background: var(--primary-dark);
    }

    .vc-note {
        margin-top: var(--space-3);
        text-align: center;
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    /* ===== Teknik özellikler =====
       10 satırlık tek sütun yerine üç başlık yan yana;
       hem yarı yükseklik hem anlamlı gruplama. */
    .vc-specs {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        border: 1px solid var(--vc-line);
        border-radius: var(--vc-radius);
        background: var(--vc-surface);
        overflow: hidden;
    }

    .vc-spec-col {
        border-right: 1px solid var(--vc-line);
    }

    .vc-spec-col:last-child {
        border-right: none;
    }

    .vc-spec-col-head {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: var(--space-3) var(--space-5);
        border-bottom: 1px solid var(--vc-line);
        background: color-mix(in srgb, var(--primary) 5%, transparent);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-primary);
    }

    .vc-spec-col-head i {
        font-size: 13px;
        color: var(--vc-accent);
    }

    .vc-spec-list {
        margin: 0;
        padding: var(--space-2) var(--space-5) var(--space-4);
    }

    .vc-spec-item {
        padding: var(--space-3) 0;
        border-bottom: 1px solid var(--border-light);
    }

    .vc-spec-item:last-child {
        border-bottom: none;
    }

    .vc-spec-item dt {
        font-size: var(--text-sm);
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 2px;
    }

    .vc-spec-item dd {
        margin: 0;
        font-size: var(--text-sm);
        color: var(--text-muted);
        line-height: 1.5;
    }

    /* ===== Tablolar ===== */
    .vc-table-wrap {
        border: 1px solid var(--vc-line);
        border-radius: var(--vc-radius);
        overflow-x: auto;
        background: var(--vc-surface);
    }

    .vc-table {
        width: 100%;
        border-collapse: collapse;
    }

    .vc-table th,
    .vc-table td {
        padding: var(--space-4) var(--space-5);
        text-align: left;
        font-size: var(--text-sm);
        border-bottom: 1px solid var(--border-light);
        vertical-align: top;
    }

    .vc-table tr:last-child th,
    .vc-table tr:last-child td {
        border-bottom: none;
    }

    .vc-table tbody th {
        width: 30%;
        font-weight: 600;
        color: var(--text-primary);
    }

    .vc-table td {
        color: var(--text-muted);
    }

    /* ===== Karşılaştırma =====
       VDS sütunu tablonun içinde dikey bir kart gibi durur;
       altta başlangıç fiyatı ve eylem satırı ile karar tablosuna döner. */
    .vc-cmp-wrap {
        overflow-x: auto;
    }

    .vc-cmp {
        width: 100%;
        min-width: 720px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .vc-cmp th,
    .vc-cmp td {
        padding: 11px var(--space-5);
        text-align: left;
        font-size: var(--text-sm);
        vertical-align: middle;
    }

    /* Ölçüt sütunu */
    .vc-cmp tbody th,
    .vc-cmp tfoot th {
        width: 22%;
        font-weight: 500;
        color: var(--text-muted);
        border-bottom: 1px solid var(--border-light);
    }

    .vc-cmp tbody td {
        width: 26%;
        color: var(--text-muted);
        border-bottom: 1px solid var(--border-light);
    }

    /* Başlık hücreleri */
    .vc-cmp thead th {
        padding: var(--space-4) var(--space-5);
        border-bottom: 1px solid var(--vc-line);
        vertical-align: bottom;
    }

    .vc-cmp thead th b {
        display: block;
        font-size: var(--text-md);
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.3;
    }

    .vc-cmp thead th span {
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    .vc-cmp-corner {
        border-bottom: 1px solid var(--vc-line);
    }

    .vc-cmp-tag {
        display: inline-block;
        margin-bottom: 6px;
        padding: 3px 9px;
        border-radius: 4px;
        background: var(--vc-accent);
        color: #fff !important;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    /* Vurgulanan sütun - dikey kart görünümü */
    .vc-cmp .is-focus {
        background: color-mix(in srgb, var(--primary) 7%, transparent);
        border-left: 1px solid var(--vc-accent);
        border-right: 1px solid var(--vc-accent);
        color: var(--text-primary);
        font-weight: 600;
    }

    .vc-cmp thead .is-focus {
        border-top: 1px solid var(--vc-accent);
        border-bottom-color: var(--vc-accent);
        border-radius: var(--vc-radius) var(--vc-radius) 0 0;
    }

    .vc-cmp tfoot tr:last-child .is-focus {
        border-bottom: 1px solid var(--vc-accent);
        border-radius: 0 0 var(--vc-radius) var(--vc-radius);
    }

    /* Fiyat ve eylem satırları */
    .vc-cmp-price th,
    .vc-cmp-price td {
        padding-top: var(--space-4);
        border-top: 1px solid var(--vc-line);
        border-bottom: none !important;
    }

    .vc-cmp-price b {
        font-size: 22px;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--text-primary);
        font-variant-numeric: tabular-nums;
    }

    .vc-cmp-price span {
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    .vc-cmp-cta th,
    .vc-cmp-cta td {
        padding-top: var(--space-3);
        padding-bottom: var(--space-5);
        border-bottom: none !important;
    }

    .vc-cmp-cta .vc-btn {
        width: 100%;
        justify-content: center;
        padding: 10px 16px;
        font-size: var(--text-sm);
    }

    /* ===== Destek =====
       Tek genel buton yerine gerçek iletişim kanalları. */
    .vc-help {
        border: 1px solid var(--vc-line);
        border-radius: var(--vc-radius);
        background: var(--vc-surface);
        overflow: hidden;
    }

    /* Başlık üstte tam genişlikte; kanallar altta eşit sütunlarda
       böylece üç kanal aynı hizadan başlar */
    /* Başlık şeridi, teknik özellikler tablosundaki grup başlığıyla aynı biçimde */
    .vc-help-top {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: var(--space-3) var(--space-5);
        padding: var(--space-3) var(--space-5);
        border-bottom: 1px solid var(--vc-line);
        background: color-mix(in srgb, var(--primary) 5%, transparent);
    }

    .vc-help-top h3 {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-primary);
        margin-bottom: 3px;
    }

    .vc-help-top p {
        font-size: var(--text-sm);
        color: var(--text-muted);
        line-height: 1.55;
    }

    .vc-help-more {
        flex-shrink: 0;
        font-size: var(--text-sm);
        font-weight: 600;
        color: var(--vc-accent);
        text-decoration: underline;
        text-underline-offset: 3px;
        text-decoration-thickness: 1px;
    }

    .vc-help-channels {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .vc-help-ch {
        display: block;
        padding: var(--space-4) var(--space-5);
        border-right: 1px solid var(--vc-line);
        transition: background-color 0.15s ease;
    }

    .vc-help-ch:last-child {
        border-right: none;
    }

    .vc-help-ch:hover {
        background: color-mix(in srgb, var(--primary) 6%, transparent);
    }

    .vc-help-kind {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 7px;
        font-size: var(--text-xs);
        font-weight: 500;
        color: var(--text-muted);
    }

    /* İkon vurgusuz: kurumsal blokta renk, bilgiden dikkat çalmasın */
    .vc-help-kind i {
        font-size: 11px;
        color: var(--text-gray);
    }

    .vc-help-ch b {
        display: block;
        font-size: var(--text-base);
        font-weight: 600;
        color: var(--text-primary);
        line-height: 1.35;
        margin-bottom: 2px;
        /* Uzun e-posta adresi taşmasın */
        overflow-wrap: anywhere;
    }

    .vc-help-when {
        display: block;
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    /* ===== Duyarlılık ===== */
    @media (max-width: 1100px) {
        .vc-build {
            grid-template-columns: 1fr;
        }

        .vc-build-main {
            border-right: none;
            border-bottom: 1px solid var(--vc-line);
        }

        .vc-side-inner {
            position: static;
        }
    }

    @media (max-width: 992px) {
        .vc-hero-grid {
            grid-template-columns: 1fr;
            gap: var(--space-6);
        }

        .vc-lead {
            max-width: 100%;
        }

        .vc-strip-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .vc-specs {
            grid-template-columns: 1fr;
        }

        .vc-spec-col {
            border-right: none;
            border-bottom: 1px solid var(--vc-line);
        }

        .vc-spec-col:last-child {
            border-bottom: none;
        }

        .vc-metric:nth-child(2) {
            border-right: none;
        }

        .vc-metric:nth-child(1),
        .vc-metric:nth-child(2) {
            border-bottom: 1px solid var(--vc-line);
        }
    }

    @media (max-width: 640px) {
        .vc-presets {
            grid-template-columns: 1fr;
        }

        .vc-points {
            grid-template-columns: 1fr;
        }

        .vc-strip-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .vc-metric {
            border-bottom: 1px solid var(--vc-line);
        }

        .vc-metric:nth-child(2n) {
            border-right: none;
        }

        .vc-metric:nth-child(3),
        .vc-metric:nth-child(4) {
            border-bottom: none;
        }

        .vc-help-channels {
            grid-template-columns: 1fr;
        }

        .vc-help-ch {
            border-right: none;
            border-bottom: 1px solid var(--vc-line);
        }

        .vc-help-ch:last-child {
            border-bottom: none;
        }
    }
</style>

<div class="vc">

    <!-- Üst bilgi -->
    <section class="vc-hero">
        <div class="container">
            <div class="vc-hero-grid">
                <div>
                    <span class="vc-kicker">VDS Sunucu</span>
                    <h1>Ölçeklenebilir kurumsal sunucu altyapısı</h1>
                    <p class="vc-lead">
                        İşlemci, bellek ve disk kaynaklarını ihtiyacınıza göre belirleyin. Kaynaklar
                        yalnızca size tahsis edilir; projeniz büyüdükçe kapasiteyi artırabilirsiniz.
                    </p>

                    <div class="vc-actions">
                        <a href="#yapilandir" class="vc-btn vc-btn-primary">
                            Sunucu Yapılandır
                        </a>
                        <a href="contact.php" class="vc-btn vc-btn-outline">
                            Satış Ekibiyle Görüşün
                        </a>
                    </div>

                    <ul class="vc-points">
                        <li><i class="fas fa-check"></i> Tam root erişimi</li>
                        <li><i class="fas fa-check"></i> KVM sanallaştırma</li>
                        <li><i class="fas fa-check"></i> Kaynak garantisi</li>
                        <li><i class="fas fa-check"></i> Dakikalar içinde kurulum</li>
                    </ul>
                </div>

                <div class="vc-quote">
                    <div class="vc-quote-head">Örnek yapılandırma</div>
                    <div class="vc-quote-body">
                        <dl style="margin:0">
                            <div class="vc-spec-row">
                                <dt>İşlemci</dt>
                                <dd>4 vCPU</dd>
                            </div>
                            <div class="vc-spec-row">
                                <dt>Bellek</dt>
                                <dd>8 GB</dd>
                            </div>
                            <div class="vc-spec-row">
                                <dt>Disk</dt>
                                <dd>100 GB NVMe</dd>
                            </div>
                            <div class="vc-spec-row">
                                <dt>Bant genişliği</dt>
                                <dd>1 Gbps · sınırsız</dd>
                            </div>
                            <div class="vc-spec-row">
                                <dt>IP adresi</dt>
                                <dd>1 IPv4</dd>
                            </div>
                        </dl>
                    </div>
                    <div class="vc-quote-foot">
                        <div class="vc-quote-price">
                            <b>₺750</b><span>/ay + KDV</span>
                        </div>
                        <a href="#yapilandir" class="vc-btn vc-btn-outline">Düzenle</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Ölçülebilir değerler şeridi -->
    <div class="vc-strip">
        <div class="container">
            <div class="vc-strip-grid">
                <div class="vc-metric">
                    <i class="fas fa-chart-line"></i>
                    <div>
                        <b>%99.9 uptime SLA</b>
                        <span>Sözleşme ile garanti altında</span>
                    </div>
                </div>
                <div class="vc-metric">
                    <i class="fas fa-headset"></i>
                    <div>
                        <b>7/24 teknik destek</b>
                        <span>Telefon, e-posta ve panel</span>
                    </div>
                </div>
                <div class="vc-metric">
                    <i class="fas fa-ethernet"></i>
                    <div>
                        <b>1 Gbps port</b>
                        <span>Sınırsız trafik, hız kısıtı yok</span>
                    </div>
                </div>
                <div class="vc-metric">
                    <i class="fas fa-hard-drive"></i>
                    <div>
                        <b>NVMe SSD</b>
                        <span>Donanımsal RAID koruması</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Yapılandırıcı -->
    <section class="vc-section" id="yapilandir">
        <div class="container">
            <div class="vc-head">
                <h2>Sunucunuzu yapılandırın</h2>
                <p>Hazır bir yapılandırmayla başlayın veya kaynakları tek tek belirleyin. Aylık tutar seçiminize göre
                    hesaplanır.</p>
            </div>

            <div class="vc-panel">
                <div class="vc-build">
                    <div class="vc-build-main">

                        <div class="vc-block">
                            <div class="vc-block-title">Hazır yapılandırmalar</div>
                            <div class="vc-presets">
                                <?php foreach ($vdsPresets as $preset): ?>
                                    <button type="button" class="vc-preset" data-preset="<?= htmlspecialchars($preset['id']) ?>"
                                        data-cpu="<?= $preset['cpu'] ?>" data-ram="<?= $preset['ram'] ?>"
                                        data-disk="<?= $preset['disk'] ?>" data-ip="<?= $preset['ip'] ?>">
                                        <b><?= htmlspecialchars($preset['name']) ?></b>
                                        <em><?= $preset['cpu'] ?> vCPU · <?= $preset['ram'] ?> GB · <?= $preset['disk'] ?> GB</em>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="vc-block">
                            <div class="vc-block-title">Kaynaklar</div>
                            <?php foreach ($vdsRows as $row):
                                $cfg = $vdsPricing[$row['key']];
                                ?>
                                <div class="vc-row">
                                    <div class="vc-row-head">
                                        <div class="vc-row-label">
                                            <b><?= htmlspecialchars($row['label']) ?></b>
                                            <span><?= htmlspecialchars($row['sub']) ?></span>
                                        </div>
                                        <div class="vc-row-out">
                                            <b id="val-<?= $row['key'] ?>">—</b>
                                            <span id="cost-<?= $row['key'] ?>">—</span>
                                        </div>
                                    </div>

                                    <input type="range" class="vc-range" id="rng-<?= $row['key'] ?>" min="<?= $cfg['min'] ?>"
                                        max="<?= $cfg['max'] ?>" step="<?= $cfg['step'] ?>" value="<?= $row['default'] ?>"
                                        aria-label="<?= htmlspecialchars($row['label']) ?>">

                                    <div class="vc-scale">
                                        <span><?= $cfg['min'] ?> <?= htmlspecialchars($cfg['unit']) ?></span>
                                        <span><?= $cfg['max'] ?> <?= htmlspecialchars($cfg['unit']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="vc-block">
                            <div class="vc-included-note">
                                <div>
                                    <b><?= (int) $vdsPricing['bandwidth']['max'] ?>
                                        <?= htmlspecialchars($vdsPricing['bandwidth']['unit']) ?> bant genişliği</b>
                                    <span> — sınırsız trafik, ek ücret uygulanmaz</span>
                                </div>
                                <em>Dahil</em>
                            </div>
                        </div>
                    </div>

                    <aside>
                        <div class="vc-side-inner">
                            <div class="vc-block">
                                <div class="vc-block-title">Özet</div>
                                <dl style="margin:0">
                                    <div class="vc-line">
                                        <dt>Taban ücret</dt>
                                        <dd>₺<?= number_format($vdsPricing['base']['price'], 0, ',', '.') ?></dd>
                                    </div>
                                    <div class="vc-line">
                                        <dt id="lbl-cpu">İşlemci</dt>
                                        <dd id="sum-cpu">—</dd>
                                    </div>
                                    <div class="vc-line">
                                        <dt id="lbl-ram">Bellek</dt>
                                        <dd id="sum-ram">—</dd>
                                    </div>
                                    <div class="vc-line">
                                        <dt id="lbl-disk">Disk</dt>
                                        <dd id="sum-disk">—</dd>
                                    </div>
                                    <div class="vc-line">
                                        <dt id="lbl-ip">IP adresi</dt>
                                        <dd id="sum-ip">—</dd>
                                    </div>
                                    <div class="vc-line">
                                        <dt>Bant genişliği</dt>
                                        <dd>Ücretsiz</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="vc-block">
                                <div class="vc-block-title">Fatura dönemi</div>
                                <div class="vc-periods">
                                    <?php foreach ($vdsBilling as $months => $b): ?>
                                        <button type="button" class="vc-period<?= $months === 1 ? ' is-active' : '' ?>"
                                            data-months="<?= $months ?>" data-discount="<?= $b['discount'] ?>">
                                            <b><?= htmlspecialchars($b['label']) ?></b>
                                            <span><?= htmlspecialchars($b['note']) ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="vc-block">
                                <div class="vc-total-row">
                                    <span>Aylık toplam</span>
                                </div>
                                <div class="vc-amount">
                                    <b>₺<span id="vdsTotal">0</span></b><span>/ay + KDV</span>
                                </div>
                                <div class="vc-saving" id="vdsSaving"><span id="vdsSavingText"></span></div>

                                <button type="button" class="vc-order" id="vdsOrder">
                                    Sipariş Ver
                                </button>
                                <div class="vc-note">Ödeme onayından sonra dakikalar içinde kurulum</div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </section>

    <!-- Teknik özellikler -->
    <section class="vc-section is-alt">
        <div class="container">
            <div class="vc-head">
                <h2>Teknik özellikler</h2>
                <p>Tüm VDS sunucularında geçerli altyapı bilgileri.</p>
            </div>

            <div class="vc-specs">
                <?php foreach ($vdsSpecGroups as $group): ?>
                    <div class="vc-spec-col">
                        <div class="vc-spec-col-head">
                            <i class="fas <?= $group['icon'] ?>"></i>
                            <?= htmlspecialchars($group['title']) ?>
                        </div>
                        <dl class="vc-spec-list">
                            <?php foreach ($group['items'] as [$ad, $deger]): ?>
                                <div class="vc-spec-item">
                                    <dt><?= htmlspecialchars($ad) ?></dt>
                                    <dd><?= htmlspecialchars($deger) ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Karşılaştırma -->
    <section class="vc-section">
        <div class="container">
            <div class="vc-head">
                <h2>Hangi çözüm size uygun?</h2>
                <p>VDS sunucunun paylaşımlı hosting ve fiziksel sunucu karşısındaki konumu.</p>
            </div>

            <div class="vc-cmp-wrap">
                <table class="vc-cmp">
                    <thead>
                        <tr>
                            <td class="vc-cmp-corner"></td>
                            <?php foreach ($vdsCompareCols as $col): ?>
                                <th scope="col" class="<?= $col['focus'] ? 'is-focus' : '' ?>">
                                    <?php if ($col['focus']): ?>
                                        <span class="vc-cmp-tag">Önerilen</span>
                                    <?php endif; ?>
                                    <b><?= htmlspecialchars($col['name']) ?></b>
                                    <span><?= htmlspecialchars($col['sub']) ?></span>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($vdsCompareRows as [$olcut, $a, $b, $c]): ?>
                            <tr>
                                <th scope="row"><?= htmlspecialchars($olcut) ?></th>
                                <td><?= htmlspecialchars($a) ?></td>
                                <td class="is-focus"><?= htmlspecialchars($b) ?></td>
                                <td><?= htmlspecialchars($c) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>

                    <tfoot>
                        <tr class="vc-cmp-price">
                            <th scope="row">Başlangıç fiyatı</th>
                            <?php foreach ($vdsCompareCols as $col): ?>
                                <td class="<?= $col['focus'] ? 'is-focus' : '' ?>">
                                    <?php if ($col['price'] !== null): ?>
                                        <b>₺<?= number_format($col['price'], 0, ',', '.') ?></b><span>/ay</span>
                                    <?php else: ?>
                                        <b>Teklife göre</b>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr class="vc-cmp-cta">
                            <th scope="row"></th>
                            <?php foreach ($vdsCompareCols as $col): ?>
                                <td class="<?= $col['focus'] ? 'is-focus' : '' ?>">
                                    <a href="<?= htmlspecialchars($col['url']) ?>"
                                        class="vc-btn <?= $col['focus'] ? 'vc-btn-primary' : 'vc-btn-outline' ?>">
                                        <?= htmlspecialchars($col['cta']) ?>
                                    </a>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </section>

    <!-- Destek -->
    <section class="vc-section is-alt">
        <div class="container">
            <?php
            // İletişim bilgileri header.php tarafından ayarlardan yüklenir
            $vdsTel = $companyPhone ?? '';
            $vdsMail = $companyEmail ?? '';
            $vdsTelHref = 'tel:' . preg_replace('/[^0-9+]/', '', $vdsTel);
            ?>
            <div class="vc-help">
                <div class="vc-help-top">
                    <div>
                        <h3>Satış ve teknik destek</h3>
                        <p>Yapılandırma seçiminde ekibimiz size yardımcı olur.</p>
                    </div>
                    <a href="contact.php" class="vc-help-more">Tüm iletişim bilgileri</a>
                </div>

                <div class="vc-help-channels">
                    <?php if ($vdsTel !== ''): ?>
                        <a href="<?= htmlspecialchars($vdsTelHref) ?>" class="vc-help-ch">
                            <span class="vc-help-kind"><i class="fas fa-phone"></i> Satış hattı</span>
                            <b><?= htmlspecialchars($vdsTel) ?></b>
                            <span class="vc-help-when">Hafta içi 09:00 – 18:00</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($vdsMail !== ''): ?>
                        <a href="mailto:<?= htmlspecialchars($vdsMail) ?>" class="vc-help-ch">
                            <span class="vc-help-kind"><i class="fas fa-envelope"></i> E-posta</span>
                            <b><?= htmlspecialchars($vdsMail) ?></b>
                            <span class="vc-help-when">Teklif ve sorularınız için</span>
                        </a>
                    <?php endif; ?>

                    <a href="client/tickets.php" class="vc-help-ch">
                        <span class="vc-help-kind"><i class="fas fa-headset"></i> Destek talebi</span>
                        <b>Müşteri paneli</b>
                        <span class="vc-help-when">7/24 kayıt açabilirsiniz</span>
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    (function () {
        'use strict';

        var fiyat = {
            base: <?= (float) $vdsPricing['base']['price'] ?>,
            cpu: <?= (float) $vdsPricing['cpu']['price'] ?>,
            ram: <?= (float) $vdsPricing['ram']['price'] ?>,
            disk: <?= (float) $vdsPricing['disk']['price'] ?>,
            ip: <?= (float) $vdsPricing['ip']['price'] ?>
        };

        var anahtar = ['cpu', 'ram', 'disk', 'ip'];
        var birim = { cpu: 'vCPU', ram: 'GB', disk: 'GB', ip: 'Adet' };
        var ozetBirim = { cpu: 'vCPU', ram: 'GB', disk: 'GB', ip: 'IPv4' };
        var ozetAd = { cpu: 'İşlemci', ram: 'Bellek', disk: 'Disk', ip: 'IP adresi' };

        var surgu = {};
        anahtar.forEach(function (k) { surgu[k] = document.getElementById('rng-' + k); });

        var toplamEl = document.getElementById('vdsTotal');
        var kazancEl = document.getElementById('vdsSaving');
        var kazancMetin = document.getElementById('vdsSavingText');
        var periyotlar = document.querySelectorAll('.vc-period');
        var hazirlar = document.querySelectorAll('.vc-preset');

        var indirim = 0;
        var ay = 1;

        function tl(n) {
            return new Intl.NumberFormat('tr-TR').format(Math.round(n));
        }

        function dolulukGuncelle(el) {
            var min = parseFloat(el.min), max = parseFloat(el.max), v = parseFloat(el.value);
            el.style.setProperty('--fill', (max > min ? ((v - min) / (max - min)) * 100 : 0) + '%');
        }

        function hazirIsaretle(deger) {
            hazirlar.forEach(function (b) {
                b.classList.toggle('is-active', anahtar.every(function (k) {
                    return parseInt(b.dataset[k], 10) === deger[k];
                }));
            });
        }

        function hesapla() {
            var deger = {};
            anahtar.forEach(function (k) {
                deger[k] = parseInt(surgu[k].value, 10);
                dolulukGuncelle(surgu[k]);
            });

            var ucretliIp = Math.max(0, deger.ip - 1); // ilk IP ücretsiz
            var kalem = {
                cpu: deger.cpu * fiyat.cpu,
                ram: deger.ram * fiyat.ram,
                disk: deger.disk * fiyat.disk,
                ip: ucretliIp * fiyat.ip
            };

            var ham = fiyat.base + kalem.cpu + kalem.ram + kalem.disk + kalem.ip;
            var toplam = ham * (1 - indirim);

            anahtar.forEach(function (k) {
                document.getElementById('val-' + k).textContent = deger[k] + ' ' + birim[k];
                document.getElementById('cost-' + k).textContent =
                    kalem[k] > 0 ? '+₺' + tl(kalem[k]) : 'Ücretsiz';

                document.getElementById('lbl-' + k).textContent =
                    ozetAd[k] + ' · ' + deger[k] + ' ' + ozetBirim[k];
                document.getElementById('sum-' + k).textContent =
                    kalem[k] > 0 ? '₺' + tl(kalem[k]) : 'Ücretsiz';
            });

            toplamEl.textContent = tl(toplam);

            if (indirim > 0) {
                kazancEl.classList.add('is-on');
                kazancMetin.textContent = ay + ' aylık ödemede ₺' + tl((ham - toplam) * ay) + ' tasarruf';
            } else {
                kazancEl.classList.remove('is-on');
            }

            hazirIsaretle(deger);
        }

        anahtar.forEach(function (k) {
            surgu[k].addEventListener('input', hesapla);
        });

        periyotlar.forEach(function (btn) {
            btn.addEventListener('click', function () {
                periyotlar.forEach(function (b) { b.classList.remove('is-active'); });
                btn.classList.add('is-active');
                indirim = parseFloat(btn.dataset.discount) || 0;
                ay = parseInt(btn.dataset.months, 10) || 1;
                hesapla();
            });
        });

        hazirlar.forEach(function (btn) {
            btn.addEventListener('click', function () {
                anahtar.forEach(function (k) { surgu[k].value = btn.dataset[k]; });
                hesapla();
            });
        });

        document.getElementById('vdsOrder').addEventListener('click', function () {
            var q = new URLSearchParams({
                add: 'vds',
                cpu: surgu.cpu.value,
                ram: surgu.ram.value,
                disk: surgu.disk.value,
                ip: surgu.ip.value,
                billing: String(ay)
            });
            window.location.href = 'cart.php?' + q.toString();
        });

        hesapla();
    })();
</script>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>
