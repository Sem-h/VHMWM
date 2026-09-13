<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
session_name(SESSION_NAME); session_start();

$pageTitle = 'Destek Talepleri';
$currentPage = 'tickets';
$db = Database::getInstance();
$message = '';

// Durum güncelleme
if (isset($_GET['close']) && is_numeric($_GET['close'])) {
    $stmt = $db->prepare("UPDATE tickets SET status = 'closed' WHERE id = ?");
    $stmt->execute([$_GET['close']]);
    $message = 'Ticket kapatıldı.';
}

// Sayfalama
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$statusFilter = $_GET['status'] ?? '';
$where = $statusFilter ? "WHERE t.status = '$statusFilter'" : "";

$total = (int)$db->query("SELECT COUNT(*) FROM tickets t $where")->fetchColumn();
$totalPages = ceil($total / $perPage);

$tickets = $db->query("
    SELECT t.*, c.first_name, c.last_name, c.email as client_email,
           d.name as department_name
    FROM tickets t 
    LEFT JOIN clients c ON t.client_id = c.id 
    LEFT JOIN departments d ON t.department_id = d.id
    $where
    ORDER BY 
        CASE t.status 
            WHEN 'open' THEN 1 
            WHEN 'customer_reply' THEN 2 
            WHEN 'answered' THEN 3 
            ELSE 4 
        END,
        t.created_at DESC 
    LIMIT $perPage OFFSET $offset
")->fetchAll();

include 'includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?= $message ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>🎫 Destek Talepleri (<?= $total ?>)</h3>
        <div style="display: flex; gap: 10px;">
            <select onchange="window.location='?status='+this.value" class="form-control" style="width: auto;">
                <option value="">Tüm Durumlar</option>
                <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Açık</option>
                <option value="customer_reply" <?= $statusFilter === 'customer_reply' ? 'selected' : '' ?>>Müşteri Yanıtladı</option>
                <option value="answered" <?= $statusFilter === 'answered' ? 'selected' : '' ?>>Yanıtlandı</option>
                <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Kapalı</option>
            </select>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($tickets)): ?>
            <div class="empty-state">
                <div class="icon">🎫</div>
                <h3>Destek talebi yok</h3>
                <p>Henüz açılmış destek talebi bulunmuyor.</p>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Konu</th>
                        <th>Müşteri</th>
                        <th>Departman</th>
                        <th>Öncelik</th>
                        <th>Durum</th>
                        <th>Tarih</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                    <tr>
                        <td><strong>#<?= htmlspecialchars($ticket['ticket_number']) ?></strong></td>
                        <td><?= htmlspecialchars($ticket['subject']) ?></td>
                        <td>
                            <?php if ($ticket['client_id']): ?>
                                <?= htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']) ?>
                            <?php else: ?>
                                <?= htmlspecialchars($ticket['name'] ?? $ticket['email'] ?? '-') ?>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($ticket['department_name'] ?? '-') ?></td>
                        <td>
                            <?php
                            $priorityBadge = match($ticket['priority']) {
                                'urgent' => 'danger',
                                'high' => 'warning',
                                'medium' => 'info',
                                default => 'gray'
                            };
                            $priorityText = match($ticket['priority']) {
                                'urgent' => 'Acil',
                                'high' => 'Yüksek',
                                'medium' => 'Normal',
                                default => 'Düşük'
                            };
                            ?>
                            <span class="badge badge-<?= $priorityBadge ?>"><?= $priorityText ?></span>
                        </td>
                        <td>
                            <?php
                            $statusBadge = match($ticket['status']) {
                                'open' => 'success',
                                'customer_reply' => 'warning',
                                'answered' => 'info',
                                'closed' => 'gray',
                                default => 'gray'
                            };
                            $statusText = match($ticket['status']) {
                                'open' => 'Açık',
                                'customer_reply' => 'Müşteri Yanıtı',
                                'answered' => 'Yanıtlandı',
                                'closed' => 'Kapalı',
                                default => $ticket['status']
                            };
                            ?>
                            <span class="badge badge-<?= $statusBadge ?>"><?= $statusText ?></span>
                        </td>
                        <td><?= date('d.m.Y H:i', strtotime($ticket['created_at'])) ?></td>
                        <td class="actions">
                            <a href="ticket-view.php?id=<?= $ticket['id'] ?>" class="btn btn-sm btn-primary">Görüntüle</a>
                            <?php if ($ticket['status'] !== 'closed'): ?>
                                <a href="?close=<?= $ticket['id'] ?>" class="btn btn-sm btn-outline">Kapat</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>&status=<?= $statusFilter ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

