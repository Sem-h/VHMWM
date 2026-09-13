<?php

declare(strict_types=1);

/**
 * TurkiyeAPI (https://api.turkiyeapi.dev) ince sarmalayıcı.
 *
 * İl / ilçe / mahalle verisi bu servisten gelir; elle ilçe yazılmaz.
 * Sokak verisi bu serviste YOKTUR (yalnızca il, ilçe, belediye, mahalle, köy).
 * Sokaklar hizmet_sokaklari tablosuna elle/ içe aktarımla girilir.
 *
 * Yanıtlar diske önbelleklenir; her sayfa açılışında dış servise gidilmez.
 */
class TurkiyeAdres
{
    private const TABAN = 'https://api.turkiyeapi.dev/v2';
    private const ONBELLEK_SURE = 86400;   // 1 gün
    private const ZAMAN_ASIMI = 8;

    /** Önbellek dizini; yoksa oluşturulur */
    private static function onbellekDizini(): string
    {
        $dizin = dirname(__DIR__) . '/storage/adres-onbellek';
        if (!is_dir($dizin)) {
            @mkdir($dizin, 0775, true);
        }
        return $dizin;
    }

    /**
     * Servise istek at, sonucu önbellekten ver.
     *
     * @param array<string, string|int> $parametre
     * @return array<string, mixed>|null
     */
    private static function istek(string $yol, array $parametre = []): ?array
    {
        $url = self::TABAN . $yol;
        if ($parametre) {
            $url .= '?' . http_build_query($parametre);
        }

        $dosya = self::onbellekDizini() . '/' . sha1($url) . '.json';
        if (is_file($dosya) && (time() - filemtime($dosya)) < self::ONBELLEK_SURE) {
            $veri = json_decode((string) file_get_contents($dosya), true);
            if (is_array($veri)) {
                return $veri;
            }
        }

        $ham = self::getir($url);
        if ($ham === null) {
            // Servis erişilemiyorsa süresi geçmiş önbellek yine de iyidir
            if (is_file($dosya)) {
                $veri = json_decode((string) file_get_contents($dosya), true);
                return is_array($veri) ? $veri : null;
            }
            return null;
        }

        $veri = json_decode($ham, true);
        if (!is_array($veri) || isset($veri['error'])) {
            return null;
        }

        @file_put_contents($dosya, $ham);
        return $veri;
    }

    /** cURL varsa onunla, yoksa akışla indir */
    private static function getir(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => self::ZAMAN_ASIMI,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => 'WHMVM/1.0 (+adres senkronu)',
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $cevap = curl_exec($ch);
            $kod = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ($cevap !== false && $kod === 200) ? (string) $cevap : null;
        }

        $baglam = stream_context_create([
            'http' => [
                'timeout' => self::ZAMAN_ASIMI,
                'header' => "Accept: application/json\r\nUser-Agent: WHMVM/1.0\r\n",
                'ignore_errors' => true,
            ],
        ]);
        $cevap = @file_get_contents($url, false, $baglam);
        return $cevap !== false ? (string) $cevap : null;
    }

    /**
     * İlleri getir.
     *
     * @return array<int, array{id:int, name:string}>
     */
    public static function iller(): array
    {
        $d = self::istek('/provinces', ['fields' => 'id,name', 'limit' => 100]);
        return $d['data'] ?? [];
    }

    /**
     * Bir ilin ilçeleri.
     *
     * @return array<int, array{id:int, name:string}>
     */
    public static function ilceler(int $ilId): array
    {
        $d = self::istek('/provinces/' . $ilId . '/districts', ['fields' => 'id,name', 'limit' => 100]);
        return $d['data'] ?? [];
    }

    /**
     * İl içinde mahalle ara. districtId verilirse o ilçeyle sınırlanır.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function mahalleAra(int $ilId, string $arama, ?int $ilceId = null, int $limit = 50): array
    {
        $parametre = [
            'provinceId' => $ilId,
            'limit' => $limit,
            'fields' => 'id,name,provinceId,districtId,postalCode',
        ];
        if (trim($arama) !== '') {
            $parametre['search'] = trim($arama);
        }
        if ($ilceId !== null) {
            $parametre['districtId'] = $ilceId;
        }

        $d = self::istek('/neighborhoods', $parametre);
        return $d['data'] ?? [];
    }

    /**
     * id -> ad eşlemesi; mahalle listesindeki districtId'yi ilçe adına çevirmek için.
     *
     * @return array<int, string>
     */
    public static function ilceAdlari(int $ilId): array
    {
        $harita = [];
        foreach (self::ilceler($ilId) as $ilce) {
            $harita[(int) $ilce['id']] = (string) $ilce['name'];
        }
        return $harita;
    }

    /** Servise erişilebiliyor mu */
    public static function erisimVar(): bool
    {
        return self::istek('/provinces', ['fields' => 'id', 'limit' => 1]) !== null;
    }
}
