<?php
/**
 * WHMVM - Yapılandırılabilir Seçenekler
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Yapılandırılabilir Seçenekler';
$currentPage = 'config-options';
$db = Database::getInstance();

// Tabloları oluştur
function ensureConfigTables() {
    $sqlFile = dirname(__DIR__) . '/install/config_options_tables.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    Database::query($statement);
                } catch (Exception $e) {}
            }
        }
    }
}
ensureConfigTables();

$message = '';
$messageType = 'success';

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Grup Ekle
    if ($action === 'add_group') {
        $name = trim($_POST['group_name'] ?? '');
        $desc = trim($_POST['group_desc'] ?? '');
        if ($name) {
            Database::query("INSERT INTO config_option_groups (name, description) VALUES (?, ?)", [$name, $desc]);
            $message = 'Grup oluşturuldu!';
        }
    }
    
    // Grup Güncelle
    if ($action === 'update_group') {
        $id = (int)$_POST['group_id'];
        $name = trim($_POST['group_name'] ?? '');
        $desc = trim($_POST['group_desc'] ?? '');
        if ($name && $id) {
            Database::query("UPDATE config_option_groups SET name = ?, description = ? WHERE id = ?", [$name, $desc, $id]);
            $message = 'Grup güncellendi!';
        }
    }
    
    // Grup Sil
    if ($action === 'delete_group') {
        $id = (int)$_POST['group_id'];
        Database::query("DELETE FROM config_option_groups WHERE id = ?", [$id]);
        $message = 'Grup silindi!';
    }
    
    // Seçenek Ekle
    if ($action === 'add_option') {
        $groupId = (int)$_POST['group_id'];
        $name = trim($_POST['option_name'] ?? '');
        $type = $_POST['option_type'] ?? 'dropdown';
        $required = isset($_POST['required']) ? 1 : 0;
        if ($name && $groupId) {
            Database::query(
                "INSERT INTO config_options (group_id, name, option_type, required) VALUES (?, ?, ?, ?)",
                [$groupId, $name, $type, $required]
            );
            $message = 'Seçenek eklendi!';
        }
    }
    
    // Seçenek Güncelle
    if ($action === 'update_option') {
        $id = (int)$_POST['option_id'];
        $name = trim($_POST['option_name'] ?? '');
        $type = $_POST['option_type'] ?? 'dropdown';
        $required = isset($_POST['required']) ? 1 : 0;
        if ($name && $id) {
            Database::query(
                "UPDATE config_options SET name = ?, option_type = ?, required = ? WHERE id = ?",
                [$name, $type, $required, $id]
            );
            $message = 'Seçenek güncellendi!';
        }
    }
    
    // Seçenek Sil
    if ($action === 'delete_option') {
        $id = (int)$_POST['option_id'];
        Database::query("DELETE FROM config_options WHERE id = ?", [$id]);
        $message = 'Seçenek silindi!';
    }
    
    // Değer Ekle
    if ($action === 'add_value') {
        $optionId = (int)$_POST['option_id'];
        $name = trim($_POST['value_name'] ?? '');
        $priceMonthly = (float)($_POST['price_monthly'] ?? 0);
        $priceAnnually = (float)($_POST['price_annually'] ?? 0);
        $setup = (float)($_POST['setup_fee'] ?? 0);
        $isDefault = isset($_POST['is_default']) ? 1 : 0;
        
        if ($name && $optionId) {
            if ($isDefault) {
                Database::query("UPDATE config_option_values SET is_default = 0 WHERE option_id = ?", [$optionId]);
            }
            Database::query(
                "INSERT INTO config_option_values (option_id, name, price_monthly, price_annually, setup_fee, is_default) VALUES (?, ?, ?, ?, ?, ?)",
                [$optionId, $name, $priceMonthly, $priceAnnually, $setup, $isDefault]
            );
            $message = 'Değer eklendi!';
        }
    }
    
    // Değer Güncelle
    if ($action === 'update_value') {
        $id = (int)$_POST['value_id'];
        $optionId = (int)$_POST['option_id'];
        $name = trim($_POST['value_name'] ?? '');
        $priceMonthly = (float)($_POST['price_monthly'] ?? 0);
        $priceAnnually = (float)($_POST['price_annually'] ?? 0);
        $setup = (float)($_POST['setup_fee'] ?? 0);
        $isDefault = isset($_POST['is_default']) ? 1 : 0;
        
        if ($name && $id) {
            if ($isDefault) {
                Database::query("UPDATE config_option_values SET is_default = 0 WHERE option_id = ?", [$optionId]);
            }
            Database::query(
                "UPDATE config_option_values SET name = ?, price_monthly = ?, price_annually = ?, setup_fee = ?, is_default = ? WHERE id = ?",
                [$name, $priceMonthly, $priceAnnually, $setup, $isDefault, $id]
            );
            $message = 'Değer güncellendi!';
        }
    }
    
    // Değer Sil
    if ($action === 'delete_value') {
        $id = (int)$_POST['value_id'];
        Database::query("DELETE FROM config_option_values WHERE id = ?", [$id]);
        $message = 'Değer silindi!';
    }
    
    // POST-Redirect-GET
    $_SESSION['config_message'] = $message;
    $redirectUrl = 'config-options.php';
    if (isset($_GET['group'])) {
        $redirectUrl .= '?group=' . $_GET['group'];
    }
    header('Location: ' . $redirectUrl);
    exit;
}

// Session'dan mesaj
if (isset($_SESSION['config_message'])) {
    $message = $_SESSION['config_message'];
    unset($_SESSION['config_message']);
}

// Grupları çek
$groups = Database::fetchAll("SELECT * FROM config_option_groups ORDER BY name");

// Seçili grup
$selectedGroupId = isset($_GET['group']) ? (int)$_GET['group'] : ($groups[0]['id'] ?? 0);
$selectedGroup = null;
$options = [];

if ($selectedGroupId) {
    $selectedGroup = Database::fetch("SELECT * FROM config_option_groups WHERE id = ?", [$selectedGroupId]);
    $options = Database::fetchAll("SELECT * FROM config_options WHERE group_id = ? ORDER BY order_priority, name", [$selectedGroupId]);
    
    // Her seçeneğin değerlerini al
    foreach ($options as &$option) {
        $option['values'] = Database::fetchAll(
            "SELECT * FROM config_option_values WHERE option_id = ? ORDER BY order_priority, name",
            [$option['id']]
        );
    }
}

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Layout */
.config-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 25px;
    align-items: start;
}

