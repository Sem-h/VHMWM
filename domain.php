<?php
/**
 * WHMVM - Domain Sorgulama & Kayıt Sayfası
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

// Sayfa değişkenleri
$pageTitle = 'Domain Kayıt & Transfer';
$pageDescription = 'Domain kaydı, transfer ve yenileme hizmetleri. .com, .net, .com.tr ve daha fazlası.';

$query = $_GET['query'] ?? '';
$results = [];

// Domain fiyatlarını çek
try {
    $domainPricing = Database::fetchAll("SELECT * FROM domain_pricing ORDER BY extension ASC");
} catch (Exception $e) {
    $domainPricing = [];
}

// Domain sorgusu yapıldıysa
if (!empty($query)) {
    $domain = strtolower(trim($query));
    $domain = preg_replace('/[^a-z0-9\-]/', '', $domain);
    
    $extensions = ['.com', '.net', '.org', '.com.tr', '.io', '.tech', '.store'];
    
    foreach ($extensions as $ext) {
        $fullDomain = $domain . $ext;
        $results[] = [
            'domain' => $fullDomain,
            'available' => (rand(0, 10) > 3),
            'price' => rand(50, 500)
        ];
    }
}

// Header
require_once __DIR__ . '/theme/includes/header.php';
?>

<style>
/* Page Hero */
.page-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
    padding: 120px 0 100px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.page-hero::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: 
        radial-gradient(ellipse at 30% 50%, rgba(14, 165, 233, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 70% 30%, rgba(99, 102, 241, 0.1) 0%, transparent 40%);
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
    font-size: 18px;
    color: var(--gray-light);
    max-width: 600px;
    margin: 0 auto 40px;
}

/* Domain Search */
.domain-search-box {
    max-width: 700px;
    margin: 0 auto;
    display: flex;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border);
    border-radius: 60px;
    padding: 8px;
    backdrop-filter: blur(10px);
}

.domain-search-box input {
    flex: 1;
    border: none;
    background: transparent;
    padding: 18px 25px;
    font-size: 16px;
    color: white;
    outline: none;
}

.domain-search-box input::placeholder {
    color: var(--gray);
}

.domain-search-box button {
    padding: 18px 35px;
    border-radius: 50px;
}

/* Domain Results */
.results-section {
    padding: 80px 0;
    background: var(--darker);
}

.results-list {
    max-width: 800px;
    margin: 0 auto;
}

.result-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 25px 30px;
    background: rgba(255,255,255,0.02);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.result-item:hover {
    border-color: rgba(99, 102, 241, 0.3);
    background: rgba(99, 102, 241, 0.05);
}

.result-item.available {
    border-color: rgba(16, 185, 129, 0.3);
}

.result-item.taken {
    opacity: 0.6;
}

.result-domain {
    display: flex;
    align-items: center;
    gap: 15px;
}

.result-domain i {
    font-size: 24px;
}

.result-item.available .result-domain i {
    color: var(--success);
}

.result-item.taken .result-domain i {
    color: var(--danger);
}

.result-domain h4 {
    font-size: 18px;
    margin-bottom: 3px;
}

.result-domain span {
    font-size: 13px;
    color: var(--gray);
}

.result-domain span.available {
    color: var(--success);
}

.result-domain span.taken {
    color: var(--danger);
}

.result-price {
    text-align: right;
}

.result-price .price {
    font-size: 22px;
    font-weight: 700;
    color: var(--primary-light);
}

.result-price .period {
    font-size: 13px;
    color: var(--gray);
}

/* Domain Pricing */
.pricing-section {
    padding: 100px 0;
    background: var(--dark);
}

.pricing-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
}

.price-card {
    background: rgba(255,255,255,0.02);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 30px;
    text-align: center;
    transition: all 0.3s ease;
}

.price-card:hover {
    border-color: rgba(99, 102, 241, 0.3);
    transform: translateY(-5px);
}

.price-card .extension {
    font-size: 28px;
    font-weight: 800;
    margin-bottom: 15px;
    color: var(--primary-light);
}

.price-card .amount {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 5px;
}

.price-card .period {
    font-size: 14px;
    color: var(--gray);
    margin-bottom: 20px;
}

.price-card .btn {
    width: 100%;
}

/* Features */
.features-section {
    padding: 100px 0;
    background: var(--darker);
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 30px;
}

.feature-box {
    text-align: center;
    padding: 40px 25px;
}

