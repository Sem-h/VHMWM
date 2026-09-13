<?php
/**
 * WHMVM - Yeni Ürün Ekleme (Wizard)
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
session_name(SESSION_NAME); session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Yeni Ürün Ekle';
$currentPage = 'products';
$db = Database::getInstance();

// Ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $groupId = $_POST['group_id'] ?: null;
    
    $type = 'other';
    if ($groupId) {
        $groupType = Database::fetchColumn("SELECT type FROM product_groups WHERE id = ?", [$groupId]);
        if ($groupType) {
            $type = $groupType;
        }
    }
    
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $_POST['name']));
    $existingSlug = Database::fetchColumn("SELECT id FROM products WHERE slug = ?", [$slug]);
    if ($existingSlug) {
        $slug .= '-' . time();
    }
    
    // domain_required sütununu kontrol et ve ekle
    $columns = Database::fetchAll("SHOW COLUMNS FROM products LIKE 'domain_required'");
    if (empty($columns)) {
        Database::query("ALTER TABLE products ADD COLUMN domain_required TINYINT(1) DEFAULT 0");
    }
    
    Database::query(
        "INSERT INTO products (name, slug, type, group_id, description, price_monthly, price_quarterly, price_semiannually, price_annually, setup_fee, is_active, order_priority, domain_required) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $_POST['name'],
            $slug,
            $type,
            $groupId,
            $_POST['description'] ?? '',
            $_POST['price_monthly'] ?: null,
            $_POST['price_quarterly'] ?: null,
            $_POST['price_semiannually'] ?: null,
            $_POST['price_annually'] ?: null,
            $_POST['setup_fee'] ?: 0,
            isset($_POST['is_active']) ? 1 : 0,
            (int)($_POST['order_priority'] ?? 0),
            (int)($_POST['domain_required'] ?? 0)
        ]
    );
    
    $newId = Database::getInstance()->lastInsertId();
    header('Location: product-edit.php?id=' . $newId . '&created=1');
    exit;
}

$groups = Database::fetchAll("SELECT * FROM product_groups ORDER BY order_priority, name");

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Wizard Container */
.wizard-container {
    max-width: 800px;
    margin: 0 auto;
}

/* Progress Steps */
.wizard-progress {
    display: flex;
    justify-content: space-between;
    margin-bottom: 40px;
    position: relative;
}

.wizard-progress::before {
    content: '';
    position: absolute;
    top: 24px;
    left: 60px;
    right: 60px;
    height: 3px;
    background: #e2e8f0;
    z-index: 0;
}

.wizard-progress .progress-line {
    position: absolute;
    top: 24px;
    left: 60px;
    height: 3px;
    background: linear-gradient(90deg, #6366f1, #8b5cf6);
    z-index: 1;
    transition: width 0.5s ease;
    width: 0%;
}

.wizard-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 2;
    cursor: pointer;
}

.wizard-step .step-circle {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #fff;
    border: 3px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 700;
    color: #94a3b8;
    transition: all 0.3s;
    margin-bottom: 10px;
}

.wizard-step.active .step-circle {
    border-color: #6366f1;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
}

.wizard-step.completed .step-circle {
    border-color: #10b981;
    background: #10b981;
    color: #fff;
}

.wizard-step .step-label {
    font-size: 13px;
    font-weight: 600;
    color: #94a3b8;
    text-align: center;
    transition: color 0.3s;
}

.wizard-step.active .step-label,
.wizard-step.completed .step-label {
    color: #1e293b;
}

/* Step Content */
.wizard-content {
    background: #fff;
    border-radius: 24px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    overflow: hidden;
}

.step-panel {
    display: none;
    padding: 40px;
    animation: fadeIn 0.4s ease;
}

.step-panel.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.step-header {
    text-align: center;
    margin-bottom: 35px;
}

.step-header .step-icon {
    width: 80px;
    height: 80px;
    border-radius: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    margin: 0 auto 20px;
    color: #fff;
}

