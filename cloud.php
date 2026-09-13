<?php
/**
 * WHMVM - Cloud Sunucu
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Cloud Sunucu';
$pageDescription = 'Esnek ve ölçeklenebilir cloud sunucu çözümleri.';

require_once __DIR__ . '/theme/includes/header.php';
?>

<style>
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
    background: radial-gradient(ellipse at 50% 50%, rgba(14, 165, 233, 0.15) 0%, transparent 50%);
}

.page-hero .container { position: relative; z-index: 1; }

.page-hero h1 {
    font-size: 48px;
    font-weight: 800;
    margin-bottom: 20px;
}

.page-hero h1 span {
    background: linear-gradient(135deg, #0ea5e9 0%, #06b6d4 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.page-hero p {
    font-size: 20px;
    color: var(--gray-light);
    max-width: 600px;
    margin: 0 auto 30px;
}

.features-section { padding: 80px 0; }

.features-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
}

.feature-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 40px;
    text-align: center;
    transition: all 0.3s;
}

.feature-card:hover {
    transform: translateY(-5px);
    border-color: #0ea5e9;
}

.feature-card i {
    font-size: 48px;
    color: #0ea5e9;
    margin-bottom: 20px;
}

.feature-card h3 {
    font-size: 20px;
    margin-bottom: 15px;
}

.feature-card p {
    color: var(--text-muted);
    line-height: 1.7;
}

.pricing-section {
    padding: 80px 0;
    background: rgba(14, 165, 233, 0.05);
}

.pricing-section h2 {
    text-align: center;
    font-size: 36px;
    margin-bottom: 50px;
}

.pricing-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
    max-width: 1200px;
    margin: 0 auto;
}

.price-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 35px;
    text-align: center;
    transition: all 0.3s;
}

.price-card:hover {
    transform: translateY(-5px);
    border-color: #0ea5e9;
}

.price-card.popular {
    border: 2px solid #0ea5e9;
    position: relative;
}

.price-card.popular::before {
    content: 'Popüler';
    position: absolute;
    top: -12px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #0ea5e9, #06b6d4);
    color: white;
    padding: 5px 20px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.price-card h3 {
    font-size: 22px;
    margin-bottom: 20px;
}

.price-card .price {
    font-size: 42px;
    font-weight: 800;
    color: #0ea5e9;
    margin-bottom: 5px;
}

.price-card .price span {
    font-size: 16px;
    color: var(--text-muted);
}

.price-card .specs {
    margin: 25px 0;
    text-align: left;
}

.price-card .specs li {
    padding: 10px 0;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    color: var(--text-muted);
}

.price-card .specs li:last-child {
    border-bottom: none;
}

.price-card .specs li strong {
    color: var(--text-primary);
}

.price-card .btn {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #0ea5e9, #06b6d4);
    border: none;
    border-radius: 10px;
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.price-card .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(14, 165, 233, 0.3);
}

@media (max-width: 992px) {
    .features-grid { grid-template-columns: repeat(2, 1fr); }
    .pricing-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .page-hero h1 { font-size: 32px; }
    .features-grid { grid-template-columns: 1fr; }
    .pricing-grid { grid-template-columns: 1fr; }
}
</style>

<section class="page-hero">
    <div class="container">
        <h1>Cloud <span>Sunucu</span></h1>
        <p>Esnek, ölçeklenebilir ve yüksek performanslı bulut altyapısı.</p>
        <a href="cart.php?add=cloud" class="btn btn-primary" style="background: linear-gradient(135deg, #0ea5e9, #06b6d4);">
            <i class="fas fa-cloud"></i> Hemen Başla
        </a>
    </div>
</section>

<section class="features-section">
    <div class="container">
        <div class="features-grid">
            <div class="feature-card">
                <i class="fas fa-expand-arrows-alt"></i>
                <h3>Anında Ölçekleme</h3>
                <p>İhtiyacınıza göre kaynakları anında artırın veya azaltın. Sadece kullandığınız kadar ödeyin.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-bolt"></i>
                <h3>NVMe SSD</h3>
                <p>Ultra hızlı NVMe SSD diskler ile maksimum okuma/yazma performansı.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-shield-alt"></i>
                <h3>DDoS Koruması</h3>
                <p>Gelişmiş DDoS koruma sistemi ile sunucunuz güvende.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-sync"></i>
                <h3>Otomatik Yedekleme</h3>
                <p>Günlük otomatik yedekleme ile verileriniz her zaman güvende.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-network-wired"></i>
                <h3>10 Gbps Network</h3>
                <p>Yüksek hızlı ağ bağlantısı ile kesintisiz veri transferi.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-headset"></i>
                <h3>7/24 Destek</h3>
                <p>Uzman teknik ekibimiz her zaman yanınızda.</p>
            </div>
        </div>
    </div>
</section>

<section class="pricing-section">
    <div class="container">
        <h2>Cloud Sunucu Paketleri</h2>
        <div class="pricing-grid">
            <div class="price-card">
                <h3>Cloud S</h3>
                <div class="price">199₺<span>/ay</span></div>
                <ul class="specs">
                    <li><span>vCPU</span><strong>2 Çekirdek</strong></li>
                    <li><span>RAM</span><strong>4 GB</strong></li>
                    <li><span>NVMe SSD</span><strong>50 GB</strong></li>
                    <li><span>Trafik</span><strong>2 TB</strong></li>
                </ul>
                <a href="cart.php?add=cloud-s" class="btn">Sipariş Ver</a>
            </div>
            <div class="price-card popular">
                <h3>Cloud M</h3>
                <div class="price">349₺<span>/ay</span></div>
                <ul class="specs">
                    <li><span>vCPU</span><strong>4 Çekirdek</strong></li>
                    <li><span>RAM</span><strong>8 GB</strong></li>
                    <li><span>NVMe SSD</span><strong>100 GB</strong></li>
                    <li><span>Trafik</span><strong>4 TB</strong></li>
                </ul>
                <a href="cart.php?add=cloud-m" class="btn">Sipariş Ver</a>
            </div>
            <div class="price-card">
                <h3>Cloud L</h3>
                <div class="price">599₺<span>/ay</span></div>
                <ul class="specs">
                    <li><span>vCPU</span><strong>8 Çekirdek</strong></li>
                    <li><span>RAM</span><strong>16 GB</strong></li>
                    <li><span>NVMe SSD</span><strong>200 GB</strong></li>
                    <li><span>Trafik</span><strong>8 TB</strong></li>
                </ul>
                <a href="cart.php?add=cloud-l" class="btn">Sipariş Ver</a>
            </div>
            <div class="price-card">
                <h3>Cloud XL</h3>
                <div class="price">999₺<span>/ay</span></div>
                <ul class="specs">
                    <li><span>vCPU</span><strong>16 Çekirdek</strong></li>
                    <li><span>RAM</span><strong>32 GB</strong></li>
                    <li><span>NVMe SSD</span><strong>400 GB</strong></li>
                    <li><span>Trafik</span><strong>Sınırsız</strong></li>
                </ul>
                <a href="cart.php?add=cloud-xl" class="btn">Sipariş Ver</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>
