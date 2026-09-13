<?php
/**
 * WHMVM - Cron Job Runner
 * Bu dosya sunucuda zamanlanmış görevler (cron jobs) tarafından çağrılır
 * 
 * Kullanım: curl -s https://yourdomain.com/cron.php
 * veya: 0 * * * * curl -s https://yourdomain.com/cron.php > /dev/null 2>&1
 */
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Settings.php';
require_once __DIR__ . '/includes/Mail.php';

// Güvenlik: İsteğe bağlı olarak bir token kontrolü eklenebilir
$cronToken = $_GET['token'] ?? '';
$expectedToken = Settings::get('cron_token', '');

// Token ayarlanmışsa kontrol et
if (!empty($expectedToken) && $cronToken !== $expectedToken) {
    http_response_code(403);
    die('Unauthorized');
}

// Sadece CLI veya doğru token ile çalıştırılabilir
if (php_sapi_name() !== 'cli' && empty($cronToken) && !empty($expectedToken)) {
    http_response_code(403);
    die('Unauthorized');
}

// Varsayılan olarak tüm aktif cron job'ları çalıştır (run_all=1 davranışı)
// Eğer sadece zamanı gelmiş olanları çalıştırmak isterseniz: ?only_scheduled=1
// run_all=1 parametresi artık gereksiz (varsayılan davranış), ama geriye dönük uyumluluk için destekleniyor
$onlyScheduled = isset($_GET['only_scheduled']) && $_GET['only_scheduled'] == '1';

