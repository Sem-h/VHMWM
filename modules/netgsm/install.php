<?php
/**
 * NetGSM Modül Kurulum Scripti
 */

require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/Database.php';

$defaultConfig = json_encode([
    'usercode' => '',
    'password' => '',
    'msgheader' => '',
    'test_mode' => true
]);

// Varsa güncelle, yoksa ekle
$exists = Database::fetch("SELECT id FROM modules WHERE slug = 'netgsm'");

if (!$exists) {
    Database::query("INSERT INTO modules (name, slug, version, description, author, category, install_path, config, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", [
        'NetGSM SMS Modülü',
        'netgsm',
        '1.0.0',
        'NetGSM SMS entegrasyonu - Müşterilere toplu veya tekli SMS bildirimleri gönderin',
        'WHMVM',
        'integration',
        'modules/netgsm',
        $defaultConfig,
        0
    ]);
    echo "✅ NetGSM modülü başarıyla kuruldu.\n";
} else {
    echo "ℹ️ NetGSM modülü zaten kurulu.\n";
}
