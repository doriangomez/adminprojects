<?php
declare(strict_types=1);

$envFile = dirname(__DIR__) . '/.env';

/*
 * Cargar .env cuando exista.
 * Las variables ya definidas por el sistema tienen prioridad.
 */
if (is_file($envFile)) {
    $env = parse_ini_file($envFile, false, INI_SCANNER_RAW);

    if ($env === false) {
        throw new RuntimeException('No fue posible leer el archivo .env.');
    }

    foreach ($env as $key => $value) {
        $current = getenv((string)$key);

        if ($current === false || $current === '') {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

$required = [
    'DB_HOST',
    'DB_PORT',
    'DB_NAME',
    'DB_USER',
    'DB_PASSWORD',
    'APP_KEY',
];

foreach ($required as $key) {
    $value = getenv($key);

    if ($value === false || trim($value) === '') {
        throw new RuntimeException(
            "Variable requerida {$key} no definida en el entorno ni en .env"
        );
    }
}

require_once __DIR__ . '/Services/NotificationCatalog.php';
require_once __DIR__ . '/Services/ConfigService.php';

$configService = new ConfigService();
$customConfig = $configService->getConfig();

return [
    'db' => [
        'host' => getenv('DB_HOST'),
        'port' => getenv('DB_PORT'),
        'database' => getenv('DB_NAME'),
        'username' => getenv('DB_USER'),
        'password' => getenv('DB_PASSWORD'),
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'Prompt Maestro - PMO',
        'key' => getenv('APP_KEY'),
    ],
    'master_files' => $customConfig['master_files'],
    'theme' => $customConfig['theme'],
    'access' => $customConfig['access'],
];
