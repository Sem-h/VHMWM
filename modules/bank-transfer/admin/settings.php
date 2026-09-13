<?php
/**
 * Havale/EFT Modülü - Ayarlar Sayfası
 */

declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/config/config.php';
require_once dirname(__DIR__, 3) . '/includes/Database.php';
session_name(SESSION_NAME);
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: ' . dirname(__DIR__, 3) . '/admin/index.php');
    exit;
}

$pageTitle = 'Havale/EFT Modülü Ayarları';
$currentPage = 'modules';
$message = '';
$messageType = 'success';

// Tabloyu oluştur (yoksa)
try {
    Database::query("SELECT 1 FROM bank_accounts LIMIT 1");
} catch (Exception $e) {
    Database::query("
        CREATE TABLE IF NOT EXISTS bank_accounts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bank_name VARCHAR(100) NOT NULL,
            account_holder VARCHAR(255) NOT NULL,
            account_number VARCHAR(100) NOT NULL,
            iban VARCHAR(50),
            branch_name VARCHAR(255),
            branch_code VARCHAR(50),
            swift_code VARCHAR(20),
            currency VARCHAR(3) DEFAULT 'TRY',
            display_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (is_active),
            INDEX idx_order (display_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// Modül ayarlarını kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        $module = Database::fetch("SELECT * FROM modules WHERE slug = 'bank-transfer'");
        if ($module) {
            $config = json_decode($module['config'] ?? '{}', true) ?: [];
            $config['auto_confirm'] = isset($_POST['auto_confirm']) ? 1 : 0;
            $config['require_receipt'] = isset($_POST['require_receipt']) ? 1 : 0;
            $config['instructions'] = $_POST['instructions'] ?? '';
            
            Database::query("UPDATE modules SET config = ? WHERE slug = 'bank-transfer'", [
                json_encode($config, JSON_UNESCAPED_UNICODE)
            ]);
            $message = 'Modül ayarları kaydedildi!';
        }
    } catch (Exception $e) {
        $message = 'Hata: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Banka hesabı ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    try {
        Database::query("
            INSERT INTO bank_accounts 
            (bank_name, account_holder, account_number, iban, branch_name, branch_code, swift_code, currency, display_order, is_active, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $_POST['bank_name'],
            $_POST['account_holder'],
            $_POST['account_number'],
            $_POST['iban'] ?? null,
            $_POST['branch_name'] ?? null,
            $_POST['branch_code'] ?? null,
            $_POST['swift_code'] ?? null,
            $_POST['currency'] ?? 'TRY',
            (int)($_POST['display_order'] ?? 0),
            isset($_POST['is_active']) ? 1 : 0,
            $_POST['notes'] ?? null
        ]);
        $message = 'Banka hesabı eklendi!';
    } catch (Exception $e) {
        $message = 'Hata: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Banka hesabı güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    try {
        $id = (int)$_POST['id'];
        Database::query("
            UPDATE bank_accounts SET
            bank_name = ?, account_holder = ?, account_number = ?, iban = ?,
            branch_name = ?, branch_code = ?, swift_code = ?, currency = ?,
            display_order = ?, is_active = ?, notes = ?
            WHERE id = ?
        ", [
            $_POST['bank_name'],
            $_POST['account_holder'],
            $_POST['account_number'],
            $_POST['iban'] ?? null,
            $_POST['branch_name'] ?? null,
            $_POST['branch_code'] ?? null,
            $_POST['swift_code'] ?? null,
            $_POST['currency'] ?? 'TRY',
            (int)($_POST['display_order'] ?? 0),
            isset($_POST['is_active']) ? 1 : 0,
            $_POST['notes'] ?? null,
            $id
        ]);
        $message = 'Banka hesabı güncellendi!';
    } catch (Exception $e) {
        $message = 'Hata: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Banka hesabı silme
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        Database::query("DELETE FROM bank_accounts WHERE id = ?", [$_GET['delete']]);
        $message = 'Banka hesabı silindi!';
    } catch (Exception $e) {
        $message = 'Hata: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Durum değiştirme
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $account = Database::fetch("SELECT is_active FROM bank_accounts WHERE id = ?", [$_GET['toggle']]);
    if ($account) {
        $newStatus = $account['is_active'] ? 0 : 1;
        Database::query("UPDATE bank_accounts SET is_active = ? WHERE id = ?", [$newStatus, $_GET['toggle']]);
        $message = 'Durum güncellendi!';
    }
}

// Modül ayarlarını çek
$module = Database::fetch("SELECT * FROM modules WHERE slug = 'bank-transfer'");
$moduleConfig = $module ? (json_decode($module['config'] ?? '{}', true) ?: []) : [];

// Düzenlenecek hesap
$editAccount = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editAccount = Database::fetch("SELECT * FROM bank_accounts WHERE id = ?", [$_GET['edit']]);
}

