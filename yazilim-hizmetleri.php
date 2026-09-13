<?php
/**
 * WHMVM - Yazılım Hizmetleri Sayfası
 * magaza.php?group=windows-hosting tasarımı referans alınarak
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

// Sayfa değişkenleri
$pageTitle = 'Yazılım Hizmetleri';
$pageDescription = 'Profesyonel yazılım geliştirme, web tasarım ve dijital çözümler. Özel yazılım projeleriniz için uzman ekibimizle çalışın.';

// Header
require_once __DIR__ . '/theme/includes/header.php';
?>

<style>
/* Yazılım Theme Colors */
:root {
    --soft-purple: #8b5cf6;
    --soft-purple-dark: #7c3aed;
    --soft-purple-light: #a78bfa;
    --soft-gradient: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%);
}

/* Yazılım Hero */
.soft-hero {
    background: linear-gradient(135deg, #0a0a1a 0%, #0f1628 50%, #0a0f1a 100%);
    padding: 80px 0 60px;
    position: relative;
    overflow: hidden;
}

.soft-hero::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: 
        radial-gradient(ellipse at 20% 30%, rgba(139, 92, 246, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 70%, rgba(236, 72, 153, 0.1) 0%, transparent 40%);
}

/* Yazılım Grid Pattern */
.soft-hero::after {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background-image: 
        linear-gradient(rgba(139, 92, 246, 0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(139, 92, 246, 0.03) 1px, transparent 1px);
    background-size: 50px 50px;
}

.soft-hero .container {
    position: relative;
    z-index: 2;
}

.soft-hero-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
}

.soft-hero-text h1 {
    font-size: 52px;
    font-weight: 800;
    color: #fff;
    line-height: 1.1;
    margin-bottom: 24px;
}

.soft-hero-text h1 .soft-logo {
    display: inline-flex;
    align-items: center;
    gap: 12px;
}

.soft-hero-text h1 .soft-logo i {
    color: var(--soft-purple);
}

.soft-hero-text h1 span {
    background: var(--soft-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.soft-hero-text p {
    font-size: 18px;
    color: #94a3b8;
    line-height: 1.7;
    margin-bottom: 32px;
}

/* Yazılım Tech Stack */
.soft-tech-stack {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 30px;
}

.soft-tech-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(139, 92, 246, 0.1);
    border: 1px solid rgba(139, 92, 246, 0.3);
    padding: 10px 18px;
    border-radius: 8px;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
}

.soft-tech-badge i {
    color: var(--soft-purple);
}

/* Yazılım Visual */
.soft-hero-visual {
    position: relative;
}

.soft-window {
    background: linear-gradient(145deg, #1a1a2e 0%, #0f172a 100%);
    border: 1px solid rgba(139, 92, 246, 0.3);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5), 0 0 50px rgba(139, 92, 246, 0.15);
}

.soft-window-header {
    background: var(--soft-gradient);
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.soft-window-dots {
    display: flex;
    gap: 8px;
}

.soft-window-dots span {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
}

.soft-window-title {
    color: white;
    font-size: 13px;
    font-weight: 600;
    flex: 1;
}

.soft-window-body {
    padding: 30px;
}

.soft-code-icon {
    width: 100px;
    height: 100px;
    background: var(--soft-gradient);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 25px;
    font-size: 45px;
    color: white;
    box-shadow: 0 15px 40px rgba(139, 92, 246, 0.4);
}

.soft-stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.soft-stat-box {
    background: rgba(139, 92, 246, 0.1);
    border: 1px solid rgba(139, 92, 246, 0.2);
    border-radius: 10px;
    padding: 15px;
    text-align: center;
}

.soft-stat-value {
    font-size: 22px;
    font-weight: 800;
    color: var(--soft-purple);
}

.soft-stat-label {
    font-size: 11px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Yazılım Services Section */
.soft-services {
    padding: 60px 0 80px;
    background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
}

.soft-section-header {
    text-align: center;
    margin-bottom: 50px;
}

.soft-section-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(139, 92, 246, 0.15);
    color: var(--soft-purple);
    padding: 8px 20px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 16px;
}

.soft-section-title {
    font-size: 36px;
    font-weight: 800;
    color: #fff;
}

.soft-section-title span {
    background: var(--soft-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

/* Yazılım Services Grid */
.soft-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
    max-width: 1400px;
    margin: 0 auto;
}

.soft-grid.cols-1 { grid-template-columns: minmax(300px, 400px); justify-content: center; }
.soft-grid.cols-2 { grid-template-columns: repeat(2, minmax(300px, 1fr)); max-width: 800px; }
.soft-grid.cols-3 { grid-template-columns: repeat(3, minmax(300px, 1fr)); max-width: 1100px; }
.soft-grid.cols-4 { grid-template-columns: repeat(4, minmax(280px, 1fr)); max-width: 1400px; }

/* Yazılım Service Card */
.soft-card {
    background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid rgba(139, 92, 246, 0.15);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.4s ease;
    position: relative;
}

.soft-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 100%;
    height: 4px;
    background: var(--soft-gradient);
    opacity: 0;
    transition: opacity 0.3s;
}

.soft-card:hover {
    transform: translateY(-8px);
    border-color: rgba(139, 92, 246, 0.4);
    box-shadow: 0 25px 50px rgba(0,0,0,0.4), 0 0 30px rgba(139, 92, 246, 0.1);
}

.soft-card:hover::before {
    opacity: 1;
}

.soft-card-header {
    padding: 35px 30px;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.soft-card-icon {
    width: 70px;
    height: 70px;
    background: rgba(139, 92, 246, 0.15);
    border: 1px solid rgba(139, 92, 246, 0.3);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 30px;
    color: var(--soft-purple);
}

.soft-card-name {
    font-size: 22px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 12px;
}

.soft-card-desc {
    font-size: 14px;
    color: #64748b;
    line-height: 1.6;
    margin-bottom: 20px;
}

.soft-card-body {
    padding: 30px;
}

.soft-card-features {
    list-style: none;
    margin-bottom: 30px;
}

.soft-card-features li {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    color: #cbd5e1;
    font-size: 14px;
}

.soft-card-features li:last-child {
    border-bottom: none;
}

.soft-card-features li i {
    width: 22px;
    height: 22px;
    background: rgba(16, 185, 129, 0.15);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #10b981;
    font-size: 10px;
}

.soft-btn {
    display: block;
    width: 100%;
    padding: 16px;
    background: var(--soft-gradient);
    border: none;
    border-radius: 10px;
    color: white;
    font-size: 15px;
    font-weight: 700;
    text-align: center;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 20px rgba(139, 92, 246, 0.3);
}

.soft-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(139, 92, 246, 0.5);
}

/* Yazılım Pricing Section */
.soft-pricing {
    padding: 80px 0;
    background: #0a0f1a;
}

.soft-pricing-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
    max-width: 1400px;
    margin: 0 auto;
}

.soft-pricing-card {
    background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid rgba(139, 92, 246, 0.15);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.4s ease;
    position: relative;
}

.soft-pricing-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 100%;
    height: 4px;
    background: var(--soft-gradient);
    opacity: 0;
    transition: opacity 0.3s;
}

.soft-pricing-card:hover {
    transform: translateY(-8px);
    border-color: rgba(139, 92, 246, 0.4);
    box-shadow: 0 25px 50px rgba(0,0,0,0.4), 0 0 30px rgba(139, 92, 246, 0.1);
}

.soft-pricing-card:hover::before {
    opacity: 1;
}

.soft-pricing-card.popular {
    border-color: var(--soft-purple);
    transform: scale(1.03);
}

.soft-pricing-card.popular:hover {
    transform: scale(1.03) translateY(-8px);
}

.soft-pricing-badge {
    background: var(--soft-gradient);
    color: white;
    text-align: center;
    padding: 10px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.soft-pricing-header {
    padding: 35px 30px;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.soft-pricing-name {
    font-size: 22px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 20px;
}

.soft-pricing-price {
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: 5px;
    margin-bottom: 20px;
}

.soft-pricing-price .currency {
    font-size: 20px;
    color: var(--soft-purple);
    vertical-align: top;
}

.soft-pricing-price .amount {
    font-size: 44px;
    font-weight: 800;
    color: var(--soft-purple);
}

.soft-pricing-price .period {
    font-size: 14px;
    color: #64748b;
}

.soft-pricing-body {
    padding: 30px;
}

.soft-pricing-features {
    list-style: none;
    margin-bottom: 30px;
}

.soft-pricing-features li {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    color: #cbd5e1;
    font-size: 14px;
}

.soft-pricing-features li:last-child {
    border-bottom: none;
}

.soft-pricing-features li i {
    width: 22px;
    height: 22px;
    background: rgba(16, 185, 129, 0.15);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #10b981;
    font-size: 10px;
}

/* Yazılım Features Section */
.soft-features-section {
    padding: 80px 0;
    background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
}

.soft-features-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 24px;
}

.soft-feature-card {
    background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 14px;
    padding: 30px;
    text-align: center;
    transition: all 0.3s;
}

.soft-feature-card:hover {
    border-color: rgba(139, 92, 246, 0.3);
    background: linear-gradient(145deg, #1e293b 0%, rgba(139, 92, 246, 0.05) 100%);
}

.soft-feature-icon {
    width: 56px;
    height: 56px;
    background: rgba(139, 92, 246, 0.15);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 18px;
    font-size: 22px;
    color: var(--soft-purple);
}

.soft-feature-card h4 {
    font-size: 15px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 8px;
}

.soft-feature-card p {
    font-size: 13px;
    color: #64748b;
    line-height: 1.6;
}

/* Yazılım CTA */
.soft-cta {
    padding: 60px 0;
    background: var(--soft-gradient);
    text-align: center;
    position: relative;
    overflow: hidden;
}

.soft-cta::before {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
}

.soft-cta .container {
    position: relative;
    z-index: 1;
}

.soft-cta h3 {
    font-size: 28px;
    font-weight: 800;
    color: #fff;
    margin-bottom: 12px;
}

.soft-cta p {
    font-size: 16px;
    color: rgba(255,255,255,0.8);
    margin-bottom: 24px;
}

.soft-cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: #fff;
    color: var(--soft-purple-dark);
    padding: 16px 32px;
    border-radius: 10px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s;
}

.soft-cta-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

/* Responsive */
@media (max-width: 1200px) {
    .soft-hero-content { grid-template-columns: 1fr; text-align: center; }
    .soft-hero-visual { display: none; }
    .soft-grid { grid-template-columns: repeat(2, 1fr) !important; max-width: 100% !important; }
    .soft-pricing-grid { grid-template-columns: repeat(2, 1fr) !important; }
    .soft-features-grid { grid-template-columns: repeat(2, 1fr); }
    .soft-tech-stack { justify-content: center; }
}

@media (max-width: 768px) {
    .soft-hero-text h1 { font-size: 36px; }
    .soft-grid { grid-template-columns: 1fr !important; }
    .soft-pricing-grid { grid-template-columns: 1fr !important; }
    .soft-features-grid { grid-template-columns: 1fr; }
}
</style>

<!-- Yazılım Hero -->
<section class="soft-hero">
    <div class="container">
        <div class="soft-hero-content">
            <div class="soft-hero-text">
                <h1>
                    <span class="soft-logo"><i class="fas fa-code"></i> Yazılım</span><br>
                    <span>Hizmetleri</span> & Çözümler
                </h1>
                <p>Profesyonel yazılım geliştirme, web tasarım ve dijital çözümler ile işletmenizi dijital dünyaya taşıyoruz. Modern teknolojiler ve uzman ekibimizle projelerinizi hayata geçirin.</p>
                
                <div class="soft-tech-stack">
                    <div class="soft-tech-badge">
                        <i class="fas fa-laptop-code"></i> Web Geliştirme
                    </div>
                    <div class="soft-tech-badge">
                        <i class="fas fa-mobile-alt"></i> Mobil Uygulama
                    </div>
                    <div class="soft-tech-badge">
                        <i class="fas fa-shopping-cart"></i> Shopify
                    </div>
                    <div class="soft-tech-badge">
                        <i class="fas fa-cogs"></i> Özel Yazılım
                    </div>
                    <div class="soft-tech-badge">
                        <i class="fas fa-paint-brush"></i> Web Tasarım
                    </div>
                </div>
            </div>
            
            <div class="soft-hero-visual">
                <div class="soft-window">
                    <div class="soft-window-header">
                        <div class="soft-window-dots">
                            <span></span><span></span><span></span>
                        </div>
                        <div class="soft-window-title">
                            <i class="fas fa-code"></i> Yazılım Geliştirme
                        </div>
                    </div>
                    <div class="soft-window-body">
                        <div class="soft-code-icon">
                            <i class="fas fa-code"></i>
                        </div>
                        <div class="soft-stats-row">
                            <div class="soft-stat-box">
                                <div class="soft-stat-value">100+</div>
                                <div class="soft-stat-label">Proje</div>
                            </div>
                            <div class="soft-stat-box">
                                <div class="soft-stat-value">7/24</div>
                                <div class="soft-stat-label">Destek</div>
                            </div>
                            <div class="soft-stat-box">
                                <div class="soft-stat-value">%100</div>
                                <div class="soft-stat-label">Memnuniyet</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Yazılım Services -->
<section class="soft-services">
    <div class="container">
        <div class="soft-section-header">
            <div class="soft-section-badge">
                <i class="fas fa-code"></i> Hizmetlerimiz
            </div>
            <h2 class="soft-section-title">Yazılım <span>Çözümlerimiz</span></h2>
        </div>
        
        <div class="soft-grid cols-3">
            <!-- Web Geliştirme -->
            <div class="soft-card">
                <div class="soft-card-header">
                    <div class="soft-card-icon">
                        <i class="fas fa-laptop-code"></i>
                    </div>
                    <div class="soft-card-name">Web Geliştirme</div>
                    <div class="soft-card-desc">Modern ve responsive web siteleri, e-ticaret platformları ve web uygulamaları geliştiriyoruz.</div>
                </div>
                <div class="soft-card-body">
                    <ul class="soft-card-features">
                        <li><i class="fas fa-check"></i> Responsive Tasarım</li>
                        <li><i class="fas fa-check"></i> E-Ticaret Çözümleri</li>
                        <li><i class="fas fa-check"></i> CMS Entegrasyonu</li>
                        <li><i class="fas fa-check"></i> SEO Optimizasyonu</li>
                    </ul>
                    <a href="iletisim.php" class="soft-btn">Teklif Al</a>
                </div>
            </div>
            
            <!-- Mobil Uygulama -->
            <div class="soft-card">
                <div class="soft-card-header">
                    <div class="soft-card-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <div class="soft-card-name">Mobil Uygulama</div>
                    <div class="soft-card-desc">iOS ve Android platformları için native ve cross-platform mobil uygulamalar geliştiriyoruz.</div>
                </div>
                <div class="soft-card-body">
                    <ul class="soft-card-features">
                        <li><i class="fas fa-check"></i> iOS & Android</li>
                        <li><i class="fas fa-check"></i> React Native</li>
                        <li><i class="fas fa-check"></i> Flutter</li>
                        <li><i class="fas fa-check"></i> App Store Yayınlama</li>
                    </ul>
                    <a href="iletisim.php" class="soft-btn">Teklif Al</a>
                </div>
            </div>
            
            <!-- Özel Yazılım -->
            <div class="soft-card">
                <div class="soft-card-header">
                    <div class="soft-card-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <div class="soft-card-name">Özel Yazılım</div>
                    <div class="soft-card-desc">İşletmenize özel yazılım çözümleri. ERP, CRM ve iş süreç yönetim sistemleri.</div>
                </div>
                <div class="soft-card-body">
                    <ul class="soft-card-features">
                        <li><i class="fas fa-check"></i> ERP Sistemleri</li>
                        <li><i class="fas fa-check"></i> CRM Çözümleri</li>
                        <li><i class="fas fa-check"></i> İş Süreç Yönetimi</li>
                        <li><i class="fas fa-check"></i> API Geliştirme</li>
                    </ul>
                    <a href="iletisim.php" class="soft-btn">Teklif Al</a>
                </div>
            </div>
            
            <!-- Web Tasarım -->
            <div class="soft-card">
                <div class="soft-card-header">
                    <div class="soft-card-icon">
                        <i class="fas fa-paint-brush"></i>
                    </div>
                    <div class="soft-card-name">Web Tasarım</div>
                    <div class="soft-card-desc">Kullanıcı dostu, modern ve etkileyici web tasarımları ile markanızı öne çıkarın.</div>
                </div>
                <div class="soft-card-body">
                    <ul class="soft-card-features">
                        <li><i class="fas fa-check"></i> UI/UX Tasarım</li>
                        <li><i class="fas fa-check"></i> Logo & Branding</li>
                        <li><i class="fas fa-check"></i> Grafik Tasarım</li>
                        <li><i class="fas fa-check"></i> Prototipleme</li>
                    </ul>
                    <a href="iletisim.php" class="soft-btn">Teklif Al</a>
                </div>
            </div>
            
            <!-- E-Ticaret -->
            <div class="soft-card">
                <div class="soft-card-header">
                    <div class="soft-card-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="soft-card-name">E-Ticaret</div>
                    <div class="soft-card-desc">Güçlü ve güvenli e-ticaret platformları ile online satış yapmaya başlayın.</div>
                </div>
                <div class="soft-card-body">
                    <ul class="soft-card-features">
                        <li><i class="fas fa-check"></i> Shopify</li>
                        <li><i class="fas fa-check"></i> Özel E-Ticaret</li>
                        <li><i class="fas fa-check"></i> Ödeme Entegrasyonu</li>
                        <li><i class="fas fa-check"></i> Stok Yönetimi</li>
                    </ul>
                    <a href="iletisim.php" class="soft-btn">Teklif Al</a>
                </div>
            </div>
            
            <!-- Bakım & Destek -->
            <div class="soft-card">
                <div class="soft-card-header">
                    <div class="soft-card-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="soft-card-name">Bakım & Destek</div>
                    <div class="soft-card-desc">Yazılımlarınızın güncel kalması ve sorunsuz çalışması için 7/24 teknik destek.</div>
                </div>
                <div class="soft-card-body">
                    <ul class="soft-card-features">
                        <li><i class="fas fa-check"></i> 7/24 Destek</li>
                        <li><i class="fas fa-check"></i> Güncelleme & Yama</li>
                        <li><i class="fas fa-check"></i> Yedekleme</li>
                        <li><i class="fas fa-check"></i> Performans Optimizasyonu</li>
                    </ul>
                    <a href="iletisim.php" class="soft-btn">Teklif Al</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Yazılım Pricing -->
<section class="soft-pricing">
    <div class="container">
        <div class="soft-section-header">
            <div class="soft-section-badge">
                <i class="fas fa-tags"></i> Fiyatlandırma
            </div>
            <h2 class="soft-section-title">Yazılım <span>Paketlerimiz</span></h2>
        </div>
        
        <div class="soft-pricing-grid">
            <!-- Temel Paket -->
            <div class="soft-pricing-card">
                <div class="soft-pricing-header">
                    <div class="soft-pricing-name">Temel</div>
                    <div class="soft-pricing-price">
                        <span class="currency">₺</span>
                        <span class="amount">2.500</span>
                        <span class="period">/proje</span>
                    </div>
                </div>
                <div class="soft-pricing-body">
                    <ul class="soft-pricing-features">
                        <li><i class="fas fa-check"></i> 5 Sayfa Web Sitesi</li>
                        <li><i class="fas fa-check"></i> Responsive Tasarım</li>
                        <li><i class="fas fa-check"></i> SEO Temel Optimizasyon</li>
                        <li><i class="fas fa-check"></i> 3 Ay Destek</li>
                        <li><i class="fas fa-check"></i> Temel Eğitim</li>
                    </ul>
                    <a href="iletisim.php?package=temel" class="soft-btn">Teklif Al</a>
                </div>
            </div>
            
            <!-- Profesyonel Paket -->
            <div class="soft-pricing-card popular">
                <div class="soft-pricing-badge"><i class="fas fa-star"></i> En Popüler</div>
                <div class="soft-pricing-header">
                    <div class="soft-pricing-name">Profesyonel</div>
                    <div class="soft-pricing-price">
                        <span class="currency">₺</span>
                        <span class="amount">5.000</span>
                        <span class="period">/proje</span>
                    </div>
                </div>
                <div class="soft-pricing-body">
                    <ul class="soft-pricing-features">
                        <li><i class="fas fa-check"></i> 10 Sayfa Web Sitesi</li>
                        <li><i class="fas fa-check"></i> Özel Tasarım</li>
                        <li><i class="fas fa-check"></i> SEO Gelişmiş</li>
                        <li><i class="fas fa-check"></i> 6 Ay Destek</li>
                        <li><i class="fas fa-check"></i> CMS Entegrasyonu</li>
                        <li><i class="fas fa-check"></i> Eğitim & Dokümantasyon</li>
                    </ul>
                    <a href="iletisim.php?package=profesyonel" class="soft-btn">Teklif Al</a>
                </div>
            </div>
            
            <!-- Kurumsal Paket -->
            <div class="soft-pricing-card">
                <div class="soft-pricing-header">
                    <div class="soft-pricing-name">Kurumsal</div>
                    <div class="soft-pricing-price">
                        <span class="currency">₺</span>
                        <span class="amount">10.000</span>
                        <span class="period">/proje</span>
                    </div>
                </div>
                <div class="soft-pricing-body">
                    <ul class="soft-pricing-features">
                        <li><i class="fas fa-check"></i> Sınırsız Sayfa</li>
                        <li><i class="fas fa-check"></i> Özel Yazılım Geliştirme</li>
                        <li><i class="fas fa-check"></i> API Entegrasyonları</li>
                        <li><i class="fas fa-check"></i> 12 Ay Destek</li>
                        <li><i class="fas fa-check"></i> Öncelikli Destek</li>
                        <li><i class="fas fa-check"></i> Özel Eğitim</li>
                    </ul>
                    <a href="iletisim.php?package=kurumsal" class="soft-btn">Teklif Al</a>
                </div>
            </div>
            
            <!-- Özel Proje -->
            <div class="soft-pricing-card">
                <div class="soft-pricing-header">
                    <div class="soft-pricing-name">Özel Proje</div>
                    <div class="soft-pricing-price">
                        <span class="currency">₺</span>
                        <span class="amount">Özel</span>
                        <span class="period">Teklif</span>
                    </div>
                </div>
                <div class="soft-pricing-body">
                    <ul class="soft-pricing-features">
                        <li><i class="fas fa-check"></i> İhtiyaca Özel Çözüm</li>
                        <li><i class="fas fa-check"></i> Mobil Uygulama</li>
                        <li><i class="fas fa-check"></i> ERP/CRM Sistemleri</li>
                        <li><i class="fas fa-check"></i> Özel Geliştirme</li>
                        <li><i class="fas fa-check"></i> Sürekli Destek</li>
                        <li><i class="fas fa-check"></i> Özel Anlaşma</li>
                    </ul>
                    <a href="iletisim.php?package=ozel" class="soft-btn">Teklif Al</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Yazılım Features -->
<section class="soft-features-section">
    <div class="container">
        <div class="soft-section-header" style="margin-bottom: 50px;">
            <div class="soft-section-badge">
                <i class="fas fa-star"></i> Avantajlar
            </div>
            <h2 class="soft-section-title">Neden <span>Bizimle Çalışmalısınız?</span></h2>
        </div>
        
        <div class="soft-features-grid">
            <div class="soft-feature-card">
                <div class="soft-feature-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h4>Uzman Ekip</h4>
                <p>Alanında uzman yazılım geliştiricileri ve tasarımcılardan oluşan ekibimiz.</p>
            </div>
            
            <div class="soft-feature-card">
                <div class="soft-feature-icon">
                    <i class="fas fa-rocket"></i>
                </div>
                <h4>Hızlı Teslimat</h4>
                <p>Projelerinizi zamanında ve kaliteli bir şekilde teslim ediyoruz.</p>
            </div>
            
            <div class="soft-feature-card">
                <div class="soft-feature-icon">
                    <i class="fas fa-headset"></i>
                </div>
                <h4>7/24 Destek</h4>
                <p>Projeleriniz için sürekli teknik destek ve bakım hizmeti.</p>
            </div>
            
            <div class="soft-feature-card">
                <div class="soft-feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h4>Güvenlik</h4>
                <p>En yüksek güvenlik standartları ile projelerinizi koruyoruz.</p>
            </div>
        </div>
    </div>
</section>

<!-- Yazılım CTA -->
<section class="soft-cta">
    <div class="container">
        <h3>Projenizi Hayata Geçirmeye Hazır mısınız?</h3>
        <p>Uzman ekibimizle iletişime geçin ve projeniz için özel teklif alın.</p>
        <a href="iletisim.php" class="soft-cta-btn">
            <i class="fas fa-paper-plane"></i> Hemen İletişime Geçin
        </a>
    </div>
</section>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>
