<?php
/**
 * WHMVM - Referanslar Sayfası
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

// Sayfa değişkenleri
$pageTitle = 'Referanslar';
$pageDescription = 'Bize güvenen markalar ve müşterilerimiz. Veri merkezi, bulut barındırma, yazılım ve hosting hizmetlerimizi tercih eden değerli referanslarımız.';

// Veritabanından referansları çek
try {
    $references = Database::fetchAll("SELECT * FROM `references` WHERE is_active = 1 ORDER BY sort_order ASC, id DESC");
} catch (Exception $e) {
    $references = [];
}

// Kategorileri çek
try {
    $categories = Database::fetchAll("SELECT DISTINCT category FROM `references` WHERE is_active = 1 AND category IS NOT NULL AND category != '' ORDER BY category ASC");
} catch (Exception $e) {
    $categories = [];
}

// İstatistikler
$totalReferences = count($references);

// Header dahil et
require_once __DIR__ . '/theme/includes/header.php';
?>

<style>
/* ===== REFERANSLAR PREMIUM PAGE ===== */
:root {
    --ref-primary: #2563eb;
    --ref-secondary: #10b981;
    --ref-accent: #8b5cf6;
    --ref-dark: #0f172a;
    --ref-gradient: linear-gradient(135deg, #2563eb 0%, #8b5cf6 100%);
}

/* ===== HERO SECTION ===== */
.ref-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
    padding: 80px 20px 100px;
    position: relative;
    overflow: hidden;
    text-align: center;
}

.ref-hero::before {
    content: '';
    position: absolute;
    width: 800px;
    height: 800px;
    background: radial-gradient(circle, rgba(37,99,235,0.15) 0%, transparent 60%);
    top: -400px;
    left: 50%;
    transform: translateX(-50%);
    animation: pulse 10s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: translateX(-50%) scale(1); opacity: 0.5; }
    50% { transform: translateX(-50%) scale(1.1); opacity: 0.8; }
}

.ref-hero .container {
    position: relative;
    z-index: 10;
    max-width: 900px;
    margin: 0 auto;
}

.ref-hero .breadcrumb-mini {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-bottom: 25px;
    font-size: 0.9rem;
}

.ref-hero .breadcrumb-mini a {
    color: rgba(255,255,255,0.5);
    text-decoration: none;
}

.ref-hero .breadcrumb-mini span {
    color: var(--ref-primary);
}

.ref-hero h1 {
    font-size: 3.5rem;
    font-weight: 900;
    color: #fff;
    margin-bottom: 20px;
    line-height: 1.2;
}

