<?php
/**
 * WHMVM - Otomatik Kurulum Sihirbazı
 * SQL dosyasından veritabanı import eder
 */

declare(strict_types=1);
session_start();
error_reporting(E_ALL);
ini_set('display_errors', '1');

class Installer
{
    private array $errors = [];
    private array $success = [];
    private ?PDO $pdo = null;

    public function checkRequirements(): array
    {
        return [
            'php_version' => ['name' => 'PHP 8.1+', 'status' => version_compare(PHP_VERSION, '8.1.0', '>='), 'current' => PHP_VERSION],
            'pdo_mysql' => ['name' => 'PDO MySQL', 'status' => extension_loaded('pdo_mysql'), 'current' => extension_loaded('pdo_mysql') ? 'Aktif' : 'Pasif'],
            'mbstring' => ['name' => 'Mbstring', 'status' => extension_loaded('mbstring'), 'current' => extension_loaded('mbstring') ? 'Aktif' : 'Pasif'],
            'curl' => ['name' => 'cURL', 'status' => extension_loaded('curl'), 'current' => extension_loaded('curl') ? 'Aktif' : 'Pasif'],
            'config_writable' => ['name' => 'Config Yazılabilir', 'status' => is_writable(dirname(__DIR__) . '/config'), 'current' => is_writable(dirname(__DIR__) . '/config') ? 'Evet' : 'Hayır'],
            'sql_exists' => ['name' => 'SQL Dosyası', 'status' => file_exists(__DIR__ . '/whmvm_full.sql'), 'current' => file_exists(__DIR__ . '/whmvm_full.sql') ? 'Mevcut' : 'Eksik']
        ];
    }

    public function testConnection(string $host, int $port, string $user, string $pass): bool
    {
        try {
            $this->pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            return true;
        } catch (PDOException $e) {
            $this->errors[] = 'Bağlantı hatası: ' . $e->getMessage();
            return false;
        }
    }

    public function createDatabase(string $dbName): bool
    {
        try {
            $this->pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->pdo->exec("USE `$dbName`");
            $this->success[] = "Veritabanı '$dbName' oluşturuldu.";
            return true;
        } catch (PDOException $e) {
            $this->errors[] = 'DB oluşturma hatası: ' . $e->getMessage();
            return false;
        }
    }

    public function importSQL(string $sqlFile): bool
    {
        try {
            $sql = file_get_contents($sqlFile);
            if (!$sql) {
                $this->errors[] = 'SQL dosyası okunamadı: ' . basename($sqlFile);
                return false;
            }

            // SQL dosyasını parçalara ayır ve çalıştır
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
            $this->pdo->exec($sql);
            $this->success[] = basename($sqlFile) . " import edildi.";
            return true;
        } catch (PDOException $e) {
            $this->errors[] = 'SQL import hatası (' . basename($sqlFile) . '): ' . $e->getMessage();
            return false;
        }
    }

    public function importAllSQL(): bool
    {
        // Ana veritabanı dosyası önce import edilmeli
        $mainFile = __DIR__ . '/whmvm_full.sql';
        if (file_exists($mainFile)) {
            if (!$this->importSQL($mainFile)) {
                return false;
            }
        }

        // Ek SQL dosyaları
        $additionalFiles = [
            'schema.sql',
            'affiliate_tables.sql',
            'email_tables.sql',
            'esxi_tables.sql',
            'config_options_tables.sql',
            'product_columns.sql',
            'references_table.sql',
            'clients_data.sql'
        ];

        foreach ($additionalFiles as $file) {
            $path = __DIR__ . '/' . $file;
            if (file_exists($path)) {
                // Hata olsa bile devam et (tablo zaten var olabilir)
                try {
                    $this->importSQL($path);
                } catch (Exception $e) {
                    // Sessizce devam et
                }
            }
        }

        return true;
    }

