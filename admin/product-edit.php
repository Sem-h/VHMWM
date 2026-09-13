<?php
/**
 * WHMVM - Ürün Düzenleme
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
session_name(SESSION_NAME); session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Ürün Düzenle';
$currentPage = 'products';
$db = Database::getInstance();
$message = '';

// Eksik sütunları kontrol et
function ensureProductColumns() {
    try {
        $columns = Database::fetchAll("SHOW COLUMNS FROM products LIKE 'is_featured'");
        if (empty($columns)) {
            Database::query("ALTER TABLE products ADD COLUMN is_featured TINYINT(1) DEFAULT 0");
        }
        $columns = Database::fetchAll("SHOW COLUMNS FROM products LIKE 'order_priority'");
        if (empty($columns)) {
            Database::query("ALTER TABLE products ADD COLUMN order_priority INT DEFAULT 0");
        }
        $columns = Database::fetchAll("SHOW COLUMNS FROM products LIKE 'domain_required'");
        if (empty($columns)) {
            Database::query("ALTER TABLE products ADD COLUMN domain_required TINYINT(1) DEFAULT 0");
        }
    } catch (Exception $e) {}
}
ensureProductColumns();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: products.php'); exit; }

$product = Database::fetch("SELECT * FROM products WHERE id = ?", [$id]);
if (!$product) { header('Location: products.php'); exit; }

// Güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $groupId = $_POST['group_id'] ?: null;
    $type = 'other';
    if ($groupId) {
        $groupType = Database::fetchColumn("SELECT type FROM product_groups WHERE id = ?", [$groupId]);
        if ($groupType) $type = $groupType;
    }
    
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $_POST['name']));
    
    Database::query(
        "UPDATE products SET name=?, slug=?, type=?, group_id=?, description=?, price_monthly=?, price_annually=?, setup_fee=?, is_active=?, is_featured=?, order_priority=?, domain_required=? WHERE id=?",
        [$_POST['name'], $slug, $type, $groupId, $_POST['description'] ?? '', $_POST['price_monthly'] ?: null, $_POST['price_annually'] ?: null, $_POST['setup_fee'] ?: 0, isset($_POST['is_active']) ? 1 : 0, isset($_POST['is_featured']) ? 1 : 0, (int)($_POST['order_priority'] ?? 0), isset($_POST['domain_required']) ? 1 : 0, $id]
    );
    
    // Config links
    Database::query("DELETE FROM product_config_links WHERE product_id = ?", [$id]);
    if (isset($_POST['config_groups'])) {
        foreach ($_POST['config_groups'] as $gid) {
            try { Database::query("INSERT INTO product_config_links (product_id, group_id) VALUES (?, ?)", [$id, (int)$gid]); } catch(Exception $e) {}
        }
    }
    
    $message = 'success';
    $product = Database::fetch("SELECT * FROM products WHERE id = ?", [$id]);
}

$groups = Database::fetchAll("SELECT * FROM product_groups ORDER BY order_priority, name");
$configGroups = [];
$linkedConfigGroups = [];
try {
    $configGroups = Database::fetchAll("SELECT * FROM config_option_groups ORDER BY name");
    $linkedConfigGroups = array_column(Database::fetchAll("SELECT group_id FROM product_config_links WHERE product_id = ?", [$id]), 'group_id');
} catch (Exception $e) {}

$typeConfig = [
    'hosting' => ['icon' => 'fa-globe', 'color' => '#3b82f6', 'gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'],
    'vps' => ['icon' => 'fa-server', 'color' => '#8b5cf6', 'gradient' => 'linear-gradient(135deg, #7c3aed 0%, #a855f7 100%)'],
    'vds' => ['icon' => 'fa-database', 'color' => '#06b6d4', 'gradient' => 'linear-gradient(135deg, #0891b2 0%, #22d3ee 100%)'],
    'dedicated' => ['icon' => 'fa-building', 'color' => '#f59e0b', 'gradient' => 'linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%)'],
    'domain' => ['icon' => 'fa-link', 'color' => '#10b981', 'gradient' => 'linear-gradient(135deg, #059669 0%, #34d399 100%)'],
    'ssl' => ['icon' => 'fa-lock', 'color' => '#ef4444', 'gradient' => 'linear-gradient(135deg, #dc2626 0%, #f87171 100%)'],
    'other' => ['icon' => 'fa-box', 'color' => '#6366f1', 'gradient' => 'linear-gradient(135deg, #6366f1 0%, #818cf8 100%)']
];
$type = $product['type'] ?? 'other';
$tc = $typeConfig[$type] ?? $typeConfig['other'];
$features = array_filter(explode("\n", $product['description'] ?? ''));

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root { --accent: <?= $tc['color'] ?>; --gradient: <?= $tc['gradient'] ?>; }

/* Hero */
.edit-hero {
    background: var(--gradient);
    border-radius: 24px;
    padding: 35px 40px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
}
.edit-hero::before {
    content: '';
    position: absolute;
    top: -100px; right: -100px;
    width: 300px; height: 300px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}
