<?php
/**
 * WHMVM - Settings Helper Class
 * Veritabanından ayarları okuma ve yazma
 */

declare(strict_types=1);

class Settings
{
    private static array $cache = [];
    private static bool $loaded = false;

    /**
     * Tüm ayarları yükle ve önbelleğe al
     */
    public static function loadAll(): void
    {
        if (self::$loaded) {
            return;
        }

        try {
            $results = Database::fetchAll("SELECT setting_key, setting_value FROM settings");
            foreach ($results as $row) {
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }
            self::$loaded = true;
        } catch (Exception $e) {
            self::$loaded = false;
        }
    }

    /**
     * Tek bir ayarı getir
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::loadAll();
        return self::$cache[$key] ?? $default;
    }

    /**
     * Ayar kaydet veya güncelle
     */
    public static function set(string $key, mixed $value, string $group = 'general'): bool
    {
        try {
            $sql = "INSERT INTO settings (setting_key, setting_value, setting_group) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?, setting_group = ?";
            Database::query($sql, [$key, $value, $group, $value, $group]);
            self::$cache[$key] = $value;
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Birden fazla ayarı kaydet
     */
    public static function setMultiple(array $settings, string $group = 'general'): bool
    {
        try {
            foreach ($settings as $key => $value) {
                self::set($key, $value, $group);
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Ayar sil
     */
    public static function delete(string $key): bool
    {
        try {
            Database::query("DELETE FROM settings WHERE setting_key = ?", [$key]);
            unset(self::$cache[$key]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Grup bazlı ayarları getir
     */
    public static function getByGroup(string $group): array
    {
        try {
            $results = Database::fetchAll(
                "SELECT setting_key, setting_value FROM settings WHERE setting_group = ?", 
                [$group]
            );
            $settings = [];
            foreach ($results as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            return $settings;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Önbelleği temizle
     */
    public static function clearCache(): void
    {
        self::$cache = [];
        self::$loaded = false;
    }

    /**
     * Site adını getir
     */
    public static function getSiteName(): string
    {
        return self::get('site_name', self::get('company_name', defined('SITE_NAME') ? SITE_NAME : 'WHMVM'));
    }

    /**
     * Logo URL'ini getir
     * Dosya diskte yoksa boş döner, böylece şablonlar kırık görsel yerine
     * ikon + site adından oluşan yedek logoyu gösterir.
     */
    public static function getLogo(): string
    {
        return self::assetIfExists(self::get('site_logo', ''));
    }

    /**
     * Favicon URL'ini getir
     */
    public static function getFavicon(): string
    {
        return self::assetIfExists(self::get('site_favicon', ''));
    }

    /**
     * Yüklenmiş bir dosya yolunu yalnızca dosya gerçekten varsa döndür
     */
    private static function assetIfExists(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        // Harici URL'ler olduğu gibi geçer
        if (preg_match('#^(https?:)?//#i', $path)) {
            return $path;
        }

        $file = dirname(__DIR__) . '/' . ltrim($path, '/');

        return is_file($file) ? $path : '';
    }

    /**
     * Tüm ayarları dizi olarak getir
     */
    public static function all(): array
    {
        self::loadAll();
        return self::$cache;
    }
}