    public function createExtraTables(): void
    {
        try {
            // Menu Items
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `menu_items` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `menu_type` enum('main','footer','social') NOT NULL DEFAULT 'main',
                `parent_id` int(10) unsigned DEFAULT NULL,
                `title` varchar(255) NOT NULL,
                `url` varchar(500) NOT NULL,
                `icon` varchar(100) DEFAULT NULL,
                `description` text DEFAULT NULL,
                `target` enum('_self','_blank') DEFAULT '_self',
                `sort_order` int(11) DEFAULT 0,
                `is_active` tinyint(1) DEFAULT 1,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `idx_menu_type` (`menu_type`),
                KEY `idx_parent` (`parent_id`),
                KEY `idx_order` (`sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // Proposals
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `proposals` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `client_id` int(11) NOT NULL,
                `subject` varchar(255) NOT NULL,
                `status` enum('Draft','Sent','Accepted','Rejected','Expired') DEFAULT 'Draft',
                `total_amount` decimal(10,2) DEFAULT 0.00,
                `currency` varchar(3) DEFAULT 'TRY',
                `created_at` datetime DEFAULT current_timestamp(),
                `valid_until` date DEFAULT NULL,
                `admin_notes` text DEFAULT NULL,
                `client_notes` text DEFAULT NULL,
                `pdf_path` varchar(255) DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // Proposal Items
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `proposal_items` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `proposal_id` int(11) NOT NULL,
                `description` varchar(255) NOT NULL,
                `quantity` int(11) DEFAULT 1,
                `unit_price` decimal(10,2) NOT NULL,
                `amount` decimal(10,2) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `proposal_id` (`proposal_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        } catch (PDOException $e) {
            // Tablo oluşturma hatası önemsiz (Already exists olabilir)
        }
    }

    public function clearAdmins(): bool
    {
        try {
            $this->pdo->exec("DELETE FROM admins");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function createAdmin(string $username, string $email, string $password, string $firstName, string $lastName): bool
    {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("INSERT INTO admins (username, email, password, first_name, last_name, role, is_active) VALUES (?, ?, ?, ?, ?, 'super_admin', 1)");
            $stmt->execute([$username, $email, $hash, $firstName, $lastName]);
            $this->success[] = "Admin oluşturuldu.";
            return true;
        } catch (PDOException $e) {
            $this->errors[] = 'Admin hatası: ' . $e->getMessage();
            return false;
        }
    }

    public function createConfigFile(array $c): bool
    {
        $key = bin2hex(random_bytes(16));
        $content = "<?php
// WHMVM Config - " . date('Y-m-d H:i:s') . "
define('DB_HOST', '{$c['db_host']}');
define('DB_PORT', {$c['db_port']});
define('DB_NAME', '{$c['db_name']}');
define('DB_USER', '{$c['db_user']}');
define('DB_PASS', '{$c['db_pass']}');
define('DB_CHARSET', 'utf8mb4');

\$protocol = (isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
\$ipConfig = @include __DIR__ . '/ip-config.php';
\$fallbackHost = \$ipConfig['external_ip'] ?? 'localhost:3005';
\$host = \$_SERVER['HTTP_HOST'] ?? \$fallbackHost;
define('SITE_URL', \$protocol . '://' . \$host);
define('SITE_NAME', '{$c['site_name']}');
define('SITE_LANG', 'tr');
define('ENCRYPTION_KEY', '$key');
define('SESSION_NAME', 'WHMVM_SESSION');
date_default_timezone_set('Europe/Istanbul');
define('DEBUG_MODE', false);
define('APP_VERSION', '1.0.0');

if (file_exists(__DIR__ . '/../includes/ModuleLoader.php')) {
    require_once __DIR__ . '/../includes/ModuleLoader.php';
}
";
        return file_put_contents(dirname(__DIR__) . '/config/config.php', $content) !== false;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
    public function getSuccess(): array
    {
        return $this->success;
    }
}

$installer = new Installer();
$step = (int) ($_GET['step'] ?? 1);
$message = '';
$messageType = '';

// POST İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 2) {
        $db = [
            'db_host' => $_POST['db_host'] ?? 'localhost',
            'db_port' => (int) ($_POST['db_port'] ?? 3306),
            'db_name' => $_POST['db_name'] ?? 'whmvm',
            'db_user' => $_POST['db_user'] ?? 'root',
            'db_pass' => $_POST['db_pass'] ?? ''
        ];

        if ($installer->testConnection($db['db_host'], $db['db_port'], $db['db_user'], $db['db_pass'])) {
            file_put_contents(__DIR__ . '/db_tmp.json', json_encode($db));
            header('Location: install.php?step=3');
            exit;
        }
        $message = implode('<br>', $installer->getErrors());
        $messageType = 'error';
    }

    if ($step === 3) {
        $db = file_exists(__DIR__ . '/db_tmp.json') ? json_decode(file_get_contents(__DIR__ . '/db_tmp.json'), true) : null;
        if (!$db) {
            header('Location: install.php?step=2');
            exit;
        }

        if ($installer->testConnection($db['db_host'], $db['db_port'], $db['db_user'], $db['db_pass'])) {
            if ($installer->createDatabase($db['db_name'])) {
                if ($installer->importAllSQL()) {
                    $installer->createExtraTables();
                    header('Location: install.php?step=4');
                    exit;
                }
            }
        }
        $message = implode('<br>', $installer->getErrors());
        $messageType = 'error';
    }

    if ($step === 4) {
        $db = file_exists(__DIR__ . '/db_tmp.json') ? json_decode(file_get_contents(__DIR__ . '/db_tmp.json'), true) : null;
        if (!$db) {
            header('Location: install.php?step=2');
            exit;
        }

        $admin = [
            'user' => trim($_POST['admin_user'] ?? ''),
            'email' => trim($_POST['admin_email'] ?? ''),
            'pass' => $_POST['admin_pass'] ?? '',
            'pass2' => $_POST['admin_pass_confirm'] ?? '',
            'fname' => trim($_POST['admin_first_name'] ?? ''),
            'lname' => trim($_POST['admin_last_name'] ?? ''),
            'site' => trim($_POST['site_name'] ?? 'WHMVM Panel')
        ];

        $errors = [];
        if (empty($admin['user']))
            $errors[] = 'Kullanıcı adı gerekli.';
        if (!filter_var($admin['email'], FILTER_VALIDATE_EMAIL))
            $errors[] = 'Geçerli e-posta gerekli.';
        if (strlen($admin['pass']) < 8)
            $errors[] = 'Şifre en az 8 karakter.';
        if ($admin['pass'] !== $admin['pass2'])
            $errors[] = 'Şifreler eşleşmiyor.';

        if (empty($errors) && $installer->testConnection($db['db_host'], $db['db_port'], $db['db_user'], $db['db_pass'])) {
            $installer->createDatabase($db['db_name']);
            $installer->clearAdmins();

            if ($installer->createAdmin($admin['user'], $admin['email'], $admin['pass'], $admin['fname'], $admin['lname'])) {
                $db['site_name'] = $admin['site'];
                if ($installer->createConfigFile($db)) {
                    @unlink(__DIR__ . '/db_tmp.json');
                    header('Location: install.php?step=5');
                    exit;
                }
            }
        }
        $message = empty($errors) ? implode('<br>', $installer->getErrors()) : implode('<br>', $errors);
        $messageType = 'error';
    }
}

$requirements = $installer->checkRequirements();
$allMet = !in_array(false, array_column($requirements, 'status'));
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WHMVM Kurulum</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 650px;
            width: 100%;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            padding: 25px;
            text-align: center;
        }

