<?php
/**
 * WHMVM - Hizmet Düzenleme
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Mail.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Hizmet Düzenle';
$currentPage = 'services';
$db = Database::getInstance();
$message = '';
$messageType = 'success';

$serviceId = (int)($_GET['id'] ?? 0);
if (!$serviceId) {
    header('Location: services.php');
    exit;
}

// Hizmet bilgilerini çek
$service = Database::fetch("
    SELECT s.*, 
           c.first_name, c.last_name, c.email,
           p.name as product_name, p.type as product_type,
           srv.name as server_name, srv.hostname as server_hostname
    FROM services s
    LEFT JOIN clients c ON s.client_id = c.id
    LEFT JOIN products p ON s.product_id = p.id
    LEFT JOIN servers srv ON s.server_id = srv.id
    WHERE s.id = ?
", [$serviceId]);

if (!$service) {
    header('Location: services.php');
    exit;
}

// Sunucuları çek
$servers = Database::fetchAll("SELECT * FROM servers WHERE is_active = 1 ORDER BY name");

// Güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $domain = trim($_POST['domain'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $dedicatedIp = trim($_POST['dedicated_ip'] ?? '');
    $serverId = !empty($_POST['server_id']) ? (int)$_POST['server_id'] : null;
    $status = $_POST['status'] ?? 'pending';
    $billingCycle = $_POST['billing_cycle'] ?? 'monthly';
    $amount = (float)($_POST['amount'] ?? 0);
    $nextDueDate = $_POST['next_due_date'] ?: null;
    $notes = trim($_POST['notes'] ?? '');
    $adminNotes = trim($_POST['admin_notes'] ?? '');
    
    // Ek bilgiler (JSON olarak saklanacak)
    $moduleData = [
        'control_panel_url' => trim($_POST['control_panel_url'] ?? ''),
        'control_panel_user' => trim($_POST['control_panel_user'] ?? ''),
        'control_panel_pass' => trim($_POST['control_panel_pass'] ?? ''),
        'ftp_host' => trim($_POST['ftp_host'] ?? ''),
        'ftp_user' => trim($_POST['ftp_user'] ?? ''),
        'ftp_pass' => trim($_POST['ftp_pass'] ?? ''),
        'ftp_port' => trim($_POST['ftp_port'] ?? '21'),
        'ssh_port' => trim($_POST['ssh_port'] ?? '22'),
        'mysql_host' => trim($_POST['mysql_host'] ?? ''),
        'mysql_user' => trim($_POST['mysql_user'] ?? ''),
        'mysql_pass' => trim($_POST['mysql_pass'] ?? ''),
        'nameservers' => trim($_POST['nameservers'] ?? ''),
        'extra_info' => trim($_POST['extra_info'] ?? '')
    ];
    
    Database::query("
        UPDATE services SET 
            domain = ?, username = ?, password = ?, dedicated_ip = ?, 
            server_id = ?, status = ?, billing_cycle = ?, amount = ?,
            next_due_date = ?, notes = ?, admin_notes = ?, module_data = ?,
            updated_at = NOW()
        WHERE id = ?
    ", [
        $domain, $username, $password, $dedicatedIp,
        $serverId, $status, $billingCycle, $amount,
        $nextDueDate, $notes, $adminNotes, json_encode($moduleData),
        $serviceId
    ]);
    
    $message = 'Hizmet bilgileri başarıyla güncellendi.';
    $messageType = 'success';
    
    // Verileri yeniden yükle
    $service = Database::fetch("
        SELECT s.*, 
               c.first_name, c.last_name, c.email,
               p.name as product_name, p.type as product_type,
               srv.name as server_name, srv.hostname as server_hostname
        FROM services s
        LEFT JOIN clients c ON s.client_id = c.id
        LEFT JOIN products p ON s.product_id = p.id
        LEFT JOIN servers srv ON s.server_id = srv.id
        WHERE s.id = ?
    ", [$serviceId]);
    
    // Module data'yı yeniden parse et
    $moduleData = json_decode($service['module_data'] ?? '{}', true) ?: [];
}

// Hizmet bilgilerini mail ile gönder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_service_info'])) {
    try {
        $moduleData = json_decode($service['module_data'] ?? '{}', true) ?: [];
        
        // Mail içeriği oluştur
        $emailContent = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: var(--y-metin-2); }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: var(--y-yuzey-2); padding: 30px; border: 1px solid var(--y-cizgi); }
        .section { margin-bottom: 25px; }
        .section-title { font-size: 18px; font-weight: 600; color: #6366f1; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid var(--y-cizgi); }
        .info-row { display: flex; margin-bottom: 12px; }
        .info-label { font-weight: 600; width: 150px; color: var(--y-metin-3); }
        .info-value { flex: 1; color: var(--y-metin); }
        .credentials-box { background: var(--y-yuzey); border: 1px solid var(--y-cizgi); border-radius: 8px; padding: 15px; margin-top: 10px; }
        .footer { text-align: center; padding: 20px; color: var(--y-metin-3); font-size: 12px; border-top: 1px solid var(--y-cizgi); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Hizmet Bilgileriniz</h1>
            <p>Sayın ' . htmlspecialchars($service['first_name'] . ' ' . $service['last_name']) . '</p>
        </div>
        <div class="content">
            <div class="section">
                <div class="section-title">📦 Hizmet Bilgileri</div>
                <div class="info-row">
                    <div class="info-label">Ürün:</div>
                    <div class="info-value">' . htmlspecialchars($service['product_name'] ?? '-') . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Domain/Hostname:</div>
                    <div class="info-value">' . htmlspecialchars($service['domain'] ?? '-') . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Durum:</div>
                    <div class="info-value">' . htmlspecialchars(ucfirst($service['status'] ?? 'pending')) . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Faturalama Dönemi:</div>
                    <div class="info-value">' . htmlspecialchars(ucfirst($service['billing_cycle'] ?? 'monthly')) . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Aylık Ücret:</div>
                    <div class="info-value">' . number_format((float)($service['amount'] ?? 0), 2) . ' ₺</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Sonraki Ödeme:</div>
                    <div class="info-value">' . ($service['next_due_date'] ? date('d.m.Y', strtotime($service['next_due_date'])) : '-') . '</div>
                </div>
            </div>';
        
        // Kullanıcı adı ve şifre varsa
        if (!empty($service['username']) || !empty($service['password'])) {
            $emailContent .= '
            <div class="section">
                <div class="section-title">🔐 Erişim Bilgileri</div>
                <div class="credentials-box">';
            if (!empty($service['username'])) {
                $emailContent .= '<div class="info-row"><div class="info-label">Kullanıcı Adı:</div><div class="info-value"><strong>' . htmlspecialchars($service['username']) . '</strong></div></div>';
            }
            if (!empty($service['password'])) {
                $emailContent .= '<div class="info-row"><div class="info-label">Şifre:</div><div class="info-value"><strong>' . htmlspecialchars($service['password']) . '</strong></div></div>';
            }
            if (!empty($service['dedicated_ip'])) {
                $emailContent .= '<div class="info-row"><div class="info-label">Dedicated IP:</div><div class="info-value"><strong>' . htmlspecialchars($service['dedicated_ip']) . '</strong></div></div>';
            }
            $emailContent .= '</div></div>';
        }
        
        // Kontrol Paneli bilgileri
        if (!empty($moduleData['control_panel_url'])) {
            $emailContent .= '
            <div class="section">
                <div class="section-title">⚙️ Kontrol Paneli</div>
                <div class="credentials-box">
                    <div class="info-row"><div class="info-label">Panel URL:</div><div class="info-value"><a href="' . htmlspecialchars($moduleData['control_panel_url']) . '">' . htmlspecialchars($moduleData['control_panel_url']) . '</a></div></div>';
            if (!empty($moduleData['control_panel_user'])) {
                $emailContent .= '<div class="info-row"><div class="info-label">Kullanıcı Adı:</div><div class="info-value"><strong>' . htmlspecialchars($moduleData['control_panel_user']) . '</strong></div></div>';
            }
            if (!empty($moduleData['control_panel_pass'])) {
                $emailContent .= '<div class="info-row"><div class="info-label">Şifre:</div><div class="info-value"><strong>' . htmlspecialchars($moduleData['control_panel_pass']) . '</strong></div></div>';
            }
            $emailContent .= '</div></div>';
        }
        
        // FTP Bilgileri
        if (!empty($moduleData['ftp_host'])) {
            $emailContent .= '
            <div class="section">
                <div class="section-title">📁 FTP Bilgileri</div>
                <div class="credentials-box">
                    <div class="info-row"><div class="info-label">FTP Host:</div><div class="info-value">' . htmlspecialchars($moduleData['ftp_host']) . '</div></div>';
            if (!empty($moduleData['ftp_user'])) {
                $emailContent .= '<div class="info-row"><div class="info-label">Kullanıcı Adı:</div><div class="info-value"><strong>' . htmlspecialchars($moduleData['ftp_user']) . '</strong></div></div>';
            }
            if (!empty($moduleData['ftp_pass'])) {
                $emailContent .= '<div class="info-row"><div class="info-label">Şifre:</div><div class="info-value"><strong>' . htmlspecialchars($moduleData['ftp_pass']) . '</strong></div></div>';
            }
            if (!empty($moduleData['ftp_port'])) {
                $emailContent .= '<div class="info-row"><div class="info-label">Port:</div><div class="info-value">' . htmlspecialchars($moduleData['ftp_port']) . '</div></div>';
            }
            $emailContent .= '</div></div>';
        }
        
        // MySQL Bilgileri
        if (!empty($moduleData['mysql_host'])) {
            $emailContent .= '
            <div class="section">
                <div class="section-title">🗄️ MySQL Veritabanı</div>
                <div class="credentials-box">
                    <div class="info-row"><div class="info-label">Host:</div><div class="info-value">' . htmlspecialchars($moduleData['mysql_host']) . '</div></div>';
            if (!empty($moduleData['mysql_user'])) {
                $emailContent .= '<div class="info-row"><div class="info-label">Kullanıcı Adı:</div><div class="info-value"><strong>' . htmlspecialchars($moduleData['mysql_user']) . '</strong></div></div>';
            }
            if (!empty($moduleData['mysql_pass'])) {
                $emailContent .= '<div class="info-row"><div class="info-label">Şifre:</div><div class="info-value"><strong>' . htmlspecialchars($moduleData['mysql_pass']) . '</strong></div></div>';
            }
            $emailContent .= '</div></div>';
        }
        
        // DNS Bilgileri
        if (!empty($moduleData['nameservers'])) {
            $emailContent .= '
            <div class="section">
                <div class="section-title">🌐 DNS Bilgileri</div>
                <div class="credentials-box">
                    <div class="info-value">' . nl2br(htmlspecialchars($moduleData['nameservers'])) . '</div>
                </div>
            </div>';
        }
        
        // Ek Bilgiler
        if (!empty($moduleData['extra_info'])) {
            $emailContent .= '
            <div class="section">
                <div class="section-title">ℹ️ Ek Bilgiler</div>
                <div class="credentials-box">
                    <div class="info-value">' . nl2br(htmlspecialchars($moduleData['extra_info'])) . '</div>
                </div>
            </div>';
        }
        
        // Notlar
        if (!empty($service['notes'])) {
            $emailContent .= '
            <div class="section">
                <div class="section-title">📝 Notlar</div>
                <div class="credentials-box">
                    <div class="info-value">' . nl2br(htmlspecialchars($service['notes'])) . '</div>
                </div>
            </div>';
        }
        
        $emailContent .= '
        </div>
        <div class="footer">
            <p>Bu bilgiler güvenli bir şekilde saklanmalıdır.</p>
            <p>' . SITE_NAME . ' - Destek Ekibi</p>
        </div>
    </div>
</body>
</html>';
        
        // Mail gönder - Şablon kullan
        $templateVars = [
            'client_name' => $service['first_name'] . ' ' . $service['last_name'],
            'product_name' => $service['product_name'] ?? 'Hizmet',
            'domain' => $service['domain'] ?? '-',
            'status' => ucfirst($service['status'] ?? 'pending'),
            'billing_cycle' => ucfirst($service['billing_cycle'] ?? 'monthly'),
            'amount' => number_format((float)($service['amount'] ?? 0), 2) . ' ₺',
            'next_due_date' => $service['next_due_date'] ? date('d.m.Y', strtotime($service['next_due_date'])) : '-',
            'username' => $service['username'] ?? '',
            'password' => $service['password'] ?? '',
            'dedicated_ip' => $service['dedicated_ip'] ?? '',
            'control_panel_url' => $moduleData['control_panel_url'] ?? '',
            'control_panel_user' => $moduleData['control_panel_user'] ?? '',
            'control_panel_pass' => $moduleData['control_panel_pass'] ?? '',
            'ftp_host' => $moduleData['ftp_host'] ?? '',
            'ftp_user' => $moduleData['ftp_user'] ?? '',
            'ftp_pass' => $moduleData['ftp_pass'] ?? '',
            'ftp_port' => $moduleData['ftp_port'] ?? '21',
            'mysql_host' => $moduleData['mysql_host'] ?? '',
            'mysql_user' => $moduleData['mysql_user'] ?? '',
            'mysql_pass' => $moduleData['mysql_pass'] ?? '',
            'nameservers' => $moduleData['nameservers'] ?? '',
            'extra_info' => $moduleData['extra_info'] ?? '',
            'notes' => $service['notes'] ?? '',
            'service_info_html' => $emailContent
        ];
        
        Mail::sendTemplate('service_info', $service['email'], $templateVars, $service['first_name'] . ' ' . $service['last_name']);
        
        $message = 'Hizmet bilgileri müşteriye e-posta ile gönderildi.';
        $messageType = 'success';
    } catch (Throwable $e) {
        $message = 'E-posta gönderilirken hata oluştu: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Module data'yı parse et
$moduleData = json_decode($service['module_data'] ?? '{}', true) ?: [];

$statusColors = [
    'pending' => 'warning',
    'active' => 'success',
    'suspended' => 'danger',
    'terminated' => 'gray',
    'cancelled' => 'gray'
];

include 'includes/header.php';
?>

<style>
/* Hero Header */
.service-header {
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #8b5cf6 100%);
    border-radius: 20px;
    padding: 35px 40px;
    margin-bottom: 30px;
    color: #fff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(59, 130, 246, 0.3);
}
.service-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: pulse 8s ease-in-out infinite;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.1); opacity: 0.8; }
}
.service-header-left {
    display: flex;
    align-items: center;
    gap: 20px;
    position: relative;
    z-index: 1;
}
.service-header-left .back-btn {
    width: 48px;
    height: 48px;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    text-decoration: none;
    transition: all 0.3s;
    border: 1px solid rgba(255,255,255,0.3);
}
.service-header-left .back-btn:hover {
    background: rgba(255,255,255,0.3);
    transform: translateX(-3px);
}
.service-header h1 {
    font-size: 28px;
    font-weight: 800;
    margin: 0 0 8px 0;
    text-shadow: 0 2px 10px rgba(0,0,0,0.2);
}
.service-header .client-info {
    font-size: 14px;
    opacity: 0.95;
    display: flex;
    align-items: center;
    gap: 8px;
}
.service-header .client-info a {
    color: #fff;
    text-decoration: none;
    font-weight: 500;
    transition: opacity 0.2s;
}
.service-header .client-info a:hover {
    opacity: 0.8;
    text-decoration: underline;
}
.service-header-right {
    position: relative;
    z-index: 1;
}
.service-header-right .status-badge {
    padding: 12px 24px;
    border-radius: 50px;
    font-size: 14px;
    font-weight: 700;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.3);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

