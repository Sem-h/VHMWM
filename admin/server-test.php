<?php
/**
 * WHMVM - Sunucu Bağlantı Testi
 * AJAX endpoint
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/WHMApi.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

$serverId = (int)($_POST['server_id'] ?? $_GET['server_id'] ?? 0);
$action = $_POST['action'] ?? $_GET['action'] ?? 'test';

if (!$serverId) {
    echo json_encode(['success' => false, 'message' => 'Sunucu ID gerekli']);
    exit;
}

$server = Database::fetch("SELECT * FROM servers WHERE id = ?", [$serverId]);

if (!$server) {
    echo json_encode(['success' => false, 'message' => 'Sunucu bulunamadı']);
    exit;
}

// Bağlantı testi
if ($action === 'test') {
    $startTime = microtime(true);
    $result = [
        'success' => false,
        'message' => '',
        'ping' => null,
        'port' => false,
        'api' => false,
        'accounts' => null,
        'response_time' => 0
    ];
    
    $host = $server['ip_address'] ?: $server['hostname'];
    $port = (int)($server['port'] ?: 2087);
    
    // 1. Ping testi
    $pingStart = microtime(true);
    if (PHP_OS_FAMILY === 'Windows') {
        exec("ping -n 1 -w 1000 {$host}", $output, $pingResult);
    } else {
        exec("ping -c 1 -W 1 {$host}", $output, $pingResult);
    }
    $pingTime = round((microtime(true) - $pingStart) * 1000, 2);
    
    if ($pingResult === 0) {
        $result['ping'] = $pingTime;
    }
    
    // 2. Port testi
    $socket = @fsockopen($host, $port, $errno, $errstr, 5);
    if ($socket) {
        $result['port'] = true;
        fclose($socket);
    }
    
    // 3. API testi (modüle göre)
    try {
        $apiResult = ServerApi::testConnection($server);
        $result['api'] = $apiResult['success'] ?? false;
        
        if ($result['api']) {
            // Gerçek hesap sayısını al
            $result['accounts'] = ServerApi::getAccountCount($server);
        }
    } catch (Exception $e) {
        $result['api'] = false;
    }
    
    // Sonuç
    $result['response_time'] = round((microtime(true) - $startTime) * 1000, 2);
    
    if ($result['ping'] !== null && $result['port']) {
        $result['success'] = true;
        $result['message'] = 'Sunucu çevrimiçi';
        
        if ($result['api']) {
            $result['message'] .= ' - API bağlantısı başarılı';
        } else {
            $result['message'] .= ' - API bağlantısı kurulamadı';
        }
    } else {
        $result['message'] = 'Sunucu çevrimdışı veya erişilemiyor';
    }
    
    // Son kontrol tarihini güncelle
    Database::query("
        UPDATE servers SET 
            last_check = NOW(),
            last_check_result = ?
        WHERE id = ?
    ", [$result['success'] ? 'online' : 'offline', $serverId]);
    
    echo json_encode($result);
    exit;
}

// Hesap sayısını al
if ($action === 'accounts') {
    try {
        $count = ServerApi::getAccountCount($server);
        echo json_encode([
            'success' => true,
            'accounts' => $count,
            'server_id' => $serverId
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Hesap sayısı alınamadı: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Sunucu istatistikleri
if ($action === 'stats') {
    try {
        $api = ServerApi::create($server);
        
        if ($api && method_exists($api, 'getServerStats')) {
            $stats = $api->getServerStats();
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'İstatistik alınamadı'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Geçersiz işlem']);

