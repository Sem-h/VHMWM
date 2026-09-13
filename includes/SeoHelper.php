<?php
/**
 * WHMVM - SEO Helper
 * SEO meta tag'leri ve URL yönetimi için yardımcı sınıf
 */

declare(strict_types=1);

class SeoHelper {
    
    /**
     * Mevcut sayfa için SEO bilgilerini al
     */
    public static function getSeoData(?string $pagePath = null): ?array {
        if ($pagePath === null) {
            $pagePath = $_SERVER['REQUEST_URI'] ?? '/';
            // Query string'i temizle
            $pagePath = strtok($pagePath, '?');
        }
        
        try {
            require_once __DIR__ . '/Database.php';
            $seo = Database::fetch("
                SELECT * FROM seo_pages 
                WHERE (page_path = ? OR custom_slug = ?) AND is_active = 1
                LIMIT 1
            ", [$pagePath, $pagePath]);
            
            return $seo ?: null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Sayfa başlığını al
     */
    public static function getTitle(?string $pagePath = null, ?string $default = null): string {
        $seo = self::getSeoData($pagePath);
        
        if ($seo && !empty($seo['title'])) {
            return htmlspecialchars($seo['title']);
        }
        
        return $default ?? SITE_NAME;
    }
    
    /**
     * Meta description'ı al
     */
    public static function getMetaDescription(?string $pagePath = null): ?string {
        $seo = self::getSeoData($pagePath);
        return $seo && !empty($seo['meta_description']) ? htmlspecialchars($seo['meta_description']) : null;
    }
    
    /**
     * Meta keywords'ü al
     */
    public static function getMetaKeywords(?string $pagePath = null): ?string {
        $seo = self::getSeoData($pagePath);
        return $seo && !empty($seo['meta_keywords']) ? htmlspecialchars($seo['meta_keywords']) : null;
    }
    
    /**
     * Meta robots'u al
     */
    public static function getMetaRobots(?string $pagePath = null): string {
        $seo = self::getSeoData($pagePath);
        return $seo && !empty($seo['meta_robots']) ? htmlspecialchars($seo['meta_robots']) : 'index, follow';
    }
    
    /**
     * Canonical URL'i al
     */
    public static function getCanonicalUrl(?string $pagePath = null): ?string {
        $seo = self::getSeoData($pagePath);
        
        if ($seo && !empty($seo['canonical_url'])) {
            return htmlspecialchars($seo['canonical_url']);
        }
        
        // Varsayılan olarak mevcut URL'i canonical yap
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $path = $pagePath ?? ($_SERVER['REQUEST_URI'] ?? '/');
        $path = strtok($path, '?'); // Query string'i temizle
        
        return $protocol . '://' . $host . $path;
    }
    
    /**
     * Open Graph başlığını al
     */
    public static function getOgTitle(?string $pagePath = null): ?string {
        $seo = self::getSeoData($pagePath);
        
        if ($seo && !empty($seo['og_title'])) {
            return htmlspecialchars($seo['og_title']);
        }
        
        // OG title yoksa normal title'ı kullan
        return self::getTitle($pagePath);
    }
    
    /**
     * Open Graph açıklamasını al
     */
    public static function getOgDescription(?string $pagePath = null): ?string {
        $seo = self::getSeoData($pagePath);
        
        if ($seo && !empty($seo['og_description'])) {
            return htmlspecialchars($seo['og_description']);
        }
        
        // OG description yoksa meta description'ı kullan
        return self::getMetaDescription($pagePath);
    }
    
    /**
     * Open Graph resmini al
     */
    public static function getOgImage(?string $pagePath = null): ?string {
        $seo = self::getSeoData($pagePath);
        
        if ($seo && !empty($seo['og_image'])) {
            return htmlspecialchars($seo['og_image']);
        }
        
        // Varsayılan logo
        $logo = Settings::get('site_logo', '');
        if (!empty($logo)) {
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            return $protocol . '://' . $host . '/' . ltrim($logo, '/');
        }
        
        return null;
    }
    
    /**
     * SEO meta tag'lerini HTML olarak döndür
     */
    public static function renderMetaTags(?string $pagePath = null): string {
        $html = [];
        
        // Title
        $title = self::getTitle($pagePath);
        $html[] = '<title>' . $title . '</title>';
        
        // Meta Description
        $description = self::getMetaDescription($pagePath);
        if ($description) {
            $html[] = '<meta name="description" content="' . $description . '">';
        }
        
        // Meta Keywords
        $keywords = self::getMetaKeywords($pagePath);
        if ($keywords) {
            $html[] = '<meta name="keywords" content="' . $keywords . '">';
        }
        
        // Meta Robots
        $robots = self::getMetaRobots($pagePath);
        $html[] = '<meta name="robots" content="' . $robots . '">';
        
        // Canonical URL
        $canonical = self::getCanonicalUrl($pagePath);
        if ($canonical) {
            $html[] = '<link rel="canonical" href="' . $canonical . '">';
        }
        
        // Open Graph Tags
        $ogTitle = self::getOgTitle($pagePath);
        $ogDescription = self::getOgDescription($pagePath);
        $ogImage = self::getOgImage($pagePath);
        $ogUrl = self::getCanonicalUrl($pagePath);
        
        if ($ogTitle) {
            $html[] = '<meta property="og:title" content="' . $ogTitle . '">';
        }
        if ($ogDescription) {
            $html[] = '<meta property="og:description" content="' . $ogDescription . '">';
        }
        if ($ogImage) {
            $html[] = '<meta property="og:image" content="' . $ogImage . '">';
        }
        if ($ogUrl) {
            $html[] = '<meta property="og:url" content="' . $ogUrl . '">';
        }
        $html[] = '<meta property="og:type" content="website">';
        $html[] = '<meta property="og:site_name" content="' . htmlspecialchars(SITE_NAME) . '">';
        
        // Twitter Card
        if ($ogTitle) {
            $html[] = '<meta name="twitter:card" content="summary_large_image">';
            $html[] = '<meta name="twitter:title" content="' . $ogTitle . '">';
            if ($ogDescription) {
                $html[] = '<meta name="twitter:description" content="' . $ogDescription . '">';
            }
            if ($ogImage) {
                $html[] = '<meta name="twitter:image" content="' . $ogImage . '">';
            }
        }
        
        return implode("\n    ", $html);
    }
    
    /**
     * URL'i özelleştirilmiş slug'a çevir
     */
    public static function getCustomUrl(string $pagePath): string {
        try {
            require_once __DIR__ . '/Database.php';
            $seo = Database::fetch("
                SELECT custom_slug FROM seo_pages 
                WHERE page_path = ? AND custom_slug IS NOT NULL AND custom_slug != '' AND is_active = 1
                LIMIT 1
            ", [$pagePath]);
            
            if ($seo && !empty($seo['custom_slug'])) {
                return $seo['custom_slug'];
            }
        } catch (Exception $e) {
            // Hata durumunda orijinal path'i döndür
        }
        
        return $pagePath;
    }
}
