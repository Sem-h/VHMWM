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

    /** Tür kartlarının varsayılan görünümü; product_types satırı yoksa kullanılır */
    private const TIP_VARSAYILAN = [
        'hosting' => ['fa-globe', '#f97316'],
        'vps' => ['fa-server', '#10b981'],
        'vds' => ['fa-hard-drive', '#6366f1'],
        'dedicated' => ['fa-database', '#8b5cf6'],
        'domain' => ['fa-at', '#0ea5e9'],
        'ssl' => ['fa-lock', '#22c55e'],
        'other' => ['fa-cube', '#64748b'],
    ];

    /** @var array<string,array{ad:string,ikon:string,renk:string,sira:int}>|null */
    private static ?array $tipOnbellek = null;

    /**
     * Ürün türlerinin görünümü.
     *
     * Hangi türlerin var olduğunu products.type enum'u belirler; adı,
     * simgesi ve rengi product_types tablosundan gelir. Tablo eskiden
     * yalnızca kendi yönetim sayfası tarafından okunuyordu, yani orada
     * yapılan değişiklik hiçbir yerde görünmüyordu.
     *
     * @return array<string,array{ad:string,ikon:string,renk:string,sira:int}>
     */
    public static function tipler(): array
    {
        if (self::$tipOnbellek !== null) {
            return self::$tipOnbellek;
        }

        $satirlar = [];
        try {
            foreach (Database::fetchAll("SELECT * FROM product_types") as $r) {
                $satirlar[(string) $r['slug']] = $r;
            }
        } catch (Throwable $e) {
            error_log('Ürün türleri okunamadı: ' . $e->getMessage());
        }

        $liste = [];
        $sira = 0;

        foreach (self::URUN_TIPLERI as $slug => $varsayilanAd) {
            $r = $satirlar[$slug] ?? null;
            [$ikon, $renk] = self::TIP_VARSAYILAN[$slug] ?? ['fa-cube', '#64748b'];

            $liste[$slug] = [
                'ad' => $r && trim((string) $r['label']) !== '' ? (string) $r['label'] : $varsayilanAd,
                'ikon' => $r && trim((string) $r['icon']) !== '' ? (string) $r['icon'] : $ikon,
                'renk' => $r && trim((string) $r['color']) !== '' ? (string) $r['color'] : $renk,
                'sira' => $r ? (int) $r['order_priority'] : ++$sira,
            ];
        }

        uasort($liste, static fn(array $a, array $b): int => $a['sira'] <=> $b['sira']);

        return self::$tipOnbellek = $liste;
    }

    /** Tek türün görünen adı */
    public static function tipAdi(?string $slug): string
    {
        $tipler = self::tipler();

        return $tipler[(string) $slug]['ad'] ?? (string) $slug;
    }

    /** Tek türün simgesi */
    public static function tipIkonu(?string $slug): string
    {
        $tipler = self::tipler();

        return $tipler[(string) $slug]['ikon'] ?? 'fa-cube';
    }

    /** products.type enum'unda karşılığı olmayan product_types satırları */
    public static function sahipsizTipler(): array
    {
        try {
            $hepsi = Database::fetchAll("SELECT * FROM product_types ORDER BY order_priority, label");
        } catch (Throwable $e) {
            return [];
        }

        return array_values(array_filter(
            $hepsi,
            static fn(array $r): bool => !isset(self::URUN_TIPLERI[(string) $r['slug']])
        ));
    }

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
