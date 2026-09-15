<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
session_name(SESSION_NAME); session_start();

$pageTitle = 'Faturalarım';
$currentPage = 'invoices';
$clientId = (int)($_SESSION['client_id'] ?? 0);

include 'includes/header.php';

// Faturaları çek
$invoices = Database::fetchAll("
    SELECT * FROM invoices 
    WHERE client_id = ? 
    ORDER BY created_at DESC
", [$clientId]);

// İstatistikler
$stats = ['unpaid' => 0, 'paid' => 0, 'cancelled' => 0, 'total' => count($invoices), 'unpaid_amount' => 0];
foreach ($invoices as $invoice) {
    if ($invoice['status'] === 'unpaid') {
        $stats['unpaid']++;
        $stats['unpaid_amount'] += (float)$invoice['total'];
    } elseif ($invoice['status'] === 'paid') {
        $stats['paid']++;
    } elseif (in_array($invoice['status'], ['cancelled', 'refunded'])) {
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

.stat-icon.red { background: linear-gradient(135deg, #ef4444, #dc2626); }
.stat-icon.green { background: linear-gradient(135deg, #10b981, #059669); }
.stat-icon.gray { background: linear-gradient(135deg, #64748b, #475569); }
.stat-icon.purple { background: linear-gradient(135deg, #4b91fa, #1b5ed4); }

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

/* Invoice Table */
.invoices-table {
    background: var(--card-bg);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    overflow: hidden;
}

.invoices-table table {
    width: 100%;
    border-collapse: collapse;
}

.invoices-table th {
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

.invoices-table td {
    padding: 18px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    font-size: 14px;
}

.invoices-table tr:hover td {
    background: rgba(255,255,255,0.02);
}

.invoices-table tr:last-child td {
    border-bottom: none;
}

.invoice-number {
    font-weight: 600;
    color: var(--primary-light);
}

.invoice-amount {
    font-weight: 700;
}

.invoice-amount.unpaid {
    color: var(--danger);
}

.invoice-amount.paid {
    color: var(--success);
}

.due-date {
    display: flex;
    align-items: center;
    gap: 6px;
}

.due-date.overdue {
    color: var(--danger);
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
    
    .invoices-table {
        overflow-x: auto;
    }
    
    .invoices-table table {
        min-width: 700px;
    }
}
</style>

<!-- Page Header -->
<div class="page-header">
    <h1><i class="fas fa-file-invoice-dollar"></i> Faturalarım</h1>
    <p>Tüm faturalarınızı görüntüleyin ve ödeyin</p>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['unpaid'] ?></h3>
            <p>Ödenmemiş Fatura</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon cyan">
            <i class="fas fa-lira-sign"></i>
        </div>
        <div class="stat-info">
            <h3><?= number_format($stats['unpaid_amount'], 0, ',', '.') ?>₺</h3>
            <p>Toplam Borç</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['paid'] ?></h3>
            <p>Ödenmiş Fatura</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon gray">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['total'] ?></h3>
            <p>Toplam Fatura</p>
        </div>
    </div>
</div>

<!-- Filter Tabs -->
<div class="filter-tabs">
    <button class="filter-tab active" data-filter="all">
        <i class="fas fa-th-large"></i> Tümü (<?= $stats['total'] ?>)
    </button>
    <button class="filter-tab" data-filter="unpaid">
        <i class="fas fa-exclamation"></i> Ödenmemiş (<?= $stats['unpaid'] ?>)
    </button>
    <button class="filter-tab" data-filter="paid">
        <i class="fas fa-check"></i> Ödenmiş (<?= $stats['paid'] ?>)
    </button>
    <button class="filter-tab" data-filter="cancelled">
        <i class="fas fa-times"></i> İptal/İade (<?= $stats['cancelled'] ?>)
    </button>
</div>

<!-- Invoices Table -->
<?php if (empty($invoices)): ?>
<div class="empty-state">
    <i class="fas fa-file-invoice"></i>
    <h3>Henüz faturanız bulunmuyor</h3>
    <p>Sipariş verdiğinizde faturalarınız burada görünecektir.</p>
</div>
<?php else: ?>
<div class="invoices-table">
    <table>
        <thead>
            <tr>
                <th>Fatura No</th>
                <th>Tarih</th>
                <th>Son Ödeme</th>
                <th>Tutar</th>
                <th>Durum</th>
                <th>İşlem</th>
            </tr>
        </thead>
        <tbody id="invoicesTable">
            <?php foreach ($invoices as $invoice): ?>
            <?php
            $statusText = match($invoice['status']) {
                'unpaid' => 'Ödenmemiş',
                'paid' => 'Ödenmiş',
                'cancelled' => 'İptal',
                'refunded' => 'İade Edildi',
                default => ucfirst($invoice['status'])
            };
            
            $statusBadge = match($invoice['status']) {
                'unpaid' => 'danger',
                'paid' => 'success',
                'cancelled', 'refunded' => 'gray',
                default => 'gray'
            };
            
            $isOverdue = $invoice['status'] === 'unpaid' && strtotime($invoice['due_date']) < time();
            $filterStatus = in_array($invoice['status'], ['cancelled', 'refunded']) ? 'cancelled' : $invoice['status'];
            ?>
            <tr data-status="<?= $filterStatus ?>">
                <td>
                    <span class="invoice-number">#<?= htmlspecialchars($invoice['invoice_number']) ?></span>
                </td>
                <td><?= date('d.m.Y', strtotime($invoice['created_at'])) ?></td>
                <td>
                    <span class="due-date <?= $isOverdue ? 'overdue' : '' ?>">
                        <?php if ($isOverdue): ?>
                            <i class="fas fa-exclamation-triangle"></i>
                        <?php endif; ?>
                        <?= date('d.m.Y', strtotime($invoice['due_date'])) ?>
                    </span>
                </td>
                <td>
                    <span class="invoice-amount <?= $invoice['status'] ?>">
                        <?= number_format((float)$invoice['total'], 2, ',', '.') ?>₺
                    </span>
                </td>
                <td>
                    <span class="badge badge-<?= $statusBadge ?>"><?= $statusText ?></span>
                </td>
                <td>
                    <a href="invoice-view.php?id=<?= $invoice['id'] ?>" class="btn btn-outline" style="padding: 8px 14px; font-size: 13px;">
                        <i class="fas fa-eye"></i> Görüntüle
                    </a>
                    <?php if ($invoice['status'] === 'unpaid'): ?>
                    <a href="invoice-pay.php?id=<?= $invoice['id'] ?>" class="btn btn-primary" style="padding: 8px 14px; font-size: 13px;">
                        <i class="fas fa-credit-card"></i> Öde
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script>
// Filter functionality
document.querySelectorAll('.filter-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        const filter = this.dataset.filter;
        const rows = document.querySelectorAll('#invoicesTable tr');
        
        rows.forEach(row => {
            if (filter === 'all' || row.dataset.status === filter) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
