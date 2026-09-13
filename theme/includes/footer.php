    </main>
    <!-- End Main Content -->
    
    <?php
    // Footer için ayarları al (header'dan gelebilir ama güvenlik için tekrar al)
    $footerSiteName = $siteName ?? Settings::get('site_name', $siteConfig['name'] ?? 'WHMVM');
    $footerLogo = $siteLogo ?? Settings::getLogo();
    ?>
    
    <!-- Footer -->
    <footer class="main-footer">
        <!-- Footer Top -->
        <div class="footer-top">
            <div class="container">
                <div class="footer-grid">
                    <!-- Brand Column -->
                    <div class="footer-brand">
                        <a href="index.php" class="footer-logo">
                            <?php if (!empty($footerLogo)): ?>
                                <img src="/<?= htmlspecialchars($footerLogo) ?>" alt="<?= htmlspecialchars($footerSiteName) ?>" class="logo-image">
                            <?php else: ?>
                                <span class="logo-mark" aria-hidden="true">
                                    <svg viewBox="0 0 120 120" fill="none">
                                        <rect width="120" height="120" rx="27" fill="var(--primary)"/>
                                        <g stroke-width="12" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M28 26V94" stroke="#fff"/>
                                            <path d="M92 26V94" stroke="#fff"/>
                                            <path d="M28 80H92" stroke="#fff"/>
                                            <path d="M28 26 60 62 92 26" stroke="#fff" stroke-opacity=".62"/>
                                        </g>
                                    </svg>
                                </span>
                                <span class="logo-text"><em>V</em>HM</span>
                            <?php endif; ?>
                        </a>
                        <p><?= Lang::e('footer.tanitim', 'Profesyonel hosting, VDS, cloud sunucu ve domain hizmetleri ile projelerinizi güçlendirin. 7/24 teknik destek ve %99.9 uptime garantisi.') ?></p>
                        
                        <!-- Social Media -->
                        <div class="social-links">
                            <?php foreach ($socialMedia as $social): ?>
                                <a href="<?= $social['url'] ?>" title="<?= $social['label'] ?>" target="_blank">
                                    <i class="fab <?= $social['icon'] ?>"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Footer Links -->
                    <?php foreach ($footerLinks as $section): ?>
                        <div class="footer-links">
                            <h4><?= htmlspecialchars(Lang::tv('footer', $section['title'])) ?></h4>
                            <ul>
                                <?php foreach ($section['links'] as $link): ?>
                                    <li>
                                        <a href="<?= $link['url'] ?>">
                                            <i class="fas fa-chevron-right"></i>
                                            <?= htmlspecialchars(Lang::tv('footer', $link['label'])) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <div class="container">
                <div class="footer-bottom-content">
                    <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($footerSiteName) ?>. <?= Lang::e('footer.haklar', 'Tüm hakları saklıdır.') ?></p>
                    <div class="payment-methods">
                        <span>Ödeme Yöntemleri:</span>
                        <i class="fab fa-cc-visa" style="font-size: 28px; color: #1a1f71;"></i>
                        <i class="fab fa-cc-mastercard" style="font-size: 28px; color: #eb001b;"></i>
                        <i class="fab fa-cc-amex" style="font-size: 28px; color: #006fcf;"></i>
                        <i class="fas fa-university" style="font-size: 24px; color: #64748b;"></i>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Back to Top -->
    <button class="back-to-top" id="backToTop">
        <i class="fas fa-arrow-up"></i>
    </button>
    
    <!-- Main JS -->
    <script src="theme/assets/js/main.js"></script>
    
    <?php if (isset($extraJs)): ?>
        <?php foreach ($extraJs as $js): ?>
            <script src="<?= $js ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
<?php
// Bu istekte karsilasilan yeni ceviri anahtarlarini varsayilan dile yaz
if (class_exists('Lang')) {
    Lang::eksikleriKaydet();
}
?>
</body>
</html>