@media (max-width: 992px) {
    .config-layout {
        grid-template-columns: 1fr;
    }
}

/* Sidebar */
.config-sidebar {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    overflow: hidden;
}

.sidebar-header {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #fff;
    padding: 20px;
}

.sidebar-header h3 {
    font-size: 16px;
    font-weight: 700;
    margin: 0 0 5px 0;
}

.sidebar-header p {
    font-size: 13px;
    opacity: 0.8;
    margin: 0;
}

.group-list {
    padding: 15px;
}

.group-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    margin-bottom: 8px;
    text-decoration: none;
    color: #475569;
}

.group-item:hover {
    background: #f1f5f9;
}

.group-item.active {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
    color: #6366f1;
}

.group-item .group-name {
    font-weight: 600;
    font-size: 14px;
}

.group-item .group-count {
    background: #e2e8f0;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.group-item.active .group-count {
    background: #6366f1;
    color: #fff;
}

.add-group-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 14px;
    background: #f8fafc;
    border: 2px dashed #e2e8f0;
    border-radius: 12px;
    color: #64748b;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.add-group-btn:hover {
    border-color: #6366f1;
    color: #6366f1;
    background: rgba(99, 102, 241, 0.05);
}

/* Main Content */
.config-main {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    overflow: hidden;
}

