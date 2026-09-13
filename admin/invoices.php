<?php
/**
 * VHM - Faturalar
 *
 * Önceki sürümdeki sorunlar:
 *   - cancel_invoice, delete_invoice ve mark_paid POST ile çalışıyordu
 *     ama CSRF belirteci doğrulanmıyordu.
 *   - mark_paid'de fatura varlığı denetlenmiyordu; kayıt yoksa
 *     $invoice['total'] okunurken hata veriyordu.
 *   - Fatura ödendi işaretlendiğinde transactions tablosuna hiçbir kayıt
 *     düşmüyordu; projede bu tabloya INSERT yapan tek bir satır yoktu,
 *     bu yüzden Ödemeler sayfası hep boştu.
 *   - Fatura güncellemesi, komisyon, log ve e-posta ardışık çalışıyordu;
 *     ortada hata olursa yarım kalıyordu. Artık para işlemleri tek işlem.
 *   - PRG yoktu; yenilemede aynı işlem tekrar uygulanıyordu.
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

$pageTitle = 'Faturalar';
$currentPage = 'invoices';

/* invoices.status enum'u ile birebir aynı */
$durumlar = [
    'draft' => ['Taslak', 'badge'],
    'unpaid' => ['Ödenmedi', 'badge-warning'],
    'paid' => ['Ödendi', 'badge-success'],
    'cancelled' => ['İptal', 'badge'],
    'refunded' => ['İade', 'badge-info'],
    'collections' => ['Takipte', 'badge-danger'],
];

function faturaMesaj(string $tip, string $metin): void
{
    $_SESSION['fat_mesaj'] = ['tip' => $tip, 'metin' => $metin];
    header('Location: invoices.php');
    exit;
}

/* ---------- Ödendi işaretle ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['odendi'])) {
    Guvenlik::zorunlu();
    $id = (int) ($_POST['invoice_id'] ?? 0);

    $fatura = Database::fetch(
        "SELECT i.*, c.first_name, c.last_name, c.email
           FROM invoices i
           LEFT JOIN clients c ON c.id = i.client_id
          WHERE i.id = ?",
        [$id]
    );

    /* Kayıt yoksa eskiden $invoice['total'] okunurken hata veriyordu */
    if (!$fatura) {
        faturaMesaj('error', 'Fatura bulunamadı.');
    }
    if ($fatura['status'] === 'paid') {
        faturaMesaj('uyari', 'Bu fatura zaten ödenmiş görünüyor.');
    }

    $yontem = (string) ($_POST['yontem'] ?? $fatura['payment_method'] ?? 'bank_transfer');
    $tutar = (float) $fatura['total'];
    $db = Database::getInstance();

    try {
        $db->beginTransaction();

        Database::query(
            "UPDATE invoices SET status = 'paid', amount_paid = ?, paid_date = NOW(),
                    payment_method = ? WHERE id = ?",
            [$tutar, $yontem, $id]
        );

        /* Ödeme kaydı. Bu satır olmadığı için Ödemeler sayfası hiç
           veri görmüyordu. */
        Database::insert('transactions', [
            'client_id' => (int) $fatura['client_id'],
            'invoice_id' => $id,
            'transaction_id' => 'MAN-' . $id . '-' . date('YmdHis'),
            'gateway' => $yontem,
            'type' => 'payment',
            'amount' => $tutar,
            'currency' => (string) ($fatura['currency'] ?: 'TRY'),
            'status' => 'success',
            'description' => 'Fatura ' . $fatura['invoice_number'] . ' panelden ödendi olarak işaretlendi.',
        ]);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Fatura ödendi işaretlenemedi: ' . $e->getMessage());
        faturaMesaj('error', 'Fatura güncellenemedi, hiçbir değişiklik kaydedilmedi.');
    }

    /* Aşağıdakiler para işlemi değil; biri patlarsa fatura yine ödenmiş kalır */
    try {
        require_once dirname(__DIR__) . '/includes/Affiliate.php';
        Affiliate::processCommission((int) $fatura['client_id'], null, $id, $tutar);
    } catch (Throwable $e) {
        error_log('Satış ortaklığı komisyonu işlenemedi: ' . $e->getMessage());
    }

    try {
        require_once dirname(__DIR__) . '/includes/ClientLog.php';
        ClientLog::invoicePaid(
            (int) $fatura['client_id'],
            (string) $fatura['invoice_number'],
            $tutar,
            (string) ($fatura['currency'] ?? 'TRY')
        );
    } catch (Throwable $e) {
        error_log('Müşteri logu yazılamadı: ' . $e->getMessage());
    }

    if (!empty($fatura['email'])) {
        try {
            require_once dirname(__DIR__) . '/includes/Mail.php';
            Mail::sendTemplate('invoice_paid', (string) $fatura['email'], [
                'client_name' => trim((string) $fatura['first_name'] . ' ' . (string) $fatura['last_name']),
                'invoice_id' => (string) $fatura['invoice_number'],
                'payment_amount' => number_format($tutar, 2, ',', '.'),
                'payment_date' => date('d.m.Y H:i'),
            ], (string) $fatura['first_name']);
        } catch (Throwable $e) {
            error_log('Ödeme bildirimi gönderilemedi: ' . $e->getMessage());
        }
    }

    faturaMesaj('success', $fatura['invoice_number'] . ' ödendi olarak işaretlendi ve ödeme kaydı oluşturuldu.');
}

