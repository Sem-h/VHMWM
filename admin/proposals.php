<?php
/**
 * VHM - Teklifler
 *
 * Önceki sürümdeki sorunlar:
 *   - Oturum yoksa login.php'ye yönlendiriyordu; o dosya yok, giriş
 *     sayfası index.php. Yani oturumsuz erişimde 404 alınıyordu.
 *   - proposals tablosu yoksa setup-proposals.php'ye yönlendiriyordu;
 *     o dosya güvenlik açığı olduğu için kaldırılmıştı.
 *   - Silmede önce proposals, sonra proposal_items siliniyordu ve
 *     proposal_items'ta yabancı anahtar yok; sıra ters olduğu için
 *     kalemler yetim kalabiliyordu.
 *   - Silme sonrası mesaj ?msg=deleted ile adres çubuğunda kalıyordu.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    /* Giriş sayfası index.php; login.php diye bir dosya yok */
    header('Location: index.php');
    exit;
}

$pageTitle = 'Teklifler';
$currentPage = 'proposals';

/* proposals.status enum'u ile birebir aynı */
$durumlar = [
    'Draft' => ['Taslak', 'badge', 'fa-pen-ruler'],
    'Sent' => ['Gönderildi', 'badge-info', 'fa-paper-plane'],
    'Accepted' => ['Kabul edildi', 'badge-success', 'fa-circle-check'],
    'Rejected' => ['Reddedildi', 'badge-danger', 'fa-circle-xmark'],
    'Expired' => ['Süresi doldu', 'badge-warning', 'fa-hourglass-end'],
];

/* Tablo yoksa sayfa çökmesin; kurulum betiğine yönlendirmek yerine
   durumu açıkça söyle. */
$tabloVar = true;
try {
    Database::fetchColumn("SELECT 1 FROM proposals LIMIT 1");
} catch (Throwable $e) {
    $tabloVar = false;
    error_log('Teklif tablosu okunamadı: ' . $e->getMessage());
}

function teklifMesaj(string $tip, string $metin): void
{
    $_SESSION['tkl_mesaj'] = ['tip' => $tip, 'metin' => $metin];
    header('Location: proposals.php');
    exit;
}

/* ---------- Silme ---------- */
if ($tabloVar && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && is_numeric($_POST['delete'])) {
    Guvenlik::zorunlu();
    $id = (int) $_POST['delete'];
    $db = Database::getInstance();

    try {
        $db->beginTransaction();
        /* proposal_items'ta yabancı anahtar yok; önce kalemler silinmeli */
        Database::query("DELETE FROM proposal_items WHERE proposal_id = ?", [$id]);
        Database::query("DELETE FROM proposals WHERE id = ?", [$id]);
        $db->commit();
        teklifMesaj('success', 'Teklif ve kalemleri silindi.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Teklif silinemedi: ' . $e->getMessage());
        teklifMesaj('error', 'Teklif silinemedi.');
    }
}

/* ---------- Durum değiştirme ---------- */
if ($tabloVar && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['durum_id'])) {
    Guvenlik::zorunlu();
    $id = (int) $_POST['durum_id'];
    $yeni = (string) ($_POST['durum'] ?? '');

    if (!isset($durumlar[$yeni])) {
        teklifMesaj('error', 'Geçersiz teklif durumu.');
    }

    try {
        Database::update('proposals', ['status' => $yeni], 'id = ?', [$id]);
        teklifMesaj('success', 'Teklif durumu "' . $durumlar[$yeni][0] . '" olarak güncellendi.');
    } catch (Throwable $e) {
        error_log('Teklif durumu güncellenemedi: ' . $e->getMessage());
        teklifMesaj('error', 'Durum güncellenemedi.');
    }
}

$mesaj = null;
if (!empty($_SESSION['tkl_mesaj'])) {
    $mesaj = $_SESSION['tkl_mesaj'];
    unset($_SESSION['tkl_mesaj']);
}

/* ---------- Veriler ---------- */
$suzgec = (string) ($_GET['durum'] ?? '');
$arama = trim((string) ($_GET['q'] ?? ''));

