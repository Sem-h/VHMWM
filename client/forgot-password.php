<?php
/**
 * WHMVM - Şifre Sıfırlama İsteği
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Mail.php';

session_name(SESSION_NAME);
session_start();

$siteLogo = Settings::getLogo();

// Zaten giriş yapmışsa
if (isset($_SESSION['client_id'])) {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Lütfen e-posta adresinizi girin.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Geçerli bir e-posta adresi girin.';
    } else {
        // Kullanıcıyı bul
        $client = Database::fetch("SELECT id, first_name, last_name, email FROM clients WHERE email = ?", [$email]);
        
        if ($client) {
            // Token oluştur
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Token'ı kaydet (password_resets tablosu varsa ona, yoksa clients tablosuna)
            try {
                // password_resets tablosu var mı kontrol et
                $tableExists = $db->query("SHOW TABLES LIKE 'password_resets'")->rowCount() > 0;
                
                if ($tableExists) {
                    // Eski tokenları sil
                    Database::query("DELETE FROM password_resets WHERE email = ?", [$email]);
                    // Yeni token ekle
                    Database::query("INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (?, ?, ?, NOW())", [$email, $token, $expires]);
                } else {
                    // Tabloyu oluştur
                    $db->exec("
                        CREATE TABLE IF NOT EXISTS password_resets (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            email VARCHAR(255) NOT NULL,
                            token VARCHAR(255) NOT NULL,
                            expires_at DATETIME NOT NULL,
                            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_email (email),
                            INDEX idx_token (token)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    Database::query("INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (?, ?, ?, NOW())", [$email, $token, $expires]);
                }
                
                // Şifre sıfırlama e-postası gönder
                $resetLink = SITE_URL . '/client/reset-password.php?token=' . $token;
                
                Mail::sendTemplate('password_reset', $email, [
                    'client_name' => $client['first_name'] . ' ' . $client['last_name'],
                    'reset_link' => $resetLink,
                    'reset_url' => $resetLink
                ], $client['first_name']);
                
                $success = 'Şifre sıfırlama bağlantısı e-posta adresinize gönderildi. Lütfen gelen kutunuzu kontrol edin.';
                
            } catch (Throwable $e) {
                $error = 'Bir hata oluştu. Lütfen tekrar deneyin.';
            }
        } else {
            // Güvenlik için aynı mesajı göster
            $success = 'Bu e-posta adresi sistemde kayıtlıysa, şifre sıfırlama bağlantısı gönderilecektir.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifremi Unuttum - <?= SITE_NAME ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #6366f1;
            --primary-light: #818cf8;
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
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            min-height: 100vh;
            display: flex;
            background: #0f172a;
            overflow: hidden;
        }
        
        /* Sol Taraf - Görsel */
        .visual-panel {
            width: 55%;
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 30%, #4338ca 60%, #6366f1 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        /* Animated Background Elements */
        .bg-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
        
        .shape {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.03);
            animation: float 20s infinite ease-in-out;
        }
        
        .shape:nth-child(1) {
            width: 600px;
            height: 600px;
            top: -200px;
            left: -200px;
            animation-delay: 0s;
        }
        
        .shape:nth-child(2) {
            width: 400px;
            height: 400px;
            bottom: -100px;
            right: -100px;
            animation-delay: -5s;
            background: rgba(168, 85, 247, 0.1);
        }
        
        .shape:nth-child(3) {
            width: 200px;
            height: 200px;
            top: 50%;
            left: 50%;
            animation-delay: -10s;
            background: rgba(99, 102, 241, 0.1);
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg) scale(1); }
            25% { transform: translate(30px, -50px) rotate(5deg) scale(1.05); }
            50% { transform: translate(-20px, 30px) rotate(-3deg) scale(0.95); }
            75% { transform: translate(40px, 20px) rotate(3deg) scale(1.02); }
        }
        
        /* Grid Pattern Overlay */
        .grid-pattern {
            position: absolute;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 50px 50px;
            pointer-events: none;
        }
        
        .visual-content {
            position: relative;
            z-index: 1;
            text-align: center;
            color: white;
            padding: 40px;
            max-width: 500px;
        }
        
        .lock-animation {
            width: 140px;
            height: 140px;
            margin: 0 auto 40px;
            position: relative;
        }
        
        .lock-circle {
            width: 140px;
            height: 140px;
            border: 3px solid rgba(255,255,255,0.2);
            border-radius: 50%;
            position: absolute;
            animation: pulse-ring 2s infinite;
        }
        
        .lock-circle:nth-child(2) {
            animation-delay: 0.5s;
        }
        
        .lock-circle:nth-child(3) {
            animation-delay: 1s;
        }
        
        @keyframes pulse-ring {
            0% { transform: scale(0.8); opacity: 1; }
            100% { transform: scale(1.5); opacity: 0; }
        }
        
        .lock-icon {
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border: 1px solid rgba(255,255,255,0.2);
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
        }
        
        .lock-icon i {
            font-size: 42px;
            color: white;
            animation: key-bounce 2s infinite ease-in-out;
        }
        
        @keyframes key-bounce {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-5px) rotate(-5deg); }
        }
        
        .visual-content h2 {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 16px;
            line-height: 1.2;
            letter-spacing: -0.5px;
        }
        
        .visual-content p {
            font-size: 17px;
            opacity: 0.8;
            line-height: 1.7;
            margin-bottom: 40px;
        }
        
        /* Steps */
        .steps {
            text-align: left;
        }
        
        .step {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 16px 20px;
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            margin-bottom: 12px;
            border: 1px solid rgba(255,255,255,0.08);
            transition: all 0.3s;
        }
        
        .step:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .step-number {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--primary) 0%, #a855f7 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .step-text {
            font-size: 14px;
            line-height: 1.5;
        }
        
        .step-text strong {
            display: block;
            margin-bottom: 2px;
        }
        
        .step-text span {
            opacity: 0.7;
            font-size: 13px;
        }
        
        /* Sağ Taraf - Form */
        .form-panel {
            width: 45%;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            position: relative;
        }
        
        .form-container {
            width: 100%;
            max-width: 420px;
        }
        
        /* Logo */
        .logo {
            margin-bottom: 40px;
        }
        
        .logo a {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--dark);
        }
        
        .logo-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary) 0%, #a855f7 100%);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 22px;
        }
        
        .logo-text {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        
        .logo-img {
            max-height: 50px;
            max-width: 200px;
            object-fit: contain;
        }
        
        /* Form Header */
        .form-header {
            margin-bottom: 32px;
        }
        
        .form-header h1 {
            font-size: 30px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }
        
        .form-header p {
            color: var(--gray);
            font-size: 15px;
            line-height: 1.6;
        }
        
        /* Alerts */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 18px 20px;
            border-radius: 16px;
            margin-bottom: 24px;
            font-size: 14px;
            line-height: 1.5;
            animation: slideDown 0.4s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert i {
            font-size: 20px;
            margin-top: 2px;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border: 1px solid #a7f3d0;
            color: #059669;
        }
        
        /* Form */
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }
        
        .form-label i {
            color: var(--primary);
            font-size: 14px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper i.input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 18px;
            transition: all 0.3s;
            pointer-events: none;
        }
        
        .form-input {
            width: 100%;
            padding: 16px 18px 16px 52px;
            background: var(--light);
            border: 2px solid var(--border);
            border-radius: 14px;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.3s;
            color: var(--dark);
        }
        
        .form-input:hover {
            border-color: #cbd5e1;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        
        .form-input:focus + .input-icon,
        .input-wrapper:focus-within i.input-icon {
            color: var(--primary);
        }
        
        .form-input::placeholder {
            color: #94a3b8;
        }
        
        /* Button */
        .btn-submit {
            width: 100%;
            padding: 18px 24px;
            background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-family: inherit;
            position: relative;
            overflow: hidden;
        }
        
        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: all 0.5s;
        }
        
        .btn-submit:hover::before {
            left: 100%;
        }
        
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(99, 102, 241, 0.35);
        }
        
        .btn-submit:active {
            transform: translateY(-1px);
        }
        
        /* Back Link */
        .back-section {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
            text-align: center;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--gray);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 12px;
            transition: all 0.3s;
            background: var(--light);
        }
        
        .back-link:hover {
            background: var(--dark);
            color: white;
        }
        
        /* Success State */
        .success-state {
            text-align: center;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .success-icon-wrapper {
            width: 120px;
            height: 120px;
            margin: 0 auto 30px;
            position: relative;
        }
        
        .success-bg {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border-radius: 50%;
            position: absolute;
            animation: success-pulse 2s infinite;
        }
        
        @keyframes success-pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.7; }
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            box-shadow: 0 15px 40px rgba(16, 185, 129, 0.4);
        }
        
        .success-icon i {
            font-size: 48px;
            color: white;
            animation: check-pop 0.5s ease 0.2s both;
        }
        
        @keyframes check-pop {
            0% { transform: scale(0) rotate(-45deg); opacity: 0; }
            50% { transform: scale(1.2) rotate(10deg); }
            100% { transform: scale(1) rotate(0deg); opacity: 1; }
        }
        
        .success-state h2 {
            font-size: 28px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 12px;
        }
        
        .success-state p {
            color: var(--gray);
            font-size: 15px;
            line-height: 1.7;
            margin-bottom: 30px;
        }
        
        .email-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid #bae6fd;
            border-radius: 50px;
            color: #0284c7;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 25px;
        }
        
        .btn-check-email {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 32px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            text-decoration: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 15px;
            transition: all 0.3s;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
        }
        
        .btn-check-email:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(16, 185, 129, 0.4);
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .visual-panel {
                display: none;
            }
            
            .form-panel {
                width: 100%;
            }
        }
        
        @media (max-width: 480px) {
            .form-panel {
                padding: 30px 20px;
            }
            
            .form-header h1 {
                font-size: 26px;
            }
            
            .success-state h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <!-- Sol Taraf - Görsel -->
    <div class="visual-panel">
        <div class="bg-shapes">
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
        </div>
        <div class="grid-pattern"></div>
        
        <div class="visual-content">
            <div class="lock-animation">
                <div class="lock-circle"></div>
                <div class="lock-circle"></div>
                <div class="lock-circle"></div>
                <div class="lock-icon">
                    <i class="fas fa-key"></i>
                </div>
            </div>
            
            <h2>Şifrenizi mi<br>Unuttunuz?</h2>
            <p>Endişelenmeyin! Şifre sıfırlama işlemi çok kolay. Sadece birkaç adımda yeni şifrenizi belirleyebilirsiniz.</p>
            
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-text">
                        <strong>E-posta Adresinizi Girin</strong>
                        <span>Hesabınıza kayıtlı e-posta adresini yazın</span>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-text">
                        <strong>Bağlantıya Tıklayın</strong>
                        <span>Size gönderilen e-postadaki linke tıklayın</span>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-text">
                        <strong>Yeni Şifre Belirleyin</strong>
                        <span>Güvenli bir şifre oluşturun ve giriş yapın</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sağ Taraf - Form -->
    <div class="form-panel">
        <div class="form-container">
            <div class="logo">
                <a href="../">
                    <?php if (!empty($siteLogo)): ?>
                        <img src="../<?= htmlspecialchars($siteLogo) ?>" alt="<?= SITE_NAME ?>" class="logo-img">
                    <?php else: ?>
                        <div class="logo-icon"><i class="fas fa-rocket"></i></div>
                        <span class="logo-text"><?= SITE_NAME ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <?php if ($success): ?>
                <div class="success-state">
                    <div class="success-icon-wrapper">
                        <div class="success-bg"></div>
                        <div class="success-icon">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                    </div>
                    
                    <h2>E-posta Gönderildi!</h2>
                    <p>Şifre sıfırlama bağlantısı e-posta adresinize gönderildi. Lütfen gelen kutunuzu kontrol edin.</p>
                    
                    <div class="email-badge">
                        <i class="fas fa-envelope"></i>
                        <?= htmlspecialchars($_POST['email'] ?? 'E-posta') ?>
                    </div>
                    
                    <div style="margin-top: 10px;">
                        <a href="index.php" class="btn-check-email">
                            <i class="fas fa-sign-in-alt"></i>
                            Giriş Sayfasına Dön
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="form-header">
                    <h1>Şifre Sıfırlama</h1>
                    <p>Hesabınıza kayıtlı e-posta adresini girin, size şifre sıfırlama bağlantısı gönderelim.</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-envelope"></i>
                            E-posta Adresi
                        </label>
                        <div class="input-wrapper">
                            <input type="email" name="email" class="form-input" required 
                                   placeholder="ornek@email.com"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   autocomplete="email">
                            <i class="fas fa-at input-icon"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i>
                        Sıfırlama Bağlantısı Gönder
                    </button>
                </form>
                
                <div class="back-section">
                    <a href="index.php" class="back-link">
                        <i class="fas fa-arrow-left"></i>
                        Giriş Sayfasına Dön
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

