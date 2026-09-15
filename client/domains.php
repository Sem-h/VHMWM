<?php
/**
 * VHM - Domainlerim (müşteri paneli)
 *
 * Hizmetlerim sayfasıyla (client/services.php) aynı düzen: hero, dört özet
 * kartı, süzgeçli araç çubuğu ve kart ızgarası. Sınıf öneki "dom-".
 *
 * Önceki sürüm tabloydu ve dar ekranda yatay kayıyordu; ayrıca kalan gün
 * bilgisi yalnızca bitiş tarihi hücresinde küçük bir rozetteydi. Alan adı
 * yönetiminde en kritik bilgi bu olduğu için öne alındı.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Domainlerim';
$currentPage = 'domains';
$clientId = (int) ($_SESSION['client_id'] ?? 0);

/* Oturum denetimi header.php'de; çıktıdan önce çağrılıyor */
include 'includes/header.php';

try {
    $domainler = Database::fetchAll(
        "SELECT * FROM domains WHERE client_id = ?
          ORDER BY FIELD(status,'active','pending','pending_transfer','expired','cancelled'),
                   expiry_date IS NULL, expiry_date",
        [$clientId]
    );
} catch (Throwable $e) {
    error_log('Alan adları okunamadı: ' . $e->getMessage());
    $domainler = [];
}

/** Bitişe kalan gün; tarih yoksa null */
function domKalanGun(?string $tarih): ?int
{
    if (!$tarih) {
        return null;
    }

    return (int) floor((strtotime($tarih) - strtotime('today')) / 86400);
}

/* domains.status enum'u ile birebir aynı */
const DOM_DURUMLAR = [
    'active' => ['Aktif', 'good'],
    'pending' => ['Beklemede', 'wait'],
    'pending_transfer' => ['Transfer bekliyor', 'wait'],
    'expired' => ['Süresi dolmuş', 'danger'],
    'cancelled' => ['İptal', 'muted'],
];

$sayilar = ['toplam' => count($domainler), 'aktif' => 0, 'yakin' => 0, 'dolmus' => 0];

foreach ($domainler as $i => $d) {
    $gun = domKalanGun($d['expiry_date'] ?? null);
    $domainler[$i]['kalan_gun'] = $gun;

    /* Tarihi geçmiş alan adı, kaydı 'active' görünse de dolmuş sayılır */
    $durum = (string) ($d['status'] ?? 'pending');
    if ($gun !== null && $gun < 0) {
        $durum = 'expired';
    }

    /* Yakında bitenler ayrı bir grup değil, aktiflerin alt kümesi. Böylece
       özetteki "Aktif" sayısı ile süzgecin sonucu birbirini tutuyor. */
    $yakin = $durum === 'active' && $gun !== null && $gun <= 30;

    $domainler[$i]['durum'] = $durum;
    $domainler[$i]['yakin'] = $yakin;

    if ($durum === 'expired') {
        $sayilar['dolmus']++;
    } elseif ($durum === 'active') {
        $sayilar['aktif']++;
        if ($yakin) {
            $sayilar['yakin']++;
        }
    }
}
?>

