<?php
/**
 * VHM - Admin Bilgi Bankası
 *
 * bilgi-bankasi.php sayfasındaki kategoriler ve makaleler buradan yönetilir.
 * Makale metni kaydedilmeden önce GuvenliHtml ile temizlenir; beyaz listede
 * olmayan etiket ve öznitelikler atılır.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/GuvenliHtml.php';

require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Bilgi Bankası';
$currentPage = 'bilgi-bankasi';

/** Türkçe harfleri de çeviren slug üretici */
function kbSlug(string $metin): string
{
    $metin = str_replace(
        ['ç', 'Ç', 'ğ', 'Ğ', 'ı', 'I', 'İ', 'ö', 'Ö', 'ş', 'Ş', 'ü', 'Ü'],
        ['c', 'c', 'g', 'g', 'i', 'i', 'i', 'o', 'o', 's', 's', 'u', 'u'],
        $metin
    );
    $metin = mb_strtolower($metin, 'UTF-8');
    $metin = preg_replace('/[^a-z0-9]+/', '-', $metin) ?? '';
    return trim($metin, '-');
}

/** Aynı slug varsa sonuna sayı ekler */
function kbTekSlug(string $tablo, string $slug, int $haric = 0): string
{
    $slug = $slug !== '' ? $slug : 'kayit';
    $aday = $slug;
    $n = 2;
    while (true) {
        $var = Database::fetch(
            "SELECT id FROM `{$tablo}` WHERE slug = ? AND id <> ?",
            [$aday, $haric]
        );
        if (!$var) {
            return $aday;
        }
        $aday = $slug . '-' . $n++;
    }
}

$mesaj = '';
$mesajTipi = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $islem = (string) ($_POST['islem'] ?? '');

    try {
        if ($islem === 'kategori_kaydet') {
            $id = (int) ($_POST['id'] ?? 0);
            $ad = mb_substr(trim((string) ($_POST['ad'] ?? '')), 0, 80);

            if ($ad === '') {
                throw new RuntimeException('Kategori adı boş olamaz.');
            }

            $veri = [
                'ad' => $ad,
                'ikon' => mb_substr(trim((string) ($_POST['ikon'] ?? 'fa-book')), 0, 40) ?: 'fa-book',
                'aciklama' => mb_substr(trim((string) ($_POST['aciklama'] ?? '')), 0, 200),
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];

            $slug = kbSlug((string) ($_POST['slug'] ?? '') !== '' ? (string) $_POST['slug'] : $ad);
            $veri['slug'] = kbTekSlug('kb_kategoriler', $slug, $id);

            if ($id > 0) {
                Database::update('kb_kategoriler', $veri, 'id = ?', [$id]);
                $mesaj = 'Kategori güncellendi.';
            } else {
                Database::insert('kb_kategoriler', $veri);
                $mesaj = 'Kategori eklendi.';
            }
            $mesajTipi = 'success';

        } elseif ($islem === 'kategori_sil') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $adet = (int) Database::fetchColumn(
                    "SELECT COUNT(*) FROM kb_makaleler WHERE kategori_id = ?",
                    [$id]
                );
                if ($adet > 0) {
                    throw new RuntimeException(
                        'Bu kategoride ' . $adet . ' makale var. Önce onları taşıyın ya da silin.'
                    );
                }
                Database::delete('kb_kategoriler', 'id = ?', [$id]);
                $mesaj = 'Kategori silindi.';
                $mesajTipi = 'success';
            }

        } elseif ($islem === 'makale_kaydet') {
            $id = (int) ($_POST['id'] ?? 0);
            $baslik = mb_substr(trim((string) ($_POST['baslik'] ?? '')), 0, 180);
            $kategoriId = (int) ($_POST['kategori_id'] ?? 0);
            $hamIcerik = (string) ($_POST['icerik'] ?? '');

            if ($baslik === '') {
                throw new RuntimeException('Makale başlığı boş olamaz.');
            }
            if ($kategoriId <= 0 || !Database::fetch("SELECT id FROM kb_kategoriler WHERE id = ?", [$kategoriId])) {
                throw new RuntimeException('Geçerli bir kategori seçin.');
            }

            $icerik = GuvenliHtml::temizle($hamIcerik);
            if ($icerik === '') {
                throw new RuntimeException('Makale metni boş olamaz.');
            }

            $ozet = mb_substr(trim((string) ($_POST['ozet'] ?? '')), 0, 300);
            if ($ozet === '') {
                $ozet = GuvenliHtml::ozet($icerik, 190);
            }

            $veri = [
                'kategori_id' => $kategoriId,
                'baslik' => $baslik,
                'ozet' => $ozet,
                'icerik' => $icerik,
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];

            $slug = kbSlug((string) ($_POST['slug'] ?? '') !== '' ? (string) $_POST['slug'] : $baslik);
            $veri['slug'] = kbTekSlug('kb_makaleler', $slug, $id);

            if ($id > 0) {
                Database::update('kb_makaleler', $veri, 'id = ?', [$id]);
                $mesaj = 'Makale güncellendi.';
            } else {
                Database::insert('kb_makaleler', $veri);
                $mesaj = 'Makale eklendi.';
            }

            /* Temizleyici bir şeyler attıysa haber ver */
            if (strlen($hamIcerik) - strlen($icerik) > 20) {
                $mesaj .= ' Metindeki izin verilmeyen etiketler temizlendi.';
            }
            $mesajTipi = 'success';

        } elseif ($islem === 'makale_sil') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                Database::delete('kb_makaleler', 'id = ?', [$id]);
                $mesaj = 'Makale silindi.';
                $mesajTipi = 'success';
            }
        }
    } catch (Throwable $e) {
        $mesaj = $e->getMessage();
        $mesajTipi = 'error';
    }
}

