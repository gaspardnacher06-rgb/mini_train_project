<?php

declare(strict_types=1);

namespace MiniTrain\Generator;

use MiniTrain\Model\{Color, DifficultyConfig, MapNode, Palette, Station, TrainSpec};

// ──────────────────────────────────────────────────────────────────
// class MapGenerator  –  génération procédurale du réseau ferroviaire
//
//  Topologie : réseau en ARBRE depuis un tunnel unique (gauche).
//  Structure garantissant zéro croisement et zéro collision :
//
//    [TUNNEL] ──── tronc horizontal ──┬── branche ↑ gare haut
//                                     ├── branche ↓ gare bas
//                                     ├── branche ↑ gare haut
//                                     └── ...
//
//  Chaque branche contient une jonction intermédiaire cliquable
//  (entre le tronc et la gare) permettant au joueur d'aiguiller
//  le train vers la gare ou de le laisser continuer sur le tronc.
//
//  Un seul train circule à la fois → pas de collision possible.
// ──────────────────────────────────────────────────────────────────
class MapGenerator
{
    // Dimensions du canvas JS (pixels)
    private const CANVAS_W = 770;
    private const CANVAS_H = 550;

    // Géométrie du réseau
    private const MID_Y   = 275.0;  // Y du tronc central
    private const TOP_Y   = 65.0;   // Y des terminus du haut
    private const BOT_Y   = 485.0;  // Y des terminus du bas
    private const JT_Y    = 155.0;  // Y des jonctions devant gares du haut
    private const JB_Y    = 395.0;  // Y des jonctions devant gares du bas
    private const COL_GAP = 110.0;  // Espacement horizontal entre colonnes
    private const START_X = 160.0;  // X de la première colonne

    /** @var MapNode[] */
    private array $nodes = [];

    /** @var Station[] */
    private array $stations = [];

    private int $nodeCounter = 0;

    public function __construct(private readonly DifficultyConfig $cfg) {}

    // ── API publique ───────────────────────────────────────────────

    /**
     * Génère la carte complète et retourne un tableau sérialisable en JSON.
     */
    public function generate(): array
    {
        $this->nodes      = [];
        $this->stations   = [];
        $this->nodeCounter = 0;

        $numC   = $this->cfg->numColors;
        $colors = Palette::pick($numC);
        $colXs  = $this->buildColumnXs($numC);

        // 1. Tunnel unique (point de spawn)
        $tunnelNode = $this->addNode(50.0, self::MID_Y);
        $tunnelNode->isTunnel = true;

        // 2. Nœud de raccord tunnel → tronc
        $trunk0 = $this->addNode(self::START_X - self::COL_GAP / 2, self::MID_Y);
        $this->link($tunnelNode, $trunk0);

        // 3. Tronc principal
        $trunkNodes = $this->buildTrunk($trunk0, $colXs, $numC);

        // 4. Répartition haut / bas des gares
        [$topIdxs, $botIdxs, $topColors, $botColors] = $this->splitStations($numC, $colors);

        // 5. Création des gares et jonctions
        $junctionCols = [];

        foreach ($topIdxs as $ci => $colIdx) {
            $this->createStation($trunkNodes[$colIdx], $colXs[$colIdx], self::JT_Y, self::TOP_Y, $topColors[$ci], 'top');
            $junctionCols[] = $colIdx;
        }

        foreach ($botIdxs as $ci => $colIdx) {
            $this->createStation($trunkNodes[$colIdx], $colXs[$colIdx], self::JB_Y, self::BOT_Y, $botColors[$ci], 'bottom');
            $junctionCols[] = $colIdx;
        }

        // 6. Jonctions sur le tronc (aux bifurcations)
        foreach ($junctionCols as $colIdx) {
            if ($colIdx < $numC - 1) {
                $trunkNodes[$colIdx]->isJunction = true;
            }
        }

        // 7. File de trains
        $queue = $this->buildQueue($colors, $tunnelNode->id);

        return [
            'nodes'        => array_map(fn(MapNode $n)  => $n->toArray(),  $this->nodes),
            'stations'     => array_map(fn(Station $s)  => $s->toArray(),  $this->stations),
            'queue'        => array_map(fn(TrainSpec $t) => $t->toArray(), $queue),
            'tunnelNodeId' => $tunnelNode->id,
            'cfg'          => $this->cfg->toArray(),
        ];
    }

