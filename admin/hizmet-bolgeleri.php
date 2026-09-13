<?php
/**
 * WHMVM - Admin Hizmet Bölgeleri
 *
 * Keşif talebi formundaki adres seçimlerini besler.
 *
 *   İl / ilçe / mahalle -> TurkiyeAPI (api.turkiyeapi.dev) üzerinden aranıp eklenir.
 *                          İlçe elle yazılmaz, mahallenin resmî ilçesi API'den gelir.
 *   Sokak               -> TurkiyeAPI sokak verisi sunmaz; liste buraya elle girilir.
 *                          Kaynak: NVİ Adres Kayıt Sistemi (adres.nvi.gov.tr) veya PTT posta kodu sorgusu.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/TurkiyeAdres.php';
require_once dirname(__DIR__) . '/includes/SokakMetni.php';

session_name(SESSION_NAME);
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Hizmet Bölgeleri';
$currentPage = 'hizmet-bolgeleri';

$mesaj = '';
$mesajTipi = '';

$seciliId = (int) ($_GET['mahalle'] ?? 0);
$aramaIl = (int) ($_GET['il'] ?? 16);          // 16 = Bursa
$aramaMetin = trim((string) ($_GET['ara'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $islem = (string) ($_POST['islem'] ?? '');

    try {
        if ($islem === 'mahalle_ekle') {
            $apiId = (int) ($_POST['api_id'] ?? 0);
            $ilId = (int) ($_POST['il_id'] ?? 0);
            $ilAdi = trim((string) ($_POST['il'] ?? ''));
            $ilceId = (int) ($_POST['ilce_id'] ?? 0);
            $ilceAdi = trim((string) ($_POST['ilce'] ?? ''));
            $mahalleAdi = trim((string) ($_POST['mahalle'] ?? ''));
            $postaKodu = trim((string) ($_POST['posta_kodu'] ?? ''));

            if ($apiId <= 0 || $mahalleAdi === '' || $ilceAdi === '') {
                throw new RuntimeException('Mahalle bilgisi eksik geldi, listeden tekrar seçin.');
            }

            Database::query(
                "INSERT INTO hizmet_mahalleleri
                     (il, ilce, mahalle, api_id, il_id, ilce_id, posta_kodu, is_active, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1,
                         (SELECT COALESCE(MAX(s.sort_order), 0) + 1
                            FROM (SELECT sort_order FROM hizmet_mahalleleri) s))
                 ON DUPLICATE KEY UPDATE is_active = 1, ilce = VALUES(ilce),
                                         ilce_id = VALUES(ilce_id), posta_kodu = VALUES(posta_kodu)",
                [$ilAdi, $ilceAdi, $mahalleAdi, $apiId, $ilId, $ilceId, $postaKodu !== '' ? $postaKodu : null]
            );
            $mesaj = $mahalleAdi . ' (' . $ilceAdi . ') hizmet listesine eklendi.';
            $mesajTipi = 'success';

        } elseif ($islem === 'mahalle_durum') {
            $id = (int) ($_POST['id'] ?? 0);
            $aktif = (int) ($_POST['aktif'] ?? 0) === 1 ? 1 : 0;
            Database::update('hizmet_mahalleleri', ['is_active' => $aktif], 'id = ?', [$id]);
            $mesaj = 'Mahalle durumu güncellendi.';
            $mesajTipi = 'success';

        } elseif ($islem === 'mahalle_sil') {
            $id = (int) ($_POST['id'] ?? 0);
            Database::delete('hizmet_mahalleleri', 'id = ?', [$id]);
            $mesaj = 'Mahalle ve sokakları silindi.';
            $mesajTipi = 'success';
            $seciliId = 0;

        } elseif ($islem === 'sokak_kaydet') {
            $id = (int) ($_POST['id'] ?? 0);
            $mahalle = Database::fetch("SELECT id, mahalle FROM hizmet_mahalleleri WHERE id = ?", [$id]);
            if (empty($mahalle)) {
                throw new RuntimeException('Mahalle bulunamadı.');
            }

            /* PTT çıktısı sütunlu ve büyük harf gelir; SokakMetni temizler */
            $duzelt = isset($_POST['duzelt']);
            $liste = SokakMetni::ayikla((string) ($_POST['sokaklar'] ?? ''), $duzelt);

            /* Tam liste olarak yazılır: eksilenler silinir */
            Database::delete('hizmet_sokaklari', 'mahalle_id = ?', [$id]);
            foreach ($liste as $sokak) {
                Database::insert('hizmet_sokaklari', ['mahalle_id' => $id, 'sokak' => $sokak]);
            }

            $mesaj = $mahalle['mahalle'] . ' için ' . count($liste) . ' sokak kaydedildi.';
            $mesajTipi = 'success';
            $seciliId = $id;
        }
    } catch (Throwable $e) {
        $mesaj = $e->getMessage();
        $mesajTipi = 'error';
    }
}

