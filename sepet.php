<?php
/**
 * VHM - Sepet
 *
 * Bu sayfa yalnızca gösterir ve düzenler; sepete ekleme client/cart.php
 * üzerinden, ürün veritabanından yapılır. Tutarlar Sepet sınıfından gelir,
 * ödeme sayfası da aynı sınıfı kullanır.
 *
 * Eski sayfalardaki ?add=<slug> bağlantıları veritabanında karşılığı olmayan
 * değerler taşıyordu ve sepete uydurma fiyat ekliyordu. Artık slug ürün
 * tablosunda aranır; bulunamazsa kullanıcı ilgili mağaza sayfasına gönderilir.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Settings.php';
require_once __DIR__ . '/includes/Sepet.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Sepet';
$pageDescription = 'Sepetinizdeki hizmetleri görüntüleyin ve siparişinizi tamamlayın.';

if (empty($_SESSION['sepet_token'])) {
    $_SESSION['sepet_token'] = bin2hex(random_bytes(32));
}

Sepet::ham();

$uyari = '';
$uyariTipi = '';

/* ---------- Eski ?add= bağlantıları ---------- */
$eklenecek = trim((string) ($_GET['add'] ?? ''));
if ($eklenecek !== '') {
    $urun = null;
    try {
        $urun = Database::fetch("SELECT id FROM products WHERE slug = ? AND is_active = 1", [$eklenecek]);
    } catch (Throwable $e) {
        error_log('Ürün aranamadı: ' . $e->getMessage());
    }

    if ($urun) {
        $donem = (string) ($_GET['billing_cycle'] ?? 'monthly');
        header('Location: client/cart.php?action=add&product_id=' . (int) $urun['id']
            . '&billing_cycle=' . urlencode($donem));
        exit;
    }

    /* Karşılığı yok: boş sepete düşürmek yerine doğru mağaza sayfasına gönder */
    $hedefler = [
        '/^(hosting|linux)/' => 'magaza.php?group=linux-hosting',
        '/^(windows)/' => 'magaza.php?group=windows-hosting',
        '/^(wordpress|wp)/' => 'magaza.php?group=wordpress-hosting',
        '/^ssl/' => 'magaza.php?group=ssl-sertifikasi',
        '/^(cloud|vps|vds)/' => 'vds-sunucu.php',
        '/^(dedicated|fiziksel|sunucu)/' => 'magaza.php?group=fiziksel-sunucu',
        '/^domain/' => 'alan-adi.php',
    ];
    $hedef = 'magaza.php';
    foreach ($hedefler as $kalip => $adres) {
        if (preg_match($kalip, $eklenecek)) {
            $hedef = $adres;
            break;
        }
    }
    header('Location: ' . $hedef);
    exit;
}

/* ---------- Alan adı: fiyat domain_pricing tablosundan gelir ---------- */
$alanAdi = trim((string) ($_GET['domain'] ?? ''));
if ($alanAdi !== '') {
    $uzanti = strtolower((string) strstr($alanAdi, '.'));
    $fiyat = null;

    if ($uzanti !== '') {
        try {
            $fiyat = Database::fetch(
                "SELECT extension, register_1yr FROM domain_pricing
                  WHERE is_active = 1 AND LOWER(extension) IN (?, ?)",
                [$uzanti, ltrim($uzanti, '.')]
            );
        } catch (Throwable $e) {
            error_log('Alan adı fiyatı okunamadı: ' . $e->getMessage());
        }
    }

    if ($fiyat && (float) $fiyat['register_1yr'] > 0) {
        $_SESSION['cart'][] = [
            'product_id' => null,
            'product_name' => 'Alan adı: ' . $alanAdi,
            'product_type' => 'domain',
            'billing_cycle' => 'annually',
            'domain' => $alanAdi,
            'price' => (float) $fiyat['register_1yr'],
            'setup_fee' => 0.0,
            'config_options' => [],
            'config_total' => 0.0,
            'added_at' => time(),
        ];
        header('Location: sepet.php');
        exit;
    }

    /* Fiyat tanımlı değil. Uydurma tutar yazmak yerine durumu söyle. */
    $uyari = 'Alan adı fiyatları henüz tanımlanmadığı için ' . $alanAdi
        . ' sepete eklenemedi. Lütfen bizimle iletişime geçin.';
    $uyariTipi = 'error';
}

