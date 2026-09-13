<?php
/**
 * VHM - Destek talebi detayı
 *
 * Önceki sürümde durum beyaz listesi 'on-hold' yazıyordu; tablodaki enum
 * değeri 'on_hold'. "Beklemede" seçildiğinde koşul tutmuyor, sayfa hiçbir
 * şey yapmadan geri dönüyordu. 'customer_reply' ve 'in_progress' ise
 * listede hiç yoktu.
 *
 * Yanıt gövdesi ham HTML olarak basılıyordu; artık düz metin olarak
 * kaçırılıp satır sonları korunuyor. İç not (is_internal) desteği eklendi.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$adminId = (int) $_SESSION['admin_id'];
$adminAdi = (string) ($_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Yönetici');
$id = (int) ($_GET['id'] ?? 0);

/* tickets.status enum'u ile birebir aynı */
const DS_DURUMLAR = [
    'open' => ['Açık', 'badge-info'],
    'answered' => ['Yanıtlandı', 'badge-success'],
    'customer_reply' => ['Müşteri yanıtladı', 'badge-warning'],
    'on_hold' => ['Beklemede', 'badge-warning'],
    'in_progress' => ['İşlemde', 'badge-info'],
    'closed' => ['Kapalı', 'badge'],
];

const DS_ONCELIKLER = [
    'low' => ['Düşük', '#64748b'],
    'medium' => ['Orta', '#2474f5'],
    'high' => ['Yüksek', '#f59e0b'],
    'urgent' => ['Acil', '#ef4444'],
];

$talep = $id > 0 ? Database::fetch(
    "SELECT t.*, c.first_name, c.last_name, c.email AS musteri_eposta, c.phone,
            d.name AS departman
       FROM tickets t
       LEFT JOIN clients c ON c.id = t.client_id
       LEFT JOIN departments d ON d.id = t.department_id
      WHERE t.id = ?",
    [$id]
) : null;

if (!$talep) {
    $_SESSION['ds_mesaj'] = ['tip' => 'error', 'metin' => 'Destek talebi bulunamadı.'];
    header('Location: tickets.php');
    exit;
}

$musteriEposta = (string) ($talep['musteri_eposta'] ?: $talep['email']);
$adSoyad = trim((string) $talep['first_name'] . ' ' . (string) $talep['last_name']);
if ($adSoyad === '') {
    $adSoyad = (string) ($talep['name'] ?: 'Misafir');
}

function talepDon(string $tip, string $metin, int $id): never
{
    $_SESSION['dv_mesaj'] = ['tip' => $tip, 'metin' => $metin];
    header('Location: ticket-view.php?id=' . $id);
    exit;
}

