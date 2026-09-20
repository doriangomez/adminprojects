<?php

declare(strict_types=1);

/**
 * Ejecutor explícito e idempotente de la Etapa A del tablero PMO.
 *
 * NO se invoca desde App.php ni en el arranque HTTP.
 * Requiere autorización separada antes de ejecutarse contra cualquier base.
 *
 * Uso (cuando se autorice):
 *   php bin/migrate_pmo_board_stage_a.php
 *
 * Bootstrap de configuración/conexión: idéntico a pmo_engine.php
 * (src/config.php o mismos fallbacks DB_* del script funcional).
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este comando solo puede ejecutarse en CLI.\n");
    exit(1);
}

$root = dirname(__DIR__);

require_once $root . '/src/Core/Database.php';
require_once $root . '/src/Core/DatabaseMigrator.php';

$configPath = $root . '/src/config.php';
if (is_file($configPath)) {
    $config = require $configPath;
} else {
    $config = [
        'db' => [
            'host' => getenv('DB_HOST') ?: 'localhost',
            'port' => getenv('DB_PORT') ?: '3306',
            'database' => getenv('DB_NAME') ?: 'pmo',
            'username' => getenv('DB_USER') ?: 'pmo_user',
            'password' => getenv('DB_PASSWORD') ?: 'secret',
            'charset' => 'utf8mb4',
        ],
    ];
}

$db = new Database($config['db']);
$migrator = new DatabaseMigrator($db);
$result = $migrator->ensurePmoBoardStageA();

$status = (string) ($result['status'] ?? 'unknown');
$code = (string) ($result['code'] ?? 'PMO_A_UNKNOWN');
$message = (string) ($result['message'] ?? '');
$step = (string) ($result['step'] ?? '');
$steps = $result['steps'] ?? [];

echo '[PMO Board Stage A] status=' . $status . PHP_EOL;
echo '[PMO Board Stage A] code=' . $code . PHP_EOL;
if ($step !== '') {
    echo '[PMO Board Stage A] step=' . $step . PHP_EOL;
}
echo '[PMO Board Stage A] ' . $message . PHP_EOL;
if (is_array($steps) && $steps !== []) {
    echo '[PMO Board Stage A] steps:' . PHP_EOL;
    foreach ($steps as $item) {
        echo '  - ' . $item . PHP_EOL;
    }
}

$exitCode = match ($status) {
    'applied', 'already_applied' => 0,
    'lock_failed' => 3,
    'failed' => 4,
    default => 5,
};

exit($exitCode);
