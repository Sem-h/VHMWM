<?php
/**
 * VHM - İptal ve İade Politikası
 *
 * İade süreleri ve kuralları iade_kurallari tablosundan, süreler
 * ayarlardan gelir; sayfaya sabit gün yazılmaz.
 *
 * Önceki sürümde sayfa kendi içinde çelişiyordu: üstte "koşulsuz iade"
 * yazarken hemen altında beş koşul sıralanıyordu. Ayrıca veri saklama
 * süresi Kullanım Şartları'ndaki süreyle uyuşmuyordu.
 *
 * NOT: Bu metin bir taslaktır ve hukukçu incelemesinden geçirilmelidir.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Settings.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'İptal ve İade Politikası';
$pageDescription = 'Cayma hakkı, iade süreleri ve iptal işlemleri.';

$kurallar = [];
try {
    $kurallar = Database::fetchAll(
        "SELECT * FROM iade_kurallari WHERE is_active = 1 ORDER BY sort_order, id"
    );
} catch (Throwable $e) {
    error_log('İade kuralları okunamadı: ' . $e->getMessage());
}

$sayi = static fn(string $anahtar, int $varsayilan): int =>
    max(0, (int) Settings::get($anahtar, $varsayilan));

$saklamaGun = $sayi('iade_veri_saklama_gun', 7);
$onayGun = $sayi('iade_onay_gun', 3);
$odemeGun = $sayi('iade_odeme_gun', 10);
$yururluk = trim((string) Settings::get('refund_yururluk', ''));

/* Tam iade veren en uzun süre; başlıkta kullanılır */
$enUzunTam = 0;
foreach ($kurallar as $k) {
    if ($k['tur'] === 'tam') {
        $enUzunTam = max($enUzunTam, (int) $k['gun']);
    }
}

$turAdi = [
    'tam' => 'Tam iade',
    'oransal' => 'Oransal iade',
    'yok' => 'İade yok',
];

require_once __DIR__ . '/theme/includes/header.php';
?>

