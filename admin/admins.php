<?php
/**
 * VHM - Yöneticiler
 *
 * Önceki sürümdeki sorunlar:
 *   - Yönetici eklemede CSRF denetimi ve hiçbir doğrulama yoktu.
 *     admins.username ve admins.email UNIQUE olduğu için aynı kullanıcı
 *     adıyla ikinci kayıt denendiğinde sayfa ölümcül hatayla çöküyordu.
 *   - $_POST['role'] enum dışı değer kabul ediyordu.
 *   - Parola uzunluğu denetlenmiyordu.
 *   - PRG yoktu; yenilemede aynı kayıt tekrar eklenmeye çalışılıyordu.
 *
 * Silme yalnızca super_admin rolüne açıktır ve yönetici kendini silemez.
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

$pageTitle = 'Yöneticiler';
$currentPage = 'admins';

$benimId = (int) $_SESSION['admin_id'];
$superMi = ($_SESSION['admin_role'] ?? '') === 'super_admin';

/* admins.role enum'u ile birebir aynı */
$roller = [
    'super_admin' => ['Süper yönetici', 'Tüm yetkiler, yönetici ekleyip silebilir', 'fa-user-shield'],
    'admin' => ['Yönetici', 'Günlük yönetim işlemleri', 'fa-user-gear'],
    'support' => ['Destek', 'Destek talepleri ve müşteri işlemleri', 'fa-headset'],
    'sales' => ['Satış', 'Sipariş, teklif ve fatura işlemleri', 'fa-cart-shopping'],
];

function yoneticiMesaj(string $tip, string $metin): void
{
    $_SESSION['ynt_mesaj'] = ['tip' => $tip, 'metin' => $metin];
    header('Location: admins.php');
    exit;
}

$hatalar = [];
$eski = ['username' => '', 'email' => '', 'first_name' => '', 'last_name' => '', 'role' => 'admin'];

/* ---------- Silme ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && is_numeric($_POST['delete'])) {
    Guvenlik::zorunlu();

    if (!$superMi) {
        yoneticiMesaj('error', 'Yönetici silmek için süper yönetici olmanız gerekir.');
    }

    $id = (int) $_POST['delete'];
    if ($id === $benimId) {
        yoneticiMesaj('error', 'Kendi hesabınızı silemezsiniz.');
    }

    try {
        /* Son süper yönetici silinirse panele kimse giremez */
        $kalanSuper = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM admins WHERE role = 'super_admin' AND is_active = 1 AND id <> ?",
            [$id]
        );
        $silinecekRol = (string) Database::fetchColumn("SELECT role FROM admins WHERE id = ?", [$id]);

        if ($silinecekRol === 'super_admin' && $kalanSuper === 0) {
            yoneticiMesaj('error', 'Son süper yönetici silinemez; önce başka bir süper yönetici tanımlayın.');
        }

        Database::query("DELETE FROM admins WHERE id = ?", [$id]);
        yoneticiMesaj('success', 'Yönetici silindi.');
    } catch (Throwable $e) {
        error_log('Yönetici silinemedi: ' . $e->getMessage());
        yoneticiMesaj('error', 'Yönetici silinemedi.');
    }
}

