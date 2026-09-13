<?php
/**
 * VHM - Hizmet detayı
 *
 * Önceki sürümdeki sorunlar:
 *   - Oturum kontrolü header.php'ye bırakılmıştı; POST işleyicisi ondan
 *     önce çalıştığı için yetkisiz bir istek hizmeti askıya alabiliyordu.
 *   - Durum doğrudan $_POST'tan alınıp yazılıyordu, enum dışı değer
 *     denetlenmiyordu.
 *   - Hizmet sonlandırıldığında termination_date hiç yazılmıyordu.
 *   - PRG yoktu; yenilemede aynı bildirim e-postası tekrar gidiyordu.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);

/* services.status enum'u ile birebir aynı */
const HZ_DURUMLAR = [
    'pending' => ['Bekliyor', 'badge-warning'],
    'active' => ['Etkin', 'badge-success'],
    'suspended' => ['Askıda', 'badge-danger'],
    'terminated' => ['Sonlandırıldı', 'badge'],
    'cancelled' => ['İptal', 'badge'],
];

const HZ_DONEMLER = [
    'monthly' => 'Aylık', 'quarterly' => '3 aylık', 'semiannually' => '6 aylık',
    'annually' => 'Yıllık', 'biennially' => '2 yıllık', 'triennially' => '3 yıllık',
    'onetime' => 'Tek seferlik',
];

$hizmet = $id > 0 ? Database::fetch(
    "SELECT s.*, p.name AS urun, p.type AS urun_tipi,
            c.first_name, c.last_name, c.email, c.phone,
            sv.name AS sunucu, sv.hostname AS sunucu_adres
       FROM services s
       LEFT JOIN products p ON p.id = s.product_id
       LEFT JOIN clients c ON c.id = s.client_id
       LEFT JOIN servers sv ON sv.id = s.server_id
      WHERE s.id = ?",
    [$id]
) : null;

if (!$hizmet) {
    $_SESSION['hz_mesaj'] = ['tip' => 'error', 'metin' => 'Hizmet bulunamadı.'];
    header('Location: services.php');
    exit;
}

function hizmetDon(string $tip, string $metin, int $id): never
{
    $_SESSION['hv_mesaj'] = ['tip' => $tip, 'metin' => $metin];
    header('Location: service-view.php?id=' . $id);
    exit;
}

