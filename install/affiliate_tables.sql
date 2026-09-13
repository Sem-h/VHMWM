-- WHMVM Satış Ortaklığı (Affiliate) Tabloları
-- Bu SQL dosyası affiliate sisteminin veritabanı tablolarını oluşturur

-- Affiliate kayıtları tablosu
CREATE TABLE IF NOT EXISTS affiliates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL UNIQUE,
    affiliate_code VARCHAR(32) NOT NULL UNIQUE,
    status ENUM('pending', 'active', 'suspended', 'rejected') DEFAULT 'pending',
    commission_rate DECIMAL(5,2) DEFAULT 10.00 COMMENT 'Komisyon oranı (%)',
    commission_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
    payment_method VARCHAR(50) DEFAULT 'bank_transfer',
    payment_details TEXT COMMENT 'Banka bilgileri veya ödeme detayları',
    total_visits INT UNSIGNED DEFAULT 0,
    total_signups INT UNSIGNED DEFAULT 0,
    total_orders INT UNSIGNED DEFAULT 0,
    total_earnings DECIMAL(15,2) DEFAULT 0.00,
    total_withdrawn DECIMAL(15,2) DEFAULT 0.00,
    balance DECIMAL(15,2) DEFAULT 0.00,
    min_withdrawal DECIMAL(10,2) DEFAULT 100.00 COMMENT 'Minimum çekim tutarı',
    approved_at DATETIME NULL,
    approved_by INT UNSIGNED NULL,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_client (client_id),
    INDEX idx_code (affiliate_code),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Affiliate ziyaretleri tablosu
CREATE TABLE IF NOT EXISTS affiliate_visits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    affiliate_id INT UNSIGNED NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    referrer_url TEXT,
    landing_page VARCHAR(500),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_affiliate (affiliate_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Affiliate referansları tablosu (kayıt olanlar)
CREATE TABLE IF NOT EXISTS affiliate_referrals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    affiliate_id INT UNSIGNED NOT NULL,
    referred_client_id INT UNSIGNED NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME NULL,
    INDEX idx_affiliate (affiliate_id),
    INDEX idx_referred (referred_client_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Affiliate komisyonları tablosu
CREATE TABLE IF NOT EXISTS affiliate_commissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    affiliate_id INT UNSIGNED NOT NULL,
    referral_id INT UNSIGNED NULL,
    order_id INT UNSIGNED NULL,
    invoice_id INT UNSIGNED NULL,
    amount DECIMAL(15,2) NOT NULL COMMENT 'Sipariş/Fatura tutarı',
    commission_rate DECIMAL(5,2) NOT NULL COMMENT 'Uygulanan komisyon oranı',
    commission_amount DECIMAL(15,2) NOT NULL COMMENT 'Kazanılan komisyon',
    status ENUM('pending', 'approved', 'paid', 'cancelled') DEFAULT 'pending',
    description VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME NULL,
    paid_at DATETIME NULL,
    INDEX idx_affiliate (affiliate_id),
    INDEX idx_referral (referral_id),
    INDEX idx_order (order_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Affiliate çekim talepleri tablosu
CREATE TABLE IF NOT EXISTS affiliate_withdrawals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    affiliate_id INT UNSIGNED NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_details TEXT,
    status ENUM('pending', 'processing', 'completed', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    processed_by INT UNSIGNED NULL,
    processed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_affiliate (affiliate_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Affiliate ayarları (settings tablosuna eklenecek)
INSERT INTO settings (setting_key, setting_value) VALUES 
('affiliate_enabled', '1'),
('affiliate_auto_approve', '0'),
('affiliate_default_commission', '10'),
('affiliate_commission_type', 'percentage'),
('affiliate_min_withdrawal', '100'),
('affiliate_cookie_days', '30'),
('affiliate_require_approval', '1')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

