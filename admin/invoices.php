<?php
/**
 * WHMVM - Admin Fatura Yönetimi
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Mail.php';
require_once dirname(__DIR__) . '/includes/ClientLog.php';
require_once dirname(__DIR__) . '/includes/Affiliate.php';
require_once dirname(__DIR__) . '/includes/OrderLog.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Faturalar';
$currentPage = 'invoices';

// İşlemler
$message = '';
$messageType = '';

// İptal işlemi
if (isset($_POST['cancel_invoice'])) {
    $invoiceId = (int)$_POST['invoice_id'];
    Database::query("UPDATE invoices SET status = 'cancelled' WHERE id = ?", [$invoiceId]);
    $message = 'Fatura iptal edildi.';
    $messageType = 'warning';
}

// Silme işlemi
if (isset($_POST['delete_invoice'])) {
    $invoiceId = (int)$_POST['invoice_id'];
    // Önce fatura kalemlerini sil
    Database::query("DELETE FROM invoice_items WHERE invoice_id = ?", [$invoiceId]);
    // Sonra faturayı sil
    Database::query("DELETE FROM invoices WHERE id = ?", [$invoiceId]);
    $message = 'Fatura kalıcı olarak silindi.';
    $messageType = 'success';
}

// Ödendi olarak işaretle
if (isset($_POST['mark_paid'])) {
    $invoiceId = (int)$_POST['invoice_id'];
    $invoice = Database::fetch("
        SELECT i.*, c.first_name, c.last_name, c.email 
        FROM invoices i 
        LEFT JOIN clients c ON i.client_id = c.id 
        WHERE i.id = ?
    ", [$invoiceId]);
    
    Database::query("UPDATE invoices SET status = 'paid', amount_paid = ?, paid_date = NOW() WHERE id = ?", [$invoice['total'], $invoiceId]);
    
    // Affiliate komisyonu hesapla
    try {
        Affiliate::processCommission(
            (int)$invoice['client_id'],
            null,
            $invoiceId,
            (float)$invoice['total']
        );
    } catch (Throwable $e) {
        // Affiliate hatası işlemi engellemesin
    }
    
    // Müşteri logu - Fatura ödendi
    ClientLog::invoicePaid(
        (int)$invoice['client_id'], 
        $invoice['invoice_number'], 
        (float)$invoice['total'], 
        $invoice['currency'] ?? 'TRY'
    );
    
    // Sipariş logu - Ödeme alındı
    // Fatura ile ilişkili siparişi bul
    $orderId = Database::fetchColumn("SELECT order_id FROM invoices WHERE id = ?", [$invoiceId]);
    if ($orderId) {
        OrderLog::paymentReceived((int)$orderId, (float)$invoice['total'], (int)$invoice['client_id']);
    }
    
    // Müşteriye ödeme bildirimi gönder
    try {
        Mail::sendTemplate('invoice_paid', $invoice['email'], [
            'client_name' => $invoice['first_name'] . ' ' . $invoice['last_name'],
            'invoice_id' => $invoice['invoice_number'],
            'payment_amount' => number_format($invoice['total'], 2, ',', '.'),
            'payment_date' => date('d.m.Y H:i'),
            'transaction_id' => 'TRX-' . $invoiceId . '-' . date('YmdHis')
        ], $invoice['first_name']);
    } catch (Throwable $e) {
        // Mail hatası işlemi engellemesin
    }
    
    $message = 'Fatura ödendi olarak işaretlendi.';
    $messageType = 'success';
}

// Sayfalama
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

$where = "WHERE 1=1";
$params = [];

if ($statusFilter) {
    $where .= " AND i.status = ?";
    $params[] = $statusFilter;
}

if ($searchQuery) {
    $where .= " AND (i.invoice_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

$total = (int)Database::fetchColumn("SELECT COUNT(*) FROM invoices i LEFT JOIN clients c ON i.client_id = c.id $where", $params);
$totalPages = (int)ceil($total / $perPage);

$invoices = Database::fetchAll("
    SELECT i.*, c.first_name, c.last_name, c.email, c.company_name
    FROM invoices i 
    LEFT JOIN clients c ON i.client_id = c.id 
    $where
    ORDER BY i.created_at DESC 
    LIMIT $perPage OFFSET $offset
", $params);

// İstatistikler
$stats = [
    'total' => (int)Database::fetchColumn("SELECT COUNT(*) FROM invoices"),
    'unpaid' => (int)Database::fetchColumn("SELECT COUNT(*) FROM invoices WHERE status = 'unpaid'"),
    'paid' => (int)Database::fetchColumn("SELECT COUNT(*) FROM invoices WHERE status = 'paid'"),
    'cancelled' => (int)Database::fetchColumn("SELECT COUNT(*) FROM invoices WHERE status = 'cancelled'"),
    'totalUnpaid' => (float)Database::fetchColumn("SELECT COALESCE(SUM(total - amount_paid), 0) FROM invoices WHERE status = 'unpaid'"),
    'totalPaid' => (float)Database::fetchColumn("SELECT COALESCE(SUM(amount_paid), 0) FROM invoices WHERE status = 'paid'"),
    'overdue' => (int)Database::fetchColumn("SELECT COUNT(*) FROM invoices WHERE status = 'unpaid' AND due_date < CURDATE()")
];

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 20px;
}

.page-header h1 {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-header h1 i {
    color: var(--primary);
}

.header-actions {
    display: flex;
    gap: 12px;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
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

.stat-icon.blue { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
.stat-icon.orange { background: linear-gradient(135deg, #f97316, #ea580c); color: white; }
.stat-icon.green { background: linear-gradient(135deg, #22c55e, #16a34a); color: white; }
.stat-icon.red { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }

.stat-info h3 {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 4px;
}

.stat-info span {
    font-size: 13px;
    color: var(--gray);
}

.stat-info .money {
    font-size: 14px;
    color: var(--primary);
    font-weight: 600;
}

/* Filter Bar */
.filter-bar {
    background: white;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
    display: flex;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-tabs {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    color: var(--gray);
    background: var(--light);
    border: 1px solid transparent;
    transition: all 0.3s;
}

.filter-tab:hover {
    color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
}

.filter-tab.active {
    color: white;
    background: var(--primary);
}

.filter-tab .count {
    display: inline-block;
    padding: 2px 8px;
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
    font-size: 12px;
    margin-left: 6px;
}

.filter-tab:not(.active) .count {
    background: var(--border);
}

.search-box {
    flex: 1;
    min-width: 250px;
    position: relative;
}

.search-box input {
    width: 100%;
    padding: 12px 16px 12px 44px;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s;
}

.search-box input:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.search-box i {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray);
}

