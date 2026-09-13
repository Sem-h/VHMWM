<?php
/**
 * NetGSM SMS Modülü - Admin Ayarlar ve SMS Gönderim Sayfası
 * 
 * @version 1.0.0
 */

declare(strict_types=1);

// WHMVM sisteminden çağrılıyorsa
if (!defined('WHMVM_ROOT')) {
    define('WHMVM_ROOT', dirname(dirname(__DIR__)));
}

require_once WHMVM_ROOT . '/config/config.php';
require_once WHMVM_ROOT . '/includes/Database.php';
require_once __DIR__ . '/NetGSMGateway.php';

session_name(SESSION_NAME);
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: ' . WHMVM_ROOT . '/admin/login.php');
    exit;
}

$pageTitle = 'NetGSM SMS Modülü';
$message = '';
$messageType = 'success';

// Ayarları Yükle
$settings = Database::fetch("SELECT config FROM modules WHERE slug = 'netgsm'");
$config = $settings ? json_decode($settings['config'] ?? '{}', true) : [];

// Varsayılan değerler
$config = array_merge([
    'usercode' => '',
    'password' => '',
    'msgheader' => '',
    'test_mode' => true
], $config);

// Ayarları Kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $newConfig = [
        'usercode' => trim($_POST['usercode'] ?? ''),
        'password' => trim($_POST['password'] ?? ''),
        'msgheader' => trim($_POST['msgheader'] ?? ''),
        'test_mode' => isset($_POST['test_mode'])
    ];

    Database::query("UPDATE modules SET config = ? WHERE slug = 'netgsm'", [json_encode($newConfig)]);
    $config = $newConfig;
    $message = 'Ayarlar başarıyla kaydedildi.';
}

// Bağlantı Testi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_connection'])) {
    $gateway = new NetGSMGateway($config);
    $result = $gateway->testConnection();

    if ($result['success']) {
        $message = 'Bağlantı başarılı! Bakiye: ' . ($result['balance'] ?? 'N/A') . ' SMS';
    } else {
        $message = 'Bağlantı hatası: ' . $result['message'];
        $messageType = 'danger';
    }
}

// Tek SMS Gönder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_single'])) {
    $phone = trim($_POST['phone'] ?? '');
    $smsMessage = trim($_POST['message'] ?? '');

    if (empty($phone) || empty($smsMessage)) {
        $message = 'Telefon ve mesaj alanları zorunludur.';
        $messageType = 'danger';
    } else {
        $gateway = new NetGSMGateway($config);
        $result = $gateway->sendSMS($phone, $smsMessage);

        if ($result['success']) {
            $testNote = $result['test_mode'] ?? false ? ' (Test Modu)' : '';
            $message = 'SMS gönderildi!' . $testNote;
        } else {
            $message = 'SMS gönderilemedi: ' . $result['message'];
            $messageType = 'danger';
        }
    }
}

// Toplu SMS Gönder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_bulk'])) {
    $clientFilter = $_POST['client_filter'] ?? 'all';
    $smsMessage = trim($_POST['bulk_message'] ?? '');

    if (empty($smsMessage)) {
        $message = 'Mesaj alanı zorunludur.';
        $messageType = 'danger';
    } else {
        // Müşteri telefonlarını çek
        $where = "phone IS NOT NULL AND phone != ''";
        if ($clientFilter === 'active') {
            $where .= " AND is_active = 1";
        } elseif ($clientFilter === 'inactive') {
            $where .= " AND is_active = 0";
        }

        $clients = Database::fetchAll("SELECT phone, first_name, last_name FROM clients WHERE $where");
        $phones = array_column($clients, 'phone');

        if (empty($phones)) {
            $message = 'Gönderilecek telefon numarası bulunamadı.';
            $messageType = 'danger';
        } else {
            $gateway = new NetGSMGateway($config);
            $result = $gateway->sendBulkSMS($phones, $smsMessage);

            $testNote = $result['test_mode'] ?? false ? ' (Test Modu)' : '';
            $message = "Toplu SMS gönderildi! Başarılı: {$result['sent']}, Başarısız: {$result['failed']}" . $testNote;

            if ($result['failed'] > 0) {
                $messageType = 'warning';
            }
        }
    }
}

