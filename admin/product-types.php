<?php
/**
 * WHMVM - Ürün Türleri Yönetimi
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

$pageTitle = 'Ürün Türleri';
$currentPage = 'product-types';
$db = Database::getInstance();
$message = '';
$messageType = 'success';

// Tablo yoksa oluştur
try {
    $db->exec("CREATE TABLE IF NOT EXISTS product_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(50) UNIQUE NOT NULL,
        label VARCHAR(100) NOT NULL,
        icon VARCHAR(50) DEFAULT 'fa-box',
        color VARCHAR(20) DEFAULT '#64748b',
        emoji VARCHAR(10) DEFAULT '📦',
        order_priority INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Varsayılan türleri ekle (yoksa)
    $count = Database::fetchColumn("SELECT COUNT(*) FROM product_types");
    if ($count == 0) {
        $defaultTypes = [
            ['hosting', 'Web Hosting', 'fa-globe', '#f97316', '🌐', 1],
            ['vps', 'VPS Sunucu', 'fa-server', '#10b981', '💻', 2],
            ['vds', 'VDS Sunucu', 'fa-database', '#6366f1', '🖥️', 3],
            ['dedicated', 'Fiziksel Sunucu', 'fa-building', '#8b5cf6', '🏢', 4],
            ['domain', 'Domain', 'fa-link', '#0ea5e9', '🔗', 5],
            ['ssl', 'SSL Sertifikası', 'fa-shield-alt', '#22c55e', '🔒', 6],
            ['email', 'E-posta', 'fa-envelope', '#f59e0b', '📧', 7],
            ['other', 'Diğer', 'fa-box', '#64748b', '📦', 8]
        ];
        foreach ($defaultTypes as $t) {
            Database::query(
                "INSERT INTO product_types (slug, label, icon, color, emoji, order_priority) VALUES (?, ?, ?, ?, ?, ?)",
                $t
            );
        }
    }
} catch (Exception $e) {
    // Tablo zaten varsa devam et
}

// Silme işlemi
/* Durum degistiren islem POST ile gelir; belirtec dogrulanir. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && is_numeric($_POST['delete'])) {
    Guvenlik::zorunlu();
    // Bu türü kullanan grup var mı kontrol et
    $groupCount = Database::fetchColumn("SELECT COUNT(*) FROM product_groups WHERE type = (SELECT slug FROM product_types WHERE id = ?)", [$_POST['delete']]);
    if ($groupCount > 0) {
        $message = "Bu türü kullanan $groupCount grup var. Önce grupların türünü değiştirin.";
        $messageType = 'danger';
    } else {
        Database::query("DELETE FROM product_types WHERE id = ?", [$_POST['delete']]);
        $message = 'Tür silindi.';
    }
}

// Ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $slug = trim($_POST['slug'] ?? '');
        $label = trim($_POST['label'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fa-box');
        $color = trim($_POST['color'] ?? '#64748b');
        $emoji = trim($_POST['emoji'] ?? '📦');
        $orderPriority = (int) ($_POST['order_priority'] ?? 0);

        if (empty($slug) || empty($label)) {
            $message = 'Slug ve etiket zorunludur!';
            $messageType = 'danger';
        } else {
            // Slug benzersiz mi kontrol et
            $exists = Database::fetchColumn("SELECT id FROM product_types WHERE slug = ?", [$slug]);
            if ($exists) {
                $message = 'Bu slug zaten kullanımda!';
                $messageType = 'danger';
            } else {
                Database::query(
                    "INSERT INTO product_types (slug, label, icon, color, emoji, order_priority) VALUES (?, ?, ?, ?, ?, ?)",
                    [$slug, $label, $icon, $color, $emoji, $orderPriority]
                );
                $message = 'Tür eklendi!';
            }
        }
    }

    if ($_POST['action'] === 'update') {
        $id = (int) $_POST['id'];
        $slug = trim($_POST['slug'] ?? '');
        $label = trim($_POST['label'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fa-box');
        $color = trim($_POST['color'] ?? '#64748b');
        $emoji = trim($_POST['emoji'] ?? '📦');
        $orderPriority = (int) ($_POST['order_priority'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Eski slug'ı al
        $oldSlug = Database::fetchColumn("SELECT slug FROM product_types WHERE id = ?", [$id]);

        // Eğer slug değiştiyse, grupları güncelle
        if ($oldSlug !== $slug) {
            Database::query("UPDATE product_groups SET type = ? WHERE type = ?", [$slug, $oldSlug]);
        }

        Database::query(
            "UPDATE product_types SET slug = ?, label = ?, icon = ?, color = ?, emoji = ?, order_priority = ?, is_active = ? WHERE id = ?",
            [$slug, $label, $icon, $color, $emoji, $orderPriority, $isActive, $id]
        );
        $message = 'Tür güncellendi!';
    }
}

// Türleri çek (kullanım sayısıyla birlikte)
$types = Database::fetchAll("
    SELECT t.*, COUNT(g.id) as group_count 
    FROM product_types t 
    LEFT JOIN product_groups g ON t.slug = g.type 
    GROUP BY t.id 
    ORDER BY t.order_priority, t.label
");

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* Type Cards Grid */
    .types-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .type-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 24px;
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }

    .type-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--type-color);
    }

    .type-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
    }

    .type-header {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 16px;
    }

    .type-icon {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
    }

    .type-info h4 {
        font-size: 18px;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 4px;
    }

    .type-info .slug {
        font-size: 13px;
        color: var(--gray);
        font-family: monospace;
        background: #f1f5f9;
        padding: 2px 8px;
        border-radius: 4px;
    }

    .type-stats {
        display: flex;
        gap: 20px;
        padding: 16px 0;
        border-top: 1px solid var(--border);
        border-bottom: 1px solid var(--border);
        margin-bottom: 16px;
    }

    .type-stat {
        text-align: center;
        flex: 1;
    }

    .type-stat .value {
        font-size: 24px;
        font-weight: 700;
        color: var(--dark);
    }

    .type-stat .label {
        font-size: 12px;
        color: var(--gray);
        text-transform: uppercase;
    }

    .type-actions {
        display: flex;
        gap: 8px;
    }

    .type-actions .btn {
        flex: 1;
        justify-content: center;
    }

    .badge-inactive {
        position: absolute;
        top: 16px;
        right: 16px;
        background: #fef3c7;
        color: #92400e;
        font-size: 10px;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 600;
        text-transform: uppercase;
    }

    /* Color Preview */
    .color-preview {
        display: inline-block;
        width: 24px;
        height: 24px;
        border-radius: 6px;
        border: 2px solid rgba(0, 0, 0, 0.1);
        vertical-align: middle;
        margin-right: 8px;
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

    .modal-close {
        background: none;
        border: none;
        font-size: 20px;
        cursor: pointer;
        color: var(--gray);
    }

    /* Icon Picker */
    .icon-list {
        display: grid;
        grid-template-columns: repeat(8, 1fr);
        gap: 8px;
        max-height: 200px;
        overflow-y: auto;
        padding: 10px;
        background: #f8fafc;
        border-radius: 10px;
        margin-top: 10px;
    }

    .icon-item {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        border: 2px solid transparent;
    }

    .icon-item:hover,
    .icon-item.selected {
        background: white;
        border-color: var(--primary);
        color: var(--primary);
    }

    /* Emoji Picker */
    .emoji-list {
        display: grid;
        grid-template-columns: repeat(10, 1fr);
        gap: 5px;
        max-height: 150px;
        overflow-y: auto;
        padding: 10px;
        background: #f8fafc;
        border-radius: 10px;
        margin-top: 10px;
    }

    .emoji-item {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        border: 2px solid transparent;
    }

    .emoji-item:hover,
    .emoji-item.selected {
        background: white;
        border-color: var(--primary);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .types-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="card" style="margin-bottom: 25px;">
    <div class="card-header">
        <h3><i class="fas fa-tags"></i> Ürün Türleri</h3>
        <button onclick="openModal('addModal')" class="btn btn-primary">
            <i class="fas fa-plus"></i> Yeni Tür
        </button>
    </div>
</div>

<div class="types-grid">
    <?php foreach ($types as $type): ?>
        <div class="type-card" style="--type-color: <?= htmlspecialchars($type['color']) ?>;">
            <?php if (!$type['is_active']): ?>
                <span class="badge-inactive">Pasif</span>
            <?php endif; ?>

            <div class="type-header">
                <div class="type-icon"
                    style="background: linear-gradient(135deg, <?= htmlspecialchars($type['color']) ?>, <?= htmlspecialchars($type['color']) ?>dd);">
                    <i class="fas <?= htmlspecialchars($type['icon']) ?>"></i>
                </div>
                <div class="type-info">
                    <h4>
                        <?= htmlspecialchars($type['emoji']) ?>
                        <?= htmlspecialchars($type['label']) ?>
                    </h4>
                    <span class="slug">
                        <?= htmlspecialchars($type['slug']) ?>
                    </span>
                </div>
            </div>

            <div class="type-stats">
                <div class="type-stat">
                    <div class="value">
                        <?= $type['group_count'] ?>
                    </div>
                    <div class="label">Grup</div>
                </div>
                <div class="type-stat">
                    <div class="value">
                        <?= $type['order_priority'] ?>
                    </div>
                    <div class="label">Sıra</div>
                </div>
            </div>

            <div class="type-actions">
                <button onclick="editType(<?= htmlspecialchars(json_encode($type)) ?>)" class="btn btn-outline btn-sm">
                    <i class="fas fa-edit"></i> Düzenle
                </button>
                <?php if ($type['group_count'] == 0): ?>
                    <button onclick="confirmDelete('Bu türü silmek istediğinizden emin misiniz?', '?delete=<?= $type['id'] ?>')"
                        class="btn btn-danger btn-sm">
                        <i class="fas fa-trash"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (empty($types)): ?>
    <div class="empty-state">
        <i class="fas fa-tags" style="font-size: 56px; color: #cbd5e1; margin-bottom: 20px;"></i>
        <h3>Henüz tür yok</h3>
        <p>Ürün gruplarınızı kategorize etmek için tür ekleyin.</p>
        <button onclick="openModal('addModal')" class="btn btn-primary">
            <i class="fas fa-plus"></i> Yeni Tür Ekle
        </button>
    </div>
<?php endif; ?>

<!-- Ekleme Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Yeni Tür Ekle</h3>
            <button onclick="closeModal('addModal')" class="modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">

                <div class="form-row">
                    <div class="form-group">
                        <label>Etiket *</label>
                        <input type="text" name="label" class="form-control" placeholder="Örn: Web Hosting" required>
                    </div>
                    <div class="form-group">
                        <label>Slug *</label>
                        <input type="text" name="slug" class="form-control" placeholder="Örn: hosting" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Emoji</label>
                        <input type="text" name="emoji" id="addEmoji" class="form-control" value="📦" maxlength="4">
                        <div class="emoji-list">
                            <?php
                            $emojis = ['🌐', '💻', '🖥️', '🏢', '🔗', '🔒', '📧', '📦', '☁️', '🚀', '⚡', '🛡️', '💾', '🗄️', '📊', '🔧', '⚙️', '🎮', '📱', '💳'];
                            foreach ($emojis as $e): ?>
                                <span class="emoji-item" onclick="selectEmoji(this, 'addEmoji')">
                                    <?= $e ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Renk</label>
                        <input type="color" name="color" class="form-control" value="#64748b" style="height: 46px;">
                    </div>
                </div>

                <div class="form-group">
                    <label>İkon (Font Awesome)</label>
                    <input type="text" name="icon" id="addIcon" class="form-control" value="fa-box"
                        placeholder="fa-box">
                    <div class="icon-list">
                        <?php
                        $icons = ['fa-globe', 'fa-server', 'fa-database', 'fa-building', 'fa-link', 'fa-shield-alt', 'fa-envelope', 'fa-box', 'fa-cloud', 'fa-rocket', 'fa-bolt', 'fa-shield', 'fa-hdd', 'fa-network-wired', 'fa-chart-bar', 'fa-cog'];
                        foreach ($icons as $i): ?>
                            <span class="icon-item" onclick="selectIcon(this, 'addIcon')"><i
                                    class="fas <?= $i ?>"></i></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Sıralama</label>
                    <input type="number" name="order_priority" class="form-control" value="0" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('addModal')" class="btn btn-outline">İptal</button>
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
            <h3><i class="fas fa-edit"></i> Tür Düzenle</h3>
            <button onclick="closeModal('editModal')" class="modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editId">

                <div class="form-row">
                    <div class="form-group">
                        <label>Etiket *</label>
                        <input type="text" name="label" id="editLabel" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Slug *</label>
                        <input type="text" name="slug" id="editSlug" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Emoji</label>
                        <input type="text" name="emoji" id="editEmoji" class="form-control" maxlength="4">
                        <div class="emoji-list">
                            <?php foreach ($emojis as $e): ?>
                                <span class="emoji-item" onclick="selectEmoji(this, 'editEmoji')">
                                    <?= $e ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Renk</label>
                        <input type="color" name="color" id="editColor" class="form-control" style="height: 46px;">
                    </div>
                </div>

                <div class="form-group">
                    <label>İkon (Font Awesome)</label>
                    <input type="text" name="icon" id="editIcon" class="form-control">
                    <div class="icon-list">
                        <?php foreach ($icons as $i): ?>
                            <span class="icon-item" onclick="selectIcon(this, 'editIcon')"><i
                                    class="fas <?= $i ?>"></i></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Sıralama</label>
                        <input type="number" name="order_priority" id="editOrder" class="form-control" min="0">
                    </div>
                    <div class="form-group" style="display: flex; align-items: center; padding-top: 30px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="is_active" id="editActive"> Aktif
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('editModal')" class="btn btn-outline">İptal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Güncelle
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) {
        document.getElementById(id).classList.add('show');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    function editType(type) {
        document.getElementById('editId').value = type.id;
        document.getElementById('editLabel').value = type.label;
        document.getElementById('editSlug').value = type.slug;
        document.getElementById('editEmoji').value = type.emoji || '📦';
        document.getElementById('editIcon').value = type.icon || 'fa-box';
        document.getElementById('editColor').value = type.color || '#64748b';
        document.getElementById('editOrder').value = type.order_priority;
        document.getElementById('editActive').checked = type.is_active == 1;
        openModal('editModal');
    }

    function selectIcon(el, inputId) {
        const icon = el.querySelector('i').className.replace('fas ', '');
        document.getElementById(inputId).value = icon;
        // Remove selected from all
        el.parentElement.querySelectorAll('.icon-item').forEach(i => i.classList.remove('selected'));
        el.classList.add('selected');
    }

    function selectEmoji(el, inputId) {
        document.getElementById(inputId).value = el.textContent;
        // Remove selected from all
        el.parentElement.querySelectorAll('.emoji-item').forEach(i => i.classList.remove('selected'));
        el.classList.add('selected');
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