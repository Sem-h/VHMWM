<?php
/**
 * WHMVM - IP Adresi Güncelleme Scripti
 * 
 * Kullanım: php update-ip.php [yeni-ip-adresi]
 * Örnek: php update-ip.php 203.0.113.10:3005
 */

$ipConfigPath = __DIR__ . '/config/ip-config.php';

// Komut satırından IP al
if ($argc < 2) {
    echo "Kullanım: php update-ip.php [ip-adresi:port]\n";
    echo "Örnek: php update-ip.php 203.0.113.10:3005\n";
    echo "Örnek: php update-ip.php 192.168.1.100:8080\n";
    exit(1);
}

$newIp = trim($argv[1]);

// IP formatını kontrol et
if (!preg_match('/^[\d\.]+(?::\d+)?$/', $newIp)) {
    echo "HATA: Geçersiz IP formatı. Format: IP:PORT veya IP\n";
    echo "Örnek: 203.0.113.10:3005\n";
    exit(1);
}

// Port yoksa varsayılan port ekle
if (strpos($newIp, ':') === false) {
    $newIp .= ':3005';
}

// Config dosyasını oluştur/güncelle
$configContent = "<?php
/**
 * WHMVM - IP Adresi Yapılandırması
 * Bu dosya dış IP adresini saklar
 * 
 * IP adresini değiştirmek için: !ipdeğiş: [yeni-ip-adresi]
 */

// Dış IP adresi ve port
// Format: IP:PORT veya sadece IP (varsayılan port 3005)
return [
    'external_ip' => '{$newIp}',
    'last_updated' => '" . date('Y-m-d H:i:s') . "'
];
";

if (file_put_contents($ipConfigPath, $configContent) !== false) {
    echo "✓ IP adresi başarıyla güncellendi: {$newIp}\n";
    echo "✓ Güncelleme zamanı: " . date('Y-m-d H:i:s') . "\n";
    echo "\n";
    echo "Not: Değişikliklerin etkili olması için server'ı yeniden başlatmanız gerekebilir.\n";
} else {
    echo "HATA: IP adresi güncellenemedi. Dosya yazma izni kontrol edin.\n";
    exit(1);
}

