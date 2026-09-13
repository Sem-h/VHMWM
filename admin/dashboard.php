<?php
/**
 * WHMVM - Admin Dashboard
 * Premium Modern Design
 * PHP 8.1+
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';

require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

// Giriş kontrolü
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// Veritabanı istatistikleri
try {
    $db = Database::getInstance();

    $clientCount = $db->query("SELECT COUNT(*) FROM clients")->fetchColumn();
    $serviceCount = $db->query("SELECT COUNT(*) FROM services WHERE status = 'active'")->fetchColumn();
    $ticketCount = $db->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open', 'customer_reply')")->fetchColumn();
    $invoiceCount = $db->query("SELECT COUNT(*) FROM invoices WHERE status = 'unpaid'")->fetchColumn();
    $revenueMonth = $db->query("SELECT COALESCE(SUM(amount_paid), 0) FROM invoices WHERE status = 'paid' AND MONTH(paid_date) = MONTH(CURRENT_DATE())")->fetchColumn();

    // İptal talepleri
    $cancellationCount = $db->query("SELECT COUNT(*) FROM cancellation_requests WHERE status = 'pending'")->fetchColumn();

    $stats = [
        'clients' => (int) ($clientCount ?: 0),
        'services' => (int) ($serviceCount ?: 0),
        'tickets' => (int) ($ticketCount ?: 0),
        'invoices_unpaid' => (int) ($invoiceCount ?: 0),
        'revenue_month' => (float) ($revenueMonth ?: 0),
        'cancellations' => (int) ($cancellationCount ?: 0),
    ];

    // Son 6 ayın gelir verileri (Grafik için)
    $chartData = $db->query("
        SELECT 
            DATE_FORMAT(paid_date, '%b') as month,
            SUM(amount_paid) as total
        FROM invoices 
        WHERE status = 'paid' 
        AND paid_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
        GROUP BY MONTH(paid_date)
        ORDER BY paid_date ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $recentOrders = $db->query("
        SELECT o.*, c.first_name, c.last_name 
        FROM orders o 
        JOIN clients c ON o.client_id = c.id 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    $recentTickets = $db->query("
        SELECT t.*, c.first_name, c.last_name 
        FROM tickets t 
        JOIN clients c ON t.client_id = c.id 
        ORDER BY t.updated_at DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    $recentCancellations = [];
    if ($stats['cancellations'] > 0) {
        $recentCancellations = $db->query("
            SELECT cr.*, s.domain, p.name as product_name, c.first_name, c.last_name
            FROM cancellation_requests cr
            JOIN services s ON cr.service_id = s.id
            JOIN products p ON s.product_id = p.id
            JOIN clients c ON cr.client_id = c.id
            WHERE cr.status = 'pending'
            ORDER BY cr.created_at DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Veritabanı hatası: " . $e->getMessage());
}

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
include 'includes/header.php';
?>

<!-- Add FontAwesome & Chart.js -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.95);
        --glass-border: rgba(255, 255, 255, 0.2);
        --shadow-soft: 0 10px 30px -5px rgba(0, 0, 0, 0.05);
        --accent-blue: #6366f1;
        --accent-green: #10b981;
        --accent-orange: #f59e0b;
        --accent-red: #ef4444;
        --accent-purple: #8b5cf6;
    }

    .dashboard-container {
        animation: fadeIn 0.5s ease-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Modern Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 24px;
        margin-bottom: 32px;
    }

    .stat-card {
        background: var(--glass-bg);
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 24px;
        position: relative;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: var(--shadow-soft);
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.1);
        border-color: var(--accent-blue);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 100px;
        height: 100px;
        background: radial-gradient(circle at top right, var(--card-color), transparent 70%);
        opacity: 0.1;
        transition: opacity 0.3s;
    }

    .stat-card:hover::before {
        opacity: 0.2;
    }

    .stat-card .icon-wrapper {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        margin-bottom: 16px;
        background: var(--card-light);
        color: var(--card-color);
    }

    .stat-card .value {
        font-size: 28px;
        font-weight: 800;
        color: var(--dark);
        margin-bottom: 4px;
        letter-spacing: -0.5px;
    }

    .stat-card .label {
        font-size: 14px;
        color: var(--gray);
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    /* Widgets Section */
    .widget-container {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
        margin-bottom: 32px;
    }

    @media (max-width: 1200px) {
        .widget-container { grid-template-columns: 1fr; }
    }

    .premium-card {
        background: var(--y-yuzey);
        border-radius: 24px;
        border: 1px solid var(--border);
        box-shadow: var(--shadow-soft);
        padding: 24px;
    }

    .card-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    /* Quick Actions */
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 16px;
        background: var(--light);
        border-radius: 16px;
        text-decoration: none;
        color: var(--dark);
        font-weight: 600;
        font-size: 13px;
        transition: all 0.2s;
        border: 1px solid transparent;
        gap: 10px;
    }

    .action-btn:hover {
        background: var(--y-yuzey);
        border-color: var(--accent-blue);
        color: var(--accent-blue);
        transform: scale(1.02);
    }

    .action-btn i {
        font-size: 20px;
        color: var(--accent-blue);
    }

    /* Table Styles */
    .modern-table { width: 100%; border-collapse: separate; border-spacing: 0 8px; margin-top: -8px; }
    .modern-table tr { box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .modern-table td { padding: 16px; background: var(--y-yuzey); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
    .modern-table td:first-child { border-left: 1px solid var(--border); border-radius: 12px 0 0 12px; }
    .modern-table td:last-child { border-right: 1px solid var(--border); border-radius: 0 12px 12px 0; }
    
    .status-badge {
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .cancellation-alert {
        background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
        border: 1px solid #f59e0b;
        border-radius: 20px;
        padding: 20px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .badge-success { background: #d1fae5; color: #065f46; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    .badge-danger { background: #fee2e2; color: #991b1b; }
    .badge-info { background: #dbeafe; color: #1e40af; }
    .badge-gray { background: var(--y-yuzey-2); color: var(--y-metin-2); }
</style>

<div class="dashboard-container">
    
    <!-- Cancellation Warning -->
    <?php if ($stats['cancellations'] > 0): ?>
        <div class="cancellation-alert">
            <div style="width: 50px; height: 50px; background: #fbbf24; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div style="flex: 1;">
                <h4 style="margin: 0; color: #92400e; font-weight: 700;">Bekleyen İptal Talepleri</h4>
                <p style="margin: 5px 0 0; color: #b45309; font-size: 14px;">Şu anda onaylanması gereken <strong><?= $stats['cancellations'] ?></strong> adet iptal talebi bulunmaktadır.</p>
            </div>
            <a href="cancellations.php" class="btn btn-primary" style="background: #f59e0b; border: none; border-radius: 12px;">Yönet</a>
        </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card" style="--card-color: #6366f1; --card-light: #e0e7ff;">
            <div class="icon-wrapper"><i class="fas fa-users"></i></div>
            <div class="value"><?= number_format($stats['clients']) ?></div>
            <div class="label">Toplam Müşteri <small style="color:var(--accent-green); margin-left:auto;"><i class="fas fa-arrow-up"></i></small></div>
        </div>
        
        <div class="stat-card" style="--card-color: #10b981; --card-light: #d1fae5;">
            <div class="icon-wrapper"><i class="fas fa-box"></i></div>
            <div class="value"><?= number_format($stats['services']) ?></div>
            <div class="label">Aktif Hizmet</div>
        </div>

        <div class="stat-card" style="--card-color: #f59e0b; --card-light: #fef3c7;">
            <div class="icon-wrapper"><i class="fas fa-ticket-alt"></i></div>
            <div class="value"><?= number_format($stats['tickets']) ?></div>
            <div class="label">Açık Destek Talebi</div>
        </div>

        <div class="stat-card" style="--card-color: #8b5cf6; --card-light: #ede9fe;">
            <div class="icon-wrapper"><i class="fas fa-lira-sign"></i></div>
            <div class="value"><?= number_format($stats['revenue_month'], 2) ?> ₺</div>
            <div class="label">Aylık Gelir</div>
        </div>
    </div>

    <!-- Main Widgets Row -->
    <div class="widget-container">
        <!-- Gelir Grafiği -->
        <div class="premium-card">
            <div class="card-title">
                <span>Gelir Analizi</span>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline" style="border-radius: 8px;">Son 6 Ay <i class="fas fa-chevron-down"></i></button>
                </div>
            </div>
            <div style="height: 300px; position: relative;">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="premium-card">
            <div class="card-title">Hızlı İşlemler</div>
            <div class="quick-actions">
                <a href="clients.php?add=1" class="action-btn">
                    <i class="fas fa-user-plus"></i>
                    <span>Müşteri Ekle</span>
                </a>
                <a href="orders.php?add=1" class="action-btn">
                    <i class="fas fa-cart-plus"></i>
                    <span>Yeni Sipariş</span>
                </a>
                <a href="invoices.php?add=1" class="action-btn">
                    <i class="fas fa-file-invoice"></i>
                    <span>Fatura Yaz</span>
                </a>
                <a href="tickets.php" class="action-btn">
                    <i class="fas fa-life-ring"></i>
                    <span>Destek Ver</span>
                </a>
                <a href="settings.php" class="action-btn">
                    <i class="fas fa-cog"></i>
                    <span>Ayarlar</span>
                </a>
                <a href="updates.php" class="action-btn">
                    <i class="fas fa-sync"></i>
                    <span>Güncelle</span>
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Orders -->
        <div class="col-lg-6">
            <div class="premium-card mb-4">
                <div class="card-title">
                    <span><i class="fas fa-shopping-basket text-primary me-2"></i> Son Siparişler</span>
                    <a href="orders.php" class="btn btn-sm btn-outline">Tümünü Gör</a>
                </div>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr style="background: transparent;">
                                <th style="padding: 0 16px 10px; font-size:11px; color:var(--gray); text-transform:uppercase;">No</th>
                                <th style="padding: 0 16px 10px; font-size:11px; color:var(--gray); text-transform:uppercase;">Müşteri</th>
                                <th style="padding: 0 16px 10px; font-size:11px; color:var(--gray); text-transform:uppercase;">Tutar</th>
                                <th style="padding: 0 16px 10px; font-size:11px; color:var(--gray); text-transform:uppercase;">Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                                <tr><td colspan="4" class="text-center p-4">Henüz sipariş yok</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td class="fw-bold">#<?= $order['order_number'] ?></td>
                                        <td><?= htmlspecialchars($order['first_name'].' '.$order['last_name']) ?></td>
                                        <td class="fw-bold"><?= number_format((float)($order['total'] ?? 0), 2) ?> ₺</td>
                                        <td>
                                            <?php
                                            $cls = match($order['status']) {
                                                'active' => 'badge-success',
                                                'pending' => 'badge-warning',
                                                'cancelled' => 'badge-danger',
                                                default => 'badge-gray'
                                            };
                                            ?>
                                            <span class="badge <?= $cls ?>"><?= $order['status'] ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Tickets -->
        <div class="col-lg-6">
            <div class="premium-card mb-4">
                <div class="card-title">
                    <span><i class="fas fa-ticket-alt text-warning me-2"></i> Son Destek Talepleri</span>
                    <a href="tickets.php" class="btn btn-sm btn-outline">Tümünü Gör</a>
                </div>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr style="background: transparent;">
                                <th style="padding: 0 16px 10px; font-size:11px; color:var(--gray); text-transform:uppercase;">Ticket</th>
                                <th style="padding: 0 16px 10px; font-size:11px; color:var(--gray); text-transform:uppercase;">Müşteri</th>
                                <th style="padding: 0 16px 10px; font-size:11px; color:var(--gray); text-transform:uppercase;">Öncelik</th>
                                <th style="padding: 0 16px 10px; font-size:11px; color:var(--gray); text-transform:uppercase;">Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentTickets)): ?>
                                <tr><td colspan="4" class="text-center p-4">Henüz destek talebi yok</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentTickets as $ticket): ?>
                                    <tr>
                                        <td><a href="tickets.php?edit=<?= $ticket['id'] ?>" class="text-decoration-none fw-bold">#<?= $ticket['ticket_number'] ?></a></td>
                                        <td><?= htmlspecialchars($ticket['first_name'].' '.$ticket['last_name']) ?></td>
                                        <td>
                                            <?php
                                            $pCls = match($ticket['priority']) {
                                                'urgent','high' => 'badge-danger',
                                                'medium' => 'badge-warning',
                                                default => 'badge-info'
                                            };
                                            ?>
                                            <span class="badge <?= $pCls ?>"><?= $ticket['priority'] ?></span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $ticket['status'] === 'open' ? 'badge-success' : 'badge-gray' ?>"><?= $ticket['status'] ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Revenue Chart Implementation
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    <?php
    $labels = [];
    $data = [];
    if (!empty($chartData)) {
        foreach ($chartData as $row) {
            $labels[] = $row['month'];
            $data[] = $row['total'];
        }
    } else {
        // Fallback for empty data
        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
        $data = [0, 0, 0, 0, 0, 0];
    }
    ?>

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                label: 'Gelir (₺)',
                data: <?= json_encode($data) ?>,
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false },
                    ticks: { font: { size: 11 }, color: '#64748b' }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 }, color: '#64748b' }
                }
            }
        }
    });
</script>

<?php include 'includes/footer.php'; ?>