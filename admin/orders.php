<?php
/**
 * VHM - Siparişler
 *
 * Önceki sürümdeki sorunlar:
 *   - Durum değiştirme ve silme POST ile çalışıyordu ama CSRF belirteci
 *     doğrulanmıyordu.
 *   - $_POST['new_status'] doğrudan UPDATE'e gidiyordu; enum dışı bir
 *     değer sipariş durumunu bozabiliyordu.
 *   - Sipariş etkinleştirilirken hizmetler tek tek ekleniyor, durum
 *     güncellemesi en sonda yapılıyordu. Ortada hata olursa hizmetler
 *     oluşmuş ama sipariş hâlâ "bekliyor" kalıyordu. Artık tek işlem.
 *   - Etkin sunucu yoksa hizmet sessizce sunucusuz oluşuyordu.
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

$pageTitle = 'Siparişler';
$currentPage = 'orders';

/* orders.status enum'u ile birebir aynı olmalı */
$durumlar = [
    'pending' => ['Bekliyor', 'badge-warning', 'fa-clock'],
    'processing' => ['İşleniyor', 'badge-info', 'fa-spinner'],
    'active' => ['Etkin', 'badge-success', 'fa-circle-check'],
    'fraud' => ['Şüpheli', 'badge-danger', 'fa-triangle-exclamation'],
    'cancelled' => ['İptal', 'badge', 'fa-ban'],
];

$donemAdi = [
    'monthly' => 'Aylık', 'quarterly' => '3 aylık', 'semiannually' => '6 aylık',
    'annually' => 'Yıllık', 'biennially' => '2 yıllık', 'triennially' => '3 yıllık',
    'onetime' => 'Tek seferlik',
];

/** Fatura dönemine göre sonraki ödeme tarihi */
function sonrakiTarih(string $donem): string
{
    $ek = match ($donem) {
        'quarterly' => '+3 months',
        'semiannually' => '+6 months',
        'annually' => '+1 year',
        'biennially' => '+2 years',
        'triennially' => '+3 years',
        default => '+1 month',
    };
    return date('Y-m-d', strtotime($ek));
}

/* ---------- Durum değiştirme ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['durum_degistir'])) {
    Guvenlik::zorunlu();

    $siparisId = (int) ($_POST['order_id'] ?? 0);
    $yeniDurum = (string) ($_POST['new_status'] ?? '');

    if ($siparisId <= 0 || !isset($durumlar[$yeniDurum])) {
        /* Enum dışı değer sipariş durumunu bozuyordu */
        $_SESSION['sip_mesaj'] = ['tip' => 'error', 'metin' => 'Geçersiz sipariş durumu.'];
        header('Location: orders.php');
        exit;
    }

    $db = Database::getInstance();
    $olusan = 0;
    $sunucusuz = false;

    try {
        $db->beginTransaction();

        $siparis = Database::fetch("SELECT * FROM orders WHERE id = ?", [$siparisId]);
        if (!$siparis) {
            throw new RuntimeException('Sipariş bulunamadı.');
        }

        /* Etkinleştirmede hizmetler oluşturulur */
        if ($yeniDurum === 'active') {
            $kalemler = Database::fetchAll("SELECT * FROM order_items WHERE order_id = ?", [$siparisId]);

            /* En az hesabı olan etkin sunucu */
            $sunucu = Database::fetch(
                "SELECT s.id,
                        (SELECT COUNT(*) FROM services v
                          WHERE v.server_id = s.id AND v.status IN ('active','pending')) AS yuk
                   FROM servers s
                  WHERE s.status = 'active'
                  ORDER BY yuk ASC
                  LIMIT 1"
            );
            $sunucuId = $sunucu['id'] ?? null;
            $sunucusuz = $sunucuId === null;

            foreach ($kalemler as $k) {
                if (empty($k['product_id'])) {
                    continue;
                }

                /* Aynı kalem için ikinci kez hizmet açılmasın */
                $var = Database::fetch(
                    "SELECT id FROM services WHERE order_id = ? AND product_id = ?",
                    [$siparisId, (int) $k['product_id']]
                );
                if ($var) {
                    continue;
                }

                $donem = (string) ($k['billing_cycle'] ?? 'monthly');

                Database::query(
                    "INSERT INTO services
                        (client_id, order_id, product_id, server_id, domain, status,
                         billing_cycle, amount, registration_date, next_due_date, first_payment_amount)
                     VALUES (?, ?, ?, ?, ?, 'active', ?, ?, CURDATE(), ?, ?)",
                    [
                        (int) $siparis['client_id'],
                        $siparisId,
                        (int) $k['product_id'],
                        $sunucuId,
                        $k['domain'] ?? null,
                        $donem,
                        (float) $k['unit_price'],
                        sonrakiTarih($donem),
                        (float) $k['total'],
                    ]
                );
                $olusan++;
            }
        }

        /* Durum güncellemesi hizmetlerle aynı işlemde; biri olur biri
           olmaz durumu oluşmaz. */
        Database::query("UPDATE orders SET status = ? WHERE id = ?", [$yeniDurum, $siparisId]);

        $db->commit();

        $metin = 'Sipariş #' . ($siparis['order_number'] ?? $siparisId) . ' durumu "'
            . $durumlar[$yeniDurum][0] . '" olarak güncellendi.';
        if ($olusan > 0) {
            $metin .= ' ' . $olusan . ' hizmet oluşturuldu.';
        }
        $tip = 'success';

        if ($yeniDurum === 'active' && $sunucusuz && $olusan > 0) {
            /* Sessizce sunucusuz hizmet açmak yerine haber verilir */
            $metin .= ' Etkin sunucu bulunmadığı için hizmetler sunucuya atanmadı;'
                . ' Altyapı > Sunucular bölümünden atama yapın.';
            $tip = 'uyari';
        }

        $_SESSION['sip_mesaj'] = ['tip' => $tip, 'metin' => $metin];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Sipariş durumu güncellenemedi: ' . $e->getMessage());
        $_SESSION['sip_mesaj'] = [
            'tip' => 'error',
            'metin' => 'Sipariş güncellenemedi, hiçbir değişiklik kaydedilmedi.',
        ];
    }

    header('Location: orders.php');
    exit;
}