/* ---------- İptal ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['iptal'])) {
    Guvenlik::zorunlu();
    $id = (int) ($_POST['invoice_id'] ?? 0);

    try {
        Database::query("UPDATE invoices SET status = 'cancelled' WHERE id = ?", [$id]);
        faturaMesaj('uyari', 'Fatura iptal edildi.');
    } catch (Throwable $e) {
        error_log('Fatura iptal edilemedi: ' . $e->getMessage());
        faturaMesaj('error', 'Fatura iptal edilemedi.');
    }
}

/* ---------- Silme ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fatura_sil'])) {
    Guvenlik::zorunlu();
    $id = (int) ($_POST['invoice_id'] ?? 0);

    try {
        /* invoice_items zaten ON DELETE CASCADE; elle silmeye gerek yok */
        Database::query("DELETE FROM invoices WHERE id = ?", [$id]);
        faturaMesaj('success', 'Fatura silindi.');
    } catch (Throwable $e) {
        error_log('Fatura silinemedi: ' . $e->getMessage());
        faturaMesaj('error', 'Fatura silinemedi.');
    }
}

$mesaj = null;
if (!empty($_SESSION['fat_mesaj'])) {
    $mesaj = $_SESSION['fat_mesaj'];
    unset($_SESSION['fat_mesaj']);
}

/* ---------- Süzme ---------- */
$suzgec = (string) ($_GET['status'] ?? '');
$arama = trim((string) ($_GET['q'] ?? $_GET['search'] ?? ''));

$kosul = [];
$par = [];
if (isset($durumlar[$suzgec])) {
    $kosul[] = "i.status = ?";
    $par[] = $suzgec;
} elseif ($suzgec === 'gecikmis') {
    $kosul[] = "i.status = 'unpaid' AND i.due_date < CURDATE()";
}
if ($arama !== '') {
    $kosul[] = "(i.invoice_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)";
    $desen = '%' . str_replace(['%', '_'], ['\%', '\_'], $arama) . '%';
    $par = array_merge($par, array_fill(0, 4, $desen));
}
$nerede = $kosul ? ' WHERE ' . implode(' AND ', $kosul) : '';

$sayfa = max(1, (int) ($_GET['page'] ?? 1));
$adet = 20;
$atla = ($sayfa - 1) * $adet;

$guvenliSayi = static function (string $sql, array $p = []): float {
    try {
        return (float) Database::fetchColumn($sql, $p);
    } catch (Throwable $e) {
        error_log('Fatura sorgusu: ' . $e->getMessage());
        return 0;
    }
};

