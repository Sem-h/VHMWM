<?php
/**
 * SEO Manager - Kurulum Scripti
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';

try {
    // SEO sayfaları tablosu
    Database::query("
        CREATE TABLE IF NOT EXISTS seo_pages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            page_type VARCHAR(50) NOT NULL COMMENT 'product, service, domain, hosting, vds, etc.',
            page_id INT UNSIGNED DEFAULT NULL COMMENT 'İlgili sayfa ID (ürün, hizmet vb.)',
            page_path VARCHAR(255) NOT NULL COMMENT 'Sayfa yolu (örn: /hosting, /vds)',
            custom_slug VARCHAR(255) DEFAULT NULL COMMENT 'Özel URL slug',
            title VARCHAR(255) NOT NULL COMMENT 'Sayfa başlığı',
            meta_description TEXT COMMENT 'Meta açıklama',
            meta_keywords VARCHAR(500) COMMENT 'Meta anahtar kelimeler',
            meta_robots VARCHAR(100) DEFAULT 'index, follow',
            og_title VARCHAR(255) COMMENT 'Open Graph başlık',
            og_description TEXT COMMENT 'Open Graph açıklama',
            og_image VARCHAR(500) COMMENT 'Open Graph resim',
            canonical_url VARCHAR(500) COMMENT 'Canonical URL',
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_path (page_path),
            UNIQUE KEY unique_slug (custom_slug),
            INDEX idx_page_type (page_type),
            INDEX idx_page_id (page_id),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // SEO ayarları tablosu
    Database::query("
        CREATE TABLE IF NOT EXISTS seo_settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Varsayılan SEO ayarları
    $defaultSettings = [
        ['default_title_suffix', ' - ' . SITE_NAME],
        ['default_meta_robots', 'index, follow'],
        ['auto_generate_sitemap', '1'],
        ['enable_og_tags', '1'],
    ];
    
    foreach ($defaultSettings as $setting) {
        try {
            Database::query("
                INSERT IGNORE INTO seo_settings (setting_key, setting_value)
                VALUES (?, ?)
            ", $setting);
        } catch (Exception $e) {
            // Zaten varsa atla
        }
    }
    
    error_log("SEO Manager: Installation completed successfully");
    
} catch (Exception $e) {
    error_log("SEO Manager: Installation failed - " . $e->getMessage());
    throw $e;
}
