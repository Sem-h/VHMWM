<?php
/**
 * VHM - Kullanım Şartları
 *
 * Satıcı kimlik bilgileri ve yürürlük tarihi ayarlardan gelir; sayfaya
 * sabit yazılmaz. Doldurulmamış alanlar hiç basılmaz, böylece eksik
 * bilgi uydurulmuş gibi görünmez.
 *
 * NOT: Bu metin bir taslaktır ve hukukçu incelemesinden geçirilmelidir.
 * Önceki sürümdeki iki madde uygulanamaz durumdaydı:
 *   - "önceden bildirim yapılmaksızın değiştirilebilir" (haksız şart)
 *   - sorumsuzluk kaydında kasıt/ağır kusur istisnasının bulunmaması
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Settings.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Kullanım Şartları';
$pageDescription = 'Hizmet kullanım şartları, tarafların hak ve yükümlülükleri.';

$ayar = static fn(string $anahtar): string => trim((string) Settings::get($anahtar, ''));

$unvan = $ayar('company_legal_name') !== '' ? $ayar('company_legal_name') : $ayar('company_name');
if ($unvan === '') {
    $unvan = 'Şirketimiz';
}

/* Satıcı kimlik satırları - yalnızca dolu olanlar gösterilir */
$kimlik = array_filter([
    'Unvan' => $unvan,
    'Adres' => $ayar('company_address'),
    'E-posta' => $ayar('company_email'),
    'Telefon' => $ayar('company_phone'),
    'Vergi dairesi' => $ayar('company_tax_office'),
    'Vergi numarası' => $ayar('company_tax_number'),
    'MERSİS numarası' => $ayar('company_mersis'),
    'Ticaret sicil no' => $ayar('company_trade_registry'),
], static fn(string $v): bool => $v !== '');

$eksikKimlik = array_diff(
    ['company_tax_office', 'company_tax_number', 'company_mersis'],
    array_keys(array_filter([
        'company_tax_office' => $ayar('company_tax_office'),
        'company_tax_number' => $ayar('company_tax_number'),
        'company_mersis' => $ayar('company_mersis'),
    ]))
);

$yururluk = $ayar('terms_yururluk');

require_once __DIR__ . '/theme/includes/header.php';
?>

