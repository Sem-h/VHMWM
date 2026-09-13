<?php
/**
 * PayTR Ödeme Gateway Sınıfı
 */

declare(strict_types=1);

namespace WHMVM\Modules\PayTR;

class PayTRGateway {
    
    private string $merchantId;
    private string $merchantKey;
    private string $merchantSalt;
    private bool $testMode;
    private string $apiUrl;
    
    public function __construct(array $config) {
        $this->merchantId = $config['merchant_id'] ?? '';
        $this->merchantKey = $config['merchant_key'] ?? '';
        $this->merchantSalt = $config['merchant_salt'] ?? '';
        $this->testMode = $config['test_mode'] ?? true;
        $this->apiUrl = $this->testMode 
            ? 'https://www.paytr.com/odeme/test' 
            : 'https://www.paytr.com/odeme';
    }
    
    /**
     * Ödeme formu oluştur
     */
    public function createPayment(array $paymentData): array {
        $merchantOid = $paymentData['merchant_oid'] ?? 'WHMVM-' . time() . '-' . uniqid();
        $paymentAmount = $paymentData['amount'] ?? 0;
        $currency = $paymentData['currency'] ?? 'TL';
        $installmentCount = $paymentData['installment'] ?? 0;
        $paymentType = $paymentData['payment_type'] ?? 'card';
        $non3d = $paymentData['non3d'] ?? 0;
        $non3dTestFailed = $paymentData['non3d_test_failed'] ?? 0;
        $email = $paymentData['email'] ?? '';
        $paymentAmount = number_format($paymentAmount, 2, '.', '');
        
        // Hash oluştur
        $hashStr = $this->merchantId . $merchantOid . $email . $paymentAmount . $this->merchantSalt;
        $paytrToken = base64_encode(hash_hmac('sha256', $hashStr, $this->merchantKey, true));
        
        // Post data
        $postData = [
            'merchant_id' => $this->merchantId,
            'merchant_oid' => $merchantOid,
            'email' => $email,
            'payment_amount' => $paymentAmount,
            'paytr_token' => $paytrToken,
            'currency' => $currency,
            'installment_count' => $installmentCount,
            'payment_type' => $paymentType,
            'non_3d' => $non3d,
            'non_3d_test_failed' => $non3dTestFailed,
            'merchant_ok_url' => $paymentData['success_url'] ?? '',
            'merchant_fail_url' => $paymentData['fail_url'] ?? '',
            'user_name' => $paymentData['user_name'] ?? '',
            'user_address' => $paymentData['user_address'] ?? '',
            'user_phone' => $paymentData['user_phone'] ?? '',
            'user_basket' => base64_encode(json_encode($paymentData['basket'] ?? [])),
            'debug_on' => $this->testMode ? 1 : 0,
            'test_mode' => $this->testMode ? 1 : 0,
        ];
        
        // API'ye istek gönder
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'status' => 'error',
                'message' => 'Bağlantı hatası: ' . $error
            ];
        }
        
        $response = json_decode($result, true);
        
        if ($response && isset($response['status']) && $response['status'] === 'success') {
            return [
                'status' => 'success',
                'token' => $response['token'] ?? '',
                'merchant_oid' => $merchantOid,
                'iframe_url' => 'https://www.paytr.com/odeme/guvenli/' . ($response['token'] ?? '')
            ];
        }
        
        return [
            'status' => 'error',
            'message' => $response['reason'] ?? 'Bilinmeyen hata'
        ];
    }
    
    /**
     * Callback doğrulama
     */
    public function verifyCallback(array $postData): array {
        $merchantOid = $postData['merchant_oid'] ?? '';
        $status = $postData['status'] ?? '';
        $totalAmount = $postData['total_amount'] ?? '';
        $hash = $postData['hash'] ?? '';
        
        // Hash kontrolü
        $hashStr = $this->merchantId . $merchantOid . $this->merchantSalt;
        $calculatedHash = base64_encode(hash_hmac('sha256', $hashStr, $this->merchantKey, true));
        
        if ($hash !== $calculatedHash) {
            return [
                'status' => 'error',
                'message' => 'Hash doğrulama hatası'
            ];
        }
        
        if ($status === 'success') {
            return [
                'status' => 'success',
                'merchant_oid' => $merchantOid,
                'total_amount' => $totalAmount,
                'payment_type' => $postData['payment_type'] ?? 'card',
                'paytr_transaction_id' => $postData['paytr_tx_id'] ?? ''
            ];
        }
        
        return [
            'status' => 'failed',
            'message' => $postData['failed_reason_code'] ?? 'Ödeme başarısız',
            'merchant_oid' => $merchantOid
        ];
    }
}
