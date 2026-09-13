<?php
/**
 * Müşteri Panel Footer
 */

// Footer için ayarları al
$footerSiteName = SITE_NAME;
$footerLogo = Settings::getLogo();

// Footer Linkleri
$footerLinks = [
    'services' => [
        'title' => 'Hizmetlerimiz',
        'links' => [
            ['label' => 'Web Hosting', 'url' => '../hosting.php'],
            ['label' => 'VDS Sunucu', 'url' => '../vds.php'],
            ['label' => 'Cloud Sunucu', 'url' => '../cloud.php'],
            ['label' => 'Dedicated Sunucu', 'url' => '../dedicated.php'],
            ['label' => 'Domain Kaydı', 'url' => '../domain.php'],
            ['label' => 'SSL Sertifikası', 'url' => '../ssl.php'],
        ]
    ],
    'company' => [
        'title' => 'Kurumsal',
        'links' => [
            ['label' => 'Hakkımızda', 'url' => '../about.php'],
            ['label' => 'İletişim', 'url' => '../contact.php'],
            ['label' => 'Blog', 'url' => '../blog.php'],
            ['label' => 'Kariyer', 'url' => '../career.php'],
        ]
    ],
    'support' => [
        'title' => 'Destek',
        'links' => [
            ['label' => 'Bilgi Bankası', 'url' => '../knowledgebase.php'],
            ['label' => 'Destek Talebi', 'url' => 'tickets.php'],
            ['label' => 'Sunucu Durumu', 'url' => '../status.php'],
            ['label' => 'SLA', 'url' => '../sla.php'],
        ]
    ],
    'legal' => [
        'title' => 'Yasal',
        'links' => [
            ['label' => 'Kullanım Şartları', 'url' => '../terms.php'],
            ['label' => 'Gizlilik Politikası', 'url' => '../privacy.php'],
            ['label' => 'KVKK', 'url' => '../kvkk.php'],
            ['label' => 'İptal ve İade', 'url' => '../refund.php'],
        ]
    ]
];

// Sosyal Medya
$socialMedia = [
    ['icon' => 'fa-facebook-f', 'url' => Settings::get('social_facebook', '#'), 'label' => 'Facebook'],
    ['icon' => 'fa-twitter', 'url' => Settings::get('social_twitter', '#'), 'label' => 'Twitter'],
    ['icon' => 'fa-instagram', 'url' => Settings::get('social_instagram', '#'), 'label' => 'Instagram'],
    ['icon' => 'fa-linkedin-in', 'url' => Settings::get('social_linkedin', '#'), 'label' => 'LinkedIn'],
    ['icon' => 'fa-youtube', 'url' => Settings::get('social_youtube', '#'), 'label' => 'YouTube'],
];
?>
</main>

<style>
/* Footer Styles */
.main-footer {
    background: rgba(15, 23, 42, 0.95);
    border-top: 1px solid rgba(255,255,255,0.1);
    margin-top: 50px;
}

.footer-top {
    padding: 60px 0 40px;
    max-width: 1400px;
    margin: 0 auto;
    padding-left: 30px;
    padding-right: 30px;
}

.footer-grid {
    display: grid;
    grid-template-columns: 2fr repeat(4, 1fr);
    gap: 40px;
}

.footer-brand {
    padding-right: 40px;
}

.footer-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    text-decoration: none;
    color: #fff;
}

.footer-logo .logo-image {
    max-height: 50px;
    max-width: 200px;
    object-fit: contain;
    filter: brightness(0) invert(1);
}

.footer-logo .logo-icon {
    width: 42px;
    height: 42px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.footer-logo .logo-text {
    font-size: 20px;
    font-weight: 700;
}

.footer-brand p {
    color: var(--text-muted);
    font-size: 14px;
    line-height: 1.8;
    margin-bottom: 25px;
}

.social-links {
    display: flex;
    gap: 12px;
}

.social-links a {
    width: 42px;
    height: 42px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    transition: all 0.3s ease;
    text-decoration: none;
}

.social-links a:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
    transform: translateY(-3px);
}

.footer-links h4 {
    font-size: 15px;
    font-weight: 700;
    margin-bottom: 25px;
    color: var(--text-primary);
}

.footer-links ul {
    list-style: none;
}

.footer-links ul li {
    margin-bottom: 12px;
}

