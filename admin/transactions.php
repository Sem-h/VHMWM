<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

$pageTitle = 'İşlemler';
$currentPage = 'transactions';
$db = Database::getInstance();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$total = (int)$db->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
$totalPages = ceil($total / $perPage);

$transactions = $db->query("
    SELECT t.*, c.first_name, c.last_name, c.email, i.invoice_number
    FROM transactions t 
    LEFT JOIN clients c ON t.client_id = c.id 
    LEFT JOIN invoices i ON t.invoice_id = i.id
    ORDER BY t.created_at DESC 
    LIMIT $perPage OFFSET $offset
")->fetchAll();

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3>💳 Ödeme İşlemleri (<?= $total ?>)</h3>
    </div>
    <div class="card-body">
        <?php if (empty($transactions)): ?>
            <div class="empty-state">
                <div class="icon">💳</div>
                <h3>Henüz işlem yok</h3>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Müşteri</th>
                        <th>Fatura</th>
                        <th>Gateway</th>
                        <th>Tür</th>
                        <th>Tutar</th>
                        <th>Durum</th>
                        <th>Tarih</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx): ?>
                    <tr>
                        <td>#<?= $tx['id'] ?></td>
                        <td>
                            <?= htmlspecialchars(($tx['first_name'] ?? '') . ' ' . ($tx['last_name'] ?? '')) ?>
                        </td>
                        <td><?= $tx['invoice_number'] ? htmlspecialchars($tx['invoice_number']) : '-' ?></td>
                        <td><?= htmlspecialchars(ucfirst($tx['gateway'])) ?></td>
                        <td>
                            <?php
                            $typeBadge = match($tx['type']) {
                                'payment' => 'success',
                                'refund' => 'warning',
                                'credit' => 'info',
                                default => 'gray'
                            };
                            $typeText = match($tx['type']) {
                                'payment' => 'Ödeme',
                                'refund' => 'İade',
                                'credit' => 'Kredi',
                                default => $tx['type']
                            };
                            ?>
                            <span class="badge badge-<?= $typeBadge ?>"><?= $typeText ?></span>
                        </td>
                        <td>
                            <strong style="color: <?= $tx['type'] === 'refund' ? 'var(--danger)' : 'var(--success)' ?>">
                                <?= $tx['type'] === 'refund' ? '-' : '+' ?><?= number_format((float)$tx['amount'], 2) ?> <?= $tx['currency'] ?>
                            </strong>
                        </td>
                        <td>
                            <?php
                            $statusBadge = match($tx['status']) {
                                'success' => 'success',
                                'pending' => 'warning',
                                'failed' => 'danger',
                                default => 'gray'
                            };
                            $statusText = match($tx['status']) {
                                'success' => 'Başarılı',
                                'pending' => 'Beklemede',
                                'failed' => 'Başarısız',
                                'refunded' => 'İade Edildi',
                                default => $tx['status']
                            };
                            ?>
                            <span class="badge badge-<?= $statusBadge ?>"><?= $statusText ?></span>
                        </td>
                        <td><?= date('d.m.Y H:i', strtotime($tx['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

