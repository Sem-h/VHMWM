<?php
/**
 * VHM - Çekim talepleri
 *
 * Önceki sürümde para yaratabilen bir hata vardı: durum geçişi hiç
 * denetlenmiyordu. Zaten reddedilmiş bir talep tekrar reddedilirse
 *
 *     UPDATE affiliates SET balance = balance + ?
 *
 * ikinci kez çalışıyor, ortağın bakiyesine aynı tutar bir daha
 * ekleniyordu. Aynı şekilde tamamlanmış bir talep tekrar tamamlanınca
 * total_withdrawn iki kez artıyordu. PRG de olmadığı için sayfayı
 * yenilemek bunu tetikliyordu.
 *
 * Artık:
 *   - yalnızca izin verilen durum geçişleri uygulanıyor
 *   - durum ve bakiye güncellemesi tek işlem içinde
 *   - CSRF belirteci zorunlu, işlemden sonra yönlendirme yapılıyor
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Guvenlik.php';
Guvenlik::oturumBaslat();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Çekim talepleri';
$currentPage = 'affiliate-withdrawals';

/* affiliate_withdrawals.status enum'u ile birebir aynı */
$durumlar = [
    'pending' => ['Bekliyor', 'badge-warning', 'fa-clock'],
    'processing' => ['İşlemde', 'badge-info', 'fa-spinner'],
    'completed' => ['Tamamlandı', 'badge-success', 'fa-circle-check'],
    'rejected' => ['Reddedildi', 'badge-danger', 'fa-circle-xmark'],
];

/**
 * İzin verilen durum geçişleri.
 * Tamamlanmış ya da reddedilmiş talep son durumdur; tekrar işlenemez.
 */
$gecisler = [
    'islemealin' => ['hedef' => 'processing', 'izin' => ['pending'], 'ad' => 'işleme alındı'],
    'tamamla' => ['hedef' => 'completed', 'izin' => ['pending', 'processing'], 'ad' => 'tamamlandı'],
    'reddet' => ['hedef' => 'rejected', 'izin' => ['pending', 'processing'], 'ad' => 'reddedildi'],
];

$tabloVar = true;
try {
    Database::fetchColumn("SELECT 1 FROM affiliate_withdrawals LIMIT 1");
} catch (Throwable $e) {
    $tabloVar = false;
    error_log('Çekim tablosu okunamadı: ' . $e->getMessage());
}

function cekimMesaj(string $tip, string $metin): void
{
    $_SESSION['cekim_mesaj'] = ['tip' => $tip, 'metin' => $metin];
    header('Location: affiliate-withdrawals.php');
    exit;
}

