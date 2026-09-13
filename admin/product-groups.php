<?php
/**
 * WHMVM - Ürün Grupları Yönetimi
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

$pageTitle = 'Ürün Grupları';
$currentPage = 'product-groups';
$db = Database::getInstance();
$message = '';
$messageType = 'success';

// Silme işlemi
/* Durum degistiren islem POST ile gelir; belirtec dogrulanir. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && is_numeric($_POST['delete'])) {
    Guvenlik::zorunlu();
    // Önce bu gruba ait ürün var mı kontrol et
    $productCount = Database::fetchColumn("SELECT COUNT(*) FROM products WHERE group_id = ?", [$_POST['delete']]);
    if ($productCount > 0) {
        $message = "Bu gruba ait $productCount ürün var. Önce ürünleri başka bir gruba taşıyın veya silin.";
        $messageType = 'danger';
    } else {
        Database::query("DELETE FROM product_groups WHERE id = ?", [$_POST['delete']]);
        $message = 'Grup silindi.';
    }
}

// type alanını ekle (yoksa)
try {
    $db->exec("ALTER TABLE product_groups ADD COLUMN IF NOT EXISTS `type` VARCHAR(50) DEFAULT 'hosting'");
} catch (Exception $e) {
    // Kolon zaten varsa devam et
}

// Ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $type = trim($_POST['type'] ?? 'hosting');
        $description = trim($_POST['description'] ?? '');
        $orderPriority = (int) ($_POST['order_priority'] ?? 0);
        $isHidden = isset($_POST['is_hidden']) ? 1 : 0;

        if (empty($name)) {
            $message = 'Grup adı zorunludur!';
            $messageType = 'danger';
        } else {
            // Slug oluştur
            if (empty($slug)) {
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
            }

            // Slug benzersiz mi kontrol et
            $exists = Database::fetchColumn("SELECT id FROM product_groups WHERE slug = ?", [$slug]);
            if ($exists) {
                $slug .= '-' . time();
            }

            Database::query(
                "INSERT INTO product_groups (name, slug, type, description, order_priority, is_hidden) VALUES (?, ?, ?, ?, ?, ?)",
                [$name, $slug, $type, $description, $orderPriority, $isHidden]
            );
            $message = 'Grup eklendi!';
        }
    }

    if ($_POST['action'] === 'update') {
        $id = (int) $_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $type = trim($_POST['type'] ?? 'hosting');
        $description = trim($_POST['description'] ?? '');
        $orderPriority = (int) ($_POST['order_priority'] ?? 0);
        $isHidden = isset($_POST['is_hidden']) ? 1 : 0;

        Database::query(
            "UPDATE product_groups SET name = ?, slug = ?, type = ?, description = ?, order_priority = ?, is_hidden = ? WHERE id = ?",
            [$name, $slug, $type, $description, $orderPriority, $isHidden, $id]
        );
        $message = 'Grup güncellendi!';
    }
}

// Grupları çek (ürün sayısıyla birlikte)
$groups = Database::fetchAll("
    SELECT g.*, COUNT(p.id) as product_count 
    FROM product_groups g 
    LEFT JOIN products p ON g.id = p.group_id 
    GROUP BY g.id 
    ORDER BY g.order_priority, g.name
");

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: linear-gradient(135deg, #fff 0%, #f8fafc 100%);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 24px;
        display: flex;
        align-items: center;
        gap: 20px;
        transition: all 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
    }

    .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
    }

    .stat-icon.blue {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    }

    .stat-icon.green {
        background: linear-gradient(135deg, #22c55e, #16a34a);
    }

    .stat-icon.orange {
        background: linear-gradient(135deg, #f97316, #ea580c);
    }

    .stat-icon.purple {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    }

    .stat-info h4 {
        font-size: 28px;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 4px;
    }

    .stat-info span {
        font-size: 13px;
        color: var(--gray);
    }

    /* Type Section */
    .type-section {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 16px;
        margin-bottom: 24px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }

    .type-header {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 20px 24px;
        background: linear-gradient(135deg, #f8fafc 0%, #fff 100%);
        border-bottom: 1px solid var(--border);
        position: relative;
    }

    .type-header::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--type-color);
    }

    .type-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .type-info {
        flex: 1;
    }

    .type-info h3 {
        font-size: 17px;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 4px;
    }

    .type-info span {
        font-size: 13px;
        color: var(--gray);
    }

    .type-add-btn {
        padding: 10px 20px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
    }

    .type-add-btn:hover {
        background: #4f46e5;
        transform: scale(1.02);
    }

    .type-groups {
        padding: 0;
    }

    /* Group Row */
    .group-row {
        display: flex;
        align-items: center;
        padding: 18px 24px;
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.2s;
    }

    .group-row:last-child {
        border-bottom: none;
    }

    .group-row:hover {
        background: linear-gradient(90deg, #f8fafc 0%, #fff 100%);
    }

    .group-main {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .group-avatar {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: white;
        flex-shrink: 0;
    }

    .group-details {
        flex: 1;
    }

    .group-name {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 6px;
    }

    .group-name strong {
        font-size: 15px;
        font-weight: 600;
        color: var(--dark);
    }

    .group-meta {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .meta-item {
        font-size: 13px;
        color: var(--gray);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .meta-item i {
        font-size: 11px;
        opacity: 0.7;
    }

    .meta-item code {
        background: #e2e8f0;
        padding: 3px 10px;
        border-radius: 6px;
        font-size: 12px;
        color: #475569;
    }

    .group-stats {
        display: flex;
        align-items: center;
        gap: 24px;
        margin-right: 24px;
    }

    .group-stat-item {
        text-align: center;
    }

    .group-stat-item .value {
        font-size: 18px;
        font-weight: 700;
        color: var(--dark);
    }

    .group-stat-item .label {
        font-size: 11px;
        color: var(--gray);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .group-link {
        margin-right: 16px;
    }

    .group-link a {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        border-radius: 10px;
        color: #3b82f6;
        text-decoration: none;
        transition: all 0.2s;
        font-size: 14px;
    }

    .group-link a:hover {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
        transform: scale(1.05);
    }

    .group-actions-row {
        display: flex;
        gap: 8px;
    }

    .btn-icon {
        width: 40px;
        height: 40px;
        border: 1px solid #e2e8f0;
        background: white;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 14px;
    }

    .btn-icon:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: #f0f0ff;
        transform: scale(1.05);
    }

    .btn-icon-danger:hover {
        border-color: #ef4444;
        color: #ef4444;
        background: #fef2f2;
    }

    .badge-hidden {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: #92400e;
        font-size: 10px;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .badge-count {
        background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        color: #1e40af;
        font-size: 12px;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 600;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 80px 20px;
        background: linear-gradient(135deg, #f8fafc 0%, #fff 100%);
        border: 2px dashed #cbd5e1;
        border-radius: 16px;
    }

    .empty-state i {
        font-size: 56px;
        color: #cbd5e1;
        margin-bottom: 20px;
    }

    .empty-state h3 {
        font-size: 20px;
        color: var(--dark);
        margin-bottom: 10px;
    }

    .empty-state p {
        font-size: 15px;
        color: var(--gray);
        margin-bottom: 24px;
    }

    /* Modal Overrides */
    .modal.show {
        display: flex;
    }

    .modal-content {
        background: white;
        max-width: 520px;
        border-radius: 16px;
    }

    .modal-header {
        background: linear-gradient(135deg, #f8fafc 0%, #fff 100%);
        border-radius: 16px 16px 0 0;
    }

    .modal-header h3 {
        color: var(--dark);
    }

    .form-group label {
        color: var(--dark);
        font-weight: 500;
    }

    /* Responsive */
    @media (max-width: 1200px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .group-stats {
            display: none;
        }

        .group-row {
            flex-wrap: wrap;
            gap: 12px;
        }

        .group-actions-row {
            width: 100%;
            justify-content: flex-end;
        }
    }
</style>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php
// İstatistikleri hesapla
$totalGroups = count($groups);
$totalProducts = array_sum(array_column($groups, 'product_count'));
$activeTypes = count(array_unique(array_column($groups, 'type')));
$hiddenGroups = count(array_filter($groups, fn($g) => $g['is_hidden']));
?>

<!-- İstatistik Kartları -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-layer-group"></i>
        </div>
        <div class="stat-info">
            <h4><?= $totalGroups ?></h4>
            <span>Toplam Grup</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-box"></i>
        </div>
        <div class="stat-info">
            <h4><?= $totalProducts ?></h4>
            <span>Toplam Ürün</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-tags"></i>
        </div>
        <div class="stat-info">
            <h4><?= $activeTypes ?></h4>
            <span>Aktif Kategori</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-eye-slash"></i>
        </div>
        <div class="stat-info">
            <h4><?= $hiddenGroups ?></h4>
            <span>Gizli Grup</span>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom: 25px;">
    <div class="card-header">
        <h3><i class="fas fa-layer-group"></i> Ürün Grupları</h3>
        <button onclick="openModal('addModal')" class="btn btn-primary">
            <i class="fas fa-plus"></i> Yeni Grup
        </button>
    </div>
</div>

<?php
// Grupları türlerine göre ayır - Veritabanından dinamik olarak çek
$typeLabels = [];
$typeOrder = [];
try {
    $dbTypes = Database::fetchAll("SELECT * FROM product_types WHERE is_active = 1 ORDER BY order_priority, label");
    foreach ($dbTypes as $t) {
        $typeLabels[$t['slug']] = [
            'label' => $t['emoji'] . ' ' . $t['label'],
            'icon' => $t['icon'],
            'color' => $t['color']
        ];
        $typeOrder[] = $t['slug'];
    }
} catch (Exception $e) {
    // Tablo yoksa varsayılanları kullan
    $typeLabels = [
        'hosting' => ['label' => '🌐 Web Hosting', 'icon' => 'fa-globe', 'color' => '#f97316'],
        'vps' => ['label' => '💻 VPS Sunucu', 'icon' => 'fa-server', 'color' => '#10b981'],
        'vds' => ['label' => '🖥️ VDS Sunucu', 'icon' => 'fa-database', 'color' => '#6366f1'],
        'dedicated' => ['label' => '🏢 Fiziksel Sunucu', 'icon' => 'fa-building', 'color' => '#8b5cf6'],
        'domain' => ['label' => '🔗 Domain', 'icon' => 'fa-link', 'color' => '#0ea5e9'],
        'ssl' => ['label' => '🔒 SSL Sertifikası', 'icon' => 'fa-shield-alt', 'color' => '#22c55e'],
        'email' => ['label' => '📧 E-posta', 'icon' => 'fa-envelope', 'color' => '#f59e0b'],
        'other' => ['label' => '📦 Diğer', 'icon' => 'fa-box', 'color' => '#64748b']
    ];
    $typeOrder = array_keys($typeLabels);
}

$groupedByType = [];
foreach ($groups as $group) {
    $type = $group['type'] ?? 'other';
    if (!isset($groupedByType[$type])) {
        $groupedByType[$type] = [];
    }
    $groupedByType[$type][] = $group;
}

// $typeOrder artık yukarıda dinamik olarak oluşturuluyor
?>

<?php foreach ($typeOrder as $type): ?>
    <?php if (isset($groupedByType[$type]) && count($groupedByType[$type]) > 0): ?>
        <?php $typeInfo = $typeLabels[$type]; ?>
        <div class="type-section">
            <div class="type-header" style="--type-color: <?= $typeInfo['color'] ?>;">
                <div class="type-icon"
                    style="background: linear-gradient(135deg, <?= $typeInfo['color'] ?>, <?= $typeInfo['color'] ?>dd);">
                    <i class="fas <?= $typeInfo['icon'] ?>"></i>
                </div>
                <div class="type-info">
                    <h3><?= $typeInfo['label'] ?></h3>
                    <span><?= count($groupedByType[$type]) ?> grup ·
                        <?= array_sum(array_column($groupedByType[$type], 'product_count')) ?> ürün</span>
                </div>
                <button
                    onclick="openModal('addModal'); document.querySelector('#addModal select[name=type]').value='<?= $type ?>';"
                    class="type-add-btn">
                    <i class="fas fa-plus"></i> Ekle
                </button>
            </div>

            <div class="type-groups">
                <?php foreach ($groupedByType[$type] as $group): ?>
                    <div class="group-row">
                        <div class="group-main">
                            <div class="group-avatar" style="background: <?= $typeInfo['color'] ?>;">
                                <i class="fas <?= $typeInfo['icon'] ?>"></i>
                            </div>
                            <div class="group-details">
                                <div class="group-name">
                                    <strong><?= htmlspecialchars($group['name']) ?></strong>
                                    <?php if ($group['is_hidden']): ?>
                                        <span class="badge-hidden">Gizli</span>
                                    <?php endif; ?>
                                </div>
                                <div class="group-meta">
                                    <span class="meta-item">
                                        <code><?= htmlspecialchars($group['slug']) ?></code>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="group-stats">
                            <div class="group-stat-item">
                                <div class="value"><?= $group['product_count'] ?></div>
                                <div class="label">Ürün</div>
                            </div>
                            <div class="group-stat-item">
                                <div class="value"><?= $group['order_priority'] ?></div>
                                <div class="label">Sıra</div>
                            </div>
                        </div>
                        <div class="group-link">
                            <a href="<?= SITE_URL ?>/magaza.php?group=<?= htmlspecialchars($group['slug']) ?>" target="_blank"
                                title="Sayfayı Görüntüle">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                        <div class="group-actions-row">
                            <a href="products.php?group=<?= $group['id'] ?>" class="btn-icon" title="Ürünleri Gör">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button onclick="editGroup(<?= htmlspecialchars(json_encode($group)) ?>)" class="btn-icon"
                                title="Düzenle">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($group['product_count'] == 0): ?>
                                <button
                                    onclick="confirmDelete('Bu grubu silmek istediğinizden emin misiniz?', '?delete=<?= $group['id'] ?>')"
                                    class="btn-icon btn-icon-danger" title="Sil">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<?php if (empty($groups)): ?>
    <div class="empty-state">
        <i class="fas fa-folder-open"></i>
        <h3>Henüz grup yok</h3>
        <p>Ürünlerinizi organize etmek için ilk grubunuzu oluşturun.</p>
        <button onclick="openModal('addModal')" class="btn btn-primary">
            <i class="fas fa-plus"></i> Yeni Grup Ekle
        </button>
    </div>
<?php endif; ?>

<!-- Yardım -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-info-circle"></i> Nasıl Çalışır?</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px;">
            <div>
                <h4 style="color: var(--primary); margin-bottom: 10px;">
                    <i class="fas fa-1"
                        style="background: var(--primary); color: white; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; margin-right: 8px;"></i>
                    Grup Oluştur
                </h4>
                <p style="color: var(--gray); font-size: 14px;">
                    "Linux Hosting", "Windows Hosting", "VPS" gibi gruplar oluşturun.
                </p>
            </div>
            <div>
                <h4 style="color: var(--primary); margin-bottom: 10px;">
                    <i class="fas fa-2"
                        style="background: var(--primary); color: white; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; margin-right: 8px;"></i>
                    Ürün Ekle
                </h4>
                <p style="color: var(--gray); font-size: 14px;">
                    <a href="products.php">Ürünler</a> sayfasından ürün eklerken ilgili grubu seçin.
                </p>
            </div>
            <div>
                <h4 style="color: var(--primary); margin-bottom: 10px;">
                    <i class="fas fa-3"
                        style="background: var(--primary); color: white; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; margin-right: 8px;"></i>
                    Sayfada Göster
                </h4>
                <p style="color: var(--gray); font-size: 14px;">
                    <code>linux-hosting.php</code> gibi sayfalarda grup slug'ına göre ürünler otomatik listelenir.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Ekleme Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Yeni Grup Ekle</h3>
            <button onclick="closeModal('addModal')" class="modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label>Grup Adı *</label>
                    <input type="text" name="name" class="form-control" placeholder="Örn: Linux Hosting" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Slug (URL için)</label>
                        <input type="text" name="slug" class="form-control" placeholder="Örn: linux-hosting">
                        <small style="color: var(--gray);">Boş bırakılırsa otomatik oluşturulur</small>
                    </div>
                    <div class="form-group">
                        <label>Tür *</label>
                        <select name="type" class="form-control" required>
                            <?php foreach ($typeLabels as $slug => $info): ?>
                                <option value="<?= htmlspecialchars($slug) ?>"><?= htmlspecialchars($info['label']) ?>
                                </option>
                            <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Açıklama</label>
                    <textarea name="description" class="form-control" rows="2"
                        placeholder="Grup hakkında kısa açıklama"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Sıralama</label>
                        <input type="number" name="order_priority" class="form-control" value="0" min="0">
                    </div>
                    <div class="form-group" style="display: flex; align-items: center; padding-top: 30px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="is_hidden"> Gizli (Menüde gösterme)
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('addModal')" class="btn btn-cancel">İptal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Düzenleme Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Grup Düzenle</h3>
            <button onclick="closeModal('editModal')" class="modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editId">

                <div class="form-group">
                    <label>Grup Adı *</label>
                    <input type="text" name="name" id="editName" class="form-control" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Slug</label>
                        <input type="text" name="slug" id="editSlug" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Tür *</label>
                        <select name="type" id="editType" class="form-control" required>
                            <?php foreach ($typeLabels as $slug => $info): ?>
                                <option value="<?= htmlspecialchars($slug) ?>"><?= htmlspecialchars($info['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Açıklama</label>
                    <textarea name="description" id="editDescription" class="form-control" rows="2"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Sıralama</label>
                        <input type="number" name="order_priority" id="editOrder" class="form-control" min="0">
                    </div>
                    <div class="form-group" style="display: flex; align-items: center; padding-top: 30px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="is_hidden" id="editHidden"> Gizli
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('editModal')" class="btn btn-cancel">İptal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Güncelle
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function editGroup(group) {
        document.getElementById('editId').value = group.id;
        document.getElementById('editName').value = group.name;
        document.getElementById('editSlug').value = group.slug;
        document.getElementById('editType').value = group.type || 'hosting';
        document.getElementById('editDescription').value = group.description || '';
        document.getElementById('editOrder').value = group.order_priority;
        document.getElementById('editHidden').checked = group.is_hidden == 1;
        openModal('editModal');
    }

    // Copy link function
    function copyLink(url) {
        navigator.clipboard.writeText(url).then(function () {
            // Show success feedback
            const btn = event.target.closest('.copy-btn');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i>';
            btn.style.background = '#16a34a';

            setTimeout(function () {
                btn.innerHTML = originalHTML;
                btn.style.background = '';
            }, 2000);
        }).catch(function () {
            alert('Kopyalanamadı: ' + url);
        });
    }

    // Modal dışına tıklayınca kapat
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function (e) {
            if (e.target === this) {
                this.classList.remove('show');
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>