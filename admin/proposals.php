<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
session_name(SESSION_NAME); session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Teklif Yönetimi';
$currentPage = 'proposals';

// Auto-Setup Check
try {
    $tableExists = Database::fetchColumn("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'proposals'");
    if (!$tableExists) { header('Location: setup-proposals.php'); exit; }
} catch (Exception $e) {}

// Silme
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    Database::query("DELETE FROM proposals WHERE id = ?", [$id]);
    Database::query("DELETE FROM proposal_items WHERE proposal_id = ?", [$id]);
    header('Location: proposals.php?msg=deleted');
    exit;
}

// İstatistikleri Çek
$stats = Database::fetch("
    SELECT 
        COUNT(*) as total_count,
        SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END) as draft_count,
        SUM(CASE WHEN status = 'Sent' THEN 1 ELSE 0 END) as sent_count,
        SUM(CASE WHEN status = 'Accepted' THEN 1 ELSE 0 END) as accepted_count,
        SUM(CASE WHEN status = 'Accepted' THEN total_amount ELSE 0 END) as accepted_amount
    FROM proposals
");

// Liste Filtreleme
$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT p.*, c.first_name, c.last_name, c.company_name, c.email 
        FROM proposals p 
        LEFT JOIN clients c ON p.client_id = c.id";

if ($statusFilter) {
    if($statusFilter == 'Expired') $sql .= " WHERE p.valid_until < CURDATE() AND p.status != 'Accepted'";
    else $sql .= " WHERE p.status = '$statusFilter'";
}
$sql .= " ORDER BY p.id DESC";

$proposals = Database::fetchAll($sql);

include 'includes/header.php';
?>

