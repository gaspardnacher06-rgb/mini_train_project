<?php

declare(strict_types=1);

namespace MiniTrain\Model;

// ──────────────────────────────────────────────────────────────────
// class Station  –  gare d'arrivée
//
//  Chaque gare est composée de deux nœuds :
//    • entryNodeId   → jonction cliquable juste avant la gare
//    • terminusNodeId→ nœud terminus (non cliquable, destination finale)
// ──────────────────────────────────────────────────────────────────
class Station
{
    public function __construct(
        public readonly int   $id,
        public readonly int   $terminusNodeId,
        public readonly int   $entryNodeId,
        public readonly Color $color,
    ) {}

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'terminusNodeId' => $this->terminusNodeId,
            'entryNodeId'    => $this->entryNodeId,
            'color'          => $this->color->toArray(),
        ];
    }
}