/* ---------- Sepet işlemleri (POST + token) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gecerli = hash_equals((string) $_SESSION['sepet_token'], (string) ($_POST['token'] ?? ''));
    $islem = (string) ($_POST['islem'] ?? '');

    if (!$gecerli) {
        $uyari = 'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.';
        $uyariTipi = 'error';
    } elseif ($islem === 'kaldir') {
        Sepet::kaldir((string) ($_POST['anahtar'] ?? ''));
        header('Location: sepet.php');
        exit;
    } elseif ($islem === 'temizle') {
        Sepet::temizle();
        header('Location: sepet.php');
        exit;
    } elseif ($islem === 'promo_uygula') {
        $sonuc = Sepet::promosyonUygula((string) ($_POST['kod'] ?? ''));
        $_SESSION['sepet_uyari'] = $sonuc['ok']
            ? ['tip' => 'success', 'metin' => 'Promosyon kodu uygulandı.']
            : ['tip' => 'error', 'metin' => (string) $sonuc['hata']];
        header('Location: sepet.php');
        exit;
    } elseif ($islem === 'promo_kaldir') {
        Sepet::promosyonKaldir();
        header('Location: sepet.php');
        exit;
    }
}

if (!empty($_SESSION['sepet_uyari'])) {
    $uyari = (string) $_SESSION['sepet_uyari']['metin'];
    $uyariTipi = (string) $_SESSION['sepet_uyari']['tip'];
    unset($_SESSION['sepet_uyari']);
}

$ozet = Sepet::toplam();
$kalemler = $ozet['kalemler'];

$paraBicim = static fn(float $t): string => '₺' . number_format($t, 2, ',', '.');

$ikonlar = [
    'hosting' => 'fa-globe',
    'vds' => 'fa-server',
    'vps' => 'fa-server',
    'cloud' => 'fa-cloud',
    'ssl' => 'fa-lock',
    'domain' => 'fa-link',
    'dedicated' => 'fa-database',
];

require_once __DIR__ . '/theme/includes/header.php';
?>

<style>
    /* ==========================================
       Sepet - ct
       Kalemler solda, özet sağda ve yapışkan.
       Tüm renkler tasarım değişkenlerinden gelir.
       ========================================== */
    .ct {
        --ct-line: var(--border-color);
        --ct-surface: var(--bg-primary);
        --ct-accent: var(--primary);
        --ct-radius: 10px;
        padding: var(--space-6) 0 var(--space-7);
    }

    .ct a {
        color: inherit;
    }

    .ct-top {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: var(--space-3);
        margin-bottom: var(--space-5);
    }

    .ct-top h1 {
        font-size: clamp(22px, 1.4vw + 16px, 29px);
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--text-primary);
    }

    .ct-top p {
        margin-top: 5px;
        font-size: var(--text-sm);
        color: var(--text-muted);
    }

    .ct-back {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-size: var(--text-sm);
        font-weight: 600;
        color: var(--ct-accent);
    }

    .ct-back:hover {
        text-decoration: underline;
    }

    .ct-alert {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: var(--space-4);
        padding: 11px 14px;
        border-radius: 8px;
        font-size: var(--text-sm);
        line-height: 1.5;
        color: var(--text-primary);
    }

    .ct-alert.is-success {
        border: 1px solid color-mix(in srgb, var(--success) 40%, transparent);
        background: color-mix(in srgb, var(--success) 10%, transparent);
    }

    .ct-alert.is-error {
        border: 1px solid color-mix(in srgb, var(--danger) 40%, transparent);
        background: color-mix(in srgb, var(--danger) 10%, transparent);
    }

    .ct-alert.is-success i {
        color: var(--success);
    }

    .ct-alert.is-error i {
        color: var(--danger);
    }

    .ct-alert i {
        margin-top: 2px;
    }

    .ct-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 370px);
        gap: var(--space-5);
        align-items: start;
    }

    .ct-card {
        border: 1px solid var(--ct-line);
        border-radius: var(--ct-radius);
        background: var(--ct-surface);
        overflow: hidden;
    }

    .ct-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-3);
        padding: var(--space-3) var(--space-5);
        border-bottom: 1px solid var(--ct-line);
        background: color-mix(in srgb, var(--primary) 5%, transparent);
    }

    .ct-card-head h2 {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-primary);
    }

    .ct-clear {
        border: none;
        background: none;
        padding: 0;
        font-family: inherit;
        font-size: var(--text-xs);
        font-weight: 600;
        color: var(--text-muted);
        cursor: pointer;
    }

    .ct-clear:hover {
        color: var(--danger);
    }

    /* ===== Kalem ===== */
    .ct-item {
        display: grid;
        grid-template-columns: 38px minmax(0, 1fr) auto auto;
        align-items: start;
        gap: var(--space-4);
        padding: var(--space-4) var(--space-5);
        border-bottom: 1px solid var(--ct-line);
    }

    .ct-item:last-child {
        border-bottom: none;
    }

    .ct-item-icon {
        width: 38px;
        height: 38px;
        display: grid;
        place-items: center;
        border-radius: 8px;
        background: color-mix(in srgb, var(--primary) 10%, transparent);
        color: var(--ct-accent);
        font-size: 15px;
    }

    .ct-item h3 {
        font-size: var(--text-base);
        font-weight: 600;
        line-height: 1.3;
        color: var(--text-primary);
    }

    .ct-meta {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 7px;
        margin-top: 7px;
    }

    .ct-tag {
        display: inline-block;
        padding: 3px 9px;
        border-radius: 999px;
        border: 1px solid var(--ct-line);
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
    }

    .ct-tag.is-cycle {
        border-color: color-mix(in srgb, var(--primary) 35%, transparent);
        background: color-mix(in srgb, var(--primary) 8%, transparent);
        color: var(--ct-accent);
    }

    .ct-opts {
        margin-top: 9px;
        padding-left: 0;
        list-style: none;
    }

    .ct-opts li {
        display: flex;
        justify-content: space-between;
        gap: var(--space-3);
        padding: 3px 0;
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    .ct-item-price {
        text-align: right;
        white-space: nowrap;
    }

    .ct-item-price b {
        display: block;
        font-size: var(--text-base);
        font-weight: 700;
        color: var(--text-primary);
    }

    .ct-item-price span {
        display: block;
        margin-top: 3px;
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    .ct-remove {
        width: 32px;
        height: 32px;
        display: grid;
        place-items: center;
        border: 1px solid var(--ct-line);
        border-radius: 8px;
        background: transparent;
        color: var(--text-muted);
        cursor: pointer;
        transition: border-color 0.15s ease, color 0.15s ease;
    }

    .ct-remove:hover {
        border-color: var(--danger);
        color: var(--danger);
    }

    /* ===== Boş sepet ===== */
    .ct-empty {
        padding: var(--space-7) var(--space-5);
        text-align: center;
    }

    .ct-empty i {
        font-size: 34px;
        color: var(--text-gray);
    }

    .ct-empty h3 {
        margin: var(--space-3) 0 6px;
        font-size: var(--text-md);
        font-weight: 700;
        color: var(--text-primary);
    }

    .ct-empty p {
        max-width: 380px;
        margin: 0 auto var(--space-4);
        font-size: var(--text-sm);
        line-height: 1.6;
        color: var(--text-muted);
    }

    /* ===== Özet ===== */
    .ct-sum {
        position: sticky;
        top: 90px;
    }

    .ct-sum-body {
        padding: var(--space-5);
    }

    .ct-row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: var(--space-3);
        padding: 9px 0;
        font-size: var(--text-sm);
        border-bottom: 1px solid var(--border-light);
    }

    .ct-row span {
        color: var(--text-muted);
    }

    .ct-row b {
        font-weight: 600;
        color: var(--text-primary);
        white-space: nowrap;
    }

    .ct-row.is-discount b {
        color: var(--success);
    }

    .ct-total {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: var(--space-3);
        margin-top: var(--space-4);
        padding: var(--space-3) var(--space-4);
        border-radius: 8px;
        background: color-mix(in srgb, var(--primary) 7%, transparent);
    }

    .ct-total span {
        font-size: var(--text-sm);
        font-weight: 600;
        color: var(--text-primary);
    }

    .ct-total b {
        font-size: 23px;
        font-weight: 700;
        color: var(--ct-accent);
    }

    /* Promosyon */
    .ct-promo {
        margin-top: var(--space-4);
        padding-top: var(--space-4);
        border-top: 1px solid var(--ct-line);
    }

    .ct-promo label {
        display: block;
        margin-bottom: 6px;
        font-size: var(--text-xs);
        font-weight: 600;
        color: var(--text-secondary);
    }

    .ct-promo-row {
        display: flex;
        gap: 8px;
    }

    .ct-promo input {
        flex: 1;
        min-width: 0;
        padding: 9px 12px;
        border: 1px solid var(--ct-line);
        border-radius: 8px;
        background: var(--bg-body);
        color: var(--text-primary);
        font-family: inherit;
        font-size: var(--text-sm);
        text-transform: uppercase;
    }

    .ct-promo input:focus {
        outline: none;
        border-color: var(--ct-accent);
        box-shadow: var(--focus-ring);
    }

    .ct-promo button {
        padding: 9px 16px;
        border: 1px solid var(--ct-line);
        border-radius: 8px;
        background: transparent;
        color: var(--text-secondary);
        font-family: inherit;
        font-size: var(--text-sm);
        font-weight: 600;
        cursor: pointer;
        transition: border-color 0.15s ease, color 0.15s ease;
    }

    .ct-promo button:hover {
        border-color: var(--ct-accent);
        color: var(--ct-accent);
    }

    .ct-promo-active {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-3);
        padding: 10px 13px;
        border: 1px solid color-mix(in srgb, var(--success) 40%, transparent);
        border-radius: 8px;
        background: color-mix(in srgb, var(--success) 10%, transparent);
        font-size: var(--text-sm);
    }

    .ct-promo-active b {
        color: var(--text-primary);
    }

    .ct-promo-active span {
        display: block;
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    .ct-promo-active button {
        border: none;
        background: none;
        padding: 0;
        font-family: inherit;
        font-size: var(--text-xs);
        font-weight: 600;
        color: var(--text-muted);
        cursor: pointer;
    }

    .ct-promo-active button:hover {
        color: var(--danger);
    }

    /* Sarmalayıcıyla birlikte yazılır: aksi halde yukarıdaki
       ".ct a { color: inherit }" kuralı beyaz yazıyı eziyor ve
       açık temada mavi buton koyu yazılı kalıyordu. */
    .ct .ct-checkout {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        width: 100%;
        margin-top: var(--space-4);
        padding: 13px 24px;
        border: none;
        border-radius: 8px;
        background: var(--ct-accent);
        color: #fff;
        font-family: inherit;
        font-size: var(--text-base);
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.15s ease;
    }

    .ct-checkout:hover {
        background: var(--primary-dark);
    }

    .ct-safe {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: var(--space-3);
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    .ct-safe i {
        color: var(--success);
    }

    /* ==========================================
       Açık tema
       ========================================== */
    [data-theme="light"] .ct {
        --ct-line: #d3e2f8;
        background: #eff5fe;
    }

    [data-theme="light"] .ct-card {
        box-shadow:
            0 1px 2px rgba(36, 116, 245, 0.05),
            0 10px 26px -14px rgba(36, 116, 245, 0.28);
    }

    [data-theme="light"] .ct-promo input {
        background: #fff;
    }

    @media (max-width: 960px) {
        .ct-grid {
            grid-template-columns: 1fr;
        }

        .ct-sum {
            position: static;
        }
    }

    @media (max-width: 560px) {
        .ct-item {
            grid-template-columns: 38px minmax(0, 1fr) auto;
        }

        .ct-item-price {
            grid-column: 2 / -1;
            text-align: left;
        }

        .ct-remove {
            grid-row: 1;
            grid-column: 3;
        }
    }
</style>

<div class="ct">
    <div class="container">

        <div class="ct-top">
            <div>
                <h1>Sepetiniz</h1>
                <p><?= count($kalemler) ?> hizmet</p>
            </div>
            <a href="magaza.php" class="ct-back"><i class="fas fa-arrow-left"></i> Alışverişe devam et</a>
        </div>

        <?php if ($uyari !== ''): ?>
            <div class="ct-alert <?= $uyariTipi === 'success' ? 'is-success' : 'is-error' ?>">
                <i class="fas fa-<?= $uyariTipi === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
                <span><?= htmlspecialchars($uyari) ?></span>
            </div>
        <?php endif; ?>

        <div class="ct-grid">

            <!-- Kalemler -->
            <div class="ct-card">
                <div class="ct-card-head">
                    <h2>Sepetteki hizmetler</h2>
                    <?php if ($kalemler): ?>
                        <form method="post" onsubmit="return confirm('Sepet tamamen boşaltılsın mı?');">
                            <input type="hidden" name="token"
                                value="<?= htmlspecialchars($_SESSION['sepet_token']) ?>">
                            <input type="hidden" name="islem" value="temizle">
                            <button type="submit" class="ct-clear"><i class="fas fa-trash-can"></i> Sepeti
                                boşalt</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if (!$kalemler): ?>
                    <div class="ct-empty">
                        <i class="fas fa-basket-shopping"></i>
                        <h3>Sepetiniz boş</h3>
                        <p>Hizmetlerimizi inceleyip sepetinize ekleyebilirsiniz.</p>
                        <a href="magaza.php" class="ct-checkout" style="max-width: 260px; margin: 0 auto;">
                            <i class="fas fa-store"></i> Mağazaya git
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($kalemler as $k): ?>
                        <div class="ct-item">
                            <div class="ct-item-icon">
                                <i class="fas <?= $ikonlar[$k['tur']] ?? 'fa-box' ?>"></i>
                            </div>

                            <div>
                                <h3><?= htmlspecialchars($k['ad']) ?></h3>
                                <div class="ct-meta">
                                    <span class="ct-tag is-cycle"><?= htmlspecialchars($k['donem_adi']) ?></span>
                                    <?php if ($k['adet'] > 1): ?>
                                        <span class="ct-tag"><?= (int) $k['adet'] ?> adet</span>
                                    <?php endif; ?>
                                    <?php if ($k['kurulum'] > 0): ?>
                                        <span class="ct-tag">Kurulum <?= $paraBicim($k['kurulum']) ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($k['secenekler']): ?>
                                    <ul class="ct-opts">
                                        <?php foreach ($k['secenekler'] as $s): ?>
                                            <li>
                                                <span><?= htmlspecialchars((string) ($s['option_name'] ?? '')) ?>:
                                                    <?= htmlspecialchars((string) ($s['value_name'] ?? '')) ?></span>
                                                <?php if ((float) ($s['price'] ?? 0) > 0): ?>
                                                    <span>+<?= $paraBicim((float) $s['price']) ?></span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>

                            <div class="ct-item-price">
                                <b><?= $paraBicim($k['satir_ara'] + $k['satir_kurulum']) ?></b>
                                <?php if ($k['adet'] > 1): ?>
                                    <span><?= $paraBicim($k['birim'] + $k['secenek_toplam']) ?> ×
                                        <?= (int) $k['adet'] ?></span>
                                <?php else: ?>
                                    <span><?= htmlspecialchars(mb_strtolower($k['donem_adi'])) ?></span>
                                <?php endif; ?>
                            </div>

                            <form method="post">
                                <input type="hidden" name="token"
                                    value="<?= htmlspecialchars($_SESSION['sepet_token']) ?>">
                                <input type="hidden" name="islem" value="kaldir">
                                <input type="hidden" name="anahtar" value="<?= htmlspecialchars($k['anahtar']) ?>">
                                <button type="submit" class="ct-remove" title="Kaldır"
                                    aria-label="<?= htmlspecialchars($k['ad']) ?> kaldır">
                                    <i class="fas fa-trash-can"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Özet -->
            <div class="ct-sum">
                <div class="ct-card">
                    <div class="ct-card-head">
                        <h2>Sipariş özeti</h2>
                    </div>
                    <div class="ct-sum-body">
                        <div class="ct-row">
                            <span>Ara toplam</span>
                            <b><?= $paraBicim($ozet['ara']) ?></b>
                        </div>

                        <?php if ($ozet['kurulum'] > 0): ?>
                            <div class="ct-row">
                                <span>Kurulum ücreti</span>
                                <b><?= $paraBicim($ozet['kurulum']) ?></b>
                            </div>
                        <?php endif; ?>

                        <?php if ($ozet['indirim'] > 0): ?>
                            <div class="ct-row is-discount">
                                <span>İndirim
                                    (<?= htmlspecialchars((string) $ozet['promo_kod']) ?>)</span>
                                <b>−<?= $paraBicim($ozet['indirim']) ?></b>
                            </div>
                        <?php endif; ?>

                        <?php if ($ozet['vergi_orani'] > 0): ?>
                            <div class="ct-row">
                                <span>KDV
                                    (%<?= rtrim(rtrim(number_format($ozet['vergi_orani'], 2, ',', '.'), '0'), ',') ?>)</span>
                                <b><?= $paraBicim($ozet['vergi']) ?></b>
                            </div>
                        <?php endif; ?>

                        <div class="ct-total">
                            <span>Toplam</span>
                            <b><?= $paraBicim($ozet['genel']) ?></b>
                        </div>

                        <!-- Promosyon -->
                        <div class="ct-promo">
                            <?php if ($ozet['promo_kod'] !== null): ?>
                                <div class="ct-promo-active">
                                    <div>
                                        <b><?= htmlspecialchars($ozet['promo_kod']) ?></b>
                                        <span><?= htmlspecialchars((string) $ozet['promo_aciklama']) ?></span>
                                    </div>
                                    <form method="post">
                                        <input type="hidden" name="token"
                                            value="<?= htmlspecialchars($_SESSION['sepet_token']) ?>">
                                        <input type="hidden" name="islem" value="promo_kaldir">
                                        <button type="submit">Kaldır</button>
                                    </form>
                                </div>
                            <?php elseif ($kalemler): ?>
                                <form method="post">
                                    <input type="hidden" name="token"
                                        value="<?= htmlspecialchars($_SESSION['sepet_token']) ?>">
                                    <input type="hidden" name="islem" value="promo_uygula">
                                    <label for="kod">Promosyon kodu</label>
                                    <div class="ct-promo-row">
                                        <input type="text" id="kod" name="kod" maxlength="50" autocomplete="off"
                                            placeholder="KOD">
                                        <button type="submit">Uygula</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>

                        <?php if ($kalemler): ?>
                            <?php if (isset($_SESSION['client_id'])): ?>
                                <a href="client/checkout.php" class="ct-checkout">
                                    <i class="fas fa-lock"></i> Ödemeye geç
                                </a>
                            <?php else: ?>
                                <a href="client/index.php?redirect=checkout" class="ct-checkout">
                                    <i class="fas fa-right-to-bracket"></i> Giriş yap ve devam et
                                </a>
                            <?php endif; ?>

                            <div class="ct-safe">
                                <i class="fas fa-shield-halved"></i> Ödeme sayfası SSL ile korunur
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>
