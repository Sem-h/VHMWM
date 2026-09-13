<?php
/**
 * VHM - Müşteriler
 *
 * Önceki sürümdeki sorunlar:
 *   - Ekleme formunda hiçbir doğrulama yoktu. email sütunu UNIQUE
 *     olduğu için aynı adresle ikinci kayıt denendiğinde sayfa
 *     ölümcül hatayla çöküyordu.
 *   - Ekleme sonrası yönlendirme yoktu; yenilemede aynı kayıt tekrar
 *     eklenmeye çalışılıyordu.
 *   - Silme onayı, faturaların ve ödeme kayıtlarının da silineceğini
 *     söylemiyordu. clients tablosuna bağlı 6 tablo CASCADE ile siliniyor.
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

$pageTitle = 'Müşteriler';
$currentPage = 'clients';

$db = Database::getInstance();
$hatalar = [];
$eski = [
    'account_type' => 'individual',
    'first_name' => '', 'last_name' => '', 'email' => '',
    'company_name' => '', 'tax_id' => '', 'tax_office' => '',
    'phone' => '', 'address' => '', 'city' => '', 'country' => 'TR',
];

/* ---------- Silme ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && is_numeric($_POST['delete'])) {
    Guvenlik::zorunlu();
    $id = (int) $_POST['delete'];

    try {
        $ad = Database::fetchColumn(
            "SELECT CONCAT(first_name, ' ', last_name) FROM clients WHERE id = ?",
            [$id]
        );
        Database::query("DELETE FROM clients WHERE id = ?", [$id]);
        $_SESSION['mus_mesaj'] = [
            'tip' => 'success',
            'metin' => ($ad ?: 'Müşteri') . ' ve bağlı tüm kayıtları silindi.',
        ];
    } catch (Throwable $e) {
        error_log('Müşteri silinemedi: ' . $e->getMessage());
        $_SESSION['mus_mesaj'] = ['tip' => 'error', 'metin' => 'Müşteri silinemedi.'];
    }

    header('Location: clients.php');
    exit;
}

/* ---------- Durum değiştirme ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['durum_id'])) {
    Guvenlik::zorunlu();
    $id = (int) $_POST['durum_id'];
    $yeni = ((int) ($_POST['durum'] ?? 0)) === 1 ? 1 : 0;

    try {
        Database::update('clients', ['is_active' => $yeni], 'id = ?', [$id]);
        $_SESSION['mus_mesaj'] = [
            'tip' => 'success',
            'metin' => $yeni === 1 ? 'Müşteri etkinleştirildi.' : 'Müşteri pasife alındı.',
        ];
    } catch (Throwable $e) {
        error_log('Müşteri durumu değiştirilemedi: ' . $e->getMessage());
        $_SESSION['mus_mesaj'] = ['tip' => 'error', 'metin' => 'Durum değiştirilemedi.'];
    }

    header('Location: clients.php');
    exit;
}

/* ---------- Ekleme ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    Guvenlik::zorunlu();

    $kirp = static fn(string $a, int $n): string => mb_substr(trim((string) ($_POST[$a] ?? '')), 0, $n);

    $eski = [
        'account_type' => ($_POST['account_type'] ?? 'individual') === 'corporate' ? 'corporate' : 'individual',
        'first_name' => $kirp('first_name', 100),
        'last_name' => $kirp('last_name', 100),
        'email' => $kirp('email', 255),
        'company_name' => $kirp('company_name', 255),
        'tax_id' => $kirp('tax_id', 50),
        'tax_office' => $kirp('tax_office', 100),
        'phone' => $kirp('phone', 30),
        'address' => $kirp('address', 500),
        'city' => $kirp('city', 100),
        'country' => mb_strtoupper($kirp('country', 2)) ?: 'TR',
    ];
    $parola = (string) ($_POST['password'] ?? '');

    if (mb_strlen($eski['first_name']) < 2) {
        $hatalar['first_name'] = 'Ad en az 2 karakter olmalı.';
    }
    if (mb_strlen($eski['last_name']) < 2) {
        $hatalar['last_name'] = 'Soyad en az 2 karakter olmalı.';
    }
    if (!filter_var($eski['email'], FILTER_VALIDATE_EMAIL)) {
        $hatalar['email'] = 'Geçerli bir e-posta adresi yazın.';
    } elseif (Database::fetch("SELECT id FROM clients WHERE email = ?", [$eski['email']])) {
        /* email sütunu UNIQUE; önceden denetlenmezse sorgu istisna fırlatıyordu */
        $hatalar['email'] = 'Bu e-posta adresi zaten kayıtlı.';
    }
    if (mb_strlen($parola) < 8) {
        $hatalar['password'] = 'Parola en az 8 karakter olmalı.';
    }
    if ($eski['account_type'] === 'corporate' && $eski['company_name'] === '') {
        $hatalar['company_name'] = 'Kurumsal hesapta şirket adı zorunlu.';
    }
    if ($eski['phone'] !== '') {
        $rakam = preg_replace('/\D+/', '', $eski['phone']) ?? '';
        if (strlen($rakam) < 10 || strlen($rakam) > 13) {
            $hatalar['phone'] = 'Telefonu 10 haneli yazın.';
        }
    }

    if (!$hatalar) {
        try {
            Database::insert('clients', [
                'account_type' => $eski['account_type'],
                'email' => $eski['email'],
                'password' => password_hash($parola, PASSWORD_DEFAULT),
                'first_name' => $eski['first_name'],
                'last_name' => $eski['last_name'],
                'company_name' => $eski['company_name'] !== '' ? $eski['company_name'] : null,
                'tax_id' => $eski['tax_id'] !== '' ? $eski['tax_id'] : null,
                'tax_office' => $eski['tax_office'] !== '' ? $eski['tax_office'] : null,
                'phone' => $eski['phone'] !== '' ? $eski['phone'] : null,
                'address' => $eski['address'] !== '' ? $eski['address'] : null,
                'city' => $eski['city'] !== '' ? $eski['city'] : null,
                'country' => $eski['country'],
                'is_active' => 1,
            ]);

            /* Yenilemede aynı kaydın tekrar eklenmemesi için yönlendir */
            $_SESSION['mus_mesaj'] = [
                'tip' => 'success',
                'metin' => $eski['first_name'] . ' ' . $eski['last_name'] . ' eklendi.',
            ];
            header('Location: clients.php');
            exit;
        } catch (Throwable $e) {
            error_log('Müşteri eklenemedi: ' . $e->getMessage());
            $hatalar['genel'] = 'Müşteri kaydedilemedi. Lütfen tekrar deneyin.';
        }
    }
}

