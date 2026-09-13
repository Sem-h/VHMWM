<?php
/**
 * WHMVM - Gizlilik Politikası
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Settings.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Gizlilik Politikası';
$pageDescription = 'WHMVM gizlilik politikası ve kişisel verilerin korunması.';

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

.content-section { padding: 80px 0; }

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
        <h1>Gizlilik <span>Politikası</span></h1>
        <p>Kişisel verilerinizi nasıl topladığımızı ve koruduğumuzu öğrenin.</p>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <div class="legal-content">
            <div class="last-updated">
                <?php
                // Tarih ayarlardan gelir; metin değişmediği sürece sabit kalır.
                $yururluk = trim((string) Settings::get('privacy_yururluk', ''));
                ?>
                <i class="fas fa-calendar-alt"></i>
                Yürürlük tarihi:
                <?= $yururluk !== '' ? htmlspecialchars(date('d.m.Y', strtotime($yururluk))) : '—' ?>
            </div>

            <h2>1. Toplanan Veriler</h2>
            <p>Hizmetlerimizi kullanırken aşağıdaki kişisel verileri toplayabiliriz:</p>
            <ul>
                <li>Ad, soyad ve iletişim bilgileri</li>
                <li>E-posta adresi ve telefon numarası</li>
                <li>Fatura ve ödeme bilgileri</li>
                <li>IP adresi ve tarayıcı bilgileri</li>
                <li>Hizmet kullanım verileri</li>
            </ul>

            <h2>2. Verilerin Kullanımı</h2>
            <p>Topladığımız verileri şu amaçlarla kullanırız:</p>
            <ul>
                <li>Hizmetlerin sunulması ve yönetilmesi</li>
                <li>Faturalama ve ödeme işlemleri</li>
                <li>Müşteri desteği sağlanması</li>
                <li>Hizmet iyileştirmeleri ve geliştirmeler</li>
                <li>Yasal yükümlülüklerin yerine getirilmesi</li>
            </ul>

            <h2>3. Veri Güvenliği</h2>
            <p>Verilerinizi korumak için endüstri standardı güvenlik önlemleri uyguluyoruz. SSL şifreleme, güvenlik duvarları ve erişim kontrolleri bu önlemler arasındadır.</p>

            <h2>4. Üçüncü Taraflar</h2>
            <p>Kişisel verilerinizi, yasal zorunluluklar dışında üçüncü taraflarla paylaşmıyoruz. Ödeme işlemleri için güvenilir ödeme sağlayıcıları kullanıyoruz.</p>

            <h2>5. Çerezler</h2>
            <p>Web sitemizde çerezler kullanılmaktadır. Çerezler, kullanıcı deneyimini iyileştirmek ve site trafiğini analiz etmek için kullanılır.</p>

            <h2>6. Haklarınız</h2>
            <p>KVKK kapsamında aşağıdaki haklara sahipsiniz:</p>
            <ul>
                <li>Verilerinize erişim hakkı</li>
                <li>Verilerin düzeltilmesini talep etme hakkı</li>
                <li>Verilerin silinmesini talep etme hakkı</li>
                <li>Veri işlemeye itiraz etme hakkı</li>
            </ul>

            <h2>7. İletişim</h2>
            <p>Gizlilik politikamız hakkında sorularınız için <a href="iletisim.php" style="color: var(--primary-light);">iletişim sayfamızdan</a> bize ulaşabilirsiniz.</p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>

