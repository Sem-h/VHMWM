<?php
/**
 * VHM - Sepet hesabı
 *
 * Sepetin tek doğru kaynağı. cart.php gösterir, client/checkout.php tahsil
 * eder; ikisi de bu sınıfı kullanır, böylece ekrandaki tutar ile faturaya
 * yazılan tutar ayrışamaz.
 *
 * Kalem biçimi client/cart.php tarafından üretilir:
 *   product_id, product_name, product_type, billing_cycle, price,
 *   setup_fee, config_options[], config_total
 */

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Settings.php';

final class Sepet
{
    /** Fatura dönemi etiketleri */
    public const DONEMLER = [
        'monthly' => 'Aylık',
        'quarterly' => '3 Aylık',
        'semiannually' => '6 Aylık',
        'annually' => 'Yıllık',
        'biennially' => '2 Yıllık',
        'triennially' => '3 Yıllık',
        'onetime' => 'Tek seferlik',
    ];

    /** Oturumdaki sepeti dizi olarak verir */
    public static function ham(): array
    {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        return $_SESSION['cart'];
    }

    public static function bos(): bool
    {
        return self::ham() === [];
    }

    /** Sepetten kalem çıkarır ve dizinleri yeniden sıralar */
    public static function kaldir(string|int $anahtar): bool
    {
        self::ham();
        if (!array_key_exists($anahtar, $_SESSION['cart'])) {
            return false;
        }
        unset($_SESSION['cart'][$anahtar]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        return true;
    }

    public static function temizle(): void
    {
        $_SESSION['cart'] = [];
        unset($_SESSION['promosyon']);
    }

    /** Ayarlardaki KDV oranı. tax_enabled kapalıysa 0 döner. */
    public static function vergiOrani(): float
    {
        if ((string) Settings::get('tax_enabled', '1') !== '1') {
            return 0.0;
        }
        return max(0.0, (float) Settings::get('tax_rate', 20));
    }

    /**
     * Kalemleri tek biçime getirir. Tutarlar burada yeniden hesaplanır;
     * oturumdaki 'total' alanına güvenilmez, çünkü kurulum ücreti onun
     * içinde gizli kalıyordu.
     */
    public static function kalemler(): array
    {
        $cikti = [];

        foreach (self::ham() as $anahtar => $k) {
            if (!is_array($k)) {
                continue;
            }

            $adet = max(1, (int) ($k['qty'] ?? 1));
            $birim = (float) ($k['price'] ?? 0);
            $secenekler = is_array($k['config_options'] ?? null) ? $k['config_options'] : [];

            $secenekToplam = isset($k['config_total'])
                ? (float) $k['config_total']
                : array_sum(array_map(static fn($s): float => (float) ($s['price'] ?? 0), $secenekler));

            $kurulum = (float) ($k['setup_fee'] ?? 0);

            /* Eski biçim: yalnızca 'total' tutan kalemler */
            if ($birim <= 0 && $secenekToplam <= 0 && isset($k['total'])) {
                $birim = (float) $k['total'];
            }

            $donem = (string) ($k['billing_cycle'] ?? 'monthly');
            if (!isset(self::DONEMLER[$donem])) {
                $donem = 'monthly';
            }

            $cikti[] = [
                'anahtar' => (string) $anahtar,
                'urun_id' => isset($k['product_id']) ? (int) $k['product_id'] : null,
                'ad' => (string) ($k['product_name'] ?? $k['name'] ?? 'Ürün'),
                'tur' => (string) ($k['product_type'] ?? $k['type'] ?? 'other'),
                'donem' => $donem,
                'donem_adi' => self::DONEMLER[$donem],
                'alan_adi' => $k['domain'] ?? null,
                'adet' => $adet,
                'birim' => $birim,
                'secenekler' => $secenekler,
                'secenek_toplam' => $secenekToplam,
                'kurulum' => $kurulum,
                'satir_ara' => ($birim + $secenekToplam) * $adet,
                'satir_kurulum' => $kurulum * $adet,
            ];
        }

        return $cikti;
    }

    /* ==========================================================
       Promosyon
       ========================================================== */

    /** longtext alanları (JSON ya da virgüllü) diziye çevirir */
    private static function liste(mixed $ham): array
    {
        if (is_array($ham)) {
            return array_values(array_filter(array_map('strval', $ham), static fn($v) => $v !== ''));
        }
        $ham = trim((string) $ham);
        if ($ham === '') {
            return [];
        }
        $cozulen = json_decode($ham, true);
        if (is_array($cozulen)) {
            return array_values(array_filter(array_map('strval', $cozulen), static fn($v) => $v !== ''));
        }
        return array_values(array_filter(array_map('trim', explode(',', $ham)), static fn($v) => $v !== ''));
    }

    /** Kalem bu promosyonun kapsamında mı */
    private static function kapsamda(array $kalem, array $promo): bool
    {
        $donemler = self::liste($promo['billing_cycles'] ?? '');
        if ($donemler && !in_array($kalem['donem'], $donemler, true)) {
            return false;
        }

        $kapsam = (string) ($promo['applies_to'] ?? 'all');
        if ($kapsam === 'all') {
            return true;
        }

        $idler = self::liste($promo['applies_to_ids'] ?? '');
        if (!$idler) {
            return false;
        }

        if ($kapsam === 'products') {
            return $kalem['urun_id'] !== null && in_array((string) $kalem['urun_id'], $idler, true);
        }

        if ($kapsam === 'product_groups') {
            if ($kalem['urun_id'] === null) {
                return false;
            }
            $grup = Database::fetchColumn("SELECT group_id FROM products WHERE id = ?", [$kalem['urun_id']]);
            if ($grup === false || $grup === null) {
                return false;
            }
            return in_array((string) $grup, $idler, true);
        }

        return false;
    }

    /**
     * Kodu doğrular. Geçerliyse promosyon satırını, değilse hatayı döner.
     * @return array{ok: bool, hata?: string, promo?: array}
     */
    public static function promosyonDogrula(string $kod): array
    {
        $kod = mb_strtoupper(trim($kod));
        if ($kod === '') {
            return ['ok' => false, 'hata' => 'Promosyon kodu girin.'];
        }

        try {
            $promo = Database::fetch("SELECT * FROM promotions WHERE UPPER(code) = ?", [$kod]);
        } catch (Throwable $e) {
            error_log('Promosyon okunamadı: ' . $e->getMessage());
            return ['ok' => false, 'hata' => 'Promosyon kodu şu anda doğrulanamıyor.'];
        }

        if (!$promo || (int) $promo['is_active'] !== 1) {
            return ['ok' => false, 'hata' => 'Bu kod geçerli değil.'];
        }

        $bugun = date('Y-m-d');
        if (!empty($promo['start_date']) && $promo['start_date'] > $bugun) {
            return ['ok' => false, 'hata' => 'Bu kod henüz kullanıma açılmadı.'];
        }
        if (!empty($promo['end_date']) && $promo['end_date'] < $bugun) {
            return ['ok' => false, 'hata' => 'Bu kodun süresi dolmuş.'];
        }

        $enFazla = (int) ($promo['max_uses'] ?? 0);
        if ($enFazla > 0 && (int) ($promo['uses'] ?? 0) >= $enFazla) {
            return ['ok' => false, 'hata' => 'Bu kodun kullanım hakkı dolmuş.'];
        }

        /* Yalnızca yeni müşteriler */
        if (!empty($promo['new_clients_only']) && !empty($_SESSION['client_id'])) {
            $siparis = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM orders WHERE client_id = ?",
                [(int) $_SESSION['client_id']]
            );
            if ($siparis > 0) {
                return ['ok' => false, 'hata' => 'Bu kod yalnızca ilk siparişte kullanılabilir.'];
            }
        }

        /* Müşteri başına kullanım */
        if (!empty($_SESSION['client_id'])) {
            $sinir = !empty($promo['once_per_client']) ? 1 : (int) ($promo['max_uses_per_client'] ?? 0);
            if ($sinir > 0) {
                $kullanim = (int) Database::fetchColumn(
                    "SELECT COUNT(*) FROM orders WHERE client_id = ? AND UPPER(promo_code) = ?",
                    [(int) $_SESSION['client_id'], $kod]
                );
                if ($kullanim >= $sinir) {
                    return ['ok' => false, 'hata' => 'Bu kodu daha önce kullandınız.'];
                }
            }
        }

        /* Sepette kapsamına giren kalem var mı */
        $kalemler = self::kalemler();
        $eslesen = array_filter($kalemler, static fn(array $k): bool => self::kapsamda($k, $promo));
        if (!$eslesen) {
            return ['ok' => false, 'hata' => 'Bu kod sepetinizdeki ürünler için geçerli değil.'];
        }

        return ['ok' => true, 'promo' => $promo];
    }