$mesaj = null;
if (!empty($_SESSION['mus_mesaj'])) {
    $mesaj = $_SESSION['mus_mesaj'];
    unset($_SESSION['mus_mesaj']);
}

/* ---------- Arama, süzme, sayfalama ---------- */
$arama = trim((string) ($_GET['q'] ?? $_GET['search'] ?? ''));
$suzgec = (string) ($_GET['durum'] ?? '');

$kosullar = [];
$par = [];

if ($arama !== '') {
    $kosullar[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR company_name LIKE ? OR phone LIKE ?)";
    $desen = '%' . str_replace(['%', '_'], ['\%', '\_'], $arama) . '%';
    $par = array_merge($par, array_fill(0, 5, $desen));
}
if ($suzgec === 'aktif') {
    $kosullar[] = "is_active = 1";
} elseif ($suzgec === 'pasif') {
    $kosullar[] = "is_active = 0";
} elseif ($suzgec === 'kurumsal') {
    $kosullar[] = "account_type = 'corporate'";
}

$nerede = $kosullar ? ' WHERE ' . implode(' AND ', $kosullar) : '';

$sayfa = max(1, (int) ($_GET['page'] ?? 1));
$adet = 20;
$atla = ($sayfa - 1) * $adet;

$toplam = (int) Database::fetchColumn("SELECT COUNT(*) FROM clients" . $nerede, $par);
$sayfaSayisi = max(1, (int) ceil($toplam / $adet));

$musteriler = Database::fetchAll(
    "SELECT * FROM clients" . $nerede . " ORDER BY created_at DESC LIMIT {$adet} OFFSET {$atla}",
    $par
);

