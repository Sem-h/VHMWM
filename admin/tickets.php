<?php
/**
 * VHM - Destek talepleri
 *
 * Önceki sürümde yalnızca düz bir liste ve kapatma düğmesi vardı;
 * öncelik, yanıt bekleyen talepler ve bekleme süresi görünmüyordu.
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

$pageTitle = 'Destek talepleri';
$currentPage = 'tickets';

/* tickets.status enum'u ile birebir aynı */
$durumlar = [
    'open' => ['Açık', 'badge-info'],
    'customer_reply' => ['Müşteri yanıtladı', 'badge-warning'],
    'answered' => ['Yanıtlandı', 'badge-success'],
    'in_progress' => ['İşlemde', 'badge-info'],
    'on_hold' => ['Beklemede', 'badge-warning'],
    'closed' => ['Kapalı', 'badge'],
];

$oncelikler = [
    'urgent' => ['Acil', 'var(--y-danger)'],
    'high' => ['Yüksek', 'var(--y-warning)'],
    'medium' => ['Normal', 'var(--y-primary)'],
    'low' => ['Düşük', 'var(--y-metin-3)'],
];

function talepMesaj(string $tip, string $metin): void
{
    $_SESSION['dst_mesaj'] = ['tip' => $tip, 'metin' => $metin];
    header('Location: tickets.php');
    exit;
}

/* ---------- Durum değiştirme ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['durum_id'])) {
    Guvenlik::zorunlu();
    $id = (int) $_POST['durum_id'];
    $yeni = (string) ($_POST['durum'] ?? '');

    if ($id <= 0 || !isset($durumlar[$yeni])) {
        talepMesaj('error', 'Geçersiz talep durumu.');
    }

    try {
        Database::query(
            "UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?",
            [$yeni, $id]
        );
        talepMesaj('success', 'Talep durumu "' . $durumlar[$yeni][0] . '" olarak güncellendi.');
    } catch (Throwable $e) {
        error_log('Talep durumu güncellenemedi: ' . $e->getMessage());
        talepMesaj('error', 'Durum güncellenemedi.');
    }
}

$mesaj = null;
if (!empty($_SESSION['dst_mesaj'])) {
    $mesaj = $_SESSION['dst_mesaj'];
    unset($_SESSION['dst_mesaj']);
}

/* ---------- Süzme ---------- */
$suzgec = (string) ($_GET['status'] ?? '');
$oncelik = (string) ($_GET['oncelik'] ?? '');
$arama = trim((string) ($_GET['q'] ?? ''));

$kosul = [];
$par = [];
if (isset($durumlar[$suzgec])) {
    $kosul[] = "t.status = ?";
    $par[] = $suzgec;
} elseif ($suzgec === 'acik') {
    /* "Açık" görünümü: kapalı olmayan her şey */
    $kosul[] = "t.status <> 'closed'";
}
if (isset($oncelikler[$oncelik])) {
    $kosul[] = "t.priority = ?";
    $par[] = $oncelik;
}
if ($arama !== '') {
    $kosul[] = "(t.ticket_number LIKE ? OR t.subject LIKE ? OR c.first_name LIKE ?"
        . " OR c.last_name LIKE ? OR c.email LIKE ?)";
    $desen = '%' . str_replace(['%', '_'], ['\%', '\_'], $arama) . '%';
    $par = array_merge($par, array_fill(0, 5, $desen));
}
$nerede = $kosul ? ' WHERE ' . implode(' AND ', $kosul) : '';

$sayfa = max(1, (int) ($_GET['page'] ?? 1));
$adet = 20;
$atla = ($sayfa - 1) * $adet;

$guvenli = static function (string $sql, array $p = []): float {
    try {
        return (float) Database::fetchColumn($sql, $p);
    } catch (Throwable $e) {
        error_log('Talep sorgusu: ' . $e->getMessage());
        return 0;
    }
};

