<?php
/**
 * WHMVM - Blog
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Blog';
$pageDescription = 'WHMVM blog yazıları, haberler ve duyurular.';

require_once __DIR__ . '/theme/includes/header.php';

// Demo blog yazıları
$posts = [
    ['title' => 'PHP 8.3 ile Gelen Yenilikler', 'excerpt' => 'PHP 8.3 sürümü ile gelen performans iyileştirmeleri ve yeni özellikler hakkında bilgi edinin.', 'date' => '2024-01-15', 'category' => 'Teknoloji', 'image' => 'php'],
    ['title' => 'Sunucu Güvenliği İçin 10 Altın Kural', 'excerpt' => 'Sunucunuzu güvende tutmak için mutlaka uygulamanız gereken güvenlik önlemleri.', 'date' => '2024-01-10', 'category' => 'Güvenlik', 'image' => 'security'],
    ['title' => 'WordPress Sitenizi Hızlandırın', 'excerpt' => 'WordPress sitenizin yüklenme hızını artırmak için uygulayabileceğiniz ipuçları.', 'date' => '2024-01-05', 'category' => 'WordPress', 'image' => 'wordpress'],
    ['title' => 'SSL Sertifikası Neden Önemli?', 'excerpt' => 'Web siteniz için SSL sertifikasının önemi ve SEO üzerindeki etkileri.', 'date' => '2024-01-01', 'category' => 'Güvenlik', 'image' => 'ssl'],
];
?>

<style>
.page-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
    padding: 120px 0 80px;
    text-align: center;
    position: relative;
}

.page-hero::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: radial-gradient(ellipse at 50% 50%, rgba(99, 102, 241, 0.15) 0%, transparent 50%);
}

.page-hero .container { position: relative; z-index: 1; }

.page-hero h1 {
    font-size: 48px;
    font-weight: 800;
    margin-bottom: 20px;
}

.page-hero h1 span {
    background: var(--gradient-primary);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.content-section { padding: 80px 0; }

.blog-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 30px;
    max-width: 1000px;
    margin: 0 auto;
}

.blog-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.3s;
}

.blog-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary);
}

.blog-image {
    height: 200px;
    background: var(--gradient-primary);
    display: flex;
    align-items: center;
    justify-content: center;
}

.blog-image i {
    font-size: 64px;
    color: rgba(255,255,255,0.3);
}

.blog-content {
    padding: 30px;
}

.blog-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
    font-size: 13px;
    color: var(--text-muted);
}

.blog-category {
    background: rgba(99, 102, 241, 0.2);
    color: var(--primary-light);
    padding: 4px 12px;
    border-radius: 20px;
}

.blog-content h3 {
    font-size: 20px;
    margin-bottom: 15px;
    line-height: 1.4;
}

.blog-content h3 a {
    color: inherit;
    text-decoration: none;
}

.blog-content h3 a:hover {
    color: var(--primary-light);
}

.blog-content p {
    color: var(--text-muted);
    line-height: 1.7;
    margin-bottom: 20px;
}

.read-more {
    color: var(--primary-light);
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.read-more:hover {
    gap: 12px;
}

.coming-soon {
    text-align: center;
    padding: 60px;
    background: rgba(99, 102, 241, 0.1);
    border: 1px dashed var(--primary);
    border-radius: 20px;
    margin-top: 40px;
}

.coming-soon i {
    font-size: 48px;
    color: var(--primary-light);
    margin-bottom: 20px;
}

.coming-soon h3 {
    margin-bottom: 10px;
}

.coming-soon p {
    color: var(--text-muted);
}

@media (max-width: 768px) {
    .page-hero h1 { font-size: 32px; }
    .blog-grid { grid-template-columns: 1fr; }
}
</style>

<section class="page-hero">
    <div class="container">
        <h1>Blog & <span>Haberler</span></h1>
        <p>Teknoloji dünyasından haberler, ipuçları ve rehberler.</p>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <div class="blog-grid">
            <?php foreach ($posts as $post): ?>
                <article class="blog-card">
                    <div class="blog-image">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <div class="blog-content">
                        <div class="blog-meta">
                            <span class="blog-category"><?= $post['category'] ?></span>
                            <span><i class="far fa-calendar"></i> <?= date('d.m.Y', strtotime($post['date'])) ?></span>
                        </div>
                        <h3><a href="#"><?= $post['title'] ?></a></h3>
                        <p><?= $post['excerpt'] ?></p>
                        <a href="#" class="read-more">
                            Devamını Oku <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        
        <div class="coming-soon">
            <i class="fas fa-rocket"></i>
            <h3>Daha Fazla İçerik Geliyor!</h3>
            <p>Yakında daha fazla blog yazısı ve rehber yayınlayacağız.</p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>

