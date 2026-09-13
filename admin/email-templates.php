<?php
/**
 * WHMVM Admin - E-posta Şablonları Yönetimi
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Mail.php';

session_name(SESSION_NAME);
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'E-posta Şablonları';
$currentPage = 'settings';
$message = '';
$messageType = 'success';
$tableExists = true;
$needsUpgrade = false;

// Tablo var mı ve category sütunu var mı kontrol et
try {
    Database::query("SELECT category FROM email_templates LIMIT 1");
} catch (Throwable $e) {
    // Tablo yok veya category sütunu yok
    try {
        Database::query("SELECT 1 FROM email_templates LIMIT 1");
        // Tablo var ama category yok - upgrade gerekli
        $needsUpgrade = true;
        $tableExists = true;
    } catch (Throwable $e2) {
        // Tablo hiç yok
        $tableExists = false;
    }
}

// Tabloları oluştur veya güncelle
if ((!$tableExists || $needsUpgrade) && isset($_GET['install'])) {
    // Önce eski tabloları sil
    try {
        Database::query("DROP TABLE IF EXISTS email_templates");
        Database::query("DROP TABLE IF EXISTS email_logs");
    } catch (Throwable $e) {
        // Silme hatası - devam et
    }
    
    try {
        // E-posta Şablonları Tablosu
        Database::query("
            CREATE TABLE IF NOT EXISTS `email_templates` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL UNIQUE,
                `display_name` VARCHAR(255) NOT NULL,
                `category` VARCHAR(50) NOT NULL DEFAULT 'general',
                `subject` VARCHAR(255) NOT NULL,
                `body` TEXT NOT NULL,
                `variables` TEXT,
                `status` ENUM('active', 'inactive') DEFAULT 'active',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_category` (`category`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // E-posta Logları Tablosu
        Database::query("
            CREATE TABLE IF NOT EXISTS `email_logs` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `to_email` VARCHAR(255) NOT NULL,
                `from_email` VARCHAR(255) NOT NULL,
                `subject` VARCHAR(255) NOT NULL,
                `body` TEXT,
                `template_name` VARCHAR(100) DEFAULT NULL,
                `status` ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
                `error_message` TEXT,
                `client_id` INT UNSIGNED DEFAULT NULL,
                `related_type` VARCHAR(50) DEFAULT NULL,
                `related_id` INT UNSIGNED DEFAULT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_to_email` (`to_email`),
                INDEX `idx_status` (`status`),
                INDEX `idx_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Varsayılan şablonları ekle
        $defaultTemplates = [
            ['welcome_email', 'Hoş Geldiniz E-postası', 'client', 'Hoş Geldiniz {client_name}!', '<h2 style="color: #1e293b; margin: 0 0 20px 0;">Aramıza Hoş Geldiniz!</h2><p style="color: #475569; line-height: 1.6;">Sayın <strong>{client_name}</strong>,</p><p style="color: #475569; line-height: 1.6;">{site_name} ailesine katıldığınız için teşekkür ederiz!</p>', '["client_name", "client_email", "site_name", "site_url"]'],
            ['password_reset', 'Şifre Sıfırlama', 'client', 'Şifre Sıfırlama Talebi', '<h2 style="color: #1e293b; margin: 0 0 20px 0;">Şifre Sıfırlama</h2><p style="color: #475569; line-height: 1.6;">Sayın <strong>{client_name}</strong>,</p><p style="color: #475569; line-height: 1.6;">Şifrenizi sıfırlamak için: <a href="{reset_link}">{reset_link}</a></p>', '["client_name", "reset_link", "site_name"]'],
            ['invoice_created', 'Yeni Fatura', 'billing', '[{site_name}] Yeni Fatura #{invoice_id}', '<h2 style="color: #1e293b; margin: 0 0 20px 0;">Yeni Fatura Oluşturuldu</h2><p style="color: #475569;">Sayın <strong>{client_name}</strong>,</p><p style="color: #475569;">Fatura No: <strong>#{invoice_id}</strong><br>Tutar: <strong>{invoice_total} ₺</strong><br>Son Ödeme: <strong>{due_date}</strong></p>', '["client_name", "invoice_id", "invoice_total", "due_date", "site_name", "site_url"]'],
            ['invoice_paid', 'Fatura Ödendi', 'billing', '[{site_name}] Ödeme Onayı - Fatura #{invoice_id}', '<h2 style="color: #1e293b; margin: 0 0 20px 0;">Ödemeniz Alındı</h2><p style="color: #475569;">Sayın <strong>{client_name}</strong>,</p><p style="color: #475569;">#{invoice_id} numaralı faturanız için ödemeniz alınmıştır.</p>', '["client_name", "invoice_id", "payment_amount", "payment_date", "site_name"]'],
            ['invoice_reminder', 'Fatura Hatırlatması', 'billing', '[{site_name}] Ödeme Hatırlatması - Fatura #{invoice_id}', '<h2 style="color: #1e293b; margin: 0 0 20px 0;">Ödeme Hatırlatması</h2><p style="color: #475569;">Sayın <strong>{client_name}</strong>,</p><p style="color: #475569;">#{invoice_id} numaralı faturanızın son ödeme tarihi: <strong>{due_date}</strong></p>', '["client_name", "invoice_id", "invoice_total", "due_date", "site_name"]'],
            ['invoice_renewal', 'Hizmet Yenileme Faturası', 'billing', '[{site_name}] Hizmet Yenileme Faturası #{invoice_id}', '<h2 style="color: #1e293b; margin: 0 0 20px 0;">Hizmet Yenileme Faturası 🔄</h2><p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">Sayın <strong>{client_name}</strong>,</p><p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">Hizmetiniz için yenileme faturası oluşturulmuştur. Hizmetiniz <strong>{renewal_date}</strong> tarihinde yenilenecektir.</p><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #f8fafc; border-radius: 8px; margin-bottom: 20px;"><tr><td style="padding: 20px;"><table width="100%"><tr><td style="color: #64748b; padding: 8px 0;">Fatura No:</td><td style="color: #1e293b; font-weight: 600; text-align: right;">#{invoice_id}</td></tr><tr><td style="color: #64748b; padding: 8px 0;">Hizmet:</td><td style="color: #1e293b; font-weight: 600; text-align: right;">{product_name}{domain}</td></tr><tr><td style="color: #64748b; padding: 8px 0;">Yenileme Tarihi:</td><td style="color: #10b981; font-weight: 600; text-align: right;">{renewal_date}</td></tr><tr><td style="color: #64748b; padding: 8px 0;">Kalan Süre:</td><td style="color: #1e293b; font-weight: 600; text-align: right;">{days_until_renewal} gün</td></tr><tr><td style="color: #64748b; padding: 8px 0;">Tutar:</td><td style="color: #1e293b; font-weight: 600; text-align: right;">{invoice_total} ₺</td></tr><tr><td style="color: #64748b; padding: 8px 0;">Son Ödeme:</td><td style="color: #ef4444; font-weight: 600; text-align: right;">{due_date}</td></tr></table></td></tr></table><table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 8px;"><a href="{invoice_url}" style="display: inline-block; padding: 14px 30px; color: white; text-decoration: none; font-weight: 600;">Faturayı Görüntüle & Öde →</a></td></tr></table>', '["client_name", "invoice_id", "invoice_total", "due_date", "renewal_date", "days_until_renewal", "product_name", "domain", "invoice_url", "site_name", "site_url"]'],
            ['ticket_opened', 'Destek Talebi Açıldı', 'support', '[{site_name}] Destek Talebi #{ticket_id}', '<h2 style="color: #1e293b; margin: 0 0 20px 0;">Destek Talebiniz Oluşturuldu</h2><p style="color: #475569;">Sayın <strong>{client_name}</strong>,</p><p style="color: #475569;">Ticket No: <strong>#{ticket_id}</strong><br>Konu: <strong>{ticket_subject}</strong></p>', '["client_name", "ticket_id", "ticket_subject", "ticket_department", "site_name"]'],
            ['ticket_reply', 'Destek Talebi Yanıtı', 'support', '[{site_name}] Destek Talebi #{ticket_id} Yanıtlandı', '<h2 style="color: #1e293b; margin: 0 0 20px 0;">Talebinize Yanıt Verildi</h2><p style="color: #475569;">Sayın <strong>{client_name}</strong>,</p><p style="color: #475569;">#{ticket_id} numaralı talebinize yanıt verildi.</p>', '["client_name", "ticket_id", "ticket_subject", "reply_message", "site_name"]'],
            ['service_activated', 'Hizmet Aktif Edildi', 'service', '[{site_name}] Hizmetiniz Aktif Edildi', '<h2 style="color: #1e293b; margin: 0 0 20px 0;">Hizmetiniz Aktif!</h2><p style="color: #475569;">Sayın <strong>{client_name}</strong>,</p><p style="color: #475569;"><strong>{product_name}</strong> hizmetiniz aktif edilmiştir.</p>', '["client_name", "product_name", "domain", "ip_address", "username", "password", "site_name"]'],
            ['service_suspended', 'Hizmet Askıya Alındı', 'service', '[{site_name}] Hizmetiniz Askıya Alındı', '<h2 style="color: #f59e0b; margin: 0 0 20px 0;">Hizmetiniz Askıya Alındı</h2><p style="color: #475569;">Sayın <strong>{client_name}</strong>,</p><p style="color: #475569;"><strong>{product_name}</strong> hizmetiniz askıya alınmıştır.</p>', '["client_name", "product_name", "suspend_reason", "site_name"]'],
            ['service_info', 'Hizmet Bilgileri', 'service', '[{site_name}] Hizmet Bilgileriniz - {product_name}', '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; } .container { max-width: 600px; margin: 0 auto; padding: 20px; } .header { background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; } .content { background: #f8fafc; padding: 30px; border: 1px solid #e2e8f0; } .section { margin-bottom: 25px; } .section-title { font-size: 18px; font-weight: 600; color: #6366f1; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0; } .info-row { display: flex; margin-bottom: 12px; } .info-label { font-weight: 600; width: 150px; color: #64748b; } .info-value { flex: 1; color: #0f172a; } .credentials-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-top: 10px; } .footer { text-align: center; padding: 20px; color: #64748b; font-size: 12px; border-top: 1px solid #e2e8f0; }</style></head><body>{service_info_html}</body></html>', '["client_name", "product_name", "domain", "status", "billing_cycle", "amount", "next_due_date", "username", "password", "dedicated_ip", "control_panel_url", "control_panel_user", "control_panel_pass", "ftp_host", "ftp_user", "ftp_pass", "ftp_port", "mysql_host", "mysql_user", "mysql_pass", "nameservers", "extra_info", "notes", "site_name", "service_info_html"]'],
            ['order_received', 'Sipariş Alındı', 'order', '[{site_name}] Siparişiniz Alındı #{order_id}', '<h2 style="color: #1e293b; margin: 0 0 20px 0;">Siparişiniz Alındı!</h2><p style="color: #475569;">Sayın <strong>{client_name}</strong>,</p><p style="color: #475569;">Sipariş No: <strong>#{order_id}</strong><br>Ürün: <strong>{product_name}</strong></p>', '["client_name", "order_id", "product_name", "order_total", "site_name"]'],
        ];
        
        foreach ($defaultTemplates as $t) {
            Database::query(
                "INSERT IGNORE INTO email_templates (name, display_name, category, subject, body, variables) VALUES (?, ?, ?, ?, ?, ?)",
                $t
            );
        }
        
        $tableExists = true;
        $needsUpgrade = false;
        $message = 'E-posta tabloları ve varsayılan şablonlar başarıyla oluşturuldu!';
        $messageType = 'success';
    } catch (Throwable $e) {
        $message = 'Tablo oluşturma hatası: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

$templates = [];
$categories = [];

if ($tableExists && !$needsUpgrade) {
    // Şablon durumu değiştirme
    if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
        $templateId = (int)$_GET['toggle'];
        $template = Database::fetch("SELECT status FROM email_templates WHERE id = ?", [$templateId]);
        if ($template) {
            $newStatus = $template['status'] === 'active' ? 'inactive' : 'active';
            Database::update('email_templates', ['status' => $newStatus], 'id = ?', [$templateId]);
            $message = 'Şablon durumu güncellendi.';
        }
    }

    // Kategorileri ve şablonları getir
    $templates = Database::fetchAll("SELECT * FROM email_templates ORDER BY category, display_name");

    // Kategorilere göre grupla
    $categories = [
        'client' => ['name' => 'Müşteri', 'icon' => '👤', 'templates' => []],
        'billing' => ['name' => 'Faturalama', 'icon' => '💰', 'templates' => []],
        'order' => ['name' => 'Sipariş', 'icon' => '🛒', 'templates' => []],
        'service' => ['name' => 'Hizmet', 'icon' => '🖥️', 'templates' => []],
        'support' => ['name' => 'Destek', 'icon' => '🎫', 'templates' => []],
        'domain' => ['name' => 'Domain', 'icon' => '🌐', 'templates' => []],
        'general' => ['name' => 'Genel', 'icon' => '📧', 'templates' => []],
    ];

    foreach ($templates as $template) {
        $cat = $template['category'];
        if (!isset($categories[$cat])) {
            $categories[$cat] = ['name' => ucfirst($cat), 'icon' => '📧', 'templates' => []];
        }
        $categories[$cat]['templates'][] = $template;
    }

    // Boş kategorileri kaldır
    $categories = array_filter($categories, fn($cat) => !empty($cat['templates']));
}

include 'includes/header.php';
?>

<style>
/* Hero Section */
.email-hero {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
    border-radius: 24px;
    padding: 40px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(102, 126, 234, 0.3);
}

