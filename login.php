<?php
require __DIR__.'/config.php';

/*
  ✅ VERSION 2025 — améliorée :
  - Trim + lowercase sur l'email
  - Vérification robuste du mot de passe
  - Logs + webhooks conservés
  - Message d'erreur clair
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $email = strtolower(trim($_POST['email'] ?? ''));
  $pass  = $_POST['password'] ?? '';

  // Récupération utilisateur sans sensibilité à la casse
  $stmt = $pdo->prepare("SELECT * FROM website_society_users WHERE LOWER(email)=?");
  $stmt->execute([$email]);
  $u = $stmt->fetch();

  $ok = $u && password_verify($pass, $u['password_hash']);

  // Historique de tentative
  $hst = $pdo->prepare("INSERT INTO website_society_login_history (user_id, ip, user_agent, success) VALUES (?,?,?,?)");
  $hst->execute([
    $u['id'] ?? null,
    $_SERVER['REMOTE_ADDR'] ?? '',
    $_SERVER['HTTP_USER_AGENT'] ?? '',
    $ok ? 1 : 0
  ]);

  if ($ok) {
    session_regenerate_id(true);
    $_SESSION['user'] = [
      'id'    => $u['id'],
      'email' => $u['email'],
      'role'  => $u['role']
    ];

    log_action($pdo, 'login_success');
    send_webhook('Connexion réussie', "✅ **{$u['email']}** vient de se connecter.", 8311585);
    header('Location: admin_new.php');
    exit;
  } else {
    send_webhook('Connexion échouée', "❌ Tentative invalide pour **$email**", 15548997);
    $err = 'Identifiants invalides. Vérifie ton email et ton mot de passe.';
  }
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
	<link rel="shortcut icon" href="https://r2.fivemanage.com/9j3PjoE11r5xfrxVMAgLE/ARKAZIA.png" type="image/png">
<link rel="icon" type="image/png" href="https://r2.fivemanage.com/9j3PjoE11r5xfrxVMAgLE/ARKAZIA.png">
  <title>Connexion — Annuaire</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="style.css" rel="stylesheet">
</head>
<body class="theme-dark">
  <div class="container-xl">
    <header class="site-header">
      <div class="brand">
        <div class="logo">🔐</div>
        <h1>Connexion</h1>
      </div>
    </header>

    <section class="card-panel" style="max-width:520px;margin:30px auto;">
      <?php if (!empty($err)): ?>
        <div class="chip danger"><?= htmlspecialchars($err) ?></div>
      <?php endif; ?>

      <form method="post" class="form" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div class="field">
          <label>Email</label>
          <input type="email" name="email" required placeholder="admin@site.local" autocomplete="username">
        </div>

        <div class="field">
          <label>Mot de passe</label>
          <input type="password" name="password" required placeholder="••••••••" autocomplete="current-password">
        </div>

        <div class="actions">
          <button class="btn">Se connecter</button>
          <a href="index.php" class="btn ghost">← Retour</a>
        </div>
      </form>

      <p class="hint">Accès restreint à l’équipe d’administration.</p>
    </section>
  </div>

  <div id="notifications"></div>
  <script src="app.js"></script>
</body>
</html>