/* ---------- Silme ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['siparis_sil'])) {
    Guvenlik::zorunlu();
    $siparisId = (int) ($_POST['order_id'] ?? 0);

    try {
        /* order_items zaten ON DELETE CASCADE; elle silmeye gerek yok */
        Database::query("DELETE FROM orders WHERE id = ?", [$siparisId]);
        $_SESSION['sip_mesaj'] = ['tip' => 'success', 'metin' => 'Sipariş silindi.'];
    } catch (Throwable $e) {
        error_log('Sipariş silinemedi: ' . $e->getMessage());
        $_SESSION['sip_mesaj'] = ['tip' => 'error', 'metin' => 'Sipariş silinemedi.'];
    }

    header('Location: orders.php');
    exit;
}

$mesaj = null;
if (!empty($_SESSION['sip_mesaj'])) {
    $mesaj = $_SESSION['sip_mesaj'];
    unset($_SESSION['sip_mesaj']);
}

/* ---------- Süzme ve sayfalama ---------- */
$suzgec = (string) ($_GET['status'] ?? '');
$arama = trim((string) ($_GET['q'] ?? $_GET['search'] ?? ''));

$kosul = [];
$par = [];
if (isset($durumlar[$suzgec])) {
    $kosul[] = "o.status = ?";
    $par[] = $suzgec;
}
if ($arama !== '') {
    $kosul[] = "(o.order_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)";
    $desen = '%' . str_replace(['%', '_'], ['\%', '\_'], $arama) . '%';
    $par = array_merge($par, array_fill(0, 4, $desen));
}
$nerede = $kosul ? ' WHERE ' . implode(' AND ', $kosul) : '';

$sayfa = max(1, (int) ($_GET['page'] ?? 1));
$adet = 20;
$atla = ($sayfa - 1) * $adet;

$sayiGuvenli = static function (string $sql, array $p = []): int {
    try {
        return (int) Database::fetchColumn($sql, $p);
    } catch (Throwable $e) {
        error_log('Sipariş sorgusu: ' . $e->getMessage());
        return 0;
    }
};

$toplam = $sayiGuvenli(
    "SELECT COUNT(*) FROM orders o LEFT JOIN clients c ON c.id = o.client_id" . $nerede,
    $par
);
$sayfaSayisi = max(1, (int) ceil($toplam / $adet));