$toplam = (int) $guvenliSayi(
    "SELECT COUNT(*) FROM invoices i LEFT JOIN clients c ON c.id = i.client_id" . $nerede,
    $par
);
$sayfaSayisi = max(1, (int) ceil($toplam / $adet));

try {
    $faturalar = Database::fetchAll(
        "SELECT i.*, c.first_name, c.last_name, c.email
           FROM invoices i
           LEFT JOIN clients c ON c.id = i.client_id
           {$nerede}
          ORDER BY i.created_at DESC
          LIMIT {$adet} OFFSET {$atla}",
        $par
    );
} catch (Throwable $e) {
    error_log('Fatura listesi okunamadı: ' . $e->getMessage());
    $faturalar = [];
}

$ozet = [
    ['Tüm faturalar', $guvenliSayi("SELECT COUNT(*) FROM invoices"), 'fa-file-invoice', '', false],
    ['Ödenmedi', $guvenliSayi("SELECT COUNT(*) FROM invoices WHERE status = 'unpaid'"), 'fa-clock', 'unpaid', false],
    ['Gecikmiş', $guvenliSayi("SELECT COUNT(*) FROM invoices WHERE status = 'unpaid' AND due_date < CURDATE()"), 'fa-triangle-exclamation', 'gecikmis', false],
    ['Açık alacak', $guvenliSayi("SELECT COALESCE(SUM(total - amount_paid),0) FROM invoices WHERE status = 'unpaid'"), 'fa-turkish-lira-sign', 'unpaid', true],
];

$odemeYontemi = [
    'bank_transfer' => 'Havale / EFT',
    'paytr' => 'PayTR',
    'credit_card' => 'Kredi kartı',
    'cash' => 'Nakit',
    'credit' => 'Bakiye',
];

require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* ==========================================
       Faturalar - fa
       ========================================== */
    .fa-ozet {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }

    .fa-ozet-kart {
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

    .fa-ozet-kart:hover {
        border-color: var(--y-primary);
        text-decoration: none;
    }

    .fa-ozet-kart.secili {
        border-color: var(--y-primary);
        background: var(--y-primary-soft);
    }

    .fa-ozet-ikon {
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

    .fa-ozet-kart b {
        display: block;
        font-size: 21px;
        font-weight: 700;
        letter-spacing: -.02em;
        color: var(--y-metin);
        line-height: 1.2;
    }

    .fa-ozet-kart span {
        font-size: 12.5px;
        color: var(--y-metin-3);
    }

    .fa-arac {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }

    .fa-ara {
        position: relative;
        flex: 1;
        min-width: 220px;
        max-width: 420px;
    }

    .fa-ara i {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 13px;
        color: var(--y-metin-3);
        pointer-events: none;
    }

    .fa-ara input {
        width: 100%;
        padding-left: 36px;
    }

    .fa-sarmal {
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow-x: auto;
    }

    .fa-tablo {
        width: 100%;
        border-collapse: collapse;
        font-size: 13.5px;
        min-width: 900px;
    }

    .fa-tablo th {
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

    .fa-tablo td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--y-cizgi-soft);
        vertical-align: middle;
    }

    .fa-tablo tr:last-child td {
        border-bottom: none;
    }

    .fa-tablo tbody tr:hover td {
        background: var(--y-yuzey-2);
    }

    .fa-no b {
        font-weight: 700;
        color: var(--y-metin);
    }

    .fa-no span {
        display: block;
        margin-top: 2px;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .fa-musteri b {
        display: block;
        font-weight: 600;
        color: var(--y-metin);
    }

    .fa-musteri a {
        font-size: 12px;
        color: var(--y-metin-3);
    }

    .fa-musteri a:hover {
        color: var(--y-primary);
    }

    .fa-sag {
        text-align: right;
        white-space: nowrap;
    }

    .fa-tutar {
        font-weight: 700;
        color: var(--y-metin);
    }

    .fa-kalan {
        display: block;
        margin-top: 2px;
        font-size: 11.5px;
        color: var(--y-danger);
    }

    .fa-gecikmis {
        display: inline-block;
        margin-left: 5px;
        padding: 1px 7px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--y-danger) 13%, transparent);
        color: var(--y-danger);
        font-size: 11px;
        font-weight: 700;
    }

    .fa-eylem {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
    }

    .fa-eylem form {
        display: inline;
    }

    .alert-uyari {
        border-left-color: var(--y-warning);
        background: color-mix(in srgb, var(--y-warning) 8%, var(--y-yuzey));
    }
