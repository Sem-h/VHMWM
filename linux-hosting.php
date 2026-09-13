<?php
/**
 * WHMVM - Linux Hosting Sayfası
 * Veritabanından "linux-hosting" grubundaki ürünleri çeker
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

// Sayfa değişkenleri
$pageTitle = 'Linux Hosting Paketleri';
$pageDescription = 'Güçlü Linux altyapısı ile yüksek performanslı hosting. cPanel, LiteSpeed ve ücretsiz SSL.';

// Grup slug'ı - bu sayfada "linux-hosting" grubundaki ürünleri göster
$groupSlug = 'linux-hosting';

// Grubu ve ürünleri veritabanından çek
try {
    // Önce grubu bul
    $group = Database::fetch("SELECT * FROM product_groups WHERE slug = ?", [$groupSlug]);
    
    if ($group) {
        // Gruba ait aktif ürünleri çek
        $products = Database::fetchAll(
            "SELECT * FROM products WHERE group_id = ? AND is_active = 1 ORDER BY order_priority, price_monthly",
            [$group['id']]
        );
    } else {
        $products = [];
    }
} catch (Exception $e) {
    $products = [];
    $group = null;
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
        radial-gradient(ellipse at 30% 50%, rgba(251, 146, 60, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 70% 30%, rgba(14, 165, 233, 0.1) 0%, transparent 40%);
}

.page-hero .container { position: relative; z-index: 1; }

.page-hero h1 {
    font-size: 48px;
    font-weight: 800;
    margin-bottom: 20px;
}

.page-hero h1 span {
    background: linear-gradient(135deg, #fb923c 0%, #f97316 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.page-hero p {
    font-size: 18px;
    color: var(--gray-light);
    max-width: 600px;
    margin: 0 auto 30px;
}

.hero-badges {
    display: flex;
    justify-content: center;
    gap: 15px;
    flex-wrap: wrap;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(251, 146, 60, 0.1);
    border: 1px solid rgba(251, 146, 60, 0.3);
    padding: 10px 20px;
    border-radius: 30px;
    color: #fb923c;
    font-size: 14px;
    font-weight: 600;
}

.hero-badge i {
    font-size: 16px;
}

/* Hosting Section */
.hosting-section {
    padding: 100px 0;
    background: var(--bg-body);
}

.hosting-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
}

.hosting-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: all 0.4s ease;
    position: relative;
}

.hosting-card:hover {
    transform: translateY(-10px);
    border-color: rgba(251, 146, 60, 0.3);
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
}

.hosting-card.popular {
    border-color: #fb923c;
    transform: scale(1.05);
}

.hosting-card.popular:hover {
    transform: scale(1.05) translateY(-10px);
}

.popular-badge {
    background: linear-gradient(135deg, #fb923c 0%, #f97316 100%);
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
    border-bottom: 1px solid var(--border-color);
}

.hosting-header .linux-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #fb923c 0%, #f97316 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    font-size: 28px;
    color: white;
}

.hosting-header h3 {
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 20px;
    color: var(--text-primary);
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
    color: #fb923c;
}

.hosting-price .amount {
    font-size: 48px;
    font-weight: 800;
    color: #fb923c;
}

.hosting-price .period {
    font-size: 14px;
    color: var(--text-muted);
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
    border-bottom: 1px solid var(--border-light);
    color: var(--text-secondary);
    font-size: 14px;
}

.hosting-features li:last-child {
    border-bottom: none;
}

.hosting-features li i {
    color: #10b981;
    font-size: 14px;
}

.hosting-features li .highlight {
    color: #fb923c;
    font-weight: 600;
}

.hosting-card .btn {
    width: 100%;
}

.btn-linux {
    background: linear-gradient(135deg, #fb923c 0%, #f97316 100%);
    color: white;
    box-shadow: 0 4px 20px rgba(251, 146, 60, 0.3);
}

.btn-linux:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(251, 146, 60, 0.4);
}

/* Tech Stack */
.tech-section {
    padding: 80px 0;
    background: var(--bg-secondary);
}

.tech-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 20px;
}

.tech-item {
    text-align: center;
    padding: 30px 20px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    transition: all 0.3s ease;
}

.tech-item:hover {
    border-color: rgba(251, 146, 60, 0.3);
    transform: translateY(-5px);
}

.tech-item img {
    height: 50px;
    margin-bottom: 15px;
    filter: grayscale(100%);
    opacity: 0.7;
    transition: all 0.3s;
}

.tech-item:hover img {
    filter: grayscale(0%);
    opacity: 1;
}

.tech-item span {
    display: block;
    color: var(--text-muted);
    font-size: 13px;
    font-weight: 600;
}

/* Features Grid */
.features-section {
    padding: 100px 0;
    background: var(--bg-body);
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 30px;
}

