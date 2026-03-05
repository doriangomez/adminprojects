<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;
use InvalidArgumentException;

class TimesheetsRepository
{
    private const ADMIN_ROLES = ['Administrador', 'PMO'];
    private const DEFAULT_ACTIVITY_TYPES = [
        'desarrollo',
        'analisis',
        'reunion',
        'documentacion',
        'soporte',
        'investigacion',
        'pruebas',
        'gestion_pm',
    ];

    public function __construct(private Database $db)
    {
    }

    public function weekly(array $user): array
    {
        $conditions = [];
        $params = [];
        $hasPmColumn = $this->db->columnExists('projects', 'pm_id');
        $talentId = $this->talentIdForUser((int) ($user['id'] ?? 0));

        if (!$this->isPrivileged($user)) {
            if ($talentId !== null) {
                $conditions[] = 'ts.talent_id = :talentId';
                $params[':talentId'] = $talentId;
            } elseif ($hasPmColumn) {
                $conditions[] = 'p.pm_id = :pmId';
                $params[':pmId'] = $user['id'];
            }
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return $this->db->fetchAll(
            'SELECT ts.id, ts.date, ts.hours, ts.status, ts.billable, ts.comment, ts.approval_comment,
                    p.name AS project, t.title AS task, ta.name AS talent
             FROM timesheets ts
             LEFT JOIN tasks t ON t.id = ts.task_id
             LEFT JOIN projects p ON p.id = COALESCE(ts.project_id, t.project_id)
             LEFT JOIN clients c ON c.id = p.client_id
             JOIN talents ta ON ta.id = ts.talent_id
             ' . $where . '
             ORDER BY ts.date DESC',
            $params
        );
    }

    public function kpis(array $user): array
    {
        $conditions = [];
        $params = [];
        $hasPmColumn = $this->db->columnExists('projects', 'pm_id');
        $talentId = $this->talentIdForUser((int) ($user['id'] ?? 0));

        if (!$this->isPrivileged($user)) {
            if ($talentId !== null) {
                $conditions[] = 'ts.talent_id = :talentId';
                $params[':talentId'] = $talentId;
            } elseif ($hasPmColumn) {
                $conditions[] = 'p.pm_id = :pmId';
                $params[':pmId'] = $user['id'];
            }
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $data = $this->db->fetchAll(
            'SELECT ts.status, SUM(ts.hours) AS hours
             FROM timesheets ts
             LEFT JOIN tasks t ON t.id = ts.task_id
             LEFT JOIN projects p ON p.id = COALESCE(ts.project_id, t.project_id)
             LEFT JOIN clients c ON c.id = p.client_id
             ' . $where . '
             GROUP BY ts.status',
            $params
        );
        $totals = ['draft' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($data as $row) {
            $status = $row['status'] ?? '';
            if ($status === 'submitted' || $status === 'pending_approval') {
                $status = 'pending';
            }
            if (!array_key_exists($status, $totals)) {
                $totals[$status] = 0;
            }
            $totals[$status] = (float) $row['hours'];
        }
        return $totals;
    }



    public function weeklyGridForUser(int $userId, \DateTimeImmutable $weekStart): array
    {
        $weekEnd = $weekStart->modify('+6 days');
        $projects = $this->projectsForTimesheetEntry($userId);
        $projectMap = [];
        foreach ($projects as $project) {
            $projectId = (int) ($project['project_id'] ?? 0);
            if ($projectId <= 0 || isset($projectMap[$projectId])) {
                continue;
            }
            $projectMap[$projectId] = [
                'project_id' => $projectId,
                'project' => (string) ($project['project'] ?? ''),
                'assignment_id' => isset($project['assignment_id']) ? (int) $project['assignment_id'] : null,
            ];
        }

        $talentId = $this->talentIdForUser($userId);
        $entries = [];
        if ($talentId !== null) {
            $structuredSelect = $this->structuredTimesheetSelectColumns();
            $structuredSegment = $structuredSelect !== '' ? ', ' . $structuredSelect : '';
            $entries = $this->db->fetchAll(
                'SELECT id, project_id, task_id, date, hours, status, comment' . $structuredSegment . '
                 FROM timesheets
                 WHERE user_id = :user
                   AND date BETWEEN :start AND :end
                 ORDER BY date ASC, updated_at DESC, id DESC',
                [
                    ':user' => $userId,
                    ':start' => $weekStart->format('Y-m-d'),
                    ':end' => $weekEnd->format('Y-m-d'),
                ]
            );
        }

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->modify('+' . $i . ' days');
            $days[] = [
                'key' => $day->format('Y-m-d'),
                'label' => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'][$i],
                'number' => $day->format('d'),
            ];
        }

        $cells = [];
        $dayTotals = array_fill_keys(array_column($days, 'key'), 0.0);
        $activitiesByDay = array_fill_keys(array_column($days, 'key'), []);
        foreach ($entries as $entry) {
            $projectId = (int) ($entry['project_id'] ?? 0);
            $date = (string) ($entry['date'] ?? '');
            if (!isset($projectMap[$projectId]) || !isset($dayTotals[$date])) {
                continue;
            }
            if (!isset($cells[$projectId][$date])) {
                $cells[$projectId][$date] = [
                    'id' => (int) ($entry['id'] ?? 0),
                    'hours' => 0.0,
                    'status' => 'draft',
                    'comment' => '',
                    'entries' => [],
                ];
            }

            $entryHours = (float) ($entry['hours'] ?? 0);
            $entryStatus = (string) ($entry['status'] ?? 'draft');
            $entryComment = trim((string) ($entry['comment'] ?? ''));
            $cells[$projectId][$date]['hours'] += $entryHours;
            $cells[$projectId][$date]['status'] = $this->mergeTimesheetStatus((string) $cells[$projectId][$date]['status'], $entryStatus);
            if ($cells[$projectId][$date]['comment'] === '' && $entryComment !== '') {
                $cells[$projectId][$date]['comment'] = $entryComment;
            }

            $activityItem = [
                'id' => (int) ($entry['id'] ?? 0),
                'task_id' => isset($entry['task_id']) ? (int) $entry['task_id'] : null,
                'hours' => $entryHours,
                'status' => $entryStatus,
                'comment' => $entryComment,
                'phase_name' => trim((string) ($entry['phase_name'] ?? '')),
                'subphase_name' => trim((string) ($entry['subphase_name'] ?? '')),
                'activity_type' => trim((string) ($entry['activity_type'] ?? '')),
                'activity_description' => trim((string) ($entry['activity_description'] ?? '')),
                'had_blocker' => (int) ($entry['had_blocker'] ?? 0) === 1,
                'blocker_description' => trim((string) ($entry['blocker_description'] ?? '')),
                'had_significant_progress' => (int) ($entry['had_significant_progress'] ?? 0) === 1,
                'generated_deliverable' => (int) ($entry['generated_deliverable'] ?? 0) === 1,
                'operational_comment' => trim((string) ($entry['operational_comment'] ?? '')),
            ];
            $cells[$projectId][$date]['entries'][] = $activityItem;
            $activitiesByDay[$date][] = array_merge($activityItem, [
                'project_id' => $projectId,
                'project' => (string) ($projectMap[$projectId]['project'] ?? ''),
                'date' => $date,
            ]);
            $dayTotals[$date] += $entryHours;
        }

        $rows = [];
        foreach ($projectMap as $projectId => $project) {
            $rowTotal = 0.0;
            $rowCells = [];
            foreach ($days as $day) {
                $date = $day['key'];
                $cell = $cells[$projectId][$date] ?? null;
                $hours = $cell !== null ? (float) ($cell['hours'] ?? 0) : 0.0;
                $rowTotal += $hours;
                $rowCells[$date] = $cell ?? [
                    'id' => null,
                    'hours' => 0.0,
                    'status' => 'draft',
                    'comment' => '',
                    'entries' => [],
                ];
            }
            $rows[] = [
                'project_id' => $projectId,
                'project' => $project['project'],
                'assignment_id' => $project['assignment_id'],
                'cells' => $rowCells,
                'total' => $rowTotal,
            ];
        }

        $profile = $this->talentProfileForUser($userId) ?? [];
        $weeklyCapacity = (float) ($profile['capacidad_horaria'] ?? 0);
        if ($weeklyCapacity <= 0) {
            $weeklyCapacity = (float) ($profile['weekly_capacity'] ?? 0);
        }

        foreach ($activitiesByDay as &$dayItems) {
            usort($dayItems, static function (array $a, array $b): int {
                return strcmp((string) ($a['project'] ?? ''), (string) ($b['project'] ?? ''));
            });
        }
        unset($dayItems);

        return [
            'days' => $days,
            'rows' => $rows,
            'day_totals' => $dayTotals,
            'week_total' => array_sum($dayTotals),
            'weekly_capacity' => $weeklyCapacity,
            'requires_full_report' => (int) ($profile['requiere_reporte_horas'] ?? 0) === 1,
            'activities_by_day' => $activitiesByDay,
            'activity_types' => $this->activityTypesCatalog(),
        ];
    }

    public function upsertDraftCell(
        int $userId,
        int $projectId,
        string $date,
        float $hours,
        string $comment = '',
        array $metadata = [],
        bool $syncOperational = false
    ): array
    {
        $assignment = $this->assignmentForProject($projectId, $userId);
        if (!$assignment) {
            throw new InvalidArgumentException('Proyecto no asignado o inactivo para este talento.');
        }

        $talentId = (int) ($assignment['talent_id'] ?? 0);
        if ($talentId <= 0) {
            throw new InvalidArgumentException('Tu usuario no tiene un talento asociado.');
        }

        $this->assertWeekIsEditable($userId, $date);

        $linkedStopperSelect = $this->db->columnExists('timesheets', 'linked_stopper_id') ? ', linked_stopper_id' : '';
        $existing = $this->db->fetchOne(
            'SELECT id, status, task_id, hours' . $linkedStopperSelect . '
             FROM timesheets
             WHERE user_id = :user AND project_id = :project AND date = :date
             LIMIT 1',
            [':user' => $userId, ':project' => $projectId, ':date' => $date]
        );

        if ($existing && !in_array((string) ($existing['status'] ?? ''), ['draft', 'rejected'], true)) {
            throw new InvalidArgumentException('La celda no es editable porque la semana ya fue enviada.');
        }

        $dayTotalRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(hours), 0) AS total
             FROM timesheets
             WHERE user_id = :user AND date = :date AND id <> :id',
            [
                ':user' => $userId,
                ':date' => $date,
                ':id' => (int) ($existing['id'] ?? 0),
            ]
        );

        $currentDayTotal = (float) ($dayTotalRow['total'] ?? 0);
        if ($currentDayTotal + $hours > 24) {
            throw new InvalidArgumentException('No puedes registrar más de 24 horas en un mismo día.');
        }

        $approverUserId = (int) ($assignment['timesheet_approver_user_id'] ?? 0);
        $approver = $approverUserId > 0 ? $approverUserId : null;
        $structured = $this->sanitizeStructuredMetadata($metadata);
        $applyStructuredFields = $metadata !== [];
        $taskId = $this->resolveTaskForEntry($projectId, $structured['task_id']);

        if ($existing) {
            $set = [
                'task_id = :task_id',
                'hours = :hours',
                'comment = :comment',
                'status = :status',
                'approver_user_id = :approver',
                'updated_at = NOW()',
            ];
            $params = [
                ':task_id' => $taskId,
                ':hours' => $hours,
                ':comment' => $comment,
                ':status' => 'draft',
                ':approver' => $approver,
                ':id' => (int) $existing['id'],
            ];

            if ($applyStructuredFields) {
                foreach ($this->structuredColumnMap() as $column => $key) {
                    if (!$this->db->columnExists('timesheets', $column)) {
                        continue;
                    }
                    $set[] = $column . ' = :' . $column;
                    $params[':' . $column] = $structured[$key];
                }
            }

            $this->db->execute(
                'UPDATE timesheets
                 SET ' . implode(",\n                     ", $set) . '
                 WHERE id = :id',
                $params
            );

            if ($syncOperational && $hours > 0) {
                $this->syncOperationalArtifacts((int) $existing['id'], $userId);
            }

            return ['id' => (int) $existing['id'], 'updated' => true];
        }

        $payload = [
            'task_id' => $taskId,
            'project_id' => $projectId,
            'talent_id' => $talentId,
            'user_id' => $userId,
            'assignment_id' => $assignment['id'] ?? null,
            'approver_user_id' => $approver,
            'date' => $date,
            'hours' => $hours,
            'status' => 'draft',
            'comment' => $comment,
            'billable' => 0,
            'approved_by' => null,
            'approved_at' => null,
        ];
        if ($applyStructuredFields) {
            $payload = array_merge($payload, [
                'phase_name' => $structured['phase_name'],
                'subphase_name' => $structured['subphase_name'],
                'activity_type' => $structured['activity_type'],
                'activity_description' => $structured['activity_description'],
                'had_blocker' => $structured['had_blocker'],
                'blocker_description' => $structured['blocker_description'],
                'had_significant_progress' => $structured['had_significant_progress'],
                'generated_deliverable' => $structured['generated_deliverable'],
                'operational_comment' => $structured['operational_comment'],
                'linked_stopper_id' => null,
            ]);
        }

        $id = $this->createTimesheet($payload);

        if ($syncOperational && $hours > 0) {
            $this->syncOperationalArtifacts($id, $userId);
        }

        return ['id' => $id, 'updated' => false];
    }

