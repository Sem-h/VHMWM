<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/ClientLog.php';
require_once dirname(__DIR__) . '/includes/ESXi.php';
session_name(SESSION_NAME); session_start();

if (!isset($_SESSION['client_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Hizmet Detayı';
$pageIcon = 'fas fa-server';
$currentPage = 'services';
$db = Database::getInstance();
$clientId = $_SESSION['client_id'];
$message = '';
$messageType = '';

$serviceId = (int)($_GET['id'] ?? 0);

// Hizmet bilgilerini çek
$stmt = $db->prepare("
    SELECT s.*, p.name as product_name, p.type as product_type, p.description as product_description,
           sv.name as server_name
    FROM services s 
    LEFT JOIN products p ON s.product_id = p.id
    LEFT JOIN servers sv ON s.server_id = sv.id
    WHERE s.id = ? AND s.client_id = ?
");
$stmt->execute([$serviceId, $clientId]);
$service = $stmt->fetch();

if (!$service) {
    header('Location: services.php');
    exit;
}

// Module data'yı parse et
$moduleData = json_decode($service['module_data'] ?? '{}', true) ?: [];

// İptal talebi gönder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_request'])) {
    $cancelReason = $_POST['cancel_reason'] ?? '';
    $cancelType = $_POST['cancel_type'] ?? 'end_of_billing';
    
    if (empty($cancelReason)) {
        $message = 'Lütfen iptal sebebi belirtin.';
        $messageType = 'danger';
    } else {
        // Daha önce bekleyen iptal talebi var mı kontrol et
        $existingRequest = Database::fetchColumn(
            "SELECT id FROM cancellation_requests WHERE service_id = ? AND status = 'pending'", 
            [$serviceId]
        );
        
        if ($existingRequest) {
            $message = 'Bu hizmet için zaten bekleyen bir iptal talebiniz bulunmaktadır.';
            $messageType = 'warning';
        } else {
            try {
                // İptal talebini kaydet
                Database::insert('cancellation_requests', [
                    'service_id' => $serviceId,
                    'client_id' => $clientId,
                    'cancel_type' => $cancelType,
                    'reason' => $cancelReason,
                    'status' => 'pending'
                ]);
                
                // Müşteri logu - İptal talebi
                ClientLog::cancellationRequest(
                    $clientId, 
                    $service['product_name'] ?? 'Hizmet',
                    $cancelType
                );
                
                $message = 'İptal talebiniz başarıyla oluşturuldu. Talebiniz incelendikten sonra size bilgi verilecektir.';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Bir hata oluştu. Lütfen tekrar deneyin.';
                $messageType = 'danger';
            }
        }
    }
}

// Mevcut iptal talebi var mı?
$pendingCancellation = Database::fetch(
    "SELECT * FROM cancellation_requests WHERE service_id = ? AND client_id = ? ORDER BY created_at DESC LIMIT 1",
    [$serviceId, $clientId]
);

// İlgili faturalar
$invoices = $db->query("
    SELECT i.* FROM invoices i 
    INNER JOIN invoice_items ii ON i.id = ii.invoice_id 
    WHERE ii.service_id = $serviceId 
    ORDER BY i.created_at DESC
    LIMIT 5
")->fetchAll();

// ESXi VM bilgilerini al
$vmInfo = null;
$vmState = null;
$esxiServer = null;

// Şifre çözme fonksiyonu
function decryptPassword(string $encrypted): string {
    return openssl_decrypt(base64_decode($encrypted), 'AES-256-CBC', SITE_NAME, 0, str_pad(substr(SITE_NAME, 0, 16), 16, '0')) ?: '';
}

if (!empty($service['esxi_server_id']) && !empty($service['esxi_vmid'])) {
    try {
        $esxiServer = Database::fetch("SELECT * FROM esxi_servers WHERE id = ? AND is_active = 1", [$service['esxi_server_id']]);
        
        if ($esxiServer) {
            $esxi = new ESXi(
                $esxiServer['ip_address'],
                $esxiServer['username'],
                decryptPassword($esxiServer['password']),
                (int)$esxiServer['port'],
                $esxiServer['connection_type']
            );
            
            $vmState = $esxi->getVMPowerState($service['esxi_vmid']);
            $vmInfo = $esxi->getVMInfo($service['esxi_vmid']);
        }
    } catch (Throwable $e) {
        // ESXi bağlantı hatası sessizce geç
    }
}

include 'includes/header.php';
?>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?>" style="display: flex; align-items: center; gap: 12px; padding: 16px 20px; border-radius: 12px; margin-bottom: 20px; <?= $messageType === 'success' ? 'background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981;' : 'background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444;' ?>">
    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <a href="services.php" style="color: var(--gray); text-decoration: none; font-size: 14px;">← Hizmetlerime Dön</a>
        <h1 style="margin-top: 10px;"><?= htmlspecialchars($service['product_name'] ?? 'Hizmet') ?></h1>
    </div>
    <div>
        <?php
        $statusBadge = match($service['status']) {
            'active' => 'success',
            'pending' => 'warning',
            'suspended' => 'danger',
            default => 'gray'
        };
        $statusText = match($service['status']) {
            'active' => 'Aktif',
            'pending' => 'Beklemede',
            'suspended' => 'Askıda',
            'terminated' => 'Sonlandırılmış',
            default => $service['status']
        };
        ?>
        <span class="badge badge-<?= $statusBadge ?>" style="font-size: 14px; padding: 10px 20px;">
            <?= $statusText ?>
        </span>
    </div>
</div>

<?php if ($esxiServer && $vmState): ?>
<!-- VM Kontrol Kartı -->
<div class="card vm-control-card" style="margin-bottom: 25px;">
    <div class="card-header" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));">
        <h3><i class="fas fa-desktop"></i> Sunucu Kontrolü</h3>
        <div class="vm-status-badge <?= $vmState ?>">
            <span class="status-dot"></span>
            <?= match($vmState) {
                'running' => 'Çalışıyor',
                'stopped' => 'Kapalı',
                'suspended' => 'Askıda',
                default => 'Bilinmiyor'
            } ?>
        </div>
    </div>
    <div class="card-body">
        <?php if ($vmInfo): ?>
        <div class="vm-info-grid">
            <?php if (!empty($vmInfo['ip_address'])): ?>
            <div class="vm-info-item">
                <i class="fas fa-network-wired"></i>
                <div>
                    <span>IP Adresi</span>
                    <strong><?= htmlspecialchars($vmInfo['ip_address']) ?></strong>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($vmInfo['cpu'])): ?>
            <div class="vm-info-item">
                <i class="fas fa-microchip"></i>
                <div>
                    <span>CPU</span>
                    <strong><?= $vmInfo['cpu'] ?> vCPU</strong>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($vmInfo['memory_mb'])): ?>
            <div class="vm-info-item">
                <i class="fas fa-memory"></i>
                <div>
                    <span>RAM</span>
                    <strong><?= number_format($vmInfo['memory_mb'] / 1024, 1) ?> GB</strong>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($vmInfo['uptime'] > 0): ?>
            <div class="vm-info-item">
                <i class="fas fa-clock"></i>
                <div>
                    <span>Uptime</span>
                    <strong><?= gmdate("H:i:s", $vmInfo['uptime']) ?></strong>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="vm-power-buttons">
            <?php if ($vmState === 'running'): ?>
                <button class="vm-btn vm-btn-warning" onclick="vmPowerAction('shutdown')" id="btn-shutdown">
                    <i class="fas fa-power-off"></i> Kapat
                </button>
                <button class="vm-btn vm-btn-primary" onclick="vmPowerAction('reboot')" id="btn-reboot">
                    <i class="fas fa-redo"></i> Yeniden Başlat
                </button>
                <button class="vm-btn vm-btn-danger" onclick="vmPowerAction('reset')" id="btn-reset">
                    <i class="fas fa-sync"></i> Zorla Yeniden Başlat
                </button>
            <?php elseif ($vmState === 'stopped'): ?>
                <button class="vm-btn vm-btn-success" onclick="vmPowerAction('power_on')" id="btn-power_on">
                    <i class="fas fa-play"></i> Sunucuyu Başlat
                </button>
            <?php elseif ($vmState === 'suspended'): ?>
                <button class="vm-btn vm-btn-success" onclick="vmPowerAction('power_on')" id="btn-power_on">
                    <i class="fas fa-play"></i> Devam Et
                </button>
            <?php else: ?>
                <p style="color: var(--text-muted);">Sunucu durumu belirlenemiyor...</p>
            <?php endif; ?>
        </div>
        
        <div id="vm-loading" class="vm-loading" style="display: none;">
            <i class="fas fa-spinner fa-spin"></i> İşlem yapılıyor...
        </div>
        <div id="vm-message" class="vm-message" style="display: none;"></div>
    </div>
