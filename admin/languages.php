<?php
/**
 * WHMVM - Dil ve Çeviri Yönetimi
 *
 * İki sekme:
 *   - Diller: ekle / düzenle / sil, aktif-pasif, varsayılan, sıra
 *   - Çeviriler: anahtar bazlı tablo, her dil için ayrı sütun
 *
 * Çeviri anahtarları ön yüzde Lang::t() çağrıldıkça kendiliğinden oluşur.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Dil Yönetimi';
$currentPage = 'languages';

$mesaj = null;
$mesajTur = 'success';

$sekme = ($_GET['tab'] ?? 'diller') === 'ceviri' ? 'ceviri' : 'diller';

/* ---------------------------------------------------------------- */
/* İşlemler                                                          */
/* ---------------------------------------------------------------- */
try {
    $db = Database::getInstance();

    /* --- dil kaydet (ekle / güncelle) --- */
    if (($_POST['islem'] ?? '') === 'dil-kaydet') {
        $id = (int) ($_POST['id'] ?? 0);
        $kod = strtolower(trim((string) ($_POST['code'] ?? '')));

        if (!preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $kod)) {
            throw new RuntimeException('Dil kodu "tr", "en", "ar" ya da "pt-br" biçiminde olmalı.');
        }

        $veri = [
            'code' => $kod,
            'name' => trim((string) ($_POST['name'] ?? '')),
            'native_name' => trim((string) ($_POST['native_name'] ?? '')),
            'flag' => trim((string) ($_POST['flag'] ?? '')),
            'direction' => ($_POST['direction'] ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        ];

        if ($veri['name'] === '' || $veri['native_name'] === '') {
            throw new RuntimeException('Dil adı ve yerel adı zorunlu.');
        }

        if ($id > 0) {
            Database::update('languages', $veri, 'id = ?', [$id]);
            $mesaj = 'Dil güncellendi.';
        } else {
            $var = Database::fetchColumn("SELECT COUNT(*) FROM languages WHERE code = ?", [$kod]);
            if ((int) $var > 0) {
                throw new RuntimeException('Bu dil kodu zaten kayıtlı.');
            }
            Database::insert('languages', $veri);
            $mesaj = 'Dil eklendi.';
        }
    }

    /* --- varsayılan dil --- */
    if (($_GET['varsayilan'] ?? '') !== '') {
        $id = (int) $_GET['varsayilan'];
        $db->exec("UPDATE languages SET is_default = 0");
        Database::update('languages', ['is_default' => 1, 'is_active' => 1], 'id = ?', [$id]);
        $mesaj = 'Varsayılan dil değiştirildi.';
    }

    /* --- sil --- */
    if (($_GET['sil'] ?? '') !== '') {
        $id = (int) $_GET['sil'];
        $dil = Database::fetch("SELECT code, is_default FROM languages WHERE id = ?", [$id]);
        if (!$dil) {
            throw new RuntimeException('Dil bulunamadı.');
        }
        if ((int) $dil['is_default'] === 1) {
            throw new RuntimeException('Varsayılan dil silinemez. Önce başka bir dili varsayılan yapın.');
        }
        Database::delete('translations', 'lang_code = ?', [$dil['code']]);
        Database::delete('languages', 'id = ?', [$id]);
        $mesaj = 'Dil ve çevirileri silindi.';
    }

    /* --- çeviri kaydet (toplu) --- */
    if (($_POST['islem'] ?? '') === 'ceviri-kaydet') {
        $ceviriler = $_POST['ceviri'] ?? [];
        $stmt = $db->prepare(
            "INSERT INTO translations (lang_code, t_key, t_value) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE t_value = VALUES(t_value)"
        );
        $sayac = 0;
        foreach ($ceviriler as $kod => $satirlar) {
            $kod = (string) $kod;
            foreach ((array) $satirlar as $anahtar => $deger) {
                $stmt->execute([$kod, (string) $anahtar, trim((string) $deger)]);
                $sayac++;
            }
        }
        $mesaj = $sayac . ' çeviri alanı kaydedildi.';
        $sekme = 'ceviri';
    }

    /* --- anahtar sil --- */
    if (($_GET['anahtar-sil'] ?? '') !== '') {
        Database::delete('translations', 't_key = ?', [(string) $_GET['anahtar-sil']]);
        $mesaj = 'Anahtar tüm dillerden silindi.';
        $sekme = 'ceviri';
    }
} catch (Throwable $e) {
    $mesaj = $e->getMessage();
    $mesajTur = 'danger';
}