.step-header .step-icon.blue { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.step-header .step-icon.purple { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
.step-header .step-icon.green { background: linear-gradient(135deg, #10b981, #059669); }
.step-header .step-icon.orange { background: linear-gradient(135deg, #f59e0b, #d97706); }

.step-header h2 {
    font-size: 26px;
    font-weight: 800;
    color: #1e293b;
    margin: 0 0 8px 0;
}

.step-header p {
    color: #64748b;
    font-size: 15px;
    margin: 0;
}

/* Form Elements */
.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 700;
    color: #334155;
    margin-bottom: 10px;
}

.form-group label .required {
    color: #ef4444;
}

.form-control {
    width: 100%;
    padding: 16px 20px;
    border: 2px solid #e2e8f0;
    border-radius: 14px;
    font-size: 16px;
    transition: all 0.3s;
    background: #fff;
}

.form-control:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
    outline: none;
}

.form-control::placeholder {
    color: #94a3b8;
}

textarea.form-control {
    min-height: 150px;
    resize: vertical;
}

/* Group Cards */
.group-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 15px;
}

.group-card {
    position: relative;
}

.group-card input {
    position: absolute;
    opacity: 0;
}

.group-card label {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 25px 15px;
    background: #f8fafc;
    border: 3px solid transparent;
    border-radius: 18px;
    cursor: pointer;
    transition: all 0.3s;
}

.group-card label:hover {
    background: #f1f5f9;
    transform: translateY(-3px);
}

.group-card input:checked + label {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(139, 92, 246, 0.08) 100%);
    border-color: #6366f1;
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.2);
}

.group-card .group-icon {
    width: 60px;
    height: 60px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    margin-bottom: 12px;
    transition: transform 0.3s;
}

.group-card input:checked + label .group-icon {
    transform: scale(1.1);
}

.group-card .group-icon.hosting { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1d4ed8; }
.group-card .group-icon.vps { background: linear-gradient(135deg, #d1fae5, #a7f3d0); color: #047857; }
.group-card .group-icon.vds { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #b45309; }
.group-card .group-icon.dedicated { background: linear-gradient(135deg, #ede9fe, #ddd6fe); color: #6d28d9; }
.group-card .group-icon.domain { background: linear-gradient(135deg, #e0f2fe, #bae6fd); color: #0369a1; }
.group-card .group-icon.ssl { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #15803d; }
.group-card .group-icon.other { background: linear-gradient(135deg, #f1f5f9, #e2e8f0); color: #475569; }

.group-card .group-name {
    font-size: 15px;
    font-weight: 700;
    color: #1e293b;
}

/* Price Cards */
.price-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

@media (max-width: 576px) {
    .price-grid { grid-template-columns: 1fr; }
}

.price-card {
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 20px;
    transition: all 0.3s;
}

.price-card:hover {
    border-color: #cbd5e1;
}

.price-card.primary {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(139, 92, 246, 0.05) 100%);
    border-color: #6366f1;
}

.price-card label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 12px;
}

.price-card label i {
    color: #6366f1;
}

.price-card.primary label {
    color: #6366f1;
    font-weight: 700;
}

.price-input {
    position: relative;
}

.price-input input {
    width: 100%;
    padding: 14px 50px 14px 18px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 18px;
    font-weight: 700;
    background: #fff;
    transition: all 0.3s;
}

.price-input input:focus {
    border-color: #6366f1;
    outline: none;
}

.price-input .currency {
    position: absolute;
    right: 18px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 18px;
    font-weight: 800;
    color: #94a3b8;
}

/* Toggle Switch */
.status-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.status-card {
    position: relative;
}

.status-card input {
    position: absolute;
    opacity: 0;
}

.status-card label {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 35px 25px;
    background: #f8fafc;
    border: 3px solid transparent;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.3s;
}

.status-card label:hover {
    background: #f1f5f9;
}

.status-card input:checked + label {
    border-color: #10b981;
    background: rgba(16, 185, 129, 0.05);
}

.status-card input[value="0"]:checked + label {
    border-color: #ef4444;
    background: rgba(239, 68, 68, 0.05);
}

.status-card .status-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    margin-bottom: 15px;
    transition: transform 0.3s;
}

.status-card input:checked + label .status-icon {
    transform: scale(1.1);
}

.status-card .status-icon.active {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: #059669;
}

.status-card .status-icon.inactive {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #dc2626;
}

.status-card h4 {
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 6px 0;
}

.status-card p {
    font-size: 13px;
    color: #64748b;
    margin: 0;
}

/* Summary */
.summary-card {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border-radius: 20px;
    padding: 30px;
    color: #fff;
}

.summary-header {
    text-align: center;
    padding-bottom: 25px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    margin-bottom: 25px;
}

.summary-header h3 {
    font-size: 24px;
    font-weight: 800;
    margin: 0 0 6px 0;
}

.summary-header span {
    color: #94a3b8;
    font-size: 14px;
}

.summary-price {
    text-align: center;
    padding: 25px;
    background: rgba(99, 102, 241, 0.15);
    border-radius: 16px;
    margin-bottom: 25px;
}

.summary-price .amount {
    font-size: 48px;
    font-weight: 900;
}

.summary-price .amount .curr {
    font-size: 24px;
    color: #6366f1;
}

.summary-price .period {
    color: #94a3b8;
    font-size: 14px;
}

.summary-features {
    margin-bottom: 25px;
}

.summary-features h4 {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #64748b;
    margin: 0 0 15px 0;
}

.summary-features ul {
    list-style: none;
    padding: 0;
    margin: 0;
    max-height: 200px;
    overflow-y: auto;
}

.summary-features li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 0;
    font-size: 14px;
    color: #e2e8f0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.summary-features li::before {
    content: '✓';
    color: #10b981;
    font-weight: bold;
}

.summary-status {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 15px;
    background: rgba(16, 185, 129, 0.15);
    border-radius: 12px;
    color: #10b981;
    font-weight: 700;
}

.summary-status.inactive {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

/* Navigation */
.wizard-nav {
    display: flex;
    justify-content: space-between;
    padding: 25px 40px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
}

.wizard-nav .btn {
    padding: 16px 32px;
    font-size: 15px;
    font-weight: 700;
    border-radius: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s;
    cursor: pointer;
    border: none;
}

.wizard-nav .btn-back {
    background: #fff;
    border: 2px solid #e2e8f0;
    color: #64748b;
}

.wizard-nav .btn-back:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.wizard-nav .btn-next {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #fff;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.wizard-nav .btn-next:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
}

.wizard-nav .btn-submit {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #fff;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
}

.wizard-nav .btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
}

.wizard-nav .btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
}

/* Empty State */
.empty-groups {
    text-align: center;
    padding: 50px;
    background: #fffbeb;
    border: 2px dashed #fbbf24;
    border-radius: 20px;
}

.empty-groups i {
    font-size: 50px;
    color: #f59e0b;
    margin-bottom: 20px;
}

.empty-groups h4 {
    font-size: 20px;
    color: #92400e;
    margin: 0 0 10px 0;
}

.empty-groups p {
    color: #b45309;
    margin: 0 0 25px 0;
}

/* Domain Options */
.domain-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.domain-card {
    position: relative;
}

.domain-card input {
    position: absolute;
    opacity: 0;
}

.domain-card label {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 25px 20px;
    background: #f8fafc;
    border: 3px solid transparent;
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.3s;
}

.domain-card label:hover {
    background: #f1f5f9;
}

.domain-card input:checked + label {
    border-color: #6366f1;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(139, 92, 246, 0.08) 100%);
}

.domain-card input[value="1"]:checked + label {
    border-color: #10b981;
    background: rgba(16, 185, 129, 0.08);
}

.domain-card .domain-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    margin-bottom: 12px;
}

.domain-card .domain-icon.no {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #dc2626;
}

.domain-card .domain-icon.yes {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: #059669;
}

.domain-card h4 {
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 5px 0;
}

.domain-card p {
    font-size: 12px;
    color: #64748b;
    margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .wizard-progress::before,
    .wizard-progress .progress-line {
        display: none;
    }
    
    .wizard-step .step-label {
        display: none;
    }
    
    .step-panel {
        padding: 30px 20px;
    }
    
    .wizard-nav {
        padding: 20px;
    }
    
    .wizard-nav .btn {
        padding: 14px 20px;
        font-size: 14px;
    }
    
    .status-options {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="wizard-container">
    <!-- Progress Steps -->
    <div class="wizard-progress">
        <div class="progress-line" id="progressLine"></div>
        <div class="wizard-step active" data-step="1">
            <div class="step-circle">1</div>
            <div class="step-label">Temel Bilgiler</div>
        </div>
        <div class="wizard-step" data-step="2">
            <div class="step-circle">2</div>
            <div class="step-label">Fiyatlandırma</div>
        </div>
        <div class="wizard-step" data-step="3">
            <div class="step-circle">3</div>
            <div class="step-label">Özellikler</div>
        </div>
        <div class="wizard-step" data-step="4">
            <div class="step-circle">4</div>
            <div class="step-label">Tamamla</div>
        </div>
    </div>
    
    <form method="POST" id="productForm">
    <div class="wizard-content">
        
        <!-- Step 1: Temel Bilgiler -->
        <div class="step-panel active" data-step="1">
            <div class="step-header">
                <div class="step-icon blue">
                    <i class="fas fa-info"></i>
                </div>
                <h2>Temel Bilgiler</h2>
                <p>Ürün adını girin ve kategorisini seçin</p>
            </div>
            
            <div class="form-group">
                <label>Ürün Adı <span class="required">*</span></label>
                <input type="text" name="name" id="productName" class="form-control" placeholder="Örn: Linux Hosting Pro, VPS Starter..." required>
            </div>
            
            <?php if (empty($groups)): ?>
                <div class="empty-groups">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h4>Ürün Grubu Bulunamadı</h4>
                    <p>Ürün eklemeden önce en az bir ürün grubu oluşturmalısınız.</p>
                    <a href="product-groups.php" class="btn btn-primary" style="display: inline-flex; padding: 14px 28px;">
                        <i class="fas fa-plus"></i> Grup Oluştur
                    </a>
                </div>
            <?php else: ?>
                <div class="form-group">
                    <label>Ürün Grubu <span class="required">*</span></label>
                    <div class="group-grid">
                        <?php foreach ($groups as $group): 
                            $type = $group['type'] ?? 'other';
                            $typeIcon = match($type) {
                                'hosting' => 'fa-globe',
                                'vps' => 'fa-server',
                                'vds' => 'fa-database',
                                'dedicated' => 'fa-building',
                                'domain' => 'fa-link',
                                'ssl' => 'fa-lock',
                                default => 'fa-box'
                            };
                        ?>
                            <div class="group-card">
                                <input type="radio" name="group_id" id="group_<?= $group['id'] ?>" value="<?= $group['id'] ?>" data-name="<?= htmlspecialchars($group['name']) ?>" required>
                                <label for="group_<?= $group['id'] ?>">
                                    <div class="group-icon <?= $type ?>">
                                        <i class="fas <?= $typeIcon ?>"></i>
                                    </div>
                                    <div class="group-name"><?= htmlspecialchars($group['name']) ?></div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Step 2: Fiyatlandırma -->
        <div class="step-panel" data-step="2">
            <div class="step-header">
                <div class="step-icon green">
                    <i class="fas fa-lira-sign"></i>
                </div>
                <h2>Fiyatlandırma</h2>
                <p>Farklı dönemler için ücretleri belirleyin</p>
            </div>
            
            <div class="price-grid">
                <div class="price-card primary">
                    <label><i class="fas fa-calendar-day"></i> Aylık Fiyat</label>
                    <div class="price-input">
                        <input type="number" name="price_monthly" id="priceMonthly" step="0.01" placeholder="0.00">
                        <span class="currency">₺</span>
                    </div>
                </div>
                
                <div class="price-card">
                    <label><i class="fas fa-calendar-week"></i> 3 Aylık</label>
                    <div class="price-input">
                        <input type="number" name="price_quarterly" step="0.01" placeholder="0.00">
                        <span class="currency">₺</span>
                    </div>
                </div>
                
                <div class="price-card">
                    <label><i class="fas fa-calendar-alt"></i> 6 Aylık</label>
                    <div class="price-input">
                        <input type="number" name="price_semiannually" step="0.01" placeholder="0.00">
                        <span class="currency">₺</span>
                    </div>
                </div>
                
                <div class="price-card">
                    <label><i class="fas fa-calendar"></i> Yıllık</label>
                    <div class="price-input">
                        <input type="number" name="price_annually" step="0.01" placeholder="0.00">
                        <span class="currency">₺</span>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 25px;">
                <div class="price-card" style="max-width: 300px;">
                    <label><i class="fas fa-tools"></i> Kurulum Ücreti</label>
                    <div class="price-input">
                        <input type="number" name="setup_fee" step="0.01" value="0" placeholder="0.00">
                        <span class="currency">₺</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Step 3: Özellikler -->
        <div class="step-panel" data-step="3">
            <div class="step-header">
                <div class="step-icon purple">
                    <i class="fas fa-list-check"></i>
                </div>
                <h2>Özellikler</h2>
                <p>Ürünün sunduğu özellikleri listeleyin</p>
            </div>
            
            <div class="form-group">
                <label>Ürün Özellikleri</label>
                <textarea name="description" id="productDesc" class="form-control" placeholder="Her satıra bir özellik yazın...

Örnek:
10 GB SSD Disk Alanı
Sınırsız Bandwidth
5 Adet E-Posta Hesabı
Ücretsiz SSL Sertifikası
7/24 Teknik Destek"></textarea>
            </div>
            
            <div class="form-group">
                <label>Sıralama Önceliği</label>
                <input type="number" name="order_priority" class="form-control" value="0" min="0" style="max-width: 150px;">
                <small style="color: #64748b; margin-top: 8px; display: block;">Düşük değer = önce gösterilir</small>
            </div>
            
            <!-- Domain/Hostname Gerekli mi? -->
            <div class="form-group" style="margin-top: 30px;">
                <label style="margin-bottom: 15px;">Domain / Hostname Gerekli mi?</label>
                <div class="domain-options">
                    <div class="domain-card">
                        <input type="radio" name="domain_required" id="domainNo" value="0" checked>
                        <label for="domainNo">
                            <div class="domain-icon no">
                                <i class="fas fa-times"></i>
                            </div>
                            <h4>Hayır</h4>
                            <p>Domain/hostname opsiyonel</p>
                        </label>
                    </div>
                    <div class="domain-card">
                        <input type="radio" name="domain_required" id="domainYes" value="1">
                        <label for="domainYes">
                            <div class="domain-icon yes">
                                <i class="fas fa-globe"></i>
                            </div>
                            <h4>Evet</h4>
                            <p>Sipariş için zorunlu</p>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Step 4: Tamamla -->
        <div class="step-panel" data-step="4">
            <div class="step-header">
                <div class="step-icon orange">
                    <i class="fas fa-check"></i>
                </div>
                <h2>Tamamla</h2>
                <p>Bilgileri kontrol edin ve yayın durumunu seçin</p>
            </div>
            
            <div class="status-options">
                <div class="status-card">
                    <input type="radio" name="is_active" id="statusActive" value="1" checked>
                    <label for="statusActive">
                        <div class="status-icon active">
                            <i class="fas fa-check"></i>
                        </div>
                        <h4>Aktif</h4>
                        <p>Ürün hemen yayınlanacak</p>
                    </label>
                </div>
                <div class="status-card">
                    <input type="radio" name="is_active" id="statusInactive" value="0">
                    <label for="statusInactive">
                        <div class="status-icon inactive">
                            <i class="fas fa-pause"></i>
                        </div>
                        <h4>Taslak</h4>
                        <p>Daha sonra yayınlanacak</p>
                    </label>
                </div>
            </div>
            
            <div style="margin-top: 30px;">
                <div class="summary-card">
                    <div class="summary-header">
                        <h3 id="summaryName">Ürün Adı</h3>
                        <span id="summaryGroup">Grup</span>
                    </div>
                    
                    <div class="summary-price">
                        <div class="amount">
                            <span class="curr">₺</span><span id="summaryPrice">0</span>
                        </div>
                        <div class="period">/ aylık</div>
                    </div>
                    
                    <div class="summary-features">
                        <h4>Özellikler</h4>
                        <ul id="summaryFeatures">
                            <li>Özellik eklenmedi</li>
                        </ul>
                    </div>
                    
                    <div class="summary-status" id="summaryStatus">
                        <i class="fas fa-check-circle"></i>
                        Aktif olarak yayınlanacak
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Navigation -->
        <div class="wizard-nav">
            <button type="button" class="btn btn-back" id="btnBack" style="visibility: hidden;">
                <i class="fas fa-arrow-left"></i> Geri
            </button>
            <div>
                <a href="products.php" class="btn btn-back" style="margin-right: 10px;">
                    <i class="fas fa-times"></i> İptal
                </a>
                <button type="button" class="btn btn-next" id="btnNext">
                    İleri <i class="fas fa-arrow-right"></i>
                </button>
                <button type="submit" class="btn btn-submit" id="btnSubmit" style="display: none;">
                    <i class="fas fa-check"></i> Ürünü Oluştur
                </button>
            </div>
        </div>
        
    </div>
    </form>
</div>

<script>
let currentStep = 1;
const totalSteps = 4;

const steps = document.querySelectorAll('.wizard-step');
const panels = document.querySelectorAll('.step-panel');
const progressLine = document.getElementById('progressLine');
const btnBack = document.getElementById('btnBack');
const btnNext = document.getElementById('btnNext');
const btnSubmit = document.getElementById('btnSubmit');

// Form elements
const productName = document.getElementById('productName');
const productDesc = document.getElementById('productDesc');
const priceMonthly = document.getElementById('priceMonthly');
const groupInputs = document.querySelectorAll('input[name="group_id"]');
const statusInputs = document.querySelectorAll('input[name="is_active"]');

// Summary elements
const summaryName = document.getElementById('summaryName');
const summaryGroup = document.getElementById('summaryGroup');
const summaryPrice = document.getElementById('summaryPrice');
const summaryFeatures = document.getElementById('summaryFeatures');
const summaryStatus = document.getElementById('summaryStatus');

function updateProgress() {
    const percent = ((currentStep - 1) / (totalSteps - 1)) * 100;
    progressLine.style.width = percent + '%';
    
    steps.forEach((step, index) => {
        const stepNum = index + 1;
        step.classList.remove('active', 'completed');
        
        if (stepNum < currentStep) {
            step.classList.add('completed');
        } else if (stepNum === currentStep) {
            step.classList.add('active');
        }
    });
    
    panels.forEach(panel => {
        panel.classList.remove('active');
        if (parseInt(panel.dataset.step) === currentStep) {
            panel.classList.add('active');
        }
    });
    
    // Navigation buttons
    btnBack.style.visibility = currentStep === 1 ? 'hidden' : 'visible';
    btnNext.style.display = currentStep === totalSteps ? 'none' : 'inline-flex';
    btnSubmit.style.display = currentStep === totalSteps ? 'inline-flex' : 'none';
}

function validateStep(step) {
    if (step === 1) {
        if (!productName.value.trim()) {
            productName.focus();
            return false;
        }
        const selectedGroup = document.querySelector('input[name="group_id"]:checked');
        if (!selectedGroup) {
            alert('Lütfen bir ürün grubu seçin');
            return false;
        }
    }
    return true;
}

function updateSummary() {
    // Name
    summaryName.textContent = productName.value || 'Ürün Adı';
    
    // Group
    const selectedGroup = document.querySelector('input[name="group_id"]:checked');
    summaryGroup.textContent = selectedGroup ? selectedGroup.dataset.name : 'Grup seçilmedi';
    
    // Price
    const price = parseFloat(priceMonthly.value) || 0;
    summaryPrice.textContent = price.toLocaleString('tr-TR');
    
    // Features
    const features = productDesc.value.split('\n').filter(line => line.trim());
    if (features.length > 0) {
        summaryFeatures.innerHTML = features.map(f => `<li>${f}</li>`).join('');
    } else {
        summaryFeatures.innerHTML = '<li style="opacity: 0.5;">Özellik eklenmedi</li>';
    }
    
    // Status
    const isActive = document.querySelector('input[name="is_active"]:checked')?.value === '1';
    summaryStatus.className = 'summary-status' + (isActive ? '' : ' inactive');
    summaryStatus.innerHTML = isActive 
        ? '<i class="fas fa-check-circle"></i> Aktif olarak yayınlanacak'
        : '<i class="fas fa-pause-circle"></i> Taslak olarak kaydedilecek';
}

btnNext.addEventListener('click', function() {
    if (validateStep(currentStep)) {
        if (currentStep < totalSteps) {
            currentStep++;
            updateProgress();
            if (currentStep === totalSteps) {
                updateSummary();
            }
        }
    }
});

btnBack.addEventListener('click', function() {
    if (currentStep > 1) {
        currentStep--;
        updateProgress();
    }
});

// Click on step to navigate (only to completed steps)
steps.forEach(step => {
    step.addEventListener('click', function() {
        const stepNum = parseInt(this.dataset.step);
        if (stepNum < currentStep) {
            currentStep = stepNum;
            updateProgress();
        }
    });
});

// Update summary when status changes
statusInputs.forEach(input => {
    input.addEventListener('change', updateSummary);
});

// Initialize
updateProgress();
</script>

<?php include 'includes/footer.php'; ?>
