<?php
/**
 * WHMVM Admin - Satış Ortaklığı Yönetimi
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Affiliate.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

$pageTitle = 'Satış Ortaklığı';
$currentPage = 'affiliates';

// Tabloları kontrol et
function ensureAffiliateTables() {
    try {
        Database::query("SELECT 1 FROM affiliates LIMIT 1");
    } catch (Throwable $e) {
        $sqlFile = dirname(__DIR__) . '/install/affiliate_tables.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $statement) {
                if (!empty($statement) && !str_starts_with($statement, '--')) {
                    try {
                        Database::getInstance()->exec($statement);
                    } catch (Throwable $ex) {}
                }
            }
        }
    }
}
ensureAffiliateTables();

$message = '';
$messageType = 'success';

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Durumu güncelle
    if (isset($_POST['update_status'])) {
        $affiliateId = (int)$_POST['affiliate_id'];
        $newStatus = $_POST['status'];
        
        $approvedAt = $newStatus === 'active' ? date('Y-m-d H:i:s') : null;
        $approvedBy = $newStatus === 'active' ? $_SESSION['admin_id'] : null;
        
        Database::query(
            "UPDATE affiliates SET status = ?, approved_at = ?, approved_by = ? WHERE id = ?",
            [$newStatus, $approvedAt, $approvedBy, $affiliateId]
        );
        
        $message = 'Ortak durumu güncellendi.';
    }
    
    // Komisyon oranını güncelle
    if (isset($_POST['update_commission'])) {
        $affiliateId = (int)$_POST['affiliate_id'];
        $commissionRate = (float)$_POST['commission_rate'];
        $commissionType = $_POST['commission_type'];
        
        Database::query(
            "UPDATE affiliates SET commission_rate = ?, commission_type = ? WHERE id = ?",
            [$commissionRate, $commissionType, $affiliateId]
        );
        
        $message = 'Komisyon oranı güncellendi.';
    }
    
    // Not ekle
    if (isset($_POST['update_notes'])) {
        $affiliateId = (int)$_POST['affiliate_id'];
        $notes = $_POST['notes'];
        
        Database::query("UPDATE affiliates SET notes = ? WHERE id = ?", [$notes, $affiliateId]);
        $message = 'Notlar güncellendi.';
    }
}

// Filtreler
$statusFilter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$where = "WHERE 1=1";
$params = [];

if ($statusFilter) {
    $where .= " AND a.status = ?";
    $params[] = $statusFilter;
}

if ($search) {
    $where .= " AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR a.affiliate_code LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

// Sayfalama
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$total = (int)Database::fetchColumn(
    "SELECT COUNT(*) FROM affiliates a JOIN clients c ON a.client_id = c.id $where",
    $params
);
$totalPages = (int)ceil($total / $perPage);

$affiliates = Database::fetchAll(
    "SELECT a.*, c.first_name, c.last_name, c.email
     FROM affiliates a
     JOIN clients c ON a.client_id = c.id
     $where
     ORDER BY a.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

// İstatistikler
$stats = [
    'total' => (int)Database::fetchColumn("SELECT COUNT(*) FROM affiliates"),
    'active' => (int)Database::fetchColumn("SELECT COUNT(*) FROM affiliates WHERE status = 'active'"),
    'pending' => (int)Database::fetchColumn("SELECT COUNT(*) FROM affiliates WHERE status = 'pending'"),
    'total_earnings' => (float)Database::fetchColumn("SELECT COALESCE(SUM(total_earnings), 0) FROM affiliates"),
    'total_balance' => (float)Database::fetchColumn("SELECT COALESCE(SUM(balance), 0) FROM affiliates")
];

include 'includes/header.php';
?>

<style>
/* ===== Affiliate Admin Page - Modern Design ===== */

/* Page Header */
.page-header-card {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
    border-radius: 20px;
    padding: 32px;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(99, 102, 241, 0.3);
}

.page-header-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
    animation: pulse-glow 8s ease-in-out infinite;
}

.page-header-card::after {
    content: '🤝';
    position: absolute;
    right: 40px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 80px;
    opacity: 0.15;
}

@keyframes pulse-glow {
    0%, 100% { transform: scale(1); opacity: 0.15; }
    50% { transform: scale(1.1); opacity: 0.2; }
}

.page-header-content {
    position: relative;
    z-index: 1;
}

.page-header-card h1 {
    color: white;
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-header-card p {
    color: rgba(255,255,255,0.85);
    font-size: 15px;
    max-width: 500px;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 20px;
    margin-bottom: 28px;
}

@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.04);
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 35px rgba(0,0,0,0.1);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}

