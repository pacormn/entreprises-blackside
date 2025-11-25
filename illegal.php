<?php
include("config.php");
$stmt = $pdo->query("SELECT * FROM society_illegal_aviabilities ORDER BY name ASC");
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Disponibilités • Groupes illégaux</title>
  <link rel="stylesheet" href="style.css?v=2">
</head>
<body class="theme-illegal">
  <div id="siteOverlay" class="site-overlay">
    <div class="overlay-content">
      <h1>Groupes illégaux</h1>
    </div>
  </div>
  <header class="site-header container-xl">
    <div class="brand">
      <div class="logo">⚠️</div>
      <div>
        <h1>Disponibilités des groupes illégaux</h1>
        <p class="muted">Vue publique en temps réel</p>
      </div>
    </div>
	<nav class="header-actions">
  		<a class="btn success" href="index.php">Entreprises</a>
		
  		<a class="btn ghost" href="admin.php">Espace admin →</a>
	</nav>
  </header>

  <section class="toolbar container-xl">
    <div class="controls">
      <input id="search" type="search" placeholder="Rechercher une société… (Ctrl + K)" autocomplete="off" />
      <button id="toggleOpen" class="btn">Afficher: Tous</button>
      <div class="seg">
        <button id="sortAZ" class="seg-btn active" aria-pressed="true">A→Z</button>
        <button id="sortZA" class="seg-btn" aria-pressed="false">Z→A</button>
        <button id="sortStatus" class="seg-btn" aria-pressed="false">Par statut</button>
      </div>
      <div class="seg">
        <button id="viewGrid" class="seg-btn active" title="Grille">▦</button>
        <button id="viewList" class="seg-btn" title="Liste">≣</button>
      </div>
    </div>
    <div class="stats">
      <span id="countAll" class="chip">0 total</span>
      <span id="countOpen" class="chip success">0 ouverts</span>
      <span id="countClosed" class="chip danger">0 fermés</span>
    </div>
  </section>

  <main class="container-xl">
    <div id="cards" class="cards grid-view" role="list">
      <?php foreach ($companies as $c): ?>
        <?php
          $name = htmlspecialchars($c['name']);
          $status = (int)$c['avaiable'] === 1 ? 'open' : 'closed';
          $statusLabel = $status === 'open' ? '📂Reprise sur dossier' : 'Recrutements ouverts ✅';
          $img = $c['image'] ?: '';
		$discord = !empty($c['discord']) ? htmlspecialchars($c['discord']) : null;

        ?>
        <article class="card" role="listitem" data-name="<?= $name ?>" data-status="<?= $status ?>">
          <div class="thumb" style="--bg:url('<?= htmlspecialchars($img) ?>')">
            <?php if ($img): ?>
              <img src="<?= htmlspecialchars($img) ?>" alt="Illustration <?= $name ?>" loading="lazy" onerror="this.closest('.thumb').classList.add('fallback')"/>
            <?php else: ?>
              <div class="noimg">Aucune image</div>
            <?php endif; ?>
            <span class="badge <?= $status ?>"><?= $status === 'open' ? 'Disponible' : 'Non Disponible' ?></span>
          </div>
          <div class="content">
            <h2 class="title"><?= $name ?></h2>
            <p class="status <?= $status ?>"><?= $statusLabel ?></p>
<div class="actions">
  <?php if ($discord): ?>
    <a href="<?= $discord ?>" target="_blank" class="btn ghost">Serveur Discord</a>
  <?php endif; ?>
</div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </main>

  <!-- Modal preview -->
  <dialog id="previewModal">
    <button class="modal-close" autofocus aria-label="Fermer">✕</button>
    <img id="previewImg" alt="Aperçu" />
  </dialog>

  <footer class="site-footer container-xl">
    <p class="muted">© <?= date('Y') ?> – ArkaziaRoleplay 2025. Données fournies par l’équipe staff.</p>
  </footer>

  <script src="app.js?v=2"></script>
  <script>
    window.addEventListener('DOMContentLoaded', () => {
      setTimeout(() => {
        document.getElementById('siteOverlay').classList.add('hide');
      }, 800); // Durée de l'animation : 2 secondes
    });
  </script>
</body>
</html>