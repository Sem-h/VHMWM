<?php
/**
 * WHMVM - Referans Yönetimi (Ultra Premium UI)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Referanslar';
$currentPage = 'references';

// Yardımcı Fonksiyonlar
function getUniqueCategories()
{
    $db = Database::getInstance();
    $rows = $db->query("SELECT DISTINCT category FROM `references` WHERE category IS NOT NULL AND category != ''")->fetchAll(PDO::FETCH_COLUMN);
    $defaults = ['Yazılım', 'E-Ticaret', 'Kurumsal', 'Hosting', 'Oyun Sunucusu', 'SaaS', 'Fintech'];
    $all = array_merge($rows, $defaults);
    $all = array_map('trim', $all);
    $all = array_unique($all);
    sort($all);
    return $all;
}

// İşlemler (Silme, Ekleme, Güncelleme)
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['delete'])) {
    $db = Database::getInstance();
    try {
        if (isset($_GET['delete'])) {
            $stmt = $db->prepare("DELETE FROM `references` WHERE id = ?");
            $stmt->execute([(int) $_GET['delete']]);
            $msg = ['type' => 'success', 'text' => 'Referans silindi.'];
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $logo = trim($_POST['logo'] ?? '');
            $website = trim($_POST['website'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $dark_logo = isset($_POST['dark_logo']) ? 1 : 0;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $sort_order = (int) ($_POST['sort_order'] ?? 0);

            if ($id > 0) {
                $sql = "UPDATE `references` SET name=?, logo=?, website=?, category=?, description=?, dark_logo=?, is_active=?, sort_order=? WHERE id=?";
                $db->prepare($sql)->execute([$name, $logo, $website, $category, $description, $dark_logo, $is_active, $sort_order, $id]);
                $msg = ['type' => 'success', 'text' => 'Güncelleme başarılı!'];
            } else {
                $sql = "INSERT INTO `references` (name, logo, website, category, description, dark_logo, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $db->prepare($sql)->execute([$name, $logo, $website, $category, $description, $dark_logo, $is_active, $sort_order]);
                $msg = ['type' => 'success', 'text' => 'Yeni referans eklendi!'];
            }
        }
        $_SESSION['flash_message'] = $msg;
        header('Location: references.php');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Verileri Çek
$db = Database::getInstance();
$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM `references` WHERE id = ?");
    $stmt->execute([(int) $_GET['edit']]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Filtreleme
$filterCategory = $_GET['cat'] ?? '';
$sql = "SELECT * FROM `references` WHERE 1=1";
if ($filterCategory) {
    $sql .= " AND category = " . $db->quote($filterCategory);
}
$sql .= " ORDER BY sort_order ASC, id DESC";
$references = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$categories = getUniqueCategories();

include 'includes/header.php';
?>

<!-- Ultra Premium Styles -->
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --bg-color: #f8fafc;
    --card-bg: #ffffff;
    --primary: #4f46e5;
    --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    --text-main: #0f172a;
    --text-muted: #64748b;
    --border: #e2e8f0;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    --shadow-lg: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
}

body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background-color: #f1f5f9;
    background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
    background-size: 24px 24px;
}

.main-container {
    padding-bottom: 50px;
}

/* Header Area */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 30px;
    background: white;
    padding: 25px 30px;
    border-radius: 20px;
    box-shadow: var(--shadow-sm);
    border: 1px solid rgba(255,255,255,0.5);
    backdrop-filter: blur(10px);
}

.header-title h1 {
    font-size: 28px;
    font-weight: 800;
    color: var(--text-main);
    margin: 0;
    letter-spacing: -0.5px;
}

.header-title p {
    margin: 5px 0 0;
    color: var(--text-muted);
    font-size: 15px;
}

.header-stats {
    display: flex;
    gap: 15px;
}

.stat-badge {
    padding: 10px 20px;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid var(--border);
    text-align: center;
}

.stat-badge span {
    display: block;
    font-size: 20px;
    font-weight: 700;
    color: var(--primary);
}

