<?php
// ===== CONFIG BASE =====
$DB_HOST = '83.150.218.23:3306';
$DB_NAME = 's78927_arkazia';
$DB_USER = 'u78927_p54sJEmxO4';
$DB_PASS = 'cqnqWTSdbU88Z1dC2QdWzso7';

// Webhook Discord (fournie)
define('DISCORD_WEBHOOK', 'https://discord.com/api/webhooks/1425393525930983556/t_Fj7IXXNOcQ05PNsW-x_VnTLfsHXWa7GIE0DFgqZQ0u-8QyvOWg1SVGiSTKdE-uh8Uu');

// Démarrer session
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

try {
  $pdo = new PDO(
    "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
    $DB_USER,
    $DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
  );
} catch (Exception $e) {
  http_response_code(500);
  echo "Erreur DB.";
  exit;
}

/** CSRF */
function csrf_token() {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf'];
}
function csrf_check() {
  if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
    http_response_code(403);
    exit('CSRF invalid');
  }
}

/** Auth */
function require_login() {
  if (empty($_SESSION['user'])) {
    header('Location: login.php'); exit;
  }
}
function require_role($roles) {
  $roles = (array)$roles;
  if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], $roles, true)) {
    http_response_code(403); exit('Accès interdit');
  }
}

/** Logs */
function log_action(PDO $pdo, $action, $meta = null) {
  $uid = $_SESSION['user']['id'] ?? null;
  $stmt = $pdo->prepare("INSERT INTO website_society_logs (user_id, action, meta) VALUES (?,?,?)");
  $stmt->execute([$uid, $action, $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null]);
}

/** Webhook Discord */
function send_webhook($title, $description, $color = 3447003, $fields = [])
{
    $webhook_url = "https://discord.com/api/webhooks/1425393525930983556/t_Fj7IXXNOcQ05PNsW-x_VnTLfsHXWa7GIE0DFgqZQ0u-8QyvOWg1SVGiSTKdE-uh8Uu";
    if (!$webhook_url) return;

    // Cherche une image dans la description ou dans les fields
    $image_url = null;
    if (preg_match('/https?:\/\/[^\s"\']+\.(?:jpg|jpeg|png|webp|gif)/i', $description, $m)) {
        $image_url = $m[0];
    } else {
        foreach ($fields as $f) {
            if (!empty($f['value']) && preg_match('/https?:\/\/[^\s"\']+\.(?:jpg|jpeg|png|webp|gif)/i', $f['value'], $m)) {
                $image_url = $m[0];
                break;
            }
        }
    }

    // Création de l'embed Discord
    $embed = [
        "title" => $title,
        "description" => $description,
        "color" => $color,
        "fields" => $fields,
        "timestamp" => date("c")
    ];

    // Si une image est trouvée, ajoute-la
    if ($image_url) {
        // Pour les petites vignettes
        $embed["thumbnail"] = ["url" => $image_url];
        // ou si tu préfères en grande image :
        // $embed["image"] = ["url" => $image_url];
    }

    $data = ["embeds" => [$embed]];
    $json = escapeshellarg(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    // Envoi asynchrone (instantané)
    $cmd = "curl -s -X POST -H 'Content-Type: application/json' -d $json '$webhook_url' > /dev/null 2>&1 &";
    exec($cmd);
}




