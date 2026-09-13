<?php
/**
 * WHMVM - Admin Sunucu Yönetimi
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

$pageTitle = 'Sunucular';
$currentPage = 'servers';

$message = '';
$messageType = 'success';

// İşlem sonrası mesajlar
if (isset($_GET['deleted'])) {
    $message = 'Sunucu başarıyla silindi.';
}
if (isset($_GET['added'])) {
    $message = 'Sunucu başarıyla eklendi.';
}
if (isset($_GET['updated'])) {
    $message = 'Sunucu durumu güncellendi.';
}

// Bağlantı Testi Fonksiyonu
function testServerConnection(string $host, int $port, int $timeout = 5): array {
    $result = [
        'success' => false,
        'ping' => null,
        'port_open' => false,
        'message' => ''
    ];
    
    // Ping testi (sadece response time için)
    $startTime = microtime(true);
    
    // Port kontrolü
    $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
    
    if ($connection) {
        $result['success'] = true;
        $result['port_open'] = true;
        $result['ping'] = round((microtime(true) - $startTime) * 1000, 2);
        $result['message'] = 'Bağlantı başarılı!';
        fclose($connection);
    } else {
        $result['message'] = "Bağlantı hatası: $errstr ($errno)";
    }
    
    return $result;
}

// AJAX Bağlantı Testi
if (isset($_POST['test_connection'])) {
    header('Content-Type: application/json');
    
    $serverId = (int)$_POST['server_id'];
    $server = Database::fetch("SELECT * FROM servers WHERE id = ?", [$serverId]);
    
    if ($server) {
        $host = $server['ip_address'] ?: $server['hostname'];
        $port = (int)$server['port'] ?: 22;
        
        $result = testServerConnection($host, $port);
        
        // Test sonucunu veritabanına kaydet
        Database::query("UPDATE servers SET last_check = NOW(), last_check_result = ? WHERE id = ?", [
            $result['success'] ? 'online' : 'offline',
            $serverId
        ]);
        
        echo json_encode($result);
    } else {
        echo json_encode(['success' => false, 'message' => 'Sunucu bulunamadı']);
    }
    exit;
}

// Silme
/* Durum degistiren islem POST ile gelir; belirtec dogrulanir. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && is_numeric($_POST['delete'])) {
    Guvenlik::zorunlu();
    Database::query("DELETE FROM servers WHERE id = ?", [$_POST['delete']]);
    header('Location: servers.php?deleted=1');
    exit;
}

// Ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    Database::query("INSERT INTO servers (name, hostname, ip_address, port, username, password, module, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())", [
        $_POST['name'],
        $_POST['hostname'],
        $_POST['ip_address'],
        $_POST['port'] ?: 22,
        $_POST['username'] ?? '',
        $_POST['password'] ?? '',
        $_POST['module'] ?? 'cpanel',
        isset($_POST['is_active']) ? 1 : 0
    ]);
    header('Location: servers.php?added=1');
    exit;
}

// Durum güncelleme
if (isset($_POST['toggle_status'])) {
    $serverId = (int)$_POST['server_id'];
    $newStatus = $_POST['new_status'] === '1' ? 1 : 0;
    Database::query("UPDATE servers SET is_active = ? WHERE id = ?", [$newStatus, $serverId]);
    header('Location: servers.php?updated=1');
    exit;
}

$servers = Database::fetchAll("
    SELECT s.*, g.name as group_name,
           (SELECT COUNT(*) FROM services WHERE server_id = s.id AND status = 'active') as active_services
    FROM servers s 
    LEFT JOIN server_groups g ON s.group_id = g.id 
    ORDER BY s.name
");

// İstatistikler
$stats = [
    'total' => count($servers),
    'active' => count(array_filter($servers, fn($s) => $s['is_active'])),
    'online' => count(array_filter($servers, fn($s) => ($s['last_check_result'] ?? '') === 'online')),
    'totalServices' => array_sum(array_column($servers, 'active_services'))
];

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* Page Header */
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

