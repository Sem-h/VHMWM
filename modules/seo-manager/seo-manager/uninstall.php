<?php
/**
 * SEO Manager - Kaldırma Scripti
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';

try {
    // Tabloları sil
    Database::query("DROP TABLE IF EXISTS seo_pages");
    Database::query("DROP TABLE IF EXISTS seo_settings");
    
    error_log("SEO Manager: Uninstallation completed successfully");
    
} catch (Exception $e) {
    error_log("SEO Manager: Uninstallation failed - " . $e->getMessage());
}
