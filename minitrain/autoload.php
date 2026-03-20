<?php

declare(strict_types=1);

// ──────────────────────────────────────────────────────────────────
// Autoloader PSR-4 simple (sans Composer)
//
//  Namespace MiniTrain\ → src/
//
//  En production, remplacer par : require 'vendor/autoload.php'
//  après avoir ajouté dans composer.json :
//    "autoload": { "psr-4": { "MiniTrain\\": "src/" } }
// ──────────────────────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $prefix = 'MiniTrain\\';
    $baseDir = __DIR__ . '/src/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