.page-header h1 i { color: var(--primary); }

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--y-yuzey);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-icon.blue { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
.stat-icon.green { background: linear-gradient(135deg, #22c55e, #16a34a); color: white; }
.stat-icon.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; }
.stat-icon.orange { background: linear-gradient(135deg, #f97316, #ea580c); color: white; }

.stat-info h3 {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 4px;
}

.stat-info span {
    font-size: 13px;
    color: var(--gray);
}

/* Server Card */
.server-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 24px;
}

.server-card {
    background: var(--y-yuzey);
    border: 1px solid var(--border);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.3s;
}

.server-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
}

.server-header {
    padding: 24px;
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    position: relative;
}

.server-status-indicator {
    position: absolute;
    top: 20px;
    right: 20px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 3px solid rgba(255,255,255,0.3);
}

.server-status-indicator.online {
    background: #22c55e;
    box-shadow: 0 0 12px rgba(34, 197, 94, 0.6);
    animation: pulse 2s infinite;
}

.server-status-indicator.offline {
    background: #ef4444;
}

.server-status-indicator.unknown {
    background: #64748b;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.server-icon {
    width: 60px;
    height: 60px;
    background: rgba(255,255,255,0.1);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    margin-bottom: 16px;
}

.server-name {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 6px;
}

.server-hostname {
    font-size: 14px;
    opacity: 0.8;
    font-family: 'JetBrains Mono', monospace;
}

.server-body {
    padding: 24px;
}

.server-info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}

.server-info-item {
    padding: 14px;
    background: var(--light);
    border-radius: 12px;
}

.server-info-item label {
    display: block;
    font-size: 11px;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}

.server-info-item span {
    font-size: 15px;
    font-weight: 600;
    color: var(--dark);
}

.server-info-item span.mono {
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
}

/* Fetch Accounts Button */
.fetch-accounts-btn {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.fetch-accounts-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

.fetch-accounts-btn.small {
    padding: 4px 8px;
    font-size: 11px;
    background: var(--gray);
    margin-left: 8px;
}

.fetch-accounts-btn.small:hover {
    background: var(--primary);
}

.api-account-count {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
}

/* Module Badge */
.module-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
}

.module-badge.cpanel { background: rgba(255, 102, 0, 0.1); color: #ff6600; }
.module-badge.plesk { background: rgba(82, 188, 226, 0.1); color: #52bce2; }
.module-badge.directadmin { background: rgba(0, 102, 204, 0.1); color: #0066cc; }
.module-badge.proxmox { background: rgba(230, 121, 0, 0.1); color: #e67900; }
.module-badge.vmware { background: rgba(100, 100, 100, 0.1); color: #646464; }
.module-badge.custom { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.status-badge.active { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
.status-badge.inactive { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

/* Connection Result */
.connection-result {
    padding: 14px;
    border-radius: 12px;
    margin-bottom: 16px;
    display: none;
}

.connection-result.success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #16a34a;
}

.connection-result.error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #dc2626;
}

.connection-result .ping {
    font-weight: 600;
    font-size: 18px;
}

/* Server Actions */
.server-actions {
    display: flex;
    gap: 10px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
}

.server-btn {
    flex: 1;
    padding: 12px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid var(--border);
    background: var(--y-yuzey);
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
    color: var(--dark);
}

.server-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.server-btn.test {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    border: none;
    color: white;
}

.server-btn.test:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3);
}

.server-btn.test.loading {
    opacity: 0.7;
    pointer-events: none;
}

.server-btn.delete:hover {
    border-color: #ef4444;
    color: #ef4444;
    background: rgba(239, 68, 68, 0.05);
}

/* Alert */
.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
}

.alert-success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #16a34a;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 80px 40px;
    background: var(--y-yuzey);
    border: 1px solid var(--border);
    border-radius: 20px;
}

.empty-state .icon {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.3;
}

.empty-state h3 {
    font-size: 20px;
    color: var(--dark);
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--gray);
    margin-bottom: 24px;
}

/* Modal */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal.show, .modal.active {
    display: flex;
}

.modal-content {
    background: var(--y-yuzey);
    border-radius: 20px;
    max-width: 550px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalSlide 0.3s ease;
}

@keyframes modalSlide {
    from { opacity: 0; transform: scale(0.9) translateY(-20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.modal-header {
    padding: 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    font-size: 20px;
    font-weight: 700;
    color: var(--dark);
}

.close-btn {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: none;
    background: var(--light);
    cursor: pointer;
    font-size: 20px;
    color: var(--gray);
    transition: all 0.3s;
}

.close-btn:hover {
    background: #fee2e2;
    color: #ef4444;
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    padding: 20px 24px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.form-group {
    margin-bottom: 20px;
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
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s;
}

.form-control:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

/* Test All Button */
.test-all-btn {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.test-all-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(139, 92, 246, 0.3);
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: 1fr; }
    .server-grid { grid-template-columns: 1fr; }
    .form-row { grid-template-columns: 1fr; }
    .page-header { flex-direction: column; align-items: flex-start; }
}
</style>

<?php if ($message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header">
    <h1><i class="fas fa-server"></i> Sunucu Yönetimi</h1>
    <div style="display: flex; gap: 12px;">
        <button onclick="testAllServers()" class="test-all-btn">
            <i class="fas fa-wifi"></i>
            Tümünü Test Et
        </button>
        <button onclick="fetchAllAccounts()" class="test-all-btn" style="background: linear-gradient(135deg, #10b981, #059669);">
            <i class="fas fa-cloud-download-alt"></i>
            Hesapları Çek
        </button>
        <button onclick="openServerModal('addModal')" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Yeni Sunucu
        </button>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-server"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['total'] ?></h3>
            <span>Toplam Sunucu</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['active'] ?></h3>
            <span>Aktif Sunucu</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-signal"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['online'] ?></h3>
            <span>Çevrimiçi</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-cubes"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['totalServices'] ?></h3>
            <span>Aktif Hizmet</span>
        </div>
    </div>
</div>

<!-- Server Cards -->
<?php if (empty($servers)): ?>
    <div class="empty-state">
        <div class="icon">🖥️</div>
        <h3>Henüz sunucu yok</h3>
        <p>İlk sunucunuzu eklemek için butona tıklayın.</p>
        <button onclick="openServerModal('addModal')" class="btn btn-primary">
            <i class="fas fa-plus"></i> Sunucu Ekle
        </button>
    </div>
<?php else: ?>
    <div class="server-grid">
        <?php foreach ($servers as $server): 
            $moduleIcon = match($server['module']) {
                'cpanel' => 'fa-c',
                'plesk' => 'fa-p',
                'directadmin' => 'fa-d',
                'proxmox' => 'fa-cube',
                'vmware' => 'fa-cloud',
                default => 'fa-server'
            };
            $lastCheckStatus = $server['last_check_result'] ?? 'unknown';
        ?>
        <div class="server-card" id="server-<?= $server['id'] ?>">
            <div class="server-header">
                <div class="server-status-indicator <?= $lastCheckStatus ?>"></div>
                <div class="server-icon">
                    <i class="fas fa-server"></i>
                </div>
                <div class="server-name"><?= htmlspecialchars($server['name']) ?></div>
                <div class="server-hostname"><?= htmlspecialchars($server['hostname']) ?></div>
            </div>
            <div class="server-body">
                <!-- Connection Result (hidden by default) -->
                <div class="connection-result" id="result-<?= $server['id'] ?>">
                    <div class="result-content"></div>
                </div>
                
                <div class="server-info-grid">
                    <div class="server-info-item">
                        <label>IP Adresi</label>
                        <span class="mono"><?= htmlspecialchars($server['ip_address']) ?></span>
                    </div>
                    <div class="server-info-item">
                        <label>Port</label>
                        <span class="mono"><?= $server['port'] ?: 22 ?></span>
                    </div>
                    <div class="server-info-item">
                        <label>Modül</label>
                        <span class="module-badge <?= $server['module'] ?>">
                            <?= ucfirst($server['module']) ?>
                        </span>
                    </div>
                    <div class="server-info-item">
                        <label>Hizmetler (Yerel)</label>
                        <span><?= (int)$server['active_services'] ?> / <?= $server['max_accounts'] ?: '∞' ?></span>
                    </div>
                    <div class="server-info-item">
                        <label>Hesaplar (API)</label>
                        <span id="api-accounts-<?= $server['id'] ?>" class="api-account-count">
                            <button type="button" onclick="fetchAccountCount(<?= $server['id'] ?>)" class="fetch-accounts-btn">
                                <i class="fas fa-sync-alt"></i> Çek
                            </button>
                        </span>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <span class="status-badge <?= $server['is_active'] ? 'active' : 'inactive' ?>">
                        <i class="fas <?= $server['is_active'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                        <?= $server['is_active'] ? 'Aktif' : 'Pasif' ?>
                    </span>
                    <?php if (!empty($server['last_check'])): ?>
                        <span style="font-size: 12px; color: var(--gray);">
                            <i class="fas fa-clock"></i>
                            Son kontrol: <?= date('d.m H:i', strtotime($server['last_check'])) ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="server-actions">
                    <button type="button" class="server-btn test" onclick="testConnection(<?= $server['id'] ?>, this)">
                        <i class="fas fa-wifi"></i>
                        Bağlantı Testi
                    </button>
                    <a href="server-edit.php?id=<?= $server['id'] ?>" class="server-btn">
                        <i class="fas fa-edit"></i>
                        Düzenle
                    </a>
                    <button type="button" class="server-btn delete" onclick="confirmServerDelete(<?= $server['id'] ?>, '<?= htmlspecialchars($server['name']) ?>')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Yeni Sunucu Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color: var(--primary);"></i> Yeni Sunucu Ekle</h3>
            <button onclick="closeServerModal('addModal')" class="close-btn">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Sunucu Adı *</label>
                    <input type="text" name="name" class="form-control" required placeholder="Örn: Web Server 1">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-globe"></i> Hostname *</label>
                        <input type="text" name="hostname" class="form-control" required placeholder="server1.example.com">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-network-wired"></i> IP Adresi *</label>
                        <input type="text" name="ip_address" class="form-control" required placeholder="192.168.1.1">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-plug"></i> Port</label>
                        <input type="number" name="port" class="form-control" value="22" placeholder="22">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-puzzle-piece"></i> Modül</label>
                        <select name="module" class="form-control">
                            <option value="cpanel">cPanel/WHM</option>
                            <option value="plesk">Plesk</option>
                            <option value="directadmin">DirectAdmin</option>
                            <option value="virtualmin">Virtualmin</option>
                            <option value="proxmox">Proxmox VE</option>
                            <option value="vmware">VMware</option>
                            <option value="custom">Özel</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Kullanıcı Adı</label>
                        <input type="text" name="username" class="form-control" placeholder="root">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Şifre / API Token</label>
                        <input type="password" name="password" class="form-control">
                    </div>
                </div>
                <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="is_active" id="is_active" checked style="width: 20px; height: 20px;">
                    <label for="is_active" style="margin: 0; cursor: pointer;">Sunucu Aktif</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeServerModal('addModal')" class="btn btn-outline">İptal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <div class="modal-body" style="padding: 40px;">
            <div style="width: 70px; height: 70px; background: rgba(239, 68, 68, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 32px; color: #ef4444;">
                <i class="fas fa-trash"></i>
            </div>
            <h3 style="margin-bottom: 10px;">Sunucuyu Sil</h3>
            <p style="color: var(--gray); margin-bottom: 24px;" id="deleteMessage">Bu sunucuyu silmek istediğinizden emin misiniz?</p>
            <div style="display: flex; gap: 12px; justify-content: center;">
                <button onclick="closeServerModal('deleteModal')" class="btn btn-outline">İptal</button>
                <a href="#" id="deleteLink" class="btn" style="background: linear-gradient(135deg, #ef4444, #dc2626); color: white;">
                    <i class="fas fa-trash"></i> Evet, Sil
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function openServerModal(id) {
    document.getElementById(id).classList.add('show');
    document.getElementById(id).classList.add('active');
}

function closeServerModal(id) {
    document.getElementById(id).classList.remove('show');
    document.getElementById(id).classList.remove('active');
}

// Close on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
            this.classList.remove('active');
        }
    });
});

// Close on ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.show, .modal.active').forEach(m => {
            m.classList.remove('show');
            m.classList.remove('active');
        });
    }
});

