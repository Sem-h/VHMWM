<?php
/**
 * VHM - Ürünler
 *
 * Önceki sürümdeki sorunlar:
 *   - Kısa ad preg_replace('/[^a-zA-Z0-9]+/', '-', $ad) ile üretiliyordu;
 *     Türkçe harfler siliniyor, "Ürün Paketi" → "-r-n-paketi" oluyordu.
 *   - slug sütunu UNIQUE; aynı adla ikinci ürün eklenince yakalanmamış
 *     SQL hatası dönüyordu.
 *   - Hiçbir alan doğrulanmıyordu: boş ad, harf içeren fiyat kabul ediliyordu.
 *   - Silme, ürünü kullanan hizmet olup olmadığına bakmıyordu.
 *     services.product_id ON DELETE SET NULL olduğu için müşterinin aktif
 *     hizmeti "Ürün silinmiş" hâline geliyor, ödeme almaya devam ediyordu.
 *   - PRG yoktu; yenilemede aynı ürün tekrar ekleniyordu.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
require_once dirname(__DIR__) . '/includes/Katalog.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

function urunDon(string $tip, string $metin): never
{
    $_SESSION['ur_mesaj'] = ['tip' => $tip, 'metin' => $metin];
    header('Location: products.php' . (!empty($_GET['grup']) ? '?grup=' . (int) $_GET['grup'] : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Guvenlik::zorunlu();

    /* ---------- Silme ---------- */
    if (isset($_POST['sil'])) {
        $urunId = (int) $_POST['sil'];
        $urun = Database::fetch("SELECT name FROM products WHERE id = ?", [$urunId]);

        if (!$urun) {
            urunDon('error', 'Ürün bulunamadı.');
        }

        $kullanim = Katalog::urunKullanimi($urunId);
        if ($kullanim > 0) {
            urunDon(
                'error',
                $urun['name'] . ' silinemez: ' . $kullanim . ' hizmet bu ürünü kullanıyor. '
                . 'Yeni satışı durdurmak için ürünü pasife alın.'
            );
        }

        try {
            Database::query("DELETE FROM products WHERE id = ?", [$urunId]);
        } catch (Throwable $e) {
            error_log('Ürün silinemedi: ' . $e->getMessage());
            urunDon('error', 'Ürün silinemedi.');
        }

        urunDon('success', $urun['name'] . ' silindi.');
    }

    /* ---------- Etkin / pasif ---------- */
    if (isset($_POST['durum_degistir'])) {
        $urunId = (int) $_POST['durum_degistir'];
        $urun = Database::fetch("SELECT name, is_active FROM products WHERE id = ?", [$urunId]);

        if (!$urun) {
            urunDon('error', 'Ürün bulunamadı.');
        }

        $yeni = (int) $urun['is_active'] === 1 ? 0 : 1;
        Database::query("UPDATE products SET is_active = ? WHERE id = ?", [$yeni, $urunId]);

        urunDon('success', $urun['name'] . ($yeni === 1 ? ' etkinleştirildi.' : ' pasife alındı.'));
    }

    /* ---------- Ekleme ---------- */
    if (isset($_POST['ekle'])) {
        $ad = trim((string) ($_POST['name'] ?? ''));

        if ($ad === '') {
            urunDon('error', 'Ürün adı boş olamaz.');
        }
        if (mb_strlen($ad) > 255) {
            urunDon('error', 'Ürün adı çok uzun (en fazla 255 karakter).');
        }

        $grupId = (int) ($_POST['group_id'] ?? 0) ?: null;
        $tip = 'other';

        if ($grupId !== null) {
            $grup = Database::fetch("SELECT type FROM product_groups WHERE id = ?", [$grupId]);
            if (!$grup) {
                urunDon('error', 'Seçilen ürün grubu bulunamadı.');
            }
            if (isset(Katalog::URUN_TIPLERI[$grup['type']])) {
                $tip = (string) $grup['type'];
            }
        }

        /* Grup seçilmemişse tür elle verilebilir */
        if ($grupId === null && isset(Katalog::URUN_TIPLERI[$_POST['type'] ?? ''])) {
            $tip = (string) $_POST['type'];
        }

        try {
            $fiyatlar = [];
            foreach (array_keys(Katalog::DONEM_SUTUNLARI) as $sutun) {
                $fiyatlar[$sutun] = Katalog::fiyatOku($_POST[$sutun] ?? '');
            }
            $kurulum = Katalog::fiyatOku($_POST['setup_fee'] ?? '') ?? 0.0;
        } catch (RuntimeException $e) {
            urunDon('error', $e->getMessage());
        }

        if (!array_filter($fiyatlar, static fn($f) => $f !== null)) {
            urunDon('error', 'En az bir dönem için fiyat girin.');
        }

        try {
            $yeniId = Database::insert('products', array_merge([
                'name' => $ad,
                'slug' => Katalog::benzersizSlug($ad, 'products'),
                'type' => $tip,
                'group_id' => $grupId,
                'description' => trim((string) ($_POST['description'] ?? '')),
                'setup_fee' => $kurulum,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ], $fiyatlar));
        } catch (Throwable $e) {
            error_log('Ürün eklenemedi: ' . $e->getMessage());
            urunDon('error', 'Ürün eklenemedi.');
        }

        $_SESSION['ur_mesaj'] = ['tip' => 'success', 'metin' => $ad . ' eklendi. Ayrıntıları tamamlayabilirsiniz.'];
        header('Location: product-edit.php?id=' . $yeniId);
        exit;
    }

    urunDon('error', 'Tanımsız işlem.');
}

