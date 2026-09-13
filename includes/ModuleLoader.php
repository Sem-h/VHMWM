<?php
/**
 * WHMVM - Modül Yükleyici
 * Modülleri yükler ve entegre eder
 */

declare(strict_types=1);

class ModuleLoader {
    private static array $loadedModules = [];
    private static string $modulesDir;
    
    public static function init(): void {
        self::$modulesDir = dirname(__DIR__) . '/modules';
        
        if (!is_dir(self::$modulesDir)) {
            return;
        }
        
        // Aktif modülleri yükle
        try {
            require_once dirname(__DIR__) . '/config/config.php';
            require_once dirname(__DIR__) . '/includes/Database.php';
            
            $modules = Database::fetchAll("SELECT * FROM modules WHERE is_active = 1");
            
            foreach ($modules as $module) {
                self::loadModule($module);
            }
        } catch (Exception $e) {
            // Veritabanı bağlantısı yoksa veya tablo yoksa sessizce geç
        }
    }
    
    /**
     * Modülü yükle
     */
    private static function loadModule(array $module): void {
        $modulePath = dirname(__DIR__) . '/' . $module['install_path'];
        $moduleFile = $modulePath . '/module.php';
        
        if (file_exists($moduleFile)) {
            try {
                include_once $moduleFile;
                self::$loadedModules[$module['slug']] = $module;
            } catch (Exception $e) {
                // Modül yüklenirken hata oluşursa logla
                error_log("ModuleLoader: Failed to load module {$module['slug']}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Modülün yüklü olup olmadığını kontrol et
     */
    public static function isLoaded(string $slug): bool {
        return isset(self::$loadedModules[$slug]);
    }
    
    /**
     * Modül bilgilerini al
     */
    public static function getModule(string $slug): ?array {
        return self::$loadedModules[$slug] ?? null;
    }
    
    /**
     * Tüm yüklü modülleri al
     */
    public static function getLoadedModules(): array {
        return self::$loadedModules;
    }
    
    /**
     * Modül hook'larını çalıştır
     */
    public static function runHook(string $hookName, ...$args): void {
        foreach (self::$loadedModules as $module) {
            $modulePath = dirname(__DIR__) . '/' . $module['install_path'];
            $hooksFile = $modulePath . '/hooks.php';
            
            if (file_exists($hooksFile)) {
                try {
                    $hooks = include $hooksFile;
                    if (is_array($hooks) && isset($hooks[$hookName]) && is_callable($hooks[$hookName])) {
                        call_user_func_array($hooks[$hookName], $args);
                    }
                } catch (Exception $e) {
                    error_log("ModuleLoader: Hook error in {$module['slug']}::{$hookName}: " . $e->getMessage());
                }
            }
        }
    }
}

// Otomatik başlat
ModuleLoader::init();
