<?php

declare(strict_types=1);

// ──────────────────────────────────────────────────────────────────
// MiniTrain – index.php  (point d'entrée)
//
//  1. Charge l'autoloader (PSR-4 : MiniTrain\ → src/)
//  2. Valide la difficulté reçue en GET
//  3. Génère la carte via MapGenerator
//  4. Injecte le JSON dans la vue HTML
// ──────────────────────────────────────────────────────────────────

require_once __DIR__ . '/autoload.php';

use MiniTrain\Generator\MapGenerator;
use MiniTrain\Model\DifficultyConfig;

// Récupération et validation de la difficulté
$level = $_GET['diff'] ?? 'medium';
if (!DifficultyConfig::isValid($level)) {
    $level = 'medium';
}

// Génération de la carte (PHP OOP)
$cfg       = new DifficultyConfig($level);
$generator = new MapGenerator($cfg);
$mapData   = $generator->generate();
$mapJson   = json_encode($mapData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

// Labels d'affichage
$diffLabel = match ($level) {
    'easy'  => '🟢 Facile',
    'hard'  => '🔴 Difficile',
    default => '🟡 Moyen',
};

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚂 MiniTrain – L2 Info 2024/2025</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>

<h1>🚂 MiniTrain</h1>

<!-- ── HUD ──────────────────────────────────────────────────────── -->
<div id="hud">
    <div class="hud-item">
        <span class="hud-label">Score</span>
        <span class="hud-val" id="h-score">0</span>
    </div>
    <div class="hud-item">
        <span class="hud-label">Restants</span>
        <span class="hud-val" id="h-rem"><?= count($mapData['queue']) ?></span>
    </div>
    <div class="hud-item">
        <span class="hud-label">En route</span>
        <span class="hud-val" id="h-on">0</span>
    </div>
    <div class="hud-item">
        <span class="hud-label">Difficulté</span>
        <span class="hud-val" style="font-size:.85rem;margin-top:3px">
            <?= htmlspecialchars($diffLabel) ?>
        </span>
    </div>
</div>

<!-- ── Contrôles ─────────────────────────────────────────────────── -->
<div id="controls">
    <form method="get" style="display:contents">
        <select name="diff" onchange="this.form.submit()">
            <option value="easy"   <?= $level === 'easy'   ? 'selected' : '' ?>>🟢 Facile</option>
            <option value="medium" <?= $level === 'medium'  ? 'selected' : '' ?>>🟡 Moyen</option>
            <option value="hard"   <?= $level === 'hard'    ? 'selected' : '' ?>>🔴 Difficile</option>
        </select>
        <button type="submit">🔀 Nouvelle carte</button>
    </form>
    <button id="btn-start">▶ Démarrer</button>
    <button id="btn-pause">⏸ Pause</button>
</div>

<!-- ── Canvas ────────────────────────────────────────────────────── -->
<div id="wrap">
    <canvas id="c"></canvas>
    <div id="overlay">
        <div id="ov-title">MiniTrain 🚂</div>
        <div id="ov-sub">
            Les trains sortent du <strong style="color:#ce93d8">tunnel à gauche</strong> un par un.<br>
            Cliquez sur les <strong style="color:#ffee58">jonctions</strong> pour les aiguiller.<br>
            ✅ Bonne gare = <strong style="color:#c8ff80">+1 point</strong> &nbsp;|&nbsp; ❌ Mauvaise gare = <strong style="color:#ef5350">0 point</strong>
        </div>
        <div id="ov-score"></div>
        <button id="btn-ov-start">▶ Commencer</button>
    </div>
</div>

<!-- ── Légende (générée par PHP) ─────────────────────────────────── -->
<div id="legend">
    <?php foreach ($mapData['stations'] as $st): ?>
        <div class="leg-item">
            <div class="leg-dot" style="
                background: <?= htmlspecialchars($st['color']['hex']) ?>;
                box-shadow: 0 0 5px <?= htmlspecialchars($st['color']['hex']) ?>99;
            "></div>
            <span><?= htmlspecialchars($st['color']['label']) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<p id="tip">Projet L2 Informatique 2024/2025 — PHP OOP + Canvas JS</p>

<!-- ── Injection PHP → JS ────────────────────────────────────────── -->
<script>
    // Seule communication PHP → JS : la config de la carte en JSON.
    // Le moteur de jeu (game.js) se débrouille entièrement avec ça.
    window.MAP_DATA = <?= $mapJson ?>;
</script>
<script src="public/js/game.js"></script>

</body>
</html>