$esxiSunuculari = [];
try {
    $esxiSunuculari = Database::fetchAll(
        "SELECT id, name, ip_address FROM esxi_servers WHERE is_active = 1 ORDER BY name"
    );
} catch (Throwable $e) {
    error_log('ESXi sunucuları okunamadı: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Guvenlik::zorunlu();

    /* ---------- Durum ---------- */
    if (isset($_POST['durum'])) {
        $yeni = (string) $_POST['durum'];
        $eski = (string) $hizmet['status'];

        if (!isset(HZ_DURUMLAR[$yeni])) {
            hizmetDon('error', 'Geçersiz hizmet durumu.', $id);
        }
        if ($yeni === $eski) {
            hizmetDon('uyari', 'Hizmet zaten "' . HZ_DURUMLAR[$yeni][0] . '" durumunda.', $id);
        }

        $gerekce = trim((string) ($_POST['gerekce'] ?? ''));

        /* services tablosunda gerekçe sütunu yok; uydurmak yerine
           yönetici notuna tarihli satır olarak ekliyoruz. */
        $not = (string) ($hizmet['admin_notes'] ?? '');
        if ($gerekce !== '' && ($yeni === 'suspended' || $yeni === 'terminated')) {
            $not = trim($not . "\n" . date('d.m.Y H:i') . ' — '
                . HZ_DURUMLAR[$yeni][0] . ': ' . $gerekce);
        }

        try {
            if ($yeni === 'terminated') {
                /* Sonlandırma tarihi eskiden hiç yazılmıyordu */
                Database::query(
                    "UPDATE services SET status = ?, termination_date = CURDATE(),
                            admin_notes = ? WHERE id = ?",
                    [$yeni, $not, $id]
                );
            } else {
                Database::query(
                    "UPDATE services SET status = ?, admin_notes = ? WHERE id = ?",
                    [$yeni, $not, $id]
                );
            }
        } catch (Throwable $e) {
            error_log('Hizmet durumu güncellenemedi: ' . $e->getMessage());
            hizmetDon('error', 'Hizmet durumu güncellenemedi.', $id);
        }

        hizmetBildir($hizmet, $eski, $yeni, $gerekce);
        hizmetDon('success', 'Hizmet durumu "' . HZ_DURUMLAR[$yeni][0] . '" olarak güncellendi.', $id);
    }

    /* ---------- ESXi eşlemesi ---------- */
    if (isset($_POST['esxi_kaydet'])) {
        $sunucuId = (int) ($_POST['esxi_server_id'] ?? 0) ?: null;
        $vmid = trim((string) ($_POST['esxi_vmid'] ?? '')) ?: null;
        $vmAdi = trim((string) ($_POST['vm_hostname'] ?? '')) ?: null;

        /* Listede olmayan bir sunucu kimliği gönderilebiliyordu */
        if ($sunucuId !== null && !in_array($sunucuId, array_map('intval', array_column($esxiSunuculari, 'id')), true)) {
            hizmetDon('error', 'Seçilen ESXi sunucusu bulunamadı.', $id);
        }
        if ($vmid !== null && !preg_match('/^[A-Za-z0-9._-]{1,64}$/', $vmid)) {
            hizmetDon('error', 'VM kimliği yalnızca harf, rakam, nokta, tire ve alt çizgi içerebilir.', $id);
        }

        try {
            Database::query(
                "UPDATE services SET esxi_server_id = ?, esxi_vmid = ?, vm_hostname = ? WHERE id = ?",
                [$sunucuId, $vmid, $vmAdi, $id]
            );
        } catch (Throwable $e) {
            error_log('ESXi eşlemesi kaydedilemedi: ' . $e->getMessage());
            hizmetDon('error', 'ESXi bilgileri kaydedilemedi.', $id);
        }

        hizmetDon('success', 'ESXi VM bilgileri güncellendi.', $id);
    }

    hizmetDon('error', 'Tanımsız işlem.', $id);
}

/** Durum değişikliği bildirimi; gönderilemezse işlem yine tamamlanmıştır */
function hizmetBildir(array $hizmet, string $eski, string $yeni, string $gerekce): void
{
    if (empty($hizmet['email'])) {
        return;
    }

    $sablon = null;
    $ek = [];

    if ($yeni === 'active') {
        $sablon = $eski === 'suspended' ? 'service_unsuspended' : 'service_activated';
        if ($sablon === 'service_activated') {
            $ek = [
                'domain' => (string) ($hizmet['domain'] ?: '-'),
                'ip_address' => (string) ($hizmet['dedicated_ip'] ?: '-'),
                'username' => (string) ($hizmet['username'] ?: '-'),
                'password' => '(Panelden görüntüleyin)',
            ];
        }
    } elseif ($yeni === 'suspended') {
        $sablon = 'service_suspended';
        $ek = [
            'suspend_reason' => $gerekce !== '' ? $gerekce : 'Ödeme bekleniyor',
            'suspend_date' => date('d.m.Y H:i'),
        ];
    } elseif ($yeni === 'terminated') {
        $sablon = 'service_terminated';
        $ek = [
            'termination_reason' => $gerekce !== '' ? $gerekce : 'Talep üzerine',
            'termination_date' => date('d.m.Y H:i'),
        ];
    }

    if ($sablon === null) {
        return;
    }

    try {
        require_once dirname(__DIR__) . '/includes/Mail.php';
        Mail::sendTemplate($sablon, (string) $hizmet['email'], array_merge([
            'client_name' => trim((string) $hizmet['first_name'] . ' ' . (string) $hizmet['last_name']),
            'product_name' => (string) ($hizmet['urun'] ?: 'Hizmet'),
        ], $ek), (string) $hizmet['first_name']);
    } catch (Throwable $e) {
        error_log('Hizmet bildirimi gönderilemedi: ' . $e->getMessage());
    }
}

$mesaj = null;
if (!empty($_SESSION['hv_mesaj'])) {
    $mesaj = $_SESSION['hv_mesaj'];
    unset($_SESSION['hv_mesaj']);
}

$faturalar = Database::fetchAll(
    "SELECT DISTINCT i.*
       FROM invoices i
       JOIN invoice_items ii ON ii.invoice_id = i.id
      WHERE ii.service_id = ?
      ORDER BY i.created_at DESC
      LIMIT 15",
    [$id]
);

$talepler = Database::fetchAll(
    "SELECT id, ticket_number, subject, status, updated_at
       FROM tickets WHERE service_id = ? ORDER BY updated_at DESC LIMIT 10",
    [$id]
);

$modulVerisi = [];
if (!empty($hizmet['module_data'])) {
    $cozulen = json_decode((string) $hizmet['module_data'], true);
    if (is_array($cozulen)) {
        $modulVerisi = $cozulen;
    }
}

[$durumAd, $durumSinif] = HZ_DURUMLAR[$hizmet['status']] ?? [(string) $hizmet['status'], 'badge'];
$adSoyad = trim((string) $hizmet['first_name'] . ' ' . (string) $hizmet['last_name']);
$paraBirimi = (string) ($hizmet['currency'] ?: 'TRY');

$vadeGun = null;
if (!empty($hizmet['next_due_date'])) {
    $vadeGun = (int) floor((strtotime((string) $hizmet['next_due_date']) - strtotime('today')) / 86400);
}

$faturaDurum = [
    'draft' => ['Taslak', 'badge'], 'unpaid' => ['Ödenmedi', 'badge-warning'],
    'paid' => ['Ödendi', 'badge-success'], 'cancelled' => ['İptal', 'badge'],
    'refunded' => ['İade', 'badge-info'], 'collections' => ['Takipte', 'badge-danger'],
];

$pageTitle = (string) ($hizmet['urun'] ?: 'Hizmet');
$currentPage = 'services';
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* ==========================================
       Hizmet detayı - hv
       ========================================== */
    .hv-duzen {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 300px;
        gap: 18px;
        align-items: start;
    }

    .hv-panel {
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .hv-panel + .hv-panel {
        margin-top: 18px;
    }

    .hv-panel-bas {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 16px;
        border-bottom: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
    }

    .hv-panel-bas h3 {
        font-size: 12.5px;
        font-weight: 700;
    }

    .hv-panel-govde {
        padding: 16px;
    }

    .hv-ozet {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1px;
        background: var(--y-cizgi);
    }

    .hv-ozet-kutu {
        padding: 14px 16px;
        background: var(--y-yuzey);
    }

    .hv-ozet-kutu b {
        display: block;
        font-size: 17px;
        font-weight: 700;
        letter-spacing: -.02em;
        color: var(--y-metin);
        line-height: 1.3;
    }

    .hv-ozet-kutu span {
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .hv-ozet-kutu.gecmis b {
        color: var(--y-danger);
    }

    .hv-ozet-kutu.yakin b {
        color: var(--y-warning);
    }

    .hv-bilgi-satir {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 9px 0;
        border-bottom: 1px solid var(--y-cizgi-soft);
        font-size: 13px;
    }

    .hv-bilgi-satir:last-child {
        border-bottom: none;
    }

    .hv-bilgi-satir span:first-child {
        color: var(--y-metin-3);
        white-space: nowrap;
    }

    .hv-bilgi-satir span:last-child {
        text-align: right;
        color: var(--y-metin);
        word-break: break-word;
    }

    .hv-kod {
        font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace;
        font-size: 12.5px;
    }

    .hv-tablo {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .hv-tablo td {
        padding: 10px 16px;
        border-bottom: 1px solid var(--y-cizgi-soft);
    }

    .hv-tablo tr:last-child td {
        border-bottom: none;
    }

    .hv-tablo tr:hover td {
        background: var(--y-yuzey-2);
    }

    .hv-tablo b {
        font-weight: 600;
        color: var(--y-metin);
    }

    .hv-tablo span {
        display: block;
        margin-top: 1px;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .hv-sag {
        text-align: right;
        white-space: nowrap;
    }

    .hv-bos {
        padding: 24px 16px;
        text-align: center;
        font-size: 13px;
        color: var(--y-metin-3);
    }

    .hv-form label {
        display: block;
        margin-bottom: 4px;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .hv-form .form-control {
        margin-bottom: 10px;
    }

    .hv-form .btn {
        width: 100%;
        justify-content: center;
    }

    @media (max-width: 980px) {
        .hv-duzen {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

<div class="page-header">
    <div>
        <h1><?= htmlspecialchars((string) ($hizmet['urun'] ?: 'Ürün silinmiş')) ?></h1>
        <p><a href="services.php">Hizmetler</a> &rsaquo;
            <?= htmlspecialchars((string) ($hizmet['domain'] ?: '#' . $id)) ?></p>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
        <span class="badge <?= $durumSinif ?>" style="font-size:12.5px;padding:7px 14px"><?= $durumAd ?></span>
        <a href="service-edit.php?id=<?= $id ?>" class="btn btn-primary">
            <i class="fas fa-pen"></i> Düzenle
        </a>
    </div>
</div>

<?php if ($mesaj): ?>
    <div class="alert alert-<?= htmlspecialchars($mesaj['tip']) ?>">
        <?= htmlspecialchars($mesaj['metin']) ?>
    </div>
<?php endif; ?>

<div class="hv-duzen">
    <div>
        <div class="hv-panel">
            <div class="hv-ozet">
                <div class="hv-ozet-kutu">
                    <b><?= number_format((float) $hizmet['amount'], 2, ',', '.') ?> <?= $paraBirimi ?></b>
                    <span><?= HZ_DONEMLER[$hizmet['billing_cycle']] ?? htmlspecialchars((string) $hizmet['billing_cycle']) ?></span>
                </div>
                <div class="hv-ozet-kutu <?= $vadeGun !== null && $vadeGun < 0 ? 'gecmis' : ($vadeGun !== null && $vadeGun <= 7 ? 'yakin' : '') ?>">
                    <b><?= !empty($hizmet['next_due_date'])
                        ? date('d.m.Y', strtotime((string) $hizmet['next_due_date'])) : '—' ?></b>
                    <span><?php
                        if ($vadeGun === null) {
                            echo 'Vade yok';
                        } elseif ($vadeGun < 0) {
                            echo abs($vadeGun) . ' gün gecikti';
                        } elseif ($vadeGun === 0) {
                            echo 'Bugün';
                        } else {
                            echo $vadeGun . ' gün kaldı';
                        }
                    ?></span>
                </div>
                <div class="hv-ozet-kutu">
                    <b><?= !empty($hizmet['registration_date'])
                        ? date('d.m.Y', strtotime((string) $hizmet['registration_date'])) : '—' ?></b>
                    <span>Kayıt tarihi</span>
                </div>
                <div class="hv-ozet-kutu">
                    <b><?= count($faturalar) ?></b>
                    <span>Fatura</span>
                </div>
            </div>
        </div>

        <div class="hv-panel">
            <div class="hv-panel-bas"><h3>Hizmet bilgileri</h3></div>
            <div class="hv-panel-govde">
                <div class="hv-bilgi-satir">
                    <span>Ürün</span>
                    <span><?= htmlspecialchars((string) ($hizmet['urun'] ?: 'Ürün silinmiş')) ?>
                        <?= !empty($hizmet['urun_tipi']) ? ' (' . htmlspecialchars((string) $hizmet['urun_tipi']) . ')' : '' ?></span>
                </div>
                <div class="hv-bilgi-satir">
                    <span>Alan adı</span>
                    <span class="hv-kod" dir="ltr"><?= htmlspecialchars((string) ($hizmet['domain'] ?: '—')) ?></span>
                </div>
                <?php if (!empty($hizmet['username'])): ?>
                    <div class="hv-bilgi-satir">
                        <span>Kullanıcı adı</span>
                        <span class="hv-kod" dir="ltr"><?= htmlspecialchars((string) $hizmet['username']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($hizmet['dedicated_ip'])): ?>
                    <div class="hv-bilgi-satir">
                        <span>IP adresi</span>
                        <span class="hv-kod" dir="ltr"><?= htmlspecialchars((string) $hizmet['dedicated_ip']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="hv-bilgi-satir">
                    <span>Sunucu</span>
                    <span><?= htmlspecialchars((string) ($hizmet['sunucu'] ?: 'Atanmamış')) ?>
                        <?= !empty($hizmet['sunucu_adres'])
                            ? ' · ' . htmlspecialchars((string) $hizmet['sunucu_adres']) : '' ?></span>
                </div>
                <?php if (!empty($hizmet['vm_hostname']) || !empty($hizmet['esxi_vmid'])): ?>
                    <div class="hv-bilgi-satir">
                        <span>Sanal makine</span>
                        <span class="hv-kod" dir="ltr">
                            <?= htmlspecialchars((string) ($hizmet['vm_hostname'] ?: '—')) ?>
                            <?= !empty($hizmet['esxi_vmid']) ? ' #' . htmlspecialchars((string) $hizmet['esxi_vmid']) : '' ?>
                        </span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($hizmet['termination_date'])): ?>
                    <div class="hv-bilgi-satir">
                        <span>Sonlandırma</span>
                        <span><?= date('d.m.Y', strtotime((string) $hizmet['termination_date'])) ?></span>
                    </div>
                <?php endif; ?>
                <?php foreach ($modulVerisi as $anahtar => $deger): ?>
                    <?php if (is_scalar($deger)): ?>
                        <div class="hv-bilgi-satir">
                            <span><?= htmlspecialchars((string) $anahtar) ?></span>
                            <span><?= htmlspecialchars((string) $deger) ?></span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="hv-panel">
            <div class="hv-panel-bas"><h3>Faturalar</h3></div>
            <?php if (!$faturalar): ?>
                <div class="hv-bos">Bu hizmete ait fatura yok.</div>
            <?php else: ?>
                <table class="hv-tablo">
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
                                <td class="hv-sag"><b><?= number_format((float) $f['total'], 2, ',', '.') ?>
                                        <?= htmlspecialchars((string) ($f['currency'] ?: $paraBirimi)) ?></b></td>
                                <td class="hv-sag"><span class="badge <?= $dSinif ?>"><?= $dAd ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if ($talepler): ?>
            <div class="hv-panel">
                <div class="hv-panel-bas"><h3>Destek talepleri</h3></div>
                <table class="hv-tablo">
                    <tbody>
                        <?php foreach ($talepler as $t): ?>
                            <tr>
                                <td>
                                    <b><a href="ticket-view.php?id=<?= (int) $t['id'] ?>">
                                        <?= htmlspecialchars(mb_strimwidth((string) $t['subject'], 0, 56, '…')) ?></a></b>
                                    <span><?= date('d.m.Y H:i', strtotime((string) $t['updated_at'])) ?></span>
                                </td>
                                <td class="hv-sag"><span class="badge"><?= htmlspecialchars((string) $t['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div>
        <div class="hv-panel">
            <div class="hv-panel-bas"><h3>Durum değiştir</h3></div>
            <div class="hv-panel-govde hv-form">
                <form method="POST">
                    <label for="durum">Yeni durum</label>
                    <select name="durum" id="durum" class="form-control" onchange="hvGerekce(this.value)">
                        <?php foreach (HZ_DURUMLAR as $deger => [$ad, $sinif]): ?>
                            <option value="<?= $deger ?>" <?= $hizmet['status'] === $deger ? 'selected' : '' ?>>
                                <?= $ad ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="hv-gerekce-alani" hidden>
                        <label for="gerekce">Gerekçe (müşteriye gider)</label>
                        <input type="text" name="gerekce" id="gerekce" class="form-control" maxlength="200"
                            placeholder="Ödeme bekleniyor">
                    </div>
                    <button type="submit" class="btn btn-primary">Durumu kaydet</button>
                </form>
            </div>
        </div>

        <?php if ($esxiSunuculari): ?>
            <div class="hv-panel">
                <div class="hv-panel-bas"><h3>ESXi eşlemesi</h3></div>
                <div class="hv-panel-govde hv-form">
                    <form method="POST">
                        <input type="hidden" name="esxi_kaydet" value="1">
                        <label for="esxi_server_id">Sunucu</label>
                        <select name="esxi_server_id" id="esxi_server_id" class="form-control">
                            <option value="0">Seçilmedi</option>
                            <?php foreach ($esxiSunuculari as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"
                                    <?= (int) $hizmet['esxi_server_id'] === (int) $s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $s['name']) ?>
                                    (<?= htmlspecialchars((string) $s['ip_address']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="esxi_vmid">VM kimliği</label>
                        <input type="text" name="esxi_vmid" id="esxi_vmid" class="form-control" maxlength="64"
                            value="<?= htmlspecialchars((string) ($hizmet['esxi_vmid'] ?? '')) ?>">
                        <label for="vm_hostname">VM adı</label>
                        <input type="text" name="vm_hostname" id="vm_hostname" class="form-control" maxlength="120"
                            value="<?= htmlspecialchars((string) ($hizmet['vm_hostname'] ?? '')) ?>">
                        <button type="submit" class="btn btn-outline">Eşlemeyi kaydet</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="hv-panel">
            <div class="hv-panel-bas"><h3>Müşteri</h3></div>
            <div class="hv-panel-govde">
                <div class="hv-bilgi-satir">
                    <span>Ad soyad</span>
                    <span><?= htmlspecialchars($adSoyad !== '' ? $adSoyad : 'Müşteri silinmiş') ?></span>
                </div>
                <div class="hv-bilgi-satir">
                    <span>E-posta</span>
                    <span><a href="mailto:<?= htmlspecialchars((string) $hizmet['email']) ?>" dir="ltr"
                            style="color:var(--y-primary)"><?= htmlspecialchars((string) $hizmet['email']) ?></a></span>
                </div>
                <?php if (!empty($hizmet['phone'])): ?>
                    <div class="hv-bilgi-satir">
                        <span>Telefon</span>
                        <span dir="ltr"><?= htmlspecialchars((string) $hizmet['phone']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($hizmet['client_id'])): ?>
                    <a href="client-view.php?id=<?= (int) $hizmet['client_id'] ?>"
                        class="btn btn-sm btn-outline" style="width:100%;justify-content:center;margin-top:12px">
                        Müşteri kartı
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($hizmet['admin_notes'])): ?>
            <div class="hv-panel">
                <div class="hv-panel-bas"><h3>Yönetici notu</h3></div>
                <div class="hv-panel-govde"
                    style="font-size:13px;line-height:1.65;color:var(--y-metin-2)">
                    <?= nl2br(htmlspecialchars((string) $hizmet['admin_notes'])) ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    /* Gerekçe kutusu yalnızca müşteriye bildirim giden durumlarda görünür */
    function hvGerekce(deger) {
        document.getElementById('hv-gerekce-alani').hidden =
            deger !== 'suspended' && deger !== 'terminated';
    }
    hvGerekce(document.getElementById('durum').value);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
