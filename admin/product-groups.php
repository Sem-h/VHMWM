<?php
/**
 * VHM - Ürün grupları
 *
 * Önceki sürümdeki sorunlar:
 *   - Her sayfa açılışında ALTER TABLE çalıştırıyordu. Şema değişikliği
 *     istek başına yapılacak iş değil; sütun zaten kurulum şemasında var.
 *   - Kısa ad Türkçe harfleri siliyordu; çakışma olunca sonuna time()
 *     ekleniyordu ("hosting-1757800000" gibi adresler oluşuyordu).
 *   - Güncellemede ad hiç doğrulanmıyor, kısa adın benzersizliğine
 *     bakılmıyordu; aynı slug girilince yakalanmamış SQL hatası dönüyordu.
 *   - PRG yoktu; yenilemede aynı grup tekrar ekleniyordu.
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

function grupDon(string $tip, string $metin): never
{
    $_SESSION['gr_mesaj'] = ['tip' => $tip, 'metin' => $metin];
    header('Location: product-groups.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Guvenlik::zorunlu();

    /* ---------- Silme ---------- */
    if (isset($_POST['sil'])) {
        $grupId = (int) $_POST['sil'];
        $grup = Database::fetch("SELECT name FROM product_groups WHERE id = ?", [$grupId]);

        if (!$grup) {
            grupDon('error', 'Grup bulunamadı.');
        }

        $urunSayisi = Katalog::gruptakiUrun($grupId);
        if ($urunSayisi > 0) {
            grupDon(
                'error',
                $grup['name'] . ' silinemez: gruba bağlı ' . $urunSayisi
                . ' ürün var. Önce ürünleri başka gruba taşıyın.'
            );
        }

        Database::query("DELETE FROM product_groups WHERE id = ?", [$grupId]);
        grupDon('success', $grup['name'] . ' silindi.');
    }

    /* ---------- Görünürlük ---------- */
    if (isset($_POST['gizle_degistir'])) {
        $grupId = (int) $_POST['gizle_degistir'];
        $grup = Database::fetch("SELECT name, is_hidden FROM product_groups WHERE id = ?", [$grupId]);

        if (!$grup) {
            grupDon('error', 'Grup bulunamadı.');
        }

        $yeni = (int) $grup['is_hidden'] === 1 ? 0 : 1;
        Database::query("UPDATE product_groups SET is_hidden = ? WHERE id = ?", [$yeni, $grupId]);

        grupDon('success', $grup['name'] . ($yeni === 1 ? ' gizlendi.' : ' mağazada görünür yapıldı.'));
    }

    /* ---------- Ekleme / güncelleme ---------- */
    if (isset($_POST['kaydet'])) {
        $grupId = (int) ($_POST['id'] ?? 0);
        $ad = trim((string) ($_POST['name'] ?? ''));
        $tip = (string) ($_POST['type'] ?? 'hosting');
        $aciklama = trim((string) ($_POST['description'] ?? ''));
        $sira = (int) ($_POST['order_priority'] ?? 0);
        $gizli = isset($_POST['is_hidden']) ? 1 : 0;
        $elleSlug = trim((string) ($_POST['slug'] ?? ''));

        if ($ad === '') {
            grupDon('error', 'Grup adı boş olamaz.');
        }
        if (mb_strlen($ad) > 255) {
            grupDon('error', 'Grup adı çok uzun (en fazla 255 karakter).');
        }
        if (!isset(Katalog::URUN_TIPLERI[$tip])) {
            grupDon('error', 'Geçersiz ürün türü.');
        }
        if ($sira < 0 || $sira > 9999) {
            grupDon('error', 'Sıra 0 ile 9999 arasında olmalı.');
        }

        /* Elle girilen kısa ad da temizlenir; çakışırsa -2 eklenir */
        $slug = Katalog::benzersizSlug(
            $elleSlug !== '' ? $elleSlug : $ad,
            'product_groups',
            $grupId > 0 ? $grupId : null
        );

        $veri = [
            'name' => $ad,
            'slug' => $slug,
            'type' => $tip,
            'description' => $aciklama,
            'order_priority' => $sira,
            'is_hidden' => $gizli,
        ];

        try {
            if ($grupId > 0) {
                if (!Database::fetch("SELECT id FROM product_groups WHERE id = ?", [$grupId])) {
                    grupDon('error', 'Güncellenecek grup bulunamadı.');
                }
                Database::update('product_groups', $veri, 'id = ?', [$grupId]);
                grupDon('success', $ad . ' güncellendi.');
            }

            Database::insert('product_groups', $veri);
            grupDon('success', $ad . ' eklendi.');
        } catch (Throwable $e) {
            error_log('Ürün grubu kaydedilemedi: ' . $e->getMessage());
            grupDon('error', 'Grup kaydedilemedi.');
        }
    }

    grupDon('error', 'Tanımsız işlem.');
}

