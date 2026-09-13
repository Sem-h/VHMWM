<?php
/**
 * WHMVM - Müşteri Satış Ortaklığı Sayfası
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Affiliate.php';
session_name(SESSION_NAME); session_start();

if (!isset($_SESSION['client_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Satış Ortaklığı';
$pageIcon = 'fas fa-handshake';
$currentPage = 'affiliate';
$clientId = $_SESSION['client_id'];

// Tabloları kontrol et ve oluştur
function ensureAffiliateTables() {
    $db = Database::getInstance();
    
    // affiliates tablosu var mı?
    try {
        $db->query("SELECT 1 FROM affiliates LIMIT 1");
        return; // Tablo var, işlem gerekmiyor
    } catch (Throwable $e) {
        // Tablo yok, oluştur
    }
    
    // Tabloları oluştur
    $tables = [
        "CREATE TABLE IF NOT EXISTS affiliates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id INT UNSIGNED NOT NULL UNIQUE,
            affiliate_code VARCHAR(32) NOT NULL UNIQUE,
            status ENUM('pending', 'active', 'suspended', 'rejected') DEFAULT 'pending',
            commission_rate DECIMAL(5,2) DEFAULT 10.00,
            commission_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
            payment_method VARCHAR(50) DEFAULT 'bank_transfer',
            payment_details TEXT,
            total_visits INT UNSIGNED DEFAULT 0,
            total_signups INT UNSIGNED DEFAULT 0,
            total_orders INT UNSIGNED DEFAULT 0,
            total_earnings DECIMAL(15,2) DEFAULT 0.00,
            total_withdrawn DECIMAL(15,2) DEFAULT 0.00,
            balance DECIMAL(15,2) DEFAULT 0.00,
            min_withdrawal DECIMAL(10,2) DEFAULT 100.00,
            approved_at DATETIME NULL,
            approved_by INT UNSIGNED NULL,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_client (client_id),
            INDEX idx_code (affiliate_code),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS affiliate_visits (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            affiliate_id INT UNSIGNED NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            referrer_url TEXT,
            landing_page VARCHAR(500),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_affiliate (affiliate_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS affiliate_referrals (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            affiliate_id INT UNSIGNED NOT NULL,
            referred_client_id INT UNSIGNED NOT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            approved_at DATETIME NULL,
            INDEX idx_affiliate (affiliate_id),
            INDEX idx_referred (referred_client_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS affiliate_commissions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            affiliate_id INT UNSIGNED NOT NULL,
            referral_id INT UNSIGNED NULL,
            order_id INT UNSIGNED NULL,
            invoice_id INT UNSIGNED NULL,
            amount DECIMAL(15,2) NOT NULL,
            commission_rate DECIMAL(5,2) NOT NULL,
            commission_amount DECIMAL(15,2) NOT NULL,
            status ENUM('pending', 'approved', 'paid', 'cancelled') DEFAULT 'pending',
            description VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            approved_at DATETIME NULL,
            paid_at DATETIME NULL,
            INDEX idx_affiliate (affiliate_id),
            INDEX idx_referral (referral_id),
            INDEX idx_order (order_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS affiliate_withdrawals (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            affiliate_id INT UNSIGNED NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            payment_details TEXT,
            status ENUM('pending', 'processing', 'completed', 'rejected') DEFAULT 'pending',
            admin_notes TEXT,
            processed_by INT UNSIGNED NULL,
            processed_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_affiliate (affiliate_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    foreach ($tables as $sql) {
        try {
            $db->exec($sql);
        } catch (Throwable $ex) {
            // Devam et
        }
    }
    
    // Varsayılan ayarları ekle
    $settings = [
        ['affiliate_enabled', '1'],
        ['affiliate_auto_approve', '0'],
        ['affiliate_default_commission', '10'],
        ['affiliate_commission_type', 'percentage'],
        ['affiliate_min_withdrawal', '100'],
        ['affiliate_cookie_days', '30'],
        ['affiliate_require_approval', '1']
    ];
    
    foreach ($settings as $setting) {
        try {
            $db->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)")
               ->execute($setting);
        } catch (Throwable $ex) {
            // Devam et
        }
    }
}
ensureAffiliateTables();

// Affiliate sistemi aktif mi?
$affiliateEnabled = Settings::get('affiliate_enabled', '1') === '1';
if (!$affiliateEnabled) {
    $pageTitle = 'Satış Ortaklığı - Kapalı';
}

// Mevcut affiliate bilgilerini al
$affiliate = Affiliate::getByClientId($clientId);
$stats = $affiliate ? Affiliate::getStats($affiliate['id']) : [];

$message = '';
$messageType = 'success';

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $affiliateEnabled) {
    // Affiliate başvurusu
    if (isset($_POST['apply_affiliate']) && !$affiliate) {
        $paymentMethod = $_POST['payment_method'] ?? 'bank_transfer';
        $paymentDetails = trim($_POST['payment_details'] ?? '');
        
        if (empty($paymentDetails)) {
            $message = 'Lütfen ödeme bilgilerinizi girin.';
            $messageType = 'error';
        } else {
            $affiliateId = Affiliate::register($clientId, [
                'payment_method' => $paymentMethod,
                'payment_details' => $paymentDetails
            ]);
            
            if ($affiliateId) {
                $affiliate = Affiliate::getByClientId($clientId);
                $stats = Affiliate::getStats($affiliate['id']);
                $message = Settings::get('affiliate_auto_approve', '0') === '1' 
                    ? 'Satış ortaklığı hesabınız başarıyla oluşturuldu!' 
                    : 'Başvurunuz alındı. Onay sonrası hesabınız aktifleştirilecektir.';
            } else {
                $message = 'Başvuru sırasında bir hata oluştu.';
                $messageType = 'error';
            }
        }
    }
    
    // Ödeme bilgilerini güncelle
    if (isset($_POST['update_payment']) && $affiliate) {
        $paymentMethod = $_POST['payment_method'] ?? 'bank_transfer';
        $paymentDetails = trim($_POST['payment_details'] ?? '');
        
        Database::query(
            "UPDATE affiliates SET payment_method = ?, payment_details = ? WHERE id = ?",
            [$paymentMethod, $paymentDetails, $affiliate['id']]
        );
        
        $affiliate = Affiliate::getByClientId($clientId);
        $message = 'Ödeme bilgileriniz güncellendi.';
    }
    
    // Çekim talebi
    if (isset($_POST['request_withdrawal']) && $affiliate && $affiliate['status'] === 'active') {
        $amount = (float)($_POST['withdrawal_amount'] ?? 0);
        $result = Affiliate::requestWithdrawal($affiliate['id'], $amount);
        
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        
        if ($result['success']) {
            $affiliate = Affiliate::getByClientId($clientId);
            $stats = Affiliate::getStats($affiliate['id']);
        }
    }
}

// Komisyon geçmişi
$commissions = [];
$withdrawals = [];
if ($affiliate) {
    $commissions = Database::fetchAll(
        "SELECT * FROM affiliate_commissions WHERE affiliate_id = ? ORDER BY created_at DESC LIMIT 10",
        [$affiliate['id']]
    );
    $withdrawals = Database::fetchAll(
        "SELECT * FROM affiliate_withdrawals WHERE affiliate_id = ? ORDER BY created_at DESC LIMIT 10",
        [$affiliate['id']]
    );
}

// Referanslar
$referrals = [];
if ($affiliate) {
    $referrals = Database::fetchAll(
        "SELECT ar.*, c.first_name, c.last_name, c.email, c.created_at as client_created
         FROM affiliate_referrals ar
         JOIN clients c ON ar.referred_client_id = c.id
         WHERE ar.affiliate_id = ?
         ORDER BY ar.created_at DESC LIMIT 20",
        [$affiliate['id']]
    );
}

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* Affiliate Sayfası */
.affiliate-page {
    animation: fadeIn 0.4s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Alert */
.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-success {
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #6ee7b7;
}

.alert-error {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
}

.alert-warning {
    background: rgba(245, 158, 11, 0.15);
    border: 1px solid rgba(245, 158, 11, 0.3);
    color: #fcd34d;
}

/* Hero Section */
.affiliate-hero {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(139, 92, 246, 0.15) 50%, rgba(236, 72, 153, 0.1) 100%);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}

