<?php
/**
 * WHMVM - Admin Panel Giriş
 * PHP 8.1+
 */

declare(strict_types=1);

// Config kontrolü
if (!file_exists(dirname(__DIR__) . '/config/config.php')) {
    header('Location: ../install/install.php');
    exit;
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

// Zaten giriş yapmışsa dashboard'a yönlendir
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Login işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Kullanıcı adı ve şifre gereklidir.';
    } else {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT * FROM admins WHERE (username = ? OR email = ?) AND is_active = 1");
            $stmt->execute([$username, $username]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password'])) {
                // Giriş başarılı
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['admin_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
                
                // Son giriş güncelle
                $update = $db->prepare("UPDATE admins SET last_login = NOW(), last_ip = ? WHERE id = ?");
                $update->execute([$_SERVER['REMOTE_ADDR'], $admin['id']]);
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Geçersiz kullanıcı adı veya şifre.';
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
    <title>Yönetici Girişi - <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --danger: #ef4444;
            --success: #10b981;
            --dark: #0f172a;
            --darker: #020617;
            --light: #f8fafc;
            --gray: #64748b;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--darker);
            min-height: 100vh;
            display: flex;
            overflow: hidden;
        }
        
        /* Left Panel - Branding */
        .brand-panel {
            flex: 1;
            background: linear-gradient(135deg, var(--dark) 0%, #1e1b4b 50%, var(--darker) 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 60px;
            position: relative;
            overflow: hidden;
        }
        
        .brand-panel::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%);
            top: -200px;
            left: -200px;
            animation: float 15s ease-in-out infinite;
        }
        
        .brand-panel::after {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.1) 0%, transparent 70%);
            bottom: -150px;
            right: -150px;
            animation: float 12s ease-in-out infinite reverse;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, 30px) scale(1.1); }
        }
        
        .brand-content {
            position: relative;
            z-index: 10;
            text-align: center;
            max-width: 500px;
        }
        
        .brand-logo {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 48px;
            box-shadow: 0 20px 60px rgba(99, 102, 241, 0.4);
            animation: pulse-glow 3s ease-in-out infinite;
        }
        
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 20px 60px rgba(99, 102, 241, 0.4); }
            50% { box-shadow: 0 20px 80px rgba(99, 102, 241, 0.6); }
        }
        
        .brand-title {
            font-size: 42px;
            font-weight: 800;
            color: white;
            margin-bottom: 15px;
            letter-spacing: -1px;
        }
        
        .brand-title span {
            background: linear-gradient(135deg, var(--primary-light) 0%, #c084fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .brand-desc {
            font-size: 18px;
            color: rgba(255,255,255,0.6);
            line-height: 1.7;
            margin-bottom: 50px;
        }
        
        .brand-features {
            display: flex;
            flex-direction: column;
            gap: 20px;
            text-align: left;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .feature-item:hover {
            background: rgba(255,255,255,0.05);
            border-color: rgba(99, 102, 241, 0.3);
            transform: translateX(5px);
        }
        
        .feature-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .feature-text h4 {
            color: white;
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 3px;
        }
        
        .feature-text p {
            color: rgba(255,255,255,0.5);
            font-size: 13px;
        }
        
        /* Right Panel - Login */
        .login-panel {
            width: 520px;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px;
            position: relative;
        }
        
        .login-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(180deg, var(--primary) 0%, #8b5cf6 50%, #c084fc 100%);
        }
        
        .login-header {
            margin-bottom: 40px;
        }
        
        .login-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .login-badge::before {
            content: '';
            width: 8px;
            height: 8px;
            background: var(--primary);
            border-radius: 50%;
            animation: blink 2s ease-in-out infinite;
        }
        
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        
        .login-header h1 {
            font-size: 32px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }
        
        .login-header p {
            font-size: 15px;
            color: var(--gray);
        }
        
        .error-message {
            display: flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, #fef2f2 0%, #fff5f5 100%);
            color: var(--danger);
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid #fecaca;
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .error-message::before {
            content: '⚠️';
            font-size: 18px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }
        
        .form-group label span {
            font-size: 16px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper input {
            width: 100%;
            padding: 16px 20px;
            padding-left: 50px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.3s;
            background: #f8fafc;
        }
        
        .input-wrapper input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        
        .input-wrapper .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            color: var(--gray);
            transition: color 0.3s;
        }
        
        .input-wrapper input:focus + .input-icon,
        .input-wrapper input:not(:placeholder-shown) + .input-icon {
            color: var(--primary);
        }
        
        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 18px;
            color: var(--gray);
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .password-toggle:hover {
            color: var(--primary);
        }
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        
        .remember-me input {
            display: none;
        }
        
        .remember-me .checkbox {
            width: 20px;
            height: 20px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .remember-me input:checked + .checkbox {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .remember-me .checkbox::after {
            content: '✓';
            color: white;
            font-size: 12px;
            font-weight: bold;
            opacity: 0;
            transform: scale(0);
            transition: all 0.2s;
        }
        
        .remember-me input:checked + .checkbox::after {
            opacity: 1;
            transform: scale(1);
        }
        
        .remember-me span {
            font-size: 14px;
            color: var(--gray);
        }
        
        .forgot-link {
            font-size: 14px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .forgot-link:hover {
            color: var(--primary-dark);
        }
        
        .btn-login {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(99, 102, 241, 0.4);
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .btn-login:active {
            transform: translateY(-1px);
        }
        
        .divider {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 30px 0;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }
        
        .divider span {
            font-size: 13px;
            color: var(--gray);
        }
        
        .back-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: var(--gray);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 14px;
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .back-link:hover {
            background: #f1f5f9;
            color: var(--primary);
        }
        
        /* Responsive */
        @media (max-width: 1100px) {
            .brand-panel {
                display: none;
            }
            
            .login-panel {
                width: 100%;
                max-width: 100%;
            }
        }
        
        @media (max-width: 576px) {
            .login-panel {
                padding: 40px 25px;
            }
            
            .login-header h1 {
                font-size: 26px;
            }
        }
    </style>
</head>
<body>
    <!-- Brand Panel -->
    <div class="brand-panel">
        <div class="brand-content">
            <div class="brand-logo">🚀</div>
            <h1 class="brand-title">WHMVM <span>Panel</span></h1>
            <p class="brand-desc">Güçlü ve modern web hosting yönetim paneli. Müşterilerinizi, siparişleri ve hizmetleri tek bir yerden yönetin.</p>
            
            <div class="brand-features">
                <div class="feature-item">
                    <div class="feature-icon">📊</div>
                    <div class="feature-text">
                        <h4>Gerçek Zamanlı Dashboard</h4>
                        <p>Anlık istatistikler ve grafikler</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">🔒</div>
                    <div class="feature-text">
                        <h4>Güvenli Altyapı</h4>
                        <p>2FA ve gelişmiş güvenlik önlemleri</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">⚡</div>
                    <div class="feature-text">
                        <h4>Hızlı Entegrasyonlar</h4>
                        <p>API desteği ile kolay bağlantılar</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Login Panel -->
    <div class="login-panel">
        <div class="login-header">
            <div class="login-badge">Admin Panel</div>
            <h1>Hoş Geldiniz! 👋</h1>
            <p>Yönetim paneline erişmek için giriş yapın.</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label><span>👤</span> Kullanıcı Adı veya E-posta</label>
                <div class="input-wrapper">
                    <input type="text" name="username" placeholder="admin@verimekproje.com" required autofocus>
                    <span class="input-icon">📧</span>
                </div>
            </div>
            
            <div class="form-group">
                <label><span>🔑</span> Şifre</label>
                <div class="input-wrapper">
                    <input type="password" name="password" id="password" placeholder="••••••••" required>
                    <span class="input-icon">🔒</span>
                    <button type="button" class="password-toggle" onclick="togglePassword()">👁️</button>
                </div>
            </div>
            
            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember">
                    <span class="checkbox"></span>
                    <span>Beni hatırla</span>
                </label>
                <a href="#" class="forgot-link">Şifremi unuttum?</a>
            </div>
            
            <button type="submit" class="btn-login">
                <span>Giriş Yap</span>
                <span>→</span>
            </button>
        </form>
        
        <div class="divider">
            <span>veya</span>
        </div>
        
        <a href="../" class="back-link">
            <span>←</span>
            <span>Ana Sayfaya Dön</span>
        </a>
    </div>
    
    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const btn = document.querySelector('.password-toggle');
            
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = '🙈';
            } else {
                input.type = 'password';
                btn.textContent = '👁️';
            }
        }
        
        // Input animations
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>
