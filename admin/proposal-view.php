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

$id = (int) ($_GET['id'] ?? 0);
$proposal = Database::fetch("
    SELECT p.*, c.first_name, c.last_name, c.company_name, c.email, c.phone, c.address, c.city, c.country
    FROM proposals p 
    LEFT JOIN clients c ON p.client_id = c.id 
    WHERE p.id = ?", [$id]);

if (!$proposal) {
    echo "Teklif bulunamadı.";
    exit;
}

$items = Database::fetchAll("SELECT * FROM proposal_items WHERE proposal_id = ?", [$id]);
$pageTitle = 'Teklif #' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);

// DURUM GÜNCELLEME
if (isset($_GET['action']) && $_GET['action'] == 'update_status') {
    $newStatus = $_GET['status'];
    Database::query("UPDATE proposals SET status = ? WHERE id = ?", [$newStatus, $id]);
    header("Location: proposal-view.php?id=$id&msg=updated");
    exit;
}

include 'includes/header.php';
?>

<style>
    /* PREMIUM INVOICE DESIGN */
    :root {
        --invoice-primary: #4f46e5;
        --invoice-bg: #ffffff;
        --invoice-text: #1e293b;
        --invoice-muted: #64748b;
        --invoice-border: #e2e8f0;
    }

    body {
        background: #f8fafc;
    }

    .invoice-container {
        max-width: 1000px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 1fr 280px;
        gap: 30px;
    }

    .invoice-card {
        background: var(--invoice-bg);
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
        overflow: hidden;
        position: relative;
        border: 1px solid rgba(0, 0, 0, 0.02);
    }

    /* Header Section */
    .invoice-top {
        background: #1e293b;
        padding: 40px 50px;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
        border-radius: 12px 12px 0 0;
    }

    .invoice-top::before {
        display: none;
    }

    .invoice-brand h1 {
        margin: 0;
        font-size: 24px;
        font-weight: 700;
        letter-spacing: -0.5px;
    }

    .invoice-brand p {
        margin: 5px 0 0;
        font-size: 13px;
        opacity: 0.8;
        font-weight: 400;
    }

    .invoice-meta-idx {
        text-align: right;
    }

    .invoice-title {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        opacity: 0.6;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .invoice-number {
        font-size: 28px;
        font-weight: 800;
        letter-spacing: 1px;
        line-height: 1;
        margin-bottom: 12px;
    }

    .invoice-status-tag {
        display: inline-block;
        padding: 6px 16px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Body Section */
    .invoice-body {
        padding: 40px 50px;
        background: #f8fafc;
    }

    .bill-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
        margin-bottom: 40px;
    }

    .bill-col {
        background: white;
        padding: 25px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
    }

    .bill-label {
        font-size: 10px;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 700;
        letter-spacing: 1px;
        margin-bottom: 15px;
    }

    .bill-address strong {
        font-size: 18px;
        color: #0f172a;
        display: block;
        margin-bottom: 6px;
        font-weight: 700;
    }

    .bill-address div {
        font-size: 14px;
        color: #64748b;
        line-height: 1.6;
    }

    .invoice-dates {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .date-item {
        background: #f8fafc;
        padding: 15px;
        border-radius: 8px;
    }

    .date-item h4 {
        font-size: 10px;
        color: #64748b;
        margin: 0 0 6px;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    .date-item div {
        font-size: 14px;
        font-weight: 600;
        color: #0f172a;
    }

    /* Table */
    .items-table {
        width: 100%;
        border-collapse: collapse;
        margin: 30px 0;
        background: white;
        border-radius: 12px;
        overflow: hidden;
    }

    .items-table th {
        text-align: left;
        padding: 15px 20px;
        font-size: 11px;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 700;
        background: white;
        border-bottom: 2px solid #3b82f6;
        letter-spacing: 0.5px;
    }

    .items-table td {
        padding: 20px;
        border-bottom: 1px solid #f1f5f9;
        color: #0f172a;
        font-size: 14px;
        background: white;
    }

    .items-table tbody tr:last-child td {
        border-bottom: none;
    }

    .items-table td.desc {
        font-weight: 500;
    }

    .items-table td.amount {
        text-align: right;
        font-weight: 600;
        font-family: 'Inter', sans-serif;
    }

    /* Totals */
    .invoice-footer {
        display: flex;
        justify-content: flex-end;
        margin-top: 30px;
    }

    .totals-box {
        width: 320px;
        background: white;
        padding: 25px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
    }

    .total-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 12px;
        font-size: 14px;
        color: #64748b;
        font-weight: 500;
    }

    .total-row.final {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 2px solid #3b82f6;
        color: #0f172a;
        font-weight: 800;
        font-size: 20px;
    }

    /* Sidebar */
    .action-box {
        background: white;
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        position: sticky;
        top: 30px;
        border: 1px solid rgba(0, 0, 0, 0.03);
    }

    .action-title {
        font-size: 14px;
        font-weight: 700;
        color: var(--invoice-text);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .action-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        padding: 12px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: 0.2s;
        margin-bottom: 10px;
        border: 1px solid transparent;
        cursor: pointer;
    }

    .btn-primary-action {
        background: var(--invoice-primary);
        color: white;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
    }

    .btn-primary-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(79, 70, 229, 0.4);
    }

    .btn-default-action {
        background: white;
        border-color: var(--invoice-border);
        color: var(--invoice-text);
    }

    .btn-default-action:hover {
        border-color: var(--invoice-primary);
        color: var(--invoice-primary);
    }

    .status-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }

    .status-option {
        padding: 8px;
        text-align: center;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: 0.2s;
        border: 1px solid transparent;
    }

    .st-Draft {
        background: #f1f5f9;
        color: #64748b;
    }

    .st-Sent {
        background: #e0e7ff;
        color: #4338ca;
    }

    .st-Accepted {
        background: #dcfce7;
        color: #15803d;
    }

    .st-Rejected {
        background: #fee2e2;
        color: #b91c1c;
    }

    .st-active {
        border-color: currentColor;
        box-shadow: 0 0 0 1px currentColor;
        transform: scale(1.02);
    }

    @media print {
        body {
            background: white !important;
        }

        .sidebar,
        .header,
        .invoice-sidebar,
        .action-box,
        .no-print {
            display: none !important;
        }

        .main {
            margin-left: 0 !important;
            padding: 0 !important;
        }

        .invoice-container {
            grid-template-columns: 1fr !important;
            max-width: 100% !important;
        }

        .invoice-card {
            box-shadow: none !important;
            border: none !important;
        }
    }
