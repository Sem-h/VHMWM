<?php
/**
 * WHMVM - Admin Sipariş Yönetimi
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

$pageTitle = 'Siparişler';
$currentPage = 'orders';

// İşlemler
$message = '';
$messageType = '';

// Durum güncelleme
if (isset($_POST['update_status'])) {
    $orderId = (int)$_POST['order_id'];
    $newStatus = $_POST['new_status'];
    
    // Sipariş aktif edilirse otomatik hizmet oluştur
    if ($newStatus === 'active') {
        // Sipariş bilgilerini al
        $order = Database::fetch("SELECT * FROM orders WHERE id = ?", [$orderId]);
        
        if ($order) {
            // Sipariş kalemlerini al
            $orderItems = Database::fetchAll("SELECT * FROM order_items WHERE order_id = ?", [$orderId]);
            
            // Uygun sunucuyu bul (en az hesabı olan aktif sunucu)
            $server = Database::fetch("
                SELECT s.*, 
                       COALESCE((SELECT COUNT(*) FROM services WHERE server_id = s.id AND status IN ('active', 'pending')), 0) as current_accounts
                FROM servers s 
                WHERE s.status = 'active' 
                ORDER BY current_accounts ASC 
                LIMIT 1
            ");
            
            $serverId = $server['id'] ?? null;
            
            foreach ($orderItems as $item) {
                // Bu sipariş kalemi için zaten hizmet var mı kontrol et
                $existingService = Database::fetch("
                    SELECT id FROM services WHERE order_id = ? AND product_id = ?
                ", [$orderId, $item['product_id']]);
                
                if (!$existingService && $item['product_id']) {
                    // Yeni hizmet oluştur
                    $nextDueDate = date('Y-m-d', strtotime('+1 month'));
                    
                    // Fatura dönemine göre sonraki ödeme tarihi
                    $billingCycle = $item['billing_cycle'] ?? 'monthly';
                    switch ($billingCycle) {
                        case 'quarterly': $nextDueDate = date('Y-m-d', strtotime('+3 months')); break;
                        case 'semiannually': $nextDueDate = date('Y-m-d', strtotime('+6 months')); break;
                        case 'annually': $nextDueDate = date('Y-m-d', strtotime('+1 year')); break;
                        case 'biennially': $nextDueDate = date('Y-m-d', strtotime('+2 years')); break;
                        case 'triennially': $nextDueDate = date('Y-m-d', strtotime('+3 years')); break;
                    }
                    
                    Database::query("
                        INSERT INTO services (
                            client_id, order_id, product_id, server_id, domain,
                            status, billing_cycle, amount, registration_date, next_due_date,
                            first_payment_amount
                        ) VALUES (?, ?, ?, ?, ?, 'active', ?, ?, CURDATE(), ?, ?)
                    ", [
                        $order['client_id'],
                        $orderId,
                        $item['product_id'],
                        $serverId,
                        $item['domain'] ?? null,
                        $billingCycle,
                        $item['unit_price'],
                        $nextDueDate,
                        $item['total']
                    ]);
                }
            }
            $message = 'Sipariş aktif edildi ve hizmetler oluşturuldu.';
        }
    } else {
        $message = 'Sipariş durumu güncellendi.';
    }
    
    Database::query("UPDATE orders SET status = ? WHERE id = ?", [$newStatus, $orderId]);
    $messageType = 'success';
}

// Sipariş silme
if (isset($_POST['delete_order'])) {
    $orderId = (int)$_POST['order_id'];
    Database::query("DELETE FROM order_items WHERE order_id = ?", [$orderId]);
    Database::query("DELETE FROM orders WHERE id = ?", [$orderId]);
    $message = 'Sipariş kalıcı olarak silindi.';
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
    $where .= " AND o.status = ?";
    $params[] = $statusFilter;
}

if ($searchQuery) {
    $where .= " AND (o.order_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

$total = (int)Database::fetchColumn("SELECT COUNT(*) FROM orders o LEFT JOIN clients c ON o.client_id = c.id $where", $params);
$totalPages = (int)ceil($total / $perPage);

$orders = Database::fetchAll("
    SELECT o.*, c.first_name, c.last_name, c.email, c.company_name
    FROM orders o 
    LEFT JOIN clients c ON o.client_id = c.id 
    $where
    ORDER BY o.created_at DESC 
    LIMIT $perPage OFFSET $offset
", $params);

// İstatistikler
$stats = [
    'total' => (int)Database::fetchColumn("SELECT COUNT(*) FROM orders"),
    'pending' => (int)Database::fetchColumn("SELECT COUNT(*) FROM orders WHERE status = 'pending'"),
    'active' => (int)Database::fetchColumn("SELECT COUNT(*) FROM orders WHERE status = 'active'"),
    'cancelled' => (int)Database::fetchColumn("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'"),
    'totalRevenue' => (float)Database::fetchColumn("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status IN ('active', 'completed')"),
    'todayOrders' => (int)Database::fetchColumn("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")
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

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--y-yuzey);
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
.stat-icon.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; }

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
    background: var(--y-yuzey);
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

/* Order Table */
.order-card {
    background: var(--y-yuzey);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
}