.stat-card.purple::before { background: linear-gradient(90deg, #8b5cf6, #a855f7); }
.stat-card.green::before { background: linear-gradient(90deg, #10b981, #34d399); }
.stat-card.yellow::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.stat-card.blue::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
.stat-card.pink::before { background: linear-gradient(90deg, #ec4899, #f472b6); }

.stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 16px;
}

.stat-card.purple .stat-icon { background: linear-gradient(135deg, #ede9fe, #ddd6fe); }
.stat-card.green .stat-icon { background: linear-gradient(135deg, #d1fae5, #a7f3d0); }
.stat-card.yellow .stat-icon { background: linear-gradient(135deg, #fef3c7, #fde68a); }
.stat-card.blue .stat-icon { background: linear-gradient(135deg, #dbeafe, #bfdbfe); }
.stat-card.pink .stat-icon { background: linear-gradient(135deg, #fce7f3, #fbcfe8); }

.stat-value {
    font-size: 28px;
    font-weight: 800;
    color: var(--dark);
    margin-bottom: 4px;
    letter-spacing: -0.5px;
}

.stat-label {
    font-size: 13px;
    color: var(--gray);
    font-weight: 500;
}

/* Search & Filter Card */
.filter-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.search-box {
    position: relative;
    flex: 1;
    min-width: 280px;
}

.search-box input {
    width: 100%;
    padding: 14px 20px 14px 48px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 14px;
    transition: all 0.3s;
    background: #f8fafc;
}

.search-box input:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
}

.search-box::before {
    content: '🔍';
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 18px;
}

.filter-select {
    padding: 14px 20px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 14px;
    background: #f8fafc;
    cursor: pointer;
    min-width: 180px;
    transition: all 0.3s;
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
}

.btn {
    padding: 14px 24px;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
}

.btn-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.btn-outline {
    background: white;
    border: 2px solid #e2e8f0;
    color: var(--dark);
}

.btn-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.btn-sm {
    padding: 10px 16px;
    font-size: 13px;
}

/* Data Table */
.table-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

.table-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-header .count {
    background: linear-gradient(135deg, var(--primary), #8b5cf6);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.data-table {
    width: 100%;
}

.data-table table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    text-align: left;
    padding: 16px 20px;
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
}

.data-table td {
    padding: 18px 20px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    vertical-align: middle;
}

.data-table tr {
    transition: all 0.2s;
}

.data-table tbody tr:hover {
    background: linear-gradient(90deg, rgba(99, 102, 241, 0.03) 0%, rgba(139, 92, 246, 0.03) 100%);
}

.data-table tbody tr:last-child td {
    border-bottom: none;
}

/* User Cell */
.user-cell {
    display: flex;
    align-items: center;
    gap: 14px;
}

.user-avatar {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--primary), #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 16px;
    flex-shrink: 0;
}

.user-info strong {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 2px;
}

.user-info small {
    font-size: 12px;
    color: var(--gray);
}

/* Code Badge */
.code-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    padding: 8px 14px;
    border-radius: 8px;
    font-family: 'Monaco', 'Consolas', monospace;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
}

.code-badge::before {
    content: '🔗';
    font-size: 12px;
}

/* Commission Badge */
.commission-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    border-radius: 8px;
    font-weight: 700;
    color: #1e40af;
    font-size: 14px;
}

/* Stat Mini */
.stat-mini {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    color: var(--dark);
}

.stat-mini.green { color: #059669; }

/* Badge */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
}

.badge::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.badge-success { background: #d1fae5; color: #059669; }
.badge-success::before { background: #059669; }

.badge-warning { background: #fef3c7; color: #d97706; }
.badge-warning::before { background: #d97706; }

.badge-danger { background: #fee2e2; color: #dc2626; }
.badge-danger::before { background: #dc2626; }

.badge-gray { background: #f1f5f9; color: #64748b; }
.badge-gray::before { background: #64748b; }

/* Alert */
.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 500;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert-success {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: #065f46;
    border-left: 4px solid #10b981;
}

.alert-danger {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #991b1b;
    border-left: 4px solid #ef4444;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 80px 40px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

.empty-icon {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, #ede9fe, #ddd6fe);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    margin: 0 auto 24px;
}

.empty-state h3 {
    font-size: 22px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--gray);
    font-size: 15px;
    max-width: 400px;
    margin: 0 auto;
}

/* Pagination */
.pagination-wrapper {
    display: flex;
    justify-content: center;
    padding: 24px;
    border-top: 1px solid #f1f5f9;
}

.pagination {
    display: flex;
    gap: 6px;
}

.pagination a {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    color: var(--dark);
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
}

.pagination a:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.pagination a.active {
    background: linear-gradient(135deg, var(--primary), #8b5cf6);
    color: white;
    border-color: transparent;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal.active { display: flex; }

.modal-content {
    background: white;
    border-radius: 20px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 60px rgba(0,0,0,0.3);
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.modal-header {
    padding: 24px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(139, 92, 246, 0.05));
}

.modal-header h3 {
    font-size: 20px;
    font-weight: 700;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-close {
    width: 36px;
    height: 36px;
    background: #f1f5f9;
    border: none;
    border-radius: 10px;
    font-size: 20px;
    cursor: pointer;
    color: var(--gray);
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    background: #fee2e2;
    color: #ef4444;
}

.modal-body { padding: 24px; }

/* Modal Stats */
.modal-user {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-radius: 14px;
    margin-bottom: 24px;
}

.modal-user-avatar {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--primary), #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 22px;
}

.modal-user-info h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 4px;
}

.modal-user-info p {
    font-size: 14px;
    color: var(--gray);
}

.info-cards {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 24px;
}

.info-card {
    background: #f8fafc;
    padding: 16px;
    border-radius: 12px;
    text-align: center;
    border: 1px solid #e2e8f0;
}

.info-card label {
    font-size: 11px;
    color: var(--gray);
    display: block;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.info-card span {
    font-size: 22px;
    font-weight: 800;
    color: var(--dark);
}

/* Form Sections */
.form-section {
    background: #f8fafc;
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid #e2e8f0;
}

.form-section-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 8px;
    color: #475569;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.2s;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
}

textarea.form-control {
    min-height: 80px;
    resize: vertical;
}

/* Payment Info */
.payment-info {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border-radius: 14px;
    padding: 20px;
    border: 1px solid #fcd34d;
}

.payment-info h5 {
    font-size: 14px;
    font-weight: 700;
    color: #92400e;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.payment-info p {
    font-size: 14px;
    color: #78350f;
    margin-bottom: 8px;
}

.payment-details-box {
    background: white;
    border-radius: 10px;
    padding: 14px;
    font-size: 13px;
    color: #475569;
    white-space: pre-wrap;
    font-family: inherit;
    border: 1px solid rgba(0,0,0,0.1);
}
</style>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?>">
    <span>✓</span> <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header-card">
    <div class="page-header-content">
        <h1>🤝 Satış Ortaklığı Yönetimi</h1>
        <p>Satış ortaklarınızı yönetin, komisyon oranlarını ayarlayın ve performanslarını takip edin.</p>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card purple">
        <div class="stat-icon">🤝</div>
        <div class="stat-value"><?= number_format($stats['total']) ?></div>
        <div class="stat-label">Toplam Ortak</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">✓</div>
        <div class="stat-value"><?= number_format($stats['active']) ?></div>
        <div class="stat-label">Aktif Ortak</div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-icon">⏳</div>
        <div class="stat-value"><?= number_format($stats['pending']) ?></div>
        <div class="stat-label">Onay Bekleyen</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">💰</div>
        <div class="stat-value"><?= number_format($stats['total_earnings'], 0) ?>₺</div>
        <div class="stat-label">Toplam Kazanç</div>
    </div>
    <div class="stat-card pink">
        <div class="stat-icon">💵</div>
        <div class="stat-value"><?= number_format($stats['total_balance'], 0) ?>₺</div>
        <div class="stat-label">Bekleyen Bakiye</div>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="filter-card">
    <div class="search-box">
        <input type="text" name="search" placeholder="İsim, email veya referans kodu ara..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="status" class="filter-select">
        <option value="">Tüm Durumlar</option>
        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>⏳ Onay Bekliyor</option>
        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>✓ Aktif</option>
        <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>⏸️ Askıda</option>
        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>✕ Reddedildi</option>
    </select>
    <button type="submit" class="btn btn-primary">🔍 Filtrele</button>
    <?php if ($search || $statusFilter): ?>
        <a href="affiliates.php" class="btn btn-outline">✕ Temizle</a>
    <?php endif; ?>
</form>

<!-- Affiliates Table -->
<?php if (empty($affiliates)): ?>
<div class="empty-state">
    <div class="empty-icon">🤝</div>
    <h3>Satış ortağı bulunamadı</h3>
    <p>Henüz satış ortaklığı başvurusu yok veya filtreleme sonucu eşleşen kayıt bulunamadı.</p>
</div>
<?php else: ?>
<div class="table-card">
    <div class="table-header">
        <h3>📋 Satış Ortakları</h3>
        <span class="count"><?= number_format($total) ?> Kayıt</span>
    </div>
    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>Ortak</th>
                    <th>Referans Kodu</th>
                    <th>Komisyon</th>
                    <th>Ziyaret</th>
                    <th>Kayıt</th>
                    <th>Sipariş</th>
                    <th>Kazanç</th>
                    <th>Bakiye</th>
                    <th>Durum</th>
                    <th style="text-align: center;">İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($affiliates as $aff): ?>
                <tr>
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar">
                                <?= strtoupper(substr($aff['first_name'], 0, 1) . substr($aff['last_name'], 0, 1)) ?>
                            </div>
                            <div class="user-info">
                                <strong><?= htmlspecialchars($aff['first_name'] . ' ' . $aff['last_name']) ?></strong>
                                <small><?= htmlspecialchars($aff['email']) ?></small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="code-badge"><?= htmlspecialchars($aff['affiliate_code']) ?></span>
                    </td>
                    <td>
                        <span class="commission-badge">%<?= number_format((float)$aff['commission_rate'], 0) ?></span>
                    </td>
                    <td><span class="stat-mini">👁️ <?= number_format((int)$aff['total_visits']) ?></span></td>
                    <td><span class="stat-mini">👤 <?= number_format((int)$aff['total_signups']) ?></span></td>
                    <td><span class="stat-mini">🛒 <?= number_format((int)$aff['total_orders']) ?></span></td>
                    <td><span class="stat-mini">💰 <?= number_format((float)$aff['total_earnings'], 0) ?>₺</span></td>
                    <td><span class="stat-mini green">💵 <?= number_format((float)$aff['balance'], 0) ?>₺</span></td>
                    <td>
                        <?php 
                        $badgeClass = match($aff['status']) {
                            'active' => 'success',
                            'pending' => 'warning',
                            'suspended' => 'gray',
                            'rejected' => 'danger',
                            default => 'gray'
                        };
                        $statusText = match($aff['status']) {
                            'active' => 'Aktif',
                            'pending' => 'Beklemede',
                            'suspended' => 'Askıda',
                            'rejected' => 'Reddedildi',
                            default => $aff['status']
                        };
                        ?>
                        <span class="badge badge-<?= $badgeClass ?>"><?= $statusText ?></span>
                    </td>
                    <td style="text-align: center;">
                        <button class="btn btn-primary btn-sm" onclick="openAffiliateModal(<?= (int)$aff['id'] ?>)">
                            ⚙️ Yönet
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <div class="pagination-wrapper">
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $statusFilter ?>" 
                   class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Management Modal -->
<div id="affiliateModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>⚙️ Ortak Yönetimi</h3>
            <button class="modal-close" onclick="closeAffiliateModal()">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="modal_affiliate_id">
            
            <!-- User Info -->
            <div class="modal-user">
                <div class="modal-user-avatar" id="modal_avatar">SA</div>
                <div class="modal-user-info">
                    <h4 id="modal_affiliate_name"></h4>
                    <p id="modal_email"></p>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="info-cards">
                <div class="info-card">
                    <label>👁️ Ziyaret</label>
                    <span id="modal_visits">0</span>
                </div>
                <div class="info-card">
                    <label>👤 Kayıt</label>
                    <span id="modal_signups">0</span>
                </div>
                <div class="info-card">
                    <label>🛒 Sipariş</label>
                    <span id="modal_orders">0</span>
                </div>
                <div class="info-card">
                    <label>💰 Kazanç</label>
                    <span id="modal_earnings">0 ₺</span>
                </div>
            </div>
            
            <!-- Status Update -->
            <div class="form-section">
                <div class="form-section-title">📋 Durum Yönetimi</div>
                <form method="POST">
                    <input type="hidden" name="affiliate_id" class="aff_id_field">
                    <input type="hidden" name="update_status" value="1">
                    <div class="form-group">
                        <label>Hesap Durumu</label>
                        <select name="status" class="form-control" id="modal_status">
                            <option value="pending">⏳ Onay Bekliyor</option>
                            <option value="active">✓ Aktif</option>
                            <option value="suspended">⏸️ Askıya Al</option>
                            <option value="rejected">✕ Reddet</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Durumu Güncelle</button>
                </form>
            </div>
            
            <!-- Commission Update -->
            <div class="form-section">
                <div class="form-section-title">💵 Komisyon Ayarları</div>
                <form method="POST">
                    <input type="hidden" name="affiliate_id" class="aff_id_field">
                    <input type="hidden" name="update_commission" value="1">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label>Komisyon Oranı</label>
                            <input type="number" name="commission_rate" class="form-control" id="modal_commission" step="0.01" min="0" max="100" placeholder="%">
                        </div>
                        <div class="form-group">
                            <label>Komisyon Tipi</label>
                            <select name="commission_type" class="form-control" id="modal_commission_type">
                                <option value="percentage">Yüzde (%)</option>
                                <option value="fixed">Sabit Tutar (₺)</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success" style="width: 100%;">Komisyonu Güncelle</button>
                </form>
            </div>
            
            <!-- Notes -->
            <div class="form-section">
                <div class="form-section-title">📝 Admin Notları</div>
                <form method="POST">
                    <input type="hidden" name="affiliate_id" class="aff_id_field">
                    <input type="hidden" name="update_notes" value="1">
                    <div class="form-group">
                        <textarea name="notes" class="form-control" id="modal_notes" placeholder="Bu ortak hakkında notlarınız..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-outline" style="width: 100%;">Notları Kaydet</button>
                </form>
            </div>
            
            <!-- Payment Details -->
            <div class="payment-info">
                <h5>💳 Ödeme Bilgileri</h5>
                <p><strong>Yöntem:</strong> <span id="modal_payment_method"></span></p>
                <div class="payment-details-box" id="modal_payment_details"></div>
            </div>
        </div>
    </div>
</div>

<script>
// Affiliate verileri
var affiliatesData = <?= json_encode($affiliates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function openAffiliateModal(id) {
    var affiliate = null;
    for (var i = 0; i < affiliatesData.length; i++) {
        if (parseInt(affiliatesData[i].id) === parseInt(id)) {
            affiliate = affiliatesData[i];
            break;
        }
    }
    
    if (!affiliate) {
        alert('Affiliate bulunamadı! ID: ' + id);
        return;
    }
    
    var modal = document.getElementById('affiliateModal');
    if (!modal) {
        alert('Modal bulunamadı!');
        return;
    }
    
    modal.classList.add('active');
    
    var firstName = affiliate.first_name || '';
    var lastName = affiliate.last_name || '';
    var email = affiliate.email || '';
    var initials = (firstName.charAt(0) + lastName.charAt(0)).toUpperCase();
    
    document.getElementById('modal_affiliate_id').value = affiliate.id;
    document.getElementById('modal_avatar').textContent = initials || '??';
    document.getElementById('modal_affiliate_name').textContent = firstName + ' ' + lastName;
    document.getElementById('modal_email').textContent = email;
    document.getElementById('modal_visits').textContent = Number(affiliate.total_visits || 0).toLocaleString();
    document.getElementById('modal_signups').textContent = Number(affiliate.total_signups || 0).toLocaleString();
    document.getElementById('modal_orders').textContent = Number(affiliate.total_orders || 0).toLocaleString();
    document.getElementById('modal_earnings').textContent = Number(affiliate.total_earnings || 0).toLocaleString('tr-TR', {minimumFractionDigits: 0}) + ' ₺';
    document.getElementById('modal_status').value = affiliate.status || 'pending';
    document.getElementById('modal_commission').value = affiliate.commission_rate || 10;
    document.getElementById('modal_commission_type').value = affiliate.commission_type || 'percentage';
    document.getElementById('modal_notes').value = affiliate.notes || '';
    
    var paymentMethod = affiliate.payment_method || 'bank_transfer';
    var methodText = paymentMethod === 'bank_transfer' ? '🏦 Banka Havalesi' : (paymentMethod === 'papara' ? '💜 Papara' : '💙 PayPal');
    document.getElementById('modal_payment_method').textContent = methodText;
    document.getElementById('modal_payment_details').textContent = affiliate.payment_details || 'Ödeme bilgisi girilmemiş';
    
    // Set affiliate ID to all forms
    document.querySelectorAll('.aff_id_field').forEach(function(input) {
        input.value = affiliate.id;
    });
}

function closeAffiliateModal() {
    var modal = document.getElementById('affiliateModal');
    if (modal) modal.classList.remove('active');
}

// Close on outside click
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('affiliateModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) closeAffiliateModal();
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>

