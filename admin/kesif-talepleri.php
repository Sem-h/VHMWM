<?php
/**
 * WHMVM - Admin Keşif Talepleri
 *
 * bursa-hotspot-hizmeti.php üzerindeki "Keşif Talep Et" formundan gelen
 * kayıtları listeler ve durumlarını günceller.
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

$pageTitle = 'Keşif Talepleri';
$currentPage = 'kesif-talepleri';

$mesaj = '';
$mesajTipi = '';

$durumlar = [
    'yeni' => 'Yeni',
    'arandi' => 'Arandı',
    'kesif_yapildi' => 'Keşif yapıldı',
    'teklif_verildi' => 'Teklif verildi',
    'kapandi' => 'Kapandı',
];

/* Durum güncelleme */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $durum = (string) ($_POST['durum'] ?? '');

    if ($id > 0 && isset($durumlar[$durum])) {
        Database::update('kesif_talepleri', ['durum' => $durum], 'id = ?', [$id]);
        $mesaj = '#' . $id . ' numaralı talebin durumu "' . $durumlar[$durum] . '" olarak güncellendi.';
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

$toplam = (int) Database::fetchColumn("SELECT COUNT(*) FROM kesif_talepleri" . $kosul, $parametre);
$sayfaSayisi = max(1, (int) ceil($toplam / $adet));

$talepler = Database::fetchAll(
    "SELECT * FROM kesif_talepleri" . $kosul . " ORDER BY created_at DESC LIMIT {$adet} OFFSET {$atla}",
    $parametre
);

$sayac = [];
foreach (Database::fetchAll("SELECT durum, COUNT(*) AS adet FROM kesif_talepleri GROUP BY durum") as $r) {
    $sayac[$r['durum']] = (int) $r['adet'];
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .kt-ozet {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 18px;
    }

    .kt-ozet a {
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

    .kt-ozet a.is-active {
        border-color: #2474f5;
        background: #eff5ff;
        color: #1d4ed8;
    }

    .kt-ozet a b {
        font-weight: 800;
    }

    .kt-tablo {
        width: 100%;
        border-collapse: collapse;
        background: var(--y-yuzey);
        border: 1px solid var(--y-cizgi);
        border-radius: 10px;
        overflow: hidden;
        font-size: 13px;
    }

    .kt-tablo th,
    .kt-tablo td {
        padding: 12px 14px;
        text-align: left;
        border-bottom: 1px solid var(--y-cizgi-soft);
        vertical-align: top;
    }

    .kt-tablo th {
        background: var(--y-yuzey-2);
        font-size: 11px;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--y-metin-3);
    }

    .kt-tablo tr:last-child td {
        border-bottom: 0;
    }

    .kt-adres {
        color: var(--y-metin-3);
        line-height: 1.5;
    }

    .kt-rozet {
        display: inline-block;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
    }

    .kt-rozet.yeni {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .kt-rozet.arandi {
        background: #fef3c7;
        color: #b45309;
    }

    .kt-rozet.kesif_yapildi {
        background: #e0e7ff;
        color: #4338ca;
    }

    .kt-rozet.teklif_verildi {
        background: #dcfce7;
        color: #15803d;
    }

    .kt-rozet.kapandi {
        background: var(--y-yuzey-2);
        color: var(--y-metin-3);
    }

    .kt-tablo select {
        padding: 6px 8px;
        border: 1px solid var(--y-cizgi);
        border-radius: 6px;
        font-size: 12px;
    }

    .kt-bos {
        padding: 40px;
        text-align: center;
        color: var(--y-metin-3);
        background: var(--y-yuzey);
        border: 1px solid var(--y-cizgi);
        border-radius: 10px;
    }

    .kt-sayfalar {
        display: flex;
        gap: 6px;
        margin-top: 16px;
    }

    .kt-sayfalar a,
    .kt-sayfalar span {
        padding: 6px 11px;
        border: 1px solid var(--y-cizgi);
        border-radius: 6px;
        background: var(--y-yuzey);
        color: var(--y-metin-2);
        font-size: 13px;
        text-decoration: none;
    }

    .kt-sayfalar span {
        background: #2474f5;
        border-color: #2474f5;
        color: #fff;
    }
</style>

<div class="page-header">
    <h1>Keşif Talepleri</h1>
    <p>Hotspot ve internet sayfasındaki keşif formundan gelen talepler.</p>
</div>

<?php if ($mesaj !== ''): ?>
    <div class="alert alert-<?= htmlspecialchars($mesajTipi) ?>">
        <?= htmlspecialchars($mesaj) ?>
    </div>
<?php endif; ?>

<div class="kt-ozet">
    <a href="kesif-talepleri.php" class="<?= $filtre === '' ? 'is-active' : '' ?>">
        Tümü <b><?= array_sum($sayac) ?></b>
    </a>
    <?php foreach ($durumlar as $kod => $etiket): ?>
        <a href="?durum=<?= urlencode($kod) ?>" class="<?= $filtre === $kod ? 'is-active' : '' ?>">
            <?= htmlspecialchars($etiket) ?> <b><?= $sayac[$kod] ?? 0 ?></b>
        </a>
    <?php endforeach; ?>
</div>

<?php if (empty($talepler)): ?>
    <div class="kt-bos">Bu filtreye uyan talep yok.</div>
<?php else: ?>
    <table class="kt-tablo">
        <thead>
            <tr>
                <th>#</th>
                <th>Tarih</th>
                <th>İletişim</th>
                <th>Adres</th>
                <th>Paket / İşletme</th>
                <th>Not</th>
                <th>Durum</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($talepler as $t): ?>
                <tr>
                    <td><?= (int) $t['id'] ?></td>
                    <td><?= date('d.m.Y H:i', strtotime((string) $t['created_at'])) ?></td>
                    <td>
                        <strong><?= htmlspecialchars((string) $t['ad_soyad']) ?></strong><br>
                        <a href="tel:<?= htmlspecialchars(preg_replace('/\D+/', '', (string) $t['telefon']) ?? '') ?>"
                            dir="ltr"><?= htmlspecialchars((string) $t['telefon']) ?></a>
                        <?php if (!empty($t['email'])): ?>
                            <br><a href="mailto:<?= htmlspecialchars((string) $t['email']) ?>"
                                dir="ltr"><?= htmlspecialchars((string) $t['email']) ?></a>
                        <?php endif; ?>
                    </td>
                    <td class="kt-adres">
                        <?= htmlspecialchars((string) $t['mahalle']) ?> Mah.<br>
                        <?php if (!empty($t['sokak'])): ?>
                            <?= htmlspecialchars((string) $t['sokak']) ?>
                            <?= !empty($t['bina_no']) ? ' No: ' . htmlspecialchars((string) $t['bina_no']) : '' ?><br>
                        <?php endif; ?>
                        <?= htmlspecialchars((string) $t['ilce']) ?> / <?= htmlspecialchars((string) $t['il']) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars((string) ($t['paket_adi'] ?? '—')) ?>
                        <?php if (!empty($t['isletme_turu'])): ?>
                            <br><span class="kt-adres"><?= htmlspecialchars((string) $t['isletme_turu']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="kt-adres"><?= nl2br(htmlspecialchars((string) ($t['aciklama'] ?? ''))) ?></td>
                    <td>
                        <form method="post" style="display: flex; gap: 6px; align-items: center;">
                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                            <select name="durum" onchange="this.form.submit()">
                                <?php foreach ($durumlar as $kod => $etiket): ?>
                                    <option value="<?= htmlspecialchars($kod) ?>"
                                        <?= $t['durum'] === $kod ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($etiket) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <span class="kt-rozet <?= htmlspecialchars((string) $t['durum']) ?>">
                            <?= htmlspecialchars($durumlar[$t['durum']] ?? (string) $t['durum']) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($sayfaSayisi > 1): ?>
        <div class="kt-sayfalar">
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
