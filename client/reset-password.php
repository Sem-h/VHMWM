<?php
/**
 * WHMVM - Şifre Sıfırlama
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
$validToken = false;
$tokenData = null;

$token = $_GET['token'] ?? $_POST['token'] ?? '';

// Token kontrolü
if (!empty($token)) {
    try {
        $tokenData = Database::fetch("
            SELECT * FROM password_resets 
            WHERE token = ? AND expires_at > NOW()
        ", [$token]);
        
        if ($tokenData) {
            $validToken = true;
        } else {
            $error = 'Bu bağlantı geçersiz veya süresi dolmuş. Lütfen yeni bir şifre sıfırlama bağlantısı talep edin.';
        }
    } catch (Throwable $e) {
        $error = 'Bir hata oluştu. Lütfen tekrar deneyin.';
    }
} else {
    $error = 'Geçersiz istek. Lütfen e-postanızdaki bağlantıyı kullanın.';
}

// Şifre değiştirme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    if (empty($password)) {
        $error = 'Lütfen yeni şifrenizi girin.';
    } elseif (strlen($password) < 8) {
        $error = 'Şifre en az 8 karakter olmalıdır.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Şifreler eşleşmiyor.';
    } else {
        try {
            // Şifreyi güncelle
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            Database::query("UPDATE clients SET password = ? WHERE email = ?", [$hashedPassword, $tokenData['email']]);
            
            // Token'ı sil
            Database::query("DELETE FROM password_resets WHERE email = ?", [$tokenData['email']]);
            
            // Müşteri bilgilerini al
            $client = Database::fetch("SELECT first_name, last_name FROM clients WHERE email = ?", [$tokenData['email']]);
            
            // Şifre değiştirildi e-postası gönder (opsiyonel, güvenlik için)
            try {
                Mail::send(
                    $tokenData['email'],
                    'Şifreniz Değiştirildi',
                    'Merhaba ' . ($client['first_name'] ?? 'Müşteri') . ',<br><br>' .
                    'Hesabınızın şifresi başarıyla değiştirildi.<br><br>' .
                    'Bu değişikliği siz yapmadıysanız, lütfen hemen bizimle iletişime geçin.<br><br>' .
                    'İyi günler dileriz.',
                    $client['first_name'] ?? 'Müşteri'
                );
            } catch (Throwable $e) {
                // Mail hatası işlemi engellemesin
            }
            
            $success = 'Şifreniz başarıyla değiştirildi! Artık yeni şifrenizle giriş yapabilirsiniz.';
            $validToken = false; // Formu gizle
            
        } catch (Throwable $e) {
            $error = 'Şifre güncellenirken bir hata oluştu. Lütfen tekrar deneyin.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Sıfırla - <?= SITE_NAME ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2474f5;
            --primary-dark: #1b5ed4;
            --success: #10b981;
            --danger: #ef4444;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
            --border: #e2e8f0;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #2474f5 0%, #4b91fa 50%, #4b91fa 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .reset-container {
            width: 100%;
            max-width: 450px;
            background: white;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
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
        
        .icon-box {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin: 0 auto 25px;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-header h1 {
            font-size: 26px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .form-header p {
            color: var(--gray);
            font-size: 14px;
            line-height: 1.6;
        }
        
        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }
        
        .form-group label i {
            color: var(--primary);
            font-size: 12px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 18px;
            background: var(--light);
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 15px;
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
            margin-top: 6px;
            color: var(--gray);
            font-size: 12px;
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary) 0%, #4b91fa 100%);
            color: white;
            border: none;
            border-radius: 12px;
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
        
        .btn-success {
            background: linear-gradient(135deg, var(--success) 0%, #34d399 100%);
        }
        
        .btn-success:hover {
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 25px;
        }
        
        .back-link a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--gray);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 10px 20px;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .back-link a:hover {
            background: var(--light);
            color: var(--primary);
        }
        
        /* Success state */
        .success-state {
            text-align: center;
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
            font-size: 22px;
            color: var(--dark);
            margin-bottom: 12px;
        }
        
        .success-state p {
            color: var(--gray);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        
        /* Error state */
        .error-state {
            text-align: center;
        }
        
        .error-state .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin: 0 auto 20px;
        }
        
        .error-state h2 {
            font-size: 22px;
            color: var(--dark);
            margin-bottom: 12px;
        }
        
        .error-state p {
            color: var(--gray);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        
        @media (max-width: 480px) {
            .reset-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
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
                <h2>Şifre Değiştirildi!</h2>
                <p><?= htmlspecialchars($success) ?></p>
                <a href="index.php" class="btn btn-success">
                    <i class="fas fa-sign-in-alt"></i> Giriş Yap
                </a>
            </div>
        <?php elseif (!$validToken && $error): ?>
            <div class="error-state">
                <div class="icon">⚠️</div>
                <h2>Geçersiz Bağlantı</h2>
                <p><?= htmlspecialchars($error) ?></p>
                <a href="forgot-password.php" class="btn">
                    <i class="fas fa-redo"></i> Yeni Bağlantı Talep Et
                </a>
            </div>
        <?php elseif ($validToken): ?>
            <div class="icon-box">🔐</div>
            
            <div class="form-header">
                <h1>Yeni Şifre Belirle</h1>
                <p>Hesabınız için yeni bir şifre belirleyin. Güvenli bir şifre seçtiğinizden emin olun.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Yeni Şifre</label>
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
                
                <button type="submit" class="btn">
                    <i class="fas fa-check"></i> Şifreyi Değiştir
                </button>
            </form>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="index.php">
                <i class="fas fa-arrow-left"></i> Giriş Sayfasına Dön
            </a>
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
    </script>
</body>
</html>

