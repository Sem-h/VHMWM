<?php
/**
 * NetGSM SMS Modülü - Ana Sınıf
 * 
 * @version 1.0.0
 * @author WHMVM
 */

class NetGSMGateway
{
    private string $usercode;
    private string $password;
    private string $msgheader;
    private bool $testMode;

    private const API_URL = 'https://api.netgsm.com.tr/sms/send/get';
    private const BULK_API_URL = 'https://api.netgsm.com.tr/sms/send/xml';

    // Hata kodları
    private const ERROR_CODES = [
        '00' => 'Mesaj gönderiliyor',
        '01' => 'Mesaj gönderildi',
        '20' => 'Mesajda hata var, parametre eksik',
        '30' => 'Geçersiz kullanıcı adı, şifre veya IP adresi',
        '40' => 'Mesaj başlığı sistemde kayıtlı değil',
        '50' => 'Abone hesabı için EFT/havale ile ödeme yapılmamış',
        '51' => 'Abone hesabı için EFT/havale ile ödeme yapılmamış',
        '60' => 'Abone hesabı bulunamadı',
        '70' => 'Hatalı sorgulama',
        '80' => 'Gönderim tarih hatası',
        '85' => 'Mükerrer kayıt engeli',
    ];

    public function __construct(array $config = [])
    {
        $this->usercode = $config['usercode'] ?? '';
        $this->password = $config['password'] ?? '';
        $this->msgheader = $config['msgheader'] ?? '';
        $this->testMode = (bool) ($config['test_mode'] ?? true);
    }

    /**
     * Tek SMS Gönder
     */
    public function sendSMS(string $phone, string $message): array
    {
        // Telefon numarasını formatla
        $phone = $this->formatPhone($phone);

        if ($this->testMode) {
            return [
                'success' => true,
                'test_mode' => true,
                'message' => 'Test modu - SMS gönderilmedi',
                'phone' => $phone,
                'content' => $message
            ];
        }

        $params = [
            'usercode' => $this->usercode,
            'password' => $this->password,
            'gsmno' => $phone,
            'message' => $message,
            'msgheader' => $this->msgheader,
        ];

        $response = $this->makeRequest(self::API_URL, $params);

        return $this->parseResponse($response, $phone);
    }

    /**
     * Toplu SMS Gönder
     */
    public function sendBulkSMS(array $phones, string $message): array
    {
        $results = [];
        $successCount = 0;
        $failCount = 0;

        if ($this->testMode) {
            foreach ($phones as $phone) {
                $results[] = [
                    'phone' => $this->formatPhone($phone),
                    'success' => true,
                    'test_mode' => true
                ];
                $successCount++;
            }

            return [
                'success' => true,
                'test_mode' => true,
                'total' => count($phones),
                'sent' => $successCount,
                'failed' => 0,
                'results' => $results
            ];
        }

        // XML formatında toplu gönderim
        $xml = $this->buildBulkXML($phones, $message);
        $response = $this->makeXMLRequest(self::BULK_API_URL, $xml);

        // Basit implementasyon - her numara için ayrı istek
        foreach ($phones as $phone) {
            $result = $this->sendSMS($phone, $message);
            $results[] = [
                'phone' => $phone,
                'success' => $result['success'],
                'message' => $result['message'] ?? ''
            ];

            if ($result['success']) {
                $successCount++;
            } else {
                $failCount++;
            }

            // Rate limiting
            usleep(100000); // 100ms
        }

        return [
            'success' => $failCount === 0,
            'total' => count($phones),
            'sent' => $successCount,
            'failed' => $failCount,
            'results' => $results
        ];
    }

    /**
     * Bakiye Sorgula
     */
    public function getBalance(): array
    {
        $url = 'https://api.netgsm.com.tr/balance/list/get';

        $params = [
            'usercode' => $this->usercode,
            'password' => $this->password,
        ];

        if ($this->testMode) {
            return [
                'success' => true,
                'test_mode' => true,
                'balance' => '1000',
                'message' => 'Test modu'
            ];
        }

        $response = $this->makeRequest($url, $params);

        if (is_numeric($response)) {
            return [
                'success' => true,
                'balance' => $response
            ];
        }

        return [
            'success' => false,
            'message' => 'Bakiye sorgulanamadı'
        ];
    }

    /**
     * Telefon numarasını formatla
     */
    private function formatPhone(string $phone): string
    {
        // Sadece rakamları al
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Başındaki 0'ı kaldır
        if (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }

        // Türkiye kodu ekle (yoksa)
        if (!str_starts_with($phone, '90') && strlen($phone) === 10) {
            $phone = '90' . $phone;
        }

        return $phone;
    }

    /**
     * HTTP isteği yap
     */
    private function makeRequest(string $url, array $params): string
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url . '?' . http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return 'CURL_ERROR: ' . $error;
        }

        return $response ?: 'EMPTY_RESPONSE';
    }

    /**
     * XML isteği yap
     */
    private function makeXMLRequest(string $url, string $xml): string
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=UTF-8'
            ]
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response ?: 'EMPTY_RESPONSE';
    }

    /**
     * Toplu SMS için XML oluştur
     */
    private function buildBulkXML(array $phones, string $message): string
    {
        $gsmNumbers = '';
        foreach ($phones as $phone) {
            $gsmNumbers .= '<no>' . $this->formatPhone($phone) . '</no>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>
        <mainbody>
            <header>
                <company dession="1">Netgsm</company>
                <usercode>' . htmlspecialchars($this->usercode) . '</usercode>
                <password>' . htmlspecialchars($this->password) . '</password>
                <type>1:n</type>
                <msgheader>' . htmlspecialchars($this->msgheader) . '</msgheader>
            </header>
            <body>
                <msg><![CDATA[' . $message . ']]></msg>
                ' . $gsmNumbers . '
            </body>
        </mainbody>';
    }

    /**
     * API yanıtını parse et
     */
    private function parseResponse(string $response, string $phone): array
    {
        $response = trim($response);

        // Başarı durumu
        if (str_starts_with($response, '00') || str_starts_with($response, '01')) {
            return [
                'success' => true,
                'message' => 'SMS başarıyla gönderildi',
                'phone' => $phone,
                'job_id' => substr($response, 3) ?: null
            ];
        }

        // Hata durumu
        $errorCode = substr($response, 0, 2);
        $errorMessage = self::ERROR_CODES[$errorCode] ?? 'Bilinmeyen hata: ' . $response;

        return [
            'success' => false,
            'message' => $errorMessage,
            'phone' => $phone,
            'error_code' => $errorCode
        ];
    }

    /**
     * Bağlantı testi
     */
    public function testConnection(): array
    {
        $balance = $this->getBalance();

        if ($balance['success']) {
            return [
                'success' => true,
                'message' => 'Bağlantı başarılı',
                'balance' => $balance['balance'] ?? 'N/A'
            ];
        }

        return [
            'success' => false,
            'message' => 'Bağlantı başarısız: ' . ($balance['message'] ?? 'Bilinmeyen hata')
        ];
    }
}
