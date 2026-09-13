<?php
/**
 * WHMVM - SEO Manager
 * SEO yönetim sayfası
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'SEO Yönetimi';
$currentPage = 'seo-manager';
$message = '';
$messageType = 'success';

// SEO modülü yüklü mü kontrol et
$seoModule = Database::fetch("SELECT * FROM modules WHERE slug = 'seo-manager' AND is_active = 1");
if (!$seoModule) {
    $message = 'SEO Manager modülü yüklü veya aktif değil!';
    $messageType = 'danger';
}

// Tabloları kontrol et ve oluştur
try {
    Database::query("SELECT 1 FROM seo_pages LIMIT 1");
} catch (Exception $e) {
    // Tablo yoksa oluştur (modül install scripti çalışmamış olabilir)
    require_once dirname(__DIR__) . '/modules/seo-manager/install.php';
}

// Sayfa kaydetme/güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_seo'])) {
        $id = isset($_POST['seo_id']) ? (int)$_POST['seo_id'] : 0;
        $pageType = trim($_POST['page_type'] ?? '');
        $pageId = !empty($_POST['page_id']) ? (int)$_POST['page_id'] : null;
        $pagePath = trim($_POST['page_path'] ?? '');
        $customSlug = trim($_POST['custom_slug'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $metaDescription = trim($_POST['meta_description'] ?? '');
        $metaKeywords = trim($_POST['meta_keywords'] ?? '');
        $metaRobots = trim($_POST['meta_robots'] ?? 'index, follow');
        $ogTitle = trim($_POST['og_title'] ?? '');
        $ogDescription = trim($_POST['og_description'] ?? '');
        $ogImage = trim($_POST['og_image'] ?? '');
        $canonicalUrl = trim($_POST['canonical_url'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // Slug kontrolü (benzersiz olmalı)
        if (!empty($customSlug)) {
            $existing = Database::fetch("SELECT id FROM seo_pages WHERE custom_slug = ? AND id != ?", [$customSlug, $id]);
            if ($existing) {
                $message = 'Bu slug zaten kullanılıyor!';
                $messageType = 'danger';
            }
        }
        
        // Path kontrolü (benzersiz olmalı)
        if (!empty($pagePath)) {
            $existing = Database::fetch("SELECT id FROM seo_pages WHERE page_path = ? AND id != ?", [$pagePath, $id]);
            if ($existing) {
                $message = 'Bu sayfa yolu zaten kullanılıyor!';
                $messageType = 'danger';
            }
        }
        
        if (empty($message)) {
            if ($id > 0) {
                // Güncelleme
                Database::query("
                    UPDATE seo_pages 
                    SET page_type = ?, page_id = ?, page_path = ?, custom_slug = ?, 
                        title = ?, meta_description = ?, meta_keywords = ?, meta_robots = ?,
                        og_title = ?, og_description = ?, og_image = ?, canonical_url = ?,
                        is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ", [
                    $pageType, $pageId, $pagePath, $customSlug,
                    $title, $metaDescription, $metaKeywords, $metaRobots,
                    $ogTitle, $ogDescription, $ogImage, $canonicalUrl,
                    $isActive, $id
                ]);
                $message = 'SEO ayarları güncellendi!';
            } else {
                // Yeni kayıt
                Database::query("
                    INSERT INTO seo_pages 
                    (page_type, page_id, page_path, custom_slug, title, meta_description, 
                     meta_keywords, meta_robots, og_title, og_description, og_image, 
                     canonical_url, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ", [
                    $pageType, $pageId, $pagePath, $customSlug,
                    $title, $metaDescription, $metaKeywords, $metaRobots,
                    $ogTitle, $ogDescription, $ogImage, $canonicalUrl,
                    $isActive
                ]);
                $message = 'SEO ayarları kaydedildi!';
            }
        }
    }
    
    if (isset($_POST['delete_seo'])) {
        $id = (int)$_POST['seo_id'];
        Database::query("DELETE FROM seo_pages WHERE id = ?", [$id]);
        $message = 'SEO kaydı silindi!';
    }
    
    if (isset($_POST['auto_detect'])) {
        // Otomatik sayfa algılama
        autoDetectPages();
        $message = 'Sayfalar otomatik olarak algılandı!';
    }
}

// Otomatik sayfa algılama fonksiyonu
function autoDetectPages() {
    // Ürünler
    $products = Database::fetchAll("SELECT id, name, slug FROM products WHERE is_active = 1");
    foreach ($products as $product) {
        $path = '/product/' . $product['slug'];
        $existing = Database::fetch("SELECT id FROM seo_pages WHERE page_path = ?", [$path]);
        if (!$existing) {
            Database::query("
                INSERT INTO seo_pages (page_type, page_id, page_path, title, is_active)
                VALUES (?, ?, ?, ?, 1)
            ", ['product', $product['id'], $path, $product['name']]);
        }
    }
    
    // Ürün grupları
    $productGroups = Database::fetchAll("SELECT id, name, slug FROM product_groups WHERE is_hidden = 0");
    foreach ($productGroups as $group) {
        $path = '/' . $group['slug'];
        $existing = Database::fetch("SELECT id FROM seo_pages WHERE page_path = ?", [$path]);
        if (!$existing) {
            Database::query("
                INSERT INTO seo_pages (page_type, page_id, page_path, title, is_active)
                VALUES (?, ?, ?, ?, 1)
            ", ['product_group', $group['id'], $path, $group['name']]);
        }
    }
    
    // Statik sayfalar
    $staticPages = [
        ['home', '/', 'Ana Sayfa'],
        ['hosting', '/hosting', 'Hosting'],
        ['vds', '/vds', 'VDS'],
        ['vps', '/vps', 'VPS'],
        ['domain', '/domain', 'Domain'],
        ['ssl', '/ssl', 'SSL Sertifikası'],
        ['blog', '/blog', 'Blog'],
        ['contact', '/contact', 'İletişim'],
        ['about', '/about', 'Hakkımızda'],
    ];
    
    foreach ($staticPages as $page) {
        $existing = Database::fetch("SELECT id FROM seo_pages WHERE page_path = ?", [$page[1]]);
        if (!$existing) {
            Database::query("
                INSERT INTO seo_pages (page_type, page_path, title, is_active)
                VALUES (?, ?, ?, 1)
            ", [$page[0], $page[1], $page[2]]);
        }
    }
}

// Düzenlenecek kayıt
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editSeo = $editId > 0 ? Database::fetch("SELECT * FROM seo_pages WHERE id = ?", [$editId]) : null;

// Tüm sayfaları listele
$seoPages = Database::fetchAll("SELECT * FROM seo_pages ORDER BY page_type, page_path");

include 'includes/header.php';
?>

<style>
.seo-form {
    background: white;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--dark);
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
}

textarea.form-control {
    resize: vertical;
    min-height: 100px;
}

.seo-pages-list {
    background: white;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.page-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
    transition: all 0.3s;
}

.page-item:hover {
    background: #f8fafc;
}

.page-item:last-child {
    border-bottom: none;
}

.page-info {
    flex: 1;
}

.page-info h4 {
    font-size: 16px;
    color: var(--dark);
    margin-bottom: 6px;
}

.page-info p {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 4px;
}

.page-path {
    font-family: monospace;
    font-size: 12px;
    color: var(--primary);
    background: #f1f5f9;
    padding: 4px 8px;
    border-radius: 4px;
    display: inline-block;
}

.page-actions {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
    color: white;
}

.btn-outline {
    background: white;
    border: 2px solid #e2e8f0;
    color: var(--dark);
}

.btn-danger {
    background: #fee2e2;
    color: #dc2626;
}

.alert {
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    font-size: 14px;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-danger {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}
</style>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- SEO Form -->
<div class="seo-form">
    <h2 style="margin-bottom: 25px; font-size: 24px; color: var(--dark);">
        <?= $editSeo ? 'SEO Ayarlarını Düzenle' : 'Yeni SEO Sayfası Ekle' ?>
    </h2>
    
    <form method="POST">
        <?php if ($editSeo): ?>
            <input type="hidden" name="seo_id" value="<?= $editSeo['id'] ?>">
        <?php endif; ?>
        
        <div class="form-grid">
            <div class="form-group">
                <label>Sayfa Tipi *</label>
                <select name="page_type" class="form-control" required>
                    <option value="">Seçiniz</option>
                    <option value="home" <?= ($editSeo['page_type'] ?? '') === 'home' ? 'selected' : '' ?>>Ana Sayfa</option>
                    <option value="product" <?= ($editSeo['page_type'] ?? '') === 'product' ? 'selected' : '' ?>>Ürün</option>
                    <option value="service" <?= ($editSeo['page_type'] ?? '') === 'service' ? 'selected' : '' ?>>Hizmet</option>
                    <option value="hosting" <?= ($editSeo['page_type'] ?? '') === 'hosting' ? 'selected' : '' ?>>Hosting</option>
                    <option value="vds" <?= ($editSeo['page_type'] ?? '') === 'vds' ? 'selected' : '' ?>>VDS</option>
                    <option value="vps" <?= ($editSeo['page_type'] ?? '') === 'vps' ? 'selected' : '' ?>>VPS</option>
                    <option value="domain" <?= ($editSeo['page_type'] ?? '') === 'domain' ? 'selected' : '' ?>>Domain</option>
                    <option value="ssl" <?= ($editSeo['page_type'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                    <option value="blog" <?= ($editSeo['page_type'] ?? '') === 'blog' ? 'selected' : '' ?>>Blog</option>
                    <option value="page" <?= ($editSeo['page_type'] ?? '') === 'page' ? 'selected' : '' ?>>Özel Sayfa</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Sayfa ID (Opsiyonel)</label>
                <input type="number" name="page_id" class="form-control" 
                       value="<?= htmlspecialchars($editSeo['page_id'] ?? '') ?>"
                       placeholder="Ürün/Hizmet ID">
            </div>
            
            <div class="form-group">
                <label>Sayfa Yolu *</label>
                <input type="text" name="page_path" class="form-control" required
                       value="<?= htmlspecialchars($editSeo['page_path'] ?? '') ?>"
                       placeholder="/hosting, /vds, /urun-adi">
            </div>
            
            <div class="form-group">
                <label>Özel Slug (URL)</label>
                <input type="text" name="custom_slug" class="form-control"
                       value="<?= htmlspecialchars($editSeo['custom_slug'] ?? '') ?>"
                       placeholder="ozel-url-slug">
                <small style="color: #64748b; font-size: 12px;">Boş bırakılırsa sayfa yolu kullanılır</small>
            </div>
            
            <div class="form-group full-width">
                <label>Sayfa Başlığı (Title) *</label>
                <input type="text" name="title" class="form-control" required
                       value="<?= htmlspecialchars($editSeo['title'] ?? '') ?>"
                       placeholder="Sayfa başlığı">
            </div>
            
            <div class="form-group full-width">
                <label>Meta Açıklama (Description)</label>
                <textarea name="meta_description" class="form-control" rows="3"
                          placeholder="Sayfa açıklaması (150-160 karakter önerilir)"><?= htmlspecialchars($editSeo['meta_description'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group full-width">
                <label>Meta Anahtar Kelimeler (Keywords)</label>
                <input type="text" name="meta_keywords" class="form-control"
                       value="<?= htmlspecialchars($editSeo['meta_keywords'] ?? '') ?>"
                       placeholder="kelime1, kelime2, kelime3">
            </div>
            
            <div class="form-group">
                <label>Meta Robots</label>
                <select name="meta_robots" class="form-control">
                    <option value="index, follow" <?= ($editSeo['meta_robots'] ?? 'index, follow') === 'index, follow' ? 'selected' : '' ?>>index, follow</option>
                    <option value="noindex, follow" <?= ($editSeo['meta_robots'] ?? '') === 'noindex, follow' ? 'selected' : '' ?>>noindex, follow</option>
                    <option value="index, nofollow" <?= ($editSeo['meta_robots'] ?? '') === 'index, nofollow' ? 'selected' : '' ?>>index, nofollow</option>
                    <option value="noindex, nofollow" <?= ($editSeo['meta_robots'] ?? '') === 'noindex, nofollow' ? 'selected' : '' ?>>noindex, nofollow</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Canonical URL</label>
                <input type="url" name="canonical_url" class="form-control"
                       value="<?= htmlspecialchars($editSeo['canonical_url'] ?? '') ?>"
                       placeholder="https://example.com/canonical-page">
            </div>
            
            <div class="form-group full-width">
                <label>Open Graph Başlık</label>
                <input type="text" name="og_title" class="form-control"
                       value="<?= htmlspecialchars($editSeo['og_title'] ?? '') ?>"
                       placeholder="Sosyal medya paylaşım başlığı">
            </div>
            
            <div class="form-group full-width">
                <label>Open Graph Açıklama</label>
                <textarea name="og_description" class="form-control" rows="2"
                          placeholder="Sosyal medya paylaşım açıklaması"><?= htmlspecialchars($editSeo['og_description'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group full-width">
                <label>Open Graph Resim URL</label>
                <input type="url" name="og_image" class="form-control"
                       value="<?= htmlspecialchars($editSeo['og_image'] ?? '') ?>"
                       placeholder="https://example.com/image.jpg">
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1" 
                           <?= ($editSeo['is_active'] ?? 1) ? 'checked' : '' ?>>
                    Aktif
                </label>
            </div>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 25px;">
            <button type="submit" name="save_seo" class="btn btn-primary">
                💾 Kaydet
            </button>
            <?php if ($editSeo): ?>
                <a href="seo-manager.php" class="btn btn-outline">❌ İptal</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Sayfa Listesi -->
<div class="seo-pages-list">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="font-size: 24px; color: var(--dark);">SEO Yönetilen Sayfalar</h2>
        <form method="POST" style="display: inline;">
            <button type="submit" name="auto_detect" class="btn btn-primary">
                🔍 Sayfaları Otomatik Algıla
            </button>
        </form>
    </div>
    
    <?php if (empty($seoPages)): ?>
        <div style="text-align: center; padding: 40px; color: #64748b;">
            <p style="font-size: 18px; margin-bottom: 10px;">Henüz SEO ayarı yapılmamış</p>
            <p>Yukarıdaki formdan yeni sayfa ekleyebilir veya "Sayfaları Otomatik Algıla" butonunu kullanabilirsiniz.</p>
        </div>
    <?php else: ?>
        <?php foreach ($seoPages as $page): ?>
            <div class="page-item">
                <div class="page-info">
                    <h4>
                        <?= htmlspecialchars($page['title']) ?>
                        <?php if (!$page['is_active']): ?>
                            <span style="color: #dc2626; font-size: 12px;">(Pasif)</span>
                        <?php endif; ?>
                    </h4>
                    <p>
                        <strong>Tip:</strong> <?= htmlspecialchars($page['page_type']) ?>
                        <?php if ($page['page_id']): ?>
                            | <strong>ID:</strong> <?= $page['page_id'] ?>
                        <?php endif; ?>
                    </p>
                    <p>
                        <span class="page-path"><?= htmlspecialchars($page['page_path']) ?></span>
                        <?php if ($page['custom_slug']): ?>
                            <span style="color: #64748b; margin-left: 10px;">→</span>
                            <span class="page-path"><?= htmlspecialchars($page['custom_slug']) ?></span>
                        <?php endif; ?>
                    </p>
                    <?php if ($page['meta_description']): ?>
                        <p style="font-size: 12px; color: #94a3b8; margin-top: 8px;">
                            <?= htmlspecialchars(substr($page['meta_description'], 0, 100)) ?>...
                        </p>
                    <?php endif; ?>
                </div>
                <div class="page-actions">
                    <a href="?edit=<?= $page['id'] ?>" class="btn btn-outline">✏️ Düzenle</a>
                    <form method="POST" style="display: inline;" 
                          onsubmit="return confirm('Bu SEO kaydını silmek istediğinize emin misiniz?')">
                        <input type="hidden" name="seo_id" value="<?= $page['id'] ?>">
                        <button type="submit" name="delete_seo" class="btn btn-danger">🗑️ Sil</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