.footer-links ul li a {
    color: var(--text-muted);
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.footer-links ul li a i {
    font-size: 8px;
    opacity: 0;
    transform: translateX(-5px);
    transition: all 0.3s ease;
}

.footer-links ul li a:hover {
    color: var(--primary-light);
}

.footer-links ul li a:hover i {
    opacity: 1;
    transform: translateX(0);
}

.footer-bottom {
    border-top: 1px solid rgba(255,255,255,0.1);
    padding: 25px 0;
    max-width: 1400px;
    margin: 0 auto;
    padding-left: 30px;
    padding-right: 30px;
}

.footer-bottom-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.footer-bottom p {
    color: var(--text-muted);
    font-size: 14px;
}

.payment-methods {
    display: flex;
    align-items: center;
    gap: 15px;
}

.payment-methods span {
    color: var(--text-muted);
    font-size: 13px;
}

.payment-methods i {
    opacity: 0.6;
    transition: opacity 0.3s ease;
}

.payment-methods i:hover {
    opacity: 1;
}

/* Back to Top */
.back-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border: none;
    border-radius: 10px;
    color: white;
    font-size: 18px;
    cursor: pointer;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
    z-index: 99;
    display: flex;
    align-items: center;
    justify-content: center;
}

.back-to-top.show {
    opacity: 1;
    visibility: visible;
}

.back-to-top:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
}

/* Responsive */
@media (max-width: 1200px) {
    .footer-grid {
        grid-template-columns: 2fr repeat(3, 1fr);
    }
}

@media (max-width: 992px) {
    .footer-grid {
        grid-template-columns: 1fr 1fr;
        gap: 30px;
    }
    
    .footer-brand {
        grid-column: 1 / -1;
    }
}

@media (max-width: 576px) {
    .footer-grid {
        grid-template-columns: 1fr;
    }
    
    .footer-bottom-content {
        flex-direction: column;
        text-align: center;
    }
    
    .back-to-top {
        bottom: 20px;
        right: 20px;
        width: 45px;
        height: 45px;
    }
}
</style>

<!-- Footer -->
<footer class="main-footer">
    <!-- Footer Top -->
    <div class="footer-top">
        <div class="footer-grid">
            <!-- Brand Column -->
            <div class="footer-brand">
                <a href="../index.php" class="footer-logo">
                    <?php if (!empty($footerLogo)): ?>
                        <img src="/<?= htmlspecialchars($footerLogo) ?>" alt="<?= htmlspecialchars($footerSiteName) ?>" class="logo-image">
                    <?php else: ?>
                        <div class="logo-icon">
                            <i class="fas fa-server"></i>
                        </div>
                        <span class="logo-text"><?= htmlspecialchars($footerSiteName) ?></span>
                    <?php endif; ?>
                </a>
                <p>Profesyonel hosting, VDS, cloud sunucu ve domain hizmetleri ile projelerinizi güçlendirin. 7/24 teknik destek ve %99.9 uptime garantisi.</p>
                
                <!-- Social Media -->
                <div class="social-links">
                    <?php foreach ($socialMedia as $social): ?>
                        <?php if ($social['url'] !== '#'): ?>
                            <a href="<?= htmlspecialchars($social['url']) ?>" title="<?= htmlspecialchars($social['label']) ?>" target="_blank">
                                <i class="fab <?= htmlspecialchars($social['icon']) ?>"></i>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Footer Links -->
            <?php foreach ($footerLinks as $section): ?>
                <div class="footer-links">
                    <h4><?= htmlspecialchars($section['title']) ?></h4>
                    <ul>
                        <?php foreach ($section['links'] as $link): ?>
                            <li>
                                <a href="<?= htmlspecialchars($link['url']) ?>">
                                    <i class="fas fa-chevron-right"></i>
                                    <?= htmlspecialchars($link['label']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Footer Bottom -->
    <div class="footer-bottom">
        <div class="footer-bottom-content">
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($footerSiteName) ?>. Tüm hakları saklıdır.</p>
            <div class="payment-methods">
                <span>Ödeme Yöntemleri:</span>
                <i class="fab fa-cc-visa" style="font-size: 28px; color: #1a1f71;"></i>
                <i class="fab fa-cc-mastercard" style="font-size: 28px; color: #eb001b;"></i>
                <i class="fab fa-cc-amex" style="font-size: 28px; color: #006fcf;"></i>
                <i class="fas fa-university" style="font-size: 24px; color: #64748b;"></i>
            </div>
        </div>
    </div>
</footer>

<!-- Back to Top -->
<button class="back-to-top" id="backToTop">
    <i class="fas fa-arrow-up"></i>
</button>

<script>
function toggleMobileMenu() {
    const menu = document.getElementById('navbarMenu');
    menu.classList.toggle('show');
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(e) {
    const menu = document.getElementById('navbarMenu');
    const toggle = document.querySelector('.mobile-toggle');
    
    if (!menu.contains(e.target) && !toggle.contains(e.target)) {
        menu.classList.remove('show');
    }
});

// Back to Top Button
const backToTopButton = document.getElementById('backToTop');

window.addEventListener('scroll', function() {
    if (window.pageYOffset > 300) {
        backToTopButton.classList.add('show');
    } else {
        backToTopButton.classList.remove('show');
    }
});

backToTopButton.addEventListener('click', function() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
});
</script>

</body>
</html>
