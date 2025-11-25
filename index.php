<?php
require __DIR__ . '/config.php';



/* === Webhook visite Discord === */
function send_visit_webhook($webhookUrl, $ip, $page, $ua = '') {
    $time = date('Y-m-d H:i:s');
    $embed = [
        "title" => "👀 Nouvelle visite sur le site BlackSide",
        "color" => 0x4FC219, // Violet
        "fields" => [
            ["name" => "Adresse IP", "value" => $ip, "inline" => true],
            ["name" => "Page", "value" => $page, "inline" => true],
            ["name" => "Heure", "value" => $time, "inline" => true],
            ["name" => "User-Agent", "value" => $ua ?: "Inconnu", "inline" => false],
        ],
        "footer" => ["text" => "BlackSide Web Tracker"],
        "timestamp" => date('c'),
    ];
    $payload = json_encode(["embeds" => [$embed]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // Envoi non bloquant (background)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        pclose(popen("start /B curl -s -X POST -H \"Content-Type: application/json\" -d \"" . addslashes($payload) . "\" \"$webhookUrl\"", "r"));
    } else {
        exec("curl -s -X POST -H 'Content-Type: application/json' -d " . escapeshellarg($payload) . " " . escapeshellarg($webhookUrl) . " > /dev/null 2>&1 &");
    }
}

/* === Collecte des infos visiteur === */
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Inconnu';
$page = $_SERVER['REQUEST_URI'] ?? 'index.php';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

send_visit_webhook(
    'https://discord.com/api/webhooks/1434910134412378211/5mTd4oCGFwMKVaNm-Y_COlshGQtRmdwWOcgxtUy9_hZFIeo6CBlKW-tzNvR3nucRRR-o',
    $ip,
    $page,
    $ua
);

/* --- Récupère uniquement les entreprises légales --- */
$stmt = $pdo->query("
    SELECT * 
    FROM website_society_cards 
    WHERE is_illegal = 0 OR is_illegal IS NULL 
    ORDER BY name ASC
");
$cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* --- Compte uniquement les entreprises légales --- */
$openCount = $pdo->query("
    SELECT COUNT(*) 
    FROM website_society_cards 
    WHERE (is_illegal = 0 OR is_illegal IS NULL) 
    AND status = 'open'
")->fetchColumn();

$closedCount = $pdo->query("
    SELECT COUNT(*) 
    FROM website_society_cards 
    WHERE (is_illegal = 0 OR is_illegal IS NULL) 
    AND status = 'closed'
")->fetchColumn();

$totalCount = $openCount + $closedCount;

/* --- Token CSRF --- */
$csrf = csrf_token();
?>

<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>BlackSide - Entreprises</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="shortcut icon" href="https://r2.fivemanage.com/9j3PjoE11r5xfrxVMAgLE/logo_alpha.png" type="image/png">
  <link rel="icon" type="image/png" href="https://r2.fivemanage.com/9j3PjoE11r5xfrxVMAgLE/logo_alpha.png">
	
  <meta property="og:title" content="🏙️ ArkaziaRP — Annuaire des entreprises">
  <meta property="og:description" content="Découvrez toutes les entreprises de FLashNight — commerces, services, sociétés ouvertes ou fermées. Consultez les disponibilités et les contacts Discord des responsables.">
  <meta property="og:image" content="https://r2.fivemanage.com/9j3PjoE11r5xfrxVMAgLE/logo_alpha.png">

  <link rel="stylesheet" href="style.css">
  <style>
  body.theme-dark {
    background: linear-gradient(180deg, #0a0d15 0%, #101726 35%, #1b2435 100%);
    color: #eaeef5;
    font-family: 'Inter', sans-serif;
  }
  body.theme-dark::before {
    content: '';
    position: fixed;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle at 30% 30%, rgba(82, 97, 255, 0.1), transparent 60%),
                radial-gradient(circle at 70% 70%, rgba(255, 0, 128, 0.05), transparent 60%);
    animation: gradientMove 12s infinite alternate;
    z-index: 0;
  }
  @keyframes gradientMove {
    0% { transform: translate(0,0) scale(1); }
    100% { transform: translate(10%,10%) scale(1.1); }
  }

  .page-wrapper, .site-header, .cards-section, .site-footer {
    position: relative;
    z-index: 1;
  }

  .page-wrapper {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }

  .site-header {
    padding: 20px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    backdrop-filter: blur(8px);
    background: rgba(16, 20, 30, 0.7);
    border-bottom: 1px solid rgba(255,255,255,0.05);
  }
  .brand {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .brand .logo { font-size: 1.8rem; }

  .intro {
    text-align: center;
    margin-top: 60px;
    margin-bottom: 40px;
    padding: 20px;
    color: #c8d2e1;
  }
  .intro h2 {
    font-weight: 600;
    font-size: 1.8rem;
    margin-bottom: 10px;
    color: #f3f5fb;
  }

  .cards-section {
    padding: 30px 40px 80px;
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    justify-content: center;
  }

  .card {
    background: var(--surface, #131a28);
    border-radius: 16px;
    overflow: hidden;
    width: 310px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.25);
    display: flex;
    flex-direction: column;
	 
  opacity: 0;
  transform: translateY(30px);
  transition: opacity 0.5s ease-out, transform 0.5s ease-out;
  }
  .card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.35);
  }
.card.visible {
  opacity: 1;
  transform: translateY(0);
}
  .card img {
    width: 100%;
    height: 170px;
    object-fit: cover;
  }
  .card-body {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .status {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 10px;
    font-size: 0.85rem;
    color: #fff;
    margin-bottom: 4px;
  }
  .status.open { background: linear-gradient(90deg,#00c853,#00e676); }
  .status.closed { background: linear-gradient(90deg,#c62828,#e53935); }
  .holder-tag {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 8px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    color: var(--text, #eaeef5);
    font-size: 0.9rem;
    margin-top: 4px;
  }

  .card .btn {
    display: inline-block;
    padding: 8px 10px;
    margin-top: 8px;
    border-radius: 8px;
    background: #1d4ed8;
    color: #fff;
    font-size: 0.9rem;
    text-align: center;
    text-decoration: none;
    transition: background 0.2s;
  }
  .card .btn:hover { background: #2563eb; }

  .site-footer {
    margin-top: auto;
    padding: 20px;
    text-align: center;
    color: #999;
    font-size: 0.9rem;
    background: rgba(0, 0, 0, 0.2);
  }

  .filter-bar {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    margin-top: 20px;
    flex-wrap: wrap;
  }
  .filter-bar input {
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.05);
    color: #eaeef5;
    min-width: 200px;
  }
  .filter-bar select {
    padding: 10px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.05);
    color: #eaeef5;
  }

  </style>
</head>
<body class="theme-dark">
  <div class="page-wrapper">
    <header class="site-header">
      <div class="brand">
<div class="logo">
  <img src="https://r2.fivemanage.com/9j3PjoE11r5xfrxVMAgLE/logo_alpha.png" alt="BlackSide Logo">
</div>
<h1>BlackSide <span class="muted">/ Annuaire des entreprises</span></h1>

      </div>
      <div class="user-controls">
		  <a href="groupes_illegaux.php" class="btn ghost" style="margin-left: 10px;">⚠️ Groupes illégaux</a>
        <?php if (!empty($_SESSION['user'])): ?>

          <a href="admin_new.php" class="btn">Admin</a>
          <a href="logout.php" class="btn ghost">Se déconnecter</a>
        <?php else: ?>
          <a href="login.php" class="btn ghost">Connexion</a>
        <?php endif; ?>
      </div>
    </header>

    <section class="intro">
      <h2>📍 Découvrez les entreprises de la ville</h2>
      <p>Consultez la disponibilité, les tenues et les contacts Discord des responsables.</p>
		
<!-- === Compteurs dynamiques animés === -->
<div class="stats-wrapper">
  <div class="stats-badges" id="statsBadges">
    <span class="badge-open" id="badgeOpen" data-target="<?= $openCount ?>">0 entreprises disponibles</span>
    <span class="badge-closed" id="badgeClosed" data-target="<?= $closedCount ?>">0 entreprises occupées</span>
  </div>
</div>


<script>
document.addEventListener("DOMContentLoaded", () => {
  const counters = [
    { el: document.getElementById("badgeOpen"), label: "entreprises disponibles" },
    { el: document.getElementById("badgeClosed"), label: "entreprises occupées" }
  ];

  counters.forEach(({ el, label }) => {
    const target = +el.getAttribute("data-target");
    let current = 0;
    const duration = 1000;
    const steps = 30;
    const increment = target / steps;
    const interval = duration / steps;

    const timer = setInterval(() => {
      current += increment;
      if (current >= target) {
        current = target;
        clearInterval(timer);
      }
      el.textContent = Math.floor(current) + " " + label;
    }, interval);
  });
});
</script>

		
      <div class="filter-bar">
        <input type="search" id="search" placeholder="Rechercher...">
		  
        <select id="filter">
          <option value="">Afficher : Tous</option>
          <option value="open">Disponibles</option>
          <option value="closed">Non Disponibles</option>
        </select>

      </div>
    </section>

    <section class="cards-section" id="cardsSection">
      <?php foreach ($cards as $c): ?>
        <div class="card" data-name="<?= htmlspecialchars(mb_strtolower($c['name'])) ?>" data-status="<?= htmlspecialchars($c['status']) ?>">
          <?php if ($c['image_url']): ?>
            <img src="<?= htmlspecialchars($c['image_url']) ?>" alt="<?= htmlspecialchars($c['name']) ?>">
          <?php endif; ?>
          <div class="card-body">
            <span class="status <?= htmlspecialchars($c['status']) ?>">
              <?= $c['status']==='open'?'Disponible':'Non disponible' ?>
            </span>
            <h3><?= htmlspecialchars($c['name']) ?> </h3>
            <?php if (!empty($c['holder_discord']) && $c['status']==='closed'): ?>
              <span class="holder-tag">👤 <?= htmlspecialchars($c['holder_discord']) ?></span>
            <?php endif; ?>
            <?php if (!empty($c['link'])): ?>
              <a href="<?= htmlspecialchars($c['link']) ?>" target="_blank" class="btn discord-btn">
  <i class="fa-brands fa-discord"></i> Discord
</a>

            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </section>

    <footer class="site-footer">
      <p>© <?= date('Y') ?> — Tout droits réservés - By @LePacss - Blackside FA</p>
    </footer>
  </div>

  <script>
  // Filtre dynamique
  const searchInput = document.getElementById('search');
  const filterSelect = document.getElementById('filter');
  const cards = document.querySelectorAll('.card');
  function applyFilters(){
    const q = (searchInput.value||'').toLowerCase().trim();
    const f = filterSelect.value;
    cards.forEach(c=>{
      const matchQ = !q || c.dataset.name.includes(q);
      const matchF = !f || c.dataset.status===f;
      c.style.display = (matchQ && matchF) ? '' : 'none';
    });
  }
  searchInput.addEventListener('input', applyFilters);
  filterSelect.addEventListener('change', applyFilters);
  </script>
	
	<script>
// Apparition fluide au scroll
document.addEventListener("DOMContentLoaded", () => {
  const cards = document.querySelectorAll(".card");

  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add("visible");
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.2 });

  cards.forEach(card => observer.observe(card));
});
</script>

</body>
</html>
