<?php
/**
 * VHM - Banka hesapları
 *
 * Müşterilerin havale göndereceği hesaplar burada tanımlanır.
 *
 * Önceki sürümdeki sorunlar:
 *   - Ekleme, düzenleme ve silmede CSRF denetimi yoktu.
 *   - Hiçbir alan doğrulanmıyordu; banka adı ve hesap sahibi boş
 *     bırakılabiliyordu.
 *   - IBAN hiç denetlenmiyordu. Tek hane yanlış girildiğinde müşteri
 *     parayı başka bir hesaba gönderir ve bu geri alınamaz. Artık
 *     uzunluk, ülke kodu ve mod-97 sağlaması yapılıyor.
 *   - Veritabanı hatası $e->getMessage() ile ekrana basılıyordu.
 *   - PRG yoktu.
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

$pageTitle = 'Banka hesapları';
$currentPage = 'bank-accounts';

$paraBirimleri = ['TRY' => 'Türk lirası', 'USD' => 'Amerikan doları', 'EUR' => 'Euro', 'GBP' => 'Sterlin'];

/** IBAN'ı boşluksuz ve büyük harfli hâle getirir */
function ibanSadelestir(string $iban): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $iban) ?? '');
}

/** Okunabilirlik için dörtlü gruplar hâlinde yazar */
function ibanBicimle(string $iban): string
{
    return trim(chunk_split(ibanSadelestir($iban), 4, ' '));
}

/**
 * IBAN mod-97 sağlaması (ISO 13616).
 * İlk dört karakter sona alınır, harfler sayıya çevrilir, kalan 1 olmalı.
 */
function ibanGecerliMi(string $iban): bool
{
    $iban = ibanSadelestir($iban);

    if (strlen($iban) < 15 || strlen($iban) > 34) {
        return false;
    }
    if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]+$/', $iban)) {
        return false;
    }
    /* Türkiye IBAN'ı tam 26 karakterdir */
    if (str_starts_with($iban, 'TR') && strlen($iban) !== 26) {
        return false;
    }

    $tasinmis = substr($iban, 4) . substr($iban, 0, 4);
    $sayisal = '';
    foreach (str_split($tasinmis) as $karakter) {
        $sayisal .= ctype_alpha($karakter)
            ? (string) (ord($karakter) - 55)
            : $karakter;
    }

    /* Sayı çok uzun olduğu için parça parça mod alınır */
    $kalan = 0;
    foreach (str_split($sayisal, 7) as $parca) {
        $kalan = (int) (((string) $kalan . $parca) % 97);
    }

    return $kalan === 1;
}

function hesapMesaj(string $tip, string $metin): void
{
    $_SESSION['bnk_mesaj'] = ['tip' => $tip, 'metin' => $metin];
    header('Location: bank-accounts.php');
    exit;
}

$hatalar = [];
$duzenleId = 0;
$eski = [
    'bank_name' => '', 'account_holder' => '', 'account_number' => '', 'iban' => '',
    'branch_name' => '', 'branch_code' => '', 'swift_code' => '', 'currency' => 'TRY',
    'display_order' => 0, 'is_active' => 1, 'notes' => '',
];

