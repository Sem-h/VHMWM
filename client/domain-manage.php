<?php
/**
 * WHMVM - Domain Yönetimi
 * Ultra Premium UI Redesign
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

if (!isset($_SESSION['client_id'])) {
    header('Location: login.php');
    exit;
}

$domainId = (int)($_GET['id'] ?? 0);
$domain = Database::fetch("SELECT * FROM domains WHERE id = ? AND client_id = ?", [$domainId, $_SESSION['client_id']]);

if (!$domain) {
    header('Location: domains.php');
    exit;
}

$pageTitle = $domain['domain'] . ' - Yönetim';
$currentPage = 'domains';
$activeTab = $_GET['tab'] ?? 'overview';
$message = '';
$error = '';

// API & Logic (Aynı kodlar korunuyor)
$api = null;
$apiActive = false;
$domainDetails = [];
$contactDetails = [];

$settings = Database::fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_group = 'integrations'");
$config = [];
foreach ($settings as $s) { $config[$s['setting_key']] = $s['setting_value']; }

if (($config['domainname_active'] ?? '0') === '1') {
    try {
        require_once dirname(__DIR__) . '/modules/registrars/domainnameapi/DomainNameAPI.php';
        $api = new DomainNameAPI($config['domainname_username'] ?? '', $config['domainname_password'] ?? '', ($config['domainname_test_mode'] ?? '0') === '1');
        $apiActive = true;
    } catch (Exception $e) { $error = "API Hatası: " . $e->getMessage(); }
}

// POST İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $apiActive) {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'toggle_lock') {
            $targetState = $_POST['new_status'] === 'on';
            $resp = $targetState ? $api->enableTheftProtectionLock($domain['domain']) : $api->disableTheftProtectionLock($domain['domain']);
            if (isset($resp['result']) && $resp['result'] === 'OK') $message = "Transfer kilidi " . ($targetState ? "kilitlendi." : "açıldı.");
            else throw new Exception($resp['error']['Message'] ?? 'Hata');
        }
        elseif ($action === 'add_child_ns') {
            $ns = trim($_POST['ns_prefix'] ?? '') . '.' . $domain['domain'];
            $ip = trim($_POST['ns_ip'] ?? '');
            $resp = $api->addChildNameServer($domain['domain'], $ns, $ip);
            if (isset($resp['result']) && $resp['result'] === 'OK') { $message = "Özel NS ($ns) eklendi."; $activeTab = 'childns'; }
            else throw new Exception($resp['error']['Message'] ?? 'Hata');
        }
        elseif ($action === 'delete_child_ns') {
            $ns = trim($_POST['ns_name'] ?? '');
            $resp = $api->deleteChildNameServer($domain['domain'], $ns);
            if (isset($resp['result']) && $resp['result'] === 'OK') { $message = "Özel NS silindi."; $activeTab = 'childns'; }
            else throw new Exception($resp['error']['Message'] ?? 'Hata');
        }
        elseif ($action === 'update_contact') {
            $contactData = array_intersect_key($_POST, array_flip(['FirstName','LastName','Company','AddressLine1','City','State','ZipCode','Country','PhoneCountryCode','Phone','Email']));
            $contacts = ["Administrative" => $contactData, "Billing" => $contactData, "Technical" => $contactData, "Registrant" => $contactData];
            $resp = $api->saveContacts($domain['domain'], $contacts);
            if (isset($resp['result']) && $resp['result'] === 'OK') { $message = "Whois güncellendi."; $activeTab = 'whois'; }
            else throw new Exception($resp['error']['Message'] ?? 'Hata');
        }
        elseif ($action === 'update_ns') {
            $nsList = array_values(array_filter([$_POST['ns1'] ?? '', $_POST['ns2'] ?? '', $_POST['ns3'] ?? '', $_POST['ns4'] ?? '']));
            if (count($nsList) >= 2) {
                $resp = $api->modifyNameServer($domain['domain'], $nsList);
                if (isset($resp['result']) && $resp['result'] === 'OK') { $message = "DNS güncellendi."; $activeTab = 'dns'; }
                else throw new Exception($resp['error']['Message'] ?? 'Hata');
            } else throw new Exception("En az 2 NS gerekli.");
        }
        elseif ($action === 'toggle_privacy') {
            $targetState = $_POST['new_status'] === 'on';
            $resp = $api->modifyPrivacyProtectionStatus($domain['domain'], $targetState);
            if (isset($resp['result']) && $resp['result'] === 'OK') { $message = "Gizlilik " . ($targetState ? "açıldı." : "kapatıldı."); $activeTab = 'privacy'; }
            else throw new Exception($resp['error']['Message'] ?? 'Hata');
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Veri Çekme
if ($apiActive) {
    try {
        $details = $api->getDetails($domain['domain']);
        if (isset($details['result']) && $details['result'] === 'OK') $domainDetails = $details['data'];
        
        if ($activeTab === 'whois' || $activeTab === 'overview') {
            $cResp = $api->getContacts($domain['domain']);
            if (isset($cResp['result']) && $cResp['result'] === 'OK') $contactDetails = $cResp['data']['contacts']['Registrant'] ?? [];
        }
    } catch (Exception $e) {}
}

$isLocked = ($domainDetails['LockStatus'] ?? 'false') === 'true';
$isPrivacy = ($domainDetails['PrivacyProtectionStatus'] ?? 'false') === 'true';
$nsValues = $domainDetails['NameServers'] ?? [];

include 'includes/header.php';
?>

<style>
/* --- ULTRA PREMIUM DESIGN --- */
:root {
    --bg-dark: #0f1115;
    --card-bg: #181b21;
    --card-border: rgba(255, 255, 255, 0.06);
    --primary: #2474f5;
    --primary-glow: rgba(36, 116, 245, 0.5);
    --text-main: #ffffff;
    --text-muted: #9ca3af;
}

