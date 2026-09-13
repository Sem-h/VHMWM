<?php
/**
 * PayTR - Kaldırma Scripti
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';

try {
    // Tabloyu sil
    Database::query("DROP TABLE IF EXISTS paytr_payments");
    
    error_log("PayTR Module: Uninstallation completed successfully");
    
} catch (Exception $e) {
    error_log("PayTR Module: Uninstallation failed - " . $e->getMessage());
}
