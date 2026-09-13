-- WHMVM E-posta Sistemi Tabloları
-- ================================

-- E-posta Şablonları Tablosu
CREATE TABLE IF NOT EXISTS `email_templates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Şablon benzersiz adı',
    `display_name` VARCHAR(255) NOT NULL COMMENT 'Görünen ad',
    `category` VARCHAR(50) NOT NULL DEFAULT 'general' COMMENT 'Kategori',
    `subject` VARCHAR(255) NOT NULL COMMENT 'E-posta konusu',
    `body` TEXT NOT NULL COMMENT 'E-posta içeriği (HTML)',
    `variables` TEXT COMMENT 'Kullanılabilir değişkenler (JSON)',
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_category` (`category`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- E-posta Logları Tablosu
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
    `related_type` VARCHAR(50) DEFAULT NULL COMMENT 'invoice, ticket, service, order',
    `related_id` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_to_email` (`to_email`),
    INDEX `idx_status` (`status`),
    INDEX `idx_template` (`template_name`),
    INDEX `idx_client` (`client_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================
-- VARSAYILAN E-POSTA ŞABLONLARI
-- ================================

-- Müşteri Şablonları
INSERT INTO `email_templates` (`name`, `display_name`, `category`, `subject`, `body`, `variables`) VALUES

-- Hoş Geldiniz
('welcome_email', 'Hoş Geldiniz E-postası', 'client', 'Hoş Geldiniz {client_name}!', 
'<h2 style="color: #1e293b; margin: 0 0 20px 0;">Aramıza Hoş Geldiniz! 🎉</h2>
<p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
    Sayın <strong>{client_name}</strong>,
</p>
<p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
    {site_name} ailesine katıldığınız için teşekkür ederiz! Hesabınız başarıyla oluşturulmuştur.
</p>
<p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
    Müşteri panelinize giriş yaparak hizmetlerimizi keşfedebilir, siparişlerinizi takip edebilir ve destek talebi oluşturabilirsiniz.
</p>
<table role="presentation" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border-radius: 8px;">
            <a href="{site_url}/client/" style="display: inline-block; padding: 14px 30px; color: white; text-decoration: none; font-weight: 600;">
                Panele Git →
            </a>
        </td>
    </tr>
</table>
<p style="color: #64748b; font-size: 14px; margin: 25px 0 0 0;">
    Herhangi bir sorunuz varsa destek ekibimiz size yardımcı olmaktan mutluluk duyacaktır.
</p>',
'["client_name", "client_email", "site_name", "site_url"]'),

-- Şifre Sıfırlama
('password_reset', 'Şifre Sıfırlama', 'client', 'Şifre Sıfırlama Talebi', 
'<h2 style="color: #1e293b; margin: 0 0 20px 0;">Şifre Sıfırlama 🔐</h2>
<p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
    Sayın <strong>{client_name}</strong>,
</p>
<p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
    Hesabınız için bir şifre sıfırlama talebi aldık. Şifrenizi sıfırlamak için aşağıdaki butona tıklayın.
</p>
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 25px 0;">
    <tr>
        <td style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border-radius: 8px;">
            <a href="{reset_link}" style="display: inline-block; padding: 14px 30px; color: white; text-decoration: none; font-weight: 600;">
                Şifremi Sıfırla →
            </a>
        </td>
    </tr>
</table>
<p style="color: #ef4444; font-size: 14px; margin: 0 0 15px 0;">
    ⚠️ Bu link 1 saat içinde geçerliliğini yitirecektir.
</p>
<p style="color: #64748b; font-size: 14px; margin: 0;">
    Bu talebi siz yapmadıysanız, bu e-postayı görmezden gelebilirsiniz.
</p>',
'["client_name", "client_email", "reset_link", "site_name"]'),

-- Fatura Oluşturuldu
('invoice_created', 'Yeni Fatura', 'billing', '[{site_name}] Yeni Fatura #{invoice_id}', 
'<h2 style="color: #1e293b; margin: 0 0 20px 0;">Yeni Fatura Oluşturuldu 📄</h2>
<p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
    Sayın <strong>{client_name}</strong>,
</p>
<p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
    Hesabınıza yeni bir fatura oluşturulmuştur.
</p>
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #f8fafc; border-radius: 8px; margin-bottom: 20px;">
    <tr>
        <td style="padding: 20px;">
            <table width="100%">
                <tr>
                    <td style="color: #64748b; padding: 8px 0;">Fatura No:</td>
                    <td style="color: #1e293b; font-weight: 600; text-align: right;">#{invoice_id}</td>
                </tr>
                <tr>
                    <td style="color: #64748b; padding: 8px 0;">Tutar:</td>
                    <td style="color: #1e293b; font-weight: 600; text-align: right;">{invoice_total} ₺</td>
                </tr>
                <tr>
                    <td style="color: #64748b; padding: 8px 0;">Son Ödeme Tarihi:</td>
                    <td style="color: #ef4444; font-weight: 600; text-align: right;">{due_date}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
<table role="presentation" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 8px;">
            <a href="{site_url}/client/invoice-view.php?id={invoice_id}" style="display: inline-block; padding: 14px 30px; color: white; text-decoration: none; font-weight: 600;">
                Faturayı Görüntüle & Öde →
            </a>
        </td>
    </tr>
</table>',
'["client_name", "invoice_id", "invoice_total", "due_date", "site_name", "site_url"]'),

-- Fatura Ödeme Hatırlatması
('invoice_reminder', 'Fatura Hatırlatması', 'billing', '[{site_name}] Ödeme Hatırlatması - Fatura #{invoice_id}', 
'<h2 style="color: #1e293b; margin: 0 0 20px 0;">Ödeme Hatırlatması ⏰</h2>
<p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
    Sayın <strong>{client_name}</strong>,
</p>
<p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
    <strong>#{invoice_id}</strong> numaralı faturanızın son ödeme tarihi yaklaşıyor.
</p>
<div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; border-radius: 0 8px 8px 0; margin-bottom: 20px;">
    <p style="margin: 0; color: #92400e;">
        <strong>⚠️ Son Ödeme Tarihi:</strong> {due_date}<br>
        <strong>Toplam Tutar:</strong> {invoice_total} ₺
    </p>
</div>
<p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
    Hizmetlerinizin kesintiye uğramaması için lütfen ödemenizi zamanında yapınız.
</p>
<table role="presentation" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-radius: 8px;">
            <a href="{site_url}/client/invoice-view.php?id={invoice_id}" style="display: inline-block; padding: 14px 30px; color: white; text-decoration: none; font-weight: 600;">
                Şimdi Öde →
            </a>
        </td>
    </tr>
</table>',
'["client_name", "invoice_id", "invoice_total", "due_date", "days_until_due", "site_name", "site_url"]'),

-- Fatura Ödendi
('invoice_paid', 'Fatura Ödendi', 'billing', '[{site_name}] Ödeme Onayı - Fatura #{invoice_id}', 
'<h2 style="color: #1e293b; margin: 0 0 20px 0;">Ödemeniz Alındı ✅</h2>
<p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
    Sayın <strong>{client_name}</strong>,
</p>
<p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
    <strong>#{invoice_id}</strong> numaralı faturanız için ödemeniz başarıyla alınmıştır.
</p>
<div style="background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; border-radius: 0 8px 8px 0; margin-bottom: 20px;">
    <p style="margin: 0; color: #065f46;">
        <strong>✅ Ödenen Tutar:</strong> {payment_amount} ₺<br>
        <strong>Ödeme Tarihi:</strong> {payment_date}<br>
        <strong>İşlem No:</strong> {transaction_id}
    </p>
</div>
<p style="color: #64748b; font-size: 14px; margin: 0;">
    Bizi tercih ettiğiniz için teşekkür ederiz!
</p>',
'["client_name", "invoice_id", "payment_amount", "payment_date", "transaction_id", "site_name", "site_url"]'),

-- Fatura İptal
('invoice_overdue', 'Vadesi Geçmiş Fatura', 'billing', '[{site_name}] Acil: Vadesi Geçmiş Fatura #{invoice_id}', 
'<h2 style="color: #ef4444; margin: 0 0 20px 0;">Vadesi Geçmiş Fatura! ⚠️</h2>
<p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
    Sayın <strong>{client_name}</strong>,
</p>
<p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
    <strong>#{invoice_id}</strong> numaralı faturanızın vadesi geçmiştir.
</p>
<div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; border-radius: 0 8px 8px 0; margin-bottom: 20px;">
    <p style="margin: 0; color: #991b1b;">
        <strong>🚨 Fatura Tutarı:</strong> {invoice_total} ₺<br>
        <strong>Vade Tarihi:</strong> {due_date}<br>
        <strong>Gecikme:</strong> {days_overdue} gün
    </p>
</div>
<p style="color: #ef4444; line-height: 1.6; margin: 0 0 20px 0;">
    <strong>Dikkat:</strong> Ödeme yapılmaması durumunda hizmetleriniz askıya alınabilir.
</p>
<table role="presentation" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border-radius: 8px;">
            <a href="{site_url}/client/invoice-view.php?id={invoice_id}" style="display: inline-block; padding: 14px 30px; color: white; text-decoration: none; font-weight: 600;">
                Hemen Öde →
            </a>
        </td>
    </tr>
</table>',
'["client_name", "invoice_id", "invoice_total", "due_date", "days_overdue", "site_name", "site_url"]'),

-- Sipariş Alındı
('order_received', 'Sipariş Alındı', 'order', '[{site_name}] Siparişiniz Alındı #{order_id}', 
'<h2 style="color: #1e293b; margin: 0 0 20px 0;">Siparişiniz Alındı! 🛒</h2>
<p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
    Sayın <strong>{client_name}</strong>,
</p>
<p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
    Siparişiniz başarıyla alınmıştır ve işleme alınacaktır.
</p>
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #f8fafc; border-radius: 8px; margin-bottom: 20px;">
    <tr>
        <td style="padding: 20px;">
            <table width="100%">
                <tr>
                    <td style="color: #64748b; padding: 8px 0;">Sipariş No:</td>
                    <td style="color: #1e293b; font-weight: 600; text-align: right;">#{order_id}</td>
                </tr>
                <tr>
                    <td style="color: #64748b; padding: 8px 0;">Ürün:</td>
                    <td style="color: #1e293b; font-weight: 600; text-align: right;">{product_name}</td>
                </tr>
                <tr>
                    <td style="color: #64748b; padding: 8px 0;">Tutar:</td>
                    <td style="color: #1e293b; font-weight: 600; text-align: right;">{order_total} ₺</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
<p style="color: #64748b; font-size: 14px; margin: 0;">
    Ödeme onaylandıktan sonra hizmetiniz aktif edilecektir.
</p>',
'["client_name", "order_id", "product_name", "order_total", "site_name", "site_url"]'),

-- Sipariş Onaylandı
('order_confirmed', 'Sipariş Onaylandı', 'order', '[{site_name}] Siparişiniz Onaylandı #{order_id}', 
'<h2 style="color: #1e293b; margin: 0 0 20px 0;">Siparişiniz Onaylandı! ✅</h2>
<p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
    Sayın <strong>{client_name}</strong>,
</p>
<p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
    <strong>#{order_id}</strong> numaralı siparişiniz onaylanmış ve hizmetiniz aktif edilmiştir.
</p>
<div style="background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; border-radius: 0 8px 8px 0; margin-bottom: 20px;">
    <p style="margin: 0; color: #065f46;">
        <strong>✅ Hizmet:</strong> {product_name}<br>
        <strong>Durum:</strong> Aktif
    </p>
</div>
<table role="presentation" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border-radius: 8px;">
            <a href="{site_url}/client/services.php" style="display: inline-block; padding: 14px 30px; color: white; text-decoration: none; font-weight: 600;">
                Hizmetlerimi Görüntüle →
            </a>
        </td>
    </tr>
</table>',
'["client_name", "order_id", "product_name", "site_name", "site_url"]'),

-- Hizmet Aktif
('service_activated', 'Hizmet Aktif Edildi', 'service', '[{site_name}] Hizmetiniz Aktif Edildi', 
'<h2 style="color: #1e293b; margin: 0 0 20px 0;">Hizmetiniz Aktif! 🚀</h2>
<p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
    Sayın <strong>{client_name}</strong>,
</p>
<p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
    Hizmetiniz başarıyla aktif edilmiştir ve kullanıma hazırdır.
</p>
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #f8fafc; border-radius: 8px; margin-bottom: 20px;">
    <tr>
        <td style="padding: 20px;">
            <table width="100%">
                <tr>
                    <td style="color: #64748b; padding: 8px 0;">Hizmet:</td>
                    <td style="color: #1e293b; font-weight: 600; text-align: right;">{product_name}</td>
                </tr>
                <tr>
                    <td style="color: #64748b; padding: 8px 0;">Domain:</td>
                    <td style="color: #1e293b; font-weight: 600; text-align: right;">{domain}</td>
                </tr>
                <tr>
                    <td style="color: #64748b; padding: 8px 0;">IP Adresi:</td>
                    <td style="color: #1e293b; font-weight: 600; text-align: right;">{ip_address}</td>
                </tr>
                <tr>
                    <td style="color: #64748b; padding: 8px 0;">Kullanıcı Adı:</td>
                    <td style="color: #1e293b; font-weight: 600; text-align: right;">{username}</td>
                </tr>
                <tr>
                    <td style="color: #64748b; padding: 8px 0;">Şifre:</td>
                    <td style="color: #1e293b; font-weight: 600; text-align: right;">{password}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
<p style="color: #ef4444; font-size: 14px; margin: 0 0 20px 0;">
    ⚠️ Güvenliğiniz için şifrenizi ilk girişte değiştirmenizi öneririz.
</p>
<table role="presentation" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border-radius: 8px;">
            <a href="{site_url}/client/services.php" style="display: inline-block; padding: 14px 30px; color: white; text-decoration: none; font-weight: 600;">
                Hizmeti Yönet →
            </a>
        </td>
    </tr>
</table>',
'["client_name", "product_name", "domain", "ip_address", "username", "password", "site_name", "site_url"]'),

-- Hizmet Askıya Alındı
('service_suspended', 'Hizmet Askıya Alındı', 'service', '[{site_name}] Hizmetiniz Askıya Alındı', 
'<h2 style="color: #f59e0b; margin: 0 0 20px 0;">Hizmetiniz Askıya Alındı ⏸️</h2>
<p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
    Sayın <strong>{client_name}</strong>,
</p>
<p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
    <strong>{product_name}</strong> hizmetiniz ödenmemiş fatura nedeniyle askıya alınmıştır.
</p>
<div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; border-radius: 0 8px 8px 0; margin-bottom: 20px;">
    <p style="margin: 0; color: #92400e;">
        <strong>⚠️ Askıya Alma Nedeni:</strong> {suspend_reason}<br>
        <strong>Askıya Alma Tarihi:</strong> {suspend_date}
    </p>
</div>
<p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
    Hizmetinizi yeniden aktif etmek için lütfen ödenmemiş faturalarınızı ödeyin.
</p>
<table role="presentation" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-radius: 8px;">
            <a href="{site_url}/client/invoices.php" style="display: inline-block; padding: 14px 30px; color: white; text-decoration: none; font-weight: 600;">
                Faturalarımı Görüntüle →
            </a>
        </td>
    </tr>
</table>',
'["client_name", "product_name", "suspend_reason", "suspend_date", "site_name", "site_url"]'),

-- Hizmet Yeniden Aktif
('service_unsuspended', 'Hizmet Yeniden Aktif', 'service', '[{site_name}] Hizmetiniz Yeniden Aktif Edildi', 
'<h2 style="color: #10b981; margin: 0 0 20px 0;">Hizmetiniz Yeniden Aktif! ✅</h2>
<p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
    Sayın <strong>{client_name}</strong>,
</p>
<p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
    <strong>{product_name}</strong> hizmetiniz yeniden aktif edilmiştir.
</p>
<div style="background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; border-radius: 0 8px 8px 0; margin-bottom: 20px;">
    <p style="margin: 0; color: #065f46;">
        ✅ Hizmetiniz artık aktif ve kullanılabilir durumda.
    </p>
</div>
<p style="color: #64748b; font-size: 14px; margin: 0;">
    Bizi tercih ettiğiniz için teşekkür ederiz!
</p>',
'["client_name", "product_name", "site_name", "site_url"]'),

-- Hizmet Sonlandırıldı
('service_terminated', 'Hizmet Sonlandırıldı', 'service', '[{site_name}] Hizmetiniz Sonlandırıldı', 
'<h2 style="color: #ef4444; margin: 0 0 20px 0;">Hizmetiniz Sonlandırıldı 🛑</h2>
<p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
    Sayın <strong>{client_name}</strong>,
</p>
<p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
    <strong>{product_name}</strong> hizmetiniz sonlandırılmıştır.
</p>
<div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; border-radius: 0 8px 8px 0; margin-bottom: 20px;">
    <p style="margin: 0; color: #991b1b;">
        <strong>🛑 Sonlandırma Nedeni:</strong> {termination_reason}<br>
        <strong>Sonlandırma Tarihi:</strong> {termination_date}
    </p>
</div>
<p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
    Yeni bir hizmet almak için web sitemizi ziyaret edebilirsiniz.
</p>
<table role="presentation" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border-radius: 8px;">
            <a href="{site_url}" style="display: inline-block; padding: 14px 30px; color: white; text-decoration: none; font-weight: 600;">
                Hizmetlerimizi İncele →
            </a>
        </td>
    </tr>
</table>',
'["client_name", "product_name", "termination_reason", "termination_date", "site_name", "site_url"]'),

-- Destek Talebi Açıldı
('ticket_opened', 'Destek Talebi Açıldı', 'support', '[{site_name}] Destek Talebi #{ticket_id} Açıldı', 
'<h2 style="color: #1e293b; margin: 0 0 20px 0;">Destek Talebiniz Oluşturuldu 🎫</h2>
<p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
    Sayın <strong>{client_name}</strong>,
</p>
<p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
    Destek talebiniz başarıyla oluşturulmuştur.
</p>
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #f8fafc; border-radius: 8px; margin-bottom: 20px;">
    <tr>
        <td style="padding: 20px;">
            <table width="100%">
                <tr>
                    <td style="color: #64748b; padding: 8px 0;">Ticket No:</td>
                    <td style="color: #1e293b; font-weight: 600; text-align: right;">#{ticket_id}</td>
                </tr>
                <tr>
                    <td style="color: #64748b; padding: 8px 0;">Konu:</td>
                    <td style="color: #1e293b; font-weight: 600; text-align: right;">{ticket_subject}</td>
                </tr>
                <tr>
                    <td style="color: #64748b; padding: 8px 0;">Departman:</td>
                    <td style="color: #1e293b; font-weight: 600; text-align: right;">{ticket_department}</td>
                </tr>
                <tr>
                    <td style="color: #64748b; padding: 8px 0;">Öncelik:</td>
                    <td style="color: #1e293b; font-weight: 600; text-align: right;">{ticket_priority}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
<p style="color: #64748b; font-size: 14px; margin: 0;">
    Destek ekibimiz en kısa sürede talebinize yanıt verecektir.
</p>',
'["client_name", "ticket_id", "ticket_subject", "ticket_department", "ticket_priority", "site_name", "site_url"]'),

-- Destek Talebi Yanıtlandı
('ticket_reply', 'Destek Talebi Yanıtı', 'support', '[{site_name}] Destek Talebi #{ticket_id} Yanıtlandı', 
'<h2 style="color: #1e293b; margin: 0 0 20px 0;">Talebinize Yanıt Verildi 💬</h2>
<p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
    Sayın <strong>{client_name}</strong>,
</p>
<p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
    <strong>#{ticket_id}</strong> numaralı destek talebinize yeni bir yanıt eklenmiştir.
</p>
<div style="background: #f8fafc; border-left: 4px solid #6366f1; padding: 15px; border-radius: 0 8px 8px 0; margin-bottom: 20px;">
    <p style="margin: 0 0 10px 0; color: #64748b; font-size: 12px;">
        <strong>{reply_staff}</strong> yazdı:
    </p>
    <p style="margin: 0; color: #1e293b; line-height: 1.6;">
        {reply_message}
    </p>
</div>
<table role="presentation" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border-radius: 8px;">
            <a href="{site_url}/client/ticket-view.php?id={ticket_id}" style="display: inline-block; padding: 14px 30px; color: white; text-decoration: none; font-weight: 600;">
                Talebi Görüntüle →
            </a>
        </td>
    </tr>
</table>',
'["client_name", "ticket_id", "ticket_subject", "reply_staff", "reply_message", "site_name", "site_url"]'),

-- Destek Talebi Kapatıldı
('ticket_closed', 'Destek Talebi Kapatıldı', 'support', '[{site_name}] Destek Talebi #{ticket_id} Kapatıldı', 
'<h2 style="color: #1e293b; margin: 0 0 20px 0;">Talebiniz Kapatıldı ✓</h2>
<p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
    Sayın <strong>{client_name}</strong>,
</p>
<p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
    <strong>#{ticket_id}</strong> - <strong>{ticket_subject}</strong> konulu destek talebiniz kapatılmıştır.
</p>
<div style="background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; border-radius: 0 8px 8px 0; margin-bottom: 20px;">
    <p style="margin: 0; color: #065f46;">
        ✅ Talebiniz çözümlenmiştir. Yardımcı olabildiysek ne mutlu bize!
    </p>
</div>
<p style="color: #64748b; font-size: 14px; margin: 0;">
    Başka bir konuda yardıma ihtiyacınız olursa yeni bir destek talebi oluşturabilirsiniz.
</p>',
'["client_name", "ticket_id", "ticket_subject", "site_name", "site_url"]'),

-- Domain Yenileme Hatırlatması
('domain_expiry', 'Domain Yenileme Hatırlatması', 'domain', '[{site_name}] Domain Yenileme: {domain}', 
'<h2 style="color: #1e293b; margin: 0 0 20px 0;">Domain Yenileme Hatırlatması 🌐</h2>
<p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
    Sayın <strong>{client_name}</strong>,
</p>
<p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
    <strong>{domain}</strong> alan adınızın süresi dolmak üzere.
</p>
<div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; border-radius: 0 8px 8px 0; margin-bottom: 20px;">
    <p style="margin: 0; color: #92400e;">
        <strong>⏰ Son Kullanma Tarihi:</strong> {expiry_date}<br>
        <strong>Kalan Süre:</strong> {days_until_expiry} gün
    </p>
</div>
<p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
    Alan adınızın başkaları tarafından alınmaması için lütfen zamanında yenileyin.
</p>
<table role="presentation" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border-radius: 8px;">
            <a href="{site_url}/client/domains.php" style="display: inline-block; padding: 14px 30px; color: white; text-decoration: none; font-weight: 600;">
                Şimdi Yenile →
            </a>
        </td>
    </tr>
</table>',
'["client_name", "domain", "expiry_date", "days_until_expiry", "site_name", "site_url"]');

