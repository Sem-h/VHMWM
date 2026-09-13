<?php
/**
 * WHMVM - Affiliate (Satış Ortaklığı) Helper Sınıfı
 */
declare(strict_types=1);

class Affiliate
{
    private const COOKIE_NAME = 'whmvm_affiliate';
    private const DEFAULT_COOKIE_DAYS = 30;
    
    /**
     * Benzersiz affiliate kodu oluştur
     */
    public static function generateCode(): string
    {
        do {
            $code = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
            $exists = Database::fetchColumn("SELECT id FROM affiliates WHERE affiliate_code = ?", [$code]);
        } while ($exists);
        
        return $code;
    }
    
    /**
     * Affiliate kaydı oluştur
     */
    public static function register(int $clientId, array $data = []): ?int
    {
        // Zaten affiliate mi kontrol et
        $existing = Database::fetchColumn("SELECT id FROM affiliates WHERE client_id = ?", [$clientId]);
        if ($existing) {
            return null;
        }
        
        $code = self::generateCode();
        $autoApprove = Settings::get('affiliate_auto_approve', '0') === '1';
        $defaultCommission = (float)Settings::get('affiliate_default_commission', '10');
        
        return Database::insert('affiliates', [
            'client_id' => $clientId,
            'affiliate_code' => $code,
            'status' => $autoApprove ? 'active' : 'pending',
            'commission_rate' => $data['commission_rate'] ?? $defaultCommission,
            'commission_type' => $data['commission_type'] ?? 'percentage',
            'payment_method' => $data['payment_method'] ?? 'bank_transfer',
            'payment_details' => $data['payment_details'] ?? '',
            'min_withdrawal' => (float)Settings::get('affiliate_min_withdrawal', '100'),
            'approved_at' => $autoApprove ? date('Y-m-d H:i:s') : null
        ]);
    }
    
    /**
     * Affiliate bilgilerini getir
     */
    public static function getByClientId(int $clientId): ?array
    {
        return Database::fetch("SELECT * FROM affiliates WHERE client_id = ?", [$clientId]) ?: null;
    }
    
    /**
     * Affiliate koduna göre getir
     */
    public static function getByCode(string $code): ?array
    {
        return Database::fetch("SELECT * FROM affiliates WHERE affiliate_code = ? AND status = 'active'", [$code]) ?: null;
    }
    
    /**
     * Referans linkini kaydet (cookie)
     */
    public static function trackVisit(string $code): bool
    {
        $affiliate = self::getByCode($code);
        if (!$affiliate) {
            return false;
        }
        
        // Cookie ayarla
        $cookieDays = (int)Settings::get('affiliate_cookie_days', (string)self::DEFAULT_COOKIE_DAYS);
        $expiry = time() + ($cookieDays * 24 * 60 * 60);
        setcookie(self::COOKIE_NAME, $code, $expiry, '/', '', false, true);
        
        // Ziyareti kaydet
        try {
            Database::insert('affiliate_visits', [
                'affiliate_id' => $affiliate['id'],
                'ip_address' => self::getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'referrer_url' => $_SERVER['HTTP_REFERER'] ?? '',
                'landing_page' => $_SERVER['REQUEST_URI'] ?? ''
            ]);
            
            // Toplam ziyareti güncelle
            Database::query("UPDATE affiliates SET total_visits = total_visits + 1 WHERE id = ?", [$affiliate['id']]);
        } catch (Throwable $e) {
            // Hata olursa sessizce geç
        }
        
        return true;
    }
    
    /**
     * Aktif referans kodunu al (cookie'den)
     */
    public static function getActiveReferralCode(): ?string
    {
        return $_COOKIE[self::COOKIE_NAME] ?? null;
    }
    
