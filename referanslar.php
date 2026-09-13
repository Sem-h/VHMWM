<?php
/**
 * VHM - Referanslar
 *
 * Kartlar `references` tablosundan gelir. Sayfada uydurma sayı yoktur;
 * eski sürüm "Mutlu Müşteri" rakamını $totalReferences + 500 ile üretiyordu.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Referanslar';
$pageDescription = 'Veri merkezi, bulut barındırma, yazılım ve hosting hizmetlerimizi tercih eden kurumlar.';

try {
    $referanslar = Database::fetchAll(
        "SELECT * FROM `references` WHERE is_active = 1 ORDER BY sort_order ASC, id DESC"
    );
} catch (Throwable $e) {
    error_log('Referanslar okunamadı: ' . $e->getMessage());
    $referanslar = [];
}

/** Türkçe harfleri de doğru çeviren slug */
$slug = static function (string $metin): string {
    $metin = str_replace(
        ['ç', 'Ç', 'ğ', 'Ğ', 'ı', 'I', 'İ', 'i', 'ö', 'Ö', 'ş', 'Ş', 'ü', 'Ü', '&'],
        ['c', 'c', 'g', 'g', 'i', 'i', 'i', 'i', 'o', 'o', 's', 's', 'u', 'u', 've'],
        $metin
    );
    $metin = mb_strtolower($metin, 'UTF-8');
    $metin = preg_replace('/[^a-z0-9]+/', '-', $metin) ?? '';
    return trim($metin, '-');
};

/* Kategoriler kartlardan türetilir; ikisi ayrışamaz */
$kategoriler = [];
foreach ($referanslar as $r) {
    $ad = trim((string) ($r['category'] ?? ''));
    if ($ad === '') {
        continue;
    }
    $kategoriler[$slug($ad)] = $ad;
}
asort($kategoriler);

$hizmetler = [
    ['fa-building-shield', 'Veri merkezi'],
    ['fa-cloud', 'Bulut barındırma'],
    ['fa-code', 'Yazılım'],
    ['fa-globe', 'Hosting ve alan adı'],
];

require_once __DIR__ . '/theme/includes/header.php';
?>