.stat-badge small {
    color: var(--text-muted);
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 600;
}

/* Filter Bar */
.filter-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
    overflow-x: auto;
    padding-bottom: 10px;
    scrollbar-width: none;
}

.filter-btn {
    padding: 10px 20px;
    border-radius: 50px;
    background: white;
    border: 1px solid var(--border);
    color: var(--text-muted);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.filter-btn:hover {
    transform: translateY(-2px);
    border-color: var(--primary);
    color: var(--primary);
}

.filter-btn.active {
    background: var(--text-main);
    color: white;
    border-color: var(--text-main);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2);
}

/* Reference Card */
.grid-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 30px;
}

.ref-card-item {
    background: white;
    border-radius: 24px;
    overflow: hidden;
    border: 1px solid rgba(226, 232, 240, 0.8);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    box-shadow: var(--shadow-sm);
    group: hover;
}

.ref-card-item:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 40px rgba(0,0,0,0.12);
    border-color: rgba(99, 102, 241, 0.3);
    z-index: 10;
}

.card-top-section {
    height: 160px;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    padding: 30px;
}

.card-top-section.dark-mode {
    background: #0f172a;
}

.ref-logo-img {
    max-width: 100%;
    max-height: 80px;
    object-fit: contain;
    filter: grayscale(100%);
    opacity: 0.7;
    transition: all 0.4s ease;
}

.ref-card-item:hover .ref-logo-img {
    filter: grayscale(0%);
    opacity: 1;
    transform: scale(1.1);
}

.status-check {
    position: absolute;
    top: 15px; left: 15px;
    width: 12px; height: 12px;
    border-radius: 50%;
    background: #e2e8f0;
    box-shadow: 0 0 0 4px white;
}

.status-check.active {
    background: #10b981;
    box-shadow: 0 0 0 4px white, 0 0 0 6px #d1fae5;
}

.order-pill {
    position: absolute;
    top: 15px; right: 15px;
    background: rgba(255,255,255,0.8);
    backdrop-filter: blur(4px);
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    color: var(--text-muted);
}

.card-content {
    padding: 24px;
}

.card-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-main);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.card-category {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 6px;
    background: #f1f5f9;
    color: var(--primary);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 15px;
}

