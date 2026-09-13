<?php
/**
 * WHMVM - Havale/EFT Ödeme Bildirimi
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

$invoiceId = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
$clientId = $_SESSION['client_id'];
$message = '';
$messageType = 'success';

// Fatura kontrolü
$invoice = Database::fetch("
    SELECT i.*, c.first_name, c.last_name, c.email
    FROM invoices i
    LEFT JOIN clients c ON i.client_id = c.id
    WHERE i.id = ? AND i.client_id = ?
", [$invoiceId, $clientId]);

if (!$invoice) {
    header('Location: invoices.php');
    exit;
}

// Ödeme bildirimi işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gateway']) && $_POST['gateway'] === 'bank-transfer') {
    try {
        $referenceNumber = $_POST['reference_number'] ?? '';
        $clientNote = $_POST['client_note'] ?? '';
        
        // İlk aktif bankayı al (varsayılan)
        $bank = Database::fetch("SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY display_order LIMIT 1");
        
        // Ödeme kaydı oluştur
        Database::query("
            INSERT INTO bank_transfer_payments 
            (invoice_id, reference_number, payment_amount, currency, bank_name, account_holder, 
             account_number, iban, branch_name, payment_date, client_note, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ", [
            $invoiceId,
            $referenceNumber,
            $invoice['total'],
            $invoice['currency'],
            $bank ? $bank['bank_name'] : null,
            $bank ? $bank['account_holder'] : null,
            $bank ? $bank['account_number'] : null,
            $bank ? ($bank['iban'] ?? null) : null,
            $bank ? ($bank['branch_name'] ?? null) : null,
            date('Y-m-d'),
            $clientNote
        ]);
        
        $message = 'Ödeme bildiriminiz başarıyla gönderildi. Ödemeniz kontrol edildikten sonra fatura otomatik olarak ödenmiş sayılacaktır.';
        
    } catch (Exception $e) {
        $message = 'Hata: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

$pageTitle = 'Ödeme Bildirimi';
$currentPage = 'invoices';

include 'includes/header.php';
?>

<div style="max-width: 800px; margin: 40px auto; padding: 0 20px;">
    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>" style="margin-bottom: 30px;">
            <?php if ($messageType === 'success'): ?>
                <i class="fas fa-check-circle"></i>
            <?php else: ?>
                <i class="fas fa-exclamation-circle"></i>
            <?php endif; ?>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($messageType === 'success'): ?>
        <div style="text-align: center; padding: 40px 20px;">
            <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #22c55e, #16a34a); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
                <i class="fas fa-check" style="font-size: 40px; color: white;"></i>
            </div>
            <h2 style="font-size: 24px; font-weight: 700; color: #1e293b; margin-bottom: 12px;">
                Ödeme Bildirimi Alındı
            </h2>
            <p style="color: #64748b; margin-bottom: 30px; line-height: 1.8;">
                Ödemeniz kontrol edildikten sonra fatura otomatik olarak ödenmiş sayılacaktır.<br>
                İşlem genellikle 1-2 iş günü içinde tamamlanır.
            </p>
            <div style="display: flex; gap: 12px; justify-content: center;">
                <a href="invoices.php" class="btn btn-primary">
                    <i class="fas fa-file-invoice"></i> Faturalarıma Dön
                </a>
                <a href="dashboard.php" class="btn btn-outline">
                    <i class="fas fa-home"></i> Ana Sayfa
                </a>
            </div>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 40px 20px;">
            <a href="invoice-pay.php?id=<?= $invoiceId ?>&gateway=bank-transfer" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Geri Dön
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