/* Listedeki müşterilerin bağlı kayıt sayıları - silme uyarısı için */
$bagliSayi = [];
if ($musteriler) {
    $idler = array_map(static fn(array $m): int => (int) $m['id'], $musteriler);
    $yer = implode(',', array_fill(0, count($idler), '?'));

    foreach (['invoices' => 'fatura', 'services' => 'hizmet', 'orders' => 'sipariş', 'transactions' => 'ödeme kaydı'] as $tablo => $ad) {
        try {
            foreach (Database::fetchAll(
                "SELECT client_id, COUNT(*) AS adet FROM `{$tablo}` WHERE client_id IN ({$yer}) GROUP BY client_id",
                $idler
            ) as $r) {
                if ((int) $r['adet'] > 0) {
                    $bagliSayi[(int) $r['client_id']][] = (int) $r['adet'] . ' ' . $ad;
                }
            }
        } catch (Throwable $e) {
            error_log('Bağlı kayıt sayılamadı (' . $tablo . '): ' . $e->getMessage());
        }
    }
}

/* ---------- Özet ---------- */
$sayi = static function (string $sql): int {
    try {
        return (int) Database::fetchColumn($sql);
    } catch (Throwable $e) {
        return 0;
    }
};
$ozet = [
    ['Toplam müşteri', $sayi("SELECT COUNT(*) FROM clients"), 'fa-users', ''],
    ['Etkin', $sayi("SELECT COUNT(*) FROM clients WHERE is_active = 1"), 'fa-circle-check', 'aktif'],
    ['Kurumsal', $sayi("SELECT COUNT(*) FROM clients WHERE account_type = 'corporate'"), 'fa-building', 'kurumsal'],
    ['Bu ay eklenen', $sayi("SELECT COUNT(*) FROM clients WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"), 'fa-user-plus', ''],
];

$formAcik = $hatalar !== [];