    public function createDraftActivity(
        int $userId,
        int $projectId,
        string $date,
        float $hours,
        string $comment,
        array $metadata = []
    ): int {
        $this->assertValidDate($date);
        if ($hours <= 0 || $hours > 24) {
            throw new InvalidArgumentException('Las horas deben estar entre 0 y 24.');
        }
        $this->assertWeekIsEditable($userId, $date);
        $this->assertNoWeekendUnlessAdmin($date, $userId);

        $assignment = $this->assignmentForProject($projectId, $userId);
        if (!$assignment) {
            throw new InvalidArgumentException('Proyecto no asignado o inactivo para este talento.');
        }

        $talentId = (int) ($assignment['talent_id'] ?? 0);
        if ($talentId <= 0) {
            throw new InvalidArgumentException('Tu usuario no tiene un talento asociado.');
        }

        $dayTotalRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(hours), 0) AS total
             FROM timesheets
             WHERE user_id = :user AND date = :date',
            [
                ':user' => $userId,
                ':date' => $date,
            ]
        );
        $currentDayTotal = (float) ($dayTotalRow['total'] ?? 0);
        if ($currentDayTotal + $hours > 24) {
            throw new InvalidArgumentException('No puedes registrar más de 24 horas en un mismo día.');
        }

        $approverUserId = (int) ($assignment['timesheet_approver_user_id'] ?? 0);
        $structured = $this->sanitizeStructuredMetadata($metadata);
        if ($structured['activity_type'] === '') {
            throw new InvalidArgumentException('Tipo de actividad es obligatorio.');
        }
        $taskManagementMode = strtolower(trim((string) ($metadata['task_management_mode'] ?? 'existing')));
        if (!in_array($taskManagementMode, ['existing', 'completed', 'pending'], true)) {
            $taskManagementMode = 'existing';
        }

        $taskId = $this->resolveTaskForEntry($projectId, (int) ($structured['task_id'] ?? 0));
        if ($taskManagementMode !== 'existing') {
            $taskTitle = trim((string) ($metadata['new_task_title'] ?? ''));
            if ($taskTitle === '') {
                $taskTitle = $structured['activity_description'] !== '' ? $structured['activity_description'] : trim($comment);
            }
            $taskPayload = [
                'priority' => in_array($metadata['new_task_priority'] ?? '', ['low', 'medium', 'high'], true) ? $metadata['new_task_priority'] : 'medium',
                'due_date' => $metadata['new_task_due_date'] ?? null,
            ];
            $taskId = $this->createTaskFromTimesheetActivity($projectId, $talentId, $userId, $taskTitle, $taskManagementMode, $taskPayload);
        }

        $linkedStopperId = null;
        if ($structured['had_blocker'] && trim((string) $structured['blocker_description']) !== '') {
            $linkedStopperId = $this->ensureStopperFromTimesheetBlocker($projectId, $userId, (string) $structured['blocker_description'], (int) ($metadata['task_id'] ?? 0), $date);
        }

        return $this->createTimesheet([
            'task_id' => $taskId,
            'project_id' => $projectId,
            'talent_id' => $talentId,
            'user_id' => $userId,
            'assignment_id' => $assignment['id'] ?? null,
            'approver_user_id' => $approverUserId > 0 ? $approverUserId : null,
            'date' => $date,
            'hours' => $hours,
            'status' => 'draft',
            'comment' => trim($comment),
            'approval_comment' => null,
            'billable' => 0,
            'approved_by' => null,
            'approved_at' => null,
            'phase_name' => $structured['phase_name'],
            'subphase_name' => $structured['subphase_name'],
            'activity_type' => $structured['activity_type'],
            'activity_description' => $structured['activity_description'],
            'had_blocker' => $structured['had_blocker'],
            'blocker_description' => $structured['blocker_description'],
            'had_significant_progress' => $structured['had_significant_progress'],
            'generated_deliverable' => $structured['generated_deliverable'],
            'operational_comment' => $structured['operational_comment'],
            'linked_stopper_id' => $linkedStopperId ?? null,
        ]);
    }

    public function updateDraftActivity(
        int $activityId,
        int $userId,
        int $projectId,
        string $date,
        float $hours,
        string $comment,
        array $metadata = []
    ): bool {
        $this->assertValidDate($date);
        if ($hours <= 0 || $hours > 24) {
            throw new InvalidArgumentException('Las horas deben estar entre 0 y 24.');
        }

        $current = $this->findUserActivity($activityId, $userId, true);
        if (!$current) {
            throw new InvalidArgumentException('La actividad no existe o no se puede editar.');
        }
        $currentDate = (string) ($current['date'] ?? '');
        if ($currentDate !== '') {
            $this->assertWeekIsEditable($userId, $currentDate);
        }
        $this->assertWeekIsEditable($userId, $date);
        $this->assertNoWeekendUnlessAdmin($date, $userId);

        $assignment = $this->assignmentForProject($projectId, $userId);
        if (!$assignment) {
            throw new InvalidArgumentException('Proyecto no asignado o inactivo para este talento.');
        }

        $dayTotalRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(hours), 0) AS total
             FROM timesheets
             WHERE user_id = :user AND date = :date AND id <> :id',
            [
                ':user' => $userId,
                ':date' => $date,
                ':id' => $activityId,
            ]
        );
        $currentDayTotal = (float) ($dayTotalRow['total'] ?? 0);
        if ($currentDayTotal + $hours > 24) {
            throw new InvalidArgumentException('No puedes registrar más de 24 horas en un mismo día.');
        }

        $structured = $this->sanitizeStructuredMetadata($metadata);
        $taskId = $this->resolveTaskForEntry($projectId, (int) ($structured['task_id'] ?? 0));
        $approverUserId = (int) ($assignment['timesheet_approver_user_id'] ?? 0);

        $set = [
            'project_id = :project_id',
            'task_id = :task_id',
            'date = :date',
            'hours = :hours',
            'comment = :comment',
            'status = :status',
            'approval_comment = NULL',
            'approved_by = NULL',
            'approved_at = NULL',
            'rejected_by = NULL',
            'rejected_at = NULL',
            'approver_user_id = :approver_user_id',
            'updated_at = NOW()',
        ];
        $params = [
            ':project_id' => $projectId,
            ':task_id' => $taskId,
            ':date' => $date,
            ':hours' => $hours,
            ':comment' => trim($comment),
            ':status' => 'draft',
            ':approver_user_id' => $approverUserId > 0 ? $approverUserId : null,
            ':id' => $activityId,
            ':user_id' => $userId,
        ];

        foreach ($this->structuredColumnMap() as $column => $key) {
            if (!$this->db->columnExists('timesheets', $column)) {
                continue;
            }
            $set[] = $column . ' = :' . $column;
            $params[':' . $column] = $structured[$key];
        }

        $this->db->execute(
            'UPDATE timesheets
             SET ' . implode(', ', $set) . '
             WHERE id = :id AND user_id = :user_id',
            $params
        );

        $row = $this->db->fetchOne('SELECT ROW_COUNT() AS total');
        return (int) ($row['total'] ?? 0) > 0;
    }

    public function deleteDraftActivity(int $activityId, int $userId): bool
    {
        $current = $this->findUserActivity($activityId, $userId, true);
        if (!$current) {
            throw new InvalidArgumentException('La actividad no existe o no se puede eliminar.');
        }
        $currentDate = (string) ($current['date'] ?? '');
        if ($currentDate !== '') {
            $this->assertWeekIsEditable($userId, $currentDate);
        }

        $taskId = (int) ($current['task_id'] ?? 0);
        $this->db->execute(
            'DELETE FROM timesheets WHERE id = :id AND user_id = :user_id',
            [':id' => $activityId, ':user_id' => $userId]
        );

        if ($taskId > 0) {
            $this->refreshTaskActualHours($taskId);
        }

        $row = $this->db->fetchOne('SELECT ROW_COUNT() AS total');
        return (int) ($row['total'] ?? 0) > 0;
    }

    public function duplicateDraftActivity(int $activityId, int $userId, string $targetDate): int
    {
        $this->assertValidDate($targetDate);
        $this->assertWeekIsEditable($userId, $targetDate);
        $current = $this->findUserActivity($activityId, $userId, false);
        if (!$current) {
            throw new InvalidArgumentException('La actividad no existe.');
        }

        $projectId = (int) ($current['resolved_project_id'] ?? 0);
        if ($projectId <= 0) {
            throw new InvalidArgumentException('La actividad no tiene proyecto asociado.');
        }

        return $this->createDraftActivity(
            $userId,
            $projectId,
            $targetDate,
            (float) ($current['hours'] ?? 0),
            (string) ($current['comment'] ?? ''),
            [
                'task_id' => (int) ($current['task_id'] ?? 0),
                'phase_name' => (string) ($current['phase_name'] ?? ''),
                'subphase_name' => (string) ($current['subphase_name'] ?? ''),
                'activity_type' => (string) ($current['activity_type'] ?? ''),
                'activity_description' => (string) ($current['activity_description'] ?? ''),
                'had_blocker' => (int) ($current['had_blocker'] ?? 0),
                'blocker_description' => (string) ($current['blocker_description'] ?? ''),
                'had_significant_progress' => (int) ($current['had_significant_progress'] ?? 0),
                'generated_deliverable' => (int) ($current['generated_deliverable'] ?? 0),
                'operational_comment' => (string) ($current['operational_comment'] ?? ''),
            ]
        );
    }

    public function moveDraftActivity(int $activityId, int $userId, string $targetDate): bool
    {
        $this->assertValidDate($targetDate);
        $current = $this->findUserActivity($activityId, $userId, true);
        if (!$current) {
            throw new InvalidArgumentException('La actividad no existe o no se puede mover.');
        }
        $currentDate = (string) ($current['date'] ?? '');
        if ($currentDate !== '') {
            $this->assertWeekIsEditable($userId, $currentDate);
        }
        $this->assertWeekIsEditable($userId, $targetDate);
        $this->assertNoWeekendUnlessAdmin($targetDate, $userId);

        if ((string) ($current['date'] ?? '') === $targetDate) {
            return true;
        }

        $hours = (float) ($current['hours'] ?? 0);
        $dayTotalRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(hours), 0) AS total
             FROM timesheets
             WHERE user_id = :user AND date = :date AND id <> :id',
            [
                ':user' => $userId,
                ':date' => $targetDate,
                ':id' => $activityId,
            ]
        );
        $currentDayTotal = (float) ($dayTotalRow['total'] ?? 0);
        if ($currentDayTotal + $hours > 24) {
            throw new InvalidArgumentException('No puedes mover la actividad: excede 24 horas en el día destino.');
        }

        $this->db->execute(
            'UPDATE timesheets
             SET date = :date,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :id AND user_id = :user',
            [
                ':date' => $targetDate,
                ':status' => 'draft',
                ':id' => $activityId,
                ':user' => $userId,
            ]
        );

        $row = $this->db->fetchOne('SELECT ROW_COUNT() AS total');
        return (int) ($row['total'] ?? 0) > 0;
    }

    public function duplicateDayActivities(int $userId, string $sourceDate, string $targetDate): int
    {
        $this->assertValidDate($sourceDate);
        $this->assertValidDate($targetDate);
        $this->assertWeekIsEditable($userId, $sourceDate);
        $this->assertWeekIsEditable($userId, $targetDate);
        if ($sourceDate === $targetDate) {
            throw new InvalidArgumentException('El día origen y destino no pueden ser iguales.');
        }

        $structuredSelect = $this->structuredTimesheetSelectColumns('ts');
        $structuredSegment = $structuredSelect !== '' ? ', ' . $structuredSelect : '';
        $entries = $this->db->fetchAll(
            'SELECT ts.id, ts.task_id, ts.project_id, ts.date, ts.hours, ts.comment' . $structuredSegment . ',
                    COALESCE(ts.project_id, t.project_id) AS resolved_project_id
             FROM timesheets ts
             LEFT JOIN tasks t ON t.id = ts.task_id
             WHERE ts.user_id = :user
               AND ts.date = :source
               AND ts.status IN ("draft", "rejected")
             ORDER BY ts.id ASC',
            [
                ':user' => $userId,
                ':source' => $sourceDate,
            ]
        );

        $created = 0;
        foreach ($entries as $entry) {
            $projectId = (int) ($entry['resolved_project_id'] ?? 0);
            if ($projectId <= 0) {
                continue;
            }
            $this->createDraftActivity(
                $userId,
                $projectId,
                $targetDate,
                (float) ($entry['hours'] ?? 0),
                (string) ($entry['comment'] ?? ''),
                [
                    'task_id' => (int) ($entry['task_id'] ?? 0),
                    'phase_name' => (string) ($entry['phase_name'] ?? ''),
                    'subphase_name' => (string) ($entry['subphase_name'] ?? ''),
                    'activity_type' => (string) ($entry['activity_type'] ?? ''),
                    'activity_description' => (string) ($entry['activity_description'] ?? ''),
                    'had_blocker' => (int) ($entry['had_blocker'] ?? 0),
                    'blocker_description' => (string) ($entry['blocker_description'] ?? ''),
                    'had_significant_progress' => (int) ($entry['had_significant_progress'] ?? 0),
                    'generated_deliverable' => (int) ($entry['generated_deliverable'] ?? 0),
                    'operational_comment' => (string) ($entry['operational_comment'] ?? ''),
                ]
            );
            $created++;
        }

        return $created;
    }

    public function submitWeek(
        int $userId,
        \DateTimeImmutable $weekStart,
        float $minimumWeeklyHours = 0.0,
        bool $lockIncompleteWeek = true
    ): int
    {
        $weekEnd = $weekStart->modify('+6 days');
        $profile = $this->talentProfileForUser($userId) ?? [];
        $requiresFull = (int) ($profile['requiere_reporte_horas'] ?? 0) === 1;

        if ($minimumWeeklyHours > 0) {
            $weeklyTotalRow = $this->db->fetchOne(
                'SELECT COALESCE(SUM(hours), 0) AS total
                 FROM timesheets
                 WHERE user_id = :user
                   AND date BETWEEN :start AND :end',
                [
                    ':user' => $userId,
                    ':start' => $weekStart->format('Y-m-d'),
                    ':end' => $weekEnd->format('Y-m-d'),
                ]
            );
            $weeklyTotal = (float) ($weeklyTotalRow['total'] ?? 0);
            if ($weeklyTotal < $minimumWeeklyHours) {
                throw new InvalidArgumentException(
                    sprintf('No se puede enviar la semana: mínimo requerido %.2fh y registradas %.2fh.', $minimumWeeklyHours, $weeklyTotal)
                );
            }
        }

        if ($requiresFull && $lockIncompleteWeek) {
            $rows = $this->db->fetchAll(
                'SELECT date, COALESCE(SUM(hours), 0) AS hours
                 FROM timesheets
                 WHERE user_id = :user AND date BETWEEN :start AND :end
                 GROUP BY date',
                [
                    ':user' => $userId,
                    ':start' => $weekStart->format('Y-m-d'),
                    ':end' => $weekEnd->format('Y-m-d'),
                ]
            );
            $totalsByDate = [];
            foreach ($rows as $row) {
                $totalsByDate[(string) $row['date']] = (float) ($row['hours'] ?? 0);
            }
            for ($i = 0; $i < 7; $i++) {
                $day = $weekStart->modify('+' . $i . ' days')->format('Y-m-d');
                if (($totalsByDate[$day] ?? 0.0) <= 0) {
                    throw new InvalidArgumentException('No se puede enviar la semana: hay días sin horas registradas.');
                }
            }
        }

        $this->db->execute(
            'UPDATE timesheets
             SET status = :submitted,
                 approved_by = NULL,
                 approved_at = NULL,
                 rejected_by = NULL,
                 rejected_at = NULL,
                 updated_at = NOW()
             WHERE user_id = :user
               AND date BETWEEN :start AND :end
               AND status = :draft',
            [
                ':submitted' => 'submitted',
                ':draft' => 'draft',
                ':user' => $userId,
                ':start' => $weekStart->format('Y-m-d'),
                ':end' => $weekEnd->format('Y-m-d'),
            ]
        );

        $updated = (int) (($this->db->fetchOne('SELECT ROW_COUNT() AS total')['total'] ?? 0));
        if ($updated > 0) {
            $this->generateWeekSubmissionNotes($userId, $weekStart, $weekEnd);
        }

        return $updated;
    }

    private function generateWeekSubmissionNotes(int $userId, \DateTimeImmutable $weekStart, \DateTimeImmutable $weekEnd): void
    {
        if (!$this->db->tableExists('audit_log')) {
            return;
        }

        $userName = $this->db->fetchOne('SELECT name FROM users WHERE id = :id', [':id' => $userId])['name'] ?? 'Usuario';
        $weekNum = (int) $weekStart->format('W');
        $year = (int) $weekStart->format('o');

        $structuredSelect = $this->structuredTimesheetSelectColumns('ts');
        $structuredSegment = $structuredSelect !== '' ? ', ' . $structuredSelect : '';
        $entries = $this->db->fetchAll(
            'SELECT ts.id, ts.project_id, ts.date, ts.hours, ts.comment' . $structuredSegment . ',
                    COALESCE(ts.activity_description, ts.comment) AS activity_desc,
                    p.name AS project_name
             FROM timesheets ts
             LEFT JOIN projects p ON p.id = ts.project_id
             WHERE ts.user_id = :user
               AND ts.date BETWEEN :start AND :end
             ORDER BY ts.project_id, ts.date',
            [
                ':user' => $userId,
                ':start' => $weekStart->format('Y-m-d'),
                ':end' => $weekEnd->format('Y-m-d'),
            ]
        );

        $byProject = [];
        foreach ($entries as $row) {
            $pid = (int) ($row['project_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            if (!isset($byProject[$pid])) {
                $byProject[$pid] = ['name' => $row['project_name'] ?? 'Proyecto', 'entries' => []];
            }
            $byProject[$pid]['entries'][] = $row;
        }

        $dowLabels = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $auditRepo = new AuditLogRepository($this->db);

        foreach ($byProject as $projectId => $data) {
            $lines = ["Semana $weekNum ($year)", "Usuario: $userName", ''];
            $byDay = [];
            $blockers = [];
            foreach ($data['entries'] as $e) {
                $date = (string) ($e['date'] ?? '');
                $dow = $date !== '' ? (int) (new \DateTimeImmutable($date))->format('w') : 0;
                $dayLabel = $dowLabels[$dow] ?? $date;
                if (!isset($byDay[$dayLabel])) {
                    $byDay[$dayLabel] = [];
                }
                $desc = trim((string) ($e['activity_desc'] ?? $e['comment'] ?? 'Actividad'));
                $byDay[$dayLabel][] = round((float) ($e['hours'] ?? 0), 2) . 'h – ' . $desc;
                if (!empty($e['had_blocker']) && trim((string) ($e['blocker_description'] ?? '')) !== '') {
                    $blockers[] = trim((string) $e['blocker_description']);
                }
            }
            foreach ($byDay as $dayLabel => $activities) {
                $lines[] = $dayLabel;
                foreach ($activities as $a) {
                    $lines[] = $a;
                }
                $lines[] = '';
            }
            if ($blockers !== []) {
                $lines[] = 'Bloqueo detectado:';
                $lines[] = implode("\n", array_unique($blockers));
            }
            $note = implode("\n", $lines);
            $auditRepo->log($userId, 'project_note', $projectId, 'project_note_created', ['note' => $note, 'source' => 'timesheet_week_submitted']);
        }
    }

    public function cancelWeekSubmission(int $userId, \DateTimeImmutable $weekStart): int
    {
        $weekEnd = $weekStart->modify('+6 days');
        $this->db->execute(
            'UPDATE timesheets
             SET status = :draft,
                 updated_at = NOW()
             WHERE user_id = :user
               AND date BETWEEN :start AND :end
               AND status IN ("submitted", "pending", "pending_approval")',
            [
                ':draft' => 'draft',
                ':user' => $userId,
                ':start' => $weekStart->format('Y-m-d'),
                ':end' => $weekEnd->format('Y-m-d'),
            ]
        );

        $row = $this->db->fetchOne('SELECT ROW_COUNT() AS total');
        return (int) ($row['total'] ?? 0);
    }

    public function reopenOwnWeekToDraft(int $userId, \DateTimeImmutable $weekStart, ?string $comment = null): int
    {
        $weekEnd = $weekStart->modify('+6 days');
        $statusRow = $this->db->fetchOne(
            'SELECT status, approver_user_id
             FROM timesheets
             WHERE user_id = :user
               AND date BETWEEN :start AND :end
               AND status = "approved"
             ORDER BY updated_at DESC, id DESC
             LIMIT 1',
            [
                ':user' => $userId,
                ':start' => $weekStart->format('Y-m-d'),
                ':end' => $weekEnd->format('Y-m-d'),
            ]
        );

        if (!$statusRow) {
            return 0;
        }

        $this->db->execute(
            'UPDATE timesheets
             SET status = :draft,
                 approval_comment = :comment,
                 approved_by = NULL,
                 approved_at = NULL,
                 rejected_by = NULL,
                 rejected_at = NULL,
                 updated_at = NOW()
             WHERE user_id = :user
               AND date BETWEEN :start AND :end
               AND status = "approved"',
            [
                ':draft' => 'draft',
                ':comment' => $comment,
                ':user' => $userId,
                ':start' => $weekStart->format('Y-m-d'),
                ':end' => $weekEnd->format('Y-m-d'),
            ]
        );
        $updated = (int) (($this->db->fetchOne('SELECT ROW_COUNT() AS total')['total'] ?? 0));

        if ($updated > 0) {
            $targetApproverUserId = (int) ($statusRow['approver_user_id'] ?? $userId);
            $this->logWeekWorkflowAction($weekStart, $userId, 'reopened', $comment, 'approved', 'draft', $targetApproverUserId);
        }

        return $updated;
    }

    public function deleteWeekEntries(int $userId, \DateTimeImmutable $weekStart, int $actorUserId, ?string $comment = null): int
    {
        $weekEnd = $weekStart->modify('+6 days');
        $statusRow = $this->db->fetchOne(
            'SELECT status, approver_user_id
             FROM timesheets
             WHERE user_id = :user
               AND date BETWEEN :start AND :end
             ORDER BY updated_at DESC, id DESC
             LIMIT 1',
            [
                ':user' => $userId,
                ':start' => $weekStart->format('Y-m-d'),
                ':end' => $weekEnd->format('Y-m-d'),
            ]
        );
        if (!$statusRow) {
            return 0;
        }
        if ((string) ($statusRow['status'] ?? '') === 'closed') {
            throw new InvalidArgumentException('La semana está cerrada definitivamente y no se puede eliminar.');
        }

        $this->db->execute(
            'DELETE FROM timesheets
             WHERE user_id = :user
               AND date BETWEEN :start AND :end',
            [
                ':user' => $userId,
                ':start' => $weekStart->format('Y-m-d'),
                ':end' => $weekEnd->format('Y-m-d'),
            ]
        );
        $deleted = (int) (($this->db->fetchOne('SELECT ROW_COUNT() AS total')['total'] ?? 0));

        if ($deleted > 0) {
            $targetApproverUserId = (int) ($statusRow['approver_user_id'] ?? $userId);
            $this->logWeekWorkflowAction($weekStart, $actorUserId, 'deleted', $comment, (string) ($statusRow['status'] ?? 'draft'), 'deleted', $targetApproverUserId);
        }

        return $deleted;
    }

    public function weeksHistoryForUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT DATE_SUB(date, INTERVAL WEEKDAY(date) DAY) AS week_start,
                    DATE_ADD(DATE_SUB(date, INTERVAL WEEKDAY(date) DAY), INTERVAL 6 DAY) AS week_end,
                    COALESCE(SUM(hours), 0) AS total_hours,
                    COUNT(*) AS entries,
                    MAX(CASE status
                        WHEN "approved" THEN 5
                        WHEN "rejected" THEN 4
                        WHEN "submitted" THEN 3
                        WHEN "pending" THEN 3
                        WHEN "pending_approval" THEN 3
                        WHEN "draft" THEN 2
                        ELSE 1 END) AS status_weight
             FROM timesheets
             WHERE user_id = :user
             GROUP BY DATE_SUB(date, INTERVAL WEEKDAY(date) DAY)
             ORDER BY week_start DESC',
            [':user' => $userId]
        );
    }

    public function weekSummaryForUser(int $userId, \DateTimeImmutable $weekStart): array
    {
        $weekEnd = $weekStart->modify('+6 days');
        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(hours), 0) AS total_hours,
                    MAX(CASE status
                        WHEN "approved" THEN 5
                        WHEN "rejected" THEN 4
                        WHEN "submitted" THEN 3
                        WHEN "pending" THEN 3
                        WHEN "pending_approval" THEN 3
                        WHEN "draft" THEN 2
                        ELSE 1 END) AS status_weight,
                    MAX(approved_at) AS approved_at,
                    MAX(rejected_at) AS rejected_at,
                    SUBSTRING_INDEX(GROUP_CONCAT(approval_comment ORDER BY updated_at DESC SEPARATOR "||"), "||", 1) AS latest_comment,
                    MAX(approved_by) AS approved_by,
                    MAX(rejected_by) AS rejected_by,
                    COUNT(DISTINCT status) AS status_count
             FROM timesheets
             WHERE user_id = :user
               AND date BETWEEN :start AND :end',
            [':user' => $userId, ':start' => $weekStart->format('Y-m-d'), ':end' => $weekEnd->format('Y-m-d')]
        ) ?: [];

        $status = $this->statusByWeight((int) ($row['status_weight'] ?? 2), (int) ($row['status_count'] ?? 1));
        $approverName = null;
        $approverId = (int) ($row['approved_by'] ?? $row['rejected_by'] ?? 0);
        if ($approverId > 0) {
            $u = $this->db->fetchOne('SELECT name FROM users WHERE id = :id LIMIT 1', [':id' => $approverId]);
            $approverName = $u['name'] ?? null;
        }

        return [
            'status' => $status,
            'total_hours' => (float) ($row['total_hours'] ?? 0),
            'approved_at' => $row['approved_at'] ?? null,
            'rejected_at' => $row['rejected_at'] ?? null,
            'approval_comment' => $row['latest_comment'] ?? null,
            'approver_name' => $approverName,
        ];
    }

    public function monthlySummaryForUser(int $userId, \DateTimeImmutable $weekStart): array
    {
        $monthStart = $weekStart->modify('first day of this month')->setTime(0, 0);
        $monthEnd = $weekStart->modify('last day of this month')->setTime(0, 0);
        $rows = $this->db->fetchAll(
            'SELECT status, COALESCE(SUM(hours), 0) AS total_hours
             FROM timesheets
             WHERE user_id = :user
               AND date BETWEEN :start AND :end
             GROUP BY status',
            [':user' => $userId, ':start' => $monthStart->format('Y-m-d'), ':end' => $monthEnd->format('Y-m-d')]
        );
        $out = ['month_total' => 0.0, 'approved' => 0.0, 'rejected' => 0.0, 'draft' => 0.0, 'capacity' => 0.0, 'compliance' => 0.0];
        foreach ($rows as $r) {
            $hours = (float) ($r['total_hours'] ?? 0);
            $status = (string) ($r['status'] ?? 'draft');
            $out['month_total'] += $hours;
            if (in_array($status, ['submitted', 'pending', 'pending_approval'], true)) {
                continue;
            }
            if (isset($out[$status])) {
                $out[$status] += $hours;
            }
        }
        $profile = $this->talentProfileForUser($userId) ?? [];
        $weeklyCapacity = (float) ($profile['capacidad_horaria'] ?? $profile['weekly_capacity'] ?? 0);
        $weeksInMonth = (int) ceil(((int) $monthEnd->format('j')) / 7);
        $out['capacity'] = max(0, $weeklyCapacity) * max(1, $weeksInMonth);
        $out['compliance'] = $out['capacity'] > 0 ? min(100, round(($out['month_total'] / $out['capacity']) * 100, 2)) : 0;
        return $out;
    }

    public function executiveSummary(array $user, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd, ?int $projectId = null): array
    {
        $params = [
            ':start' => $periodStart->format('Y-m-d'),
            ':end' => $periodEnd->format('Y-m-d'),
        ];
        $where = $this->timesheetScopeWhere($user, $params);
        if ($projectId !== null && $projectId > 0) {
            $where[] = 'COALESCE(ts.project_id, t.project_id) = :project';
            $params[':project'] = $projectId;
        }

        $rows = $this->db->fetchAll(
            'SELECT ts.status, COALESCE(SUM(ts.hours), 0) AS total_hours
             FROM timesheets ts
             LEFT JOIN tasks t ON t.id = ts.task_id
             LEFT JOIN projects p ON p.id = COALESCE(ts.project_id, t.project_id)
             WHERE ts.date BETWEEN :start AND :end' . ($where ? ' AND ' . implode(' AND ', $where) : '') . '
             GROUP BY ts.status',
            $params
        );

        $summary = [
            'total' => 0.0,
            'approved' => 0.0,
            'rejected' => 0.0,
            'draft' => 0.0,
            'pending' => 0.0,
            'capacity' => 0.0,
            'approved_percent' => 0.0,
            'compliance_percent' => 0.0,
        ];

        foreach ($rows as $row) {
            $hours = (float) ($row['total_hours'] ?? 0);
            $status = (string) ($row['status'] ?? 'draft');
            $summary['total'] += $hours;
            if (in_array($status, ['submitted', 'pending', 'pending_approval'], true)) {
                $summary['pending'] += $hours;
            } elseif (isset($summary[$status])) {
                $summary[$status] += $hours;
            }
        }

        $summary['capacity'] = $this->capacityForScope($user, $periodStart, $periodEnd, $projectId);
        $summary['approved_percent'] = $summary['total'] > 0 ? round(($summary['approved'] / $summary['total']) * 100, 2) : 0.0;
        $summary['compliance_percent'] = $summary['capacity'] > 0 ? round(($summary['total'] / $summary['capacity']) * 100, 2) : 0.0;

        return $summary;
    }

    public function approvedWeeksByPeriod(array $user, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd, ?int $projectId = null): array
    {
        $params = [
            ':start' => $periodStart->format('Y-m-d'),
            ':end' => $periodEnd->format('Y-m-d'),
        ];
        $where = $this->timesheetScopeWhere($user, $params);
        if ($projectId !== null && $projectId > 0) {
            $where[] = 'COALESCE(ts.project_id, t.project_id) = :project';
            $params[':project'] = $projectId;
        }

        return $this->db->fetchAll(
            'SELECT DATE_SUB(ts.date, INTERVAL WEEKDAY(ts.date) DAY) AS week_start,
                    DATE_ADD(DATE_SUB(ts.date, INTERVAL WEEKDAY(ts.date) DAY), INTERVAL 6 DAY) AS week_end,
                    COALESCE(SUM(ts.hours), 0) AS total_hours,
                    MAX(CASE ts.status
                        WHEN "approved" THEN 5
                        WHEN "rejected" THEN 4
                        WHEN "submitted" THEN 3
                        WHEN "pending" THEN 3
                        WHEN "pending_approval" THEN 3
                        WHEN "draft" THEN 2
                        ELSE 1 END) AS status_weight,
                    MAX(ts.approved_at) AS approved_at,
                    SUBSTRING_INDEX(GROUP_CONCAT(ua.name ORDER BY ts.approved_at DESC SEPARATOR "||"), "||", 1) AS approver_name
             FROM timesheets ts
             LEFT JOIN tasks t ON t.id = ts.task_id
             LEFT JOIN projects p ON p.id = COALESCE(ts.project_id, t.project_id)
             LEFT JOIN users ua ON ua.id = ts.approved_by
             WHERE ts.date BETWEEN :start AND :end' . ($where ? ' AND ' . implode(' AND ', $where) : '') . '
             GROUP BY DATE_SUB(ts.date, INTERVAL WEEKDAY(ts.date) DAY)
             ORDER BY week_start DESC'
            , $params
        );
    }

    public function talentBreakdownByPeriod(array $user, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd, ?int $projectId = null, string $sort = 'load_desc'): array
    {
        $monthWeeks = max(1, (int) ceil(((int) $periodEnd->format('j')) / 7));
        $params = [
            ':start' => $periodStart->format('Y-m-d'),
            ':end' => $periodEnd->format('Y-m-d'),
            ':month_weeks' => $monthWeeks,
        ];
        $where = $this->timesheetScopeWhere($user, $params);
        if ($projectId !== null && $projectId > 0) {
            $where[] = 'COALESCE(ts.project_id, t.project_id) = :project';
            $params[':project'] = $projectId;
        }
        $orderBy = $sort === 'compliance_asc' ? 'compliance_percent ASC, total_hours DESC' : 'total_hours DESC, compliance_percent ASC';

        $sql = 'SELECT ta.id AS talent_id, ta.name AS talent_name,
                    COALESCE(SUM(ts.hours), 0) AS total_hours,
                    COALESCE(SUM(CASE WHEN ts.status = "approved" THEN ts.hours ELSE 0 END), 0) AS approved_hours,
                    COALESCE(SUM(CASE WHEN ts.status = "rejected" THEN ts.hours ELSE 0 END), 0) AS rejected_hours,
                    COALESCE(SUM(CASE WHEN ts.status = "draft" THEN ts.hours ELSE 0 END), 0) AS draft_hours,
                    COALESCE(SUM(CASE WHEN ts.status IN ("submitted", "pending", "pending_approval") THEN ts.hours ELSE 0 END), 0) AS pending_hours,
                    MAX(DATE_SUB(ts.date, INTERVAL WEEKDAY(ts.date) DAY)) AS last_week_submitted,
                    MAX(CASE WHEN ts.status = "approved" THEN DATE_SUB(ts.date, INTERVAL WEEKDAY(ts.date) DAY) ELSE NULL END) AS last_week_approved,
                    MAX(COALESCE(ta.capacidad_horaria, ta.weekly_capacity, 0)) AS weekly_capacity,
                    ROUND((COALESCE(SUM(ts.hours), 0) / NULLIF(MAX(COALESCE(ta.capacidad_horaria, ta.weekly_capacity, 0)) * :month_weeks, 0)) * 100, 2) AS compliance_percent
             FROM timesheets ts
             JOIN talents ta ON ta.id = ts.talent_id
             LEFT JOIN tasks t ON t.id = ts.task_id
             LEFT JOIN projects p ON p.id = COALESCE(ts.project_id, t.project_id)
             WHERE ts.date BETWEEN :start AND :end' . ($where ? ' AND ' . implode(' AND ', $where) : '') . '
             GROUP BY ta.id, ta.name
             ORDER BY ' . $orderBy;

        return $this->db->fetchAll(
            $sql,
            $params
        );
    }

    public function projectBreakdownByPeriod(array $user, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd): array
    {
        $params = [':start' => $periodStart->format('Y-m-d'), ':end' => $periodEnd->format('Y-m-d')];
        $where = $this->timesheetScopeWhere($user, $params);

        return $this->db->fetchAll(
            'SELECT COALESCE(ts.project_id, t.project_id) AS project_id, p.name AS project,
                    COALESCE(SUM(ts.hours), 0) AS total_hours
             FROM timesheets ts
             LEFT JOIN tasks t ON t.id = ts.task_id
             LEFT JOIN projects p ON p.id = COALESCE(ts.project_id, t.project_id)
             WHERE ts.date BETWEEN :start AND :end' . ($where ? ' AND ' . implode(' AND ', $where) : '') . '
             GROUP BY COALESCE(ts.project_id, t.project_id), p.name
             ORDER BY total_hours DESC'
            , $params
        );
    }

    public function weekHistoryLogForUser(int $userId, \DateTimeImmutable $weekStart): array
    {
        $weekKey = $this->weekKey($weekStart);
        return $this->db->fetchAll(
            'SELECT w.created_at, w.action, w.action_comment, actor.name AS actor_name
             FROM timesheet_week_actions w
             LEFT JOIN users actor ON actor.id = w.actor_user_id
             WHERE w.week_key = :week_key
               AND w.deleted_at IS NULL
               AND EXISTS (
                    SELECT 1 FROM timesheets ts
                    WHERE ts.user_id = :user
                      AND ts.approver_user_id = w.target_approver_user_id
                      AND ts.date BETWEEN w.week_start AND w.week_end
               )
             ORDER BY w.created_at DESC, w.id DESC',
            [':week_key' => $weekKey, ':user' => $userId]
        );
    }

    public function pendingApprovalsByWeek(array $user): array
    {
        $rows = $this->pendingApprovals($user);
        $grouped = [];
        foreach ($rows as $row) {
            $date = new \DateTimeImmutable((string) ($row['date'] ?? 'now'));
            $weekStart = $date->modify('monday this week')->format('Y-m-d');
            $project = (string) ($row['project'] ?? 'Sin proyecto');
            $grouped[$weekStart]['week_start'] = $weekStart;
            $grouped[$weekStart]['week_label'] = $date->modify('monday this week')->format('d/m') . ' - ' . $date->modify('sunday this week')->format('d/m');
            $grouped[$weekStart]['total_hours'] = ($grouped[$weekStart]['total_hours'] ?? 0) + (float) ($row['hours'] ?? 0);
            $grouped[$weekStart]['rows'][] = $row;
            $grouped[$weekStart]['projects'][$project] = ($grouped[$weekStart]['projects'][$project] ?? 0) + (float) ($row['hours'] ?? 0);
        }

        foreach ($grouped as &$week) {
            $summary = [];
            foreach ($week['projects'] as $project => $hours) {
                $summary[] = ['project' => $project, 'hours' => $hours];
            }
            usort($summary, static fn(array $a, array $b): int => strcmp($a['project'], $b['project']));
            $week['project_summary'] = $summary;
            unset($week['projects']);
        }
        unset($week);

        krsort($grouped);
        return array_values($grouped);
    }

    public function updateWeekApprovalStatus(int $approverUserId, string $weekStart, string $status, ?string $comment = null): int
    {
        $start = new \DateTimeImmutable($weekStart);
        $end = $start->modify('+6 days');
        $params = [
            ':status' => $status,
            ':comment' => $comment,
            ':approver_set' => $approverUserId,
            ':approver_where' => $approverUserId,
            ':start' => $start->format('Y-m-d'),
            ':end' => $end->format('Y-m-d'),
        ];

        $column = $status === 'approved'
            ? 'approved_by = :approver_set, approved_at = NOW(), rejected_by = NULL, rejected_at = NULL'
            : 'rejected_by = :approver_set, rejected_at = NOW(), approved_by = NULL, approved_at = NULL';

        $sql = 'UPDATE timesheets
             SET status = :status,
                 approval_comment = :comment,
                 ' . $column . ',
                 updated_at = NOW()
             WHERE approver_user_id = :approver_where
               AND date BETWEEN :start AND :end
               AND status IN ("submitted", "pending", "pending_approval")';

        $this->db->execute($sql, $params);
        $row = $this->db->fetchOne('SELECT ROW_COUNT() AS total');
        $updated = (int) ($row['total'] ?? 0);

        if ($updated > 0) {
            $this->logWeekWorkflowAction($start, $approverUserId, $status, $comment, 'pending', $status, $approverUserId);
        }

        return $updated;
    }

    public function reopenWeek(int $actorUserId, string $weekStart, int $approverUserId, ?string $comment = null): int
    {
        $start = new \DateTimeImmutable($weekStart);
        $end = $start->modify('+6 days');
        $statusRow = $this->db->fetchOne(
            'SELECT status
             FROM timesheets
             WHERE approver_user_id = :approver
               AND date BETWEEN :start AND :end
               AND status IN ("approved", "rejected")
             ORDER BY updated_at DESC, id DESC
             LIMIT 1',
            [
                ':approver' => $approverUserId,
                ':start' => $start->format('Y-m-d'),
                ':end' => $end->format('Y-m-d'),
            ]
        );

        $previousStatus = (string) ($statusRow['status'] ?? '');
        if ($previousStatus === '') {
            return 0;
        }

        $this->db->execute(
            'UPDATE timesheets
             SET status = :status,
                 approval_comment = :comment,
                 approved_by = NULL,
                 approved_at = NULL,
                 rejected_by = NULL,
                 rejected_at = NULL,
                 updated_at = NOW()
             WHERE approver_user_id = :approver
               AND date BETWEEN :start AND :end
               AND status IN ("approved", "rejected")',
            [
                ':status' => 'pending',
                ':comment' => $comment,
                ':approver' => $approverUserId,
                ':start' => $start->format('Y-m-d'),
                ':end' => $end->format('Y-m-d'),
            ]
        );

        $row = $this->db->fetchOne('SELECT ROW_COUNT() AS total');
        $updated = (int) ($row['total'] ?? 0);
        if ($updated > 0) {
            $this->logWeekWorkflowAction($start, $actorUserId, 'reopened', $comment, $previousStatus, 'pending', $approverUserId);
        }

        return $updated;
    }

    public function softDeleteWeekWorkflow(int $actorUserId, string $weekStart, int $approverUserId, ?string $comment = null): int
    {
        $start = new \DateTimeImmutable($weekStart);
        $end = $start->modify('+6 days');
        $row = $this->db->fetchOne(
            'SELECT status
             FROM timesheets
             WHERE approver_user_id = :approver
               AND date BETWEEN :start AND :end
             ORDER BY updated_at DESC, id DESC
             LIMIT 1',
            [
                ':approver' => $approverUserId,
                ':start' => $start->format('Y-m-d'),
                ':end' => $end->format('Y-m-d'),
            ]
        );
        $previousStatus = (string) ($row['status'] ?? 'pending');
        if (!$row) {
            return 0;
        }

        $this->logWeekWorkflowAction($start, $actorUserId, 'deleted', $comment, $previousStatus, $previousStatus, $approverUserId);
        $this->db->execute(
            'UPDATE timesheet_week_actions
             SET deleted_at = NOW(),
                 deleted_by = :actor,
                 updated_at = NOW()
             WHERE week_key = :week_key
               AND target_approver_user_id = :approver
               AND deleted_at IS NULL',
            [
                ':actor' => $actorUserId,
                ':week_key' => $this->weekKey($start),
                ':approver' => $approverUserId,
            ]
        );

        $affected = $this->db->fetchOne('SELECT ROW_COUNT() AS total');
        return (int) ($affected['total'] ?? 0);
    }

    public function weekApprovalHistoryByApprover(array $user): array
    {
        if (!$this->db->tableExists('timesheet_week_actions')) {
            return [];
        }

        $conditions = ['w.deleted_at IS NULL'];
        $params = [];
        if (!$this->isPrivileged($user)) {
            $conditions[] = 'w.target_approver_user_id = :approver';
            $params[':approver'] = (int) ($user['id'] ?? 0);
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return $this->db->fetchAll(
            'SELECT w.id, w.week_key, w.week_start, w.week_end, w.action, w.action_comment,
                    w.previous_status, w.resulting_status, w.created_at, w.target_approver_user_id,
                    actor.name AS actor_name, target.name AS approver_name
             FROM timesheet_week_actions w
             LEFT JOIN users actor ON actor.id = w.actor_user_id
             LEFT JOIN users target ON target.id = w.target_approver_user_id
             ' . $where . '
             ORDER BY w.week_start DESC, w.created_at DESC, w.id DESC',
            $params
        );
    }

    private function logWeekWorkflowAction(\DateTimeImmutable $weekStart, int $actorUserId, string $action, ?string $comment, ?string $previousStatus, string $resultingStatus, int $targetApproverUserId): void
    {
        if (!$this->db->tableExists('timesheet_week_actions')) {
            return;
        }

        $weekEnd = $weekStart->modify('+6 days');
        $this->db->insert(
            'INSERT INTO timesheet_week_actions
                (week_key, week_start, week_end, action, action_comment, actor_user_id, previous_status, resulting_status, target_approver_user_id)
             VALUES (:week_key, :week_start, :week_end, :action, :comment, :actor, :previous_status, :resulting_status, :target_approver)',
            [
                ':week_key' => $this->weekKey($weekStart),
                ':week_start' => $weekStart->format('Y-m-d'),
                ':week_end' => $weekEnd->format('Y-m-d'),
                ':action' => $action,
                ':comment' => $comment,
                ':actor' => $actorUserId,
                ':previous_status' => $previousStatus,
                ':resulting_status' => $resultingStatus,
                ':target_approver' => $targetApproverUserId,
            ]
        );
    }

    private function weekKey(\DateTimeImmutable $weekStart): string
    {
        return $weekStart->format('o-\WW');
    }

    private function statusByWeight(int $weight, int $statusCount = 1): string
    {
        if ($statusCount > 1) {
            return 'partial';
        }

        return match (true) {
            $weight >= 5 => 'approved',
            $weight >= 4 => 'rejected',
            $weight >= 3 => 'submitted',
            default => 'draft',
        };
    }
    public function hasTimesheetAssignments(int $userId): bool
    {
        if (!$this->db->tableExists('talents')) {
            return false;
        }

        $talent = $this->talentProfileForUser($userId);

        return $talent !== null && (int) ($talent['requiere_reporte_horas'] ?? 0) === 1;
    }

    public function projectsCatalog(): array
    {
        if (!$this->db->tableExists('projects')) {
            return [];
        }

        $condition = $this->activeProjectCondition('p');
        return $this->db->fetchAll(
            'SELECT p.id AS project_id, p.name AS project
             FROM projects p' . ($condition !== '' ? ' WHERE ' . $condition : '') . '
             ORDER BY p.name ASC'
        );
    }

    public function tasksForTimesheetEntry(int $userId): array
    {
        if (
            !$this->db->tableExists('project_talent_assignments')
            || !$this->db->tableExists('projects')
            || !$this->db->tableExists('tasks')
        ) {
            return [];
        }

        $projectStatusCondition = $this->activeProjectCondition('p');
        $assignmentStatusCondition = "(a.assignment_status = 'active' OR (a.assignment_status IS NULL AND a.active = 1))";

        return $this->db->fetchAll(
            'SELECT tk.id AS task_id, tk.title AS task_title, p.id AS project_id, p.name AS project
             FROM project_talent_assignments a
             JOIN projects p ON p.id = a.project_id
             JOIN tasks tk ON tk.project_id = p.id
             JOIN talents t ON t.user_id = a.user_id
             WHERE a.user_id = :user
               AND COALESCE(t.requiere_reporte_horas, 0) = 1
               AND ' . $assignmentStatusCondition .
            ($projectStatusCondition !== '' ? ' AND ' . $projectStatusCondition : '') . '
             ORDER BY p.name ASC, tk.title ASC',
            [':user' => $userId]
        );
    }

    public function recentActivitySuggestions(int $userId, int $limit = 8): array
    {
        if (!$this->db->tableExists('timesheets')) {
            return [];
        }

        $sqlLimit = max(1, min(30, $limit * 4));

        $rows = $this->db->fetchAll(
            'SELECT ts.project_id, ts.task_id, ts.activity_type, ts.activity_description, ts.phase_name, ts.subphase_name,
                    p.name AS project, tk.title AS task
             FROM timesheets ts
             LEFT JOIN projects p ON p.id = ts.project_id
             LEFT JOIN tasks tk ON tk.id = ts.task_id
             WHERE ts.user_id = :user
               AND (
                    COALESCE(ts.activity_description, "") <> ""
                    OR COALESCE(ts.comment, "") <> ""
               )
             ORDER BY ts.updated_at DESC, ts.id DESC
             LIMIT ' . $sqlLimit,
            [
                ':user' => $userId,
            ]
        );

        $unique = [];
        foreach ($rows as $row) {
            $fingerprint = implode('|', [
                (int) ($row['project_id'] ?? 0),
                (int) ($row['task_id'] ?? 0),
                strtolower(trim((string) ($row['activity_type'] ?? ''))),
                strtolower(trim((string) ($row['activity_description'] ?? ''))),
                strtolower(trim((string) ($row['phase_name'] ?? ''))),
                strtolower(trim((string) ($row['subphase_name'] ?? ''))),
            ]);
            if (isset($unique[$fingerprint])) {
                continue;
            }
            $unique[$fingerprint] = [
                'project_id' => (int) ($row['project_id'] ?? 0),
                'task_id' => (int) ($row['task_id'] ?? 0),
                'project' => (string) ($row['project'] ?? ''),
                'task' => (string) ($row['task'] ?? ''),
                'activity_type' => (string) ($row['activity_type'] ?? ''),
                'activity_description' => (string) ($row['activity_description'] ?? ''),
                'phase_name' => (string) ($row['phase_name'] ?? ''),
                'subphase_name' => (string) ($row['subphase_name'] ?? ''),
            ];
            if (count($unique) >= $limit) {
                break;
            }
        }

        return array_values($unique);
    }

    public function activityTypesCatalog(): array
    {
        $config = (new \ConfigService($this->db))->getConfig();
        $configured = $config['operational_rules']['timesheets']['activity_types'] ?? [];
        $activityTypes = [];
        foreach ($configured as $type) {
            $value = strtolower(trim((string) $type));
            if ($value === '') {
                continue;
            }
            $activityTypes[$value] = $value;
        }

        if ($activityTypes === []) {
            foreach (self::DEFAULT_ACTIVITY_TYPES as $type) {
                $activityTypes[$type] = $type;
            }
        }

        return array_values($activityTypes);
    }

    public function activityTypeBreakdownByPeriod(array $user, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd, ?int $projectId = null): array
    {
        $params = [
            ':start' => $periodStart->format('Y-m-d'),
            ':end' => $periodEnd->format('Y-m-d'),
        ];
        $where = $this->timesheetScopeWhere($user, $params);
        if ($projectId !== null && $projectId > 0) {
            $where[] = 'COALESCE(ts.project_id, t.project_id) = :project';
            $params[':project'] = $projectId;
        }

        $rows = $this->db->fetchAll(
            'SELECT COALESCE(NULLIF(TRIM(ts.activity_type), ""), "sin_clasificar") AS activity_type,
                    COALESCE(SUM(ts.hours), 0) AS total_hours
             FROM timesheets ts
             LEFT JOIN tasks t ON t.id = ts.task_id
             LEFT JOIN projects p ON p.id = COALESCE(ts.project_id, t.project_id)
             WHERE ts.date BETWEEN :start AND :end' . ($where ? ' AND ' . implode(' AND ', $where) : '') . '
             GROUP BY COALESCE(NULLIF(TRIM(ts.activity_type), ""), "sin_clasificar")
             ORDER BY total_hours DESC',
            $params
        );

        $total = array_reduce($rows, static fn(float $carry, array $row): float => $carry + (float) ($row['total_hours'] ?? 0), 0.0);
        foreach ($rows as &$row) {
            $hours = (float) ($row['total_hours'] ?? 0);
            $row['percent'] = $total > 0 ? round(($hours / $total) * 100, 2) : 0.0;
        }
        unset($row);

        return $rows;
    }

    public function phaseBreakdownByPeriod(array $user, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd, ?int $projectId = null): array
    {
        $params = [
            ':start' => $periodStart->format('Y-m-d'),
            ':end' => $periodEnd->format('Y-m-d'),
        ];
        $where = $this->timesheetScopeWhere($user, $params);
        if ($projectId !== null && $projectId > 0) {
            $where[] = 'COALESCE(ts.project_id, t.project_id) = :project';
            $params[':project'] = $projectId;
        }

        return $this->db->fetchAll(
            'SELECT COALESCE(NULLIF(TRIM(ts.phase_name), ""), "sin_fase") AS phase_name,
                    COALESCE(NULLIF(TRIM(ts.subphase_name), ""), "sin_subfase") AS subphase_name,
                    COALESCE(SUM(ts.hours), 0) AS total_hours
             FROM timesheets ts
             LEFT JOIN tasks t ON t.id = ts.task_id
             LEFT JOIN projects p ON p.id = COALESCE(ts.project_id, t.project_id)
             WHERE ts.date BETWEEN :start AND :end' . ($where ? ' AND ' . implode(' AND ', $where) : '') . '
             GROUP BY COALESCE(NULLIF(TRIM(ts.phase_name), ""), "sin_fase"), COALESCE(NULLIF(TRIM(ts.subphase_name), ""), "sin_subfase")
             ORDER BY total_hours DESC',
            $params
        );
    }

    public function projectsForTimesheetEntry(int $userId): array
    {
        if (!$this->db->tableExists('project_talent_assignments') || !$this->db->tableExists('projects')) {
            return [];
        }
        $projectStatusCondition = $this->activeProjectCondition('p');
        $assignmentStatusCondition = "(a.assignment_status = 'active' OR (a.assignment_status IS NULL AND a.active = 1))";

        return $this->db->fetchAll(
            'SELECT a.id AS assignment_id, a.project_id, a.requires_timesheet_approval, p.name AS project
             FROM project_talent_assignments a
             JOIN projects p ON p.id = a.project_id
             JOIN talents t ON t.user_id = a.user_id
             WHERE a.user_id = :user
               AND COALESCE(t.requiere_reporte_horas, 0) = 1
               AND ' . $assignmentStatusCondition .
             ($projectStatusCondition !== '' ? ' AND ' . $projectStatusCondition : '') . '
             ORDER BY p.name ASC',
            [':user' => $userId]
        );
    }

    public function assignmentForProject(int $projectId, int $userId): ?array
    {
        if (!$this->db->tableExists('project_talent_assignments') || !$this->db->tableExists('projects')) {
            return null;
        }
        $projectStatusCondition = $this->activeProjectCondition('p');
        $assignmentStatusCondition = "(a.assignment_status = 'active' OR (a.assignment_status IS NULL AND a.active = 1))";
        $hasTalentColumn = $this->db->columnExists('project_talent_assignments', 'talent_id');
        $talentSelect = $hasTalentColumn ? 'a.talent_id' : 'NULL AS talent_id';
        $talentJoin = $hasTalentColumn ? 'a.talent_id' : 'NULL';
        $assignment = $this->db->fetchOne(
            'SELECT a.id, ' . $talentSelect . ', a.requires_timesheet, a.requires_timesheet_approval,
                    a.project_id, p.name AS project,
                    tal.requiere_reporte_horas, tal.requiere_aprobacion_horas, tal.timesheet_approver_user_id
             FROM project_talent_assignments a
             JOIN projects p ON p.id = a.project_id
             LEFT JOIN talents tal ON tal.id = ' . $talentJoin . '
             WHERE a.user_id = :user
               AND a.project_id = :project
               AND ' . $assignmentStatusCondition .
            ($projectStatusCondition !== '' ? ' AND ' . $projectStatusCondition : '') . '
             LIMIT 1',
            [':user' => $userId, ':project' => $projectId]
        );

        if (!$assignment) {
            return null;
        }

        $talentId = $hasTalentColumn ? (int) ($assignment['talent_id'] ?? 0) : 0;
        if ($talentId <= 0) {
            $talentId = $this->talentIdForUser($userId) ?? 0;
        }

        return [
            'id' => $assignment['id'] ?? null,
            'requires_timesheet' => $assignment['requires_timesheet'] ?? 0,
            'requires_timesheet_approval' => $assignment['requires_timesheet_approval'] ?? 0,
            'talent_requires_report' => $assignment['requiere_reporte_horas'] ?? 0,
            'talent_requires_approval' => $assignment['requiere_aprobacion_horas'] ?? 0,
            'timesheet_approver_user_id' => $assignment['timesheet_approver_user_id'] ?? null,
            'talent_id' => $talentId,
            'project_id' => $assignment['project_id'] ?? null,
        ];
    }

    public function resolveTimesheetTaskId(int $projectId): int
    {
        if (!$this->db->tableExists('tasks')) {
            throw new InvalidArgumentException('No hay tareas disponibles para registrar horas.');
        }

        $task = $this->db->fetchOne(
            'SELECT id FROM tasks WHERE project_id = :project AND title = :title ORDER BY id ASC LIMIT 1',
            [':project' => $projectId, ':title' => 'Registro de horas']
        );

        if ($task && isset($task['id'])) {
            return (int) $task['id'];
        }

        $columns = ['project_id', 'title', 'status', 'priority', 'estimated_hours', 'actual_hours', 'created_at', 'updated_at'];
        $values = [':project', ':title', ':status', ':priority', '0', '0', 'NOW()', 'NOW()'];
        if ($this->db->columnExists('tasks', 'completed_at')) {
            $columns[] = 'completed_at';
            $values[] = 'NULL';
        }

        return (int) $this->db->insert(
            'INSERT INTO tasks (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $values) . ')',
            [
                ':project' => $projectId,
                ':title' => 'Registro de horas',
                ':status' => 'pending',
                ':priority' => 'medium',
            ]
        );
    }

    public function createTimesheet(array $payload): int
    {
        $columns = [
            'task_id',
            'talent_id',
            'assignment_id',
            'approver_user_id',
            'date',
            'hours',
            'status',
            'comment',
            'approval_comment',
            'billable',
            'approved_by',
            'approved_at',
        ];

        $params = [
            ':task_id' => (int) $payload['task_id'],
            ':talent_id' => (int) $payload['talent_id'],
            ':assignment_id' => $payload['assignment_id'] !== null ? (int) $payload['assignment_id'] : null,
            ':approver_user_id' => $payload['approver_user_id'] ?? null,
            ':date' => $payload['date'],
            ':hours' => (float) $payload['hours'],
            ':status' => $payload['status'],
            ':comment' => $payload['comment'],
            ':approval_comment' => $payload['approval_comment'] ?? null,
            ':billable' => (int) $payload['billable'],
            ':approved_by' => $payload['approved_by'],
            ':approved_at' => $payload['approved_at'],
        ];

        $structuredInsertMap = [
            'phase_name' => 'phase_name',
            'subphase_name' => 'subphase_name',
            'activity_type' => 'activity_type',
            'activity_description' => 'activity_description',
            'had_blocker' => 'had_blocker',
            'blocker_description' => 'blocker_description',
            'had_significant_progress' => 'had_significant_progress',
            'generated_deliverable' => 'generated_deliverable',
            'operational_comment' => 'operational_comment',
            'linked_stopper_id' => 'linked_stopper_id',
        ];
        foreach ($structuredInsertMap as $column => $payloadKey) {
            if (!$this->db->columnExists('timesheets', $column)) {
                continue;
            }
            $columns[] = $column;
            $params[':' . $column] = $payload[$payloadKey] ?? null;
        }

        if ($this->db->columnExists('timesheets', 'project_id')) {
            $columns[] = 'project_id';
            $params[':project_id'] = $payload['project_id'] ?? null;
        }

        if ($this->db->columnExists('timesheets', 'user_id')) {
            $columns[] = 'user_id';
            $params[':user_id'] = $payload['user_id'] ?? null;
        }

        $columns[] = 'created_at';
        $columns[] = 'updated_at';

        $timesheetId = $this->db->insert(
            'INSERT INTO timesheets (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', array_keys($params)) . ', NOW(), NOW())',
            $params
        );

        $this->refreshTaskActualHours((int) $payload['task_id']);

        return $timesheetId;
    }

    public function myTimesheets(int $talentId): array
    {
        if (!$this->db->tableExists('timesheets')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT ts.id, ts.date, ts.hours, ts.status, ts.billable, ts.comment, ts.approval_comment, p.name AS project, t.title AS task,
                    ts.approved_at, ts.rejected_at, ua.name AS approved_by_name, ur.name AS rejected_by_name
             FROM timesheets ts
             LEFT JOIN tasks t ON t.id = ts.task_id
             LEFT JOIN projects p ON p.id = COALESCE(ts.project_id, t.project_id)
             LEFT JOIN users ua ON ua.id = ts.approved_by
             LEFT JOIN users ur ON ur.id = ts.rejected_by
             WHERE ts.talent_id = :talent
             ORDER BY ts.date DESC, ts.id DESC',
            [':talent' => $talentId]
        );
    }

    public function pendingApprovals(array $user): array
    {
        if (!$this->db->tableExists('timesheets')) {
            return [];
        }

        $conditions = ['ts.status IN ("pending", "submitted", "pending_approval")'];
        $params = [];
        if (!$this->isPrivileged($user)) {
            $conditions[] = 'ts.approver_user_id = :approverUser';
            $params[':approverUser'] = (int) ($user['id'] ?? 0);
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return $this->db->fetchAll(
            'SELECT ts.id, ts.date, ts.hours, ts.status, ts.billable, ts.comment, ts.approval_comment, p.name AS project, t.title AS task, ta.name AS talent, ts.approver_user_id
             FROM timesheets ts
             LEFT JOIN tasks t ON t.id = ts.task_id
             LEFT JOIN projects p ON p.id = COALESCE(ts.project_id, t.project_id)
             JOIN talents ta ON ta.id = ts.talent_id
             ' . $where . '
             ORDER BY ts.date DESC, ts.id DESC',
            $params
        );
    }

    public function countPendingApprovals(): int
    {
        if (!$this->db->tableExists('timesheets')) {
            return 0;
        }

        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total
             FROM timesheets
             WHERE status IN ("pending", "submitted", "pending_approval")'
        );

        return (int) ($row['total'] ?? 0);
    }

    public function updateApprovalStatus(int $timesheetId, string $status, int $userId, ?string $comment = null): bool
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Estado de aprobación inválido.');
        }

        $column = $status === 'approved' ? 'approved_by' : 'rejected_by';
        $columnDate = $status === 'approved' ? 'approved_at' : 'rejected_at';

        $timesheet = $this->db->fetchOne(
            'SELECT ts.task_id, ta.user_id, ts.approver_user_id
             FROM timesheets ts
             JOIN talents ta ON ta.id = ts.talent_id
             WHERE ts.id = :id',
            [':id' => $timesheetId]
        );

        if ($timesheet && (int) ($timesheet['user_id'] ?? 0) === $userId) {
            throw new InvalidArgumentException('No puedes aprobar tus propias horas.');
        }

        if ($timesheet && (int) ($timesheet['approver_user_id'] ?? 0) > 0 && (int) ($timesheet['approver_user_id'] ?? 0) !== $userId) {
            throw new InvalidArgumentException('No tienes permiso para aprobar o rechazar este registro de horas.');
        }

        $updated = $this->db->execute(
            "UPDATE timesheets
             SET status = :status,
                 {$column} = :user,
                 {$columnDate} = NOW(),
                 approval_comment = :comment,
                 updated_at = NOW()
             WHERE id = :id AND status IN ('pending', 'submitted', 'pending_approval')",
            [
                ':status' => $status,
                ':user' => $userId,
                ':comment' => $comment !== null ? trim($comment) : null,
                ':id' => $timesheetId,
            ]
        );

        if ($updated && $timesheet) {
            $this->refreshTaskActualHours((int) $timesheet['task_id']);
        }

        return $updated;
    }


    public function managedWeekEntries(\DateTimeImmutable $weekStart, ?int $talentId = null): array
    {
        $weekEnd = $weekStart->modify('+6 days');
        $params = [
            ':start' => $weekStart->format('Y-m-d'),
            ':end' => $weekEnd->format('Y-m-d'),
        ];
        $where = ['ts.date BETWEEN :start AND :end'];
        if ($talentId !== null && $talentId > 0) {
            $where[] = 'ts.talent_id = :talent';
            $params[':talent'] = $talentId;
        }

        return $this->db->fetchAll(
            'SELECT ts.id, ts.date, ts.hours, ts.status, ts.comment, ts.user_id, ts.talent_id,
                    COALESCE(ts.project_id, t.project_id) AS project_id,
                    p.name AS project_name, ta.name AS talent_name, u.name AS user_name
             FROM timesheets ts
             LEFT JOIN tasks t ON t.id = ts.task_id
             LEFT JOIN projects p ON p.id = COALESCE(ts.project_id, t.project_id)
             LEFT JOIN talents ta ON ta.id = ts.talent_id
             LEFT JOIN users u ON u.id = ts.user_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY ts.date DESC, ta.name ASC, ts.id DESC',
            $params
        );
    }

    public function adminUpdateHours(int $timesheetId, float $hours, string $reason, int $actorUserId): bool
    {
        if ($hours < 0 || $hours > 24) {
            throw new InvalidArgumentException('Horas inválidas. Deben estar entre 0 y 24.');
        }

        $row = $this->db->fetchOne('SELECT id, task_id, user_id, talent_id, date, hours, status FROM timesheets WHERE id = :id LIMIT 1', [':id' => $timesheetId]);
        if (!$row) {
            throw new InvalidArgumentException('No se encontró el registro de timesheet.');
        }

        $status = (string) ($row['status'] ?? 'draft');
        if (!in_array($status, ['draft', 'rejected'], true)) {
            throw new InvalidArgumentException('El estado actual no permite edición directa. Reabre la semana para continuar.');
        }

        $this->db->execute(
            'UPDATE timesheets
             SET hours = :hours,
                 approval_comment = :reason,
                 updated_at = NOW()
             WHERE id = :id',
            [
                ':hours' => $hours,
                ':reason' => $reason,
                ':id' => $timesheetId,
            ]
        );

        $this->db->execute(
            'INSERT INTO audit_log (user_id, entity, entity_id, action, payload)
             VALUES (:user_id, :entity, :entity_id, :action, :payload)',
            [
                ':user_id' => $actorUserId,
                ':entity' => 'timesheet',
                ':entity_id' => $timesheetId,
                ':action' => 'admin_hours_updated',
                ':payload' => json_encode([
                    'reason' => $reason,
                    'previous_hours' => (float) ($row['hours'] ?? 0),
                    'new_hours' => $hours,
                    'week_start' => (new \DateTimeImmutable((string) $row['date']))->modify('monday this week')->format('Y-m-d'),
                    'talent_id' => (int) ($row['talent_id'] ?? 0),
                    'target_user_id' => (int) ($row['user_id'] ?? 0),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]
        );

        return true;
    }

    public function adminDeleteTimesheet(int $timesheetId, string $reason, int $actorUserId): bool
    {
        $row = $this->db->fetchOne('SELECT id, user_id, talent_id, date, status FROM timesheets WHERE id = :id LIMIT 1', [':id' => $timesheetId]);
        if (!$row) {
            return false;
        }

        if ((string) ($row['status'] ?? '') === 'approved') {
            throw new InvalidArgumentException('No se puede eliminar un registro aprobado sin reabrir la semana.');
        }

        $this->db->execute('DELETE FROM timesheets WHERE id = :id', [':id' => $timesheetId]);
        $this->db->execute(
            'INSERT INTO audit_log (user_id, entity, entity_id, action, payload)
             VALUES (:user_id, :entity, :entity_id, :action, :payload)',
            [
                ':user_id' => $actorUserId,
                ':entity' => 'timesheet',
                ':entity_id' => $timesheetId,
                ':action' => 'admin_deleted',
                ':payload' => json_encode([
                    'reason' => $reason,
                    'week_start' => (new \DateTimeImmutable((string) $row['date']))->modify('monday this week')->format('Y-m-d'),
                    'talent_id' => (int) ($row['talent_id'] ?? 0),
                    'target_user_id' => (int) ($row['user_id'] ?? 0),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]
        );

        return true;
    }

    public function adminDeleteWeek(\DateTimeImmutable $weekStart, ?int $talentId, string $reason, int $actorUserId): int
    {
        $weekEnd = $weekStart->modify('+6 days');
        $params = [':start' => $weekStart->format('Y-m-d'), ':end' => $weekEnd->format('Y-m-d')];
        $where = ['date BETWEEN :start AND :end'];
        if ($talentId !== null && $talentId > 0) {
            $where[] = 'talent_id = :talent';
            $params[':talent'] = $talentId;
        }

        $sample = $this->db->fetchAll('SELECT DISTINCT user_id, talent_id FROM timesheets WHERE ' . implode(' AND ', $where), $params);
        $deleteStmt = $this->db->connection()->prepare('DELETE FROM timesheets WHERE ' . implode(' AND ', $where));
        $deleteStmt->execute($params);
        $affected = (int) $deleteStmt->rowCount();

        if ($affected > 0) {
            $this->db->execute(
                'INSERT INTO audit_log (user_id, entity, entity_id, action, payload)
                 VALUES (:user_id, :entity, :entity_id, :action, :payload)',
                [
                    ':user_id' => $actorUserId,
                    ':entity' => 'timesheet_week',
                    ':entity_id' => 0,
                    ':action' => 'admin_bulk_deleted',
                    ':payload' => json_encode([
                        'reason' => $reason,
                        'week_start' => $weekStart->format('Y-m-d'),
                        'week_end' => $weekEnd->format('Y-m-d'),
                        'affected_rows' => $affected,
                        'affected_talents' => $sample,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]
            );
        }

        return $affected;
    }

    public function adminReopenWeek(\DateTimeImmutable $weekStart, ?int $talentId, string $reason, int $actorUserId): int
    {
        $weekEnd = $weekStart->modify('+6 days');
        $params = [
            ':start' => $weekStart->format('Y-m-d'),
            ':end' => $weekEnd->format('Y-m-d'),
        ];
        $where = ['date BETWEEN :start AND :end'];
        if ($talentId !== null && $talentId > 0) {
            $where[] = 'talent_id = :talent';
            $params[':talent'] = $talentId;
        }

        $sample = $this->db->fetchAll(
            'SELECT DISTINCT user_id, talent_id, status
             FROM timesheets
             WHERE ' . implode(' AND ', $where) . " AND status IN ('approved','rejected','submitted','pending','pending_approval')",
            $params
        );

        $updateStmt = $this->db->connection()->prepare(
            "UPDATE timesheets
             SET status = 'draft',
                 approved_by = NULL,
                 approved_at = NULL,
                 rejected_by = NULL,
                 rejected_at = NULL,
                 updated_at = NOW()
             WHERE " . implode(' AND ', $where) . " AND status IN ('approved','rejected','submitted','pending','pending_approval')"
        );
        $updateStmt->execute($params);

        $updated = (int) $updateStmt->rowCount();

        $updated = (int) (($this->db->fetchOne('SELECT ROW_COUNT() AS total')['total'] ?? 0));

        if ($updated > 0) {
            $this->db->execute(
                'INSERT INTO audit_log (user_id, entity, entity_id, action, payload)
                 VALUES (:user_id, :entity, :entity_id, :action, :payload)',
                [
                    ':user_id' => $actorUserId,
                    ':entity' => 'timesheet_week',
                    ':entity_id' => 0,
                    ':action' => 'admin_reopened',
                    ':payload' => json_encode([
                        'reason' => $reason,
                        'week_start' => $weekStart->format('Y-m-d'),
                        'week_end' => $weekEnd->format('Y-m-d'),
                        'affected_rows' => $updated,
                        'affected_talents' => $sample,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]
            );
        }

        return $updated;
    }

    public function projectIdForTimesheet(int $timesheetId): ?int
    {
        if (!$this->db->tableExists('timesheets')) {
            return null;
        }

        $row = $this->db->fetchOne(
            'SELECT COALESCE(ts.project_id, t.project_id) AS project_id
             FROM timesheets ts
             LEFT JOIN tasks t ON t.id = ts.task_id
             WHERE ts.id = :id
             LIMIT 1',
            [':id' => $timesheetId]
        );

        return $row && isset($row['project_id']) ? (int) $row['project_id'] : null;
    }

    public function findOwnerId(int $timesheetId): ?int
    {
        if (!$this->db->tableExists('timesheets')) {
            return null;
        }

        $row = $this->db->fetchOne(
            'SELECT user_id FROM timesheets WHERE id = :id LIMIT 1',
            [':id' => $timesheetId]
        );

        return $row && isset($row['user_id']) ? (int) $row['user_id'] : null;
    }

    public function talentIdForUser(int $userId): ?int
    {
        if ($userId <= 0 || !$this->db->tableExists('talents')) {
            return null;
        }

        $row = $this->db->fetchOne(
            'SELECT id FROM talents WHERE user_id = :user LIMIT 1',
            [':user' => $userId]
        );

        if (!$row) {
            return null;
        }

        return (int) $row['id'];
    }

    public function talentProfileForUser(int $userId): ?array
    {
        if ($userId <= 0 || !$this->db->tableExists('talents')) {
            return null;
        }

        $row = $this->db->fetchOne(
            'SELECT id, requiere_reporte_horas, requiere_aprobacion_horas, timesheet_approver_user_id, is_outsourcing,
                    capacidad_horaria, weekly_capacity
             FROM talents
             WHERE user_id = :user
             LIMIT 1',
            [':user' => $userId]
        );

        return $row ?: null;
    }

    public function refreshTaskActualHours(int $taskId): void
    {
        if (!$this->db->tableExists('tasks') || !$this->db->tableExists('timesheets')) {
            return;
        }

        $taskRow = $this->db->fetchOne(
            'SELECT project_id FROM tasks WHERE id = :task LIMIT 1',
            [':task' => $taskId]
        );

        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(hours), 0) AS total
             FROM timesheets
             WHERE task_id = :task AND status = \'approved\'',
            [':task' => $taskId]
        );

        $this->db->execute(
            'UPDATE tasks SET actual_hours = :hours, updated_at = NOW() WHERE id = :task',
            [
                ':hours' => (float) ($row['total'] ?? 0),
                ':task' => $taskId,
            ]
        );

        $projectId = (int) ($taskRow['project_id'] ?? 0);
        if ($projectId > 0 && $this->db->tableExists('projects') && $this->db->columnExists('projects', 'actual_hours')) {
            $projectHours = $this->db->fetchOne(
                'SELECT COALESCE(SUM(ts.hours), 0) AS total
                 FROM timesheets ts
                 JOIN tasks t ON t.id = ts.task_id
                 WHERE t.project_id = :project AND ts.status = \'approved\'',
                [':project' => $projectId]
            );

            $this->db->execute(
                'UPDATE projects SET actual_hours = :hours, updated_at = NOW() WHERE id = :project',
                [
                    ':hours' => (float) ($projectHours['total'] ?? 0),
                    ':project' => $projectId,
                ]
            );
        }
    }

    private function structuredTimesheetSelectColumns(string $alias = ''): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        $columns = [
            'phase_name',
            'subphase_name',
            'activity_type',
            'activity_description',
            'had_blocker',
            'blocker_description',
            'had_significant_progress',
            'generated_deliverable',
            'operational_comment',
            'linked_stopper_id',
            'updated_at',
        ];

        $available = [];
        foreach ($columns as $column) {
            if ($this->db->columnExists('timesheets', $column)) {
                $available[] = $prefix . $column;
            }
        }

        return implode(', ', $available);
    }

    private function resolveTaskForEntry(int $projectId, int $candidateTaskId): int
    {
        if ($candidateTaskId > 0 && $this->taskBelongsToProject($candidateTaskId, $projectId)) {
            return $candidateTaskId;
        }

        return $this->resolveTimesheetTaskId($projectId);
    }

    private function createTaskFromTimesheetActivity(int $projectId, int $talentId, int $userId, string $rawTitle, string $taskManagementMode, array $taskPayload = []): int
    {
        if (!$this->db->tableExists('tasks')) {
            throw new InvalidArgumentException('No hay tareas disponibles para registrar horas.');
        }

        $title = $this->limitText($rawTitle, 180);
        if ($title === '') {
            $title = 'Actividad registrada desde timesheet';
        }

        $status = $taskManagementMode === 'completed' ? 'completed' : 'pending';
        $isCompleted = $status === 'completed';
        $priority = in_array($taskPayload['priority'] ?? '', ['low', 'medium', 'high'], true) ? $taskPayload['priority'] : 'medium';
        $dueDate = $taskPayload['due_date'] ?? null;
        if ($dueDate !== null && $dueDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($dueDate))) {
            $dueDate = null;
        }

        $columns = ['project_id', 'title', 'status', 'priority', 'estimated_hours', 'actual_hours', 'due_date', 'created_at', 'updated_at'];
        $values = [':project', ':title', ':status', ':priority', '0', '0', ':due_date', 'NOW()', 'NOW()'];
        $params = [
            ':project' => $projectId,
            ':title' => $title,
            ':status' => $status,
            ':priority' => $priority,
            ':due_date' => $dueDate !== null && $dueDate !== '' ? trim($dueDate) : null,
        ];

        if ($this->db->columnExists('tasks', 'assignee_id')) {
            $columns[] = 'assignee_id';
            $values[] = ':assignee_id';
            $params[':assignee_id'] = $talentId > 0 ? $talentId : null;
        }

        if ($this->db->columnExists('tasks', 'description')) {
            $columns[] = 'description';
            $values[] = ':description';
            $params[':description'] = $title;
        }

        if ($this->db->columnExists('tasks', 'completed_at')) {
            $columns[] = 'completed_at';
            $values[] = ':completed_at';
            $params[':completed_at'] = $isCompleted ? date('Y-m-d H:i:s') : null;
        }

        return (int) $this->db->insert(
            'INSERT INTO tasks (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $values) . ')',
            $params
        );
    }

    private function assertNoWeekendUnlessAdmin(string $date, int $userId): void
    {
        try {
            $dt = new \DateTimeImmutable($date);
        } catch (\Throwable $e) {
            return;
        }
        $dow = (int) $dt->format('w');
        if ($dow !== 0 && $dow !== 6) {
            return;
        }
        $user = $this->db->fetchOne('SELECT r.nombre AS role FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = :id', [':id' => $userId]);
        if ($user && in_array((string) ($user['role'] ?? ''), self::ADMIN_ROLES, true)) {
            return;
        }
        throw new InvalidArgumentException('No se puede registrar horas en sábado ni domingo.');
    }

    private function ensureStopperFromTimesheetBlocker(int $projectId, int $userId, string $blockerDescription, int $taskId, string $date): ?int
    {
        if (!$this->db->tableExists('project_stoppers')) {
            return null;
        }
        $title = $this->limitText($blockerDescription, 150);
        if ($title === '') {
            $title = 'Bloqueo reportado desde timesheet';
        }
        $existing = $this->db->fetchOne(
            'SELECT id FROM project_stoppers
             WHERE project_id = :project AND status IN (\'abierto\', \'en_gestion\', \'escalado\')
               AND title = :title
               AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             LIMIT 1',
            [':project' => $projectId, ':title' => $title]
        );
        if ($existing) {
            return (int) $existing['id'];
        }
        $detectedAt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
        $estimatedAt = date('Y-m-d', strtotime($detectedAt . ' +7 days'));
        $stoppersRepo = new ProjectStoppersRepository($this->db);
        return $stoppersRepo->create($projectId, [
            'title' => $title,
            'description' => $blockerDescription,
            'stopper_type' => 'tecnico',
            'impact_level' => 'medio',
            'affected_area' => 'tiempo',
            'responsible_id' => $userId,
            'detected_at' => $detectedAt,
            'estimated_resolution_at' => $estimatedAt,
            'status' => 'abierto',
        ], $userId);
    }

    private function taskBelongsToProject(int $taskId, int $projectId): bool
    {
        if (!$this->db->tableExists('tasks')) {
            return false;
        }

        $row = $this->db->fetchOne(
            'SELECT 1 FROM tasks WHERE id = :task AND project_id = :project LIMIT 1',
            [':task' => $taskId, ':project' => $projectId]
        );

        return $row !== null;
    }

    private function findUserActivity(int $activityId, int $userId, bool $editableOnly): ?array
    {
        if ($activityId <= 0 || $userId <= 0) {
            return null;
        }

        $structuredSelect = $this->structuredTimesheetSelectColumns('ts');
        $structuredSegment = $structuredSelect !== '' ? ', ' . $structuredSelect : '';
        $row = $this->db->fetchOne(
            'SELECT ts.id, ts.task_id, ts.project_id, ts.user_id, ts.date, ts.hours, ts.status, ts.comment' . $structuredSegment . ',
                    COALESCE(ts.project_id, t.project_id) AS resolved_project_id
             FROM timesheets ts
             LEFT JOIN tasks t ON t.id = ts.task_id
             WHERE ts.id = :id
               AND ts.user_id = :user
             LIMIT 1',
            [
                ':id' => $activityId,
                ':user' => $userId,
            ]
        );
        if (!$row) {
            return null;
        }

        if ($editableOnly && !in_array((string) ($row['status'] ?? ''), ['draft', 'rejected'], true)) {
            return null;
        }

        return $row;
    }

    private function assertValidDate(string $date): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException('Fecha inválida.');
        }

        try {
            new \DateTimeImmutable($date);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Fecha inválida.');
        }
    }

    private function assertWeekIsEditable(int $userId, string $date): void
    {
        $this->assertValidDate($date);
        try {
            $day = new \DateTimeImmutable($date);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Fecha inválida.');
        }

        $weekStart = $day->modify('monday this week')->setTime(0, 0);
        $summary = $this->weekSummaryForUser($userId, $weekStart);
        $status = (string) ($summary['status'] ?? 'draft');
        if (in_array($status, ['submitted', 'approved'], true)) {
            throw new InvalidArgumentException('Semana enviada. Registros bloqueados.');
        }
    }

    private function structuredColumnMap(): array
    {
        return [
            'phase_name' => 'phase_name',
            'subphase_name' => 'subphase_name',
            'activity_type' => 'activity_type',
            'activity_description' => 'activity_description',
            'had_blocker' => 'had_blocker',
            'blocker_description' => 'blocker_description',
            'had_significant_progress' => 'had_significant_progress',
            'generated_deliverable' => 'generated_deliverable',
            'operational_comment' => 'operational_comment',
        ];
    }

    private function sanitizeStructuredMetadata(array $metadata): array
    {
        $activityType = strtolower(trim((string) ($metadata['activity_type'] ?? '')));
        if ($activityType !== '' && !in_array($activityType, $this->activityTypesCatalog(), true)) {
            $activityType = '';
        }

        return [
            'task_id' => max(0, (int) ($metadata['task_id'] ?? 0)),
            'phase_name' => $this->limitText((string) ($metadata['phase_name'] ?? ''), 120),
            'subphase_name' => $this->limitText((string) ($metadata['subphase_name'] ?? ''), 120),
            'activity_type' => $this->limitText($activityType, 60),
            'activity_description' => $this->limitText((string) ($metadata['activity_description'] ?? ''), 255),
            'had_blocker' => $this->toFlag($metadata['had_blocker'] ?? 0),
            'blocker_description' => trim((string) ($metadata['blocker_description'] ?? '')),
            'had_significant_progress' => $this->toFlag($metadata['had_significant_progress'] ?? 0),
            'generated_deliverable' => $this->toFlag($metadata['generated_deliverable'] ?? 0),
            'operational_comment' => trim((string) ($metadata['operational_comment'] ?? '')),
        ];
    }

    private function toFlag(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'on', 'yes', 'si'], true) ? 1 : 0;
    }

    private function limitText(string $value, int $maxLength): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        return strlen($trimmed) > $maxLength ? substr($trimmed, 0, $maxLength) : $trimmed;
    }

    private function mergeTimesheetStatus(string $currentStatus, string $incomingStatus): string
    {
        $normalize = static function (string $status): string {
            if (in_array($status, ['submitted', 'pending_approval'], true)) {
                return 'pending';
            }

            return $status;
        };
        $weights = [
            'approved' => 5,
            'rejected' => 4,
            'pending' => 3,
            'draft' => 2,
        ];

        $current = $normalize($currentStatus);
        $incoming = $normalize($incomingStatus);
        $currentWeight = $weights[$current] ?? 1;
        $incomingWeight = $weights[$incoming] ?? 1;

        return $incomingWeight > $currentWeight ? $incoming : $current;
    }

    private function syncOperationalArtifacts(int $timesheetId, int $actorUserId): void
    {
        if (!$this->db->tableExists('timesheets')) {
            return;
        }

        $requiredColumns = [
            'phase_name',
            'subphase_name',
            'activity_type',
            'activity_description',
            'had_blocker',
            'blocker_description',
            'had_significant_progress',
            'generated_deliverable',
            'operational_comment',
            'linked_stopper_id',
        ];
        foreach ($requiredColumns as $column) {
            if (!$this->db->columnExists('timesheets', $column)) {
                return;
            }
        }

        $row = $this->db->fetchOne(
            'SELECT ts.id, ts.project_id, ts.task_id, ts.user_id, ts.date, ts.hours, ts.comment,
                    ts.phase_name, ts.subphase_name, ts.activity_type, ts.activity_description,
                    ts.had_blocker, ts.blocker_description, ts.had_significant_progress,
                    ts.generated_deliverable, ts.operational_comment, ts.linked_stopper_id,
                    p.name AS project_name, tk.title AS task_title, u.name AS user_name
             FROM timesheets ts
             LEFT JOIN projects p ON p.id = ts.project_id
             LEFT JOIN tasks tk ON tk.id = ts.task_id
             LEFT JOIN users u ON u.id = ts.user_id
             WHERE ts.id = :id
             LIMIT 1',
            [':id' => $timesheetId]
        );

        if (!$row) {
            return;
        }

        $projectId = (int) ($row['project_id'] ?? 0);
        if ($projectId <= 0) {
            return;
        }

        $authorUserId = (int) ($row['user_id'] ?? 0);
        $actor = $actorUserId > 0 ? $actorUserId : $authorUserId;

        (new AuditLogRepository($this->db))->log(
            $actor > 0 ? $actor : null,
            'project_note',
            $projectId,
            'project_note_created',
            [
                'note' => $this->buildOperationalNote($row),
                'source' => 'timesheet',
                'timesheet_id' => (int) ($row['id'] ?? 0),
                'activity_type' => $row['activity_type'] ?? null,
            ]
        );

        $hasBlocker = (int) ($row['had_blocker'] ?? 0) === 1;
        $blockerDescription = trim((string) ($row['blocker_description'] ?? ''));
        if (!$hasBlocker || $blockerDescription === '' || !$this->db->tableExists('project_stoppers')) {
            return;
        }

        $responsibleId = $this->resolveExistingUserId($authorUserId)
            ?? $this->resolveExistingUserId($actorUserId)
            ?? 1;
        $linkedStopperId = (int) ($row['linked_stopper_id'] ?? 0);

        if ($linkedStopperId > 0) {
            $this->db->execute(
                'UPDATE project_stoppers
                 SET description = :description,
                     updated_by = :updated_by,
                     updated_at = NOW()
                 WHERE id = :id AND project_id = :project_id',
                [
                    ':description' => $blockerDescription,
                    ':updated_by' => $responsibleId,
                    ':id' => $linkedStopperId,
                    ':project_id' => $projectId,
                ]
            );
            return;
        }

        $detectedAt = (string) ($row['date'] ?? date('Y-m-d'));
        $estimatedResolution = (new \DateTimeImmutable($detectedAt))->modify('+3 days')->format('Y-m-d');
        $activityContext = trim((string) ($row['activity_description'] ?? '')) ?: trim((string) ($row['activity_type'] ?? ''));
        $stopperTitle = 'Bloqueo Timesheet';
        if ($activityContext !== '') {
            $stopperTitle .= ': ' . $this->limitText($activityContext, 120);
        }

        $stopperId = (new ProjectStoppersRepository($this->db))->create(
            $projectId,
            [
                'title' => $stopperTitle,
                'description' => $blockerDescription,
                'stopper_type' => 'interno',
                'impact_level' => 'medio',
                'affected_area' => 'tiempo',
                'responsible_id' => $responsibleId,
                'detected_at' => $detectedAt,
                'estimated_resolution_at' => $estimatedResolution,
                'status' => ProjectStoppersRepository::STATUS_OPEN,
            ],
            $responsibleId
        );

        if ($stopperId > 0 && $this->db->columnExists('timesheets', 'linked_stopper_id')) {
            $this->db->execute(
                'UPDATE timesheets SET linked_stopper_id = :stopper WHERE id = :id',
                [':stopper' => $stopperId, ':id' => $timesheetId]
            );
        }
    }

    private function buildOperationalNote(array $row): string
    {
        $author = trim((string) ($row['user_name'] ?? 'Talento'));
        $hours = round((float) ($row['hours'] ?? 0), 2);
        $description = trim((string) ($row['activity_description'] ?? ''));
        if ($description === '') {
            $description = trim((string) ($row['comment'] ?? ''));
        }
        if ($description === '') {
            $description = trim((string) ($row['task_title'] ?? 'Actividad operativa'));
        }

        $parts = [];
        $parts[] = sprintf('Actividad registrada por %s: %s - %sh', $author, $description, rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.'));
        if (trim((string) ($row['project_name'] ?? '')) !== '') {
            $parts[] = 'Proyecto: ' . trim((string) ($row['project_name'] ?? ''));
        }
        if (trim((string) ($row['task_title'] ?? '')) !== '') {
            $parts[] = 'Tarea: ' . trim((string) ($row['task_title'] ?? ''));
        }
        if (trim((string) ($row['phase_name'] ?? '')) !== '') {
            $phaseLine = 'Fase: ' . trim((string) ($row['phase_name'] ?? ''));
            if (trim((string) ($row['subphase_name'] ?? '')) !== '') {
                $phaseLine .= ' / ' . trim((string) ($row['subphase_name'] ?? ''));
            }
            $parts[] = $phaseLine;
        }
        if (trim((string) ($row['activity_type'] ?? '')) !== '') {
            $parts[] = 'Tipo: ' . trim((string) ($row['activity_type'] ?? ''));
        }
        if ((int) ($row['had_blocker'] ?? 0) === 1 && trim((string) ($row['blocker_description'] ?? '')) !== '') {
            $parts[] = 'Bloqueo: ' . trim((string) ($row['blocker_description'] ?? ''));
        }
        if ((int) ($row['had_significant_progress'] ?? 0) === 1) {
            $parts[] = 'Avance significativo: si';
        }
        if ((int) ($row['generated_deliverable'] ?? 0) === 1) {
            $parts[] = 'Entregable generado: si';
        }
        if (trim((string) ($row['operational_comment'] ?? '')) !== '') {
            $parts[] = 'Comentario operativo: ' . trim((string) ($row['operational_comment'] ?? ''));
        }

        return implode("\n", $parts);
    }

    private function resolveExistingUserId(int $userId): ?int
    {
        if ($userId <= 0 || !$this->db->tableExists('users')) {
            return null;
        }

        $row = $this->db->fetchOne('SELECT id FROM users WHERE id = :id LIMIT 1', [':id' => $userId]);

        return $row ? (int) $row['id'] : null;
    }


    private function timesheetScopeWhere(array $user, array &$params): array
    {
        $where = [];
        $hasPmColumn = $this->db->columnExists('projects', 'pm_id');
        $talentId = $this->talentIdForUser((int) ($user['id'] ?? 0));

        if (!$this->isPrivileged($user)) {
            if ($talentId !== null) {
                $where[] = 'ts.talent_id = :scopeTalentId';
                $params[':scopeTalentId'] = $talentId;
            } elseif ($hasPmColumn) {
                $where[] = 'p.pm_id = :scopePmId';
                $params[':scopePmId'] = (int) ($user['id'] ?? 0);
            }
        }

        return $where;
    }

    private function capacityForScope(array $user, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd, ?int $projectId = null): float
    {
        $monthWeeks = (int) ceil(((int) $periodEnd->format('j')) / 7);
        $multiplier = max(1, $monthWeeks);

        if ($this->isPrivileged($user)) {
            $params = [];
            $query = 'SELECT COALESCE(SUM(COALESCE(capacidad_horaria, weekly_capacity, 0)), 0) AS total FROM talents';
            if ($projectId !== null && $projectId > 0 && $this->db->tableExists('project_talent_assignments')) {
                $query = 'SELECT COALESCE(SUM(COALESCE(t.capacidad_horaria, t.weekly_capacity, 0)), 0) AS total
                          FROM project_talent_assignments a
                          JOIN talents t ON t.user_id = a.user_id
                          WHERE a.project_id = :project
                            AND (a.assignment_status = "active" OR (a.assignment_status IS NULL AND a.active = 1))';
                $params[':project'] = $projectId;
            }
            $row = $this->db->fetchOne($query, $params);
            return (float) ($row['total'] ?? 0) * $multiplier;
        }

        $profile = $this->talentProfileForUser((int) ($user['id'] ?? 0)) ?? [];
        $weeklyCapacity = (float) ($profile['capacidad_horaria'] ?? $profile['weekly_capacity'] ?? 0);
        return max(0, $weeklyCapacity) * $multiplier;
    }

    private function isPrivileged(array $user): bool
    {
        return in_array($user['role'] ?? '', self::ADMIN_ROLES, true);
    }

    private function activeProjectCondition(string $alias): string
    {
        $conditions = [];
        if ($this->db->columnExists('projects', 'status_code')) {
            $conditions[] = $alias . '.status_code NOT IN ("closed", "cancelled")';
        }
        if ($this->db->columnExists('projects', 'status')) {
            $conditions[] = $alias . '.status NOT IN ("closed", "cancelled")';
        }
        if ($conditions === []) {
            return '';
        }

        return implode(' AND ', $conditions);
    }
}
