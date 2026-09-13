<?php
/**
 * Örnek Modül - Kurulum Scripti
 * 
 * Bu dosya modül yüklendiğinde bir kez çalıştırılır.
 * Veritabanı tabloları, varsayılan ayarlar vb. burada oluşturulur.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Settings.php';

try {
    // Örnek: Veritabanı tablosu oluştur
    Database::query("
        CREATE TABLE IF NOT EXISTS example_module_data (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Örnek: Varsayılan ayarları ekle
    Settings::set('example_module_setting1', 'default_value', 'example-module');
    Settings::set('example_module_setting2', '100', 'example-module');
    
    // Kurulum başarılı
    error_log("Example Module: Installation completed successfully");
    
} catch (Exception $e) {
    // Kurulum hatası
    error_log("Example Module: Installation failed - " . $e->getMessage());
    throw $e;
}
