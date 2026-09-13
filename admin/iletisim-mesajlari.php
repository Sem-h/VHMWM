<?php
/**
 * VHM - Admin İletişim Mesajları
 *
 * iletisim.php üzerindeki iletişim formundan gelen mesajları listeler ve
 * durumlarını günceller. Keşif talepleri sayfasıyla aynı akışı kullanır.
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

$pageTitle = 'İletişim Mesajları';
$currentPage = 'iletisim-mesajlari';

$mesaj = '';
$mesajTipi = '';

$durumlar = [
    'yeni' => 'Yeni',
    'okundu' => 'Okundu',
    'yanitlandi' => 'Yanıtlandı',
    'kapandi' => 'Kapandı',
];

/* Durum güncelleme */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $durum = (string) ($_POST['durum'] ?? '');

    if ($id > 0 && isset($durumlar[$durum])) {
        Database::update('iletisim_mesajlari', ['durum' => $durum], 'id = ?', [$id]);
        $mesaj = '#' . $id . ' numaralı mesajın durumu "' . $durumlar[$durum] . '" olarak güncellendi.';
        $mesajTipi = 'success';
    } else {
        $mesaj = 'Geçersiz istek.';
        $mesajTipi = 'error';
    }
}

/* Filtre ve sayfalama */
$filtre = (string) ($_GET['durum'] ?? '');
$sayfa = max(1, (int) ($_GET['sayfa'] ?? 1));
$adet = 25;
$atla = ($sayfa - 1) * $adet;

$kosul = '';
$parametre = [];
if (isset($durumlar[$filtre])) {
    $kosul = ' WHERE durum = ?';
    $parametre[] = $filtre;
}

$toplam = (int) Database::fetchColumn("SELECT COUNT(*) FROM iletisim_mesajlari" . $kosul, $parametre);
$sayfaSayisi = max(1, (int) ceil($toplam / $adet));

$mesajlar = Database::fetchAll(
    "SELECT * FROM iletisim_mesajlari" . $kosul . " ORDER BY created_at DESC LIMIT {$adet} OFFSET {$atla}",
    $parametre
);

