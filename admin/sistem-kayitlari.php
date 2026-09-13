<?php
/**
 * WHMVM - Admin Sistem Kayıtları
 * Müşteri, E-posta ve Entegrasyon loglarını görüntüleme
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
session_name(SESSION_NAME); session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Sistem Kayıtları';
$currentPage = 'sistem-kayitlari';
$db = Database::getInstance();

// Aktif tab
$activeTab = $_GET['tab'] ?? 'email';

// Sayfalama
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Filtreler
$searchQuery = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Log silme işlemi
$message = '';
$messageType = 'success';

// Log türü isimleri
$logTypeNames = [
    'email' => 'E-posta Logları',
    'client' => 'Müşteri Logları',
    'integration' => 'Entegrasyon Logları',
    'order' => 'Sipariş Logları',
    'cron' => 'Cron Job Logları'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_logs'])) {
        $logType = $_POST['log_type'] ?? '';
        $daysOld = (int)($_POST['days_old'] ?? 30);
        
        try {
            $dateLimit = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));
            $deletedCount = 0;
            
            if ($logType === 'email') {
                $deletedCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM email_logs WHERE created_at < ?", [$dateLimit]);
                Database::query("DELETE FROM email_logs WHERE created_at < ?", [$dateLimit]);
                $message = "{$daysOld} günden eski e-posta logları silindi.";
            } elseif ($logType === 'client') {
                $deletedCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM client_logs WHERE created_at < ?", [$dateLimit]);
                Database::query("DELETE FROM client_logs WHERE created_at < ?", [$dateLimit]);
                $message = "{$daysOld} günden eski müşteri logları silindi.";
            } elseif ($logType === 'integration') {
                $deletedCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM integration_logs WHERE created_at < ?", [$dateLimit]);
                Database::query("DELETE FROM integration_logs WHERE created_at < ?", [$dateLimit]);
                $message = "{$daysOld} günden eski entegrasyon logları silindi.";
            } elseif ($logType === 'order') {
                $deletedCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM order_logs WHERE created_at < ?", [$dateLimit]);
                Database::query("DELETE FROM order_logs WHERE created_at < ?", [$dateLimit]);
                $message = "{$daysOld} günden eski sipariş logları silindi.";
            } elseif ($logType === 'cron') {
                // Cron logları için last_run tarihini kontrol et
                $deletedCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM cron_tasks WHERE last_run < ? AND last_run IS NOT NULL", [$dateLimit]);
                // Sadece log bilgilerini temizle, task'ı silme
                Database::query("UPDATE cron_tasks SET last_status = NULL, last_output = NULL, last_run = NULL WHERE last_run < ? AND last_run IS NOT NULL", [$dateLimit]);
                $message = "{$daysOld} günden eski cron job logları temizlendi.";
            }
            
            // Kalıcı bildirim kaydet - hangi admin, hangi log türünü, ne zaman temizledi
            if ($deletedCount > 0) {
                $clearInfo = json_encode([
                    'admin_name' => $_SESSION['admin_name'] ?? 'Yönetici',
                    'log_type' => $logTypeNames[$logType] ?? $logType,
                    'cleared_at' => date('d.m.Y H:i'),
                    'days_old' => $daysOld,
                    'deleted_count' => $deletedCount
                ], JSON_UNESCAPED_UNICODE);
                
                // Settings tablosuna kaydet
                $existingClear = Database::fetchColumn("SELECT setting_value FROM settings WHERE setting_key = ?", ['log_clear_history']);
                $clearHistory = $existingClear ? json_decode($existingClear, true) : [];
                if (!is_array($clearHistory)) $clearHistory = [];
                
                // Yeni kaydı ekle (en fazla 50 kayıt tut)
                array_unshift($clearHistory, [
                    'admin_name' => $_SESSION['admin_name'] ?? 'Yönetici',
                    'log_type' => $logTypeNames[$logType] ?? $logType,
                    'cleared_at' => date('d.m.Y H:i'),
                    'days_old' => $daysOld,
                    'deleted_count' => $deletedCount
                ]);
                $clearHistory = array_slice($clearHistory, 0, 50);
                
                // Kaydet veya güncelle
                if ($existingClear !== false && $existingClear !== null) {
                    Database::query("UPDATE settings SET setting_value = ? WHERE setting_key = ?", [json_encode($clearHistory, JSON_UNESCAPED_UNICODE), 'log_clear_history']);
                } else {
                    Database::query("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)", ['log_clear_history', json_encode($clearHistory, JSON_UNESCAPED_UNICODE)]);
                }
            }
        } catch (Throwable $e) {
            $message = 'Log silme sırasında hata oluştu.';
            $messageType = 'error';
        }
    }
}

// Log temizleme geçmişini al
$logClearHistory = [];
try {
    $historyJson = Database::fetchColumn("SELECT setting_value FROM settings WHERE setting_key = ?", ['log_clear_history']);
    if ($historyJson) {
        $logClearHistory = json_decode($historyJson, true) ?: [];
    }
} catch (Throwable $e) {
    // Hata olursa boş geç
}

// Tabloları kontrol et ve oluştur
function ensureLogTables($db) {
    // client_logs tablosu
    try {
        $db->query("SELECT 1 FROM client_logs LIMIT 1");
    } catch (Throwable $e) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS client_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                client_id INT UNSIGNED,
                action VARCHAR(100) NOT NULL,
                description TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_client (client_id),
                INDEX idx_action (action),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    // integration_logs tablosu
    try {
        $db->query("SELECT 1 FROM integration_logs LIMIT 1");
    } catch (Throwable $e) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS integration_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                integration VARCHAR(100) NOT NULL,
                action VARCHAR(100) NOT NULL,
                request TEXT,
                response TEXT,
                status ENUM('success', 'error', 'warning') DEFAULT 'success',
                execution_time FLOAT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_integration (integration),
                INDEX idx_status (status),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    // order_logs tablosu
    try {
        $db->query("SELECT 1 FROM order_logs LIMIT 1");
    } catch (Throwable $e) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS order_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_id INT UNSIGNED,
                client_id INT UNSIGNED,
                action VARCHAR(100) NOT NULL,
                description TEXT,
                old_status VARCHAR(50),
                new_status VARCHAR(50),
                admin_id INT UNSIGNED,
                ip_address VARCHAR(45),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_order (order_id),
                INDEX idx_client (client_id),
                INDEX idx_action (action),
                INDEX idx_created (created_at),
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

ensureLogTables($db);

// Verileri çek
$emailLogs = [];
$clientLogs = [];
$integrationLogs = [];
$orderLogs = [];
$cronLogs = [];
$totalRecords = 0;

// Tarih filtresi için WHERE koşulu
$dateWhere = '';
$dateParams = [];
if ($dateFrom) {
    $dateWhere .= " AND created_at >= ?";
    $dateParams[] = $dateFrom . ' 00:00:00';
}
if ($dateTo) {
    $dateWhere .= " AND created_at <= ?";
    $dateParams[] = $dateTo . ' 23:59:59';
}

if ($activeTab === 'email') {
    // E-posta logları
    $where = "WHERE 1=1" . $dateWhere;
    $params = $dateParams;
    
    if ($searchQuery) {
        $where .= " AND (to_email LIKE ? OR subject LIKE ?)";
        $params[] = "%{$searchQuery}%";
        $params[] = "%{$searchQuery}%";
    }
    if ($statusFilter) {
        $where .= " AND status = ?";
        $params[] = $statusFilter;
    }
    
    try {
        $totalRecords = (int)Database::fetchColumn("SELECT COUNT(*) FROM email_logs $where", $params);
        $emailLogs = Database::fetchAll("
            SELECT * FROM email_logs $where 
            ORDER BY created_at DESC 
            LIMIT $perPage OFFSET $offset
        ", $params);
    } catch (Throwable $e) {
        $emailLogs = [];
    }
    
} elseif ($activeTab === 'client') {
    // Müşteri logları
    $where = "WHERE 1=1" . $dateWhere;
    $params = $dateParams;
    
    if ($searchQuery) {
        $where .= " AND (cl.action LIKE ? OR cl.description LIKE ? OR c.email LIKE ?)";
        $params[] = "%{$searchQuery}%";
        $params[] = "%{$searchQuery}%";
        $params[] = "%{$searchQuery}%";
    }
    
    try {
        $totalRecords = (int)Database::fetchColumn("
            SELECT COUNT(*) FROM client_logs cl 
            LEFT JOIN clients c ON cl.client_id = c.id 
            $where
        ", $params);
        $clientLogs = Database::fetchAll("
            SELECT cl.*, c.first_name, c.last_name, c.email 
            FROM client_logs cl 
            LEFT JOIN clients c ON cl.client_id = c.id 
            $where
            ORDER BY cl.created_at DESC 
            LIMIT $perPage OFFSET $offset
        ", $params);
    } catch (Throwable $e) {
        $clientLogs = [];
    }
    
} elseif ($activeTab === 'integration') {
    // Entegrasyon logları
    $where = "WHERE 1=1" . $dateWhere;
    $params = $dateParams;
    
    if ($searchQuery) {
        $where .= " AND (integration LIKE ? OR action LIKE ?)";
        $params[] = "%{$searchQuery}%";
        $params[] = "%{$searchQuery}%";
    }
    if ($statusFilter) {
        $where .= " AND status = ?";
        $params[] = $statusFilter;
    }
    
    try {
        $totalRecords = (int)Database::fetchColumn("SELECT COUNT(*) FROM integration_logs $where", $params);
        $integrationLogs = Database::fetchAll("
            SELECT * FROM integration_logs $where 
            ORDER BY created_at DESC 
            LIMIT $perPage OFFSET $offset
        ", $params);
    } catch (Throwable $e) {
        $integrationLogs = [];
    }
    
} elseif ($activeTab === 'order') {
    // Sipariş logları
    $where = "WHERE 1=1" . $dateWhere;
    $params = $dateParams;
    
    if ($searchQuery) {
        $where .= " AND (ol.action LIKE ? OR ol.description LIKE ? OR o.order_number LIKE ? OR c.email LIKE ?)";
        $params[] = "%{$searchQuery}%";
        $params[] = "%{$searchQuery}%";
        $params[] = "%{$searchQuery}%";
        $params[] = "%{$searchQuery}%";
    }
    
    try {
        $totalRecords = (int)Database::fetchColumn("
            SELECT COUNT(*) FROM order_logs ol
            LEFT JOIN orders o ON ol.order_id = o.id
            LEFT JOIN clients c ON ol.client_id = c.id
            $where
        ", $params);
        $orderLogs = Database::fetchAll("
            SELECT ol.*, o.order_number, c.first_name, c.last_name, c.email,
                   a.username as admin_username
            FROM order_logs ol
            LEFT JOIN orders o ON ol.order_id = o.id
            LEFT JOIN clients c ON ol.client_id = c.id
            LEFT JOIN admins a ON ol.admin_id = a.id
            $where
            ORDER BY ol.created_at DESC
            LIMIT $perPage OFFSET $offset
        ", $params);
    } catch (Throwable $e) {
        $orderLogs = [];
    }
    
} elseif ($activeTab === 'cron') {
    // Cron job logları
    $where = "WHERE last_run IS NOT NULL" . $dateWhere;
    $params = $dateParams;
    
    if ($searchQuery) {
        $where .= " AND (name LIKE ? OR command LIKE ?)";
        $params[] = "%{$searchQuery}%";
        $params[] = "%{$searchQuery}%";
    }
    if ($statusFilter) {
        $where .= " AND last_status = ?";
        $params[] = $statusFilter;
    }
    
    try {
        $totalRecords = (int)Database::fetchColumn("SELECT COUNT(*) FROM cron_tasks $where", $params);
        $cronLogs = Database::fetchAll("
            SELECT * FROM cron_tasks $where 
            ORDER BY last_run DESC 
            LIMIT $perPage OFFSET $offset
        ", $params);
    } catch (Throwable $e) {
        $cronLogs = [];
    }
}

$totalPages = (int)ceil($totalRecords / $perPage);

// İstatistikler
$stats = [
    'email_total' => 0,
    'email_sent' => 0,
    'email_failed' => 0,
    'client_total' => 0,
    'integration_total' => 0,
    'integration_errors' => 0,
    'order_total' => 0
];

try {
    $stats['email_total'] = (int)Database::fetchColumn("SELECT COUNT(*) FROM email_logs");
    $stats['email_sent'] = (int)Database::fetchColumn("SELECT COUNT(*) FROM email_logs WHERE status = 'sent'");
    $stats['email_failed'] = (int)Database::fetchColumn("SELECT COUNT(*) FROM email_logs WHERE status = 'failed'");
} catch (Throwable $e) {}

try {
    $stats['client_total'] = (int)Database::fetchColumn("SELECT COUNT(*) FROM client_logs");
} catch (Throwable $e) {}

try {
    $stats['integration_total'] = (int)Database::fetchColumn("SELECT COUNT(*) FROM integration_logs");
    $stats['integration_errors'] = (int)Database::fetchColumn("SELECT COUNT(*) FROM integration_logs WHERE status = 'error'");
} catch (Throwable $e) {}

try {
    $stats['order_total'] = (int)Database::fetchColumn("SELECT COUNT(*) FROM order_logs");
} catch (Throwable $e) {}

try {
    $stats['cron_total'] = (int)Database::fetchColumn("SELECT COUNT(*) FROM cron_tasks WHERE last_run IS NOT NULL");
    $stats['cron_success'] = (int)Database::fetchColumn("SELECT COUNT(*) FROM cron_tasks WHERE last_status = 'success'");
    $stats['cron_failed'] = (int)Database::fetchColumn("SELECT COUNT(*) FROM cron_tasks WHERE last_status = 'failed'");
} catch (Throwable $e) {
    $stats['cron_total'] = 0;
    $stats['cron_success'] = 0;
    $stats['cron_failed'] = 0;
}

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* Sistem Kayıtları Özel Stiller */
.logs-page {
    animation: fadeIn 0.4s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Log Temizleme Geçmişi Bildirimleri */
.clear-history-alerts {
    margin-bottom: 25px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.clear-history-alert {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border: 1px solid #f59e0b;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.15);
}

.clear-history-icon {
    width: 44px;
    height: 44px;
    background: rgba(245, 158, 11, 0.2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #d97706;
    font-size: 18px;
    flex-shrink: 0;
}

.clear-history-content {
    flex: 1;
    font-size: 14px;
    color: #92400e;
    line-height: 1.6;
}

.clear-history-content strong {
    color: #78350f;
}

.log-type-badge {
    display: inline-block;
    padding: 3px 10px;
    background: rgba(120, 53, 15, 0.15);
    color: #78350f;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    margin: 0 4px;
}

.deleted-count {
    display: inline-block;
    margin-left: 8px;
    font-size: 12px;
    color: #b45309;
    font-weight: 500;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    border: 1px solid var(--border);
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
}

.stat-icon.email { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); }
.stat-icon.success { background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); }
.stat-icon.error { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); }
.stat-icon.client { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); }
.stat-icon.integration { background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); }

