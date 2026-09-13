<?php
/**
 * WHMVM - Admin Sunucu Düzenleme
 */
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/WHMApi.php';
session_name(SESSION_NAME);
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$serverId = (int)($_GET['id'] ?? 0);

if (!$serverId) {
    header('Location: servers.php');
    exit;
}

$server = Database::fetch("SELECT * FROM servers WHERE id = ?", [$serverId]);

if (!$server) {
    header('Location: servers.php');
    exit;
}

$pageTitle = 'Sunucu Düzenle - ' . htmlspecialchars($server['name']);
$currentPage = 'servers';

$message = '';
$messageType = '';

// Güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update':
                Database::query("
                    UPDATE servers SET 
                        name = ?,
                        hostname = ?,
                        ip_address = ?,
                        port = ?,
                        username = ?,
                        password = CASE WHEN ? != '' THEN ? ELSE password END,
                        api_token = CASE WHEN ? != '' THEN ? ELSE api_token END,
                        module = ?,
                        max_accounts = ?,
                        is_active = ?,
                        secure = ?,
                        notes = ?
                    WHERE id = ?
                ", [
                    $_POST['name'],
                    $_POST['hostname'],
                    $_POST['ip_address'],
                    (int)($_POST['port'] ?? 22) ?: 22,
                    $_POST['username'] ?? '',
                    $_POST['password'] ?? '', $_POST['password'] ?? '',
                    $_POST['api_token'] ?? '', $_POST['api_token'] ?? '',
                    $_POST['module'] ?? 'cpanel',
                    (int)($_POST['max_accounts'] ?? 0) ?: null,
                    isset($_POST['is_active']) ? 1 : 0,
                    isset($_POST['secure']) ? 1 : 0,
                    $_POST['notes'] ?? '',
                    $serverId
                ]);
                
                // Debug: Kaydedilen değerleri kontrol et
                error_log("API Token saved: " . ($_POST['api_token'] ?? 'empty'));
                
                $message = 'Sunucu başarıyla güncellendi.';
                $messageType = 'success';
                
                // Sunucu bilgilerini yeniden çek
                $server = Database::fetch("SELECT * FROM servers WHERE id = ?", [$serverId]);
                break;
                
            case 'test':
                header('Content-Type: application/json');
                $result = ServerApi::testConnection($server);
                echo json_encode($result);
                exit;
                
            case 'accounts':
                header('Content-Type: application/json');
                try {
                    $count = ServerApi::getAccountCount($server);
                    echo json_encode(['success' => true, 'accounts' => $count]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                exit;
        }
    }
}

// Sunucudaki hizmetler
$services = Database::fetchAll("
    SELECT s.*, p.name as product_name, c.first_name, c.last_name, c.email
    FROM services s
    LEFT JOIN products p ON s.product_id = p.id
    LEFT JOIN clients c ON s.client_id = c.id
    WHERE s.server_id = ?
    ORDER BY s.created_at DESC
    LIMIT 20
", [$serverId]);

$activeServices = count(array_filter($services, fn($s) => $s['status'] === 'active'));

include 'includes/header.php';
?>

<style>
:root {
    --primary: #3b82f6;
    --primary-dark: #2563eb;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --dark: #1e293b;
    --gray: #64748b;
    --light: #f1f5f9;
    --white: #ffffff;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 20px;
}

.page-header h1 {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 12px;
}

.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: var(--light);
    color: var(--dark);
    border-radius: 10px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
}

.back-btn:hover {
    background: var(--dark);
    color: white;
}

.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert.success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #059669;
}

.alert.error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #dc2626;
}

.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
}

@media (max-width: 1200px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}

.card {
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    overflow: hidden;
}

.card-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--light);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-body {
    padding: 24px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

@media (max-width: 600px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: var(--dark);
    margin-bottom: 8px;
}

.form-group label i {
    color: var(--primary);
    width: 20px;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid var(--light);
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.2s;
    background: var(--white);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-control::placeholder {
    color: var(--gray);
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: var(--light);
    border-radius: 10px;
    cursor: pointer;
}

.checkbox-group input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.checkbox-group label {
    margin: 0;
    cursor: pointer;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #059669);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger), #dc2626);
    color: white;
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
}

.btn-outline {
    background: transparent;
    border: 2px solid var(--light);
    color: var(--dark);
}

.btn-outline:hover {
    background: var(--light);
}

/* Server Status Card */
.server-status-card {
    text-align: center;
    padding: 30px;
    background: linear-gradient(135deg, var(--dark), #334155);
    color: white;
    border-radius: 16px;
    margin-bottom: 24px;
}

.server-icon-big {
    width: 80px;
    height: 80px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 36px;
}

.server-status-card h2 {
    font-size: 24px;
    margin-bottom: 8px;
}

.server-status-card p {
    opacity: 0.7;
    margin-bottom: 20px;
}

.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
}

.status-indicator.online {
    background: rgba(16, 185, 129, 0.2);
    color: #6ee7b7;
}

.status-indicator.offline {
    background: rgba(239, 68, 68, 0.2);
    color: #fca5a5;
}

.status-indicator.unknown {
    background: rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.7);
}

/* Info Box */
.info-box {
    background: var(--light);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
}

.info-box-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.info-box-row:last-child {
    border-bottom: none;
}

.info-box-row .label {
    color: var(--gray);
    font-size: 13px;
}

.info-box-row .value {
    font-weight: 600;
    color: var(--dark);
}

/* Test Result */
.test-result {
    padding: 20px;
    border-radius: 12px;
    margin-top: 16px;
    display: none;
}

.test-result.success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.test-result.error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
}