<style>
    /* ==========================================
       Kullanım Şartları - ks
       Sözleşme metni: numaralı maddeler, geniş satır
       aralığı, okunabilir ölçü. Renk yalnızca vurgu için.
       ========================================== */
    .ks {
        --ks-line: var(--border-color);
        --ks-surface: var(--bg-primary);
        --ks-accent: var(--primary);
        --ks-radius: 10px;
    }

    .ks a {
        color: var(--ks-accent);
    }

    .ks a:hover {
        text-decoration: underline;
    }

    /* ===== Üst bilgi ===== */
    .ks-hero {
        position: relative;
        overflow: hidden;
        background: var(--gradient-hero);
        border-bottom: 1px solid var(--ks-line);
        padding: var(--space-7) 0;
    }

    .ks-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 55% 50% at 15% 25%, color-mix(in srgb, var(--primary) 16%, transparent) 0%, transparent 62%),
            radial-gradient(ellipse 45% 45% at 85% 10%, color-mix(in srgb, var(--secondary) 12%, transparent) 0%, transparent 58%);
        pointer-events: none;
    }

    .ks-hero>.container {
        position: relative;
        z-index: 1;
    }

    .ks-hero-inner {
        max-width: 700px;
        margin: 0 auto;
        text-align: center;
    }

    .ks-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 50px;
        border: 1px solid var(--ks-line);
        background: var(--ks-surface);
        font-size: var(--text-xs);
        font-weight: 600;
        color: var(--text-secondary);
    }

    .ks-eyebrow i {
        color: var(--ks-accent);
    }

    .ks-title {
        margin: var(--space-3) 0 0;
        font-size: clamp(26px, 2vw + 18px, 38px);
        font-weight: 700;
        letter-spacing: -0.02em;
        line-height: 1.2;
        color: var(--text-primary);
    }

    .ks-lead {
        max-width: 580px;
        margin: var(--space-3) auto 0;
        font-size: var(--text-base);
        line-height: 1.65;
        color: var(--text-muted);
    }

    .ks-date {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: var(--space-4);
        padding: 6px 14px;
        border: 1px solid var(--ks-line);
        border-radius: 50px;
        background: var(--ks-surface);
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    /* ===== Gövde ===== */
    .ks-body {
        padding: var(--space-7) 0;
    }

    .ks-layout {
        display: grid;
        grid-template-columns: 230px minmax(0, 1fr);
        gap: var(--space-6);
        align-items: start;
        max-width: 1000px;
        margin: 0 auto;
    }

    /* İçindekiler */
    .ks-toc {
        position: sticky;
        top: 90px;
        border: 1px solid var(--ks-line);
        border-radius: var(--ks-radius);
        background: var(--ks-surface);
        overflow: hidden;
    }

    .ks-toc h2 {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 11px var(--space-4);
        border-bottom: 1px solid var(--ks-line);
        background: color-mix(in srgb, var(--primary) 5%, transparent);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-primary);
    }

    .ks-toc h2 i {
        color: var(--ks-accent);
    }

    .ks-toc ol {
        margin: 0;
        padding: 6px;
        list-style: none;
        counter-reset: madde;
    }

    /* Satır: numara rozeti + başlık. Numara sabit genişlikte
       olduğu için başlıklar iki haneli maddede de hizalı kalır. */
    .ks-toc a {
        position: relative;
        display: grid;
        grid-template-columns: 22px minmax(0, 1fr);
        align-items: center;
        gap: 9px;
        padding: 7px 9px;
        border-radius: 7px;
        font-size: var(--text-sm);
        line-height: 1.4;
        color: var(--text-secondary);
        transition: background-color 0.15s ease, color 0.15s ease;
    }

    .ks-toc a::before {
        counter-increment: madde;
        content: counter(madde);
        display: grid;
        place-items: center;
        height: 22px;
        border-radius: 6px;
        background: var(--bg-body);
        border: 1px solid var(--ks-line);
        font-size: 11px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        color: var(--text-muted);
        transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }

    .ks-toc a:hover {
        background: color-mix(in srgb, var(--primary) 7%, transparent);
        color: var(--text-primary);
        text-decoration: none;
    }

    .ks-toc a:hover::before {
        border-color: var(--ks-accent);
        color: var(--ks-accent);
    }

    /* Okunmakta olan madde */
    .ks-toc a.is-current {
        background: color-mix(in srgb, var(--primary) 11%, transparent);
        color: var(--ks-accent);
        font-weight: 600;
    }

    .ks-toc a.is-current::before {
        background: var(--ks-accent);
        border-color: var(--ks-accent);
        color: #fff;
    }

    /* Sol vurgu çubuğu */
    .ks-toc a.is-current::after {
        content: '';
        position: absolute;
        left: -6px;
        top: 8px;
        bottom: 8px;
        width: 3px;
        border-radius: 0 3px 3px 0;
        background: var(--ks-accent);
    }

    /* Metin */
    .ks-doc {
        padding: var(--space-6);
        border: 1px solid var(--ks-line);
        border-radius: var(--ks-radius);
        background: var(--ks-surface);
    }

    .ks-doc section+section {
        margin-top: var(--space-6);
    }

    .ks-doc h2 {
        margin-bottom: var(--space-3);
        padding-bottom: 9px;
        border-bottom: 1px solid var(--ks-line);
        font-size: var(--text-md);
        font-weight: 700;
        letter-spacing: -0.01em;
        color: var(--text-primary);
        scroll-margin-top: 90px;
    }

    .ks-doc h2 span {
        display: inline-block;
        min-width: 26px;
        color: var(--ks-accent);
    }

    .ks-doc h3 {
        margin: var(--space-4) 0 7px;
        font-size: var(--text-sm);
        font-weight: 700;
        color: var(--text-primary);
    }

    .ks-doc p {
        font-size: var(--text-sm);
        line-height: 1.75;
        color: var(--text-muted);
    }

    .ks-doc p+p {
        margin-top: var(--space-3);
    }

    .ks-doc ul {
        margin: var(--space-3) 0 0;
        padding: 0;
        list-style: none;
    }

    .ks-doc ul li {
        position: relative;
        padding: 5px 0 5px 21px;
        font-size: var(--text-sm);
        line-height: 1.7;
        color: var(--text-muted);
    }

    .ks-doc ul li::before {
        content: '';
        position: absolute;
        left: 4px;
        top: 14px;
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: var(--ks-accent);
    }

    /* Kimlik tablosu */
    .ks-id {
        margin-top: var(--space-3);
        border: 1px solid var(--ks-line);
        border-radius: 8px;
        overflow: hidden;
    }

    .ks-id-row {
        display: grid;
        grid-template-columns: 160px minmax(0, 1fr);
        gap: var(--space-3);
        padding: 9px var(--space-4);
        border-bottom: 1px solid var(--border-light);
        font-size: var(--text-sm);
    }

    .ks-id-row:last-child {
        border-bottom: none;
    }

    .ks-id-row dt {
        font-weight: 600;
        color: var(--text-secondary);
    }

    .ks-id-row dd {
        margin: 0;
        color: var(--text-muted);
        word-break: break-word;
    }

    /* Uyarı kutusu */
    .ks-warn {
        display: flex;
        align-items: flex-start;
        gap: 11px;
        margin-top: var(--space-3);
        padding: 12px 15px;
        border: 1px solid color-mix(in srgb, var(--warning) 40%, transparent);
        border-radius: 8px;
        background: color-mix(in srgb, var(--warning) 9%, transparent);
        font-size: var(--text-sm);
        line-height: 1.6;
        color: var(--text-primary);
    }

    .ks-warn i {
        margin-top: 3px;
        color: var(--warning);
    }

    @media (max-width: 900px) {
        .ks-layout {
            grid-template-columns: 1fr;
        }

        .ks-toc {
            position: static;
        }
    }

    @media (max-width: 560px) {
        .ks-id-row {
            grid-template-columns: 1fr;
            gap: 2px;
        }

        .ks-doc {
            padding: var(--space-4);
        }
    }

    /* ==========================================
       Açık tema
       ========================================== */
    [data-theme="light"] .ks {
        --ks-line: #d3e2f8;
        background: #eff5fe;
    }

    [data-theme="light"] .ks-hero {
        background: linear-gradient(180deg, #e4edfb 0%, #eff5fe 100%);
    }

    [data-theme="light"] .ks-doc,
    [data-theme="light"] .ks-toc {
        box-shadow:
            0 1px 2px rgba(36, 116, 245, 0.05),
            0 10px 26px -14px rgba(36, 116, 245, 0.28);
    }
</style>

<?php
/* Madde başlıkları tek yerde: içindekiler ile metin ayrışamaz */
$maddeler = [
    'taraflar' => 'Taraflar ve tanımlar',
    'konu' => 'Sözleşmenin konusu',
    'kullanim' => 'Hizmetin kullanımı',
    'odeme' => 'Ücretler ve ödeme',
    'cayma' => 'Cayma hakkı ve iade',
    'seviye' => 'Hizmet seviyesi',
    'yedekleme' => 'Veri, yedekleme ve gizlilik',
    'hesap' => 'Hesap güvenliği',
    'askiya' => 'Askıya alma ve fesih',
    'sorumluluk' => 'Sorumluluğun sınırı',
    'degisiklik' => 'Şartlarda değişiklik',
    'uyusmazlik' => 'Uyuşmazlık çözümü',
];
$sira = 0;
?>

<div class="ks">

    <section class="ks-hero">
        <div class="container">
            <div class="ks-hero-inner">
                <span class="ks-eyebrow"><i class="fas fa-file-signature"></i> Kullanım Şartları</span>
                <h1 class="ks-title">Hizmet kullanım şartları</h1>
                <p class="ks-lead">
                    Bu metin, hizmetlerimizi kullanırken tarafların hak ve yükümlülüklerini düzenler.
                    Hizmeti kullanmaya başlamanız, şartları kabul ettiğiniz anlamına gelir.
                </p>
                <?php if ($yururluk !== ''): ?>
                    <span class="ks-date">
                        <i class="fas fa-calendar-check"></i>
                        Yürürlük tarihi: <?= htmlspecialchars(date('d.m.Y', strtotime($yururluk))) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="ks-body">
        <div class="container">
            <div class="ks-layout">

                <nav class="ks-toc" id="ks-toc" aria-label="İçindekiler">
                    <h2><i class="fas fa-list-ul"></i> İçindekiler</h2>
                    <ol>
                        <?php foreach ($maddeler as $anahtar => $baslik): ?>
                            <li><a href="#<?= $anahtar ?>"><?= htmlspecialchars($baslik) ?></a></li>
                        <?php endforeach; ?>
                    </ol>
                </nav>

                <div class="ks-doc">

                    <section>
                        <h2 id="taraflar"><span><?= ++$sira ?>.</span> <?= $maddeler['taraflar'] ?></h2>
                        <p>
                            Bu şartlar, aşağıda bilgileri yer alan hizmet sağlayıcı ("Sağlayıcı") ile
                            hizmetlerden yararlanan gerçek veya tüzel kişi ("Müşteri") arasında geçerlidir.
                        </p>

                        <dl class="ks-id">
                            <?php foreach ($kimlik as $etiket => $deger): ?>
                                <div class="ks-id-row">
                                    <dt><?= htmlspecialchars($etiket) ?></dt>
                                    <dd><?= nl2br(htmlspecialchars($deger)) ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>

                        <?php if ($eksikKimlik): ?>
                            <div class="ks-warn">
                                <i class="fas fa-triangle-exclamation"></i>
                                <span>
                                    Vergi ve MERSİS bilgileri henüz girilmediği için bu bölümde
                                    gösterilmiyor. Mesafeli sözleşmelerde satıcının bu bilgileri
                                    bulundurması gerekir; yönetim panelindeki Şirket Bilgileri
                                    bölümünden tamamlayın.
                                </span>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section>
                        <h2 id="konu"><span><?= ++$sira ?>.</span> <?= $maddeler['konu'] ?></h2>
                        <p>
                            Sözleşmenin konusu, Sağlayıcı tarafından sunulan barındırma, sunucu, alan adı,
                            sertifika ve bağlantılı hizmetlerin Müşteri'ye sunulmasıdır. Hizmetin kapsamı,
                            süresi ve bedeli sipariş sırasında seçilen pakete göre belirlenir ve
                            faturada gösterilir.
                        </p>
                    </section>

                    <section>
                        <h2 id="kullanim"><span><?= ++$sira ?>.</span> <?= $maddeler['kullanim'] ?></h2>
                        <p>Müşteri, hizmetleri kullanırken aşağıdaki kurallara uymayı kabul eder:</p>
                        <ul>
                            <li>Yürürlükteki mevzuata aykırı içerik barındırmamak</li>
                            <li>İzinsiz toplu elektronik ileti (spam) göndermemek</li>
                            <li>Üçüncü kişilerin telif ve fikrî mülkiyet haklarını ihlal etmemek</li>
                            <li>Paylaşımlı ortamlarda paketinde tanımlı kaynak sınırlarını aşmamak</li>
                            <li>Sağlayıcı'nın veya diğer müşterilerin sistemlerine yönelik saldırı,
                                tarama ve yetkisiz erişim girişiminde bulunmamak</li>
                            <li>Hizmeti, üzerinde çalıştırdığı yazılımların güvenliğini sağlayacak
                                şekilde güncel tutmak</li>
                        </ul>
                        <p>
                            Müşteri, hizmet üzerinde barındırdığı içerikten ve kendi kurduğu
                            yazılımlardan doğrudan sorumludur.
                        </p>
                    </section>

                    <section>
                        <h2 id="odeme"><span><?= ++$sira ?>.</span> <?= $maddeler['odeme'] ?></h2>
                        <p>
                            Hizmet bedelleri, seçilen fatura dönemine göre peşin olarak tahsil edilir.
                            Sitede gösterilen tutarlara aksi belirtilmedikçe KDV dâhil değildir; geçerli
                            KDV oranı sepet ve fatura ekranlarında ayrıca gösterilir.
                        </p>
                        <p>
                            Fatura, vade tarihine kadar ödenmezse hizmet askıya alınabilir. Askıya alma
                            öncesinde Müşteri'ye e-posta ile bildirim yapılır. Fiyat değişiklikleri
                            yalnızca yeni fatura dönemlerinde uygulanır; devam eden bir dönemin
                            bedeli sonradan değiştirilmez.
                        </p>
                    </section>

                    <section>
                        <h2 id="cayma"><span><?= ++$sira ?>.</span> <?= $maddeler['cayma'] ?></h2>
                        <p>
                            Tüketici sıfatını taşıyan Müşteri, mesafeli sözleşmelerde kural olarak
                            on dört gün içinde cayma hakkına sahiptir. Ancak elektronik ortamda anında
                            ifa edilen hizmetlerde ve Müşteri'nin onayıyla ifasına başlanan hizmetlerde
                            cayma hakkının kapsamı mevzuatla sınırlandırılmıştır.
                        </p>
                        <p>
                            Hangi hizmetlerde iade yapılabildiği, iade tutarının nasıl hesaplandığı ve
                            başvuru yolu <a href="iade-politikasi.php">İade Politikası</a> sayfasında ayrıntılı
                            olarak açıklanmıştır. Alan adı kaydı gibi üçüncü taraf nezdinde geri
                            alınamayan işlemlerin bedeli iade edilemez.
                        </p>
                    </section>

                    <section>
                        <h2 id="seviye"><span><?= ++$sira ?>.</span> <?= $maddeler['seviye'] ?></h2>
                        <p>
                            Erişilebilirlik oranları, bu oranın altına düşülmesi hâlinde uygulanacak
                            hizmet kredisi ve destek yanıt süreleri
                            <a href="sla.php">Hizmet Seviyesi Anlaşması</a> sayfasında düzenlenmiştir.
                            Söz konusu belge bu sözleşmenin ayrılmaz parçasıdır.
                        </p>
                    </section>

                    <section>
                        <h2 id="yedekleme"><span><?= ++$sira ?>.</span> <?= $maddeler['yedekleme'] ?></h2>
                        <p>
                            Sağlayıcı, altyapı düzeyinde yedekleme yapar; ancak bu yedekler felaket
                            kurtarma amaçlıdır ve Müşteri'nin kendi yedeğinin yerini tutmaz. Müşteri,
                            kendi verisinin düzenli yedeğini almakla yükümlüdür.
                        </p>
                        <p>
                            Kişisel verilerin işlenmesine ilişkin esaslar
                            <a href="kvkk.php">KVKK Aydınlatma Metni</a> ve
                            <a href="gizlilik-politikasi.php">Gizlilik Politikası</a> sayfalarında açıklanmıştır.
                        </p>
                    </section>

                    <section>
                        <h2 id="hesap"><span><?= ++$sira ?>.</span> <?= $maddeler['hesap'] ?></h2>
                        <p>
                            Panel erişim bilgilerinin gizliliğinden Müşteri sorumludur. Hesabın
                            yetkisiz kullanıldığından şüphelenilmesi hâlinde durum gecikmeksizin
                            Sağlayıcı'ya bildirilmelidir. Bildirim yapılana kadar hesap üzerinden
                            gerçekleştirilen işlemler Müşteri'ye ait sayılır.
                        </p>
                    </section>

                    <section>
                        <h2 id="askiya"><span><?= ++$sira ?>.</span> <?= $maddeler['askiya'] ?></h2>
                        <p>
                            Müşteri, hizmetini dilediği zaman yenilememe yoluyla sona erdirebilir;
                            dönem sonuna kadar hizmet çalışmaya devam eder.
                        </p>
                        <p>
                            Sağlayıcı, hizmeti sona erdirmek isterse en az otuz gün önceden yazılı
                            bildirim yapar ve kullanılmayan döneme ait bedeli iade eder. Mevzuata aykırı
                            kullanım, diğer müşterileri etkileyen kötüye kullanım veya güvenlik tehdidi
                            hâllerinde hizmet bildirim beklenmeksizin askıya alınabilir; bu durumda
                            gerekçe Müşteri'ye derhal bildirilir.
                        </p>
                        <p>
                            Sona eren hizmete ait veriler, aksi kararlaştırılmadıkça sona erme
                            tarihinden itibaren
                            <strong><?= max(0, (int) Settings::get('iade_veri_saklama_gun', 7)) ?> gün</strong>
                            boyunca saklanır ve bu süre içinde Müşteri'nin talebi üzerine teslim
                            edilir. Sürenin sonunda veriler kalıcı olarak silinir; ayrıntı
                            <a href="iade-politikasi.php">İade Politikası</a> sayfasındadır.
                        </p>
                    </section>

                    <section>
                        <h2 id="sorumluluk"><span><?= ++$sira ?>.</span> <?= $maddeler['sorumluluk'] ?></h2>
                        <p>
                            Sağlayıcı'nın sorumluluğu, ilgili hizmet için son on iki ayda ödenen bedelle
                            sınırlıdır. Sağlayıcı, dolaylı zararlardan, kâr kaybından ve üçüncü taraf
                            hizmetlerinden kaynaklanan kesintilerden sorumlu tutulamaz.
                        </p>
                        <p>
                            Bu sınırlama, Sağlayıcı'nın <strong>kastından veya ağır kusurundan</strong>
                            doğan zararlar ile mevzuat gereği sınırlandırılamayacak sorumluluk hâllerinde
                            uygulanmaz.
                        </p>
                    </section>

                    <section>
                        <h2 id="degisiklik"><span><?= ++$sira ?>.</span> <?= $maddeler['degisiklik'] ?></h2>
                        <p>
                            Sağlayıcı bu şartlarda değişiklik yapabilir. Müşteri aleyhine sonuç doğuran
                            değişiklikler, yürürlüğe girmesinden en az <strong>otuz gün önce</strong>
                            e-posta ve panel duyurusu ile bildirilir.
                        </p>
                        <p>
                            Müşteri değişikliği kabul etmezse, yürürlük tarihine kadar sözleşmeyi
                            bedelsiz olarak feshedebilir ve kullanılmayan döneme ait bedeli geri alır.
                            Bildirim yapılmadan yürürlüğe konulan değişiklikler Müşteri'yi bağlamaz.
                        </p>
                    </section>

                    <section>
                        <h2 id="uyusmazlik"><span><?= ++$sira ?>.</span> <?= $maddeler['uyusmazlik'] ?></h2>
                        <p>
                            Bu sözleşmeye Türkiye Cumhuriyeti hukuku uygulanır. Uyuşmazlık hâlinde
                            önce <a href="iletisim.php#iletisim-formu">iletişim kanallarımızdan</a>
                            çözüm aranması esastır.
                        </p>
                        <p>
                            Tüketici sıfatını taşıyan Müşteri, parasal sınırlar dâhilinde kendi
                            yerleşim yerindeki Tüketici Hakem Heyeti'ne, sınırın üzerindeki
                            uyuşmazlıklarda Tüketici Mahkemeleri'ne başvurabilir. Tüketici sayılmayan
                            Müşteriler bakımından Bursa mahkemeleri ve icra daireleri yetkilidir.
                        </p>
                    </section>

                </div>
            </div>
        </div>
    </section>

</div>

<script>
    /* İçindekiler: okunmakta olan maddeyi işaretler.
       Kaydırma olayı yerine IntersectionObserver kullanılır. */
    (function () {
        var toc = document.getElementById('ks-toc');
        if (!toc || !('IntersectionObserver' in window)) {
            return;
        }

        var baglantilar = Array.prototype.slice.call(toc.querySelectorAll('a'));
        var eslesme = {};
        var basliklar = [];

        baglantilar.forEach(function (a) {
            var id = a.getAttribute('href').slice(1);
            var baslik = document.getElementById(id);
            if (baslik) {
                eslesme[id] = a;
                basliklar.push(baslik);
            }
        });

        if (!basliklar.length) {
            return;
        }

        var gorunen = new Set();

        function isaretle() {
            var sirali = basliklar.filter(function (b) {
                return gorunen.has(b.id);
            });
            var etkin = sirali.length ? sirali[0].id : null;

            /* Hiçbiri görünmüyorsa en son geçilen başlık etkin sayılır */
            if (!etkin) {
                var ustte = basliklar.filter(function (b) {
                    return b.getBoundingClientRect().top < 120;
                });
                etkin = ustte.length
                    ? ustte[ustte.length - 1].id
                    : basliklar[0].id;   // sayfa başındayken ilk madde işaretli kalır
            }

            baglantilar.forEach(function (a) {
                a.classList.toggle('is-current', a.getAttribute('href') === '#' + etkin);
            });
        }

        var gozlemci = new IntersectionObserver(function (kayitlar) {
            kayitlar.forEach(function (k) {
                if (k.isIntersecting) {
                    gorunen.add(k.target.id);
                } else {
                    gorunen.delete(k.target.id);
                }
            });
            isaretle();
        }, { rootMargin: '-90px 0px -55% 0px' });

        basliklar.forEach(function (b) {
            gozlemci.observe(b);
        });

        isaretle();
    })();
</script>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>
