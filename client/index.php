<?php
/**
 * WHMVM - Müşteri Panel Giriş
 * Verimek Theme Style
 */

declare(strict_types=1);

if (!file_exists(dirname(__DIR__) . '/config/config.php')) {
    header('Location: ../install/install.php');
    exit;
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/ClientLog.php';

// Site logosu
$siteLogo = Settings::getLogo();

session_name(SESSION_NAME);
session_start();

// Zaten giriş yapmışsa dashboard'a yönlendir
if (isset($_SESSION['client_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Login işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'E-posta ve şifre gereklidir.';
    } else {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT * FROM clients WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $client = $stmt->fetch();
            
            if ($client && password_verify($password, $client['password'])) {
                $_SESSION['client_id'] = $client['id'];
                $_SESSION['client_email'] = $client['email'];
                $_SESSION['client_name'] = $client['first_name'] . ' ' . $client['last_name'];
                
                // Son giriş güncelle
                $update = $db->prepare("UPDATE clients SET last_login = NOW(), last_ip = ? WHERE id = ?");
                $update->execute([$_SERVER['REMOTE_ADDR'], $client['id']]);
                
                // Giriş logu
                ClientLog::login($client['id'], $client['email']);
                
                header('Location: dashboard.php');
                exit;
            } else {
                // Başarısız giriş logu
                ClientLog::loginFailed($email);
                $error = 'Geçersiz e-posta veya şifre.';
            }
        } catch (Exception $e) {
            $error = 'Bir hata oluştu. Lütfen tekrar deneyin.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Müşteri Girişi - <?= SITE_NAME ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0083ff;
            --primary-dark: #0066cc;
            --secondary: #37394c;
            --success: #27c93f;
            --danger: #ff5f56;
            --dark: #00104b;
            --gray: #6978a0;
            --light: #f3f5f9;
            --border: #e2e7ec;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--light);
            min-height: 100vh;
        }
        
        .login-body {
            display: flex;
            min-height: 100vh;
        }
        
        .form-holder {
            width: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 50px;
            background: #fff;
        }
        
        .form-content {
            width: 100%;
            max-width: 420px;
        }
        
        .logo {
            margin-bottom: 40px;
        }
        
        .logo a {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--dark);
            font-weight: 700;
            font-size: 24px;
        }
        
        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary) 0%, #00bcd4 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 24px;
        }
        
        .logo-img {
            max-height: 70px;
            max-width: 250px;
            object-fit: contain;
        }
        
        .form-content h3 {
            font-size: 28px;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .form-content > p {
            color: var(--gray);
            margin-bottom: 30px;
        }
        
        .form-content > p a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }
        
        .form-content > p a:hover {
            text-decoration: underline;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 16px 18px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(0, 131, 255, 0.1);
        }
        
        .checkbox {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
        }
        
        .checkbox label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--dark);
            cursor: pointer;
        }
        
        .checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }
        
        .forgot {
            color: var(--primary);
            font-size: 14px;
            text-decoration: none;
            font-weight: 500;
        }
        
        .forgot:hover {
            text-decoration: underline;
        }
        
        .btn {
            width: 100%;
            padding: 18px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 131, 255, 0.3);
        }
        
        .btn-outline {
            background: #fff;
            color: var(--primary);
            border: 2px solid var(--primary);
            margin-top: 15px;
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: #fff;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }
        
        .divider {
            text-align: center;
            margin: 30px 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--border);
        }
        
        .divider span {
            background: #fff;
            padding: 0 20px;
            color: var(--gray);
            font-size: 14px;
            position: relative;
        }
        
        .register-box {
            text-align: center;
        }
        
        .register-box p {
            color: var(--gray);
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .img-holder {
            width: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, #00bcd4 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .img-holder::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            top: -200px;
            right: -200px;
        }
        
        .img-holder::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            bottom: -100px;
            left: -100px;
        }
        
        .info-holder {
            position: relative;
            z-index: 1;
            text-align: center;
            color: #fff;
            padding: 40px;
        }
        
        .info-holder h2 {
            font-size: 36px;
            margin-bottom: 20px;
        }
        
        .info-holder p {
            font-size: 18px;
            opacity: 0.9;
            max-width: 400px;
            line-height: 1.7;
        }
        
        .features {
            margin-top: 40px;
            text-align: left;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .feature-icon {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        
        .feature-text h4 {
            font-size: 16px;
            margin-bottom: 4px;
        }
        
        .feature-text p {
            font-size: 14px;
            opacity: 0.8;
            margin: 0;
        }
        
        @media (max-width: 1024px) {
            .img-holder {
                display: none;
            }
            
            .form-holder {
                width: 100%;
            }
        }
        
        @media (max-width: 576px) {
            .form-holder {
                padding: 30px 20px;
            }
            
            .form-content h3 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-body">
        <div class="form-holder">
            <div class="form-content">
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
                
                <h3>Müşteri Girişi</h3>
                <p>Hesabınız yok mu? <a href="register.php">Hemen kayıt olun</a></p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="inputEmail">E-posta Adresi</label>
                        <input type="email" name="email" id="inputEmail" class="form-control" 
                               required autofocus placeholder="ornek@email.com"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="inputPassword">Şifre</label>
                        <input type="password" name="password" id="inputPassword" class="form-control" 
                               required placeholder="••••••••">
                    </div>
                    
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="rememberme"> Beni Hatırla
                        </label>
                        <a href="forgot-password.php" class="forgot">Şifremi Unuttum</a>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-sign-in-alt"></i> Giriş Yap
                    </button>
                </form>
                
                <div class="divider">
                    <span>veya</span>
                </div>
                
                <div class="register-box">
                    <p>Henüz hesabınız yok mu?</p>
                    <a href="register.php" class="btn btn-outline">
                        <i class="fas fa-user-plus"></i> Hesap Oluştur
                    </a>
                </div>
            </div>
        </div>
        
        <div class="img-holder">
            <div class="info-holder">
                <h2>Hoş Geldiniz!</h2>
                <p>Müşteri panelinize giriş yaparak tüm hizmetlerinizi yönetebilir, faturalarınızı görüntüleyebilir ve destek taleplerini takip edebilirsiniz.</p>
                
                <div class="features">
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-server"></i></div>
                        <div class="feature-text">
                            <h4>Hizmet Yönetimi</h4>
                            <p>Tüm hizmetlerinizi tek panelden yönetin</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-file-invoice"></i></div>
                        <div class="feature-text">
                            <h4>Fatura Takibi</h4>
                            <p>Faturalarınızı görüntüleyin ve ödeyin</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-headset"></i></div>
                        <div class="feature-text">
                            <h4>7/24 Destek</h4>
                            <p>Profesyonel teknik destek ekibimiz yanınızda</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
