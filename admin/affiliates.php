<?php
/**
 * VHM - Satış ortakları
 *
 * Önceki sürümdeki sorunlar:
 *   - Durum, komisyon ve not güncellemeleri POST ile çalışıyordu ama
 *     CSRF belirteci doğrulanmıyordu.
 *   - $_POST['status'] ve $_POST['commission_type'] enum dışı değer
 *     kabul ediyordu.
 *   - Komisyon oranında sınır yoktu; negatif ya da %1000 girilebiliyordu.
 *   - PRG yoktu; yenilemede aynı güncelleme tekrar uygulanıyordu.
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

$pageTitle = 'Satış ortakları';
$currentPage = 'affiliates';

/* affiliates.status enum'u ile birebir aynı */
$durumlar = [
    'pending' => ['Onay bekliyor', 'badge-warning'],
    'active' => ['Etkin', 'badge-success'],
    'suspended' => ['Askıda', 'badge-danger'],
    'rejected' => ['Reddedildi', 'badge'],
];

$komisyonTuru = [
    'percentage' => 'Yüzde',
    'fixed' => 'Sabit tutar',
];

/* Tablo yoksa sayfa çökmesin */
$tabloVar = true;
try {
    Database::fetchColumn("SELECT 1 FROM affiliates LIMIT 1");
} catch (Throwable $e) {
    $tabloVar = false;
    error_log('Satış ortağı tablosu okunamadı: ' . $e->getMessage());
}

function ortakMesaj(string $tip, string $metin): void
{
    $_SESSION['ortak_mesaj'] = ['tip' => $tip, 'metin' => $metin];
    header('Location: affiliates.php');
    exit;
}

/* ---------- Durum ---------- */
if ($tabloVar && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['durum_guncelle'])) {
    Guvenlik::zorunlu();
    $id = (int) ($_POST['affiliate_id'] ?? 0);
    $yeni = (string) ($_POST['status'] ?? '');

    if ($id <= 0 || !isset($durumlar[$yeni])) {
        ortakMesaj('error', 'Geçersiz ortak durumu.');
    }

    try {
        /* Onay bilgisi yalnızca etkinleştirmede yazılır, aksi hâlde temizlenir */
        Database::query(
            "UPDATE affiliates SET status = ?, approved_at = ?, approved_by = ? WHERE id = ?",
            [
                $yeni,
                $yeni === 'active' ? date('Y-m-d H:i:s') : null,
                $yeni === 'active' ? (int) $_SESSION['admin_id'] : null,
                $id,
            ]
        );
        ortakMesaj('success', 'Ortak durumu "' . $durumlar[$yeni][0] . '" olarak güncellendi.');
    } catch (Throwable $e) {
        error_log('Ortak durumu güncellenemedi: ' . $e->getMessage());
        ortakMesaj('error', 'Durum güncellenemedi.');
    }
}

/* ---------- Komisyon ---------- */
if ($tabloVar && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['komisyon_guncelle'])) {
    Guvenlik::zorunlu();
    $id = (int) ($_POST['affiliate_id'] ?? 0);
    $tur = (string) ($_POST['commission_type'] ?? '');
    $oran = (float) str_replace(',', '.', (string) ($_POST['commission_rate'] ?? '0'));

    if ($id <= 0 || !isset($komisyonTuru[$tur])) {
        ortakMesaj('error', 'Geçersiz komisyon türü.');
    }
    /* Yüzde 0-100 arası olmalı; sabit tutarda üst sınır yok ama negatif olamaz */
    if ($oran < 0) {
        ortakMesaj('error', 'Komisyon değeri negatif olamaz.');
    }
    if ($tur === 'percentage' && $oran > 100) {
        ortakMesaj('error', 'Yüzde komisyon 100\'den büyük olamaz.');
    }

    try {
        Database::query(
            "UPDATE affiliates SET commission_rate = ?, commission_type = ? WHERE id = ?",
            [round($oran, 2), $tur, $id]
        );
        ortakMesaj('success', 'Komisyon güncellendi.');
    } catch (Throwable $e) {
        error_log('Komisyon güncellenemedi: ' . $e->getMessage());
        ortakMesaj('error', 'Komisyon güncellenemedi.');
    }
}

