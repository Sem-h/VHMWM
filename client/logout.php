<?php
/**
 * WHMVM - Müşteri Panel Çıkış
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

session_name(SESSION_NAME);
session_start();

// Session'ı temizle
$_SESSION = [];

// Session cookie'sini sil
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Session'ı yok et
session_destroy();

// Ana sayfaya veya giriş sayfasına yönlendir
header('Location: index.php');
exit;

