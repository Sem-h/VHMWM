<?php
/**
 * VHM - Sipariş işlemleri
 *
 * Hizmet oluşturma kodu order-view.php içinde iki kez ayrı ayrı yazılmıştı
 * (durum "active" yapılınca ve elle "hizmet oluştur" denince). İkisinde de:
 *   - Dönem eşlemesi eksikti: 2 ve 3 yıllık ile tek seferlik yok sayılıp
 *     bir ay sonrasına vade veriliyordu.
 *   - "Zaten var mı" kontrolü yalnızca ürün kimliğine bakıyordu; aynı üründen
 *     iki alan adı sipariş edilirse ikincisi hiç oluşmuyordu.
 *   - İşlem sarmalanmamıştı; ortada hata olursa sipariş yarı açılmış kalıyordu.
 */

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class Siparis
{
    public const DURUMLAR = [
        'pending' => 'Bekliyor',
        'processing' => 'İşleniyor',
        'active' => 'Etkin',
        'fraud' => 'Şüpheli',
        'cancelled' => 'İptal',
    ];

    /** Faturalama dönemi -> vade aralığı */
    private const DONEM_ARALIK = [
        'monthly' => '+1 month',
        'quarterly' => '+3 months',
        'semiannually' => '+6 months',
        'annually' => '+1 year',
        'biennially' => '+2 years',
        'triennially' => '+3 years',
    ];

    /** Bir sonraki ödeme tarihi. Tek seferlik ürünlerde vade yoktur. */
    public static function sonrakiVade(?string $donem): ?string
    {
        $donem = (string) ($donem ?: 'monthly');

        if ($donem === 'onetime') {
            return null;
        }

        $aralik = self::DONEM_ARALIK[$donem] ?? '+1 month';

        return date('Y-m-d', strtotime($aralik));
    }

    /**
     * Siparişin hizmeti olmayan kalemleri için hizmet açar.
     *
     * @return int[] Oluşturulan hizmet kimlikleri
     */
    public static function hizmetleriOlustur(int $siparisId, ?int $adminId = null): array
    {
        $siparis = Database::fetch("SELECT * FROM orders WHERE id = ?", [$siparisId]);
        if (!$siparis) {
            throw new RuntimeException('Sipariş bulunamadı.');
        }

        $kalemler = Database::fetchAll(
            "SELECT * FROM order_items WHERE order_id = ? ORDER BY id",
            [$siparisId]
        );

        $db = Database::getInstance();
        $db->beginTransaction();
        $olusan = [];

        try {
            foreach ($kalemler as $kalem) {
                if (empty($kalem['product_id'])) {
                    continue;
                }

                /* Aynı üründen iki alan adı sipariş edilebilir; bu yüzden
                   alan adı da karşılaştırmaya giriyor. */
                $varOlan = Database::fetch(
                    "SELECT id FROM services
                      WHERE order_id = ? AND product_id = ?
                        AND (domain <=> ?)",
                    [$siparisId, (int) $kalem['product_id'], $kalem['domain'] ?: null]
                );

                if ($varOlan) {
                    continue;
                }

                $olusan[] = Database::insert('services', [
                    'client_id' => (int) $siparis['client_id'],
                    'order_id' => $siparisId,
                    'product_id' => (int) $kalem['product_id'],
                    'domain' => $kalem['domain'] ?: null,
                    'status' => 'pending',
                    'billing_cycle' => (string) ($kalem['billing_cycle'] ?: 'monthly'),
                    'amount' => (float) $kalem['unit_price'],
                    'registration_date' => date('Y-m-d'),
                    'next_due_date' => self::sonrakiVade($kalem['billing_cycle']),
                    'first_payment_amount' => (float) $kalem['total'],
                ]);
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Sipariş hizmetleri oluşturulamadı: ' . $e->getMessage());
            throw new RuntimeException('Hizmetler oluşturulamadı, hiçbir kayıt eklenmedi.');
        }

        foreach ($olusan as $hizmetId) {
            try {
                require_once __DIR__ . '/OrderLog.php';
                OrderLog::serviceCreated($siparisId, $hizmetId, $adminId, (int) $siparis['client_id']);
            } catch (Throwable $e) {
                error_log('Sipariş günlüğü yazılamadı: ' . $e->getMessage());
            }
        }

        return $olusan;
    }

    /**
     * Sipariş durumunu değiştirir. "active" yapıldığında hizmetleri açar
     * ve müşteriye bilgilendirme gönderir.
     *
     * @return int Oluşturulan hizmet sayısı
     */
    public static function durumDegistir(int $siparisId, string $yeniDurum, ?int $adminId = null): int
    {
        if (!isset(self::DURUMLAR[$yeniDurum])) {
            throw new RuntimeException('Geçersiz sipariş durumu.');
        }

        $siparis = Database::fetch(
            "SELECT o.*, c.email, c.first_name, c.last_name
               FROM orders o
               LEFT JOIN clients c ON c.id = o.client_id
              WHERE o.id = ?",
            [$siparisId]
        );

        if (!$siparis) {
            throw new RuntimeException('Sipariş bulunamadı.');
        }

        $eskiDurum = (string) $siparis['status'];
        if ($eskiDurum === $yeniDurum) {
            throw new RuntimeException('Sipariş zaten "' . self::DURUMLAR[$yeniDurum] . '" durumunda.');
        }

        Database::query("UPDATE orders SET status = ? WHERE id = ?", [$yeniDurum, $siparisId]);

        try {
            require_once __DIR__ . '/OrderLog.php';
            OrderLog::statusChanged($siparisId, $eskiDurum, $yeniDurum, $adminId, (int) $siparis['client_id']);
        } catch (Throwable $e) {
            error_log('Sipariş günlüğü yazılamadı: ' . $e->getMessage());
        }

        if ($yeniDurum !== 'active') {
            return 0;
        }

        $olusan = self::hizmetleriOlustur($siparisId, $adminId);

        if (!empty($siparis['email'])) {
            try {
                require_once __DIR__ . '/Mail.php';
                $adlar = Database::fetchAll(
                    "SELECT description FROM order_items WHERE order_id = ?",
                    [$siparisId]
                );
                Mail::sendTemplate('order_confirmed', (string) $siparis['email'], [
                    'client_name' => trim((string) $siparis['first_name'] . ' ' . (string) $siparis['last_name']),
                    'order_id' => (string) $siparis['order_number'],
                    'product_name' => implode(', ', array_column($adlar, 'description')),
                ], (string) $siparis['first_name']);
            } catch (Throwable $e) {
                error_log('Sipariş onay e-postası gönderilemedi: ' . $e->getMessage());
            }
        }

        return count($olusan);
    }
}