/* Services Table */
.services-table {
    width: 100%;
    border-collapse: collapse;
}

.services-table th {
    text-align: left;
    padding: 12px 16px;
    background: var(--light);
    font-size: 12px;
    text-transform: uppercase;
    color: var(--gray);
    font-weight: 600;
}

.services-table td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--light);
    font-size: 14px;
}

.services-table tr:hover td {
    background: rgba(59, 130, 246, 0.02);
}

.service-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.service-status.active {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
}

.service-status.pending {
    background: rgba(245, 158, 11, 0.1);
    color: #d97706;
}

.service-status.suspended {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: var(--gray);
}

.empty-state i {
    font-size: 48px;
    opacity: 0.3;
    margin-bottom: 16px;
}

/* Actions */
.card-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.module-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    background: rgba(59, 130, 246, 0.1);
    color: var(--primary);
}
</style>

<div class="page-header">
    <h1>
        <i class="fas fa-server" style="color: var(--primary);"></i>
        <?= htmlspecialchars($server['name']) ?>
    </h1>
    <a href="servers.php" class="back-btn">
        <i class="fas fa-arrow-left"></i>
        Sunuculara Dön
    </a>
</div>

<?php if ($message): ?>
<div class="alert <?= $messageType ?>">
    <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
    <?= $message ?>
</div>
<?php endif; ?>

