<?php
require __DIR__ . '/config.php';
require_login();
require_role(['admin','moderator']);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

// Filtres
$where = '1=1';
$params = [];
if (!empty($_GET['action'])) {
  $where .= ' AND l.action LIKE ?';
  $params[] = '%' . $_GET['action'] . '%';
}
if (!empty($_GET['user'])) {
  $where .= ' AND u.email LIKE ?';
  $params[] = '%' . $_GET['user'] . '%';
}

// Export CSV
if (isset($_GET['export'])) {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="logs_export.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Date', 'Utilisateur', 'Action', 'Meta']);
  $stmt = $pdo->prepare("SELECT l.*, u.email FROM website_society_logs l LEFT JOIN website_society_users u ON u.id=l.user_id WHERE $where ORDER BY l.created_at DESC");
  $stmt->execute($params);
  foreach ($stmt as $row) {
    fputcsv($out, [$row['created_at'], $row['email'], $row['action'], $row['meta']]);
  }
  fclose($out);
  send_webhook('Export logs', "📦 Export CSV effectué par **{$_SESSION['user']['email']}**", 7506394);
  exit;
}

// Comptage total
$total = $pdo->prepare("SELECT COUNT(*) FROM website_society_logs l LEFT JOIN website_society_users u ON u.id=l.user_id WHERE $where");
$total->execute($params);
$totalRows = (int)$total->fetchColumn();
$totalPages = max(1, ceil($totalRows / $limit));

// Logs paginés
$stmt = $pdo->prepare("SELECT l.*, u.email FROM website_society_logs l LEFT JOIN website_society_users u ON u.id=l.user_id WHERE $where ORDER BY l.created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Logs — Annuaire</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>
<body class="theme-dark">
<div class="container-xl">
  <header class="site-header">
    <div class="brand">
      <div class="logo">🧾</div>
      <h1>Logs système</h1>
    </div>
    <div class="header-actions">
      <a href="admin.php" class="btn ghost">← Retour admin</a>
    </div>
  </header>

  <section class="card-panel">
    <form method="get" class="form" style="margin-bottom:20px;">
      <div class="field">
        <label>Filtrer par action :</label>
        <input type="text" name="action" value="<?= htmlspecialchars($_GET['action'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Filtrer par utilisateur :</label>
        <input type="text" name="user" value="<?= htmlspecialchars($_GET['user'] ?? '') ?>">
      </div>
      <div class="actions">
        <button class="btn">Filtrer</button>
        <a href="logs.php" class="btn ghost">Réinitialiser</a>
        <a href="?export=1" class="btn success">Exporter CSV</a>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Meta</th></tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $l): ?>
            <tr>
              <td><?= htmlspecialchars($l['created_at']) ?></td>
              <td><?= htmlspecialchars($l['email'] ?? '—') ?></td>
              <td><?= htmlspecialchars($l['action']) ?></td>
              <td><code><?= htmlspecialchars($l['meta']) ?></code></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
      <nav class="pagination" style="margin-top:16px;display:flex;gap:8px;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a class="btn xs <?= $i === $page ? 'active' : 'ghost' ?>" href="?page=<?= $i ?>&<?= http_build_query(array_diff_key($_GET,['page'=>1])) ?>"><?= $i ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  </section>
</div>
</body>
</html>