.affiliate-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(139, 92, 246, 0.1) 0%, transparent 70%);
    animation: float 15s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translate(0, 0); }
    50% { transform: translate(-30px, 30px); }
}

.affiliate-hero-content {
    position: relative;
    z-index: 1;
}

.affiliate-hero h1 {
    font-size: 32px;
    font-weight: 800;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 16px;
}

.affiliate-hero h1 i {
    color: var(--primary-light);
}

.affiliate-hero p {
    font-size: 16px;
    color: var(--text-muted);
    max-width: 600px;
    line-height: 1.7;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 600;
    margin-top: 20px;
}

.status-badge.active {
    background: rgba(16, 185, 129, 0.15);
    color: #6ee7b7;
}

.status-badge.pending {
    background: rgba(245, 158, 11, 0.15);
    color: #fcd34d;
}

.status-badge.suspended {
    background: rgba(239, 68, 68, 0.15);
    color: #fca5a5;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--card-bg);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    border-color: rgba(99, 102, 241, 0.3);
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-icon.purple { background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(99, 102, 241, 0.2)); color: #a78bfa; }
.stat-icon.blue { background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(37, 99, 235, 0.2)); color: #60a5fa; }
.stat-icon.green { background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2)); color: #6ee7b7; }
.stat-icon.orange { background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(217, 119, 6, 0.2)); color: #fcd34d; }
.stat-icon.pink { background: linear-gradient(135deg, rgba(236, 72, 153, 0.2), rgba(219, 39, 119, 0.2)); color: #f472b6; }

.stat-info h4 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 4px;
}

