<?php
require __DIR__.'/config.php';
require_login();
require_role(['admin','moderator']);

// CREATE / UPDATE / DELETE
if ($_SERVER['REQUEST_METHOD']==='POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  if ($action === 'create' || $action === 'update') {
    $name   = trim($_POST['name'] ?? '');
    $link   = trim($_POST['link'] ?? '');
    $status = in_array($_POST['status'] ?? 'open', ['open','closed'], true) ? $_POST['status'] : 'open';
    $imgUrl = trim($_POST['image_url'] ?? '');
    $holder = trim($_POST['holder_discord'] ?? '');

    // Upload fichier (optionnel)
    if (!empty($_FILES['image_file']['name'])) {
      $dir = __DIR__.'/uploads';
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

    if ($action === 'create') {
      $stmt = $pdo->prepare("INSERT INTO website_society_cards(name, link, status, image_url, holder_discord) VALUES (?,?,?,?,?)");
      $stmt->execute([$name, $link, $status, $imgUrl, $holder]);
      log_action($pdo, 'card_create', compact('name','status','holder'));
      send_webhook('Carte créée', "➕ **$name** ($status)", 5763719, [
        ['name'=>'Holder', 'value'=>$holder ?: '—'],
        ['name'=>'URL', 'value'=>$link ?: '—']
      ]);
    } else {
      $id = (int)$_POST['id'];
      $stmt = $pdo->prepare("UPDATE website_society_cards SET name=?, link=?, status=?, image_url=?, holder_discord=? WHERE id=?");
      $stmt->execute([$name, $link, $status, $imgUrl, $holder, $id]);
      log_action($pdo, 'card_update', compact('id','name','status','holder'));
      send_webhook('Carte modifiée', "✏️ **$name** ($status)", 15844367, [
        ['name'=>'ID', 'value'=>strval($id)],
        ['name'=>'Holder', 'value'=>$holder ?: '—']
      ]);
    }

    header('Location: admin.php?ok=1'); exit;
  }

  if ($action === 'delete' && $_SESSION['user']['role']==='admin') {
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM website_society_cards WHERE id=?");
    $stmt->execute([$id]);
    log_action($pdo, 'card_delete', ['id'=>$id]);
    send_webhook('Carte supprimée', "🗑️ ID **$id**", 15548997);
    header('Location: admin.php?ok=1'); exit;
  }
}

// Récup cartes
$cards = $pdo->query("SELECT * FROM website_society_cards ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Stats dashboard
$totalUsers = $pdo->query("SELECT COUNT(*) AS c FROM website_society_users")->fetch()['c'] ?? 0;
$totalCards = $pdo->query("SELECT COUNT(*) AS c FROM website_society_cards")->fetch()['c'] ?? 0;
$totalOpen  = $pdo->query("SELECT COUNT(*) AS c FROM website_society_cards WHERE status='open'")->fetch()['c'] ?? 0;
$totalClosed= $pdo->query("SELECT COUNT(*) AS c FROM website_society_cards WHERE status='closed'")->fetch()['c'] ?? 0;
$latestLogs = $pdo->query("SELECT l.*, u.email FROM website_society_logs l LEFT JOIN website_society_users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 8")->fetchAll();

$csrf = csrf_token();
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Admin — Annuaire</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="style.css" rel="stylesheet">
</head>
<body class="theme-dark">
  <div class="container-xl">
    <header class="site-header">
      <div class="brand">
        <div class="logo">🛠️</div>
        <h1>Admin <span class="muted">/ dashboard & gestion</span></h1>
      </div>
      <div class="header-actions">
        <a href="index.php" class="btn ghost">← Retour</a>
        <button id="toggleTheme" class="btn ghost">🌓</button>
      </div>
    </header>

    <!-- ===== Dashboard ===== -->
    <section class="cards grid-view" style="margin: 10px 0 24px;">
      <div class="card-panel">
        <h3>Total utilisateurs</h3>
        <p class="title" style="margin-top:6px;font-size:1.6rem;"><?= (int)$totalUsers ?></p>
      </div>
      <div class="card-panel">
        <h3>Total entreprises</h3>
        <p class="title" style="margin-top:6px;font-size:1.6rem;"><?= (int)$totalCards ?></p>
      </div>
      <div class="card-panel">
        <h3>Disponibles</h3>
        <p class="title" style="margin-top:6px;font-size:1.6rem;"><?= (int)$totalOpen ?></p>
      </div>
      <div class="card-panel">
        <h3>Non disponibles</h3>
        <p class="title" style="margin-top:6px;font-size:1.6rem;"><?= (int)$totalClosed ?></p>
      </div>
    </section>

    <div class="admin-grid">
      <!-- ===== Form CRUD ===== -->
      <section class="card-panel">
        <h2>Ajouter / Modifier</h2>
        <form class="form" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="create" id="formAction">
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
            <p class="hint">Si l’entreprise est <b>non disponible</b>, ce handle s’affiche sur la carte via un bouton.</p>
          </div>
          <div class="actions">
            <button class="btn success" type="submit">Enregistrer</button>
            <button class="btn ghost" type="button" id="resetForm">Réinitialiser</button>
          </div>
        </form>
      </section>

      <!-- ===== Liste + Logs ===== -->
      <section class="card-panel">
        <h2>Liste des entreprises</h2>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>ID</th><th>Nom</th><th>Statut</th><th>Holder</th><th>Image</th><th>Actions</th>
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
                  <td class="row-actions">
                    <button class="btn xs" data-edit='<?= json_encode($c, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>'>Modifier</button>
                    <?php if ($_SESSION['user']['role']==='admin'): ?>
                    <form method="post" onsubmit="return confirm('Supprimer ?');" style="display:inline">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                      <button class="btn xs danger" type="submit">Supprimer</button>
                    </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <h2 style="margin-top:24px;">Derniers logs</h2>
        <div class="table-responsive">
          <table class="table">
            <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Meta</th></tr></thead>
            <tbody>
              <?php foreach ($latestLogs as $log): ?>
              <tr>
                <td><?= htmlspecialchars($log['created_at']) ?></td>
                <td><?= htmlspecialchars($log['email'] ?? '—') ?></td>
                <td><?= htmlspecialchars($log['action'] ?? '') ?></td>
                <td><code><?= htmlspecialchars($log['meta'] ?? '') ?></code></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>

  <div id="notifications"></div>
  <script src="app.js"></script>
  <script>
    // Remplir formulaire pour édition
    document.querySelectorAll('[data-edit]').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const data = JSON.parse(btn.getAttribute('data-edit'));
        document.getElementById('formAction').value = 'update';
        document.getElementById('formId').value = data.id;
        document.getElementById('name').value = data.name || '';
        document.getElementById('link').value = data.link || '';
        document.getElementById('status').value = data.status || 'open';
        document.getElementById('imageUrl').value = data.image_url || '';
        document.getElementById('holder').value = data.holder_discord || '';
        showToast('Mode édition activé', 'info');
        const e = new Event('input'); document.getElementById('imageUrl').dispatchEvent(e);
      });
    });
    // Reset
    document.getElementById('resetForm').addEventListener('click', ()=>{
      document.getElementById('formAction').value = 'create';
      document.getElementById('formId').value = '';
      document.querySelector('.form').reset();
      showToast('Formulaire réinitialisé', 'info');
      const e = new Event('input'); document.getElementById('imageUrl').dispatchEvent(e);
    });
  </script>
</body>
</html>
