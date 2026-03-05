#!/usr/bin/env php
<?php
/**
 * PMO Motor - Ejecución diaria
 * Calcular avance por horas, avance por tareas, riesgo por bloqueos.
 * Generar alertas PMO y persistir en snapshots.
 *
 * Uso: php scripts/pmo_motor_daily.php
 * Cron (7:00 a.m. Colombia): 0 7 * * * cd /path/to/app && php scripts/pmo_motor_daily.php
 */

declare(strict_types=1);

$basePath = dirname(__DIR__);
require_once $basePath . '/src/Core/Database.php';
foreach (glob($basePath . '/src/Repositories/*.php') as $f) {
    require_once $f;
}
foreach (glob($basePath . '/src/Services/*.php') as $f) {
    require_once $f;
}
class_alias('App\\Repositories\\ProjectsRepository', 'ProjectsRepository');

$configFile = $basePath . '/src/config.php';
if (!file_exists($configFile)) {
    echo "Config no encontrado: $configFile\n";
    exit(1);
}
$config = require $configFile;
$dbConfig = $config['db'] ?? [];
$dbConfig['database'] = $dbConfig['database'] ?? $dbConfig['name'] ?? 'pmo';
$dbConfig['username'] = $dbConfig['username'] ?? $dbConfig['user'] ?? 'root';
$dbConfig['password'] = $dbConfig['password'] ?? '';
$dbConfig['host'] = $dbConfig['host'] ?? 'localhost';
$dbConfig['port'] = $dbConfig['port'] ?? 3306;

$db = new Database($dbConfig);

$today = date('Y-m-d');
$repo = new ProjectsRepository($db);

$projects = $db->fetchAll(
    'SELECT id, name, progress, planned_hours, actual_hours FROM projects WHERE status NOT IN (\'closed\', \'cancelled\')'
);

$snapshotTable = 'pmo_project_snapshots';
$alertsTable = 'pmo_alerts';

if (!$db->tableExists($snapshotTable) || !$db->tableExists($alertsTable)) {
    echo "Tablas PMO no encontradas. Ejecutar migración 2025_03_05_pmo_motor_tables.sql\n";
    exit(1);
}

foreach ($projects as $project) {
    $projectId = (int) $project['id'];
    $indicators = $repo->pmoIndicatorsForProject($projectId, $project);

    $db->execute(
        'INSERT INTO ' . $snapshotTable . ' (project_id, snapshot_date, progress_manual, progress_hours, progress_tasks, risk_score, approved_hours, planned_hours, total_tasks, done_tasks, overdue_tasks, open_stoppers)
         VALUES (:pid, :date, :pman, :phours, :ptasks, :risk, :ahours, :phours_plan, :ttasks, :dtasks, :overdue, :stoppers)
         ON DUPLICATE KEY UPDATE progress_manual=VALUES(progress_manual), progress_hours=VALUES(progress_hours), progress_tasks=VALUES(progress_tasks), risk_score=VALUES(risk_score), approved_hours=VALUES(approved_hours), planned_hours=VALUES(planned_hours), total_tasks=VALUES(total_tasks), done_tasks=VALUES(done_tasks), overdue_tasks=VALUES(overdue_tasks), open_stoppers=VALUES(open_stoppers)',
        [
            ':pid' => $projectId,
            ':date' => $today,
            ':pman' => $project['progress'] ?? null,
            ':phours' => $indicators['progress_hours'],
            ':ptasks' => $indicators['progress_tasks'],
            ':risk' => $indicators['risk_pmo_score'],
            ':ahours' => $indicators['approved_hours'],
            ':phours_plan' => $indicators['planned_hours'],
            ':ttasks' => $indicators['total_tasks'],
            ':dtasks' => $indicators['done_tasks'],
            ':overdue' => $indicators['overdue_tasks'],
            ':stoppers' => $indicators['open_stoppers'],
        ]
    );

    $alerts = [];
    if ($indicators['planned_hours'] > 0 && $indicators['approved_hours'] > $indicators['planned_hours']) {
        $alerts[] = ['type' => 'hours_overrun', 'severity' => 'warning', 'message' => 'Sobreconsumo de horas vs plan'];
    }
    if ($indicators['overdue_tasks'] > 0) {
        $alerts[] = ['type' => 'overdue_tasks', 'severity' => 'warning', 'message' => $indicators['overdue_tasks'] . ' tarea(s) vencida(s)'];
    }
    if ($indicators['open_stoppers'] > 0 && $indicators['risk_pmo_score'] >= 50) {
        $alerts[] = ['type' => 'critical_blockers', 'severity' => 'critical', 'message' => 'Bloqueos críticos activos'];
    }

    foreach ($alerts as $a) {
        $db->execute(
            'INSERT INTO ' . $alertsTable . ' (project_id, alert_type, severity, message, payload) VALUES (:pid, :type, :sev, :msg, :payload)',
            [
                ':pid' => $projectId,
                ':type' => $a['type'],
                ':sev' => $a['severity'],
                ':msg' => $a['message'],
                ':payload' => json_encode(['snapshot_date' => $today]),
            ]
        );
    }
}

echo "PMO Motor ejecutado: " . count($projects) . " proyectos procesados.\n";
