<?php
/**
 * WHMVM Admin - E-posta Şablonu Düzenleme
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Mail.php';

require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: email-templates.php');
    exit;
}

$template = Database::fetch("SELECT * FROM email_templates WHERE id = ?", [$id]);

if (!$template) {
    header('Location: email-templates.php');
    exit;
}

$pageTitle = 'Şablon Düzenle: ' . $template['display_name'];
$currentPage = 'settings';
$message = '';
$messageType = 'success';

// Kaydetme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $body = $_POST['body'] ?? '';
    $status = $_POST['status'] ?? 'active';
    
    if (empty($subject)) {
        $message = 'E-posta konusu boş olamaz!';
        $messageType = 'danger';
    } else {
        Database::update('email_templates', [
            'subject' => $subject,
            'body' => $body,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$id]);
        
        // Güncel veriyi çek
        $template = Database::fetch("SELECT * FROM email_templates WHERE id = ?", [$id]);
        $message = 'Şablon başarıyla kaydedildi!';
    }
}

// Değişkenleri parse et
$variables = json_decode($template['variables'] ?? '[]', true) ?: [];

include 'includes/header.php';
?>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.page-header h1 {
    font-size: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-header .breadcrumb {
    font-size: 14px;
    color: var(--gray);
}

.page-header .breadcrumb a {
    color: var(--primary);
}

/* Editor Layout */
.editor-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 25px;
}

@media (max-width: 1024px) {
    .editor-grid {
        grid-template-columns: 1fr;
    }
}

/* Main Editor */
.editor-section {
    background: var(--y-yuzey);
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px 25px;
    background: linear-gradient(135deg, var(--y-yuzey-2) 0%, var(--y-yuzey) 100%);
    border-bottom: 1px solid var(--border);
}

.section-icon {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.section-info h3 {
    font-size: 18px;
    color: var(--dark);
    margin-bottom: 4px;
}

.section-info p {
    font-size: 13px;
    color: var(--gray);
}

.section-body {
    padding: 25px;
}

/* Form Elements */
.form-group {
    margin-bottom: 20px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    font-weight: 600;
    color: var(--dark);
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 14px 18px;
    background: var(--y-yuzey-2);
    border: 2px solid var(--y-cizgi);
    border-radius: 12px;
    color: var(--dark);
    font-size: 14px;
    font-family: inherit;
    transition: all 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    background: var(--y-yuzey);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
}

select.form-control {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 15px center;
    background-size: 18px;
    padding-right: 45px;
}

textarea.form-control {
    min-height: 400px;
    font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace;
    font-size: 13px;
    line-height: 1.6;
    resize: vertical;
}

.form-hint {
    display: block;
    margin-top: 8px;
    color: var(--y-metin-3);
    font-size: 12px;
}

/* Sidebar */
.sidebar-section {
    background: var(--y-yuzey);
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    overflow: hidden;
}

.sidebar-header {
    padding: 18px 20px;
    background: var(--y-yuzey-2);
    border-bottom: 1px solid var(--border);
    font-weight: 600;
    font-size: 14px;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

.sidebar-body {
    padding: 20px;
}

/* Variables List */
.variables-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 300px;
    overflow-y: auto;
}

.variable-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    background: var(--y-yuzey-2);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.variable-item:hover {
    background: rgba(99, 102, 241, 0.1);
}

.variable-item code {
    font-family: 'SF Mono', monospace;
    font-size: 12px;
    color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
    padding: 3px 8px;
    border-radius: 4px;
}

.variable-item .copy-icon {
    color: var(--y-metin-3);
    font-size: 12px;
}

.variable-item:hover .copy-icon {
    color: var(--primary);
}

/* Global Variables */
.global-vars {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid var(--y-cizgi);
}

.global-vars h5 {
    font-size: 12px;
    color: var(--y-metin-3);
    margin-bottom: 10px;
    text-transform: uppercase;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 22px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
    font-family: inherit;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
}

.btn-outline {
    background: var(--y-yuzey);
    border: 2px solid var(--y-cizgi);
    color: var(--dark);
}

.btn-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.btn-secondary {
    background: var(--y-yuzey-2);
    color: var(--dark);
}

.btn-secondary:hover {
    background: var(--y-cizgi);
}

.btn-lg {
    padding: 16px 32px;
    font-size: 16px;
}

/* Back Button */
.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: var(--y-yuzey);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--dark);
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.3s;
}

