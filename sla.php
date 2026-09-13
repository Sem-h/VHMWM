<?php
/**
 * VHM - Hizmet Seviyesi Anlaşması (SLA)
 *
 * Uptime garantileri, kredi eşikleri ve destek yanıt süreleri
 * sla_* tablolarından gelir; sayfaya sabit oran yazılmaz.
 *
 * Eski sürümde kredi tablosu tek eşiğe göre yazılmıştı: VDS %99,95
 * garanti ediliyor ama tablonun en üst aralığı %99,90'da bitiyordu.
 * Garantinin ihlal edildiği ama kredinin tanımsız kaldığı bir aralık
 * vardı. Artık her hizmetin en üst aralığı kendi garanti oranında biter.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Settings.php';

session_name(SESSION_NAME);
session_start();

$sirket = trim((string) Settings::get('company_name', 'VHM'));

$pageTitle = 'Hizmet Seviyesi Anlaşması (SLA)';
$pageDescription = 'Uptime garantileri, hizmet kredisi koşulları ve destek yanıt süreleri.';

/* ---------- Veriler ---------- */
$hizmetler = [];
$krediler = [];
$sureler = [];

try {
    $hizmetler = Database::fetchAll(
        "SELECT * FROM sla_hizmetleri WHERE is_active = 1 ORDER BY sort_order, id"
    );
    if ($hizmetler) {
        $krediler = Database::fetchAll(
            "SELECT * FROM sla_kredileri ORDER BY hizmet_id, sort_order"
        );
    }
    $sureler = Database::fetchAll(
        "SELECT * FROM sla_yanit_sureleri WHERE is_active = 1 ORDER BY sort_order, id"
    );
} catch (Throwable $e) {
    error_log('SLA verileri okunamadı: ' . $e->getMessage());
}

/* Hizmet başına kredi aralıkları */
$krediHaritasi = [];
$krediSeviyeleri = [];
foreach ($krediler as $k) {
    $krediHaritasi[(int) $k['hizmet_id']][(int) $k['kredi']] = $k;
    $krediSeviyeleri[(int) $k['kredi']] = true;
}
krsort($krediSeviyeleri);
$krediSeviyeleri = array_keys($krediSeviyeleri);

$yururluk = trim((string) Settings::get('sla_yururluk', ''));
$bakimSaat = (int) Settings::get('sla_bakim_bildirim_saat', 48);
$basvuruGun = (int) Settings::get('sla_kredi_basvuru_gun', 30);

/** %99,90 gibi yazar; gereksiz sıfırları atar */
$oran = static function (float $deger): string {
    $metin = rtrim(rtrim(number_format($deger, 3, ',', '.'), '0'), ',');
    return '%' . $metin;
};

/** Dakikayı okunur süreye çevirir */
$sure = static function (int $dakika): string {
    if ($dakika < 60) {
        return $dakika . ' dakika';
    }
    if ($dakika < 1440) {
        $saat = $dakika / 60;
        return rtrim(rtrim(number_format($saat, 1, ',', '.'), '0'), ',') . ' saat';
    }
    $gun = $dakika / 1440;
    return rtrim(rtrim(number_format($gun, 1, ',', '.'), '0'), ',') . ' gün';
};

$kapsamDisi = [
    'Önceden duyurulan planlı bakım ve güncelleme çalışmaları',
    'Müşteri kaynaklı hatalı yapılandırma, hatalı yazılım veya kaynak aşımı',
    'Müşterinin kendi yüklediği yazılımlardan doğan kesintiler',
    'Üçüncü taraf sağlayıcılardan kaynaklanan kesintiler (alan adı, ödeme, dış API)',
    'Ödeme gecikmesi nedeniyle askıya alınan hizmetler',
    'Doğal afet, savaş, genel grev ve benzeri mücbir sebep halleri',
];

require_once __DIR__ . '/theme/includes/header.php';
?>

