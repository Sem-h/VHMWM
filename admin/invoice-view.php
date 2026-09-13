<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Mail.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

/* Oturum kontrolü burada; header.php sayfanın sonunda çağrıldığı için
   oradaki kontrol POST işleyicisini durdurmuyordu. */
if (!isset($_SESSION["admin_id"])) {
    header("Location: index.php");
    exit;
}

$pageTitle = 'Fatura Detayı';
$currentPage = 'invoices';
$db = Database::getInstance();

$invoiceId = (int)($_GET['id'] ?? 0);
$message = '';

// Fatura bilgilerini çek
$stmt = $db->prepare("
    SELECT i.*, c.first_name, c.last_name, c.email, c.phone, c.company_name, c.address, c.city, c.country
    FROM invoices i 
    LEFT JOIN clients c ON i.client_id = c.id 
    WHERE i.id = ?
");
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    header('Location: invoices.php');
    exit;
}

// Durum güncelleme ve işlemler
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_paid') {
        $stmt = $db->prepare("UPDATE invoices SET status = 'paid', paid_date = NOW(), amount_paid = total, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$invoiceId]);
        $invoice['status'] = 'paid';
        $message = 'Fatura ödendi olarak işaretlendi.';
    } elseif ($_POST['action'] === 'cancel') {
        $stmt = $db->prepare("UPDATE invoices SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$invoiceId]);
        $invoice['status'] = 'cancelled';
        $message = 'Fatura iptal edildi.';
    } elseif ($_POST['action'] === 'send_email') {
        // Faturayı müşteriye e-posta ile gönder
        try {
            $statusText = match($invoice['status']) {
                'paid' => 'Ödendi',
                'unpaid' => 'Ödenmemiş',
                'cancelled' => 'İptal Edildi',
                default => $invoice['status']
            };
            
            // invoice_created şablonunu kullan
            $result = Mail::sendTemplate('invoice_created', $invoice['email'], [
                'client_name' => $invoice['first_name'] . ' ' . $invoice['last_name'],
                'invoice_id' => $invoice['invoice_number'],
                'invoice_total' => number_format((float)$invoice['total'], 2, ',', '.'),
                'due_date' => date('d.m.Y', strtotime($invoice['due_date'])),
                'invoice_status' => $statusText
            ], $invoice['first_name']);
            
            if ($result) {
                $message = 'Fatura başarıyla <strong>' . htmlspecialchars($invoice['email']) . '</strong> adresine gönderildi!';
            } else {
                $message = 'E-posta gönderilemedi. SMTP ayarlarını kontrol edin.';
                $messageType = 'error';
            }
        } catch (Throwable $e) {
            $message = 'E-posta gönderimi sırasında hata oluştu: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Fatura kalemleri
$items = $db->query("SELECT * FROM invoice_items WHERE invoice_id = $invoiceId")->fetchAll();

// Ödemeler
$payments = $db->query("SELECT * FROM transactions WHERE invoice_id = $invoiceId ORDER BY created_at DESC")->fetchAll();

include 'includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>" style="display: flex; align-items: center; gap: 10px; padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; <?= $messageType === 'error' ? 'background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #ef4444;' : 'background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #10b981;' ?>">
        <i class="fas fa-<?= $messageType === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
        <?= $message ?>
    </div>
<?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <div>
        <a href="invoices.php" style="color: var(--gray); text-decoration: none; font-size: 14px;">← Faturalara Dön</a>
        <h2 style="margin-top: 10px;">Fatura <?= htmlspecialchars($invoice['invoice_number']) ?></h2>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
        <?php
        $statusBadge = match($invoice['status']) {
            'paid' => 'success',
            'unpaid' => 'warning',
            'cancelled' => 'gray',
            default => 'gray'
        };
        $statusText = match($invoice['status']) {
            'paid' => 'Ödenmiş',
            'unpaid' => 'Ödenmemiş',
            'cancelled' => 'İptal',
            default => $invoice['status']
        };
        ?>
        <span class="badge badge-<?= $statusBadge ?>" style="font-size: 14px; padding: 10px 20px;">
            <?= $statusText ?>
        </span>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
    <div>
        <!-- Fatura İçeriği -->
        <div class="card">
            <div class="card-body" style="padding: 40px;">
                <!-- Başlık -->
                <div style="display: flex; justify-content: space-between; margin-bottom: 40px;">
                    <div>
                        <h1 style="font-size: 32px; color: var(--primary);">FATURA</h1>
                        <p style="color: var(--gray);"><?= htmlspecialchars($invoice['invoice_number']) ?></p>
                    </div>
                    <div style="text-align: right;">
                        <h3><?= SITE_NAME ?></h3>
                        <?php
                        $companyAddress = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'company_address'")->fetchColumn();
                        $companyEmail = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'company_email'")->fetchColumn();
                        ?>
                        <p style="color: var(--gray);"><?= htmlspecialchars($companyAddress ?? '') ?></p>
                        <p style="color: var(--gray);"><?= htmlspecialchars($companyEmail ?? '') ?></p>
                    </div>
                </div>
                
                <!-- Müşteri ve Tarih -->
                <div style="display: flex; justify-content: space-between; margin-bottom: 40px;">
                    <div>
                        <h4 style="color: var(--gray); margin-bottom: 10px;">Fatura Adresi</h4>
                        <p><strong><?= htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']) ?></strong></p>
                        <?php if ($invoice['company_name']): ?>
                            <p><?= htmlspecialchars($invoice['company_name']) ?></p>
                        <?php endif; ?>
                        <p><?= htmlspecialchars($invoice['email']) ?></p>
                        <?php if ($invoice['address']): ?>
                            <p><?= htmlspecialchars($invoice['address']) ?></p>
                            <p><?= htmlspecialchars($invoice['city'] ?? '') ?>, <?= htmlspecialchars($invoice['country'] ?? '') ?></p>
                        <?php endif; ?>
                    </div>
                    <div style="text-align: right;">
                        <p><strong>Fatura Tarihi:</strong> <?= date('d.m.Y', strtotime($invoice['created_at'])) ?></p>
                        <p><strong>Vade Tarihi:</strong> <?= date('d.m.Y', strtotime($invoice['due_date'])) ?></p>
                        <?php if ($invoice['paid_date']): ?>
                            <p><strong>Ödeme Tarihi:</strong> <?= date('d.m.Y', strtotime($invoice['paid_date'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Kalemler -->
                <table class="table" style="margin-bottom: 30px;">
                    <thead>
                        <tr style="background: var(--dark); color: white;">
                            <th>Açıklama</th>
                            <th style="text-align: center;">Adet</th>
                            <th style="text-align: right;">Birim Fiyat</th>
                            <th style="text-align: right;">Toplam</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['description']) ?></td>
                            <td style="text-align: center;"><?= $item['quantity'] ?></td>
                            <td style="text-align: right;"><?= number_format((float)$item['unit_price'], 2) ?> <?= $invoice['currency'] ?></td>
                            <td style="text-align: right;"><?= number_format((float)$item['total'], 2) ?> <?= $invoice['currency'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Toplamlar -->
                <div style="display: flex; justify-content: flex-end;">
                    <div style="width: 300px;">
                        <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border);">
                            <span>Ara Toplam</span>
                            <span><?= number_format((float)$invoice['subtotal'], 2) ?> <?= $invoice['currency'] ?></span>
                        </div>
                        <?php if ($invoice['discount'] > 0): ?>
                        <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border);">
                            <span>İndirim</span>
                            <span style="color: var(--success);">-<?= number_format((float)$invoice['discount'], 2) ?> <?= $invoice['currency'] ?></span>
                        </div>
                        <?php endif; ?>
                        <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border);">
                            <span>KDV (%<?= $invoice['tax_rate'] ?>)</span>
                            <span><?= number_format((float)$invoice['tax'], 2) ?> <?= $invoice['currency'] ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 15px 0; font-size: 20px; font-weight: 700;">
                            <span>Toplam</span>
                            <span style="color: var(--primary);"><?= number_format((float)$invoice['total'], 2) ?> <?= $invoice['currency'] ?></span>
                        </div>
                        <?php if ($invoice['amount_paid'] > 0 && $invoice['amount_paid'] < $invoice['total']): ?>
                        <div style="display: flex; justify-content: space-between; padding: 10px 0; color: var(--danger);">
                            <span>Kalan Borç</span>
                            <span><?= number_format((float)$invoice['total'] - (float)$invoice['amount_paid'], 2) ?> <?= $invoice['currency'] ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Ödemeler -->
        <?php if (!empty($payments)): ?>
        <div class="card">
            <div class="card-header">
                <h3>💳 Ödeme Geçmişi</h3>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Gateway</th>
                            <th>İşlem ID</th>
                            <th>Tutar</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= date('d.m.Y H:i', strtotime($payment['created_at'])) ?></td>
                            <td><?= htmlspecialchars(ucfirst($payment['gateway'])) ?></td>
                            <td><?= htmlspecialchars($payment['transaction_id'] ?? '-') ?></td>
                            <td><?= number_format((float)$payment['amount'], 2) ?> <?= $payment['currency'] ?></td>
                            <td>
                                <span class="badge badge-<?= $payment['status'] === 'success' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($payment['status']) ?>
                                </span>
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
        <!-- İşlemler -->
        <div class="card">
            <div class="card-header">
                <h3>⚡ İşlemler</h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php if ($invoice['status'] === 'unpaid'): ?>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="action" value="mark_paid">
                            <button type="submit" class="btn btn-success" style="width: 100%;">✅ Ödendi İşaretle</button>
                        </form>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="btn btn-danger" style="width: 100%;" onclick="return confirm('Faturayı iptal etmek istediğinizden emin misiniz?')">❌ İptal Et</button>
                        </form>
                    <?php endif; ?>
                    <a href="javascript:window.print()" class="btn btn-outline">🖨️ Yazdır</a>
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="action" value="send_email">
                        <button type="submit" class="btn btn-outline" style="width: 100%;" onclick="this.innerHTML='📧 Gönderiliyor...'; this.disabled=true; this.form.submit();">
                            📧 E-posta Gönder
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Müşteri -->
        <div class="card">
            <div class="card-header">
                <h3>👤 Müşteri</h3>
            </div>
            <div class="card-body">
                <p><strong><?= htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']) ?></strong></p>
                <p><a href="mailto:<?= htmlspecialchars($invoice['email']) ?>"><?= htmlspecialchars($invoice['email']) ?></a></p>
                <hr style="border: none; border-top: 1px solid var(--border); margin: 15px 0;">
                <a href="client-edit.php?id=<?= $invoice['client_id'] ?>" class="btn btn-sm btn-outline" style="width: 100%;">Müşteri Profiline Git</a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

