<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Mail.php';
require_once dirname(__DIR__) . '/includes/OrderLog.php';
session_name(SESSION_NAME); session_start();

$pageTitle = 'Sipariş Detayı';
$currentPage = 'orders';
$db = Database::getInstance();

$orderId = (int)($_GET['id'] ?? 0);
$message = '';

// Sipariş bilgilerini çek
$stmt = $db->prepare("
    SELECT o.*, c.first_name, c.last_name, c.email, c.phone, c.company_name
    FROM orders o 
    LEFT JOIN clients c ON o.client_id = c.id 
    WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

// Durum güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $oldStatus = $order['status'];
    $newStatus = $_POST['status'];
    $stmt = $db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$newStatus, $orderId]);
    
    // Sipariş durumu değişikliği logu
    OrderLog::statusChanged($orderId, $oldStatus, $newStatus, $_SESSION['admin_id'] ?? null, $order['client_id']);
    
    // Sipariş onaylandığında hizmetleri oluştur ve mail gönder
    if ($newStatus === 'active' && $oldStatus !== 'active') {
        // Sipariş kalemlerini al ve hizmet oluştur
        $orderItems = Database::fetchAll("SELECT * FROM order_items WHERE order_id = ?", [$orderId]);
        
        foreach ($orderItems as $item) {
            // Bu sipariş kalemi için zaten hizmet var mı kontrol et
            $existingService = Database::fetch("SELECT id FROM services WHERE order_id = ? AND product_id = ?", [$orderId, $item['product_id']]);
            
            if (!$existingService && $item['product_id']) {
                // Yeni hizmet oluştur
                $nextDueDate = date('Y-m-d', strtotime('+1 month'));
                if ($item['billing_cycle'] === 'quarterly') $nextDueDate = date('Y-m-d', strtotime('+3 months'));
                elseif ($item['billing_cycle'] === 'semiannually') $nextDueDate = date('Y-m-d', strtotime('+6 months'));
                elseif ($item['billing_cycle'] === 'annually') $nextDueDate = date('Y-m-d', strtotime('+1 year'));
                
                Database::query("
                    INSERT INTO services (client_id, order_id, product_id, domain, status, billing_cycle, amount, registration_date, next_due_date, created_at)
                    VALUES (?, ?, ?, ?, 'pending', ?, ?, CURDATE(), ?, NOW())
                ", [
                    $order['client_id'],
                    $orderId,
                    $item['product_id'],
                    $item['domain'],
                    $item['billing_cycle'] ?? 'monthly',
                    $item['unit_price'],
                    $nextDueDate
                ]);
                
                $serviceId = $db->lastInsertId();
                OrderLog::serviceCreated($orderId, $serviceId, $_SESSION['admin_id'] ?? null, $order['client_id']);
            }
        }
        
        try {
            $productNames = array_column($orderItems, 'description');
            
            Mail::sendTemplate('order_confirmed', $order['email'], [
                'client_name' => $order['first_name'] . ' ' . $order['last_name'],
                'order_id' => $order['order_number'],
                'product_name' => implode(', ', $productNames)
            ], $order['first_name']);
        } catch (Throwable $e) {
            // Mail hatası işlemi engellemesin
        }
    }
    
    $order['status'] = $newStatus;
    $message = 'Sipariş durumu güncellendi.';
}

// Manuel hizmet oluşturma
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_service'])) {
    $itemId = (int)$_POST['item_id'];
    $item = Database::fetch("SELECT * FROM order_items WHERE id = ? AND order_id = ?", [$itemId, $orderId]);
    
    if ($item) {
        $existingService = Database::fetch("SELECT id FROM services WHERE order_id = ? AND product_id = ?", [$orderId, $item['product_id']]);
        
        if (!$existingService) {
            $nextDueDate = date('Y-m-d', strtotime('+1 month'));
            if ($item['billing_cycle'] === 'quarterly') $nextDueDate = date('Y-m-d', strtotime('+3 months'));
            elseif ($item['billing_cycle'] === 'semiannually') $nextDueDate = date('Y-m-d', strtotime('+6 months'));
            elseif ($item['billing_cycle'] === 'annually') $nextDueDate = date('Y-m-d', strtotime('+1 year'));
            
            Database::query("
                INSERT INTO services (client_id, order_id, product_id, domain, status, billing_cycle, amount, registration_date, next_due_date, created_at)
                VALUES (?, ?, ?, ?, 'pending', ?, ?, CURDATE(), ?, NOW())
            ", [
                $order['client_id'],
                $orderId,
                $item['product_id'],
                $item['domain'],
                $item['billing_cycle'] ?? 'monthly',
                $item['unit_price'],
                $nextDueDate
            ]);
            
            $newServiceId = $db->lastInsertId();
            OrderLog::serviceCreated($orderId, $newServiceId, $_SESSION['admin_id'] ?? null, $order['client_id']);
            header("Location: service-edit.php?id=$newServiceId");
            exit;
        } else {
            $message = 'Bu sipariş kalemi için zaten bir hizmet mevcut.';
        }
    }
}

