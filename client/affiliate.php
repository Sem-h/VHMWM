<?php
/**
 * VHM - Satış ortaklığı (müşteri paneli)
 *
 * Önceki sürümdeki sorunlar:
 *   - 1138 satırın 493'ü satır içi <style> idi ve panel.css'te zaten
 *     tanımlı olan kart, tablo, buton, rozet stillerini yeniden yazıyordu.
 *   - ensureAffiliateTables() her sayfa açılışında CREATE TABLE
 *     çalıştırıyordu; şema işi istek başına yapılacak iş değil.
 *   - Para çekme ve ödeme bilgisi formlarında CSRF belirteci yoktu.
 *     Müşteri panelinin tamamında yok; burada başlatıldı.
 *   - PRG yoktu; sayfa yenilenince çekim talebi tekrar gönderiliyordu.
 *
 * Görünüm client/assets/css/panel.css bileşenlerinin üstüne kuruldu;
 * yalnızca bu sayfaya özgü olanlar aşağıda "or-" öneki ile tanımlı.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Affiliate.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';

Guvenlik::oturumBaslat();

if (!isset($_SESSION['client_id'])) {
    header('Location: index.php');
    exit;
}

$clientId = (int) $_SESSION['client_id'];
$acik = Settings::get('affiliate_enabled', '1') === '1';

const OR_ODEME_YONTEMLERI = [
    'bank_transfer' => 'Banka havalesi / EFT',
    'papara' => 'Papara',
    'crypto' => 'Kripto cüzdan',
    'other' => 'Diğer',
];

const OR_DURUMLAR = [
    'pending' => ['Onay bekliyor', 'warning'],
    'active' => ['Etkin', 'success'],
    'suspended' => ['Askıya alındı', 'danger'],
    'rejected' => ['Reddedildi', 'danger'],
];

const OR_KOMISYON_DURUM = [
    'pending' => ['Bekliyor', 'warning'],
    'approved' => ['Onaylandı', 'success'],
    'paid' => ['Ödendi', 'success'],
    'cancelled' => ['İptal', 'danger'],
    'rejected' => ['Reddedildi', 'danger'],
];

const OR_CEKIM_DURUM = [
    'pending' => ['Bekliyor', 'warning'],
    'processing' => ['İşleniyor', 'info'],
    'completed' => ['Tamamlandı', 'success'],
    'rejected' => ['Reddedildi', 'danger'],
];

function orDon(string $tip, string $metin): never
{
    $_SESSION['or_mesaj'] = ['tip' => $tip, 'metin' => $metin];
    header('Location: affiliate.php');
    exit;
}

/** Ödeme bilgisi alanlarını doğrular */
function orOdemeOku(): array
{
    $yontem = (string) ($_POST['payment_method'] ?? 'bank_transfer');
    $bilgi = trim((string) ($_POST['payment_details'] ?? ''));

    if (!isset(OR_ODEME_YONTEMLERI[$yontem])) {
        orDon('error', 'Geçersiz ödeme yöntemi.');
    }
    if ($bilgi === '') {
        orDon('error', 'Ödeme bilgilerinizi girin; ödemeyi buraya yapacağız.');
    }
    if (mb_strlen($bilgi) > 500) {
        orDon('error', 'Ödeme bilgisi çok uzun (en fazla 500 karakter).');
    }

    return [$yontem, $bilgi];
}