/* Kayıtlı mahalleler */
$mahalleler = Database::fetchAll(
    "SELECT m.*, (SELECT COUNT(*) FROM hizmet_sokaklari s WHERE s.mahalle_id = m.id) AS sokak_adedi
       FROM hizmet_mahalleleri m
      ORDER BY m.il, m.ilce, m.sort_order, m.mahalle"
);
$kayitliApiId = array_flip(array_map('intval', array_column($mahalleler, 'api_id')));

/* API arama */
$apiCalisiyor = TurkiyeAdres::erisimVar();
$iller = $apiCalisiyor ? TurkiyeAdres::iller() : [];
$sonuclar = [];
if ($apiCalisiyor && $aramaMetin !== '') {
    $ilceAdlari = TurkiyeAdres::ilceAdlari($aramaIl);
    foreach (TurkiyeAdres::mahalleAra($aramaIl, $aramaMetin) as $m) {
        $sonuclar[] = [
            'api_id' => (int) $m['id'],
            'mahalle' => (string) $m['name'],
            'il_id' => (int) $m['provinceId'],
            'ilce_id' => (int) $m['districtId'],
            'ilce' => $ilceAdlari[(int) $m['districtId']] ?? '—',
            'posta_kodu' => (string) ($m['postalCode'] ?? ''),
        ];
    }
}
$aramaIlAdi = 'Bursa';
foreach ($iller as $il) {
    if ((int) $il['id'] === $aramaIl) {
        $aramaIlAdi = (string) $il['name'];
        break;
    }
}

