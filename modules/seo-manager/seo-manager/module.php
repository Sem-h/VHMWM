<?php
/**
 * SEO Manager Modülü - Ana Dosya
 */

declare(strict_types=1);

namespace WHMVM\Modules\SeoManager;

class SeoManager {
    
    public function __construct() {
        $this->init();
    }
    
    private function init(): void {
        // Modül başlatma işlemleri
        // Hook'lar burada kaydedilebilir
    }
}

// Modülü başlat
new SeoManager();
