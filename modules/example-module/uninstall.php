<?php
/**
 * Örnek Modül - Kaldırma Scripti
 * 
 * Bu dosya modül silindiğinde çalıştırılır.
 * Veritabanı tabloları, ayarlar vb. burada temizlenir.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Settings.php';

try {
    // Örnek: Veritabanı tablosunu sil
    Database::query("DROP TABLE IF EXISTS example_module_data");
    
    // Örnek: Ayarları sil
    Settings::delete('example_module_setting1', 'example-module');
    Settings::delete('example_module_setting2', 'example-module');
    
    // Kaldırma başarılı
    error_log("Example Module: Uninstallation completed successfully");
    
} catch (Exception $e) {
    // Kaldırma hatası
    error_log("Example Module: Uninstallation failed - " . $e->getMessage());
    // Hata olsa bile devam et (modül zaten silinecek)
}