<style>
    /* ==========================================
       Domainlerim - dom
       Düzen client/services.php ile aynı.
       ========================================== */
    .dom-page {
        max-width: 1400px;
        margin: auto;
    }

    .dom-hero {
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

    .dom-kicker {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--primary-light);
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .dom-kicker i {
        color: #6ee7b7;
    }

    .dom-hero h1 {
        margin: 11px 0 8px;
        font-size: 32px;
        letter-spacing: -.04em;
    }

    .dom-hero p {
        margin: 0;
        color: var(--text-muted);
        font-size: 14px;
    }

    .dom-cta {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        padding: 12px 16px;
        border-radius: 11px;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        box-shadow: 0 9px 20px color-mix(in srgb, var(--primary) 26%, transparent);
        color: #fff;
        text-decoration: none;
        font-size: 13px;
        font-weight: 800;
        white-space: nowrap;
        transition: .2s;
    }

    .dom-cta:hover {
        transform: translateY(-2px);
        color: #fff;
    }

    /* ---- Özet ---- */
    .dom-summary {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin: 18px 0 28px;
    }

    .dom-stat {
        padding: 18px;
        border: 1px solid rgba(255, 255, 255, .1);
        border-radius: 16px;
        background: var(--card-bg);
    }

    .dom-stat-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: var(--text-muted);
        font-size: 12px;
        font-weight: 700;
    }

    .dom-stat i {
        display: grid;
        place-items: center;
        width: 33px;
        height: 33px;
        border-radius: 10px;
        background: color-mix(in srgb, var(--primary) 16%, transparent);
        color: var(--primary-light);
    }

    .dom-stat:nth-child(2) i {
        background: rgba(16, 185, 129, .13);
        color: #6ee7b7;
    }

    .dom-stat:nth-child(3) i {
        background: rgba(245, 158, 11, .13);
        color: #fcd34d;
    }

    .dom-stat:nth-child(4) i {
        background: rgba(239, 68, 68, .13);
        color: #fca5a5;
    }

    .dom-stat strong {
        display: block;
        margin-top: 14px;
        font-size: 27px;
        letter-spacing: -.04em;
    }

    .dom-stat span {
        display: block;
        margin-top: 3px;
        color: var(--text-muted);
        font-size: 11px;
    }

    /* ---- Araç çubuğu ---- */
    .dom-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
    }

    .dom-toolbar h2 {
        margin: 0;
        font-size: 19px;
    }

    .dom-toolbar p {
        margin: 4px 0 0;
        color: var(--text-muted);
        font-size: 13px;
    }

    .dom-filters {
        display: flex;
        gap: 7px;
        flex-wrap: wrap;
    }

    .dom-filter {
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

    .dom-filter:hover {
        border-color: var(--primary-light);
        color: var(--text-primary);
    }

    .dom-filter.is-active {
        border-color: var(--primary);
        background: var(--primary);
        color: #fff;
    }

    /* ---- Kartlar ---- */
    .dom-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 16px;
    }

    .dom-card {
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, .1);
        border-radius: 18px;
        background: var(--card-bg);
        transition: .25s;
    }

    .dom-card:hover {
        transform: translateY(-4px);
        border-color: color-mix(in srgb, var(--primary) 62%, transparent);
        box-shadow: 0 16px 34px rgba(15, 23, 42, .14);
    }

    .dom-card-main {
        padding: 20px;
    }

    .dom-card-top {
        display: flex;
        justify-content: space-between;
        align-items: start;
        gap: 13px;
    }

    .dom-identity {
        display: flex;
        min-width: 0;
        gap: 12px;
    }

    .dom-icon {
        display: grid;
        place-items: center;
        flex: 0 0 43px;
        width: 43px;
        height: 43px;
        border-radius: 12px;
        background: color-mix(in srgb, var(--primary) 16%, transparent);
        color: var(--primary-light);
    }

    .dom-name {
        overflow: hidden;
        margin: 0 0 5px;
        font-size: 15px;
        text-overflow: ellipsis;
        white-space: nowrap;
        direction: ltr;
    }

    .dom-registrar {
        overflow: hidden;
        color: var(--text-muted);
        font-size: 12px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dom-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 8px;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 800;
        white-space: nowrap;
    }

    .dom-badge:before {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        content: '';
    }

    .dom-badge.good {
        background: rgba(16, 185, 129, .12);
        color: #6ee7b7;
    }

    .dom-badge.good:before {
        background: #10b981;
    }

    .dom-badge.wait {
        background: rgba(245, 158, 11, .12);
        color: #fcd34d;
    }

    .dom-badge.wait:before {
        background: #f59e0b;
    }

    .dom-badge.danger {
        background: rgba(239, 68, 68, .12);
        color: #fca5a5;
    }

    .dom-badge.danger:before {
        background: #ef4444;
    }

    .dom-badge.muted {
        background: rgba(148, 163, 184, .12);
        color: #cbd5e1;
    }

    .dom-badge.muted:before {
        background: #94a3b8;
    }

    /* ---- Süre çubuğu: alan adında en kritik bilgi ---- */
    .dom-sure {
        margin-top: 18px;
        padding: 12px;
        border-radius: 10px;
        background: rgba(255, 255, 255, .035);
    }

    .dom-sure-ust {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 10px;
        margin-bottom: 9px;
    }

    .dom-sure-ust span {
        color: var(--text-muted);
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .dom-sure-ust strong {
        font-size: 13px;
    }

    .dom-cubuk {
        height: 5px;
        border-radius: 99px;
        background: rgba(148, 163, 184, .22);
        overflow: hidden;
    }

    .dom-cubuk i {
        display: block;
        height: 100%;
        border-radius: 99px;
        background: #10b981;
    }

    .dom-cubuk.yakin i {
        background: #f59e0b;
    }

    .dom-cubuk.gecti i {
        background: #ef4444;
    }

    .dom-sure small {
        display: block;
        margin-top: 8px;
        color: var(--text-muted);
        font-size: 11.5px;
    }

    .dom-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 10px;
    }

    .dom-detail {
        padding: 11px;
        border-radius: 10px;
        background: rgba(255, 255, 255, .035);
    }

    .dom-detail span {
        display: block;
        margin-bottom: 5px;
        color: var(--text-muted);
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .dom-detail strong {
        font-size: 12px;
    }

    .dom-actions {
        display: flex;
        gap: 9px;
        padding: 14px 20px;
        border-top: 1px solid rgba(255, 255, 255, .08);
        background: rgba(255, 255, 255, .018);
    }

    .dom-button {
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

    .dom-button:hover {
        border-color: var(--primary-light);
        color: var(--primary-light);
    }

    .dom-button.primary {
        border-color: var(--primary);
        background: var(--primary);
        color: #fff;
    }

    .dom-button.primary:hover {
        background: var(--primary-dark);
        border-color: var(--primary-dark);
        color: #fff;
    }

    .dom-empty {
        padding: 68px 25px;
        border: 1px dashed rgba(255, 255, 255, .18);
        border-radius: 18px;
        color: var(--text-muted);
        text-align: center;
    }

    .dom-empty i {
        display: block;
        margin-bottom: 15px;
        color: var(--primary-light);
        font-size: 38px;
    }

    .dom-empty h2 {
        margin: 0 0 8px;
        color: var(--text-primary);
        font-size: 19px;
    }

    .dom-empty p {
        margin: 0 0 21px;
        font-size: 13px;
    }

    .dom-yok {
        padding: 44px 20px;
        color: var(--text-muted);
        font-size: 13px;
        text-align: center;
    }

    /* ---- Açık tema ---- */
    html[data-client-theme="light"] .dom-stat,
    html[data-client-theme="light"] .dom-card {
        border-color: rgba(15, 23, 42, .1);
        box-shadow: 0 8px 25px rgba(15, 23, 42, .035);
    }

    html[data-client-theme="light"] .dom-filter {
        border-color: #dbe3ef;
        background: #fff;
    }

    html[data-client-theme="light"] .dom-filter.is-active {
        border-color: var(--primary);
        background: var(--primary);
        color: #fff;
    }

    html[data-client-theme="light"] .dom-sure,
    html[data-client-theme="light"] .dom-detail,
    html[data-client-theme="light"] .dom-actions {
        background: rgba(15, 23, 42, .025);
    }

    html[data-client-theme="light"] .dom-actions {
        border-color: rgba(15, 23, 42, .08);
    }

    html[data-client-theme="light"] .dom-badge.good {
        background: #dcfce7;
        color: #15803d;
    }

    html[data-client-theme="light"] .dom-badge.wait {
        background: #fef3c7;
        color: #b45309;
    }

    html[data-client-theme="light"] .dom-badge.danger {
        background: #fee2e2;
        color: #b91c1c;
    }

    html[data-client-theme="light"] .dom-badge.muted {
        background: #f1f5f9;
        color: #475569;
    }

    html[data-client-theme="light"] .dom-empty {
        border-color: rgba(15, 23, 42, .16);
    }

    @media (max-width: 850px) {
        .dom-summary {
            grid-template-columns: repeat(2, 1fr);
        }

        .dom-hero,
        .dom-toolbar {
            align-items: start;
            flex-direction: column;
        }
    }

    @media (max-width: 520px) {
        .dom-hero {
            padding: 25px;
        }

        .dom-summary,
        .dom-grid {
            grid-template-columns: 1fr;
        }

        .dom-card-main {
            padding: 18px;
        }
    }
</style>

<div class="dom-page">

    <section class="dom-hero">
        <div>
            <div class="dom-kicker"><i class="fa-solid fa-globe"></i> Alan adı yönetimi</div>
            <h1>Domainlerim</h1>
            <p>Alan adlarınızın bitiş tarihlerini izleyin, DNS ve yenileme ayarlarını yönetin.</p>
        </div>
        <a class="dom-cta" href="../alan-adi.php">
            <i class="fa-solid fa-plus"></i> Yeni alan adı
        </a>
    </section>

    <section class="dom-summary">
        <article class="dom-stat">
            <div class="dom-stat-top">Toplam alan adı <i class="fa-solid fa-globe"></i></div>
            <strong><?= $sayilar['toplam'] ?></strong>
            <span>Hesabınıza kayıtlı</span>
        </article>
        <article class="dom-stat">
            <div class="dom-stat-top">Aktif <i class="fa-solid fa-circle-check"></i></div>
            <strong><?= $sayilar['aktif'] ?></strong>
            <span>Yayında olan alan adları</span>
        </article>
        <article class="dom-stat">
            <div class="dom-stat-top">Yakında bitiyor <i class="fa-regular fa-clock"></i></div>
            <strong><?= $sayilar['yakin'] ?></strong>
            <span>30 günden az kaldı</span>
        </article>
        <article class="dom-stat">
            <div class="dom-stat-top">Süresi dolmuş <i class="fa-solid fa-triangle-exclamation"></i></div>
            <strong><?= $sayilar['dolmus'] ?></strong>
            <span>Yenilenmesi gerekiyor</span>
        </article>
    </section>

    <section>
        <div class="dom-toolbar">
            <div>
                <h2>Alan adı listesi</h2>
                <p><?= $sayilar['toplam'] ?> alan adı görüntüleniyor</p>
            </div>
            <div class="dom-filters" aria-label="Alan adı filtresi">
                <button class="dom-filter is-active" data-filter="all">Tümü</button>
                <button class="dom-filter" data-filter="active">Aktif</button>
                <button class="dom-filter" data-filter="expiring">Yakında bitiyor</button>
                <button class="dom-filter" data-filter="expired">Süresi dolmuş</button>
            </div>
        </div>

        <?php if (!$domainler): ?>

            <div class="dom-empty">
                <i class="fa-solid fa-globe"></i>
                <h2>Henüz alan adınız yok</h2>
                <p>Yeni bir alan adı kaydedebilir ya da başka bir firmadaki alan adınızı taşıyabilirsiniz.</p>
                <a class="dom-cta" href="../alan-adi.php">
                    <i class="fa-solid fa-magnifying-glass"></i> Alan adı ara
                </a>
            </div>

        <?php else: ?>

            <div class="dom-grid" id="dom-grid">
                <?php foreach ($domainler as $d):
                    [$durumAd, $durumSinif] = DOM_DURUMLAR[$d['durum']] ?? [(string) $d['durum'], 'muted'];
                    $gun = $d['kalan_gun'];

                    /* Çubuk bir yıllık dönem üzerinden doluluk gösterir */
                    if ($gun === null) {
                        $oran = 0;
                        $cubukSinif = '';
                    } elseif ($gun < 0) {
                        $oran = 100;
                        $cubukSinif = 'gecti';
                    } else {
                        $oran = max(3, min(100, (int) round((365 - min($gun, 365)) / 365 * 100)));
                        $cubukSinif = $gun <= 30 ? 'yakin' : '';
                    }
                    ?>
                    <article class="dom-card" data-durum="<?= htmlspecialchars((string) $d['durum']) ?>"
                        data-yakin="<?= $d['yakin'] ? '1' : '0' ?>">
                        <div class="dom-card-main">

                            <div class="dom-card-top">
                                <div class="dom-identity">
                                    <div class="dom-icon"><i class="fa-solid fa-globe"></i></div>
                                    <div style="min-width:0">
                                        <h3 class="dom-name" dir="ltr"><?= htmlspecialchars((string) $d['domain']) ?></h3>
                                        <div class="dom-registrar">
                                            <?= htmlspecialchars((string) ($d['registrar'] ?: 'Kayıt firması belirtilmemiş')) ?>
                                        </div>
                                    </div>
                                </div>
                                <span class="dom-badge <?= $durumSinif ?>"><?= $durumAd ?></span>
                            </div>

                            <div class="dom-sure">
                                <div class="dom-sure-ust">
                                    <span>Bitiş tarihi</span>
                                    <strong><?= !empty($d['expiry_date'])
                                        ? date('d.m.Y', strtotime((string) $d['expiry_date'])) : '—' ?></strong>
                                </div>
                                <div class="dom-cubuk <?= $cubukSinif ?>"><i style="width:<?= $oran ?>%"></i></div>
                                <small>
                                    <?php
                                    if ($gun === null) {
                                        echo 'Bitiş tarihi kayıtlı değil';
                                    } elseif ($gun < 0) {
                                        echo abs($gun) . ' gün önce doldu, yenilemeniz gerekiyor';
                                    } elseif ($gun === 0) {
                                        echo 'Bugün doluyor';
                                    } elseif ($gun <= 30) {
                                        echo $gun . ' gün kaldı, yenilemeyi geciktirmeyin';
                                    } else {
                                        echo $gun . ' gün kaldı';
                                    }
                                    ?>
                                </small>
                            </div>

                            <div class="dom-details">
                                <div class="dom-detail">
                                    <span>Otomatik yenileme</span>
                                    <strong><?= (int) ($d['auto_renew'] ?? 0) === 1 ? 'Açık' : 'Kapalı' ?></strong>
                                </div>
                                <div class="dom-detail">
                                    <span>Yenileme ücreti</span>
                                    <strong><?= (float) ($d['amount'] ?? 0) > 0
                                        ? number_format((float) $d['amount'], 2, ',', '.') . ' '
                                            . htmlspecialchars((string) ($d['currency'] ?: '₺'))
                                        : '—' ?></strong>
                                </div>
                            </div>

                        </div>

                        <footer class="dom-actions">
                            <a class="dom-button primary" href="domain-manage.php?id=<?= (int) $d['id'] ?>">Yönet</a>
                            <?php if ($d['durum'] === 'active'): ?>
                                <a class="dom-button" href="domain-manage.php?id=<?= (int) $d['id'] ?>#dns">DNS</a>
                            <?php endif; ?>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="dom-yok" id="dom-yok" hidden>Bu süzgece uyan alan adı yok.</div>

        <?php endif; ?>
    </section>
</div>

<script>
    document.querySelectorAll('.dom-filter').forEach(function (dugme) {
        dugme.addEventListener('click', function () {
            document.querySelectorAll('.dom-filter').forEach(function (d) {
                d.classList.remove('is-active');
            });
            dugme.classList.add('is-active');

            var secim = dugme.dataset.filter;
            var gorunen = 0;

            document.querySelectorAll('.dom-card').forEach(function (kart) {
                var uygun = secim === 'all'
                    || (secim === 'expiring'
                        ? kart.dataset.yakin === '1'
                        : kart.dataset.durum === secim);
                kart.hidden = !uygun;
                if (uygun) {
                    gorunen++;
                }
            });

            /* Süzgeç hiçbir şey bırakmazsa boş ızgara yerine açıklama göster */
            var yok = document.getElementById('dom-yok');
            if (yok) {
                yok.hidden = gorunen > 0;
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
