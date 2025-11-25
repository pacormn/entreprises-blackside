$start = microtime(true);
send_webhook("Test", "Ceci est un test rapide");
echo "Page chargée en " . round(microtime(true)-$start, 4) . "s";
