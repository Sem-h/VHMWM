-- ---------------------------------------------------------------
-- VHM - veritabanı şeması
--
-- Bu dosya YALNIZCA tablo yapısını içerir: tablo, sütun, indeks ve
-- kısıtlar. Hiçbir satır verisi bulunmaz (ürün, ayar, çeviri, müşteri
-- ya da kimlik bilgisi yoktur).
--
-- Yeniden üretmek için:  php install/sema-disari-aktar.php
--
-- Kurulum:  mysql -u root vhm < install/whmvm-kurulum.sql
-- ---------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `activity_log`;

CREATE TABLE `activity_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_type` enum('admin','client','system') NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `related_id` int(10) unsigned DEFAULT NULL,
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_data`)),
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_type`,`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_related` (`related_type`,`related_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `admins`;

CREATE TABLE `admins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `role` enum('super_admin','admin','support','sales') DEFAULT 'admin',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `affiliate_commissions`;

CREATE TABLE `affiliate_commissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `affiliate_id` int(10) unsigned NOT NULL,
  `referral_id` int(10) unsigned DEFAULT NULL,
  `order_id` int(10) unsigned DEFAULT NULL,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `commission_rate` decimal(5,2) NOT NULL,
  `commission_amount` decimal(15,2) NOT NULL,
  `status` enum('pending','approved','paid','cancelled') DEFAULT 'pending',
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_affiliate` (`affiliate_id`),
  KEY `idx_referral` (`referral_id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `affiliate_referrals`;

CREATE TABLE `affiliate_referrals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `affiliate_id` int(10) unsigned NOT NULL,
  `referred_client_id` int(10) unsigned NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_affiliate` (`affiliate_id`),
  KEY `idx_referred` (`referred_client_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `affiliate_visits`;

CREATE TABLE `affiliate_visits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `affiliate_id` int(10) unsigned NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `referrer_url` text DEFAULT NULL,
  `landing_page` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_affiliate` (`affiliate_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `affiliate_withdrawals`;

CREATE TABLE `affiliate_withdrawals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `affiliate_id` int(10) unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_details` text DEFAULT NULL,
  `status` enum('pending','processing','completed','rejected') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `processed_by` int(10) unsigned DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_affiliate` (`affiliate_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `affiliates`;

CREATE TABLE `affiliates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `affiliate_code` varchar(32) NOT NULL,
  `status` enum('pending','active','suspended','rejected') DEFAULT 'pending',
  `commission_rate` decimal(5,2) DEFAULT 10.00,
  `commission_type` enum('percentage','fixed') DEFAULT 'percentage',
  `payment_method` varchar(50) DEFAULT 'bank_transfer',
  `payment_details` text DEFAULT NULL,
  `total_visits` int(10) unsigned DEFAULT 0,
  `total_signups` int(10) unsigned DEFAULT 0,
  `total_orders` int(10) unsigned DEFAULT 0,
  `total_earnings` decimal(15,2) DEFAULT 0.00,
  `total_withdrawn` decimal(15,2) DEFAULT 0.00,
  `balance` decimal(15,2) DEFAULT 0.00,
  `min_withdrawal` decimal(10,2) DEFAULT 100.00,
  `approved_at` datetime DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_id` (`client_id`),
  UNIQUE KEY `affiliate_code` (`affiliate_code`),
  KEY `idx_client` (`client_id`),
  KEY `idx_code` (`affiliate_code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `bank_accounts`;

CREATE TABLE `bank_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bank_name` varchar(100) NOT NULL,
  `account_holder` varchar(255) NOT NULL,
  `account_number` varchar(100) NOT NULL,
  `iban` varchar(50) DEFAULT NULL,
  `branch_name` varchar(255) DEFAULT NULL,
  `branch_code` varchar(50) DEFAULT NULL,
  `swift_code` varchar(20) DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'TRY',
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `bank_transfer_payments`;

CREATE TABLE `bank_transfer_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) unsigned NOT NULL,
  `reference_number` varchar(100) NOT NULL,
  `payment_amount` decimal(15,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'TRY',
  `bank_name` varchar(100) DEFAULT NULL,
  `account_holder` varchar(255) DEFAULT NULL,
  `account_number` varchar(100) DEFAULT NULL,
  `iban` varchar(50) DEFAULT NULL,
  `branch_name` varchar(255) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `receipt_file` varchar(255) DEFAULT NULL COMMENT 'Makbuz dosya yolu',
  `client_note` text DEFAULT NULL COMMENT 'Müşteri notu',
  `admin_note` text DEFAULT NULL COMMENT 'Admin notu',
  `status` enum('pending','confirmed','rejected','cancelled') DEFAULT 'pending',
  `confirmed_by` int(10) unsigned DEFAULT NULL COMMENT 'Onaylayan admin ID',
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_number` (`reference_number`),
  KEY `idx_invoice` (`invoice_id`),
  KEY `idx_reference` (`reference_number`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cancellation_requests`;

CREATE TABLE `cancellation_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `cancel_type` enum('immediate','end_of_billing') DEFAULT 'end_of_billing',
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_id` int(10) unsigned DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_service` (`service_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `client_logs`;

CREATE TABLE `client_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `clients`;

CREATE TABLE `clients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_type` enum('individual','corporate') DEFAULT 'individual',
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `tax_office` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postcode` varchar(20) DEFAULT NULL,
  `country` char(2) DEFAULT 'TR',
  `language` varchar(10) DEFAULT 'tr',
  `currency` varchar(3) DEFAULT 'TRY',
  `credit_balance` decimal(15,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `email_verified` tinyint(1) DEFAULT 0,
  `email_verify_token` varchar(100) DEFAULT NULL,
  `password_reset_token` varchar(100) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_active` (`is_active`),
  KEY `idx_country` (`country`),
  FULLTEXT KEY `idx_search` (`first_name`,`last_name`,`email`,`company_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `config_option_groups`;

CREATE TABLE `config_option_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `config_option_values`;

CREATE TABLE `config_option_values` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `option_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `price_monthly` decimal(15,2) DEFAULT 0.00,
  `price_quarterly` decimal(15,2) DEFAULT 0.00,
  `price_semiannually` decimal(15,2) DEFAULT 0.00,
  `price_annually` decimal(15,2) DEFAULT 0.00,
  `setup_fee` decimal(15,2) DEFAULT 0.00,
  `order_priority` int(11) DEFAULT 0,
  `is_default` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `option_id` (`option_id`),
  CONSTRAINT `config_option_values_ibfk_1` FOREIGN KEY (`option_id`) REFERENCES `config_options` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `config_options`;

CREATE TABLE `config_options` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `option_type` enum('dropdown','radio','checkbox','quantity') DEFAULT 'dropdown',
  `required` tinyint(1) DEFAULT 0,
  `order_priority` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  CONSTRAINT `config_options_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `config_option_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cron_tasks`;

CREATE TABLE `cron_tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `command` varchar(255) NOT NULL,
  `schedule` varchar(100) NOT NULL COMMENT 'Cron expression',
  `is_active` tinyint(1) DEFAULT 1,
  `last_run` datetime DEFAULT NULL,
  `next_run` datetime DEFAULT NULL,
  `last_status` enum('success','failed','running') DEFAULT 'success',
  `last_output` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_active` (`is_active`),
  KEY `idx_next_run` (`next_run`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `currencies`;

CREATE TABLE `currencies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(3) NOT NULL,
  `name` varchar(100) NOT NULL,
  `symbol` varchar(10) NOT NULL,
  `format` tinyint(4) DEFAULT 1 COMMENT '1: $1,234.56, 2: 1.234,56$',
  `exchange_rate` decimal(10,6) DEFAULT 1.000000,
  `is_default` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_active` (`is_active`),
  KEY `idx_default` (`is_default`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `departments`;

CREATE TABLE `departments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `is_hidden` tinyint(1) DEFAULT 0,
  `clients_only` tinyint(1) DEFAULT 0,
  `order_priority` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_priority`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `domain_pricing`;

CREATE TABLE `domain_pricing` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `extension` varchar(50) NOT NULL,
  `register_1yr` decimal(15,2) DEFAULT NULL,
  `register_2yr` decimal(15,2) DEFAULT NULL,
  `register_3yr` decimal(15,2) DEFAULT NULL,
  `register_5yr` decimal(15,2) DEFAULT NULL,
  `register_10yr` decimal(15,2) DEFAULT NULL,
  `transfer_price` decimal(15,2) DEFAULT NULL,
  `renew_1yr` decimal(15,2) DEFAULT NULL,
  `renew_2yr` decimal(15,2) DEFAULT NULL,
  `renew_3yr` decimal(15,2) DEFAULT NULL,
  `renew_5yr` decimal(15,2) DEFAULT NULL,
  `renew_10yr` decimal(15,2) DEFAULT NULL,
  `grace_period_days` int(11) DEFAULT 0,
  `redemption_period_days` int(11) DEFAULT 0,
  `id_protection_price` decimal(15,2) DEFAULT NULL,
  `epp_required` tinyint(1) DEFAULT 1,
  `registrar` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `currency` varchar(3) DEFAULT 'TRY',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_extension` (`extension`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `domains`;

CREATE TABLE `domains` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `order_id` int(10) unsigned DEFAULT NULL,
  `domain` varchar(255) NOT NULL,
  `registrar` varchar(100) DEFAULT NULL,
  `registration_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `status` enum('pending','active','pending_transfer','expired','cancelled') DEFAULT 'pending',
  `type` enum('register','transfer','external') DEFAULT 'register',
  `auto_renew` tinyint(1) DEFAULT 1,
  `id_protection` tinyint(1) DEFAULT 0,
  `nameservers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`nameservers`)),
  `dns_management` tinyint(1) DEFAULT 0,
  `email_forwarding` tinyint(1) DEFAULT 0,
  `amount` decimal(15,2) DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'TRY',
  `epp_code` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `registrar_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`registrar_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_domain` (`domain`),
  KEY `order_id` (`order_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_expiry` (`expiry_date`),
  CONSTRAINT `domains_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `domains_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `email_logs`;

CREATE TABLE `email_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `to_email` varchar(255) NOT NULL,
  `from_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `template_name` varchar(100) DEFAULT NULL,
  `status` enum('sent','failed','pending') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `related_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_to_email` (`to_email`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `email_templates`;

CREATE TABLE `email_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `variables` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_category` (`category`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `esxi_logs`;

CREATE TABLE `esxi_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `esxi_server_id` int(10) unsigned NOT NULL,
  `service_id` int(10) unsigned DEFAULT NULL,
  `vmid` varchar(50) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `status` enum('success','failed','pending') DEFAULT 'pending',
  `message` text DEFAULT NULL,
  `initiated_by` enum('admin','client','system') DEFAULT 'system',
  `admin_id` int(10) unsigned DEFAULT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_server` (`esxi_server_id`),
  KEY `idx_service` (`service_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `esxi_servers`;

CREATE TABLE `esxi_servers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `port` int(10) unsigned DEFAULT 22,
  `username` varchar(100) NOT NULL,
  `password` text NOT NULL,
  `connection_type` enum('ssh','api') DEFAULT 'ssh',
  `is_active` tinyint(1) DEFAULT 1,
  `max_vms` int(10) unsigned DEFAULT 0,
  `notes` text DEFAULT NULL,
  `last_check` datetime DEFAULT NULL,
  `last_status` enum('online','offline','error') DEFAULT 'offline',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_status` (`last_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hizmet_mahalleleri`;

CREATE TABLE `hizmet_mahalleleri` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `il` varchar(50) NOT NULL DEFAULT 'Bursa',
  `ilce` varchar(50) NOT NULL,
  `mahalle` varchar(100) NOT NULL,
  `api_id` int(10) unsigned DEFAULT NULL,
  `il_id` int(10) unsigned DEFAULT NULL,
  `ilce_id` int(10) unsigned DEFAULT NULL,
  `posta_kodu` varchar(10) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_mahalle` (`il`,`ilce`,`mahalle`),
  UNIQUE KEY `u_api` (`api_id`),
  KEY `k_aktif` (`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hizmet_sokaklari`;

CREATE TABLE `hizmet_sokaklari` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mahalle_id` int(10) unsigned NOT NULL,
  `sokak` varchar(150) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_sokak` (`mahalle_id`,`sokak`),
  KEY `k_mahalle` (`mahalle_id`,`is_active`),
  CONSTRAINT `fk_sokak_mahalle` FOREIGN KEY (`mahalle_id`) REFERENCES `hizmet_mahalleleri` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=518 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `integration_logs`;

CREATE TABLE `integration_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `integration` varchar(100) NOT NULL,
  `action` varchar(100) NOT NULL,
  `request` text DEFAULT NULL,
  `response` text DEFAULT NULL,
  `status` enum('success','error','warning') DEFAULT 'success',
  `execution_time` float DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_integration` (`integration`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `invoice_items`;

CREATE TABLE `invoice_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) unsigned NOT NULL,
  `service_id` int(10) unsigned DEFAULT NULL,
  `type` enum('service','domain','addon','promo','late_fee','other') DEFAULT 'service',
  `description` varchar(255) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(15,2) NOT NULL,
  `discount` decimal(15,2) DEFAULT 0.00,
  `tax` decimal(15,2) DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  KEY `idx_invoice` (`invoice_id`),
  CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_items_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `invoices`;

CREATE TABLE `invoices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `status` enum('draft','unpaid','paid','cancelled','refunded','collections') DEFAULT 'unpaid',
  `subtotal` decimal(15,2) NOT NULL,
  `discount` decimal(15,2) DEFAULT 0.00,
  `tax` decimal(15,2) DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL,
  `amount_paid` decimal(15,2) DEFAULT 0.00,
  `currency` varchar(3) DEFAULT 'TRY',
  `due_date` date NOT NULL,
  `paid_date` datetime DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `idx_client` (`client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_paid_date` (`paid_date`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `kesif_talepleri`;

CREATE TABLE `kesif_talepleri` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `paket_id` int(10) unsigned DEFAULT NULL,
  `paket_adi` varchar(150) DEFAULT NULL,
  `ad_soyad` varchar(120) NOT NULL,
  `telefon` varchar(30) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `isletme_turu` varchar(60) DEFAULT NULL,
  `il` varchar(50) NOT NULL,
  `ilce` varchar(50) NOT NULL,
  `mahalle` varchar(100) NOT NULL,
  `sokak` varchar(150) DEFAULT NULL,
  `bina_no` varchar(30) DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `durum` enum('yeni','arandi','kesif_yapildi','teklif_verildi','kapandi') NOT NULL DEFAULT 'yeni',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `k_durum` (`durum`),
  KEY `k_tarih` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `languages`;

CREATE TABLE `languages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(5) NOT NULL,
  `name` varchar(64) NOT NULL,
  `native_name` varchar(64) NOT NULL,
  `flag` varchar(16) DEFAULT NULL,
  `direction` enum('ltr','rtl') NOT NULL DEFAULT 'ltr',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code` (`code`),
  KEY `idx_aktif` (`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `menu_items`;

CREATE TABLE `menu_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `menu_type` enum('main','footer','social') NOT NULL DEFAULT 'main',
  `parent_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `url` varchar(500) NOT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `target` enum('_self','_blank') DEFAULT '_self',
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_menu_type` (`menu_type`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_order` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `modules`;

CREATE TABLE `modules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `version` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `author` varchar(100) DEFAULT NULL,
  `author_url` varchar(255) DEFAULT NULL,
  `category` varchar(50) DEFAULT 'general' COMMENT 'payment, seo, integration, general',
  `is_active` tinyint(1) DEFAULT 0,
  `install_path` varchar(255) NOT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `dependencies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dependencies`)),
  `installed_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_active` (`is_active`),
  KEY `idx_slug` (`slug`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `order_items`;

CREATE TABLE `order_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `domain` varchar(255) DEFAULT NULL,
  `billing_cycle` enum('monthly','quarterly','semiannually','annually','biennially','triennially','onetime') DEFAULT 'monthly',
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(15,2) NOT NULL,
  `setup_fee` decimal(15,2) DEFAULT 0.00,
  `discount` decimal(15,2) DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `idx_order` (`order_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `order_logs`;

CREATE TABLE `order_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned DEFAULT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `admin_id` int(10) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `order_logs_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `orders`;

CREATE TABLE `orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) NOT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `status` enum('pending','processing','active','fraud','cancelled') DEFAULT 'pending',
  `subtotal` decimal(15,2) NOT NULL,
  `discount` decimal(15,2) DEFAULT 0.00,
  `tax` decimal(15,2) DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'TRY',
  `payment_method` varchar(50) DEFAULT NULL,
  `promo_code` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `idx_status` (`status`),
  KEY `idx_client` (`client_id`),
  KEY `idx_date` (`created_at`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `password_resets`;

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_token` (`token`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_gateways`;

CREATE TABLE `payment_gateways` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `module` varchar(100) NOT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `is_active` tinyint(1) DEFAULT 1,
  `order_priority` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_name` (`name`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `paytr_payments`;

CREATE TABLE `paytr_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) unsigned NOT NULL,
  `order_id` int(10) unsigned DEFAULT NULL,
  `merchant_oid` varchar(100) NOT NULL,
  `payment_amount` decimal(15,2) NOT NULL,
  `payment_type` varchar(50) DEFAULT 'card',
  `status` enum('pending','success','failed','cancelled') DEFAULT 'pending',
  `paytr_transaction_id` varchar(100) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `callback_data` text DEFAULT NULL COMMENT 'PayTR callback verisi',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `merchant_oid` (`merchant_oid`),
  KEY `idx_invoice` (`invoice_id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_merchant_oid` (`merchant_oid`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_config_links`;

CREATE TABLE `product_config_links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_group` (`product_id`,`group_id`),
  KEY `group_id` (`group_id`),
  CONSTRAINT `product_config_links_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_config_links_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `config_option_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_groups`;

CREATE TABLE `product_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `order_priority` int(11) DEFAULT 0,
  `is_hidden` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `type` varchar(50) DEFAULT 'hosting',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_order` (`order_priority`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_types`;

CREATE TABLE `product_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT 'fa-box',
  `color` varchar(20) DEFAULT '#64748b',
  `emoji` varchar(10) DEFAULT '?',
  `order_priority` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `products`;

CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned DEFAULT NULL,
  `type` enum('hosting','vps','vds','dedicated','domain','ssl','other') NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`features`)),
  `price_monthly` decimal(15,2) DEFAULT NULL,
  `price_quarterly` decimal(15,2) DEFAULT NULL,
  `price_semiannually` decimal(15,2) DEFAULT NULL,
  `price_annually` decimal(15,2) DEFAULT NULL,
  `price_biennially` decimal(15,2) DEFAULT NULL,
  `price_triennially` decimal(15,2) DEFAULT NULL,
  `setup_fee` decimal(15,2) DEFAULT 0.00,
  `module` varchar(100) DEFAULT NULL,
  `module_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`module_config`)),
  `server_group_id` int(10) unsigned DEFAULT NULL,
  `stock_control` tinyint(1) DEFAULT 0,
  `stock_quantity` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `is_hidden` tinyint(1) DEFAULT 0,
  `order_priority` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_featured` tinyint(1) DEFAULT 0,
  `domain_required` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `group_id` (`group_id`),
  KEY `idx_type` (`type`),
  KEY `idx_active` (`is_active`),
  KEY `idx_order` (`order_priority`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `product_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `promotions`;

CREATE TABLE `promotions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `type` enum('percentage','fixed','override','free_setup') DEFAULT 'percentage',
  `value` decimal(15,2) NOT NULL,
  `applies_to` enum('all','products','product_groups') DEFAULT 'all',
  `applies_to_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`applies_to_ids`)),
  `billing_cycles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`billing_cycles`)),
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `max_uses` int(11) DEFAULT 0,
  `uses` int(11) DEFAULT 0,
  `max_uses_per_client` int(11) DEFAULT 0,
  `new_clients_only` tinyint(1) DEFAULT 0,
  `once_per_client` tinyint(1) DEFAULT 0,
  `recurring` tinyint(1) DEFAULT 0,
  `recurring_months` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_code` (`code`),
  KEY `idx_active` (`is_active`),
  KEY `idx_dates` (`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `proposal_items`;

CREATE TABLE `proposal_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proposal_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `proposal_id` (`proposal_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `proposals`;

CREATE TABLE `proposals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `status` enum('Draft','Sent','Accepted','Rejected','Expired') DEFAULT 'Draft',
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `currency` varchar(3) DEFAULT 'TRY',
  `created_at` datetime DEFAULT current_timestamp(),
  `valid_until` date DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `client_notes` text DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `references`;

CREATE TABLE `references` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `logo` varchar(500) DEFAULT NULL,
  `website` varchar(500) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `dark_logo` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_category` (`category`),
  KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `server_groups`;

CREATE TABLE `server_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `fill_type` enum('fill','balance') DEFAULT 'balance',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `servers`;

CREATE TABLE `servers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `port` int(11) DEFAULT 22,
  `username` varchar(100) DEFAULT NULL,
  `password` text DEFAULT NULL,
  `access_hash` text DEFAULT NULL,
  `api_token` text DEFAULT NULL,
  `module` varchar(100) NOT NULL,
  `module_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`module_config`)),
  `max_accounts` int(11) DEFAULT 0,
  `current_accounts` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `status` enum('online','offline','maintenance') DEFAULT 'online',
  `last_check` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_check_result` enum('online','offline','unknown') DEFAULT 'unknown',
  `secure` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_status` (`status`),
  KEY `idx_module` (`module`),
  CONSTRAINT `servers_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `server_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `services`;

CREATE TABLE `services` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `order_id` int(10) unsigned DEFAULT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `server_id` int(10) unsigned DEFAULT NULL,
  `esxi_server_id` int(10) unsigned DEFAULT NULL,
  `esxi_vmid` varchar(50) DEFAULT NULL,
  `vm_hostname` varchar(255) DEFAULT NULL,
  `domain` varchar(255) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password` text DEFAULT NULL,
  `status` enum('pending','active','suspended','terminated','cancelled') DEFAULT 'pending',
  `billing_cycle` enum('monthly','quarterly','semiannually','annually','biennially','triennially','onetime') DEFAULT 'monthly',
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'TRY',
  `registration_date` date DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `first_payment_amount` decimal(15,2) DEFAULT NULL,
  `override_auto_suspend` tinyint(1) DEFAULT 0,
  `override_suspend_until` date DEFAULT NULL,
  `dedicated_ip` varchar(45) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `module_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`module_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  KEY `server_id` (`server_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`next_due_date`),
  KEY `idx_domain` (`domain`),
  CONSTRAINT `services_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `services_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `services_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `services_ibfk_4` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `settings`;

CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_group` varchar(50) DEFAULT 'general',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_group` (`setting_group`)
) ENGINE=InnoDB AUTO_INCREMENT=270 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ssl_certificates`;

CREATE TABLE `ssl_certificates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `service_id` int(10) unsigned DEFAULT NULL,
  `order_id` int(10) unsigned DEFAULT NULL,
  `type` varchar(100) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `status` enum('pending','processing','active','expired','cancelled') DEFAULT 'pending',
  `csr` text DEFAULT NULL,
  `private_key` text DEFAULT NULL,
  `certificate` text DEFAULT NULL,
  `ca_bundle` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `provider` varchar(100) DEFAULT NULL,
  `provider_order_id` varchar(255) DEFAULT NULL,
  `configuration` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`configuration`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  KEY `order_id` (`order_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_domain` (`domain`),
  KEY `idx_expiry` (`expiry_date`),
  CONSTRAINT `ssl_certificates_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ssl_certificates_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ssl_certificates_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ticket_replies`;

CREATE TABLE `ticket_replies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `admin_id` int(10) unsigned DEFAULT NULL,
  `message` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `is_internal` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `admin_id` (`admin_id`),
  KEY `idx_ticket` (`ticket_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `ticket_replies_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_replies_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ticket_replies_ibfk_3` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tickets`;

CREATE TABLE `tickets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_number` varchar(20) NOT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `service_id` int(10) unsigned DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('open','answered','customer_reply','on_hold','in_progress','closed') DEFAULT 'open',
  `admin_id` int(10) unsigned DEFAULT NULL,
  `last_reply_by` enum('client','admin') DEFAULT 'client',
  `last_reply_at` datetime DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `cc` text DEFAULT NULL,
  `flag_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_number` (`ticket_number`),
  KEY `service_id` (`service_id`),
  KEY `admin_id` (`admin_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_department` (`department_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_ibfk_3` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `transactions`;

CREATE TABLE `transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `gateway` varchar(50) NOT NULL,
  `type` enum('payment','refund','credit') DEFAULT 'payment',
  `amount` decimal(15,2) NOT NULL,
  `fee` decimal(15,2) DEFAULT 0.00,
  `currency` varchar(3) DEFAULT 'TRY',
  `exchange_rate` decimal(10,6) DEFAULT 1.000000,
  `status` enum('pending','success','failed','refunded') DEFAULT 'pending',
  `description` text DEFAULT NULL,
  `gateway_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gateway_response`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_invoice` (`invoice_id`),
  KEY `idx_gateway` (`gateway`),
  KEY `idx_status` (`status`),
  KEY `idx_transaction` (`transaction_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `translations`;

CREATE TABLE `translations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lang_code` varchar(5) NOT NULL,
  `t_key` varchar(191) NOT NULL,
  `t_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dil_anahtar` (`lang_code`,`t_key`),
  KEY `idx_anahtar` (`t_key`)
) ENGINE=InnoDB AUTO_INCREMENT=5551 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `vds_pricing`;

CREATE TABLE `vds_pricing` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `resource_type` enum('cpu','ram','disk','base','bandwidth','ip') NOT NULL,
  `resource_name` varchar(100) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_value` int(11) NOT NULL DEFAULT 1,
  `max_value` int(11) NOT NULL DEFAULT 100,
  `step_value` int(11) NOT NULL DEFAULT 1,
  `unit_label` varchar(20) DEFAULT '',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
