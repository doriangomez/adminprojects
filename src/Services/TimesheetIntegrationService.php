<?php

declare(strict_types=1);

use App\Repositories\AuditLogRepository;
use App\Repositories\ProjectStoppersRepository;

/**
 * Servicio de integración del Timesheet con otros módulos del PMO.
 * Alimenta automáticamente: notas del proyecto, bloqueos, timeline operativo.
 */
class TimesheetIntegrationService
{
    public function __construct(
        private Database $db,
        private AuditLogRepository $auditRepo,
        private ProjectStoppersRepository $stoppersRepo
    ) {
    }

    /**
     * Ejecuta las integraciones cuando un timesheet es aprobado.
     */
    public function onTimesheetApproved(int $timesheetId, int $actorUserId): void
    {
        $entry = $this->db->fetchOne(
            'SELECT ts.id, ts.project_id, ts.talent_id, ts.date, ts.hours, ts.comment,
                    ts.activity_type, ts.activity_description, ts.phase, ts.has_blocker,
                    ts.blocker_description, ts.has_advance, ts.has_deliverable,
                    ta.name AS talent_name, p.name AS project_name, t.title AS task_title
             FROM timesheets ts
             LEFT JOIN talents ta ON ta.id = ts.talent_id
             LEFT JOIN projects p ON p.id = COALESCE(ts.project_id, (SELECT project_id FROM tasks WHERE id = ts.task_id))
             LEFT JOIN tasks t ON t.id = ts.task_id
             WHERE ts.id = :id',
            [':id' => $timesheetId]
        );

        if (!$entry || (int) ($entry['project_id'] ?? 0) <= 0) {
            return;
        }

        $projectId = (int) $entry['project_id'];
        $talentName = (string) ($entry['talent_name'] ?? 'Talento');
        $description = trim((string) ($entry['activity_description'] ?? $entry['comment'] ?? ''));
        if ($description === '') {
            $description = (string) ($entry['task_title'] ?? 'Registro de horas');
        }
        $hours = (float) ($entry['hours'] ?? 0);
        $activityType = (string) ($entry['activity_type'] ?? '');

        $noteText = sprintf(
            'Actividad registrada por %s: %s – %.2fh',
            $talentName,
            $description,
            $hours
        );
        if ($activityType !== '') {
            $noteText .= ' [' . $this->activityTypeLabel($activityType) . ']';
        }

        $this->auditRepo->log(
            $actorUserId,
            'project_note',
            $projectId,
            'project_note_created',
            ['note' => $noteText, 'source' => 'timesheet', 'timesheet_id' => $timesheetId]
        );

        if ((int) ($entry['has_blocker'] ?? 0) === 1) {
            $blockerDesc = trim((string) ($entry['blocker_description'] ?? ''));
            if ($blockerDesc !== '' && $this->db->tableExists('project_stoppers')) {
                $this->createStopperFromTimesheet($projectId, $blockerDesc, $entry, $actorUserId);
            }
        }
    }

    private function activityTypeLabel(string $code): string
    {
        $labels = [
            'desarrollo' => 'Desarrollo',
            'analisis' => 'Análisis',
            'reunion' => 'Reunión',
            'documentacion' => 'Documentación',
            'soporte' => 'Soporte',
            'investigacion' => 'Investigación',
            'pruebas' => 'Pruebas',
            'gestion_pm' => 'Gestión PM',
        ];
        return $labels[$code] ?? $code;
    }

    private function createStopperFromTimesheet(int $projectId, string $description, array $entry, int $actorUserId): void
    {
        $project = $this->db->fetchOne('SELECT pm_id FROM projects WHERE id = :id LIMIT 1', [':id' => $projectId]);
        $responsibleId = (int) ($project['pm_id'] ?? $actorUserId);
        $date = (string) ($entry['date'] ?? date('Y-m-d'));
        $resolutionDate = (new \DateTimeImmutable($date))->modify('+7 days')->format('Y-m-d');

        $this->stoppersRepo->create($projectId, [
            'title' => 'Bloqueo reportado desde Timesheet',
            'description' => $description,
            'stopper_type' => 'tecnico',
            'impact_level' => 'medio',
            'affected_area' => 'tiempo',
            'responsible_id' => $responsibleId,
            'detected_at' => $date,
            'estimated_resolution_at' => $resolutionDate,
            'status' => 'abierto',
        ], $actorUserId);
    }
}
