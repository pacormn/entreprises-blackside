<?php
require __DIR__.'/config.php';
require_role(['admin']); // seul un admin peut créer un compte

if ($_SERVER['REQUEST_METHOD']==='POST') {
  csrf_check();
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';
  $role  = in_array($_POST['role'] ?? 'user', ['admin','moderator','user'], true) ? $_POST['role'] : 'user';
  if ($email && $pass) {
    $stmt = $pdo->prepare("INSERT INTO website_society_users(email,password_hash,role) VALUES (?,?,?)");
    $stmt->execute([$email, password_hash($pass, PASSWORD_DEFAULT), $role]);
    log_action($pdo, 'user_create', compact('email','role'));
    send_webhook('Utilisateur créé', "👤 **$email** ($role)", 3447003);
    header('Location: admin.php'); exit;
  } else {
    $err = "Email et mot de passe requis.";
  }
}
$csrf = csrf_token();
?>
<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><title>Créer un utilisateur</title><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="style.css"></head>
<body class="theme-dark">
  <div class="container-xl card-panel" style="max-width:520px;margin-top:30px;">
    <h1>Nouveau compte</h1>
    <?php if (!empty($err)): ?><div class="chip danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <form method="post" class="form">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <div class="field"><label>Email</label><input type="text" name="email" required></div>
      <div class="field"><label>Mot de passe</label><input type="password" name="password" required></div>
      <div class="field">
        <label>Rôle</label>
        <select name="role">
          <option value="user">user</option>
          <option value="moderator">moderator</option>
          <option value="admin">admin</option>
        </select>
      </div>
      <div class="actions">
        <button class="btn success">Créer</button>
        <a class="btn ghost" href="admin.php">Annuler</a>
      </div>
    </form>
  </div>
  <script src="app.js"></script>
</body>
</html>