try {
    $siparisler = Database::fetchAll(
        "SELECT o.*, c.first_name, c.last_name, c.email, c.company_name,
                (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS kalem,
                (SELECT COUNT(*) FROM services v WHERE v.order_id = o.id) AS hizmet
           FROM orders o
           LEFT JOIN clients c ON c.id = o.client_id
           {$nerede}
          ORDER BY o.created_at DESC
          LIMIT {$adet} OFFSET {$atla}",
        $par
    );
} catch (Throwable $e) {
    error_log('Sipariş listesi okunamadı: ' . $e->getMessage());
    $siparisler = [];
}

/* ---------- Özet ---------- */
$ozet = [
    ['Tüm siparişler', $sayiGuvenli("SELECT COUNT(*) FROM orders"), 'fa-cart-shopping', ''],
    ['Bekleyen', $sayiGuvenli("SELECT COUNT(*) FROM orders WHERE status = 'pending'"), 'fa-clock', 'pending'],
    ['Etkin', $sayiGuvenli("SELECT COUNT(*) FROM orders WHERE status = 'active'"), 'fa-circle-check', 'active'],
    ['İptal', $sayiGuvenli("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'"), 'fa-ban', 'cancelled'],
];

$bekleyenCiro = 0.0;
try {
    $bekleyenCiro = (float) Database::fetchColumn(
        "SELECT COALESCE(SUM(total), 0) FROM orders WHERE status = 'pending'"
    );
} catch (Throwable $e) {
    error_log('Bekleyen ciro okunamadı: ' . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* ==========================================
       Siparişler - sp
       ========================================== */
    .sp-ozet {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }

    .sp-ozet-kart {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 15px 17px;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        color: var(--y-metin-2);
        transition: border-color .15s;
    }

    .sp-ozet-kart:hover {
        border-color: var(--y-primary);
        text-decoration: none;
    }

    .sp-ozet-kart.secili {
        border-color: var(--y-primary);
        background: var(--y-primary-soft);
    }

    .sp-ozet-ikon {
        width: 38px;
        height: 38px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border-radius: 9px;
        background: var(--y-primary-soft);
        color: var(--y-primary);
        font-size: 15px;
    }

    .sp-ozet-kart b {
        display: block;
        font-size: 21px;
        font-weight: 700;
        letter-spacing: -.02em;
        color: var(--y-metin);
        line-height: 1.2;
    }

    .sp-ozet-kart span {
        font-size: 12.5px;
        color: var(--y-metin-3);
    }

    .sp-arac {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }

    .sp-ara {
        position: relative;
        flex: 1;
        min-width: 220px;
        max-width: 420px;
    }

    .sp-ara i {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 13px;
        color: var(--y-metin-3);
        pointer-events: none;
    }

    .sp-ara input {
        width: 100%;
        padding-left: 36px;
    }

    .sp-sarmal {
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow-x: auto;
    }

    .sp-tablo {
        width: 100%;
        border-collapse: collapse;
        font-size: 13.5px;
        min-width: 860px;
    }

    .sp-tablo th {
        padding: 11px 16px;
        text-align: left;
        border-bottom: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: var(--y-metin-3);
        white-space: nowrap;
    }

    .sp-tablo td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--y-cizgi-soft);
        vertical-align: middle;
    }

    .sp-tablo tr:last-child td {
        border-bottom: none;
    }

    .sp-tablo tbody tr:hover td {
        background: var(--y-yuzey-2);
    }

    .sp-no b {
        font-weight: 700;
        color: var(--y-metin);
    }

    .sp-no span {
        display: block;
        margin-top: 2px;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .sp-musteri b {
        display: block;
        font-weight: 600;
        color: var(--y-metin);
    }

    .sp-musteri a {
        font-size: 12px;
        color: var(--y-metin-3);
    }

    .sp-musteri a:hover {
        color: var(--y-primary);
    }

    .sp-sag {
        text-align: right;
        white-space: nowrap;
    }

    .sp-tutar {
        font-weight: 700;
        color: var(--y-metin);
    }

    .sp-etiket {
        display: inline-block;
        margin-left: 5px;
        padding: 1px 7px;
        border-radius: 999px;
        border: 1px solid var(--y-cizgi);
        font-size: 11px;
        color: var(--y-metin-3);
    }

    .sp-durum-form {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .sp-durum-form select {
        width: auto;
        min-width: 128px;
        padding: 6px 9px;
        font-size: 12.5px;
    }

    .sp-eylem {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
    }

    .sp-eylem form {
        display: inline;
    }

    .alert-uyari {
        border-left-color: var(--y-warning);
        background: color-mix(in srgb, var(--y-warning) 8%, var(--y-yuzey));
    }
</style>

<div class="page-header">
    <div>
        <h1>Siparişler</h1>
        <p>
            <?= number_format($toplam, 0, ',', '.') ?> kayıt<?= $arama !== '' || $suzgec !== '' ? ' (süzülmüş)' : '' ?>
            <?php if ($bekleyenCiro > 0): ?>
                &middot; bekleyen tutar <?= number_format($bekleyenCiro, 2, ',', '.') ?> ₺
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if ($mesaj): ?>
    <?php
    $sinif = match ($mesaj['tip']) {
        'success' => 'alert-success',
        'uyari' => 'alert-uyari',
        default => 'alert-error',
    };
    $ikon = match ($mesaj['tip']) {
        'success' => 'fa-circle-check',
        'uyari' => 'fa-triangle-exclamation',
        default => 'fa-circle-exclamation',
    };
    ?>
    <div class="alert <?= $sinif ?>">
        <i class="fas <?= $ikon ?> alert-icon"></i>
        <span><?= htmlspecialchars((string) $mesaj['metin']) ?></span>
    </div>
<?php endif; ?>

<!-- Özet -->
<div class="sp-ozet">
    <?php foreach ($ozet as [$ad, $deger, $ikon, $filtre]): ?>
        <a class="sp-ozet-kart <?= $filtre !== '' && $suzgec === $filtre ? 'secili' : '' ?>"
            href="orders.php<?= $filtre !== '' ? '?status=' . $filtre : '' ?>">
            <span class="sp-ozet-ikon"><i class="fas <?= $ikon ?>"></i></span>
            <span>
                <b><?= number_format($deger, 0, ',', '.') ?></b>
                <span><?= $ad ?></span>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Arama -->
<form class="sp-arac" method="get">
    <?php if ($suzgec !== ''): ?>
        <input type="hidden" name="status" value="<?= htmlspecialchars($suzgec) ?>">
    <?php endif; ?>
    <div class="sp-ara">
        <i class="fas fa-magnifying-glass"></i>
        <input type="search" name="q" value="<?= htmlspecialchars($arama) ?>"
            placeholder="Sipariş numarası, müşteri adı veya e-posta…">
    </div>
    <div style="display:flex; gap:8px;">
        <button type="submit" class="btn btn-outline"><i class="fas fa-filter"></i> Ara</button>
        <?php if ($arama !== '' || $suzgec !== ''): ?>
            <a href="orders.php" class="btn btn-outline"><i class="fas fa-xmark"></i> Sıfırla</a>
        <?php endif; ?>
    </div>
</form>

<!-- Liste -->
<div class="sp-sarmal">
    <?php if (!$siparisler): ?>
        <div class="empty-state">
            <i class="fas fa-cart-shopping"></i>
            <h3><?= $arama !== '' || $suzgec !== '' ? 'Eşleşen sipariş yok' : 'Henüz sipariş yok' ?></h3>
            <p><?= $arama !== '' || $suzgec !== ''
                ? 'Aramayı değiştirin ya da süzgeci sıfırlayın.'
                : 'Mağazadan sipariş verildiğinde burada listelenir.' ?></p>
        </div>
    <?php else: ?>
        <table class="sp-tablo">
            <thead>
                <tr>
                    <th>Sipariş</th>
                    <th>Müşteri</th>
                    <th class="sp-sag">Tutar</th>
                    <th>Ödeme</th>
                    <th>Durum</th>
                    <th class="sp-sag">İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($siparisler as $s):
                    $id = (int) $s['id'];
                    $no = (string) ($s['order_number'] ?: $id);
                    $ad = trim((string) ($s['first_name'] ?? '') . ' ' . (string) ($s['last_name'] ?? ''));
                    [$durumAd, $durumSinif] = $durumlar[$s['status']] ?? [(string) $s['status'], 'badge'];
                    $hizmet = (int) $s['hizmet'];
                    $uyari = $hizmet > 0
                        ? 'Sipariş #' . $no . ' silinecek. Bu siparişten oluşan ' . $hizmet
                        . ' hizmet silinmez ama hangi siparişten geldiği bilgisi kaybolur. Devam edilsin mi?'
                        : 'Sipariş #' . $no . ' ve kalemleri silinecek. Devam edilsin mi?';
                    ?>
                    <tr>
                        <td class="sp-no">
                            <b>#<?= htmlspecialchars($no) ?></b>
                            <span>
                                <?= date('d.m.Y H:i', strtotime((string) $s['created_at'])) ?>
                                &middot; <?= (int) $s['kalem'] ?> kalem
                                <?php if ($hizmet > 0): ?>
                                    &middot; <?= $hizmet ?> hizmet
                                <?php endif; ?>
                            </span>
                        </td>
                        <td class="sp-musteri">
                            <?php if ($ad !== ''): ?>
                                <b><?= htmlspecialchars($ad) ?></b>
                                <a href="client-view.php?id=<?= (int) $s['client_id'] ?>" dir="ltr">
                                    <?= htmlspecialchars((string) ($s['email'] ?? '')) ?>
                                </a>
                            <?php else: ?>
                                <b style="color:var(--y-metin-3)">Müşteri silinmiş</b>
                            <?php endif; ?>
                        </td>
                        <td class="sp-sag">
                            <span class="sp-tutar"><?= number_format((float) $s['total'], 2, ',', '.') ?> ₺</span>
                            <?php if ((float) ($s['discount'] ?? 0) > 0): ?>
                                <span class="sp-etiket">−<?= number_format((float) $s['discount'], 0, ',', '.') ?> ₺</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--y-metin-3); font-size:12.5px;">
                            <?= htmlspecialchars((string) ($s['payment_method'] ?: '—')) ?>
                        </td>
                        <td>
                            <form method="post" class="sp-durum-form">
                                <?= Guvenlik::alan() ?>
                                <input type="hidden" name="durum_degistir" value="1">
                                <input type="hidden" name="order_id" value="<?= $id ?>">
                                <select name="new_status" onchange="this.form.submit()">
                                    <?php foreach ($durumlar as $kod => [$etiket, , ]): ?>
                                        <option value="<?= $kod ?>" <?= $s['status'] === $kod ? 'selected' : '' ?>>
                                            <?= $etiket ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="badge <?= $durumSinif ?>"><?= $durumAd ?></span>
                            </form>
                        </td>
                        <td class="sp-sag">
                            <div class="sp-eylem">
                                <a class="action-btn" href="order-view.php?id=<?= $id ?>" title="Görüntüle">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <form method="post"
                                    onsubmit="return confirm(<?= htmlspecialchars(json_encode($uyari, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>);">
                                    <?= Guvenlik::alan() ?>
                                    <input type="hidden" name="siparis_sil" value="1">
                                    <input type="hidden" name="order_id" value="<?= $id ?>">
                                    <button type="submit" class="action-btn" title="Sil">
                                        <i class="fas fa-trash-can"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if ($sayfaSayisi > 1): ?>
    <?php
    $bag = static function (int $s) use ($arama, $suzgec): string {
        $p = ['page' => $s];
        if ($arama !== '') {
            $p['q'] = $arama;
        }
        if ($suzgec !== '') {
            $p['status'] = $suzgec;
        }
        return 'orders.php?' . http_build_query($p);
    };
    ?>
    <div class="pagination">
        <?php if ($sayfa > 1): ?>
            <a href="<?= htmlspecialchars($bag($sayfa - 1)) ?>"><i class="fas fa-chevron-left"></i></a>
        <?php endif; ?>
        <?php for ($i = max(1, $sayfa - 2); $i <= min($sayfaSayisi, $sayfa + 2); $i++): ?>
            <?php if ($i === $sayfa): ?>
                <span class="active"><?= $i ?></span>
            <?php else: ?>
                <a href="<?= htmlspecialchars($bag($i)) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($sayfa < $sayfaSayisi): ?>
            <a href="<?= htmlspecialchars($bag($sayfa + 1)) ?>"><i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
