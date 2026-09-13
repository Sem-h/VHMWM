<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
session_name(SESSION_NAME);
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Teklif Düzenle';
$currentPage = 'proposals';

$id = (int) ($_GET['id'] ?? 0);

// Teklifi ve kalemlerini çek
$proposal = Database::fetch("SELECT * FROM proposals WHERE id = ?", [$id]);
if (!$proposal) {
    die("Teklif bulunamadı.");
}

$items = Database::fetchAll("SELECT * FROM proposal_items WHERE proposal_id = ?", [$id]);
$clients = Database::fetchAll("SELECT id, first_name, last_name, company_name FROM clients ORDER BY first_name ASC");

// GÜNCELLEME İŞLEMİ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = (int) $_POST['client_id'];
    $subject = trim($_POST['subject']);
    $validUntil = $_POST['valid_until'];
    $currency = $_POST['currency'] ?? 'TRY';
    $notes = $_POST['notes'] ?? '';

    // Items
    $itemsDesc = $_POST['item_desc'] ?? [];
    $itemsQty = $_POST['item_qty'] ?? [];
    $itemsPrice = $_POST['item_price'] ?? [];

    // Toplam Tutar Hesapla
    $totalAmount = 0;
    $itemsToInsert = [];

    foreach ($itemsDesc as $key => $desc) {
        if (!empty($desc)) {
            $qty = (int) $itemsQty[$key];
            $price = (float) $itemsPrice[$key];
            $lineTotal = $qty * $price;
            $totalAmount += $lineTotal;

            $itemsToInsert[] = [
                'desc' => $desc,
                'qty' => $qty,
                'price' => $price,
                'amount' => $lineTotal
            ];
        }
    }

    // Update Proposal
    Database::query("UPDATE proposals SET client_id=?, subject=?, total_amount=?, currency=?, valid_until=?, admin_notes=? WHERE id=?", [
        $clientId,
        $subject,
        $totalAmount,
        $currency,
        $validUntil,
        $notes,
        $id
    ]);

    // Eski kalemleri sil
    Database::query("DELETE FROM proposal_items WHERE proposal_id=?", [$id]);

    // Yeni kalemleri ekle
    foreach ($itemsToInsert as $item) {
        Database::insert('proposal_items', [
            'proposal_id' => $id,
            'description' => $item['desc'],
            'quantity' => $item['qty'],
            'unit_price' => $item['price'],
            'amount' => $item['amount']
        ]);
    }

    header("Location: proposal-view.php?id=$id&msg=updated");
    exit;
}

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3>Teklif Düzenle #
            <?= str_pad((string) $id, 5, '0', STR_PAD_LEFT) ?>
        </h3>
    </div>
    <div class="card-body">
        <form method="POST" id="proposalForm">
            <div class="row">
                <div class="col-md-6 form-group mb-3">
                    <label>Müşteri</label>
                    <select name="client_id" class="form-control" required>
                        <option value="">Seçiniz...</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $c['id'] == $proposal['client_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?>
                                <?= $c['company_name'] ? '(' . htmlspecialchars($c['company_name']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 form-group mb-3">
                    <label>Teklif Konusu / Başlığı</label>
                    <input type="text" name="subject" class="form-control"
                        value="<?= htmlspecialchars($proposal['subject']) ?>" required>
                </div>
                <div class="col-md-6 form-group mb-3">
                    <label>Geçerlilik Tarihi</label>
                    <input type="date" name="valid_until" class="form-control" value="<?= $proposal['valid_until'] ?>">
                </div>
                <div class="col-md-6 form-group mb-3">
                    <label>Para Birimi</label>
                    <select name="currency" class="form-control">
                        <option value="TRY" <?= $proposal['currency'] == 'TRY' ? 'selected' : '' ?>>TRY (₺)</option>
                        <option value="USD" <?= $proposal['currency'] == 'USD' ? 'selected' : '' ?>>USD ($)</option>
                        <option value="EUR" <?= $proposal['currency'] == 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
                    </select>
                </div>
            </div>

            <h4 class="mt-4 mb-3">Hizmet Kalemleri</h4>
            <table class="table table-bordered" id="itemsTable">
                <thead>
                    <tr class="bg-light">
                        <th width="50%">Açıklama</th>
                        <th width="10%">Miktar</th>
                        <th width="15%">Birim Fiyat</th>
                        <th width="15%">Toplam</th>
                        <th width="5%"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><input type="text" name="item_desc[]" class="form-control"
                                    value="<?= htmlspecialchars($item['description']) ?>" required></td>
                            <td><input type="number" name="item_qty[]" class="form-control qty"
                                    value="<?= $item['quantity'] ?>" min="1" required onchange="calcTotal()"></td>
                            <td><input type="number" name="item_price[]" class="form-control price"
                                    value="<?= $item['unit_price'] ?>" step="0.01" required onchange="calcTotal()"></td>
                            <td><input type="text" class="form-control total"
                                    value="<?= number_format((float) $item['amount'], 2) ?>" readonly></td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i
                                        class="fas fa-trash"></i></button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-end"><strong>GENEL TOPLAM:</strong></td>
                        <td><strong id="grandTotal">
                                <?= number_format((float) $proposal['total_amount'], 2) ?>
                            </strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            <button type="button" class="btn btn-success btn-sm mb-4" onclick="addRow()"><i class="fas fa-plus"></i>
                Satır Ekle</button>

            <div class="form-group mb-4">
                <label>Notlar (Müşteriye Gösterilmez / Admin Notu)</label>
                <textarea name="notes" class="form-control"
                    rows="3"><?= htmlspecialchars($proposal['admin_notes'] ?? '') ?></textarea>
            </div>

            <div class="text-end">
                <a href="proposal-view.php?id=<?= $id ?>" class="btn btn-secondary">İptal</a>
                <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
    function calcTotal() {
        let grandTotal = 0;
        document.querySelectorAll('#itemsTable tbody tr').forEach(row => {
            let qty = parseFloat(row.querySelector('.qty').value) || 0;
            let price = parseFloat(row.querySelector('.price').value) || 0;
            let total = qty * price;
            row.querySelector('.total').value = total.toFixed(2);
            grandTotal += total;
        });
        document.getElementById('grandTotal').innerText = grandTotal.toFixed(2);
    }

    function addRow() {
        let row = `<tr>
        <td><input type="text" name="item_desc[]" class="form-control" placeholder="Hizmet adı" required></td>
        <td><input type="number" name="item_qty[]" class="form-control qty" value="1" min="1" required onchange="calcTotal()"></td>
        <td><input type="number" name="item_price[]" class="form-control price" value="0.00" step="0.01" required onchange="calcTotal()"></td>
        <td><input type="text" class="form-control total" readonly></td>
        <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-trash"></i></button></td>
    </tr>`;
        document.querySelector('#itemsTable tbody').insertAdjacentHTML('beforeend', row);
    }

    function removeRow(btn) {
        if (document.querySelectorAll('#itemsTable tbody tr').length > 1) {
            btn.closest('tr').remove();
            calcTotal();
        }
    }

    // Sayfa yüklendiğinde toplam hesapla
    window.addEventListener('DOMContentLoaded', calcTotal);
</script>

<?php include 'includes/footer.php'; ?>