/* Alert */
.alert {
    padding: 18px 24px;
    border-radius: 14px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    animation: slideDown 0.3s ease;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.alert-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.1));
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #059669;
}
.alert i {
    font-size: 20px;
}

/* Grid Layout */
.edit-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
}
@media (max-width: 1024px) {
    .edit-grid { grid-template-columns: 1fr; }
}

/* Cards */
.card {
    background: var(--bg-card);
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    margin-bottom: 25px;
    border: 1px solid rgba(255,255,255,0.05);
    overflow: hidden;
    transition: all 0.3s;
}
.card:hover {
    box-shadow: 0 8px 30px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.card-header {
    padding: 24px 28px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 16px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(139, 92, 246, 0.02));
}
.card-header .icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: #fff;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
}
.card-header h3 {
    font-size: 18px;
    font-weight: 700;
    margin: 0;
    color: var(--text);
}
.card-body {
    padding: 28px;
}

/* Form Grid */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
}
.form-grid.cols-3 {
    grid-template-columns: repeat(3, 1fr);
}
@media (max-width: 768px) {
    .form-grid, .form-grid.cols-3 { grid-template-columns: 1fr; }
}

.form-group {
    margin-bottom: 0;
}
.form-group.full {
    grid-column: 1 / -1;
}
.form-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 700;
    color: var(--gray);
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.form-group label i {
    color: var(--primary);
    font-size: 14px;
}
.form-control {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-size: 15px;
    transition: all 0.3s;
    background: var(--bg-secondary);
    color: var(--text);
    font-family: inherit;
}
.form-control:hover {
    border-color: rgba(99, 102, 241, 0.3);
}
.form-control:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
    background: var(--bg-card);
}
.form-control::placeholder {
    color: var(--text-muted);
    opacity: 0.6;
}
textarea.form-control {
    min-height: 120px;
    resize: vertical;
    line-height: 1.6;
}
select.form-control {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236366f1' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 16px center;
    padding-right: 45px;
}