function confirmServerDelete(serverId, serverName) {
    document.getElementById('deleteMessage').innerHTML = '<strong>' + serverName + '</strong> sunucusunu silmek istediğinizden emin misiniz?';
    document.getElementById('deleteLink').href = 'servers.php?delete=' + serverId;
    openServerModal('deleteModal');
}

async function testConnection(serverId, btn) {
    const resultDiv = document.getElementById('result-' + serverId);
    const indicator = document.querySelector('#server-' + serverId + ' .server-status-indicator');
    const originalHtml = btn.innerHTML;
    
    // Loading state
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Test ediliyor...';
    btn.classList.add('loading');
    resultDiv.style.display = 'none';
    
    try {
        const formData = new FormData();
        formData.append('test_connection', '1');
        formData.append('server_id', serverId);
        
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        resultDiv.style.display = 'block';
        
        if (result.success) {
            resultDiv.className = 'connection-result success';
            resultDiv.innerHTML = `
                <div style="display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-check-circle" style="font-size: 24px;"></i>
                    <div>
                        <strong>Bağlantı Başarılı!</strong><br>
                        <span style="font-size: 13px;">Yanıt süresi: <span class="ping">${result.ping}ms</span></span>
                    </div>
                </div>
            `;
            indicator.className = 'server-status-indicator online';
        } else {
            resultDiv.className = 'connection-result error';
            resultDiv.innerHTML = `
                <div style="display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-times-circle" style="font-size: 24px;"></i>
                    <div>
                        <strong>Bağlantı Başarısız!</strong><br>
                        <span style="font-size: 13px;">${result.message}</span>
                    </div>
                </div>
            `;
            indicator.className = 'server-status-indicator offline';
        }
    } catch (error) {
        resultDiv.style.display = 'block';
        resultDiv.className = 'connection-result error';
        resultDiv.innerHTML = `
            <div style="display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-exclamation-triangle" style="font-size: 24px;"></i>
                <div>
                    <strong>Hata!</strong><br>
                    <span style="font-size: 13px;">${error.message}</span>
                </div>
            </div>
        `;
    }
    
    btn.innerHTML = originalHtml;
    btn.classList.remove('loading');
}