.btn-back:hover {
    border-color: var(--primary);
    color: var(--primary);
}

/* Alert */
.alert {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 18px 22px;
    border-radius: 14px;
    margin-bottom: 25px;
    font-size: 14px;
    font-weight: 500;
}

.alert-success {
    background: linear-gradient(135deg, #d1fae5 0%, #ecfdf5 100%);
    border: 1px solid #a7f3d0;
    color: #065f46;
}

.alert-danger {
    background: linear-gradient(135deg, #fee2e2 0%, #fef2f2 100%);
    border: 1px solid #fecaca;
    color: #991b1b;
}

/* Template Info */
.template-meta {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.meta-item {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
}

.meta-item .label {
    color: var(--y-metin-3);
}

.meta-item .value {
    color: var(--dark);
    font-weight: 500;
}

.meta-item .value code {
    font-family: 'SF Mono', monospace;
    font-size: 11px;
    background: var(--y-yuzey-2);
    padding: 2px 6px;
    border-radius: 4px;
}

/* Preview Button */
.preview-btn {
    width: 100%;
    margin-top: 15px;
}

/* Actions Footer */
.actions-footer {
    display: flex;
    gap: 12px;
    padding: 20px 25px;
    background: var(--y-yuzey-2);
    border-top: 1px solid var(--border);
}

/* Tooltip */
.copied-tooltip {
    position: fixed;
    background: #1e293b;
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    z-index: 9999;
    animation: fadeInOut 1.5s ease;
}

@keyframes fadeInOut {
    0%, 100% { opacity: 0; transform: translateY(10px); }
    20%, 80% { opacity: 1; transform: translateY(0); }
}

/* Code Editor Enhancement */
.code-toolbar {
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
}

.code-btn {
    padding: 8px 14px;
    background: var(--y-yuzey-2);
    border: 1px solid var(--y-cizgi);
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
}

.code-btn:hover {
    background: var(--y-cizgi);
    border-color: #cbd5e1;
}
</style>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <span><?= $messageType === 'success' ? '✅' : '❌' ?></span>
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1>✏️ <?= htmlspecialchars($template['display_name']) ?></h1>
        <p class="breadcrumb">
            <a href="settings.php">Ayarlar</a> / 
            <a href="email-templates.php">E-posta Şablonları</a> / 
            Düzenle
        </p>
    </div>
    <a href="email-templates.php" class="btn-back">
        ← Şablonlara Dön
    </a>
</div>

<form method="POST">
    <div class="editor-grid">
        <!-- Main Editor -->
        <div>
            <div class="editor-section">
                <div class="section-header">
                    <div class="section-icon">📝</div>
                    <div class="section-info">
                        <h3>Şablon İçeriği</h3>
                        <p>E-posta konusu ve HTML içeriğini düzenleyin</p>
                    </div>
                </div>
                <div class="section-body">
                    <div class="form-group">
                        <label>📌 E-posta Konusu</label>
                        <input type="text" name="subject" class="form-control" 
                               value="<?= htmlspecialchars($template['subject']) ?>" required>
                        <span class="form-hint">Değişkenler kullanabilirsiniz: {client_name}, {site_name} vb.</span>
                    </div>
                    
                    <div class="form-group">
                        <label>📄 E-posta İçeriği (HTML)</label>
                        <div class="code-toolbar">
                            <button type="button" class="code-btn" onclick="insertTag('strong')">
                                <b>B</b> Kalın
                            </button>
                            <button type="button" class="code-btn" onclick="insertTag('em')">
                                <i>I</i> İtalik
                            </button>
                            <button type="button" class="code-btn" onclick="insertLink()">
                                🔗 Link
                            </button>
                            <button type="button" class="code-btn" onclick="insertButton()">
                                🔘 Buton
                            </button>
                        </div>
                        <textarea name="body" id="bodyEditor" class="form-control"><?= htmlspecialchars($template['body']) ?></textarea>
                        <span class="form-hint">HTML formatında içerik yazabilirsiniz. Wrapper otomatik eklenir.</span>
                    </div>
                    
                    <div class="form-group">
                        <label>📊 Durum</label>
                        <select name="status" class="form-control">
                            <option value="active" <?= $template['status'] === 'active' ? 'selected' : '' ?>>✅ Aktif</option>
                            <option value="inactive" <?= $template['status'] === 'inactive' ? 'selected' : '' ?>>❌ Pasif</option>
                        </select>
                        <span class="form-hint">Pasif şablonlar gönderilmez.</span>
                    </div>
                </div>
                <div class="actions-footer">
                    <button type="submit" class="btn btn-primary btn-lg">
                        💾 Değişiklikleri Kaydet
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="previewTemplate()">
                        👁️ Önizle
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div>
            <!-- Variables -->
            <div class="sidebar-section">
                <div class="sidebar-header">
                    🏷️ Kullanılabilir Değişkenler
                </div>
                <div class="sidebar-body">
                    <div class="variables-list">
                        <?php if (!empty($variables)): ?>
                            <?php foreach ($variables as $var): ?>
                                <div class="variable-item" onclick="copyVariable('<?= $var ?>')">
                                    <code>{<?= $var ?>}</code>
                                    <span class="copy-icon">📋</span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #64748b; font-size: 13px;">Bu şablon için özel değişken tanımlanmamış.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="global-vars">
                        <h5>Global Değişkenler</h5>
                        <div class="variables-list">
                            <div class="variable-item" onclick="copyVariable('site_name')">
                                <code>{site_name}</code>
                                <span class="copy-icon">📋</span>
                            </div>
                            <div class="variable-item" onclick="copyVariable('site_url')">
                                <code>{site_url}</code>
                                <span class="copy-icon">📋</span>
                            </div>
                            <div class="variable-item" onclick="copyVariable('company_name')">
                                <code>{company_name}</code>
                                <span class="copy-icon">📋</span>
                            </div>
                            <div class="variable-item" onclick="copyVariable('company_email')">
                                <code>{company_email}</code>
                                <span class="copy-icon">📋</span>
                            </div>
                            <div class="variable-item" onclick="copyVariable('current_date')">
                                <code>{current_date}</code>
                                <span class="copy-icon">📋</span>
                            </div>
                            <div class="variable-item" onclick="copyVariable('current_year')">
                                <code>{current_year}</code>
                                <span class="copy-icon">📋</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Template Info -->
            <div class="sidebar-section">
                <div class="sidebar-header">
                    ℹ️ Şablon Bilgileri
                </div>
                <div class="sidebar-body">
                    <div class="template-meta">
                        <div class="meta-item">
                            <span class="label">Şablon Adı</span>
                            <span class="value"><code><?= htmlspecialchars($template['name']) ?></code></span>
                        </div>
                        <div class="meta-item">
                            <span class="label">Kategori</span>
                            <span class="value"><?= ucfirst($template['category']) ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="label">Oluşturulma</span>
                            <span class="value"><?= date('d.m.Y', strtotime($template['created_at'])) ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="label">Son Güncelleme</span>
                            <span class="value"><?= date('d.m.Y H:i', strtotime($template['updated_at'])) ?></span>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-outline preview-btn" onclick="previewTemplate()">
                        👁️ Önizlemeyi Göster
                    </button>
                </div>
            </div>
            
            <!-- Tips -->
            <div class="sidebar-section">
                <div class="sidebar-header">
                    💡 İpuçları
                </div>
                <div class="sidebar-body">
                    <ul style="margin: 0; padding-left: 20px; color: #64748b; font-size: 13px; line-height: 1.8;">
                        <li>Değişkenlere tıklayarak kopyalayın</li>
                        <li>HTML stilleri inline olmalı</li>
                        <li>Tablo yapısı e-posta uyumluluğu için önerilir</li>
                        <li>Header ve footer otomatik eklenir</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Preview Modal -->
<div id="previewModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 16px; max-width: 800px; width: 90%; max-height: 90vh; overflow: hidden; box-shadow: 0 25px 50px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px 25px; border-bottom: 1px solid #e2e8f0;">
            <h3 style="margin: 0; font-size: 18px;">📧 E-posta Önizleme</h3>
            <button onclick="closePreview()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b;">×</button>
        </div>
        <div style="padding: 0; overflow-y: auto; max-height: calc(90vh - 70px);">
            <iframe id="previewFrame" style="width: 100%; height: 600px; border: none;"></iframe>
        </div>
    </div>
</div>

<script>
// Copy variable to clipboard and insert into editor
function copyVariable(varName) {
    const text = '{' + varName + '}';
    const editor = document.getElementById('bodyEditor');
    
    // Insert at cursor position
    const start = editor.selectionStart;
    const end = editor.selectionEnd;
    const content = editor.value;
    editor.value = content.substring(0, start) + text + content.substring(end);
    editor.selectionStart = editor.selectionEnd = start + text.length;
    editor.focus();
    
    // Show tooltip
    showTooltip('Değişken eklendi!');
}

// Show tooltip
function showTooltip(message) {
    const tooltip = document.createElement('div');
    tooltip.className = 'copied-tooltip';
    tooltip.textContent = message;
    tooltip.style.top = (event.clientY - 40) + 'px';
    tooltip.style.left = (event.clientX - 50) + 'px';
    document.body.appendChild(tooltip);
    
    setTimeout(() => tooltip.remove(), 1500);
}

// Insert HTML tag
function insertTag(tag) {
    const editor = document.getElementById('bodyEditor');
    const start = editor.selectionStart;
    const end = editor.selectionEnd;
    const selected = editor.value.substring(start, end);
    const content = editor.value;
    
    const newText = '<' + tag + '>' + (selected || 'metin') + '</' + tag + '>';
    editor.value = content.substring(0, start) + newText + content.substring(end);
    editor.focus();
}

// Insert link
function insertLink() {
    const url = prompt('Link URL:', 'https://');
    if (url) {
        const editor = document.getElementById('bodyEditor');
        const start = editor.selectionStart;
        const end = editor.selectionEnd;
        const selected = editor.value.substring(start, end) || 'Link Metni';
        const content = editor.value;
        
        const newText = '<a href="' + url + '" style="color: #6366f1;">' + selected + '</a>';
        editor.value = content.substring(0, start) + newText + content.substring(end);
        editor.focus();
    }
}

// Insert button
function insertButton() {
    const url = prompt('Buton Link:', '{site_url}/client/');
    if (url) {
        const text = prompt('Buton Metni:', 'Tıklayın');
        if (text) {
            const editor = document.getElementById('bodyEditor');
            const start = editor.selectionStart;
            const content = editor.value;
            
            const buttonHtml = `
<table role="presentation" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border-radius: 8px;">
            <a href="${url}" style="display: inline-block; padding: 14px 30px; color: white; text-decoration: none; font-weight: 600;">
                ${text} →
            </a>
        </td>
    </tr>
</table>`;
            
            editor.value = content.substring(0, start) + buttonHtml + content.substring(start);
            editor.focus();
        }
    }
}

// Preview template
function previewTemplate() {
    const modal = document.getElementById('previewModal');
    const frame = document.getElementById('previewFrame');
    frame.src = 'ajax/preview-template.php?id=<?= $id ?>';
    modal.style.display = 'flex';
}

function closePreview() {
    document.getElementById('previewModal').style.display = 'none';
    document.getElementById('previewFrame').src = '';
}

// ESC key to close
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePreview();
});

// Click outside to close
document.getElementById('previewModal').addEventListener('click', function(e) {
    if (e.target === this) closePreview();
});

// Tab key support for textarea
document.getElementById('bodyEditor').addEventListener('keydown', function(e) {
    if (e.key === 'Tab') {
        e.preventDefault();
        const start = this.selectionStart;
        const end = this.selectionEnd;
        this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
        this.selectionStart = this.selectionEnd = start + 4;
    }
});
</script>

<?php include 'includes/footer.php'; ?>

