<?php
/**
 * PayTR Callback Handler
 * PayTR'den gelen ödeme sonuçlarını işler
 */

declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/modules/paytr/PayTRGateway.php';

// PayTR modülü kontrolü
$paytrModule = Database::fetch("SELECT * FROM modules WHERE slug = 'paytr' AND is_active = 1");
if (!$paytrModule) {
    die('PayTR modülü aktif değil');
}

$paytrConfig = json_decode($paytrModule['config'] ?? '{}', true) ?: [];
$gateway = new \WHMVM\Modules\PayTR\PayTRGateway($paytrConfig);

// POST verilerini al
$postData = $_POST;

// Callback doğrulama
$result = $gateway->verifyCallback($postData);

if ($result['status'] === 'success') {
    // Ödeme başarılı
    $merchantOid = $result['merchant_oid'];
    $totalAmount = $result['total_amount'];
    $transactionId = $result['paytr_transaction_id'] ?? '';
    
    // Ödeme kaydını bul
    $payment = Database::fetch("SELECT * FROM paytr_payments WHERE merchant_oid = ?", [$merchantOid]);
    
    if ($payment) {
        Database::query("
            UPDATE paytr_payments 
            SET status = 'success', 
                paytr_transaction_id = ?,
                callback_data = ?,
                updated_at = NOW()
            WHERE merchant_oid = ?
        ", [
            $transactionId,
            json_encode($postData),
            $merchantOid
        ]);
        
        // Faturayı ödendi olarak işaretle
        $invoice = Database::fetch("SELECT * FROM invoices WHERE id = ?", [$payment['invoice_id']]);
        if ($invoice && $invoice['status'] === 'unpaid') {
            Database::query("
                UPDATE invoices 
                SET status = 'paid', 
                    amount_paid = ?,
                    paid_date = NOW(),
                    payment_method = 'paytr',
                    updated_at = NOW()
                WHERE id = ?
            ", [$totalAmount, $invoice['id']]);
            
            // Sipariş varsa onayla
            if ($payment['order_id']) {
                Database::query("
                    UPDATE orders 
                    SET status = 'approved', 
                        updated_at = NOW()
                    WHERE id = ?
                ", [$payment['order_id']]);
                
                // Sipariş onaylandığında hizmetleri oluştur
                require_once __DIR__ . '/includes/OrderLog.php';
                // Hizmet oluşturma işlemleri burada yapılabilir
            }
            
            // Ödeme başarılı e-postası gönder
            try {
                require_once __DIR__ . '/includes/Mail.php';
                $client = Database::fetch("SELECT * FROM clients WHERE id = ?", [$invoice['client_id']]);
                if ($client) {
                    Mail::sendTemplate('invoice_paid', $client['email'], [
                        'client_name' => $client['first_name'] . ' ' . $client['last_name'],
                        'invoice_id' => $invoice['invoice_number'],
                        'invoice_total' => number_format((float)$totalAmount, 2, ',', '.'),
                        'payment_method' => 'PayTR'
                    ], $client['first_name']);
                }
            } catch (Exception $e) {
                // Mail hatası ödemeyi engellemesin
            }
        }
    }
    
    echo "OK";
} else {
    // Ödeme başarısız
    $merchantOid = $result['merchant_oid'] ?? '';
    $errorMessage = $result['message'] ?? 'Bilinmeyen hata';
    
    if ($merchantOid) {
        Database::query("
            UPDATE paytr_payments 
            SET status = 'failed', 
                error_message = ?,
                callback_data = ?,
                updated_at = NOW()
            WHERE merchant_oid = ?
        ", [
            $errorMessage,
            json_encode($postData),
            $merchantOid
        ]);
    }
    
    echo "FAILED";
}