.edit-hero::after {
    content: '<?= $type === 'hosting' ? '🌐' : ($type === 'vps' ? '💻' : ($type === 'dedicated' ? '🏢' : '📦')) ?>';
    position: absolute;
    right: 50px; top: 50%;
    transform: translateY(-50%);
    font-size: 100px;
    opacity: 0.15;
}
.hero-left { display: flex; align-items: center; gap: 20px; z-index: 1; }
.hero-back {
    width: 48px; height: 48px;
    background: rgba(255,255,255,0.15);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    text-decoration: none;
    transition: all 0.3s;
    backdrop-filter: blur(10px);
}
.hero-back:hover { background: rgba(255,255,255,0.25); transform: translateX(-5px); }
.hero-icon {
    width: 72px; height: 72px;
    background: rgba(255,255,255,0.2);
    border-radius: 20px;
    display: flex; align-items: center; justify-content: center;
    font-size: 30px; color: #fff;
    backdrop-filter: blur(10px);
}
.hero-info h1 { color: #fff; font-size: 28px; font-weight: 800; margin: 0 0 6px; }
.hero-info p { color: rgba(255,255,255,0.8); font-size: 14px; margin: 0; }
.hero-badges { display: flex; gap: 10px; z-index: 1; }
.hero-badge {
    padding: 10px 18px;
    border-radius: 50px;
    font-size: 13px;
    font-weight: 600;
    backdrop-filter: blur(10px);
}
.hero-badge.active { background: rgba(16,185,129,0.25); color: #a7f3d0; border: 1px solid rgba(16,185,129,0.4); }
.hero-badge.inactive { background: rgba(239,68,68,0.25); color: #fecaca; border: 1px solid rgba(239,68,68,0.4); }
.hero-badge.featured { background: rgba(251,191,36,0.25); color: #fef3c7; border: 1px solid rgba(251,191,36,0.4); }

/* Success Alert */
.success-alert {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    border: 1px solid #10b981;
    border-radius: 16px;
    padding: 18px 24px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 14px;
    animation: slideDown 0.4s ease;
}
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.success-alert i { font-size: 22px; color: #059669; }
.success-alert span { font-weight: 600; color: #065f46; }

/* Layout */
.edit-grid { display: grid; grid-template-columns: 1fr 360px; gap: 30px; align-items: start; }
@media (max-width: 1200px) { .edit-grid { grid-template-columns: 1fr; } }

/* Cards */
.edit-card {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    margin-bottom: 24px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    transition: all 0.3s;
}
.edit-card:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.08); }
.card-header {
    padding: 22px 28px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 14px;
}
.card-header .icon {
    width: 44px; height: 44px;
    background: var(--gradient);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 18px;
}
.card-header h3 { font-size: 16px; font-weight: 700; color: #1e293b; margin: 0; }
.card-header span { font-size: 13px; color: #64748b; display: block; margin-top: 2px; }
.card-body { padding: 28px; }

/* Form */
.form-group { margin-bottom: 24px; }
.form-group:last-child { margin-bottom: 0; }
.form-label {
    display: flex; align-items: center; gap: 8px;
    font-size: 14px; font-weight: 600; color: #374151;
    margin-bottom: 10px;
}
.form-label i { color: var(--accent); font-size: 14px; }
.form-control {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 15px;
    transition: all 0.3s;
    background: #f9fafb;
}
.form-control:focus {
    border-color: var(--accent);
    background: #fff;
    box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
    outline: none;
}
textarea.form-control { min-height: 140px; resize: vertical; line-height: 1.7; }
.form-hint { font-size: 12px; color: #9ca3af; margin-top: 8px; display: flex; align-items: center; gap: 6px; }

/* Price Grid */
.price-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
@media (max-width: 768px) { .price-grid { grid-template-columns: 1fr; } }
.price-box {
    background: linear-gradient(145deg, #f9fafb 0%, #f3f4f6 100%);
    border: 2px solid #e5e7eb;
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s;
}
.price-box:hover { border-color: var(--accent); transform: translateY(-3px); }
.price-box.has-value { border-color: var(--accent); background: linear-gradient(145deg, rgba(99,102,241,0.05) 0%, rgba(99,102,241,0.02) 100%); }
.price-box-icon {
    width: 48px; height: 48px;
    background: var(--gradient);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 14px; color: #fff; font-size: 18px;
}
.price-box-label { font-size: 12px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; }
.price-input {
    position: relative;
}
.price-input input {
    width: 100%;
    padding: 14px 45px 14px 18px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 18px; font-weight: 700;
    text-align: center;
    background: #fff;
    transition: all 0.3s;
}
.price-input input:focus { border-color: var(--accent); outline: none; box-shadow: 0 0 0 4px rgba(99,102,241,0.1); }
.price-input .currency {
    position: absolute;
    right: 16px; top: 50%;
    transform: translateY(-50%);
    font-size: 16px; font-weight: 800;
    color: var(--accent);
}

/* Toggle Cards */
.toggle-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
@media (max-width: 600px) { .toggle-grid { grid-template-columns: 1fr; } }
.toggle-item {
    background: #f9fafb;
    border: 2px solid #e5e7eb;
    border-radius: 16px;
    padding: 18px 20px;
    display: flex; align-items: center; gap: 14px;
    cursor: pointer;
    transition: all 0.3s;
}
.toggle-item:hover { border-color: #d1d5db; }
.toggle-item.active { border-color: #10b981; background: linear-gradient(145deg, #ecfdf5 0%, #d1fae5 100%); }
.toggle-item.featured { border-color: #f59e0b; background: linear-gradient(145deg, #fffbeb 0%, #fef3c7 100%); }
.toggle-icon {
    width: 48px; height: 48px;
    background: #e5e7eb;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    transition: all 0.3s;
}
.toggle-item.active .toggle-icon { background: #10b981; color: #fff; }
.toggle-item.featured .toggle-icon { background: #f59e0b; color: #fff; }
.toggle-text strong { display: block; font-size: 14px; color: #1f2937; margin-bottom: 2px; }
.toggle-text span { font-size: 12px; color: #6b7280; }
.toggle-switch {
    margin-left: auto;
    width: 52px; height: 28px;
    background: #d1d5db;
    border-radius: 14px;
    position: relative;
    transition: all 0.3s;
}
.toggle-switch::after {
    content: '';
    position: absolute;
    width: 22px; height: 22px;
    background: #fff;
    border-radius: 50%;
    top: 3px; left: 3px;
    transition: all 0.3s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.toggle-item.active .toggle-switch { background: #10b981; }
.toggle-item.featured .toggle-switch { background: #f59e0b; }
.toggle-item.domain { border-color: #0ea5e9; background: linear-gradient(145deg, #f0f9ff 0%, #e0f2fe 100%); }
.toggle-item.domain .toggle-icon { background: #0ea5e9; color: #fff; }
.toggle-item.domain .toggle-switch { background: #0ea5e9; }
.toggle-item.active .toggle-switch::after,
.toggle-item.featured .toggle-switch::after,
.toggle-item.domain .toggle-switch::after { left: 27px; }
.toggle-item input { display: none; }

/* Config Groups */
.config-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
.config-item {
    display: flex; align-items: center; gap: 12px;
    padding: 16px 18px;
    background: #f9fafb;
    border: 2px solid #e5e7eb;
    border-radius: 14px;
    cursor: pointer;
    transition: all 0.3s;
}
.config-item:hover { border-color: #d1d5db; transform: translateY(-2px); }
.config-item.selected { border-color: #8b5cf6; background: linear-gradient(145deg, rgba(139,92,246,0.06) 0%, rgba(139,92,246,0.02) 100%); }
.config-item input { width: 18px; height: 18px; accent-color: #8b5cf6; }
.config-item-info strong { display: block; font-size: 13px; color: #1f2937; }
.config-item-info span { font-size: 11px; color: #6b7280; }

/* Sidebar Cards */
.sidebar-card {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    margin-bottom: 20px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
}
.sidebar-header {
    padding: 20px 24px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 1px solid #e5e7eb;
    display: flex; align-items: center; gap: 12px;
}
.sidebar-header .icon {
    width: 38px; height: 38px;
    background: var(--gradient);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 15px;
}
.sidebar-header h4 { font-size: 15px; font-weight: 700; color: #1e293b; margin: 0; }
.sidebar-body { padding: 24px; }

/* Preview */
.preview-box {
    background: linear-gradient(145deg, rgba(99,102,241,0.08) 0%, rgba(99,102,241,0.02) 100%);
    border: 2px dashed var(--accent);
    border-radius: 16px;
    padding: 28px;
    text-align: center;
}
.preview-icon {
    width: 70px; height: 70px;
    background: var(--gradient);
    border-radius: 18px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 18px;
    font-size: 28px; color: #fff;
    box-shadow: 0 8px 25px rgba(99,102,241,0.3);
}
.preview-name { font-size: 20px; font-weight: 800; color: #1e293b; margin-bottom: 8px; }
.preview-price { font-size: 28px; font-weight: 800; color: var(--accent); margin-bottom: 18px; }
.preview-price span { font-size: 14px; color: #6b7280; font-weight: 500; }
.preview-features { text-align: left; }
.preview-feature {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 0;
    font-size: 13px; color: #4b5563;
    border-bottom: 1px solid #e5e7eb;
}
.preview-feature:last-child { border-bottom: none; }
.preview-feature i { color: #10b981; font-size: 12px; }

/* Info List */
.info-list { display: flex; flex-direction: column; gap: 14px; }
.info-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 16px;
    background: #f9fafb;
    border-radius: 10px;
}
.info-row .label { font-size: 13px; color: #6b7280; display: flex; align-items: center; gap: 8px; }
.info-row .label i { color: var(--accent); }
.info-row .value { font-size: 13px; font-weight: 600; color: #1f2937; }

/* Action Buttons */
.action-btns { display: flex; flex-direction: column; gap: 12px; }
.action-btn {
    display: flex; align-items: center; justify-content: center; gap: 10px;
    padding: 14px 20px;
    border-radius: 12px;
    font-size: 14px; font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    border: none;
}
.action-btn.primary {
    background: var(--gradient);
    color: #fff;
    box-shadow: 0 4px 15px rgba(99,102,241,0.3);
}
.action-btn.primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(99,102,241,0.4); }
.action-btn.outline {
    background: #fff;
    border: 2px solid #e5e7eb;
    color: #4b5563;
}
.action-btn.outline:hover { border-color: #d1d5db; background: #f9fafb; }
.action-btn.danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: #fff;
}
.action-btn.danger:hover { opacity: 0.9; }

/* Group Select */
.group-dropdown {
    position: relative;
}
.group-dropdown select {
    appearance: none;
    width: 100%;
    padding: 14px 45px 14px 18px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 15px;
    background: #f9fafb;
    cursor: pointer;
    transition: all 0.3s;
}
.group-dropdown select:focus { border-color: var(--accent); background: #fff; outline: none; }
.group-dropdown::after {
    content: '\f078';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    right: 18px; top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    pointer-events: none;
}
</style>

<!-- Hero Header -->
<div class="edit-hero">
    <div class="hero-left">
        <a href="products.php" class="hero-back"><i class="fas fa-arrow-left"></i></a>
        <div class="hero-icon"><i class="fas <?= $tc['icon'] ?>"></i></div>
        <div class="hero-info">
            <h1><?= htmlspecialchars($product['name']) ?></h1>
            <p>ID: #<?= $product['id'] ?> • <?= ucfirst($type) ?> • <?= htmlspecialchars($product['slug']) ?></p>
        </div>
    </div>
    <div class="hero-badges">
        <?php if ($product['is_active']): ?>
            <span class="hero-badge active"><i class="fas fa-check-circle"></i> Aktif</span>
        <?php else: ?>
            <span class="hero-badge inactive"><i class="fas fa-times-circle"></i> Pasif</span>
        <?php endif; ?>
        <?php if ($product['is_featured'] ?? 0): ?>
            <span class="hero-badge featured"><i class="fas fa-star"></i> Öne Çıkan</span>
        <?php endif; ?>
    </div>
</div>

<?php if ($message === 'success'): ?>
<div class="success-alert">
    <i class="fas fa-check-circle"></i>
    <span>Ürün başarıyla güncellendi!</span>
</div>
<?php endif; ?>

<form method="POST">
<div class="edit-grid">
    <!-- Sol Kolon -->
    <div>
        <!-- Temel Bilgiler -->
        <div class="edit-card">
            <div class="card-header">
                <div class="icon"><i class="fas fa-info"></i></div>
                <div><h3>Temel Bilgiler</h3><span>Ürün adı ve açıklaması</span></div>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-tag"></i> Ürün Adı</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($product['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-folder"></i> Ürün Grubu</label>
                    <div class="group-dropdown">
                        <select name="group_id" class="form-control">
                            <option value="">-- Grup Seçin --</option>
                            <?php foreach ($groups as $g): ?>
                                <option value="<?= $g['id'] ?>" <?= $product['group_id'] == $g['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($g['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-list"></i> Özellikler / Açıklama</label>
                    <textarea name="description" class="form-control" placeholder="Her satıra bir özellik..."><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                    <div class="form-hint"><i class="fas fa-lightbulb"></i> Her satır ayrı bir özellik olarak gösterilir</div>
                </div>
            </div>
        </div>
        
        <!-- Fiyatlandırma -->
        <div class="edit-card">
            <div class="card-header">
                <div class="icon"><i class="fas fa-lira-sign"></i></div>
                <div><h3>Fiyatlandırma</h3><span>Dönemsel ücretler</span></div>
            </div>
            <div class="card-body">
                <div class="price-grid">
                    <div class="price-box <?= $product['price_monthly'] ? 'has-value' : '' ?>">
                        <div class="price-box-icon"><i class="fas fa-calendar-day"></i></div>
                        <div class="price-box-label">Aylık</div>
                        <div class="price-input">
                            <input type="number" name="price_monthly" step="0.01" value="<?= $product['price_monthly'] ?? '' ?>" placeholder="0.00">
                            <span class="currency">₺</span>
                        </div>
                    </div>
                    <div class="price-box <?= $product['price_annually'] ? 'has-value' : '' ?>">
                        <div class="price-box-icon"><i class="fas fa-calendar"></i></div>
                        <div class="price-box-label">Yıllık</div>
                        <div class="price-input">
                            <input type="number" name="price_annually" step="0.01" value="<?= $product['price_annually'] ?? '' ?>" placeholder="0.00">
                            <span class="currency">₺</span>
                        </div>
                    </div>
                    <div class="price-box <?= $product['setup_fee'] ? 'has-value' : '' ?>">
                        <div class="price-box-icon"><i class="fas fa-tools"></i></div>
                        <div class="price-box-label">Kurulum</div>
                        <div class="price-input">
                            <input type="number" name="setup_fee" step="0.01" value="<?= $product['setup_fee'] ?? '' ?>" placeholder="0.00">
                            <span class="currency">₺</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Durum -->
        <div class="edit-card">
            <div class="card-header">
                <div class="icon"><i class="fas fa-toggle-on"></i></div>
                <div><h3>Yayın Durumu</h3><span>Görünürlük ve öne çıkarma</span></div>
            </div>
            <div class="card-body">
                <div class="toggle-grid">
                    <label class="toggle-item <?= $product['is_active'] ? 'active' : '' ?>">
                        <div class="toggle-icon"><?= $product['is_active'] ? '✓' : '○' ?></div>
                        <div class="toggle-text">
                            <strong>Aktif</strong>
                            <span>Ürün satışa açık</span>
                        </div>
                        <div class="toggle-switch"></div>
                        <input type="checkbox" name="is_active" <?= $product['is_active'] ? 'checked' : '' ?>>
                    </label>
                    <label class="toggle-item <?= ($product['is_featured'] ?? 0) ? 'featured' : '' ?>">
                        <div class="toggle-icon"><?= ($product['is_featured'] ?? 0) ? '⭐' : '☆' ?></div>
                        <div class="toggle-text">
                            <strong>Öne Çıkan</strong>
                            <span>Ana sayfada göster</span>
                        </div>
                        <div class="toggle-switch"></div>
                        <input type="checkbox" name="is_featured" <?= ($product['is_featured'] ?? 0) ? 'checked' : '' ?>>
                    </label>
                    <label class="toggle-item <?= ($product['domain_required'] ?? 0) ? 'domain' : '' ?>">
                        <div class="toggle-icon"><?= ($product['domain_required'] ?? 0) ? '🌐' : '🔗' ?></div>
                        <div class="toggle-text">
                            <strong>Domain Zorunlu</strong>
                            <span>Sipariş için hostname gerekli</span>
                        </div>
                        <div class="toggle-switch"></div>
                        <input type="checkbox" name="domain_required" <?= ($product['domain_required'] ?? 0) ? 'checked' : '' ?>>
                    </label>
                </div>
                <div class="form-group" style="margin-top: 20px;">
                    <label class="form-label"><i class="fas fa-sort-numeric-up"></i> Sıralama Önceliği</label>
                    <input type="number" name="order_priority" class="form-control" value="<?= $product['order_priority'] ?? 0 ?>" min="0" style="max-width: 150px;">
                    <div class="form-hint">Düşük değer = önce gösterilir</div>
                </div>
            </div>
        </div>
        
        <!-- Yapılandırma Seçenekleri -->
        <?php if (!empty($configGroups)): ?>
        <div class="edit-card">
            <div class="card-header">
                <div class="icon" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);"><i class="fas fa-cogs"></i></div>
                <div><h3>Yapılandırma Seçenekleri</h3><span>Ek satın alma seçenekleri</span></div>
            </div>
            <div class="card-body">
                <div class="config-grid">
                    <?php foreach ($configGroups as $cg): 
                        $isLinked = in_array($cg['id'], $linkedConfigGroups);
                        $optCount = Database::fetchColumn("SELECT COUNT(*) FROM config_options WHERE group_id = ?", [$cg['id']]);
                    ?>
                        <label class="config-item <?= $isLinked ? 'selected' : '' ?>">
                            <input type="checkbox" name="config_groups[]" value="<?= $cg['id'] ?>" <?= $isLinked ? 'checked' : '' ?>>
                            <div class="config-item-info">
                                <strong><?= htmlspecialchars($cg['name']) ?></strong>
                                <span><?= $optCount ?> seçenek</span>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                    <a href="config-options.php" style="display: inline-flex; align-items: center; gap: 8px; color: #8b5cf6; font-size: 13px; font-weight: 600; text-decoration: none;">
                        <i class="fas fa-external-link-alt"></i> Seçenekleri Yönet
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Sağ Kolon -->
    <div>
        <!-- Önizleme -->
        <div class="sidebar-card">
            <div class="sidebar-header">
                <div class="icon"><i class="fas fa-eye"></i></div>
                <h4>Önizleme</h4>
            </div>
            <div class="sidebar-body">
                <div class="preview-box">
                    <div class="preview-icon"><i class="fas <?= $tc['icon'] ?>"></i></div>
                    <div class="preview-name"><?= htmlspecialchars($product['name']) ?></div>
                    <div class="preview-price">
                        <?= $product['price_monthly'] ? number_format((float)$product['price_monthly'], 2, ',', '.') . '₺' : '0₺' ?>
                        <span>/ aylık</span>
                    </div>
                    <?php if (!empty($features)): ?>
                    <div class="preview-features">
                        <?php foreach (array_slice($features, 0, 5) as $f): ?>
                            <div class="preview-feature"><i class="fas fa-check"></i> <?= htmlspecialchars(trim($f)) ?></div>
                        <?php endforeach; ?>
                        <?php if (count($features) > 5): ?>
                            <div class="preview-feature" style="color: #9ca3af;"><i class="fas fa-ellipsis-h"></i> +<?= count($features) - 5 ?> daha</div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- İşlemler -->
        <div class="sidebar-card">
            <div class="sidebar-header">
                <div class="icon"><i class="fas fa-bolt"></i></div>
                <h4>İşlemler</h4>
            </div>
            <div class="sidebar-body">
                <div class="action-btns">
                    <button type="submit" class="action-btn primary"><i class="fas fa-save"></i> Değişiklikleri Kaydet</button>
                    <a href="products.php" class="action-btn outline"><i class="fas fa-arrow-left"></i> Ürün Listesi</a>
                    <button type="button" onclick="if(confirm('Bu ürünü silmek istediğinizden emin misiniz?')) location.href='products.php?delete=<?= $id ?>'" class="action-btn danger"><i class="fas fa-trash"></i> Ürünü Sil</button>
                </div>
            </div>
        </div>
        
        <!-- Bilgi -->
        <div class="sidebar-card">
            <div class="sidebar-header">
                <div class="icon"><i class="fas fa-info"></i></div>
                <h4>Bilgiler</h4>
            </div>
            <div class="sidebar-body">
                <div class="info-list">
                    <div class="info-row">
                        <span class="label"><i class="fas fa-hashtag"></i> ID</span>
                        <span class="value">#<?= $product['id'] ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label"><i class="fas fa-link"></i> Slug</span>
                        <span class="value"><?= htmlspecialchars($product['slug']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label"><i class="fas fa-layer-group"></i> Tür</span>
                        <span class="value"><?= ucfirst($type) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label"><i class="fas fa-calendar-plus"></i> Oluşturulma</span>
                        <span class="value"><?= date('d.m.Y', strtotime($product['created_at'])) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</form>

<script>
// Toggle functionality - checkbox değiştiğinde güncelle
document.querySelectorAll('.toggle-item input[type="checkbox"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const item = this.closest('.toggle-item');
        
        if (this.name === 'is_active') {
            item.classList.toggle('active', this.checked);
            item.querySelector('.toggle-icon').textContent = this.checked ? '✓' : '○';
        } else if (this.name === 'is_featured') {
            item.classList.toggle('featured', this.checked);
            item.querySelector('.toggle-icon').textContent = this.checked ? '⭐' : '☆';
        } else if (this.name === 'domain_required') {
            item.classList.toggle('domain', this.checked);
            item.querySelector('.toggle-icon').textContent = this.checked ? '🌐' : '🔗';
        }
    });
});

// Config item selection
document.querySelectorAll('.config-item').forEach(item => {
    item.addEventListener('click', function(e) {
        if (e.target.tagName === 'INPUT') {
            this.classList.toggle('selected', e.target.checked);
            return;
        }
        const checkbox = this.querySelector('input[type="checkbox"]');
        checkbox.checked = !checkbox.checked;
        this.classList.toggle('selected', checkbox.checked);
    });
});

// Price box highlight
document.querySelectorAll('.price-input input').forEach(input => {
    input.addEventListener('input', function() {
        this.closest('.price-box').classList.toggle('has-value', this.value && this.value > 0);
    });
});
</script>

<?php include 'includes/footer.php'; ?>
