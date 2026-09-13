<?php
/**
 * WHMVM Admin - ESXi VM Yönetimi
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Sifreleme.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/ESXi.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$serverId = (int)($_GET['server'] ?? 0);
if (!$serverId) {
    header('Location: esxi-servers.php');
    exit;
}

// Şifre çözme
function decryptPassword(string $encrypted): string
{
    return Sifreleme::coz($encrypted);
}

// Sunucu bilgisi
$server = Database::fetch("SELECT * FROM esxi_servers WHERE id = ?", [$serverId]);
if (!$server) {
    header('Location: esxi-servers.php');
    exit;
}

$pageTitle = 'VM\'ler - ' . $server['name'];
$currentPage = 'esxi-servers';

$message = '';
$messageType = 'success';
$vms = [];
$connectionError = '';

// ESXi bağlantısı
$esxi = new ESXi(
    $server['ip_address'],
    $server['username'],
    decryptPassword($server['password']),
    (int)$server['port'],
    $server['connection_type']
);

// Power işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $vmid = $_POST['vmid'] ?? '';
    $action = $_POST['action'];
    
    if (!empty($vmid)) {
        $result = match($action) {
            'power_on' => $esxi->powerOn($vmid),
            'power_off' => $esxi->powerOff($vmid),
            'shutdown' => $esxi->shutdown($vmid),
            'reset' => $esxi->reset($vmid),
            'reboot' => $esxi->reboot($vmid),
            'suspend' => $esxi->suspend($vmid),
            default => ['success' => false, 'message' => 'Geçersiz işlem']
        };
        
        // Log kaydet
        try {
            Database::insert('esxi_logs', [
                'esxi_server_id' => $serverId,
                'vmid' => $vmid,
                'action' => $action,
                'status' => $result['success'] ? 'success' : 'failed',
                'message' => $result['message'],
                'initiated_by' => 'admin',
                'admin_id' => $_SESSION['admin_id'],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
        } catch (Throwable $e) {}
        
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    }
}

// VM listesi al
$testResult = $esxi->testConnection();
if ($testResult['success']) {
    $vms = $esxi->listVMs();
    
    // Sunucu durumunu güncelle
    Database::query("UPDATE esxi_servers SET last_check = NOW(), last_status = 'online' WHERE id = ?", [$serverId]);
} else {
    $connectionError = $testResult['message'];
    Database::query("UPDATE esxi_servers SET last_check = NOW(), last_status = 'error' WHERE id = ?", [$serverId]);
}

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* Breadcrumb */
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    font-size: 14px;
}

.breadcrumb a {
    color: var(--primary);
    text-decoration: none;
}

.breadcrumb a:hover {
    text-decoration: underline;
}

.breadcrumb .separator {
    color: var(--gray);
}

/* Server Info */
.server-info {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.server-details {
    display: flex;
    align-items: center;
    gap: 20px;
}

.server-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.server-meta h2 {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 4px;
}

.server-meta p {
    color: var(--gray);
    font-size: 14px;
}

.server-status {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
}

.server-status.online {
    background: #d1fae5;
    color: #059669;
}

.server-status.offline {
    background: #fee2e2;
    color: #dc2626;
}

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
.alert-warning { background: #fef3c7; color: #d97706; }

/* VM Grid */
.vm-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.vm-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    overflow: hidden;
    transition: all 0.3s;
}

.vm-card:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    transform: translateY(-3px);
}