async function testAllServers() {
    const buttons = document.querySelectorAll('.server-btn.test');
    for (const btn of buttons) {
        const serverId = btn.getAttribute('onclick').match(/\d+/)[0];
        await testConnection(parseInt(serverId), btn);
        await new Promise(resolve => setTimeout(resolve, 500)); // 500ms bekleme
    }
}

// API'den hesap sayısını çek
async function fetchAccountCount(serverId) {
    const container = document.getElementById('api-accounts-' + serverId);
    const originalHtml = container.innerHTML;
    
    container.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    try {
        const formData = new FormData();
        formData.append('server_id', serverId);
        formData.append('action', 'accounts');
        
        const response = await fetch('server-test.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            container.innerHTML = `
                <span style="font-weight: 600; color: var(--success);">
                    ${result.accounts} hesap
                </span>
                <button type="button" onclick="fetchAccountCount(${serverId})" class="fetch-accounts-btn small" title="Yenile">
                    <i class="fas fa-sync-alt"></i>
                </button>
            `;
        } else {
            container.innerHTML = `
                <span style="color: var(--danger); font-size: 12px;">
                    <i class="fas fa-exclamation-triangle"></i> API Hatası
                </span>
                <button type="button" onclick="fetchAccountCount(${serverId})" class="fetch-accounts-btn small" title="Tekrar Dene">
                    <i class="fas fa-redo"></i>
                </button>
            `;
        }
    } catch (error) {
        container.innerHTML = originalHtml;
        console.error('Hesap sayısı alınamadı:', error);
    }
}

// Tüm sunucuların hesap sayılarını çek
async function fetchAllAccounts() {
    const buttons = document.querySelectorAll('.fetch-accounts-btn:not(.small)');
    for (const btn of buttons) {
        const match = btn.getAttribute('onclick').match(/fetchAccountCount\((\d+)\)/);
        if (match) {
            await fetchAccountCount(parseInt(match[1]));
            await new Promise(resolve => setTimeout(resolve, 300));
        }
    }
}
</script>

<?php include 'includes/footer.php'; ?>