$ortak = Affiliate::getByClientId($clientId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* Müşteri panelinde CSRF yoktu; para çekme burada yapılıyor. */
    Guvenlik::zorunlu();

    if (!$acik) {
        orDon('error', 'Satış ortaklığı programı şu anda kapalı.');
    }

    /* ---------- Başvuru ---------- */
    if (isset($_POST['basvur'])) {
        if ($ortak) {
            orDon('uyari', 'Zaten bir satış ortaklığı hesabınız var.');
        }

        [$yontem, $bilgi] = orOdemeOku();

        $yeniId = Affiliate::register($clientId, [
            'payment_method' => $yontem,
            'payment_details' => $bilgi,
        ]);

        if (!$yeniId) {
            orDon('error', 'Başvuru oluşturulamadı, lütfen tekrar deneyin.');
        }

        orDon('success', Settings::get('affiliate_auto_approve', '0') === '1'
            ? 'Satış ortaklığı hesabınız oluşturuldu, hemen kullanmaya başlayabilirsiniz.'
            : 'Başvurunuz alındı. Onaylandığında hesabınız etkinleşecek.');
    }

    if (!$ortak) {
        orDon('error', 'Önce satış ortaklığı başvurusu yapmalısınız.');
    }

    /* ---------- Ödeme bilgisi ---------- */
    if (isset($_POST['odeme_kaydet'])) {
        [$yontem, $bilgi] = orOdemeOku();

        try {
            Database::query(
                "UPDATE affiliates SET payment_method = ?, payment_details = ? WHERE id = ?",
                [$yontem, $bilgi, (int) $ortak['id']]
            );
        } catch (Throwable $e) {
            error_log('Ödeme bilgisi güncellenemedi: ' . $e->getMessage());
            orDon('error', 'Ödeme bilgisi kaydedilemedi.');
        }

        orDon('success', 'Ödeme bilgileriniz güncellendi.');
    }

    /* ---------- Çekim talebi ---------- */
    if (isset($_POST['cekim'])) {
        if ($ortak['status'] !== 'active') {
            orDon('error', 'Çekim talebi için hesabınızın etkin olması gerekiyor.');
        }

        $ham = str_replace(',', '.', trim((string) ($_POST['tutar'] ?? '')));

        if ($ham === '' || !is_numeric($ham)) {
            orDon('error', 'Çekim tutarını sayı olarak girin.');
        }

        $sonuc = Affiliate::requestWithdrawal((int) $ortak['id'], (float) $ham);
        orDon($sonuc['success'] ? 'success' : 'error', $sonuc['message']);
    }

    orDon('error', 'Tanımsız işlem.');
}

$mesaj = null;
if (!empty($_SESSION['or_mesaj'])) {
    $mesaj = $_SESSION['or_mesaj'];
    unset($_SESSION['or_mesaj']);
}

$sayilar = $ortak ? Affiliate::getStats((int) $ortak['id']) : [];
$komisyonlar = [];
$cekimler = [];
$referanslar = [];
$bekleyenCekim = null;

if ($ortak) {
    $ortakId = (int) $ortak['id'];

    $komisyonlar = Database::fetchAll(
        "SELECT * FROM affiliate_commissions WHERE affiliate_id = ?
          ORDER BY created_at DESC LIMIT 15",
        [$ortakId]
    );

    $cekimler = Database::fetchAll(
        "SELECT * FROM affiliate_withdrawals WHERE affiliate_id = ?
          ORDER BY created_at DESC LIMIT 15",
        [$ortakId]
    );

    $referanslar = Database::fetchAll(
        "SELECT ar.*, c.first_name, c.last_name, c.email
           FROM affiliate_referrals ar
           JOIN clients c ON c.id = ar.referred_client_id
          WHERE ar.affiliate_id = ?
          ORDER BY ar.created_at DESC LIMIT 20",
        [$ortakId]
    );

    foreach ($cekimler as $c) {
        if (in_array($c['status'], ['pending', 'processing'], true)) {
            $bekleyenCekim = $c;
            break;
        }
    }
}

/**
 * Referansın e-postasını kısmen gizler.
 * Satış ortağının, getirdiği kişiyi tanıması için yeterli;
 * tam adresi görmesi gerekmiyor.
 */
function orEpostaGizle(string $eposta): string
{
    $at = strpos($eposta, "@");
    if ($at === false || $at < 1) {
        return $eposta;
    }

    $ad = substr($eposta, 0, $at);
    $alan = substr($eposta, $at);

    if (mb_strlen($ad) <= 2) {
        return mb_substr($ad, 0, 1) . str_repeat("*", 3) . $alan;
    }

    return mb_substr($ad, 0, 2) . str_repeat("*", min(6, mb_strlen($ad) - 2)) . $alan;
}

function orPara(mixed $t): string
{
    return number_format((float) $t, 2, ',', '.') . ' ₺';
}

$pageTitle = 'Satış Ortaklığı';
$pageIcon = 'fas fa-handshake';
$currentPage = 'affiliate';
include 'includes/header.php';
?>