</style>

<div class="page-header">
    <div>
        <h1>Faturalar</h1>
        <p><?= number_format($toplam, 0, ',', '.') ?> kayıt<?= $arama !== '' || $suzgec !== '' ? ' (süzülmüş)' : '' ?></p>
    </div>
    <?php if (is_file(__DIR__ . '/invoice-create.php')): ?>
        <a href="invoice-create.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Yeni fatura
        </a>
    <?php endif; ?>
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

<div class="fa-ozet">
    <?php foreach ($ozet as [$ad, $deger, $ikon, $filtre, $paraMi]): ?>
        <a class="fa-ozet-kart <?= $filtre !== '' && $suzgec === $filtre ? 'secili' : '' ?>"
            href="invoices.php<?= $filtre !== '' ? '?status=' . $filtre : '' ?>">
            <span class="fa-ozet-ikon"><i class="fas <?= $ikon ?>"></i></span>
            <span>
                <b><?= $paraMi
                    ? number_format($deger, 2, ',', '.') . ' ₺'
                    : number_format($deger, 0, ',', '.') ?></b>
                <span><?= $ad ?></span>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<form class="fa-arac" method="get">
    <?php if ($suzgec !== ''): ?>
        <input type="hidden" name="status" value="<?= htmlspecialchars($suzgec) ?>">
    <?php endif; ?>
    <div class="fa-ara">
        <i class="fas fa-magnifying-glass"></i>
        <input type="search" name="q" value="<?= htmlspecialchars($arama) ?>"
            placeholder="Fatura numarası, müşteri adı veya e-posta…">
    </div>
    <div style="display:flex; gap:8px;">
        <button type="submit" class="btn btn-outline"><i class="fas fa-filter"></i> Ara</button>
        <?php if ($arama !== '' || $suzgec !== ''): ?>
            <a href="invoices.php" class="btn btn-outline"><i class="fas fa-xmark"></i> Sıfırla</a>
        <?php endif; ?>
    </div>
</form>

