<?php
/**
 * WHMVM - Fatura Ödeme Sayfası
 * PayTR ve diğer ödeme gateway'leri için
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
session_name(SESSION_NAME);
session_start();

if (!isset($_SESSION['client_id'])) {
    header('Location: index.php');
    exit;
}

$invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$clientId = $_SESSION['client_id'];

// Fatura bilgilerini al
$invoice = Database::fetch("
    SELECT i.*, c.first_name, c.last_name, c.email, c.phone, c.address, c.city, c.country
    FROM invoices i
    LEFT JOIN clients c ON i.client_id = c.id
    WHERE i.id = ? AND i.client_id = ?
", [$invoiceId, $clientId]);

// Sipariş numarasını bul (notes alanından veya invoice_items üzerinden)
$orderNumber = null;
if (!empty($invoice['notes'])) {
    // Notes'tan sipariş numarasını çıkar (format: "Sipariş No: ORD-...")
    if (preg_match('/Sipariş No:\s*([A-Z0-9\-]+)/i', $invoice['notes'], $matches)) {
        $orderNumber = $matches[1];
    }
}

// Eğer notes'tan bulunamadıysa, invoice_items üzerinden service'e, oradan order'a bak
if (!$orderNumber) {
    $orderFromService = Database::fetch("
        SELECT o.order_number 
        FROM orders o
        INNER JOIN services s ON o.id = s.order_id
        INNER JOIN invoice_items ii ON s.id = ii.service_id
        WHERE ii.invoice_id = ?
        LIMIT 1
    ", [$invoiceId]);
    
    if ($orderFromService) {
        $orderNumber = $orderFromService['order_number'];
    }
}

if (!$invoice) {
    header('Location: invoices.php');
    exit;
}

if ($invoice['status'] === 'paid') {
    header('Location: invoice-view.php?id=' . $invoiceId);
    exit;
}

$pageTitle = 'Fatura Ödemesi';
$currentPage = 'invoices';

// Ödeme gateway'lerini al (aktif modüller)
$paymentGateways = Database::fetchAll("
    SELECT * FROM modules 
    WHERE category = 'payment' AND is_active = 1
    ORDER BY name
");

// Seçilen ödeme yöntemi
$selectedGateway = $_GET['gateway'] ?? ($paymentGateways[0]['slug'] ?? '');

include 'includes/header.php';
?>

<style>
.payment-page {
    max-width: 1000px;
    margin: 0 auto;
    padding: 40px 20px;
}

/* Page Header */
.payment-header {
    text-align: center;
    margin-bottom: 40px;
    padding-bottom: 30px;
    border-bottom: 2px solid #e2e8f0;
}

.payment-header h1 {
    font-size: 32px;
    font-weight: 800;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 12px;
}

.payment-header .invoice-number {
    font-size: 16px;
    color: #64748b;
    font-family: 'JetBrains Mono', monospace;
    background: #f1f5f9;
    padding: 8px 16px;
    border-radius: 8px;
    display: inline-block;
}

/* Layout Grid */
.payment-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 30px;
    align-items: start;
}

/* Invoice Summary Card */
.invoice-summary-card {
    background: white;
    border-radius: 20px;
    padding: 0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #e2e8f0;
    overflow: hidden;
    position: sticky;
    top: 20px;
}

.summary-header {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    padding: 24px 30px;
    color: white;
}

.summary-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}

.summary-header i {
    font-size: 20px;
}

.summary-body {
    padding: 30px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 15px;
}

.summary-row:last-child {
    border-bottom: none;
}

.summary-row .label {
    color: #64748b;
    font-weight: 500;
}

.summary-row .value {
    color: #1e293b;
    font-weight: 600;
    font-family: 'JetBrains Mono', monospace;
}

.summary-row.total {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(139, 92, 246, 0.05));
    margin: 20px -30px -30px -30px;
    padding: 24px 30px;
    border-top: 2px solid #e2e8f0;
    border-bottom: none;
}

.summary-row.total .label {
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
}