/* Sokak düzenleme */
$secili = null;
$seciliSokaklar = '';
foreach ($mahalleler as $m) {
    if ((int) $m['id'] === $seciliId) {
        $secili = $m;
        break;
    }
}
if ($secili) {
    $satirlar = Database::fetchAll(
        "SELECT sokak FROM hizmet_sokaklari WHERE mahalle_id = ? ORDER BY sokak",
        [$seciliId]
    );
    $seciliSokaklar = implode("\n", array_column($satirlar, 'sokak'));
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .hb-duzen {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 20px;
        align-items: start;
    }

    @media (max-width: 1180px) {
        .hb-duzen {
            grid-template-columns: 1fr;
        }
    }

    .hb-kart {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 20px;
    }

    .hb-kart-bas {
        padding: 14px 16px;
        border-bottom: 1px solid #f1f3f5;
        background: #f9fafb;
    }

    .hb-kart-bas h3 {
        margin: 0;
        font-size: 14px;
        font-weight: 800;
        color: #111827;
    }

    .hb-kart-bas p {
        margin: 4px 0 0;
        font-size: 12px;
        line-height: 1.6;
        color: #6b7280;
    }

    .hb-kart-govde {
        padding: 16px;
    }

    .hb-tablo {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .hb-tablo th,
    .hb-tablo td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid #f1f3f5;
        vertical-align: middle;
    }

    .hb-tablo th {
        font-size: 11px;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #6b7280;
        background: #f9fafb;
    }

    .hb-tablo tr:last-child td {
        border-bottom: 0;
    }

    .hb-tablo tr.is-secili td {
        background: #eff5ff;
    }

    .hb-satir-form {
        display: inline-flex;
        gap: 6px;
        align-items: center;
    }

    .hb-rozet {
        display: inline-block;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        background: #f3f4f6;
        color: #6b7280;
    }

    .hb-rozet.dolu {
        background: #dcfce7;
        color: #15803d;
    }

    .hb-rozet.ilce {
        background: #e0e7ff;
        color: #4338ca;
    }

    .hb-arama {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: flex-end;
    }

    .hb-alan {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .hb-alan label {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .hb-alan input,
    .hb-alan select,
    .hb-alan textarea {
        padding: 8px 10px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        font-family: inherit;
        font-size: 13px;
        color: #111827;
    }

    .hb-alan textarea {
        min-height: 300px;
        width: 100%;
        resize: vertical;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 12px;
        line-height: 1.7;
    }

    .hb-dugme {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border: 0;
        border-radius: 6px;
        background: #2474f5;
        color: #fff;
        font-family: inherit;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
    }

    .hb-dugme.ikincil {
        background: #f3f4f6;
        color: #374151;
    }

    .hb-dugme.tehlike {
        background: #fee2e2;
        color: #b91c1c;
    }

    .hb-dugme:disabled {
        opacity: 0.5;
        cursor: default;
    }

    .hb-kucuk {
        padding: 5px 9px;
        font-size: 12px;
    }

    .hb-secim {
        display: flex;
        gap: 8px;
        align-items: flex-start;
        margin-bottom: 12px;
        font-size: 12px;
        line-height: 1.6;
        color: #374151;
        cursor: pointer;
    }

    .hb-secim input {
        margin-top: 3px;
        flex-shrink: 0;
    }

    .hb-sayac {
        margin-left: 10px;
        font-size: 12px;
        color: #6b7280;
    }

    .hb-ipucu {
        margin: 12px 0 0;
        font-size: 12px;
        line-height: 1.7;
        color: #6b7280;
    }

    .hb-ipucu code {
        background: #f3f4f6;
        padding: 1px 5px;
        border-radius: 4px;
    }

    .hb-uyari {
        padding: 10px 12px;
        border-radius: 8px;
        background: #fef3c7;
        border: 1px solid #fde68a;
        color: #92400e;
        font-size: 12px;
        line-height: 1.6;
    }
</style>

<div class="page-header">
    <h1>Hizmet Bölgeleri</h1>
    <p>Keşif talebi formundaki il / ilçe / mahalle / sokak seçimleri buradan beslenir.</p>
</div>

<?php if ($mesaj !== ''): ?>
    <div class="alert alert-<?= htmlspecialchars($mesajTipi) ?>"><?= htmlspecialchars($mesaj) ?></div>
<?php endif; ?>

<div class="hb-duzen">
    <div>
        <div class="hb-kart">
            <div class="hb-kart-bas">
                <h3>Hizmet Verilen Mahalleler</h3>
                <p>Formda yalnızca aktif mahalleler görünür. Sokak sayısı 0 olan mahallede sokak alanı serbest metne düşer.</p>
            </div>
            <table class="hb-tablo">
                <thead>
                    <tr>
                        <th>Mahalle</th>
                        <th>İlçe</th>
                        <th>Sokak</th>
                        <th>Durum</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mahalleler)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #6b7280; padding: 24px;">
                                Henüz mahalle eklenmemiş. Sağdaki aramadan ekleyin.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($mahalleler as $m): ?>
                        <tr class="<?= (int) $m['id'] === $seciliId ? 'is-secili' : '' ?>">
                            <td>
                                <strong><?= htmlspecialchars((string) $m['mahalle']) ?></strong><br>
                                <span style="color: #9ca3af; font-size: 12px;">
                                    <?= htmlspecialchars((string) $m['il']) ?>
                                    <?= !empty($m['posta_kodu']) ? ' &middot; ' . htmlspecialchars((string) $m['posta_kodu']) : '' ?>
                                </span>
                            </td>
                            <td><span class="hb-rozet ilce"><?= htmlspecialchars((string) $m['ilce']) ?></span></td>
                            <td>
                                <span class="hb-rozet <?= (int) $m['sokak_adedi'] > 0 ? 'dolu' : '' ?>">
                                    <?= (int) $m['sokak_adedi'] ?>
                                </span>
                            </td>
                            <td>
                                <form method="post" class="hb-satir-form">
                                    <input type="hidden" name="islem" value="mahalle_durum">
                                    <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                    <input type="hidden" name="aktif" value="<?= (int) $m['is_active'] === 1 ? 0 : 1 ?>">
                                    <button type="submit" class="hb-dugme ikincil hb-kucuk">
                                        <?= (int) $m['is_active'] === 1 ? 'Aktif' : 'Pasif' ?>
                                    </button>
                                </form>
                            </td>
                            <td style="white-space: nowrap;">
                                <a href="?mahalle=<?= (int) $m['id'] ?>" class="hb-dugme hb-kucuk">Sokaklar</a>
                                <form method="post" class="hb-satir-form"
                                    onsubmit="return confirm('<?= htmlspecialchars((string) $m['mahalle']) ?> ve bütün sokakları silinecek. Emin misiniz?');">
                                    <input type="hidden" name="islem" value="mahalle_sil">
                                    <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                    <button type="submit" class="hb-dugme tehlike hb-kucuk">Sil</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="hb-kart">
            <div class="hb-kart-bas">
                <h3>Mahalle Ara ve Ekle</h3>
                <p>İl, ilçe ve mahalle adları <code>api.turkiyeapi.dev</code> üzerinden gelir; ilçe elle yazılmaz.</p>
            </div>
            <div class="hb-kart-govde">
                <?php if (!$apiCalisiyor): ?>
                    <div class="hb-uyari">
                        TurkiyeAPI'ye şu anda ulaşılamıyor. Sunucunun dışarı HTTPS erişimi kapalı olabilir.
                        Erişim açıldığında arama çalışacaktır; mevcut kayıtlar etkilenmez.
                    </div>
                <?php else: ?>
                    <form method="get" class="hb-arama">
                        <div class="hb-alan">
                            <label for="hbIl">İl</label>
                            <select id="hbIl" name="il">
                                <?php foreach ($iller as $il): ?>
                                    <option value="<?= (int) $il['id'] ?>" <?= (int) $il['id'] === $aramaIl ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) $il['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="hb-alan" style="flex: 1 1 200px;">
                            <label for="hbAra">Mahalle adı</label>
                            <input type="text" id="hbAra" name="ara" value="<?= htmlspecialchars($aramaMetin) ?>"
                                placeholder="Örn. Emek" style="width: 100%;">
                        </div>
                        <button type="submit" class="hb-dugme">Ara</button>
                    </form>

                    <?php if ($aramaMetin !== ''): ?>
                        <table class="hb-tablo" style="margin-top: 14px;">
                            <thead>
                                <tr>
                                    <th>Mahalle</th>
                                    <th>İlçe</th>
                                    <th>Posta Kodu</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sonuclar)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: #6b7280; padding: 20px;">
                                            <?= htmlspecialchars($aramaIlAdi) ?> içinde
                                            "<?= htmlspecialchars($aramaMetin) ?>" için sonuç yok.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($sonuclar as $s): ?>
                                    <?php $ekli = isset($kayitliApiId[$s['api_id']]); ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($s['mahalle']) ?></strong></td>
                                        <td><span class="hb-rozet ilce"><?= htmlspecialchars($s['ilce']) ?></span></td>
                                        <td><?= htmlspecialchars($s['posta_kodu'] !== '' ? $s['posta_kodu'] : '—') ?></td>
                                        <td style="text-align: right;">
                                            <?php if ($ekli): ?>
                                                <button class="hb-dugme ikincil hb-kucuk" disabled>Ekli</button>
                                            <?php else: ?>
                                                <form method="post" class="hb-satir-form">
                                                    <input type="hidden" name="islem" value="mahalle_ekle">
                                                    <input type="hidden" name="api_id" value="<?= $s['api_id'] ?>">
                                                    <input type="hidden" name="il_id" value="<?= $s['il_id'] ?>">
                                                    <input type="hidden" name="il" value="<?= htmlspecialchars($aramaIlAdi) ?>">
                                                    <input type="hidden" name="ilce_id" value="<?= $s['ilce_id'] ?>">
                                                    <input type="hidden" name="ilce" value="<?= htmlspecialchars($s['ilce']) ?>">
                                                    <input type="hidden" name="mahalle" value="<?= htmlspecialchars($s['mahalle']) ?>">
                                                    <input type="hidden" name="posta_kodu" value="<?= htmlspecialchars($s['posta_kodu']) ?>">
                                                    <button type="submit" class="hb-dugme hb-kucuk">Ekle</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="hb-kart">
        <div class="hb-kart-bas">
            <h3><?= $secili ? htmlspecialchars((string) $secili['mahalle']) . ' Sokakları' : 'Sokak Listesi' ?></h3>
            <p>
                <?= $secili
                    ? 'Her satıra bir sokak veya cadde adı. Kaydedilen liste formda açılır menü olur.'
                    : 'Soldaki listeden bir mahallenin "Sokaklar" düğmesine basın.' ?>
            </p>
        </div>
        <div class="hb-kart-govde">
            <?php if ($secili): ?>
                <form method="post">
                    <input type="hidden" name="islem" value="sokak_kaydet">
                    <input type="hidden" name="id" value="<?= (int) $secili['id'] ?>">
                    <div class="hb-alan" style="margin-bottom: 12px;">
                        <label for="hbSokaklar">Sokaklar &mdash; her satıra bir tane</label>
                        <textarea id="hbSokaklar" name="sokaklar"
                            placeholder="1. Sokak&#10;Atatürk Caddesi&#10;Cumhuriyet Sokak"><?= htmlspecialchars($seciliSokaklar) ?></textarea>
                    </div>
                    <label class="hb-secim">
                        <input type="checkbox" name="duzelt" value="1" checked>
                        <span>
                            <strong>PTT çıktısını düzelt.</strong>
                            Sütunlu satırdan sokak adını ayıklar, posta kodunu atar, BÜYÜK HARF
                            yazımı normale çevirir ve kısaltmaları açar
                            (<code>ATATÜRK CD</code> &rarr; <code>Atatürk Caddesi</code>).
                        </span>
                    </label>
                    <button type="submit" class="hb-dugme">Listeyi Kaydet</button>
                    <a href="hizmet-bolgeleri.php" class="hb-dugme ikincil">Kapat</a>
                    <span id="hbSayac" class="hb-sayac"></span>
                </form>
                <p class="hb-ipucu">
                    Kaydettiğinizde liste tamamen değiştirilir: yazmadığınız sokaklar silinir.
                    Boş satırlar ve tekrarlar atılır, liste alfabetik sıralanır.
                </p>
            <?php endif; ?>

            <p class="hb-ipucu">
                <strong>Sokak listesi neden API'den gelmiyor?</strong>
                TurkiyeAPI il, ilçe, belediye, mahalle ve köy verisi sunar; sokak/cadde verisi yoktur.
                Türkiye'de sokak seviyesindeki resmî kaynak Nüfus ve Vatandaşlık İşleri'nin
                <code>adres.nvi.gov.tr</code> adresindeki Adres Kayıt Sistemi'dir; PTT'nin posta kodu
                sorgusu da mahalle bazında cadde/sokak dökümü verir. Listeyi oradan alıp buraya yapıştırın.
            </p>
        </div>
    </div>
</div>

<script>
    // Yazdıkça dolu satır sayısını göster
    (function () {
        var alan = document.getElementById('hbSokaklar');
        var sayac = document.getElementById('hbSayac');
        if (!alan || !sayac) return;
        function say() {
            var n = alan.value.split(/
||
/).filter(function (s) { return s.trim() !== ''; }).length;
            sayac.textContent = n + ' satır';
        }
        alan.addEventListener('input', say);
        say();
    })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