/** Müşteriye bildirim; gönderilemezse işlem yine de tamamlanır */
function talepBildir(string $sablon, array $talep, string $eposta, array $degisken): void
{
    if ($eposta === '') {
        return;
    }

    try {
        require_once dirname(__DIR__) . '/includes/Mail.php';
        Mail::sendTemplate($sablon, $eposta, $degisken, (string) $talep['first_name']);
    } catch (Throwable $e) {
        error_log('Destek bildirimi gönderilemedi: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Guvenlik::zorunlu();

    /* ---------- Yanıt / iç not ---------- */
    if (isset($_POST['yanit'])) {
        $metin = trim((string) $_POST['mesaj']);
        $icNot = isset($_POST['ic_not']);

        if ($metin === '') {
            talepDon('error', 'Boş yanıt gönderilemez.', $id);
        }
        if (mb_strlen($metin) > 20000) {
            talepDon('error', 'Yanıt çok uzun (en fazla 20.000 karakter).', $id);
        }

        try {
            Database::insert('ticket_replies', [
                'ticket_id' => $id,
                'admin_id' => $adminId,
                'message' => $metin,
                'is_internal' => $icNot ? 1 : 0,
            ]);

            /* İç not müşteriye görünmez; talebin durumunu da değiştirmez */
            if (!$icNot) {
                Database::query(
                    "UPDATE tickets
                        SET status = 'answered', last_reply_by = 'admin', last_reply_at = NOW()
                      WHERE id = ?",
                    [$id]
                );
            }
        } catch (Throwable $e) {
            error_log('Destek yanıtı kaydedilemedi: ' . $e->getMessage());
            talepDon('error', 'Yanıt kaydedilemedi.', $id);
        }

        if (!$icNot) {
            talepBildir('ticket_reply', $talep, $musteriEposta, [
                'client_name' => $adSoyad,
                'ticket_id' => (string) $talep['ticket_number'],
                'ticket_subject' => (string) $talep['subject'],
                'reply_staff' => $adminAdi,
                'reply_message' => mb_substr($metin, 0, 200) . (mb_strlen($metin) > 200 ? '…' : ''),
            ]);
        }

        talepDon('success', $icNot ? 'İç not eklendi.' : 'Yanıtınız gönderildi.', $id);
    }

    /* ---------- Durum ---------- */
    if (isset($_POST['durum'])) {
        $yeni = (string) $_POST['durum'];

        if (!isset(DS_DURUMLAR[$yeni])) {
            talepDon('error', 'Geçersiz durum.', $id);
        }
        if ($yeni === $talep['status']) {
            talepDon('uyari', 'Talep zaten bu durumda.', $id);
        }

        Database::query("UPDATE tickets SET status = ? WHERE id = ?", [$yeni, $id]);

        if ($yeni === 'closed') {
            talepBildir('ticket_closed', $talep, $musteriEposta, [
                'client_name' => $adSoyad,
                'ticket_id' => (string) $talep['ticket_number'],
                'ticket_subject' => (string) $talep['subject'],
            ]);
        }

        talepDon('success', 'Durum "' . DS_DURUMLAR[$yeni][0] . '" olarak güncellendi.', $id);
    }

    /* ---------- Öncelik ---------- */
    if (isset($_POST['oncelik'])) {
        $yeni = (string) $_POST['oncelik'];

        if (!isset(DS_ONCELIKLER[$yeni])) {
            talepDon('error', 'Geçersiz öncelik.', $id);
        }

        Database::query("UPDATE tickets SET priority = ? WHERE id = ?", [$yeni, $id]);
        talepDon('success', 'Öncelik "' . DS_ONCELIKLER[$yeni][0] . '" olarak güncellendi.', $id);
    }

    talepDon('error', 'Tanımsız işlem.', $id);
}

$mesaj = null;
if (!empty($_SESSION['dv_mesaj'])) {
    $mesaj = $_SESSION['dv_mesaj'];
    unset($_SESSION['dv_mesaj']);
}

$yanitlar = Database::fetchAll(
    "SELECT r.*,
            c.first_name AS m_ad, c.last_name AS m_soyad,
            a.first_name AS y_ad, a.last_name AS y_soyad, a.username AS y_kullanici
       FROM ticket_replies r
       LEFT JOIN clients c ON c.id = r.client_id
       LEFT JOIN admins a ON a.id = r.admin_id
      WHERE r.ticket_id = ?
      ORDER BY r.created_at, r.id",
    [$id]
);

$hizmet = null;
if (!empty($talep['service_id'])) {
    $hizmet = Database::fetch(
        "SELECT s.id, s.domain, s.status, p.name AS urun
           FROM services s
           LEFT JOIN products p ON p.id = s.product_id
          WHERE s.id = ?",
        [(int) $talep['service_id']]
    );
}

[$durumAd, $durumSinif] = DS_DURUMLAR[$talep['status']] ?? [(string) $talep['status'], 'badge'];
[$oncelikAd, $oncelikRenk] = DS_ONCELIKLER[$talep['priority']] ?? [(string) $talep['priority'], '#64748b'];

$pageTitle = 'Talep ' . ($talep['ticket_number'] ?: '#' . $id);
$currentPage = 'tickets';
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* ==========================================
       Destek talebi detayı - dv
       ========================================== */
    .dv-duzen {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 290px;
        gap: 18px;
        align-items: start;
    }

    .dv-konu {
        padding: 16px 18px;
        margin-bottom: 18px;
        border: 1px solid var(--y-cizgi);
        border-left: 3px solid <?= $oncelikRenk ?>;
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
    }

    .dv-konu h2 {
        font-size: 16px;
        font-weight: 700;
        line-height: 1.45;
        color: var(--y-metin);
    }

    .dv-konu-alt {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin-top: 9px;
        font-size: 12px;
        color: var(--y-metin-3);
    }

    .dv-nokta {
        color: var(--y-cizgi);
    }

    .dv-mesaj {
        display: flex;
        gap: 12px;
        padding: 16px;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
    }

    .dv-mesaj + .dv-mesaj {
        margin-top: 12px;
    }

    .dv-mesaj.personel {
        background: var(--y-primary-soft);
        border-color: color-mix(in srgb, var(--y-primary) 26%, transparent);
    }

    .dv-mesaj.icnot {
        background: color-mix(in srgb, var(--y-warning) 12%, var(--y-yuzey));
        border-color: color-mix(in srgb, var(--y-warning) 34%, transparent);
        border-style: dashed;
    }

    .dv-avatar {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border-radius: 50%;
        background: var(--y-yuzey-2);
        color: var(--y-metin-2);
        font-size: 13px;
        font-weight: 700;
    }

    .dv-mesaj.personel .dv-avatar {
        background: var(--y-primary);
        color: #fff;
    }

    .dv-govde {
        flex: 1;
        min-width: 0;
    }

    .dv-govde-bas {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 8px;
        margin-bottom: 6px;
    }

    .dv-govde-bas b {
        font-size: 13px;
        font-weight: 600;
        color: var(--y-metin);
    }

    .dv-govde-bas time {
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .dv-rozet {
        padding: 1px 7px;
        border-radius: 999px;
        background: var(--y-warning);
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .dv-metin {
        font-size: 13.5px;
        line-height: 1.7;
        color: var(--y-metin-2);
        white-space: pre-wrap;
        word-break: break-word;
    }

    .dv-ek {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 10px;
        padding: 5px 10px;
        border: 1px solid var(--y-cizgi);
        border-radius: 7px;
        font-size: 12px;
        color: var(--y-metin-2);
    }

    .dv-yanit {
        margin-top: 18px;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .dv-yanit-bas {
        padding: 12px 16px;
        border-bottom: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
        font-size: 12.5px;
        font-weight: 700;
    }

    .dv-yanit-govde {
        padding: 16px;
    }

    .dv-yanit textarea {
        width: 100%;
        min-height: 140px;
        padding: 12px;
        border: 1px solid var(--y-cizgi);
        border-radius: 9px;
        background: var(--y-zemin);
        color: var(--y-metin);
        font: inherit;
        font-size: 13.5px;
        line-height: 1.65;
        resize: vertical;
    }

    .dv-yanit textarea:focus {
        outline: none;
        border-color: var(--y-primary);
        box-shadow: 0 0 0 3px var(--y-primary-soft);
    }

    .dv-yanit-alt {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 12px;
    }

    .dv-onay {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 12.5px;
        color: var(--y-metin-2);
        cursor: pointer;
    }

    .dv-panel {
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .dv-panel + .dv-panel {
        margin-top: 18px;
    }

    .dv-panel-bas {
        padding: 12px 16px;
        border-bottom: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
        font-size: 12.5px;
        font-weight: 700;
    }

    .dv-panel-govde {
        padding: 16px;
    }

    .dv-panel-govde form + form {
        margin-top: 10px;
    }

    .dv-panel-govde label {
        display: block;
        margin-bottom: 4px;
        font-size: 11.5px;
        color: var(--y-metin-3);
    }

    .dv-panel-govde .btn {
        width: 100%;
        justify-content: center;
        margin-top: 7px;
    }

    .dv-bilgi-satir {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 8px 0;
        border-bottom: 1px solid var(--y-cizgi-soft);
        font-size: 13px;
    }

    .dv-bilgi-satir:last-child {
        border-bottom: none;
    }

    .dv-bilgi-satir span:first-child {
        color: var(--y-metin-3);
        white-space: nowrap;
    }

    .dv-bilgi-satir span:last-child {
        text-align: right;
        color: var(--y-metin);
        word-break: break-word;
    }

    @media (max-width: 980px) {
        .dv-duzen {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

<div class="page-header">
    <div>
        <h1><?= htmlspecialchars((string) ($talep['ticket_number'] ?: '#' . $id)) ?></h1>
        <p><a href="tickets.php">Destek talepleri</a> &rsaquo; <?= htmlspecialchars($adSoyad) ?></p>
    </div>
    <span class="badge <?= $durumSinif ?>" style="font-size:12.5px;padding:7px 14px"><?= $durumAd ?></span>
</div>

<?php if ($mesaj): ?>
    <div class="alert alert-<?= htmlspecialchars($mesaj['tip']) ?>">
        <?= htmlspecialchars($mesaj['metin']) ?>
    </div>
<?php endif; ?>

<div class="dv-duzen">
    <div>
        <div class="dv-konu">
            <h2><?= htmlspecialchars((string) $talep['subject']) ?></h2>
            <div class="dv-konu-alt">
                <span style="color:<?= $oncelikRenk ?>;font-weight:600"><?= $oncelikAd ?> öncelik</span>
                <span class="dv-nokta">&bull;</span>
                <span><?= htmlspecialchars((string) ($talep['departman'] ?: 'Departman yok')) ?></span>
                <span class="dv-nokta">&bull;</span>
                <span><?= date('d.m.Y H:i', strtotime((string) $talep['created_at'])) ?> açıldı</span>
                <span class="dv-nokta">&bull;</span>
                <span><?= count($yanitlar) ?> yanıt</span>
            </div>
        </div>

        <?php
        /* İlk mesaj tickets tablosunda değil; talebi açan müşteri yanıtı
           ticket_replies'ın ilk satırıdır. Yine de boş liste olabilir. */
        if (!$yanitlar):
            ?>
            <div class="dv-mesaj">
                <div class="dv-govde">
                    <div class="dv-metin" style="color:var(--y-metin-3)">Bu talepte henüz mesaj yok.</div>
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($yanitlar as $y):
            $personelMi = !empty($y['admin_id']);
            $icNot = (int) ($y['is_internal'] ?? 0) === 1;
            $yazan = $personelMi
                ? trim((string) $y['y_ad'] . ' ' . (string) $y['y_soyad'])
                : trim((string) $y['m_ad'] . ' ' . (string) $y['m_soyad']);
            if ($yazan === '') {
                $yazan = $personelMi ? (string) ($y['y_kullanici'] ?: 'Yönetici') : $adSoyad;
            }
            ?>
            <div class="dv-mesaj <?= $icNot ? 'icnot' : ($personelMi ? 'personel' : '') ?>">
                <span class="dv-avatar">
                    <?= htmlspecialchars(mb_strtoupper(mb_substr($yazan, 0, 1), 'UTF-8')) ?>
                </span>
                <div class="dv-govde">
                    <div class="dv-govde-bas">
                        <b><?= htmlspecialchars($yazan) ?></b>
                        <?php if ($icNot): ?>
                            <span class="dv-rozet">İç not</span>
                        <?php elseif ($personelMi): ?>
                            <span style="font-size:11px;color:var(--y-metin-3)">Destek ekibi</span>
                        <?php endif; ?>
                        <time><?= date('d.m.Y H:i', strtotime((string) $y['created_at'])) ?></time>
                    </div>
                    <div class="dv-metin"><?= htmlspecialchars((string) $y['message']) ?></div>
                    <?php if (!empty($y['attachment'])): ?>
                        <span class="dv-ek">
                            <i class="fas fa-paperclip"></i>
                            <?= htmlspecialchars(basename((string) $y['attachment'])) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($talep['status'] !== 'closed'): ?>
            <div class="dv-yanit">
                <div class="dv-yanit-bas">Yanıt yaz</div>
                <div class="dv-yanit-govde">
                    <form method="POST">
                        <input type="hidden" name="yanit" value="1">
                        <textarea name="mesaj" required maxlength="20000"
                            placeholder="Müşteriye yanıtınızı yazın…"></textarea>
                        <div class="dv-yanit-alt">
                            <label class="dv-onay">
                                <input type="checkbox" name="ic_not" value="1">
                                İç not olarak kaydet (müşteri görmez)
                            </label>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Gönder
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="dv-yanit">
                <div class="dv-yanit-govde" style="text-align:center;font-size:13px;color:var(--y-metin-3)">
                    Bu talep kapatıldı. Yanıt yazmak için sağdan yeniden açın.
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div>
        <div class="dv-panel">
            <div class="dv-panel-bas">Talep yönetimi</div>
            <div class="dv-panel-govde">
                <form method="POST">
                    <label for="durum">Durum</label>
                    <select name="durum" id="durum" class="form-control">
                        <?php foreach (DS_DURUMLAR as $deger => [$ad, $sinif]): ?>
                            <option value="<?= $deger ?>" <?= $talep['status'] === $deger ? 'selected' : '' ?>>
                                <?= $ad ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-outline">Durumu kaydet</button>
                </form>

                <form method="POST">
                    <label for="oncelik">Öncelik</label>
                    <select name="oncelik" id="oncelik" class="form-control">
                        <?php foreach (DS_ONCELIKLER as $deger => [$ad, $renk]): ?>
                            <option value="<?= $deger ?>" <?= $talep['priority'] === $deger ? 'selected' : '' ?>>
                                <?= $ad ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-outline">Önceliği kaydet</button>
                </form>
            </div>
        </div>

        <div class="dv-panel">
            <div class="dv-panel-bas">Müşteri</div>
            <div class="dv-panel-govde">
                <div class="dv-bilgi-satir">
                    <span>Ad soyad</span>
                    <span><?= htmlspecialchars($adSoyad) ?></span>
                </div>
                <div class="dv-bilgi-satir">
                    <span>E-posta</span>
                    <span><a href="mailto:<?= htmlspecialchars($musteriEposta) ?>" dir="ltr"
                            style="color:var(--y-primary)"><?= htmlspecialchars($musteriEposta) ?></a></span>
                </div>
                <?php if (!empty($talep['phone'])): ?>
                    <div class="dv-bilgi-satir">
                        <span>Telefon</span>
                        <span dir="ltr"><?= htmlspecialchars((string) $talep['phone']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($talep['client_id'])): ?>
                    <a href="client-view.php?id=<?= (int) $talep['client_id'] ?>"
                        class="btn btn-sm btn-outline">Müşteri kartı</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($hizmet): ?>
            <div class="dv-panel">
                <div class="dv-panel-bas">İlgili hizmet</div>
                <div class="dv-panel-govde">
                    <div class="dv-bilgi-satir">
                        <span>Ürün</span>
                        <span><?= htmlspecialchars((string) ($hizmet['urun'] ?: 'Ürün silinmiş')) ?></span>
                    </div>
                    <?php if (!empty($hizmet['domain'])): ?>
                        <div class="dv-bilgi-satir">
                            <span>Alan adı</span>
                            <span dir="ltr"><?= htmlspecialchars((string) $hizmet['domain']) ?></span>
                        </div>
                    <?php endif; ?>
                    <a href="service-view.php?id=<?= (int) $hizmet['id'] ?>"
                        class="btn btn-sm btn-outline">Hizmete git</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="dv-panel">
            <div class="dv-panel-bas">Talep bilgisi</div>
            <div class="dv-panel-govde">
                <div class="dv-bilgi-satir">
                    <span>Açılış</span>
                    <span><?= date('d.m.Y H:i', strtotime((string) $talep['created_at'])) ?></span>
                </div>
                <div class="dv-bilgi-satir">
                    <span>Son hareket</span>
                    <span><?= date('d.m.Y H:i', strtotime((string) $talep['updated_at'])) ?></span>
                </div>
                <div class="dv-bilgi-satir">
                    <span>Son yanıtlayan</span>
                    <span><?= $talep['last_reply_by'] === 'admin' ? 'Destek ekibi'
                        : ($talep['last_reply_by'] ? 'Müşteri' : '—') ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
