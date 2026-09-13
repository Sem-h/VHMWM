<?php
/**
 * Örnek Modül - Ana Dosya
 * 
 * Bu dosya modül yüklendiğinde otomatik olarak çalıştırılır.
 */

declare(strict_types=1);

// Modül namespace (önerilir)
namespace WHMVM\Modules\ExampleModule;

/**
 * Örnek Modül Sınıfı
 */
class ExampleModule {
    
    public function __construct() {
        $this->init();
    }
    
    /**
     * Modül başlatma
     */
    private function init(): void {
        // Modül başlatma işlemleri buraya gelir
        // Örnek: Hook'ları kaydet, filter'ları ekle, vb.
        
        // Admin menüsüne ekle (eğer gerekirse)
        // add_action('admin_menu', [$this, 'addAdminMenu']);
        
        // Müşteri menüsüne ekle (eğer gerekirse)
        // add_action('client_menu', [$this, 'addClientMenu']);
    }
    
    /**
     * Admin menüsüne öğe ekle
     */
    public function addAdminMenu(): void {
        // Admin panel menüsüne öğe ekleme işlemleri
    }
    
    /**
     * Müşteri menüsüne öğe ekle
     */
    public function addClientMenu(): void {
        // Müşteri paneli menüsüne öğe ekleme işlemleri
    }
}

// Modülü başlat
new ExampleModule();
