<?php
/**
 * WHMVM - Gelişmiş Sistem Güncelleme Yönetimi (Premium UI)
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once __DIR__ . '/includes/GithubUpdater.php';

require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Sistem Güncelleme';
$message = '';
$updater = new GithubUpdater();

// Ayarları Kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $repo = trim($_POST['github_repo']);
    $token = trim($_POST['github_token']);

    if (strpos($repo, '/') === false) {
        $message = ['type' => 'danger', 'text' => 'Geçersiz repo formatı! Örn: kullanıcı/proje'];
    } else {
        $updater->saveSettings($repo, $token);
        $message = ['type' => 'success', 'text' => 'GitHub ayarları başarıyla kaydedildi!'];
    }
}

// Güncelleme Başlat
if (isset($_GET['action']) && $_GET['action'] === 'update' && isset($_GET['version'])) {
    // Demo modu kontrolü (Gerekirse)
    $targetVersion = $_GET['version'];
    $result = $updater->performUpdate($targetVersion);

    if ($result['success']) {
        // Yönlendir (Refresh sorunu olmasın)
        $_SESSION['update_success'] = "Sistem başarıyla <strong>$targetVersion</strong> sürümüne güncellendi!";
        header("Location: updates.php");
        exit;
    } else {
        $message = ['type' => 'danger', 'text' => "Güncelleme hatası: " . htmlspecialchars($result['error'])];
    }
}

if (isset($_SESSION['update_success'])) {
    $message = ['type' => 'success', 'text' => $_SESSION['update_success']];
    unset($_SESSION['update_success']);
}

// Mevcut Durumu Çek
$currentVersion = APP_VERSION;
$settings = $updater->getSettings();
$latestRelease = null;
$error = null;
$check = ['success' => false, 'updateAvailable' => false];

if (!empty($settings['repo'])) {
    $check = $updater->checkForUpdates($currentVersion);
    if ($check['success']) {
        $latestRelease = $check['release'];
    } else {
        $error = $check['error'];
    }
}

include 'includes/header.php';
?>

<style>
    /* Premium Update Page Styles */
    :root {
        --up-primary: #6366f1;
        --up-success: #10b981;
        --up-bg: var(--y-yuzey-2);
        --up-card: var(--y-yuzey);
        --up-text: var(--y-metin);
    }

    .update-container {
        max-width: 1000px;
        margin: 0 auto;
    }

    .hero-card {
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        border-radius: 24px;
        padding: 40px;
        color: white;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 40px rgba(79, 70, 229, 0.2);
        margin-bottom: 30px;
    }

    .hero-content {
        position: relative;
        z-index: 2;
        text-align: center;
    }

    .version-badge {
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        padding: 8px 16px;
        border-radius: 50px;
        font-size: 14px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 20px;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .hero-title {
        font-size: 32px;
        font-weight: 800;
        margin-bottom: 15px;
        letter-spacing: -0.5px;
    }

    .hero-subtitle {
        font-size: 16px;
        opacity: 0.9;
        max-width: 600px;
        margin: 0 auto 30px;
        line-height: 1.6;
    }

    /* Status Icons */
    .status-icon-wrapper {
        width: 120px;
        height: 120px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 25px;
        position: relative;
    }

    .status-icon-wrapper::before {
        content: '';
        position: absolute;
        top: -10px;
        left: -10px;
        right: -10px;
        bottom: -10px;
        border: 2px dashed rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        animation: spin 20s linear infinite;
    }

    .status-icon {
        font-size: 48px;
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    /* Settings Panel */
    .settings-toggle {
        background: var(--y-yuzey);
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .settings-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .settings-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--up-text);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-control-lg {
        padding: 15px 20px;
        font-size: 15px;
        border-radius: 12px;
    }

    /* Release Notes */
    .release-notes-card {
        background: var(--y-yuzey);
        border-radius: 16px;
        padding: 30px;
        margin-top: 30px;
        border: 1px solid var(--y-cizgi);
    }

    .update-btn {
        background: var(--y-yuzey);
        color: #4f46e5;
        font-weight: 700;
        padding: 16px 32px;
        border-radius: 12px;
        border: none;
        font-size: 16px;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .update-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }

    /* Confetti */
    .confetti {
        position: absolute;
        width: 10px;
        height: 10px;
        background-color: #f00;
        animation: confetti 5s ease-in-out infinite;
        opacity: 0;
    }

    @keyframes confetti {
        0% {
            transform: translateY(0) rotate(0deg);
            opacity: 1;
        }

        100% {
            transform: translateY(100vh) rotate(720deg);
            opacity: 0;
        }
    }
</style>

<div class="update-container">

    <?php if ($message): ?>
        <div class="alert alert-<?= $message['type'] ?> mb-4 shadow-sm border-0" style="border-radius: 12px;">
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-<?= $message['type'] == 'success' ? 'check-circle' : 'exclamation-circle' ?> fa-lg"></i>
                <div><?= $message['text'] ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- HERO SECTION -->
    <div class="hero-card">
        <!-- Abstract Shapes -->
        <div
            style="position: absolute; top: -50px; left: -50px; width: 200px; height: 200px; background: rgba(255,255,255,0.1); border-radius: 50%;">
        </div>
        <div
            style="position: absolute; bottom: -50px; right: -50px; width: 300px; height: 300px; background: rgba(255,255,255,0.05); border-radius: 50%;">
        </div>

        <div class="hero-content">
            <?php if (empty($settings['repo'])): ?>
                <!-- Yapılandırılmamış -->
                <div class="status-icon-wrapper">
                    <span class="status-icon">⚙️</span>
                </div>
                <h1 class="hero-title">Kurulum Gerekli</h1>
                <p class="hero-subtitle">Otomatik güncellemeleri almak için aşağıdaki GitHub ayarlarını yapılandırmanız
                    gerekmektedir.</p>

            <?php elseif ($error): ?>
                <!-- Hata -->
                <div class="status-icon-wrapper" style="border-color: rgba(255,200,200,0.3);">
                    <span class="status-icon">⚠️</span>
                </div>
                <h1 class="hero-title">Bağlantı Hatası</h1>
                <p class="hero-subtitle"><?= htmlspecialchars($error) ?></p>
                <div class="version-badge" style="background: rgba(239, 68, 68, 0.2);">
                    Tekrar Denemeyi Öneririz
                </div>

            <?php elseif ($check['updateAvailable']): ?>
                <!-- Güncelleme Var -->
                <div class="status-icon-wrapper">
                    <span class="status-icon">🚀</span>
                </div>
                <div class="version-badge">
                    <span>Mevcut: v<?= $currentVersion ?></span>
                    <i class="fas fa-arrow-right fa-xs"></i>
                    <strong style="color: #86efac;">Yeni: v<?= $check['latestVersion'] ?></strong>
                </div>
                <h1 class="hero-title">Yeni Sürüm Mevcut!</h1>
                <p class="hero-subtitle">
                    Sisteminiz için önemli performans iyileştirmeleri ve yeni özellikler hazır.
                    Yükseltme işlemine hemen başlayın.
                </p>
                <a href="updates.php?action=update&version=<?= htmlspecialchars($check['latestVersion']) ?>"
                    class="update-btn"
                    onclick="return confirm('Güncelleme işlemi sistem dosyalarını yenileyecektir. Devam edilsin mi?');">
                    <span class="icon">✨</span> Şimdi Güncelle
                </a>

            <?php else: ?>
                <!-- Sistem Güncel -->
                <div class="status-icon-wrapper">
                    <span class="status-icon">✨</span>
                </div>
                <div class="version-badge">
                    <i class="fas fa-check-circle"></i>
                    Sürüm v<?= $currentVersion ?>
                </div>
                <h1 class="hero-title">Sisteminiz Harika Görünüyor!</h1>
                <p class="hero-subtitle">En güncel WHMVM sürümünü kullanıyorsunuz. Şu an yapmanız gereken hiçbir şey yok.
                </p>
                <button class="btn btn-outline-light rounded-pill px-4" onclick="window.location.reload()">
                    <i class="fas fa-sync-alt me-2"></i> Tekrar Kontrol Et
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- RELEASE NOTES (Sadece Güncelleme Varsa) -->
    <?php if (isset($latestRelease) && $check['updateAvailable']): ?>
        <div class="release-notes-card">
            <h3 class="settings-title mb-4">
                <span
                    style="background: #e0e7ff; color: #4338ca; padding: 8px; border-radius: 8px; font-size: 20px;">📝</span>
                Sürüm Notları (v<?= htmlspecialchars($check['latestVersion']) ?>)
            </h3>
            <div class="markdown-body" style="color: #475569; line-height: 1.7;">
                <?= nl2br(htmlspecialchars($latestRelease['body'] ?? 'Açıklama belirtilmemiş.')) ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- GITHUB SETTINGS -->
    <div class="settings-toggle mt-4">
        <div class="settings-header"
            onclick="document.getElementById('settingsForm').style.display = document.getElementById('settingsForm').style.display == 'none' ? 'block' : 'none'"
            style="cursor: pointer;">
            <div class="settings-title">
                <i class="fab fa-github fa-lg text-dark"></i>
                GitHub Bağlantı Ayarları
            </div>
            <i class="fas fa-chevron-down text-muted"></i>
        </div>

        <div id="settingsForm" style="display: <?= empty($settings['repo']) ? 'block' : 'none' ?>;">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold text-uppercase">GitHub Repo</label>
                        <input type="text" name="github_repo" class="form-control form-control-lg bg-light border-0"
                            value="<?= htmlspecialchars($settings['repo'] ?? '') ?>" placeholder="kullanici/proje">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold text-uppercase">Personal Access Token</label>
                        <input type="password" name="github_token"
                            class="form-control form-control-lg bg-light border-0"
                            value="<?= htmlspecialchars($settings['token'] ?? '') ?>"
                            placeholder="ghp_....................">
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" name="save_settings" class="btn btn-dark px-4 py-2 rounded-3">
                        <i class="fas fa-save me-2"></i> Ayarları Kaydet
                    </button>
                </div>
                <div class="alert alert-light mt-3 mb-0 small text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Private repolar için <strong>Read-Only</strong> yetkili bir Classic Token kullanmanız önerilir.
                </div>
            </form>
        </div>
    </div>

</div>

<!-- Confetti Script (Sistem Güncelse) -->
<?php if (!$check['updateAvailable'] && empty($error) && !empty($settings['repo'])): ?>
    <script>
        // Basit konfeti efekti
        function createConfetti() {
            const colors = ['#6366f1', '#10b981', '#f59e0b', '#ec4899'];
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            confetti.style.left = Math.random() * 100 + 'vw';
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.animationDuration = (Math.random() * 3 + 2) + 's';
            document.body.appendChild(confetti);

            setTimeout(() => confetti.remove(), 5000);
        }

        // Sayfa açıldığında biraz konfeti at
        let interval = setInterval(createConfetti, 200);
        setTimeout(() => clearInterval(interval), 3000);
    </script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>