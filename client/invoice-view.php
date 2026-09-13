<?php
/**
 * WHMVM - Fatura Detay Sayfası
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
session_name(SESSION_NAME); session_start();

if (!isset($_SESSION['client_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Fatura Detayı';
$pageIcon = 'fas fa-file-invoice';
$currentPage = 'invoices';
$clientId = $_SESSION['client_id'];

$invoiceId = (int)($_GET['id'] ?? 0);

// Fatura bilgilerini çek
$invoice = Database::fetch("SELECT * FROM invoices WHERE id = ? AND client_id = ?", [$invoiceId, $clientId]);

if (!$invoice) {
    header('Location: invoices.php');
    exit;
}

// Fatura kalemleri
$items = Database::fetchAll("SELECT * FROM invoice_items WHERE invoice_id = ?", [$invoiceId]);

// Müşteri bilgileri
$client = Database::fetch("SELECT * FROM clients WHERE id = ?", [$clientId]);

// Şirket bilgileri
$companyName = Settings::get('company_name') ?: SITE_NAME;
$companyAddress = Settings::get('company_address') ?: '';
$companyEmail = Settings::get('company_email') ?: '';
$companyPhone = Settings::get('company_phone') ?: '';
$siteLogo = Settings::get('site_logo') ?: '';

// Durum bilgileri
$statusInfo = match($invoice['status']) {
    'paid' => ['class' => 'paid', 'text' => 'Ödendi', 'icon' => 'fa-check-circle', 'color' => '#22c55e'],
    'unpaid' => ['class' => 'unpaid', 'text' => 'Ödenmedi', 'icon' => 'fa-clock', 'color' => '#f59e0b'],
    'cancelled' => ['class' => 'cancelled', 'text' => 'İptal Edildi', 'icon' => 'fa-times-circle', 'color' => '#ef4444'],
    'refunded' => ['class' => 'refunded', 'text' => 'İade Edildi', 'icon' => 'fa-undo', 'color' => '#8b5cf6'],
    default => ['class' => 'draft', 'text' => 'Taslak', 'icon' => 'fa-file', 'color' => '#64748b']
};

// Vade durumu
$isOverdue = $invoice['status'] === 'unpaid' && strtotime($invoice['due_date']) < time();

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* Invoice Page */
.invoice-page {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px 0;
}

/* Back Link */
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--text-muted);
    text-decoration: none;
    font-size: 14px;
    margin-bottom: 20px;
    transition: color 0.3s;
}

.back-link:hover {
    color: var(--primary);
}

/* Invoice Actions Bar */
.invoice-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.invoice-title {
    display: flex;
    align-items: center;
    gap: 16px;
}

.invoice-title h1 {
    font-size: 24px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.invoice-status {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 600;
}

.invoice-status.paid {
    background: rgba(34, 197, 94, 0.15);
    color: #22c55e;
}

.invoice-status.unpaid {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.invoice-status.cancelled {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.invoice-status.refunded {
    background: rgba(139, 92, 246, 0.15);
    color: #8b5cf6;
}

.action-buttons {
    display: flex;
    gap: 12px;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s;
    cursor: pointer;
    border: none;
}

.btn-pay {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
}

.btn-pay:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3);
}

.btn-download {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
}

.btn-download:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.btn-print {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
}

.btn-print:hover {
    border-color: var(--primary);
    color: var(--primary);
}

/* Invoice Card */
.invoice-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

/* Invoice Header */
.invoice-header {
    background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-card) 100%);
    padding: 40px;
    border-bottom: 1px solid var(--border-color);
}

.invoice-header-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 30px;
}

.company-info {
    flex: 1;
}

.company-logo {
    max-height: 50px;
    max-width: 180px;
    margin-bottom: 16px;
}

.company-name {
    font-size: 22px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 8px;
}

.company-details {
    font-size: 14px;
    color: var(--text-muted);
    line-height: 1.8;
}

.invoice-info {
    text-align: right;
}

.invoice-label {
    font-size: 36px;
    font-weight: 800;
    background: linear-gradient(135deg, var(--primary), #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 8px;
}

.invoice-number {
    font-size: 16px;
    color: var(--text-muted);
    font-family: monospace;
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 30px;
    padding: 30px 40px;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border-color);
}

.info-box {
    padding: 20px;
    background: var(--bg-card);
    border-radius: 12px;
    border: 1px solid var(--border-color);
}

