<?php
/**
 * WHMVM - Ürün Yönetimi
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
session_name(SESSION_NAME); session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Ürünler';
$currentPage = 'products';
$db = Database::getInstance();
$message = '';
$messageType = 'success';

// Silme
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    Database::query("DELETE FROM products WHERE id = ?", [$_GET['delete']]);
    $message = 'Ürün silindi.';
}

// Ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $groupId = $_POST['group_id'] ?: null;
    
    // Grubun türünü al
    $type = 'other';
    if ($groupId) {
        $groupType = Database::fetchColumn("SELECT type FROM product_groups WHERE id = ?", [$groupId]);
        if ($groupType) {
            $type = $groupType;
        }
    }
    
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $_POST['name']));
    Database::query(
        "INSERT INTO products (name, slug, type, group_id, description, price_monthly, price_annually, setup_fee, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $_POST['name'],
            $slug,
            $type,
            $groupId,
            $_POST['description'] ?? '',
            $_POST['price_monthly'] ?: null,
            $_POST['price_annually'] ?: null,
            $_POST['setup_fee'] ?: 0,
            isset($_POST['is_active']) ? 1 : 0
        ]
    );
    $message = 'Ürün eklendi!';
}

// Grupları çek
$groups = Database::fetchAll("SELECT * FROM product_groups ORDER BY order_priority");

// Ürünleri çek
$products = Database::fetchAll("
    SELECT p.*, g.name as group_name, g.type as group_type, g.slug as group_slug
    FROM products p 
    LEFT JOIN product_groups g ON p.group_id = g.id 
    ORDER BY g.order_priority, p.order_priority, p.name
");

// İstatistikler
$totalProducts = count($products);
$activeProducts = count(array_filter($products, fn($p) => $p['is_active']));
$totalGroups = count($groups);

// Tür bazlı sayılar
$typeStats = [];
foreach ($products as $p) {
    $type = $p['group_type'] ?? $p['type'] ?? 'other';
    $typeStats[$type] = ($typeStats[$type] ?? 0) + 1;
}

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 576px) {
    .stats-grid { grid-template-columns: 1fr; }
}

.stat-card {
    background: linear-gradient(135deg, #f8fafc 0%, #fff 100%);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.stat-icon.blue { background: linear-gradient(135deg, #6366f1 0%, #818cf8 100%); }
.stat-icon.green { background: linear-gradient(135deg, #10b981 0%, #34d399 100%); }
.stat-icon.orange { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); }
.stat-icon.purple { background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%); }

.stat-info h3 {
    font-size: 28px;
    font-weight: 800;
    color: var(--dark);
    margin-bottom: 4px;
}

.stat-info p {
    font-size: 13px;
    color: var(--gray);
    margin: 0;
}

/* Filter Tabs */
.filter-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.filter-tabs {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 10px 18px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: white;
    color: var(--gray);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-tab:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.filter-tab.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.filter-tab .count {
    background: rgba(0,0,0,0.1);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
}

.filter-tab.active .count {
    background: rgba(255,255,255,0.2);
}

.search-box {
    position: relative;
}

.search-box input {
    padding: 10px 15px 10px 40px;
    border-radius: 10px;
    border: 1px solid var(--border);
    width: 250px;
    font-size: 14px;
    transition: all 0.3s;
}

.search-box input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    outline: none;
}

.search-box i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray);
}

/* Product Cards View */
.view-toggle {
    display: flex;
    gap: 5px;
    background: #f1f5f9;
    padding: 4px;
    border-radius: 8px;
}

.view-btn {
    padding: 8px 12px;
    border: none;
    background: transparent;
    color: var(--gray);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s;
}

.view-btn.active {
    background: white;
    color: var(--primary);
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

/* Products Grid */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}

.product-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    border-color: var(--primary);
}

.product-card-header {
    padding: 20px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.product-card-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: white;
}