/* Tab Bar */
.tabs-container {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    padding: 6px;
    border-radius: 16px;
    display: inline-flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-bottom: 30px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.nav-tab {
    padding: 10px 20px;
    border-radius: 12px;
    color: var(--text-muted);
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    border: 1px solid transparent;
}

.nav-tab:hover {
    color: white;
    background: rgba(255,255,255,0.03);
}

.nav-tab.active {
    background: rgba(36, 116, 245, 0.1);
    color: var(--primary);
    border-color: rgba(36, 116, 245, 0.2);
    box-shadow: 0 0 15px rgba(36, 116, 245, 0.1);
}

.nav-tab i { font-size: 16px; }

/* Content Cards */
.feature-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 20px;
    padding: 30px;
    position: relative;
    overflow: hidden;
    transition: transform 0.3s;
}

.feature-card:hover { /* transform: translateY(-3px); */ border-color: rgba(255,255,255,0.1); }

/* Header Info */
.domain-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--card-border);
}

.domain-title {
    font-size: 32px;
    font-weight: 700;
    background: linear-gradient(135deg, #fff 0%, #a5b4fc 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

/* Switch */
.switch-container {
    display: flex;
    align-items: center;
    gap: 15px;
    background: rgba(0,0,0,0.2);
    padding: 15px;
    border-radius: 14px;
    border: 1px solid var(--card-border);
}

.toggle-switch-ios {
    position: relative;
    width: 50px;
    height: 28px;
    appearance: none;
    background: #374151;
    border-radius: 100px;
    cursor: pointer;
    transition: 0.3s;
    outline: none;
}
.toggle-switch-ios::after {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 22px;
    height: 22px;
    background: white;
    border-radius: 50%;
    transition: 0.3s;
    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
}
.toggle-switch-ios:checked { background: #10b981; }
.toggle-switch-ios:checked::after { transform: translateX(22px); }

/* Inputs & Buttons */
.prem-input {
    width: 100%;
    background: #111316;
    border: 1px solid var(--card-border);
    padding: 14px 18px;
    border-radius: 12px;
    color: white;
    outline: none;
    transition: 0.3s;
    font-size: 14px;
}
.prem-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(36, 116, 245, 0.15); }

.prem-btn {
    background: var(--primary);
    color: white;
    border: none;
    padding: 14px 28px;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(36, 116, 245, 0.3);
    transition: 0.3s;
}
.prem-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(36, 116, 245, 0.4); }

.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.status-box {
    background: linear-gradient(145deg, #1d2129, #16191f);
    padding: 20px;
    border-radius: 18px;
    border: 1px solid var(--card-border);
    display: flex;
    align-items: center;
    gap: 15px;
}

.status-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    background: rgba(255,255,255,0.05);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
</style>

<div class="card" style="background: transparent; box-shadow: none; border: none; padding: 0;">
    
    <div class="domain-header">
        <div>
            <a href="domains.php" style="color: var(--text-muted); font-size: 13px; text-decoration: none; margin-bottom: 5px; display: block;">
                <i class="fas fa-arrow-left"></i> Domain Listesine Dön
            </a>
            <h1 class="domain-title"><?= htmlspecialchars($domain['domain']) ?></h1>
        </div>
        <div style="text-align: right;">
            <span class="badge badge-success" style="padding: 8px 12px; font-size: 12px; letter-spacing: 0.5px;">AKTİF</span>
        </div>
    </div>

    <?php if ($message): ?> <div class="alert alert-success mb-4" style="border-radius: 12px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #34d399;"><i class="fas fa-check-circle"></i> <?= $message ?></div> <?php endif; ?>
    <?php if ($error): ?> <div class="alert alert-danger mb-4" style="border-radius: 12px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #f87171;"><i class="fas fa-exclamation-triangle"></i> <?= $error ?></div> <?php endif; ?>

    <!-- TABS -->
    <div class="tabs-container">
        <a href="?id=<?= $domainId ?>&tab=overview" class="nav-tab <?= $activeTab === 'overview' ? 'active' : '' ?>">
            <i class="fas fa-chart-pie"></i> Genel Bakış
        </a>
        <a href="?id=<?= $domainId ?>&tab=dns" class="nav-tab <?= $activeTab === 'dns' ? 'active' : '' ?>">
            <i class="fas fa-globe"></i> DNS
        </a>
        <a href="?id=<?= $domainId ?>&tab=whois" class="nav-tab <?= $activeTab === 'whois' ? 'active' : '' ?>">
            <i class="fas fa-id-card"></i> Whois
        </a>
        <a href="?id=<?= $domainId ?>&tab=childns" class="nav-tab <?= $activeTab === 'childns' ? 'active' : '' ?>">
            <i class="fas fa-server"></i> Özel NS
        </a>
        <a href="?id=<?= $domainId ?>&tab=privacy" class="nav-tab <?= $activeTab === 'privacy' ? 'active' : '' ?>">
            <i class="fas fa-shield-alt"></i> Gizlilik
        </a>
    </div>

    <!-- TAB: OVERVIEW -->
    <?php if ($activeTab === 'overview'): ?>
    <div class="status-grid">
        <!-- Gün Kartı -->
        <div class="status-box">
            <div class="status-icon" style="color: #60a5fa; background: rgba(96, 165, 250, 0.1);">
                <i class="far fa-calendar-alt"></i>
            </div>
            <div>
                <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 2px;">Kalan Süre</div>
                <div style="font-size: 20px; font-weight: 700; color: white;">
                    <?= $domainDetails['Dates']['RemainingDays'] ?? '-' ?> Gün
                </div>
            </div>
        </div>
        <!-- Bitiş Tarihi -->
        <div class="status-box">
            <div class="status-icon" style="color: #f472b6; background: rgba(244, 114, 182, 0.1);">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div>
                <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 2px;">Bitiş Tarihi</div>
                <div style="font-size: 16px; font-weight: 600; color: white;">
                    <?= isset($domainDetails['Dates']['Expiration']) ? date('d.m.Y', strtotime($domainDetails['Dates']['Expiration'])) : '-' ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Transfer Kilidi Kartı -->
        <div class="col-md-6 mb-4">
            <div class="feature-card">
                <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                    <div style="width: 44px; height: 44px; background: rgba(16, 185, 129, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #10b981;">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0; font-size: 18px;">Transfer Kilidi</h4>
                        <p style="margin: 5px 0 0; font-size: 13px; color: var(--text-muted);">Domain transferlerini engeller.</p>
                    </div>
                </div>

                <div class="switch-container">
                    <form method="POST" id="lockForm" style="display: flex; width: 100%; align-items: center; justify-content: space-between;">
                        <input type="hidden" name="action" value="toggle_lock">
                        <input type="hidden" name="new_status" value="<?= $isLocked ? 'off' : 'on' ?>">
                        
                        <div style="font-size: 14px; font-weight: 600; color: <?= $isLocked ? '#10b981' : '#ef4444' ?>">
                            <?= $isLocked ? 'KİLİTLİ (GÜVENLİ)' : 'KİLİTSİZ (GÜVENSİZ)' ?>
                        </div>
                        
                        <!-- Toggle Switch -->
                        <div style="position: relative;">
                            <input type="checkbox" class="toggle-switch-ios" <?= $isLocked ? 'checked' : '' ?> onchange="document.getElementById('lockForm').submit()">
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- EPP Kartı -->
        <div class="col-md-6 mb-4">
            <div class="feature-card">
                <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                    <div style="width: 44px; height: 44px; background: rgba(36, 116, 245, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #2474f5;">
                        <i class="fas fa-key"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0; font-size: 18px;">EPP Transfer Kodu</h4>
                        <p style="margin: 5px 0 0; font-size: 13px; color: var(--text-muted);">Transfer işlemi için gerekli kod.</p>
                    </div>
                </div>

                <div style="position: relative;">
                    <input type="password" value="<?= htmlspecialchars($domainDetails['AuthCode'] ?? '') ?>" id="authCodeInput" class="prem-input" readonly style="font-family: monospace; letter-spacing: 1px; font-size: 16px;">
                    <button onclick="toggleVisibility('authCodeInput')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer;">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div style="margin-top: 10px; font-size: 12px; color: var(--text-muted);">
                    <i class="fas fa-info-circle"></i> Bu kodu kimseyle paylaşmayınız.
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- TAB: DNS -->
    <?php if ($activeTab === 'dns'): ?>
    <div class="feature-card">
        <h3 class="mb-4">Nameserver Yönetimi</h3>
         <form method="POST">
            <input type="hidden" name="action" value="update_ns">
            <div class="row">
                <?php for($i=1; $i<=4; $i++): ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label ms-1">Nameserver <?= $i ?></label>
                    <input name="ns<?= $i ?>" value="<?= htmlspecialchars($nsValues[$i-1] ?? '') ?>" class="prem-input" placeholder="ns<?= $i ?>.example.com">
                </div>
                <?php endfor; ?>
            </div>
            <div class="mt-4 text-end">
                <button type="submit" class="prem-btn">Güncelle</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- TAB: WHOIS -->
    <?php if ($activeTab === 'whois'): ?>
    <div class="feature-card">
        <h3 class="mb-4">Whois Bilgileri</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update_contact">
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Ad</label><input name="FirstName" value="<?= htmlspecialchars($contactDetails['FirstName'] ?? '') ?>" class="prem-input"></div>
                <div class="col-md-6 mb-3"><label class="form-label">Soyad</label><input name="LastName" value="<?= htmlspecialchars($contactDetails['LastName'] ?? '') ?>" class="prem-input"></div>
                <div class="col-md-12 mb-3"><label class="form-label">Adres</label><input name="AddressLine1" value="<?= htmlspecialchars($contactDetails['AddressLine1'] ?? '') ?>" class="prem-input"></div>
                <div class="col-md-6 mb-3"><label class="form-label">Şehir</label><input name="City" value="<?= htmlspecialchars($contactDetails['City'] ?? '') ?>" class="prem-input"></div>
                <div class="col-md-6 mb-3"><label class="form-label">Posta Kodu</label><input name="ZipCode" value="<?= htmlspecialchars($contactDetails['ZipCode'] ?? '') ?>" class="prem-input"></div>
                <div class="col-md-6 mb-3"><label class="form-label">Ülke (Kodu)</label><input name="Country" value="<?= htmlspecialchars($contactDetails['Country'] ?? 'TR') ?>" class="prem-input"></div>
                <div class="col-md-6 mb-3"><label class="form-label">Telefon</label><input name="Phone" value="<?= htmlspecialchars($contactDetails['Phone'] ?? '') ?>" class="prem-input"></div>
                <div class="col-md-12 mb-3"><label class="form-label">Email</label><input name="Email" value="<?= htmlspecialchars($contactDetails['Email'] ?? '') ?>" class="prem-input"></div>
                <!-- Hidden Required Fields -->
                <input type="hidden" name="Company" value="<?= htmlspecialchars($contactDetails['Company'] ?? '') ?>">
                <input type="hidden" name="State" value="<?= htmlspecialchars($contactDetails['State'] ?? '') ?>">
                <input type="hidden" name="PhoneCountryCode" value="<?= htmlspecialchars($contactDetails['PhoneCountryCode'] ?? '90') ?>">
            </div>
            <div class="mt-4 text-end"><button type="submit" class="prem-btn">Kaydet</button></div>
        </form>
    </div>
    <?php endif; ?>

    <!-- TAB: CHILD NS & PRIVACY (Basitleştirilmiş) -->
    <?php if ($activeTab === 'childns'): ?>
    <div class="feature-card mb-4">
        <h4>Yeni Child NS</h4>
         <form method="POST" class="d-flex gap-3 mt-3">
            <input type="hidden" name="action" value="add_child_ns">
            <input name="ns_prefix" class="prem-input" placeholder="ns1" style="flex:1">
            <input name="ns_ip" class="prem-input" placeholder="IP Adresi" style="flex:2">
            <button class="prem-btn">Ekle</button>
        </form>
    </div>
    <div class="feature-card">
        <h4>Child NS Sil</h4>
         <form method="POST" class="d-flex gap-3 mt-3">
            <input type="hidden" name="action" value="delete_child_ns">
            <input name="ns_name" class="prem-input" placeholder="ns1.domain.com" style="flex:1">
            <button class="prem-btn" style="background:#ef4444;">Sil</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($activeTab === 'privacy'): ?>
    <div class="feature-card">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3>Whois Gizliliği</h3>
                <p class="text-muted">Kişisel bilgilerinizi gizleyin.</p>
            </div>
            <form method="POST" id="privForm">
                <input type="hidden" name="action" value="toggle_privacy">
                <input type="hidden" name="new_status" value="<?= $isPrivacy ? 'off' : 'on' ?>">
                <input type="checkbox" class="toggle-switch-ios" <?= $isPrivacy ? 'checked' : '' ?> onchange="document.getElementById('privForm').submit()">
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleVisibility(id) {
    const el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
}
</script>

<?php include 'includes/footer.php'; ?>