<div class="fa-sarmal">
    <?php if (!$faturalar): ?>
        <div class="empty-state">
            <i class="fas fa-file-invoice"></i>
            <h3><?= $arama !== '' || $suzgec !== '' ? 'Eşleşen fatura yok' : 'Henüz fatura yok' ?></h3>
            <p><?= $arama !== '' || $suzgec !== ''
                ? 'Aramayı değiştirin ya da süzgeci sıfırlayın.'
                : 'Sipariş verildiğinde ya da elle oluşturduğunuzda burada listelenir.' ?></p>
        </div>
    <?php else: ?>
        <table class="fa-tablo">
            <thead>
                <tr>
                    <th>Fatura</th>
                    <th>Müşteri</th>
                    <th class="fa-sag">Tutar</th>
                    <th>Vade</th>
                    <th>Durum</th>
                    <th class="fa-sag">İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($faturalar as $f):
                    $id = (int) $f['id'];
                    $no = (string) ($f['invoice_number'] ?: $id);
                    $ad = trim((string) ($f['first_name'] ?? '') . ' ' . (string) ($f['last_name'] ?? ''));
                    [$durumAd, $durumSinif] = $durumlar[$f['status']] ?? [(string) $f['status'], 'badge'];
                    $kalan = (float) $f['total'] - (float) $f['amount_paid'];
                    $gecikmis = $f['status'] === 'unpaid' && !empty($f['due_date'])
                        && strtotime((string) $f['due_date']) < strtotime(date('Y-m-d'));
                    $gun = $gecikmis
                        ? (int) floor((strtotime(date('Y-m-d')) - strtotime((string) $f['due_date'])) / 86400)
                        : 0;
                    ?>
                    <tr>
                        <td class="fa-no">
                            <b><?= htmlspecialchars($no) ?></b>
                            <span><?= date('d.m.Y', strtotime((string) $f['created_at'])) ?>
                                <?php if (!empty($f['payment_method'])): ?>
                                    &middot; <?= htmlspecialchars($odemeYontemi[$f['payment_method']] ?? (string) $f['payment_method']) ?>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td class="fa-musteri">
                            <?php if ($ad !== ''): ?>
                                <b><?= htmlspecialchars($ad) ?></b>
                                <a href="client-view.php?id=<?= (int) $f['client_id'] ?>" dir="ltr">
                                    <?= htmlspecialchars((string) ($f['email'] ?? '')) ?>
                                </a>
                            <?php else: ?>
                                <b style="color:var(--y-metin-3)">Müşteri silinmiş</b>
                            <?php endif; ?>
                        </td>
                        <td class="fa-sag">
                            <span class="fa-tutar"><?= number_format((float) $f['total'], 2, ',', '.') ?> ₺</span>
                            <?php if ($kalan > 0 && $f['status'] !== 'cancelled'): ?>
                                <span class="fa-kalan"><?= number_format($kalan, 2, ',', '.') ?> ₺ kalan</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12.5px; color:var(--y-metin-3);">
                            <?= !empty($f['due_date']) ? date('d.m.Y', strtotime((string) $f['due_date'])) : '—' ?>
                            <?php if ($gecikmis): ?>
                                <span class="fa-gecikmis"><?= $gun ?> gün</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $durumSinif ?>"><?= $durumAd ?></span></td>
                        <td class="fa-sag">
                            <div class="fa-eylem">
                                <a class="action-btn" href="invoice-view.php?id=<?= $id ?>" title="Görüntüle">
                                    <i class="fas fa-eye"></i>
                                </a>

                                <?php if ($f['status'] === 'unpaid' || $f['status'] === 'draft'): ?>
                                    <form method="post"
                                        onsubmit="return confirm(<?= htmlspecialchars(json_encode(
                                            $no . ' ödendi olarak işaretlenecek ve '
                                            . number_format((float) $f['total'], 2, ',', '.')
                                            . ' ₺ tutarında ödeme kaydı oluşturulacak. Onaylıyor musunuz?',
                                            JSON_UNESCAPED_UNICODE
                                        ), ENT_QUOTES) ?>);">
                                        <?= Guvenlik::alan() ?>
                                        <input type="hidden" name="odendi" value="1">
                                        <input type="hidden" name="invoice_id" value="<?= $id ?>">
                                        <button type="submit" class="action-btn" title="Ödendi işaretle">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>

                                    <form method="post"
                                        onsubmit="return confirm('<?= htmlspecialchars($no) ?> iptal edilecek. Devam edilsin mi?');">
                                        <?= Guvenlik::alan() ?>
                                        <input type="hidden" name="iptal" value="1">
                                        <input type="hidden" name="invoice_id" value="<?= $id ?>">
                                        <button type="submit" class="action-btn" title="İptal et">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form method="post"
                                    onsubmit="return confirm(<?= htmlspecialchars(json_encode(
                                        $no . ' ve kalemleri kalıcı olarak silinecek. Bu faturaya bağlı ödeme '
                                        . 'kayıtları silinmez ama fatura bağı kopar. Devam edilsin mi?',
                                        JSON_UNESCAPED_UNICODE
                                    ), ENT_QUOTES) ?>);">
                                    <?= Guvenlik::alan() ?>
                                    <input type="hidden" name="fatura_sil" value="1">
                                    <input type="hidden" name="invoice_id" value="<?= $id ?>">
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
        return 'invoices.php?' . http_build_query($p);
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
