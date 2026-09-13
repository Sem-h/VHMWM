<?php
/**
 * VHM - Oturum ve istek güvenliği
 *
 * Üç işi bir arada toplar:
 *   1) Oturum çerezini sertleştirerek başlatır (httponly, samesite, secure)
 *   2) CSRF belirteci üretir ve doğrular
 *   3) Giriş denemelerini sayar, art arda başarısızlıkta geçici kilit uygular
 *
 * Yönetim panelindeki silme işlemleri eskiden GET bağlantısıyla yapılıyordu;
 * giriş yapmış bir yöneticiye gösterilen zararlı bir sayfa, onun adına kayıt
 * sildirebiliyordu. Artık bu işlemler POST + belirteç gerektirir.
 */

declare(strict_types=1);

final class Guvenlik
{
    private const TOKEN_ANAHTAR = 'guvenlik_token';
    private const DENEME_SINIR = 5;          // bu kadar başarısızlıktan sonra
    private const DENEME_PENCERE = 900;      // son 15 dakika içinde
    private const KILIT_SURE = 900;          // 15 dakika kilit

    /**
     * Oturumu güvenli çerez ayarlarıyla başlatır.
     * session_start çağrılmadan ÖNCE kullanılmalıdır.
     */
    public static function oturumBaslat(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            /* Yerel geliştirmede HTTPS yoksa secure verilemez; verilirse
               çerez hiç kurulmaz ve oturum açılmaz. */
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (defined('SESSION_NAME')) {
            session_name(SESSION_NAME);
        }

        session_start();
    }

    /** Girişten sonra oturum kimliğini yeniler (oturum sabitlemeye karşı) */
    public static function kimlikYenile(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /* ==========================================================
       CSRF
       ========================================================== */

    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::oturumBaslat();
        }
        if (empty($_SESSION[self::TOKEN_ANAHTAR])) {
            $_SESSION[self::TOKEN_ANAHTAR] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION[self::TOKEN_ANAHTAR];
    }

    /** Forma gömülecek gizli alan */
    public static function alan(): string
    {
        return '<input type="hidden" name="_token" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /** Gelen isteğin belirtecini doğrular */
    public static function dogrula(?string $gelen = null): bool
    {
        $gelen ??= (string) ($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $kayitli = (string) ($_SESSION[self::TOKEN_ANAHTAR] ?? '');

        return $kayitli !== '' && $gelen !== '' && hash_equals($kayitli, $gelen);
    }

    /**
     * Doğrulama başarısızsa isteği keser.
     * Yönetim panelindeki her durum değiştiren POST'ta çağrılır.
     */
    public static function zorunlu(): void
    {
        if (self::dogrula()) {
            return;
        }

        http_response_code(419);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8">'
            . '<title>Oturum doğrulaması başarısız</title>'
            . '<div style="font:15px/1.6 system-ui;max-width:520px;margin:80px auto;padding:24px;'
            . 'border:1px solid #e5e7eb;border-radius:10px">'
            . '<h1 style="font-size:18px;margin:0 0 10px">İstek doğrulanamadı</h1>'
            . '<p style="color:#6b7280;margin:0 0 16px">Oturumunuz zaman aşımına uğramış ya da '
            . 'istek başka bir sayfadan gönderilmiş olabilir. Sayfayı yenileyip tekrar deneyin.</p>'
            . '<a href="javascript:history.back()" style="color:#2474f5">Geri dön</a></div>';
        exit;
    }

    /* ==========================================================
       Giriş denemeleri
       ========================================================== */

    private static function ip(): string
    {
        return mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    /** Denemeyi kaydeder; tablo yoksa sessizce geçer */
    public static function denemeKaydet(string $kullanici, bool $basarili): void
    {
        try {
            Database::insert('giris_denemeleri', [
                'kullanici' => mb_substr($kullanici, 0, 150),
                'ip' => self::ip(),
                'basarili' => $basarili ? 1 : 0,
            ]);
        } catch (Throwable $e) {
            error_log('Giriş denemesi kaydedilemedi: ' . $e->getMessage());
        }
    }

    /**
     * Kilit varsa kalan saniyeyi, yoksa 0 döner.
     * Hem kullanıcı adı hem IP ayrı ayrı sayılır: tek hesaba yüklenen
     * saldırı da, tek adresten çok hesap denenmesi de yakalanır.
     */
    public static function kilitliMi(string $kullanici): int
    {
        try {
            $satir = Database::fetch(
                "SELECT COUNT(*) AS adet, MAX(created_at) AS son
                   FROM giris_denemeleri
                  WHERE basarili = 0
                    AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
                    AND (kullanici = ? OR ip = ?)",
                [self::DENEME_PENCERE, mb_substr($kullanici, 0, 150), self::ip()]
            );
        } catch (Throwable $e) {
            error_log('Giriş denemeleri okunamadı: ' . $e->getMessage());
            return 0;
        }

        if (!$satir || (int) $satir['adet'] < self::DENEME_SINIR) {
            return 0;
        }

        $gecen = time() - strtotime((string) $satir['son']);
        $kalan = self::KILIT_SURE - $gecen;

        return $kalan > 0 ? $kalan : 0;
    }

    /** Başarılı girişten sonra o kullanıcı ve adres için sayaç sıfırlanır */
    public static function denemeleriSil(string $kullanici): void
    {
        try {
            Database::query(
                "DELETE FROM giris_denemeleri WHERE kullanici = ? OR ip = ?",
                [mb_substr($kullanici, 0, 150), self::ip()]
            );
        } catch (Throwable $e) {
            error_log('Giriş denemeleri silinemedi: ' . $e->getMessage());
        }
    }

    /** Süresi geçmiş kayıtları temizler (cron ya da giriş sırasında) */
    public static function eskiDenemeleriSil(int $gun = 7): void
    {
        try {
            Database::query(
                "DELETE FROM giris_denemeleri WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$gun]
            );
        } catch (Throwable $e) {
            error_log('Eski giriş denemeleri silinemedi: ' . $e->getMessage());
        }
    }
}
