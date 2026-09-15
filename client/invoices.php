<?php
/**
 * VHM - Faturalarım (müşteri paneli)
 *
 * Hizmetlerim ve Domainlerim ile aynı düzen; sınıf öneki "fat-".
 *
 * Önceki sürümdeki eksikler:
 *   - invoices.status enum'unda altı değer var ama sayfa yalnızca dördünü
 *     tanıyordu; 'draft' ve 'collections' ham hâliyle ekrana basılıyordu.
 *   - Kısmi ödeme (amount_paid) hiç gösterilmiyordu; yarısı ödenmiş fatura
 *     tam tutarıyla "Ödenmemiş" görünüyordu.
 *   - Vadesi geçmiş faturalar yalnızca küçük bir üçgen simgesiyle
 *     belirtiliyordu; ödeme sayfasında en önemli bilgi bu.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Faturalarım';
$currentPage = 'invoices';
$clientId = (int) ($_SESSION['client_id'] ?? 0);

/* Oturum denetimi header.php'de; çıktıdan önce çağrılıyor */
include 'includes/header.php';

try {
    $faturalar = Database::fetchAll(
        "SELECT * FROM invoices WHERE client_id = ?
          ORDER BY FIELD(status,'collections','unpaid','draft','paid','refunded','cancelled'),
                   due_date, created_at DESC",
        [$clientId]
    );
} catch (Throwable $e) {
    error_log('Faturalar okunamadı: ' . $e->getMessage());
    $faturalar = [];
}

/* invoices.status enum'u ile birebir aynı.
   [görünen ad, rozet sınıfı, süzgeç grubu] */
const FAT_DURUMLAR = [
    'draft' => ['Taslak', 'muted', 'diger'],
    'unpaid' => ['Ödenmemiş', 'wait', 'odenmemis'],
    'collections' => ['Takipte', 'danger', 'odenmemis'],
    'paid' => ['Ödendi', 'good', 'odenmis'],
    'refunded' => ['İade edildi', 'muted', 'diger'],
    'cancelled' => ['İptal', 'muted', 'diger'],
];

$sayilar = ['toplam' => count($faturalar), 'odenmemis' => 0, 'odenmis' => 0, 'diger' => 0];
$borc = 0.0;
$gecikmis = 0;
$ilkOdenecek = null;

foreach ($faturalar as $i => $f) {
    $durum = (string) ($f['status'] ?? 'unpaid');
    $grup = FAT_DURUMLAR[$durum][2] ?? 'diger';

    $toplam = (float) $f['total'];
    $odenen = (float) ($f['amount_paid'] ?? 0);
    $kalan = max(0.0, $toplam - $odenen);

    /* Vade yalnızca ödenmeyi bekleyen faturalar için anlamlı */
    $gun = null;
    if ($grup === 'odenmemis' && !empty($f['due_date'])) {
        $gun = (int) floor((strtotime((string) $f['due_date']) - strtotime('today')) / 86400);
    }

    $faturalar[$i]['grup'] = $grup;
    $faturalar[$i]['kalan'] = $kalan;
    $faturalar[$i]['kismi'] = $odenen > 0 && $kalan > 0;
    $faturalar[$i]['gun'] = $gun;

    $sayilar[$grup]++;

    if ($grup === 'odenmemis') {
        $borc += $kalan;
        if ($gun !== null && $gun < 0) {
            $gecikmis++;
        }
        if ($ilkOdenecek === null) {
            $ilkOdenecek = $faturalar[$i];
        }
    }
}

$paraBirimi = $faturalar ? (string) ($faturalar[0]['currency'] ?: 'TRY') : 'TRY';

function fatPara(float $t, string $b = 'TRY'): string
{
    return number_format($t, 2, ',', '.') . ' ' . ($b === 'TRY' ? '₺' : htmlspecialchars($b));
}
?>