$kategoriler = Database::fetchAll(
    "SELECT k.*, (SELECT COUNT(*) FROM kb_makaleler m WHERE m.kategori_id = k.id) AS adet
       FROM kb_kategoriler k ORDER BY k.sort_order, k.ad"
);

$duzenle = null;
$duzenleTur = '';
if (isset($_GET['makale'])) {
    $duzenle = Database::fetch("SELECT * FROM kb_makaleler WHERE id = ?", [(int) $_GET['makale']]);
    $duzenleTur = 'makale';
} elseif (isset($_GET['kategori'])) {
    $duzenle = Database::fetch("SELECT * FROM kb_kategoriler WHERE id = ?", [(int) $_GET['kategori']]);
    $duzenleTur = 'kategori';
}

$filtre = (int) ($_GET['k'] ?? 0);
$kosul = $filtre > 0 ? ' WHERE m.kategori_id = ' . $filtre : '';
$makaleler = Database::fetchAll(
    "SELECT m.*, k.ad AS kategori_ad FROM kb_makaleler m
       JOIN kb_kategoriler k ON k.id = m.kategori_id
       {$kosul}
      ORDER BY k.sort_order, m.sort_order, m.baslik"
);

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .bb-kutu {
        margin-bottom: 20px;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: #fff;
        overflow: hidden;
    }

    .bb-kutu summary,
    .bb-kutu-basluk {
        padding: 13px 16px;
        border-bottom: 1px solid #e5e7eb;
        background: #f9fafb;
        font-size: 13px;
        font-weight: 700;
        color: #374151;
        cursor: pointer;
        list-style: none;
    }

    .bb-kutu summary::-webkit-details-marker {
        display: none;
    }

    .bb-kutu summary::before {
        content: '▸ ';
    }

    .bb-kutu[open] summary::before {
        content: '▾ ';
    }

    .bb-govde {
        padding: 16px;
    }

    .bb-alan {
        margin-bottom: 13px;
    }

    .bb-alan label {
        display: block;
        margin-bottom: 5px;
        font-size: 12px;
        font-weight: 600;
        color: #374151;
    }

    .bb-alan input[type="text"],
    .bb-alan input[type="number"],
    .bb-alan select,
    .bb-alan textarea {
        width: 100%;
        padding: 8px 11px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        font-size: 13px;
        font-family: inherit;
    }

    .bb-alan textarea {
        min-height: 300px;
        line-height: 1.6;
        font-family: ui-monospace, Consolas, monospace;
        font-size: 12.5px;
    }

    .bb-alan small {
        display: block;
        margin-top: 5px;
        font-size: 11px;
        line-height: 1.5;
        color: #6b7280;
    }

    .bb-satir {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 13px;
    }

    .bb-onay {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: #374151;
    }

    .bb-alt {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        margin-top: 14px;
    }

    .bb-tablo {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .bb-tablo th,
    .bb-tablo td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid #f1f3f5;
        vertical-align: middle;
    }

    .bb-tablo thead th {
        background: #f9fafb;
        font-size: 11px;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .bb-tablo tr:last-child td {
        border-bottom: 0;
    }

    .bb-rozet {
        display: inline-block;
        padding: 2px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
    }

    .bb-rozet.acik {
        background: #dcfce7;
        color: #15803d;
    }

    .bb-rozet.kapali {
        background: #f3f4f6;
        color: #6b7280;
    }

    .bb-rozet.kategori {
        background: #eff5ff;
        color: #1d4ed8;
    }

    .bb-eylem {
        display: flex;
        gap: 6px;
    }

    .bb-eylem a,
    .bb-eylem button {
        padding: 5px 10px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        background: #fff;
        font-size: 12px;
        color: #374151;
        text-decoration: none;
        cursor: pointer;
    }

    .bb-eylem a:hover {
        border-color: #2474f5;
        color: #1d4ed8;
    }

    .bb-eylem button:hover {
        border-color: #ef4444;
        color: #ef4444;
    }

    .bb-filtre {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
    }

    .bb-filtre a {
        padding: 7px 13px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #fff;
        font-size: 12.5px;
        font-weight: 600;
        color: #374151;
        text-decoration: none;
    }

    .bb-filtre a.is-active {
        border-color: #2474f5;
        background: #eff5ff;
        color: #1d4ed8;
    }
</style>

<div class="page-header">
    <h1>Bilgi Bankası</h1>
    <p>Sitedeki bilgi bankası kategorileri ve makaleleri.</p>
</div>

<?php if ($mesaj !== ''): ?>
    <div class="alert alert-<?= htmlspecialchars($mesajTipi) ?>">
        <?= htmlspecialchars($mesaj) ?>
    </div>
<?php endif; ?>

<!-- Kategoriler -->
<details class="bb-kutu" <?= $duzenleTur === 'kategori' ? 'open' : '' ?>>
    <summary>Kategoriler (<?= count($kategoriler) ?>)</summary>
    <div class="bb-govde">
        <table class="bb-tablo" style="margin-bottom: 18px;">
            <thead>
                <tr>
                    <th>Ad</th>
                    <th>Slug</th>
                    <th>İkon</th>
                    <th>Makale</th>
                    <th>Sıra</th>
                    <th>Durum</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kategoriler as $k): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars((string) $k['ad']) ?></strong></td>
                        <td style="color: #6b7280;"><?= htmlspecialchars((string) $k['slug']) ?></td>
                        <td><i class="fas <?= htmlspecialchars((string) $k['ikon']) ?>"></i></td>
                        <td><?= (int) $k['adet'] ?></td>
                        <td><?= (int) $k['sort_order'] ?></td>
                        <td>
                            <span class="bb-rozet <?= (int) $k['is_active'] === 1 ? 'acik' : 'kapali' ?>">
                                <?= (int) $k['is_active'] === 1 ? 'Yayında' : 'Kapalı' ?>
                            </span>
                        </td>
                        <td>
                            <div class="bb-eylem">
                                <a href="?kategori=<?= (int) $k['id'] ?>">Düzenle</a>
                                <form method="post"
                                    onsubmit="return confirm('<?= htmlspecialchars((string) $k['ad']) ?> silinsin mi?');">
                                    <input type="hidden" name="islem" value="kategori_sil">
                                    <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                                    <button type="submit">Sil</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <form method="post">
            <input type="hidden" name="islem" value="kategori_kaydet">
            <input type="hidden" name="id"
                value="<?= $duzenleTur === 'kategori' ? (int) $duzenle['id'] : 0 ?>">

            <div class="bb-satir">
                <div class="bb-alan">
                    <label for="kat_ad">Kategori adı</label>
                    <input type="text" id="kat_ad" name="ad" required
                        value="<?= $duzenleTur === 'kategori' ? htmlspecialchars((string) $duzenle['ad']) : '' ?>">
                </div>
                <div class="bb-alan">
                    <label for="kat_slug">Slug</label>
                    <input type="text" id="kat_slug" name="slug"
                        value="<?= $duzenleTur === 'kategori' ? htmlspecialchars((string) $duzenle['slug']) : '' ?>"
                        placeholder="boş bırakılırsa addan üretilir">
                </div>
                <div class="bb-alan">
                    <label for="kat_ikon">İkon</label>
                    <input type="text" id="kat_ikon" name="ikon"
                        value="<?= $duzenleTur === 'kategori' ? htmlspecialchars((string) $duzenle['ikon']) : 'fa-book' ?>"
                        placeholder="fa-server">
                    <small>Font Awesome sınıf adı</small>
                </div>
                <div class="bb-alan">
                    <label for="kat_sira">Sıra</label>
                    <input type="number" id="kat_sira" name="sort_order" step="1"
                        value="<?= $duzenleTur === 'kategori' ? (int) $duzenle['sort_order'] : 0 ?>">
                </div>
            </div>

            <div class="bb-alan">
                <label for="kat_aciklama">Açıklama</label>
                <input type="text" id="kat_aciklama" name="aciklama" maxlength="200"
                    value="<?= $duzenleTur === 'kategori' ? htmlspecialchars((string) ($duzenle['aciklama'] ?? '')) : '' ?>">
            </div>

            <div class="bb-alt">
                <label class="bb-onay">
                    <input type="checkbox" name="is_active" value="1"
                        <?= $duzenleTur !== 'kategori' || (int) $duzenle['is_active'] === 1 ? 'checked' : '' ?>>
                    Yayında
                </label>
                <div class="bb-eylem">
                    <?php if ($duzenleTur === 'kategori'): ?>
                        <a href="bilgi-bankasi.php">Vazgeç</a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">
                        <?= $duzenleTur === 'kategori' ? 'Kategoriyi güncelle' : 'Kategori ekle' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</details>

