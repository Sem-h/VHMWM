<?php
/**
 * WHMVM - Web Hosting Management Virtual Machine
 * Örnek Yapılandırma Dosyası
 * 
 * Bu dosyayı config.php olarak kopyalayın ve ayarları düzenleyin.
 * PHP 8.1+
 */

// Veritabanı Ayarları
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'whmvm');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Site Ayarları
// Dinamik URL: HTTP_HOST kullanarak otomatik belirlenir (localhost veya dış IP)
// Eğer HTTP_HOST yoksa (cron job gibi durumlarda) fallback olarak belirtilen URL kullanılır
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'yourdomain.com'; // Fallback: Domain veya IP:Port
define('SITE_URL', $protocol . '://' . $host);
define('SITE_NAME', 'WHMVM Panel');
define('SITE_LANG', 'tr');

// Güvenlik
define('ENCRYPTION_KEY', 'your-32-character-secret-key-here');
define('SESSION_NAME', 'WHMVM_SESSION');

// Zaman Dilimi
date_default_timezone_set('Europe/Istanbul');

// Hata Ayıklama (Production'da false yapın)
define('DEBUG_MODE', true);

// Sürüm
define('APP_VERSION', '1.0.0');