.vm-header {
    padding: 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.vm-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.vm-icon {
    width: 48px;
    height: 48px;
    background: #f1f5f9;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.vm-icon.running { background: #d1fae5; color: #059669; }
.vm-icon.stopped { background: #fee2e2; color: #dc2626; }
.vm-icon.suspended { background: #fef3c7; color: #d97706; }

.vm-title h3 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 4px;
}

.vm-title p {
    font-size: 12px;
    color: var(--gray);
}

.vm-state {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.vm-state.running { background: #d1fae5; color: #059669; }
.vm-state.stopped { background: #fee2e2; color: #dc2626; }
.vm-state.suspended { background: #fef3c7; color: #d97706; }
.vm-state.unknown { background: #f1f5f9; color: #64748b; }

.vm-body {
    padding: 20px;
}

.vm-specs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.spec-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.spec-item i {
    width: 32px;
    height: 32px;
    background: #f1f5f9;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 14px;
}

.spec-item .spec-text {
    font-size: 13px;
}

.spec-item .spec-text strong {
    display: block;
    font-size: 14px;
    color: var(--dark);
}

.spec-item .spec-text span {
    color: var(--gray);
    font-size: 11px;
}

.vm-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn {
    padding: 10px 16px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

.btn-sm { padding: 8px 12px; font-size: 12px; }
.btn-success { background: #10b981; color: white; }
.btn-danger { background: #ef4444; color: white; }
.btn-warning { background: #f59e0b; color: white; }
.btn-primary { background: var(--primary); color: white; }
.btn-outline { background: transparent; border: 1px solid var(--border); color: var(--dark); }

.btn:hover { opacity: 0.9; transform: translateY(-1px); }
.btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

/* Empty State */
.empty-state {
    text-align: center;
    padding: 80px 40px;
    background: white;
    border-radius: 12px;
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
}

/* Loading */
.loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 20px;
    color: var(--gray);
}

.loading i {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Refresh button */
.refresh-btn {
    padding: 10px 20px;
    background: white;
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--dark);
    cursor: pointer;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

.refresh-btn:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
</style>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <a href="esxi-servers.php"><i class="fas fa-server"></i> ESXi Sunucuları</a>
    <span class="separator">/</span>
    <span><?= htmlspecialchars($server['name']) ?></span>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?>">
    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- Server Info -->
<div class="server-info">
    <div class="server-details">
        <div class="server-icon"><i class="fas fa-server"></i></div>
        <div class="server-meta">
            <h2><?= htmlspecialchars($server['name']) ?></h2>
            <p><?= htmlspecialchars($server['ip_address']) ?>:<?= $server['port'] ?> (<?= strtoupper($server['connection_type']) ?>)</p>
        </div>
    </div>
    <div style="display: flex; gap: 15px; align-items: center;">
        <div class="server-status <?= $connectionError ? 'offline' : 'online' ?>">
            <i class="fas fa-<?= $connectionError ? 'times' : 'check' ?>-circle"></i>
            <?= $connectionError ? 'Bağlantı Hatası' : 'Çevrimiçi' ?>
        </div>
        <button class="refresh-btn" onclick="location.reload()">
            <i class="fas fa-sync-alt"></i> Yenile
        </button>
    </div>
</div>

<?php if ($connectionError): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Bağlantı Hatası:</strong> <?= htmlspecialchars($connectionError) ?>
    </div>
<?php elseif (empty($vms)): ?>
    <div class="empty-state">
        <i class="fas fa-desktop"></i>
        <h3>VM Bulunamadı</h3>
        <p>Bu ESXi sunucusunda henüz sanal makine bulunmuyor.</p>
    </div>
<?php else: ?>
    <div class="vm-grid">
        <?php foreach ($vms as $vm): 
            $stateClass = match($vm['state']) {
                'running' => 'running',
                'stopped' => 'stopped',
                'suspended' => 'suspended',
                default => 'unknown'
            };
            $stateText = match($vm['state']) {
                'running' => 'Çalışıyor',
                'stopped' => 'Kapalı',
                'suspended' => 'Askıda',
                default => 'Bilinmiyor'
            };
        ?>
        <div class="vm-card">
            <div class="vm-header">
                <div class="vm-info">
                    <div class="vm-icon <?= $stateClass ?>">
                        <i class="fas fa-desktop"></i>
                    </div>
                    <div class="vm-title">
                        <h3><?= htmlspecialchars($vm['name']) ?></h3>
                        <p>VMID: <?= htmlspecialchars($vm['vmid']) ?></p>
                    </div>
                </div>
                <span class="vm-state <?= $stateClass ?>"><?= $stateText ?></span>
            </div>
            <div class="vm-body">
                <div class="vm-specs">
                    <div class="spec-item">
                        <i class="fas fa-hdd"></i>
                        <div class="spec-text">
                            <strong><?= htmlspecialchars($vm['datastore'] ?: '-') ?></strong>
                            <span>Datastore</span>
                        </div>
                    </div>
                    <div class="spec-item">
                        <i class="fas fa-laptop"></i>
                        <div class="spec-text">
                            <strong><?= htmlspecialchars($vm['guest_os'] ?: '-') ?></strong>
                            <span>Guest OS</span>
                        </div>
                    </div>
                </div>
                
                <div class="vm-actions">
                    <?php if ($vm['state'] === 'running'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="vmid" value="<?= htmlspecialchars($vm['vmid']) ?>">
                            <button type="submit" name="action" value="shutdown" class="btn btn-warning btn-sm" 
                                    onclick="return confirm('VM düzgün kapatılsın mı?')">
                                <i class="fas fa-power-off"></i> Kapat
                            </button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="vmid" value="<?= htmlspecialchars($vm['vmid']) ?>">
                            <button type="submit" name="action" value="reboot" class="btn btn-primary btn-sm"
                                    onclick="return confirm('VM yeniden başlatılsın mı?')">
                                <i class="fas fa-redo"></i> Yeniden Başlat
                            </button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="vmid" value="<?= htmlspecialchars($vm['vmid']) ?>">
                            <button type="submit" name="action" value="power_off" class="btn btn-danger btn-sm"
                                    onclick="return confirm('VM zorla kapatılsın mı? (Veri kaybı olabilir!)')">
                                <i class="fas fa-stop"></i> Zorla Kapat
                            </button>
                        </form>
                    <?php elseif ($vm['state'] === 'stopped'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="vmid" value="<?= htmlspecialchars($vm['vmid']) ?>">
                            <button type="submit" name="action" value="power_on" class="btn btn-success btn-sm">
                                <i class="fas fa-play"></i> Başlat
                            </button>
                        </form>
                    <?php elseif ($vm['state'] === 'suspended'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="vmid" value="<?= htmlspecialchars($vm['vmid']) ?>">
                            <button type="submit" name="action" value="power_on" class="btn btn-success btn-sm">
                                <i class="fas fa-play"></i> Devam Et
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

