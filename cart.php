<?php
/**
 * WHMVM - Sepet & Sipariş Sayfası
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

// Sayfa değişkenleri
$pageTitle = 'Sepet';
$pageDescription = 'Sepetinizdeki ürünleri görüntüleyin ve siparişinizi tamamlayın.';

// Sepet işlemleri
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_GET['action'] ?? '';
$productSlug = $_GET['add'] ?? '';
$domain = $_GET['domain'] ?? '';

// Ürün ekle
if (!empty($productSlug)) {
    $products = [
        'hosting-starter' => ['name' => 'Hosting Starter', 'price' => 29, 'type' => 'hosting'],
        'hosting-pro' => ['name' => 'Hosting Professional', 'price' => 59, 'type' => 'hosting'],
        'hosting-business' => ['name' => 'Hosting Business', 'price' => 99, 'type' => 'hosting'],
        'hosting-enterprise' => ['name' => 'Hosting Enterprise', 'price' => 199, 'type' => 'hosting'],
        'ssl-dv' => ['name' => 'Domain SSL (DV)', 'price' => 99, 'type' => 'ssl'],
        'ssl-ov' => ['name' => 'Organization SSL (OV)', 'price' => 299, 'type' => 'ssl'],
        'ssl-ev' => ['name' => 'Extended SSL (EV)', 'price' => 799, 'type' => 'ssl'],
    ];
    
    if (isset($products[$productSlug])) {
        $_SESSION['cart'][$productSlug] = $products[$productSlug];
        $_SESSION['cart'][$productSlug]['qty'] = 1;
    }
}

// VDS yapılandırması
if ($productSlug === 'vds' || ($_GET['type'] ?? '') === 'vds') {
    $cpu = (int)($_GET['cpu'] ?? 2);
    $ram = (int)($_GET['ram'] ?? 4);
    $disk = (int)($_GET['disk'] ?? 50);
    $ip = (int)($_GET['ip'] ?? 1);
    $billing = (int)($_GET['billing'] ?? 1);

    // Fiyatlar vds_pricing tablosundan okunur; vds.php ile aynı kaynak.
    // Gömülü fiyat kullanıldığında sayfadaki tutar ile sepet tutarı tutmuyordu.
    $vdsRates = ['base' => 150.0, 'cpu' => 25.0, 'ram' => 25.0, 'disk' => 3.0, 'ip' => 25.0];
    try {
        foreach (Database::fetchAll("SELECT resource_type, unit_price FROM vds_pricing WHERE is_active = 1") as $r) {
            $vdsRates[$r['resource_type']] = (float)$r['unit_price'];
        }
    } catch (Exception $e) {
        // Varsayılan oranlarla devam
    }

    // İlk IPv4 ücretsiz
    $paidIp = max(0, $ip - 1);

    $total = $vdsRates['base']
        + ($cpu * $vdsRates['cpu'])
        + ($ram * $vdsRates['ram'])
        + ($disk * $vdsRates['disk'])
        + ($paidIp * $vdsRates['ip']);

    // Dönem indirimleri - vds.php ile aynı
    $discount = match($billing) {
        3 => 0.05,
        6 => 0.10,
        12 => 0.20,
        default => 0
    };
    $total = $total * (1 - $discount);

    $_SESSION['cart']['vds-custom'] = [
        'name' => "VDS Sunucu: {$cpu} vCPU, {$ram}GB RAM, {$disk}GB NVMe, {$ip} IPv4",
        'price' => round($total),
        'type' => 'vds',
        'qty' => 1,
        'config' => ['cpu' => $cpu, 'ram' => $ram, 'disk' => $disk, 'ip' => $ip, 'billing' => $billing]
    ];
}

// Domain ekle
if (!empty($domain)) {
    $_SESSION['cart']['domain-' . md5($domain)] = [
        'name' => 'Domain: ' . $domain,
        'price' => rand(99, 399),
        'type' => 'domain',
        'qty' => 1
    ];
}

// Sepetten kaldır
if ($action === 'remove' && isset($_GET['item'])) {
    $item = $_GET['item'];
    if (isset($_SESSION['cart'][$item])) {
        unset($_SESSION['cart'][$item]);
    }
    header('Location: cart.php');
    exit;
}

// Sepeti temizle
if ($action === 'clear') {
    $_SESSION['cart'] = [];
    header('Location: cart.php');
    exit;
}

// Toplam hesapla
$subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += (float)($item['total'] ?? $item['price'] ?? 0) * ($item['qty'] ?? 1);
}
$tax = $subtotal * 0.20;
$total = $subtotal + $tax;

// Header
require_once __DIR__ . '/theme/includes/header.php';
?>

<style>
/* Page Hero */
.page-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
    padding: 100px 0 60px;
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
    font-size: 42px;
    font-weight: 800;
    margin-bottom: 15px;
}