// Müşteri sayıları
$totalClients = (int) Database::fetch("SELECT COUNT(*) as cnt FROM clients WHERE phone IS NOT NULL AND phone != ''")['cnt'];
$activeClients = (int) Database::fetch("SELECT COUNT(*) as cnt FROM clients WHERE phone IS NOT NULL AND phone != '' AND is_active = 1")['cnt'];

include WHMVM_ROOT . '/admin/includes/header.php';
?>

<style>
    .netgsm-container {
        max-width: 1200px;
        margin: 0 auto;
    }

    .netgsm-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 30px;
    }

    .netgsm-logo {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #00b894, #00cec9);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        color: white;
    }

    .netgsm-title h1 {
        font-size: 24px;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }

    .netgsm-title p {
        color: #64748b;
        margin: 5px 0 0 0;
    }

    .netgsm-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
    }

    @media (max-width: 992px) {
        .netgsm-grid {
            grid-template-columns: 1fr;
        }
    }

    .sms-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    }

    .sms-card-header {
        background: linear-gradient(135deg, #00b894, #00cec9);
        color: white;
        padding: 20px 25px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .sms-card-header.settings {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
    }

    .sms-card-header.bulk {
        background: linear-gradient(135deg, #f59e0b, #f97316);
    }

    .sms-card-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
    }

    .sms-card-body {
        padding: 25px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 8px;
    }

    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.2s;
    }

    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        outline: none;
        border-color: #00b894;
        box-shadow: 0 0 0 3px rgba(0, 184, 148, 0.1);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 100px;
    }

    .form-check {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
    }

    .form-check input[type="checkbox"] {
        width: 20px;
        height: 20px;
        accent-color: #00b894;
    }

    .btn-netgsm {
        background: linear-gradient(135deg, #00b894, #00cec9);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
    }

    .btn-netgsm:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0, 184, 148, 0.3);
    }

    .btn-netgsm.purple {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
    }

    .btn-netgsm.orange {
        background: linear-gradient(135deg, #f59e0b, #f97316);
    }

    .btn-outline-netgsm {
        background: white;
        color: #00b894;
        border: 2px solid #00b894;
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-outline-netgsm:hover {
        background: #00b894;
        color: white;
    }

    .stats-row {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
    }

    .stat-box {
        flex: 1;
        background: #f8fafc;
        border-radius: 12px;
        padding: 15px;
        text-align: center;
    }

    .stat-box .value {
        font-size: 24px;
        font-weight: 700;
        color: #00b894;
    }

    .stat-box .label {
        font-size: 12px;
        color: #64748b;
        text-transform: uppercase;
    }

    .char-counter {
        text-align: right;
        font-size: 12px;
        color: #64748b;
        margin-top: 5px;
    }

    .alert-premium {
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
    }

    .alert-premium.success {
        background: #d1fae5;
        color: #065f46;
        border-left: 4px solid #10b981;
    }

    .alert-premium.danger {
        background: #fee2e2;
        color: #991b1b;
        border-left: 4px solid #ef4444;
    }

    .alert-premium.warning {
        background: #fef3c7;
        color: #92400e;
        border-left: 4px solid #f59e0b;
    }

    .test-mode-badge {
        background: #fef3c7;
        color: #92400e;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        margin-left: 10px;
    }
</style>

<div class="netgsm-container">
    <?php if ($message): ?>
        <div class="alert-premium <?= $messageType ?>">
            <?= $messageType === 'success' ? '✅' : ($messageType === 'warning' ? '⚠️' : '❌') ?>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="netgsm-header">
        <div class="netgsm-logo">📱</div>
        <div class="netgsm-title">
            <h1>NetGSM SMS Yönetimi
                <?php if ($config['test_mode']): ?>
                    <span class="test-mode-badge">TEST MODU</span>
                <?php endif; ?>
            </h1>
            <p>Müşterilerinize SMS bildirimleri gönderin</p>
        </div>
    </div>

    <div class="netgsm-grid">
        <!-- Ayarlar -->
        <div class="sms-card">
            <div class="sms-card-header settings">
                <span>⚙️</span>
                <h3>API Ayarları</h3>
            </div>
            <div class="sms-card-body">
                <form method="POST">
                    <div class="form-group">
                        <label>Kullanıcı Kodu (Abone No)</label>
                        <input type="text" name="usercode" value="<?= htmlspecialchars($config['usercode']) ?>"
                            placeholder="850XXXXXXX">
                    </div>
                    <div class="form-group">
                        <label>API Şifresi</label>
                        <input type="password" name="password" value="<?= htmlspecialchars($config['password']) ?>"
                            placeholder="••••••••">
                    </div>
                    <div class="form-group">
                        <label>Mesaj Başlığı (Sender ID)</label>
                        <input type="text" name="msgheader" value="<?= htmlspecialchars($config['msgheader']) ?>"
                            placeholder="ŞİRKET ADI">
                    </div>
                    <div class="form-group">
                        <label class="form-check">
                            <input type="checkbox" name="test_mode" <?= $config['test_mode'] ? 'checked' : '' ?>>
                            <span>Test Modu (SMS gönderilmez)</span>
                        </label>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" name="save_settings" class="btn-netgsm purple">
                            💾 Kaydet
                        </button>
                        <button type="submit" name="test_connection" class="btn-outline-netgsm">
                            🔌 Bağlantı Test
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tek SMS -->
        <div class="sms-card">
            <div class="sms-card-header">
                <span>💬</span>
                <h3>Tek SMS Gönder</h3>
            </div>
            <div class="sms-card-body">
                <form method="POST">
                    <div class="form-group">
                        <label>Telefon Numarası</label>
                        <input type="tel" name="phone" placeholder="05XX XXX XX XX" required>
                    </div>
                    <div class="form-group">
                        <label>Mesaj</label>
                        <textarea name="message" id="singleMessage" placeholder="Mesajınızı yazın..." required
                            maxlength="918" oninput="updateCharCount(this, 'singleCounter')"></textarea>
                        <div class="char-counter"><span id="singleCounter">0</span>/918 karakter</div>
                    </div>
                    <button type="submit" name="send_single" class="btn-netgsm">
                        📤 Gönder
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Toplu SMS -->
    <div class="sms-card" style="margin-top: 25px;">
        <div class="sms-card-header bulk">
            <span>📢</span>
            <h3>Toplu SMS Gönder</h3>
        </div>
        <div class="sms-card-body">
            <div class="stats-row">
                <div class="stat-box">
                    <div class="value">
                        <?= $totalClients ?>
                    </div>
                    <div class="label">Toplam Müşteri</div>
                </div>
                <div class="stat-box">
                    <div class="value">
                        <?= $activeClients ?>
                    </div>
                    <div class="label">Aktif Müşteri</div>
                </div>
                <div class="stat-box">
                    <div class="value">
                        <?= $totalClients - $activeClients ?>
                    </div>
                    <div class="label">Pasif Müşteri</div>
                </div>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label>Alıcı Grubu</label>
                    <select name="client_filter">
                        <option value="all">Tüm Müşteriler (
                            <?= $totalClients ?>)
                        </option>
                        <option value="active">Sadece Aktif Müşteriler (
                            <?= $activeClients ?>)
                        </option>
                        <option value="inactive">Sadece Pasif Müşteriler (
                            <?= $totalClients - $activeClients ?>)
                        </option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mesaj</label>
                    <textarea name="bulk_message" id="bulkMessage" placeholder="Tüm müşterilere gönderilecek mesaj..."
                        required maxlength="918" oninput="updateCharCount(this, 'bulkCounter')"></textarea>
                    <div class="char-counter"><span id="bulkCounter">0</span>/918 karakter</div>
                </div>
                <button type="submit" name="send_bulk" class="btn-netgsm orange"
                    onclick="return confirm('Toplu SMS göndermek istediğinizden emin misiniz?');">
                    📢 Toplu Gönder
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    function updateCharCount(textarea, counterId) {
        document.getElementById(counterId).textContent = textarea.value.length;
    }
</script>

<?php include WHMVM_ROOT . '/admin/includes/footer.php'; ?>