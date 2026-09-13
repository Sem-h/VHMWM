<?php
/**
 * WHMVM - SLA (Hizmet Seviyesi Anlaşması)
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Hizmet Seviyesi Anlaşması (SLA)';
$pageDescription = 'WHMVM hizmet seviyesi anlaşması, uptime garantisi ve destek taahhütlerimiz.';

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

.page-hero p {
    font-size: 20px;
    color: var(--gray-light);
    max-width: 600px;
    margin: 0 auto;
}

.content-section {
    padding: 80px 0;
    background: var(--bg-body);
}

.content-wrapper {
    max-width: 900px;
    margin: 0 auto;
}

.sla-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 30px;
}

.sla-card h2 {
    font-size: 24px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.sla-card h2 i {
    width: 50px;
    height: 50px;
    background: var(--gradient-primary);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sla-card p, .sla-card li {
    color: var(--text-muted);
    line-height: 1.8;
    margin-bottom: 15px;
}

.sla-card ul {
    list-style: none;
    padding-left: 0;
}

.sla-card ul li {
    padding-left: 30px;
    position: relative;
}

.sla-card ul li::before {
    content: '✓';
    position: absolute;
    left: 0;
    color: var(--success);
    font-weight: bold;
}

.uptime-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin: 30px 0;
}

.uptime-item {
    text-align: center;
    padding: 30px;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 15px;
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.uptime-item .percentage {
    font-size: 42px;
    font-weight: 800;
    color: var(--primary-light);
}

.uptime-item .label {
    color: var(--text-muted);
    margin-top: 10px;
}

.compensation-table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
}

.compensation-table th,
.compensation-table td {
    padding: 15px 20px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.compensation-table th {
    background: rgba(99, 102, 241, 0.1);
    font-weight: 600;
}

.compensation-table tr:hover {
    background: rgba(255,255,255,0.02);
}

@media (max-width: 768px) {
    .page-hero h1 { font-size: 32px; }
    .uptime-grid { grid-template-columns: 1fr; }
    .sla-card { padding: 25px; }
}
</style>

<section class="page-hero">
    <div class="container">
        <h1>Hizmet Seviyesi <span>Anlaşması</span></h1>
        <p>Kesintisiz hizmet ve %99.9 uptime garantisi ile yanınızdayız.</p>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <div class="content-wrapper">
            
            <div class="sla-card">
                <h2><i class="fas fa-shield-alt"></i> Uptime Garantisi</h2>
                <p>Tüm hosting ve sunucu hizmetlerimiz için yüksek erişilebilirlik garantisi sunuyoruz.</p>
                
                <div class="uptime-grid">
                    <div class="uptime-item">
                        <div class="percentage">99.9%</div>
                        <div class="label">Web Hosting</div>
                    </div>
                    <div class="uptime-item">
                        <div class="percentage">99.95%</div>
                        <div class="label">VDS / Cloud</div>
                    </div>
                    <div class="uptime-item">
                        <div class="percentage">99.99%</div>
                        <div class="label">Dedicated</div>
                    </div>
                </div>
                
                <p>Uptime hesaplaması, planlı bakım süreleri hariç tutularak aylık bazda yapılır.</p>
            </div>
            
            <div class="sla-card">
                <h2><i class="fas fa-coins"></i> Tazminat Politikası</h2>
                <p>Garantili uptime oranının altına düşülmesi durumunda aşağıdaki tazminat oranları uygulanır:</p>
                
                <table class="compensation-table">
                    <thead>
                        <tr>
                            <th>Uptime Oranı</th>
                            <th>Kredi Oranı</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>%99.0 - %99.9</td>
                            <td>%10 kredi</td>
                        </tr>
                        <tr>
                            <td>%98.0 - %99.0</td>
                            <td>%25 kredi</td>
                        </tr>
                        <tr>
                            <td>%95.0 - %98.0</td>
                            <td>%50 kredi</td>
                        </tr>
                        <tr>
                            <td>%95.0 altı</td>
                            <td>%100 kredi</td>
                        </tr>
                    </tbody>
                </table>
                
                <p><small>* Krediler bir sonraki fatura döneminde uygulanır ve nakit olarak iade edilmez.</small></p>
            </div>
            
            <div class="sla-card">
                <h2><i class="fas fa-headset"></i> Destek Yanıt Süreleri</h2>
                <ul>
                    <li><strong>Kritik (Sistem Çökmesi):</strong> 15 dakika içinde ilk yanıt</li>
                    <li><strong>Yüksek (Performans Sorunu):</strong> 1 saat içinde ilk yanıt</li>
                    <li><strong>Normal (Genel Sorular):</strong> 4 saat içinde ilk yanıt</li>
                    <li><strong>Düşük (Bilgi Talebi):</strong> 24 saat içinde ilk yanıt</li>
                </ul>
            </div>
            
            <div class="sla-card">
                <h2><i class="fas fa-exclamation-triangle"></i> Kapsam Dışı Durumlar</h2>
                <p>Aşağıdaki durumlar SLA kapsamı dışındadır:</p>
                <ul>
                    <li>Planlı bakım ve güncelleme çalışmaları</li>
                    <li>DDoS saldırıları ve güvenlik olayları</li>
                    <li>Müşteri kaynaklı hatalar ve yanlış yapılandırmalar</li>
                    <li>Üçüncü taraf hizmet sağlayıcılarından kaynaklanan kesintiler</li>
                    <li>Doğal afetler ve mücbir sebep halleri</li>
                </ul>
            </div>
            
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>