.feature-box {
    text-align: center;
    padding: 40px 25px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    transition: all 0.3s ease;
}

.feature-box:hover {
    border-color: rgba(251, 146, 60, 0.3);
    background: rgba(251, 146, 60, 0.05);
}

.feature-box-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, #fb923c 0%, #f97316 100%);
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
    color: var(--text-primary);
}

.feature-box p {
    color: var(--text-muted);
    font-size: 14px;
    line-height: 1.6;
}

/* CTA Section */
.cta-section {
    padding: 80px 0;
    background: linear-gradient(135deg, #fb923c 0%, #f97316 100%);
    text-align: center;
}

.cta-section h2 {
    font-size: 36px;
    font-weight: 800;
    color: white;
    margin-bottom: 15px;
}

.cta-section p {
    font-size: 18px;
    color: rgba(255,255,255,0.9);
    margin-bottom: 30px;
}

.cta-section .btn {
    background: white;
    color: #f97316;
    font-weight: 700;
}

.cta-section .btn:hover {
    background: #0f172a;
    color: white;
}

/* Responsive */
@media (max-width: 1200px) {
    .hosting-grid { grid-template-columns: repeat(2, 1fr); }
    .hosting-card.popular { transform: scale(1); }
    .hosting-card.popular:hover { transform: translateY(-10px); }
    .tech-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 768px) {
    .hosting-grid { grid-template-columns: 1fr; }
    .features-grid { grid-template-columns: repeat(2, 1fr); }
    .tech-grid { grid-template-columns: repeat(2, 1fr); }
    .page-hero h1 { font-size: 36px; }
    .hero-badges { flex-direction: column; align-items: center; }
}

@media (max-width: 576px) {
    .features-grid { grid-template-columns: 1fr; }
    .tech-grid { grid-template-columns: 1fr; }
}
</style>

<!-- Page Hero -->
<section class="page-hero">
    <div class="container">
        <h1><span>Linux</span> Hosting</h1>
        <p>Güçlü Linux altyapısı, LiteSpeed Web Server ve cPanel kontrol paneli ile yüksek performanslı hosting çözümleri.</p>
        
        <div class="hero-badges">
            <span class="hero-badge">
                <i class="fab fa-linux"></i>
                CloudLinux OS
            </span>
            <span class="hero-badge">
                <i class="fas fa-tachometer-alt"></i>
                LiteSpeed
            </span>
            <span class="hero-badge">
                <i class="fas fa-shield-alt"></i>
                Imunify360
            </span>
            <span class="hero-badge">
                <i class="fas fa-database"></i>
                cPanel
            </span>
        </div>
    </div>
</section>

<!-- Hosting Packages -->
<section class="hosting-section">
    <div class="container">
        <div class="section-header">
            <span class="section-badge" style="background: rgba(251, 146, 60, 0.1); color: #fb923c;">
                <i class="fab fa-linux"></i>
                Linux Hosting
            </span>
            <h2 class="section-title">Linux Hosting <span style="background: linear-gradient(135deg, #fb923c 0%, #f97316 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Paketleri</span></h2>
            <p class="section-desc">PHP, MySQL, WordPress ve tüm popüler CMS sistemleri için optimize edilmiş Linux hosting.</p>
        </div>
        
        <?php if (!empty($products)): ?>
        <div class="hosting-grid">
            <?php 
            $popularIndex = floor(count($products) / 3); // Popüler için orta paket
            foreach ($products as $index => $product): 
                $isPopular = ($index == $popularIndex && count($products) > 1);
                $price = (float)($product['price_monthly'] ?? 0);
                $priceInt = floor($price);
                
                // Ürün özelliklerini description'dan parse et (satır satır)
                $features = [];
                if (!empty($product['description'])) {
                    $lines = explode("\n", $product['description']);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (!empty($line)) {
                            $features[] = $line;
                        }
                    }
                }
            ?>
            <div class="hosting-card <?= $isPopular ? 'popular' : '' ?>">
                <?php if ($isPopular): ?>
                    <div class="popular-badge">En Popüler</div>
                <?php endif; ?>
                <div class="hosting-header">
                    <div class="linux-icon">
                        <i class="fab fa-linux"></i>
                    </div>
                    <h3><?= htmlspecialchars($product['name']) ?></h3>
                    <div class="hosting-price">
                        <span class="currency">₺</span>
                        <span class="amount"><?= number_format($priceInt, 0) ?></span>
                        <span class="period">/ay</span>
                    </div>
                </div>
                <div class="hosting-body">
                    <ul class="hosting-features">
                        <?php if (!empty($features)): ?>
                            <?php foreach (array_slice($features, 0, 6) as $feature): ?>
                                <li><i class="fas fa-check"></i> <?= htmlspecialchars($feature) ?></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li><i class="fas fa-check"></i> NVMe SSD Disk</li>
                            <li><i class="fas fa-check"></i> Ücretsiz SSL</li>
                            <li><i class="fas fa-check"></i> cPanel Kontrol Paneli</li>
                            <li><i class="fas fa-check"></i> LiteSpeed Web Server</li>
                            <li><i class="fas fa-check"></i> Günlük Yedekleme</li>
                            <li><i class="fas fa-check"></i> 7/24 Destek</li>
                        <?php endif; ?>
                    </ul>
                    <a href="sepet.php?add=<?= $product['id'] ?>" class="btn <?= $isPopular ? 'btn-linux' : 'btn-outline' ?>">
                        Sipariş Ver
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- Ürün yoksa varsayılan kartlar göster -->
        <div class="hosting-grid">
            <div class="hosting-card">
                <div class="hosting-header">
                    <div class="linux-icon"><i class="fab fa-linux"></i></div>
                    <h3>Starter</h3>
                    <div class="hosting-price">
                        <span class="currency">₺</span>
                        <span class="amount">39</span>
                        <span class="period">/ay</span>
                    </div>
                </div>
                <div class="hosting-body">
                    <ul class="hosting-features">
                        <li><i class="fas fa-check"></i> 10 GB NVMe SSD</li>
                        <li><i class="fas fa-check"></i> 100 GB Bant Genişliği</li>
                        <li><i class="fas fa-check"></i> 1 Web Sitesi</li>
                        <li><i class="fas fa-check"></i> Ücretsiz SSL</li>
                        <li><i class="fas fa-check"></i> cPanel</li>
                        <li><i class="fas fa-check"></i> LiteSpeed</li>
                    </ul>
                    <a href="sepet.php" class="btn btn-outline">Sipariş Ver</a>
                </div>
            </div>
            <div class="hosting-card popular">
                <div class="popular-badge">En Popüler</div>
                <div class="hosting-header">
                    <div class="linux-icon"><i class="fab fa-linux"></i></div>
                    <h3>Professional</h3>
                    <div class="hosting-price">
                        <span class="currency">₺</span>
                        <span class="amount">79</span>
                        <span class="period">/ay</span>
                    </div>
                </div>
                <div class="hosting-body">
                    <ul class="hosting-features">
                        <li><i class="fas fa-check"></i> 30 GB NVMe SSD</li>
                        <li><i class="fas fa-check"></i> 300 GB Bant Genişliği</li>
                        <li><i class="fas fa-check"></i> 10 Web Sitesi</li>
                        <li><i class="fas fa-check"></i> Ücretsiz SSL</li>
                        <li><i class="fas fa-check"></i> cPanel + Softaculous</li>
                        <li><i class="fas fa-check"></i> LiteSpeed + Redis</li>
                    </ul>
                    <a href="sepet.php" class="btn btn-linux">Sipariş Ver</a>
                </div>
            </div>
            <div class="hosting-card">
                <div class="hosting-header">
                    <div class="linux-icon"><i class="fab fa-linux"></i></div>
                    <h3>Business</h3>
                    <div class="hosting-price">
                        <span class="currency">₺</span>
                        <span class="amount">129</span>
                        <span class="period">/ay</span>
                    </div>
                </div>
                <div class="hosting-body">
                    <ul class="hosting-features">
                        <li><i class="fas fa-check"></i> 60 GB NVMe SSD</li>
                        <li><i class="fas fa-check"></i> Sınırsız Bant Genişliği</li>
                        <li><i class="fas fa-check"></i> Sınırsız Web Sitesi</li>
                        <li><i class="fas fa-check"></i> Wildcard SSL</li>
                        <li><i class="fas fa-check"></i> Imunify360</li>
                        <li><i class="fas fa-check"></i> JetBackup</li>
                    </ul>
                    <a href="sepet.php" class="btn btn-outline">Sipariş Ver</a>
                </div>
            </div>
            <div class="hosting-card">
                <div class="hosting-header">
                    <div class="linux-icon"><i class="fab fa-linux"></i></div>
                    <h3>Enterprise</h3>
                    <div class="hosting-price">
                        <span class="currency">₺</span>
                        <span class="amount">249</span>
                        <span class="period">/ay</span>
                    </div>
                </div>
                <div class="hosting-body">
                    <ul class="hosting-features">
                        <li><i class="fas fa-check"></i> 150 GB NVMe SSD</li>
                        <li><i class="fas fa-check"></i> Sınırsız Her Şey</li>
                        <li><i class="fas fa-check"></i> Premium SSL</li>
                        <li><i class="fas fa-check"></i> Özel IP Adresi</li>
                        <li><i class="fas fa-check"></i> Öncelikli Destek</li>
                        <li><i class="fas fa-check"></i> SSH Erişimi</li>
                    </ul>
                    <a href="sepet.php" class="btn btn-outline">Sipariş Ver</a>
                </div>
            </div>
        </div>
        
        <!-- Admin için bilgi mesajı -->
        <?php if (isset($_SESSION['admin_id'])): ?>
        <div style="margin-top: 30px; padding: 20px; background: rgba(251, 146, 60, 0.1); border: 1px solid rgba(251, 146, 60, 0.3); border-radius: 12px; text-align: center;">
            <p style="color: #fb923c; margin-bottom: 15px;">
                <i class="fas fa-info-circle"></i>
                <strong>Admin:</strong> Bu sayfa henüz veritabanından ürün çekemiyor.
            </p>
            <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 15px;">
                Ürünlerin burada görünmesi için:
            </p>
            <ol style="text-align: left; max-width: 500px; margin: 0 auto 20px; color: var(--text-secondary); font-size: 14px;">
                <li style="margin-bottom: 8px;"><a href="<?= SITE_URL ?>/admin/product-groups.php" style="color: #fb923c;">Ürün Grupları</a>'ndan <strong>"Linux Hosting"</strong> grubu oluşturun (slug: <code>linux-hosting</code>)</li>
                <li style="margin-bottom: 8px;"><a href="<?= SITE_URL ?>/admin/products.php" style="color: #fb923c;">Ürünler</a>'den ürün eklerken bu grubu seçin</li>
            </ol>
            <a href="<?= SITE_URL ?>/admin/product-groups.php" class="btn btn-linux btn-sm">
                <i class="fas fa-plus"></i> Grup Oluştur
            </a>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Tech Stack -->