$mesaj = null;
if (!empty($_SESSION['gr_mesaj'])) {
    $mesaj = $_SESSION['gr_mesaj'];
    unset($_SESSION['gr_mesaj']);
}

$duzenlenen = null;
if (!empty($_GET['duzenle'])) {
    $duzenlenen = Database::fetch(
        "SELECT * FROM product_groups WHERE id = ?",
        [(int) $_GET['duzenle']]
    );
}

$gruplar = Database::fetchAll(
    "SELECT g.*,
            COUNT(p.id) AS urun_sayisi,
            SUM(CASE WHEN p.is_active = 1 THEN 1 ELSE 0 END) AS etkin_sayisi
       FROM product_groups g
       LEFT JOIN products p ON p.group_id = g.id
      GROUP BY g.id
      ORDER BY g.order_priority, g.name"
);

$grupsuzUrun = (int) Database::fetchColumn("SELECT COUNT(*) FROM products WHERE group_id IS NULL");

$pageTitle = 'Ürün grupları';
$currentPage = 'product-groups';
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* ==========================================
       Ürün grupları - gr
       ========================================== */
    .gr-duzen {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 310px;
        gap: 18px;
        align-items: start;
    }

    .gr-liste {
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .gr-bas {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 16px;
        border-bottom: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
    }

    .gr-bas h3 {
        font-size: 12.5px;
        font-weight: 700;
    }

    .gr-bas span {
        font-size: 12px;
        color: var(--y-metin-3);
    }

    .gr-satir {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 13px 16px;
        border-bottom: 1px solid var(--y-cizgi-soft);
    }

    .gr-satir:last-child {
        border-bottom: none;
    }

    .gr-satir:hover {
        background: var(--y-yuzey-2);
    }

    .gr-satir.secili {
        background: var(--y-primary-soft);
    }

    .gr-satir.gizli {
        opacity: .62;
    }

    .gr-sira {
        width: 26px;
        flex-shrink: 0;
        font-size: 11.5px;
        font-weight: 700;
        color: var(--y-metin-3);
        text-align: center;
    }

    .gr-ikon {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border-radius: 9px;
        background: var(--y-primary-soft);
        color: var(--y-primary);
        font-size: 13px;
    }

    .gr-orta {
        flex: 1;
        min-width: 0;
    }

    .gr-orta b {
        font-size: 13.5px;
        font-weight: 600;
        color: var(--y-metin);
    }

    .gr-alt {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 4px;
    }

    .gr-etiket {
        padding: 2px 8px;
        border: 1px solid var(--y-cizgi);
        border-radius: 999px;
        font-size: 11px;
        color: var(--y-metin-3);
    }

    .gr-etiket.kod {
        font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace;
    }

    .gr-sayi {
        text-align: right;
        white-space: nowrap;
    }

    .gr-sayi b {
        font-size: 15px;
        font-weight: 700;
        color: var(--y-metin);
    }

    .gr-sayi span {
        display: block;
        margin-top: 1px;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .gr-eylem {
        display: flex;
        gap: 6px;
        flex-shrink: 0;
    }

    .gr-eylem form {
        margin: 0;
    }

    .gr-dugme {
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

    .gr-dugme:hover {
        border-color: var(--y-primary);
        color: var(--y-primary);
    }

    .gr-dugme.tehlike:hover {
        border-color: var(--y-danger);
        color: var(--y-danger);
    }

    .gr-form {
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
        position: sticky;
        top: 78px;
    }

    .gr-form-govde {
        padding: 16px;
    }

    .gr-alan {
        margin-bottom: 12px;
    }

    .gr-alan label {
        display: block;
        margin-bottom: 4px;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .gr-alan small {
        display: block;
        margin-top: 4px;
        font-size: 11px;
        color: var(--y-metin-3);
    }

    .gr-onay {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 12.5px;
        color: var(--y-metin-2);
        cursor: pointer;
    }

    .gr-form-alt {
        display: flex;
        gap: 8px;
        padding: 12px 16px;
        border-top: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
    }

    .gr-form-alt .btn {
        flex: 1;
        justify-content: center;
    }

    .gr-bos {
        padding: 40px 16px;
        text-align: center;
        color: var(--y-metin-3);
    }

    .gr-bos i {
        display: block;
        margin-bottom: 10px;
        font-size: 26px;
        opacity: .4;
    }

    .gr-uyari {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 11px 16px;
        border-bottom: 1px solid var(--y-cizgi);
        background: color-mix(in srgb, var(--y-warning) 12%, var(--y-yuzey));
        font-size: 12.5px;
        color: var(--y-metin-2);
    }

    .gr-uyari a {
        color: var(--y-primary);
        font-weight: 600;
    }

    @media (max-width: 980px) {
        .gr-duzen {
            grid-template-columns: minmax(0, 1fr);
        }

        .gr-form {
            position: static;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>Ürün grupları</h1>
        <p>Mağazada ürünlerin hangi başlık altında görüneceği</p>
    </div>
</div>

<?php if ($mesaj): ?>
    <div class="alert alert-<?= htmlspecialchars($mesaj['tip']) ?>">
        <?= htmlspecialchars($mesaj['metin']) ?>
    </div>
<?php endif; ?>

<div class="gr-duzen">
    <div class="gr-liste">
        <div class="gr-bas">
            <h3>Gruplar</h3>
            <span><?= count($gruplar) ?> grup</span>
        </div>

        <?php if ($grupsuzUrun > 0): ?>
            <div class="gr-uyari">
                <i class="fas fa-triangle-exclamation"></i>
                <span><?= $grupsuzUrun ?> ürün hiçbir gruba bağlı değil; mağazada görünmez.
                    <a href="products.php">Ürünlere git</a></span>
            </div>
        <?php endif; ?>

        <?php if (!$gruplar): ?>
            <div class="gr-bos">
                <i class="fas fa-layer-group"></i>
                Henüz grup yok. Sağdaki formdan ekleyin.
            </div>
        <?php else: ?>
            <?php foreach ($gruplar as $g):
                $gizli = (int) $g['is_hidden'] === 1;
                $secili = $duzenlenen && (int) $duzenlenen['id'] === (int) $g['id'];
                ?>
                <div class="gr-satir <?= $gizli ? 'gizli' : '' ?> <?= $secili ? 'secili' : '' ?>">
                    <span class="gr-sira"><?= (int) $g['order_priority'] ?></span>
                    <span class="gr-ikon">
                        <i class="fas <?= Katalog::tipIkonu($g['type']) ?>"></i>
                    </span>

                    <div class="gr-orta">
                        <b><?= htmlspecialchars((string) $g['name']) ?></b>
                        <div class="gr-alt">
                            <span class="gr-etiket"><?= htmlspecialchars(Katalog::tipAdi($g['type'])) ?></span>
                            <span class="gr-etiket kod">/<?= htmlspecialchars((string) $g['slug']) ?></span>
                            <?php if ($gizli): ?>
                                <span class="gr-etiket">Gizli</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="gr-sayi">
                        <b><?= (int) $g['urun_sayisi'] ?></b>
                        <span><?= (int) $g['etkin_sayisi'] ?> satışta</span>
                    </div>

                    <div class="gr-eylem">
                        <a href="products.php?grup=<?= (int) $g['id'] ?>" class="gr-dugme" title="Ürünlerini gör">
                            <i class="fas fa-cubes"></i>
                        </a>
                        <a href="product-groups.php?duzenle=<?= (int) $g['id'] ?>" class="gr-dugme" title="Düzenle">
                            <i class="fas fa-pen"></i>
                        </a>
                        <form method="POST">
                            <input type="hidden" name="gizle_degistir" value="<?= (int) $g['id'] ?>">
                            <button type="submit" class="gr-dugme" title="<?= $gizli ? 'Göster' : 'Gizle' ?>">
                                <i class="fas <?= $gizli ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                            </button>
                        </form>
                        <form method="POST"
                            onsubmit="return confirm('<?= htmlspecialchars(addslashes((string) $g['name'])) ?> silinecek. Emin misiniz?')">
                            <input type="hidden" name="sil" value="<?= (int) $g['id'] ?>">
                            <button type="submit" class="gr-dugme tehlike" title="Sil">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="gr-form">
        <div class="gr-bas">
            <h3><?= $duzenlenen ? 'Grubu düzenle' : 'Yeni grup' ?></h3>
            <?php if ($duzenlenen): ?>
                <a href="product-groups.php" style="font-size:12px;color:var(--y-primary)">Vazgeç</a>
            <?php endif; ?>
        </div>

        <form method="POST">
            <input type="hidden" name="kaydet" value="1">
            <?php if ($duzenlenen): ?>
                <input type="hidden" name="id" value="<?= (int) $duzenlenen['id'] ?>">
            <?php endif; ?>

            <div class="gr-form-govde">
                <div class="gr-alan">
                    <label for="name">Grup adı</label>
                    <input type="text" name="name" id="name" class="form-control" required maxlength="255"
                        value="<?= htmlspecialchars((string) ($duzenlenen['name'] ?? '')) ?>"
                        placeholder="Paylaşımlı Hosting">
                </div>

                <div class="gr-alan">
                    <label for="type">Ürün türü</label>
                    <select name="type" id="type" class="form-control">
                        <?php foreach (Katalog::tipler() as $deger => $t): $ad = $t['ad']; ?>
                            <option value="<?= $deger ?>"
                                <?= ($duzenlenen['type'] ?? 'hosting') === $deger ? 'selected' : '' ?>>
                                <?= $ad ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Bu gruba eklenen ürünler bu türü alır.</small>
                </div>

                <div class="gr-alan">
                    <label for="slug">Kısa ad</label>
                    <input type="text" name="slug" id="slug" class="form-control" maxlength="255"
                        value="<?= htmlspecialchars((string) ($duzenlenen['slug'] ?? '')) ?>"
                        placeholder="Boş bırakın, addan üretilir">
                    <small>Mağaza adresinde görünür. Türkçe harfler çevrilir.</small>
                </div>

                <div class="gr-alan">
                    <label for="description">Açıklama</label>
                    <input type="text" name="description" id="description" class="form-control" maxlength="500"
                        value="<?= htmlspecialchars((string) ($duzenlenen['description'] ?? '')) ?>">
                </div>

                <div class="gr-alan">
                    <label for="order_priority">Sıra</label>
                    <input type="number" name="order_priority" id="order_priority" class="form-control"
                        min="0" max="9999"
                        value="<?= (int) ($duzenlenen['order_priority'] ?? 0) ?>">
                    <small>Küçük sayı önce görünür.</small>
                </div>

                <label class="gr-onay">
                    <input type="checkbox" name="is_hidden" value="1"
                        <?= (int) ($duzenlenen['is_hidden'] ?? 0) === 1 ? 'checked' : '' ?>>
                    Mağazada gizle
                </label>
            </div>

            <div class="gr-form-alt">
                <button type="submit" class="btn btn-primary">
                    <?= $duzenlenen ? 'Kaydet' : 'Grup ekle' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
