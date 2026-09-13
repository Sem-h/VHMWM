<?php
/**
 * VHM - Hakkımızda
 *
 * Sayfadaki sayılar veritabanından gelir: erişilebilirlik taahhüdü
 * sla_hizmetleri, hizmet alanları product_groups, destek yanıt süresi
 * sla_yanit_sureleri tablosundan okunur.
 *
 * Önceki sürümde doğrulanamaz değerler vardı: "50K+ mutlu müşteri"
 * (tabloda kayıt yok), "15+ yıl" ile "2010'dan bu yana" (aynı sayfada
 * iki farklı yaş) ve kanıt gerektiren bir üstünlük iddiası.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Settings.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Hakkımızda';
$pageDescription = 'Hizmet alanlarımız, çalışma biçimimiz ve şirket bilgilerimiz.';

$ayar = static fn(string $anahtar): string => trim((string) Settings::get($anahtar, ''));

$sirket = $ayar('company_name') !== '' ? $ayar('company_name') : 'Şirketimiz';
$unvan = $ayar('company_legal_name') !== '' ? $ayar('company_legal_name') : $sirket;

/* ---------- Doğrulanabilir sayılar ---------- */
$enYuksekUptime = 0.0;
$enHizliYanit = 0;
$hizmetGrubu = 0;

try {
    $enYuksekUptime = (float) (Database::fetchColumn(
        "SELECT MAX(uptime) FROM sla_hizmetleri WHERE is_active = 1"
    ) ?: 0);

    $enHizliYanit = (int) (Database::fetchColumn(
        "SELECT MIN(dakika) FROM sla_yanit_sureleri WHERE is_active = 1"
    ) ?: 0);

    /* Yalnızca ürünü olan gruplar sayılır; boş grup hizmet sayılmaz */
    $hizmetGrubu = (int) Database::fetchColumn(
        "SELECT COUNT(*) FROM product_groups g
          WHERE g.is_hidden = 0
            AND EXISTS (SELECT 1 FROM products p WHERE p.group_id = g.id AND p.is_active = 1)"
    );
} catch (Throwable $e) {
    error_log('Hakkımızda verileri okunamadı: ' . $e->getMessage());
}

$oran = static function (float $deger): string {
    return '%' . rtrim(rtrim(number_format($deger, 3, ',', '.'), '0'), ',');
};

$sure = static function (int $dakika): string {
    if ($dakika <= 0) {
        return '';
    }
    if ($dakika < 60) {
        return $dakika . ' dakika';
    }
    $saat = $dakika / 60;
    return rtrim(rtrim(number_format($saat, 1, ',', '.'), '0'), ',') . ' saat';
};

/* Yalnızca değeri olan ölçütler gösterilir */
$olcutler = [];
if ($enYuksekUptime > 0) {
    $olcutler[] = [
        'fa-shield-halved',
        $oran($enYuksekUptime) . '\'a kadar',
        'Erişilebilirlik taahhüdü',
        'sla.php',
    ];
}
if ($hizmetGrubu > 0) {
    $olcutler[] = [
        'fa-layer-group',
        (string) $hizmetGrubu,
        'Hizmet alanı',
        'magaza.php',
    ];
}
if ($enHizliYanit > 0) {
    $olcutler[] = [
        'fa-bolt',
        $sure($enHizliYanit),
        'Kritik talepte ilk yanıt',
        'sla.php',
    ];
}
$olcutler[] = [
    'fa-headset',
    '7/24',
    'Teknik destek',
    'iletisim.php',
];

/* ---------- Hizmet alanları ---------- */
$gruplar = [];
try {
    $gruplar = Database::fetchAll(
        "SELECT g.name, g.slug, g.type,
                (SELECT COUNT(*) FROM products p WHERE p.group_id = g.id AND p.is_active = 1) AS adet
           FROM product_groups g
          WHERE g.is_hidden = 0
          HAVING adet > 0
          ORDER BY g.order_priority, g.name"
    );
} catch (Throwable $e) {
    error_log('Hizmet grupları okunamadı: ' . $e->getMessage());
}

