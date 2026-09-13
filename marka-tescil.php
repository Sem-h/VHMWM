<?php
/**
 * WHMVM - Marka Tescil Sayfası (Verimek Benzeri)
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Marka Tescil | Markanızı Koruma Altına Alın';
$pageDescription = 'Profesyonel marka tescil hizmeti. Ücretsiz marka araştırması, Türk Patent başvurusu.';

require_once __DIR__ . '/theme/includes/header.php';
?>

<style>
    /* Hero Section - Verimek Style */
    .marka-hero {
        background: linear-gradient(135deg, #1e3a5f 0%, #0d1b2a 100%);
        padding: 100px 0 60px;
        position: relative;
        overflow: hidden;
    }

    .marka-hero::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 50%;
        height: 100%;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="80" cy="20" r="40" fill="rgba(99,102,241,0.1)"/></svg>');
        background-size: cover;
    }

    .marka-hero .container {
        position: relative;
        z-index: 1;
    }

    .marka-hero h1 {
        font-size: 42px;
        font-weight: 800;
        margin-bottom: 20px;
        color: white;
    }

    .marka-hero p {
        font-size: 16px;
        color: rgba(255, 255, 255, 0.8);
        max-width: 600px;
        line-height: 1.7;
        margin-bottom: 40px;
    }

    /* Stats */
    .hero-stats {
        display: flex;
        gap: 50px;
    }

    .stat-item {
        text-align: center;
    }

    .stat-number {
        font-size: 42px;
        font-weight: 800;
        color: #6366f1;
        display: block;
    }

    .stat-label {
        font-size: 14px;
        color: rgba(255, 255, 255, 0.7);
    }

    /* Form Section - 2 Column Layout */
    .form-section {
        padding: 80px 0;
        background: #f8fafc;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 40px;
        align-items: start;
    }

    /* Main Form Card */
    .main-form-card {
        background: white;
        border-radius: 16px;
        padding: 40px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
    }

    .main-form-card h2 {
        font-size: 24px;
        color: #0f172a;
        margin-bottom: 8px;
    }

    .main-form-card>p {
        color: #64748b;
        margin-bottom: 30px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #334155;
        font-size: 14px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 14px 16px;
        background: #f8fafc;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        color: #0f172a;
        font-size: 15px;
        transition: all 0.3s ease;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    /* Class Selection */
    .class-selection {
        background: #f8fafc;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
    }

    .class-selection h4 {
        font-size: 16px;
        color: #0f172a;
        margin-bottom: 15px;
    }

    .class-checkboxes {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
    }

    .class-checkbox {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 15px;
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 13px;
        color: #475569;
    }

    .class-checkbox:hover {
        border-color: #6366f1;
    }

    .class-checkbox input {
        width: 18px;
        height: 18px;
        accent-color: #6366f1;
    }

    .class-checkbox.selected {
        border-color: #6366f1;
        background: rgba(99, 102, 241, 0.05);
    }

    /* Summary Card */
    .summary-card {
        background: linear-gradient(135deg, #1e3a5f 0%, #0d1b2a 100%);
        border-radius: 16px;
        padding: 30px;
        color: white;
        position: sticky;
        top: 120px;
    }

    .summary-card h3 {
        font-size: 20px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        padding: 15px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        font-size: 14px;
    }

    .summary-item:last-child {
        border-bottom: none;
    }

    .summary-item .label {
        color: rgba(255, 255, 255, 0.7);
    }

    .summary-item .value {
        font-weight: 700;
        color: #6366f1;
    }

    .summary-total {
        background: rgba(99, 102, 241, 0.2);
        border-radius: 10px;
        padding: 20px;
        margin-top: 20px;
        text-align: center;
    }

    .summary-total .total-label {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 5px;
    }

    .summary-total .total-amount {
        font-size: 32px;
        font-weight: 800;
        color: white;
    }

    .summary-note {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.5);
        margin-top: 15px;
        text-align: center;
    }

    /* Popular Classes */
    .classes-section {
        padding: 80px 0;
        background: white;
    }

    .classes-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 25px;
    }

    .class-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 25px;
        transition: all 0.3s ease;
    }

    .class-card:hover {
        border-color: #6366f1;
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(99, 102, 241, 0.1);
    }

    .class-card-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #6366f1, #818cf8);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: white;
        margin-bottom: 15px;
    }

    .class-card h4 {
        font-size: 16px;
        color: #0f172a;
        margin-bottom: 8px;
    }

    .class-card p {
        color: #64748b;
        font-size: 13px;
        line-height: 1.6;
    }

    /* Pricing Table */
    .pricing-section {
        padding: 80px 0;
        background: #f8fafc;
    }

    .pricing-table {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
    }

    .pricing-table table {
        width: 100%;
        border-collapse: collapse;
    }

    .pricing-table th {
        background: linear-gradient(135deg, #1e3a5f 0%, #0d1b2a 100%);
        color: white;
        padding: 18px 20px;
        text-align: left;
        font-weight: 600;
        font-size: 14px;
    }

    .pricing-table td {
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        color: #334155;
        font-size: 14px;
    }

    .pricing-table tr:last-child td {
        border-bottom: none;
    }

    .pricing-table tr:hover td {
        background: rgba(99, 102, 241, 0.03);
    }

    /* Advantages */
    .advantages-section {
        padding: 80px 0;
        background: white;
    }

    .advantages-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 25px;
    }

    .advantage-card {
        text-align: center;
        padding: 30px 20px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    .advantage-card:hover {
        border-color: #6366f1;
        transform: translateY(-5px);
    }

    .advantage-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #6366f1, #818cf8);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        font-size: 24px;
        color: white;
    }

    .advantage-card h4 {
        font-size: 15px;
        color: #0f172a;
        margin-bottom: 8px;
    }

    .advantage-card p {
        color: #64748b;
        font-size: 13px;
    }

    /* Process Timeline */
    .process-section {
        padding: 80px 0;
        background: linear-gradient(135deg, #1e3a5f 0%, #0d1b2a 100%);
    }

    .process-section .section-header h2,
    .process-section .section-header p {
        color: white;
    }

    .process-section .section-badge {
        background: rgba(99, 102, 241, 0.2);
        color: #818cf8;
    }

    .process-timeline {
        display: flex;
        justify-content: space-between;
        position: relative;
        margin-top: 50px;
    }

    .process-timeline::before {
        content: '';
        position: absolute;
        top: 35px;
        left: 70px;
        right: 70px;
        height: 3px;
        background: linear-gradient(90deg, #6366f1, #0ea5e9);
    }

    .process-step {
        text-align: center;
        position: relative;
        flex: 1;
    }

    .process-number {
        width: 70px;
        height: 70px;
        background: linear-gradient(135deg, #6366f1, #818cf8);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        font-weight: 800;
        color: white;
        margin: 0 auto 20px;
        position: relative;
        z-index: 1;
        box-shadow: 0 10px 30px rgba(99, 102, 241, 0.4);
    }

    .process-step h4 {
        font-size: 15px;
        color: white;
        margin-bottom: 8px;
    }

    .process-step p {
        color: rgba(255, 255, 255, 0.6);
        font-size: 12px;
        max-width: 140px;
        margin: 0 auto;
    }

    /* CTA */
    .cta-section {
        padding: 80px 0;
        background: linear-gradient(135deg, #6366f1 0%, #818cf8 100%);
        text-align: center;
    }

    .cta-section h2 {
        font-size: 32px;
        font-weight: 700;
        color: white;
        margin-bottom: 15px;
    }

    .cta-section p {
        font-size: 16px;
        color: rgba(255, 255, 255, 0.9);
        margin-bottom: 30px;
    }

    .cta-buttons {
        display: flex;
        gap: 20px;
        justify-content: center;
    }

    .btn-white {
        background: white;
        color: #6366f1;
        padding: 16px 40px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s ease;
    }

    .btn-white:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }

    .btn-outline-white {
        background: transparent;
        border: 2px solid white;
        color: white;
        padding: 14px 40px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s ease;
    }

    .btn-outline-white:hover {
        background: white;
        color: #6366f1;
    }

    /* Light Theme Section Headers */
    .section-header-light .section-badge {
        background: rgba(99, 102, 241, 0.1);
        color: #6366f1;
    }

    .section-header-light h2 {
        color: #0f172a;
    }

    .section-header-light p {
        color: #64748b;
    }

    /* Responsive */
    @media (max-width: 1200px) {
        .advantages-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .class-checkboxes {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 992px) {
        .form-grid {
            grid-template-columns: 1fr;
        }

        .summary-card {
            position: static;
        }

        .classes-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .process-timeline {
            flex-direction: column;
            gap: 30px;
        }

        .process-timeline::before {
            display: none;
        }
    }

    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }

        .classes-grid {
            grid-template-columns: 1fr;
        }

        .advantages-grid {
            grid-template-columns: 1fr;
        }

        .hero-stats {
            flex-direction: column;
            gap: 20px;
        }

        .marka-hero h1 {
            font-size: 32px;
        }

        .class-checkboxes {
            grid-template-columns: 1fr;
        }

        .cta-buttons {
            flex-direction: column;
            align-items: center;
        }
    }
</style>

<!-- Hero Section -->
<section class="marka-hero">
    <div class="container">
        <h1>Marka Tescil Sorgulama</h1>
        <p>İşletmenizin tüm mal ve hizmetlerini diğer işletmelerin mal veya hizmetlerinden ayıran her türlü işaret marka
            olarak adlandırılmaktadır. Markanızı tescille koruma altına alın.</p>

        <div class="hero-stats">
            <div class="stat-item">
                <span class="stat-number">5000+</span>
                <span class="stat-label">Tescilli Marka</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">%98</span>
                <span class="stat-label">Başarı Oranı</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">7/24</span>
                <span class="stat-label">Destek</span>
            </div>
        </div>
    </div>
</section>

<!-- Form Section -->
<section class="form-section">
    <div class="container">
        <div class="form-grid">
            <!-- Main Form -->
            <div class="main-form-card">
                <h2>Ücretsiz Marka Araştırma</h2>
                <p>Bilgilerinizi ve marka sektörünüzü girin, uzmanlarımız ücretsiz araştırma yapsınlar.</p>

                <form action="contact.php" method="POST">
                    <input type="hidden" name="subject" value="marka-tescil">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Ad Soyad *</label>
                            <input type="text" name="name" placeholder="Adınız Soyadınız" required>
                        </div>
                        <div class="form-group">
                            <label>Telefon *</label>
                            <input type="tel" name="phone" placeholder="0532 XXX XX XX" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>E-posta</label>
                            <input type="email" name="email" placeholder="ornek@email.com">
                        </div>
                        <div class="form-group">
                            <label>Marka Adı *</label>
                            <input type="text" name="brand_name" placeholder="Tescil edilecek marka" required>
                        </div>
                    </div>

                    <div class="class-selection">
                        <h4>Marka Sınıfı Seçin</h4>
                        <div class="class-checkboxes">
                            <label class="class-checkbox">
                                <input type="checkbox" name="classes[]" value="9"> Sınıf 9 - Yazılım
                            </label>
                            <label class="class-checkbox">
                                <input type="checkbox" name="classes[]" value="35"> Sınıf 35 - Ticaret
                            </label>
                            <label class="class-checkbox">
                                <input type="checkbox" name="classes[]" value="38"> Sınıf 38 - Telekom
                            </label>
                            <label class="class-checkbox">
                                <input type="checkbox" name="classes[]" value="41"> Sınıf 41 - Eğitim
                            </label>
                            <label class="class-checkbox">
                                <input type="checkbox" name="classes[]" value="42"> Sınıf 42 - Bilim
                            </label>
                            <label class="class-checkbox">
                                <input type="checkbox" name="classes[]" value="43"> Sınıf 43 - Yiyecek
                            </label>
                            <label class="class-checkbox">
                                <input type="checkbox" name="classes[]" value="25"> Sınıf 25 - Giyim
                            </label>
                            <label class="class-checkbox">
                                <input type="checkbox" name="classes[]" value="3"> Sınıf 3 - Kozmetik
                            </label>
                            <label class="class-checkbox">
                                <input type="checkbox" name="classes[]" value="30"> Sınıf 30 - Gıda
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 16px;">
                        <i class="fas fa-search"></i> Ücretsiz Araştır
                    </button>
                </form>
            </div>

            <!-- Summary Card -->
            <div class="summary-card">
                <h3><i class="fas fa-file-invoice"></i> Başvuru Özeti</h3>

                <div class="summary-item">
                    <span class="label">Hizmet Bedeli</span>
                    <span class="value">₺2.500</span>
                </div>
                <div class="summary-item">
                    <span class="label">Harç Bedeli (1 Sınıf)</span>
                    <span class="value">₺1.200</span>
                </div>
                <div class="summary-item">
                    <span class="label">Ek Sınıf (Her biri)</span>
                    <span class="value">+₺600</span>
                </div>

                <div class="summary-total">
                    <div class="total-label">Toplam Tutar</div>
                    <div class="total-amount">₺3.700</div>
                </div>

                <p class="summary-note">* Fiyatlarımıza KDV dahil DEĞİLDİR.</p>
            </div>
        </div>
    </div>
</section>

<!-- Popular Classes -->
<section class="classes-section">
    <div class="container">
        <div class="section-header section-header-light">
            <span class="section-badge"><i class="fas fa-tags"></i> Sınıflar</span>
            <h2 class="section-title">Popüler Marka Sınıfları</h2>
            <p class="section-desc">Sınıf içerikleri için detaylı bilgi alabilirsiniz.</p>
        </div>

        <div class="classes-grid">
            <div class="class-card">
                <div class="class-card-icon"><i class="fas fa-laptop-code"></i></div>
                <h4>Yazılım & Elektronik</h4>
                <p>Bilgisayar yazılımları, mobil uygulamalar, elektronik cihazlar.</p>
            </div>
            <div class="class-card">
                <div class="class-card-icon"><i class="fas fa-bullhorn"></i></div>
                <h4>Ticaret & Reklam</h4>
                <p>Reklamcılık, iş yönetimi, ticari işletme yönetimi.</p>
            </div>
            <div class="class-card">
                <div class="class-card-icon"><i class="fas fa-satellite-dish"></i></div>
                <h4>Telekomünikasyon</h4>
                <p>Haberleşme hizmetleri, internet, veri iletimi.</p>
            </div>
            <div class="class-card">
                <div class="class-card-icon"><i class="fas fa-graduation-cap"></i></div>
                <h4>Eğitim & Eğlence</h4>
                <p>Eğitim hizmetleri, spor, kültürel faaliyetler.</p>
            </div>
            <div class="class-card">
                <div class="class-card-icon"><i class="fas fa-flask"></i></div>
                <h4>Bilimsel Hizmetler</h4>
                <p>Teknolojik hizmetler, araştırma, yazılım tasarımı.</p>
            </div>
            <div class="class-card">
                <div class="class-card-icon"><i class="fas fa-utensils"></i></div>
                <h4>Yiyecek & İçecek</h4>
                <p>Restoran, cafe, otel, konaklama hizmetleri.</p>
            </div>
            <div class="class-card">
                <div class="class-card-icon"><i class="fas fa-tshirt"></i></div>
                <h4>Giyim & Tekstil</h4>
                <p>Giysiler, ayakkabılar, tekstil ürünleri.</p>
            </div>
            <div class="class-card">
                <div class="class-card-icon"><i class="fas fa-spray-can"></i></div>
                <h4>Kozmetik</h4>
                <p>Parfümler, kozmetikler, temizlik maddeleri.</p>
            </div>
            <div class="class-card">
                <div class="class-card-icon"><i class="fas fa-cookie-bite"></i></div>
                <h4>Gıda Ürünleri</h4>
                <p>Kahve, çay, şeker, unlu mamüller, şekerlemeler.</p>
            </div>
        </div>
    </div>
</section>

<!-- Pricing Table -->
<section class="pricing-section">
    <div class="container">
        <div class="section-header section-header-light">
            <span class="section-badge"><i class="fas fa-lira-sign"></i> Ücretler</span>
            <h2 class="section-title">Türk Patent Resmi Ücretleri</h2>
            <p class="section-desc">Türk Patent ve Marka Kurumu tarafından belirlenen güncel ücretler</p>
        </div>

        <div class="pricing-table">
            <table>
                <thead>
                    <tr>
                        <th>İşlem Türü</th>
                        <th>Açıklama</th>
                        <th>Ücret</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Marka Başvuru Harcı</strong></td>
                        <td>1 sınıf için başvuru harcı</td>
                        <td><strong>₺1.200</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Ek Sınıf Harcı</strong></td>
                        <td>Her ek sınıf için</td>
                        <td><strong>₺600</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Tescil Harcı</strong></td>
                        <td>Tescil belgesi düzenleme</td>
                        <td><strong>₺1.500</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Yenileme Harcı</strong></td>
                        <td>10 yıllık yenileme (1 sınıf)</td>
                        <td><strong>₺2.000</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p style="text-align: center; color: #64748b; font-size: 13px; margin-top: 20px;">
            * Ücretler Türk Patent ve Marka Kurumu tarafından güncellenebilir.
        </p>
    </div>
</section>

<!-- Advantages -->
<section class="advantages-section">
    <div class="container">
        <div class="section-header section-header-light">
            <span class="section-badge"><i class="fas fa-star"></i> Avantajlar</span>
            <h2 class="section-title">Marka Tescil Avantajları</h2>
            <p class="section-desc">Markanızı tescil ettirerek kazanacağınız haklar</p>
        </div>

        <div class="advantages-grid">
            <div class="advantage-card">
                <div class="advantage-icon"><i class="fas fa-shield-alt"></i></div>
                <h4>Hukuki Koruma</h4>
                <p>Markanız yasal güvence altında</p>
            </div>
            <div class="advantage-card">
                <div class="advantage-icon"><i class="fas fa-globe"></i></div>
                <h4>.TR Alan Adı Hakkı</h4>
                <p>.com.tr alan adı tescil hakkı</p>
            </div>
            <div class="advantage-card">
                <div class="advantage-icon"><i class="fas fa-hand-holding-usd"></i></div>
                <h4>Devlet Teşvikleri</h4>
                <p>KOSGEB teşviklerinden yararlanın</p>
            </div>
            <div class="advantage-card">
                <div class="advantage-icon"><i class="fas fa-certificate"></i></div>
                <h4>TSE/CE Belgesi</h4>
                <p>Belge almanız kolaylaşır</p>
            </div>
            <div class="advantage-card">
                <div class="advantage-icon"><i class="fas fa-ban"></i></div>
                <h4>Taklit Engelleme</h4>
                <p>Taklitleri engelleyebilirsiniz</p>
            </div>
            <div class="advantage-card">
                <div class="advantage-icon"><i class="fas fa-exchange-alt"></i></div>
                <h4>Devir & Kiralama</h4>
                <p>Markanızı satabilir, kiralayabilirsiniz</p>
            </div>
            <div class="advantage-card">
                <div class="advantage-icon"><i class="fas fa-globe-americas"></i></div>
                <h4>WIPO/Madrid Hakkı</h4>
                <p>Uluslararası tescil başvurusu</p>
            </div>
            <div class="advantage-card">
                <div class="advantage-icon"><i class="fas fa-calendar-check"></i></div>
                <h4>10 Yıl Koruma</h4>
                <p>10 yıl boyunca tam koruma</p>
            </div>
        </div>
    </div>
</section>

<!-- Process Timeline -->
<section class="process-section">
    <div class="container">
        <div class="section-header">
            <span class="section-badge"><i class="fas fa-tasks"></i> Süreç</span>
            <h2 class="section-title">Marka Tescil Süreçleri</h2>
            <p class="section-desc">Türk Patent ve Marka Kurumu tarafından yayınlanan resmi süreçler</p>
        </div>

        <div class="process-timeline">
            <div class="process-step">
                <div class="process-number">1</div>
                <h4>Başvuru İşlemleri</h4>
                <p>Marka başvurunuz Türk Patent'e iletilir</p>
            </div>
            <div class="process-step">
                <div class="process-number">2</div>
                <h4>Marka İtiraz</h4>
                <p>İtiraz süreçleri yönetilir</p>
            </div>
            <div class="process-step">
                <div class="process-number">3</div>
                <h4>Feragat İşlemleri</h4>
                <p>Gerekli feragat işlemleri</p>
            </div>
            <div class="process-step">
                <div class="process-number">4</div>
                <h4>Bülten Yayını</h4>
                <p>Resmi bültende yayınlanır</p>
            </div>
            <div class="process-step">
                <div class="process-number">5</div>
                <h4>Tescil Belgesi</h4>
                <p>Belgeniz tarafınıza iletilir</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <div class="container">
        <h2>Markanızı Hemen Araştırın!</h2>
        <p>Ücretsiz marka araştırması için uzman ekibimizle iletişime geçin.</p>
        <div class="cta-buttons">
            <a href="#"
                onclick="document.querySelector('.form-section').scrollIntoView({behavior: 'smooth'}); return false;"
                class="btn-white">
                <i class="fas fa-search"></i> Ücretsiz Araştır
            </a>
            <a href="contact.php" class="btn-outline-white">
                <i class="fas fa-phone"></i> Bizi Arayın
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>