<?php
/**
 * WHMVM - Sipariş Log Helper
 * Sipariş işlemlerini loglar
 */
declare(strict_types=1);

class OrderLog {
    /**
     * Sipariş logu kaydet
     */
    public static function log(
        int $orderId,
        string $action,
        ?string $description = null,
        ?string $oldStatus = null,
        ?string $newStatus = null,
        ?int $adminId = null,
        ?int $clientId = null
    ): void {
        try {
            // Tablo yoksa oluştur
            self::ensureTable();
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            
            Database::query("
                INSERT INTO order_logs (order_id, client_id, action, description, old_status, new_status, admin_id, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ", [
                $orderId,
                $clientId,
                $action,
                $description,
                $oldStatus,
                $newStatus,
                $adminId,
                $ipAddress
            ]);
        } catch (Throwable $e) {
            // Log hatası işlemi engellemesin
            error_log("OrderLog::log() error: " . $e->getMessage());
        }
    }
    
    /**
     * Sipariş oluşturuldu
     */
    public static function orderCreated(int $orderId, int $clientId, string $orderNumber, float $total): void {
        self::log(
            $orderId,
            'order_created',
            "Sipariş oluşturuldu: {$orderNumber} (Toplam: " . number_format($total, 2) . " ₺)",
            null,
            'pending',
            null,
            $clientId
        );
    }
    
    /**
     * Sipariş durumu değişti
     */
    public static function statusChanged(int $orderId, string $oldStatus, string $newStatus, ?int $adminId = null, ?int $clientId = null): void {
        self::log(
            $orderId,
            'status_changed',
            "Sipariş durumu değiştirildi: {$oldStatus} → {$newStatus}",
            $oldStatus,
            $newStatus,
            $adminId,
            $clientId
        );
    }
    
    /**
     * Sipariş onaylandı
     */
    public static function orderApproved(int $orderId, ?int $adminId = null, ?int $clientId = null): void {
        self::log(
            $orderId,
            'order_approved',
            "Sipariş onaylandı ve aktif hale getirildi",
            'pending',
            'active',
            $adminId,
            $clientId
        );
    }
    
    /**
     * Sipariş iptal edildi
     */
    public static function orderCancelled(int $orderId, ?string $reason = null, ?int $adminId = null, ?int $clientId = null): void {
        $desc = "Sipariş iptal edildi";
        if ($reason) {
            $desc .= ": " . $reason;
        }
        self::log(
            $orderId,
            'order_cancelled',
            $desc,
            null,
            'cancelled',
            $adminId,
            $clientId
        );
    }
    
    /**
     * Ödeme alındı
     */
    public static function paymentReceived(int $orderId, float $amount, ?int $clientId = null): void {
        self::log(
            $orderId,
            'payment_received',
            "Ödeme alındı: " . number_format($amount, 2) . " ₺",
            null,
            null,
            null,
            $clientId
        );
    }
    
    /**
     * Hizmet oluşturuldu
     */
    public static function serviceCreated(int $orderId, int $serviceId, ?int $adminId = null, ?int $clientId = null): void {
        self::log(
            $orderId,
            'service_created',
            "Sipariş için hizmet oluşturuldu (Hizmet ID: {$serviceId})",
            null,
            null,
            $adminId,
            $clientId
        );
    }
    
    /**
     * Tablo yoksa oluştur
     */
    private static function ensureTable(): void {
        try {
            Database::query("SELECT 1 FROM order_logs LIMIT 1");
        } catch (Throwable $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS order_logs (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    order_id INT UNSIGNED,
                    client_id INT UNSIGNED,
                    action VARCHAR(100) NOT NULL,
                    description TEXT,
                    old_status VARCHAR(50),
                    new_status VARCHAR(50),
                    admin_id INT UNSIGNED,
                    ip_address VARCHAR(45),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_order (order_id),
                    INDEX idx_client (client_id),
                    INDEX idx_action (action),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }
}