/* ---------- İşlem ---------- */
if ($tabloVar && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['islem'])) {
    Guvenlik::zorunlu();

    $id = (int) ($_POST['withdrawal_id'] ?? 0);
    $islem = (string) $_POST['islem'];
    $not = mb_substr(trim((string) ($_POST['admin_notes'] ?? '')), 0, 1000);

    if ($id <= 0 || !isset($gecisler[$islem])) {
        cekimMesaj('error', 'Geçersiz işlem.');
    }

    $kural = $gecisler[$islem];
    $db = Database::getInstance();

    try {
        $db->beginTransaction();

        /* Satırı kilitleyerek oku: iki sekmeden aynı anda işlenirse
           ikisi de geçerli görünmesin. */
        $talep = Database::fetch(
            "SELECT * FROM affiliate_withdrawals WHERE id = ? FOR UPDATE",
            [$id]
        );

        if (!$talep) {
            throw new RuntimeException('Çekim talebi bulunamadı.');
        }

        /* Asıl düzeltme: mevcut durum izin verilenler arasında değilse
           hiçbir para hareketi yapılmaz. */
        if (!in_array((string) $talep['status'], $kural['izin'], true)) {
            $mevcut = $durumlar[$talep['status']][0] ?? $talep['status'];
            throw new RuntimeException(
                'Bu talep "' . $mevcut . '" durumunda; bu işlem uygulanamaz.'
            );
        }

        Database::query(
            "UPDATE affiliate_withdrawals
                SET status = ?, admin_notes = ?, processed_by = ?, processed_at = NOW()
              WHERE id = ? AND status = ?",
            [$kural['hedef'], $not !== '' ? $not : null, (int) $_SESSION['admin_id'], $id, $talep['status']]
        );

        $tutar = (float) $talep['amount'];
        $ortakId = (int) $talep['affiliate_id'];

        if ($kural['hedef'] === 'completed') {
            Database::query(
                "UPDATE affiliates SET total_withdrawn = total_withdrawn + ? WHERE id = ?",
                [$tutar, $ortakId]
            );
        } elseif ($kural['hedef'] === 'rejected') {
            /* Talep oluşturulurken bakiyeden düşülmüştü; geri verilir */
            Database::query(
                "UPDATE affiliates SET balance = balance + ? WHERE id = ?",
                [$tutar, $ortakId]
            );
        }

        $db->commit();

        $metin = '#' . $id . ' numaralı çekim talebi ' . $kural['ad'] . '.';
        if ($kural['hedef'] === 'rejected') {
            $metin .= ' ' . number_format($tutar, 2, ',', '.') . ' ₺ ortağın bakiyesine iade edildi.';
        } elseif ($kural['hedef'] === 'completed') {
            $metin .= ' ' . number_format($tutar, 2, ',', '.') . ' ₺ ödendi olarak kaydedildi.';
        }
        cekimMesaj('success', $metin);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Çekim talebi işlenemedi: ' . $e->getMessage());
        cekimMesaj('error', $e->getMessage());
    }
}

$mesaj = null;
if (!empty($_SESSION['cekim_mesaj'])) {
    $mesaj = $_SESSION['cekim_mesaj'];
    unset($_SESSION['cekim_mesaj']);
}

/* ---------- Veriler ---------- */
$suzgec = (string) ($_GET['durum'] ?? '');
$sayfa = max(1, (int) ($_GET['page'] ?? 1));
$adet = 20;

$talepler = [];
$toplam = 0;
$sayfaSayisi = 1;
$ozet = [];

