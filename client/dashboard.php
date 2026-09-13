<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
session_name(SESSION_NAME); session_start();

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
$clientId = (int)($_SESSION['client_id'] ?? 0);

include 'includes/header.php';

// İstatistikler
try {
    $stats = [
        'services' => (int)Database::fetchColumn("SELECT COUNT(*) FROM services WHERE client_id = ? AND status = 'active'", [$clientId]),
        'invoices_unpaid' => (int)Database::fetchColumn("SELECT COUNT(*) FROM invoices WHERE client_id = ? AND status = 'unpaid'", [$clientId]),
        'tickets_open' => (int)Database::fetchColumn("SELECT COUNT(*) FROM tickets WHERE client_id = ? AND status IN ('open', 'answered')", [$clientId]),
        'domains' => (int)Database::fetchColumn("SELECT COUNT(*) FROM domains WHERE client_id = ?", [$clientId]),
        'total_spent' => (float)Database::fetchColumn("SELECT COALESCE(SUM(total), 0) FROM invoices WHERE client_id = ? AND status = 'paid'", [$clientId]),
    ];
} catch (Exception $e) {
    $stats = ['services' => 0, 'invoices_unpaid' => 0, 'tickets_open' => 0, 'domains' => 0, 'total_spent' => 0];
}

// Son hizmetler
try {
    $recentServices = Database::fetchAll("
        SELECT s.*, p.name as product_name 
        FROM services s 
        LEFT JOIN products p ON s.product_id = p.id 
        WHERE s.client_id = ? 
        ORDER BY s.created_at DESC 
        LIMIT 5
    ", [$clientId]);
} catch (Exception $e) {
    $recentServices = [];
}

// Ödenmemiş faturalar
try {
    $unpaidInvoices = Database::fetchAll("
        SELECT * FROM invoices 
        WHERE client_id = ? AND status = 'unpaid' 
        ORDER BY due_date ASC 
        LIMIT 5
    ", [$clientId]);
} catch (Exception $e) {
    $unpaidInvoices = [];
}

// Son ticketlar
try {
    $recentTickets = Database::fetchAll("
        SELECT * FROM tickets 
        WHERE client_id = ? 
        ORDER BY created_at DESC 
        LIMIT 3
    ", [$clientId]);
} catch (Exception $e) {
    $recentTickets = [];
}
?>

<style>
/* Dashboard Specific Styles */
.welcome-section {
    background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 50%, #a855f7 100%);
    border-radius: 20px;
    padding: 35px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}

.welcome-section::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    border-radius: 50%;
}

.welcome-content {
    position: relative;
    z-index: 1;
}

.welcome-section h1 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 10px;
}

.welcome-section p {
    opacity: 0.9;
    font-size: 15px;
    margin-bottom: 20px;
}

.welcome-stats {
    display: flex;
    gap: 30px;
    margin-top: 25px;
}

.welcome-stat {
    text-align: center;
}

.welcome-stat-value {
    font-size: 32px;
    font-weight: 800;
}

.welcome-stat-label {
    font-size: 12px;
    opacity: 0.8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--card-bg);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary);
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

.stat-icon.primary {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(99, 102, 241, 0.1));
    color: var(--primary-light);
}

.stat-icon.success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(16, 185, 129, 0.1));
    color: #6ee7b7;
}

.stat-icon.warning {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(245, 158, 11, 0.1));
    color: #fcd34d;
}

.stat-icon.danger {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(239, 68, 68, 0.1));
    color: #fca5a5;
}

.stat-info h3 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 4px;
}

.stat-info p {
    color: var(--text-muted);
    font-size: 13px;
}

/* Content Grid */
.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
}

/* Card Styles */
.dashboard-card {
    background: var(--card-bg);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    margin-bottom: 24px;
}

.dashboard-card-header {
    padding: 20px 24px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.dashboard-card-header h3 {
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.dashboard-card-header h3 i {
    color: var(--primary-light);
}

.dashboard-card-body {
    padding: 20px 24px;
}

/* Service List */
.service-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.service-item:last-child {
    border-bottom: none;
}

.service-info {
    display: flex;
    align-items: center;
    gap: 14px;
}

.service-icon {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(99, 102, 241, 0.1));
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-light);
}

