<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;
use InvalidArgumentException;

class TimesheetsRepository
{
    private const ADMIN_ROLES = ['Administrador', 'PMO'];

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
            $entries = $this->db->fetchAll(
                'SELECT id, project_id, date, hours, status, comment
                 FROM timesheets
                 WHERE user_id = :user
                   AND date BETWEEN :start AND :end',
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
        foreach ($entries as $entry) {
            $projectId = (int) ($entry['project_id'] ?? 0);
            $date = (string) ($entry['date'] ?? '');
            if (!isset($projectMap[$projectId]) || !isset($dayTotals[$date])) {
                continue;
            }
            $cells[$projectId][$date] = [
                'id' => (int) ($entry['id'] ?? 0),
                'hours' => (float) ($entry['hours'] ?? 0),
                'status' => (string) ($entry['status'] ?? 'draft'),
                'comment' => (string) ($entry['comment'] ?? ''),
            ];
            $dayTotals[$date] += (float) ($entry['hours'] ?? 0);
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

        return [
            'days' => $days,
            'rows' => $rows,
            'day_totals' => $dayTotals,
            'week_total' => array_sum($dayTotals),
            'weekly_capacity' => $weeklyCapacity,
            'requires_full_report' => (int) ($profile['requiere_reporte_horas'] ?? 0) === 1,
        ];
    }

    public function upsertDraftCell(int $userId, int $projectId, string $date, float $hours, string $comment = ''): array
    {
        $assignment = $this->assignmentForProject($projectId, $userId);
        if (!$assignment) {
            throw new InvalidArgumentException('Proyecto no asignado o inactivo para este talento.');
        }

        $talentId = (int) ($assignment['talent_id'] ?? 0);
        if ($talentId <= 0) {
            throw new InvalidArgumentException('Tu usuario no tiene un talento asociado.');
        }

        $existing = $this->db->fetchOne(
            'SELECT id, status, task_id, hours
             FROM timesheets
             WHERE user_id = :user AND project_id = :project AND date = :date
             LIMIT 1',
            [':user' => $userId, ':project' => $projectId, ':date' => $date]
        );

        if ($existing && !in_array((string) ($existing['status'] ?? ''), ['draft'], true)) {
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
        $taskId = $this->resolveTimesheetTaskId($projectId);

        if ($existing) {
            $this->db->execute(
                'UPDATE timesheets
                 SET hours = :hours,
                     comment = :comment,
                     status = :status,
                     approver_user_id = :approver,
                     updated_at = NOW()
                 WHERE id = :id',
                [
                    ':hours' => $hours,
                    ':comment' => $comment,
                    ':status' => 'draft',
                    ':approver' => $approver,
                    ':id' => (int) $existing['id'],
                ]
            );
            return ['id' => (int) $existing['id'], 'updated' => true];
        }

        $id = $this->createTimesheet([
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
        ]);

        return ['id' => $id, 'updated' => false];
    }

    public function submitWeek(int $userId, \DateTimeImmutable $weekStart): int
    {
        $weekEnd = $weekStart->modify('+6 days');
        $profile = $this->talentProfileForUser($userId) ?? [];
        $requiresFull = (int) ($profile['requiere_reporte_horas'] ?? 0) === 1;

        if ($requiresFull) {
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

        $row = $this->db->fetchOne('SELECT ROW_COUNT() AS total');
        return (int) ($row['total'] ?? 0);
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

        return (int) $this->db->insert(
            'INSERT INTO tasks (project_id, title, status, priority, estimated_hours, actual_hours, created_at, updated_at)
             VALUES (:project, :title, :status, :priority, 0, 0, NOW(), NOW())',
            [
                ':project' => $projectId,
                ':title' => 'Registro de horas',
                ':status' => 'todo',
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

        $hasActivityType = $this->db->columnExists('timesheets', 'activity_type');
        $extraCols = $hasActivityType ? ', ts.activity_type, ts.description' : '';

        return $this->db->fetchAll(
            'SELECT ts.id, ts.date, ts.hours, ts.status, ts.comment, ts.user_id, ts.talent_id,
                    COALESCE(ts.project_id, t.project_id) AS project_id,
                    p.name AS project_name, ta.name AS talent_name, u.name AS user_name' . $extraCols . '
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

        $cols = 'id, requiere_reporte_horas, requiere_aprobacion_horas, timesheet_approver_user_id';
        if ($this->db->columnExists('talents', 'is_outsourcing')) {
            $cols .= ', is_outsourcing';
        }
        if ($this->db->columnExists('talents', 'capacidad_horaria')) {
            $cols .= ', capacidad_horaria';
        }
        if ($this->db->columnExists('talents', 'weekly_capacity')) {
            $cols .= ', weekly_capacity';
        }

        $row = $this->db->fetchOne(
            "SELECT {$cols} FROM talents WHERE user_id = :user LIMIT 1",
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

    // ── Activity-based Timesheet methods ──

    public function activityTypes(): array
    {
        if (!$this->db->tableExists('timesheet_activity_types')) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT id, code, name, color, icon FROM timesheet_activity_types WHERE active = 1 ORDER BY sort_order ASC'
        );
    }

    public function activitiesForWeek(int $userId, \DateTimeImmutable $weekStart): array
    {
        $weekEnd = $weekStart->modify('+6 days');
        $hasActivityType = $this->db->columnExists('timesheets', 'activity_type');

        $select = 'ts.id, ts.date, ts.hours, ts.status, ts.comment, ts.project_id,
                   p.name AS project_name, t.title AS task_name, ta.name AS talent_name';
        if ($hasActivityType) {
            $select .= ', ts.activity_type, ts.description, ts.phase, ts.subphase,
                         ts.has_blocker, ts.blocker_description, ts.has_significant_progress,
                         ts.has_deliverable, ts.operational_comment';
        }

        return $this->db->fetchAll(
            "SELECT {$select}
             FROM timesheets ts
             LEFT JOIN projects p ON p.id = ts.project_id
             LEFT JOIN tasks t ON t.id = ts.task_id
             LEFT JOIN talents ta ON ta.id = ts.talent_id
             WHERE ts.user_id = :user AND ts.date BETWEEN :start AND :end
             ORDER BY ts.date ASC, ts.id ASC",
            [':user' => $userId, ':start' => $weekStart->format('Y-m-d'), ':end' => $weekEnd->format('Y-m-d')]
        );
    }

    public function createActivityEntry(int $userId, array $data): int
    {
        $projectId = (int) ($data['project_id'] ?? 0);
        $assignment = $this->assignmentForProject($projectId, $userId);
        if (!$assignment) {
            throw new InvalidArgumentException('Proyecto no asignado o inactivo para este talento.');
        }

        $talentId = (int) ($assignment['talent_id'] ?? 0);
        if ($talentId <= 0) {
            throw new InvalidArgumentException('Tu usuario no tiene un talento asociado.');
        }

        $date = (string) ($data['date'] ?? '');
        $hours = (float) ($data['hours'] ?? 0);

        $dayTotalRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(hours), 0) AS total FROM timesheets WHERE user_id = :user AND date = :date',
            [':user' => $userId, ':date' => $date]
        );
        if ((float) ($dayTotalRow['total'] ?? 0) + $hours > 24) {
            throw new InvalidArgumentException('No puedes registrar más de 24 horas en un mismo día.');
        }

        $approverUserId = (int) ($assignment['timesheet_approver_user_id'] ?? 0);
        $approver = $approverUserId > 0 ? $approverUserId : null;
        $taskId = $this->resolveTimesheetTaskId($projectId);

        $columns = ['task_id', 'project_id', 'talent_id', 'user_id', 'assignment_id',
                     'approver_user_id', 'date', 'hours', 'status', 'comment', 'billable'];
        $params = [
            ':task_id' => $taskId,
            ':project_id' => $projectId,
            ':talent_id' => $talentId,
            ':user_id' => $userId,
            ':assignment_id' => $assignment['id'] ?? null,
            ':approver_user_id' => $approver,
            ':date' => $date,
            ':hours' => $hours,
            ':status' => 'draft',
            ':comment' => trim((string) ($data['comment'] ?? '')),
            ':billable' => 0,
        ];

        $activityFields = [
            'activity_type' => 'activity_type',
            'description' => 'description',
            'phase' => 'phase',
            'subphase' => 'subphase',
            'has_blocker' => 'has_blocker',
            'blocker_description' => 'blocker_description',
            'has_significant_progress' => 'has_significant_progress',
            'has_deliverable' => 'has_deliverable',
            'operational_comment' => 'operational_comment',
        ];

        foreach ($activityFields as $col => $key) {
            if ($this->db->columnExists('timesheets', $col)) {
                $columns[] = $col;
                if (in_array($col, ['has_blocker', 'has_significant_progress', 'has_deliverable'], true)) {
                    $params[':' . $col] = !empty($data[$key]) ? 1 : 0;
                } else {
                    $params[':' . $col] = isset($data[$key]) ? trim((string) $data[$key]) : null;
                }
            }
        }

        $columns[] = 'created_at';
        $columns[] = 'updated_at';

        $id = $this->db->insert(
            'INSERT INTO timesheets (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', array_map(fn($c) => $c === 'created_at' || $c === 'updated_at' ? 'NOW()' : ':' . $c, $columns)) . ')',
            $params
        );

        $this->refreshTaskActualHours($taskId);
        $this->triggerAutoIntegrations($id, $userId, $data, $projectId);

        return $id;
    }

    public function updateActivityEntry(int $entryId, int $userId, array $data): void
    {
        $existing = $this->db->fetchOne(
            'SELECT id, status, task_id, project_id FROM timesheets WHERE id = :id AND user_id = :user LIMIT 1',
            [':id' => $entryId, ':user' => $userId]
        );
        if (!$existing) {
            throw new InvalidArgumentException('Registro no encontrado.');
        }
        if (!in_array((string) ($existing['status'] ?? ''), ['draft', 'rejected'], true)) {
            throw new InvalidArgumentException('Este registro no es editable en su estado actual.');
        }

        $hours = (float) ($data['hours'] ?? 0);
        $date = (string) ($data['date'] ?? $existing['date'] ?? '');

        $dayTotalRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(hours), 0) AS total FROM timesheets WHERE user_id = :user AND date = :date AND id <> :id',
            [':user' => $userId, ':date' => $date, ':id' => $entryId]
        );
        if ((float) ($dayTotalRow['total'] ?? 0) + $hours > 24) {
            throw new InvalidArgumentException('No puedes registrar más de 24 horas en un mismo día.');
        }

        $sets = ['hours = :hours', 'comment = :comment', 'updated_at = NOW()'];
        $params = [
            ':hours' => $hours,
            ':comment' => trim((string) ($data['comment'] ?? '')),
            ':id' => $entryId,
        ];

        $activityFields = [
            'activity_type', 'description', 'phase', 'subphase',
            'has_blocker', 'blocker_description', 'has_significant_progress',
            'has_deliverable', 'operational_comment',
        ];
        foreach ($activityFields as $col) {
            if ($this->db->columnExists('timesheets', $col)) {
                $sets[] = "{$col} = :{$col}";
                if (in_array($col, ['has_blocker', 'has_significant_progress', 'has_deliverable'], true)) {
                    $params[':' . $col] = !empty($data[$col]) ? 1 : 0;
                } else {
                    $params[':' . $col] = isset($data[$col]) ? trim((string) $data[$col]) : null;
                }
            }
        }

        $this->db->execute(
            'UPDATE timesheets SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params
        );

        $this->refreshTaskActualHours((int) ($existing['task_id'] ?? 0));
        $this->triggerAutoIntegrations($entryId, $userId, $data, (int) ($existing['project_id'] ?? 0));
    }

    public function deleteActivityEntry(int $entryId, int $userId): void
    {
        $existing = $this->db->fetchOne(
            'SELECT id, status, task_id FROM timesheets WHERE id = :id AND user_id = :user LIMIT 1',
            [':id' => $entryId, ':user' => $userId]
        );
        if (!$existing) {
            throw new InvalidArgumentException('Registro no encontrado.');
        }
        if (!in_array((string) ($existing['status'] ?? ''), ['draft', 'rejected'], true)) {
            throw new InvalidArgumentException('No se puede eliminar un registro que ya fue enviado o aprobado.');
        }

        $this->db->execute('DELETE FROM timesheets WHERE id = :id', [':id' => $entryId]);
        $this->refreshTaskActualHours((int) ($existing['task_id'] ?? 0));
    }

    public function recentActivities(int $userId, int $limit = 5): array
    {
        $hasActivityType = $this->db->columnExists('timesheets', 'activity_type');
        $select = 'ts.project_id, p.name AS project_name, ts.hours';
        if ($hasActivityType) {
            $select .= ', ts.activity_type, ts.description';
        }
        return $this->db->fetchAll(
            "SELECT {$select}
             FROM timesheets ts
             LEFT JOIN projects p ON p.id = ts.project_id
             WHERE ts.user_id = :user
             ORDER BY ts.created_at DESC
             LIMIT :limit",
            [':user' => $userId, ':limit' => $limit]
        );
    }

    public function activityTypeBreakdown(array $user, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd, ?int $projectId = null): array
    {
        if (!$this->db->columnExists('timesheets', 'activity_type')) {
            return [];
        }
        $params = [':start' => $periodStart->format('Y-m-d'), ':end' => $periodEnd->format('Y-m-d')];
        $where = $this->timesheetScopeWhere($user, $params);
        if ($projectId !== null && $projectId > 0) {
            $where[] = 'COALESCE(ts.project_id, t.project_id) = :project';
            $params[':project'] = $projectId;
        }

        return $this->db->fetchAll(
            'SELECT COALESCE(ts.activity_type, "sin_tipo") AS activity_type,
                    COALESCE(SUM(ts.hours), 0) AS total_hours,
                    COUNT(*) AS entry_count
             FROM timesheets ts
             LEFT JOIN tasks t ON t.id = ts.task_id
             LEFT JOIN projects p ON p.id = COALESCE(ts.project_id, t.project_id)
             WHERE ts.date BETWEEN :start AND :end' . ($where ? ' AND ' . implode(' AND ', $where) : '') . '
             GROUP BY ts.activity_type
             ORDER BY total_hours DESC',
            $params
        );
    }

    public function capacityUtilization(int $userId, \DateTimeImmutable $weekStart): array
    {
        $profile = $this->talentProfileForUser($userId) ?? [];
        $weeklyCapacity = (float) ($profile['capacidad_horaria'] ?? $profile['weekly_capacity'] ?? 0);
        $weekEnd = $weekStart->modify('+6 days');

        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(hours), 0) AS total_hours
             FROM timesheets
             WHERE user_id = :user AND date BETWEEN :start AND :end',
            [':user' => $userId, ':start' => $weekStart->format('Y-m-d'), ':end' => $weekEnd->format('Y-m-d')]
        );
        $totalHours = (float) ($row['total_hours'] ?? 0);
        $utilization = $weeklyCapacity > 0 ? min(100, round(($totalHours / $weeklyCapacity) * 100, 2)) : 0;

        return [
            'weekly_capacity' => $weeklyCapacity,
            'hours_reported' => $totalHours,
            'utilization_percent' => $utilization,
            'remaining' => max(0, $weeklyCapacity - $totalHours),
        ];
    }