$turIkon = [
    'hosting' => 'fa-globe',
    'vps' => 'fa-server',
    'vds' => 'fa-hard-drive',
    'dedicated' => 'fa-database',
    'domain' => 'fa-link',
    'ssl' => 'fa-lock',
    'email' => 'fa-envelope',
    'other' => 'fa-box',
];

/* ---------- Çalışma biçimi: her madde bir sayfaya dayanıyor ---------- */
$ilkeler = [
    [
        'fa-file-contract',
        'Taahhüdümüz yazılı',
        'Erişilebilirlik oranları, bu oranın altına düşülmesi hâlinde uygulanacak kredi ve destek yanıt süreleri belirli bir belgeye bağlıdır.',
        'sla.php',
        'Hizmet Seviyesi Anlaşması',
    ],
    [
        'fa-lock',
        'Veriniz size ait',
        'Hizmet sona erdiğinde verileriniz belirli bir süre saklanır ve talebiniz üzerine size teslim edilir. Kişisel verilerin işlenmesi ayrıca açıklanmıştır.',
        'kvkk.php',
        'KVKK Aydınlatma Metni',
    ],
    [
        'fa-rotate-left',
        'Cayma hakkınız duruyor',
        'İade koşulları hizmet bazında yazılıdır; yasal cayma hakkınız ile ticari iade taahhüdümüz ayrı ayrı açıklanmıştır.',
        'iade-politikasi.php',
        'İade Politikası',
    ],
    [
        'fa-book-open',
        'Sorunu önce siz çözebilirsiniz',
        'Kurulum, yapılandırma ve sorun giderme adımları bilgi bankasında yayımlanır; destek beklemeden uygulayabilirsiniz.',
        'bilgi-bankasi.php',
        'Bilgi Bankası',
    ],
];

/* ---------- Şirket bilgileri ---------- */
$kimlik = array_filter([
    'Unvan' => $unvan,
    'Adres' => $ayar('company_address'),
    'Telefon' => $ayar('company_phone'),
    'E-posta' => $ayar('company_email'),
    'Vergi dairesi' => $ayar('company_tax_office'),
    'Vergi numarası' => $ayar('company_tax_number'),
    'MERSİS numarası' => $ayar('company_mersis'),
    'Ticaret sicil no' => $ayar('company_trade_registry'),
], static fn(string $v): bool => $v !== '');

require_once __DIR__ . '/theme/includes/header.php';
?>

