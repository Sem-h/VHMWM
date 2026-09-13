<?php
/**
 * WHMVM - Web Hosting Sayfası
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

// Sayfa değişkenleri
$pageTitle = 'Web Hosting Paketleri';
$pageDescription = 'Yüksek performanslı web hosting paketleri. SSD diskler, ücretsiz SSL ve 7/24 teknik destek.';

$type = $_GET['type'] ?? 'linux';

// Hosting paketlerini çek
try {
    $products = Database::fetchAll("SELECT p.*, pg.name as group_name FROM products p LEFT JOIN product_groups pg ON p.group_id = pg.id ORDER BY p.id ASC LIMIT 4");
} catch (Exception $e) {
    $products = [];
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

/* Hosting Section */
.hosting-section {
    padding: 100px 0;
    background: var(--darker);
}

.hosting-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
}

.hosting-card {
    background: rgba(255,255,255,0.02);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: all 0.4s ease;
    position: relative;
}

.hosting-card:hover {
    transform: translateY(-10px);
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
}

.hosting-card.popular {
    border-color: var(--primary);
    transform: scale(1.05);
}

.hosting-card.popular:hover {
    transform: scale(1.05) translateY(-10px);
}

.popular-badge {
    background: var(--gradient-primary);
    color: white;
    text-align: center;
    padding: 10px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.hosting-header {
    padding: 35px 25px;
    text-align: center;
    border-bottom: 1px solid var(--border);
}

.hosting-header h3 {
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 20px;
}

.hosting-price {
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: 5px;
}

.hosting-price .currency {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary-light);
}

.hosting-price .amount {
    font-size: 48px;
    font-weight: 800;
    color: var(--primary-light);
}

.hosting-price .period {
    font-size: 14px;
    color: var(--gray);
}

.hosting-body {
    padding: 30px 25px;
}

.hosting-features {
    margin-bottom: 30px;
}

.hosting-features li {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    color: var(--gray-light);
    font-size: 14px;
}

.hosting-features li:last-child {
    border-bottom: none;
}

.hosting-features li i {
    color: var(--success);
    font-size: 14px;
}

.hosting-card .btn {
    width: 100%;
}

/* Features Grid */
.features-section {
    padding: 100px 0;
    background: var(--dark);
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 30px;
}

.feature-box {
    text-align: center;
    padding: 40px 25px;
    background: rgba(255,255,255,0.02);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    transition: all 0.3s ease;
}

.feature-box:hover {
    border-color: rgba(99, 102, 241, 0.3);
    background: rgba(99, 102, 241, 0.05);
}

