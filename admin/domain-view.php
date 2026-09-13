<?php
/**
 * Admin Panel - Domain Yönetimi (Zen/Bento UI)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';

// Modül Dosyaları
if (file_exists(dirname(__DIR__) . '/modules/registrars/domainnameapi/DomainNameAPI.php')) {
    require_once dirname(__DIR__) . '/modules/registrars/domainnameapi/DomainNameAPI.php';
}

require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$db = Database::getInstance();
$id = (int) ($_GET['id'] ?? 0);
$pageTitle = 'Domain Yönetimi';

// Alan adını çek
$stmt = $db->prepare("
    SELECT d.*, c.first_name, c.last_name, c.email, c.id as client_id, c.company_name
    FROM domains d
    LEFT JOIN clients c ON d.client_id = c.id
    WHERE d.id = ?
");
$stmt->execute([$id]);
$localDomain = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$localDomain) {
    die("Alan adı bulunamadı.");
}

// API İşlemleri (Arka Planda)
// Gerçek projede burada API Class çağrısı yapılır...
// $api = new DomainNameAPI(...);

// POST İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_ns') {
        $ns1 = trim($_POST['ns1'] ?? '');
        $ns2 = trim($_POST['ns2'] ?? '');
        $ns3 = trim($_POST['ns3'] ?? '');
        $ns4 = trim($_POST['ns4'] ?? '');

        $stmt = $db->prepare("UPDATE domains SET ns1=?, ns2=?, ns3=?, ns4=? WHERE id=?");
        $stmt->execute([$ns1, $ns2, $ns3, $ns4, $id]);

        // API Call Here...

        $_SESSION['flash'] = "Nameserver adresleri güncellendi!";
    } elseif ($action === 'update_settings') {
        $status = $_POST['status'];
        $renew = isset($_POST['auto_renew']) ? 1 : 0;
        $db->prepare("UPDATE domains SET status=?, auto_renew=? WHERE id=?")->execute([$status, $renew, $id]);
        $_SESSION['flash'] = "Alan adı ayarları güncellendi.";
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM domains WHERE id=?")->execute([$id]);
        header("Location: domains.php");
        exit;
    }

    header("Location: domain-view.php?id=$id");
    exit;
}

include 'includes/header.php';

// Hesaplamalar
$expiryDate = strtotime($localDomain['expiry_date']);
$daysLeft = ($expiryDate - time()) / 86400;
$daysLeftInt = ceil($daysLeft);

$statusClass = match ($localDomain['status']) {
    'active' => 'success',
    'pending' => 'warning',
    'expired' => 'danger',
    'cancelled' => 'secondary',
    default => 'primary'
};
?>

<!-- Zen UI Styles -->
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --z-bg: #f8fafc;
    --z-card: #ffffff;
    --z-primary: #0f172a;
    --z-accent: #3b82f6;
    --z-text: #334155;
    --z-muted: #94a3b8;
    --z-border: #f1f5f9;
}

body {
    background-color: var(--z-bg);
    font-family: 'Plus Jakarta Sans', sans-serif;
    color: var(--z-text);
}

.zen-container {
    max-width: 1100px;
    margin: 0 auto;
    padding-bottom: 60px;
}

/* Header Area */
.zen-header {
    text-align: center;
    padding: 40px 0;
    margin-bottom: 20px;
}

.domain-lg {
    font-size: 48px;
    font-weight: 800;
    color: var(--z-primary);
    letter-spacing: -1.5px;
    margin-bottom: 10px;
}

.domain-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 14px;
    background: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.dot { width: 8px; height: 8px; border-radius: 50%; }
