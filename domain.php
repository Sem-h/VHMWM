<?php
/**
 * VHM - Alan Adı Sorgulama ve Kayıt
 *
 * Uzantılar ve fiyatlar domain_pricing tablosundan gelir. Müsaitlik
 * DomainNameAPI registrar modülünden sorulur; modül yapılandırılmamışsa
 * durum "bilinmiyor" olarak gösterilir. Eski sürümde hem müsaitlik hem
 * fiyat rand() ile üretiliyordu.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Settings.php';
require_once __DIR__ . '/includes/AlanAdi.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Alan Adı Kayıt ve Transfer';
$pageDescription = 'Alan adı sorgulama, kayıt, transfer ve yenileme. Fiyatlar ve uzantılar panelden yönetilir.';

$uzantilar = AlanAdi::uzantilar();
$apiHazir = AlanAdi::apiHazir();

$sorgu = trim((string) ($_GET['sorgu'] ?? $_GET['query'] ?? ''));
$ayrik = ['ad' => '', 'uzanti' => null];
$sonuclar = [];
$hata = '';

if ($sorgu !== '') {
    $ayrik = AlanAdi::ayikla($sorgu);

    if (mb_strlen($ayrik['ad']) < 2) {
        $hata = 'Alan adı en az 2 karakter olmalı.';
    } elseif (!$uzantilar) {
        $hata = 'Uzantı fiyatları henüz tanımlanmadığı için sorgulama yapılamıyor.';
    } else {
        /* Yazılan uzantı varsa önce o, sonra diğerleri */
        $sira = array_keys($uzantilar);
        if ($ayrik['uzanti'] !== null && isset($uzantilar[$ayrik['uzanti']])) {
            $sira = array_merge(
                [$ayrik['uzanti']],
                array_values(array_diff($sira, [$ayrik['uzanti']]))
            );
        }
        $sira = array_slice($sira, 0, 12);

        $sonuclar = AlanAdi::sorgula($ayrik['ad'], $sira);
    }
}

$paraBicim = static fn(float $t): string => '₺' . number_format($t, 2, ',', '.');

$avantajlar = [
    ['fa-user-shield', 'WHOIS gizliliği', 'Kayıt bilgileriniz herkese açık sorgularda gizlenir.'],
    ['fa-lock', 'Transfer kilidi', 'Alan adınız izniniz olmadan başka sağlayıcıya taşınamaz.'],
    ['fa-rotate', 'Otomatik yenileme', 'Süre dolmadan önce yenilenir, alan adı düşmez.'],
    ['fa-server', 'DNS yönetimi', 'A, CNAME, MX ve TXT kayıtlarını panelden düzenlersiniz.'],
];

require_once __DIR__ . '/theme/includes/header.php';
?>