<style>
    /* ==========================================
       SLA - sla
       Hukuki metin: ölçülü tipografi, geniş satır
       aralığı, renk yalnızca vurgu için.
       ========================================== */
    .sla {
        --sla-line: var(--border-color);
        --sla-surface: var(--bg-primary);
        --sla-accent: var(--primary);
        --sla-radius: 10px;
    }

    .sla a {
        color: inherit;
    }

    /* ===== Üst bilgi ===== */
    .sla-hero {
        position: relative;
        overflow: hidden;
        background: var(--gradient-hero);
        border-bottom: 1px solid var(--sla-line);
        padding: var(--space-7) 0;
    }

    .sla-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 55% 50% at 15% 25%, color-mix(in srgb, var(--primary) 16%, transparent) 0%, transparent 62%),
            radial-gradient(ellipse 45% 45% at 85% 10%, color-mix(in srgb, var(--secondary) 12%, transparent) 0%, transparent 58%);
        pointer-events: none;
    }

    .sla-hero>.container {
        position: relative;
        z-index: 1;
    }

    .sla-hero-inner {
        max-width: 700px;
        margin: 0 auto;
        text-align: center;
    }

    .sla-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 50px;
        border: 1px solid var(--sla-line);
        background: var(--sla-surface);
        font-size: var(--text-xs);
        font-weight: 600;
        color: var(--text-secondary);
    }

    .sla-eyebrow i {
        color: var(--sla-accent);
    }

    .sla-title {
        margin: var(--space-3) 0 0;
        font-size: clamp(26px, 2vw + 18px, 38px);
        font-weight: 700;
        letter-spacing: -0.02em;
        line-height: 1.2;
        color: var(--text-primary);
    }

    .sla-lead {
        max-width: 580px;
        margin: var(--space-3) auto 0;
        font-size: var(--text-base);
        line-height: 1.65;
        color: var(--text-muted);
    }

    .sla-date {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: var(--space-4);
        padding: 6px 14px;
        border: 1px solid var(--sla-line);
        border-radius: 50px;
        background: var(--sla-surface);
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    /* ===== Gövde ===== */
    .sla-body {
        padding: var(--space-7) 0;
    }

    .sla-wrap {
        max-width: 880px;
        margin: 0 auto;
        display: grid;
        gap: var(--space-5);
    }

    .sla-card {
        border: 1px solid var(--sla-line);
        border-radius: var(--sla-radius);
        background: var(--sla-surface);
        overflow: hidden;
    }

    .sla-card-head {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: var(--space-3) var(--space-5);
        border-bottom: 1px solid var(--sla-line);
        background: color-mix(in srgb, var(--primary) 5%, transparent);
    }

    .sla-card-head i {
        color: var(--sla-accent);
    }

    .sla-card-head h2 {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-primary);
    }

    .sla-card-body {
        padding: var(--space-5);
    }

    .sla-card-body>p {
        font-size: var(--text-sm);
        line-height: 1.7;
        color: var(--text-muted);
    }

    .sla-card-body>p+p,
    .sla-card-body>ul+p {
        margin-top: var(--space-3);
    }

    .sla-list {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .sla-list li {
        position: relative;
        padding: 6px 0 6px 22px;
        font-size: var(--text-sm);
        line-height: 1.6;
        color: var(--text-muted);
    }

    .sla-list li::before {
        content: '';
        position: absolute;
        left: 4px;
        top: 14px;
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: var(--sla-accent);
    }

    /* Uptime kartları */
    .sla-uptime {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: var(--space-4);
        margin-bottom: var(--space-4);
    }

    .sla-uptime-item {
        padding: var(--space-4);
        border: 1px solid var(--sla-line);
        border-radius: 8px;
        background: var(--bg-body);
        text-align: center;
    }

    .sla-uptime-item b {
        display: block;
        font-size: 27px;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--sla-accent);
    }

    .sla-uptime-item span {
        display: block;
        margin-top: 5px;
        font-size: var(--text-sm);
        font-weight: 600;
        color: var(--text-primary);
    }

    .sla-uptime-item small {
        display: block;
        margin-top: 3px;
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    /* Tablolar */
    .sla-table-wrap {
        border: 1px solid var(--sla-line);
        border-radius: 8px;
        overflow-x: auto;
    }

    .sla-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 520px;
    }

    .sla-table th,
    .sla-table td {
        padding: 11px var(--space-4);
        text-align: left;
        font-size: var(--text-sm);
        border-bottom: 1px solid var(--border-light);
    }

    .sla-table thead th {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--text-muted);
        border-bottom: 1px solid var(--sla-line);
        background: color-mix(in srgb, var(--primary) 4%, transparent);
    }

    .sla-table tbody th {
        font-weight: 700;
        color: var(--sla-accent);
        white-space: nowrap;
    }

    .sla-table td {
        color: var(--text-muted);
        white-space: nowrap;
    }

    .sla-table tr:last-child th,
    .sla-table tr:last-child td {
        border-bottom: none;
    }

    .sla-note {
        margin-top: var(--space-3);
        font-size: var(--text-xs);
        line-height: 1.6;
        color: var(--text-muted);
    }

    .sla-note a {
        color: var(--sla-accent);
    }

    .sla-empty {
        padding: var(--space-5);
        text-align: center;
        border: 1px dashed var(--sla-line);
        border-radius: 8px;
        font-size: var(--text-sm);
        color: var(--text-muted);
    }

    /* Adım listesi */
    .sla-steps {
        display: grid;
        gap: var(--space-3);
        counter-reset: adim;
    }

    .sla-step {
        position: relative;
        padding-left: 38px;
    }

    .sla-step::before {
        counter-increment: adim;
        content: counter(adim);
        position: absolute;
        left: 0;
        top: 0;
        width: 25px;
        height: 25px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: var(--sla-accent);
        color: #fff;
        font-size: 12px;
        font-weight: 700;
    }

    .sla-step b {
        display: block;
        font-size: var(--text-sm);
        font-weight: 600;
        color: var(--text-primary);
    }

    .sla-step span {
        display: block;
        margin-top: 3px;
        font-size: var(--text-sm);
        line-height: 1.6;
        color: var(--text-muted);
    }

    /* ==========================================
       Açık tema
       ========================================== */
    [data-theme="light"] .sla {
        --sla-line: #d3e2f8;
        background: #eff5fe;
    }

    [data-theme="light"] .sla-hero {
        background: linear-gradient(180deg, #e4edfb 0%, #eff5fe 100%);
    }

    [data-theme="light"] .sla-card {
        box-shadow:
            0 1px 2px rgba(36, 116, 245, 0.05),
            0 10px 26px -14px rgba(36, 116, 245, 0.28);
    }

    [data-theme="light"] .sla-uptime-item {
        background: #fff;
    }

    @media (max-width: 640px) {
        .sla-uptime {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="sla">

    <section class="sla-hero">
        <div class="container">
            <div class="sla-hero-inner">
                <span class="sla-eyebrow"><i class="fas fa-file-contract"></i> Hizmet Seviyesi Anlaşması</span>
                <h1 class="sla-title">Neyi taahhüt ediyoruz</h1>
                <p class="sla-lead">
                    Erişilebilirlik oranlarımız, bu oranların altına düşülmesi hâlinde uygulanacak
                    hizmet kredisi ve destek yanıt sürelerimiz aşağıdadır.
                </p>
                <?php if ($yururluk !== ''): ?>
                    <span class="sla-date">
                        <i class="fas fa-calendar-check"></i>
                        Yürürlük tarihi: <?= htmlspecialchars(date('d.m.Y', strtotime($yururluk))) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="sla-body">
        <div class="container">
            <div class="sla-wrap">

                <!-- Uptime -->
                <div class="sla-card">
                    <div class="sla-card-head">
                        <i class="fas fa-shield-halved"></i>
                        <h2>Erişilebilirlik garantisi</h2>
                    </div>
                    <div class="sla-card-body">
                        <?php if (!$hizmetler): ?>
                            <div class="sla-empty">Erişilebilirlik oranları henüz tanımlanmadı.</div>
                        <?php else: ?>
                            <div class="sla-uptime">
                                <?php foreach ($hizmetler as $h): ?>
                                    <div class="sla-uptime-item">
                                        <b><?= $oran((float) $h['uptime']) ?></b>
                                        <span><?= htmlspecialchars((string) $h['ad']) ?></span>
                                        <?php if (!empty($h['aciklama'])): ?>
                                            <small><?= htmlspecialchars((string) $h['aciklama']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <p>
                            Erişilebilirlik aylık olarak hesaplanır: ilgili ay içinde hizmetin erişilebilir
                            olduğu dakikaların, ayın toplam dakikasına oranıdır. Önceden duyurulan planlı
                            bakım süreleri bu hesaba dâhil edilmez.
                        </p>
                    </div>
                </div>

                <!-- Hizmet kredisi -->
                <div class="sla-card">
                    <div class="sla-card-head">
                        <i class="fas fa-percent"></i>
                        <h2>Hizmet kredisi</h2>
                    </div>
                    <div class="sla-card-body">
                        <p>
                            Aylık erişilebilirlik, hizmetinizin garanti oranının altına düşerse aşağıdaki
                            oranda hizmet kredisi uygulanır. Kredi, o ayki hizmet bedeli üzerinden hesaplanır.
                        </p>

                        <?php if (!$hizmetler || !$krediSeviyeleri): ?>
                            <div class="sla-empty" style="margin-top: var(--space-4);">
                                Kredi basamakları henüz tanımlanmadı.
                            </div>
                        <?php else: ?>
                            <div class="sla-table-wrap" style="margin-top: var(--space-4);">
                                <table class="sla-table">
                                    <thead>
                                        <tr>
                                            <th>Kredi</th>
                                            <?php foreach ($hizmetler as $h): ?>
                                                <th>
                                                    <?= htmlspecialchars((string) $h['ad']) ?>
                                                    <br><span
                                                        style="font-weight: 400; text-transform: none; letter-spacing: 0;">garanti
                                                        <?= $oran((float) $h['uptime']) ?></span>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($krediSeviyeleri as $seviye): ?>
                                            <tr>
                                                <th>%<?= (int) $seviye ?></th>
                                                <?php foreach ($hizmetler as $h):
                                                    $satir = $krediHaritasi[(int) $h['id']][$seviye] ?? null;
                                                    ?>
                                                    <td>
                                                        <?php if ($satir === null): ?>
                                                            —
                                                        <?php elseif ((float) $satir['alt_sinir'] <= 0): ?>
                                                            <?= $oran((float) $satir['ust_sinir']) ?> altı
                                                        <?php else: ?>
                                                            <?= $oran((float) $satir['alt_sinir']) ?> –
                                                            <?= $oran((float) $satir['ust_sinir']) ?>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <p class="sla-note">
                                Aralıklar alt sınır dâhil, üst sınır hariçtir; bir değer yalnızca tek bir
                                basamağa girer. Krediler bir sonraki fatura döneminde mahsup edilir,
                                nakit olarak iade edilmez.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Kredi talebi -->
                <div class="sla-card">
                    <div class="sla-card-head">
                        <i class="fas fa-file-invoice"></i>
                        <h2>Krediyi nasıl talep edersiniz</h2>
                    </div>
                    <div class="sla-card-body">
                        <div class="sla-steps">
                            <div class="sla-step">
                                <b>Destek talebi açın</b>
                                <span>Panelinizden konu başlığı "SLA kredi talebi" olan bir kayıt
                                    oluşturun.</span>
                            </div>
                            <div class="sla-step">
                                <b>Kesinti bilgisini yazın</b>
                                <span>Etkilenen hizmet, kesintinin başlangıç ve bitiş zamanı ile varsa
                                    hata kayıtlarını ekleyin.</span>
                            </div>
                            <div class="sla-step">
                                <b>İnceleme</b>
                                <span>Sunucu izleme kayıtlarımızla karşılaştırır, sonucu aynı kayıt
                                    üzerinden bildiririz.</span>
                            </div>
                            <div class="sla-step">
                                <b>Mahsup</b>
                                <span>Onaylanan kredi, bir sonraki faturanıza indirim olarak
                                    işlenir.</span>
                            </div>
                        </div>

                        <p class="sla-note">
                            Talep, kesintinin gerçekleştiği ayın bitiminden itibaren
                            <strong><?= $basvuruGun ?> gün</strong> içinde iletilmelidir. Bu sürenin
                            dışında yapılan başvurular değerlendirilemez.
                            <a href="client/tickets.php">Destek talebi açın</a>.
                        </p>
                    </div>
                </div>

                <!-- Yanıt süreleri -->
                <div class="sla-card">
                    <div class="sla-card-head">
                        <i class="fas fa-headset"></i>
                        <h2>Destek yanıt süreleri</h2>
                    </div>
                    <div class="sla-card-body">
                        <?php if (!$sureler): ?>
                            <div class="sla-empty">Yanıt süreleri henüz tanımlanmadı.</div>
                        <?php else: ?>
                            <div class="sla-table-wrap">
                                <table class="sla-table">
                                    <thead>
                                        <tr>
                                            <th>Öncelik</th>
                                            <th>Tanım</th>
                                            <th>İlk yanıt</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sureler as $s): ?>
                                            <tr>
                                                <th><?= htmlspecialchars((string) $s['ad']) ?></th>
                                                <td style="white-space: normal;">
                                                    <?= htmlspecialchars((string) ($s['aciklama'] ?? '')) ?>
                                                </td>
                                                <td><?= $sure((int) $s['dakika']) ?> içinde</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="sla-note">
                                Süreler ilk yanıt içindir, sorunun çözüm süresini ifade etmez.
                                Önceliği, bildirilen etkiye göre destek ekibimiz belirler.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Planlı bakım -->
                <div class="sla-card">
                    <div class="sla-card-head">
                        <i class="fas fa-screwdriver-wrench"></i>
                        <h2>Planlı bakım</h2>
                    </div>
                    <div class="sla-card-body">
                        <p>
                            Planlı bakım çalışmaları en az <strong><?= $bakimSaat ?> saat</strong> önce
                            e-posta ve panel duyurusuyla bildirilir. Bakımlar, etkinin en düşük olduğu
                            saatlerde yapılmaya çalışılır ve erişilebilirlik hesabına dâhil edilmez.
                        </p>
                        <p>
                            Güvenlik açığı kapatmak gibi ertelenemez durumlarda bakım önceden
                            duyurulmadan yapılabilir; bu çalışmalar da erişilebilirlik hesabı dışındadır.
                        </p>
                    </div>
                </div>

                <!-- Kapsam dışı -->
                <div class="sla-card">
                    <div class="sla-card-head">
                        <i class="fas fa-circle-exclamation"></i>
                        <h2>Kapsam dışı durumlar</h2>
                    </div>
                    <div class="sla-card-body">
                        <p>Aşağıdaki durumlardan doğan kesintiler bu anlaşmanın kapsamı dışındadır:</p>
                        <ul class="sla-list">
                            <?php foreach ($kapsamDisi as $madde): ?>
                                <li><?= htmlspecialchars($madde) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="sla-note">
                            Bu belge <?= htmlspecialchars($sirket) ?> tarafından sunulan hizmetler için
                            geçerlidir ve <a href="kullanim-sartlari.php">Kullanım Şartları</a> ile birlikte
                            değerlendirilir.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </section>

</div>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>
