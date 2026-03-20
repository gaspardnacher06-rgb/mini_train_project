<?php

declare(strict_types=1);

namespace MiniTrain\Model;

// ──────────────────────────────────────────────────────────────────
// class DifficultyConfig  –  paramètres de difficulté
//
//  Niveaux disponibles : 'easy' | 'medium' | 'hard'
//    numColors     → nombre de gares / couleurs différentes
//    spawnInterval → délai en ms entre deux trains (côté JS)
//    numTrains     → nombre total de trains dans la partie
//    trainSpeed    → vitesse de déplacement (px/frame à 60fps)
// ──────────────────────────────────────────────────────────────────
class DifficultyConfig
{
    public readonly int   $numColors;
    public readonly int   $spawnInterval;
    public readonly int   $numTrains;
    public readonly float $trainSpeed;

    private static array $presets = [
        'easy'   => ['numColors' => 3, 'spawnInterval' => 4000, 'numTrains' => 9,  'trainSpeed' => 1.0],
        'medium' => ['numColors' => 4, 'spawnInterval' => 2800, 'numTrains' => 12, 'trainSpeed' => 1.5],
        'hard'   => ['numColors' => 5, 'spawnInterval' => 1800, 'numTrains' => 16, 'trainSpeed' => 2.2],
    ];

    public function __construct(public readonly string $level)
    {
        $p = self::$presets[$level] ?? self::$presets['medium'];

        $this->numColors     = $p['numColors'];
        $this->spawnInterval = $p['spawnInterval'];
        $this->numTrains     = $p['numTrains'];
        $this->trainSpeed    = $p['trainSpeed'];
    }

    public static function isValid(string $level): bool
    {
        return isset(self::$presets[$level]);
    }

    public function toArray(): array
    {
        return [
            'level'         => $this->level,
            'numColors'     => $this->numColors,
            'spawnInterval' => $this->spawnInterval,
            'numTrains'     => $this->numTrains,
            'trainSpeed'    => $this->trainSpeed,
        ];
    }
}
