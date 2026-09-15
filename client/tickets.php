<?php
/**
 * WHMVM - Destek Taleplerim
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Destek Taleplerim';
$currentPage = 'tickets';
$clientId = (int)($_SESSION['client_id'] ?? 0);

include 'includes/header.php';

$statusFilter = $_GET['status'] ?? '';

try {
    $stats = [
        'total' => (int)Database::fetchColumn("SELECT COUNT(*) FROM tickets WHERE client_id = ?", [$clientId]),
        'open' => (int)Database::fetchColumn("SELECT COUNT(*) FROM tickets WHERE client_id = ? AND status = 'open'", [$clientId]),
        'answered' => (int)Database::fetchColumn("SELECT COUNT(*) FROM tickets WHERE client_id = ? AND status = 'answered'", [$clientId]),
        'closed' => (int)Database::fetchColumn("SELECT COUNT(*) FROM tickets WHERE client_id = ? AND status = 'closed'", [$clientId])
    ];
    
    $params = [$clientId];
    $statusWhere = '';
    if ($statusFilter && in_array($statusFilter, ['open', 'answered', 'closed', 'customer_reply'])) {
        $statusWhere = " AND status = ?";
        $params[] = $statusFilter;
    }
    
    $tickets = Database::fetchAll("
        SELECT t.*, d.name as department_name
        FROM tickets t 
        LEFT JOIN departments d ON t.department_id = d.id
        WHERE t.client_id = ? {$statusWhere}
        ORDER BY 
            CASE t.status 
                WHEN 'open' THEN 1 
                WHEN 'customer_reply' THEN 2 
                WHEN 'answered' THEN 3 
                ELSE 4 
            END,
            t.updated_at DESC
    ", $params);
    
} catch (Exception $e) {
    $stats = ['total' => 0, 'open' => 0, 'answered' => 0, 'closed' => 0];
    $tickets = [];
}
?>

<style>
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
    color: white;
}

.stat-icon.blue { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.stat-icon.green { background: linear-gradient(135deg, #10b981, #059669); }
.stat-icon.purple { background: linear-gradient(135deg, #4b91fa, #1b5ed4); }
.stat-icon.gray { background: linear-gradient(135deg, #64748b, #475569); }

.stat-info h3 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 4px;
}

.stat-info p {
    color: var(--text-muted);
    font-size: 13px;
}

/* Filter Tabs */
.filter-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.filter-tab {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    color: var(--text-muted);
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
    text-decoration: none;
}

.filter-tab:hover {
    background: rgba(255,255,255,0.1);
    color: #fff;
}

.filter-tab.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}

.filter-tab .count {
    background: rgba(255,255,255,0.2);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 12px;
}

/* Tickets Card */
.tickets-card {
    background: var(--card-bg);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    overflow: hidden;
}