<section class="tech-section">
    <div class="container">
        <div class="section-header">
            <span class="section-badge" style="background: rgba(251, 146, 60, 0.1); color: #fb923c;">
                <i class="fas fa-code"></i>
                Teknolojiler
            </span>
            <h2 class="section-title">Desteklenen <span style="background: linear-gradient(135deg, #fb923c 0%, #f97316 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Teknolojiler</span></h2>
        </div>
        
        <div class="tech-grid">
            <div class="tech-item">
                <i class="fab fa-php" style="font-size: 50px; color: #777BB4;"></i>
                <span>PHP 8.3</span>
            </div>
            <div class="tech-item">
                <i class="fab fa-node-js" style="font-size: 50px; color: #339933;"></i>
                <span>Node.js</span>
            </div>
            <div class="tech-item">
                <i class="fab fa-python" style="font-size: 50px; color: #3776AB;"></i>
                <span>Python</span>
            </div>
            <div class="tech-item">
                <i class="fas fa-database" style="font-size: 50px; color: #00758F;"></i>
                <span>MySQL 8.0</span>
            </div>
            <div class="tech-item">
                <i class="fab fa-wordpress" style="font-size: 50px; color: #21759B;"></i>
                <span>WordPress</span>
            </div>
            <div class="tech-item">
                <i class="fab fa-laravel" style="font-size: 50px; color: #FF2D20;"></i>
                <span>Laravel</span>
            </div>
        </div>
    </div>
