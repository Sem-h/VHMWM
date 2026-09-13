<?php
/**
 * VHM - İletişim Sayfası
 *
 * Adres, telefon ve e-posta sayfaya sabit yazılmaz; hepsi ayarlardan gelir.
 * Form gönderimi iletisim_mesajlari tablosuna yazılır, ardından şirket
 * adresine bildirim denenir. E-posta gönderilemese bile kayıt durur.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Settings.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'İletişim';
$pageDescription = 'Bize ulaşın. Sorularınız, teklif talepleriniz ve teknik destek için iletişim kanallarımız.';

/* ---------- Ayarlardan gelen iletişim bilgileri ---------- */
$adres = trim((string) Settings::get('company_address', ''));
$telefon = trim((string) Settings::get('company_phone', ''));
$eposta = trim((string) Settings::get('company_email', ''));

/** Telefonu tel: bağlantısına uygun hale getirir */
$telBaglanti = static function (string $ham): string {
    $rakam = preg_replace('/\D+/', '', $ham) ?? '';
    if ($rakam === '') {
        return '';
    }
    if (str_starts_with($rakam, '90')) {
        return '+' . $rakam;
    }
    return '+90' . ltrim($rakam, '0');
};

/* Sosyal hesaplar: yalnızca ayarda değeri olanlar basılır, ölü bağlantı kalmaz */
$sosyalTanim = [
    'social_facebook' => ['Facebook', 'fab fa-facebook-f'],
    'social_x' => ['X', 'fab fa-x-twitter'],
    'social_instagram' => ['Instagram', 'fab fa-instagram'],
    'social_linkedin' => ['LinkedIn', 'fab fa-linkedin-in'],
    'social_youtube' => ['YouTube', 'fab fa-youtube'],
];
$sosyal = [];
foreach ($sosyalTanim as $anahtar => [$ad, $ikon]) {
    $url = trim((string) Settings::get($anahtar, ''));
    if ($url !== '') {
        $sosyal[] = ['ad' => $ad, 'ikon' => $ikon, 'url' => $url];
    }
}

/* ---------- CSRF ---------- */
if (empty($_SESSION['iletisim_token'])) {
    $_SESSION['iletisim_token'] = bin2hex(random_bytes(32));
}

