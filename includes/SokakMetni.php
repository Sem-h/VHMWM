<?php

declare(strict_types=1);

/**
 * Yapıştırılan sokak listesini temizler.
 *
 * PTT posta kodu sorgusundan kopyalanan satırlar genelde sütunlu ve büyük harflidir:
 *     EMEK ADNAN MENDERES MAH<TAB>1. SK<TAB>16180
 * Bu sınıf satırdan sokak adını ayıklar, istenirse büyük harfi düzeltip
 * CD / SK gibi kısaltmaları açar.
 */
class SokakMetni
{
    /** PTT kısaltmaları -> açık yazım */
    private const KISALTMA = [
        'CAD' => 'Caddesi',
        'CAD.' => 'Caddesi',
        'CD' => 'Caddesi',
        'CD.' => 'Caddesi',
        'SOK' => 'Sokak',
        'SOK.' => 'Sokak',
        'SK' => 'Sokak',
        'SK.' => 'Sokak',
        'BLV' => 'Bulvarı',
        'BLV.' => 'Bulvarı',
        'BULV' => 'Bulvarı',
        'BULV.' => 'Bulvarı',
        'MH' => 'Mahallesi',
        'MH.' => 'Mahallesi',
        'MAH' => 'Mahallesi',
        'MAH.' => 'Mahallesi',
        'CIK' => 'Çıkmazı',
        'ÇIKM' => 'Çıkmazı',
        'MEYD' => 'Meydanı',
    ];

    /** Türkçe küçük harf: I->ı, İ->i ayrımı korunur */
    public static function kucult(string $metin): string
    {
        $metin = str_replace(['I', 'İ'], ['ı', 'i'], $metin);
        return mb_strtolower($metin, 'UTF-8');
    }

    /** Türkçe baş harf büyütme: i->İ, ı->I */
    private static function bastaBuyut(string $kelime): string
    {
        if ($kelime === '') {
            return $kelime;
        }
        $ilk = mb_substr($kelime, 0, 1, 'UTF-8');
        $kalan = mb_substr($kelime, 1, null, 'UTF-8');
        $ilk = str_replace(['i', 'ı'], ['İ', 'I'], $ilk);
        return mb_strtoupper($ilk, 'UTF-8') . $kalan;
    }

    /**
     * Tek satırdan sokak adını çıkar.
     * Sütunlu satırda posta kodu ve mahalle adı atılır.
     */
    private static function satirdanAd(string $satir): string
    {
        $satir = str_replace(["\t", ';', '|'], "\t", $satir);

        if (str_contains($satir, "\t")) {
            $hucreler = array_values(array_filter(
                array_map('trim', explode("\t", $satir)),
                static fn(string $h): bool => $h !== ''
            ));

            /* Sondan başlayıp sayı olmayan ilk hücreyi al: "MAH<TAB>SOKAK<TAB>16180" -> SOKAK */
            for ($i = count($hucreler) - 1; $i >= 0; $i--) {
                if (!preg_match('/^\d+$/', $hucreler[$i])) {
                    $satir = $hucreler[$i];
                    break;
                }
            }
        }

        /* Sonda kalan posta kodu */
        $satir = preg_replace('/\s+\d{5}\s*$/u', '', $satir) ?? $satir;

        return trim(preg_replace('/\s+/u', ' ', $satir) ?? '');
    }

    /** Kısaltmaları aç ve büyük harfi normale çevir */
    private static function duzelt(string $ad): string
    {
        $parcalar = explode(' ', $ad);
        $cikti = [];

        foreach ($parcalar as $p) {
            if ($p === '') {
                continue;
            }

            $buyuk = mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $p), 'UTF-8');
            if (isset(self::KISALTMA[$buyuk])) {
                $cikti[] = self::KISALTMA[$buyuk];
                continue;
            }

            /* "1." ya da "12" gibi sıra numaraları olduğu gibi kalır */
            if (preg_match('/^\d+\.?$/', $p)) {
                $cikti[] = $p;
                continue;
            }

            $cikti[] = self::bastaBuyut(self::kucult($p));
        }

        return implode(' ', $cikti);
    }

    /**
     * Yapıştırılan metni sokak listesine çevir.
     *
     * @return array<int, string> benzersiz, sıralı
     */
    public static function ayikla(string $ham, bool $duzelt = true): array
    {
        $liste = [];

        foreach (preg_split('/\r\n|\r|\n/', $ham) ?: [] as $satir) {
            $ad = self::satirdanAd($satir);
            if ($ad === '') {
                continue;
            }
            if ($duzelt) {
                $ad = self::duzelt($ad);
            }
            $ad = mb_substr($ad, 0, 150, 'UTF-8');
            if ($ad === '') {
                continue;
            }
            /* Tekrarlar büyük/küçük harf farkına bakılmadan elenir */
            $liste[self::kucult($ad)] = $ad;
        }

        $liste = array_values($liste);
        sort($liste, SORT_NATURAL | SORT_FLAG_CASE);

        return $liste;
    }
}
