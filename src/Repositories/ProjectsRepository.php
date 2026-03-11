<?php

declare(strict_types=1);

namespace App\Repositories;

use ConfigService;
use Database;
use InvalidArgumentException;
use TalentAvailabilityService;

class ProjectsRepository
{
    private const ADMIN_ROLES = ['Administrador', 'PMO'];

    private array $signalRules;
    private ?array $assignmentCostTypeDefinition = null;
    private ?TalentAvailabilityService $talentAvailabilityService = null;

    public function __construct(private Database $db)
    {
        $config = (new ConfigService($this->db))->getConfig();
        $this->signalRules = $config['operational_rules']['semaforization'] ?? [];
    }

    public function summary(array $user, array $filters = []): array
    {
        $conditions = [];
        $params = [];
        $hasPmColumn = $this->db->columnExists('projects', 'pm_id');
        $hasStatusColumn = $this->db->columnExists('projects', 'status_code');
        $hasHealthColumn = $this->db->columnExists('projects', 'health_code');
        $hasPriorityColumn = $this->db->columnExists('projects', 'priority_code');
        $hasPriorityTextColumn = $this->db->columnExists('projects', 'priority');
        $hasStatusTextColumn = $this->db->columnExists('projects', 'status');
        $hasHealthTextColumn = $this->db->columnExists('projects', 'health');
        $hasRiskScoreColumn = $this->db->columnExists('projects', 'risk_score');
        $hasRiskLevelColumn = $this->db->columnExists('projects', 'risk_level');
        $hasActiveColumn = $this->db->columnExists('projects', 'active');
        $hasBillableColumn = $this->db->columnExists('projects', 'is_billable');
        $hasAuditLogTable = $this->db->tableExists('audit_log');
        $hasProjectStoppersTable = $this->db->tableExists('project_stoppers');
        $hasProjectPmoSnapshots = $this->db->tableExists('project_pmo_snapshots');
        $hasTasksTable = $this->db->tableExists('tasks');
        $hasTimesheetsTable = $this->db->tableExists('timesheets');
        $timesheetsHasProjectColumn = $hasTimesheetsTable && $this->db->columnExists('timesheets', 'project_id');
        $timesheetsCanResolveFromTasks = !$timesheetsHasProjectColumn
            && $hasTimesheetsTable
            && $hasTasksTable
            && $this->db->columnExists('timesheets', 'task_id')
            && $this->db->columnExists('tasks', 'project_id');
        $tasksHasStatusColumn = $hasTasksTable && $this->db->columnExists('tasks', 'status');
        $tasksHasDueDateColumn = $hasTasksTable && $this->db->columnExists('tasks', 'due_date');
        $tasksHasEstimatedColumn = $hasTasksTable && $this->db->columnExists('tasks', 'estimated_hours');

        if ($hasPmColumn && !$this->isPrivileged($user)) {
            $conditions[] = 'p.pm_id = :pmId';
            $params[':pmId'] = $user['id'];
        }

        if (!empty($filters['client_id'])) {
            $conditions[] = 'p.client_id = :clientId';
            $params[':clientId'] = (int) $filters['client_id'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = 'p.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['project_stage'])) {
            $conditions[] = 'p.project_stage = :projectStage';
            $params[':projectStage'] = $filters['project_stage'];
        }

        if (!empty($filters['methodology'])) {
            $conditions[] = 'p.methodology = :methodology';
            $params[':methodology'] = $filters['methodology'];
        }

        if (($filters['billable'] ?? '') === 'yes' && $hasBillableColumn) {
            $conditions[] = 'p.is_billable = 1';
        }

        if (($filters['billable'] ?? '') === 'no' && $hasBillableColumn) {
            $conditions[] = 'p.is_billable = 0';
        }

        if (!empty($filters['start_date'])) {
            $conditions[] = 'p.start_date >= :startDate';
            $params[':startDate'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $conditions[] = 'p.end_date <= :endDate';
            $params[':endDate'] = $filters['end_date'];
        }

        if ($hasActiveColumn) {
            $conditions[] = 'p.active = 1';
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $select = [
            'p.id',
            'p.name',
            'p.progress',
            'p.budget',
            'p.actual_cost',
            'p.planned_hours',
            'p.actual_hours',
            'p.methodology',
            'p.phase',
            'p.project_stage',
            'prisk.risks AS risk_codes',
            'c.name AS client',
            $hasBillableColumn ? 'p.is_billable' : '0 AS is_billable',
            $hasAuditLogTable ? 'COALESCE(pnotes.notes_count, 0) AS notes_count' : '0 AS notes_count',
            $hasProjectStoppersTable ? 'COALESCE(pstop.total_count, 0) AS blockers_count' : '0 AS blockers_count',
            $hasProjectStoppersTable ? 'COALESCE(pstop.critical_count, 0) AS blocker_critical_count' : '0 AS blocker_critical_count',
            $hasProjectStoppersTable ? 'COALESCE(pstop.high_count, 0) AS blocker_high_count' : '0 AS blocker_high_count',
            ($hasTimesheetsTable && ($timesheetsHasProjectColumn || $timesheetsCanResolveFromTasks))
                ? 'COALESCE(ptsh.logged_hours, p.actual_hours, 0) AS timesheet_hours_logged'
                : 'COALESCE(p.actual_hours, 0) AS timesheet_hours_logged',
            $hasTasksTable
                ? 'COALESCE(ptask.estimated_hours, 0) AS hours_estimated_total'
                : 'COALESCE(p.planned_hours, 0) AS hours_estimated_total',
            $hasTasksTable ? 'COALESCE(ptask.total_tasks, 0) AS tasks_total_auto' : '0 AS tasks_total_auto',
            $hasTasksTable ? 'COALESCE(ptask.completed_tasks, 0) AS tasks_completed_auto' : '0 AS tasks_completed_auto',
            $hasTasksTable ? 'COALESCE(ptask.overdue_tasks, 0) AS tasks_overdue_auto' : '0 AS tasks_overdue_auto',
            $hasProjectPmoSnapshots ? 'ppmo.progress_hours AS progress_hours_auto' : 'NULL AS progress_hours_auto',
            $hasProjectPmoSnapshots ? 'ppmo.progress_tasks AS progress_tasks_auto' : 'NULL AS progress_tasks_auto',
            $hasProjectPmoSnapshots ? 'ppmo.risk_score AS pmo_risk_score' : 'NULL AS pmo_risk_score',
        ];

        $joins = [
            'JOIN clients c ON c.id = p.client_id',
            'LEFT JOIN (SELECT project_id, GROUP_CONCAT(risk_code) AS risks FROM project_risk_evaluations WHERE selected = 1 GROUP BY project_id) prisk ON prisk.project_id = p.id',
        ];

        if ($hasAuditLogTable) {
            $joins[] = "LEFT JOIN (
                SELECT entity_id AS project_id, COUNT(*) AS notes_count
                FROM audit_log
                WHERE entity = 'project_note' AND action = 'project_note_created'
                GROUP BY entity_id
            ) pnotes ON pnotes.project_id = p.id";
        }

        if ($hasProjectStoppersTable) {
            $joins[] = "LEFT JOIN (
                SELECT project_id,
                       COUNT(*) AS total_count,
                       SUM(CASE WHEN impact_level = 'critico' THEN 1 ELSE 0 END) AS critical_count,
                       SUM(CASE WHEN impact_level = 'alto' THEN 1 ELSE 0 END) AS high_count
                FROM project_stoppers
                WHERE status <> 'cerrado'
                GROUP BY project_id
            ) pstop ON pstop.project_id = p.id";
        }

        if ($hasTimesheetsTable && ($timesheetsHasProjectColumn || $timesheetsCanResolveFromTasks)) {
            $timesheetProjectExpr = $timesheetsHasProjectColumn ? 'ts.project_id' : 'tk.project_id';
            $timesheetTaskJoin = $timesheetsHasProjectColumn ? '' : 'LEFT JOIN tasks tk ON tk.id = ts.task_id';
            $joins[] = "LEFT JOIN (
                SELECT {$timesheetProjectExpr} AS project_id,
                       COALESCE(SUM(ts.hours), 0) AS logged_hours
                FROM timesheets ts
                {$timesheetTaskJoin}
                GROUP BY {$timesheetProjectExpr}
            ) ptsh ON ptsh.project_id = p.id";
        }

        if ($hasTasksTable) {
            $estimatedExpr = $tasksHasEstimatedColumn
                ? 'COALESCE(SUM(COALESCE(tk.estimated_hours, 0)), 0)'
                : '0';
            $doneCondition = $tasksHasStatusColumn
                ? 'LOWER(TRIM(COALESCE(tk.status, ""))) IN ("done", "completed", "completada")'
                : '0 = 1';
            $overdueCondition = ($tasksHasStatusColumn && $tasksHasDueDateColumn)
                ? 'tk.due_date IS NOT NULL AND tk.due_date < CURDATE() AND LOWER(TRIM(COALESCE(tk.status, ""))) NOT IN ("done", "completed", "completada")'
                : '0 = 1';
            $joins[] = "LEFT JOIN (
                SELECT tk.project_id,
                       {$estimatedExpr} AS estimated_hours,
                       COUNT(*) AS total_tasks,
                       SUM(CASE WHEN {$doneCondition} THEN 1 ELSE 0 END) AS completed_tasks,
                       SUM(CASE WHEN {$overdueCondition} THEN 1 ELSE 0 END) AS overdue_tasks
                FROM tasks tk
                GROUP BY tk.project_id
            ) ptask ON ptask.project_id = p.id";
        }

