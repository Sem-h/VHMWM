<?php
/**
 * NetGSM Modül Kaldırma Scripti
 */

require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/Database.php';

Database::query("DELETE FROM modules WHERE slug = 'netgsm'");

echo "✅ NetGSM modülü kaldırıldı.\n";
