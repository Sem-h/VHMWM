<?php
/**
 * WHMVM Admin - ESXi Sunucu Yönetimi
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Sifreleme.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}
require_once dirname(__DIR__) . '/includes/ESXi.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'ESXi Sunucuları';
$currentPage = 'esxi-servers';

// Tabloları kontrol et ve oluştur
function ensureESXiTables() {
    $db = Database::getInstance();
    
    // esxi_servers tablosu var mı?
    try {
        $result = $db->query("SHOW TABLES LIKE 'esxi_servers'")->fetch();
        if (!$result) {
            // Tablo yok, oluştur
            $db->exec("
                CREATE TABLE IF NOT EXISTS esxi_servers (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    hostname VARCHAR(255) NOT NULL,
                    ip_address VARCHAR(45) NOT NULL,
                    port INT UNSIGNED DEFAULT 22,
                    username VARCHAR(100) NOT NULL,
                    password TEXT NOT NULL,
                    connection_type ENUM('ssh', 'api') DEFAULT 'ssh',
                    is_active TINYINT(1) DEFAULT 1,
                    max_vms INT UNSIGNED DEFAULT 0,
                    notes TEXT,
                    last_check DATETIME NULL,
                    last_status ENUM('online', 'offline', 'error') DEFAULT 'offline',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_active (is_active),
                    INDEX idx_status (last_status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    } catch (Throwable $e) {
        // Hata olursa logla
        error_log("ESXi tables error: " . $e->getMessage());
    }
    
    // esxi_logs tablosu
    try {
        $result = $db->query("SHOW TABLES LIKE 'esxi_logs'")->fetch();
        if (!$result) {
            $db->exec("
                CREATE TABLE IF NOT EXISTS esxi_logs (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    esxi_server_id INT UNSIGNED NOT NULL,
                    service_id INT UNSIGNED NULL,
                    vmid VARCHAR(50) NULL,
                    action VARCHAR(50) NOT NULL,
                    status ENUM('success', 'failed', 'pending') DEFAULT 'pending',
                    message TEXT,
                    initiated_by ENUM('admin', 'client', 'system') DEFAULT 'system',
                    admin_id INT UNSIGNED NULL,
                    client_id INT UNSIGNED NULL,
                    ip_address VARCHAR(45),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_server (esxi_server_id),
                    INDEX idx_service (service_id),
                    INDEX idx_action (action),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    } catch (Throwable $e) {}
    
    // Services tablosuna ESXi alanları ekle
    try {
        $db->query("SELECT esxi_server_id FROM services LIMIT 1");
    } catch (Throwable $e) {
        try {
            $db->exec("ALTER TABLE services ADD COLUMN esxi_server_id INT UNSIGNED NULL AFTER server_id");
        } catch (Throwable $ex) {}
        try {
            $db->exec("ALTER TABLE services ADD COLUMN esxi_vmid VARCHAR(50) NULL AFTER esxi_server_id");
        } catch (Throwable $ex) {}
        try {
            $db->exec("ALTER TABLE services ADD COLUMN vm_hostname VARCHAR(255) NULL AFTER esxi_vmid");
        } catch (Throwable $ex) {}
    }
}
ensureESXiTables();

$message = '';
$messageType = 'success';

// İşlem sonrası mesajlar
if (isset($_GET['deleted'])) {
    $message = 'Sunucu başarıyla silindi.';
    $messageType = 'success';
}
if (isset($_GET['added'])) {
    $message = 'ESXi sunucusu başarıyla eklendi.';
    $messageType = 'success';
}
if (isset($_GET['updated'])) {
    $message = 'Sunucu bilgileri güncellendi.';
    $messageType = 'success';
}

// Şifre şifreleme/çözme (basit)
function encryptPassword(string $password): string
{
    return Sifreleme::sifrele($password);
}

function decryptPassword(string $encrypted): string
{
    return Sifreleme::coz($encrypted);
}

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Yeni sunucu ekle
    if (isset($_POST['add_server'])) {
        $name = trim($_POST['name'] ?? '');
        $hostname = trim($_POST['hostname'] ?? '');
        $ipAddress = trim($_POST['ip_address'] ?? '');
        $port = (int)($_POST['port'] ?? 22);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $connectionType = $_POST['connection_type'] ?? 'ssh';
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($name) || empty($ipAddress) || empty($username) || empty($password)) {
            $message = 'Lütfen zorunlu alanları doldurun.';
            $messageType = 'error';
        } else {
            Database::insert('esxi_servers', [
                'name' => $name,
                'hostname' => $hostname ?: $ipAddress,
                'ip_address' => $ipAddress,
                'port' => $port,
                'username' => $username,
                'password' => encryptPassword($password),
                'connection_type' => $connectionType,
                'notes' => $notes,
                'is_active' => 1
            ]);
            
            header('Location: esxi-servers.php?added=1');
            exit;
        }
    }
    
    // Sunucu güncelle
    if (isset($_POST['update_server'])) {
        $serverId = (int)$_POST['server_id'];
        $name = trim($_POST['name'] ?? '');
        $hostname = trim($_POST['hostname'] ?? '');
        $ipAddress = trim($_POST['ip_address'] ?? '');
        $port = (int)($_POST['port'] ?? 22);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $connectionType = $_POST['connection_type'] ?? 'ssh';
        $notes = trim($_POST['notes'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $updateData = [
            'name' => $name,
            'hostname' => $hostname ?: $ipAddress,
            'ip_address' => $ipAddress,
            'port' => $port,
            'username' => $username,
            'connection_type' => $connectionType,
            'notes' => $notes,
            'is_active' => $isActive
        ];
        
        // Şifre değiştiyse güncelle
        if (!empty($password)) {
            $updateData['password'] = encryptPassword($password);
        }
        
        Database::update('esxi_servers', $updateData, 'id = ?', [$serverId]);
        header('Location: esxi-servers.php?updated=1');
        exit;
    }
    
    // Sunucu sil
    if (isset($_POST['delete_server'])) {
        Guvenlik::zorunlu();
        $serverId = (int)$_POST['server_id'];
        Database::query("DELETE FROM esxi_servers WHERE id = ?", [$serverId]);
        // Redirect to prevent refresh re-submission
        header('Location: esxi-servers.php?deleted=1');
        exit;
    }
    
    // Bağlantı testi
    if (isset($_POST['test_connection'])) {
        $serverId = (int)$_POST['server_id'];
        $server = Database::fetch("SELECT * FROM esxi_servers WHERE id = ?", [$serverId]);
        
        if ($server) {
            $esxi = new ESXi(
                $server['ip_address'],
                $server['username'],
                decryptPassword($server['password']),
                (int)$server['port'],
                $server['connection_type']
            );
            
            $result = $esxi->testConnection();
            
            // Durumu güncelle
            Database::query(
                "UPDATE esxi_servers SET last_check = NOW(), last_status = ? WHERE id = ?",
                [$result['success'] ? 'online' : 'error', $serverId]
            );
            
            if ($result['success']) {
                $message = 'Bağlantı başarılı! ' . ($result['details']['version'] ?? '');
                $messageType = 'success';
            } else {
                $message = 'Bağlantı başarısız: ' . $result['message'];
                $messageType = 'error';
            }
        }
    }
}

// Sunucuları listele
$servers = Database::fetchAll("SELECT * FROM esxi_servers ORDER BY name ASC");

// İstatistikler
$stats = [
    'total' => count($servers),
    'online' => count(array_filter($servers, fn($s) => $s['last_status'] === 'online')),
    'offline' => count(array_filter($servers, fn($s) => $s['last_status'] !== 'online'))
];

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* Stats */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-box {
    background: var(--y-yuzey);
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    display: flex;
    align-items: center;
    gap: 16px;
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

.stat-icon.blue { background: #dbeafe; color: #2563eb; }
.stat-icon.green { background: #d1fae5; color: #059669; }
.stat-icon.red { background: #fee2e2; color: #dc2626; }

.stat-info h4 {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
}

.stat-info p {
    font-size: 13px;
    color: var(--gray);
}

/* Card */
.card {
    background: var(--y-yuzey);
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    margin-bottom: 20px;
}

.card-header {
    padding: 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-body {
    padding: 20px;
}

/* Table */
.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    text-align: left;
    padding: 12px 16px;
    font-size: 12px;
    font-weight: 600;
    color: var(--gray);
    text-transform: uppercase;
    background: var(--y-yuzey-2);
    border-bottom: 1px solid var(--border);
}

.data-table td {
    padding: 16px;
    border-bottom: 1px solid var(--y-cizgi-soft);
    font-size: 14px;
}

.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: var(--y-yuzey-2); }

/* Buttons */
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
    text-decoration: none;
}

.btn-sm { padding: 8px 14px; font-size: 13px; }
.btn-primary { background: var(--primary); color: white; }
.btn-success { background: var(--success); color: white; }
.btn-warning { background: var(--warning); color: white; }
.btn-danger { background: var(--danger); color: white; }
.btn-outline { background: transparent; border: 1px solid var(--border); color: var(--dark); }

.btn-group {
    display: flex;
    gap: 8px;
}

/* Badge */
.badge {
    display: inline-flex;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.badge-success { background: #d1fae5; color: #059669; }
.badge-danger { background: #fee2e2; color: #dc2626; }
.badge-warning { background: #fef3c7; color: #d97706; }
.badge-gray { background: var(--y-yuzey-2); color: var(--y-metin-3); }

/* Alert */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success { background: #d1fae5; color: #059669; }
.alert-error { background: #fee2e2; color: #dc2626; }

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.active { display: flex; }

.modal-content {
    background: var(--y-yuzey);
    border-radius: 16px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 { font-size: 18px; font-weight: 600; }

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--gray);
}

.modal-body { padding: 20px; }

.modal-footer {
    padding: 20px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

/* Form */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 8px;
    color: var(--dark);
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-hint {
    font-size: 12px;
    color: var(--gray);
    margin-top: 5px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 40px;
}

.empty-state i {
    font-size: 64px;
    color: var(--gray);
    margin-bottom: 20px;
}

.empty-state h3 {
    font-size: 20px;
    margin-bottom: 10px;
}

.empty-state p {
    color: var(--gray);
    margin-bottom: 20px;
}

/* Server Status */
.server-status {
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

.status-dot.online { background: #10b981; }
.status-dot.offline { background: #ef4444; }
.status-dot.unknown { background: #94a3b8; }

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* SSH Info Box */
.info-box {
    background: var(--y-yuzey-2);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
}

.info-box h4 {
    font-size: 14px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-box p {
    font-size: 13px;
    color: var(--gray);
    margin-bottom: 5px;
}

.info-box code {
    background: var(--y-cizgi);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 12px;
}
</style>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?>">
    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-row">
    <div class="stat-box">
        <div class="stat-icon blue"><i class="fas fa-server"></i></div>
        <div class="stat-info">
            <h4><?= $stats['total'] ?></h4>
            <p>Toplam Sunucu</p>
        </div>
    </div>
    <div class="stat-box">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <h4><?= $stats['online'] ?></h4>
            <p>Çevrimiçi</p>
        </div>
    </div>
    <div class="stat-box">
        <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
        <div class="stat-info">
            <h4><?= $stats['offline'] ?></h4>
            <p>Çevrimdışı</p>
        </div>
    </div>
</div>

<!-- Info Box -->
<div class="info-box">
    <h4><i class="fas fa-info-circle"></i> ESXi Bağlantı Gereksinimleri</h4>
    <p><strong>SSH Bağlantısı için:</strong> ESXi sunucusunda SSH servisi aktif olmalı ve <code>php-ssh2</code> extension yüklü olmalı.</p>
    <p><strong>API Bağlantısı için:</strong> vSphere API erişimi açık olmalı (genelde 443 portu).</p>
    <p><strong>Komutlar:</strong> <code>vim-cmd vmsvc/getallvms</code>, <code>vim-cmd vmsvc/power.on</code>, <code>vim-cmd vmsvc/power.off</code>, vb.</p>
</div>

<!-- Servers Card -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-server"></i> ESXi Sunucuları</h3>
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Yeni Sunucu Ekle
        </button>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($servers)): ?>
            <div class="empty-state">
                <i class="fas fa-server"></i>
                <h3>Henüz ESXi sunucusu eklenmemiş</h3>
                <p>Sanal makinelerinizi yönetmek için bir ESXi sunucusu ekleyin.</p>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> İlk Sunucuyu Ekle
                </button>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sunucu Adı</th>
                        <th>IP Adresi</th>
                        <th>Port</th>
                        <th>Bağlantı</th>
                        <th>Durum</th>
                        <th>Son Kontrol</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($servers as $server): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($server['name']) ?></strong>
                            <?php if (!$server['is_active']): ?>
                                <span class="badge badge-gray">Pasif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <code><?= htmlspecialchars($server['ip_address']) ?></code>
                        </td>
                        <td><?= $server['port'] ?></td>
                        <td>
                            <span class="badge badge-<?= $server['connection_type'] === 'ssh' ? 'warning' : 'success' ?>">
                                <?= strtoupper($server['connection_type']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="server-status">
                                <span class="status-dot <?= $server['last_status'] ?>"></span>
                                <?= match($server['last_status']) {
                                    'online' => 'Çevrimiçi',
                                    'offline' => 'Çevrimdışı',
                                    default => 'Bilinmiyor'
                                } ?>
                            </div>
                        </td>
                        <td>
                            <?= $server['last_check'] ? date('d.m.Y H:i', strtotime($server['last_check'])) : '-' ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="server_id" value="<?= $server['id'] ?>">
                                    <button type="submit" name="test_connection" class="btn btn-sm btn-outline" title="Bağlantı Testi">
                                        <i class="fas fa-plug"></i>
                                    </button>
                                </form>
                                <a href="esxi-vms.php?server=<?= $server['id'] ?>" class="btn btn-sm btn-outline" title="VM'leri Görüntüle">
                                    <i class="fas fa-desktop"></i>
                                </a>
                                <button class="btn btn-sm btn-outline" onclick="openEditModal(<?= htmlspecialchars(json_encode($server)) ?>)" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $server['id'] ?>, '<?= htmlspecialchars($server['name']) ?>')" title="Sil">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Add Server Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Yeni ESXi Sunucusu Ekle</h3>
            <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label>Sunucu Adı *</label>
                    <input type="text" name="name" class="form-control" required placeholder="Örn: ESXi-01">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>IP Adresi *</label>
                        <input type="text" name="ip_address" class="form-control" required placeholder="192.168.1.100">
                    </div>
                    <div class="form-group">
                        <label>Port</label>
                        <input type="number" name="port" class="form-control" value="22" min="1" max="65535">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Hostname (Opsiyonel)</label>
                    <input type="text" name="hostname" class="form-control" placeholder="esxi01.example.com">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Kullanıcı Adı *</label>
                        <input type="text" name="username" class="form-control" required placeholder="root">
                    </div>
                    <div class="form-group">
                        <label>Şifre *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Bağlantı Tipi</label>
                    <select name="connection_type" class="form-control">
                        <option value="ssh">SSH (Port 22)</option>
                        <option value="api">vSphere API (Port 443)</option>
                    </select>
                    <p class="form-hint">SSH önerilir. API için vSphere Client gerekebilir.</p>
                </div>
                
                <div class="form-group">
                    <label>Notlar</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Sunucu hakkında notlar..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">İptal</button>
                <button type="submit" name="add_server" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Sunucu Ekle
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Server Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Sunucu Düzenle</h3>
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="server_id" id="edit_server_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Sunucu Adı *</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>IP Adresi *</label>
                        <input type="text" name="ip_address" id="edit_ip" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Port</label>
                        <input type="number" name="port" id="edit_port" class="form-control" min="1" max="65535">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Hostname</label>
                    <input type="text" name="hostname" id="edit_hostname" class="form-control">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Kullanıcı Adı *</label>
                        <input type="text" name="username" id="edit_username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Şifre (Boş bırakılırsa değişmez)</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Bağlantı Tipi</label>
                    <select name="connection_type" id="edit_connection_type" class="form-control">
                        <option value="ssh">SSH</option>
                        <option value="api">vSphere API</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Notlar</label>
                    <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                        Sunucu Aktif
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">İptal</button>
                <button type="submit" name="update_server" class="btn btn-primary">
                    <i class="fas fa-save"></i> Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    <?= Guvenlik::alan() ?>
    <input type="hidden" name="server_id" id="delete_server_id">
    <input type="hidden" name="delete_server" value="1">
</form>

<script>
function openAddModal() {
    document.getElementById('addModal').classList.add('active');
}

function openEditModal(server) {
    document.getElementById('edit_server_id').value = server.id;
    document.getElementById('edit_name').value = server.name;
    document.getElementById('edit_ip').value = server.ip_address;
    document.getElementById('edit_port').value = server.port;
    document.getElementById('edit_hostname').value = server.hostname;
    document.getElementById('edit_username').value = server.username;
    document.getElementById('edit_connection_type').value = server.connection_type;
    document.getElementById('edit_notes').value = server.notes || '';
    document.getElementById('edit_is_active').checked = server.is_active == 1;
    
    document.getElementById('editModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function confirmDelete(serverId, serverName) {
    if (confirm('\"' + serverName + '\" sunucusunu silmek istediğinize emin misiniz?')) {
        document.getElementById('delete_server_id').value = serverId;
        document.getElementById('deleteForm').submit();
    }
}

// Close modal on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>

