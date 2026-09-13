<?php
/**
 * WHMVM - Güvenlik Ayarları
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Güvenlik';
$currentPage = 'security';
$clientId = (int)($_SESSION['client_id'] ?? 0);

include 'includes/header.php';

$message = '';
$error = '';

// Müşteri bilgilerini çek
try {
    $client = Database::fetch("SELECT * FROM clients WHERE id = ?", [$clientId]);
} catch (Exception $e) {
    $client = [];
}

// Şifre değiştir
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'Tüm alanları doldurun.';
    } elseif (!password_verify($currentPassword, $client['password'] ?? '')) {
        $error = 'Mevcut şifreniz yanlış.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Yeni şifre en az 8 karakter olmalıdır.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Yeni şifreler eşleşmiyor.';
    } else {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            Database::query("UPDATE clients SET password = ?, updated_at = NOW() WHERE id = ?", [$hashedPassword, $clientId]);
            $message = 'Şifreniz başarıyla değiştirildi.';
        } catch (Exception $e) {
            $error = 'Şifre değiştirme sırasında bir hata oluştu.';
        }
    }
}
?>

<style>
/* Page Specific Styles */
.security-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
}

.form-card {
    background: var(--card-bg);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
}

.form-card-header {
    padding: 20px 24px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-card-header h3 {
    font-size: 16px;
    font-weight: 600;
    margin: 0;
}

.form-card-header i {
    color: var(--primary-light);
}

.form-card-body {
    padding: 24px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 8px;
    color: var(--text-secondary);
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    color: #fff;
    font-size: 14px;
    transition: all 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

.form-control::placeholder {
    color: var(--text-muted);
}

.btn-save {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 28px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
}

/* Info Card */
.info-card {
    background: var(--card-bg);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    height: fit-content;
}

.info-card-header {
    padding: 15px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-card-header i {
    color: var(--primary-light);
}

.info-card-body {
    padding: 20px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    font-size: 14px;
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    color: var(--text-muted);
}

.info-value {
    font-weight: 600;
}

/* Password Tips */
.password-tips {
    padding: 20px;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 12px;
    margin-top: 15px;
}

.password-tips h4 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.password-tips ul {
    margin: 0;
    padding-left: 20px;
    color: var(--text-muted);
    font-size: 13px;
}

.password-tips li {
    margin-bottom: 6px;
}

/* Responsive */
@media (max-width: 1024px) {
    .security-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Page Header -->
<div class="page-header">
    <h1><i class="fas fa-shield-alt"></i> Güvenlik</h1>
    <p>Şifrenizi ve güvenlik ayarlarınızı yönetin</p>
</div>

<?php if ($message): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="security-grid">
    <!-- Password Change Form -->
    <div class="form-card">
        <div class="form-card-header">
            <i class="fas fa-key"></i>
            <h3>Şifre Değiştir</h3>
        </div>
        <div class="form-card-body">
            <form method="POST">
                <div class="form-group">
                    <label>Mevcut Şifre</label>
                    <input type="password" name="current_password" class="form-control" required
                           placeholder="••••••••">
                </div>
                <div class="form-group">
                    <label>Yeni Şifre</label>
                    <input type="password" name="new_password" class="form-control" required
                           minlength="8" placeholder="••••••••">
                </div>
                <div class="form-group">
                    <label>Yeni Şifre (Tekrar)</label>
                    <input type="password" name="confirm_password" class="form-control" required
                           minlength="8" placeholder="••••••••">
                </div>
                
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Şifreyi Değiştir
                </button>
            </form>
            
            <div class="password-tips">
                <h4><i class="fas fa-lightbulb"></i> Güçlü Şifre İpuçları</h4>
                <ul>
                    <li>En az 8 karakter kullanın</li>
                    <li>Büyük ve küçük harf karışımı kullanın</li>
                    <li>Rakam ve özel karakterler ekleyin</li>
                    <li>Kişisel bilgilerinizi kullanmayın</li>
                    <li>Her hesap için farklı şifre kullanın</li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Security Info -->
    <div>
        <div class="info-card">
            <div class="info-card-header">
                <i class="fas fa-info-circle"></i>
                Hesap Bilgileri
            </div>
            <div class="info-card-body">
                <div class="info-item">
                    <span class="info-label">Son Giriş</span>
                    <span class="info-value">
                        <?= $client['last_login'] ? date('d.m.Y H:i', strtotime($client['last_login'])) : 'Hiç' ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Son IP Adresi</span>
                    <span class="info-value"><?= htmlspecialchars($client['last_ip'] ?? '-') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Kayıt Tarihi</span>
                    <span class="info-value"><?= date('d.m.Y', strtotime($client['created_at'] ?? 'now')) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Hesap Durumu</span>
                    <span class="info-value">
                        <?php if ($client['is_active'] ?? false): ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Pasif</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="info-card" style="margin-top: 20px;">
            <div class="info-card-header">
                <i class="fas fa-shield-alt"></i>
                Güvenlik Durumu
            </div>
            <div class="info-card-body">
                <div class="info-item">
                    <span class="info-label">E-posta Doğrulaması</span>
                    <span class="info-value">
                        <?php if ($client['email_verified'] ?? false): ?>
                            <span class="badge badge-success">✅ Doğrulanmış</span>
                        <?php else: ?>
                            <span class="badge badge-warning">⏳ Bekliyor</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">İki Faktörlü Doğrulama</span>
                    <span class="info-value">
                        <span class="badge badge-gray">Pasif</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
