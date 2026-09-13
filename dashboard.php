<?php
/**
 * Dashboard Yönlendirme
 * Oturum durumuna göre doğru dashboard'a yönlendirir
 */
require_once __DIR__ . '/config/config.php';

session_name(SESSION_NAME);
session_start();

// Admin giriş yapmışsa admin dashboard'a yönlendir
if (isset($_SESSION['admin_id'])) {
    header('Location: /admin/dashboard.php');
    exit;
}

// Client giriş yapmışsa client dashboard'a yönlendir
if (isset($_SESSION['client_id'])) {
    header('Location: /client/dashboard.php');
    exit;
}

// Kimse giriş yapmamışsa ana sayfaya yönlendir
header('Location: /');
exit;