.tickets-card-header {
    padding: 20px 24px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.tickets-card-header h3 {
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.tickets-card-header h3 i {
    color: var(--primary-light);
}

/* Tickets Table */
.tickets-table {
    width: 100%;
    border-collapse: collapse;
}

.tickets-table th {
    text-align: left;
    padding: 16px 20px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: rgba(255,255,255,0.02);
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.tickets-table td {
    padding: 18px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    font-size: 14px;
}

.tickets-table tr:hover td {
    background: rgba(255,255,255,0.02);
}

.tickets-table tr:last-child td {
    border-bottom: none;
}

.ticket-number {
    color: var(--primary-light);
    font-weight: 600;
    text-decoration: none;
}

.ticket-number:hover {
    text-decoration: underline;
}

.ticket-subject {
    color: #fff;
    text-decoration: none;
    font-weight: 500;
}

.ticket-subject:hover {
    color: var(--primary-light);
}

.badge-secondary {
    background: rgba(100, 116, 139, 0.2);
    color: #94a3b8;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 60px;
    opacity: 0.2;
    margin-bottom: 20px;
}

.empty-state h3 {
    font-size: 20px;
    margin-bottom: 10px;
    color: #fff;
}

.empty-state p {
    margin-bottom: 20px;
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .tickets-card {
        overflow-x: auto;
    }
    
    .tickets-table {
        min-width: 800px;
    }
}
</style>

<!-- Page Header -->
<div class="page-header">
    <h1><i class="fas fa-headset"></i> Destek Taleplerim</h1>
    <p>Destek taleplerinizi görüntüleyin ve yönetin</p>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-ticket-alt"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['total'] ?></h3>
            <p>Toplam Talep</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-folder-open"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['open'] ?></h3>
            <p>Açık Talep</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon cyan">
            <i class="fas fa-reply"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['answered'] ?></h3>
            <p>Yanıtlandı</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon gray">
            <i class="fas fa-check-double"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['closed'] ?></h3>
            <p>Çözümlendi</p>
        </div>
    </div>
</div>

<!-- Filter Tabs -->
<div class="filter-tabs">
    <a href="tickets.php" class="filter-tab <?= !$statusFilter ? 'active' : '' ?>">
        <i class="fas fa-list"></i> Tümü <span class="count"><?= $stats['total'] ?></span>
    </a>
    <a href="?status=open" class="filter-tab <?= $statusFilter === 'open' ? 'active' : '' ?>">
        <i class="fas fa-folder-open"></i> Açık <span class="count"><?= $stats['open'] ?></span>
    </a>
    <a href="?status=answered" class="filter-tab <?= $statusFilter === 'answered' ? 'active' : '' ?>">
        <i class="fas fa-reply"></i> Yanıtlandı <span class="count"><?= $stats['answered'] ?></span>
    </a>
    <a href="?status=closed" class="filter-tab <?= $statusFilter === 'closed' ? 'active' : '' ?>">
        <i class="fas fa-check"></i> Kapalı <span class="count"><?= $stats['closed'] ?></span>
    </a>
</div>

<!-- Tickets Card -->
<div class="tickets-card">
    <div class="tickets-card-header">
        <h3><i class="fas fa-list"></i> Tüm Talepler</h3>
        <a href="tickets-new.php" class="btn btn-primary" style="padding: 10px 20px; font-size: 14px;">
            <i class="fas fa-plus"></i> Yeni Talep
        </a>
    </div>
    
    <?php if (empty($tickets)): ?>
    <div class="empty-state">
        <i class="fas fa-headset"></i>
        <h3>Destek talebi bulunmuyor</h3>
        <p>
            <?php if ($statusFilter): ?>
                Bu kriterlere uygun talep yok.
            <?php else: ?>
                Yardıma mı ihtiyacınız var? Hemen bir destek talebi oluşturun.
            <?php endif; ?>
        </p>
        <?php if ($statusFilter): ?>
            <a href="tickets.php" class="btn btn-outline">Tüm Talepleri Göster</a>
        <?php else: ?>
            <a href="tickets-new.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Yeni Talep Oluştur
            </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <table class="tickets-table">
        <thead>
            <tr>
                <th>Talep No</th>
                <th>Departman</th>
                <th>Konu</th>
                <th>Öncelik</th>
                <th>Son Güncelleme</th>
                <th>Durum</th>
                <th>İşlem</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tickets as $ticket): ?>
            <tr>
                <td>
                    <a href="ticket-view.php?id=<?= $ticket['id'] ?>" class="ticket-number">
                        #<?= htmlspecialchars($ticket['ticket_number'] ?? $ticket['id']) ?>
                    </a>
                </td>
                <td>
                    <span class="badge badge-info">
                        <?= htmlspecialchars($ticket['department_name'] ?? 'Genel') ?>
                    </span>
                </td>
                <td>
                    <a href="ticket-view.php?id=<?= $ticket['id'] ?>" class="ticket-subject">
                        <?= htmlspecialchars($ticket['subject']) ?>
                    </a>
                </td>
                <td>
                    <?php
                    $pBadge = match($ticket['priority'] ?? 'medium') {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        default => 'gray'
                    };
                    $pText = match($ticket['priority'] ?? 'medium') {
                        'urgent' => 'Acil',
                        'high' => 'Yüksek',
                        'medium' => 'Normal',
                        default => 'Düşük'
                    };
                    ?>
                    <span class="badge badge-<?= $pBadge ?>"><?= $pText ?></span>
                </td>
                <td>
                    <?= date('d.m.Y H:i', strtotime($ticket['updated_at'])) ?>
                </td>
                <td>
                    <?php
                    $sBadge = match($ticket['status']) {
                        'open' => 'warning',
                        'answered' => 'success',
                        'customer_reply' => 'info',
                        'closed' => 'gray',
                        default => 'gray'
                    };
                    $sText = match($ticket['status']) {
                        'open' => 'Açık',
                        'answered' => 'Yanıtlandı',
                        'customer_reply' => 'Yanıt Bekliyor',
                        'closed' => 'Kapalı',
                        default => ucfirst($ticket['status'])
                    };
                    ?>
                    <span class="badge badge-<?= $sBadge ?>"><?= $sText ?></span>
                </td>
                <td>
                    <a href="ticket-view.php?id=<?= $ticket['id'] ?>" class="btn btn-outline" style="padding: 8px 14px; font-size: 13px;">
                        <i class="fas fa-eye"></i> Görüntüle
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
