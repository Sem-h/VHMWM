<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Mail.php';
require_once dirname(__DIR__) . '/includes/ESXi.php';
session_name(SESSION_NAME); session_start();

$pageTitle = 'Hizmet Detayı';
$currentPage = 'services';
$db = Database::getInstance();

$serviceId = (int)($_GET['id'] ?? 0);
$message = '';

// Hizmet bilgilerini çek
$stmt = $db->prepare("
    SELECT s.*, p.name as product_name, p.type as product_type,
           c.first_name, c.last_name, c.email, c.phone,
           sv.name as server_name, sv.hostname as server_hostname
    FROM services s 
    LEFT JOIN products p ON s.product_id = p.id
    LEFT JOIN clients c ON s.client_id = c.id 
    LEFT JOIN servers sv ON s.server_id = sv.id
    WHERE s.id = ?
");
$stmt->execute([$serviceId]);
$service = $stmt->fetch();

if (!$service) {
    header('Location: services.php');
    exit;
}

// Module data'yı parse et
$moduleData = json_decode($service['module_data'] ?? '{}', true) ?: [];

// ESXi sunucu listesi
$esxiServers = [];
try {
    $esxiServers = Database::fetchAll("SELECT id, name, ip_address FROM esxi_servers WHERE is_active = 1 ORDER BY name");
} catch (Throwable $e) {}

// ESXi VM atama
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_esxi'])) {
    $esxiServerId = (int)($_POST['esxi_server_id'] ?? 0) ?: null;
    $esxiVmid = trim($_POST['esxi_vmid'] ?? '') ?: null;
    $vmHostname = trim($_POST['vm_hostname'] ?? '') ?: null;
    
    $stmt = $db->prepare("UPDATE services SET esxi_server_id = ?, esxi_vmid = ?, vm_hostname = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$esxiServerId, $esxiVmid, $vmHostname, $serviceId]);
    
    $service['esxi_server_id'] = $esxiServerId;
    $service['esxi_vmid'] = $esxiVmid;
    $service['vm_hostname'] = $vmHostname;
    
    $message = 'ESXi VM bilgileri güncellendi.';
}

// Durum güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $oldStatus = $service['status'];
    $newStatus = $_POST['status'];
    $stmt = $db->prepare("UPDATE services SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$newStatus, $serviceId]);
    
    // Durum değişikliği mail bildirimi
    try {
        $templateName = null;
        $extraVars = [];
        
        if ($newStatus === 'active' && $oldStatus !== 'active') {
            $templateName = 'service_activated';
            $extraVars = [
                'domain' => $service['domain'] ?? '-',
                'ip_address' => $service['dedicated_ip'] ?? '-',
                'username' => $service['username'] ?? '-',
                'password' => '(Panelden görüntüleyin)'
            ];
        } elseif ($newStatus === 'suspended') {
            $templateName = 'service_suspended';
            $extraVars = [
                'suspend_reason' => $_POST['suspend_reason'] ?? 'Ödeme bekleniyor',
                'suspend_date' => date('d.m.Y H:i')
            ];
        } elseif ($newStatus === 'active' && $oldStatus === 'suspended') {
            $templateName = 'service_unsuspended';
        } elseif ($newStatus === 'terminated') {
            $templateName = 'service_terminated';
            $extraVars = [
                'termination_reason' => $_POST['termination_reason'] ?? 'Talep üzerine',
                'termination_date' => date('d.m.Y H:i')
            ];
        }
        
        if ($templateName && !empty($service['email'])) {
            Mail::sendTemplate($templateName, $service['email'], array_merge([
                'client_name' => $service['first_name'] . ' ' . $service['last_name'],
                'product_name' => $service['product_name'] ?? 'Hizmet'
            ], $extraVars), $service['first_name']);
        }
    } catch (Throwable $e) {
        // Mail hatası işlemi engellemesin
    }
    
    $service['status'] = $newStatus;
    $message = 'Hizmet durumu güncellendi.';
}

// İlgili faturalar
$invoices = $db->query("
    SELECT i.* FROM invoices i 
    INNER JOIN invoice_items ii ON i.id = ii.invoice_id 
    WHERE ii.service_id = $serviceId 
    ORDER BY i.created_at DESC
")->fetchAll();

include 'includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?= $message ?></div>
<?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <div>
        <a href="services.php" style="color: var(--gray); text-decoration: none; font-size: 14px;">← Hizmetlere Dön</a>
        <h2 style="margin-top: 10px;"><?= htmlspecialchars($service['product_name'] ?? 'Hizmet') ?> #<?= $serviceId ?></h2>
    </div>
    <div>
        <?php
        $statusBadge = match($service['status']) {
            'active' => 'success',
            'pending' => 'warning',
            'suspended' => 'danger',
            default => 'gray'
        };
        ?>
        <span class="badge badge-<?= $statusBadge ?>" style="font-size: 14px; padding: 10px 20px;">
            <?= ucfirst($service['status']) ?>
        </span>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
    <div>
        <!-- Hizmet Bilgileri -->
        <div class="card">
            <div class="card-header">
                <h3>📦 Hizmet Bilgileri</h3>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <td style="width: 200px;"><strong>Ürün</strong></td>
                        <td><?= htmlspecialchars($service['product_name'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td><strong>Domain/Hostname</strong></td>
                        <td><?= htmlspecialchars($service['domain'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td><strong>Kullanıcı Adı</strong></td>
                        <td><?= htmlspecialchars($service['username'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td><strong>Şifre</strong></td>
                        <td>
                            <?php if ($service['password']): ?>
                            <code style="background: var(--bg-secondary); padding: 4px 8px; border-radius: 4px; font-family: monospace;"><?= htmlspecialchars($service['password']) ?></code>
                            <button type="button" onclick="copyToClipboard('<?= htmlspecialchars($service['password']) ?>')" class="btn btn-sm btn-outline" style="margin-left: 8px;">
                                <i class="fas fa-copy"></i> Kopyala
                            </button>
                            <?php else: ?>
                            <span style="color: var(--gray);">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Dedicated IP</strong></td>
                        <td><?= htmlspecialchars($service['dedicated_ip'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td><strong>Sunucu</strong></td>
                        <td><?= $service['server_name'] ? htmlspecialchars($service['server_name'] . ' (' . $service['server_hostname'] . ')') : '-' ?></td>
                    </tr>
                    <tr>
                        <td><strong>Kayıt Tarihi</strong></td>
                        <td><?= $service['registration_date'] ? date('d.m.Y', strtotime($service['registration_date'])) : '-' ?></td>
                    </tr>
                    <tr>
                        <td><strong>Sonraki Vade</strong></td>
                        <td><?= $service['next_due_date'] ? date('d.m.Y', strtotime($service['next_due_date'])) : '-' ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Kontrol Paneli Bilgileri -->
        <?php if (!empty($moduleData['control_panel_url']) || !empty($moduleData['control_panel_user'])): ?>
        <div class="card">
            <div class="card-header">
                <h3>🎛️ Kontrol Paneli Bilgileri</h3>
            </div>
            <div class="card-body">
                <table class="table">
                    <?php if (!empty($moduleData['control_panel_url'])): ?>
                    <tr>
                        <td style="width: 200px;"><strong>Panel URL</strong></td>
                        <td><a href="<?= htmlspecialchars($moduleData['control_panel_url']) ?>" target="_blank"><?= htmlspecialchars($moduleData['control_panel_url']) ?></a></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($moduleData['control_panel_user'])): ?>
                    <tr>
                        <td><strong>Panel Kullanıcı</strong></td>
                        <td><?= htmlspecialchars($moduleData['control_panel_user']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($moduleData['control_panel_pass'])): ?>
                    <tr>
                        <td><strong>Panel Şifre</strong></td>
                        <td>
                            <code style="background: var(--bg-secondary); padding: 4px 8px; border-radius: 4px; font-family: monospace;"><?= htmlspecialchars($moduleData['control_panel_pass']) ?></code>
                            <button type="button" onclick="copyToClipboard('<?= htmlspecialchars($moduleData['control_panel_pass']) ?>')" class="btn btn-sm btn-outline" style="margin-left: 8px;">
                                <i class="fas fa-copy"></i> Kopyala
                            </button>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
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
                <table class="table">
                    <?php if (!empty($moduleData['ftp_host'])): ?>
                    <tr>
                        <td style="width: 200px;"><strong>FTP Host</strong></td>
                        <td><?= htmlspecialchars($moduleData['ftp_host']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($moduleData['ftp_user'])): ?>
                    <tr>
                        <td><strong>FTP Kullanıcı</strong></td>
                        <td><?= htmlspecialchars($moduleData['ftp_user']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($moduleData['ftp_pass'])): ?>
                    <tr>
                        <td><strong>FTP Şifre</strong></td>
                        <td>
                            <code style="background: var(--bg-secondary); padding: 4px 8px; border-radius: 4px; font-family: monospace;"><?= htmlspecialchars($moduleData['ftp_pass']) ?></code>
                            <button type="button" onclick="copyToClipboard('<?= htmlspecialchars($moduleData['ftp_pass']) ?>')" class="btn btn-sm btn-outline" style="margin-left: 8px;">
                                <i class="fas fa-copy"></i> Kopyala
                            </button>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($moduleData['ftp_port'])): ?>
                    <tr>
                        <td><strong>FTP Port</strong></td>
                        <td><?= htmlspecialchars($moduleData['ftp_port']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($moduleData['ssh_port'])): ?>
                    <tr>
                        <td><strong>SSH Port</strong></td>
                        <td><?= htmlspecialchars($moduleData['ssh_port']) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
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
                <table class="table">
                    <?php if (!empty($moduleData['mysql_host'])): ?>
                    <tr>
                        <td style="width: 200px;"><strong>MySQL Host</strong></td>
                        <td><?= htmlspecialchars($moduleData['mysql_host']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($moduleData['mysql_user'])): ?>
                    <tr>
                        <td><strong>MySQL Kullanıcı</strong></td>
                        <td><?= htmlspecialchars($moduleData['mysql_user']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($moduleData['mysql_pass'])): ?>
                    <tr>
                        <td><strong>MySQL Şifre</strong></td>
                        <td>
                            <code style="background: var(--bg-secondary); padding: 4px 8px; border-radius: 4px; font-family: monospace;"><?= htmlspecialchars($moduleData['mysql_pass']) ?></code>
                            <button type="button" onclick="copyToClipboard('<?= htmlspecialchars($moduleData['mysql_pass']) ?>')" class="btn btn-sm btn-outline" style="margin-left: 8px;">
                                <i class="fas fa-copy"></i> Kopyala
                            </button>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- DNS Bilgileri -->
        <?php if (!empty($moduleData['nameservers'])): ?>
        <div class="card">
            <div class="card-header">
                <h3>🌐 DNS Bilgileri</h3>
            </div>
            <div class="card-body">
                <div style="background: var(--bg-secondary); padding: 15px; border-radius: 8px; font-family: monospace; white-space: pre-line;">
                    <?= htmlspecialchars($moduleData['nameservers']) ?>
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
                <div style="white-space: pre-line;"><?= nl2br(htmlspecialchars($moduleData['extra_info'])) ?></div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Faturalama -->
        <div class="card">
            <div class="card-header">
                <h3>💰 Faturalama</h3>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <td style="width: 200px;"><strong>Faturalama Dönemi</strong></td>
                        <td><?= ucfirst($service['billing_cycle']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Tutar</strong></td>
                        <td><strong><?= number_format((float)$service['amount'], 2) ?> <?= $service['currency'] ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Faturalar -->
        <?php if (!empty($invoices)): ?>
        <div class="card">
            <div class="card-header">
                <h3>📄 İlgili Faturalar</h3>
            </div>
            <div class="card-body">
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
                                ?>
                                <span class="badge badge-<?= $iBadge ?>"><?= ucfirst($invoice['status']) ?></span>
                            </td>
                            <td>
                                <a href="invoice-view.php?id=<?= $invoice['id'] ?>" class="btn btn-sm btn-outline">Görüntüle</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Notlar -->
        <?php if ($service['notes'] || $service['admin_notes']): ?>
        <div class="card">
            <div class="card-header">
                <h3>📝 Notlar</h3>
            </div>
            <div class="card-body">
                <?php if ($service['admin_notes']): ?>
                    <div style="background: #fef3c7; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                        <strong>Admin Notu:</strong><br>
                        <?= nl2br(htmlspecialchars($service['admin_notes'])) ?>
                    </div>
                <?php endif; ?>
                <?php if ($service['notes']): ?>
                    <div>
                        <strong>Müşteri Notu:</strong><br>
                        <?= nl2br(htmlspecialchars($service['notes'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div>
        <!-- Müşteri -->
        <div class="card">
            <div class="card-header">
                <h3>👤 Müşteri</h3>
            </div>
            <div class="card-body">
                <p><strong><?= htmlspecialchars($service['first_name'] . ' ' . $service['last_name']) ?></strong></p>
                <p><a href="mailto:<?= htmlspecialchars($service['email']) ?>"><?= htmlspecialchars($service['email']) ?></a></p>
                <?php if ($service['phone']): ?>
                    <p><?= htmlspecialchars($service['phone']) ?></p>
                <?php endif; ?>
                <hr style="border: none; border-top: 1px solid var(--border); margin: 15px 0;">
                <a href="client-edit.php?id=<?= $service['client_id'] ?>" class="btn btn-sm btn-outline" style="width: 100%;">Müşteri Profiline Git</a>
            </div>
        </div>
        
        <!-- Durum Güncelle -->
        <div class="card">
            <div class="card-header">
                <h3>⚙️ Durum Yönetimi</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label>Durum</label>
                        <select name="status" class="form-control">
                            <option value="pending" <?= $service['status'] === 'pending' ? 'selected' : '' ?>>Beklemede</option>
                            <option value="active" <?= $service['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                            <option value="suspended" <?= $service['status'] === 'suspended' ? 'selected' : '' ?>>Askıda</option>
                            <option value="terminated" <?= $service['status'] === 'terminated' ? 'selected' : '' ?>>Sonlandırılmış</option>
                            <option value="cancelled" <?= $service['status'] === 'cancelled' ? 'selected' : '' ?>>İptal</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Güncelle</button>
                </form>
            </div>
        </div>
        
        <!-- ESXi VM Atama -->
        <?php if (!empty($esxiServers)): ?>
        <div class="card">
            <div class="card-header">
                <h3>🔌 ESXi VM Atama</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="update_esxi" value="1">
                    <div class="form-group">
                        <label>ESXi Sunucu</label>
                        <select name="esxi_server_id" class="form-control">
                            <option value="">-- Seçiniz --</option>
                            <?php foreach ($esxiServers as $esxiSrv): ?>
                                <option value="<?= $esxiSrv['id'] ?>" <?= ($service['esxi_server_id'] ?? '') == $esxiSrv['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($esxiSrv['name']) ?> (<?= $esxiSrv['ip_address'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>VM ID</label>
                        <input type="text" name="esxi_vmid" class="form-control" 
                               value="<?= htmlspecialchars($service['esxi_vmid'] ?? '') ?>" 
                               placeholder="Örn: 1, 2, 3...">
                        <small style="color: var(--gray);">vim-cmd vmsvc/getallvms ile alınır</small>
                    </div>
                    <div class="form-group">
                        <label>VM Hostname</label>
                        <input type="text" name="vm_hostname" class="form-control" 
                               value="<?= htmlspecialchars($service['vm_hostname'] ?? '') ?>" 
                               placeholder="Opsiyonel">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">💾 Kaydet</button>
                </form>
                
                <?php if (!empty($service['esxi_server_id']) && !empty($service['esxi_vmid'])): ?>
                <hr style="border: none; border-top: 1px solid var(--border); margin: 15px 0;">
                <a href="esxi-vms.php?server=<?= $service['esxi_server_id'] ?>" class="btn btn-outline btn-sm" style="width: 100%;">
                    🖥️ ESXi Sunucusunu Görüntüle
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Hızlı İşlemler -->
        <div class="card">
            <div class="card-header">
                <h3>⚡ Hızlı İşlemler</h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="service-edit.php?id=<?= $serviceId ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Hizmeti Düzenle
                    </a>
                    <a href="#" class="btn btn-outline">📄 Fatura Oluştur</a>
                    <a href="#" class="btn btn-outline">📧 E-posta Gönder</a>
                    <?php if ($service['status'] === 'active'): ?>
                        <a href="#" class="btn btn-warning" onclick="return confirm('Hizmeti askıya almak istediğinizden emin misiniz?')">⏸️ Askıya Al</a>
                    <?php elseif ($service['status'] === 'suspended'): ?>
                        <a href="#" class="btn btn-success">▶️ Aktif Et</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text) {
    const btn = event.target.closest('button');
    navigator.clipboard.writeText(text).then(function() {
        // Görsel feedback
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Kopyalandı!';
        btn.style.background = '#10b981';
        btn.style.color = '#fff';
        
        setTimeout(function() {
            btn.innerHTML = originalHTML;
            btn.style.background = '';
            btn.style.color = '';
        }, 2000);
    }).catch(function(err) {
        alert('Kopyalama başarısız: ' + err);
    });
}
</script>

<?php include 'includes/footer.php'; ?>

