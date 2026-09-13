<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
session_name(SESSION_NAME); session_start();

$pageTitle = 'Sipariş Yapılandırma';
$currentPage = 'order';
$db = Database::getInstance();

$productId = (int)($_GET['id'] ?? 0);
$product = Database::fetch(
    "SELECT p.*, g.name as group_name FROM products p 
     LEFT JOIN product_groups g ON p.group_id = g.id 
     WHERE p.id = ? AND p.is_active = 1", 
    [$productId]
);

if (!$product) { header('Location: order.php'); exit; }

// domain_required sütununu kontrol et
$domainRequired = false;
try {
    $columns = Database::fetchAll("SHOW COLUMNS FROM products LIKE 'domain_required'");
    if (!empty($columns)) {
        $domainRequired = (bool)($product['domain_required'] ?? false);
    }
} catch (Exception $e) {}

// Config options
$configOptions = [];
try {
    $linkedGroups = Database::fetchAll(
        "SELECT cog.* FROM config_option_groups cog 
         INNER JOIN product_config_links pcl ON cog.id = pcl.group_id 
         WHERE pcl.product_id = ?", [$productId]
    );
    foreach ($linkedGroups as $group) {
        $options = Database::fetchAll("SELECT * FROM config_options WHERE group_id = ? ORDER BY order_priority", [$group['id']]);
        foreach ($options as $option) {
            $option['values'] = Database::fetchAll("SELECT * FROM config_option_values WHERE option_id = ? ORDER BY order_priority", [$option['id']]);
            $configOptions[] = $option;
        }
    }
} catch (Exception $e) {}

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $billingCycle = $_POST['billing_cycle'] ?? 'monthly';
    $hostname = trim($_POST['hostname'] ?? '');
    $selectedConfigs = $_POST['config'] ?? [];
    
    // Domain zorunluluğu kontrolü
    if ($domainRequired && empty($hostname)) {
        $error = 'Bu ürün için hostname/domain alanı zorunludur.';
    }
    
    $price = match($billingCycle) {
        'monthly' => (float)($product['price_monthly'] ?? 0),
        'quarterly' => (float)($product['price_quarterly'] ?? 0),
        'semiannually' => (float)($product['price_semiannually'] ?? 0),
        'annually' => (float)($product['price_annually'] ?? 0),
        default => (float)($product['price_monthly'] ?? 0)
    };
    
    $configTotal = 0; $configDetails = [];
    foreach ($selectedConfigs as $optId => $valId) {
        if (empty($valId)) continue;
        $val = Database::fetch("SELECT v.*, o.name as opt_name FROM config_option_values v JOIN config_options o ON v.option_id = o.id WHERE v.id = ?", [(int)$valId]);
        if ($val) {
            $optPrice = (float)($val['price_monthly'] ?? 0);
            $configTotal += $optPrice;
            $configDetails[] = ['option_id' => $optId, 'value_id' => $valId, 'option_name' => $val['opt_name'], 'value_name' => $val['name'], 'price' => $optPrice];
        }
    }
    
    $setupFee = (float)($product['setup_fee'] ?? 0);
    
    // Hata yoksa sepete ekle
    if (!isset($error)) {
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        $_SESSION['cart'][] = [
            'product_id' => $product['id'], 'product_name' => $product['name'], 'product_type' => $product['type'],
            'domain' => $hostname, 'billing_cycle' => $billingCycle, 'price' => $price, 'setup_fee' => $setupFee,
            'config_options' => $configDetails, 'config_total' => $configTotal, 'total' => $price + $configTotal + $setupFee, 'added_at' => time()
        ];
        header('Location: /cart.php'); exit;
    }
}

$taxRate = 20;
try { $taxRate = (float)($db->query("SELECT setting_value FROM settings WHERE setting_key = 'tax_rate'")->fetchColumn() ?: 20); } catch (Exception $e) {}
$setupFee = (float)($product['setup_fee'] ?? 0);
$monthlyPrice = (float)($product['price_monthly'] ?? 0);

include 'includes/header.php';
?>