    public function projectPhases(int $projectId): array
    {
        if (!$this->db->tableExists('projects')) {
            return [];
        }
        $project = $this->db->fetchOne(
            'SELECT phase, methodology FROM projects WHERE id = :id LIMIT 1',
            [':id' => $projectId]
        );
        if (!$project) {
            return [];
        }
        $methodology = strtolower(trim((string) ($project['methodology'] ?? '')));
        $configRow = $this->db->fetchOne(
            "SELECT setting_value FROM config_settings WHERE setting_key = 'delivery' LIMIT 1"
        );
        $delivery = [];
        if ($configRow && !empty($configRow['setting_value'])) {
            $decoded = json_decode((string) $configRow['setting_value'], true);
            $delivery = is_array($decoded) ? $decoded : [];
        }
        $phases = [];
        if (isset($delivery['phases'][$methodology]) && is_array($delivery['phases'][$methodology])) {
            $phases = $delivery['phases'][$methodology];
        }
        return $phases;
    }

    public function tasksForProject(int $projectId): array
    {
        if (!$this->db->tableExists('tasks')) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT id, title FROM tasks WHERE project_id = :project AND status NOT IN ('done', 'closed') ORDER BY title ASC",
            [':project' => $projectId]
        );
    }

    private function triggerAutoIntegrations(int $timesheetId, int $userId, array $data, int $projectId): void
    {
        if ($projectId <= 0) {
            return;
        }

        $description = trim((string) ($data['description'] ?? ''));
        $hours = (float) ($data['hours'] ?? 0);
        $activityType = trim((string) ($data['activity_type'] ?? ''));
        $hasBlocker = !empty($data['has_blocker']);
        $blockerDesc = trim((string) ($data['blocker_description'] ?? ''));

        $talentName = '';
        $talentRow = $this->db->fetchOne(
            'SELECT ta.name FROM talents ta JOIN timesheets ts ON ts.talent_id = ta.id WHERE ts.id = :id LIMIT 1',
            [':id' => $timesheetId]
        );
        $talentName = (string) ($talentRow['name'] ?? 'Usuario');

        if ($description !== '' && $hours > 0) {
            $typeLabel = $activityType !== '' ? " ({$activityType})" : '';
            $noteText = "Actividad registrada por {$talentName}{$typeLabel}: {$description} – {$hours}h";
            $this->db->execute(
                'INSERT INTO audit_log (user_id, entity, entity_id, action, payload)
                 VALUES (:user_id, :entity, :entity_id, :action, :payload)',
                [
                    ':user_id' => $userId,
                    ':entity' => 'project_note',
                    ':entity_id' => $projectId,
                    ':action' => 'project_note_created',
                    ':payload' => json_encode([
                        'note' => $noteText,
                        'source' => 'timesheet',
                        'timesheet_id' => $timesheetId,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]
            );
        }

        if ($hasBlocker && $blockerDesc !== '' && $this->db->tableExists('project_stoppers')) {
            $this->db->insert(
                'INSERT INTO project_stoppers (
                    project_id, title, description, stopper_type, impact_level, affected_area,
                    responsible_id, detected_at, status, created_by, updated_by, created_at, updated_at
                ) VALUES (
                    :project_id, :title, :description, :stopper_type, :impact_level, :affected_area,
                    :responsible_id, :detected_at, :status, :created_by, :updated_by, NOW(), NOW()
                )',
                [
                    ':project_id' => $projectId,
                    ':title' => 'Bloqueo reportado desde Timesheet',
                    ':description' => $blockerDesc,
                    ':stopper_type' => 'operativo',
                    ':impact_level' => 'medio',
                    ':affected_area' => 'desarrollo',
                    ':responsible_id' => $userId,
                    ':detected_at' => date('Y-m-d'),
                    ':status' => 'abierto',
                    ':created_by' => $userId,
                    ':updated_by' => $userId,
                ]
            );
        }
    }

    public function talentTopLoaded(array $user, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd): array
    {
        $params = [':start' => $periodStart->format('Y-m-d'), ':end' => $periodEnd->format('Y-m-d')];
        $where = $this->timesheetScopeWhere($user, $params);
        return $this->db->fetchAll(
            'SELECT ta.name AS talent_name, COALESCE(SUM(ts.hours), 0) AS total_hours
             FROM timesheets ts
             JOIN talents ta ON ta.id = ts.talent_id
             LEFT JOIN tasks t ON t.id = ts.task_id
             LEFT JOIN projects p ON p.id = COALESCE(ts.project_id, t.project_id)
             WHERE ts.date BETWEEN :start AND :end' . ($where ? ' AND ' . implode(' AND ', $where) : '') . '
             GROUP BY ta.id, ta.name
             ORDER BY total_hours DESC
             LIMIT 5',
            $params
        );
    }

    public function projectTopConsuming(array $user, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd): array
    {
        $params = [':start' => $periodStart->format('Y-m-d'), ':end' => $periodEnd->format('Y-m-d')];
        $where = $this->timesheetScopeWhere($user, $params);
        return $this->db->fetchAll(
            'SELECT p.name AS project_name, COALESCE(SUM(ts.hours), 0) AS total_hours
             FROM timesheets ts
             LEFT JOIN tasks t ON t.id = ts.task_id
             LEFT JOIN projects p ON p.id = COALESCE(ts.project_id, t.project_id)
             WHERE ts.date BETWEEN :start AND :end' . ($where ? ' AND ' . implode(' AND ', $where) : '') . '
             GROUP BY COALESCE(ts.project_id, t.project_id), p.name
             ORDER BY total_hours DESC
             LIMIT 5',
            $params
        );
    }
}
