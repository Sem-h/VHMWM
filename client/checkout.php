<?php
/**
 * WHMVM - Client Checkout (Ödeme) Sayfası
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Mail.php';
require_once dirname(__DIR__) . '/includes/OrderLog.php';

session_name(SESSION_NAME);
session_start();

// Giriş kontrolü
if (!isset($_SESSION['client_id'])) {
    header('Location: index.php?redirect=checkout');
    exit;
}

$pageTitle = 'Ödeme';
$pageIcon = 'fas fa-credit-card';
$currentPage = 'checkout';
$clientId = $_SESSION['client_id'];

$db = Database::getInstance();

// Müşteri bilgilerini çek
$client = Database::fetch("SELECT * FROM clients WHERE id = ?", [$clientId]);

// Sepet kontrolü
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: ../cart.php');
    exit;
}

// Sipariş işlemi
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    try {
        $db->beginTransaction();

        // Toplam hesapla
        $subtotal = 0;
        foreach ($_SESSION['cart'] as $item) {
            $subtotal += (float) ($item['total'] ?? $item['price'] ?? 0) * ($item['qty'] ?? 1);
        }
        $taxRate = 20;
        $tax = $subtotal * ($taxRate / 100);
        $total = $subtotal + $tax;
        $paymentMethod = $_POST['payment_method'] ?? 'bank_transfer';

        // Sipariş oluştur
        $lastOrder = $db->query("SELECT MAX(CAST(order_number AS UNSIGNED)) as last_id FROM orders")->fetch();
        $newOrderInt = (int) ($lastOrder['last_id'] ?? 0) + 1;
        $orderNumber = str_pad((string) $newOrderInt, 5, '0', STR_PAD_LEFT);

        Database::query("
            INSERT INTO orders (order_number, client_id, subtotal, tax, total, status, payment_method, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())
        ", [$orderNumber, $clientId, $subtotal, $tax, $total, $paymentMethod]);

        $orderId = (int) $db->lastInsertId();

        // Sipariş logu kaydet
        OrderLog::orderCreated($orderId, $clientId, $orderNumber, $total);

        // Sipariş kalemleri
        foreach ($_SESSION['cart'] as $key => $item) {
            $qty = $item['qty'] ?? 1;
            $unitPrice = (float) ($item['total'] ?? $item['price'] ?? 0);
            $itemTotal = $unitPrice * $qty;
            $itemName = $item['product_name'] ?? $item['name'] ?? 'Ürün';

            // Yapılandırma seçeneklerini açıklamaya ekle
            $configDetails = '';
            if (!empty($item['config_options'])) {
                $configParts = [];
                foreach ($item['config_options'] as $opt) {
                    $configParts[] = $opt['option_name'] . ': ' . $opt['value_name'];
                }
                $configDetails = ' [' . implode(', ', $configParts) . ']';
            }

            Database::query("
                INSERT INTO order_items (order_id, product_id, description, domain, billing_cycle, quantity, unit_price, setup_fee, total)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $orderId,
                $item['product_id'] ?? null,
                $itemName . $configDetails,
                $item['domain'] ?? null,
                $item['billing_cycle'] ?? 'monthly',
                $qty,
                (float) ($item['price'] ?? 0),
                (float) ($item['setup_fee'] ?? 0),
                $itemTotal
            ]);
        }

        // ===== FATURA OLUŞTUR =====
        $lastInvoice = $db->query("SELECT MAX(CAST(invoice_number AS UNSIGNED)) as last_id FROM invoices")->fetch();
        $newInvoiceInt = (int) ($lastInvoice['last_id'] ?? 0) + 1;
        $invoiceNumber = str_pad((string) $newInvoiceInt, 5, '0', STR_PAD_LEFT);
        $dueDate = date('Y-m-d', strtotime('+7 days')); // 7 gün vade

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
            'Sipariş No: ' . $orderNumber
        ]);

        $invoiceId = (int) $db->lastInsertId();

        // Fatura kalemleri
        foreach ($_SESSION['cart'] as $key => $item) {
            $qty = $item['qty'] ?? 1;
            $unitPrice = (float) ($item['total'] ?? $item['price'] ?? 0);
            $itemTax = $unitPrice * $qty * ($taxRate / 100);
            $itemTotal = ($unitPrice * $qty) + $itemTax;
            $itemName = $item['product_name'] ?? $item['name'] ?? 'Ürün';

            // Yapılandırma seçeneklerini açıklamaya ekle
            $configDetails = '';
            if (!empty($item['config_options'])) {
                $configParts = [];
                foreach ($item['config_options'] as $opt) {
                    $configParts[] = $opt['option_name'] . ': ' . $opt['value_name'];
                }
                $configDetails = ' [' . implode(', ', $configParts) . ']';
            }

            Database::query("
                INSERT INTO invoice_items (invoice_id, type, description, quantity, unit_price, tax, total)
                VALUES (?, 'service', ?, ?, ?, ?, ?)
            ", [
                $invoiceId,
                $itemName . $configDetails,
                $qty,
                $unitPrice,
                $itemTax,
                $itemTotal
            ]);
        }

        $db->commit();

        // Ödeme yöntemine göre yönlendirme
        if ($paymentMethod === 'paytr' || $paymentMethod === 'credit_card') {
            // PayTR veya kredi kartı için ödeme sayfasına yönlendir
            // Önce PayTR modülünün aktif olup olmadığını kontrol et
            $paytrModule = Database::fetch("SELECT * FROM modules WHERE slug = 'paytr' AND is_active = 1");

            if ($paytrModule) {
                // PayTR ile ödeme sayfasına yönlendir
                header('Location: invoice-pay.php?id=' . (int) $invoiceId . '&gateway=paytr');
                exit;
            } else {
                // PayTR modülü aktif değilse hata göster
                $db->rollBack();
                $message = 'Kredi kartı ödeme yöntemi şu anda kullanılamıyor. Lütfen başka bir ödeme yöntemi seçin.';
                $messageType = 'error';
            }
        } else {
            // Banka havalesi için normal akış
            // Sipariş alındı e-postası gönder
            try {
                $productNames = array_column($_SESSION['cart'], 'name');
                Mail::sendTemplate('order_received', $client['email'], [
                    'client_name' => $client['first_name'] . ' ' . $client['last_name'],
                    'order_id' => $orderNumber,
                    'product_name' => implode(', ', $productNames),
                    'order_total' => number_format($total, 2, ',', '.')
                ], $client['first_name']);
            } catch (Throwable $e) {
                // Mail hatası siparişi engellemesin
            }

            // Fatura oluşturuldu e-postası gönder
            try {
                Mail::sendTemplate('invoice_created', $client['email'], [
                    'client_name' => $client['first_name'] . ' ' . $client['last_name'],
                    'invoice_id' => $invoiceNumber,
                    'invoice_total' => number_format($total, 2, ',', '.'),
                    'due_date' => date('d.m.Y', strtotime($dueDate))
                ], $client['first_name']);
            } catch (Throwable $e) {
                // Mail hatası siparişi engellemesin
            }

            // Sepeti temizle
            $_SESSION['cart'] = [];

            // Başarılı sayfasına yönlendir
            header('Location: order-complete.php?order=' . $orderNumber);
            exit;
        }

    } catch (Exception $e) {
        $db->rollBack();
        $message = 'Sipariş oluşturulurken bir hata oluştu: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Toplam hesapla
$subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += (float) ($item['total'] ?? $item['price'] ?? 0) * ($item['qty'] ?? 1);
}
$tax = $subtotal * 0.20;
$total = $subtotal + $tax;

include 'includes/header.php';
?>

<style>
    .checkout-page {
        padding: 30px 0;
    }

    .checkout-grid {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 30px;
    }

    .checkout-section {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 30px;
        margin-bottom: 24px;
    }

    .checkout-section h3 {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .checkout-section h3 i {
        color: var(--primary);
    }

    /* Customer Info */
    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }

    .info-item {
        padding: 16px;
        background: var(--bg-secondary);
        border-radius: 10px;
    }

    .info-item label {
        display: block;
        font-size: 12px;
        color: var(--text-muted);
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .info-item span {
        font-size: 15px;
        color: var(--text-primary);
        font-weight: 500;
    }

    /* Payment Methods */
    .payment-methods {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .payment-option {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 20px;
        background: var(--bg-secondary);
        border: 2px solid var(--border-color);
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s;
    }

    .payment-option:hover {
        border-color: var(--primary);
    }

    .payment-option.selected {
        border-color: var(--primary);
        background: rgba(99, 102, 241, 0.1);
    }

    .payment-option input[type="radio"] {
        display: none;
    }

    .payment-radio {
        width: 22px;
        height: 22px;
        border: 2px solid var(--border-color);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .payment-option.selected .payment-radio {
        border-color: var(--primary);
    }

    .payment-radio::after {
        content: '';
        width: 12px;
        height: 12px;
        background: var(--primary);
        border-radius: 50%;
        transform: scale(0);
        transition: transform 0.2s;
    }

    .payment-option.selected .payment-radio::after {
        transform: scale(1);
    }

    .payment-icon {
        width: 50px;
        height: 50px;
        background: var(--bg-card);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }

    .payment-info h4 {
        font-size: 16px;
        color: var(--text-primary);
        margin-bottom: 4px;
    }

    .payment-info p {
        font-size: 13px;
        color: var(--text-muted);
    }

    /* Order Summary */
    .order-summary {
        position: sticky;
        top: 100px;
    }

    .summary-items {
        margin-bottom: 20px;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 16px 0;
        border-bottom: 1px solid var(--border-light);
    }

    .summary-item:last-child {
        border-bottom: none;
    }

    .item-info h4 {
        font-size: 15px;
        color: var(--text-primary);
        margin-bottom: 4px;
    }

    .item-info>span {
        font-size: 13px;
        color: var(--text-muted);
        display: inline-block;
    }

    .item-info .item-type {
        background: var(--primary-color);
        color: white;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 11px;
        margin-right: 6px;
    }

    .item-info .item-cycle {
        background: rgba(var(--primary-rgb), 0.1);
        color: var(--primary-color);
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 11px;
    }

    .config-options-list {
        margin-top: 10px;
        padding: 10px;
        background: rgba(var(--primary-rgb), 0.05);
        border-radius: 8px;
        border-left: 3px solid var(--primary-color);
    }

    .config-option-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 4px 0;
        font-size: 13px;
    }

    .config-option-item .opt-name {
        color: var(--text-muted);
    }

    .config-option-item .opt-value {
        color: var(--text-primary);
        font-weight: 500;
    }

    .config-option-item .opt-price {
        color: var(--success-color);
        font-weight: 600;
        margin-left: auto;
    }

    .setup-fee-info {
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px dashed var(--border-light);
        font-size: 12px;
        color: var(--text-muted);
    }

    .setup-fee-info span {
        color: var(--warning-color);
    }

    .item-price {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-primary);
        white-space: nowrap;
    }

    .summary-totals {
        padding-top: 20px;
        border-top: 2px solid var(--border-color);
    }

    .total-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        font-size: 15px;
        color: var(--text-secondary);
    }

    .total-row.grand-total {
        padding-top: 16px;
        margin-top: 10px;
        border-top: 1px solid var(--border-color);
        font-size: 20px;
        font-weight: 700;
        color: var(--text-primary);
    }

    .total-row.grand-total span:last-child {
        color: var(--primary);
    }

    .btn-checkout {
        width: 100%;
        padding: 18px;
        background: linear-gradient(135deg, var(--primary), #8b5cf6);
        border: none;
        border-radius: 12px;
        color: white;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        margin-top: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: all 0.3s;
    }

    .btn-checkout:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
    }

    .secure-notice {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 16px;
        font-size: 13px;
        color: var(--text-muted);
    }

    .secure-notice i {
        color: #22c55e;
    }

    /* Terms */
    .terms-check {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin-top: 20px;
        padding: 16px;
        background: var(--bg-secondary);
        border-radius: 10px;
    }

    .terms-check input[type="checkbox"] {
        width: 20px;
        height: 20px;
        margin-top: 2px;
        accent-color: var(--primary);
    }

    .terms-check label {
        font-size: 14px;
        color: var(--text-secondary);
        line-height: 1.5;
    }

    .terms-check label a {
        color: var(--primary);
        text-decoration: none;
    }

    /* Alert */
    .alert {
        padding: 16px 20px;
        border-radius: 10px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #ef4444;
    }

    /* Responsive */
    @media (max-width: 992px) {
        .checkout-grid {
            grid-template-columns: 1fr;
        }

        .order-summary {
            position: static;
        }
    }

    @media (max-width: 576px) {
        .info-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="checkout-page">
    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="checkoutForm">
        <div class="checkout-grid">
            <!-- Sol Kolon -->
            <div class="checkout-left">
                <!-- Müşteri Bilgileri -->
                <div class="checkout-section">
                    <h3><i class="fas fa-user"></i> Müşteri Bilgileri</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Ad Soyad</label>
                            <span><?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?></span>
                        </div>
                        <div class="info-item">
                            <label>E-posta</label>
                            <span><?= htmlspecialchars($client['email']) ?></span>
                        </div>
                        <div class="info-item">
                            <label>Telefon</label>
                            <span><?= htmlspecialchars($client['phone'] ?? '-') ?></span>
                        </div>
                        <div class="info-item">
                            <label>Şirket</label>
                            <span><?= htmlspecialchars($client['company_name'] ?? '-') ?></span>
                        </div>
                    </div>
                </div>

                <!-- Ödeme Yöntemi -->
                <div class="checkout-section">
                    <h3><i class="fas fa-credit-card"></i> Ödeme Yöntemi</h3>
                    <div class="payment-methods">
                        <label class="payment-option selected" onclick="selectPayment(this)">
                            <input type="radio" name="payment_method" value="bank_transfer" checked>
                            <span class="payment-radio"></span>
                            <div class="payment-icon">🏦</div>
                            <div class="payment-info">
                                <h4>Banka Havalesi / EFT</h4>
                                <p>Havale veya EFT ile ödeme yapın</p>
                            </div>
                        </label>

                        <?php
                        // Aktif ödeme gateway'lerini al
                        $paymentGateways = Database::fetchAll("
                            SELECT * FROM modules 
                            WHERE category = 'payment' AND is_active = 1
                            ORDER BY name
                        ");

                        // PayTR modülü varsa göster
                        $paytrModule = null;
                        foreach ($paymentGateways as $gateway) {
                            if ($gateway['slug'] === 'paytr') {
                                $paytrModule = $gateway;
                                break;
                            }
                        }

                        if ($paytrModule):
                            ?>
                            <label class="payment-option" onclick="selectPayment(this)">
                                <input type="radio" name="payment_method" value="paytr">
                                <span class="payment-radio"></span>
                                <div class="payment-icon">💳</div>
                                <div class="payment-info">
                                    <h4>Kredi / Banka Kartı (PayTR)</h4>
                                    <p>PayTR ile güvenli online ödeme</p>
                                </div>
                            </label>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sözleşme -->
                <div class="checkout-section">
                    <h3><i class="fas fa-file-contract"></i> Sözleşmeler</h3>
                    <div class="terms-check">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">
                            <a href="#">Hizmet Sözleşmesi</a>, <a href="#">Mesafeli Satış Sözleşmesi</a> ve
                            <a href="#">Gizlilik Politikası</a>'nı okudum ve kabul ediyorum.
                        </label>
                    </div>
                </div>
            </div>

            <!-- Sağ Kolon - Sipariş Özeti -->
            <div class="checkout-right">
                <div class="checkout-section order-summary">
                    <h3><i class="fas fa-shopping-cart"></i> Sipariş Özeti</h3>

                    <div class="summary-items">
                        <?php foreach ($_SESSION['cart'] as $key => $item): ?>
                            <div class="summary-item">
                                <div class="item-info">
                                    <h4><?= htmlspecialchars($item['product_name'] ?? $item['name'] ?? 'Ürün') ?></h4>
                                    <span
                                        class="item-type"><?= ucfirst($item['product_type'] ?? $item['type'] ?? 'Ürün') ?></span>
                                    <span class="item-cycle"><?= match ($item['billing_cycle'] ?? 'monthly') {
                                        'monthly' => 'Aylık',
                                        'quarterly' => '3 Aylık',
                                        'semiannually' => '6 Aylık',
                                        'annually' => 'Yıllık',
                                        default => 'Aylık'
                                    } ?></span>

                                    <?php if (!empty($item['config_options'])): ?>
                                        <div class="config-options-list">
                                            <?php foreach ($item['config_options'] as $opt): ?>
                                                <div class="config-option-item">
                                                    <span class="opt-name"><?= htmlspecialchars($opt['option_name']) ?>:</span>
                                                    <span class="opt-value"><?= htmlspecialchars($opt['value_name']) ?></span>
                                                    <?php if ($opt['price'] > 0): ?>
                                                        <span class="opt-price">+₺<?= number_format((float) $opt['price'], 2) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (($item['setup_fee'] ?? 0) > 0): ?>
                                        <div class="setup-fee-info">
                                            <span>Kurulum Ücreti: +₺<?= number_format((float) $item['setup_fee'], 2) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="item-price">
                                    ₺<?= number_format((float) ($item['total'] ?? $item['price'] ?? 0), 2) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="summary-totals">
                        <div class="total-row">
                            <span>Ara Toplam</span>
                            <span>₺<?= number_format($subtotal, 2) ?></span>
                        </div>
                        <div class="total-row">
                            <span>KDV (%20)</span>
                            <span>₺<?= number_format($tax, 2) ?></span>
                        </div>
                        <div class="total-row grand-total">
                            <span>Toplam</span>
                            <span>₺<?= number_format($total, 2) ?></span>
                        </div>
                    </div>

                    <button type="submit" name="place_order" class="btn-checkout">
                        <i class="fas fa-lock"></i>
                        Siparişi Tamamla
                    </button>

                    <div class="secure-notice">
                        <i class="fas fa-shield-alt"></i>
                        256-bit SSL ile güvenli ödeme
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    function selectPayment(el) {
        document.querySelectorAll('.payment-option').forEach(opt => opt.classList.remove('selected'));
        el.classList.add('selected');
    }

    document.getElementById('checkoutForm').addEventListener('submit', function (e) {
        if (!document.getElementById('terms').checked) {
            e.preventDefault();
            alert('Lütfen sözleşmeleri kabul edin.');
        }
    });
</script>

<?php include 'includes/footer.php'; ?>