.info-box-title {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-box-title i {
    color: var(--primary);
}

.info-box-content h4 {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.info-box-content p {
    font-size: 14px;
    color: var(--text-muted);
    line-height: 1.6;
    margin: 0;
}

/* Date Cards */
.date-cards {
    display: flex;
    gap: 20px;
    padding: 30px 40px;
    border-bottom: 1px solid var(--border-color);
    flex-wrap: wrap;
}

.date-card {
    flex: 1;
    min-width: 150px;
    padding: 20px;
    background: var(--bg-secondary);
    border-radius: 12px;
    text-align: center;
}

.date-card.overdue {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.date-card-label {
    font-size: 12px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.date-card-value {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-primary);
}

.date-card.overdue .date-card-value {
    color: #ef4444;
}

.date-card-icon {
    font-size: 24px;
    margin-bottom: 10px;
    opacity: 0.5;
}

/* Invoice Items */
.invoice-items {
    padding: 30px 40px;
}

.items-header {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.items-header i {
    color: var(--primary);
}

.items-table {
    width: 100%;
    border-collapse: collapse;
}

.items-table thead tr {
    background: linear-gradient(135deg, #1e293b, #334155);
}

.items-table th {
    padding: 16px 20px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: white;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.items-table th:last-child,
.items-table th:nth-child(3),
.items-table th:nth-child(4) {
    text-align: right;
}

.items-table th:nth-child(2) {
    text-align: center;
}

.items-table tbody tr {
    border-bottom: 1px solid var(--border-color);
    transition: background 0.3s;
}

.items-table tbody tr:hover {
    background: var(--bg-secondary);
}

.items-table td {
    padding: 20px;
    font-size: 15px;
    color: var(--text-primary);
}

.items-table td:last-child,
.items-table td:nth-child(3),
.items-table td:nth-child(4) {
    text-align: right;
    font-family: 'JetBrains Mono', monospace;
}

.items-table td:nth-child(2) {
    text-align: center;
}

.item-description {
    font-weight: 500;
}

.item-type {
    display: inline-block;
    padding: 4px 10px;
    background: var(--bg-secondary);
    border-radius: 6px;
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 6px;
    text-transform: uppercase;
}

/* Invoice Totals */
.invoice-totals {
    padding: 30px 40px;
    background: var(--bg-secondary);
    display: flex;
    justify-content: flex-end;
}

.totals-box {
    width: 350px;
    background: var(--bg-card);
    border-radius: 16px;
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.total-row {
    display: flex;
    justify-content: space-between;
    padding: 16px 24px;
    border-bottom: 1px solid var(--border-color);
}

.total-row:last-child {
    border-bottom: none;
}

.total-row span:first-child {
    color: var(--text-muted);
    font-size: 14px;
}

.total-row span:last-child {
    font-weight: 600;
    color: var(--text-primary);
    font-family: 'JetBrains Mono', monospace;
}

.total-row.discount span:last-child {
    color: #22c55e;
}

.total-row.grand-total {
    background: linear-gradient(135deg, var(--primary), #8b5cf6);
    padding: 20px 24px;
}

.total-row.grand-total span {
    color: white;
    font-size: 18px;
    font-weight: 700;
}

/* Payment Section */
.payment-section {
    padding: 40px;
    text-align: center;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(139, 92, 246, 0.05));
    border-top: 2px dashed var(--border-color);
}

.payment-section h3 {
    font-size: 20px;
    color: var(--text-primary);
    margin-bottom: 12px;
}

.payment-section p {
    color: var(--text-muted);
    margin-bottom: 24px;
    font-size: 15px;
}

.pay-now-btn {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 18px 40px;
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
    border: none;
    border-radius: 14px;
    font-size: 18px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s;
    box-shadow: 0 8px 30px rgba(34, 197, 94, 0.3);
}

.pay-now-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 40px rgba(34, 197, 94, 0.4);
}

.pay-now-btn i {
    font-size: 22px;
}

.payment-methods {
    display: flex;
    justify-content: center;
    gap: 16px;
    margin-top: 24px;
}

.payment-method-icon {
    width: 50px;
    height: 32px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: var(--text-muted);
}

/* Paid Stamp */
.paid-stamp {
    padding: 40px;
    text-align: center;
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.05), rgba(22, 163, 74, 0.05));
    border-top: 2px dashed var(--border-color);
}

.stamp {
    display: inline-flex;
    align-items: center;
    gap: 16px;
    padding: 20px 40px;
    border: 4px solid #22c55e;
    border-radius: 12px;
    transform: rotate(-3deg);
}

.stamp i {
    font-size: 40px;
    color: #22c55e;
}

.stamp-text {
    text-align: left;
}

.stamp-text h4 {
    font-size: 28px;
    font-weight: 800;
    color: #22c55e;
    text-transform: uppercase;
    letter-spacing: 2px;
}

.stamp-text span {
    font-size: 14px;
    color: var(--text-muted);
}

/* Invoice Notes */
.invoice-notes {
    padding: 30px 40px;
    background: var(--bg-secondary);
    border-top: 1px solid var(--border-color);
}

.notes-content {
    padding: 20px;
    background: var(--bg-card);
    border-radius: 12px;
    border-left: 4px solid var(--primary);
}

.notes-content h5 {
    font-size: 14px;
    color: var(--text-primary);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.notes-content p {
    font-size: 14px;
    color: var(--text-muted);
    line-height: 1.6;
    margin: 0;
}

/* Print Styles */
@media print {
    .back-link, .invoice-actions, .action-buttons, .pay-now-btn, .payment-methods { display: none !important; }
    .invoice-card { box-shadow: none; border: 1px solid #ddd; }
    .invoice-header { background: #f8f8f8 !important; }
    body { background: white !important; }
}

/* Responsive */
@media (max-width: 768px) {
    .invoice-header-content { flex-direction: column; }
    .invoice-info { text-align: left; margin-top: 20px; }
    .info-grid { grid-template-columns: 1fr; }
    .date-cards { flex-direction: column; }
    .invoice-totals { padding: 20px; }
    .totals-box { width: 100%; }
    .invoice-items { padding: 20px; overflow-x: auto; }
    .items-table { min-width: 500px; }
    .payment-section, .paid-stamp, .invoice-header, .invoice-notes { padding: 30px 20px; }
    .action-buttons { flex-wrap: wrap; }
    .invoice-actions { flex-direction: column; align-items: flex-start; }
}
</style>

<div class="invoice-page">
    <!-- Back Link -->
    <a href="invoices.php" class="back-link">
        <i class="fas fa-arrow-left"></i>
        Faturalara Dön
    </a>
    
    <!-- Actions Bar -->
    <div class="invoice-actions">
        <div class="invoice-title">
            <h1>Fatura #<?= htmlspecialchars($invoice['invoice_number']) ?></h1>
            <span class="invoice-status <?= $statusInfo['class'] ?>">
                <i class="fas <?= $statusInfo['icon'] ?>"></i>
                <?= $statusInfo['text'] ?>
            </span>
        </div>
        <div class="action-buttons">
            <?php if ($invoice['status'] === 'unpaid'): ?>
                <a href="invoice-pay.php?id=<?= $invoice['id'] ?>" class="btn-action btn-pay">
                    <i class="fas fa-credit-card"></i>
                    Ödeme Yap
                </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn-action btn-print">
                <i class="fas fa-print"></i>
                Yazdır
            </button>
            <a href="invoice-pdf.php?id=<?= $invoice['id'] ?>" class="btn-action btn-download">
                <i class="fas fa-download"></i>
                PDF İndir
            </a>
        </div>
    </div>
    
    <!-- Invoice Card -->
    <div class="invoice-card">
        <!-- Header -->
        <div class="invoice-header">
            <div class="invoice-header-content">
                <div class="company-info">
                    <?php if (!empty($siteLogo)): ?>
                        <img src="/<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($companyName) ?>" class="company-logo">
                    <?php else: ?>
                        <div class="company-name"><?= htmlspecialchars($companyName) ?></div>
                    <?php endif; ?>
                    <div class="company-details">
                        <?php if ($companyAddress): ?>
                            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($companyAddress) ?><br>
                        <?php endif; ?>
                        <?php if ($companyEmail): ?>
                            <i class="fas fa-envelope"></i> <?= htmlspecialchars($companyEmail) ?><br>
                        <?php endif; ?>
                        <?php if ($companyPhone): ?>
                            <i class="fas fa-phone"></i> <?= htmlspecialchars($companyPhone) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="invoice-info">
                    <div class="invoice-label">FATURA</div>
                    <div class="invoice-number"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
                </div>
            </div>
        </div>
        
        <!-- Info Grid -->
        <div class="info-grid">
            <div class="info-box">
                <div class="info-box-title">
                    <i class="fas fa-user"></i>
                    Fatura Adresi
                </div>
                <div class="info-box-content">
                    <h4><?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?></h4>
                    <?php if (!empty($client['company_name'])): ?>
                        <p><?= htmlspecialchars($client['company_name']) ?></p>
                    <?php endif; ?>
                    <p><?= htmlspecialchars($client['email']) ?></p>
                    <?php if (!empty($client['phone'])): ?>
                        <p><?= htmlspecialchars($client['phone']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="info-box">
                <div class="info-box-title">
                    <i class="fas fa-credit-card"></i>
                    Ödeme Bilgileri
                </div>
                <div class="info-box-content">
                    <h4><?= match($invoice['payment_method']) {
                        'bank_transfer' => 'Banka Havalesi / EFT',
                        'credit_card' => 'Kredi Kartı',
                        'paytr' => 'PayTR',
                        default => $invoice['payment_method'] ?? 'Belirtilmemiş'
                    } ?></h4>
                    <p>Para Birimi: <?= htmlspecialchars($invoice['currency']) ?></p>
                    <?php if ($invoice['amount_paid'] > 0): ?>
                        <p>Ödenen: <?= number_format((float)$invoice['amount_paid'], 2) ?> <?= $invoice['currency'] ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Date Cards -->
        <div class="date-cards">
            <div class="date-card">
                <div class="date-card-icon">📅</div>
                <div class="date-card-label">Fatura Tarihi</div>
                <div class="date-card-value"><?= date('d.m.Y', strtotime($invoice['created_at'])) ?></div>
            </div>
            <div class="date-card <?= $isOverdue ? 'overdue' : '' ?>">
                <div class="date-card-icon"><?= $isOverdue ? '⚠️' : '⏰' ?></div>
                <div class="date-card-label">Vade Tarihi</div>
                <div class="date-card-value"><?= date('d.m.Y', strtotime($invoice['due_date'])) ?></div>
            </div>
            <?php if ($invoice['paid_date']): ?>
                <div class="date-card">
                    <div class="date-card-icon">✅</div>
                    <div class="date-card-label">Ödeme Tarihi</div>
                    <div class="date-card-value"><?= date('d.m.Y', strtotime($invoice['paid_date'])) ?></div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Invoice Items -->
        <div class="invoice-items">
            <div class="items-header">
                <i class="fas fa-list"></i>
                Fatura Kalemleri
            </div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Açıklama</th>
                        <th>Adet</th>
                        <th>Birim Fiyat</th>
                        <th>Toplam</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div class="item-description"><?= htmlspecialchars($item['description']) ?></div>
                                <span class="item-type"><?= htmlspecialchars($item['type'] ?? 'Hizmet') ?></span>
                            </td>
                            <td><?= (int)$item['quantity'] ?></td>
                            <td><?= number_format((float)$item['unit_price'], 2) ?> <?= $invoice['currency'] ?></td>
                            <td><?= number_format((float)$item['total'], 2) ?> <?= $invoice['currency'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Totals -->
        <div class="invoice-totals">
            <div class="totals-box">
                <div class="total-row">
                    <span>Ara Toplam</span>
                    <span><?= number_format((float)$invoice['subtotal'], 2) ?> <?= $invoice['currency'] ?></span>
                </div>
                <?php if ($invoice['discount'] > 0): ?>
                    <div class="total-row discount">
                        <span>İndirim</span>
                        <span>-<?= number_format((float)$invoice['discount'], 2) ?> <?= $invoice['currency'] ?></span>
                    </div>
                <?php endif; ?>
                <div class="total-row">
                    <span>KDV (%<?= (int)$invoice['tax_rate'] ?>)</span>
                    <span><?= number_format((float)$invoice['tax'], 2) ?> <?= $invoice['currency'] ?></span>
                </div>
                <div class="total-row grand-total">
                    <span>Genel Toplam</span>
                    <span><?= number_format((float)$invoice['total'], 2) ?> <?= $invoice['currency'] ?></span>
                </div>
            </div>
        </div>
        
        <?php if ($invoice['status'] === 'unpaid'): ?>
            <!-- Payment Section -->
            <div class="payment-section">
                <h3>💳 Ödemenizi Şimdi Tamamlayın</h3>
                <p>Güvenli ödeme sistemiyle hizmetinizi hemen aktif edin</p>
                <a href="invoice-pay.php?id=<?= $invoice['id'] ?>" class="pay-now-btn">
                    <i class="fas fa-lock"></i>
                    Şimdi Öde - <?= number_format((float)$invoice['total'], 2) ?> <?= $invoice['currency'] ?>
                </a>
                <div class="payment-methods">
                    <div class="payment-method-icon">💳</div>
                    <div class="payment-method-icon">🏦</div>
                    <div class="payment-method-icon">💰</div>
                </div>
            </div>
        <?php elseif ($invoice['status'] === 'paid'): ?>
            <!-- Paid Stamp -->
            <div class="paid-stamp">
                <div class="stamp">
                    <i class="fas fa-check-circle"></i>
                    <div class="stamp-text">
                        <h4>Ödendi</h4>
                        <span><?= $invoice['paid_date'] ? date('d.m.Y H:i', strtotime($invoice['paid_date'])) : '' ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($invoice['notes'])): ?>
            <!-- Notes -->
            <div class="invoice-notes">
                <div class="notes-content">
                    <h5><i class="fas fa-sticky-note"></i> Notlar</h5>
                    <p><?= nl2br(htmlspecialchars($invoice['notes'])) ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