<style>
    /* ==========================================
       Alan adı - dm
       Renkler tasarım değişkenlerinden gelir.
       ========================================== */
    .dm {
        --dm-line: var(--border-color);
        --dm-surface: var(--bg-primary);
        --dm-accent: var(--primary);
        --dm-radius: 10px;
    }

    .dm a {
        color: inherit;
    }

    /* ===== Üst bilgi ===== */
    .dm-hero {
        position: relative;
        overflow: hidden;
        background: var(--gradient-hero);
        border-bottom: 1px solid var(--dm-line);
        padding: var(--space-7) 0;
    }

    .dm-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 55% 50% at 15% 25%, color-mix(in srgb, var(--primary) 16%, transparent) 0%, transparent 62%),
            radial-gradient(ellipse 45% 45% at 85% 10%, color-mix(in srgb, var(--secondary) 12%, transparent) 0%, transparent 58%);
        pointer-events: none;
    }

    .dm-hero>.container {
        position: relative;
        z-index: 1;
    }

    .dm-hero-inner {
        max-width: 700px;
        margin: 0 auto;
        text-align: center;
    }

    .dm-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 50px;
        border: 1px solid var(--dm-line);
        background: var(--dm-surface);
        font-size: var(--text-xs);
        font-weight: 600;
        color: var(--text-secondary);
    }

    .dm-eyebrow i {
        color: var(--dm-accent);
    }

    .dm-title {
        margin: var(--space-3) 0 0;
        font-size: clamp(26px, 2vw + 18px, 38px);
        font-weight: 700;
        letter-spacing: -0.02em;
        line-height: 1.2;
        color: var(--text-primary);
    }

    .dm-lead {
        max-width: 560px;
        margin: var(--space-3) auto 0;
        font-size: var(--text-base);
        line-height: 1.65;
        color: var(--text-muted);
    }

    /* Arama */
    .dm-search {
        display: flex;
        gap: 8px;
        max-width: 560px;
        margin: var(--space-5) auto 0;
    }

    .dm-search input {
        flex: 1;
        min-width: 0;
        padding: 13px 16px;
        border: 1px solid var(--dm-line);
        border-radius: 8px;
        background: var(--dm-surface);
        color: var(--text-primary);
        font-family: inherit;
        font-size: var(--text-base);
    }

    .dm-search input:focus {
        outline: none;
        border-color: var(--dm-accent);
        box-shadow: var(--focus-ring);
    }

    .dm .dm-search button {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 13px 24px;
        border: none;
        border-radius: 8px;
        background: var(--dm-accent);
        color: #fff;
        font-family: inherit;
        font-size: var(--text-base);
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.15s ease;
    }

    .dm .dm-search button:hover {
        background: var(--primary-dark);
    }

    .dm-hint {
        margin-top: var(--space-3);
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    /* ===== Bölümler ===== */
    .dm-section {
        padding: var(--space-7) 0;
    }

    .dm-section.is-alt {
        background: var(--dm-surface);
        border-top: 1px solid var(--dm-line);
        border-bottom: 1px solid var(--dm-line);
    }

    .dm-head {
        max-width: 640px;
        margin-bottom: var(--space-5);
    }

    .dm-head h2 {
        font-size: clamp(20px, 1vw + 15px, 25px);
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--text-primary);
    }

    .dm-head p {
        margin-top: 6px;
        font-size: var(--text-sm);
        line-height: 1.6;
        color: var(--text-muted);
    }

    .dm-alert {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: var(--space-4);
        padding: 11px 14px;
        border-radius: 8px;
        font-size: var(--text-sm);
        line-height: 1.5;
        color: var(--text-primary);
        border: 1px solid color-mix(in srgb, var(--warning) 40%, transparent);
        background: color-mix(in srgb, var(--warning) 10%, transparent);
    }

    .dm-alert i {
        margin-top: 2px;
        color: var(--warning);
    }

    /* ===== Sonuç listesi ===== */
    .dm-results {
        border: 1px solid var(--dm-line);
        border-radius: var(--dm-radius);
        background: var(--dm-surface);
        overflow: hidden;
    }

    .dm-result {
        display: grid;
        grid-template-columns: 26px minmax(0, 1fr) auto auto;
        align-items: center;
        gap: var(--space-4);
        padding: var(--space-4) var(--space-5);
        border-bottom: 1px solid var(--dm-line);
    }

    .dm-result:last-child {
        border-bottom: none;
    }

    .dm-result i.dm-state {
        font-size: 17px;
    }

    .dm-result.is-free i.dm-state {
        color: var(--success);
    }

    .dm-result.is-taken i.dm-state {
        color: var(--text-gray);
    }

    .dm-result.is-unknown i.dm-state {
        color: var(--warning);
    }

    .dm-result b {
        display: block;
        font-size: var(--text-base);
        font-weight: 600;
        color: var(--text-primary);
        word-break: break-all;
    }

    .dm-result span.dm-state-text {
        display: block;
        margin-top: 3px;
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    .dm-price {
        text-align: right;
        white-space: nowrap;
    }

    .dm-price b {
        font-size: var(--text-base);
        font-weight: 700;
        color: var(--text-primary);
    }

    .dm-price span {
        display: block;
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    .dm .dm-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 16px;
        border-radius: 8px;
        border: 1px solid transparent;
        font-family: inherit;
        font-size: var(--text-sm);
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }

    /* Sarmalayıcıyla birlikte yazılır: ".dm a { color: inherit }" reseti
       aksi halde beyaz yazıyı eziyor ve açık temada koyu yazı çıkıyor. */
    .dm .dm-btn-primary {
        background: var(--dm-accent);
        color: #fff;
    }

    .dm .dm-btn-primary:hover {
        background: var(--primary-dark);
    }

    .dm .dm-btn-outline {
        background: transparent;
        border-color: var(--dm-line);
        color: var(--text-secondary);
    }

    .dm .dm-btn-outline:hover {
        border-color: var(--dm-accent);
        color: var(--dm-accent);
    }

    /* ===== Fiyat tablosu ===== */
    .dm-table-wrap {
        border: 1px solid var(--dm-line);
        border-radius: var(--dm-radius);
        background: var(--dm-surface);
        overflow-x: auto;
    }

    .dm-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 560px;
    }

    .dm-table th,
    .dm-table td {
        padding: 12px var(--space-5);
        text-align: left;
        font-size: var(--text-sm);
        border-bottom: 1px solid var(--border-light);
    }

    .dm-table thead th {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-muted);
        border-bottom: 1px solid var(--dm-line);
        background: color-mix(in srgb, var(--primary) 5%, transparent);
    }

    .dm-table tbody th {
        font-weight: 700;
        color: var(--dm-accent);
    }

    .dm-table td {
        color: var(--text-muted);
    }

    .dm-table td.dm-num {
        text-align: right;
        font-weight: 600;
        color: var(--text-primary);
        white-space: nowrap;
    }

    .dm-table tr:last-child th,
    .dm-table tr:last-child td {
        border-bottom: none;
    }

    .dm-empty {
        padding: var(--space-6);
        text-align: center;
        border: 1px dashed var(--dm-line);
        border-radius: var(--dm-radius);
        color: var(--text-muted);
        font-size: var(--text-sm);
        line-height: 1.6;
    }

    /* ===== Avantajlar ===== */
    .dm-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: var(--space-4);
    }

    .dm-tile {
        padding: var(--space-4);
        border: 1px solid var(--dm-line);
        border-radius: var(--dm-radius);
        background: var(--bg-body);
    }

    .dm-tile-icon {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        margin-bottom: 10px;
        border-radius: 8px;
        background: color-mix(in srgb, var(--primary) 10%, transparent);
        color: var(--dm-accent);
        font-size: 14px;
    }

    .dm-tile h4 {
        margin-bottom: 6px;
        font-size: var(--text-sm);
        font-weight: 700;
        color: var(--text-primary);
    }

    .dm-tile p {
        font-size: var(--text-xs);
        line-height: 1.55;
        color: var(--text-muted);
    }

    /* ==========================================
       Açık tema
       ========================================== */
    [data-theme="light"] .dm {
        --dm-line: #d3e2f8;
        background: #eff5fe;
    }

    [data-theme="light"] .dm-hero {
        background: linear-gradient(180deg, #e4edfb 0%, #eff5fe 100%);
    }

    [data-theme="light"] .dm-section.is-alt {
        background: #e4edfb;
    }

    [data-theme="light"] .dm-results,
    [data-theme="light"] .dm-table-wrap {
        box-shadow:
            0 1px 2px rgba(36, 116, 245, 0.05),
            0 10px 26px -14px rgba(36, 116, 245, 0.28);
    }

    [data-theme="light"] .dm-tile {
        background: #fff;
    }

    @media (max-width: 860px) {
        .dm-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .dm-search {
            flex-direction: column;
        }

        .dm .dm-search button {
            justify-content: center;
        }

        .dm-result {
            grid-template-columns: 26px minmax(0, 1fr);
        }

        .dm-price,
        .dm-result .dm-btn {
            grid-column: 2;
            text-align: left;
        }

        .dm-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="dm">

    <section class="dm-hero">
        <div class="container">
            <div class="dm-hero-inner">
                <span class="dm-eyebrow"><i class="fas fa-link"></i> Alan adı kayıt ve transfer</span>
                <h1 class="dm-title">Alan adınızı alın</h1>
                <p class="dm-lead">
                    Aklınızdaki adı yazın; tanımlı uzantılarda durumunu ve yıllık ücretini görün.
                </p>

                <form class="dm-search" action="domain.php" method="get">
                    <input type="text" name="sorgu" value="<?= htmlspecialchars($sorgu) ?>"
                        placeholder="ornek veya ornek.com.tr" autocomplete="off" spellcheck="false" dir="ltr">
                    <button type="submit"><i class="fas fa-magnifying-glass"></i> Sorgula</button>
                </form>

                <?php if (!$apiHazir): ?>
                    <p class="dm-hint">
                        Müsaitlik sorgusu için alan adı sağlayıcısı bağlantısı gerekir.
                        Şu an yalnızca fiyatlar gösteriliyor.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Sonuçlar -->
    <?php if ($sorgu !== ''): ?>
        <section class="dm-section">
            <div class="container">
                <div class="dm-head">
                    <h2>
                        <?= $ayrik['ad'] !== '' ? '"' . htmlspecialchars($ayrik['ad']) . '" sonuçları' : 'Sonuçlar' ?>
                    </h2>
                    <?php if ($sonuclar): ?>
                        <p><?= count($sonuclar) ?> uzantı listelendi.</p>
                    <?php endif; ?>
                </div>

                <?php if ($hata !== ''): ?>
                    <div class="dm-alert">
                        <i class="fas fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($hata) ?></span>
                    </div>
                <?php elseif (!$apiHazir): ?>
                    <div class="dm-alert">
                        <i class="fas fa-circle-info"></i>
                        <span>Alan adı sağlayıcısı bağlantısı yapılandırılmadığı için müsaitlik kontrol
                            edilemedi. Aşağıdaki ücretler geçerlidir; kayıt için talep oluşturabilirsiniz.</span>
                    </div>
                <?php endif; ?>

                <?php if ($sonuclar): ?>
                    <div class="dm-results">
                        <?php foreach ($sonuclar as $s):
                            $durumSinif = match ($s['durum']) {
                                AlanAdi::MUSAIT => 'is-free',
                                AlanAdi::KAYITLI => 'is-taken',
                                default => 'is-unknown',
                            };
                            $durumIkon = match ($s['durum']) {
                                AlanAdi::MUSAIT => 'fa-circle-check',
                                AlanAdi::KAYITLI => 'fa-circle-xmark',
                                default => 'fa-circle-question',
                            };
                            $durumMetin = match ($s['durum']) {
                                AlanAdi::MUSAIT => 'Müsait',
                                AlanAdi::KAYITLI => 'Kayıtlı',
                                default => 'Müsaitlik kontrol edilemedi',
                            };
                            ?>
                            <div class="dm-result <?= $durumSinif ?>">
                                <i class="fas <?= $durumIkon ?> dm-state"></i>

                                <div>
                                    <b dir="ltr"><?= htmlspecialchars($s['alan_adi']) ?></b>
                                    <span class="dm-state-text"><?= $durumMetin ?></span>
                                </div>

                                <div class="dm-price">
                                    <?php if ($s['fiyat'] !== null && $s['fiyat'] > 0): ?>
                                        <b><?= $paraBicim($s['fiyat']) ?></b>
                                        <span>/yıl</span>
                                    <?php else: ?>
                                        <span>Fiyat tanımlı değil</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($s['durum'] === AlanAdi::KAYITLI): ?>
                                    <a href="contact.php#iletisim-formu" class="dm-btn dm-btn-outline">
                                        <i class="fas fa-right-left"></i> Transfer et
                                    </a>
                                <?php elseif ($s['fiyat'] !== null && $s['fiyat'] > 0): ?>
                                    <a href="cart.php?domain=<?= urlencode($s['alan_adi']) ?>"
                                        class="dm-btn dm-btn-primary">
                                        <i class="fas fa-cart-plus"></i> Sepete ekle
                                    </a>
                                <?php else: ?>
                                    <a href="contact.php#iletisim-formu" class="dm-btn dm-btn-outline">
                                        <i class="fas fa-envelope"></i> Fiyat sor
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Fiyat listesi -->
    <section class="dm-section <?= $sorgu !== '' ? 'is-alt' : '' ?>">
        <div class="container">
            <div class="dm-head">
                <h2>Uzantı ücretleri</h2>
                <p>Yıllık kayıt, yenileme ve transfer ücretleri. Tutarlara KDV dâhil değildir.</p>
            </div>

            <?php if (!$uzantilar): ?>
                <div class="dm-empty">
                    Uzantı fiyatları henüz tanımlanmadı.<br>
                    Fiyat listesi girildiğinde bu bölüm otomatik olarak dolar.
                </div>
            <?php else: ?>
                <div class="dm-table-wrap">
                    <table class="dm-table">
                        <thead>
                            <tr>
                                <th>Uzantı</th>
                                <th style="text-align: right;">Kayıt / yıl</th>
                                <th style="text-align: right;">Yenileme / yıl</th>
                                <th style="text-align: right;">Transfer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($uzantilar as $u): ?>
                                <tr>
                                    <th dir="ltr"><?= htmlspecialchars($u['uzanti']) ?></th>
                                    <td class="dm-num"><?= $u['kayit'] > 0 ? $paraBicim($u['kayit']) : '—' ?></td>
                                    <td class="dm-num"><?= $u['yenileme'] > 0 ? $paraBicim($u['yenileme']) : '—' ?></td>
                                    <td class="dm-num"><?= $u['transfer'] > 0 ? $paraBicim($u['transfer']) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Avantajlar -->
    <section class="dm-section <?= $sorgu !== '' ? '' : 'is-alt' ?>">
        <div class="container">
            <div class="dm-head">
                <h2>Her alan adında standart</h2>
            </div>

            <div class="dm-grid">
                <?php foreach ($avantajlar as [$ikon, $baslik, $metin]): ?>
                    <div class="dm-tile">
                        <div class="dm-tile-icon"><i class="fas <?= htmlspecialchars($ikon) ?>"></i></div>
                        <h4><?= htmlspecialchars($baslik) ?></h4>
                        <p><?= htmlspecialchars($metin) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

</div>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>
