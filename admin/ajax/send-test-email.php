<?php
/**
 * WHMVM Admin - Test E-posta Gönderme
 */

declare(strict_types=1);

// Hata çıktılarını engelle
error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

try {
    require_once dirname(__DIR__, 2) . '/config/config.php';
    require_once dirname(__DIR__, 2) . '/includes/Database.php';
    require_once dirname(__DIR__, 2) . '/includes/Settings.php';
    require_once dirname(__DIR__, 2) . '/includes/Mail.php';

    session_name(SESSION_NAME);
    session_start();

    // Admin kontrolü
    if (!isset($_SESSION['admin_id'])) {
        echo json_encode(['success' => false, 'error' => 'Yetkisiz erişim']);
        exit;
    }

    // JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Geçersiz e-posta adresi']);
        exit;
    }

    // Test mail gönder
    $result = Mail::sendTestMail($email);

    echo json_encode([
        'success' => $result['success'],
        'error' => $result['error'] ?? null,
        'debug' => $result['debug'] ?? []
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Sistem hatası: ' . $e->getMessage()
    ]);
}