if ($onlyScheduled) {
    // Sadece zamanı gelmiş olanları çalıştır
    $cronJobs = Database::fetchAll("
        SELECT * FROM cron_tasks 
        WHERE is_active = 1 
        AND (next_run IS NULL OR next_run <= NOW())
        ORDER BY next_run ASC
    ");
} else {
    // Varsayılan: Tüm aktif cron job'ları çalıştır (zaman kontrolü yapmadan)
    // Bu, run_all=1 davranışı ile aynı - artık varsayılan davranış
    $cronJobs = Database::fetchAll("
        SELECT * FROM cron_tasks 
        WHERE is_active = 1 
        ORDER BY name ASC
    ");
}

if (empty($cronJobs)) {
    // Toplam cron job sayısını kontrol et
    $totalJobs = Database::fetchColumn("SELECT COUNT(*) FROM cron_tasks");
    $activeJobs = Database::fetchColumn("SELECT COUNT(*) FROM cron_tasks WHERE is_active = 1");
    
    if (!$onlyScheduled) {
        if ($totalJobs == 0) {
            echo "No cron jobs found in database.\n";
            echo "Please go to Admin Panel > Settings > Cron Jobs and add default cron jobs.\n";
        } elseif ($activeJobs == 0) {
            echo "No active cron jobs found. Total jobs: $totalJobs (all inactive).\n";
            echo "Please activate cron jobs in Admin Panel > Settings > Cron Jobs.\n";
        } else {
            echo "No cron jobs to run at this time.\n";
        }
    } else {
        if ($totalJobs == 0) {
            echo "No cron jobs found in database.\n";
            echo "Please go to Admin Panel > Settings > Cron Jobs and add default cron jobs.\n";
        } elseif ($activeJobs == 0) {
            echo "No active cron jobs found. Total jobs: $totalJobs (all inactive).\n";
            echo "Please activate cron jobs in Admin Panel > Settings > Cron Jobs.\n";
        } else {
            echo "No cron jobs to run at this time.\n";
            echo "Active jobs: $activeJobs, but none are scheduled to run now.\n";
        }
    }
    exit(0);
}

$executed = 0;
$failed = 0;

foreach ($cronJobs as $job) {
    try {
        // Job'ı "running" olarak işaretle
        Database::query("
            UPDATE cron_tasks 
            SET last_status = 'running', last_run = NOW() 
            WHERE id = ?
        ", [$job['id']]);
        
        // Komutu çalıştır
        $result = executeCronCommand($job['command'], $job['id']);
        
        // Sonraki çalışma zamanını hesapla
        $nextRun = calculateNextRun($job['schedule']);
        
        // Başarılı olarak işaretle
        Database::query("
            UPDATE cron_tasks 
            SET last_status = 'success', 
                last_output = ?, 
                next_run = ?,
                updated_at = NOW()
            WHERE id = ?
        ", [
            is_string($result) ? $result : json_encode($result),
            $nextRun,
            $job['id']
        ]);
        
        $executed++;
        
    } catch (Exception $e) {
        // Hata durumunda kaydet
        $nextRun = calculateNextRun($job['schedule']);
        Database::query("
            UPDATE cron_tasks 
            SET last_status = 'failed', 
                last_output = ?,
                next_run = ?,
                updated_at = NOW()
            WHERE id = ?
        ", [
            'Error: ' . $e->getMessage(),
            $nextRun,
            $job['id']
        ]);
        
        $failed++;
    }
}

echo "Cron execution completed. Executed: $executed, Failed: $failed\n";

/**
 * Cron komutunu çalıştır
 */
function executeCronCommand(string $command, int $jobId): mixed {
    switch ($command) {
        case 'generate_invoices':
            return generateRenewalInvoices();
        
        case 'suspend_services':
            return suspendOverdueServices();
        
        case 'terminate_services':
            return terminateSuspendedServices();
        
        case 'send_invoice_reminders':
            return sendInvoiceReminders();
        
        case 'cleanup_old_logs':
            return cleanupOldLogs();
        
        case 'overdue_invoice_notices':
            return sendOverdueInvoiceNotices();
        
        case 'domain_renewal_notices':
            return sendDomainRenewalNotices();
        
        case 'domain_expiry':
            return processDomainExpiry();
        
        case 'ticket_escalations':
            return escalateTickets();
        
        case 'affiliate_commissions':
            return processAffiliateCommissions();
        
        case 'email_marketer':
            return sendEmailCampaigns();
        
        case 'database_backup':
            return createDatabaseBackup();
        
        default:
            throw new Exception("Unknown command: $command");
    }
}

/**
 * Otomatik fatura yenileme - Yenileme tarihinden 3 gün önce yeni fatura oluştur
 */
function generateRenewalInvoices(): array {
    $results = [
        'invoices_created' => 0,
        'services_processed' => 0,
        'emails_sent' => 0,
        'errors' => []
    ];
    
    try {
        // next_due_date'den 3 gün önce veya daha yakın olan aktif hizmetleri bul
        // Ve henüz bu dönem için fatura oluşturulmamış olanları
        $services = Database::fetchAll("
            SELECT s.*, p.name as product_name, c.first_name, c.last_name, c.email, c.id as client_id
            FROM services s
            LEFT JOIN products p ON s.product_id = p.id
            LEFT JOIN clients c ON s.client_id = c.id
            WHERE s.status = 'active'
            AND s.billing_cycle != 'onetime'
            AND s.next_due_date IS NOT NULL
            AND s.next_due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
            AND s.next_due_date >= CURDATE()
            AND NOT EXISTS (
                SELECT 1 FROM invoices i
                INNER JOIN invoice_items ii ON i.id = ii.invoice_id
                WHERE i.client_id = s.client_id
                AND ii.service_id = s.id
                AND i.status = 'unpaid'
                AND DATE(i.due_date) >= DATE(s.next_due_date)
            )
        ");
        
        $taxRate = (float)Settings::get('tax_rate', '20');
        $invoicePrefix = Settings::get('invoice_prefix', 'INV-');
        
        foreach ($services as $service) {
            try {
                // Fatura tutarını hesapla
                $amount = (float)($service['amount'] ?? 0);
                $subtotal = $amount;
                $tax = $subtotal * ($taxRate / 100);
                $total = $subtotal + $tax;
                
                // Fatura numarası oluştur
                $invoiceNumber = $invoicePrefix . date('Ymd') . '-' . strtoupper(substr(md5(uniqid() . $service['id']), 0, 6));
                
                // Sonraki ödeme tarihini hesapla
                $nextDueDate = calculateNextDueDate($service['next_due_date'], $service['billing_cycle']);
                
                // Fatura oluştur
                Database::query("
                    INSERT INTO invoices (
                        invoice_number, client_id, status, subtotal, tax, tax_rate, total, 
                        amount_paid, currency, due_date, payment_method, notes, created_at
                    ) VALUES (?, ?, 'unpaid', ?, ?, ?, ?, 0, 'TRY', ?, 'bank_transfer', ?, NOW())
                ", [
                    $invoiceNumber,
                    $service['client_id'],
                    $subtotal,
                    $tax,
                    $taxRate,
                    $total,
                    $nextDueDate,
                    'Hizmet Yenileme: ' . ($service['product_name'] ?? 'Hizmet') . 
                    ($service['domain'] ? ' (' . $service['domain'] . ')' : '')
                ]);
                
                $db = Database::getInstance();
                $invoiceId = $db->lastInsertId();
                
                // Fatura kalemi ekle
                Database::query("
                    INSERT INTO invoice_items (
                        invoice_id, type, description, quantity, unit_price, tax, total, service_id
                    ) VALUES (?, 'service', ?, 1, ?, ?, ?, ?)
                ", [
                    $invoiceId,
                    ($service['product_name'] ?? 'Hizmet') . 
                    ($service['domain'] ? ' - ' . $service['domain'] : '') . 
                    ' (Yenileme)',
                    $amount,
                    $tax,
                    $total,
                    $service['id']
                ]);
                
                // Hizmetin next_due_date'ini güncelle
                Database::query("
                    UPDATE services 
                    SET next_due_date = ? 
                    WHERE id = ?
                ", [$nextDueDate, $service['id']]);
                
                // Müşteriye e-posta gönder (yenileme faturası)
                try {
                    $daysUntilRenewal = (int)((strtotime($service['next_due_date']) - time()) / 86400);
                    $renewalDate = date('d.m.Y', strtotime($service['next_due_date']));
                    $domainText = !empty($service['domain']) ? ' (' . $service['domain'] . ')' : '';
                    
                    Mail::sendTemplate('invoice_renewal', $service['email'], [
                        'client_name' => $service['first_name'] . ' ' . $service['last_name'],
                        'invoice_id' => $invoiceNumber,
                        'invoice_total' => number_format($total, 2, ',', '.'),
                        'due_date' => date('d.m.Y', strtotime($nextDueDate)),
                        'renewal_date' => $renewalDate,
                        'days_until_renewal' => $daysUntilRenewal,
                        'product_name' => $service['product_name'] ?? 'Hizmet',
                        'domain' => $domainText,
                        'invoice_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                                        '://' . $_SERVER['HTTP_HOST'] . '/client/invoice-view.php?id=' . $invoiceId
                    ], $service['first_name']);
                    $results['emails_sent']++;
                } catch (Exception $e) {
                    // Mail hatası fatura oluşturmayı engellemesin
                    $results['errors'][] = "Email gönderilemedi (Service ID {$service['id']}): " . $e->getMessage();
                }
                
                $results['invoices_created']++;
                $results['services_processed']++;
                
            } catch (Exception $e) {
                $results['errors'][] = "Service ID {$service['id']}: " . $e->getMessage();
            }
        }
        
    } catch (Exception $e) {
        $results['errors'][] = $e->getMessage();
    }
    
    return $results;
}

/**
 * Sonraki ödeme tarihini hesapla
 */
function calculateNextDueDate(string $currentDate, string $billingCycle): string {
    $date = new DateTime($currentDate);
    
    switch ($billingCycle) {
        case 'monthly':
            $date->modify('+1 month');
            break;
        case 'quarterly':
            $date->modify('+3 months');
            break;
        case 'semiannually':
            $date->modify('+6 months');
            break;
        case 'annually':
            $date->modify('+1 year');
            break;
        case 'biennially':
            $date->modify('+2 years');
            break;
        case 'triennially':
            $date->modify('+3 years');
            break;
        default:
            $date->modify('+1 month');
    }
    
    return $date->format('Y-m-d');
}

/**
 * Süresi geçen hizmetleri askıya al
 */
function suspendOverdueServices(): array {
    $autoSuspendDays = (int)Settings::get('auto_suspend_days', '3');
    
    $services = Database::fetchAll("
        SELECT * FROM services 
        WHERE status = 'active'
        AND next_due_date < DATE_SUB(CURDATE(), INTERVAL ? DAY)
        AND override_auto_suspend = 0
    ", [$autoSuspendDays]);
    
    $suspended = 0;
    foreach ($services as $service) {
        Database::query("UPDATE services SET status = 'suspended' WHERE id = ?", [$service['id']]);
        $suspended++;
    }
    
    return ['suspended' => $suspended];
}

/**
 * Askıya alınmış hizmetleri sonlandır
 */
function terminateSuspendedServices(): array {
    $autoTerminateDays = (int)Settings::get('auto_terminate_days', '14');
    
    $services = Database::fetchAll("
        SELECT * FROM services 
        WHERE status = 'suspended'
        AND next_due_date < DATE_SUB(CURDATE(), INTERVAL ? DAY)
    ", [$autoTerminateDays]);
    
    $terminated = 0;
    foreach ($services as $service) {
        Database::query("
            UPDATE services 
            SET status = 'terminated', termination_date = CURDATE() 
            WHERE id = ?
        ", [$service['id']]);
        $terminated++;
    }
    
    return ['terminated' => $terminated];
}

/**
 * Fatura hatırlatma e-postaları gönder
 */
function sendInvoiceReminders(): array {
    $reminderDays = (int)Settings::get('invoice_reminder_days', '7');
    
    $invoices = Database::fetchAll("
        SELECT i.*, c.first_name, c.last_name, c.email
        FROM invoices i
        LEFT JOIN clients c ON i.client_id = c.id
        WHERE i.status = 'unpaid'
        AND i.due_date = DATE_ADD(CURDATE(), INTERVAL ? DAY)
            AND (c.email IS NOT NULL AND c.email != '')
    ", [$reminderDays]);
    
    $sent = 0;
    foreach ($invoices as $invoice) {
        try {
            Mail::sendTemplate('invoice_reminder', $invoice['email'], [
                'client_name' => $invoice['first_name'] . ' ' . $invoice['last_name'],
                'invoice_id' => $invoice['invoice_number'],
                'invoice_total' => number_format($invoice['total'], 2, ',', '.'),
                'due_date' => date('d.m.Y', strtotime($invoice['due_date']))
            ], $invoice['first_name']);
            $sent++;
        } catch (Exception $e) {
            // Hata durumunda devam et
        }
    }
    
    return ['sent' => $sent];
}

/**
 * Eski logları temizle
 */
function cleanupOldLogs(): array {
    // 90 günden eski logları sil
    try {
        $stmt = Database::getInstance()->prepare("
            DELETE FROM client_logs 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        $stmt->execute();
        $deleted = $stmt->rowCount();
    } catch (Exception $e) {
        $deleted = 0;
    }
    
    return ['deleted' => $deleted];
}

/**
 * Geciken faturalar için bildirim gönder
 */
function sendOverdueInvoiceNotices(): array {
    $overdueDays = (int)Settings::get('overdue_notice_days', '3');
    
    $invoices = Database::fetchAll("
        SELECT i.*, c.first_name, c.last_name, c.email,
               DATEDIFF(CURDATE(), i.due_date) as days_overdue
        FROM invoices i
        LEFT JOIN clients c ON i.client_id = c.id
        WHERE i.status = 'unpaid'
        AND i.due_date < CURDATE()
        AND DATEDIFF(CURDATE(), i.due_date) >= ?
        AND (c.email IS NOT NULL AND c.email != '')
    ", [$overdueDays]);
    
    $sent = 0;
    foreach ($invoices as $invoice) {
        try {
            Mail::sendTemplate('invoice_overdue', $invoice['email'], [
                'client_name' => $invoice['first_name'] . ' ' . $invoice['last_name'],
                'invoice_id' => $invoice['invoice_number'],
                'invoice_total' => number_format($invoice['total'], 2, ',', '.'),
                'due_date' => date('d.m.Y', strtotime($invoice['due_date'])),
                'days_overdue' => $invoice['days_overdue']
            ], $invoice['first_name']);
            $sent++;
        } catch (Exception $e) {
            // Hata durumunda devam et
        }
    }
    
    return ['sent' => $sent, 'total' => count($invoices)];
}

/**
 * Domain yenileme bildirimleri gönder
 */
function sendDomainRenewalNotices(): array {
    $noticeDays = [90, 60, 30, 14, 7, 1]; // Bildirim günleri
    
    $results = ['sent' => 0, 'domains' => []];
    
    foreach ($noticeDays as $days) {
        $domains = Database::fetchAll("
            SELECT d.*, c.first_name, c.last_name, c.email,
                   DATEDIFF(d.expiry_date, CURDATE()) as days_until_expiry
            FROM domains d
            LEFT JOIN clients c ON d.client_id = c.id
            WHERE d.status = 'active'
            AND d.expiry_date IS NOT NULL
            AND d.expiry_date > CURDATE()
            AND DATEDIFF(d.expiry_date, CURDATE()) = ?
            AND (c.email IS NOT NULL AND c.email != '')
        ", [$days, $days]);
        
        foreach ($domains as $domain) {
            try {
                Mail::sendTemplate('domain_renewal_notice', $domain['email'], [
                    'client_name' => $domain['first_name'] . ' ' . $domain['last_name'],
                    'domain' => $domain['domain'],
                    'expiry_date' => date('d.m.Y', strtotime($domain['expiry_date'])),
                    'days' => $days,
                    'auto_renew' => $domain['auto_renew'] ? 'Aktif' : 'Pasif'
                ], $domain['first_name']);
                $results['sent']++;
                $results['domains'][] = $domain['domain'];
            } catch (Exception $e) {
                // Hata durumunda devam et
            }
        }
    }
    
    return $results;
}

/**
 * Süresi dolan domainleri işle
 */
function processDomainExpiry(): array {
    $results = [
        'expired' => 0,
        'auto_renewed' => 0,
        'errors' => []
    ];
    
    // Süresi dolmuş domainleri bul
    $expiredDomains = Database::fetchAll("
        SELECT d.*, c.first_name, c.last_name, c.email
        FROM domains d
        LEFT JOIN clients c ON d.client_id = c.id
        WHERE d.status = 'active'
        AND d.expiry_date IS NOT NULL
        AND d.expiry_date < CURDATE()
    ");
    
    foreach ($expiredDomains as $domain) {
        try {
            if ($domain['auto_renew'] == 1) {
                // Otomatik yenileme aktifse fatura oluştur
                // Domain extension'ını çıkar (örn: example.com -> .com)
                $domainParts = explode('.', $domain['domain']);
                $extension = '.' . end($domainParts);
                
                $renewalPrice = Database::fetchColumn("
                    SELECT renew_1yr FROM domain_pricing 
                    WHERE extension = ? AND is_active = 1
                ", [$extension]);
                
                if ($renewalPrice) {
                    $taxRate = (float)Settings::get('tax_rate', '20');
                    $subtotal = (float)$renewalPrice;
                    $tax = $subtotal * ($taxRate / 100);
                    $total = $subtotal + $tax;
                    
                    $invoiceNumber = Settings::get('invoice_prefix', 'INV-') . date('Ymd') . '-' . strtoupper(substr(md5(uniqid() . $domain['id']), 0, 6));
                    $dueDate = date('Y-m-d', strtotime('+7 days'));
                    $newExpiryDate = date('Y-m-d', strtotime('+1 year'));
                    
                    Database::query("
                        INSERT INTO invoices (
                            invoice_number, client_id, status, subtotal, tax, tax_rate, total, 
                            amount_paid, currency, due_date, payment_method, notes, created_at
                        ) VALUES (?, ?, 'unpaid', ?, ?, ?, ?, 0, 'TRY', ?, 'bank_transfer', ?, NOW())
                    ", [
                        $invoiceNumber,
                        $domain['client_id'],
                        $subtotal,
                        $tax,
                        $taxRate,
                        $total,
                        $dueDate,
                        'Domain Yenileme: ' . $domain['domain']
                    ]);
                    
                    $invoiceId = Database::getInstance()->lastInsertId();
                    
                    Database::query("
                        INSERT INTO invoice_items (
                            invoice_id, type, description, quantity, unit_price, tax, total
                        ) VALUES (?, 'domain', ?, 1, ?, ?, ?, ?)
                    ", [
                        $invoiceId,
                        $domain['domain'] . ' (Yenileme)',
                        $subtotal,
                        $tax,
                        $total
                    ]);
                    
                    // Domain expiry date'i güncelle (fatura ödenince tam olarak güncellenecek)
                    Database::query("
                        UPDATE domains 
                        SET next_due_date = ? 
                        WHERE id = ?
                    ", [$newExpiryDate, $domain['id']]);
                    
                    $results['auto_renewed']++;
                }
            } else {
                // Otomatik yenileme yoksa domain'i expired olarak işaretle
                Database::query("
                    UPDATE domains 
                    SET status = 'expired' 
                    WHERE id = ?
                ", [$domain['id']]);
                
                // Müşteriye bildirim gönder
                try {
                    Mail::sendTemplate('domain_expired', $domain['email'], [
                        'client_name' => $domain['first_name'] . ' ' . $domain['last_name'],
                        'domain' => $domain['domain'],
                        'expiry_date' => date('d.m.Y', strtotime($domain['expiry_date']))
                    ], $domain['first_name']);
                } catch (Exception $e) {
                    // Mail hatası
                }
                
                $results['expired']++;
            }
        } catch (Exception $e) {
            $results['errors'][] = "Domain ID {$domain['id']}: " . $e->getMessage();
        }
    }
    
    return $results;
}

/**
 * Destek taleplerini yükselt (escalate)
 */
function escalateTickets(): array {
    $escalationHours = (int)Settings::get('ticket_escalation_hours', '24');
    
    $tickets = Database::fetchAll("
        SELECT t.*, c.first_name, c.last_name, c.email
        FROM tickets t
        LEFT JOIN clients c ON t.client_id = c.id
        WHERE t.status IN ('open', 'customer_reply')
        AND t.last_reply_at IS NOT NULL
        AND TIMESTAMPDIFF(HOUR, t.last_reply_at, NOW()) >= ?
        AND t.priority != 'urgent'
    ", [$escalationHours]);
    
    $escalated = 0;
    foreach ($tickets as $ticket) {
        try {
            // Önceliği yükselt
            $newPriority = match($ticket['priority']) {
                'low' => 'medium',
                'medium' => 'high',
                'high' => 'urgent',
                default => 'high'
            };
            
            Database::query("
                UPDATE tickets 
                SET priority = ?, updated_at = NOW() 
                WHERE id = ?
            ", [$newPriority, $ticket['id']]);
            
            // Admin'lere bildirim gönder (opsiyonel)
            // Mail::sendTemplate('ticket_escalated', ...);
            
            $escalated++;
        } catch (Exception $e) {
            // Hata durumunda devam et
        }
    }
    
    return ['escalated' => $escalated, 'total' => count($tickets)];
}

/**
 * Affiliate komisyonlarını hesapla ve öde
 */
function processAffiliateCommissions(): array {
    require_once __DIR__ . '/includes/Affiliate.php';
    
    $results = [
        'processed' => 0,
        'paid' => 0,
        'errors' => []
    ];
    
    try {
        // Ödenmiş faturalar için komisyon hesapla
        $paidInvoices = Database::fetchAll("
            SELECT i.*, c.id as client_id
            FROM invoices i
            LEFT JOIN clients c ON i.client_id = c.id
            WHERE i.status = 'paid'
            AND i.paid_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            AND NOT EXISTS (
                SELECT 1 FROM affiliate_commissions ac
                WHERE ac.invoice_id = i.id
            )
        ");
        
        foreach ($paidInvoices as $invoice) {
            try {
                Affiliate::processCommission(
                    (int)$invoice['client_id'],
                    null,
                    (int)$invoice['id'],
                    (float)$invoice['total']
                );
                $results['processed']++;
            } catch (Exception $e) {
                $results['errors'][] = "Invoice ID {$invoice['id']}: " . $e->getMessage();
            }
        }
        
        // Bekleyen çekim taleplerini işle (opsiyonel - manuel onay gerekebilir)
        // Bu kısım admin onayı gerektirdiği için şimdilik yorumda
        
    } catch (Exception $e) {
        $results['errors'][] = $e->getMessage();
    }
    
    return $results;
}

/**
 * E-posta kampanyaları gönder
 */
function sendEmailCampaigns(): array {
    // E-posta kampanyası sistemi için gerekli tablolar yoksa atla
    try {
        Database::query("SELECT 1 FROM email_campaigns LIMIT 1");
    } catch (Exception $e) {
        return ['sent' => 0, 'message' => 'Email campaign system not available'];
    }
    
    $campaigns = Database::fetchAll("
        SELECT * FROM email_campaigns
        WHERE status = 'scheduled'
        AND send_at <= NOW()
        AND sent_count < total_recipients
    ");
    
    $sent = 0;
    foreach ($campaigns as $campaign) {
        // Kampanya gönderim mantığı buraya eklenecek
        // Şimdilik basit bir implementasyon
        $sent++;
    }
    
    return ['sent' => $sent, 'campaigns' => count($campaigns)];
}

/**
 * Veritabanı yedekleme oluştur
 */
function createDatabaseBackup(): array {
    $backupDir = __DIR__ . '/backups/';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $config = require __DIR__ . '/config/config.php';
    $dbName = $config['DB_NAME'];
    $dbUser = $config['DB_USER'];
    $dbPass = $config['DB_PASS'];
    $dbHost = $config['DB_HOST'];
    
    $backupFile = $backupDir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    // mysqldump komutu (sunucuda mysqldump olmalı)
    $command = sprintf(
        'mysqldump -h %s -u %s -p%s %s > %s 2>&1',
        escapeshellarg($dbHost),
        escapeshellarg($dbUser),
        escapeshellarg($dbPass),
        escapeshellarg($dbName),
        escapeshellarg($backupFile)
    );
    
    exec($command, $output, $returnVar);
    
    if ($returnVar === 0 && file_exists($backupFile)) {
        $fileSize = filesize($backupFile);
        
        // Eski yedekleri temizle (30 günden eski)
        $oldBackups = glob($backupDir . 'backup_*.sql');
        foreach ($oldBackups as $oldBackup) {
            if (filemtime($oldBackup) < strtotime('-30 days')) {
                @unlink($oldBackup);
            }
        }
        
        return [
            'success' => true,
            'file' => basename($backupFile),
            'size' => $fileSize,
            'message' => 'Backup created successfully'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Backup failed: ' . implode("\n", $output),
            'return_code' => $returnVar
        ];
    }
}

/**
 * Cron expression'dan sonraki çalışma zamanını hesapla
 * Basit implementasyon - sadece saatlik, günlük, haftalık, aylık destekler
 */
function calculateNextRun(string $schedule): string {
    $parts = explode(' ', trim($schedule));
    if (count($parts) < 5) {
        return date('Y-m-d H:i:s', strtotime('+1 hour'));
    }
    
    // Basit hesaplama - daha gelişmiş bir cron parser kullanılabilir
    // Şimdilik sadece saatlik görevler için
    if ($parts[0] === '0' && $parts[1] === '*') {
        // Her saat başı
        return date('Y-m-d H:i:s', strtotime('+1 hour'));
    } elseif ($parts[0] === '0' && $parts[1] !== '*') {
        // Günlük belirli saatte
        $hour = (int)$parts[1];
        $next = new DateTime();
        $next->setTime($hour, 0);
        if ($next <= new DateTime()) {
            $next->modify('+1 day');
        }
        return $next->format('Y-m-d H:i:s');
    }
    
    // Varsayılan: 1 saat sonra
    return date('Y-m-d H:i:s', strtotime('+1 hour'));
}

