<?php
/**
 * WHMVM - WHMCS Veri Aktarımı
 * WHMCS veritabanından müşteri, ürün ve diğer verileri aktarır
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

$pageTitle = 'WHMCS Veri Aktarımı';
$currentPage = 'settings';

$message = '';
$messageType = 'success';
$importResults = [];

// WHMCS bağlantı bilgileri session'dan veya POST'tan al
$whmcsConfig = $_SESSION['whmcs_config'] ?? [
    'host' => '',
    'database' => '',
    'username' => '',
    'password' => '',
    'prefix' => 'tbl'
];

// Bağlantı test ve import işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Bağlantı bilgilerini güncelle
    $whmcsConfig = [
        'host' => $_POST['whmcs_host'] ?? $whmcsConfig['host'],
        'database' => $_POST['whmcs_database'] ?? $whmcsConfig['database'],
        'username' => $_POST['whmcs_username'] ?? $whmcsConfig['username'],
        'password' => $_POST['whmcs_password'] ?? $whmcsConfig['password'],
        'prefix' => $_POST['whmcs_prefix'] ?? 'tbl'
    ];
    $_SESSION['whmcs_config'] = $whmcsConfig;
    
    // WHMCS veritabanına bağlan
    try {
        $whmcsDb = new PDO(
            "mysql:host={$whmcsConfig['host']};dbname={$whmcsConfig['database']};charset=utf8mb4",
            $whmcsConfig['username'],
            $whmcsConfig['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        if ($action === 'test') {
            // Bağlantı testi
            $clientCount = $whmcsDb->query("SELECT COUNT(*) FROM {$whmcsConfig['prefix']}clients")->fetchColumn();
            $message = "✅ WHMCS bağlantısı başarılı! {$clientCount} müşteri bulundu.";
            $messageType = 'success';
            
        } elseif ($action === 'import_clients') {
            // Müşterileri aktar
            $importResults = importClients($whmcsDb, $whmcsConfig['prefix']);
            $message = "Müşteri aktarımı tamamlandı!";
            
        } elseif ($action === 'import_products') {
            // Ürünleri aktar
            $importResults = importProducts($whmcsDb, $whmcsConfig['prefix']);
            $message = "Ürün aktarımı tamamlandı!";
            
        } elseif ($action === 'import_services') {
            // Hizmetleri aktar
            $importResults = importServices($whmcsDb, $whmcsConfig['prefix']);
            $message = "Hizmet aktarımı tamamlandı!";
            
        } elseif ($action === 'import_invoices') {
            // Faturaları aktar
            $importResults = importInvoices($whmcsDb, $whmcsConfig['prefix']);
            $message = "Fatura aktarımı tamamlandı!";
            
        } elseif ($action === 'import_tickets') {
            // Destek taleplerini aktar
            $importResults = importTickets($whmcsDb, $whmcsConfig['prefix']);
            $message = "Destek talepleri aktarımı tamamlandı!";
        }
        
    } catch (PDOException $e) {
        $message = "❌ WHMCS bağlantı hatası: " . $e->getMessage();
        $messageType = 'error';
    }
}

/**
 * Müşterileri WHMCS'den aktar
 */
