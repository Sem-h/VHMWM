<?php
/**
 * Eski adres. Sayfa fiziksel-sunucu.php olarak yeniden adlandırıldı.
 *
 * Kalıcı yönlendirme bırakılıyor: dışarıda paylaşılmış bağlantılar,
 * yer imleri ve arama motoru kayıtları kırılmasın.
 */

declare(strict_types=1);

/* Sorgu dizesi korunur: ?group=... gibi parametreler kaybolmasın */
$sorgu = $_SERVER['QUERY_STRING'] ?? '';
header('Location: fiziksel-sunucu.php' . ($sorgu !== '' ? '?' . $sorgu : ''), true, 301);
exit;