$mesaj = null;
if (!empty($_SESSION['ur_mesaj'])) {
    $mesaj = $_SESSION['ur_mesaj'];
    unset($_SESSION['ur_mesaj']);
}

$gruplar = Database::fetchAll("SELECT * FROM product_groups ORDER BY order_priority, name");

$grupSuzgec = (int) ($_GET['grup'] ?? 0);
$tipSuzgec = (string) ($_GET['tip'] ?? '');
$arama = trim((string) ($_GET['q'] ?? ''));
$durumSuzgec = (string) ($_GET['durum'] ?? '');

$kosul = [];
$par = [];

if ($grupSuzgec > 0) {
    $kosul[] = 'p.group_id = ?';
    $par[] = $grupSuzgec;
}
if (isset(Katalog::URUN_TIPLERI[$tipSuzgec])) {
    $kosul[] = 'p.type = ?';
    $par[] = $tipSuzgec;
}
if ($durumSuzgec === 'etkin' || $durumSuzgec === 'pasif') {
    $kosul[] = 'p.is_active = ?';
    $par[] = $durumSuzgec === 'etkin' ? 1 : 0;
}
if ($arama !== '') {
    $kosul[] = '(p.name LIKE ? OR p.slug LIKE ?)';
    $par[] = '%' . $arama . '%';
    $par[] = '%' . $arama . '%';
}

$nerede = $kosul ? 'WHERE ' . implode(' AND ', $kosul) : '';

$urunler = Database::fetchAll(
    "SELECT p.*, g.name AS grup_adi,
            (SELECT COUNT(*) FROM services s
              WHERE s.product_id = p.id AND s.status IN ('pending','active','suspended')) AS kullanim
       FROM products p
       LEFT JOIN product_groups g ON g.id = p.group_id
       $nerede
       ORDER BY g.order_priority, p.order_priority, p.name",
    $par
);

$toplamUrun = (int) Database::fetchColumn("SELECT COUNT(*) FROM products");
$etkinUrun = (int) Database::fetchColumn("SELECT COUNT(*) FROM products WHERE is_active = 1");
$satilan = (int) Database::fetchColumn(
    "SELECT COUNT(DISTINCT product_id) FROM services WHERE status IN ('pending','active','suspended')"
);

$tipIkon = [
    'hosting' => 'fa-globe', 'vps' => 'fa-server', 'vds' => 'fa-hard-drive',
    'dedicated' => 'fa-database', 'domain' => 'fa-at', 'ssl' => 'fa-lock',
    'other' => 'fa-cube',
];

