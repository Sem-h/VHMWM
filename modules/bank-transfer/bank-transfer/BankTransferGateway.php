<?php
/**
 * Havale/EFT Ödeme Gateway Sınıfı
 */

declare(strict_types=1);

namespace WHMVM\Modules\BankTransfer;

class BankTransferGateway {
    
    private array $config;
    
    public function __construct(array $config) {
        $this->config = $config;
    }
    
    /**
     * Ödeme oluştur - Havale/EFT için ödeme kaydı oluşturur
     */
    public function createPayment(array $paymentData): array {
        // Gerekli bilgileri kontrol et
        if (empty($this->config['banks']) || !is_array($this->config['banks'])) {
            return [
                'status' => 'error',
                'message' => 'Banka bilgileri yapılandırılmamış. Lütfen admin panelinden banka bilgilerini ekleyin.'
            ];
        }
        
        $invoiceId = $paymentData['invoice_id'] ?? 0;
        $amount = $paymentData['amount'] ?? 0;
        $currency = $paymentData['currency'] ?? 'TRY';
        
        if ($invoiceId <= 0) {
            return [
                'status' => 'error',
                'message' => 'Geçersiz fatura ID'
            ];
        }
        
        if ($amount <= 0) {
            return [
                'status' => 'error',
                'message' => 'Ödeme tutarı 0\'dan büyük olmalıdır.'
            ];
        }
        
        // Ödeme referans numarası oluştur
        $referenceNumber = 'BT-' . $invoiceId . '-' . time() . '-' . rand(1000, 9999);
        
        return [
            'status' => 'success',
            'reference_number' => $referenceNumber,
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'currency' => $currency,
            'banks' => $this->config['banks']
        ];
    }
    
    /**
     * Ödeme onayı - Admin tarafından manuel onay
     */
    public function confirmPayment(string $referenceNumber, array $paymentData): array {
        return [
            'status' => 'success',
            'message' => 'Ödeme onaylandı',
            'reference_number' => $referenceNumber
        ];
    }
    
    /**
     * Banka bilgilerini al
     */
    public function getBanks(): array {
        return $this->config['banks'] ?? [];
    }
    
    /**
     * Aktif bankaları al
     */
    public function getActiveBanks(): array {
        $banks = $this->getBanks();
        return array_filter($banks, function($bank) {
            return ($bank['is_active'] ?? true) === true;
        });
    }
}
