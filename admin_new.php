<?php
require __DIR__.'/config.php';
require_login();
require_role(['admin','moderator']);

$csrf = csrf_token();

/* ---------- Stats pour l'onglet "Stats" ---------- */
$counts = [
  'users'  => (int)($pdo->query("SELECT COUNT(*) c FROM website_society_users")->fetch()['c'] ?? 0),
  'cards'  => (int)($pdo->query("SELECT COUNT(*) c FROM website_society_cards")->fetch()['c'] ?? 0),
  'open'   => (int)($pdo->query("SELECT COUNT(*) c FROM website_society_cards WHERE status='open'")->fetch()['c'] ?? 0),
  'closed' => (int)($pdo->query("SELECT COUNT(*) c FROM website_society_cards WHERE status='closed'")->fetch()['c'] ?? 0),
];

/* Évolution cartes par mois (12 derniers mois) */
$seriesStmt = $pdo->query("
  SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COUNT(*) c
  FROM website_society_cards
  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
  GROUP BY ym
  ORDER BY ym ASC
");
$series = $seriesStmt->fetchAll(PDO::FETCH_KEY_PAIR); // [ '2025-01' => 12, ... ]
$labels = array_keys($series);
$values = array_values($series);

/* ---------- Récup listages initiaux ---------- */
$cards = $pdo->query("SELECT * FROM website_society_cards ORDER BY created_at DESC")->fetchAll();
$latestLogs = $pdo->query("
  SELECT l.*, u.email 
  FROM website_society_logs l 
  LEFT JOIN website_society_users u ON u.id=l.user_id 
  ORDER BY l.created_at DESC LIMIT 25
")->fetchAll();
$users = $pdo->query("SELECT id,email,role,created_at FROM website_society_users ORDER BY created_at DESC")->fetchAll();

/* ---------- Actions POST ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  /* --- CRUD ENTREPRISES --- */
  if (in_array($action, ['create_card','update_card','delete_card'], true)) {
    require_role(['admin','moderator']);
    if ($action === 'delete_card' && $_SESSION['user']['role'] !== 'admin') {
      http_response_code(403); exit('Accès interdit');
    }

    if ($action === 'delete_card') {
      $id = (int)$_POST['id'];
      $pdo->prepare("DELETE FROM website_society_cards WHERE id=?")->execute([$id]);
      log_action($pdo, 'card_delete', ['id'=>$id]);
      send_webhook('Carte supprimée', "🗑️ ID **$id**", 15548997);
      header('Location: admin_new.php?tab=entreprises&ok=1'); exit;
    }

    // Champs communs
    $name   = trim($_POST['name'] ?? '');
    $link   = trim($_POST['link'] ?? '');
    $status = in_array(($_POST['status'] ?? 'open'), ['open','closed'], true) ? $_POST['status'] : 'open';
    $imgUrl = trim($_POST['image_url'] ?? '');
    $holder = trim($_POST['holder_discord'] ?? '');
    $is_illegal = !empty($_POST['is_illegal']) ? 1 : 0;
    $illegal_group = trim($_POST['illegal_group'] ?? null);
    if ($illegal_group === '') $illegal_group = null;

    // Upload (optionnel)
    if (!empty($_FILES['image_file']['name'] ?? '')) {
      $dir = __DIR__ . '/uploads';
      if (!is_dir($dir)) @mkdir($dir, 0775, true);
      $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
      if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
        $fname = 'img_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
        $dest = $dir.'/'.$fname;
        if (is_uploaded_file($_FILES['image_file']['tmp_name'])) {
          move_uploaded_file($_FILES['image_file']['tmp_name'], $dest);
          $imgUrl = 'uploads/'.$fname;
          send_webhook('Image upload', "Fichier **$fname** ajouté.", 3447003);
        }
      }
    }

    if ($action === 'create_card') {
$pdo->prepare("INSERT INTO website_society_cards (name, link, status, image_url, holder_discord, is_illegal) VALUES (?,?,?,?,?,?)")
    ->execute([$name, $link, $status, $imgUrl, $holder, $is_illegal]);

      log_action($pdo, 'card_create', compact('name','status','holder','is_illegal','illegal_group'));
      send_webhook('Carte créée', "➕ **$name** ($status)", 5763719, [
        ['name'=>'Holder', 'value'=>$holder ?: '—'],
        ['name'=>'URL', 'value'=>$link ?: '—'],
        ['name'=>'Illégal ?', 'value'=>$is_illegal ? 'Oui' : 'Non'],
        ['name'=>'Groupe', 'value'=>$illegal_group ?: '—'],
      ]);
      header('Location: admin_new.php?tab=entreprises&ok=1'); exit;
    }

    if ($action === 'update_card') {
      $id = (int)$_POST['id'];
$pdo->prepare("UPDATE website_society_cards SET name=?, link=?, status=?, image_url=?, holder_discord=?, is_illegal=? WHERE id=?")
    ->execute([$name, $link, $status, $imgUrl, $holder, $is_illegal, $id]);

      log_action($pdo, 'card_update', compact('id','name','status','holder','is_illegal','illegal_group'));
      send_webhook('Carte modifiée', "✏️ **$name** ($status)", 15844367, [
        ['name'=>'ID', 'value'=>strval($id)],
        ['name'=>'Holder', 'value'=>$holder ?: '—'],
        ['name'=>'Illégal ?', 'value'=>$is_illegal ? 'Oui' : 'Non'],
        ['name'=>'Groupe', 'value'=>$illegal_group ?: '—'],
      ]);
      header('Location: admin_new.php?tab=entreprises&ok=1'); exit;
    }
  }

  /* --- CREATE USER --- */
  if ($action === 'create_user') {
    require_role(['admin']);
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';
    $role  = in_array($_POST['role'] ?? 'user', ['admin','moderator','user'], true) ? $_POST['role'] : 'user';
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($pass) >= 8) {
      $pdo->prepare("INSERT INTO website_society_users(email,password_hash,role) VALUES (?,?,?)")
          ->execute([$email, password_hash($pass, PASSWORD_DEFAULT), $role]);
      log_action($pdo, 'user_create', compact('email','role'));
      send_webhook('Utilisateur créé', "👤 **$email** ($role)", 3447003);
      header('Location: admin_new.php?tab=utilisateurs&ok=1'); exit;
    } else {
      header('Location: admin_new.php?tab=utilisateurs&err=1'); exit;
    }
  }

  /* --- DELETE USER --- */
  if ($action === 'delete_user') {
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    if ($id === (int)$_SESSION['user']['id']) {
      header('Location: admin_new.php?tab=utilisateurs&err=self'); exit;
    }
    $stmt = $pdo->prepare("SELECT email FROM website_society_users WHERE id=?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
      $pdo->prepare("DELETE FROM website_society_users WHERE id=?")->execute([$id]);
      log_action($pdo, 'user_delete', ['id'=>$id, 'email'=>$user['email']]);
      send_webhook('Utilisateur supprimé', "🗑️ **{$user['email']}** (ID: $id)", 15158332);
      header('Location: admin_new.php?tab=utilisateurs&ok=del'); exit;
    } else {
      header('Location: admin_new.php?tab=utilisateurs&err=notfound'); exit;
    }
  }

  /* --- UPDATE USER ROLE --- */
  if ($action === 'update_role') {
    require_role(['admin']);
    $id   = (int)($_POST['id'] ?? 0);
    $role = $_POST['role'] ?? '';
    if (!in_array($role, ['user','moderator','admin'], true)) {
      header('Location: admin_new.php?tab=utilisateurs&err=badrole'); exit;
    }
    if ($id === (int)$_SESSION['user']['id']) {
      header('Location: admin_new.php?tab=utilisateurs&err=selfrole'); exit;
    }
    $stmt = $pdo->prepare("SELECT email FROM website_society_users WHERE id=?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
      $pdo->prepare("UPDATE website_society_users SET role=? WHERE id=?")->execute([$role, $id]);
      log_action($pdo, 'user_role_update', ['id'=>$id, 'email'=>$user['email'], 'role'=>$role]);
      send_webhook('Rôle modifié', "🛠️ **{$user['email']}** → **$role**", 3066993);
      header('Location: admin_new.php?tab=utilisateurs&ok=role'); exit;
    } else {
      header('Location: admin_new.php?tab=utilisateurs&err=notfound'); exit;
    }
  }
}

