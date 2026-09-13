<?php
/**
 * VHM - Yönetim kontrol paneli
 *
 * Önceki sürümdeki sorunlar:
 *   - Hızlı işlemlerin üçü çalışmayan adreslere gidiyordu (?add=1)
 *   - Ödenmemiş fatura sayısı sorgulanıp hiç gösterilmiyordu
 *   - "Toplam Müşteri" kartında karşılaştırmasız bir artış oku vardı
 *   - Veritabanı hatası die() ile ekrana basılıyordu
 *   - Grafik ay adları İngilizceydi ve MONTH() ile gruplandığı için
 *     yıl atlayınca aynı ay çakışıyordu
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Kontrol paneli';
$currentPage = 'dashboard';

/** Sorguyu çalıştırır; tablo yoksa ya da hata olursa varsayılanı döner */
function dbSayi(string $sql, array $par = [], int $varsayilan = 0): int
{
    try {
        return (int) Database::fetchColumn($sql, $par);
    } catch (Throwable $e) {
        error_log('Kontrol paneli sorgusu: ' . $e->getMessage());
        return $varsayilan;
    }
}

function dbTutar(string $sql, array $par = []): float
{
    try {
        return (float) Database::fetchColumn($sql, $par);
    } catch (Throwable $e) {
        error_log('Kontrol paneli sorgusu: ' . $e->getMessage());
        return 0.0;
    }
}

function dbListe(string $sql, array $par = []): array
{
    try {
        return Database::fetchAll($sql, $par);
    } catch (Throwable $e) {
        error_log('Kontrol paneli listesi: ' . $e->getMessage());
        return [];
    }
}

/* ---------------- Özet ---------------- */
$buAy = dbTutar(
    "SELECT COALESCE(SUM(amount_paid), 0) FROM invoices
      WHERE status = 'paid' AND paid_date >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')"
);
$gecenAy = dbTutar(
    "SELECT COALESCE(SUM(amount_paid), 0) FROM invoices
      WHERE status = 'paid'
        AND paid_date >= DATE_FORMAT(CURRENT_DATE() - INTERVAL 1 MONTH, '%Y-%m-01')
        AND paid_date <  DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')"
);

$musteriBuAy = dbSayi(
    "SELECT COUNT(*) FROM clients WHERE created_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')"
);
$musteriGecenAy = dbSayi(
    "SELECT COUNT(*) FROM clients
      WHERE created_at >= DATE_FORMAT(CURRENT_DATE() - INTERVAL 1 MONTH, '%Y-%m-01')
        AND created_at <  DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')"
);

/**
 * İki dönemi karşılaştırır. Karşılaştırılacak veri yoksa null döner;
 * böylece "artıyor" oku uydurulmuş olmaz.
 */
function degisim(float $simdi, float $onceki): ?float
{
    if ($onceki <= 0) {
        return null;
    }
    return (($simdi - $onceki) / $onceki) * 100;
}

$kartlar = [
    [
        'etiket' => 'Müşteri',
        'deger' => number_format(dbSayi("SELECT COUNT(*) FROM clients"), 0, ',', '.'),
        'ikon' => 'fa-users',
        'renk' => 'mavi',
        'adres' => 'clients.php',
        'degisim' => degisim((float) $musteriBuAy, (float) $musteriGecenAy),
        'alt' => $musteriBuAy > 0 ? 'Bu ay ' . $musteriBuAy . ' yeni' : null,
    ],
    [
        'etiket' => 'Aktif hizmet',
        'deger' => number_format(dbSayi("SELECT COUNT(*) FROM services WHERE status = 'active'"), 0, ',', '.'),
        'ikon' => 'fa-cubes',
        'renk' => 'yesil',
        'adres' => 'services.php',
    ],
    [
        'etiket' => 'Açık destek talebi',
        'deger' => number_format(dbSayi(
            "SELECT COUNT(*) FROM tickets WHERE status IN ('open','customer_reply','on_hold')"
        ), 0, ',', '.'),
        'ikon' => 'fa-life-ring',
        'renk' => 'turuncu',
        'adres' => 'tickets.php',
    ],
    [
        'etiket' => 'Ödenmemiş fatura',
        'deger' => number_format(dbSayi("SELECT COUNT(*) FROM invoices WHERE status = 'unpaid'"), 0, ',', '.'),
        'ikon' => 'fa-file-invoice',
        'renk' => 'kirmizi',
        'adres' => 'invoices.php',
        'alt' => ($t = dbTutar("SELECT COALESCE(SUM(total - amount_paid),0) FROM invoices WHERE status = 'unpaid'")) > 0
            ? number_format($t, 2, ',', '.') . ' ₺ alacak' : null,
    ],
    [
        'etiket' => 'Bekleyen sipariş',
        'deger' => number_format(dbSayi("SELECT COUNT(*) FROM orders WHERE status = 'pending'"), 0, ',', '.'),
        'ikon' => 'fa-cart-shopping',
        'renk' => 'mor',
        'adres' => 'orders.php',
    ],
    [
        'etiket' => 'Bu ayki gelir',
        'deger' => number_format($buAy, 2, ',', '.') . ' ₺',
        'ikon' => 'fa-turkish-lira-sign',
        'renk' => 'lacivert',
        'adres' => 'transactions.php',
        'degisim' => degisim($buAy, $gecenAy),
        'alt' => $gecenAy > 0 ? 'Geçen ay ' . number_format($gecenAy, 2, ',', '.') . ' ₺' : null,
    ],
];

