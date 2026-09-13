<?php
/**
 * Admin Panel - Entegrasyonlar
 * Domain ve Fatura Entegrasyonları Yönetimi
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';

require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

$pageTitle = 'Entegrasyonlar';
$currentPage = 'integrations';

// Mesaj değişkenleri
$success = '';
$error = '';

// Aktif sekme
$activeTab = $_GET['tab'] ?? 'domain';

// Entegrasyon ayarlarını kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['integration_type'] ?? '';

    try {
        if ($type === 'domainname') {
            // Domainname API ayarları
            $settings = [
                'domainname_username' => trim($_POST['domainname_username'] ?? ''),
                'domainname_password' => trim($_POST['domainname_password'] ?? ''),
                'domainname_test_mode' => isset($_POST['domainname_test_mode']) ? '1' : '0',
                'domainname_active' => isset($_POST['domainname_active']) ? '1' : '0'
            ];

            foreach ($settings as $key => $value) {
                $exists = Database::fetch("SELECT id FROM settings WHERE setting_key = ?", [$key]);
                if ($exists) {
                    Database::query("UPDATE settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
                } else {
                    Database::query("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, 'integrations')", [$key, $value]);
                }
            }

            $success = 'Domainname API ayarları kaydedildi.';
            $activeTab = 'domain';

        } elseif ($type === 'metunic') {
            // Metunic API ayarları
            $settings = [
                'metunic_api_url' => trim($_POST['metunic_api_url'] ?? ''),
                'metunic_api_key' => trim($_POST['metunic_api_key'] ?? ''),
                'metunic_username' => trim($_POST['metunic_username'] ?? ''),
                'metunic_password' => trim($_POST['metunic_password'] ?? ''),
                'metunic_test_mode' => isset($_POST['metunic_test_mode']) ? '1' : '0',
                'metunic_active' => isset($_POST['metunic_active']) ? '1' : '0'
            ];

            foreach ($settings as $key => $value) {
                $exists = Database::fetch("SELECT id FROM settings WHERE setting_key = ?", [$key]);
                if ($exists) {
                    Database::query("UPDATE settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
                } else {
                    Database::query("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, 'integrations')", [$key, $value]);
                }
            }

            $success = 'Metunic API ayarları kaydedildi.';
            $activeTab = 'domain';

        } elseif ($type === 'invoice') {
            // Fatura entegrasyon ayarları
            $provider = $_POST['invoice_provider'] ?? '';
            $apiKey = $_POST['invoice_api_key'] ?? '';
            $apiSecret = $_POST['invoice_api_secret'] ?? '';
            $companyVkn = $_POST['invoice_company_vkn'] ?? '';
            $testMode = isset($_POST['invoice_test_mode']) ? '1' : '0';
            $autoCreate = isset($_POST['invoice_auto_create']) ? '1' : '0';

            $settings = [
                'invoice_provider' => $provider,
                'invoice_api_key' => $apiKey,
                'invoice_api_secret' => $apiSecret,
                'invoice_company_vkn' => $companyVkn,
                'invoice_test_mode' => $testMode,
                'invoice_auto_create' => $autoCreate
            ];

            foreach ($settings as $key => $value) {
                $exists = Database::fetch("SELECT id FROM settings WHERE setting_key = ?", [$key]);
                if ($exists) {
                    Database::query("UPDATE settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
                } else {
                    Database::query("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, 'integrations')", [$key, $value]);
                }
            }

            $success = 'Fatura entegrasyon ayarları kaydedildi.';
            $activeTab = 'invoice';

        } elseif ($type === 'payment') {
            // Ödeme entegrasyon ayarları
            $provider = $_POST['payment_provider'] ?? '';
            $merchantId = $_POST['payment_merchant_id'] ?? '';
            $apiKey = $_POST['payment_api_key'] ?? '';
            $apiSecret = $_POST['payment_api_secret'] ?? '';
            $testMode = isset($_POST['payment_test_mode']) ? '1' : '0';
            $installment = isset($_POST['payment_installment']) ? '1' : '0';

            $settings = [
                'payment_provider' => $provider,
                'payment_merchant_id' => $merchantId,
                'payment_api_key' => $apiKey,
                'payment_api_secret' => $apiSecret,
                'payment_test_mode' => $testMode,
                'payment_installment' => $installment
            ];

            foreach ($settings as $key => $value) {
                $exists = Database::fetch("SELECT id FROM settings WHERE setting_key = ?", [$key]);
                if ($exists) {
                    Database::query("UPDATE settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
                } else {
                    Database::query("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, 'integrations')", [$key, $value]);
                }
            }

            $success = 'Ödeme entegrasyon ayarları kaydedildi.';
            $activeTab = 'payment';
        }

    } catch (Exception $e) {
        $error = 'Kayıt hatası: ' . $e->getMessage();
    }
}

// Mevcut ayarları çek
function getSetting($key, $default = '')
{
    try {
        $result = Database::fetch("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
        return $result['setting_value'] ?? $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Domainname API ayarları
$domainnameUsername = getSetting('domainname_username', '');
$domainnamePassword = getSetting('domainname_password', '');
$domainnameTestMode = getSetting('domainname_test_mode', '0');
$domainnameActive = getSetting('domainname_active', '0');

// Metunic API ayarları
$metunicApiUrl = getSetting('metunic_api_url', '');
$metunicApiKey = getSetting('metunic_api_key', '');
$metunicUsername = getSetting('metunic_username', '');
$metunicPassword = getSetting('metunic_password', '');
$metunicTestMode = getSetting('metunic_test_mode', '0');
$metunicActive = getSetting('metunic_active', '0');

// Fatura ayarları
$invoiceProvider = getSetting('invoice_provider', '');
$invoiceApiKey = getSetting('invoice_api_key', '');
$invoiceApiSecret = getSetting('invoice_api_secret', '');
$invoiceCompanyVkn = getSetting('invoice_company_vkn', '');
$invoiceTestMode = getSetting('invoice_test_mode', '0');
$invoiceAutoCreate = getSetting('invoice_auto_create', '0');

// Ödeme ayarları
$paymentProvider = getSetting('payment_provider', '');
$paymentMerchantId = getSetting('payment_merchant_id', '');
$paymentApiKey = getSetting('payment_api_key', '');
$paymentApiSecret = getSetting('payment_api_secret', '');
$paymentTestMode = getSetting('payment_test_mode', '0');
$paymentInstallment = getSetting('payment_installment', '0');

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .tabs {
        display: flex;
        gap: 5px;
        margin-bottom: 0;
        background: white;
        padding: 20px 20px 0;
        border-radius: 16px 16px 0 0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .tab-btn {
        padding: 15px 30px;
        background: #f1f5f9;
        border: none;
        border-radius: 12px 12px 0 0;
        font-size: 14px;
        font-weight: 600;
        color: var(--gray);
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .tab-btn:hover {
        background: #e2e8f0;
        color: var(--dark);
    }

    .tab-btn.active {
        background: var(--primary);
        color: white;
    }

    .tab-btn .icon {
        font-size: 18px;
    }

    .tab-content {
        display: none;
        background: white;
        border-radius: 0 0 16px 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .tab-content.active {
        display: block;
    }

    .tab-content .card-body {
        padding: 30px;
    }

    .integration-header {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--border);
    }

    .integration-icon {
        width: 70px;
        height: 70px;
        background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
    }

    .integration-info h3 {
        font-size: 20px;
        color: var(--dark);
        margin-bottom: 5px;
    }

    .integration-info p {
        color: var(--gray);
        font-size: 14px;
    }

    /* Provider Cards - Accordion Style */
    .provider-cards {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .provider-card {
        background: #f8fafc;
        border: 2px solid var(--border);
        border-radius: 16px;
        overflow: hidden;
        transition: all 0.3s;
    }

    .provider-card.active {
        border-color: var(--primary);
        box-shadow: 0 10px 40px rgba(99, 102, 241, 0.15);
    }

    .provider-card-header {
        display: flex;
        align-items: center;
        gap: 20px;
        padding: 20px 25px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .provider-card-header:hover {
        background: #f1f5f9;
    }

    .provider-card.active .provider-card-header {
        background: rgba(99, 102, 241, 0.05);
    }

    .provider-logo {
        width: 60px;
        height: 60px;
        background: white;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .provider-logo img {
        max-width: 40px;
        max-height: 40px;
        object-fit: contain;
    }

    .provider-info {
        flex: 1;
    }

    .provider-info h4 {
        font-size: 18px;
        color: var(--dark);
        margin-bottom: 5px;
    }

    .provider-info p {
        font-size: 13px;
        color: var(--gray);
    }

    .provider-status {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
    }

    .status-badge.connected {
        background: #d1fae5;
        color: #065f46;
    }

    .status-badge.disconnected {
        background: #fee2e2;
        color: #991b1b;
    }

    .status-badge.test {
        background: #fef3c7;
        color: #92400e;
    }

    .toggle-arrow {
        width: 36px;
        height: 36px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        color: var(--gray);
        transition: transform 0.3s;
    }

    .provider-card.active .toggle-arrow {
        transform: rotate(180deg);
        background: var(--primary);
        color: white;
    }

    .provider-card-body {
        display: none;
        padding: 0 25px 25px;
    }

    .provider-card.active .provider-card-body {
        display: block;
    }

    .api-form {
        background: white;
        border-radius: 12px;
        padding: 25px;
        border: 1px solid var(--border);
    }

    .api-form-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border);
    }

    .api-form-header h5 {
        font-size: 16px;
        color: var(--dark);
    }

    .config-section {
        background: #f8fafc;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .config-section h4 {
        font-size: 14px;
        color: var(--dark);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .switch-group {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 12px 15px;
        background: white;
        border-radius: 10px;
        margin-bottom: 10px;
    }

    .switch-group:last-child {
        margin-bottom: 0;
    }

    .switch-group label {
        flex: 1;
    }

    .switch-group .switch-label {
        font-weight: 600;
        color: var(--dark);
        display: block;
        margin-bottom: 2px;
        font-size: 14px;
    }

    .switch-group .switch-desc {
        font-size: 12px;
        color: var(--gray);
    }

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

    .toggle-switch .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #cbd5e1;
        transition: .3s;
        border-radius: 26px;
    }

    .toggle-switch .slider:before {
        position: absolute;
        content: "";
        height: 20px;
        width: 20px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
    }

    .toggle-switch input:checked+.slider {
        background-color: var(--primary);
    }

    .toggle-switch input:checked+.slider:before {
        transform: translateX(24px);
    }

    .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid var(--border);
    }

    /* Provider Select for Invoice/Payment */
    .provider-select {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
        margin-bottom: 30px;
    }

    .provider-option {
        position: relative;
    }

    .provider-option input {
        position: absolute;
        opacity: 0;
        cursor: pointer;
    }

    .provider-option label {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 20px;
        background: #f8fafc;
        border: 2px solid var(--border);
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .provider-option label:hover {
        border-color: var(--primary);
        background: #f1f5f9;
    }

    .provider-option input:checked+label {
        border-color: var(--primary);
        background: rgba(99, 102, 241, 0.1);
    }

    .provider-option .provider-logo {
        width: 60px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 10px;
        font-size: 28px;
        background: transparent;
        box-shadow: none;
    }

    .provider-option .provider-name {
        font-size: 13px;
        font-weight: 600;
        color: var(--dark);
    }

    @media (max-width: 992px) {
        .provider-select {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 576px) {
        .provider-select {
            grid-template-columns: 1fr;
        }

        .provider-card-header {
            flex-wrap: wrap;
        }

        .provider-status {
            width: 100%;
            margin-top: 10px;
            justify-content: space-between;
        }
    }
</style>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Tabs -->
<div class="tabs">
    <button class="tab-btn <?= $activeTab === 'domain' ? 'active' : '' ?>" onclick="showTab('domain')">
        <span class="icon">🌐</span> Domain Entegrasyonları
    </button>
    <button class="tab-btn <?= $activeTab === 'invoice' ? 'active' : '' ?>" onclick="showTab('invoice')">
        <span class="icon">📄</span> Fatura Entegrasyonları
    </button>
    <button class="tab-btn <?= $activeTab === 'payment' ? 'active' : '' ?>" onclick="showTab('payment')">
        <span class="icon">💳</span> Ödeme Entegrasyonları
    </button>
    <button class="tab-btn <?= $activeTab === 'esxi' ? 'active' : '' ?>" onclick="showTab('esxi')">
        <span class="icon">🖥️</span> ESXi Sunucuları
    </button>
</div>

<!-- Domain Entegrasyonları -->
<div class="tab-content <?= $activeTab === 'domain' ? 'active' : '' ?>" id="tab-domain">
    <div class="card-body">
        <div class="integration-header">
            <div class="integration-icon">🌐</div>
            <div class="integration-info">
                <h3>Domain Entegrasyonları</h3>
                <p>Domain kayıt, transfer ve yenileme işlemleri için API entegrasyonları. Bir sağlayıcı seçerek
                    yapılandırın.</p>
            </div>
        </div>

        <div class="provider-cards">
            <!-- Domainname API -->
            <div class="provider-card <?= !empty($domainnameUsername) ? 'active' : '' ?>" id="card-domainname">
                <div class="provider-card-header" onclick="toggleProviderCard('domainname')">
                    <div class="provider-logo domainname-logo">
                        <svg viewBox="0 0 100 100" width="40" height="40">
                            <defs>
                                <linearGradient id="dnGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:#00d4aa" />
                                    <stop offset="100%" style="stop-color:#00a085" />
                                </linearGradient>
                            </defs>
                            <rect x="10" y="20" width="80" height="60" rx="8" fill="url(#dnGrad)" />
                            <text x="50" y="58" text-anchor="middle" fill="white" font-size="28"
                                font-weight="bold">DN</text>
                        </svg>
                    </div>
                    <div class="provider-info">
                        <h4>Domainname API</h4>
                        <p>Domainname.com.tr domain kayıt ve yönetim API'si</p>
                    </div>
                    <div class="provider-status">
                        <?php if ($domainnameActive === '1' && !empty($domainnameUsername)): ?>
                            <span class="status-badge <?= $domainnameTestMode === '1' ? 'test' : 'connected' ?>">
                                <?= $domainnameTestMode === '1' ? '⚡ Test Modu' : '✓ Aktif' ?>
                            </span>
                        <?php else: ?>
                            <span class="status-badge disconnected">○ Pasif</span>
                        <?php endif; ?>
                        <div class="toggle-arrow">▼</div>
                    </div>
                </div>
                <div class="provider-card-body">
                    <form method="POST" class="api-form">
                        <input type="hidden" name="integration_type" value="domainname">

                        <div class="api-form-header">
                            <span>🔑</span>
                            <h5>Domainname API Bilgileri</h5>
                        </div>

                        <!-- Bakiye Bilgisi Alanı -->
                        <div id="domainname-balance-alert" class="alert alert-success"
                            style="display: none; margin-bottom: 20px; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="font-size: 24px;">💰</div>
                                <div>
                                    <div style="font-size: 12px; opacity: 0.9;">Mevcut Bakiye</div>
                                    <div id="domainname-balance-value" style="font-size: 18px; font-weight: 700;">-
                                    </div>
                                </div>
                            </div>
                            <span class="badge badge-success">✓ Bağlı</span>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Kullanıcı Adı (UserName)</label>
                                <input type="text" name="domainname_username" class="form-control"
                                    value="<?= htmlspecialchars($domainnameUsername) ?>"
                                    placeholder="Domainname kullanıcı adınız">
                            </div>
                            <div class="form-group">
                                <label>Şifre (Password)</label>
                                <input type="password" name="domainname_password" class="form-control"
                                    value="<?= htmlspecialchars($domainnamePassword) ?>"
                                    placeholder="Domainname şifreniz">
                            </div>
                        </div>

                        <div class="config-section">
                            <h4>⚙️ Ayarlar</h4>
                            <div class="switch-group">
                                <label>
                                    <span class="switch-label">Entegrasyonu Aktif Et</span>
                                    <span class="switch-desc">Domain işlemleri için bu API'yi kullan</span>
                                </label>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="domainname_active" value="1" <?= $domainnameActive === '1' ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="switch-group">
                                <label>
                                    <span class="switch-label">Test Modu</span>
                                    <span class="switch-desc">Gerçek işlem yapmadan API'yi test edin</span>
                                </label>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="domainname_test_mode" value="1"
                                        <?= $domainnameTestMode === '1' ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">💾 Kaydet</button>
                            <button type="button" class="btn btn-outline" onclick="testApi('domainname')">🔄 Bağlantıyı
                                Test Et</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Metunic API -->
            <div class="provider-card <?= !empty($metunicApiKey) ? 'active' : '' ?>" id="card-metunic">
                <div class="provider-card-header" onclick="toggleProviderCard('metunic')">
                    <div class="provider-logo metunic-logo">
                        <svg viewBox="0 0 100 100" width="40" height="40">
                            <defs>
                                <linearGradient id="metGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:#6366f1" />
                                    <stop offset="100%" style="stop-color:#8b5cf6" />
                                </linearGradient>
                            </defs>
                            <circle cx="50" cy="50" r="40" fill="url(#metGrad)" />
                            <text x="50" y="58" text-anchor="middle" fill="white" font-size="32"
                                font-weight="bold">M</text>
                        </svg>
                    </div>
                    <div class="provider-info">
                        <h4>Metunic API</h4>
                        <p>Metunic domain ve hosting yönetim API'si</p>
                    </div>
                    <div class="provider-status">
                        <?php if ($metunicActive === '1' && !empty($metunicApiKey)): ?>
                            <span class="status-badge <?= $metunicTestMode === '1' ? 'test' : 'connected' ?>">
                                <?= $metunicTestMode === '1' ? '⚡ Test Modu' : '✓ Aktif' ?>
                            </span>
                        <?php else: ?>
                            <span class="status-badge disconnected">○ Pasif</span>
                        <?php endif; ?>
                        <div class="toggle-arrow">▼</div>
                    </div>
                </div>
                <div class="provider-card-body">
                    <form method="POST" class="api-form">
                        <input type="hidden" name="integration_type" value="metunic">

                        <div class="api-form-header">
                            <span>🔑</span>
                            <h5>Metunic API Bilgileri</h5>
                        </div>

                        <div class="form-group">
                            <label>API URL</label>
                            <input type="url" name="metunic_api_url" class="form-control"
                                value="<?= htmlspecialchars($metunicApiUrl) ?>"
                                placeholder="https://api.metunic.com.tr/v1">
                        </div>

                        <div class="form-group">
                            <label>API Key</label>
                            <input type="text" name="metunic_api_key" class="form-control"
                                value="<?= htmlspecialchars($metunicApiKey) ?>" placeholder="Metunic API Key">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Kullanıcı Adı</label>
                                <input type="text" name="metunic_username" class="form-control"
                                    value="<?= htmlspecialchars($metunicUsername) ?>"
                                    placeholder="Metunic kullanıcı adınız">
                            </div>
                            <div class="form-group">
                                <label>Şifre</label>
                                <input type="password" name="metunic_password" class="form-control"
                                    value="<?= htmlspecialchars($metunicPassword) ?>" placeholder="Metunic şifreniz">
                            </div>
                        </div>

                        <div class="config-section">
                            <h4>⚙️ Ayarlar</h4>
                            <div class="switch-group">
                                <label>
                                    <span class="switch-label">Entegrasyonu Aktif Et</span>
                                    <span class="switch-desc">Domain işlemleri için bu API'yi kullan</span>
                                </label>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="metunic_active" value="1" <?= $metunicActive === '1' ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="switch-group">
                                <label>
                                    <span class="switch-label">Test Modu</span>
                                    <span class="switch-desc">Gerçek işlem yapmadan API'yi test edin</span>
                                </label>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="metunic_test_mode" value="1" <?= $metunicTestMode === '1' ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">💾 Kaydet</button>
                            <button type="button" class="btn btn-outline" onclick="testApi('metunic')">🔄 Bağlantıyı
                                Test Et</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Fatura Entegrasyonları -->
<div class="tab-content <?= $activeTab === 'invoice' ? 'active' : '' ?>" id="tab-invoice">
    <div class="card-body">
        <div class="integration-header">
            <div class="integration-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">📄
            </div>
            <div class="integration-info">
                <h3>Fatura Entegrasyonları</h3>
                <p>E-Fatura, E-Arşiv fatura kesimi için API entegrasyonları</p>
            </div>
            <?php if (!empty($invoiceProvider)): ?>
                <span class="status-badge <?= $invoiceTestMode === '1' ? 'test' : 'connected' ?>">
                    <?= $invoiceTestMode === '1' ? '⚡ Test Modu' : '✓ Bağlı' ?>
                </span>
            <?php else: ?>
                <span class="status-badge disconnected">○ Yapılandırılmadı</span>
            <?php endif; ?>
        </div>

        <form method="POST">
            <input type="hidden" name="integration_type" value="invoice">

            <h4 style="margin-bottom: 15px; color: var(--dark);">📦 Sağlayıcı Seçin</h4>
            <div class="provider-select">
                <div class="provider-option">
                    <input type="radio" name="invoice_provider" value="parasut" id="invoice_parasut"
                        <?= $invoiceProvider === 'parasut' ? 'checked' : '' ?>>
                    <label for="invoice_parasut">
                        <div class="provider-logo">🟢</div>
                        <span class="provider-name">Paraşüt</span>
                    </label>
                </div>
                <div class="provider-option">
                    <input type="radio" name="invoice_provider" value="logo" id="invoice_logo"
                        <?= $invoiceProvider === 'logo' ? 'checked' : '' ?>>
                    <label for="invoice_logo">
                        <div class="provider-logo">🔵</div>
                        <span class="provider-name">Logo</span>
                    </label>
                </div>
                <div class="provider-option">
                    <input type="radio" name="invoice_provider" value="kolaybi" id="invoice_kolaybi"
                        <?= $invoiceProvider === 'kolaybi' ? 'checked' : '' ?>>
                    <label for="invoice_kolaybi">
                        <div class="provider-logo">🟣</div>
                        <span class="provider-name">KolayBi</span>
                    </label>
                </div>
                <div class="provider-option">
                    <input type="radio" name="invoice_provider" value="other" id="invoice_other"
                        <?= $invoiceProvider === 'other' ? 'checked' : '' ?>>
                    <label for="invoice_other">
                        <div class="provider-logo">⚙️</div>
                        <span class="provider-name">Diğer</span>
                    </label>
                </div>
            </div>

            <div class="config-section">
                <h4>🔑 API Bilgileri</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>API Key / Kullanıcı Adı</label>
                        <input type="text" name="invoice_api_key" class="form-control"
                            value="<?= htmlspecialchars($invoiceApiKey) ?>" placeholder="API anahtarınızı girin">
                    </div>
                    <div class="form-group">
                        <label>API Secret / Şifre</label>
                        <input type="password" name="invoice_api_secret" class="form-control"
                            value="<?= htmlspecialchars($invoiceApiSecret) ?>"
                            placeholder="API secret anahtarınızı girin">
                    </div>
                </div>
                <div class="form-group">
                    <label>Firma VKN / TCKN</label>
                    <input type="text" name="invoice_company_vkn" class="form-control"
                        value="<?= htmlspecialchars($invoiceCompanyVkn) ?>"
                        placeholder="Vergi Kimlik Numarası veya TC Kimlik No">
                </div>
            </div>

            <div class="config-section">
                <h4>⚙️ Ayarlar</h4>
                <div class="switch-group">
                    <label>
                        <span class="switch-label">Test Modu</span>
                        <span class="switch-desc">Gerçek fatura kesmeden API'yi test edin</span>
                    </label>
                    <div class="toggle-switch">
                        <input type="checkbox" name="invoice_test_mode" value="1" <?= $invoiceTestMode === '1' ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </div>
                </div>
                <div class="switch-group">
                    <label>
                        <span class="switch-label">Otomatik Fatura Kesimi</span>
                        <span class="switch-desc">Ödeme alındığında otomatik e-fatura/e-arşiv oluştur</span>
                    </label>
                    <div class="toggle-switch">
                        <input type="checkbox" name="invoice_auto_create" value="1" <?= $invoiceAutoCreate === '1' ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">💾 Kaydet</button>
                <button type="button" class="btn btn-outline" onclick="testApi('invoice')">🔄 Bağlantıyı Test
                    Et</button>
            </div>
        </form>
    </div>
</div>

<!-- Ödeme Entegrasyonları -->
<div class="tab-content <?= $activeTab === 'payment' ? 'active' : '' ?>" id="tab-payment">
    <div class="card-body">
        <div class="integration-header">
            <div class="integration-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">💳
            </div>
            <div class="integration-info">
                <h3>Ödeme Entegrasyonları</h3>
                <p>Kredi kartı, sanal pos ve diğer ödeme yöntemleri için API entegrasyonları</p>
            </div>
            <?php if (!empty($paymentProvider)): ?>
                <span class="status-badge <?= $paymentTestMode === '1' ? 'test' : 'connected' ?>">
                    <?= $paymentTestMode === '1' ? '⚡ Test Modu' : '✓ Bağlı' ?>
                </span>
            <?php else: ?>
                <span class="status-badge disconnected">○ Yapılandırılmadı</span>
            <?php endif; ?>
        </div>

        <form method="POST">
            <input type="hidden" name="integration_type" value="payment">

            <h4 style="margin-bottom: 15px; color: var(--dark);">📦 Sağlayıcı Seçin</h4>
            <div class="provider-select">
                <div class="provider-option">
                    <input type="radio" name="payment_provider" value="iyzico" id="payment_iyzico"
                        <?= $paymentProvider === 'iyzico' ? 'checked' : '' ?>>
                    <label for="payment_iyzico">
                        <div class="provider-logo">🔵</div>
                        <span class="provider-name">iyzico</span>
                    </label>
                </div>
                <div class="provider-option">
                    <input type="radio" name="payment_provider" value="paytr" id="payment_paytr"
                        <?= $paymentProvider === 'paytr' ? 'checked' : '' ?>>
                    <label for="payment_paytr">
                        <div class="provider-logo">🟢</div>
                        <span class="provider-name">PayTR</span>
                    </label>
                </div>
                <div class="provider-option">
                    <input type="radio" name="payment_provider" value="param" id="payment_param"
                        <?= $paymentProvider === 'param' ? 'checked' : '' ?>>
                    <label for="payment_param">
                        <div class="provider-logo">🔴</div>
                        <span class="provider-name">Param</span>
                    </label>
                </div>
                <div class="provider-option">
                    <input type="radio" name="payment_provider" value="other" id="payment_other"
                        <?= $paymentProvider === 'other' ? 'checked' : '' ?>>
                    <label for="payment_other">
                        <div class="provider-logo">⚙️</div>
                        <span class="provider-name">Diğer</span>
                    </label>
                </div>
            </div>

            <div class="config-section">
                <h4>🔑 API Bilgileri</h4>
                <div class="form-group">
                    <label>Merchant ID / Mağaza Kodu</label>
                    <input type="text" name="payment_merchant_id" class="form-control"
                        value="<?= htmlspecialchars($paymentMerchantId) ?>" placeholder="Mağaza kodunuzu girin">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>API Key</label>
                        <input type="text" name="payment_api_key" class="form-control"
                            value="<?= htmlspecialchars($paymentApiKey) ?>" placeholder="API anahtarınızı girin">
                    </div>
                    <div class="form-group">
                        <label>API Secret</label>
                        <input type="password" name="payment_api_secret" class="form-control"
                            value="<?= htmlspecialchars($paymentApiSecret) ?>"
                            placeholder="API secret anahtarınızı girin">
                    </div>
                </div>
            </div>

            <div class="config-section">
                <h4>⚙️ Ayarlar</h4>
                <div class="switch-group">
                    <label>
                        <span class="switch-label">Test Modu</span>
                        <span class="switch-desc">Gerçek ödeme almadan API'yi test edin</span>
                    </label>
                    <div class="toggle-switch">
                        <input type="checkbox" name="payment_test_mode" value="1" <?= $paymentTestMode === '1' ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </div>
                </div>
                <div class="switch-group">
                    <label>
                        <span class="switch-label">Taksit Seçenekleri</span>
                        <span class="switch-desc">Müşterilere taksitli ödeme seçeneği sun</span>
                    </label>
                    <div class="toggle-switch">
                        <input type="checkbox" name="payment_installment" value="1" <?= $paymentInstallment === '1' ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">💾 Kaydet</button>
                <button type="button" class="btn btn-outline" onclick="testApi('payment')">🔄 Bağlantıyı Test
                    Et</button>
            </div>
        </form>
    </div>
</div>

<!-- ESXi Sunucuları -->
<div class="tab-content <?= $activeTab === 'esxi' ? 'active' : '' ?>" id="tab-esxi">
    <div class="card-body">
        <div class="integration-header">
            <div class="integration-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">🖥️
            </div>
            <div class="integration-info">
                <h3>ESXi Sunucu Entegrasyonları</h3>
                <p>VMware ESXi sunucularını yönetin, sanal makinelerinizi uzaktan kontrol edin (Başlat, Durdur, Yeniden
                    Başlat)</p>
            </div>
        </div>

        <div class="provider-cards">
            <div class="provider-card active">
                <div class="provider-card-header" style="cursor: default;">
                    <div class="provider-logo" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                        <svg viewBox="0 0 100 100" width="40" height="40">
                            <rect x="15" y="25" width="70" height="50" rx="5" fill="white" opacity="0.9" />
                            <rect x="20" y="30" width="60" height="35" rx="3" fill="#1d4ed8" />
                            <circle cx="50" cy="47" r="12" fill="white" opacity="0.3" />
                            <rect x="35" y="78" width="30" height="5" rx="2" fill="white" opacity="0.7" />
                        </svg>
                    </div>
                    <div class="provider-info">
                        <h4>VMware ESXi</h4>
                        <p>SSH üzerinden ESXi sunucularına bağlanarak sanal makinelerinizi yönetin</p>
                    </div>
                    <div class="provider-status">
                        <span class="status-badge connected">✓ phpseclib Aktif</span>
                    </div>
                </div>
                <div class="provider-card-body" style="display: block;">
                    <div class="api-form">
                        <div class="api-form-header">
                            <span>🖥️</span>
                            <h5>ESXi Sunucu Yönetimi</h5>
                        </div>

                        <div
                            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 25px;">
                            <div
                                style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 20px; text-align: center;">
                                <div style="font-size: 32px; margin-bottom: 10px;">🔌</div>
                                <div style="font-weight: 600; color: #0369a1; margin-bottom: 5px;">SSH Bağlantısı</div>
                                <div style="font-size: 13px; color: #64748b;">Port 22 üzerinden güvenli bağlantı</div>
                            </div>
                            <div
                                style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 20px; text-align: center;">
                                <div style="font-size: 32px; margin-bottom: 10px;">⚡</div>
                                <div style="font-weight: 600; color: #15803d; margin-bottom: 5px;">Güç Kontrolü</div>
                                <div style="font-size: 13px; color: #64748b;">VM'leri açın, kapatın, yeniden başlatın
                                </div>
                            </div>
                            <div
                                style="background: #fdf4ff; border: 1px solid #f5d0fe; border-radius: 12px; padding: 20px; text-align: center;">
                                <div style="font-size: 32px; margin-bottom: 10px;">📊</div>
                                <div style="font-weight: 600; color: #86198f; margin-bottom: 5px;">Durum İzleme</div>
                                <div style="font-size: 13px; color: #64748b;">VM durumlarını gerçek zamanlı görün</div>
                            </div>
                        </div>

                        <div class="config-section">
                            <h4>📋 Özellikler</h4>
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                <li
                                    style="padding: 10px 15px; background: white; border-radius: 8px; margin-bottom: 8px; display: flex; align-items: center; gap: 12px;">
                                    <span style="color: #22c55e;">✓</span> Birden fazla ESXi sunucu desteği
                                </li>
                                <li
                                    style="padding: 10px 15px; background: white; border-radius: 8px; margin-bottom: 8px; display: flex; align-items: center; gap: 12px;">
                                    <span style="color: #22c55e;">✓</span> Müşteri panelinden VM kontrolü
                                </li>
                                <li
                                    style="padding: 10px 15px; background: white; border-radius: 8px; margin-bottom: 8px; display: flex; align-items: center; gap: 12px;">
                                    <span style="color: #22c55e;">✓</span> Hizmetlere VM atama
                                </li>
                                <li
                                    style="padding: 10px 15px; background: white; border-radius: 8px; margin-bottom: 8px; display: flex; align-items: center; gap: 12px;">
                                    <span style="color: #22c55e;">✓</span> İşlem logları ve izleme
                                </li>
                                <li
                                    style="padding: 10px 15px; background: white; border-radius: 8px; display: flex; align-items: center; gap: 12px;">
                                    <span style="color: #22c55e;">✓</span> phpseclib ile güvenli SSH bağlantısı
                                </li>
                            </ul>
                        </div>

                        <div class="form-actions">
                            <a href="esxi-servers.php" class="btn btn-primary" style="text-decoration: none;">
                                🖥️ ESXi Sunucuları Yönet
                            </a>
                            <a href="esxi-test.php" class="btn btn-outline" style="text-decoration: none;">
                                🔧 Bağlantı Testi
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function showTab(tabName) {
        // Tüm tabları gizle
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        // Seçili tabı göster
        document.getElementById('tab-' + tabName).classList.add('active');
        event.target.classList.add('active');

        // URL'yi güncelle
        history.replaceState(null, '', '?tab=' + tabName);
    }

    function toggleProviderCard(provider) {
        const card = document.getElementById('card-' + provider);
        const isActive = card.classList.contains('active');

        // Diğer kartları kapat (opsiyonel - aynı anda sadece bir kart açık olsun istersen)
        // document.querySelectorAll('.provider-card').forEach(c => c.classList.remove('active'));

        // Seçili kartı aç/kapat
        if (isActive) {
            card.classList.remove('active');
        } else {
            card.classList.add('active');
        }
    }

    // event nesnesi inline çağrılarda bazen sorun olabilir, bu yüzden window.event kullanıyoruz
    function testApi(provider) {
        const btn = window.event.target;
        const originalText = btn.innerHTML;

        // Formu bul ve tüm verileri al
        const form = btn.closest('form');
        const formData = form ? new FormData(form) : new FormData();

        // Provider bilgisini ekle (eğer formda yoksa)
        if (!formData.has('provider')) {
            formData.append('provider', provider);
        }

        btn.disabled = true;
        btn.innerHTML = '🔄 Test ediliyor...';

        fetch('ajax/test-connection.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);

                    if (data.success) {
                        // Başarılı ise bakiye alanını göster
                        if (provider === 'domainname' && data.data && data.data.balance) {
                            const balanceAlert = document.getElementById('domainname-balance-alert');
                            const balanceValue = document.getElementById('domainname-balance-value');
                            if (balanceAlert && balanceValue) {
                                balanceValue.textContent = data.data.balance;
                                balanceAlert.style.display = 'flex';
                            }
                        }

                        alert('✅ ' + data.message);
                    } else {
                        // Hata durumunda bakiye alanını gizle
                        const balanceAlert = document.getElementById('domainname-balance-alert');
                        if (balanceAlert) balanceAlert.style.display = 'none';

                        alert('❌ ' + data.message);
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    console.log('Server Response:', text);
                    // Sunucu hatasını göster (HTML taglerini temizle)
                    const cleanText = text.replace(/<[^>]*>/g, '').trim();
                    alert('❌ Sunucu Hatası:\n' + cleanText.substring(0, 300) + '...');
                }
            })
            .catch(error => {
                console.error('API Test Error:', error);
                alert('❌ Ağ hatası oluştu.');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>