require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* ==========================================
       Müşteriler - ms
       ========================================== */
    .ms-ozet {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }

    .ms-ozet-kart {
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

    .ms-ozet-kart:hover {
        border-color: var(--y-primary);
        text-decoration: none;
    }

    .ms-ozet-kart.secili {
        border-color: var(--y-primary);
        background: var(--y-primary-soft);
    }

    .ms-ozet-ikon {
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

    .ms-ozet-kart b {
        display: block;
        font-size: 21px;
        font-weight: 700;
        letter-spacing: -.02em;
        color: var(--y-metin);
        line-height: 1.2;
    }

    .ms-ozet-kart span {
        font-size: 12.5px;
        color: var(--y-metin-3);
    }

    /* Araç çubuğu */
    .ms-arac {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }

    .ms-ara {
        position: relative;
        flex: 1;
        min-width: 220px;
        max-width: 420px;
    }

    .ms-ara i {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 13px;
        color: var(--y-metin-3);
        pointer-events: none;
    }

    .ms-ara input {
        width: 100%;
        padding-left: 36px;
    }

    .ms-ara-temizle {
        position: absolute;
        right: 9px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--y-metin-3);
        font-size: 12px;
    }

    /* Tablo */
    .ms-sarmal {
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .ms-tablo {
        width: 100%;
        border-collapse: collapse;
        font-size: 13.5px;
    }

    .ms-tablo th {
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

    .ms-tablo td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--y-cizgi-soft);
        vertical-align: middle;
    }

    .ms-tablo tr:last-child td {
        border-bottom: none;
    }

    .ms-tablo tbody tr:hover td {
        background: var(--y-yuzey-2);
    }

    .ms-kisi {
        display: flex;
        align-items: center;
        gap: 11px;
    }

    .ms-avatar {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border-radius: 50%;
        background: var(--y-primary-soft);
        color: var(--y-primary);
        font-size: 13px;
        font-weight: 700;
    }

    .ms-kisi b {
        display: block;
        font-weight: 600;
        color: var(--y-metin);
    }

    .ms-kisi span {
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .ms-iletisim a {
        display: block;
        color: var(--y-metin-2);
        font-size: 13px;
    }

    .ms-iletisim a:hover {
        color: var(--y-primary);
    }

    .ms-iletisim span {
        display: block;
        margin-top: 2px;
        font-size: 12px;
        color: var(--y-metin-3);
    }

    .ms-eylem {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
    }

    .ms-eylem form {
        display: inline;
    }

    .ms-sag {
        text-align: right;
        white-space: nowrap;
    }

    /* Ekleme formu */
    .ms-form {
        margin-bottom: 20px;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .ms-form summary {
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

    .ms-form summary::-webkit-details-marker {
        display: none;
    }

    .ms-form summary i.ok {
        margin-left: auto;
        font-size: 11px;
        color: var(--y-metin-3);
        transition: transform .18s;
    }

    .ms-form[open] summary i.ok {
        transform: rotate(90deg);
    }

    .ms-form-govde {
        padding: 18px;
        border-top: 1px solid var(--y-cizgi);
    }

    .ms-hata {
        display: block;
        margin-top: 5px;
        font-size: 11.5px;
        color: var(--y-danger);
    }

    .form-group.hatali input,
    .form-group.hatali select {
        border-color: var(--y-danger);
    }

    .ms-tur {
        display: flex;
        gap: 9px;
        margin-bottom: 16px;
    }

    .ms-tur label {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        padding: 9px 16px;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r-sm);
        font-size: 13px;
        font-weight: 600;
        color: var(--y-metin-2);
        cursor: pointer;
    }

    .ms-tur label:has(input:checked) {
        border-color: var(--y-primary);
        background: var(--y-primary-soft);
        color: var(--y-primary);
    }

    .ms-form-alt {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--y-cizgi);
    }

    @media (max-width: 900px) {
        .ms-gizle-orta {
            display: none;
        }
    }

    @media (max-width: 640px) {
        .ms-gizle-kucuk {
            display: none;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>Müşteriler</h1>
        <p><?= number_format($toplam, 0, ',', '.') ?> kayıt<?= $arama !== '' || $suzgec !== '' ? ' (süzülmüş)' : '' ?></p>
    </div>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('ms-form').open = true;
        document.getElementById('ms-form').scrollIntoView({behavior:'smooth'});
        document.getElementById('first_name').focus();">
        <i class="fas fa-user-plus"></i> Yeni müşteri
    </button>
</div>

<?php if ($mesaj): ?>
    <div class="alert alert-<?= $mesaj['tip'] === 'success' ? 'success' : 'error' ?>">
        <i class="fas fa-<?= $mesaj['tip'] === 'success' ? 'circle-check' : 'circle-exclamation' ?> alert-icon"></i>
        <span><?= htmlspecialchars((string) $mesaj['metin']) ?></span>
    </div>
<?php endif; ?>

<!-- Özet -->
<div class="ms-ozet">
    <?php foreach ($ozet as [$ad, $deger, $ikon, $filtre]): ?>
        <a class="ms-ozet-kart <?= $filtre !== '' && $suzgec === $filtre ? 'secili' : '' ?>"
            href="clients.php<?= $filtre !== '' ? '?durum=' . $filtre : '' ?>">
            <span class="ms-ozet-ikon"><i class="fas <?= $ikon ?>"></i></span>
            <span>
                <b><?= number_format($deger, 0, ',', '.') ?></b>
                <span><?= $ad ?></span>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Ekleme formu -->
<details class="ms-form" id="ms-form" <?= $formAcik ? 'open' : '' ?>>
    <summary>
        <i class="fas fa-user-plus"></i> Yeni müşteri ekle
        <i class="fas fa-chevron-right ok"></i>
    </summary>
    <div class="ms-form-govde">
        <?php if (isset($hatalar['genel'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-circle-exclamation alert-icon"></i>
                <span><?= htmlspecialchars($hatalar['genel']) ?></span>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= Guvenlik::alan() ?>
            <input type="hidden" name="action" value="add">

            <div class="ms-tur">
                <label>
                    <input type="radio" name="account_type" value="individual"
                        <?= $eski['account_type'] === 'individual' ? 'checked' : '' ?>>
                    Bireysel
                </label>
                <label>
                    <input type="radio" name="account_type" value="corporate"
                        <?= $eski['account_type'] === 'corporate' ? 'checked' : '' ?>>
                    Kurumsal
                </label>
            </div>

            <div class="form-row">
                <div class="form-group <?= isset($hatalar['first_name']) ? 'hatali' : '' ?>">
                    <label for="first_name">Ad *</label>
                    <input type="text" id="first_name" name="first_name" required maxlength="100"
                        value="<?= htmlspecialchars($eski['first_name']) ?>">
                    <?php if (isset($hatalar['first_name'])): ?>
                        <span class="ms-hata"><?= htmlspecialchars($hatalar['first_name']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group <?= isset($hatalar['last_name']) ? 'hatali' : '' ?>">
                    <label for="last_name">Soyad *</label>
                    <input type="text" id="last_name" name="last_name" required maxlength="100"
                        value="<?= htmlspecialchars($eski['last_name']) ?>">
                    <?php if (isset($hatalar['last_name'])): ?>
                        <span class="ms-hata"><?= htmlspecialchars($hatalar['last_name']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group <?= isset($hatalar['email']) ? 'hatali' : '' ?>">
                    <label for="email">E-posta *</label>
                    <input type="email" id="email" name="email" required maxlength="255" dir="ltr"
                        value="<?= htmlspecialchars($eski['email']) ?>">
                    <?php if (isset($hatalar['email'])): ?>
                        <span class="ms-hata"><?= htmlspecialchars($hatalar['email']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group <?= isset($hatalar['password']) ? 'hatali' : '' ?>">
                    <label for="password">Parola *</label>
                    <input type="text" id="password" name="password" required minlength="8"
                        autocomplete="new-password" placeholder="En az 8 karakter">
                    <?php if (isset($hatalar['password'])): ?>
                        <span class="ms-hata"><?= htmlspecialchars($hatalar['password']) ?></span>
                    <?php else: ?>
                        <small>Müşteriye bu parolayla giriş yapacağı bildirilmeli.</small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group <?= isset($hatalar['company_name']) ? 'hatali' : '' ?>">
                    <label for="company_name">Şirket adı</label>
                    <input type="text" id="company_name" name="company_name" maxlength="255"
                        value="<?= htmlspecialchars($eski['company_name']) ?>">
                    <?php if (isset($hatalar['company_name'])): ?>
                        <span class="ms-hata"><?= htmlspecialchars($hatalar['company_name']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group <?= isset($hatalar['phone']) ? 'hatali' : '' ?>">
                    <label for="phone">Telefon</label>
                    <input type="tel" id="phone" name="phone" maxlength="30" dir="ltr"
                        placeholder="532 123 45 67" value="<?= htmlspecialchars($eski['phone']) ?>">
                    <?php if (isset($hatalar['phone'])): ?>
                        <span class="ms-hata"><?= htmlspecialchars($hatalar['phone']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="tax_office">Vergi dairesi</label>
                    <input type="text" id="tax_office" name="tax_office" maxlength="100"
                        value="<?= htmlspecialchars($eski['tax_office']) ?>">
                </div>
                <div class="form-group">
                    <label for="tax_id">Vergi / TC numarası</label>
                    <input type="text" id="tax_id" name="tax_id" maxlength="50"
                        value="<?= htmlspecialchars($eski['tax_id']) ?>">
                </div>
                <div class="form-group">
                    <label for="city">Şehir</label>
                    <input type="text" id="city" name="city" maxlength="100"
                        value="<?= htmlspecialchars($eski['city']) ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="address">Adres</label>
                <textarea id="address" name="address" rows="2"
                    style="min-height:70px"><?= htmlspecialchars($eski['address']) ?></textarea>
            </div>

            <div class="ms-form-alt">
                <button type="reset" class="btn btn-outline">Temizle</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Müşteriyi kaydet
                </button>
            </div>
        </form>
    </div>
</details>

<!-- Araç çubuğu -->
<form class="ms-arac" method="get">
    <?php if ($suzgec !== ''): ?>
        <input type="hidden" name="durum" value="<?= htmlspecialchars($suzgec) ?>">
    <?php endif; ?>
    <div class="ms-ara">
        <i class="fas fa-magnifying-glass"></i>
        <input type="search" name="q" value="<?= htmlspecialchars($arama) ?>"
            placeholder="Ad, e-posta, şirket veya telefon ile ara…">
    </div>
    <div style="display:flex; gap:8px;">
        <button type="submit" class="btn btn-outline"><i class="fas fa-filter"></i> Ara</button>
        <?php if ($arama !== '' || $suzgec !== ''): ?>
            <a href="clients.php" class="btn btn-outline"><i class="fas fa-xmark"></i> Sıfırla</a>
        <?php endif; ?>
    </div>
</form>

<!-- Liste -->
<div class="ms-sarmal">
    <?php if (!$musteriler): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <h3><?= $arama !== '' || $suzgec !== '' ? 'Eşleşen müşteri yok' : 'Henüz müşteri yok' ?></h3>
            <p>
                <?= $arama !== '' || $suzgec !== ''
                    ? 'Aramayı değiştirin ya da süzgeci sıfırlayın.'
                    : 'İlk müşteriyi yukarıdaki formdan ekleyebilirsiniz.' ?>
            </p>
        </div>
    <?php else: ?>
        <table class="ms-tablo">
            <thead>
                <tr>
                    <th>Müşteri</th>
                    <th class="ms-gizle-kucuk">İletişim</th>
                    <th class="ms-gizle-orta">Şirket</th>
                    <th class="ms-sag ms-gizle-orta">Bakiye</th>
                    <th>Durum</th>
                    <th class="ms-gizle-kucuk">Kayıt</th>
                    <th class="ms-sag">İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($musteriler as $m):
                    $id = (int) $m['id'];
                    $ad = trim((string) $m['first_name'] . ' ' . (string) $m['last_name']);
                    $bas = mb_strtoupper(mb_substr($ad !== '' ? $ad : '?', 0, 1), 'UTF-8');
                    $etkin = (int) $m['is_active'] === 1;
                    $bagli = $bagliSayi[$id] ?? [];
                    $uyari = $bagli
                        ? $ad . ' silinecek. Bağlı ' . implode(', ', $bagli)
                        . ' da birlikte silinir ve geri alınamaz. Devam edilsin mi?'
                        : $ad . ' silinecek. Devam edilsin mi?';
                    ?>
                    <tr>
                        <td>
                            <div class="ms-kisi">
                                <span class="ms-avatar"><?= htmlspecialchars($bas) ?></span>
                                <span>
                                    <b><?= htmlspecialchars($ad !== '' ? $ad : 'İsimsiz') ?></b>
                                    <span>#<?= $id ?>
                                        <?= $m['account_type'] === 'corporate' ? '· Kurumsal' : '· Bireysel' ?></span>
                                </span>
                            </div>
                        </td>
                        <td class="ms-iletisim ms-gizle-kucuk">
                            <a href="mailto:<?= htmlspecialchars((string) $m['email']) ?>"
                                dir="ltr"><?= htmlspecialchars((string) $m['email']) ?></a>
                            <?php if (!empty($m['phone'])): ?>
                                <span dir="ltr"><?= htmlspecialchars((string) $m['phone']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="ms-gizle-orta">
                            <?= !empty($m['company_name'])
                                ? htmlspecialchars((string) $m['company_name'])
                                : '<span style="color:var(--y-metin-3)">—</span>' ?>
                        </td>
                        <td class="ms-sag ms-gizle-orta">
                            <?= number_format((float) ($m['credit_balance'] ?? 0), 2, ',', '.') ?> ₺
                        </td>
                        <td>
                            <span class="badge <?= $etkin ? 'badge-success' : '' ?>">
                                <?= $etkin ? 'Etkin' : 'Pasif' ?>
                            </span>
                        </td>
                        <td class="ms-gizle-kucuk" style="color:var(--y-metin-3); font-size:12.5px;">
                            <?= date('d.m.Y', strtotime((string) $m['created_at'])) ?>
                        </td>
                        <td class="ms-sag">
                            <div class="ms-eylem">
                                <a class="action-btn" href="client-view.php?id=<?= $id ?>" title="Görüntüle">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a class="action-btn" href="client-edit.php?id=<?= $id ?>" title="Düzenle">
                                    <i class="fas fa-pen"></i>
                                </a>

                                <form method="post">
                                    <?= Guvenlik::alan() ?>
                                    <input type="hidden" name="durum_id" value="<?= $id ?>">
                                    <input type="hidden" name="durum" value="<?= $etkin ? 0 : 1 ?>">
                                    <button type="submit" class="action-btn"
                                        title="<?= $etkin ? 'Pasife al' : 'Etkinleştir' ?>">
                                        <i class="fas fa-<?= $etkin ? 'user-slash' : 'user-check' ?>"></i>
                                    </button>
                                </form>

                                <form method="post"
                                    onsubmit="return confirm(<?= htmlspecialchars(json_encode($uyari, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>);">
                                    <?= Guvenlik::alan() ?>
                                    <input type="hidden" name="delete" value="<?= $id ?>">
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
            $p['durum'] = $suzgec;
        }
        return 'clients.php?' . http_build_query($p);
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
