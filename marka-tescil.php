<?php
/**
 * VHM - Marka Tescil Sayfası
 *
 * Ücretsiz araştırma formu marka_tescil_talepleri tablosuna yazılır.
 * Hizmet bedeli ve Türk Patent harçları marka_ucretleri tablosundan gelir;
 * sayfaya sabit tutar yazılmaz. Tablo boşsa fiyat bölümü basılmaz.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Settings.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Marka Tescil';
$pageDescription = 'Ücretsiz marka ön araştırması ve Türk Patent ve Marka Kurumu nezdinde marka tescil başvurusu.';

/* ---------- Nice sınıfları: hem form hem kart listesi aynı kaynaktan ---------- */
$siniflar = [
    ['no' => 9, 'ad' => 'Yazılım & Elektronik', 'ikon' => 'fa-laptop-code', 'aciklama' => 'Bilgisayar yazılımları, mobil uygulamalar, elektronik cihazlar.'],
    ['no' => 35, 'ad' => 'Ticaret & Reklam', 'ikon' => 'fa-bullhorn', 'aciklama' => 'Reklamcılık, iş yönetimi, ticari işletme yönetimi.'],
    ['no' => 38, 'ad' => 'Telekomünikasyon', 'ikon' => 'fa-satellite-dish', 'aciklama' => 'Haberleşme hizmetleri, internet, veri iletimi.'],
    ['no' => 41, 'ad' => 'Eğitim & Eğlence', 'ikon' => 'fa-graduation-cap', 'aciklama' => 'Eğitim hizmetleri, spor ve kültürel faaliyetler.'],
    ['no' => 42, 'ad' => 'Bilimsel Hizmetler', 'ikon' => 'fa-flask', 'aciklama' => 'Teknolojik hizmetler, araştırma, yazılım tasarımı.'],
    ['no' => 43, 'ad' => 'Yiyecek & İçecek', 'ikon' => 'fa-utensils', 'aciklama' => 'Restoran, kafe, otel ve konaklama hizmetleri.'],
    ['no' => 25, 'ad' => 'Giyim & Tekstil', 'ikon' => 'fa-shirt', 'aciklama' => 'Giysiler, ayakkabılar, tekstil ürünleri.'],
    ['no' => 3, 'ad' => 'Kozmetik', 'ikon' => 'fa-spray-can', 'aciklama' => 'Parfümler, kozmetikler, temizlik maddeleri.'],
    ['no' => 30, 'ad' => 'Gıda Ürünleri', 'ikon' => 'fa-cookie-bite', 'aciklama' => 'Kahve, çay, şeker, unlu mamüller, şekerlemeler.'],
];
$gecerliSinif = array_column($siniflar, 'no');

/* ---------- Ücretler ---------- */
$ucretler = [];
try {
    foreach (Database::fetchAll("SELECT * FROM marka_ucretleri WHERE is_active = 1 ORDER BY sort_order") as $u) {
        $ucretler[$u['kod']] = $u;
    }
} catch (Throwable $e) {
    error_log('Marka ücretleri okunamadı: ' . $e->getMessage());
}

$tutar = static fn(string $kod): float => isset($ucretler[$kod]) ? (float) $ucretler[$kod]['tutar'] : 0.0;

$hizmetBedeli = $tutar('hizmet_bedeli');
$basvuruHarci = $tutar('basvuru_harci');
$ekSinifHarci = $tutar('ek_sinif_harci');

/* Özet ancak hizmet bedeli ve başvuru harcı biliniyorsa hesaplanır */
$ozetVar = $hizmetBedeli > 0 && $basvuruHarci > 0;

$harclar = array_filter($ucretler, static fn(array $u): bool => $u['tur'] === 'harc');
$gecerlilik = trim((string) Settings::get('marka_harc_gecerlilik', ''));

/* ---------- CSRF ---------- */
if (empty($_SESSION['marka_token'])) {
    $_SESSION['marka_token'] = bin2hex(random_bytes(32));
}