/* Invoice Table */
.invoice-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
}

.invoice-table {
    width: 100%;
    border-collapse: collapse;
}

.invoice-table th {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    padding: 16px 20px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border);
}

.invoice-table td {
    padding: 18px 20px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.invoice-table tr:last-child td {
    border-bottom: none;
}

.invoice-table tr:hover {
    background: #fafbfc;
}

/* Invoice Number */
.invoice-number {
    display: flex;
    align-items: center;
    gap: 12px;
}

.invoice-icon {
    width: 42px;
    height: 42px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
}

.invoice-number-text {
    font-weight: 600;
    color: var(--dark);
    font-size: 14px;
}

.invoice-date {
    font-size: 12px;
    color: var(--gray);
    margin-top: 2px;
}

/* Client Info */
.client-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.client-avatar {
    width: 38px;
    height: 38px;
    background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: #64748b;
    font-size: 14px;
}

.client-name {
    font-weight: 500;
    color: var(--dark);
    font-size: 14px;
}

.client-email {
    font-size: 12px;
    color: var(--gray);
}

/* Amount */
.amount-cell {
    text-align: right;
}

.amount-total {
    font-size: 16px;
    font-weight: 700;
    color: var(--dark);
}

.amount-paid {
    font-size: 12px;
    color: var(--gray);
}

.amount-paid.full {
    color: #22c55e;
}

/* Due Date */
.due-date {
    font-size: 14px;
    color: var(--dark);
}

.due-date.overdue {
    color: #ef4444;
    font-weight: 600;
}

.overdue-badge {
    display: inline-block;
    padding: 4px 10px;
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    margin-top: 4px;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.status-badge.paid {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
}

.status-badge.unpaid {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.status-badge.cancelled {
    background: rgba(100, 116, 139, 0.1);
    color: #64748b;
}

.status-badge.refunded {
    background: rgba(139, 92, 246, 0.1);
    color: #8b5cf6;
}

.status-badge.draft {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

.action-btn {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
    color: var(--gray);
    text-decoration: none;
}

.action-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.action-btn.view:hover { border-color: #3b82f6; color: #3b82f6; background: rgba(59, 130, 246, 0.05); }
.action-btn.paid:hover { border-color: #22c55e; color: #22c55e; background: rgba(34, 197, 94, 0.05); }
.action-btn.cancel:hover { border-color: #f59e0b; color: #f59e0b; background: rgba(245, 158, 11, 0.05); }
.action-btn.delete:hover { border-color: #ef4444; color: #ef4444; background: rgba(239, 68, 68, 0.05); }

/* Dropdown Menu */
.dropdown {
    position: relative;
}

.dropdown-toggle {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: var(--gray);
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    min-width: 180px;
    z-index: 100;
    display: none;
    overflow: hidden;
}

.dropdown-menu.show {
    display: block;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    font-size: 14px;
    color: var(--dark);
    text-decoration: none;
    border: none;
    background: none;
    width: 100%;
    cursor: pointer;
    transition: all 0.2s;
}

.dropdown-item:hover {
    background: var(--light);
}

.dropdown-item.danger {
    color: #ef4444;
}

.dropdown-item.danger:hover {
    background: rgba(239, 68, 68, 0.05);
}

.dropdown-item.warning {
    color: #f59e0b;
}

.dropdown-item.success {
    color: #22c55e;
}

.dropdown-divider {
    height: 1px;
    background: var(--border);
    margin: 4px 0;
}

/* Empty State */
.empty-state {
    padding: 80px 40px;
    text-align: center;
}

.empty-state .icon {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.3;
}

.empty-state h3 {
    font-size: 20px;
    color: var(--dark);
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--gray);
    font-size: 14px;
}

/* Pagination */
.pagination-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-top: 1px solid var(--border);
}

.pagination-info {
    font-size: 14px;
    color: var(--gray);
}

.pagination {
    display: flex;
    gap: 6px;
}

.pagination a {
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    color: var(--dark);
    background: var(--light);
    transition: all 0.3s;
}

.pagination a:hover {
    background: var(--primary);
    color: white;
}

.pagination a.active {
    background: var(--primary);
    color: white;
}

/* Alert */
.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
}

.alert-success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #16a34a;
}

.alert-warning {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    color: #d97706;
}

.alert-danger {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #dc2626;
}

/* Confirm Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-overlay.show {
    display: flex;
}

.modal-box {
    background: white;
    border-radius: 20px;
    padding: 30px;
    max-width: 420px;
    width: 90%;
    text-align: center;
    animation: modalSlide 0.3s ease;
}

@keyframes modalSlide {
    from { opacity: 0; transform: scale(0.9) translateY(-20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.modal-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    margin: 0 auto 20px;
}

.modal-icon.warning {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.modal-icon.danger {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.modal-box h3 {
    font-size: 20px;
    color: var(--dark);
    margin-bottom: 10px;
}

.modal-box p {
    color: var(--gray);
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 24px;
}

.modal-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.modal-btn {
    padding: 12px 28px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.3s;
}

.modal-btn.cancel {
    background: var(--light);
    color: var(--dark);
}

.modal-btn.cancel:hover {
    background: var(--border);
}

.modal-btn.confirm-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.modal-btn.confirm-danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.modal-btn.confirm-warning:hover,
.modal-btn.confirm-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: 1fr; }
    .filter-bar { flex-direction: column; align-items: stretch; }
    .filter-tabs { justify-content: center; }
    .invoice-table { display: block; overflow-x: auto; }
    .page-header { flex-direction: column; align-items: flex-start; }
}
</style>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'times-circle') ?>"></i>
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header">
    <h1><i class="fas fa-file-invoice-dollar"></i> Fatura Yönetimi</h1>
    <div class="header-actions">
        <a href="invoice-create.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Yeni Fatura
        </a>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-file-invoice"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['total'] ?></h3>
            <span>Toplam Fatura</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['unpaid'] ?></h3>
            <span>Ödenmemiş</span>
            <div class="money">₺<?= number_format($stats['totalUnpaid'], 2) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['paid'] ?></h3>
            <span>Ödenmiş</span>
            <div class="money">₺<?= number_format($stats['totalPaid'], 2) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['overdue'] ?></h3>
            <span>Gecikmiş Fatura</span>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <div class="filter-tabs">
        <a href="?status=" class="filter-tab <?= !$statusFilter ? 'active' : '' ?>">
            Tümü <span class="count"><?= $stats['total'] ?></span>
        </a>
        <a href="?status=unpaid" class="filter-tab <?= $statusFilter === 'unpaid' ? 'active' : '' ?>">
            Ödenmemiş <span class="count"><?= $stats['unpaid'] ?></span>
        </a>
        <a href="?status=paid" class="filter-tab <?= $statusFilter === 'paid' ? 'active' : '' ?>">
            Ödenmiş <span class="count"><?= $stats['paid'] ?></span>
        </a>
        <a href="?status=cancelled" class="filter-tab <?= $statusFilter === 'cancelled' ? 'active' : '' ?>">
            İptal <span class="count"><?= $stats['cancelled'] ?></span>
        </a>
    </div>
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Fatura no, müşteri ara..." value="<?= htmlspecialchars($searchQuery) ?>" 
               onkeypress="if(event.key==='Enter') window.location='?search='+this.value+'&status=<?= $statusFilter ?>'">
    </div>
</div>

<!-- Invoice Table -->
<div class="invoice-card">
    <?php if (empty($invoices)): ?>
        <div class="empty-state">
            <div class="icon">📄</div>
            <h3>Fatura bulunamadı</h3>
            <p>Henüz fatura oluşturulmamış veya filtreye uygun fatura yok.</p>
        </div>
    <?php else: ?>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Fatura</th>
                    <th>Müşteri</th>
                    <th style="text-align: right;">Tutar</th>
                    <th>Vade Tarihi</th>
                    <th>Durum</th>
                    <th style="text-align: right;">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $invoice): 
                    $isOverdue = $invoice['status'] === 'unpaid' && strtotime($invoice['due_date']) < time();
                    $statusClass = match($invoice['status']) {
                        'paid' => 'paid',
                        'unpaid' => 'unpaid',
                        'cancelled' => 'cancelled',
                        'refunded' => 'refunded',
                        default => 'draft'
                    };
                    $statusText = match($invoice['status']) {
                        'paid' => 'Ödendi',
                        'unpaid' => 'Ödenmedi',
                        'cancelled' => 'İptal',
                        'refunded' => 'İade',
                        'draft' => 'Taslak',
                        default => $invoice['status']
                    };
                    $statusIcon = match($invoice['status']) {
                        'paid' => 'fa-check-circle',
                        'unpaid' => 'fa-clock',
                        'cancelled' => 'fa-times-circle',
                        'refunded' => 'fa-undo',
                        default => 'fa-file'
                    };
                    $initials = strtoupper(substr($invoice['first_name'] ?? 'X', 0, 1) . substr($invoice['last_name'] ?? 'X', 0, 1));
                ?>
                <tr>
                    <td>
                        <div class="invoice-number">
                            <div class="invoice-icon">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                            <div>
                                <div class="invoice-number-text"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
                                <div class="invoice-date"><?= date('d.m.Y H:i', strtotime($invoice['created_at'])) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="client-info">
                            <div class="client-avatar"><?= $initials ?></div>
                            <div>
                                <div class="client-name"><?= htmlspecialchars(($invoice['first_name'] ?? '') . ' ' . ($invoice['last_name'] ?? '')) ?></div>
                                <div class="client-email"><?= htmlspecialchars($invoice['email'] ?? '') ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="amount-cell">
                            <div class="amount-total"><?= number_format((float)$invoice['total'], 2) ?> <?= $invoice['currency'] ?></div>
                            <div class="amount-paid <?= (float)$invoice['amount_paid'] >= (float)$invoice['total'] ? 'full' : '' ?>">
                                Ödenen: <?= number_format((float)$invoice['amount_paid'], 2) ?> <?= $invoice['currency'] ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="due-date <?= $isOverdue ? 'overdue' : '' ?>">
                            <?= date('d.m.Y', strtotime($invoice['due_date'])) ?>
                        </div>
                        <?php if ($isOverdue): ?>
                            <div class="overdue-badge">
                                <i class="fas fa-exclamation-triangle"></i> Gecikmiş
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge <?= $statusClass ?>">
                            <i class="fas <?= $statusIcon ?>"></i>
                            <?= $statusText ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="invoice-view.php?id=<?= $invoice['id'] ?>" class="action-btn view" title="Görüntüle">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if ($invoice['status'] === 'unpaid'): ?>
                                <button type="button" class="action-btn paid" title="Ödendi İşaretle" 
                                        onclick="confirmAction('paid', <?= $invoice['id'] ?>, '<?= htmlspecialchars($invoice['invoice_number']) ?>')">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button type="button" class="action-btn cancel" title="İptal Et" 
                                        onclick="confirmAction('cancel', <?= $invoice['id'] ?>, '<?= htmlspecialchars($invoice['invoice_number']) ?>')">
                                    <i class="fas fa-ban"></i>
                                </button>
                            <?php endif; ?>
                            <button type="button" class="action-btn delete" title="Sil" 
                                    onclick="confirmAction('delete', <?= $invoice['id'] ?>, '<?= htmlspecialchars($invoice['invoice_number']) ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination-wrapper">
            <div class="pagination-info">
                Toplam <?= $total ?> faturadan <?= $offset + 1 ?>-<?= min($offset + $perPage, $total) ?> arası gösteriliyor
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&status=<?= $statusFilter ?>&search=<?= urlencode($searchQuery) ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&status=<?= $statusFilter ?>&search=<?= urlencode($searchQuery) ?>" 
                       class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&status=<?= $statusFilter ?>&search=<?= urlencode($searchQuery) ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Confirm Modal -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal-box">
        <div class="modal-icon" id="modalIcon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 id="modalTitle">Emin misiniz?</h3>
        <p id="modalMessage">Bu işlemi gerçekleştirmek istediğinizden emin misiniz?</p>
        <div class="modal-actions">
            <button type="button" class="modal-btn cancel" onclick="closeModal()">İptal</button>
            <form method="POST" id="actionForm" style="display: inline;">
                <input type="hidden" name="invoice_id" id="actionInvoiceId">
                <button type="submit" class="modal-btn" id="confirmBtn" name="">Onayla</button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmAction(action, invoiceId, invoiceNumber) {
    const modal = document.getElementById('confirmModal');
    const modalIcon = document.getElementById('modalIcon');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const confirmBtn = document.getElementById('confirmBtn');
    const actionInvoiceId = document.getElementById('actionInvoiceId');
    
    actionInvoiceId.value = invoiceId;
    
    if (action === 'cancel') {
        modalIcon.className = 'modal-icon warning';
        modalIcon.innerHTML = '<i class="fas fa-ban"></i>';
        modalTitle.textContent = 'Faturayı İptal Et';
        modalMessage.innerHTML = '<strong>' + invoiceNumber + '</strong> numaralı faturayı iptal etmek istediğinizden emin misiniz?<br><br>Bu işlem geri alınabilir.';
        confirmBtn.className = 'modal-btn confirm-warning';
        confirmBtn.textContent = 'Evet, İptal Et';
        confirmBtn.name = 'cancel_invoice';
    } else if (action === 'delete') {
        modalIcon.className = 'modal-icon danger';
        modalIcon.innerHTML = '<i class="fas fa-trash"></i>';
        modalTitle.textContent = 'Faturayı Kalıcı Olarak Sil';
        modalMessage.innerHTML = '<strong>' + invoiceNumber + '</strong> numaralı faturayı kalıcı olarak silmek istediğinizden emin misiniz?<br><br><strong style="color:#ef4444;">⚠️ Bu işlem geri alınamaz!</strong>';
        confirmBtn.className = 'modal-btn confirm-danger';
        confirmBtn.textContent = 'Evet, Kalıcı Olarak Sil';
        confirmBtn.name = 'delete_invoice';
    } else if (action === 'paid') {
        modalIcon.className = 'modal-icon warning';
        modalIcon.innerHTML = '<i class="fas fa-check-circle" style="color:#22c55e;"></i>';
        modalTitle.textContent = 'Ödendi Olarak İşaretle';
        modalMessage.innerHTML = '<strong>' + invoiceNumber + '</strong> numaralı faturayı ödendi olarak işaretlemek istediğinizden emin misiniz?';
        confirmBtn.className = 'modal-btn confirm-warning';
        confirmBtn.style.background = 'linear-gradient(135deg, #22c55e, #16a34a)';
        confirmBtn.textContent = 'Evet, Ödendi İşaretle';
        confirmBtn.name = 'mark_paid';
    }
    
    modal.classList.add('show');
}

function closeModal() {
    document.getElementById('confirmModal').classList.remove('show');
}

// Close modal on outside click
document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