</div>

<style>
.vm-control-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.vm-status-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.vm-status-badge.running {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.vm-status-badge.stopped {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.vm-status-badge.suspended {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.vm-status-badge .status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: currentColor;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.vm-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.vm-info-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px;
}

.vm-info-item i {
    width: 36px;
    height: 36px;
    background: rgba(99, 102, 241, 0.15);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-light);
    font-size: 14px;
}

.vm-info-item span {
    font-size: 11px;
    color: var(--text-muted);
    display: block;
    margin-bottom: 2px;
}

.vm-info-item strong {
    font-size: 14px;
    color: var(--text-primary);
}

.vm-power-buttons {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.vm-btn {
    padding: 12px 24px;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.vm-btn:hover {
    transform: translateY(-2px);
}

.vm-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.vm-btn-success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.vm-btn-success:hover {
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
}

.vm-btn-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.vm-btn-warning:hover {
    box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3);
}

.vm-btn-danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.vm-btn-danger:hover {
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
}

.vm-btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}

.vm-btn-primary:hover {
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
}

.vm-loading {
    margin-top: 16px;
    padding: 12px;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 8px;
    color: var(--primary-light);
    font-size: 14px;
}

.vm-message {
    margin-top: 16px;
    padding: 12px;
    border-radius: 8px;
    font-size: 14px;
}

.vm-message.success {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.vm-message.error {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}
</style>

<script>
function vmPowerAction(action) {
    const actionTexts = {
        'power_on': 'Sunucu başlatılsın mı?',
        'power_off': 'Sunucu zorla kapatılsın mı? (Veri kaybı olabilir!)',
        'shutdown': 'Sunucu düzgün şekilde kapatılsın mı?',
        'reboot': 'Sunucu yeniden başlatılsın mı?',
        'reset': 'Sunucu zorla yeniden başlatılsın mı? (Veri kaybı olabilir!)'
    };
    
    if (!confirm(actionTexts[action] || 'Bu işlemi gerçekleştirmek istiyor musunuz?')) {
        return;
    }
    
    // Disable all buttons
    document.querySelectorAll('.vm-btn').forEach(btn => btn.disabled = true);
    
    // Show loading
    document.getElementById('vm-loading').style.display = 'block';
    document.getElementById('vm-message').style.display = 'none';
    
    fetch('ajax/vm-power.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'service_id=<?= $serviceId ?>&action=' + action
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('vm-loading').style.display = 'none';
        
        const msgDiv = document.getElementById('vm-message');
        msgDiv.textContent = data.message;
        msgDiv.className = 'vm-message ' + (data.success ? 'success' : 'error');
        msgDiv.style.display = 'block';
        
        if (data.success) {
            // Sayfayı 2 saniye sonra yenile
            setTimeout(() => location.reload(), 2000);
        } else {
            // Re-enable buttons
            document.querySelectorAll('.vm-btn').forEach(btn => btn.disabled = false);
        }
    })
    .catch(error => {
        document.getElementById('vm-loading').style.display = 'none';
        const msgDiv = document.getElementById('vm-message');
        msgDiv.textContent = 'Bir hata oluştu. Lütfen tekrar deneyin.';
        msgDiv.className = 'vm-message error';
        msgDiv.style.display = 'block';
        document.querySelectorAll('.vm-btn').forEach(btn => btn.disabled = false);
    });
}
</script>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px;">
    <div>
        <!-- Hizmet Bilgileri -->
        <div class="card">
            <div class="card-header">
                <h3>📦 Hizmet Bilgileri</h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                    <div>
                        <small style="color: var(--gray);">Domain/Hostname</small>
                        <p style="font-size: 18px;"><strong><?= htmlspecialchars($service['domain'] ?? '-') ?></strong></p>
                    </div>
                    <?php if ($service['dedicated_ip']): ?>
                    <div>
                        <small style="color: var(--gray);">IP Adresi</small>
                        <p style="font-size: 18px;"><strong><?= htmlspecialchars($service['dedicated_ip']) ?></strong></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($service['username']): ?>
                    <div>
                        <small style="color: var(--gray);">Kullanıcı Adı</small>
                        <p style="font-size: 18px;"><strong><?= htmlspecialchars($service['username']) ?></strong></p>
                    </div>
                    <?php endif; ?>
                    <div>
                        <small style="color: var(--gray);">Kayıt Tarihi</small>
                        <p><strong><?= $service['registration_date'] ? date('d.m.Y', strtotime($service['registration_date'])) : '-' ?></strong></p>
                    </div>
                    <div>
                        <small style="color: var(--gray);">Sonraki Vade Tarihi</small>
                        <p>
                            <strong><?= $service['next_due_date'] ? date('d.m.Y', strtotime($service['next_due_date'])) : '-' ?></strong>
                            <?php if ($service['next_due_date']): 
                                $daysLeft = (strtotime($service['next_due_date']) - time()) / 86400;
                                if ($daysLeft < 0): ?>
                                    <span class="badge badge-danger">Vadesi Geçmiş</span>
                                <?php elseif ($daysLeft < 7): ?>
                                    <span class="badge badge-warning"><?= ceil($daysLeft) ?> gün kaldı</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Kontrol Paneli Bilgileri -->
        <?php if (!empty($moduleData['control_panel_url']) || !empty($moduleData['control_panel_user'])): ?>
        <div class="card">
            <div class="card-header">
                <h3>🎛️ Kontrol Paneli Bilgileri</h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <?php if (!empty($moduleData['control_panel_url'])): ?>
                    <div>
                        <small style="color: var(--gray);">Panel URL</small>
                        <p style="font-size: 15px;"><a href="<?= htmlspecialchars($moduleData['control_panel_url']) ?>" target="_blank" style="color: var(--primary); text-decoration: none;"><?= htmlspecialchars($moduleData['control_panel_url']) ?></a></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($moduleData['control_panel_user'])): ?>
                    <div>
                        <small style="color: var(--gray);">Panel Kullanıcı Adı</small>
                        <p style="font-size: 15px;"><strong><?= htmlspecialchars($moduleData['control_panel_user']) ?></strong></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($moduleData['control_panel_pass'])): ?>
                    <div>
                        <small style="color: var(--gray);">Panel Şifresi</small>
                        <p style="font-size: 15px;"><code style="background: var(--bg-secondary); padding: 4px 8px; border-radius: 4px; font-family: monospace;"><?= htmlspecialchars($moduleData['control_panel_pass']) ?></code></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- FTP Bilgileri -->
        <?php if (!empty($moduleData['ftp_host']) || !empty($moduleData['ftp_user'])): ?>
        <div class="card">
            <div class="card-header">
                <h3>📁 FTP Bilgileri</h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <?php if (!empty($moduleData['ftp_host'])): ?>
                    <div>
                        <small style="color: var(--gray);">FTP Host</small>
                        <p style="font-size: 15px;"><strong><?= htmlspecialchars($moduleData['ftp_host']) ?></strong></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($moduleData['ftp_user'])): ?>
                    <div>
                        <small style="color: var(--gray);">FTP Kullanıcı</small>
                        <p style="font-size: 15px;"><strong><?= htmlspecialchars($moduleData['ftp_user']) ?></strong></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($moduleData['ftp_pass'])): ?>
                    <div>
                        <small style="color: var(--gray);">FTP Şifre</small>
                        <p style="font-size: 15px;"><code style="background: var(--bg-secondary); padding: 4px 8px; border-radius: 4px; font-family: monospace;"><?= htmlspecialchars($moduleData['ftp_pass']) ?></code></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($moduleData['ftp_port'])): ?>
                    <div>
                        <small style="color: var(--gray);">FTP Port</small>
                        <p style="font-size: 15px;"><strong><?= htmlspecialchars($moduleData['ftp_port']) ?></strong></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($moduleData['ssh_port'])): ?>
                    <div>
                        <small style="color: var(--gray);">SSH Port</small>
                        <p style="font-size: 15px;"><strong><?= htmlspecialchars($moduleData['ssh_port']) ?></strong></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- MySQL Bilgileri -->
        <?php if (!empty($moduleData['mysql_host']) || !empty($moduleData['mysql_user'])): ?>
        <div class="card">
            <div class="card-header">
                <h3>🗄️ MySQL Veritabanı</h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <?php if (!empty($moduleData['mysql_host'])): ?>
                    <div>
                        <small style="color: var(--gray);">MySQL Host</small>
                        <p style="font-size: 15px;"><strong><?= htmlspecialchars($moduleData['mysql_host']) ?></strong></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($moduleData['mysql_user'])): ?>
                    <div>
                        <small style="color: var(--gray);">MySQL Kullanıcı</small>
                        <p style="font-size: 15px;"><strong><?= htmlspecialchars($moduleData['mysql_user']) ?></strong></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($moduleData['mysql_pass'])): ?>
                    <div>
                        <small style="color: var(--gray);">MySQL Şifre</small>
                        <p style="font-size: 15px;"><code style="background: var(--bg-secondary); padding: 4px 8px; border-radius: 4px; font-family: monospace;"><?= htmlspecialchars($moduleData['mysql_pass']) ?></code></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Ek Bilgiler -->
        <?php if (!empty($moduleData['extra_info'])): ?>
        <div class="card">
            <div class="card-header">
                <h3>ℹ️ Ek Bilgiler</h3>
            </div>
            <div class="card-body">
                <div style="white-space: pre-line; line-height: 1.7;"><?= nl2br(htmlspecialchars($moduleData['extra_info'])) ?></div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Son Faturalar -->
        <div class="card">
            <div class="card-header">
                <h3>📄 Son Faturalar</h3>
                <a href="invoices.php" class="btn btn-sm btn-outline">Tümünü Gör</a>
            </div>
            <div class="card-body">
                <?php if (empty($invoices)): ?>
                    <p style="color: var(--gray); text-align: center; padding: 20px;">Henüz fatura yok.</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fatura #</th>
                                <th>Tarih</th>
                                <th>Tutar</th>
                                <th>Durum</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                                <td><?= date('d.m.Y', strtotime($invoice['created_at'])) ?></td>
                                <td><?= number_format((float)$invoice['total'], 2) ?> <?= $invoice['currency'] ?></td>
                                <td>
                                    <?php
                                    $iBadge = match($invoice['status']) {
                                        'paid' => 'success',
                                        'unpaid' => 'warning',
                                        default => 'gray'
                                    };
                                    $iText = match($invoice['status']) {
                                        'paid' => 'Ödenmiş',
                                        'unpaid' => 'Ödenmemiş',
                                        default => $invoice['status']
                                    };
                                    ?>
                                    <span class="badge badge-<?= $iBadge ?>"><?= $iText ?></span>
                                </td>
                                <td>
                                    <a href="invoice-view.php?id=<?= $invoice['id'] ?>" class="btn btn-sm btn-outline">Görüntüle</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div>
        <!-- Faturalama -->
        <div class="card">
            <div class="card-header">
                <h3>💰 Faturalama</h3>
            </div>
            <div class="card-body">
                <div style="text-align: center; padding: 20px 0;">
                    <div style="font-size: 36px; font-weight: 700; color: var(--primary);">
                        <?= number_format((float)$service['amount'], 2) ?> <?= $service['currency'] ?>
                    </div>
                    <p style="color: var(--gray);">
                        <?php
                        $cycle = match($service['billing_cycle']) {
                            'monthly' => 'Aylık',
                            'quarterly' => '3 Aylık',
                            'semiannually' => '6 Aylık',
                            'annually' => 'Yıllık',
                            default => $service['billing_cycle']
                        };
                        ?>
                        <?= $cycle ?> Ödeme
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Hızlı İşlemler -->
        <div class="card">
            <div class="card-header">
                <h3>⚡ Hızlı İşlemler</h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="tickets-new.php?service=<?= $serviceId ?>" class="btn btn-outline">🎫 Destek Talebi Aç</a>
                    <a href="#" class="btn btn-outline">🔄 Yükselt/Değiştir</a>
                    <?php if ($pendingCancellation && $pendingCancellation['status'] === 'pending'): ?>
                        <div class="cancellation-status pending">
                            <i class="fas fa-clock"></i>
                            <span>İptal Talebi Beklemede</span>
                        </div>
                    <?php elseif ($pendingCancellation && $pendingCancellation['status'] === 'approved'): ?>
                        <div class="cancellation-status approved">
                            <i class="fas fa-check-circle"></i>
                            <span>İptal Onaylandı</span>
                        </div>
                    <?php elseif ($pendingCancellation && $pendingCancellation['status'] === 'rejected'): ?>
                        <div class="cancellation-status rejected">
                            <i class="fas fa-times-circle"></i>
                            <span>İptal Reddedildi</span>
                            <button type="button" onclick="openCancelModal()" class="btn btn-sm" style="margin-top: 8px;">Tekrar Dene</button>
                        </div>
                    <?php else: ?>
                        <button type="button" onclick="openCancelModal()" class="btn btn-outline" style="color: var(--danger); border-color: rgba(239,68,68,0.3); background: rgba(239,68,68,0.05);">❌ İptal Talebi</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Ürün Bilgisi -->
        <?php if ($service['product_description']): ?>
        <div class="card">
            <div class="card-header">
                <h3>📋 Paket Bilgisi</h3>
            </div>
            <div class="card-body">
                <p style="color: var(--gray); line-height: 1.7;"><?= nl2br(htmlspecialchars($service['product_description'])) ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- İptal Talebi Modal -->
