<?php
/**
 * VHM - VDS fiyatlandırma
 *
 * Buradaki değerler doğrudan vds-sunucu.php'deki yapılandırıcıya gidiyor;
 * min/max/step değerleri kaydırma çubuklarının sınırlarını belirliyor.
 * Önceki sürümde hiçbiri doğrulanmıyordu:
 *   - step_value 0 girilince <input type="range" step="0"> geçersiz olup
 *     çubuk kullanılamaz hâle geliyordu.
 *   - min_value > max_value girilince çubuk ölü kalıyordu.
 *   - unit_price harf girilince sessizce 0 oluyor, eksi değer kabul ediliyordu.
 *   - $_POST['pricing'] hiç gelmezse foreach uyarı veriyordu.
 *
 * Örnek fiyat hesabı IP satırını hiç katmıyordu.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
require_once dirname(__DIR__) . '/includes/Katalog.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

/** vds_pricing.resource_type enum'u ile birebir aynı */
const VD_KAYNAKLAR = [
    'base' => ['Taban', 'fa-cube', 'Her sunucuda sabit alınan tutar'],
    'cpu' => ['İşlemci', 'fa-microchip', 'Çekirdek başına'],
    'ram' => ['Bellek', 'fa-memory', 'GB başına'],
    'disk' => ['Disk', 'fa-hard-drive', 'GB başına'],
    'bandwidth' => ['Bant genişliği', 'fa-network-wired', 'Gbps başına'],
    'ip' => ['IP adresi', 'fa-ethernet', 'Adet başına'],
];

