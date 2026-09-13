<?php
/**
 * VHM - Admin Alan Adı Fiyatları
 *
 * domain_pricing tablosunu yönetir. Sitedeki uzantı listesi ve tüm alan adı
 * ücretleri bu tablodan gelir; sayfaya sabit fiyat yazılmaz.
 *
 * Registrar kimlik bilgileri girilmişse TLD listesi ve maliyetler
 * DomainNameAPI'den çekilip kâr oranı uygulanarak yazılabilir.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/AlanAdi.php';

require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Alan Adı Fiyatları';
$currentPage = 'domain-fiyatlari';

$mesaj = '';
$mesajTipi = '';

$sayi = static fn(string $alan): float =>
    max(0.0, (float) str_replace([',', ' '], ['.', ''], (string) ($_POST[$alan] ?? '0')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $islem = (string) ($_POST['islem'] ?? '');

    if ($islem === 'ekle') {
        $uzanti = ltrim(mb_strtolower(trim((string) ($_POST['extension'] ?? ''))), '.');
        $uzanti = preg_replace('/[^a-z0-9.\-]/', '', $uzanti) ?? '';

        if ($uzanti === '') {
            $mesaj = 'Uzantı boş olamaz.';
            $mesajTipi = 'error';
        } elseif (Database::fetch("SELECT id FROM domain_pricing WHERE extension = ?", [$uzanti])) {
            $mesaj = '.' . $uzanti . ' zaten kayıtlı.';
            $mesajTipi = 'error';
        } else {
            Database::insert('domain_pricing', [
                'extension' => $uzanti,
                'register_1yr' => $sayi('register_1yr'),
                'renew_1yr' => $sayi('renew_1yr'),
                'transfer_price' => $sayi('transfer_price'),
                'id_protection_price' => $sayi('id_protection_price'),
                'currency' => 'TRY',
                'is_active' => 1,
            ]);
            $mesaj = '.' . $uzanti . ' eklendi.';
            $mesajTipi = 'success';
        }
    } elseif ($islem === 'kaydet') {
        $satirlar = (array) ($_POST['satir'] ?? []);
        $adet = 0;
        foreach ($satirlar as $id => $v) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $oku = static fn(string $a): float =>
                max(0.0, (float) str_replace([',', ' '], ['.', ''], (string) ($v[$a] ?? '0')));

            Database::update('domain_pricing', [
                'register_1yr' => $oku('register_1yr'),
                'renew_1yr' => $oku('renew_1yr'),
                'transfer_price' => $oku('transfer_price'),
                'id_protection_price' => $oku('id_protection_price'),
                'is_active' => isset($v['is_active']) ? 1 : 0,
            ], 'id = ?', [$id]);
            $adet++;
        }
        $mesaj = $adet . ' uzantı güncellendi.';
        $mesajTipi = 'success';
    } elseif ($islem === 'sil') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            Database::delete('domain_pricing', 'id = ?', [$id]);
            $mesaj = 'Uzantı silindi.';
            $mesajTipi = 'success';
        }
    } elseif ($islem === 'apiden_cek') {
        $kar = max(0.0, (float) str_replace(',', '.', (string) ($_POST['kar_orani'] ?? '0')));
        $sonuc = AlanAdi::fiyatlariCek($kar, 200);
        $mesaj = $sonuc['mesaj'];
        $mesajTipi = $sonuc['ok'] ? 'success' : 'error';
    }
}

$uzantilar = Database::fetchAll("SELECT * FROM domain_pricing ORDER BY extension");
$apiHazir = AlanAdi::apiHazir();

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .df-kutu {
        margin-bottom: 20px;
        border: 1px solid var(--y-cizgi);
        border-radius: 10px;
        background: var(--y-yuzey);
        overflow: hidden;
    }

    .df-kutu-basluk {
        padding: 13px 16px;
        border-bottom: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
        font-size: 13px;
        font-weight: 700;
        color: var(--y-metin-2);
    }

    .df-kutu-govde {
        padding: 16px;
    }

    .df-satir {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 12px;
    }

    .df-alan label {
        display: block;
        margin-bottom: 5px;
        font-size: 12px;
        font-weight: 600;
        color: var(--y-metin-2);
    }

    .df-alan input {
        width: 130px;
        padding: 8px 11px;
        border: 1px solid var(--y-cizgi);
        border-radius: 6px;
        font-size: 13px;
    }

    .df-not {
        margin-top: 10px;
        font-size: 12px;
        line-height: 1.5;
        color: var(--y-metin-3);
    }

    .df-tablo {
        width: 100%;
        border-collapse: collapse;
        background: var(--y-yuzey);
        border: 1px solid var(--y-cizgi);
        border-radius: 10px;
        overflow: hidden;
        font-size: 13px;
    }

    .df-tablo th,
    .df-tablo td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid var(--y-cizgi-soft);
    }

    .df-tablo thead th {
        background: var(--y-yuzey-2);
        font-size: 11px;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--y-metin-3);
    }

    .df-tablo tr:last-child td {
        border-bottom: 0;
    }

    .df-tablo input[type="number"] {
        width: 108px;
        padding: 6px 9px;
        border: 1px solid var(--y-cizgi);
        border-radius: 6px;
        font-size: 13px;
        text-align: right;
    }

    .df-uzanti {
        font-weight: 700;
        color: #1d4ed8;
    }

    .df-sil {
        border: 1px solid var(--y-cizgi);
        border-radius: 6px;
        background: var(--y-yuzey);
        padding: 5px 9px;
        font-size: 12px;
        color: var(--y-metin-3);
        cursor: pointer;
    }

    .df-sil:hover {
        border-color: #ef4444;
        color: #ef4444;
    }

    .df-bos {
        padding: 34px;
        text-align: center;
        color: var(--y-metin-3);
        background: var(--y-yuzey);
        border: 1px dashed var(--y-cizgi);
        border-radius: 10px;
    }

    .df-alt {
        display: flex;
        justify-content: flex-end;
        margin-top: 14px;
    }
</style>

<div class="page-header">
    <h1>Alan Adı Fiyatları</h1>
    <p>Sitedeki uzantı listesi ve tüm alan adı ücretleri bu tablodan gelir.</p>
</div>

<?php if ($mesaj !== ''): ?>
    <div class="alert alert-<?= htmlspecialchars($mesajTipi) ?>">
        <?= htmlspecialchars($mesaj) ?>
    </div>
<?php endif; ?>

<!-- API'den çekme -->
<div class="df-kutu">
    <div class="df-kutu-basluk">Sağlayıcıdan fiyat çek</div>
    <div class="df-kutu-govde">
        <?php if (!$apiHazir): ?>
            <p class="df-not" style="margin-top: 0;">
                Alan adı sağlayıcısı bağlantısı yapılandırılmamış.
                <a href="integrations.php">Entegrasyonlar</a> sayfasından kullanıcı adı ve parolayı girdikten
                sonra TLD listesi ve maliyetler buraya çekilebilir.
            </p>
        <?php else: ?>
            <form method="post" class="df-satir">
                <input type="hidden" name="islem" value="apiden_cek">
                <div class="df-alan">
                    <label for="kar_orani">Kâr oranı (%)</label>
                    <input type="number" id="kar_orani" name="kar_orani" step="1" min="0" value="30">
                </div>
                <button type="submit" class="btn btn-primary">Fiyatları çek ve güncelle</button>
            </form>
            <p class="df-not">
                Maliyetin üzerine bu oran eklenerek satış fiyatı yazılır. Mevcut uzantılar güncellenir,
                yeni uzantılar eklenir. Elle girdiğiniz değerler bu işlemle değişir.
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Elle ekleme -->
<div class="df-kutu">
    <div class="df-kutu-basluk">Uzantı ekle</div>
    <div class="df-kutu-govde">
        <form method="post" class="df-satir">
            <input type="hidden" name="islem" value="ekle">
            <div class="df-alan">
                <label for="extension">Uzantı</label>
                <input type="text" id="extension" name="extension" placeholder="com.tr" required>
            </div>
            <div class="df-alan">
                <label for="register_1yr">Kayıt / yıl</label>
                <input type="number" id="register_1yr" name="register_1yr" step="0.01" min="0" value="0">
            </div>
            <div class="df-alan">
                <label for="renew_1yr">Yenileme / yıl</label>
                <input type="number" id="renew_1yr" name="renew_1yr" step="0.01" min="0" value="0">
            </div>
            <div class="df-alan">
                <label for="transfer_price">Transfer</label>
                <input type="number" id="transfer_price" name="transfer_price" step="0.01" min="0" value="0">
            </div>
            <div class="df-alan">
                <label for="id_protection_price">WHOIS gizliliği</label>
                <input type="number" id="id_protection_price" name="id_protection_price" step="0.01" min="0"
                    value="0">
            </div>
            <button type="submit" class="btn btn-primary">Ekle</button>
        </form>
    </div>
</div>

<?php if (!$uzantilar): ?>
    <div class="df-bos">
        Henüz uzantı tanımlanmadı. Tablo boşken sitedeki fiyat bölümü gösterilmez ve
        sorgulama yapılmaz — uydurma fiyat basılmaz.
    </div>
<?php else: ?>
    <form method="post">
        <input type="hidden" name="islem" value="kaydet">
        <table class="df-tablo">
            <thead>
                <tr>
                    <th>Uzantı</th>
                    <th>Kayıt / yıl</th>
                    <th>Yenileme / yıl</th>
                    <th>Transfer</th>
                    <th>WHOIS gizliliği</th>
                    <th>Etkin</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($uzantilar as $u): ?>
                    <tr>
                        <td class="df-uzanti" dir="ltr">.<?= htmlspecialchars((string) $u['extension']) ?></td>
                        <td>
                            <input type="number" step="0.01" min="0"
                                name="satir[<?= (int) $u['id'] ?>][register_1yr]"
                                value="<?= number_format((float) $u['register_1yr'], 2, '.', '') ?>">
                        </td>
                        <td>
                            <input type="number" step="0.01" min="0" name="satir[<?= (int) $u['id'] ?>][renew_1yr]"
                                value="<?= number_format((float) $u['renew_1yr'], 2, '.', '') ?>">
                        </td>
                        <td>
                            <input type="number" step="0.01" min="0"
                                name="satir[<?= (int) $u['id'] ?>][transfer_price]"
                                value="<?= number_format((float) $u['transfer_price'], 2, '.', '') ?>">
                        </td>
                        <td>
                            <input type="number" step="0.01" min="0"
                                name="satir[<?= (int) $u['id'] ?>][id_protection_price]"
                                value="<?= number_format((float) ($u['id_protection_price'] ?? 0), 2, '.', '') ?>">
                        </td>
                        <td>
                            <input type="checkbox" name="satir[<?= (int) $u['id'] ?>][is_active]" value="1"
                                <?= (int) $u['is_active'] === 1 ? 'checked' : '' ?>>
                        </td>
                        <td>
                            <button type="submit" form="sil-<?= (int) $u['id'] ?>" class="df-sil"
                                onclick="return confirm('.<?= htmlspecialchars((string) $u['extension']) ?> silinsin mi?');">
                                Sil
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="df-alt">
            <button type="submit" class="btn btn-primary">Değişiklikleri kaydet</button>
        </div>
    </form>

    <?php foreach ($uzantilar as $u): ?>
        <form method="post" id="sil-<?= (int) $u['id'] ?>" style="display: none;">
            <input type="hidden" name="islem" value="sil">
            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
        </form>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
