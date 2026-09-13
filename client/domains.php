<?php
/**
 * WHMVM - Domainlerim
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Domainlerim';
$currentPage = 'domains';
$clientId = (int)($_SESSION['client_id'] ?? 0);

include 'includes/header.php';

// Domainleri çek
try {
    $domains = Database::fetchAll("
        SELECT * FROM domains 
        WHERE client_id = ? 
        ORDER BY expiry_date ASC
    ", [$clientId]);
    
    $stats = [
        'total' => count($domains),
        'active' => count(array_filter($domains, fn($d) => $d['status'] === 'active')),
        'expiring' => count(array_filter($domains, fn($d) => $d['expiry_date'] && (strtotime($d['expiry_date']) - time()) / 86400 < 30 && (strtotime($d['expiry_date']) - time()) > 0)),
        'expired' => count(array_filter($domains, fn($d) => $d['status'] === 'expired'))
    ];
} catch (Exception $e) {
    $domains = [];
    $stats = ['total' => 0, 'active' => 0, 'expiring' => 0, 'expired' => 0];
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
.stat-icon.yellow { background: linear-gradient(135deg, #f59e0b, #d97706); }
.stat-icon.red { background: linear-gradient(135deg, #ef4444, #dc2626); }

.stat-info h3 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 4px;
}

.stat-info p {
    color: var(--text-muted);
    font-size: 13px;
}

/* Domains Table */
.domains-card {
    background: var(--card-bg);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    overflow: hidden;
}

.domains-card-header {
    padding: 20px 24px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.domains-card-header h3 {
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.domains-card-header h3 i {
    color: var(--primary-light);
}

.domains-table {
    width: 100%;
    border-collapse: collapse;
}

.domains-table th {
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

.domains-table td {
    padding: 18px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    font-size: 14px;
}

.domains-table tr:hover td {
    background: rgba(255,255,255,0.02);
}

.domains-table tr:last-child td {
    border-bottom: none;
}

.domain-name {
    display: flex;
    align-items: center;
    gap: 12px;
}

.domain-name i {
    color: var(--primary-light);
    font-size: 18px;
}

.domain-name strong {
    font-weight: 600;
}

.date-cell {
    display: flex;
    flex-direction: column;
    gap: 5px;
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
    
    .domains-card {
        overflow-x: auto;
    }
    
    .domains-table {
        min-width: 700px;
    }
}
</style>

<!-- Page Header -->
<div class="page-header">
    <h1><i class="fas fa-globe"></i> Domainlerim</h1>
    <p>Tüm domainlerinizi görüntüleyin ve yönetin</p>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-globe"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['total'] ?></h3>
            <p>Toplam Domain</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['active'] ?></h3>
            <p>Aktif Domain</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon yellow">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['expiring'] ?></h3>
            <p>Süresi Dolacak</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['expired'] ?></h3>
            <p>Süresi Dolmuş</p>
        </div>
    </div>
</div>

<!-- Domains Table -->
<div class="domains-card">
    <div class="domains-card-header">
        <h3><i class="fas fa-list"></i> Domain Listesi</h3>
        <a href="../domain.php" class="btn btn-primary" style="padding: 10px 20px; font-size: 14px;">
            <i class="fas fa-plus"></i> Yeni Domain
        </a>
    </div>
    
    <?php if (empty($domains)): ?>
    <div class="empty-state">
        <i class="fas fa-globe"></i>
        <h3>Henüz domain bulunmuyor</h3>
        <p>Yeni bir domain kaydedin veya transfer edin.</p>
        <a href="../domain.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Domain Kaydet
        </a>
    </div>
    <?php else: ?>
    <table class="domains-table">
        <thead>
            <tr>
                <th>Domain</th>
                <th>Kayıt Tarihi</th>
                <th>Bitiş Tarihi</th>
                <th>Otomatik Yenile</th>
                <th>Durum</th>
                <th>İşlem</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($domains as $domain): ?>
            <tr>
                <td>
                    <div class="domain-name">
                        <i class="fas fa-globe"></i>
                        <strong><?= htmlspecialchars($domain['domain']) ?></strong>
                    </div>
                </td>
                <td>
                    <?= $domain['registration_date'] ? date('d.m.Y', strtotime($domain['registration_date'])) : '-' ?>
                </td>
                <td>
                    <?php if ($domain['expiry_date']): ?>
                        <?php $daysLeft = (strtotime($domain['expiry_date']) - time()) / 86400; ?>
                        <div class="date-cell">
                            <span><?= date('d.m.Y', strtotime($domain['expiry_date'])) ?></span>
                            <?php if ($daysLeft < 0): ?>
                                <span class="badge badge-danger">Süresi Dolmuş</span>
                            <?php elseif ($daysLeft < 30): ?>
                                <span class="badge badge-warning"><?= ceil($daysLeft) ?> gün kaldı</span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($domain['auto_renew'] ?? false): ?>
                        <span class="badge badge-success">Aktif</span>
                    <?php else: ?>
                        <span class="badge badge-secondary">Pasif</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $badge = match($domain['status'] ?? 'pending') {
                        'active' => 'success',
                        'pending' => 'warning',
                        'expired' => 'danger',
                        default => 'gray'
                    };
                    $text = match($domain['status'] ?? 'pending') {
                        'active' => 'Aktif',
                        'pending' => 'Beklemede',
                        'pending_transfer' => 'Transfer Bekliyor',
                        'expired' => 'Süresi Dolmuş',
                        'cancelled' => 'İptal',
                        default => ucfirst($domain['status'] ?? 'Beklemede')
                    };
                    ?>
                    <span class="badge badge-<?= $badge ?>"><?= $text ?></span>
                </td>
                <td>
                    <a href="domain-manage.php?id=<?= $domain['id'] ?>" class="btn btn-outline" style="padding: 8px 14px; font-size: 13px;">
                        <i class="fas fa-cog"></i> Yönet
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
