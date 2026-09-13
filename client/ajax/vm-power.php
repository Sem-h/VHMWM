<?php
/**
 * WHMVM - Müşteri VM Power İşlemleri AJAX
 */
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Sifreleme.php';
require_once dirname(__DIR__, 2) . '/includes/ESXi.php';
session_name(SESSION_NAME); session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Oturum geçersiz']);
    exit;
}

$clientId = $_SESSION['client_id'];
$serviceId = (int)($_POST['service_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$serviceId || !$action) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek']);
    exit;
}

// Şifre çözme
function decryptPassword(string $encrypted): string
{
    return Sifreleme::coz($encrypted);
}

try {
    // Hizmetin müşteriye ait olduğunu ve ESXi bilgilerinin olduğunu kontrol et
    $service = Database::fetch("
        SELECT s.*, es.* 
        FROM services s
        LEFT JOIN esxi_servers es ON s.esxi_server_id = es.id
        WHERE s.id = ? AND s.client_id = ? AND s.status = 'active'
    ", [$serviceId, $clientId]);
    
    if (!$service) {
        echo json_encode(['success' => false, 'message' => 'Hizmet bulunamadı veya erişim yetkiniz yok']);
        exit;
    }
    
    if (!$service['esxi_server_id'] || !$service['esxi_vmid']) {
        echo json_encode(['success' => false, 'message' => 'Bu hizmet için VM yapılandırması mevcut değil']);
        exit;
    }
    
    // İzin verilen işlemler
    $allowedActions = ['power_on', 'power_off', 'shutdown', 'reboot', 'reset'];
    if (!in_array($action, $allowedActions)) {
        echo json_encode(['success' => false, 'message' => 'Geçersiz işlem']);
        exit;
    }
    
    // ESXi bağlantısı
    $esxi = new ESXi(
        $service['ip_address'],
        $service['username'],
        decryptPassword($service['password']),
        (int)$service['port'],
        $service['connection_type']
    );
    
    // İşlemi gerçekleştir
    $vmid = $service['esxi_vmid'];
    $result = match($action) {
        'power_on' => $esxi->powerOn($vmid),
        'power_off' => $esxi->powerOff($vmid),
        'shutdown' => $esxi->shutdown($vmid),
        'reset' => $esxi->reset($vmid),
        'reboot' => $esxi->reboot($vmid),
        default => ['success' => false, 'message' => 'Geçersiz işlem']
    };
    
    // Log kaydet
    try {
        Database::insert('esxi_logs', [
            'esxi_server_id' => $service['esxi_server_id'],
            'service_id' => $serviceId,
            'vmid' => $vmid,
            'action' => $action,
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['message'],
            'initiated_by' => 'client',
            'client_id' => $clientId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (Throwable $e) {}
    
    echo json_encode([
        'success' => $result['success'],
        'message' => $result['message'],
        'state' => $result['state'] ?? null
    ]);
    
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Bir hata oluştu: ' . $e->getMessage()]);
}

