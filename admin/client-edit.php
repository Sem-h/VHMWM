<?php
/**
 * WHMVM - Admin Müşteri Düzenleme
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Affiliate.php';

// NetGSM modülü
$netgsmPath = dirname(__DIR__) . '/modules/netgsm/NetGSMGateway.php';
$netgsmAvailable = file_exists($netgsmPath);
if ($netgsmAvailable) {
    require_once $netgsmPath;
}

require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Müşteri Düzenle';
$currentPage = 'clients';
$db = Database::getInstance();

$clientId = (int) ($_GET['id'] ?? 0);
$message = '';
$messageType = '';
$smsMessage = '';
$smsMessageType = '';

// Müşteriyi çek
$stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$clientId]);
$client = $stmt->fetch();

if (!$client) {
    header('Location: clients.php');
    exit;
}

// SMS Gönderme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_sms'])) {
    $smsText = trim($_POST['sms_text'] ?? '');
    $phone = $client['phone'] ?? '';

    if (empty($smsText)) {
        $smsMessage = 'SMS metni boş olamaz.';
        $smsMessageType = 'danger';
    } elseif (empty($phone)) {
        $smsMessage = 'Müşterinin telefon numarası bulunamadı.';
        $smsMessageType = 'danger';
    } elseif (!$netgsmAvailable) {
        $smsMessage = 'NetGSM modülü bulunamadı. Lütfen önce modülü yükleyin.';
        $smsMessageType = 'danger';
    } else {
        // NetGSM ayarlarını çek
        $netgsmSettings = Database::fetch("SELECT config FROM modules WHERE slug = 'netgsm'");
        $netgsmConfig = $netgsmSettings ? json_decode($netgsmSettings['config'] ?? '{}', true) : [];

        if (empty($netgsmConfig['usercode'])) {
            $smsMessage = 'NetGSM ayarları yapılandırılmamış. Modül ayarlarından API bilgilerini girin.';
            $smsMessageType = 'danger';
        } else {
            $gateway = new NetGSMGateway($netgsmConfig);
            $result = $gateway->sendSMS($phone, $smsText);

            if ($result['success']) {
                $testNote = ($result['test_mode'] ?? false) ? ' (Test Modu - gerçek SMS gönderilmedi)' : '';
                $smsMessage = '✅ SMS başarıyla gönderildi!' . $testNote;
                $smsMessageType = 'success';
            } else {
                $smsMessage = '❌ SMS gönderilemedi: ' . ($result['message'] ?? 'Bilinmeyen hata');
                $smsMessageType = 'danger';
            }
        }
    }
    // SMS gönderme sonrası SMS sekmesinde kal
    $_GET['tab'] = 'sms';
}

// Güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['first_name'])) {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    $taxId = trim($_POST['tax_id'] ?? '');
    $taxOffice = trim($_POST['tax_office'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $postcode = trim($_POST['postcode'] ?? '');
    $country = $_POST['country'] ?? 'TR';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $creditBalance = (float) ($_POST['credit_balance'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';

    // E-posta benzersizliğini kontrol et
    $checkEmail = $db->prepare("SELECT id FROM clients WHERE email = ? AND id != ?");
    $checkEmail->execute([$email, $clientId]);

    if ($checkEmail->fetch()) {
        $message = 'Bu e-posta adresi başka bir müşteri tarafından kullanılıyor.';
        $messageType = 'danger';
    } else {
        // Güncelleme sorgusu
        $sql = "UPDATE clients SET 
                first_name = ?, last_name = ?, email = ?, phone = ?,
                company_name = ?, tax_id = ?, tax_office = ?, address = ?, city = ?,
                state = ?, postcode = ?, country = ?, is_active = ?,
                credit_balance = ?, notes = ?";
        $params = [
            $firstName,
            $lastName,
            $email,
            $phone,
            $companyName,
            $taxId,
            $taxOffice,
            $address,
            $city,
            $state,
            $postcode,
            $country,
            $isActive,
            $creditBalance,
            $notes
        ];

        // Şifre değiştirilecekse
        if (!empty($newPassword)) {
            $sql .= ", password = ?";
            $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = ?";
        $params[] = $clientId;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $message = 'Müşteri bilgileri güncellendi.';
        $messageType = 'success';

        // Güncel verileri çek
        $stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch();
    }
}

// Müşterinin hizmetlerini çek (tümü)
$services = $db->prepare("
    SELECT s.*, p.name as product_name, p.type as product_type, 
           sv.name as server_name, sv.hostname as server_hostname
    FROM services s 
    LEFT JOIN products p ON s.product_id = p.id 
    LEFT JOIN servers sv ON s.server_id = sv.id
    WHERE s.client_id = ? 
    ORDER BY s.status = 'active' DESC, s.created_at DESC
");
$services->execute([$clientId]);
$services = $services->fetchAll();

// Müşterinin faturalarını çek (tümü)
$invoices = $db->prepare("
    SELECT * FROM invoices WHERE client_id = ? ORDER BY created_at DESC
");
$invoices->execute([$clientId]);
$invoices = $invoices->fetchAll();

// Müşterinin ticketlarını çek (tümü)
$tickets = $db->prepare("
    SELECT t.*, d.name as department_name
    FROM tickets t
    LEFT JOIN departments d ON t.department_id = d.id
    WHERE t.client_id = ? ORDER BY t.created_at DESC
");
$tickets->execute([$clientId]);
$tickets = $tickets->fetchAll();

// Müşterinin domainlerini çek
$domains = $db->prepare("
    SELECT * FROM domains WHERE client_id = ? ORDER BY expiry_date ASC
");
$domains->execute([$clientId]);
$domains = $domains->fetchAll();

// Müşterinin siparişlerini çek
$orders = $db->prepare("
    SELECT o.*, COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.client_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 20
");
$orders->execute([$clientId]);
$orders = $orders->fetchAll();

// Affiliate bilgilerini çek
$affiliate = Affiliate::getByClientId($clientId);
$affiliateStats = [];
$affiliateReferrals = [];
$affiliateCommissions = [];
$affiliateWithdrawals = [];

if ($affiliate) {
    $affiliateStats = Affiliate::getStats($affiliate['id']);

    // Kazandırdıkları - Referral'ları çek
    $affiliateReferrals = $db->prepare("
        SELECT ar.*, c.first_name, c.last_name, c.email, c.created_at as client_created,
               (SELECT COUNT(*) FROM affiliate_commissions ac WHERE ac.referral_id = ar.id) as commission_count,
               (SELECT COALESCE(SUM(ac.commission_amount), 0) FROM affiliate_commissions ac WHERE ac.referral_id = ar.id AND ac.status IN ('approved', 'paid')) as total_earned,
               (SELECT COUNT(*) FROM invoices i WHERE i.client_id = c.id AND i.status = 'paid') as paid_invoices_count
        FROM affiliate_referrals ar
        JOIN clients c ON ar.referred_client_id = c.id
        WHERE ar.affiliate_id = ?
        ORDER BY ar.created_at DESC
    ");
    $affiliateReferrals->execute([$affiliate['id']]);
    $affiliateReferrals = $affiliateReferrals->fetchAll();

    // Komisyonları çek
    $affiliateCommissions = $db->prepare("
        SELECT ac.*, ar.referred_client_id, c.first_name, c.last_name, c.email,
               i.invoice_number, i.total as invoice_total
        FROM affiliate_commissions ac
        LEFT JOIN affiliate_referrals ar ON ac.referral_id = ar.id
        LEFT JOIN clients c ON ar.referred_client_id = c.id
        LEFT JOIN invoices i ON ac.invoice_id = i.id
        WHERE ac.affiliate_id = ?
        ORDER BY ac.created_at DESC
    ");
    $affiliateCommissions->execute([$affiliate['id']]);
    $affiliateCommissions = $affiliateCommissions->fetchAll();

    // Çekim taleplerini çek
    $affiliateWithdrawals = $db->prepare("
        SELECT * FROM affiliate_withdrawals 
        WHERE affiliate_id = ? 
        ORDER BY created_at DESC
    ");
    $affiliateWithdrawals->execute([$affiliate['id']]);
    $affiliateWithdrawals = $affiliateWithdrawals->fetchAll();
}

// İstatistikler
$stats = [
    'total_services' => count($services),
    'active_services' => count(array_filter($services, fn($s) => $s['status'] === 'active')),
    'total_invoices' => count($invoices),
    'unpaid_invoices' => count(array_filter($invoices, fn($i) => $i['status'] === 'unpaid')),
    'total_tickets' => count($tickets),
    'open_tickets' => count(array_filter($tickets, fn($t) => in_array($t['status'], ['open', 'customer_reply']))),
    'total_domains' => count($domains),
    'total_orders' => count($orders),
    'total_spent' => array_sum(array_column(array_filter($invoices, fn($i) => $i['status'] === 'paid'), 'total'))
];

// Aktif tab
$activeTab = $_GET['tab'] ?? 'overview';

include 'includes/header.php';
?>

<style>
    .client-layout {
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    .client-main {
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    .client-sidebar {
        display: none;
        /* Tab yapısına geçiyoruz */
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--gray);
        text-decoration: none;
        font-size: 14px;
        margin-bottom: 15px;
        transition: color 0.2s;
    }

    .back-link:hover {
        color: var(--primary);
    }

    .client-header {
        background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
        border-radius: 16px;
        padding: 30px;
        color: white;
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .client-avatar {
        width: 80px;
        height: 80px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        font-weight: 700;
    }

    .client-info h1 {
        font-size: 24px;
        font-weight: 700;
        margin: 0 0 5px;
    }

    .client-info p {
        opacity: 0.8;
        margin: 0;
        font-size: 15px;
    }

    .client-badges {
        display: flex;
        gap: 10px;
        margin-top: 10px;
    }

    .client-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.2);
    }

    .form-card {
        background: var(--y-yuzey);
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .form-card-header {
        padding: 20px 25px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-card-header h3 {
        font-size: 16px;
        font-weight: 600;
        margin: 0;
    }

    .form-card-body {
        padding: 25px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .form-row-3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 20px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 8px;
    }

    .form-group label small {
        color: var(--gray);
        font-weight: 400;
    }

    .form-control {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid var(--border);
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.2s;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    textarea.form-control {
        resize: vertical;
        min-height: 80px;
    }

    .form-check {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-check input[type="checkbox"] {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }

    .btn-save {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        padding: 14px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-save:hover {
        background: var(--primary-dark);
    }

    /* Sidebar Cards */
    .info-card {
        background: var(--y-yuzey);
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .info-card-header {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border);
        font-size: 14px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .info-card-body {
        padding: 15px 20px;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid var(--y-cizgi-soft);
        font-size: 13px;
    }

    .info-item:last-child {
        border-bottom: none;
    }

    .info-label {
        color: var(--gray);
    }

    .info-value {
        font-weight: 600;
    }

    .stat-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .stat-item {
        text-align: center;
        padding: 15px;
        background: var(--y-yuzey-2);
        border-radius: 10px;
    }

    .stat-value {
        font-size: 24px;
        font-weight: 700;
        color: var(--primary);
    }

    .stat-label {
        font-size: 12px;
        color: var(--gray);
        margin-top: 5px;
    }

    /* Alert */
    .alert {
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .alert-success {
        background: #d1fae5;
        color: #065f46;
    }

    .alert-danger {
        background: #fee2e2;
        color: #991b1b;
    }

    /* Mini table */
    .mini-table {
        width: 100%;
        font-size: 13px;
    }

    .mini-table th {
        text-align: left;
        padding: 8px 0;
        font-weight: 600;
        color: var(--gray);
        font-size: 11px;
        text-transform: uppercase;
        border-bottom: 1px solid var(--border);
    }

    .mini-table td {
        padding: 10px 0;
        border-bottom: 1px solid var(--y-cizgi-soft);
    }

    .mini-table tr:last-child td {
        border-bottom: none;
    }

    .badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
    }

    .badge-success {
        background: #d1fae5;
        color: #065f46;
    }

    .badge-warning {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-danger {
        background: #fee2e2;
        color: #991b1b;
    }

    .badge-info {
        background: #dbeafe;
        color: #1e40af;
    }

    .badge-gray {
        background: var(--y-yuzey-2);
        color: var(--y-metin-2);
    }

    /* Tab Navigation */
    .tabs-nav {
        display: flex;
        gap: 5px;
        border-bottom: 2px solid var(--border);
        background: var(--y-yuzey);
        padding: 0 25px;
        margin-top: 20px;
        overflow-x: auto;
    }

    .tab-item {
        padding: 15px 20px;
        font-size: 14px;
        font-weight: 600;
        color: var(--gray);
        cursor: pointer;
        border-bottom: 3px solid transparent;
        transition: all 0.2s;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .tab-item:hover {
        color: var(--primary);
        background: var(--y-yuzey-2);
    }

    .tab-item.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
        background: var(--y-yuzey-2);
    }

    .tab-content {
        display: none;
        padding: 25px;
        background: var(--y-yuzey);
    }

    .tab-content.active {
        display: block;
    }

    /* Services Table */
    .services-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .services-table thead {
        background: var(--y-yuzey-2);
    }

    .services-table th {
        padding: 12px 15px;
        text-align: left;
        font-size: 12px;
        font-weight: 600;
        color: var(--gray);
        text-transform: uppercase;
        border-bottom: 2px solid var(--border);
    }

    .services-table td {
        padding: 15px;
        border-bottom: 1px solid var(--y-cizgi-soft);
        font-size: 14px;
    }

    .services-table tbody tr:hover {
        background: var(--y-yuzey-2);
    }

    .service-actions {
        display: flex;
        gap: 8px;
    }

    .service-actions .btn {
        padding: 6px 12px;
        font-size: 12px;
        border-radius: 6px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    /* Invoices Table */
    .invoices-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .invoices-table thead {
        background: var(--y-yuzey-2);
    }

    .invoices-table th {
        padding: 12px 15px;
        text-align: left;
        font-size: 12px;
        font-weight: 600;
        color: var(--gray);
        text-transform: uppercase;
        border-bottom: 2px solid var(--border);
    }

    .invoices-table td {
        padding: 15px;
        border-bottom: 1px solid var(--y-cizgi-soft);
        font-size: 14px;
    }

    .invoices-table tbody tr:hover {
        background: var(--y-yuzey-2);
    }

    /* Tickets Table */
    .tickets-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .tickets-table thead {
        background: var(--y-yuzey-2);
    }

    .tickets-table th {
        padding: 12px 15px;
        text-align: left;
        font-size: 12px;
        font-weight: 600;
        color: var(--gray);
        text-transform: uppercase;
        border-bottom: 2px solid var(--border);
    }

    .tickets-table td {
        padding: 15px;
        border-bottom: 1px solid var(--y-cizgi-soft);
        font-size: 14px;
    }

    .tickets-table tbody tr:hover {
        background: var(--y-yuzey-2);
    }

    /* Overview Stats Grid */
    .overview-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .overview-stat-card {
        background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
        border-radius: 12px;
        padding: 20px;
        color: white;
    }

    .overview-stat-card.orange {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }

    .overview-stat-card.green {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .overview-stat-card.red {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }

    .overview-stat-value {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .overview-stat-label {
        font-size: 13px;
        opacity: 0.9;
    }

    /* Responsive */
    @media (max-width: 1200px) {
        .client-layout {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {

        .form-row,
        .form-row-3 {
            grid-template-columns: 1fr;
        }

        .client-header {
            flex-direction: column;
            text-align: center;
        }

        .tabs-nav {
            padding: 0 15px;
            overflow-x: auto;
        }

        .tab-item {
            padding: 12px 15px;
            font-size: 13px;
        }

        .tab-content {
            padding: 15px;
        }

        .services-table,
        .invoices-table,
        .tickets-table {
            font-size: 12px;
        }

        .services-table th,
        .services-table td,
        .invoices-table th,
        .invoices-table td,
        .tickets-table th,
        .tickets-table td {
            padding: 10px 8px;
        }
    }
</style>

<a href="clients.php" class="back-link">← Müşteri Listesine Dön</a>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <?= $messageType === 'success' ? '✅' : '⚠️' ?>     <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="client-layout">
    <!-- Main Content -->
    <div class="client-main">
        <!-- Header -->
        <div class="client-header">
            <div class="client-avatar">
                <?= strtoupper(substr($client['first_name'], 0, 1) . substr($client['last_name'], 0, 1)) ?>
            </div>
            <div class="client-info">
                <h1><?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?></h1>
                <p><?= htmlspecialchars($client['email']) ?></p>
                <div class="client-badges">
                    <span class="client-badge">#<?= $client['id'] ?></span>
                    <?php if ($client['is_active']): ?>
                        <span class="client-badge">✅ Aktif</span>
                    <?php else: ?>
                        <span class="client-badge">❌ Pasif</span>
                    <?php endif; ?>
                    <?php if ($client['email_verified']): ?>
                        <span class="client-badge">📧 Doğrulanmış</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tabs-nav">
            <div class="tab-item <?= $activeTab === 'overview' ? 'active' : '' ?>" onclick="switchTab('overview')">
                📊 Genel Bakış
            </div>
            <div class="tab-item <?= $activeTab === 'profile' ? 'active' : '' ?>" onclick="switchTab('profile')">
                👤 Profil
            </div>
            <div class="tab-item <?= $activeTab === 'products' ? 'active' : '' ?>" onclick="switchTab('products')">
                📦 Ürünler/Hizmetler <span class="badge badge-info"><?= $stats['total_services'] ?></span>
            </div>
            <div class="tab-item <?= $activeTab === 'invoices' ? 'active' : '' ?>" onclick="switchTab('invoices')">
                📄 Faturalar <span class="badge badge-info"><?= $stats['total_invoices'] ?></span>
            </div>
            <div class="tab-item <?= $activeTab === 'tickets' ? 'active' : '' ?>" onclick="switchTab('tickets')">
                🎫 Destek Talepleri <span class="badge badge-info"><?= $stats['total_tickets'] ?></span>
            </div>
            <?php if ($netgsmAvailable): ?>
                <div class="tab-item <?= $activeTab === 'sms' ? 'active' : '' ?>" onclick="switchTab('sms')">
                    📱 SMS Gönder
                </div>
            <?php endif; ?>
            <?php if (count($domains) > 0): ?>
                <div class="tab-item <?= $activeTab === 'domains' ? 'active' : '' ?>" onclick="switchTab('domains')">
                    🌐 Domainler <span class="badge badge-info"><?= $stats['total_domains'] ?></span>
                </div>
            <?php endif; ?>
            <?php if ($affiliate): ?>
                <div class="tab-item <?= $activeTab === 'affiliate' ? 'active' : '' ?>" onclick="switchTab('affiliate')">
                    🤝 Satış Ortaklığı <span class="badge badge-info"><?= $affiliateStats['total_signups'] ?? 0 ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Overview Tab -->
        <div id="tab-overview" class="tab-content <?= $activeTab === 'overview' ? 'active' : '' ?>">
            <div class="overview-stats">
                <div class="overview-stat-card">
                    <div class="overview-stat-value"><?= $stats['total_services'] ?></div>
                    <div class="overview-stat-label">Toplam Hizmet</div>
                </div>
                <div class="overview-stat-card green">
                    <div class="overview-stat-value"><?= $stats['active_services'] ?></div>
                    <div class="overview-stat-label">Aktif Hizmet</div>
                </div>
                <div class="overview-stat-card orange">
                    <div class="overview-stat-value"><?= $stats['unpaid_invoices'] ?></div>
                    <div class="overview-stat-label">Ödenmemiş Fatura</div>
                </div>
                <div class="overview-stat-card red">
                    <div class="overview-stat-value"><?= $stats['open_tickets'] ?></div>
                    <div class="overview-stat-label">Açık Ticket</div>
                </div>
                <div class="overview-stat-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <div class="overview-stat-value"><?= number_format($stats['total_spent'], 2) ?> ₺</div>
                    <div class="overview-stat-label">Toplam Kazanç</div>
                </div>
                <?php if ($affiliate): ?>
                    <div class="overview-stat-card" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                        <div class="overview-stat-value"><?= number_format($affiliateStats['total_earnings'] ?? 0, 2) ?> ₺
                        </div>
                        <div class="overview-stat-label">Affiliate Kazancı</div>
                    </div>
                    <div class="overview-stat-card" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
                        <div class="overview-stat-value"><?= $affiliateStats['total_signups'] ?? 0 ?></div>
                        <div class="overview-stat-label">Kazandırdıkları</div>
                    </div>
                <?php endif; ?>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px;">
                <!-- Hesap Bilgileri -->
                <div class="form-card">
                    <div class="form-card-header">
                        <span>📋</span>
                        <h3>Hesap Bilgileri</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="info-item">
                            <span class="info-label">Kayıt Tarihi</span>
                            <span class="info-value"><?= date('d.m.Y H:i', strtotime($client['created_at'])) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Son Giriş</span>
                            <span
                                class="info-value"><?= $client['last_login'] ? date('d.m.Y H:i', strtotime($client['last_login'])) : 'Hiç' ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Bakiye</span>
                            <span class="info-value"><?= number_format((float) $client['credit_balance'], 2) ?> ₺</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Toplam Kazanç</span>
                            <span class="info-value"
                                style="color: #10b981; font-weight: 700;"><?= number_format($stats['total_spent'], 2) ?>
                                ₺</span>
                        </div>
                    </div>
                </div>

                <!-- Kurumsal Bilgiler -->
                <?php if (!empty($client['company_name']) || !empty($client['tax_id'])): ?>
                    <div class="form-card">
                        <div class="form-card-header">
                            <span>🏢</span>
                            <h3>Kurumsal Bilgiler</h3>
                        </div>
                        <div class="form-card-body">
                            <?php if (!empty($client['company_name'])): ?>
                                <div class="info-item">
                                    <span class="info-label">Şirket Adı</span>
                                    <span class="info-value"><?= htmlspecialchars($client['company_name']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($client['tax_id'])): ?>
                                <div class="info-item">
                                    <span class="info-label">Vergi No</span>
                                    <span class="info-value"><?= htmlspecialchars($client['tax_id']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($client['tax_office'])): ?>
                                <div class="info-item">
                                    <span class="info-label">Vergi Dairesi</span>
                                    <span class="info-value"><?= htmlspecialchars($client['tax_office']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Satış Ortaklığı Bilgileri -->
            <?php if ($affiliate): ?>
                <div class="form-card" style="margin-top: 30px;">
                    <div class="form-card-header">
                        <span>🤝</span>
                        <h3>Satış Ortaklığı (Affiliate)</h3>
                        <span style="margin-left: auto;">
                            <span
                                class="badge badge-<?= $affiliate['status'] === 'active' ? 'success' : ($affiliate['status'] === 'pending' ? 'warning' : 'gray') ?>">
                                <?= match ($affiliate['status']) {
                                    'active' => 'Aktif',
                                    'pending' => 'Beklemede',
                                    'suspended' => 'Askıda',
                                    default => ucfirst($affiliate['status'])
                                } ?>
                            </span>
                        </span>
                    </div>
                    <div class="form-card-body">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 25px;">
                            <div>
                                <div style="font-size: 12px; color: var(--gray); margin-bottom: 5px;">Affiliate Kodu</div>
                                <div
                                    style="font-size: 18px; font-weight: 700; color: var(--primary); font-family: monospace;">
                                    <?= htmlspecialchars($affiliate['affiliate_code']) ?>
                                </div>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: var(--gray); margin-bottom: 5px;">Toplam Kazanç</div>
                                <div style="font-size: 18px; font-weight: 700; color: #10b981;">
                                    <?= number_format($affiliateStats['total_earnings'] ?? 0, 2) ?> ₺
                                </div>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: var(--gray); margin-bottom: 5px;">Mevcut Bakiye</div>
                                <div style="font-size: 18px; font-weight: 700; color: #8b5cf6;">
                                    <?= number_format($affiliateStats['balance'] ?? 0, 2) ?> ₺
                                </div>
                            </div>
                        </div>

                        <div
                            style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px; padding: 15px; background: #f8fafc; border-radius: 10px;">
                            <div style="text-align: center;">
                                <div style="font-size: 24px; font-weight: 700; color: var(--primary);">
                                    <?= $affiliateStats['total_signups'] ?? 0 ?>
                                </div>
                                <div style="font-size: 12px; color: var(--gray);">Kayıt Olan</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 24px; font-weight: 700; color: #f59e0b;">
                                    <?= $affiliateStats['total_orders'] ?? 0 ?>
                                </div>
                                <div style="font-size: 12px; color: var(--gray);">Sipariş</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 24px; font-weight: 700; color: #10b981;">
                                    <?= $affiliateStats['total_visits'] ?? 0 ?>
                                </div>
                                <div style="font-size: 12px; color: var(--gray);">Ziyaret</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 24px; font-weight: 700; color: #ef4444;">
                                    <?= number_format($affiliateStats['total_withdrawn'] ?? 0, 2) ?> ₺
                                </div>
                                <div style="font-size: 12px; color: var(--gray);">Çekilen</div>
                            </div>
                        </div>

                        <?php if (!empty($affiliateReferrals)): ?>
                            <div style="margin-top: 25px;">
                                <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 15px;">📊 Kazandırdıkları
                                    (Referral'lar)</h4>
                                <div style="overflow-x: auto;">
                                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                        <thead>
                                            <tr style="background: #f8fafc; border-bottom: 2px solid var(--border);">
                                                <th
                                                    style="padding: 10px; text-align: left; font-weight: 600; color: var(--gray); text-transform: uppercase; font-size: 11px;">
                                                    Müşteri</th>
                                                <th
                                                    style="padding: 10px; text-align: left; font-weight: 600; color: var(--gray); text-transform: uppercase; font-size: 11px;">
                                                    Durum</th>
                                                <th
                                                    style="padding: 10px; text-align: left; font-weight: 600; color: var(--gray); text-transform: uppercase; font-size: 11px;">
                                                    Kayıt Tarihi</th>
                                                <th
                                                    style="padding: 10px; text-align: right; font-weight: 600; color: var(--gray); text-transform: uppercase; font-size: 11px;">
                                                    Kazanç</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($affiliateReferrals as $referral): ?>
                                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                                    <td style="padding: 12px 10px;">
                                                        <strong><?= htmlspecialchars($referral['first_name'] . ' ' . $referral['last_name']) ?></strong>
                                                        <br>
                                                        <small
                                                            style="color: var(--gray);"><?= htmlspecialchars($referral['email']) ?></small>
                                                    </td>
                                                    <td style="padding: 12px 10px;">
                                                        <?php
                                                        $statusBadge = match ($referral['status']) {
                                                            'approved' => 'success',
                                                            'pending' => 'warning',
                                                            'rejected' => 'danger',
                                                            default => 'gray'
                                                        };
                                                        $statusText = match ($referral['status']) {
                                                            'approved' => 'Onaylandı',
                                                            'pending' => 'Beklemede',
                                                            'rejected' => 'Reddedildi',
                                                            default => ucfirst($referral['status'])
                                                        };
                                                        ?>
                                                        <span class="badge badge-<?= $statusBadge ?>"><?= $statusText ?></span>
                                                    </td>
                                                    <td style="padding: 12px 10px;">
                                                        <?= date('d.m.Y', strtotime($referral['client_created'])) ?>
                                                    </td>
                                                    <td style="padding: 12px 10px; text-align: right;">
                                                        <strong
                                                            style="color: #10b981;"><?= number_format((float) $referral['total_earned'], 2) ?>
                                                            ₺</strong>
                                                        <br>
                                                        <small style="color: var(--gray);"><?= $referral['commission_count'] ?>
                                                            komisyon</small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div style="margin-top: 15px; text-align: right;">
                                    <a href="affiliates.php?filter_client=<?= $clientId ?>" class="btn btn-outline btn-sm"
                                        style="text-decoration: none;">
                                        📊 Tüm Detayları Görüntüle
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 30px; color: var(--gray);">
                                <div style="font-size: 32px; margin-bottom: 10px;">👥</div>
                                <p>Henüz kayıt olan müşteri bulunmuyor.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Profile Tab -->
        <div id="tab-profile" class="tab-content <?= $activeTab === 'profile' ? 'active' : '' ?>">
            <form method="POST">
                <!-- Kişisel Bilgiler -->
                <div class="form-card">
                    <div class="form-card-header">
                        <span>👤</span>
                        <h3>Kişisel Bilgiler</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Ad *</label>
                                <input type="text" name="first_name" class="form-control" required
                                    value="<?= htmlspecialchars($client['first_name']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Soyad *</label>
                                <input type="text" name="last_name" class="form-control" required
                                    value="<?= htmlspecialchars($client['last_name']) ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>E-posta *</label>
                                <input type="email" name="email" class="form-control" required
                                    value="<?= htmlspecialchars($client['email']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Telefon</label>
                                <input type="tel" name="phone" class="form-control"
                                    value="<?= htmlspecialchars($client['phone'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kurumsal Bilgiler -->
                <div class="form-card" style="margin-top: 20px;">
                    <div class="form-card-header">
                        <span>🏢</span>
                        <h3>Kurumsal Bilgiler</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Şirket Adı</label>
                                <input type="text" name="company_name" class="form-control"
                                    value="<?= htmlspecialchars($client['company_name'] ?? '') ?>"
                                    placeholder="Şirket adını girin">
                            </div>
                            <div class="form-group">
                                <label>Vergi Numarası</label>
                                <input type="text" name="tax_id" class="form-control"
                                    value="<?= htmlspecialchars($client['tax_id'] ?? '') ?>"
                                    placeholder="10 haneli vergi numarası">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Vergi Dairesi</label>
                            <input type="text" name="tax_office" class="form-control"
                                value="<?= htmlspecialchars($client['tax_office'] ?? '') ?>"
                                placeholder="Vergi dairesi adı">
                        </div>
                    </div>
                </div>

                <!-- Adres Bilgileri -->
                <div class="form-card" style="margin-top: 20px;">
                    <div class="form-card-header">
                        <span>📍</span>
                        <h3>Adres Bilgileri</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="form-group">
                            <label>Adres</label>
                            <textarea name="address" class="form-control"
                                rows="2"><?= htmlspecialchars($client['address'] ?? '') ?></textarea>
                        </div>
                        <div class="form-row-3">
                            <div class="form-group">
                                <label>Şehir</label>
                                <input type="text" name="city" class="form-control"
                                    value="<?= htmlspecialchars($client['city'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>İl/Eyalet</label>
                                <input type="text" name="state" class="form-control"
                                    value="<?= htmlspecialchars($client['state'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Posta Kodu</label>
                                <input type="text" name="postcode" class="form-control"
                                    value="<?= htmlspecialchars($client['postcode'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Ülke</label>
                            <select name="country" class="form-control">
                                <option value="TR" <?= ($client['country'] ?? '') === 'TR' ? 'selected' : '' ?>>Türkiye
                                </option>
                                <option value="US" <?= ($client['country'] ?? '') === 'US' ? 'selected' : '' ?>>ABD
                                </option>
                                <option value="DE" <?= ($client['country'] ?? '') === 'DE' ? 'selected' : '' ?>>Almanya
                                </option>
                                <option value="GB" <?= ($client['country'] ?? '') === 'GB' ? 'selected' : '' ?>>İngiltere
                                </option>
                                <option value="NL" <?= ($client['country'] ?? '') === 'NL' ? 'selected' : '' ?>>Hollanda
                                </option>
                                <option value="FR" <?= ($client['country'] ?? '') === 'FR' ? 'selected' : '' ?>>Fransa
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Hesap Ayarları -->
                <div class="form-card" style="margin-top: 20px;">
                    <div class="form-card-header">
                        <span>⚙️</span>
                        <h3>Hesap Ayarları</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Yeni Şifre <small>(Boş bırakılırsa değişmez)</small></label>
                                <input type="password" name="new_password" class="form-control" minlength="8">
                                <div style="margin-top: 10px;">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="togglePasswordHash()"
                                        style="font-size: 12px;">
                                        🔑 Mevcut Şifre Hash'ini Göster
                                    </button>
                                    <div id="passwordHashContainer"
                                        style="display: none; margin-top: 10px; padding: 12px; background: #1e293b; border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <code id="passwordHash"
                                                style="color: #10b981; font-size: 11px; word-break: break-all; flex: 1;"><?= htmlspecialchars($client['password'] ?? 'Şifre bulunamadı') ?></code>
                                            <button type="button" onclick="copyPasswordHash()"
                                                style="background: #374151; border: none; color: white; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 11px;">
                                                📋 Kopyala
                                            </button>
                                        </div>
                                        <div style="margin-top: 8px; font-size: 11px; color: #94a3b8;">
                                            ⚠️ Bu bcrypt hash'idir, şifrenin kendisi değildir.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Bakiye (₺)</label>
                                <input type="number" name="credit_balance" class="form-control" step="0.01"
                                    value="<?= number_format((float) $client['credit_balance'], 2, '.', '') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-check">
                                <input type="checkbox" name="is_active" <?= $client['is_active'] ? 'checked' : '' ?>>
                                <span>Hesap Aktif</span>
                            </label>
                        </div>
                        <div class="form-group">
                            <label>Notlar <small>(Sadece yöneticiler görür)</small></label>
                            <textarea name="notes" class="form-control"
                                rows="3"><?= htmlspecialchars($client['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-save" style="margin-top: 20px;">
                    💾 Değişiklikleri Kaydet
                </button>
            </form>
        </div>

        <!-- Products/Services Tab -->
        <div id="tab-products" class="tab-content <?= $activeTab === 'products' ? 'active' : '' ?>">
            <?php if (empty($services)): ?>
                <div style="text-align: center; padding: 60px 20px; color: var(--gray);">
                    <div style="font-size: 48px; margin-bottom: 20px;">📦</div>
                    <h3>Henüz hizmet bulunmuyor</h3>
                    <p>Bu müşterinin henüz aktif bir hizmeti yok.</p>
                </div>
            <?php else: ?>
                <table class="services-table">
                    <thead>
                        <tr>
                            <th>Ürün/Hizmet</th>
                            <th>Domain</th>
                            <th>Durum</th>
                            <th>Yenileme Tarihi</th>
                            <th>Tutar</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($service['product_name'] ?? 'Ürün') ?></strong>
                                    <br>
                                    <small
                                        style="color: var(--gray);"><?= ucfirst($service['product_type'] ?? 'hosting') ?></small>
                                </td>
                                <td><?= htmlspecialchars($service['domain'] ?? '-') ?></td>
                                <td>
                                    <?php
                                    $statusBadge = match ($service['status']) {
                                        'active' => 'success',
                                        'pending' => 'warning',
                                        'suspended' => 'danger',
                                        'terminated', 'cancelled' => 'gray',
                                        default => 'gray'
                                    };
                                    $statusText = match ($service['status']) {
                                        'active' => 'Aktif',
                                        'pending' => 'Beklemede',
                                        'suspended' => 'Askıda',
                                        'terminated', 'cancelled' => 'İptal',
                                        default => ucfirst($service['status'])
                                    };
                                    ?>
                                    <span class="badge badge-<?= $statusBadge ?>"><?= $statusText ?></span>
                                </td>
                                <td>
                                    <?= $service['next_due_date'] ? date('d.m.Y', strtotime($service['next_due_date'])) : '-' ?>
                                </td>
                                <td>
                                    <?= $service['amount'] ? number_format((float) $service['amount'], 2) . ' ₺' : '-' ?>
                                </td>
                                <td>
                                    <div class="service-actions">
                                        <a href="service-view.php?id=<?= $service['id'] ?>" class="btn btn-outline btn-sm">👁️
                                            Görüntüle</a>
                                        <a href="service-edit.php?id=<?= $service['id'] ?>" class="btn btn-outline btn-sm">✏️
                                            Düzenle</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Invoices Tab -->
        <div id="tab-invoices" class="tab-content <?= $activeTab === 'invoices' ? 'active' : '' ?>">
            <?php if (empty($invoices)): ?>
                <div style="text-align: center; padding: 60px 20px; color: var(--gray);">
                    <div style="font-size: 48px; margin-bottom: 20px;">📄</div>
                    <h3>Henüz fatura bulunmuyor</h3>
                    <p>Bu müşterinin henüz faturası yok.</p>
                </div>
            <?php else: ?>
                <table class="invoices-table">
                    <thead>
                        <tr>
                            <th>Fatura No</th>
                            <th>Tarih</th>
                            <th>Vade Tarihi</th>
                            <th>Tutar</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong></td>
                                <td><?= date('d.m.Y', strtotime($invoice['created_at'])) ?></td>
                                <td><?= $invoice['due_date'] ? date('d.m.Y', strtotime($invoice['due_date'])) : '-' ?></td>
                                <td><strong><?= number_format((float) $invoice['total'], 2) ?> ₺</strong></td>
                                <td>
                                    <?php
                                    $statusBadge = match ($invoice['status']) {
                                        'paid' => 'success',
                                        'unpaid' => 'danger',
                                        'cancelled' => 'gray',
                                        default => 'warning'
                                    };
                                    $statusText = match ($invoice['status']) {
                                        'paid' => 'Ödendi',
                                        'unpaid' => 'Ödenmedi',
                                        'cancelled' => 'İptal',
                                        default => ucfirst($invoice['status'])
                                    };
                                    ?>
                                    <span class="badge badge-<?= $statusBadge ?>"><?= $statusText ?></span>
                                </td>
                                <td>
                                    <a href="invoice-view.php?id=<?= $invoice['id'] ?>" class="btn btn-outline btn-sm">👁️
                                        Görüntüle</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Tickets Tab -->
        <div id="tab-tickets" class="tab-content <?= $activeTab === 'tickets' ? 'active' : '' ?>">
            <?php if (empty($tickets)): ?>
                <div style="text-align: center; padding: 60px 20px; color: var(--gray);">
                    <div style="font-size: 48px; margin-bottom: 20px;">🎫</div>
                    <h3>Henüz destek talebi bulunmuyor</h3>
                    <p>Bu müşterinin henüz destek talebi yok.</p>
                </div>
            <?php else: ?>
                <table class="tickets-table">
                    <thead>
                        <tr>
                            <th>Konu</th>
                            <th>Departman</th>
                            <th>Öncelik</th>
                            <th>Durum</th>
                            <th>Tarih</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($ticket['subject']) ?></strong></td>
                                <td><?= htmlspecialchars($ticket['department_name'] ?? '-') ?></td>
                                <td>
                                    <?php
                                    $priorityBadge = match ($ticket['priority'] ?? 'medium') {
                                        'high' => 'danger',
                                        'medium' => 'warning',
                                        'low' => 'info',
                                        default => 'gray'
                                    };
                                    ?>
                                    <span
                                        class="badge badge-<?= $priorityBadge ?>"><?= ucfirst($ticket['priority'] ?? 'Orta') ?></span>
                                </td>
                                <td>
                                    <?php
                                    $statusBadge = match ($ticket['status']) {
                                        'open' => 'danger',
                                        'answered' => 'warning',
                                        'customer_reply' => 'info',
                                        'closed' => 'gray',
                                        default => 'gray'
                                    };
                                    $statusText = match ($ticket['status']) {
                                        'open' => 'Açık',
                                        'answered' => 'Cevaplandı',
                                        'customer_reply' => 'Müşteri Yanıtı',
                                        'closed' => 'Kapalı',
                                        default => ucfirst($ticket['status'])
                                    };
                                    ?>
                                    <span class="badge badge-<?= $statusBadge ?>"><?= $statusText ?></span>
                                </td>
                                <td><?= date('d.m.Y H:i', strtotime($ticket['created_at'])) ?></td>
                                <td>
                                    <a href="ticket-view.php?id=<?= $ticket['id'] ?>" class="btn btn-outline btn-sm">👁️
                                        Görüntüle</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Satış Ortaklığı Tab -->
        <?php if ($affiliate): ?>
            <div id="tab-affiliate" class="tab-content <?= $activeTab === 'affiliate' ? 'active' : '' ?>">
                <!-- Affiliate Özet İstatistikleri -->
                <div class="overview-stats" style="margin-bottom: 30px;">
                    <div class="overview-stat-card" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                        <div class="overview-stat-value"><?= number_format($affiliateStats['total_earnings'] ?? 0, 2) ?> ₺
                        </div>
                        <div class="overview-stat-label">Toplam Kazanç</div>
                    </div>
                    <div class="overview-stat-card" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
                        <div class="overview-stat-value"><?= $affiliateStats['total_signups'] ?? 0 ?></div>
                        <div class="overview-stat-label">Kayıt Olan</div>
                    </div>
                    <div class="overview-stat-card green">
                        <div class="overview-stat-value"><?= $affiliateStats['total_orders'] ?? 0 ?></div>
                        <div class="overview-stat-label">Sipariş</div>
                    </div>
                    <div class="overview-stat-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        <div class="overview-stat-value"><?= number_format($affiliateStats['balance'] ?? 0, 2) ?> ₺</div>
                        <div class="overview-stat-label">Mevcut Bakiye</div>
                    </div>
                    <div class="overview-stat-card orange">
                        <div class="overview-stat-value"><?= $affiliateStats['total_visits'] ?? 0 ?></div>
                        <div class="overview-stat-label">Toplam Ziyaret</div>
                    </div>
                    <div class="overview-stat-card" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                        <div class="overview-stat-value"><?= number_format($affiliateStats['total_withdrawn'] ?? 0, 2) ?> ₺
                        </div>
                        <div class="overview-stat-label">Çekilen Toplam</div>
                    </div>
                </div>

                <!-- Affiliate Bilgileri -->
                <div class="form-card" style="margin-bottom: 30px;">
                    <div class="form-card-header">
                        <span>🤝</span>
                        <h3>Affiliate Bilgileri</h3>
                        <span style="margin-left: auto;">
                            <span
                                class="badge badge-<?= $affiliate['status'] === 'active' ? 'success' : ($affiliate['status'] === 'pending' ? 'warning' : 'gray') ?>">
                                <?= match ($affiliate['status']) {
                                    'active' => 'Aktif',
                                    'pending' => 'Beklemede',
                                    'suspended' => 'Askıda',
                                    default => ucfirst($affiliate['status'])
                                } ?>
                            </span>
                        </span>
                    </div>
                    <div class="form-card-body">
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
                            <div>
                                <div style="font-size: 12px; color: var(--gray); margin-bottom: 5px;">Affiliate Kodu</div>
                                <div
                                    style="font-size: 18px; font-weight: 700; color: var(--primary); font-family: monospace;">
                                    <?= htmlspecialchars($affiliate['affiliate_code']) ?>
                                </div>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: var(--gray); margin-bottom: 5px;">Komisyon Oranı</div>
                                <div style="font-size: 18px; font-weight: 700; color: #10b981;">
                                    <?= number_format((float) $affiliate['commission_rate'], 2) ?>%
                                </div>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: var(--gray); margin-bottom: 5px;">Minimum Çekim</div>
                                <div style="font-size: 18px; font-weight: 700; color: #f59e0b;">
                                    <?= number_format((float) $affiliate['min_withdrawal'], 2) ?> ₺
                                </div>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: var(--gray); margin-bottom: 5px;">Kayıt Tarihi</div>
                                <div style="font-size: 14px; font-weight: 600; color: var(--dark);">
                                    <?= $affiliate['created_at'] ? date('d.m.Y H:i', strtotime($affiliate['created_at'])) : '-' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kazandırdıkları (Referral'lar) -->
                <div class="form-card" style="margin-bottom: 30px;">
                    <div class="form-card-header">
                        <span>👥</span>
                        <h3>Kazandırdıkları (Referral'lar)</h3>
                        <span style="margin-left: auto; color: var(--gray); font-size: 13px;">
                            Toplam: <?= count($affiliateReferrals) ?>
                        </span>
                    </div>
                    <div class="form-card-body" style="padding: 0;">
                        <?php if (empty($affiliateReferrals)): ?>
                            <div style="text-align: center; padding: 60px 20px; color: var(--gray);">
                                <div style="font-size: 48px; margin-bottom: 20px;">👥</div>
                                <h3>Henüz referral bulunmuyor</h3>
                                <p>Bu affiliate'in henüz kayıt olan müşterisi yok.</p>
                            </div>
                        <?php else: ?>
                            <table class="services-table">
                                <thead>
                                    <tr>
                                        <th>Müşteri</th>
                                        <th>Durum</th>
                                        <th>Kayıt Tarihi</th>
                                        <th>Ödenen Fatura</th>
                                        <th>Toplam Kazanç</th>
                                        <th>Komisyon</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($affiliateReferrals as $referral): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <div
                                                        style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 14px;">
                                                        <?= strtoupper(substr($referral['first_name'] ?? '?', 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <strong><?= htmlspecialchars($referral['first_name'] . ' ' . $referral['last_name']) ?></strong>
                                                        <br>
                                                        <small
                                                            style="color: var(--gray);"><?= htmlspecialchars($referral['email']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $statusBadge = match ($referral['status']) {
                                                    'approved' => 'success',
                                                    'pending' => 'warning',
                                                    'rejected' => 'danger',
                                                    default => 'gray'
                                                };
                                                $statusText = match ($referral['status']) {
                                                    'approved' => 'Onaylandı',
                                                    'pending' => 'Beklemede',
                                                    'rejected' => 'Reddedildi',
                                                    default => ucfirst($referral['status'])
                                                };
                                                ?>
                                                <span class="badge badge-<?= $statusBadge ?>"><?= $statusText ?></span>
                                            </td>
                                            <td>
                                                <?= date('d.m.Y H:i', strtotime($referral['client_created'])) ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <strong><?= $referral['paid_invoices_count'] ?? 0 ?></strong>
                                            </td>
                                            <td style="text-align: right;">
                                                <strong
                                                    style="color: #10b981;"><?= number_format((float) $referral['total_earned'], 2) ?>
                                                    ₺</strong>
                                            </td>
                                            <td style="text-align: center;">
                                                <span
                                                    style="color: var(--gray); font-size: 13px;"><?= $referral['commission_count'] ?? 0 ?>
                                                    adet</span>
                                            </td>
                                            <td>
                                                <div class="service-actions">
                                                    <a href="client-edit.php?id=<?= $referral['referred_client_id'] ?>"
                                                        class="btn btn-outline btn-sm">👁️ Müşteri</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Komisyonlar -->
                <div class="form-card" style="margin-bottom: 30px;">
                    <div class="form-card-header">
                        <span>💰</span>
                        <h3>Komisyonlar</h3>
                        <span style="margin-left: auto; color: var(--gray); font-size: 13px;">
                            Toplam: <?= count($affiliateCommissions) ?>
                        </span>
                    </div>
                    <div class="form-card-body" style="padding: 0;">
                        <?php if (empty($affiliateCommissions)): ?>
                            <div style="text-align: center; padding: 60px 20px; color: var(--gray);">
                                <div style="font-size: 48px; margin-bottom: 20px;">💰</div>
                                <h3>Henüz komisyon bulunmuyor</h3>
                                <p>Bu affiliate'in henüz komisyon kaydı yok.</p>
                            </div>
                        <?php else: ?>
                            <table class="services-table">
                                <thead>
                                    <tr>
                                        <th>Müşteri</th>
                                        <th>Fatura</th>
                                        <th>Fatura Tutarı</th>
                                        <th>Komisyon</th>
                                        <th>Durum</th>
                                        <th>Tarih</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($affiliateCommissions as $commission): ?>
                                        <tr>
                                            <td>
                                                <?php if ($commission['referred_client_id']): ?>
                                                    <div style="display: flex; align-items: center; gap: 12px;">
                                                        <div
                                                            style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 14px;">
                                                            <?= strtoupper(substr($commission['first_name'] ?? '?', 0, 1)) ?>
                                                        </div>
                                                        <div>
                                                            <strong><?= htmlspecialchars(($commission['first_name'] ?? '') . ' ' . ($commission['last_name'] ?? '')) ?></strong>
                                                            <br>
                                                            <small
                                                                style="color: var(--gray);"><?= htmlspecialchars($commission['email'] ?? 'Bilinmiyor') ?></small>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: var(--gray);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($commission['invoice_number']): ?>
                                                    <a href="invoice-view.php?id=<?= $commission['invoice_id'] ?>"
                                                        style="color: var(--primary); text-decoration: none; font-weight: 600;">
                                                        <?= htmlspecialchars($commission['invoice_number']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span style="color: var(--gray);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: right;">
                                                <?php if ($commission['invoice_total']): ?>
                                                    <strong><?= number_format((float) $commission['invoice_total'], 2) ?> ₺</strong>
                                                <?php else: ?>
                                                    <span style="color: var(--gray);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: right;">
                                                <strong
                                                    style="color: #10b981; font-size: 16px;"><?= number_format((float) $commission['commission_amount'], 2) ?>
                                                    ₺</strong>
                                            </td>
                                            <td>
                                                <?php
                                                $statusBadge = match ($commission['status']) {
                                                    'approved', 'paid' => 'success',
                                                    'pending' => 'warning',
                                                    'rejected' => 'danger',
                                                    default => 'gray'
                                                };
                                                $statusText = match ($commission['status']) {
                                                    'approved' => 'Onaylandı',
                                                    'paid' => 'Ödendi',
                                                    'pending' => 'Beklemede',
                                                    'rejected' => 'Reddedildi',
                                                    default => ucfirst($commission['status'])
                                                };
                                                ?>
                                                <span class="badge badge-<?= $statusBadge ?>"><?= $statusText ?></span>
                                            </td>
                                            <td>
                                                <?= date('d.m.Y H:i', strtotime($commission['created_at'])) ?>
                                            </td>
                                            <td>
                                                <div class="service-actions">
                                                    <?php if ($commission['invoice_id']): ?>
                                                        <a href="invoice-view.php?id=<?= $commission['invoice_id'] ?>"
                                                            class="btn btn-outline btn-sm">📄 Fatura</a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Çekim Talepleri -->
                <?php if (!empty($affiliateWithdrawals)): ?>
                    <div class="form-card">
                        <div class="form-card-header">
                            <span>💸</span>
                            <h3>Çekim Talepleri</h3>
                            <span style="margin-left: auto; color: var(--gray); font-size: 13px;">
                                Toplam: <?= count($affiliateWithdrawals) ?>
                            </span>
                        </div>
                        <div class="form-card-body" style="padding: 0;">
                            <table class="services-table">
                                <thead>
                                    <tr>
                                        <th>Tutar</th>
                                        <th>Durum</th>
                                        <th>Talep Tarihi</th>
                                        <th>Notlar</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($affiliateWithdrawals as $withdrawal): ?>
                                        <tr>
                                            <td style="text-align: right;">
                                                <strong
                                                    style="font-size: 16px;"><?= number_format((float) $withdrawal['amount'], 2) ?>
                                                    ₺</strong>
                                            </td>
                                            <td>
                                                <?php
                                                $statusBadge = match ($withdrawal['status']) {
                                                    'approved', 'paid' => 'success',
                                                    'pending' => 'warning',
                                                    'rejected' => 'danger',
                                                    default => 'gray'
                                                };
                                                $statusText = match ($withdrawal['status']) {
                                                    'approved' => 'Onaylandı',
                                                    'paid' => 'Ödendi',
                                                    'pending' => 'Beklemede',
                                                    'rejected' => 'Reddedildi',
                                                    default => ucfirst($withdrawal['status'])
                                                };
                                                ?>
                                                <span class="badge badge-<?= $statusBadge ?>"><?= $statusText ?></span>
                                            </td>
                                            <td>
                                                <?= date('d.m.Y H:i', strtotime($withdrawal['created_at'])) ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($withdrawal['notes'] ?? '-') ?>
                                            </td>
                                            <td>
                                                <a href="affiliate-withdrawals.php?id=<?= $withdrawal['id'] ?>"
                                                    class="btn btn-outline btn-sm">👁️ Detay</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- SMS Tab -->
        <?php if ($netgsmAvailable): ?>
            <div id="tab-sms" class="tab-content <?= $activeTab === 'sms' ? 'active' : '' ?>">
                <div class="form-card">
                    <div class="form-card-header">
                        <span>📱</span>
                        <h3>SMS Gönder</h3>
                    </div>
                    <div class="form-card-body">
                        <?php if ($smsMessage): ?>
                            <div class="alert alert-<?= $smsMessageType ?>" style="margin-bottom: 20px;">
                                <?= $smsMessage ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="form-group">
                                <label>📞 Telefon Numarası</label>
                                <input type="text" class="form-control"
                                    value="<?= htmlspecialchars($client['phone'] ?? 'Telefon numarası bulunamadı') ?>"
                                    readonly style="background: #f8fafc;">
                            </div>

                            <div class="form-group">
                                <label>💬 Mesaj Metni <small>(<span id="smsCharCount">0</span>/918 karakter)</small></label>
                                <textarea name="sms_text" class="form-control" rows="5"
                                    placeholder="SMS mesajınızı yazın..."
                                    oninput="document.getElementById('smsCharCount').textContent = this.value.length"
                                    maxlength="918" required><?= htmlspecialchars($_POST['sms_text'] ?? '') ?></textarea>
                            </div>

                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <button type="submit" name="send_sms" value="1" class="btn-save"
                                    style="flex: 1; min-width: 200px; background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                                    📤 SMS Gönder
                                </button>
                            </div>

                            <div
                                style="margin-top: 20px; padding: 15px; background: #f8fafc; border-radius: 10px; font-size: 13px; color: var(--gray);">
                                <strong>💡 Bilgi:</strong> SMS gönderimi NetGSM altyapısı üzerinden gerçekleştirilir.
                                Test modu aktifse gerçek SMS gönderilmez.
                                <a href="/modules/netgsm/admin.php"
                                    style="color: var(--primary); text-decoration: none;">NetGSM Ayarları →</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Domains Tab -->
        <?php if (count($domains) > 0): ?>
            <div id="tab-domains" class="tab-content <?= $activeTab === 'domains' ? 'active' : '' ?>">
                <table class="services-table">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>Kayıt Tarihi</th>
                            <th>Bitiş Tarihi</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($domains as $domain): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($domain['domain'] ?? '') ?></strong></td>
                                <td><?= $domain['registration_date'] ? date('d.m.Y', strtotime($domain['registration_date'])) : '-' ?>
                                </td>
                                <td><?= $domain['expiry_date'] ? date('d.m.Y', strtotime($domain['expiry_date'])) : '-' ?></td>
                                <td>
                                    <span class="badge badge-success">Aktif</span>
                                </td>
                                <td>
                                    <a href="#" class="btn btn-outline btn-sm">👁️ Görüntüle</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function switchTab(tabName) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });

        // Remove active class from all tab items
        document.querySelectorAll('.tab-item').forEach(item => {
            item.classList.remove('active');
        });

        // Show selected tab
        document.getElementById('tab-' + tabName).classList.add('active');

        // Add active class to clicked tab item
        event.target.closest('.tab-item').classList.add('active');

        // Update URL without reload
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.pushState({}, '', url);
    }

    function togglePasswordHash() {
        const container = document.getElementById('passwordHashContainer');
        if (container.style.display === 'none') {
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
        }
    }

    function copyPasswordHash() {
        const hash = document.getElementById('passwordHash').textContent;
        navigator.clipboard.writeText(hash).then(function () {
            alert('Şifre hash\'i panoya kopyalandı!');
        });
    }
</script>

<?php include 'includes/footer.php'; ?>