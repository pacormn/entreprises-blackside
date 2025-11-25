<?php
require __DIR__.'/config.php';

$cards = $pdo->query("SELECT * FROM website_society_cards WHERE is_illegal = 1 ORDER BY created_at DESC")->fetchAll();
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Groupes illégaux — ArkaziaRP</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
	  <link rel="shortcut icon" href="https://r2.fivemanage.com/9L3GkUqw3LqkDP21YkuCi/logoFlashnight.png" type="image/png">
  <link rel="icon" type="image/png" href="https://r2.fivemanage.com/9L3GkUqw3LqkDP21YkuCi/logoFlashnight.png">
  <link href="style.css" rel="stylesheet">
  <style>
    body {
      background: #0f0f10;
      color: #fff;
      font-family: "Inter", sans-serif;
      margin: 0;
      overflow-x: hidden;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    header {
      text-align: center;
      padding: 50px 20px 20px;
      position: relative;
      max-width: 900px;
    }

    header h1 {
      font-size: 2.2rem;
      margin-bottom: 8px;
    }

    header p {
      opacity: 0.8;
      margin-bottom: 20px;
      font-size: 1rem;
    }

    a.btn {
      display: inline-block;
      padding: 10px 18px;
      border-radius: 10px;
      background: rgba(255, 255, 255, 0.08);
      color: #fff;
      text-decoration: none;
      border: 1px solid rgba(255, 255, 255, 0.1);
      transition: background 0.25s ease, transform 0.25s ease;
    }

    a.btn:hover {
      background: rgba(255, 255, 255, 0.15);
      transform: translateY(-2px);
    }

    /* --- Grille --- */
    .cards-grid {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 35px;
      padding: 40px 20px 80px;
      max-width: 1300px;
      width: 100%;
    }

    /* --- Cartes --- */
    .card {
      opacity: 0;
      transform: translateY(15px);
      background: linear-gradient(180deg, rgba(255,255,255,0.08), rgba(255,255,255,0.03));
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 18px;
      overflow: hidden;
      width: 340px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.4);
      transition: opacity 1.2s ease-out, transform 1.2s ease-out, background 0.3s ease, box-shadow 0.3s ease;
      position: relative;
    }

    .card.visible {
      opacity: 1;
      transform: translateY(0);
    }

    .card:hover {
      background: linear-gradient(180deg, rgba(255,255,255,0.12), rgba(255,255,255,0.05));
      box-shadow: 0 10px 25px rgba(0,0,0,0.5);
    }

    .card img {
      width: 100%;
      height: 200px;
      object-fit: cover;
    }

    .card-content {
      padding: 18px 20px;
      text-align: center;
    }

    .card-content h3 {
      font-size: 1.3rem;
      margin: 0 0 8px;
      color: #fff;
      font-weight: 600;
    }

    .status {
      font-size: 0.95rem;
      opacity: 0.9;
      margin-bottom: 6px;
    }

    .holder {
      font-size: 0.9rem;
      color: #bbb;
    }

    footer {
      text-align: center;
      opacity: 0.6;
      font-size: 0.9rem;
      padding: 25px 0 40px;
      width: 100%;
    }

    /* --- Animation halo subtil --- */
    .card::before {
      content: "";
      position: absolute;
      top: -30%;
      left: -30%;
      width: 160%;
      height: 160%;
      background: radial-gradient(circle at center, rgba(255,0,128,0.05), transparent 70%);
      filter: blur(50px);
      opacity: 0;
      transition: opacity 0.4s ease;
      z-index: 0;
    }

    .card:hover::before {
      opacity: 1;
      animation: haloMove 6s ease-in-out infinite alternate;
    }

    @keyframes haloMove {
      0% { transform: translate(-10%, -10%) scale(1); }
      100% { transform: translate(10%, 10%) scale(1.1); }
    }
  </style>
</head>
<body>
  <header>
    <h1>⚠️ Groupes illégaux</h1>
    <p>Liste des entreprises marquées comme illégales dans ArkaziaRP</p>
    <a href="index.php" class="btn ghost">↩️ Retour à l’annuaire</a>
  </header>

  <section class="cards-grid">
    <?php if (empty($cards)): ?>
      <p style="text-align:center;opacity:0.7;width:100%;">Aucune entreprise illégale enregistrée.</p>
    <?php else: ?>
      <?php foreach ($cards as $c): ?>
      <div class="card">
        <?php if ($c['image_url']): ?>
          <img src="<?= htmlspecialchars($c['image_url']) ?>" alt="">
        <?php endif; ?>
        <div class="card-content">
          <h3><?= htmlspecialchars($c['name']) ?></h3>
          <p class="status"><?= $c['status']==='open' ? '🟢 Disponible' : '🔴 Occupé' ?></p>
		  <?php if (!empty($c['holder_discord']) && $c['status']==='closed'): ?>
          <p class="holder-tag">👤 <?= htmlspecialchars($c['holder_discord'] ?: '—') ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <footer>© <?= date('Y') ?> — ArkaziaRP</footer>

  <script>
  // Apparition fluide au scroll (fade-in)
  const cards = document.querySelectorAll('.card');
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });
  cards.forEach(card => observer.observe(card));
  </script>
</body>
</html>