$toplam = (int) $guvenli(
    "SELECT COUNT(*) FROM tickets t LEFT JOIN clients c ON c.id = t.client_id" . $nerede,
    $par
);
$sayfaSayisi = max(1, (int) ceil($toplam / $adet));

try {
    $talepler = Database::fetchAll(
        "SELECT t.*, c.first_name, c.last_name, c.email,
                (SELECT COUNT(*) FROM ticket_replies r WHERE r.ticket_id = t.id) AS yanit
           FROM tickets t
           LEFT JOIN clients c ON c.id = t.client_id
           {$nerede}
          ORDER BY
            FIELD(t.priority, 'urgent', 'high', 'medium', 'low'),
            t.status = 'closed',
            t.updated_at DESC
          LIMIT {$adet} OFFSET {$atla}",
        $par
    );
} catch (Throwable $e) {
    error_log('Talep listesi okunamadı: ' . $e->getMessage());
    $talepler = [];
}

$ozet = [
    ['Açık talep', $guvenli("SELECT COUNT(*) FROM tickets WHERE status <> 'closed'"), 'fa-envelope-open-text', 'acik'],
    ['Müşteri yanıtladı', $guvenli("SELECT COUNT(*) FROM tickets WHERE status = 'customer_reply'"), 'fa-reply', 'customer_reply'],
    ['Acil', $guvenli("SELECT COUNT(*) FROM tickets WHERE priority = 'urgent' AND status <> 'closed'"), 'fa-fire', ''],
    ['Bugün kapanan', $guvenli("SELECT COUNT(*) FROM tickets WHERE status = 'closed' AND DATE(updated_at) = CURDATE()"), 'fa-circle-check', ''],
];