/* ---------------- Gelir grafiği ---------------- */
$ayAdi = [1 => 'Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
$aySayisi = (int) ($_GET['ay'] ?? 6);
if (!in_array($aySayisi, [6, 12], true)) {
    $aySayisi = 6;
}

/* Yıl ve ay birlikte gruplanır; aksi hâlde bir yıl önceki aynı ay
   bu ayın üzerine biner. */
$ham = dbListe(
    "SELECT DATE_FORMAT(paid_date, '%Y-%m') AS donem, SUM(amount_paid) AS toplam
       FROM invoices
      WHERE status = 'paid'
        AND paid_date >= DATE_FORMAT(CURRENT_DATE() - INTERVAL {$aySayisi} MONTH, '%Y-%m-01')
      GROUP BY donem
      ORDER BY donem"
);
$haritaGelir = [];
foreach ($ham as $s) {
    $haritaGelir[(string) $s['donem']] = (float) $s['toplam'];
}

/* Kayıt olmayan aylar sıfırla doldurulur; grafik boşluk atlamaz */
$grafikEtiket = [];
$grafikDeger = [];
for ($i = $aySayisi - 1; $i >= 0; $i--) {
    $zaman = strtotime("first day of -{$i} month");
    $anahtar = date('Y-m', $zaman);
    $grafikEtiket[] = $ayAdi[(int) date('n', $zaman)] . ' ' . date('y', $zaman);
    $grafikDeger[] = round($haritaGelir[$anahtar] ?? 0, 2);
}
$grafikVarMi = array_sum($grafikDeger) > 0;

/* ---------------- Bekleyen işler ---------------- */
$bekleyen = array_values(array_filter([
    [
        'ad' => 'İptal talebi',
        'adet' => dbSayi("SELECT COUNT(*) FROM cancellation_requests WHERE status = 'pending'"),
        'ikon' => 'fa-ban',
        'adres' => 'cancellations.php',
    ],
    [
        'ad' => 'Keşif talebi',
        'adet' => dbSayi("SELECT COUNT(*) FROM kesif_talepleri WHERE durum = 'yeni'"),
        'ikon' => 'fa-location-dot',
        'adres' => 'kesif-talepleri.php',
    ],
    [
        'ad' => 'İletişim mesajı',
        'adet' => dbSayi("SELECT COUNT(*) FROM iletisim_mesajlari WHERE durum = 'yeni'"),
        'ikon' => 'fa-envelope',
        'adres' => 'iletisim-mesajlari.php',
    ],
    [
        'ad' => 'Marka tescil talebi',
        'adet' => dbSayi("SELECT COUNT(*) FROM marka_tescil_talepleri WHERE durum = 'yeni'"),
        'ikon' => 'fa-trademark',
        'adres' => 'marka-talepleri.php',
    ],
], static fn(array $x): bool => $x['adet'] > 0));

/* ---------------- Listeler ---------------- */
$sonSiparisler = dbListe(
    "SELECT o.id, o.order_number, o.total, o.status, o.created_at,
            c.first_name, c.last_name
       FROM orders o
       LEFT JOIN clients c ON c.id = o.client_id
      ORDER BY o.created_at DESC LIMIT 6"
);

$sonTalepler = dbListe(
    "SELECT t.id, t.subject, t.status, t.updated_at, c.first_name, c.last_name
       FROM tickets t
       LEFT JOIN clients c ON c.id = t.client_id
      ORDER BY t.updated_at DESC LIMIT 6"
);

/* ---------------- Sistem özeti ---------------- */
$sistem = [
    ['Sunucu', dbSayi("SELECT COUNT(*) FROM servers"), 'fa-server', 'servers.php'],
    ['ESXi sunucusu', dbSayi("SELECT COUNT(*) FROM esxi_servers"), 'fa-hard-drive', 'esxi-servers.php'],
    ['Kayıtlı alan adı', dbSayi("SELECT COUNT(*) FROM domains"), 'fa-globe', 'domains.php'],
    ['Satıştaki ürün', dbSayi("SELECT COUNT(*) FROM products WHERE is_active = 1"), 'fa-box', 'products.php'],
    ['Etkin modül', dbSayi("SELECT COUNT(*) FROM modules WHERE is_active = 1"), 'fa-puzzle-piece', 'modules.php'],
    ['Bilgi bankası makalesi', dbSayi("SELECT COUNT(*) FROM kb_makaleler WHERE is_active = 1"), 'fa-book', 'bilgi-bankasi.php'],
];

$siparisDurum = [
    'pending' => ['Bekliyor', 'badge-warning'],
    'processing' => ['İşleniyor', 'badge-info'],
    'active' => ['Etkin', 'badge-success'],
    'fraud' => ['Şüpheli', 'badge-danger'],
    'cancelled' => ['İptal', 'badge'],
];
$talepDurum = [
    'open' => ['Açık', 'badge-info'],
    'answered' => ['Yanıtlandı', 'badge-success'],
    'customer_reply' => ['Müşteri yanıtladı', 'badge-warning'],
    'on_hold' => ['Beklemede', 'badge-warning'],
    'in_progress' => ['İşlemde', 'badge-info'],
    'closed' => ['Kapalı', 'badge'],
];

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

<style>
    /* ==========================================
       Kontrol paneli - db
       ========================================== */
    .db-ozet {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }

    .db-kart {
        display: flex;
        align-items: flex-start;
        gap: 13px;
        padding: 17px;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        transition: border-color .15s, transform .15s;
    }

    .db-kart:hover {
        border-color: var(--y-primary);
        transform: translateY(-2px);
        text-decoration: none;
    }

    .db-kart-ikon {
        flex-shrink: 0;
        width: 40px;
        height: 40px;
        display: grid;
        place-items: center;
        border-radius: 9px;
        font-size: 16px;
    }

    .db-kart-ikon.mavi { background: color-mix(in srgb, #2474f5 14%, transparent); color: #2474f5; }
    .db-kart-ikon.yesil { background: color-mix(in srgb, #16a34a 14%, transparent); color: #16a34a; }
    .db-kart-ikon.turuncu { background: color-mix(in srgb, #d97706 16%, transparent); color: #d97706; }
    .db-kart-ikon.kirmizi { background: color-mix(in srgb, #dc2626 13%, transparent); color: #dc2626; }
    .db-kart-ikon.mor { background: color-mix(in srgb, #7c3aed 14%, transparent); color: #7c3aed; }
    .db-kart-ikon.lacivert { background: color-mix(in srgb, #0891b2 14%, transparent); color: #0891b2; }

    .db-kart-govde {
        min-width: 0;
        flex: 1;
    }

    .db-kart-deger {
        display: flex;
        align-items: baseline;
        gap: 8px;
        font-size: 22px;
        font-weight: 700;
        letter-spacing: -.02em;
        color: var(--y-metin);
        line-height: 1.2;
    }

    .db-kart-etiket {
        margin-top: 2px;
        font-size: 12.5px;
        color: var(--y-metin-3);
    }

    .db-kart-alt {
        margin-top: 5px;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    /* Karşılaştırma yalnızca gerçek veri varsa gösterilir */
    .db-degisim {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 1px 7px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
    }

    .db-degisim.artis {
        background: color-mix(in srgb, var(--y-success) 14%, transparent);
        color: var(--y-success);
    }

    .db-degisim.azalis {
        background: color-mix(in srgb, var(--y-danger) 13%, transparent);
        color: var(--y-danger);
    }

    /* ---------- Düzen ---------- */
    .db-satir {
        display: grid;
        gap: 18px;
        margin-bottom: 18px;
    }

    .db-satir.iki {
        grid-template-columns: minmax(0, 1.9fr) minmax(0, 1fr);
    }

    .db-satir.esit {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .db-panel {
        display: flex;
        flex-direction: column;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .db-panel-bas {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 13px 18px;
        border-bottom: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
    }

    .db-panel-bas h2 {
        font-size: 13.5px;
        font-weight: 700;
    }

    .db-panel-govde {
        flex: 1;
        padding: 18px;
    }

    .db-panel-govde.sikisik {
        padding: 6px;
    }

    /* Dönem seçici */
    .db-donem {
        display: flex;
        gap: 5px;
    }

    .db-donem a {
        padding: 4px 11px;
        border: 1px solid var(--y-cizgi);
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        color: var(--y-metin-3);
    }

    .db-donem a:hover {
        border-color: var(--y-primary);
        color: var(--y-primary);
        text-decoration: none;
    }

    .db-donem a.secili {
        border-color: var(--y-primary);
        background: var(--y-primary-soft);
        color: var(--y-primary);
    }

    /* ---------- Bekleyen işler ---------- */
    .db-is {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 12px;
        border-radius: var(--y-r-sm);
        color: var(--y-metin-2);
        transition: background-color .15s;
    }

    .db-is:hover {
        background: var(--y-yuzey-2);
        text-decoration: none;
    }

    .db-is-ikon {
        width: 32px;
        height: 32px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border-radius: 8px;
        background: color-mix(in srgb, var(--y-danger) 12%, transparent);
        color: var(--y-danger);
        font-size: 13px;
    }

    .db-is b {
        display: block;
        font-size: 13.5px;
        font-weight: 600;
        color: var(--y-metin);
    }

    .db-is span {
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .db-is-sayi {
        margin-left: auto;
        min-width: 26px;
        padding: 2px 9px;
        border-radius: 999px;
        background: var(--y-danger);
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        text-align: center;
    }

    /* ---------- Hızlı işlemler ---------- */
    .db-eylemler {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 9px;
    }

    .db-eylem {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        padding: 15px 10px;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r-sm);
        background: var(--y-yuzey);
        color: var(--y-metin-2);
        font-size: 12.5px;
        font-weight: 600;
        text-align: center;
        transition: border-color .15s, color .15s, background-color .15s;
    }

    .db-eylem:hover {
        border-color: var(--y-primary);
        background: var(--y-primary-soft);
        color: var(--y-primary);
        text-decoration: none;
    }

    .db-eylem i {
        font-size: 16px;
        color: var(--y-primary);
    }

    /* ---------- Sistem özeti ---------- */
    .db-sistem {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 1px;
        background: var(--y-cizgi);
    }

    .db-sistem a {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 15px 18px;
        background: var(--y-yuzey);
        color: var(--y-metin-2);
        transition: background-color .15s;
    }

    .db-sistem a:hover {
        background: var(--y-yuzey-2);
        text-decoration: none;
    }

    .db-sistem i {
        width: 30px;
        height: 30px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border-radius: 8px;
        background: var(--y-primary-soft);
        color: var(--y-primary);
        font-size: 13px;
    }

    .db-sistem b {
        display: block;
        font-size: 17px;
        font-weight: 700;
        color: var(--y-metin);
        line-height: 1.2;
    }

    .db-sistem span {
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    /* ---------- Liste ---------- */
    .db-liste {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .db-liste td {
        padding: 10px 18px;
        border-bottom: 1px solid var(--y-cizgi-soft);
        vertical-align: middle;
    }

    .db-liste tr:last-child td {
        border-bottom: none;
    }

    .db-liste tr:hover td {
        background: var(--y-yuzey-2);
    }

    .db-liste b {
        font-weight: 600;
        color: var(--y-metin);
    }

    .db-liste span {
        display: block;
        margin-top: 1px;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .db-liste .sag {
        text-align: right;
        white-space: nowrap;
    }

    .db-bos {
        padding: 38px 20px;
        text-align: center;
        color: var(--y-metin-3);
    }

    .db-bos i {
        font-size: 24px;
        opacity: .5;
    }

    .db-bos p {
        margin: 9px 0 0;
        font-size: 13px;
    }

    @media (max-width: 1100px) {
        .db-satir.iki,
        .db-satir.esit {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>Kontrol paneli</h1>
        <p><?= date('d.m.Y') ?> &middot; Hoş geldiniz, <?= htmlspecialchars($yAdmin) ?></p>
    </div>
</div>

<!-- ===== Özet ===== -->
<div class="db-ozet">
    <?php foreach ($kartlar as $k): ?>
        <a class="db-kart" href="<?= htmlspecialchars($k['adres']) ?>">
            <span class="db-kart-ikon <?= htmlspecialchars($k['renk']) ?>">
                <i class="fas <?= htmlspecialchars($k['ikon']) ?>"></i>
            </span>
            <span class="db-kart-govde">
                <span class="db-kart-deger">
                    <?= htmlspecialchars($k['deger']) ?>
                    <?php if (isset($k['degisim']) && $k['degisim'] !== null): ?>
                        <?php $artis = $k['degisim'] >= 0; ?>
                        <span class="db-degisim <?= $artis ? 'artis' : 'azalis' ?>">
                            <i class="fas fa-arrow-<?= $artis ? 'up' : 'down' ?>"></i>
                            %<?= number_format(abs($k['degisim']), 0) ?>
                        </span>
                    <?php endif; ?>
                </span>
                <span class="db-kart-etiket"><?= htmlspecialchars($k['etiket']) ?></span>
                <?php if (!empty($k['alt'])): ?>
                    <span class="db-kart-alt"><?= htmlspecialchars($k['alt']) ?></span>
                <?php endif; ?>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<!-- ===== Grafik + bekleyen işler ===== -->
<div class="db-satir iki">
    <div class="db-panel">
        <div class="db-panel-bas">
            <h2>Gelir</h2>
            <div class="db-donem">
                <a href="?ay=6" class="<?= $aySayisi === 6 ? 'secili' : '' ?>">6 ay</a>
                <a href="?ay=12" class="<?= $aySayisi === 12 ? 'secili' : '' ?>">12 ay</a>
            </div>
        </div>
        <div class="db-panel-govde">
            <?php if (!$grafikVarMi): ?>
                <div class="db-bos">
                    <i class="fas fa-chart-line"></i>
                    <p>Bu dönemde ödenmiş fatura yok.<br>Tahsilat yapıldıkça grafik dolacak.</p>
                </div>
            <?php else: ?>
                <div style="height: 280px; position: relative;">
                    <canvas id="db-grafik"></canvas>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="db-panel">
        <div class="db-panel-bas">
            <h2>Bekleyen işler</h2>
        </div>
        <div class="db-panel-govde <?= $bekleyen ? 'sikisik' : '' ?>">
            <?php if (!$bekleyen): ?>
                <div class="db-bos">
                    <i class="fas fa-circle-check" style="color: var(--y-success); opacity: 1;"></i>
                    <p>Bekleyen iş yok.<br>Tüm talepler ele alınmış.</p>
                </div>
            <?php else: ?>
                <?php foreach ($bekleyen as $b): ?>
                    <a class="db-is" href="<?= htmlspecialchars($b['adres']) ?>">
                        <span class="db-is-ikon"><i class="fas <?= htmlspecialchars($b['ikon']) ?>"></i></span>
                        <span>
                            <b><?= htmlspecialchars($b['ad']) ?></b>
                            <span>yanıt bekliyor</span>
                        </span>
                        <span class="db-is-sayi"><?= (int) $b['adet'] ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===== Son siparişler + son talepler ===== -->
<div class="db-satir esit">
    <div class="db-panel">
        <div class="db-panel-bas">
            <h2>Son siparişler</h2>
            <a href="orders.php" class="btn btn-sm btn-outline">Tümü</a>
        </div>
        <?php if (!$sonSiparisler): ?>
            <div class="db-bos">
                <i class="fas fa-cart-shopping"></i>
                <p>Henüz sipariş yok.</p>
            </div>
        <?php else: ?>
            <table class="db-liste">
                <tbody>
                    <?php foreach ($sonSiparisler as $s):
                        [$durumAd, $durumSinif] = $siparisDurum[$s['status']] ?? [$s['status'], 'badge'];
                        $ad = trim((string) ($s['first_name'] ?? '') . ' ' . (string) ($s['last_name'] ?? ''));
                        ?>
                        <tr>
                            <td>
                                <b>#<?= htmlspecialchars((string) ($s['order_number'] ?? $s['id'])) ?></b>
                                <span><?= htmlspecialchars($ad !== '' ? $ad : 'Müşteri silinmiş') ?></span>
                            </td>
                            <td class="sag">
                                <b><?= number_format((float) $s['total'], 2, ',', '.') ?> ₺</b>
                                <span><?= date('d.m.Y', strtotime((string) $s['created_at'])) ?></span>
                            </td>
                            <td class="sag">
                                <span class="badge <?= $durumSinif ?>"><?= htmlspecialchars($durumAd) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="db-panel">
        <div class="db-panel-bas">
            <h2>Son destek talepleri</h2>
            <a href="tickets.php" class="btn btn-sm btn-outline">Tümü</a>
        </div>
        <?php if (!$sonTalepler): ?>
            <div class="db-bos">
                <i class="fas fa-life-ring"></i>
                <p>Henüz destek talebi yok.</p>
            </div>
        <?php else: ?>
            <table class="db-liste">
                <tbody>
                    <?php foreach ($sonTalepler as $t):
                        [$durumAd, $durumSinif] = $talepDurum[$t['status']] ?? [$t['status'], 'badge'];
                        $ad = trim((string) ($t['first_name'] ?? '') . ' ' . (string) ($t['last_name'] ?? ''));
                        ?>
                        <tr>
                            <td>
                                <b><?= htmlspecialchars(mb_strimwidth((string) $t['subject'], 0, 46, '…')) ?></b>
                                <span><?= htmlspecialchars($ad !== '' ? $ad : 'Müşteri silinmiş') ?></span>
                            </td>
                            <td class="sag">
                                <span class="badge <?= $durumSinif ?>"><?= htmlspecialchars($durumAd) ?></span>
                                <span><?= date('d.m.Y H:i', strtotime((string) $t['updated_at'])) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Hızlı işlemler + sistem ===== -->
<div class="db-satir iki">
    <div class="db-panel">
        <div class="db-panel-bas">
            <h2>Sistem özeti</h2>
        </div>
        <div class="db-sistem">
            <?php foreach ($sistem as [$ad, $sayi, $ikon, $adres]): ?>
                <a href="<?= htmlspecialchars($adres) ?>">
                    <i class="fas <?= htmlspecialchars($ikon) ?>"></i>
                    <span>
                        <b><?= number_format($sayi, 0, ',', '.') ?></b>
                        <span><?= htmlspecialchars($ad) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="db-panel">
        <div class="db-panel-bas">
            <h2>Hızlı işlemler</h2>
        </div>
        <div class="db-panel-govde">
            <?php
            /* Yalnızca gerçekten çalışan sayfalara bağlanır.
               Eski sürümdeki ?add=1 bağlantıları hiçbir sayfada
               okunmuyordu; tıklayınca form açılmıyordu. */
            $eylemler = [
                ['product-add.php', 'Ürün ekle', 'fa-box'],
                ['invoice-create.php', 'Fatura oluştur', 'fa-file-invoice'],
                ['proposal-create.php', 'Teklif hazırla', 'fa-file-signature'],
                ['tickets.php', 'Destek talepleri', 'fa-life-ring'],
                ['bilgi-bankasi.php', 'Makale yaz', 'fa-book'],
                ['settings.php', 'Ayarlar', 'fa-gear'],
            ];
            ?>
            <div class="db-eylemler">
                <?php foreach ($eylemler as [$adres, $ad, $ikon]): ?>
                    <?php if (!is_file(__DIR__ . '/' . $adres)) {
                        continue;
                    } ?>
                    <a class="db-eylem" href="<?= htmlspecialchars($adres) ?>">
                        <i class="fas <?= htmlspecialchars($ikon) ?>"></i>
                        <span><?= htmlspecialchars($ad) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($grafikVarMi): ?>
    <script>
        (function () {
            var tuval = document.getElementById('db-grafik');
            if (!tuval || typeof Chart === 'undefined') {
                return;
            }

            var kok = getComputedStyle(document.documentElement);
            var vurgu = kok.getPropertyValue('--y-primary').trim() || '#2474f5';
            var soluk = kok.getPropertyValue('--y-metin-3').trim() || '#7c8aa0';
            var cizgi = kok.getPropertyValue('--y-cizgi').trim() || '#dbe4f2';

            var alan = tuval.getContext('2d').createLinearGradient(0, 0, 0, 280);
            alan.addColorStop(0, vurgu + '33');
            alan.addColorStop(1, vurgu + '00');

            new Chart(tuval, {
                type: 'line',
                data: {
                    labels: <?= json_encode($grafikEtiket, JSON_UNESCAPED_UNICODE) ?>,
                    datasets: [{
                        label: 'Tahsilat',
                        data: <?= json_encode($grafikDeger) ?>,
                        borderColor: vurgu,
                        backgroundColor: alan,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointBackgroundColor: vurgu
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (b) {
                                    return b.parsed.y.toLocaleString('tr-TR', {
                                        minimumFractionDigits: 2
                                    }) + ' ₺';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: soluk }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: cizgi },
                            ticks: {
                                color: soluk,
                                callback: function (d) {
                                    return d.toLocaleString('tr-TR');
                                }
                            }
                        }
                    }
                }
            });
        })();
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