<style>
    /* ==========================================
       Faturalarım - fat
       Düzen client/services.php ile aynı.
       ========================================== */
    .fat-page {
        max-width: 1400px;
        margin: auto;
    }

    .fat-hero {
        display: flex;
        justify-content: space-between;
        align-items: end;
        gap: 25px;
        padding: 31px 34px;
        border: 1px solid color-mix(in srgb, var(--primary) 32%, rgba(255, 255, 255, .15));
        border-radius: 22px;
        background: linear-gradient(125deg,
                color-mix(in srgb, var(--primary) 19%, var(--card-bg)),
                var(--card-bg) 68%,
                color-mix(in srgb, var(--secondary) 13%, var(--card-bg)));
    }

    .fat-kicker {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--primary-light);
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .fat-kicker i {
        color: #6ee7b7;
    }

    .fat-hero h1 {
        margin: 11px 0 8px;
        font-size: 32px;
        letter-spacing: -.04em;
    }

    .fat-hero p {
        margin: 0;
        color: var(--text-muted);
        font-size: 14px;
    }

    /* Borç kutusu: sayfanın en önemli bilgisi */
    .fat-borc {
        min-width: 220px;
        padding: 20px;
        border: 1px solid rgba(255, 255, 255, .2);
        border-radius: 16px;
        background: rgba(15, 23, 42, .2);
    }

    .fat-borc span {
        display: block;
        color: var(--text-muted);
        font-size: 12px;
        font-weight: 700;
    }

    .fat-borc strong {
        display: block;
        margin: 7px 0 4px;
        font-size: 27px;
        letter-spacing: -.04em;
    }

    .fat-borc small {
        display: block;
        color: var(--text-muted);
        font-size: 12px;
    }

    .fat-borc.var strong {
        color: #fca5a5;
    }

    .fat-cta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        width: 100%;
        margin-top: 14px;
        padding: 11px 16px;
        border-radius: 11px;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: #fff;
        text-decoration: none;
        font-size: 13px;
        font-weight: 800;
        transition: .2s;
    }

    .fat-cta:hover {
        transform: translateY(-2px);
        color: #fff;
    }

    /* ---- Özet ---- */
    .fat-summary {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin: 18px 0 28px;
    }

    .fat-stat {
        padding: 18px;
        border: 1px solid rgba(255, 255, 255, .1);
        border-radius: 16px;
        background: var(--card-bg);
    }

    .fat-stat-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: var(--text-muted);
        font-size: 12px;
        font-weight: 700;
    }

    .fat-stat i {
        display: grid;
        place-items: center;
        width: 33px;
        height: 33px;
        border-radius: 10px;
        background: rgba(245, 158, 11, .13);
        color: #fcd34d;
    }

    .fat-stat:nth-child(2) i {
        background: rgba(239, 68, 68, .13);
        color: #fca5a5;
    }

    .fat-stat:nth-child(3) i {
        background: rgba(16, 185, 129, .13);
        color: #6ee7b7;
    }

    .fat-stat:nth-child(4) i {
        background: color-mix(in srgb, var(--primary) 16%, transparent);
        color: var(--primary-light);
    }

    .fat-stat strong {
        display: block;
        margin-top: 14px;
        font-size: 27px;
        letter-spacing: -.04em;
    }

    .fat-stat span {
        display: block;
        margin-top: 3px;
        color: var(--text-muted);
        font-size: 11px;
    }

    /* ---- Araç çubuğu ---- */
    .fat-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
    }

    .fat-toolbar h2 {
        margin: 0;
        font-size: 19px;
    }

    .fat-toolbar p {
        margin: 4px 0 0;
        color: var(--text-muted);
        font-size: 13px;
    }

    .fat-filters {
        display: flex;
        gap: 7px;
        flex-wrap: wrap;
    }

    .fat-filter {
        padding: 8px 11px;
        border: 1px solid rgba(255, 255, 255, .11);
        border-radius: 9px;
        background: var(--card-bg);
        color: var(--text-muted);
        cursor: pointer;
        font: inherit;
        font-size: 12px;
        font-weight: 700;
        transition: .2s;
    }

    .fat-filter:hover {
        border-color: var(--primary-light);
        color: var(--text-primary);
    }

    .fat-filter.is-active {
        border-color: var(--primary);
        background: var(--primary);
        color: #fff;
    }

    /* ---- Kartlar ---- */
    .fat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
        gap: 16px;
    }

    .fat-card {
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, .1);
        border-radius: 18px;
        background: var(--card-bg);
        transition: .25s;
    }

    .fat-card:hover {
        transform: translateY(-4px);
        border-color: color-mix(in srgb, var(--primary) 62%, transparent);
        box-shadow: 0 16px 34px rgba(15, 23, 42, .14);
    }

    /* Vadesi geçmiş fatura sol kenardan işaretlenir */
    .fat-card.gecikmis {
        border-color: rgba(239, 68, 68, .45);
    }

    .fat-card.gecikmis:before {
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: #ef4444;
        content: '';
    }

    .fat-card-main {
        padding: 20px;
    }

    .fat-card-top {
        display: flex;
        justify-content: space-between;
        align-items: start;
        gap: 13px;
    }

    .fat-no {
        overflow: hidden;
        margin: 0 0 4px;
        font-size: 15px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .fat-tarih {
        color: var(--text-muted);
        font-size: 12px;
    }

    .fat-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 8px;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 800;
        white-space: nowrap;
    }

    .fat-badge:before {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        content: '';
    }

    .fat-badge.good {
        background: rgba(16, 185, 129, .12);
        color: #6ee7b7;
    }

    .fat-badge.good:before {
        background: #10b981;
    }

    .fat-badge.wait {
        background: rgba(245, 158, 11, .12);
        color: #fcd34d;
    }

    .fat-badge.wait:before {
        background: #f59e0b;
    }

    .fat-badge.danger {
        background: rgba(239, 68, 68, .12);
        color: #fca5a5;
    }

    .fat-badge.danger:before {
        background: #ef4444;
    }

    .fat-badge.muted {
        background: rgba(148, 163, 184, .12);
        color: #cbd5e1;
    }

    .fat-badge.muted:before {
        background: #94a3b8;
    }

    .fat-tutar {
        margin-top: 20px;
        padding: 14px;
        border-radius: 12px;
        background: rgba(255, 255, 255, .035);
    }

    .fat-tutar-ust {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 10px;
    }

    .fat-tutar-ust span {
        color: var(--text-muted);
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .fat-tutar b {
        font-size: 23px;
        font-weight: 800;
        letter-spacing: -.03em;
    }

    .fat-kismi {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        margin-top: 9px;
        padding-top: 9px;
        border-top: 1px dashed rgba(148, 163, 184, .3);
        color: var(--text-muted);
        font-size: 12px;
    }

    .fat-vade {
        display: flex;
        align-items: center;
        gap: 7px;
        margin-top: 13px;
        font-size: 12px;
        color: var(--text-muted);
    }

    .fat-vade.gecti {
        color: #fca5a5;
        font-weight: 700;
    }

    .fat-vade.yakin {
        color: #fcd34d;
        font-weight: 700;
    }

    .fat-actions {
        display: flex;
        gap: 9px;
        padding: 14px 20px;
        border-top: 1px solid rgba(255, 255, 255, .08);
        background: rgba(255, 255, 255, .018);
    }

    .fat-button {
        flex: 1;
        padding: 10px;
        border: 1px solid rgba(255, 255, 255, .14);
        border-radius: 9px;
        color: var(--text-secondary);
        font-size: 12px;
        font-weight: 800;
        text-align: center;
        text-decoration: none;
        transition: .2s;
    }

    .fat-button:hover {
        border-color: var(--primary-light);
        color: var(--primary-light);
    }

    .fat-button.primary {
        border-color: var(--primary);
        background: var(--primary);
        color: #fff;
    }

    .fat-button.primary:hover {
        background: var(--primary-dark);
        border-color: var(--primary-dark);
        color: #fff;
    }

    .fat-empty {
        padding: 68px 25px;
        border: 1px dashed rgba(255, 255, 255, .18);
        border-radius: 18px;
        color: var(--text-muted);
        text-align: center;
    }

    .fat-empty i {
        display: block;
        margin-bottom: 15px;
        color: var(--primary-light);
        font-size: 38px;
    }

    .fat-empty h2 {
        margin: 0 0 8px;
        color: var(--text-primary);
        font-size: 19px;
    }

    .fat-empty p {
        margin: 0;
        font-size: 13px;
    }

    .fat-yok {
        padding: 44px 20px;
        color: var(--text-muted);
        font-size: 13px;
        text-align: center;
    }

    /* ---- Açık tema ---- */
    html[data-client-theme="light"] .fat-stat,
    html[data-client-theme="light"] .fat-card {
        border-color: rgba(15, 23, 42, .1);
        box-shadow: 0 8px 25px rgba(15, 23, 42, .035);
    }

    html[data-client-theme="light"] .fat-card.gecikmis {
        border-color: rgba(239, 68, 68, .4);
    }

    html[data-client-theme="light"] .fat-filter {
        border-color: #dbe3ef;
        background: #fff;
    }

    html[data-client-theme="light"] .fat-filter.is-active {
        border-color: var(--primary);
        background: var(--primary);
        color: #fff;
    }

    html[data-client-theme="light"] .fat-borc {
        border-color: rgba(15, 23, 42, .1);
        background: rgba(255, 255, 255, .72);
    }

    html[data-client-theme="light"] .fat-borc.var strong {
        color: #b91c1c;
    }

    html[data-client-theme="light"] .fat-tutar,
    html[data-client-theme="light"] .fat-actions {
        background: rgba(15, 23, 42, .025);
    }

    html[data-client-theme="light"] .fat-actions {
        border-color: rgba(15, 23, 42, .08);
    }

    html[data-client-theme="light"] .fat-badge.good {
        background: #dcfce7;
        color: #15803d;
    }

    html[data-client-theme="light"] .fat-badge.wait {
        background: #fef3c7;
        color: #b45309;
    }

    html[data-client-theme="light"] .fat-badge.danger {
        background: #fee2e2;
        color: #b91c1c;
    }

    html[data-client-theme="light"] .fat-badge.muted {
        background: #f1f5f9;
        color: #475569;
    }

    html[data-client-theme="light"] .fat-vade.gecti {
        color: #b91c1c;
    }

    html[data-client-theme="light"] .fat-vade.yakin {
        color: #b45309;
    }

    html[data-client-theme="light"] .fat-empty {
        border-color: rgba(15, 23, 42, .16);
    }

    @media (max-width: 850px) {
        .fat-summary {
            grid-template-columns: repeat(2, 1fr);
        }

        .fat-hero,
        .fat-toolbar {
            align-items: stretch;
            flex-direction: column;
        }

        .fat-borc {
            min-width: 0;
        }
    }

    @media (max-width: 520px) {
        .fat-hero {
            padding: 25px;
        }

        .fat-summary,
        .fat-grid {
            grid-template-columns: 1fr;
        }

        .fat-card-main {
            padding: 18px;
        }
    }
</style>

<div class="fat-page">

    <section class="fat-hero">
        <div>
            <div class="fat-kicker"><i class="fa-solid fa-file-invoice-dollar"></i> Fatura ve ödeme</div>
            <h1>Faturalarım</h1>
            <p>Faturalarınızı görüntüleyin, ödeme geçmişinizi izleyin ve borcunuzu kapatın.</p>
        </div>

        <div class="fat-borc <?= $borc > 0 ? 'var' : '' ?>">
            <span>Güncel borcunuz</span>
            <strong><?= fatPara($borc, $paraBirimi) ?></strong>
            <small>
                <?php if ($borc <= 0): ?>
                    Ödenmemiş faturanız yok
                <?php elseif ($gecikmis > 0): ?>
                    <?= $gecikmis ?> fatura vadesi geçmiş
                <?php else: ?>
                    <?= $sayilar['odenmemis'] ?> fatura ödeme bekliyor
                <?php endif; ?>
            </small>
            <?php if ($ilkOdenecek): ?>
                <a class="fat-cta" href="invoice-pay.php?id=<?= (int) $ilkOdenecek['id'] ?>">
                    <i class="fa-solid fa-credit-card"></i> Şimdi öde
                </a>
            <?php endif; ?>
        </div>
    </section>

    <section class="fat-summary">
        <article class="fat-stat">
            <div class="fat-stat-top">Ödenmemiş <i class="fa-regular fa-clock"></i></div>
            <strong><?= $sayilar['odenmemis'] ?></strong>
            <span>Ödeme bekleyen fatura</span>
        </article>
        <article class="fat-stat">
            <div class="fat-stat-top">Vadesi geçmiş <i class="fa-solid fa-triangle-exclamation"></i></div>
            <strong><?= $gecikmis ?></strong>
            <span>Gecikmiş ödeme</span>
        </article>
        <article class="fat-stat">
            <div class="fat-stat-top">Ödenmiş <i class="fa-solid fa-circle-check"></i></div>
            <strong><?= $sayilar['odenmis'] ?></strong>
            <span>Kapanmış fatura</span>
        </article>
        <article class="fat-stat">
            <div class="fat-stat-top">Toplam <i class="fa-solid fa-file-lines"></i></div>
            <strong><?= $sayilar['toplam'] ?></strong>
            <span>Tüm fatura kaydı</span>
        </article>
    </section>

    <section>
        <div class="fat-toolbar">
            <div>
                <h2>Fatura listesi</h2>
                <p><?= $sayilar['toplam'] ?> fatura kaydı görüntüleniyor</p>
            </div>
            <div class="fat-filters" aria-label="Fatura filtresi">
                <button class="fat-filter is-active" data-filter="all">Tümü (<?= $sayilar['toplam'] ?>)</button>
                <button class="fat-filter" data-filter="odenmemis">Ödenmemiş (<?= $sayilar['odenmemis'] ?>)</button>
                <button class="fat-filter" data-filter="odenmis">Ödenmiş (<?= $sayilar['odenmis'] ?>)</button>
                <button class="fat-filter" data-filter="diger">Diğer (<?= $sayilar['diger'] ?>)</button>
            </div>
        </div>

        <?php if (!$faturalar): ?>

            <div class="fat-empty">
                <i class="fa-solid fa-file-invoice"></i>
                <h2>Henüz faturanız yok</h2>
                <p>Sipariş verdiğinizde faturalarınız burada görünecek.</p>
            </div>

        <?php else: ?>

            <div class="fat-grid" id="fat-grid">
                <?php foreach ($faturalar as $f):
                    [$durumAd, $durumSinif] = FAT_DURUMLAR[$f['status']] ?? [(string) $f['status'], 'muted'];
                    $gun = $f['gun'];
                    $gecikti = $gun !== null && $gun < 0;
                    $birim = (string) ($f['currency'] ?: 'TRY');
                    ?>
                    <article class="fat-card <?= $gecikti ? 'gecikmis' : '' ?>"
                        data-grup="<?= htmlspecialchars((string) $f['grup']) ?>">
                        <div class="fat-card-main">

                            <div class="fat-card-top">
                                <div style="min-width:0">
                                    <h3 class="fat-no">#<?= htmlspecialchars((string) $f['invoice_number']) ?></h3>
                                    <div class="fat-tarih">
                                        <?= date('d.m.Y', strtotime((string) $f['created_at'])) ?> tarihli
                                    </div>
                                </div>
                                <span class="fat-badge <?= $durumSinif ?>"><?= $durumAd ?></span>
                            </div>

                            <div class="fat-tutar">
                                <div class="fat-tutar-ust">
                                    <span><?= $f['grup'] === 'odenmemis' ? 'Ödenecek tutar' : 'Fatura tutarı' ?></span>
                                    <b><?= fatPara(
                                        $f['grup'] === 'odenmemis' ? (float) $f['kalan'] : (float) $f['total'],
                                        $birim
                                    ) ?></b>
                                </div>

                                <?php if ($f['kismi']): ?>
                                    <div class="fat-kismi">
                                        <span>Kısmi ödeme yapıldı</span>
                                        <span><?= fatPara((float) $f['amount_paid'], $birim) ?> /
                                            <?= fatPara((float) $f['total'], $birim) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($f['grup'] === 'odenmemis' && !empty($f['due_date'])): ?>
                                <div class="fat-vade <?= $gecikti ? 'gecti' : ($gun <= 7 ? 'yakin' : '') ?>">
                                    <i class="fa-regular fa-calendar"></i>
                                    <?php
                                    $vade = date('d.m.Y', strtotime((string) $f['due_date']));
                                    if ($gecikti) {
                                        echo 'Vadesi ' . abs($gun) . ' gün geçti (' . $vade . ')';
                                    } elseif ($gun === 0) {
                                        echo 'Son ödeme bugün';
                                    } else {
                                        echo 'Son ödeme ' . $vade . ' (' . $gun . ' gün)';
                                    }
                                    ?>
                                </div>
                            <?php elseif (!empty($f['paid_date'])): ?>
                                <div class="fat-vade">
                                    <i class="fa-solid fa-circle-check"></i>
                                    <?= date('d.m.Y', strtotime((string) $f['paid_date'])) ?> tarihinde ödendi
                                </div>
                            <?php endif; ?>

                        </div>

                        <footer class="fat-actions">
                            <a class="fat-button<?= $f['grup'] === 'odenmemis' ? '' : ' primary' ?>"
                                href="invoice-view.php?id=<?= (int) $f['id'] ?>">Görüntüle</a>
                            <?php if ($f['grup'] === 'odenmemis'): ?>
                                <a class="fat-button primary"
                                    href="invoice-pay.php?id=<?= (int) $f['id'] ?>">Öde</a>
                            <?php endif; ?>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="fat-yok" id="fat-yok" hidden>Bu süzgece uyan fatura yok.</div>

        <?php endif; ?>
    </section>
</div>

<script>
    document.querySelectorAll('.fat-filter').forEach(function (dugme) {
        dugme.addEventListener('click', function () {
            document.querySelectorAll('.fat-filter').forEach(function (d) {
                d.classList.remove('is-active');
            });
            dugme.classList.add('is-active');

            var secim = dugme.dataset.filter;
            var gorunen = 0;

            document.querySelectorAll('.fat-card').forEach(function (kart) {
                var uygun = secim === 'all' || kart.dataset.grup === secim;
                kart.hidden = !uygun;
                if (uygun) {
                    gorunen++;
                }
            });

            var yok = document.getElementById('fat-yok');
            if (yok) {
                yok.hidden = gorunen > 0;
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