.main-header {
    padding: 25px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.main-header h2 {
    font-size: 20px;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.main-header h2 i {
    color: #6366f1;
}

.header-actions {
    display: flex;
    gap: 10px;
}

/* Options */
.options-list {
    padding: 20px;
}

.option-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    margin-bottom: 20px;
    overflow: hidden;
}

.option-header {
    padding: 18px 20px;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.option-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.option-icon {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 18px;
}

.option-details h4 {
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 4px 0;
}

.option-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 13px;
    color: #64748b;
}

.option-meta .badge {
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.badge-dropdown { background: #dbeafe; color: #1d4ed8; }
.badge-radio { background: #d1fae5; color: #047857; }
.badge-checkbox { background: #fef3c7; color: #b45309; }
.badge-quantity { background: #ede9fe; color: #6d28d9; }
.badge-required { background: #fee2e2; color: #dc2626; }

.option-actions {
    display: flex;
    gap: 8px;
}

.option-actions button {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.option-actions .btn-edit {
    background: #dbeafe;
    color: #1d4ed8;
}

.option-actions .btn-delete {
    background: #fee2e2;
    color: #dc2626;
}

.option-actions button:hover {
    transform: scale(1.1);
}

/* Values Table */
.values-table {
    margin: 0;
}

.values-table table {
    width: 100%;
    border-collapse: collapse;
}

.values-table th {
    padding: 12px 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    background: #f1f5f9;
}

.values-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 14px;
    color: #334155;
}

.values-table tr:last-child td {
    border-bottom: none;
}

.values-table .value-name {
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.values-table .default-badge {
    background: #10b981;
    color: #fff;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
}

.values-table .price {
    font-weight: 700;
    color: #10b981;
}

.values-table .price.zero {
    color: #94a3b8;
}

.add-value-row {
    padding: 15px 20px;
    background: #fff;
    border-top: 1px solid #e2e8f0;
}

.add-value-row button {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: transparent;
    border: 2px dashed #e2e8f0;
    border-radius: 10px;
    color: #64748b;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.add-value-row button:hover {
    border-color: #6366f1;
    color: #6366f1;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state .empty-icon {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 25px;
    font-size: 40px;
}

.empty-state h3 {
    font-size: 20px;
    color: #1e293b;
    margin: 0 0 10px 0;
}

.empty-state p {
    color: #64748b;
    margin: 0 0 25px 0;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal.show {
    display: flex;
}

.modal-content {
    background: #fff;
    border-radius: 20px;
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalIn 0.3s ease;
}

@keyframes modalIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

.modal-header {
    padding: 25px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-header h3 i {
    color: #6366f1;
}

.modal-close {
    width: 36px;
    height: 36px;
    border: none;
    background: #f1f5f9;
    border-radius: 10px;
    cursor: pointer;
    color: #64748b;
    transition: all 0.3s;
}

.modal-close:hover {
    background: #e2e8f0;
}

.modal-body {
    padding: 25px;
}

.modal-footer {
    padding: 20px 25px;
    background: #f8fafc;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

/* Forms */
.form-group {
    margin-bottom: 20px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 8px;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 14px;
    transition: all 0.3s;
}

.form-control:focus {
    border-color: #6366f1;
    outline: none;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.checkbox-label input {
    width: 18px;
    height: 18px;
    accent-color: #6366f1;
}

/* Buttons */
.btn {
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
}

.btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
}

.btn-primary:hover {
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
}

.btn-outline {
    background: #fff;
    border: 2px solid #e2e8f0;
    color: #64748b;
}

.btn-outline:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
}

.btn-danger {
    background: #ef4444;
    color: #fff;
}

.btn-sm {
    padding: 8px 14px;
    font-size: 13px;
}

/* Alert */
.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
}
</style>

<?php if ($message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="config-layout">
    <!-- Sidebar - Gruplar -->
    <div class="config-sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-cogs"></i> Seçenek Grupları</h3>
            <p>Yapılandırılabilir seçenekleri yönetin</p>
        </div>
        <div class="group-list">
            <?php foreach ($groups as $group): 
                $optionCount = Database::fetchColumn("SELECT COUNT(*) FROM config_options WHERE group_id = ?", [$group['id']]);
            ?>
                <a href="?group=<?= $group['id'] ?>" class="group-item <?= $selectedGroupId == $group['id'] ? 'active' : '' ?>">
                    <span class="group-name"><?= htmlspecialchars($group['name']) ?></span>
                    <span class="group-count"><?= $optionCount ?></span>
                </a>
            <?php endforeach; ?>
            
            <button type="button" class="add-group-btn" onclick="openModal('groupModal')">
                <i class="fas fa-plus"></i> Yeni Grup
            </button>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="config-main">
        <?php if ($selectedGroup): ?>
            <div class="main-header">
                <h2>
                    <i class="fas fa-sliders-h"></i>
                    <?= htmlspecialchars($selectedGroup['name']) ?>
                </h2>
                <div class="header-actions">
                    <button class="btn btn-outline btn-sm" onclick="editGroup(<?= $selectedGroup['id'] ?>, '<?= htmlspecialchars($selectedGroup['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($selectedGroup['description'] ?? '', ENT_QUOTES) ?>')">
                        <i class="fas fa-edit"></i> Düzenle
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="openModal('optionModal')">
                        <i class="fas fa-plus"></i> Seçenek Ekle
                    </button>
                </div>
            </div>
            
            <div class="options-list">
                <?php if (empty($options)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">⚙️</div>
                        <h3>Henüz seçenek yok</h3>
                        <p>Bu gruba yapılandırılabilir seçenekler ekleyin</p>
                        <button class="btn btn-primary" onclick="openModal('optionModal')">
                            <i class="fas fa-plus"></i> İlk Seçeneği Ekle
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($options as $option): 
                        $typeIcon = match($option['option_type']) {
                            'dropdown' => 'fa-caret-down',
                            'radio' => 'fa-dot-circle',
                            'checkbox' => 'fa-check-square',
                            'quantity' => 'fa-hashtag',
                            default => 'fa-list'
                        };
                    ?>
                        <div class="option-card">
                            <div class="option-header">
                                <div class="option-info">
                                    <div class="option-icon">
                                        <i class="fas <?= $typeIcon ?>"></i>
                                    </div>
                                    <div class="option-details">
                                        <h4><?= htmlspecialchars($option['name']) ?></h4>
                                        <div class="option-meta">
                                            <span class="badge badge-<?= $option['option_type'] ?>"><?= ucfirst($option['option_type']) ?></span>
                                            <?php if ($option['required']): ?>
                                                <span class="badge badge-required">Zorunlu</span>
                                            <?php endif; ?>
                                            <span><?= count($option['values']) ?> değer</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="option-actions">
                                    <button class="btn-edit" onclick="editOption(<?= $option['id'] ?>, '<?= htmlspecialchars($option['name'], ENT_QUOTES) ?>', '<?= $option['option_type'] ?>', <?= $option['required'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-delete" onclick="deleteOption(<?= $option['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <?php if (!empty($option['values'])): ?>
                                <div class="values-table">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Değer</th>
                                                <th>Aylık</th>
                                                <th>Yıllık</th>
                                                <th>Kurulum</th>
                                                <th style="width: 100px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($option['values'] as $value): ?>
                                                <tr>
                                                    <td>
                                                        <span class="value-name">
                                                            <?= htmlspecialchars($value['name']) ?>
                                                            <?php if ($value['is_default']): ?>
                                                                <span class="default-badge">Varsayılan</span>
                                                            <?php endif; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="price <?= $value['price_monthly'] == 0 ? 'zero' : '' ?>">
                                                            <?= $value['price_monthly'] > 0 ? '+' . number_format((float)$value['price_monthly'], 2) . ' ₺' : 'Ücretsiz' ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="price <?= $value['price_annually'] == 0 ? 'zero' : '' ?>">
                                                            <?= $value['price_annually'] > 0 ? '+' . number_format((float)$value['price_annually'], 2) . ' ₺' : '-' ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="price <?= $value['setup_fee'] == 0 ? 'zero' : '' ?>">
                                                            <?= $value['setup_fee'] > 0 ? '+' . number_format((float)$value['setup_fee'], 2) . ' ₺' : '-' ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="option-actions">
                                                            <button class="btn-edit" onclick="editValue(<?= $value['id'] ?>, <?= $option['id'] ?>, '<?= htmlspecialchars($value['name'], ENT_QUOTES) ?>', <?= $value['price_monthly'] ?>, <?= $value['price_annually'] ?>, <?= $value['setup_fee'] ?>, <?= $value['is_default'] ?>)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn-delete" onclick="deleteValue(<?= $value['id'] ?>)">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                            
                            <div class="add-value-row">
                                <button type="button" onclick="addValue(<?= $option['id'] ?>)">
                                    <i class="fas fa-plus"></i> Değer Ekle
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="empty-state" style="padding: 100px 20px;">
                <div class="empty-icon">📦</div>
                <h3>Grup Seçilmedi</h3>
                <p>Sol menüden bir grup seçin veya yeni grup oluşturun</p>
                <button class="btn btn-primary" onclick="openModal('groupModal')">
                    <i class="fas fa-plus"></i> Yeni Grup Oluştur
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Grup Modal -->
<div id="groupModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-folder-plus"></i> <span id="groupModalTitle">Yeni Grup</span></h3>
            <button type="button" class="modal-close" onclick="closeModal('groupModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="groupForm">
            <input type="hidden" name="action" id="groupAction" value="add_group">
            <input type="hidden" name="group_id" id="editGroupId" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label>Grup Adı</label>
                    <input type="text" name="group_name" id="groupName" class="form-control" placeholder="Örn: Sunucu Özellikleri" required>
                </div>
                <div class="form-group">
                    <label>Açıklama (Opsiyonel)</label>
                    <textarea name="group_desc" id="groupDesc" class="form-control" rows="3" placeholder="Bu grup hakkında kısa bilgi..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('groupModal')">İptal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Seçenek Modal -->
<div id="optionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> <span id="optionModalTitle">Yeni Seçenek</span></h3>
            <button type="button" class="modal-close" onclick="closeModal('optionModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="optionForm">
            <input type="hidden" name="action" id="optionAction" value="add_option">
            <input type="hidden" name="group_id" value="<?= $selectedGroupId ?>">
            <input type="hidden" name="option_id" id="editOptionId" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label>Seçenek Adı</label>
                    <input type="text" name="option_name" id="optionName" class="form-control" placeholder="Örn: RAM Miktarı" required>
                </div>
                <div class="form-group">
                    <label>Seçim Tipi</label>
                    <select name="option_type" id="optionType" class="form-control">
                        <option value="dropdown">Dropdown (Tek Seçim)</option>
                        <option value="radio">Radio Button (Tek Seçim)</option>
                        <option value="checkbox">Checkbox (Çoklu Seçim)</option>
                        <option value="quantity">Miktar (Sayı Girişi)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="required" id="optionRequired">
                        <span>Zorunlu seçenek</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('optionModal')">İptal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Değer Modal -->
<div id="valueModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-tag"></i> <span id="valueModalTitle">Yeni Değer</span></h3>
            <button type="button" class="modal-close" onclick="closeModal('valueModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="valueForm">
            <input type="hidden" name="action" id="valueAction" value="add_value">
            <input type="hidden" name="option_id" id="valueOptionId" value="">
            <input type="hidden" name="value_id" id="editValueId" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label>Değer Adı</label>
                    <input type="text" name="value_name" id="valueName" class="form-control" placeholder="Örn: 32 GB RAM" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Aylık Ek Ücret</label>
                        <input type="number" name="price_monthly" id="valueMonthly" class="form-control" step="0.01" value="0" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label>Yıllık Ek Ücret</label>
                        <input type="number" name="price_annually" id="valueAnnually" class="form-control" step="0.01" value="0" placeholder="0.00">
                    </div>
                </div>
                <div class="form-group">
                    <label>Kurulum Ücreti</label>
                    <input type="number" name="setup_fee" id="valueSetup" class="form-control" step="0.01" value="0" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_default" id="valueDefault">
                        <span>Varsayılan değer olarak ayarla</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('valueModal')">İptal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Silme Formu -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" id="deleteAction" value="">
    <input type="hidden" name="group_id" id="deleteGroupId" value="">
    <input type="hidden" name="option_id" id="deleteOptionId" value="">
    <input type="hidden" name="value_id" id="deleteValueId" value="">
</form>

<script>
function openModal(id) {
    document.getElementById(id).classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
    // Reset forms
    if (id === 'groupModal') {
        document.getElementById('groupAction').value = 'add_group';
        document.getElementById('editGroupId').value = '';
        document.getElementById('groupName').value = '';
        document.getElementById('groupDesc').value = '';
        document.getElementById('groupModalTitle').textContent = 'Yeni Grup';
    }
    if (id === 'optionModal') {
        document.getElementById('optionAction').value = 'add_option';
        document.getElementById('editOptionId').value = '';
        document.getElementById('optionName').value = '';
        document.getElementById('optionType').value = 'dropdown';
        document.getElementById('optionRequired').checked = false;
        document.getElementById('optionModalTitle').textContent = 'Yeni Seçenek';
    }
    if (id === 'valueModal') {
        document.getElementById('valueAction').value = 'add_value';
        document.getElementById('editValueId').value = '';
        document.getElementById('valueName').value = '';
        document.getElementById('valueMonthly').value = '0';
        document.getElementById('valueAnnually').value = '0';
        document.getElementById('valueSetup').value = '0';
        document.getElementById('valueDefault').checked = false;
        document.getElementById('valueModalTitle').textContent = 'Yeni Değer';
    }
}

function editGroup(id, name, desc) {
    document.getElementById('groupAction').value = 'update_group';
    document.getElementById('editGroupId').value = id;
    document.getElementById('groupName').value = name;
    document.getElementById('groupDesc').value = desc;
    document.getElementById('groupModalTitle').textContent = 'Grubu Düzenle';
    openModal('groupModal');
}

function editOption(id, name, type, required) {
    document.getElementById('optionAction').value = 'update_option';
    document.getElementById('editOptionId').value = id;
    document.getElementById('optionName').value = name;
    document.getElementById('optionType').value = type;
    document.getElementById('optionRequired').checked = required == 1;
    document.getElementById('optionModalTitle').textContent = 'Seçeneği Düzenle';
    openModal('optionModal');
}

function deleteOption(id) {
    if (confirm('Bu seçeneği ve tüm değerlerini silmek istediğinizden emin misiniz?')) {
        document.getElementById('deleteAction').value = 'delete_option';
        document.getElementById('deleteOptionId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

function addValue(optionId) {
    document.getElementById('valueOptionId').value = optionId;
    document.getElementById('valueAction').value = 'add_value';
    openModal('valueModal');
}

function editValue(id, optionId, name, monthly, annually, setup, isDefault) {
    document.getElementById('valueAction').value = 'update_value';
    document.getElementById('editValueId').value = id;
    document.getElementById('valueOptionId').value = optionId;
    document.getElementById('valueName').value = name;
    document.getElementById('valueMonthly').value = monthly;
    document.getElementById('valueAnnually').value = annually;
    document.getElementById('valueSetup').value = setup;
    document.getElementById('valueDefault').checked = isDefault == 1;
    document.getElementById('valueModalTitle').textContent = 'Değeri Düzenle';
    openModal('valueModal');
}

function deleteValue(id) {
    if (confirm('Bu değeri silmek istediğinizden emin misiniz?')) {
        document.getElementById('deleteAction').value = 'delete_value';
        document.getElementById('deleteValueId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

// Close modal on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal(this.id);
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>

