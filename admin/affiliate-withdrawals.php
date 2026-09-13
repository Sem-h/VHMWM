<?php
/**
 * WHMVM Admin - Affiliate Çekim Talepleri
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

$pageTitle = 'Çekim Talepleri';
$currentPage = 'affiliate-withdrawals';

$message = '';
$messageType = 'success';

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $withdrawalId = (int)$_POST['withdrawal_id'];
    $action = $_POST['action'] ?? '';
    $adminNotes = $_POST['admin_notes'] ?? '';
    
    // Çekim bilgilerini al
    $withdrawal = Database::fetch("SELECT * FROM affiliate_withdrawals WHERE id = ?", [$withdrawalId]);
    
    if ($withdrawal) {
        if ($action === 'approve') {
            // Onayla ve işleme al
            Database::query(
                "UPDATE affiliate_withdrawals SET status = 'processing', admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ?",
                [$adminNotes, $_SESSION['admin_id'], $withdrawalId]
            );
            $message = 'Çekim talebi işleme alındı.';
        } elseif ($action === 'complete') {
            // Tamamla
            Database::query(
                "UPDATE affiliate_withdrawals SET status = 'completed', admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ?",
                [$adminNotes, $_SESSION['admin_id'], $withdrawalId]
            );
            
            // Toplam çekilen tutarı güncelle
            Database::query(
                "UPDATE affiliates SET total_withdrawn = total_withdrawn + ? WHERE id = ?",
                [$withdrawal['amount'], $withdrawal['affiliate_id']]
            );
            
            $message = 'Çekim talebi tamamlandı.';
        } elseif ($action === 'reject') {
            // Reddet ve bakiyeyi iade et
            Database::query(
                "UPDATE affiliate_withdrawals SET status = 'rejected', admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ?",
                [$adminNotes, $_SESSION['admin_id'], $withdrawalId]
            );
            
            // Bakiyeyi geri ekle
            Database::query(
                "UPDATE affiliates SET balance = balance + ? WHERE id = ?",
                [$withdrawal['amount'], $withdrawal['affiliate_id']]
            );
            
            $message = 'Çekim talebi reddedildi ve bakiye iade edildi.';
        }
    }
}

// Filtreler
$statusFilter = $_GET['status'] ?? '';

$where = "WHERE 1=1";
$params = [];

if ($statusFilter) {
    $where .= " AND w.status = ?";
    $params[] = $statusFilter;
}

// Sayfalama
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$total = (int)Database::fetchColumn(
    "SELECT COUNT(*) FROM affiliate_withdrawals w $where",
    $params
);
$totalPages = (int)ceil($total / $perPage);

$withdrawals = Database::fetchAll(
    "SELECT w.*, a.affiliate_code, c.first_name, c.last_name, c.email
     FROM affiliate_withdrawals w
     JOIN affiliates a ON w.affiliate_id = a.id
     JOIN clients c ON a.client_id = c.id
     $where
     ORDER BY 
        CASE w.status 
            WHEN 'pending' THEN 1 
            WHEN 'processing' THEN 2 
            ELSE 3 
        END,
        w.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

// İstatistikler
$stats = [
    'pending' => (int)Database::fetchColumn("SELECT COUNT(*) FROM affiliate_withdrawals WHERE status = 'pending'"),
    'processing' => (int)Database::fetchColumn("SELECT COUNT(*) FROM affiliate_withdrawals WHERE status = 'processing'"),
    'pending_amount' => (float)Database::fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM affiliate_withdrawals WHERE status IN ('pending', 'processing')"),
    'completed_amount' => (float)Database::fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM affiliate_withdrawals WHERE status = 'completed'")
];

include 'includes/header.php';
?>

<style>
/* Stats Row */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-box {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    display: flex;
    align-items: center;
    gap: 16px;
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-icon.yellow { background: #fef3c7; }
.stat-icon.blue { background: #dbeafe; }
.stat-icon.orange { background: #ffedd5; }
.stat-icon.green { background: #d1fae5; }

.stat-info h4 {
    font-size: 24px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 4px;
}

.stat-info p {
    font-size: 13px;
    color: var(--gray);
}

/* Filters */
.filters {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    gap: 15px;
    align-items: center;
}

.filters select {
    padding: 10px 15px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
}

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
}

.btn-sm { padding: 8px 14px; font-size: 13px; }
.btn-primary { background: var(--primary); color: white; }
.btn-success { background: var(--success); color: white; }
.btn-warning { background: var(--warning); color: white; }
.btn-danger { background: var(--danger); color: white; }
.btn-outline { background: transparent; border: 1px solid var(--border); color: var(--dark); }

/* Table */
.data-table {
    width: 100%;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.data-table table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    text-align: left;
    padding: 14px 18px;
    font-size: 12px;
    font-weight: 600;
    color: var(--gray);
    text-transform: uppercase;
    background: #f8fafc;
    border-bottom: 1px solid var(--border);
}

.data-table td {
    padding: 16px 18px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
}

.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #fafbfc; }

/* Badge */
.badge {
    display: inline-flex;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.badge-success { background: #d1fae5; color: #059669; }
.badge-warning { background: #fef3c7; color: #d97706; }
.badge-danger { background: #fee2e2; color: #dc2626; }
.badge-info { background: #dbeafe; color: #2563eb; }

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
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 500px;
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

textarea.form-control {
    min-height: 80px;
    resize: vertical;
}

/* Payment Details */
.payment-box {
    background: #f8fafc;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.payment-box h5 {
    font-size: 14px;
    margin-bottom: 10px;
}

.payment-box pre {
    background: white;
    padding: 10px;
    border-radius: 6px;
    font-size: 13px;
    white-space: pre-wrap;
    margin: 0;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.action-buttons .btn {
    flex: 1;
    justify-content: center;
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 60px 40px;
    background: white;
    border-radius: 12px;
}

.empty-state .icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.empty-state h3 {
    font-size: 20px;
    margin-bottom: 10px;
    color: var(--dark);
}

.empty-state p {
    color: var(--gray);
}

/* Pagination */
.pagination {
    display: flex;
    gap: 5px;
    justify-content: center;
    margin-top: 20px;
}

.pagination a {
    padding: 8px 14px;
    background: white;
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--dark);
    text-decoration: none;
    font-size: 14px;
}

.pagination a:hover, .pagination a.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
</style>

<?php if ($message): ?>
<div class="alert alert-success">
    <span>✓</span> <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-row">
    <div class="stat-box">
        <div class="stat-icon yellow">⏳</div>
        <div class="stat-info">
            <h4><?= number_format($stats['pending']) ?></h4>
            <p>Bekleyen Talep</p>
        </div>
    </div>
    <div class="stat-box">
        <div class="stat-icon blue">🔄</div>
        <div class="stat-info">
            <h4><?= number_format($stats['processing']) ?></h4>
            <p>İşleniyor</p>
        </div>
    </div>
    <div class="stat-box">
        <div class="stat-icon orange">💰</div>
        <div class="stat-info">
            <h4><?= number_format($stats['pending_amount'], 2) ?> ₺</h4>
            <p>Bekleyen Tutar</p>
        </div>
    </div>
    <div class="stat-box">
        <div class="stat-icon green">✅</div>
        <div class="stat-info">
            <h4><?= number_format($stats['completed_amount'], 2) ?> ₺</h4>
            <p>Ödenen Tutar</p>
        </div>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="filters">
    <select name="status">
        <option value="">Tüm Durumlar</option>
        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Beklemede</option>
        <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>İşleniyor</option>
        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Tamamlandı</option>
        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Reddedildi</option>
    </select>
    <button type="submit" class="btn btn-primary">🔍 Filtrele</button>
    <?php if ($statusFilter): ?>
        <a href="affiliate-withdrawals.php" class="btn btn-outline">✕ Temizle</a>
    <?php endif; ?>
</form>

<!-- Withdrawals Table -->
<?php if (empty($withdrawals)): ?>
<div class="empty-state">
    <div class="icon">💸</div>
    <h3>Çekim talebi bulunamadı</h3>
    <p>Henüz herhangi bir çekim talebi yok.</p>
</div>
<?php else: ?>
<div class="data-table">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Ortak</th>
                <th>Tutar</th>
                <th>Ödeme Yöntemi</th>
                <th>Talep Tarihi</th>
                <th>Durum</th>
                <th>İşlem</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($withdrawals as $wd): ?>
            <tr>
                <td>#<?= $wd['id'] ?></td>
                <td>
                    <strong><?= htmlspecialchars($wd['first_name'] . ' ' . $wd['last_name']) ?></strong><br>
                    <small style="color: var(--gray);"><?= htmlspecialchars($wd['affiliate_code']) ?></small>
                </td>
                <td><strong style="color: var(--primary);"><?= number_format((float)$wd['amount'], 2) ?> ₺</strong></td>
                <td>
                    <?= match($wd['payment_method']) {
                        'bank_transfer' => '🏦 Banka Havalesi',
                        'papara' => '💳 Papara',
                        'paypal' => '💰 PayPal',
                        default => $wd['payment_method']
                    } ?>
                </td>
                <td><?= date('d.m.Y H:i', strtotime($wd['created_at'])) ?></td>
                <td>
                    <?php 
                    $badgeClass = match($wd['status']) {
                        'completed' => 'success',
                        'processing' => 'info',
                        'rejected' => 'danger',
                        default => 'warning'
                    };
                    $statusText = match($wd['status']) {
                        'completed' => 'Tamamlandı',
                        'processing' => 'İşleniyor',
                        'rejected' => 'Reddedildi',
                        default => 'Beklemede'
                    };
                    ?>
                    <span class="badge badge-<?= $badgeClass ?>"><?= $statusText ?></span>
                </td>
                <td>
                    <?php if ($wd['status'] === 'pending' || $wd['status'] === 'processing'): ?>
                        <button class="btn btn-outline btn-sm" onclick="openModal(<?= htmlspecialchars(json_encode($wd)) ?>)">
                            ⚙️ İşlem
                        </button>
                    <?php else: ?>
                        <span style="color: var(--gray); font-size: 13px;">
                            <?= $wd['processed_at'] ? date('d.m.Y', strtotime($wd['processed_at'])) : '-' ?>
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?>&status=<?= $statusFilter ?>" 
           class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Process Modal -->
<div id="processModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>💸 Çekim Talebi İşleme</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="withdrawal_id" id="modal_withdrawal_id">
                <input type="hidden" name="action" id="modal_action">
                
                <div style="text-align: center; margin-bottom: 20px;">
                    <h2 style="color: var(--primary); margin-bottom: 5px;" id="modal_amount">0.00 ₺</h2>
                    <p style="color: var(--gray);" id="modal_affiliate_name">-</p>
                </div>
                
                <div class="payment-box">
                    <h5>💳 Ödeme Bilgileri</h5>
                    <p><strong>Yöntem:</strong> <span id="modal_payment_method"></span></p>
                    <pre id="modal_payment_details"></pre>
                </div>
                
                <div class="form-group">
                    <label>Admin Notu</label>
                    <textarea name="admin_notes" class="form-control" id="modal_notes" placeholder="İşlem notu (opsiyonel)"></textarea>
                </div>
                
                <div class="action-buttons" id="pending_actions">
                    <button type="submit" class="btn btn-success" onclick="document.getElementById('modal_action').value='approve'">
                        ✓ İşleme Al
                    </button>
                    <button type="submit" class="btn btn-danger" onclick="document.getElementById('modal_action').value='reject'">
                        ✕ Reddet
                    </button>
                </div>
                
                <div class="action-buttons" id="processing_actions" style="display: none;">
                    <button type="submit" class="btn btn-success" onclick="document.getElementById('modal_action').value='complete'">
                        ✓ Ödemeyi Tamamla
                    </button>
                    <button type="submit" class="btn btn-danger" onclick="document.getElementById('modal_action').value='reject'">
                        ✕ Reddet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(wd) {
    document.getElementById('processModal').classList.add('active');
    document.getElementById('modal_withdrawal_id').value = wd.id;
    document.getElementById('modal_amount').textContent = Number(wd.amount).toLocaleString('tr-TR', {minimumFractionDigits: 2}) + ' ₺';
    document.getElementById('modal_affiliate_name').textContent = wd.first_name + ' ' + wd.last_name + ' (' + wd.affiliate_code + ')';
    document.getElementById('modal_payment_method').textContent = 
        wd.payment_method === 'bank_transfer' ? 'Banka Havalesi' : 
        (wd.payment_method === 'papara' ? 'Papara' : 'PayPal');
    document.getElementById('modal_payment_details').textContent = wd.payment_details || 'Bilgi yok';
    document.getElementById('modal_notes').value = wd.admin_notes || '';
    
    // Show appropriate buttons based on status
    if (wd.status === 'pending') {
        document.getElementById('pending_actions').style.display = 'flex';
        document.getElementById('processing_actions').style.display = 'none';
    } else {
        document.getElementById('pending_actions').style.display = 'none';
        document.getElementById('processing_actions').style.display = 'flex';
    }
}

function closeModal() {
    document.getElementById('processModal').classList.remove('active');
}

document.getElementById('processModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php include 'includes/footer.php'; ?>

