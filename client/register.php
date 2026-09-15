<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Mail.php';
require_once dirname(__DIR__) . '/includes/Affiliate.php';
session_name(SESSION_NAME); session_start();

$siteLogo = Settings::getLogo();

// Zaten giriş yapmışsa
if (isset($_SESSION['client_id'])) {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance();
$error = '';
$success = '';

// Tax office kolonu yoksa ekle
try {
    $db->query("SELECT tax_office FROM clients LIMIT 1");
} catch (Throwable $e) {
    try {
        $db->exec("ALTER TABLE clients ADD COLUMN tax_office VARCHAR(100) NULL AFTER tax_id");
    } catch (Throwable $e2) {
        // Kolon zaten varsa veya hata varsa devam et
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accountType = $_POST['account_type'] ?? 'individual';
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'];
    $passwordConfirm = $_POST['password_confirm'];
    
    // Kurumsal bilgiler
    $companyName = trim($_POST['company_name'] ?? '');
    $taxId = trim($_POST['tax_id'] ?? '');
    $taxOffice = trim($_POST['tax_office'] ?? '');
    
    // Validasyon
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $error = 'Lütfen tüm zorunlu alanları doldurun.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Geçerli bir e-posta adresi girin.';
    } elseif (strlen($password) < 8) {
        $error = 'Şifre en az 8 karakter olmalıdır.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Şifreler eşleşmiyor.';
    } elseif ($accountType === 'corporate' && (empty($companyName) || empty($taxId))) {
        $error = 'Kurumsal üyelik için şirket adı ve vergi numarası zorunludur.';
    } elseif ($accountType === 'corporate' && !empty($taxId) && !preg_match('/^[0-9]{10}$/', $taxId)) {
        $error = 'Vergi numarası 10 haneli olmalıdır.';
    } else {
        // E-posta kontrolü
        $stmt = $db->prepare("SELECT id FROM clients WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Bu e-posta adresi zaten kayıtlı.';
        } else {
            // Kayıt
            $stmt = $db->prepare("
                INSERT INTO clients (email, password, first_name, last_name, phone, company_name, tax_id, tax_office, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $firstName,
                $lastName,
                $phone,
                $accountType === 'corporate' ? $companyName : null,
                $accountType === 'corporate' ? $taxId : null,
                $accountType === 'corporate' ? $taxOffice : null
            ]);
            
            $newClientId = (int)$db->lastInsertId();
            
            // Affiliate referansını işle
            try {
                Affiliate::processSignup($newClientId);
            } catch (Throwable $e) {
                // Affiliate hatası kayıt işlemini engellemesin
            }
            
            // Hoş geldiniz e-postası gönder
            try {
                Mail::sendTemplate('welcome_email', $email, [
                    'client_name' => $firstName . ' ' . $lastName,
                    'client_email' => $email
                ], $firstName . ' ' . $lastName);
            } catch (Throwable $e) {
                // Mail hatası kayıt işlemini engellemesin
            }
            
            $success = 'Hesabınız başarıyla oluşturuldu!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - <?= SITE_NAME ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2474f5;
            --primary-dark: #1b5ed4;
            --secondary: #0ea5e9;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
            --border: #e2e8f0;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--light);
            min-height: 100vh;
        }
        
        .register-page {
            display: flex;
            min-height: 100vh;
        }
        
        /* Left Side - Visual */
        .visual-side {
            width: 45%;
            background: linear-gradient(135deg, #2474f5 0%, #4b91fa 50%, #4b91fa 100%);
            display: flex;
            align-items: flex-start;
            justify-content: center;
            position: relative;
            overflow: hidden;
            padding-top: 138px;
        }
        
        .visual-side::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            top: -200px;
            left: -200px;
            animation: float 15s infinite ease-in-out;
        }
        
        .visual-side::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
            bottom: -100px;
            right: -100px;
            animation: float 12s infinite ease-in-out reverse;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(30px, -30px) rotate(5deg); }
            50% { transform: translate(-20px, 20px) rotate(-5deg); }
            75% { transform: translate(20px, 30px) rotate(3deg); }
        }
        
        .visual-content {
            position: relative;
            z-index: 1;
            text-align: center;
            color: #fff;
            padding: 0 30px;
            max-width: 420px;
        }
        
        .visual-content .icon-box {
            width: 70px;
            height: 70px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 20px;
            animation: bounce 3s infinite ease-in-out;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .visual-content h2 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 15px;
            line-height: 1.2;
        }
        
        .visual-content p {
            font-size: 15px;
            opacity: 0.9;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        
        .benefits {
            text-align: left;
        }
        
        .benefit-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        
        .benefit-item:hover {
            background: rgba(255,255,255,0.2);
            transform: translateX(5px);
        }
        
        .benefit-item .icon {
            width: 38px;
            height: 38px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .benefit-item .text h4 {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .benefit-item .text p {
            font-size: 11px;
            opacity: 0.8;
            margin: 0;
        }
        
        /* Right Side - Form */
        .form-side {
            width: 55%;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 0 50px;
            padding-top: 20px;
            background: #fff;
            overflow-y: auto;
        }
        
        .form-container {
            width: 100%;
            max-width: 480px;
        }
        
        .logo {
            margin-bottom: 20px;
            margin-top: 0;
        }
        
        .logo a {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--dark);
            font-weight: 700;
            font-size: 20px;
        }
        
        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary) 0%, #4b91fa 100%);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 24px;
        }
        
        .logo-img {
            max-height: 60px;
            max-width: 220px;
            object-fit: contain;
        }
        
        .form-header {
            margin-bottom: 20px;
        }
        
        .form-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 8px;
        }
        
        .form-header p {
            color: var(--gray);
            font-size: 15px;
        }
        
        .form-header p a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }
        
        .form-header p a:hover {
            text-decoration: underline;
        }
        
        /* Alert */
        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 13px;
            font-weight: 500;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #fee2e2 0%, #fef2f2 100%);
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d1fae5 0%, #ecfdf5 100%);
            border: 1px solid #a7f3d0;
            color: #059669;
        }
        
        .alert i {
            font-size: 16px;
        }
        
        /* Form */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--dark);
            font-size: 13px;
        }
        
        .form-group label i {
            color: var(--primary);
            font-size: 12px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: var(--light);
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
            color: var(--dark);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(36, 116, 245, 0.1);
        }
        
        .form-control::placeholder {
            color: #94a3b8;
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .password-wrapper .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            font-size: 14px;
            padding: 5px;
        }
        
        .password-wrapper .toggle-password:hover {
            color: var(--primary);
        }
        
        .form-hint {
            display: block;
            margin-top: 4px;
            color: var(--gray);
            font-size: 11px;
        }
        
        /* Checkbox */
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 18px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            margin-top: 2px;
            flex-shrink: 0;
        }
        
        .checkbox-group label {
            font-size: 13px;
            color: var(--gray);
            line-height: 1.4;
        }
        
        .checkbox-group label a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .checkbox-group label a:hover {
            text-decoration: underline;
        }
        
        /* Button */
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary) 0%, #4b91fa 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: inherit;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(36, 116, 245, 0.3);
        }
        
        .btn:active {
            transform: translateY(-1px);
        }
        
        .btn-outline {
            background: #fff;
            color: var(--primary);
            border: 2px solid var(--primary);
            padding: 12px;
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: #fff;
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--success) 0%, #34d399 100%);
        }
        
        .btn-success:hover {
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }
        
        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 20px 0;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }
        
        .divider span {
            color: var(--gray);
            font-size: 14px;
        }
        
        /* Login Link */
        .login-box {
            text-align: center;
        }
        
        .login-box p {
            color: var(--gray);
            font-size: 14px;
            margin-bottom: 12px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--gray);
            text-decoration: none;
            font-size: 13px;
            margin-top: 15px;
            padding: 8px 15px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .back-link:hover {
            background: var(--light);
            color: var(--primary);
        }
        
        /* Success State */
        .success-state {
            text-align: center;
            padding: 20px 0;
        }
        
        .success-state .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin: 0 auto 20px;
            animation: success-pop 0.5s ease;
        }
        
        @keyframes success-pop {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .success-state h2 {
            font-size: 24px;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .success-state p {
            color: var(--gray);
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .visual-side {
                display: none;
            }
            
            .form-side {
                width: 100%;
            }
        }
        
        /* Account Type Selector */
        .account-type-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 8px;
        }
        
        .account-type-option {
            position: relative;
            cursor: pointer;
        }
        
        .account-type-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .account-type-option .option-content {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: var(--light);
            border: 2px solid var(--border);
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .account-type-option:hover .option-content {
            border-color: var(--primary);
            background: rgba(36, 116, 245, 0.05);
        }
        
        .account-type-option input[type="radio"]:checked + .option-content {
            background: linear-gradient(135deg, rgba(36, 116, 245, 0.1) 0%, rgba(75, 145, 250, 0.1) 100%);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(36, 116, 245, 0.1);
        }
        
        .option-icon {
            font-size: 24px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(36, 116, 245, 0.1);
            border-radius: 10px;
            flex-shrink: 0;
        }
        
        .account-type-option input[type="radio"]:checked + .option-content .option-icon {
            background: linear-gradient(135deg, var(--primary) 0%, #4b91fa 100%);
        }
        
        .option-text {
            flex: 1;
        }
        
        .option-text strong {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 2px;
        }
        
        .option-text span {
            display: block;
            font-size: 12px;
            color: var(--gray);
        }
        
        /* Company Divider */
        .company-divider {
            position: relative;
            text-align: center;
            margin: 24px 0 20px;
        }
        
        .company-divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--border);
        }
        
        .company-divider span {
            position: relative;
            background: #fff;
            padding: 0 15px;
            color: var(--gray);
            font-size: 13px;
            font-weight: 600;
        }
        
        @media (max-width: 576px) {
            .form-side {
                padding: 10px 15px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .account-type-selector {
                grid-template-columns: 1fr;
            }
            
            .form-header h1 {
                font-size: 24px;
            }
            
            .logo {
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="register-page">
        <!-- Visual Side -->
        <div class="visual-side">
            <div class="visual-content">
                <div class="icon-box">🚀</div>
                <h2>Hizmetlerinizi<br>Kolayca Yönetin</h2>
                <p>Hesap oluşturarak sunucu, hosting ve domain hizmetlerinizi tek bir panel üzerinden yönetin.</p>
                
                <div class="benefits">
                    <div class="benefit-item">
                        <div class="icon"><i class="fas fa-bolt"></i></div>
                        <div class="text">
                            <h4>Anında Aktivasyon</h4>
                            <p>Siparişleriniz otomatik olarak aktif edilir</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="icon"><i class="fas fa-shield-alt"></i></div>
                        <div class="text">
                            <h4>Güvenli Altyapı</h4>
                            <p>Verileriniz güvende, 7/24 izleme</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="icon"><i class="fas fa-headset"></i></div>
                        <div class="text">
                            <h4>7/24 Destek</h4>
                            <p>Uzman ekibimiz her zaman yanınızda</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Form Side -->
        <div class="form-side">
            <div class="form-container">
                <div class="logo">
                    <a href="../">
                        <?php if (!empty($siteLogo)): ?>
                            <img src="../<?= htmlspecialchars($siteLogo) ?>" alt="<?= SITE_NAME ?>" class="logo-img">
                        <?php else: ?>
                            <div class="logo-icon"><i class="fas fa-rocket"></i></div>
                            <?= SITE_NAME ?>
                        <?php endif; ?>
                    </a>
                </div>
                
                <?php if ($success): ?>
                    <div class="success-state">
                        <div class="icon">✓</div>
                        <h2>Hesabınız Oluşturuldu!</h2>
                        <p>Artık giriş yaparak hizmetlerimizden yararlanabilirsiniz.</p>
                        <a href="index.php" class="btn btn-success">
                            <i class="fas fa-sign-in-alt"></i> Giriş Yap
                        </a>
                    </div>
                <?php else: ?>
                    <div class="form-header">
                        <h1>Hesap Oluştur</h1>
                        <p>Zaten hesabınız var mı? <a href="index.php">Giriş yapın</a></p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="registerForm">
                        <!-- Hesap Tipi Seçimi -->
                        <div class="form-group">
                            <label><i class="fas fa-user-tag"></i> Hesap Tipi <span style="color: #dc2626;">*</span></label>
                            <div class="account-type-selector">
                                <label class="account-type-option">
                                    <input type="radio" name="account_type" value="individual" checked onchange="toggleCompanyFields()">
                                    <div class="option-content">
                                        <div class="option-icon">👤</div>
                                        <div class="option-text">
                                            <strong>Bireysel</strong>
                                            <span>Kişisel kullanım için</span>
                                        </div>
                                    </div>
                                </label>
                                <label class="account-type-option">
                                    <input type="radio" name="account_type" value="corporate" onchange="toggleCompanyFields()">
                                    <div class="option-content">
                                        <div class="option-icon">🏢</div>
                                        <div class="option-text">
                                            <strong>Kurumsal</strong>
                                            <span>Şirket bilgileri ile</span>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Adınız <span style="color: #dc2626;">*</span></label>
                                <input type="text" name="first_name" class="form-control" required 
                                       placeholder="Adınızı girin"
                                       value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Soyadınız <span style="color: #dc2626;">*</span></label>
                                <input type="text" name="last_name" class="form-control" required 
                                       placeholder="Soyadınızı girin"
                                       value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> E-posta Adresi <span style="color: #dc2626;">*</span></label>
                            <input type="email" name="email" class="form-control" required 
                                   placeholder="ornek@email.com"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Telefon <span style="color: var(--gray); font-weight: 400;">(Opsiyonel)</span></label>
                            <input type="tel" name="phone" class="form-control" 
                                   placeholder="+90 555 123 4567"
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>
                        
                        <!-- Kurumsal Bilgiler (Gizli) -->
                        <div id="companyFields" style="display: none;">
                            <div class="company-divider">
                                <span>Kurumsal Bilgiler</span>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-building"></i> Şirket Adı <span style="color: #dc2626;">*</span></label>
                                <input type="text" name="company_name" id="company_name" class="form-control" 
                                       placeholder="Şirket adını girin"
                                       value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-id-card"></i> Vergi Numarası <span style="color: #dc2626;">*</span></label>
                                    <input type="text" name="tax_id" id="tax_id" class="form-control" 
                                           placeholder="Vergi numarası"
                                           value="<?= htmlspecialchars($_POST['tax_id'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-landmark"></i> Vergi Dairesi</label>
                                    <input type="text" name="tax_office" id="tax_office" class="form-control" 
                                           placeholder="Vergi dairesi adı"
                                           value="<?= htmlspecialchars($_POST['tax_office'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> Şifre</label>
                                <div class="password-wrapper">
                                    <input type="password" name="password" id="password" class="form-control" 
                                           required minlength="8" placeholder="••••••••">
                                    <button type="button" class="toggle-password" onclick="togglePassword('password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <span class="form-hint">En az 8 karakter</span>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> Şifre Tekrar</label>
                                <div class="password-wrapper">
                                    <input type="password" name="password_confirm" id="password_confirm" class="form-control" 
                                           required minlength="8" placeholder="••••••••">
                                    <button type="button" class="toggle-password" onclick="togglePassword('password_confirm')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" name="terms" id="terms" required>
                            <label for="terms">
                                <a href="../kullanim-sartlari.php">Kullanım Şartları</a>'nı ve 
                                <a href="../gizlilik-politikasi.php">Gizlilik Politikası</a>'nı okudum ve kabul ediyorum.
                            </label>
                        </div>
                        
                        <button type="submit" class="btn">
                            <i class="fas fa-user-plus"></i> Hesap Oluştur
                        </button>
                    </form>
                    
                    <div class="divider">
                        <span>veya</span>
                    </div>
                    
                    <div class="login-box">
                        <p>Zaten bir hesabınız var mı?</p>
                        <a href="index.php" class="btn btn-outline">
                            <i class="fas fa-sign-in-alt"></i> Giriş Yap
                        </a>
                    </div>
                <?php endif; ?>
                
                <div style="text-align: center;">
                    <a href="../" class="back-link">
                        <i class="fas fa-arrow-left"></i> Ana Sayfaya Dön
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const icon = input.nextElementSibling.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
    
    function toggleCompanyFields() {
        const accountType = document.querySelector('input[name="account_type"]:checked').value;
        const companyFields = document.getElementById('companyFields');
        const companyName = document.getElementById('company_name');
        const taxId = document.getElementById('tax_id');
        
        if (accountType === 'corporate') {
            companyFields.style.display = 'block';
            companyName.setAttribute('required', 'required');
            taxId.setAttribute('required', 'required');
        } else {
            companyFields.style.display = 'none';
            companyName.removeAttribute('required');
            taxId.removeAttribute('required');
            companyName.value = '';
            taxId.value = '';
            document.getElementById('tax_office').value = '';
        }
    }
    
    // Form gönderilirken kurumsal alanları kontrol et
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        const accountType = document.querySelector('input[name="account_type"]:checked').value;
        if (accountType === 'corporate') {
            const companyName = document.getElementById('company_name').value.trim();
            const taxId = document.getElementById('tax_id').value.trim();
            
            if (!companyName || !taxId) {
                e.preventDefault();
                alert('Kurumsal üyelik için şirket adı ve vergi numarası zorunludur.');
                return false;
            }
        }
    });
    </script>
</body>
</html>