.order-table {
    width: 100%;
    border-collapse: collapse;
}

.order-table th {
    background: linear-gradient(135deg, var(--y-yuzey-2), var(--y-yuzey-2));
    padding: 16px 20px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border);
}

.order-table td {
    padding: 18px 20px;
    border-bottom: 1px solid var(--y-cizgi-soft);
    vertical-align: middle;
}

.order-table tr:last-child td {
    border-bottom: none;
}

.order-table tr:hover {
    background: var(--y-yuzey-2);
}

/* Order Number */
.order-number {
    display: flex;
    align-items: center;
    gap: 12px;
}

.order-icon {
    width: 42px;
    height: 42px;
    background: linear-gradient(135deg, #f97316, #ea580c);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
}

.order-number-text {
    font-weight: 600;
    color: var(--dark);
    font-size: 14px;
}

.order-date {
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
    background: linear-gradient(135deg, var(--y-cizgi), #cbd5e1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: var(--y-metin-3);
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

.amount-items {
    font-size: 12px;
    color: var(--gray);
}

/* Payment Method */
.payment-method {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    background: var(--light);
    border-radius: 8px;
    font-size: 13px;
    color: var(--dark);
}

.payment-method i {
    color: var(--primary);
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

.status-badge.pending {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.status-badge.processing {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.status-badge.active, .status-badge.completed {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
}

.status-badge.cancelled {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.status-badge.fraud {
    background: rgba(139, 92, 246, 0.1);
    color: #8b5cf6;
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
    background: var(--y-yuzey);
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

.action-btn.view:hover { border-color: #3b82f6; color: #3b82f6; }
.action-btn.approve:hover { border-color: #22c55e; color: #22c55e; }
.action-btn.cancel:hover { border-color: #f59e0b; color: #f59e0b; }
.action-btn.delete:hover { border-color: #ef4444; color: #ef4444; }

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

.pagination a:hover, .pagination a.active {
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
    background: var(--y-yuzey);
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

.modal-icon.warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.modal-icon.danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.modal-icon.success { background: rgba(34, 197, 94, 0.1); color: #22c55e; }

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

.modal-btn.confirm-success {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
}

.modal-btn.confirm-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.modal-btn.confirm-danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

/* Status Dropdown in Modal */
.status-select {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 14px;
    margin-bottom: 20px;
    background: var(--y-yuzey);
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: 1fr; }
    .filter-bar { flex-direction: column; align-items: stretch; }
    .filter-tabs { justify-content: center; }
    .order-table { display: block; overflow-x: auto; }
    .page-header { flex-direction: column; align-items: flex-start; }
}
</style>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header">
    <h1><i class="fas fa-shopping-cart"></i> Sipariş Yönetimi</h1>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-shopping-bag"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['total'] ?></h3>
            <span>Toplam Sipariş</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['pending'] ?></h3>
            <span>Bekleyen Sipariş</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['active'] ?></h3>
            <span>Aktif Sipariş</span>
            <div class="money">₺<?= number_format($stats['totalRevenue'], 2) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['todayOrders'] ?></h3>
            <span>Bugünkü Sipariş</span>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <div class="filter-tabs">
        <a href="?status=" class="filter-tab <?= !$statusFilter ? 'active' : '' ?>">
            Tümü <span class="count"><?= $stats['total'] ?></span>
        </a>
        <a href="?status=pending" class="filter-tab <?= $statusFilter === 'pending' ? 'active' : '' ?>">
            Beklemede <span class="count"><?= $stats['pending'] ?></span>
        </a>
        <a href="?status=active" class="filter-tab <?= $statusFilter === 'active' ? 'active' : '' ?>">
            Aktif <span class="count"><?= $stats['active'] ?></span>
        </a>
        <a href="?status=cancelled" class="filter-tab <?= $statusFilter === 'cancelled' ? 'active' : '' ?>">
            İptal <span class="count"><?= $stats['cancelled'] ?></span>
        </a>
    </div>
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Sipariş no, müşteri ara..." value="<?= htmlspecialchars($searchQuery) ?>" 
               onkeypress="if(event.key==='Enter') window.location='?search='+this.value+'&status=<?= $statusFilter ?>'">
    </div>
</div>

<!-- Order Table -->
<div class="order-card">
    <?php if (empty($orders)): ?>
        <div class="empty-state">
            <div class="icon">🛒</div>
            <h3>Sipariş bulunamadı</h3>
            <p>Henüz sipariş oluşturulmamış veya filtreye uygun sipariş yok.</p>
        </div>
    <?php else: ?>
        <table class="order-table">
            <thead>
                <tr>
                    <th>Sipariş</th>
                    <th>Müşteri</th>
                    <th style="text-align: right;">Tutar</th>
                    <th>Ödeme</th>
                    <th>Durum</th>
                    <th style="text-align: right;">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): 
                    $statusClass = match($order['status']) {
                        'active', 'completed' => 'active',
                        'pending' => 'pending',
                        'processing' => 'processing',
                        'cancelled' => 'cancelled',
                        'fraud' => 'fraud',
                        default => 'pending'
                    };
                    $statusText = match($order['status']) {
                        'active' => 'Aktif',
                        'pending' => 'Beklemede',
                        'processing' => 'İşleniyor',
                        'completed' => 'Tamamlandı',
                        'cancelled' => 'İptal',
                        'fraud' => 'Dolandırıcılık',
                        default => $order['status']
                    };
                    $statusIcon = match($order['status']) {
                        'active', 'completed' => 'fa-check-circle',
                        'pending' => 'fa-clock',
                        'processing' => 'fa-spinner',
                        'cancelled' => 'fa-times-circle',
                        'fraud' => 'fa-exclamation-triangle',
                        default => 'fa-circle'
                    };
                    $paymentIcon = match($order['payment_method'] ?? '') {
                        'bank_transfer' => 'fa-university',
                        'credit_card' => 'fa-credit-card',
                        'paytr' => 'fa-money-bill-wave',
                        default => 'fa-wallet'
                    };
                    $paymentText = match($order['payment_method'] ?? '') {
                        'bank_transfer' => 'Havale/EFT',
                        'credit_card' => 'Kredi Kartı',
                        'paytr' => 'PayTR',
                        default => $order['payment_method'] ?? '-'
                    };
                    $initials = strtoupper(substr($order['first_name'] ?? 'X', 0, 1) . substr($order['last_name'] ?? 'X', 0, 1));
                    
                    // Sipariş kalemi sayısı
                    $itemCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM order_items WHERE order_id = ?", [$order['id']]);
                ?>
                <tr>
                    <td>
                        <div class="order-number">
                            <div class="order-icon">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <div>
                                <div class="order-number-text"><?= htmlspecialchars($order['order_number']) ?></div>
                                <div class="order-date"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="client-info">
                            <div class="client-avatar"><?= $initials ?></div>
                            <div>
                                <div class="client-name"><?= htmlspecialchars(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')) ?></div>
                                <div class="client-email"><?= htmlspecialchars($order['email'] ?? '') ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="amount-cell">
                            <div class="amount-total">₺<?= number_format((float)$order['total'], 2) ?></div>
                            <div class="amount-items"><?= $itemCount ?> ürün</div>
                        </div>
                    </td>
                    <td>
                        <div class="payment-method">
                            <i class="fas <?= $paymentIcon ?>"></i>
                            <?= $paymentText ?>
                        </div>
                    </td>
                    <td>
                        <span class="status-badge <?= $statusClass ?>">
                            <i class="fas <?= $statusIcon ?>"></i>
                            <?= $statusText ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="order-view.php?id=<?= $order['id'] ?>" class="action-btn view" title="Görüntüle">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if ($order['status'] === 'pending'): ?>
                                <button type="button" class="action-btn approve" title="Onayla" 
                                        onclick="confirmAction('approve', <?= $order['id'] ?>, '<?= htmlspecialchars($order['order_number']) ?>')">
                                    <i class="fas fa-check"></i>
                                </button>
                            <?php endif; ?>
                            <button type="button" class="action-btn cancel" title="Durum Değiştir" 
                                    onclick="showStatusModal(<?= $order['id'] ?>, '<?= htmlspecialchars($order['order_number']) ?>', '<?= $order['status'] ?>')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="action-btn delete" title="Sil" 
                                    onclick="confirmAction('delete', <?= $order['id'] ?>, '<?= htmlspecialchars($order['order_number']) ?>')">
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
                Toplam <?= $total ?> siparişten <?= $offset + 1 ?>-<?= min($offset + $perPage, $total) ?> arası gösteriliyor
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
                <input type="hidden" name="order_id" id="actionOrderId">
                <button type="submit" class="modal-btn" id="confirmBtn" name="">Onayla</button>
            </form>
        </div>
    </div>
</div>

<!-- Status Change Modal -->
<div class="modal-overlay" id="statusModal">
    <div class="modal-box">
        <div class="modal-icon warning">
            <i class="fas fa-edit"></i>
        </div>
        <h3>Sipariş Durumunu Değiştir</h3>
        <p id="statusModalOrder"></p>
        <form method="POST">
            <input type="hidden" name="order_id" id="statusOrderId">
            <select name="new_status" class="status-select" id="statusSelect">
                <option value="pending">Beklemede</option>
                <option value="processing">İşleniyor</option>
                <option value="active">Aktif</option>
                <option value="completed">Tamamlandı</option>
                <option value="cancelled">İptal</option>
                <option value="fraud">Dolandırıcılık</option>
            </select>
            <div class="modal-actions">
                <button type="button" class="modal-btn cancel" onclick="closeStatusModal()">İptal</button>
                <button type="submit" name="update_status" class="modal-btn confirm-warning">Durumu Güncelle</button>
            </div>
        </form>
    </div>
</div>

<script>
function confirmAction(action, orderId, orderNumber) {
    const modal = document.getElementById('confirmModal');
    const modalIcon = document.getElementById('modalIcon');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const confirmBtn = document.getElementById('confirmBtn');
    const actionOrderId = document.getElementById('actionOrderId');
    
    actionOrderId.value = orderId;
    
    if (action === 'approve') {
        modalIcon.className = 'modal-icon success';
        modalIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
        modalTitle.textContent = 'Siparişi Onayla';
        modalMessage.innerHTML = '<strong>' + orderNumber + '</strong> numaralı siparişi onaylamak ve aktif etmek istediğinizden emin misiniz?';
        confirmBtn.className = 'modal-btn confirm-success';
        confirmBtn.textContent = 'Evet, Onayla';
        confirmBtn.name = 'update_status';
        
        // Hidden input for status
        let statusInput = document.getElementById('hiddenStatus');
        if (!statusInput) {
            statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.id = 'hiddenStatus';
            statusInput.name = 'new_status';
            document.getElementById('actionForm').appendChild(statusInput);
        }
        statusInput.value = 'active';
    } else if (action === 'delete') {
        modalIcon.className = 'modal-icon danger';
        modalIcon.innerHTML = '<i class="fas fa-trash"></i>';
        modalTitle.textContent = 'Siparişi Kalıcı Olarak Sil';
        modalMessage.innerHTML = '<strong>' + orderNumber + '</strong> numaralı siparişi kalıcı olarak silmek istediğinizden emin misiniz?<br><br><strong style="color:#ef4444;">⚠️ Bu işlem geri alınamaz!</strong>';
        confirmBtn.className = 'modal-btn confirm-danger';
        confirmBtn.textContent = 'Evet, Kalıcı Olarak Sil';
        confirmBtn.name = 'delete_order';
        
        // Remove hidden status input if exists
        const statusInput = document.getElementById('hiddenStatus');
        if (statusInput) statusInput.remove();
    }
    
    modal.classList.add('show');
}

function showStatusModal(orderId, orderNumber, currentStatus) {
    document.getElementById('statusOrderId').value = orderId;
    document.getElementById('statusModalOrder').innerHTML = 'Sipariş: <strong>' + orderNumber + '</strong>';
    document.getElementById('statusSelect').value = currentStatus;
    document.getElementById('statusModal').classList.add('show');
}

function closeModal() {
    document.getElementById('confirmModal').classList.remove('show');
}

function closeStatusModal() {
    document.getElementById('statusModal').classList.remove('show');
}

// Close modals on outside click
document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
document.getElementById('statusModal').addEventListener('click', function(e) {
    if (e.target === this) closeStatusModal();
});

// Close modals on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeStatusModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
