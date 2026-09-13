<?php
/**
 * WHMVM - Admin Ticket Görüntüleme
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Mail.php';

require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Destek Talebi';
$currentPage = 'tickets';
$db = Database::getInstance();
$adminId = $_SESSION['admin_id'];
$adminName = $_SESSION['admin_name'] ?? 'Admin';

$ticketId = (int)($_GET['id'] ?? 0);
$message = '';
$error = '';

// Ticket'ı çek
$stmt = $db->prepare("
    SELECT t.*, 
           c.first_name, c.last_name, c.email as client_email,
           d.name as department_name 
    FROM tickets t 
    LEFT JOIN clients c ON t.client_id = c.id 
    LEFT JOIN departments d ON t.department_id = d.id 
    WHERE t.id = ?
");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: tickets.php');
    exit;
}

// Yanıt gönder
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['message'])) {
        $replyMessage = trim($_POST['message']);
        
        if (!empty($replyMessage)) {
            $stmt = $db->prepare("INSERT INTO ticket_replies (ticket_id, admin_id, message) VALUES (?, ?, ?)");
            $stmt->execute([$ticketId, $adminId, $replyMessage]);
            
            // Ticket durumunu güncelle
            $stmt = $db->prepare("UPDATE tickets SET status = 'answered', last_reply_by = 'admin', last_reply_at = NOW(), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$ticketId]);
            
            // Müşteriye yanıt bildirimi gönder
            try {
                Mail::sendTemplate('ticket_reply', $ticket['client_email'], [
                    'client_name' => $ticket['first_name'] . ' ' . $ticket['last_name'],
                    'ticket_id' => $ticket['ticket_number'],
                    'ticket_subject' => $ticket['subject'],
                    'reply_staff' => $adminName,
                    'reply_message' => mb_substr(strip_tags($replyMessage), 0, 200) . '...'
                ], $ticket['first_name']);
            } catch (Throwable $e) {
                // Mail hatası yanıtı engellemesin
            }
            
            header("Location: ticket-view.php?id=$ticketId&sent=1");
            exit;
        }
    }
    
    // Durum değiştir
    if (isset($_POST['change_status'])) {
        $newStatus = $_POST['new_status'] ?? '';
        if (in_array($newStatus, ['open', 'answered', 'closed', 'on-hold'])) {
            $stmt = $db->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $ticketId]);
            
            // Ticket kapatıldığında bildirim gönder
            if ($newStatus === 'closed') {
                try {
                    Mail::sendTemplate('ticket_closed', $ticket['client_email'], [
                        'client_name' => $ticket['first_name'] . ' ' . $ticket['last_name'],
                        'ticket_id' => $ticket['ticket_number'],
                        'ticket_subject' => $ticket['subject']
                    ], $ticket['first_name']);
                } catch (Throwable $e) {
                    // Mail hatası işlemi engellemesin
                }
            }
            
            header("Location: ticket-view.php?id=$ticketId&status_changed=1");
            exit;
        }
    }
    
    // Öncelik değiştir
    if (isset($_POST['change_priority'])) {
        $newPriority = $_POST['new_priority'] ?? '';
        if (in_array($newPriority, ['low', 'medium', 'high', 'urgent'])) {
            $stmt = $db->prepare("UPDATE tickets SET priority = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newPriority, $ticketId]);
            
            header("Location: ticket-view.php?id=$ticketId&priority_changed=1");
            exit;
        }
    }
}

// Yanıtları çek
$replies = $db->query("
    SELECT r.*, 
           c.first_name as client_first_name, c.last_name as client_last_name,
           a.first_name as admin_first_name, a.last_name as admin_last_name
    FROM ticket_replies r 
    LEFT JOIN clients c ON r.client_id = c.id 
    LEFT JOIN admins a ON r.admin_id = a.id 
    WHERE r.ticket_id = $ticketId 
    ORDER BY r.created_at ASC
")->fetchAll();

// Mesajlar
if (isset($_GET['sent'])) {
    $message = 'Yanıtınız başarıyla gönderildi.';
}
if (isset($_GET['status_changed'])) {
    $message = 'Ticket durumu güncellendi.';
}
if (isset($_GET['priority_changed'])) {
    $message = 'Ticket önceliği güncellendi.';
}

// Durum ve öncelik bilgileri
$statusInfo = match($ticket['status']) {
    'open' => ['class' => 'success', 'text' => 'Açık', 'icon' => '🟢'],
    'answered' => ['class' => 'info', 'text' => 'Yanıtlandı', 'icon' => '🔵'],
    'customer_reply' => ['class' => 'warning', 'text' => 'Müşteri Yanıtı', 'icon' => '🟡'],
    'on-hold' => ['class' => 'secondary', 'text' => 'Beklemede', 'icon' => '⏸️'],
    'closed' => ['class' => 'gray', 'text' => 'Kapalı', 'icon' => '⚫'],
    default => ['class' => 'gray', 'text' => $ticket['status'], 'icon' => '⚪']
};

$priorityInfo = match($ticket['priority']) {
    'urgent' => ['class' => 'danger', 'text' => 'Acil', 'icon' => '🔴'],
    'high' => ['class' => 'warning', 'text' => 'Yüksek', 'icon' => '🟠'],
    'medium' => ['class' => 'info', 'text' => 'Normal', 'icon' => '🟡'],
    default => ['class' => 'success', 'text' => 'Düşük', 'icon' => '🟢']
};

include 'includes/header.php';
?>

<style>
/* Ticket View Styles */
.ticket-layout {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 25px;
}

