<?php
/**
 * WHMVM - Admin Hizmet Yönetimi
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

$pageTitle = 'Hizmetler';
$currentPage = 'services';

// İşlemler
$message = '';
$messageType = '';

// Durum güncelleme
if (isset($_POST['update_status'])) {
    $serviceId = (int)$_POST['service_id'];
    $newStatus = $_POST['new_status'];
    Database::query("UPDATE services SET status = ? WHERE id = ?", [$newStatus, $serviceId]);
    $message = 'Hizmet durumu güncellendi.';
    $messageType = 'success';
}

// Hizmet silme
if (isset($_POST['delete_service'])) {
    $serviceId = (int)$_POST['service_id'];
    Database::query("DELETE FROM services WHERE id = ?", [$serviceId]);
    $message = 'Hizmet kalıcı olarak silindi.';
    $messageType = 'success';
}

// Sayfalama
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$typeFilter = $_GET['type'] ?? '';

$where = "WHERE 1=1";
$params = [];

if ($statusFilter) {
    $where .= " AND s.status = ?";
    $params[] = $statusFilter;
}

if ($typeFilter) {
    $where .= " AND p.type = ?";
    $params[] = $typeFilter;
}

if ($searchQuery) {
    $where .= " AND (s.domain LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR p.name LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

$total = (int)Database::fetchColumn("
    SELECT COUNT(*) FROM services s 
    LEFT JOIN clients c ON s.client_id = c.id 
    LEFT JOIN products p ON s.product_id = p.id
    $where
", $params);
$totalPages = (int)ceil($total / $perPage);

// İptal taleplerini çek
$cancelRequests = [];
$cancelResult = Database::fetchAll("SELECT service_id, status, cancel_type FROM cancellation_requests WHERE status = 'pending'");
foreach ($cancelResult as $row) {
    $cancelRequests[$row['service_id']] = $row;
}

$services = Database::fetchAll("
    SELECT s.*, c.first_name, c.last_name, c.email, c.company_name, p.name as product_name, p.type as product_type
    FROM services s 
    LEFT JOIN clients c ON s.client_id = c.id 
    LEFT JOIN products p ON s.product_id = p.id
    $where
    ORDER BY s.created_at DESC 
    LIMIT $perPage OFFSET $offset
", $params);

// İstatistikler
$stats = [
    'total' => (int)Database::fetchColumn("SELECT COUNT(*) FROM services"),
    'active' => (int)Database::fetchColumn("SELECT COUNT(*) FROM services WHERE status = 'active'"),
    'pending' => (int)Database::fetchColumn("SELECT COUNT(*) FROM services WHERE status = 'pending'"),
    'suspended' => (int)Database::fetchColumn("SELECT COUNT(*) FROM services WHERE status = 'suspended'"),
    'terminated' => (int)Database::fetchColumn("SELECT COUNT(*) FROM services WHERE status IN ('terminated', 'cancelled')"),
    'cancelRequests' => count($cancelRequests),
    'monthlyRevenue' => (float)Database::fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM services WHERE status = 'active' AND billing_cycle = 'monthly'"),
    'expiringThisMonth' => (int)Database::fetchColumn("SELECT COUNT(*) FROM services WHERE status = 'active' AND next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")
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

/* Alert Banner */
.alert-banner {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border: 1px solid #fbbf24;
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.alert-banner-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
    flex-shrink: 0;
}

.alert-banner-content {
    flex: 1;
}

.alert-banner-content strong {
    display: block;
    font-size: 16px;
    color: #92400e;
    margin-bottom: 4px;
}

.alert-banner-content p {
    margin: 0;
    font-size: 14px;
    color: #a16207;
}

