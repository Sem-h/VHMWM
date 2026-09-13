<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Alan Adları';
$currentPage = 'domains';
$db = Database::getInstance();

$view = $_GET['view'] ?? 'local';
$message = '';
$error = '';

/**
 * Domain İçe Aktarma İşlemi (API Listesinden)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import') {
    $domainName = $_POST['domain_name'] ?? '';
    $clientId = (int) ($_POST['client_id'] ?? 0);
    $expiryDate = $_POST['expiry_date'] ?? null;
    $regDate = $_POST['reg_date'] ?? date('Y-m-d');

    if ($domainName && $clientId > 0) {
        $exists = Database::fetch("SELECT id FROM domains WHERE domain = ?", [$domainName]);
        if (!$exists) {
            Database::insert("domains", [
                "client_id" => $clientId,
                "domain" => $domainName,
                "registration_date" => $regDate,
                "expiry_date" => $expiryDate,
                "status" => 'active',
                "auto_renew" => 1
            ]);
            $message = "$domainName başarıyla içe aktarıldı ve eşleştirildi.";
        } else {
            $error = "$domainName zaten sistemde kayıtlı.";
        }
    } else {
        $error = "Lütfen bir müşteri seçin.";
    }
}

/**
 * LOCAL LİSTE VERİLERİ
 */
