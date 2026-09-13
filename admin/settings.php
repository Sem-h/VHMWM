<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Site Ayarları';
$currentPage = 'settings';
$db = Database::getInstance();
$message = '';
$messageType = 'success';

// Uploads dizini
$uploadDir = dirname(__DIR__) . '/uploads/logos/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Logo yükleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Logo yükleme
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/svg+xml', 'image/webp'];
        $fileType = $_FILES['site_logo']['type'];

        if (in_array($fileType, $allowedTypes)) {
            $extension = pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION);
            $fileName = 'logo_' . time() . '.' . $extension;
            $targetPath = $uploadDir . $fileName;

            // Eski logoyu sil
            $oldLogo = Settings::get('site_logo', '');
            if (!empty($oldLogo)) {
                $oldPath = dirname(__DIR__) . '/' . ltrim($oldLogo, '/');
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $targetPath)) {
                Settings::set('site_logo', 'uploads/logos/' . $fileName, 'appearance');
                $message = 'Logo başarıyla yüklendi!';
            } else {
                $message = 'Logo yüklenirken bir hata oluştu!';
                $messageType = 'danger';
            }
        } else {
            $message = 'Geçersiz dosya türü! PNG, JPG, GIF, SVG veya WebP yükleyebilirsiniz.';
            $messageType = 'danger';
        }
    }

    // Favicon yükleme
    if (isset($_FILES['site_favicon']) && $_FILES['site_favicon']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/ico'];
        $fileType = $_FILES['site_favicon']['type'];

        // ico dosyaları için özel kontrol
        $extension = strtolower(pathinfo($_FILES['site_favicon']['name'], PATHINFO_EXTENSION));
        if ($extension === 'ico' || in_array($fileType, $allowedTypes) || $fileType === 'image/png') {
            $fileName = 'favicon_' . time() . '.' . $extension;
            $targetPath = $uploadDir . $fileName;

            // Eski favicon'u sil
            $oldFavicon = Settings::get('site_favicon', '');
            if (!empty($oldFavicon)) {
                $oldPath = dirname(__DIR__) . '/' . ltrim($oldFavicon, '/');
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            if (move_uploaded_file($_FILES['site_favicon']['tmp_name'], $targetPath)) {
                Settings::set('site_favicon', 'uploads/logos/' . $fileName, 'appearance');
                $message = 'Favicon başarıyla yüklendi!';
            } else {
                $message = 'Favicon yüklenirken bir hata oluştu!';
                $messageType = 'danger';
            }
        } else {
            $message = 'Geçersiz dosya türü! ICO veya PNG yükleyebilirsiniz.';
            $messageType = 'danger';
        }
    }

    // Logo silme
    if (isset($_POST['delete_logo'])) {
        $oldLogo = Settings::get('site_logo', '');
        if (!empty($oldLogo)) {
            $oldPath = dirname(__DIR__) . '/' . ltrim($oldLogo, '/');
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
            Settings::delete('site_logo');
            $message = 'Logo silindi!';
        }
    }

    // Favicon silme
    if (isset($_POST['delete_favicon'])) {
        $oldFavicon = Settings::get('site_favicon', '');
        if (!empty($oldFavicon)) {
            $oldPath = dirname(__DIR__) . '/' . ltrim($oldFavicon, '/');
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
            Settings::delete('site_favicon');
            $message = 'Favicon silindi!';
        }
    }

    // Diğer ayarları kaydet
    if (isset($_POST['settings']) && is_array($_POST['settings'])) {
        foreach ($_POST['settings'] as $key => $value) {
            $group = match (true) {
                str_starts_with($key, 'site_') => 'appearance',
                str_starts_with($key, 'company_') => 'company',
                str_starts_with($key, 'tax_') || str_starts_with($key, 'invoice_') || str_starts_with($key, 'order_') => 'billing',
                str_starts_with($key, 'auto_') || str_starts_with($key, 'registration_') || str_starts_with($key, 'maintenance_') => 'system',
                default => 'general'
            };
            Settings::set($key, $value, $group);
        }
        if (empty($message)) {
            $message = 'Ayarlar başarıyla kaydedildi!';
        }
    }

    Settings::clearCache();
}

// Ayarları çek
$settings = Settings::all();

include 'includes/header.php';
?>

