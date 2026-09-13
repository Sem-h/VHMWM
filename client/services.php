<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
session_name(SESSION_NAME); session_start();

$pageTitle = 'Hizmetlerim';
$currentPage = 'services';
$clientId = (int)($_SESSION['client_id'] ?? 0);

include 'includes/header.php';

// Hizmetleri çek
$services = Database::fetchAll("
    SELECT s.*, p.name as product_name, p.type as product_type, sv.name as server_name
    FROM services s 
    LEFT JOIN products p ON s.product_id = p.id 
    LEFT JOIN servers sv ON s.server_id = sv.id
    WHERE s.client_id = ? 
    ORDER BY s.status = 'active' DESC, s.created_at DESC
", [$clientId]);

// Durum bazlı istatistikler
$stats = ['active' => 0, 'pending' => 0, 'suspended' => 0, 'cancelled' => 0, 'total' => count($services)];
foreach ($services as $service) {
    if (isset($stats[$service['status']])) {
        $stats[$service['status']]++;
    }
    if ($service['status'] === 'terminated') {
        $stats['cancelled']++;
    }
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

.stat-icon.green { background: linear-gradient(135deg, #10b981, #059669); }
.stat-icon.yellow { background: linear-gradient(135deg, #f59e0b, #d97706); }
.stat-icon.red { background: linear-gradient(135deg, #ef4444, #dc2626); }
.stat-icon.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }

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
    cursor: pointer;
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

/* Services Grid */
.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 20px;
}

.service-card {
    background: var(--card-bg);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s;
}

.service-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary);
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
}

.service-header {
    padding: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.service-title {
    display: flex;
    align-items: center;
    gap: 14px;
}

.service-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.service-icon.hosting { background: linear-gradient(135deg, #f97316, #ea580c); }
.service-icon.vps { background: linear-gradient(135deg, #10b981, #059669); }
.service-icon.vds { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
.service-icon.dedicated { background: linear-gradient(135deg, #3b82f6, #2563eb); }

.service-name {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 4px;
}

.service-domain {
    font-size: 13px;
    color: var(--text-muted);
}

.service-body {
    padding: 20px;
}

.service-info-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    font-size: 14px;
}

.service-info-row:last-child {
    border-bottom: none;
}

.service-info-label {
    color: var(--text-muted);
}

.service-info-value {
    font-weight: 600;
}

.service-footer {
    padding: 15px 20px;
    background: rgba(255,255,255,0.02);
    display: flex;
    gap: 10px;
}

.service-footer .btn {
    flex: 1;
    padding: 10px;
    font-size: 13px;
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
    
    .services-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Page Header -->
<div class="page-header">
    <h1><i class="fas fa-server"></i> Hizmetlerim</h1>
    <p>Tüm aktif ve geçmiş hizmetlerinizi yönetin</p>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['active'] ?></h3>
            <p>Aktif Hizmet</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon yellow">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['pending'] ?></h3>
            <p>Beklemede</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fas fa-pause-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['suspended'] ?></h3>
            <p>Askıda</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['cancelled'] ?></h3>
            <p>İptal Edilen</p>
        </div>
    </div>
</div>

<!-- Filter Tabs -->
<div class="filter-tabs">
    <button class="filter-tab active" data-filter="all">
        <i class="fas fa-th-large"></i> Tümü (<?= $stats['total'] ?>)
    </button>
    <button class="filter-tab" data-filter="active">
        <i class="fas fa-check"></i> Aktif (<?= $stats['active'] ?>)
    </button>
    <button class="filter-tab" data-filter="pending">
        <i class="fas fa-clock"></i> Beklemede (<?= $stats['pending'] ?>)
    </button>
    <button class="filter-tab" data-filter="suspended">
        <i class="fas fa-pause"></i> Askıda (<?= $stats['suspended'] ?>)
    </button>
    <button class="filter-tab" data-filter="cancelled">
        <i class="fas fa-times"></i> İptal (<?= $stats['cancelled'] ?>)
    </button>
</div>

<!-- Services Grid -->
<?php if (empty($services)): ?>
<div class="empty-state">
    <i class="fas fa-server"></i>
    <h3>Henüz hizmetiniz bulunmuyor</h3>
    <p>Yeni bir sipariş vererek hizmet satın alabilirsiniz.</p>
    <a href="order.php" class="btn btn-primary" style="margin-top: 20px;">
        <i class="fas fa-shopping-cart"></i> Yeni Sipariş
    </a>
</div>
<?php else: ?>
<div class="services-grid" id="servicesGrid">
    <?php foreach ($services as $service): ?>
    <?php
    $iconClass = match($service['product_type'] ?? 'hosting') {
        'hosting' => 'hosting',
        'vps' => 'vps',
        'vds' => 'vds',
        'dedicated' => 'dedicated',
        default => ''
    };
    
    $statusText = match($service['status']) {
        'active' => 'Aktif',
        'pending' => 'Beklemede',
        'suspended' => 'Askıda',
        'terminated', 'cancelled' => 'İptal',
        default => ucfirst($service['status'])
    };
    
    $statusBadge = match($service['status']) {
        'active' => 'success',
        'pending' => 'warning',
        'suspended' => 'danger',
        'terminated', 'cancelled' => 'gray',
        default => 'gray'
    };
    
    $filterStatus = in_array($service['status'], ['terminated', 'cancelled']) ? 'cancelled' : $service['status'];
    ?>
    <div class="service-card" data-status="<?= $filterStatus ?>">
        <div class="service-header">
            <div class="service-title">
                <div class="service-icon <?= $iconClass ?>">
                    <i class="fas fa-server"></i>
                </div>
                <div>
                    <div class="service-name"><?= htmlspecialchars($service['product_name'] ?? 'Hizmet') ?></div>
                    <div class="service-domain"><?= htmlspecialchars($service['domain'] ?? '-') ?></div>
                </div>
            </div>
            <span class="badge badge-<?= $statusBadge ?>"><?= $statusText ?></span>
        </div>
        
        <div class="service-body">
            <div class="service-info-row">
                <span class="service-info-label">Fiyat</span>
                <span class="service-info-value"><?= number_format((float)$service['amount'], 2, ',', '.') ?>₺ / ay</span>
            </div>
            <div class="service-info-row">
                <span class="service-info-label">Sonraki Ödeme</span>
                <span class="service-info-value"><?= $service['next_due_date'] ? date('d.m.Y', strtotime($service['next_due_date'])) : '-' ?></span>
            </div>
            <div class="service-info-row">
                <span class="service-info-label">Kayıt Tarihi</span>
                <span class="service-info-value"><?= date('d.m.Y', strtotime($service['created_at'])) ?></span>
            </div>
        </div>
        
        <div class="service-footer">
            <a href="service-view.php?id=<?= $service['id'] ?>" class="btn btn-primary">
                <i class="fas fa-eye"></i> Detaylar
            </a>
            <?php if ($service['status'] === 'active'): ?>
            <a href="service-view.php?id=<?= $service['id'] ?>#login" class="btn btn-outline">
                <i class="fas fa-sign-in-alt"></i> Panel
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
// Filter functionality
document.querySelectorAll('.filter-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        const filter = this.dataset.filter;
        const cards = document.querySelectorAll('.service-card');
        
        cards.forEach(card => {
            if (filter === 'all' || card.dataset.status === filter) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