    /** Kodu sepete uygular */
    public static function promosyonUygula(string $kod): array
    {
        $sonuc = self::promosyonDogrula($kod);
        if ($sonuc['ok']) {
            $_SESSION['promosyon'] = mb_strtoupper(trim($kod));
        }
        return $sonuc;
    }

    public static function promosyonKaldir(): void
    {
        unset($_SESSION['promosyon']);
    }

    /** Oturumdaki kod hâlâ geçerliyse promosyon satırını verir */
    public static function promosyon(): ?array
    {
        $kod = (string) ($_SESSION['promosyon'] ?? '');
        if ($kod === '') {
            return null;
        }
        $sonuc = self::promosyonDogrula($kod);
        if (!$sonuc['ok']) {
            unset($_SESSION['promosyon']);
            return null;
        }
        return $sonuc['promo'];
    }

    /* ==========================================================
       Toplam
       ========================================================== */

    /**
     * Sepetin tüm tutarlarını hesaplar.
     *
     * @return array{kalemler: array, ara: float, kurulum: float, indirim: float,
     *               promo_kod: ?string, promo_aciklama: ?string, matrah: float,
     *               vergi_orani: float, vergi: float, genel: float}
     */
    public static function toplam(): array
    {
        $kalemler = self::kalemler();
        $promo = self::promosyon();

        $ara = 0.0;
        $kurulum = 0.0;
        foreach ($kalemler as $k) {
            $ara += $k['satir_ara'];
            $kurulum += $k['satir_kurulum'];
        }

        $indirim = 0.0;
        $aciklama = null;

        if ($promo) {
            $kapsamAra = 0.0;
            $kapsamKurulum = 0.0;
            $kapsamAdet = 0;
            foreach ($kalemler as $k) {
                if (self::kapsamda($k, $promo)) {
                    $kapsamAra += $k['satir_ara'];
                    $kapsamKurulum += $k['satir_kurulum'];
                    $kapsamAdet += $k['adet'];
                }
            }

            $deger = (float) ($promo['value'] ?? 0);

            switch ((string) $promo['type']) {
                case 'percentage':
                    $indirim = $kapsamAra * ($deger / 100);
                    $aciklama = '%' . rtrim(rtrim(number_format($deger, 2, ',', '.'), '0'), ',') . ' indirim';
                    break;

                case 'fixed':
                    $indirim = min($deger, $kapsamAra);
                    $aciklama = 'Sabit indirim';
                    break;

                case 'free_setup':
                    $indirim = $kapsamKurulum;
                    $aciklama = 'Kurulum ücreti yok';
                    break;

                case 'override':
                    /* Kapsamdaki kalemlerin birim fiyatı bu değere iner */
                    $indirim = max(0.0, $kapsamAra - ($deger * $kapsamAdet));
                    $aciklama = 'Özel fiyat';
                    break;
            }

            $indirim = round(min($indirim, $ara + $kurulum), 2);
        }

        $matrah = max(0.0, $ara + $kurulum - $indirim);
        $oran = self::vergiOrani();
        $vergi = round($matrah * ($oran / 100), 2);

        return [
            'kalemler' => $kalemler,
            'ara' => round($ara, 2),
            'kurulum' => round($kurulum, 2),
            'indirim' => $indirim,
            'promo_kod' => $promo ? (string) $promo['code'] : null,
            'promo_aciklama' => $aciklama,
            'matrah' => round($matrah, 2),
            'vergi_orani' => $oran,
            'vergi' => $vergi,
            'genel' => round($matrah + $vergi, 2),
        ];
    }

    /** Sipariş tamamlandığında promosyonun kullanım sayacını artırır */
    public static function promosyonKullanildi(?string $kod): void
    {
        if ($kod === null || $kod === '') {
            return;
        }
        try {
            Database::query("UPDATE promotions SET uses = uses + 1 WHERE UPPER(code) = ?", [mb_strtoupper($kod)]);
        } catch (Throwable $e) {
            error_log('Promosyon sayacı artırılamadı: ' . $e->getMessage());
        }
    }
}
