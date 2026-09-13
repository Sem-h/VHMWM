<?php
/**
 * VHM - Bilgi Bankası
 *
 * Tek dosya üç görünüm sunar:
 *   knowledgebase.php                 -> kategoriler ve popüler makaleler
 *   knowledgebase.php?kategori=slug   -> kategorideki makaleler
 *   knowledgebase.php?makale=slug     -> makale metni
 *   knowledgebase.php?q=...           -> arama sonuçları
 *
 * Eski sürümde kategoriler ve makale sayıları koda sabit yazılmıştı
 * ("Demo kategoriler"), toplamda 113 makale olduğu söyleniyordu ama
 * hiç makale yoktu ve hiçbir kart tıklanabilir değildi.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

$kategoriSlug = trim((string) ($_GET['kategori'] ?? ''));
$makaleSlug = trim((string) ($_GET['makale'] ?? ''));
$arama = trim((string) ($_GET['q'] ?? ''));

$kategoriler = [];
$makale = null;
$kategori = null;
$makaleler = [];
$populer = [];
$toplamMakale = 0;

try {
    $kategoriler = Database::fetchAll(
        "SELECT k.*, (SELECT COUNT(*) FROM kb_makaleler m
                       WHERE m.kategori_id = k.id AND m.is_active = 1) AS adet
           FROM kb_kategoriler k
          WHERE k.is_active = 1
          ORDER BY k.sort_order, k.ad"
    );
    $toplamMakale = (int) Database::fetchColumn("SELECT COUNT(*) FROM kb_makaleler WHERE is_active = 1");

    if ($makaleSlug !== '') {
        $makale = Database::fetch(
            "SELECT m.*, k.ad AS kategori_ad, k.slug AS kategori_slug, k.ikon AS kategori_ikon
               FROM kb_makaleler m
               JOIN kb_kategoriler k ON k.id = m.kategori_id
              WHERE m.slug = ? AND m.is_active = 1",
            [$makaleSlug]
        );

        if ($makale) {
            /* Görüntülenme sayacı - aynı oturumda bir kez artar */
            $sayilan = (array) ($_SESSION['kb_okunan'] ?? []);
            if (!in_array((int) $makale['id'], $sayilan, true)) {
                Database::query(
                    "UPDATE kb_makaleler SET goruntulenme = goruntulenme + 1 WHERE id = ?",
                    [(int) $makale['id']]
                );
                $sayilan[] = (int) $makale['id'];
                $_SESSION['kb_okunan'] = $sayilan;
                /* Satır güncellemeden önce okunmuştu; ekranda da artmış görünsün */
                $makale['goruntulenme'] = (int) $makale['goruntulenme'] + 1;
            }

            /* Aynı kategorideki diğer makaleler */
            $makaleler = Database::fetchAll(
                "SELECT baslik, slug FROM kb_makaleler
                  WHERE kategori_id = ? AND id <> ? AND is_active = 1
                  ORDER BY sort_order, baslik LIMIT 6",
                [(int) $makale['kategori_id'], (int) $makale['id']]
            );
        }
    } elseif ($arama !== '') {
        /* Basit ve öngörülebilir arama: başlık, özet ve metinde geçen ifade.
           Türkçe için FULLTEXT sözcük kökü ayrımı yapmadığından LIKE tercih
           edildi; makale sayısı bu ölçekte sorun çıkarmaz. */
        $desen = '%' . str_replace(['%', '_'], ['\%', '\_'], $arama) . '%';
        $makaleler = Database::fetchAll(
            "SELECT m.baslik, m.slug, m.ozet, k.ad AS kategori_ad, k.slug AS kategori_slug
               FROM kb_makaleler m
               JOIN kb_kategoriler k ON k.id = m.kategori_id
              WHERE m.is_active = 1
                AND (m.baslik LIKE ? OR m.ozet LIKE ? OR m.icerik LIKE ?)
              ORDER BY (m.baslik LIKE ?) DESC, m.goruntulenme DESC
              LIMIT 40",
            [$desen, $desen, $desen, $desen]
        );
    } elseif ($kategoriSlug !== '') {
        $kategori = Database::fetch(
            "SELECT * FROM kb_kategoriler WHERE slug = ? AND is_active = 1",
            [$kategoriSlug]
        );
        if ($kategori) {
            $makaleler = Database::fetchAll(
                "SELECT baslik, slug, ozet, goruntulenme FROM kb_makaleler
                  WHERE kategori_id = ? AND is_active = 1
                  ORDER BY sort_order, baslik",
                [(int) $kategori['id']]
            );
        }
    } else {
        $populer = Database::fetchAll(
            "SELECT m.baslik, m.slug, k.ad AS kategori_ad
               FROM kb_makaleler m
               JOIN kb_kategoriler k ON k.id = m.kategori_id
              WHERE m.is_active = 1
              ORDER BY m.goruntulenme DESC, m.id DESC
              LIMIT 6"
        );
    }
} catch (Throwable $e) {
    error_log('Bilgi bankası okunamadı: ' . $e->getMessage());
}