<div id="cancelModal" class="cancel-modal-overlay">
    <div class="cancel-modal">
        <div class="cancel-modal-header">
            <h3><i class="fas fa-times-circle"></i> Hizmet İptal Talebi</h3>
            <button type="button" onclick="closeCancelModal()" class="cancel-modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="cancel_request" value="1">
            <div class="cancel-modal-body">
                <div class="cancel-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Dikkat!</strong>
                        <p>Hizmetinizi iptal etmek istediğinizden emin misiniz? İptal talebi onaylandıktan sonra tüm verileriniz silinecektir.</p>
                    </div>
                </div>
                
                <div class="cancel-service-info">
                    <div class="cancel-service-name"><?= htmlspecialchars($service['product_name'] ?? 'Hizmet') ?></div>
                    <div class="cancel-service-domain"><?= htmlspecialchars($service['domain'] ?? '-') ?></div>
                </div>
                
                <div class="form-group">
                    <label>İptal Türü *</label>
                    <div class="cancel-type-options">
                        <label class="cancel-type-option">
                            <input type="radio" name="cancel_type" value="end_of_billing" checked>
                            <div class="cancel-type-content">
                                <i class="fas fa-calendar-check"></i>
                                <div>
                                    <strong>Dönem Sonunda</strong>
                                    <span>Mevcut fatura döneminin sonunda iptal edilsin</span>
                                </div>
                            </div>
                        </label>
                        <label class="cancel-type-option">
                            <input type="radio" name="cancel_type" value="immediate">
                            <div class="cancel-type-content">
                                <i class="fas fa-bolt"></i>
                                <div>
                                    <strong>Hemen İptal</strong>
                                    <span>Hizmet hemen iptal edilsin (İade yapılmaz)</span>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>İptal Sebebi *</label>
                    <textarea name="cancel_reason" class="form-control" rows="4" required 
                              placeholder="Lütfen iptal sebebinizi detaylı bir şekilde açıklayın..."></textarea>
                </div>
            </div>
            <div class="cancel-modal-footer">
                <button type="button" onclick="closeCancelModal()" class="btn btn-outline">Vazgeç</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-paper-plane"></i> İptal Talebi Gönder
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Cancel Modal Styles */
.cancel-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.9);
    backdrop-filter: blur(8px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.cancel-modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.cancel-modal {
    background: var(--bg-card, #1e293b);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 20px;
    max-width: 550px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    transform: scale(0.9) translateY(20px);
    transition: all 0.3s ease;
}

.cancel-modal-overlay.active .cancel-modal {
    transform: scale(1) translateY(0);
}

.cancel-modal-header {
    padding: 25px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.cancel-modal-header h3 {
    font-size: 18px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
    color: #ef4444;
}

.cancel-modal-close {
    width: 40px;
    height: 40px;
    border: none;
    background: rgba(255,255,255,0.05);
    border-radius: 10px;
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cancel-modal-close:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.cancel-modal-body {
    padding: 25px;
}

.cancel-warning {
    display: flex;
    gap: 15px;
    padding: 20px;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: 12px;
    margin-bottom: 25px;
}

.cancel-warning i {
    font-size: 24px;
    color: #ef4444;
    flex-shrink: 0;
}

.cancel-warning strong {
    display: block;
    color: #ef4444;
    margin-bottom: 5px;
}

.cancel-warning p {
    font-size: 14px;
    color: var(--text-muted);
    margin: 0;
    line-height: 1.6;
}

.cancel-service-info {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    margin-bottom: 25px;
}

.cancel-service-name {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 5px;
}

.cancel-service-domain {
    font-size: 14px;
    color: var(--text-muted);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 10px;
}

.cancel-type-options {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.cancel-type-option {
    cursor: pointer;
}

.cancel-type-option input {
    display: none;
}

.cancel-type-content {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 18px;
    background: rgba(255,255,255,0.03);
    border: 2px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    transition: all 0.3s;
}

.cancel-type-option input:checked + .cancel-type-content {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
}

.cancel-type-content i {
    width: 45px;
    height: 45px;
    background: rgba(255,255,255,0.05);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: var(--text-muted);
}

.cancel-type-option input:checked + .cancel-type-content i {
    background: var(--primary);
    color: white;
}

.cancel-type-content strong {
    display: block;
    font-size: 15px;
    margin-bottom: 3px;
}

.cancel-type-content span {
    font-size: 13px;
    color: var(--text-muted);
}

.form-control {
    width: 100%;
    padding: 14px 16px;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    color: var(--text-primary);
    font-size: 14px;
    font-family: inherit;
    resize: vertical;
    transition: all 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.form-control::placeholder {
    color: var(--text-muted);
}

.cancel-modal-footer {
    padding: 20px 25px;
    border-top: 1px solid rgba(255,255,255,0.1);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
}

.btn-outline {
    background: transparent;
    border: 1px solid rgba(255,255,255,0.2);
    color: var(--text-primary);
}

.btn-outline:hover {
    background: rgba(255,255,255,0.05);
}

.btn-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.btn-danger:hover {
    box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
    transform: translateY(-2px);
}

/* Cancellation Status */
.cancellation-status {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 15px;
    border-radius: 12px;
    text-align: center;
    font-size: 14px;
    font-weight: 600;
}

.cancellation-status i {
    font-size: 24px;
}

.cancellation-status.pending {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    color: #f59e0b;
}

.cancellation-status.approved {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.cancellation-status.rejected {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}
</style>

<script>
function openCancelModal() {
    document.getElementById('cancelModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Overlay'e tıklayınca kapat
document.getElementById('cancelModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCancelModal();
    }
});

// ESC tuşu ile kapat
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCancelModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>

