<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Mail.php';
session_name(SESSION_NAME); session_start();

if (!isset($_SESSION['client_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Destek Talebi';
$pageIcon = 'fas fa-comments';
$currentPage = 'tickets';
$db = Database::getInstance();
$clientId = $_SESSION['client_id'];

$ticketId = (int)($_GET['id'] ?? 0);
$message = '';

// Ticket'ı çek
$stmt = $db->prepare("
    SELECT t.*, d.name as department_name 
    FROM tickets t 
    LEFT JOIN departments d ON t.department_id = d.id 
    WHERE t.id = ? AND t.client_id = ?
");
$stmt->execute([$ticketId, $clientId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: tickets.php');
    exit;
}

// Yanıt gönder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $replyMessage = trim($_POST['message']);
    
    if (!empty($replyMessage)) {
        $stmt = $db->prepare("INSERT INTO ticket_replies (ticket_id, client_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$ticketId, $clientId, $replyMessage]);
        
        // Ticket durumunu güncelle
        $stmt = $db->prepare("UPDATE tickets SET status = 'customer_reply', last_reply_by = 'client', last_reply_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->execute([$ticketId]);
        
        // Admin'e bildirim gönder (opsiyonel - admin email ayardan alınır)
        try {
            $adminEmail = Settings::get('admin_email') ?: Settings::get('smtp_from_email');
            if ($adminEmail) {
                $client = Database::fetch("SELECT first_name, last_name FROM clients WHERE id = ?", [$clientId]);
                Mail::send(
                    $adminEmail,
                    '[Müşteri Yanıtı] Ticket #' . $ticket['ticket_number'] . ' - ' . $ticket['subject'],
                    '<h2>Müşteri Yanıtı</h2>
                    <p><strong>' . htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) . '</strong> tarafından <strong>#' . htmlspecialchars($ticket['ticket_number']) . '</strong> numaralı destek talebine yanıt verildi.</p>
                    <div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin: 15px 0;">
                        <p style="margin: 0; color: #475569;">' . nl2br(htmlspecialchars(mb_substr($replyMessage, 0, 300))) . '...</p>
                    </div>
                    <p><a href="' . SITE_URL . '/admin/ticket-view.php?id=' . $ticketId . '">Talebi Görüntüle</a></p>',
                    'Admin'
                );
            }
        } catch (Throwable $e) {
            // Mail hatası yanıtı engellemesin
        }
        
        header("Location: ticket-view.php?id=$ticketId&sent=1");
        exit;
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

if (isset($_GET['created'])) {
    $message = 'Destek talebiniz başarıyla oluşturuldu.';
}
if (isset($_GET['sent'])) {
    $message = 'Yanıtınız gönderildi.';
}

// Durum bilgileri
$statusInfo = match($ticket['status']) {
    'open' => ['class' => 'success', 'text' => 'Açık', 'icon' => 'fas fa-door-open'],
    'answered' => ['class' => 'info', 'text' => 'Yanıtlandı', 'icon' => 'fas fa-reply'],
    'customer_reply' => ['class' => 'warning', 'text' => 'Yanıt Bekliyor', 'icon' => 'fas fa-clock'],
    'closed' => ['class' => 'secondary', 'text' => 'Kapalı', 'icon' => 'fas fa-lock'],
    default => ['class' => 'secondary', 'text' => $ticket['status'], 'icon' => 'fas fa-ticket-alt']
};

$priorityInfo = match($ticket['priority']) {
    'urgent' => ['class' => 'danger', 'text' => 'Acil', 'icon' => '🔴'],
    'high' => ['class' => 'warning', 'text' => 'Yüksek', 'icon' => '🟠'],
    'medium' => ['class' => 'info', 'text' => 'Normal', 'icon' => '🟡'],
    default => ['class' => 'success', 'text' => 'Düşük', 'icon' => '🟢']
};

require_once 'includes/header.php';
?>

<style>
/* Ticket View Styles */
.ticket-header-card {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(139, 92, 246, 0.1) 100%);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 25px;
}

.ticket-header-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.ticket-back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--text-muted);
    text-decoration: none;
    font-size: 14px;
    transition: all 0.3s;
    margin-bottom: 10px;
}

.ticket-back-link:hover {
    color: var(--primary-light);
}

.ticket-title {
    font-size: 22px;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.ticket-number {
    background: rgba(99, 102, 241, 0.2);
    color: var(--primary-light);
    padding: 4px 12px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
}

.ticket-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 25px;
    font-size: 14px;
    font-weight: 600;
}

.ticket-status-badge.success {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.ticket-status-badge.info {
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.ticket-status-badge.warning {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.ticket-status-badge.secondary {
    background: rgba(100, 116, 139, 0.15);
    color: #94a3b8;
    border: 1px solid rgba(100, 116, 139, 0.3);
}

.ticket-status-badge.danger {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.ticket-meta-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
}

.ticket-meta-item {
    background: rgba(255, 255, 255, 0.03);
    padding: 15px 18px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.ticket-meta-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.ticket-meta-value {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
}

/* Success Alert */
.ticket-alert {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    animation: slideInDown 0.4s ease;
}

.ticket-alert.success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #10b981;
}

@keyframes slideInDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Conversation */
.conversation-container {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 25px;
}

.conversation-header {
    padding: 20px 25px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.conversation-header h3 {
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.conversation-header h3 i {
    color: var(--primary-light);
}

.message-count {
    background: rgba(99, 102, 241, 0.15);
    color: var(--primary-light);
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 13px;
    font-weight: 600;
}

.conversation-messages {
    max-height: 500px;
    overflow-y: auto;
}

.message-item {
    padding: 25px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    transition: background 0.3s;
}

.message-item:last-child {
    border-bottom: none;
}

.message-item:hover {
    background: rgba(255, 255, 255, 0.02);
}

.message-item.admin-message {
    background: rgba(99, 102, 241, 0.05);
    border-left: 3px solid var(--primary);
}

.message-item.admin-message:hover {
    background: rgba(99, 102, 241, 0.08);
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    flex-wrap: wrap;
    gap: 10px;
}

.message-author {
    display: flex;
    align-items: center;
    gap: 14px;
}

.author-avatar {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
    color: #fff;
    flex-shrink: 0;
}

.author-avatar.client {
    background: linear-gradient(135deg, #64748b 0%, #475569 100%);
}

.author-avatar.admin {
    background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
}

.author-info h4 {
    font-size: 15px;
    font-weight: 600;
    margin: 0 0 4px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.staff-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
    color: #fff;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.author-info span {
    font-size: 13px;
    color: var(--text-muted);
}

.message-time {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--text-muted);
}

.message-content {
    line-height: 1.8;
    white-space: pre-wrap;
    color: var(--text-secondary);
    font-size: 14px;
    padding-left: 62px;
}

/* Reply Form */
.reply-form-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    overflow: hidden;
}

.reply-form-header {
    padding: 20px 25px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    align-items: center;
    gap: 10px;
}

.reply-form-header h3 {
    font-size: 16px;
    font-weight: 600;
    margin: 0;
}

.reply-form-header i {
    color: var(--primary-light);
}

.reply-form-body {
    padding: 25px;
}

.reply-textarea {
    width: 100%;
    min-height: 150px;
    padding: 18px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    color: var(--text-primary);
    font-size: 14px;
    font-family: inherit;
    resize: vertical;
    transition: all 0.3s;
    line-height: 1.6;
}

.reply-textarea:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.reply-textarea::placeholder {
    color: var(--text-muted);
}

.reply-form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.reply-tips {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--text-muted);
}

.reply-tips i {
    color: var(--primary-light);
}

.btn-send {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 28px;
    background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
    color: #fff;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-send:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
}

/* Closed Ticket Notice */
.ticket-closed-notice {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 25px;
    background: rgba(100, 116, 139, 0.1);
    border: 1px solid rgba(100, 116, 139, 0.2);
    border-radius: 16px;
}

.ticket-closed-notice .notice-icon {
    width: 50px;
    height: 50px;
    background: rgba(100, 116, 139, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: var(--text-muted);
}

.ticket-closed-notice .notice-content h4 {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 5px 0;
}

.ticket-closed-notice .notice-content p {
    font-size: 14px;
    color: var(--text-muted);
    margin: 0;
}

.ticket-closed-notice .notice-content a {
    color: var(--primary-light);
    text-decoration: none;
    font-weight: 500;
}

.ticket-closed-notice .notice-content a:hover {
    text-decoration: underline;
}

/* Scrollbar */
.conversation-messages::-webkit-scrollbar {
    width: 6px;
}

.conversation-messages::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.02);
}

.conversation-messages::-webkit-scrollbar-thumb {
    background: rgba(99, 102, 241, 0.3);
    border-radius: 3px;
}

.conversation-messages::-webkit-scrollbar-thumb:hover {
    background: rgba(99, 102, 241, 0.5);
}

/* Responsive */
@media (max-width: 768px) {
    .ticket-header-top {
        flex-direction: column;
    }
    
    .ticket-title {
        font-size: 18px;
    }
    
    .ticket-meta-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .message-content {
        padding-left: 0;
        margin-top: 15px;
    }
    
    .reply-form-actions {
        flex-direction: column;
    }
    
    .btn-send {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php if ($message): ?>
    <div class="ticket-alert success">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Ticket Header Card -->
<div class="ticket-header-card">
    <div class="ticket-header-top">
        <div>
            <a href="tickets.php" class="ticket-back-link">
                <i class="fas fa-arrow-left"></i> Destek Taleplerim
            </a>
            <h1 class="ticket-title">
                <span class="ticket-number">#<?= htmlspecialchars($ticket['ticket_number']) ?></span>
                <?= htmlspecialchars($ticket['subject']) ?>
            </h1>
        </div>
        <div class="ticket-status-badge <?= $statusInfo['class'] ?>">
            <i class="<?= $statusInfo['icon'] ?>"></i>
            <?= $statusInfo['text'] ?>
        </div>
    </div>
    
    <div class="ticket-meta-grid">
        <div class="ticket-meta-item">
            <div class="ticket-meta-label">
                <i class="fas fa-building"></i> Departman
            </div>
            <div class="ticket-meta-value"><?= htmlspecialchars($ticket['department_name'] ?? 'Genel') ?></div>
        </div>
        <div class="ticket-meta-item">
            <div class="ticket-meta-label">
                <i class="fas fa-flag"></i> Öncelik
            </div>
            <div class="ticket-meta-value"><?= $priorityInfo['icon'] ?> <?= $priorityInfo['text'] ?></div>
        </div>
        <div class="ticket-meta-item">
            <div class="ticket-meta-label">
                <i class="fas fa-calendar-plus"></i> Oluşturulma
            </div>
            <div class="ticket-meta-value"><?= date('d.m.Y H:i', strtotime($ticket['created_at'])) ?></div>
        </div>
        <div class="ticket-meta-item">
            <div class="ticket-meta-label">
                <i class="fas fa-sync-alt"></i> Son Güncelleme
            </div>
            <div class="ticket-meta-value"><?= date('d.m.Y H:i', strtotime($ticket['updated_at'])) ?></div>
        </div>
    </div>
</div>

<!-- Conversation -->
<div class="conversation-container">
    <div class="conversation-header">
        <h3><i class="fas fa-comments"></i> Mesajlar</h3>
        <span class="message-count"><?= count($replies) ?> mesaj</span>
    </div>
    <div class="conversation-messages">
        <?php if (empty($replies)): ?>
            <div class="empty-state" style="padding: 60px 20px;">
                <div class="empty-icon" style="margin: 0 auto 20px; width: 70px; height: 70px; background: rgba(99, 102, 241, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px;">
                    <i class="fas fa-inbox" style="color: var(--primary-light);"></i>
                </div>
                <h3 style="margin-bottom: 10px;">Henüz mesaj yok</h3>
                <p style="color: var(--text-muted);">Bu talep için henüz bir mesaj bulunmuyor.</p>
            </div>
        <?php else: ?>
            <?php foreach ($replies as $index => $reply): ?>
                <?php $isAdmin = !empty($reply['admin_id']); ?>
                <div class="message-item <?= $isAdmin ? 'admin-message' : '' ?>">
                    <div class="message-header">
                        <div class="message-author">
                            <div class="author-avatar <?= $isAdmin ? 'admin' : 'client' ?>">
                                <?php if ($isAdmin): ?>
                                    <i class="fas fa-headset"></i>
                                <?php else: ?>
                                    <?= strtoupper(substr($reply['client_first_name'] ?? 'M', 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                            <div class="author-info">
                                <h4>
                                    <?php if ($isAdmin): ?>
                                        <?= htmlspecialchars(trim(($reply['admin_first_name'] ?? '') . ' ' . ($reply['admin_last_name'] ?? '')) ?: 'Destek Personeli') ?>
                                        <span class="staff-badge"><i class="fas fa-shield-alt"></i> Destek Ekibi</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars(trim(($reply['client_first_name'] ?? '') . ' ' . ($reply['client_last_name'] ?? '')) ?: 'Müşteri') ?>
                                    <?php endif; ?>
                                </h4>
                                <span><?= $isAdmin ? 'Destek Personeli' : 'Müşteri' ?></span>
                            </div>
                        </div>
                        <div class="message-time">
                            <i class="far fa-clock"></i>
                            <?= date('d.m.Y H:i', strtotime($reply['created_at'])) ?>
                        </div>
                    </div>
                    <div class="message-content"><?= nl2br(htmlspecialchars($reply['message'])) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Reply Form -->
<?php if ($ticket['status'] !== 'closed'): ?>
<div class="reply-form-card">
    <div class="reply-form-header">
        <i class="fas fa-reply"></i>
        <h3>Yanıt Yaz</h3>
    </div>
    <div class="reply-form-body">
        <form method="POST">
            <textarea name="message" class="reply-textarea" required placeholder="Yanıtınızı buraya yazın... Sorununuzu detaylı açıklamak çözüm süresini kısaltır."></textarea>
            <div class="reply-form-actions">
                <div class="reply-tips">
                    <i class="fas fa-lightbulb"></i>
                    Detaylı açıklama yaparak çözüm süresini hızlandırabilirsiniz
                </div>
                <button type="submit" class="btn-send">
                    <i class="fas fa-paper-plane"></i>
                    Yanıt Gönder
                </button>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="ticket-closed-notice">
    <div class="notice-icon">
        <i class="fas fa-lock"></i>
    </div>
    <div class="notice-content">
        <h4>Bu Destek Talebi Kapatılmıştır</h4>
        <p>Yeni bir sorunuz veya talebiniz varsa <a href="tickets-new.php">yeni bir destek talebi</a> oluşturabilirsiniz.</p>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>