.ref-hero h1 em {
    font-style: normal;
    background: var(--ref-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.ref-hero p {
    font-size: 1.2rem;
    color: rgba(255,255,255,0.7);
    max-width: 700px;
    margin: 0 auto 40px;
    line-height: 1.7;
}

/* Stats Row */
.hero-stats {
    display: flex;
    justify-content: center;
    gap: 60px;
    flex-wrap: wrap;
}

.hero-stat {
    text-align: center;
}

.hero-stat .number {
    font-size: 3.5rem;
    font-weight: 900;
    background: var(--ref-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1;
    display: block;
}

.hero-stat .label {
    font-size: 1rem;
    color: rgba(255,255,255,0.6);
    margin-top: 8px;
}

/* ===== FILTER SECTION ===== */
.ref-filters {
    background: #fff;
    padding: 0;
    position: sticky;
    top: 70px;
    z-index: 100;
    border-bottom: 1px solid #e2e8f0;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

.filter-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    display: flex;
    justify-content: center;
    gap: 15px;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 14px 30px;
    background: #f1f5f9;
    border: 2px solid transparent;
    border-radius: 50px;
    color: var(--ref-dark);
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.filter-btn:hover {
    background: #e2e8f0;
    transform: translateY(-2px);
}

.filter-btn.active {
    background: var(--ref-gradient);
    color: #fff;
    border-color: transparent;
    box-shadow: 0 10px 30px rgba(37,99,235,0.3);
}

/* ===== REFERENCES GRID ===== */
.ref-grid-section {
    background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
    padding: 80px 0;
    min-height: 60vh;
}

.ref-grid-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
}

.ref-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 25px;
}

/* Reference Card */
.ref-card {
    background: #fff;
    border-radius: 16px;
    padding: 25px;
    text-align: center;
    border: 2px solid #e2e8f0;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 120px;
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.ref-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--ref-gradient);
    opacity: 0;
    transition: opacity 0.3s;
}

.ref-card:hover {
    border-color: var(--ref-primary);
    transform: translateY(-10px) scale(1.02);
    box-shadow: 0 25px 50px rgba(37,99,235,0.15);
}

.ref-card:hover::before {
    opacity: 1;
}

.ref-card .ref-logo-wrap {
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ref-card .ref-logo-wrap img {
    max-width: 140px;
    max-height: 60px;
    width: auto;
    height: auto;
    object-fit: contain;
    transition: all 0.4s;
}

.ref-card:hover .ref-logo-wrap img {
    transform: scale(1.1);
}

/* Modal Styles */
.ref-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.9);
    backdrop-filter: blur(10px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.ref-modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.ref-modal {
    background: #fff;
    border-radius: 24px;
    max-width: 500px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    transform: scale(0.9) translateY(20px);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 25px 80px rgba(0,0,0,0.3);
}

.ref-modal-overlay.active .ref-modal {
    transform: scale(1) translateY(0);
}

.ref-modal-header {
    padding: 30px 30px 0;
    display: flex;
    justify-content: flex-end;
}

.ref-modal-close {
    width: 40px;
    height: 40px;
    border: none;
    background: #f1f5f9;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: #64748b;
    transition: all 0.3s;
}

.ref-modal-close:hover {
    background: #fee2e2;
    color: #ef4444;
    transform: rotate(90deg);
}

.ref-modal-body {
    padding: 20px 40px 40px;
    text-align: center;
}

.ref-modal-logo {
    width: 180px;
    height: 100px;
    margin: 0 auto 25px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    border-radius: 16px;
    padding: 20px;
}

.ref-modal-logo img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.ref-modal-logo i {
    font-size: 48px;
    color: #94a3b8;
}

.ref-modal-name {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--ref-dark);
    margin-bottom: 8px;
}

.ref-modal-category {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, #2563eb 0%, #8b5cf6 100%);
    color: #fff;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 20px;
}

.ref-modal-description {
    color: #64748b;
    font-size: 1rem;
    line-height: 1.7;
    margin-bottom: 30px;
}

.ref-modal-website {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 30px;
    background: var(--ref-gradient);
    color: #fff;
    text-decoration: none;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1rem;
    transition: all 0.3s;
    box-shadow: 0 10px 30px rgba(37,99,235,0.3);
}

.ref-modal-website:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 40px rgba(37,99,235,0.4);
    color: #fff;
}

.ref-modal-website i {
    font-size: 1.1rem;
}

/* Dark logo cards */
.ref-card.dark-logo {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-color: #334155;
}

.ref-card.dark-logo:hover {
    border-color: var(--ref-primary);
}

/* Empty State */
.empty-refs {
    text-align: center;
    padding: 80px 40px;
    color: #64748b;
}

.empty-refs i {
    font-size: 64px;
    margin-bottom: 20px;
    color: #cbd5e1;
}

.empty-refs h3 {
    font-size: 1.5rem;
    margin-bottom: 10px;
    color: var(--ref-dark);
}

