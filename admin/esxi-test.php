<?php
/**
 * ESXi SSH Kütüphane Debug Testi
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>ESXi SSH Kütüphane Debug</h2>";
echo "<pre>";

// 1. Vendor klasörü kontrolü
$vendorPath = dirname(__DIR__) . '/vendor';
echo "1. Vendor Path: $vendorPath\n";
echo "   Mevcut: " . (is_dir($vendorPath) ? "✓ EVET" : "✗ HAYIR") . "\n\n";

// 2. Autoload dosyası kontrolü
$autoloadPath = $vendorPath . '/autoload.php';
echo "2. Autoload Path: $autoloadPath\n";
echo "   Mevcut: " . (file_exists($autoloadPath) ? "✓ EVET" : "✗ HAYIR") . "\n\n";

// 3. phpseclib klasörü kontrolü
$phpseclibPath = $vendorPath . '/phpseclib-master/phpseclib';
echo "3. phpseclib Path: $phpseclibPath\n";
echo "   Mevcut: " . (is_dir($phpseclibPath) ? "✓ EVET" : "✗ HAYIR") . "\n\n";

// 4. SSH2.php dosyası kontrolü
$ssh2Path = $phpseclibPath . '/Net/SSH2.php';
echo "4. SSH2.php Path: $ssh2Path\n";
echo "   Mevcut: " . (file_exists($ssh2Path) ? "✓ EVET" : "✗ HAYIR") . "\n\n";

// 5. Autoload yükle
if (file_exists($autoloadPath)) {
    echo "5. Autoload yükleniyor...\n";
    require_once $autoloadPath;
    echo "   ✓ Yüklendi\n\n";
} else {
    echo "5. ✗ Autoload dosyası bulunamadı!\n\n";
}

// 6. Class kontrolü - autoload SONRASI
echo "6. Class kontrolü (autoload sonrası):\n";
echo "   class_exists('\\phpseclib4\\Net\\SSH2'): " . (class_exists('\phpseclib4\Net\SSH2') ? "✓ EVET" : "✗ HAYIR") . "\n\n";

// 7. Manuel olarak SSH2.php yükle
if (!class_exists('\phpseclib4\Net\SSH2') && file_exists($ssh2Path)) {
    echo "7. Manuel SSH2.php yükleme deneniyor...\n";
    require_once $ssh2Path;
    echo "   class_exists sonrası: " . (class_exists('\phpseclib4\Net\SSH2') ? "✓ EVET" : "✗ HAYIR") . "\n\n";
}

// 8. ESXi class testi
echo "8. ESXi class testi:\n";
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/ESXi.php';

$esxi = new ESXi('test.example.com', 'root', 'password');
echo "   SSH Method: " . $esxi->getSSHMethod() . "\n";

echo "</pre>";

// Sonuç
echo "<hr>";
$method = $esxi->getSSHMethod();
if ($method === 'phpseclib') {
    echo "<p style='background: #d1fae5; padding: 15px; border-radius: 8px; color: #059669;'>";
    echo "<strong>✓ phpseclib çalışıyor!</strong>";
    echo "</p>";
} elseif ($method === 'ssh2') {
    echo "<p style='background: #dbeafe; padding: 15px; border-radius: 8px; color: #2563eb;'>";
    echo "<strong>✓ php-ssh2 extension çalışıyor!</strong>";
    echo "</p>";
} else {
    echo "<p style='background: #fee2e2; padding: 15px; border-radius: 8px; color: #dc2626;'>";
    echo "<strong>✗ Hiçbir SSH kütüphanesi bulunamadı!</strong><br>";
    echo "Yukarıdaki debug bilgilerine bakın.";
    echo "</p>";
}
