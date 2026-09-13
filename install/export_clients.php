<?php
/**
 * WHMVM - Veritabanı Export Scripti
 * Müşteri verilerini SQL formatında export eder
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';

$db = Database::getInstance();

echo "=== WHMVM Müşteri Export ===\n\n";

// Müşterileri çek
$clients = Database::fetchAll('SELECT * FROM clients ORDER BY id');
$count = count($clients);

echo "Toplam müşteri: $count\n\n";

if ($count === 0) {
    die("Export edilecek müşteri yok.\n");
}

// SQL dosyası oluştur
$sql = "-- WHMVM Müşteri Verileri Export\n";
$sql .= "-- Oluşturulma: " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- Toplam Kayıt: $count\n\n";

$sql .= "-- Mevcut verileri temizle (opsiyonel)\n";
$sql .= "-- TRUNCATE TABLE clients;\n\n";

$sql .= "-- Müşteri verileri\n";

foreach ($clients as $client) {
    $columns = [];
    $values = [];

    foreach ($client as $key => $value) {
        $columns[] = "`$key`";
        if ($value === null) {
            $values[] = 'NULL';
        } else {
            $values[] = "'" . addslashes((string) $value) . "'";
        }
    }

    $sql .= "INSERT INTO `clients` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ") ON DUPLICATE KEY UPDATE ";

    // Update kısmı (id hariç)
    $updates = [];
    foreach ($client as $key => $value) {
        if ($key !== 'id') {
            if ($value === null) {
                $updates[] = "`$key` = NULL";
            } else {
                $updates[] = "`$key` = '" . addslashes((string) $value) . "'";
            }
        }
    }
    $sql .= implode(', ', $updates) . ";\n";
}

// Dosyaya yaz
$exportFile = __DIR__ . '/clients_data.sql';
file_put_contents($exportFile, $sql);

echo "✅ Export tamamlandı!\n";
echo "📁 Dosya: $exportFile\n";
echo "📊 Kayıt sayısı: $count\n\n";

echo "GitHub'a göndermek için:\n";
echo "  git add install/clients_data.sql\n";
echo "  git commit -m \"Müşteri verileri güncellendi\"\n";
echo "  git push\n";
