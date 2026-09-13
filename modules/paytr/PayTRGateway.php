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
        // test_mode int (1/0) olarak gelebilir, boolean'a çevir
        $this->testMode = (bool)($config['test_mode'] ?? true);
        // PayTR iframe API endpoint (test ve canlı aynı endpoint)
        $this->apiUrl = 'https://www.paytr.com/odeme/api/get-token';
    }
    
    /**
     * Ödeme formu oluştur
     */
    public function createPayment(array $paymentData): array {
        // Gerekli bilgileri kontrol et
        if (empty($this->merchantId) || empty($this->merchantKey) || empty($this->merchantSalt)) {
            return [
                'status' => 'error',
                'message' => 'PayTR modülü yapılandırılmamış. Lütfen admin panelinden PayTR ayarlarını yapılandırın.'
            ];
        }
        
        $merchantOid = $paymentData['merchant_oid'] ?? 'WHMVM-' . time() . '-' . uniqid();
        $paymentAmount = $paymentData['amount'] ?? 0;
        $currency = $paymentData['currency'] ?? 'TL';
        $installmentCount = $paymentData['installment'] ?? 0;
        $paymentType = $paymentData['payment_type'] ?? 'card';
        $non3d = $paymentData['non3d'] ?? 0;
        $non3dTestFailed = $paymentData['non3d_test_failed'] ?? 0;
        $email = trim($paymentData['email'] ?? '');
        
        // Email kontrolü
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'status' => 'error',
                'message' => 'Geçerli bir e-posta adresi gerekli.'
            ];
        }
        
        // Tutar kontrolü
        if ($paymentAmount <= 0) {
            return [
                'status' => 'error',
                'message' => 'Ödeme tutarı 0\'dan büyük olmalıdır.'
            ];
        }
        
        // PayTR minimum tutar kontrolü (0.50 TL)
        if ($paymentAmount < 0.50) {
            return [
                'status' => 'error',
                'message' => 'Minimum ödeme tutarı 0.50 TL\'dir.'
            ];
        }
        
        // Tutarı formatla (PayTR 2 ondalık basamak bekliyor)
        $paymentAmount = number_format((float)$paymentAmount, 2, '.', '');
        
        // Hash oluştur
        $hashStr = $this->merchantId . $merchantOid . $email . $paymentAmount . $this->merchantSalt;
        $paytrToken = base64_encode(hash_hmac('sha256', $hashStr, $this->merchantKey, true));
        
        // Basket formatını kontrol et ve düzenle
        $basket = $paymentData['basket'] ?? [];
        if (empty($basket)) {
            // Eğer basket yoksa, varsayılan bir basket oluştur
            $basket = [
                [
                    'Ürün',
                    $paymentAmount,
                    1
                ]
            ];
        }
        
        // Basket formatını kontrol et (PayTR [ürün_adı, tutar, adet] formatı bekliyor)
        $formattedBasket = [];
        foreach ($basket as $item) {
            if (is_array($item) && count($item) >= 3) {
                $formattedBasket[] = [
                    (string)$item[0], // Ürün adı
                    number_format((float)$item[1], 2, '.', ''), // Tutar
                    (int)$item[2] // Adet
                ];
            }
        }
        
        if (empty($formattedBasket)) {
            $formattedBasket = [['Ürün', $paymentAmount, 1]];
        }
        
        // Post data
        $postData = [
            'merchant_id' => $this->merchantId,
            'merchant_oid' => $merchantOid,
            'email' => $email,
            'payment_amount' => $paymentAmount,
            'paytr_token' => $paytrToken,
            'currency' => $currency,
            'installment_count' => (int)$installmentCount,
            'payment_type' => $paymentType,
            'non_3d' => (int)$non3d,
            'non_3d_test_failed' => (int)$non3dTestFailed,
            'merchant_ok_url' => $paymentData['success_url'] ?? '',
            'merchant_fail_url' => $paymentData['fail_url'] ?? '',
            'user_name' => mb_substr(trim($paymentData['user_name'] ?? ''), 0, 50), // Max 50 karakter
            'user_address' => mb_substr(trim($paymentData['user_address'] ?? ''), 0, 200), // Max 200 karakter
            'user_phone' => mb_substr(trim($paymentData['user_phone'] ?? ''), 0, 20), // Max 20 karakter
            'user_basket' => base64_encode(json_encode($formattedBasket, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'debug_on' => $this->testMode ? 1 : 0,
            'test_mode' => $this->testMode ? 1 : 0,
        ];
        
        // API'ye istek gönder
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'WHMVM-PayTR/1.0');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'status' => 'error',
                'message' => 'Bağlantı hatası: ' . $error,
                'debug' => ['http_code' => $httpCode, 'curl_error' => $error]
            ];
        }
        
        // Yanıtı kontrol et
        if (empty($result)) {
            return [
                'status' => 'error',
                'message' => 'PayTR API\'den yanıt alınamadı. HTTP Kodu: ' . $httpCode,
                'debug' => ['http_code' => $httpCode, 'response' => 'empty']
            ];
        }
        
        $response = json_decode($result, true);
        
        // JSON decode hatası kontrolü
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'status' => 'error',
                'message' => 'PayTR API yanıtı geçersiz format. Yanıt: ' . substr($result, 0, 200),
                'debug' => ['http_code' => $httpCode, 'raw_response' => $result, 'json_error' => json_last_error_msg()]
            ];
        }
        
        // Başarılı yanıt kontrolü
        if ($response && isset($response['status'])) {
            if ($response['status'] === 'success' && !empty($response['token'])) {
                return [
                    'status' => 'success',
                    'token' => $response['token'],
                    'merchant_oid' => $merchantOid,
                    'iframe_url' => 'https://www.paytr.com/odeme/guvenli/' . $response['token']
                ];
            }
            
            // Hata durumu - PayTR API'den gelen hata mesajı
            $errorMessage = 'Bilinmeyen hata';
            if (isset($response['reason']) && !empty($response['reason'])) {
                $errorMessage = $response['reason'];
            } elseif (isset($response['err_no']) && !empty($response['err_no'])) {
                // PayTR hata kodları
                $errorMessages = [
                    '1' => 'Beklenmeyen hata',
                    '2' => 'Geçersiz merchant bilgileri',
                    '3' => 'Geçersiz hash',
                    '4' => 'Geçersiz tutar',
                    '5' => 'Geçersiz e-posta',
                    '6' => 'Geçersiz sepet bilgisi',
                    '7' => 'Geçersiz merchant_oid',
                    '8' => 'Geçersiz para birimi',
                    '9' => 'Geçersiz taksit sayısı',
                    '10' => 'Geçersiz ödeme tipi',
                    '11' => 'Geçersiz callback URL',
                    '12' => 'Geçersiz kullanıcı bilgileri',
                    '13' => 'Test modu hatası',
                    '14' => 'Merchant aktif değil',
                    '15' => 'IP adresi engellendi',
                    '16' => 'Rate limit aşıldı',
                    '17' => 'Bakiyeniz yetersiz',
                    '18' => 'Geçersiz token',
                    '19' => 'Ödeme zaman aşımına uğradı',
                    '20' => 'Geçersiz işlem'
                ];
                $errNo = (string)$response['err_no'];
                $errorMessage = $errorMessages[$errNo] ?? 'PayTR API Hatası (Kod: ' . $errNo . ')';
                if (isset($response['reason']) && !empty($response['reason'])) {
                    $errorMessage .= ': ' . $response['reason'];
                }
            } elseif (isset($response['message']) && !empty($response['message'])) {
                $errorMessage = $response['message'];
            } elseif ($response['status'] === 'failed') {
                $errorMessage = 'PayTR API işlem başarısız';
                if (isset($response['reason'])) {
                    $errorMessage .= ': ' . $response['reason'];
                }
            }
        } else {
            $errorMessage = 'PayTR API\'den geçersiz yanıt alındı';
        }
        
        return [
            'status' => 'error',
            'message' => $errorMessage,
            'debug' => [
                'http_code' => $httpCode,
                'response' => $response,
                'raw_response' => $result,
                'test_mode' => $this->testMode
            ]
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