function importClients(PDO $whmcsDb, string $prefix): array {
    $results = ['success' => 0, 'skipped' => 0, 'errors' => []];
    $db = Database::getInstance();
    
    // Önce WHMCS tablo yapısını kontrol et
    $columns = $whmcsDb->query("SHOW COLUMNS FROM {$prefix}clients")->fetchAll(PDO::FETCH_COLUMN);
    
    // Mevcut sütunlara göre sorgu oluştur
    $selectFields = ['id', 'firstname', 'lastname', 'email', 'password', 'datecreated', 'status'];
    $optionalFields = ['companyname', 'address1', 'address2', 'city', 'state', 'postcode', 'country', 'phonenumber', 'credit', 'taxid', 'tax_id'];
    
    foreach ($optionalFields as $field) {
        if (in_array($field, $columns)) {
            $selectFields[] = $field;
        }
    }
    
    // WHMCS müşterilerini çek
    $clients = $whmcsDb->query("
        SELECT " . implode(', ', $selectFields) . "
        FROM {$prefix}clients 
        ORDER BY id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($clients as $client) {
        try {
            // E-posta zaten var mı kontrol et
            $exists = Database::fetch("SELECT id FROM clients WHERE email = ?", [$client['email']]);
            
            if ($exists) {
                $results['skipped']++;
                continue;
            }
            
            // Adresi birleştir (opsiyonel alanlar için null check)
            $address1 = $client['address1'] ?? '';
            $address2 = $client['address2'] ?? '';
            $address = trim($address1 . ($address2 ? "\n" . $address2 : ''));
            
            // Durumu dönüştür
            $isActive = (($client['status'] ?? 'Active') === 'Active') ? 1 : 0;
            
            // Ülke kodunu düzenle (WHMCS bazen full name kullanır)
            $countryRaw = $client['country'] ?? 'TR';
            $country = strlen($countryRaw) > 2 ? 'TR' : strtoupper($countryRaw);
            
            // Tax ID için alternatif alan adları kontrol et
            $taxId = $client['taxid'] ?? $client['tax_id'] ?? '';
            
            // Müşteriyi ekle
            Database::query("
                INSERT INTO clients (
                    email, password, first_name, last_name, company_name, tax_id,
                    phone, address, city, state, postcode, country,
                    credit_balance, is_active, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $client['email'],
                $client['password'], // WHMCS şifresini direkt aktar (bcrypt uyumlu)
                $client['firstname'] ?? '',
                $client['lastname'] ?? '',
                $client['companyname'] ?? '',
                $taxId,
                $client['phonenumber'] ?? '',
                $address,
                $client['city'] ?? '',
                $client['state'] ?? '',
                $client['postcode'] ?? '',
                $country,
                (float)($client['credit'] ?? 0),
                $isActive,
                $client['datecreated'] ?? date('Y-m-d H:i:s')
            ]);
            
            $results['success']++;
            
        } catch (Exception $e) {
            $results['errors'][] = "Müşteri #{$client['id']} ({$client['email']}): " . $e->getMessage();
        }
    }
    
    return $results;
}

/**
 * Ürünleri WHMCS'den aktar
 */
function importProducts(PDO $whmcsDb, string $prefix): array {
    $results = ['success' => 0, 'skipped' => 0, 'errors' => []];
    
    // Önce tablo yapısını kontrol et
    $groupColumns = $whmcsDb->query("SHOW COLUMNS FROM {$prefix}product_groups")->fetchAll(PDO::FETCH_COLUMN);
    
    // Mevcut sütunlara göre sorgu oluştur
    $groupSelectFields = ['id', 'name'];
    $optionalGroupFields = ['slug', 'headline', 'tagline', 'orderfrmtpl', 'hidden', 'order'];
    foreach ($optionalGroupFields as $field) {
        if (in_array($field, $groupColumns)) {
            $groupSelectFields[] = ($field === 'order') ? '`order`' : $field;
        }
    }
    
    // Önce ürün gruplarını aktar
    $orderBy = in_array('order', $groupColumns) ? '`order`' : 'id';
    $groups = $whmcsDb->query("
        SELECT " . implode(', ', $groupSelectFields) . "
        FROM {$prefix}product_groups 
        ORDER BY {$orderBy} ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $groupMapping = []; // WHMCS ID => WHMVM ID
    
    foreach ($groups as $group) {
        try {
            $slugBase = $group['slug'] ?? strtolower(str_replace(' ', '-', $group['name']));
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $slugBase));
            
            $exists = Database::fetch("SELECT id FROM product_groups WHERE slug = ?", [$slug]);
            
            if ($exists) {
                $groupMapping[$group['id']] = $exists['id'];
                continue;
            }
            
            Database::query("
                INSERT INTO product_groups (name, slug, description, is_active)
                VALUES (?, ?, ?, ?)
            ", [
                $group['name'],
                $slug,
                $group['headline'] ?? $group['tagline'] ?? '',
                ($group['hidden'] ?? 0) ? 0 : 1
            ]);
            
            $groupMapping[$group['id']] = Database::getInstance()->lastInsertId();
            
        } catch (Exception $e) {
            $results['errors'][] = "Grup #{$group['id']}: " . $e->getMessage();
        }
    }
    
    // Ürün tablo yapısını kontrol et
    $productColumns = $whmcsDb->query("SHOW COLUMNS FROM {$prefix}products")->fetchAll(PDO::FETCH_COLUMN);
    
    // Pricing tablosu var mı kontrol et
    $hasPricing = false;
    try {
        $whmcsDb->query("SELECT 1 FROM {$prefix}pricing LIMIT 1");
        $hasPricing = true;
    } catch (Exception $e) {
        // Pricing tablosu yok
    }
    
    // Ürün sorgusu oluştur
    $productOrderBy = in_array('order', $productColumns) ? 'p.`order`' : 'p.id';
    
    if ($hasPricing) {
        $products = $whmcsDb->query("
            SELECT 
                p.id, p.gid, p.name, p.description, 
                " . (in_array('hidden', $productColumns) ? 'p.hidden,' : '') . "
                " . (in_array('order', $productColumns) ? 'p.`order`,' : '') . "
                pr.monthly, pr.annually, pr.msetupfee
            FROM {$prefix}products p
            LEFT JOIN {$prefix}pricing pr ON pr.relid = p.id AND pr.type = 'product' AND pr.currency = 1
            ORDER BY {$productOrderBy} ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $products = $whmcsDb->query("
            SELECT 
                p.id, p.gid, p.name, p.description
                " . (in_array('hidden', $productColumns) ? ', p.hidden' : '') . "
                " . (in_array('order', $productColumns) ? ', p.`order`' : '') . "
            FROM {$prefix}products p
            ORDER BY {$productOrderBy} ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    foreach ($products as $product) {
        try {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $product['name']));
            
            $exists = Database::fetch("SELECT id FROM products WHERE slug = ?", [$slug]);
            if ($exists) {
                $results['skipped']++;
                continue;
            }
            
            $groupId = $groupMapping[$product['gid']] ?? null;
            
            Database::query("
                INSERT INTO products (
                    name, slug, group_id, description, 
                    price_monthly, price_annually, setup_fee,
                    is_active, order_priority
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $product['name'],
                $slug,
                $groupId,
                $product['description'] ?? '',
                (isset($product['monthly']) && $product['monthly'] > 0) ? $product['monthly'] : null,
                (isset($product['annually']) && $product['annually'] > 0) ? $product['annually'] : null,
                $product['msetupfee'] ?? 0,
                ($product['hidden'] ?? 0) ? 0 : 1,
                $product['order'] ?? 0
            ]);
            
            $results['success']++;
            
        } catch (Exception $e) {
            $results['errors'][] = "Ürün #{$product['id']}: " . $e->getMessage();
        }
    }
    
    return $results;
}

