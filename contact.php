<?php
/**
 * Eski adres. Sayfa iletisim.php olarak yeniden adlandırıldı.
 *
 * Kalıcı yönlendirme bırakılıyor: dışarıda paylaşılmış bağlantılar,
 * yer imleri ve arama motoru kayıtları kırılmasın.
 */

declare(strict_types=1);

/* Sorgu dizesi korunur: ?group=... gibi parametreler kaybolmasın */
$sorgu = $_SERVER['QUERY_STRING'] ?? '';
header('Location: iletisim.php' . ($sorgu !== '' ? '?' . $sorgu : ''), true, 301);
exit;
