<?php
/**
 * VHM - Fatura detayı
 *
 * Önceki sürümde "Ödendi işaretle" yalnızca invoices tablosunu
 * güncelliyor, transactions kaydını oluşturmuyordu; aynı işlem
 * invoices.php'de farklı yazılmıştı. İkisi de artık includes/Fatura.php
 * kullanıyor. Ayrıca POST sonrası yönlendirme yoktu: sayfa yenilenince
 * e-posta tekrar gidiyordu.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
require_once dirname(__DIR__) . '/includes/Fatura.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);

$fatura = $id > 0 ? Database::fetch(
    "SELECT i.*, c.first_name, c.last_name, c.email, c.phone,
            c.company_name, c.address, c.city, c.country, c.tax_office, c.tax_id
       FROM invoices i
       LEFT JOIN clients c ON c.id = i.client_id
      WHERE i.id = ?",
    [$id]
) : null;

if (!$fatura) {
    $_SESSION['fat_mesaj'] = ['tip' => 'error', 'metin' => 'Fatura bulunamadı.'];
    header('Location: invoices.php');
    exit;
}

/** Mesajı oturuma koyup aynı sayfaya döner (yenileme işlemi tekrarlamasın) */
function faturaDon(string $tip, string $metin, int $id): never
{
    $_SESSION['fd_mesaj'] = ['tip' => $tip, 'metin' => $metin];
    header('Location: invoice-view.php?id=' . $id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Guvenlik::zorunlu();
    $islem = (string) ($_POST['islem'] ?? '');

    try {
        if ($islem === 'odendi') {
            $yontem = (string) ($_POST['yontem'] ?? 'bank_transfer');
            Fatura::odendiIsaretle($id, $yontem);
            faturaDon('success', $fatura['invoice_number'] . ' ödendi olarak işaretlendi ve ödeme kaydı oluşturuldu.', $id);
        }

        if ($islem === 'iptal') {
            Fatura::iptalEt($id);
            faturaDon('uyari', 'Fatura iptal edildi.', $id);
        }

        if ($islem === 'eposta') {
            Fatura::epostaGonder($fatura);
            faturaDon('success', 'Fatura ' . $fatura['email'] . ' adresine gönderildi.', $id);
        }

        faturaDon('error', 'Tanımsız işlem.', $id);
    } catch (RuntimeException $e) {
        faturaDon('error', $e->getMessage(), $id);
    }
}

$mesaj = null;
if (!empty($_SESSION['fd_mesaj'])) {
    $mesaj = $_SESSION['fd_mesaj'];
    unset($_SESSION['fd_mesaj']);
}

$kalemler = Database::fetchAll(
    "SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id",
    [$id]
);

$odemeler = Database::fetchAll(
    "SELECT * FROM transactions WHERE invoice_id = ? ORDER BY created_at DESC",
    [$id]
);

$sirketAdi = Settings::get('company_name', defined('SITE_NAME') ? SITE_NAME : 'VHM');
$sirketAdres = (string) Settings::get('company_address', '');
$sirketEposta = (string) Settings::get('company_email', '');

$durumSinifi = [
    'draft' => 'badge', 'unpaid' => 'badge-warning', 'paid' => 'badge-success',
    'cancelled' => 'badge', 'refunded' => 'badge-info', 'collections' => 'badge-danger',
];

$paraBirimi = (string) ($fatura['currency'] ?: 'TRY');
$toplam = (float) $fatura['total'];
$odenen = (float) $fatura['amount_paid'];
$kalan = max(0, $toplam - $odenen);
$adSoyad = trim((string) $fatura['first_name'] . ' ' . (string) $fatura['last_name']);

/** 1.234,56 TRY */
function fvPara(float $t, string $b): string
{
    return number_format($t, 2, ',', '.') . ' ' . $b;
}

$pageTitle = 'Fatura ' . $fatura['invoice_number'];
$currentPage = 'invoices';
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* ==========================================
       Fatura detayı - fv
       ========================================== */
    .fv-duzen {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 300px;
        gap: 18px;
        align-items: start;
    }

    .fv-kagit {
        padding: 32px;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
    }

    .fv-kagit-ust {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 20px;
        padding-bottom: 22px;
        margin-bottom: 22px;
        border-bottom: 2px solid var(--y-cizgi);
    }

    .fv-no {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: var(--y-primary);
    }

    .fv-kagit-ust h2 {
        margin-top: 4px;
        font-size: 24px;
        font-weight: 700;
        letter-spacing: -.02em;
        color: var(--y-metin);
    }

    .fv-saglik {
        text-align: right;
        font-size: 12.5px;
        line-height: 1.6;
        color: var(--y-metin-3);
    }

    .fv-saglik b {
        display: block;
        font-size: 14px;
        color: var(--y-metin);
    }

    .fv-taraflar {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 22px;
        margin-bottom: 26px;
    }

    .fv-taraf h4 {
        margin-bottom: 7px;
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: var(--y-metin-3);
    }

    .fv-taraf p {
        font-size: 13px;
        line-height: 1.65;
        color: var(--y-metin-2);
    }

    .fv-taraf p strong {
        color: var(--y-metin);
    }

    .fv-tarih {
        display: flex;
        justify-content: space-between;
        gap: 14px;
        padding: 5px 0;
        font-size: 13px;
    }

    .fv-tarih span:first-child {
        color: var(--y-metin-3);
    }

    .fv-kalem {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .fv-kalem th {
        padding: 9px 10px;
        border-bottom: 1px solid var(--y-cizgi);
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--y-metin-3);
        text-align: left;
    }

    .fv-kalem td {
        padding: 11px 10px;
        border-bottom: 1px solid var(--y-cizgi-soft);
        color: var(--y-metin-2);
    }

    .fv-kalem tr:last-child td {
        border-bottom: none;
    }

    .fv-kalem .sag {
        text-align: right;
        white-space: nowrap;
    }

    .fv-kalem .orta {
        text-align: center;
    }

    .fv-toplam {
        display: flex;
        justify-content: flex-end;
        margin-top: 18px;
    }

    .fv-toplam-kutu {
        width: 100%;
        max-width: 300px;
    }

    .fv-toplam-satir {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        padding: 8px 0;
        font-size: 13px;
        color: var(--y-metin-2);
    }

    .fv-toplam-satir.cizgi {
        border-top: 1px solid var(--y-cizgi);
    }

    .fv-toplam-satir.buyuk {
        margin-top: 4px;
        padding-top: 12px;
        border-top: 2px solid var(--y-cizgi);
        font-size: 17px;
        font-weight: 700;
        color: var(--y-metin);
    }

    .fv-toplam-satir.buyuk span:last-child {
        color: var(--y-primary);
    }

    .fv-toplam-satir.borc {
        font-weight: 600;
        color: var(--y-danger);
    }

    .fv-panel {
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .fv-panel + .fv-panel,
    .fv-kagit + .fv-panel {
        margin-top: 18px;
    }

    .fv-panel-bas {
        padding: 12px 16px;
        border-bottom: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
        font-size: 12.5px;
        font-weight: 700;
    }

    .fv-panel-govde {
        padding: 16px;
    }

    .fv-islem {
        display: flex;
        flex-direction: column;
        gap: 9px;
    }

    .fv-islem form {
        margin: 0;
    }

    .fv-islem .btn {
        width: 100%;
        justify-content: center;
    }

    .fv-secim {
        margin-bottom: 9px;
    }

    .fv-secim label {
        display: block;
        margin-bottom: 4px;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .fv-musteri p {
        font-size: 13px;
        line-height: 1.6;
        color: var(--y-metin-2);
    }

    .fv-musteri p strong {
        color: var(--y-metin);
    }

    .fv-musteri a {
        color: var(--y-primary);
        word-break: break-all;
    }

    .fv-bos {
        padding: 22px 16px;
        text-align: center;
        font-size: 13px;
        color: var(--y-metin-3);
    }

    @media (max-width: 980px) {
        .fv-duzen {
            grid-template-columns: minmax(0, 1fr);
        }

        .fv-kagit {
            padding: 20px;
        }
    }

    @media print {

        .y-kenar,
        .y-ust,
        .page-header,
        .fv-duzen > div:last-child,
        .fv-panel {
            display: none !important;
        }

        .fv-duzen {
            grid-template-columns: 1fr;
        }

        .fv-kagit {
            border: none;
            box-shadow: none;
            padding: 0;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>Fatura <?= htmlspecialchars((string) $fatura['invoice_number']) ?></h1>
        <p><a href="invoices.php">Faturalar</a> &rsaquo; <?= htmlspecialchars($adSoyad !== '' ? $adSoyad : 'Müşteri silinmiş') ?></p>
    </div>
    <span class="badge <?= $durumSinifi[$fatura['status']] ?? 'badge' ?>" style="font-size:12.5px;padding:7px 14px">
        <?= Fatura::DURUMLAR[$fatura['status']] ?? htmlspecialchars((string) $fatura['status']) ?>
    </span>
</div>

<?php if ($mesaj): ?>
    <div class="alert alert-<?= htmlspecialchars($mesaj['tip']) ?>">
        <?= htmlspecialchars($mesaj['metin']) ?>
    </div>
<?php endif; ?>

<div class="fv-duzen">
    <div>
        <div class="fv-kagit">
            <div class="fv-kagit-ust">
                <div>
                    <span class="fv-no">Fatura</span>
                    <h2><?= htmlspecialchars((string) $fatura['invoice_number']) ?></h2>
                </div>
                <div class="fv-saglik">
                    <b><?= htmlspecialchars((string) $sirketAdi) ?></b>
                    <?php if ($sirketAdres !== ''): ?>
                        <?= nl2br(htmlspecialchars($sirketAdres)) ?><br>
                    <?php endif; ?>
                    <?= htmlspecialchars($sirketEposta) ?>
                </div>
            </div>

            <div class="fv-taraflar">
                <div class="fv-taraf">
                    <h4>Fatura adresi</h4>
                    <p>
                        <strong><?= htmlspecialchars($adSoyad !== '' ? $adSoyad : 'Müşteri silinmiş') ?></strong><br>
                        <?php if (!empty($fatura['company_name'])): ?>
                            <?= htmlspecialchars((string) $fatura['company_name']) ?><br>
                        <?php endif; ?>
                        <?php if (!empty($fatura['tax_office']) || !empty($fatura['tax_id'])): ?>
                            <?= htmlspecialchars(trim((string) ($fatura['tax_office'] ?? '') . ' ' . (string) ($fatura['tax_id'] ?? ''))) ?><br>
                        <?php endif; ?>
                        <?= htmlspecialchars((string) $fatura['email']) ?><br>
                        <?php if (!empty($fatura['address'])): ?>
                            <?= htmlspecialchars((string) $fatura['address']) ?><br>
                            <?= htmlspecialchars(trim((string) ($fatura['city'] ?? '') . ' ' . (string) ($fatura['country'] ?? ''))) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="fv-taraf">
                    <h4>Tarihler</h4>
                    <div class="fv-tarih">
                        <span>Düzenleme</span>
                        <span><?= date('d.m.Y', strtotime((string) $fatura['created_at'])) ?></span>
                    </div>
                    <div class="fv-tarih">
                        <span>Vade</span>
                        <span><?= !empty($fatura['due_date'])
                            ? date('d.m.Y', strtotime((string) $fatura['due_date'])) : '—' ?></span>
                    </div>
                    <?php if (!empty($fatura['paid_date'])): ?>
                        <div class="fv-tarih">
                            <span>Ödeme</span>
                            <span><?= date('d.m.Y', strtotime((string) $fatura['paid_date'])) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($fatura['payment_method'])): ?>
                        <div class="fv-tarih">
                            <span>Yöntem</span>
                            <span><?= Fatura::ODEME_YONTEMLERI[$fatura['payment_method']]
                                ?? htmlspecialchars((string) $fatura['payment_method']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$kalemler): ?>
                <div class="fv-bos">Bu faturada kalem yok.</div>
            <?php else: ?>
                <table class="fv-kalem">
                    <thead>
                        <tr>
                            <th>Açıklama</th>
                            <th class="orta">Adet</th>
                            <th class="sag">Birim</th>
                            <th class="sag">Tutar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kalemler as $k): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) $k['description']) ?></td>
                                <td class="orta"><?= (int) $k['quantity'] ?></td>
                                <td class="sag"><?= fvPara((float) $k['unit_price'], $paraBirimi) ?></td>
                                <td class="sag"><?= fvPara((float) $k['total'], $paraBirimi) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div class="fv-toplam">
                <div class="fv-toplam-kutu">
                    <div class="fv-toplam-satir">
                        <span>Ara toplam</span>
                        <span><?= fvPara((float) $fatura['subtotal'], $paraBirimi) ?></span>
                    </div>
                    <?php if ((float) $fatura['discount'] > 0): ?>
                        <div class="fv-toplam-satir cizgi">
                            <span>İndirim</span>
                            <span>−<?= fvPara((float) $fatura['discount'], $paraBirimi) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="fv-toplam-satir cizgi">
                        <span>KDV (%<?= rtrim(rtrim(number_format((float) $fatura['tax_rate'], 2, ',', '.'), '0'), ',') ?>)</span>
                        <span><?= fvPara((float) $fatura['tax'], $paraBirimi) ?></span>
                    </div>
                    <div class="fv-toplam-satir buyuk">
                        <span>Genel toplam</span>
                        <span><?= fvPara($toplam, $paraBirimi) ?></span>
                    </div>
                    <?php if ($odenen > 0): ?>
                        <div class="fv-toplam-satir">
                            <span>Ödenen</span>
                            <span><?= fvPara($odenen, $paraBirimi) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($kalan > 0 && $fatura['status'] !== 'cancelled'): ?>
                        <div class="fv-toplam-satir borc">
                            <span>Kalan borç</span>
                            <span><?= fvPara($kalan, $paraBirimi) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="fv-panel">
            <div class="fv-panel-bas">Ödeme geçmişi</div>
            <?php if (!$odemeler): ?>
                <div class="fv-bos">Bu faturaya ait ödeme kaydı yok.</div>
            <?php else: ?>
                <table class="fv-kalem" style="padding:0">
                    <thead>
                        <tr>
                            <th style="padding-left:16px">Tarih</th>
                            <th>Sağlayıcı</th>
                            <th>İşlem no</th>
                            <th class="sag">Tutar</th>
                            <th class="sag" style="padding-right:16px">Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($odemeler as $o): ?>
                            <tr>
                                <td style="padding-left:16px"><?= date('d.m.Y H:i', strtotime((string) $o['created_at'])) ?></td>
                                <td><?= htmlspecialchars((string) ($o['gateway'] ?: '—')) ?></td>
                                <td><?= htmlspecialchars((string) ($o['transaction_id'] ?: '—')) ?></td>
                                <td class="sag"><?= fvPara((float) $o['amount'], (string) ($o['currency'] ?: $paraBirimi)) ?></td>
                                <td class="sag" style="padding-right:16px">
                                    <span class="badge <?= $o['status'] === 'success' ? 'badge-success' : 'badge-warning' ?>">
                                        <?= $o['status'] === 'success' ? 'Başarılı' : htmlspecialchars((string) $o['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="fv-panel">
            <div class="fv-panel-bas">İşlemler</div>
            <div class="fv-panel-govde fv-islem">
                <?php if (!in_array($fatura['status'], ['paid', 'cancelled', 'refunded'], true)): ?>
                    <form method="POST">
                        <input type="hidden" name="islem" value="odendi">
                        <div class="fv-secim">
                            <label for="yontem">Ödeme yöntemi</label>
                            <select name="yontem" id="yontem" class="form-control">
                                <?php foreach (Fatura::ODEME_YONTEMLERI as $deger => $ad): ?>
                                    <option value="<?= $deger ?>" <?= $fatura['payment_method'] === $deger ? 'selected' : '' ?>>
                                        <?= $ad ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Ödendi işaretle
                        </button>
                    </form>

                    <form method="POST"
                        onsubmit="return confirm('Fatura iptal edilecek. Emin misiniz?')">
                        <input type="hidden" name="islem" value="iptal">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-ban"></i> İptal et
                        </button>
                    </form>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="islem" value="eposta">
                    <button type="submit" class="btn btn-outline">
                        <i class="fas fa-paper-plane"></i> Müşteriye gönder
                    </button>
                </form>

                <button type="button" class="btn btn-outline" onclick="window.print()">
                    <i class="fas fa-print"></i> Yazdır
                </button>
            </div>
        </div>

        <div class="fv-panel">
            <div class="fv-panel-bas">Müşteri</div>
            <div class="fv-panel-govde fv-musteri">
                <p>
                    <strong><?= htmlspecialchars($adSoyad !== '' ? $adSoyad : 'Müşteri silinmiş') ?></strong><br>
                    <a href="mailto:<?= htmlspecialchars((string) $fatura['email']) ?>" dir="ltr">
                        <?= htmlspecialchars((string) $fatura['email']) ?></a>
                    <?php if (!empty($fatura['phone'])): ?>
                        <br><?= htmlspecialchars((string) $fatura['phone']) ?>
                    <?php endif; ?>
                </p>
                <?php if (!empty($fatura['client_id'])): ?>
                    <a href="client-view.php?id=<?= (int) $fatura['client_id'] ?>"
                        class="btn btn-sm btn-outline" style="width:100%;justify-content:center;margin-top:12px">
                        Müşteri kartı
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($fatura['notes']) || !empty($fatura['admin_notes'])): ?>
            <div class="fv-panel">
                <div class="fv-panel-bas">Notlar</div>
                <div class="fv-panel-govde fv-musteri">
                    <?php if (!empty($fatura['notes'])): ?>
                        <p><?= nl2br(htmlspecialchars((string) $fatura['notes'])) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($fatura['admin_notes'])): ?>
                        <p style="margin-top:10px;color:var(--y-metin-3)">
                            <?= nl2br(htmlspecialchars((string) $fatura['admin_notes'])) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
