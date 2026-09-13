<?php
/**
 * VHM - Alan adı yardımcıları
 *
 * Uzantılar ve fiyatlar domain_pricing tablosundan gelir; müsaitlik
 * DomainNameAPI registrar modülünden sorulur. Hiçbir değer uydurulmaz:
 * fiyat tanımlı değilse fiyat gösterilmez, API yapılandırılmamışsa
 * müsaitlik "bilinmiyor" olarak işaretlenir.
 */

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Settings.php';

final class AlanAdi
{
    /** Sorgu sonucu durumları */
    public const MUSAIT = 'musait';
    public const KAYITLI = 'kayitli';
    public const BILINMIYOR = 'bilinmiyor';

    /**
     * Kullanıcının yazdığını ada ve uzantıya ayırır.
     * "example.com" -> ['ad' => 'example', 'uzanti' => '.com']
     * "Example"     -> ['ad' => 'example', 'uzanti' => null]
     *
     * Eski kod bütün noktaları siliyordu; "example.com" yazan kullanıcı
     * "examplecom.com" sorgulamış oluyordu.
     */
    public static function ayikla(string $giris): array
    {
        $giris = trim(mb_strtolower($giris));
        $giris = preg_replace('#^https?://#', '', $giris) ?? $giris;
        $giris = explode('/', $giris)[0];
        $giris = preg_replace('/^www\./', '', $giris) ?? $giris;

        /* Türkçe harfler ASCII karşılığına çevrilir; yoksa "örnek" yazan
           kullanıcının ö'sü silinip "rnek" sorgulanıyordu. */
        $giris = strtr($giris, [
            'ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'i̇' => 'i',
            'ö' => 'o', 'ş' => 's', 'ü' => 'u', 'â' => 'a', 'î' => 'i', 'û' => 'u',
        ]);

        $giris = preg_replace('/\s+/', '-', $giris) ?? $giris;
        $giris = preg_replace('/[^a-z0-9.\-]/', '', $giris) ?? '';
        $giris = preg_replace('/-{2,}/', '-', $giris) ?? $giris;
        $giris = trim($giris, '.-');

        if ($giris === '') {
            return ['ad' => '', 'uzanti' => null];
        }

        if (!str_contains($giris, '.')) {
            return ['ad' => $giris, 'uzanti' => null];
        }

        /* Bilinen uzantılar arasında en uzun eşleşmeyi bul (.com.tr, .com) */
        $bilinen = array_map(
            static fn(array $u): string => $u['uzanti'],
            self::uzantilar()
        );
        usort($bilinen, static fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($bilinen as $u) {
            if (str_ends_with($giris, $u)) {
                return ['ad' => substr($giris, 0, -strlen($u)), 'uzanti' => $u];
            }
        }

        /* Tanımlı olmayan uzantı: ilk noktadan böl */
        $nokta = strpos($giris, '.');
        return ['ad' => substr($giris, 0, $nokta), 'uzanti' => substr($giris, $nokta)];
    }

    /** domain_pricing tablosundaki etkin uzantılar */
    public static function uzantilar(): array
    {
        static $onbellek = null;
        if ($onbellek !== null) {
            return $onbellek;
        }

        $onbellek = [];
        try {
            $satirlar = Database::fetchAll(
                "SELECT * FROM domain_pricing WHERE is_active = 1 ORDER BY extension"
            );
        } catch (Throwable $e) {
            error_log('Alan adı fiyatları okunamadı: ' . $e->getMessage());
            return $onbellek;
        }

        foreach ($satirlar as $s) {
            $uzanti = '.' . ltrim(mb_strtolower((string) $s['extension']), '.');
            $onbellek[$uzanti] = [
                'id' => (int) $s['id'],
                'uzanti' => $uzanti,
                'kayit' => (float) $s['register_1yr'],
                'yenileme' => (float) $s['renew_1yr'],
                'transfer' => (float) $s['transfer_price'],
                'gizlilik' => (float) ($s['id_protection_price'] ?? 0),
                'epp' => (int) ($s['epp_required'] ?? 0) === 1,
                'para_birimi' => (string) ($s['currency'] ?: 'TRY'),
            ];
        }

        return $onbellek;
    }

    public static function uzanti(string $uzanti): ?array
    {
        $uzanti = '.' . ltrim(mb_strtolower($uzanti), '.');
        return self::uzantilar()[$uzanti] ?? null;
    }

    /* ==========================================================
       Registrar
       ========================================================== */

    public static function apiHazir(): bool
    {
        return (string) Settings::get('domainname_active', '0') === '1'
            && trim((string) Settings::get('domainname_username', '')) !== ''
            && trim((string) Settings::get('domainname_password', '')) !== '';
    }

    private static function api(): ?object
    {
        if (!self::apiHazir()) {
            return null;
        }
        $dosya = dirname(__DIR__) . '/modules/registrars/domainnameapi/DomainNameAPI.php';
        if (!is_file($dosya)) {
            return null;
        }
        try {
            require_once $dosya;
            return new DomainNameAPI(
                (string) Settings::get('domainname_username', ''),
                (string) Settings::get('domainname_password', ''),
                (string) Settings::get('domainname_test_mode', '0') === '1'
            );
        } catch (Throwable $e) {
            error_log('Registrar başlatılamadı: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Bir ad için verilen uzantıların durumunu döner.
     *
     * @return array<int, array{alan_adi: string, uzanti: string, durum: string, fiyat: ?float}>
     */
    public static function sorgula(string $ad, array $uzantilar): array
    {
        $sonuc = [];
        foreach ($uzantilar as $u) {
            $tanim = self::uzanti($u);
            $sonuc[$u] = [
                'alan_adi' => $ad . $u,
                'uzanti' => $u,
                'durum' => self::BILINMIYOR,
                'fiyat' => $tanim ? $tanim['kayit'] : null,
            ];
        }

        $api = self::api();
        if ($api === null) {
            return array_values($sonuc);
        }

        try {
            $yanit = $api->checkAvailability(
                [$ad],
                array_map(static fn(string $u): string => ltrim($u, '.'), $uzantilar),
                1,
                'create'
            );

            $liste = $yanit['data'] ?? $yanit;
            if (is_array($liste)) {
                foreach ($liste as $satir) {
                    if (!is_array($satir) || !isset($satir['TLD'])) {
                        continue;
                    }
                    $u = '.' . ltrim(mb_strtolower((string) $satir['TLD']), '.');
                    if (!isset($sonuc[$u])) {
                        continue;
                    }
                    $durum = mb_strtolower((string) ($satir['Status'] ?? ''));
                    $sonuc[$u]['durum'] = $durum === 'available' ? self::MUSAIT : self::KAYITLI;
                }
            }
        } catch (Throwable $e) {
            error_log('Alan adı sorgulanamadı: ' . $e->getMessage());
        }

        return array_values($sonuc);
    }

    /**
     * TLD listesini ve maliyetleri registrar'dan çekip domain_pricing
     * tablosuna yazar. $karOrani yüzde olarak maliyetin üzerine eklenir.
     *
     * @return array{ok: bool, mesaj: string, adet: int}
     */
    public static function fiyatlariCek(float $karOrani = 0.0, int $adet = 100): array
    {
        $api = self::api();
        if ($api === null) {
            return ['ok' => false, 'mesaj' => 'Registrar kimlik bilgileri girilmemiş.', 'adet' => 0];
        }

        try {
            $yanit = $api->getTldList($adet);
        } catch (Throwable $e) {
            return ['ok' => false, 'mesaj' => 'API hatası: ' . $e->getMessage(), 'adet' => 0];
        }

        if (($yanit['result'] ?? '') !== 'OK' || empty($yanit['data'])) {
            return ['ok' => false, 'mesaj' => 'TLD listesi alınamadı.', 'adet' => 0];
        }

        $carpan = 1 + ($karOrani / 100);
        $yazilan = 0;

        foreach ($yanit['data'] as $t) {
            $uzanti = ltrim(mb_strtolower((string) ($t['tld'] ?? '')), '.');
            if ($uzanti === '') {
                continue;
            }

            $fiyat = static fn(string $tur): float =>
                round(((float) ($t['pricing'][$tur][1] ?? 0)) * $carpan, 2);

            $kayit = $fiyat('register');
            if ($kayit <= 0) {
                continue;
            }

            $veri = [
                'register_1yr' => $kayit,
                'renew_1yr' => $fiyat('renew') ?: $kayit,
                'transfer_price' => $fiyat('transfer') ?: $kayit,
                'currency' => mb_substr((string) ($t['currencies']['register'] ?? 'TRY'), 0, 3),
                'registrar' => 'domainnameapi',
                'is_active' => 1,
            ];

            $mevcut = Database::fetch("SELECT id FROM domain_pricing WHERE extension = ?", [$uzanti]);
            if ($mevcut) {
                Database::update('domain_pricing', $veri, 'id = ?', [(int) $mevcut['id']]);
            } else {
                Database::insert('domain_pricing', $veri + ['extension' => $uzanti]);
            }
            $yazilan++;
        }

        return ['ok' => true, 'mesaj' => $yazilan . ' uzantı güncellendi.', 'adet' => $yazilan];
    }
}
