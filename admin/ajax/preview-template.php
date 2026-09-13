<?php
/**
 * WHMVM Admin - E-posta Şablonu Önizleme
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Settings.php';
require_once dirname(__DIR__, 2) . '/includes/Mail.php';

require_once dirname(__DIR__, 2) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

// Admin kontrolü
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo '<p>Yetkisiz erişim</p>';
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    echo '<p>Şablon bulunamadı</p>';
    exit;
}

$template = Database::fetch("SELECT * FROM email_templates WHERE id = ?", [$id]);

if (!$template) {
    echo '<p>Şablon bulunamadı</p>';
    exit;
}

// Örnek değişkenler
$sampleVariables = [
    'client_name' => 'Ahmet Yılmaz',
    'client_email' => 'ahmet@example.com',
    'site_name' => Settings::get('site_name', 'WHMVM'),
    'site_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
    'company_name' => Settings::get('company_name', 'Şirket Adı'),
    'company_email' => Settings::get('company_email', 'info@example.com'),
    'company_phone' => Settings::get('company_phone', '+90 212 123 45 67'),
    'current_date' => date('d.m.Y'),
    'current_year' => date('Y'),
    'invoice_id' => '1001',
    'invoice_total' => '599.00',
    'due_date' => date('d.m.Y', strtotime('+7 days')),
    'payment_amount' => '599.00',
    'payment_date' => date('d.m.Y'),
    'transaction_id' => 'TXN-' . rand(100000, 999999),
    'order_id' => '2001',
    'product_name' => 'VDS Sunucu Pro',
    'order_total' => '299.00',
    'domain' => 'example.com',
    'ip_address' => '192.168.1.100',
    'username' => 'admin',
    'password' => 'p@ssw0rd123',
    'ticket_id' => '3001',
    'ticket_subject' => 'Sunucu Bağlantı Sorunu',
    'ticket_department' => 'Teknik Destek',
    'ticket_priority' => 'Yüksek',
    'reply_staff' => 'Destek Ekibi',
    'reply_message' => 'Merhaba, sorununuz çözülmüştür. Başka bir konuda yardıma ihtiyacınız olursa bize ulaşın.',
    'suspend_reason' => 'Ödenmemiş fatura',
    'suspend_date' => date('d.m.Y'),
    'termination_reason' => '30 gün ödeme yapılmadı',
    'termination_date' => date('d.m.Y'),
    'days_overdue' => '7',
    'days_until_due' => '3',
    'days_until_expiry' => '14',
    'expiry_date' => date('d.m.Y', strtotime('+14 days')),
    'reset_link' => '#'
];

// Değişkenleri değiştir
$subject = Mail::replaceVariables($template['subject'], $sampleVariables);
$body = Mail::replaceVariables($template['body'], $sampleVariables);

// HTML wrapper uygula
$html = Mail::wrapHtmlTemplate($body);

// Subject'i de göster
$html = str_replace('<body', '<body><div style="background:#f1f5f9;padding:15px 20px;border-bottom:2px solid #e2e8f0;font-family:sans-serif;"><strong style="color:#64748b;">Konu:</strong> <span style="color:#1e293b;">' . htmlspecialchars($subject) . '</span></div', $html);

echo $html;

