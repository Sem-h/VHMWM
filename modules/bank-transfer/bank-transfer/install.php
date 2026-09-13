<?php
/**
 * Havale/EFT - Kurulum Scripti
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';

try {
    // Havale/EFT ödeme kayıtları tablosu
    Database::query("
        CREATE TABLE IF NOT EXISTS bank_transfer_payments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT UNSIGNED NOT NULL,
            reference_number VARCHAR(100) NOT NULL UNIQUE,
            payment_amount DECIMAL(15,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'TRY',
            bank_name VARCHAR(100),
            account_holder VARCHAR(255),
            account_number VARCHAR(100),
            iban VARCHAR(50),
            branch_name VARCHAR(255),
            payment_date DATE,
            receipt_file VARCHAR(255) COMMENT 'Makbuz dosya yolu',
            client_note TEXT COMMENT 'Müşteri notu',
            admin_note TEXT COMMENT 'Admin notu',
            status ENUM('pending', 'confirmed', 'rejected', 'cancelled') DEFAULT 'pending',
            confirmed_by INT UNSIGNED COMMENT 'Onaylayan admin ID',
            confirmed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_invoice (invoice_id),
            INDEX idx_reference (reference_number),
            INDEX idx_status (status),
            INDEX idx_payment_date (payment_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Banka bilgileri tablosu
    Database::query("
        CREATE TABLE IF NOT EXISTS bank_accounts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bank_name VARCHAR(100) NOT NULL,
            account_holder VARCHAR(255) NOT NULL,
            account_number VARCHAR(100) NOT NULL,
            iban VARCHAR(50),
            branch_name VARCHAR(255),
            branch_code VARCHAR(50),
            swift_code VARCHAR(20),
            currency VARCHAR(3) DEFAULT 'TRY',
            display_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (is_active),
            INDEX idx_order (display_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    error_log("Bank Transfer Module: Installation completed successfully");
    
} catch (Exception $e) {
    error_log("Bank Transfer Module: Installation failed - " . $e->getMessage());
    throw $e;
}
