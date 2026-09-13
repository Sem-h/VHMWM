<?php
/**
 * WHMVM - Client Log Helper
 * Müşteri aktivitelerini loglar
 */
declare(strict_types=1);

class ClientLog
{
    /**
     * Müşteri aktivitesini logla
     * 
     * @param int|null $clientId Müşteri ID (null ise anonim)
     * @param string $action Aksiyon türü
     * @param string $description Açıklama
     * @param array $extra Ek bilgiler (JSON olarak kaydedilir)
     * @return bool
     */
    public static function log(?int $clientId, string $action, string $description, array $extra = []): bool
    {
        try {
            $ipAddress = self::getClientIP();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            // Eğer extra bilgi varsa description'a ekle
            if (!empty($extra)) {
                $description .= ' | ' . json_encode($extra, JSON_UNESCAPED_UNICODE);
            }
            
            Database::insert('client_logs', [
                'client_id' => $clientId,
                'action' => $action,
                'description' => $description,
                'ip_address' => $ipAddress,
                'user_agent' => mb_substr($userAgent, 0, 500),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return true;
        } catch (Throwable $e) {
            // Log hatası ana işlemi engellememeli
            error_log("ClientLog Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Müşteri giriş logla
     */
    public static function login(int $clientId, string $email): bool
    {
        return self::log($clientId, 'login', "Müşteri girişi yapıldı: {$email}");
    }
    
    /**
     * Başarısız giriş denemesi
     */
    public static function loginFailed(string $email): bool
    {
        return self::log(null, 'login_failed', "Başarısız giriş denemesi: {$email}");
    }
    
    /**
     * Müşteri çıkış logla
     */
    public static function logout(int $clientId): bool
    {
        return self::log($clientId, 'logout', "Müşteri çıkış yaptı");
    }
    
    /**
     * Fatura ödendi logla
     */
    public static function invoicePaid(int $clientId, string $invoiceNumber, float $amount, string $currency = 'TRY'): bool
    {
        return self::log(
            $clientId, 
            'invoice_paid', 
            "Fatura ödendi: #{$invoiceNumber} - " . number_format($amount, 2) . " {$currency}"
        );
    }
    
    /**
     * Destek talebi oluşturma logla
     */
    public static function ticketCreated(int $clientId, string $ticketNumber, string $subject): bool
    {
        return self::log(
            $clientId, 
            'ticket_created', 
            "Destek talebi oluşturuldu: #{$ticketNumber} - {$subject}"
        );
    }
    
    /**
     * Destek talebine yanıt
     */
    public static function ticketReply(int $clientId, string $ticketNumber): bool
    {
        return self::log(
            $clientId, 
            'ticket_reply', 
            "Destek talebine yanıt verildi: #{$ticketNumber}"
        );
    }
    
    /**
     * İptal talebi logla
     */
    public static function cancellationRequest(int $clientId, string $serviceName, string $cancelType): bool
    {
        $typeText = $cancelType === 'immediate' ? 'Hemen' : 'Dönem Sonunda';
        return self::log(
            $clientId, 
            'cancellation_request', 
            "İptal talebi gönderildi: {$serviceName} ({$typeText})"
        );
    }
    
    /**
     * Sipariş oluşturma logla
     */
    public static function orderCreated(int $clientId, string $orderNumber, float $total, string $currency = 'TRY'): bool
    {
        return self::log(
            $clientId, 
            'order_created', 
            "Sipariş oluşturuldu: #{$orderNumber} - " . number_format($total, 2) . " {$currency}"
        );
    }
    
    /**
     * Profil güncelleme logla
     */
    public static function profileUpdated(int $clientId, array $changes = []): bool
    {
        $desc = "Profil bilgileri güncellendi";
        if (!empty($changes)) {
            $desc .= ": " . implode(', ', $changes);
        }
        return self::log($clientId, 'profile_updated', $desc);
    }
    
    /**
     * Şifre değişikliği logla
     */
    public static function passwordChanged(int $clientId): bool
    {
        return self::log($clientId, 'password_changed', "Şifre değiştirildi");
    }
    
    /**
     * Şifre sıfırlama talebi
     */
    public static function passwordResetRequest(string $email): bool
    {
        return self::log(null, 'password_reset_request', "Şifre sıfırlama talebi: {$email}");
    }
    
    /**
     * Kayıt olma logla
     */
    public static function registered(int $clientId, string $email): bool
    {
        return self::log($clientId, 'registered', "Yeni müşteri kaydı: {$email}");
    }
    
    /**
     * Hizmet aktivasyonu logla
     */
    public static function serviceActivated(int $clientId, string $serviceName): bool
    {
        return self::log($clientId, 'service_activated', "Hizmet aktifleştirildi: {$serviceName}");
    }
    
    /**
     * Hizmet askıya alındı logla
     */
    public static function serviceSuspended(int $clientId, string $serviceName): bool
    {
        return self::log($clientId, 'service_suspended', "Hizmet askıya alındı: {$serviceName}");
    }
    
    /**
     * Gerçek IP adresini al
     */
    private static function getClientIP(): string
    {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Birden fazla IP varsa ilkini al
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