<style>
    /* ==========================================
       Hakkımızda - hk
       ========================================== */
    .hk {
        --hk-line: var(--border-color);
        --hk-surface: var(--bg-primary);
        --hk-accent: var(--primary);
        --hk-radius: 10px;
    }

    .hk a {
        color: inherit;
    }

    /* ===== Üst bilgi ===== */
    .hk-hero {
        position: relative;
        overflow: hidden;
        background: var(--gradient-hero);
        border-bottom: 1px solid var(--hk-line);
        padding: var(--space-7) 0;
    }

    .hk-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 55% 50% at 15% 25%, color-mix(in srgb, var(--primary) 16%, transparent) 0%, transparent 62%),
            radial-gradient(ellipse 45% 45% at 85% 10%, color-mix(in srgb, var(--secondary) 12%, transparent) 0%, transparent 58%);
        pointer-events: none;
    }

    .hk-hero>.container {
        position: relative;
        z-index: 1;
    }

    .hk-hero-inner {
        max-width: 700px;
        margin: 0 auto;
        text-align: center;
    }

    .hk-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 50px;
        border: 1px solid var(--hk-line);
        background: var(--hk-surface);
        font-size: var(--text-xs);
        font-weight: 600;
        color: var(--text-secondary);
    }

    .hk-eyebrow i {
        color: var(--hk-accent);
    }

    .hk-title {
        margin: var(--space-3) 0 0;
        font-size: clamp(26px, 2vw + 18px, 38px);
        font-weight: 700;
        letter-spacing: -0.02em;
        line-height: 1.2;
        color: var(--text-primary);
    }

    .hk-lead {
        max-width: 600px;
        margin: var(--space-3) auto 0;
        font-size: var(--text-base);
        line-height: 1.7;
        color: var(--text-muted);
    }

    /* ===== Ölçütler ===== */
    .hk-metrics {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: var(--space-4);
        margin-top: var(--space-6);
    }

    .hk-metric {
        display: block;
        padding: var(--space-4);
        border: 1px solid var(--hk-line);
        border-radius: var(--hk-radius);
        background: var(--hk-surface);
        text-align: center;
        transition: border-color 0.15s ease, transform 0.15s ease;
    }

    .hk-metric:hover {
        border-color: var(--hk-accent);
        transform: translateY(-2px);
    }

    .hk-metric i {
        font-size: 15px;
        color: var(--hk-accent);
    }

    .hk-metric b {
        display: block;
        margin-top: 8px;
        font-size: 21px;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--text-primary);
    }

    .hk-metric span {
        display: block;
        margin-top: 4px;
        font-size: var(--text-xs);
        line-height: 1.45;
        color: var(--text-muted);
    }

    /* ===== Bölümler ===== */
    .hk-section {
        padding: var(--space-7) 0;
    }

    .hk-section.is-alt {
        background: var(--hk-surface);
        border-top: 1px solid var(--hk-line);
        border-bottom: 1px solid var(--hk-line);
    }

    .hk-head {
        max-width: 640px;
        margin-bottom: var(--space-5);
    }

    .hk-head h2 {
        font-size: clamp(20px, 1vw + 15px, 25px);
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--text-primary);
    }

    .hk-head p {
        margin-top: 6px;
        font-size: var(--text-sm);
        line-height: 1.7;
        color: var(--text-muted);
    }

    /* Hizmet alanları */
    .hk-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: var(--space-4);
    }

    .hk-tile {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: var(--space-4);
        border: 1px solid var(--hk-line);
        border-radius: var(--hk-radius);
        background: var(--bg-body);
        transition: border-color 0.15s ease;
    }

    .hk-tile:hover {
        border-color: var(--hk-accent);
    }

    .hk-tile-icon {
        flex-shrink: 0;
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        border-radius: 8px;
        background: color-mix(in srgb, var(--primary) 10%, transparent);
        color: var(--hk-accent);
        font-size: 14px;
    }

    .hk-tile b {
        display: block;
        font-size: var(--text-sm);
        font-weight: 600;
        line-height: 1.35;
        color: var(--text-primary);
    }

    .hk-tile span {
        display: block;
        margin-top: 2px;
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    /* İlkeler */
    .hk-principles {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--space-4);
    }

    .hk-principle {
        padding: var(--space-5);
        border: 1px solid var(--hk-line);
        border-radius: var(--hk-radius);
        background: var(--bg-body);
    }

    .hk-principle-top {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 9px;
    }

    .hk-principle-icon {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        border-radius: 8px;
        background: color-mix(in srgb, var(--primary) 10%, transparent);
        color: var(--hk-accent);
        font-size: 14px;
    }

    .hk-principle h3 {
        font-size: var(--text-base);
        font-weight: 600;
        color: var(--text-primary);
    }

    .hk-principle p {
        font-size: var(--text-sm);
        line-height: 1.7;
        color: var(--text-muted);
    }

    .hk-principle a {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: var(--space-3);
        font-size: var(--text-sm);
        font-weight: 600;
        color: var(--hk-accent);
    }

    .hk-principle a:hover {
        text-decoration: underline;
    }

    .hk-principle a i {
        font-size: 10px;
    }

    /* Şirket bilgileri */
    .hk-id {
        max-width: 640px;
        border: 1px solid var(--hk-line);
        border-radius: var(--hk-radius);
        background: var(--bg-body);
        overflow: hidden;
    }

    .hk-id-row {
        display: grid;
        grid-template-columns: 170px minmax(0, 1fr);
        gap: var(--space-3);
        padding: 10px var(--space-4);
        border-bottom: 1px solid var(--border-light);
        font-size: var(--text-sm);
    }

    .hk-id-row:last-child {
        border-bottom: none;
    }

    .hk-id-row dt {
        font-weight: 600;
        color: var(--text-secondary);
    }

    .hk-id-row dd {
        margin: 0;
        color: var(--text-muted);
        word-break: break-word;
    }

    .hk-id-row dd a:hover {
        color: var(--hk-accent);
    }

    /* Kapanış */
    .hk-cta {
        padding: var(--space-6) var(--space-5);
        border: 1px solid var(--hk-line);
        border-radius: var(--hk-radius);
        background: var(--hk-surface);
        text-align: center;
    }

    .hk-cta h2 {
        font-size: clamp(19px, 1vw + 14px, 23px);
        font-weight: 700;
        color: var(--text-primary);
    }

    .hk-cta p {
        max-width: 520px;
        margin: 9px auto 0;
        font-size: var(--text-sm);
        line-height: 1.65;
        color: var(--text-muted);
    }

    .hk-cta-row {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: var(--space-4);
    }

    /* Sarmalayıcıyla yazılır: ".hk a { color: inherit }" reseti
       aksi halde beyaz yazıyı eziyor. */
    .hk .hk-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 11px 22px;
        border: 1px solid transparent;
        border-radius: 8px;
        font-size: var(--text-sm);
        font-weight: 600;
    }

    .hk .hk-btn-primary {
        background: var(--hk-accent);
        color: #fff;
    }

    .hk .hk-btn-primary:hover {
        background: var(--primary-dark);
    }

    .hk .hk-btn-outline {
        border-color: var(--hk-line);
        color: var(--text-secondary);
    }

    .hk .hk-btn-outline:hover {
        border-color: var(--hk-accent);
        color: var(--hk-accent);
    }

    /* ==========================================
       Açık tema
       ========================================== */
    [data-theme="light"] .hk {
        --hk-line: #d3e2f8;
        background: #eff5fe;
    }

    [data-theme="light"] .hk-hero {
        background: linear-gradient(180deg, #e4edfb 0%, #eff5fe 100%);
    }

    [data-theme="light"] .hk-section.is-alt {
        background: #e4edfb;
    }

    [data-theme="light"] .hk-metric,
    [data-theme="light"] .hk-cta {
        box-shadow:
            0 1px 2px rgba(36, 116, 245, 0.05),
            0 10px 26px -14px rgba(36, 116, 245, 0.28);
    }

    [data-theme="light"] .hk-tile,
    [data-theme="light"] .hk-principle,
    [data-theme="light"] .hk-id {
        background: #fff;
    }

    @media (max-width: 900px) {

        .hk-metrics,
        .hk-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .hk-principles {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 540px) {

        .hk-metrics,
        .hk-grid {
            grid-template-columns: 1fr;
        }

        .hk-id-row {
            grid-template-columns: 1fr;
            gap: 2px;
        }
    }
</style>

<div class="hk">

    <section class="hk-hero">
        <div class="container">
            <div class="hk-hero-inner">
                <span class="hk-eyebrow"><i class="fas fa-building"></i> Hakkımızda</span>
                <h1 class="hk-title">Ne yapıyoruz</h1>
                <p class="hk-lead">
                    Web barındırma, sanal ve fiziksel sunucu, alan adı ve SSL hizmetleri sunuyoruz.
                    Taahhütlerimizi yazılı belgelere bağlıyor, altyapıyı kendi ekibimizle işletiyor
                    ve destek taleplerini panel üzerinden kayıt altında yürütüyoruz.
                </p>
            </div>

            <?php if ($olcutler): ?>
                <div class="hk-metrics">
                    <?php foreach ($olcutler as [$ikon, $deger, $etiket, $baglanti]): ?>
                        <a class="hk-metric" href="<?= htmlspecialchars($baglanti) ?>">
                            <i class="fas <?= htmlspecialchars($ikon) ?>"></i>
                            <b><?= htmlspecialchars($deger) ?></b>
                            <span><?= htmlspecialchars($etiket) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Hizmet alanları -->
    <?php if ($gruplar): ?>
        <section class="hk-section">
            <div class="container">
                <div class="hk-head">
                    <h2>Hizmet alanlarımız</h2>
                    <p>Aşağıdaki başlıklarda satışa açık paketlerimiz bulunuyor. Sayılar mağazadaki
                        güncel paket sayısını gösterir.</p>
                </div>

                <div class="hk-grid">
                    <?php foreach ($gruplar as $g): ?>
                        <a class="hk-tile" href="magaza.php?group=<?= urlencode((string) $g['slug']) ?>">
                            <span class="hk-tile-icon">
                                <i class="fas <?= $turIkon[$g['type'] ?? 'other'] ?? 'fa-box' ?>"></i>
                            </span>
                            <span>
                                <b><?= htmlspecialchars((string) $g['name']) ?></b>
                                <span><?= (int) $g['adet'] ?> paket</span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Çalışma biçimi -->
    <section class="hk-section is-alt">
        <div class="container">
            <div class="hk-head">
                <h2>Nasıl çalışıyoruz</h2>
                <p>Aşağıdaki maddelerin her biri yayımlanmış bir belgeye dayanır; sözde kalmaz,
                    bağlantısından okuyabilirsiniz.</p>
            </div>

            <div class="hk-principles">
                <?php foreach ($ilkeler as [$ikon, $baslik, $metin, $adres, $belge]): ?>
                    <div class="hk-principle">
                        <div class="hk-principle-top">
                            <span class="hk-principle-icon"><i class="fas <?= htmlspecialchars($ikon) ?>"></i></span>
                            <h3><?= htmlspecialchars($baslik) ?></h3>
                        </div>
                        <p><?= htmlspecialchars($metin) ?></p>
                        <a href="<?= htmlspecialchars($adres) ?>">
                            <?= htmlspecialchars($belge) ?> <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Şirket bilgileri -->
    <?php if ($kimlik): ?>
        <section class="hk-section">
            <div class="container">
                <div class="hk-head">
                    <h2>Şirket bilgileri</h2>
                    <p>Sözleşme ve fatura işlemlerinde geçerli olan bilgiler.</p>
                </div>

                <dl class="hk-id">
                    <?php foreach ($kimlik as $etiket => $deger): ?>
                        <div class="hk-id-row">
                            <dt><?= htmlspecialchars($etiket) ?></dt>
                            <dd>
                                <?php if ($etiket === 'E-posta'): ?>
                                    <a href="mailto:<?= htmlspecialchars($deger) ?>"
                                        dir="ltr"><?= htmlspecialchars($deger) ?></a>
                                <?php elseif ($etiket === 'Telefon'): ?>
                                    <a href="tel:<?= htmlspecialchars(preg_replace('/\D+/', '', $deger) ?? '') ?>"
                                        dir="ltr"><?= htmlspecialchars($deger) ?></a>
                                <?php else: ?>
                                    <?= nl2br(htmlspecialchars($deger)) ?>
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </div>
        </section>
    <?php endif; ?>

    <!-- Kapanış -->
    <section class="hk-section <?= $kimlik ? 'is-alt' : '' ?>">
        <div class="container">
            <div class="hk-cta">
                <h2>Projenizi konuşalım</h2>
                <p>İhtiyacınızı dinleyip uygun yapılandırmayı birlikte belirleyelim.
                    Hangi hizmetin size uyduğundan emin değilseniz sorun, yönlendirelim.</p>
                <div class="hk-cta-row">
                    <a href="iletisim.php#iletisim-formu" class="hk-btn hk-btn-primary">
                        <i class="fas fa-envelope"></i> Bize ulaşın
                    </a>
                    <a href="referanslar.php" class="hk-btn hk-btn-outline">
                        <i class="fas fa-handshake"></i> Referanslarımız
                    </a>
                </div>
            </div>
        </div>
    </section>

</div>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>
