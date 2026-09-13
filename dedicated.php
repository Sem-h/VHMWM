<?php
/**
 * WHMVM - Dedicated Sunucu
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Dedicated Sunucu';
$pageDescription = 'Yüksek performanslı fiziksel sunucu çözümleri.';

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
    background: radial-gradient(ellipse at 50% 50%, rgba(245, 158, 11, 0.15) 0%, transparent 50%);
}

.page-hero .container { position: relative; z-index: 1; }

.page-hero h1 {
    font-size: 48px;
    font-weight: 800;
    margin-bottom: 20px;
}

.page-hero h1 span {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.page-hero p {
    font-size: 20px;
    color: var(--gray-light);
    max-width: 600px;
    margin: 0 auto 30px;
}

.servers-section { padding: 80px 0; }

.servers-section h2 {
    text-align: center;
    font-size: 36px;
    margin-bottom: 50px;
}

.server-grid {
    display: grid;
    gap: 25px;
    max-width: 1000px;
    margin: 0 auto;
}

.server-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 30px;
    display: grid;
    grid-template-columns: 1fr 2fr auto;
    align-items: center;
    gap: 30px;
    transition: all 0.3s;
}

.server-card:hover {
    border-color: #f59e0b;
    transform: translateY(-3px);
}

.server-name {
    text-align: center;
}

.server-name h3 {
    font-size: 24px;
    margin-bottom: 10px;
}

.server-name .badge {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 12px;
}

.server-specs {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.spec-item {
    text-align: center;
}

.spec-item .value {
    font-size: 18px;
    font-weight: 700;
    color: #f59e0b;
}

.spec-item .label {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 5px;
}

.server-price {
    text-align: center;
}

.server-price .price {
    font-size: 32px;
    font-weight: 800;
    color: #f59e0b;
}

.server-price .period {
    color: var(--text-muted);
    font-size: 14px;
}

.server-price .btn {
    margin-top: 15px;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    border: none;
    color: white;
    padding: 12px 25px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.server-price .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(245, 158, 11, 0.3);
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
    margin-top: 60px;
    max-width: 1000px;
    margin-left: auto;
    margin-right: auto;
}

.feature-item {
    text-align: center;
    padding: 30px;
    background: rgba(245, 158, 11, 0.1);
    border-radius: 15px;
}

.feature-item i {
    font-size: 36px;
    color: #f59e0b;
    margin-bottom: 15px;
}

.feature-item h4 {
    font-size: 16px;
    margin-bottom: 10px;
}

.feature-item p {
    color: var(--text-muted);
    font-size: 13px;
}

@media (max-width: 992px) {
    .server-card {
        grid-template-columns: 1fr;
        text-align: center;
    }
    .server-specs { grid-template-columns: repeat(2, 1fr); }
    .features-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .page-hero h1 { font-size: 32px; }
    .features-grid { grid-template-columns: 1fr; }
}
</style>

<section class="page-hero">
    <div class="container">
        <h1>Dedicated <span>Sunucu</span></h1>
        <p>Tüm kaynaklar sadece size ait. Maksimum performans ve kontrol.</p>
    </div>
</section>

<section class="servers-section">
    <div class="container">
        <h2>Sunucu Modelleri</h2>
        
        <div class="server-grid">
            <div class="server-card">
                <div class="server-name">
                    <h3>DS-1</h3>
                    <span class="badge">Entry</span>
                </div>
                <div class="server-specs">
                    <div class="spec-item">
                        <div class="value">Intel Xeon E3</div>
                        <div class="label">İşlemci</div>
                    </div>
                    <div class="spec-item">
                        <div class="value">32 GB</div>
                        <div class="label">RAM</div>
                    </div>
                    <div class="spec-item">
                        <div class="value">2x 500GB</div>
                        <div class="label">SSD</div>
                    </div>
                    <div class="spec-item">
                        <div class="value">1 Gbps</div>
                        <div class="label">Network</div>
                    </div>
                </div>
                <div class="server-price">
                    <div class="price">2.499₺</div>
                    <div class="period">/ay</div>
                    <a href="contact.php" class="btn">Teklif Al</a>
                </div>
            </div>
            
            <div class="server-card">
                <div class="server-name">
                    <h3>DS-2</h3>
                    <span class="badge">Business</span>
                </div>
                <div class="server-specs">
                    <div class="spec-item">
                        <div class="value">Intel Xeon E5</div>
                        <div class="label">İşlemci</div>
                    </div>
                    <div class="spec-item">
                        <div class="value">64 GB</div>
                        <div class="label">RAM</div>
                    </div>
                    <div class="spec-item">
                        <div class="value">4x 1TB</div>
                        <div class="label">SSD</div>
                    </div>
                    <div class="spec-item">
                        <div class="value">1 Gbps</div>
                        <div class="label">Network</div>
                    </div>
                </div>
                <div class="server-price">
                    <div class="price">4.999₺</div>
                    <div class="period">/ay</div>
                    <a href="contact.php" class="btn">Teklif Al</a>
                </div>
            </div>
            
            <div class="server-card">
                <div class="server-name">
                    <h3>DS-3</h3>
                    <span class="badge">Enterprise</span>
                </div>
                <div class="server-specs">
                    <div class="spec-item">
                        <div class="value">2x Intel Xeon</div>
                        <div class="label">İşlemci</div>
                    </div>
                    <div class="spec-item">
                        <div class="value">128 GB</div>
                        <div class="label">RAM</div>
                    </div>
                    <div class="spec-item">
                        <div class="value">4x 2TB NVMe</div>
                        <div class="label">SSD</div>
                    </div>
                    <div class="spec-item">
                        <div class="value">10 Gbps</div>
                        <div class="label">Network</div>
                    </div>
                </div>
                <div class="server-price">
                    <div class="price">9.999₺</div>
                    <div class="period">/ay</div>
                    <a href="contact.php" class="btn">Teklif Al</a>
                </div>
            </div>
        </div>
        
        <div class="features-grid">
            <div class="feature-item">
                <i class="fas fa-server"></i>
                <h4>Tam Erişim</h4>
                <p>Root/Admin erişimi ile tam kontrol</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-clock"></i>
                <h4>4 Saat Kurulum</h4>
                <p>Hızlı sunucu hazırlık süresi</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-tools"></i>
                <h4>Donanım Değişimi</h4>
                <p>Arıza durumunda ücretsiz değişim</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-headset"></i>
                <h4>Öncelikli Destek</h4>
                <p>7/24 öncelikli teknik destek</p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>

