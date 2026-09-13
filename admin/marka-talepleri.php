<?php
/**
 * VHM - Admin Marka Tescil
 *
 * marka-tescil.php üzerindeki ücretsiz araştırma formundan gelen talepleri
 * listeler. Ayrıca sayfada gösterilen hizmet bedeli ve Türk Patent harçları
 * buradan güncellenir; tutarlar sayfaya sabit yazılmaz.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';

require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Marka Tescil Talepleri';
$currentPage = 'marka-talepleri';

$mesaj = '';
$mesajTipi = '';

$durumlar = [
    'yeni' => 'Yeni',
    'arastiriliyor' => 'Araştırılıyor',
    'teklif_verildi' => 'Teklif verildi',
    'basvuruldu' => 'Başvuruldu',
    'kapandi' => 'Kapandı',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* Ücret güncelleme */
    if (isset($_POST['ucretler']) && is_array($_POST['ucretler'])) {
        $sayi = 0;
        foreach ($_POST['ucretler'] as $id => $tutar) {
            $id = (int) $id;
            $tutar = (float) str_replace([',', ' '], ['.', ''], (string) $tutar);
            if ($id > 0 && $tutar >= 0) {
                Database::update('marka_ucretleri', ['tutar' => $tutar], 'id = ?', [$id]);
                $sayi++;
            }
        }
        $yil = trim((string) ($_POST['gecerlilik'] ?? ''));
        Settings::set('marka_harc_gecerlilik', mb_substr($yil, 0, 20));

        $mesaj = $sayi . ' ücret satırı güncellendi.';
        $mesajTipi = 'success';
    } else {
        /* Durum güncelleme */
        $id = (int) ($_POST['id'] ?? 0);
        $durum = (string) ($_POST['durum'] ?? '');

        if ($id > 0 && isset($durumlar[$durum])) {
            Database::update('marka_tescil_talepleri', ['durum' => $durum], 'id = ?', [$id]);
            $mesaj = '#' . $id . ' numaralı talebin durumu "' . $durumlar[$durum] . '" olarak güncellendi.';
            $mesajTipi = 'success';
        } else {
            $mesaj = 'Geçersiz istek.';
            $mesajTipi = 'error';
        }
    }
}

/* Ücretler */
$ucretler = Database::fetchAll("SELECT * FROM marka_ucretleri ORDER BY sort_order");
$gecerlilik = (string) Settings::get('marka_harc_gecerlilik', '');

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

$toplam = (int) Database::fetchColumn("SELECT COUNT(*) FROM marka_tescil_talepleri" . $kosul, $parametre);
$sayfaSayisi = max(1, (int) ceil($toplam / $adet));

$talepler = Database::fetchAll(
    "SELECT * FROM marka_tescil_talepleri" . $kosul . " ORDER BY created_at DESC LIMIT {$adet} OFFSET {$atla}",
    $parametre
);

