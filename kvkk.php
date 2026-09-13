<?php
/**
 * WHMVM - KVKK Aydınlatma Metni
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

// İletişim adresi ayarlardan gelir; sayfaya sabit yazılmaz
require_once __DIR__ . '/includes/Settings.php';
$iletisimEposta = Settings::get('company_email', '');

$pageTitle = 'KVKK Aydınlatma Metni';
$pageDescription = 'WHMVM KVKK kapsamında kişisel verilerin korunması aydınlatma metni.';

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
        <h1>KVKK <span>Aydınlatma Metni</span></h1>
        <p>6698 Sayılı Kişisel Verilerin Korunması Kanunu kapsamında aydınlatma metni.</p>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <div class="legal-content">
            <div class="last-updated">
                <i class="fas fa-calendar-alt"></i> Son güncelleme: <?= date('d.m.Y') ?>
            </div>

            <h2>1. Veri Sorumlusu</h2>
            <p>6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") uyarınca, kişisel verileriniz veri sorumlusu sıfatıyla WHMVM tarafından aşağıda açıklanan kapsamda işlenebilecektir.</p>

            <h2>2. İşlenen Kişisel Veriler</h2>
            <p>Aşağıdaki kategorilerdeki kişisel verileriniz işlenmektedir:</p>
            <ul>
                <li><strong>Kimlik Bilgileri:</strong> Ad, soyad, T.C. kimlik numarası</li>
                <li><strong>İletişim Bilgileri:</strong> Adres, telefon, e-posta</li>
                <li><strong>Finansal Bilgiler:</strong> Banka hesap bilgileri, fatura bilgileri</li>
                <li><strong>Müşteri İşlem Bilgileri:</strong> Sipariş ve hizmet kullanım bilgileri</li>
                <li><strong>İşlem Güvenliği Bilgileri:</strong> IP adresi, log kayıtları</li>
            </ul>

            <h2>3. Kişisel Verilerin İşlenme Amaçları</h2>
            <ul>
                <li>Hizmet sözleşmesinin kurulması ve ifası</li>
                <li>Faturalama ve ödeme işlemlerinin yürütülmesi</li>
                <li>Müşteri ilişkileri yönetimi</li>
                <li>Yasal yükümlülüklerin yerine getirilmesi</li>
                <li>Bilgi güvenliği süreçlerinin yürütülmesi</li>
            </ul>

            <h2>4. Kişisel Verilerin Aktarılması</h2>
            <p>Kişisel verileriniz, yukarıda belirtilen amaçlar doğrultusunda;</p>
            <ul>
                <li>Kanunen yetkili kamu kurumlarına</li>
                <li>Ödeme hizmeti sağlayıcılarına</li>
                <li>Hizmet sağlayıcı iş ortaklarımıza</li>
            </ul>
            <p>aktarılabilecektir.</p>

            <h2>5. Veri Saklama Süresi</h2>
            <p>Kişisel verileriniz, işleme amaçlarının gerektirdiği süre boyunca ve ilgili mevzuatta öngörülen zamanaşımı süreleri kadar saklanmaktadır.</p>

            <h2>6. İlgili Kişi Olarak Haklarınız</h2>
            <p>KVKK'nın 11. maddesi uyarınca;</p>
            <ul>
                <li>Kişisel verilerinizin işlenip işlenmediğini öğrenme</li>
                <li>İşlenmişse buna ilişkin bilgi talep etme</li>
                <li>İşlenme amacını ve amaca uygun kullanılıp kullanılmadığını öğrenme</li>
                <li>Aktarıldığı üçüncü kişileri bilme</li>
                <li>Eksik veya yanlış işlenmişse düzeltilmesini isteme</li>
                <li>Silinmesini veya yok edilmesini isteme</li>
                <li>İşlenen verilerin münhasıran otomatik sistemler vasıtasıyla analiz edilmesi suretiyle aleyhinize bir sonucun ortaya çıkmasına itiraz etme</li>
                <li>Kanuna aykırı işleme sebebiyle zarara uğramanız halinde zararın giderilmesini talep etme</li>
            </ul>
            <p>haklarına sahipsiniz.</p>

            <h2>7. Başvuru</h2>
            <p>Haklarınızı kullanmak için <a href="contact.php" style="color: var(--primary-light);">iletişim sayfamızdan</a> veya <?= htmlspecialchars($iletisimEposta) ?> adresinden bize ulaşabilirsiniz.</p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>

