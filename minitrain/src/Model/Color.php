<?php

declare(strict_types=1);

namespace MiniTrain\Model;

// ──────────────────────────────────────────────────────────────────
// class Color  –  représente une couleur de train / gare
// ──────────────────────────────────────────────────────────────────
class Color
{
    public function __construct(
        public readonly string $id,
        public readonly string $hex,
        public readonly string $dark,
        public readonly string $label,
    ) {}

    public function toArray(): array
    {
        return [
            'id'    => $this->id,
            'hex'   => $this->hex,
            'dark'  => $this->dark,
            'label' => $this->label,
        ];
    }
}