$teklifler = [];
$toplam = 0;
$sayfaSayisi = 1;
$sayfa = max(1, (int) ($_GET['page'] ?? 1));
$adet = 20;

$ozet = [
    ['Tüm teklifler', 0, 'fa-file-signature', ''],
    ['Gönderildi', 0, 'fa-paper-plane', 'Sent'],
    ['Kabul edildi', 0, 'fa-circle-check', 'Accepted'],
    ['Bekleyen tutar', 0.0, 'fa-turkish-lira-sign', ''],
];

if ($tabloVar) {
    $kosul = [];
    $par = [];
    if (isset($durumlar[$suzgec])) {
        $kosul[] = "p.status = ?";
        $par[] = $suzgec;
    }
    if ($arama !== '') {
        $kosul[] = "(p.subject LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)";
        $desen = '%' . str_replace(['%', '_'], ['\%', '\_'], $arama) . '%';
        $par = array_merge($par, array_fill(0, 4, $desen));
    }
    $nerede = $kosul ? ' WHERE ' . implode(' AND ', $kosul) : '';
    $atla = ($sayfa - 1) * $adet;

    $guvenli = static function (string $sql, array $p = []): float {
        try {
            return (float) Database::fetchColumn($sql, $p);
        } catch (Throwable $e) {
            error_log('Teklif sorgusu: ' . $e->getMessage());
            return 0;
        }
    };

    $toplam = (int) $guvenli(
        "SELECT COUNT(*) FROM proposals p LEFT JOIN clients c ON c.id = p.client_id" . $nerede,
        $par
    );
    $sayfaSayisi = max(1, (int) ceil($toplam / $adet));

    try {
        $teklifler = Database::fetchAll(
            "SELECT p.*, c.first_name, c.last_name, c.email,
                    (SELECT COUNT(*) FROM proposal_items i WHERE i.proposal_id = p.id) AS kalem
               FROM proposals p
               LEFT JOIN clients c ON c.id = p.client_id
               {$nerede}
              ORDER BY p.created_at DESC
              LIMIT {$adet} OFFSET {$atla}",
            $par
        );
    } catch (Throwable $e) {
        error_log('Teklif listesi okunamadı: ' . $e->getMessage());
        $teklifler = [];
    }

    $ozet = [
        ['Tüm teklifler', $guvenli("SELECT COUNT(*) FROM proposals"), 'fa-file-signature', ''],
        ['Gönderildi', $guvenli("SELECT COUNT(*) FROM proposals WHERE status = 'Sent'"), 'fa-paper-plane', 'Sent'],
        ['Kabul edildi', $guvenli("SELECT COUNT(*) FROM proposals WHERE status = 'Accepted'"), 'fa-circle-check', 'Accepted'],
        ['Bekleyen tutar', $guvenli("SELECT COALESCE(SUM(total_amount),0) FROM proposals WHERE status = 'Sent'"), 'fa-turkish-lira-sign', ''],
    ];
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* ==========================================
       Teklifler - tk
       ========================================== */
    .tk-ozet {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }

    .tk-ozet-kart {
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

    .tk-ozet-kart:hover {
        border-color: var(--y-primary);
        text-decoration: none;
    }

    .tk-ozet-kart.secili {
        border-color: var(--y-primary);
        background: var(--y-primary-soft);
    }

    .tk-ozet-ikon {
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

    .tk-ozet-kart b {
        display: block;
        font-size: 20px;
        font-weight: 700;
        letter-spacing: -.02em;
        color: var(--y-metin);
        line-height: 1.2;
    }

    .tk-ozet-kart span {
        font-size: 12.5px;
        color: var(--y-metin-3);
    }

    .tk-arac {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }

    .tk-ara {
        position: relative;
        flex: 1;
        min-width: 220px;
        max-width: 420px;
    }

    .tk-ara i {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 13px;
        color: var(--y-metin-3);
        pointer-events: none;
    }

    .tk-ara input {
        width: 100%;
        padding-left: 36px;
    }

    .tk-sarmal {
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow-x: auto;
    }

    .tk-tablo {
        width: 100%;
        border-collapse: collapse;
        font-size: 13.5px;
        min-width: 860px;
    }

    .tk-tablo th {
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

    .tk-tablo td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--y-cizgi-soft);
        vertical-align: middle;
    }

    .tk-tablo tr:last-child td {
        border-bottom: none;
    }

    .tk-tablo tbody tr:hover td {
        background: var(--y-yuzey-2);
    }

    .tk-konu b {
        display: block;
        font-weight: 600;
        color: var(--y-metin);
    }

    .tk-konu span {
        display: block;
        margin-top: 2px;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .tk-musteri b {
        display: block;
        font-weight: 600;
        color: var(--y-metin);
    }

    .tk-musteri a {
        font-size: 12px;
        color: var(--y-metin-3);
    }

    .tk-musteri a:hover {
        color: var(--y-primary);
    }

    .tk-sag {
        text-align: right;
        white-space: nowrap;
    }

    .tk-tutar {
        font-weight: 700;
        color: var(--y-metin);
    }

    .tk-suresi-gecti {
        display: inline-block;
        margin-left: 5px;
        padding: 1px 7px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--y-warning) 16%, transparent);
        color: var(--y-warning);
        font-size: 11px;
        font-weight: 700;
    }

    .tk-durum-form {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .tk-durum-form select {
        width: auto;
        min-width: 132px;
        padding: 6px 9px;
        font-size: 12.5px;
    }

    .tk-eylem {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
    }

    .tk-eylem form {
        display: inline;
    }
</style>

<div class="page-header">
    <div>
        <h1>Teklifler</h1>
        <p><?= number_format($toplam, 0, ',', '.') ?> kayıt<?= $arama !== '' || $suzgec !== '' ? ' (süzülmüş)' : '' ?></p>
    </div>
    <?php if ($tabloVar && is_file(__DIR__ . '/proposal-create.php')): ?>
        <a href="proposal-create.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Yeni teklif
        </a>
    <?php endif; ?>
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
        <span>
            Teklif tablosu bulunamadı. Kurulum dosyasını çalıştırmanız gerekiyor:
            <code>mysql -u root <?= htmlspecialchars(defined('DB_NAME') ? DB_NAME : 'vhm') ?>
                &lt; install/whmvm-kurulum.sql</code>
        </span>
    </div>
<?php else: ?>

    <div class="tk-ozet">
        <?php foreach ($ozet as $i => [$ad, $deger, $ikon, $filtre]): ?>
            <a class="tk-ozet-kart <?= $filtre !== '' && $suzgec === $filtre ? 'secili' : '' ?>"
                href="proposals.php<?= $filtre !== '' ? '?durum=' . $filtre : '' ?>">
                <span class="tk-ozet-ikon"><i class="fas <?= $ikon ?>"></i></span>
                <span>
                    <b><?= $i === 3
                        ? number_format((float) $deger, 2, ',', '.') . ' ₺'
                        : number_format((float) $deger, 0, ',', '.') ?></b>
                    <span><?= $ad ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <form class="tk-arac" method="get">
        <?php if ($suzgec !== ''): ?>
            <input type="hidden" name="durum" value="<?= htmlspecialchars($suzgec) ?>">
        <?php endif; ?>
        <div class="tk-ara">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" name="q" value="<?= htmlspecialchars($arama) ?>"
                placeholder="Teklif konusu, müşteri adı veya e-posta…">
        </div>
        <div style="display:flex; gap:8px;">
            <button type="submit" class="btn btn-outline"><i class="fas fa-filter"></i> Ara</button>
            <?php if ($arama !== '' || $suzgec !== ''): ?>
                <a href="proposals.php" class="btn btn-outline"><i class="fas fa-xmark"></i> Sıfırla</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="tk-sarmal">
        <?php if (!$teklifler): ?>
            <div class="empty-state">
                <i class="fas fa-file-signature"></i>
                <h3><?= $arama !== '' || $suzgec !== '' ? 'Eşleşen teklif yok' : 'Henüz teklif yok' ?></h3>
                <p><?= $arama !== '' || $suzgec !== ''
                    ? 'Aramayı değiştirin ya da süzgeci sıfırlayın.'
                    : 'Müşterilerinize hazırladığınız teklifler burada listelenir.' ?></p>
                <?php if ($arama === '' && $suzgec === '' && is_file(__DIR__ . '/proposal-create.php')): ?>
                    <a href="proposal-create.php" class="btn btn-primary" style="margin-top:14px;">
                        <i class="fas fa-plus"></i> İlk teklifi hazırla
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table class="tk-tablo">
                <thead>
                    <tr>
                        <th>Teklif</th>
                        <th>Müşteri</th>
                        <th class="tk-sag">Tutar</th>
                        <th>Geçerlilik</th>
                        <th>Durum</th>
                        <th class="tk-sag">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teklifler as $t):
                        $id = (int) $t['id'];
                        $ad = trim((string) ($t['first_name'] ?? '') . ' ' . (string) ($t['last_name'] ?? ''));
                        [$durumAd, $durumSinif] = $durumlar[$t['status']] ?? [(string) $t['status'], 'badge'];
                        $gecti = !empty($t['valid_until'])
                            && strtotime((string) $t['valid_until']) < strtotime(date('Y-m-d'))
                            && !in_array($t['status'], ['Accepted', 'Rejected'], true);
                        ?>
                        <tr>
                            <td class="tk-konu">
                                <b><?= htmlspecialchars((string) $t['subject']) ?></b>
                                <span>#<?= $id ?>
                                    &middot; <?= date('d.m.Y', strtotime((string) $t['created_at'])) ?>
                                    &middot; <?= (int) $t['kalem'] ?> kalem</span>
                            </td>
                            <td class="tk-musteri">
                                <?php if ($ad !== ''): ?>
                                    <b><?= htmlspecialchars($ad) ?></b>
                                    <a href="client-view.php?id=<?= (int) $t['client_id'] ?>" dir="ltr">
                                        <?= htmlspecialchars((string) ($t['email'] ?? '')) ?>
                                    </a>
                                <?php else: ?>
                                    <b style="color:var(--y-metin-3)">Müşteri silinmiş</b>
                                <?php endif; ?>
                            </td>
                            <td class="tk-sag">
                                <span class="tk-tutar">
                                    <?= number_format((float) $t['total_amount'], 2, ',', '.') ?>
                                    <?= ($t['currency'] ?? 'TRY') === 'TRY' ? '₺' : htmlspecialchars((string) $t['currency']) ?>
                                </span>
                            </td>
                            <td style="font-size:12.5px; color:var(--y-metin-3);">
                                <?= !empty($t['valid_until']) ? date('d.m.Y', strtotime((string) $t['valid_until'])) : '—' ?>
                                <?php if ($gecti): ?>
                                    <span class="tk-suresi-gecti">geçti</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" class="tk-durum-form">
                                    <?= Guvenlik::alan() ?>
                                    <input type="hidden" name="durum_id" value="<?= $id ?>">
                                    <select name="durum" onchange="this.form.submit()">
                                        <?php foreach ($durumlar as $kod => [$etiket, , ]): ?>
                                            <option value="<?= $kod ?>" <?= $t['status'] === $kod ? 'selected' : '' ?>>
                                                <?= $etiket ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="badge <?= $durumSinif ?>"><?= $durumAd ?></span>
                                </form>
                            </td>
                            <td class="tk-sag">
                                <div class="tk-eylem">
                                    <?php if (is_file(__DIR__ . '/proposal-view.php')): ?>
                                        <a class="action-btn" href="proposal-view.php?id=<?= $id ?>" title="Görüntüle">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (is_file(__DIR__ . '/proposal-edit.php')): ?>
                                        <a class="action-btn" href="proposal-edit.php?id=<?= $id ?>" title="Düzenle">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                    <?php endif; ?>
                                    <form method="post"
                                        onsubmit="return confirm(<?= htmlspecialchars(json_encode(
                                            '"' . $t['subject'] . '" teklifi ve ' . (int) $t['kalem']
                                            . ' kalemi silinecek. Devam edilsin mi?',
                                            JSON_UNESCAPED_UNICODE
                                        ), ENT_QUOTES) ?>);">
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
            return 'proposals.php?' . http_build_query($p);
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
