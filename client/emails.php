<?php
/**
 * WHMVM - Müşteri E-Postaları Sayfası
 * Müşteriye gönderilen e-postaların listesi
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
session_name(SESSION_NAME); session_start();

if (!isset($_SESSION['client_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'E-Postalarım';
$pageIcon = 'fas fa-envelope';
$currentPage = 'emails';
$clientId = $_SESSION['client_id'];

// Müşteri bilgileri
$client = Database::fetch("SELECT email FROM clients WHERE id = ?", [$clientId]);
$clientEmail = $client['email'] ?? '';

// Sayfalama
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// E-postaları çek
$emails = [];
$totalEmails = 0;

try {
    $totalEmails = (int)Database::fetchColumn(
        "SELECT COUNT(*) FROM email_logs WHERE to_email = ?", 
        [$clientEmail]
    );
    
    $emails = Database::fetchAll(
        "SELECT * FROM email_logs WHERE to_email = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
        [$clientEmail, $perPage, $offset]
    );
} catch (Throwable $e) {
    // Tablo yoksa boş geç
}

$totalPages = (int)ceil($totalEmails / $perPage);

// E-posta detay görüntüleme
$viewEmail = null;
if (isset($_GET['view'])) {
    $emailId = (int)$_GET['view'];
    $viewEmail = Database::fetch(
        "SELECT * FROM email_logs WHERE id = ? AND to_email = ?",
        [$emailId, $clientEmail]
    );
}

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* E-Postalar Sayfası */
.emails-page {
    animation: fadeIn 0.4s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Page Header */
.page-title-card {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(139, 92, 246, 0.1) 100%);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px;
}

.page-title-content {
    display: flex;
    align-items: center;
    gap: 20px;
}

.page-title-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary), #8b5cf6);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    color: white;
}

.page-title-text h1 {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 4px;
}

.page-title-text p {
    color: var(--text-muted);
    font-size: 14px;
}

.page-stats {
    display: flex;
    gap: 30px;
}

.stat-item {
    text-align: center;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--primary-light);
}

.stat-label {
    font-size: 12px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Email List */
.email-list-card {
    background: var(--card-bg);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    overflow: hidden;
}

.email-list-header {
    padding: 20px 24px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.email-list-header h3 {
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.email-item {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 20px 24px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    cursor: pointer;
    transition: all 0.2s;
}

.email-item:hover {
    background: rgba(99, 102, 241, 0.05);
}

.email-item:last-child {
    border-bottom: none;
}

.email-icon {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(139, 92, 246, 0.2) 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: var(--primary-light);
    flex-shrink: 0;
}

.email-icon.success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.2) 100%);
    color: #6ee7b7;
}

.email-icon.failed {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.2) 100%);
    color: #fca5a5;
}

.email-content {
    flex: 1;
    min-width: 0;
}

.email-subject {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.email-template {
    display: inline-block;
    padding: 3px 10px;
    background: rgba(99, 102, 241, 0.15);
    border-radius: 6px;
    font-size: 11px;
    color: var(--primary-light);
    margin-bottom: 6px;
}

.email-preview {
    font-size: 13px;
    color: var(--text-muted);
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.email-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
    flex-shrink: 0;
}

.email-date {
    font-size: 12px;
    color: var(--text-muted);
    white-space: nowrap;
}

.email-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.email-status.sent {
    background: rgba(16, 185, 129, 0.15);
    color: #6ee7b7;
}

.email-status.failed {
    background: rgba(239, 68, 68, 0.15);
    color: #fca5a5;
}

.email-status.pending {
    background: rgba(245, 158, 11, 0.15);
    color: #fcd34d;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 80px 40px;
}

.empty-icon {
    width: 100px;
    height: 100px;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    margin: 0 auto 24px;
    color: var(--primary-light);
}

.empty-state h3 {
    font-size: 20px;
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--text-muted);
    font-size: 14px;
}

/* Pagination */
.pagination-wrapper {
    padding: 20px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid rgba(255,255,255,0.1);
}

.pagination-info {
    font-size: 13px;
    color: var(--text-muted);
}

.pagination {
    display: flex;
    gap: 5px;
}

.pagination a, .pagination span {
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    color: var(--text-primary);
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    transition: all 0.2s;
}

.pagination a:hover {
    border-color: var(--primary);
    color: var(--primary-light);
}

.pagination .active {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border-color: var(--primary);
}