if ($makale) {
    $pageTitle = (string) $makale['baslik'];
    $pageDescription = (string) ($makale['ozet'] ?? '');
} elseif ($kategori) {
    $pageTitle = $kategori['ad'] . ' - Bilgi Bankası';
    $pageDescription = (string) ($kategori['aciklama'] ?? '');
} else {
    $pageTitle = 'Bilgi Bankası';
    $pageDescription = 'Kurulum, yapılandırma ve sorun giderme rehberleri.';
}

require_once __DIR__ . '/theme/includes/header.php';
?>

<style>
    /* ==========================================
       Bilgi Bankası - kb
       ========================================== */
    .kb {
        --kb-line: var(--border-color);
        --kb-surface: var(--bg-primary);
        --kb-accent: var(--primary);
        --kb-radius: 10px;
    }

    .kb a {
        color: inherit;
    }

    /* ===== Üst bilgi ===== */
    .kb-hero {
        position: relative;
        overflow: hidden;
        background: var(--gradient-hero);
        border-bottom: 1px solid var(--kb-line);
        padding: var(--space-7) 0;
    }

    .kb-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 55% 50% at 15% 25%, color-mix(in srgb, var(--primary) 16%, transparent) 0%, transparent 62%),
            radial-gradient(ellipse 45% 45% at 85% 10%, color-mix(in srgb, var(--secondary) 12%, transparent) 0%, transparent 58%);
        pointer-events: none;
    }

    .kb-hero>.container {
        position: relative;
        z-index: 1;
    }

    .kb-hero-inner {
        max-width: 700px;
        margin: 0 auto;
        text-align: center;
    }

    .kb-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 50px;
        border: 1px solid var(--kb-line);
        background: var(--kb-surface);
        font-size: var(--text-xs);
        font-weight: 600;
        color: var(--text-secondary);
    }

    .kb-eyebrow i {
        color: var(--kb-accent);
    }

    .kb-title {
        margin: var(--space-3) 0 0;
        font-size: clamp(26px, 2vw + 18px, 38px);
        font-weight: 700;
        letter-spacing: -0.02em;
        line-height: 1.2;
        color: var(--text-primary);
    }

    .kb-lead {
        max-width: 560px;
        margin: var(--space-3) auto 0;
        font-size: var(--text-base);
        line-height: 1.65;
        color: var(--text-muted);
    }

    /* Arama */
    .kb-search {
        display: flex;
        gap: 8px;
        max-width: 520px;
        margin: var(--space-5) auto 0;
    }

    .kb-search input {
        flex: 1;
        min-width: 0;
        padding: 12px 16px;
        border: 1px solid var(--kb-line);
        border-radius: 8px;
        background: var(--kb-surface);
        color: var(--text-primary);
        font-family: inherit;
        font-size: var(--text-base);
    }

    .kb-search input:focus {
        outline: none;
        border-color: var(--kb-accent);
        box-shadow: var(--focus-ring);
    }

    .kb .kb-search button {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 22px;
        border: none;
        border-radius: 8px;
        background: var(--kb-accent);
        color: #fff;
        font-family: inherit;
        font-size: var(--text-base);
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.15s ease;
    }

    .kb .kb-search button:hover {
        background: var(--primary-dark);
    }

    /* ===== Gövde ===== */
    .kb-body {
        padding: var(--space-7) 0;
    }

    .kb-head {
        max-width: 640px;
        margin-bottom: var(--space-5);
    }

    .kb-head h2 {
        font-size: clamp(20px, 1vw + 15px, 25px);
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--text-primary);
    }

    .kb-head p {
        margin-top: 6px;
        font-size: var(--text-sm);
        line-height: 1.6;
        color: var(--text-muted);
    }

    /* İz */
    .kb-crumb {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: var(--space-4);
        font-size: var(--text-sm);
        color: var(--text-muted);
    }

    .kb-crumb a {
        color: var(--kb-accent);
    }

    .kb-crumb a:hover {
        text-decoration: underline;
    }

    .kb-crumb i {
        font-size: 10px;
        opacity: 0.6;
    }

    /* Kategori kartları */
    .kb-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: var(--space-4);
    }

    .kb-cat {
        display: block;
        padding: var(--space-4);
        border: 1px solid var(--kb-line);
        border-radius: var(--kb-radius);
        background: var(--kb-surface);
        transition: border-color 0.15s ease, transform 0.15s ease;
    }

    .kb-cat:hover {
        border-color: var(--kb-accent);
        transform: translateY(-2px);
    }

    .kb-cat-icon {
        width: 36px;
        height: 36px;
        display: grid;
        place-items: center;
        margin-bottom: 11px;
        border-radius: 8px;
        background: color-mix(in srgb, var(--primary) 10%, transparent);
        color: var(--kb-accent);
        font-size: 15px;
    }

    .kb-cat h3 {
        font-size: var(--text-base);
        font-weight: 600;
        color: var(--text-primary);
    }

    .kb-cat p {
        margin-top: 5px;
        font-size: var(--text-xs);
        line-height: 1.55;
        color: var(--text-muted);
    }

    .kb-count {
        display: inline-block;
        margin-top: 11px;
        padding: 3px 10px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--primary) 9%, transparent);
        font-size: 11px;
        font-weight: 700;
        color: var(--kb-accent);
    }

    .kb-count.is-empty {
        background: var(--bg-secondary);
        color: var(--text-muted);
    }

    /* Makale listesi */
    .kb-list {
        border: 1px solid var(--kb-line);
        border-radius: var(--kb-radius);
        background: var(--kb-surface);
        overflow: hidden;
    }

    .kb-item {
        display: grid;
        grid-template-columns: 30px minmax(0, 1fr) auto;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-4) var(--space-5);
        border-bottom: 1px solid var(--kb-line);
        transition: background-color 0.15s ease;
    }

    .kb-item:last-child {
        border-bottom: none;
    }

    .kb-item:hover {
        background: color-mix(in srgb, var(--primary) 5%, transparent);
    }

    .kb-item>i:first-child {
        color: var(--kb-accent);
    }

    .kb-item b {
        display: block;
        font-size: var(--text-base);
        font-weight: 600;
        line-height: 1.35;
        color: var(--text-primary);
    }

    .kb-item span {
        display: block;
        margin-top: 4px;
        font-size: var(--text-sm);
        line-height: 1.55;
        color: var(--text-muted);
    }

    .kb-item-tag {
        display: inline-block;
        margin-top: 6px;
        padding: 2px 9px;
        border-radius: 999px;
        border: 1px solid var(--kb-line);
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
    }

    .kb-item>i:last-child {
        font-size: 12px;
        color: var(--text-gray);
    }

    /* Popüler */
    .kb-popular {
        margin-top: var(--space-7);
    }

    /* Boş durum */
    .kb-empty {
        padding: var(--space-7) var(--space-5);
        text-align: center;
        border: 1px dashed var(--kb-line);
        border-radius: var(--kb-radius);
    }

    .kb-empty i {
        font-size: 30px;
        color: var(--text-gray);
    }

    .kb-empty h3 {
        margin: var(--space-3) 0 6px;
        font-size: var(--text-md);
        font-weight: 700;
        color: var(--text-primary);
    }

    .kb-empty p {
        max-width: 420px;
        margin: 0 auto;
        font-size: var(--text-sm);
        line-height: 1.6;
        color: var(--text-muted);
    }

    /* ===== Makale ===== */
    .kb-article {
        max-width: 820px;
        margin: 0 auto;
    }

    .kb-doc {
        padding: var(--space-6);
        border: 1px solid var(--kb-line);
        border-radius: var(--kb-radius);
        background: var(--kb-surface);
    }

    .kb-doc h1 {
        font-size: clamp(22px, 1.4vw + 16px, 29px);
        font-weight: 700;
        letter-spacing: -0.02em;
        line-height: 1.25;
        color: var(--text-primary);
    }

    .kb-meta {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: var(--space-4);
        margin-top: var(--space-3);
        padding-bottom: var(--space-4);
        border-bottom: 1px solid var(--kb-line);
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    .kb-meta span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    /* Makale metni */
    .kb-content {
        margin-top: var(--space-5);
    }

    .kb-content h3 {
        margin: var(--space-5) 0 var(--space-3);
        font-size: var(--text-md);
        font-weight: 700;
        color: var(--text-primary);
    }

    .kb-content h4 {
        margin: var(--space-4) 0 7px;
        font-size: var(--text-sm);
        font-weight: 700;
        color: var(--text-primary);
    }

    .kb-content p {
        font-size: var(--text-sm);
        line-height: 1.75;
        color: var(--text-muted);
    }

    .kb-content p+p {
        margin-top: var(--space-3);
    }

    .kb-content ul,
    .kb-content ol {
        margin: var(--space-3) 0 0;
        padding-left: 20px;
    }

    .kb-content ul {
        padding-left: 0;
        list-style: none;
    }

    .kb-content ul li {
        position: relative;
        padding: 5px 0 5px 21px;
    }

    .kb-content ul li::before {
        content: '';
        position: absolute;
        left: 4px;
        top: 14px;
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: var(--kb-accent);
    }

    .kb-content ol li {
        padding: 5px 0;
    }

    .kb-content li {
        font-size: var(--text-sm);
        line-height: 1.7;
        color: var(--text-muted);
    }

    .kb-content strong {
        color: var(--text-primary);
    }

    .kb-content a {
        color: var(--kb-accent);
    }

    .kb-content a:hover {
        text-decoration: underline;
    }

    .kb-content code {
        padding: 2px 6px;
        border: 1px solid var(--kb-line);
        border-radius: 5px;
        background: var(--bg-body);
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 0.9em;
        color: var(--text-primary);
    }

    .kb-content pre {
        margin-top: var(--space-3);
        padding: var(--space-4);
        border: 1px solid var(--kb-line);
        border-radius: 8px;
        background: var(--bg-body);
        overflow-x: auto;
    }

    .kb-content pre code {
        padding: 0;
        border: none;
        background: none;
    }

    .kb-content blockquote {
        margin: var(--space-3) 0 0;
        padding: var(--space-3) var(--space-4);
        border-left: 3px solid var(--kb-accent);
        border-radius: 0 8px 8px 0;
        background: color-mix(in srgb, var(--primary) 5%, transparent);
    }

    .kb-content blockquote p {
        color: var(--text-secondary);
    }

    .kb-content table {
        width: 100%;
        margin-top: var(--space-3);
        border-collapse: collapse;
        font-size: var(--text-sm);
    }

    .kb-content th,
    .kb-content td {
        padding: 9px 12px;
        text-align: left;
        border: 1px solid var(--kb-line);
        color: var(--text-muted);
    }

    .kb-content th {
        background: color-mix(in srgb, var(--primary) 5%, transparent);
        color: var(--text-primary);
    }

    .kb-content img {
        max-width: 100%;
        height: auto;
        border-radius: 8px;
    }

    /* Sonraki adım */
    .kb-next {
        margin-top: var(--space-5);
        padding: var(--space-4) var(--space-5);
        border: 1px solid var(--kb-line);
        border-radius: var(--kb-radius);
        background: var(--kb-surface);
    }

    .kb-next h2 {
        margin-bottom: var(--space-3);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-muted);
    }

    .kb-next ul {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .kb-next li+li {
        margin-top: 3px;
    }

    .kb-next a {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 7px 9px;
        border-radius: 7px;
        font-size: var(--text-sm);
        color: var(--text-secondary);
        transition: background-color 0.15s ease, color 0.15s ease;
    }

    .kb-next a:hover {
        background: color-mix(in srgb, var(--primary) 7%, transparent);
        color: var(--kb-accent);
    }

    .kb-next i {
        font-size: 11px;
        color: var(--kb-accent);
    }

    /* Yardım kutusu */
    .kb-help {
        margin-top: var(--space-5);
        padding: var(--space-5);
        border: 1px solid var(--kb-line);
        border-radius: var(--kb-radius);
        background: var(--kb-surface);
        text-align: center;
    }

    .kb-help h2 {
        font-size: var(--text-md);
        font-weight: 700;
        color: var(--text-primary);
    }

    .kb-help p {
        max-width: 460px;
        margin: 7px auto 0;
        font-size: var(--text-sm);
        line-height: 1.6;
        color: var(--text-muted);
    }

    .kb .kb-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: var(--space-4);
        padding: 11px 22px;
        border-radius: 8px;
        background: var(--kb-accent);
        color: #fff;
        font-size: var(--text-sm);
        font-weight: 600;
    }

    .kb .kb-btn:hover {
        background: var(--primary-dark);
    }

    /* ==========================================
       Açık tema
       ========================================== */
    [data-theme="light"] .kb {
        --kb-line: #d3e2f8;
        background: #eff5fe;
    }

    [data-theme="light"] .kb-hero {
        background: linear-gradient(180deg, #e4edfb 0%, #eff5fe 100%);
    }

    [data-theme="light"] .kb-cat,
    [data-theme="light"] .kb-list,
    [data-theme="light"] .kb-doc,
    [data-theme="light"] .kb-next,
    [data-theme="light"] .kb-help {
        box-shadow:
            0 1px 2px rgba(36, 116, 245, 0.05),
            0 10px 26px -14px rgba(36, 116, 245, 0.28);
    }

    @media (max-width: 1000px) {
        .kb-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .kb-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 520px) {
        .kb-grid {
            grid-template-columns: 1fr;
        }

        .kb-search {
            flex-direction: column;
        }

        .kb .kb-search button {
            justify-content: center;
        }

        .kb-doc {
            padding: var(--space-4);
        }
    }
</style>

<div class="kb">

    <section class="kb-hero">
        <div class="container">
            <div class="kb-hero-inner">
                <span class="kb-eyebrow"><i class="fas fa-book-open"></i> Bilgi Bankası</span>
                <h1 class="kb-title">
                    <?= $makale ? htmlspecialchars((string) $makale['baslik']) : 'Aradığınız cevabı bulun' ?>
                </h1>
                <?php if (!$makale): ?>
                    <p class="kb-lead">
                        Kurulum, yapılandırma ve sorun giderme rehberleri.
                        <?= $toplamMakale > 0 ? $toplamMakale . ' makale yayında.' : '' ?>
                    </p>
                <?php endif; ?>

                <form class="kb-search" action="knowledgebase.php" method="get" role="search">
                    <input type="search" name="q" value="<?= htmlspecialchars($arama) ?>"
                        placeholder="Ne aramak istersiniz?" aria-label="Bilgi bankasında ara">
                    <button type="submit"><i class="fas fa-magnifying-glass"></i> Ara</button>
                </form>
            </div>
        </div>
    </section>

    <section class="kb-body">
        <div class="container">

            <?php if ($makaleSlug !== '' && !$makale): ?>
                <!-- Makale bulunamadı -->
                <div class="kb-empty">
                    <i class="fas fa-file-circle-question"></i>
                    <h3>Makale bulunamadı</h3>
                    <p>Aradığınız makale yayından kaldırılmış olabilir.</p>
                    <a href="knowledgebase.php" class="kb-btn"><i class="fas fa-arrow-left"></i> Bilgi bankasına dön</a>
                </div>

            <?php elseif ($makale): ?>
                <!-- ===== Makale ===== -->
                <div class="kb-article">
                    <div class="kb-crumb">
                        <a href="knowledgebase.php">Bilgi Bankası</a>
                        <i class="fas fa-chevron-right"></i>
                        <a href="knowledgebase.php?kategori=<?= urlencode((string) $makale['kategori_slug']) ?>">
                            <?= htmlspecialchars((string) $makale['kategori_ad']) ?>
                        </a>
                    </div>

                    <article class="kb-doc">
                        <h1><?= htmlspecialchars((string) $makale['baslik']) ?></h1>
                        <div class="kb-meta">
                            <span>
                                <i class="fas <?= htmlspecialchars((string) $makale['kategori_ikon']) ?>"></i>
                                <?= htmlspecialchars((string) $makale['kategori_ad']) ?>
                            </span>
                            <span>
                                <i class="fas fa-clock-rotate-left"></i>
                                Güncelleme:
                                <?= date('d.m.Y', strtotime((string) $makale['updated_at'])) ?>
                            </span>
                            <span>
                                <i class="fas fa-eye"></i>
                                <?= number_format((int) $makale['goruntulenme'], 0, ',', '.') ?> görüntülenme
                            </span>
                        </div>

                        <?php /* İçerik panelde temizlenerek kaydedilir; burada olduğu gibi basılır */ ?>
                        <div class="kb-content"><?= $makale['icerik'] ?></div>
                    </article>

                    <?php if ($makaleler): ?>
                        <div class="kb-next">
                            <h2>Aynı kategoriden</h2>
                            <ul>
                                <?php foreach ($makaleler as $d): ?>
                                    <li>
                                        <a href="knowledgebase.php?makale=<?= urlencode((string) $d['slug']) ?>">
                                            <i class="fas fa-file-lines"></i>
                                            <?= htmlspecialchars((string) $d['baslik']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="kb-help">
                        <h2>Sorununuz çözülmedi mi?</h2>
                        <p>Destek ekibimiz hizmetinize özel olarak yardımcı olabilir.</p>
                        <a href="client/tickets.php" class="kb-btn">
                            <i class="fas fa-headset"></i> Destek talebi açın
                        </a>
                    </div>
                </div>

            <?php elseif ($arama !== ''): ?>
                <!-- ===== Arama sonuçları ===== -->
                <div class="kb-crumb">
                    <a href="knowledgebase.php">Bilgi Bankası</a>
                    <i class="fas fa-chevron-right"></i>
                    <span>Arama</span>
                </div>

                <div class="kb-head">
                    <h2>"<?= htmlspecialchars($arama) ?>" için <?= count($makaleler) ?> sonuç</h2>
                </div>

                <?php if (!$makaleler): ?>
                    <div class="kb-empty">
                        <i class="fas fa-magnifying-glass"></i>
                        <h3>Sonuç bulunamadı</h3>
                        <p>Farklı bir ifadeyle arayabilir ya da kategorilere göz atabilirsiniz.
                            Aradığınız konu yoksa destek talebi açın.</p>
                        <a href="knowledgebase.php" class="kb-btn">
                            <i class="fas fa-table-cells-large"></i> Kategorilere dön
                        </a>
                    </div>
                <?php else: ?>
                    <div class="kb-list">
                        <?php foreach ($makaleler as $a): ?>
                            <a class="kb-item" href="knowledgebase.php?makale=<?= urlencode((string) $a['slug']) ?>">
                                <i class="fas fa-file-lines"></i>
                                <span>
                                    <b><?= htmlspecialchars((string) $a['baslik']) ?></b>
                                    <span><?= htmlspecialchars((string) ($a['ozet'] ?? '')) ?></span>
                                    <span class="kb-item-tag"><?= htmlspecialchars((string) $a['kategori_ad']) ?></span>
                                </span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($kategoriSlug !== ''): ?>
                <!-- ===== Kategori ===== -->
                <?php if (!$kategori): ?>
                    <div class="kb-empty">
                        <i class="fas fa-folder-open"></i>
                        <h3>Kategori bulunamadı</h3>
                        <p>Bu kategori kaldırılmış olabilir.</p>
                        <a href="knowledgebase.php" class="kb-btn"><i class="fas fa-arrow-left"></i> Geri dön</a>
                    </div>
                <?php else: ?>
                    <div class="kb-crumb">
                        <a href="knowledgebase.php">Bilgi Bankası</a>
                        <i class="fas fa-chevron-right"></i>
                        <span><?= htmlspecialchars((string) $kategori['ad']) ?></span>
                    </div>

                    <div class="kb-head">
                        <h2><?= htmlspecialchars((string) $kategori['ad']) ?></h2>
                        <?php if (!empty($kategori['aciklama'])): ?>
                            <p><?= htmlspecialchars((string) $kategori['aciklama']) ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if (!$makaleler): ?>
                        <div class="kb-empty">
                            <i class="fas fa-file-circle-plus"></i>
                            <h3>Bu kategoride henüz makale yok</h3>
                            <p>İhtiyacınız olan konuyu destek talebi açarak bize bildirebilirsiniz.</p>
                            <a href="client/tickets.php" class="kb-btn">
                                <i class="fas fa-headset"></i> Destek talebi açın
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="kb-list">
                            <?php foreach ($makaleler as $a): ?>
                                <a class="kb-item" href="knowledgebase.php?makale=<?= urlencode((string) $a['slug']) ?>">
                                    <i class="fas fa-file-lines"></i>
                                    <span>
                                        <b><?= htmlspecialchars((string) $a['baslik']) ?></b>
                                        <span><?= htmlspecialchars((string) ($a['ozet'] ?? '')) ?></span>
                                    </span>
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            <?php else: ?>
                <!-- ===== Kategori listesi ===== -->
                <?php if (!$kategoriler): ?>
                    <div class="kb-empty">
                        <i class="fas fa-book"></i>
                        <h3>Bilgi bankası henüz hazırlanıyor</h3>
                        <p>İçerikler eklendiğinde burada listelenecek.</p>
                        <a href="client/tickets.php" class="kb-btn">
                            <i class="fas fa-headset"></i> Destek talebi açın
                        </a>
                    </div>
                <?php else: ?>
                    <div class="kb-head">
                        <h2>Kategoriler</h2>
                        <p>Konu başlığına göz atın ya da yukarıdaki kutudan arayın.</p>
                    </div>

                    <div class="kb-grid">
                        <?php foreach ($kategoriler as $k): ?>
                            <a class="kb-cat" href="knowledgebase.php?kategori=<?= urlencode((string) $k['slug']) ?>">
                                <span class="kb-cat-icon"><i
                                        class="fas <?= htmlspecialchars((string) $k['ikon']) ?>"></i></span>
                                <h3><?= htmlspecialchars((string) $k['ad']) ?></h3>
                                <?php if (!empty($k['aciklama'])): ?>
                                    <p><?= htmlspecialchars((string) $k['aciklama']) ?></p>
                                <?php endif; ?>
                                <span class="kb-count <?= (int) $k['adet'] === 0 ? 'is-empty' : '' ?>">
                                    <?= (int) $k['adet'] === 0 ? 'Yakında' : (int) $k['adet'] . ' makale' ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($populer): ?>
                        <div class="kb-popular">
                            <div class="kb-head">
                                <h2>En çok okunanlar</h2>
                            </div>
                            <div class="kb-list">
                                <?php foreach ($populer as $a): ?>
                                    <a class="kb-item"
                                        href="knowledgebase.php?makale=<?= urlencode((string) $a['slug']) ?>">
                                        <i class="fas fa-file-lines"></i>
                                        <span>
                                            <b><?= htmlspecialchars((string) $a['baslik']) ?></b>
                                            <span class="kb-item-tag">
                                                <?= htmlspecialchars((string) $a['kategori_ad']) ?>
                                            </span>
                                        </span>
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </section>

</div>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>