$sayac = [];
foreach (Database::fetchAll("SELECT durum, COUNT(*) AS adet FROM marka_tescil_talepleri GROUP BY durum") as $r) {
    $sayac[$r['durum']] = (int) $r['adet'];
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .mk-ucret {
        margin-bottom: 22px;
        border: 1px solid var(--y-cizgi);
        border-radius: 10px;
        background: var(--y-yuzey);
        overflow: hidden;
    }

    .mk-ucret summary {
        padding: 13px 16px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 700;
        color: var(--y-metin-2);
        background: var(--y-yuzey-2);
        list-style: none;
    }

    .mk-ucret summary::-webkit-details-marker {
        display: none;
    }

    .mk-ucret summary::before {
        content: '▸ ';
    }

    .mk-ucret[open] summary::before {
        content: '▾ ';
    }

    .mk-ucret-govde {
        padding: 16px;
        border-top: 1px solid var(--y-cizgi);
    }

    .mk-ucret-satir {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 170px;
        gap: 14px;
        align-items: center;
        padding: 9px 0;
        border-bottom: 1px solid var(--y-cizgi-soft);
    }

    .mk-ucret-satir:last-of-type {
        border-bottom: 0;
    }

    .mk-ucret-satir b {
        display: block;
        font-size: 13px;
        color: var(--y-metin);
    }

    .mk-ucret-satir span {
        font-size: 12px;
        color: var(--y-metin-3);
    }

    .mk-ucret-satir em {
        font-style: normal;
        font-size: 11px;
        font-weight: 700;
        color: #1d4ed8;
    }

    .mk-ucret input {
        width: 100%;
        padding: 8px 11px;
        border: 1px solid var(--y-cizgi);
        border-radius: 6px;
        font-size: 13px;
        text-align: right;
    }

    .mk-ucret-alt {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 14px;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--y-cizgi);
    }

    .mk-ucret-alt label {
        display: block;
        margin-bottom: 5px;
        font-size: 12px;
        font-weight: 600;
        color: var(--y-metin-2);
    }

    .mk-ucret-alt input {
        width: 150px;
        text-align: left;
    }

    .mk-ozet {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 18px;
    }

    .mk-ozet a {
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

    .mk-ozet a.is-active {
        border-color: #2474f5;
        background: #eff5ff;
        color: #1d4ed8;
    }

    .mk-ozet a b {
        font-weight: 800;
    }

    .mk-tablo {
        width: 100%;
        border-collapse: collapse;
        background: var(--y-yuzey);
        border: 1px solid var(--y-cizgi);
        border-radius: 10px;
        overflow: hidden;
        font-size: 13px;
    }

    .mk-tablo th,
    .mk-tablo td {
        padding: 12px 14px;
        text-align: left;
        border-bottom: 1px solid var(--y-cizgi-soft);
        vertical-align: top;
    }

    .mk-tablo th {
        background: var(--y-yuzey-2);
        font-size: 11px;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--y-metin-3);
    }

    .mk-tablo tr:last-child td {
        border-bottom: 0;
    }

    .mk-soluk {
        color: var(--y-metin-3);
        line-height: 1.5;
    }

    .mk-sinif {
        display: inline-block;
        margin: 2px 3px 2px 0;
        padding: 2px 8px;
        border-radius: 999px;
        background: #eff5ff;
        color: #1d4ed8;
        font-size: 11px;
        font-weight: 700;
    }

    .mk-rozet {
        display: inline-block;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
    }

    .mk-rozet.yeni {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .mk-rozet.arastiriliyor {
        background: #fef3c7;
        color: #b45309;
    }

    .mk-rozet.teklif_verildi {
        background: #e0e7ff;
        color: #4338ca;
    }

    .mk-rozet.basvuruldu {
        background: #dcfce7;
        color: #15803d;
    }

    .mk-rozet.kapandi {
        background: var(--y-yuzey-2);
        color: var(--y-metin-3);
    }

    .mk-tablo select {
        padding: 6px 8px;
        border: 1px solid var(--y-cizgi);
        border-radius: 6px;
        font-size: 12px;
        margin-bottom: 6px;
    }

    .mk-bos {
        padding: 40px;
        text-align: center;
        color: var(--y-metin-3);
        background: var(--y-yuzey);
        border: 1px solid var(--y-cizgi);
        border-radius: 10px;
    }

    .mk-sayfalar {
        display: flex;
        gap: 6px;
        margin-top: 16px;
    }

    .mk-sayfalar a,
    .mk-sayfalar span {
        padding: 6px 11px;
        border: 1px solid var(--y-cizgi);
        border-radius: 6px;
        background: var(--y-yuzey);
        color: var(--y-metin-2);
        font-size: 13px;
        text-decoration: none;
    }

    .mk-sayfalar span {
        background: #2474f5;
        border-color: #2474f5;
        color: #fff;
    }
</style>

<div class="page-header">
    <h1>Marka Tescil Talepleri</h1>
    <p>Marka tescil sayfasındaki ücretsiz araştırma formundan gelen talepler.</p>
</div>

<?php if ($mesaj !== ''): ?>
    <div class="alert alert-<?= htmlspecialchars($mesajTipi) ?>">
        <?= htmlspecialchars($mesaj) ?>
    </div>
<?php endif; ?>

<!-- Ücretler: sayfada gösterilen tutarların tek kaynağı -->
<details class="mk-ucret">
    <summary>Hizmet bedeli ve Türk Patent harçları</summary>
    <div class="mk-ucret-govde">
        <form method="post">
            <?php foreach ($ucretler as $u): ?>
                <div class="mk-ucret-satir">
                    <div>
                        <b><?= htmlspecialchars((string) $u['baslik']) ?>
                            <em><?= $u['tur'] === 'harc' ? 'HARÇ' : 'HİZMET' ?></em>
                        </b>
                        <span><?= htmlspecialchars((string) ($u['aciklama'] ?? '')) ?></span>
                    </div>
                    <input type="number" name="ucretler[<?= (int) $u['id'] ?>]" step="1" min="0"
                        value="<?= (int) $u['tutar'] ?>">
                </div>
            <?php endforeach; ?>

            <div class="mk-ucret-alt">
                <div>
                    <label for="gecerlilik">Harç tarifesinin geçerlilik yılı</label>
                    <input type="text" id="gecerlilik" name="gecerlilik" maxlength="20"
                        value="<?= htmlspecialchars($gecerlilik) ?>" placeholder="2026">
                </div>
                <button type="submit" class="btn btn-primary">Ücretleri kaydet</button>
            </div>

            <p class="mk-soluk" style="margin-top: 12px; font-size: 12px;">
                Harç tutarları Türk Patent ve Marka Kurumu tarafından her yıl güncellenir.
                Tarifeyi <a href="https://www.turkpatent.gov.tr/ucret-tarifesi" target="_blank"
                    rel="noopener noreferrer">turkpatent.gov.tr</a> üzerinden doğrulayın.
                Tablo boş bırakılırsa sitedeki ücret bölümü hiç gösterilmez.
            </p>
        </form>
    </div>
</details>

<div class="mk-ozet">
    <a href="marka-talepleri.php" class="<?= $filtre === '' ? 'is-active' : '' ?>">
        Tümü <b><?= array_sum($sayac) ?></b>
    </a>
    <?php foreach ($durumlar as $kod => $etiket): ?>
        <a href="?durum=<?= urlencode($kod) ?>" class="<?= $filtre === $kod ? 'is-active' : '' ?>">
            <?= htmlspecialchars($etiket) ?> <b><?= $sayac[$kod] ?? 0 ?></b>
        </a>
    <?php endforeach; ?>
</div>

<?php if (empty($talepler)): ?>
    <div class="mk-bos">Bu filtreye uyan talep yok.</div>
<?php else: ?>
    <table class="mk-tablo">
        <thead>
            <tr>
                <th>#</th>
                <th>Tarih</th>
                <th>İletişim</th>
                <th>Marka</th>
                <th>Sınıflar</th>
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
                    <td><strong><?= htmlspecialchars((string) $t['marka_adi']) ?></strong></td>
                    <td>
                        <?php foreach (array_filter(explode(',', (string) $t['siniflar'])) as $s): ?>
                            <span class="mk-sinif"><?= (int) $s ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td class="mk-soluk"><?= nl2br(htmlspecialchars((string) ($t['not_metni'] ?? ''))) ?></td>
                    <td>
                        <form method="post">
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
                        <span class="mk-rozet <?= htmlspecialchars((string) $t['durum']) ?>">
                            <?= htmlspecialchars($durumlar[$t['durum']] ?? (string) $t['durum']) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($sayfaSayisi > 1): ?>
        <div class="mk-sayfalar">
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
