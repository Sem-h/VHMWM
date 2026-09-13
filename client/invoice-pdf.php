<?php
/**
 * WHMVM - Fatura PDF İndirme
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
session_name(SESSION_NAME); session_start();

if (!isset($_SESSION['client_id'])) {
    header('Location: index.php');
    exit;
}

$clientId = $_SESSION['client_id'];
$invoiceId = (int)($_GET['id'] ?? 0);

// Fatura bilgilerini çek
$invoice = Database::fetch("SELECT * FROM invoices WHERE id = ? AND client_id = ?", [$invoiceId, $clientId]);

if (!$invoice) {
    header('Location: invoices.php');
    exit;
}

// Fatura kalemleri
$items = Database::fetchAll("SELECT * FROM invoice_items WHERE invoice_id = ?", [$invoiceId]);

// Müşteri bilgileri
$client = Database::fetch("SELECT * FROM clients WHERE id = ?", [$clientId]);

// Şirket bilgileri
$companyName = Settings::get('company_name') ?: SITE_NAME;
$companyAddress = Settings::get('company_address') ?: '';
$companyEmail = Settings::get('company_email') ?: '';
$companyPhone = Settings::get('company_phone') ?: '';
$siteLogo = Settings::get('site_logo') ?: '';

// Durum
$statusText = match($invoice['status']) {
    'paid' => 'ÖDENDİ',
    'unpaid' => 'ÖDEME BEKLENİYOR',
    'cancelled' => 'İPTAL EDİLDİ',
    'refunded' => 'İADE EDİLDİ',
    default => strtoupper($invoice['status'])
};

$statusColor = match($invoice['status']) {
    'paid' => '#22c55e',
    'unpaid' => '#f59e0b',
    'cancelled' => '#ef4444',
    default => '#64748b'
};
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fatura <?= htmlspecialchars($invoice['invoice_number']) ?> - <?= htmlspecialchars($companyName) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            line-height: 1.6;
        }
        
        .print-controls {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        
        .print-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .print-btn.primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
        }
        
        .print-btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
        }
        
        .print-btn.secondary {
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        
        .print-btn.secondary:hover {
            border-color: #6366f1;
            color: #6366f1;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 40px auto;
            background: white;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border-radius: 16px;
            overflow: hidden;
        }
        
        /* Header */
        .invoice-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            padding: 40px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .company-logo {
            max-height: 60px;
            max-width: 200px;
            margin-bottom: 16px;
            filter: brightness(0) invert(1);
        }
        
        .company-info h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 12px;
        }
        
        .company-info p {
            font-size: 14px;
            opacity: 0.8;
            line-height: 1.8;
        }
        
        .invoice-badge {
            text-align: right;
        }
        
        .invoice-title {
            font-size: 42px;
            font-weight: 800;
            letter-spacing: 2px;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #a78bfa, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .invoice-number {
            font-size: 16px;
            opacity: 0.9;
            font-family: monospace;
        }
        
        /* Status Banner */
        .status-banner {
            background: <?= $statusColor ?>20;
            border-left: 4px solid <?= $statusColor ?>;
            padding: 16px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .status-text {
            font-weight: 700;
            font-size: 14px;
            color: <?= $statusColor ?>;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .status-date {
            font-size: 13px;
            color: #64748b;
        }
        
        /* Info Section */
        .info-section {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            padding: 40px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .info-box h3 {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }
        
        .info-box h4 {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }
        
        .info-box p {
            font-size: 14px;
            color: #64748b;
        }
        
        /* Dates */
        .dates-row {
            display: flex;
            gap: 40px;
            padding: 30px 40px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .date-item {
            text-align: center;
            padding: 20px 30px;
            background: #f8fafc;
            border-radius: 12px;
        }
        
        .date-item span {
            display: block;
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        
        .date-item strong {
            font-size: 16px;
            color: #1e293b;
        }
        
        /* Items Table */
        .items-section {
            padding: 40px;
        }
        
        .items-section h3 {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 20px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .items-table th {
            background: #1e293b;
            color: white;
            padding: 14px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .items-table th:nth-child(2) { text-align: center; }
        .items-table th:nth-child(3),
        .items-table th:nth-child(4) { text-align: right; }
        
        .items-table td {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        
        .items-table td:nth-child(2) { text-align: center; }
        .items-table td:nth-child(3),
        .items-table td:nth-child(4) { 
            text-align: right; 
            font-family: 'JetBrains Mono', monospace;
            font-weight: 500;
        }
        
        .item-desc {
            font-weight: 500;
            color: #1e293b;
        }
        
        .item-type {
            display: inline-block;
            margin-top: 4px;
            padding: 2px 8px;
            background: #e2e8f0;
            border-radius: 4px;
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
        }
        
        /* Totals */
        .totals-section {
            display: flex;
            justify-content: flex-end;
            padding: 0 40px 40px;
        }
        
        .totals-box {
            width: 320px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 14px 20px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        
        .total-row:last-child {
            border-bottom: none;
        }
        
        .total-row span:first-child {
            color: #64748b;
        }
        
        .total-row span:last-child {
            font-weight: 600;
            color: #1e293b;
            font-family: 'JetBrains Mono', monospace;
        }
        
        .total-row.discount span:last-child {
            color: #22c55e;
        }
        
        .total-row.grand {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            padding: 18px 20px;
        }
        
        .total-row.grand span {
            color: white;
            font-size: 18px;
            font-weight: 700;
        }
        
        /* Footer */
        .invoice-footer {
            background: #f8fafc;
            padding: 30px 40px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        
        .invoice-footer p {
            font-size: 13px;
            color: #64748b;
        }
        
        .invoice-footer strong {
            color: #1e293b;
        }
        
        /* Print styles */
        @media print {
            body {
                background: white;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .print-controls {
                display: none !important;
            }
            
            .invoice-container {
                margin: 0;
                box-shadow: none;
                border-radius: 0;
                max-width: 100%;
            }
            
            .invoice-header {
                background: #1e293b !important;
                -webkit-print-color-adjust: exact;
            }
            
            .company-logo {
                filter: brightness(0) invert(1) !important;
                -webkit-print-color-adjust: exact;
            }
            
            .items-table th {
                background: #1e293b !important;
                -webkit-print-color-adjust: exact;
            }
            
            .total-row.grand {
                background: #6366f1 !important;
                -webkit-print-color-adjust: exact;
            }
            
            @page {
                margin: 0;
                size: A4;
            }
        }
    </style>
</head>
<body>
    <!-- Print Controls -->
    <div class="print-controls">
        <button class="print-btn primary" onclick="window.print()">
            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
            PDF Olarak Kaydet
        </button>
        <a href="invoice-view.php?id=<?= $invoiceId ?>" class="print-btn secondary">
            <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
            Geri Dön
        </a>
    </div>
    
    <!-- Invoice -->
    <div class="invoice-container">
        <!-- Header -->
        <div class="invoice-header">
            <div class="company-info">
                <?php if (!empty($siteLogo)): ?>
                    <img src="/<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($companyName) ?>" class="company-logo">
                <?php else: ?>
                    <h1><?= htmlspecialchars($companyName) ?></h1>
                <?php endif; ?>
                <p>
                    <?php if ($companyAddress): ?><?= htmlspecialchars($companyAddress) ?><br><?php endif; ?>
                    <?php if ($companyEmail): ?><?= htmlspecialchars($companyEmail) ?><br><?php endif; ?>
                    <?php if ($companyPhone): ?><?= htmlspecialchars($companyPhone) ?><?php endif; ?>
                </p>
            </div>
            <div class="invoice-badge">
                <div class="invoice-title">FATURA</div>
                <div class="invoice-number"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
            </div>
        </div>
        
        <!-- Status Banner -->
        <div class="status-banner">
            <span class="status-text"><?= $statusText ?></span>
            <span class="status-date">Düzenlenme: <?= date('d.m.Y H:i', strtotime($invoice['created_at'])) ?></span>
        </div>
        
        <!-- Info Section -->
        <div class="info-section">
            <div class="info-box">
                <h3>Fatura Adresi</h3>
                <h4><?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?></h4>
                <?php if (!empty($client['company_name'])): ?>
                    <p><?= htmlspecialchars($client['company_name']) ?></p>
                <?php endif; ?>
                <p><?= htmlspecialchars($client['email']) ?></p>
                <?php if (!empty($client['phone'])): ?>
                    <p><?= htmlspecialchars($client['phone']) ?></p>
                <?php endif; ?>
            </div>
            <div class="info-box">
                <h3>Ödeme Bilgileri</h3>
                <h4><?= match($invoice['payment_method']) {
                    'bank_transfer' => 'Banka Havalesi / EFT',
                    'credit_card' => 'Kredi Kartı',
                    'paytr' => 'PayTR',
                    default => $invoice['payment_method'] ?? 'Belirtilmemiş'
                } ?></h4>
                <p>Para Birimi: <?= htmlspecialchars($invoice['currency']) ?></p>
            </div>
        </div>
        
        <!-- Dates -->
        <div class="dates-row">
            <div class="date-item">
                <span>Fatura Tarihi</span>
                <strong><?= date('d.m.Y', strtotime($invoice['created_at'])) ?></strong>
            </div>
            <div class="date-item">
                <span>Vade Tarihi</span>
                <strong><?= date('d.m.Y', strtotime($invoice['due_date'])) ?></strong>
            </div>
            <?php if ($invoice['paid_date']): ?>
                <div class="date-item">
                    <span>Ödeme Tarihi</span>
                    <strong><?= date('d.m.Y', strtotime($invoice['paid_date'])) ?></strong>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Items -->
        <div class="items-section">
            <h3>Fatura Kalemleri</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Açıklama</th>
                        <th>Adet</th>
                        <th>Birim Fiyat</th>
                        <th>Toplam</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div class="item-desc"><?= htmlspecialchars($item['description']) ?></div>
                                <span class="item-type"><?= htmlspecialchars($item['type'] ?? 'Hizmet') ?></span>
                            </td>
                            <td><?= (int)$item['quantity'] ?></td>
                            <td><?= number_format((float)$item['unit_price'], 2) ?> <?= $invoice['currency'] ?></td>
                            <td><?= number_format((float)$item['total'], 2) ?> <?= $invoice['currency'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Totals -->
        <div class="totals-section">
            <div class="totals-box">
                <div class="total-row">
                    <span>Ara Toplam</span>
                    <span><?= number_format((float)$invoice['subtotal'], 2) ?> <?= $invoice['currency'] ?></span>
                </div>
                <?php if ($invoice['discount'] > 0): ?>
                    <div class="total-row discount">
                        <span>İndirim</span>
                        <span>-<?= number_format((float)$invoice['discount'], 2) ?> <?= $invoice['currency'] ?></span>
                    </div>
                <?php endif; ?>
                <div class="total-row">
                    <span>KDV (%<?= (int)$invoice['tax_rate'] ?>)</span>
                    <span><?= number_format((float)$invoice['tax'], 2) ?> <?= $invoice['currency'] ?></span>
                </div>
                <div class="total-row grand">
                    <span>Genel Toplam</span>
                    <span><?= number_format((float)$invoice['total'], 2) ?> <?= $invoice['currency'] ?></span>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="invoice-footer">
            <p>
                Bu fatura <strong><?= htmlspecialchars($companyName) ?></strong> tarafından elektronik ortamda düzenlenmiştir.<br>
                Sorularınız için: <strong><?= htmlspecialchars($companyEmail) ?></strong>
            </p>
        </div>
    </div>
    
    <script>
        // Auto print dialog on load (optional - uncomment if you want auto print)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>