        if ($hasProjectPmoSnapshots) {
            $joins[] = "LEFT JOIN (
                SELECT ps.project_id, ps.progress_hours, ps.progress_tasks, ps.risk_score
                FROM project_pmo_snapshots ps
                JOIN (
                    SELECT project_id, MAX(snapshot_date) AS latest_date
                    FROM project_pmo_snapshots
                    GROUP BY project_id
                ) latest ON latest.project_id = ps.project_id AND latest.latest_date = ps.snapshot_date
            ) ppmo ON ppmo.project_id = p.id";
        }

        if ($hasRiskScoreColumn) {
            $select[] = 'p.risk_score';
        } else {
            $select[] = 'NULL AS risk_score';
        }

        if ($hasRiskLevelColumn) {
            $select[] = 'p.risk_level';
        } else {
            $select[] = 'NULL AS risk_level';
        }

        if ($hasPmColumn) {
            $select[] = 'p.pm_id';
            $select[] = 'u.name AS pm_name';
            $joins[] = 'LEFT JOIN users u ON u.id = p.pm_id';
        } else {
            $select[] = 'NULL AS pm_id';
            $select[] = 'NULL AS pm_name';
        }

        if ($hasPriorityColumn) {
            $select[] = 'p.priority_code AS priority';
            $select[] = 'pr.label AS priority_label';
            $joins[] = 'LEFT JOIN priorities pr ON pr.code = p.priority_code';
        } elseif ($hasPriorityTextColumn) {
            $select[] = 'p.priority AS priority';
            $select[] = 'p.priority AS priority_label';
        } else {
            $select[] = 'NULL AS priority';
            $select[] = 'NULL AS priority_label';
        }

        if ($hasStatusColumn) {
            $select[] = 'p.status_code AS status';
            $select[] = 'st.label AS status_label';
            $joins[] = 'LEFT JOIN project_status st ON st.code = p.status_code';
        } elseif ($hasStatusTextColumn) {
            $select[] = 'p.status AS status';
            $select[] = 'p.status AS status_label';
        } else {
            $select[] = "'' AS status";
            $select[] = 'NULL AS status_label';
        }

        if ($hasHealthColumn) {
            $select[] = 'p.health_code AS health';
            $select[] = 'h.label AS health_label';
            $joins[] = 'LEFT JOIN project_health h ON h.code = p.health_code';
        } elseif ($hasHealthTextColumn) {
            $select[] = 'p.health AS health';
            $select[] = 'p.health AS health_label';
        } else {
            $select[] = 'NULL AS health';
            $select[] = 'NULL AS health_label';
        }

        $sql = sprintf(
            'SELECT %s FROM projects p %s %s ORDER BY p.created_at DESC',
            implode(', ', $select),
            implode(' ', $joins),
            $whereClause
        );

        $projects = $this->db->fetchAll($sql, $params);
        $projectIds = array_values(array_filter(array_map(static fn (array $project): int => (int) ($project['id'] ?? 0), $projects)));

        $latestNotesByProject = $this->latestNotesByProject($projectIds);
        $activeStoppersByProject = $this->activeStoppersByProject($projectIds);
        $scopeChangesByProject = $this->scopeChangesByProject($projectIds);
        $progressByProject = $this->progressActivityByProject($projectIds);

        return array_map(function (array $project) use ($latestNotesByProject, $activeStoppersByProject, $scopeChangesByProject, $progressByProject): array {
            $projectId = (int) ($project['id'] ?? 0);
            $noteData = $latestNotesByProject[$projectId] ?? [];
            $noteData['count'] = (int) ($project['notes_count'] ?? 0);
            $stopperData = [
                'total_count' => (int) ($project['blockers_count'] ?? 0),
                'critical_count' => (int) ($project['blocker_critical_count'] ?? 0),
                'high_count' => (int) ($project['blocker_high_count'] ?? 0),
            ];

            if (isset($activeStoppersByProject[$projectId])) {
                $stopperData = array_merge($stopperData, $activeStoppersByProject[$projectId]);
            }

            $project = $this->enrichPmoIndicators($project);

            return $this->attachProjectOperationalData(
                $this->attachProjectSignal($project),
                $noteData,
                $stopperData,
                $scopeChangesByProject[$projectId] ?? false,
                $progressByProject[$projectId] ?? null
            );
        }, $projects);
    }

    public function find(int $id): ?array
    {
        $project = $this->db->fetchOne('SELECT * FROM projects WHERE id = :id LIMIT 1', [':id' => $id]);

        return $project ?: null;
    }

    public function findForUser(int $id, array $user): ?array
    {
        [$conditions, $params] = $this->visibilityConditions($user, 'c', 'p');
        $conditions[] = 'p.id = :projectId';
        $params[':projectId'] = $id;

        $hasStatusColumn = $this->db->columnExists('projects', 'status_code');
        $hasHealthColumn = $this->db->columnExists('projects', 'health_code');
        $hasPriorityColumn = $this->db->columnExists('projects', 'priority_code');
        $hasPriorityTextColumn = $this->db->columnExists('projects', 'priority');
        $hasStatusTextColumn = $this->db->columnExists('projects', 'status');
        $hasHealthTextColumn = $this->db->columnExists('projects', 'health');
        $hasTypeColumn = $this->db->columnExists('projects', 'project_type');
        $hasRiskScoreColumn = $this->db->columnExists('projects', 'risk_score');
        $hasRiskLevelColumn = $this->db->columnExists('projects', 'risk_level');
        $hasClientLogoColumn = $this->db->columnExists('clients', 'logo_path');
        $isoColumns = $this->isoFlagColumns();

        $select = [
            'p.id',
            'p.client_id',
            'p.name',
            'p.progress',
            'p.budget',
            'p.actual_cost',
            'p.planned_hours',
            'p.actual_hours',
            'p.start_date',
            'p.end_date',
            'p.pm_id',
            'u.name AS pm_name',
            'p.methodology',
            'p.phase',
            'p.project_stage',
            'prisk.risks AS risk_codes',
            'c.name AS client_name',
            $hasClientLogoColumn ? 'c.logo_path AS client_logo_path' : 'NULL AS client_logo_path',
        ];

        $joins = [
            'JOIN clients c ON c.id = p.client_id',
            'LEFT JOIN users u ON u.id = p.pm_id',
            'LEFT JOIN (SELECT project_id, GROUP_CONCAT(risk_code) AS risks FROM project_risk_evaluations WHERE selected = 1 GROUP BY project_id) prisk ON prisk.project_id = p.id',
        ];

        if ($this->db->columnExists('projects', 'scope')) {
            $select[] = 'p.scope';
        }

        if ($this->db->columnExists('projects', 'design_inputs')) {
            $select[] = 'p.design_inputs';
        }

        if ($this->db->columnExists('projects', 'client_participation')) {
            $select[] = 'p.client_participation';
        }

        foreach ($isoColumns as $isoFlag) {
            $select[] = 'p.' . $isoFlag;
        }

        if ($hasTypeColumn) {
            $select[] = 'p.project_type';
        }

        if ($hasRiskScoreColumn) {
            $select[] = 'p.risk_score';
        }

        if ($hasRiskLevelColumn) {
            $select[] = 'p.risk_level';
        }

        if ($hasPriorityColumn) {
            $select[] = 'p.priority_code AS priority';
            $select[] = 'pr.label AS priority_label';
            $joins[] = 'LEFT JOIN priorities pr ON pr.code = p.priority_code';
        } elseif ($hasPriorityTextColumn) {
            $select[] = 'p.priority AS priority';
            $select[] = 'p.priority AS priority_label';
        } else {
            $select[] = 'NULL AS priority';
            $select[] = 'NULL AS priority_label';
        }

        if ($hasStatusColumn) {
            $select[] = 'p.status_code AS status';
            $select[] = 'st.label AS status_label';
            $joins[] = 'LEFT JOIN project_status st ON st.code = p.status_code';
        } elseif ($hasStatusTextColumn) {
            $select[] = 'p.status AS status';
            $select[] = 'p.status AS status_label';
        } else {
            $select[] = "'' AS status";
            $select[] = 'NULL AS status_label';
        }

        if ($hasHealthColumn) {
            $select[] = 'p.health_code AS health';
            $select[] = 'h.label AS health_label';
            $joins[] = 'LEFT JOIN project_health h ON h.code = p.health_code';
        } elseif ($hasHealthTextColumn) {
            $select[] = 'p.health AS health';
            $select[] = 'p.health AS health_label';
        } else {
            $select[] = 'NULL AS health';
            $select[] = 'NULL AS health_label';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        $project = $this->db->fetchOne(
            sprintf('SELECT %s FROM projects p %s %s LIMIT 1', implode(', ', $select), implode(' ', $joins), $whereClause),
            $params
        );

        return $project ? $this->attachProjectSignal($project) : null;
    }

    public function aggregatedKpis(array $user, array $filters = []): array
    {
        [$conditions, $params] = $this->visibilityConditions($user, 'c', 'p');
        $hasHealthColumn = $this->db->columnExists('projects', 'health_code');
        $hasHealthTextColumn = $this->db->columnExists('projects', 'health');
        $hasRiskScoreColumn = $this->db->columnExists('projects', 'risk_score');
        $hasRiskLevelColumn = $this->db->columnExists('projects', 'risk_level');

        if (!empty($filters['client_id'])) {
            $conditions[] = 'c.id = :client';
            $params[':client'] = (int) $filters['client_id'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = 'p.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['start_date'])) {
            $conditions[] = 'p.start_date >= :start';
            $params[':start'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $conditions[] = 'p.end_date <= :end';
            $params[':end'] = $filters['end_date'];
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $totalsSelect = [
            'COUNT(*) AS total',
            'SUM(p.planned_hours) AS planned_hours',
            'SUM(p.actual_hours) AS actual_hours',
            'AVG(p.progress) AS avg_progress',
        ];

        if ($hasRiskScoreColumn) {
            $totalsSelect[] = 'AVG(p.risk_score) AS avg_risk_score';
        }

        if ($hasRiskLevelColumn) {
            $totalsSelect[] = "SUM(CASE WHEN p.risk_level = 'alto' THEN 1 ELSE 0 END) AS risk_level_high";
            $totalsSelect[] = "SUM(CASE WHEN p.risk_level = 'medio' THEN 1 ELSE 0 END) AS risk_level_medium";
            $totalsSelect[] = "SUM(CASE WHEN p.risk_level = 'bajo' THEN 1 ELSE 0 END) AS risk_level_low";
        }

        $totals = $this->db->fetchOne(
            'SELECT ' . implode(', ', $totalsSelect) . ' FROM projects p JOIN clients c ON c.id = p.client_id ' . $whereClause,
            $params
        );

        $healthConditions = [];
        if ($hasHealthTextColumn) {
            $healthConditions[] = "p.health IN ('at_risk','critical','red','yellow')";
        }
        if ($hasHealthColumn) {
            $healthConditions[] = "p.health_code IN ('at_risk','critical','red','yellow')";
        }

        if ($healthConditions) {
            $atRiskConditions = $conditions;
            $atRiskConditions[] = '(' . implode(' OR ', $healthConditions) . ')';
            $atRiskWhere = 'WHERE ' . implode(' AND ', $atRiskConditions);
            $atRisk = $this->db->fetchOne(
                "SELECT COUNT(*) AS total FROM projects p JOIN clients c ON c.id = p.client_id $atRiskWhere",
                $params
            );
        } else {
            $atRisk = ['total' => 0];
        }

        return [
            'total_projects' => (int) ($totals['total'] ?? 0),
            'avg_progress' => round((float) ($totals['avg_progress'] ?? 0), 1),
            'planned_hours' => (int) ($totals['planned_hours'] ?? 0),
            'actual_hours' => (int) ($totals['actual_hours'] ?? 0),
            'at_risk' => (int) ($atRisk['total'] ?? 0),
            'avg_risk_score' => $hasRiskScoreColumn ? round((float) ($totals['avg_risk_score'] ?? 0), 1) : 0.0,
            'risk_levels' => [
                'alto' => $hasRiskLevelColumn ? (int) ($totals['risk_level_high'] ?? 0) : 0,
                'medio' => $hasRiskLevelColumn ? (int) ($totals['risk_level_medium'] ?? 0) : 0,
                'bajo' => $hasRiskLevelColumn ? (int) ($totals['risk_level_low'] ?? 0) : 0,
            ],
        ];
    }

    public function projectsForClient(int $clientId, array $user, array $filters = []): array
    {
        [$conditions, $params] = $this->visibilityConditions($user, 'c', 'p');
        $conditions[] = 'c.id = :clientId';
        $params[':clientId'] = $clientId;

        if (!empty($filters['status'])) {
            $conditions[] = 'p.status = :status';
            $params[':status'] = (string) $filters['status'];
        }

        if (!empty($filters['project_stage'])) {
            $conditions[] = 'p.project_stage = :projectStage';
            $params[':projectStage'] = (string) $filters['project_stage'];
        }

        if (!empty($filters['methodology'])) {
            $conditions[] = 'p.methodology = :methodology';
            $params[':methodology'] = (string) $filters['methodology'];
        }
        $hasStatusColumn = $this->db->columnExists('projects', 'status_code');
        $hasHealthColumn = $this->db->columnExists('projects', 'health_code');
        $hasPriorityColumn = $this->db->columnExists('projects', 'priority_code');
        $hasPriorityTextColumn = $this->db->columnExists('projects', 'priority');
        $hasStatusTextColumn = $this->db->columnExists('projects', 'status');
        $hasHealthTextColumn = $this->db->columnExists('projects', 'health');

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        $select = [
            'p.id',
            'p.name',
            'p.progress',
            'p.project_type',
            'p.methodology',
            'p.phase',
            'p.project_stage',
            'p.budget',
            'p.actual_cost',
            'p.pm_id',
            'u.name AS pm_name',
            'p.actual_hours',
            'p.planned_hours',
            'prisk.risks AS risk_codes',
        ];

        $joins = [
            'JOIN clients c ON c.id = p.client_id',
            'LEFT JOIN users u ON u.id = p.pm_id',
            'LEFT JOIN (SELECT project_id, GROUP_CONCAT(risk_code) AS risks FROM project_risk_evaluations WHERE selected = 1 GROUP BY project_id) prisk ON prisk.project_id = p.id',
        ];

        if ($hasPriorityColumn) {
            $select[] = 'p.priority_code AS priority';
            $select[] = 'pr.label AS priority_label';
            $joins[] = 'LEFT JOIN priorities pr ON pr.code = p.priority_code';
        } elseif ($hasPriorityTextColumn) {
            $select[] = 'p.priority AS priority';
            $select[] = 'p.priority AS priority_label';
        } else {
            $select[] = 'NULL AS priority';
            $select[] = 'NULL AS priority_label';
        }

        if ($hasStatusColumn) {
            $select[] = 'p.status_code AS status';
            $select[] = 'st.label AS status_label';
            $joins[] = 'LEFT JOIN project_status st ON st.code = p.status_code';
        } elseif ($hasStatusTextColumn) {
            $select[] = 'p.status AS status';
            $select[] = 'p.status AS status_label';
        } else {
            $select[] = "'' AS status";
            $select[] = 'NULL AS status_label';
        }

        if ($hasHealthColumn) {
            $select[] = 'p.health_code AS health';
            $select[] = 'h.label AS health_label';
            $joins[] = 'LEFT JOIN project_health h ON h.code = p.health_code';
        } elseif ($hasHealthTextColumn) {
            $select[] = 'p.health AS health';
            $select[] = 'p.health AS health_label';
        } else {
            $select[] = 'NULL AS health';
            $select[] = 'NULL AS health_label';
        }

        $sql = sprintf(
            'SELECT %s FROM projects p %s %s ORDER BY p.created_at DESC',
            implode(', ', $select),
            implode(' ', $joins),
            $whereClause
        );

        $projects = $this->db->fetchAll($sql, $params);

        return array_map(fn (array $project) => $this->attachProjectSignal($project), $projects);
    }

    public function clientGroups(array $user, array $filters = []): array
    {
        [$conditions, $params] = $this->visibilityConditions($user, 'c', 'p');
        $hasBillableColumn = $this->db->columnExists('projects', 'is_billable');
        $hasActiveColumn = $this->db->columnExists('projects', 'active');
        $hasClientLogoColumn = $this->db->columnExists('clients', 'logo_path');

        if (!empty($filters['client_id'])) {
            $conditions[] = 'c.id = :clientId';
            $params[':clientId'] = (int) $filters['client_id'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = 'p.status = :status';
            $params[':status'] = (string) $filters['status'];
        }

        if (!empty($filters['project_stage'])) {
            $conditions[] = 'p.project_stage = :projectStage';
            $params[':projectStage'] = (string) $filters['project_stage'];
        }

        if (($filters['billable'] ?? '') === 'yes' && $hasBillableColumn) {
            $conditions[] = 'p.is_billable = 1';
        }

        if (($filters['billable'] ?? '') === 'no' && $hasBillableColumn) {
            $conditions[] = 'p.is_billable = 0';
        }

        if ($hasActiveColumn) {
            $conditions[] = 'p.active = 1';
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return $this->db->fetchAll(
            sprintf(
                'SELECT c.id, c.name %s, COUNT(*) AS project_count
                 FROM projects p
                 JOIN clients c ON c.id = p.client_id
                 %s
                 GROUP BY c.id, c.name
                 ORDER BY c.name ASC',
                $hasClientLogoColumn ? ', c.logo_path' : ', NULL AS logo_path',
                $whereClause
            ),
            $params
        );
    }

    public function statusOptions(array $user, array $filters = []): array
    {
        [$conditions, $params] = $this->visibilityConditions($user, 'c', 'p');
        $hasActiveColumn = $this->db->columnExists('projects', 'active');

        if (!empty($filters['client_id'])) {
            $conditions[] = 'c.id = :clientId';
            $params[':clientId'] = (int) $filters['client_id'];
        }

        if (!empty($filters['project_stage'])) {
            $conditions[] = 'p.project_stage = :projectStage';
            $params[':projectStage'] = (string) $filters['project_stage'];
        }

        if ($hasActiveColumn) {
            $conditions[] = 'p.active = 1';
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT p.status AS status FROM projects p JOIN clients c ON c.id = p.client_id ' . $whereClause . ' ORDER BY p.status ASC',
            $params
        );

        return array_values(array_filter(array_map(static fn (array $row): string => trim((string) ($row['status'] ?? '')), $rows)));
    }

    public function dependencySummary(int $projectId): array
    {
        $tasks = 0;
        $timesheets = 0;
        $assignments = 0;
        $outsourcingFollowups = 0;
        $designInputs = 0;
        $designControls = 0;
        $designChanges = 0;
        $nodes = 0;

        if ($this->db->tableExists('tasks')) {
            $tasks = (int) ($this->db->fetchOne('SELECT COUNT(*) AS total FROM tasks WHERE project_id = :id', [':id' => $projectId])['total'] ?? 0);
        }

        if ($this->db->tableExists('timesheets')) {
            $usesProjectColumn = $this->db->columnExists('timesheets', 'project_id');
            $canResolveFromTasks = $this->db->tableExists('tasks')
                && $this->db->columnExists('timesheets', 'task_id')
                && $this->db->columnExists('tasks', 'project_id');
            if ($usesProjectColumn) {
                $timesheets = (int) ($this->db->fetchOne(
                    'SELECT COUNT(*) AS total FROM timesheets ts WHERE ts.project_id = :id',
                    [':id' => $projectId]
                )['total'] ?? 0);
            } elseif ($canResolveFromTasks) {
                $timesheets = (int) ($this->db->fetchOne(
                    'SELECT COUNT(*) AS total FROM timesheets ts JOIN tasks tk ON tk.id = ts.task_id WHERE tk.project_id = :id',
                    [':id' => $projectId]
                )['total'] ?? 0);
            }
        }

        if ($this->db->tableExists('project_talent_assignments')) {
            $assignments = (int) ($this->db->fetchOne('SELECT COUNT(*) AS total FROM project_talent_assignments WHERE project_id = :id', [':id' => $projectId])['total'] ?? 0);
        }

        if ($this->db->tableExists('project_outsourcing_followups')) {
            $outsourcingFollowups = (int) ($this->db->fetchOne(
                'SELECT COUNT(*) AS total FROM project_outsourcing_followups WHERE project_id = :id',
                [':id' => $projectId]
            )['total'] ?? 0);
        }

        if ($this->db->tableExists('project_design_inputs')) {
            $designInputs = (int) ($this->db->fetchOne('SELECT COUNT(*) AS total FROM project_design_inputs WHERE project_id = :id', [':id' => $projectId])['total'] ?? 0);
        }

        if ($this->db->tableExists('project_design_controls')) {
            $designControls = (int) ($this->db->fetchOne('SELECT COUNT(*) AS total FROM project_design_controls WHERE project_id = :id', [':id' => $projectId])['total'] ?? 0);
        }

        if ($this->db->tableExists('project_design_changes')) {
            $designChanges = (int) ($this->db->fetchOne('SELECT COUNT(*) AS total FROM project_design_changes WHERE project_id = :id', [':id' => $projectId])['total'] ?? 0);
        }

        if ($this->db->tableExists('project_nodes')) {
            $nodes = (int) ($this->db->fetchOne('SELECT COUNT(*) AS total FROM project_nodes WHERE project_id = :id', [':id' => $projectId])['total'] ?? 0);
        }

        $hasDependencies = ($tasks + $timesheets + $assignments + $outsourcingFollowups + $designInputs + $designControls + $designChanges + $nodes) > 0;

        return [
            'tasks' => $tasks,
            'timesheets' => $timesheets,
            'assignments' => $assignments,
            'outsourcing_followups' => $outsourcingFollowups,
            'design_inputs' => $designInputs,
            'design_controls' => $designControls,
            'design_changes' => $designChanges,
            'nodes' => $nodes,
            'has_dependencies' => $hasDependencies,
        ];
    }

    public function timesheetHoursForProject(int $projectId): ?float
    {
        if (!$this->db->tableExists('timesheets')) {
            return null;
        }

        $usesProjectColumn = $this->db->columnExists('timesheets', 'project_id');
        $canResolveFromTasks = $this->db->tableExists('tasks')
            && $this->db->columnExists('timesheets', 'task_id')
            && $this->db->columnExists('tasks', 'project_id');
        if (!$usesProjectColumn && !$canResolveFromTasks) {
            return null;
        }

        $projectFilter = $usesProjectColumn ? 'ts.project_id = :project_id' : 't.project_id = :project_id';
        $taskJoin = $usesProjectColumn ? '' : 'JOIN tasks t ON t.id = ts.task_id';
        $row = $this->db->fetchOne(
            'SELECT SUM(ts.hours) AS total
             FROM timesheets ts
             ' . $taskJoin . '
             WHERE ' . $projectFilter . '
             AND ts.status = \'approved\'',
            [':project_id' => $projectId]
        );

        return $row ? (float) ($row['total'] ?? 0) : 0.0;
    }

    public function timesheetEntriesForProject(int $projectId, int $limit = 300): array
    {
        if (!$this->db->tableExists('timesheets')) {
            return [];
        }

        $usesProjectColumn = $this->db->columnExists('timesheets', 'project_id');
        $hasTasksTable = $this->db->tableExists('tasks');
        $canResolveFromTasks = $hasTasksTable && $this->db->columnExists('timesheets', 'task_id');
        if (!$usesProjectColumn && !$canResolveFromTasks) {
            return [];
        }

        $safeLimit = max(1, min(2000, $limit));
        $taskJoin = $canResolveFromTasks ? 'LEFT JOIN tasks tk ON tk.id = ts.task_id' : '';
        $projectExpr = $usesProjectColumn
            ? ($canResolveFromTasks ? 'COALESCE(ts.project_id, tk.project_id, 0)' : 'COALESCE(ts.project_id, 0)')
            : 'COALESCE(tk.project_id, 0)';

        $hasUsersTable = $this->db->tableExists('users') && $this->db->columnExists('timesheets', 'user_id');
        $hasTalentsTable = $this->db->tableExists('talents') && $this->db->columnExists('timesheets', 'talent_id');
        $userJoin = $hasUsersTable ? 'LEFT JOIN users u ON u.id = ts.user_id' : '';
        $talentJoin = $hasTalentsTable ? 'LEFT JOIN talents ta ON ta.id = ts.talent_id' : '';
        $userNameExpr = $hasUsersTable && $hasTalentsTable
            ? 'COALESCE(NULLIF(TRIM(u.name), ""), NULLIF(TRIM(ta.name), ""), "Sin usuario")'
            : ($hasUsersTable
                ? 'COALESCE(NULLIF(TRIM(u.name), ""), "Sin usuario")'
                : ($hasTalentsTable
                    ? 'COALESCE(NULLIF(TRIM(ta.name), ""), "Sin usuario")'
                    : '"Sin usuario"'));
        $activityExpr = $this->db->columnExists('timesheets', 'activity_description')
            ? 'NULLIF(TRIM(ts.activity_description), "")'
            : 'NULL';
        $taskTitleExpr = ($hasTasksTable && $this->db->columnExists('tasks', 'title'))
            ? 'NULLIF(TRIM(tk.title), "")'
            : 'NULL';

        return $this->db->fetchAll(
            'SELECT ts.id,
                    ts.date,
                    ts.hours,
                    ts.status,
                    ' . $userNameExpr . ' AS user_name,
                    COALESCE(' . $activityExpr . ', ' . $taskTitleExpr . ', "Sin tarea") AS task_name
             FROM timesheets ts
             ' . $taskJoin . '
             ' . $userJoin . '
             ' . $talentJoin . '
             WHERE ' . $projectExpr . ' = :project_id
             ORDER BY ts.date DESC, ts.id DESC
             LIMIT ' . $safeLimit,
            [':project_id' => $projectId]
        );
    }

    public function assignmentsForProject(int $projectId, array $user): array
    {
        [$conditions, $params] = $this->visibilityConditions($user, 'c', 'p');
        $conditions[] = 'p.id = :projectId';
        $params[':projectId'] = $projectId;
        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        $hasTalentColumn = $this->db->columnExists('project_talent_assignments', 'talent_id');
        $hasTalentWeeklyCapacity = $this->db->tableExists('talents') && $this->db->columnExists('talents', 'weekly_capacity');

        $select = [
            'a.*',
            'p.id AS project_id',
            'u.can_review_documents',
            'u.can_validate_documents',
            'u.can_approve_documents',
        ];
        $joins = [
            'JOIN projects p ON p.id = a.project_id',
            'JOIN clients c ON c.id = p.client_id',
            'JOIN users u ON u.id = a.user_id',
        ];

        if ($hasTalentColumn) {
            $select[] = 't.name AS talent_name';
            $select[] = 't.capacidad_horaria';
            $select[] = $hasTalentWeeklyCapacity ? 'COALESCE(t.weekly_capacity, 0) AS talent_weekly_capacity' : '0 AS talent_weekly_capacity';
            $select[] = 't.requiere_reporte_horas';
            $select[] = 't.requiere_aprobacion_horas';
            $select[] = 't.tipo_talento';
            $select[] = 'COALESCE(tload.total_allocation_percent, 0) AS total_allocation_percent';
            $select[] = 'GREATEST(0, 100 - COALESCE(tload.total_allocation_percent, 0)) AS available_allocation_percent';
            $joins[] = 'LEFT JOIN talents t ON t.id = a.talent_id';
            $joins[] = 'LEFT JOIN (
                SELECT talent_id, SUM(allocation_percent) AS total_allocation_percent
                FROM project_talent_assignments
                WHERE talent_id IS NOT NULL
                  AND (assignment_status = "active" OR (assignment_status IS NULL AND active = 1))
                GROUP BY talent_id
            ) tload ON tload.talent_id = a.talent_id';
        } else {
            $select[] = 'u.name AS talent_name';
            $select[] = '0 AS capacidad_horaria';
            $select[] = '0 AS talent_weekly_capacity';
            $select[] = '0 AS requiere_reporte_horas';
            $select[] = '0 AS requiere_aprobacion_horas';
            $select[] = '\'interno\' AS tipo_talento';
            $select[] = 'a.user_id AS talent_id';
            $select[] = '0 AS total_allocation_percent';
            $select[] = '0 AS available_allocation_percent';
        }

        return $this->db->fetchAll(
            'SELECT ' . implode(', ', $select) . '
             FROM project_talent_assignments a
             ' . implode(' ', $joins) . '
             ' . $whereClause,
            $params
        );
    }

    public function assignmentsForClient(int $clientId, array $user): array
    {
        [$conditions, $params] = $this->visibilityConditions($user, 'c', 'p');
        $conditions[] = 'c.id = :clientId';
        $params[':clientId'] = $clientId;
        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        $hasTalentColumn = $this->db->columnExists('project_talent_assignments', 'talent_id');

        $select = ['a.*', 'p.id AS project_id'];
        $joins = [
            'JOIN projects p ON p.id = a.project_id',
            'JOIN clients c ON c.id = p.client_id',
        ];

        if ($hasTalentColumn) {
            $select[] = 't.name AS talent_name';
            $select[] = 't.capacidad_horaria';
            $select[] = 't.requiere_reporte_horas';
            $select[] = 't.requiere_aprobacion_horas';
            $select[] = 't.tipo_talento';
            $joins[] = 'LEFT JOIN talents t ON t.id = a.talent_id';
        } else {
            $select[] = 'u.name AS talent_name';
            $select[] = '0 AS capacidad_horaria';
            $select[] = '0 AS requiere_reporte_horas';
            $select[] = '0 AS requiere_aprobacion_horas';
            $select[] = '\'interno\' AS tipo_talento';
            $select[] = 'a.user_id AS talent_id';
            $joins[] = 'LEFT JOIN users u ON u.id = a.user_id';
        }

        return $this->db->fetchAll(
            'SELECT ' . implode(', ', $select) . '
             FROM project_talent_assignments a
             ' . implode(' ', $joins) . '
             ' . $whereClause,
            $params
        );
    }

    public function assignTalent(array $payload): void
    {
        $assignmentStatus = strtolower(trim((string) ($payload['assignment_status'] ?? 'active')));
        $allowedStatuses = ['active', 'paused', 'removed'];
        if (!in_array($assignmentStatus, $allowedStatuses, true)) {
            $assignmentStatus = 'active';
        }
        $activeFlag = $assignmentStatus === 'active' ? 1 : 0;

        $hasTalentColumn = $this->db->columnExists('project_talent_assignments', 'talent_id');
        $assignmentTalentColumn = $hasTalentColumn ? 'talent_id' : 'user_id';
        $talentId = (int) ($payload['talent_id'] ?? 0);
        $userId = (int) ($payload['user_id'] ?? 0);
        if ($userId <= 0 && $this->db->tableExists('talents') && $this->db->columnExists('talents', 'user_id')) {
            $talentUser = $this->db->fetchOne(
                'SELECT user_id FROM talents WHERE id = :id',
                [':id' => $talentId]
            );
            $userId = (int) ($talentUser['user_id'] ?? 0);
        }
        if ($userId <= 0) {
            $userId = (int) ($payload['created_by'] ?? 0);
        }

        $assignmentTalentValue = $hasTalentColumn ? $talentId : $userId;
        $totalPercent = $this->db->fetchOne(
            sprintf(
                'SELECT SUM(allocation_percent) AS total_percent FROM project_talent_assignments WHERE %s = :talent',
                $assignmentTalentColumn
            ),
            [':talent' => $assignmentTalentValue]
        );
        $totalHours = $this->db->fetchOne(
            sprintf(
                'SELECT SUM(weekly_hours) AS total_hours FROM project_talent_assignments WHERE %s = :talent',
                $assignmentTalentColumn
            ),
            [':talent' => $assignmentTalentValue]
        );

        $talent = [];
        if ($this->db->tableExists('talents') && $talentId > 0) {
            $talent = $this->db->fetchOne(
                'SELECT weekly_capacity, capacidad_horaria, availability FROM talents WHERE id = :id',
                [':id' => $talentId]
            ) ?: [];
        }
        $weeklyCapacity = (float) ($talent['weekly_capacity'] ?? 0);
        $talentCapacity = (float) ($talent['capacidad_horaria'] ?? 0);
        if ($talentCapacity > 0) {
            $weeklyCapacity = $talentCapacity;
        }
        if ($weeklyCapacity <= 0) {
            $weeklyCapacity = 40.0;
        }
        $effectiveWeeklyCapacity = $this->resolveTalentEffectiveWeeklyCapacity(
            $talentId,
            $weeklyCapacity,
            (float) ($talent['availability'] ?? 100)
        );

        $newPercentTotal = (float) ($totalPercent['total_percent'] ?? 0) + (float) ($payload['allocation_percent'] ?? 0);
        $newHoursTotal = (float) ($totalHours['total_hours'] ?? 0) + (float) ($payload['weekly_hours'] ?? 0);

        if ($payload['allocation_percent'] !== null && $newPercentTotal > 100.0) {
            throw new InvalidArgumentException('La asignación supera el 100% de disponibilidad del talento.');
        }

        if ($effectiveWeeklyCapacity > 0 && $payload['weekly_hours'] !== null && $newHoursTotal > $effectiveWeeklyCapacity) {
            throw new InvalidArgumentException('Las horas asignadas exceden la capacidad semanal del talento.');
        }

        $talentTimesheetConfig = [];
        if ($this->db->tableExists('talents') && $talentId > 0) {
            $talentTimesheetConfig = $this->db->fetchOne(
                'SELECT requiere_reporte_horas, requiere_aprobacion_horas FROM talents WHERE id = :id',
                [':id' => $talentId]
            ) ?: [];
        }

        $requiresTimesheet = (int) ($talentTimesheetConfig['requiere_reporte_horas'] ?? 0);
        $requiresApproval = (int) ($talentTimesheetConfig['requiere_aprobacion_horas'] ?? 0);

        $columns = [
            'project_id',
            'user_id',
            'role',
            'start_date',
            'end_date',
            'allocation_percent',
            'weekly_hours',
            'cost_type',
            'cost_value',
            'is_external',
            'requires_timesheet',
            'requires_timesheet_approval',
            'assignment_status',
            'active',
            'created_at',
            'updated_at',
        ];
        $params = [
            ':project_id' => (int) $payload['project_id'],
            ':user_id' => $userId,
            ':role' => $payload['role'],
            ':start_date' => $payload['start_date'] ?: null,
            ':end_date' => $payload['end_date'] ?: null,
            ':allocation_percent' => $payload['allocation_percent'],
            ':weekly_hours' => $payload['weekly_hours'],
            ':cost_type' => $this->normalizeAssignmentCostType((string) ($payload['cost_type'] ?? '')),
            ':cost_value' => $payload['cost_value'],
            ':is_external' => (int) $payload['is_external'],
            ':requires_timesheet' => $requiresTimesheet,
            ':requires_timesheet_approval' => $requiresApproval,
            ':assignment_status' => $assignmentStatus,
            ':active' => $activeFlag,
        ];
        if ($hasTalentColumn) {
            $columns[] = 'talent_id';
            $params[':talent_id'] = $talentId;
        }

        $valuePlaceholders = [];
        foreach ($columns as $column) {
            if (in_array($column, ['created_at', 'updated_at'], true)) {
                $valuePlaceholders[] = 'NOW()';
                continue;
            }
            $valuePlaceholders[] = ':' . $column;
        }

        $this->db->insert(
            'INSERT INTO project_talent_assignments (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $valuePlaceholders) . ')',
            $params
        );
    }


    private function normalizeAssignmentCostType(string $costType): string
    {
        $normalized = strtolower(trim($costType));
        $aliases = [
            'por_horas' => 'por_horas',
            'fijo' => 'fijo',
            'hourly' => 'hourly',
            'fixed' => 'fixed',
        ];
        $canonical = $aliases[$normalized] ?? 'por_horas';

        $definition = $this->assignmentCostTypeDefinition();
        if ($definition === null || $definition['data_type'] !== 'enum') {
            return in_array($canonical, ['fijo', 'fixed'], true) ? 'fijo' : 'por_horas';
        }

        $options = $definition['enum_values'];
        if ($options === []) {
            return in_array($canonical, ['fijo', 'fixed'], true) ? 'fijo' : 'por_horas';
        }

        $compatibility = [
            'por_horas' => ['por_horas', 'hourly', 'hora', 'horas', 'variable'],
            'hourly' => ['hourly', 'por_horas', 'hora', 'horas', 'variable'],
            'fijo' => ['fijo', 'fixed', 'mensual', 'flat'],
            'fixed' => ['fixed', 'fijo', 'mensual', 'flat'],
        ];

        foreach ($compatibility[$canonical] ?? [$canonical] as $candidate) {
            if (in_array($candidate, $options, true)) {
                return $candidate;
            }
        }

        return $options[0];
    }

    private function assignmentCostTypeDefinition(): ?array
    {
        if ($this->assignmentCostTypeDefinition !== null) {
            return $this->assignmentCostTypeDefinition;
        }

        $metadata = $this->db->fetchOne(
            'SELECT DATA_TYPE, COLUMN_TYPE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :schema
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column
             LIMIT 1',
            [
                ':schema' => $this->db->databaseName(),
                ':table' => 'project_talent_assignments',
                ':column' => 'cost_type',
            ]
        );

        if (!$metadata) {
            return null;
        }

        $enumValues = [];
        if (strtolower((string) ($metadata['DATA_TYPE'] ?? '')) === 'enum') {
            preg_match_all("/'([^']*)'/", (string) ($metadata['COLUMN_TYPE'] ?? ''), $matches);
            $enumValues = $matches[1] ?? [];
        }

        $this->assignmentCostTypeDefinition = [
            'data_type' => strtolower((string) ($metadata['DATA_TYPE'] ?? '')),
            'enum_values' => $enumValues,
        ];

        return $this->assignmentCostTypeDefinition;
    }

    public function updateAssignmentStatus(int $projectId, int $assignmentId, string $status): void
    {
        if (!$this->db->tableExists('project_talent_assignments')) {
            return;
        }

        $normalized = strtolower(trim($status));
        $allowed = ['active', 'paused', 'removed'];
        if (!in_array($normalized, $allowed, true)) {
            throw new \InvalidArgumentException('Estado de asignación no válido.');
        }

        $activeFlag = $normalized === 'active' ? 1 : 0;

        $this->db->execute(
            'UPDATE project_talent_assignments
             SET assignment_status = :status, active = :active, updated_at = NOW()
             WHERE id = :id AND project_id = :project',
            [
                ':status' => $normalized,
                ':active' => $activeFlag,
                ':id' => $assignmentId,
                ':project' => $projectId,
            ]
        );
    }

    public function updateAssignmentWorkload(
        int $projectId,
        int $assignmentId,
        ?float $allocationPercent,
        ?float $weeklyHours,
        string $editedField = 'allocation_percent',
        bool $forceUpdate = false
    ): array {
        if (!$this->db->tableExists('project_talent_assignments')) {
            throw new \InvalidArgumentException('No existe la tabla de asignaciones de talento.');
        }

        $assignment = $this->findAssignmentById($projectId, $assignmentId);
        if (!$assignment) {
            throw new \InvalidArgumentException('Asignación no encontrada.');
        }

        $hasTalentColumn = $this->db->columnExists('project_talent_assignments', 'talent_id');
        $talentId = (int) ($assignment['talent_id'] ?? 0);
        $userId = (int) ($assignment['user_id'] ?? 0);

        if ($talentId <= 0 && $this->db->tableExists('talents') && $this->db->columnExists('talents', 'user_id') && $userId > 0) {
            $talent = $this->db->fetchOne(
                'SELECT id FROM talents WHERE user_id = :user_id LIMIT 1',
                [':user_id' => $userId]
            );
            $talentId = (int) ($talent['id'] ?? 0);
        }

        $weeklyCapacity = $this->resolveTalentWeeklyCapacity($talentId);
        $effectiveWeeklyCapacity = $this->resolveTalentEffectiveWeeklyCapacity($talentId, $weeklyCapacity);
        $normalizedEditedField = strtolower(trim($editedField));

        if ($normalizedEditedField === 'weekly_hours') {
            if ($weeklyHours === null) {
                throw new \InvalidArgumentException('Las horas semanales son obligatorias.');
            }
            if ($weeklyHours < 0) {
                throw new \InvalidArgumentException('Las horas semanales no pueden ser negativas.');
            }
            if ($weeklyCapacity <= 0) {
                throw new \InvalidArgumentException('La capacidad semanal del talento no es válida para recalcular porcentaje.');
            }
            $allocationPercent = ($weeklyHours / $weeklyCapacity) * 100;
        } else {
            if ($allocationPercent === null) {
                throw new \InvalidArgumentException('El porcentaje de dedicación es obligatorio.');
            }
            if ($allocationPercent < 0 || $allocationPercent > 100) {
                throw new \InvalidArgumentException('El porcentaje de dedicación debe estar entre 0 y 100.');
            }
            $weeklyHours = $weeklyCapacity * ($allocationPercent / 100);
        }

        $allocationPercent = round((float) $allocationPercent, 2);
        $weeklyHours = round((float) $weeklyHours, 2);

        if ($allocationPercent < 0 || $allocationPercent > 100) {
            throw new \InvalidArgumentException('El porcentaje de dedicación debe estar entre 0 y 100.');
        }
        if ($weeklyHours < 0) {
            throw new \InvalidArgumentException('Las horas semanales no pueden ser negativas.');
        }

        $assignmentTalentColumn = $hasTalentColumn ? 'talent_id' : 'user_id';
        $assignmentTalentValue = $hasTalentColumn ? $talentId : $userId;

        if ($assignmentTalentValue <= 0) {
            throw new \InvalidArgumentException('No se pudo identificar el talento asociado a la asignación.');
        }

        $totals = $this->db->fetchOne(
            sprintf(
                'SELECT COALESCE(SUM(allocation_percent), 0) AS total_percent,
                        COALESCE(SUM(weekly_hours), 0) AS total_hours
                 FROM project_talent_assignments
                 WHERE %s = :talent
                   AND id <> :assignment_id',
                $assignmentTalentColumn
            ),
            [
                ':talent' => $assignmentTalentValue,
                ':assignment_id' => $assignmentId,
            ]
        ) ?: ['total_percent' => 0.0, 'total_hours' => 0.0];

        $newPercentTotal = (float) ($totals['total_percent'] ?? 0.0) + $allocationPercent;
        $newHoursTotal = (float) ($totals['total_hours'] ?? 0.0) + $weeklyHours;

        if ($newPercentTotal > 100.0) {
            throw new \InvalidArgumentException('La dedicación total del talento supera el 100% con este ajuste.');
        }

        if ($effectiveWeeklyCapacity > 0 && $newHoursTotal > $effectiveWeeklyCapacity) {
            throw new \InvalidArgumentException('Las horas semanales totales del talento exceden su capacidad estándar.');
        }

        $conflicts = $this->weeklyTimesheetOveragesForAssignment($projectId, $talentId, $userId, $weeklyHours);
        if ($conflicts !== [] && !$forceUpdate) {
            $maxLogged = (float) max(array_column($conflicts, 'hours'));
            return [
                'updated' => false,
                'requires_confirmation' => true,
                'message' => 'Las horas registradas en timesheet superan la nueva dedicación.',
                'max_logged_weekly_hours' => round($maxLogged, 2),
                'assigned_weekly_hours' => $weeklyHours,
                'conflict_weeks' => $conflicts,
                'capacity_week' => round($effectiveWeeklyCapacity, 2),
                'allocation_percent' => $allocationPercent,
            ];
        }

        $this->db->execute(
            'UPDATE project_talent_assignments
             SET allocation_percent = :allocation_percent,
                 weekly_hours = :weekly_hours,
                 updated_at = NOW()
             WHERE id = :id AND project_id = :project',
            [
                ':allocation_percent' => $allocationPercent,
                ':weekly_hours' => $weeklyHours,
                ':id' => $assignmentId,
                ':project' => $projectId,
            ]
        );

        return [
            'updated' => true,
            'requires_confirmation' => false,
            'assignment_id' => $assignmentId,
            'project_id' => $projectId,
            'before' => [
                'allocation_percent' => (float) ($assignment['allocation_percent'] ?? 0),
                'weekly_hours' => (float) ($assignment['weekly_hours'] ?? 0),
            ],
            'after' => [
                'allocation_percent' => $allocationPercent,
                'weekly_hours' => $weeklyHours,
            ],
            'capacity_week' => round($effectiveWeeklyCapacity, 2),
            'timesheet_conflicts' => $conflicts,
        ];
    }

    private function resolveTalentWeeklyCapacity(int $talentId): float
    {
        if ($talentId > 0 && $this->db->tableExists('talents')) {
            $selectColumns = ['capacidad_horaria'];
            if ($this->db->columnExists('talents', 'weekly_capacity')) {
                $selectColumns[] = 'weekly_capacity';
            }
            $talent = $this->db->fetchOne(
                'SELECT ' . implode(', ', $selectColumns) . '
                 FROM talents
                 WHERE id = :id
                 LIMIT 1',
                [':id' => $talentId]
            ) ?: [];
            $capacidadHoraria = (float) ($talent['capacidad_horaria'] ?? 0);
            if ($capacidadHoraria > 0) {
                return $capacidadHoraria;
            }

            $weeklyCapacity = (float) ($talent['weekly_capacity'] ?? 0);
            if ($weeklyCapacity > 0) {
                return $weeklyCapacity;
            }
        }

        return 40.0;
    }

    private function resolveTalentAvailabilityPercent(int $talentId): float
    {
        if ($talentId <= 0 || !$this->db->tableExists('talents') || !$this->db->columnExists('talents', 'availability')) {
            return 100.0;
        }

        $row = $this->db->fetchOne(
            'SELECT availability FROM talents WHERE id = :id LIMIT 1',
            [':id' => $talentId]
        );

        return max(0.0, min(100.0, (float) ($row['availability'] ?? 100)));
    }

    private function resolveTalentEffectiveWeeklyCapacity(int $talentId, float $weeklyCapacity, ?float $availability = null): float
    {
        $availabilityPercent = $availability ?? $this->resolveTalentAvailabilityPercent($talentId);
        if ($talentId <= 0) {
            return max(0.0, $weeklyCapacity) * ($availabilityPercent / 100);
        }

        $breakdown = $this->talentAvailabilityService()->weeklyCapacityBreakdown(
            $talentId,
            max(0.0, $weeklyCapacity),
            new \DateTimeImmutable('monday this week'),
            $availabilityPercent
        );

        $effective = (float) ($breakdown['weekly_real_hours'] ?? 0);
        if ($effective <= 0 && $weeklyCapacity > 0) {
            return max(0.0, $weeklyCapacity) * ($availabilityPercent / 100);
        }

        return $effective;
    }

    private function talentAvailabilityService(): TalentAvailabilityService
    {
        if ($this->talentAvailabilityService instanceof TalentAvailabilityService) {
            return $this->talentAvailabilityService;
        }

        $this->talentAvailabilityService = new TalentAvailabilityService($this->db);
        return $this->talentAvailabilityService;
    }

    private function weeklyTimesheetOveragesForAssignment(
        int $projectId,
        int $talentId,
        int $userId,
        float $assignedWeeklyHours
    ): array {
        if (!$this->db->tableExists('timesheets') || !$this->db->columnExists('timesheets', 'date')) {
            return [];
        }

        $identityConditions = [];
        $params = [
            ':project_id' => $projectId,
            ':assigned_hours' => $assignedWeeklyHours,
        ];

        if ($talentId > 0 && $this->db->columnExists('timesheets', 'talent_id')) {
            $identityConditions[] = 'ts.talent_id = :talent_id';
            $params[':talent_id'] = $talentId;
        }

        if ($userId > 0 && $this->db->columnExists('timesheets', 'user_id')) {
            $identityConditions[] = 'ts.user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        if ($identityConditions === []) {
            return [];
        }

        $hasProjectColumn = $this->db->columnExists('timesheets', 'project_id');
        $hasTaskTable = $this->db->tableExists('tasks');
        if (!$hasProjectColumn && !$hasTaskTable) {
            return [];
        }
        $projectExpression = $hasProjectColumn
            ? ($hasTaskTable ? 'COALESCE(ts.project_id, t.project_id)' : 'ts.project_id')
            : 't.project_id';
        $taskJoin = $hasTaskTable ? 'LEFT JOIN tasks t ON t.id = ts.task_id' : '';

        $rows = $this->db->fetchAll(
            'SELECT DATE_SUB(ts.date, INTERVAL WEEKDAY(ts.date) DAY) AS week_start,
                    ROUND(COALESCE(SUM(ts.hours), 0), 2) AS total_hours
             FROM timesheets ts
             ' . $taskJoin . '
             WHERE (' . implode(' OR ', $identityConditions) . ')
               AND ' . $projectExpression . ' = :project_id
             GROUP BY DATE_SUB(ts.date, INTERVAL WEEKDAY(ts.date) DAY)
             HAVING SUM(ts.hours) > :assigned_hours
             ORDER BY week_start DESC',
            $params
        );

        return array_map(
            static fn (array $row): array => [
                'week_start' => (string) ($row['week_start'] ?? ''),
                'hours' => round((float) ($row['total_hours'] ?? 0), 2),
            ],
            $rows
        );
    }


    public function findAssignmentById(int $projectId, int $assignmentId): ?array
    {
        if (!$this->db->tableExists('project_talent_assignments')) {
            return null;
        }

        $assignment = $this->db->fetchOne(
            'SELECT * FROM project_talent_assignments WHERE id = :id AND project_id = :project LIMIT 1',
            [
                ':id' => $assignmentId,
                ':project' => $projectId,
            ]
        );

        return $assignment ?: null;
    }

    public function deleteAssignmentPermanently(int $projectId, int $assignmentId): void
    {
        if (!$this->db->tableExists('project_talent_assignments')) {
            return;
        }

        $this->db->execute(
            'DELETE FROM project_talent_assignments WHERE id = :id AND project_id = :project',
            [
                ':id' => $assignmentId,
                ':project' => $projectId,
            ]
        );
    }


    public function create(array $payload): int
    {
        try {
            $status = $payload['status'] ?? 'planning';
            $health = $payload['health'] ?? 'on_track';
            $riskScore = $payload['risk_score'] ?? 0;
            $riskLevel = $payload['risk_level'] ?? 'bajo';
            $methodology = $payload['methodology'] ?? 'scrum';
            $phase = $payload['phase'] ?? match ($methodology) {
                'scrum' => 'descubrimiento',
                'convencional' => 'inicio',
                default => null,
            };

            $statusColumn = $this->db->columnExists('projects', 'status_code') ? 'status_code' : 'status';
            $healthColumn = $this->db->columnExists('projects', 'health_code') ? 'health_code' : 'health';
            $priorityColumn = $this->db->columnExists('projects', 'priority_code') ? 'priority_code' : 'priority';

            $columns = [
                'client_id',
                'pm_id',
                'name',
                'project_type',
                'methodology',
                'project_stage',
                $statusColumn,
                $healthColumn,
                $priorityColumn,
                'budget',
                'actual_cost',
                'planned_hours',
                'actual_hours',
                'progress',
                'start_date',
                'end_date',
            ];

            $placeholders = [
                ':client_id',
                ':pm_id',
                ':name',
                ':project_type',
                ':methodology',
                ':project_stage',
                ':status',
                ':health',
                ':priority',
                ':budget',
                ':actual_cost',
                ':planned_hours',
                ':actual_hours',
                ':progress',
                ':start_date',
                ':end_date',
            ];

            $params = [
                ':client_id' => (int) $payload['client_id'],
                ':pm_id' => (int) $payload['pm_id'],
                ':name' => $payload['name'],
                ':project_type' => $payload['project_type'] ?? 'convencional',
                ':methodology' => $methodology,
                ':project_stage' => $payload['project_stage'] ?? 'Discovery',
                ':status' => $status,
                ':health' => $health,
                ':priority' => $payload['priority'] ?? 'medium',
                ':budget' => $payload['budget'] ?? 0,
                ':actual_cost' => $payload['actual_cost'] ?? 0,
                ':planned_hours' => $payload['planned_hours'] ?? 0,
                ':actual_hours' => $payload['actual_hours'] ?? 0,
                ':progress' => 0.0,
                ':start_date' => $payload['start_date'] ?? null,
                ':end_date' => $payload['end_date'] ?? null,
            ];

            if ($this->db->columnExists('projects', 'phase')) {
                $columns[] = 'phase';
                $placeholders[] = ':phase';
                $params[':phase'] = $phase;
            }

            if ($this->db->columnExists('projects', 'risk_score')) {
                $columns[] = 'risk_score';
                $placeholders[] = ':risk_score';
                $params[':risk_score'] = $riskScore;
            }

            if ($this->db->columnExists('projects', 'risk_level')) {
                $columns[] = 'risk_level';
                $placeholders[] = ':risk_level';
                $params[':risk_level'] = $riskLevel;
            }

            if ($this->db->columnExists('projects', 'scope')) {
                $columns[] = 'scope';
                $placeholders[] = ':scope';
                $params[':scope'] = $payload['scope'] ?? '';
            }

            if ($this->db->columnExists('projects', 'design_inputs')) {
                $columns[] = 'design_inputs';
                $placeholders[] = ':design_inputs';
                $params[':design_inputs'] = $payload['design_inputs'] ?? '';
            }

            if ($this->db->columnExists('projects', 'client_participation')) {
                $columns[] = 'client_participation';
                $placeholders[] = ':client_participation';
                $params[':client_participation'] = (int) ($payload['client_participation'] ?? 0);
            }

            $isoControls = [
                'design_inputs_defined',
                'design_review_done',
                'design_verification_done',
                'design_validation_done',
                'legal_requirements',
                'change_control_required',
            ];

            foreach ($isoControls as $column => $payloadKey) {
                if (is_int($column)) {
                    $column = $payloadKey;
                    $payloadKey = $payloadKey;
                }

                if ($this->db->columnExists('projects', $column)) {
                    $columns[] = $column;
                    $placeholders[] = ':' . $column;
                    $params[':' . $column] = (int) ($payload[$payloadKey] ?? 0);
                }
            }

            if ($this->db->columnExists('projects', 'is_billable')) {
                $columns[] = 'is_billable';
                $placeholders[] = ':is_billable';
                $params[':is_billable'] = (int) ($payload['is_billable'] ?? 0);
            }

            if ($this->db->columnExists('projects', 'billing_type')) {
                $columns[] = 'billing_type';
                $placeholders[] = ':billing_type';
                $params[':billing_type'] = $payload['billing_type'] ?? 'fixed';
            }

            if ($this->db->columnExists('projects', 'billing_periodicity')) {
                $columns[] = 'billing_periodicity';
                $placeholders[] = ':billing_periodicity';
                $params[':billing_periodicity'] = $payload['billing_periodicity'] ?? 'monthly';
            }

            if ($this->db->columnExists('projects', 'contract_value')) {
                $columns[] = 'contract_value';
                $placeholders[] = ':contract_value';
                $params[':contract_value'] = (float) ($payload['contract_value'] ?? 0);
            }

            if ($this->db->columnExists('projects', 'currency_code')) {
                $columns[] = 'currency_code';
                $placeholders[] = ':currency_code';
                $params[':currency_code'] = strtoupper((string) ($payload['currency_code'] ?? 'USD'));
            }

            if ($this->db->columnExists('projects', 'billing_start_date')) {
                $columns[] = 'billing_start_date';
                $placeholders[] = ':billing_start_date';
                $params[':billing_start_date'] = $payload['billing_start_date'] ?? null;
            }

            if ($this->db->columnExists('projects', 'billing_end_date')) {
                $columns[] = 'billing_end_date';
                $placeholders[] = ':billing_end_date';
                $params[':billing_end_date'] = $payload['billing_end_date'] ?? null;
            }

            if ($this->db->columnExists('projects', 'hourly_rate')) {
                $columns[] = 'hourly_rate';
                $placeholders[] = ':hourly_rate';
                $params[':hourly_rate'] = (float) ($payload['hourly_rate'] ?? 0);
            }

            error_log('Project insert payload: ' . json_encode(['columns' => $columns, 'params' => $params]));

            $projectId = $this->db->insert(
                sprintf(
                    'INSERT INTO projects (%s) VALUES (%s)',
                    implode(', ', $columns),
                    implode(', ', $placeholders)
                ),
                $params
            );
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int) $e->getCode(), $e);
        }

        $riskEvaluations = $payload['risk_evaluations'] ?? null;
        if ($riskEvaluations === null && array_key_exists('risks', $payload)) {
            $riskEvaluations = $this->buildEvaluationsFromSelection($payload['risks']);
        }

        if (is_array($riskEvaluations)) {
            $this->syncProjectRiskEvaluations($projectId, $riskEvaluations);
        }

        return $projectId;
    }

    public function updateProject(int $id, array $payload, ?int $userId = null): void
    {
        $fields = ['name = :name'];
        $params = [
            ':id' => $id,
            ':name' => $payload['name'],
        ];
        $beforeIsoFlags = $this->projectIsoFlags($id);

        if ($this->db->columnExists('projects', 'status_code')) {
            $fields[] = 'status_code = :status';
            $params[':status'] = $payload['status'];
        } elseif ($this->db->columnExists('projects', 'status')) {
            $fields[] = 'status = :status';
            $params[':status'] = $payload['status'];
        }

        if ($this->db->columnExists('projects', 'health_code')) {
            $fields[] = 'health_code = :health';
            $params[':health'] = $payload['health'];
        } elseif ($this->db->columnExists('projects', 'health')) {
            $fields[] = 'health = :health';
            $params[':health'] = $payload['health'];
        }

        if ($this->db->columnExists('projects', 'risk_score')) {
            $fields[] = 'risk_score = :risk_score';
            $params[':risk_score'] = $payload['risk_score'] ?? 0;
        }

        if ($this->db->columnExists('projects', 'risk_level')) {
            $fields[] = 'risk_level = :risk_level';
            $params[':risk_level'] = $payload['risk_level'] ?? 'bajo';
        }

        if ($this->db->columnExists('projects', 'priority_code')) {
            $fields[] = 'priority_code = :priority';
            $params[':priority'] = $payload['priority'];
        } elseif ($this->db->columnExists('projects', 'priority')) {
            $fields[] = 'priority = :priority';
            $params[':priority'] = $payload['priority'];
        }

        if ($this->db->columnExists('projects', 'pm_id')) {
            $fields[] = 'pm_id = :pm_id';
            $params[':pm_id'] = $payload['pm_id'];
        }

        if ($this->db->columnExists('projects', 'project_type')) {
            $fields[] = 'project_type = :project_type';
            $params[':project_type'] = $payload['project_type'];
        }

        if ($this->db->columnExists('projects', 'methodology')) {
            $fields[] = 'methodology = :methodology';
            $params[':methodology'] = $payload['methodology'] ?? 'scrum';
        }

        if ($this->db->columnExists('projects', 'phase')) {
            $fields[] = 'phase = :phase';
            $params[':phase'] = $payload['phase'] ?? null;
        }

        if ($this->db->columnExists('projects', 'project_stage')) {
            $fields[] = 'project_stage = :project_stage';
            $params[':project_stage'] = $payload['project_stage'] ?? 'Discovery';
        }

        if ($this->db->columnExists('projects', 'budget')) {
            $fields[] = 'budget = :budget';
            $params[':budget'] = $payload['budget'];
        }

        if ($this->db->columnExists('projects', 'actual_cost')) {
            $fields[] = 'actual_cost = :actual_cost';
            $params[':actual_cost'] = $payload['actual_cost'];
        }

        if ($this->db->columnExists('projects', 'planned_hours')) {
            $fields[] = 'planned_hours = :planned_hours';
            $params[':planned_hours'] = $payload['planned_hours'];
        }

        if ($this->db->columnExists('projects', 'actual_hours')) {
            $fields[] = 'actual_hours = :actual_hours';
            $params[':actual_hours'] = $payload['actual_hours'];
        }

        if ($this->db->columnExists('projects', 'scope')) {
            $fields[] = 'scope = :scope';
            $params[':scope'] = $payload['scope'] ?? '';
        }

        if ($this->db->columnExists('projects', 'design_inputs')) {
            $fields[] = 'design_inputs = :design_inputs';
            $params[':design_inputs'] = $payload['design_inputs'] ?? '';
        }

        if ($this->db->columnExists('projects', 'client_participation')) {
            $fields[] = 'client_participation = :client_participation';
            $params[':client_participation'] = (int) ($payload['client_participation'] ?? 0);
        }

        if ($this->db->columnExists('projects', 'start_date')) {
            $fields[] = 'start_date = :start_date';
            $params[':start_date'] = $payload['start_date'];
        }

        if ($this->db->columnExists('projects', 'end_date')) {
            $fields[] = 'end_date = :end_date';
            $params[':end_date'] = $payload['end_date'];
        }

        if ($this->db->columnExists('projects', 'is_billable')) {
            $fields[] = 'is_billable = :is_billable';
            $params[':is_billable'] = (int) ($payload['is_billable'] ?? 0);
        }

        if ($this->db->columnExists('projects', 'billing_type')) {
            $fields[] = 'billing_type = :billing_type';
            $params[':billing_type'] = $payload['billing_type'] ?? 'fixed';
        }

        if ($this->db->columnExists('projects', 'billing_periodicity')) {
            $fields[] = 'billing_periodicity = :billing_periodicity';
            $params[':billing_periodicity'] = $payload['billing_periodicity'] ?? 'monthly';
        }

        if ($this->db->columnExists('projects', 'contract_value')) {
            $fields[] = 'contract_value = :contract_value';
            $params[':contract_value'] = (float) ($payload['contract_value'] ?? 0);
        }

        if ($this->db->columnExists('projects', 'currency_code')) {
            $fields[] = 'currency_code = :currency_code';
            $params[':currency_code'] = strtoupper((string) ($payload['currency_code'] ?? 'USD'));
        }

        if ($this->db->columnExists('projects', 'billing_start_date')) {
            $fields[] = 'billing_start_date = :billing_start_date';
            $params[':billing_start_date'] = $payload['billing_start_date'] ?? null;
        }

        if ($this->db->columnExists('projects', 'billing_end_date')) {
            $fields[] = 'billing_end_date = :billing_end_date';
            $params[':billing_end_date'] = $payload['billing_end_date'] ?? null;
        }

        if ($this->db->columnExists('projects', 'hourly_rate')) {
            $fields[] = 'hourly_rate = :hourly_rate';
            $params[':hourly_rate'] = (float) ($payload['hourly_rate'] ?? 0);
        }

        if (array_key_exists('design_review_done', $payload)) {
            $this->assertDesignReviewPrerequisites($id, (int) $payload['design_review_done']);
        }

        $this->assertIsoControlSequence($id, $payload, $beforeIsoFlags);

        $isoControls = [
            'design_inputs_defined',
            'design_review_done',
            'design_verification_done',
            'design_validation_done',
            'legal_requirements',
            'change_control_required',
        ];

        foreach ($isoControls as $control) {
            if ($this->db->columnExists('projects', $control) && array_key_exists($control, $payload)) {
                $fields[] = $control . ' = :' . $control;
                $params[':' . $control] = (int) $payload[$control];
            }
        }

        $this->db->execute(
            'UPDATE projects SET ' . implode(', ', $fields) . ' WHERE id = :id',
            $params
        );

        $afterIsoFlags = $this->mergedIsoFlags($beforeIsoFlags, $payload);
        $this->logIsoFlagChanges($id, $beforeIsoFlags, $afterIsoFlags, $userId);

        $riskEvaluations = $payload['risk_evaluations'] ?? null;
        if ($riskEvaluations === null && array_key_exists('risks', $payload)) {
            $riskEvaluations = $this->buildEvaluationsFromSelection($payload['risks']);
        }

        if (is_array($riskEvaluations)) {
            $this->syncProjectRiskEvaluations($id, $riskEvaluations);
        }
    }

    public function persistProgress(int $projectId, float $progress): void
    {
        if (!$this->db->columnExists('projects', 'progress')) {
            return;
        }

        $clamped = max(0.0, min(100.0, $progress));
        $this->db->execute(
            'UPDATE projects SET progress = :progress, updated_at = NOW() WHERE id = :id',
            [
                ':progress' => $clamped,
                ':id' => $projectId,
            ]
        );
    }

    public function deleteProject(int $id, bool $forceDelete = false, bool $isAdmin = false, ?int $userId = null): array
    {
        if ($forceDelete && $isAdmin) {
            return $this->forceDeleteWithCascade($id, $userId);
        }

        $inactivated = $this->inactivate($id);

        return [
            'success' => $inactivated,
            'inactivated' => $inactivated,
            'error' => $inactivated ? null : 'No se pudo inactivar el proyecto.',
        ];
    }

    public function inactivate(int $id): bool
    {
        if (!$this->db->columnExists('projects', 'active')) {
            return false;
        }

        return $this->db->execute('UPDATE projects SET active = 0, updated_at = NOW() WHERE id = :id', [':id' => $id]);
    }

    public function closeProject(int $id, string $health, ?string $riskLevel = null): void
    {
        $fields = [];
        $params = [':id' => $id];

        if ($this->db->columnExists('projects', 'status_code')) {
            $fields[] = 'status_code = :status';
            $params[':status'] = 'closed';
        } elseif ($this->db->columnExists('projects', 'status')) {
            $fields[] = 'status = :status';
            $params[':status'] = 'closed';
        }

        if ($this->db->columnExists('projects', 'health_code')) {
            $fields[] = 'health_code = :health';
            $params[':health'] = $health;
        } elseif ($this->db->columnExists('projects', 'health')) {
            $fields[] = 'health = :health';
            $params[':health'] = $health;
        }

        if ($riskLevel !== null && $this->db->columnExists('projects', 'risk_level')) {
            $fields[] = 'risk_level = :risk_level';
            $params[':risk_level'] = $riskLevel;
        }

        if ($this->db->columnExists('projects', 'end_date')) {
            $fields[] = 'end_date = :end_date';
            $params[':end_date'] = date('Y-m-d');
        }

        if (empty($fields)) {
            return;
        }

        $this->db->execute(
            'UPDATE projects SET ' . implode(', ', $fields) . ' WHERE id = :id',
            $params
        );
    }

    private function forceDeleteWithCascade(int $projectId, ?int $userId = null): array
    {
        $pdo = $this->db->connection();
        $transactionStarted = false;
        $deleted = [
            'timesheets' => 0,
            'tasks' => 0,
            'assignments' => 0,
            'design_inputs' => 0,
            'design_controls' => 0,
            'design_changes' => 0,
            'project_nodes' => 0,
            'risk_evaluations' => 0,
            'project_record' => 0,
        ];

        try {
            $pdo->beginTransaction();
            $transactionStarted = true;

            $deleted['timesheets'] = $this->deleteTimesheetsForProject($projectId);
            $deleted['tasks'] = $this->deleteTasksForProject($projectId);
            $deleted['assignments'] = $this->deleteAssignmentsForProject($projectId);
            $deleted['design_inputs'] = $this->deleteDesignInputsForProject($projectId);
            $deleted['design_controls'] = $this->deleteDesignControlsForProject($projectId);
            $deleted['design_changes'] = $this->deleteDesignChangesForProject($projectId);
            $deleted['risk_evaluations'] = $this->deleteRiskEvaluationsForProject($projectId);
            $deleted['project_nodes'] = $this->deleteProjectNodes($projectId);

            $deleted['project_record'] = $this->db->execute('DELETE FROM projects WHERE id = :id', [':id' => $projectId]) ? 1 : 0;

            $pdo->commit();
            $transactionStarted = false;
        } catch (\PDOException $e) {
            if ($transactionStarted && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        } catch (\Throwable $e) {
            if ($transactionStarted && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        $this->deleteProjectStorage($projectId);

        if ($userId !== null) {
            try {
                (new AuditLogRepository($this->db))->log(
                    $userId,
                    'project',
                    $projectId,
                    'project.force_delete',
                    [
                        'deleted' => $deleted,
                        'performed_at' => date('c'),
                    ]
                );
            } catch (\Throwable $e) {
                error_log('No se pudo registrar la eliminación en cascada: ' . $e->getMessage());
            }
        }

        return [
            'success' => true,
            'deleted' => $deleted,
        ];
    }

    private function deleteTimesheetsForProject(int $projectId): int
    {
        if (!$this->db->tableExists('timesheets') || !$this->db->tableExists('tasks')) {
            return 0;
        }

        $count = (int) ($this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM timesheets ts JOIN tasks t ON t.id = ts.task_id WHERE t.project_id = :project_id',
            [':project_id' => $projectId]
        )['total'] ?? 0);

        $this->db->execute(
            'DELETE ts FROM timesheets ts JOIN tasks t ON t.id = ts.task_id WHERE t.project_id = :project_id',
            [':project_id' => $projectId]
        );

        return $count;
    }

    private function deleteTasksForProject(int $projectId): int
    {
        if (!$this->db->tableExists('tasks')) {
            return 0;
        }

        $count = (int) ($this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM tasks WHERE project_id = :project_id',
            [':project_id' => $projectId]
        )['total'] ?? 0);

        $this->db->execute('DELETE FROM tasks WHERE project_id = :project_id', [':project_id' => $projectId]);

        return $count;
    }

    private function deleteAssignmentsForProject(int $projectId): int
    {
        if (!$this->db->tableExists('project_talent_assignments')) {
            return 0;
        }

        $count = (int) ($this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM project_talent_assignments WHERE project_id = :project_id',
            [':project_id' => $projectId]
        )['total'] ?? 0);

        $this->db->execute('DELETE FROM project_talent_assignments WHERE project_id = :project_id', [':project_id' => $projectId]);

        return $count;
    }

    private function deleteDesignInputsForProject(int $projectId): int
    {
        if (!$this->db->tableExists('project_design_inputs')) {
            return 0;
        }

        $count = (int) ($this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM project_design_inputs WHERE project_id = :project_id',
            [':project_id' => $projectId]
        )['total'] ?? 0);

        $this->db->execute('DELETE FROM project_design_inputs WHERE project_id = :project_id', [':project_id' => $projectId]);

        return $count;
    }

    private function deleteDesignControlsForProject(int $projectId): int
    {
        if (!$this->db->tableExists('project_design_controls')) {
            return 0;
        }

        $count = (int) ($this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM project_design_controls WHERE project_id = :project_id',
            [':project_id' => $projectId]
        )['total'] ?? 0);

        $this->db->execute('DELETE FROM project_design_controls WHERE project_id = :project_id', [':project_id' => $projectId]);

        return $count;
    }

    private function deleteDesignChangesForProject(int $projectId): int
    {
        if (!$this->db->tableExists('project_design_changes')) {
            return 0;
        }

        $count = (int) ($this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM project_design_changes WHERE project_id = :project_id',
            [':project_id' => $projectId]
        )['total'] ?? 0);

        $this->db->execute('DELETE FROM project_design_changes WHERE project_id = :project_id', [':project_id' => $projectId]);

        return $count;
    }

    private function deleteRiskEvaluationsForProject(int $projectId): int
    {
        if (!$this->db->tableExists('project_risk_evaluations')) {
            return 0;
        }

        $count = (int) ($this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM project_risk_evaluations WHERE project_id = :project_id',
            [':project_id' => $projectId]
        )['total'] ?? 0);

        $this->db->execute('DELETE FROM project_risk_evaluations WHERE project_id = :project_id', [':project_id' => $projectId]);

        return $count;
    }

    private function deleteProjectNodes(int $projectId): int
    {
        if (!$this->db->tableExists('project_nodes')) {
            return 0;
        }

        $count = (int) ($this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM project_nodes WHERE project_id = :project_id',
            [':project_id' => $projectId]
        )['total'] ?? 0);

        $this->db->execute('DELETE FROM project_nodes WHERE project_id = :project_id', [':project_id' => $projectId]);

        return $count;
    }

    private function deleteProjectStorage(int $projectId): void
    {
        $basePath = __DIR__ . '/../../public/storage/projects/' . $projectId;

        if (!is_dir($basePath)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($basePath);
    }

    public function syncProjectRiskEvaluations(int $projectId, array $evaluations): void
    {
        $this->db->execute('DELETE FROM project_risk_evaluations WHERE project_id = :project', [':project' => $projectId]);

        if (empty($evaluations)) {
            return;
        }

        $stmt = $this->db->connection()->prepare(
            'INSERT INTO project_risk_evaluations (project_id, risk_code, selected) VALUES (:project, :risk, :selected)'
        );

        foreach ($evaluations as $evaluation) {
            $code = trim((string) ($evaluation['risk_code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $stmt->execute([
                ':project' => $projectId,
                ':risk' => $code,
                ':selected' => (int) (($evaluation['selected'] ?? 0) === 1),
            ]);
        }
    }

    private function visibilityConditions(array $user, string $clientAlias, string $projectAlias): array
    {
        $conditions = [];
        $params = [];
        $hasClientPm = $this->db->columnExists('clients', 'pm_id');
        $hasProjectPm = $this->db->columnExists('projects', 'pm_id');
        $hasProjectActive = $this->db->columnExists('projects', 'active');

        if (!$this->isPrivileged($user)) {
            if ($hasClientPm) {
                $conditions[] = $clientAlias . '.pm_id = :clientPmId';
                $params[':clientPmId'] = $user['id'];
            }

            if ($hasProjectPm) {
                $conditions[] = $projectAlias . '.pm_id = :projectPmId';
                $params[':projectPmId'] = $user['id'];
            }
        }

        if ($hasProjectActive) {
            $conditions[] = $projectAlias . '.active = 1';
        }

        return [$conditions, $params];
    }

    public function clientKpis(array $projects, array $assignments): array
    {
        $activeProjects = array_filter($projects, fn (array $p) => $p['status'] !== 'closed' && $p['status'] !== 'cancelled');
        $progressValues = array_column($activeProjects, 'progress');
        $avgProgress = $progressValues ? array_sum($progressValues) / count($progressValues) : 0.0;

        $riskLevel = 'bajo';
        foreach ($activeProjects as $project) {
            if ($project['health'] === 'critical') {
                $riskLevel = 'alto';
                break;
            }
            if ($project['health'] === 'at_risk') {
                $riskLevel = 'medio';
            }
        }

        $usedHours = 0.0;
        $availableHours = 0.0;
        $seenTalents = [];
        foreach ($assignments as $assignment) {
            $usedHours += (float) ($assignment['weekly_hours'] ?? 0);
            $talentId = (int) $assignment['talent_id'];
            if (!in_array($talentId, $seenTalents, true)) {
                $availableHours += (float) ($assignment['capacidad_horaria'] ?? 0);
                $seenTalents[] = $talentId;
            }
        }

        $capacityPercent = $availableHours > 0 ? round(($usedHours / $availableHours) * 100, 1) : 0;

        return [
            'avg_progress' => round($avgProgress, 1),
            'risk_level' => $riskLevel,
            'active_projects' => count($activeProjects),
            'total_projects' => count($projects),
            'capacity_used' => $usedHours,
            'capacity_available' => $availableHours,
            'capacity_percent' => $capacityPercent,
        ];
    }

    public function hasTasks(int $projectId): bool
    {
        if (!$this->db->tableExists('tasks')) {
            return false;
        }

        $result = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM tasks WHERE project_id = :projectId',
            [':projectId' => $projectId]
        );

        return (int) ($result['total'] ?? 0) > 0;
    }

    public function openTasksCount(int $projectId): int
    {
        if (!$this->db->tableExists('tasks')) {
            return 0;
        }

        $openStatuses = ['todo', 'pending', 'in_progress', 'review', 'blocked'];
        $params = [':projectId' => $projectId];
        $statusPlaceholders = [];

        if ($this->db->columnExists('tasks', 'status')) {
            foreach ($openStatuses as $index => $status) {
                $placeholder = ':status' . $index;
                $statusPlaceholders[] = $placeholder;
                $params[$placeholder] = $status;
            }
        }

        $statusFilter = $statusPlaceholders ? ' AND status IN (' . implode(', ', $statusPlaceholders) . ')' : '';
        $result = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM tasks WHERE project_id = :projectId' . $statusFilter,
            $params
        );

        return (int) ($result['total'] ?? 0);
    }

    private function attachProjectSignal(array $project): array
    {
        $project['signal'] = $this->projectSignal($project);
        $project['risks'] = $this->splitRisks($project['risk_codes'] ?? null);

        return $project;
    }

    private function attachProjectOperationalData(
        array $project,
        ?array $noteData,
        ?array $stopperData,
        bool $hasScopeChanges,
        ?string $lastProgressAt
    ): array {
        $project['latest_note'] = $noteData;
        $project['top_stopper'] = $stopperData;
        $project['has_scope_changes'] = $hasScopeChanges;
        $project['last_progress_at'] = $lastProgressAt;
        $project['signals'] = $this->buildOperationalSignals($project, $stopperData, $hasScopeChanges, $lastProgressAt);

        return $project;
    }

    private function buildOperationalSignals(array $project, ?array $stopperData, bool $hasScopeChanges, ?string $lastProgressAt): array
    {
        $signals = [];

        if (($stopperData['critical_count'] ?? 0) > 0) {
            $signals[] = '⚠ Bloqueo crítico';
        }

        if (count($project['risks'] ?? []) > 0) {
            $signals[] = '⚠ Riesgo activo';
        }

        if ($lastProgressAt !== null) {
            $daysWithoutProgress = (int) floor((time() - strtotime($lastProgressAt)) / 86400);
            if ($daysWithoutProgress >= 7) {
                $signals[] = '⚠ Sin avance reciente';
            }
        }

        if ($hasScopeChanges) {
            $signals[] = '⚠ Cambio de alcance';
        }

        return array_slice($signals, 0, 3);
    }

    private function latestNotesByProject(array $projectIds): array
    {
        if (empty($projectIds) || !$this->db->tableExists('audit_log')) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($projectIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT al.entity_id AS project_id, al.created_at, al.payload, u.name AS author
             FROM audit_log al
             LEFT JOIN users u ON u.id = al.user_id
             INNER JOIN (
                SELECT entity_id, MAX(created_at) AS max_created_at
                FROM audit_log
                WHERE entity = 'project_note' AND action = 'project_note_created' AND entity_id IN ($placeholders)
                GROUP BY entity_id
             ) latest ON latest.entity_id = al.entity_id AND latest.max_created_at = al.created_at
             WHERE al.entity = 'project_note' AND al.action = 'project_note_created'",
            $projectIds
        );

        $index = [];
        foreach ($rows as $row) {
            $payload = json_decode((string) ($row['payload'] ?? ''), true);
            $text = is_array($payload) ? trim((string) ($payload['note'] ?? '')) : '';
            $projectId = (int) ($row['project_id'] ?? 0);
            $index[$projectId] = [
                'text' => $text,
                'author' => $row['author'] ?? 'Sistema',
                'created_at' => $row['created_at'] ?? null,
                'extra_count' => max(0, $this->notesCountForProject($projectId) - 1),
            ];
        }

        return $index;
    }

    private function notesCountForProject(int $projectId): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM audit_log WHERE entity = 'project_note' AND action = 'project_note_created' AND entity_id = :projectId",
            [':projectId' => $projectId]
        );

        return (int) ($row['total'] ?? 0);
    }

    private function activeStoppersByProject(array $projectIds): array
    {
        if (empty($projectIds) || !$this->db->tableExists('project_stoppers')) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($projectIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT s.project_id, s.title, s.description, s.impact_level, s.created_at,
                    totals.total_count, totals.critical_count, totals.high_count
             FROM project_stoppers s
             INNER JOIN (
                SELECT project_id,
                       COUNT(*) AS total_count,
                       SUM(CASE WHEN impact_level = 'critico' THEN 1 ELSE 0 END) AS critical_count,
                       SUM(CASE WHEN impact_level = 'alto' THEN 1 ELSE 0 END) AS high_count,
                       MAX(CASE impact_level WHEN 'critico' THEN 3 WHEN 'alto' THEN 2 WHEN 'medio' THEN 1 ELSE 0 END) AS max_severity
                FROM project_stoppers
                WHERE status <> 'cerrado' AND project_id IN ($placeholders)
                GROUP BY project_id
             ) totals ON totals.project_id = s.project_id
             WHERE s.status <> 'cerrado' AND s.project_id IN ($placeholders)
             ORDER BY s.project_id,
                      CASE s.impact_level WHEN 'critico' THEN 3 WHEN 'alto' THEN 2 WHEN 'medio' THEN 1 ELSE 0 END DESC,
                      s.created_at DESC",
            array_merge($projectIds, $projectIds)
        );

        $index = [];
        foreach ($rows as $row) {
            $projectId = (int) ($row['project_id'] ?? 0);
            if (isset($index[$projectId])) {
                continue;
            }

            $text = trim((string) ($row['title'] ?? ''));
            if ($text === '') {
                $text = trim((string) ($row['description'] ?? ''));
            }

            $index[$projectId] = [
                'text' => $text,
                'impact_level' => $row['impact_level'] ?? 'medio',
                'created_at' => $row['created_at'] ?? null,
                'extra_count' => max(0, (int) ($row['total_count'] ?? 0) - 1),
                'critical_count' => (int) ($row['critical_count'] ?? 0),
                'high_count' => (int) ($row['high_count'] ?? 0),
            ];
        }

        return $index;
    }

    private function scopeChangesByProject(array $projectIds): array
    {
        if (empty($projectIds) || !$this->db->tableExists('audit_log')) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($projectIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT entity_id AS project_id, COUNT(*) AS total
             FROM audit_log
             WHERE entity = 'project'
               AND action = 'project.change'
               AND entity_id IN ($placeholders)
               AND payload LIKE '%\"scope\"%'
             GROUP BY entity_id",
            $projectIds
        );

        $index = [];
        foreach ($rows as $row) {
            $index[(int) ($row['project_id'] ?? 0)] = (int) ($row['total'] ?? 0) > 0;
        }

        return $index;
    }

    private function progressActivityByProject(array $projectIds): array
    {
        if (empty($projectIds) || !$this->db->tableExists('audit_log')) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($projectIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT entity_id AS project_id, MAX(created_at) AS last_progress_at
             FROM audit_log
             WHERE entity = 'project'
               AND action = 'project_progress_updated'
               AND entity_id IN ($placeholders)
             GROUP BY entity_id",
            $projectIds
        );

        $index = [];
        foreach ($rows as $row) {
            $index[(int) ($row['project_id'] ?? 0)] = $row['last_progress_at'] ?? null;
        }

        return $index;
    }

    private function projectSignal(array $project): array
    {
        $severity = 'green';
        $reasons = [];

        $health = $project['health'] ?? '';
        if (in_array($health, ['critical', 'red'], true)) {
            $severity = 'red';
            $reasons[] = 'Salud crítica reportada por el equipo.';
        } elseif (in_array($health, ['at_risk', 'yellow'], true)) {
            $severity = $this->maxSeverity($severity, 'yellow');
            $reasons[] = 'El proyecto fue marcado como en riesgo.';
        }

        $costDeviation = $this->deviationPercent((float) ($project['actual_cost'] ?? 0), (float) ($project['budget'] ?? 0));
        if ($costDeviation !== null) {
            $costRules = $this->signalRules['cost'] ?? [];
            $redCost = (float) ($costRules['red_above'] ?? 0.1);
            $yellowCost = (float) ($costRules['yellow_above'] ?? 0.05);
            if ($costDeviation > $redCost) {
                $severity = 'red';
                $reasons[] = 'El costo supera el presupuesto en más de ' . (int) round($redCost * 100) . '%.';
            } elseif ($costDeviation > $yellowCost) {
                $severity = $this->maxSeverity($severity, 'yellow');
                $reasons[] = 'El costo supera el presupuesto en más de ' . (int) round($yellowCost * 100) . '%.';
            }
        }

        $actualHoursForSignal = (float) ($project['timesheet_hours_logged'] ?? $project['actual_hours'] ?? 0);
        $plannedHoursForSignal = (float) ($project['hours_estimated_total'] ?? $project['planned_hours'] ?? 0);
        $hoursDeviation = $this->deviationPercent($actualHoursForSignal, $plannedHoursForSignal);
        if ($hoursDeviation !== null) {
            $consumptionPercent = ($actualHoursForSignal / max(1.0, $plannedHoursForSignal)) * 100;
            if ($consumptionPercent > 100.0) {
                $severity = 'red';
                $reasons[] = 'El consumo de horas superó el 100% de lo estimado.';
            } elseif ($consumptionPercent > 80.0) {
                $severity = $this->maxSeverity($severity, 'yellow');
                $reasons[] = 'El consumo de horas superó el 80% de lo estimado.';
            }
        }

        $progress = (float) ($project['progress'] ?? 0);
        $progressRules = $this->signalRules['progress'] ?? [];
        $redProgress = (float) ($progressRules['red_below'] ?? 25.0);
        $yellowProgress = (float) ($progressRules['yellow_below'] ?? 50.0);
        if ($progress < $redProgress) {
            $severity = 'red';
            $reasons[] = 'El avance está por debajo del ' . $redProgress . '% configurado.';
        } elseif ($progress < $yellowProgress) {
            $severity = $this->maxSeverity($severity, 'yellow');
            $reasons[] = 'El avance está rezagado (<' . $yellowProgress . '%).';
        }

        $label = match ($severity) {
            'red' => 'Rojo',
            'yellow' => 'Amarillo',
            default => 'Verde',
        };

        return [
            'code' => $severity,
            'label' => $label,
            'reasons' => $reasons ?: ['Sin alertas operativas detectadas.'],
            'cost_deviation' => $costDeviation,
            'hours_deviation' => $hoursDeviation,
            'progress' => $progress,
        ];
    }

    public function clientSignal(array $projects): array
    {
        $hasRed = false;
        $hasYellow = false;

        foreach ($projects as $project) {
            $signal = $project['signal']['code'] ?? 'green';
            if ($signal === 'red') {
                $hasRed = true;
                break;
            }
            if ($signal === 'yellow') {
                $hasYellow = true;
            }
        }

        $code = $hasRed ? 'red' : ($hasYellow ? 'yellow' : 'green');
        $label = match ($code) {
            'red' => 'Rojo',
            'yellow' => 'Amarillo',
            default => 'Verde',
        };

        return [
            'code' => $code,
            'label' => $label,
            'summary' => $this->clientSignalSummary($projects, $code),
        ];
    }

    private function clientSignalSummary(array $projects, string $code): string
    {
        $totalProjects = count($projects);
        $redProjects = count(array_filter($projects, fn ($p) => ($p['signal']['code'] ?? '') === 'red'));
        $yellowProjects = count(array_filter($projects, fn ($p) => ($p['signal']['code'] ?? '') === 'yellow'));

        return match ($code) {
            'red' => "Al menos $redProjects de $totalProjects proyectos requieren atención inmediata.",
            'yellow' => "Hay $yellowProjects proyecto(s) con alertas preventivas.",
            default => 'Todos los proyectos están dentro de los parámetros operativos.',
        };
    }

    private function maxSeverity(string $current, string $candidate): string
    {
        $rank = ['green' => 1, 'yellow' => 2, 'red' => 3];

        return ($rank[$candidate] ?? 1) > ($rank[$current] ?? 1) ? $candidate : $current;
    }

    private function enrichPmoIndicators(array $project): array
    {
        $loggedHours = round((float) ($project['timesheet_hours_logged'] ?? $project['actual_hours'] ?? 0), 2);
        $estimatedHours = (float) ($project['hours_estimated_total'] ?? 0);
        if ($estimatedHours <= 0) {
            $estimatedHours = (float) ($project['planned_hours'] ?? 0);
        }
        $estimatedHours = round(max(0.0, $estimatedHours), 2);

        $totalTasks = max(0, (int) ($project['tasks_total_auto'] ?? 0));
        $completedTasks = max(0, (int) ($project['tasks_completed_auto'] ?? 0));
        $overdueTasks = max(0, (int) ($project['tasks_overdue_auto'] ?? 0));
        $openBlockers = max(0, (int) ($project['blockers_count'] ?? 0));
        $criticalBlockers = max(0, (int) ($project['blocker_critical_count'] ?? 0));

        $progressHours = $estimatedHours > 0
            ? round(min(100.0, ($loggedHours / $estimatedHours) * 100), 2)
            : (isset($project['progress_hours_auto']) && $project['progress_hours_auto'] !== null ? round((float) $project['progress_hours_auto'], 2) : null);
        $progressTasks = $totalTasks > 0
            ? round(($completedTasks / $totalTasks) * 100, 2)
            : (isset($project['progress_tasks_auto']) && $project['progress_tasks_auto'] !== null ? round((float) $project['progress_tasks_auto'], 2) : null);
        $consumptionPercent = $estimatedHours > 0 ? round(($loggedHours / $estimatedHours) * 100, 2) : null;
        $deviationHours = $estimatedHours > 0 ? round($loggedHours - $estimatedHours, 2) : null;
        $deviationPercent = $estimatedHours > 0 ? round((($loggedHours - $estimatedHours) / $estimatedHours) * 100, 2) : null;

        $riskScore = $this->automaticPmoRiskScore([
            'open_blockers' => $openBlockers,
            'critical_blockers' => $criticalBlockers,
            'overdue_tasks' => $overdueTasks,
            'hours_consumption_percent' => $consumptionPercent,
        ]);
        $riskLevel = $this->automaticPmoRiskLevel($riskScore);

        $project['timesheet_hours_logged'] = $loggedHours;
        $project['hours_estimated_total'] = $estimatedHours;
        $project['progress_hours_auto'] = $progressHours;
        $project['progress_tasks_auto'] = $progressTasks;
        $project['hours_consumption_percent'] = $consumptionPercent;
        $project['hours_deviation_hours'] = $deviationHours;
        $project['hours_deviation_percent'] = $deviationPercent;
        $project['pmo_risk_score'] = $riskScore;
        $project['pmo_risk_level'] = $riskLevel;
        $project['hours_alert_level'] = $consumptionPercent === null
            ? 'none'
            : ($consumptionPercent > 100 ? 'critical' : ($consumptionPercent > 80 ? 'warning' : 'none'));

        return $project;
    }

    private function automaticPmoRiskScore(array $metrics): int
    {
        $score = 0;
        $score += max(0, (int) ($metrics['open_blockers'] ?? 0)) * 10;
        $score += max(0, (int) ($metrics['critical_blockers'] ?? 0)) * 20;
        $score += max(0, (int) ($metrics['overdue_tasks'] ?? 0)) * 8;

        $consumption = (float) ($metrics['hours_consumption_percent'] ?? 0);
        if ($consumption > 100) {
            $score += 30;
        } elseif ($consumption > 80) {
            $score += 15;
        }

        return max(0, min(100, $score));
    }

    private function automaticPmoRiskLevel(int $riskScore): string
    {
        if ($riskScore >= 70) {
            return 'critical';
        }
        if ($riskScore >= 40) {
            return 'warning';
        }

        return 'on_track';
    }

    private function deviationPercent(float $actual, float $planned): ?float
    {
        if ($planned <= 0.0) {
            return null;
        }

        return ($actual - $planned) / $planned;
    }

    private function isPrivileged(array $user): bool
    {
        return in_array($user['role'] ?? '', self::ADMIN_ROLES, true);
    }

    private function splitRisks(?string $value): array
    {
        if (!$value) {
            return [];
        }

        $parts = array_map('trim', explode(',', $value));

        return array_values(array_filter($parts, fn ($risk) => $risk !== ''));
    }

    private function assertDesignReviewPrerequisites(int $projectId, int $designReviewDone): void
    {
        if ($designReviewDone !== 1) {
            return;
        }

        if (!$this->db->tableExists('project_design_inputs')) {
            return;
        }

        $designInputsRepo = new DesignInputsRepository($this->db);
        $inputsCount = $designInputsRepo->countByProject($projectId);

        if ($inputsCount < 1) {
            throw new \InvalidArgumentException('No puedes marcar la revisión de diseño como completada sin entradas de diseño registradas.');
        }
    }

    private function assertIsoControlSequence(int $projectId, array $payload, array $currentIsoFlags): void
    {
        if (!$this->db->tableExists('project_design_controls')) {
            return;
        }

        $isoColumns = $this->isoFlagColumns();
        if (empty($isoColumns)) {
            return;
        }

        $controlsRepo = new DesignControlsRepository($this->db);
        $afterReview = $this->targetIsoFlagValue('design_review_done', $payload, $currentIsoFlags);
        $afterVerification = $this->targetIsoFlagValue('design_verification_done', $payload, $currentIsoFlags);
        $afterValidation = $this->targetIsoFlagValue('design_validation_done', $payload, $currentIsoFlags);

        $hasRevisionControl = $controlsRepo->countByType($projectId, 'revision') > 0;
        $hasVerificationControl = $controlsRepo->countByType($projectId, 'verificacion') > 0;
        $hasValidationControl = $controlsRepo->countByType($projectId, 'validacion') > 0;

        if ($afterReview === 1 && !$hasRevisionControl) {
            throw new \InvalidArgumentException('Registra al menos un control de revisión antes de marcar la revisión como completada.');
        }

        if ($afterVerification === 1) {
            if (!$hasRevisionControl) {
                throw new \InvalidArgumentException('No puedes completar la verificación sin revisiones de diseño registradas.');
            }
            if (!$hasVerificationControl) {
                throw new \InvalidArgumentException('Registra al menos un control de verificación antes de marcarla como completada.');
            }
        }

        if ($afterValidation === 1) {
            if ($afterVerification !== 1) {
                throw new \InvalidArgumentException('Completa la verificación antes de dar por terminada la validación.');
            }
            if (!$hasVerificationControl) {
                throw new \InvalidArgumentException('No puedes completar la validación sin verificaciones registradas.');
            }
            if (!$hasValidationControl) {
                throw new \InvalidArgumentException('Registra al menos un control de validación antes de marcarla como completada.');
            }
        }
    }

    public function persistIsoFlags(int $projectId, array $flags, ?int $userId = null): void
    {
        $columns = $this->isoFlagColumns();
        if (empty($columns)) {
            return;
        }

        $fields = [];
        $params = [':id' => $projectId];

        foreach ($columns as $flag) {
            if (array_key_exists($flag, $flags)) {
                $fields[] = $flag . ' = :' . $flag;
                $params[':' . $flag] = (int) $flags[$flag];
            }
        }

        if (empty($fields)) {
            return;
        }

        $before = $this->projectIsoFlags($projectId);
        $this->db->execute(
            'UPDATE projects SET ' . implode(', ', $fields) . ' WHERE id = :id',
            $params
        );

        $after = $this->mergedIsoFlags($before, $flags);
        $this->logIsoFlagChanges($projectId, $before, $after, $userId);
    }

    private function projectIsoFlags(int $projectId): array
    {
        $isoColumns = $this->isoFlagColumns();
        if (empty($isoColumns)) {
            return [];
        }

        $select = implode(', ', $isoColumns);
        $result = $this->db->fetchOne(
            "SELECT $select FROM projects WHERE id = :id",
            [':id' => $projectId]
        );

        return $result ?: [];
    }

    public function isoFlagColumns(): array
    {
        $candidates = [
            'design_inputs_defined',
            'design_review_done',
            'design_verification_done',
            'design_validation_done',
            'legal_requirements',
            'change_control_required',
        ];

        $existing = [];
        foreach ($candidates as $column) {
            if ($this->db->columnExists('projects', $column)) {
                $existing[] = $column;
            }
        }

        return $existing;
    }

    private function targetIsoFlagValue(string $flag, array $payload, array $currentIsoFlags): int
    {
        if (!in_array($flag, $this->isoFlagColumns(), true)) {
            return 0;
        }

        if (array_key_exists($flag, $payload)) {
            return (int) $payload[$flag];
        }

        return (int) ($currentIsoFlags[$flag] ?? 0);
    }

    private function mergedIsoFlags(array $before, array $payload): array
    {
        $after = [];
        foreach ($this->isoFlagColumns() as $flag) {
            $after[$flag] = $this->targetIsoFlagValue($flag, $payload, $before);
        }

        return $after;
    }

    private function logIsoFlagChanges(int $projectId, array $before, array $after, ?int $userId): void
    {
        $changes = [];
        foreach ($this->isoFlagColumns() as $flag) {
            $beforeValue = (int) ($before[$flag] ?? 0);
            $afterValue = (int) ($after[$flag] ?? $beforeValue);
            if ($beforeValue !== $afterValue) {
                $changes[$flag] = [
                    'before' => $beforeValue,
                    'after' => $afterValue,
                ];
            }
        }

        if (empty($changes)) {
            return;
        }

        try {
            (new AuditLogRepository($this->db))->log(
                $userId,
                'project',
                $projectId,
                'project.iso_flags',
                [
                    'before' => array_map(fn ($change) => $change['before'], $changes),
                    'after' => array_map(fn ($change) => $change['after'], $changes),
                ]
            );
        } catch (\Throwable $e) {
            error_log('No se pudo registrar el cambio de banderas ISO: ' . $e->getMessage());
        }
    }

    public function riskEvaluationsForProject(int $projectId): array
    {
        if (!$this->db->tableExists('project_risk_evaluations')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT risk_code, selected FROM project_risk_evaluations WHERE project_id = :project',
            [':project' => $projectId]
        );
    }

    private function buildEvaluationsFromSelection(array $risks): array
    {
        return array_map(
            fn ($risk) => ['risk_code' => (string) $risk, 'selected' => 1],
            array_values(array_unique(array_filter(array_map('trim', $risks), fn ($risk) => $risk !== '')))
        );
    }
}
