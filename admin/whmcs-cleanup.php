<?php
/**
 * WHMVM - WHMCS Aktarım Temizleme
 * Aktarılan verileri temizler
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'WHMCS Veri Temizleme';
$currentPage = 'settings';

$message = '';
$messageType = '';

// Silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $confirm = $_POST['confirm'] ?? '';
    
    if ($confirm !== 'SIL') {
        $message = '❌ Onay kodu yanlış! "SIL" yazmanız gerekiyor.';
        $messageType = 'error';
    } else {
        try {
            switch ($action) {
                case 'delete_all_clients':
                    // Önce ilişkili verileri sil
                    Database::query("DELETE FROM services WHERE client_id > 0");
                    Database::query("DELETE FROM invoices WHERE client_id > 0");
                    Database::query("DELETE FROM tickets WHERE client_id > 0");
                    Database::query("DELETE FROM affiliates WHERE client_id > 0");
                    // Müşterileri sil
                    $count = Database::query("DELETE FROM clients");
                    $message = "✅ Tüm müşteriler ve ilişkili veriler silindi!";
                    $messageType = 'success';
                    break;
                    
                case 'delete_recent_clients':
                    $hours = (int)($_POST['hours'] ?? 24);
                    $date = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
                    
                    // Son X saat içinde eklenen müşterileri bul
                    $clientIds = Database::fetchAll("SELECT id FROM clients WHERE created_at >= ?", [$date]);
                    $ids = array_column($clientIds, 'id');
                    
                    if (!empty($ids)) {
                        $placeholders = implode(',', array_fill(0, count($ids), '?'));
                        Database::query("DELETE FROM services WHERE client_id IN ($placeholders)", $ids);
                        Database::query("DELETE FROM invoices WHERE client_id IN ($placeholders)", $ids);
                        Database::query("DELETE FROM tickets WHERE client_id IN ($placeholders)", $ids);
                        Database::query("DELETE FROM affiliates WHERE client_id IN ($placeholders)", $ids);
                        Database::query("DELETE FROM clients WHERE id IN ($placeholders)", $ids);
                    }
                    
                    $message = "✅ Son {$hours} saat içinde eklenen " . count($ids) . " müşteri silindi!";
                    $messageType = 'success';
                    break;
                    
                case 'delete_all_products':
                    Database::query("DELETE FROM services");
                    Database::query("DELETE FROM products");
                    Database::query("DELETE FROM product_groups");
                    $message = "✅ Tüm ürünler ve gruplar silindi!";
                    $messageType = 'success';
                    break;
                    
                case 'delete_all_invoices':
                    Database::query("DELETE FROM invoices");
                    $message = "✅ Tüm faturalar silindi!";
                    $messageType = 'success';
                    break;
                    
                case 'delete_all_tickets':
                    Database::query("DELETE FROM ticket_replies");
                    Database::query("DELETE FROM tickets");
                    $message = "✅ Tüm destek talepleri silindi!";
                    $messageType = 'success';
                    break;
                    
                case 'delete_all_services':
                    Database::query("DELETE FROM services");
                    $message = "✅ Tüm hizmetler silindi!";
                    $messageType = 'success';
                    break;
            }
        } catch (Exception $e) {
            $message = "❌ Hata: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// İstatistikler
$stats = [
    'clients' => (int)Database::fetchColumn("SELECT COUNT(*) FROM clients"),
    'products' => (int)Database::fetchColumn("SELECT COUNT(*) FROM products"),
    'services' => (int)Database::fetchColumn("SELECT COUNT(*) FROM services"),
    'invoices' => (int)Database::fetchColumn("SELECT COUNT(*) FROM invoices"),
    'tickets' => (int)Database::fetchColumn("SELECT COUNT(*) FROM tickets")
];

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.cleanup-header {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    border-radius: 20px;
    padding: 30px 35px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    gap: 25px;
    color: white;
    box-shadow: 0 10px 40px rgba(220, 38, 38, 0.3);
}

.cleanup-header-icon {
    width: 70px;
    height: 70px;
    background: rgba(255,255,255,0.2);
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
}

.cleanup-header h1 {
    font-size: 26px;
    margin-bottom: 5px;
}

.cleanup-header p {
    opacity: 0.9;
    font-size: 14px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

.stat-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 25px;
    text-align: center;
}

.stat-card-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    font-size: 22px;
    color: white;
}

.stat-card-icon.clients { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.stat-card-icon.products { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
.stat-card-icon.services { background: linear-gradient(135deg, #f59e0b, #d97706); }
.stat-card-icon.invoices { background: linear-gradient(135deg, #10b981, #059669); }
.stat-card-icon.tickets { background: linear-gradient(135deg, #ef4444, #dc2626); }

.stat-card h3 {
    font-size: 32px;
    color: #1e293b;
    margin-bottom: 5px;
}

.stat-card p {
    font-size: 14px;
    color: #64748b;
}

.cleanup-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 25px;
}

@media (max-width: 900px) {
    .cleanup-grid {
        grid-template-columns: 1fr;
    }
}

.cleanup-card {
    background: white;
    border: 2px solid #fecaca;
    border-radius: 16px;
    overflow: hidden;
}

.cleanup-card-header {
    background: linear-gradient(135deg, #fef2f2, #fee2e2);
    padding: 20px 25px;
    border-bottom: 2px solid #fecaca;
    display: flex;
    align-items: center;
    gap: 15px;
}

.cleanup-card-header .icon-box {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
}

.cleanup-card-header h3 {
    font-size: 16px;
    color: #991b1b;
}

.cleanup-card-body {
    padding: 25px;
}

.cleanup-card p {
    font-size: 14px;
    color: #64748b;
    margin-bottom: 20px;
    line-height: 1.6;
}

.confirm-input {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.confirm-input input {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid #fecaca;
    border-radius: 10px;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 2px;
    text-align: center;
    font-weight: 700;
}

.confirm-input input:focus {
    border-color: #ef4444;
    outline: none;
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
}

.hours-input {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.hours-input label {
    font-size: 14px;
    color: #475569;
}

.hours-input input {
    width: 80px;
    padding: 10px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    text-align: center;
    font-weight: 600;
}

.btn-delete {
    width: 100%;
    padding: 14px 20px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
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
    gap: 10px;
}

.btn-delete:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
}

.alert {
    padding: 20px 25px;
    border-radius: 12px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.alert-success {
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    border: 2px solid #10b981;
    color: #065f46;
}

.alert-error {
    background: linear-gradient(135deg, #fef2f2, #fee2e2);
    border: 2px solid #ef4444;
    color: #991b1b;
}

.warning-banner {
    background: linear-gradient(135deg, #fef2f2, #fee2e2);
    border: 2px solid #ef4444;
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 30px;
    display: flex;
    align-items: flex-start;
    gap: 20px;
}

.warning-banner i {
    font-size: 40px;
    color: #ef4444;
}

.warning-banner h4 {
    color: #991b1b;
    font-size: 18px;
    margin-bottom: 8px;
}

.warning-banner p {
    color: #b91c1c;
    font-size: 14px;
    line-height: 1.6;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #64748b;
    text-decoration: none;
    font-size: 14px;
    margin-bottom: 20px;
}

.back-link:hover {
    color: #1e293b;
}
</style>

<a href="whmcs-import.php" class="back-link">
    <i class="fas fa-arrow-left"></i> WHMCS Aktarım Sayfasına Dön
</a>

<!-- Header -->
<div class="cleanup-header">
    <div class="cleanup-header-icon">
        <i class="fas fa-trash-alt"></i>
    </div>
    <div>
        <h1>Veri Temizleme</h1>
        <p>WHMCS'den aktarılan veya mevcut verileri temizleyin</p>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?>">
    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>" style="font-size: 24px;"></i>
    <div><?= $message ?></div>
</div>
<?php endif; ?>

<div class="warning-banner">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <h4>⚠️ DİKKAT - Bu İşlem Geri Alınamaz!</h4>
        <p>
            Silme işlemleri kalıcıdır ve geri alınamaz. İşlem öncesi mutlaka veritabanı yedeği alın.
            Silmek için onay kutusuna <strong>"SIL"</strong> yazmanız gerekmektedir.
        </p>
    </div>
</div>

<!-- İstatistikler -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon clients"><i class="fas fa-users"></i></div>
        <h3><?= number_format($stats['clients']) ?></h3>
        <p>Müşteri</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon products"><i class="fas fa-box"></i></div>
        <h3><?= number_format($stats['products']) ?></h3>
        <p>Ürün</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon services"><i class="fas fa-cogs"></i></div>
        <h3><?= number_format($stats['services']) ?></h3>
        <p>Hizmet</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon invoices"><i class="fas fa-file-invoice"></i></div>
        <h3><?= number_format($stats['invoices']) ?></h3>
        <p>Fatura</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon tickets"><i class="fas fa-headset"></i></div>
        <h3><?= number_format($stats['tickets']) ?></h3>
        <p>Destek Talebi</p>
    </div>
</div>

<!-- Temizleme Seçenekleri -->
<div class="cleanup-grid">
    <!-- Tüm Müşterileri Sil -->
    <div class="cleanup-card">
        <div class="cleanup-card-header">
            <div class="icon-box"><i class="fas fa-users"></i></div>
            <h3>Tüm Müşterileri Sil</h3>
        </div>
        <div class="cleanup-card-body">
            <p>Sistemdeki tüm müşterileri ve ilişkili verilerini (hizmetler, faturalar, destek talepleri) kalıcı olarak siler.</p>
            <form method="POST" onsubmit="return confirm('TÜM MÜŞTERİLER SİLİNECEK! Emin misiniz?')">
                <input type="hidden" name="action" value="delete_all_clients">
                <div class="confirm-input">
                    <input type="text" name="confirm" placeholder="SIL yazın" autocomplete="off">
                </div>
                <button type="submit" class="btn-delete">
                    <i class="fas fa-trash"></i> Tüm Müşterileri Sil (<?= $stats['clients'] ?>)
                </button>
            </form>
        </div>
    </div>
    
    <!-- Son X Saat İçindeki Müşterileri Sil -->
    <div class="cleanup-card">
        <div class="cleanup-card-header">
            <div class="icon-box"><i class="fas fa-clock"></i></div>
            <h3>Son Eklenen Müşterileri Sil</h3>
        </div>
        <div class="cleanup-card-body">
            <p>Belirtilen süre içinde eklenen müşterileri siler. Test aktarımlarını temizlemek için idealdir.</p>
            <form method="POST" onsubmit="return confirm('Seçili süredeki müşteriler silinecek! Emin misiniz?')">
                <input type="hidden" name="action" value="delete_recent_clients">
                <div class="hours-input">
                    <label>Son</label>
                    <input type="number" name="hours" value="24" min="1" max="720">
                    <label>saat içinde eklenenler</label>
                </div>
                <div class="confirm-input">
                    <input type="text" name="confirm" placeholder="SIL yazın" autocomplete="off">
                </div>
                <button type="submit" class="btn-delete">
                    <i class="fas fa-clock"></i> Son Eklenen Müşterileri Sil
                </button>
            </form>
        </div>
    </div>
    
    <!-- Tüm Ürünleri Sil -->
    <div class="cleanup-card">
        <div class="cleanup-card-header">
            <div class="icon-box"><i class="fas fa-box"></i></div>
            <h3>Tüm Ürünleri Sil</h3>
        </div>
        <div class="cleanup-card-body">
            <p>Sistemdeki tüm ürünleri, ürün gruplarını ve ilişkili hizmetleri kalıcı olarak siler.</p>
            <form method="POST" onsubmit="return confirm('TÜM ÜRÜNLER SİLİNECEK! Emin misiniz?')">
                <input type="hidden" name="action" value="delete_all_products">
                <div class="confirm-input">
                    <input type="text" name="confirm" placeholder="SIL yazın" autocomplete="off">
                </div>
                <button type="submit" class="btn-delete">
                    <i class="fas fa-trash"></i> Tüm Ürünleri Sil (<?= $stats['products'] ?>)
                </button>
            </form>
        </div>
    </div>
    
    <!-- Tüm Hizmetleri Sil -->
    <div class="cleanup-card">
        <div class="cleanup-card-header">
            <div class="icon-box"><i class="fas fa-cogs"></i></div>
            <h3>Tüm Hizmetleri Sil</h3>
        </div>
        <div class="cleanup-card-body">
            <p>Sistemdeki tüm müşteri hizmetlerini kalıcı olarak siler. Müşteri ve ürünler korunur.</p>
            <form method="POST" onsubmit="return confirm('TÜM HİZMETLER SİLİNECEK! Emin misiniz?')">
                <input type="hidden" name="action" value="delete_all_services">
                <div class="confirm-input">
                    <input type="text" name="confirm" placeholder="SIL yazın" autocomplete="off">
                </div>
                <button type="submit" class="btn-delete">
                    <i class="fas fa-trash"></i> Tüm Hizmetleri Sil (<?= $stats['services'] ?>)
                </button>
            </form>
        </div>
    </div>
    
    <!-- Tüm Faturaları Sil -->
    <div class="cleanup-card">
        <div class="cleanup-card-header">
            <div class="icon-box"><i class="fas fa-file-invoice"></i></div>
            <h3>Tüm Faturaları Sil</h3>
        </div>
        <div class="cleanup-card-body">
            <p>Sistemdeki tüm faturaları kalıcı olarak siler.</p>
            <form method="POST" onsubmit="return confirm('TÜM FATURALAR SİLİNECEK! Emin misiniz?')">
                <input type="hidden" name="action" value="delete_all_invoices">
                <div class="confirm-input">
                    <input type="text" name="confirm" placeholder="SIL yazın" autocomplete="off">
                </div>
                <button type="submit" class="btn-delete">
                    <i class="fas fa-trash"></i> Tüm Faturaları Sil (<?= $stats['invoices'] ?>)
                </button>
            </form>
        </div>
    </div>
    
    <!-- Tüm Destek Taleplerini Sil -->
    <div class="cleanup-card">
        <div class="cleanup-card-header">
            <div class="icon-box"><i class="fas fa-headset"></i></div>
            <h3>Tüm Destek Taleplerini Sil</h3>
        </div>
        <div class="cleanup-card-body">
            <p>Sistemdeki tüm destek taleplerini ve yanıtlarını kalıcı olarak siler.</p>
            <form method="POST" onsubmit="return confirm('TÜM DESTEK TALEPLERİ SİLİNECEK! Emin misiniz?')">
                <input type="hidden" name="action" value="delete_all_tickets">
                <div class="confirm-input">
                    <input type="text" name="confirm" placeholder="SIL yazın" autocomplete="off">
                </div>
                <button type="submit" class="btn-delete">
                    <i class="fas fa-trash"></i> Tüm Talepleri Sil (<?= $stats['tickets'] ?>)
                </button>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