function vdsDon(string $tip, string $metin): never
{
    $_SESSION['vd_mesaj'] = ['tip' => $tip, 'metin' => $metin];
    header('Location: vds-pricing.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Guvenlik::zorunlu();

    if (!isset($_POST['kaydet'])) {
        vdsDon('error', 'Tanımsız işlem.');
    }

    /* Form hiç gelmezse eskiden foreach uyarı veriyordu */
    $gelen = $_POST['fiyat'] ?? null;
    if (!is_array($gelen) || !$gelen) {
        vdsDon('error', 'Gönderilecek satır yok.');
    }

    $mevcut = [];
    foreach (Database::fetchAll("SELECT id, resource_name FROM vds_pricing") as $r) {
        $mevcut[(int) $r['id']] = (string) $r['resource_name'];
    }

    $yazilacak = [];

    foreach ($gelen as $id => $veri) {
        $id = (int) $id;

        if (!isset($mevcut[$id]) || !is_array($veri)) {
            continue;
        }

        $ad = $mevcut[$id];

        try {
            $birimFiyat = Katalog::fiyatOku($veri['unit_price'] ?? '') ?? 0.0;
        } catch (RuntimeException $e) {
            vdsDon('error', $ad . ': ' . $e->getMessage());
        }

        $en_az = (int) ($veri['min_value'] ?? 0);
        $en_cok = (int) ($veri['max_value'] ?? 0);
        $adim = (int) ($veri['step_value'] ?? 0);

        if ($adim < 1) {
            vdsDon('error', $ad . ': artış değeri en az 1 olmalı. 0 verilirse müşteri tarafındaki kaydırma çubuğu çalışmaz.');
        }
        if ($en_az < 0 || $en_cok < 0) {
            vdsDon('error', $ad . ': alt ve üst sınır eksi olamaz.');
        }
        if ($en_az > $en_cok) {
            vdsDon('error', $ad . ': alt sınır üst sınırdan büyük olamaz.');
        }
        if ($en_cok > 100000) {
            vdsDon('error', $ad . ': üst sınır çok yüksek.');
        }
        if ($adim > max(1, $en_cok - $en_az) && $en_cok !== $en_az) {
            vdsDon('error', $ad . ': artış değeri, alt ve üst sınır arasındaki farktan büyük olamaz.');
        }

        $yazilacak[$id] = [
            'unit_price' => $birimFiyat,
            'min_value' => $en_az,
            'max_value' => $en_cok,
            'step_value' => $adim,
            'is_active' => isset($veri['is_active']) ? 1 : 0,
        ];
    }

    if (!$yazilacak) {
        vdsDon('error', 'Geçerli satır bulunamadı.');
    }

    $db = Database::getInstance();
    $db->beginTransaction();
    try {
        foreach ($yazilacak as $id => $veri) {
            Database::update('vds_pricing', $veri, 'id = ?', [$id]);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('VDS fiyatları güncellenemedi: ' . $e->getMessage());
        vdsDon('error', 'Fiyatlar kaydedilemedi, hiçbir satır değişmedi.');
    }

    vdsDon('success', count($yazilacak) . ' satır güncellendi.');
}

$mesaj = null;
if (!empty($_SESSION['vd_mesaj'])) {
    $mesaj = $_SESSION['vd_mesaj'];
    unset($_SESSION['vd_mesaj']);
}

$satirlar = Database::fetchAll("SELECT * FROM vds_pricing ORDER BY sort_order, id");

$birim = [];
foreach ($satirlar as $s) {
    $birim[(string) $s['resource_type']] = $s;
}

/** Örnek yapılandırmanın aylık tutarı */
function vdKalem(array $birim, string $tur, int $adet): float
{
    if (!isset($birim[$tur]) || (int) $birim[$tur]['is_active'] !== 1) {
        return 0.0;
    }

    return (float) $birim[$tur]['unit_price'] * $adet;
}

$ornek = [
    'cpu' => 2,
    'ram' => 4,
    'disk' => 50,
    'ip' => 1,
];

$ornekToplam = vdKalem($birim, 'base', 1);
$ornekKalemler = [];

foreach ($ornek as $tur => $adet) {
    $tutar = vdKalem($birim, $tur, $adet);
    $ornekToplam += $tutar;
    $ornekKalemler[$tur] = ['adet' => $adet, 'tutar' => $tutar];
}

$etkinSayisi = count(array_filter($satirlar, static fn(array $s): bool => (int) $s['is_active'] === 1));

function vdPara(mixed $t): string
{
    return number_format((float) $t, 2, ',', '.') . ' ₺';
}

$pageTitle = 'VDS fiyatlandırma';
$currentPage = 'vds-pricing';
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* ==========================================
       VDS fiyatlandırma - vd
       ========================================== */
    .vd-duzen {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 290px;
        gap: 18px;
        align-items: start;
    }

    .vd-panel {
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .vd-panel + .vd-panel {
        margin-top: 18px;
    }

    .vd-bas {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 16px;
        border-bottom: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
    }

    .vd-bas h3 {
        font-size: 12.5px;
        font-weight: 700;
    }

    .vd-bas span {
        font-size: 12px;
        color: var(--y-metin-3);
    }

    .vd-satir {
        display: grid;
        grid-template-columns: 210px repeat(4, minmax(78px, 1fr)) 70px;
        gap: 12px;
        align-items: end;
        padding: 14px 16px;
        border-bottom: 1px solid var(--y-cizgi-soft);
    }

    .vd-satir:last-of-type {
        border-bottom: none;
    }

    .vd-satir.pasif {
        opacity: .58;
    }

    .vd-kaynak {
        display: flex;
        align-items: center;
        gap: 11px;
        min-width: 0;
    }

    .vd-ikon {
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

    .vd-kaynak b {
        display: block;
        font-size: 13.5px;
        font-weight: 600;
        color: var(--y-metin);
    }

    .vd-kaynak small {
        display: block;
        margin-top: 1px;
        font-size: 11px;
        color: var(--y-metin-3);
    }

    .vd-alan label {
        display: block;
        margin-bottom: 4px;
        font-size: 11px;
        color: var(--y-metin-3);
    }

    .vd-alan .form-control {
        padding: 7px 9px;
        font-size: 13px;
    }

    .vd-durum {
        display: flex;
        align-items: center;
        justify-content: center;
        padding-bottom: 8px;
    }

    .vd-baslik {
        display: grid;
        grid-template-columns: 210px repeat(4, minmax(78px, 1fr)) 70px;
        gap: 12px;
        padding: 9px 16px;
        border-bottom: 1px solid var(--y-cizgi);
        background: var(--y-zemin);
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--y-metin-3);
    }

    .vd-baslik span:not(:first-child) {
        text-align: center;
    }

    .vd-alt {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        padding: 13px 16px;
        border-top: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
    }

    .vd-alt small {
        font-size: 12px;
        color: var(--y-metin-3);
    }

    /* Örnek hesap */
    .vd-ornek-satir {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 8px 0;
        border-bottom: 1px solid var(--y-cizgi-soft);
        font-size: 13px;
        color: var(--y-metin-2);
    }

    .vd-ornek-satir:last-of-type {
        border-bottom: none;
    }

    .vd-ornek-toplam {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        margin-top: 8px;
        padding-top: 12px;
        border-top: 2px solid var(--y-cizgi);
        font-size: 17px;
        font-weight: 700;
        color: var(--y-metin);
    }

    .vd-ornek-toplam span:last-child {
        color: var(--y-primary);
    }

    .vd-ipucu {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 12px 16px;
        border-top: 1px solid var(--y-cizgi);
        font-size: 12.5px;
        line-height: 1.6;
        color: var(--y-metin-3);
    }

    .vd-ipucu i {
        margin-top: 2px;
        color: var(--y-primary);
    }

    @media (max-width: 1100px) {
        .vd-duzen {
            grid-template-columns: minmax(0, 1fr);
        }

        .vd-baslik {
            display: none;
        }

        .vd-satir {
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
        }

        .vd-kaynak {
            grid-column: 1/-1;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>VDS fiyatlandırma</h1>
        <p>Müşterinin sunucu yapılandırırken gördüğü birim fiyatlar ve sınırlar</p>
    </div>
    <a href="/vds-sunucu.php" target="_blank" rel="noopener" class="btn btn-outline">
        <i class="fas fa-arrow-up-right-from-square"></i> Sayfayı gör
    </a>
</div>

<?php if ($mesaj): ?>
    <div class="alert alert-<?= htmlspecialchars($mesaj['tip']) ?>">
        <?= htmlspecialchars($mesaj['metin']) ?>
    </div>
<?php endif; ?>

<div class="vd-duzen">
    <div class="vd-panel">
        <div class="vd-bas">
            <h3>Kaynak fiyatları</h3>
            <span><?= $etkinSayisi ?> / <?= count($satirlar) ?> etkin</span>
        </div>

        <?php if (!$satirlar): ?>
            <div style="padding:36px 16px;text-align:center;color:var(--y-metin-3);font-size:13px">
                Fiyat satırı yok.
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="kaydet" value="1">

                <div class="vd-baslik">
                    <span>Kaynak</span>
                    <span>Birim fiyat</span>
                    <span>Alt sınır</span>
                    <span>Üst sınır</span>
                    <span>Artış</span>
                    <span>Etkin</span>
                </div>

                <?php foreach ($satirlar as $s):
                    $id = (int) $s['id'];
                    $tur = (string) $s['resource_type'];
                    [$kAd, $kIkon, $kNot] = VD_KAYNAKLAR[$tur] ?? [$tur, 'fa-cube', ''];
                    $etkin = (int) $s['is_active'] === 1;
                    ?>
                    <div class="vd-satir <?= $etkin ? '' : 'pasif' ?>">
                        <div class="vd-kaynak">
                            <span class="vd-ikon"><i class="fas <?= $kIkon ?>"></i></span>
                            <span>
                                <b><?= htmlspecialchars((string) $s['resource_name']) ?></b>
                                <small><?= htmlspecialchars($kNot) ?><?= !empty($s['unit_label'])
                                    ? ' · ' . htmlspecialchars((string) $s['unit_label']) : '' ?></small>
                            </span>
                        </div>

                        <div class="vd-alan">
                            <label for="fiyat-<?= $id ?>">Birim fiyat</label>
                            <input type="text" name="fiyat[<?= $id ?>][unit_price]" id="fiyat-<?= $id ?>"
                                class="form-control" inputmode="decimal"
                                value="<?= number_format((float) $s['unit_price'], 2, ',', '') ?>">
                        </div>

                        <div class="vd-alan">
                            <label for="min-<?= $id ?>">Alt sınır</label>
                            <input type="number" name="fiyat[<?= $id ?>][min_value]" id="min-<?= $id ?>"
                                class="form-control" min="0" max="100000"
                                value="<?= (int) $s['min_value'] ?>">
                        </div>

                        <div class="vd-alan">
                            <label for="max-<?= $id ?>">Üst sınır</label>
                            <input type="number" name="fiyat[<?= $id ?>][max_value]" id="max-<?= $id ?>"
                                class="form-control" min="0" max="100000"
                                value="<?= (int) $s['max_value'] ?>">
                        </div>

                        <div class="vd-alan">
                            <label for="adim-<?= $id ?>">Artış</label>
                            <input type="number" name="fiyat[<?= $id ?>][step_value]" id="adim-<?= $id ?>"
                                class="form-control" min="1" max="100000"
                                value="<?= (int) $s['step_value'] ?>">
                        </div>

                        <div class="vd-durum">
                            <label class="sc-onay" style="gap:6px;font-size:12px">
                                <input type="checkbox" name="fiyat[<?= $id ?>][is_active]" value="1"
                                    <?= $etkin ? 'checked' : '' ?>>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="vd-alt">
                    <small>Artış değeri, müşterinin kaydırma çubuğunu kaç kaç oynatacağını belirler.</small>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-floppy-disk"></i> Fiyatları kaydet
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div>
        <div class="vd-panel">
            <div class="vd-bas"><h3>Örnek yapılandırma</h3></div>
            <div style="padding:16px">
                <div class="vd-ornek-satir">
                    <span>Taban</span>
                    <span><?= vdPara(vdKalem($birim, 'base', 1)) ?></span>
                </div>
                <?php foreach ($ornekKalemler as $tur => $k):
                    [$kAd] = VD_KAYNAKLAR[$tur] ?? [$tur];
                    $etiket = (string) ($birim[$tur]['unit_label'] ?? '');
                    ?>
                    <div class="vd-ornek-satir">
                        <span><?= $k['adet'] ?> <?= htmlspecialchars($etiket !== '' ? $etiket : $kAd) ?></span>
                        <span><?= vdPara($k['tutar']) ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="vd-ornek-toplam">
                    <span>Aylık</span>
                    <span><?= vdPara($ornekToplam) ?></span>
                </div>
            </div>
            <div class="vd-ipucu">
                <i class="fas fa-circle-info"></i>
                <span>Pasif satırlar hesaba katılmaz. IP satırı önceki sürümde
                    örnek hesaba hiç dahil edilmiyordu.</span>
            </div>
        </div>
    </div>
</div>

<style>
    .sc-onay {
        display: flex;
        align-items: center;
        gap: 7px;
        color: var(--y-metin-2);
        cursor: pointer;
    }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
