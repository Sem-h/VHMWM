<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';

session_name(SESSION_NAME);
session_start();

if (!isset($_SESSION['client_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Yeni Sipariş';
$pageIcon = 'fas fa-shopping-cart';
$currentPage = 'order';
$clientId = $_SESSION['client_id'];

$db = Database::getInstance();

// Ürün gruplarını ve ürünleri çek
$groups = $db->query("
    SELECT g.*, COUNT(p.id) as product_count 
    FROM product_groups g 
    LEFT JOIN products p ON g.id = p.group_id AND p.is_active = 1 AND p.is_hidden = 0
    WHERE g.is_hidden = 0 
    GROUP BY g.id 
    HAVING product_count > 0
    ORDER BY g.order_priority
")->fetchAll();

$selectedGroup = isset($_GET['group']) ? (int)$_GET['group'] : null;
$selectedType = $_GET['type'] ?? null;

// Ürünleri çek
$productsWhere = "WHERE p.is_active = 1 AND p.is_hidden = 0";
if ($selectedGroup) {
    $productsWhere .= " AND p.group_id = $selectedGroup";
}
if ($selectedType) {
    $productsWhere .= " AND p.type = '" . addslashes($selectedType) . "'";
}

$products = $db->query("
    SELECT p.*, g.name as group_name 
    FROM products p 
    LEFT JOIN product_groups g ON p.group_id = g.id 
    $productsWhere
    ORDER BY p.order_priority, p.name
")->fetchAll();

include 'includes/header.php';
?>

<style>
/* Order Page Styles */
.category-tabs {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 30px;
}

.category-tab {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 24px;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    color: var(--text-muted);
    text-decoration: none;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.3s;
}

.category-tab:hover {
    background: rgba(99, 102, 241, 0.1);
    border-color: var(--primary);
    color: var(--primary-light);
    transform: translateY(-2px);
}

.category-tab.active {
    background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
    border-color: transparent;
    color: #fff;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.category-tab .tab-icon {
    font-size: 18px;
}

/* Products Grid */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 25px;
}

/* Product Card */
.product-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
}

.product-card:hover {
    transform: translateY(-8px);
    border-color: var(--primary);
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
}

.product-card.featured {
    border-color: var(--primary);
    box-shadow: 0 0 30px rgba(99, 102, 241, 0.2);
}

.product-card.featured::before {
    content: '⭐ Popüler';
    position: absolute;
    top: 15px;
    right: 15px;
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #fff;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    z-index: 1;
}

.product-header {
    padding: 30px 25px 20px;
    text-align: center;
    position: relative;
}

.product-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
}

.product-icon.hosting { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); }
.product-icon.vps { background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); }
.product-icon.vds { background: linear-gradient(135deg, #ec4899 0%, #be185d 100%); }
.product-icon.dedicated { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
.product-icon.domain { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.product-icon.ssl { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
.product-icon.default { background: linear-gradient(135deg, #64748b 0%, #475569 100%); }

.product-name {
    font-size: 20px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 8px;
}

.product-group {
    font-size: 13px;
    color: var(--text-muted);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.05);
    padding: 5px 12px;
    border-radius: 20px;
}

.product-body {
    padding: 0 25px 25px;
}

.product-description {
    color: var(--text-muted);
    font-size: 14px;
    line-height: 1.7;
    margin-bottom: 20px;
    text-align: center;
}

/* Features List */
.features-list {
    list-style: none;
    padding: 0;
    margin: 0 0 25px;
}

.features-list li {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    font-size: 14px;
    color: var(--text-secondary);
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.features-list li:last-child {
    border-bottom: none;
}

.features-list li i {
    color: var(--success);
    font-size: 12px;
}

/* Pricing Box */
.pricing-box {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    padding: 25px;
    text-align: center;
    margin-bottom: 20px;
}

.price-main {
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: 5px;
    margin-bottom: 5px;
}

.price-currency {
    font-size: 20px;
    font-weight: 600;
    color: var(--primary-light);
}

.price-amount {
    font-size: 42px;
    font-weight: 800;
    color: #fff;
    line-height: 1;
}

.price-period {
    font-size: 16px;
    color: var(--text-muted);
}

.price-yearly {
    font-size: 14px;
    color: var(--text-muted);
    margin-top: 10px;
}

.price-yearly strong {
    color: var(--success);
}

.price-setup {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 12px;
    padding: 6px 14px;
    background: rgba(245, 158, 11, 0.1);
    border-radius: 20px;
    font-size: 13px;
    color: var(--warning);
}

.price-contact {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-muted);
}

/* Order Button */
.order-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
    border: none;
    border-radius: 12px;
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s;
    cursor: pointer;
}

.order-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
}

.order-btn i {
    transition: transform 0.3s;
}

.order-btn:hover i {
    transform: translateX(5px);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 80px 20px;
}

.empty-icon {
    width: 120px;
    height: 120px;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 30px;
    font-size: 60px;
}

.empty-state h3 {
    font-size: 24px;
    margin-bottom: 15px;
}

.empty-state p {
    color: var(--text-muted);
    font-size: 16px;
    margin-bottom: 25px;
}

/* Info Banner */
.info-banner {
    display: flex;
    align-items: center;
    gap: 20px;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
    border: 1px solid rgba(16, 185, 129, 0.2);
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 30px;
}

.info-banner-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    flex-shrink: 0;
}

.info-banner-content h4 {
    font-size: 18px;
    margin-bottom: 5px;
}

.info-banner-content p {
    color: var(--text-muted);
    font-size: 14px;
    margin: 0;
}

@media (max-width: 768px) {
    .category-tabs {
        overflow-x: auto;
        flex-wrap: nowrap;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
    
    .category-tab {
        flex-shrink: 0;
        padding: 12px 18px;
    }
    
    .products-grid {
        grid-template-columns: 1fr;
    }
    
    .info-banner {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<!-- Info Banner -->
<div class="info-banner">
    <div class="info-banner-icon">🚀</div>
    <div class="info-banner-content">
        <h4>Hizmetiniz Anında Aktif!</h4>
        <p>Siparişiniz tamamlandıktan sonra hizmetiniz otomatik olarak kurulur ve aktif edilir.</p>
    </div>
</div>

<!-- Category Tabs -->
<div class="category-tabs">
    <a href="order.php" class="category-tab <?= !$selectedGroup && !$selectedType ? 'active' : '' ?>">
        <span class="tab-icon">📦</span> Tümü
    </a>
    <a href="?type=hosting" class="category-tab <?= $selectedType === 'hosting' ? 'active' : '' ?>">
        <span class="tab-icon">🌐</span> Web Hosting
    </a>
    <a href="?type=vps" class="category-tab <?= $selectedType === 'vps' ? 'active' : '' ?>">
        <span class="tab-icon">💻</span> VPS Sunucu
    </a>
    <a href="?type=vds" class="category-tab <?= $selectedType === 'vds' ? 'active' : '' ?>">
        <span class="tab-icon">🖥️</span> VDS Sunucu
    </a>
    <a href="?type=dedicated" class="category-tab <?= $selectedType === 'dedicated' ? 'active' : '' ?>">
        <span class="tab-icon">🏢</span> Dedicated
    </a>
    <a href="?type=domain" class="category-tab <?= $selectedType === 'domain' ? 'active' : '' ?>">
        <span class="tab-icon">🔗</span> Domain
    </a>
    <a href="?type=ssl" class="category-tab <?= $selectedType === 'ssl' ? 'active' : '' ?>">
        <span class="tab-icon">🔒</span> SSL
    </a>
</div>

<?php if (empty($products)): ?>
    <!-- Empty State -->
    <div class="content-card">
        <div class="empty-state">
            <div class="empty-icon">📦</div>
            <h3>Ürün Bulunamadı</h3>
            <p>Bu kategoride henüz ürün bulunmuyor.</p>
            <a href="order.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Tüm Ürünleri Gör
            </a>
        </div>
    </div>
<?php else: ?>
    <!-- Products Grid -->
    <div class="products-grid">
        <?php foreach ($products as $index => $product): ?>
            <?php
            $typeIcon = match($product['type']) {
                'hosting' => '🌐',
                'vps' => '💻',
                'vds' => '🖥️',
                'dedicated' => '🏢',
                'domain' => '🔗',
                'ssl' => '🔒',
                default => '📦'
            };
            $typeClass = $product['type'] ?? 'default';
            $isFeatured = $index === 0 && !$selectedType; // İlk ürün popüler
            ?>
            <div class="product-card <?= $isFeatured ? 'featured' : '' ?>">
                <div class="product-header">
                    <div class="product-icon <?= $typeClass ?>">
                        <?= $typeIcon ?>
                    </div>
                    <h3 class="product-name"><?= htmlspecialchars($product['name']) ?></h3>
                    <span class="product-group">
                        <i class="fas fa-folder"></i>
                        <?= htmlspecialchars($product['group_name'] ?? ucfirst($product['type'])) ?>
                    </span>
                </div>
                
                <div class="product-body">
                    <?php if ($product['description']): ?>
                        <p class="product-description">
                            <?= htmlspecialchars(mb_substr($product['description'], 0, 100)) ?>
                            <?= mb_strlen($product['description']) > 100 ? '...' : '' ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($product['features']): 
                        $features = json_decode($product['features'], true);
                        if (is_array($features) && count($features) > 0):
                    ?>
                        <ul class="features-list">
                            <?php foreach (array_slice($features, 0, 5) as $feature): ?>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    <?= htmlspecialchars($feature) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; endif; ?>
                    
                    <div class="pricing-box">
                        <?php if ($product['price_monthly']): ?>
                            <div class="price-main">
                                <span class="price-amount"><?= number_format((float)$product['price_monthly'], 0) ?></span>
                                <span class="price-currency">₺</span>
                            </div>
                            <div class="price-period">/ aylık</div>
                            
                            <?php if ($product['price_annually']): ?>
                                <div class="price-yearly">
                                    Yıllık öde: <strong><?= number_format((float)$product['price_annually'], 0) ?> ₺</strong>
                                    <?php 
                                    $monthlyTotal = $product['price_monthly'] * 12;
                                    $savings = $monthlyTotal - $product['price_annually'];
                                    if ($savings > 0):
                                    ?>
                                        <span style="color: var(--success);">(<?= number_format($savings, 0) ?> ₺ tasarruf)</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($product['price_annually']): ?>
                            <div class="price-main">
                                <span class="price-amount"><?= number_format((float)$product['price_annually'], 0) ?></span>
                                <span class="price-currency">₺</span>
                            </div>
                            <div class="price-period">/ yıllık</div>
                        <?php else: ?>
                            <div class="price-contact">
                                <i class="fas fa-phone-alt"></i> Fiyat için iletişime geçin
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($product['setup_fee'] > 0): ?>
                            <div class="price-setup">
                                <i class="fas fa-tools"></i>
                                + <?= number_format((float)$product['setup_fee'], 0) ?> ₺ kurulum
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <a href="order-configure.php?id=<?= $product['id'] ?>" class="order-btn">
                        <i class="fas fa-shopping-cart"></i>
                        Sipariş Ver
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