.stat-info h4 {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 4px;
}

.stat-info p {
    font-size: 13px;
    color: var(--gray);
}

/* Tabs */
.tabs-container {
    background: white;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    border: 1px solid var(--border);
    overflow: hidden;
}

.tabs-header {
    display: flex;
    background: #f8fafc;
    border-bottom: 1px solid var(--border);
    padding: 0 20px;
}

.tab-btn {
    padding: 18px 28px;
    background: none;
    border: none;
    font-size: 14px;
    font-weight: 600;
    color: var(--gray);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
    transition: all 0.3s;
}

.tab-btn:hover {
    color: var(--primary);
}

.tab-btn.active {
    color: var(--primary);
}

.tab-btn.active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--primary);
    border-radius: 3px 3px 0 0;
}

.tab-btn .count {
    background: var(--light);
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 12px;
    color: var(--gray);
}

.tab-btn.active .count {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
}

/* Filter Bar */
.filter-bar {
    padding: 20px 25px;
    background: #fafbfc;
    border-bottom: 1px solid var(--border);
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-group label {
    font-size: 13px;
    font-weight: 600;
    color: var(--gray);
    white-space: nowrap;
}

.filter-input {
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 13px;
    min-width: 150px;
    transition: all 0.2s;
}

.filter-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.btn-filter {
    padding: 10px 20px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

.btn-filter:hover {
    background: var(--primary-dark);
}

.btn-clear {
    padding: 10px 16px;
    background: white;
    color: var(--gray);
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-clear:hover {
    border-color: var(--danger);
    color: var(--danger);
}

/* Table */
.logs-table {
    width: 100%;
    border-collapse: collapse;
}

.logs-table th {
    padding: 14px 20px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--gray);
    background: #f8fafc;
    border-bottom: 1px solid var(--border);
}

.logs-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    color: var(--dark);
    vertical-align: middle;
}

.logs-table tr:hover td {
    background: #fafbfc;
}

.logs-table tr:last-child td {
    border-bottom: none;
}

/* Status Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.status-badge.sent {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.failed {
    background: #fee2e2;
    color: #991b1b;
}

.status-badge.pending {
    background: #fef3c7;
    color: #92400e;
}

.status-badge.success {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.error {
    background: #fee2e2;
    color: #991b1b;
}

.status-badge.warning {
    background: #fef3c7;
    color: #92400e;
}

/* Email Cell */
.email-cell {
    max-width: 250px;
}

.email-cell .to {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.email-cell .subject {
    font-size: 13px;
    color: var(--gray);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Client Cell */
.client-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.client-avatar {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
}

.client-info .name {
    font-weight: 600;
    color: var(--dark);
}

.client-info .email {
    font-size: 12px;
    color: var(--gray);
}

/* Action Cell */
.action-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #f1f5f9;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    color: var(--dark);
}

/* Integration Cell */
.integration-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.integration-icon {
    width: 36px;
    height: 36px;
    background: #f1f5f9;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

/* Time Cell */
.time-cell {
    font-size: 13px;
    color: var(--gray);
}

.time-cell .date {
    font-weight: 600;
    color: var(--dark);
}

/* View Button */
.btn-view {
    padding: 8px 14px;
    background: #f1f5f9;
    color: var(--dark);
    border: none;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-view:hover {
    background: var(--primary);
    color: white;
}

/* Clear Logs Section */
.clear-logs-section {
    padding: 20px 25px;
    background: #fff8f8;
    border-top: 1px solid #fecaca;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
}

.clear-logs-section p {
    font-size: 14px;
    color: #991b1b;
    display: flex;
    align-items: center;
    gap: 8px;
}

.clear-form {
    display: flex;
    align-items: center;
    gap: 10px;
}

.clear-form select {
    padding: 8px 12px;
    border: 1px solid #fecaca;
    border-radius: 6px;
    font-size: 13px;
    background: white;
}

.btn-danger-outline {
    padding: 8px 16px;
    background: white;
    color: #dc2626;
    border: 1px solid #dc2626;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-danger-outline:hover {
    background: #dc2626;
    color: white;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 80px 40px;
}

.empty-icon {
    width: 100px;
    height: 100px;
    background: #f1f5f9;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    margin: 0 auto 24px;
}

.empty-state h3 {
    font-size: 20px;
    color: var(--dark);
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--gray);
    font-size: 14px;
}

/* Pagination */
.pagination-wrapper {
    padding: 20px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid var(--border);
}

.pagination-info {
    font-size: 13px;
    color: var(--gray);
}

.pagination {
    display: flex;
    gap: 5px;
}

.pagination a, .pagination span {
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    color: var(--dark);
    background: white;
    border: 1px solid var(--border);
    transition: all 0.2s;
}

.pagination a:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.pagination .active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Alert */
.alert-floating {
    position: fixed;
    top: 100px;
    right: 30px;
    padding: 16px 24px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    z-index: 1000;
    animation: slideInRight 0.4s ease;
}

@keyframes slideInRight {
    from { opacity: 0; transform: translateX(50px); }
    to { opacity: 1; transform: translateX(0); }
}

.alert-floating.success {
    background: #10b981;
    color: white;
}

.alert-floating.error {
    background: #ef4444;
    color: white;
}

/* Modal for log details */
.log-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.log-modal.active {
    display: flex;
}

.log-modal-content {
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 700px;
    max-height: 80vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.log-modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.log-modal-header h3 {
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.log-modal-body {
    padding: 24px;
    overflow-y: auto;
}

.log-detail-row {
    display: flex;
    margin-bottom: 16px;
}

.log-detail-row label {
    width: 120px;
    font-weight: 600;
    color: var(--gray);
    font-size: 13px;
    flex-shrink: 0;
}

.log-detail-row .value {
    flex: 1;
    color: var(--dark);
    font-size: 14px;
    word-break: break-all;
}

.log-content-box {
    background: #f8fafc;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 16px;
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 12px;
    line-height: 1.6;
    max-height: 200px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-all;
}
</style>

<div class="logs-page">
    
    <?php if ($message): ?>
        <div class="alert-floating <?= $messageType ?>">
            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
        <script>setTimeout(() => document.querySelector('.alert-floating').style.display = 'none', 4000);</script>
    <?php endif; ?>
    
    <!-- Log Temizleme Geçmişi Bildirimleri (Kalıcı) -->
    <?php if (!empty($logClearHistory)): ?>
        <div class="clear-history-alerts">
            <?php foreach ($logClearHistory as $index => $clearItem): ?>
                <div class="clear-history-alert">
                    <div class="clear-history-icon">
                        <i class="fas fa-trash-alt"></i>
                    </div>
                    <div class="clear-history-content">
                        <strong><?= htmlspecialchars($clearItem['admin_name']) ?></strong> adlı yönetici 
                        <span class="log-type-badge"><?= htmlspecialchars($clearItem['log_type']) ?></span> 
                        kayıtlarını <strong><?= htmlspecialchars($clearItem['cleared_at']) ?></strong> tarihinde temizledi.
                        <span class="deleted-count">(<?= number_format($clearItem['deleted_count']) ?> kayıt silindi)</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- İstatistik Kartları -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon email">📧</div>
            <div class="stat-info">
                <h4><?= number_format($stats['email_total']) ?></h4>
                <p>Toplam E-posta</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success">✅</div>
            <div class="stat-info">
                <h4><?= number_format($stats['email_sent']) ?></h4>
                <p>Başarılı Gönderim</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon error">❌</div>
            <div class="stat-info">
                <h4><?= number_format($stats['email_failed']) ?></h4>
                <p>Başarısız Gönderim</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon client">👤</div>
            <div class="stat-info">
                <h4><?= number_format($stats['client_total']) ?></h4>
                <p>Müşteri Aktivitesi</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon integration">🔌</div>
            <div class="stat-info">
                <h4><?= number_format($stats['integration_total']) ?></h4>
                <p>Entegrasyon Logu</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);">🛒</div>
            <div class="stat-info">
                <h4><?= number_format($stats['order_total']) ?></h4>
                <p>Sipariş Logu</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);">⏰</div>
            <div class="stat-info">
                <h4><?= number_format($stats['cron_total'] ?? 0) ?></h4>
                <p>Cron Job Logu</p>
            </div>
        </div>
    </div>
    
    <!-- Tab Container -->
    <div class="tabs-container">
        <!-- Tab Başlıkları -->
        <div class="tabs-header">
            <a href="?tab=email" class="tab-btn <?= $activeTab === 'email' ? 'active' : '' ?>">
                <i class="fas fa-envelope"></i>
                E-posta Logları
                <span class="count"><?= number_format($stats['email_total']) ?></span>
            </a>
            <a href="?tab=client" class="tab-btn <?= $activeTab === 'client' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                Müşteri Logları
                <span class="count"><?= number_format($stats['client_total']) ?></span>
            </a>
            <a href="?tab=integration" class="tab-btn <?= $activeTab === 'integration' ? 'active' : '' ?>">
                <i class="fas fa-plug"></i>
                Entegrasyon Logları
                <span class="count"><?= number_format($stats['integration_total']) ?></span>
            </a>
            <a href="?tab=order" class="tab-btn <?= $activeTab === 'order' ? 'active' : '' ?>">
                <i class="fas fa-shopping-cart"></i>
                Sipariş Logları
                <span class="count"><?= number_format($stats['order_total']) ?></span>
            </a>
            <a href="?tab=cron" class="tab-btn <?= $activeTab === 'cron' ? 'active' : '' ?>">
                <i class="fas fa-clock"></i>
                Cron Job Logları
                <span class="count"><?= number_format($stats['cron_total'] ?? 0) ?></span>
            </a>
        </div>
        
        <!-- Filtre Bölümü -->
        <form method="GET" class="filter-bar">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
            
            <div class="filter-group">
                <label><i class="fas fa-search"></i></label>
                <input type="text" name="search" class="filter-input" placeholder="Ara..." 
                       value="<?= htmlspecialchars($searchQuery) ?>">
            </div>
            
            <?php if ($activeTab === 'email' || $activeTab === 'integration' || $activeTab === 'cron'): ?>
            <div class="filter-group">
                <label>Durum:</label>
                <select name="status" class="filter-input">
                    <option value="">Tümü</option>
                    <?php if ($activeTab === 'email'): ?>
                        <option value="sent" <?= $statusFilter === 'sent' ? 'selected' : '' ?>>Gönderildi</option>
                        <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Başarısız</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Beklemede</option>
                    <?php elseif ($activeTab === 'cron'): ?>
                        <option value="success" <?= $statusFilter === 'success' ? 'selected' : '' ?>>Başarılı</option>
                        <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Başarısız</option>
                        <option value="running" <?= $statusFilter === 'running' ? 'selected' : '' ?>>Çalışıyor</option>
                    <?php else: ?>
                        <option value="success" <?= $statusFilter === 'success' ? 'selected' : '' ?>>Başarılı</option>
                        <option value="error" <?= $statusFilter === 'error' ? 'selected' : '' ?>>Hata</option>
                        <option value="warning" <?= $statusFilter === 'warning' ? 'selected' : '' ?>>Uyarı</option>
                    <?php endif; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="filter-group">
                <label>Başlangıç:</label>
                <input type="date" name="date_from" class="filter-input" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            
            <div class="filter-group">
                <label>Bitiş:</label>
                <input type="date" name="date_to" class="filter-input" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            
            <button type="submit" class="btn-filter">
                <i class="fas fa-filter"></i> Filtrele
            </button>
            
            <a href="?tab=<?= $activeTab ?>" class="btn-clear">Temizle</a>
        </form>
        
        <!-- E-posta Logları -->
        <?php if ($activeTab === 'email'): ?>
            <?php if (empty($emailLogs)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📧</div>
                    <h3>E-posta Logu Bulunamadı</h3>
                    <p>Henüz e-posta gönderim kaydı yok veya filtrelere uygun sonuç bulunamadı.</p>
                </div>
            <?php else: ?>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Alıcı / Konu</th>
                            <th>Şablon</th>
                            <th>Durum</th>
                            <th>Tarih</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emailLogs as $log): ?>
                        <tr>
                            <td>
                                <div class="email-cell">
                                    <div class="to"><?= htmlspecialchars($log['to_email']) ?></div>
                                    <div class="subject"><?= htmlspecialchars($log['subject']) ?></div>
                                </div>
                            </td>
                            <td>
                                <span class="action-badge">
                                    <?= htmlspecialchars($log['template_name'] ?? 'Manuel') ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?= $log['status'] ?>">
                                    <?php if ($log['status'] === 'sent'): ?>
                                        <i class="fas fa-check"></i> Gönderildi
                                    <?php elseif ($log['status'] === 'failed'): ?>
                                        <i class="fas fa-times"></i> Başarısız
                                    <?php else: ?>
                                        <i class="fas fa-clock"></i> Beklemede
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <div class="time-cell">
                                    <div class="date"><?= date('d.m.Y', strtotime($log['created_at'])) ?></div>
                                    <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                </div>
                            </td>
                            <td>
                                <button class="btn-view" onclick="showEmailLog(<?= htmlspecialchars(json_encode($log)) ?>)">
                                    <i class="fas fa-eye"></i> Detay
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Müşteri Logları -->
        <?php if ($activeTab === 'client'): ?>
            <?php if (empty($clientLogs)): ?>
                <div class="empty-state">
                    <div class="empty-icon">👤</div>
                    <h3>Müşteri Logu Bulunamadı</h3>
                    <p>Henüz müşteri aktivite kaydı yok veya filtrelere uygun sonuç bulunamadı.</p>
                </div>
            <?php else: ?>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Müşteri</th>
                            <th>Aksiyon</th>
                            <th>Açıklama</th>
                            <th>IP Adresi</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientLogs as $log): ?>
                        <tr>
                            <td>
                                <div class="client-cell">
                                    <div class="client-avatar">
                                        <?= strtoupper(substr($log['first_name'] ?? '?', 0, 1)) ?>
                                    </div>
                                    <div class="client-info">
                                        <div class="name"><?= htmlspecialchars(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?></div>
                                        <div class="email"><?= htmlspecialchars($log['email'] ?? 'Bilinmiyor') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="action-badge">
                                    <?= htmlspecialchars($log['action']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars(mb_substr($log['description'] ?? '', 0, 50)) ?>...</td>
                            <td><code><?= htmlspecialchars($log['ip_address'] ?? '-') ?></code></td>
                            <td>
                                <div class="time-cell">
                                    <div class="date"><?= date('d.m.Y', strtotime($log['created_at'])) ?></div>
                                    <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Entegrasyon Logları -->
        <?php if ($activeTab === 'integration'): ?>
            <?php if (empty($integrationLogs)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🔌</div>
                    <h3>Entegrasyon Logu Bulunamadı</h3>
                    <p>Henüz entegrasyon kaydı yok veya filtrelere uygun sonuç bulunamadı.</p>
                </div>
            <?php else: ?>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Entegrasyon</th>
                            <th>Aksiyon</th>
                            <th>Durum</th>
                            <th>Süre</th>
                            <th>Tarih</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($integrationLogs as $log): ?>
                        <tr>
                            <td>
                                <div class="integration-cell">
                                    <div class="integration-icon">
                                        <?php
                                        $icon = match(strtolower($log['integration'])) {
                                            'proxmox' => '🖥️',
                                            'virtualizor' => '💻',
                                            'plesk' => '🌐',
                                            'cpanel' => '⚙️',
                                            'stripe' => '💳',
                                            'paypal' => '🅿️',
                                            default => '🔌'
                                        };
                                        echo $icon;
                                        ?>
                                    </div>
                                    <?= htmlspecialchars($log['integration']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="action-badge"><?= htmlspecialchars($log['action']) ?></span>
                            </td>
                            <td>
                                <span class="status-badge <?= $log['status'] ?>">
                                    <?php if ($log['status'] === 'success'): ?>
                                        <i class="fas fa-check"></i> Başarılı
                                    <?php elseif ($log['status'] === 'error'): ?>
                                        <i class="fas fa-times"></i> Hata
                                    <?php else: ?>
                                        <i class="fas fa-exclamation"></i> Uyarı
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td><?= number_format((float)$log['execution_time'], 3) ?>s</td>
                            <td>
                                <div class="time-cell">
                                    <div class="date"><?= date('d.m.Y', strtotime($log['created_at'])) ?></div>
                                    <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                </div>
                            </td>
                            <td>
                                <button class="btn-view" onclick="showIntegrationLog(<?= htmlspecialchars(json_encode($log)) ?>)">
                                    <i class="fas fa-eye"></i> Detay
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Sipariş Logları -->
        <?php if ($activeTab === 'order'): ?>
            <?php if (empty($orderLogs)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🛒</div>
                    <h3>Sipariş Logu Bulunamadı</h3>
                    <p>Henüz sipariş kaydı yok veya filtrelere uygun sonuç bulunamadı.</p>
                </div>
            <?php else: ?>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Sipariş</th>
                            <th>Müşteri</th>
                            <th>Aksiyon</th>
                            <th>Durum Değişikliği</th>
                            <th>Yönetici</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderLogs as $log): ?>
                        <tr>
                            <td>
                                <?php if ($log['order_id']): ?>
                                <a href="order-view.php?id=<?= $log['order_id'] ?>" style="color: var(--primary); text-decoration: none; font-weight: 600;">
                                    <?= htmlspecialchars($log['order_number'] ?? 'ORD-' . $log['order_id']) ?>
                                </a>
                                <?php else: ?>
                                <span style="color: var(--gray);">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log['client_id']): ?>
                                <div class="client-cell">
                                    <div class="client-avatar">
                                        <?= strtoupper(substr($log['first_name'] ?? '?', 0, 1)) ?>
                                    </div>
                                    <div class="client-info">
                                        <div class="name"><?= htmlspecialchars(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?></div>
                                        <div class="email"><?= htmlspecialchars($log['email'] ?? 'Bilinmiyor') ?></div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <span style="color: var(--gray);">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="action-badge">
                                    <?= htmlspecialchars($log['action']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($log['old_status'] && $log['new_status']): ?>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="status-badge <?= $log['old_status'] === 'active' ? 'success' : ($log['old_status'] === 'pending' ? 'pending' : 'error') ?>" style="font-size: 11px; padding: 4px 8px;">
                                        <?= ucfirst($log['old_status']) ?>
                                    </span>
                                    <i class="fas fa-arrow-right" style="color: var(--gray); font-size: 12px;"></i>
                                    <span class="status-badge <?= $log['new_status'] === 'active' ? 'success' : ($log['new_status'] === 'pending' ? 'pending' : 'error') ?>" style="font-size: 11px; padding: 4px 8px;">
                                        <?= ucfirst($log['new_status']) ?>
                                    </span>
                                </div>
                                <?php else: ?>
                                <span style="color: var(--gray);">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log['admin_username']): ?>
                                <span style="color: var(--primary); font-weight: 600;">
                                    <i class="fas fa-user-shield"></i> <?= htmlspecialchars($log['admin_username']) ?>
                                </span>
                                <?php else: ?>
                                <span style="color: var(--gray);">Sistem</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="time-cell">
                                    <div class="date"><?= date('d.m.Y', strtotime($log['created_at'])) ?></div>
                                    <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Cron Job Logları -->
        <?php if ($activeTab === 'cron'): ?>
            <?php if (empty($cronLogs)): ?>
                <div class="empty-state">
                    <div class="empty-icon">⏰</div>
                    <h3>Cron Job Logu Bulunamadı</h3>
                    <p>Henüz cron job çalışma kaydı yok veya filtrelere uygun sonuç bulunamadı.</p>
                </div>
            <?php else: ?>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Cron Job</th>
                            <th>Komut</th>
                            <th>Durum</th>
                            <th>Son Çalışma</th>
                            <th>Sonraki Çalışma</th>
                            <th>Çıktı</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cronLogs as $log): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($log['name']) ?></strong>
                                <?php if (!$log['is_active']): ?>
                                    <span class="badge badge-gray" style="margin-left: 8px; font-size: 10px;">Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code style="background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                    <?= htmlspecialchars($log['command']) ?>
                                </code>
                            </td>
                            <td>
                                <?php
                                $statusBadge = match($log['last_status'] ?? '') {
                                    'success' => 'success',
                                    'failed' => 'error',
                                    'running' => 'warning',
                                    default => 'gray'
                                };
                                $statusText = match($log['last_status'] ?? '') {
                                    'success' => 'Başarılı',
                                    'failed' => 'Başarısız',
                                    'running' => 'Çalışıyor',
                                    default => 'Bilinmiyor'
                                };
                                ?>
                                <span class="status-badge <?= $statusBadge ?>">
                                    <?php if ($log['last_status'] === 'success'): ?>
                                        <i class="fas fa-check"></i> Başarılı
                                    <?php elseif ($log['last_status'] === 'failed'): ?>
                                        <i class="fas fa-times"></i> Başarısız
                                    <?php elseif ($log['last_status'] === 'running'): ?>
                                        <i class="fas fa-spinner fa-spin"></i> Çalışıyor
                                    <?php else: ?>
                                        <i class="fas fa-question"></i> Bilinmiyor
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($log['last_run']): ?>
                                <div class="time-cell">
                                    <div class="date"><?= date('d.m.Y', strtotime($log['last_run'])) ?></div>
                                    <?= date('H:i:s', strtotime($log['last_run'])) ?>
                                </div>
                                <?php else: ?>
                                <span style="color: var(--gray);">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log['next_run']): ?>
                                <div class="time-cell">
                                    <div class="date"><?= date('d.m.Y', strtotime($log['next_run'])) ?></div>
                                    <?= date('H:i:s', strtotime($log['next_run'])) ?>
                                </div>
                                <?php else: ?>
                                <span style="color: var(--gray);">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log['last_output']): ?>
                                    <span style="color: var(--gray); font-size: 12px;">
                                        <?= mb_strlen($log['last_output']) > 50 ? mb_substr($log['last_output'], 0, 50) . '...' : htmlspecialchars($log['last_output']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--gray);">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn-view" onclick="showCronLog(<?= htmlspecialchars(json_encode($log)) ?>)">
                                    <i class="fas fa-eye"></i> Detay
                                </button>
                                <?php if ($log['is_active']): ?>
                                    <a href="settings.php?tab=cron&edit=<?= $log['id'] ?>" class="btn-view" style="margin-left: 8px; text-decoration: none;">
                                        <i class="fas fa-edit"></i> Düzenle
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Sayfalama -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination-wrapper">
            <div class="pagination-info">
                Toplam <?= number_format($totalRecords) ?> kayıt, Sayfa <?= $page ?>/<?= $totalPages ?>
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?>&search=<?= urlencode($searchQuery) ?>&status=<?= urlencode($statusFilter) ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a href="?tab=<?= $activeTab ?>&page=<?= $i ?>&search=<?= urlencode($searchQuery) ?>&status=<?= urlencode($statusFilter) ?>" 
                       class="<?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?>&search=<?= urlencode($searchQuery) ?>&status=<?= urlencode($statusFilter) ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Log Temizleme -->
        <div class="clear-logs-section">
            <p>
                <i class="fas fa-trash-alt"></i>
                Eski logları temizleyerek veritabanı boyutunu küçültebilirsiniz.
            </p>
            <form method="POST" class="clear-form" onsubmit="return confirm('Seçili logları silmek istediğinizden emin misiniz?')">
                <input type="hidden" name="log_type" value="<?= $activeTab ?>">
                <select name="days_old">
                    <option value="7">7 günden eski</option>
                    <option value="30" selected>30 günden eski</option>
                    <option value="60">60 günden eski</option>
                    <option value="90">90 günden eski</option>
                </select>
                <button type="submit" name="clear_logs" class="btn-danger-outline">
                    <i class="fas fa-trash"></i> Logları Temizle
                </button>
            </form>
        </div>
    </div>
</div>

<!-- E-posta Log Detay Modal -->
<div class="log-modal" id="emailLogModal">
    <div class="log-modal-content">
        <div class="log-modal-header">
            <h3><i class="fas fa-envelope"></i> E-posta Detayı</h3>
            <button class="close-btn" onclick="closeModal('emailLogModal')">&times;</button>
        </div>
        <div class="log-modal-body" id="emailLogContent">
            <!-- İçerik JS ile doldurulacak -->
        </div>
    </div>
</div>

<!-- Entegrasyon Log Detay Modal -->
<div class="log-modal" id="integrationLogModal">
    <div class="log-modal-content">
        <div class="log-modal-header">
            <h3><i class="fas fa-plug"></i> Entegrasyon Log Detayı</h3>
            <button class="close-btn" onclick="closeModal('integrationLogModal')">&times;</button>
        </div>
        <div class="log-modal-body" id="integrationLogContent">
            <!-- İçerik JS ile doldurulacak -->
        </div>
    </div>
</div>

<!-- Cron Job Log Detay Modal -->
<div class="log-modal" id="cronLogModal">
    <div class="log-modal-content">
        <div class="log-modal-header">
            <h3><i class="fas fa-clock"></i> Cron Job Log Detayı</h3>
            <button class="close-btn" onclick="closeModal('cronLogModal')">&times;</button>
        </div>
        <div class="log-modal-body" id="cronLogContent">
            <!-- İçerik JS ile doldurulacak -->
        </div>
    </div>
</div>

<script>
function showEmailLog(log) {
    const content = document.getElementById('emailLogContent');
    content.innerHTML = `
        <div class="log-detail-row">
            <label>Alıcı:</label>
            <div class="value">${escapeHtml(log.to_email)}</div>
        </div>
        <div class="log-detail-row">
            <label>Gönderen:</label>
            <div class="value">${escapeHtml(log.from_email || '-')}</div>
        </div>
        <div class="log-detail-row">
            <label>Konu:</label>
            <div class="value">${escapeHtml(log.subject)}</div>
        </div>
        <div class="log-detail-row">
            <label>Şablon:</label>
            <div class="value">${escapeHtml(log.template_name || 'Manuel')}</div>
        </div>
        <div class="log-detail-row">
            <label>Durum:</label>
            <div class="value"><span class="status-badge ${log.status}">${log.status === 'sent' ? 'Gönderildi' : (log.status === 'failed' ? 'Başarısız' : 'Beklemede')}</span></div>
        </div>
        <div class="log-detail-row">
            <label>Tarih:</label>
            <div class="value">${log.created_at}</div>
        </div>
        ${log.error_message ? `
        <div class="log-detail-row">
            <label>Hata:</label>
            <div class="value" style="color: #dc2626;">${escapeHtml(log.error_message)}</div>
        </div>
        ` : ''}
        <div style="margin-top: 20px;">
            <label style="display: block; margin-bottom: 10px; font-weight: 600;">E-posta İçeriği:</label>
            <div class="log-content-box">${log.body || 'İçerik mevcut değil'}</div>
        </div>
    `;
    document.getElementById('emailLogModal').classList.add('active');
}

function showIntegrationLog(log) {
    const content = document.getElementById('integrationLogContent');
    content.innerHTML = `
        <div class="log-detail-row">
            <label>Entegrasyon:</label>
            <div class="value">${escapeHtml(log.integration)}</div>
        </div>
        <div class="log-detail-row">
            <label>Aksiyon:</label>
            <div class="value">${escapeHtml(log.action)}</div>
        </div>
        <div class="log-detail-row">
            <label>Durum:</label>
            <div class="value"><span class="status-badge ${log.status}">${log.status === 'success' ? 'Başarılı' : (log.status === 'error' ? 'Hata' : 'Uyarı')}</span></div>
        </div>
        <div class="log-detail-row">
            <label>Süre:</label>
            <div class="value">${parseFloat(log.execution_time).toFixed(3)} saniye</div>
        </div>
        <div class="log-detail-row">
            <label>Tarih:</label>
            <div class="value">${log.created_at}</div>
        </div>
        ${log.request ? `
        <div style="margin-top: 20px;">
            <label style="display: block; margin-bottom: 10px; font-weight: 600;">İstek (Request):</label>
            <div class="log-content-box">${escapeHtml(log.request)}</div>
        </div>
        ` : ''}
        ${log.response ? `
        <div style="margin-top: 20px;">
            <label style="display: block; margin-bottom: 10px; font-weight: 600;">Yanıt (Response):</label>
            <div class="log-content-box">${escapeHtml(log.response)}</div>
        </div>
        ` : ''}
    `;
    document.getElementById('integrationLogModal').classList.add('active');
}

function showCronLog(log) {
    const content = document.getElementById('cronLogContent');
    content.innerHTML = `
        <div class="log-detail-row">
            <label>Cron Job:</label>
            <div class="value"><strong>${escapeHtml(log.name)}</strong></div>
        </div>
        <div class="log-detail-row">
            <label>Açıklama:</label>
            <div class="value">${escapeHtml(log.description || '-')}</div>
        </div>
        <div class="log-detail-row">
            <label>Komut:</label>
            <div class="value"><code style="background: #f1f5f9; padding: 6px 10px; border-radius: 6px; display: inline-block;">${escapeHtml(log.command)}</code></div>
        </div>
        <div class="log-detail-row">
            <label>Durum:</label>
            <div class="value">
                <span class="status-badge ${log.last_status === 'success' ? 'success' : (log.last_status === 'failed' ? 'error' : (log.last_status === 'running' ? 'warning' : 'gray'))}">
                    ${log.last_status === 'success' ? 'Başarılı' : (log.last_status === 'failed' ? 'Başarısız' : (log.last_status === 'running' ? 'Çalışıyor' : 'Bilinmiyor'))}
                </span>
            </div>
        </div>
        <div class="log-detail-row">
            <label>Aktif:</label>
            <div class="value">
                <span class="status-badge ${log.is_active ? 'success' : 'gray'}">
                    ${log.is_active ? 'Evet' : 'Hayır'}
                </span>
            </div>
        </div>
        <div class="log-detail-row">
            <label>Zamanlama:</label>
            <div class="value"><code>${escapeHtml(log.schedule)}</code></div>
        </div>
        ${log.last_run ? `
        <div class="log-detail-row">
            <label>Son Çalışma:</label>
            <div class="value">${log.last_run}</div>
        </div>
        ` : ''}
        ${log.next_run ? `
        <div class="log-detail-row">
            <label>Sonraki Çalışma:</label>
            <div class="value">${log.next_run}</div>
        </div>
        ` : ''}
        ${log.last_output ? `
        <div style="margin-top: 20px;">
            <label style="display: block; margin-bottom: 10px; font-weight: 600;">Son Çıktı:</label>
            <div class="log-content-box">${escapeHtml(log.last_output)}</div>
        </div>
        ` : ''}
    `;
    document.getElementById('cronLogModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Modal dışına tıklayınca kapat
document.querySelectorAll('.log-modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// ESC tuşu ile modal kapat
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.log-modal.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>

