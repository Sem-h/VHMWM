<?php
// WHMVM Proposal Setup
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';

$db = Database::getInstance();

echo "<h1>Teklif Modülü Kurulumu</h1>";

try {
    $db->query("CREATE TABLE IF NOT EXISTS proposals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        subject VARCHAR(255) NOT NULL,
        status ENUM('Draft', 'Sent', 'Accepted', 'Rejected', 'Expired') DEFAULT 'Draft',
        total_amount DECIMAL(10,2) DEFAULT 0.00,
        currency VARCHAR(3) DEFAULT 'TRY',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        valid_until DATE,
        admin_notes TEXT,
        client_notes TEXT,
        pdf_path VARCHAR(255) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "✅ Proposals tablosu oluşturuldu.<br>";
} catch (Exception $e) {
    echo "❌ Hata (Proposals): " . $e->getMessage() . "<br>";
}

try {
    $db->query("CREATE TABLE IF NOT EXISTS proposal_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proposal_id INT NOT NULL,
        description VARCHAR(255) NOT NULL,
        quantity INT DEFAULT 1,
        unit_price DECIMAL(10,2) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        INDEX (proposal_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "✅ Proposal Items tablosu oluşturuldu.<br>";
} catch (Exception $e) {
    echo "❌ Hata (Items): " . $e->getMessage() . "<br>";
}

echo "<br><b>Kurulum tamamlandı!</b> <a href='proposals.php'>Yönetim Paneline Git</a>";
?>