<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
session_name(SESSION_NAME); session_start();

$pageTitle = 'Yöneticiler';
$currentPage = 'admins';
$db = Database::getInstance();
$message = '';

// Sadece super_admin işlem yapabilir
$isSuperAdmin = ($_SESSION['admin_role'] ?? '') === 'super_admin';

// Silme
if ($isSuperAdmin && isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if ((int)$_GET['delete'] !== (int)$_SESSION['admin_id']) {
        $stmt = $db->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $message = 'Yönetici silindi.';
    }
}

// Ekleme
if ($isSuperAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $stmt = $db->prepare("INSERT INTO admins (username, email, password, first_name, last_name, role, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['username'],
        $_POST['email'],
        password_hash($_POST['password'], PASSWORD_DEFAULT),
        $_POST['first_name'],
        $_POST['last_name'],
        $_POST['role'],
        isset($_POST['is_active']) ? 1 : 0
    ]);
    $message = 'Yönetici eklendi.';
}

$admins = $db->query("SELECT * FROM admins ORDER BY created_at DESC")->fetchAll();

include 'includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?= $message ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>👤 Yöneticiler (<?= count($admins) ?>)</h3>
        <?php if ($isSuperAdmin): ?>
            <button onclick="openModal('addModal')" class="btn btn-primary">+ Yeni Yönetici</button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Kullanıcı Adı</th>
                    <th>Ad Soyad</th>
                    <th>E-posta</th>
                    <th>Rol</th>
                    <th>Son Giriş</th>
                    <th>Durum</th>
                    <?php if ($isSuperAdmin): ?><th>İşlem</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $admin): ?>
                <tr>
                    <td>#<?= $admin['id'] ?></td>
                    <td><strong><?= htmlspecialchars($admin['username']) ?></strong></td>
                    <td><?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?></td>
                    <td><?= htmlspecialchars($admin['email']) ?></td>
                    <td>
                        <?php
                        $roleBadge = match($admin['role']) {
                            'super_admin' => 'danger',
                            'admin' => 'primary',
                            'support' => 'info',
                            'sales' => 'success',
                            default => 'gray'
                        };
                        $roleText = match($admin['role']) {
                            'super_admin' => 'Süper Admin',
                            'admin' => 'Admin',
                            'support' => 'Destek',
                            'sales' => 'Satış',
                            default => $admin['role']
                        };
                        ?>
                        <span class="badge badge-<?= $roleBadge ?>"><?= $roleText ?></span>
                    </td>
                    <td><?= $admin['last_login'] ? date('d.m.Y H:i', strtotime($admin['last_login'])) : 'Hiç' ?></td>
                    <td>
                        <?php if ($admin['is_active']): ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Pasif</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($isSuperAdmin): ?>
                    <td class="actions">
                        <?php if ((int)$admin['id'] !== (int)$_SESSION['admin_id']): ?>
                            <button onclick="confirmDelete('Bu yöneticiyi silmek istediğinizden emin misiniz?', '?delete=<?= $admin['id'] ?>')" class="btn btn-sm btn-danger">Sil</button>
                        <?php else: ?>
                            <span style="color: var(--gray); font-size: 12px;">Kendiniz</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($isSuperAdmin): ?>
<!-- Yeni Yönetici Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Yeni Yönetici Ekle</h3>
            <button onclick="closeModal('addModal')" class="close-btn">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Ad *</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Soyad *</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Kullanıcı Adı *</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>E-posta *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Şifre *</label>
                    <input type="password" name="password" class="form-control" required minlength="8">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Rol *</label>
                        <select name="role" class="form-control" required>
                            <option value="admin">Admin</option>
                            <option value="support">Destek</option>
                            <option value="sales">Satış</option>
                            <option value="super_admin">Süper Admin</option>
                        </select>
                    </div>
                    <div class="form-group" style="display: flex; align-items: center; padding-top: 30px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="is_active" checked> Aktif
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('addModal')" class="btn btn-outline">İptal</button>
                <button type="submit" class="btn btn-primary">Kaydet</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