<style>
    /* ==========================================
       Referanslar - rf
       Renkler tasarım değişkenlerinden gelir; sayfa
       kendi :root değişkenlerini tanımlamaz.
       ========================================== */
    .rf {
        --rf-line: var(--border-color);
        --rf-surface: var(--bg-primary);
        --rf-accent: var(--primary);
        --rf-radius: 10px;
    }

    .rf a {
        color: inherit;
    }

    /* ===== Üst bilgi ===== */
    .rf-hero {
        position: relative;
        overflow: hidden;
        background: var(--gradient-hero);
        border-bottom: 1px solid var(--rf-line);
        padding: var(--space-7) 0;
    }

    .rf-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 55% 50% at 15% 25%, color-mix(in srgb, var(--primary) 16%, transparent) 0%, transparent 62%),
            radial-gradient(ellipse 45% 45% at 85% 10%, color-mix(in srgb, var(--secondary) 12%, transparent) 0%, transparent 58%);
        pointer-events: none;
    }

    .rf-hero>.container {
        position: relative;
        z-index: 1;
    }

    .rf-hero-inner {
        max-width: 700px;
        margin: 0 auto;
        text-align: center;
    }

    .rf-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 50px;
        border: 1px solid var(--rf-line);
        background: var(--rf-surface);
        font-size: var(--text-xs);
        font-weight: 600;
        color: var(--text-secondary);
    }

    .rf-eyebrow i {
        color: var(--rf-accent);
    }

    .rf-title {
        margin: var(--space-3) 0 0;
        font-size: clamp(26px, 2vw + 18px, 38px);
        font-weight: 700;
        letter-spacing: -0.02em;
        line-height: 1.2;
        color: var(--text-primary);
    }

    .rf-lead {
        max-width: 560px;
        margin: var(--space-3) auto 0;
        font-size: var(--text-base);
        line-height: 1.65;
        color: var(--text-muted);
    }

    /* Hizmet alanları - sayı uydurmak yerine ne yaptığımızı yazıyoruz */
    .rf-fields {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: var(--space-3) var(--space-5);
        margin-top: var(--space-5);
    }

    .rf-fields span {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: var(--text-sm);
        color: var(--text-secondary);
    }

    .rf-fields i {
        font-size: 13px;
        color: var(--rf-accent);
    }

    /* ===== Gövde ===== */
    .rf-body {
        padding: var(--space-7) 0;
    }

    /* Filtre */
    .rf-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: var(--space-5);
    }

    .rf-filter {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 15px;
        border: 1px solid var(--rf-line);
        border-radius: 50px;
        background: var(--rf-surface);
        font-family: inherit;
        font-size: var(--text-sm);
        font-weight: 600;
        color: var(--text-secondary);
        cursor: pointer;
        transition: border-color 0.15s ease, background-color 0.15s ease, color 0.15s ease;
    }

    .rf-filter:hover {
        border-color: var(--rf-accent);
    }

    .rf-filter.is-active {
        border-color: var(--rf-accent);
        background: color-mix(in srgb, var(--primary) 10%, transparent);
        color: var(--rf-accent);
    }

    .rf-filter small {
        font-size: 11px;
        font-weight: 700;
        opacity: 0.7;
    }

    /* Kart ızgarası */
    .rf-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
        gap: var(--space-4);
    }

    .rf-card {
        display: flex;
        flex-direction: column;
        padding: var(--space-4);
        border: 1px solid var(--rf-line);
        border-radius: var(--rf-radius);
        background: var(--rf-surface);
        text-align: left;
        font-family: inherit;
        cursor: pointer;
        transition: border-color 0.15s ease, transform 0.15s ease;
    }

    .rf-card:hover {
        border-color: var(--rf-accent);
        transform: translateY(-2px);
    }

    .rf-card[hidden] {
        display: none;
    }

    .rf-logo {
        height: 74px;
        display: grid;
        place-items: center;
        margin-bottom: var(--space-3);
        padding: var(--space-3);
        border-radius: 8px;
        background: var(--bg-body);
    }

    /* Koyu zemin için hazırlanmış logolar okunabilsin diye
       açık zeminde de koyu bir kutuya oturtulur */
    .rf-card.is-dark-logo .rf-logo {
        background: #0f172a;
    }

    .rf-logo img {
        max-width: 100%;
        max-height: 52px;
        object-fit: contain;
    }

    .rf-logo i {
        font-size: 26px;
        color: var(--text-gray);
    }

    .rf-card b {
        font-size: var(--text-base);
        font-weight: 600;
        line-height: 1.3;
        color: var(--text-primary);
    }

    .rf-card span {
        margin-top: 4px;
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    .rf-empty {
        padding: var(--space-7) var(--space-5);
        text-align: center;
        border: 1px dashed var(--rf-line);
        border-radius: var(--rf-radius);
    }

    .rf-empty i {
        font-size: 30px;
        color: var(--text-gray);
    }

    .rf-empty h3 {
        margin: var(--space-3) 0 6px;
        font-size: var(--text-md);
        font-weight: 700;
        color: var(--text-primary);
    }

    .rf-empty p {
        font-size: var(--text-sm);
        color: var(--text-muted);
    }

    /* ===== Ayrıntı penceresi ===== */
    .rf-modal {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: var(--space-4);
        background: rgba(15, 23, 42, 0.55);
    }

    .rf-modal.is-open {
        display: flex;
    }

    .rf-modal-box {
        width: 100%;
        max-width: 420px;
        border: 1px solid var(--rf-line);
        border-radius: var(--rf-radius);
        background: var(--rf-surface);
        overflow: hidden;
    }

    .rf-modal-head {
        display: flex;
        justify-content: flex-end;
        padding: 10px 10px 0;
    }

    .rf-modal-close {
        width: 30px;
        height: 30px;
        display: grid;
        place-items: center;
        border: 1px solid var(--rf-line);
        border-radius: 8px;
        background: transparent;
        color: var(--text-muted);
        cursor: pointer;
    }

    .rf-modal-close:hover {
        border-color: var(--danger);
        color: var(--danger);
    }

    .rf-modal-body {
        padding: var(--space-3) var(--space-5) var(--space-5);
        text-align: center;
    }

    .rf-modal-logo {
        height: 84px;
        display: grid;
        place-items: center;
        margin-bottom: var(--space-4);
        padding: var(--space-3);
        border-radius: 8px;
        background: var(--bg-body);
    }

    .rf-modal-logo.is-dark {
        background: #0f172a;
    }

    .rf-modal-logo img {
        max-width: 100%;
        max-height: 60px;
        object-fit: contain;
    }

    .rf-modal-body h3 {
        font-size: var(--text-md);
        font-weight: 700;
        color: var(--text-primary);
    }

    .rf-modal-cat {
        display: inline-block;
        margin-top: 7px;
        padding: 3px 11px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--primary) 10%, transparent);
        font-size: 11px;
        font-weight: 700;
        color: var(--rf-accent);
    }

    .rf-modal-desc {
        margin-top: var(--space-3);
        font-size: var(--text-sm);
        line-height: 1.6;
        color: var(--text-muted);
        white-space: pre-line;
    }

    /* Sarmalayıcıyla yazılır: ".rf a { color: inherit }" reseti
       aksi halde beyaz yazıyı eziyor. */
    .rf .rf-modal-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: var(--space-4);
        padding: 10px 20px;
        border-radius: 8px;
        background: var(--rf-accent);
        color: #fff;
        font-size: var(--text-sm);
        font-weight: 600;
    }

    .rf .rf-modal-link:hover {
        background: var(--primary-dark);
    }

    /* ===== Kapanış ===== */
    .rf-cta {
        padding: var(--space-6) var(--space-5);
        border: 1px solid var(--rf-line);
        border-radius: var(--rf-radius);
        background: var(--rf-surface);
        text-align: center;
    }

    .rf-cta h2 {
        font-size: clamp(19px, 1vw + 14px, 23px);
        font-weight: 700;
        color: var(--text-primary);
    }

    .rf-cta p {
        max-width: 520px;
        margin: 9px auto 0;
        font-size: var(--text-sm);
        line-height: 1.6;
        color: var(--text-muted);
    }

    .rf-cta-row {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: var(--space-4);
    }

    .rf .rf-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 11px 22px;
        border: 1px solid transparent;
        border-radius: 8px;
        font-size: var(--text-sm);
        font-weight: 600;
    }

    .rf .rf-btn-primary {
        background: var(--rf-accent);
        color: #fff;
    }

    .rf .rf-btn-primary:hover {
        background: var(--primary-dark);
    }

    .rf .rf-btn-outline {
        border-color: var(--rf-line);
        color: var(--text-secondary);
    }

    .rf .rf-btn-outline:hover {
        border-color: var(--rf-accent);
        color: var(--rf-accent);
    }

    /* ==========================================
       Açık tema
       ========================================== */
    [data-theme="light"] .rf {
        --rf-line: #d3e2f8;
        background: #eff5fe;
    }

    [data-theme="light"] .rf-hero {
        background: linear-gradient(180deg, #e4edfb 0%, #eff5fe 100%);
    }

    [data-theme="light"] .rf-card,
    [data-theme="light"] .rf-cta,
    [data-theme="light"] .rf-modal-box {
        box-shadow:
            0 1px 2px rgba(36, 116, 245, 0.05),
            0 10px 26px -14px rgba(36, 116, 245, 0.28);
    }

    @media (max-width: 640px) {
        .rf-grid {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        }
    }
</style>

<div class="rf">

    <section class="rf-hero">
        <div class="container">
            <div class="rf-hero-inner">
                <span class="rf-eyebrow"><i class="fas fa-handshake"></i> Referanslarımız</span>
                <h1 class="rf-title">Bize güvenen kurumlar</h1>
                <p class="rf-lead">
                    Altyapımızı kullanan kurumlardan bir bölümü. Logoya tıklayarak
                    hangi hizmeti aldıklarını görebilirsiniz.
                </p>

                <div class="rf-fields">
                    <?php foreach ($hizmetler as [$ikon, $ad]): ?>
                        <span><i class="fas <?= htmlspecialchars($ikon) ?>"></i> <?= htmlspecialchars($ad) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="rf-body">
        <div class="container">

            <?php if ($referanslar): ?>
                <?php if (count($kategoriler) > 1): ?>
                    <div class="rf-filters" id="rf-filtreler">
                        <button type="button" class="rf-filter is-active" data-filtre="tumu">
                            <i class="fas fa-table-cells-large"></i> Tümü
                            <small><?= count($referanslar) ?></small>
                        </button>
                        <?php foreach ($kategoriler as $anahtar => $ad):
                            $adet = 0;
                            foreach ($referanslar as $r) {
                                if ($slug((string) ($r['category'] ?? '')) === $anahtar) {
                                    $adet++;
                                }
                            }
                            ?>
                            <button type="button" class="rf-filter" data-filtre="<?= htmlspecialchars($anahtar) ?>">
                                <i class="fas fa-tag"></i> <?= htmlspecialchars($ad) ?>
                                <small><?= $adet ?></small>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="rf-grid" id="rf-liste">
                    <?php foreach ($referanslar as $r):
                        $kategoriAdi = trim((string) ($r['category'] ?? ''));
                        ?>
                        <button type="button"
                            class="rf-card <?= !empty($r['dark_logo']) ? 'is-dark-logo' : '' ?>"
                            data-filtre="<?= htmlspecialchars($slug($kategoriAdi)) ?>"
                            data-ad="<?= htmlspecialchars((string) $r['name']) ?>"
                            data-logo="<?= htmlspecialchars((string) ($r['logo'] ?? '')) ?>"
                            data-koyu="<?= !empty($r['dark_logo']) ? '1' : '0' ?>"
                            data-kategori="<?= htmlspecialchars($kategoriAdi) ?>"
                            data-site="<?= htmlspecialchars((string) ($r['website'] ?? '')) ?>"
                            data-aciklama="<?= htmlspecialchars((string) ($r['description'] ?? '')) ?>">
                            <span class="rf-logo">
                                <?php if (!empty($r['logo'])): ?>
                                    <img src="<?= htmlspecialchars((string) $r['logo']) ?>"
                                        alt="<?= htmlspecialchars((string) $r['name']) ?>" loading="lazy"
                                        referrerpolicy="no-referrer">
                                <?php else: ?>
                                    <i class="fas fa-building"></i>
                                <?php endif; ?>
                            </span>
                            <b><?= htmlspecialchars((string) $r['name']) ?></b>
                            <?php if ($kategoriAdi !== ''): ?>
                                <span><?= htmlspecialchars($kategoriAdi) ?></span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="rf-empty">
                    <i class="fas fa-building"></i>
                    <h3>Henüz referans eklenmedi</h3>
                    <p>Referanslar panelden eklendiğinde burada listelenir.</p>
                </div>
            <?php endif; ?>

            <div class="rf-cta" style="margin-top: var(--space-6);">
                <h2>Siz de aramıza katılın</h2>
                <p>Projeniz için uygun altyapıyı birlikte belirleyelim. Önce ihtiyacınızı dinler,
                    sonra teklif hazırlarız.</p>
                <div class="rf-cta-row">
                    <a href="iletisim.php#iletisim-formu" class="rf-btn rf-btn-primary">
                        <i class="fas fa-envelope"></i> Bize ulaşın
                    </a>
                    <a href="magaza.php" class="rf-btn rf-btn-outline">
                        <i class="fas fa-server"></i> Hizmetlerimiz
                    </a>
                </div>
            </div>

        </div>
    </section>

    <!-- Ayrıntı penceresi -->
    <div class="rf-modal" id="rf-modal" role="dialog" aria-modal="true" aria-labelledby="rf-modal-ad">
        <div class="rf-modal-box">
            <div class="rf-modal-head">
                <button type="button" class="rf-modal-close" id="rf-kapat" aria-label="Kapat">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
            <div class="rf-modal-body">
                <div class="rf-modal-logo" id="rf-modal-logo"></div>
                <h3 id="rf-modal-ad"></h3>
                <span class="rf-modal-cat" id="rf-modal-kategori" hidden></span>
                <p class="rf-modal-desc" id="rf-modal-aciklama"></p>
                <a class="rf-modal-link" id="rf-modal-site" target="_blank" rel="noopener noreferrer" hidden>
                    <i class="fas fa-arrow-up-right-from-square"></i> Web sitesini ziyaret et
                </a>
            </div>
        </div>
    </div>

</div>

<script>
    (function () {
        /* ---- Filtre: satır içi onclick ve global event yok ---- */
        var filtreler = document.getElementById('rf-filtreler');
        var kartlar = Array.prototype.slice.call(document.querySelectorAll('.rf-card'));

        if (filtreler) {
            filtreler.addEventListener('click', function (olay) {
                var dugme = olay.target.closest('.rf-filter');
                if (!dugme) {
                    return;
                }
                var secim = dugme.dataset.filtre;

                filtreler.querySelectorAll('.rf-filter').forEach(function (d) {
                    d.classList.toggle('is-active', d === dugme);
                });

                kartlar.forEach(function (k) {
                    k.hidden = secim !== 'tumu' && k.dataset.filtre !== secim;
                });
            });
        }

        /* ---- Ayrıntı penceresi ----
           İçerik textContent ve createElement ile yazılır; eski sürüm
           logo adresini innerHTML şablonuna gömdüğü için script
           çalıştırılabiliyordu. */
        var pencere = document.getElementById('rf-modal');
        var pLogo = document.getElementById('rf-modal-logo');
        var pAd = document.getElementById('rf-modal-ad');
        var pKategori = document.getElementById('rf-modal-kategori');
        var pAciklama = document.getElementById('rf-modal-aciklama');
        var pSite = document.getElementById('rf-modal-site');
        var sonOdak = null;

        function guvenliAdres(deger) {
            try {
                var u = new URL(deger, window.location.href);
                return (u.protocol === 'http:' || u.protocol === 'https:') ? u.href : '';
            } catch (e) {
                return '';
            }
        }

        function ac(kart) {
            sonOdak = kart;

            pLogo.textContent = '';
            pLogo.classList.toggle('is-dark', kart.dataset.koyu === '1');

            var logo = guvenliAdres(kart.dataset.logo || '');
            if (logo) {
                var img = document.createElement('img');
                img.src = logo;
                img.alt = kart.dataset.ad || '';
                img.referrerPolicy = 'no-referrer';
                pLogo.appendChild(img);
            } else {
                var ikon = document.createElement('i');
                ikon.className = 'fas fa-building';
                pLogo.appendChild(ikon);
            }

            pAd.textContent = kart.dataset.ad || '';

            var kategori = (kart.dataset.kategori || '').trim();
            pKategori.textContent = kategori;
            pKategori.hidden = kategori === '';

            var aciklama = (kart.dataset.aciklama || '').trim();
            pAciklama.textContent = aciklama || 'Bu referans için henüz açıklama eklenmedi.';

            var site = guvenliAdres(kart.dataset.site || '');
            pSite.hidden = site === '';
            if (site) {
                pSite.href = site;
            }

            pencere.classList.add('is-open');
            document.body.style.overflow = 'hidden';
            document.getElementById('rf-kapat').focus();
        }

        function kapat() {
            pencere.classList.remove('is-open');
            document.body.style.overflow = '';
            if (sonOdak) {
                sonOdak.focus();
            }
        }

        kartlar.forEach(function (k) {
            k.addEventListener('click', function () {
                ac(k);
            });
        });

        document.getElementById('rf-kapat').addEventListener('click', kapat);

        pencere.addEventListener('click', function (olay) {
            if (olay.target === pencere) {
                kapat();
            }
        });

        document.addEventListener('keydown', function (olay) {
            if (olay.key === 'Escape' && pencere.classList.contains('is-open')) {
                kapat();
            }
        });
    })();
</script>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>
