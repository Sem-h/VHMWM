<?php
/**
 * PayTR Ödeme Başarı Sayfası
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

$invoiceId = isset($_GET['invoice']) ? (int)$_GET['invoice'] : 0;
$clientId = $_SESSION['client_id'];

// Fatura kontrolü
$invoice = Database::fetch("SELECT * FROM invoices WHERE id = ? AND client_id = ?", [$invoiceId, $clientId]);

if (!$invoice) {
    header('Location: invoices.php');
    exit;
}

$pageTitle = 'Ödeme Başarılı';
$currentPage = 'invoices';

include 'includes/header.php';
?>

<div style="max-width: 600px; margin: 50px auto; text-align: center; padding: 40px; background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
    <div style="font-size: 80px; margin-bottom: 20px;">✅</div>
    <h1 style="font-size: 28px; color: var(--dark); margin-bottom: 10px;">Ödeme Başarılı!</h1>
    <p style="color: #64748b; margin-bottom: 30px;">
        Faturanız başarıyla ödendi. Fatura detaylarını görüntülemek için aşağıdaki butona tıklayın.
    </p>
    <div style="display: flex; gap: 15px; justify-content: center;">
        <a href="invoice-view.php?id=<?= $invoiceId ?>" class="btn btn-primary" 
           style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 10px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; background: linear-gradient(135deg, var(--primary) 0%, #4b91fa 100%); color: white; text-decoration: none;">
            📄 Faturayı Görüntüle
        </a>
        <a href="invoices.php" class="btn btn-outline" 
           style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 10px; font-size: 14px; font-weight: 600; border: 2px solid #e2e8f0; background: white; color: var(--dark); text-decoration: none;">
            📋 Tüm Faturalar
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