// Banka hesaplarını çek
$accounts = Database::fetchAll("SELECT * FROM bank_accounts ORDER BY display_order, bank_name");

include dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <div>
        <a href="../../admin/modules.php" style="color: var(--gray); text-decoration: none; font-size: 14px;">← Modüllere Dön</a>
        <h2 style="margin-top: 10px;">🏦 Havale/EFT Modülü Ayarları</h2>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
    <!-- Modül Ayarları -->
    <div class="card">
        <div class="card-header">
            <h3>⚙️ Modül Ayarları</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="save_settings" value="1">
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" name="auto_confirm" <?= ($moduleConfig['auto_confirm'] ?? 0) ? 'checked' : '' ?>>
                        <span>Otomatik Onay</span>
                    </label>
                    <small style="color: #64748b; font-size: 12px; display: block; margin-top: 4px;">
                        Ödeme bildirimleri otomatik olarak onaylanır (Önerilmez)
                    </small>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" name="require_receipt" <?= ($moduleConfig['require_receipt'] ?? 1) ? 'checked' : '' ?>>
                        <span>Makbuz Zorunlu</span>
                    </label>
                    <small style="color: #64748b; font-size: 12px; display: block; margin-top: 4px;">
                        Müşterilerden makbuz yüklemeleri istenir
                    </small>
                </div>
                
                <div class="form-group">
                    <label>Ödeme Talimatları</label>
                    <textarea name="instructions" class="form-control" rows="4" 
                              placeholder="Müşterilere gösterilecek ödeme talimatları..."><?= htmlspecialchars($moduleConfig['instructions'] ?? 'Lütfen ödeme yaparken açıklama kısmına referans numaranızı yazmayı unutmayın.') ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px;">
                    <i class="fas fa-save"></i> Ayarları Kaydet
                </button>
            </form>
        </div>
    </div>
    
    <!-- İstatistikler -->
    <div class="card">
        <div class="card-header">
            <h3>📊 İstatistikler</h3>
        </div>
        <div class="card-body">
            <?php
            $totalAccounts = Database::fetchColumn("SELECT COUNT(*) FROM bank_accounts");
            $activeAccounts = Database::fetchColumn("SELECT COUNT(*) FROM bank_accounts WHERE is_active = 1");
            $pendingPayments = Database::fetchColumn("SELECT COUNT(*) FROM bank_transfer_payments WHERE status = 'pending'");
            $confirmedPayments = Database::fetchColumn("SELECT COUNT(*) FROM bank_transfer_payments WHERE status = 'confirmed'");
            ?>
            <div style="display: grid; gap: 16px;">
                <div style="padding: 16px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Toplam Banka Hesabı</div>
                    <div style="font-size: 24px; font-weight: 700; color: #1e293b;"><?= $totalAccounts ?></div>
                </div>
                <div style="padding: 16px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Aktif Banka Hesabı</div>
                    <div style="font-size: 24px; font-weight: 700; color: #22c55e;"><?= $activeAccounts ?></div>
                </div>
                <div style="padding: 16px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Bekleyen Ödemeler</div>
                    <div style="font-size: 24px; font-weight: 700; color: #f59e0b;"><?= $pendingPayments ?></div>
                </div>
                <div style="padding: 16px; background: #f8fafc; border-radius: 10px;">
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Onaylanan Ödemeler</div>
                    <div style="font-size: 24px; font-weight: 700; color: #6366f1;"><?= $confirmedPayments ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Banka Hesapları Yönetimi -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3>🏦 Banka Hesapları</h3>
        <button class="btn btn-primary" onclick="document.getElementById('addForm').style.display='block'">
            <i class="fas fa-plus"></i> Yeni Banka Hesabı
        </button>
    </div>
    
    <!-- Ekleme Formu -->
    <div id="addForm" class="card-body" style="display: <?= $editAccount ? 'block' : 'none' ?>; border-bottom: 2px solid #e2e8f0;">
        <form method="POST">
            <input type="hidden" name="action" value="<?= $editAccount ? 'edit' : 'add' ?>">
            <?php if ($editAccount): ?>
                <input type="hidden" name="id" value="<?= $editAccount['id'] ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Banka Adı *</label>
                    <input type="text" name="bank_name" class="form-control" required 
                           value="<?= htmlspecialchars($editAccount['bank_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Hesap Sahibi *</label>
                    <input type="text" name="account_holder" class="form-control" required
                           value="<?= htmlspecialchars($editAccount['account_holder'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Hesap Numarası *</label>
                    <input type="text" name="account_number" class="form-control" required
                           value="<?= htmlspecialchars($editAccount['account_number'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>IBAN</label>
                    <input type="text" name="iban" class="form-control" placeholder="TR00 0000 0000 0000 0000 0000 00"
                           value="<?= htmlspecialchars($editAccount['iban'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Şube Adı</label>
                    <input type="text" name="branch_name" class="form-control"
                           value="<?= htmlspecialchars($editAccount['branch_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Şube Kodu</label>
                    <input type="text" name="branch_code" class="form-control"
                           value="<?= htmlspecialchars($editAccount['branch_code'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>SWIFT Kodu</label>
                    <input type="text" name="swift_code" class="form-control"
                           value="<?= htmlspecialchars($editAccount['swift_code'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Para Birimi</label>
                    <select name="currency" class="form-control">
                        <option value="TRY" <?= ($editAccount['currency'] ?? 'TRY') === 'TRY' ? 'selected' : '' ?>>TRY</option>
                        <option value="USD" <?= ($editAccount['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD</option>
                        <option value="EUR" <?= ($editAccount['currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Sıralama</label>
                    <input type="number" name="display_order" class="form-control" value="<?= $editAccount['display_order'] ?? 0 ?>">
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; margin-top: 30px;">
                        <input type="checkbox" name="is_active" <?= ($editAccount['is_active'] ?? 1) ? 'checked' : '' ?>>
                        Aktif
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label>Notlar</label>
                <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($editAccount['notes'] ?? '') ?></textarea>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?= $editAccount ? 'Güncelle' : 'Kaydet' ?>
                </button>
                <?php if ($editAccount): ?>
                    <a href="?module=bank-transfer&action=settings" class="btn btn-outline">İptal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Banka Hesapları Listesi -->
    <div class="card-body" style="padding: 0;">
        <?php if (empty($accounts)): ?>
            <div style="padding: 40px; text-align: center; color: var(--gray);">
                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                <p>Henüz banka hesabı eklenmemiş.</p>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Banka</th>
                        <th>Hesap Sahibi</th>
                        <th>Hesap No</th>
                        <th>IBAN</th>
                        <th>Para Birimi</th>
                        <th>Sıra</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accounts as $account): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($account['bank_name']) ?></strong></td>
                        <td><?= htmlspecialchars($account['account_holder']) ?></td>
                        <td><?= htmlspecialchars($account['account_number']) ?></td>
                        <td><?= htmlspecialchars($account['iban'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($account['currency']) ?></td>
                        <td><?= $account['display_order'] ?></td>
                        <td>
                            <span class="badge badge-<?= $account['is_active'] ? 'success' : 'gray' ?>">
                                <?= $account['is_active'] ? 'Aktif' : 'Pasif' ?>
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <a href="?module=bank-transfer&action=settings&edit=<?= $account['id'] ?>" class="btn btn-sm btn-outline" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?module=bank-transfer&action=settings&toggle=<?= $account['id'] ?>" class="btn btn-sm btn-<?= $account['is_active'] ? 'warning' : 'success' ?>" title="Durum Değiştir">
                                    <i class="fas fa-<?= $account['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                </a>
                                <a href="?module=bank-transfer&action=settings&delete=<?= $account['id'] ?>" class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Bu banka hesabını silmek istediğinize emin misiniz?')" title="Sil">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