/** Geçen süreyi okunur yazar */
function gecenSure(string $tarih): string
{
    $fark = time() - strtotime($tarih);
    if ($fark < 60) {
        return 'az önce';
    }
    if ($fark < 3600) {
        return (int) ($fark / 60) . ' dk önce';
    }
    if ($fark < 86400) {
        return (int) ($fark / 3600) . ' saat önce';
    }
    if ($fark < 2592000) {
        return (int) ($fark / 86400) . ' gün önce';
    }
    return date('d.m.Y', strtotime($tarih));
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* ==========================================
       Destek talepleri - ds
       ========================================== */
    .ds-ozet {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }

    .ds-ozet-kart {
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

    .ds-ozet-kart:hover {
        border-color: var(--y-primary);
        text-decoration: none;
    }

    .ds-ozet-kart.secili {
        border-color: var(--y-primary);
        background: var(--y-primary-soft);
    }

    .ds-ozet-ikon {
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

    .ds-ozet-kart b {
        display: block;
        font-size: 21px;
        font-weight: 700;
        letter-spacing: -.02em;
        color: var(--y-metin);
        line-height: 1.2;
    }

    .ds-ozet-kart span {
        font-size: 12.5px;
        color: var(--y-metin-3);
    }

    .ds-arac {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 14px;
    }

    .ds-ara {
        position: relative;
        flex: 1;
        min-width: 220px;
        max-width: 420px;
    }

    .ds-ara i {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 13px;
        color: var(--y-metin-3);
        pointer-events: none;
    }

    .ds-ara input {
        width: 100%;
        padding-left: 36px;
    }

    .ds-suzgec {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 16px;
    }

    .ds-suzgec a {
        padding: 7px 13px;
        border: 1px solid var(--y-cizgi);
        border-radius: 999px;
        background: var(--y-yuzey);
        font-size: 12.5px;
        font-weight: 600;
        color: var(--y-metin-2);
    }

    .ds-suzgec a:hover {
        border-color: var(--y-primary);
        color: var(--y-primary);
        text-decoration: none;
    }

    .ds-suzgec a.secili {
        border-color: var(--y-primary);
        background: var(--y-primary-soft);
        color: var(--y-primary);
    }

    .ds-sarmal {
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .ds-satir {
        display: grid;
        grid-template-columns: 4px minmax(0, 1fr) auto auto auto;
        align-items: center;
        gap: 14px;
        padding: 14px 18px 14px 0;
        border-bottom: 1px solid var(--y-cizgi-soft);
    }

    .ds-satir:last-child {
        border-bottom: none;
    }

    .ds-satir:hover {
        background: var(--y-yuzey-2);
    }

    .ds-oncelik {
        align-self: stretch;
        border-radius: 0 3px 3px 0;
    }

    .ds-konu b {
        display: block;
        font-size: 14px;
        font-weight: 600;
        line-height: 1.35;
        color: var(--y-metin);
    }

    .ds-konu b a {
        color: inherit;
    }

    .ds-konu b a:hover {
        color: var(--y-primary);
    }

    .ds-konu span {
        display: block;
        margin-top: 3px;
        font-size: 12px;
        color: var(--y-metin-3);
    }

    .ds-oncelik-etiket {
        display: inline-block;
        padding: 1px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
    }

    .ds-sure {
        font-size: 12px;
        color: var(--y-metin-3);
        white-space: nowrap;
        text-align: right;
    }

    .ds-durum-form {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .ds-durum-form select {
        width: auto;
        min-width: 142px;
        padding: 6px 9px;
        font-size: 12.5px;
    }

    @media (max-width: 860px) {
        .ds-satir {
            grid-template-columns: 4px minmax(0, 1fr) auto;
        }

        .ds-sure,
        .ds-gizle {
            display: none;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>Destek talepleri</h1>
        <p><?= number_format($toplam, 0, ',', '.') ?> kayıt<?= $arama !== '' || $suzgec !== '' || $oncelik !== '' ? ' (süzülmüş)' : '' ?></p>
    </div>
</div>

<?php if ($mesaj): ?>
    <div class="alert alert-<?= $mesaj['tip'] === 'success' ? 'success' : 'error' ?>">
        <i class="fas fa-<?= $mesaj['tip'] === 'success' ? 'circle-check' : 'circle-exclamation' ?> alert-icon"></i>
        <span><?= htmlspecialchars((string) $mesaj['metin']) ?></span>
    </div>
<?php endif; ?>

<div class="ds-ozet">
    <?php foreach ($ozet as [$ad, $deger, $ikon, $filtre]): ?>
        <a class="ds-ozet-kart <?= $filtre !== '' && $suzgec === $filtre ? 'secili' : '' ?>"
            href="tickets.php<?= $filtre !== '' ? '?status=' . $filtre : '' ?>">
            <span class="ds-ozet-ikon"><i class="fas <?= $ikon ?>"></i></span>
            <span>
                <b><?= number_format($deger, 0, ',', '.') ?></b>
                <span><?= $ad ?></span>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<form class="ds-arac" method="get">
    <?php if ($suzgec !== ''): ?>
        <input type="hidden" name="status" value="<?= htmlspecialchars($suzgec) ?>">
    <?php endif; ?>
    <div class="ds-ara">
        <i class="fas fa-magnifying-glass"></i>
        <input type="search" name="q" value="<?= htmlspecialchars($arama) ?>"
            placeholder="Talep numarası, konu veya müşteri…">
    </div>
    <div style="display:flex; gap:8px;">
        <button type="submit" class="btn btn-outline"><i class="fas fa-filter"></i> Ara</button>
        <?php if ($arama !== '' || $suzgec !== '' || $oncelik !== ''): ?>
            <a href="tickets.php" class="btn btn-outline"><i class="fas fa-xmark"></i> Sıfırla</a>
        <?php endif; ?>
    </div>
</form>

<div class="ds-suzgec">
    <a href="tickets.php" class="<?= $suzgec === '' && $oncelik === '' ? 'secili' : '' ?>">Tümü</a>
    <a href="?status=acik" class="<?= $suzgec === 'acik' ? 'secili' : '' ?>">Kapalı olmayan</a>
    <?php foreach ($durumlar as $kod => [$ad, ]): ?>
        <a href="?status=<?= $kod ?>" class="<?= $suzgec === $kod ? 'secili' : '' ?>"><?= $ad ?></a>
    <?php endforeach; ?>
    <?php foreach ($oncelikler as $kod => [$ad, ]): ?>
        <a href="?oncelik=<?= $kod ?>" class="<?= $oncelik === $kod ? 'secili' : '' ?>">Öncelik: <?= $ad ?></a>
    <?php endforeach; ?>
</div>

<div class="ds-sarmal">
    <?php if (!$talepler): ?>
        <div class="empty-state">
            <i class="fas fa-life-ring"></i>
            <h3><?= $arama !== '' || $suzgec !== '' || $oncelik !== '' ? 'Eşleşen talep yok' : 'Açık destek talebi yok' ?></h3>
            <p><?= $arama !== '' || $suzgec !== '' || $oncelik !== ''
                ? 'Aramayı değiştirin ya da süzgeci sıfırlayın.'
                : 'Müşteriler panelden talep açtığında burada listelenir.' ?></p>
        </div>
    <?php else: ?>
        <?php foreach ($talepler as $t):
            $id = (int) $t['id'];
            $ad = trim((string) ($t['first_name'] ?? '') . ' ' . (string) ($t['last_name'] ?? ''));
            if ($ad === '') {
                $ad = (string) ($t['name'] ?? 'Misafir');
            }
            [$durumAd, $durumSinif] = $durumlar[$t['status']] ?? [(string) $t['status'], 'badge'];
            [$oncelikAd, $oncelikRenk] = $oncelikler[$t['priority']] ?? ['Normal', 'var(--y-metin-3)'];
            $kapali = $t['status'] === 'closed';
            ?>
            <div class="ds-satir">
                <span class="ds-oncelik" style="background: <?= $kapali ? 'transparent' : $oncelikRenk ?>"></span>

                <div class="ds-konu">
                    <b>
                        <?php if (is_file(__DIR__ . '/ticket-view.php')): ?>
                            <a href="ticket-view.php?id=<?= $id ?>"><?= htmlspecialchars((string) $t['subject']) ?></a>
                        <?php else: ?>
                            <?= htmlspecialchars((string) $t['subject']) ?>
                        <?php endif; ?>
                    </b>
                    <span>
                        #<?= htmlspecialchars((string) ($t['ticket_number'] ?: $id)) ?>
                        &middot; <?= htmlspecialchars($ad) ?>
                        &middot; <?= (int) $t['yanit'] ?> yanıt
                    </span>
                </div>

                <span class="ds-oncelik-etiket ds-gizle"
                    style="background: color-mix(in srgb, <?= $oncelikRenk ?> 14%, transparent); color: <?= $oncelikRenk ?>">
                    <?= $oncelikAd ?>
                </span>

                <span class="ds-sure"><?= gecenSure((string) $t['updated_at']) ?></span>

                <form method="post" class="ds-durum-form">
                    <?= Guvenlik::alan() ?>
                    <input type="hidden" name="durum_id" value="<?= $id ?>">
                    <select name="durum" onchange="this.form.submit()">
                        <?php foreach ($durumlar as $kod => [$etiket, ]): ?>
                            <option value="<?= $kod ?>" <?= $t['status'] === $kod ? 'selected' : '' ?>>
                                <?= $etiket ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="badge <?= $durumSinif ?>"><?= $durumAd ?></span>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($sayfaSayisi > 1): ?>
    <?php
    $bag = static function (int $s) use ($arama, $suzgec, $oncelik): string {
        $p = ['page' => $s];
        if ($arama !== '') {
            $p['q'] = $arama;
        }
        if ($suzgec !== '') {
            $p['status'] = $suzgec;
        }
        if ($oncelik !== '') {
            $p['oncelik'] = $oncelik;
        }
        return 'tickets.php?' . http_build_query($p);
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
