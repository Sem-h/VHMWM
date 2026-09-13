<?php
/**
 * VHM - Ödemeler
 *
 * Bu sayfa uzun süre boş kaldı çünkü projede transactions tablosuna
 * INSERT yapan tek bir satır yoktu. Kayıt artık fatura "Ödendi"
 * işaretlendiğinde invoices.php tarafından oluşturuluyor.
 *
 * Önceki sürümde arama, süzgeç, özet ve durum gösterimi yoktu;
 * yalnızca düz bir liste vardı.
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

$pageTitle = 'Ödemeler';
$currentPage = 'transactions';

/* transactions tablosundaki enum'larla birebir aynı */
$durumlar = [
    'success' => ['Başarılı', 'badge-success'],
    'pending' => ['Bekliyor', 'badge-warning'],
    'failed' => ['Başarısız', 'badge-danger'],
    'refunded' => ['İade edildi', 'badge-info'],
];

$turler = [
    'payment' => ['Tahsilat', 'fa-arrow-down', 'gelir'],
    'refund' => ['İade', 'fa-arrow-up', 'gider'],
    'credit' => ['Bakiye yükleme', 'fa-wallet', 'gelir'],
];

$gecitAdi = [
    'bank_transfer' => 'Havale / EFT',
    'paytr' => 'PayTR',
    'credit_card' => 'Kredi kartı',
    'cash' => 'Nakit',
    'credit' => 'Bakiye',
];

/* ---------- Süzme ---------- */
$suzgec = (string) ($_GET['durum'] ?? '');
$tur = (string) ($_GET['tur'] ?? '');
$arama = trim((string) ($_GET['q'] ?? ''));

$kosul = [];
$par = [];
if (isset($durumlar[$suzgec])) {
    $kosul[] = "t.status = ?";
    $par[] = $suzgec;
}
if (isset($turler[$tur])) {
    $kosul[] = "t.type = ?";
    $par[] = $tur;
}
if ($arama !== '') {
    $kosul[] = "(t.transaction_id LIKE ? OR i.invoice_number LIKE ? OR c.first_name LIKE ?"
        . " OR c.last_name LIKE ? OR c.email LIKE ?)";
    $desen = '%' . str_replace(['%', '_'], ['\%', '\_'], $arama) . '%';
    $par = array_merge($par, array_fill(0, 5, $desen));
}
$nerede = $kosul ? ' WHERE ' . implode(' AND ', $kosul) : '';

$sayfa = max(1, (int) ($_GET['page'] ?? 1));
$adet = 25;
$atla = ($sayfa - 1) * $adet;

$guvenli = static function (string $sql, array $p = []): float {
    try {
        return (float) Database::fetchColumn($sql, $p);
    } catch (Throwable $e) {
        error_log('Ödeme sorgusu: ' . $e->getMessage());
        return 0;
    }
};

$birlesim = "FROM transactions t
             LEFT JOIN clients c ON c.id = t.client_id
             LEFT JOIN invoices i ON i.id = t.invoice_id";

$toplam = (int) $guvenli("SELECT COUNT(*) {$birlesim}{$nerede}", $par);
$sayfaSayisi = max(1, (int) ceil($toplam / $adet));

try {
    $odemeler = Database::fetchAll(
        "SELECT t.*, c.first_name, c.last_name, c.email, i.invoice_number
         {$birlesim}{$nerede}
         ORDER BY t.created_at DESC
         LIMIT {$adet} OFFSET {$atla}",
        $par
    );
} catch (Throwable $e) {
    error_log('Ödeme listesi okunamadı: ' . $e->getMessage());
    $odemeler = [];
}

/* ---------- Özet ---------- */
$buAy = $guvenli(
    "SELECT COALESCE(SUM(amount),0) FROM transactions
      WHERE type = 'payment' AND status = 'success'
        AND created_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')"
);
$iadeToplam = $guvenli(
    "SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type = 'refund' AND status = 'success'"
);

$ozet = [
    ['Bu ayki tahsilat', $buAy, 'fa-turkish-lira-sign', '', true],
    ['Başarılı işlem', $guvenli("SELECT COUNT(*) FROM transactions WHERE status = 'success'"), 'fa-circle-check', 'success', false],
    ['Bekleyen', $guvenli("SELECT COUNT(*) FROM transactions WHERE status = 'pending'"), 'fa-clock', 'pending', false],
    ['Toplam iade', $iadeToplam, 'fa-rotate-left', '', true],
];