<style>
.configure-page { padding: 30px 0; background: #f8fafc; min-height: calc(100vh - 200px); }
[data-theme="dark"] .configure-page { background: var(--bg-secondary); }

.configure-container { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: grid; grid-template-columns: 1fr 340px; gap: 24px; }

/* Sol Panel */
.config-main { display: flex; flex-direction: column; gap: 20px; }

.config-card { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
[data-theme="dark"] .config-card { background: var(--bg-card); }

.config-card-header { background: linear-gradient(135deg, #1e40af, #3b82f6); padding: 20px 24px; }
.config-card-header h2 { color: #fff; font-size: 20px; font-weight: 600; margin: 0 0 6px 0; }
.config-card-header p { color: rgba(255,255,255,0.8); font-size: 14px; margin: 0; }

.config-card-body { padding: 24px; }

/* Billing Seçimi - Grid */
.billing-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 16px; margin-bottom: 20px; }

.billing-card {
    position: relative;
    background: #f8fafc;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px 16px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}
[data-theme="dark"] .billing-card { background: var(--bg-secondary); border-color: var(--border-color); }

.billing-card:hover { border-color: #3b82f6; }

.billing-card.selected,
.billing-card:has(input:checked) {
    border-color: #3b82f6;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.05), rgba(99, 102, 241, 0.05));
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.billing-card input { position: absolute; opacity: 0; }

.billing-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 12px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 20px;
}

.billing-label {
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
}
[data-theme="dark"] .billing-label { color: var(--text-muted); }

.billing-amount {
    font-size: 20px;
    font-weight: 700;
    color: #1f2937;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 10px 12px;
}
[data-theme="dark"] .billing-amount { background: var(--bg-card); border-color: var(--border-color); color: var(--text-primary); }

.billing-amount span { color: #3b82f6; font-size: 16px; }
.billing-price .current { font-size: 36px; font-weight: 700; color: #1f2937; }
.billing-price .current sup { font-size: 14px; font-weight: 400; }
.billing-price .current small { font-size: 14px; font-weight: 400; color: #6b7280; }
[data-theme="dark"] .billing-price .current { color: var(--text-primary); }

/* Input Grid */
.input-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
.input-group label { display: block; font-size: 13px; font-weight: 500; color: #6b7280; margin-bottom: 8px; }
[data-theme="dark"] .input-group label { color: var(--text-muted); }
.input-group input { width: 100%; padding: 12px 16px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px; color: #1f2937; background: #fff; }
[data-theme="dark"] .input-group input { background: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary); }
.input-group input:focus { outline: none; border-color: #2563eb; }

/* Config Options Card */
.config-options-card { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
[data-theme="dark"] .config-options-card { background: var(--bg-card); }

.config-options-header { background: linear-gradient(135deg, #1e40af, #3b82f6); padding: 14px 24px; }
.config-options-header h3 { color: #fff; font-size: 16px; font-weight: 500; margin: 0; }

.config-options-body { padding: 0; }

.config-option-row { display: flex; align-items: center; padding: 16px 24px; border-bottom: 1px solid #e5e7eb; cursor: pointer; transition: background 0.2s; }
[data-theme="dark"] .config-option-row { border-color: var(--border-color); }
.config-option-row:hover { background: #f9fafb; }
[data-theme="dark"] .config-option-row:hover { background: var(--bg-secondary); }
.config-option-row:last-child { border-bottom: none; }

.config-option-row input { display: none; }
.config-option-row .radio { width: 20px; height: 20px; border: 2px solid #d1d5db; border-radius: 50%; margin-right: 16px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: all 0.2s; }
.config-option-row.selected .radio { border-color: #2563eb; }
.config-option-row.selected .radio::after { content: ''; width: 10px; height: 10px; background: #2563eb; border-radius: 50%; }

.config-option-icon { width: 40px; height: 40px; margin-right: 14px; display: flex; align-items: center; justify-content: center; }
.config-option-icon img { width: 36px; height: 36px; object-fit: contain; }
.config-option-icon i { font-size: 24px; color: #6b7280; }

.config-option-info { flex: 1; }
.config-option-info .name { font-size: 15px; font-weight: 500; color: #1f2937; }
[data-theme="dark"] .config-option-info .name { color: var(--text-primary); }

.config-option-price { font-size: 15px; font-weight: 600; color: #1f2937; }
[data-theme="dark"] .config-option-price { color: var(--text-primary); }

.badge-free { background: #10b981; color: #fff; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 4px; text-transform: uppercase; }

/* Sağ Panel - Sipariş Özeti */
.order-summary { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); position: sticky; top: 100px; }
[data-theme="dark"] .order-summary { background: var(--bg-card); }

.summary-header { padding: 20px 24px; border-bottom: 1px solid #e5e7eb; }
[data-theme="dark"] .summary-header { border-color: var(--border-color); }
.summary-header h3 { font-size: 18px; font-weight: 600; color: #1f2937; margin: 0 0 16px 0; }
[data-theme="dark"] .summary-header h3 { color: var(--text-primary); }

.summary-total { font-size: 28px; font-weight: 700; color: #1f2937; margin-bottom: 4px; }
[data-theme="dark"] .summary-total { color: var(--text-primary); }
.summary-label { font-size: 13px; color: #6b7280; }

.summary-body { padding: 20px 24px; }

.summary-product { margin-bottom: 16px; }
.summary-product-name { font-size: 15px; font-weight: 600; color: #2563eb; margin-bottom: 2px; }
.summary-product-type { font-size: 13px; color: #6b7280; }

.summary-details { border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 16px; }
[data-theme="dark"] .summary-details { border-color: var(--border-color); }

.summary-details-toggle { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; cursor: pointer; }
.summary-details-toggle span { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #1f2937; }
[data-theme="dark"] .summary-details-toggle span { color: var(--text-primary); }
.summary-details-toggle i { color: #6b7280; transition: transform 0.2s; }
.summary-details.open .summary-details-toggle i { transform: rotate(180deg); }

.summary-details-content { padding: 0 16px 12px; display: none; }
.summary-details.open .summary-details-content { display: block; }

.summary-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; }
.summary-row .label { color: #6b7280; }
.summary-row .value { color: #1f2937; font-weight: 500; }
[data-theme="dark"] .summary-row .value { color: var(--text-primary); }

.summary-row.setup .label { color: #2563eb; font-weight: 500; }
.summary-row.setup .value { color: #10b981; font-weight: 600; }

.summary-row.monthly .label { color: #1f2937; font-weight: 500; }
[data-theme="dark"] .summary-row.monthly .label { color: var(--text-primary); }
.summary-row.monthly .value { color: #2563eb; font-weight: 600; }

.summary-row.tax .label { color: #1f2937; font-weight: 500; }
[data-theme="dark"] .summary-row.tax .label { color: var(--text-primary); }
.summary-row.tax .value { color: #2563eb; font-weight: 600; }

.summary-footer { padding: 0 24px 24px; }

.btn-continue { width: 100%; padding: 14px 24px; background: #f97316; border: none; border-radius: 6px; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.2s; }
.btn-continue:hover { background: #ea580c; }

@media (max-width: 900px) {
    .configure-container { grid-template-columns: 1fr; }
    .order-summary { position: static; }
    .input-grid { grid-template-columns: 1fr; }
}
</style>

<div class="configure-page">
    <div class="configure-container">
        <form method="POST" id="configForm" class="config-main">
            
            <!-- Ana Kart -->
            <div class="config-card">
                <div class="config-card-header">
                    <h2><?= htmlspecialchars($product['name']) ?></h2>
                    <p>Lütfen hizmetinizle ilgili seçenekleri yapılandırın ve ödeme işlemine devam edin.</p>
                            </div>
                <div class="config-card-body">
                    <!-- Billing Seçimi -->
                    <?php
                    // Aylık fiyatı olmayan ürünlerde (yıllık SSL gibi) ilk mevcut dönem seçili gelir
                    $ilkDongu = null;
                    foreach (['monthly', 'quarterly', 'semiannually', 'annually'] as $d) {
                        if ((float)($product['price_' . $d] ?? 0) > 0) { $ilkDongu = $d; break; }
                    }
                    ?>
                    <div class="billing-grid">
                        <?php if (($product['price_monthly'] ?? 0) > 0): ?>
                        <label class="billing-card <?= !($product['price_quarterly'] ?? 0) && !($product['price_semiannually'] ?? 0) && !($product['price_annually'] ?? 0) ? 'selected' : 'selected' ?>">
                            <input type="radio" name="billing_cycle" value="monthly"<?= $ilkDongu === 'monthly' ? ' checked' : '' ?> data-price="<?= (float)$product['price_monthly'] ?>">
                            <div class="billing-icon"><i class="fas fa-calendar-day"></i></div>
                            <div class="billing-label">AYLIK</div>
                            <div class="billing-amount"><?= number_format((float)$product['price_monthly'], 2, ',', '.') ?> <span>₺</span></div>
                        </label>
                        <?php endif; ?>
                        
                        <?php if (($product['price_quarterly'] ?? 0) > 0): ?>
                        <label class="billing-card">
                            <input type="radio" name="billing_cycle" value="quarterly"<?= $ilkDongu === 'quarterly' ? ' checked' : '' ?> data-price="<?= (float)$product['price_quarterly'] ?>">
                            <div class="billing-icon"><i class="fas fa-calendar-week"></i></div>
                            <div class="billing-label">3 AYLIK</div>
                            <div class="billing-amount"><?= number_format((float)$product['price_quarterly'], 2, ',', '.') ?> <span>₺</span></div>
                        </label>
                        <?php endif; ?>
                        
                        <?php if (($product['price_semiannually'] ?? 0) > 0): ?>
                        <label class="billing-card">
                            <input type="radio" name="billing_cycle" value="semiannually"<?= $ilkDongu === 'semiannually' ? ' checked' : '' ?> data-price="<?= (float)$product['price_semiannually'] ?>">
                            <div class="billing-icon"><i class="fas fa-calendar-alt"></i></div>
                            <div class="billing-label">6 AYLIK</div>
                            <div class="billing-amount"><?= number_format((float)$product['price_semiannually'], 2, ',', '.') ?> <span>₺</span></div>
                        </label>
                        <?php endif; ?>
                        
                        <?php if (($product['price_annually'] ?? 0) > 0): ?>
                        <label class="billing-card">
                            <input type="radio" name="billing_cycle" value="annually"<?= $ilkDongu === 'annually' ? ' checked' : '' ?> data-price="<?= (float)$product['price_annually'] ?>">
                            <div class="billing-icon"><i class="fas fa-calendar"></i></div>
                            <div class="billing-label">YILLIK</div>
                            <div class="billing-amount"><?= number_format((float)$product['price_annually'], 2, ',', '.') ?> <span>₺</span></div>
                        </label>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Error Mesajı -->
                    <?php if (isset($error)): ?>
                    <div class="error-alert" style="background: #fee2e2; border: 1px solid #fecaca; color: #dc2626; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
                    <?php endif; ?>
                    
                    <!-- Hostname / Domain -->
                    <?php if ($domainRequired): ?>
                    <div class="input-grid" style="grid-template-columns: 1fr;">
                        <div class="input-group">
                            <label>Domain <span style="color: #dc2626;">*</span></label>
                            <input type="text" name="hostname" placeholder="ornek.com veya subdomain.ornek.com" required value="<?= htmlspecialchars($_POST['hostname'] ?? '') ?>">
                            <small style="color: #dc2626; font-size: 12px; margin-top: 4px; display: block;">Bu alan zorunludur</small>
                        </div>
                    </div>
                    <?php elseif (in_array($product['type'], ['hosting', 'vps', 'vds', 'dedicated'])): ?>
                    <div class="input-grid">
                        <div class="input-group">
                            <label>Hostname</label>
                            <input type="text" name="hostname" placeholder="server.firmaadi.com" value="<?= htmlspecialchars($_POST['hostname'] ?? '') ?>">
                        </div>
                        <div class="input-group">
                            <label>Root Şifresi</label>
                            <input type="password" name="root_password" placeholder="••••••••">
                        </div>
                    </div>
                    <?php endif; ?>
        </div>
    </div>
            
            <!-- Config Options -->
            <?php foreach ($configOptions as $option): ?>
            <div class="config-options-card">
                <div class="config-options-header">
                    <h3><?= htmlspecialchars($option['name']) ?> İhtiyacınız Var mı?</h3>
                </div>
                <div class="config-options-body">
                    <!-- Varsayılan - İstemiyorum -->
                    <label class="config-option-row selected" onclick="selectConfigOption(this)">
                        <input type="radio" name="config[<?= $option['id'] ?>]" value="" checked data-price="0">
                        <span class="radio"></span>
                        <div class="config-option-icon">
                            <i class="fas fa-memory"></i>
                        </div>
                        <div class="config-option-info">
                            <div class="name">Ek <?= htmlspecialchars($option['name']) ?> İstemiyorum</div>
                        </div>
                        <span class="badge-free">ÜCRETSİZ</span>
                    </label>
                    
                    <?php foreach ($option['values'] as $val): ?>
                    <label class="config-option-row" onclick="selectConfigOption(this)">
                        <input type="radio" name="config[<?= $option['id'] ?>]" value="<?= $val['id'] ?>" 
                               data-price="<?= (float)($val['price_monthly'] ?? 0) ?>" 
                               data-name="<?= htmlspecialchars($val['name']) ?>">
                        <span class="radio"></span>
                        <div class="config-option-icon">
                            <i class="fas fa-memory"></i>
                        </div>
                        <div class="config-option-info">
                            <div class="name"><?= htmlspecialchars($val['name']) ?></div>
                        </div>
                        <div class="config-option-price"><?= number_format((float)($val['price_monthly'] ?? 0), 2) ?> TL</div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
        </form>
    
    <!-- Sipariş Özeti -->
        <div class="order-summary">
            <div class="summary-header">
                <h3>Sipariş Özeti</h3>
                <div class="summary-total" id="summaryTotal"><?= number_format($monthlyPrice * (1 + $taxRate/100), 2) ?> TL</div>
                <div class="summary-label">Bugün Ödenmesi Gereken</div>
            </div>
            
            <div class="summary-body">
                <div class="summary-product">
                    <div class="summary-product-name"><?= htmlspecialchars($product['name']) ?></div>
                    <div class="summary-product-type"><?= htmlspecialchars($product['group_name'] ?? ucfirst($product['type'])) ?></div>
                </div>
                
                <div class="summary-details open">
                    <div class="summary-details-toggle" onclick="toggleDetails(this)">
                        <span><i class="fas fa-search"></i> Detaylar</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="summary-details-content">
                        <div class="summary-row">
                            <span class="label"><?= htmlspecialchars($product['name']) ?></span>
                            <span class="value" id="detailProduct"><?= number_format($monthlyPrice, 2) ?> TL</span>
                        </div>
                        <div id="detailConfigs"></div>
                    </div>
                </div>
                
                <div class="summary-row setup">
                    <span class="label">Kurulum Ücreti</span>
                    <span class="value"><?= number_format($setupFee, 2) ?> TL</span>
                </div>
                
                <div class="summary-row monthly">
                    <span class="label">Aylık Ücret</span>
                    <span class="value" id="summaryMonthly"><?= number_format($monthlyPrice, 2) ?> TL</span>
                </div>
                
                <div class="summary-row tax">
                    <span class="label">KDV @ <?= $taxRate ?>.00%:</span>
                    <span class="value" id="summaryTax"><?= number_format($monthlyPrice * $taxRate / 100, 2) ?> TL</span>
                </div>
                </div>
                
            <div class="summary-footer">
                <button type="submit" form="configForm" class="btn-continue">
                    Devam Et <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let basePrice = <?= $monthlyPrice ?>;
const setupFee = <?= $setupFee ?>;
const taxRate = <?= $taxRate ?>;

// Billing card seçimi
document.querySelectorAll('.billing-card').forEach(card => {
    card.addEventListener('click', function() {
        document.querySelectorAll('.billing-card').forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');
        const input = this.querySelector('input');
        input.checked = true;
        basePrice = parseFloat(input.dataset.price) || 0;
        updateSummary();
    });
});

function selectConfigOption(el) {
    const parent = el.closest('.config-options-body');
    parent.querySelectorAll('.config-option-row').forEach(r => r.classList.remove('selected'));
    el.classList.add('selected');
    el.querySelector('input').checked = true;
    updateSummary();
}

function toggleDetails(el) {
    el.closest('.summary-details').classList.toggle('open');
}

function updateSummary() {
    let configTotal = 0;
    let configHtml = '';
    
    // Seçili billing cycle
    const billingInput = document.querySelector('input[name="billing_cycle"]:checked');
    const billingCycle = billingInput ? billingInput.value : 'monthly';
    
    const cycleLabels = {
        'monthly': { label: 'Aylık Ücret', period: '/ay' },
        'quarterly': { label: '3 Aylık Ücret', period: '/3 ay' },
        'semiannually': { label: '6 Aylık Ücret', period: '/6 ay' },
        'annually': { label: 'Yıllık Ücret', period: '/yıl' }
    };
    const cycleInfo = cycleLabels[billingCycle] || cycleLabels['monthly'];
    
    document.querySelectorAll('.config-option-row.selected input').forEach(input => {
        if (input.value) {
            const price = parseFloat(input.dataset.price || 0);
            const name = input.dataset.name;
            if (price > 0) {
                configTotal += price;
                configHtml += `<div class="summary-row"><span class="label">${name}</span><span class="value">${price.toFixed(2)} TL</span></div>`;
            }
        }
    });
    
    document.getElementById('detailConfigs').innerHTML = configHtml;
    
    const subtotal = basePrice + configTotal;
    const tax = subtotal * (taxRate / 100);
    const total = subtotal + tax + setupFee;
    
    document.getElementById('detailProduct').textContent = basePrice.toFixed(2) + ' TL';
    document.getElementById('summaryMonthly').textContent = subtotal.toFixed(2) + ' TL';
    document.getElementById('summaryTax').textContent = tax.toFixed(2) + ' TL';
    document.getElementById('summaryTotal').textContent = total.toFixed(2) + ' TL';
    
    // Dönem label'ını güncelle
    const monthlyLabel = document.querySelector('.summary-row.monthly .label');
    if (monthlyLabel) monthlyLabel.textContent = cycleInfo.label;
}

updateSummary();
</script>

<?php include 'includes/footer.php'; ?>
