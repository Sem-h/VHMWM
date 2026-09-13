<?php
/**
 * Veritabanı şemasını install/whmvm-kurulum.sql dosyasına yazar.
 *
 * KURAL: Bu dosyaya YALNIZCA yapı gider — tablo, sütun, indeks, kısıt.
 *        Satır verisi (ürün, ayar, çeviri, müşteri…) asla yazılmaz.
 *
 * Yeni bir tablo ya da sütun eklediğinizde bu betiği çalıştırın ve
 * çıkan dosyayı commit'leyin:
 *
 *     php install/sema-disari-aktar.php
 *
 * Kurulum sırasında install/install.php bu dosyayı çalıştırır; başlangıç
 * verisi (yönetici hesabı, varsayılan menü) kod tarafından oluşturulur.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

$mysqldump = 'C:/xampp/mysql/bin/mysqldump.exe';
if (!is_file($mysqldump)) {
    $mysqldump = 'mysqldump';           // PATH üzerinden
}

$cikti = __DIR__ . '/whmvm-kurulum.sql';

$arg = [
    $mysqldump,
    '-h', DB_HOST,
    '-u', DB_USER,
];
if (defined('DB_PASS') && DB_PASS !== '') {
    $arg[] = '-p' . DB_PASS;
}
array_push(
    $arg,
    '--default-character-set=utf8mb4',
    '--no-data',                 // <- veri yok, yalnızca yapı
    '--skip-comments',
    '--skip-set-charset',
    '--single-transaction',
    DB_NAME
);

$komut = implode(' ', array_map(static fn($a) => '"' . $a . '"', $arg));
$yapi = (string) shell_exec($komut . ' 2>&1');

if (!str_contains($yapi, 'CREATE TABLE')) {
    fwrite(STDERR, 'Şema alınamadı:' . PHP_EOL . substr($yapi, 0, 400) . PHP_EOL);
    exit(1);
}

/* Sürüm/host satırları gereksiz fark yaratmasın diye ayıklanır */
$yapi = preg_replace('/^\/\*!\d+ SET .*$/m', '', $yapi) ?? $yapi;
$yapi = preg_replace('/\R{3,}/', "\n\n", trim($yapi)) ?? $yapi;

$baslik = <<<SQL
-- ---------------------------------------------------------------
-- VHM - veritabanı şeması
--
-- Bu dosya YALNIZCA tablo yapısını içerir: tablo, sütun, indeks ve
-- kısıtlar. Hiçbir satır verisi bulunmaz (ürün, ayar, çeviri, müşteri
-- ya da kimlik bilgisi yoktur).
--
-- Yeniden üretmek için:  php install/sema-disari-aktar.php
--
-- Kurulum:  mysql -u root vhm < install/whmvm-kurulum.sql
-- ---------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


SQL;

$son = "\n\nSET FOREIGN_KEY_CHECKS = 1;\n";

file_put_contents($cikti, $baslik . $yapi . $son);

$icerik = (string) file_get_contents($cikti);
printf("yazıldı : %s%s", $cikti, PHP_EOL);
printf("tablo   : %d%s", preg_match_all('/CREATE TABLE/', $icerik), PHP_EOL);
printf("INSERT  : %d  (0 olmalı)%s", preg_match_all('/INSERT INTO/', $icerik), PHP_EOL);
printf("boyut   : %d KB%s", (int) round(strlen($icerik) / 1024), PHP_EOL);