.stat-info p {
    font-size: 13px;
    color: var(--text-muted);
}

/* Referral Link Box */
.referral-link-box {
    background: var(--card-bg);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 30px;
}

.referral-link-box h3 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.referral-link-input {
    display: flex;
    gap: 12px;
}

.referral-link-input input {
    flex: 1;
    padding: 14px 18px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    color: var(--primary-light);
    font-size: 14px;
    font-family: monospace;
}

.btn-copy {
    padding: 14px 24px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border: none;
    border-radius: 10px;
    color: white;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.btn-copy:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
}

.btn-copy.copied {
    background: linear-gradient(135deg, var(--success), #059669);
}

/* Content Grid */
.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
}

@media (max-width: 1024px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}

/* Content Card */
.content-card {
    background: var(--card-bg);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    overflow: hidden;
}

.card-header {
    padding: 20px 24px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.card-header h3 {
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-body {
    padding: 24px;
}

/* Table */
.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    text-align: left;
    padding: 12px 16px;
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.data-table td {
    padding: 14px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    font-size: 14px;
}

.data-table tr:last-child td {
    border-bottom: none;
}

.data-table tr:hover td {
    background: rgba(255,255,255,0.02);
}

/* Apply Form */
.apply-card {
    max-width: 600px;
    margin: 0 auto;
}

.form-group {
    margin-bottom: 24px;
}

.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 10px;
    color: var(--text-secondary);
}

.form-control {
    width: 100%;
    padding: 14px 18px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    color: #fff;
    font-size: 14px;
    transition: all 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

textarea.form-control {
    min-height: 120px;
    resize: vertical;
}

select.form-control {
    cursor: pointer;
}

select.form-control option {
    background: #1e293b;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 14px 28px;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.3s;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #059669);
    color: white;
}

.btn-outline {
    background: transparent;
    border: 1px solid rgba(255,255,255,0.2);
    color: #fff;
}

/* Features */
.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.feature-item {
    display: flex;
    gap: 16px;
    padding: 20px;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 12px;
}

.feature-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: var(--primary-light);
    flex-shrink: 0;
}

.feature-text h4 {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 4px;
}

.feature-text p {
    font-size: 13px;
    color: var(--text-muted);
    line-height: 1.5;
}

/* Disabled State */
.disabled-state {
    text-align: center;
    padding: 80px 40px;
}

.disabled-state i {
    font-size: 64px;
    color: var(--text-muted);
    margin-bottom: 24px;
}

.disabled-state h2 {
    font-size: 24px;
    margin-bottom: 12px;
}

.disabled-state p {
    color: var(--text-muted);
    font-size: 16px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 40px;
    margin-bottom: 16px;
    opacity: 0.5;
}

/* Withdrawal Form */
.withdrawal-form {
    display: flex;
    gap: 12px;
    margin-top: 16px;
}

.withdrawal-form input {
    flex: 1;
    padding: 12px 16px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    color: #fff;
    font-size: 14px;
}

.withdrawal-form button {
    padding: 12px 20px;
    background: var(--success);
    border: none;
    border-radius: 8px;
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.withdrawal-form button:hover {
    background: #059669;
}
</style>

<div class="affiliate-page">
    
    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    
    <?php if (!$affiliateEnabled): ?>
        <!-- Sistem Kapalı -->
        <div class="disabled-state">
            <i class="fas fa-lock"></i>
            <h2>Satış Ortaklığı Sistemi Kapalı</h2>
            <p>Satış ortaklığı sistemi şu anda aktif değil. Lütfen daha sonra tekrar deneyin.</p>
        </div>
        
    <?php elseif (!$affiliate): ?>
        <!-- Başvuru Formu -->
        <div class="affiliate-hero">
            <div class="affiliate-hero-content">
                <h1><i class="fas fa-handshake"></i> Satış Ortaklığı Programı</h1>
                <p>Referans linkinizi paylaşarak yeni müşteriler kazandırın ve her satıştan komisyon kazanın. Hemen başvurun ve kazanmaya başlayın!</p>
            </div>
        </div>
        
        <div class="content-card apply-card">
            <div class="card-header">
                <h3><i class="fas fa-user-plus"></i> Satış Ortağı Başvurusu</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="apply_affiliate" value="1">
                    
                    <div class="form-group">
                        <label>Ödeme Yöntemi</label>
                        <select name="payment_method" class="form-control" required>
                            <option value="bank_transfer">Banka Havalesi / EFT</option>
                            <option value="papara">Papara</option>
                            <option value="paypal">PayPal</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Ödeme Bilgileri</label>
                        <textarea name="payment_details" class="form-control" required 
                                  placeholder="Banka hesap bilgilerinizi girin (IBAN, Hesap Sahibi vb.)"></textarea>
                        <small style="color: var(--text-muted); margin-top: 8px; display: block;">
                            Komisyonlarınızın ödeneceği hesap bilgilerini eksiksiz girin.
                        </small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-paper-plane"></i> Başvuruyu Gönder
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Özellikler -->
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-percentage"></i></div>
                <div class="feature-text">
                    <h4>%<?= Settings::get('affiliate_default_commission', '10') ?> Komisyon</h4>
                    <p>Her satıştan komisyon kazanın</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-clock"></i></div>
                <div class="feature-text">
                    <h4><?= Settings::get('affiliate_cookie_days', '30') ?> Gün Takip</h4>
                    <p>Referanslarınız <?= Settings::get('affiliate_cookie_days', '30') ?> gün boyunca takip edilir</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-money-bill-wave"></i></div>
                <div class="feature-text">
                    <h4>Kolay Çekim</h4>
                    <p>Minimum <?= number_format((float)Settings::get('affiliate_min_withdrawal', '100'), 0) ?> TL'den itibaren çekim yapın</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                <div class="feature-text">
                    <h4>Detaylı Raporlar</h4>
                    <p>Ziyaretçi, kayıt ve satış istatistiklerinizi takip edin</p>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Affiliate Dashboard -->
        <div class="affiliate-hero">
            <div class="affiliate-hero-content">
                <h1><i class="fas fa-handshake"></i> Satış Ortaklığı Paneli</h1>
                <p>Referans linkinizi paylaşın, yeni müşteriler kazandırın ve komisyon kazanın!</p>
                
                <span class="status-badge <?= $affiliate['status'] ?>">
                    <?php if ($affiliate['status'] === 'active'): ?>
                        <i class="fas fa-check-circle"></i> Aktif Hesap
                    <?php elseif ($affiliate['status'] === 'pending'): ?>
                        <i class="fas fa-clock"></i> Onay Bekliyor
                    <?php elseif ($affiliate['status'] === 'suspended'): ?>
                        <i class="fas fa-ban"></i> Askıya Alındı
                    <?php endif; ?>
                </span>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-eye"></i></div>
                <div class="stat-info">
                    <h4><?= number_format($stats['total_visits'] ?? 0) ?></h4>
                    <p>Toplam Ziyaret</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-user-plus"></i></div>
                <div class="stat-info">
                    <h4><?= number_format($stats['total_signups'] ?? 0) ?></h4>
                    <p>Kayıt Olan</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-shopping-cart"></i></div>
                <div class="stat-info">
                    <h4><?= number_format($stats['total_orders'] ?? 0) ?></h4>
                    <p>Sipariş</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-coins"></i></div>
                <div class="stat-info">
                    <h4><?= number_format($stats['total_earnings'] ?? 0, 2) ?> ₺</h4>
                    <p>Toplam Kazanç</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon pink"><i class="fas fa-wallet"></i></div>
                <div class="stat-info">
                    <h4><?= number_format($stats['balance'] ?? 0, 2) ?> ₺</h4>
                    <p>Mevcut Bakiye</p>
                </div>
            </div>
        </div>
        
        <!-- Referral Link -->
        <?php if ($affiliate['status'] === 'active'): ?>
        <div class="referral-link-box">
            <h3><i class="fas fa-link"></i> Referans Linkiniz</h3>
            <div class="referral-link-input">
                <input type="text" id="referralLink" value="<?= htmlspecialchars(Affiliate::getReferralLink($affiliate['affiliate_code'])) ?>" readonly>
                <button class="btn-copy" onclick="copyLink()">
                    <i class="fas fa-copy"></i> Kopyala
                </button>
            </div>
            <p style="margin-top: 12px; font-size: 13px; color: var(--text-muted);">
                <strong>Affiliate Kodunuz:</strong> <?= htmlspecialchars($affiliate['affiliate_code']) ?> 
                | <strong>Komisyon Oranı:</strong> %<?= number_format((float)$affiliate['commission_rate'], 0) ?>
            </p>
        </div>
        <?php endif; ?>
        
        <div class="content-grid">
            <div>
                <!-- Referanslar -->
                <div class="content-card" style="margin-bottom: 30px;">
                    <div class="card-header">
                        <h3><i class="fas fa-users"></i> Referanslarım</h3>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <?php if (empty($referrals)): ?>
                            <div class="empty-state">
                                <i class="fas fa-user-friends"></i>
                                <p>Henüz referansınız bulunmuyor</p>
                            </div>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Müşteri</th>
                                        <th>Kayıt Tarihi</th>
                                        <th>Durum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($referrals as $ref): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($ref['first_name'] . ' ' . $ref['last_name']) ?></strong><br>
                                            <small style="color: var(--text-muted);"><?= htmlspecialchars($ref['email']) ?></small>
                                        </td>
                                        <td><?= date('d.m.Y H:i', strtotime($ref['client_created'])) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $ref['status'] === 'approved' ? 'success' : 'warning' ?>">
                                                <?= $ref['status'] === 'approved' ? 'Onaylı' : 'Beklemede' ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Komisyonlar -->
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-money-bill-wave"></i> Komisyon Geçmişi</h3>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <?php if (empty($commissions)): ?>
                            <div class="empty-state">
                                <i class="fas fa-coins"></i>
                                <p>Henüz komisyon kaydınız bulunmuyor</p>
                            </div>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Açıklama</th>
                                        <th>Tutar</th>
                                        <th>Komisyon</th>
                                        <th>Durum</th>
                                        <th>Tarih</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($commissions as $comm): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($comm['description']) ?></td>
                                        <td><?= number_format((float)$comm['amount'], 2) ?> ₺</td>
                                        <td><strong style="color: var(--success);">+<?= number_format((float)$comm['commission_amount'], 2) ?> ₺</strong></td>
                                        <td>
                                            <span class="badge badge-<?= $comm['status'] === 'paid' ? 'success' : ($comm['status'] === 'approved' ? 'info' : 'warning') ?>">
                                                <?= match($comm['status']) {
                                                    'paid' => 'Ödendi',
                                                    'approved' => 'Onaylı',
                                                    'pending' => 'Beklemede',
                                                    default => $comm['status']
                                                } ?>
                                            </span>
                                        </td>
                                        <td><?= date('d.m.Y', strtotime($comm['created_at'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div>
                <!-- Çekim Talebi -->
                <?php if ($affiliate['status'] === 'active'): ?>
                <div class="content-card" style="margin-bottom: 30px;">
                    <div class="card-header">
                        <h3><i class="fas fa-wallet"></i> Bakiye Çekimi</h3>
                    </div>
                    <div class="card-body">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <div style="font-size: 36px; font-weight: 700; color: var(--success);">
                                <?= number_format($stats['balance'] ?? 0, 2) ?> ₺
                            </div>
                            <p style="color: var(--text-muted); font-size: 14px;">Çekilebilir Bakiye</p>
                        </div>
                        
                        <form method="POST" class="withdrawal-form">
                            <input type="hidden" name="request_withdrawal" value="1">
                            <input type="number" name="withdrawal_amount" placeholder="Tutar" step="0.01" 
                                   min="<?= $affiliate['min_withdrawal'] ?>" max="<?= $stats['balance'] ?? 0 ?>" required>
                            <button type="submit" <?= ($stats['balance'] ?? 0) < $affiliate['min_withdrawal'] ? 'disabled' : '' ?>>
                                <i class="fas fa-paper-plane"></i> Talep Et
                            </button>
                        </form>
                        
                        <p style="font-size: 12px; color: var(--text-muted); margin-top: 12px; text-align: center;">
                            Minimum çekim: <?= number_format((float)$affiliate['min_withdrawal'], 0) ?> ₺
                        </p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Ödeme Bilgileri -->
                <div class="content-card" style="margin-bottom: 30px;">
                    <div class="card-header">
                        <h3><i class="fas fa-credit-card"></i> Ödeme Bilgileri</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="update_payment" value="1">
                            
                            <div class="form-group">
                                <label>Ödeme Yöntemi</label>
                                <select name="payment_method" class="form-control">
                                    <option value="bank_transfer" <?= $affiliate['payment_method'] === 'bank_transfer' ? 'selected' : '' ?>>Banka Havalesi</option>
                                    <option value="papara" <?= $affiliate['payment_method'] === 'papara' ? 'selected' : '' ?>>Papara</option>
                                    <option value="paypal" <?= $affiliate['payment_method'] === 'paypal' ? 'selected' : '' ?>>PayPal</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Ödeme Detayları</label>
                                <textarea name="payment_details" class="form-control" rows="3"><?= htmlspecialchars($affiliate['payment_details']) ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-outline" style="width: 100%;">
                                <i class="fas fa-save"></i> Güncelle
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Çekim Geçmişi -->
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> Çekim Geçmişi</h3>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <?php if (empty($withdrawals)): ?>
                            <div class="empty-state">
                                <i class="fas fa-receipt"></i>
                                <p>Çekim talebiniz yok</p>
                            </div>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Tutar</th>
                                        <th>Durum</th>
                                        <th>Tarih</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($withdrawals as $wd): ?>
                                    <tr>
                                        <td><strong><?= number_format((float)$wd['amount'], 2) ?> ₺</strong></td>
                                        <td>
                                            <span class="badge badge-<?= match($wd['status']) {
                                                'completed' => 'success',
                                                'processing' => 'info',
                                                'rejected' => 'danger',
                                                default => 'warning'
                                            } ?>">
                                                <?= match($wd['status']) {
                                                    'completed' => 'Tamamlandı',
                                                    'processing' => 'İşleniyor',
                                                    'rejected' => 'Reddedildi',
                                                    default => 'Beklemede'
                                                } ?>
                                            </span>
                                        </td>
                                        <td><?= date('d.m.Y', strtotime($wd['created_at'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function copyLink() {
    const input = document.getElementById('referralLink');
    input.select();
    document.execCommand('copy');
    
    const btn = document.querySelector('.btn-copy');
    btn.classList.add('copied');
    btn.innerHTML = '<i class="fas fa-check"></i> Kopyalandı!';
    
    setTimeout(() => {
        btn.classList.remove('copied');
        btn.innerHTML = '<i class="fas fa-copy"></i> Kopyala';
    }, 2000);
}
</script>

<?php include 'includes/footer.php'; ?>

