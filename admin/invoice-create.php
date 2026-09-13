<?php
/**
 * WHMVM - Admin Yeni Fatura Oluşturma
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Mail.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Yeni Fatura Oluştur';
$currentPage = 'invoices';
$db = Database::getInstance();

$message = '';
$messageType = '';

// Müşterileri çek
$clients = Database::fetchAll("SELECT id, first_name, last_name, email, company_name FROM clients ORDER BY first_name, last_name");

// Hizmetleri çek (opsiyonel ilişkilendirme için)
$services = [];

// Seçili müşteri varsa hizmetlerini çek
$selectedClientId = (int) ($_GET['client_id'] ?? $_POST['client_id'] ?? 0);
if ($selectedClientId > 0) {
    $services = Database::fetchAll("
        SELECT s.id, s.domain, p.name as product_name 
        FROM services s 
        LEFT JOIN products p ON s.product_id = p.id 
        WHERE s.client_id = ? AND s.status IN ('active', 'pending')
        ORDER BY s.created_at DESC
    ", [$selectedClientId]);
}

// Fatura oluştur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invoice'])) {
    $clientId = (int) $_POST['client_id'];
    $dueDate = $_POST['due_date'] ?? date('Y-m-d', strtotime('+7 days'));
    $notes = trim($_POST['notes'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? 'bank_transfer';
    $sendEmail = isset($_POST['send_email']);

    // Kalemler
    $itemDescriptions = $_POST['item_description'] ?? [];
    $itemQuantities = $_POST['item_quantity'] ?? [];
    $itemPrices = $_POST['item_price'] ?? [];
    $itemServiceIds = $_POST['item_service_id'] ?? [];

    if ($clientId <= 0) {
        $message = 'Lütfen bir müşteri seçin.';
        $messageType = 'error';
    } elseif (empty($itemDescriptions) || empty(array_filter($itemDescriptions))) {
        $message = 'En az bir fatura kalemi ekleyin.';
        $messageType = 'error';
    } else {
        try {
            $db->beginTransaction();

            // Fatura hesaplamaları
            $subtotal = 0;
            $taxRate = (float) Settings::get('tax_rate', '20');

            foreach ($itemDescriptions as $i => $desc) {
                if (empty(trim($desc)))
                    continue;
                $qty = (float) ($itemQuantities[$i] ?? 1);
                $price = (float) ($itemPrices[$i] ?? 0);
                $subtotal += $qty * $price;
            }

            $tax = $subtotal * ($taxRate / 100);
            $total = $subtotal + $tax;

            // Fatura numarası oluştur
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));

            // Faturayı oluştur
            Database::query("
                INSERT INTO invoices (invoice_number, client_id, status, subtotal, tax, tax_rate, total, amount_paid, currency, due_date, payment_method, notes, created_at)
                VALUES (?, ?, 'unpaid', ?, ?, ?, ?, 0, 'TRY', ?, ?, ?, NOW())
            ", [
                $invoiceNumber,
                $clientId,
                $subtotal,
                $tax,
                $taxRate,
                $total,
                $dueDate,
                $paymentMethod,
                $notes
            ]);

            $invoiceId = $db->lastInsertId();

            // Fatura kalemlerini ekle
            foreach ($itemDescriptions as $i => $desc) {
                if (empty(trim($desc)))
                    continue;

                $qty = (float) ($itemQuantities[$i] ?? 1);
                $price = (float) ($itemPrices[$i] ?? 0);
                $serviceId = !empty($itemServiceIds[$i]) ? (int) $itemServiceIds[$i] : null;
                $itemTax = ($qty * $price) * ($taxRate / 100);
                $itemTotal = ($qty * $price) + $itemTax;

                Database::query("
                    INSERT INTO invoice_items (invoice_id, type, description, quantity, unit_price, tax, total, service_id)
                    VALUES (?, 'service', ?, ?, ?, ?, ?, ?)
                ", [
                    $invoiceId,
                    trim($desc),
                    $qty,
                    $price,
                    $itemTax,
                    $itemTotal,
                    $serviceId
                ]);
            }

            $db->commit();

            // E-posta gönder
            $client = null;
            if ($sendEmail || isset($_POST['send_sms'])) {
                $client = Database::fetch("SELECT first_name, last_name, email, phone FROM clients WHERE id = ?", [$clientId]);
            }

            if ($sendEmail && $client) {
                try {
                    Mail::sendTemplate('invoice_created', $client['email'], [
                        'client_name' => $client['first_name'] . ' ' . $client['last_name'],
                        'invoice_id' => $invoiceNumber,
                        'invoice_total' => number_format($total, 2, ',', '.'),
                        'due_date' => date('d.m.Y', strtotime($dueDate))
                    ], $client['first_name']);
                } catch (Throwable $e) {
                    // Mail hatası
                }
            }

            // SMS Bildirimi Gönder (ayrı checkbox kontrolü)
            if (isset($_POST['send_sms']) && $client && !empty($client['phone'])) {
                try {
                    $netgsmPath = dirname(__DIR__) . '/modules/netgsm/NetGSMGateway.php';
                    if (file_exists($netgsmPath)) {
                        require_once $netgsmPath;
                        $netgsmSettings = Database::fetch("SELECT config FROM modules WHERE slug = 'netgsm' AND is_active = 1");
                        if ($netgsmSettings) {
                            $netgsmConfig = json_decode($netgsmSettings['config'] ?? '{}', true);
                            if (!empty($netgsmConfig['usercode'])) {
                                $gateway = new NetGSMGateway($netgsmConfig);
                                $smsText = "Sayın " . $client['first_name'] . " " . $client['last_name'] . ", " .
                                    $invoiceNumber . " numaralı faturanız oluşturulmuştur. " .
                                    "Tutar: " . number_format($total, 2, ',', '.') . " TL. " .
                                    "Son Ödeme: " . date('d.m.Y', strtotime($dueDate)) . ". " .
                                    SITE_NAME;
                                $gateway->sendSMS($client['phone'], $smsText);
                            }
                        }
                    }
                } catch (Throwable $e) {
                    // SMS hatası
                }
            }

            header("Location: invoice-view.php?id=$invoiceId&created=1");
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $message = 'Fatura oluşturulurken hata: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

include 'includes/header.php';
?>

<style>
    /* ========================================
   INVOICE CREATE - PREMIUM DESIGN
   ======================================== */

    .invoice-create-wrapper {
        max-width: 1100px;
        margin: 0 auto;
        animation: fadeInUp 0.5s ease;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Hero Header */
    .invoice-hero {
        background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);
        border-radius: 24px;
        padding: 40px;
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
    }

    .invoice-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 500px;
        height: 500px;
        background: radial-gradient(circle, rgba(99, 102, 241, 0.3) 0%, transparent 70%);
        pointer-events: none;
    }

    .invoice-hero::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(168, 85, 247, 0.2) 0%, transparent 70%);
        pointer-events: none;
    }

    .invoice-hero-content {
        position: relative;
        z-index: 1;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .invoice-hero-left {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .invoice-hero-icon {
        width: 70px;
        height: 70px;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .invoice-hero-text h1 {
        color: white;
        font-size: 28px;
        font-weight: 700;
        margin: 0 0 8px 0;
    }

    .invoice-hero-text p {
        color: rgba(255, 255, 255, 0.7);
        font-size: 15px;
        margin: 0;
    }

    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        color: white;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s;
    }

    .back-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: translateX(-5px);
    }

    /* Alert */
    .alert {
        padding: 18px 24px;
        border-radius: 16px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 15px;
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .alert-error {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(239, 68, 68, 0.05) 100%);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #fca5a5;
    }

    .alert-success {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(16, 185, 129, 0.05) 100%);
        border: 1px solid rgba(16, 185, 129, 0.3);
        color: #6ee7b7;
    }

    .alert i {
        font-size: 20px;
    }

    /* Main Layout */
    .invoice-layout {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 25px;
    }

    @media (max-width: 1024px) {
        .invoice-layout {
            grid-template-columns: 1fr;
        }
    }

    /* Form Cards */
    .form-section {
        background: linear-gradient(145deg, rgba(30, 41, 59, 0.8) 0%, rgba(15, 23, 42, 0.9) 100%);
        border-radius: 20px;
        border: 1px solid rgba(148, 163, 184, 0.1);
        overflow: hidden;
        margin-bottom: 25px;
        backdrop-filter: blur(10px);
    }

    .form-section-header {
        padding: 24px 28px;
        background: linear-gradient(90deg, rgba(99, 102, 241, 0.1) 0%, transparent 100%);
        border-bottom: 1px solid rgba(148, 163, 184, 0.1);
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .section-icon {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
        box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
    }

    .section-title {
        flex: 1;
    }

    .section-title h3 {
        margin: 0 0 4px 0;
        font-size: 17px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .section-title span {
        font-size: 13px;
        color: var(--text-muted);
    }

    .form-section-body {
        padding: 28px;
    }

    /* Form Elements */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .form-grid.single {
        grid-template-columns: 1fr;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .form-group.full-width {
        grid-column: span 2;
    }

    .form-label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 600;
        color: var(--text-secondary);
    }

    .form-label i {
        font-size: 12px;
        color: var(--primary);
    }

    .form-label .required {
        color: #f87171;
        font-size: 12px;
    }

    .form-input {
        width: 100%;
        padding: 14px 18px;
        background: rgba(15, 23, 42, 0.6);
        border: 2px solid rgba(148, 163, 184, 0.15);
        border-radius: 12px;
        color: var(--text-primary);
        font-size: 14px;
        font-family: inherit;
        transition: all 0.3s ease;
    }

    .form-input:hover {
        border-color: rgba(148, 163, 184, 0.25);
    }

    .form-input:focus {
        outline: none;
        border-color: var(--primary);
        background: rgba(99, 102, 241, 0.05);
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
    }

    .form-input::placeholder {
        color: var(--text-muted);
    }

    select.form-input {
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 15px center;
        background-size: 18px;
        padding-right: 45px;
    }

    textarea.form-input {
        resize: vertical;
        min-height: 100px;
    }

    /* Invoice Items Table */
    .items-container {
        background: rgba(15, 23, 42, 0.4);
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid rgba(148, 163, 184, 0.1);
    }

    .items-header {
        display: grid;
        grid-template-columns: 1fr 100px 140px 120px 50px;
        gap: 12px;
        padding: 16px 20px;
        background: linear-gradient(90deg, rgba(99, 102, 241, 0.15) 0%, rgba(168, 85, 247, 0.1) 100%);
        border-bottom: 1px solid rgba(148, 163, 184, 0.1);
    }

    .items-header span {
        font-size: 12px;
        font-weight: 700;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .items-header span:nth-child(2),
    .items-header span:nth-child(3),
    .items-header span:nth-child(4) {
        text-align: center;
    }

    .items-header span:last-child {
        text-align: center;
    }

    .items-body {
        max-height: 400px;
        overflow-y: auto;
    }

    .item-row {
        display: grid;
        grid-template-columns: 1fr 100px 140px 120px 50px;
        gap: 12px;
        padding: 16px 20px;
        border-bottom: 1px solid rgba(148, 163, 184, 0.05);
        transition: background 0.2s;
        align-items: center;
    }

    .item-row:hover {
        background: rgba(99, 102, 241, 0.03);
    }

    .item-row:last-child {
        border-bottom: none;
    }

    .item-input {
        width: 100%;
        padding: 12px 14px;
        background: rgba(30, 41, 59, 0.6);
        border: 1px solid rgba(148, 163, 184, 0.15);
        border-radius: 10px;
        color: var(--text-primary);
        font-size: 14px;
        transition: all 0.2s;
    }

    .item-input:focus {
        outline: none;
        border-color: var(--primary);
        background: rgba(99, 102, 241, 0.05);
    }

    .item-input.qty,
    .item-input.price {
        text-align: center;
    }

    .item-total-display {
        text-align: center;
        font-weight: 600;
        color: var(--text-primary);
        font-size: 14px;
        padding: 12px;
        background: rgba(99, 102, 241, 0.1);
        border-radius: 10px;
    }

    .btn-delete-item {
        width: 40px;
        height: 40px;
        border: none;
        background: rgba(239, 68, 68, 0.1);
        color: #f87171;
        border-radius: 10px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        margin: 0 auto;
    }

    .btn-delete-item:hover {
        background: #ef4444;
        color: white;
        transform: scale(1.05);
    }

    .add-item-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        width: 100%;
        padding: 16px;
        background: transparent;
        border: 2px dashed rgba(99, 102, 241, 0.3);
        border-radius: 12px;
        color: var(--primary);
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 16px;
    }

    .add-item-btn:hover {
        background: rgba(99, 102, 241, 0.1);
        border-color: var(--primary);
        border-style: solid;
    }

    .add-item-btn i {
        font-size: 16px;
    }

    /* Sidebar */
    .invoice-sidebar {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* Summary Card */
    .summary-card {
        background: linear-gradient(145deg, rgba(30, 41, 59, 0.9) 0%, rgba(15, 23, 42, 0.95) 100%);
        border-radius: 20px;
        border: 1px solid rgba(148, 163, 184, 0.1);
        overflow: hidden;
        position: sticky;
        top: 20px;
    }

    .summary-header {
        padding: 24px;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(6, 182, 212, 0.1) 100%);
        border-bottom: 1px solid rgba(148, 163, 184, 0.1);
        text-align: center;
    }

    .summary-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
        color: #6ee7b7;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .summary-body {
        padding: 24px;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 0;
        border-bottom: 1px solid rgba(148, 163, 184, 0.08);
    }

    .summary-row:last-of-type {
        border-bottom: none;
    }

    .summary-row span:first-child {
        color: var(--text-muted);
        font-size: 14px;
    }

    .summary-row span:last-child {
        color: var(--text-primary);
        font-weight: 600;
        font-size: 15px;
    }

    .summary-total {
        margin-top: 20px;
        padding: 20px;
        background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
        border-radius: 16px;
        text-align: center;
    }

    .summary-total-label {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.8);
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .summary-total-value {
        font-size: 32px;
        font-weight: 800;
        color: white;
    }

    /* Email Checkbox */
    .email-option {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 18px 20px;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(6, 182, 212, 0.05) 100%);
        border: 1px solid rgba(16, 185, 129, 0.2);
        border-radius: 14px;
        cursor: pointer;
        transition: all 0.3s;
    }

    .email-option:hover {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(6, 182, 212, 0.1) 100%);
    }

    .email-option input {
        display: none;
    }

    .email-checkbox {
        width: 24px;
        height: 24px;
        border: 2px solid rgba(16, 185, 129, 0.5);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        flex-shrink: 0;
    }

    .email-option input:checked+.email-checkbox {
        background: #10b981;
        border-color: #10b981;
    }

    .email-checkbox i {
        color: white;
        font-size: 12px;
        opacity: 0;
        transform: scale(0);
        transition: all 0.2s;
    }

    .email-option input:checked+.email-checkbox i {
        opacity: 1;
        transform: scale(1);
    }

    .email-text {
        flex: 1;
    }

    .email-text strong {
        display: block;
        font-size: 14px;
        color: var(--text-primary);
        margin-bottom: 2px;
    }

    .email-text span {
        font-size: 12px;
        color: var(--text-muted);
    }

    /* Submit Button */
    .submit-section {
        padding: 24px;
        background: rgba(15, 23, 42, 0.5);
        border-top: 1px solid rgba(148, 163, 184, 0.1);
    }

    .btn-submit {
        width: 100%;
        padding: 18px 30px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border: none;
        border-radius: 14px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        transition: all 0.3s;
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
    }

    .btn-submit:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 35px rgba(16, 185, 129, 0.4);
    }

    .btn-submit:active {
        transform: translateY(-1px);
    }

    .btn-submit i {
        font-size: 18px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .invoice-hero {
            padding: 30px 20px;
        }

        .invoice-hero-content {
            flex-direction: column;
            gap: 20px;
            text-align: center;
        }

        .invoice-hero-left {
            flex-direction: column;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .form-group.full-width {
            grid-column: span 1;
        }

        .items-header,
        .item-row {
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .items-header span:first-child {
            display: block;
        }

        .items-header span:not(:first-child) {
            display: none;
        }

        .item-row {
            padding: 20px;
            background: rgba(30, 41, 59, 0.3);
            margin: 10px;
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.1);
        }
    }
</style>

<div class="invoice-create-wrapper">

    <!-- Hero Header -->
    <div class="invoice-hero">
        <div class="invoice-hero-content">
            <div class="invoice-hero-left">
                <div class="invoice-hero-icon">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="invoice-hero-text">
                    <h1>Yeni Fatura Oluştur</h1>
                    <p>Müşterinize yeni bir fatura oluşturun ve e-posta ile gönderin</p>
                </div>
            </div>
            <a href="invoices.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Faturalara Dön
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <i class="fas fa-<?= $messageType === 'error' ? 'exclamation-triangle' : 'check-circle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="invoiceForm">
        <input type="hidden" name="create_invoice" value="1">

        <div class="invoice-layout">
            <!-- Sol Kolon - Form Alanları -->
            <div class="invoice-main">

                <!-- Müşteri Seçimi -->
                <div class="form-section">
                    <div class="form-section-header">
                        <div class="section-icon"><i class="fas fa-user-circle"></i></div>
                        <div class="section-title">
                            <h3>Müşteri Bilgileri</h3>
                            <span>Faturanın kesileceği müşteriyi seçin</span>
                        </div>
                    </div>
                    <div class="form-section-body">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-user"></i>
                                Müşteri Seçin
                                <span class="required">*</span>
                            </label>
                            <select name="client_id" class="form-input" required id="clientSelect">
                                <option value="">🔍 Müşteri ara veya seç...</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= $client['id'] ?>" <?= $selectedClientId == $client['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?>
                                        <?= !empty($client['company_name']) ? ' • ' . htmlspecialchars($client['company_name']) : '' ?>
                                        — <?= htmlspecialchars($client['email']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Fatura Detayları -->
                <div class="form-section">
                    <div class="form-section-header">
                        <div class="section-icon"><i class="fas fa-cog"></i></div>
                        <div class="section-title">
                            <h3>Fatura Ayarları</h3>
                            <span>Vade tarihi ve ödeme yöntemini belirleyin</span>
                        </div>
                    </div>
                    <div class="form-section-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-calendar-alt"></i>
                                    Son Ödeme Tarihi
                                    <span class="required">*</span>
                                </label>
                                <input type="date" name="due_date" class="form-input"
                                    value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-credit-card"></i>
                                    Ödeme Yöntemi
                                </label>
                                <select name="payment_method" class="form-input">
                                    <option value="bank_transfer">🏦 Banka Havalesi / EFT</option>
                                    <option value="credit_card">💳 Kredi Kartı</option>
                                    <option value="paypal">🅿️ PayPal</option>
                                    <option value="crypto">₿ Kripto Para</option>
                                </select>
                            </div>
                            <div class="form-group full-width">
                                <label class="form-label">
                                    <i class="fas fa-sticky-note"></i>
                                    Fatura Notları
                                </label>
                                <textarea name="notes" class="form-input"
                                    placeholder="Ödeme koşulları, banka bilgileri veya özel notlar..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fatura Kalemleri -->
                <div class="form-section">
                    <div class="form-section-header">
                        <div class="section-icon"><i class="fas fa-list-ul"></i></div>
                        <div class="section-title">
                            <h3>Fatura Kalemleri</h3>
                            <span>Faturalanacak ürün ve hizmetleri ekleyin</span>
                        </div>
                    </div>
                    <div class="form-section-body">
                        <div class="items-container">
                            <div class="items-header">
                                <span>Açıklama</span>
                                <span>Miktar</span>
                                <span>Birim Fiyat</span>
                                <span>Toplam</span>
                                <span></span>
                            </div>
                            <div class="items-body" id="itemsBody">
                                <div class="item-row">
                                    <input type="text" name="item_description[]" class="item-input"
                                        placeholder="Ürün veya hizmet açıklaması..." required>
                                    <input type="hidden" name="item_service_id[]" value="">
                                    <input type="number" name="item_quantity[]" class="item-input qty item-qty"
                                        value="1" min="1" step="1" oninput="calculateTotals()">
                                    <input type="number" name="item_price[]" class="item-input price item-price"
                                        value="0" min="0" step="0.01" placeholder="0,00" oninput="calculateTotals()">
                                    <div class="item-total-display item-total">0,00 ₺</div>
                                    <button type="button" class="btn-delete-item" onclick="removeItem(this)"
                                        title="Kaldır">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="add-item-btn" onclick="addItem()">
                            <i class="fas fa-plus-circle"></i>
                            Yeni Kalem Ekle
                        </button>
                    </div>
                </div>

            </div>

            <!-- Sağ Kolon - Özet -->
            <div class="invoice-sidebar">
                <div class="summary-card">
                    <div class="summary-header">
                        <h3><i class="fas fa-calculator"></i> Fatura Özeti</h3>
                    </div>
                    <div class="summary-body">
                        <div class="summary-row">
                            <span>Ara Toplam</span>
                            <span id="subtotal">0,00 ₺</span>
                        </div>
                        <div class="summary-row">
                            <span>KDV (%<?= Settings::get('tax_rate', '20') ?>)</span>
                            <span id="taxAmount">0,00 ₺</span>
                        </div>

                        <div class="summary-total">
                            <div class="summary-total-label">Genel Toplam</div>
                            <div class="summary-total-value" id="grandTotal">0,00 ₺</div>
                        </div>

                        <!-- E-posta Seçeneği -->
                        <label class="email-option" style="margin-top: 24px;">
                            <input type="checkbox" name="send_email" id="sendEmail" checked>
                            <div class="email-checkbox">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="email-text">
                                <strong>📧 E-posta Gönder</strong>
                                <span>Müşteriye e-posta bildirimi gönder</span>
                            </div>
                        </label>

                        <!-- SMS Seçeneği -->
                        <label class="email-option"
                            style="margin-top: 12px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(168, 85, 247, 0.05) 100%); border-color: rgba(99, 102, 241, 0.2);">
                            <input type="checkbox" name="send_sms" id="sendSms" checked>
                            <div class="email-checkbox" style="border-color: rgba(99, 102, 241, 0.5);">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="email-text">
                                <strong>📱 SMS Gönder</strong>
                                <span>Müşteriye SMS bildirimi gönder (NetGSM)</span>
                            </div>
                        </label>
                    </div>

                    <div class="submit-section">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-file-invoice"></i>
                            Fatura Oluştur
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

<script>
    const TAX_RATE = <?= (float) Settings::get('tax_rate', '20') ?>;

    function addItem() {
        const container = document.getElementById('itemsBody');
        const newRow = document.createElement('div');
        newRow.className = 'item-row';
        newRow.style.animation = 'fadeInUp 0.3s ease';
        newRow.innerHTML = `
        <input type="text" name="item_description[]" class="item-input" 
               placeholder="Ürün veya hizmet açıklaması..." required>
        <input type="hidden" name="item_service_id[]" value="">
        <input type="number" name="item_quantity[]" class="item-input qty item-qty" 
               value="1" min="1" step="1" oninput="calculateTotals()">
        <input type="number" name="item_price[]" class="item-input price item-price" 
               value="0" min="0" step="0.01" placeholder="0,00" oninput="calculateTotals()">
        <div class="item-total-display item-total">0,00 ₺</div>
        <button type="button" class="btn-delete-item" onclick="removeItem(this)" title="Kaldır">
            <i class="fas fa-trash-alt"></i>
        </button>
    `;
        container.appendChild(newRow);

        // Focus to new description input
        newRow.querySelector('input[name="item_description[]"]').focus();
    }

    function removeItem(btn) {
        const rows = document.querySelectorAll('.item-row');
        if (rows.length > 1) {
            const row = btn.closest('.item-row');
            row.style.animation = 'fadeOut 0.2s ease';
            setTimeout(() => {
                row.remove();
                calculateTotals();
            }, 200);
        } else {
            // Shake animation for feedback
            const row = btn.closest('.item-row');
            row.style.animation = 'shake 0.3s ease';
            setTimeout(() => row.style.animation = '', 300);
        }
    }

    function calculateTotals() {
        const rows = document.querySelectorAll('.item-row');
        let subtotal = 0;

        rows.forEach(row => {
            const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            const price = parseFloat(row.querySelector('.item-price').value) || 0;
            const total = qty * price;

            const totalDisplay = row.querySelector('.item-total');
            totalDisplay.textContent = formatCurrency(total);

            // Highlight effect when value changes
            if (total > 0) {
                totalDisplay.style.background = 'rgba(16, 185, 129, 0.15)';
                totalDisplay.style.color = '#6ee7b7';
            } else {
                totalDisplay.style.background = 'rgba(99, 102, 241, 0.1)';
                totalDisplay.style.color = 'var(--text-primary)';
            }

            subtotal += total;
        });

        const tax = subtotal * (TAX_RATE / 100);
        const grandTotal = subtotal + tax;

        // Animate the totals
        animateValue('subtotal', formatCurrency(subtotal));
        animateValue('taxAmount', formatCurrency(tax));
        animateValue('grandTotal', formatCurrency(grandTotal));
    }

    function animateValue(elementId, newValue) {
        const el = document.getElementById(elementId);
        el.style.transform = 'scale(1.05)';
        el.textContent = newValue;
        setTimeout(() => el.style.transform = 'scale(1)', 150);
    }

    function formatCurrency(value) {
        return value.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
    }

    // Müşteri değiştiğinde hizmetleri yükle
    document.getElementById('clientSelect').addEventListener('change', function () {
        if (this.value) {
            window.location.href = 'invoice-create.php?client_id=' + this.value;
        }
    });

    // İlk yüklemede toplamları hesapla
    calculateTotals();

    // Add shake animation keyframes
    const style = document.createElement('style');
    style.textContent = `
    @keyframes fadeOut {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(-20px); }
    }
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
`;
    document.head.appendChild(style);
</script>

<?php include 'includes/footer.php'; ?>