.dot.success { background: #10b981; }
.dot.warning { background: #f59e0b; }
.dot.danger { background: #ef4444; }
.dot.secondary { background: #64748b; }

/* Bento Grid */
.bento-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    grid-auto-rows: minmax(180px, auto);
    gap: 20px;
}

/* Tiles */
.tile {
    background: var(--z-card);
    border-radius: 24px;
    padding: 25px;
    border: 1px solid var(--z-border);
    transition: transform 0.2s, box-shadow 0.2s;
    overflow: hidden;
    position: relative;
    display: flex;
    flex-direction: column;
}

.tile:hover {
    transform: translateY(-4px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.04);
}

.tile-lg { grid-column: span 2; }
.tile-tall { grid-row: span 2; }

@media (max-width: 900px) {
    .bento-grid { grid-template-columns: 1fr; }
    .tile-lg { grid-column: auto; }
    .tile-tall { grid-row: auto; }
}

.tile-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.tile-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--z-primary);
    display: flex;
    align-items: center;
    gap: 10px;
}

.tile-icon {
    width: 36px; height: 36px;
    background: #f1f5f9;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: var(--z-primary);
}

/* Specific Tile Styles */
.stat-number {
    font-size: 36px;
    font-weight: 800;
    color: var(--z-primary);
    margin-top: auto;
}

.stat-label {
    font-size: 14px;
    color: var(--z-muted);
}

/* NS Form */
.ns-input {
    width: 100%;
    padding: 12px 15px;
    background: #f8fafc;
    border: 1px solid var(--z-border);
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 10px;
    transition: all 0.2s;
}
.ns-input:focus {
    background: white;
    border-color: var(--z-accent);
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Client Info */
.client-avatar-ph {
    width: 60px; height: 60px;
    background: #e2e8f0;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px;
    color: #64748b;
    margin-bottom: 15px;
}

/* Action Bar */
.action-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-top: auto;
}

.btn-zen {
    padding: 12px;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
    text-decoration: none;
}

.btn-zen-primary { background: var(--z-primary); color: white; }
.btn-zen-primary:hover { opacity: 0.9; }

.btn-zen-light { background: #f1f5f9; color: var(--z-text); }
.btn-zen-light:hover { background: #e2e8f0; }

.btn-zen-danger { background: #fef2f2; color: #ef4444; }
.btn-zen-danger:hover { background: #fee2e2; }

/* Switch */
.z-switch {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px;
    background: #f8fafc;
    border-radius: 14px;
    margin-bottom: 10px;
}
</style>

<div class="zen-container">
    
    <?php if (isset($_SESSION['flash'])): ?>
            <div class="alert alert-success text-center border-0 shadow-sm mb-4" style="border-radius: 50px; background: #dcfce7; color: #166534;">
                <?= $_SESSION['flash'] ?>
            </div>
            <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <!-- HEADER -->
    <div class="zen-header">
        <div class="domain-lg"><?= htmlspecialchars($localDomain['domain']) ?></div>
        <div class="domain-status-pill">
            <span class="dot <?= $statusClass ?>"></span>
            <span style="text-transform: uppercase;"><?= $localDomain['status'] ?: 'Bilinmiyor' ?></span>
            <span style="color: #cbd5e1; margin: 0 5px;">|</span>
            <span>ID: #<?= $localDomain['id'] ?></span>
        </div>
        <div class="mt-3">
            <a href="https://<?= htmlspecialchars($localDomain['domain']) ?>" target="_blank" class="btn btn-sm btn-light rounded-pill px-3">
                <i class="fas fa-external-link-alt small me-2"></i> Siteyi Aç
            </a>
        </div>
    </div>

    <!-- BENTO GRID -->
    <div class="bento-grid">
        
        <!-- 1. Süre Bilgisi (Küçük) -->
        <div class="tile" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white;">
            <div class="tile-header mb-0">
                <div class="tile-title" style="color: white; opacity: 0.8;">
                    <i class="far fa-clock"></i> Kalan Süre
                </div>
            </div>
            <div class="stat-number" style="color: white;"><?= $daysLeftInt ?> <span style="font-size: 16px; opacity: 0.6;">Gün</span></div>
            <div class="mt-2 text-white-50 small">
                Bitiş: <?= date('d.m.Y', $expiryDate) ?>
            </div>
            <div class="progress mt-3" style="height: 4px; background: rgba(255,255,255,0.1);">
                <div class="progress-bar bg-info" style="width: <?= max(0, min(100, $daysLeftInt / 3.65)) ?>%"></div>
            </div>
        </div>

        <!-- 2. Müşteri (Küçük) -->
        <div class="tile text-center align-items-center justify-content-center">
            <div class="client-avatar-ph">
                <?= strtoupper(substr($localDomain['first_name'], 0, 1)) ?>
            </div>
            <h4 class="m-0 fw-bold text-dark"><?= htmlspecialchars($localDomain['first_name'] . ' ' . $localDomain['last_name']) ?></h4>
            <div class="text-muted small mb-3"><?= htmlspecialchars($localDomain['email']) ?></div>
            <a href="client-view.php?id=<?= $localDomain['client_id'] ?>" class="btn-zen btn-zen-light btn-sm w-50 rounded-pill">Profili Gör</a>
        </div>

        <!-- 3. Ayarlar / Durum (Uzun) -->
        <div class="tile tile-tall">
            <div class="tile-header">
                <div class="tile-title">
                    <div class="tile-icon"><i class="fas fa-sliders-h"></i></div>
                    Ayarlar
                </div>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_settings">
                
                <div class="mb-3">
                    <label class="small fw-bold text-muted mb-2 d-block">DURUM</label>
                    <select name="status" class="ns-input">
                        <option value="active" <?= $localDomain['status'] == 'active' ? 'selected' : '' ?>>Aktif</option>
                        <option value="pending" <?= $localDomain['status'] == 'pending' ? 'selected' : '' ?>>Beklemede</option>
                        <option value="expired" <?= $localDomain['status'] == 'expired' ? 'selected' : '' ?>>Süresi Dolmuş</option>
                        <option value="cancelled" <?= $localDomain['status'] == 'cancelled' ? 'selected' : '' ?>>İptal</option>
                    </select>
                </div>

                <div class="z-switch">
                    <div>
                        <strong>Otomatik Yenileme</strong><br>
                        <small class="text-muted">Faturayı otomatik oluştur</small>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="auto_renew" value="1" <?= $localDomain['auto_renew'] ? 'checked' : '' ?>>
                    </div>
                </div>

                <div class="z-switch">
                    <div>
                        <strong>Transfer Kilidi</strong><br>
                        <small class="text-muted">İzinsiz transferi engelle</small>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" checked disabled title="API Bağlantısı Gereklidir">
                    </div>
                </div>

                <button type="submit" class="btn-zen btn-zen-primary w-100 mt-auto">Kaydet</button>
            </form>

            <form method="POST" onsubmit="return confirm('Silmek istediğinize emin misiniz?')" class="mt-3">
                <input type="hidden" name="action" value="delete">
                <button class="btn-zen btn-zen-danger w-100">Alan Adını Sil</button>
            </form>
        </div>

        <!-- 4. Nameservers (Geniş) -->
        <div class="tile tile-lg">
            <div class="tile-header">
                <div class="tile-title">
                    <div class="tile-icon"><i class="fas fa-server"></i></div>
                    Nameservers
                </div>
                <span class="badge bg-light text-dark">Canlı Güncelleme</span>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_ns">
                <div class="row">
                    <div class="col-md-6">
                        <input type="text" name="ns1" class="ns-input" value="<?= htmlspecialchars($localDomain['ns1'] ?? '') ?>" placeholder="NS1">
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="ns2" class="ns-input" value="<?= htmlspecialchars($localDomain['ns2'] ?? '') ?>" placeholder="NS2">
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="ns3" class="ns-input" value="<?= htmlspecialchars($localDomain['ns3'] ?? '') ?>" placeholder="NS3">
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="ns4" class="ns-input" value="<?= htmlspecialchars($localDomain['ns4'] ?? '') ?>" placeholder="NS4">
                    </div>
                </div>
                <div class="text-end mt-3">
                    <button type="submit" class="btn-zen btn-zen-primary px-4">NS Güncelle</button>
                </div>
            </form>
        </div>

        <!-- 5. EPP Kodu -->
        <div class="tile">
            <div class="tile-header">
                <div class="tile-title">
                    <div class="tile-icon"><i class="fas fa-key"></i></div>
                    EPP Kodu
                </div>
            </div>
            <div style="background: #1e293b; color: #22d3ee; padding: 15px; border-radius: 12px; font-family: monospace; text-align: center; cursor: pointer;" onclick="navigator.clipboard.writeText(this.innerText); alert('Kopyalandı!')">
                <?= !empty($localDomain['epp_code']) ? htmlspecialchars($localDomain['epp_code']) : '******' ?>
            </div>
            <small class="text-center d-block text-muted mt-2">Görmek için tıklayın</small>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>