<?php
/**
 * WHMVM - Admin İptal Talepleri Yönetimi
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

$pageTitle = 'İptal Talepleri';
$currentPage = 'cancellations';
$db = Database::getInstance();
$adminId = $_SESSION['admin_id'];

$message = '';
$messageType = '';

// Talep işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $adminNotes = trim($_POST['admin_notes'] ?? '');
    
    if ($requestId && in_array($action, ['approve', 'reject'])) {
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        
        // Talebi güncelle
        $stmt = $db->prepare("
            UPDATE cancellation_requests 
            SET status = ?, admin_id = ?, admin_notes = ?, processed_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$newStatus, $adminId, $adminNotes, $requestId]);
        
        // Eğer onaylandıysa ve hemen iptal ise, hizmeti iptal et
        if ($action === 'approve') {
            $request = $db->prepare("SELECT * FROM cancellation_requests WHERE id = ?")->fetch(PDO::FETCH_ASSOC);
            if ($request) {
                $stmt = $db->prepare("SELECT * FROM cancellation_requests WHERE id = ?");
                $stmt->execute([$requestId]);
                $request = $stmt->fetch();
                
                if ($request && $request['cancel_type'] === 'immediate') {
                    // Hizmeti hemen iptal et
                    $db->prepare("UPDATE services SET status = 'cancelled' WHERE id = ?")->execute([$request['service_id']]);
                }
            }
        }
        
        $message = $action === 'approve' ? 'İptal talebi onaylandı.' : 'İptal talebi reddedildi.';
        $messageType = 'success';
    }
}

// Filtreleme
$statusFilter = $_GET['status'] ?? '';
$whereClause = $statusFilter ? "WHERE cr.status = '$statusFilter'" : "";

// Sayfalama
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Toplam sayı
$total = (int)$db->query("SELECT COUNT(*) FROM cancellation_requests cr $whereClause")->fetchColumn();
$totalPages = ceil($total / $perPage);

// İstatistikler
$stats = [
    'pending' => (int)$db->query("SELECT COUNT(*) FROM cancellation_requests WHERE status = 'pending'")->fetchColumn(),
    'approved' => (int)$db->query("SELECT COUNT(*) FROM cancellation_requests WHERE status = 'approved'")->fetchColumn(),
    'rejected' => (int)$db->query("SELECT COUNT(*) FROM cancellation_requests WHERE status = 'rejected'")->fetchColumn(),
];

// Talepleri çek
$requests = $db->query("
    SELECT cr.*, 
           s.domain, s.status as service_status,
           p.name as product_name,
           c.first_name, c.last_name, c.email,
           a.first_name as admin_first_name, a.last_name as admin_last_name
    FROM cancellation_requests cr
    LEFT JOIN services s ON cr.service_id = s.id
    LEFT JOIN products p ON s.product_id = p.id
    LEFT JOIN clients c ON cr.client_id = c.id
    LEFT JOIN admins a ON cr.admin_id = a.id
    $whereClause
    ORDER BY 
        CASE cr.status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 ELSE 3 END,
        cr.created_at DESC
    LIMIT $perPage OFFSET $offset
")->fetchAll();

include 'includes/header.php';
?>

<style>
.stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-icon.warning { background: #fef3c7; color: #f59e0b; }
.stat-icon.success { background: #d1fae5; color: #10b981; }
.stat-icon.danger { background: #fee2e2; color: #ef4444; }

.stat-value {
    font-size: 32px;
    font-weight: 800;
    color: var(--dark);
}

.stat-label {
    font-size: 14px;
    color: var(--gray);
}

.filter-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.filter-tab {
    padding: 10px 20px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
    background: #f1f5f9;
    color: var(--dark);
}

.filter-tab:hover {
    background: #e2e8f0;
}

.filter-tab.active {
    background: var(--primary);
    color: white;
}

.request-card {
    background: white;
    border-radius: 16px;
    margin-bottom: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

.request-header {
    padding: 20px 25px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.request-service {
    display: flex;
    align-items: center;
    gap: 15px;
}

.request-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
}

.request-info h4 {
    font-size: 16px;
    font-weight: 700;
    margin: 0 0 4px;
}

.request-info p {
    font-size: 13px;
    color: var(--gray);
    margin: 0;
}

.request-meta {
    display: flex;
    gap: 20px;
    align-items: center;
}

.request-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.request-badge.pending {
    background: #fef3c7;
    color: #92400e;
}

.request-badge.approved {
    background: #d1fae5;
    color: #065f46;
}

.request-badge.rejected {
    background: #fee2e2;
    color: #991b1b;
}

.request-badge.immediate {
    background: #fee2e2;
    color: #991b1b;
}

.request-badge.end_of_billing {
    background: #dbeafe;
    color: #1e40af;
}

.request-body {
    padding: 25px;
}

.request-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
}

.request-section h5 {
    font-size: 12px;
    text-transform: uppercase;
    color: var(--gray);
    letter-spacing: 0.5px;
    margin-bottom: 10px;
}

.request-section p {
    font-size: 14px;
    margin: 0;
}

.request-reason {
    background: #f8fafc;
    padding: 15px;
    border-radius: 10px;
    font-size: 14px;
    line-height: 1.7;
    color: #374151;
    margin-top: 20px;
}

.request-actions {
    padding: 20px 25px;
    border-top: 1px solid var(--border);
    background: #f8fafc;
}

.action-form {
    display: flex;
    gap: 15px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.form-group {
    flex: 1;
    min-width: 250px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 8px;
}

.form-control {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
}

.btn-group {
    display: flex;
    gap: 10px;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover {
    background: #059669;
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
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

.processed-info {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f1f5f9;
    border-radius: 10px;
}

.processed-info i {
    font-size: 20px;
}

.processed-info.approved i { color: var(--success); }
.processed-info.rejected i { color: var(--danger); }

.processed-info div p {
    margin: 0;
    font-size: 14px;
}

.processed-info div small {
    color: var(--gray);
    font-size: 12px;
}

.empty-state {
    text-align: center;
    padding: 60px 40px;
    color: var(--gray);
}

.empty-state .icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.empty-state h3 {
    font-size: 20px;
    color: var(--dark);
    margin-bottom: 10px;
}

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

@media (max-width: 768px) {
    .stats-row {
        grid-template-columns: 1fr;
    }
    
    .request-grid {
        grid-template-columns: 1fr;
    }
    
    .action-form {
        flex-direction: column;
    }
    
    .form-group {
        width: 100%;
    }
}
</style>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        ✅ <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- İstatistikler -->
<div class="stats-row">
    <div class="stat-card">
        <div class="stat-icon warning">⏳</div>
        <div>
            <div class="stat-value"><?= $stats['pending'] ?></div>
            <div class="stat-label">Bekleyen Talep</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success">✅</div>
        <div>
            <div class="stat-value"><?= $stats['approved'] ?></div>
            <div class="stat-label">Onaylanan</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon danger">❌</div>
        <div>
            <div class="stat-value"><?= $stats['rejected'] ?></div>
            <div class="stat-label">Reddedilen</div>
        </div>
    </div>
</div>

<!-- Filtre Tabs -->
<div class="filter-tabs">
    <a href="cancellations.php" class="filter-tab <?= !$statusFilter ? 'active' : '' ?>">Tümü (<?= $total ?>)</a>
    <a href="?status=pending" class="filter-tab <?= $statusFilter === 'pending' ? 'active' : '' ?>">Bekleyen (<?= $stats['pending'] ?>)</a>
    <a href="?status=approved" class="filter-tab <?= $statusFilter === 'approved' ? 'active' : '' ?>">Onaylanan (<?= $stats['approved'] ?>)</a>
    <a href="?status=rejected" class="filter-tab <?= $statusFilter === 'rejected' ? 'active' : '' ?>">Reddedilen (<?= $stats['rejected'] ?>)</a>
</div>

<!-- Talepler -->
<?php if (empty($requests)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="icon">📋</div>
            <h3>İptal talebi bulunamadı</h3>
            <p>Henüz işlenecek iptal talebi yok.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($requests as $request): ?>
        <div class="request-card">
            <div class="request-header">
                <div class="request-service">
                    <div class="request-icon">
                        <i class="fas fa-server"></i>
                    </div>
                    <div class="request-info">
                        <h4><?= htmlspecialchars($request['product_name'] ?? 'Hizmet') ?></h4>
                        <p><?= htmlspecialchars($request['domain'] ?? '-') ?></p>
                    </div>
                </div>
                <div class="request-meta">
                    <span class="request-badge <?= $request['cancel_type'] ?>">
                        <?= $request['cancel_type'] === 'immediate' ? '⚡ Hemen İptal' : '📅 Dönem Sonu' ?>
                    </span>
                    <span class="request-badge <?= $request['status'] ?>">
                        <?php
                        echo match($request['status']) {
                            'pending' => '⏳ Beklemede',
                            'approved' => '✅ Onaylandı',
                            'rejected' => '❌ Reddedildi',
                            default => $request['status']
                        };
                        ?>
                    </span>
                </div>
            </div>
            
            <div class="request-body">
                <div class="request-grid">
                    <div class="request-section">
                        <h5>Müşteri Bilgileri</h5>
                        <p>
                            <strong><?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?></strong><br>
                            <a href="mailto:<?= htmlspecialchars($request['email']) ?>"><?= htmlspecialchars($request['email']) ?></a>
                        </p>
                    </div>
                    <div class="request-section">
                        <h5>Talep Tarihi</h5>
                        <p><?= date('d.m.Y H:i', strtotime($request['created_at'])) ?></p>
                    </div>
                </div>
                
                <div class="request-reason">
                    <strong>İptal Sebebi:</strong><br>
                    <?= nl2br(htmlspecialchars($request['reason'])) ?>
                </div>
            </div>
            
            <div class="request-actions">
                <?php if ($request['status'] === 'pending'): ?>
                    <form method="POST" class="action-form">
                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                        <div class="form-group">
                            <label>Yönetici Notu (Opsiyonel)</label>
                            <input type="text" name="admin_notes" class="form-control" placeholder="Müşteriye iletilecek not...">
                        </div>
                        <div class="btn-group">
                            <button type="submit" name="action" value="approve" class="btn btn-success" onclick="return confirm('Bu iptal talebini onaylamak istediğinizden emin misiniz?')">
                                <i class="fas fa-check"></i> Onayla
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger" onclick="return confirm('Bu iptal talebini reddetmek istediğinizden emin misiniz?')">
                                <i class="fas fa-times"></i> Reddet
                            </button>
                            <a href="client-edit.php?id=<?= $request['client_id'] ?>" class="btn btn-outline">
                                <i class="fas fa-user"></i> Müşteri
                            </a>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="processed-info <?= $request['status'] ?>">
                        <i class="fas fa-<?= $request['status'] === 'approved' ? 'check-circle' : 'times-circle' ?>"></i>
                        <div>
                            <p>
                                <strong><?= $request['status'] === 'approved' ? 'Onaylandı' : 'Reddedildi' ?></strong>
                                <?= $request['admin_first_name'] ? ' - ' . htmlspecialchars($request['admin_first_name'] . ' ' . $request['admin_last_name']) : '' ?>
                            </p>
                            <small>
                                <?= $request['processed_at'] ? date('d.m.Y H:i', strtotime($request['processed_at'])) : '' ?>
                                <?= $request['admin_notes'] ? ' • ' . htmlspecialchars($request['admin_notes']) : '' ?>
                            </small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    
    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="display: flex; gap: 5px; justify-content: center; margin-top: 20px;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&status=<?= $statusFilter ?>" 
               class="btn <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>" 
               style="padding: 8px 14px;">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