/* Info Box */
.info-box {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.08), rgba(99, 102, 241, 0.05));
    border: 1px solid rgba(59, 130, 246, 0.2);
    border-radius: 14px;
    padding: 18px 20px;
    margin-bottom: 16px;
    transition: all 0.3s;
}
.info-box:hover {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.12), rgba(99, 102, 241, 0.08));
    transform: translateX(4px);
}
.info-box .info-label {
    font-size: 11px;
    color: var(--gray);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}
.info-box .info-value {
    font-size: 16px;
    font-weight: 700;
    color: var(--text);
}
.info-box .info-value a {
    color: var(--primary);
    text-decoration: none;
    transition: all 0.2s;
}
.info-box .info-value a:hover {
    text-decoration: underline;
}

/* Save Button */
.btn-save {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #fff;
    border: none;
    padding: 16px 36px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: all 0.3s;
    width: 100%;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.btn-save:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
}
.btn-save:active {
    transform: translateY(-1px);
}

/* Section Title */
.section-title {
    font-size: 12px;
    font-weight: 800;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin: 28px 0 18px 0;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
}
.section-title:first-child {
    margin-top: 0;
}
.section-title::before {
    content: '';
    width: 4px;
    height: 16px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: 2px;
}

/* Password Field */
.password-field {
    position: relative;
}
.password-field input {
    padding-right: 90px;
    font-family: 'Courier New', monospace;
    letter-spacing: 2px;
}
.password-field .actions {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    gap: 6px;
}
.password-field .actions button {
    background: var(--bg-secondary);
    border: 2px solid var(--border);
    width: 36px;
    height: 36px;
    border-radius: 8px;
    cursor: pointer;
    color: var(--gray);
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.password-field .actions button:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
    transform: scale(1.1);
}
.password-field .actions button:active {
    transform: scale(0.95);
}