<style>
    /* ==========================================
       Satış ortaklığı - or
       Genel bileşenler panel.css'ten gelir; burada
       yalnızca bu sayfaya özgü olanlar var.
       ========================================== */
    .or-link {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        padding: 14px 16px;
        border: 1px dashed var(--primary);
        border-radius: 10px;
        background: color-mix(in srgb, var(--primary) 7%, transparent);
    }

    .or-link code {
        flex: 1;
        min-width: 200px;
        overflow-x: auto;
        white-space: nowrap;
        font-family: ui-monospace, Consolas, monospace;
        font-size: 14px;
        color: var(--primary);
    }

    .or-kod {
        display: inline-flex;
        align-items: baseline;
        gap: 8px;
        margin-top: 10px;
        font-size: 13px;
        opacity: .75;
    }

    .or-kod b {
        font-family: ui-monospace, Consolas, monospace;
        font-size: 15px;
        letter-spacing: .06em;
    }

    .or-izgara {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 320px;
        gap: 20px;
        align-items: start;
    }

    .or-alan {
        margin-bottom: 14px;
    }

    .or-alan label {
        display: block;
        margin-bottom: 5px;
        font-size: 12.5px;
        font-weight: 600;
        opacity: .8;
    }

    .or-alan small {
        display: block;
        margin-top: 5px;
        font-size: 11.5px;
        opacity: .65;
    }

    .or-ozet {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }

    .or-ozet-kart {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 18px;
        border: 1px solid color-mix(in srgb, currentColor 12%, transparent);
        border-radius: 14px;
        background: color-mix(in srgb, currentColor 3%, transparent);
    }

    .or-ozet-ikon {
        width: 44px;
        height: 44px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border-radius: 12px;
        color: #fff;
        font-size: 16px;
    }

    .or-ozet-ikon.cyan {
        background: linear-gradient(135deg, #06b6d4, #0e7490);
    }

    .or-ozet-ikon.blue {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    }

    .or-ozet-ikon.yellow {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    .or-ozet-ikon.green {
        background: linear-gradient(135deg, #10b981, #059669);
    }

    .or-ozet-kart b {
        display: block;
        font-size: 21px;
        font-weight: 700;
        letter-spacing: -.02em;
        line-height: 1.25;
    }

    .or-ozet-kart small {
        font-size: 12.5px;
        opacity: .68;
    }

    .or-bos {
        padding: 44px 20px;
        text-align: center;
    }

    .or-bos i {
        font-size: 30px;
        opacity: .25;
    }

    .or-bos p {
        max-width: 380px;
        margin: 12px auto 0;
        font-size: 13.5px;
        line-height: 1.6;
        opacity: .7;
    }

    .or-bakiye {
        padding: 16px;
        margin-bottom: 14px;
        border-radius: 11px;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: #fff;
    }

    .or-bakiye span {
        display: block;
        font-size: 12px;
        opacity: .85;
    }

    .or-bakiye b {
        display: block;
        margin-top: 2px;
        font-size: 26px;
        font-weight: 700;
        letter-spacing: -.02em;
    }

    .or-bakiye-alt {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        margin-top: 12px;
        padding-top: 11px;
        border-top: 1px solid rgba(255, 255, 255, .22);
        font-size: 12px;
    }

    .or-adim {
        display: flex;
        gap: 13px;
        padding: 13px 0;
        border-bottom: 1px solid color-mix(in srgb, currentColor 10%, transparent);
    }

    .or-adim:last-child {
        border-bottom: none;
    }

    .or-adim-no {
        width: 27px;
        height: 27px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border-radius: 50%;
        background: var(--primary);
        color: #fff;
        font-size: 12.5px;
        font-weight: 700;
    }

    .or-adim b {
        display: block;
        font-size: 14px;
    }

    .or-adim p {
        margin: 3px 0 0;
        font-size: 13px;
        line-height: 1.55;
        opacity: .72;
    }

    .or-oran {
        display: flex;
        align-items: baseline;
        gap: 7px;
        padding: 13px 16px;
        margin-bottom: 16px;
        border-radius: 10px;
        background: color-mix(in srgb, var(--primary) 9%, transparent);
    }

    .or-oran b {
        font-size: 24px;
        font-weight: 700;
        color: var(--primary);
    }

    .or-kapali {
        max-width: 470px;
        margin: 60px auto;
        text-align: center;
    }

    .or-kapali i {
        font-size: 40px;
        opacity: .3;
    }

    .or-kapali h2 {
        margin: 16px 0 8px;
        font-size: 19px;
    }

    .or-kapali p {
        font-size: 14px;
        line-height: 1.6;
        opacity: .7;
    }

    @media (max-width: 980px) {
        .or-izgara {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

<div class="page-header">
    <h1><i class="fas fa-handshake"></i> Satış Ortaklığı</h1>
    <p>Referanslarınızdan kazandığınız komisyonlar ve çekim talepleriniz</p>
</div>
<?php if ($mesaj): ?>
    <div class="alert alert-<?= $mesaj['tip'] === 'error' ? 'danger' : ($mesaj['tip'] === 'uyari' ? 'warning' : 'success') ?>">
        <i class="fas fa-<?= $mesaj['tip'] === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
        <?= htmlspecialchars($mesaj['metin']) ?>
    </div>
<?php endif; ?>

<?php if (!$acik): ?>

    <div class="or-kapali">
        <i class="fas fa-handshake"></i>
        <h2>Satış ortaklığı programı kapalı</h2>
        <p>Program şu anda yeni başvuru almıyor. Daha sonra tekrar bakabilirsiniz.</p>
    </div>

<?php elseif (!$ortak): ?>

    <!-- ============ Başvuru ============ -->
    <div class="or-izgara">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-handshake"></i> Satış ortaklığı programı</h3>
            </div>
            <div class="card-body">
                <div class="or-oran">
                    <b>%<?= rtrim(rtrim(number_format(
                        (float) Settings::get('affiliate_default_commission', '10'), 2, ',', '.'), '0'), ',') ?></b>
                    <span>her ödenen faturadan komisyon</span>
                </div>

                <div class="or-adim">
                    <span class="or-adim-no">1</span>
                    <div>
                        <b>Başvurun</b>
                        <p>Ödemeyi nereye almak istediğinizi yazın. Başvurunuz onaylandığında
                            size özel bir referans bağlantısı oluşturulur.</p>
                    </div>
                </div>
                <div class="or-adim">
                    <span class="or-adim-no">2</span>
                    <div>
                        <b>Bağlantını paylaş</b>
                        <p>Bağlantınızdan gelen ziyaretçi kayıt olduğunda size bağlanır.
                            Ziyaret, kayıt ve sipariş sayıları bu sayfada görünür.</p>
                    </div>
                </div>
                <div class="or-adim">
                    <span class="or-adim-no">3</span>
                    <div>
                        <b>Kazan</b>
                        <p>Referansınızın ödediği her faturadan komisyon bakiyenize eklenir.
                            Bakiyeniz alt sınırı geçtiğinde çekim talebi oluşturabilirsiniz.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-plus"></i> Başvuru formu</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= Guvenlik::alan() ?>
                    <input type="hidden" name="basvur" value="1">

                    <div class="or-alan">
                        <label for="payment_method">Ödeme yöntemi</label>
                        <select name="payment_method" id="payment_method" class="form-control">
                            <?php foreach (OR_ODEME_YONTEMLERI as $deger => $ad): ?>
                                <option value="<?= $deger ?>"><?= $ad ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="or-alan">
                        <label for="payment_details">Ödeme bilgileri</label>
                        <textarea name="payment_details" id="payment_details" class="form-control"
                            required maxlength="500"
                            placeholder="Ad soyad ve IBAN"></textarea>
                        <small>Komisyon ödemeleriniz buraya yapılır. Sonradan değiştirebilirsiniz.</small>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
                        <i class="fas fa-paper-plane"></i> Başvuruyu gönder
                    </button>
                </form>
            </div>
        </div>
    </div>

<?php else: ?>

    <?php
    [$durumAd, $durumSinif] = OR_DURUMLAR[$ortak['status']] ?? [(string) $ortak['status'], 'info'];
    $referansLinki = Affiliate::getReferralLink((string) $ortak['affiliate_code']);
    $etkin = $ortak['status'] === 'active';
    ?>

    <!-- ============ Durum + referans bağlantısı ============ -->
    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h3><i class="fas fa-link"></i> Referans bağlantınız</h3>
            <span class="badge badge-<?= $durumSinif ?>"><?= $durumAd ?></span>
        </div>
        <div class="card-body">
            <?php if (!$etkin): ?>
                <div class="alert alert-warning" style="margin-bottom:14px">
                    <i class="fas fa-circle-exclamation"></i>
                    <?= $ortak['status'] === 'pending'
                        ? 'Başvurunuz inceleniyor. Onaylanana kadar bağlantınızdan gelen kayıtlar işlenmez.'
                        : 'Hesabınız şu anda etkin değil. Ayrıntı için destek talebi açabilirsiniz.' ?>
                </div>
            <?php endif; ?>

            <div class="or-link">
                <code id="or-baglanti"><?= htmlspecialchars($referansLinki) ?></code>
                <button type="button" class="btn btn-primary btn-sm" id="or-kopyala">
                    <i class="fas fa-copy"></i> Kopyala
                </button>
            </div>

            <span class="or-kod">
                Referans kodunuz: <b><?= htmlspecialchars((string) $ortak['affiliate_code']) ?></b>
            </span>
        </div>
    </div>

    <!-- ============ Özet ============ -->
    <div class="or-ozet">
        <div class="or-ozet-kart">
                <span class="or-ozet-ikon cyan"><i class="fas fa-eye"></i></span>
                <span>
                    <b><?= number_format((int) ($sayilar['total_visits'] ?? 0), 0, ',', '.') ?></b>
                    <small>Ziyaret</small>
                </span>
            </div>
        <div class="or-ozet-kart">
                <span class="or-ozet-ikon blue"><i class="fas fa-user-plus"></i></span>
                <span>
                    <b><?= number_format((int) ($sayilar['total_signups'] ?? 0), 0, ',', '.') ?></b>
                    <small>Kayıt</small>
                </span>
            </div>
        <div class="or-ozet-kart">
                <span class="or-ozet-ikon yellow"><i class="fas fa-cart-shopping"></i></span>
                <span>
                    <b><?= number_format((int) ($sayilar['total_orders'] ?? 0), 0, ',', '.') ?></b>
                    <small>Sipariş</small>
                </span>
            </div>
        <div class="or-ozet-kart">
                <span class="or-ozet-ikon green"><i class="fas fa-coins"></i></span>
                <span>
                    <b><?= orPara($sayilar['total_earnings'] ?? 0) ?></b>
                    <small>Toplam kazanç</small>
                </span>
            </div>
    </div>

    <div class="or-izgara">
        <div>
            <!-- ============ Referanslar ============ -->
            <div class="card" style="margin-bottom:20px">
                <div class="card-header">
                    <h3><i class="fas fa-users"></i> Referanslarım</h3>
                    <span><?= count($referanslar) ?></span>
                </div>
                <?php if (!$referanslar): ?>
                    <div class="or-bos">
                        <i class="fas fa-users"></i>
                        <p>Henüz referansınız yok. Bağlantınızı paylaşmaya başlayın.</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Müşteri</th>
                                <th>Kayıt tarihi</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($referanslar as $r): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars(trim(
                                            (string) $r['first_name'] . ' ' . (string) $r['last_name'])) ?></strong><br>
                                        <small><?= htmlspecialchars(orEpostaGizle((string) $r['email'])) ?></small>
                                    </td>
                                    <td><?= date('d.m.Y', strtotime((string) $r['created_at'])) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $r['status'] === 'approved' ? 'success' : 'warning' ?>">
                                            <?= $r['status'] === 'approved' ? 'Onaylı' : 'Bekliyor' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- ============ Komisyonlar ============ -->
            <div class="card" style="margin-bottom:20px">
                <div class="card-header">
                    <h3><i class="fas fa-money-bill-wave"></i> Komisyon geçmişi</h3>
                    <?php if (($sayilar['pending_commissions'] ?? 0) > 0): ?>
                        <span><?= orPara($sayilar['pending_commissions']) ?> onay bekliyor</span>
                    <?php endif; ?>
                </div>
                <?php if (!$komisyonlar): ?>
                    <div class="or-bos">
                        <i class="fas fa-money-bill-wave"></i>
                        <p>Henüz komisyon kaydınız yok. Referansınız ilk ödemesini
                            yaptığında burada görünür.</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Açıklama</th>
                                <th>Komisyon</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($komisyonlar as $k):
                                [$kAd, $kSinif] = OR_KOMISYON_DURUM[$k['status']] ?? [(string) $k['status'], 'info'];
                                ?>
                                <tr>
                                    <td><?= date('d.m.Y', strtotime((string) $k['created_at'])) ?></td>
                                    <td>
                                        <?= htmlspecialchars((string) ($k['description'] ?: 'Komisyon')) ?><br>
                                        <small><?= orPara($k['amount']) ?> tutarlı işlem
                                            &middot; %<?= rtrim(rtrim(number_format(
                                                (float) $k['commission_rate'], 2, ',', '.'), '0'), ',') ?></small>
                                    </td>
                                    <td><strong><?= orPara($k['commission_amount']) ?></strong></td>
                                    <td><span class="badge badge-<?= $kSinif ?>"><?= $kAd ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- ============ Çekimler ============ -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-wallet"></i> Çekim talepleri</h3>
                    <span><?= orPara($sayilar['total_withdrawn'] ?? 0) ?> ödendi</span>
                </div>
                <?php if (!$cekimler): ?>
                    <div class="or-bos">
                        <i class="fas fa-wallet"></i>
                        <p>Henüz çekim talebiniz yok.</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Tutar</th>
                                <th>Yöntem</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cekimler as $c):
                                [$cAd, $cSinif] = OR_CEKIM_DURUM[$c['status']] ?? [(string) $c['status'], 'info'];
                                ?>
                                <tr>
                                    <td><?= date('d.m.Y H:i', strtotime((string) $c['created_at'])) ?></td>
                                    <td><strong><?= orPara($c['amount']) ?></strong></td>
                                    <td><?= OR_ODEME_YONTEMLERI[$c['payment_method']]
                                        ?? htmlspecialchars((string) $c['payment_method']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $cSinif ?>"><?= $cAd ?></span>
                                        <?php if (!empty($c['admin_notes'])): ?>
                                            <br><small><?= htmlspecialchars((string) $c['admin_notes']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============ Sağ sütun ============ -->
        <div>
            <div class="card" style="margin-bottom:20px">
                <div class="card-body">
                    <div class="or-bakiye">
                        <span>Çekilebilir bakiye</span>
                        <b><?= orPara($sayilar['balance'] ?? 0) ?></b>
                        <div class="or-bakiye-alt">
                            <span>En az çekim</span>
                            <span><?= orPara($ortak['min_withdrawal']) ?></span>
                        </div>
                    </div>

                    <?php if ($bekleyenCekim): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-hourglass-half"></i>
                            <?= orPara($bekleyenCekim['amount']) ?> tutarlı talebiniz işleniyor.
                            Sonuçlanmadan yeni talep oluşturamazsınız.
                        </div>
                    <?php elseif (!$etkin): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-circle-exclamation"></i>
                            Hesabınız etkinleşince çekim talebi oluşturabilirsiniz.
                        </div>
                    <?php elseif ((float) ($sayilar['balance'] ?? 0) < (float) $ortak['min_withdrawal']): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-circle-info"></i>
                            Çekim için en az <?= orPara($ortak['min_withdrawal']) ?> bakiye gerekiyor.
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <?= Guvenlik::alan() ?>
                            <input type="hidden" name="cekim" value="1">

                            <div class="or-alan">
                                <label for="tutar">Çekmek istediğiniz tutar</label>
                                <input type="text" name="tutar" id="tutar" class="form-control"
                                    inputmode="decimal" required
                                    placeholder="<?= number_format((float) $ortak['min_withdrawal'], 2, ',', '') ?>">
                                <small>Ödeme, kayıtlı bilgilerinize yapılır.</small>
                            </div>

                            <button type="submit" class="btn btn-primary"
                                style="width:100%;justify-content:center">
                                <i class="fas fa-paper-plane"></i> Çekim talebi oluştur
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-credit-card"></i> Ödeme bilgileri</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= Guvenlik::alan() ?>
                        <input type="hidden" name="odeme_kaydet" value="1">

                        <div class="or-alan">
                            <label for="yontem">Yöntem</label>
                            <select name="payment_method" id="yontem" class="form-control">
                                <?php foreach (OR_ODEME_YONTEMLERI as $deger => $ad): ?>
                                    <option value="<?= $deger ?>"
                                        <?= $ortak['payment_method'] === $deger ? 'selected' : '' ?>>
                                        <?= $ad ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="or-alan">
                            <label for="bilgi">Ödeme bilgileri</label>
                            <textarea name="payment_details" id="bilgi" class="form-control"
                                required maxlength="500"><?= htmlspecialchars(
                                    (string) ($ortak['payment_details'] ?? '')) ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-outline"
                            style="width:100%;justify-content:center">
                            Bilgileri güncelle
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('or-kopyala').addEventListener('click', function () {
            var metin = document.getElementById('or-baglanti').textContent.trim();
            var dugme = this;

            var bitti = function () {
                var eski = dugme.innerHTML;
                dugme.innerHTML = '<i class="fas fa-check"></i> Kopyalandı';
                setTimeout(function () { dugme.innerHTML = eski; }, 1600);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(metin).then(bitti, function () { yedek(metin, bitti); });
            } else {
                yedek(metin, bitti);
            }

            /* http:// adresinde clipboard API kapalı olabiliyor */
            function yedek(m, tamam) {
                var a = document.createElement('textarea');
                a.value = m;
                a.style.position = 'fixed';
                a.style.opacity = '0';
                document.body.appendChild(a);
                a.select();
                try { document.execCommand('copy'); tamam(); } catch (e) { }
                document.body.removeChild(a);
            }
        });
    </script>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