<!-- Makale düzenleyici -->
<details class="bb-kutu" <?= $duzenleTur === 'makale' ? 'open' : '' ?>>
    <summary><?= $duzenleTur === 'makale' ? 'Makaleyi düzenle' : 'Yeni makale' ?></summary>
    <div class="bb-govde">
        <form method="post">
            <input type="hidden" name="islem" value="makale_kaydet">
            <input type="hidden" name="id" value="<?= $duzenleTur === 'makale' ? (int) $duzenle['id'] : 0 ?>">

            <div class="bb-alan">
                <label for="mak_baslik">Başlık</label>
                <input type="text" id="mak_baslik" name="baslik" required maxlength="180"
                    value="<?= $duzenleTur === 'makale' ? htmlspecialchars((string) $duzenle['baslik']) : '' ?>">
            </div>

            <div class="bb-satir">
                <div class="bb-alan">
                    <label for="mak_kategori">Kategori</label>
                    <select id="mak_kategori" name="kategori_id" required>
                        <?php foreach ($kategoriler as $k): ?>
                            <option value="<?= (int) $k['id'] ?>"
                                <?= $duzenleTur === 'makale' && (int) $duzenle['kategori_id'] === (int) $k['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $k['ad']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bb-alan">
                    <label for="mak_slug">Slug</label>
                    <input type="text" id="mak_slug" name="slug"
                        value="<?= $duzenleTur === 'makale' ? htmlspecialchars((string) $duzenle['slug']) : '' ?>"
                        placeholder="boş bırakılırsa başlıktan üretilir">
                </div>
                <div class="bb-alan">
                    <label for="mak_sira">Sıra</label>
                    <input type="number" id="mak_sira" name="sort_order" step="1"
                        value="<?= $duzenleTur === 'makale' ? (int) $duzenle['sort_order'] : 0 ?>">
                </div>
            </div>

            <div class="bb-alan">
                <label for="mak_ozet">Özet</label>
                <input type="text" id="mak_ozet" name="ozet" maxlength="300"
                    value="<?= $duzenleTur === 'makale' ? htmlspecialchars((string) ($duzenle['ozet'] ?? '')) : '' ?>"
                    placeholder="boş bırakılırsa metinden üretilir">
            </div>

            <div class="bb-alan">
                <label for="mak_icerik">Metin</label>
                <textarea id="mak_icerik" name="icerik"
                    required><?= $duzenleTur === 'makale' ? htmlspecialchars((string) $duzenle['icerik']) : '' ?></textarea>
                <small>
                    İzin verilen etiketler: <code>p h2 h3 h4 strong em u ul ol li a code pre blockquote
                        table thead tbody tr th td img br hr</code>.
                    Bunların dışındaki etiketler ve tüm olay öznitelikleri kaydederken temizlenir;
                    <code>javascript:</code> adresli bağlantılar kaldırılır.
                </small>
            </div>

            <div class="bb-alt">
                <label class="bb-onay">
                    <input type="checkbox" name="is_active" value="1"
                        <?= $duzenleTur !== 'makale' || (int) $duzenle['is_active'] === 1 ? 'checked' : '' ?>>
                    Yayında
                </label>
                <div class="bb-eylem">
                    <?php if ($duzenleTur === 'makale'): ?>
                        <a href="bilgi-bankasi.php">Vazgeç</a>
                        <a href="../bilgi-bankasi.php?makale=<?= urlencode((string) $duzenle['slug']) ?>"
                            target="_blank">Sitede gör</a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">
                        <?= $duzenleTur === 'makale' ? 'Makaleyi güncelle' : 'Makale ekle' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</details>

<!-- Makale listesi -->
<div class="bb-filtre">
    <a href="bilgi-bankasi.php" class="<?= $filtre === 0 ? 'is-active' : '' ?>">
        Tümü <?= count(Database::fetchAll("SELECT id FROM kb_makaleler")) ?>
    </a>
    <?php foreach ($kategoriler as $k): ?>
        <a href="?k=<?= (int) $k['id'] ?>" class="<?= $filtre === (int) $k['id'] ? 'is-active' : '' ?>">
            <?= htmlspecialchars((string) $k['ad']) ?> <?= (int) $k['adet'] ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="bb-kutu">
    <div class="bb-kutu-basluk">Makaleler (<?= count($makaleler) ?>)</div>
    <table class="bb-tablo">
        <thead>
            <tr>
                <th>Başlık</th>
                <th>Kategori</th>
                <th>Görüntülenme</th>
                <th>Güncelleme</th>
                <th>Durum</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$makaleler): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: #6b7280; padding: 30px;">
                        Bu filtreye uyan makale yok.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($makaleler as $m): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars((string) $m['baslik']) ?></strong><br>
                            <span style="color: #6b7280; font-size: 12px;">
                                <?= htmlspecialchars((string) $m['slug']) ?>
                            </span>
                        </td>
                        <td><span class="bb-rozet kategori"><?= htmlspecialchars((string) $m['kategori_ad']) ?></span></td>
                        <td><?= (int) $m['goruntulenme'] ?></td>
                        <td><?= date('d.m.Y', strtotime((string) $m['updated_at'])) ?></td>
                        <td>
                            <span class="bb-rozet <?= (int) $m['is_active'] === 1 ? 'acik' : 'kapali' ?>">
                                <?= (int) $m['is_active'] === 1 ? 'Yayında' : 'Taslak' ?>
                            </span>
                        </td>
                        <td>
                            <div class="bb-eylem">
                                <a href="?makale=<?= (int) $m['id'] ?>">Düzenle</a>
                                <form method="post"
                                    onsubmit="return confirm('Makale silinsin mi? Bu işlem geri alınamaz.');">
                                    <input type="hidden" name="islem" value="makale_sil">
                                    <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                    <button type="submit">Sil</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
