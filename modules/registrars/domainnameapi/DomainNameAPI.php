<?php
/**
 * DomainNameAPI Entegrasyon Sınıfı (Wrapper)
 */

// Kütüphaneyi dahil et (Eğer Composer kullanmıyorsak manuel include)
require_once __DIR__ . '/lib/DomainNameAPI_PHPLibrary.php';

use DomainNameApi\DomainNameAPI_PHPLibrary;

class DomainNameAPI
{
    private DomainNameAPI_PHPLibrary $dna;
    private string $username;

    public function __construct(string $username, string $password, bool $testMode = false)
    {
        $this->username = $username;
        try {
            // Kütüphaneyi başlat
            $this->dna = new DomainNameAPI_PHPLibrary($username, $password, $testMode);
        } catch (Exception $e) {
            // Constructor hatası (örn: SOAP yüklü değilse)
            throw new Exception("Kütüphane Hatası: " . $e->getMessage());
        }
    }

    /**
     * API Bağlantısını Test Et ve Bakiyeyi Çek
     */
    public function testConnection(): array
    {
        if (empty($this->username)) {
            return ['success' => false, 'message' => 'Kullanıcı adı eksik.'];
        }

        try {
            // Reseller detaylarını ve bakiyeyi çek
            $response = $this->dna->GetResellerDetails();

            // Başarılı mı?
            if (isset($response['result']) && $response['result'] === 'OK') {
                $balances = $response['balances'] ?? [];

                $balanceDisplay = '';
                $mainCurrency = 'USD'; // Varsayılan

                if (!empty($balances)) {
                    $parts = [];
                    foreach ($balances as $b) {
                        $amount = (float) $b['balance'];
                        $currencyName = $b['currency']; // Örn: US Dollar, Turkish Lira

                        // Kısa kod bulmaya çalış (Basit mapping)
                        $code = 'USD';
                        if (stripos($currencyName, 'Lira') !== false)
                            $code = 'TRY';
                        elseif (stripos(strtolower($currencyName), 'euro') !== false)
                            $code = 'EUR';
                        elseif (stripos(strtolower($currencyName), 'dollar') !== false)
                            $code = 'USD';
                        else
                            $code = $currencyName; // Eğer eşleşmezse tam adı kullan

                        $parts[] = number_format($amount, 2, ',', '.') . ' ' . $code;

                        // Ana para birimi (bakiye > 0 olan ilk)
                        if ($amount > 0 && $balanceDisplay === '') {
                            // İlk >0 bakiyeyi ana bakiye yapabiliriz ama hepsini göstermek daha iyi
                        }
                    }
                    $balanceDisplay = implode(' + ', $parts);
                } else {
                    // Balances dizisi boşsa tekli veriyi kullan
                    // Not: GetResellerDetails'in doğrudan 'balance' ve 'currency' döndürdüğü durumlar için
                    $val = (float) ($response['balance'] ?? 0);
                    $curr = $response['currency'] ?? 'USD';
                    $balanceDisplay = number_format($val, 2, ',', '.') . ' ' . $curr;
                }

                return [
                    'success' => true,
                    'message' => 'API bağlantısı başarılı!',
                    'data' => [
                        'balance' => $balanceDisplay,
                        'currency' => '' // Zaten string içinde var
                    ]
                ];
            }

            // Eğer result OK değilse error mesajını bul
            $errorMessage = $response['error']['Message'] ?? ($response['error']['Code'] ?? 'Bilinmeyen Hata');

            return ['success' => false, 'message' => 'API Yanıtı: ' . $errorMessage];

        } catch (SoapFault $e) {
            return ['success' => false, 'message' => 'SOAP Hatası: ' . $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Sistem Hatası: ' . $e->getMessage()];
        }
    }

    /**
     * Alt kütüphanedeki metodlara doğrudan erişim
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->dna, $name)) {
            return call_user_func_array([$this->dna, $name], $arguments);
        }
        throw new Exception("Method $name bulunamadı.");
    }
}