/* ---------- Silme ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    Guvenlik::zorunlu();
    $id = (int) $_POST['delete'];

    try {
        Database::query("DELETE FROM bank_accounts WHERE id = ?", [$id]);
        hesapMesaj('success', 'Banka hesabı silindi.');
    } catch (Throwable $e) {
        error_log('Banka hesabı silinemedi: ' . $e->getMessage());
        hesapMesaj('error', 'Banka hesabı silinemedi.');
    }
}

/* ---------- Durum ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['durum_id'])) {
    Guvenlik::zorunlu();
    $id = (int) $_POST['durum_id'];
    $yeni = ((int) ($_POST['durum'] ?? 0)) === 1 ? 1 : 0;

    try {
        Database::update('bank_accounts', ['is_active' => $yeni], 'id = ?', [$id]);
        hesapMesaj('success', $yeni === 1 ? 'Hesap yayına alındı.' : 'Hesap yayından kaldırıldı.');
    } catch (Throwable $e) {
        error_log('Banka hesabı durumu değiştirilemedi: ' . $e->getMessage());
        hesapMesaj('error', 'Durum değiştirilemedi.');
    }
}

/* ---------- Ekleme / düzenleme ---------- */
$islem = (string) ($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($islem === 'add' || $islem === 'edit')) {
    Guvenlik::zorunlu();

    $kirp = static fn(string $a, int $n): string => mb_substr(trim((string) ($_POST[$a] ?? '')), 0, $n);
    $duzenleId = $islem === 'edit' ? (int) ($_POST['id'] ?? 0) : 0;

    $eski = [
        'bank_name' => $kirp('bank_name', 120),
        'account_holder' => $kirp('account_holder', 160),
        'account_number' => $kirp('account_number', 60),
        'iban' => ibanSadelestir($kirp('iban', 40)),
        'branch_name' => $kirp('branch_name', 120),
        'branch_code' => $kirp('branch_code', 30),
        'swift_code' => strtoupper($kirp('swift_code', 20)),
        'currency' => isset($paraBirimleri[$_POST['currency'] ?? '']) ? (string) $_POST['currency'] : 'TRY',
        'display_order' => (int) ($_POST['display_order'] ?? 0),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'notes' => $kirp('notes', 1000),
    ];

    if (mb_strlen($eski['bank_name']) < 2) {
        $hatalar['bank_name'] = 'Banka adı zorunlu.';
    }
    if (mb_strlen($eski['account_holder']) < 3) {
        $hatalar['account_holder'] = 'Hesap sahibi zorunlu; faturadaki unvanla aynı olmalı.';
    }
    if ($eski['iban'] === '' && $eski['account_number'] === '') {
        $hatalar['iban'] = 'IBAN ya da hesap numarasından en az biri gerekli.';
    } elseif ($eski['iban'] !== '' && !ibanGecerliMi($eski['iban'])) {
        /* Yanlış IBAN doğrudan para kaybı demek */
        $hatalar['iban'] = 'IBAN geçersiz. Sağlama tutmuyor, lütfen karakterleri kontrol edin.';
    }
    if ($eski['swift_code'] !== '' && !preg_match('/^[A-Z0-9]{8}([A-Z0-9]{3})?$/', $eski['swift_code'])) {
        $hatalar['swift_code'] = 'SWIFT kodu 8 ya da 11 karakter olmalı.';
    }

    if (!$hatalar) {
        $veri = [
            'bank_name' => $eski['bank_name'],
            'account_holder' => $eski['account_holder'],
            'account_number' => $eski['account_number'] !== '' ? $eski['account_number'] : null,
            'iban' => $eski['iban'] !== '' ? $eski['iban'] : null,
            'branch_name' => $eski['branch_name'] !== '' ? $eski['branch_name'] : null,
            'branch_code' => $eski['branch_code'] !== '' ? $eski['branch_code'] : null,
            'swift_code' => $eski['swift_code'] !== '' ? $eski['swift_code'] : null,
            'currency' => $eski['currency'],
            'display_order' => $eski['display_order'],
            'is_active' => $eski['is_active'],
            'notes' => $eski['notes'] !== '' ? $eski['notes'] : null,
        ];

        try {
            if ($islem === 'edit' && $duzenleId > 0) {
                Database::update('bank_accounts', $veri, 'id = ?', [$duzenleId]);
                hesapMesaj('success', $eski['bank_name'] . ' hesabı güncellendi.');
            } else {
                Database::insert('bank_accounts', $veri);
                hesapMesaj('success', $eski['bank_name'] . ' hesabı eklendi.');
            }
        } catch (Throwable $e) {
            error_log('Banka hesabı kaydedilemedi: ' . $e->getMessage());
            $hatalar['genel'] = 'Hesap kaydedilemedi. Lütfen tekrar deneyin.';
        }
    }
}

/* Düzenleme için yükleme */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $duzenleId = (int) $_GET['edit'];
    try {
        $kayit = Database::fetch("SELECT * FROM bank_accounts WHERE id = ?", [$duzenleId]);
        if ($kayit) {
            foreach ($eski as $anahtar => $_) {
                $eski[$anahtar] = $kayit[$anahtar] ?? $eski[$anahtar];
            }
        } else {
            $duzenleId = 0;
        }
    } catch (Throwable $e) {
        error_log('Banka hesabı okunamadı: ' . $e->getMessage());
        $duzenleId = 0;
    }
}

$mesaj = null;
if (!empty($_SESSION['bnk_mesaj'])) {
    $mesaj = $_SESSION['bnk_mesaj'];
    unset($_SESSION['bnk_mesaj']);
}

try {
    $hesaplar = Database::fetchAll("SELECT * FROM bank_accounts ORDER BY display_order, bank_name");
} catch (Throwable $e) {
    error_log('Banka hesapları okunamadı: ' . $e->getMessage());
    $hesaplar = [];
}