/* ---------- Form gönderimi ---------- */
$hatalar = [];
$eski = ['ad_soyad' => '', 'email' => '', 'telefon' => '', 'konu' => '', 'mesaj' => ''];
$sonuc = '';
$sonucTipi = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kirp = static fn(string $alan, int $uzunluk): string =>
        mb_substr(trim((string) ($_POST[$alan] ?? '')), 0, $uzunluk);

    $eski = [
        'ad_soyad' => $kirp('ad_soyad', 120),
        'email' => $kirp('email', 150),
        'telefon' => $kirp('telefon', 30),
        'konu' => $kirp('konu', 180),
        'mesaj' => $kirp('mesaj', 4000),
    ];

    if (!hash_equals((string) $_SESSION['iletisim_token'], (string) ($_POST['token'] ?? ''))) {
        $hatalar['genel'] = 'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.';
    } elseif (trim((string) ($_POST['website'] ?? '')) !== '') {
        /* Bot tuzağı: gizli alan doluysa sessizce başarılı gibi davran */
        $_SESSION['iletisim_sonuc'] = ['tip' => 'success', 'metin' => 'Mesajınız alındı. En kısa sürede dönüş yapacağız.'];
        header('Location: iletisim.php#iletisim-formu');
        exit;
    } elseif (!empty($_SESSION['iletisim_son']) && (time() - (int) $_SESSION['iletisim_son']) < 30) {
        $hatalar['genel'] = 'Az önce bir mesaj gönderdiniz. Lütfen yarım dakika bekleyin.';
    }

    if (!$hatalar) {
        if (mb_strlen($eski['ad_soyad']) < 3) {
            $hatalar['ad_soyad'] = 'Ad soyad en az 3 karakter olmalı.';
        }
        if (!filter_var($eski['email'], FILTER_VALIDATE_EMAIL)) {
            $hatalar['email'] = 'Geçerli bir e-posta adresi yazın.';
        }
        if ($eski['telefon'] !== '') {
            $rakam = preg_replace('/\D+/', '', $eski['telefon']) ?? '';
            if (strlen($rakam) < 10 || strlen($rakam) > 11) {
                $hatalar['telefon'] = 'Telefonu 10 haneli yazın (örn. 532 123 45 67).';
            }
        }
        if (mb_strlen($eski['konu']) < 3) {
            $hatalar['konu'] = 'Konu en az 3 karakter olmalı.';
        }
        if (mb_strlen($eski['mesaj']) < 10) {
            $hatalar['mesaj'] = 'Mesaj en az 10 karakter olmalı.';
        }
    }

    if (!$hatalar) {
        $kaydedildi = false;
        try {
            Database::insert('iletisim_mesajlari', [
                'ad_soyad' => $eski['ad_soyad'],
                'email' => $eski['email'],
                'telefon' => $eski['telefon'] !== '' ? $eski['telefon'] : null,
                'konu' => $eski['konu'],
                'mesaj' => $eski['mesaj'],
                'ip' => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
            $kaydedildi = true;
        } catch (Throwable $e) {
            error_log('İletişim mesajı kaydedilemedi: ' . $e->getMessage());
        }

        /* Bildirim e-postası. Gönderilemezse kayıt yine de durur. */
        if ($kaydedildi && $eposta !== '') {
            try {
                require_once __DIR__ . '/includes/Mail.php';
                $govde = '<p><strong>Ad soyad:</strong> ' . htmlspecialchars($eski['ad_soyad']) . '</p>'
                    . '<p><strong>E-posta:</strong> ' . htmlspecialchars($eski['email']) . '</p>'
                    . ($eski['telefon'] !== '' ? '<p><strong>Telefon:</strong> ' . htmlspecialchars($eski['telefon']) . '</p>' : '')
                    . '<p><strong>Konu:</strong> ' . htmlspecialchars($eski['konu']) . '</p>'
                    . '<hr><p>' . nl2br(htmlspecialchars($eski['mesaj'])) . '</p>';
                Mail::send($eposta, 'İletişim formu: ' . $eski['konu'], $govde);
            } catch (Throwable $e) {
                error_log('İletişim bildirimi gönderilemedi: ' . $e->getMessage());
            }
        }

        $_SESSION['iletisim_son'] = time();
        $_SESSION['iletisim_sonuc'] = $kaydedildi
            ? ['tip' => 'success', 'metin' => 'Mesajınız bize ulaştı. En kısa sürede dönüş yapacağız.']
            : ['tip' => 'error', 'metin' => 'Mesaj kaydedilemedi. Lütfen telefonla ulaşın.'];

        /* PRG: yenilemede form tekrar gönderilmesin */
        header('Location: iletisim.php#iletisim-formu');
        exit;
    }

    $sonuc = $hatalar['genel'] ?? 'Formda düzeltilmesi gereken alanlar var.';
    $sonucTipi = 'error';
}

/* Yönlendirmeden dönen sonuç */
if (!empty($_SESSION['iletisim_sonuc'])) {
    $sonuc = (string) $_SESSION['iletisim_sonuc']['metin'];
    $sonucTipi = (string) $_SESSION['iletisim_sonuc']['tip'];
    unset($_SESSION['iletisim_sonuc']);
}

require_once __DIR__ . '/theme/includes/header.php';
?>

<style>
    /* ==========================================
       İletişim - cn
       Ölçülü yüzeyler, net kenarlıklar. Bilgi kartları
       solda, form sağda; ikisi de aynı kart dilini kullanır.
       ========================================== */
    .cn {
        --cn-line: var(--border-color);
        --cn-surface: var(--bg-primary);
        --cn-accent: var(--primary);
        --cn-radius: 10px;
    }

    .cn a {
        color: inherit;
    }

    /* ===== Üst bilgi: ortalanmış tek kolon ===== */
    .cn-hero {
        position: relative;
        overflow: hidden;
        background: var(--gradient-hero);
        border-bottom: 1px solid var(--cn-line);
        padding: var(--space-7) 0;
    }

    .cn-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 55% 50% at 15% 25%, color-mix(in srgb, var(--primary) 16%, transparent) 0%, transparent 62%),
            radial-gradient(ellipse 45% 45% at 85% 10%, color-mix(in srgb, var(--secondary) 12%, transparent) 0%, transparent 58%);
        pointer-events: none;
    }

    .cn-hero>.container {
        position: relative;
        z-index: 1;
    }

    .cn-hero-inner {
        max-width: 700px;
        margin: 0 auto;
        text-align: center;
    }

    .cn-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 50px;
        border: 1px solid var(--cn-line);
        background: var(--cn-surface);
        font-size: var(--text-xs);
        font-weight: 600;
        color: var(--text-secondary);
    }

    .cn-eyebrow i {
        color: var(--cn-accent);
    }

    .cn-title {
        margin: var(--space-3) 0 0;
        font-size: clamp(26px, 2vw + 18px, 38px);
        font-weight: 700;
        letter-spacing: -0.02em;
        line-height: 1.2;
        color: var(--text-primary);
    }

    .cn-lead {
        max-width: 560px;
        margin: var(--space-3) auto 0;
        font-size: var(--text-base);
        line-height: 1.65;
        color: var(--text-muted);
    }

    /* ===== Gövde ===== */
    .cn-body {
        padding: var(--space-7) 0;
    }

    .cn-grid {
        display: grid;
        grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.2fr);
        gap: var(--space-6);
        align-items: start;
    }

    /* ===== Kanallar ===== */
    .cn-channels {
        display: grid;
        gap: var(--space-3);
    }

    .cn-ch {
        display: flex;
        gap: var(--space-3);
        padding: var(--space-4);
        border: 1px solid var(--cn-line);
        border-radius: var(--cn-radius);
        background: var(--cn-surface);
    }

    .cn-ch-icon {
        flex-shrink: 0;
        width: 38px;
        height: 38px;
        display: grid;
        place-items: center;
        border-radius: 8px;
        background: color-mix(in srgb, var(--primary) 10%, transparent);
        color: var(--cn-accent);
        font-size: 15px;
    }

    .cn-ch h3 {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 4px;
    }

    .cn-ch p {
        font-size: var(--text-sm);
        line-height: 1.55;
        color: var(--text-primary);
    }

    .cn-ch a:hover {
        color: var(--cn-accent);
    }

    .cn-ch-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 8px;
        font-size: var(--text-xs);
        font-weight: 600;
        color: var(--cn-accent);
    }

    .cn-ch-link:hover {
        text-decoration: underline;
    }

    .cn-social {
        display: flex;
        gap: 8px;
        margin-top: var(--space-3);
    }

    .cn-social a {
        width: 36px;
        height: 36px;
        display: grid;
        place-items: center;
        border: 1px solid var(--cn-line);
        border-radius: 8px;
        background: var(--cn-surface);
        color: var(--text-muted);
        transition: border-color 0.15s ease, color 0.15s ease;
    }

    .cn-social a:hover {
        border-color: var(--cn-accent);
        color: var(--cn-accent);
    }

    /* ===== Form ===== */
    .cn-form-card {
        border: 1px solid var(--cn-line);
        border-radius: var(--cn-radius);
        background: var(--cn-surface);
        overflow: hidden;
    }

    .cn-form-head {
        padding: var(--space-3) var(--space-5);
        border-bottom: 1px solid var(--cn-line);
        background: color-mix(in srgb, var(--primary) 5%, transparent);
    }

    .cn-form-head h2 {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-primary);
    }

    .cn-form-head p {
        margin-top: 4px;
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    .cn-form-body {
        padding: var(--space-5);
    }

    .cn-alert {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: var(--space-4);
        padding: 11px 14px;
        border-radius: 8px;
        font-size: var(--text-sm);
        line-height: 1.5;
    }

    .cn-alert.is-success {
        border: 1px solid color-mix(in srgb, var(--success) 40%, transparent);
        background: color-mix(in srgb, var(--success) 10%, transparent);
        color: var(--text-primary);
    }

    .cn-alert.is-error {
        border: 1px solid color-mix(in srgb, var(--danger) 40%, transparent);
        background: color-mix(in srgb, var(--danger) 10%, transparent);
        color: var(--text-primary);
    }

    .cn-alert i {
        margin-top: 2px;
    }

    .cn-alert.is-success i {
        color: var(--success);
    }

    .cn-alert.is-error i {
        color: var(--danger);
    }

    .cn-row {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--space-4);
    }

    .cn-field {
        margin-bottom: var(--space-4);
    }

    .cn-field label {
        display: block;
        margin-bottom: 6px;
        font-size: var(--text-sm);
        font-weight: 600;
        color: var(--text-secondary);
    }

    .cn-field label span {
        font-weight: 400;
        color: var(--text-muted);
    }

    .cn-field input,
    .cn-field textarea {
        width: 100%;
        padding: 10px 13px;
        border: 1px solid var(--cn-line);
        border-radius: 8px;
        background: var(--bg-body);
        color: var(--text-primary);
        font-family: inherit;
        font-size: var(--text-sm);
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .cn-field textarea {
        min-height: 150px;
        resize: vertical;
        line-height: 1.6;
    }

    .cn-field input:focus,
    .cn-field textarea:focus {
        outline: none;
        border-color: var(--cn-accent);
        box-shadow: var(--focus-ring);
    }

    .cn-field.has-error input,
    .cn-field.has-error textarea {
        border-color: var(--danger);
    }

    .cn-err {
        display: block;
        margin-top: 5px;
        font-size: var(--text-xs);
        color: var(--danger);
    }

    /* Bot tuzağı - ekranda ve okuyucuda görünmez */
    .cn-trap {
        position: absolute;
        left: -9999px;
        width: 1px;
        height: 1px;
        overflow: hidden;
    }

    .cn-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: var(--space-3);
    }

    .cn-note {
        max-width: 340px;
        font-size: var(--text-xs);
        line-height: 1.5;
        color: var(--text-muted);
    }

    .cn-note a {
        color: var(--cn-accent);
    }

    .cn-submit {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        background: var(--cn-accent);
        color: #fff;
        font-family: inherit;
        font-size: var(--text-sm);
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.15s ease;
    }

    .cn-submit:hover {
        background: var(--primary-dark);
    }

    /* ==========================================
       Açık tema: zemin soğuk maviye çekilir,
       kartlar beyaz kalıp zeminden ayrışır.
       ========================================== */
    [data-theme="light"] .cn {
        --cn-line: #d3e2f8;
        background: #eff5fe;
    }

    [data-theme="light"] .cn-hero {
        background: linear-gradient(180deg, #e4edfb 0%, #eff5fe 100%);
    }

    [data-theme="light"] .cn-ch,
    [data-theme="light"] .cn-form-card {
        box-shadow:
            0 1px 2px rgba(36, 116, 245, 0.05),
            0 10px 26px -14px rgba(36, 116, 245, 0.28);
    }

    [data-theme="light"] .cn-field input,
    [data-theme="light"] .cn-field textarea {
        background: #fff;
    }

    @media (max-width: 900px) {
        .cn-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 640px) {
        .cn-row {
            grid-template-columns: 1fr;
        }

        .cn-foot {
            flex-direction: column;
            align-items: stretch;
        }

        .cn-submit {
            justify-content: center;
        }
    }
</style>

<div class="cn">

    <section class="cn-hero">
        <div class="container">
            <div class="cn-hero-inner">
                <span class="cn-eyebrow"><i class="fas fa-headset"></i> 7/24 teknik destek</span>
                <h1 class="cn-title">Bize ulaşın</h1>
                <p class="cn-lead">
                    Teklif, teknik destek ya da iş birliği — aşağıdaki kanallardan
                    doğrudan yazabilir veya formu doldurabilirsiniz.
                </p>
            </div>
        </div>
    </section>

    <section class="cn-body">
        <div class="container">
            <div class="cn-grid">

                <!-- İletişim kanalları: tümü ayarlardan gelir -->
                <div>
                    <div class="cn-channels">
                        <?php if ($adres !== ''): ?>
                            <div class="cn-ch">
                                <div class="cn-ch-icon"><i class="fas fa-location-dot"></i></div>
                                <div>
                                    <h3>Adres</h3>
                                    <p><?= nl2br(htmlspecialchars($adres)) ?></p>
                                    <a class="cn-ch-link"
                                        href="https://www.google.com/maps/search/?api=1&amp;query=<?= urlencode($adres) ?>"
                                        target="_blank" rel="noopener noreferrer">
                                        Yol tarifi al <i class="fas fa-arrow-up-right-from-square"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($telefon !== ''): ?>
                            <div class="cn-ch">
                                <div class="cn-ch-icon"><i class="fas fa-phone"></i></div>
                                <div>
                                    <h3>Telefon</h3>
                                    <p><a href="tel:<?= htmlspecialchars($telBaglanti($telefon)) ?>"
                                            dir="ltr"><?= htmlspecialchars($telefon) ?></a></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($eposta !== ''): ?>
                            <div class="cn-ch">
                                <div class="cn-ch-icon"><i class="fas fa-envelope"></i></div>
                                <div>
                                    <h3>E-posta</h3>
                                    <p><a href="mailto:<?= htmlspecialchars($eposta) ?>"
                                            dir="ltr"><?= htmlspecialchars($eposta) ?></a></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="cn-ch">
                            <div class="cn-ch-icon"><i class="fas fa-clock"></i></div>
                            <div>
                                <h3>Destek</h3>
                                <p>7/24 teknik destek. Formdan gelen mesajlara mesai saatleri içinde dönüş yapılır.</p>
                                <a class="cn-ch-link" href="client/tickets.php">
                                    Destek talebi aç <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <?php if ($sosyal): ?>
                        <div class="cn-social">
                            <?php foreach ($sosyal as $s): ?>
                                <a href="<?= htmlspecialchars($s['url']) ?>" target="_blank" rel="noopener noreferrer"
                                    title="<?= htmlspecialchars($s['ad']) ?>"
                                    aria-label="<?= htmlspecialchars($s['ad']) ?>">
                                    <i class="<?= htmlspecialchars($s['ikon']) ?>"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Form -->
                <div class="cn-form-card" id="iletisim-formu">
                    <div class="cn-form-head">
                        <h2>Mesaj gönderin</h2>
                        <p>Yıldızlı alanlar zorunludur.</p>
                    </div>

                    <div class="cn-form-body">
                        <?php if ($sonuc !== ''): ?>
                            <div class="cn-alert <?= $sonucTipi === 'success' ? 'is-success' : 'is-error' ?>">
                                <i class="fas fa-<?= $sonucTipi === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
                                <span><?= htmlspecialchars($sonuc) ?></span>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="iletisim.php#iletisim-formu" novalidate>
                            <input type="hidden" name="token"
                                value="<?= htmlspecialchars($_SESSION['iletisim_token']) ?>">

                            <div class="cn-trap" aria-hidden="true">
                                <label for="website">Web siteniz</label>
                                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                            </div>

                            <div class="cn-row">
                                <div class="cn-field <?= isset($hatalar['ad_soyad']) ? 'has-error' : '' ?>">
                                    <label for="ad_soyad">Ad soyad *</label>
                                    <input type="text" id="ad_soyad" name="ad_soyad" required maxlength="120"
                                        autocomplete="name" value="<?= htmlspecialchars($eski['ad_soyad']) ?>">
                                    <?php if (isset($hatalar['ad_soyad'])): ?>
                                        <span class="cn-err"><?= htmlspecialchars($hatalar['ad_soyad']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="cn-field <?= isset($hatalar['email']) ? 'has-error' : '' ?>">
                                    <label for="email">E-posta *</label>
                                    <input type="email" id="email" name="email" required maxlength="150"
                                        autocomplete="email" dir="ltr"
                                        value="<?= htmlspecialchars($eski['email']) ?>">
                                    <?php if (isset($hatalar['email'])): ?>
                                        <span class="cn-err"><?= htmlspecialchars($hatalar['email']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="cn-row">
                                <div class="cn-field <?= isset($hatalar['telefon']) ? 'has-error' : '' ?>">
                                    <label for="telefon">Telefon <span>(isteğe bağlı)</span></label>
                                    <input type="tel" id="telefon" name="telefon" maxlength="30" autocomplete="tel"
                                        dir="ltr" placeholder="532 123 45 67"
                                        value="<?= htmlspecialchars($eski['telefon']) ?>">
                                    <?php if (isset($hatalar['telefon'])): ?>
                                        <span class="cn-err"><?= htmlspecialchars($hatalar['telefon']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="cn-field <?= isset($hatalar['konu']) ? 'has-error' : '' ?>">
                                    <label for="konu">Konu *</label>
                                    <input type="text" id="konu" name="konu" required maxlength="180"
                                        value="<?= htmlspecialchars($eski['konu']) ?>">
                                    <?php if (isset($hatalar['konu'])): ?>
                                        <span class="cn-err"><?= htmlspecialchars($hatalar['konu']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="cn-field <?= isset($hatalar['mesaj']) ? 'has-error' : '' ?>">
                                <label for="mesaj">Mesajınız *</label>
                                <textarea id="mesaj" name="mesaj" required maxlength="4000"
                                    placeholder="Nasıl yardımcı olabiliriz?"><?= htmlspecialchars($eski['mesaj']) ?></textarea>
                                <?php if (isset($hatalar['mesaj'])): ?>
                                    <span class="cn-err"><?= htmlspecialchars($hatalar['mesaj']) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="cn-foot">
                                <p class="cn-note">
                                    Gönderdiğiniz bilgiler yalnızca talebinizi yanıtlamak için kullanılır.
                                    Ayrıntı için <a href="kvkk.php">KVKK Aydınlatma Metni</a>.
                                </p>
                                <button type="submit" class="cn-submit">
                                    <i class="fas fa-paper-plane"></i> Mesajı gönder
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </section>

</div>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>
