<?php
require __DIR__ . '/config.php'; // si ta fonction send_webhook est dedans

$start = microtime(true);
send_webhook("Test", "Webhook ultra rapide !");
echo "Page chargée en " . round(microtime(true) - $start, 4) . "s";
?>