/**
 * Hizmetleri WHMCS'den aktar
 */
function importServices(PDO $whmcsDb, string $prefix): array {
    $results = ['success' => 0, 'skipped' => 0, 'errors' => []];
    
    // Önce client ve product mapping oluştur
    $clientMapping = [];
    $whmcsClients = $whmcsDb->query("SELECT id, email FROM {$prefix}clients")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($whmcsClients as $wc) {
        $local = Database::fetch("SELECT id FROM clients WHERE email = ?", [$wc['email']]);
        if ($local) {
            $clientMapping[$wc['id']] = $local['id'];
        }
    }
    
    $productMapping = [];
    $whmcsProducts = $whmcsDb->query("SELECT id, name FROM {$prefix}products")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($whmcsProducts as $wp) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $wp['name']));
        $local = Database::fetch("SELECT id FROM products WHERE slug = ?", [$slug]);
        if ($local) {
            $productMapping[$wp['id']] = $local['id'];
        }
    }
    
    // Hizmetleri çek
    $services = $whmcsDb->query("
        SELECT 
            id, userid, packageid, domain, domainstatus,
            regdate, nextduedate, billingcycle, amount,
            dedicatedip, assignedips, username, password
        FROM {$prefix}hosting 
        ORDER BY id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($services as $service) {
        try {
            $clientId = $clientMapping[$service['userid']] ?? null;
            $productId = $productMapping[$service['packageid']] ?? null;
            
            if (!$clientId || !$productId) {
                $results['skipped']++;
                continue;
            }
            
            // Durum dönüşümü
            $statusMap = [
                'Active' => 'active',
                'Pending' => 'pending',
                'Suspended' => 'suspended',
                'Terminated' => 'terminated',
                'Cancelled' => 'cancelled'
            ];
            $status = $statusMap[$service['domainstatus']] ?? 'pending';
            
            // Periyot dönüşümü
            $cycleMap = [
                'Monthly' => 'monthly',
                'Quarterly' => 'quarterly',
                'Semi-Annually' => 'semi_annually',
                'Annually' => 'annually',
                'Biennially' => 'biennially',
                'Triennially' => 'triennially'
            ];
            $cycle = $cycleMap[$service['billingcycle']] ?? 'monthly';
            
            Database::query("
                INSERT INTO services (
                    client_id, product_id, domain, status,
                    billing_cycle, amount, start_date, next_due_date,
                    dedicated_ip, assigned_ips, username, password
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $clientId,
                $productId,
                $service['domain'] ?? '',
                $status,
                $cycle,
                $service['amount'] ?? 0,
                $service['regdate'],
                $service['nextduedate'],
                $service['dedicatedip'] ?? '',
                $service['assignedips'] ?? '',
                $service['username'] ?? '',
                $service['password'] ?? ''
            ]);
            
            $results['success']++;
            
        } catch (Exception $e) {
            $results['errors'][] = "Hizmet #{$service['id']}: " . $e->getMessage();
        }
    }
    
    return $results;
}

