<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

$pageTitle = 'Müşteriler';
$currentPage = 'clients';
$db = Database::getInstance();
$message = '';
$messageType = '';

// Silme işlemi
/* Durum degistiren islem POST ile gelir; belirtec dogrulanir. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && is_numeric($_POST['delete'])) {
    Guvenlik::zorunlu();
    $stmt = $db->prepare("DELETE FROM clients WHERE id = ?");
    $stmt->execute([$_POST['delete']]);
    $message = 'Müşteri silindi.';
    $messageType = 'success';
}

// Yeni müşteri ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $stmt = $db->prepare("INSERT INTO clients (email, password, first_name, last_name, company_name, phone, address, city, country) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['email'],
            password_hash($_POST['password'], PASSWORD_DEFAULT),
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['company_name'] ?? '',
            $_POST['phone'] ?? '',
            $_POST['address'] ?? '',
            $_POST['city'] ?? '',
            $_POST['country'] ?? 'TR'
        ]);
        $message = 'Müşteri başarıyla eklendi.';
        $messageType = 'success';
    }
}

// Arama
$search = trim($_GET['search'] ?? '');
$searchCondition = '';
$searchParams = [];
if ($search) {
    $searchCondition = " WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR company_name LIKE ? OR phone LIKE ?)";
    $searchParams = array_fill(0, 5, "%$search%");
}

// Sayfalama
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$totalStmt = $db->prepare("SELECT COUNT(*) FROM clients" . $searchCondition);
$totalStmt->execute($searchParams);
$total = (int) $totalStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// İstatistikler
$activeCount = (int) $db->query("SELECT COUNT(*) FROM clients WHERE is_active = 1")->fetchColumn();
$corporateCount = (int) $db->query("SELECT COUNT(*) FROM clients WHERE company_name IS NOT NULL AND company_name != ''")->fetchColumn();
$thisMonthCount = (int) $db->query("SELECT COUNT(*) FROM clients WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();

$clientsStmt = $db->prepare("SELECT * FROM clients" . $searchCondition . " ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$clientsStmt->execute($searchParams);
$clients = $clientsStmt->fetchAll();

include 'includes/header.php';
?>

<style>
    /* Premium Clients Page Styles */
    .clients-page {
        animation: fadeIn 0.3s ease-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 24px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
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

    .stat-icon.purple {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
    }

    .stat-icon.green {
        background: linear-gradient(135deg, #10b981, #34d399);
    }

    .stat-icon.orange {
        background: linear-gradient(135deg, #f59e0b, #fbbf24);
    }

    .stat-icon.blue {
        background: linear-gradient(135deg, #3b82f6, #60a5fa);
    }

    .stat-value {
        font-size: 28px;
        font-weight: 800;
        color: #1e293b;
    }

    .stat-label {
        font-size: 13px;
        color: #64748b;
        margin-top: 2px;
    }

    /* Header Section */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .page-title {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .page-title h1 {
        font-size: 28px;
        font-weight: 800;
        color: #1e293b;
        margin: 0;
    }

    .page-title .count {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: white;
        padding: 6px 14px;
        border-radius: 50px;
        font-size: 14px;
        font-weight: 600;
    }

    .header-actions {
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .search-box {
        position: relative;
    }

    .search-box input {
        padding: 12px 20px 12px 45px;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 14px;
        width: 280px;
        transition: all 0.2s;
    }

    .search-box input:focus {
        outline: none;
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    .search-box::before {
        content: '🔍';
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 16px;
    }

    .btn-add {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
    }

    .btn-add:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
    }

    /* Clients Table */
    .clients-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    }

    .clients-table {
        width: 100%;
        border-collapse: collapse;
    }

    .clients-table thead {
        background: #f8fafc;
    }

    .clients-table th {
        padding: 16px 20px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
    }

    .clients-table td {
        padding: 18px 20px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
        color: #334155;
    }

    .clients-table tbody tr {
        transition: background 0.15s;
    }

    .clients-table tbody tr:hover {
        background: #f8fafc;
    }

    /* Client Info Cell */
    .client-info {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .client-avatar {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
    }

    .client-details h4 {
        margin: 0 0 3px 0;
        font-size: 15px;
        font-weight: 600;
        color: #1e293b;
    }

    .client-details h4:hover {
        color: #6366f1;
    }

    .client-details span {
        font-size: 13px;
        color: #64748b;
    }

    /* Contact Cell */
    .contact-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .contact-info a {
        color: #6366f1;
        text-decoration: none;
        font-size: 13px;
    }

    .contact-info a:hover {
        text-decoration: underline;
    }

    .contact-info .phone {
        color: #64748b;
        font-size: 13px;
    }

    /* Company Badge */
    .company-badge {
        background: #f1f5f9;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 13px;
        color: #475569;
        display: inline-block;
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .company-badge.empty {
        color: #94a3b8;
        font-style: italic;
    }

    /* Balance */
    .balance {
        font-weight: 600;
        color: #10b981;
    }

    .balance.zero {
        color: #94a3b8;
    }

    /* Status Badge */
    .status-badge {
        padding: 6px 14px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .status-badge.active {
        background: #d1fae5;
        color: #065f46;
    }

    .status-badge.inactive {
        background: #fee2e2;
        color: #991b1b;
    }

    /* Date */
    .date-cell {
        color: #64748b;
        font-size: 13px;
    }

    /* Actions */
    .actions-cell {
        display: flex;
        gap: 6px;
        align-items: center;
    }

    .action-btn {
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.15s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        cursor: pointer;
        border: none;
        white-space: nowrap;
    }

    .action-btn.view {
        background: #6366f1;
        color: white;
    }

    .action-btn.view:hover {
        background: #4f46e5;
    }

    .action-btn.delete {
        background: #fee2e2;
        color: #dc2626;
        padding: 6px 10px;
    }

    .action-btn.delete:hover {
        background: #fecaca;
    }

    /* Pagination */
    .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 25px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }

    .pagination-info {
        font-size: 14px;
        color: #64748b;
    }

    .pagination-links {
        display: flex;
        gap: 6px;
    }

    .pagination-links a {
        padding: 8px 14px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        color: #64748b;
        text-decoration: none;
        background: white;
        border: 1px solid #e2e8f0;
        transition: all 0.15s;
    }

    .pagination-links a:hover {
        background: #f1f5f9;
        color: #1e293b;
    }

    .pagination-links a.active {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: white;
        border-color: transparent;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 80px 20px;
    }

    .empty-state .icon {
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
        margin: 0 auto 25px;
    }

    .empty-state h3 {
        font-size: 22px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 10px;
    }

    .empty-state p {
        color: #64748b;
        margin-bottom: 25px;
    }

    /* Modal Premium Styling */
    #addModal .modal-content {
        max-width: 600px;
        border-radius: 20px;
    }

    #addModal .modal-header {
        padding: 25px 30px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: white;
        border-radius: 20px 20px 0 0;
    }

    #addModal .modal-header h3 {
        font-size: 20px;
        font-weight: 700;
    }

    #addModal .modal-body {
        padding: 30px;
    }

    #addModal .form-group label {
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 8px;
        display: block;
    }

    #addModal .form-control {
        border-radius: 10px;
        border: 2px solid #e2e8f0;
        padding: 12px 16px;
    }

    #addModal .form-control:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    #addModal .modal-footer {
        padding: 20px 30px;
        background: #f8fafc;
        border-radius: 0 0 20px 20px;
    }

    /* Alert Premium */
    .alert-premium {
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
    }

    .alert-premium.success {
        background: #d1fae5;
        color: #065f46;
        border-left: 4px solid #10b981;
    }

    .alert-premium.error {
        background: #fee2e2;
        color: #991b1b;
        border-left: 4px solid #ef4444;
    }

    /* Responsive */
    @media (max-width: 1200px) {

        .clients-table th:nth-child(5),
        .clients-table td:nth-child(5) {
            display: none;
        }
    }

    @media (max-width: 992px) {
        .search-box input {
            width: 200px;
        }

        .clients-table th:nth-child(4),
        .clients-table td:nth-child(4) {
            display: none;
        }
    }

    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .header-actions {
            width: 100%;
            flex-wrap: wrap;
        }

        .search-box {
            width: 100%;
        }

        .search-box input {
            width: 100%;
        }
    }
