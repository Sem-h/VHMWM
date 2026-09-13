<?php
/**
 * Client View - client-edit.php'ye yönlendirir
 */
require_once dirname(__DIR__) . '/config/config.php';

$id = $_GET['id'] ?? '';
header('Location: client-edit.php?id=' . $id);
exit;