.email-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
    border-radius: 50%;
}

.email-hero::after {
    content: '✉️';
    position: absolute;
    right: 40px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 120px;
    opacity: 0.15;
}

.email-hero-content {
    position: relative;
    z-index: 1;
}

.email-hero h1 {
    color: white;
    font-size: 32px;
    font-weight: 800;
    margin-bottom: 10px;
    text-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.email-hero p {
    color: rgba(255,255,255,0.9);
    font-size: 16px;
    margin-bottom: 25px;
    max-width: 500px;
}

.email-hero .hero-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.email-hero .btn-hero {
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.email-hero .btn-hero-primary {
    background: white;
    color: #667eea;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.email-hero .btn-hero-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.email-hero .btn-hero-outline {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 2px solid rgba(255,255,255,0.3);
}

.email-hero .btn-hero-outline:hover {
    background: rgba(255,255,255,0.25);
    border-color: rgba(255,255,255,0.5);
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    transition: all 0.3s;
    border: 1px solid transparent;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0,0,0,0.08);
    border-color: var(--primary);
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}

.stat-icon.purple { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.stat-icon.green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.stat-icon.blue { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); }
.stat-icon.orange { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }

.stat-info h3 {
    font-size: 28px;
    font-weight: 800;
    color: var(--dark);
    margin-bottom: 4px;
}

.stat-info p {
    font-size: 13px;
    color: var(--gray);
    font-weight: 500;
}

/* Categories Grid */
.templates-grid {
    display: grid;
    gap: 24px;
}

.category-section {
    background: white;
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    overflow: hidden;
    border: 1px solid rgba(0,0,0,0.04);
    transition: all 0.3s;
}

.category-section:hover {
    box-shadow: 0 8px 40px rgba(0,0,0,0.08);
}

.category-header {
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 24px 28px;
    background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    border-bottom: 1px solid #f1f5f9;
}

.category-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.category-icon.client { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.category-icon.billing { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.category-icon.order { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
.category-icon.service { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); }
.category-icon.support { background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); }
.category-icon.domain { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
.category-icon.general { background: linear-gradient(135deg, #64748b 0%, #475569 100%); }

.category-info h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 4px;
}

.category-info p {
    font-size: 13px;
    color: var(--gray);
}

.category-badge {
    margin-left: auto;
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
}

/* Templates Table */
.templates-table {
    width: 100%;
    border-collapse: collapse;
}

.templates-table th {
    text-align: left;
    padding: 16px 28px;
    font-size: 11px;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 1px;
    background: #fafbfc;
    border-bottom: 1px solid #f1f5f9;
}

.templates-table td {
    padding: 20px 28px;
    border-bottom: 1px solid #f8fafc;
    vertical-align: middle;
}

.templates-table tr:last-child td {
    border-bottom: none;
}

.templates-table tbody tr {
    transition: all 0.2s;
}

.templates-table tbody tr:hover {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.03) 0%, rgba(118, 75, 162, 0.03) 100%);
}

.template-name {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.template-name strong {
    color: var(--dark);
    font-size: 14px;
    font-weight: 600;
}

.template-name code {
    font-size: 11px;
    color: #667eea;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    padding: 4px 10px;
    border-radius: 6px;
    font-family: 'JetBrains Mono', 'SF Mono', monospace;
    display: inline-block;
    width: fit-content;
}

.template-subject {
    color: #64748b;
    font-size: 13px;
    max-width: 320px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 25px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.3px;
}

.status-badge.active {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #047857;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
}

.status-badge.inactive {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #dc2626;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.2);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-action {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 15px;
    text-decoration: none;
}

.btn-action.edit {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    color: #667eea;
}

.btn-action.edit:hover {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    transform: scale(1.1);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.btn-action.toggle {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(217, 119, 6, 0.1) 100%);
    color: #f59e0b;
}

.btn-action.toggle:hover {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    transform: scale(1.1);
    box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
}

.btn-action.preview {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
    color: #10b981;
}

.btn-action.preview:hover {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    transform: scale(1.1);
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
}

/* Alert */
.alert {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px 24px;
    border-radius: 16px;
    margin-bottom: 24px;
    font-size: 14px;
    font-weight: 500;
    animation: slideIn 0.4s ease;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert-success {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    border: 1px solid #6ee7b7;
    color: #047857;
    box-shadow: 0 4px 20px rgba(16, 185, 129, 0.15);
}

.alert-danger {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    border: 1px solid #fca5a5;
    color: #dc2626;
    box-shadow: 0 4px 20px rgba(239, 68, 68, 0.15);
}

.alert-info {
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
    border: 1px solid #7dd3fc;
    color: #0369a1;
    box-shadow: 0 4px 20px rgba(14, 165, 233, 0.15);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 80px 40px;
}

.empty-state .icon {
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    margin: 0 auto 24px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.empty-state h3 {
    font-size: 22px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 12px;
}

.empty-state p {
    color: #64748b;
    margin-bottom: 28px;
    font-size: 15px;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 28px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(102, 126, 234, 0.5);
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: 1fr; }
    .email-hero { padding: 30px; }
    .email-hero h1 { font-size: 24px; }
    .email-hero::after { display: none; }
    
    .templates-table thead { display: none; }
    .templates-table tbody { display: block; }
    .templates-table tr {
        display: block;
        padding: 20px;
        border-bottom: 1px solid #f1f5f9;
    }
    .templates-table td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border: none;
    }
    .templates-table td::before {
        content: attr(data-label);
        font-weight: 700;
        color: #94a3b8;
        font-size: 11px;
        text-transform: uppercase;
    }
}
</style>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <span><?= $messageType === 'success' ? '✅' : '❌' ?></span>
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Hero Section -->
<div class="email-hero">
    <div class="email-hero-content">
        <h1>📧 E-posta Şablonları</h1>
        <p>Tüm otomatik e-posta bildirimlerinizi tek bir yerden yönetin. Profesyonel şablonlar ile müşterilerinize etkileyici e-postalar gönderin.</p>
        <div class="hero-actions">
            <a href="settings.php" class="btn-hero btn-hero-primary">
                ⚙️ SMTP Ayarları
            </a>
            <a href="#templates" class="btn-hero btn-hero-outline">
                📋 Şablonları Görüntüle
            </a>
        </div>
    </div>
</div>

<?php if ($tableExists && !$needsUpgrade): ?>
<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon purple">📧</div>
        <div class="stat-info">
            <h3><?= count($templates) ?></h3>
            <p>Toplam Şablon</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">✅</div>
        <div class="stat-info">
            <h3><?= count(array_filter($templates, fn($t) => $t['status'] === 'active')) ?></h3>
            <p>Aktif Şablon</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">📁</div>
        <div class="stat-info">
            <h3><?= count($categories) ?></h3>
            <p>Kategori</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">🏷️</div>
        <div class="stat-info">
            <h3><?= count(array_unique(array_column($templates, 'category'))) ?></h3>
            <p>Farklı Tip</p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!$tableExists): ?>
    <div class="category-section">
        <div class="empty-state">
            <div class="icon">⚠️</div>
            <h3>E-posta Tabloları Bulunamadı</h3>
            <p>E-posta şablonları için gerekli veritabanı tabloları henüz oluşturulmamış.</p>
            <a href="?install=1" class="btn btn-primary">🔧 Tabloları Oluştur</a>
        </div>
    </div>
<?php elseif ($needsUpgrade): ?>
    <div class="category-section">
        <div class="empty-state">
            <div class="icon">🔄</div>
            <h3>Tablo Güncelleme Gerekli</h3>
            <p>E-posta şablonları tablosu güncel değil. Yeni sütunlar eklenmesi gerekiyor.</p>
            <a href="?install=1" class="btn btn-primary">🔧 Tabloyu Güncelle</a>
        </div>
    </div>
<?php elseif (empty($categories)): ?>
    <div class="category-section">
        <div class="empty-state">
            <div class="icon">📧</div>
            <h3>E-posta Şablonları Bulunamadı</h3>
            <p>Henüz e-posta şablonu oluşturulmamış.</p>
            <a href="?install=1" class="btn btn-primary">Varsayılan Şablonları Yükle</a>
        </div>
    </div>
<?php else: ?>
    <div class="templates-grid" id="templates">
        <?php foreach ($categories as $catKey => $category): ?>
            <div class="category-section">
                <div class="category-header">
                    <div class="category-icon <?= $catKey ?>"><?= $category['icon'] ?></div>
                    <div class="category-info">
                        <h3><?= htmlspecialchars($category['name']) ?> Şablonları</h3>
                        <p>Bu kategoride <?= count($category['templates']) ?> adet e-posta şablonu bulunuyor</p>
                    </div>
                    <span class="category-badge"><?= count($category['templates']) ?> Şablon</span>
                </div>
                
                <table class="templates-table">
                    <thead>
                        <tr>
                            <th>Şablon</th>
                            <th>Konu</th>
                            <th>Durum</th>
                            <th style="width: 140px;">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($category['templates'] as $template): ?>
                            <tr>
                                <td data-label="Şablon">
                                    <div class="template-name">
                                        <strong><?= htmlspecialchars($template['display_name']) ?></strong>
                                        <code><?= htmlspecialchars($template['name']) ?></code>
                                    </div>
                                </td>
                                <td data-label="Konu">
                                    <span class="template-subject" title="<?= htmlspecialchars($template['subject']) ?>">
                                        <?= htmlspecialchars($template['subject']) ?>
                                    </span>
                                </td>
                                <td data-label="Durum">
                                    <span class="status-badge <?= $template['status'] ?>">
                                        <?= $template['status'] === 'active' ? '✓ Aktif' : '✗ Pasif' ?>
                                    </span>
                                </td>
                                <td data-label="İşlemler">
                                    <div class="action-buttons">
                                        <a href="email-template-edit.php?id=<?= $template['id'] ?>" class="btn-action edit" title="Düzenle">
                                            ✏️
                                        </a>
                                        <button onclick="previewTemplate(<?= $template['id'] ?>)" class="btn-action preview" title="Önizle">
                                            👁️
                                        </button>
                                        <a href="?toggle=<?= $template['id'] ?>" class="btn-action toggle" title="<?= $template['status'] === 'active' ? 'Devre Dışı Bırak' : 'Aktif Et' ?>">
                                            ⚡
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Preview Modal -->
<div id="previewModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 24px; max-width: 900px; width: 90%; max-height: 90vh; overflow: hidden; box-shadow: 0 25px 80px rgba(0,0,0,0.3); animation: modalIn 0.3s ease;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 24px 28px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 700;">📧 E-posta Önizleme</h3>
            <button onclick="closePreview()" style="background: rgba(255,255,255,0.2); border: none; width: 36px; height: 36px; border-radius: 10px; font-size: 20px; cursor: pointer; color: white; transition: all 0.2s;">×</button>
        </div>
        <div id="previewContent" style="padding: 0; overflow-y: auto; max-height: calc(90vh - 80px); background: #f8fafc;">
            <iframe id="previewFrame" style="width: 100%; height: 600px; border: none;"></iframe>
        </div>
    </div>
</div>

<style>
@keyframes modalIn {
    from { opacity: 0; transform: scale(0.95) translateY(-20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
</style>

<script>
function previewTemplate(id) {
    const modal = document.getElementById('previewModal');
    const frame = document.getElementById('previewFrame');
    
    frame.src = 'ajax/preview-template.php?id=' + id;
    modal.style.display = 'flex';
}

function closePreview() {
    document.getElementById('previewModal').style.display = 'none';
    document.getElementById('previewFrame').src = '';
}

// ESC tuşu ile kapatma
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePreview();
});

// Modal dışına tıklama ile kapatma
document.getElementById('previewModal').addEventListener('click', function(e) {
    if (e.target === this) closePreview();
});
</script>

<?php include 'includes/footer.php'; ?>

