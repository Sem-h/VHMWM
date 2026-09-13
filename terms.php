<?php
/**
 * Eski adres. Sayfa kullanim-sartlari.php olarak yeniden adlandırıldı.
 *
 * Kalıcı yönlendirme bırakılıyor: dışarıda paylaşılmış bağlantılar,
 * yer imleri ve arama motoru kayıtları kırılmasın.
 */

declare(strict_types=1);

/* Sorgu dizesi korunur: ?group=... gibi parametreler kaybolmasın */
$sorgu = $_SERVER['QUERY_STRING'] ?? '';
header('Location: kullanim-sartlari.php' . ($sorgu !== '' ? '?' . $sorgu : ''), true, 301);
exit;
