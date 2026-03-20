<?php

declare(strict_types=1);

namespace MiniTrain\Model;

// ──────────────────────────────────────────────────────────────────
// class TrainSpec  –  spécification d'un train dans la file d'attente
//
//  Le PHP génère à l'avance toute la séquence des trains.
//  Le JS les fait apparaître un par un depuis le tunnel.
// ──────────────────────────────────────────────────────────────────
class TrainSpec
{
    public function __construct(
        public int          $index,
        public readonly Color $color,
        public readonly int   $tunnelNodeId,
    ) {}

    public function toArray(): array
    {
        return [
            'index'        => $this->index,
            'color'        => $this->color->toArray(),
            'tunnelNodeId' => $this->tunnelNodeId,
        ];
    }
}