$pageTitle = 'Ürünler';
$currentPage = 'products';
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* ==========================================
       Ürünler - ur
       ========================================== */
    .ur-ozet {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }

    .ur-ozet-kart {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
    }

    .ur-ozet-ikon {
        width: 36px;
        height: 36px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border-radius: 9px;
        background: var(--y-primary-soft);
        color: var(--y-primary);
        font-size: 14px;
    }

    .ur-ozet-kart b {
        display: block;
        font-size: 19px;
        font-weight: 700;
        letter-spacing: -.02em;
        color: var(--y-metin);
        line-height: 1.2;
    }

    .ur-ozet-kart span {
        font-size: 12px;
        color: var(--y-metin-3);
    }

    .ur-arac {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 9px;
        padding: 12px 14px;
        margin-bottom: 18px;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
    }

    .ur-arac form {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 9px;
        margin: 0;
        flex: 1;
    }

    .ur-arac .form-control {
        width: auto;
        min-width: 130px;
    }

    .ur-arac input[type="search"] {
        flex: 1;
        min-width: 180px;
    }

    .ur-grup {
        margin-bottom: 18px;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .ur-grup-bas {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 16px;
        border-bottom: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
    }

    .ur-grup-bas h3 {
        font-size: 12.5px;
        font-weight: 700;
    }

    .ur-grup-bas span {
        font-size: 12px;
        color: var(--y-metin-3);
    }

    .ur-satir {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 13px 16px;
        border-bottom: 1px solid var(--y-cizgi-soft);
    }

    .ur-satir:last-child {
        border-bottom: none;
    }

    .ur-satir:hover {
        background: var(--y-yuzey-2);
    }

    .ur-satir.pasif {
        opacity: .62;
    }

    .ur-ikon {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border-radius: 9px;
        background: var(--y-yuzey-2);
        color: var(--y-metin-2);
        font-size: 13px;
    }

    .ur-satir.pasif .ur-ikon {
        background: var(--y-yuzey-2);
    }

    .ur-orta {
        flex: 1;
        min-width: 0;
    }

    .ur-orta b {
        font-size: 13.5px;
        font-weight: 600;
        color: var(--y-metin);
    }

    .ur-orta a {
        color: inherit;
    }

    .ur-orta a:hover {
        color: var(--y-primary);
    }

    .ur-alt {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 4px;
    }

    .ur-etiket {
        padding: 2px 8px;
        border: 1px solid var(--y-cizgi);
        border-radius: 999px;
        font-size: 11px;
        color: var(--y-metin-3);
    }

    .ur-fiyat {
        text-align: right;
        white-space: nowrap;
    }

    .ur-fiyat b {
        font-size: 14px;
        font-weight: 700;
        color: var(--y-metin);
    }

    .ur-fiyat span {
        display: block;
        margin-top: 2px;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .ur-eylem {
        display: flex;
        gap: 6px;
        flex-shrink: 0;
    }

    .ur-eylem form {
        margin: 0;
    }

    .ur-dugme {
        width: 30px;
        height: 30px;
        display: grid;
        place-items: center;
        padding: 0;
        border: 1px solid var(--y-cizgi);
        border-radius: 7px;
        background: var(--y-yuzey);
        color: var(--y-metin-3);
        font-size: 12px;
        cursor: pointer;
        transition: .15s;
    }

    .ur-dugme:hover {
        border-color: var(--y-primary);
        color: var(--y-primary);
    }

    .ur-dugme.tehlike:hover {
        border-color: var(--y-danger);
        color: var(--y-danger);
    }

    .ur-bos {
        padding: 40px 16px;
        text-align: center;
        color: var(--y-metin-3);
    }

    .ur-bos i {
        display: block;
        margin-bottom: 10px;
        font-size: 26px;
        opacity: .4;
    }

    /* Yeni ürün kutusu */
    .ur-yeni {
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .ur-yeni-govde {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        padding: 16px;
    }

    .ur-alan label {
        display: block;
        margin-bottom: 4px;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .ur-yeni-alt {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        padding: 12px 16px;
        border-top: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
    }

    .ur-onay {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 12.5px;
        color: var(--y-metin-2);
        cursor: pointer;
    }

    @media (max-width: 700px) {
        .ur-satir {
            flex-wrap: wrap;
        }

        .ur-fiyat {
            text-align: left;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>Ürünler</h1>
        <p>Satışa açtığınız paketler ve fiyatları</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="urYeniAc()">
        <i class="fas fa-plus"></i> Yeni ürün
    </button>
</div>

<?php if ($mesaj): ?>
    <div class="alert alert-<?= htmlspecialchars($mesaj['tip']) ?>">
        <?= htmlspecialchars($mesaj['metin']) ?>
    </div>
<?php endif; ?>

<div class="ur-ozet">
    <div class="ur-ozet-kart">
        <span class="ur-ozet-ikon"><i class="fas fa-cubes"></i></span>
        <span><b><?= $toplamUrun ?></b><span>Toplam ürün</span></span>
    </div>
    <div class="ur-ozet-kart">
        <span class="ur-ozet-ikon"><i class="fas fa-circle-check"></i></span>
        <span><b><?= $etkinUrun ?></b><span>Satışa açık</span></span>
    </div>
    <div class="ur-ozet-kart">
        <span class="ur-ozet-ikon"><i class="fas fa-layer-group"></i></span>
        <span><b><?= count($gruplar) ?></b><span>Ürün grubu</span></span>
    </div>
    <div class="ur-ozet-kart">
        <span class="ur-ozet-ikon"><i class="fas fa-cart-shopping"></i></span>
        <span><b><?= $satilan ?></b><span>Satılmış ürün</span></span>
    </div>
</div>

<div class="ur-yeni" id="ur-yeni" hidden style="margin-bottom:18px">
    <div class="ur-grup-bas"><h3>Yeni ürün</h3></div>
    <form method="POST">
        <input type="hidden" name="ekle" value="1">
        <div class="ur-yeni-govde">
            <div class="ur-alan" style="grid-column:1/-1">
                <label for="name">Ürün adı</label>
                <input type="text" name="name" id="name" class="form-control" required maxlength="255"
                    placeholder="Başlangıç Hosting">
            </div>
            <div class="ur-alan">
                <label for="group_id">Grup</label>
                <select name="group_id" id="group_id" class="form-control" onchange="urTipGizle(this.value)">
                    <option value="0">Grupsuz</option>
                    <?php foreach ($gruplar as $g): ?>
                        <option value="<?= (int) $g['id'] ?>"
                            <?= $grupSuzgec === (int) $g['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $g['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ur-alan" id="ur-tip-alani">
                <label for="type">Tür</label>
                <select name="type" id="type" class="form-control">
                    <?php foreach (Katalog::URUN_TIPLERI as $deger => $ad): ?>
                        <option value="<?= $deger ?>"><?= $ad ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ur-alan">
                <label for="price_monthly">Aylık fiyat</label>
                <input type="text" name="price_monthly" id="price_monthly" class="form-control"
                    inputmode="decimal" placeholder="199,00">
            </div>
            <div class="ur-alan">
                <label for="price_annually">Yıllık fiyat</label>
                <input type="text" name="price_annually" id="price_annually" class="form-control"
                    inputmode="decimal" placeholder="1990,00">
            </div>
            <div class="ur-alan">
                <label for="setup_fee">Kurulum ücreti</label>
                <input type="text" name="setup_fee" id="setup_fee" class="form-control"
                    inputmode="decimal" placeholder="0">
            </div>
            <div class="ur-alan" style="grid-column:1/-1">
                <label for="description">Açıklama</label>
                <input type="text" name="description" id="description" class="form-control" maxlength="500">
            </div>
        </div>
        <div class="ur-yeni-alt">
            <label class="ur-onay">
                <input type="checkbox" name="is_active" value="1" checked>
                Satışa açık olsun
            </label>
            <div style="display:flex;gap:8px">
                <button type="button" class="btn btn-outline" onclick="urYeniKapat()">Vazgeç</button>
                <button type="submit" class="btn btn-primary">Ekle ve düzenle</button>
            </div>
        </div>
    </form>
</div>

<div class="ur-arac">
    <form method="GET">
        <input type="search" name="q" class="form-control" placeholder="Ürün adı ara…"
            value="<?= htmlspecialchars($arama) ?>">
        <select name="grup" class="form-control">
            <option value="0">Tüm gruplar</option>
            <?php foreach ($gruplar as $g): ?>
                <option value="<?= (int) $g['id'] ?>" <?= $grupSuzgec === (int) $g['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) $g['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="tip" class="form-control">
            <option value="">Tüm türler</option>
            <?php foreach (Katalog::URUN_TIPLERI as $deger => $ad): ?>
                <option value="<?= $deger ?>" <?= $tipSuzgec === $deger ? 'selected' : '' ?>><?= $ad ?></option>
            <?php endforeach; ?>
        </select>
        <select name="durum" class="form-control">
            <option value="">Hepsi</option>
            <option value="etkin" <?= $durumSuzgec === 'etkin' ? 'selected' : '' ?>>Satışa açık</option>
            <option value="pasif" <?= $durumSuzgec === 'pasif' ? 'selected' : '' ?>>Pasif</option>
        </select>
        <button type="submit" class="btn btn-outline">Süz</button>
        <?php if ($arama !== '' || $grupSuzgec > 0 || $tipSuzgec !== '' || $durumSuzgec !== ''): ?>
            <a href="products.php" class="btn btn-outline">Temizle</a>
        <?php endif; ?>
    </form>
</div>

<?php if (!$urunler): ?>
    <div class="ur-grup">
        <div class="ur-bos">
            <i class="fas fa-cubes"></i>
            <?= $toplamUrun === 0
                ? 'Henüz ürün eklenmemiş. Yukarıdaki "Yeni ürün" düğmesiyle başlayın.'
                : 'Bu süzgece uyan ürün yok.' ?>
        </div>
    </div>
<?php else: ?>
    <?php
    /* Gruba göre böl; grupsuzlar en sona */
    $bolumler = [];
    foreach ($urunler as $u) {
        $anahtar = $u['grup_adi'] ?? '';
        $bolumler[$anahtar][] = $u;
    }
    ?>
    <?php foreach ($bolumler as $grupAdi => $liste): ?>
        <div class="ur-grup">
            <div class="ur-grup-bas">
                <h3><?= htmlspecialchars($grupAdi !== '' ? (string) $grupAdi : 'Grupsuz ürünler') ?></h3>
                <span><?= count($liste) ?> ürün</span>
            </div>

            <?php foreach ($liste as $u):
                $etkin = (int) $u['is_active'] === 1;
                $fiyatlar = Katalog::fiyatlar($u);
                $ilkAd = array_key_first($fiyatlar);
                ?>
                <div class="ur-satir <?= $etkin ? '' : 'pasif' ?>">
                    <span class="ur-ikon">
                        <i class="fas <?= $tipIkon[$u['type']] ?? 'fa-cube' ?>"></i>
                    </span>

                    <div class="ur-orta">
                        <b><a href="product-edit.php?id=<?= (int) $u['id'] ?>">
                            <?= htmlspecialchars((string) $u['name']) ?></a></b>
                        <div class="ur-alt">
                            <span class="ur-etiket"><?= Katalog::URUN_TIPLERI[$u['type']] ?? htmlspecialchars((string) $u['type']) ?></span>
                            <?php if (!$etkin): ?>
                                <span class="ur-etiket">Pasif</span>
                            <?php endif; ?>
                            <?php if ((int) $u['kullanim'] > 0): ?>
                                <span class="ur-etiket"><?= (int) $u['kullanim'] ?> hizmet</span>
                            <?php endif; ?>
                            <?php if ((float) $u['setup_fee'] > 0): ?>
                                <span class="ur-etiket">Kurulum <?= number_format((float) $u['setup_fee'], 2, ',', '.') ?> ₺</span>
                            <?php endif; ?>
                            <?php if ((int) $u['stock_control'] === 1): ?>
                                <span class="ur-etiket">Stok <?= (int) $u['stock_quantity'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="ur-fiyat">
                        <?php if ($ilkAd === null): ?>
                            <b style="color:var(--y-danger)">Fiyat yok</b>
                            <span>Düzenleyip fiyat girin</span>
                        <?php else: ?>
                            <b><?= number_format($fiyatlar[$ilkAd], 2, ',', '.') ?> ₺</b>
                            <span><?= $ilkAd ?><?= count($fiyatlar) > 1
                                ? ' +' . (count($fiyatlar) - 1) . ' dönem' : '' ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="ur-eylem">
                        <a href="product-edit.php?id=<?= (int) $u['id'] ?>" class="ur-dugme" title="Düzenle">
                            <i class="fas fa-pen"></i>
                        </a>

                        <form method="POST">
                            <input type="hidden" name="durum_degistir" value="<?= (int) $u['id'] ?>">
                            <button type="submit" class="ur-dugme"
                                title="<?= $etkin ? 'Pasife al' : 'Satışa aç' ?>">
                                <i class="fas <?= $etkin ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                            </button>
                        </form>

                        <form method="POST"
                            onsubmit="return confirm('<?= htmlspecialchars(addslashes((string) $u['name'])) ?> silinecek. Emin misiniz?')">
                            <input type="hidden" name="sil" value="<?= (int) $u['id'] ?>">
                            <button type="submit" class="ur-dugme tehlike" title="Sil">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
    function urYeniAc() {
        var k = document.getElementById('ur-yeni');
        k.hidden = false;
        document.getElementById('name').focus();
    }

    function urYeniKapat() {
        document.getElementById('ur-yeni').hidden = true;
    }

    /* Grup seçilince tür gruptan gelir, elle seçim anlamsız olur */
    function urTipGizle(deger) {
        document.getElementById('ur-tip-alani').hidden = deger !== '0';
    }

    urTipGizle(document.getElementById('group_id').value);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