/* ---------- Thème et affichage ---------- */
$theme = ($_COOKIE['dash_theme'] ?? 'dark');
$themeClass = $theme === 'modern' ? 'theme-modern' : 'theme-dark';
$activeTab = $_GET['tab'] ?? 'entreprises';
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <link rel="shortcut icon" href="https://r2.fivemanage.com/9L3GkUqw3LqkDP21YkuCi/logoFlashnight.png" type="image/png">
  <link rel="icon" type="image/png" href="https://r2.fivemanage.com/9L3GkUqw3LqkDP21YkuCi/logoFlashnight.png">
  <title>Dashboard — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="style.css" rel="stylesheet">
  <link href="dashboard.css" rel="stylesheet">
  <style>
    .role-user { color:#7cd992; font-weight:600; }
    .role-moderator { color:#f3d17a; font-weight:600; }
    .role-admin { color:#f17474; font-weight:600; }
    .tbl-thumb{width:50px;height:34px;object-fit:cover;border-radius:6px;border:1px solid rgba(255,255,255,0.1)}
  </style>
</head>
<body class="<?= htmlspecialchars($themeClass) ?>">
<div class="container-xl">
  <!-- Header -->
  <header class="site-header">
    <div class="brand">
      <div class="logo">📊</div>
      <h1>Dashboard <span class="muted">/ administration</span></h1>
    </div>
    <div class="header-actions">
      <div class="user-menu">
        <div class="user-chip">
          <span class="user-email"><?= htmlspecialchars($_SESSION['user']['email']) ?></span>
          <div class="dropdown">
            <a href="register.php">Créer un utilisateur</a>
            <a href="logs.php">Voir tous les logs</a>
            <a href="logout.php">Se déconnecter</a>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- Tabs -->
  <nav class="tabbar">
    <a href="?tab=entreprises" class="tab-btn <?= $activeTab==='entreprises'?'active':'' ?>">🏢 Entreprises</a>
    <!--<a href="admin_illegal.php" class="tab-btn">⚠️ Groupes illégaux</a>-->
    <a href="?tab=stats" class="tab-btn <?= $activeTab==='stats'?'active':'' ?>">📈 Stats</a>
    <a href="?tab=logs" class="tab-btn <?= $activeTab==='logs'?'active':'' ?>">🧾 Logs</a>
    <a href="?tab=utilisateurs" class="tab-btn <?= $activeTab==='utilisateurs'?'active':'' ?>">👥 Utilisateurs</a>
    <a href="index.php" class="tab-btn ghost">↩️ Site</a>
  </nav>

  <!-- Panels -->
  <?php if ($activeTab==='entreprises'): ?>
    <section class="grid-2">
      <!-- Liste -->
      <div class="card-panel">
        <h2>Liste des entreprises</h2>
        <div class="toolbar compact">
          <input type="search" id="searchCards" placeholder="Rechercher par nom ou holder…">
          <select id="filterStatus">
            <option value="">Tous statuts</option>
            <option value="open">Disponibles</option>
            <option value="closed">Non disponibles</option>
          </select>
        </div>
        <div class="table-responsive">
          <table class="table" id="cardsTable">
            <thead>
              <tr>
                <th>ID</th><th>Nom</th><th>Statut</th><th>Holder</th><th>Image</th><th>Illégal</th><th>Actions</th>
              </tr>
            </thead>
<tbody>
  <?php foreach ($cards as $c): ?>
  <tr>
    <td>#<?= (int)$c['id'] ?></td>
    <td><?= htmlspecialchars($c['name']) ?></td>
    <td><?= htmlspecialchars($c['status']) ?></td>
    <td><?= htmlspecialchars($c['holder_discord'] ?: '—') ?></td>
    <td><?php if ($c['image_url']): ?><img class="tbl-thumb" src="<?= htmlspecialchars($c['image_url']) ?>" alt=""><?php endif; ?></td>
    <td><?= !empty($c['is_illegal']) ? '⚠️ Oui' : '—' ?></td>
    <td class="row-actions">
      <button class="btn xs" data-edit='<?= json_encode($c, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>'>✏️</button>
      <?php if ($_SESSION['user']['role']==='admin'): ?>
      <form method="post" onsubmit="return confirm('Supprimer cette entreprise ?');" style="display:inline">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="delete_card">
        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <button class="btn xs danger">🗑️</button>
      </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</tbody>
          </table>
        </div>
      </div>

      <!-- Formulaire -->
      <div class="card-panel">
        <h2>Ajouter / Modifier</h2>
        <form class="form" method="post" enctype="multipart/form-data" id="cardForm">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="create_card" id="formAction">
          <input type="hidden" name="id" id="formId">

          <div class="field">
            <label>Nom</label>
            <input type="text" name="name" id="name" required>
          </div>

          <div class="field">
            <label>Lien</label>
            <input type="url" name="link" id="link" placeholder="https://...">
          </div>

          <div class="field">
            <label>Statut</label>
            <select name="status" id="status">
              <option value="open">Ouvert (Disponible)</option>
              <option value="closed">Fermé (Non disponible)</option>
            </select>
          </div>

          <div class="field">
            <label>Image (URL)</label>
            <input type="url" name="image_url" id="imageUrl" placeholder="https://image...">
            <div id="imgPreview" class="img-preview">
              <span class="hint">Aperçu de l’image</span>
              <img id="preview" alt="" style="display:none">
            </div>
          </div>

          <div class="field">
            <label>Upload d’image (fichier)</label>
            <input type="file" name="image_file" id="fileUpload" accept="image/*">
          </div>

          <div class="field">
            <label>Tenue par (handle Discord)</label>
            <input type="text" name="holder_discord" id="holder" placeholder="@Pseudo#0001 (si non dispo)">
          </div>

<!-- === SECTION ILLÉGAL (simple indicateur, sans modifier la visibilité) === -->
<div class="field">
  <label style="display:block;margin-bottom:6px;font-weight:600;">Entreprise illégale ?</label>
  <label class="switch">
    <input type="checkbox" name="is_illegal" id="is_illegal" value="1" <?= !empty($editing['is_illegal']) ? 'checked' : '' ?>>
    <span class="slider"></span>
  </label>
  <p class="hint" style="color:#aaa;font-size:0.85rem;margin-top:5px;">
    Cochez cette case si l’entreprise appartient à un groupe illégal.<br>
    Cela n’affecte pas son affichage public normal.
  </p>
</div>


          <div class="actions">
            <button class="btn success" type="submit">Enregistrer</button>
            <button class="btn ghost" type="button" id="resetForm">Réinitialiser</button>
          </div>
        </form>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($activeTab==='stats'): ?>
    <section class="card-panel">
      <h2>Statistiques</h2>
      <div class="stats-grid">
        <div class="stat-card"><h3>Entreprises</h3><p><?= (int)$counts['cards'] ?></p></div>
        <div class="stat-card success"><h3>Disponibles</h3><p><?= (int)$counts['open'] ?></p></div>
        <div class="stat-card warning"><h3>Non dispo</h3><p><?= (int)$counts['closed'] ?></p></div>
        <div class="stat-card neutral"><h3>Utilisateurs</h3><p><?= (int)$counts['users'] ?></p></div>
      </div>

<div class="charts">
  <canvas id="chartPie" width="280" height="280"></canvas>
  <canvas id="chartLine" width="600" height="250"></canvas>
</div>

    </section>
    <script>
      window.__CHART_DATA__ = {
        pie: { open: <?= (int)$counts['open'] ?>, closed: <?= (int)$counts['closed'] ?> },
        line: { labels: <?= json_encode($labels) ?>, values: <?= json_encode($values) ?> }
      };
    </script>
  <?php endif; ?>

  <?php if ($activeTab==='logs'): ?>
    <section class="card-panel">
      <h2>Derniers logs</h2>
      <div class="toolbar compact">
        <input type="search" id="searchLogs" placeholder="Rechercher dans les actions ou email…">
      </div>
      <div class="table-responsive">
        <table class="table" id="logsTable">
          <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Meta</th></tr></thead>
          <tbody>
          <?php foreach ($latestLogs as $l): ?>
            <tr data-user="<?= htmlspecialchars(mb_strtolower($l['email'] ?? '')) ?>" data-action="<?= htmlspecialchars(mb_strtolower($l['action'] ?? '')) ?>">
              <td><?= htmlspecialchars($l['created_at']) ?></td>
              <td><?= htmlspecialchars($l['email'] ?? '—') ?></td>
              <td><?= htmlspecialchars($l['action'] ?? '') ?></td>
              <td><code><?= htmlspecialchars($l['meta'] ?? '') ?></code></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="actions" style="margin-top:10px;">
        <a class="btn" href="logs.php">Voir plus / Export CSV</a>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($activeTab==='utilisateurs'): ?>
    <section class="grid-2">
      <div class="card-panel">
        <h2>Créer un utilisateur</h2>
        <?php if (!empty($_GET['ok']) && $_GET['ok']==='1'): ?><div class="chip success">✅ Utilisateur créé.</div><?php endif; ?>
        <?php if (!empty($_GET['ok']) && $_GET['ok']==='del'): ?><div class="chip success">🗑️ Utilisateur supprimé.</div><?php endif; ?>
        <?php if (!empty($_GET['ok']) && $_GET['ok']==='role'): ?><div class="chip success">🛠️ Rôle mis à jour.</div><?php endif; ?>
        <?php if (!empty($_GET['err'])): ?>
          <div class="chip danger">
            <?php
              if ($_GET['err']==='1')        echo "❌ Email invalide ou mot de passe trop court.";
              elseif ($_GET['err']==='self') echo "❌ Vous ne pouvez pas vous supprimer.";
              elseif ($_GET['err']==='selfrole') echo "❌ Vous ne pouvez pas modifier votre propre rôle.";
              elseif ($_GET['err']==='badrole') echo "❌ Rôle invalide.";
              elseif ($_GET['err']==='notfound') echo "❌ Utilisateur introuvable.";
              else echo "❌ Erreur inconnue.";
            ?>
          </div>
        <?php endif; ?>

        <form class="form" method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="create_user">
          <div class="field"><label>Email</label><input type="email" name="email" required></div>
          <div class="field"><label>Mot de passe</label><input type="password" name="password" required></div>
          <div class="field">
            <label>Rôle</label>
            <select name="role">
              <option value="moderator">moderator</option>
              <option value="admin">admin</option>
            </select>
          </div>
          <button class="btn success">Créer</button>
        </form>
      </div>

      <div class="card-panel">
        <h2>Liste des utilisateurs</h2>
        <div class="table-responsive">
          <table class="table" id="usersTable">
            <thead><tr><th>ID</th><th>Email</th><th>Rôle</th><th>Créé le</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
              <tr data-user="<?= htmlspecialchars(mb_strtolower($u['email'])) ?>">
                <td>#<?= (int)$u['id'] ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td class="role-<?= htmlspecialchars($u['role']) ?>">
                  <?php if ($_SESSION['user']['role']==='admin'): ?>
                  <form method="post" style="display:inline-flex;align-items:center;gap:6px;">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <select name="role" style="padding:4px 8px;border-radius:6px;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.05);color:#fff;">
                      <option value="moderator" <?= $u['role']==='moderator'?'selected':'' ?>>moderator</option>
                      <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>admin</option>
                    </select>
                    <button class="btn xs">💾</button>
                  </form>
                  <?php else: ?>
                    <?= htmlspecialchars($u['role']) ?>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($u['created_at']) ?></td>
                <td style="text-align:right;">
                  <?php if ($_SESSION['user']['role']==='admin'): ?>
                  <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cet utilisateur ?');">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button class="btn xs danger">🗑️</button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <footer class="site-footer">
    <p>© <?= date('Y') ?> — Dashboard admin</p>
  </footer>
</div>

<div id="notifications"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="dashboard.js"></script>
<script>
/* Filtres entreprises (client) */
(() => {
  const search = document.getElementById('searchCards');
  const filter = document.getElementById('filterStatus');
  const rows = document.querySelectorAll('#cardsTable tbody tr');
  if (!search || !filter || !rows.length) return;
  function apply() {
    const q = (search.value||'').toLowerCase().trim();
    const st = filter.value;
    rows.forEach(tr=>{
      const okQ = !q || tr.dataset.name.includes(q) || tr.dataset.holder.includes(q);
      const okS = !st || tr.dataset.status===st;
      tr.style.display = okQ && okS ? '' : 'none';
    });
  }
  search.addEventListener('input', apply);
  filter.addEventListener('change', apply);
})();

/* Préremplir le formulaire entreprise en édition */
(() => {
  const btns = document.querySelectorAll('[data-edit]');
  const form = document.getElementById('cardForm');
  if (!btns.length || !form) return;
  btns.forEach(b=>{
    b.addEventListener('click', ()=>{
      const data = JSON.parse(b.getAttribute('data-edit'));
      form.querySelector('#formAction').value = 'update_card';
      form.querySelector('#formId').value = data.id;
      form.querySelector('#name').value = data.name || '';
      form.querySelector('#link').value = data.link || '';
      form.querySelector('#status').value = data.status || 'open';
      form.querySelector('#imageUrl').value = data.image_url || '';
      form.querySelector('#holder').value = data.holder_discord || '';
      const ill = form.querySelector('#is_illegal');
      const grp = form.querySelector('#illegal_group');
      if (ill) ill.checked = !!(+data.is_illegal);
      if (grp) grp.value = data.illegal_group || '';
      // preview
      const prev = document.getElementById('preview');
      if (prev) {
        if (data.image_url) { prev.src = data.image_url; prev.style.display='block'; }
        else { prev.removeAttribute('src'); prev.style.display='none'; }
      }
      window.scrollTo({top:0, behavior:'smooth'});
    });
  });
  const resetBtn = document.getElementById('resetForm');
  if (resetBtn) resetBtn.addEventListener('click', ()=>{
    form.reset();
    form.querySelector('#formAction').value = 'create_card';
    form.querySelector('#formId').value = '';
    const prev = document.getElementById('preview');
    if (prev) { prev.removeAttribute('src'); prev.style.display='none'; }
  });
})();

/* Charts (Stats) */
(() => {
  if (!window.__CHART_DATA__) return;
  const pieCtx = document.getElementById('chartPie');
  const lineCtx = document.getElementById('chartLine');
  if (pieCtx) {
    const { open, closed } = window.__CHART_DATA__.pie;
    new Chart(pieCtx, {
      type: 'doughnut',
      data: {
        labels: ['Ouvert', 'Fermé'],
        datasets: [{ data:[open,closed] }]
      }
    });
  }
  if (lineCtx) {
    const { labels, values } = window.__CHART_DATA__.line;
    new Chart(lineCtx, {
      type: 'line',
      data: { labels, datasets: [{ label:'Créations par mois', data: values, tension:.35, fill:false }] }
    });
  }
})();
</script>
</body>
</html>