/* ---------------------------------------------------------------- */
/* Veri                                                              */
/* ---------------------------------------------------------------- */
$diller = Database::fetchAll("SELECT * FROM languages ORDER BY sort_order, id");
$dilKodlari = array_column($diller, 'code');
$duzenlenen = null;
if (($_GET['duzenle'] ?? '') !== '') {
    $duzenlenen = Database::fetch("SELECT * FROM languages WHERE id = ?", [(int) $_GET['duzenle']]);
}

/* Çeviri sekmesi: anahtarlar + sayfalama + arama */
$ara = trim((string) ($_GET['ara'] ?? ''));
$grup = trim((string) ($_GET['grup'] ?? ''));
$sayfa = max(1, (int) ($_GET['sayfa'] ?? 1));
$limit = 40;

$gruplar = Database::fetchAll(
    "SELECT SUBSTRING_INDEX(t_key, '.', 1) AS grup, COUNT(DISTINCT t_key) AS adet
     FROM translations GROUP BY grup ORDER BY grup"
);

$kosul = [];
$parametre = [];
if ($ara !== '') {
    $kosul[] = '(t_key LIKE ? OR t_value LIKE ?)';
    $parametre[] = '%' . $ara . '%';
    $parametre[] = '%' . $ara . '%';
}
if ($grup !== '') {
    $kosul[] = 'SUBSTRING_INDEX(t_key, ".", 1) = ?';
    $parametre[] = $grup;
}
$where = $kosul ? 'WHERE ' . implode(' AND ', $kosul) : '';

$toplamAnahtar = (int) Database::fetchColumn(
    "SELECT COUNT(DISTINCT t_key) FROM translations $where",
    $parametre
);
$toplamSayfa = max(1, (int) ceil($toplamAnahtar / $limit));
$sayfa = min($sayfa, $toplamSayfa);
$offset = ($sayfa - 1) * $limit;

$anahtarlar = Database::fetchAll(
    "SELECT DISTINCT t_key FROM translations $where ORDER BY t_key LIMIT $limit OFFSET $offset",
    $parametre
);
$anahtarListe = array_column($anahtarlar, 't_key');

/* Seçili anahtarların tüm dillerdeki değerleri */
$degerler = [];
if ($anahtarListe) {
    $yerTutucu = implode(',', array_fill(0, count($anahtarListe), '?'));
    foreach (Database::fetchAll(
        "SELECT lang_code, t_key, t_value FROM translations WHERE t_key IN ($yerTutucu)",
        $anahtarListe
    ) as $r) {
        $degerler[$r['t_key']][$r['lang_code']] = $r['t_value'];
    }
}

include 'includes/header.php';
?>

