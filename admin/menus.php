<?php
/**
 * WHMVM - Menü Yönetimi
 * Admin panelinden frontend menüsünü yönetme
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
session_name(SESSION_NAME);
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Menü Yönetimi';
$currentPage = 'menus';
$db = Database::getInstance();
$message = '';
$messageType = 'success';

// Menü tablosunu oluştur (yoksa)
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `menu_items` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `menu_type` ENUM('main', 'footer', 'social') NOT NULL DEFAULT 'main',
            `parent_id` INT UNSIGNED DEFAULT NULL,
            `title` VARCHAR(255) NOT NULL,
            `url` VARCHAR(500) NOT NULL,
            `icon` VARCHAR(100) DEFAULT NULL,
            `description` TEXT,
            `target` ENUM('_self', '_blank') DEFAULT '_self',
            `sort_order` INT DEFAULT 0,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_menu_type` (`menu_type`),
            INDEX `idx_parent` (`parent_id`),
            INDEX `idx_order` (`sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (Exception $e) {
    // Tablo zaten varsa devam et
}

// Varsayılan menü öğelerini ekle (tablo boşsa)
$menuCount = Database::fetchColumn("SELECT COUNT(*) FROM menu_items WHERE menu_type = 'main'");
if ($menuCount == 0) {
    $defaultMenus = [
        ['main', null, 'Ana Sayfa', 'index.php', 'fa-home', '', 0],
        ['main', null, 'Hosting', 'web-hosting.php', 'fa-server', 'Web hosting paketleri', 1],
        ['main', null, 'VDS Sunucu', 'vds-sunucu.php', 'fa-database', 'Virtual Dedicated Server', 2],
        ['main', null, 'Domain', 'alan-adi.php', 'fa-globe', 'Domain kayıt ve transfer', 3],
        ['main', null, 'SSL', 'ssl-sertifikasi.php', 'fa-lock', 'SSL sertifikaları', 4],
        ['main', null, 'İletişim', 'iletisim.php', 'fa-envelope', 'Bize ulaşın', 5],
    ];

    foreach ($defaultMenus as $menu) {
        Database::query(
            "INSERT INTO menu_items (menu_type, parent_id, title, url, icon, description, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)",
            $menu
        );
    }
}

// AJAX İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Yeni menü ekle
    if ($action === 'add') {
        $menuType = $_POST['menu_type'] ?? 'main';
        $parentId = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $target = $_POST['target'] ?? '_self';

        if (empty($title)) {
            $message = 'Menü başlığı zorunludur!';
            $messageType = 'danger';
        } else {
            // Sıra numarasını al
            $maxOrder = Database::fetchColumn(
                "SELECT COALESCE(MAX(sort_order), 0) FROM menu_items WHERE menu_type = ? AND (parent_id = ? OR (parent_id IS NULL AND ? IS NULL))",
                [$menuType, $parentId, $parentId]
            );

            Database::query(
                "INSERT INTO menu_items (menu_type, parent_id, title, url, icon, description, target, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$menuType, $parentId, $title, $url, $icon, $description, $target, $maxOrder + 1]
            );

            $message = 'Menü öğesi eklendi!';
        }
    }

    // Menü güncelle
    if ($action === 'update') {
        $id = (int) $_POST['id'];
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $target = $_POST['target'] ?? '_self';
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        Database::query(
            "UPDATE menu_items SET title = ?, url = ?, icon = ?, description = ?, target = ?, is_active = ? WHERE id = ?",
            [$title, $url, $icon, $description, $target, $isActive, $id]
        );

        $message = 'Menü öğesi güncellendi!';
    }

    // Menü sil
    if ($action === 'delete') {
        $id = (int) $_POST['id'];

        // Alt menüleri de sil
        Database::query("DELETE FROM menu_items WHERE parent_id = ?", [$id]);
        Database::query("DELETE FROM menu_items WHERE id = ?", [$id]);

        $message = 'Menü öğesi silindi!';
    }

    // Sıralama güncelle
    if ($action === 'reorder') {
        $items = json_decode($_POST['items'] ?? '[]', true);
        foreach ($items as $index => $id) {
            Database::query("UPDATE menu_items SET sort_order = ? WHERE id = ?", [$index, $id]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // Alt menü ekle
    if ($action === 'add_submenu') {
        $parentId = (int) $_POST['parent_id'];
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $maxOrder = Database::fetchColumn(
            "SELECT COALESCE(MAX(sort_order), 0) FROM menu_items WHERE parent_id = ?",
            [$parentId]
        );

        Database::query(
            "INSERT INTO menu_items (menu_type, parent_id, title, url, icon, description, sort_order) VALUES ('main', ?, ?, ?, ?, ?, ?)",
            [$parentId, $title, $url, $icon, $description, $maxOrder + 1]
        );

        $message = 'Alt menü eklendi!';
    }

    // Menü taşı (başka menünün altına)
    if ($action === 'move') {
        $id = (int) $_POST['id'];
        $newParentId = !empty($_POST['new_parent_id']) ? (int) $_POST['new_parent_id'] : null;

        // Kendisini kendi altına taşıyamaz
        if ($id === $newParentId) {
            $message = 'Bir menü kendisinin altına taşınamaz!';
            $messageType = 'danger';
        } else {
            // Yeni parent altındaki son sırayı al
            if ($newParentId === null) {
                $maxOrder = Database::fetchColumn(
                    "SELECT COALESCE(MAX(sort_order), 0) FROM menu_items WHERE parent_id IS NULL AND menu_type = 'main'"
                );
            } else {
                $maxOrder = Database::fetchColumn(
                    "SELECT COALESCE(MAX(sort_order), 0) FROM menu_items WHERE parent_id = ?",
                    [$newParentId]
                );
            }

            Database::query(
                "UPDATE menu_items SET parent_id = ?, sort_order = ? WHERE id = ?",
                [$newParentId, $maxOrder + 1, $id]
            );

            // Eğer bu menünün alt menüleri varsa ve ana menüye taşındıysa, alt menüleri de taşı
            if ($newParentId === null) {
                // Alt menüleri de ana menüye taşı (artık üst seviye olacaklar)
                // Alternatif olarak alt menüleri korumak için bu satır kaldırılabilir
            }

            $message = 'Menü başarıyla taşındı!';
        }
    }

    // Alt menüyü ana menüye çıkar
    if ($action === 'promote') {
        $id = (int) $_POST['id'];

        $maxOrder = Database::fetchColumn(
            "SELECT COALESCE(MAX(sort_order), 0) FROM menu_items WHERE parent_id IS NULL AND menu_type = 'main'"
        );

        Database::query(
            "UPDATE menu_items SET parent_id = NULL, sort_order = ? WHERE id = ?",
            [$maxOrder + 1, $id]
        );

        $message = 'Menü ana menüye taşındı!';
    }
}

// Menüleri çek
$mainMenuItems = Database::fetchAll(
    "SELECT * FROM menu_items WHERE menu_type = 'main' AND parent_id IS NULL ORDER BY sort_order"
);

// Alt menüleri çek
foreach ($mainMenuItems as &$item) {
    $item['children'] = Database::fetchAll(
        "SELECT * FROM menu_items WHERE parent_id = ? ORDER BY sort_order",
        [$item['id']]
    );
}
unset($item);

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    .menu-manager {
        display: grid;
        grid-template-columns: 380px 1fr;
        gap: 25px;
    }

    @media (max-width: 1200px) {
        .menu-manager {
            grid-template-columns: 1fr;
        }
    }

    /* Menu List */
    .menu-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .menu-item-card {
        background: #f8fafc;
        border: 1px solid var(--border);
        border-radius: 12px;
        margin-bottom: 12px;
        transition: all 0.3s;
    }

    .menu-item-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
    }

    .menu-item-card.inactive {
        opacity: 0.5;
    }

    .menu-item-header {
        display: flex;
        align-items: center;
        padding: 16px;
        cursor: move;
    }

    .menu-item-drag {
        color: #94a3b8;
        margin-right: 12px;
        cursor: grab;
    }

    .menu-item-icon {
        width: 44px;
        height: 44px;
        background: linear-gradient(135deg, var(--primary) 0%, #818cf8 100%);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        font-size: 16px;
        color: white;
    }

    .menu-item-info {
        flex: 1;
    }

    .menu-item-info h4 {
        font-size: 15px;
        font-weight: 600;
        margin-bottom: 4px;
        color: var(--dark);
    }

    .menu-item-info span {
        font-size: 13px;
        color: var(--gray);
    }

    .menu-item-actions {
        display: flex;
        gap: 8px;
    }

    .menu-item-actions button {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }

    .btn-edit-menu {
        background: #e0e7ff;
        color: var(--primary);
    }

    .btn-edit-menu:hover {
        background: var(--primary);
        color: white;
    }

    .btn-submenu {
        background: #d1fae5;
        color: var(--success);
    }

    .btn-submenu:hover {
        background: var(--success);
        color: white;
    }

    .btn-delete-menu {
        background: #fee2e2;
        color: var(--danger);
    }

    .btn-delete-menu:hover {
        background: var(--danger);
        color: white;
    }

    .btn-move-menu {
        background: #fef3c7;
        color: #d97706;
    }

    .btn-move-menu:hover {
        background: #f59e0b;
        color: white;
    }

    .btn-promote {
        background: #e0e7ff;
        color: var(--primary);
    }

    .btn-promote:hover {
        background: var(--primary);
        color: white;
    }

    /* Parent Select Styling */
    #parentMenuSelect {
        background: #f8fafc;
        border: 2px solid var(--border);
        padding: 12px 15px;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s;
    }

    #parentMenuSelect:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    #parentMenuSelect option {
        padding: 10px;
    }

    /* Parent indicator badge */
    .parent-indicator {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: linear-gradient(135deg, #dbeafe 0%, #e0e7ff 100%);
        color: #3730a3;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        margin-left: 8px;
    }

    /* Submenu */
    .submenu-list {
        padding: 0 16px 16px 67px;
        list-style: none;
        position: relative;
    }

    .submenu-list::before {
        content: '';
        position: absolute;
        left: 38px;
        top: 0;
        bottom: 16px;
        width: 2px;
        background: linear-gradient(to bottom, var(--primary) 0%, #e2e8f0 100%);
        border-radius: 2px;
    }

    .submenu-item {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        background: linear-gradient(135deg, #f8fafc 0%, #fff 100%);
        border-radius: 8px;
        margin-bottom: 8px;
        border-left: 3px solid var(--primary);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        position: relative;
        transition: all 0.3s;
    }

    .submenu-item::before {
        content: '';
        position: absolute;
        left: -32px;
        top: 50%;
        width: 20px;
        height: 2px;
        background: var(--primary);
    }

    .submenu-item:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
    }

    .submenu-item:last-child {
        margin-bottom: 0;
    }

    /* Mega Menu Badge */
    .mega-menu-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        font-size: 10px;
        padding: 3px 8px;
        border-radius: 10px;
        margin-left: 8px;
    }

    .submenu-item-info {
        flex: 1;
    }

    .submenu-item-info h5 {
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 2px;
        color: var(--dark);
    }

    .submenu-item-info span {
        font-size: 12px;
        color: var(--gray);
    }

    .submenu-item-actions {
        display: flex;
        gap: 6px;
    }

    .submenu-item-actions button {
        width: 30px;
        height: 30px;
        border-radius: 6px;
        font-size: 12px;
    }

    /* Toggle Switch */
    .toggle-switch {
        position: relative;
        width: 50px;
        height: 26px;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: #cbd5e1;
        border-radius: 26px;
        transition: 0.3s;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 20px;
        width: 20px;
        left: 3px;
        bottom: 3px;
        background: white;
        border-radius: 50%;
        transition: 0.3s;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .toggle-switch input:checked+.toggle-slider {
        background: var(--primary);
    }

    .toggle-switch input:checked+.toggle-slider:before {
        transform: translateX(24px);
    }

    .btn-block {
        width: 100%;
        justify-content: center;
    }

    /* Icon Picker */
    .icon-picker {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        display: none;
        grid-template-columns: repeat(8, 1fr);
        gap: 8px;
        max-height: 200px;
        overflow-y: auto;
        padding: 12px;
        background: white;
        border: 1px solid var(--border);
        border-radius: 10px;
        margin-top: 8px;
        z-index: 100;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .icon-picker.show {
        display: grid;
    }

    .icon-picker-item {
        width: 38px;
        height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f1f5f9;
        border: 1px solid var(--border);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        color: var(--gray);
    }

    .icon-picker-item:hover,
    .icon-picker-item.selected {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
    }

    /* Preview */
    .menu-preview {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        border-radius: 12px;
        padding: 20px;
        margin-top: 20px;
    }

    .menu-preview h4 {
        font-size: 14px;
        color: #94a3b8;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .preview-nav {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .preview-nav a {
        padding: 10px 16px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        color: #e2e8f0;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: all 0.3s;
    }

    .preview-nav a:hover {
        background: var(--primary);
        color: white;
    }

    /* Modal Styles - Override for light theme */
    .modal.show {
        display: flex;
    }

    .menu-modal .modal-content {
        background: white;
        border: none;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
    }

    .menu-modal .modal-header {
        background: #f8fafc;
    }

    .menu-modal .modal-header h3 {
        color: var(--dark);
    }

    .menu-modal .modal-close {
        background: #f1f5f9;
        color: var(--gray);
    }

    .menu-modal .modal-close:hover {
        background: #fee2e2;
        color: var(--danger);
    }

    .menu-modal .modal-body .form-group label {
        color: var(--dark);
    }

    .menu-modal .form-control {
        background: white;
        color: var(--dark);
    }

    .menu-modal .modal-footer .btn-cancel {
        background: #f1f5f9;
        color: var(--dark);
    }

    .menu-modal .modal-footer .btn-cancel:hover {
        background: #e2e8f0;
    }
</style>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="menu-manager">
    <!-- Sol Panel - Yeni Menü Ekle -->
    <div>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-plus-circle"></i> Yeni Menü Ekle</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="menu_type" value="main">

                    <div class="form-group">
                        <label>Ana Menü (Üst Menü)</label>
                        <select name="parent_id" class="form-control" id="parentMenuSelect">
                            <option value="">🏠 Ana Menü (Üst Seviye)</option>
                            <?php foreach ($mainMenuItems as $menuItem): ?>
                                <option value="<?= $menuItem['id'] ?>"
                                    data-icon="<?= htmlspecialchars($menuItem['icon'] ?? 'fa-folder') ?>">
                                    📁 <?= htmlspecialchars($menuItem['title']) ?> altına ekle
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: var(--gray); margin-top: 6px; display: block;">
                            <i class="fas fa-info-circle"></i> Boş bırakırsanız ana menü olarak eklenir
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Menü Başlığı *</label>
                        <input type="text" name="title" class="form-control" placeholder="Örn: Hizmetler" required>
                    </div>

                    <div class="form-group">
                        <label>URL / Link</label>
                        <input type="text" name="url" class="form-control" placeholder="Örn: services.php veya #">
                    </div>

                    <div class="form-group">
                        <label>İkon (Font Awesome)</label>
                        <div style="position: relative;">
                            <input type="text" name="icon" id="iconInput" class="form-control"
                                placeholder="Örn: fa-server" onclick="toggleIconPicker()">
                            <div class="icon-picker" id="iconPicker">
                                <?php
                                $icons = ['fa-home', 'fa-server', 'fa-database', 'fa-globe', 'fa-lock', 'fa-envelope', 'fa-phone', 'fa-user', 'fa-cog', 'fa-shopping-cart', 'fa-credit-card', 'fa-file', 'fa-folder', 'fa-cloud', 'fa-shield-alt', 'fa-rocket', 'fa-chart-line', 'fa-users', 'fa-building', 'fa-laptop', 'fa-mobile', 'fa-desktop', 'fa-hdd', 'fa-microchip'];
                                foreach ($icons as $icon): ?>
                                    <div class="icon-picker-item" onclick="selectIcon('<?= $icon ?>')">
                                        <i class="fas <?= $icon ?>"></i>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Açıklama</label>
                        <input type="text" name="description" class="form-control"
                            placeholder="Kısa açıklama (opsiyonel)">
                    </div>

                    <div class="form-group">
                        <label>Hedef</label>
                        <select name="target" class="form-control">
                            <option value="_self">Aynı Sekmede Aç</option>
                            <option value="_blank">Yeni Sekmede Aç</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" id="addMenuBtn">
                        <i class="fas fa-plus"></i> <span id="addMenuBtnText">Ana Menü Ekle</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Önizleme -->
        <div class="menu-preview">
            <h4><i class="fas fa-eye"></i> Menü Önizleme</h4>
            <div class="preview-nav">
                <?php foreach ($mainMenuItems as $item): ?>
                    <?php if ($item['is_active']): ?>
                        <a href="#" class="<?= !empty($item['children']) ? 'has-submenu' : '' ?>">
                            <?php if ($item['icon']): ?>
                                <i class="fas <?= htmlspecialchars($item['icon']) ?>"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($item['title']) ?>
                            <?php if (!empty($item['children'])): ?>
                                <i class="fas fa-chevron-down" style="font-size: 10px; margin-left: 5px;"></i>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Yardım -->
        <div
            style="background: linear-gradient(135deg, #dbeafe 0%, #ede9fe 100%); border-radius: 12px; padding: 18px; margin-top: 20px;">
            <h4
                style="font-size: 14px; color: #1e40af; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-lightbulb"></i> Mega Menü Nasıl Oluşturulur?
            </h4>
            <ul style="font-size: 13px; color: #3730a3; margin: 0; padding-left: 20px; line-height: 1.8;">
                <li><strong>Alt Menü Ekle:</strong> <i class="fas fa-sitemap" style="color: #10b981;"></i> butonuna
                    tıklayın</li>
                <li><strong>Taşı:</strong> <i class="fas fa-arrows-alt" style="color: #f59e0b;"></i> butonuyla bir
                    menüyü başka bir menünün altına taşıyın</li>
                <li><strong>Ana Menüye Çıkar:</strong> <i class="fas fa-level-up-alt"
                        style="color: var(--primary);"></i> butonuyla alt menüyü ana menüye çıkarın</li>
            </ul>
        </div>
    </div>

    <!-- Sağ Panel - Menü Listesi -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bars"></i> Ana Menü Öğeleri</h3>
            <div style="display: flex; align-items: center; gap: 15px;">
                <span style="color: var(--gray); font-size: 13px;">
                    <?= count($mainMenuItems) ?> ana menü
                </span>
                <span class="badge"
                    style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 5px 12px; border-radius: 20px; font-size: 12px;">
                    <i class="fas fa-sitemap"></i> Mega Menü Destekli
                </span>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($mainMenuItems)): ?>
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <h3>Henüz menü öğesi eklenmemiş</h3>
                    <p>Sol taraftaki formu kullanarak yeni menü öğeleri ekleyebilirsiniz.</p>
                </div>
            <?php else: ?>
                <ul class="menu-list" id="menuList">
                    <?php foreach ($mainMenuItems as $item): ?>
                        <li class="menu-item-card <?= !$item['is_active'] ? 'inactive' : '' ?>" data-id="<?= $item['id'] ?>">
                            <div class="menu-item-header">
                                <div class="menu-item-drag">
                                    <i class="fas fa-grip-vertical"></i>
                                </div>
                                <div class="menu-item-icon">
                                    <i class="fas <?= $item['icon'] ?: 'fa-link' ?>"></i>
                                </div>
                                <div class="menu-item-info">
                                    <h4>
                                        <?= htmlspecialchars($item['title']) ?>
                                        <?php if (!empty($item['children'])): ?>
                                            <span class="mega-menu-badge">
                                                <i class="fas fa-layer-group"></i>
                                                <?= count($item['children']) ?> alt menü
                                            </span>
                                        <?php endif; ?>
                                    </h4>
                                    <span><?= htmlspecialchars($item['url']) ?></span>
                                </div>
                                <div class="menu-item-actions">
                                    <button class="btn-submenu" title="Alt Menü Ekle"
                                        onclick="openSubmenuModal(<?= $item['id'] ?>, '<?= htmlspecialchars($item['title']) ?>')">
                                        <i class="fas fa-sitemap"></i>
                                    </button>
                                    <button class="btn-move-menu" title="Taşı"
                                        onclick="openMoveModal(<?= $item['id'] ?>, '<?= htmlspecialchars($item['title']) ?>', null)">
                                        <i class="fas fa-arrows-alt"></i>
                                    </button>
                                    <button class="btn-edit-menu" title="Düzenle"
                                        onclick="openEditModal(<?= htmlspecialchars(json_encode($item)) ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-delete-menu" title="Sil"
                                        onclick="deleteMenuItem(<?= $item['id'] ?>, '<?= htmlspecialchars($item['title']) ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>

                            <?php if (!empty($item['children'])): ?>
                                <ul class="submenu-list" id="submenuList<?= $item['id'] ?>" data-parent="<?= $item['id'] ?>">
                                    <?php foreach ($item['children'] as $child): ?>
                                        <li class="submenu-item" data-id="<?= $child['id'] ?>">
                                            <div class="submenu-drag" style="color: #94a3b8; margin-right: 10px; cursor: grab;">
                                                <i class="fas fa-grip-vertical"></i>
                                            </div>
                                            <div class="submenu-item-info">
                                                <h5>
                                                    <?php if ($child['icon']): ?>
                                                        <i class="fas <?= htmlspecialchars($child['icon']) ?>"
                                                            style="margin-right: 8px; color: var(--primary);"></i>
                                                    <?php endif; ?>
                                                    <?= htmlspecialchars($child['title']) ?>
                                                </h5>
                                                <span><?= htmlspecialchars($child['url']) ?></span>
                                            </div>
                                            <div class="submenu-item-actions">
                                                <button class="btn-promote" title="Ana Menüye Çıkar"
                                                    onclick="promoteMenuItem(<?= $child['id'] ?>, '<?= htmlspecialchars($child['title']) ?>')">
                                                    <i class="fas fa-level-up-alt"></i>
                                                </button>
                                                <button class="btn-move-menu" title="Başka Menüye Taşı"
                                                    onclick="openMoveModal(<?= $child['id'] ?>, '<?= htmlspecialchars($child['title']) ?>', <?= $item['id'] ?>)">
                                                    <i class="fas fa-arrows-alt"></i>
                                                </button>
                                                <button class="btn-edit-menu"
                                                    onclick="openEditModal(<?= htmlspecialchars(json_encode($child)) ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn-delete-menu"
                                                    onclick="deleteMenuItem(<?= $child['id'] ?>, '<?= htmlspecialchars($child['title']) ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Düzenleme Modal -->
<div class="modal menu-modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Menü Düzenle</h3>
            <button class="modal-close" type="button" onclick="closeModal('editModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editId">

                <div class="form-group">
                    <label>Menü Başlığı</label>
                    <input type="text" name="title" id="editTitle" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>URL / Link</label>
                    <input type="text" name="url" id="editUrl" class="form-control">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>İkon</label>
                        <input type="text" name="icon" id="editIcon" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Hedef</label>
                        <select name="target" id="editTarget" class="form-control">
                            <option value="_self">Aynı Sekme</option>
                            <option value="_blank">Yeni Sekme</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Açıklama</label>
                    <input type="text" name="description" id="editDescription" class="form-control">
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 15px;">
                    <label class="toggle-switch" style="margin: 0;">
                        <input type="checkbox" name="is_active" id="editActive">
                        <span class="toggle-slider"></span>
                    </label>
                    <span style="color: var(--dark);">Menü Aktif</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" onclick="closeModal('editModal')">İptal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Alt Menü Ekleme Modal -->
<div class="modal menu-modal" id="submenuModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-sitemap"></i> Alt Menü Ekle</h3>
            <button class="modal-close" type="button" onclick="closeModal('submenuModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add_submenu">
                <input type="hidden" name="parent_id" id="submenuParentId">

                <div class="alert alert-info" style="background: #dbeafe; color: #1e40af; border: none;">
                    <i class="fas fa-info-circle"></i>
                    <strong id="submenuParentTitle"></strong> altına yeni menü ekleniyor
                </div>

                <div class="form-group">
                    <label>Alt Menü Başlığı</label>
                    <input type="text" name="title" class="form-control" required placeholder="Örn: Linux Hosting">
                </div>

                <div class="form-group">
                    <label>URL / Link</label>
                    <input type="text" name="url" class="form-control" placeholder="Örn: web-hosting.php?type=linux">
                </div>

                <div class="form-group">
                    <label>İkon</label>
                    <input type="text" name="icon" class="form-control" placeholder="Örn: fa-linux">
                </div>

                <div class="form-group">
                    <label>Açıklama</label>
                    <input type="text" name="description" class="form-control" placeholder="Kısa açıklama">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" onclick="closeModal('submenuModal')">İptal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Ekle
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Menü Taşıma Modal -->
<div class="modal menu-modal" id="moveModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-arrows-alt"></i> Menü Taşı</h3>
            <button class="modal-close" type="button" onclick="closeModal('moveModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="move">
                <input type="hidden" name="id" id="moveId">

                <div class="alert alert-warning"
                    style="background: #fef3c7; color: #92400e; border: none; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong id="moveMenuTitle"></strong> menüsünü taşıyorsunuz
                </div>

                <div class="form-group">
                    <label>Hedef Konum</label>
                    <select name="new_parent_id" id="moveParentSelect" class="form-control">
                        <option value="">🏠 Ana Menü (Üst Seviye)</option>
                        <?php foreach ($mainMenuItems as $menuItem): ?>
                            <option value="<?= $menuItem['id'] ?>">
                                📁 <?= htmlspecialchars($menuItem['title']) ?> altına taşı
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: var(--gray); margin-top: 8px; display: block;">
                        <i class="fas fa-info-circle"></i>
                        Bir ana menü seçerseniz, menü o menünün alt menüsü olur (Mega Menü)
                    </small>
                </div>

                <div class="move-preview"
                    style="background: #f8fafc; border-radius: 10px; padding: 15px; margin-top: 15px;">
                    <h4 style="font-size: 13px; color: var(--gray); margin-bottom: 10px;">
                        <i class="fas fa-eye"></i> Önizleme
                    </h4>
                    <div id="movePreviewContent" style="font-size: 14px; color: var(--dark);">
                        <span id="movePreviewText">Ana menü olarak kalacak</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" onclick="closeModal('moveModal')">İptal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Taşı
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Silme Form (Hidden) -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<!-- Promote Form (Hidden) -->
<form id="promoteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="promote">
    <input type="hidden" name="id" id="promoteId">
</form>

<script>
    // Icon Picker
    function toggleIconPicker() {
        document.getElementById('iconPicker').classList.toggle('show');
    }

    function selectIcon(icon) {
        document.getElementById('iconInput').value = icon;
        document.getElementById('iconPicker').classList.remove('show');
    }

    // Modal Functions
    function openEditModal(item) {
        document.getElementById('editId').value = item.id;
        document.getElementById('editTitle').value = item.title;
        document.getElementById('editUrl').value = item.url;
        document.getElementById('editIcon').value = item.icon || '';
        document.getElementById('editTarget').value = item.target || '_self';
        document.getElementById('editDescription').value = item.description || '';
        document.getElementById('editActive').checked = item.is_active == 1;
        document.getElementById('editModal').classList.add('show');
    }

    function openSubmenuModal(parentId, parentTitle) {
        document.getElementById('submenuParentId').value = parentId;
        document.getElementById('submenuParentTitle').textContent = parentTitle;
        document.getElementById('submenuModal').classList.add('show');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }

    // Delete Function
    function deleteMenuItem(id, title) {
        if (confirm(`"${title}" menüsünü ve alt menülerini silmek istediğinize emin misiniz?`)) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteForm').submit();
        }
    }

    // Move Modal
    function openMoveModal(id, title, currentParentId) {
        document.getElementById('moveId').value = id;
        document.getElementById('moveMenuTitle').textContent = title;

        // Menünün kendisini seçeneklerden gizle
        const select = document.getElementById('moveParentSelect');
        select.querySelectorAll('option').forEach(opt => {
            opt.style.display = '';
            opt.disabled = false;
            if (opt.value == id) {
                opt.style.display = 'none';
                opt.disabled = true;
            }
        });

        // Eğer zaten bir alt menüyse, mevcut parent'ı seçili yap
        if (currentParentId) {
            select.value = '';
        } else {
            select.value = '';
        }

        updateMovePreview();
        document.getElementById('moveModal').classList.add('show');
    }

    // Move Preview Update
    function updateMovePreview() {
        const select = document.getElementById('moveParentSelect');
        const previewText = document.getElementById('movePreviewText');
        const selectedOption = select.options[select.selectedIndex];

        if (select.value === '') {
            previewText.innerHTML = '<i class="fas fa-home" style="color: var(--primary);"></i> Ana menü olarak kalacak (üst seviye)';
        } else {
            previewText.innerHTML = '<i class="fas fa-folder-open" style="color: var(--success);"></i> <strong>' + selectedOption.text.replace('📁 ', '').replace(' altına taşı', '') + '</strong> altında alt menü olacak';
        }
    }

    // Promote Function (Alt menüyü ana menüye çıkar)
    function promoteMenuItem(id, title) {
        if (confirm(`"${title}" menüsünü ana menüye çıkarmak istediğinize emin misiniz?`)) {
            document.getElementById('promoteId').value = id;
            document.getElementById('promoteForm').submit();
        }
    }

    // Move preview update on select change
    document.getElementById('moveParentSelect')?.addEventListener('change', updateMovePreview);

    // Parent menu select - update button text
    const parentMenuSelect = document.getElementById('parentMenuSelect');
    const addMenuBtnText = document.getElementById('addMenuBtnText');

    if (parentMenuSelect && addMenuBtnText) {
        parentMenuSelect.addEventListener('change', function () {
            if (this.value) {
                addMenuBtnText.textContent = 'Alt Menü Ekle';
                document.getElementById('addMenuBtn').style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
            } else {
                addMenuBtnText.textContent = 'Ana Menü Ekle';
                document.getElementById('addMenuBtn').style.background = '';
            }
        });
    }

    // Close modal on outside click
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function (e) {
            if (e.target === this) {
                this.classList.remove('show');
            }
        });
    });

    // Close icon picker on outside click
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#iconInput') && !e.target.closest('#iconPicker')) {
            document.getElementById('iconPicker').classList.remove('show');
        }
    });

    // Drag and Drop (basit sıralama)
    const menuList = document.getElementById('menuList');
    if (menuList) {
        let draggedItem = null;

        menuList.querySelectorAll('.menu-item-card').forEach(item => {
            item.setAttribute('draggable', true);

            item.addEventListener('dragstart', function () {
                draggedItem = this;
                setTimeout(() => this.style.opacity = '0.5', 0);
            });

            item.addEventListener('dragend', function () {
                this.style.opacity = '1';
                draggedItem = null;

                // Sırayı kaydet
                const items = [...menuList.querySelectorAll('.menu-item-card')].map(el => el.dataset.id);

                fetch('menus.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=reorder&items=' + JSON.stringify(items)
                });
            });

            item.addEventListener('dragover', function (e) {
                e.preventDefault();
            });

            item.addEventListener('drop', function (e) {
                e.preventDefault();
                if (this !== draggedItem) {
                    const allItems = [...menuList.querySelectorAll('.menu-item-card')];
                    const draggedIndex = allItems.indexOf(draggedItem);
                    const droppedIndex = allItems.indexOf(this);

                    if (draggedIndex < droppedIndex) {
                        this.parentNode.insertBefore(draggedItem, this.nextSibling);
                    } else {
                        this.parentNode.insertBefore(draggedItem, this);
                    }
                }
            });
        });
    }

    // Submenu Drag and Drop (alt menü sıralaması)
    document.querySelectorAll('.submenu-list').forEach(submenuList => {
        let draggedItem = null;
        const parentId = submenuList.dataset.parent;

        submenuList.querySelectorAll('.submenu-item').forEach(item => {
            item.setAttribute('draggable', true);

            item.addEventListener('dragstart', function (e) {
                e.stopPropagation();
                draggedItem = this;
                setTimeout(() => this.style.opacity = '0.5', 0);
            });

            item.addEventListener('dragend', function () {
                this.style.opacity = '1';
                draggedItem = null;

                // Sırayı kaydet
                const items = [...submenuList.querySelectorAll('.submenu-item')].map(el => el.dataset.id);

                fetch('menus.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=reorder&items=' + JSON.stringify(items)
                }).then(() => {
                    // Başarılı bildirim (opsiyonel)
                    console.log('Alt menü sıralaması güncellendi');
                });
            });

            item.addEventListener('dragover', function (e) {
                e.preventDefault();
                e.stopPropagation();
            });

            item.addEventListener('drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (this !== draggedItem && draggedItem) {
                    const allItems = [...submenuList.querySelectorAll('.submenu-item')];
                    const draggedIndex = allItems.indexOf(draggedItem);
                    const droppedIndex = allItems.indexOf(this);

                    if (draggedIndex < droppedIndex) {
                        this.parentNode.insertBefore(draggedItem, this.nextSibling);
                    } else {
                        this.parentNode.insertBefore(draggedItem, this);
                    }
                }
            });
        });
    });
</script>

<?php include 'includes/footer.php'; ?>