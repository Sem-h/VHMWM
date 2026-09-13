<?php
/**
 * WHMVM Release Builder
 * Hosting kurulumu için hazır ZIP paketi oluşturur.
 */

declare(strict_types=1);
set_time_limit(0); // Büyük dosyalar için süre sınırını kaldır
ini_set('memory_limit', '512M');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Kurulum Paketi Oluşturucu';
$message = '';
$downloadFile = '';

// YEDEKLEME VE ZIPLEME FONKSİYONLARI
class ReleaseBuilder
{
    private $pdo;
    private $excludeFiles = [
        '.git',
        '.gitignore',
        '.vscode',
        '.idea', // Dev dosyaları
        'generate_image',
        'images', // Gereksiz medya klasörleri (varsa)
        'WHMVM_Installer.zip', // Kendisini ziplemesin
        'config/config.php', // Hosting'de kurulum tetiklensin diye bunu hariç tutacağız
        'admin/builder.php' // İsteğe bağlı, builder scriptini pakete koymayabiliriz
    ];

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // 1. Veritabanını Export Et -> install/whmvm_full.sql
    public function dumpDatabase($outputFile)
    {
        $tables = [];
        $query = $this->pdo->query('SHOW TABLES');
        while ($row = $query->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $sql = "SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\nSET time_zone = \"+00:00\";\n\n";

        foreach ($tables as $table) {
            // Drop Table Ekle
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";

            // Şema
            $row2 = $this->pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);
            $sql .= "\n" . $row2[1] . ";\n\n";

            // Veriler
            $query3 = $this->pdo->query('SELECT * FROM `' . $table . '`');
            while ($row = $query3->fetch(PDO::FETCH_NUM)) {
                $sql .= "INSERT INTO `$table` VALUES(";
                for ($j = 0; $j < count($row); $j++) {
                    if ($row[$j] === null) {
                        $sql .= 'NULL';
                    } elseif ($row[$j] === '') {
                        $sql .= "''";
                    } else {
                        $sql .= '0x' . bin2hex((string) $row[$j]);
                    }

                    if ($j < (count($row) - 1)) {
                        $sql .= ',';
                    }
                }
                $sql .= ");\n";
            }
        }
        $sql .= "\n\nSET FOREIGN_KEY_CHECKS=1;";

        return file_put_contents($outputFile, $sql);
    }

    // 2. Dosyaları Ziple
    public function zipProject($zipName)
    {
        $rootPath = dirname(__DIR__);
        $zip = new ZipArchive();

        if ($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            return false;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            // Skip directories (they would be added automatically)
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();

                // Path Normalization (Windows Fix)
                $filePathNormalized = str_replace('\\', '/', $filePath);
                $rootPathNormalized = str_replace('\\', '/', $rootPath);

                // Relative path hesapla
                // Eğer hata olursa diye strlen kontrolü
                if (strpos($filePathNormalized, $rootPathNormalized) === 0) {
                    $relativePath = substr($filePathNormalized, strlen($rootPathNormalized) + 1);
                } else {
                    $relativePath = $file->getFilename(); // Fallback
                }

                // Exclude checks
                $exclude = false;
                foreach ($this->excludeFiles as $ex) {
                    // Hem relative path hem full path kontrolü
                    if (strpos($relativePath, $ex) === 0 || strpos($filePathNormalized, $ex) !== false) {
                        $exclude = true;
                        break;
                    }
                }

                // Ayrıca __MACOSX ve .DS_Store vb.
                if (basename($filePath) == '.DS_Store' || strpos($relativePath, '.git') !== false)
                    $exclude = true;

                if (!$exclude) {
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }

        // Custom: Boş bir config/index.html ekle (klasör boş kalmasın diye)
        $zip->addFromString('config/index.html', '');

        $zip->close();
        return file_exists($zipName);
    }
}

// İŞLEM BAŞLAT
if (isset($_POST['build'])) {
    $builder = new ReleaseBuilder();

    // 1. SQL Dump
    $sqlPath = dirname(__DIR__) . '/install/whmvm_full.sql';
    $bytes = $builder->dumpDatabase($sqlPath);

    if ($bytes) {
        $message .= "<div class='alert alert-success'>✅ Veritabanı yedeği alındı ($bytes bytes) -> install/whmvm_full.sql</div>";

        // 2. ZIP Oluştur
        $zipPath = dirname(__DIR__) . '/WHMVM_Installer.zip';
        if ($builder->zipProject($zipPath)) {
            $message .= "<div class='alert alert-success'>✅ ZIP Paketi oluşturuldu!</div>";
            $downloadFile = '../WHMVM_Installer.zip';
        } else {
            $message .= "<div class='alert alert-danger'>❌ ZIP oluşturulamadı. Yazma izinlerini kontrol edin.</div>";
        }
    } else {
        $message .= "<div class='alert alert-danger'>❌ Veritabanı yedeği alınamadı!</div>";
    }
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-box-open"></i> Kurulum Paketi (Release) Oluşturucu</h3>
            </div>
            <div class="card-body text-center py-5">

                <?php if ($message)
                    echo $message; ?>

                <div class="mb-4">
                    <img src="https://cdni.iconscout.com/illustration/premium/thumb/web-deployment-4488814-3765664.png"
                        alt="Deploy" style="width: 200px;">
                </div>

                <h4>Hosting Kurulum Paketi Hazırla</h4>
                <p class="text-muted mb-4">
                    Bu işlem sistemdeki <strong>tüm veritabanını</strong> <code>install/</code> klasörüne yedekler ve
                    projeyi
                    kuruluma hazır bir <strong>ZIP</strong> dosyası haline getirir.<br>
                    Oluşan ZIP dosyasının içinde <code>config.php</code> <u>yer almaz</u>, böylece hostinge
                    yüklediğinizde
                    otomatik olarak kurulum sihirbazı başlar.
                </p>

                <?php if ($downloadFile): ?>
                    <a href="<?= $downloadFile ?>" class="btn btn-success btn-lg mb-3">
                        <i class="fas fa-download"></i> İNDİR (WHMVM_Installer.zip)
                    </a>
                    <br>
                    <a href="builder.php" class="btn btn-outline-secondary btn-sm">Bölümü Sıfırla</a>
                <?php else: ?>
                    <form method="POST">
                        <button type="submit" name="build" class="btn btn-primary btn-lg">
                            <i class="fas fa-cogs"></i> Paketi Hazırla ve İndir
                        </button>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>