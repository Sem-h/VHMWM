<?php
/**
 * VHM - Müşteri kartı
 *
 * Önceki sürüm on satırdı ve yalnızca client-edit.php'ye yönlendiriyordu.
 * Listedeki "Görüntüle" ve "Düzenle" düğmeleri aynı yere gidiyordu;
 * müşterinin durumunu tek ekranda görmenin yolu yoktu.
 *
 * Bu sayfa salt okunur: hizmet, fatura, sipariş, ödeme ve destek
 * geçmişini bir arada gösterir. Değişiklik client-edit.php'de yapılır.
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

$id = (int) ($_GET['id'] ?? 0);

$musteri = null;
if ($id > 0) {
    try {
        $musteri = Database::fetch("SELECT * FROM clients WHERE id = ?", [$id]);
    } catch (Throwable $e) {
        error_log('Müşteri okunamadı: ' . $e->getMessage());
    }
}

if (!$musteri) {
    $_SESSION['mus_mesaj'] = ['tip' => 'error', 'metin' => 'Müşteri bulunamadı.'];
    header('Location: clients.php');
    exit;
}

$adSoyad = trim((string) $musteri['first_name'] . ' ' . (string) $musteri['last_name']);
$pageTitle = $adSoyad !== '' ? $adSoyad : 'Müşteri';
$currentPage = 'clients';

/** Tablo yoksa ya da sorgu patlarsa sayfa çökmesin */
function musListe(string $sql, array $par = []): array
{
    try {
        return Database::fetchAll($sql, $par);
    } catch (Throwable $e) {
        error_log('Müşteri kartı sorgusu: ' . $e->getMessage());
        return [];
    }
}

function musSayi(string $sql, array $par = []): float
{
    try {
        return (float) Database::fetchColumn($sql, $par);
    } catch (Throwable $e) {
        error_log('Müşteri kartı sorgusu: ' . $e->getMessage());
        return 0;
    }
}

$hizmetler = musListe(
    "SELECT s.*, p.name AS urun
       FROM services s
       LEFT JOIN products p ON p.id = s.product_id
      WHERE s.client_id = ?
      ORDER BY FIELD(s.status,'active','pending','suspended','terminated','cancelled'), s.next_due_date
      LIMIT 20",
    [$id]
);

$faturalar = musListe(
    "SELECT * FROM invoices WHERE client_id = ? ORDER BY created_at DESC LIMIT 10",
    [$id]
);

$siparisler = musListe(
    "SELECT * FROM orders WHERE client_id = ? ORDER BY created_at DESC LIMIT 10",
    [$id]
);

$talepler = musListe(
    "SELECT * FROM tickets WHERE client_id = ? ORDER BY updated_at DESC LIMIT 10",
    [$id]
);

$odemeler = musListe(
    "SELECT t.*, i.invoice_number
       FROM transactions t
       LEFT JOIN invoices i ON i.id = t.invoice_id
      WHERE t.client_id = ?
      ORDER BY t.created_at DESC LIMIT 10",
    [$id]
);

$ozet = [
    ['Aktif hizmet', musSayi("SELECT COUNT(*) FROM services WHERE client_id = ? AND status = 'active'", [$id]), 'fa-cubes', false],
    ['Ödenmemiş fatura', musSayi("SELECT COUNT(*) FROM invoices WHERE client_id = ? AND status = 'unpaid'", [$id]), 'fa-file-invoice', false],
    ['Toplam ödenen', musSayi("SELECT COALESCE(SUM(amount_paid),0) FROM invoices WHERE client_id = ? AND status = 'paid'", [$id]), 'fa-turkish-lira-sign', true],
    ['Açık destek', musSayi("SELECT COUNT(*) FROM tickets WHERE client_id = ? AND status <> 'closed'", [$id]), 'fa-life-ring', false],
];

$hizmetDurum = [
    'active' => ['Etkin', 'badge-success'],
    'pending' => ['Bekliyor', 'badge-warning'],
    'suspended' => ['Askıda', 'badge-danger'],
    'terminated' => ['Sonlandırıldı', 'badge'],
    'cancelled' => ['İptal', 'badge'],
];
$faturaDurum = [
    'draft' => ['Taslak', 'badge'], 'unpaid' => ['Ödenmedi', 'badge-warning'],
    'paid' => ['Ödendi', 'badge-success'], 'cancelled' => ['İptal', 'badge'],
    'refunded' => ['İade', 'badge-info'], 'collections' => ['Takipte', 'badge-danger'],
];
$siparisDurum = [
    'pending' => ['Bekliyor', 'badge-warning'], 'processing' => ['İşleniyor', 'badge-info'],
    'active' => ['Etkin', 'badge-success'], 'fraud' => ['Şüpheli', 'badge-danger'],
    'cancelled' => ['İptal', 'badge'],
];
$talepDurum = [
    'open' => ['Açık', 'badge-info'], 'answered' => ['Yanıtlandı', 'badge-success'],
    'customer_reply' => ['Müşteri yanıtladı', 'badge-warning'], 'on_hold' => ['Beklemede', 'badge-warning'],
    'in_progress' => ['İşlemde', 'badge-info'], 'closed' => ['Kapalı', 'badge'],
];

