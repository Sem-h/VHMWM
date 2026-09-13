<?php
/**
 * WHMVM - Hakkımızda
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Hakkımızda';
$pageDescription = 'WHMVM hakkında bilgi edinin. Misyonumuz, vizyonumuz ve değerlerimiz.';

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

.page-hero p {
    font-size: 20px;
    color: var(--gray-light);
    max-width: 600px;
    margin: 0 auto;
}

.about-section { padding: 80px 0; }

.about-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
}

.about-content h2 {
    font-size: 36px;
    margin-bottom: 20px;
}

.about-content p {
    color: var(--text-muted);
    line-height: 1.8;
    margin-bottom: 20px;
}

.about-image {
    background: var(--gradient-primary);
    border-radius: 20px;
    padding: 60px;
    text-align: center;
}

.about-image i {
    font-size: 120px;
    color: white;
}

.stats-section {
    background: rgba(99, 102, 241, 0.05);
    padding: 60px 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 30px;
}

.stat-item {
    text-align: center;
}

.stat-item .number {
    font-size: 48px;
    font-weight: 800;
    color: var(--primary-light);
}

.stat-item .label {
    color: var(--text-muted);
    margin-top: 10px;
}

.values-section { padding: 80px 0; }

.values-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
}

.value-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 40px;
    text-align: center;
}

.value-card i {
    font-size: 48px;
    color: var(--primary-light);
    margin-bottom: 20px;
}

.value-card h3 {
    font-size: 20px;
    margin-bottom: 15px;
}

.value-card p {
    color: var(--text-muted);
    line-height: 1.7;
}

@media (max-width: 992px) {
    .about-grid { grid-template-columns: 1fr; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .values-grid { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .page-hero h1 { font-size: 32px; }
    .stats-grid { grid-template-columns: 1fr 1fr; }
}
</style>

<section class="page-hero">
    <div class="container">
        <h1>Bizi Daha Yakından <span>Tanıyın</span></h1>
        <p>2010'dan bu yana güvenilir hosting ve sunucu hizmetleri sunuyoruz.</p>
    </div>
</section>

<section class="about-section">
    <div class="container">
        <div class="about-grid">
            <div class="about-content">
                <h2>Misyonumuz</h2>
                <p>Türkiye'nin önde gelen veri merkezi ve hosting şirketlerinden biri olarak, işletmelerin dijital dönüşüm yolculuğunda güvenilir bir partner olmayı hedefliyoruz.</p>
                <p>En son teknolojileri kullanarak, yüksek performanslı ve güvenli altyapı hizmetleri sunuyor, müşterilerimizin işlerini büyütmelerine yardımcı oluyoruz.</p>
                <p>7/24 teknik destek ekibimiz ile her zaman yanınızdayız.</p>
            </div>
            <div class="about-image">
                <i class="fas fa-server"></i>
            </div>
        </div>
    </div>
</section>

<section class="stats-section">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="number">15+</div>
                <div class="label">Yıllık Deneyim</div>
            </div>
            <div class="stat-item">
                <div class="number">50K+</div>
                <div class="label">Mutlu Müşteri</div>
            </div>
            <div class="stat-item">
                <div class="number">99.9%</div>
                <div class="label">Uptime</div>
            </div>
            <div class="stat-item">
                <div class="number">24/7</div>
                <div class="label">Destek</div>
            </div>
        </div>
    </div>
</section>

<section class="values-section">
    <div class="container">
        <h2 style="text-align: center; font-size: 36px; margin-bottom: 50px;">Değerlerimiz</h2>
        <div class="values-grid">
            <div class="value-card">
                <i class="fas fa-shield-alt"></i>
                <h3>Güvenlik</h3>
                <p>Müşteri verilerinin güvenliği bizim için en önemli önceliktir. En üst düzey güvenlik standartlarını uyguluyoruz.</p>
            </div>
            <div class="value-card">
                <i class="fas fa-bolt"></i>
                <h3>Performans</h3>
                <p>En son teknolojileri kullanarak yüksek performanslı hizmetler sunuyoruz. Hız ve kararlılık bizim işimiz.</p>
            </div>
            <div class="value-card">
                <i class="fas fa-heart"></i>
                <h3>Müşteri Odaklılık</h3>
                <p>Müşteri memnuniyeti her şeyin önünde gelir. Sorunlarınızı çözmek için buradayız.</p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>