/* Ödeme yöntemine göre dağılım - yalnızca kayıt varsa gösterilir */
try {
    $dagilim = Database::fetchAll(
        "SELECT gateway, COUNT(*) AS adet, COALESCE(SUM(amount),0) AS tutar
           FROM transactions
          WHERE type = 'payment' AND status = 'success'
          GROUP BY gateway
          ORDER BY tutar DESC"
    );
} catch (Throwable $e) {
    error_log('Ödeme dağılımı okunamadı: ' . $e->getMessage());
    $dagilim = [];
}
$dagilimToplam = array_sum(array_map(static fn(array $d): float => (float) $d['tutar'], $dagilim));

require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* ==========================================
       Ödemeler - od
       ========================================== */
    .od-ozet {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }

    .od-ozet-kart {
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

    .od-ozet-kart:hover {
        border-color: var(--y-primary);
        text-decoration: none;
    }

    .od-ozet-kart.secili {
        border-color: var(--y-primary);
        background: var(--y-primary-soft);
    }

    .od-ozet-ikon {
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

    .od-ozet-kart b {
        display: block;
        font-size: 20px;
        font-weight: 700;
        letter-spacing: -.02em;
        color: var(--y-metin);
        line-height: 1.2;
    }

    .od-ozet-kart span {
        font-size: 12.5px;
        color: var(--y-metin-3);
    }

    /* Yöntem dağılımı */
    .od-dagilim {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 18px;
    }

    .od-yontem {
        flex: 1;
        min-width: 190px;
        padding: 13px 16px;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
    }

    .od-yontem-bas {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 8px;
    }

    .od-yontem-bas b {
        font-size: 13px;
        font-weight: 600;
        color: var(--y-metin);
    }

    .od-yontem-bas span {
        font-size: 12px;
        color: var(--y-metin-3);
    }

    .od-cubuk {
        height: 6px;
        border-radius: 999px;
        background: var(--y-cizgi-soft);
        overflow: hidden;
    }

    .od-cubuk i {
        display: block;
        height: 100%;
        border-radius: 999px;
        background: var(--y-primary);
    }

    .od-yontem-tutar {
        margin-top: 7px;
        font-size: 15px;
        font-weight: 700;
        color: var(--y-metin);
    }

    /* Araç çubuğu */
    .od-arac {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }

    .od-ara {
        position: relative;
        flex: 1;
        min-width: 220px;
        max-width: 420px;
    }

    .od-ara i {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 13px;
        color: var(--y-metin-3);
        pointer-events: none;
    }

    .od-ara input {
        width: 100%;
        padding-left: 36px;
    }

    .od-tur {
        display: flex;
        gap: 6px;
    }

    .od-tur a {
        padding: 8px 14px;
        border: 1px solid var(--y-cizgi);
        border-radius: 999px;
        background: var(--y-yuzey);
        font-size: 12.5px;
        font-weight: 600;
        color: var(--y-metin-2);
    }

    .od-tur a:hover {
        border-color: var(--y-primary);
        color: var(--y-primary);
        text-decoration: none;
    }

    .od-tur a.secili {
        border-color: var(--y-primary);
        background: var(--y-primary-soft);
        color: var(--y-primary);
    }

    /* Tablo */
    .od-sarmal {
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow-x: auto;
    }

    .od-tablo {
        width: 100%;
        border-collapse: collapse;
        font-size: 13.5px;
        min-width: 900px;
    }

    .od-tablo th {
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

    .od-tablo td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--y-cizgi-soft);
        vertical-align: middle;
    }

    .od-tablo tr:last-child td {
        border-bottom: none;
    }

    .od-tablo tbody tr:hover td {
        background: var(--y-yuzey-2);
    }

    .od-tur-ikon {
        width: 30px;
        height: 30px;
        display: grid;
        place-items: center;
        border-radius: 8px;
        font-size: 12px;
    }

    .od-tur-ikon.gelir {
        background: color-mix(in srgb, var(--y-success) 14%, transparent);
        color: var(--y-success);
    }

    .od-tur-ikon.gider {
        background: color-mix(in srgb, var(--y-danger) 13%, transparent);
        color: var(--y-danger);
    }

    .od-kod {
        font-family: ui-monospace, Consolas, monospace;
        font-size: 12px;
        color: var(--y-metin-2);
    }

    .od-kod span {
        display: block;
        margin-top: 2px;
        font-family: inherit;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .od-musteri b {
        display: block;
        font-weight: 600;
        color: var(--y-metin);
    }

    .od-musteri a {
        font-size: 12px;
        color: var(--y-metin-3);
    }

    .od-musteri a:hover {
        color: var(--y-primary);
    }

    .od-sag {
        text-align: right;
        white-space: nowrap;
    }

    .od-tutar {
        font-weight: 700;
        color: var(--y-metin);
    }

    .od-tutar.gider {
        color: var(--y-danger);
    }
</style>

<div class="page-header">
    <div>
        <h1>Ödemeler</h1>
        <p><?= number_format($toplam, 0, ',', '.') ?> kayıt<?= $arama !== '' || $suzgec !== '' || $tur !== '' ? ' (süzülmüş)' : '' ?></p>
    </div>
</div>

<div class="od-ozet">
    <?php foreach ($ozet as [$ad, $deger, $ikon, $filtre, $paraMi]): ?>
        <a class="od-ozet-kart <?= $filtre !== '' && $suzgec === $filtre ? 'secili' : '' ?>"
            href="transactions.php<?= $filtre !== '' ? '?durum=' . $filtre : '' ?>">
            <span class="od-ozet-ikon"><i class="fas <?= $ikon ?>"></i></span>
            <span>
                <b><?= $paraMi
                    ? number_format($deger, 2, ',', '.') . ' ₺'
                    : number_format($deger, 0, ',', '.') ?></b>
                <span><?= $ad ?></span>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($dagilim && $dagilimToplam > 0): ?>
    <div class="od-dagilim">
        <?php foreach ($dagilim as $d): ?>
            <?php $oran = $dagilimToplam > 0 ? ((float) $d['tutar'] / $dagilimToplam) * 100 : 0; ?>
            <div class="od-yontem">
                <div class="od-yontem-bas">
                    <b><?= htmlspecialchars($gecitAdi[$d['gateway']] ?? (string) ($d['gateway'] ?: 'Belirtilmemiş')) ?></b>
                    <span><?= (int) $d['adet'] ?> işlem &middot; %<?= number_format($oran, 0) ?></span>
                </div>
                <div class="od-cubuk"><i style="width: <?= max(2, (int) round($oran)) ?>%"></i></div>
                <div class="od-yontem-tutar"><?= number_format((float) $d['tutar'], 2, ',', '.') ?> ₺</div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="od-tur">
    <a href="transactions.php" class="<?= $tur === '' ? 'secili' : '' ?>">Tümü</a>
    <?php foreach ($turler as $kod => [$ad, , ]): ?>
        <a href="?tur=<?= $kod ?>" class="<?= $tur === $kod ? 'secili' : '' ?>"><?= $ad ?></a>
    <?php endforeach; ?>
</div>

<form class="od-arac" method="get" style="margin-top:14px;">
    <?php if ($suzgec !== ''): ?>
        <input type="hidden" name="durum" value="<?= htmlspecialchars($suzgec) ?>">
    <?php endif; ?>
    <?php if ($tur !== ''): ?>
        <input type="hidden" name="tur" value="<?= htmlspecialchars($tur) ?>">
    <?php endif; ?>
    <div class="od-ara">
        <i class="fas fa-magnifying-glass"></i>
        <input type="search" name="q" value="<?= htmlspecialchars($arama) ?>"
            placeholder="İşlem numarası, fatura, müşteri adı veya e-posta…">
    </div>
    <div style="display:flex; gap:8px;">
        <button type="submit" class="btn btn-outline"><i class="fas fa-filter"></i> Ara</button>
        <?php if ($arama !== '' || $suzgec !== '' || $tur !== ''): ?>
            <a href="transactions.php" class="btn btn-outline"><i class="fas fa-xmark"></i> Sıfırla</a>
        <?php endif; ?>
    </div>
</form>

<div class="od-sarmal">
    <?php if (!$odemeler): ?>
        <div class="empty-state">
            <i class="fas fa-credit-card"></i>
            <h3><?= $arama !== '' || $suzgec !== '' || $tur !== '' ? 'Eşleşen ödeme yok' : 'Henüz ödeme kaydı yok' ?></h3>
            <p><?= $arama !== '' || $suzgec !== '' || $tur !== ''
                ? 'Aramayı değiştirin ya da süzgeci sıfırlayın.'
                : 'Bir fatura ödendi olarak işaretlendiğinde ya da çevrim içi tahsilat yapıldığında burada görünür.' ?></p>
            <?php if ($arama === '' && $suzgec === '' && $tur === ''): ?>
                <a href="invoices.php?status=unpaid" class="btn btn-primary" style="margin-top:14px;">
                    <i class="fas fa-file-invoice"></i> Ödenmemiş faturalar
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <table class="od-tablo">
            <thead>
                <tr>
                    <th></th>
                    <th>İşlem</th>
                    <th>Müşteri</th>
                    <th>Fatura</th>
                    <th>Yöntem</th>
                    <th class="od-sag">Tutar</th>
                    <th>Durum</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($odemeler as $o):
                    [$turAd, $turIkon, $turYon] = $turler[$o['type']] ?? ['Diğer', 'fa-circle', 'gelir'];
                    [$durumAd, $durumSinif] = $durumlar[$o['status']] ?? [(string) $o['status'], 'badge'];
                    $ad = trim((string) ($o['first_name'] ?? '') . ' ' . (string) ($o['last_name'] ?? ''));
                    ?>
                    <tr>
                        <td style="width:46px;">
                            <span class="od-tur-ikon <?= $turYon ?>" title="<?= $turAd ?>">
                                <i class="fas <?= $turIkon ?>"></i>
                            </span>
                        </td>
                        <td class="od-kod">
                            <?= htmlspecialchars((string) ($o['transaction_id'] ?: '#' . $o['id'])) ?>
                            <span><?= date('d.m.Y H:i', strtotime((string) $o['created_at'])) ?>
                                &middot; <?= $turAd ?></span>
                        </td>
                        <td class="od-musteri">
                            <?php if ($ad !== ''): ?>
                                <b><?= htmlspecialchars($ad) ?></b>
                                <a href="client-view.php?id=<?= (int) $o['client_id'] ?>" dir="ltr">
                                    <?= htmlspecialchars((string) ($o['email'] ?? '')) ?>
                                </a>
                            <?php else: ?>
                                <b style="color:var(--y-metin-3)">—</b>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($o['invoice_number'])): ?>
                                <a href="invoice-view.php?id=<?= (int) $o['invoice_id'] ?>">
                                    <?= htmlspecialchars((string) $o['invoice_number']) ?>
                                </a>
                            <?php else: ?>
                                <span style="color:var(--y-metin-3)">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12.5px; color:var(--y-metin-3);">
                            <?= htmlspecialchars($gecitAdi[$o['gateway']] ?? (string) ($o['gateway'] ?: '—')) ?>
                        </td>
                        <td class="od-sag">
                            <span class="od-tutar <?= $turYon === 'gider' ? 'gider' : '' ?>">
                                <?= $turYon === 'gider' ? '−' : '' ?><?= number_format((float) $o['amount'], 2, ',', '.') ?>
                                <?= htmlspecialchars((string) ($o['currency'] ?: 'TRY')) === 'TRY' ? '₺' : htmlspecialchars((string) $o['currency']) ?>
                            </span>
                        </td>
                        <td><span class="badge <?= $durumSinif ?>"><?= $durumAd ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if ($sayfaSayisi > 1): ?>
    <?php
    $bag = static function (int $s) use ($arama, $suzgec, $tur): string {
        $p = ['page' => $s];
        if ($arama !== '') {
            $p['q'] = $arama;
        }
        if ($suzgec !== '') {
            $p['durum'] = $suzgec;
        }
        if ($tur !== '') {
            $p['tur'] = $tur;
        }
        return 'transactions.php?' . http_build_query($p);
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
