<?php
/**
 * VHM - Ürün türleri
 *
 * Bu sayfa eskiden yeni tür "ekleyebiliyordu" ama hiçbir işe yaramıyordu:
 * product_types tablosunu projede yalnızca bu sayfanın kendisi okuyordu.
 * Hangi türlerin var olabileceğini products.type enum'u belirliyor; tabloya
 * eklenen bir tür (örneğin varsayılan gelen 'email') o enum'da olmadığı için
 * o türdeki gruba hiçbir ürün eklenemiyordu.
 *
 * Artık tablo gerçekten devrede: tür listesi enum'dan gelir, görünen ad /
 * simge / renk / sıra buradan düzenlenir ve Katalog::tipler() üzerinden
 * ürün ve grup sayfalarında görünür. Enum dışındaki satırlar "kullanılmıyor"
 * olarak işaretlenir.
 *
 * Sayfa ayrıca her açılışta CREATE TABLE çalıştırıyordu; şema işi istek
 * başına yapılacak iş değil, tablo kurulum şemasında var.
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

function tipDon(string $tip, string $metin): never
{
    $_SESSION['tp_mesaj'] = ['tip' => $tip, 'metin' => $metin];
    header('Location: product-types.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Guvenlik::zorunlu();

    /* ---------- Görünümü kaydet ---------- */
    if (isset($_POST['kaydet'])) {
        $slug = (string) ($_POST['slug'] ?? '');

        if (!isset(Katalog::URUN_TIPLERI[$slug])) {
            tipDon('error', 'Bu tür ürün tablosunda tanımlı değil, düzenlenemez.');
        }

        $ad = trim((string) ($_POST['label'] ?? ''));
        $ikon = trim((string) ($_POST['icon'] ?? ''));
        $renk = trim((string) ($_POST['color'] ?? ''));
        $sira = (int) ($_POST['order_priority'] ?? 0);

        if ($ad === '') {
            tipDon('error', 'Görünen ad boş olamaz.');
        }
        if (mb_strlen($ad) > 100) {
            tipDon('error', 'Görünen ad çok uzun (en fazla 100 karakter).');
        }
        if ($ikon !== '' && !preg_match('/^fa-[a-z0-9-]{1,40}$/', $ikon)) {
            tipDon('error', 'Simge adı fa- ile başlamalı (örnek: fa-server).');
        }
        if ($renk !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $renk)) {
            tipDon('error', 'Renk #rrggbb biçiminde olmalı.');
        }
        if ($sira < 0 || $sira > 999) {
            tipDon('error', 'Sıra 0 ile 999 arasında olmalı.');
        }

        $veri = [
            'label' => $ad,
            'icon' => $ikon !== '' ? $ikon : 'fa-cube',
            'color' => $renk !== '' ? $renk : '#64748b',
            'order_priority' => $sira,
        ];

        try {
            if (Database::fetch("SELECT id FROM product_types WHERE slug = ?", [$slug])) {
                Database::update('product_types', $veri, 'slug = ?', [$slug]);
            } else {
                Database::insert('product_types', array_merge(['slug' => $slug], $veri));
            }
        } catch (Throwable $e) {
            error_log('Ürün türü kaydedilemedi: ' . $e->getMessage());
            tipDon('error', 'Tür kaydedilemedi.');
        }

        tipDon('success', $ad . ' güncellendi.');
    }

    /* ---------- Sahipsiz satırı sil ---------- */
    if (isset($_POST['sahipsiz_sil'])) {
        $slug = (string) $_POST['sahipsiz_sil'];

        if (isset(Katalog::URUN_TIPLERI[$slug])) {
            tipDon('error', 'Bu tür kullanımda; silinemez.');
        }

        $kullananGrup = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM product_groups WHERE type = ?",
            [$slug]
        );

        if ($kullananGrup > 0) {
            tipDon(
                'error',
                'Bu türü ' . $kullananGrup . ' grup kullanıyor. Önce grupların türünü değiştirin.'
            );
        }

        Database::query("DELETE FROM product_types WHERE slug = ?", [$slug]);
        tipDon('success', $slug . ' satırı kaldırıldı.');
    }

    tipDon('error', 'Tanımsız işlem.');
}

$mesaj = null;
if (!empty($_SESSION['tp_mesaj'])) {
    $mesaj = $_SESSION['tp_mesaj'];
    unset($_SESSION['tp_mesaj']);
}

$tipler = Katalog::tipler();
$sahipsizler = Katalog::sahipsizTipler();

