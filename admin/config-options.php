<?php
/**
 * VHM - Yapılandırılabilir seçenekler
 *
 * Buradaki fiyatlar sepete ve ödemeye giriyor (includes/Sepet.php).
 * Önceki sürümde hiçbir fiyat doğrulanmıyordu: (float)$_POST[...] harf
 * girilince sessizce 0 oluyor, eksi değer kabul ediliyordu.
 *
 * Sayfa ayrıca her açılışta install/config_options_tables.sql dosyasını
 * baştan sona çalıştırıyordu; dört tablo da kurulum şemasında zaten var.
 *
 * Grup silinirken içindeki seçeneklere ve o gruba bağlı ürünlere
 * bakılmıyordu; bağlı ürünün sepetteki ek seçenekleri sessizce kayboluyordu.
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

/** config_options.option_type için kabul edilen değerler */
const SC_TURLER = [
    'dropdown' => 'Açılır liste',
    'radio' => 'Tek seçim',
    'checkbox' => 'Onay kutusu',
    'quantity' => 'Adet',
    'text' => 'Serbest metin',
];

function secenekDon(string $tip, string $metin, int $grupId = 0): never
{
    $_SESSION['sc_mesaj'] = ['tip' => $tip, 'metin' => $metin];
    header('Location: config-options.php' . ($grupId > 0 ? '?grup=' . $grupId : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Guvenlik::zorunlu();
    $islem = (string) ($_POST['islem'] ?? '');
    $grupId = (int) ($_POST['group_id'] ?? 0);

    /* ---------- Grup ---------- */
    if ($islem === 'grup_kaydet') {
        $ad = trim((string) ($_POST['name'] ?? ''));
        $aciklama = trim((string) ($_POST['description'] ?? ''));

        if ($ad === '') {
            secenekDon('error', 'Grup adı boş olamaz.', $grupId);
        }
        if (mb_strlen($ad) > 255) {
            secenekDon('error', 'Grup adı çok uzun.', $grupId);
        }

        if ($grupId > 0) {
            Database::update('config_option_groups',
                ['name' => $ad, 'description' => $aciklama], 'id = ?', [$grupId]);
            secenekDon('success', $ad . ' güncellendi.', $grupId);
        }

        $yeni = Database::insert('config_option_groups',
            ['name' => $ad, 'description' => $aciklama]);
        secenekDon('success', $ad . ' oluşturuldu.', $yeni);
    }

    if ($islem === 'grup_sil') {
        $grup = Database::fetch("SELECT name FROM config_option_groups WHERE id = ?", [$grupId]);
        if (!$grup) {
            secenekDon('error', 'Grup bulunamadı.');
        }

        $bagliUrun = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM product_config_links WHERE group_id = ?",
            [$grupId]
        );

        if ($bagliUrun > 0) {
            secenekDon(
                'error',
                $grup['name'] . ' silinemez: ' . $bagliUrun . ' ürün bu seçenek grubunu kullanıyor. '
                . 'Önce ürün düzenleme ekranından bağlantıyı kaldırın.'
            );
        }

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            Database::query(
                "DELETE v FROM config_option_values v
                   JOIN config_options o ON o.id = v.option_id
                  WHERE o.group_id = ?",
                [$grupId]
            );
            Database::query("DELETE FROM config_options WHERE group_id = ?", [$grupId]);
            Database::query("DELETE FROM config_option_groups WHERE id = ?", [$grupId]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Seçenek grubu silinemedi: ' . $e->getMessage());
            secenekDon('error', 'Grup silinemedi.');
        }

        secenekDon('success', $grup['name'] . ' ve içindeki seçenekler silindi.');
    }

    /* ---------- Seçenek ---------- */
    if ($islem === 'secenek_kaydet') {
        $secenekId = (int) ($_POST['option_id'] ?? 0);
        $ad = trim((string) ($_POST['name'] ?? ''));
        $tur = (string) ($_POST['option_type'] ?? 'dropdown');
        $zorunlu = isset($_POST['required']) ? 1 : 0;
        $sira = (int) ($_POST['order_priority'] ?? 0);

        if ($ad === '') {
            secenekDon('error', 'Seçenek adı boş olamaz.', $grupId);
        }
        if (!isset(SC_TURLER[$tur])) {
            secenekDon('error', 'Geçersiz seçenek türü.', $grupId);
        }
        if ($sira < 0 || $sira > 999) {
            secenekDon('error', 'Sıra 0 ile 999 arasında olmalı.', $grupId);
        }

        $veri = [
            'name' => $ad,
            'option_type' => $tur,
            'required' => $zorunlu,
            'order_priority' => $sira,
        ];

        if ($secenekId > 0) {
            $mevcut = Database::fetch("SELECT group_id FROM config_options WHERE id = ?", [$secenekId]);
            if (!$mevcut) {
                secenekDon('error', 'Seçenek bulunamadı.', $grupId);
            }
            Database::update('config_options', $veri, 'id = ?', [$secenekId]);
            secenekDon('success', $ad . ' güncellendi.', (int) $mevcut['group_id']);
        }

        if (!Database::fetch("SELECT id FROM config_option_groups WHERE id = ?", [$grupId])) {
            secenekDon('error', 'Seçenek grubu bulunamadı.');
        }

        Database::insert('config_options', array_merge(['group_id' => $grupId], $veri));
        secenekDon('success', $ad . ' eklendi.', $grupId);
    }

    if ($islem === 'secenek_sil') {
        $secenekId = (int) ($_POST['option_id'] ?? 0);
        $secenek = Database::fetch("SELECT name, group_id FROM config_options WHERE id = ?", [$secenekId]);

        if (!$secenek) {
            secenekDon('error', 'Seçenek bulunamadı.', $grupId);
        }

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            Database::query("DELETE FROM config_option_values WHERE option_id = ?", [$secenekId]);
            Database::query("DELETE FROM config_options WHERE id = ?", [$secenekId]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Seçenek silinemedi: ' . $e->getMessage());
            secenekDon('error', 'Seçenek silinemedi.', (int) $secenek['group_id']);
        }

        secenekDon('success', $secenek['name'] . ' silindi.', (int) $secenek['group_id']);
    }

    /* ---------- Değer ---------- */
    if ($islem === 'deger_kaydet') {
        $degerId = (int) ($_POST['value_id'] ?? 0);
        $secenekId = (int) ($_POST['option_id'] ?? 0);
        $ad = trim((string) ($_POST['name'] ?? ''));
        $varsayilan = isset($_POST['is_default']) ? 1 : 0;
        $sira = (int) ($_POST['order_priority'] ?? 0);

        if ($ad === '') {
            secenekDon('error', 'Değer adı boş olamaz.', $grupId);
        }

        $secenek = Database::fetch(
            "SELECT id, group_id FROM config_options WHERE id = ?",
            [$secenekId]
        );
        if (!$secenek) {
            secenekDon('error', 'Seçenek bulunamadı.', $grupId);
        }

        /* Bu fiyatlar doğrudan sepete giriyor; harf veya eksi kabul edilemez */
        try {
            $fiyatlar = [];
            foreach (array_keys(Katalog::DONEM_SUTUNLARI) as $sutun) {
                if ($sutun === 'price_biennially' || $sutun === 'price_triennially') {
                    continue; // config_option_values tablosunda bu sütunlar yok
                }
                $fiyatlar[$sutun] = Katalog::fiyatOku($_POST[$sutun] ?? '') ?? 0.0;
            }
            $fiyatlar['setup_fee'] = Katalog::fiyatOku($_POST['setup_fee'] ?? '') ?? 0.0;
        } catch (RuntimeException $e) {
            secenekDon('error', $e->getMessage(), (int) $secenek['group_id']);
        }

        $veri = array_merge([
            'name' => $ad,
            'order_priority' => $sira,
            'is_default' => $varsayilan,
        ], $fiyatlar);

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            /* Varsayılan tektir; önce diğerlerini temizle */
            if ($varsayilan === 1) {
                Database::query(
                    "UPDATE config_option_values SET is_default = 0 WHERE option_id = ?",
                    [$secenekId]
                );
            }

            if ($degerId > 0) {
                Database::update('config_option_values', $veri, 'id = ? AND option_id = ?',
                    [$degerId, $secenekId]);
            } else {
                Database::insert('config_option_values',
                    array_merge(['option_id' => $secenekId], $veri));
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Seçenek değeri kaydedilemedi: ' . $e->getMessage());
            secenekDon('error', 'Değer kaydedilemedi.', (int) $secenek['group_id']);
        }

        secenekDon('success', $ad . ' kaydedildi.', (int) $secenek['group_id']);
    }

    if ($islem === 'deger_sil') {
        $degerId = (int) ($_POST['value_id'] ?? 0);
        $deger = Database::fetch(
            "SELECT v.name, o.group_id
               FROM config_option_values v
               JOIN config_options o ON o.id = v.option_id
              WHERE v.id = ?",
            [$degerId]
        );

        if (!$deger) {
            secenekDon('error', 'Değer bulunamadı.', $grupId);
        }

        Database::query("DELETE FROM config_option_values WHERE id = ?", [$degerId]);
        secenekDon('success', $deger['name'] . ' silindi.', (int) $deger['group_id']);
    }

    secenekDon('error', 'Tanımsız işlem.', $grupId);
}

$mesaj = null;
if (!empty($_SESSION['sc_mesaj'])) {
    $mesaj = $_SESSION['sc_mesaj'];
    unset($_SESSION['sc_mesaj']);
}

$gruplar = Database::fetchAll(
    "SELECT g.*,
            (SELECT COUNT(*) FROM config_options o WHERE o.group_id = g.id) AS secenek_sayisi,
            (SELECT COUNT(*) FROM product_config_links l WHERE l.group_id = g.id) AS urun_sayisi
       FROM config_option_groups g
      ORDER BY g.name"
);

$acikGrup = (int) ($_GET['grup'] ?? 0);
if ($acikGrup === 0 && $gruplar) {
    $acikGrup = (int) $gruplar[0]['id'];
}

$grup = null;
$secenekler = [];
$bagliUrunler = [];

if ($acikGrup > 0) {
    $grup = Database::fetch("SELECT * FROM config_option_groups WHERE id = ?", [$acikGrup]);

    if ($grup) {
        $secenekler = Database::fetchAll(
            "SELECT * FROM config_options WHERE group_id = ? ORDER BY order_priority, name",
            [$acikGrup]
        );

        foreach ($secenekler as $i => $s) {
            $secenekler[$i]['degerler'] = Database::fetchAll(
                "SELECT * FROM config_option_values WHERE option_id = ? ORDER BY order_priority, id",
                [(int) $s['id']]
            );
        }

        $bagliUrunler = Database::fetchAll(
            "SELECT p.id, p.name
               FROM product_config_links l
               JOIN products p ON p.id = l.product_id
              WHERE l.group_id = ?
              ORDER BY p.name",
            [$acikGrup]
        );
    }
}

$duzenlenenSecenek = null;
if (!empty($_GET['secenek'])) {
    foreach ($secenekler as $s) {
        if ((int) $s['id'] === (int) $_GET['secenek']) {
            $duzenlenenSecenek = $s;
            break;
        }
    }
}

function scPara(mixed $t): string
{
    return number_format((float) $t, 2, ',', '.') . ' ₺';
}

$pageTitle = 'Yapılandırma seçenekleri';
$currentPage = 'config-options';
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* ==========================================
       Yapılandırma seçenekleri - sc
       ========================================== */
    .sc-duzen {
        display: grid;
        grid-template-columns: 270px minmax(0, 1fr);
        gap: 18px;
        align-items: start;
    }

    .sc-panel {
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .sc-panel + .sc-panel {
        margin-top: 18px;
    }

    .sc-bas {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 16px;
        border-bottom: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
    }

    .sc-bas h3 {
        font-size: 12.5px;
        font-weight: 700;
    }

    .sc-bas span {
        font-size: 12px;
        color: var(--y-metin-3);
    }

    .sc-govde {
        padding: 16px;
    }

    /* Sol: grup listesi */
    .sc-grup {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 11px 16px;
        border-bottom: 1px solid var(--y-cizgi-soft);
        color: var(--y-metin-2);
        font-size: 13px;
    }

    .sc-grup:last-of-type {
        border-bottom: none;
    }

    .sc-grup:hover {
        background: var(--y-yuzey-2);
    }

    .sc-grup.acik {
        background: var(--y-primary-soft);
        color: var(--y-primary);
        font-weight: 600;
    }

    .sc-grup b {
        display: block;
        font-weight: inherit;
    }

    .sc-grup small {
        display: block;
        margin-top: 2px;
        font-size: 11px;
        color: var(--y-metin-3);
    }

    .sc-yeni-grup {
        padding: 14px 16px;
        border-top: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
    }

    .sc-yeni-grup input {
        margin-bottom: 8px;
    }

    .sc-yeni-grup .btn {
        width: 100%;
        justify-content: center;
    }

    /* Sağ: seçenekler */
    .sc-secenek {
        border-bottom: 1px solid var(--y-cizgi);
    }

    .sc-secenek:last-child {
        border-bottom: none;
    }

    .sc-secenek-bas {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 13px 16px;
    }

    .sc-secenek-bas b {
        font-size: 13.5px;
        font-weight: 600;
        color: var(--y-metin);
    }

    .sc-secenek-bas > div {
        flex: 1;
        min-width: 0;
    }

    .sc-etiketler {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 4px;
    }

    .sc-etiket {
        padding: 2px 8px;
        border: 1px solid var(--y-cizgi);
        border-radius: 999px;
        font-size: 11px;
        color: var(--y-metin-3);
    }

    .sc-eylem {
        display: flex;
        gap: 6px;
        flex-shrink: 0;
    }

    .sc-eylem form {
        margin: 0;
    }

    .sc-dugme {
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

    .sc-dugme:hover {
        border-color: var(--y-primary);
        color: var(--y-primary);
    }

    .sc-dugme.tehlike:hover {
        border-color: var(--y-danger);
        color: var(--y-danger);
    }

    .sc-degerler {
        padding: 0 16px 14px 16px;
    }

    .sc-deger {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 9px 12px;
        border: 1px solid var(--y-cizgi-soft);
        border-radius: 9px;
        background: var(--y-zemin);
        font-size: 12.5px;
    }

    .sc-deger + .sc-deger {
        margin-top: 7px;
    }

    .sc-deger > span:first-child {
        flex: 1;
        color: var(--y-metin);
        font-weight: 500;
    }

    .sc-deger-fiyat {
        color: var(--y-metin-2);
        white-space: nowrap;
    }

    .sc-varsayilan {
        padding: 1px 7px;
        border-radius: 999px;
        background: var(--y-primary);
        color: #fff;
        font-size: 10px;
        font-weight: 700;
    }

    .sc-form {
        padding: 14px 16px;
        border-top: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
    }

    .sc-form-satir {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 10px;
        margin-bottom: 10px;
    }

    .sc-alan label {
        display: block;
        margin-bottom: 4px;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .sc-onay {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 12.5px;
        color: var(--y-metin-2);
        cursor: pointer;
    }

    .sc-form-alt {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }

    .sc-bos {
        padding: 36px 16px;
        text-align: center;
        color: var(--y-metin-3);
        font-size: 13px;
    }

    .sc-bos i {
        display: block;
        margin-bottom: 10px;
        font-size: 26px;
        opacity: .4;
    }

    .sc-urunler {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
    }

    .sc-urun {
        padding: 4px 10px;
        border: 1px solid var(--y-cizgi);
        border-radius: 999px;
        font-size: 12px;
        color: var(--y-metin-2);
    }

    .sc-urun:hover {
        border-color: var(--y-primary);
        color: var(--y-primary);
    }

    @media (max-width: 900px) {
        .sc-duzen {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>Yapılandırma seçenekleri</h1>
        <p>Ürüne eklenebilen ek seçenekler ve fiyatları</p>
    </div>
</div>

<?php if ($mesaj): ?>
    <div class="alert alert-<?= htmlspecialchars($mesaj['tip']) ?>">
        <?= htmlspecialchars($mesaj['metin']) ?>
    </div>
<?php endif; ?>

<div class="sc-duzen">
    <!-- Gruplar -->
    <div class="sc-panel">
        <div class="sc-bas">
            <h3>Seçenek grupları</h3>
            <span><?= count($gruplar) ?></span>
        </div>

        <?php if (!$gruplar): ?>
            <div class="sc-bos" style="padding:24px 16px">Henüz grup yok.</div>
        <?php else: ?>
            <?php foreach ($gruplar as $g): ?>
                <a href="config-options.php?grup=<?= (int) $g['id'] ?>"
                    class="sc-grup <?= $acikGrup === (int) $g['id'] ? 'acik' : '' ?>">
                    <span>
                        <b><?= htmlspecialchars((string) $g['name']) ?></b>
                        <small><?= (int) $g['secenek_sayisi'] ?> seçenek
                            &middot; <?= (int) $g['urun_sayisi'] ?> ürün</small>
                    </span>
                    <i class="fas fa-chevron-right" style="font-size:10px"></i>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="POST" class="sc-yeni-grup">
            <input type="hidden" name="islem" value="grup_kaydet">
            <input type="text" name="name" class="form-control" required maxlength="255"
                placeholder="Yeni grup adı">
            <button type="submit" class="btn btn-outline">
                <i class="fas fa-plus"></i> Grup ekle
            </button>
        </form>
    </div>

    <!-- Seçilen grup -->
    <div>
        <?php if (!$grup): ?>
            <div class="sc-panel">
                <div class="sc-bos">
                    <i class="fas fa-sliders"></i>
                    Soldan bir grup seçin ya da yeni grup ekleyin.
                </div>
            </div>
        <?php else: ?>
            <div class="sc-panel">
                <div class="sc-bas">
                    <h3><?= htmlspecialchars((string) $grup['name']) ?></h3>
                    <form method="POST" style="margin:0"
                        onsubmit="return confirm('Grup ve içindeki tüm seçenekler silinecek. Emin misiniz?')">
                        <input type="hidden" name="islem" value="grup_sil">
                        <input type="hidden" name="group_id" value="<?= (int) $grup['id'] ?>">
                        <button type="submit" class="sc-dugme tehlike" title="Grubu sil">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>

                <form method="POST" class="sc-govde">
                    <input type="hidden" name="islem" value="grup_kaydet">
                    <input type="hidden" name="group_id" value="<?= (int) $grup['id'] ?>">
                    <div class="sc-form-satir">
                        <div class="sc-alan">
                            <label for="grup_ad">Grup adı</label>
                            <input type="text" name="name" id="grup_ad" class="form-control" required
                                maxlength="255" value="<?= htmlspecialchars((string) $grup['name']) ?>">
                        </div>
                        <div class="sc-alan">
                            <label for="grup_aciklama">Açıklama</label>
                            <input type="text" name="description" id="grup_aciklama" class="form-control"
                                maxlength="500"
                                value="<?= htmlspecialchars((string) ($grup['description'] ?? '')) ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline btn-sm">Grubu kaydet</button>
                </form>

                <?php if ($bagliUrunler): ?>
                    <div class="sc-govde" style="border-top:1px solid var(--y-cizgi)">
                        <label style="display:block;margin-bottom:7px;font-size:11.5px;color:var(--y-metin-3)">
                            Bu grubu kullanan ürünler
                        </label>
                        <div class="sc-urunler">
                            <?php foreach ($bagliUrunler as $u): ?>
                                <a href="product-edit.php?id=<?= (int) $u['id'] ?>" class="sc-urun">
                                    <?= htmlspecialchars((string) $u['name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="sc-panel">
                <div class="sc-bas">
                    <h3>Seçenekler</h3>
                    <span><?= count($secenekler) ?></span>
                </div>

                <?php if (!$secenekler): ?>
                    <div class="sc-bos">
                        <i class="fas fa-list-check"></i>
                        Bu grupta seçenek yok. Aşağıdan ekleyin.
                    </div>
                <?php endif; ?>

                <?php foreach ($secenekler as $s):
                    $duzenle = $duzenlenenSecenek && (int) $duzenlenenSecenek['id'] === (int) $s['id'];
                    ?>
                    <div class="sc-secenek">
                        <div class="sc-secenek-bas">
                            <div>
                                <b><?= htmlspecialchars((string) $s['name']) ?></b>
                                <div class="sc-etiketler">
                                    <span class="sc-etiket"><?= SC_TURLER[$s['option_type']] ?? htmlspecialchars((string) $s['option_type']) ?></span>
                                    <?php if ((int) $s['required'] === 1): ?>
                                        <span class="sc-etiket">Zorunlu</span>
                                    <?php endif; ?>
                                    <span class="sc-etiket"><?= count($s['degerler']) ?> değer</span>
                                </div>
                            </div>
                            <div class="sc-eylem">
                                <a href="config-options.php?grup=<?= $acikGrup ?>&secenek=<?= (int) $s['id'] ?>"
                                    class="sc-dugme" title="Düzenle"><i class="fas fa-pen"></i></a>
                                <form method="POST"
                                    onsubmit="return confirm('<?= htmlspecialchars(addslashes((string) $s['name'])) ?> ve değerleri silinecek. Emin misiniz?')">
                                    <input type="hidden" name="islem" value="secenek_sil">
                                    <input type="hidden" name="option_id" value="<?= (int) $s['id'] ?>">
                                    <button type="submit" class="sc-dugme tehlike" title="Sil">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <?php if ($s['degerler']): ?>
                            <div class="sc-degerler">
                                <?php foreach ($s['degerler'] as $d): ?>
                                    <div class="sc-deger">
                                        <span><?= htmlspecialchars((string) $d['name']) ?></span>
                                        <?php if ((int) $d['is_default'] === 1): ?>
                                            <span class="sc-varsayilan">Varsayılan</span>
                                        <?php endif; ?>
                                        <span class="sc-deger-fiyat">
                                            <?= scPara($d['price_monthly']) ?>/ay
                                            <?php if ((float) $d['setup_fee'] > 0): ?>
                                                &middot; kurulum <?= scPara($d['setup_fee']) ?>
                                            <?php endif; ?>
                                        </span>
                                        <form method="POST"
                                            onsubmit="return confirm('<?= htmlspecialchars(addslashes((string) $d['name'])) ?> silinecek. Emin misiniz?')">
                                            <input type="hidden" name="islem" value="deger_sil">
                                            <input type="hidden" name="value_id" value="<?= (int) $d['id'] ?>">
                                            <button type="submit" class="sc-dugme tehlike" title="Sil">
                                                <i class="fas fa-xmark"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($duzenle): ?>
                            <form method="POST" class="sc-form">
                                <input type="hidden" name="islem" value="secenek_kaydet">
                                <input type="hidden" name="option_id" value="<?= (int) $s['id'] ?>">
                                <div class="sc-form-satir">
                                    <div class="sc-alan">
                                        <label>Seçenek adı</label>
                                        <input type="text" name="name" class="form-control" required
                                            maxlength="255" value="<?= htmlspecialchars((string) $s['name']) ?>">
                                    </div>
                                    <div class="sc-alan">
                                        <label>Tür</label>
                                        <select name="option_type" class="form-control">
                                            <?php foreach (SC_TURLER as $deger => $ad): ?>
                                                <option value="<?= $deger ?>"
                                                    <?= $s['option_type'] === $deger ? 'selected' : '' ?>><?= $ad ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="sc-alan">
                                        <label>Sıra</label>
                                        <input type="number" name="order_priority" class="form-control"
                                            min="0" max="999" value="<?= (int) $s['order_priority'] ?>">
                                    </div>
                                </div>
                                <div class="sc-form-alt">
                                    <label class="sc-onay">
                                        <input type="checkbox" name="required" value="1"
                                            <?= (int) $s['required'] === 1 ? 'checked' : '' ?>>
                                        Müşteri seçmek zorunda
                                    </label>
                                    <div style="display:flex;gap:8px">
                                        <a href="config-options.php?grup=<?= $acikGrup ?>"
                                            class="btn btn-sm btn-outline">Vazgeç</a>
                                        <button type="submit" class="btn btn-sm btn-primary">Kaydet</button>
                                    </div>
                                </div>
                            </form>

                            <form method="POST" class="sc-form" style="border-top:1px dashed var(--y-cizgi)">
                                <input type="hidden" name="islem" value="deger_kaydet">
                                <input type="hidden" name="option_id" value="<?= (int) $s['id'] ?>">
                                <div class="sc-form-satir">
                                    <div class="sc-alan">
                                        <label>Yeni değer</label>
                                        <input type="text" name="name" class="form-control" required
                                            maxlength="255" placeholder="32 GB RAM">
                                    </div>
                                    <div class="sc-alan">
                                        <label>Aylık</label>
                                        <input type="text" name="price_monthly" class="form-control"
                                            inputmode="decimal" placeholder="0">
                                    </div>
                                    <div class="sc-alan">
                                        <label>3 aylık</label>
                                        <input type="text" name="price_quarterly" class="form-control"
                                            inputmode="decimal" placeholder="0">
                                    </div>
                                    <div class="sc-alan">
                                        <label>6 aylık</label>
                                        <input type="text" name="price_semiannually" class="form-control"
                                            inputmode="decimal" placeholder="0">
                                    </div>
                                    <div class="sc-alan">
                                        <label>Yıllık</label>
                                        <input type="text" name="price_annually" class="form-control"
                                            inputmode="decimal" placeholder="0">
                                    </div>
                                    <div class="sc-alan">
                                        <label>Kurulum</label>
                                        <input type="text" name="setup_fee" class="form-control"
                                            inputmode="decimal" placeholder="0">
                                    </div>
                                </div>
                                <div class="sc-form-alt">
                                    <label class="sc-onay">
                                        <input type="checkbox" name="is_default" value="1">
                                        Varsayılan seçili gelsin
                                    </label>
                                    <button type="submit" class="btn btn-sm btn-outline">Değer ekle</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <form method="POST" class="sc-form">
                    <input type="hidden" name="islem" value="secenek_kaydet">
                    <input type="hidden" name="group_id" value="<?= (int) $grup['id'] ?>">
                    <div class="sc-form-satir">
                        <div class="sc-alan">
                            <label for="yeni_secenek">Yeni seçenek</label>
                            <input type="text" name="name" id="yeni_secenek" class="form-control" required
                                maxlength="255" placeholder="Ek RAM">
                        </div>
                        <div class="sc-alan">
                            <label for="yeni_tur">Tür</label>
                            <select name="option_type" id="yeni_tur" class="form-control">
                                <?php foreach (SC_TURLER as $deger => $ad): ?>
                                    <option value="<?= $deger ?>"><?= $ad ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="sc-form-alt">
                        <label class="sc-onay">
                            <input type="checkbox" name="required" value="1">
                            Müşteri seçmek zorunda
                        </label>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Seçenek ekle
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
