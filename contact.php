<?php
/**
 * WHMVM - İletişim Sayfası
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

// İletişim adresi ayarlardan gelir; sayfaya sabit yazılmaz
require_once __DIR__ . '/includes/Settings.php';
$iletisimEposta = Settings::get('company_email', '');

// Sayfa değişkenleri
$pageTitle = 'İletişim';
$pageDescription = 'Bizimle iletişime geçin. 7/24 destek hattımız ile her zaman yanınızdayız.';

$message = '';
$messageType = '';

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $content = trim($_POST['message'] ?? '');
    
    if (!empty($name) && !empty($email) && !empty($subject) && !empty($content)) {
        // Burada e-posta gönderme veya veritabanına kaydetme işlemi yapılabilir
        $message = 'Mesajınız başarıyla gönderildi. En kısa sürede size dönüş yapacağız.';
        $messageType = 'success';
    } else {
        $message = 'Lütfen tüm alanları doldurun.';
        $messageType = 'error';
    }
}

// Header
require_once __DIR__ . '/theme/includes/header.php';
?>

<style>
/* Page Hero */
.page-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
    padding: 120px 0 80px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.page-hero::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: 
        radial-gradient(ellipse at 30% 50%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 70% 30%, rgba(14, 165, 233, 0.1) 0%, transparent 40%);
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

.page-hero p {
    font-size: 18px;
    color: var(--gray-light);
    max-width: 600px;
    margin: 0 auto;
}

/* Contact Section */
.contact-section {
    padding: 100px 0;
    background: var(--darker);
}

.contact-grid {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 50px;
}

/* Contact Info */
.contact-info h3 {
    font-size: 28px;
    margin-bottom: 20px;
}

.contact-info p {
    color: var(--gray-light);
    margin-bottom: 40px;
    line-height: 1.7;
}

.info-list {
    margin-bottom: 40px;
}

.info-item {
    display: flex;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 25px;
}

.info-icon {
    width: 55px;
    height: 55px;
    background: rgba(99, 102, 241, 0.1);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: var(--primary-light);
    flex-shrink: 0;
}

.info-content h4 {
    font-size: 16px;
    margin-bottom: 5px;
}

.info-content p {
    color: var(--gray-light);
    font-size: 14px;
    margin: 0;
}

.info-content a {
    color: var(--primary-light);
}

.social-links {
    display: flex;
    gap: 12px;
}

.social-links a {
    width: 45px;
    height: 45px;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray);
    transition: all 0.3s ease;
}

.social-links a:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
    transform: translateY(-3px);
}

/* Contact Form */
.contact-form-wrapper {
    background: rgba(255,255,255,0.02);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 40px;
}

.contact-form-wrapper h3 {
    font-size: 24px;
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 10px;
    color: var(--gray-light);
}

.form-control {
    width: 100%;
    padding: 15px 20px;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    color: white;
    font-size: 15px;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.form-control::placeholder {
    color: var(--gray);
}

textarea.form-control {
    min-height: 150px;
    resize: vertical;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.submit-btn {
    width: 100%;
    padding: 18px;
    font-size: 16px;
}

/* Alert */
.alert {
    padding: 15px 20px;
    border-radius: var(--radius-md);
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-success {
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #34d399;
}

.alert-error {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #f87171;
}

/* Map Section */
.map-section {
    height: 400px;
    background: var(--dark);
    position: relative;
}

.map-section iframe {
    width: 100%;
    height: 100%;
    border: none;
    filter: grayscale(100%) invert(90%);
}

/* Responsive */
@media (max-width: 992px) {
    .contact-grid { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .page-hero h1 { font-size: 36px; }
    .form-row { grid-template-columns: 1fr; }
    .contact-form-wrapper { padding: 25px; }
}
</style>

<!-- Page Hero -->
<section class="page-hero">
    <div class="container">
        <h1>Bizimle <span>İletişime</span> Geçin</h1>
        <p>Sorularınız için 7/24 destek ekibimize ulaşabilirsiniz. Size yardımcı olmaktan mutluluk duyarız.</p>
    </div>
</section>

<!-- Contact Section -->
<section class="contact-section">
    <div class="container">
        <div class="contact-grid">
            <!-- Contact Info -->
            <div class="contact-info">
                <h3>İletişim Bilgilerimiz</h3>
                <p>Aşağıdaki iletişim kanallarından bize ulaşabilir veya formu doldurarak mesaj gönderebilirsiniz.</p>
                
                <div class="info-list">
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="info-content">
                            <h4>Adres</h4>
                            <p>Maslak, Büyükdere Cad. No:123<br>Sarıyer / İstanbul</p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="info-content">
                            <h4>Telefon</h4>
                            <p><a href="tel:+902121234567">0212 123 45 67</a></p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="info-content">
                            <h4>E-posta</h4>
                            <p><a href="mailto:<?= htmlspecialchars($iletisimEposta) ?>" dir="ltr"><?= htmlspecialchars($iletisimEposta) ?></a></p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="info-content">
                            <h4>Çalışma Saatleri</h4>
                            <p>7/24 Teknik Destek</p>
                        </div>
                    </div>
                </div>
                
                <div class="social-links">
                    <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" title="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            
            <!-- Contact Form -->
            <div class="contact-form-wrapper">
                <h3>Mesaj Gönderin</h3>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?>">
                        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Adınız Soyadınız</label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="Adınızı girin" required>
                        </div>
                        <div class="form-group">
                            <label for="email">E-posta Adresiniz</label>
                            <input type="email" id="email" name="email" class="form-control" placeholder="E-posta adresinizi girin" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Konu</label>
                        <input type="text" id="subject" name="subject" class="form-control" placeholder="Mesajınızın konusu" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Mesajınız</label>
                        <textarea id="message" name="message" class="form-control" placeholder="Mesajınızı yazın..." required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary submit-btn">
                        <i class="fas fa-paper-plane"></i>
                        Mesaj Gönder
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Map -->
<section class="map-section">
    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3007.7673784428747!2d29.0178!3d41.1089!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zNDHCsDA2JzMyLjAiTiAyOcKwMDEnMDQuMSJF!5e0!3m2!1str!2str!4v1234567890" allowfullscreen="" loading="lazy"></iframe>
</section>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>
