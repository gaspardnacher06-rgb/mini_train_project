<?php

declare(strict_types=1);

namespace MiniTrain\Model;

// ──────────────────────────────────────────────────────────────────
// class Palette  –  registre de toutes les couleurs disponibles
// ──────────────────────────────────────────────────────────────────
class Palette
{
    /** @return Color[] */
    public static function all(): array
    {
        return [
            new Color('blue',   '#4fc3f7', '#01579b', 'Bleu'),
            new Color('red',    '#ef5350', '#b71c1c', 'Rouge'),
            new Color('green',  '#66bb6a', '#1b5e20', 'Vert'),
            new Color('yellow', '#ffee58', '#c47f00', 'Jaune'),
            new Color('pink',   '#f48fb1', '#880e4f', 'Rose'),
            new Color('purple', '#ce93d8', '#4a148c', 'Violet'),
            new Color('orange', '#ffa726', '#e65100', 'Orange'),
        ];
    }

    /**
     * Retourne $n couleurs choisies aléatoirement dans la palette.
     * @return Color[]
     */
    public static function pick(int $n): array
    {
        $all = self::all();
        shuffle($all);
        return array_slice($all, 0, min($n, count($all)));
    }
}