</style>

<div class="clients-page">
    <?php if ($message): ?>
        <div class="alert-premium <?= $messageType ?>">
            <?= $messageType === 'success' ? '✅' : '❌' ?>     <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon purple">👥</div>
            <div>
                <div class="stat-value"><?= $total ?></div>
                <div class="stat-label">Toplam Müşteri</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">✅</div>
            <div>
                <div class="stat-value"><?= $activeCount ?></div>
                <div class="stat-label">Aktif Müşteri</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange">🏢</div>
            <div>
                <div class="stat-value"><?= $corporateCount ?></div>
                <div class="stat-label">Kurumsal</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">📅</div>
            <div>
                <div class="stat-value"><?= $thisMonthCount ?></div>
                <div class="stat-label">Bu Ay Eklenen</div>
            </div>
        </div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <h1>Müşteri Yönetimi</h1>
            <span class="count"><?= $total ?> kayıt</span>
        </div>
        <div class="header-actions">
            <form method="GET" class="search-box">
                <input type="text" name="search" placeholder="Ara... (ad, e-posta, şirket)"
                    value="<?= htmlspecialchars($search) ?>">
            </form>
            <button onclick="openModal('addModal')" class="btn-add">
                <span>+</span> Yeni Müşteri
            </button>
        </div>
    </div>

    <!-- Clients Table -->
    <div class="clients-card">
        <?php if (empty($clients)): ?>
            <div class="empty-state">
                <div class="icon">👥</div>
                <h3>Henüz müşteri yok</h3>
                <p>İlk müşterinizi eklemek için butona tıklayın.</p>
                <button onclick="openModal('addModal')" class="btn-add">+ Yeni Müşteri Ekle</button>
            </div>
        <?php else: ?>
            <table class="clients-table">
                <thead>
                    <tr>
                        <th>Müşteri</th>
                        <th>İletişim</th>
                        <th>Şirket</th>
                        <th>Bakiye</th>
                        <th>Durum</th>
                        <th>Kayıt</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td>
                                <a href="client-edit.php?id=<?= $client['id'] ?>" style="text-decoration: none;">
                                    <div class="client-info">
                                        <div class="client-avatar">
                                            <?= strtoupper(substr($client['first_name'], 0, 1) . substr($client['last_name'], 0, 1)) ?>
                                        </div>
                                        <div class="client-details">
                                            <h4><?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?></h4>
                                            <span>#<?= $client['id'] ?></span>
                                        </div>
                                    </div>
                                </a>
                            </td>
                            <td>
                                <div class="contact-info">
                                    <a
                                        href="mailto:<?= htmlspecialchars($client['email']) ?>"><?= htmlspecialchars($client['email']) ?></a>
                                    <span class="phone"><?= htmlspecialchars($client['phone'] ?? '-') ?></span>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($client['company_name'])): ?>
                                    <span class="company-badge"
                                        title="<?= htmlspecialchars($client['company_name']) ?>"><?= htmlspecialchars($client['company_name']) ?></span>
                                <?php else: ?>
                                    <span class="company-badge empty">Bireysel</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $balance = (float) ($client['credit_balance'] ?? 0); ?>
                                <span class="balance <?= $balance <= 0 ? 'zero' : '' ?>">
                                    <?= number_format($balance, 2) ?> ₺
                                </span>
                            </td>
                            <td>
                                <?php if ($client['is_active']): ?>
                                    <span class="status-badge active">● Aktif</span>
                                <?php else: ?>
                                    <span class="status-badge inactive">● Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td class="date-cell">
                                <?= date('d.m.Y', strtotime($client['created_at'])) ?>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <a href="client-edit.php?id=<?= $client['id'] ?>" class="action-btn view">👁 Görüntüle</a>
                                    <button
                                        onclick="confirmDelete('Bu müşteriyi silmek istediğinizden emin misiniz?', '?delete=<?= $client['id'] ?>')"
                                        class="action-btn delete">🗑</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        Sayfa <?= $page ?> / <?= $totalPages ?> (Toplam <?= $total ?> kayıt)
                    </div>
                    <div class="pagination-links">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">← Önceki</a>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        for ($i = $start; $i <= $end; $i++):
                            ?>
                            <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>"
                                class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">Sonraki →</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Yeni Müşteri Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>👤 Yeni Müşteri Ekle</h3>
            <button onclick="closeModal('addModal')" class="close-btn">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Ad *</label>
                        <input type="text" name="first_name" class="form-control" required placeholder="Müşteri adı">
                    </div>
                    <div class="form-group">
                        <label>Soyad *</label>
                        <input type="text" name="last_name" class="form-control" required placeholder="Müşteri soyadı">
                    </div>
                </div>
                <div class="form-group">
                    <label>E-posta *</label>
                    <input type="email" name="email" class="form-control" required placeholder="ornek@email.com">
                </div>
                <div class="form-group">
                    <label>Şifre *</label>
                    <input type="password" name="password" class="form-control" required minlength="8"
                        placeholder="En az 8 karakter">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Şirket Adı</label>
                        <input type="text" name="company_name" class="form-control"
                            placeholder="Şirket adı (opsiyonel)">
                    </div>
                    <div class="form-group">
                        <label>Telefon</label>
                        <input type="tel" name="phone" class="form-control" placeholder="05XX XXX XX XX">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Şehir</label>
                        <input type="text" name="city" class="form-control" placeholder="Şehir">
                    </div>
                    <div class="form-group">
                        <label>Ülke</label>
                        <select name="country" class="form-control">
                            <option value="TR">Türkiye</option>
                            <option value="US">ABD</option>
                            <option value="DE">Almanya</option>
                            <option value="GB">İngiltere</option>
                            <option value="NL">Hollanda</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Adres</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Açık adres"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('addModal')" class="btn btn-outline">İptal</button>
                <button type="submit" class="btn btn-primary">💾 Kaydet</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>