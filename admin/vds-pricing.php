<?php
/**
 * WHMVM - VDS Fiyatlandırma Yönetimi
 */
declare(strict_types=1);

// UTF-8 karakter seti
header('Content-Type: text/html; charset=UTF-8');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'VDS Fiyatlandırma';
$currentPage = 'vds-pricing';
$message = '';
$messageType = 'success';

// Güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pricing'])) {
    foreach ($_POST['pricing'] as $id => $data) {
        Database::query(
            "UPDATE vds_pricing SET unit_price = ?, min_value = ?, max_value = ?, step_value = ?, is_active = ? WHERE id = ?",
            [
                (float)$data['unit_price'],
                (int)$data['min_value'],
                (int)$data['max_value'],
                (int)$data['step_value'],
                isset($data['is_active']) ? 1 : 0,
                $id
            ]
        );
    }
    $message = 'Fiyatlar güncellendi!';
}

// Fiyatları çek
$pricing = Database::fetchAll("SELECT * FROM vds_pricing ORDER BY sort_order");

// Örnek fiyat hesapla
$basePrice = 0;
$cpuPrice = 0;
$ramPrice = 0;
$diskPrice = 0;
$ipPrice = 0;

foreach ($pricing as $p) {
    if ($p['resource_type'] === 'base') $basePrice = (float)$p['unit_price'];
    if ($p['resource_type'] === 'cpu') $cpuPrice = (float)$p['unit_price'];
    if ($p['resource_type'] === 'ram') $ramPrice = (float)$p['unit_price'];
    if ($p['resource_type'] === 'disk') $diskPrice = (float)$p['unit_price'];
    if ($p['resource_type'] === 'ip') $ipPrice = (float)$p['unit_price'];
}

// Örnek: 2 CPU, 4GB RAM, 50GB Disk, 1 IP (ilk IP ücretsiz)
$examplePrice = $basePrice + (2 * $cpuPrice) + (4 * $ramPrice) + (50 * $diskPrice);

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.pricing-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.pricing-header h2 {
    font-size: 24px;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 12px;
}

.pricing-header h2 i {
    color: var(--primary);
}

