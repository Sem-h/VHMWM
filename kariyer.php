<?php
/**
 * WHMVM - Kariyer
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Kariyer';
$pageDescription = 'WHMVM kariyer fırsatları ve açık pozisyonlar.';

require_once __DIR__ . '/theme/includes/header.php';
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

.benefits-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
    margin-bottom: 60px;
}

.benefit-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    padding: 30px;
    text-align: center;
}

.benefit-card i {
    font-size: 36px;
    color: var(--primary-light);
    margin-bottom: 15px;
}

.benefit-card h3 {
    font-size: 16px;
    margin-bottom: 10px;
}

.benefit-card p {
    color: var(--text-muted);
    font-size: 13px;
}

.jobs-section h2 {
    font-size: 32px;
    margin-bottom: 30px;
}

.job-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s;
}

.job-card:hover {
    border-color: var(--primary);
    transform: translateX(10px);
}

.job-info h3 {
    font-size: 20px;
    margin-bottom: 10px;
}

.job-tags {
    display: flex;
    gap: 10px;
}

.job-tags span {
    background: rgba(99, 102, 241, 0.2);
    color: var(--primary-light);
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
}

.job-card .btn {
    background: var(--gradient-primary);
    color: white;
    padding: 12px 25px;
    border-radius: 10px;
    font-weight: 600;
    border: none;
    cursor: pointer;
}

.no-jobs {
    text-align: center;
    padding: 60px;
    background: rgba(99, 102, 241, 0.1);
    border: 1px dashed var(--primary);
    border-radius: 20px;
}

.no-jobs i {
    font-size: 48px;
    color: var(--primary-light);
    margin-bottom: 20px;
}

.no-jobs p {
    color: var(--text-muted);
    margin-top: 15px;
}

@media (max-width: 992px) {
    .benefits-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .page-hero h1 { font-size: 32px; }
    .benefits-grid { grid-template-columns: 1fr; }
    .job-card { flex-direction: column; text-align: center; gap: 20px; }
}
</style>

<section class="page-hero">
    <div class="container">
        <h1>Ekibimize <span>Katılın</span></h1>
        <p>Teknoloji tutkusuyla dolu bir ekibin parçası olun.</p>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <div class="benefits-grid">
            <div class="benefit-card">
                <i class="fas fa-laptop-house"></i>
                <h3>Uzaktan Çalışma</h3>
                <p>Esnek çalışma modeli</p>
            </div>
            <div class="benefit-card">
                <i class="fas fa-graduation-cap"></i>
                <h3>Eğitim Desteği</h3>
                <p>Sürekli gelişim fırsatları</p>
            </div>
            <div class="benefit-card">
                <i class="fas fa-heartbeat"></i>
                <h3>Sağlık Sigortası</h3>
                <p>Özel sağlık sigortası</p>
            </div>
            <div class="benefit-card">
                <i class="fas fa-chart-line"></i>
                <h3>Kariyer Gelişimi</h3>
                <p>Net kariyer yolu</p>
            </div>
        </div>
        
        <div class="jobs-section">
            <h2>Açık Pozisyonlar</h2>
            
            <div class="no-jobs">
                <i class="fas fa-briefcase"></i>
                <h3>Şu anda açık pozisyon bulunmuyor</h3>
                <p>Ancak CV'nizi bize gönderebilirsiniz. Uygun pozisyonlar açıldığında sizinle iletişime geçeceğiz.</p>
                <a href="iletisim.php" class="btn btn-primary" style="margin-top: 20px;">CV Gönder</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>

