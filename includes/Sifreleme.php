<?php

declare(strict_types=1);

/**
 * Sunucu parolaları gibi geri çözülebilir olması gereken değerleri şifreler.
 *
 * Anahtar config/config.php içindeki ENCRYPTION_KEY'den türetilir; bu dosya
 * depoya girmez ve her kurulumda farklıdır.
 *
 * Her şifrelemede rastgele IV üretilir ve şifreli metnin başına eklenir.
 * Sonuç ayrıca HMAC ile imzalanır; kurcalanmış veri çözülmez.
 *
 * ESKİ BİÇİM
 * ----------
 * Önceki sürüm anahtar olarak SITE_NAME kullanıyordu ("WHMVM Panel").
 * Bu değer kodda açıkça yazılı olduğu için şifreleme koruma sağlamıyordu.
 * coz() eski biçimi de okur; boolean referans $eskiBicim ile haber verir,
 * böylece çağıran taraf kaydı yeni biçimle güncelleyebilir.
 */
class Sifreleme
{
    private const YONTEM = 'AES-256-CBC';
    private const ONEK = 'v2:';

    /** Şifreleme anahtarı — ENCRYPTION_KEY'den türetilir */
    private static function anahtar(): string
    {
        if (!defined('ENCRYPTION_KEY') || ENCRYPTION_KEY === '') {
            throw new RuntimeException(
                'ENCRYPTION_KEY tanımlı değil. config/config.php dosyasını kontrol edin.'
            );
        }
        return hash('sha256', 'whmvm-veri:' . ENCRYPTION_KEY, true);
    }

    /** İmza anahtarı — şifreleme anahtarından ayrı türetilir */
    private static function imzaAnahtari(): string
    {
        return hash('sha256', 'whmvm-imza:' . ENCRYPTION_KEY, true);
    }

    /**
     * Değeri şifrele. Çıktı: "v2:" + base64(iv | şifreli | hmac)
     */
    public static function sifrele(string $deger): string
    {
        if ($deger === '') {
            return '';
        }

        $iv = random_bytes(16);
        $sifreli = openssl_encrypt($deger, self::YONTEM, self::anahtar(), OPENSSL_RAW_DATA, $iv);
        if ($sifreli === false) {
            throw new RuntimeException('Şifreleme başarısız.');
        }

        $govde = $iv . $sifreli;
        $imza = hash_hmac('sha256', $govde, self::imzaAnahtari(), true);

        return self::ONEK . base64_encode($govde . $imza);
    }

    /**
     * Şifreli değeri çöz. Eski biçimdeki kayıtlar da okunur.
     *
     * @param bool|null $eskiBicim Kayıt eski biçimdeyse true olarak doldurulur
     */
    public static function coz(string $sifreli, ?bool &$eskiBicim = null): string
    {
        $eskiBicim = false;

        if ($sifreli === '') {
            return '';
        }

        if (!str_starts_with($sifreli, self::ONEK)) {
            $eskiBicim = true;
            return self::eskiBicimCoz($sifreli);
        }

        $ham = base64_decode(substr($sifreli, strlen(self::ONEK)), true);
        if ($ham === false || strlen($ham) < 16 + 32 + 1) {
            return '';
        }

        $imza = substr($ham, -32);
        $govde = substr($ham, 0, -32);

        /* Zaman sabitli karşılaştırma: kurcalanmış veri çözülmeden reddedilir */
        if (!hash_equals(hash_hmac('sha256', $govde, self::imzaAnahtari(), true), $imza)) {
            return '';
        }

        $iv = substr($govde, 0, 16);
        $govde = substr($govde, 16);

        $sonuc = openssl_decrypt($govde, self::YONTEM, self::anahtar(), OPENSSL_RAW_DATA, $iv);

        return $sonuc === false ? '' : $sonuc;
    }

    /**
     * Eski biçim: anahtar SITE_NAME'di. Yalnızca geriye dönük okuma için.
     */
    private static function eskiBicimCoz(string $sifreli): string
    {
        if (!defined('SITE_NAME')) {
            return '';
        }
        $anahtar = SITE_NAME;
        $iv = str_pad(substr($anahtar, 0, 16), 16, '0');
        $sonuc = openssl_decrypt(base64_decode($sifreli), self::YONTEM, $anahtar, 0, $iv);

        return $sonuc === false ? '' : $sonuc;
    }

    /** Kayıt eski biçimdeyse yeni biçime taşı; değilse olduğu gibi döner */
    public static function tazele(string $sifreli): string
    {
        $acik = self::coz($sifreli, $eski);
        if (!$eski || $acik === '') {
            return $sifreli;
        }
        return self::sifrele($acik);
    }
}