/* ---------- Form ---------- */
$hatalar = [];
$eski = ['ad_soyad' => '', 'telefon' => '', 'email' => '', 'marka_adi' => '', 'not_metni' => ''];
$secili = [];
$sonuc = '';
$sonucTipi = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kirp = static fn(string $alan, int $uzunluk): string =>
        mb_substr(trim((string) ($_POST[$alan] ?? '')), 0, $uzunluk);

    $eski = [
        'ad_soyad' => $kirp('ad_soyad', 120),
        'telefon' => $kirp('telefon', 30),
        'email' => $kirp('email', 150),
        'marka_adi' => $kirp('marka_adi', 150),
        'not_metni' => $kirp('not_metni', 1000),
    ];

    /* Sınıflar: yalnızca listedekiler kabul edilir */
    $gelen = (array) ($_POST['siniflar'] ?? []);
    $secili = array_values(array_intersect(array_map('intval', $gelen), $gecerliSinif));

    if (!hash_equals((string) $_SESSION['marka_token'], (string) ($_POST['token'] ?? ''))) {
        $hatalar['genel'] = 'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.';
    } elseif (trim((string) ($_POST['website'] ?? '')) !== '') {
        /* Bot tuzağı */
        $_SESSION['marka_sonuc'] = ['tip' => 'success', 'metin' => 'Talebiniz alındı. En kısa sürede dönüş yapacağız.'];
        header('Location: marka-tescil.php#arastirma');
        exit;
    } elseif (!empty($_SESSION['marka_son']) && (time() - (int) $_SESSION['marka_son']) < 30) {
        $hatalar['genel'] = 'Az önce bir talep gönderdiniz. Lütfen yarım dakika bekleyin.';
    }

    if (!$hatalar) {
        if (mb_strlen($eski['ad_soyad']) < 3) {
            $hatalar['ad_soyad'] = 'Ad soyad en az 3 karakter olmalı.';
        }
        $rakam = preg_replace('/\D+/', '', $eski['telefon']) ?? '';
        if (strlen($rakam) < 10 || strlen($rakam) > 11) {
            $hatalar['telefon'] = 'Telefonu 10 haneli yazın (örn. 532 123 45 67).';
        }
        if ($eski['email'] !== '' && !filter_var($eski['email'], FILTER_VALIDATE_EMAIL)) {
            $hatalar['email'] = 'E-posta adresi geçerli görünmüyor.';
        }
        if (mb_strlen($eski['marka_adi']) < 2) {
            $hatalar['marka_adi'] = 'Araştırılacak marka adını yazın.';
        }
        if (!$secili) {
            $hatalar['siniflar'] = 'En az bir sınıf seçin.';
        }
    }

    if (!$hatalar) {
        $kaydedildi = false;
        try {
            Database::insert('marka_tescil_talepleri', [
                'ad_soyad' => $eski['ad_soyad'],
                'telefon' => $eski['telefon'],
                'email' => $eski['email'] !== '' ? $eski['email'] : null,
                'marka_adi' => $eski['marka_adi'],
                'siniflar' => implode(',', $secili),
                'not_metni' => $eski['not_metni'] !== '' ? $eski['not_metni'] : null,
                'ip' => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
            $kaydedildi = true;
        } catch (Throwable $e) {
            error_log('Marka tescil talebi kaydedilemedi: ' . $e->getMessage());
        }

        /* Bildirim - gönderilemezse kayıt yine de durur */
        $bildirimAdresi = trim((string) Settings::get('company_email', ''));
        if ($kaydedildi && $bildirimAdresi !== '') {
            try {
                require_once __DIR__ . '/includes/Mail.php';
                $govde = '<p><strong>Marka:</strong> ' . htmlspecialchars($eski['marka_adi']) . '</p>'
                    . '<p><strong>Sınıflar:</strong> ' . htmlspecialchars(implode(', ', $secili)) . '</p>'
                    . '<p><strong>Ad soyad:</strong> ' . htmlspecialchars($eski['ad_soyad']) . '</p>'
                    . '<p><strong>Telefon:</strong> ' . htmlspecialchars($eski['telefon']) . '</p>'
                    . ($eski['email'] !== '' ? '<p><strong>E-posta:</strong> ' . htmlspecialchars($eski['email']) . '</p>' : '')
                    . ($eski['not_metni'] !== '' ? '<hr><p>' . nl2br(htmlspecialchars($eski['not_metni'])) . '</p>' : '');
                Mail::send($bildirimAdresi, 'Marka araştırma talebi: ' . $eski['marka_adi'], $govde);
            } catch (Throwable $e) {
                error_log('Marka tescil bildirimi gönderilemedi: ' . $e->getMessage());
            }
        }

        $_SESSION['marka_son'] = time();
        $_SESSION['marka_sonuc'] = $kaydedildi
            ? ['tip' => 'success', 'metin' => 'Araştırma talebiniz bize ulaştı. Uzmanımız en kısa sürede dönüş yapacak.']
            : ['tip' => 'error', 'metin' => 'Talep kaydedilemedi. Lütfen telefonla ulaşın.'];

        header('Location: marka-tescil.php#arastirma');
        exit;
    }

    $sonuc = $hatalar['genel'] ?? 'Formda düzeltilmesi gereken alanlar var.';
    $sonucTipi = 'error';
}

if (!empty($_SESSION['marka_sonuc'])) {
    $sonuc = (string) $_SESSION['marka_sonuc']['metin'];
    $sonucTipi = (string) $_SESSION['marka_sonuc']['tip'];
    unset($_SESSION['marka_sonuc']);
}

/* Süreç adımları ve avantajlar */
$adimlar = [
    ['Ön araştırma', 'Marka adınız Türk Patent veri tabanında taranır, benzer kayıtlar raporlanır.'],
    ['Başvuru', 'Seçilen sınıflarla başvuru Türk Patent ve Marka Kurumu\'na iletilir.'],
    ['İnceleme', 'Kurum mutlak ret nedenleri yönünden başvuruyu inceler.'],
    ['Bülten yayını', 'Başvuru Resmî Marka Bülteni\'nde yayımlanır; iki ay itiraz süresi işler.'],
    ['Tescil belgesi', 'İtiraz gelmezse tescil harcı yatırılır ve belge düzenlenir.'],
];

$avantajlar = [
    ['fa-shield-halved', 'Hukuki koruma', 'Tescilli marka, izinsiz kullanıma karşı yasal dayanak sağlar.'],
    ['fa-globe', '.com.tr alan adı', 'Tescil belgesi, .com.tr uzantılı alan adı başvurusunda belge yerine geçer.'],
    ['fa-ban', 'Taklide karşı işlem', 'Benzer başvurulara itiraz edebilir, taklit ürünlere işlem başlatabilirsiniz.'],
    ['fa-right-left', 'Devir ve lisans', 'Markanızı devredebilir, lisans vererek gelir elde edebilirsiniz.'],
    ['fa-earth-americas', 'Uluslararası başvuru', 'Madrid Protokolü ile yurt dışı tesciline temel oluşturur.'],
    ['fa-calendar-check', '10 yıl koruma', 'Koruma süresi on yıldır ve süresiz olarak yenilenebilir.'],
];

require_once __DIR__ . '/theme/includes/header.php';
?>

<style>
    /* ==========================================
       Marka Tescil - mt
       Tüm renkler tasarım değişkenlerinden gelir;
       sayfa açık ve koyu temada aynı dili konuşur.
       ========================================== */
    .mt {
        --mt-line: var(--border-color);
        --mt-surface: var(--bg-primary);
        --mt-accent: var(--primary);
        --mt-radius: 10px;
    }

    .mt a {
        color: inherit;
    }

    /* ===== Üst bilgi: ortalanmış tek kolon ===== */
    .mt-hero {
        position: relative;
        overflow: hidden;
        background: var(--gradient-hero);
        border-bottom: 1px solid var(--mt-line);
        padding: var(--space-7) 0;
    }

    .mt-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 55% 50% at 15% 25%, color-mix(in srgb, var(--primary) 16%, transparent) 0%, transparent 62%),
            radial-gradient(ellipse 45% 45% at 85% 10%, color-mix(in srgb, var(--secondary) 12%, transparent) 0%, transparent 58%);
        pointer-events: none;
    }

    .mt-hero>.container {
        position: relative;
        z-index: 1;
    }

    .mt-hero-inner {
        max-width: 700px;
        margin: 0 auto;
        text-align: center;
    }

    .mt-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 50px;
        border: 1px solid var(--mt-line);
        background: var(--mt-surface);
        font-size: var(--text-xs);
        font-weight: 600;
        color: var(--text-secondary);
    }

    .mt-eyebrow i {
        color: var(--mt-accent);
    }

    .mt-title {
        margin: var(--space-3) 0 0;
        font-size: clamp(26px, 2vw + 18px, 38px);
        font-weight: 700;
        letter-spacing: -0.02em;
        line-height: 1.2;
        color: var(--text-primary);
    }

    .mt-lead {
        max-width: 580px;
        margin: var(--space-3) auto 0;
        font-size: var(--text-base);
        line-height: 1.65;
        color: var(--text-muted);
    }

    /* Doğrulanabilir üç madde - uydurma istatistik yok */
    .mt-points {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: var(--space-3) var(--space-5);
        margin-top: var(--space-4);
    }

    .mt-points span {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: var(--text-sm);
        color: var(--text-secondary);
    }

    .mt-points i {
        font-size: 11px;
        color: var(--mt-accent);
    }

    /* ===== Bölümler ===== */
    .mt-section {
        padding: var(--space-7) 0;
    }

    .mt-section.is-alt {
        background: var(--mt-surface);
        border-top: 1px solid var(--mt-line);
        border-bottom: 1px solid var(--mt-line);
    }

    .mt-head {
        max-width: 640px;
        margin-bottom: var(--space-5);
    }

    .mt-head h2 {
        font-size: clamp(20px, 1vw + 15px, 25px);
        font-weight: 700;
        letter-spacing: -0.02em;
        line-height: 1.3;
        color: var(--text-primary);
    }

    .mt-head p {
        margin-top: 6px;
        font-size: var(--text-sm);
        line-height: 1.6;
        color: var(--text-muted);
    }

    /* ===== Araştırma formu + özet ===== */
    .mt-apply {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(0, 0.65fr);
        gap: var(--space-5);
        align-items: start;
    }

    .mt-card {
        border: 1px solid var(--mt-line);
        border-radius: var(--mt-radius);
        background: var(--mt-surface);
        overflow: hidden;
    }

    .mt-card-head {
        padding: var(--space-3) var(--space-5);
        border-bottom: 1px solid var(--mt-line);
        background: color-mix(in srgb, var(--primary) 5%, transparent);
    }

    .mt-card-head h3 {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-primary);
    }

    .mt-card-head p {
        margin-top: 4px;
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    .mt-card-body {
        padding: var(--space-5);
    }

    .mt-alert {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: var(--space-4);
        padding: 11px 14px;
        border-radius: 8px;
        font-size: var(--text-sm);
        line-height: 1.5;
        color: var(--text-primary);
    }

    .mt-alert.is-success {
        border: 1px solid color-mix(in srgb, var(--success) 40%, transparent);
        background: color-mix(in srgb, var(--success) 10%, transparent);
    }

    .mt-alert.is-error {
        border: 1px solid color-mix(in srgb, var(--danger) 40%, transparent);
        background: color-mix(in srgb, var(--danger) 10%, transparent);
    }

    .mt-alert.is-success i {
        color: var(--success);
    }

    .mt-alert.is-error i {
        color: var(--danger);
    }

    .mt-alert i {
        margin-top: 2px;
    }

    .mt-row {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--space-4);
    }

    .mt-field {
        margin-bottom: var(--space-4);
    }

    .mt-field label {
        display: block;
        margin-bottom: 6px;
        font-size: var(--text-sm);
        font-weight: 600;
        color: var(--text-secondary);
    }

    .mt-field label span {
        font-weight: 400;
        color: var(--text-muted);
    }

    .mt-field input,
    .mt-field textarea {
        width: 100%;
        padding: 10px 13px;
        border: 1px solid var(--mt-line);
        border-radius: 8px;
        background: var(--bg-body);
        color: var(--text-primary);
        font-family: inherit;
        font-size: var(--text-sm);
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .mt-field textarea {
        min-height: 90px;
        resize: vertical;
        line-height: 1.6;
    }

    .mt-field input:focus,
    .mt-field textarea:focus {
        outline: none;
        border-color: var(--mt-accent);
        box-shadow: var(--focus-ring);
    }

    .mt-field.has-error input,
    .mt-field.has-error textarea {
        border-color: var(--danger);
    }

    .mt-err {
        display: block;
        margin-top: 5px;
        font-size: var(--text-xs);
        color: var(--danger);
    }

    /* Sınıf seçimi */
    .mt-classes {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 9px;
    }

    .mt-class {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 9px 12px;
        border: 1px solid var(--mt-line);
        border-radius: 8px;
        font-size: var(--text-sm);
        color: var(--text-secondary);
        cursor: pointer;
        transition: border-color 0.15s ease, background-color 0.15s ease;
    }

    .mt-class:hover {
        border-color: var(--mt-accent);
    }

    .mt-class input {
        accent-color: var(--mt-accent);
        flex-shrink: 0;
    }

    .mt-class:has(input:checked) {
        border-color: var(--mt-accent);
        background: color-mix(in srgb, var(--primary) 8%, transparent);
        color: var(--text-primary);
    }

    .mt-class b {
        font-weight: 700;
        color: var(--mt-accent);
    }

    /* Bot tuzağı */
    .mt-trap {
        position: absolute;
        left: -9999px;
        width: 1px;
        height: 1px;
        overflow: hidden;
    }

    .mt-submit {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        width: 100%;
        margin-top: var(--space-4);
        padding: 13px 24px;
        border: none;
        border-radius: 8px;
        background: var(--mt-accent);
        color: #fff;
        font-family: inherit;
        font-size: var(--text-base);
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.15s ease;
    }

    .mt-submit:hover {
        background: var(--primary-dark);
    }

    .mt-note {
        margin-top: var(--space-3);
        font-size: var(--text-xs);
        line-height: 1.5;
        color: var(--text-muted);
    }

    .mt-note a {
        color: var(--mt-accent);
    }

    /* Özet */
    .mt-sum-row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: var(--space-3);
        padding: 10px 0;
        border-bottom: 1px solid var(--border-light);
        font-size: var(--text-sm);
    }

    .mt-sum-row span {
        color: var(--text-muted);
    }

    .mt-sum-row b {
        font-weight: 600;
        color: var(--text-primary);
        white-space: nowrap;
    }

    .mt-sum-total {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: var(--space-3);
        margin-top: var(--space-4);
        padding: var(--space-3) var(--space-4);
        border-radius: 8px;
        background: color-mix(in srgb, var(--primary) 7%, transparent);
    }

    .mt-sum-total span {
        font-size: var(--text-sm);
        font-weight: 600;
        color: var(--text-primary);
    }

    .mt-sum-total b {
        font-size: 23px;
        font-weight: 700;
        color: var(--mt-accent);
    }

    /* ===== Sınıf kartları ===== */
    .mt-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: var(--space-4);
    }

    .mt-tile {
        padding: var(--space-4);
        border: 1px solid var(--mt-line);
        border-radius: var(--mt-radius);
        background: var(--bg-body);
    }

    .mt-tile-top {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 9px;
    }

    .mt-tile-icon {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        border-radius: 8px;
        background: color-mix(in srgb, var(--primary) 10%, transparent);
        color: var(--mt-accent);
        font-size: 14px;
        flex-shrink: 0;
    }

    .mt-tile h4 {
        font-size: var(--text-sm);
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.3;
    }

    .mt-tile-no {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: var(--mt-accent);
    }

    .mt-tile p {
        font-size: var(--text-sm);
        line-height: 1.55;
        color: var(--text-muted);
    }

    /* ===== Ücret tablosu ===== */
    .mt-table-wrap {
        border: 1px solid var(--mt-line);
        border-radius: var(--mt-radius);
        background: var(--mt-surface);
        overflow-x: auto;
    }

    .mt-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 520px;
    }

    .mt-table th,
    .mt-table td {
        padding: 13px var(--space-5);
        text-align: left;
        font-size: var(--text-sm);
        border-bottom: 1px solid var(--border-light);
    }

    .mt-table thead th {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-muted);
        border-bottom: 1px solid var(--mt-line);
        background: color-mix(in srgb, var(--primary) 5%, transparent);
    }

    .mt-table tbody th {
        font-weight: 600;
        color: var(--text-primary);
    }

    .mt-table td {
        color: var(--text-muted);
    }

    .mt-table td.mt-amount {
        text-align: right;
        font-weight: 700;
        color: var(--text-primary);
        white-space: nowrap;
    }

    .mt-table tr:last-child th,
    .mt-table tr:last-child td {
        border-bottom: none;
    }

    /* ===== Süreç ===== */
    .mt-steps {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: var(--space-4);
    }

    .mt-step {
        padding: var(--space-4);
        border: 1px solid var(--mt-line);
        border-radius: var(--mt-radius);
        background: var(--bg-body);
    }

    .mt-step-no {
        width: 26px;
        height: 26px;
        display: grid;
        place-items: center;
        margin-bottom: 10px;
        border-radius: 50%;
        background: var(--mt-accent);
        color: #fff;
        font-size: 12px;
        font-weight: 700;
    }

    .mt-step h4 {
        margin-bottom: 6px;
        font-size: var(--text-sm);
        font-weight: 700;
        color: var(--text-primary);
    }

    .mt-step p {
        font-size: var(--text-xs);
        line-height: 1.55;
        color: var(--text-muted);
    }

    /* ==========================================
       Açık tema: zemin soğuk maviye çekilir,
       kartlar beyaz kalıp zeminden ayrışır.
       ========================================== */
    [data-theme="light"] .mt {
        --mt-line: #d3e2f8;
        background: #eff5fe;
    }

    [data-theme="light"] .mt-hero {
        background: linear-gradient(180deg, #e4edfb 0%, #eff5fe 100%);
    }

    [data-theme="light"] .mt-section.is-alt {
        background: #e4edfb;
    }

    [data-theme="light"] .mt-card,
    [data-theme="light"] .mt-table-wrap {
        box-shadow:
            0 1px 2px rgba(36, 116, 245, 0.05),
            0 10px 26px -14px rgba(36, 116, 245, 0.28);
    }

    [data-theme="light"] .mt-tile,
    [data-theme="light"] .mt-step,
    [data-theme="light"] .mt-field input,
    [data-theme="light"] .mt-field textarea {
        background: #fff;
    }

    @media (max-width: 1000px) {
        .mt-apply {
            grid-template-columns: 1fr;
        }

        .mt-steps {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 860px) {
        .mt-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .mt-classes {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {

        .mt-row,
        .mt-grid,
        .mt-classes {
            grid-template-columns: 1fr;
        }

        .mt-steps {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="mt">

    <section class="mt-hero">
        <div class="container">
            <div class="mt-hero-inner">
                <span class="mt-eyebrow"><i class="fas fa-trademark"></i> Türk Patent ve Marka Kurumu başvurusu</span>
                <h1 class="mt-title">Markanızı koruma altına alın</h1>
                <p class="mt-lead">
                    İşletmenizin mal ve hizmetlerini diğerlerinden ayıran her işaret marka olarak tescil
                    edilebilir. Önce ücretsiz ön araştırma yapıyor, sonuca göre başvuruyu biz yürütüyoruz.
                </p>
                <div class="mt-points">
                    <span><i class="fas fa-check"></i> Ücretsiz ön araştırma</span>
                    <span><i class="fas fa-check"></i> 10 yıl koruma, süresiz yenileme</span>
                    <span><i class="fas fa-check"></i> .com.tr alan adı hakkı</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Araştırma formu -->
    <section class="mt-section" id="arastirma">
        <div class="container">
            <div class="mt-apply">

                <div class="mt-card">
                    <div class="mt-card-head">
                        <h3>Ücretsiz marka araştırması</h3>
                        <p>Marka adınızı ve ilgilendiğiniz sınıfları yazın; uzmanımız Türk Patent kayıtlarını tarayıp
                            dönüş yapsın.</p>
                    </div>
                    <div class="mt-card-body">
                        <?php if ($sonuc !== ''): ?>
                            <div class="mt-alert <?= $sonucTipi === 'success' ? 'is-success' : 'is-error' ?>">
                                <i class="fas fa-<?= $sonucTipi === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
                                <span><?= htmlspecialchars($sonuc) ?></span>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="marka-tescil.php#arastirma" novalidate id="mt-form">
                            <input type="hidden" name="token"
                                value="<?= htmlspecialchars($_SESSION['marka_token']) ?>">

                            <div class="mt-trap" aria-hidden="true">
                                <label for="website">Web siteniz</label>
                                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                            </div>

                            <div class="mt-row">
                                <div class="mt-field <?= isset($hatalar['ad_soyad']) ? 'has-error' : '' ?>">
                                    <label for="ad_soyad">Ad soyad *</label>
                                    <input type="text" id="ad_soyad" name="ad_soyad" required maxlength="120"
                                        autocomplete="name" value="<?= htmlspecialchars($eski['ad_soyad']) ?>">
                                    <?php if (isset($hatalar['ad_soyad'])): ?>
                                        <span class="mt-err"><?= htmlspecialchars($hatalar['ad_soyad']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-field <?= isset($hatalar['telefon']) ? 'has-error' : '' ?>">
                                    <label for="telefon">Telefon *</label>
                                    <input type="tel" id="telefon" name="telefon" required maxlength="30"
                                        autocomplete="tel" dir="ltr" placeholder="532 123 45 67"
                                        value="<?= htmlspecialchars($eski['telefon']) ?>">
                                    <?php if (isset($hatalar['telefon'])): ?>
                                        <span class="mt-err"><?= htmlspecialchars($hatalar['telefon']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mt-row">
                                <div class="mt-field <?= isset($hatalar['email']) ? 'has-error' : '' ?>">
                                    <label for="email">E-posta <span>(isteğe bağlı)</span></label>
                                    <input type="email" id="email" name="email" maxlength="150" autocomplete="email"
                                        dir="ltr" value="<?= htmlspecialchars($eski['email']) ?>">
                                    <?php if (isset($hatalar['email'])): ?>
                                        <span class="mt-err"><?= htmlspecialchars($hatalar['email']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-field <?= isset($hatalar['marka_adi']) ? 'has-error' : '' ?>">
                                    <label for="marka_adi">Marka adı *</label>
                                    <input type="text" id="marka_adi" name="marka_adi" required maxlength="150"
                                        placeholder="Tescil edilecek marka"
                                        value="<?= htmlspecialchars($eski['marka_adi']) ?>">
                                    <?php if (isset($hatalar['marka_adi'])): ?>
                                        <span class="mt-err"><?= htmlspecialchars($hatalar['marka_adi']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mt-field <?= isset($hatalar['siniflar']) ? 'has-error' : '' ?>">
                                <label>Marka sınıfı * <span>(birden fazla seçebilirsiniz)</span></label>
                                <div class="mt-classes">
                                    <?php foreach ($siniflar as $s): ?>
                                        <label class="mt-class">
                                            <input type="checkbox" name="siniflar[]" value="<?= (int) $s['no'] ?>"
                                                <?= in_array($s['no'], $secili, true) ? 'checked' : '' ?>>
                                            <span><b><?= (int) $s['no'] ?></b> · <?= htmlspecialchars($s['ad']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (isset($hatalar['siniflar'])): ?>
                                    <span class="mt-err"><?= htmlspecialchars($hatalar['siniflar']) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="mt-field">
                                <label for="not_metni">Eklemek istedikleriniz <span>(isteğe bağlı)</span></label>
                                <textarea id="not_metni" name="not_metni"
                                    maxlength="1000"><?= htmlspecialchars($eski['not_metni']) ?></textarea>
                            </div>

                            <button type="submit" class="mt-submit">
                                <i class="fas fa-magnifying-glass"></i> Ücretsiz araştırma isteyin
                            </button>

                            <p class="mt-note">
                                Bilgileriniz yalnızca araştırma talebinizi yanıtlamak için kullanılır.
                                Ayrıntı için <a href="kvkk.php">KVKK Aydınlatma Metni</a>.
                            </p>
                        </form>
                    </div>
                </div>

                <!-- Özet: ücretler tabloda tanımlıysa hesaplanır -->
                <?php if ($ozetVar): ?>
                    <div class="mt-card" id="mt-ozet" data-hizmet="<?= (int) $hizmetBedeli ?>"
                        data-basvuru="<?= (int) $basvuruHarci ?>" data-eksinif="<?= (int) $ekSinifHarci ?>">
                        <div class="mt-card-head">
                            <h3>Başvuru özeti</h3>
                            <p>Seçtiğiniz sınıf sayısına göre güncellenir.</p>
                        </div>
                        <div class="mt-card-body">
                            <div class="mt-sum-row">
                                <span><?= htmlspecialchars($ucretler['hizmet_bedeli']['baslik']) ?></span>
                                <b>₺<?= number_format($hizmetBedeli, 0, ',', '.') ?></b>
                            </div>
                            <div class="mt-sum-row">
                                <span>Başvuru harcı (1 sınıf)</span>
                                <b>₺<?= number_format($basvuruHarci, 0, ',', '.') ?></b>
                            </div>
                            <?php if ($ekSinifHarci > 0): ?>
                                <div class="mt-sum-row">
                                    <span>Ek sınıf (<b id="mt-ek-adet">0</b> adet)</span>
                                    <b id="mt-ek-tutar">₺0</b>
                                </div>
                            <?php endif; ?>

                            <div class="mt-sum-total">
                                <span>Toplam</span>
                                <b id="mt-toplam">₺<?= number_format($hizmetBedeli + $basvuruHarci, 0, ',', '.') ?></b>
                            </div>

                            <p class="mt-note">
                                Tutarlara KDV dâhil değildir. Harçlar Türk Patent ve Marka Kurumu tarafından
                                belirlenir<?= $gecerlilik !== '' ? ' (' . htmlspecialchars($gecerlilik) . ' tarifesi)' : '' ?>.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </section>

    <!-- Sınıflar -->
    <section class="mt-section is-alt">
        <div class="container">
            <div class="mt-head">
                <h2>Popüler marka sınıfları</h2>
                <p>Marka tescili, seçtiğiniz sınıflardaki mal ve hizmetler için koruma sağlar. Aşağıdakiler en sık
                    başvurulan sınıflardır; tam liste 45 sınıftan oluşur.</p>
            </div>

            <div class="mt-grid">
                <?php foreach ($siniflar as $s): ?>
                    <div class="mt-tile">
                        <div class="mt-tile-top">
                            <div class="mt-tile-icon"><i class="fas <?= htmlspecialchars($s['ikon']) ?>"></i></div>
                            <h4>
                                <span class="mt-tile-no">Sınıf <?= (int) $s['no'] ?></span>
                                <?= htmlspecialchars($s['ad']) ?>
                            </h4>
                        </div>
                        <p><?= htmlspecialchars($s['aciklama']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Ücretler: tablo boşsa bölüm hiç basılmaz -->
    <?php if ($harclar): ?>
        <section class="mt-section">
            <div class="container">
                <div class="mt-head">
                    <h2>Türk Patent harçları</h2>
                    <p>
                        Aşağıdaki tutarlar Türk Patent ve Marka Kurumu'nun
                        <?= $gecerlilik !== '' ? htmlspecialchars($gecerlilik) . ' yılı ' : '' ?>tarifesine göredir ve
                        hizmet bedelimizden ayrıdır. Güncel tarife için
                        <a href="https://www.turkpatent.gov.tr/ucret-tarifesi" target="_blank"
                            rel="noopener noreferrer">Türk Patent ücret tarifesi</a>ne bakabilirsiniz.
                    </p>
                </div>

                <div class="mt-table-wrap">
                    <table class="mt-table">
                        <thead>
                            <tr>
                                <th>İşlem</th>
                                <th>Açıklama</th>
                                <th style="text-align: right;">Tutar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($harclar as $h): ?>
                                <tr>
                                    <th><?= htmlspecialchars((string) $h['baslik']) ?></th>
                                    <td><?= htmlspecialchars((string) ($h['aciklama'] ?? '')) ?></td>
                                    <td class="mt-amount">₺<?= number_format((float) $h['tutar'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Avantajlar -->
    <section class="mt-section is-alt">
        <div class="container">
            <div class="mt-head">
                <h2>Tescil size ne kazandırır</h2>
                <p>Tescilsiz kullanım da mümkündür; ancak aşağıdaki hakların tamamı yalnızca tescille doğar.</p>
            </div>

            <div class="mt-grid">
                <?php foreach ($avantajlar as [$ikon, $baslik, $metin]): ?>
                    <div class="mt-tile">
                        <div class="mt-tile-top">
                            <div class="mt-tile-icon"><i class="fas <?= htmlspecialchars($ikon) ?>"></i></div>
                            <h4><?= htmlspecialchars($baslik) ?></h4>
                        </div>
                        <p><?= htmlspecialchars($metin) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Süreç -->
    <section class="mt-section">
        <div class="container">
            <div class="mt-head">
                <h2>Tescil süreci</h2>
                <p>Başvurudan belgeye kadar izlenen resmî adımlar. Süre, itiraz olup olmamasına göre değişir.</p>
            </div>

            <div class="mt-steps">
                <?php foreach ($adimlar as $i => [$baslik, $metin]): ?>
                    <div class="mt-step">
                        <div class="mt-step-no"><?= $i + 1 ?></div>
                        <h4><?= htmlspecialchars($baslik) ?></h4>
                        <p><?= htmlspecialchars($metin) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

</div>

<script>
    /* Başvuru özeti: seçilen sınıf sayısına göre toplamı günceller.
       Sunucu tarafı zaten kendi hesabını yapar; bu yalnızca gösterimdir. */
    (function () {
        var ozet = document.getElementById('mt-ozet');
        var form = document.getElementById('mt-form');
        if (!ozet || !form) {
            return;
        }

        var hizmet = parseInt(ozet.dataset.hizmet, 10) || 0;
        var basvuru = parseInt(ozet.dataset.basvuru, 10) || 0;
        var ekBirim = parseInt(ozet.dataset.eksinif, 10) || 0;

        var ekAdetEl = document.getElementById('mt-ek-adet');
        var ekTutarEl = document.getElementById('mt-ek-tutar');
        var toplamEl = document.getElementById('mt-toplam');

        function bicim(sayi) {
            return '₺' + sayi.toLocaleString('tr-TR');
        }

        function hesapla() {
            var secili = form.querySelectorAll('input[name="siniflar[]"]:checked').length;
            var ek = Math.max(0, secili - 1);

            if (ekAdetEl) {
                ekAdetEl.textContent = String(ek);
            }
            if (ekTutarEl) {
                ekTutarEl.textContent = bicim(ek * ekBirim);
            }
            toplamEl.textContent = bicim(hizmet + basvuru + ek * ekBirim);
        }

        form.addEventListener('change', function (olay) {
            if (olay.target.name === 'siniflar[]') {
                hesapla();
            }
        });

        hesapla();
    })();
</script>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>