<style>
    /* Settings Page Styles */
    .settings-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 25px;
        background: white;
        padding: 8px;
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .settings-tab {
        flex: 1;
        padding: 14px 20px;
        background: transparent;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        color: var(--gray);
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .settings-tab:hover {
        background: #f1f5f9;
        color: var(--dark);
    }

    .settings-tab.active {
        background: var(--primary);
        color: white;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
    }

    .settings-tab .icon {
        font-size: 18px;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .settings-section {
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        margin-bottom: 25px;
        overflow: hidden;
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 25px;
        border-bottom: 1px solid var(--border);
        background: linear-gradient(135deg, #f8fafc 0%, #fff 100%);
    }

    .section-header>div:last-child {
        margin-left: auto;
    }

    .section-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: white;
    }

    .section-info h3 {
        font-size: 18px;
        color: var(--dark);
        margin-bottom: 4px;
    }

    .section-info p {
        font-size: 13px;
        color: var(--gray);
    }

    .section-body {
        padding: 25px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
    }

    .form-group {
        margin-bottom: 0;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-group label {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 10px;
        font-weight: 600;
        color: var(--dark);
        font-size: 14px;
    }

    .form-group label .label-icon {
        font-size: 16px;
    }

    .form-control {
        width: 100%;
        padding: 14px 18px;
        background: #f8fafc;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        color: var(--dark);
        font-size: 14px;
        font-family: inherit;
        transition: all 0.3s;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
    }

    .form-control::placeholder {
        color: #94a3b8;
    }

    select.form-control {
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 15px center;
        background-size: 18px;
        padding-right: 45px;
    }

    textarea.form-control {
        resize: vertical;
        min-height: 100px;
    }

    .form-hint {
        display: block;
        margin-top: 8px;
        color: #64748b;
        font-size: 12px;
    }

    /* Logo Upload */
    .upload-area {
        display: flex;
        align-items: center;
        gap: 25px;
        padding: 25px;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border: 2px dashed #e2e8f0;
        border-radius: 16px;
        transition: all 0.3s;
    }

    .upload-area:hover {
        border-color: var(--primary);
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(139, 92, 246, 0.05) 100%);
    }

    .upload-preview {
        width: 140px;
        height: 70px;
        background: white;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        flex-shrink: 0;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    }

    .upload-preview.favicon {
        width: 60px;
        height: 60px;
    }

    .upload-preview img {
        max-width: 90%;
        max-height: 90%;
        object-fit: contain;
    }

    .upload-preview .no-image {
        color: #94a3b8;
        text-align: center;
        font-size: 12px;
    }

    .upload-preview .no-image span {
        display: block;
        font-size: 28px;
        margin-bottom: 5px;
    }

    .upload-content {
        flex: 1;
    }

    .upload-content h4 {
        font-size: 16px;
        color: var(--dark);
        margin-bottom: 6px;
    }

    .upload-content p {
        color: #64748b;
        font-size: 13px;
        margin-bottom: 15px;
    }

    .upload-actions {
        display: flex;
        gap: 10px;
    }

    .file-btn {
        position: relative;
        overflow: hidden;
        display: inline-block;
    }

    .file-btn input[type="file"] {
        position: absolute;
        left: 0;
        top: 0;
        opacity: 0;
        cursor: pointer;
        width: 100%;
        height: 100%;
    }

    /* Color Input */
    .color-input-wrapper {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .color-preview {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        border: 3px solid white;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
        cursor: pointer;
        overflow: hidden;
    }

    .color-preview input[type="color"] {
        width: 70px;
        height: 70px;
        margin: -10px;
        border: none;
        cursor: pointer;
    }

    .color-value {
        flex: 1;
        padding: 14px 18px;
        background: #f8fafc;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-family: 'SF Mono', 'Fira Code', monospace;
        font-size: 14px;
        color: var(--dark);
    }

    /* Switch Toggle */
    .switch-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 20px;
        background: #f8fafc;
        border-radius: 12px;
        margin-bottom: 15px;
    }

    .switch-row:last-child {
        margin-bottom: 0;
    }

    .switch-info h4 {
        font-size: 14px;
        color: var(--dark);
        margin-bottom: 4px;
    }

    .switch-info p {
        font-size: 12px;
        color: #64748b;
    }

    .switch-toggle {
        position: relative;
        width: 56px;
        height: 30px;
    }

    .switch-toggle input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .switch-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #cbd5e1;
        transition: .3s;
        border-radius: 30px;
    }

    .switch-slider:before {
        position: absolute;
        content: "";
        height: 24px;
        width: 24px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }

    .switch-toggle input:checked+.switch-slider {
        background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
    }

    .switch-toggle input:checked+.switch-slider:before {
        transform: translateX(26px);
    }

    /* Alerts */
    .alert {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 18px 22px;
        border-radius: 14px;
        margin-bottom: 25px;
        font-size: 14px;
        font-weight: 500;
    }

    .alert-success {
        background: linear-gradient(135deg, #d1fae5 0%, #ecfdf5 100%);
        border: 1px solid #a7f3d0;
        color: #065f46;
    }

    .alert-danger {
        background: linear-gradient(135deg, #fee2e2 0%, #fef2f2 100%);
        border: 1px solid #fecaca;
        color: #991b1b;
    }

    .alert .alert-icon {
        font-size: 22px;
    }

    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 22px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        font-family: inherit;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
    }

    .btn-outline {
        background: white;
        border: 2px solid #e2e8f0;
        color: var(--dark);
    }

    .btn-outline:hover {
        border-color: var(--primary);
        color: var(--primary);
    }

    .btn-danger {
        background: #fee2e2;
        color: #dc2626;
    }

    .btn-danger:hover {
        background: #dc2626;
        color: white;
    }

    .btn-sm {
        padding: 8px 16px;
        font-size: 13px;
    }

    .btn-lg {
        padding: 16px 32px;
        font-size: 16px;
    }

    /* Save Footer */
    .save-footer {
        position: sticky;
        bottom: 0;
        background: white;
        border-radius: 16px;
        box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
        padding: 20px 25px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 30px;
    }

    .save-footer .info {
        display: flex;
        align-items: center;
        gap: 12px;
        color: #64748b;
        font-size: 14px;
    }

    .save-footer .info span {
        font-size: 20px;
    }

    @media (max-width: 768px) {
        .settings-tabs {
            flex-wrap: wrap;
        }

        .settings-tab {
            flex: 1 1 45%;
        }

        .upload-area {
            flex-direction: column;
            text-align: center;
        }

        .upload-actions {
            justify-content: center;
        }
    }
</style>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <span class="alert-icon"><?= $messageType === 'success' ? '✅' : '❌' ?></span>
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Tabs -->
<div class="settings-tabs">
    <button class="settings-tab active" onclick="showSettingsTab('appearance')">
        <span class="icon">🎨</span> Görünüm
    </button>
    <button class="settings-tab" onclick="showSettingsTab('company')">
        <span class="icon">🏢</span> Şirket
    </button>
    <button class="settings-tab" onclick="showSettingsTab('billing')">
        <span class="icon">💰</span> Faturalama
    </button>
    <button class="settings-tab" onclick="showSettingsTab('smtp')">
        <span class="icon">📧</span> E-Posta
    </button>
    <button class="settings-tab" onclick="showSettingsTab('affiliate')">
        <span class="icon">🤝</span> Ortaklık
    </button>
    <button class="settings-tab" onclick="showSettingsTab('cron')">
        <span class="icon">⏰</span> Cron Jobs
    </button>
    <button class="settings-tab" onclick="showSettingsTab('system')">
        <span class="icon">⚙️</span> Sistem
    </button>
</div>

<form method="POST" enctype="multipart/form-data">

    <!-- Görünüm Ayarları -->
    <div class="tab-content active" id="tab-appearance">
        <div class="settings-section">
            <div class="section-header">
                <div class="section-icon">🎨</div>
                <div class="section-info">
                    <h3>Görünüm Ayarları</h3>
                    <p>Logo, favicon ve tema ayarlarını yapılandırın</p>
                </div>
            </div>
            <div class="section-body">
                <!-- Site Adı -->
                <div class="form-grid" style="margin-bottom: 25px;">
                    <div class="form-group full-width">
                        <label><span class="label-icon">📝</span> Site Adı</label>
                        <input type="text" name="settings[site_name]" class="form-control"
                            value="<?= htmlspecialchars($settings['site_name'] ?? SITE_NAME) ?>"
                            placeholder="Örn: Verimek Proje">
                        <span class="form-hint">Bu isim site başlığı ve footer'da görünecektir.</span>
                    </div>
                </div>

                <!-- Logo Yükleme -->
                <div class="upload-area" style="margin-bottom: 20px;">
                    <div class="upload-preview">
                        <?php $currentLogo = Settings::getLogo();
                        if (!empty($currentLogo)): ?>
                            <img src="/<?= htmlspecialchars($currentLogo) ?>" alt="Logo">
                        <?php else: ?>
                            <div class="no-image"><span>🖼️</span>Logo Yok</div>
                        <?php endif; ?>
                    </div>
                    <div class="upload-content">
                        <h4>Site Logosu</h4>
                        <p>Önerilen: 200x50px • PNG, JPG, SVG veya WebP</p>
                        <div class="upload-actions">
                            <div class="file-btn">
                                <button type="button" class="btn btn-primary btn-sm">📤 Logo Yükle</button>
                                <input type="file" name="site_logo" accept="image/*">
                            </div>
                            <?php if (!empty($currentLogo)): ?>
                                <button type="submit" name="delete_logo" value="1" class="btn btn-danger btn-sm"
                                    onclick="return confirm('Logoyu silmek istediğinize emin misiniz?')">🗑️ Sil</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Favicon Yükleme -->
                <div class="upload-area" style="margin-bottom: 25px;">
                    <div class="upload-preview favicon">
                        <?php $currentFavicon = Settings::getFavicon();
                        if (!empty($currentFavicon)): ?>
                            <img src="/<?= htmlspecialchars($currentFavicon) ?>" alt="Favicon">
                        <?php else: ?>
                            <div class="no-image"><span>⭐</span></div>
                        <?php endif; ?>
                    </div>
                    <div class="upload-content">
                        <h4>Favicon</h4>
                        <p>Önerilen: 32x32 veya 64x64px • ICO veya PNG</p>
                        <div class="upload-actions">
                            <div class="file-btn">
                                <button type="button" class="btn btn-primary btn-sm">📤 Favicon Yükle</button>
                                <input type="file" name="site_favicon" accept=".ico,.png,image/png,image/x-icon">
                            </div>
                            <?php if (!empty($currentFavicon)): ?>
                                <button type="submit" name="delete_favicon" value="1" class="btn btn-danger btn-sm"
                                    onclick="return confirm('Favicon silmek istediğinize emin misiniz?')">🗑️ Sil</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tema Seçimi -->
                <div class="form-grid" style="margin-bottom: 25px;">
                    <div class="form-group">
                        <label><span class="label-icon">🌙</span> Varsayılan Tema</label>
                        <select name="settings[default_theme]" class="form-control">
                            <option value="dark" <?= ($settings['default_theme'] ?? 'dark') === 'dark' ? 'selected' : '' ?>>🌙 Koyu Tema</option>
                            <option value="light" <?= ($settings['default_theme'] ?? 'dark') === 'light' ? 'selected' : '' ?>>☀️ Açık Tema</option>
                            <option value="auto" <?= ($settings['default_theme'] ?? 'dark') === 'auto' ? 'selected' : '' ?>>🔄 Otomatik</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">🔄</span> Tema Değiştirme</label>
                        <select name="settings[allow_theme_switch]" class="form-control">
                            <option value="1" <?= ($settings['allow_theme_switch'] ?? '1') === '1' ? 'selected' : '' ?>>✅
                                Kullanıcılar değiştirebilir</option>
                            <option value="0" <?= ($settings['allow_theme_switch'] ?? '1') === '0' ? 'selected' : '' ?>>🚫
                                Sadece varsayılan</option>
                        </select>
                    </div>
                </div>

                <!-- Site Renkleri -->
                <div class="form-grid">
                    <div class="form-group">
                        <label><span class="label-icon">🎨</span> Ana Renk (Primary)</label>
                        <div class="color-input-wrapper">
                            <div class="color-preview">
                                <input type="color" name="settings[site_primary_color]"
                                    value="<?= htmlspecialchars($settings['site_primary_color'] ?? '#6366f1') ?>"
                                    onchange="this.parentElement.nextElementSibling.value = this.value">
                            </div>
                            <input type="text" class="color-value"
                                value="<?= htmlspecialchars($settings['site_primary_color'] ?? '#6366f1') ?>" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">🎨</span> İkincil Renk</label>
                        <div class="color-input-wrapper">
                            <div class="color-preview">
                                <input type="color" name="settings[site_secondary_color]"
                                    value="<?= htmlspecialchars($settings['site_secondary_color'] ?? '#0ea5e9') ?>"
                                    onchange="this.parentElement.nextElementSibling.value = this.value">
                            </div>
                            <input type="text" class="color-value"
                                value="<?= htmlspecialchars($settings['site_secondary_color'] ?? '#0ea5e9') ?>"
                                readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Şirket Bilgileri -->
    <div class="tab-content" id="tab-company">
        <div class="settings-section">
            <div class="section-header">
                <div class="section-icon">🏢</div>
                <div class="section-info">
                    <h3>Şirket Bilgileri</h3>
                    <p>Fatura ve iletişim bilgilerini düzenleyin</p>
                </div>
            </div>
            <div class="section-body">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label><span class="label-icon">🏛️</span> Şirket Adı</label>
                        <input type="text" name="settings[company_name]" class="form-control"
                            value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>"
                            placeholder="Örn: Verimek Proje Ltd. Şti.">
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">📧</span> E-posta Adresi</label>
                        <input type="email" name="settings[company_email]" class="form-control"
                            value="<?= htmlspecialchars($settings['company_email'] ?? '') ?>"
                            placeholder="info@verimekproje.com">
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">📞</span> Telefon</label>
                        <input type="tel" name="settings[company_phone]" class="form-control"
                            value="<?= htmlspecialchars($settings['company_phone'] ?? '') ?>"
                            placeholder="+90 212 123 45 67">
                    </div>
                    <div class="form-group full-width">
                        <label><span class="label-icon">📍</span> Adres</label>
                        <textarea name="settings[company_address]" class="form-control" rows="3"
                            placeholder="Şirket adresi..."><?= htmlspecialchars($settings['company_address'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label><span class="label-icon">⚖️</span> Yasal Unvan</label>
                        <input type="text" name="settings[company_legal_name]" class="form-control"
                            value="<?= htmlspecialchars($settings['company_legal_name'] ?? '') ?>"
                            placeholder="Ticaret sicilindeki tam unvan">
                        <small>Sözleşme metinlerinde satıcı olarak bu unvan yazılır. Boşsa şirket adı kullanılır.</small>
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">🏦</span> Vergi Dairesi</label>
                        <input type="text" name="settings[company_tax_office]" class="form-control"
                            value="<?= htmlspecialchars($settings['company_tax_office'] ?? '') ?>"
                            placeholder="Örn: Nilüfer">
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">🔢</span> Vergi Numarası</label>
                        <input type="text" name="settings[company_tax_number]" class="form-control"
                            value="<?= htmlspecialchars($settings['company_tax_number'] ?? '') ?>"
                            placeholder="10 haneli">
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">🆔</span> MERSİS Numarası</label>
                        <input type="text" name="settings[company_mersis]" class="form-control"
                            value="<?= htmlspecialchars($settings['company_mersis'] ?? '') ?>"
                            placeholder="16 haneli">
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">📋</span> Ticaret Sicil No</label>
                        <input type="text" name="settings[company_trade_registry]" class="form-control"
                            value="<?= htmlspecialchars($settings['company_trade_registry'] ?? '') ?>"
                            placeholder="Örn: Bursa / 12345">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Faturalama Ayarları -->
    <div class="tab-content" id="tab-billing">
        <div class="settings-section">
            <div class="section-header">
                <div class="section-icon">💰</div>
                <div class="section-info">
                    <h3>Faturalama Ayarları</h3>
                    <p>Vergi, para birimi ve fatura ayarları</p>
                </div>
            </div>
            <div class="section-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label><span class="label-icon">💵</span> Para Birimi</label>
                        <select name="settings[currency]" class="form-control">
                            <option value="TRY" <?= ($settings['currency'] ?? 'TRY') === 'TRY' ? 'selected' : '' ?>>₺ Türk
                                Lirası (TRY)</option>
                            <option value="USD" <?= ($settings['currency'] ?? 'TRY') === 'USD' ? 'selected' : '' ?>>$
                                Amerikan Doları (USD)</option>
                            <option value="EUR" <?= ($settings['currency'] ?? 'TRY') === 'EUR' ? 'selected' : '' ?>>€ Euro
                                (EUR)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">📊</span> Vergi Oranı (%)</label>
                        <input type="number" name="settings[tax_rate]" class="form-control"
                            value="<?= htmlspecialchars($settings['tax_rate'] ?? '20') ?>" step="0.01" min="0"
                            max="100">
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">📄</span> Fatura Öneki</label>
                        <input type="text" name="settings[invoice_prefix]" class="form-control"
                            value="<?= htmlspecialchars($settings['invoice_prefix'] ?? 'INV-') ?>">
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">🛒</span> Sipariş Öneki</label>
                        <input type="text" name="settings[order_prefix]" class="form-control"
                            value="<?= htmlspecialchars($settings['order_prefix'] ?? 'ORD-') ?>">
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">🎫</span> Ticket Öneki</label>
                        <input type="text" name="settings[ticket_prefix]" class="form-control"
                            value="<?= htmlspecialchars($settings['ticket_prefix'] ?? 'TKT-') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="settings-section">
            <div class="section-header">
                <div class="section-icon">🤖</div>
                <div class="section-info">
                    <h3>Otomasyon Ayarları</h3>
                    <p>Otomatik askıya alma ve sonlandırma</p>
                </div>
            </div>
            <div class="section-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label><span class="label-icon">⏸️</span> Otomatik Askıya Alma</label>
                        <input type="number" name="settings[auto_suspend_days]" class="form-control"
                            value="<?= htmlspecialchars($settings['auto_suspend_days'] ?? '3') ?>" min="0">
                        <span class="form-hint">Vadeden kaç gün sonra askıya alınsın</span>
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">🚫</span> Otomatik Sonlandırma</label>
                        <input type="number" name="settings[auto_terminate_days]" class="form-control"
                            value="<?= htmlspecialchars($settings['auto_terminate_days'] ?? '14') ?>" min="0">
                        <span class="form-hint">Askıdan kaç gün sonra sonlandırılsın</span>
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">📬</span> Fatura Hatırlatma</label>
                        <input type="number" name="settings[invoice_reminder_days]" class="form-control"
                            value="<?= htmlspecialchars($settings['invoice_reminder_days'] ?? '7') ?>" min="0">
                        <span class="form-hint">Vadeden kaç gün önce hatırlatma gönder</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- E-Posta / SMTP Ayarları -->
    <div class="tab-content" id="tab-smtp">
        <div class="settings-section">
            <div class="section-header">
                <div class="section-icon">📧</div>
                <div class="section-info">
                    <h3>SMTP Ayarları</h3>
                    <p>E-posta gönderimi için SMTP sunucu yapılandırması</p>
                </div>
            </div>
            <div class="section-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label><span class="label-icon">🖥️</span> SMTP Sunucu</label>
                        <input type="text" name="settings[smtp_host]" class="form-control"
                            value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>"
                            placeholder="smtp.example.com">
                        <span class="form-hint">Örn: smtp.gmail.com, smtp.yandex.com</span>
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">🔌</span> Port</label>
                        <input type="number" name="settings[smtp_port]" class="form-control"
                            value="<?= htmlspecialchars($settings['smtp_port'] ?? '587') ?>" placeholder="587">
                        <span class="form-hint">TLS: 587, SSL: 465</span>
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">👤</span> Kullanıcı Adı</label>
                        <input type="text" name="settings[smtp_username]" class="form-control"
                            value="<?= htmlspecialchars($settings['smtp_username'] ?? '') ?>"
                            placeholder="mail@example.com">
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">🔑</span> Şifre</label>
                        <input type="password" name="settings[smtp_password]" class="form-control"
                            value="<?= htmlspecialchars($settings['smtp_password'] ?? '') ?>" placeholder="••••••••">
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">🔒</span> Şifreleme</label>
                        <select name="settings[smtp_encryption]" class="form-control">
                            <option value="tls" <?= ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>
                                TLS (Önerilen)</option>
                            <option value="ssl" <?= ($settings['smtp_encryption'] ?? 'tls') === 'ssl' ? 'selected' : '' ?>>
                                SSL</option>
                            <option value="none" <?= ($settings['smtp_encryption'] ?? 'tls') === 'none' ? 'selected' : '' ?>>Şifreleme Yok</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">✅</span> Kimlik Doğrulama</label>
                        <select name="settings[smtp_auth]" class="form-control">
                            <option value="1" <?= ($settings['smtp_auth'] ?? '1') === '1' ? 'selected' : '' ?>>Aktif
                            </option>
                            <option value="0" <?= ($settings['smtp_auth'] ?? '1') === '0' ? 'selected' : '' ?>>Pasif
                            </option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="settings-section">
            <div class="section-header">
                <div class="section-icon">✉️</div>
                <div class="section-info">
                    <h3>Gönderici Bilgileri</h3>
                    <p>E-postalar bu bilgilerle gönderilecek</p>
                </div>
            </div>
            <div class="section-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label><span class="label-icon">📧</span> Gönderici E-posta</label>
                        <input type="email" name="settings[smtp_from_email]" class="form-control"
                            value="<?= htmlspecialchars($settings['smtp_from_email'] ?? '') ?>"
                            placeholder="noreply@example.com">
                        <span class="form-hint">E-postaların gönderileceği adres</span>
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">👤</span> Gönderici Adı</label>
                        <input type="text" name="settings[smtp_from_name]" class="form-control"
                            value="<?= htmlspecialchars($settings['smtp_from_name'] ?? '') ?>" placeholder="Site Adı">
                        <span class="form-hint">E-postalarda görünecek gönderici adı</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="settings-section">
            <div class="section-header">
                <div class="section-icon">🧪</div>
                <div class="section-info">
                    <h3>Test E-postası</h3>
                    <p>SMTP ayarlarınızı test edin</p>
                </div>
            </div>
            <div class="section-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label><span class="label-icon">📬</span> Test E-posta Adresi</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="email" id="test_email" class="form-control" placeholder="test@example.com"
                                style="flex: 1;">
                            <button type="button" class="btn btn-primary" onclick="sendTestEmail()">
                                📤 Test Gönder
                            </button>
                        </div>
                        <span class="form-hint">Önce ayarları kaydedin, ardından test edin</span>
                    </div>
                </div>
                <div id="test_result" style="margin-top: 15px; display: none;"></div>
            </div>
        </div>

        <div class="settings-section">
            <div class="section-header">
                <div class="section-icon">📋</div>
                <div class="section-info">
                    <h3>E-posta Şablonları</h3>
                    <p>Tüm sistem e-posta şablonlarını yönetin</p>
                </div>
            </div>
            <div class="section-body">
                <p style="color: #64748b; margin-bottom: 15px;">
                    Fatura, sipariş, destek talebi ve diğer tüm e-posta şablonlarını düzenleyin.
                </p>
                <a href="email-templates.php" class="btn btn-primary">
                    📧 E-posta Şablonlarını Yönet →
                </a>
            </div>
        </div>
    </div>

    <!-- Satış Ortaklığı Ayarları -->
    <div class="tab-content" id="tab-affiliate">
        <div class="settings-section">
            <div class="section-header">
                <div class="section-icon">🤝</div>
                <div class="section-info">
                    <h3>Satış Ortaklığı Ayarları</h3>
                    <p>Affiliate sistemi yapılandırması</p>
                </div>
            </div>
            <div class="section-body">
                <div class="switch-row">
                    <div class="switch-info">
                        <h4>✅ Satış Ortaklığı Sistemi</h4>
                        <p>Satış ortaklığı sistemini etkinleştir/devre dışı bırak</p>
                    </div>
                    <label class="switch-toggle">
                        <input type="checkbox" name="settings[affiliate_enabled]" value="1"
                            <?= ($settings['affiliate_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="switch-row">
                    <div class="switch-info">
                        <h4>⚡ Otomatik Onay</h4>
                        <p>Başvuruları onay beklemeden otomatik aktifleştir</p>
                    </div>
                    <label class="switch-toggle">
                        <input type="checkbox" name="settings[affiliate_auto_approve]" value="1"
                            <?= ($settings['affiliate_auto_approve'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label><span class="label-icon">💰</span> Varsayılan Komisyon Oranı (%)</label>
                        <input type="number" name="settings[affiliate_default_commission]" class="form-control"
                            value="<?= htmlspecialchars($settings['affiliate_default_commission'] ?? '10') ?>" min="0"
                            max="100" step="0.01">
                        <span class="form-hint">Yeni ortaklara verilecek varsayılan komisyon yüzdesi</span>
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">📅</span> Cookie Süresi (Gün)</label>
                        <input type="number" name="settings[affiliate_cookie_days]" class="form-control"
                            value="<?= htmlspecialchars($settings['affiliate_cookie_days'] ?? '30') ?>" min="1"
                            max="365">
                        <span class="form-hint">Referans takip cookie'sinin geçerli olacağı gün sayısı</span>
                    </div>
                    <div class="form-group">
                        <label><span class="label-icon">💵</span> Minimum Çekim Tutarı (TL)</label>
                        <input type="number" name="settings[affiliate_min_withdrawal]" class="form-control"
                            value="<?= htmlspecialchars($settings['affiliate_min_withdrawal'] ?? '100') ?>" min="0"
                            step="0.01">
                        <span class="form-hint">Çekim talebi için gereken minimum bakiye</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cron Jobs -->
    <div class="tab-content" id="tab-cron">
        <div class="settings-section">
            <div class="section-header">
                <div class="section-icon">⏰</div>
                <div class="section-info">
                    <h3>Cron Job Yönetimi</h3>
                    <p>Otomatik görevleri yönetin ve zamanlayın</p>
                </div>
            </div>
            <div class="section-body">
                <?php
                // Cron tasks tablosunu kontrol et ve yoksa oluştur
                $tableExists = false;
                $hasJobs = false;
                try {
                    Database::query("SELECT 1 FROM cron_tasks LIMIT 1");
                    $tableExists = true;
                    $jobCount = Database::fetchColumn("SELECT COUNT(*) FROM cron_tasks");
                    $hasJobs = $jobCount > 0;
                } catch (Exception $e) {
                    // Tablo yoksa oluştur
                    Database::query("
                        CREATE TABLE IF NOT EXISTS `cron_tasks` (
                            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                            `name` VARCHAR(100) NOT NULL UNIQUE,
                            `description` TEXT,
                            `command` VARCHAR(255) NOT NULL,
                            `schedule` VARCHAR(100) NOT NULL COMMENT 'Cron expression',
                            `is_active` TINYINT(1) DEFAULT 1,
                            `last_run` DATETIME,
                            `next_run` DATETIME,
                            `last_status` ENUM('success', 'failed', 'running') DEFAULT 'success',
                            `last_output` TEXT,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            INDEX `idx_active` (`is_active`),
                            INDEX `idx_next_run` (`next_run`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    $tableExists = true;
                }

                // Varsayılan cron job'ları ekle (tablo boşsa)
                if ($tableExists && !$hasJobs) {
                    $defaultJobs = [
                        [
                            'name' => 'Otomatik Fatura Yenileme',
                            'description' => 'Süresi dolan hizmetler için otomatik olarak yeni fatura oluşturur',
                            'command' => 'generate_invoices',
                            'schedule' => '0 10 * * *',
                            'is_active' => 1
                        ],
                        [
                            'name' => 'Fatura Hatırlatma',
                            'description' => 'Yaklaşan faturalar için müşterilere hatırlatma e-postası gönderir',
                            'command' => 'send_invoice_reminders',
                            'schedule' => '0 10 * * *',
                            'is_active' => 1
                        ],
                        [
                            'name' => 'Geciken Fatura Bildirimi',
                            'description' => 'Geciken faturalar için müşterilere bildirim e-postası gönderir',
                            'command' => 'overdue_invoice_notices',
                            'schedule' => '0 10 * * *',
                            'is_active' => 1
                        ],
                        [
                            'name' => 'Domain Yenileme Bildirimi',
                            'description' => 'Yaklaşan domain yenilemeleri için müşterilere bildirim gönderir (90, 60, 30, 14, 7, 1 gün önce)',
                            'command' => 'domain_renewal_notices',
                            'schedule' => '0 10 * * *',
                            'is_active' => 1
                        ],
                        [
                            'name' => 'Domain Süresi Dolan İşlemleri',
                            'description' => 'Süresi dolan domainleri işler ve otomatik yenileme için fatura oluşturur',
                            'command' => 'domain_expiry',
                            'schedule' => '0 10 * * *',
                            'is_active' => 1
                        ],
                        [
                            'name' => 'Hizmet Askıya Alma',
                            'description' => 'Süresi geçen aktif hizmetleri otomatik olarak askıya alır',
                            'command' => 'suspend_services',
                            'schedule' => '0 10 * * *',
                            'is_active' => 1
                        ],
                        [
                            'name' => 'Hizmet Sonlandırma',
                            'description' => 'Uzun süre askıda kalan hizmetleri sonlandırır',
                            'command' => 'terminate_services',
                            'schedule' => '0 10 * * *',
                            'is_active' => 1
                        ],
                        [
                            'name' => 'Destek Talebi Yükseltme',
                            'description' => 'Yanıt bekleyen destek taleplerinin önceliğini otomatik yükseltir',
                            'command' => 'ticket_escalations',
                            'schedule' => '0 10 * * *',
                            'is_active' => 1
                        ],
                        [
                            'name' => 'Affiliate Komisyon Hesaplama',
                            'description' => 'Ödenmiş faturalar için affiliate komisyonlarını hesaplar',
                            'command' => 'affiliate_commissions',
                            'schedule' => '0 10 * * *',
                            'is_active' => 1
                        ],
                        [
                            'name' => 'Eski Logları Temizle',
                            'description' => '90 günden eski sistem loglarını temizler',
                            'command' => 'cleanup_old_logs',
                            'schedule' => '0 10 * * *',
                            'is_active' => 1
                        ],
                        [
                            'name' => 'Veritabanı Yedekleme',
                            'description' => 'Veritabanının otomatik yedeğini oluşturur (günlük)',
                            'command' => 'database_backup',
                            'schedule' => '0 10 * * *',
                            'is_active' => 0
                        ]
                    ];

                    foreach ($defaultJobs as $job) {
                        try {
                            Database::query("
                                INSERT IGNORE INTO cron_tasks (name, description, command, schedule, is_active, next_run)
                                VALUES (?, ?, ?, ?, ?, NOW())
                            ", [$job['name'], $job['description'], $job['command'], $job['schedule'], $job['is_active']]);
                        } catch (Exception $e) {
                            // Zaten varsa atla
                        }
                    }
                }

                // Eğer tablo varsa ama içi boşsa varsayılan cron job'ları otomatik ekle
                if ($tableExists && !$hasJobs && !isset($_POST['add_default_crons'])) {
                    foreach ($defaultJobs as $job) {
                        try {
                            Database::query("
                                INSERT IGNORE INTO cron_tasks (name, description, command, schedule, is_active, next_run)
                                VALUES (?, ?, ?, ?, ?, NOW())
                            ", [$job['name'], $job['description'], $job['command'], $job['schedule'], $job['is_active']]);
                        } catch (Exception $e) {
                            // Hata durumunda devam et
                        }
                    }
                }

                // Varsayılan cron job'ları ekle butonu
                if (isset($_POST['add_default_crons'])) {
                    foreach ($defaultJobs as $job) {
                        try {
                            Database::query("
                                INSERT IGNORE INTO cron_tasks (name, description, command, schedule, is_active, next_run)
                                VALUES (?, ?, ?, ?, ?, NOW())
                            ", [$job['name'], $job['description'], $job['command'], $job['schedule'], $job['is_active']]);
                        } catch (Exception $e) {
                            // Hata durumunda devam et
                        }
                    }
                    $message = 'Varsayılan cron job\'lar başarıyla eklendi!';
                    $messageType = 'success';
                }

                // Cron job işlemleri
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    if (isset($_POST['add_cron'])) {
                        $name = trim($_POST['cron_name'] ?? '');
                        $description = trim($_POST['cron_description'] ?? '');
                        $command = trim($_POST['cron_command'] ?? '');
                        $schedule = trim($_POST['cron_schedule'] ?? '0 * * * *');
                        $isActive = isset($_POST['cron_is_active']) ? 1 : 0;

                        if (!empty($name) && !empty($command)) {
                            try {
                                Database::query("
                                    INSERT INTO cron_tasks (name, description, command, schedule, is_active, next_run)
                                    VALUES (?, ?, ?, ?, ?, NOW())
                                ", [$name, $description, $command, $schedule, $isActive]);
                                $message = 'Cron job başarıyla eklendi!';
                                $messageType = 'success';
                            } catch (Exception $e) {
                                $message = 'Hata: ' . $e->getMessage();
                                $messageType = 'danger';
                            }
                        }
                    } elseif (isset($_POST['update_cron'])) {
                        $id = (int) $_POST['cron_id'];
                        $name = trim($_POST['cron_name'] ?? '');
                        $description = trim($_POST['cron_description'] ?? '');
                        $command = trim($_POST['cron_command'] ?? '');
                        $schedule = trim($_POST['cron_schedule'] ?? '0 * * * *');
                        $isActive = isset($_POST['cron_is_active']) ? 1 : 0;

                        if (!empty($name) && !empty($command)) {
                            try {
                                Database::query("
                                    UPDATE cron_tasks 
                                    SET name = ?, description = ?, command = ?, schedule = ?, is_active = ?
                                    WHERE id = ?
                                ", [$name, $description, $command, $schedule, $isActive, $id]);
                                $message = 'Cron job başarıyla güncellendi!';
                                $messageType = 'success';
                            } catch (Exception $e) {
                                $message = 'Hata: ' . $e->getMessage();
                                $messageType = 'danger';
                            }
                        }
                    } elseif (isset($_POST['delete_cron'])) {
                        $id = (int) $_POST['cron_id'];
                        try {
                            Database::query("DELETE FROM cron_tasks WHERE id = ?", [$id]);
                            $message = 'Cron job silindi!';
                            $messageType = 'success';
                        } catch (Exception $e) {
                            $message = 'Hata: ' . $e->getMessage();
                            $messageType = 'danger';
                        }
                    } elseif (isset($_POST['toggle_cron'])) {
                        $id = (int) $_POST['cron_id'];
                        try {
                            $current = Database::fetch("SELECT is_active FROM cron_tasks WHERE id = ?", [$id]);
                            $newStatus = $current['is_active'] ? 0 : 1;
                            Database::query("UPDATE cron_tasks SET is_active = ? WHERE id = ?", [$newStatus, $id]);
                            $message = 'Cron job durumu güncellendi!';
                            $messageType = 'success';
                        } catch (Exception $e) {
                            $message = 'Hata: ' . $e->getMessage();
                            $messageType = 'danger';
                        }
                    }
                }

                // Cron job'ları listele
                $cronJobs = Database::fetchAll("SELECT * FROM cron_tasks ORDER BY name");
                $editCronId = isset($_GET['edit_cron']) ? (int) $_GET['edit_cron'] : 0;
                $editCron = $editCronId > 0 ? Database::fetch("SELECT * FROM cron_tasks WHERE id = ?", [$editCronId]) : null;

                // Cron job URL'i
                $cronUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') .
                    '://' . $_SERVER['HTTP_HOST'] . '/cron.php';
                ?>

                <div
                    style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); padding: 20px; border-radius: 12px; margin-bottom: 25px; border: 2px solid #0ea5e9;">
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                        <span style="font-size: 32px;">🔗</span>
                        <div style="flex: 1;">
                            <h4 style="margin: 0 0 5px 0; color: var(--dark);">Cron Job URL</h4>
                            <p style="margin: 0; color: #64748b; font-size: 13px;">Bu URL'i sunucunuzda cron job olarak
                                ekleyin (örn: her saat başı)</p>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="text" id="cronUrl" value="<?= htmlspecialchars($cronUrl) ?>" readonly
                            style="flex: 1; padding: 12px; background: white; border: 2px solid #0ea5e9; border-radius: 8px; font-family: monospace; font-size: 13px;">
                        <button type="button" onclick="copyCronUrl()" class="btn btn-primary btn-sm">📋 Kopyala</button>
                    </div>
                    <p style="margin: 10px 0 0 0; color: #64748b; font-size: 12px;">
                        <strong>Örnek cron komutu:</strong> <code
                            style="background: white; padding: 4px 8px; border-radius: 4px;">0 * * * * curl -s <?= htmlspecialchars($cronUrl) ?> > /dev/null 2>&1</code>
                    </p>
                </div>

                <!-- Cron Job Ekleme/Düzenleme Formu -->
                <div class="settings-section" style="margin-bottom: 25px;">
                    <div class="section-header">
                        <div class="section-icon"><?= $editCron ? '✏️' : '➕' ?></div>
                        <div class="section-info">
                            <h3><?= $editCron ? 'Cron Job Düzenle' : 'Yeni Cron Job Ekle' ?></h3>
                            <p><?= $editCron ? 'Mevcut cron job bilgilerini güncelleyin' : 'Yeni bir otomatik görev ekleyin' ?>
                            </p>
                        </div>
                    </div>
                    <div class="section-body">
                        <form method="POST">
                            <?php if ($editCron): ?>
                                <input type="hidden" name="cron_id" value="<?= $editCron['id'] ?>">
                                <input type="hidden" name="update_cron" value="1">
                            <?php else: ?>
                                <input type="hidden" name="add_cron" value="1">
                            <?php endif; ?>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label><span class="label-icon">📝</span> İsim</label>
                                    <input type="text" name="cron_name" class="form-control"
                                        value="<?= htmlspecialchars($editCron['name'] ?? '') ?>"
                                        placeholder="Örn: Otomatik Fatura Yenileme" required>
                                </div>
                                <div class="form-group">
                                    <label><span class="label-icon">⏰</span> Zamanlama (Cron Expression)</label>
                                    <input type="text" name="cron_schedule" class="form-control"
                                        value="<?= htmlspecialchars($editCron['schedule'] ?? '0 * * * *') ?>"
                                        placeholder="0 * * * * (her saat başı)" required>
                                    <span class="form-hint">Format: dakika saat gün ay hafta (örn: 0 * * * * = her saat
                                        başı)</span>
                                </div>
                                <div class="form-group full-width">
                                    <label><span class="label-icon">⚙️</span> Komut</label>
                                    <input type="text" name="cron_command" class="form-control"
                                        value="<?= htmlspecialchars($editCron['command'] ?? '') ?>"
                                        placeholder="Örn: generate_invoices" required>
                                    <span class="form-hint">Cron.php içinde çalıştırılacak fonksiyon adı</span>
                                </div>
                                <div class="form-group full-width">
                                    <label><span class="label-icon">📄</span> Açıklama</label>
                                    <textarea name="cron_description" class="form-control" rows="3"
                                        placeholder="Bu cron job ne yapar?"><?= htmlspecialchars($editCron['description'] ?? '') ?></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <div class="switch-row">
                                        <div class="switch-info">
                                            <h4>✅ Aktif</h4>
                                            <p>Cron job'ı aktif et/devre dışı bırak</p>
                                        </div>
                                        <label class="switch-toggle">
                                            <input type="checkbox" name="cron_is_active" value="1"
                                                <?= ($editCron['is_active'] ?? 1) ? 'checked' : '' ?>>
                                            <span class="switch-slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div style="display: flex; gap: 10px; margin-top: 20px;">
                                <button type="submit" class="btn btn-primary">
                                    <?= $editCron ? '💾 Güncelle' : '➕ Ekle' ?>
                                </button>
                                <?php if ($editCron): ?>
                                    <a href="settings.php?tab=cron" class="btn btn-outline">❌ İptal</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Cron Job Listesi -->
                <div class="settings-section">
                    <div class="section-header">
                        <div class="section-icon">📋</div>
                        <div class="section-info">
                            <h3>Mevcut Cron Job'lar</h3>
                            <p>Tüm zamanlanmış görevler</p>
                        </div>
                        <div style="margin-left: auto;">
                            <button type="button" class="btn btn-primary" onclick="runAllCronJobs()" id="runAllBtn">
                                ▶️ Tümünü Çalıştır
                            </button>
                        </div>
                    </div>
                    <div class="section-body">
                        <?php if (empty($cronJobs)): ?>
                            <div
                                style="text-align: center; padding: 30px; background: #f8fafc; border-radius: 12px; margin-bottom: 20px;">
                                <p style="color: #64748b; margin-bottom: 20px; font-size: 15px;">
                                    Henüz cron job eklenmemiş. Varsayılan cron job'ları ekleyebilir veya yukarıdaki formdan
                                    yeni bir tane oluşturabilirsiniz.
                                </p>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="add_default_crons" value="1">
                                    <button type="submit" class="btn btn-primary"
                                        onclick="return confirm('Tüm varsayılan cron job\'lar eklenecek. Devam etmek istiyor musunuz?')">
                                        ➕ Varsayılan Cron Job'ları Ekle
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                            <th
                                                style="padding: 12px; text-align: left; font-size: 13px; color: var(--dark);">
                                                İsim</th>
                                            <th
                                                style="padding: 12px; text-align: left; font-size: 13px; color: var(--dark);">
                                                Zamanlama</th>
                                            <th
                                                style="padding: 12px; text-align: left; font-size: 13px; color: var(--dark);">
                                                Son Çalışma</th>
                                            <th
                                                style="padding: 12px; text-align: left; font-size: 13px; color: var(--dark);">
                                                Durum</th>
                                            <th
                                                style="padding: 12px; text-align: right; font-size: 13px; color: var(--dark);">
                                                İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cronJobs as $job): ?>
                                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                                <td style="padding: 15px;">
                                                    <div style="font-weight: 600; color: var(--dark); margin-bottom: 4px;">
                                                        <?= htmlspecialchars($job['name']) ?>
                                                    </div>
                                                    <?php if (!empty($job['description'])): ?>
                                                        <div style="font-size: 12px; color: #64748b;">
                                                            <?= htmlspecialchars($job['description']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 15px;">
                                                    <code
                                                        style="background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                                                <?= htmlspecialchars($job['schedule']) ?>
                                                            </code>
                                                </td>
                                                <td style="padding: 15px; font-size: 13px; color: #64748b;">
                                                    <?php if ($job['last_run']): ?>
                                                        <?= date('d.m.Y H:i', strtotime($job['last_run'])) ?>
                                                        <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">
                                                            <?php
                                                            $lastRun = strtotime($job['last_run']);
                                                            $now = time();
                                                            $diff = $now - $lastRun;
                                                            if ($diff < 3600) {
                                                                echo floor($diff / 60) . ' dakika önce';
                                                            } elseif ($diff < 86400) {
                                                                echo floor($diff / 3600) . ' saat önce';
                                                            } else {
                                                                echo floor($diff / 86400) . ' gün önce';
                                                            }
                                                            ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span style="color: #94a3b8;">Henüz çalışmadı</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 15px;">
                                                    <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; 
                                                          background: <?= $job['is_active'] ? '#d1fae5' : '#fee2e2' ?>; 
                                                          color: <?= $job['is_active'] ? '#065f46' : '#991b1b' ?>;">
                                                        <?= $job['is_active'] ? '✅ Aktif' : '❌ Pasif' ?>
                                                    </span>
                                                    <?php if ($job['last_status'] === 'failed'): ?>
                                                        <div style="font-size: 11px; color: #dc2626; margin-top: 4px;">⚠️ Son
                                                            çalışma başarısız</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 15px; text-align: right;">
                                                    <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                                        <button type="button" class="btn btn-sm"
                                                            onclick="submitCronAction(<?= $job['id'] ?>, 'toggle')" style="background: <?= $job['is_active'] ? '#fee2e2' : '#d1fae5' ?>; 
                                                                       color: <?= $job['is_active'] ? '#dc2626' : '#065f46' ?>; 
                                                                       border: none;">
                                                            <?= $job['is_active'] ? '⏸️' : '▶️' ?>
                                                        </button>

                                                        <a href="settings.php?tab=cron&edit_cron=<?= $job['id'] ?>"
                                                            class="btn btn-sm btn-outline">✏️</a>

                                                        <button type="button" class="btn btn-sm btn-danger"
                                                            onclick="if(confirm('Bu cron job\'ı silmek istediğinize emin misiniz?')) submitCronAction(<?= $job['id'] ?>, 'delete')">
                                                            🗑️
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sistem Ayarları -->
    <div class="tab-content" id="tab-system">
        <div class="settings-section">
            <div class="section-header">
                <div class="section-icon">⚙️</div>
                <div class="section-info">
                    <h3>Sistem Ayarları</h3>
                    <p>Genel sistem yapılandırması</p>
                </div>
            </div>
            <div class="section-body">
                <div class="switch-row">
                    <div class="switch-info">
                        <h4>👥 Yeni Kayıtlar</h4>
                        <p>Yeni müşteri kayıtlarına izin ver</p>
                    </div>
                    <label class="switch-toggle">
                        <input type="hidden" name="settings[registration_enabled]" value="0">
                        <input type="checkbox" name="settings[registration_enabled]" value="1"
                            <?= ($settings['registration_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <span class="switch-slider"></span>
                    </label>
                </div>

                <div class="switch-row">
                    <div class="switch-info">
                        <h4>🔧 Bakım Modu</h4>
                        <p>Aktifken sadece adminler siteye erişebilir</p>
                    </div>
                    <label class="switch-toggle">
                        <input type="hidden" name="settings[maintenance_mode]" value="0">
                        <input type="checkbox" name="settings[maintenance_mode]" value="1"
                            <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <span class="switch-slider"></span>
                    </label>
                </div>

                <div class="form-grid" style="margin-top: 20px;">
                    <div class="form-group">
                        <label><span class="label-icon">🌐</span> Varsayılan Dil</label>
                        <select name="settings[default_language]" class="form-control">
                            <option value="tr" <?= ($settings['default_language'] ?? 'tr') === 'tr' ? 'selected' : '' ?>>
                                🇹🇷 Türkçe</option>
                            <option value="en" <?= ($settings['default_language'] ?? 'tr') === 'en' ? 'selected' : '' ?>>
                                🇬🇧 English</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Save Footer -->
    <div class="save-footer">
        <div class="info">
            <span>💡</span>
            <span>Değişikliklerinizi kaydetmeyi unutmayın!</span>
        </div>
        <button type="submit" class="btn btn-primary btn-lg">
            💾 Ayarları Kaydet
        </button>
    </div>

</form>

<script>
    function showSettingsTab(tabName) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.settings-tab').forEach(btn => {
            btn.classList.remove('active');
        });

        // Show selected tab
        document.getElementById('tab-' + tabName).classList.add('active');
        event.target.closest('.settings-tab').classList.add('active');

        // URL'yi güncelle
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.pushState({}, '', url);
    }

    // Sayfa yüklendiğinde tab'ı kontrol et
    document.addEventListener('DOMContentLoaded', function () {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        if (tab) {
            const tabButton = document.querySelector(`.settings-tab[onclick*="'${tab}'"]`);
            if (tabButton) {
                tabButton.click();
            }
        }
    });

    function copyCronUrl() {
        const input = document.getElementById('cronUrl');
        input.select();
        document.execCommand('copy');

        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '✅ Kopyalandı!';
        btn.style.background = '#d1fae5';
        btn.style.color = '#065f46';

        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.style.background = '';
            btn.style.color = '';
        }, 2000);
    }

    async function runAllCronJobs() {
        const btn = document.getElementById('runAllBtn');
        if (!btn) return;

        const originalText = btn.innerHTML;
        const originalDisabled = btn.disabled;

        btn.disabled = true;
        btn.innerHTML = '⏳ Çalıştırılıyor...';

        try {
            const response = await fetch('/cron.php?run_all=1', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const text = await response.text();

            if (response.ok) {
                btn.innerHTML = '✅ Tamamlandı!';
                btn.style.background = '#d1fae5';
                btn.style.color = '#065f46';

                // Sonuçları göster
                showCronResult(text);

                // 3 saniye sonra sayfayı yenile
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
            } else {
                btn.innerHTML = '❌ Hata!';
                btn.style.background = '#fee2e2';
                btn.style.color = '#991b1b';
                alert('Cron job çalıştırılırken hata oluştu: ' + text);
            }
        } catch (error) {
            btn.innerHTML = '❌ Hata!';
            btn.style.background = '#fee2e2';
            btn.style.color = '#991b1b';
            alert('Bağlantı hatası: ' + error.message);
        } finally {
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = originalDisabled;
                btn.style.background = '';
                btn.style.color = '';
            }, 3000);
        }
    }

    function showCronResult(text) {
        // Sonuçları göstermek için bir alert veya modal oluştur
        const resultDiv = document.createElement('div');
        resultDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); z-index: 10000; max-width: 500px; max-height: 400px; overflow-y: auto;';
        resultDiv.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h4 style="margin: 0; color: var(--dark);">Cron Job Sonuçları</h4>
            <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">&times;</button>
        </div>
        <pre style="background: #f8fafc; padding: 15px; border-radius: 8px; font-size: 12px; color: #1e293b; white-space: pre-wrap; word-wrap: break-word; margin: 0;">${text}</pre>
    `;
        document.body.appendChild(resultDiv);

        // 10 saniye sonra otomatik kapat
        setTimeout(() => {
            if (resultDiv.parentElement) {
                resultDiv.remove();
            }
        }, 10000);
    }

    // Color input sync
    document.querySelectorAll('.color-preview input[type="color"]').forEach(input => {
        input.addEventListener('input', function () {
            this.closest('.color-input-wrapper').querySelector('.color-value').value = this.value;
        });
    });

    // Test email function
    async function sendTestEmail() {
        const email = document.getElementById('test_email').value;
        const resultDiv = document.getElementById('test_result');

        if (!email) {
            resultDiv.innerHTML = '<div class="alert alert-danger"><span class="alert-icon">❌</span> Lütfen bir e-posta adresi girin.</div>';
            resultDiv.style.display = 'block';
            return;
        }

        resultDiv.innerHTML = '<div class="alert" style="background: #f1f5f9; border: 1px solid #e2e8f0; color: #64748b;"><span class="alert-icon">⏳</span> Test e-postası gönderiliyor...</div>';
        resultDiv.style.display = 'block';

        try {
            const response = await fetch('ajax/send-test-email.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: email })
            });

            const data = await response.json();

            if (data.success) {
                resultDiv.innerHTML = '<div class="alert alert-success"><span class="alert-icon">✅</span> Test e-postası başarıyla gönderildi!</div>';
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger"><span class="alert-icon">❌</span> Hata: ' + (data.error || 'Bilinmeyen hata') + '</div>';
            }
        } catch (error) {
            resultDiv.innerHTML = '<div class="alert alert-danger"><span class="alert-icon">❌</span> Bağlantı hatası: ' + error.message + '</div>';
        }
    }

    function submitCronAction(id, action) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'cron_id';
        idInput.value = id;
        form.appendChild(idInput);

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';

        if (action === 'toggle') {
            actionInput.name = 'toggle_cron';
        } else if (action === 'delete') {
            actionInput.name = 'delete_cron';
        }
        actionInput.value = '1';
        form.appendChild(actionInput);

        document.body.appendChild(form);
        form.submit();
    }
</script>

<?php include 'includes/footer.php'; ?>