/* Status Select Colors */
.status-select[data-status="active"] {
    border-color: rgba(16, 185, 129, 0.3);
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.05), rgba(5, 150, 105, 0.02));
}
.status-select[data-status="pending"] {
    border-color: rgba(245, 158, 11, 0.3);
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.05), rgba(217, 119, 6, 0.02));
}
.status-select[data-status="suspended"] {
    border-color: rgba(239, 68, 68, 0.3);
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.05), rgba(220, 38, 38, 0.02));
}
.status-select[data-status="terminated"],
.status-select[data-status="cancelled"] {
    border-color: rgba(107, 114, 128, 0.3);
    background: linear-gradient(135deg, rgba(107, 114, 128, 0.05), rgba(75, 85, 99, 0.02));
}

/* Date Input */
input[type="date"] {
    position: relative;
}
input[type="date"]::-webkit-calendar-picker-indicator {
    cursor: pointer;
    opacity: 0.6;
    transition: opacity 0.2s;
}
input[type="date"]::-webkit-calendar-picker-indicator:hover {
    opacity: 1;
}

/* Number Input */
input[type="number"] {
    font-weight: 600;
}
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    opacity: 0.6;
    cursor: pointer;
}

/* URL Input */
input[type="url"] {
    font-family: 'Courier New', monospace;
    font-size: 14px;
}

