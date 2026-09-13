-- Yapılandırılabilir Seçenek Grupları
CREATE TABLE IF NOT EXISTS `config_option_groups` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Yapılandırılabilir Seçenekler
CREATE TABLE IF NOT EXISTS `config_options` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `option_type` ENUM('dropdown', 'radio', 'checkbox', 'quantity') DEFAULT 'dropdown',
    `required` TINYINT(1) DEFAULT 0,
    `order_priority` INT DEFAULT 0,
    FOREIGN KEY (`group_id`) REFERENCES `config_option_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seçenek Değerleri
CREATE TABLE IF NOT EXISTS `config_option_values` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `option_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `price_monthly` DECIMAL(15,2) DEFAULT 0.00,
    `price_quarterly` DECIMAL(15,2) DEFAULT 0.00,
    `price_semiannually` DECIMAL(15,2) DEFAULT 0.00,
    `price_annually` DECIMAL(15,2) DEFAULT 0.00,
    `setup_fee` DECIMAL(15,2) DEFAULT 0.00,
    `order_priority` INT DEFAULT 0,
    `is_default` TINYINT(1) DEFAULT 0,
    FOREIGN KEY (`option_id`) REFERENCES `config_options`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ürün-Grup İlişkisi (Hangi ürünlerde hangi seçenek grupları kullanılacak)
CREATE TABLE IF NOT EXISTS `product_config_links` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `group_id` INT UNSIGNED NOT NULL,
    UNIQUE KEY `product_group` (`product_id`, `group_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`group_id`) REFERENCES `config_option_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

