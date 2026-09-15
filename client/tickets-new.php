<?php
/**
 * WHMVM - Yeni Destek Talebi
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Mail.php';
require_once dirname(__DIR__) . '/includes/ClientLog.php';

session_name(SESSION_NAME);
session_start();

if (!isset($_SESSION['client_id'])) {
    header('Location: index.php');
    exit;
}

$clientId = $_SESSION['client_id'];

// Sayfa değişkenleri
$pageTitle = 'Yeni Destek Talebi';
$pageIcon = 'fas fa-plus-circle';
$currentPage = 'tickets-new';

// Header'ı dahil et
require_once 'includes/header.php';

$error = '';
$success = '';

// Departmanları çek
try {
    $departments = Database::fetchAll("SELECT * FROM departments WHERE is_hidden = 0 ORDER BY order_priority");
} catch (Exception $e) {
    $departments = [];
}

// Hizmetleri çek (ilişkilendirme için)
try {
    $services = Database::fetchAll("
        SELECT s.id, s.domain, p.name as product_name 
        FROM services s 
        LEFT JOIN products p ON s.product_id = p.id 
        WHERE s.client_id = ? AND s.status = 'active'
    ", [$clientId]);
} catch (Exception $e) {
    $services = [];
}

// Ticket oluştur
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $departmentId = (int)($_POST['department_id'] ?? 0);
    $serviceId = !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null;
    $subject = trim($_POST['subject'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $message = trim($_POST['message'] ?? '');
    
    if (empty($subject) || empty($message)) {
        $error = 'Lütfen tüm gerekli alanları doldurun.';
    } else {
        try {
            // Ticket numarası oluştur
            $prefix = Database::fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'ticket_prefix'") ?: 'TKT-';
            $lastTicket = Database::fetchColumn("SELECT MAX(id) FROM tickets");
            $ticketNumber = $prefix . str_pad((string)(($lastTicket ?: 0) + 1), 6, '0', STR_PAD_LEFT);
            
            // Ticket oluştur
            $ticketId = Database::insert('tickets', [
                'ticket_number' => $ticketNumber,
                'client_id' => $clientId,
                'department_id' => $departmentId ?: null,
                'service_id' => $serviceId,
                'subject' => $subject,
                'priority' => $priority,
                'status' => 'open',
                'last_reply_by' => 'client',
                'last_reply_at' => date('Y-m-d H:i:s')
            ]);
            
            // İlk mesajı ekle
            Database::insert('ticket_replies', [
                'ticket_id' => $ticketId,
                'client_id' => $clientId,
                'message' => $message
            ]);
            
            // Müşteri logu - Destek talebi oluşturuldu
            ClientLog::ticketCreated($clientId, $ticketNumber, $subject);
            
            // Ticket oluşturuldu e-postası gönder
            try {
                $client = Database::fetch("SELECT first_name, last_name, email FROM clients WHERE id = ?", [$clientId]);
                $deptName = 'Genel';
                if ($departmentId && !empty($departments)) {
                    foreach ($departments as $d) {
                        if ($d['id'] == $departmentId) {
                            $deptName = $d['name'];
                            break;
                        }
                    }
                }
                $priorityText = match($priority) {
                    'low' => 'Düşük',
                    'high' => 'Yüksek',
                    'urgent' => 'Acil',
                    default => 'Normal'
                };
                
                Mail::sendTemplate('ticket_opened', $client['email'], [
                    'client_name' => $client['first_name'] . ' ' . $client['last_name'],
                    'ticket_id' => $ticketNumber,
                    'ticket_subject' => $subject,
                    'ticket_department' => $deptName,
                    'ticket_priority' => $priorityText
                ], $client['first_name']);
            } catch (Throwable $e) {
                // Mail hatası ticket oluşturmayı engellemesin
            }
            
            header("Location: ticket-view.php?id=$ticketId&created=1");
            exit;
        } catch (Exception $e) {
            $error = 'Bir hata oluştu. Lütfen tekrar deneyin.';
        }
    }
}
?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="content-card">
    <div class="card-header">
        <h3><i class="fas fa-edit"></i> Destek Talebi Oluştur</h3>
        <div class="card-actions">
            <a href="tickets.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Taleplerime Dön
            </a>
        </div>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Departman *</label>
                    <select name="department_id" class="form-control" required>
                        <option value="">Seçiniz...</option>
                        <?php if (empty($departments)): ?>
                            <option value="1">Teknik Destek</option>
                            <option value="2">Satış</option>
                            <option value="3">Faturalama</option>
                        <?php else: ?>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Öncelik *</label>
                    <select name="priority" class="form-control" required>
                        <option value="low">🟢 Düşük</option>
                        <option value="medium" selected>🟡 Normal</option>
                        <option value="high">🟠 Yüksek</option>
                        <option value="urgent">🔴 Acil</option>
                    </select>
                </div>
            </div>
            
            <?php if (!empty($services)): ?>
            <div class="form-group">
                <label>İlgili Hizmet <small>(Opsiyonel)</small></label>
                <select name="service_id" class="form-control">
                    <option value="">Seçiniz...</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?= $service['id'] ?>">
                            <?= htmlspecialchars($service['product_name'] ?? 'Hizmet') ?>
                            <?= !empty($service['domain']) ? ' - ' . htmlspecialchars($service['domain']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label>Konu *</label>
                <input type="text" name="subject" class="form-control" required 
                       placeholder="Talebinizin konusunu kısaca yazın"
                       value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label>Mesajınız *</label>
                <textarea name="message" class="form-control" rows="8" required 
                          placeholder="Sorununuzu veya talebinizi detaylı bir şekilde açıklayın..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
            </div>
            
            <div class="form-actions">
                <a href="tickets.php" class="btn btn-outline">İptal</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Gönder
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bilgi Kartı -->
<div class="info-card-standalone">
    <div class="info-icon">
        <i class="fas fa-info-circle"></i>
    </div>
    <div class="info-content">
        <h4>Destek Talebi İpuçları</h4>
        <ul>
            <li>Sorununuzu mümkün olduğunca detaylı açıklayın</li>
            <li>Hata mesajları varsa ekran görüntüsü ekleyin</li>
            <li>İlgili hizmeti seçerek destek sürecini hızlandırın</li>
            <li>Acil olmayan konular için "Normal" öncelik seçin</li>
        </ul>
    </div>
</div>

<style>
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--text-secondary);
}

.form-group label small {
    color: var(--text-muted);
    font-weight: 400;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 14px;
    transition: all 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(36, 116, 245, 0.1);
}

textarea.form-control {
    resize: vertical;
    min-height: 150px;
    font-family: inherit;
}

select.form-control {
    cursor: pointer;
    background-color: var(--bg-card, #1e293b);
    color: var(--text-primary, #e2e8f0);
}

select.form-control option {
    background-color: #1e293b;
    color: #e2e8f0;
    padding: 10px;
}

select.form-control option:hover,
select.form-control option:checked {
    background-color: #334155;
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
    margin-top: 10px;
}

.info-card-standalone {
    display: flex;
    gap: 20px;
    padding: 25px;
    background: rgba(36, 116, 245, 0.1);
    border: 1px solid rgba(36, 116, 245, 0.2);
    border-radius: 12px;
    margin-top: 25px;
}

.info-icon {
    width: 50px;
    height: 50px;
    background: var(--primary);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    flex-shrink: 0;
}

.info-content h4 {
    font-size: 16px;
    margin-bottom: 12px;
}

.info-content ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.info-content ul li {
    color: var(--text-muted);
    font-size: 14px;
    padding: 6px 0;
    padding-left: 20px;
    position: relative;
}

.info-content ul li::before {
    content: '→';
    position: absolute;
    left: 0;
    color: var(--primary-light);
}

.alert {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    border-radius: 10px;
    margin-bottom: 25px;
}

.alert-danger {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .info-card-standalone {
        flex-direction: column;
        text-align: center;
    }
    
    .info-icon {
        margin: 0 auto;
    }
    
    .info-content ul li {
        text-align: left;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>