.feature-icon {
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
@media (max-width: 992px) {
    .pricing-grid { grid-template-columns: repeat(2, 1fr); }
    .features-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .page-hero h1 { font-size: 36px; }
    .domain-search-box { flex-direction: column; border-radius: 20px; }
    .domain-search-box button { width: 100%; border-radius: 15px; }
    .result-item { flex-direction: column; text-align: center; gap: 20px; }
}

@media (max-width: 576px) {
    .pricing-grid { grid-template-columns: 1fr; }
    .features-grid { grid-template-columns: 1fr; }
}
</style>

<!-- Page Hero -->
<section class="page-hero">
    <div class="container">
        <h1>Domain <span>Kayıt</span> & Transfer</h1>
        <p>Markanızı koruma altına alın. Binlerce uzantı seçeneği ile hayalinizdeki domain'i şimdi kaydettirin.</p>
        
        <form class="domain-search-box" action="domain.php" method="GET">
            <input type="text" name="query" placeholder="Hayalinizdeki domain adını yazın..." value="<?= htmlspecialchars($query) ?>">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i>
                Sorgula
            </button>
        </form>
    </div>
</section>

<?php if (!empty($results)): ?>
<!-- Results -->
<section class="results-section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">"<span><?= htmlspecialchars($query) ?></span>" Sonuçları</h2>
        </div>
        
        <div class="results-list">
            <?php foreach ($results as $result): ?>
                <div class="result-item <?= $result['available'] ? 'available' : 'taken' ?>">
                    <div class="result-domain">
                        <i class="fas <?= $result['available'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                        <div>
                            <h4><?= htmlspecialchars($result['domain']) ?></h4>
                            <span class="<?= $result['available'] ? 'available' : 'taken' ?>">
                                <?= $result['available'] ? 'Müsait' : 'Kayıtlı' ?>
                            </span>
                        </div>
                    </div>
                    <div class="result-price">
                        <?php if ($result['available']): ?>
                            <div class="price">₺<?= number_format($result['price'], 0) ?></div>
                            <div class="period">/yıl</div>
                            <a href="cart.php?add=domain&domain=<?= urlencode($result['domain']) ?>" class="btn btn-primary btn-sm" style="margin-top: 10px;">
                                <i class="fas fa-cart-plus"></i> Sepete Ekle
                            </a>
                        <?php else: ?>
                            <a href="#" class="btn btn-outline btn-sm">
                                <i class="fas fa-exchange-alt"></i> Transfer
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Domain Pricing -->
<section class="pricing-section">
    <div class="container">
        <div class="section-header">
            <span class="section-badge">
                <i class="fas fa-tags"></i>
                Fiyatlar
            </span>
            <h2 class="section-title">Domain <span>Fiyatları</span></h2>
            <p class="section-desc">En popüler uzantılar için güncel fiyatlar.</p>
        </div>
        
        <div class="pricing-grid">
            <div class="price-card">
                <div class="extension">.com</div>
                <div class="amount">₺149</div>
                <div class="period">/yıl</div>
                <a href="domain.php?query=example" class="btn btn-outline btn-sm">
                    Kaydet
                </a>
            </div>
            
            <div class="price-card">
                <div class="extension">.net</div>
                <div class="amount">₺179</div>
                <div class="period">/yıl</div>
                <a href="domain.php?query=example" class="btn btn-outline btn-sm">
                    Kaydet
                </a>
            </div>
            
            <div class="price-card">
                <div class="extension">.com.tr</div>
                <div class="amount">₺99</div>
                <div class="period">/yıl</div>
                <a href="domain.php?query=example" class="btn btn-outline btn-sm">
                    Kaydet
                </a>
            </div>
            
            <div class="price-card">
                <div class="extension">.io</div>
                <div class="amount">₺399</div>
                <div class="period">/yıl</div>
                <a href="domain.php?query=example" class="btn btn-outline btn-sm">
                    Kaydet
                </a>
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
                Avantajlar
            </span>
            <h2 class="section-title">Domain <span>Hizmetleri</span></h2>
        </div>
        
        <div class="features-grid">
            <div class="feature-box">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h4>WHOIS Koruması</h4>
                <p>Kişisel bilgilerinizi gizli tutun.</p>
            </div>
            
            <div class="feature-box">
                <div class="feature-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <h4>Transfer Kilidi</h4>
                <p>Yetkisiz transferlere karşı koruma.</p>
            </div>
            
            <div class="feature-box">
                <div class="feature-icon">
                    <i class="fas fa-sync-alt"></i>
                </div>
                <h4>Otomatik Yenileme</h4>
                <p>Domain'inizi asla kaybetmeyin.</p>
            </div>
            
            <div class="feature-box">
                <div class="feature-icon">
                    <i class="fas fa-headset"></i>
                </div>
                <h4>7/24 Destek</h4>
                <p>Her zaman yanınızdayız.</p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>