/* ---------- Not ---------- */
if ($tabloVar && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['not_guncelle'])) {
    Guvenlik::zorunlu();
    $id = (int) ($_POST['affiliate_id'] ?? 0);
    $not = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 2000);

    try {
        Database::query("UPDATE affiliates SET notes = ? WHERE id = ?", [$not !== '' ? $not : null, $id]);
        ortakMesaj('success', 'Not kaydedildi.');
    } catch (Throwable $e) {
        error_log('Ortak notu kaydedilemedi: ' . $e->getMessage());
        ortakMesaj('error', 'Not kaydedilemedi.');
    }
}

$mesaj = null;
if (!empty($_SESSION['ortak_mesaj'])) {
    $mesaj = $_SESSION['ortak_mesaj'];
    unset($_SESSION['ortak_mesaj']);
}

/* ---------- Veriler ---------- */
$suzgec = (string) ($_GET['status'] ?? '');
$arama = trim((string) ($_GET['q'] ?? $_GET['search'] ?? ''));
$sayfa = max(1, (int) ($_GET['page'] ?? 1));
$adet = 20;

$ortaklar = [];
$toplam = 0;
$sayfaSayisi = 1;
$ozet = [];
$bekleyenCekim = 0;

if ($tabloVar) {
    $kosul = [];
    $par = [];
    if (isset($durumlar[$suzgec])) {
        $kosul[] = "a.status = ?";
        $par[] = $suzgec;
    }
    if ($arama !== '') {
        $kosul[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR a.affiliate_code LIKE ?)";
        $desen = '%' . str_replace(['%', '_'], ['\%', '\_'], $arama) . '%';
        $par = array_merge($par, array_fill(0, 4, $desen));
    }
    $nerede = $kosul ? ' WHERE ' . implode(' AND ', $kosul) : '';
    $atla = ($sayfa - 1) * $adet;

    $guvenli = static function (string $sql, array $p = []): float {
        try {
            return (float) Database::fetchColumn($sql, $p);
        } catch (Throwable $e) {
            error_log('Ortak sorgusu: ' . $e->getMessage());
            return 0;
        }
    };

    $toplam = (int) $guvenli(
        "SELECT COUNT(*) FROM affiliates a LEFT JOIN clients c ON c.id = a.client_id" . $nerede,
        $par
    );
    $sayfaSayisi = max(1, (int) ceil($toplam / $adet));

    try {
        $ortaklar = Database::fetchAll(
            "SELECT a.*, c.first_name, c.last_name, c.email
               FROM affiliates a
               LEFT JOIN clients c ON c.id = a.client_id
               {$nerede}
              ORDER BY a.created_at DESC
              LIMIT {$adet} OFFSET {$atla}",
            $par
        );
    } catch (Throwable $e) {
        error_log('Ortak listesi okunamadı: ' . $e->getMessage());
        $ortaklar = [];
    }

    $ozet = [
        ['Tüm ortaklar', $guvenli("SELECT COUNT(*) FROM affiliates"), 'fa-handshake', '', false],
        ['Onay bekleyen', $guvenli("SELECT COUNT(*) FROM affiliates WHERE status = 'pending'"), 'fa-clock', 'pending', false],
        ['Etkin', $guvenli("SELECT COUNT(*) FROM affiliates WHERE status = 'active'"), 'fa-circle-check', 'active', false],
        ['Ödenecek bakiye', $guvenli("SELECT COALESCE(SUM(balance),0) FROM affiliates"), 'fa-wallet', '', true],
    ];

    $bekleyenCekim = (int) $guvenli(
        "SELECT COUNT(*) FROM affiliate_withdrawals WHERE status IN ('pending','processing')"
    );
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* ==========================================
       Satış ortakları - or
       ========================================== */
    .or-ozet {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }

    .or-ozet-kart {
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

    .or-ozet-kart:hover {
        border-color: var(--y-primary);
        text-decoration: none;
    }

    .or-ozet-kart.secili {
        border-color: var(--y-primary);
        background: var(--y-primary-soft);
    }

    .or-ozet-ikon {
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

    .or-ozet-kart b {
        display: block;
        font-size: 20px;
        font-weight: 700;
        letter-spacing: -.02em;
        color: var(--y-metin);
        line-height: 1.2;
    }

    .or-ozet-kart span {
        font-size: 12.5px;
        color: var(--y-metin-3);
    }

    .or-arac {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }

    .or-ara {
        position: relative;
        flex: 1;
        min-width: 220px;
        max-width: 420px;
    }

    .or-ara i {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 13px;
        color: var(--y-metin-3);
        pointer-events: none;
    }

    .or-ara input {
        width: 100%;
        padding-left: 36px;
    }

    /* Kart listesi */
    .or-liste {
        display: grid;
        gap: 14px;
    }

    .or-kart {
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .or-kart-bas {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 14px;
        padding: 14px 18px;
        border-bottom: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
    }

    .or-avatar {
        width: 38px;
        height: 38px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border-radius: 50%;
        background: var(--y-primary-soft);
        color: var(--y-primary);
        font-size: 14px;
        font-weight: 700;
    }

    .or-kim {
        flex: 1;
        min-width: 180px;
    }

    .or-kim b {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: var(--y-metin);
    }

    .or-kim a {
        font-size: 12px;
        color: var(--y-metin-3);
    }

    .or-kim a:hover {
        color: var(--y-primary);
    }

    .or-kod {
        padding: 4px 11px;
        border: 1px dashed var(--y-cizgi);
        border-radius: var(--y-r-sm);
        font-family: ui-monospace, Consolas, monospace;
        font-size: 12.5px;
        color: var(--y-metin-2);
    }

    /* Sayısal şerit */
    .or-sayilar {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 1px;
        background: var(--y-cizgi);
    }

    .or-sayi {
        padding: 13px 18px;
        background: var(--y-yuzey);
    }

    .or-sayi b {
        display: block;
        font-size: 16px;
        font-weight: 700;
        color: var(--y-metin);
        line-height: 1.25;
    }

    .or-sayi span {
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    /* Yönetim bölümü */
    .or-yonet {
        padding: 16px 18px;
        border-top: 1px solid var(--y-cizgi);
    }

    .or-form-satir {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 12px;
    }

    .or-alan label {
        display: block;
        margin-bottom: 5px;
        font-size: 11.5px;
        font-weight: 600;
        color: var(--y-metin-3);
    }

    .or-alan select,
    .or-alan input {
        width: auto;
        min-width: 128px;
        padding: 7px 10px;
        font-size: 12.5px;
    }

    .or-not {
        margin-top: 14px;
        padding-top: 14px;
        border-top: 1px solid var(--y-cizgi-soft);
    }

    .or-not textarea {
        min-height: 62px;
        font-size: 12.5px;
    }

    .or-not-alt {
        display: flex;
        justify-content: flex-end;
        margin-top: 9px;
    }

    .or-uyari {
        display: flex;
        align-items: center;
        gap: 9px;
        margin-bottom: 18px;
        padding: 12px 16px;
        border: 1px solid color-mix(in srgb, var(--y-warning) 40%, transparent);
        border-left-width: 4px;
        border-radius: var(--y-r-sm);
        background: color-mix(in srgb, var(--y-warning) 8%, var(--y-yuzey));
        font-size: 13.5px;
        color: var(--y-metin);
    }

    .or-uyari i {
        color: var(--y-warning);
    }

    .or-uyari a {
        margin-left: auto;
    }
</style>

<div class="page-header">
    <div>
        <h1>Satış ortakları</h1>
        <p><?= number_format($toplam, 0, ',', '.') ?> kayıt<?= $arama !== '' || $suzgec !== '' ? ' (süzülmüş)' : '' ?></p>
    </div>
</div>

<?php if ($mesaj): ?>
    <div class="alert alert-<?= $mesaj['tip'] === 'success' ? 'success' : 'error' ?>">
        <i class="fas fa-<?= $mesaj['tip'] === 'success' ? 'circle-check' : 'circle-exclamation' ?> alert-icon"></i>
        <span><?= htmlspecialchars((string) $mesaj['metin']) ?></span>
    </div>
<?php endif; ?>

<?php if (!$tabloVar): ?>
    <div class="alert alert-error">
        <i class="fas fa-circle-exclamation alert-icon"></i>
        <span>Satış ortaklığı tabloları bulunamadı. Kurulum dosyasını içe aktarmanız gerekiyor.</span>
    </div>
<?php else: ?>

    <?php if ($bekleyenCekim > 0): ?>
        <div class="or-uyari">
            <i class="fas fa-money-bill-transfer"></i>
            <span><strong><?= $bekleyenCekim ?></strong> çekim talebi işlem bekliyor.</span>
            <a href="affiliate-withdrawals.php" class="btn btn-sm btn-outline">Çekim taleplerine git</a>
        </div>
    <?php endif; ?>

    <div class="or-ozet">
        <?php foreach ($ozet as [$ad, $deger, $ikon, $filtre, $paraMi]): ?>
            <a class="or-ozet-kart <?= $filtre !== '' && $suzgec === $filtre ? 'secili' : '' ?>"
                href="affiliates.php<?= $filtre !== '' ? '?status=' . $filtre : '' ?>">
                <span class="or-ozet-ikon"><i class="fas <?= $ikon ?>"></i></span>
                <span>
                    <b><?= $paraMi
                        ? number_format($deger, 2, ',', '.') . ' ₺'
                        : number_format($deger, 0, ',', '.') ?></b>
                    <span><?= $ad ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <form class="or-arac" method="get">
        <?php if ($suzgec !== ''): ?>
            <input type="hidden" name="status" value="<?= htmlspecialchars($suzgec) ?>">
        <?php endif; ?>
        <div class="or-ara">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" name="q" value="<?= htmlspecialchars($arama) ?>"
                placeholder="Ad, e-posta veya ortak kodu…">
        </div>
        <div style="display:flex; gap:8px;">
            <button type="submit" class="btn btn-outline"><i class="fas fa-filter"></i> Ara</button>
            <?php if ($arama !== '' || $suzgec !== ''): ?>
                <a href="affiliates.php" class="btn btn-outline"><i class="fas fa-xmark"></i> Sıfırla</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (!$ortaklar): ?>
        <div class="or-kart">
            <div class="empty-state">
                <i class="fas fa-handshake"></i>
                <h3><?= $arama !== '' || $suzgec !== '' ? 'Eşleşen ortak yok' : 'Henüz satış ortağı yok' ?></h3>
                <p><?= $arama !== '' || $suzgec !== ''
                    ? 'Aramayı değiştirin ya da süzgeci sıfırlayın.'
                    : 'Müşteriler panelden başvurduğunda burada listelenir.' ?></p>
            </div>
        </div>
    <?php else: ?>
        <div class="or-liste">
            <?php foreach ($ortaklar as $o):
                $id = (int) $o['id'];
                $ad = trim((string) ($o['first_name'] ?? '') . ' ' . (string) ($o['last_name'] ?? ''));
                $bas = mb_strtoupper(mb_substr($ad !== '' ? $ad : '?', 0, 1), 'UTF-8');
                [$durumAd, $durumSinif] = $durumlar[$o['status']] ?? [(string) $o['status'], 'badge'];
                $donusum = ((int) $o['total_visits']) > 0
                    ? ((int) $o['total_orders'] / (int) $o['total_visits']) * 100
                    : null;
                ?>
                <div class="or-kart">
                    <div class="or-kart-bas">
                        <span class="or-avatar"><?= htmlspecialchars($bas) ?></span>
                        <span class="or-kim">
                            <b><?= htmlspecialchars($ad !== '' ? $ad : 'Müşteri silinmiş') ?></b>
                            <?php if (!empty($o['email'])): ?>
                                <a href="client-view.php?id=<?= (int) $o['client_id'] ?>" dir="ltr">
                                    <?= htmlspecialchars((string) $o['email']) ?>
                                </a>
                            <?php endif; ?>
                        </span>
                        <span class="or-kod"><?= htmlspecialchars((string) $o['affiliate_code']) ?></span>
                        <span class="badge <?= $durumSinif ?>"><?= $durumAd ?></span>
                    </div>

                    <div class="or-sayilar">
                        <div class="or-sayi">
                            <b><?= number_format((int) $o['total_visits'], 0, ',', '.') ?></b>
                            <span>Ziyaret</span>
                        </div>
                        <div class="or-sayi">
                            <b><?= number_format((int) $o['total_signups'], 0, ',', '.') ?></b>
                            <span>Kayıt</span>
                        </div>
                        <div class="or-sayi">
                            <b><?= number_format((int) $o['total_orders'], 0, ',', '.') ?></b>
                            <span>Sipariş<?= $donusum !== null ? ' · %' . number_format($donusum, 1, ',', '.') : '' ?></span>
                        </div>
                        <div class="or-sayi">
                            <b><?= number_format((float) $o['total_earnings'], 2, ',', '.') ?> ₺</b>
                            <span>Toplam kazanç</span>
                        </div>
                        <div class="or-sayi">
                            <b><?= number_format((float) $o['total_withdrawn'], 2, ',', '.') ?> ₺</b>
                            <span>Çekilen</span>
                        </div>
                        <div class="or-sayi">
                            <b style="color:var(--y-primary)"><?= number_format((float) $o['balance'], 2, ',', '.') ?> ₺</b>
                            <span>Bakiye</span>
                        </div>
                    </div>

                    <div class="or-yonet">
                        <div class="or-form-satir">
                            <form method="post" class="or-form-satir" style="gap:9px;">
                                <?= Guvenlik::alan() ?>
                                <input type="hidden" name="durum_guncelle" value="1">
                                <input type="hidden" name="affiliate_id" value="<?= $id ?>">
                                <div class="or-alan">
                                    <label>Durum</label>
                                    <select name="status">
                                        <?php foreach ($durumlar as $kod => [$etiket, ]): ?>
                                            <option value="<?= $kod ?>" <?= $o['status'] === $kod ? 'selected' : '' ?>>
                                                <?= $etiket ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-sm btn-outline">Kaydet</button>
                            </form>

                            <form method="post" class="or-form-satir" style="gap:9px;">
                                <?= Guvenlik::alan() ?>
                                <input type="hidden" name="komisyon_guncelle" value="1">
                                <input type="hidden" name="affiliate_id" value="<?= $id ?>">
                                <div class="or-alan">
                                    <label>Komisyon türü</label>
                                    <select name="commission_type">
                                        <?php foreach ($komisyonTuru as $kod => $etiket): ?>
                                            <option value="<?= $kod ?>" <?= $o['commission_type'] === $kod ? 'selected' : '' ?>>
                                                <?= $etiket ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="or-alan">
                                    <label>Değer</label>
                                    <input type="number" name="commission_rate" step="0.01" min="0" max="100000"
                                        value="<?= number_format((float) $o['commission_rate'], 2, '.', '') ?>"
                                        style="min-width:100px">
                                </div>
                                <button type="submit" class="btn btn-sm btn-outline">Kaydet</button>
                            </form>
                        </div>

                        <form method="post" class="or-not">
                            <?= Guvenlik::alan() ?>
                            <input type="hidden" name="not_guncelle" value="1">
                            <input type="hidden" name="affiliate_id" value="<?= $id ?>">
                            <label for="not-<?= $id ?>">Yönetici notu</label>
                            <textarea id="not-<?= $id ?>" name="notes" maxlength="2000"
                                placeholder="Bu ortakla ilgili iç not…"><?= htmlspecialchars((string) ($o['notes'] ?? '')) ?></textarea>
                            <div class="or-not-alt">
                                <button type="submit" class="btn btn-sm btn-outline">Notu kaydet</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

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
            return 'affiliates.php?' . http_build_query($p);
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

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