.summary-row.total .value {
    font-size: 24px;
    font-weight: 800;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Payment Methods Section */
.payment-methods-section {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #e2e8f0;
    margin-bottom: 30px;
}

.section-title {
    font-size: 20px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.section-title i {
    color: #6366f1;
    font-size: 24px;
}

.payment-gateways {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 30px;
}

.gateway-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 24px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.gateway-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    transform: scaleX(0);
    transition: transform 0.3s;
}

.gateway-card:hover {
    border-color: #6366f1;
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(99, 102, 241, 0.15);
}

.gateway-card:hover::before {
    transform: scaleX(1);
}

.gateway-card.active {
    border-color: #6366f1;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(139, 92, 246, 0.08));
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.2);
}

.gateway-card.active::before {
    transform: scaleX(1);
}

.gateway-card.active::after {
    content: '✓';
    position: absolute;
    top: 12px;
    right: 12px;
    width: 24px;
    height: 24px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 700;
}

.gateway-icon {
    font-size: 48px;
    margin-bottom: 12px;
    display: block;
}

.gateway-name {
    font-size: 15px;
    font-weight: 600;
    color: #1e293b;
}

/* Payment Form */
.payment-form {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #e2e8f0;
}

.payment-form h3 {
    font-size: 22px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.payment-form h3 i {
    color: #6366f1;
}

.payment-form p {
    color: #64748b;
    margin-bottom: 24px;
    font-size: 15px;
}

.payment-form iframe {
    width: 100%;
    height: 650px;
    border: none;
    border-radius: 16px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

/* Error States */
.error-box {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    border: 2px solid #f87171;
    border-radius: 16px;
    padding: 24px;
    color: #991b1b;
}

.error-box strong {
    display: block;
    font-size: 18px;
    margin-bottom: 12px;
}

/* Responsive */
@media (max-width: 1024px) {
    .payment-layout {
        grid-template-columns: 1fr;
    }
    
    .invoice-summary-card {
        position: relative;
        top: 0;
        margin-bottom: 30px;
    }
}

@media (max-width: 768px) {
    .payment-page {
        padding: 20px 15px;
    }
    
    .payment-header h1 {
        font-size: 24px;
    }
    
    .payment-gateways {
        grid-template-columns: 1fr;
    }
    
    .summary-body {
        padding: 20px;
    }
    
    .summary-row.total {
        margin: 20px -20px -20px -20px;
        padding: 20px;
    }
}
</style>

<div class="payment-page">
    <!-- Page Header -->
    <div class="payment-header">
        <h1><i class="fas fa-credit-card"></i> Fatura Ödemesi</h1>
        <div class="invoice-number">Fatura No: <?= htmlspecialchars($invoice['invoice_number']) ?></div>
    </div>
    
    <div class="payment-layout">
        <!-- Main Content -->
        <div>
            <!-- Ödeme Yöntemleri -->
            <?php if (!empty($paymentGateways)): ?>
                <div class="payment-methods-section">
                    <h3 class="section-title">
                        <i class="fas fa-wallet"></i>
                        Ödeme Yöntemi Seçin
                    </h3>
                    <div class="payment-gateways">
                        <?php foreach ($paymentGateways as $gateway): ?>
                            <div class="gateway-card <?= $selectedGateway === $gateway['slug'] ? 'active' : '' ?>" 
                                 onclick="selectGateway('<?= $gateway['slug'] ?>')">
                                <div class="gateway-icon">
                                    <?php
                                    $icons = [
                                        'paytr' => '💳',
                                        'iyzico' => '💳',
                                        'parampay' => '💳',
                                        'bank-transfer' => '🏦'
                                    ];
                                    echo $icons[$gateway['slug']] ?? '💳';
                                    ?>
                                </div>
                                <div class="gateway-name"><?= htmlspecialchars($gateway['name']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
        
        <!-- PayTR Ödeme Formu -->
        <?php if ($selectedGateway === 'paytr'): ?>
            <?php
            $paytrModule = Database::fetch("SELECT * FROM modules WHERE slug = 'paytr' AND is_active = 1");
            if ($paytrModule && !empty($paytrModule)):
                $paytrConfig = json_decode($paytrModule['config'] ?? '{}', true) ?: [];
                require_once dirname(__DIR__) . '/modules/paytr/PayTRGateway.php';
                
                $gateway = new \WHMVM\Modules\PayTR\PayTRGateway($paytrConfig);
                
                // Ödeme verileri
                $paymentData = [
                    'merchant_oid' => 'INV-' . $invoice['id'] . '-' . time(),
                    'amount' => (float)$invoice['total'],
                    'currency' => $invoice['currency'] === 'TRY' ? 'TL' : $invoice['currency'],
                    'email' => $invoice['email'],
                    'user_name' => $invoice['first_name'] . ' ' . $invoice['last_name'],
                    'user_address' => $invoice['address'] ?? '',
                    'user_phone' => $invoice['phone'] ?? '',
                    'success_url' => SITE_URL . '/client/payment-success.php?invoice=' . $invoice['id'],
                    'fail_url' => SITE_URL . '/client/payment-fail.php?invoice=' . $invoice['id'],
                    'basket' => [
                        [
                            $invoice['invoice_number'],
                            (float)$invoice['total'],
                            1
                        ]
                    ]
                ];
                
                // Ödeme oluştur
                $paymentResult = $gateway->createPayment($paymentData);
                
                if ($paymentResult['status'] === 'success'):
                    // Ödeme kaydı oluştur
                    try {
                        Database::query("
                            INSERT INTO paytr_payments 
                            (invoice_id, merchant_oid, payment_amount, status)
                            VALUES (?, ?, ?, 'pending')
                        ", [$invoice['id'], $paymentData['merchant_oid'], $invoice['total']]);
                    } catch (Exception $e) {
                        // Hata durumunda devam et
                    }
            ?>
                    <div class="payment-form">
                        <h3>
                            <i class="fas fa-lock"></i>
                            PayTR ile Güvenli Ödeme
                        </h3>
                        <p>
                            <i class="fas fa-shield-alt" style="color: #22c55e; margin-right: 8px;"></i>
                            Güvenli ödeme sayfasına yönlendiriliyorsunuz...
                        </p>
                        <iframe src="<?= htmlspecialchars($paymentResult['iframe_url']) ?>"></iframe>
                    </div>
            <?php else: ?>
                    <div class="payment-form">
                        <div class="error-box">
                            <strong><i class="fas fa-exclamation-circle"></i> Hata</strong>
                            <?= htmlspecialchars($paymentResult['message'] ?? 'Ödeme oluşturulamadı') ?>
                            <?php if (isset($paymentResult['debug']) && $paytrConfig['test_mode'] ?? false): ?>
                                <div style="margin-top: 20px; padding: 20px; background: #1e293b; border-radius: 12px; font-size: 12px; font-family: 'JetBrains Mono', monospace; word-break: break-all; color: #e2e8f0;">
                                    <strong style="color: #fbbf24; display: block; margin-bottom: 12px; font-size: 13px;">
                                        <i class="fas fa-bug"></i> Debug Bilgileri (Test Modu)
                                    </strong>
                                    <?php if (isset($paymentResult['debug']['http_code'])): ?>
                                        <div style="margin-bottom: 10px;">
                                            <span style="color: #94a3b8;">HTTP Kodu:</span> 
                                            <span style="color: #60a5fa;"><?= htmlspecialchars((string)$paymentResult['debug']['http_code']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($paymentResult['debug']['response'])): ?>
                                        <pre style="margin: 10px 0; white-space: pre-wrap; color: #cbd5e1; background: rgba(0,0,0,0.3); padding: 12px; border-radius: 8px; overflow-x: auto;"><?= htmlspecialchars(json_encode($paymentResult['debug']['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                    <?php endif; ?>
                                    <?php if (isset($paymentResult['debug']['raw_response'])): ?>
                                        <div style="margin-top: 15px;">
                                            <strong style="color: #fbbf24;">Ham Yanıt:</strong>
                                            <pre style="margin: 10px 0; white-space: pre-wrap; max-height: 200px; overflow: auto; color: #cbd5e1; background: rgba(0,0,0,0.3); padding: 12px; border-radius: 8px;"><?= htmlspecialchars(substr($paymentResult['debug']['raw_response'], 0, 500)) ?></pre>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div style="margin-top: 20px; padding: 16px; background: rgba(255,255,255,0.6); border-radius: 12px; border-left: 4px solid #6366f1;">
                                <strong style="color: #1e293b; display: block; margin-bottom: 12px; font-size: 14px;">
                                    <i class="fas fa-lightbulb" style="color: #f59e0b;"></i> Çözüm Önerileri
                                </strong>
                                <ul style="margin: 0; padding-left: 20px; line-height: 2; color: #475569; font-size: 13px;">
                                    <li>PayTR modül ayarlarını kontrol edin (Merchant ID, Key, Salt)</li>
                                    <li>Test modunda iseniz, test bilgilerinin doğru olduğundan emin olun</li>
                                    <li>PayTR panelinde bildirim URL'inin doğru yapılandırıldığını kontrol edin</li>
                                    <li>Fatura tutarının minimum limitler içinde olduğundan emin olun</li>
                                </ul>
                            </div>
                        </div>
                    </div>
            <?php endif; ?>
            <?php else: ?>
                    <div class="payment-form">
                        <div class="error-box">
                            <strong><i class="fas fa-exclamation-circle"></i> Hata</strong>
                            PayTR ödeme modülü aktif değil veya yapılandırılmamış. Lütfen yönetici ile iletişime geçin.
                        </div>
                    </div>
            <?php endif; // if ($paytrModule) ?>
        <?php endif; // if ($selectedGateway === 'paytr') ?>
        
        <!-- Havale/EFT Ödeme Formu -->
        <?php if ($selectedGateway === 'bank-transfer'): ?>
            <?php
            $bankTransferModule = Database::fetch("SELECT * FROM modules WHERE slug = 'bank-transfer' AND is_active = 1");
            if ($bankTransferModule):
                // Banka hesaplarını çek
                $bankAccounts = Database::fetchAll("SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY display_order, bank_name");
                
                if (!empty($bankAccounts)):
                    require_once dirname(__DIR__) . '/modules/bank-transfer/BankTransferGateway.php';
                    $moduleConfig = json_decode($bankTransferModule['config'] ?? '{}', true) ?: [];
                    $moduleConfig['banks'] = $bankAccounts;
                    
                    $gateway = new \WHMVM\Modules\BankTransfer\BankTransferGateway($moduleConfig);
                    $paymentResult = $gateway->createPayment([
                        'invoice_id' => $invoice['id'],
                        'amount' => (float)$invoice['total'],
                        'currency' => $invoice['currency']
                    ]);
                    
                    if ($paymentResult['status'] === 'success'):
            ?>
                    <div class="payment-form">
                        <h3>
                            <i class="fas fa-university"></i>
                            Havale/EFT ile Ödeme
                        </h3>
                        <p style="color: #64748b; margin-bottom: 24px; line-height: 1.8;">
                            <i class="fas fa-info-circle" style="color: #6366f1; margin-right: 8px;"></i>
                            Lütfen aşağıdaki banka hesaplarından birine ödemenizi yapın. Ödeme yaparken <strong>açıklama kısmına sipariş numaranızı</strong> yazmayı unutmayın.
                        </p>
                        
                        <!-- Sipariş Numarası -->
                        <?php if ($orderNumber): ?>
                        <div style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1)); border: 2px solid #6366f1; border-radius: 12px; padding: 20px; margin-bottom: 30px; text-align: center;">
                            <div style="font-size: 13px; color: #64748b; margin-bottom: 8px; font-weight: 600;">SİPARİŞ NUMARANIZ</div>
                            <div style="font-size: 24px; font-weight: 800; color: #6366f1; font-family: 'JetBrains Mono', monospace; letter-spacing: 2px;">
                                <?= htmlspecialchars($orderNumber) ?>
                            </div>
                            <div style="margin-top: 12px;">
                                <button onclick="copyOrderNumber('<?= htmlspecialchars($orderNumber) ?>')" 
                                        class="btn btn-sm btn-primary" style="padding: 8px 20px;">
                                    <i class="fas fa-copy"></i> Kopyala
                                </button>
                            </div>
                        </div>
                        <?php else: ?>
                        <div style="background: #fef3c7; border: 2px solid #f59e0b; border-radius: 12px; padding: 16px; margin-bottom: 30px; text-align: center;">
                            <i class="fas fa-exclamation-triangle" style="color: #f59e0b; margin-right: 8px;"></i>
                            <span style="color: #92400e;">Bu fatura için sipariş numarası bulunamadı. Lütfen fatura numaranızı kullanabilirsiniz: <strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong></span>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Banka Hesapları -->
                        <div style="margin-bottom: 30px;">
                            <h4 style="font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 20px;">
                                <i class="fas fa-building" style="color: #6366f1;"></i> Banka Hesaplarımız
                            </h4>
                            <div style="display: grid; gap: 20px;">
                                <?php foreach ($bankAccounts as $index => $bank): ?>
                                <div style="background: white; border: 2px solid #e2e8f0; border-radius: 16px; padding: 24px; transition: all 0.3s;">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px;">
                                        <div>
                                            <h5 style="font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 4px;">
                                                <?= htmlspecialchars($bank['bank_name']) ?>
                                            </h5>
                                            <?php if ($bank['branch_name']): ?>
                                                <p style="font-size: 13px; color: #64748b; margin: 0;">
                                                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($bank['branch_name']) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <span style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 700;">
                                            <?= htmlspecialchars($bank['currency']) ?>
                                        </span>
                                    </div>
                                    
                                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 16px;">
                                        <div>
                                            <label style="font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px;">Hesap Sahibi</label>
                                            <div style="font-size: 15px; font-weight: 600; color: #1e293b;">
                                                <?= htmlspecialchars($bank['account_holder']) ?>
                                            </div>
                                        </div>
                                        <div>
                                            <label style="font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px;">Hesap Numarası</label>
                                            <div style="font-size: 15px; font-weight: 600; color: #1e293b; font-family: 'JetBrains Mono', monospace;">
                                                <?= htmlspecialchars($bank['account_number']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($bank['iban']): ?>
                                    <div style="background: #f8fafc; border-radius: 10px; padding: 14px; margin-bottom: 12px;">
                                        <label style="font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px;">IBAN</label>
                                        <div style="font-size: 16px; font-weight: 700; color: #6366f1; font-family: 'JetBrains Mono', monospace; word-break: break-all;">
                                            <?= htmlspecialchars($bank['iban']) ?>
                                        </div>
                                        <button onclick="copyIBAN('<?= htmlspecialchars($bank['iban']) ?>')" 
                                                style="margin-top: 8px; padding: 6px 12px; background: #6366f1; color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;">
                                            <i class="fas fa-copy"></i> IBAN'ı Kopyala
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($bank['swift_code']): ?>
                                    <div>
                                        <label style="font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px;">SWIFT Kodu</label>
                                        <div style="font-size: 14px; font-weight: 600; color: #1e293b; font-family: 'JetBrains Mono', monospace;">
                                            <?= htmlspecialchars($bank['swift_code']) ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Ödeme Onay Formu -->
                        <div style="background: #f8fafc; border-radius: 16px; padding: 24px; border: 2px dashed #e2e8f0;">
                            <h4 style="font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 16px;">
                                <i class="fas fa-sticky-note" style="color: #6366f1;"></i> Siparişiniz ile Alakalı Not
                            </h4>
                            <form method="POST" action="payment-confirm.php">
                                <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                                <input type="hidden" name="reference_number" value="<?= htmlspecialchars($paymentResult['reference_number']) ?>">
                                <input type="hidden" name="gateway" value="bank-transfer">
                                
                                <div style="margin-bottom: 20px;">
                                    <label style="display: block; font-size: 14px; font-weight: 600; color: #1e293b; margin-bottom: 8px;">
                                        Not (Opsiyonel)
                                    </label>
                                    <textarea name="client_note" class="form-control" rows="3" 
                                              placeholder="Ödeme ile ilgili ek bilgi varsa buraya yazabilirsiniz..."></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 16px; font-weight: 700;">
                                    <i class="fas fa-paper-plane"></i> Ödeme Bildirimi Gönder
                                </button>
                            </form>
                        </div>
                    </div>
            <?php else: ?>
                    <div class="payment-form">
                        <div class="error-box">
                            <strong><i class="fas fa-exclamation-circle"></i> Hata</strong>
                            Ödeme kaydı oluşturulamadı. Lütfen tekrar deneyin.
                        </div>
                    </div>
            <?php endif; ?>
            <?php else: ?>
                    <div class="payment-form">
                        <div style="text-align: center; padding: 60px 40px; color: #64748b;">
                            <i class="fas fa-university" style="font-size: 48px; color: #f59e0b; margin-bottom: 20px; display: block;"></i>
                            <p style="font-size: 18px; font-weight: 600; margin-bottom: 10px; color: #1e293b;">Banka hesabı bulunamadı</p>
                            <p style="font-size: 15px;">Lütfen yönetici ile iletişime geçin.</p>
                        </div>
                    </div>
            <?php endif; ?>
            <?php else: ?>
                    <div class="payment-form">
                        <div class="error-box">
                            <strong><i class="fas fa-exclamation-circle"></i> Hata</strong>
                            Havale/EFT ödeme modülü aktif değil. Lütfen yönetici ile iletişime geçin.
                        </div>
                    </div>
            <?php endif; // if ($bankTransferModule) ?>
        <?php endif; // if ($selectedGateway === 'bank-transfer') ?>
            <?php else: ?>
                <div class="payment-form">
                    <div style="text-align: center; padding: 60px 40px; color: #64748b;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #f59e0b; margin-bottom: 20px; display: block;"></i>
                        <p style="font-size: 18px; font-weight: 600; margin-bottom: 10px; color: #1e293b;">Aktif ödeme yöntemi bulunamadı</p>
                        <p style="font-size: 15px;">Lütfen yönetici ile iletişime geçin.</p>
                    </div>
                </div>
            <?php endif; // if (!empty($paymentGateways)) ?>
        </div>
        
        <!-- Sidebar - Invoice Summary -->
        <div class="invoice-summary-card">
            <div class="summary-header">
                <h3>
                    <i class="fas fa-receipt"></i>
                    Fatura Özeti
                </h3>
            </div>
            <div class="summary-body">
                <div class="summary-row">
                    <span class="label">Fatura Tutarı</span>
                    <span class="value"><?= number_format((float)$invoice['subtotal'], 2, ',', '.') ?> <?= $invoice['currency'] ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">KDV (%20)</span>
                    <span class="value"><?= number_format((float)$invoice['tax'], 2, ',', '.') ?> <?= $invoice['currency'] ?></span>
                </div>
                <div class="summary-row total">
                    <span class="label">Toplam Tutar</span>
                    <span class="value"><?= number_format((float)$invoice['total'], 2, ',', '.') ?> <?= $invoice['currency'] ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function selectGateway(gateway) {
    window.location.href = '?id=<?= $invoiceId ?>&gateway=' + gateway;
}

function copyOrderNumber(orderNum) {
    navigator.clipboard.writeText(orderNum).then(function() {
        alert('Sipariş numarası kopyalandı: ' + orderNum);
    }, function() {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = orderNum;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Sipariş numarası kopyalandı: ' + orderNum);
    });
}

function copyIBAN(iban) {
    const cleanIban = iban.replace(/\s/g, '');
    navigator.clipboard.writeText(cleanIban).then(function() {
        alert('IBAN kopyalandı!');
    }, function() {
        const textarea = document.createElement('textarea');
        textarea.value = cleanIban;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('IBAN kopyalandı!');
    });
}
</script>

<?php include 'includes/footer.php'; ?>