<style>
    .dil-bayrak {
        font-size: 20px;
        line-height: 1;
    }

    .sekme-bar {
        display: flex;
        gap: 8px;
        margin-bottom: 20px;
    }

    .sekme-bar a {
        padding: 9px 18px;
        border-radius: 10px;
        border: 1px solid var(--border, #e5e7eb);
        font-size: 14px;
        font-weight: 600;
        color: inherit;
        text-decoration: none;
    }

    .sekme-bar a.aktif {
        background: #2474f5;
        border-color: transparent;
        color: #fff;
    }

    .ceviri-arac {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        margin-bottom: 16px;
    }

    .ceviri-tablo textarea {
        width: 100%;
        min-height: 46px;
        padding: 8px 10px;
        border: 1px solid var(--border, #e5e7eb);
        border-radius: 8px;
        font-family: inherit;
        font-size: 13px;
        line-height: 1.45;
        resize: vertical;
    }

    .ceviri-tablo textarea[dir="rtl"] {
        text-align: right;
    }

    .anahtar-hucre {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 12px;
        white-space: nowrap;
        vertical-align: top;
        padding-top: 14px !important;
    }

    .sayfalama {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 16px;
    }

    .sayfalama a,
    .sayfalama span {
        min-width: 34px;
        padding: 6px 10px;
        text-align: center;
        border: 1px solid var(--border, #e5e7eb);
        border-radius: 8px;
        font-size: 13px;
        text-decoration: none;
        color: inherit;
    }

    .sayfalama .aktif {
        background: #2474f5;
        border-color: transparent;
        color: #fff;
    }
</style>

<div class="page-header" style="margin-bottom:20px">
    <h1 style="font-size:22px;font-weight:700">🌐 Dil Yönetimi</h1>
</div>

<?php if ($mesaj): ?>
    <div class="alert alert-<?= $mesajTur === 'danger' ? 'danger' : 'success' ?>" style="margin-bottom:20px">
        <?= htmlspecialchars($mesaj) ?>
    </div>
<?php endif; ?>

<div class="sekme-bar">
    <a href="?tab=diller" class="<?= $sekme === 'diller' ? 'aktif' : '' ?>">Diller (<?= count($diller) ?>)</a>
    <a href="?tab=ceviri" class="<?= $sekme === 'ceviri' ? 'aktif' : '' ?>">Çeviriler (<?= $toplamAnahtar ?>)</a>
</div>

<?php if ($sekme === 'diller'): ?>

    <div class="card" style="margin-bottom:24px">
        <div class="card-header">
            <h3><?= $duzenlenen ? 'Dili Düzenle' : 'Yeni Dil Ekle' ?></h3>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="islem" value="dil-kaydet">
                <input type="hidden" name="id" value="<?= (int) ($duzenlenen['id'] ?? 0) ?>">

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px">
                    <div>
                        <label class="form-label">Dil kodu</label>
                        <input type="text" name="code" class="form-input" required maxlength="5" placeholder="tr"
                            value="<?= htmlspecialchars($duzenlenen['code'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label">Adı (yönetim)</label>
                        <input type="text" name="name" class="form-input" required placeholder="Türkçe"
                            value="<?= htmlspecialchars($duzenlenen['name'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label">Yerel adı (menüde)</label>
                        <input type="text" name="native_name" class="form-input" required placeholder="Türkçe"
                            value="<?= htmlspecialchars($duzenlenen['native_name'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label">Bayrak</label>
                        <input type="text" name="flag" class="form-input" maxlength="16" placeholder="🇹🇷"
                            value="<?= htmlspecialchars($duzenlenen['flag'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label">Yazım yönü</label>
                        <select name="direction" class="form-input">
                            <option value="ltr" <?= ($duzenlenen['direction'] ?? 'ltr') === 'ltr' ? 'selected' : '' ?>>
                                Soldan sağa (ltr)</option>
                            <option value="rtl" <?= ($duzenlenen['direction'] ?? '') === 'rtl' ? 'selected' : '' ?>>
                                Sağdan sola (rtl)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Sıra</label>
                        <input type="number" name="sort_order" class="form-input"
                            value="<?= (int) ($duzenlenen['sort_order'] ?? 0) ?>">
                    </div>
                </div>

                <label style="display:flex;align-items:center;gap:8px;margin:16px 0">
                    <input type="checkbox" name="is_active" <?= (int) ($duzenlenen['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                    Sitede görünsün
                </label>

                <button type="submit" class="btn btn-primary"><?= $duzenlenen ? 'Güncelle' : 'Ekle' ?></button>
                <?php if ($duzenlenen): ?>
                    <a href="?tab=diller" class="btn btn-outline">Vazgeç</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Diller</h3>
        </div>
        <div class="card-body" style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Bayrak</th>
                        <th>Kod</th>
                        <th>Adı</th>
                        <th>Yerel adı</th>
                        <th>Yön</th>
                        <th>Çeviri</th>
                        <th>Durum</th>
                        <th>Sıra</th>
                        <th style="text-align:right">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($diller as $d):
                        $dolu = (int) Database::fetchColumn(
                            "SELECT COUNT(*) FROM translations WHERE lang_code = ? AND t_value IS NOT NULL AND t_value <> ''",
                            [$d['code']]
                        );
                        ?>
                        <tr>
                            <td><span class="dil-bayrak"><?= htmlspecialchars($d['flag'] ?? '') ?></span></td>
                            <td><code><?= htmlspecialchars($d['code']) ?></code></td>
                            <td><?= htmlspecialchars($d['name']) ?></td>
                            <td><?= htmlspecialchars($d['native_name']) ?></td>
                            <td><?= $d['direction'] === 'rtl' ? 'Sağdan sola' : 'Soldan sağa' ?></td>
                            <td><?= $dolu ?> / <?= $toplamAnahtar ?></td>
                            <td>
                                <?php if ((int) $d['is_default'] === 1): ?>
                                    <span class="badge badge-info">Varsayılan</span>
                                <?php elseif ((int) $d['is_active'] === 1): ?>
                                    <span class="badge badge-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge badge-gray">Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) $d['sort_order'] ?></td>
                            <td style="text-align:right;white-space:nowrap">
                                <a href="?tab=diller&duzenle=<?= (int) $d['id'] ?>" class="btn btn-sm btn-outline">Düzenle</a>
                                <?php if ((int) $d['is_default'] !== 1): ?>
                                    <a href="?tab=diller&varsayilan=<?= (int) $d['id'] ?>" class="btn btn-sm btn-outline">Varsayılan
                                        yap</a>
                                    <a href="?tab=diller&sil=<?= (int) $d['id'] ?>" class="btn btn-sm btn-danger"
                                        onclick="return confirm('<?= htmlspecialchars($d['name']) ?> dili ve tüm çevirileri silinecek. Emin misiniz?')">Sil</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>

    <div class="card">
        <div class="card-header">
            <h3>Çeviriler</h3>
        </div>
        <div class="card-body">
            <p style="font-size:13px;opacity:.75;margin-bottom:16px">
                Anahtarlar, ön yüzde ilgili metin ilk kez gösterildiğinde kendiliğinden oluşur.
                Boş bıraktığınız alanlarda varsayılan dildeki metin gösterilir.
            </p>

            <form method="get" class="ceviri-arac">
                <input type="hidden" name="tab" value="ceviri">
                <input type="text" name="ara" class="form-input" style="max-width:260px" placeholder="Anahtar ya da metin ara"
                    value="<?= htmlspecialchars($ara) ?>">
                <select name="grup" class="form-input" style="max-width:200px">
                    <option value="">Tüm gruplar</option>
                    <?php foreach ($gruplar as $g): ?>
                        <option value="<?= htmlspecialchars($g['grup']) ?>" <?= $grup === $g['grup'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g['grup']) ?> (<?= (int) $g['adet'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline">Filtrele</button>
                <?php if ($ara !== '' || $grup !== ''): ?>
                    <a href="?tab=ceviri" class="btn btn-outline">Temizle</a>
                <?php endif; ?>
            </form>

            <?php if (!$anahtarListe): ?>
                <p style="opacity:.7">Kayıt bulunamadı.</p>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="islem" value="ceviri-kaydet">
                    <div style="overflow-x:auto">
                        <table class="table ceviri-tablo">
                            <thead>
                                <tr>
                                    <th style="min-width:190px">Anahtar</th>
                                    <?php foreach ($diller as $d): ?>
                                        <th style="min-width:220px">
                                            <?= htmlspecialchars($d['flag'] ?? '') ?>
                                            <?= htmlspecialchars($d['native_name']) ?>
                                            <?= (int) $d['is_default'] === 1 ? ' (varsayılan)' : '' ?>
                                        </th>
                                    <?php endforeach; ?>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($anahtarListe as $anahtar): ?>
                                    <tr>
                                        <td class="anahtar-hucre"><?= htmlspecialchars($anahtar) ?></td>
                                        <?php foreach ($diller as $d): ?>
                                            <td>
                                                <textarea name="ceviri[<?= htmlspecialchars($d['code']) ?>][<?= htmlspecialchars($anahtar) ?>]"
                                                    dir="<?= $d['direction'] === 'rtl' ? 'rtl' : 'ltr' ?>"
                                                    placeholder="<?= htmlspecialchars($degerler[$anahtar][$dilKodlari[0] ?? 'tr'] ?? '') ?>"><?= htmlspecialchars($degerler[$anahtar][$d['code']] ?? '') ?></textarea>
                                            </td>
                                        <?php endforeach; ?>
                                        <td style="vertical-align:top;padding-top:12px">
                                            <a href="?tab=ceviri&anahtar-sil=<?= urlencode($anahtar) ?>"
                                                class="btn btn-sm btn-danger"
                                                onclick="return confirm('Bu anahtar tüm dillerden silinecek. Emin misiniz?')">Sil</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top:18px">
                        <button type="submit" class="btn btn-primary">Çevirileri Kaydet</button>
                        <span style="margin-left:10px;font-size:13px;opacity:.7">
                            <?= $toplamAnahtar ?> anahtar · sayfa <?= $sayfa ?>/<?= $toplamSayfa ?>
                        </span>
                    </div>
                </form>

                <?php if ($toplamSayfa > 1): ?>
                    <div class="sayfalama">
                        <?php
                        $temel = '?tab=ceviri'
                            . ($ara !== '' ? '&ara=' . urlencode($ara) : '')
                            . ($grup !== '' ? '&grup=' . urlencode($grup) : '');
                        for ($i = 1; $i <= $toplamSayfa; $i++):
                            if ($i === $sayfa): ?>
                                <span class="aktif"><?= $i ?></span>
                            <?php else: ?>
                                <a href="<?= $temel ?>&sayfa=<?= $i ?>"><?= $i ?></a>
                            <?php endif;
                        endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
