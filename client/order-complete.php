<?php
/**
 * WHMVM - Sipariş Tamamlandı Sayfası
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';

session_name(SESSION_NAME);
session_start();

if (!isset($_SESSION['client_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Sipariş Tamamlandı';
$pageIcon = 'fas fa-check-circle';
$currentPage = 'order-complete';
$clientId = $_SESSION['client_id'];

$orderNumber = $_GET['order'] ?? '';

if (empty($orderNumber)) {
    header('Location: dashboard.php');
    exit;
}

// Sipariş bilgilerini çek
$order = Database::fetch("SELECT * FROM orders WHERE order_number = ? AND client_id = ?", [$orderNumber, $clientId]);

if (!$order) {
    header('Location: dashboard.php');
    exit;
}

// Sipariş kalemlerini çek
$orderItems = Database::fetchAll("SELECT * FROM order_items WHERE order_id = ?", [$order['id']]);

include 'includes/header.php';
?>

<style>
.success-page {
    padding: 60px 0;
    text-align: center;
}

.success-icon {
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, #22c55e, #16a34a);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 30px;
    font-size: 60px;
    color: white;
    animation: scaleIn 0.5s ease;
}

@keyframes scaleIn {
    0% { transform: scale(0); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.success-title {
    font-size: 32px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 12px;
}

.success-subtitle {
    font-size: 18px;
    color: var(--text-muted);
    margin-bottom: 40px;
}

.order-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 30px;
    max-width: 600px;
    margin: 0 auto 30px;
    text-align: left;
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
    margin-bottom: 20px;
}

.order-number {
    font-size: 14px;
    color: var(--text-muted);
}

.order-number strong {
    color: var(--primary);
    font-size: 16px;
}

.order-status {
    padding: 6px 14px;
    background: rgba(251, 191, 36, 0.1);
    border: 1px solid rgba(251, 191, 36, 0.3);
    border-radius: 20px;
    color: #f59e0b;
    font-size: 13px;
    font-weight: 600;
}

.order-items {
    margin-bottom: 20px;
}

.order-item {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-light);
}

.order-item:last-child {
    border-bottom: none;
}

.order-item-name {
    font-size: 15px;
    color: var(--text-primary);
}

.order-item-price {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
}

.order-total {
    display: flex;
    justify-content: space-between;
    padding-top: 20px;
    border-top: 2px solid var(--border-color);
    font-size: 18px;
    font-weight: 700;
}

.order-total span:last-child {
    color: var(--primary);
}

.payment-info {
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 20px;
    margin-top: 24px;
}

.payment-info h4 {
    font-size: 16px;
    color: var(--text-primary);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.payment-info h4 i {
    color: var(--primary);
}

.bank-details {
    font-size: 14px;
    color: var(--text-secondary);
    line-height: 1.8;
}

.bank-details strong {
    color: var(--text-primary);
}

.action-buttons {
    display: flex;
    gap: 16px;
    justify-content: center;
    margin-top: 30px;
}

.btn-action {
    padding: 14px 28px;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.btn-primary-action {
    background: linear-gradient(135deg, var(--primary), #8b5cf6);
    color: white;
}

.btn-primary-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
}

.btn-secondary-action {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
}

.btn-secondary-action:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.next-steps {
    max-width: 600px;
    margin: 40px auto 0;
    text-align: left;
}

.next-steps h4 {
    font-size: 18px;
    color: var(--text-primary);
    margin-bottom: 20px;
    text-align: center;
}

.step-item {
    display: flex;
    gap: 16px;
    padding: 16px 0;
    border-bottom: 1px solid var(--border-light);
}

.step-item:last-child {
    border-bottom: none;
}

.step-number {
    width: 32px;
    height: 32px;
    background: var(--bg-secondary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 600;
    color: var(--primary);
    flex-shrink: 0;
}

.step-content h5 {
    font-size: 15px;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.step-content p {
    font-size: 14px;
    color: var(--text-muted);
}
</style>

<div class="success-page">
    <div class="success-icon">
        <i class="fas fa-check"></i>
    </div>
    
    <h1 class="success-title">Siparişiniz Alındı!</h1>
    <p class="success-subtitle">Sipariş numaranız: <strong><?= htmlspecialchars($orderNumber) ?></strong></p>
    
    <div class="order-card">
        <div class="order-header">
            <div class="order-number">
                Sipariş No: <strong><?= htmlspecialchars($orderNumber) ?></strong>
            </div>
            <span class="order-status">Ödeme Bekleniyor</span>
        </div>
        
        <div class="order-items">
            <?php foreach ($orderItems as $item): ?>
                <div class="order-item">
                    <span class="order-item-name"><?= htmlspecialchars($item['description']) ?></span>
                    <span class="order-item-price">₺<?= number_format((float)$item['total'], 2) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="order-total">
            <span>Toplam</span>
            <span>₺<?= number_format((float)$order['total'], 2) ?></span>
        </div>
        
        <?php if ($order['payment_method'] === 'bank_transfer'): ?>
            <?php
            // Hesap bilgileri bank_accounts tablosundan gelir; invoice-pay.php ve
            // payment-confirm.php de aynı kaynağı kullanır. Buradaki bilgiler
            // önceden sabit kodluydu ve IBAN geçersizdi.
            $bankaHesaplari = Database::fetchAll(
                "SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY display_order, bank_name"
            );
            ?>
            <div class="payment-info">
                <h4><i class="fas fa-university"></i> Banka Hesap Bilgileri</h4>
                <?php if (empty($bankaHesaplari)): ?>
                    <div class="bank-details">
                        Havale bilgileri için lütfen destek ekibimizle iletişime geçin.
                    </div>
                <?php else: ?>
                    <?php foreach ($bankaHesaplari as $hesap): ?>
                        <div class="bank-details">
                            <strong>Banka:</strong> <?= htmlspecialchars((string) $hesap['bank_name']) ?><br>
                            <?php if (!empty($hesap['branch_name'])): ?>
                                <strong>Şube:</strong> <?= htmlspecialchars((string) $hesap['branch_name']) ?>
                                <?= !empty($hesap['branch_code'])
                                    ? '(' . htmlspecialchars((string) $hesap['branch_code']) . ')'
                                    : '' ?><br>
                            <?php endif; ?>
                            <strong>Hesap Adı:</strong> <?= htmlspecialchars((string) $hesap['account_holder']) ?><br>
                            <?php if (!empty($hesap['iban'])): ?>
                                <strong>IBAN:</strong>
                                <span dir="ltr"><?= htmlspecialchars((string) $hesap['iban']) ?></span><br>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="bank-details">
                        <em>Açıklama kısmına sipariş numaranızı yazmayı unutmayın.</em>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="action-buttons">
        <a href="invoices.php" class="btn-action btn-primary-action">
            <i class="fas fa-file-invoice"></i>
            Faturalarım
        </a>
        <a href="dashboard.php" class="btn-action btn-secondary-action">
            <i class="fas fa-home"></i>
            Panele Dön
        </a>
    </div>
    
    <div class="next-steps">
        <h4>Sonraki Adımlar</h4>
        <div class="step-item">
            <span class="step-number">1</span>
            <div class="step-content">
                <h5>Ödeme Yapın</h5>
                <p>Havale/EFT veya kredi kartı ile ödemenizi tamamlayın.</p>
            </div>
        </div>
        <div class="step-item">
            <span class="step-number">2</span>
            <div class="step-content">
                <h5>Onay Bekleyin</h5>
                <p>Ödemeniz onaylandığında size e-posta ile bildirim göndereceğiz.</p>
            </div>
        </div>
        <div class="step-item">
            <span class="step-number">3</span>
            <div class="step-content">
                <h5>Hizmetinizi Kullanın</h5>
                <p>Hizmetiniz aktif edildiğinde hemen kullanmaya başlayabilirsiniz.</p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