if ($tabloVar) {
    $kosul = [];
    $par = [];
    if (isset($durumlar[$suzgec])) {
        $kosul[] = "w.status = ?";
        $par[] = $suzgec;
    }
    $nerede = $kosul ? ' WHERE ' . implode(' AND ', $kosul) : '';
    $atla = ($sayfa - 1) * $adet;

    $guvenli = static function (string $sql, array $p = []): float {
        try {
            return (float) Database::fetchColumn($sql, $p);
        } catch (Throwable $e) {
            error_log('Çekim sorgusu: ' . $e->getMessage());
            return 0;
        }
    };

    $toplam = (int) $guvenli("SELECT COUNT(*) FROM affiliate_withdrawals w" . $nerede, $par);
    $sayfaSayisi = max(1, (int) ceil($toplam / $adet));

    try {
        $talepler = Database::fetchAll(
            "SELECT w.*, a.affiliate_code, a.balance,
                    c.first_name, c.last_name, c.email,
                    y.username AS islem_yapan
               FROM affiliate_withdrawals w
               LEFT JOIN affiliates a ON a.id = w.affiliate_id
               LEFT JOIN clients c ON c.id = a.client_id
               LEFT JOIN admins y ON y.id = w.processed_by
               {$nerede}
              ORDER BY
                FIELD(w.status, 'pending', 'processing', 'completed', 'rejected'),
                w.created_at DESC
              LIMIT {$adet} OFFSET {$atla}",
            $par
        );
    } catch (Throwable $e) {
        error_log('Çekim listesi okunamadı: ' . $e->getMessage());
        $talepler = [];
    }

    $ozet = [
        ['Bekleyen', $guvenli("SELECT COUNT(*) FROM affiliate_withdrawals WHERE status = 'pending'"), 'fa-clock', 'pending', false],
        ['İşlemde', $guvenli("SELECT COUNT(*) FROM affiliate_withdrawals WHERE status = 'processing'"), 'fa-spinner', 'processing', false],
        ['Ödenecek tutar', $guvenli("SELECT COALESCE(SUM(amount),0) FROM affiliate_withdrawals WHERE status IN ('pending','processing')"), 'fa-turkish-lira-sign', '', true],
        ['Bugüne kadar ödenen', $guvenli("SELECT COALESCE(SUM(amount),0) FROM affiliate_withdrawals WHERE status = 'completed'"), 'fa-circle-check', 'completed', true],
    ];
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* ==========================================
       Çekim talepleri - ck
       ========================================== */
    .ck-ozet {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }

    .ck-ozet-kart {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 15px 17px;
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        color: var(--y-metin-2);
        transition: border-color .15s;
    }

    .ck-ozet-kart:hover {
        border-color: var(--y-primary);
        text-decoration: none;
    }

    .ck-ozet-kart.secili {
        border-color: var(--y-primary);
        background: var(--y-primary-soft);
    }

    .ck-ozet-ikon {
        width: 38px;
        height: 38px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border-radius: 9px;
        background: var(--y-primary-soft);
        color: var(--y-primary);
        font-size: 15px;
    }

    .ck-ozet-kart b {
        display: block;
        font-size: 20px;
        font-weight: 700;
        letter-spacing: -.02em;
        color: var(--y-metin);
        line-height: 1.2;
    }

    .ck-ozet-kart span {
        font-size: 12.5px;
        color: var(--y-metin-3);
    }

    .ck-suzgec {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 16px;
    }

    .ck-suzgec a {
        padding: 8px 14px;
        border: 1px solid var(--y-cizgi);
        border-radius: 999px;
        background: var(--y-yuzey);
        font-size: 12.5px;
        font-weight: 600;
        color: var(--y-metin-2);
    }

    .ck-suzgec a:hover {
        border-color: var(--y-primary);
        color: var(--y-primary);
        text-decoration: none;
    }

    .ck-suzgec a.secili {
        border-color: var(--y-primary);
        background: var(--y-primary-soft);
        color: var(--y-primary);
    }

    .ck-liste {
        display: grid;
        gap: 13px;
    }

    .ck-kart {
        border: 1px solid var(--y-cizgi);
        border-radius: var(--y-r);
        background: var(--y-yuzey);
        box-shadow: var(--y-golge);
        overflow: hidden;
    }

    .ck-kart.bekliyor {
        border-left: 4px solid var(--y-warning);
    }

    .ck-bas {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 14px;
        padding: 14px 18px;
        border-bottom: 1px solid var(--y-cizgi);
    }

    .ck-tutar {
        font-size: 21px;
        font-weight: 700;
        letter-spacing: -.02em;
        color: var(--y-metin);
        white-space: nowrap;
    }

    .ck-kim {
        flex: 1;
        min-width: 180px;
    }

    .ck-kim b {
        display: block;
        font-size: 13.5px;
        font-weight: 600;
        color: var(--y-metin);
    }

    .ck-kim span {
        font-size: 12px;
        color: var(--y-metin-3);
    }

    .ck-govde {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1px;
        background: var(--y-cizgi);
    }

    .ck-alan {
        padding: 12px 18px;
        background: var(--y-yuzey);
    }

    .ck-alan b {
        display: block;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--y-metin-3);
        margin-bottom: 3px;
    }

    .ck-alan span {
        font-size: 13px;
        color: var(--y-metin);
        word-break: break-word;
    }

    .ck-eylem {
        padding: 14px 18px;
        border-top: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
    }

    .ck-eylem textarea {
        min-height: 56px;
        margin-bottom: 10px;
        font-size: 12.5px;
    }

    .ck-dugmeler {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: flex-end;
    }

    .ck-kapali {
        padding: 13px 18px;
        border-top: 1px solid var(--y-cizgi);
        background: var(--y-yuzey-2);
        font-size: 12.5px;
        color: var(--y-metin-3);
    }
</style>

