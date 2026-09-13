<?php
/**
 * VHM - Admin SLA Yönetimi
 *
 * sla.php sayfasındaki tüm rakamlar buradan gelir: erişilebilirlik
 * garantileri, hizmet kredisi basamakları, destek yanıt süreleri,
 * bakım bildirim süresi, kredi başvuru süresi ve hukuki sayfaların
 * yürürlük tarihleri.
 *
 * Kredi basamaklarının en üst aralığı, ilgili hizmetin garanti oranıyla
 * biter. Aksi hâlde garantinin ihlal edildiği ama kredinin tanımsız
 * kaldığı bir aralık oluşur; sayfa bu denetimi uyarı olarak gösterir.
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

$pageTitle = 'SLA Yönetimi';
$currentPage = 'sla';

$mesaj = '';
$mesajTipi = '';

$ondalik = static fn(mixed $v): float =>
    max(0.0, min(100.0, (float) str_replace([',', ' '], ['.', ''], (string) $v)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $islem = (string) ($_POST['islem'] ?? '');

    if ($islem === 'uptime') {
        foreach ((array) ($_POST['hizmet'] ?? []) as $id => $v) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            Database::update('sla_hizmetleri', [
                'ad' => mb_substr(trim((string) ($v['ad'] ?? '')), 0, 80),
                'aciklama' => mb_substr(trim((string) ($v['aciklama'] ?? '')), 0, 200),
                'uptime' => $ondalik($v['uptime'] ?? 0),
                'is_active' => isset($v['is_active']) ? 1 : 0,
            ], 'id = ?', [$id]);
        }
        $mesaj = 'Erişilebilirlik oranları kaydedildi.';
        $mesajTipi = 'success';
    } elseif ($islem === 'krediler') {
        foreach ((array) ($_POST['kredi'] ?? []) as $id => $v) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            Database::update('sla_kredileri', [
                'alt_sinir' => $ondalik($v['alt_sinir'] ?? 0),
                'ust_sinir' => $ondalik($v['ust_sinir'] ?? 0),
                'kredi' => max(0, min(100, (int) ($v['kredi'] ?? 0))),
            ], 'id = ?', [$id]);
        }
        $mesaj = 'Kredi basamakları kaydedildi.';
        $mesajTipi = 'success';
    } elseif ($islem === 'sureler') {
        foreach ((array) ($_POST['sure'] ?? []) as $id => $v) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            Database::update('sla_yanit_sureleri', [
                'ad' => mb_substr(trim((string) ($v['ad'] ?? '')), 0, 80),
                'aciklama' => mb_substr(trim((string) ($v['aciklama'] ?? '')), 0, 200),
                'dakika' => max(1, (int) ($v['dakika'] ?? 1)),
                'is_active' => isset($v['is_active']) ? 1 : 0,
            ], 'id = ?', [$id]);
        }
        $mesaj = 'Yanıt süreleri kaydedildi.';
        $mesajTipi = 'success';
    } elseif ($islem === 'ayarlar') {
        $tarih = static function (string $alan): string {
            $v = trim((string) ($_POST[$alan] ?? ''));
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 ? $v : '';
        };

        foreach (['sla_yururluk', 'terms_yururluk', 'privacy_yururluk', 'kvkk_yururluk'] as $k) {
            $v = $tarih($k);
            if ($v !== '') {
                Settings::set($k, $v);
            }
        }
        Settings::set('sla_bakim_bildirim_saat', (string) max(0, (int) ($_POST['sla_bakim_bildirim_saat'] ?? 48)));
        Settings::set('sla_kredi_basvuru_gun', (string) max(1, (int) ($_POST['sla_kredi_basvuru_gun'] ?? 30)));

        $mesaj = 'Ayarlar kaydedildi.';
        $mesajTipi = 'success';
    } elseif ($islem === 'iade') {
        foreach ((array) ($_POST['iade'] ?? []) as $id => $v) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $tur = (string) ($v['tur'] ?? 'tam');
            if (!in_array($tur, ['tam', 'oransal', 'yok'], true)) {
                $tur = 'tam';
            }
            Database::update('iade_kurallari', [
                'ad' => mb_substr(trim((string) ($v['ad'] ?? '')), 0, 80),
                'tur' => $tur,
                'gun' => max(0, (int) ($v['gun'] ?? 0)),
                'aciklama' => mb_substr(trim((string) ($v['aciklama'] ?? '')), 0, 250),
                'is_active' => isset($v['is_active']) ? 1 : 0,
            ], 'id = ?', [$id]);
        }

        Settings::set('iade_veri_saklama_gun', (string) max(0, (int) ($_POST['iade_veri_saklama_gun'] ?? 7)));
        Settings::set('iade_onay_gun', (string) max(1, (int) ($_POST['iade_onay_gun'] ?? 3)));
        Settings::set('iade_odeme_gun', (string) max(1, (int) ($_POST['iade_odeme_gun'] ?? 10)));

        $refundTarih = trim((string) ($_POST['refund_yururluk'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $refundTarih) === 1) {
            Settings::set('refund_yururluk', $refundTarih);
        }

        $mesaj = 'İade kuralları kaydedildi.';
        $mesajTipi = 'success';
    }
}

$hizmetler = Database::fetchAll("SELECT * FROM sla_hizmetleri ORDER BY sort_order, id");
$iadeKurallari = Database::fetchAll("SELECT * FROM iade_kurallari ORDER BY sort_order, id");
$krediler = Database::fetchAll("SELECT * FROM sla_kredileri ORDER BY hizmet_id, sort_order");
$sureler = Database::fetchAll("SELECT * FROM sla_yanit_sureleri ORDER BY sort_order, id");

$krediGrup = [];
foreach ($krediler as $k) {
    $krediGrup[(int) $k['hizmet_id']][] = $k;
}

/* Tutarlılık denetimi: garanti ile en üst aralık arasında boşluk var mı,
   aralıklar örtüşüyor mu */