require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* ==========================================
       Müşteri kartı - mk
       ========================================== */
    .mk-ust {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        padding: 18px;
        margin-bottom: 18px;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
    }

    .mk-avatar {
        width: 54px;
        height: 54px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border-radius: 50%;
        background: var(--y-primary-soft);
        color: var(--y-primary);
        font-size: 20px;
        font-weight: 700;
    }

    .mk-kim {
        flex: 1;
        min-width: 200px;
    }

    .mk-kim h2 {
        font-size: 18px;
        font-weight: 700;
        color: var(--y-metin);
    }

    .mk-kim span {
        display: block;
        margin-top: 3px;
        font-size: 13px;
        color: var(--y-metin-3);
    }

    .mk-kim a {
        color: var(--y-metin-3);
    }

    .mk-kim a:hover {
        color: var(--y-primary);
    }

    .mk-eylem {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .mk-ozet {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }

    .mk-ozet-kart {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
    }

    .mk-ozet-ikon {
        width: 36px;
        height: 36px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border-radius: 9px;
        background: var(--y-primary-soft);
        color: var(--y-primary);
        font-size: 14px;
    }

    .mk-ozet-kart b {
        display: block;
        font-size: 19px;
        font-weight: 700;
        letter-spacing: -.02em;
        color: var(--y-metin);
        line-height: 1.2;
    }

    .mk-ozet-kart span {
        font-size: 12px;
        color: var(--y-metin-3);
    }

    .mk-duzen {
        display: grid;
        grid-template-columns: 300px minmax(0, 1fr);
        gap: 18px;
        align-items: start;
    }

    .mk-panel {
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .mk-panel + .mk-panel {
        margin-top: 18px;
    }

    .mk-panel-bas {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 16px;
        border-bottom: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
    }

    .mk-panel-bas h3 {
        font-size: 12.5px;
        font-weight: 700;
    }

    .mk-bilgi {
        padding: 6px 16px 12px;
    }

    .mk-bilgi-satir {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 9px 0;
        border-bottom: 1px solid var(--y-cizgi-soft);
        font-size: 13px;
    }

    .mk-bilgi-satir:last-child {
        border-bottom: none;
    }

    .mk-bilgi-satir span:first-child {
        color: var(--y-metin-3);
        white-space: nowrap;
    }

    .mk-bilgi-satir span:last-child {
        color: var(--y-metin);
        text-align: right;
        word-break: break-word;
    }

    .mk-tablo {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .mk-tablo td {
        padding: 10px 16px;
        border-bottom: 1px solid var(--y-cizgi-soft);
        vertical-align: middle;
    }

    .mk-tablo tr:last-child td {
        border-bottom: none;
    }

    .mk-tablo tr:hover td {
        background: var(--y-yuzey-2);
    }

    .mk-tablo b {
        font-weight: 600;
        color: var(--y-metin);
    }

    .mk-tablo span {
        display: block;
        margin-top: 1px;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .mk-sag {
        text-align: right;
        white-space: nowrap;
    }

    .mk-bos {
        padding: 26px 16px;
        text-align: center;
        font-size: 13px;
        color: var(--y-metin-3);
    }

    @media (max-width: 1000px) {
        .mk-duzen {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>Müşteri kartı</h1>
        <p><a href="clients.php">Müşteriler</a> &rsaquo; #<?= $id ?></p>
    </div>
</div>

<div class="mk-ust">
    <span class="mk-avatar">
        <?= htmlspecialchars(mb_strtoupper(mb_substr($adSoyad !== '' ? $adSoyad : '?', 0, 1), 'UTF-8')) ?>
    </span>
    <div class="mk-kim">
        <h2><?= htmlspecialchars($adSoyad !== '' ? $adSoyad : 'İsimsiz') ?></h2>
        <span>
            <a href="mailto:<?= htmlspecialchars((string) $musteri['email']) ?>"
                dir="ltr"><?= htmlspecialchars((string) $musteri['email']) ?></a>
            <?php if (!empty($musteri['phone'])): ?>
                &middot; <a href="tel:<?= htmlspecialchars(preg_replace('/\D+/', '', (string) $musteri['phone']) ?? '') ?>"
                    dir="ltr"><?= htmlspecialchars((string) $musteri['phone']) ?></a>
            <?php endif; ?>
        </span>
    </div>
    <span class="badge <?= (int) $musteri['is_active'] === 1 ? 'badge-success' : '' ?>">
        <?= (int) $musteri['is_active'] === 1 ? 'Etkin' : 'Pasif' ?>
    </span>
    <div class="mk-eylem">
        <a href="client-edit.php?id=<?= $id ?>" class="btn btn-primary">
            <i class="fas fa-pen"></i> Düzenle
        </a>
        <a href="clients.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Listeye dön
        </a>
    </div>
</div>

<div class="mk-ozet">
    <?php foreach ($ozet as [$ad, $deger, $ikon, $paraMi]): ?>
        <div class="mk-ozet-kart">
            <span class="mk-ozet-ikon"><i class="fas <?= $ikon ?>"></i></span>
            <span>
                <b><?= $paraMi
                    ? number_format($deger, 2, ',', '.') . ' ₺'
                    : number_format($deger, 0, ',', '.') ?></b>
                <span><?= $ad ?></span>
            </span>
        </div>
    <?php endforeach; ?>
</div>

<div class="mk-duzen">

    <!-- Sol: hesap bilgileri -->
    <div>
        <div class="mk-panel">
            <div class="mk-panel-bas"><h3>Hesap bilgileri</h3></div>
            <div class="mk-bilgi">
                <div class="mk-bilgi-satir">
                    <span>Hesap türü</span>
                    <span><?= $musteri['account_type'] === 'corporate' ? 'Kurumsal' : 'Bireysel' ?></span>
                </div>
                <?php if (!empty($musteri['company_name'])): ?>
                    <div class="mk-bilgi-satir">
                        <span>Şirket</span>
                        <span><?= htmlspecialchars((string) $musteri['company_name']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($musteri['tax_office']) || !empty($musteri['tax_id'])): ?>
                    <div class="mk-bilgi-satir">
                        <span>Vergi</span>
                        <span>
                            <?= htmlspecialchars((string) ($musteri['tax_office'] ?? '')) ?>
                            <?= !empty($musteri['tax_id']) ? ' / ' . htmlspecialchars((string) $musteri['tax_id']) : '' ?>
                        </span>
                    </div>
                <?php endif; ?>
                <div class="mk-bilgi-satir">
                    <span>Bakiye</span>
                    <span><?= number_format((float) ($musteri['credit_balance'] ?? 0), 2, ',', '.') ?> ₺</span>
                </div>
                <div class="mk-bilgi-satir">
                    <span>E-posta doğrulama</span>
                    <span><?= (int) ($musteri['email_verified'] ?? 0) === 1 ? 'Doğrulandı' : 'Doğrulanmadı' ?></span>
                </div>
                <div class="mk-bilgi-satir">
                    <span>Kayıt</span>
                    <span><?= date('d.m.Y', strtotime((string) $musteri['created_at'])) ?></span>
                </div>
                <div class="mk-bilgi-satir">
                    <span>Son giriş</span>
                    <span>
                        <?= !empty($musteri['last_login'])
                            ? date('d.m.Y H:i', strtotime((string) $musteri['last_login']))
                            : 'Hiç giriş yapmamış' ?>
                    </span>
                </div>
                <?php if (!empty($musteri['address']) || !empty($musteri['city'])): ?>
                    <div class="mk-bilgi-satir">
                        <span>Adres</span>
                        <span>
                            <?= nl2br(htmlspecialchars((string) ($musteri['address'] ?? ''))) ?>
                            <?php if (!empty($musteri['city'])): ?>
                                <br><?= htmlspecialchars((string) $musteri['city']) ?>
                                <?= htmlspecialchars((string) ($musteri['country'] ?? '')) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($musteri['notes'])): ?>
            <div class="mk-panel">
                <div class="mk-panel-bas"><h3>Yönetici notu</h3></div>
                <div class="mk-bilgi" style="padding-top:12px; font-size:13px; line-height:1.65; color:var(--y-metin-2)">
                    <?= nl2br(htmlspecialchars((string) $musteri['notes'])) ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sağ: geçmiş -->
    <div>
        <div class="mk-panel">
            <div class="mk-panel-bas">
                <h3>Hizmetler</h3>
                <a href="services.php" class="btn btn-sm btn-outline">Tümü</a>
            </div>
            <?php if (!$hizmetler): ?>
                <div class="mk-bos">Bu müşterinin hizmeti yok.</div>
            <?php else: ?>
                <table class="mk-tablo">
                    <tbody>
                        <?php foreach ($hizmetler as $h):
                            [$dAd, $dSinif] = $hizmetDurum[$h['status']] ?? [(string) $h['status'], 'badge'];
                            ?>
                            <tr>
                                <td>
                                    <b><?= htmlspecialchars((string) ($h['urun'] ?? 'Ürün silinmiş')) ?></b>
                                    <span><?= htmlspecialchars((string) ($h['domain'] ?: '—')) ?></span>
                                </td>
                                <td class="mk-sag">
                                    <b><?= number_format((float) $h['amount'], 2, ',', '.') ?> ₺</b>
                                    <span><?= !empty($h['next_due_date'])
                                        ? date('d.m.Y', strtotime((string) $h['next_due_date'])) : '—' ?></span>
                                </td>
                                <td class="mk-sag"><span class="badge <?= $dSinif ?>"><?= $dAd ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="mk-panel">
            <div class="mk-panel-bas">
                <h3>Faturalar</h3>
                <a href="invoices.php?q=<?= urlencode((string) $musteri['email']) ?>" class="btn btn-sm btn-outline">Tümü</a>
            </div>
            <?php if (!$faturalar): ?>
                <div class="mk-bos">Fatura yok.</div>
            <?php else: ?>
                <table class="mk-tablo">
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
                                <td class="mk-sag">
                                    <b><?= number_format((float) $f['total'], 2, ',', '.') ?> ₺</b>
                                </td>
                                <td class="mk-sag"><span class="badge <?= $dSinif ?>"><?= $dAd ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="mk-panel">
            <div class="mk-panel-bas">
                <h3>Siparişler</h3>
                <a href="orders.php?q=<?= urlencode((string) $musteri['email']) ?>" class="btn btn-sm btn-outline">Tümü</a>
            </div>
            <?php if (!$siparisler): ?>
                <div class="mk-bos">Sipariş yok.</div>
            <?php else: ?>
                <table class="mk-tablo">
                    <tbody>
                        <?php foreach ($siparisler as $s):
                            [$dAd, $dSinif] = $siparisDurum[$s['status']] ?? [(string) $s['status'], 'badge'];
                            ?>
                            <tr>
                                <td>
                                    <b><a href="order-view.php?id=<?= (int) $s['id'] ?>">
                                        #<?= htmlspecialchars((string) ($s['order_number'] ?: $s['id'])) ?></a></b>
                                    <span><?= date('d.m.Y', strtotime((string) $s['created_at'])) ?></span>
                                </td>
                                <td class="mk-sag">
                                    <b><?= number_format((float) $s['total'], 2, ',', '.') ?> ₺</b>
                                </td>
                                <td class="mk-sag"><span class="badge <?= $dSinif ?>"><?= $dAd ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="mk-panel">
            <div class="mk-panel-bas">
                <h3>Ödemeler</h3>
                <a href="transactions.php?q=<?= urlencode((string) $musteri['email']) ?>" class="btn btn-sm btn-outline">Tümü</a>
            </div>
            <?php if (!$odemeler): ?>
                <div class="mk-bos">Ödeme kaydı yok.</div>
            <?php else: ?>
                <table class="mk-tablo">
                    <tbody>
                        <?php foreach ($odemeler as $o): ?>
                            <tr>
                                <td>
                                    <b><?= htmlspecialchars((string) ($o['transaction_id'] ?: '#' . $o['id'])) ?></b>
                                    <span><?= date('d.m.Y H:i', strtotime((string) $o['created_at'])) ?>
                                        <?= !empty($o['invoice_number']) ? ' · ' . htmlspecialchars((string) $o['invoice_number']) : '' ?></span>
                                </td>
                                <td class="mk-sag">
                                    <b><?= number_format((float) $o['amount'], 2, ',', '.') ?> ₺</b>
                                    <span><?= htmlspecialchars((string) ($o['gateway'] ?: '—')) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="mk-panel">
            <div class="mk-panel-bas">
                <h3>Destek talepleri</h3>
                <a href="tickets.php?q=<?= urlencode((string) $musteri['email']) ?>" class="btn btn-sm btn-outline">Tümü</a>
            </div>
            <?php if (!$talepler): ?>
                <div class="mk-bos">Destek talebi yok.</div>
            <?php else: ?>
                <table class="mk-tablo">
                    <tbody>
                        <?php foreach ($talepler as $t):
                            [$dAd, $dSinif] = $talepDurum[$t['status']] ?? [(string) $t['status'], 'badge'];
                            ?>
                            <tr>
                                <td>
                                    <b><a href="ticket-view.php?id=<?= (int) $t['id'] ?>">
                                        <?= htmlspecialchars(mb_strimwidth((string) $t['subject'], 0, 52, '…')) ?></a></b>
                                    <span>#<?= htmlspecialchars((string) ($t['ticket_number'] ?: $t['id'])) ?>
                                        &middot; <?= date('d.m.Y H:i', strtotime((string) $t['updated_at'])) ?></span>
                                </td>
                                <td class="mk-sag"><span class="badge <?= $dSinif ?>"><?= $dAd ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
