<?php
/**
 * WHMVM - Bilgi Bankası
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Bilgi Bankası';
$pageDescription = 'WHMVM yardım merkezi, sık sorulan sorular ve rehberler.';

require_once __DIR__ . '/theme/includes/header.php';

// Demo kategoriler
$categories = [
    ['name' => 'Başlangıç', 'icon' => 'fa-rocket', 'count' => 12, 'desc' => 'Yeni başlayanlar için rehberler'],
    ['name' => 'Hosting', 'icon' => 'fa-server', 'count' => 24, 'desc' => 'Web hosting ile ilgili makaleler'],
    ['name' => 'VDS/VPS', 'icon' => 'fa-database', 'count' => 18, 'desc' => 'Sanal sunucu yönetimi'],
    ['name' => 'Domain', 'icon' => 'fa-globe', 'count' => 8, 'desc' => 'Alan adı işlemleri'],
    ['name' => 'E-posta', 'icon' => 'fa-envelope', 'count' => 15, 'desc' => 'E-posta yapılandırması'],
    ['name' => 'Güvenlik', 'icon' => 'fa-shield-alt', 'count' => 20, 'desc' => 'Güvenlik önlemleri'],
    ['name' => 'Faturalama', 'icon' => 'fa-credit-card', 'count' => 10, 'desc' => 'Ödeme ve fatura işlemleri'],
    ['name' => 'SSL', 'icon' => 'fa-lock', 'count' => 6, 'desc' => 'SSL sertifikaları'],
];

// Demo popüler makaleler
$popularArticles = [
    'cPanel\'e Nasıl Giriş Yapılır?',
    'DNS Ayarları Nasıl Yapılır?',
    'WordPress Kurulumu',
    'E-posta Hesabı Oluşturma',
    'SSL Sertifikası Kurulumu',
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

.search-box {
    max-width: 600px;
    margin: 30px auto 0;
    position: relative;
}

.search-box input {
    width: 100%;
    padding: 18px 25px;
    padding-right: 60px;
    background: rgba(255,255,255,0.1);
    border: 1px solid var(--border-color);
    border-radius: 50px;
    color: white;
    font-size: 16px;
}

.search-box input::placeholder {
    color: var(--text-muted);
}

.search-box button {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    width: 45px;
    height: 45px;
    background: var(--gradient-primary);
    border: none;
    border-radius: 50%;
    color: white;
    cursor: pointer;
}

.content-section { padding: 80px 0; }

.kb-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
}

.kb-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 30px;
    text-align: center;
    transition: all 0.3s;
    cursor: pointer;
}

.kb-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary);
}

.kb-card i {
    font-size: 40px;
    color: var(--primary-light);
    margin-bottom: 20px;
}

.kb-card h3 {
    font-size: 18px;
    margin-bottom: 10px;
}

.kb-card p {
    color: var(--text-muted);
    font-size: 14px;
    margin-bottom: 15px;
}

.kb-card .count {
    background: rgba(99, 102, 241, 0.2);
    color: var(--primary-light);
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 13px;
}

.popular-section {
    margin-top: 60px;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
}

.popular-section h2 {
    font-size: 28px;
    text-align: center;
    margin-bottom: 30px;
}

.popular-list {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    overflow: hidden;
}

.popular-item {
    display: flex;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 1px solid var(--border-color);
    transition: all 0.3s;
    cursor: pointer;
}

.popular-item:last-child {
    border-bottom: none;
}

.popular-item:hover {
    background: rgba(99, 102, 241, 0.1);
}

.popular-item i {
    color: var(--primary-light);
    margin-right: 15px;
}

.popular-item span {
    flex: 1;
}

.popular-item .arrow {
    color: var(--text-muted);
}

@media (max-width: 992px) {
    .kb-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .page-hero h1 { font-size: 32px; }
    .kb-grid { grid-template-columns: 1fr; }
}
</style>

<section class="page-hero">
    <div class="container">
        <h1>Bilgi <span>Bankası</span></h1>
        <p>Aradığınız cevapları hızlıca bulun.</p>
        <div class="search-box">
            <input type="text" placeholder="Ne aramak istersiniz?">
            <button type="submit"><i class="fas fa-search"></i></button>
        </div>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <div class="kb-grid">
            <?php foreach ($categories as $cat): ?>
                <div class="kb-card">
                    <i class="fas <?= $cat['icon'] ?>"></i>
                    <h3><?= $cat['name'] ?></h3>
                    <p><?= $cat['desc'] ?></p>
                    <span class="count"><?= $cat['count'] ?> makale</span>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="popular-section">
            <h2>Popüler Makaleler</h2>
            <div class="popular-list">
                <?php foreach ($popularArticles as $article): ?>
                    <div class="popular-item">
                        <i class="fas fa-file-alt"></i>
                        <span><?= $article ?></span>
                        <i class="fas fa-chevron-right arrow"></i>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>

