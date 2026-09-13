<?php
/**
 * WHMVM - İptal ve İade Politikası
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'İptal ve İade Politikası';
$pageDescription = 'WHMVM iptal ve iade koşulları hakkında bilgi.';

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

.refund-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 30px;
    max-width: 1000px;
    margin: 0 auto;
}

.refund-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 40px;
}

.refund-card.full-width {
    grid-column: span 2;
}

.refund-card h2 {
    font-size: 22px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.refund-card h2 i {
    width: 45px;
    height: 45px;
    background: var(--gradient-primary);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.refund-card p {
    color: var(--text-muted);
    line-height: 1.8;
    margin-bottom: 15px;
}

.refund-card ul {
    list-style: none;
    padding: 0;
}

.refund-card li {
    color: var(--text-muted);
    padding: 10px 0;
    padding-left: 30px;
    position: relative;
    border-bottom: 1px solid var(--border-color);
}

.refund-card li:last-child {
    border-bottom: none;
}

.refund-card li::before {
    content: '→';
    position: absolute;
    left: 0;
    color: var(--primary-light);
}

.guarantee-badge {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: 15px;
    padding: 30px;
    text-align: center;
    margin-top: 20px;
}

.guarantee-badge .days {
    font-size: 48px;
    font-weight: 800;
    color: #10b981;
}

.guarantee-badge .text {
    color: var(--text-muted);
    margin-top: 10px;
}

@media (max-width: 768px) {
    .page-hero h1 { font-size: 32px; }
    .refund-grid { grid-template-columns: 1fr; }
    .refund-card.full-width { grid-column: span 1; }
}
</style>

<section class="page-hero">
    <div class="container">
        <h1>İptal ve <span>İade Politikası</span></h1>
        <p>30 gün para iade garantisi ile güvenle satın alın.</p>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <div class="refund-grid">
            
            <div class="refund-card">
                <h2><i class="fas fa-undo"></i> Para İade Garantisi</h2>
                <p>Hosting ve VDS hizmetlerimizde 30 gün içinde koşulsuz para iade garantisi sunuyoruz.</p>
                <div class="guarantee-badge">
                    <div class="days">30</div>
                    <div class="text">Gün Para İade Garantisi</div>
                </div>
            </div>
            
            <div class="refund-card">
                <h2><i class="fas fa-clock"></i> İade Süreleri</h2>
                <ul>
                    <li><strong>Web Hosting:</strong> 30 gün içinde tam iade</li>
                    <li><strong>VDS/VPS:</strong> 30 gün içinde tam iade</li>
                    <li><strong>Dedicated Sunucu:</strong> 7 gün içinde kısmi iade</li>
                    <li><strong>Domain:</strong> İade yapılmaz</li>
                    <li><strong>SSL Sertifikası:</strong> İade yapılmaz</li>
                </ul>
            </div>
            
            <div class="refund-card full-width">
                <h2><i class="fas fa-exclamation-circle"></i> İade Koşulları</h2>
                <p>İade talebinde bulunabilmeniz için aşağıdaki koşulların sağlanması gerekmektedir:</p>
                <ul>
                    <li>Hizmet kullanım şartlarına aykırı davranış olmamış olmalı</li>
                    <li>Spam, zararlı yazılım gibi ihlaller yapılmamış olmalı</li>
                    <li>Ödeme, kredi kartı veya banka havalesi ile yapılmış olmalı</li>
                    <li>İade talebi destek bileti üzerinden yapılmalı</li>
                    <li>İade, ödeme yapılan yönteme geri yapılır</li>
                </ul>
            </div>
            
            <div class="refund-card">
                <h2><i class="fas fa-ban"></i> İade Dışı Durumlar</h2>
                <ul>
                    <li>30 günü aşan hosting/VDS hizmetleri</li>
                    <li>Domain kayıt ve transfer işlemleri</li>
                    <li>SSL sertifikaları</li>
                    <li>Kurulum ücretleri</li>
                    <li>Ek IP adresleri</li>
                    <li>Lisans ücretleri</li>
                </ul>
            </div>
            
            <div class="refund-card">
                <h2><i class="fas fa-calendar-times"></i> İptal İşlemleri</h2>
                <ul>
                    <li>İptal talepleri dönem sonunda işlenir</li>
                    <li>Otomatik yenileme öncesinde iptal edilebilir</li>
                    <li>Veriler iptal tarihinden 7 gün sonra silinir</li>
                    <li>İptal öncesi yedek almanız önerilir</li>
                </ul>
            </div>
            
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>