/* Her tür kaç yerde kullanılıyor */
$urunSayisi = [];
foreach (Database::fetchAll("SELECT type, COUNT(*) AS adet FROM products GROUP BY type") as $r) {
    $urunSayisi[(string) $r['type']] = (int) $r['adet'];
}

$grupSayisi = [];
foreach (Database::fetchAll("SELECT type, COUNT(*) AS adet FROM product_groups GROUP BY type") as $r) {
    $grupSayisi[(string) $r['type']] = (int) $r['adet'];
}

$duzenlenen = null;
if (!empty($_GET['duzenle']) && isset($tipler[(string) $_GET['duzenle']])) {
    $duzenlenen = (string) $_GET['duzenle'];
}

$pageTitle = 'Ürün türleri';
$currentPage = 'product-types';
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* ==========================================
       Ürün türleri - tp
       ========================================== */
    .tp-not {
        display: flex;
        align-items: flex-start;
        gap: 11px;
        padding: 13px 16px;
        margin-bottom: 18px;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        font-size: 13px;
        line-height: 1.6;
        color: var(--y-metin-2);
    }

    .tp-not i {
        margin-top: 2px;
        color: var(--y-primary);
    }

    .tp-izgara {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 14px;
    }

    .tp-kart {
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .tp-kart.acik {
        border-color: var(--y-primary);
    }

    .tp-kart-bas {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
    }

    .tp-ikon {
        width: 40px;
        height: 40px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border-radius: 11px;
        color: #fff;
        font-size: 15px;
    }

    .tp-kart-bas div {
        flex: 1;
        min-width: 0;
    }

    .tp-kart-bas b {
        display: block;
        font-size: 13.5px;
        font-weight: 600;
        color: var(--y-metin);
    }

    .tp-kod {
        font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .tp-sayilar {
        display: flex;
        gap: 1px;
        background: var(--y-cizgi);
        border-top: 1px solid var(--y-cizgi);
    }

    .tp-sayi {
        flex: 1;
        padding: 10px 14px;
        background: var(--y-yuzey-2);
        text-align: center;
    }

    .tp-sayi b {
        display: block;
        font-size: 15px;
        font-weight: 700;
        color: var(--y-metin);
    }

    .tp-sayi span {
        font-size: 11px;
        color: var(--y-metin-3);
    }

    .tp-alt {
        padding: 10px 16px;
        border-top: 1px solid var(--y-cizgi);
        text-align: right;
    }

    .tp-baglanti {
        font-size: 12.5px;
        font-weight: 600;
        color: var(--y-primary);
    }

    .tp-form {
        padding: 16px;
        border-top: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
    }

    .tp-alan {
        margin-bottom: 11px;
    }

    .tp-alan label {
        display: block;
        margin-bottom: 4px;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .tp-ikili {
        display: grid;
        grid-template-columns: 1fr 76px;
        gap: 9px;
    }

    .tp-renk {
        height: 38px;
        padding: 3px;
        border: 1px solid var(--y-cizgi);
        border-radius: 8px;
        background: var(--y-yuzey);
        cursor: pointer;
    }

    .tp-form-alt {
        display: flex;
        gap: 8px;
    }

    .tp-form-alt .btn {
        flex: 1;
        justify-content: center;
    }

    .tp-sahipsiz {
        margin-top: 22px;
        border: 1px solid color-mix(in srgb, var(--y-warning) 40%, var(--y-cizgi));
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .tp-sahipsiz-bas {
        padding: 12px 16px;
        border-bottom: 1px solid var(--y-cizgi);
        background: color-mix(in srgb, var(--y-warning) 12%, var(--y-yuzey));
        font-size: 12.5px;
        font-weight: 700;
    }

    .tp-sahipsiz-satir {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-bottom: 1px solid var(--y-cizgi-soft);
        font-size: 13px;
    }

    .tp-sahipsiz-satir:last-child {
        border-bottom: none;
    }

    .tp-sahipsiz-satir div {
        flex: 1;
    }

    .tp-sahipsiz-satir small {
        display: block;
        margin-top: 2px;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .tp-sahipsiz-satir form {
        margin: 0;
    }
</style>

<div class="page-header">
    <div>
        <h1>Ürün türleri</h1>
        <p>Türlerin panelde ve mağazada nasıl görüneceği</p>
    </div>
</div>

<?php if ($mesaj): ?>
    <div class="alert alert-<?= htmlspecialchars($mesaj['tip']) ?>">
        <?= htmlspecialchars($mesaj['metin']) ?>
    </div>
<?php endif; ?>

<div class="tp-not">
    <i class="fas fa-circle-info"></i>
    <span>
        Tür listesi <code>products.type</code> sütununa bağlıdır; buradan yeni tür
        eklenemez, mevcut türlerin görünen adı, simgesi, rengi ve sırası düzenlenir.
        Yeni bir tür gerekiyorsa veritabanı şemasının değişmesi gerekir.
    </span>
</div>

<div class="tp-izgara">
    <?php foreach ($tipler as $slug => $t):
        $acik = $duzenlenen === $slug;
        ?>
        <div class="tp-kart <?= $acik ? 'acik' : '' ?>">
            <div class="tp-kart-bas">
                <span class="tp-ikon" style="background:<?= htmlspecialchars($t['renk']) ?>">
                    <i class="fas <?= htmlspecialchars($t['ikon']) ?>"></i>
                </span>
                <div>
                    <b><?= htmlspecialchars($t['ad']) ?></b>
                    <span class="tp-kod"><?= htmlspecialchars($slug) ?></span>
                </div>
            </div>

            <div class="tp-sayilar">
                <div class="tp-sayi">
                    <b><?= $urunSayisi[$slug] ?? 0 ?></b>
                    <span>ürün</span>
                </div>
                <div class="tp-sayi">
                    <b><?= $grupSayisi[$slug] ?? 0 ?></b>
                    <span>grup</span>
                </div>
                <div class="tp-sayi">
                    <b><?= $t['sira'] ?></b>
                    <span>sıra</span>
                </div>
            </div>

            <?php if ($acik): ?>
                <form method="POST" class="tp-form">
                    <input type="hidden" name="kaydet" value="1">
                    <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>">

                    <div class="tp-alan">
                        <label for="label-<?= $slug ?>">Görünen ad</label>
                        <input type="text" name="label" id="label-<?= $slug ?>" class="form-control"
                            required maxlength="100" value="<?= htmlspecialchars($t['ad']) ?>">
                    </div>

                    <div class="tp-alan">
                        <label for="icon-<?= $slug ?>">Simge</label>
                        <div class="tp-ikili">
                            <input type="text" name="icon" id="icon-<?= $slug ?>" class="form-control"
                                maxlength="40" pattern="fa-[a-z0-9\-]{1,40}"
                                value="<?= htmlspecialchars($t['ikon']) ?>" placeholder="fa-server">
                            <input type="color" name="color" class="tp-renk"
                                value="<?= htmlspecialchars($t['renk']) ?>" title="Renk">
                        </div>
                    </div>

                    <div class="tp-alan">
                        <label for="sira-<?= $slug ?>">Sıra</label>
                        <input type="number" name="order_priority" id="sira-<?= $slug ?>" class="form-control"
                            min="0" max="999" value="<?= $t['sira'] ?>">
                    </div>

                    <div class="tp-form-alt">
                        <a href="product-types.php" class="btn btn-outline">Vazgeç</a>
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="tp-alt">
                    <a href="product-types.php?duzenle=<?= htmlspecialchars($slug) ?>" class="tp-baglanti">
                        Düzenle <i class="fas fa-chevron-right" style="font-size:10px"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($sahipsizler): ?>
    <div class="tp-sahipsiz">
        <div class="tp-sahipsiz-bas">Kullanılmayan tür kayıtları</div>
        <?php foreach ($sahipsizler as $s): ?>
            <div class="tp-sahipsiz-satir">
                <div>
                    <b><?= htmlspecialchars((string) $s['label']) ?></b>
                    <span class="tp-kod">(<?= htmlspecialchars((string) $s['slug']) ?>)</span>
                    <small>
                        Bu tür <code>products.type</code> sütununda yok; bu türdeki bir gruba
                        ürün eklenemez.
                        <?php $kullanan = $grupSayisi[(string) $s['slug']] ?? 0; ?>
                        <?= $kullanan > 0
                            ? $kullanan . ' grup bu türü kullanıyor.'
                            : 'Hiçbir grup kullanmıyor.' ?>
                    </small>
                </div>
                <form method="POST"
                    onsubmit="return confirm('<?= htmlspecialchars(addslashes((string) $s['slug'])) ?> kaydı silinecek. Emin misiniz?')">
                    <input type="hidden" name="sahipsiz_sil" value="<?= htmlspecialchars((string) $s['slug']) ?>">
                    <button type="submit" class="btn btn-sm btn-outline">Kaldır</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
