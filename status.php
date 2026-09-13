<?php
/**
 * WHMVM - Sunucu Durumu
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Sunucu Durumu';
$pageDescription = 'WHMVM altyapı ve sunucu durumu bilgileri.';

require_once __DIR__ . '/theme/includes/header.php';

// Demo sunucu durumları
$servers = [
    ['name' => 'Web Sunucu 1', 'location' => 'İstanbul', 'status' => 'online', 'uptime' => '99.99%', 'load' => 23],
    ['name' => 'Web Sunucu 2', 'location' => 'İstanbul', 'status' => 'online', 'uptime' => '99.98%', 'load' => 45],
    ['name' => 'Database Sunucu', 'location' => 'İstanbul', 'status' => 'online', 'uptime' => '99.99%', 'load' => 31],
    ['name' => 'Mail Sunucu', 'location' => 'İstanbul', 'status' => 'online', 'uptime' => '99.97%', 'load' => 12],
    ['name' => 'DNS Sunucu 1', 'location' => 'İstanbul', 'status' => 'online', 'uptime' => '100%', 'load' => 5],
    ['name' => 'DNS Sunucu 2', 'location' => 'Ankara', 'status' => 'online', 'uptime' => '100%', 'load' => 8],
    ['name' => 'Yedekleme Sunucu', 'location' => 'İstanbul', 'status' => 'online', 'uptime' => '99.95%', 'load' => 67],
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
    background: radial-gradient(ellipse at 50% 50%, rgba(16, 185, 129, 0.15) 0%, transparent 50%);
}

.page-hero .container { position: relative; z-index: 1; }

.page-hero h1 {
    font-size: 48px;
    font-weight: 800;
    margin-bottom: 20px;
}

.page-hero h1 span {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.overall-status {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: rgba(16, 185, 129, 0.2);
    border: 1px solid rgba(16, 185, 129, 0.3);
    padding: 15px 30px;
    border-radius: 50px;
    margin-top: 20px;
}

.status-dot {
    width: 12px;
    height: 12px;
    background: #10b981;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.content-section { padding: 80px 0; }

.status-grid {
    display: grid;
    gap: 20px;
    max-width: 900px;
    margin: 0 auto;
}

.server-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    padding: 25px 30px;
    display: grid;
    grid-template-columns: 1fr auto auto auto;
    align-items: center;
    gap: 30px;
}

.server-info h3 {
    font-size: 18px;
    margin-bottom: 5px;
}

.server-info span {
    color: var(--text-muted);
    font-size: 14px;
}

.server-status {
    display: flex;
    align-items: center;
    gap: 8px;
}

.server-status.online {
    color: #10b981;
}

.server-status.offline {
    color: #ef4444;
}

.server-status.maintenance {
    color: #f59e0b;
}

.server-uptime {
    text-align: center;
}

.server-uptime .value {
    font-size: 20px;
    font-weight: 700;
    color: var(--primary-light);
}

.server-uptime .label {
    font-size: 12px;
    color: var(--text-muted);
}

.server-load {
    width: 100px;
}

.load-bar {
    height: 8px;
    background: rgba(255,255,255,0.1);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 5px;
}

.load-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s;
}

.load-fill.low { background: #10b981; }
.load-fill.medium { background: #f59e0b; }
.load-fill.high { background: #ef4444; }

.load-text {
    font-size: 12px;
    color: var(--text-muted);
    text-align: center;
}

.incidents-section {
    max-width: 900px;
    margin: 60px auto 0;
}

.incidents-section h2 {
    font-size: 24px;
    margin-bottom: 25px;
}

.no-incidents {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: 15px;
    padding: 40px;
    text-align: center;
    color: #10b981;
}

.no-incidents i {
    font-size: 48px;
    margin-bottom: 15px;
}

@media (max-width: 768px) {
    .page-hero h1 { font-size: 32px; }
    .server-card {
        grid-template-columns: 1fr;
        text-align: center;
    }
}
</style>

<section class="page-hero">
    <div class="container">
        <h1>Sunucu <span>Durumu</span></h1>
        <p>Tüm sistemlerimizin anlık durumunu görüntüleyin.</p>
        <div class="overall-status">
            <div class="status-dot"></div>
            <span>Tüm Sistemler Çalışıyor</span>
        </div>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <div class="status-grid">
            <?php foreach ($servers as $server): ?>
                <div class="server-card">
                    <div class="server-info">
                        <h3><?= htmlspecialchars($server['name']) ?></h3>
                        <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($server['location']) ?></span>
                    </div>
                    <div class="server-status <?= $server['status'] ?>">
                        <i class="fas fa-circle"></i>
                        <?= $server['status'] === 'online' ? 'Çalışıyor' : ($server['status'] === 'maintenance' ? 'Bakımda' : 'Çevrimdışı') ?>
                    </div>
                    <div class="server-uptime">
                        <div class="value"><?= $server['uptime'] ?></div>
                        <div class="label">Uptime</div>
                    </div>
                    <div class="server-load">
                        <?php
                        $loadClass = $server['load'] < 50 ? 'low' : ($server['load'] < 80 ? 'medium' : 'high');
                        ?>
                        <div class="load-bar">
                            <div class="load-fill <?= $loadClass ?>" style="width: <?= $server['load'] ?>%"></div>
                        </div>
                        <div class="load-text">Yük: <?= $server['load'] ?>%</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="incidents-section">
            <h2>Son Olaylar</h2>
            <div class="no-incidents">
                <i class="fas fa-check-circle"></i>
                <p>Son 90 gün içinde herhangi bir olay kaydı bulunmuyor.</p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>