.card-desc {
    color: var(--text-muted);
    font-size: 13px;
    line-height: 1.6;
    margin-bottom: 20px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Action Overlay */
.card-actions {
    display: flex;
    gap: 8px;
    padding-top: 15px;
    border-top: 1px solid #f1f5f9;
}

.btn-card {
    flex: 1;
    padding: 10px;
    border-radius: 10px;
    border: none;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.btn-edit {
    background: #f1f5f9;
    color: var(--text-main);
}

.btn-edit:hover {
    background: var(--text-main);
    color: white;
}

.btn-delete {
    background: #fef2f2;
    color: #ef4444;
}

.btn-delete:hover {
    background: #ef4444;
    color: white;
}

/* Floating Add Button */
.fab-container {
    position: fixed;
    bottom: 40px;
    right: 40px;
    z-index: 100;
}

.fab-btn {
    width: 65px;
    height: 65px;
    background: var(--text-main);
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    box-shadow: 0 10px 25px rgba(15, 23, 42, 0.4);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.fab-btn:hover {
    transform: scale(1.1) rotate(90deg);
}

/* Modal / Slide Panel */
.slide-panel {
    position: fixed;
    top: 0; right: -500px;
    width: 450px;
    height: 100vh;
    background: white;
    z-index: 1000;
    box-shadow: -10px 0 50px rgba(0,0,0,0.1);
    transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    padding: 0;
    display: flex;
    flex-direction: column;
}

.slide-panel.active {
    right: 0;
}

.panel-header {
    padding: 25px 30px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8fafc;
}

.panel-body {
    flex: 1;
    overflow-y: auto;
    padding: 30px;
}

.backdrop {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(4px);
    z-index: 999;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.4s;
}

.backdrop.active {
    opacity: 1;
    pointer-events: all;
}

/* Form Styles */
.form-input {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 14px;
    background: white;
    transition: all 0.2s;
    outline: none;
}

.form-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
}

.label-bold {
    font-weight: 700;
    font-size: 13px;
    margin-bottom: 8px;
    display: block;
    color: var(--text-main);
}

.btn-save {
    width: 100%;
    padding: 16px;
    background: var(--text-main);
    color: white;
    border: none;
    border-radius: 14px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    transition: transform 0.2s;
}

.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(15, 23, 42, 0.2);
}

</style>

<div class="main-container">
    
    <!-- Flash Message -->
    <?php if (isset($_SESSION['flash_message'])):
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']); ?>
            <div class="alert alert-success mb-4 d-flex align-items-center gap-3 shadow-sm bg-white border-0" 
                 style="border-left: 5px solid #10b981; border-radius: 12px;">
                <i class="fas fa-check-circle text-success fs-4"></i>
                <strong><?= $msg['text'] ?></strong>
            </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="page-header">
        <div class="header-title">
            <h1>Referanslar & Markalar</h1>
            <p>Müşterileri ve çözüm ortaklarını yönetin.</p>
        </div>
        <div class="header-stats">
            <div class="stat-badge">
                <span><?= count($references) ?></span>
                <small>Toplam</small>
            </div>
            <div class="stat-badge">
                <span><?= count(array_filter($references, fn($r) => $r['is_active'])) ?></span>
                <small>Aktif</small>
            </div>
            <button onclick="togglePanel()" class="btn btn-primary d-flex align-items-center gap-2" style="background: var(--primary); border-radius: 12px; padding: 0 24px;">
                <i class="fas fa-plus"></i> Ekle
            </button>
        </div>
    </div>

    <!-- Filter -->
    <div class="filter-bar">
        <a href="references.php" class="filter-btn <?= empty($filterCategory) ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i> Tümü
        </a>
        <?php foreach ($categories as $cat): ?>
                <a href="?cat=<?= urlencode($cat) ?>" class="filter-btn <?= $filterCategory === $cat ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat) ?>
                </a>
        <?php endforeach; ?>
    </div>

    <!-- Grid -->
    <div class="grid-container">
        <?php foreach ($references as $ref): ?>
                <div class="ref-card-item">
                    <div class="card-top-section <?= $ref['dark_logo'] ? 'dark-mode' : '' ?>">
                        <div class="status-check <?= $ref['is_active'] ? 'active' : '' ?>"></div>
                        <div class="order-pill">#<?= $ref['sort_order'] ?></div>
                    
                        <?php if ($ref['logo']): ?>
                                <img src="<?= htmlspecialchars($ref['logo']) ?>" class="ref-logo-img" alt="<?= htmlspecialchars($ref['name']) ?>">
                        <?php else: ?>
                                <span class="fs-1 fw-bold text-muted opacity-25"><?= mb_substr($ref['name'], 0, 2) ?></span>
                        <?php endif; ?>
                    </div>
                
                    <div class="card-content">
                        <span class="card-category"><?= htmlspecialchars($ref['category']) ?></span>
                        <h3 class="card-title">
                            <?= htmlspecialchars($ref['name']) ?>
                            <?php if ($ref['website']): ?>
                                    <a href="<?= $ref['website'] ?>" target="_blank" class="text-muted small"><i class="fas fa-external-link-alt"></i></a>
                            <?php endif; ?>
                        </h3>
                        <p class="card-desc"><?= htmlspecialchars($ref['description']) ?: 'Açıklama belirtilmemiş.' ?></p>
                    
                        <div class="card-actions">
                            <a href="references.php?edit=<?= $ref['id'] ?>" class="btn-card btn-edit">
                                <i class="fas fa-pencil-alt"></i> Düzenle
                            </a>
                            <a href="references.php?delete=<?= $ref['id'] ?>" 
                               class="btn-card btn-delete"
                               onclick="return confirm('Bu referansı silmek istediğinize emin misiniz?');">
                                <i class="fas fa-trash"></i> Sil
                            </a>
                        </div>
                    </div>
                </div>
        <?php endforeach; ?>
    </div>

    <!-- Floating Action Button -->
    <div class="fab-container">
        <div class="fab-btn" onclick="togglePanel()" title="Hızlı Ekle">
            <i class="fas fa-plus"></i>
        </div>
    </div>

</div>

<!-- Slide Panel (Right Sidebar) -->
<div class="backdrop" id="panelBackdrop" onclick="togglePanel()"></div>
<div class="slide-panel" id="slidePanel">
    <div class="panel-header">
        <h3 class="m-0 fw-bold text-dark"><?= $editData ? 'Referansı Düzenle' : 'Yeni Referans' ?></h3>
        <button onclick="togglePanel()" class="btn btn-light rounded-circle"><i class="fas fa-times"></i></button>
    </div>
    <div class="panel-body">
        <form method="POST">
            <?php if ($editData): ?>
                    <input type="hidden" name="id" value="<?= $editData['id'] ?>">
            <?php endif; ?>

            <div class="mb-4">
                <label class="label-bold">Firma / Marka Adı</label>
                <input type="text" name="name" class="form-input" placeholder="Örn: Google" value="<?= htmlspecialchars($editData['name'] ?? '') ?>" required>
            </div>

            <div class="mb-4">
                <label class="label-bold">Logo URL</label>
                <input type="text" name="logo" class="form-input" placeholder="Resim linki..." value="<?= htmlspecialchars($editData['logo'] ?? '') ?>">
                <small class="text-muted d-block mt-2"><i class="fas fa-info-circle"></i> PNG veya SVG önerilir.</small>
            </div>

            <div class="row mb-4">
                <div class="col-6">
                    <label class="label-bold">Kategori</label>
                    <input type="text" name="category" list="catList" class="form-input" placeholder="Seçim yapın" value="<?= htmlspecialchars($editData['category'] ?? '') ?>">
                    <datalist id="catList">
                        <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-6">
                    <label class="label-bold">Sıralama</label>
                    <input type="number" name="sort_order" class="form-input" value="<?= $editData['sort_order'] ?? 0 ?>">
                </div>
            </div>

            <div class="mb-4">
                <label class="label-bold">Web Sitesi</label>
                <input type="url" name="website" class="form-input" placeholder="https://" value="<?= htmlspecialchars($editData['website'] ?? '') ?>">
            </div>

            <div class="mb-4">
                <label class="label-bold">Açıklama</label>
                <textarea name="description" class="form-input" rows="3" placeholder="Marka hakkında..."><?= htmlspecialchars($editData['description'] ?? '') ?></textarea>
            </div>

            <div class="mb-4 d-flex gap-4 p-3 bg-light rounded-3">
                <div class="form-check form-switch ps-0">
                    <label class="cursor-pointer d-flex align-items-center gap-2">
                        <input class="form-check-input ms-0" type="checkbox" name="is_active" <?= ($editData['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <span class="fw-bold text-dark small">Yayında</span>
                    </label>
                </div>
                <div class="vr opacity-25"></div>
                <div class="form-check form-switch ps-0">
                    <label class="cursor-pointer d-flex align-items-center gap-2">
                        <input class="form-check-input ms-0" type="checkbox" name="dark_logo" <?= ($editData['dark_logo'] ?? 0) ? 'checked' : '' ?>>
                        <span class="fw-bold text-dark small">Koyu Logo</span>
                    </label>
                </div>
            </div>

            <div class="mt-5">
                <button type="submit" class="btn-save">
                    <?= $editData ? 'Değişiklikleri Kaydet' : 'Referansı Ekle' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePanel() {
    const panel = document.getElementById('slidePanel');
    const backdrop = document.getElementById('panelBackdrop');
    panel.classList.toggle('active');
    backdrop.classList.toggle('active');
}

// Edit modundaysa paneli otomatik aç
<?php if ($editData): ?>
        document.addEventListener('DOMContentLoaded', togglePanel);
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>