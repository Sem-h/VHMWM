<?php
/**
 * VHM - Yönetim paneli kabuğu (üst kısım)
 *
 * Menü artık burada yazılı değil; admin/includes/menu.php dosyasındaki
 * ağaçtan üretilir. Görünüm admin/assets/css/yonetim.css içindedir.
 *
 * Sayfalar bu dosyadan önce $pageTitle ve $currentPage değişkenlerini
 * tanımlar; $currentPage dosya adının uzantısız hâlidir.
 */

declare(strict_types=1);

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

/* Çıkış */
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

require_once dirname(__DIR__, 2) . '/includes/Settings.php';
require_once dirname(__DIR__, 2) . '/includes/Guvenlik.php';
require_once __DIR__ . '/menu.php';

/* Tema tercihi çerezde saklanır */
$yTema = ($_COOKIE['vhm_panel_tema'] ?? 'acik') === 'koyu' ? 'koyu' : 'acik';

$yDosya = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$yMenu = yonetimMenusu();
$yRozet = yonetimRozetleri();
$yAktifBolum = yonetimAktifBolum($yDosya);

/* Bölüm kapalıyken başlıkta gösterilecek toplam */
$yBolumRozet = [];
foreach ($yMenu as $anahtar => $bolum) {
    $toplam = 0;
    foreach ($bolum['ogeler'] as $oge) {
        if (isset($oge[3])) {
            $toplam += $yRozet[$oge[3]] ?? 0;
        }
    }
    $yBolumRozet[$anahtar] = $toplam;
}

/* Üst bardaki iz için bulunduğumuz sayfanın adı */
$yBolumAdi = $yMenu[$yAktifBolum]['ad'] ?? '';
$ySayfaAdi = $pageTitle ?? 'Yönetim';
foreach ($yMenu[$yAktifBolum]['ogeler'] ?? [] as $oge) {
    if ($oge[0] === $yDosya) {
        $ySayfaAdi = $oge[1];
        break;
    }
}

$yAdmin = (string) ($_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Yönetici');
$yBasHarf = mb_strtoupper(mb_substr(trim($yAdmin), 0, 1), 'UTF-8');
$ySiteAdi = Settings::get('site_name', defined('SITE_NAME') ? SITE_NAME : 'VHM');

/* Çıktı tamponlanır; footer.php kapatırken POST formlarına CSRF
   belirtecini gömer. Böylece her sayfanın formunu tek tek düzenlemek
   gerekmiyor. */
ob_start();
?>
<!DOCTYPE html>
<html lang="tr" data-tema="<?= htmlspecialchars($yTema) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php /* Durum değiştiren istekler bu belirteci taşır */ ?>
    <meta name="csrf-token" content="<?= htmlspecialchars(Guvenlik::token(), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'Yönetim') ?> &middot; <?= htmlspecialchars((string) $ySiteAdi) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/admin/assets/css/yonetim.css?v=1">
</head>

<body>
    <div class="yonetim">

        <!-- ================= Kenar çubuğu ================= -->
        <aside class="y-kenar" id="y-kenar">
            <a class="y-marka" href="dashboard.php">
                <span class="y-marka-logo">V</span>
                <span>
                    <b><?= htmlspecialchars((string) $ySiteAdi) ?></b>
                    <span>Yönetim paneli</span>
                </span>
            </a>

            <div class="y-ara">
                <div class="y-ara-kutu">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="search" id="y-menu-ara" placeholder="Menüde ara…"
                        autocomplete="off" aria-label="Menüde ara">
                </div>
            </div>

            <nav class="y-menu" id="y-menu" aria-label="Yönetim menüsü">
                <?php foreach ($yMenu as $anahtar => $bolum): ?>
                    <?php $acik = $anahtar === $yAktifBolum; ?>
                    <div class="y-bolum <?= $acik ? 'acik' : '' ?>" data-bolum="<?= htmlspecialchars($anahtar) ?>">
                        <button type="button" class="y-bolum-bas" aria-expanded="<?= $acik ? 'true' : 'false' ?>">
                            <i class="fas <?= htmlspecialchars($bolum['ikon']) ?>"></i>
                            <span class="y-bolum-ad"><?= htmlspecialchars($bolum['ad']) ?></span>
                            <?php if (($yBolumRozet[$anahtar] ?? 0) > 0): ?>
                                <span class="y-bolum-rozet"><?= (int) $yBolumRozet[$anahtar] ?></span>
                            <?php endif; ?>
                            <i class="fas fa-chevron-right y-bolum-ok"></i>
                        </button>

                        <div class="y-liste">
                            <?php foreach ($bolum['ogeler'] as $oge): ?>
                                <?php
                                $sayi = isset($oge[3]) ? ($yRozet[$oge[3]] ?? 0) : 0;
                                $aktif = $oge[0] === $yDosya;
                                ?>
                                <a class="y-oge <?= $aktif ? 'aktif' : '' ?>"
                                    href="/admin/<?= htmlspecialchars($oge[0]) ?>"
                                    <?= $aktif ? 'aria-current="page"' : '' ?>>
                                    <i class="fas <?= htmlspecialchars($oge[2]) ?>"></i>
                                    <span class="y-oge-ad"><?= htmlspecialchars($oge[1]) ?></span>
                                    <?php if ($sayi > 0): ?>
                                        <span class="y-rozet"><?= $sayi > 99 ? '99+' : $sayi ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <p class="y-bos-arama" id="y-bos-arama">Eşleşen menü bulunamadı.</p>
            </nav>
        </aside>

        <div class="y-perde" id="y-perde"></div>

        <!-- ================= Gövde ================= -->
        <div class="y-govde">

            <header class="y-ust">
                <button type="button" class="y-ust-menu" id="y-menu-ac" aria-label="Menüyü aç">
                    <i class="fas fa-bars"></i>
                </button>

                <div class="y-iz">
                    <?php if ($yBolumAdi !== '' && $yAktifBolum !== 'genel'): ?>
                        <span><?= htmlspecialchars($yBolumAdi) ?></span>
                        <i class="fas fa-chevron-right"></i>
                    <?php endif; ?>
                    <b><?= htmlspecialchars($ySayfaAdi) ?></b>
                </div>

                <div class="y-ust-eylem">
                    <a class="y-ikon-btn" href="/" target="_blank" rel="noopener"
                        title="Siteyi yeni sekmede aç">
                        <i class="fas fa-arrow-up-right-from-square"></i>
                    </a>
                    <button type="button" class="y-ikon-btn" id="y-tema" title="Temayı değiştir"
                        aria-label="Temayı değiştir">
                        <i class="fas <?= $yTema === 'koyu' ? 'fa-sun' : 'fa-moon' ?>"></i>
                    </button>
                    <a class="y-ikon-btn" href="?logout=1" title="Çıkış yap"
                        onclick="return confirm('Oturumu kapatmak istiyor musunuz?');">
                        <i class="fas fa-right-from-bracket"></i>
                    </a>
                    <span class="y-kullanici">
                        <span class="y-avatar"><?= htmlspecialchars($yBasHarf) ?></span>
                        <span><?= htmlspecialchars($yAdmin) ?></span>
                    </span>
                </div>
            </header>

            <main class="y-icerik">