<!-- FontAwesome Force Load -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* Dashboard Style Tweaks */
.page-header-actions { display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px; }
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
.stat-card {
    background: white; padding: 20px; border-radius: 12px;
    border: 1px solid #e2e8f0;
    display: flex; align-items: center; gap: 15px;
    transition: transform 0.2s;
}
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
.stat-icon {
    width: 48px; height: 48px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
}
.stat-info h3 { font-size: 24px; font-weight: 700; margin: 0; color: #1e293b; }
.stat-info p { margin: 0; font-size: 13px; color: #64748b; }

/* Filters */
.filter-tabs {
    display: inline-flex; background: white; padding: 5px; border-radius: 10px;
    border: 1px solid #e2e8f0; margin-bottom: 20px;
}
.filter-tab {
    padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600;
    text-decoration: none; color: #64748b; transition: 0.2s;
}
.filter-tab:hover { background: #f1f5f9; color: #1e293b; }
.filter-tab.active { background: #6366f1; color: white; box-shadow: 0 2px 6px rgba(99, 102, 241, 0.3); }

/* Proposal Table */
.proposal-row td { vertical-align: middle; padding: 16px 20px; }
.client-info { display: flex; align-items: center; gap: 10px; }
.client-avatar {
    width: 36px; height: 36px; background: #e0e7ff; border-radius: 50%;
    color: #6366f1; display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 13px;
}
.action-btn {
    width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e2e8f0;
    display: inline-flex; align-items: center; justify-content: center;
    color: #64748b; transition: 0.2s; background: white;
    text-decoration: none;
}
.action-btn i { font-size: 14px; color: #64748b; } /* Force Icon Color */
.action-btn:hover { background: #f8fafc; border-color: #6366f1; }
.action-btn:hover i { color: #6366f1; }

.action-btn.delete:hover { border-color: #ef4444; background: #fef2f2; }
.action-btn.delete:hover i { color: #ef4444; }

@media (max-width: 1200px) { .stats-grid { grid-template-columns: 1fr 1fr; } }
</style>

<!-- Header -->
<div class="page-header-actions">
    <div>
        <h2 style="font-size: 24px; font-weight: 700; color: #1e293b;">Teklif Yönetimi</h2>
        <p style="color: #64748b; font-size: 14px;">Müşteri tekliflerini oluşturun ve yönetin.</p>
    </div>
    <a href="proposal-create.php" class="btn btn-primary" style="padding: 12px 24px; background: #6366f1; color: white; border-radius: 10px; text-decoration: none; border: none;">
        <i class="fas fa-plus"></i> <span style="font-weight: 600;">Yeni Teklif Oluştur</span>
    </a>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: #e0e7ff; color: #4f46e5;"><i class="fas fa-file-invoice"></i></div>
        <div class="stat-info">
            <h3><?= $stats['total_count'] ?? 0 ?></h3>
            <p>Toplam Teklif</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #fef3c7; color: #d97706;"><i class="fas fa-clock"></i></div>
        <div class="stat-info">
            <h3><?= $stats['sent_count'] ?? 0 ?></h3>
            <p>Bekleyen / Gönderilen</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #d1fae5; color: #059669;"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <h3><?= $stats['accepted_count'] ?? 0 ?></h3>
            <p>Onaylanan</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #f3e8ff; color: #9333ea;"><i class="fas fa-coins"></i></div>
        <div class="stat-info">
            <h3><?= number_format((float)($stats['accepted_amount'] ?? 0), 0) ?>₺</h3>
            <p>Kazanılan Tutar</p>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="filter-tabs">
    <a href="proposals.php" class="filter-tab <?= !$statusFilter ? 'active' : '' ?>">Tümü</a>
    <a href="proposals.php?status=Draft" class="filter-tab <?= $statusFilter == 'Draft' ? 'active' : '' ?>"><i class="fas fa-pencil-alt" style="margin-right:4px;"></i> Taslak</a>
    <a href="proposals.php?status=Sent" class="filter-tab <?= $statusFilter == 'Sent' ? 'active' : '' ?>"><i class="fas fa-paper-plane" style="margin-right:4px;"></i> Gönderilen</a>
    <a href="proposals.php?status=Accepted" class="filter-tab <?= $statusFilter == 'Accepted' ? 'active' : '' ?>"><i class="fas fa-check" style="margin-right:4px;"></i> Onaylanan</a>
    <a href="proposals.php?status=Rejected" class="filter-tab <?= $statusFilter == 'Rejected' ? 'active' : '' ?>">Reddedilen</a>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm" style="background:white; border-radius:12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
    <table class="table mb-0" style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                <th style="padding:15px 20px; text-align:left; color:#64748b; font-weight:600; font-size:12px;">#ID</th>
                <th style="padding:15px 20px; text-align:left; color:#64748b; font-weight:600; font-size:12px;">Teklif Konusu</th>
                <th style="padding:15px 20px; text-align:left; color:#64748b; font-weight:600; font-size:12px;">Müşteri</th>
                <th style="padding:15px 20px; text-align:left; color:#64748b; font-weight:600; font-size:12px;">Toplam Tutar</th>
                <th style="padding:15px 20px; text-align:left; color:#64748b; font-weight:600; font-size:12px;">Oluşturma</th>
                <th style="padding:15px 20px; text-align:left; color:#64748b; font-weight:600; font-size:12px;">Durum</th>
                <th style="padding:15px 20px; text-align:right; color:#64748b; font-weight:600; font-size:12px;">İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($proposals)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <div class="empty-state" style="padding:40px; text-align:center;">
                            <i class="fas fa-folder-open icon" style="font-size: 40px; color: #cbd5e1; margin-bottom: 10px; display:block;"></i>
                            <p style="color: #64748b; margin:0;">Henüz kayıtlı teklif bulunmuyor.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($proposals as $p): 
                    $initial = strtoupper(substr($p['first_name'] ?? 'U', 0, 1));
                    $statusColor = match($p['status']) { 'Draft' => '#64748b', 'Sent' => '#3b82f6', 'Accepted' => '#10b981', 'Rejected' => '#ef4444', 'Expired' => '#f59e0b', default => '#64748b' };
                    $statusBg = match($p['status']) { 'Draft' => '#f1f5f9', 'Sent' => '#dbeafe', 'Accepted' => '#d1fae5', 'Rejected' => '#fee2e2', 'Expired' => '#fef3c7', default => '#f1f5f9' };
                ?>
                <tr class="proposal-row" style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding:16px 20px; vertical-align:middle;">
                        <span style="font-family: monospace; font-weight: 600; color: #64748b;">#<?= str_pad((string)$p['id'], 5, '0', STR_PAD_LEFT) ?></span>
                    </td>
                    <td style="padding:16px 20px; vertical-align:middle;">
                        <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($p['subject']) ?></div>
                    </td>
                    <td style="padding:16px 20px; vertical-align:middle;">
                        <div class="client-info">
                            <div class="client-avatar"><?= $initial ?></div>
                            <div>
                                <div style="font-weight: 500; font-size:14px; color:#1e293b;"><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></div>
                                <?php if ($p['company_name']): ?>
                                    <div style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($p['company_name']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td style="padding:16px 20px; vertical-align:middle;">
                        <span style="font-weight: 700; color: #0f172a;"><?= number_format((float)$p['total_amount'], 2) ?></span> 
                        <span style="font-size: 12px; color: #64748b;"><?= $p['currency'] ?></span>
                    </td>
                    <td style="padding:16px 20px; vertical-align:middle; color: #64748b; font-size: 13px;">
                        <?= date('d M Y', strtotime($p['created_at'])) ?>
                        <div style="font-size: 11px; color: #94a3b8;"><?= date('H:i', strtotime($p['created_at'])) ?></div>
                    </td>
                    <td style="padding:16px 20px; vertical-align:middle;">
                        <span class="badge" style="background: <?= $statusBg ?>; color: <?= $statusColor ?>; padding: 6px 12px; border-radius: 6px; font-size:12px; font-weight:600;">
                            <?= match($p['status']) { 'Draft'=>'Taslak', 'Sent'=>'Gönderildi', 'Accepted'=>'Kabul Edildi', 'Rejected'=>'Reddedildi', default=>$p['status'] } ?>
                        </span>
                    </td>
                    <td class="text-end" style="padding:16px 20px; vertical-align:middle; text-align:right;">
                        <a href="proposal-view.php?id=<?= $p['id'] ?>" class="action-btn" title="Görüntüle"><i class="fas fa-eye"></i></a>
                        <a href="proposal-edit.php?id=<?= $p['id'] ?>" class="action-btn" title="Düzenle"><i class="fas fa-pen"></i></a>
                        <a href="proposals.php?delete=<?= $p['id'] ?>" class="action-btn delete" onclick="return confirm('Silmek istediğinize emin misiniz?')" title="Sil"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>