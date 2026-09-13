<?php
/**
 * PayTR - Kurulum Scripti
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';

try {
    // PayTR ödeme kayıtları tablosu
    Database::query("
        CREATE TABLE IF NOT EXISTS paytr_payments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT UNSIGNED NOT NULL,
            order_id INT UNSIGNED,
            merchant_oid VARCHAR(100) NOT NULL UNIQUE,
            payment_amount DECIMAL(15,2) NOT NULL,
            payment_type VARCHAR(50) DEFAULT 'card',
            status ENUM('pending', 'success', 'failed', 'cancelled') DEFAULT 'pending',
            paytr_transaction_id VARCHAR(100),
            error_message TEXT,
            callback_data TEXT COMMENT 'PayTR callback verisi',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_invoice (invoice_id),
            INDEX idx_order (order_id),
            INDEX idx_merchant_oid (merchant_oid),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    error_log("PayTR Module: Installation completed successfully");
    
} catch (Exception $e) {
    error_log("PayTR Module: Installation failed - " . $e->getMessage());
    throw $e;
}