<style>
    /* ==========================================
       İade Politikası - ia
       ========================================== */
    .ia {
        --ia-line: var(--border-color);
        --ia-surface: var(--bg-primary);
        --ia-accent: var(--primary);
        --ia-radius: 10px;
    }

    .ia a {
        color: var(--ia-accent);
    }

    .ia a:hover {
        text-decoration: underline;
    }

    /* ===== Üst bilgi ===== */
    .ia-hero {
        position: relative;
        overflow: hidden;
        background: var(--gradient-hero);
        border-bottom: 1px solid var(--ia-line);
        padding: var(--space-7) 0;
    }

    .ia-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 55% 50% at 15% 25%, color-mix(in srgb, var(--primary) 16%, transparent) 0%, transparent 62%),
            radial-gradient(ellipse 45% 45% at 85% 10%, color-mix(in srgb, var(--secondary) 12%, transparent) 0%, transparent 58%);
        pointer-events: none;
    }

    .ia-hero>.container {
        position: relative;
        z-index: 1;
    }

    .ia-hero-inner {
        max-width: 700px;
        margin: 0 auto;
        text-align: center;
    }

    .ia-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 50px;
        border: 1px solid var(--ia-line);
        background: var(--ia-surface);
        font-size: var(--text-xs);
        font-weight: 600;
        color: var(--text-secondary);
    }

    .ia-eyebrow i {
        color: var(--ia-accent);
    }

    .ia-title {
        margin: var(--space-3) 0 0;
        font-size: clamp(26px, 2vw + 18px, 38px);
        font-weight: 700;
        letter-spacing: -0.02em;
        line-height: 1.2;
        color: var(--text-primary);
    }

    .ia-lead {
        max-width: 580px;
        margin: var(--space-3) auto 0;
        font-size: var(--text-base);
        line-height: 1.65;
        color: var(--text-muted);
    }

    .ia-date {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: var(--space-4);
        padding: 6px 14px;
        border: 1px solid var(--ia-line);
        border-radius: 50px;
        background: var(--ia-surface);
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    /* ===== Gövde ===== */
    .ia-body {
        padding: var(--space-7) 0;
    }

    .ia-wrap {
        max-width: 880px;
        margin: 0 auto;
        display: grid;
        gap: var(--space-5);
    }

    .ia-card {
        border: 1px solid var(--ia-line);
        border-radius: var(--ia-radius);
        background: var(--ia-surface);
        overflow: hidden;
    }

    .ia-card-head {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: var(--space-3) var(--space-5);
        border-bottom: 1px solid var(--ia-line);
        background: color-mix(in srgb, var(--primary) 5%, transparent);
    }

    .ia-card-head i {
        color: var(--ia-accent);
    }

    .ia-card-head h2 {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-primary);
    }

    .ia-card-body {
        padding: var(--space-5);
    }

    .ia-card-body p {
        font-size: var(--text-sm);
        line-height: 1.75;
        color: var(--text-muted);
    }

    .ia-card-body p+p,
    .ia-card-body ul+p,
    .ia-card-body>*+.ia-table-wrap {
        margin-top: var(--space-3);
    }

    .ia-card-body strong {
        color: var(--text-primary);
    }

    .ia-list {
        margin: var(--space-3) 0 0;
        padding: 0;
        list-style: none;
    }

    .ia-list li {
        position: relative;
        padding: 6px 0 6px 22px;
        font-size: var(--text-sm);
        line-height: 1.7;
        color: var(--text-muted);
    }

    .ia-list li::before {
        content: '';
        position: absolute;
        left: 4px;
        top: 15px;
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: var(--ia-accent);
    }

    /* İki hakkı ayıran kutular */
    .ia-split {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--space-4);
    }

    .ia-box {
        padding: var(--space-4);
        border: 1px solid var(--ia-line);
        border-radius: 8px;
        background: var(--bg-body);
    }

    .ia-box h3 {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 7px;
        font-size: var(--text-sm);
        font-weight: 700;
        color: var(--text-primary);
    }

    .ia-box h3 i {
        color: var(--ia-accent);
    }

    .ia-box p {
        font-size: var(--text-sm);
        line-height: 1.65;
        color: var(--text-muted);
    }

    .ia-box b {
        display: block;
        margin-top: 9px;
        font-size: var(--text-xs);
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--ia-accent);
    }

    /* Tablo */
    .ia-table-wrap {
        border: 1px solid var(--ia-line);
        border-radius: 8px;
        overflow-x: auto;
    }

    .ia-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 520px;
    }

    .ia-table th,
    .ia-table td {
        padding: 11px var(--space-4);
        text-align: left;
        font-size: var(--text-sm);
        border-bottom: 1px solid var(--border-light);
        vertical-align: top;
    }

    .ia-table thead th {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--text-muted);
        border-bottom: 1px solid var(--ia-line);
        background: color-mix(in srgb, var(--primary) 4%, transparent);
    }

    .ia-table tbody th {
        font-weight: 700;
        color: var(--text-primary);
        white-space: nowrap;
    }

    .ia-table td {
        color: var(--text-muted);
    }

    .ia-table tr:last-child th,
    .ia-table tr:last-child td {
        border-bottom: none;
    }

    .ia-rozet {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }

    .ia-rozet.tam {
        background: color-mix(in srgb, var(--success) 14%, transparent);
        color: var(--success);
    }

    .ia-rozet.oransal {
        background: color-mix(in srgb, var(--warning) 16%, transparent);
        color: var(--warning);
    }

    .ia-rozet.yok {
        background: var(--bg-secondary);
        color: var(--text-muted);
    }

    /* Hesap örneği */
    .ia-formul {
        margin-top: var(--space-3);
        padding: var(--space-4);
        border: 1px solid var(--ia-line);
        border-radius: 8px;
        background: var(--bg-body);
        font-size: var(--text-sm);
        line-height: 1.8;
        color: var(--text-muted);
    }

    .ia-formul code {
        padding: 2px 7px;
        border: 1px solid var(--ia-line);
        border-radius: 5px;
        background: var(--ia-surface);
        font-family: ui-monospace, Consolas, monospace;
        font-size: 0.92em;
        color: var(--text-primary);
    }

    /* Adımlar */
    .ia-steps {
        display: grid;
        gap: var(--space-3);
        counter-reset: adim;
    }

    .ia-step {
        position: relative;
        padding-left: 38px;
    }

    .ia-step::before {
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
        background: var(--ia-accent);
        color: #fff;
        font-size: 12px;
        font-weight: 700;
    }

    .ia-step b {
        display: block;
        font-size: var(--text-sm);
        font-weight: 600;
        color: var(--text-primary);
    }

    .ia-step span {
        display: block;
        margin-top: 3px;
        font-size: var(--text-sm);
        line-height: 1.65;
        color: var(--text-muted);
    }

    .ia-note {
        margin-top: var(--space-3);
        font-size: var(--text-xs);
        line-height: 1.6;
        color: var(--text-muted);
    }

    .ia-empty {
        padding: var(--space-5);
        text-align: center;
        border: 1px dashed var(--ia-line);
        border-radius: 8px;
        font-size: var(--text-sm);
        color: var(--text-muted);
    }

    /* ==========================================
       Açık tema
       ========================================== */
    [data-theme="light"] .ia {
        --ia-line: #d3e2f8;
        background: #eff5fe;
    }

    [data-theme="light"] .ia-hero {
        background: linear-gradient(180deg, #e4edfb 0%, #eff5fe 100%);
    }

    [data-theme="light"] .ia-card {
        box-shadow:
            0 1px 2px rgba(36, 116, 245, 0.05),
            0 10px 26px -14px rgba(36, 116, 245, 0.28);
    }

    [data-theme="light"] .ia-box,
    [data-theme="light"] .ia-formul {
        background: #fff;
    }

    @media (max-width: 640px) {
        .ia-split {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="ia">

    <section class="ia-hero">
        <div class="container">
            <div class="ia-hero-inner">
                <span class="ia-eyebrow"><i class="fas fa-rotate-left"></i> İptal ve İade</span>
                <h1 class="ia-title">İade koşulları</h1>
                <p class="ia-lead">
                    Hangi hizmette ne kadar süreyle iade yapılabildiği, başvurunun nasıl
                    yapılacağı ve paranın ne zaman geri döneceği aşağıda açıklanmıştır.
                </p>
                <?php if ($yururluk !== ''): ?>
                    <span class="ia-date">
                        <i class="fas fa-calendar-check"></i>
                        Yürürlük tarihi: <?= htmlspecialchars(date('d.m.Y', strtotime($yururluk))) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="ia-body">
        <div class="container">
            <div class="ia-wrap">

                <!-- İki ayrı hak -->
                <div class="ia-card">
                    <div class="ia-card-head">
                        <i class="fas fa-scale-balanced"></i>
                        <h2>İki ayrı hakkınız var</h2>
                    </div>
                    <div class="ia-card-body">
                        <p>
                            İadeye ilişkin iki farklı hak söz konusudur. Bunlar birbirinin yerine
                            geçmez; koşulları ve kapsamları farklıdır.
                        </p>

                        <div class="ia-split" style="margin-top: var(--space-4);">
                            <div class="ia-box">
                                <h3><i class="fas fa-gavel"></i> Yasal cayma hakkı</h3>
                                <p>
                                    Mesafeli sözleşmelerde tüketicinin on dört gün içinde gerekçe
                                    göstermeden cayma hakkı vardır. Elektronik ortamda anında ifa edilen
                                    ve sizin onayınızla ifasına başlanan hizmetlerde bu hakkın kapsamı
                                    mevzuatla sınırlandırılmıştır.
                                </p>
                                <b>Kaynağı: mevzuat</b>
                            </div>
                            <div class="ia-box">
                                <h3><i class="fas fa-handshake"></i> Bizim iade taahhüdümüz</h3>
                                <p>
                                    Yasal hakkın ötesinde, aşağıdaki tabloda belirtilen sürelerde
                                    iade yapıyoruz. Bu, kendi ticari taahhüdümüzdür ve yasal cayma
                                    hakkınızı ortadan kaldırmaz.
                                </p>
                                <b>Kaynağı: bu politika</b>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- İade süreleri -->
                <div class="ia-card">
                    <div class="ia-card-head">
                        <i class="fas fa-clock"></i>
                        <h2>Hizmete göre iade</h2>
                    </div>
                    <div class="ia-card-body">
                        <?php if (!$kurallar): ?>
                            <div class="ia-empty">İade kuralları henüz tanımlanmadı.</div>
                        <?php else: ?>
                            <div class="ia-table-wrap">
                                <table class="ia-table">
                                    <thead>
                                        <tr>
                                            <th>Hizmet</th>
                                            <th>Süre</th>
                                            <th>Kapsam</th>
                                            <th>Açıklama</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($kurallar as $k): ?>
                                            <tr>
                                                <th><?= htmlspecialchars((string) $k['ad']) ?></th>
                                                <td><?= (int) $k['gun'] > 0 ? (int) $k['gun'] . ' gün' : '—' ?></td>
                                                <td>
                                                    <span class="ia-rozet <?= htmlspecialchars((string) $k['tur']) ?>">
                                                        <?= $turAdi[$k['tur']] ?? $k['tur'] ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars((string) ($k['aciklama'] ?? '')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <p>
                                Süreler, hizmetin ilk kez satın alındığı tarihten itibaren işler.
                                Yenileme ödemeleri bu taahhüdün kapsamı dışındadır.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Oransal iade hesabı -->
                <?php
                $oransalVar = false;
                foreach ($kurallar as $k) {
                    if ($k['tur'] === 'oransal') {
                        $oransalVar = true;
                        break;
                    }
                }
                ?>
                <?php if ($oransalVar): ?>
                    <div class="ia-card">
                        <div class="ia-card-head">
                            <i class="fas fa-calculator"></i>
                            <h2>Oransal iade nasıl hesaplanır</h2>
                        </div>
                        <div class="ia-card-body">
                            <p>
                                Oransal iadede, kullanılmayan günlerin karşılığı geri ödenir. Kurulum
                                bedeli ve donanım tedariki için yapılan harcamalar düşülür.
                            </p>

                            <div class="ia-formul">
                                <code>İade = (Dönem bedeli ÷ Dönem gün sayısı) × Kalan gün − Kurulum bedeli</code>
                                <p style="margin-top: var(--space-3);">
                                    <strong>Örnek:</strong> Aylık 3.000 ₺ bedelli bir hizmette 5. günde
                                    iade talep edildiğinde, 30 günlük dönemin 25 günü kullanılmamıştır.
                                    Kurulum bedeli yoksa iade tutarı
                                    <code>(3.000 ÷ 30) × 25 = 2.500 ₺</code> olur.
                                </p>
                            </div>

                            <p class="ia-note">
                                Gün hesabı, hizmetin kurulum tarihinden talebin bize ulaştığı tarihe
                                kadar geçen tam günler üzerinden yapılır.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Hangi durumlarda iade yapılmaz -->
                <div class="ia-card">
                    <div class="ia-card-head">
                        <i class="fas fa-circle-exclamation"></i>
                        <h2>İade yapılmayan durumlar</h2>
                    </div>
                    <div class="ia-card-body">
                        <p>
                            Yukarıdaki süreler içinde olsa dahi aşağıdaki hâllerde ticari iade
                            taahhüdümüz uygulanmaz:
                        </p>
                        <ul class="ia-list">
                            <li>Kullanım Şartları'na aykırı kullanım nedeniyle hizmetin sonlandırılması
                                (spam gönderimi, zararlı yazılım barındırma, yetkisiz erişim girişimi)</li>
                            <li>Üçüncü taraf nezdinde geri alınamayan işlemler: alan adı kaydı ve
                                transferi, düzenlenmiş SSL sertifikaları, üçüncü taraf lisansları</li>
                            <li>Kurulum ücretleri ve tek seferlik hizmet bedelleri</li>
                            <li>Tabloda belirtilen sürenin dolmuş olması</li>
                            <li>Aynı müşteri tarafından aynı hizmet için daha önce iade alınmış olması</li>
                        </ul>
                        <p class="ia-note">
                            Bu kısıtlamalar, tüketici mevzuatından doğan haklarınızı ortadan kaldırmaz.
                            Yasal cayma hakkınız saklıdır.
                        </p>
                    </div>
                </div>

                <!-- Başvuru -->
                <div class="ia-card">
                    <div class="ia-card-head">
                        <i class="fas fa-file-signature"></i>
                        <h2>İade talebi nasıl yapılır</h2>
                    </div>
                    <div class="ia-card-body">
                        <div class="ia-steps">
                            <div class="ia-step">
                                <b>Panelden destek talebi açın</b>
                                <span>Konu başlığına "İade talebi" yazın ve hangi hizmet için talepte
                                    bulunduğunuzu belirtin.</span>
                            </div>
                            <div class="ia-step">
                                <b>Gerekçenizi yazın</b>
                                <span>Zorunlu değildir, ancak sorunu çözebiliyorsak önce onu denemek
                                    isteriz.</span>
                            </div>
                            <div class="ia-step">
                                <b>Değerlendirme</b>
                                <span>Talebiniz en geç <strong><?= $onayGun ?> iş günü</strong> içinde
                                    sonuçlandırılır ve sonuç aynı kayıt üzerinden bildirilir.</span>
                            </div>
                            <div class="ia-step">
                                <b>Ödeme</b>
                                <span>İade, ödemeyi yaptığınız yönteme yapılır. Onaydan sonra
                                    <strong><?= $odemeGun ?> iş günü</strong> içinde başlatılır;
                                    kartınıza yansıma süresi bankanıza bağlıdır.</span>
                            </div>
                        </div>

                        <p class="ia-note">
                            <a href="client/tickets.php">Destek talebi açın</a> &middot;
                            Hesabınız yoksa <a href="iletisim.php#iletisim-formu">iletişim formundan</a>
                            da ulaşabilirsiniz.
                        </p>
                    </div>
                </div>

                <!-- İptal -->
                <div class="ia-card">
                    <div class="ia-card-head">
                        <i class="fas fa-calendar-xmark"></i>
                        <h2>Hizmet iptali</h2>
                    </div>
                    <div class="ia-card-body">
                        <p>
                            İptal ile iade farklı işlemlerdir. İptal, hizmetin yenilenmemesi anlamına
                            gelir; ödediğiniz dönemin sonuna kadar hizmet çalışmaya devam eder.
                        </p>
                        <ul class="ia-list">
                            <li>İptal talebini müşteri panelinizdeki hizmet ayrıntısı sayfasından
                                oluşturabilirsiniz</li>
                            <li>Talep, yenileme tarihinden önce iletilmelidir; sonrasında oluşan
                                fatura iptal edilemez</li>
                            <li>İptal edilen hizmet dönem sonuna kadar açık kalır</li>
                            <li>Dönem sonunda hizmet kapatılır ve verileriniz
                                <strong><?= $saklamaGun ?> gün</strong> boyunca saklanır</li>
                            <li>Bu süre içinde talep ederseniz verileriniz size teslim edilir;
                                süre dolduğunda kalıcı olarak silinir</li>
                        </ul>
                        <p class="ia-note">
                            <strong>İptal etmeden önce yedeğinizi alın.</strong> Saklama süresi
                            dolduktan sonra veri kurtarma mümkün değildir.
                            Ayrıntı için <a href="kullanim-sartlari.php#askiya">Kullanım Şartları</a>.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </section>

</div>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>
