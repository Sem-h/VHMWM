<?php
/**
 * WHMVM - Kullanım Şartları
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Kullanım Şartları';
$pageDescription = 'WHMVM hizmet kullanım şartları ve koşulları.';

require_once __DIR__ . '/theme/includes/header.php';
?>

<style>
.page-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
    padding: 120px 0 80px;
    text-align: center;
    position: relative;
}

.page-hero::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: radial-gradient(ellipse at 50% 50%, rgba(99, 102, 241, 0.15) 0%, transparent 50%);
}

.page-hero .container { position: relative; z-index: 1; }

.page-hero h1 {
    font-size: 48px;
    font-weight: 800;
    margin-bottom: 20px;
}

.page-hero h1 span {
    background: var(--gradient-primary);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.content-section {
    padding: 80px 0;
}

.legal-content {
    max-width: 800px;
    margin: 0 auto;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 50px;
}

.legal-content h2 {
    font-size: 24px;
    margin: 40px 0 20px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}

.legal-content h2:first-of-type {
    margin-top: 0;
    padding-top: 0;
    border-top: none;
}

.legal-content p {
    color: var(--text-muted);
    line-height: 1.8;
    margin-bottom: 15px;
}

.legal-content ul {
    margin: 20px 0;
    padding-left: 25px;
}

.legal-content li {
    color: var(--text-muted);
    line-height: 1.8;
    margin-bottom: 10px;
}

.last-updated {
    background: rgba(99, 102, 241, 0.1);
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 30px;
    font-size: 14px;
    color: var(--text-muted);
}

@media (max-width: 768px) {
    .page-hero h1 { font-size: 32px; }
    .legal-content { padding: 30px; }
}
</style>

<section class="page-hero">
    <div class="container">
        <h1>Kullanım <span>Şartları</span></h1>
        <p>Hizmetlerimizi kullanmadan önce lütfen bu şartları okuyun.</p>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <div class="legal-content">
            <div class="last-updated">
                <i class="fas fa-calendar-alt"></i> Son güncelleme: <?= date('d.m.Y') ?>
            </div>

            <h2>1. Genel Hükümler</h2>
            <p>Bu kullanım şartları, WHMVM tarafından sunulan tüm hosting, sunucu ve ilgili hizmetlerin kullanımını düzenler. Hizmetlerimizi kullanarak bu şartları kabul etmiş sayılırsınız.</p>

            <h2>2. Hizmet Kullanımı</h2>
            <p>Hizmetlerimizi kullanırken aşağıdaki kurallara uymanız gerekmektedir:</p>
            <ul>
                <li>Yasalara aykırı içerik barındırılamaz</li>
                <li>Spam gönderimi yapılamaz</li>
                <li>Telif haklarına saygı gösterilmelidir</li>
                <li>Sunucu kaynaklarının kötüye kullanımı yasaktır</li>
                <li>Diğer kullanıcıların hizmetlerini etkileyecek davranışlardan kaçınılmalıdır</li>
            </ul>

            <h2>3. Ödeme Koşulları</h2>
            <p>Tüm faturalar vade tarihinde ödenmeli. Ödenmemiş faturalar hizmetin askıya alınmasına neden olabilir. Fiyatlar KDV hariçtir ve değişiklik hakkı saklıdır.</p>

            <h2>4. Hizmet Düzeyi</h2>
            <p>Hizmet düzeyi anlaşmamız (SLA) ayrı bir belge olarak sunulmaktadır. Uptime garantisi ve tazminat politikası için SLA sayfamızı ziyaret edin.</p>

            <h2>5. Veri Güvenliği</h2>
            <p>Verilerinizin güvenliği bizim için önemlidir ancak yedekleme sorumluluğu müşteriye aittir. Düzenli yedek almanızı öneriyoruz.</p>

            <h2>6. Hesap Güvenliği</h2>
            <p>Hesap bilgilerinizin güvenliğinden siz sorumlusunuz. Güçlü şifreler kullanın ve hesap bilgilerinizi paylaşmayın.</p>

            <h2>7. Fesih</h2>
            <p>Her iki taraf da 30 gün önceden yazılı bildirimde bulunarak hizmeti feshedebilir. Kural ihlali durumunda hizmet derhal sonlandırılabilir.</p>

            <h2>8. Sorumluluk Sınırı</h2>
            <p>Şirketimiz, hizmet kesintileri veya veri kayıplarından doğabilecek dolaylı zararlardan sorumlu tutulamaz.</p>

            <h2>9. Değişiklikler</h2>
            <p>Bu şartlar önceden bildirim yapılmaksızın değiştirilebilir. Değişiklikler web sitemizde yayınlandığı tarihte yürürlüğe girer.</p>

            <h2>10. İletişim</h2>
            <p>Sorularınız için <a href="contact.php" style="color: var(--primary-light);">iletişim sayfamızdan</a> bize ulaşabilirsiniz.</p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>