/* Email Modal */
.email-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(8px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.email-modal.active {
    display: flex;
}

.email-modal-content {
    background: #1e293b;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 20px;
    width: 100%;
    max-width: 800px;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    animation: modalSlide 0.3s ease;
}

@keyframes modalSlide {
    from { opacity: 0; transform: scale(0.95) translateY(20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.email-modal-header {
    padding: 24px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.email-modal-title {
    flex: 1;
}

.email-modal-title h3 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 8px;
}

.email-modal-meta {
    display: flex;
    gap: 20px;
    font-size: 13px;
    color: var(--text-muted);
}

.email-modal-meta span {
    display: flex;
    align-items: center;
    gap: 6px;
}

.email-modal-close {
    width: 40px;
    height: 40px;
    background: rgba(255,255,255,0.05);
    border: none;
    border-radius: 10px;
    color: var(--text-muted);
    font-size: 18px;
    cursor: pointer;
    transition: all 0.2s;
}

.email-modal-close:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.email-modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
}

.email-body-content {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    padding: 24px;
    line-height: 1.7;
    font-size: 14px;
    color: var(--text-secondary);
}

.email-body-content a {
    color: var(--primary-light);
}

/* Responsive */
@media (max-width: 768px) {
    .page-title-card {
        flex-direction: column;
        text-align: center;
    }
    
    .page-title-content {
        flex-direction: column;
    }
    
    .page-stats {
        width: 100%;
        justify-content: center;
    }
    
    .email-item {
        flex-direction: column;
    }
    
    .email-meta {
        flex-direction: row;
        width: 100%;
        justify-content: space-between;
    }
    
    .pagination-wrapper {
        flex-direction: column;
        gap: 15px;
    }
}
</style>

<div class="emails-page">
    <!-- Page Header -->
    <div class="page-title-card">
        <div class="page-title-content">
            <div class="page-title-icon">
                <i class="fas fa-envelope-open-text"></i>
            </div>
            <div class="page-title-text">
                <h1>E-Postalarım</h1>
                <p>Size gönderilen tüm sistem e-postalarını buradan görüntüleyebilirsiniz</p>
            </div>
        </div>
        <div class="page-stats">
            <div class="stat-item">
                <div class="stat-value"><?= number_format($totalEmails) ?></div>
                <div class="stat-label">Toplam E-Posta</div>
            </div>
        </div>
    </div>
    
    <!-- Email List -->
    <div class="email-list-card">
        <div class="email-list-header">
            <h3><i class="fas fa-inbox"></i> Gelen E-Postalar</h3>
        </div>
        
        <?php if (empty($emails)): ?>
            <div class="empty-state">
                <div class="empty-icon">📧</div>
                <h3>Henüz E-Posta Yok</h3>
                <p>Size gönderilen e-postalar burada listelenecektir.</p>
            </div>
        <?php else: ?>
            <?php foreach ($emails as $email): ?>
                <div class="email-item" onclick="showEmail(<?= htmlspecialchars(json_encode($email)) ?>)">
                    <div class="email-icon <?= $email['status'] === 'sent' ? 'success' : ($email['status'] === 'failed' ? 'failed' : '') ?>">
                        <i class="fas fa-<?= $email['status'] === 'sent' ? 'check' : ($email['status'] === 'failed' ? 'times' : 'clock') ?>"></i>
                    </div>
                    <div class="email-content">
                        <div class="email-subject"><?= htmlspecialchars($email['subject']) ?></div>
                        <?php if (!empty($email['template_name'])): ?>
                            <span class="email-template"><?= htmlspecialchars($email['template_name']) ?></span>
                        <?php endif; ?>
                        <div class="email-preview">
                            <?= htmlspecialchars(mb_substr(strip_tags($email['body'] ?? ''), 0, 150)) ?>...
                        </div>
                    </div>
                    <div class="email-meta">
                        <span class="email-date">
                            <i class="far fa-clock"></i>
                            <?= date('d.m.Y H:i', strtotime($email['created_at'])) ?>
                        </span>
                        <span class="email-status <?= $email['status'] ?>">
                            <?php if ($email['status'] === 'sent'): ?>
                                <i class="fas fa-check-circle"></i> Gönderildi
                            <?php elseif ($email['status'] === 'failed'): ?>
                                <i class="fas fa-times-circle"></i> Başarısız
                            <?php else: ?>
                                <i class="fas fa-clock"></i> Beklemede
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        Toplam <?= number_format($totalEmails) ?> e-posta, Sayfa <?= $page ?>/<?= $totalPages ?>
                    </div>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <a href="?page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Email Detail Modal -->
<div class="email-modal" id="emailModal">
    <div class="email-modal-content">
        <div class="email-modal-header">
            <div class="email-modal-title">
                <h3 id="modalSubject">E-Posta Konusu</h3>
                <div class="email-modal-meta">
                    <span><i class="far fa-calendar"></i> <span id="modalDate"></span></span>
                    <span><i class="far fa-envelope"></i> <span id="modalTemplate"></span></span>
                    <span id="modalStatus"></span>
                </div>
            </div>
            <button class="email-modal-close" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="email-modal-body">
            <div class="email-body-content" id="modalBody">
                <!-- E-posta içeriği buraya gelecek -->
            </div>
        </div>
    </div>
</div>

<script>
function showEmail(email) {
    document.getElementById('modalSubject').textContent = email.subject;
    document.getElementById('modalDate').textContent = new Date(email.created_at).toLocaleString('tr-TR');
    document.getElementById('modalTemplate').textContent = email.template_name || 'Manuel';
    
    // Status
    let statusHtml = '';
    if (email.status === 'sent') {
        statusHtml = '<span class="email-status sent"><i class="fas fa-check-circle"></i> Gönderildi</span>';
    } else if (email.status === 'failed') {
        statusHtml = '<span class="email-status failed"><i class="fas fa-times-circle"></i> Başarısız</span>';
    } else {
        statusHtml = '<span class="email-status pending"><i class="fas fa-clock"></i> Beklemede</span>';
    }
    document.getElementById('modalStatus').innerHTML = statusHtml;
    
    // Body - HTML içeriği güvenli şekilde göster
    const bodyContent = email.body || 'E-posta içeriği bulunamadı.';
    document.getElementById('modalBody').innerHTML = bodyContent;
    
    document.getElementById('emailModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('emailModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Modal dışına tıklayınca kapat
document.getElementById('emailModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// ESC tuşu ile kapat
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>