.page-hero h1 span {
    background: var(--gradient-primary);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.page-hero p {
    font-size: 18px;
    color: var(--gray-light);
}

/* Cart Section */
.cart-section {
    padding: 80px 0;
    background: var(--darker);
    min-height: 60vh;
}

.cart-grid {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 30px;
}

/* Cart Items */
.cart-items {
    background: rgba(255,255,255,0.02);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    overflow: hidden;
}

.cart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 25px 30px;
    background: rgba(99, 102, 241, 0.1);
    border-bottom: 1px solid var(--border);
}

.cart-header h2 {
    font-size: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.cart-header h2 i {
    color: var(--primary-light);
}

.clear-btn {
    color: var(--gray);
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: color 0.3s;
}

.clear-btn:hover {
    color: var(--danger);
}

/* Cart Item */
.cart-item {
    display: flex;
    align-items: center;
    padding: 25px 30px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    transition: all 0.3s ease;
}

.cart-item:hover {
    background: rgba(99, 102, 241, 0.05);
}

.cart-item:last-child {
    border-bottom: none;
}

.item-icon {
    width: 60px;
    height: 60px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
    margin-right: 20px;
    flex-shrink: 0;
}

.item-icon.hosting { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.item-icon.vds { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
.item-icon.ssl { background: linear-gradient(135deg, #10b981, #059669); }
.item-icon.domain { background: linear-gradient(135deg, #0ea5e9, #0284c7); }
.item-icon.default { background: linear-gradient(135deg, #64748b, #475569); }

.item-info {
    flex: 1;
}

.item-info h4 {
    font-size: 16px;
    margin-bottom: 5px;
}

.item-info .type {
    font-size: 13px;
    color: var(--gray);
    text-transform: capitalize;
}

.item-configs {
    margin-top: 8px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.config-tag {
    display: inline-block;
    padding: 4px 10px;
    background: rgba(99, 102, 241, 0.15);
    color: var(--primary-light);
    font-size: 12px;
    border-radius: 6px;
}

.item-price {
    font-size: 22px;
    font-weight: 700;
    color: var(--primary-light);
    margin-right: 20px;
}

.remove-btn {
    width: 42px;
    height: 42px;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: var(--radius-sm);
    color: var(--danger);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.remove-btn:hover {
    background: var(--danger);
    border-color: var(--danger);
    color: white;
}

/* Empty Cart */
.cart-empty {
    text-align: center;
    padding: 80px 30px;
}

.cart-empty-icon {
    width: 100px;
    height: 100px;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 25px;
}

.cart-empty-icon i {
    font-size: 40px;
    color: var(--primary-light);
}

.cart-empty h3 {
    font-size: 22px;
    margin-bottom: 10px;
}

.cart-empty p {
    color: var(--gray-light);
    margin-bottom: 25px;
}

/* Cart Summary */
.cart-summary {
    background: rgba(255,255,255,0.02);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 30px;
    height: fit-content;
    position: sticky;
    top: 100px;
}

.cart-summary h3 {
    font-size: 18px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.cart-summary h3 i {
    color: var(--primary-light);
}

/* Toplam Kutusu */
.summary-total-box {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05));
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    margin-bottom: 20px;
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.summary-total-box .total-amount {
    font-size: 28px;
    font-weight: 700;
    color: #6366f1;
    margin-bottom: 4px;
}

.summary-total-box .total-label {
    font-size: 13px;
    color: var(--gray);
}

/* Ürün Detayları */
.summary-product {
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.summary-product .product-name {
    font-size: 15px;
    font-weight: 600;
    color: #3b82f6;
    margin-bottom: 2px;
}

.summary-product .product-type {
    font-size: 13px;
    color: var(--gray);
    margin-bottom: 12px;
}

.summary-details {
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    overflow: hidden;
}

.summary-details .details-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    cursor: pointer;
    transition: background 0.2s;
}

.summary-details .details-toggle:hover {
    background: rgba(255,255,255,0.03);
}

.summary-details .details-toggle span {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: var(--text-primary, #fff);
}

.summary-details .details-toggle i:last-child {
    color: var(--gray);
    transition: transform 0.2s;
}

.summary-details.open .details-toggle i:last-child {
    transform: rotate(180deg);
}

.summary-details .details-content {
    display: none;
    padding: 0 16px 12px;
}

.summary-details.open .details-content {
    display: block;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 14px;
}

.detail-row span:first-child {
    color: var(--gray);
}

.detail-row span:last-child {
    color: var(--text-primary, #fff);
    font-weight: 500;
}

.detail-row.config span:first-child {
    padding-left: 12px;
    color: var(--gray-light);
}

.detail-row.config span:last-child {
    color: #10b981;
}

/* Summary Rows */
.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.summary-row:last-of-type {
    border-bottom: none;
}

.summary-row .label {
    color: var(--gray-light);
    font-size: 14px;
}

.summary-row .value {
    font-weight: 600;
    font-size: 14px;
}

.summary-row .value.green {
    color: #10b981;
}

.summary-row .value.blue {
    color: #3b82f6;
}

.summary-row.setup .label {
    color: #3b82f6;
    font-weight: 500;
}

.summary-row.total {
    margin-top: 15px;
    padding-top: 20px;
    border-top: 2px solid var(--primary);
    border-bottom: none;
}

.summary-row.total .label {
    font-size: 18px;
    font-weight: 700;
    color: white;
}

.summary-row.total .value {
    font-size: 28px;
    font-weight: 800;
    color: var(--primary-light);
}

/* Promo Code */
.promo-code {
    display: flex;
    gap: 10px;
    margin: 25px 0;
}

.promo-code input {
    flex: 1;
    padding: 14px 18px;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    color: white;
    font-size: 14px;
}

.promo-code input::placeholder {
    color: var(--gray);
}

.promo-code input:focus {
    outline: none;
    border-color: var(--primary);
}

.promo-code button {
    padding: 14px 20px;
    background: rgba(99, 102, 241, 0.2);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: var(--radius-md);
    color: var(--primary-light);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.promo-code button:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

/* Checkout Button */
.checkout-btn {
    width: 100%;
    padding: 18px;
    font-size: 16px;
    margin-bottom: 15px;
}

.checkout-btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.secure-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    color: var(--gray);
    font-size: 13px;
}

.secure-badge i {
    color: var(--success);
}

/* Responsive */
@media (max-width: 992px) {
    .cart-grid { grid-template-columns: 1fr; }
    .cart-summary { position: static; }
}

@media (max-width: 768px) {
    .page-hero h1 { font-size: 32px; }
    .cart-item { flex-wrap: wrap; gap: 15px; }
    .item-price { margin-right: 0; }
}
</style>

<!-- Page Hero -->
<section class="page-hero">
    <div class="container">
        <h1>Alışveriş <span>Sepetim</span></h1>
        <p>Sepetinizdeki ürünleri kontrol edin ve siparişinizi tamamlayın.</p>
    </div>
</section>

<!-- Cart Section -->
<section class="cart-section">
    <div class="container">
        <div class="cart-grid">
            <!-- Cart Items -->
            <div class="cart-items">
                <div class="cart-header">
                    <h2><i class="fas fa-shopping-cart"></i> Sepetim (<?= count($_SESSION['cart']) ?>)</h2>
                    <?php if (!empty($_SESSION['cart'])): ?>
                        <a href="cart.php?action=clear" class="clear-btn">
                            <i class="fas fa-trash"></i>
                            Sepeti Temizle
                        </a>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($_SESSION['cart'])): ?>
                    <div class="cart-empty">
                        <div class="cart-empty-icon">
                            <i class="fas fa-shopping-basket"></i>
                        </div>
                        <h3>Sepetiniz Boş</h3>
                        <p>Hizmetlerimizi inceleyerek sepetinize ürün ekleyebilirsiniz.</p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i>
                            Alışverişe Devam Et
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($_SESSION['cart'] as $key => $item): 
                        $itemType = $item['product_type'] ?? $item['type'] ?? 'other';
                        $iconClass = match($itemType) {
                            'hosting' => 'hosting',
                            'vds', 'vps', 'cloud' => 'vds',
                            'ssl' => 'ssl',
                            'domain' => 'domain',
                            default => 'default'
                        };
                        $icon = match($itemType) {
                            'hosting' => 'fa-globe',
                            'vds', 'vps', 'cloud' => 'fa-server',
                            'ssl' => 'fa-shield-alt',
                            'domain' => 'fa-link',
                            default => 'fa-box'
                        };
                        $itemName = $item['product_name'] ?? $item['name'] ?? 'Ürün';
                    ?>
                        <div class="cart-item">
                            <div class="item-icon <?= $iconClass ?>">
                                <i class="fas <?= $icon ?>"></i>
                            </div>
                            <div class="item-info">
                                <h4><?= htmlspecialchars($itemName) ?></h4>
                                <span class="type"><?= ucfirst($itemType) ?></span>
                                <?php if (!empty($item['config_options'])): ?>
                                    <div class="item-configs">
                                        <?php foreach ($item['config_options'] as $cfg): ?>
                                            <span class="config-tag">
                                                <?= htmlspecialchars($cfg['value_name']) ?>
                                                <?php if ($cfg['price'] > 0): ?>
                                                    (+<?= number_format((float)$cfg['price'], 0) ?> ₺)
                                                <?php endif; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="item-price">
                                <?= number_format((float)($item['total'] ?? $item['price'] ?? 0), 0, ',', '.') ?> ₺
                            </div>
                            <a href="cart.php?action=remove&item=<?= urlencode((string)$key) ?>" class="remove-btn">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Cart Summary -->
            <div class="cart-summary">
                <h3><i class="fas fa-receipt"></i> Sipariş Özeti</h3>
                
                <!-- Toplam -->
                <div class="summary-total-box">
                    <div class="total-amount"><?= number_format($total, 2) ?> TL</div>
                    <div class="total-label">Bugün Ödenmesi Gereken</div>
                </div>
                
                <!-- Ürün Detayları -->
                <?php foreach ($_SESSION['cart'] as $item): ?>
                <div class="summary-product">
                    <div class="product-name"><?= htmlspecialchars($item['product_name'] ?? $item['name'] ?? 'Ürün') ?></div>
                    <div class="product-type"><?= htmlspecialchars($item['product_type'] ?? 'Hizmet') ?></div>
                    
                    <div class="summary-details">
                        <div class="details-toggle" onclick="this.parentElement.classList.toggle('open')">
                            <span><i class="fas fa-search"></i> Detaylar</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="details-content">
                            <div class="detail-row">
                                <span><?= htmlspecialchars($item['product_name'] ?? 'Ürün') ?></span>
                                <span><?= number_format((float)($item['price'] ?? 0), 2) ?> TL</span>
                            </div>
                            <?php if (!empty($item['config_options'])): ?>
                                <?php foreach ($item['config_options'] as $cfg): ?>
                                <div class="detail-row config">
                                    <span><?= htmlspecialchars($cfg['value_name']) ?></span>
                                    <span><?= number_format((float)$cfg['price'], 2) ?> TL</span>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Kurulum Ücreti -->
                <div class="summary-row setup">
                    <span class="label">Kurulum Ücreti</span>
                    <span class="value green">0.00 TL</span>
                </div>
                
                <!-- Aylık Ücret -->
                <div class="summary-row">
                    <span class="label">Aylık Ücret</span>
                    <span class="value blue"><?= number_format($subtotal, 2) ?> TL</span>
                </div>
                
                <!-- KDV -->
                <div class="summary-row">
                    <span class="label">KDV @ 20.00%:</span>
                    <span class="value blue"><?= number_format($tax, 2) ?> TL</span>
                </div>
                
                <div class="promo-code">
                    <input type="text" placeholder="Promosyon kodu">
                    <button type="button">Uygula</button>
                </div>
                
                <?php if (!empty($_SESSION['cart'])): ?>
                    <?php if (isset($_SESSION['client_id'])): ?>
                        <a href="client/checkout.php" class="btn btn-primary checkout-btn">
                            <i class="fas fa-lock"></i>
                            Güvenli Ödemeye Geç
                        </a>
                    <?php else: ?>
                        <a href="client/index.php?redirect=checkout" class="btn btn-primary checkout-btn">
                            <i class="fas fa-sign-in-alt"></i>
                            Giriş Yap & Devam Et
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <button class="btn btn-primary checkout-btn disabled" disabled>
                        Sepet Boş
                    </button>
                <?php endif; ?>
                
                <div class="secure-badge">
                    <i class="fas fa-shield-alt"></i>
                    256-bit SSL ile güvenli ödeme
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>
