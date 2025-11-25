<?php
require __DIR__ . '/config.php';
require_login();
require_role(['admin','moderator']);

$csrf = csrf_token();

// Récupère la liste des groupes illégaux et leur nombre
$stmt = $pdo->query("
  SELECT illegal_group, COUNT(*) as cnt
  FROM website_society_cards
  WHERE is_illegal = 1 AND illegal_group IS NOT NULL
  GROUP BY illegal_group
  ORDER BY cnt DESC, illegal_group ASC
");
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// option : si on demande un groupe particulier (via GET)
$selected = $_GET['group'] ?? null;
$cards_in_group = [];
if ($selected) {
    $q = $pdo->prepare("SELECT * FROM website_society_cards WHERE is_illegal=1 AND illegal_group = ? ORDER BY name ASC");
    $q->execute([$selected]);
    $cards_in_group = $q->fetchAll(PDO::FETCH_ASSOC);
}

// Action rapide : désigner/retirer illégal pour une carte (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'remove_illegal' && !empty($_POST['id'])) {
        // sécurité : seulement admin
        require_role(['admin']);
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE website_society_cards SET is_illegal=0, illegal_group=NULL WHERE id=?")->execute([$id]);
        log_action($pdo, 'card_unmark_illegal', ['id'=>$id]);
        send_webhook('Carte désétiquetée illégale', "✅ Carte #{$id} retirée du statut illégal par {$_SESSION['user']['email']}");
        header('Location: admin_illegal.php?ok=1');
        exit;
    }
    if ($action === 'remove_group') {
        require_role(['admin']);
        $group = trim($_POST['group'] ?? '');
        if ($group !== '') {
            $pdo->prepare("UPDATE website_society_cards SET is_illegal=0, illegal_group=NULL WHERE illegal_group = ?")->execute([$group]);
            log_action($pdo, 'group_removed', ['group'=>$group]);
            send_webhook('Groupe illégal nettoyé', "✅ Toutes les cartes du groupe '{$group}' ont été retirées du statut illégal.");
            header('Location: admin_illegal.php?ok=1');
            exit;
        }
    }
}

?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Groupes illégaux — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css">
  <style>
    /* Styles spécifiques à cette page (ajoute dans dashboard.css si tu préfères) */
    .illegal-list { max-width:1100px; margin:18px auto; display:flex; flex-direction:column; gap:18px; }
    .illegal-group {
      background: var(--surface, #0f1724);
      border:1px solid rgba(255,80,80,0.08);
      padding:14px;
      border-radius:12px;
    }
    .illegal-group h3 { margin:0; font-size:1.05rem; display:flex; align-items:center; gap:10px; }
    .illegal-badge { background:linear-gradient(90deg,#ff8a80,#ff5252); padding:6px 10px; border-radius:999px; color:#fff; font-weight:700; }
    .group-actions { margin-left:auto; display:flex; gap:8px; align-items:center; }
    .group-grid { display:flex; gap:12px; margin-top:12px; flex-wrap:wrap; }
    .group-card {
      width:260px; background:var(--card-bg,#0f1622); border-radius:10px; overflow:hidden; border:1px solid rgba(255,255,255,0.03);
      box-shadow:0 8px 20px rgba(0,0,0,0.25);
    }
    .group-card img { width:100%; height:120px; object-fit:cover; display:block; }
    .group-card .body { padding:10px; color:var(--text,#eaeef5); }
    .small-quiet { color:#9aa3b2; font-size:0.9rem; }
    .danger-outline { border:1px solid rgba(255,80,80,0.25); background:rgba(255,80,80,0.03); color:#ffb3b3; }
  </style>
</head>
<body class="theme-dark">
  <div class="container-xl">
    <header class="site-header">
      <div class="brand"><div class="logo">⚠️</div><h1>Groupes illégaux <span class="muted">/ administration</span></h1></div>
      <div class="header-actions">
        <a href="admin_new.php" class="btn ghost">← Retour admin</a>
      </div>
    </header>

    <main class="illegal-list">
      <?php if (!empty($_GET['ok'])): ?>
        <div class="chip success">Action effectuée.</div>
      <?php endif; ?>

      <?php if (empty($groups)): ?>
        <div class="card-panel">Aucun groupe illégal n'est enregistré actuellement.</div>
      <?php endif; ?>

      <?php foreach ($groups as $g): ?>
        <?php $groupName = $g['illegal_group']; $count = (int)$g['cnt']; ?>
        <div class="illegal-group" data-group="<?= htmlspecialchars($groupName) ?>">
          <div style="display:flex;align-items:center;gap:12px">
            <h3>
              <span class="illegal-badge">⚠️</span>
              <span style="min-width:240px; display:inline-block;"><?= htmlspecialchars($groupName) ?></span>
              <span class="small-quiet"><?= $count ?> entreprise<?= $count>1?'s':'' ?></span>
            </h3>

            <div class="group-actions">
              <a class="btn ghost" href="admin_illegal.php?group=<?= urlencode($groupName) ?>">Voir</a>

              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="group" value="<?= htmlspecialchars($groupName) ?>">
                <input type="hidden" name="action" value="remove_group">
                <button class="btn danger-outline" onclick="return confirm('Retirer tout le groupe <?= htmlspecialchars(addslashes($groupName)) ?> ?');">Retirer groupe</button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if ($selected): ?>
        <section style="max-width:1100px;margin:6px auto 120px;">
          <h2>Cartes du groupe : <?= htmlspecialchars($selected) ?></h2>
          <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <?php foreach ($cards_in_group as $c): ?>
              <div class="group-card">
                <?php if (!empty($c['image_url'])): ?><img src="<?= htmlspecialchars($c['image_url']) ?>" alt=""><?php endif; ?>
                <div class="body">
                  <strong><?= htmlspecialchars($c['name']) ?></strong>
                  <div class="small-quiet">Statut : <?= htmlspecialchars($c['status']) ?></div>
                  <div style="margin-top:8px;display:flex;gap:8px;">
                    <a class="btn" href="admin_new.php?tab=entreprises#card-<?= (int)$c['id'] ?>">Éditer</a>
                    <form method="post" style="display:inline">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="action" value="remove_illegal">
                      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                      <button class="btn ghost" onclick="return confirm('Retirer le statut illégal pour <?= htmlspecialchars(addslashes($c['name'])) ?> ?');">Retirer illégal</button>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

    </main>
  </div>

  <script>
    // petit confort visuel : cliquer sur un groupe le met en surbrillance (front)
    document.querySelectorAll('.illegal-group').forEach(el=>{
      el.addEventListener('click', (e)=>{
        if (e.target.tagName.toLowerCase() === 'a' || e.target.closest('form')) return;
        const g = el.dataset.group;
        window.location = 'admin_illegal.php?group=' + encodeURIComponent(g);
      });
    });
  </script>
</body>
</html>