$uyarilar = [];
foreach ($hizmetler as $h) {
    $satirlar = $krediGrup[(int) $h['id']] ?? [];
    if (!$satirlar) {
        $uyarilar[] = $h['ad'] . ': kredi basamağı tanımlı değil.';
        continue;
    }

    $enYuksek = max(array_map(static fn(array $s): float => (float) $s['ust_sinir'], $satirlar));
    if ($enYuksek < (float) $h['uptime']) {
        $uyarilar[] = sprintf(
            '%s: garanti %%%s, en üst aralık %%%s\'te bitiyor. Aradaki değerler için kredi tanımsız.',
            $h['ad'],
            rtrim(rtrim(number_format((float) $h['uptime'], 3, ',', '.'), '0'), ','),
            rtrim(rtrim(number_format($enYuksek, 3, ',', '.'), '0'), ',')
        );
    }

    usort($satirlar, static fn(array $a, array $b): int => (float) $a['alt_sinir'] <=> (float) $b['alt_sinir']);
    for ($i = 0; $i < count($satirlar) - 1; $i++) {
        if ((float) $satirlar[$i]['ust_sinir'] > (float) $satirlar[$i + 1]['alt_sinir']) {
            $uyarilar[] = $h['ad'] . ': kredi aralıkları örtüşüyor.';
            break;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .sl-kutu {
        margin-bottom: 20px;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: #fff;
        overflow: hidden;
    }

    .sl-kutu-basluk {
        padding: 13px 16px;
        border-bottom: 1px solid #e5e7eb;
        background: #f9fafb;
        font-size: 13px;
        font-weight: 700;
        color: #374151;
    }

    .sl-kutu-govde {
        padding: 16px;
    }

    .sl-tablo {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .sl-tablo th,
    .sl-tablo td {
        padding: 9px 10px;
        text-align: left;
        border-bottom: 1px solid #f1f3f5;
        vertical-align: middle;
    }

    .sl-tablo thead th {
        font-size: 11px;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .sl-tablo tr:last-child td {
        border-bottom: 0;
    }

    .sl-tablo input[type="text"],
    .sl-tablo input[type="number"] {
        width: 100%;
        padding: 7px 10px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        font-size: 13px;
    }

    .sl-tablo input[type="number"] {
        text-align: right;
    }

    .sl-dar {
        width: 110px;
    }

    .sl-orta {
        width: 170px;
    }

    .sl-alt {
        display: flex;
        justify-content: flex-end;
        margin-top: 14px;
    }

    .sl-hizmet-ad {
        margin: 18px 0 8px;
        font-size: 13px;
        font-weight: 700;
        color: #1d4ed8;
    }

    .sl-hizmet-ad:first-child {
        margin-top: 0;
    }

    .sl-uyari {
        margin-bottom: 20px;
        padding: 13px 16px;
        border: 1px solid #fcd34d;
        border-left: 5px solid #f59e0b;
        border-radius: 10px;
        background: #fffbeb;
        font-size: 13px;
        line-height: 1.6;
        color: #92400e;
    }

    .sl-uyari b {
        display: block;
        margin-bottom: 5px;
    }

    .sl-uyari ul {
        margin: 0;
        padding-left: 18px;
    }

    .sl-ayar {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
    }

    .sl-ayar label {
        display: block;
        margin-bottom: 5px;
        font-size: 12px;
        font-weight: 600;
        color: #374151;
    }

    .sl-ayar input {
        width: 100%;
        padding: 8px 11px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        font-size: 13px;
    }

    .sl-not {
        margin-top: 10px;
        font-size: 12px;
        line-height: 1.5;
        color: #6b7280;
    }
</style>

<div class="page-header">
    <h1>SLA Yönetimi</h1>
    <p>sla.php sayfasındaki tüm oranlar, süreler ve tarihler buradan yönetilir.</p>
</div>

<?php if ($mesaj !== ''): ?>
    <div class="alert alert-<?= htmlspecialchars($mesajTipi) ?>">
        <?= htmlspecialchars($mesaj) ?>
    </div>
<?php endif; ?>

<?php if ($uyarilar): ?>
    <div class="sl-uyari">
        <b>Tutarsızlık bulundu</b>
        <ul>
            <?php foreach ($uyarilar as $u): ?>
                <li><?= htmlspecialchars($u) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Erişilebilirlik -->
<div class="sl-kutu">
    <div class="sl-kutu-basluk">Erişilebilirlik garantileri</div>
    <div class="sl-kutu-govde">
        <form method="post">
            <input type="hidden" name="islem" value="uptime">
            <table class="sl-tablo">
                <thead>
                    <tr>
                        <th>Hizmet</th>
                        <th>Açıklama</th>
                        <th class="sl-dar">Uptime %</th>
                        <th>Etkin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hizmetler as $h): ?>
                        <tr>
                            <td class="sl-orta">
                                <input type="text" name="hizmet[<?= (int) $h['id'] ?>][ad]"
                                    value="<?= htmlspecialchars((string) $h['ad']) ?>">
                            </td>
                            <td>
                                <input type="text" name="hizmet[<?= (int) $h['id'] ?>][aciklama]"
                                    value="<?= htmlspecialchars((string) ($h['aciklama'] ?? '')) ?>">
                            </td>
                            <td>
                                <input type="number" step="0.001" min="0" max="100"
                                    name="hizmet[<?= (int) $h['id'] ?>][uptime]"
                                    value="<?= rtrim(rtrim(number_format((float) $h['uptime'], 3, '.', ''), '0'), '.') ?>">
                            </td>
                            <td>
                                <input type="checkbox" name="hizmet[<?= (int) $h['id'] ?>][is_active]" value="1"
                                    <?= (int) $h['is_active'] === 1 ? 'checked' : '' ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="sl-alt"><button type="submit" class="btn btn-primary">Kaydet</button></div>
        </form>
    </div>
</div>

<!-- Kredi basamakları -->
<div class="sl-kutu">
    <div class="sl-kutu-basluk">Hizmet kredisi basamakları</div>
    <div class="sl-kutu-govde">
        <form method="post">
            <input type="hidden" name="islem" value="krediler">
            <?php foreach ($hizmetler as $h): ?>
                <div class="sl-hizmet-ad">
                    <?= htmlspecialchars((string) $h['ad']) ?>
                    — garanti
                    %<?= rtrim(rtrim(number_format((float) $h['uptime'], 3, ',', '.'), '0'), ',') ?>
                </div>
                <table class="sl-tablo">
                    <thead>
                        <tr>
                            <th class="sl-dar">Alt sınır %</th>
                            <th class="sl-dar">Üst sınır %</th>
                            <th class="sl-dar">Kredi %</th>
                            <th>Aralık</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($krediGrup[(int) $h['id']] ?? [] as $k): ?>
                            <tr>
                                <td>
                                    <input type="number" step="0.001" min="0" max="100"
                                        name="kredi[<?= (int) $k['id'] ?>][alt_sinir]"
                                        value="<?= rtrim(rtrim(number_format((float) $k['alt_sinir'], 3, '.', ''), '0'), '.') ?>">
                                </td>
                                <td>
                                    <input type="number" step="0.001" min="0" max="100"
                                        name="kredi[<?= (int) $k['id'] ?>][ust_sinir]"
                                        value="<?= rtrim(rtrim(number_format((float) $k['ust_sinir'], 3, '.', ''), '0'), '.') ?>">
                                </td>
                                <td>
                                    <input type="number" step="1" min="0" max="100"
                                        name="kredi[<?= (int) $k['id'] ?>][kredi]" value="<?= (int) $k['kredi'] ?>">
                                </td>
                                <td style="color: #6b7280;">
                                    alt sınır dâhil, üst sınır hariç
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
            <div class="sl-alt"><button type="submit" class="btn btn-primary">Kaydet</button></div>
            <p class="sl-not">
                En üst aralığın üst sınırı, hizmetin garanti oranıyla aynı olmalıdır.
                Daha düşük bırakılırsa garantinin hemen altındaki kesintiler için kredi tanımsız kalır
                ve sayfanın başında uyarı çıkar.
            </p>
        </form>
    </div>
</div>

<!-- Yanıt süreleri -->
<div class="sl-kutu">
    <div class="sl-kutu-basluk">Destek yanıt süreleri</div>
    <div class="sl-kutu-govde">
        <form method="post">
            <input type="hidden" name="islem" value="sureler">
            <table class="sl-tablo">
                <thead>
                    <tr>
                        <th class="sl-orta">Öncelik</th>
                        <th>Tanım</th>
                        <th class="sl-dar">Dakika</th>
                        <th>Etkin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sureler as $s): ?>
                        <tr>
                            <td>
                                <input type="text" name="sure[<?= (int) $s['id'] ?>][ad]"
                                    value="<?= htmlspecialchars((string) $s['ad']) ?>">
                            </td>
                            <td>
                                <input type="text" name="sure[<?= (int) $s['id'] ?>][aciklama]"
                                    value="<?= htmlspecialchars((string) ($s['aciklama'] ?? '')) ?>">
                            </td>
                            <td>
                                <input type="number" step="1" min="1" name="sure[<?= (int) $s['id'] ?>][dakika]"
                                    value="<?= (int) $s['dakika'] ?>">
                            </td>
                            <td>
                                <input type="checkbox" name="sure[<?= (int) $s['id'] ?>][is_active]" value="1"
                                    <?= (int) $s['is_active'] === 1 ? 'checked' : '' ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="sl-alt"><button type="submit" class="btn btn-primary">Kaydet</button></div>
        </form>
    </div>
</div>

<!-- Ayarlar ve yürürlük tarihleri -->
<div class="sl-kutu">
    <div class="sl-kutu-basluk">Süreler ve yürürlük tarihleri</div>
    <div class="sl-kutu-govde">
        <form method="post">
            <input type="hidden" name="islem" value="ayarlar">
            <div class="sl-ayar">
                <div>
                    <label for="sla_bakim_bildirim_saat">Planlı bakım bildirimi (saat)</label>
                    <input type="number" id="sla_bakim_bildirim_saat" name="sla_bakim_bildirim_saat" step="1"
                        min="0" value="<?= (int) Settings::get('sla_bakim_bildirim_saat', 48) ?>">
                </div>
                <div>
                    <label for="sla_kredi_basvuru_gun">Kredi başvuru süresi (gün)</label>
                    <input type="number" id="sla_kredi_basvuru_gun" name="sla_kredi_basvuru_gun" step="1" min="1"
                        value="<?= (int) Settings::get('sla_kredi_basvuru_gun', 30) ?>">
                </div>
                <div>
                    <label for="sla_yururluk">SLA yürürlük tarihi</label>
                    <input type="date" id="sla_yururluk" name="sla_yururluk"
                        value="<?= htmlspecialchars((string) Settings::get('sla_yururluk', '')) ?>">
                </div>
                <div>
                    <label for="terms_yururluk">Kullanım Şartları tarihi</label>
                    <input type="date" id="terms_yururluk" name="terms_yururluk"
                        value="<?= htmlspecialchars((string) Settings::get('terms_yururluk', '')) ?>">
                </div>
                <div>
                    <label for="privacy_yururluk">Gizlilik Politikası tarihi</label>
                    <input type="date" id="privacy_yururluk" name="privacy_yururluk"
                        value="<?= htmlspecialchars((string) Settings::get('privacy_yururluk', '')) ?>">
                </div>
                <div>
                    <label for="kvkk_yururluk">KVKK Metni tarihi</label>
                    <input type="date" id="kvkk_yururluk" name="kvkk_yururluk"
                        value="<?= htmlspecialchars((string) Settings::get('kvkk_yururluk', '')) ?>">
                </div>
            </div>
            <div class="sl-alt"><button type="submit" class="btn btn-primary">Kaydet</button></div>
            <p class="sl-not">
                Bu tarihler hukuki metinlerin başında görünür. Önceden her sayfa açıldığı günün
                tarihini basıyordu; yani metin değişmese bile "bugün güncellendi" yazıyordu.
                Metni değiştirdiğinizde tarihi de burada güncelleyin.
            </p>
        </form>
    </div>
</div>

<!-- İade kuralları: iade-politikasi.php ve kullanim-sartlari.php aynı değerleri okur -->
<div class="sl-kutu">
    <div class="sl-kutu-basluk">İade kuralları</div>
    <div class="sl-kutu-govde">
        <form method="post">
            <input type="hidden" name="islem" value="iade">
            <table class="sl-tablo">
                <thead>
                    <tr>
                        <th class="sl-orta">Hizmet</th>
                        <th class="sl-dar">Kapsam</th>
                        <th class="sl-dar">Süre (gün)</th>
                        <th>Açıklama</th>
                        <th>Etkin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($iadeKurallari as $k): ?>
                        <tr>
                            <td>
                                <input type="text" name="iade[<?= (int) $k['id'] ?>][ad]"
                                    value="<?= htmlspecialchars((string) $k['ad']) ?>">
                            </td>
                            <td>
                                <select name="iade[<?= (int) $k['id'] ?>][tur]"
                                    style="width: 100%; padding: 7px 9px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px;">
                                    <option value="tam" <?= $k['tur'] === 'tam' ? 'selected' : '' ?>>Tam iade</option>
                                    <option value="oransal" <?= $k['tur'] === 'oransal' ? 'selected' : '' ?>>Oransal
                                    </option>
                                    <option value="yok" <?= $k['tur'] === 'yok' ? 'selected' : '' ?>>İade yok</option>
                                </select>
                            </td>
                            <td>
                                <input type="number" step="1" min="0" name="iade[<?= (int) $k['id'] ?>][gun]"
                                    value="<?= (int) $k['gun'] ?>">
                            </td>
                            <td>
                                <input type="text" maxlength="250" name="iade[<?= (int) $k['id'] ?>][aciklama]"
                                    value="<?= htmlspecialchars((string) ($k['aciklama'] ?? '')) ?>">
                            </td>
                            <td>
                                <input type="checkbox" name="iade[<?= (int) $k['id'] ?>][is_active]" value="1"
                                    <?= (int) $k['is_active'] === 1 ? 'checked' : '' ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="sl-ayar" style="margin-top: 18px;">
                <div>
                    <label for="iade_veri_saklama_gun">İptal sonrası veri saklama (gün)</label>
                    <input type="number" id="iade_veri_saklama_gun" name="iade_veri_saklama_gun" step="1" min="0"
                        value="<?= (int) Settings::get('iade_veri_saklama_gun', 7) ?>">
                </div>
                <div>
                    <label for="iade_onay_gun">Talep değerlendirme (iş günü)</label>
                    <input type="number" id="iade_onay_gun" name="iade_onay_gun" step="1" min="1"
                        value="<?= (int) Settings::get('iade_onay_gun', 3) ?>">
                </div>
                <div>
                    <label for="iade_odeme_gun">İadenin başlatılması (iş günü)</label>
                    <input type="number" id="iade_odeme_gun" name="iade_odeme_gun" step="1" min="1"
                        value="<?= (int) Settings::get('iade_odeme_gun', 10) ?>">
                </div>
                <div>
                    <label for="refund_yururluk">İade Politikası tarihi</label>
                    <input type="date" id="refund_yururluk" name="refund_yururluk"
                        value="<?= htmlspecialchars((string) Settings::get('refund_yururluk', '')) ?>">
                </div>
            </div>

            <div class="sl-alt"><button type="submit" class="btn btn-primary">Kaydet</button></div>
            <p class="sl-not">
                Veri saklama süresini hem İade Politikası hem Kullanım Şartları okur; iki sayfa
                farklı gün söyleyemez. Oransal iade seçilen hizmetlerde sayfada hesap formülü
                ve örnek gösterilir.
            </p>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
