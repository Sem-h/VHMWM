<?php
/**
 * Keşif talebi uç noktası.
 *
 *   GET  ?islem=mahalleler          -> hizmet verilen il/ilçe/mahalle listesi
 *   GET  ?islem=sokaklar&mahalle=ID -> o mahallenin sokakları (boş olabilir)
 *   POST                            -> keşif talebini kaydeder
 *
 * Adres verisi hizmet_mahalleleri / hizmet_sokaklari tablolarından gelir;
 * sokak listesi boşsa form serbest metne düşer.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';

session_name(SESSION_NAME);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

/** JSON yanıt ver ve bitir */
function yanit(array $veri, int $kod = 200): never
{
    http_response_code($kod);
    echo json_encode($veri, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- CSRF ---------- */
if (empty($_SESSION['kesif_token'])) {
    $_SESSION['kesif_token'] = bin2hex(random_bytes(32));
}

/* ================= GET ================= */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $islem = $_GET['islem'] ?? 'mahalleler';

    if ($islem === 'sokaklar') {
        $mahalleId = (int) ($_GET['mahalle'] ?? 0);
        if ($mahalleId <= 0) {
            yanit(['ok' => false, 'hata' => 'Mahalle seçilmedi.'], 400);
        }
        $sokaklar = Database::fetchAll(
            "SELECT id, sokak FROM hizmet_sokaklari
              WHERE mahalle_id = ? AND is_active = 1
              ORDER BY sokak",
            [$mahalleId]
        );
        yanit(['ok' => true, 'sokaklar' => $sokaklar]);
    }

    $mahalleler = Database::fetchAll(
        "SELECT id, il, ilce, mahalle FROM hizmet_mahalleleri
          WHERE is_active = 1
          ORDER BY sort_order, mahalle"
    );
    yanit(['ok' => true, 'mahalleler' => $mahalleler, 'token' => $_SESSION['kesif_token']]);
}

/* ================= POST ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    yanit(['ok' => false, 'hata' => 'Desteklenmeyen istek.'], 405);
}

$gelen = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($gelen)) {
    $gelen = $_POST;
}

/* Token */
if (!hash_equals((string) ($_SESSION['kesif_token'] ?? ''), (string) ($gelen['token'] ?? ''))) {
    yanit(['ok' => false, 'hata' => 'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.'], 419);
}

/* Bot tuzağı: gizli alan doluysa sessizce başarılı gibi dön */
if (trim((string) ($gelen['website'] ?? '')) !== '') {
    yanit(['ok' => true, 'mesaj' => 'Talebiniz alındı.']);
}

/* Aynı oturumdan üst üste gönderim */
$simdi = time();
if (!empty($_SESSION['kesif_son']) && ($simdi - (int) $_SESSION['kesif_son']) < 30) {
    yanit(['ok' => false, 'hata' => 'Az önce bir talep gönderdiniz. Lütfen biraz bekleyin.'], 429);
}

$kirp = static fn(string $anahtar, int $uzunluk): string =>
    mb_substr(trim((string) ($gelen[$anahtar] ?? '')), 0, $uzunluk);

$adSoyad = $kirp('ad_soyad', 120);
$telefon = $kirp('telefon', 30);
$email = $kirp('email', 150);
$isletme = $kirp('isletme_turu', 60);
$binaNo = $kirp('bina_no', 30);
$aciklama = $kirp('aciklama', 1000);
$sokakAdi = $kirp('sokak', 150);
$mahalleId = (int) ($gelen['mahalle_id'] ?? 0);
$paketId = (int) ($gelen['paket_id'] ?? 0);

$hatalar = [];

if (mb_strlen($adSoyad) < 3) {
    $hatalar['ad_soyad'] = 'Ad soyad en az 3 karakter olmalı.';
}

/* Telefon: yalnızca rakamları say, 10-11 hane bekle */
$telRakam = preg_replace('/\D+/', '', $telefon) ?? '';
if (strlen($telRakam) < 10 || strlen($telRakam) > 11) {
    $hatalar['telefon'] = 'Telefonu 10 haneli olarak yazın (örn. 532 123 45 67).';
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $hatalar['email'] = 'E-posta adresi geçerli görünmüyor.';
}

/* Mahalle hizmet listesinde mi */
$mahalle = null;
if ($mahalleId > 0) {
    $mahalle = Database::fetch(
        "SELECT id, il, ilce, mahalle FROM hizmet_mahalleleri WHERE id = ? AND is_active = 1",
        [$mahalleId]
    );
}
if (empty($mahalle)) {
    $hatalar['mahalle_id'] = 'Lütfen hizmet verdiğimiz mahallelerden birini seçin.';
}

if ($hatalar) {
    yanit(['ok' => false, 'hata' => 'Formda eksik alanlar var.', 'alanlar' => $hatalar], 422);
}

/* Sokak: listede varsa adını oradan al, yoksa yazılanı kullan */
$sokakId = (int) ($gelen['sokak_id'] ?? 0);
if ($sokakId > 0) {
    $sokak = Database::fetch(
        "SELECT sokak FROM hizmet_sokaklari WHERE id = ? AND mahalle_id = ? AND is_active = 1",
        [$sokakId, (int) $mahalle['id']]
    );
    if (!empty($sokak['sokak'])) {
        $sokakAdi = $sokak['sokak'];
    }
}

/* Paket adı, sipariş sonrası ürün değişse bile talepte kalsın diye kopyalanır */
$paketAdi = null;
if ($paketId > 0) {
    $paket = Database::fetch("SELECT name FROM products WHERE id = ? AND is_active = 1", [$paketId]);
    $paketAdi = $paket['name'] ?? null;
    if ($paketAdi === null) {
        $paketId = 0;
    }
}

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

try {
    $id = Database::insert('kesif_talepleri', [
        'paket_id' => $paketId > 0 ? $paketId : null,
        'paket_adi' => $paketAdi,
        'ad_soyad' => $adSoyad,
        'telefon' => $telefon,
        'email' => $email !== '' ? $email : null,
        'isletme_turu' => $isletme !== '' ? $isletme : null,
        'il' => $mahalle['il'],
        'ilce' => $mahalle['ilce'],
        'mahalle' => $mahalle['mahalle'],
        'sokak' => $sokakAdi !== '' ? $sokakAdi : null,
        'bina_no' => $binaNo !== '' ? $binaNo : null,
        'aciklama' => $aciklama !== '' ? $aciklama : null,
        'ip' => mb_substr($ip, 0, 45),
    ]);
} catch (Throwable $e) {
    error_log('Kesif talebi kaydedilemedi: ' . $e->getMessage());
    yanit(['ok' => false, 'hata' => 'Talep kaydedilemedi. Lütfen telefonla ulaşın.'], 500);
}

$_SESSION['kesif_son'] = $simdi;

yanit([
    'ok' => true,
    'id' => (int) $id,
    'mesaj' => 'Talebiniz alındı. Keşif için en kısa sürede sizi arayacağız.',
]);