/* ===== CTA SECTION ===== */
.ref-cta {
    background: linear-gradient(135deg, #0f172a, #1e293b);
    padding: 100px 0;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.ref-cta::before {
    content: '';
    position: absolute;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(37,99,235,0.15) 0%, transparent 60%);
    top: -300px;
    right: -200px;
}

.ref-cta .container {
    position: relative;
    z-index: 10;
    max-width: 800px;
    margin: 0 auto;
}

.ref-cta h2 {
    font-size: 2.8rem;
    font-weight: 900;
    color: #fff;
    margin-bottom: 20px;
}

.ref-cta h2 span {
    background: var(--ref-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.ref-cta p {
    font-size: 1.15rem;
    color: rgba(255,255,255,0.7);
    margin-bottom: 40px;
    line-height: 1.7;
}

.cta-buttons {
    display: flex;
    justify-content: center;
    gap: 20px;
    flex-wrap: wrap;
}

.btn-cta-primary {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 18px 40px;
    background: var(--ref-gradient);
    border: none;
    border-radius: 14px;
    color: #fff;
    font-size: 1.05rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s;
    box-shadow: 0 15px 40px rgba(37,99,235,0.3);
}

.btn-cta-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 20px 50px rgba(37,99,235,0.4);
    color: #fff;
}

.btn-cta-outline {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 18px 40px;
    background: rgba(255,255,255,0.05);
    border: 2px solid rgba(255,255,255,0.2);
    border-radius: 14px;
    color: #fff;
    font-size: 1.05rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s;
}

.btn-cta-outline:hover {
    background: rgba(255,255,255,0.1);
    border-color: #fff;
    color: #fff;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1200px) {
    .ref-grid { grid-template-columns: repeat(4, 1fr); }
}

@media (max-width: 992px) {
    .ref-grid { grid-template-columns: repeat(3, 1fr); }
    .hero-stats { gap: 40px; }
    .hero-stat .number { font-size: 2.8rem; }
}

@media (max-width: 768px) {
    .ref-hero { padding: 60px 20px 80px; }
    .ref-hero h1 { font-size: 2.5rem; }
    .ref-hero p { font-size: 1rem; }
    .hero-stats { gap: 30px; }
    .hero-stat .number { font-size: 2.2rem; }
    .filter-container { gap: 10px; }
    .filter-btn { padding: 12px 20px; font-size: 0.85rem; }
    .ref-grid { grid-template-columns: repeat(2, 1fr); gap: 15px; }
    .ref-card { padding: 20px 15px; min-height: 140px; }
    .ref-cta h2 { font-size: 2rem; }
    .cta-buttons { flex-direction: column; align-items: center; }
}

@media (max-width: 480px) {
    .ref-grid { grid-template-columns: 1fr 1fr; }
}

/* Animation */
.ref-card {
    animation: fadeInUp 0.5s ease forwards;
    opacity: 0;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.ref-card:nth-child(1) { animation-delay: 0.05s; }
.ref-card:nth-child(2) { animation-delay: 0.1s; }
.ref-card:nth-child(3) { animation-delay: 0.15s; }
.ref-card:nth-child(4) { animation-delay: 0.2s; }
.ref-card:nth-child(5) { animation-delay: 0.25s; }
.ref-card:nth-child(6) { animation-delay: 0.3s; }
.ref-card:nth-child(7) { animation-delay: 0.35s; }
.ref-card:nth-child(8) { animation-delay: 0.4s; }
.ref-card:nth-child(9) { animation-delay: 0.45s; }
.ref-card:nth-child(10) { animation-delay: 0.5s; }

.hidden { display: none !important; }
</style>

<!-- HERO SECTION -->
<section class="ref-hero">
    <div class="container">
        <div class="breadcrumb-mini">
            <a href="index.php">Anasayfa</a>
            <span>/</span>
            <span>Referanslar</span>
        </div>
        <h1>Bize Güvenen <em>Markalar</em></h1>
        <p>Veri merkezi, bulut barındırma, yazılım ve hosting hizmetlerimizi tercih eden değerli müşterilerimiz. Onların güveni, bizim en büyük referansımız.</p>
        
        <div class="hero-stats">
            <div class="hero-stat">
                <span class="number"><?= $totalReferences > 0 ? $totalReferences + 500 : '500' ?>+</span>
                <span class="label">Mutlu Müşteri</span>
            </div>
            <div class="hero-stat">
                <span class="number">8+</span>
                <span class="label">Yıllık Deneyim</span>
            </div>
            <div class="hero-stat">
                <span class="number">%99.9</span>
                <span class="label">Müşteri Memnuniyeti</span>
            </div>
        </div>
    </div>
</section>

<!-- FILTER SECTION -->
<section class="ref-filters">
    <div class="filter-container">
        <button class="filter-btn active" onclick="filterRefs('all')">
            <i class="fas fa-th-large"></i> Tümü
        </button>
        <?php if (!empty($categories)): ?>
            <?php foreach ($categories as $cat): ?>
                <button class="filter-btn" onclick="filterRefs('<?= htmlspecialchars(strtolower(str_replace(' ', '-', $cat['category']))) ?>')">
                    <i class="fas fa-tag"></i> <?= htmlspecialchars($cat['category']) ?>
                </button>
            <?php endforeach; ?>
        <?php else: ?>
            <button class="filter-btn" onclick="filterRefs('veri-merkezi')"><i class="fas fa-server"></i> Veri Merkezi</button>
            <button class="filter-btn" onclick="filterRefs('bulut')"><i class="fas fa-cloud"></i> Bulut & Barındırma</button>
            <button class="filter-btn" onclick="filterRefs('yazilim')"><i class="fas fa-code"></i> Yazılım</button>
        <?php endif; ?>
    </div>
</section>

<!-- REFERENCES GRID -->
<section class="ref-grid-section">
    <div class="ref-grid-container">
        <?php if (!empty($references)): ?>
            <div class="ref-grid">
                <?php foreach ($references as $ref): ?>
                    <div class="ref-card <?= !empty($ref['dark_logo']) ? 'dark-logo' : '' ?>" 
                         data-category="<?= htmlspecialchars(strtolower(str_replace(' ', '-', $ref['category'] ?? ''))) ?>"
                         data-name="<?= htmlspecialchars($ref['name']) ?>"
                         data-logo="<?= htmlspecialchars($ref['logo'] ?? '') ?>"
                         data-website="<?= htmlspecialchars($ref['website'] ?? '') ?>"
                         data-description="<?= htmlspecialchars($ref['description'] ?? '') ?>"
                         onclick="openRefModal(this)">
                        <div class="ref-logo-wrap">
                            <?php if (!empty($ref['logo'])): ?>
                                <img src="<?= htmlspecialchars($ref['logo']) ?>" alt="<?= htmlspecialchars($ref['name']) ?>" loading="lazy">
                            <?php else: ?>
                                <i class="fas fa-building" style="font-size: 2.5rem; color: #94a3b8;"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-refs">
                <i class="fas fa-building"></i>
                <h3>Henüz Referans Eklenmemiş</h3>
                <p>Referanslar yakında burada görünecek.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- REFERENCE DETAIL MODAL -->
<div class="ref-modal-overlay" id="refModal">
    <div class="ref-modal">
        <div class="ref-modal-header">
            <button class="ref-modal-close" onclick="closeRefModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="ref-modal-body">
            <div class="ref-modal-logo" id="modalLogo"></div>
            <h3 class="ref-modal-name" id="modalName"></h3>
            <span class="ref-modal-category" id="modalCategory">
                <i class="fas fa-tag"></i>
                <span id="modalCategoryText"></span>
            </span>
            <p class="ref-modal-description" id="modalDescription"></p>
            <a href="#" class="ref-modal-website" id="modalWebsite" target="_blank" style="display: none;">
                <i class="fas fa-external-link-alt"></i>
                Web Sitesini Ziyaret Et
            </a>
        </div>
    </div>
</div>

<!-- CTA SECTION -->
<section class="ref-cta">
    <div class="container">
        <h2>Siz de <span>Aramıza Katılın</span></h2>
        <p>Yüzlerce markanın güvendiği altyapımız ile tanışın. Profesyonel altyapı, kesintisiz hizmet ve 7/24 destek ile işinizi büyütün.</p>
        
        <div class="cta-buttons">
            <a href="contact.php" class="btn-cta-primary"><i class="fas fa-phone"></i> Bizi Arayın</a>
            <a href="hosting.php" class="btn-cta-outline"><i class="fas fa-server"></i> Hizmetlerimiz</a>
        </div>
    </div>
</section>

<script>
// Filtre fonksiyonu
function filterRefs(category) {
    // Update active button
    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
    event.target.closest('.filter-btn').classList.add('active');
    
    // Filter cards
    document.querySelectorAll('.ref-card').forEach(card => {
        const cardCategory = card.dataset.category || '';
        
        if (category === 'all') {
            card.classList.remove('hidden');
            card.style.animation = 'none';
            card.offsetHeight;
            card.style.animation = null;
        } else {
            if (cardCategory === category) {
                card.classList.remove('hidden');
                card.style.animation = 'none';
                card.offsetHeight;
                card.style.animation = null;
            } else {
                card.classList.add('hidden');
            }
        }
    });
}

// Modal aç
function openRefModal(card) {
    const modal = document.getElementById('refModal');
    const name = card.dataset.name || 'Referans';
    const logo = card.dataset.logo || '';
    const category = card.dataset.category || '';
    const website = card.dataset.website || '';
    const description = card.dataset.description || 'Bu referans hakkında henüz detaylı bilgi eklenmemiş.';
    
    // Modal içeriğini doldur
    document.getElementById('modalName').textContent = name;
    document.getElementById('modalCategoryText').textContent = category.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) || 'Genel';
    document.getElementById('modalDescription').textContent = description;
    
    // Logo
    const logoContainer = document.getElementById('modalLogo');
    if (logo) {
        logoContainer.innerHTML = `<img src="${logo}" alt="${name}">`;
    } else {
        logoContainer.innerHTML = `<i class="fas fa-building"></i>`;
    }
    
    // Website butonu
    const websiteBtn = document.getElementById('modalWebsite');
    if (website) {
        websiteBtn.href = website;
        websiteBtn.style.display = 'inline-flex';
    } else {
        websiteBtn.style.display = 'none';
    }
    
    // Modalı göster
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Modal kapat
function closeRefModal() {
    const modal = document.getElementById('refModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

// Overlay'e tıklayınca kapat
document.getElementById('refModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRefModal();
    }
});

// ESC tuşu ile kapat
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRefModal();
    }
});
</script>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>