if ($view === 'local') {
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;

    $statusFilter = $_GET['status'] ?? '';
    $where = $statusFilter ? "WHERE d.status = '$statusFilter'" : "";

    $total = (int) $db->query("SELECT COUNT(*) FROM domains d $where")->fetchColumn();
    $totalPages = ceil($total / $perPage);

    $domains = $db->query("
        SELECT d.*, c.first_name, c.last_name, c.email 
        FROM domains d 
        LEFT JOIN clients c ON d.client_id = c.id 
        $where
        ORDER BY d.expiry_date ASC 
        LIMIT $perPage OFFSET $offset
    ")->fetchAll();
}

/**
 * API LİSTE VERİLERİ
 */
$apiDomains = [];
$clients = [];
if ($view === 'api') {
    // Müşteri listesini çek (Dropdown için)
    $clients = Database::fetchAll("SELECT id, first_name, last_name, email, company_name FROM clients ORDER BY first_name ASC");

    // API Ayarlarını Çek
    $settings = Database::fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_group = 'integrations'");
    $config = [];
    foreach ($settings as $s) {
        $config[$s['setting_key']] = $s['setting_value'];
    }

    // DomainNameAPI Aktif mi?
    if (($config['domainname_active'] ?? '0') === '1') {
        try {
            require_once dirname(__DIR__) . '/modules/registrars/domainnameapi/DomainNameAPI.php';

            $api = new DomainNameAPI(
                $config['domainname_username'] ?? '',
                $config['domainname_password'] ?? '',
                ($config['domainname_test_mode'] ?? '0') === '1'
            );

            // Domainleri çek (İlk 100)
            $response = $api->GetList(['PageSize' => 100, 'PageNumber' => 0]);

            if (isset($response['result']) && $response['result'] === 'OK') {
                $apiData = $response['data']['Domains'] ?? [];
                // API yapısı bazen değişebilir, kontrol et
                if (empty($apiData) && isset($response['data'][0])) {
                    $apiData = $response['data'];
                }
                $apiDomains = $apiData;
            } else {
                $error = "API Hatası: " . ($response['error']['Message'] ?? 'Bilinmeyen Hata');
            }

        } catch (Exception $e) {
            $error = "Bağlantı Hatası: " . $e->getMessage();
        }
    } else {
        $error = "DomainNameAPI entegrasyonu aktif değil. Lütfen ayarlardan aktif edin.";
    }
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>🌐 Alan Adı Yönetimi</h2>
    <div class="btn-group">
        <a href="?view=local" class="btn <?= $view === 'local' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            📂 Yerel Liste
        </a>
        <a href="?view=api" class="btn <?= $view === 'api' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            ☁️ API Listesi (DomainName)
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?= $message ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<?php if ($view === 'api'): ?>
    <!-- API VIEW -->
    <div class="card">
        <div class="card-header">
            <h3>API Üzerindeki Alan Adları</h3>
            <small>DomainNameAPI hesabınızdaki ilk 100 alan adı listelenmektedir.</small>
        </div>
        <div class="card-body">
            <?php if (empty($apiDomains)): ?>
                <div class="empty-state">
                    <p>API'den alan adı bulunamadı veya bağlantı hatası.</p>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>Bitiş Tarihi</th>
                            <th>Durum</th>
                            <th>Sistem Durumu</th>
                            <th width="300">İçe Aktar / Eşleştir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($apiDomains as $domain): ?>
                            <?php
                            // Yerel veritabanında var mı?
                            $inDb = Database::fetch("SELECT id, client_id FROM domains WHERE domain = ?", [$domain['DomainName']]);

                            // Tarih formatı
                            $expiry = isset($domain['Dates']['Expiration']) ? date('Y-m-d', strtotime($domain['Dates']['Expiration'])) : '';
                            $regDate = isset($domain['Dates']['Start']) ? date('Y-m-d', strtotime($domain['Dates']['Start'])) : date('Y-m-d');
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($domain['DomainName']) ?></strong></td>
                                <td><?= $expiry ? date('d.m.Y', strtotime($expiry)) : '-' ?></td>
                                <td><span class="badge badge-info"><?= $domain['Status'] ?></span></td>
                                <td>
                                    <?php if ($inDb): ?>
                                        <span class="badge badge-success">✓ Eşleşti</span>
                                        <small>(Müşteri ID: <?= $inDb['client_id'] ?>)</small>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Eşleşmedi</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$inDb): ?>
                                        <form method="POST" class="d-flex gap-2">
                                            <input type="hidden" name="action" value="import">
                                            <input type="hidden" name="domain_name"
                                                value="<?= htmlspecialchars($domain['DomainName']) ?>">
                                            <input type="hidden" name="expiry_date" value="<?= $expiry ?>">
                                            <input type="hidden" name="reg_date" value="<?= $regDate ?>">

                                            <select name="client_id" class="form-control form-control-sm" required>
                                                <option value="">- Müşteri Seç -</option>
                                                <?php foreach ($clients as $client): ?>
                                                    <option value="<?= $client['id'] ?>">
                                                        <?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?>
                                                        (<?= $client['company_name'] ?: 'Bireysel' ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-success">Ekle</button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled>Kayıtlı</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- LOCAL VIEW (Mevcut kod) -->
    <div class="card">
        <div class="card-header">
            <h3>🌐 Sistemdeki Alan Adları (<?= $total ?>)</h3>
            <select onchange="window.location='?status='+this.value" class="form-control" style="width: auto;">
                <option value="">Tüm Durumlar</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Aktif</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Beklemede</option>
                <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Süresi Dolmuş</option>
            </select>
        </div>
        <div class="card-body">
            <?php if (empty($domains)): ?>
                <div class="empty-state">
                    <div class="icon">🌐</div>
                    <h3>Henüz alan adı yok</h3>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>Müşteri</th>
                            <th>Kayıt Tarihi</th>
                            <th>Bitiş Tarihi</th>
                            <th>Otomatik Yenile</th>
                            <th>Durum</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($domains as $domain): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($domain['domain']) ?></strong></td>
                                <td>
                                    <?= htmlspecialchars(($domain['first_name'] ?? '') . ' ' . ($domain['last_name'] ?? '')) ?>
                                </td>
                                <td><?= $domain['registration_date'] ? date('d.m.Y', strtotime($domain['registration_date'])) : '-' ?>
                                </td>
                                <td>
                                    <?php if ($domain['expiry_date']): ?>
                                        <?= date('d.m.Y', strtotime($domain['expiry_date'])) ?>
                                        <?php
                                        $daysLeft = (strtotime($domain['expiry_date']) - time()) / 86400;
                                        if ($daysLeft < 0): ?>
                                            <span class="badge badge-danger">Süresi Dolmuş</span>
                                        <?php elseif ($daysLeft < 30): ?>
                                            <span class="badge badge-warning"><?= ceil($daysLeft) ?> gün</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($domain['auto_renew']): ?>
                                        <span class="badge badge-success">Evet</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">Hayır</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusBadge = match ($domain['status']) {
                                        'active' => 'success',
                                        'pending' => 'warning',
                                        'expired' => 'danger',
                                        default => 'gray'
                                    };
                                    $statusText = match ($domain['status']) {
                                        'active' => 'Aktif',
                                        'pending' => 'Beklemede',
                                        'pending_transfer' => 'Transfer Bekliyor',
                                        'expired' => 'Süresi Dolmuş',
                                        'cancelled' => 'İptal',
                                        default => $domain['status']
                                    };
                                    ?>
                                    <span class="badge badge-<?= $statusBadge ?>"><?= $statusText ?></span>
                                </td>
                                <td class="actions">
                                    <a href="domain-view.php?id=<?= $domain['id'] ?>" class="btn btn-sm btn-primary">Görüntüle</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?= $i ?>&status=<?= $statusFilter ?>&view=local"
                                class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>