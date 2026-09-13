-- ---------------------------------------------------------------
-- WHMVM - kurulum şeması ve başlangıç verisi
--
-- Bu dosya müşteri, sipariş, fatura ve log verisi İÇERMEZ.
-- SMTP ve alan adı sağlayıcı parolaları boştur; kurulumdan sonra
-- yönetim panelinden girilir.
--
-- Kullanım:  mysql -u root whmvm < install/whmvm-kurulum.sql
-- ---------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `affiliate_commissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `affiliate_referrals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `affiliate_visits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `affiliate_withdrawals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `affiliates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_transfer_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cancellation_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `config_option_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `config_option_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `config_option_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `config_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cron_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `domain_pricing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `domains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `esxi_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `esxi_servers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hizmet_mahalleleri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hizmet_sokaklari`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `integration_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kesif_talepleri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `languages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `menu_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_gateways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paytr_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_config_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `promotions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proposal_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proposals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `references`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `server_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `server_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `fill_type` enum('fill','balance') DEFAULT 'balance',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `servers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ssl_certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ticket_replies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vds_pricing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;



/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `product_groups` WRITE;
/*!40000 ALTER TABLE `product_groups` DISABLE KEYS */;
INSERT INTO `product_groups` (`id`, `name`, `slug`, `description`, `order_priority`, `is_hidden`, `created_at`, `updated_at`, `type`) VALUES (6,'Linux Hosting','linux-hosting','',0,0,'2026-01-09 00:33:50','2026-01-09 00:36:33','hosting'),(7,'Windows Hosting','windows-hosting','',1,0,'2026-01-09 00:34:10','2026-01-09 00:36:33','hosting'),(9,'Kurumsal Hosting','kurumsal-hosting','',1,0,'2026-01-09 00:52:32','2026-01-09 00:52:32','hosting'),(10,'Arşiv Hosting','arsiv-hosting','',3,0,'2026-01-09 01:34:10','2026-01-09 01:34:10','hosting'),(11,'Fiziksel Sunucu','fiziksel-sunucu','',1,0,'2026-01-09 01:38:49','2026-01-09 01:38:49','dedicated'),(12,'BTK Log Sunucu','btk-log-sunucu','BTK Tarafından ISS\'lardan istenen log ve kayıt sistemi için hazırlanmış gruptur.',0,0,'2026-01-11 10:48:41','2026-09-13 14:02:59','other'),(13,'Ekran Kartlı Sunucu','ekran-kartli-sunucu','',0,0,'2026-01-11 13:20:07','2026-01-11 13:20:07','dedicated'),(14,'Co Location','co-location','',0,0,'2026-01-12 13:14:50','2026-01-12 13:14:50','other'),(15,'Wordpress Hosting','wordpress-hosting','WordPress için ayarlanmış, LiteSpeed Cache ve WP Toolkit ile gelen hosting paketleri.',1,0,'2026-09-13 13:47:29','2026-09-13 13:47:29','hosting'),(16,'Web Site Builder','website-builder','Kod yazmadan, sürükle-bırak editörle web sitesi kurma paketleri.',2,0,'2026-09-13 13:58:56','2026-09-13 13:58:56','hosting'),(17,'SSL Sertifikası','ssl-sertifikasi','Alan adı, kuruluş ve genişletilmiş doğrulamalı SSL sertifikaları.',5,1,'2026-09-13 14:20:32','2026-09-13 14:20:32','ssl'),(18,'Hotspot & İnternet','hotspot-hizmeti','Fiber internet hattı, hotspot donanımı ve 5651 uyumlu log kaydı tek pakette.',6,1,'2026-09-13 14:24:58','2026-09-13 14:24:58','other');
/*!40000 ALTER TABLE `product_groups` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `product_types` WRITE;
/*!40000 ALTER TABLE `product_types` DISABLE KEYS */;
INSERT INTO `product_types` (`id`, `slug`, `label`, `icon`, `color`, `emoji`, `order_priority`, `is_active`, `created_at`) VALUES (1,'hosting','Web Hosting','fa-globe','#f97316','🌐',1,1,'2026-02-04 15:36:10'),(2,'vps','VPS Sunucu','fa-server','#10b981','💻',2,1,'2026-02-04 15:36:10'),(3,'vds','VDS Sunucu','fa-database','#6366f1','🖥️',3,1,'2026-02-04 15:36:10'),(4,'dedicated','Fiziksel Sunucu','fa-building','#8b5cf6','🏢',4,1,'2026-02-04 15:36:10'),(5,'domain','Domain','fa-link','#0ea5e9','🔗',5,1,'2026-02-04 15:36:10'),(6,'ssl','SSL Sertifikası','fa-shield-alt','#22c55e','🔒',6,1,'2026-02-04 15:36:10'),(7,'email','E-posta','fa-envelope','#f59e0b','📧',7,1,'2026-02-04 15:36:10'),(8,'other','Diğer','fa-box','#64748b','📦',8,1,'2026-02-04 15:36:10');
/*!40000 ALTER TABLE `product_types` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` (`id`, `group_id`, `type`, `name`, `slug`, `description`, `features`, `price_monthly`, `price_quarterly`, `price_semiannually`, `price_annually`, `price_biennially`, `price_triennially`, `setup_fee`, `module`, `module_config`, `server_group_id`, `stock_control`, `stock_quantity`, `is_active`, `is_hidden`, `order_priority`, `created_at`, `updated_at`, `is_featured`, `domain_required`) VALUES (9,9,'hosting','Kurumsal P1','kurumsal-hosting-p1','500 MB Disk Alanı\n1500 GB Aylık Trafik\n50 Adet Kurumsal E-Posta\n5 Adet FTP Kullanıcısı\nÜcretsiz Let\'s SSL\n',NULL,500.00,NULL,NULL,NULL,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,1,'2026-01-09 00:52:51','2026-09-13 13:51:28',0,0),(10,10,'hosting','Arşiv Hosting P1','arsiv-hosting-p1','1 TB Yedekleme Alanı\n1 TB Aylık Trafik\n1000 Mbps İnternet Hızı\n2 Kullanıcı\nFTP / SFTP / FTPS Erişimi\nWebDAV ile Sürücü Bağlama\nTLS + AES-256 Şifreleme\n30 Gün Sürüm Geçmişi\nRAID 10 Depolama\nGünlük Otomatik Yedek Planı\n7/24 Teknik Destek',NULL,750.00,NULL,NULL,7650.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,1,'2026-01-09 01:35:55','2026-09-13 13:57:15',0,0),(11,7,'hosting','Windows Hosting P1','windows-hosting-p1','2 GB SSD Disk Alanı\nLimitsiz Aylık Trafik\n1024 MB RAM Kullanımı\n1 Core CPU Kullanımı\n10 Adet E-posta Hesabı\nVeri Tabanı  1 Adet MSSQL Veritabanı\nPlesk Kontrol Paneli\nASP.NET ve .NET Core Desteği\nIIS Web Sunucusu\nÜcretsiz SSL Sertifikası\nGünlük Yedekleme\n%99.9 Uptime Garantisi',NULL,35.00,NULL,NULL,357.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,1,'2026-01-11 10:38:02','2026-09-13 13:42:33',0,0),(12,12,'other','BTK-LOG-2U','btk-log-2u','BTK ile N/N Devre\n1U Sunucu Barındırma\n1U Firewall Barındırma\n1 Adet Local IP Adresi\n1 Adet VPN Tunnel\nKesintisiz Güç ve İklimlendirme\nÜcretsiz Kurulum\nÜcretsiz Danışmanlık\n7/24 Teknik Destek',NULL,2500.00,NULL,NULL,30000.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,1,'2026-01-11 10:51:34','2026-09-13 14:02:59',0,0),(13,12,'other','BTK-LOG-3U','btk-log-3u','BTK ile N/N Devre\n2U Sunucu Barındırma\n1U Firewall Barındırma\n1 Adet Local IP Adresi\n1 Adet VPN Tunnel\nKesintisiz Güç ve İklimlendirme\nÜcretsiz Kurulum\nÜcretsiz Danışmanlık\n7/24 Teknik Destek',NULL,3000.00,NULL,NULL,36000.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,2,'2026-01-11 10:51:58','2026-09-13 14:02:59',0,0),(14,11,'dedicated','Dell R820','dell-r820','Dell PowerEdge R820 Sunucu\n14 Çekirdek İşlemci\n128 GB DDR3 RAM\n4 TB SSD Disk\nLimitlendirilmemiş Trafik\n1x Intel Xeon E5-2680 v4\n1 Gbps Port Hızı\nDonanımsal RAID Denetleyici\niDRAC 7 Uzaktan Yönetim\nÜcretsiz Kurulum\n7/24 Teknik Destek',NULL,3500.00,NULL,NULL,42000.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,2,'2026-01-11 13:03:26','2026-09-13 14:06:27',0,0),(16,13,'dedicated','RTX 4060','rtx-4060','RTX 4060 Ekran Kartı\n8GB DDR4 Ram\n240 GB SSD Disk\nISP IP Adresi\nBursa Lokasyon',NULL,4500.00,NULL,NULL,NULL,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,1,'2026-01-11 13:57:40','2026-01-11 13:58:35',0,0),(17,13,'dedicated','RTX 4070','rtx-4070','RTX 4060 Ekran Kartı\n8GB DDR4 Ram\n240 GB SSD Disk\nISP IP Adresi\nBursa Lokasyon',NULL,7500.00,NULL,NULL,NULL,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,2,'2026-01-11 13:57:40','2026-01-11 13:58:54',0,0),(18,13,'dedicated','RTX 4080','rtx-4080','RTX 4060 Ekran Kartı\n8GB DDR4 Ram\n240 GB SSD Disk\nISP IP Adresi\nBursa Lokasyon',NULL,12000.00,NULL,NULL,NULL,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,3,'2026-01-11 13:57:40','2026-01-11 13:58:57',0,0),(19,13,'dedicated','RTX 4090','rtx-4090','RTX 4090 Ekran Kartı\n8GB DDR4 Ram\n240 GB SSD Disk\nISP IP Adresi\nBursa Lokasyon',NULL,18000.00,NULL,NULL,NULL,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,4,'2026-01-11 13:57:40','2026-01-11 13:59:32',0,0),(20,6,'hosting','Linux Hosting P1','linux-hosting-p1','1000 MB Nvme Disk Alanı\nLimitsiz Aylik Trafik\n1024 MB RAM Kullanımı\n5 Adet E-posta Hesabı.\nCpu  1 Core CPU Kullanımı\nVeri Tabanı  1 Adet MySQL Veritabanı\nInode Limiti  15000 İnode Limiti\ncPanel Kontrol Paneli\nÜcretsiz SSL Sertifikası\nCloudLinux Modülü\nLiteSpeed Web Server\nJetbackup Modülü\nSoftaculous Otomasyonu\n%99.7 Uptime Garantisi',NULL,25.00,NULL,NULL,NULL,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,0,'2026-01-11 19:45:24','2026-01-11 19:45:24',0,1),(21,6,'hosting','Test Tosting 1','test-tosting-1','Satır 1\nSatır 2\nSatır 3\nSatır 4\nSatır 5\nSatır 6\nSatır 7\nSatır 8\nSatır 9\nSatır 10',NULL,500.00,NULL,NULL,50000.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,0,0,1,'2026-01-11 19:47:47','2026-09-13 10:26:25',0,1),(22,6,'hosting','Linux Hosting P2','linux-hosting-p2','5 GB Nvme Disk Alanı\nLimitsiz Aylik Trafik\n2048 MB RAM Kullanımı\nCpu  2 Core CPU Kullanımı\n25 Adet E-posta Hesabı\nVeri Tabanı  5 Adet MySQL Veritabanı\nInode Limiti  50000 İnode Limiti\ncPanel Kontrol Paneli\nÜcretsiz SSL Sertifikası\nCloudLinux Modülü\nLiteSpeed Web Server\nJetbackup Modülü\nSoftaculous Otomasyonu\n%99.9 Uptime Garantisi',NULL,45.00,NULL,NULL,459.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,2,'2026-09-13 10:26:25','2026-09-13 10:26:25',0,0),(23,6,'hosting','Linux Hosting P3','linux-hosting-p3','15 GB Nvme Disk Alanı\nLimitsiz Aylik Trafik\n4096 MB RAM Kullanımı\nCpu  3 Core CPU Kullanımı\n100 Adet E-posta Hesabı\nVeri Tabanı  15 Adet MySQL Veritabanı\nInode Limiti  150000 İnode Limiti\ncPanel Kontrol Paneli\nÜcretsiz SSL Sertifikası\nCloudLinux Modülü\nLiteSpeed Web Server\nJetbackup Modülü\nSoftaculous Otomasyonu\nImunify360 Güvenlik Koruması\nÜcretsiz Site Taşıma\n%99.9 Uptime Garantisi',NULL,79.00,NULL,NULL,799.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,3,'2026-09-13 10:26:25','2026-09-13 10:26:25',1,0),(24,6,'hosting','Linux Hosting P4','linux-hosting-p4','50 GB Nvme Disk Alanı\nLimitsiz Aylik Trafik\n8192 MB RAM Kullanımı\nCpu  4 Core CPU Kullanımı\nLimitsiz E-posta Hesabı\nVeri Tabanı  Limitsiz MySQL Veritabanı\nInode Limiti  400000 İnode Limiti\ncPanel Kontrol Paneli\nÜcretsiz SSL Sertifikası\nCloudLinux Modülü\nLiteSpeed Web Server\nJetbackup Modülü\nSoftaculous Otomasyonu\nImunify360 Güvenlik Koruması\nÜcretsiz Site Taşıma\nÖncelikli Teknik Destek\nDedicated IP Adresi\n%99.9 Uptime Garantisi',NULL,149.00,NULL,NULL,1490.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,4,'2026-09-13 10:26:25','2026-09-13 10:26:25',0,0),(25,9,'hosting','Kurumsal P2','kurumsal-hosting-p2','5 GB Disk Alanı\n3000 GB Aylık Trafik\n100 Adet Kurumsal E-Posta\n15 Adet FTP Kullanıcısı\nÜcretsiz Let\'s SSL',NULL,750.00,NULL,NULL,7650.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,2,'2026-09-13 11:24:15','2026-09-13 13:51:28',0,0),(26,9,'hosting','Kurumsal P3','kurumsal-hosting-p3','15 GB Disk Alanı\nLimitsiz Aylık Trafik\n250 Adet Kurumsal E-Posta\n50 Adet FTP Kullanıcısı\nÜcretsiz Let\'s SSL',NULL,1200.00,NULL,NULL,12000.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,3,'2026-09-13 11:24:15','2026-09-13 13:51:28',1,0),(27,9,'hosting','Kurumsal P4','kurumsal-hosting-p4','50 GB Disk Alanı\nLimitsiz Aylık Trafik\nLimitsiz Kurumsal E-Posta\nLimitsiz FTP Kullanıcısı\nÜcretsiz Let\'s SSL',NULL,2000.00,NULL,NULL,19200.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,4,'2026-09-13 11:24:15','2026-09-13 13:51:28',0,0),(28,7,'hosting','Windows Hosting P2','windows-hosting-p2','10 GB SSD Disk Alanı\nLimitsiz Aylık Trafik\n2048 MB RAM Kullanımı\n2 Core CPU Kullanımı\n50 Adet E-posta Hesabı\nVeri Tabanı  3 Adet MSSQL Veritabanı\nPlesk Kontrol Paneli\nASP.NET ve .NET Core Desteği\nIIS Web Sunucusu\nÜcretsiz SSL Sertifikası\nGünlük Yedekleme\nÜcretsiz Site Taşıma\n%99.9 Uptime Garantisi',NULL,65.00,NULL,NULL,663.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,2,'2026-09-13 13:42:33','2026-09-13 13:42:33',0,0),(29,7,'hosting','Windows Hosting P3','windows-hosting-p3','25 GB SSD Disk Alanı\nLimitsiz Aylık Trafik\n4096 MB RAM Kullanımı\n3 Core CPU Kullanımı\n150 Adet E-posta Hesabı\nVeri Tabanı  10 Adet MSSQL Veritabanı\nPlesk Kontrol Paneli\nASP.NET ve .NET Core Desteği\nIIS Web Sunucusu\nÜcretsiz SSL Sertifikası\nGünlük Yedekleme\nÜcretsiz Site Taşıma\nÖncelikli Teknik Destek\n%99.9 Uptime Garantisi',NULL,119.00,NULL,NULL,1214.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,3,'2026-09-13 13:42:33','2026-09-13 13:42:33',1,0),(30,7,'hosting','Windows Hosting P4','windows-hosting-p4','60 GB SSD Disk Alanı\nLimitsiz Aylık Trafik\n8192 MB RAM Kullanımı\n4 Core CPU Kullanımı\nLimitsiz E-posta Hesabı\nVeri Tabanı  Limitsiz MSSQL Veritabanı\nPlesk Kontrol Paneli\nASP.NET ve .NET Core Desteği\nIIS Web Sunucusu\nÜcretsiz SSL Sertifikası\nGünlük Yedekleme\nÜcretsiz Site Taşıma\nÖncelikli Teknik Destek\nDedicated IP Adresi\n%99.9 Uptime Garantisi',NULL,199.00,NULL,NULL,2030.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,4,'2026-09-13 13:42:33','2026-09-13 13:42:33',0,0),(31,15,'hosting','WordPress P1','wordpress-hosting-p1','5 GB NVMe Disk Alanı\nLimitsiz Aylık Trafik\n1024 MB RAM Kullanımı\n1 Core CPU Kullanımı\n10 Adet E-posta Hesabı\nVeri Tabanı  1 Adet MySQL Veritabanı\n1 Adet WordPress Kurulumu\ncPanel Kontrol Paneli\nWP Toolkit Yönetimi\nLiteSpeed Cache Eklentisi\nÜcretsiz SSL Sertifikası\nGünlük Yedekleme\n%99.9 Uptime Garantisi',NULL,45.00,NULL,NULL,459.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,1,'2026-09-13 13:47:29','2026-09-13 13:51:28',0,0),(32,15,'hosting','WordPress P2','wordpress-hosting-p2','15 GB NVMe Disk Alanı\nLimitsiz Aylık Trafik\n2048 MB RAM Kullanımı\n2 Core CPU Kullanımı\n50 Adet E-posta Hesabı\nVeri Tabanı  3 Adet MySQL Veritabanı\n3 Adet WordPress Kurulumu\ncPanel Kontrol Paneli\nWP Toolkit Yönetimi\nLiteSpeed Cache Eklentisi\nOtomatik Çekirdek Güncelleme\nÜcretsiz SSL Sertifikası\nGünlük Yedekleme\nÜcretsiz Site Taşıma\n%99.9 Uptime Garantisi',NULL,89.00,NULL,NULL,907.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,2,'2026-09-13 13:47:29','2026-09-13 13:51:28',0,0),(33,15,'hosting','WordPress P3','wordpress-hosting-p3','35 GB NVMe Disk Alanı\nLimitsiz Aylık Trafik\n4096 MB RAM Kullanımı\n3 Core CPU Kullanımı\n150 Adet E-posta Hesabı\nVeri Tabanı  10 Adet MySQL Veritabanı\n10 Adet WordPress Kurulumu\ncPanel Kontrol Paneli\nWP Toolkit Yönetimi\nLiteSpeed Cache Eklentisi\nOtomatik Çekirdek Güncelleme\nStaging (Deneme Kopyası)\nÜcretsiz SSL Sertifikası\nGünlük Yedekleme\nÜcretsiz Site Taşıma\nÖncelikli Teknik Destek\n%99.9 Uptime Garantisi',NULL,149.00,NULL,NULL,1519.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,3,'2026-09-13 13:47:29','2026-09-13 13:51:28',1,0),(34,15,'hosting','WordPress P4','wordpress-hosting-p4','75 GB NVMe Disk Alanı\nLimitsiz Aylık Trafik\n8192 MB RAM Kullanımı\n4 Core CPU Kullanımı\nLimitsiz E-posta Hesabı\nVeri Tabanı  Limitsiz MySQL Veritabanı\nLimitsiz WordPress Kurulumu\ncPanel Kontrol Paneli\nWP Toolkit Yönetimi\nLiteSpeed Cache Eklentisi\nOtomatik Çekirdek Güncelleme\nStaging (Deneme Kopyası)\nÜcretsiz SSL Sertifikası\nGünlük Yedekleme\nÜcretsiz Site Taşıma\nÖncelikli Teknik Destek\nDedicated IP Adresi\n%99.9 Uptime Garantisi',NULL,249.00,NULL,NULL,2540.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,4,'2026-09-13 13:47:29','2026-09-13 13:51:28',0,0),(35,10,'hosting','Arşiv Hosting P2','arsiv-hosting-p2','2 TB Yedekleme Alanı\n2 TB Aylık Trafik\n1000 Mbps İnternet Hızı\n5 Kullanıcı\nFTP / SFTP / FTPS Erişimi\nWebDAV ile Sürücü Bağlama\nTLS + AES-256 Şifreleme\n30 Gün Sürüm Geçmişi\nRAID 10 Depolama\nGünlük Otomatik Yedek Planı\nSüreli Paylaşım Bağlantısı\n7/24 Teknik Destek',NULL,1350.00,NULL,NULL,13770.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,2,'2026-09-13 13:57:15','2026-09-13 13:57:15',0,0),(36,10,'hosting','Arşiv Hosting P3','arsiv-hosting-p3','4 TB Yedekleme Alanı\n4 TB Aylık Trafik\n1000 Mbps İnternet Hızı\n15 Kullanıcı\nFTP / SFTP / FTPS Erişimi\nWebDAV ile Sürücü Bağlama\nTLS + AES-256 Şifreleme\n30 Gün Sürüm Geçmişi\nRAID 10 Depolama\nGünlük Otomatik Yedek Planı\nSüreli Paylaşım Bağlantısı\nÖncelikli Teknik Destek\n7/24 Teknik Destek',NULL,2400.00,NULL,NULL,24480.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,3,'2026-09-13 13:57:15','2026-09-13 13:57:15',1,0),(37,10,'hosting','Arşiv Hosting P4','arsiv-hosting-p4','8 TB Yedekleme Alanı\n8 TB Aylık Trafik\n1000 Mbps İnternet Hızı\nLimitsiz Kullanıcı\nFTP / SFTP / FTPS Erişimi\nWebDAV ile Sürücü Bağlama\nTLS + AES-256 Şifreleme\n30 Gün Sürüm Geçmişi\nRAID 10 Depolama\nGünlük Otomatik Yedek Planı\nSüreli Paylaşım Bağlantısı\nÖncelikli Teknik Destek\nAyrılmış IP Adresi\n7/24 Teknik Destek',NULL,4200.00,NULL,NULL,42840.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,4,'2026-09-13 13:57:15','2026-09-13 13:57:15',0,0),(38,16,'hosting','Site Builder P1','website-builder-p1','1 Web Sitesi\n5 Sayfa Hakkı\n5 GB Disk Alanı\nLimitsiz Aylık Trafik\nSürükle-Bırak Editör\n50+ Hazır Şablon\nMobil Uyumlu Tasarım\nÜcretsiz SSL Sertifikası\nSEO Ayarları\nAlan Adı Bağlama\n7/24 Teknik Destek',NULL,89.00,NULL,NULL,908.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,1,'2026-09-13 13:58:56','2026-09-13 13:58:56',0,0),(39,16,'hosting','Site Builder P2','website-builder-p2','3 Web Sitesi\n20 Sayfa Hakkı\n15 GB Disk Alanı\nLimitsiz Aylık Trafik\nSürükle-Bırak Editör\n100+ Hazır Şablon\nMobil Uyumlu Tasarım\nÜcretsiz SSL Sertifikası\nSEO Ayarları\nAlan Adı Bağlama\nKendi Alan Adınızla E-posta\nZiyaretçi İstatistikleri\n7/24 Teknik Destek',NULL,179.00,NULL,NULL,1826.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,2,'2026-09-13 13:58:56','2026-09-13 13:58:56',0,0),(40,16,'hosting','Site Builder P3','website-builder-p3','10 Web Sitesi\nLimitsiz Sayfa Hakkı\n40 GB Disk Alanı\nLimitsiz Aylık Trafik\nSürükle-Bırak Editör\n150+ Hazır Şablon\nMobil Uyumlu Tasarım\nÜcretsiz SSL Sertifikası\nSEO Ayarları\nAlan Adı Bağlama\nKendi Alan Adınızla E-posta\nZiyaretçi İstatistikleri\nE-Ticaret Modülü\nÖncelikli Teknik Destek',NULL,299.00,NULL,NULL,3050.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,3,'2026-09-13 13:58:56','2026-09-13 13:58:56',1,0),(41,16,'hosting','Site Builder P4','website-builder-p4','Limitsiz Web Sitesi\nLimitsiz Sayfa Hakkı\n100 GB Disk Alanı\nLimitsiz Aylık Trafik\nSürükle-Bırak Editör\n150+ Hazır Şablon\nMobil Uyumlu Tasarım\nÜcretsiz SSL Sertifikası\nSEO Ayarları\nAlan Adı Bağlama\nKendi Alan Adınızla E-posta\nZiyaretçi İstatistikleri\nE-Ticaret Modülü\nÇoklu Dil Desteği\nÖncelikli Teknik Destek',NULL,499.00,NULL,NULL,5090.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,4,'2026-09-13 13:58:56','2026-09-13 13:58:56',0,0),(42,12,'other','BTK-LOG-4U','btk-log-4u','BTK ile N/N Devre\n3U Sunucu Barındırma\n1U Firewall Barındırma\n2 Adet Local IP Adresi\n2 Adet VPN Tunnel\nKesintisiz Güç ve İklimlendirme\nÜcretsiz Kurulum\nÜcretsiz Danışmanlık\nÖncelikli Teknik Destek\n7/24 Teknik Destek',NULL,3500.00,NULL,NULL,42000.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,3,'2026-09-13 14:02:59','2026-09-13 14:02:59',1,0),(43,12,'other','BTK-LOG-5U','btk-log-5u','BTK ile N/N Devre\n4U Sunucu Barındırma\n1U Firewall Barındırma\n4 Adet Local IP Adresi\n4 Adet VPN Tunnel\nKesintisiz Güç ve İklimlendirme\nÜcretsiz Kurulum\nÜcretsiz Danışmanlık\nÖncelikli Teknik Destek\nYedekli Devre Seçeneği\n7/24 Teknik Destek',NULL,4000.00,NULL,NULL,48000.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,4,'2026-09-13 14:02:59','2026-09-13 14:02:59',0,0),(44,11,'dedicated','Dell R730','dell-r730','Dell PowerEdge R730 Sunucu\n24 Çekirdek İşlemci\n64 GB DDR4 RAM\n2x 480 GB SSD Disk\nLimitlendirilmemiş Trafik\n2x Intel Xeon E5-2650 v4\n1 Gbps Port Hızı\nDonanımsal RAID Denetleyici\niDRAC 8 Uzaktan Yönetim\nÜcretsiz Kurulum\n7/24 Teknik Destek',NULL,2750.00,NULL,NULL,33000.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,1,'2026-09-13 14:06:27','2026-09-13 14:06:27',0,0),(45,11,'dedicated','Dell R730xd','dell-r730xd','Dell PowerEdge R730xd Sunucu\n36 Çekirdek İşlemci\n256 GB DDR4 RAM\n8x 960 GB SSD Disk\nLimitlendirilmemiş Trafik\n2x Intel Xeon E5-2697 v4\n1 Gbps Port Hızı\nDonanımsal RAID Denetleyici\niDRAC 8 Uzaktan Yönetim\n12 Disk Yuvası\nÜcretsiz Kurulum\nÖncelikli Teknik Destek\n7/24 Teknik Destek',NULL,4900.00,NULL,NULL,58800.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,3,'2026-09-13 14:06:27','2026-09-13 14:06:27',1,0),(46,11,'dedicated','R730 + VNX7600','dell-r730-vnx7600','Dell R730 + EMC VNX7600\n36 Çekirdek İşlemci\n384 GB DDR4 RAM\n60 TB VNX7600 Disk\nLimitlendirilmemiş Trafik\n2x Intel Xeon E5-2697 v4\n8 Gbps Fibre Channel Bağlantı\nÇift Storage Processor\nDonanımsal RAID 5 / 6 / 10\niDRAC 8 Uzaktan Yönetim\nYedekli Güç Kaynağı\nÜcretsiz Kurulum\nÖncelikli Teknik Destek\n7/24 Teknik Destek',NULL,7500.00,NULL,NULL,90000.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,4,'2026-09-13 14:06:27','2026-09-13 14:06:27',0,0),(47,17,'ssl','DV SSL','dv-ssl','DV Doğrulama\n1 Alan Adı Kapsamı\n256-bit Şifreleme\n$10 bin Garanti\nDakikalar içinde aktivasyon\nÜcretsiz kurulum ve yapılandırma\nSınırsız yeniden düzenleme\nBütün büyük tarayıcılarda geçerli\nSite güven mührü',NULL,0.00,0.00,0.00,2475.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,1,'2026-09-13 14:20:32','2026-09-13 14:23:32',0,1),(48,17,'ssl','OV SSL','ov-ssl','OV Doğrulama\n1 Alan Adı Kapsamı\n256-bit Şifreleme\n$100 bin Garanti\nSertifikada şirket adı görünür\n1-3 iş günü içinde doğrulama\nÜcretsiz kurulum ve yapılandırma\nSınırsız yeniden düzenleme\nBütün büyük tarayıcılarda geçerli\nSite güven mührü',NULL,0.00,0.00,0.00,7475.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,2,'2026-09-13 14:20:32','2026-09-13 14:23:32',1,1),(49,17,'ssl','Wildcard SSL','wildcard-ssl','DV Doğrulama\nTüm Alt Alanlar Kapsamı\n256-bit Şifreleme\n$10 bin Garanti\nTek sertifikayla bütün alt alan adları\nDakikalar içinde aktivasyon\nÜcretsiz kurulum ve yapılandırma\nSınırsız yeniden düzenleme\nBütün büyük tarayıcılarda geçerli',NULL,0.00,0.00,0.00,11225.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,3,'2026-09-13 14:20:32','2026-09-13 14:23:32',0,1),(50,17,'ssl','EV SSL','ev-ssl','EV Doğrulama\n1 Alan Adı Kapsamı\n256-bit Şifreleme\n$1,75 milyon Garanti\nSertifikada şirket adı görünür\nEn yüksek güven seviyesi\n3-5 iş günü içinde doğrulama\nÜcretsiz kurulum ve yapılandırma\nSınırsız yeniden düzenleme\nBütün büyük tarayıcılarda geçerli',NULL,0.00,0.00,0.00,19975.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,4,'2026-09-13 14:20:32','2026-09-13 14:23:32',0,1),(51,18,'other','Hotspot 100','hotspot-100','100 Mbps İnternet Hızı\n2 Adet Erişim Noktası\n50 Eşzamanlı Kullanıcı\n2 Yıl Log Saklama\nFiber internet hattı dahil\n5651 uyumlu kayıt sistemi\nSMS ile misafir girişi\nMarkanıza özel karşılama sayfası\nÜcretsiz keşif ve kurulum\nZiyaretçi raporları\n7/24 teknik destek',NULL,1950.00,0.00,0.00,23400.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,1,'2026-09-13 14:24:58','2026-09-13 14:24:58',0,0),(52,18,'other','Hotspot 200','hotspot-200','200 Mbps İnternet Hızı\n5 Adet Erişim Noktası\n150 Eşzamanlı Kullanıcı\n2 Yıl Log Saklama\nFiber internet hattı dahil\n5651 uyumlu kayıt sistemi\nSMS ile misafir girişi\nSosyal medya ile giriş\nMarkanıza özel karşılama sayfası\nÜcretsiz keşif ve kurulum\nZiyaretçi raporları\n7/24 teknik destek',NULL,3450.00,0.00,0.00,41400.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,2,'2026-09-13 14:24:58','2026-09-13 14:24:58',0,0),(53,18,'other','Hotspot 500','hotspot-500','500 Mbps İnternet Hızı\n12 Adet Erişim Noktası\n400 Eşzamanlı Kullanıcı\n2 Yıl Log Saklama\nFiber internet hattı dahil\n5651 uyumlu kayıt sistemi\nSMS ile misafir girişi\nSosyal medya ile giriş\nMarkanıza özel karşılama sayfası\nBant genişliği sınırlama\nÜcretsiz keşif ve kurulum\nDetaylı ziyaretçi analizi\nAynı gün yerinde destek\n7/24 teknik destek',NULL,6900.00,0.00,0.00,82800.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,3,'2026-09-13 14:24:58','2026-09-13 14:24:58',1,0),(54,18,'other','Hotspot 1000','hotspot-1000','1000 Mbps İnternet Hızı\nLimitsiz Erişim Noktası\n1000 Eşzamanlı Kullanıcı\n2 Yıl Log Saklama\nFiber internet hattı dahil\nYedekli internet hattı seçeneği\n5651 uyumlu kayıt sistemi\nSMS ile misafir girişi\nSosyal medya ile giriş\nMarkanıza özel karşılama sayfası\nBant genişliği sınırlama\nÜcretsiz keşif ve kurulum\nDetaylı ziyaretçi analizi\nAynı gün yerinde destek\nÖncelikli teknik destek',NULL,12500.00,0.00,0.00,150000.00,NULL,NULL,0.00,NULL,NULL,NULL,0,0,1,0,4,'2026-09-13 14:24:58','2026-09-13 14:24:58',0,0);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `config_option_groups` WRITE;
/*!40000 ALTER TABLE `config_option_groups` DISABLE KEYS */;
INSERT INTO `config_option_groups` (`id`, `name`, `description`, `created_at`) VALUES (1,'Fiziksel Sunucu Seçenekleri','','2026-01-11 14:17:37');
/*!40000 ALTER TABLE `config_option_groups` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `config_options` WRITE;
/*!40000 ALTER TABLE `config_options` DISABLE KEYS */;
INSERT INTO `config_options` (`id`, `group_id`, `name`, `option_type`, `required`, `order_priority`) VALUES (1,1,'EK RAM','dropdown',0,0);
/*!40000 ALTER TABLE `config_options` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `config_option_values` WRITE;
/*!40000 ALTER TABLE `config_option_values` DISABLE KEYS */;
INSERT INTO `config_option_values` (`id`, `option_id`, `name`, `price_monthly`, `price_quarterly`, `price_semiannually`, `price_annually`, `setup_fee`, `order_priority`, `is_default`) VALUES (1,1,'32 GB RAM',250.00,0.00,0.00,0.00,0.00,0,0);
/*!40000 ALTER TABLE `config_option_values` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `product_config_links` WRITE;
/*!40000 ALTER TABLE `product_config_links` DISABLE KEYS */;
INSERT INTO `product_config_links` (`id`, `product_id`, `group_id`) VALUES (2,14,1);
/*!40000 ALTER TABLE `product_config_links` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `menu_items` WRITE;
/*!40000 ALTER TABLE `menu_items` DISABLE KEYS */;
INSERT INTO `menu_items` (`id`, `menu_type`, `parent_id`, `title`, `url`, `icon`, `description`, `target`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES (2,'main',NULL,'Web Hosting','#','fa-server','Web hosting paketleri','_self',1,1,'2026-01-07 09:50:38','2026-01-23 10:53:03'),(3,'main',NULL,'Sunucu','#','fa-database','Virtual Dedicated Server','_self',2,1,'2026-01-07 09:50:38','2026-01-23 10:53:03'),(4,'main',NULL,'Alan Adı','domain.php','fa-globe','Domain kayıt ve transfer','_self',0,1,'2026-01-07 09:50:38','2026-01-09 08:37:42'),(5,'main',NULL,'SSL Sertifikası','ssl.php','fa-lock','SSL sertifikaları','_self',3,1,'2026-01-07 09:50:38','2026-09-13 14:17:02'),(6,'main',NULL,'İletişim','contact.php','fa-envelope','Bize ulaşın','_self',5,1,'2026-01-07 09:50:38','2026-01-23 10:53:05'),(8,'main',2,'Linux Hosting','/store.php?group=linux-hosting','fa-rocket','Yüksek Performanslı Linux Web Hosting hizmetimiz ile web siteleriniz uçacak!','_self',1,1,'2026-01-09 00:11:09','2026-01-23 12:33:15'),(9,'main',2,'Windows Hosting','store.php?group=windows-hosting','fa-globe','Web Sitenizde ASP, .NET, MVC  MSSQL gibi teknolojileri kullanıyorsanız, Windows planlarımıza göz atmanızı öneririz.','_self',4,1,'2026-01-09 00:11:40','2026-01-23 12:34:12'),(10,'main',2,'Arşiv Hosting','/store.php?group=arsiv-hosting','fa-folder','Tablet, Telefon, Bilgisayar cihazlarınız üzerinde bulundurduğunuz dosya ve yedeklere her an her yerden erişmek ve düzenlemek ister misiniz?','_self',6,1,'2026-01-09 00:12:28','2026-01-23 12:34:28'),(11,'main',2,'Kurumsal Hosting','store.php?group=kurumsal-hosting','fa-folder','Tamamen Yüksek kaynaklar ile yapılandırılmış Kurumsal Hosting paketlerimiz ile trafik ve mail sorunu yaşamayacaksınız!','_self',2,1,'2026-01-09 01:31:15','2026-01-23 12:33:52'),(12,'main',3,'BTK Log Sunucu','store.php?group=btk-log-sunucu','fa-database','5651 sayılı kanun gereği tutmanız gereken internet erişim kayıtlarını, zaman damgalı ve imzalı biçimde yasal süre boyunca güvenle saklayın.','_self',1,1,'2026-01-11 10:52:52','2026-09-13 10:34:39'),(13,'main',3,'VDS Sunucu','vds.php','fa-cloud','Tam root erişimi ve size ayrılmış garantili kaynaklar. Projeniz büyüdükçe CPU, RAM ve diski dakikalar içinde yükseltin.','_self',2,1,'2026-01-11 10:54:01','2026-09-13 10:34:39'),(14,'main',3,'Fiziksel Sunucu','store.php?group=fiziksel-sunucu','fa-cloud','Donanımı kimseyle paylaşmayın. Yüksek trafikli projeleriniz için tamamen size ait, maksimum performanslı fiziksel sunucular.','_self',3,1,'2026-01-11 13:09:36','2026-09-13 10:34:39'),(15,'main',NULL,'Diğer Hizmetler','#','fa-chart-line','Yazılım geliştirme, hotspot çözümleri ve marka tescil hizmetleri.','_self',4,1,'2026-01-22 21:17:15','2026-09-13 10:34:39'),(16,'main',15,'Yazılım Hizmetleri','yazilim-hizmetleri.php','fa-cog','CRM, ERP, Web Projeleri, Mobil Uygulama taleplerinizi profesyonel mühendis ekibimiz ile hayata geçiriyoruz.','_self',1,1,'2026-01-22 21:18:08','2026-01-23 12:41:15'),(17,'main',15,'Hotspot Hizmeti','bursa-hotspot-hizmeti.php','fa-wifi','Fiber internet hattı, hotspot donanımı ve 5651 uyumlu log kaydı tek pakette.','_self',2,1,'2026-01-22 21:34:31','2026-09-13 14:24:58'),(18,'main',2,'Wordpress Hosting','store.php?group=wordpress-hosting','fa-blog','WordPress için ayarlanmış, LiteSpeed Cache ve WP Toolkit ile gelen hosting paketleri.','_self',5,1,'2026-01-23 10:59:38','2026-09-13 13:47:29'),(19,'main',2,'Web Site Builder','store.php?group=website-builder','fa-wand-magic-sparkles','Kod yazmadan, sürükle-bırak editörle dakikalar içinde web sitenizi yayına alın.','_self',3,1,'2026-01-23 12:23:55','2026-09-13 13:58:56'),(21,'main',15,'Marka Tescil','marka-tescil.php','fa-building','Markanızı koruma altına alın. Profesyonel marka tescil hizmeti ile işletmenizi güvence altına alıyoruz.','_self',3,1,'2026-01-23 12:43:41','2026-01-23 12:44:04');
/*!40000 ALTER TABLE `menu_items` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `languages` WRITE;
/*!40000 ALTER TABLE `languages` DISABLE KEYS */;
INSERT INTO `languages` (`id`, `code`, `name`, `native_name`, `flag`, `direction`, `is_active`, `is_default`, `sort_order`, `created_at`, `updated_at`) VALUES (1,'tr','Türkçe','Türkçe','🇹🇷','ltr',1,1,1,'2026-09-13 12:42:14','2026-09-13 12:42:14'),(2,'en','İngilizce','English','🇬🇧','ltr',1,0,2,'2026-09-13 12:42:14','2026-09-13 12:42:14'),(3,'ar','Arapça','العربية','🇸🇦','rtl',1,0,3,'2026-09-13 12:42:14','2026-09-13 12:42:14');
/*!40000 ALTER TABLE `languages` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `translations` WRITE;
/*!40000 ALTER TABLE `translations` DISABLE KEYS */;
INSERT INTO `translations` (`id`, `lang_code`, `t_key`, `t_value`, `updated_at`) VALUES (1,'tr','ustbar.sistem-durumu','Sistem Durumu','2026-09-13 12:43:39'),(2,'tr','ustbar.referanslar','Referanslar','2026-09-13 12:43:39'),(3,'tr','ustbar.destek','Destek','2026-09-13 12:43:39'),(4,'tr','ortak.tema-degistir','Tema Değiştir','2026-09-13 12:43:39'),(5,'tr','menu.alan-adi','Alan Adı','2026-09-13 12:43:39'),(6,'tr','menu.web-hosting','Web Hosting','2026-09-13 12:43:39'),(7,'tr','menu.hosting-hizmetleri','Hosting Hizmetleri','2026-09-13 12:43:39'),(8,'tr','menu.linux-hosting','Linux Hosting','2026-09-13 12:43:39'),(9,'tr','menu.yuksek-performansli-linux-web-hosting-hizmetimiz-ile-web-siteleriniz-ucacak','Yüksek Performanslı Linux Web Hosting hizmetimiz ile web siteleriniz uçacak!','2026-09-13 12:43:39'),(10,'tr','menu.kurumsal-hosting','Kurumsal Hosting','2026-09-13 12:43:39'),(11,'tr','menu.tamamen-yuksek-kaynaklar-ile-yapilandirilmis-kurumsal-hosting-paketlerimiz-ile-t','Tamamen Yüksek kaynaklar ile yapılandırılmış Kurumsal Hosting paketlerimiz ile trafik ve mail sorunu yaşamayacaksınız!','2026-09-13 12:43:39'),(12,'tr','menu.e-ticaret-hosting','E-Ticaret Hosting','2026-09-13 12:43:39'),(13,'tr','menu.web-site-builder','Web Site Builder','2026-09-13 12:43:39'),(14,'tr','menu.hazir-websitesi-araci-ile-harika-bir-web-sitesi-olusturmak-icin-hizlica-siparis-','Hazır websitesi aracı ile Harika bir web sitesi oluşturmak için hızlıca sipariş verebilirsiniz!.','2026-09-13 12:43:39'),(15,'tr','menu.windows-hosting','Windows Hosting','2026-09-13 12:43:39'),(16,'tr','menu.web-sitenizde-asp-net-mvc-mssql-gibi-teknolojileri-kullaniyorsaniz-windows-planl','Web Sitenizde ASP, .NET, MVC  MSSQL gibi teknolojileri kullanıyorsanız, Windows planlarımıza göz atmanızı öneririz.','2026-09-13 12:43:39'),(17,'tr','menu.ozel-hosting','Özel Hosting','2026-09-13 12:43:39'),(18,'tr','menu.wordpress-hosting','Wordpress Hosting','2026-09-13 12:43:39'),(19,'tr','menu.wordpress-hosting-paketimiz-sayesinde-ziyaretcilerinize-hizli-bir-blog-sunun','Wordpress Hosting paketimiz sayesinde ziyaretçilerinize hızlı bir blog sunun!','2026-09-13 12:43:39'),(20,'tr','menu.arsiv-hosting','Arşiv Hosting','2026-09-13 12:43:39'),(21,'tr','menu.tablet-telefon-bilgisayar-cihazlariniz-uzerinde-bulundurdugunuz-dosya-ve-yedekle','Tablet, Telefon, Bilgisayar cihazlarınız üzerinde bulundurduğunuz dosya ve yedeklere her an her yerden erişmek ve düzenlemek ister misiniz?','2026-09-13 12:43:39'),(22,'tr','menu.sunucu','Sunucu','2026-09-13 12:43:39'),(23,'tr','menu.sunucu-cozumleri','Sunucu Çözümleri','2026-09-13 12:43:39'),(24,'tr','menu.btk-log-sunucu','BTK Log Sunucu','2026-09-13 12:43:39'),(25,'tr','menu.5651-sayili-kanun-geregi-tutmaniz-gereken-internet-erisim-kayitlarini-zaman-damg','5651 sayılı kanun gereği tutmanız gereken internet erişim kayıtlarını, zaman damgalı ve imzalı biçimde yasal süre boyunca güvenle saklayın.','2026-09-13 12:43:39'),(26,'tr','menu.vds-vps','VDS & VPS','2026-09-13 12:43:39'),(27,'tr','menu.vds-sunucu','VDS Sunucu','2026-09-13 12:43:39'),(28,'tr','menu.tam-root-erisimi-ve-size-ayrilmis-garantili-kaynaklar-projeniz-buyudukce-cpu-ram','Tam root erişimi ve size ayrılmış garantili kaynaklar. Projeniz büyüdükçe CPU, RAM ve diski dakikalar içinde yükseltin.','2026-09-13 12:43:39'),(29,'tr','menu.fiziksel-sunucular','Fiziksel Sunucular','2026-09-13 12:43:39'),(30,'tr','menu.fiziksel-sunucu','Fiziksel Sunucu','2026-09-13 12:43:39'),(31,'tr','menu.donanimi-kimseyle-paylasmayin-yuksek-trafikli-projeleriniz-icin-tamamen-size-ait','Donanımı kimseyle paylaşmayın. Yüksek trafikli projeleriniz için tamamen size ait, maksimum performanslı fiziksel sunucular.','2026-09-13 12:43:39'),(32,'tr','menu.ssl','SSL','2026-09-13 12:43:39'),(33,'tr','menu.diger-hizmetler','Diğer Hizmetler','2026-09-13 12:43:39'),(34,'tr','menu.yazilim','Yazılım','2026-09-13 12:43:39'),(35,'tr','menu.yazilim-hizmetleri','Yazılım Hizmetleri','2026-09-13 12:43:39'),(36,'tr','menu.crm-erp-web-projeleri-mobil-uygulama-taleplerinizi-profesyonel-muhendis-ekibimiz','CRM, ERP, Web Projeleri, Mobil Uygulama taleplerinizi profesyonel mühendis ekibimiz ile hayata geçiriyoruz.','2026-09-13 12:43:39'),(37,'tr','menu.ek-hizmetler','Ek Hizmetler','2026-09-13 12:43:39'),(38,'tr','menu.hotspot-hizmeti','Hotspot Hizmeti','2026-09-13 12:43:39'),(39,'tr','menu.cafe-restoran-otel-musterilerinize-guvenli-bir-wifi-alt-yapisi-sunablir-ve-5651-','Cafe, Restoran, Otel müşterilerinize güvenli bir WİFİ alt yapısı sunablir ve 5651 Loglama ile güvenli internet sağlayabilirsiniz.','2026-09-13 12:43:39'),(40,'tr','menu.destek','Destek','2026-09-13 12:43:39'),(41,'tr','menu.marka-tescil','Marka Tescil','2026-09-13 12:43:39'),(42,'tr','menu.markanizi-koruma-altina-alin-profesyonel-marka-tescil-hizmeti-ile-isletmenizi-gu','Markanızı koruma altına alın. Profesyonel marka tescil hizmeti ile işletmenizi güvence altına alıyoruz.','2026-09-13 12:43:39'),(43,'tr','menu.iletisim','İletişim','2026-09-13 12:43:39'),(44,'tr','ortak.sepet','Sepet','2026-09-13 12:43:39'),(45,'tr','ortak.giris-yap','Giriş Yap','2026-09-13 12:43:39'),(46,'tr','footer.tanitim','Profesyonel hosting, VDS, cloud sunucu ve domain hizmetleri ile projelerinizi güçlendirin. 7/24 teknik destek ve %99.9 uptime garantisi.','2026-09-13 12:43:39'),(47,'tr','footer.hizmetlerimiz','Hizmetlerimiz','2026-09-13 12:43:39'),(48,'tr','footer.web-hosting','Web Hosting','2026-09-13 12:43:39'),(49,'tr','footer.vds-sunucu','VDS Sunucu','2026-09-13 12:43:39'),(50,'tr','footer.cloud-sunucu','Cloud Sunucu','2026-09-13 12:43:39'),(51,'tr','footer.dedicated-sunucu','Dedicated Sunucu','2026-09-13 12:43:39'),(52,'tr','footer.domain-kaydi','Domain Kaydı','2026-09-13 12:43:39'),(53,'tr','footer.ssl-sertifikasi','SSL Sertifikası','2026-09-13 12:43:39'),(54,'tr','footer.kurumsal','Kurumsal','2026-09-13 12:43:39'),(55,'tr','footer.hakkimizda','Hakkımızda','2026-09-13 12:43:39'),(56,'tr','footer.iletisim','İletişim','2026-09-13 12:43:39'),(57,'tr','footer.referanslar','Referanslar','2026-09-13 12:43:39'),(58,'tr','footer.blog','Blog','2026-09-13 12:43:39'),(59,'tr','footer.kariyer','Kariyer','2026-09-13 12:43:39'),(60,'tr','footer.destek','Destek','2026-09-13 12:43:39'),(61,'tr','footer.bilgi-bankasi','Bilgi Bankası','2026-09-13 12:43:39'),(62,'tr','footer.destek-talebi','Destek Talebi','2026-09-13 12:43:39'),(63,'tr','footer.sunucu-durumu','Sunucu Durumu','2026-09-13 12:43:39'),(64,'tr','footer.sla','SLA','2026-09-13 12:43:39'),(65,'tr','footer.yasal','Yasal','2026-09-13 12:43:39'),(66,'tr','footer.kullanim-sartlari','Kullanım Şartları','2026-09-13 12:43:39'),(67,'tr','footer.gizlilik-politikasi','Gizlilik Politikası','2026-09-13 12:43:39'),(68,'tr','footer.kvkk','KVKK','2026-09-13 12:43:39'),(69,'tr','footer.iptal-ve-iade','İptal ve İade','2026-09-13 12:43:39'),(70,'tr','footer.haklar','Tüm hakları saklıdır.','2026-09-13 12:43:39'),(71,'tr','anasayfa.rozet','Veri Merkezi','2026-09-13 12:44:40'),(72,'tr','anasayfa.baslik','Kabinetten buluta, <em>tek tesis</em> altında','2026-09-13 12:44:40'),(73,'tr','anasayfa.aciklama','Kendi donanımınızı kabinetimize yerleştirin ya da hazır sunucularımızı kiralayın. Yedekli enerji, kontrollü iklimlendirme ve kesintisiz izleme tesisin standardıdır.','2026-09-13 12:44:40'),(74,'tr','ortak.teklif-alin','Teklif Alın','2026-09-13 12:44:40'),(75,'tr','ortak.hizmetleri-inceleyin','Hizmetleri İnceleyin','2026-09-13 12:44:40'),(76,'tr','anasayfa.vds-etiket','Dakikalar içinde teslim','2026-09-13 12:44:40'),(77,'tr','anasayfa.vds-baslik','Sunucunuzu kendiniz yapılandırın','2026-09-13 12:44:40'),(78,'tr','anasayfa.vds-aciklama','İşlemci, bellek, disk ve IP sayısını seçin; fiyat siz seçtikçe güncellensin.','2026-09-13 12:44:40'),(79,'tr','hizmet.kabinet-barindirma','Kabinet Barındırma','2026-09-13 12:44:40'),(80,'tr','hizmet.tam-yarim-u-bazli','Tam / yarım / U bazlı','2026-09-13 12:44:40'),(81,'tr','hizmet.fiziksel-sunucu','Fiziksel Sunucu','2026-09-13 12:44:40'),(82,'tr','hizmet.tamamen-size-tahsisli','Tamamen size tahsisli','2026-09-13 12:44:40'),(83,'tr','serit.calisma-suresi','Çalışma Süresi','2026-09-13 12:44:40'),(84,'tr','serit.yillik-uptime-hedefi','Yıllık uptime hedefi','2026-09-13 12:44:40'),(85,'tr','serit.enerji','Enerji','2026-09-13 12:44:40'),(86,'tr','serit.ups-ve-jenerator-yedekliligi','UPS ve jeneratör yedekliliği','2026-09-13 12:44:40'),(87,'tr','serit.iklimlendirme','İklimlendirme','2026-09-13 12:44:40'),(88,'tr','serit.hassas-kontrollu-kogus-sicakligi','Hassas kontrollü koğuş sıcaklığı','2026-09-13 12:44:40'),(89,'tr','serit.operasyon','Operasyon','2026-09-13 12:44:40'),(90,'tr','serit.izleme-ve-teknik-mudahale','İzleme ve teknik müdahale','2026-09-13 12:44:40'),(91,'tr','etiket.tesis','Tesis','2026-09-13 12:44:40'),(92,'tr','tesis.baslik','Veri merkezi perspektifi','2026-09-13 12:44:40'),(93,'tr','tesis.aciklama','Tesisin bölüm yerleşimi: enerji, soğutma, telekom ve kabinet salonları. Bölüm başlığına tıklayarak ayrıntısını görebilirsiniz.','2026-09-13 12:44:40'),(94,'tr','tesis.jenerator-grubu','Jeneratör grubu','2026-09-13 12:44:40'),(95,'tr','tesis.lobi-ve-giris','Lobi ve giriş','2026-09-13 12:44:40'),(96,'tr','tesis.toplanti-odalari','Toplantı odaları','2026-09-13 12:44:40'),(97,'tr','tesis.noc','NOC','2026-09-13 12:44:40'),(98,'tr','tesis.ofisler','Ofisler','2026-09-13 12:44:40'),(99,'tr','tesis.telekom-odasi','Telekom odası','2026-09-13 12:44:40'),(100,'tr','tesis.depo','Depo','2026-09-13 12:44:40'),(101,'tr','tesis.yangin-sondurme','Yangın söndürme','2026-09-13 12:44:40'),(102,'tr','tesis.ups','UPS','2026-09-13 12:44:40'),(103,'tr','tesis.elektrik-odasi','Elektrik odası','2026-09-13 12:44:40'),(104,'tr','tesis.crac-uniteleri','CRAC üniteleri','2026-09-13 12:44:40'),(105,'tr','tesis.salon-1','Salon-1','2026-09-13 12:44:40'),(106,'tr','tesis.salon-2','Salon-2','2026-09-13 12:44:40'),(107,'tr','tesis.n-1-yedekli-besleme','N+1 yedekli besleme','2026-09-13 12:44:40'),(108,'tr','tesis.sehir-sebekesinde-kesinti-oldugunda-jenerator-grubu-otomatik-olarak-devreye-gire','Şehir şebekesinde kesinti olduğunda jeneratör grubu otomatik olarak devreye girer ve tesisin tüm yükünü üstlenir. Jeneratörler tam yüke ulaşana kadar geçen sürede besleme UPS sistemlerinden karşılanır, bu yüzden sunucular kesinti hissetmez.','2026-09-13 12:44:40'),(109,'tr','tesis.n-1-yedeklilik','N+1 yedeklilik','2026-09-13 12:44:40'),(110,'tr','tesis.otomatik-devreye-girme','Otomatik devreye girme','2026-09-13 12:44:40'),(111,'tr','tesis.yakit-stogu-ve-periyodik-test','Yakıt stoğu ve periyodik test','2026-09-13 12:44:40'),(112,'tr','tesis.kontrollu-fiziksel-erisim','Kontrollü fiziksel erişim','2026-09-13 12:44:40'),(113,'tr','tesis.tesise-giris-randevu-ile-yapilir-ziyaretci-kaydi-alinir-kimlik-dogrulamasi-yapil','Tesise giriş randevu ile yapılır. Ziyaretçi kaydı alınır, kimlik doğrulaması yapılır ve salonlara yetkili personel eşliğinde geçilir. Giriş noktaları kamera ile sürekli izlenir.','2026-09-13 12:44:40'),(114,'tr','tesis.randevulu-giris','Randevulu giriş','2026-09-13 12:44:40'),(115,'tr','tesis.ziyaretci-kaydi','Ziyaretçi kaydı','2026-09-13 12:44:40'),(116,'tr','tesis.kamera-ile-7-24-izleme','Kamera ile 7/24 izleme','2026-09-13 12:44:40'),(117,'tr','tesis.musteri-gorusmeleri','Müşteri görüşmeleri','2026-09-13 12:44:40'),(118,'tr','tesis.kurulum-planlamasi-kapasite-artisi-ve-teknik-gorusmeler-icin-musterilere-ayrilmi','Kurulum planlaması, kapasite artışı ve teknik görüşmeler için müşterilere ayrılmış çalışma alanları. Tesiste işlem yapacak ekipler burada hazırlık yapabilir.','2026-09-13 12:44:40'),(119,'tr','tesis.kurulum-planlamasi','Kurulum planlaması','2026-09-13 12:44:40'),(120,'tr','tesis.teknik-gorusmeler','Teknik görüşmeler','2026-09-13 12:44:40'),(121,'tr','tesis.7-24-izleme-merkezi','7/24 izleme merkezi','2026-09-13 12:44:40'),(122,'tr','tesis.network-operations-center-tesisin-ve-musteri-hizmetlerinin-kesintisiz-izlendigi-','Network Operations Center, tesisin ve müşteri hizmetlerinin kesintisiz izlendiği merkezdir. Sıcaklık, nem, güç tüketimi ve ağ trafiği sürekli takip edilir; belirlenen eşik aşıldığında nöbetçi ekip müdahale eder.','2026-09-13 12:44:40'),(123,'tr','tesis.7-24-izleme','7/24 izleme','2026-09-13 12:44:40'),(124,'tr','tesis.sicaklik-nem-ve-guc-takibi','Sıcaklık, nem ve güç takibi','2026-09-13 12:44:40'),(125,'tr','tesis.olay-mudahale','Olay müdahale','2026-09-13 12:44:40'),(126,'tr','tesis.yonetim-ve-teknik-ekip','Yönetim ve teknik ekip','2026-09-13 12:44:40'),(127,'tr','tesis.yonetim-satis-ve-teknik-ekiplerin-calisma-alani-saha-mudahalesi-gerektiren-durum','Yönetim, satış ve teknik ekiplerin çalışma alanı. Saha müdahalesi gerektiren durumlarda ekip aynı bina içinde olduğu için yerinde işlem hızlı yapılır.','2026-09-13 12:44:40'),(128,'tr','tesis.hafta-ici-09-00-18-00','Hafta içi 09:00 – 18:00','2026-09-13 12:44:40'),(129,'tr','tesis.operator-baglantilari','Operatör bağlantıları','2026-09-13 12:44:40'),(130,'tr','tesis.operator-baglantilarinin-tesise-girdigi-ve-dagitildigi-odadir-omurga-baglantilar','Operatör bağlantılarının tesise girdiği ve dağıtıldığı odadır. Omurga bağlantıları burada sonlanır, salonlara buradan taşınır. Trafik filtreleme ve yönlendirme de bu noktada yapılır.','2026-09-13 12:44:40'),(131,'tr','tesis.yuksek-kapasiteli-omurga','Yüksek kapasiteli omurga','2026-09-13 12:44:40'),(132,'tr','tesis.ddos-filtreleme','DDoS filtreleme','2026-09-13 12:44:40'),(133,'tr','tesis.ipv4-tahsisi-ve-yonlendirme','IPv4 tahsisi ve yönlendirme','2026-09-13 12:44:40'),(134,'tr','tesis.yedek-donanim','Yedek donanım','2026-09-13 12:44:40'),(135,'tr','tesis.yedek-disk-guc-kaynagi-kablo-ve-sarf-malzemesinin-bulundugu-alan-arizali-bilesen','Yedek disk, güç kaynağı, kablo ve sarf malzemesinin bulunduğu alan. Arızalı bileşenin hızlı değiştirilebilmesi için yedek parça tesiste tutulur.','2026-09-13 12:44:40'),(136,'tr','tesis.sarf-malzemesi','Sarf malzemesi','2026-09-13 12:44:40'),(137,'tr','tesis.hizli-parca-degisimi','Hızlı parça değişimi','2026-09-13 12:44:40'),(138,'tr','tesis.gazli-sondurme-sistemi','Gazlı söndürme sistemi','2026-09-13 12:44:40'),(139,'tr','tesis.salonlarda-erken-duman-algilama-ve-gazli-sondurme-sistemi-bulunur-gazli-sistem-s','Salonlarda erken duman algılama ve gazlı söndürme sistemi bulunur. Gazlı sistem, su kullanmadığı için yangını donanıma zarar vermeden bastırır.','2026-09-13 12:44:40'),(140,'tr','tesis.erken-duman-algilama','Erken duman algılama','2026-09-13 12:44:40'),(141,'tr','tesis.yangin-algilama-sensorleri','Yangın algılama sensörleri','2026-09-13 12:44:40'),(142,'tr','tesis.kesintisiz-guc-kaynagi','Kesintisiz güç kaynağı','2026-09-13 12:44:40'),(143,'tr','tesis.kesintisiz-guc-kaynaklari-sebeke-kesildigi-anda-yuku-aku-grubundan-beslemeye-gec','Kesintisiz güç kaynakları, şebeke kesildiği anda yükü akü grubundan beslemeye geçer. Jeneratör devreye girene kadar hiçbir sunucu kapanmaz.','2026-09-13 12:44:40'),(144,'tr','tesis.kesintisiz-besleme','Kesintisiz besleme','2026-09-13 12:44:40'),(145,'tr','tesis.aku-grubu','Akü grubu','2026-09-13 12:44:40'),(146,'tr','tesis.yedekli-unite-yapisi','Yedekli ünite yapısı','2026-09-13 12:44:40'),(147,'tr','tesis.ana-dagitim-panosu','Ana dağıtım panosu','2026-09-13 12:44:40'),(148,'tr','tesis.ana-dagitim-panosundan-kabinetlere-kadar-enerji-dagitimi-buradan-yonetilir-her-k','Ana dağıtım panosundan kabinetlere kadar enerji dağıtımı buradan yönetilir. Her kabinetin tüketimi ayrı ölçülür, böylece faturalama ve kapasite planlaması gerçek tüketime dayanır.','2026-09-13 12:44:40'),(149,'tr','tesis.yedekli-besleme-yollari','Yedekli besleme yolları','2026-09-13 12:44:40'),(150,'tr','tesis.kabinet-basina-olcum','Kabinet başına ölçüm','2026-09-13 12:44:40'),(151,'tr','tesis.n-1-yedekli-sogutma','N+1 yedekli soğutma','2026-09-13 12:44:40'),(152,'tr','tesis.hassas-kontrollu-klima-uniteleri-kogus-sicakligini-18-24-c-bagil-nemi-40-60-aral','Hassas kontrollü klima üniteleri koğuş sıcaklığını 18 – 24 °C, bağıl nemi %40 – %60 aralığında tutar. Üniteler N+1 yedeklidir; biri devre dışı kalsa da sıcaklık korunur.','2026-09-13 12:44:40'),(153,'tr','tesis.18-24-c-kogus-sicakligi','18 – 24 °C koğuş sıcaklığı','2026-09-13 12:44:40'),(154,'tr','tesis.sicak-soguk-koridor-duzeni','Sıcak/soğuk koridor düzeni','2026-09-13 12:44:40'),(155,'tr','tesis.kabinet-kogusu','Kabinet koğuşu','2026-09-13 12:44:40'),(156,'tr','tesis.yukseltilmis-doseme-soguk-koridor-kapatma-ve-kilitli-kabinetlerden-olusan-kabine','Yükseltilmiş döşeme, soğuk koridor kapatma ve kilitli kabinetlerden oluşan kabinet koğuşu. Kendi donanımınızı tam, yarım ya da U bazlı kabinet olarak barındırabilirsiniz.','2026-09-13 12:44:40'),(157,'tr','tesis.tam-yarim-ve-u-bazli-kabinet','Tam, yarım ve U bazlı kabinet','2026-09-13 12:44:40'),(158,'tr','tesis.kilitli-kabinet','Kilitli kabinet','2026-09-13 12:44:40'),(159,'tr','tesis.randevulu-erisim','Randevulu erişim','2026-09-13 12:44:40'),(160,'tr','tesis.ikinci-kabinet-kogusu-salon-1-ile-ayni-standartta-yukseltilmis-doseme-koridor-du','İkinci kabinet koğuşu; Salon-1 ile aynı standartta yükseltilmiş döşeme, koridor düzeni ve erişim kontrolü uygulanır. Kapasite artışları bu salondan karşılanır.','2026-09-13 12:44:40'),(161,'tr','tesis.salon-1-ile-ayni-standart','Salon-1 ile aynı standart','2026-09-13 12:44:40'),(162,'tr','tesis.kapasite-artisi','Kapasite artışı','2026-09-13 12:44:40'),(163,'tr','etiket.hizmet-hatlari','Hizmet Hatları','2026-09-13 12:44:40'),(164,'tr','hatlar.baslik','Donanımınız bizde, ya da bizim donanımımız sizde','2026-09-13 12:44:40'),(165,'tr','hatlar.aciklama','Kabinet barındırmadan sanal sunucuya kadar dört ana hizmet hattı.','2026-09-13 12:44:40'),(166,'tr','hizmet.kendi-donaniminizi-veri-merkezimizde-barindirin-guc-sogutma-ve-baglanti-bizden','Kendi donanımınızı veri merkezimizde barındırın. Güç, soğutma ve bağlantı bizden.','2026-09-13 12:44:40'),(167,'tr','kunye.kabinet-tipi','Kabinet tipi','2026-09-13 12:44:40'),(168,'tr','kunye.tam-yarim-u-bazli','Tam / yarım / U bazlı','2026-09-13 12:44:40'),(169,'tr','kunye.enerji','Enerji','2026-09-13 12:44:40'),(170,'tr','kunye.olcumlenen-besleme','Ölçümlenen besleme','2026-09-13 12:44:40'),(171,'tr','kunye.erisim','Erişim','2026-09-13 12:44:40'),(172,'tr','kunye.randevulu-kilitli-kabinet','Randevulu, kilitli kabinet','2026-09-13 12:44:40'),(173,'tr','ortak.teklife-gore','Teklife göre','2026-09-13 12:44:40'),(174,'tr','ortak.kapasiteye-gore','Kapasiteye göre fiyatlanır','2026-09-13 12:44:40'),(175,'tr','hizmet.kaynaklarini-kimseyle-paylasmayan-tamamen-size-tahsis-edilmis-donanim','Kaynaklarını kimseyle paylaşmayan, tamamen size tahsis edilmiş donanım.','2026-09-13 12:44:40'),(176,'tr','kunye.donanim','Donanım','2026-09-13 12:44:40'),(177,'tr','kunye.tamamen-size-tahsisli','Tamamen size tahsisli','2026-09-13 12:44:40'),(178,'tr','kunye.depolama','Depolama','2026-09-13 12:44:40'),(179,'tr','kunye.nvme-ssd','NVMe SSD','2026-09-13 12:44:40'),(180,'tr','kunye.yonetim','Yönetim','2026-09-13 12:44:40'),(181,'tr','kunye.ipmi-uzaktan-erisim','IPMI / uzaktan erişim','2026-09-13 12:44:40'),(182,'tr','ortak.aylik-baslangic','aylık başlangıç · KDV hariç','2026-09-13 12:44:40'),(183,'tr','ortak.incele','İncele','2026-09-13 12:44:40'),(184,'tr','hizmet.sanal-sunucu-vds','Sanal Sunucu (VDS)','2026-09-13 12:44:40'),(185,'tr','hizmet.islemci-bellek-ve-diski-kendiniz-belirleyin-dakikalar-icinde-teslim','İşlemci, bellek ve diski kendiniz belirleyin; dakikalar içinde teslim.','2026-09-13 12:44:40'),(186,'tr','kunye.yapilandirma','Yapılandırma','2026-09-13 12:44:40'),(187,'tr','kunye.vcpu-ram-ve-disk-size-ait','vCPU, RAM ve disk size ait','2026-09-13 12:44:40'),(188,'tr','kunye.sanallastirma','Sanallaştırma','2026-09-13 12:44:40'),(189,'tr','kunye.kvm','KVM','2026-09-13 12:44:40'),(190,'tr','kunye.teslim','Teslim','2026-09-13 12:44:40'),(191,'tr','kunye.dakikalar-icinde','Dakikalar içinde','2026-09-13 12:44:40'),(192,'tr','hizmet.yasal-log-kaydi','Yasal Log Kaydı','2026-09-13 12:44:40'),(193,'tr','hizmet.5651-sayili-kanun-kapsaminda-zaman-damgali-erisim-kaydi-saklama','5651 sayılı kanun kapsamında zaman damgalı erişim kaydı saklama.','2026-09-13 12:44:40'),(194,'tr','kunye.kapsam','Kapsam','2026-09-13 12:44:40'),(195,'tr','kunye.5651-sayili-kanun','5651 sayılı kanun','2026-09-13 12:44:40'),(196,'tr','kunye.kayit','Kayıt','2026-09-13 12:44:40'),(197,'tr','kunye.zaman-damgali-imzali','Zaman damgalı, imzalı','2026-09-13 12:44:40'),(198,'tr','kunye.saklama','Saklama','2026-09-13 12:44:40'),(199,'tr','kunye.yasal-sure-boyunca','Yasal süre boyunca','2026-09-13 12:44:40'),(200,'tr','etiket.barindirma','Barındırma','2026-09-13 12:44:40'),(201,'tr','katalog.baslik','Hazır barındırma paketleri','2026-09-13 12:44:40'),(202,'tr','katalog.aciklama','Kendi sunucunuzu yönetmek istemiyorsanız, altyapımız üzerinde hazır paketler.','2026-09-13 12:44:40'),(203,'tr','katalog.sutun-hizmet','HİZMET','2026-09-13 12:44:40'),(204,'tr','katalog.sutun-ozellik','GİRİŞ PAKETİ ÖZELLİKLERİ','2026-09-13 12:44:40'),(205,'tr','katalog.sutun-paket','PAKET','2026-09-13 12:44:40'),(206,'tr','katalog.sutun-fiyat','AYLIK BAŞLANGIÇ','2026-09-13 12:44:40'),(207,'tr','hizmet.linux-hosting','Linux Hosting','2026-09-13 12:44:40'),(208,'tr','hizmet.paylasimli-barindirma','Paylaşımlı barındırma','2026-09-13 12:44:40'),(209,'tr','ozellik.1000-mb-nvme-disk-alani','1000 MB Nvme Disk Alanı','2026-09-13 12:44:40'),(210,'tr','ozellik.limitsiz-aylik-trafik','Limitsiz Aylik Trafik','2026-09-13 12:44:40'),(211,'tr','ozellik.1024-mb-ram-kullanimi','1024 MB RAM Kullanımı','2026-09-13 12:44:40'),(212,'tr','hizmet.ekran-kartli-sunucu','Ekran Kartlı Sunucu','2026-09-13 12:44:40'),(213,'tr','hizmet.gpu-destekli-hesaplama','GPU destekli hesaplama','2026-09-13 12:44:40'),(214,'tr','ozellik.rtx-4060-ekran-karti','RTX 4060 Ekran Kartı','2026-09-13 12:44:40'),(215,'tr','ozellik.8gb-ddr4-ram','8GB DDR4 Ram','2026-09-13 12:44:40'),(216,'tr','ozellik.240-gb-ssd-disk','240 GB SSD Disk','2026-09-13 12:44:40'),(217,'tr','hizmet.windows-hosting','Windows Hosting','2026-09-13 12:44:40'),(218,'tr','hizmet.windows-asp-net','Windows / ASP.NET','2026-09-13 12:44:40'),(219,'tr','ozellik.500-mb-disk','500 MB Disk','2026-09-13 12:44:40'),(220,'tr','ozellik.100-mb-trafik','100 MB TRafik','2026-09-13 12:44:40'),(221,'tr','ozellik.test-alani','Test Alanı','2026-09-13 12:44:40'),(222,'tr','hizmet.kurumsal-hosting','Kurumsal Hosting','2026-09-13 12:44:40'),(223,'tr','hizmet.kurumsal-e-posta-ve-site','Kurumsal e-posta ve site','2026-09-13 12:44:40'),(224,'tr','ozellik.500-mb-disk-alani','500 MB Disk Alanı','2026-09-13 12:44:40'),(225,'tr','ozellik.1500-gb-aylik-trafik','1500 GB Aylık Trafik','2026-09-13 12:44:40'),(226,'tr','ozellik.50-adet-kurumsal-e-posta','50 Adet Kurumsal E-Posta','2026-09-13 12:44:40'),(227,'tr','hizmet.arsiv-hosting','Arşiv Hosting','2026-09-13 12:44:40'),(228,'tr','hizmet.depolama-ve-yedek','Depolama ve yedek','2026-09-13 12:44:40'),(229,'tr','ozellik.1-tb-yedekleme-alani','1 TB Yedekleme Alanı','2026-09-13 12:44:40'),(230,'tr','ozellik.1-tb-aylik-trafik','1 TB Aylık Trafik','2026-09-13 12:44:40'),(231,'tr','ozellik.1000-mbps-internet-hizi','1000 Mbps İnternet Hızı','2026-09-13 12:44:40'),(232,'tr','katalog.dipnot','Fiyatlar aylık ve KDV hariçtir. Özellik sütunu her hizmetin en düşük fiyatlı paketine aittir; diğer paketler için hizmeti inceleyin.','2026-09-13 12:44:40'),(233,'tr','etiket.referanslar','Referanslar','2026-09-13 12:44:40'),(234,'tr','referans.baslik','Altyapımızı kullanan kurumlar','2026-09-13 12:44:40'),(235,'tr','etiket.iletisim','İletişim','2026-09-13 12:44:40'),(236,'tr','iletisim.baslik','Tesisimizi yerinde görmek ister misiniz?','2026-09-13 12:51:53'),(237,'tr','iletisim.aciklama','Kabinet ihtiyacınızı, enerji ve bağlantı gereksinimlerinizi konuşalım. Randevu oluşturup veri merkezimizi yerinde inceleyebilirsiniz.','2026-09-13 12:44:41'),(238,'tr','iletisim.randevu','Randevu Talep Edin','2026-09-13 12:44:41'),(239,'tr','iletisim.satis-hatti','Satış hattı · hafta içi 09:00 – 18:00','2026-09-13 12:44:41'),(240,'tr','iletisim.eposta-not','Teklif ve sorularınız için','2026-09-13 12:44:41'),(241,'tr','iletisim.destek-talebi','Müşteri paneli','2026-09-13 12:51:53'),(242,'tr','iletisim.destek-not','7/24 destek kaydı açabilirsiniz','2026-09-13 12:51:53'),(246,'en','iletisim.eposta-not','For quotes and questions','2026-09-13 12:45:51'),(247,'en','ortak.teklif-alin','Get a Quote','2026-09-13 12:45:51'),(248,'en','ortak.teklife-gore','On request','2026-09-13 12:45:51'),(249,'ar','iletisim.eposta-not','للعروض والاستفسارات','2026-09-13 12:45:51'),(250,'ar','ortak.teklif-alin','اطلب عرض سعر','2026-09-13 12:45:51'),(251,'ar','ortak.teklife-gore','حسب الطلب','2026-09-13 12:45:51'),(3100,'tr','iletisim.anahtar-telefon','Telefon','2026-09-13 12:51:26'),(3101,'tr','iletisim.anahtar-eposta','E-posta','2026-09-13 12:51:26'),(3102,'tr','iletisim.anahtar-adres','Adres','2026-09-13 12:51:26'),(3103,'tr','iletisim.adres-not','Tesis konumu · haritada aç','2026-09-13 12:51:26'),(3104,'tr','iletisim.anahtar-destek','Destek','2026-09-13 12:51:26'),(4539,'tr','serit.uptime-not','yıllık hedef','2026-09-13 13:32:40'),(4540,'tr','serit.enerji-not','yedekli besleme','2026-09-13 13:32:40'),(4541,'tr','serit.iklim-not','koğuş sıcaklığı','2026-09-13 13:32:40'),(4542,'tr','serit.operasyon-not','izleme ve müdahale','2026-09-13 13:32:40'),(5021,'tr','katalog.giris-paketi','Giriş paketinde neler var','2026-09-13 13:35:46'),(5022,'tr','ozellik.5-adet-e-posta-hesabi','5 Adet E-posta Hesabı.','2026-09-13 13:35:46'),(5023,'tr','ozellik.cpu-1-core-cpu-kullanimi','Cpu  1 Core CPU Kullanımı','2026-09-13 13:35:46'),(5024,'tr','ozellik.veri-tabani-1-adet-mysql-veritabani','Veri Tabanı  1 Adet MySQL Veritabanı','2026-09-13 13:35:46'),(5025,'tr','katalog.paket-secenegi','paket seçeneği','2026-09-13 13:35:46'),(5026,'tr','katalog.kdv','Aylık, KDV hariç','2026-09-13 13:35:46'),(5027,'tr','katalog.paketleri-gor','Paketleri gör','2026-09-13 13:35:46'),(5028,'tr','ozellik.isp-ip-adresi','ISP IP Adresi','2026-09-13 13:35:46'),(5029,'tr','ozellik.bursa-lokasyon','Bursa Lokasyon','2026-09-13 13:35:46'),(5030,'tr','ozellik.test-paket','Test paket','2026-09-13 13:35:46'),(5031,'tr','ozellik.test-paketm','Test Paketm','2026-09-13 13:35:46'),(5032,'tr','ozellik.5-adet-ftp-kullanicisi','5 Adet FTP Kullanıcısı','2026-09-13 13:35:46'),(5033,'tr','ozellik.ucretsiz-let-s-ssl','Ücretsiz Let\'s SSL','2026-09-13 13:35:46'),(5034,'tr','ozellik.2-kullanici','2 Kullanıcı','2026-09-13 13:35:46'),(5531,'tr','menu.wordpress-icin-ayarlanmis-litespeed-cache-ve-wp-toolkit-ile-gelen-hosting-paketl','WordPress için ayarlanmış, LiteSpeed Cache ve WP Toolkit ile gelen hosting paketleri.','2026-09-13 13:48:59'),(5532,'tr','ozellik.2-gb-ssd-disk-alani','2 GB SSD Disk Alanı','2026-09-13 13:51:57'),(5533,'tr','ozellik.1-core-cpu-kullanimi','1 Core CPU Kullanımı','2026-09-13 13:51:57'),(5534,'tr','ozellik.10-adet-e-posta-hesabi','10 Adet E-posta Hesabı','2026-09-13 13:51:57'),(5535,'tr','ozellik.veri-tabani-1-adet-mssql-veritabani','Veri Tabanı  1 Adet MSSQL Veritabanı','2026-09-13 13:51:57'),(5536,'tr','hizmet.wordpress-hosting','Wordpress Hosting','2026-09-13 13:52:10'),(5537,'tr','hizmet.wordpress-icin-ayarli','WordPress için ayarlı','2026-09-13 13:52:10'),(5538,'tr','ozellik.5-gb-nvme-disk-alani','5 GB NVMe Disk Alanı','2026-09-13 13:52:10'),(5539,'tr','ozellik.ftp-sftp-ftps-erisimi','FTP / SFTP / FTPS Erişimi','2026-09-13 13:57:59'),(5540,'tr','ozellik.webdav-ile-surucu-baglama','WebDAV ile Sürücü Bağlama','2026-09-13 13:57:59'),(5541,'tr','menu.kod-yazmadan-surukle-birak-editorle-dakikalar-icinde-web-sitenizi-yayina-alin','Kod yazmadan, sürükle-bırak editörle dakikalar içinde web sitenizi yayına alın.','2026-09-13 13:59:59'),(5542,'tr','hizmet.web-site-builder','Web Site Builder','2026-09-13 14:00:46'),(5543,'tr','hizmet.kodsuz-site-kurucu','Kodsuz site kurucu','2026-09-13 14:00:46'),(5544,'tr','ozellik.1-web-sitesi','1 Web Sitesi','2026-09-13 14:00:46'),(5545,'tr','ozellik.5-sayfa-hakki','5 Sayfa Hakkı','2026-09-13 14:00:46'),(5546,'tr','ozellik.5-gb-disk-alani','5 GB Disk Alanı','2026-09-13 14:00:46'),(5547,'tr','ozellik.surukle-birak-editor','Sürükle-Bırak Editör','2026-09-13 14:00:46'),(5548,'tr','ozellik.50-hazir-sablon','50+ Hazır Şablon','2026-09-13 14:00:46'),(5549,'tr','menu.ssl-sertifikasi','SSL Sertifikası','2026-09-13 14:17:09'),(5550,'tr','menu.fiber-internet-hatti-hotspot-donanimi-ve-5651-uyumlu-log-kaydi-tek-pakette','Fiber internet hattı, hotspot donanımı ve 5651 uyumlu log kaydı tek pakette.','2026-09-13 14:25:55');
/*!40000 ALTER TABLE `translations` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `currencies` WRITE;
/*!40000 ALTER TABLE `currencies` DISABLE KEYS */;
INSERT INTO `currencies` (`id`, `code`, `name`, `symbol`, `format`, `exchange_rate`, `is_default`, `is_active`, `created_at`, `updated_at`) VALUES (1,'TRY','Türk Lirası','₺',2,1.000000,1,1,'2026-01-06 22:33:12','2026-01-06 22:33:12'),(2,'USD','ABD Doları','$',1,0.030000,0,1,'2026-01-06 22:33:12','2026-01-06 22:33:12'),(3,'EUR','Euro','€',2,0.027000,0,1,'2026-01-06 22:33:12','2026-01-06 22:33:12');
/*!40000 ALTER TABLE `currencies` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` (`id`, `name`, `description`, `email`, `is_hidden`, `clients_only`, `order_priority`, `created_at`, `updated_at`) VALUES (1,'Teknik Destek','Teknik sorunlar için destek',NULL,0,0,1,'2026-01-06 22:33:12','2026-01-06 22:33:12'),(2,'Satış','Satış öncesi sorular',NULL,0,0,2,'2026-01-06 22:33:12','2026-01-06 22:33:12'),(3,'Fatura/Ödeme','Fatura ve ödeme işlemleri',NULL,0,0,3,'2026-01-06 22:33:12','2026-01-06 22:33:12'),(4,'Genel','Genel sorular',NULL,0,0,4,'2026-01-06 22:33:12','2026-01-06 22:33:12');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `email_templates` WRITE;
/*!40000 ALTER TABLE `email_templates` DISABLE KEYS */;
INSERT INTO `email_templates` (`id`, `name`, `display_name`, `category`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (1,'welcome_email','Hoş Geldiniz E-postası','client','Hoş Geldiniz {client_name}!','<h2 style=\"color: #1e293b; margin: 0 0 20px 0;\">Aramıza Hoş Geldiniz!</h2><p style=\"color: #475569; line-height: 1.6;\">Sayın <strong>{client_name}</strong>,</p><p style=\"color: #475569; line-height: 1.6;\">{site_name} ailesine katıldığınız için teşekkür ederiz!</p>\n','[\"client_name\", \"client_email\", \"site_name\", \"site_url\"]','active','2026-01-09 09:36:14','2026-01-09 09:50:49'),(2,'password_reset','Şifre Sıfırlama','client','Şifre Sıfırlama Talebi','<h2 style=\"color: #1e293b; margin: 0 0 20px 0;\">Şifre Sıfırlama</h2><p style=\"color: #475569; line-height: 1.6;\">Sayın <strong>{client_name}</strong>,</p><p style=\"color: #475569; line-height: 1.6;\">Şifrenizi sıfırlamak için: <a href=\"{reset_link}\">{reset_link}</a></p>','[\"client_name\", \"reset_link\", \"site_name\"]','active','2026-01-09 09:36:14','2026-01-09 09:36:14'),(3,'invoice_created','Yeni Fatura','billing','[{site_name}] Yeni Fatura #{invoice_id}','<h2 style=\"color: #1e293b; margin: 0 0 20px 0;\">Yeni Fatura Oluşturuldu</h2><p style=\"color: #475569;\">Sayın <strong>{client_name}</strong>,</p><p style=\"color: #475569;\">Fatura No: <strong>#{invoice_id}</strong><br>Tutar: <strong>{invoice_total} ₺</strong><br>Son Ödeme: <strong>{due_date}</strong></p>','[\"client_name\", \"invoice_id\", \"invoice_total\", \"due_date\", \"site_name\", \"site_url\"]','active','2026-01-09 09:36:14','2026-01-09 09:36:14'),(4,'invoice_paid','Fatura Ödendi','billing','[{site_name}] Ödeme Onayı - Fatura #{invoice_id}','<h2 style=\"color: #1e293b; margin: 0 0 20px 0;\">Ödemeniz Alındı</h2><p style=\"color: #475569;\">Sayın <strong>{client_name}</strong>,</p><p style=\"color: #475569;\">#{invoice_id} numaralı faturanız için ödemeniz alınmıştır.</p>','[\"client_name\", \"invoice_id\", \"payment_amount\", \"payment_date\", \"site_name\"]','active','2026-01-09 09:36:14','2026-01-09 09:36:14'),(5,'invoice_reminder','Fatura Hatırlatması','billing','[{site_name}] Ödeme Hatırlatması - Fatura #{invoice_id}','<h2 style=\"color: #1e293b; margin: 0 0 20px 0;\">Ödeme Hatırlatması</h2><p style=\"color: #475569;\">Sayın <strong>{client_name}</strong>,</p><p style=\"color: #475569;\">#{invoice_id} numaralı faturanızın son ödeme tarihi: <strong>{due_date}</strong></p>','[\"client_name\", \"invoice_id\", \"invoice_total\", \"due_date\", \"site_name\"]','active','2026-01-09 09:36:14','2026-01-09 09:36:14'),(6,'ticket_opened','Destek Talebi Açıldı','support','[{site_name}] Destek Talebi #{ticket_id}','<h2 style=\"color: #1e293b; margin: 0 0 20px 0;\">Destek Talebiniz Oluşturuldu</h2><p style=\"color: #475569;\">Sayın <strong>{client_name}</strong>,</p><p style=\"color: #475569;\">Ticket No: <strong>#{ticket_id}</strong><br>Konu: <strong>{ticket_subject}</strong></p>','[\"client_name\", \"ticket_id\", \"ticket_subject\", \"ticket_department\", \"site_name\"]','active','2026-01-09 09:36:14','2026-01-09 09:36:14'),(7,'ticket_reply','Destek Talebi Yanıtı','support','[{site_name}] Destek Talebi #{ticket_id} Yanıtlandı','<h2 style=\"color: #1e293b; margin: 0 0 20px 0;\">Talebinize Yanıt Verildi</h2><p style=\"color: #475569;\">Sayın <strong>{client_name}</strong>,</p><p style=\"color: #475569;\">#{ticket_id} numaralı talebinize yanıt verildi.</p>','[\"client_name\", \"ticket_id\", \"ticket_subject\", \"reply_message\", \"site_name\"]','active','2026-01-09 09:36:14','2026-01-09 09:36:14'),(8,'service_activated','Hizmet Aktif Edildi','service','[{site_name}] Hizmetiniz Aktif Edildi','<h2 style=\"color: #1e293b; margin: 0 0 20px 0;\">Hizmetiniz Aktif!</h2><p style=\"color: #475569;\">Sayın <strong>{client_name}</strong>,</p><p style=\"color: #475569;\"><strong>{product_name}</strong> hizmetiniz aktif edilmiştir.</p>','[\"client_name\", \"product_name\", \"domain\", \"ip_address\", \"username\", \"password\", \"site_name\"]','active','2026-01-09 09:36:14','2026-01-09 09:36:14'),(9,'service_suspended','Hizmet Askıya Alındı','service','[{site_name}] Hizmetiniz Askıya Alındı','<h2 style=\"color: #f59e0b; margin: 0 0 20px 0;\">Hizmetiniz Askıya Alındı</h2><p style=\"color: #475569;\">Sayın <strong>{client_name}</strong>,</p><p style=\"color: #475569;\"><strong>{product_name}</strong> hizmetiniz askıya alınmıştır.</p>','[\"client_name\", \"product_name\", \"suspend_reason\", \"site_name\"]','active','2026-01-09 09:36:14','2026-01-09 09:36:14'),(10,'order_received','Sipariş Alındı','order','[{site_name}] Siparişiniz Alındı #{order_id}','<h2 style=\"color: #1e293b; margin: 0 0 20px 0;\">Siparişiniz Alındı!</h2><p style=\"color: #475569;\">Sayın <strong>{client_name}</strong>,</p><p style=\"color: #475569;\">Sipariş No: <strong>#{order_id}</strong><br>Ürün: <strong>{product_name}</strong></p>','[\"client_name\", \"order_id\", \"product_name\", \"order_total\", \"site_name\"]','active','2026-01-09 09:36:14','2026-01-09 09:36:14');
/*!40000 ALTER TABLE `email_templates` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `vds_pricing` WRITE;
/*!40000 ALTER TABLE `vds_pricing` DISABLE KEYS */;
INSERT INTO `vds_pricing` (`id`, `resource_type`, `resource_name`, `unit_price`, `min_value`, `max_value`, `step_value`, `unit_label`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (1,'base','Taban Fiyat',150.00,1,1,1,'',1,0,'2026-01-09 01:51:15','2026-01-09 01:56:20'),(2,'cpu','İşlemci (vCPU)',25.00,1,16,1,'Core',1,1,'2026-01-09 01:51:15','2026-01-12 13:12:36'),(3,'ram','Bellek (RAM)',25.00,1,64,1,'GB',1,2,'2026-01-09 01:51:15','2026-01-09 01:54:58'),(4,'disk','NVMe SSD Disk',3.00,25,1000,25,'GB',1,3,'2026-01-09 01:51:15','2026-01-09 02:02:38'),(5,'bandwidth','Bant Genişliği',0.00,1,1,1,'Gbps',1,4,'2026-01-09 01:51:15','2026-01-09 02:04:35'),(6,'ip','IP Adresi',25.00,1,5,1,'Adet',1,5,'2026-01-09 01:51:15','2026-01-09 02:01:11');
/*!40000 ALTER TABLE `vds_pricing` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `domain_pricing` WRITE;
/*!40000 ALTER TABLE `domain_pricing` DISABLE KEYS */;
/*!40000 ALTER TABLE `domain_pricing` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `promotions` WRITE;
/*!40000 ALTER TABLE `promotions` DISABLE KEYS */;
/*!40000 ALTER TABLE `promotions` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `references` WRITE;
/*!40000 ALTER TABLE `references` DISABLE KEYS */;
INSERT INTO `references` (`id`, `name`, `logo`, `website`, `category`, `description`, `dark_logo`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (1,'GHS','https://www.ghs.com.tr/uploads/ghsgruplogo.png','https://www.ghs.com.tr','Bulut & Barındırma','',1,1,1,'2026-01-08 19:34:57','2026-01-08 19:34:57'),(2,'NaraMaxx','https://www.naramaxx.com/skins/shared/images/logo.webp','https://www.naramaxx.com','Veri Merkezi','Selam\nTest',0,1,1,'2026-01-08 19:40:03','2026-01-12 13:14:07');
/*!40000 ALTER TABLE `references` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `server_groups` WRITE;
/*!40000 ALTER TABLE `server_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `server_groups` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `hizmet_mahalleleri` WRITE;
/*!40000 ALTER TABLE `hizmet_mahalleleri` DISABLE KEYS */;
INSERT INTO `hizmet_mahalleleri` (`id`, `il`, `ilce`, `mahalle`, `api_id`, `il_id`, `ilce_id`, `posta_kodu`, `is_active`, `sort_order`, `created_at`) VALUES (5,'Bursa','Osmangazi','Emek Adnan Menderes',11255,16,1832,'16180',1,1,'2026-09-13 14:34:39'),(6,'Bursa','Osmangazi','Emek Fatih Sultan Mehmet',11256,16,1832,'16180',1,2,'2026-09-13 14:34:39'),(7,'Bursa','Osmangazi','Emek Zekai Gümüşdiş',11257,16,1832,'16180',1,3,'2026-09-13 14:34:39'),(8,'Bursa','Nilüfer','Minareliçavuş',11125,16,1829,'16220',1,4,'2026-09-13 14:34:39'),(9,'Bursa','Nilüfer','23 Nisan',140334,16,1829,'16230',1,5,'2026-09-13 14:34:39'),(10,'Bursa','Osmangazi','Yunuseli',11245,16,1832,'16165',1,6,'2026-09-13 14:38:15');
/*!40000 ALTER TABLE `hizmet_mahalleleri` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `hizmet_sokaklari` WRITE;
/*!40000 ALTER TABLE `hizmet_sokaklari` DISABLE KEYS */;
INSERT INTO `hizmet_sokaklari` (`id`, `mahalle_id`, `sokak`, `is_active`) VALUES (7,10,'1. Akyüz Sokak',1),(8,10,'1. Akıl Sokak',1),(9,10,'1. Bahriyeli Sokak',1),(10,10,'1. Baş Sokak',1),(11,10,'1. Bengü Sokak',1),(12,10,'1. Betül Sokak',1),(13,10,'1. Bilge Sokak',1),(14,10,'1. Buket Sokak',1),(15,10,'1. Efsane Sokak',1),(16,10,'1. Hatır Sokak',1),(17,10,'1. Hoşnut Sokak',1),(18,10,'1. Tepebaşı Sokak',1),(19,10,'1. Uygur Sokak',1),(20,10,'1. Yamaç Sokak',1),(21,10,'1. Yeditepe Sokak',1),(22,10,'1. Yenice Sokak',1),(23,10,'1. Çekiç Sokak',1),(24,10,'1. Çınar Sokak',1),(25,10,'2. Alkan Sokak',1),(26,10,'2. Asker Caddesi',1),(27,10,'2. Bereket Sokak',1),(28,10,'2. Burgaz Sokak',1),(29,10,'2. Bölüntü Sokak',1),(30,10,'2. Cevher Sokak',1),(31,10,'2. Ceyhan Sokak',1),(32,10,'2. Cumhur Sokak',1),(33,10,'2. Dalgıç Sokak',1),(34,10,'2. Defne Sokak',1),(35,10,'2. Delege Sokak',1),(36,10,'2. Demir Sokak',1),(37,10,'2. Denge Sokak',1),(38,10,'2. Destan Sokak',1),(39,10,'2. Deva Sokak',1),(40,10,'2. Devran Sokak',1),(41,10,'2. Dolunay Sokak',1),(42,10,'2. Dumlupınar Caddesi',1),(43,10,'2. Ekinci Sokak',1),(44,10,'2. Erguvan Sokak',1),(45,10,'2. Esen Sokak',1),(46,10,'2. Esinti Sokak',1),(47,10,'2. Gülcan Sokak',1),(48,10,'2. Karaman Sokak',1),(49,10,'2. Karanfil Caddesi',1),(50,10,'2. Kent Sokak',1),(51,10,'2. Neslihan Sokak',1),(52,10,'2. Oba Sokak',1),(53,10,'2. Semra Sokak',1),(54,10,'2. Simge Sokak',1),(55,10,'2. Suna Sokak',1),(56,10,'2. Umut Sokak',1),(57,10,'2. Vahit Sokak',1),(58,10,'2. Vakıf Sokak',1),(59,10,'2. Yahya Sokak',1),(60,10,'2. Yakın Sokak',1),(61,10,'2. Yalım Sokak',1),(62,10,'2. Yaprak Sokak',1),(63,10,'2. Yaren Sokak',1),(64,10,'2. Yavuz Sokak',1),(65,10,'2. Yazar Sokak',1),(66,10,'2. Yemeni Sokak',1),(67,10,'2. Yeni Cami Sokak',1),(68,10,'2. Yeniceler Sokak',1),(69,10,'2. Yokuş Sokak',1),(70,10,'2. Yurdakul Sokak',1),(71,10,'2. Yüce Çıkmazı Sokak',1),(72,10,'2. Yüksek Sokak',1),(73,10,'2. Çalıkuşu Sokak',1),(74,10,'2. Çankaya Sokak',1),(75,10,'2. Çark Sokak',1),(76,10,'2. Çekirge Sokak',1),(77,10,'2. Çemen Sokak',1),(78,10,'2. Çınar Caddesi',1),(79,10,'2. Çığ Sokak',1),(80,10,'2. Şark Sokak',1),(81,10,'2. Şentuna Sokak',1),(82,10,'3. Balık Sokak',1),(83,10,'3. Burcu Sokak',1),(84,10,'3. Canan Sokak',1),(85,10,'3. Ece Sokak',1),(86,10,'3. Halis Sokak',1),(87,10,'3. Harite Sokak',1),(88,10,'3. Hasanağa Sokak',1),(89,10,'3. Ihlamur Sokak',1),(90,10,'3. Kafkas Sokak',1),(91,10,'3. Kurtuluş Sokak',1),(92,10,'3. Köşk Sokak',1),(93,10,'3. Maltepe Sokak',1),(94,10,'3. Nasip Sokak',1),(95,10,'3. Olcay Sokak',1),(96,10,'3. Sever Sokak',1),(97,10,'3. Yeşilyurt Sokak',1),(98,10,'3. Yuva Sokak',1),(99,10,'3. Yıldız Sokak',1),(100,10,'3. Çavdar Sokak',1),(101,10,'3. Çağrı Sokak',1),(102,10,'3. Çengel Sokak',1),(103,10,'3. Çıra Sokak',1),(104,10,'3. Çığır Sokak',1),(105,10,'3. İdeal Sokak',1),(106,10,'4. Akdemir Sokak',1),(107,10,'4. Aktaş Sokak',1),(108,10,'4. Burcu Sokak',1),(109,10,'4. Cihan Sokak',1),(110,10,'4. Civan Sokak',1),(111,10,'4. Damla Sokak',1),(112,10,'4. Erkal Sokak',1),(113,10,'4. Filibe Sokak',1),(114,10,'4. Gürbüz Sokak',1),(115,10,'4. Hatas Sokak',1),(116,10,'4. Hür Sokak',1),(117,10,'4. Kerem Sokak',1),(118,10,'4. Meltem Sokak',1),(119,10,'4. Nazlı Sokak',1),(120,10,'4. Neşe Sokak',1),(121,10,'4. Onur Sokak',1),(122,10,'4. Serdar Sokak',1),(123,10,'4. Tunca Sokak',1),(124,10,'4. Yamaç Sokak',1),(125,10,'4. Yan Sokak',1),(126,10,'4. Yazıcı Sokak',1),(127,10,'4. Yeni Sokak',1),(128,10,'4. Yeşilova Sokak',1),(129,10,'4. Yusuf Sokak',1),(130,10,'4. Çevik Sokak',1),(131,10,'4. Önder Sokak',1),(132,10,'4. Şener Sokak',1),(133,10,'5. Afacan Sokak',1),(134,10,'5. Akçay Sokak',1),(135,10,'5. Arzu Sokak',1),(136,10,'5. Batı Sokak',1),(137,10,'5. Cenk Sokak',1),(138,10,'5. Coşkun Sokak',1),(139,10,'5. Derman Sokak',1),(140,10,'5. Fırat Sokak',1),(141,10,'5. Keklik Sokak',1),(142,10,'5. Yağmur Sokak',1),(143,10,'5. Yiğit Sokak',1),(144,10,'5. Zeytin Sokak',1),(145,10,'5. Önder Sokak',1),(146,10,'5. Özgür Sokak',1),(147,10,'5. Ümit Sokak',1),(148,10,'6. Akarsu Sokak',1),(149,10,'6. Akça Sokak',1),(150,10,'6. Aslı Sokak',1),(151,10,'6. Asma Sokak',1),(152,10,'6. Bulut Sokak',1),(153,10,'6. Derya Sokak',1),(154,10,'6. Eser Sokak',1),(155,10,'6. Mert Sokak',1),(156,10,'6. Okul Caddesi',1),(157,10,'6. Orta Sokak',1),(158,10,'6. Sarmaşık Sokak',1),(159,10,'6. Sevim Sokak',1),(160,10,'6. Sevinç Sokak',1),(161,10,'6. Yağmur Sokak',1),(162,10,'6. Çalı Sokak',1),(163,10,'6. Çamlı Sokak',1),(164,10,'6. Çay Sokak',1),(165,10,'6. Çeyiz Sokak',1),(166,10,'6. Ömür Sokak',1),(167,10,'6. Önder Sokak',1),(168,10,'6. Özkan Sokak',1),(169,10,'6. Ünal Sokak',1),(170,10,'7. Aksu Sokak',1),(171,10,'7. Aktar Sokak',1),(172,10,'7. Açelya Sokak',1),(173,10,'7. Ege Sokak',1),(174,10,'7. Savaş Sokak',1),(175,10,'7. Selim Sokak',1),(176,10,'7. Selvi Sokak',1),(177,10,'7. Çardak Sokak',1),(178,10,'8. Bilgin Sokak',1),(179,10,'8. Gülşen Sokak',1),(180,10,'8. Güngör Sokak',1),(181,10,'8. Karaca Sokak',1),(182,10,'8. Kumru Sokak',1),(183,10,'8. Okul Caddesi',1),(184,10,'8. Sağlık Sokak',1),(185,10,'8. Tepe Sokak',1),(186,10,'8. Yurt Sokak',1),(187,10,'8. Yüksel Sokak',1),(188,10,'8. Çalışkan Sokak',1),(189,10,'9. Akça Sokak',1),(190,10,'9. Asker Sokak',1),(191,10,'9. Can Sokak',1),(192,10,'9. Güçlü Sokak',1),(193,10,'9. Huzur Sokak',1),(194,10,'9. Meriç Sokak',1),(195,10,'9. Ulu Sokak',1),(196,10,'9. Uzun Sokak',1),(197,10,'9. Yalçın Sokak',1),(198,10,'9. Yüce Sokak',1),(199,10,'9. Yıldırım Sokak',1),(200,10,'10. Arı Sokak',1),(201,10,'10. Başaran Sokak',1),(202,10,'10. Engin Sokak',1),(203,10,'10. Gazi Sokak',1),(204,10,'10. Çağlayan Sokak',1),(205,10,'10. Çetin Sokak',1),(206,10,'10. Şimşek Sokak',1),(207,10,'11. Ada Sokak',1),(208,10,'11. Deniz Sokak',1),(209,10,'11. Fatih Sokak',1),(210,10,'11. Kanarya Sokak',1),(211,10,'11. Çelik Sokak',1),(212,10,'11. Çınar Sokak',1),(213,10,'12. Akar Sokak',1),(214,10,'12. Manolya Sokak',1),(215,10,'12. Ufuk Sokak',1),(216,10,'12. Yasemin Sokak',1),(217,10,'12. Yeşil Sokak',1),(218,10,'12. Çayır Sokak',1),(219,10,'13. Akasya Sokak',1),(220,10,'13. Yayla Sokak',1),(221,10,'14. Aydoğan Sokak',1),(222,10,'14. Demir Sokak',1),(223,10,'14. Değirmen Sokak',1),(224,10,'14. Çınar Sokak',1),(225,10,'14. İnci Sokak',1),(226,10,'15. Akın Sokak',1),(227,10,'15. Ceylan Sokak',1),(228,10,'15. Yavuz Sokak',1),(229,10,'15. Özen Sokak',1),(230,10,'16. Bahar Sokak',1),(231,10,'16. Yılmaz Sokak',1),(232,10,'16. Çakmak Sokak',1),(233,10,'18. Ata Sokak',1),(234,10,'19. Dere Sokak',1),(235,10,'19. Mutlu Sokak',1),(236,10,'20. Bahar Sokak',1),(237,10,'20. Bahçe Sokak',1),(238,10,'21. Çiğdem Sokak',1),(239,10,'22. Şen Sokak',1),(240,10,'27. Yıldız Sokak',1),(241,10,'212. Sokak',1),(242,10,'215. Sokak',1),(243,10,'218. Sokak',1),(244,10,'219. Sokak',1),(245,10,'221. Sokak',1),(246,10,'222. Sokak',1),(247,10,'227. Sokak',1),(248,10,'230. Sokak',1),(249,10,'231. Sokak',1),(250,10,'232. Sokak',1),(251,10,'237. Sokak',1),(252,10,'238. Sokak',1),(253,10,'241. Sokak',1),(254,10,'242. Sokak',1),(255,10,'243. Sokak',1),(256,10,'244. Sokak',1),(257,10,'246. Sokak',1),(258,10,'247. Sokak',1),(259,10,'248. Sokak',1),(260,10,'249. Sokak',1),(261,10,'250. Sokak',1),(262,10,'251. Sokak',1),(263,10,'252. Sokak',1),(264,10,'254. Sokak',1),(265,10,'261. Sokak',1),(266,10,'262. Sokak',1),(267,10,'263. Sokak',1),(268,10,'264. Sokak',1),(269,10,'265. Sokak',1),(270,10,'266. Sokak',1),(271,10,'267. Sokak',1),(272,10,'269. Sokak',1),(273,10,'270. Sokak',1),(274,10,'271. Sokak',1),(275,10,'272. Sokak',1),(276,10,'274. Sokak',1),(277,10,'279. Sokak',1),(278,10,'280. Sokak',1),(279,10,'281. Sokak',1),(280,10,'282. Sokak',1),(281,10,'284. Sokak',1),(282,10,'288. Sokak',1),(283,10,'289. Sokak',1),(284,10,'290. Sokak',1),(285,10,'291. Sokak',1),(286,10,'295. Sokak',1),(287,10,'296. Sokak',1),(288,10,'410. Sokak',1),(289,10,'419. Sokak',1),(290,10,'420. Sokak',1),(291,10,'426. Sokak',1),(292,10,'427. Sokak',1),(293,10,'428. Sokak',1),(294,10,'520. Sokak',1),(295,10,'623. Sokak',1),(296,10,'624. Sokak',1),(297,10,'625. Sokak',1),(298,10,'626. Sokak',1),(299,10,'627. Sokak',1),(300,10,'629. Sokak',1),(301,10,'630. Sokak',1),(302,10,'631. Sokak',1),(303,10,'632. Sokak',1),(304,10,'633. Sokak',1),(305,10,'634. Sokak',1),(306,10,'635. Sokak',1),(307,10,'636. Sokak',1),(308,10,'637. Sokak',1),(309,10,'638. Sokak',1),(310,10,'639. Sokak',1),(311,10,'640. Sokak',1),(312,10,'641. Sokak',1),(313,10,'642. Sokak',1),(314,10,'643. Sokak',1),(315,10,'644. Sokak',1),(316,10,'645. Sokak',1),(317,10,'646. Sokak',1),(318,10,'647. Sokak',1),(319,10,'648. Sokak',1),(320,10,'649. Sokak',1),(321,10,'650. Sokak',1),(322,10,'651. Sokak',1),(323,10,'652. Sokak',1),(324,10,'653. Sokak',1),(325,10,'654. Sokak',1),(326,10,'657. Sokak',1),(327,10,'658. Sokak',1),(328,10,'660. Sokak',1),(329,10,'661. Sokak',1),(330,10,'664. Sokak',1),(331,10,'665. Sokak',1),(332,10,'666. Sokak',1),(333,10,'667. Sokak',1),(334,10,'668. Sokak',1),(335,10,'669. Sokak',1),(336,10,'670. Sokak',1),(337,10,'671. Sokak',1),(338,10,'672. Sokak',1),(339,10,'673. Sokak',1),(340,10,'674. Sokak',1),(341,10,'675. Sokak',1),(342,10,'676. Sokak',1),(343,10,'677. Sokak',1),(344,10,'678. Sokak',1),(345,10,'679. Sokak',1),(346,10,'680. Sokak',1),(347,10,'688. Sokak',1),(348,10,'691. Sokak',1),(349,10,'692. Sokak',1),(350,10,'708. Sokak',1),(351,10,'714. Sokak',1),(352,10,'721. Sokak',1),(353,10,'759. Sokak',1),(354,10,'760. Sokak',1),(355,10,'763. Sokak',1),(356,10,'764. Sokak',1),(357,10,'765. Sokak',1),(358,10,'769. Sokak',1),(359,10,'770. Sokak',1),(360,10,'773. Sokak',1),(361,10,'784. Sokak',1),(362,10,'787. Sokak',1),(363,10,'790. Sokak',1),(364,10,'791. Sokak',1),(365,10,'793. Sokak',1),(366,10,'794. Sokak',1),(367,10,'795. Sokak',1),(368,10,'796. Sokak',1),(369,10,'797. Sokak',1),(370,10,'799. Sokak',1),(371,10,'800. Sokak',1),(372,10,'802. Sokak',1),(373,10,'806. Sokak',1),(374,10,'807. Sokak',1),(375,10,'808. Sokak',1),(376,10,'809. Sokak',1),(377,10,'810. Sokak',1),(378,10,'811. Sokak',1),(379,10,'813. Sokak',1),(380,10,'814. Sokak',1),(381,10,'815. Sokak',1),(382,10,'816. Sokak',1),(383,10,'817. Sokak',1),(384,10,'818. Sokak',1),(385,10,'820. Sokak',1),(386,10,'821. Sokak',1),(387,10,'823. Sokak',1),(388,10,'824. Sokak',1),(389,10,'825. Sokak',1),(390,10,'827. Sokak',1),(391,10,'829. Sokak',1),(392,10,'830. Sokak',1),(393,10,'831. Sokak',1),(394,10,'832. Sokak',1),(395,10,'833. Sokak',1),(396,10,'834. Sokak',1),(397,10,'836. Sokak',1),(398,10,'838. Sokak',1),(399,10,'840. Sokak',1),(400,10,'841. Sokak',1),(401,10,'842. Sokak',1),(402,10,'843. Sokak',1),(403,10,'844. Sokak',1),(404,10,'845. Sokak',1),(405,10,'846. Sokak',1),(406,10,'847. Sokak',1),(407,10,'848. Sokak',1),(408,10,'849. Sokak',1),(409,10,'851. Sokak',1),(410,10,'852. Sokak',1),(411,10,'853. Sokak',1),(412,10,'854. Sokak',1),(413,10,'855. Sokak',1),(414,10,'856. Sokak',1),(415,10,'857. Sokak',1),(416,10,'858. Sokak',1),(417,10,'859. Sokak',1),(418,10,'860. Sokak',1),(419,10,'861. Sokak',1),(420,10,'862. Sokak',1),(421,10,'863. Sokak',1),(422,10,'Alpaslan Türkeş Caddesi',1),(423,10,'Andız Sokak',1),(424,10,'Aslanbey Sokak',1),(425,10,'Aysun Sokak',1),(426,10,'Ayyıldız Caddesi',1),(427,10,'Batman Sokak',1),(428,10,'Başoğlu Sokak',1),(429,10,'Belen Sokak',1),(430,10,'Beyaz Sokak',1),(431,10,'Biladiyunus Caddesi',1),(432,10,'Bilge Kağan Caddesi',1),(433,10,'Candan Sokak',1),(434,10,'Caner Sokak',1),(435,10,'Cebel Sokak',1),(436,10,'Celil Sokak',1),(437,10,'Cenap Sokak',1),(438,10,'Cesim Sokak',1),(439,10,'Cevahir Sokak',1),(440,10,'Cezve Sokak',1),(441,10,'Cihangir Sokak',1),(442,10,'Ciritçi Sokak',1),(443,10,'Civa Sokak',1),(444,10,'Cumbalı Sokak',1),(445,10,'Cüneyt Yıldız Caddesi',1),(446,10,'Dalyan Sokak',1),(447,10,'Darı Sokak',1),(448,10,'Didim Sokak',1),(449,10,'Dilek Sokak',1),(450,10,'Dizdar Caddesi',1),(451,10,'Dora Sokak',1),(452,10,'Efe Sokak',1),(453,10,'Ekber Sokak',1),(454,10,'Erkul Sokak',1),(455,10,'Erman Sokak',1),(456,10,'Esmer Sokak',1),(457,10,'Evgin Sokak',1),(458,10,'Ezgi Sokak',1),(459,10,'Fatih Sokak',1),(460,10,'Fuat Kuşçuoğlu Caddesi',1),(461,10,'Göçmen Sokak',1),(462,10,'Halit Sokak',1),(463,10,'Hilal Sokak',1),(464,10,'Hüseyin Kanalıcı Caddesi',1),(465,10,'Kamelya Caddesi',1),(466,10,'Kanal Boyu Caddesi',1),(467,10,'Kavaklıdere Sokak',1),(468,10,'Kutlubey Caddesi',1),(469,10,'Kuğulu Sokak',1),(470,10,'Kıymet Sokak',1),(471,10,'Mirayda Sokak',1),(472,10,'Muhasebe Sokak',1),(473,10,'Nesrin Sokak',1),(474,10,'Neşet Ertaş Caddesi',1),(475,10,'Oğuzhan Sokak',1),(476,10,'Palandöken Sokak',1),(477,10,'Recep Tayyip Erdoğan Bulvarı',1),(478,10,'Samet Sokak',1),(479,10,'Somuncu Baba Sokak',1),(480,10,'Tapduk Emre Sokak',1),(481,10,'Taşköprü Caddesi',1),(482,10,'Turfan Sokak',1),(483,10,'Yaman Caddesi',1),(484,10,'Yaygın Sokak',1),(485,10,'Yayladere Sokak',1),(486,10,'Yağış Sokak',1),(487,10,'Yaşar Sokak',1),(488,10,'Yeditepe Sokak',1),(489,10,'Yener Sokak',1),(490,10,'Yeniköy Sokak',1),(491,10,'Yeniler Sokak',1),(492,10,'Yeniçağ Sokak',1),(493,10,'Yetenek Sokak',1),(494,10,'Yetimler Sokak',1),(495,10,'Yetim Sokak',1),(496,10,'Yetiş Sokak',1),(497,10,'Yeşilcami Sokak',1),(498,10,'Yeşildere Sokak',1),(499,10,'Yeşilkent Sokak',1),(500,10,'Yiğitler Sokak',1),(501,10,'Yolcu Sokak',1),(502,10,'Yudum Sokak',1),(503,10,'Yunuseli Bulvarı',1),(504,10,'Yunusemre Sokak',1),(505,10,'Yunus Sokak',1),(506,10,'Yurtsever Sokak',1),(507,10,'Yuvam Sokak',1),(508,10,'Yürekli Sokak',1),(509,10,'Çamlık Caddesi',1),(510,10,'Çevreci Sokak',1),(511,10,'Çözüm Sokak',1),(512,10,'Özge Sokak',1),(513,10,'Öznur Sokak',1),(514,10,'Üzüm Sokak',1),(515,10,'Şehit Er Burak Özkan Sokak',1),(516,10,'Şehit Göksu Şafak Şahin Sokak',1),(517,10,'Şenakıncı Sokak',1);
/*!40000 ALTER TABLE `hizmet_sokaklari` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `cron_tasks` WRITE;
/*!40000 ALTER TABLE `cron_tasks` DISABLE KEYS */;
INSERT INTO `cron_tasks` (`id`, `name`, `description`, `command`, `schedule`, `is_active`, `last_run`, `next_run`, `last_status`, `last_output`, `created_at`, `updated_at`) VALUES (1,'Otomatik Fatura Yenileme','Süresi dolan hizmetler için otomatik olarak yeni fatura oluşturur','generate_invoices','0 10 * * *',1,'2026-01-12 02:06:26','2026-01-12 10:00:00','success','{\"invoices_created\":0,\"services_processed\":0,\"emails_sent\":0,\"errors\":[]}','2026-01-12 01:06:55','2026-01-12 02:06:26'),(2,'Fatura Hatırlatma','Yaklaşan faturalar için müşterilere hatırlatma e-postası gönderir','send_invoice_reminders','0 10 * * *',1,'2026-01-12 02:06:26','2026-01-12 10:00:00','success','{\"sent\":0}','2026-01-12 01:06:55','2026-01-12 02:06:26'),(3,'Geciken Fatura Bildirimi','Geciken faturalar için müşterilere bildirim e-postası gönderir','overdue_invoice_notices','0 10 * * *',1,'2026-01-12 02:06:26','2026-01-12 10:00:00','success','{\"sent\":0,\"total\":0}','2026-01-12 01:06:55','2026-01-12 02:06:26'),(4,'Domain Yenileme Bildirimi','Yaklaşan domain yenilemeleri için müşterilere bildirim gönderir (90, 60, 30, 14, 7, 1 gün önce)','domain_renewal_notices','0 10 * * *',1,'2026-01-12 02:06:26','2026-01-12 10:00:00','failed','Error: SQLSTATE[HY093]: Invalid parameter number','2026-01-12 01:06:55','2026-01-12 02:06:26'),(5,'Domain Süresi Dolan İşlemleri','Süresi dolan domainleri işler ve otomatik yenileme için fatura oluşturur','domain_expiry','0 10 * * *',1,'2026-01-12 02:06:26','2026-01-12 10:00:00','success','{\"expired\":0,\"auto_renewed\":0,\"errors\":[]}','2026-01-12 01:06:55','2026-01-12 02:06:26'),(6,'Hizmet Askıya Alma','Süresi geçen aktif hizmetleri otomatik olarak askıya alır','suspend_services','0 10 * * *',1,'2026-01-12 02:06:26','2026-01-12 10:00:00','success','{\"suspended\":0}','2026-01-12 01:06:55','2026-01-12 02:06:26'),(7,'Hizmet Sonlandırma','Uzun süre askıda kalan hizmetleri sonlandırır','terminate_services','0 10 * * *',1,'2026-01-12 02:06:26','2026-01-12 10:00:00','success','{\"terminated\":0}','2026-01-12 01:06:55','2026-01-12 02:06:26'),(8,'Destek Talebi Yükseltme','Yanıt bekleyen destek taleplerinin önceliğini otomatik yükseltir','ticket_escalations','0 10 * * *',1,'2026-01-12 02:06:26','2026-01-12 10:00:00','success','{\"escalated\":0,\"total\":0}','2026-01-12 01:06:55','2026-01-12 02:06:26'),(9,'Affiliate Komisyon Hesaplama','Ödenmiş faturalar için affiliate komisyonlarını hesaplar','affiliate_commissions','0 10 * * *',1,'2026-01-12 02:06:26','2026-01-12 10:00:00','success','{\"processed\":0,\"paid\":0,\"errors\":[]}','2026-01-12 01:06:55','2026-01-12 02:06:26'),(10,'Eski Logları Temizle','90 günden eski sistem loglarını temizler','cleanup_old_logs','0 10 * * *',1,'2026-01-12 02:06:26','2026-01-12 10:00:00','success','{\"deleted\":0}','2026-01-12 01:06:55','2026-01-12 02:06:26'),(11,'Veritabanı Yedekleme','Veritabanının otomatik yedeğini oluşturur (günlük)','database_backup','0 10 * * *',0,NULL,'2026-01-12 01:06:55','success',NULL,'2026-01-12 01:06:55','2026-01-12 01:06:55');
/*!40000 ALTER TABLE `cron_tasks` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `modules` WRITE;
/*!40000 ALTER TABLE `modules` DISABLE KEYS */;
INSERT INTO `modules` (`id`, `name`, `slug`, `version`, `description`, `author`, `author_url`, `category`, `is_active`, `install_path`, `config`, `dependencies`, `installed_at`, `updated_at`) VALUES (2,'PayTR Ödeme Modülü','paytr','1.0.0','PayTR ödeme gateway entegrasyonu - Kredi kartı, havale/EFT ve mobil ödeme desteği','WHMVM','','payment',1,'modules/paytr','{\"merchant_id\":\"123456\",\"merchant_key\":\"xxxxxxxxxxxx\",\"merchant_salt\":\"xxxxxxxxxxxxxx\",\"test_mode\":1}','{\"php\":\">=8.1\",\"extensions\":[\"curl\",\"openssl\"]}','2026-01-22 13:59:26','2026-09-13 18:39:20'),(3,'Havale/EFT Ödeme Modülü','bank-transfer','1.0.0','Havale ve EFT ile ödeme alma modülü - Manuel ödeme onayı ve banka bilgileri yönetimi','WHMVM','','payment',1,'modules/bank-transfer','{\"banks\":[],\"auto_confirm\":0,\"require_receipt\":0,\"instructions\":\"Lütfen ödeme yaparken açıklama kısmına referans numaranızı yazmayı unutmayın.\"}','{\"php\":\">=8.1\"}','2026-01-22 22:19:11','2026-09-13 18:39:20'),(4,'SEO Manager','seo-manager','1.0.0','SEO yönetim modülü - URL\'ler, meta tags, sayfa başlıkları ve SEO ayarlarını yönetin','Semih AKBAŞ','','general',0,'modules/seo-manager','{\"auto_detect_pages\":true,\"default_meta_robots\":\"index, follow\"}','{\"php\":\">=8.1\"}','2026-01-22 23:40:20','2026-09-13 18:41:14');
/*!40000 ALTER TABLE `modules` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;



-- Ayarlar: SMTP ve alan adı sağlayıcı parolaları bilerek boş bırakılmıştır.
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_group`) VALUES
('company_name', 'Verimek Proje | Türkiye\'nin Veri Merkezi', 'company'),
('company_email', 'info@verimekproje.com', 'company'),
('company_phone', '+90 (0224) 334 13 55', 'company'),
('company_address', 'Minareliçavuş, Mete (290) Sokak No:27/A, 16140 Nilüfer/Bursa', 'company'),
('tax_enabled', '1', 'billing'),
('tax_rate', '20', 'billing'),
('invoice_prefix', 'INV-', 'billing'),
('order_prefix', 'ORD-', 'billing'),
('ticket_prefix', 'TKT-', 'general'),
('auto_suspend_days', '3', 'system'),
('auto_terminate_days', '14', 'system'),
('maintenance_mode', '0', 'system'),
('registration_enabled', '1', 'system'),
('site_name', 'WHMVM Panel', 'appearance'),
('site_primary_color', '#6366f1', 'appearance'),
('site_secondary_color', '#0ea5e9', 'appearance'),
('currency', 'TRY', 'general'),
('invoice_reminder_days', '7', 'billing'),
('default_language', 'tr', 'general'),
('default_theme', 'dark', 'general'),
('allow_theme_switch', '1', 'general'),
('smtp_host', '', 'general'),
('smtp_port', '587', 'general'),
('smtp_username', '', 'general'),
('smtp_password', '', 'general'),
('smtp_encryption', 'tls', 'general'),
('smtp_auth', '1', 'general'),
('smtp_from_email', '', 'general'),
('smtp_from_name', 'VHMVM Panel', 'general'),
('affiliate_enabled', '1', 'general'),
('affiliate_auto_approve', '0', 'general'),
('affiliate_default_commission', '10', 'general'),
('affiliate_commission_type', 'percentage', 'general'),
('affiliate_min_withdrawal', '2000', 'general'),
('affiliate_cookie_days', '30', 'general'),
('affiliate_require_approval', '1', 'general'),
('site_logo', 'uploads/logos/logo_1768154779.png', 'appearance'),
('domainname_username', '', 'integrations'),
('domainname_password', '', 'integrations'),
('domainname_test_mode', '0', 'integrations'),
('domainname_active', '1', 'integrations');


SET FOREIGN_KEY_CHECKS = 1;
