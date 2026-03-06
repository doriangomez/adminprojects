<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Core/Database.php';
require_once __DIR__ . '/src/Core/DatabaseMigrator.php';
require_once __DIR__ . '/src/Services/PmoAutomationService.php';

$configPath = __DIR__ . '/src/config.php';
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
$migrator->ensureProjectStoppersModule();
$migrator->ensureProjectPmoAutomationModule();

$service = new PmoAutomationService($db);
$criticalOnly = in_array('--critical-only', $argv, true);
$total = $criticalOnly
    ? $service->runCriticalBlockersPulse()
    : $service->runDailyForAllProjects();

echo sprintf(
    "[PMO Engine] %s ejecutado correctamente. Proyectos procesados: %d\n",
    $criticalOnly ? 'Pulse de bloqueos críticos' : 'Corte diario',
    $total
);