/* ---------- Durum ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['durum_id'])) {
    Guvenlik::zorunlu();

    if (!$superMi) {
        yoneticiMesaj('error', 'Bu işlem için süper yönetici olmanız gerekir.');
    }

    $id = (int) $_POST['durum_id'];
    $yeni = ((int) ($_POST['durum'] ?? 0)) === 1 ? 1 : 0;

    if ($id === $benimId && $yeni === 0) {
        yoneticiMesaj('error', 'Kendi hesabınızı pasife alamazsınız.');
    }

    try {
        Database::update('admins', ['is_active' => $yeni], 'id = ?', [$id]);
        yoneticiMesaj('success', $yeni === 1 ? 'Hesap etkinleştirildi.' : 'Hesap pasife alındı.');
    } catch (Throwable $e) {
        error_log('Yönetici durumu değiştirilemedi: ' . $e->getMessage());
        yoneticiMesaj('error', 'Durum değiştirilemedi.');
    }
}

/* ---------- Ekleme ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    Guvenlik::zorunlu();

    if (!$superMi) {
        yoneticiMesaj('error', 'Yönetici eklemek için süper yönetici olmanız gerekir.');
    }

    $kirp = static fn(string $a, int $n): string => mb_substr(trim((string) ($_POST[$a] ?? '')), 0, $n);

    $eski = [
        'username' => $kirp('username', 50),
        'email' => $kirp('email', 255),
        'first_name' => $kirp('first_name', 100),
        'last_name' => $kirp('last_name', 100),
        'role' => isset($roller[$_POST['role'] ?? '']) ? (string) $_POST['role'] : 'admin',
    ];
    $parola = (string) ($_POST['password'] ?? '');

    if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $eski['username'])) {
        $hatalar['username'] = 'Kullanıcı adı 3-50 karakter olmalı; harf, rakam, nokta, alt çizgi ve tire kullanılabilir.';
    } elseif (Database::fetch("SELECT id FROM admins WHERE username = ?", [$eski['username']])) {
        /* username UNIQUE; denetlenmezse sorgu istisna fırlatıyordu */
        $hatalar['username'] = 'Bu kullanıcı adı zaten kullanılıyor.';
    }

    if (!filter_var($eski['email'], FILTER_VALIDATE_EMAIL)) {
        $hatalar['email'] = 'Geçerli bir e-posta adresi yazın.';
    } elseif (Database::fetch("SELECT id FROM admins WHERE email = ?", [$eski['email']])) {
        $hatalar['email'] = 'Bu e-posta adresi zaten kayıtlı.';
    }

    if (mb_strlen($parola) < 10) {
        $hatalar['password'] = 'Yönetici parolası en az 10 karakter olmalı.';
    }
    if (mb_strlen($eski['first_name']) < 2) {
        $hatalar['first_name'] = 'Ad en az 2 karakter olmalı.';
    }

    if (!$hatalar) {
        try {
            Database::insert('admins', [
                'username' => $eski['username'],
                'email' => $eski['email'],
                'password' => password_hash($parola, PASSWORD_DEFAULT),
                'first_name' => $eski['first_name'],
                'last_name' => $eski['last_name'],
                'role' => $eski['role'],
                'is_active' => 1,
            ]);
            yoneticiMesaj('success', $eski['username'] . ' adlı yönetici eklendi.');
        } catch (Throwable $e) {
            error_log('Yönetici eklenemedi: ' . $e->getMessage());
            $hatalar['genel'] = 'Yönetici kaydedilemedi.';
        }
    }
}

$mesaj = null;
if (!empty($_SESSION['ynt_mesaj'])) {
    $mesaj = $_SESSION['ynt_mesaj'];
    unset($_SESSION['ynt_mesaj']);
}

try {
    $yoneticiler = Database::fetchAll(
        "SELECT * FROM admins ORDER BY FIELD(role,'super_admin','admin','support','sales'), username"
    );
} catch (Throwable $e) {
    error_log('Yönetici listesi okunamadı: ' . $e->getMessage());
    $yoneticiler = [];
}

$formAcik = $hatalar !== [];

