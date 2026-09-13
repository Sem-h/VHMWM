<?php
/**
 * Havale/EFT Ödeme Modülü - Ana Dosya
 */

declare(strict_types=1);

namespace WHMVM\Modules\BankTransfer;

class BankTransfer {
    
    public function __construct() {
        $this->init();
    }
    
    private function init(): void {
        // Modül başlatma işlemleri
    }
}

// Modülü başlat
new BankTransfer();