    /**
     * Yeni müşteri kaydında referansı işle
     */
    public static function processSignup(int $newClientId): bool
    {
        $code = self::getActiveReferralCode();
        if (!$code) {
            return false;
        }
        
        $affiliate = self::getByCode($code);
        if (!$affiliate) {
            return false;
        }
        
        // Kendi kendine referans olamaz
        if ($affiliate['client_id'] === $newClientId) {
            return false;
        }
        
        // Zaten referans edilmiş mi?
        $existing = Database::fetchColumn(
            "SELECT id FROM affiliate_referrals WHERE referred_client_id = ?", 
            [$newClientId]
        );
        if ($existing) {
            return false;
        }
        
        try {
            // Referans kaydı oluştur
            Database::insert('affiliate_referrals', [
                'affiliate_id' => $affiliate['id'],
                'referred_client_id' => $newClientId,
                'status' => 'approved' // Kayıt otomatik onaylı
            ]);
            
            // İstatistikleri güncelle
            Database::query("UPDATE affiliates SET total_signups = total_signups + 1 WHERE id = ?", [$affiliate['id']]);
            
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
    
    /**
     * Sipariş/Fatura ödendiğinde komisyon hesapla
     */
    public static function processCommission(int $clientId, int $orderId = null, int $invoiceId = null, float $amount = 0): bool
    {
        // Bu müşteri bir referans mı?
        $referral = Database::fetch(
            "SELECT ar.*, a.commission_rate, a.commission_type 
             FROM affiliate_referrals ar 
             JOIN affiliates a ON ar.affiliate_id = a.id 
             WHERE ar.referred_client_id = ? AND ar.status = 'approved' AND a.status = 'active'",
            [$clientId]
        );
        
        if (!$referral) {
            return false;
        }
        
        // Komisyon hesapla
        $commissionRate = (float)$referral['commission_rate'];
        $commissionType = $referral['commission_type'];
        
        if ($commissionType === 'percentage') {
            $commissionAmount = $amount * ($commissionRate / 100);
        } else {
            $commissionAmount = $commissionRate;
        }
        
        if ($commissionAmount <= 0) {
            return false;
        }
        
        try {
            // Komisyon kaydı oluştur
            Database::insert('affiliate_commissions', [
                'affiliate_id' => $referral['affiliate_id'],
                'referral_id' => $referral['id'],
                'order_id' => $orderId,
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'status' => 'approved',
                'description' => $orderId ? "Sipariş #$orderId komisyonu" : "Fatura #$invoiceId komisyonu",
                'approved_at' => date('Y-m-d H:i:s')
            ]);
            
            // Bakiyeyi güncelle
            Database::query(
                "UPDATE affiliates SET 
                 total_orders = total_orders + 1,
                 total_earnings = total_earnings + ?,
                 balance = balance + ?
                 WHERE id = ?",
                [$commissionAmount, $commissionAmount, $referral['affiliate_id']]
            );
            
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
    
    /**
     * Çekim talebi oluştur
     */
    public static function requestWithdrawal(int $affiliateId, float $amount): array
    {
        $affiliate = Database::fetch("SELECT * FROM affiliates WHERE id = ? AND status = 'active'", [$affiliateId]);
        
        if (!$affiliate) {
            return ['success' => false, 'message' => 'Affiliate hesabı bulunamadı.'];
        }
        
        if ($amount > $affiliate['balance']) {
            return ['success' => false, 'message' => 'Yetersiz bakiye.'];
        }
        
        if ($amount < $affiliate['min_withdrawal']) {
            return ['success' => false, 'message' => 'Minimum çekim tutarı: ' . number_format($affiliate['min_withdrawal'], 2) . ' TL'];
        }
        
        // Bekleyen talep var mı?
        $pending = Database::fetchColumn(
            "SELECT id FROM affiliate_withdrawals WHERE affiliate_id = ? AND status IN ('pending', 'processing')",
            [$affiliateId]
        );
        if ($pending) {
            return ['success' => false, 'message' => 'Bekleyen bir çekim talebiniz bulunmaktadır.'];
        }
        
        try {
            // Talep oluştur
            Database::insert('affiliate_withdrawals', [
                'affiliate_id' => $affiliateId,
                'amount' => $amount,
                'payment_method' => $affiliate['payment_method'],
                'payment_details' => $affiliate['payment_details'],
                'status' => 'pending'
            ]);
            
            // Bakiyeden düş (beklemede)
            Database::query("UPDATE affiliates SET balance = balance - ? WHERE id = ?", [$amount, $affiliateId]);
            
            return ['success' => true, 'message' => 'Çekim talebiniz alındı.'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Bir hata oluştu.'];
        }
    }
    
    /**
     * Referans linkini oluştur
     */
    public static function getReferralLink(string $code): string
    {
        return SITE_URL . '?ref=' . $code;
    }
    
    /**
     * Affiliate istatistiklerini getir
     */
    public static function getStats(int $affiliateId): array
    {
        $affiliate = Database::fetch("SELECT * FROM affiliates WHERE id = ?", [$affiliateId]);
        if (!$affiliate) {
            return [];
        }
        
        // Son 30 günlük istatistikler
        $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $monthlyVisits = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM affiliate_visits WHERE affiliate_id = ? AND created_at >= ?",
            [$affiliateId, $thirtyDaysAgo]
        );
        
        $monthlySignups = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM affiliate_referrals WHERE affiliate_id = ? AND created_at >= ?",
            [$affiliateId, $thirtyDaysAgo]
        );
        
        $monthlyEarnings = (float)Database::fetchColumn(
            "SELECT COALESCE(SUM(commission_amount), 0) FROM affiliate_commissions WHERE affiliate_id = ? AND created_at >= ? AND status IN ('approved', 'paid')",
            [$affiliateId, $thirtyDaysAgo]
        );
        
        $pendingCommissions = (float)Database::fetchColumn(
            "SELECT COALESCE(SUM(commission_amount), 0) FROM affiliate_commissions WHERE affiliate_id = ? AND status = 'pending'",
            [$affiliateId]
        );
        
        return [
            'total_visits' => (int)$affiliate['total_visits'],
            'total_signups' => (int)$affiliate['total_signups'],
            'total_orders' => (int)$affiliate['total_orders'],
            'total_earnings' => (float)$affiliate['total_earnings'],
            'total_withdrawn' => (float)$affiliate['total_withdrawn'],
            'balance' => (float)$affiliate['balance'],
            'monthly_visits' => $monthlyVisits,
            'monthly_signups' => $monthlySignups,
            'monthly_earnings' => $monthlyEarnings,
            'pending_commissions' => $pendingCommissions,
            'conversion_rate' => $affiliate['total_visits'] > 0 
                ? round(($affiliate['total_signups'] / $affiliate['total_visits']) * 100, 2) 
                : 0
        ];
    }
    
    /**
     * Gerçek IP adresini al
     */
    private static function getClientIP(): string
    {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
}