.product-card-icon.hosting { background: linear-gradient(135deg, #6366f1 0%, #818cf8 100%); }
.product-card-icon.vps { background: linear-gradient(135deg, #10b981 0%, #34d399 100%); }
.product-card-icon.vds { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); }
.product-card-icon.dedicated { background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%); }
.product-card-icon.domain { background: linear-gradient(135deg, #0ea5e9 0%, #38bdf8 100%); }
.product-card-icon.ssl { background: linear-gradient(135deg, #10b981 0%, #34d399 100%); }
.product-card-icon.other { background: linear-gradient(135deg, #64748b 0%, #94a3b8 100%); }

.product-card-status {
    display: flex;
    gap: 6px;
}

.product-card-body {
    padding: 20px;
}

.product-card-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 6px;
}

.product-card-group {
    font-size: 13px;
    color: var(--gray);
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.product-card-prices {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.price-item {
    flex: 1;
    text-align: center;
    padding: 12px;
    background: #f8fafc;
    border-radius: 10px;
}

.price-item .label {
    font-size: 11px;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.price-item .value {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary);
}

.price-item .value.muted {
    color: var(--gray);
    font-size: 14px;
}

.product-card-actions {
    display: flex;
    gap: 10px;
    padding-top: 15px;
    border-top: 1px solid var(--border);
}

.product-card-actions .btn {
    flex: 1;
    justify-content: center;
    padding: 10px;
    font-size: 13px;
}

/* Enhanced Table */
.products-table {
    display: none;
}

.products-table.active {
    display: block;
}

.products-grid.active {
    display: grid;
}

.table-enhanced {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.table-enhanced thead th {
    background: #f8fafc;
    padding: 14px 16px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--gray);
    border-bottom: 2px solid var(--border);
    text-align: left;
}

.table-enhanced tbody tr {
    transition: all 0.3s;
}

.table-enhanced tbody tr:hover {
    background: #f8fafc;
}

.table-enhanced tbody td {
    padding: 16px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.product-info {
    display: flex;
    align-items: center;
    gap: 14px;
}

.product-info-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: white;
    flex-shrink: 0;
}

.product-info-text h4 {
    font-size: 15px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 3px;
}

.product-info-text span {
    font-size: 12px;
    color: var(--gray);
}

.price-cell {
    font-weight: 600;
    color: var(--dark);
}

.price-cell.muted {
    color: var(--gray);
    font-weight: 400;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

/* Modal Enhancements */
.modal.show { display: flex; }

.modal-content {
    background: white;
    border-radius: 16px;
    max-width: 550px;
    width: 100%;
}

.modal-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 20px 25px;
    border-radius: 16px 16px 0 0;
    border-bottom: 1px solid var(--border);
}

.modal-header h3 {
    color: var(--dark);
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-body {
    padding: 25px;
}

.modal-body .form-group label {
    color: var(--dark);
    font-weight: 600;
    margin-bottom: 8px;
    display: block;
}

.modal-footer {
    padding: 20px 25px;
    background: #f8fafc;
    border-radius: 0 0 16px 16px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Empty State */
.empty-state-modern {
    text-align: center;
    padding: 60px 20px;
}

.empty-state-modern .icon {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 25px;
    font-size: 40px;
}

.empty-state-modern h3 {
    font-size: 20px;
    color: var(--dark);
    margin-bottom: 10px;
}

.empty-state-modern p {
    color: var(--gray);
    margin-bottom: 25px;
}

/* Badge */
.badge-sm {
    padding: 4px 10px;
    font-size: 11px;
    border-radius: 6px;
}

.badge-active {
    background: #d1fae5;
    color: #065f46;
}

.badge-inactive {
    background: #fee2e2;
    color: #991b1b;
}

.badge-featured {
    background: #fef3c7;
    color: #92400e;
}
</style>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-box"></i>
        </div>
        <div class="stat-info">
            <h3><?= $totalProducts ?></h3>
            <p>Toplam Ürün</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?= $activeProducts ?></h3>
            <p>Aktif Ürün</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-folder"></i>
        </div>
        <div class="stat-info">
            <h3><?= $totalGroups ?></h3>
            <p>Ürün Grubu</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-globe"></i>
        </div>
        <div class="stat-info">
            <h3><?= $typeStats['hosting'] ?? 0 ?></h3>
            <p>Hosting Ürünü</p>
        </div>
    </div>
</div>

<!-- Filter & Actions -->
<div class="card" style="margin-bottom: 25px;">
    <div class="card-header">
        <h3><i class="fas fa-box" style="color: var(--primary); margin-right: 10px;"></i> Ürünler</h3>
        <a href="product-add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Yeni Ürün
        </a>
    </div>
    <div class="card-body" style="padding: 15px 20px;">
        <div class="filter-section">
            <div class="filter-tabs">
                <button class="filter-tab active" data-filter="all">
                    <i class="fas fa-th"></i> Tümü
                    <span class="count"><?= $totalProducts ?></span>
                </button>
                <?php foreach ($groups as $group): 
                    $count = count(array_filter($products, fn($p) => $p['group_id'] == $group['id']));
                    if ($count > 0):
                        $emoji = match($group['type'] ?? 'other') {
                            'hosting' => '🌐',
                            'vps' => '💻',
                            'vds' => '🖥️',
                            'dedicated' => '🏢',
                            'domain' => '🔗',
                            'ssl' => '🔒',
                            default => '📦'
                        };
                ?>
                    <button class="filter-tab" data-filter="group-<?= $group['id'] ?>">
                        <?= $emoji ?> <?= htmlspecialchars($group['name']) ?>
                        <span class="count"><?= $count ?></span>
                    </button>
                <?php endif; endforeach; ?>
            </div>
            <div style="display: flex; gap: 15px; align-items: center;">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Ürün ara...">
                </div>
                <div class="view-toggle">
                    <button class="view-btn active" data-view="grid" title="Kart Görünümü">
                        <i class="fas fa-th-large"></i>
                    </button>
                    <button class="view-btn" data-view="table" title="Tablo Görünümü">
                        <i class="fas fa-list"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($products)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state-modern">
                <div class="icon">📦</div>
                <h3>Henüz ürün eklenmemiş</h3>
                <p>İlk ürününüzü ekleyerek başlayın. Önce bir ürün grubu oluşturmanız gerekebilir.</p>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <a href="product-groups.php" class="btn btn-outline">
                        <i class="fas fa-folder-plus"></i> Grup Oluştur
                    </a>
                    <a href="product-add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Ürün Ekle
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>

<!-- Grid View -->
<div class="products-grid active" id="gridView">
    <?php foreach ($products as $product): 
        $type = $product['group_type'] ?? $product['type'] ?? 'other';
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
        <div class="product-card" data-group="<?= $product['group_id'] ?>" data-name="<?= strtolower($product['name']) ?>">
            <div class="product-card-header">
                <div class="product-card-icon <?= $type ?>">
                    <i class="fas <?= $typeIcon ?>"></i>
                </div>
                <div class="product-card-status">
                    <?php if ($product['is_active']): ?>
                        <span class="badge badge-sm badge-active">Aktif</span>
                    <?php else: ?>
                        <span class="badge badge-sm badge-inactive">Pasif</span>
                    <?php endif; ?>
                    <?php if ($product['is_featured'] ?? false): ?>
                        <span class="badge badge-sm badge-featured">⭐</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="product-card-body">
                <h4 class="product-card-title"><?= htmlspecialchars($product['name']) ?></h4>
                <div class="product-card-group">
                    <?php if ($product['group_name']): ?>
                        <i class="fas fa-folder" style="color: var(--primary);"></i>
                        <?= htmlspecialchars($product['group_name']) ?>
                    <?php else: ?>
                        <i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i>
                        <span style="color: #f59e0b;">Grup atanmamış</span>
                    <?php endif; ?>
                </div>
                <div class="product-card-prices">
                    <div class="price-item">
                        <div class="label">Aylık</div>
                        <div class="value <?= !$product['price_monthly'] ? 'muted' : '' ?>">
                            <?= $product['price_monthly'] ? number_format((float)$product['price_monthly'], 0) . '₺' : '-' ?>
                        </div>
                    </div>
                    <div class="price-item">
                        <div class="label">Yıllık</div>
                        <div class="value <?= !$product['price_annually'] ? 'muted' : '' ?>">
                            <?= $product['price_annually'] ? number_format((float)$product['price_annually'], 0) . '₺' : '-' ?>
                        </div>
                    </div>
                    <div class="price-item">
                        <div class="label">Kurulum</div>
                        <div class="value <?= !$product['setup_fee'] ? 'muted' : '' ?>">
                            <?= $product['setup_fee'] ? number_format((float)$product['setup_fee'], 0) . '₺' : 'Ücretsiz' ?>
                        </div>
                    </div>
                </div>
                <div class="product-card-actions">
                    <a href="product-edit.php?id=<?= $product['id'] ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-edit"></i> Düzenle
                    </a>
                    <button onclick="confirmDelete('Bu ürünü silmek istediğinizden emin misiniz?', '?delete=<?= $product['id'] ?>')" class="btn btn-danger btn-sm">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Table View -->
<div class="products-table card" id="tableView">
    <div class="card-body" style="padding: 0;">
        <table class="table-enhanced">
            <thead>
                <tr>
                    <th>Ürün</th>
                    <th>Grup</th>
                    <th>Aylık</th>
                    <th>Yıllık</th>
                    <th>Kurulum</th>
                    <th>Durum</th>
                    <th style="text-align: right;">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): 
                    $type = $product['group_type'] ?? $product['type'] ?? 'other';
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
                    <tr data-group="<?= $product['group_id'] ?>" data-name="<?= strtolower($product['name']) ?>">
                        <td>
                            <div class="product-info">
                                <div class="product-info-icon <?= $type ?>">
                                    <i class="fas <?= $typeIcon ?>"></i>
                                </div>
                                <div class="product-info-text">
                                    <h4><?= htmlspecialchars($product['name']) ?></h4>
                                    <span>#<?= $product['id'] ?> • <?= htmlspecialchars($product['slug']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($product['group_name']): ?>
                                <?= htmlspecialchars($product['group_name']) ?>
                            <?php else: ?>
                                <span style="color: #f59e0b;">⚠️ Yok</span>
                            <?php endif; ?>
                        </td>
                        <td class="price-cell <?= !$product['price_monthly'] ? 'muted' : '' ?>">
                            <?= $product['price_monthly'] ? number_format((float)$product['price_monthly'], 2) . ' ₺' : '-' ?>
                        </td>
                        <td class="price-cell <?= !$product['price_annually'] ? 'muted' : '' ?>">
                            <?= $product['price_annually'] ? number_format((float)$product['price_annually'], 2) . ' ₺' : '-' ?>
                        </td>
                        <td class="price-cell <?= !$product['setup_fee'] ? 'muted' : '' ?>">
                            <?= $product['setup_fee'] ? number_format((float)$product['setup_fee'], 2) . ' ₺' : 'Ücretsiz' ?>
                        </td>
                        <td>
                            <?php if ($product['is_active']): ?>
                                <span class="badge badge-sm badge-active">Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-sm badge-inactive">Pasif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons" style="justify-content: flex-end;">
                                <a href="product-edit.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-outline">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="confirmDelete('Bu ürünü silmek istediğinizden emin misiniz?', '?delete=<?= $product['id'] ?>')" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<script>
// View Toggle
document.querySelectorAll('.view-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        const view = this.dataset.view;
        if (view === 'grid') {
            document.getElementById('gridView').classList.add('active');
            document.getElementById('tableView').classList.remove('active');
        } else {
            document.getElementById('gridView').classList.remove('active');
            document.getElementById('tableView').classList.add('active');
        }
    });
});

// Filter Tabs
document.querySelectorAll('.filter-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        const filter = this.dataset.filter;
        const cards = document.querySelectorAll('.product-card, .products-table tbody tr');
        
        cards.forEach(card => {
            if (filter === 'all') {
                card.style.display = '';
            } else {
                const groupId = card.dataset.group;
                const filterGroupId = filter.replace('group-', '');
                card.style.display = groupId === filterGroupId ? '' : 'none';
            }
        });
    });
});

// Search
document.getElementById('searchInput').addEventListener('input', function() {
    const search = this.value.toLowerCase();
    const items = document.querySelectorAll('.product-card, .products-table tbody tr');
    
    items.forEach(item => {
        const name = item.dataset.name || '';
        item.style.display = name.includes(search) ? '' : 'none';
    });
});

// Modal outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
