<?php
/**
 * VHM - Sipariş detayı
 *
 * Önceki sürümdeki sorunlar:
 *   - Oturum kontrolü yoktu. header.php sayfanın sonunda çağrıldığı için
 *     yetkisiz bir POST siparişi etkinleştirip hizmet açabiliyordu.
 *   - Hizmet oluşturma kodu aynı dosyada iki kez yazılmıştı; dönem
 *     eşlemesi eksik, "zaten var mı" kontrolü hatalıydı. includes/Siparis.php
 *     içine taşındı.
 *   - Durum doğrudan $_POST'tan alınıyordu; enum dışı değer yazılabiliyordu.
 *   - PRG yoktu; yenilemede işlem tekrarlanıyordu.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
require_once dirname(__DIR__) . '/includes/Siparis.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);

$siparis = $id > 0 ? Database::fetch(
    "SELECT o.*, c.first_name, c.last_name, c.email, c.phone, c.company_name
       FROM orders o
       LEFT JOIN clients c ON c.id = o.client_id
      WHERE o.id = ?",
    [$id]
) : null;

if (!$siparis) {
    $_SESSION['sip_mesaj'] = ['tip' => 'error', 'metin' => 'Sipariş bulunamadı.'];
    header('Location: orders.php');
    exit;
}

function siparisDon(string $tip, string $metin, int $id): never
{
    $_SESSION['sd_mesaj'] = ['tip' => $tip, 'metin' => $metin];
    header('Location: order-view.php?id=' . $id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Guvenlik::zorunlu();
    $adminId = (int) ($_SESSION['admin_id'] ?? 0) ?: null;

    try {
        if (isset($_POST['durum'])) {
            $adet = Siparis::durumDegistir($id, (string) $_POST['durum'], $adminId);
            siparisDon(
                'success',
                'Sipariş durumu güncellendi.' . ($adet > 0 ? ' ' . $adet . ' hizmet oluşturuldu.' : ''),
                $id
            );
        }

        if (isset($_POST['hizmet_olustur'])) {
            $olusan = Siparis::hizmetleriOlustur($id, $adminId);
            if (!$olusan) {
                siparisDon('uyari', 'Bu siparişin tüm kalemleri için hizmet zaten mevcut.', $id);
            }
            header('Location: service-edit.php?id=' . $olusan[0]);
            exit;
        }

        siparisDon('error', 'Tanımsız işlem.', $id);
    } catch (RuntimeException $e) {
        siparisDon('error', $e->getMessage(), $id);
    }
}

$mesaj = null;
if (!empty($_SESSION['sd_mesaj'])) {
    $mesaj = $_SESSION['sd_mesaj'];
    unset($_SESSION['sd_mesaj']);
}

$kalemler = Database::fetchAll(
    "SELECT oi.*, p.name AS urun, p.type AS urun_tipi
       FROM order_items oi
       LEFT JOIN products p ON p.id = oi.product_id
      WHERE oi.order_id = ?
      ORDER BY oi.id",
    [$id]
);

$hizmetler = Database::fetchAll(
    "SELECT s.*, p.name AS urun
       FROM services s
       LEFT JOIN products p ON p.id = s.product_id
      WHERE s.order_id = ?
      ORDER BY s.id",
    [$id]
);

$faturalar = Database::fetchAll(
    "SELECT DISTINCT i.*
       FROM invoices i
       JOIN invoice_items ii ON ii.invoice_id = i.id
       JOIN services s ON s.id = ii.service_id
      WHERE s.order_id = ?
      ORDER BY i.created_at DESC",
    [$id]
);

$gunluk = [];
try {
    $gunluk = Database::fetchAll(
        "SELECT * FROM order_logs WHERE order_id = ? ORDER BY created_at DESC LIMIT 25",
        [$id]
    );
} catch (Throwable $e) {
    error_log('Sipariş günlüğü okunamadı: ' . $e->getMessage());
}

$durumSinifi = [
    'pending' => 'badge-warning', 'processing' => 'badge-info', 'active' => 'badge-success',
    'fraud' => 'badge-danger', 'cancelled' => 'badge',
];
$hizmetDurum = [
    'active' => ['Etkin', 'badge-success'], 'pending' => ['Bekliyor', 'badge-warning'],
    'suspended' => ['Askıda', 'badge-danger'], 'terminated' => ['Sonlandırıldı', 'badge'],
    'cancelled' => ['İptal', 'badge'],
];
$faturaDurum = [
    'draft' => ['Taslak', 'badge'], 'unpaid' => ['Ödenmedi', 'badge-warning'],
    'paid' => ['Ödendi', 'badge-success'], 'cancelled' => ['İptal', 'badge'],
    'refunded' => ['İade', 'badge-info'], 'collections' => ['Takipte', 'badge-danger'],
];
$donemler = [
    'monthly' => 'Aylık', 'quarterly' => '3 aylık', 'semiannually' => '6 aylık',
    'annually' => 'Yıllık', 'biennially' => '2 yıllık', 'triennially' => '3 yıllık',
    'onetime' => 'Tek seferlik',
];

$paraBirimi = (string) ($siparis['currency'] ?: 'TRY');
$adSoyad = trim((string) $siparis['first_name'] . ' ' . (string) $siparis['last_name']);
$eksikHizmet = count($kalemler) > count($hizmetler);

function svPara(float $t, string $b): string
{
    return number_format($t, 2, ',', '.') . ' ' . $b;
}

$pageTitle = 'Sipariş ' . ($siparis['order_number'] ?: '#' . $id);
$currentPage = 'orders';
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* ==========================================
       Sipariş detayı - sv
       ========================================== */
    .sv-duzen {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 300px;
        gap: 18px;
        align-items: start;
    }

    .sv-panel {
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .sv-panel + .sv-panel {
        margin-top: 18px;
    }

    .sv-panel-bas {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 16px;
        border-bottom: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
    }

    .sv-panel-bas h3 {
        font-size: 12.5px;
        font-weight: 700;
    }

    .sv-panel-govde {
        padding: 16px;
    }

    .sv-kalem {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 14px 16px;
        border-bottom: 1px solid var(--y-cizgi-soft);
    }

    .sv-kalem:last-child {
        border-bottom: none;
    }

    .sv-kalem-ikon {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border-radius: 9px;
        background: var(--y-primary-soft);
        color: var(--y-primary);
        font-size: 13px;
    }

    .sv-kalem-orta {
        flex: 1;
        min-width: 0;
    }

    .sv-kalem-orta b {
        display: block;
        font-size: 13.5px;
        font-weight: 600;
        color: var(--y-metin);
    }

    .sv-etiketler {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 5px;
    }

    .sv-etiket {
        padding: 2px 8px;
        border: 1px solid var(--y-cizgi);
        border-radius: 999px;
        font-size: 11px;
        color: var(--y-metin-3);
    }

    .sv-kalem-fiyat {
        text-align: right;
        white-space: nowrap;
    }

    .sv-kalem-fiyat b {
        font-size: 14px;
        font-weight: 700;
        color: var(--y-metin);
    }

    .sv-kalem-fiyat span {
        display: block;
        margin-top: 2px;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .sv-ozet {
        padding: 14px 16px;
        border-top: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
    }

    .sv-ozet-satir {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        padding: 5px 0;
        font-size: 13px;
        color: var(--y-metin-2);
    }

    .sv-ozet-satir.buyuk {
        margin-top: 6px;
        padding-top: 10px;
        border-top: 1px solid var(--y-cizgi);
        font-size: 16px;
        font-weight: 700;
        color: var(--y-metin);
    }

    .sv-ozet-satir.buyuk span:last-child {
        color: var(--y-primary);
    }

    .sv-tablo {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .sv-tablo td {
        padding: 10px 16px;
        border-bottom: 1px solid var(--y-cizgi-soft);
    }

    .sv-tablo tr:last-child td {
        border-bottom: none;
    }

    .sv-tablo tr:hover td {
        background: var(--y-yuzey-2);
    }

    .sv-tablo b {
        font-weight: 600;
        color: var(--y-metin);
    }

    .sv-tablo span {
        display: block;
        margin-top: 1px;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .sv-sag {
        text-align: right;
        white-space: nowrap;
    }

    .sv-bilgi-satir {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 8px 0;
        border-bottom: 1px solid var(--y-cizgi-soft);
        font-size: 13px;
    }

    .sv-bilgi-satir:last-child {
        border-bottom: none;
    }

    .sv-bilgi-satir span:first-child {
        color: var(--y-metin-3);
    }

    .sv-bilgi-satir span:last-child {
        color: var(--y-metin);
        text-align: right;
        word-break: break-word;
    }

    .sv-gunluk {
        position: relative;
        padding: 0 16px 14px 34px;
    }

    .sv-gunluk::before {
        content: "";
        position: absolute;
        left: 21px;
        top: 6px;
        bottom: 20px;
        width: 1px;
        background: var(--y-cizgi);
    }

    .sv-gunluk-oge {
        position: relative;
        padding: 9px 0;
        font-size: 12.5px;
        color: var(--y-metin-2);
    }

    .sv-gunluk-oge::before {
        content: "";
        position: absolute;
        left: -17px;
        top: 15px;
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--y-primary);
        box-shadow: 0 0 0 3px var(--y-yuzey);
    }

    .sv-gunluk-oge time {
        display: block;
        margin-top: 2px;
        font-size: 11px;
        color: var(--y-metin-3);
    }

    .sv-bos {
        padding: 24px 16px;
        text-align: center;
        font-size: 13px;
        color: var(--y-metin-3);
    }

    .sv-islem {
        display: flex;
        flex-direction: column;
        gap: 9px;
    }

    .sv-islem form {
        margin: 0;
    }

    .sv-islem .btn {
        width: 100%;
        justify-content: center;
    }

    .sv-islem label {
        display: block;
        margin-bottom: 4px;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    @media (max-width: 980px) {
        .sv-duzen {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>Sipariş <?= htmlspecialchars((string) ($siparis['order_number'] ?: '#' . $id)) ?></h1>
        <p><a href="orders.php">Siparişler</a> &rsaquo;
            <?= htmlspecialchars($adSoyad !== '' ? $adSoyad : 'Müşteri silinmiş') ?> &middot;
            <?= date('d.m.Y H:i', strtotime((string) $siparis['created_at'])) ?></p>
    </div>
    <span class="badge <?= $durumSinifi[$siparis['status']] ?? 'badge' ?>" style="font-size:12.5px;padding:7px 14px">
        <?= Siparis::DURUMLAR[$siparis['status']] ?? htmlspecialchars((string) $siparis['status']) ?>
    </span>
</div>

<?php if ($mesaj): ?>
    <div class="alert alert-<?= htmlspecialchars($mesaj['tip']) ?>">
        <?= htmlspecialchars($mesaj['metin']) ?>
    </div>
<?php endif; ?>

<div class="sv-duzen">
    <div>
        <div class="sv-panel">
            <div class="sv-panel-bas">
                <h3>Sipariş kalemleri</h3>
                <span style="font-size:12px;color:var(--y-metin-3)"><?= count($kalemler) ?> kalem</span>
            </div>

            <?php if (!$kalemler): ?>
                <div class="sv-bos">Bu siparişte kalem yok.</div>
            <?php else: ?>
                <?php foreach ($kalemler as $k): ?>
                    <div class="sv-kalem">
                        <span class="sv-kalem-ikon"><i class="fas fa-box"></i></span>
                        <div class="sv-kalem-orta">
                            <b><?= htmlspecialchars((string) ($k['urun'] ?: $k['description'])) ?></b>
                            <div class="sv-etiketler">
                                <?php if (!empty($k['domain'])): ?>
                                    <span class="sv-etiket"><?= htmlspecialchars((string) $k['domain']) ?></span>
                                <?php endif; ?>
                                <span class="sv-etiket"><?= $donemler[$k['billing_cycle']] ?? htmlspecialchars((string) $k['billing_cycle']) ?></span>
                                <?php if ((int) $k['quantity'] > 1): ?>
                                    <span class="sv-etiket"><?= (int) $k['quantity'] ?> adet</span>
                                <?php endif; ?>
                                <?php if ((float) $k['setup_fee'] > 0): ?>
                                    <span class="sv-etiket">Kurulum <?= svPara((float) $k['setup_fee'], $paraBirimi) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="sv-kalem-fiyat">
                            <b><?= svPara((float) $k['total'], $paraBirimi) ?></b>
                            <span><?= svPara((float) $k['unit_price'], $paraBirimi) ?> birim</span>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="sv-ozet">
                    <div class="sv-ozet-satir">
                        <span>Ara toplam</span>
                        <span><?= svPara((float) $siparis['subtotal'], $paraBirimi) ?></span>
                    </div>
                    <?php if ((float) $siparis['discount'] > 0): ?>
                        <div class="sv-ozet-satir">
                            <span>İndirim<?= !empty($siparis['promo_code'])
                                ? ' (' . htmlspecialchars((string) $siparis['promo_code']) . ')' : '' ?></span>
                            <span>−<?= svPara((float) $siparis['discount'], $paraBirimi) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="sv-ozet-satir">
                        <span>KDV</span>
                        <span><?= svPara((float) $siparis['tax'], $paraBirimi) ?></span>
                    </div>
                    <div class="sv-ozet-satir buyuk">
                        <span>Genel toplam</span>
                        <span><?= svPara((float) $siparis['total'], $paraBirimi) ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="sv-panel">
            <div class="sv-panel-bas">
                <h3>Oluşan hizmetler</h3>
                <span style="font-size:12px;color:var(--y-metin-3)"><?= count($hizmetler) ?> hizmet</span>
            </div>
            <?php if (!$hizmetler): ?>
                <div class="sv-bos">Bu sipariş için henüz hizmet açılmadı.</div>
            <?php else: ?>
                <table class="sv-tablo">
                    <tbody>
                        <?php foreach ($hizmetler as $h):
                            [$dAd, $dSinif] = $hizmetDurum[$h['status']] ?? [(string) $h['status'], 'badge'];
                            ?>
                            <tr>
                                <td>
                                    <b><a href="service-view.php?id=<?= (int) $h['id'] ?>">
                                        <?= htmlspecialchars((string) ($h['urun'] ?: 'Ürün silinmiş')) ?></a></b>
                                    <span><?= htmlspecialchars((string) ($h['domain'] ?: '—')) ?></span>
                                </td>
                                <td class="sv-sag">
                                    <b><?= svPara((float) $h['amount'], $paraBirimi) ?></b>
                                    <span><?= !empty($h['next_due_date'])
                                        ? date('d.m.Y', strtotime((string) $h['next_due_date'])) . ' vade'
                                        : 'Tek seferlik' ?></span>
                                </td>
                                <td class="sv-sag"><span class="badge <?= $dSinif ?>"><?= $dAd ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if ($faturalar): ?>
            <div class="sv-panel">
                <div class="sv-panel-bas"><h3>Faturalar</h3></div>
                <table class="sv-tablo">
                    <tbody>
                        <?php foreach ($faturalar as $f):
                            [$dAd, $dSinif] = $faturaDurum[$f['status']] ?? [(string) $f['status'], 'badge'];
                            ?>
                            <tr>
                                <td>
                                    <b><a href="invoice-view.php?id=<?= (int) $f['id'] ?>">
                                        <?= htmlspecialchars((string) $f['invoice_number']) ?></a></b>
                                    <span><?= date('d.m.Y', strtotime((string) $f['created_at'])) ?></span>
                                </td>
                                <td class="sv-sag"><b><?= svPara((float) $f['total'], (string) ($f['currency'] ?: $paraBirimi)) ?></b></td>
                                <td class="sv-sag"><span class="badge <?= $dSinif ?>"><?= $dAd ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($gunluk): ?>
            <div class="sv-panel">
                <div class="sv-panel-bas"><h3>Hareket geçmişi</h3></div>
                <div class="sv-gunluk">
                    <?php foreach ($gunluk as $g): ?>
                        <div class="sv-gunluk-oge">
                            <?= htmlspecialchars((string) ($g['description'] ?? $g['action'] ?? '')) ?>
                            <time><?= date('d.m.Y H:i', strtotime((string) $g['created_at'])) ?></time>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div>
        <div class="sv-panel">
            <div class="sv-panel-bas"><h3>İşlemler</h3></div>
            <div class="sv-panel-govde sv-islem">
                <form method="POST">
                    <label for="durum">Sipariş durumu</label>
                    <select name="durum" id="durum" class="form-control" style="margin-bottom:9px">
                        <?php foreach (Siparis::DURUMLAR as $deger => $ad): ?>
                            <option value="<?= $deger ?>" <?= $siparis['status'] === $deger ? 'selected' : '' ?>>
                                <?= $ad ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-rotate"></i> Durumu güncelle
                    </button>
                </form>

                <?php if ($eksikHizmet): ?>
                    <form method="POST">
                        <input type="hidden" name="hizmet_olustur" value="1">
                        <button type="submit" class="btn btn-outline">
                            <i class="fas fa-plus"></i> Eksik hizmetleri aç
                        </button>
                    </form>
                <?php endif; ?>

                <a href="invoice-create.php?client_id=<?= (int) $siparis['client_id'] ?>" class="btn btn-outline">
                    <i class="fas fa-file-invoice"></i> Fatura oluştur
                </a>
            </div>
        </div>

        <div class="sv-panel">
            <div class="sv-panel-bas"><h3>Müşteri</h3></div>
            <div class="sv-panel-govde">
                <div class="sv-bilgi-satir">
                    <span>Ad soyad</span>
                    <span><?= htmlspecialchars($adSoyad !== '' ? $adSoyad : 'Müşteri silinmiş') ?></span>
                </div>
                <?php if (!empty($siparis['company_name'])): ?>
                    <div class="sv-bilgi-satir">
                        <span>Şirket</span>
                        <span><?= htmlspecialchars((string) $siparis['company_name']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="sv-bilgi-satir">
                    <span>E-posta</span>
                    <span><a href="mailto:<?= htmlspecialchars((string) $siparis['email']) ?>" dir="ltr"
                            style="color:var(--y-primary)"><?= htmlspecialchars((string) $siparis['email']) ?></a></span>
                </div>
                <?php if (!empty($siparis['phone'])): ?>
                    <div class="sv-bilgi-satir">
                        <span>Telefon</span>
                        <span dir="ltr"><?= htmlspecialchars((string) $siparis['phone']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($siparis['client_id'])): ?>
                    <a href="client-view.php?id=<?= (int) $siparis['client_id'] ?>"
                        class="btn btn-sm btn-outline" style="width:100%;justify-content:center;margin-top:12px">
                        Müşteri kartı
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="sv-panel">
            <div class="sv-panel-bas"><h3>Sipariş bilgisi</h3></div>
            <div class="sv-panel-govde">
                <div class="sv-bilgi-satir">
                    <span>Ödeme yöntemi</span>
                    <span><?= htmlspecialchars((string) ($siparis['payment_method'] ?: '—')) ?></span>
                </div>
                <?php if (!empty($siparis['promo_code'])): ?>
                    <div class="sv-bilgi-satir">
                        <span>Promosyon</span>
                        <span><?= htmlspecialchars((string) $siparis['promo_code']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="sv-bilgi-satir">
                    <span>IP adresi</span>
                    <span dir="ltr"><?= htmlspecialchars((string) ($siparis['ip_address'] ?: '—')) ?></span>
                </div>
                <div class="sv-bilgi-satir">
                    <span>Son güncelleme</span>
                    <span><?= date('d.m.Y H:i', strtotime((string) $siparis['updated_at'])) ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($siparis['notes']) || !empty($siparis['admin_notes'])): ?>
            <div class="sv-panel">
                <div class="sv-panel-bas"><h3>Notlar</h3></div>
                <div class="sv-panel-govde" style="font-size:13px;line-height:1.65;color:var(--y-metin-2)">
                    <?php if (!empty($siparis['notes'])): ?>
                        <p><?= nl2br(htmlspecialchars((string) $siparis['notes'])) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($siparis['admin_notes'])): ?>
                        <p style="margin-top:10px;color:var(--y-metin-3)">
                            <?= nl2br(htmlspecialchars((string) $siparis['admin_notes'])) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
