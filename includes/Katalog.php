<?php
/**
 * VHM - Katalog yardımcıları
 *
 * Ürün, grup ve tür sayfalarının hepsi kısa ad (slug) üretiyordu ama
 * her biri farklı şekilde: preg_replace('/[^a-zA-Z0-9]+/', '-', $ad)
 * Türkçe harfleri tamamen siliyordu; "Ürün Paketi" → "-r-n-paketi".
 * Ayrıca slug sütunu UNIQUE olduğu için aynı adla ikinci kayıt
 * yakalanmamış bir SQL hatasına düşüyordu.
 */

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class Katalog
{
    /** products.type enum'u ile birebir aynı */
    public const URUN_TIPLERI = [
        'hosting' => 'Web hosting',
        'vps' => 'VPS sunucu',
        'vds' => 'VDS sunucu',
        'dedicated' => 'Fiziksel sunucu',
        'domain' => 'Alan adı',
        'ssl' => 'SSL sertifikası',
        'other' => 'Diğer',
    ];

    public const DONEM_SUTUNLARI = [
        'price_monthly' => 'Aylık',
        'price_quarterly' => '3 aylık',
        'price_semiannually' => '6 aylık',
        'price_annually' => 'Yıllık',
        'price_biennially' => '2 yıllık',
        'price_triennially' => '3 yıllık',
    ];

    private const TR_HARFLER = [
        'ş' => 's', 'Ş' => 's', 'ı' => 'i', 'I' => 'i', 'İ' => 'i',
        'ğ' => 'g', 'Ğ' => 'g', 'ü' => 'u', 'Ü' => 'u',
        'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c',
    ];

    /** Türkçe harfleri koruyarak kısa ad üretir */
    public static function slugYap(string $ad): string
    {
        $ad = strtr($ad, self::TR_HARFLER);
        $ad = mb_strtolower($ad, 'UTF-8');
        $ad = preg_replace('/[^a-z0-9]+/', '-', $ad) ?? '';

        return trim($ad, '-');
    }

    /**
     * Tabloda benzersiz kısa ad üretir. Çakışırsa sonuna -2, -3 ekler.
     *
     * @param string   $tablo     products veya product_groups
     * @param int|null $haricId   Güncellemede kendi kaydını dışarıda bırakır
     */
    public static function benzersizSlug(string $ad, string $tablo, ?int $haricId = null): string
    {
        if (!in_array($tablo, ['products', 'product_groups'], true)) {
            throw new InvalidArgumentException('Bilinmeyen tablo: ' . $tablo);
        }

        $kok = self::slugYap($ad);
        if ($kok === '') {
            $kok = 'kayit';
        }

        $slug = $kok;
        $sayac = 1;

        while (true) {
            $sql = "SELECT id FROM `$tablo` WHERE slug = ?";
            $par = [$slug];

            if ($haricId !== null) {
                $sql .= " AND id <> ?";
                $par[] = $haricId;
            }

            if (!Database::fetch($sql, $par)) {
                return $slug;
            }

            $sayac++;
            $slug = $kok . '-' . $sayac;
        }
    }

    /**
     * Ürünü kullanan, sonlandırılmamış hizmet sayısı.
     * products silinince services.product_id NULL'a düşüyor; müşteri
     * ödemeye devam ederken hizmet "Ürün silinmiş" görünüyordu.
     */
    public static function urunKullanimi(int $urunId): int
    {
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM services
              WHERE product_id = ? AND status IN ('pending','active','suspended')",
            [$urunId]
        );
    }

    /** Gruptaki ürün sayısı */
    public static function gruptakiUrun(int $grupId): int
    {
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM products WHERE group_id = ?",
            [$grupId]
        );
    }

    /** Boş olmayan fiyatları döndürür: ['Aylık' => 199.0, ...] */
    public static function fiyatlar(array $urun): array
    {
        $liste = [];

        foreach (self::DONEM_SUTUNLARI as $sutun => $ad) {
            if (isset($urun[$sutun]) && $urun[$sutun] !== null && (float) $urun[$sutun] > 0) {
                $liste[$ad] = (float) $urun[$sutun];
            }
        }

        return $liste;
    }

    /** Fiyat girdisini doğrular; boş ise null döner */
    public static function fiyatOku(mixed $ham): ?float
    {
        $ham = trim((string) $ham);

        if ($ham === '') {
            return null;
        }

        $ham = str_replace(',', '.', $ham);

        if (!is_numeric($ham)) {
            throw new RuntimeException('Fiyat sayı olmalı.');
        }

        $deger = (float) $ham;

        if ($deger < 0) {
            throw new RuntimeException('Fiyat eksi olamaz.');
        }
        if ($deger > 9999999999999) {
            throw new RuntimeException('Fiyat çok büyük.');
        }

        return $deger;
    }
}