$formAcik = $hatalar !== [] || $duzenleId > 0;

require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* ==========================================
       Banka hesapları - bn
       ========================================== */
    .bn-liste {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
        gap: 14px;
    }

    .bn-kart {
        display: flex;
        flex-direction: column;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .bn-kart.pasif {
        opacity: .68;
    }

    .bn-bas {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 15px 18px;
        border-bottom: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
    }

    .bn-ikon {
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

    .bn-bas b {
        display: block;
        font-size: 14px;
        font-weight: 700;
        color: var(--y-metin);
    }

    .bn-bas span {
        font-size: 12px;
        color: var(--y-metin-3);
    }

    .bn-govde {
        flex: 1;
        padding: 15px 18px;
    }

    .bn-iban {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 10px 13px;
        border: 1px dashed var(--y-cizgi);
        border-radius: var(--y-r-sm);
        background: var(--y-yuzey-2);
        font-family: ui-monospace, Consolas, monospace;
        font-size: 13px;
        letter-spacing: .04em;
        color: var(--y-metin);
        word-break: break-all;
    }

    .bn-kopyala {
        margin-left: auto;
        flex-shrink: 0;
        border: none;
        background: none;
        padding: 3px 6px;
        color: var(--y-metin-3);
        font-size: 12px;
        cursor: pointer;
    }

    .bn-kopyala:hover {
        color: var(--y-primary);
    }

    .bn-satir {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 7px 0;
        border-bottom: 1px solid var(--y-cizgi-soft);
        font-size: 12.5px;
    }

    .bn-satir:last-child {
        border-bottom: none;
    }

    .bn-satir span:first-child {
        color: var(--y-metin-3);
    }

    .bn-satir span:last-child {
        color: var(--y-metin);
        text-align: right;
        word-break: break-word;
    }

    .bn-not {
        margin-top: 10px;
        padding: 9px 12px;
        border-radius: var(--y-r-sm);
        background: var(--y-yuzey-2);
        font-size: 12px;
        line-height: 1.55;
        color: var(--y-metin-2);
    }

    .bn-alt {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 18px;
        border-top: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
    }

    .bn-alt form {
        display: inline;
    }

    .bn-alt .badge {
        margin-right: auto;
    }

    .bn-form {
        margin-bottom: 20px;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .bn-form summary {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 13px 18px;
        background: var(--y-yuzey-2);
        font-size: 13.5px;
        font-weight: 700;
        color: var(--y-metin);
        cursor: pointer;
        list-style: none;
    }

    .bn-form summary::-webkit-details-marker {
        display: none;
    }

    .bn-form summary i.ok {
        margin-left: auto;
        font-size: 11px;
        color: var(--y-metin-3);
        transition: transform .18s;
    }

    .bn-form[open] summary i.ok {
        transform: rotate(90deg);
    }

    .bn-form-govde {
        padding: 18px;
        border-top: 1px solid var(--y-cizgi);
    }

    .bn-hata {
        display: block;
        margin-top: 5px;
        font-size: 11.5px;
        color: var(--y-danger);
    }

    .form-group.hatali input,
    .form-group.hatali select {
        border-color: var(--y-danger);
    }

    .bn-form-alt {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--y-cizgi);
    }

    .bn-onay {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-right: auto;
        font-size: 13px;
        color: var(--y-metin-2);
    }

    .bn-onay label {
        margin: 0;
        font-size: 13px;
    }
</style>

<div class="page-header">
    <div>
        <h1>Banka hesapları</h1>
        <p><?= count($hesaplar) ?> hesap &middot; havale ile ödeme sayfasında gösterilir</p>
    </div>
</div>

<?php if ($mesaj): ?>
    <div class="alert alert-<?= $mesaj['tip'] === 'success' ? 'success' : 'error' ?>">
        <i class="fas fa-<?= $mesaj['tip'] === 'success' ? 'circle-check' : 'circle-exclamation' ?> alert-icon"></i>
        <span><?= htmlspecialchars((string) $mesaj['metin']) ?></span>
    </div>
<?php endif; ?>

<details class="bn-form" <?= $formAcik ? 'open' : '' ?>>
    <summary>
        <i class="fas fa-<?= $duzenleId > 0 ? 'pen' : 'plus' ?>"></i>
        <?= $duzenleId > 0 ? 'Hesabı düzenle' : 'Yeni banka hesabı' ?>
        <i class="fas fa-chevron-right ok"></i>
    </summary>
    <div class="bn-form-govde">
        <?php if (isset($hatalar['genel'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-circle-exclamation alert-icon"></i>
                <span><?= htmlspecialchars($hatalar['genel']) ?></span>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= Guvenlik::alan() ?>
            <input type="hidden" name="action" value="<?= $duzenleId > 0 ? 'edit' : 'add' ?>">
            <?php if ($duzenleId > 0): ?>
                <input type="hidden" name="id" value="<?= $duzenleId ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group <?= isset($hatalar['bank_name']) ? 'hatali' : '' ?>">
                    <label for="bank_name">Banka adı *</label>
                    <input type="text" id="bank_name" name="bank_name" required maxlength="120"
                        value="<?= htmlspecialchars((string) $eski['bank_name']) ?>">
                    <?php if (isset($hatalar['bank_name'])): ?>
                        <span class="bn-hata"><?= htmlspecialchars($hatalar['bank_name']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group <?= isset($hatalar['account_holder']) ? 'hatali' : '' ?>">
                    <label for="account_holder">Hesap sahibi *</label>
                    <input type="text" id="account_holder" name="account_holder" required maxlength="160"
                        value="<?= htmlspecialchars((string) $eski['account_holder']) ?>">
                    <?php if (isset($hatalar['account_holder'])): ?>
                        <span class="bn-hata"><?= htmlspecialchars($hatalar['account_holder']) ?></span>
                    <?php else: ?>
                        <small>Faturadaki unvanla aynı olmalı.</small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group <?= isset($hatalar['iban']) ? 'hatali' : '' ?>">
                <label for="iban">IBAN</label>
                <input type="text" id="iban" name="iban" maxlength="40" dir="ltr"
                    style="font-family: ui-monospace, Consolas, monospace; letter-spacing:.04em"
                    placeholder="TR00 0000 0000 0000 0000 0000 00"
                    value="<?= htmlspecialchars(ibanBicimle((string) $eski['iban'])) ?>">
                <?php if (isset($hatalar['iban'])): ?>
                    <span class="bn-hata"><?= htmlspecialchars($hatalar['iban']) ?></span>
                <?php else: ?>
                    <small>Kaydederken mod-97 sağlaması yapılır; hatalı IBAN kabul edilmez.</small>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="account_number">Hesap numarası</label>
                    <input type="text" id="account_number" name="account_number" maxlength="60" dir="ltr"
                        value="<?= htmlspecialchars((string) $eski['account_number']) ?>">
                </div>
                <div class="form-group">
                    <label for="branch_name">Şube</label>
                    <input type="text" id="branch_name" name="branch_name" maxlength="120"
                        value="<?= htmlspecialchars((string) $eski['branch_name']) ?>">
                </div>
                <div class="form-group">
                    <label for="branch_code">Şube kodu</label>
                    <input type="text" id="branch_code" name="branch_code" maxlength="30" dir="ltr"
                        value="<?= htmlspecialchars((string) $eski['branch_code']) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group <?= isset($hatalar['swift_code']) ? 'hatali' : '' ?>">
                    <label for="swift_code">SWIFT / BIC</label>
                    <input type="text" id="swift_code" name="swift_code" maxlength="20" dir="ltr"
                        value="<?= htmlspecialchars((string) $eski['swift_code']) ?>">
                    <?php if (isset($hatalar['swift_code'])): ?>
                        <span class="bn-hata"><?= htmlspecialchars($hatalar['swift_code']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="currency">Para birimi</label>
                    <select id="currency" name="currency">
                        <?php foreach ($paraBirimleri as $kod => $ad): ?>
                            <option value="<?= $kod ?>" <?= $eski['currency'] === $kod ? 'selected' : '' ?>>
                                <?= $kod ?> — <?= $ad ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="display_order">Sıra</label>
                    <input type="number" id="display_order" name="display_order" step="1"
                        value="<?= (int) $eski['display_order'] ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Müşteriye gösterilecek not</label>
                <textarea id="notes" name="notes" maxlength="1000" rows="2" style="min-height:64px"
                    placeholder="Açıklama alanına fatura numarasını yazın…"><?= htmlspecialchars((string) $eski['notes']) ?></textarea>
            </div>

            <div class="bn-form-alt">
                <span class="bn-onay">
                    <input type="checkbox" id="is_active" name="is_active" value="1"
                        <?= (int) $eski['is_active'] === 1 ? 'checked' : '' ?>>
                    <label for="is_active">Ödeme sayfasında göster</label>
                </span>
                <?php if ($duzenleId > 0): ?>
                    <a href="bank-accounts.php" class="btn btn-outline">Vazgeç</a>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> <?= $duzenleId > 0 ? 'Güncelle' : 'Hesabı ekle' ?>
                </button>
            </div>
        </form>
    </div>
</details>

<?php if (!$hesaplar): ?>
    <div class="bn-kart">
        <div class="empty-state">
            <i class="fas fa-building-columns"></i>
            <h3>Tanımlı banka hesabı yok</h3>
            <p>Havale ile ödeme seçeneği, burada en az bir etkin hesap tanımlanana kadar
                müşteriye hesap bilgisi gösteremez.</p>
        </div>
    </div>
<?php else: ?>
    <div class="bn-liste">
        <?php foreach ($hesaplar as $h):
            $id = (int) $h['id'];
            $etkin = (int) $h['is_active'] === 1;
            $iban = (string) ($h['iban'] ?? '');
            ?>
            <div class="bn-kart <?= $etkin ? '' : 'pasif' ?>">
                <div class="bn-bas">
                    <span class="bn-ikon"><i class="fas fa-building-columns"></i></span>
                    <span>
                        <b><?= htmlspecialchars((string) $h['bank_name']) ?></b>
                        <span><?= htmlspecialchars((string) $h['account_holder']) ?></span>
                    </span>
                </div>

                <div class="bn-govde">
                    <?php if ($iban !== ''): ?>
                        <div class="bn-iban">
                            <span id="iban-<?= $id ?>"><?= htmlspecialchars(ibanBicimle($iban)) ?></span>
                            <button type="button" class="bn-kopyala" data-iban="<?= htmlspecialchars($iban) ?>"
                                title="IBAN'ı kopyala">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top: 12px;">
                        <?php if (!empty($h['account_number'])): ?>
                            <div class="bn-satir">
                                <span>Hesap no</span>
                                <span dir="ltr"><?= htmlspecialchars((string) $h['account_number']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($h['branch_name'])): ?>
                            <div class="bn-satir">
                                <span>Şube</span>
                                <span><?= htmlspecialchars((string) $h['branch_name']) ?>
                                    <?= !empty($h['branch_code']) ? ' (' . htmlspecialchars((string) $h['branch_code']) . ')' : '' ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($h['swift_code'])): ?>
                            <div class="bn-satir">
                                <span>SWIFT</span>
                                <span dir="ltr"><?= htmlspecialchars((string) $h['swift_code']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="bn-satir">
                            <span>Para birimi</span>
                            <span><?= htmlspecialchars((string) $h['currency']) ?></span>
                        </div>
                    </div>

                    <?php if (!empty($h['notes'])): ?>
                        <div class="bn-not"><?= nl2br(htmlspecialchars((string) $h['notes'])) ?></div>
                    <?php endif; ?>
                </div>

                <div class="bn-alt">
                    <span class="badge <?= $etkin ? 'badge-success' : '' ?>">
                        <?= $etkin ? 'Yayında' : 'Gizli' ?>
                    </span>

                    <a class="action-btn" href="?edit=<?= $id ?>" title="Düzenle">
                        <i class="fas fa-pen"></i>
                    </a>

                    <form method="post">
                        <?= Guvenlik::alan() ?>
                        <input type="hidden" name="durum_id" value="<?= $id ?>">
                        <input type="hidden" name="durum" value="<?= $etkin ? 0 : 1 ?>">
                        <button type="submit" class="action-btn"
                            title="<?= $etkin ? 'Yayından kaldır' : 'Yayına al' ?>">
                            <i class="fas fa-<?= $etkin ? 'eye-slash' : 'eye' ?>"></i>
                        </button>
                    </form>

                    <form method="post"
                        onsubmit="return confirm(<?= htmlspecialchars(json_encode(
                            $h['bank_name'] . ' hesabı silinecek. Devam edilsin mi?',
                            JSON_UNESCAPED_UNICODE
                        ), ENT_QUOTES) ?>);">
                        <?= Guvenlik::alan() ?>
                        <input type="hidden" name="delete" value="<?= $id ?>">
                        <button type="submit" class="action-btn" title="Sil">
                            <i class="fas fa-trash-can"></i>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('click', function (olay) {
        var dugme = olay.target.closest('.bn-kopyala');
        if (!dugme) {
            return;
        }
        var iban = dugme.dataset.iban || '';
        if (!navigator.clipboard) {
            return;
        }
        navigator.clipboard.writeText(iban).then(function () {
            var ikon = dugme.querySelector('i');
            ikon.className = 'fas fa-check';
            setTimeout(function () {
                ikon.className = 'fas fa-copy';
            }, 1400);
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