/* Textarea Improvements */
textarea.form-control {
    font-family: inherit;
    line-height: 1.7;
}

/* Responsive Improvements */
@media (max-width: 768px) {
    .service-header {
        padding: 25px 20px;
        flex-direction: column;
        align-items: flex-start;
        gap: 20px;
    }
    .service-header h1 {
        font-size: 22px;
    }
    .card-body {
        padding: 20px;
    }
    .form-grid {
        gap: 18px;
    }
}
</style>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?>">
    <i class="fas fa-check-circle"></i>
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="service-header">
    <div class="service-header-left">
        <a href="services.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1><?= htmlspecialchars($service['product_name'] ?? 'Hizmet') ?> #<?= $serviceId ?></h1>
            <div class="client-info">
                <a href="client-view.php?id=<?= $service['client_id'] ?>"><?= htmlspecialchars($service['first_name'] . ' ' . $service['last_name']) ?></a>
                - <?= htmlspecialchars($service['email']) ?>
            </div>
        </div>
    </div>
    <div class="service-header-right">
        <span class="status-badge badge-<?= $statusColors[$service['status']] ?? 'gray' ?>">
            <?= ucfirst($service['status']) ?>
        </span>
    </div>
</div>

<form method="POST">
<div class="edit-grid">
    <div>
        <!-- Temel Bilgiler -->
        <div class="card">
            <div class="card-header">
                <div class="icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);"><i class="fas fa-info-circle"></i></div>
                <h3>Temel Bilgiler</h3>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-globe"></i> Domain / Hostname</label>
                        <input type="text" name="domain" class="form-control" value="<?= htmlspecialchars($service['domain'] ?? '') ?>" placeholder="ornek.com">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-network-wired"></i> IP Adresi</label>
                        <input type="text" name="dedicated_ip" class="form-control" value="<?= htmlspecialchars($service['dedicated_ip'] ?? '') ?>" placeholder="192.168.1.1">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Kullanıcı Adı</label>
                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($service['username'] ?? '') ?>" placeholder="user123">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Şifre</label>
                        <div class="password-field">
                            <input type="text" name="password" id="mainPassword" class="form-control" value="<?= htmlspecialchars($service['password'] ?? '') ?>" placeholder="••••••••">
                            <div class="actions">
                                <button type="button" onclick="generatePassword('mainPassword')" title="Şifre Oluştur"><i class="fas fa-random"></i></button>
                                <button type="button" onclick="copyPassword('mainPassword')" title="Kopyala"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-server"></i> Sunucu</label>
                        <select name="server_id" class="form-control">
                            <option value="">-- Sunucu Seçin --</option>
                            <?php foreach ($servers as $srv): ?>
                            <option value="<?= $srv['id'] ?>" <?= ($service['server_id'] ?? '') == $srv['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($srv['name']) ?> (<?= htmlspecialchars($srv['hostname']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-toggle-on"></i> Durum</label>
                        <select name="status" class="form-control status-select" data-status="<?= $service['status'] ?>">
                            <option value="pending" <?= $service['status'] === 'pending' ? 'selected' : '' ?>>Beklemede</option>
                            <option value="active" <?= $service['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                            <option value="suspended" <?= $service['status'] === 'suspended' ? 'selected' : '' ?>>Askıya Alındı</option>
                            <option value="terminated" <?= $service['status'] === 'terminated' ? 'selected' : '' ?>>Sonlandırıldı</option>
                            <option value="cancelled" <?= $service['status'] === 'cancelled' ? 'selected' : '' ?>>İptal Edildi</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Kontrol Paneli Bilgileri -->
        <div class="card">
            <div class="card-header">
                <div class="icon" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);"><i class="fas fa-cogs"></i></div>
                <h3>Kontrol Paneli Bilgileri</h3>
            </div>
            <div class="card-body">
                <div class="form-grid cols-3">
                    <div class="form-group full">
                        <label><i class="fas fa-link"></i> Panel URL</label>
                        <input type="url" name="control_panel_url" class="form-control" value="<?= htmlspecialchars($moduleData['control_panel_url'] ?? '') ?>" placeholder="https://panel.sunucu.com:2083">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Panel Kullanıcı Adı</label>
                        <input type="text" name="control_panel_user" class="form-control" value="<?= htmlspecialchars($moduleData['control_panel_user'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Panel Şifresi</label>
                        <div class="password-field">
                            <input type="text" name="control_panel_pass" id="panelPass" class="form-control" value="<?= htmlspecialchars($moduleData['control_panel_pass'] ?? '') ?>">
                            <div class="actions">
                                <button type="button" onclick="generatePassword('panelPass')" title="Şifre Oluştur"><i class="fas fa-random"></i></button>
                                <button type="button" onclick="copyPassword('panelPass')" title="Kopyala"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="section-title">FTP Bilgileri</div>
                <div class="form-grid cols-3">
                    <div class="form-group">
                        <label><i class="fas fa-server"></i> FTP Host</label>
                        <input type="text" name="ftp_host" class="form-control" value="<?= htmlspecialchars($moduleData['ftp_host'] ?? '') ?>" placeholder="ftp.ornek.com">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> FTP Kullanıcı</label>
                        <input type="text" name="ftp_user" class="form-control" value="<?= htmlspecialchars($moduleData['ftp_user'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> FTP Şifre</label>
                        <div class="password-field">
                            <input type="text" name="ftp_pass" id="ftpPass" class="form-control" value="<?= htmlspecialchars($moduleData['ftp_pass'] ?? '') ?>">
                            <div class="actions">
                                <button type="button" onclick="generatePassword('ftpPass')" title="Şifre Oluştur"><i class="fas fa-random"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-plug"></i> FTP Port</label>
                        <input type="text" name="ftp_port" class="form-control" value="<?= htmlspecialchars($moduleData['ftp_port'] ?? '21') ?>" placeholder="21">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-terminal"></i> SSH Port</label>
                        <input type="text" name="ssh_port" class="form-control" value="<?= htmlspecialchars($moduleData['ssh_port'] ?? '22') ?>" placeholder="22">
                    </div>
                </div>
                
                <div class="section-title">MySQL Veritabanı</div>
                <div class="form-grid cols-3">
                    <div class="form-group">
                        <label><i class="fas fa-database"></i> MySQL Host</label>
                        <input type="text" name="mysql_host" class="form-control" value="<?= htmlspecialchars($moduleData['mysql_host'] ?? '') ?>" placeholder="localhost">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> MySQL Kullanıcı</label>
                        <input type="text" name="mysql_user" class="form-control" value="<?= htmlspecialchars($moduleData['mysql_user'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> MySQL Şifre</label>
                        <div class="password-field">
                            <input type="text" name="mysql_pass" id="mysqlPass" class="form-control" value="<?= htmlspecialchars($moduleData['mysql_pass'] ?? '') ?>">
                            <div class="actions">
                                <button type="button" onclick="generatePassword('mysqlPass')" title="Şifre Oluştur"><i class="fas fa-random"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="section-title">DNS Bilgileri</div>
                <div class="form-group">
                    <label><i class="fas fa-globe"></i> Nameserverlar</label>
                    <textarea name="nameservers" class="form-control" placeholder="ns1.sunucu.com&#10;ns2.sunucu.com"><?= htmlspecialchars($moduleData['nameservers'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Notlar -->
        <div class="card">
            <div class="card-header">
                <div class="icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);"><i class="fas fa-sticky-note"></i></div>
                <h3>Notlar</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Müşteri Notları (Müşteri görebilir)</label>
                    <textarea name="notes" class="form-control"><?= htmlspecialchars($service['notes'] ?? '') ?></textarea>
                </div>
                <div class="form-group" style="margin-top: 20px;">
                    <label><i class="fas fa-lock"></i> Admin Notları (Sadece admin görebilir)</label>
                    <textarea name="admin_notes" class="form-control"><?= htmlspecialchars($service['admin_notes'] ?? '') ?></textarea>
                </div>
                <div class="form-group" style="margin-top: 20px;">
                    <label><i class="fas fa-info-circle"></i> Ek Bilgiler</label>
                    <textarea name="extra_info" class="form-control" placeholder="Müşteriye gösterilecek ek bilgiler..."><?= htmlspecialchars($moduleData['extra_info'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>
    
    <div>
        <!-- Ürün Bilgisi -->
        <div class="card">
            <div class="card-header">
                <div class="icon" style="background: linear-gradient(135deg, #10b981, #059669);"><i class="fas fa-box"></i></div>
                <h3>Ürün Bilgisi</h3>
            </div>
            <div class="card-body">
                <div class="info-box">
                    <div class="info-label">Ürün</div>
                    <div class="info-value"><?= htmlspecialchars($service['product_name'] ?? '-') ?></div>
                </div>
                <div class="info-box">
                    <div class="info-label">Ürün Tipi</div>
                    <div class="info-value"><?= ucfirst($service['product_type'] ?? '-') ?></div>
                </div>
                <?php if ($service['order_id']): ?>
                <div class="info-box">
                    <div class="info-label">Sipariş</div>
                    <div class="info-value"><a href="order-view.php?id=<?= $service['order_id'] ?>">#<?= $service['order_id'] ?></a></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Faturalama -->
        <div class="card">
            <div class="card-header">
                <div class="icon" style="background: linear-gradient(135deg, #ec4899, #be185d);"><i class="fas fa-file-invoice-dollar"></i></div>
                <h3>Faturalama</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label><i class="fas fa-sync"></i> Fatura Dönemi</label>
                    <select name="billing_cycle" class="form-control">
                        <option value="monthly" <?= ($service['billing_cycle'] ?? '') === 'monthly' ? 'selected' : '' ?>>Aylık</option>
                        <option value="quarterly" <?= ($service['billing_cycle'] ?? '') === 'quarterly' ? 'selected' : '' ?>>3 Aylık</option>
                        <option value="semiannually" <?= ($service['billing_cycle'] ?? '') === 'semiannually' ? 'selected' : '' ?>>6 Aylık</option>
                        <option value="annually" <?= ($service['billing_cycle'] ?? '') === 'annually' ? 'selected' : '' ?>>Yıllık</option>
                    </select>
                </div>
                <div class="form-group" style="margin-top: 15px;">
                    <label><i class="fas fa-lira-sign"></i> Tutar</label>
                    <input type="number" name="amount" class="form-control" value="<?= $service['amount'] ?? 0 ?>" step="0.01">
                </div>
                <div class="form-group" style="margin-top: 15px;">
                    <label><i class="fas fa-calendar"></i> Sonraki Vade Tarihi</label>
                    <input type="date" name="next_due_date" class="form-control" value="<?= $service['next_due_date'] ?? '' ?>">
                </div>
            </div>
        </div>
        
        <!-- Mail Gönder -->
        <form method="POST" style="margin-bottom: 15px;" onsubmit="return confirm('Hizmet bilgileri müşteriye e-posta ile gönderilecek. Devam etmek istiyor musunuz?');">
            <input type="hidden" name="send_service_info" value="1">
            <button type="submit" class="btn-save" style="width: 100%; background: linear-gradient(135deg, #0ea5e9, #0284c7);">
                <i class="fas fa-envelope"></i> Ürün Hizmet Bilgisini Gönder
            </button>
        </form>
        
        <!-- Kaydet -->
        <button type="submit" class="btn-save" style="width: 100%;">
            <i class="fas fa-save"></i> Değişiklikleri Kaydet
        </button>
    </div>
</div>
</form>

<script>
// Status select renk değişimi
const statusSelect = document.querySelector('.status-select');
if (statusSelect) {
    function updateStatusColor() {
        const status = statusSelect.value;
        statusSelect.setAttribute('data-status', status);
    }
    statusSelect.addEventListener('change', updateStatusColor);
    updateStatusColor();
}

// Şifre oluşturma
function generatePassword(fieldId) {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%';
    let password = '';
    for (let i = 0; i < 16; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    const field = document.getElementById(fieldId);
    field.value = password;
    
    // Görsel feedback
    field.style.borderColor = '#10b981';
    field.style.boxShadow = '0 0 0 4px rgba(16, 185, 129, 0.1)';
    setTimeout(() => {
        field.style.borderColor = '';
        field.style.boxShadow = '';
    }, 1000);
}

// Şifre kopyalama
function copyPassword(fieldId) {
    const field = document.getElementById(fieldId);
    field.select();
    field.setSelectionRange(0, 99999); // Mobile için
    
    navigator.clipboard.writeText(field.value).then(() => {
        // Görsel feedback
        const btn = field.parentElement.querySelector('.actions button:last-child');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i>';
        btn.style.background = '#10b981';
        btn.style.borderColor = '#10b981';
        btn.style.color = '#fff';
        
        // Field feedback
        field.style.borderColor = '#10b981';
        field.style.boxShadow = '0 0 0 4px rgba(16, 185, 129, 0.1)';
        
        setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.style.background = '';
            btn.style.borderColor = '';
            btn.style.color = '';
            field.style.borderColor = '';
            field.style.boxShadow = '';
        }, 2000);
    }).catch(err => {
        // Fallback for older browsers
        document.execCommand('copy');
        alert('Şifre kopyalandı!');
    });
}

// Form submit animasyonu
const form = document.querySelector('form');
if (form) {
    form.addEventListener('submit', function(e) {
        const btn = document.querySelector('.btn-save');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';
        }
    });
}

// Input focus animasyonları
document.querySelectorAll('.form-control').forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.classList.add('focused');
    });
    input.addEventListener('blur', function() {
        this.parentElement.classList.remove('focused');
    });
});
</script>

<?php include 'includes/footer.php'; ?>