$sayac = [];
foreach (Database::fetchAll("SELECT durum, COUNT(*) AS adet FROM iletisim_mesajlari GROUP BY durum") as $r) {
    $sayac[$r['durum']] = (int) $r['adet'];
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .im-ozet {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 18px;
    }

    .im-ozet a {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        border: 1px solid var(--y-cizgi);
        border-radius: 8px;
        background: var(--y-yuzey);
        color: var(--y-metin-2);
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
    }

    .im-ozet a.is-active {
        border-color: #2474f5;
        background: #eff5ff;
        color: #1d4ed8;
    }

    .im-ozet a b {
        font-weight: 800;
    }

    .im-tablo {
        width: 100%;
        border-collapse: collapse;
        background: var(--y-yuzey);
        border: 1px solid var(--y-cizgi);
        border-radius: 10px;
        overflow: hidden;
        font-size: 13px;
    }

    .im-tablo th,
    .im-tablo td {
        padding: 12px 14px;
        text-align: left;
        border-bottom: 1px solid var(--y-cizgi-soft);
        vertical-align: top;
    }

    .im-tablo th {
        background: var(--y-yuzey-2);
        font-size: 11px;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--y-metin-3);
    }

    .im-tablo tr:last-child td {
        border-bottom: 0;
    }

    .im-soluk {
        color: var(--y-metin-3);
        line-height: 1.5;
    }

    /* Uzun mesajlar tabloyu şişirmesin; tıklayınca açılır */
    .im-mesaj {
        max-width: 420px;
    }

    .im-mesaj summary {
        cursor: pointer;
        color: #1d4ed8;
        font-weight: 600;
        list-style: none;
    }

    .im-mesaj summary::-webkit-details-marker {
        display: none;
    }

    .im-mesaj summary::before {
        content: '▸ ';
    }

    .im-mesaj[open] summary::before {
        content: '▾ ';
    }

    .im-mesaj p {
        margin-top: 8px;
        color: var(--y-metin-2);
        line-height: 1.6;
        white-space: pre-wrap;
    }

    .im-rozet {
        display: inline-block;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
    }

    .im-rozet.yeni {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .im-rozet.okundu {
        background: #fef3c7;
        color: #b45309;
    }

    .im-rozet.yanitlandi {
        background: #dcfce7;
        color: #15803d;
    }

    .im-rozet.kapandi {
        background: var(--y-yuzey-2);
        color: var(--y-metin-3);
    }

    .im-tablo select {
        padding: 6px 8px;
        border: 1px solid var(--y-cizgi);
        border-radius: 6px;
        font-size: 12px;
        margin-bottom: 6px;
    }

    .im-bos {
        padding: 40px;
        text-align: center;
        color: var(--y-metin-3);
        background: var(--y-yuzey);
        border: 1px solid var(--y-cizgi);
        border-radius: 10px;
    }

    .im-sayfalar {
        display: flex;
        gap: 6px;
        margin-top: 16px;
    }

    .im-sayfalar a,
    .im-sayfalar span {
        padding: 6px 11px;
        border: 1px solid var(--y-cizgi);
        border-radius: 6px;
        background: var(--y-yuzey);
        color: var(--y-metin-2);
        font-size: 13px;
        text-decoration: none;
    }

    .im-sayfalar span {
        background: #2474f5;
        border-color: #2474f5;
        color: #fff;
    }
</style>

<div class="page-header">
    <h1>İletişim Mesajları</h1>
    <p>Sitedeki iletişim formundan gelen mesajlar.</p>
</div>

<?php if ($mesaj !== ''): ?>
    <div class="alert alert-<?= htmlspecialchars($mesajTipi) ?>">
        <?= htmlspecialchars($mesaj) ?>
    </div>
<?php endif; ?>

<div class="im-ozet">
    <a href="iletisim-mesajlari.php" class="<?= $filtre === '' ? 'is-active' : '' ?>">
        Tümü <b><?= array_sum($sayac) ?></b>
    </a>
    <?php foreach ($durumlar as $kod => $etiket): ?>
        <a href="?durum=<?= urlencode($kod) ?>" class="<?= $filtre === $kod ? 'is-active' : '' ?>">
            <?= htmlspecialchars($etiket) ?> <b><?= $sayac[$kod] ?? 0 ?></b>
        </a>
    <?php endforeach; ?>
</div>

<?php if (empty($mesajlar)): ?>
    <div class="im-bos">Bu filtreye uyan mesaj yok.</div>
<?php else: ?>
    <table class="im-tablo">
        <thead>
            <tr>
                <th>#</th>
                <th>Tarih</th>
                <th>Gönderen</th>
                <th>Konu ve mesaj</th>
                <th>Durum</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($mesajlar as $m): ?>
                <tr>
                    <td><?= (int) $m['id'] ?></td>
                    <td><?= date('d.m.Y H:i', strtotime((string) $m['created_at'])) ?></td>
                    <td>
                        <strong><?= htmlspecialchars((string) $m['ad_soyad']) ?></strong><br>
                        <a href="mailto:<?= htmlspecialchars((string) $m['email']) ?>"
                            dir="ltr"><?= htmlspecialchars((string) $m['email']) ?></a>
                        <?php if (!empty($m['telefon'])): ?>
                            <br><a href="tel:<?= htmlspecialchars(preg_replace('/\D+/', '', (string) $m['telefon']) ?? '') ?>"
                                dir="ltr"><?= htmlspecialchars((string) $m['telefon']) ?></a>
                        <?php endif; ?>
                        <?php if (!empty($m['ip'])): ?>
                            <br><span class="im-soluk"><?= htmlspecialchars((string) $m['ip']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <details class="im-mesaj">
                            <summary><?= htmlspecialchars((string) $m['konu']) ?></summary>
                            <p><?= htmlspecialchars((string) $m['mesaj']) ?></p>
                        </details>
                    </td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                            <select name="durum" onchange="this.form.submit()">
                                <?php foreach ($durumlar as $kod => $etiket): ?>
                                    <option value="<?= htmlspecialchars($kod) ?>"
                                        <?= $m['durum'] === $kod ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($etiket) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <span class="im-rozet <?= htmlspecialchars((string) $m['durum']) ?>">
                            <?= htmlspecialchars($durumlar[$m['durum']] ?? (string) $m['durum']) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($sayfaSayisi > 1): ?>
        <div class="im-sayfalar">
            <?php for ($i = 1; $i <= $sayfaSayisi; $i++):
                $bag = '?sayfa=' . $i . ($filtre !== '' ? '&durum=' . urlencode($filtre) : '');
                ?>
                <?php if ($i === $sayfa): ?>
                    <span><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($bag) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
