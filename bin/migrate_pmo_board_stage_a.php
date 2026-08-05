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
 * Credenciales: únicamente src/config.php o variables de entorno DB_* completas.
 * No usa contraseñas predeterminadas ni imprime DSN/secretos.
 */

require_once dirname(__DIR__) . '/src/Core/Database.php';
require_once dirname(__DIR__) . '/src/Core/DatabaseMigrator.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este comando solo puede ejecutarse en CLI.\n");
    exit(1);
}

/**
 * @return array{db: array<string, mixed>}
 */
function pmo_board_stage_a_load_config(): array
{
    $configPath = dirname(__DIR__) . '/src/config.php';
    if (is_file($configPath)) {
        $config = require $configPath;
        if (!is_array($config) || !isset($config['db']) || !is_array($config['db'])) {
            throw new RuntimeException('CONFIG_INVALID: src/config.php no define db correctamente.');
        }

        return $config;
    }

    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT');
    $database = getenv('DB_NAME');
    $username = getenv('DB_USER');
    $password = getenv('DB_PASSWORD');

    $missing = [];
    foreach ([
        'DB_HOST' => $host,
        'DB_PORT' => $port,
        'DB_NAME' => $database,
        'DB_USER' => $username,
        'DB_PASSWORD' => $password,
    ] as $name => $value) {
        if ($value === false || $value === null || $value === '') {
            $missing[] = $name;
        }
    }

    if ($missing !== []) {
        throw new RuntimeException(
            'CONFIG_MISSING: faltan variables de entorno o src/config.php. '
            . 'Defina la configuración de base de datos antes de ejecutar la migración.'
        );
    }

    return [
        'db' => [
            'host' => (string) $host,
            'port' => (string) $port,
            'database' => (string) $database,
            'username' => (string) $username,
            'password' => (string) $password,
            'charset' => 'utf8mb4',
        ],
    ];
}

function pmo_board_stage_a_public_error(Throwable $e): string
{
    $code = 'PMO_A_RUNTIME';
    $message = $e->getMessage();
    if (str_starts_with($message, 'CONFIG_')) {
        return $message;
    }

    return $code . ': no se pudo completar la migración. Revise el log del servidor.';
}

try {
    $config = pmo_board_stage_a_load_config();
} catch (Throwable $e) {
    fwrite(STDERR, '[PMO Board Stage A] ' . pmo_board_stage_a_public_error($e) . PHP_EOL);
    exit(2);
}

try {
    $db = new Database($config['db']);
    $migrator = new DatabaseMigrator($db);
    $result = $migrator->ensurePmoBoardStageA();
} catch (Throwable $e) {
    error_log('PMO Board Stage A connection/runtime failure: ' . $e::class);
    fwrite(STDERR, '[PMO Board Stage A] ' . pmo_board_stage_a_public_error($e) . PHP_EOL);
    exit(2);
}

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