.alert-banner-action {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    padding: 12px 24px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.alert-banner-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3);
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
.stat-icon.green { background: linear-gradient(135deg, #22c55e, #16a34a); color: white; }
.stat-icon.orange { background: linear-gradient(135deg, #f97316, #ea580c); color: white; }
.stat-icon.red { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
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

/* Service Table */
.service-card {
    background: var(--y-yuzey);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
}

.service-table {
    width: 100%;
    border-collapse: collapse;
}

.service-table th {
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

.service-table td {
    padding: 18px 20px;
    border-bottom: 1px solid var(--y-cizgi-soft);
    vertical-align: middle;
}

.service-table tr:last-child td {
    border-bottom: none;
}

.service-table tr:hover {
    background: var(--y-yuzey-2);
}

/* Service Info */
.service-info {
    display: flex;
    align-items: center;
    gap: 14px;
}

.service-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: white;
}

.service-icon.hosting { background: linear-gradient(135deg, #f97316, #ea580c); }
.service-icon.vps { background: linear-gradient(135deg, #22c55e, #16a34a); }
.service-icon.vds { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
.service-icon.dedicated { background: linear-gradient(135deg, #3b82f6, #2563eb); }
.service-icon.domain { background: linear-gradient(135deg, #06b6d4, #0891b2); }
.service-icon.ssl { background: linear-gradient(135deg, #10b981, #059669); }
.service-icon.other { background: linear-gradient(135deg, #64748b, #475569); }

.service-name {
    font-weight: 600;
    color: var(--dark);
    font-size: 14px;
    margin-bottom: 2px;
}

.service-type {
    font-size: 12px;
    color: var(--gray);
    display: flex;
    align-items: center;
    gap: 6px;
}

.service-type i {
    font-size: 10px;
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

/* Domain */
.domain-cell {
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
    padding: 6px 12px;
    border-radius: 6px;
    display: inline-block;
}

.domain-cell.empty {
    color: var(--gray);
    background: var(--light);
}

/* Price */
.price-cell {
    text-align: right;
}

.price-amount {
    font-size: 16px;
    font-weight: 700;
    color: var(--dark);
}

.price-cycle {
    font-size: 12px;
    color: var(--gray);
}

/* Due Date */
.due-date {
    font-size: 14px;
    color: var(--dark);
}

.due-date.expiring {
    color: #f59e0b;
    font-weight: 600;
}

.due-date.expired {
    color: #ef4444;
    font-weight: 600;
}

.expiring-badge {
    display: inline-block;
    padding: 4px 10px;
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
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

.status-badge.active {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
}

.status-badge.pending {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.status-badge.suspended {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.status-badge.terminated, .status-badge.cancelled {
    background: rgba(100, 116, 139, 0.1);
    color: var(--y-metin-3);
}

.cancel-request-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    background: rgba(245, 158, 11, 0.15);
    color: #d97706;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    margin-top: 6px;
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
.action-btn.suspend:hover { border-color: #f59e0b; color: #f59e0b; }
.action-btn.activate:hover { border-color: #22c55e; color: #22c55e; }
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

/* Modal */
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

.modal-btn.confirm-success { background: linear-gradient(135deg, #22c55e, #16a34a); color: white; }
.modal-btn.confirm-warning { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
.modal-btn.confirm-danger { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }

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
    .service-table { display: block; overflow-x: auto; }
    .page-header { flex-direction: column; align-items: flex-start; }
    .alert-banner { flex-direction: column; text-align: center; }
}
</style>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($stats['cancelRequests'] > 0): ?>
<div class="alert-banner">
    <div class="alert-banner-icon">
        <i class="fas fa-ban"></i>
    </div>
    <div class="alert-banner-content">
        <strong><?= $stats['cancelRequests'] ?> Bekleyen İptal Talebi</strong>
        <p>Hizmetler için bekleyen iptal talepleri bulunmaktadır.</p>
    </div>
    <a href="cancellations.php" class="alert-banner-action">
        <i class="fas fa-arrow-right"></i>
        Talepleri Görüntüle
    </a>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header">
    <h1><i class="fas fa-server"></i> Hizmet Yönetimi</h1>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-cubes"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['total'] ?></h3>
            <span>Toplam Hizmet</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['active'] ?></h3>
            <span>Aktif Hizmet</span>
            <div class="money">₺<?= number_format($stats['monthlyRevenue'], 2) ?>/ay</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['pending'] ?></h3>
            <span>Bekleyen</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['expiringThisMonth'] ?></h3>
            <span>Bu Ay Sona Erecek</span>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <div class="filter-tabs">
        <a href="?status=" class="filter-tab <?= !$statusFilter ? 'active' : '' ?>">
            Tümü <span class="count"><?= $stats['total'] ?></span>
        </a>
        <a href="?status=active" class="filter-tab <?= $statusFilter === 'active' ? 'active' : '' ?>">
            Aktif <span class="count"><?= $stats['active'] ?></span>
        </a>
        <a href="?status=pending" class="filter-tab <?= $statusFilter === 'pending' ? 'active' : '' ?>">
            Bekleyen <span class="count"><?= $stats['pending'] ?></span>
        </a>
        <a href="?status=suspended" class="filter-tab <?= $statusFilter === 'suspended' ? 'active' : '' ?>">
            Askıda <span class="count"><?= $stats['suspended'] ?></span>
        </a>
        <a href="?status=terminated" class="filter-tab <?= $statusFilter === 'terminated' ? 'active' : '' ?>">
            Sonlandırılmış <span class="count"><?= $stats['terminated'] ?></span>
        </a>
    </div>
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Domain, müşteri, ürün ara..." value="<?= htmlspecialchars($searchQuery) ?>" 
               onkeypress="if(event.key==='Enter') window.location='?search='+this.value+'&status=<?= $statusFilter ?>'">
    </div>
</div>

<!-- Service Table -->
<div class="service-card">
    <?php if (empty($services)): ?>
        <div class="empty-state">
            <div class="icon">📦</div>
            <h3>Hizmet bulunamadı</h3>
            <p>Henüz hizmet oluşturulmamış veya filtreye uygun hizmet yok.</p>
        </div>
    <?php else: ?>
        <table class="service-table">
            <thead>
                <tr>
                    <th>Hizmet</th>
                    <th>Müşteri</th>
                    <th>Domain</th>
                    <th style="text-align: right;">Fiyat</th>
                    <th>Sonraki Vade</th>
                    <th>Durum</th>
                    <th style="text-align: right;">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $service): 
                    $productType = $service['product_type'] ?? 'other';
                    $typeIcon = match($productType) {
                        'hosting' => 'fa-globe',
                        'vps' => 'fa-server',
                        'vds' => 'fa-hdd',
                        'dedicated' => 'fa-database',
                        'domain' => 'fa-link',
                        'ssl' => 'fa-shield-alt',
                        default => 'fa-cube'
                    };
                    $typeName = match($productType) {
                        'hosting' => 'Web Hosting',
                        'vps' => 'VPS Sunucu',
                        'vds' => 'VDS Sunucu',
                        'dedicated' => 'Fiziksel Sunucu',
                        'domain' => 'Domain',
                        'ssl' => 'SSL Sertifikası',
                        default => 'Diğer'
                    };
                    
                    $statusClass = match($service['status']) {
                        'active' => 'active',
                        'pending' => 'pending',
                        'suspended' => 'suspended',
                        'terminated', 'cancelled' => 'terminated',
                        default => 'pending'
                    };
                    $statusText = match($service['status']) {
                        'active' => 'Aktif',
                        'pending' => 'Beklemede',
                        'suspended' => 'Askıda',
                        'terminated' => 'Sonlandırılmış',
                        'cancelled' => 'İptal',
                        default => $service['status']
                    };
                    $statusIcon = match($service['status']) {
                        'active' => 'fa-check-circle',
                        'pending' => 'fa-clock',
                        'suspended' => 'fa-pause-circle',
                        'terminated', 'cancelled' => 'fa-times-circle',
                        default => 'fa-circle'
                    };
                    
                    $initials = strtoupper(substr($service['first_name'] ?? 'X', 0, 1) . substr($service['last_name'] ?? 'X', 0, 1));
                    
                    // Vade kontrolü
                    $isExpiring = false;
                    $isExpired = false;
                    if ($service['next_due_date']) {
                        $dueDate = strtotime($service['next_due_date']);
                        $today = time();
                        $daysUntilDue = ($dueDate - $today) / (60 * 60 * 24);
                        if ($daysUntilDue < 0) $isExpired = true;
                        elseif ($daysUntilDue <= 30) $isExpiring = true;
                    }
                    
                    $billingText = match($service['billing_cycle']) {
                        'monthly' => 'Aylık',
                        'quarterly' => '3 Aylık',
                        'semiannually' => '6 Aylık',
                        'annually' => 'Yıllık',
                        'biennially' => '2 Yıllık',
                        'triennially' => '3 Yıllık',
                        default => $service['billing_cycle']
                    };
                ?>
                <tr>
                    <td>
                        <div class="service-info">
                            <div class="service-icon <?= $productType ?>">
                                <i class="fas <?= $typeIcon ?>"></i>
                            </div>
                            <div>
                                <div class="service-name"><?= htmlspecialchars($service['product_name'] ?? 'Bilinmiyor') ?></div>
                                <div class="service-type">
                                    <i class="fas fa-tag"></i>
                                    <?= $typeName ?> • #<?= $service['id'] ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="client-info">
                            <div class="client-avatar"><?= $initials ?></div>
                            <div>
                                <div class="client-name"><?= htmlspecialchars(($service['first_name'] ?? '') . ' ' . ($service['last_name'] ?? '')) ?></div>
                                <div class="client-email"><?= htmlspecialchars($service['email'] ?? '') ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if (!empty($service['domain'])): ?>
                            <span class="domain-cell"><?= htmlspecialchars($service['domain']) ?></span>
                        <?php else: ?>
                            <span class="domain-cell empty">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="price-cell">
                            <div class="price-amount">₺<?= number_format((float)$service['amount'], 2) ?></div>
                            <div class="price-cycle"><?= $billingText ?></div>
                        </div>
                    </td>
                    <td>
                        <?php if ($service['next_due_date']): ?>
                            <div class="due-date <?= $isExpired ? 'expired' : ($isExpiring ? 'expiring' : '') ?>">
                                <?= date('d.m.Y', strtotime($service['next_due_date'])) ?>
                            </div>
                            <?php if ($isExpired): ?>
                                <div class="expiring-badge" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                                    <i class="fas fa-exclamation-triangle"></i> Vadesi Geçmiş
                                </div>
                            <?php elseif ($isExpiring): ?>
                                <div class="expiring-badge">
                                    <i class="fas fa-clock"></i> <?= (int)$daysUntilDue ?> gün
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: var(--gray);">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge <?= $statusClass ?>">
                            <i class="fas <?= $statusIcon ?>"></i>
                            <?= $statusText ?>
                        </span>
                        <?php if (isset($cancelRequests[$service['id']])): ?>
                            <div class="cancel-request-badge">
                                <i class="fas fa-ban"></i> İptal Talebi
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="service-view.php?id=<?= $service['id'] ?>" class="action-btn view" title="Görüntüle">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if ($service['status'] === 'active'): ?>
                                <button type="button" class="action-btn suspend" title="Askıya Al" 
                                        onclick="confirmAction('suspend', <?= $service['id'] ?>, '<?= htmlspecialchars($service['product_name'] ?? '') ?>')">
                                    <i class="fas fa-pause"></i>
                                </button>
                            <?php elseif ($service['status'] === 'suspended'): ?>
                                <button type="button" class="action-btn activate" title="Aktif Et" 
                                        onclick="confirmAction('activate', <?= $service['id'] ?>, '<?= htmlspecialchars($service['product_name'] ?? '') ?>')">
                                    <i class="fas fa-play"></i>
                                </button>
                            <?php endif; ?>
                            <button type="button" class="action-btn delete" title="Sil" 
                                    onclick="confirmAction('delete', <?= $service['id'] ?>, '<?= htmlspecialchars($service['product_name'] ?? '') ?>')">
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
                Toplam <?= $total ?> hizmetten <?= $offset + 1 ?>-<?= min($offset + $perPage, $total) ?> arası gösteriliyor
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
                <input type="hidden" name="service_id" id="actionServiceId">
                <input type="hidden" name="new_status" id="actionNewStatus">
                <button type="submit" class="modal-btn" id="confirmBtn" name="">Onayla</button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmAction(action, serviceId, serviceName) {
    const modal = document.getElementById('confirmModal');
    const modalIcon = document.getElementById('modalIcon');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const confirmBtn = document.getElementById('confirmBtn');
    const actionServiceId = document.getElementById('actionServiceId');
    const actionNewStatus = document.getElementById('actionNewStatus');
    
    actionServiceId.value = serviceId;
    
    if (action === 'suspend') {
        modalIcon.className = 'modal-icon warning';
        modalIcon.innerHTML = '<i class="fas fa-pause-circle"></i>';
        modalTitle.textContent = 'Hizmeti Askıya Al';
        modalMessage.innerHTML = '<strong>' + serviceName + '</strong> hizmetini askıya almak istediğinizden emin misiniz?';
        confirmBtn.className = 'modal-btn confirm-warning';
        confirmBtn.textContent = 'Evet, Askıya Al';
        confirmBtn.name = 'update_status';
        actionNewStatus.value = 'suspended';
    } else if (action === 'activate') {
        modalIcon.className = 'modal-icon success';
        modalIcon.innerHTML = '<i class="fas fa-play-circle"></i>';
        modalTitle.textContent = 'Hizmeti Aktif Et';
        modalMessage.innerHTML = '<strong>' + serviceName + '</strong> hizmetini aktif etmek istediğinizden emin misiniz?';
        confirmBtn.className = 'modal-btn confirm-success';
        confirmBtn.textContent = 'Evet, Aktif Et';
        confirmBtn.name = 'update_status';
        actionNewStatus.value = 'active';
    } else if (action === 'delete') {
        modalIcon.className = 'modal-icon danger';
        modalIcon.innerHTML = '<i class="fas fa-trash"></i>';
        modalTitle.textContent = 'Hizmeti Kalıcı Olarak Sil';
        modalMessage.innerHTML = '<strong>' + serviceName + '</strong> hizmetini kalıcı olarak silmek istediğinizden emin misiniz?<br><br><strong style="color:#ef4444;">⚠️ Bu işlem geri alınamaz!</strong>';
        confirmBtn.className = 'modal-btn confirm-danger';
        confirmBtn.textContent = 'Evet, Kalıcı Olarak Sil';
        confirmBtn.name = 'delete_service';
        actionNewStatus.value = '';
    }
    
    modal.classList.add('show');
}

function closeModal() {
    document.getElementById('confirmModal').classList.remove('show');
}

document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>

<?php include 'includes/footer.php'; ?>
