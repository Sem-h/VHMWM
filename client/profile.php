<?php
/**
 * WHMVM - Profilim
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';

session_name(SESSION_NAME);
session_start();

$pageTitle = 'Profilim';
$currentPage = 'profile';
$clientId = (int) ($_SESSION['client_id'] ?? 0);

include 'includes/header.php';

$message = '';
$error = '';

// Müşteri bilgilerini çek
try {
    $client = Database::fetch("SELECT * FROM clients WHERE id = ?", [$clientId]);
} catch (Exception $e) {
    $client = [];
}

// Profil güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $accountType = $_POST['account_type'] ?? 'individual';
    $companyName = trim($_POST['company_name'] ?? '');
    $taxId = trim($_POST['tax_id'] ?? '');
    $taxOffice = trim($_POST['tax_office'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $postcode = trim($_POST['postcode'] ?? '');
    $country = $_POST['country'] ?? 'TR';

    if (empty($firstName) || empty($lastName)) {
        $error = 'Ad ve soyad zorunludur.';
    } else {
        try {
            // Eğer bireysel seçildiyse kurumsal alanları temizle
            if ($accountType === 'individual') {
                $companyName = '';
                $taxId = '';
                $taxOffice = '';
            }

            Database::query("
                UPDATE clients SET 
                    first_name = ?, last_name = ?, account_type = ?,
                    company_name = ?, tax_id = ?, tax_office = ?,
                    phone = ?, address = ?, city = ?, state = ?, postcode = ?, country = ?,
                    updated_at = NOW()
                WHERE id = ?
            ", [$firstName, $lastName, $accountType, $companyName, $taxId, $taxOffice, $phone, $address, $city, $state, $postcode, $country, $clientId]);

            $_SESSION['client_name'] = $firstName . ' ' . $lastName;
            $message = 'Profiliniz başarıyla güncellendi.';

            $client = Database::fetch("SELECT * FROM clients WHERE id = ?", [$clientId]);
        } catch (Exception $e) {
            $error = 'Güncelleme sırasında bir hata oluştu: ' . $e->getMessage();
        }
    }
}
?>

<style>
    /* Page Specific Styles */
    .profile-header {
        background: linear-gradient(135deg, var(--primary) 0%, #4b91fa 100%);
        border-radius: 16px;
        padding: 30px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        gap: 24px;
    }

    .profile-avatar {
        width: 80px;
        height: 80px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        font-weight: 700;
    }

    .profile-info h1 {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .profile-info p {
        opacity: 0.8;
        font-size: 15px;
    }

    .profile-badges {
        display: flex;
        gap: 10px;
        margin-top: 10px;
    }

    .profile-badge {
        padding: 5px 12px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    /* Form Card */
    .form-card {
        background: var(--card-bg);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        margin-bottom: 24px;
    }

    .form-card-header {
        padding: 20px 24px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-card-header h3 {
        font-size: 16px;
        font-weight: 600;
        margin: 0;
    }

    .form-card-header i {
        color: var(--primary-light);
    }

    .form-card-body {
        padding: 24px;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .form-row-3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 8px;
        color: var(--text-secondary);
    }

    .form-group small {
        color: var(--text-muted);
        font-weight: 400;
    }

    .form-control {
        width: 100%;
        padding: 12px 16px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        color: #fff;
        font-size: 14px;
        transition: all 0.2s;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(36, 116, 245, 0.2);
    }

    .form-control::placeholder {
        color: var(--text-muted);
    }

    select.form-control option {
        background: #1e293b;
        color: #fff;
    }

    textarea.form-control {
        resize: vertical;
        min-height: 100px;
    }

    .btn-save {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 14px 28px;
        background: linear-gradient(135deg, var(--success), #059669);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
    }

    /* Responsive */
    @media (max-width: 768px) {

        .form-row,
        .form-row-3 {
            grid-template-columns: 1fr;
        }

        .profile-header {
            flex-direction: column;
            text-align: center;
        }
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <h1><i class="fas fa-user"></i> Profilim</h1>
    <p>Kişisel bilgilerinizi güncelleyin</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Profile Header -->
<div class="profile-header">
    <div class="profile-avatar">
        <?= strtoupper(substr($client['first_name'] ?? 'M', 0, 1) . substr($client['last_name'] ?? '', 0, 1)) ?>
    </div>
    <div class="profile-info">
        <h1><?= htmlspecialchars(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')) ?></h1>
        <p><?= htmlspecialchars($client['email'] ?? '') ?></p>
        <div class="profile-badges">
            <span class="profile-badge">#<?= $clientId ?></span>
            <span
                class="profile-badge type-badge"><?= ($client['account_type'] ?? 'individual') === 'corporate' ? '🏢 Kurumsal' : '👤 Bireysel' ?></span>
            <span class="profile-badge"><?= $client['is_active'] ? '✅ Aktif' : '❌ Pasif' ?></span>
            <span class="profile-badge">📅 <?= date('d.m.Y', strtotime($client['created_at'] ?? 'now')) ?></span>
        </div>
    </div>
</div>

<form method="POST">
    <!-- Kişisel Bilgiler -->
    <div class="form-card">
        <div class="form-card-header">
            <i class="fas fa-user"></i>
            <h3>Kişisel Bilgiler</h3>
        </div>
        <div class="form-card-body">
            <div class="form-row">
                <div class="form-group">
                    <label>Ad *</label>
                    <input type="text" name="first_name" class="form-control" required
                        value="<?= htmlspecialchars($client['first_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Soyad *</label>
                    <input type="text" name="last_name" class="form-control" required
                        value="<?= htmlspecialchars($client['last_name'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>E-posta <small>(Değiştirilemez)</small></label>
                    <input type="email" class="form-control" disabled
                        value="<?= htmlspecialchars($client['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Telefon</label>
                    <input type="tel" name="phone" class="form-control"
                        value="<?= htmlspecialchars($client['phone'] ?? '') ?>" placeholder="+90 555 123 4567">
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 25px;">
                <label>Hesap Türü</label>
                <div style="display: flex; gap: 20px; margin-top: 10px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: normal;">
                        <input type="radio" name="account_type" value="individual" <?= ($client['account_type'] ?? 'individual') === 'individual' ? 'checked' : '' ?>>
                        Bireysel
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: normal;">
                        <input type="radio" name="account_type" value="corporate" <?= ($client['account_type'] ?? 'individual') === 'corporate' ? 'checked' : '' ?>>
                        Kurumsal
                    </label>
                </div>
            </div>

            <div id="corporate-fields"
                style="display: none; background: rgba(255,255,255,0.03); padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.05);">
                <div class="form-group">
                    <label>Şirket Adı (Ünvan) *</label>
                    <input type="text" name="company_name" class="form-control"
                        value="<?= htmlspecialchars($client['company_name'] ?? '') ?>" placeholder="Şirket Ltd. Şti.">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Vergi Dairesi *</label>
                        <input type="text" name="tax_office" class="form-control"
                            value="<?= htmlspecialchars($client['tax_office'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Vergi No *</label>
                        <input type="text" name="tax_id" class="form-control"
                            value="<?= htmlspecialchars($client['tax_id'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Ad *</label>
                    <input type="text" name="first_name" class="form-control" required
                        value="<?= htmlspecialchars($client['first_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Soyad *</label>
                    <input type="text" name="last_name" class="form-control" required
                        value="<?= htmlspecialchars($client['last_name'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Adres Bilgileri -->
    <div class="form-card">
        <div class="form-card-header">
            <i class="fas fa-map-marker-alt"></i>
            <h3>Adres Bilgileri</h3>
        </div>
        <div class="form-card-body">
            <div class="form-group">
                <label>Adres</label>
                <textarea name="address" class="form-control" rows="2"
                    placeholder="Sokak, Mahalle, Bina No"><?= htmlspecialchars($client['address'] ?? '') ?></textarea>
            </div>
            <div class="form-row-3">
                <div class="form-group">
                    <label>Şehir</label>
                    <input type="text" name="city" class="form-control"
                        value="<?= htmlspecialchars($client['city'] ?? '') ?>" placeholder="İstanbul">
                </div>
                <div class="form-group">
                    <label>İlçe</label>
                    <input type="text" name="state" class="form-control"
                        value="<?= htmlspecialchars($client['state'] ?? '') ?>" placeholder="Kadıköy">
                </div>
                <div class="form-group">
                    <label>Posta Kodu</label>
                    <input type="text" name="postcode" class="form-control"
                        value="<?= htmlspecialchars($client['postcode'] ?? '') ?>" placeholder="34000">
                </div>
            </div>
            <div class="form-group">
                <label>Ülke</label>
                <select name="country" class="form-control">
                    <option value="TR" <?= ($client['country'] ?? '') === 'TR' ? 'selected' : '' ?>>🇹🇷 Türkiye</option>
                    <option value="US" <?= ($client['country'] ?? '') === 'US' ? 'selected' : '' ?>>🇺🇸 Amerika Birleşik
                        Devletleri</option>
                    <option value="DE" <?= ($client['country'] ?? '') === 'DE' ? 'selected' : '' ?>>🇩🇪 Almanya</option>
                    <option value="GB" <?= ($client['country'] ?? '') === 'GB' ? 'selected' : '' ?>>🇬🇧 Birleşik Krallık
                    </option>
                    <option value="NL" <?= ($client['country'] ?? '') === 'NL' ? 'selected' : '' ?>>🇳🇱 Hollanda</option>
                    <option value="FR" <?= ($client['country'] ?? '') === 'FR' ? 'selected' : '' ?>>🇫🇷 Fransa</option>
                </select>
            </div>
        </div>
    </div>

    <button type="submit" class="btn-save">
        <i class="fas fa-save"></i> Değişiklikleri Kaydet
    </button>
</form>

<?php include 'includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const accountTypeRadios = document.querySelectorAll('input[name="account_type"]');
        const corporateFields = document.getElementById('corporate-fields');
        const typeBadge = document.querySelector('.type-badge');

        function toggleFields() {
            const selected = document.querySelector('input[name="account_type"]:checked').value;
            if (selected === 'corporate') {
                corporateFields.style.display = 'block';
                if (typeBadge) typeBadge.textContent = '🏢 Kurumsal';

                // Kurumsal alanları required yap
                document.querySelector('input[name="company_name"]').required = true;
                document.querySelector('input[name="tax_office"]').required = true;
                document.querySelector('input[name="tax_id"]').required = true;
            } else {
                corporateFields.style.display = 'none';
                if (typeBadge) typeBadge.textContent = '👤 Bireysel';

                // Kurumsal alanların required özelliğini kaldır
                document.querySelector('input[name="company_name"]').required = false;
                document.querySelector('input[name="tax_office"]').required = false;
                document.querySelector('input[name="tax_id"]').required = false;
            }
        }

        accountTypeRadios.forEach(radio => {
            radio.addEventListener('change', toggleFields);
        });

        // İlk yüklemede çalıştır
        toggleFields();
    });
</script>