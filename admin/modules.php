<?php
/**
 * WHMVM - Modül Yönetimi (Premium UI)
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

$pageTitle = 'Modül Mağazası';
$currentPage = 'modules';
$message = '';
$messageType = 'success';

// DB Kontrol (Tablo Yoksa Oluştur)
try {
    Database::query("SELECT 1 FROM modules LIMIT 1");
} catch (Exception $e) {
    Database::query("
        CREATE TABLE IF NOT EXISTS `modules` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL UNIQUE,
            `slug` VARCHAR(100) NOT NULL UNIQUE,
            `version` VARCHAR(20) NOT NULL,
            `description` TEXT,
            `author` VARCHAR(100),
            `author_url` VARCHAR(255),
            `category` VARCHAR(50) DEFAULT 'general',
            `is_active` TINYINT(1) DEFAULT 0,
            `install_path` VARCHAR(255) NOT NULL,
            `config` JSON,
            `dependencies` JSON,
            `installed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// Modül Yükleme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['module_zip'])) {
    $file = $_FILES['module_zip'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) === TRUE) {
            $jsonContent = $zip->getFromName('module.json');
            if (!$jsonContent) {
                // Alt klasörde ara
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    if (basename($zip->getNameIndex($i)) === 'module.json') {
                        $jsonContent = $zip->getFromIndex($i);
                        break;
                    }
                }
            }

            if ($jsonContent) {
                $data = json_decode($jsonContent, true);
                if (isset($data['name'], $data['slug'])) {
                    $slug = $data['slug'];
                    $targetDir = dirname(__DIR__) . '/modules/' . $slug;

                    if (!is_dir($targetDir))
                        mkdir($targetDir, 0755, true);
                    $zip->extractTo($targetDir);
                    $zip->close();

                    // DB Kayıt/Update
                    $exists = Database::fetch("SELECT id FROM modules WHERE slug=?", [$slug]);
                    if ($exists) {
                        Database::query("UPDATE modules SET version=?, description=?, updated_at=NOW() WHERE slug=?", [
                            $data['version'],
                            $data['description'] ?? '',
                            $slug
                        ]);
                        $message = "Modül güncellendi: " . $data['name'];
                    } else {
                        Database::query("INSERT INTO modules (name, slug, version, description, author, category, install_path) VALUES (?,?,?,?,?,?,?)", [
                            $data['name'],
                            $slug,
                            $data['version'],
                            $data['description'] ?? '',
                            $data['author'] ?? '',
                            $data['category'] ?? 'general',
                            'modules/' . $slug
                        ]);
                        $message = "Yeni modül yüklendi: " . $data['name'];
                    }
                } else {
                    $message = "module.json geçersiz.";
                    $messageType = 'danger';
                }
            } else {
                $message = "module.json bulunamadı.";
                $messageType = 'danger';
            }
        }
    }
}

// Aktif/Pasif
if (isset($_POST['toggle_id'])) {
    $id = (int) $_POST['toggle_id'];
    $m = Database::fetch("SELECT is_active FROM modules WHERE id=?", [$id]);
    if ($m) {
        $newState = $m['is_active'] ? 0 : 1;
        Database::query("UPDATE modules SET is_active=? WHERE id=?", [$newState, $id]);
        $message = "Modül durumu değiştirildi.";
    }
}

// Silme
if (isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];
    Database::query("DELETE FROM modules WHERE id=?", [$id]);
    // Klasör silme işlemi eklenebilir
    $message = "Modül kaldırıldı.";
}

// Modülleri Çek
$modules = Database::fetchAll("SELECT * FROM modules ORDER BY is_active DESC, name ASC");
$categories = ['all' => 'Tümü', 'payment' => 'Ödeme', 'seo' => 'SEO', 'integration' => 'Entegrasyon', 'general' => 'Genel'];

include 'includes/header.php';
?>

<style>
    :root {
        --m-primary: #6366f1;
        --m-bg: #f8fafc;
        --m-card: #ffffff;
        --m-text: #1e293b;
    }

    body {
        background-color: #f1f5f9;
    }

    .module-header-area {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }

    .upload-btn {
        background: var(--m-primary);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 12px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 4px 6px rgba(99, 102, 241, 0.2);
    }

    .upload-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 15px rgba(99, 102, 241, 0.3);
    }

    /* Category Filters */
    .cat-filters {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        padding-bottom: 5px;
        margin-bottom: 30px;
    }

    .cat-btn {
        background: white;
        border: 1px solid #e2e8f0;
        padding: 10px 20px;
        border-radius: 50px;
        font-size: 14px;
        font-weight: 600;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .cat-btn:hover,
    .cat-btn.active {
        background: var(--m-primary);
        color: white;
        border-color: var(--m-primary);
    }

    /* Grid */
    .modules-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 25px;
    }

    /* Module Card */
    .mod-card {
        background: white;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        display: flex;
        flex-direction: column;
    }

    .mod-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
    }

    .mod-status-bar {
        height: 6px;
        width: 100%;
    }

    .mod-status-bar.active {
        background: #10b981;
    }

    .mod-status-bar.inactive {
        background: #e2e8f0;
    }

    .mod-body {
        padding: 25px;
        flex: 1;
    }

    .mod-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .mod-icon {
        width: 56px;
        height: 56px;
        background: #f1f5f9;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: var(--m-primary);
    }

    .mod-badge {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .mod-badge.active {
        background: #dcfce7;
        color: #15803d;
    }

    .mod-badge.inactive {
        background: #f1f5f9;
        color: #64748b;
    }

    .mod-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--m-text);
        margin-bottom: 5px;
    }

    .mod-desc {
        font-size: 13px;
        color: #64748b;
        line-height: 1.6;
        margin-bottom: 20px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .mod-meta {
        display: flex;
        gap: 15px;
        font-size: 12px;
        color: #94a3b8;
        margin-top: auto;
        border-top: 1px solid #f1f5f9;
        padding-top: 15px;
    }

    .mod-actions {
        padding: 15px 25px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .btn-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
        background: white;
        border: 1px solid #e2e8f0;
        color: #64748b;
    }

    .btn-icon:hover {
        background: #f1f5f9;
        color: #1e293b;
    }

    .btn-icon.delete:hover {
        background: #fee2e2;
        color: #ef4444;
        border-color: #fee2e2;
    }

    .btn-settings {
        background: var(--m-primary);
        color: white;
        border: none;
        padding: 0 20px;
        height: 36px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-settings:hover {
        opacity: 0.9;
    }

    /* Upload Modal */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .modal-overlay.active {
        display: flex;
        animation: fadeIn 0.3s;
    }

    .upload-card {
        background: white;
        padding: 40px;
        border-radius: 24px;
        width: 100%;
        max-width: 500px;
        text-align: center;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
    }

    .drop-zone {
        border: 3px dashed #e2e8f0;
        border-radius: 16px;
        padding: 40px 20px;
        margin: 20px 0;
        transition: all 0.2s;
        background: #f8fafc;
        cursor: pointer;
    }

    .drop-zone:hover,
    .drop-zone.dragover {
        border-color: var(--m-primary);
        background: #eef2ff;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }
</style>

<div style="max-width: 1200px; margin: 0 auto; padding-bottom: 50px;">

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType == 'danger' ? 'danger' : 'success' ?> mb-4 shadow-sm border-0 d-flex align-items-center gap-2"
            style="border-radius: 12px;">
            <i class="fas fa-<?= $messageType == 'danger' ? 'exclamation-circle' : 'check-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="module-header-area">
        <div>
            <h1 class="h3 fw-bold mb-1">Modüller & Eklentiler</h1>
            <p class="text-muted m-0">Sisteminizi genişletmek için modülleri yönetin.</p>
        </div>
        <button class="upload-btn" onclick="openUploadModal()">
            <i class="fas fa-cloud-upload-alt"></i> Modül Yükle
        </button>
    </div>

    <!-- Filtreler -->
    <div class="cat-filters">
        <button class="cat-btn active" onclick="filterModules('all', this)">Tümü</button>
        <?php foreach ($categories as $key => $name):
            if ($key == 'all')
                continue; ?>
            <button class="cat-btn" onclick="filterModules('<?= $key ?>', this)"><?= $name ?></button>
        <?php endforeach; ?>
    </div>

    <!-- Modül Grid -->
    <div class="modules-grid">
        <?php foreach ($modules as $mod): ?>
            <div class="mod-card category-<?= $mod['category'] ?>">
                <div class="mod-status-bar <?= $mod['is_active'] ? 'active' : 'inactive' ?>"></div>
                <div class="mod-body">
                    <div class="mod-top">
                        <div class="mod-icon">
                            <?php
                            $icon = 'fa-cube';
                            if ($mod['category'] == 'payment')
                                $icon = 'fa-credit-card';
                            if ($mod['category'] == 'seo')
                                $icon = 'fa-search';
                            if ($mod['category'] == 'integration')
                                $icon = 'fa-plug';
                            if ($mod['slug'] == 'netgsm')
                                $icon = 'fa-sms';
                            ?>
                            <i class="fas <?= $icon ?>"></i>
                        </div>
                        <span class="mod-badge <?= $mod['is_active'] ? 'active' : 'inactive' ?>">
                            <?= $mod['is_active'] ? 'AKTİF' : 'PASİF' ?>
                        </span>
                    </div>

                    <h3 class="mod-title"><?= htmlspecialchars($mod['name']) ?></h3>
                    <p class="mod-desc"><?= htmlspecialchars($mod['description'] ?? 'Açıklama yok.') ?></p>

                    <div class="mod-meta">
                        <span><i class="fas fa-code-branch me-1"></i> v<?= $mod['version'] ?></span>
                        <span><i class="fas fa-user me-1"></i> <?= htmlspecialchars($mod['author'] ?? 'Admin') ?></span>
                    </div>
                </div>

                <div class="mod-actions">
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="toggle_id" value="<?= $mod['id'] ?>">
                        <button type="submit" class="btn-icon" title="<?= $mod['is_active'] ? 'Pasif Yap' : 'Aktif Et' ?>">
                            <i class="fas fa-power-off <?= $mod['is_active'] ? 'text-success' : 'text-muted' ?>"></i>
                        </button>
                    </form>

                    <form method="POST" style="margin: 0;" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                        <input type="hidden" name="delete_id" value="<?= $mod['id'] ?>">
                        <button type="submit" class="btn-icon delete" title="Sil">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </form>

                    <?php
                    // Ayarlar Sayfası Belirleme
                    $settingsLink = '#';
                    if ($mod['slug'] == 'domainnameapi')
                        $settingsLink = 'settings.php'; // Entegrasyonlar sekmesine git
                    elseif ($mod['slug'] == 'seo-manager')
                        $settingsLink = 'seo-manager.php';
                    elseif ($mod['slug'] == 'netgsm')
                        $settingsLink = '../modules/netgsm/admin.php';
                    else
                        $settingsLink = 'modules.php?module=' . $mod['slug'] . '&action=settings';
                    ?>

                    <a href="<?= $settingsLink ?>" class="btn-settings">
                        <i class="fas fa-cog"></i> Ayarlar
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal-overlay" id="uploadModal">
    <div class="upload-card">
        <h2 class="h4 fw-bold mb-3">Yeni Modül Yükle</h2>
        <p class="text-muted mb-4">.zip uzantılı modül dosyasını sürükleyip bırakın.</p>

        <form method="POST" enctype="multipart/form-data">
            <div class="drop-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
                <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                <div id="dropText" class="fw-bold text-dark">Dosya Seçin</div>
                <input type="file" name="module_zip" id="fileInput" accept=".zip" hidden onchange="showFileName(this)">
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="button" class="btn btn-light w-50 py-3 rounded-3 fw-bold"
                    onclick="closeUploadModal()">İptal</button>
                <button type="submit" class="btn btn-primary w-50 py-3 rounded-3 fw-bold">Yükle</button>
            </div>
        </form>
    </div>
</div>

<script>
    function filterModules(cat, btn) {
        document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const cards = document.querySelectorAll('.mod-card');
        cards.forEach(card => {
            if (cat === 'all' || card.classList.contains('category-' + cat)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function openUploadModal() {
        document.getElementById('uploadModal').classList.add('active');
    }
    function closeUploadModal() {
        document.getElementById('uploadModal').classList.remove('active');
    }
    function showFileName(input) {
        if (input.files && input.files[0]) {
            document.getElementById('dropText').innerText = input.files[0].name;
        }
    }

    // Close modal on outside click
    document.getElementById('uploadModal').addEventListener('click', function (e) {
        if (e.target === this) closeUploadModal();
    });
</script>

<?php include 'includes/footer.php'; ?>