    // ── Méthodes privées ───────────────────────────────────────────

    /** Calcule les X de chaque colonne */
    private function buildColumnXs(int $numC): array
    {
        $xs = [];
        for ($i = 0; $i < $numC; $i++) {
            $xs[] = self::START_X + $i * self::COL_GAP;
        }
        return $xs;
    }

    /** Crée les nœuds du tronc et les relie en chaîne */
    private function buildTrunk(MapNode $trunk0, array $colXs, int $numC): array
    {
        $trunkNodes = [];
        for ($i = 0; $i < $numC; $i++) {
            $trunkNodes[] = $this->addNode($colXs[$i], self::MID_Y);
        }

        $this->link($trunk0, $trunkNodes[0]);
        for ($i = 0; $i < $numC - 1; $i++) {
            $this->link($trunkNodes[$i], $trunkNodes[$i + 1]);
        }

        return $trunkNodes;
    }

    /**
     * Répartit les indices de colonnes et les couleurs en haut / bas.
     * @return array [topIdxs[], botIdxs[], topColors[], botColors[]]
     */
    private function splitStations(int $numC, array $colors): array
    {
        $allIdxs = range(0, $numC - 1);
        shuffle($allIdxs);

        $topCount  = (int) ceil($numC / 2);
        $topIdxs   = array_slice($allIdxs, 0, $topCount);
        $botIdxs   = array_slice($allIdxs, $topCount);

        $topColors = array_slice($colors, 0, $topCount);
        $botColors = array_slice($colors, $topCount);

        return [$topIdxs, $botIdxs, $topColors, $botColors];
    }

    /**
     * Crée une jonction intermédiaire + un terminus pour une gare.
     * Relie : trunkNode ↔ junctionNode ↔ terminusNode
     */
    private function createStation(
        MapNode $trunkNode,
        float   $x,
        float   $junctionY,
        float   $terminusY,
        Color   $color,
        string  $side,
    ): void {
        static $stId = 0;

        // Jonction devant la gare (cliquable par le joueur)
        $jNode = $this->addNode($x, $junctionY);
        $jNode->isJunction     = true;
        $jNode->isStationEntry = true;

        // Terminus (gare, non cliquable)
        $stNode = $this->addNode($x, $terminusY);
        $stNode->isTerminus   = true;
        $stNode->stationColor = $color->toArray();

        $this->link($trunkNode, $jNode);
        $this->link($jNode, $stNode);

        $station = new Station($stId++, $stNode->id, $jNode->id, $color);
        $jNode->stationId = $station->id;
        $this->stations[] = $station;
    }

    /**
     * Génère la file de trains (mélangée) depuis les couleurs disponibles.
     * @return TrainSpec[]
     */
    private function buildQueue(array $colors, int $tunnelNodeId): array
    {
        $queue = [];
        $n     = $this->cfg->numTrains;

        for ($i = 0; $i < $n; $i++) {
            $color   = $colors[$i % count($colors)];
            $queue[] = new TrainSpec($i, $color, $tunnelNodeId);
        }

        shuffle($queue);
        foreach ($queue as $k => $spec) {
            $spec->index = $k;
        }

        return $queue;
    }

    // ── Utilitaires nœuds ─────────────────────────────────────────

    private function addNode(float $x, float $y): MapNode
    {
        $n = new MapNode($this->nodeCounter++, $x, $y);
        $this->nodes[] = $n;
        return $n;
    }

    private function link(MapNode $a, MapNode $b): void
    {
        $a->connect($b->id);
        $b->connect($a->id);
    }
}
