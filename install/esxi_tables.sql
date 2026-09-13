-- WHMVM ESXi Entegrasyonu Tabloları

-- ESXi Sunucuları
CREATE TABLE IF NOT EXISTS esxi_servers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    hostname VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    port INT UNSIGNED DEFAULT 22,
    username VARCHAR(100) NOT NULL,
    password TEXT NOT NULL COMMENT 'Encrypted',
    connection_type ENUM('ssh', 'api') DEFAULT 'ssh',
    is_active TINYINT(1) DEFAULT 1,
    max_vms INT UNSIGNED DEFAULT 0 COMMENT '0 = unlimited',
    notes TEXT,
    last_check DATETIME NULL,
    last_status ENUM('online', 'offline', 'error') DEFAULT 'offline',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_status (last_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hizmet-VM eşleştirmesi (services tablosuna ek alanlar)
-- ALTER TABLE services ADD COLUMN esxi_server_id INT UNSIGNED NULL AFTER server_id;
-- ALTER TABLE services ADD COLUMN esxi_vmid VARCHAR(50) NULL AFTER esxi_server_id;
-- ALTER TABLE services ADD COLUMN vm_hostname VARCHAR(255) NULL AFTER esxi_vmid;

-- ESXi işlem logları
CREATE TABLE IF NOT EXISTS esxi_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    esxi_server_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NULL,
    vmid VARCHAR(50) NULL,
    action VARCHAR(50) NOT NULL COMMENT 'power_on, power_off, reset, etc.',
    status ENUM('success', 'failed', 'pending') DEFAULT 'pending',
    message TEXT,
    initiated_by ENUM('admin', 'client', 'system') DEFAULT 'system',
    admin_id INT UNSIGNED NULL,
    client_id INT UNSIGNED NULL,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_server (esxi_server_id),
    INDEX idx_service (service_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

