<?php
/**
 * VHM - Fatura işlemleri
 *
 * "Ödendi işaretle" ve "iptal et" hem invoices.php hem invoice-view.php
 * üzerinden yapılabiliyor. Kod iki yerde ayrı ayrı yazılınca biri
 * transactions kaydını oluşturuyor, diğeri oluşturmuyordu; Ödemeler
 * sayfası da buna bağlı olarak eksik görünüyordu. Tek kaynağa taşındı.
 *
 * Yöntemler başarısızlıkta RuntimeException fırlatır; çağıran sayfa
 * mesajı kendi biçiminde gösterir.
 */

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class Fatura
{
    public const DURUMLAR = [
        'draft' => 'Taslak',
        'unpaid' => 'Ödenmedi',
        'paid' => 'Ödendi',
        'cancelled' => 'İptal',
        'refunded' => 'İade',
        'collections' => 'Takipte',
    ];

    public const ODEME_YONTEMLERI = [
        'bank_transfer' => 'Havale / EFT',
        'credit_card' => 'Kredi kartı',
        'paytr' => 'PayTR',
        'cash' => 'Nakit',
        'balance' => 'Bakiye',
        'other' => 'Diğer',
    ];

    /**
     * Faturayı ödendi olarak işaretler ve ödeme kaydını oluşturur.
     *
     * Fatura satırı FOR UPDATE ile kilitlenir; iki yönetici aynı anda
     * tıklarsa ikinci istek "zaten ödenmiş" ile durur, çift ödeme kaydı
     * oluşmaz.
     *
     * @return array Güncellenmiş fatura satırı
     */
    public static function odendiIsaretle(int $id, string $yontem = 'bank_transfer'): array
    {
        if (!isset(self::ODEME_YONTEMLERI[$yontem])) {
            $yontem = 'bank_transfer';
        }

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $fatura = Database::fetch(
                "SELECT i.*, c.first_name, c.last_name, c.email
                   FROM invoices i
                   LEFT JOIN clients c ON c.id = i.client_id
                  WHERE i.id = ? FOR UPDATE",
                [$id]
            );

            if (!$fatura) {
                throw new RuntimeException('Fatura bulunamadı.');
            }
            if ($fatura['status'] === 'paid') {
                throw new RuntimeException('Bu fatura zaten ödenmiş görünüyor.');
            }
            if ($fatura['status'] === 'cancelled') {
                throw new RuntimeException('İptal edilmiş fatura ödendi olarak işaretlenemez.');
            }

            $tutar = (float) $fatura['total'];

            Database::query(
                "UPDATE invoices
                    SET status = 'paid', amount_paid = ?, paid_date = NOW(), payment_method = ?
                  WHERE id = ? AND status <> 'paid'",
                [$tutar, $yontem, $id]
            );

            /* Bu kayıt eskiden hiçbir yerde oluşturulmuyordu; Ödemeler
               sayfası bu yüzden boştu. */
            Database::insert('transactions', [
                'client_id' => (int) $fatura['client_id'],
                'invoice_id' => $id,
                'transaction_id' => 'MAN-' . $id . '-' . date('YmdHis'),
                'gateway' => $yontem,
                'type' => 'payment',
                'amount' => $tutar,
                'currency' => (string) ($fatura['currency'] ?: 'TRY'),
                'status' => 'success',
                'description' => 'Fatura ' . $fatura['invoice_number']
                    . ' panelden ödendi olarak işaretlendi.',
            ]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            error_log('Fatura ödendi işaretlenemedi: ' . $e->getMessage());
            throw new RuntimeException('Fatura güncellenemedi, hiçbir değişiklik kaydedilmedi.');
        }

        /* Buradan sonrası para işlemi değil. Biri patlarsa fatura yine
           ödenmiş kalır; hatayı loglayıp devam ediyoruz. */
        self::odemeSonrasi($fatura, $tutar);

        $fatura['status'] = 'paid';
        $fatura['amount_paid'] = $tutar;
        $fatura['payment_method'] = $yontem;

        return $fatura;
    }

    /** Komisyon, müşteri günlüğü ve bilgilendirme e-postası */
    private static function odemeSonrasi(array $fatura, float $tutar): void
    {
        $id = (int) $fatura['id'];

        try {
            require_once __DIR__ . '/Affiliate.php';
            Affiliate::processCommission((int) $fatura['client_id'], null, $id, $tutar);
        } catch (Throwable $e) {
            error_log('Satış ortaklığı komisyonu işlenemedi: ' . $e->getMessage());
        }

        try {
            require_once __DIR__ . '/ClientLog.php';
            ClientLog::invoicePaid(
                (int) $fatura['client_id'],
                (string) $fatura['invoice_number'],
                $tutar,
                (string) ($fatura['currency'] ?? 'TRY')
            );
        } catch (Throwable $e) {
            error_log('Müşteri logu yazılamadı: ' . $e->getMessage());
        }

        if (empty($fatura['email'])) {
            return;
        }

        try {
            require_once __DIR__ . '/Mail.php';
            Mail::sendTemplate('invoice_paid', (string) $fatura['email'], [
                'client_name' => trim((string) $fatura['first_name'] . ' ' . (string) $fatura['last_name']),
                'invoice_id' => (string) $fatura['invoice_number'],
                'payment_amount' => number_format($tutar, 2, ',', '.'),
                'payment_date' => date('d.m.Y H:i'),
            ], (string) $fatura['first_name']);
        } catch (Throwable $e) {
            error_log('Ödeme bildirimi gönderilemedi: ' . $e->getMessage());
        }
    }

    /** Faturayı iptal eder. Ödenmiş fatura iptal edilemez; iade edilir. */
    public static function iptalEt(int $id): void
    {
        $fatura = Database::fetch("SELECT status FROM invoices WHERE id = ?", [$id]);

        if (!$fatura) {
            throw new RuntimeException('Fatura bulunamadı.');
        }
        if ($fatura['status'] === 'paid') {
            throw new RuntimeException('Ödenmiş fatura iptal edilemez; iade kaydı oluşturun.');
        }
        if ($fatura['status'] === 'cancelled') {
            throw new RuntimeException('Fatura zaten iptal edilmiş.');
        }

        try {
            Database::query("UPDATE invoices SET status = 'cancelled' WHERE id = ?", [$id]);
        } catch (Throwable $e) {
            error_log('Fatura iptal edilemedi: ' . $e->getMessage());
            throw new RuntimeException('Fatura iptal edilemedi.');
        }
    }

    /** Fatura bildirimini müşteriye yeniden gönderir */
    public static function epostaGonder(array $fatura): void
    {
        if (empty($fatura['email'])) {
            throw new RuntimeException('Müşterinin kayıtlı e-posta adresi yok.');
        }

        require_once __DIR__ . '/Mail.php';

        $sonuc = Mail::sendTemplate('invoice_created', (string) $fatura['email'], [
            'client_name' => trim((string) $fatura['first_name'] . ' ' . (string) $fatura['last_name']),
            'invoice_id' => (string) $fatura['invoice_number'],
            'invoice_total' => number_format((float) $fatura['total'], 2, ',', '.'),
            'due_date' => !empty($fatura['due_date'])
                ? date('d.m.Y', strtotime((string) $fatura['due_date'])) : '-',
            'invoice_status' => self::DURUMLAR[$fatura['status']] ?? (string) $fatura['status'],
        ], (string) $fatura['first_name']);

        if (!$sonuc) {
            throw new RuntimeException('E-posta gönderilemedi. SMTP ayarlarını kontrol edin.');
        }
    }
}