/* Stats Cards */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, var(--y-yuzey) 0%, var(--y-yuzey-2) 100%);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.stat-icon.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
.stat-icon.blue { background: linear-gradient(135deg, #3b82f6, #2563eb); }
.stat-icon.green { background: linear-gradient(135deg, #22c55e, #16a34a); }
.stat-icon.orange { background: linear-gradient(135deg, #f97316, #ea580c); }

.stat-info h4 {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
}

.stat-info span {
    font-size: 13px;
    color: var(--gray);
}

/* Pricing Table */
.pricing-card {
    background: var(--y-yuzey);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 25px;
}

.pricing-card-header {
    background: linear-gradient(135deg, var(--y-yuzey-2) 0%, var(--y-yuzey) 100%);
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pricing-card-header h3 {
    font-size: 18px;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

.pricing-card-header h3 i {
    color: var(--primary);
}

.pricing-table {
    width: 100%;
}

.pricing-table th {
    background: var(--y-yuzey-2);
    padding: 14px 20px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border);
}

.pricing-table td {
    padding: 16px 20px;
    border-bottom: 1px solid var(--y-cizgi-soft);
    vertical-align: middle;
}

.pricing-table tr:last-child td {
    border-bottom: none;
}

.pricing-table tr:hover {
    background: var(--y-yuzey-2);
}

.resource-info {
    display: flex;
    align-items: center;
    gap: 14px;
}

.resource-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: white;
}

.resource-icon.cpu { background: linear-gradient(135deg, #6366f1, #4f46e5); }
.resource-icon.ram { background: linear-gradient(135deg, #22c55e, #16a34a); }
.resource-icon.disk { background: linear-gradient(135deg, #f97316, #ea580c); }
.resource-icon.base { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
.resource-icon.bandwidth { background: linear-gradient(135deg, #0ea5e9, #0284c7); }
.resource-icon.ip { background: linear-gradient(135deg, #ec4899, #db2777); }

.resource-name {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 2px;
}

.resource-type {
    font-size: 12px;
    color: var(--gray);
}

.price-input-group {
    display: flex;
    align-items: center;
    gap: 6px;
}

.price-input-group input {
    width: 100px;
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    text-align: right;
}

.price-input-group input:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.price-input-group span {
    color: var(--gray);
    font-size: 13px;
}

.range-inputs {
    display: flex;
    align-items: center;
    gap: 8px;
}

.range-inputs input {
    width: 70px;
    padding: 8px 10px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 14px;
    text-align: center;
}

.range-inputs span {
    color: var(--gray);
    font-size: 12px;
}

.status-toggle {
    display: flex;
    align-items: center;
}

.status-toggle input[type="checkbox"] {
    width: 44px;
    height: 24px;
    appearance: none;
    background: var(--y-cizgi);
    border-radius: 12px;
    cursor: pointer;
    position: relative;
    transition: all 0.3s;
}

.status-toggle input[type="checkbox"]:checked {
    background: #22c55e;
}

.status-toggle input[type="checkbox"]::before {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    background: var(--y-yuzey);
    border-radius: 50%;
    top: 2px;
    left: 2px;
    transition: all 0.3s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.status-toggle input[type="checkbox"]:checked::before {
    left: 22px;
}

/* Example Card */
.example-card {
    background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
    border-radius: 16px;
    padding: 30px;
    color: white;
}

.example-card h4 {
    font-size: 16px;
    margin-bottom: 20px;
    opacity: 0.9;
}

.example-config {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.example-item {
    text-align: center;
    padding: 16px;
    background: rgba(255,255,255,0.1);
    border-radius: 12px;
}

.example-item .value {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 4px;
}

.example-item .label {
    font-size: 13px;
    opacity: 0.8;
}

.example-total {
    text-align: center;
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.2);
}

.example-total .price {
    font-size: 42px;
    font-weight: 800;
}

.example-total .period {
    font-size: 16px;
    opacity: 0.8;
}

.example-total .note {
    font-size: 13px;
    opacity: 0.7;
    margin-top: 8px;
}

/* Save Button */
.save-bar {
    position: sticky;
    bottom: 20px;
    background: var(--y-yuzey);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
}

.save-bar p {
    color: var(--gray);
    font-size: 14px;
}

.save-bar .btn {
    padding: 12px 30px;
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-row { grid-template-columns: repeat(2, 1fr); }
    .example-config { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .stats-row { grid-template-columns: 1fr; }
    .pricing-table { display: block; overflow-x: auto; }
}
</style>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="pricing-header">
    <h2><i class="fas fa-server"></i> VDS Fiyatlandırma</h2>
    <a href="<?= SITE_URL ?>/vds-sunucu.php" target="_blank" class="btn btn-outline">
        <i class="fas fa-external-link-alt"></i> Sayfayı Görüntüle
    </a>
</div>

<!-- Stats -->
<div class="stats-row">
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-money-bill"></i>
        </div>
        <div class="stat-info">
            <h4>₺<?= number_format($basePrice, 0) ?></h4>
            <span>Taban Fiyat</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-microchip"></i>
        </div>
        <div class="stat-info">
            <h4>₺<?= number_format($cpuPrice, 0) ?></h4>
            <span>vCPU Başına</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-memory"></i>
        </div>
        <div class="stat-info">
            <h4>₺<?= number_format($ramPrice, 0) ?></h4>
            <span>GB RAM Başına</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-hdd"></i>
        </div>
        <div class="stat-info">
            <h4>₺<?= number_format($diskPrice, 2) ?></h4>
            <span>GB Disk Başına</span>
        </div>
    </div>
</div>
<div class="stats-row" style="grid-template-columns: repeat(2, 1fr); margin-top: -10px;">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #ec4899, #db2777);">
            <i class="fas fa-network-wired"></i>
        </div>
        <div class="stat-info">
            <h4>₺<?= number_format($ipPrice, 0) ?></h4>
            <span>Ek IP Başına <small style="color: #22c55e;">(ilk IP ücretsiz)</small></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #14b8a6, #0d9488);">
            <i class="fas fa-calculator"></i>
        </div>
        <div class="stat-info">
            <h4>₺<?= number_format($examplePrice, 0) ?></h4>
            <span>Örnek Fiyat (2 CPU, 4GB, 50GB)</span>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 350px; gap: 25px;">
    <form method="POST">
        <div class="pricing-card">
            <div class="pricing-card-header">
                <h3><i class="fas fa-sliders-h"></i> Kaynak Fiyatları</h3>
            </div>
            <table class="pricing-table">
                <thead>
                    <tr>
                        <th>Kaynak</th>
                        <th>Birim Fiyat</th>
                        <th>Min - Max Değer</th>
                        <th>Adım</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pricing as $item): 
                        $icons = [
                            'base' => 'fas fa-coins',
                            'cpu' => 'fas fa-microchip',
                            'ram' => 'fas fa-memory',
                            'disk' => 'fas fa-hdd',
                            'bandwidth' => 'fas fa-network-wired',
                            'ip' => 'fas fa-globe'
                        ];
                    ?>
                        <tr>
                            <td>
                                <div class="resource-info">
                                    <div class="resource-icon <?= $item['resource_type'] ?>">
                                        <i class="<?= $icons[$item['resource_type']] ?? 'fas fa-cube' ?>"></i>
                                    </div>
                                    <div>
                                        <div class="resource-name"><?= htmlspecialchars($item['resource_name']) ?></div>
                                        <div class="resource-type"><?= strtoupper($item['resource_type']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="price-input-group">
                                    <span>₺</span>
                                    <input type="number" 
                                           name="pricing[<?= $item['id'] ?>][unit_price]" 
                                           value="<?= $item['unit_price'] ?>" 
                                           step="0.01" 
                                           min="0">
                                    <span>/ <?= $item['unit_label'] ?: 'birim' ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="range-inputs">
                                    <input type="number" 
                                           name="pricing[<?= $item['id'] ?>][min_value]" 
                                           value="<?= $item['min_value'] ?>" 
                                           min="0">
                                    <span>-</span>
                                    <input type="number" 
                                           name="pricing[<?= $item['id'] ?>][max_value]" 
                                           value="<?= $item['max_value'] ?>" 
                                           min="1">
                                </div>
                            </td>
                            <td>
                                <div class="range-inputs">
                                    <input type="number" 
                                           name="pricing[<?= $item['id'] ?>][step_value]" 
                                           value="<?= $item['step_value'] ?>" 
                                           min="1" 
                                           style="width: 60px;">
                                </div>
                            </td>
                            <td>
                                <div class="status-toggle">
                                    <input type="checkbox" 
                                           name="pricing[<?= $item['id'] ?>][is_active]" 
                                           <?= $item['is_active'] ? 'checked' : '' ?>>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="save-bar">
            <p><i class="fas fa-info-circle"></i> Değişiklikler kaydedildiğinde VDS sayfasında otomatik yansır.</p>
            <button type="submit" name="update_pricing" class="btn btn-primary">
                <i class="fas fa-save"></i> Değişiklikleri Kaydet
            </button>
        </div>
    </form>
    
    <!-- Example Card -->
    <div>
        <div class="example-card">
            <h4><i class="fas fa-calculator"></i> Örnek Hesaplama</h4>
            <div class="example-config">
                <div class="example-item">
                    <div class="value">2</div>
                    <div class="label">vCPU</div>
                </div>
                <div class="example-item">
                    <div class="value">4</div>
                    <div class="label">GB RAM</div>
                </div>
                <div class="example-item">
                    <div class="value">50</div>
                    <div class="label">GB SSD</div>
                </div>
            </div>
            <div class="example-total">
                <div class="price">₺<?= number_format($examplePrice, 0) ?></div>
                <div class="period">/ aylık</div>
                <div class="note">Taban + (2×CPU) + (4×RAM) + (50×Disk)</div>
            </div>
        </div>
        
        <div style="margin-top: 20px; padding: 20px; background: #f8fafc; border-radius: 12px;">
            <h4 style="font-size: 14px; color: var(--dark); margin-bottom: 12px;">
                <i class="fas fa-lightbulb" style="color: #f59e0b;"></i> Formül
            </h4>
            <code style="font-size: 12px; color: var(--gray); line-height: 2; display: block;">
                Fiyat = Taban + (CPU × ₺<?= $cpuPrice ?>) + (RAM × ₺<?= $ramPrice ?>) + (Disk × ₺<?= $diskPrice ?>) + (Ek IP × ₺<?= $ipPrice ?>)
            </code>
            <p style="font-size: 11px; color: #6b7280; margin-top: 10px;">
                <i class="fas fa-info-circle"></i> İlk IP adresi ücretsizdir. Ek IP'ler için ₺<?= $ipPrice ?>/adet ücret uygulanır.
            </p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

