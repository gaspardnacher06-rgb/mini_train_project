<?php

declare(strict_types=1);

namespace MiniTrain\Model;

// ──────────────────────────────────────────────────────────────────
// class MapNode  –  nœud du réseau ferroviaire
//
//  Un nœud peut être :
//    • un nœud normal (tronc, connexion)
//    • une jonction  (isJunction=true) → le joueur peut cliquer
//    • une jonction devant gare (isStationEntry=true)
//    • un tunnel     (isTunnel=true)  → point de spawn unique
//    • un terminus   (isTerminus=true)→ gare d'arrivée
// ──────────────────────────────────────────────────────────────────
class MapNode
{
    /** @var int[] IDs des nœuds voisins */
    public array  $connections    = [];

    public bool   $isJunction     = false;
    public int    $junctionState  = 0;     // index de la sortie active

    public bool   $isStationEntry = false; // jonction juste devant une gare
    public ?int   $stationId      = null;  // id Station liée

    public bool   $isTunnel       = false;
    public bool   $isTerminus     = false;
    public ?array $stationColor   = null;  // Color::toArray() si terminus

    public function __construct(
        public readonly int   $id,
        public readonly float $x,
        public readonly float $y,
    ) {}

    public function connect(int $nodeId): void
    {
        if (!in_array($nodeId, $this->connections, true)) {
            $this->connections[] = $nodeId;
        }
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'x'              => $this->x,
            'y'              => $this->y,
            'connections'    => $this->connections,
            'isJunction'     => $this->isJunction,
            'junctionState'  => $this->junctionState,
            'isStationEntry' => $this->isStationEntry,
            'stationId'      => $this->stationId,
            'isTunnel'       => $this->isTunnel,
            'isTerminus'     => $this->isTerminus,
            'stationColor'   => $this->stationColor,
        ];
    }
}