// Sipariş kalemleri
$items = $db->query("
    SELECT oi.*, p.name as product_name, p.type as product_type
    FROM order_items oi 
    LEFT JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = $orderId
")->fetchAll();

// İlgili hizmetler
$services = $db->query("SELECT * FROM services WHERE order_id = $orderId")->fetchAll();

// İlgili faturalar
$invoices = $db->query("
    SELECT i.* FROM invoices i 
    INNER JOIN invoice_items ii ON i.id = ii.invoice_id 
    INNER JOIN services s ON ii.service_id = s.id 
    WHERE s.order_id = $orderId 
    GROUP BY i.id
")->fetchAll();

include 'includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?= $message ?></div>
<?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <div>
        <a href="orders.php" style="color: var(--gray); text-decoration: none; font-size: 14px;">← Siparişlere Dön</a>
        <h2 style="margin-top: 10px;">Sipariş <?= htmlspecialchars($order['order_number']) ?></h2>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
        <?php
        $statusBadge = match($order['status']) {
            'active' => 'success',
            'pending' => 'warning',
            'processing' => 'info',
            'cancelled', 'fraud' => 'danger',
            default => 'gray'
        };
        ?>
        <span class="badge badge-<?= $statusBadge ?>" style="font-size: 14px; padding: 10px 20px;">
            <?= ucfirst($order['status']) ?>
        </span>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
    <div>
        <!-- Sipariş Kalemleri -->
        <div class="card">
            <div class="card-header">
                <h3>📦 Sipariş Kalemleri</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php foreach ($items as $index => $item): ?>
                <div class="order-item-card">
                    <div class="order-item-header" onclick="toggleItemDetail(<?= $index ?>)">
                        <div class="item-main">
                            <span class="item-toggle"><i class="fas fa-chevron-right" id="toggle-icon-<?= $index ?>"></i></span>
                            <div class="item-info">
                                <strong class="item-name"><?= htmlspecialchars($item['product_name'] ?? $item['description']) ?></strong>
                                <?php if ($item['domain']): ?>
                                <span class="item-domain"><?= htmlspecialchars($item['domain']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="item-price">
                            <strong><?= number_format((float)$item['total'], 2) ?> ₺</strong>
                        </div>
                    </div>
                    <div class="order-item-detail" id="item-detail-<?= $index ?>" style="display: none;">
                        <div class="detail-grid">
                            <div class="detail-box">
                                <label>Ürün ID</label>
                                <span><?= $item['product_id'] ?? '-' ?></span>
                            </div>
                            <div class="detail-box">
                                <label>Domain / Hostname</label>
                                <span class="domain-value"><?= htmlspecialchars($item['domain'] ?? 'Belirtilmemiş') ?></span>
                            </div>
                            <div class="detail-box">
                                <label>Fatura Dönemi</label>
                                <span><?php 
                                    echo match($item['billing_cycle'] ?? 'monthly') {
                                        'monthly' => 'Aylık',
                                        'quarterly' => '3 Aylık',
                                        'semiannually' => '6 Aylık',
                                        'annually' => 'Yıllık',
                                        'biennially' => '2 Yıllık',
                                        'triennially' => '3 Yıllık',
                                        default => ucfirst($item['billing_cycle'] ?? 'Aylık')
                                    };
                                ?></span>
                            </div>
                            <div class="detail-box">
                                <label>Birim Fiyat</label>
                                <span><?= number_format((float)$item['unit_price'], 2) ?> ₺</span>
                            </div>
                            <div class="detail-box">
                                <label>Kurulum Ücreti</label>
                                <span><?= number_format((float)($item['setup_fee'] ?? 0), 2) ?> ₺</span>
                            </div>
                            <div class="detail-box">
                                <label>Miktar</label>
                                <span><?= $item['quantity'] ?? 1 ?> Adet</span>
                            </div>
                        </div>
                        <div class="detail-description">
                            <label>Açıklama</label>
                            <p><?= htmlspecialchars($item['description'] ?? '-') ?></p>
                        </div>
                        <?php if ($item['product_id']): 
                            // Bu kalem için hizmet var mı kontrol et
                            $itemService = Database::fetch("SELECT id FROM services WHERE order_id = ? AND product_id = ?", [$orderId, $item['product_id']]);
                        ?>
                        <div class="detail-actions">
                            <a href="product-edit.php?id=<?= $item['product_id'] ?>" class="btn btn-sm btn-outline">
                                <i class="fas fa-box"></i> Ürünü Görüntüle
                            </a>
                            <?php if ($itemService): ?>
                            <a href="service-edit.php?id=<?= $itemService['id'] ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-cog"></i> Hizmeti Düzenle
                            </a>
                            <?php else: ?>
                            <form method="POST" style="display: inline;" onclick="event.stopPropagation();">
                                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                <button type="submit" name="create_service" value="1" class="btn btn-sm btn-success">
                                    <i class="fas fa-plus"></i> Hizmet Oluştur
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($items)): ?>
                <div style="padding: 40px; text-align: center; color: var(--gray);">
                    <i class="fas fa-inbox" style="font-size: 40px; margin-bottom: 15px;"></i>
                    <p>Sipariş kalemi bulunamadı</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .order-item-card {
            border-bottom: 1px solid var(--border);
        }
        .order-item-card:last-child {
            border-bottom: none;
        }
        .order-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .order-item-header:hover {
            background: rgba(99, 102, 241, 0.05);
        }
        .item-main {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .item-toggle {
            width: 28px;
            height: 28px;
            background: var(--bg-secondary);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .item-toggle i {
            font-size: 12px;
            color: var(--gray);
            transition: transform 0.2s;
        }
        .item-toggle.open i {
            transform: rotate(90deg);
        }
        .item-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .item-name {
            font-size: 15px;
            color: var(--text);
        }
        .item-domain {
            font-size: 13px;
            color: var(--primary);
            background: rgba(99, 102, 241, 0.1);
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .item-price {
            font-size: 16px;
            color: var(--text);
        }
        .order-item-detail {
            background: var(--bg-secondary);
            padding: 20px;
            border-top: 1px solid var(--border);
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 16px;
        }
        .detail-box {
            background: var(--bg-card);
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        .detail-box label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray);
            margin-bottom: 4px;
        }
        .detail-box span {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
        }
        .detail-box .domain-value {
            color: var(--primary);
        }
        .detail-description {
            background: var(--bg-card);
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid var(--border);
            margin-bottom: 16px;
        }
        .detail-description label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray);
            margin-bottom: 8px;
        }
        .detail-description p {
            margin: 0;
            font-size: 14px;
            color: var(--text);
            line-height: 1.5;
        }
        .detail-actions {
            display: flex;
            gap: 10px;
        }
        @media (max-width: 768px) {
            .detail-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        </style>
        
        <script>
        function toggleItemDetail(index) {
            const detail = document.getElementById('item-detail-' + index);
            const icon = document.getElementById('toggle-icon-' + index);
            const toggle = icon.closest('.item-toggle');
            
            if (detail.style.display === 'none') {
                detail.style.display = 'block';
                toggle.classList.add('open');
            } else {
                detail.style.display = 'none';
                toggle.classList.remove('open');
            }
        }
        </script>
        
        <!-- İlgili Hizmetler -->
        <?php if (!empty($services)): ?>
        <div class="card">
            <div class="card-header">
                <h3>🔗 İlgili Hizmetler</h3>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Domain</th>
                            <th>Durum</th>
                            <th>Sonraki Vade</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                        <tr>
                            <td>#<?= $service['id'] ?></td>
                            <td><?= htmlspecialchars($service['domain'] ?? '-') ?></td>
                            <td>
                                <?php
                                $sBadge = match($service['status']) {
                                    'active' => 'success',
                                    'pending' => 'warning',
                                    'suspended' => 'danger',
                                    default => 'gray'
                                };
                                ?>
                                <span class="badge badge-<?= $sBadge ?>"><?= ucfirst($service['status']) ?></span>
                            </td>
                            <td><?= $service['next_due_date'] ? date('d.m.Y', strtotime($service['next_due_date'])) : '-' ?></td>
                            <td>
                                <a href="service-view.php?id=<?= $service['id'] ?>" class="btn btn-sm btn-outline">Görüntüle</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div>
        <!-- Sipariş Özeti -->
        <div class="card">
            <div class="card-header">
                <h3>📋 Sipariş Özeti</h3>
            </div>
            <div class="card-body">
                <div style="margin-bottom: 15px;">
                    <small style="color: var(--gray);">Sipariş Tarihi</small>
                    <p><strong><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></strong></p>
                </div>
                
                <hr style="border: none; border-top: 1px solid var(--border); margin: 15px 0;">
                
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span style="color: var(--gray);">Ara Toplam</span>
                    <span><?= number_format((float)$order['subtotal'], 2) ?> <?= $order['currency'] ?></span>
                </div>
                
                <?php if ($order['discount'] > 0): ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span style="color: var(--gray);">İndirim</span>
                    <span style="color: var(--success);">-<?= number_format((float)$order['discount'], 2) ?> <?= $order['currency'] ?></span>
                </div>
                <?php endif; ?>
                
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span style="color: var(--gray);">KDV</span>
                    <span><?= number_format((float)$order['tax'], 2) ?> <?= $order['currency'] ?></span>
                </div>
                
                <hr style="border: none; border-top: 1px solid var(--border); margin: 15px 0;">
                
                <div style="display: flex; justify-content: space-between; font-size: 18px; font-weight: 700;">
                    <span>Toplam</span>
                    <span style="color: var(--primary);"><?= number_format((float)$order['total'], 2) ?> <?= $order['currency'] ?></span>
                </div>
            </div>
        </div>
        
        <!-- Müşteri Bilgileri -->
        <div class="card">
            <div class="card-header">
                <h3>👤 Müşteri</h3>
            </div>
            <div class="card-body">
                <p><strong><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></strong></p>
                <?php if ($order['company_name']): ?>
                    <p style="color: var(--gray);"><?= htmlspecialchars($order['company_name']) ?></p>
                <?php endif; ?>
                <p style="margin-top: 10px;">
                    <a href="mailto:<?= htmlspecialchars($order['email']) ?>"><?= htmlspecialchars($order['email']) ?></a>
                </p>
                <?php if ($order['phone']): ?>
                    <p><?= htmlspecialchars($order['phone']) ?></p>
                <?php endif; ?>
                <hr style="border: none; border-top: 1px solid var(--border); margin: 15px 0;">
                <a href="clients.php?id=<?= $order['client_id'] ?>" class="btn btn-sm btn-outline" style="width: 100%;">Müşteri Profiline Git</a>
            </div>
        </div>
        
        <!-- Durum Güncelle -->
        <div class="card">
            <div class="card-header">
                <h3>⚙️ Durum Güncelle</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <select name="status" class="form-control">
                            <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Beklemede</option>
                            <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>İşleniyor</option>
                            <option value="active" <?= $order['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                            <option value="fraud" <?= $order['status'] === 'fraud' ? 'selected' : '' ?>>Fraud</option>
                            <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>İptal</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Güncelle</button>
                </form>
            </div>
        </div>
        
        <!-- IP Bilgisi -->
        <?php if ($order['ip_address']): ?>
        <div class="card">
            <div class="card-header">
                <h3>🌐 Sipariş Bilgisi</h3>
            </div>
            <div class="card-body">
                <small style="color: var(--gray);">IP Adresi</small>
                <p><strong><?= htmlspecialchars($order['ip_address']) ?></strong></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

