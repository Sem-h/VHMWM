<?php
/**
 * Eski adres. Sayfa gizlilik-politikasi.php olarak yeniden adlandırıldı.
 *
 * Kalıcı yönlendirme bırakılıyor: dışarıda paylaşılmış bağlantılar,
 * yer imleri ve arama motoru kayıtları kırılmasın.
 */

declare(strict_types=1);

/* Sorgu dizesi korunur: ?group=... gibi parametreler kaybolmasın */
$sorgu = $_SERVER['QUERY_STRING'] ?? '';
header('Location: gizlilik-politikasi.php' . ($sorgu !== '' ? '?' . $sorgu : ''), true, 301);
exit;
