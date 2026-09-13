<?php
/**
 * WHMVM - PayTR Ödeme Modülü Ayarları
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

$pageTitle = 'PayTR Ödeme Ayarları';
$currentPage = 'paytr';
$message = '';
$messageType = 'success';

// PayTR modülü kontrolü
$paytrModule = Database::fetch("SELECT * FROM modules WHERE slug = 'paytr'");
if (!$paytrModule) {
    $message = 'PayTR modülü yüklü değil!';
    $messageType = 'danger';
}

// Ayarları kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $merchantId = trim($_POST['merchant_id'] ?? '');
    $merchantKey = trim($_POST['merchant_key'] ?? '');
    $merchantSalt = trim($_POST['merchant_salt'] ?? '');
    $testMode = isset($_POST['test_mode']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($merchantId) || empty($merchantKey) || empty($merchantSalt)) {
        $message = 'Tüm alanlar doldurulmalıdır!';
        $messageType = 'danger';
    } else {
        // Config'i güncelle
        $config = json_encode([
            'merchant_id' => $merchantId,
            'merchant_key' => $merchantKey,
            'merchant_salt' => $merchantSalt,
            'test_mode' => $testMode
        ]);
        
        Database::query("
            UPDATE modules 
            SET config = ?, is_active = ?, updated_at = NOW()
            WHERE slug = 'paytr'
        ", [$config, $isActive]);
        
        $message = 'Ayarlar başarıyla kaydedildi!';
    }
}

// Mevcut ayarları al
$config = [];
if ($paytrModule) {
    $config = json_decode($paytrModule['config'] ?? '{}', true) ?: [];
}

include 'includes/header.php';
?>

<style>
.settings-form {
    background: var(--y-yuzey);
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--dark);
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid var(--y-cizgi);
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
}

.form-hint {
    display: block;
    margin-top: 6px;
    color: var(--y-metin-3);
    font-size: 12px;
}

.switch-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 20px;
    background: var(--y-yuzey-2);
    border-radius: 12px;
    margin-bottom: 15px;
}

.switch-info h4 {
    font-size: 14px;
    color: var(--dark);
    margin-bottom: 4px;
}

.switch-info p {
    font-size: 12px;
    color: var(--y-metin-3);
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
    background-color: var(--y-yuzey);
    transition: .3s;
    border-radius: 50%;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.switch-toggle input:checked + .switch-slider {
    background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
}

.switch-toggle input:checked + .switch-slider:before {
    transform: translateX(26px);
}

.alert {
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    font-size: 14px;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-danger {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}
</style>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="settings-form">
    <h2 style="margin-bottom: 25px; font-size: 24px; color: var(--dark);">
        PayTR Ödeme Ayarları
    </h2>
    
    <!-- Callback URL Bilgisi -->
    <div style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); padding: 20px; border-radius: 12px; margin-bottom: 25px; border: 2px solid #0ea5e9;">
        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
            <span style="font-size: 32px;">🔗</span>
            <div style="flex: 1;">
                <h4 style="margin: 0 0 5px 0; color: var(--dark);">Bildirim URL (Callback URL)</h4>
                <p style="margin: 0; color: #64748b; font-size: 13px;">Bu URL'i PayTR panelinde "Bildirim URL" alanına ekleyin</p>
            </div>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <input type="text" id="callbackUrl" value="<?= SITE_URL ?>/paytr-callback.php" 
                   readonly style="flex: 1; padding: 12px; background: white; border: 2px solid #0ea5e9; border-radius: 8px; font-family: monospace; font-size: 13px;">
            <button type="button" onclick="copyCallbackUrl()" class="btn btn-primary btn-sm" 
                    style="padding: 12px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%); color: white;">
                📋 Kopyala
            </button>
        </div>
        <p style="margin: 10px 0 0 0; color: #64748b; font-size: 12px;">
            <strong>Önemli:</strong> PayTR yönetim panelinde <strong>Ayarlar > Bildirim URL</strong> bölümüne bu URL'i ekleyin.
        </p>
    </div>
    
    <form method="POST">
        <div class="form-group">
            <label>Mağaza No (Merchant ID) *</label>
            <input type="text" name="merchant_id" class="form-control" required
                   value="<?= htmlspecialchars($config['merchant_id'] ?? '') ?>"
                   placeholder="123456">
            <span class="form-hint">PayTR panelinden alacağınız mağaza numarası</span>
        </div>
        
        <div class="form-group">
            <label>Mağaza Anahtarı (Merchant Key) *</label>
            <input type="text" name="merchant_key" class="form-control" required
                   value="<?= htmlspecialchars($config['merchant_key'] ?? '') ?>"
                   placeholder="xxxxxxxxxxxxxxxx">
            <span class="form-hint">PayTR panelinden alacağınız mağaza anahtarı</span>
        </div>
        
        <div class="form-group">
            <label>Mağaza Salt (Merchant Salt) *</label>
            <input type="text" name="merchant_salt" class="form-control" required
                   value="<?= htmlspecialchars($config['merchant_salt'] ?? '') ?>"
                   placeholder="xxxxxxxxxxxxxxxx">
            <span class="form-hint">PayTR panelinden alacağınız salt değeri</span>
        </div>
        
        <div class="switch-row">
            <div class="switch-info">
                <h4>🧪 Test Modu</h4>
                <p>Test modunda gerçek ödeme alınmaz</p>
            </div>
            <label class="switch-toggle">
                <input type="checkbox" name="test_mode" value="1" 
                       <?= ($config['test_mode'] ?? true) ? 'checked' : '' ?>>
                <span class="switch-slider"></span>
            </label>
        </div>
        
        <div class="switch-row">
            <div class="switch-info">
                <h4>✅ Modülü Aktif Et</h4>
                <p>PayTR ödeme modülünü aktifleştir</p>
            </div>
            <label class="switch-toggle">
                <input type="checkbox" name="is_active" value="1" 
                       <?= ($paytrModule['is_active'] ?? 0) ? 'checked' : '' ?>>
                <span class="switch-slider"></span>
            </label>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
            <button type="submit" name="save_settings" class="btn btn-primary" 
                    style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 10px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%); color: white;">
                💾 Ayarları Kaydet
            </button>
        </div>
    </form>
</div>

<div class="settings-form">
    <h3 style="margin-bottom: 20px; font-size: 18px; color: var(--dark);">
        PayTR Bilgileri
    </h3>
    <div style="background: #f8fafc; padding: 20px; border-radius: 12px;">
        <p style="color: #64748b; margin-bottom: 15px;">
            PayTR ödeme bilgilerinizi <strong>PayTR Yönetim Paneli</strong>'nden alabilirsiniz.
        </p>
        <ul style="color: #64748b; line-height: 1.8;">
            <li><strong>Mağaza No:</strong> PayTR panelinde hesabınızın benzersiz numarası</li>
            <li><strong>Mağaza Anahtarı:</strong> API erişimi için güvenlik anahtarı</li>
            <li><strong>Mağaza Salt:</strong> Hash oluşturma için kullanılan salt değeri</li>
            <li><strong>Test Modu:</strong> Gerçek ödeme almadan test yapmak için aktif edin</li>
        </ul>
        <p style="margin-top: 15px; color: #64748b;">
            <strong>Önemli:</strong> Test modunda gerçek ödeme alınmaz. Canlı ortamda test modunu kapatmayı unutmayın!
        </p>
    </div>
</div>

<script>
function copyCallbackUrl() {
    const input = document.getElementById('callbackUrl');
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
</script>

<?php include 'includes/footer.php'; ?>