/**
 * Faturaları WHMCS'den aktar
 */
function importInvoices(PDO $whmcsDb, string $prefix): array {
    $results = ['success' => 0, 'skipped' => 0, 'errors' => []];
    
    // Client mapping
    $clientMapping = [];
    $whmcsClients = $whmcsDb->query("SELECT id, email FROM {$prefix}clients")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($whmcsClients as $wc) {
        $local = Database::fetch("SELECT id FROM clients WHERE email = ?", [$wc['email']]);
        if ($local) {
            $clientMapping[$wc['id']] = $local['id'];
        }
    }
    
    // Faturaları çek
    $invoices = $whmcsDb->query("
        SELECT 
            id, userid, date, duedate, datepaid, subtotal, tax, total, 
            status, paymentmethod, notes
        FROM {$prefix}invoices 
        ORDER BY id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($invoices as $invoice) {
        try {
            $clientId = $clientMapping[$invoice['userid']] ?? null;
            if (!$clientId) {
                $results['skipped']++;
                continue;
            }
            
            // Durum dönüşümü
            $statusMap = [
                'Paid' => 'paid',
                'Unpaid' => 'unpaid',
                'Cancelled' => 'cancelled',
                'Refunded' => 'refunded'
            ];
            $status = $statusMap[$invoice['status']] ?? 'unpaid';
            
            Database::query("
                INSERT INTO invoices (
                    client_id, subtotal, tax, total, status,
                    payment_method, created_at, due_date, paid_at, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $clientId,
                $invoice['subtotal'],
                $invoice['tax'],
                $invoice['total'],
                $status,
                $invoice['paymentmethod'] ?? '',
                $invoice['date'],
                $invoice['duedate'],
                $invoice['datepaid'] ?: null,
                $invoice['notes'] ?? ''
            ]);
            
            $results['success']++;
            
        } catch (Exception $e) {
            $results['errors'][] = "Fatura #{$invoice['id']}: " . $e->getMessage();
        }
    }
    
    return $results;
}

/**
 * Destek taleplerini WHMCS'den aktar
 */
function importTickets(PDO $whmcsDb, string $prefix): array {
    $results = ['success' => 0, 'skipped' => 0, 'errors' => []];
    
    // Client mapping
    $clientMapping = [];
    $whmcsClients = $whmcsDb->query("SELECT id, email FROM {$prefix}clients")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($whmcsClients as $wc) {
        $local = Database::fetch("SELECT id FROM clients WHERE email = ?", [$wc['email']]);
        if ($local) {
            $clientMapping[$wc['id']] = $local['id'];
        }
    }
    
    // Ticketları çek
    $tickets = $whmcsDb->query("
        SELECT 
            id, tid, userid, deptid, subject, message, status,
            priority, date, lastreply
        FROM {$prefix}tickets 
        ORDER BY id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tickets as $ticket) {
        try {
            $clientId = $clientMapping[$ticket['userid']] ?? null;
            if (!$clientId) {
                $results['skipped']++;
                continue;
            }
            
            // Durum dönüşümü
            $statusMap = [
                'Open' => 'open',
                'Answered' => 'answered',
                'Customer-Reply' => 'customer_reply',
                'Closed' => 'closed',
                'In Progress' => 'in_progress'
            ];
            $status = $statusMap[$ticket['status']] ?? 'open';
            
            // Öncelik dönüşümü
            $priorityMap = [
                'Low' => 'low',
                'Medium' => 'medium',
                'High' => 'high'
            ];
            $priority = $priorityMap[$ticket['priority']] ?? 'medium';
            
            Database::query("
                INSERT INTO tickets (
                    client_id, department_id, subject, message, status,
                    priority, created_at, last_reply_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $clientId,
                $ticket['deptid'] ?? 1,
                $ticket['subject'],
                $ticket['message'],
                $status,
                $priority,
                $ticket['date'],
                $ticket['lastreply'] ?: null
            ]);
            
            $results['success']++;
            
        } catch (Exception $e) {
            $results['errors'][] = "Ticket #{$ticket['id']}: " . $e->getMessage();
        }
    }
    
    return $results;
}

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.import-header {
    background: linear-gradient(135deg, #059669, #047857);
    border-radius: 20px;
    padding: 30px 35px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    gap: 25px;
    color: white;
    box-shadow: 0 10px 40px rgba(5, 150, 105, 0.3);
}

.import-header-icon {
    width: 70px;
    height: 70px;
    background: rgba(255,255,255,0.2);
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
}

.import-header h1 {
    font-size: 26px;
    margin-bottom: 5px;
}

.import-header p {
    opacity: 0.9;
    font-size: 14px;
}

.import-grid {
    display: grid;
    grid-template-columns: 400px 1fr;
    gap: 30px;
}

@media (max-width: 1200px) {
    .import-grid {
        grid-template-columns: 1fr;
    }
}

.config-card {
    background: white;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    height: fit-content;
}

.config-card-header {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    padding: 20px 25px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.config-card-header .icon-box {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #059669, #047857);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.config-card-header h3 {
    font-size: 16px;
    color: #1e293b;
}

.config-card-body {
    padding: 25px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: #475569;
    margin-bottom: 8px;
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s;
}

.form-control:focus {
    border-color: #059669;
    box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.1);
    outline: none;
}

.btn-test {
    width: 100%;
    padding: 14px 20px;
    background: linear-gradient(135deg, #059669, #047857);
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

.btn-test:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(5, 150, 105, 0.3);
}

.import-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

@media (max-width: 900px) {
    .import-options {
        grid-template-columns: 1fr;
    }
}

.import-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 25px;
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
}

.import-card:hover {
    border-color: #059669;
    transform: translateY(-5px);
    box-shadow: 0 15px 35px -10px rgba(5, 150, 105, 0.2);
}

.import-card-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    color: white;
    margin-bottom: 20px;
}

.import-card-icon.clients { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.import-card-icon.products { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
.import-card-icon.services { background: linear-gradient(135deg, #f59e0b, #d97706); }
.import-card-icon.invoices { background: linear-gradient(135deg, #10b981, #059669); }
.import-card-icon.tickets { background: linear-gradient(135deg, #ef4444, #dc2626); }

.import-card h4 {
    font-size: 18px;
    color: #1e293b;
    margin-bottom: 8px;
}

.import-card p {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 20px;
    line-height: 1.6;
}

.import-card .btn-import {
    width: 100%;
    padding: 12px 20px;
    border: 2px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.import-card .btn-import:hover {
    background: #059669;
    border-color: #059669;
    color: white;
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

.alert-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.alert-success .alert-icon {
    background: #10b981;
    color: white;
}

.alert-error .alert-icon {
    background: #ef4444;
    color: white;
}

.results-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    margin-top: 15px;
}

.results-box h5 {
    font-size: 14px;
    color: #1e293b;
    margin-bottom: 15px;
}

.results-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
}

.result-stat {
    padding: 10px 15px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
}

.result-stat.success {
    background: #d1fae5;
    color: #065f46;
}

.result-stat.skipped {
    background: #fef3c7;
    color: #92400e;
}

.result-stat.errors {
    background: #fee2e2;
    color: #991b1b;
}

.error-list {
    max-height: 200px;
    overflow-y: auto;
    font-size: 12px;
    color: #dc2626;
    background: #fef2f2;
    padding: 15px;
    border-radius: 8px;
}

.error-list p {
    margin-bottom: 5px;
    padding-bottom: 5px;
    border-bottom: 1px dashed #fecaca;
}

.warning-box {
    background: linear-gradient(135deg, #fffbeb, #fef3c7);
    border: 2px solid #f59e0b;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    display: flex;
    align-items: flex-start;
    gap: 15px;
}

.warning-box i {
    font-size: 24px;
    color: #f59e0b;
    flex-shrink: 0;
}

.warning-box div h4 {
    color: #92400e;
    font-size: 15px;
    margin-bottom: 5px;
}

.warning-box div p {
    color: #a16207;
    font-size: 13px;
    line-height: 1.6;
}
</style>

<!-- Header -->
<div class="import-header">
    <div class="import-header-icon">
        <i class="fas fa-database"></i>
    </div>
    <div>
        <h1>WHMCS Veri Aktarımı</h1>
        <p>WHMCS veritabanından müşteri, ürün, hizmet ve diğer verileri bu sisteme aktarın</p>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?>">
    <div class="alert-icon">
        <i class="fas fa-<?= $messageType === 'success' ? 'check' : 'times' ?>"></i>
    </div>
    <div>
        <strong><?= $messageType === 'success' ? 'Başarılı!' : 'Hata!' ?></strong>
        <p style="margin: 0;"><?= htmlspecialchars($message) ?></p>
        
        <?php if (!empty($importResults)): ?>
        <div class="results-box">
            <h5>📊 Aktarım Sonuçları</h5>
            <div class="results-stats">
                <span class="result-stat success">✓ <?= $importResults['success'] ?? 0 ?> Başarılı</span>
                <span class="result-stat skipped">⊘ <?= $importResults['skipped'] ?? 0 ?> Atlandı</span>
                <span class="result-stat errors">✕ <?= count($importResults['errors'] ?? []) ?> Hata</span>
            </div>
            
            <?php if (!empty($importResults['errors'])): ?>
            <div class="error-list">
                <?php foreach ($importResults['errors'] as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="warning-box">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <h4>⚠️ Önemli Uyarı</h4>
        <p>
            Veri aktarımı geri alınamaz. Aktarım öncesi mutlaka veritabanı yedeği alın. 
            Aynı e-posta adresine sahip müşteriler atlanacaktır. 
            WHMCS şifreleri bcrypt formatında ise doğrudan çalışacaktır.
        </p>
    </div>
</div>

<div class="import-grid">
    <!-- Sol Kolon - Bağlantı Ayarları -->
    <div class="config-card">
        <div class="config-card-header">
            <div class="icon-box">
                <i class="fas fa-plug"></i>
            </div>
            <h3>WHMCS Veritabanı Bağlantısı</h3>
        </div>
        <div class="config-card-body">
            <form method="POST">
                <input type="hidden" name="action" value="test">
                
                <div class="form-group">
                    <label><i class="fas fa-server"></i> Sunucu (Host)</label>
                    <input type="text" name="whmcs_host" class="form-control" value="<?= htmlspecialchars($whmcsConfig['host']) ?>" placeholder="localhost veya IP adresi">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-database"></i> Veritabanı Adı</label>
                    <input type="text" name="whmcs_database" class="form-control" value="<?= htmlspecialchars($whmcsConfig['database']) ?>" placeholder="whmcs_db">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Kullanıcı Adı</label>
                    <input type="text" name="whmcs_username" class="form-control" value="<?= htmlspecialchars($whmcsConfig['username']) ?>" placeholder="root">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-key"></i> Şifre</label>
                    <input type="password" name="whmcs_password" class="form-control" value="<?= htmlspecialchars($whmcsConfig['password']) ?>" placeholder="••••••••">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Tablo Öneki (Prefix)</label>
                    <input type="text" name="whmcs_prefix" class="form-control" value="<?= htmlspecialchars($whmcsConfig['prefix']) ?>" placeholder="tbl">
                </div>
                
                <button type="submit" class="btn-test">
                    <i class="fas fa-plug"></i> Bağlantıyı Test Et
                </button>
            </form>
        </div>
    </div>
    
    <!-- Sağ Kolon - Import Seçenekleri -->
    <div>
        <div class="import-options">
            <!-- Müşteriler -->
            <div class="import-card">
                <div class="import-card-icon clients">
                    <i class="fas fa-users"></i>
                </div>
                <h4>Müşteriler</h4>
                <p>WHMCS'deki tüm müşteri hesaplarını, iletişim bilgileri ve bakiyeleri ile birlikte aktarın.</p>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="import_clients">
                    <input type="hidden" name="whmcs_host" value="<?= htmlspecialchars($whmcsConfig['host']) ?>">
                    <input type="hidden" name="whmcs_database" value="<?= htmlspecialchars($whmcsConfig['database']) ?>">
                    <input type="hidden" name="whmcs_username" value="<?= htmlspecialchars($whmcsConfig['username']) ?>">
                    <input type="hidden" name="whmcs_password" value="<?= htmlspecialchars($whmcsConfig['password']) ?>">
                    <input type="hidden" name="whmcs_prefix" value="<?= htmlspecialchars($whmcsConfig['prefix']) ?>">
                    <button type="submit" class="btn-import" onclick="return confirm('Müşterileri aktarmak istediğinize emin misiniz?')">
                        <i class="fas fa-download"></i> Müşterileri Aktar
                    </button>
                </form>
            </div>
            
            <!-- Ürünler -->
            <div class="import-card">
                <div class="import-card-icon products">
                    <i class="fas fa-box"></i>
                </div>
                <h4>Ürünler & Gruplar</h4>
                <p>Ürün gruplarını ve tüm ürünleri fiyatlandırma bilgileri ile birlikte aktarın.</p>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="import_products">
                    <input type="hidden" name="whmcs_host" value="<?= htmlspecialchars($whmcsConfig['host']) ?>">
                    <input type="hidden" name="whmcs_database" value="<?= htmlspecialchars($whmcsConfig['database']) ?>">
                    <input type="hidden" name="whmcs_username" value="<?= htmlspecialchars($whmcsConfig['username']) ?>">
                    <input type="hidden" name="whmcs_password" value="<?= htmlspecialchars($whmcsConfig['password']) ?>">
                    <input type="hidden" name="whmcs_prefix" value="<?= htmlspecialchars($whmcsConfig['prefix']) ?>">
                    <button type="submit" class="btn-import" onclick="return confirm('Ürünleri aktarmak istediğinize emin misiniz?')">
                        <i class="fas fa-download"></i> Ürünleri Aktar
                    </button>
                </form>
            </div>
            
            <!-- Hizmetler -->
            <div class="import-card">
                <div class="import-card-icon services">
                    <i class="fas fa-cogs"></i>
                </div>
                <h4>Hizmetler</h4>
                <p>Müşterilerin aktif ve geçmiş tüm hizmetlerini, domain ve IP bilgileri ile aktarın.</p>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="import_services">
                    <input type="hidden" name="whmcs_host" value="<?= htmlspecialchars($whmcsConfig['host']) ?>">
                    <input type="hidden" name="whmcs_database" value="<?= htmlspecialchars($whmcsConfig['database']) ?>">
                    <input type="hidden" name="whmcs_username" value="<?= htmlspecialchars($whmcsConfig['username']) ?>">
                    <input type="hidden" name="whmcs_password" value="<?= htmlspecialchars($whmcsConfig['password']) ?>">
                    <input type="hidden" name="whmcs_prefix" value="<?= htmlspecialchars($whmcsConfig['prefix']) ?>">
                    <button type="submit" class="btn-import" onclick="return confirm('Hizmetleri aktarmak istediğinize emin misiniz? Önce müşteri ve ürünlerin aktarılmış olması gerekir.')">
                        <i class="fas fa-download"></i> Hizmetleri Aktar
                    </button>
                </form>
            </div>
            
            <!-- Faturalar -->
            <div class="import-card">
                <div class="import-card-icon invoices">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <h4>Faturalar</h4>
                <p>Tüm fatura kayıtlarını, ödeme durumları ve tutarları ile birlikte aktarın.</p>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="import_invoices">
                    <input type="hidden" name="whmcs_host" value="<?= htmlspecialchars($whmcsConfig['host']) ?>">
                    <input type="hidden" name="whmcs_database" value="<?= htmlspecialchars($whmcsConfig['database']) ?>">
                    <input type="hidden" name="whmcs_username" value="<?= htmlspecialchars($whmcsConfig['username']) ?>">
                    <input type="hidden" name="whmcs_password" value="<?= htmlspecialchars($whmcsConfig['password']) ?>">
                    <input type="hidden" name="whmcs_prefix" value="<?= htmlspecialchars($whmcsConfig['prefix']) ?>">
                    <button type="submit" class="btn-import" onclick="return confirm('Faturaları aktarmak istediğinize emin misiniz? Önce müşterilerin aktarılmış olması gerekir.')">
                        <i class="fas fa-download"></i> Faturaları Aktar
                    </button>
                </form>
            </div>
            
            <!-- Destek Talepleri -->
            <div class="import-card">
                <div class="import-card-icon tickets">
                    <i class="fas fa-headset"></i>
                </div>
                <h4>Destek Talepleri</h4>
                <p>Tüm destek taleplerini, durumları ve öncelikleri ile birlikte aktarın.</p>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="import_tickets">
                    <input type="hidden" name="whmcs_host" value="<?= htmlspecialchars($whmcsConfig['host']) ?>">
                    <input type="hidden" name="whmcs_database" value="<?= htmlspecialchars($whmcsConfig['database']) ?>">
                    <input type="hidden" name="whmcs_username" value="<?= htmlspecialchars($whmcsConfig['username']) ?>">
                    <input type="hidden" name="whmcs_password" value="<?= htmlspecialchars($whmcsConfig['password']) ?>">
                    <input type="hidden" name="whmcs_prefix" value="<?= htmlspecialchars($whmcsConfig['prefix']) ?>">
                    <button type="submit" class="btn-import" onclick="return confirm('Destek taleplerini aktarmak istediğinize emin misiniz? Önce müşterilerin aktarılmış olması gerekir.')">
                        <i class="fas fa-download"></i> Talepleri Aktar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