<div class="page-header">
    <div>
        <h1>Çekim talepleri</h1>
        <p><?= number_format($toplam, 0, ',', '.') ?> kayıt<?= $suzgec !== '' ? ' (süzülmüş)' : '' ?></p>
    </div>
    <a href="affiliates.php" class="btn btn-outline"><i class="fas fa-handshake"></i> Satış ortakları</a>
</div>

<?php if ($mesaj): ?>
    <div class="alert alert-<?= $mesaj['tip'] === 'success' ? 'success' : 'error' ?>">
        <i class="fas fa-<?= $mesaj['tip'] === 'success' ? 'circle-check' : 'circle-exclamation' ?> alert-icon"></i>
        <span><?= htmlspecialchars((string) $mesaj['metin']) ?></span>
    </div>
<?php endif; ?>

<?php if (!$tabloVar): ?>
    <div class="alert alert-error">
        <i class="fas fa-circle-exclamation alert-icon"></i>
        <span>Satış ortaklığı tabloları bulunamadı. Kurulum dosyasını içe aktarmanız gerekiyor.</span>
    </div>
<?php else: ?>

    <div class="ck-ozet">
        <?php foreach ($ozet as [$ad, $deger, $ikon, $filtre, $paraMi]): ?>
            <a class="ck-ozet-kart <?= $filtre !== '' && $suzgec === $filtre ? 'secili' : '' ?>"
                href="affiliate-withdrawals.php<?= $filtre !== '' ? '?durum=' . $filtre : '' ?>">
                <span class="ck-ozet-ikon"><i class="fas <?= $ikon ?>"></i></span>
                <span>
                    <b><?= $paraMi
                        ? number_format($deger, 2, ',', '.') . ' ₺'
                        : number_format($deger, 0, ',', '.') ?></b>
                    <span><?= $ad ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="ck-suzgec">
        <a href="affiliate-withdrawals.php" class="<?= $suzgec === '' ? 'secili' : '' ?>">Tümü</a>
        <?php foreach ($durumlar as $kod => [$ad, , ]): ?>
            <a href="?durum=<?= $kod ?>" class="<?= $suzgec === $kod ? 'secili' : '' ?>"><?= $ad ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (!$talepler): ?>
        <div class="ck-kart">
            <div class="empty-state">
                <i class="fas fa-money-bill-transfer"></i>
                <h3><?= $suzgec !== '' ? 'Bu durumda talep yok' : 'Henüz çekim talebi yok' ?></h3>
                <p>Satış ortakları bakiyelerini çekmek istediğinde talepleri burada görünür.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="ck-liste">
            <?php foreach ($talepler as $t):
                $id = (int) $t['id'];
                $ad = trim((string) ($t['first_name'] ?? '') . ' ' . (string) ($t['last_name'] ?? ''));
                [$durumAd, $durumSinif] = $durumlar[$t['status']] ?? [(string) $t['status'], 'badge'];
                $islenebilir = in_array((string) $t['status'], ['pending', 'processing'], true);
                ?>
                <div class="ck-kart <?= $t['status'] === 'pending' ? 'bekliyor' : '' ?>">
                    <div class="ck-bas">
                        <span class="ck-tutar"><?= number_format((float) $t['amount'], 2, ',', '.') ?> ₺</span>
                        <span class="ck-kim">
                            <b><?= htmlspecialchars($ad !== '' ? $ad : 'Ortak bulunamadı') ?></b>
                            <span>
                                <?= htmlspecialchars((string) ($t['affiliate_code'] ?? '—')) ?>
                                &middot; #<?= $id ?>
                                &middot; <?= date('d.m.Y H:i', strtotime((string) $t['created_at'])) ?>
                            </span>
                        </span>
                        <span class="badge <?= $durumSinif ?>"><?= $durumAd ?></span>
                    </div>

                    <div class="ck-govde">
                        <div class="ck-alan">
                            <b>Ödeme yöntemi</b>
                            <span><?= htmlspecialchars((string) ($t['payment_method'] ?: '—')) ?></span>
                        </div>
                        <div class="ck-alan">
                            <b>Ödeme bilgileri</b>
                            <span><?= nl2br(htmlspecialchars((string) ($t['payment_details'] ?: '—'))) ?></span>
                        </div>
                        <div class="ck-alan">
                            <b>Ortağın güncel bakiyesi</b>
                            <span><?= number_format((float) ($t['balance'] ?? 0), 2, ',', '.') ?> ₺</span>
                        </div>
                        <?php if (!empty($t['email'])): ?>
                            <div class="ck-alan">
                                <b>E-posta</b>
                                <span dir="ltr"><?= htmlspecialchars((string) $t['email']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($islenebilir): ?>
                        <form method="post" class="ck-eylem">
                            <?= Guvenlik::alan() ?>
                            <input type="hidden" name="withdrawal_id" value="<?= $id ?>">
                            <label for="not-<?= $id ?>">Yönetici notu (isteğe bağlı)</label>
                            <textarea id="not-<?= $id ?>" name="admin_notes" maxlength="1000"
                                placeholder="Havale dekont numarası, açıklama…"><?= htmlspecialchars((string) ($t['admin_notes'] ?? '')) ?></textarea>
                            <div class="ck-dugmeler">
                                <?php if ($t['status'] === 'pending'): ?>
                                    <button type="submit" name="islem" value="islemealin" class="btn btn-sm btn-outline">
                                        <i class="fas fa-hourglass-half"></i> İşleme al
                                    </button>
                                <?php endif; ?>
                                <button type="submit" name="islem" value="reddet" class="btn btn-sm btn-danger"
                                    onclick="return confirm(<?= htmlspecialchars(json_encode(
                                        '#' . $id . ' reddedilecek ve ' . number_format((float) $t['amount'], 2, ',', '.')
                                        . ' ₺ ortağın bakiyesine iade edilecek. Devam edilsin mi?',
                                        JSON_UNESCAPED_UNICODE
                                    ), ENT_QUOTES) ?>);">
                                    <i class="fas fa-xmark"></i> Reddet
                                </button>
                                <button type="submit" name="islem" value="tamamla" class="btn btn-sm btn-primary"
                                    onclick="return confirm(<?= htmlspecialchars(json_encode(
                                        number_format((float) $t['amount'], 2, ',', '.')
                                        . ' ₺ ödemesini yaptığınızı onaylıyor musunuz? Bu işlem geri alınamaz.',
                                        JSON_UNESCAPED_UNICODE
                                    ), ENT_QUOTES) ?>);">
                                    <i class="fas fa-check"></i> Ödendi, tamamla
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="ck-kapali">
                            <i class="fas fa-lock"></i>
                            Bu talep <?= mb_strtolower($durumAd, 'UTF-8') ?> durumunda ve yeniden işlenemez.
                            <?php if (!empty($t['processed_at'])): ?>
                                <?= date('d.m.Y H:i', strtotime((string) $t['processed_at'])) ?>
                                <?php if (!empty($t['islem_yapan'])): ?>
                                    &middot; <?= htmlspecialchars((string) $t['islem_yapan']) ?>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if (!empty($t['admin_notes'])): ?>
                                <div style="margin-top:6px; color:var(--y-metin-2);">
                                    <?= nl2br(htmlspecialchars((string) $t['admin_notes'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($sayfaSayisi > 1): ?>
        <?php
        $bag = static function (int $s) use ($suzgec): string {
            $p = ['page' => $s];
            if ($suzgec !== '') {
                $p['durum'] = $suzgec;
            }
            return 'affiliate-withdrawals.php?' . http_build_query($p);
        };
        ?>
        <div class="pagination">
            <?php if ($sayfa > 1): ?>
                <a href="<?= htmlspecialchars($bag($sayfa - 1)) ?>"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php for ($i = max(1, $sayfa - 2); $i <= min($sayfaSayisi, $sayfa + 2); $i++): ?>
                <?php if ($i === $sayfa): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($bag($i)) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($sayfa < $sayfaSayisi): ?>
                <a href="<?= htmlspecialchars($bag($sayfa + 1)) ?>"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