</section>

<!-- Features -->
<section class="features-section">
    <div class="container">
        <div class="section-header">
            <span class="section-badge" style="background: rgba(251, 146, 60, 0.1); color: #fb923c;">
                <i class="fas fa-star"></i>
                Özellikler
            </span>
            <h2 class="section-title">Linux Hosting <span style="background: linear-gradient(135deg, #fb923c 0%, #f97316 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Avantajları</span></h2>
        </div>
        
        <div class="features-grid">
            <div class="feature-box">
                <div class="feature-box-icon">
                    <i class="fas fa-rocket"></i>
                </div>
                <h4>LiteSpeed Web Server</h4>
                <p>Apache'den 10 kat daha hızlı. HTTP/3 ve QUIC desteği.</p>
            </div>
            
            <div class="feature-box">
                <div class="feature-box-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h4>Imunify360</h4>
                <p>AI destekli güvenlik sistemi ile maksimum koruma.</p>
            </div>
            
            <div class="feature-box">
                <div class="feature-box-icon">
                    <i class="fas fa-cloud"></i>
                </div>
                <h4>CloudLinux OS</h4>
                <p>İzole kaynak kullanımı ile stabil performans.</p>
            </div>
            
            <div class="feature-box">
                <div class="feature-box-icon">
                    <i class="fas fa-sync"></i>
                </div>
                <h4>JetBackup</h4>
                <p>Otomatik günlük yedekleme ve tek tıkla geri yükleme.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <div class="container">
        <h2>Linux Hosting ile Başlayın</h2>
        <p>30 gün para iade garantisi ile risk almadan deneyin.</p>
        <a href="sepet.php?add=linux-pro" class="btn btn-lg">
            <i class="fas fa-shopping-cart"></i>
            Hemen Sipariş Ver
        </a>
    </div>
</section>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>

