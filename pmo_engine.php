<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Core/Database.php';
require_once __DIR__ . '/src/Core/DatabaseMigrator.php';
require_once __DIR__ . '/src/Services/PmoAutomationService.php';
require_once __DIR__ . '/src/Services/MonthlyTaskAutomationService.php';

$configPath = __DIR__ . '/src/config.php';

if (!is_file($configPath)) {
    throw new RuntimeException('Archivo de configuración no encontrado.');
}

$config = require $configPath;

$db = new Database($config['db']);
$migrator = new DatabaseMigrator($db);
$migrator->ensureProjectStoppersModule();
$migrator->ensureProjectPmoAutomationModule();

$monthlyTasks = (new MonthlyTaskAutomationService($db))->generateDueTasks();

$service = new PmoAutomationService($db);
$criticalOnly = in_array('--critical-only', $argv, true);
$total = $criticalOnly
    ? $service->runCriticalBlockersPulse()
    : $service->runDailyForAllProjects();

echo sprintf(
    "[PMO Engine] %s ejecutado correctamente. Proyectos procesados: %d. Tareas mensuales creadas: %d\n",
    $criticalOnly ? 'Pulse de bloqueos críticos' : 'Corte diario',
    $total,
    $monthlyTasks
);