.ticket-main {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.ticket-sidebar {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.ticket-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

.ticket-card-header {
    padding: 18px 22px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.ticket-card-header h3 {
    font-size: 15px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.ticket-card-body {
    padding: 22px;
}

/* Header Card */
.ticket-header-card {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white;
    border-radius: 12px;
    padding: 25px;
}

.ticket-header-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.ticket-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: rgba(255,255,255,0.7);
    text-decoration: none;
    font-size: 13px;
    margin-bottom: 10px;
    transition: color 0.2s;
}

.ticket-back:hover {
    color: white;
}

.ticket-number {
    background: rgba(255,255,255,0.2);
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
}

.ticket-title {
    font-size: 22px;
    font-weight: 700;
    margin: 0;
}

.ticket-badges {
    display: flex;
    gap: 10px;
}

.ticket-badge {
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    background: rgba(255,255,255,0.2);
}

/* Client Info */
.client-info {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f8fafc;
    border-radius: 10px;
}

.client-avatar {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 18px;
}

.client-details h4 {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 3px;
}

.client-details p {
    font-size: 13px;
    color: var(--gray);
    margin: 0;
}

.client-details a {
    color: var(--primary);
    text-decoration: none;
}

/* Info Items */
.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    font-size: 13px;
    color: var(--gray);
}

.info-value {
    font-size: 14px;
    font-weight: 600;
}

/* Status/Priority Select */
.quick-select {
    padding: 8px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 13px;
    cursor: pointer;
    background: white;
}

.quick-select:focus {
    outline: none;
    border-color: var(--primary);
}

/* Messages */
.messages-container {
    max-height: 500px;
    overflow-y: auto;
}

.message-item {
    padding: 20px;
    border-bottom: 1px solid #f1f5f9;
}

.message-item:last-child {
    border-bottom: none;
}

.message-item.admin-message {
    background: #f0f9ff;
    border-left: 3px solid var(--primary);
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.message-author {
    display: flex;
    align-items: center;
    gap: 12px;
}

.author-avatar {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: white;
}

.author-avatar.client {
    background: linear-gradient(135deg, #64748b 0%, #475569 100%);
}

.author-avatar.admin {
    background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
}

.author-info h4 {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 2px 0;
}

.author-info span {
    font-size: 12px;
    color: var(--gray);
}

.staff-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: var(--primary);
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 600;
    margin-left: 8px;
}

.message-time {
    font-size: 12px;
    color: var(--gray);
}

.message-content {
    line-height: 1.7;
    white-space: pre-wrap;
    font-size: 14px;
    color: #374151;
    padding-left: 54px;
}

/* Reply Form */
.reply-textarea {
    width: 100%;
    min-height: 120px;
    padding: 15px;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 14px;
    font-family: inherit;
    resize: vertical;
    margin-bottom: 15px;
}

.reply-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.reply-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.canned-responses {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.canned-btn {
    padding: 6px 12px;
    background: #f1f5f9;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.canned-btn:hover {
    background: #e2e8f0;
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

.alert-success {
    background: #d1fae5;
    color: #065f46;
}

/* Badge */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.badge-success { background: #d1fae5; color: #065f46; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-danger { background: #fee2e2; color: #991b1b; }
.badge-info { background: #dbeafe; color: #1e40af; }
.badge-gray { background: #f1f5f9; color: #475569; }
.badge-secondary { background: #e2e8f0; color: #64748b; }

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
}

.btn-outline {
    background: white;
    color: var(--dark);
    border: 1px solid var(--border);
}

.btn-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

/* Closed Notice */
.closed-notice {
    background: #fef3c7;
    border: 1px solid #fcd34d;
    border-radius: 10px;
    padding: 15px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: #92400e;
}

/* Responsive */
@media (max-width: 1200px) {
    .ticket-layout {
        grid-template-columns: 1fr;
    }
    
    .ticket-sidebar {
        order: -1;
    }
}
</style>

<?php if ($message): ?>
    <div class="alert alert-success">
        ✅ <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="ticket-layout">
    <!-- Main Content -->
    <div class="ticket-main">
        <!-- Header -->
        <div class="ticket-header-card">
            <a href="tickets.php" class="ticket-back">← Destek Taleplerine Dön</a>
            <div class="ticket-header-top">
                <div>
                    <span class="ticket-number">#<?= htmlspecialchars($ticket['ticket_number']) ?></span>
                    <h1 class="ticket-title"><?= htmlspecialchars($ticket['subject']) ?></h1>
                </div>
                <div class="ticket-badges">
                    <span class="ticket-badge"><?= $statusInfo['icon'] ?> <?= $statusInfo['text'] ?></span>
                    <span class="ticket-badge"><?= $priorityInfo['icon'] ?> <?= $priorityInfo['text'] ?></span>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <div class="ticket-card">
            <div class="ticket-card-header">
                <h3>💬 Mesajlar (<?= count($replies) ?>)</h3>
            </div>
            <div class="messages-container">
                <?php if (empty($replies)): ?>
                    <div style="padding: 40px; text-align: center; color: var(--gray);">
                        <div style="font-size: 40px; margin-bottom: 10px;">📭</div>
                        <p>Henüz mesaj bulunmuyor.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($replies as $reply): ?>
                        <?php $isAdmin = !empty($reply['admin_id']); ?>
                        <div class="message-item <?= $isAdmin ? 'admin-message' : '' ?>">
                            <div class="message-header">
                                <div class="message-author">
                                    <div class="author-avatar <?= $isAdmin ? 'admin' : 'client' ?>">
                                        <?php if ($isAdmin): ?>
                                            👤
                                        <?php else: ?>
                                            <?= strtoupper(substr($reply['client_first_name'] ?? 'M', 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="author-info">
                                        <h4>
                                            <?php if ($isAdmin): ?>
                                                <?= htmlspecialchars(trim(($reply['admin_first_name'] ?? '') . ' ' . ($reply['admin_last_name'] ?? '')) ?: 'Admin') ?>
                                                <span class="staff-badge">👨‍💼 Personel</span>
                                            <?php else: ?>
                                                <?= htmlspecialchars(trim(($reply['client_first_name'] ?? '') . ' ' . ($reply['client_last_name'] ?? '')) ?: 'Müşteri') ?>
                                            <?php endif; ?>
                                        </h4>
                                        <span><?= $isAdmin ? 'Destek Ekibi' : 'Müşteri' ?></span>
                                    </div>
                                </div>
                                <span class="message-time"><?= date('d.m.Y H:i', strtotime($reply['created_at'])) ?></span>
                            </div>
                            <div class="message-content"><?= nl2br(htmlspecialchars($reply['message'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Reply Form -->
        <?php if ($ticket['status'] !== 'closed'): ?>
        <div class="ticket-card">
            <div class="ticket-card-header">
                <h3>✏️ Yanıt Yaz</h3>
            </div>
            <div class="ticket-card-body">
                <form method="POST">
                    <textarea name="message" class="reply-textarea" required placeholder="Yanıtınızı buraya yazın..."></textarea>
                    <div class="reply-actions">
                        <div class="canned-responses">
                            <button type="button" class="canned-btn" onclick="insertCanned('Merhaba,\n\nTalebiniz alınmıştır. En kısa sürede size dönüş yapacağız.\n\nSaygılarımızla')">🔔 Alındı</button>
                            <button type="button" class="canned-btn" onclick="insertCanned('Sorununuz çözülmüştür. Başka bir sorunuz olursa bizimle iletişime geçebilirsiniz.\n\nİyi günler dileriz.')">✅ Çözüldü</button>
                            <button type="button" class="canned-btn" onclick="insertCanned('Lütfen daha detaylı bilgi verebilir misiniz?')">❓ Detay İste</button>
                        </div>
                        <button type="submit" class="btn btn-primary">📤 Yanıt Gönder</button>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="closed-notice">
            🔒 Bu ticket kapatılmıştır. Yeniden açmak için durum değiştirin.
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Sidebar -->
    <div class="ticket-sidebar">
        <!-- Client Info -->
        <div class="ticket-card">
            <div class="ticket-card-header">
                <h3>👤 Müşteri Bilgileri</h3>
            </div>
            <div class="ticket-card-body">
                <div class="client-info">
                    <div class="client-avatar">
                        <?= strtoupper(substr($ticket['first_name'] ?? 'M', 0, 1)) ?>
                    </div>
                    <div class="client-details">
                        <h4><?= htmlspecialchars(($ticket['first_name'] ?? '') . ' ' . ($ticket['last_name'] ?? '')) ?></h4>
                        <p><a href="mailto:<?= htmlspecialchars($ticket['client_email'] ?? '') ?>"><?= htmlspecialchars($ticket['client_email'] ?? '-') ?></a></p>
                    </div>
                </div>
                <?php if ($ticket['client_id']): ?>
                <div style="margin-top: 15px;">
                    <a href="client-view.php?id=<?= $ticket['client_id'] ?>" class="btn btn-outline" style="width: 100%; justify-content: center;">
                        📋 Müşteri Profilini Görüntüle
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Ticket Details -->
        <div class="ticket-card">
            <div class="ticket-card-header">
                <h3>📋 Ticket Detayları</h3>
            </div>
            <div class="ticket-card-body">
                <div class="info-item">
                    <span class="info-label">Departman</span>
                    <span class="info-value"><?= htmlspecialchars($ticket['department_name'] ?? 'Genel') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Durum</span>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="change_status" value="1">
                        <select name="new_status" class="quick-select" onchange="this.form.submit()">
                            <option value="open" <?= $ticket['status'] === 'open' ? 'selected' : '' ?>>🟢 Açık</option>
                            <option value="answered" <?= $ticket['status'] === 'answered' ? 'selected' : '' ?>>🔵 Yanıtlandı</option>
                            <option value="on-hold" <?= $ticket['status'] === 'on-hold' ? 'selected' : '' ?>>⏸️ Beklemede</option>
                            <option value="closed" <?= $ticket['status'] === 'closed' ? 'selected' : '' ?>>⚫ Kapalı</option>
                        </select>
                    </form>
                </div>
                <div class="info-item">
                    <span class="info-label">Öncelik</span>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="change_priority" value="1">
                        <select name="new_priority" class="quick-select" onchange="this.form.submit()">
                            <option value="low" <?= $ticket['priority'] === 'low' ? 'selected' : '' ?>>🟢 Düşük</option>
                            <option value="medium" <?= $ticket['priority'] === 'medium' ? 'selected' : '' ?>>🟡 Normal</option>
                            <option value="high" <?= $ticket['priority'] === 'high' ? 'selected' : '' ?>>🟠 Yüksek</option>
                            <option value="urgent" <?= $ticket['priority'] === 'urgent' ? 'selected' : '' ?>>🔴 Acil</option>
                        </select>
                    </form>
                </div>
                <div class="info-item">
                    <span class="info-label">Oluşturulma</span>
                    <span class="info-value"><?= date('d.m.Y H:i', strtotime($ticket['created_at'])) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Son Güncelleme</span>
                    <span class="info-value"><?= date('d.m.Y H:i', strtotime($ticket['updated_at'])) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Son Yanıt</span>
                    <span class="info-value">
                        <?= $ticket['last_reply_by'] === 'admin' ? '👨‍💼 Personel' : '👤 Müşteri' ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="ticket-card">
            <div class="ticket-card-header">
                <h3>⚡ Hızlı İşlemler</h3>
            </div>
            <div class="ticket-card-body">
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php if ($ticket['status'] !== 'closed'): ?>
                    <form method="POST">
                        <input type="hidden" name="change_status" value="1">
                        <input type="hidden" name="new_status" value="closed">
                        <button type="submit" class="btn btn-danger" style="width: 100%; justify-content: center;">
                            🔒 Ticket'ı Kapat
                        </button>
                    </form>
                    <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="change_status" value="1">
                        <input type="hidden" name="new_status" value="open">
                        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                            🔓 Ticket'ı Yeniden Aç
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <a href="tickets.php" class="btn btn-outline" style="justify-content: center;">
                        ← Listeye Dön
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function insertCanned(text) {
    const textarea = document.querySelector('.reply-textarea');
    textarea.value = text;
    textarea.focus();
}
</script>

<?php include 'includes/footer.php'; ?>