</style>

<div class="invoice-container">
    <!-- Left: Invoice -->
    <div class="invoice-card">
        <div class="invoice-top">
            <div class="invoice-brand">
                <?php
                // Mevcut logoyu settings'den çek
                $logoPath = '';
                try {
                    $logoSetting = Database::fetch("SELECT setting_value FROM settings WHERE setting_key = 'site_logo' LIMIT 1");
                    if ($logoSetting && $logoSetting['setting_value']) {
                        $logoPath = $logoSetting['setting_value'];
                    }
                } catch (Exception $e) {
                    // Hata durumunda logo yok
                }

                // Logo varsa göster, yoksa site adını göster
                if ($logoPath && file_exists(dirname(__DIR__) . '/' . ltrim($logoPath, '/'))):
                    ?>
                    <img src="../<?= htmlspecialchars($logoPath) ?>" alt="<?= SITE_NAME ?>"
                        style="max-height: 50px; max-width: 200px; object-fit: contain; filter: brightness(0) invert(1);">
                <?php else: ?>
                    <h1><?= SITE_NAME ?></h1>
                <?php endif; ?>
                <p>Teknoloji ve Yazılım Çözümleri</p>
            </div>
            <div class="invoice-meta-idx">
                <div class="invoice-title">TEKLİF NO</div>
                <div class="invoice-number">#<?= str_pad((string) $proposal['id'], 5, '0', STR_PAD_LEFT) ?></div>
                <div class="invoice-status-tag">
                    <?= match ($proposal['status']) { 'Draft' => 'Taslak', 'Sent' => 'Gönderildi', 'Accepted' => 'Onaylandı', 'Rejected' => 'Reddedildi', default => $proposal['status']} ?>
                </div>
            </div>
        </div>

        <div class="invoice-body">

            <!-- Address Row -->
            <div class="bill-row">
                <div class="bill-col">
                    <div class="bill-label">Müşteri (Sayın)</div>
                    <div class="bill-address">
                        <strong><?= htmlspecialchars($proposal['first_name'] . ' ' . $proposal['last_name']) ?></strong>
                        <?php if ($proposal['company_name']): ?>
                            <div><?= htmlspecialchars($proposal['company_name']) ?></div>
                        <?php endif; ?>
                        <div><?= htmlspecialchars($proposal['email']) ?></div>
                        <div><?= htmlspecialchars($proposal['phone'] ?? '') ?></div>
                        <?php if ($proposal['address']): ?>
                            <div style="margin-top:5px; opacity:0.8;">
                                <?= htmlspecialchars($proposal['address']) . ' ' . htmlspecialchars($proposal['city'] ?? '') . ' ' . htmlspecialchars($proposal['country'] ?? '') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bill-col">
                    <div class="bill-label">Teklif Detayları</div>
                    <div class="invoice-dates">
                        <div class="date-item">
                            <h4>Oluşturma</h4>
                            <div><?= date('d.m.Y', strtotime($proposal['created_at'])) ?></div>
                        </div>
                        <div class="date-item">
                            <h4>Geçerlilik</h4>
                            <div style="color: #ef4444;"><?= date('d.m.Y', strtotime($proposal['valid_until'])) ?></div>
                        </div>
                        <div class="date-item"
                            style="grid-column: span 2; border-top: 1px solid #e2e8f0; margin-top: 10px; padding-top: 10px;">
                            <h4>Konu</h4>
                            <div style="font-size: 14px; font-weight: 500;">
                                <?= htmlspecialchars($proposal['subject']) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th width="50%">Hizmet / Ürün Açıklaması</th>
                        <th width="15%" class="text-center">Adet</th>
                        <th width="20%" class="text-right" style="text-align:right">Birim Fiyat</th>
                        <th width="15%" class="text-right" style="text-align:right">Tutar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="desc">
                                <div style="font-weight: 600; color: #334155; margin-bottom: 2px;">
                                    <?= htmlspecialchars($item['description']) ?>
                                </div>
                            </td>
                            <td class="amount text-center" style="font-weight: 400; color: #64748b;">
                                <?= $item['quantity'] ?>
                            </td>
                            <td class="amount"><?= number_format((float) $item['unit_price'], 2) ?></td>
                            <td class="amount" style="color: #0f172a;"><?= number_format((float) $item['amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Footer -->
            <div class="invoice-footer">
                <div class="totals-box">
                    <div class="total-row">
                        <span>Ara Toplam</span>
                        <span><?= number_format((float) $proposal['total_amount'], 2) ?>
                            <?= $proposal['currency'] ?></span>
                    </div>
                    <div class="total-row">
                        <span>KDV (%20)</span>
                        <span><?= number_format($proposal['total_amount'] * 0.20, 2) ?>
                            <?= $proposal['currency'] ?></span>
                    </div>
                    <div class="total-row final">
                        <span>TOPLAM</span>
                        <span><?= number_format($proposal['total_amount'] * 1.20, 2) ?>
                            <?= $proposal['currency'] ?></span>
                    </div>
                </div>
            </div>

            <?php if ($proposal['admin_notes']): ?>
                <div style="margin-top: 40px; padding-top: 20px; border-top: 1px dashed #e2e8f0;">
                    <div
                        style="font-size: 13px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">
                        Yönetici Notu</div>
                    <div style="font-size: 14px; color: #64748b; font-style: italic;">
                        <?= nl2br(htmlspecialchars($proposal['admin_notes'])) ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Right: Actions -->
    <div class="invoice-sidebar">
        <div class="action-box">
            <div class="action-title"><i class="fas fa-bolt"></i> Hızlı İşlemler</div>

            <a href="proposal-edit.php?id=<?= $id ?>" class="action-btn btn-primary-action">
                <i class="fas fa-pen"></i> Teklifi Düzenle
            </a>
            <button onclick="window.print()" class="action-btn btn-default-action">
                <i class="fas fa-print"></i> Yazdır
            </button>
            <button onclick="downloadPDF()" class="action-btn btn-default-action">
                <i class="fas fa-file-pdf"></i> PDF İndir
            </button>
            <button class="action-btn btn-default-action" onclick="sendEmail()">
                <i class="fas fa-envelope"></i> E-posta Gönder
            </button>

            <hr style="margin: 20px 0; border: none; border-top: 1px solid #f1f5f9;">

            <div class="action-title"><i class="fas fa-tag"></i> Durum Değiştir</div>
            <div class="status-grid">
                <a href="?id=<?= $id ?>&action=update_status&status=Draft"
                    class="status-option st-Draft <?= $proposal['status'] == 'Draft' ? 'st-active' : '' ?>">Taslak</a>
                <a href="?id=<?= $id ?>&action=update_status&status=Sent"
                    class="status-option st-Sent <?= $proposal['status'] == 'Sent' ? 'st-active' : '' ?>">Gönderildi</a>
                <a href="?id=<?= $id ?>&action=update_status&status=Accepted"
                    class="status-option st-Accepted <?= $proposal['status'] == 'Accepted' ? 'st-active' : '' ?>">Onaylandı</a>
                <a href="?id=<?= $id ?>&action=update_status&status=Rejected"
                    class="status-option st-Rejected <?= $proposal['status'] == 'Rejected' ? 'st-active' : '' ?>">Reddedildi</a>
            </div>

            <div style="margin-top: 25px; text-align: center;">
                <a href="proposals.php"
                    style="font-size: 13px; color: #94a3b8; text-decoration: none; font-weight: 500;">
                    <i class="fas fa-arrow-left"></i> Listeye Geri Dön
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    function downloadPDF() {
        // Sayfa başlığını PDF dosya adı olarak kullan
        const originalTitle = document.title;
        document.title = 'Teklif_<?= str_pad((string) $id, 5, '0', STR_PAD_LEFT) ?>_<?= date('Y-m-d') ?>';

        // Print dialog'u aç (kullanıcı "PDF olarak kaydet" seçeneğini seçebilir)
        window.print();

        // Başlığı geri yükle
        setTimeout(() => {
            document.title = originalTitle;
        }, 100);
    }

    function sendEmail() {
        // E-posta gönderme modal'ı veya sayfası açılabilir
        const email = '<?= htmlspecialchars($proposal['email'] ?? '') ?>';
        if (confirm('Teklif ' + email + ' adresine gönderilsin mi?')) {
            // AJAX ile e-posta gönderme işlemi yapılabilir
            fetch('proposal-send-email.php?id=<?= $id ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ E-posta başarıyla gönderildi!');
                    } else {
                        alert('❌ E-posta gönderilemedi: ' + (data.error || 'Bilinmeyen hata'));
                    }
                })
                .catch(error => {
                    alert('❌ Hata: ' + error.message);
                });
        }
    }
</script>

<?php include 'includes/footer.php'; ?>