require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* ==========================================
       Yöneticiler - yn
       ========================================== */
    .yn-liste {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
        gap: 14px;
    }

    .yn-kart {
        display: flex;
        flex-direction: column;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .yn-kart.ben {
        border-color: var(--y-primary);
    }

    .yn-bas {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 18px;
    }

    .yn-avatar {
        width: 44px;
        height: 44px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border-radius: 50%;
        background: var(--y-primary-soft);
        color: var(--y-primary);
        font-size: 16px;
        font-weight: 700;
    }

    .yn-kim {
        min-width: 0;
        flex: 1;
    }

    .yn-kim b {
        display: block;
        font-size: 14.5px;
        font-weight: 600;
        color: var(--y-metin);
    }

    .yn-kim span {
        display: block;
        font-size: 12px;
        color: var(--y-metin-3);
        word-break: break-all;
    }

    .yn-rol {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin: 0 18px 12px;
        padding: 5px 11px;
        border-radius: 999px;
        background: var(--y-yuzey-2);
        font-size: 12px;
        font-weight: 600;
        color: var(--y-metin-2);
        width: fit-content;
    }

    .yn-rol i {
        color: var(--y-primary);
    }

    .yn-bilgi {
        padding: 0 18px 14px;
        font-size: 12px;
        line-height: 1.6;
        color: var(--y-metin-3);
    }

    .yn-alt {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: auto;
        padding: 12px 18px;
        border-top: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
    }

    .yn-alt form {
        display: inline;
    }

    .yn-alt .badge {
        margin-right: auto;
    }

    .yn-form {
        margin-bottom: 20px;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .yn-form summary {
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

    .yn-form summary::-webkit-details-marker {
        display: none;
    }

    .yn-form summary i.ok {
        margin-left: auto;
        font-size: 11px;
        color: var(--y-metin-3);
        transition: transform .18s;
    }

    .yn-form[open] summary i.ok {
        transform: rotate(90deg);
    }

    .yn-form-govde {
        padding: 18px;
        border-top: 1px solid var(--y-cizgi);
    }

    .yn-hata {
        display: block;
        margin-top: 5px;
        font-size: 11.5px;
        color: var(--y-danger);
    }

    .form-group.hatali input,
    .form-group.hatali select {
        border-color: var(--y-danger);
    }

    .yn-rol-secim {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 9px;
        margin-bottom: 15px;
    }

    .yn-rol-secim label {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        margin: 0;
        padding: 11px 14px;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r-sm);
        cursor: pointer;
        font-weight: 500;
    }

    .yn-rol-secim label:has(input:checked) {
        border-color: var(--y-primary);
        background: var(--y-primary-soft);
    }

    .yn-rol-secim b {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: var(--y-metin);
    }

    .yn-rol-secim span {
        display: block;
        margin-top: 2px;
        font-size: 11.5px;
        line-height: 1.45;
        color: var(--y-metin-3);
    }

    .yn-form-alt {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--y-cizgi);
    }
</style>

<div class="page-header">
    <div>
        <h1>Yöneticiler</h1>
        <p><?= count($yoneticiler) ?> hesap<?= $superMi ? '' : ' · yalnızca görüntüleme' ?></p>
    </div>
</div>

<?php if ($mesaj): ?>
    <div class="alert alert-<?= $mesaj['tip'] === 'success' ? 'success' : 'error' ?>">
        <i class="fas fa-<?= $mesaj['tip'] === 'success' ? 'circle-check' : 'circle-exclamation' ?> alert-icon"></i>
        <span><?= htmlspecialchars((string) $mesaj['metin']) ?></span>
    </div>
<?php endif; ?>

<?php if (!$superMi): ?>
    <div class="alert alert-info">
        <i class="fas fa-circle-info alert-icon"></i>
        <span>Yönetici ekleme, silme ve durum değiştirme yalnızca süper yöneticilere açıktır.</span>
    </div>
<?php else: ?>
    <details class="yn-form" <?= $formAcik ? 'open' : '' ?>>
        <summary>
            <i class="fas fa-user-plus"></i> Yeni yönetici ekle
            <i class="fas fa-chevron-right ok"></i>
        </summary>
        <div class="yn-form-govde">
            <?php if (isset($hatalar['genel'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-circle-exclamation alert-icon"></i>
                    <span><?= htmlspecialchars($hatalar['genel']) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= Guvenlik::alan() ?>
                <input type="hidden" name="action" value="add">

                <div class="form-row">
                    <div class="form-group <?= isset($hatalar['first_name']) ? 'hatali' : '' ?>">
                        <label for="first_name">Ad *</label>
                        <input type="text" id="first_name" name="first_name" required maxlength="100"
                            value="<?= htmlspecialchars($eski['first_name']) ?>">
                        <?php if (isset($hatalar['first_name'])): ?>
                            <span class="yn-hata"><?= htmlspecialchars($hatalar['first_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Soyad</label>
                        <input type="text" id="last_name" name="last_name" maxlength="100"
                            value="<?= htmlspecialchars($eski['last_name']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group <?= isset($hatalar['username']) ? 'hatali' : '' ?>">
                        <label for="username">Kullanıcı adı *</label>
                        <input type="text" id="username" name="username" required maxlength="50"
                            autocomplete="off" dir="ltr"
                            value="<?= htmlspecialchars($eski['username']) ?>">
                        <?php if (isset($hatalar['username'])): ?>
                            <span class="yn-hata"><?= htmlspecialchars($hatalar['username']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group <?= isset($hatalar['email']) ? 'hatali' : '' ?>">
                        <label for="email">E-posta *</label>
                        <input type="email" id="email" name="email" required maxlength="255" dir="ltr"
                            value="<?= htmlspecialchars($eski['email']) ?>">
                        <?php if (isset($hatalar['email'])): ?>
                            <span class="yn-hata"><?= htmlspecialchars($hatalar['email']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group <?= isset($hatalar['password']) ? 'hatali' : '' ?>">
                        <label for="password">Parola *</label>
                        <input type="text" id="password" name="password" required minlength="10"
                            autocomplete="new-password" placeholder="En az 10 karakter">
                        <?php if (isset($hatalar['password'])): ?>
                            <span class="yn-hata"><?= htmlspecialchars($hatalar['password']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <label>Rol</label>
                <div class="yn-rol-secim">
                    <?php foreach ($roller as $kod => [$ad, $aciklama, $ikon]): ?>
                        <label>
                            <input type="radio" name="role" value="<?= $kod ?>"
                                <?= $eski['role'] === $kod ? 'checked' : '' ?>>
                            <span>
                                <b><?= $ad ?></b>
                                <span><?= $aciklama ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="yn-form-alt">
                    <button type="reset" class="btn btn-outline">Temizle</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Yöneticiyi ekle
                    </button>
                </div>
            </form>
        </div>
    </details>
<?php endif; ?>

<div class="yn-liste">
    <?php foreach ($yoneticiler as $y):
        $id = (int) $y['id'];
        $ad = trim((string) $y['first_name'] . ' ' . (string) $y['last_name']);
        $bas = mb_strtoupper(mb_substr($ad !== '' ? $ad : (string) $y['username'], 0, 1), 'UTF-8');
        [$rolAd, , $rolIkon] = $roller[$y['role']] ?? [(string) $y['role'], '', 'fa-user'];
        $etkin = (int) $y['is_active'] === 1;
        $benMi = $id === $benimId;
        ?>
        <div class="yn-kart <?= $benMi ? 'ben' : '' ?>">
            <div class="yn-bas">
                <span class="yn-avatar"><?= htmlspecialchars($bas) ?></span>
                <span class="yn-kim">
                    <b><?= htmlspecialchars($ad !== '' ? $ad : (string) $y['username']) ?><?= $benMi ? ' (siz)' : '' ?></b>
                    <span dir="ltr"><?= htmlspecialchars((string) $y['email']) ?></span>
                </span>
            </div>

            <span class="yn-rol">
                <i class="fas <?= $rolIkon ?>"></i> <?= htmlspecialchars($rolAd) ?>
            </span>

            <div class="yn-bilgi">
                Kullanıcı adı: <?= htmlspecialchars((string) $y['username']) ?><br>
                <?php if (!empty($y['last_login'])): ?>
                    Son giriş: <?= date('d.m.Y H:i', strtotime((string) $y['last_login'])) ?>
                    <?php if (!empty($y['last_ip'])): ?>
                        &middot; <?= htmlspecialchars((string) $y['last_ip']) ?>
                    <?php endif; ?>
                <?php else: ?>
                    Henüz giriş yapmamış
                <?php endif; ?>
            </div>

            <div class="yn-alt">
                <span class="badge <?= $etkin ? 'badge-success' : '' ?>">
                    <?= $etkin ? 'Etkin' : 'Pasif' ?>
                </span>

                <?php if ($superMi && !$benMi): ?>
                    <form method="post">
                        <?= Guvenlik::alan() ?>
                        <input type="hidden" name="durum_id" value="<?= $id ?>">
                        <input type="hidden" name="durum" value="<?= $etkin ? 0 : 1 ?>">
                        <button type="submit" class="action-btn" title="<?= $etkin ? 'Pasife al' : 'Etkinleştir' ?>">
                            <i class="fas fa-<?= $etkin ? 'user-slash' : 'user-check' ?>"></i>
                        </button>
                    </form>
                    <form method="post"
                        onsubmit="return confirm(<?= htmlspecialchars(json_encode(
                            $y['username'] . ' adlı yönetici hesabı silinecek. Devam edilsin mi?',
                            JSON_UNESCAPED_UNICODE
                        ), ENT_QUOTES) ?>);">
                        <?= Guvenlik::alan() ?>
                        <input type="hidden" name="delete" value="<?= $id ?>">
                        <button type="submit" class="action-btn" title="Sil">
                            <i class="fas fa-trash-can"></i>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