<div class="content-grid">
    <!-- Sol Kolon - Form -->
    <div>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-edit"></i> Sunucu Bilgileri</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update">
                    
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Sunucu Adı</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($server['name']) ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-globe"></i> Hostname</label>
                            <input type="text" name="hostname" class="form-control" value="<?= htmlspecialchars($server['hostname']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-network-wired"></i> IP Adresi</label>
                            <input type="text" name="ip_address" class="form-control" value="<?= htmlspecialchars($server['ip_address']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-plug"></i> Port</label>
                            <input type="number" name="port" class="form-control" value="<?= $server['port'] ?: 22 ?>">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-puzzle-piece"></i> Modül</label>
                            <select name="module" class="form-control">
                                <option value="cpanel" <?= $server['module'] === 'cpanel' ? 'selected' : '' ?>>cPanel/WHM</option>
                                <option value="plesk" <?= $server['module'] === 'plesk' ? 'selected' : '' ?>>Plesk</option>
                                <option value="directadmin" <?= $server['module'] === 'directadmin' ? 'selected' : '' ?>>DirectAdmin</option>
                                <option value="virtualmin" <?= $server['module'] === 'virtualmin' ? 'selected' : '' ?>>Virtualmin</option>
                                <option value="proxmox" <?= $server['module'] === 'proxmox' ? 'selected' : '' ?>>Proxmox VE</option>
                                <option value="vmware" <?= $server['module'] === 'vmware' ? 'selected' : '' ?>>VMware</option>
                                <option value="custom" <?= $server['module'] === 'custom' ? 'selected' : '' ?>>Özel</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Kullanıcı Adı</label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($server['username'] ?? '') ?>" placeholder="root">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-key"></i> Şifre (değiştirmek için doldurun)</label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-fingerprint"></i> API Token (değiştirmek için doldurun)</label>
                        <input type="password" name="api_token" class="form-control" placeholder="<?= !empty($server['api_token']) ? '••••••••• (mevcut token kayıtlı)' : 'WHM API Token veya benzeri' ?>">
                        <?php if (!empty($server['api_token'])): ?>
                        <small style="color: var(--success); margin-top: 6px; display: block;">
                            <i class="fas fa-check-circle"></i> API Token kayıtlı (<?= strlen($server['api_token']) ?> karakter)
                        </small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-users"></i> Maksimum Hesap Sayısı</label>
                        <input type="number" name="max_accounts" class="form-control" value="<?= $server['max_accounts'] ?? '' ?>" placeholder="Sınırsız için boş bırakın">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i> Notlar</label>
                        <textarea name="notes" class="form-control" placeholder="Sunucu hakkında notlar..."><?= htmlspecialchars($server['notes'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_active" id="is_active" <?= $server['is_active'] ? 'checked' : '' ?>>
                            <label for="is_active">
                                <i class="fas fa-power-off"></i> Sunucu Aktif
                            </label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="secure" id="secure" <?= ($server['secure'] ?? 1) ? 'checked' : '' ?>>
                            <label for="secure">
                                <i class="fas fa-lock"></i> SSL/TLS Kullan
                            </label>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px; display: flex; gap: 12px; justify-content: flex-end;">
                        <a href="servers.php" class="btn btn-outline">İptal</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Değişiklikleri Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Sunucudaki Hizmetler -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <h3><i class="fas fa-cubes"></i> Sunucudaki Hizmetler</h3>
                <span class="module-badge"><?= count($services) ?> Hizmet</span>
            </div>
            <?php if (empty($services)): ?>
            <div class="card-body">
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>Bu sunucuda henüz hizmet bulunmuyor.</p>
                </div>
            </div>
            <?php else: ?>
            <table class="services-table">
                <thead>
                    <tr>
                        <th>Ürün</th>
                        <th>Müşteri</th>
                        <th>Domain</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($service['product_name'] ?? 'Bilinmeyen') ?></strong>
                        </td>
                        <td>
                            <?= htmlspecialchars($service['first_name'] . ' ' . $service['last_name']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($service['domain'] ?? '-') ?>
                        </td>
                        <td>
                            <?php
                            $statusClass = match($service['status']) {
                                'active' => 'active',
                                'pending' => 'pending',
                                default => 'suspended'
                            };
                            $statusText = match($service['status']) {
                                'active' => 'Aktif',
                                'pending' => 'Beklemede',
                                'suspended' => 'Askıda',
                                'terminated' => 'Sonlandırıldı',
                                'cancelled' => 'İptal',
                                default => $service['status']
                            };
                            ?>
                            <span class="service-status <?= $statusClass ?>"><?= $statusText ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Sağ Kolon - Status & Info -->
    <div>
        <!-- Status Card -->
        <div class="server-status-card">
            <div class="server-icon-big">
                <i class="fas fa-server"></i>
            </div>
            <h2><?= htmlspecialchars($server['name']) ?></h2>
            <p><?= htmlspecialchars($server['hostname']) ?></p>
            
            <?php
            $lastCheckResult = $server['last_check_result'] ?? '';
            $statusClass = match($lastCheckResult) {
                'online' => 'online',
                'offline' => 'offline',
                default => 'unknown'
            };
            $statusText = match($lastCheckResult) {
                'online' => 'Çevrimiçi',
                'offline' => 'Çevrimdışı',
                default => 'Bilinmiyor'
            };
            ?>
            <span class="status-indicator <?= $statusClass ?>">
                <i class="fas fa-circle"></i>
                <?= $statusText ?>
            </span>
        </div>
        
        <!-- Quick Info -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Bilgiler</h3>
            </div>
            <div class="card-body">
                <div class="info-box">
                    <div class="info-box-row">
                        <span class="label">IP Adresi</span>
                        <span class="value"><?= htmlspecialchars($server['ip_address']) ?></span>
                    </div>
                    <div class="info-box-row">
                        <span class="label">Port</span>
                        <span class="value"><?= $server['port'] ?: 22 ?></span>
                    </div>
                    <div class="info-box-row">
                        <span class="label">Modül</span>
                        <span class="value"><?= ucfirst($server['module']) ?></span>
                    </div>
                    <div class="info-box-row">
                        <span class="label">Aktif Hizmetler</span>
                        <span class="value"><?= $activeServices ?></span>
                    </div>
                    <div class="info-box-row">
                        <span class="label">Maks. Hesap</span>
                        <span class="value"><?= $server['max_accounts'] ?: '∞' ?></span>
                    </div>
                    <div class="info-box-row">
                        <span class="label">Son Kontrol</span>
                        <span class="value"><?= $server['last_check'] ? date('d.m.Y H:i', strtotime($server['last_check'])) : '-' ?></span>
                    </div>
                </div>
                
                <div class="card-actions">
                    <button type="button" onclick="testConnection()" class="btn btn-success" id="testBtn">
                        <i class="fas fa-wifi"></i> Bağlantı Test
                    </button>
                    <button type="button" onclick="fetchAccounts()" class="btn btn-primary" id="accountsBtn">
                        <i class="fas fa-cloud-download-alt"></i> Hesap Sayısı
                    </button>
                </div>
                
                <div id="testResult" class="test-result"></div>
            </div>
        </div>
        
        <!-- Danger Zone -->
        <div class="card" style="margin-top: 24px; border: 1px solid rgba(239, 68, 68, 0.3);">
            <div class="card-header" style="background: rgba(239, 68, 68, 0.05);">
                <h3 style="color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> Tehlikeli Bölge</h3>
            </div>
            <div class="card-body">
                <p style="color: var(--gray); margin-bottom: 16px;">Bu sunucuyu silmek, sunucu üzerindeki hizmetlerin atanmamış kalmasına neden olacaktır.</p>
                <a href="servers.php?delete=<?= $serverId ?>" class="btn btn-danger" onclick="return confirm('Bu sunucuyu silmek istediğinizden emin misiniz?')">
                    <i class="fas fa-trash"></i> Sunucuyu Sil
                </a>
            </div>
        </div>
    </div>
</div>

<script>
async function testConnection() {
    const btn = document.getElementById('testBtn');
    const resultDiv = document.getElementById('testResult');
    const originalHtml = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Test ediliyor...';
    btn.disabled = true;
    
    try {
        const response = await fetch('server-test.php?server_id=<?= $serverId ?>&action=test');
        const result = await response.json();
        
        resultDiv.style.display = 'block';
        
        if (result.success) {
            resultDiv.className = 'test-result success';
            resultDiv.innerHTML = `
                <div style="display: flex; align-items: center; gap: 12px; color: #059669;">
                    <i class="fas fa-check-circle" style="font-size: 24px;"></i>
                    <div>
                        <strong>Bağlantı Başarılı!</strong><br>
                        <small>Yanıt: ${result.ping || result.response_time}ms</small>
                        ${result.accounts !== null ? `<br><small>API Hesap: ${result.accounts}</small>` : ''}
                    </div>
                </div>
            `;
        } else {
            resultDiv.className = 'test-result error';
            resultDiv.innerHTML = `
                <div style="display: flex; align-items: center; gap: 12px; color: #dc2626;">
                    <i class="fas fa-times-circle" style="font-size: 24px;"></i>
                    <div>
                        <strong>Bağlantı Başarısız!</strong><br>
                        <small>${result.message}</small>
                    </div>
                </div>
            `;
        }
    } catch (error) {
        resultDiv.style.display = 'block';
        resultDiv.className = 'test-result error';
        resultDiv.innerHTML = `
            <div style="display: flex; align-items: center; gap: 12px; color: #dc2626;">
                <i class="fas fa-exclamation-triangle" style="font-size: 24px;"></i>
                <div>
                    <strong>Hata!</strong><br>
                    <small>${error.message}</small>
                </div>
            </div>
        `;
    }
    
    btn.innerHTML = originalHtml;
    btn.disabled = false;
}

async function fetchAccounts() {
    const btn = document.getElementById('accountsBtn');
    const resultDiv = document.getElementById('testResult');
    const originalHtml = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Çekiliyor...';
    btn.disabled = true;
    
    try {
        const response = await fetch('server-test.php?server_id=<?= $serverId ?>&action=accounts');
        const result = await response.json();
        
        resultDiv.style.display = 'block';
        
        if (result.success) {
            resultDiv.className = 'test-result success';
            resultDiv.innerHTML = `
                <div style="display: flex; align-items: center; gap: 12px; color: #059669;">
                    <i class="fas fa-users" style="font-size: 24px;"></i>
                    <div>
                        <strong>Hesap Sayısı (API)</strong><br>
                        <span style="font-size: 28px; font-weight: 700;">${result.accounts}</span> hesap
                    </div>
                </div>
            `;
        } else {
            resultDiv.className = 'test-result error';
            resultDiv.innerHTML = `
                <div style="display: flex; align-items: center; gap: 12px; color: #dc2626;">
                    <i class="fas fa-times-circle" style="font-size: 24px;"></i>
                    <div>
                        <strong>API Hatası</strong><br>
                        <small>${result.message || 'Hesap sayısı alınamadı'}</small>
                    </div>
                </div>
            `;
        }
    } catch (error) {
        resultDiv.style.display = 'block';
        resultDiv.className = 'test-result error';
        resultDiv.innerHTML = `
            <div style="display: flex; align-items: center; gap: 12px; color: #dc2626;">
                <i class="fas fa-exclamation-triangle" style="font-size: 24px;"></i>
                <div>
                    <strong>Hata!</strong><br>
                    <small>${error.message}</small>
                </div>
            </div>
        `;
    }
    
    btn.innerHTML = originalHtml;
    btn.disabled = false;
}
</script>

<?php include 'includes/footer.php'; ?>

