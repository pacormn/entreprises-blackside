<?php
require __DIR__.'/config.php';
if (!empty($_SESSION['user'])) {
  send_webhook('Déconnexion', "👋 **{$_SESSION['user']['email']}**", 15105570);
}
session_destroy();
header('Location: index.php');