.feature-box-icon {
    width: 70px;
    height: 70px;
    background: var(--gradient-primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 28px;
    color: white;
}

.feature-box h4 {
    font-size: 18px;
    margin-bottom: 12px;
}

.feature-box p {
    color: var(--gray-light);
    font-size: 14px;
    line-height: 1.6;
}

/* Responsive */
@media (max-width: 1200px) {
    .hosting-grid { grid-template-columns: repeat(2, 1fr); }
    .hosting-card.popular { transform: scale(1); }
    .hosting-card.popular:hover { transform: translateY(-10px); }
}

@media (max-width: 768px) {
    .hosting-grid { grid-template-columns: 1fr; }
    .features-grid { grid-template-columns: repeat(2, 1fr); }
    .page-hero h1 { font-size: 36px; }
}

@media (max-width: 576px) {
    .features-grid { grid-template-columns: 1fr; }
}
</style>

<!-- Page Hero -->
<section class="page-hero">
    <div class="container">
        <h1>Web <span>Hosting</span> Paketleri</h1>
        <p>Yüksek performanslı SSD diskler, ücretsiz SSL sertifikası ve 7/24 teknik destek ile web sitenizi güçlendirin.</p>
    </div>
</section>

<!-- Hosting Packages -->
<section class="hosting-section">
    <div class="container">
        <div class="section-header">
            <span class="section-badge">
                <i class="fas fa-server"></i>
                Hosting Paketleri
            </span>
            <h2 class="section-title">Size Uygun <span>Paketi Seçin</span></h2>
            <p class="section-desc">Her ihtiyaca uygun hosting çözümleri. Küçük bloglardan büyük e-ticaret sitelerine.</p>
        </div>
        
        <div class="hosting-grid">
            <!-- Starter -->
            <div class="hosting-card">
                <div class="hosting-header">
                    <h3>Starter</h3>
                    <div class="hosting-price">
                        <span class="currency">₺</span>
                        <span class="amount">29</span>
                        <span class="period">/ay</span>
                    </div>
                </div>
                <div class="hosting-body">
                    <ul class="hosting-features">
                        <li><i class="fas fa-check"></i> 5 GB SSD Disk</li>
                        <li><i class="fas fa-check"></i> 50 GB Bant Genişliği</li>
                        <li><i class="fas fa-check"></i> 1 Web Sitesi</li>
                        <li><i class="fas fa-check"></i> Ücretsiz SSL</li>
                        <li><i class="fas fa-check"></i> 5 E-posta Hesabı</li>
                        <li><i class="fas fa-check"></i> Günlük Yedekleme</li>
                    </ul>
                    <a href="sepet.php?add=hosting-starter" class="btn btn-outline">
                        Sipariş Ver
                    </a>
                </div>
            </div>
            
            <!-- Professional -->
            <div class="hosting-card popular">
                <div class="popular-badge">En Popüler</div>
                <div class="hosting-header">
                    <h3>Professional</h3>
                    <div class="hosting-price">
                        <span class="currency">₺</span>
                        <span class="amount">59</span>
                        <span class="period">/ay</span>
                    </div>
                </div>
                <div class="hosting-body">
                    <ul class="hosting-features">
                        <li><i class="fas fa-check"></i> 25 GB SSD Disk</li>
                        <li><i class="fas fa-check"></i> 200 GB Bant Genişliği</li>
                        <li><i class="fas fa-check"></i> 10 Web Sitesi</li>
                        <li><i class="fas fa-check"></i> Ücretsiz SSL</li>
                        <li><i class="fas fa-check"></i> 50 E-posta Hesabı</li>
                        <li><i class="fas fa-check"></i> Günlük Yedekleme</li>
                    </ul>
                    <a href="sepet.php?add=hosting-pro" class="btn btn-primary">
                        Sipariş Ver
                    </a>
                </div>
            </div>
            
            <!-- Business -->
            <div class="hosting-card">
                <div class="hosting-header">
                    <h3>Business</h3>
                    <div class="hosting-price">
                        <span class="currency">₺</span>
                        <span class="amount">99</span>
                        <span class="period">/ay</span>
                    </div>
                </div>
                <div class="hosting-body">
                    <ul class="hosting-features">
                        <li><i class="fas fa-check"></i> 50 GB SSD Disk</li>
                        <li><i class="fas fa-check"></i> Sınırsız Bant Genişliği</li>
                        <li><i class="fas fa-check"></i> Sınırsız Web Sitesi</li>
                        <li><i class="fas fa-check"></i> Ücretsiz SSL</li>
                        <li><i class="fas fa-check"></i> Sınırsız E-posta</li>
                        <li><i class="fas fa-check"></i> Günlük Yedekleme</li>
                    </ul>
                    <a href="sepet.php?add=hosting-business" class="btn btn-outline">
                        Sipariş Ver
                    </a>
                </div>
            </div>
            
            <!-- Enterprise -->
            <div class="hosting-card">
                <div class="hosting-header">
                    <h3>Enterprise</h3>
                    <div class="hosting-price">
                        <span class="currency">₺</span>
                        <span class="amount">199</span>
                        <span class="period">/ay</span>
                    </div>
                </div>
                <div class="hosting-body">
                    <ul class="hosting-features">
                        <li><i class="fas fa-check"></i> 100 GB NVMe SSD</li>
                        <li><i class="fas fa-check"></i> Sınırsız Bant Genişliği</li>
                        <li><i class="fas fa-check"></i> Sınırsız Web Sitesi</li>
                        <li><i class="fas fa-check"></i> Wildcard SSL</li>
                        <li><i class="fas fa-check"></i> Sınırsız E-posta</li>
                        <li><i class="fas fa-check"></i> Öncelikli Destek</li>
                    </ul>
                    <a href="sepet.php?add=hosting-enterprise" class="btn btn-outline">
                        Sipariş Ver
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features -->
<section class="features-section">
    <div class="container">
        <div class="section-header">
            <span class="section-badge">
                <i class="fas fa-star"></i>
                Özellikler
            </span>
            <h2 class="section-title">Hosting <span>Avantajları</span></h2>
        </div>
        
        <div class="features-grid">
            <div class="feature-box">
                <div class="feature-box-icon">
                    <i class="fas fa-bolt"></i>
                </div>
                <h4>NVMe SSD</h4>
                <p>En hızlı disk teknolojisi ile maksimum performans.</p>
            </div>
            
            <div class="feature-box">
                <div class="feature-box-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <h4>Ücretsiz SSL</h4>
                <p>Tüm paketlerde ücretsiz SSL sertifikası.</p>
            </div>
            
            <div class="feature-box">
                <div class="feature-box-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h4>DDoS Koruması</h4>
                <p>Gelişmiş güvenlik altyapısı ile koruma.</p>
            </div>
            
            <div class="feature-box">
                <div class="feature-box-icon">
                    <i class="fas fa-headset"></i>
                </div>
                <h4>7/24 Destek</h4>
                <p>Uzman ekibimiz her an yanınızda.</p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>
