<?php
/**
 * WHMVM - Template/Theme Manager
 * PHP 8.1+
 */

declare(strict_types=1);

class Template
{
    private static ?string $activeTheme = null;
    private static array $themeConfig = [];
    private static string $templatesPath;
    
    /**
     * Template sistemini başlat
     */
    public static function init(): void
    {
        self::$templatesPath = dirname(__DIR__) . '/templates';
        self::loadActiveTheme();
    }
    
    /**
     * Aktif temayı yükle
     */
    private static function loadActiveTheme(): void
    {
        // Veritabanından aktif temayı al veya varsayılanı kullan
        try {
            $db = Database::getInstance();
            $stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'active_theme'");
            self::$activeTheme = $stmt->fetchColumn() ?: 'default';
        } catch (Exception $e) {
            self::$activeTheme = 'default';
        }
        
        // Tema config dosyasını yükle
        $configFile = self::$templatesPath . '/' . self::$activeTheme . '/config.php';
        if (file_exists($configFile)) {
            self::$themeConfig = require $configFile;
        }
    }
    
    /**
     * Aktif tema adını döndür
     */
    public static function getActiveTheme(): string
    {
        if (self::$activeTheme === null) {
            self::init();
        }
        return self::$activeTheme;
    }
    
    /**
     * Tema ayarlarını döndür
     */
    public static function getConfig(): array
    {
        if (empty(self::$themeConfig)) {
            self::init();
        }
        return self::$themeConfig;
    }
    
    /**
     * Mevcut temaları listele
     */
    public static function getAvailableThemes(): array
    {
        $themes = [];
        $dirs = glob(self::$templatesPath . '/*', GLOB_ONLYDIR);
        
        foreach ($dirs as $dir) {
            $configFile = $dir . '/config.php';
            if (file_exists($configFile)) {
                $config = require $configFile;
                $themes[basename($dir)] = [
                    'name' => $config['name'] ?? basename($dir),
                    'version' => $config['version'] ?? '1.0.0',
                    'author' => $config['author'] ?? 'Unknown',
                    'description' => $config['description'] ?? '',
                    'path' => $dir,
                ];
            }
        }
        
        return $themes;
    }
    
    /**
     * Temayı değiştir
     */
    public static function setTheme(string $themeName): bool
    {
        $themePath = self::$templatesPath . '/' . $themeName;
        
        if (!is_dir($themePath)) {
            return false;
        }
        
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('active_theme', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$themeName, $themeName]);
            
            self::$activeTheme = $themeName;
            self::loadActiveTheme();
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Tema dosyasını dahil et
     */
    public static function include(string $file, array $data = []): void
    {
        extract($data);
        $filePath = self::$templatesPath . '/' . self::getActiveTheme() . '/' . $file;
        
        if (file_exists($filePath)) {
            include $filePath;
        } else {
            // Fallback to default theme
            $defaultPath = self::$templatesPath . '/default/' . $file;
            if (file_exists($defaultPath)) {
                include $defaultPath;
            }
        }
    }
    
    /**
     * Tema header'ını dahil et
     */
    public static function header(array $data = []): void
    {
        self::include('header.php', $data);
    }
    
    /**
     * Tema footer'ını dahil et
     */
    public static function footer(array $data = []): void
    {
        self::include('footer.php', $data);
    }
    
    /**
     * Tema asset URL'si oluştur
     */
    public static function asset(string $path): string
    {
        return SITE_URL . '/templates/' . self::getActiveTheme() . '/assets/' . ltrim($path, '/');
    }
    
    /**
     * Renk değerini al
     */
    public static function color(string $name): string
    {
        return self::$themeConfig['colors'][$name] ?? '#000000';
    }
    
    /**
     * Tema ayarını al
     */
    public static function setting(string $name, mixed $default = null): mixed
    {
        return self::$themeConfig['settings'][$name] ?? $default;
    }
}

