<?php
/**
 * API Bağlantı Testi AJAX Handler
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';

// Oturum kontrolü
session_name(SESSION_NAME);
session_start();

// Oturum kontrolü
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Oturum açmalısınız.']);
    exit;
}

header('Content-Type: application/json');

$provider = $_POST['provider'] ?? ($_POST['integration_type'] ?? '');

try {
    if ($provider === 'domainname') {
        require_once dirname(__DIR__, 2) . '/modules/registrars/domainnameapi/DomainNameAPI.php';

        // Önce POST verilerini kontrol et, yoksa veritabanından çek
        // Js tarafında form datasını gönderirsek POST çalışır
        $username = $_POST['domainname_username'] ?? '';
        $password = $_POST['domainname_password'] ?? '';
        $testMode = isset($_POST['domainname_test_mode']) ? $_POST['domainname_test_mode'] === '1' : false;

        // Eğer POST boşsa veritabanından çek
        if (empty($username)) {
            $settings = Database::fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_group = 'integrations'");
            $config = [];
            foreach ($settings as $s) {
                $config[$s['setting_key']] = $s['setting_value'];
            }
            $username = $config['domainname_username'] ?? '';
            $password = $config['domainname_password'] ?? '';
            $testMode = ($config['domainname_test_mode'] ?? '0') === '1';
        }

        $api = new DomainNameAPI($username, $password, $testMode);

        $result = $api->testConnection();
        echo json_encode($result);

    } elseif ($provider === 'metunic') {
        echo json_encode(['success' => false, 'message' => 'Metunic modülü henüz yüklenmedi.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Geçersiz sağlayıcı: ' . htmlspecialchars($provider)]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
