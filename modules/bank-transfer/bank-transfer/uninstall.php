<?php
/**
 * Havale/EFT - Kaldırma Scripti
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';

try {
    // Tabloları sil (verileri korumak isterseniz DROP TABLE yerine TRUNCATE kullanabilirsiniz)
    // Database::query("DROP TABLE IF EXISTS bank_transfer_payments");
    // Database::query("DROP TABLE IF EXISTS bank_accounts");
    
    // Veya sadece modülü devre dışı bırak
    Database::query("UPDATE modules SET is_active = 0 WHERE slug = 'bank-transfer'");
    
    error_log("Bank Transfer Module: Uninstallation completed");
    
} catch (Exception $e) {
    error_log("Bank Transfer Module: Uninstallation failed - " . $e->getMessage());
    throw $e;
}