.service-details h4 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 3px;
}

.service-details span {
    font-size: 12px;
    color: var(--text-muted);
}

/* Invoice Item */
.invoice-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.invoice-item:last-child {
    border-bottom: none;
}

.invoice-info h4 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 3px;
}

.invoice-info span {
    font-size: 12px;
    color: var(--text-muted);
}

.invoice-amount {
    text-align: right;
}

.invoice-amount .amount {
    font-size: 16px;
    font-weight: 700;
    color: var(--danger);
}

.invoice-amount .due {
    font-size: 11px;
    color: var(--text-muted);
}

/* Ticket Item */
.ticket-item {
    padding: 16px;
    background: rgba(255,255,255,0.02);
    border-radius: 12px;
    margin-bottom: 12px;
    border-left: 3px solid var(--primary);
}

.ticket-item:last-child {
    margin-bottom: 0;
}

.ticket-item h4 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 6px;
}

.ticket-item p {
    font-size: 12px;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 15px;
}

/* Quick Actions */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.quick-action {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    color: #fff;
    text-decoration: none;
    transition: all 0.2s;
}

.quick-action:hover {
    background: var(--primary);
    border-color: var(--primary);
    transform: translateY(-2px);
}

.quick-action i {
    width: 40px;
    height: 40px;
    background: rgba(255,255,255,0.1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.quick-action span {
    font-size: 14px;
    font-weight: 500;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 40px;
    opacity: 0.3;
    margin-bottom: 15px;
}

.empty-state p {
    font-size: 14px;
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .content-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .welcome-stats {
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .quick-actions {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Welcome Section -->
<div class="welcome-section">
    <div class="welcome-content">
        <h1>👋 Hoş Geldin, <?= htmlspecialchars($currentClient['first_name'] ?? 'Müşteri') ?>!</h1>
        <p>Hesabınızın genel durumuna buradan göz atabilirsiniz.</p>
        
        <div class="welcome-stats">
            <div class="welcome-stat">
                <div class="welcome-stat-value"><?= $stats['services'] ?></div>
                <div class="welcome-stat-label">Aktif Hizmet</div>
            </div>
            <div class="welcome-stat">
                <div class="welcome-stat-value"><?= number_format($stats['total_spent'], 0, ',', '.') ?>₺</div>
                <div class="welcome-stat-label">Toplam Harcama</div>
            </div>
            <div class="welcome-stat">
                <div class="welcome-stat-value"><?= number_format((float)($currentClient['credit_balance'] ?? 0), 0, ',', '.') ?>₺</div>
                <div class="welcome-stat-label">Bakiye</div>
            </div>
        </div>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="fas fa-server"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['services'] ?></h3>
            <p>Aktif Hizmet</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon danger">
            <i class="fas fa-file-invoice-dollar"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['invoices_unpaid'] ?></h3>
            <p>Ödenmemiş Fatura</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="fas fa-headset"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['tickets_open'] ?></h3>
            <p>Açık Destek Talebi</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon success">
            <i class="fas fa-globe"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['domains'] ?></h3>
            <p>Domain</p>
        </div>
    </div>
</div>

<!-- Content Grid -->
<div class="content-grid">
    <div>
        <!-- Son Hizmetler -->
        <div class="dashboard-card">
            <div class="dashboard-card-header" style="background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); border-bottom-color: rgba(255,255,255,0.2);">
                <h3 style="color: #fff;"><i class="fas fa-server"></i> Son Hizmetler</h3>
                <a href="services.php" class="btn btn-outline" style="padding: 8px 16px; font-size: 13px; background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.3); color: #fff;">
                    Tümünü Gör
                </a>
            </div>
            <div class="dashboard-card-body">
                <?php if (empty($recentServices)): ?>
                <div class="empty-state">
                    <i class="fas fa-server"></i>
                    <p>Henüz aktif hizmetiniz bulunmuyor.</p>
                </div>
                <?php else: ?>
                <?php foreach ($recentServices as $service): ?>
                <div class="service-item">
                    <div class="service-info">
                        <div class="service-icon">
                            <i class="fas fa-server"></i>
                        </div>
                        <div class="service-details">
                            <h4><?= htmlspecialchars($service['product_name'] ?? 'Hizmet') ?></h4>
                            <span><?= htmlspecialchars($service['domain'] ?? '-') ?></span>
                        </div>
                    </div>
                    <?php
                    $statusBadge = match($service['status']) {
                        'active' => ['success', 'Aktif'],
                        'pending' => ['warning', 'Beklemede'],
                        'suspended' => ['danger', 'Askıda'],
                        default => ['gray', ucfirst($service['status'])]
                    };
                    ?>
                    <span class="badge badge-<?= $statusBadge[0] ?>"><?= $statusBadge[1] ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Ödenmemiş Faturalar -->
        <div class="dashboard-card">
            <div class="dashboard-card-header" style="background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); border-bottom-color: rgba(255,255,255,0.2);">
                <h3 style="color: #fff;"><i class="fas fa-file-invoice-dollar"></i> Ödenmemiş Faturalar</h3>
                <a href="invoices.php" class="btn btn-outline" style="padding: 8px 16px; font-size: 13px; background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.3); color: #fff;">
                    Tümünü Gör
                </a>
            </div>
            <div class="dashboard-card-body">
                <?php if (empty($unpaidInvoices)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>Tüm faturalarınız ödenmiş! 🎉</p>
                </div>
                <?php else: ?>
                <?php foreach ($unpaidInvoices as $invoice): ?>
                <div class="invoice-item">
                    <div class="invoice-info">
                        <h4>#<?= htmlspecialchars($invoice['invoice_number']) ?></h4>
                        <span><?= date('d.m.Y', strtotime($invoice['created_at'])) ?></span>
                    </div>
                    <div class="invoice-amount">
                        <div class="amount"><?= number_format((float)$invoice['total'], 2, ',', '.') ?>₺</div>
                        <div class="due">Son: <?= date('d.m.Y', strtotime($invoice['due_date'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div>
        <!-- Hızlı İşlemler -->
        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <h3><i class="fas fa-bolt"></i> Hızlı İşlemler</h3>
            </div>
            <div class="dashboard-card-body">
                <div class="quick-actions">
                    <a href="order.php" class="quick-action">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Yeni Sipariş</span>
                    </a>
                    <a href="tickets-new.php" class="quick-action">
                        <i class="fas fa-plus-circle"></i>
                        <span>Destek Talebi</span>
                    </a>
                    <a href="invoices.php" class="quick-action">
                        <i class="fas fa-file-invoice"></i>
                        <span>Faturalarım</span>
                    </a>
                    <a href="profile.php" class="quick-action">
                        <i class="fas fa-user-edit"></i>
                        <span>Profilim</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Son Destek Talepleri -->
        <div class="dashboard-card">
            <div class="dashboard-card-header" style="background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); border-bottom-color: rgba(255,255,255,0.2);">
                <h3 style="color: #fff;"><i class="fas fa-headset"></i> Son Destek Talepleri</h3>
                <a href="tickets.php" class="btn btn-outline" style="padding: 8px 16px; font-size: 13px; background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.3); color: #fff;">
                    Tümü
                </a>
            </div>
            <div class="dashboard-card-body">
                <?php if (empty($recentTickets)): ?>
                <div class="empty-state">
                    <i class="fas fa-headset"></i>
                    <p>Henüz destek talebiniz yok.</p>
                </div>
                <?php else: ?>
                <?php foreach ($recentTickets as $ticket): ?>
                <a href="ticket-view.php?id=<?= $ticket['id'] ?>" class="ticket-item" style="text-decoration: none; color: inherit; display: block;">
                    <h4><?= htmlspecialchars($ticket['subject']) ?></h4>
                    <p>
                        <span><i class="fas fa-clock"></i> <?= date('d.m.Y', strtotime($ticket['created_at'])) ?></span>
                        <?php
                        $ticketStatus = match($ticket['status']) {
                            'open' => ['warning', 'Açık'],
                            'answered' => ['success', 'Yanıtlandı'],
                            'customer-reply' => ['info', 'Yanıt Bekleniyor'],
                            'closed' => ['gray', 'Kapalı'],
                            default => ['gray', ucfirst($ticket['status'])]
                        };
                        ?>
                        <span class="badge badge-<?= $ticketStatus[0] ?>"><?= $ticketStatus[1] ?></span>
                    </p>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
