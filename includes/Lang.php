<?php

/**
 * WHMVM - Dil ve çeviri katmanı
 *
 * Kullanım:
 *   Lang::init();                          // header'da bir kez
 *   Lang::t('hero.baslik', 'Hoş geldiniz') // çeviri yoksa Türkçe varsayılanı basar
 *
 * Çevirisi olmayan anahtar ilk çağrıldığında varsayılan diline kaydedilir;
 * böylece admin panelindeki çeviri listesi kendiliğinden dolar.
 */

declare(strict_types=1);

class Lang
{
    private const COOKIE = 'whmvm_lang';
    private const COOKIE_GUN = 180;

    private static bool $hazir = false;
    private static string $aktif = 'tr';
    private static string $varsayilan = 'tr';
    private static string $yon = 'ltr';

    /** @var array<string,array{code:string,name:string,native_name:string,flag:?string,direction:string,is_default:int}> */
    private static array $diller = [];

    /** @var array<string,string> */
    private static array $sozluk = [];

    /** @var array<string,string> Bu istekte DB'de bulunamayan anahtarlar */
    private static array $eksikler = [];

    private static bool $tabloVar = true;

    /**
     * Aktif dili belirler ve sözlüğü yükler. Birden fazla çağrı zararsızdır.
     */
    public static function init(): void
    {
        if (self::$hazir) {
            return;
        }
        self::$hazir = true;

        self::dilleriYukle();
        if (!self::$tabloVar) {
            return;
        }

        self::$aktif = self::dilSec();
        self::$yon = self::$diller[self::$aktif]['direction'] ?? 'ltr';
        self::sozlukYukle();
    }

    /**
     * Çeviriyi döndürür. Anahtar yoksa $varsayilan yazılır ve kayda alınır.
     * :isim biçimindeki yer tutucular $degiskenler ile değiştirilir.
     */
    public static function t(string $anahtar, string $varsayilan = '', array $degiskenler = []): string
    {
        self::init();

        $metin = self::$sozluk[$anahtar] ?? null;

        if ($metin === null || $metin === '') {
            $metin = $varsayilan !== '' ? $varsayilan : $anahtar;
            if ($varsayilan !== '') {
                self::$eksikler[$anahtar] = $varsayilan;
            }
        }

        foreach ($degiskenler as $ad => $deger) {
            $metin = str_replace(':' . $ad, (string) $deger, $metin);
        }

        return $metin;
    }

    /**
     * Veritabanından gelen metinleri çevirir. Anahtar metinden türetilir:
     * tv('menu', 'Web Hosting') -> menu.web-hosting
     */
    public static function tv(string $onEk, string $metin): string
    {
        $metin = trim($metin);
        if ($metin === '') {
            return $metin;
        }
        return self::t($onEk . '.' . self::slug($metin), $metin);
    }

    /** Türkçe karakterleri de çözen basit anahtar üretici. */
    public static function slug(string $metin): string
    {
        $tr = ['ı' => 'i', 'İ' => 'i', 'ş' => 's', 'Ş' => 's', 'ğ' => 'g', 'Ğ' => 'g',
               'ü' => 'u', 'Ü' => 'u', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c'];
        $metin = strtr($metin, $tr);
        $metin = mb_strtolower($metin, 'UTF-8');
        $metin = preg_replace('/[^a-z0-9]+/u', '-', $metin) ?? '';
        $metin = trim($metin, '-');
        return substr($metin, 0, 80);
    }
    /** Çeviriyi HTML'e güvenli biçimde basar. */
    public static function e(string $anahtar, string $varsayilan = '', array $degiskenler = []): string
    {
        return htmlspecialchars(self::t($anahtar, $varsayilan, $degiskenler), ENT_QUOTES, 'UTF-8');
    }

    public static function kod(): string
    {
        self::init();
        return self::$aktif;
    }

    public static function yon(): string
    {
        self::init();
        return self::$yon;
    }

    public static function rtlMi(): bool
    {
        return self::yon() === 'rtl';
    }

    /** @return array<string,array> Aktif diller, sort_order sırasıyla */
    public static function diller(): array
    {
        self::init();
        return self::$diller;
    }

    public static function aktifDil(): array
    {
        self::init();
        return self::$diller[self::$aktif] ?? [
            'code' => 'tr',
            'name' => 'Türkçe',
            'native_name' => 'Türkçe',
            'flag' => '🇹🇷',
            'direction' => 'ltr',
        ];
    }

    /**
     * Mevcut URL'yi verilen dile çevirir (diğer parametreler korunur).
     */
    public static function url(string $kod): string
    {
        $yol = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
        $q = [];
        if (!empty($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $q);
        }
        $q['lang'] = $kod;
        return $yol . '?' . http_build_query($q);
    }

    /**
     * İstek sonunda eksik anahtarları varsayılan dile yazar.
     * Sayfa çıktısını etkilememesi için hatalar yutulur.
     */
    public static function eksikleriKaydet(): void
    {
        if (!self::$tabloVar || empty(self::$eksikler)) {
            return;
        }

        $kayitlar = self::$eksikler;
        self::$eksikler = [];

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare(
                "INSERT INTO translations (lang_code, t_key, t_value)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE t_key = t_key"
            );
            foreach ($kayitlar as $anahtar => $deger) {
                $stmt->execute([self::$varsayilan, $anahtar, $deger]);
            }
        } catch (Throwable $e) {
            // Çeviri kaydı sayfayı bozmamalı
        }
    }

    /* ---------------------------------------------------------- */

    private static function dilleriYukle(): void
    {
        try {
            $satirlar = Database::fetchAll(
                "SELECT code, name, native_name, flag, direction, is_default
                 FROM languages WHERE is_active = 1 ORDER BY sort_order, id"
            );
        } catch (Throwable $e) {
            // Tablo henüz yoksa site Türkçe çalışmaya devam eder
            self::$tabloVar = false;
            self::$diller = [];
            return;
        }

        foreach ($satirlar as $s) {
            self::$diller[$s['code']] = $s;
            if ((int) $s['is_default'] === 1) {
                self::$varsayilan = $s['code'];
            }
        }

        if (empty(self::$diller)) {
            self::$tabloVar = false;
        }
    }

    /** ?lang= → cookie → varsayılan */
    private static function dilSec(): string
    {
        $istek = isset($_GET['lang']) ? strtolower(trim((string) $_GET['lang'])) : '';
        if ($istek !== '' && isset(self::$diller[$istek])) {
            self::cerezYaz($istek);
            return $istek;
        }

        $cerez = isset($_COOKIE[self::COOKIE]) ? strtolower(trim((string) $_COOKIE[self::COOKIE])) : '';
        if ($cerez !== '' && isset(self::$diller[$cerez])) {
            return $cerez;
        }

        return isset(self::$diller[self::$varsayilan]) ? self::$varsayilan : array_key_first(self::$diller);
    }

    private static function cerezYaz(string $kod): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie(self::COOKIE, $kod, [
            'expires' => time() + self::COOKIE_GUN * 86400,
            'path' => '/',
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $kod;
    }

    private static function sozlukYukle(): void
    {
        try {
            $satirlar = Database::fetchAll(
                "SELECT t_key, t_value FROM translations WHERE lang_code = ?",
                [self::$aktif]
            );
        } catch (Throwable $e) {
            return;
        }

        foreach ($satirlar as $s) {
            if ($s['t_value'] !== null && $s['t_value'] !== '') {
                self::$sozluk[$s['t_key']] = $s['t_value'];
            }
        }
    }
}