        .header h1 {
            color: #fff;
            font-size: 26px;
            margin-bottom: 5px;
        }

        .header p {
            color: #94a3b8;
            font-size: 13px;
        }

        .steps {
            display: flex;
            justify-content: center;
            padding: 15px;
            background: #f1f5f9;
            gap: 8px;
            flex-wrap: wrap;
        }

        .step {
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 12px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .step.active {
            background: #6366f1;
            color: #fff;
        }

        .step.completed {
            background: #10b981;
            color: #fff;
        }

        .step-num {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
        }

        .content {
            padding: 25px;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 13px;
        }

        .message.error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .message.success {
            background: #f0fdf4;
            color: #10b981;
            border: 1px solid #bbf7d0;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #1e293b;
            font-size: 13px;
        }

        .form-group input {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #6366f1;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            background: #6366f1;
            color: #fff;
        }

        .btn:hover {
            background: #4f46e5;
        }

        .btn-success {
            background: #10b981;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 13px;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f8fafc;
            font-weight: 600;
        }

        .ok {
            color: #10b981;
            font-weight: 600;
        }

        .fail {
            color: #ef4444;
            font-weight: 600;
        }

        .success-box {
            text-align: center;
            padding: 30px;
        }

        .success-icon {
            width: 70px;
            height: 70px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 35px;
            color: #fff;
        }

        .warning {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 12px;
            margin-top: 15px;
            font-size: 12px;
            color: #92400e;
        }

        h2 {
            margin-bottom: 15px;
            color: #1e293b;
            font-size: 18px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🚀 WHMVM Kurulum</h1>
            <p>Otomatik Kurulum Sihirbazı</p>
        </div>

        <div class="steps">
            <div class="step <?= $step === 1 ? 'active' : ($step > 1 ? 'completed' : '') ?>"><span
                    class="step-num">1</span> Gereksinimler</div>
            <div class="step <?= $step === 2 ? 'active' : ($step > 2 ? 'completed' : '') ?>"><span
                    class="step-num">2</span> Veritabanı</div>
            <div class="step <?= $step === 3 ? 'active' : ($step > 3 ? 'completed' : '') ?>"><span
                    class="step-num">3</span> Import</div>
            <div class="step <?= $step === 4 ? 'active' : ($step > 4 ? 'completed' : '') ?>"><span
                    class="step-num">4</span> Admin</div>
            <div class="step <?= $step === 5 ? 'active' : '' ?>"><span class="step-num">5</span> Bitti</div>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="message <?= $messageType ?>"><?= $message ?></div><?php endif; ?>

            <?php if ($step === 1): ?>
                <h2>Sistem Gereksinimleri</h2>
                <table>
                    <tr>
                        <th>Gereksinim</th>
                        <th>Durum</th>
                    </tr>
                    <?php foreach ($requirements as $r): ?>
                        <tr>
                            <td><?= $r['name'] ?></td>
                            <td class="<?= $r['status'] ? 'ok' : 'fail' ?>">
                                <?= $r['status'] ? '✓ ' . $r['current'] : '✗ ' . $r['current'] ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php if ($allMet): ?><a href="install.php?step=2" class="btn">Devam Et →</a>
                <?php else: ?>
                    <div class="message error">Tüm gereksinimler karşılanmalı.</div><?php endif; ?>

            <?php elseif ($step === 2): ?>
                <h2>Veritabanı Bilgileri</h2>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group"><label>Sunucu</label><input type="text" name="db_host" value="localhost"
                                required></div>
                        <div class="form-group"><label>Port</label><input type="number" name="db_port" value="3306"
                                required></div>
                    </div>
                    <div class="form-group"><label>Veritabanı Adı</label><input type="text" name="db_name" value="whmvm"
                            required></div>
                    <div class="form-row">
                        <div class="form-group"><label>Kullanıcı</label><input type="text" name="db_user" value="root"
                                required></div>
                        <div class="form-group"><label>Şifre</label><input type="password" name="db_pass"></div>
                    </div>
                    <button type="submit" class="btn">Bağlantıyı Test Et →</button>
                </form>

            <?php elseif ($step === 3): ?>
                <h2>Veritabanı Import</h2>
                <p style="margin-bottom:15px;color:#64748b;font-size:13px;">Tüm tablolar, yapılar ve veriler import
                    edilecek. Bu işlem birkaç dakika sürebilir.
                </p>
                <form method="POST"><button type="submit" class="btn">Veritabanını Kur →</button></form>

            <?php elseif ($step === 4): ?>
                <h2>Admin Hesabı</h2>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group"><label>Ad</label><input type="text" name="admin_first_name" required></div>
                        <div class="form-group"><label>Soyad</label><input type="text" name="admin_last_name" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Kullanıcı Adı</label><input type="text" name="admin_user" required>
                        </div>
                        <div class="form-group"><label>E-posta</label><input type="email" name="admin_email" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Şifre (min 8)</label><input type="password" name="admin_pass"
                                minlength="8" required></div>
                        <div class="form-group"><label>Şifre Tekrar</label><input type="password" name="admin_pass_confirm"
                                required></div>
                    </div>
                    <div class="form-group"><label>Site Adı</label><input type="text" name="site_name" value="WHMVM Panel"
                            required></div>
                    <button type="submit" class="btn">Kurulumu Tamamla →</button>
                </form>

            <?php elseif ($step === 5): ?>
                <div class="success-box">
                    <div class="success-icon">✓</div>
                    <h2 style="color:#10b981;">Kurulum Tamamlandı!</h2>
                    <p style="color:#64748b;margin-bottom:15px;font-size:13px;">WHMVM başarıyla kuruldu.</p>
                    <a href="../admin" class="btn btn-success">Yönetim Paneli →</a>
                    <div class="warning"><strong>⚠️ Güvenlik:</strong> /install klasörünü silin!</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>