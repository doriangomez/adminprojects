<?php

declare(strict_types=1);

class ProjectsRepository
{
    private const ADMIN_ROLES = ['Administrador', 'PMO'];

    private array $signalRules;

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

        if (!empty($filters['methodology'])) {
            $conditions[] = 'p.methodology = :methodology';
            $params[':methodology'] = $filters['methodology'];
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
            'prisk.risks AS risk_codes',
            'c.name AS client',
        ];

        $joins = [
            'JOIN clients c ON c.id = p.client_id',
            'LEFT JOIN (SELECT project_id, GROUP_CONCAT(risk_code) AS risks FROM project_risk_evaluations WHERE selected = 1 GROUP BY project_id) prisk ON prisk.project_id = p.id',
        ];

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

        return array_map(fn (array $project) => $this->attachProjectSignal($project), $projects);
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
            'prisk.risks AS risk_codes',
            'c.name AS client_name',
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

    public function projectsForClient(int $clientId, array $user): array
    {
        [$conditions, $params] = $this->visibilityConditions($user, 'c', 'p');
        $conditions[] = 'c.id = :clientId';
        $params[':clientId'] = $clientId;
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

        if ($this->db->tableExists('timesheets') && $this->db->tableExists('tasks')) {
            $timesheets = (int) ($this->db->fetchOne(
                'SELECT COUNT(*) AS total FROM timesheets t JOIN tasks tk ON tk.id = t.task_id WHERE tk.project_id = :id',
                [':id' => $projectId]
            )['total'] ?? 0);
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
        if (!$this->db->tableExists('timesheets') || !$this->db->tableExists('tasks')) {
            return null;
        }

        $row = $this->db->fetchOne(
            'SELECT SUM(ts.hours) AS total
             FROM timesheets ts
             JOIN tasks t ON t.id = ts.task_id
             WHERE t.project_id = :project_id',
            [':project_id' => $projectId]
        );

        return $row ? (float) ($row['total'] ?? 0) : 0.0;
    }

    public function assignmentsForProject(int $projectId, array $user): array
    {
        [$conditions, $params] = $this->visibilityConditions($user, 'c', 'p');
        $conditions[] = 'p.id = :projectId';
        $params[':projectId'] = $projectId;
        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        $hasTalentColumn = $this->db->columnExists('project_talent_assignments', 'talent_id');

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
            $select[] = 't.weekly_capacity';
            $joins[] = 'LEFT JOIN talents t ON t.id = a.talent_id';
        } else {
            $select[] = 'u.name AS talent_name';
            $select[] = '0 AS weekly_capacity';
            $select[] = 'a.user_id AS talent_id';
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
            $select[] = 't.weekly_capacity';
            $joins[] = 'LEFT JOIN talents t ON t.id = a.talent_id';
        } else {
            $select[] = 'u.name AS talent_name';
            $select[] = '0 AS weekly_capacity';
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

        $userId = (int) ($payload['user_id'] ?? 0);
        if ($userId <= 0 && $this->db->tableExists('talents') && $this->db->columnExists('talents', 'user_id')) {
            $talentUser = $this->db->fetchOne(
                'SELECT user_id FROM talents WHERE id = :id',
                [':id' => (int) $payload['talent_id']]
            );
            $userId = (int) ($talentUser['user_id'] ?? 0);
        }
        if ($userId <= 0) {
            $userId = (int) ($payload['created_by'] ?? 0);
        }

        $totalPercent = $this->db->fetchOne(
            'SELECT SUM(allocation_percent) AS total_percent FROM project_talent_assignments WHERE talent_id = :talent',
            [':talent' => $payload['talent_id']]
        );
        $totalHours = $this->db->fetchOne(
            'SELECT SUM(weekly_hours) AS total_hours FROM project_talent_assignments WHERE talent_id = :talent',
            [':talent' => $payload['talent_id']]
        );

        $talent = $this->db->fetchOne('SELECT weekly_capacity FROM talents WHERE id = :id', [':id' => $payload['talent_id']]);
        $weeklyCapacity = (float) ($talent['weekly_capacity'] ?? 0);

        $newPercentTotal = (float) ($totalPercent['total_percent'] ?? 0) + (float) ($payload['allocation_percent'] ?? 0);
        $newHoursTotal = (float) ($totalHours['total_hours'] ?? 0) + (float) ($payload['weekly_hours'] ?? 0);

        if ($payload['allocation_percent'] !== null && $newPercentTotal > 100.0) {
            throw new InvalidArgumentException('La asignación supera el 100% de disponibilidad del talento.');
        }

        if ($weeklyCapacity > 0 && $payload['weekly_hours'] !== null && $newHoursTotal > $weeklyCapacity) {
            throw new InvalidArgumentException('Las horas asignadas exceden la capacidad semanal del talento.');
        }

        $this->db->insert(
            'INSERT INTO project_talent_assignments (project_id, user_id, talent_id, role, start_date, end_date, allocation_percent, weekly_hours, cost_type, cost_value, is_external, requires_timesheet, requires_timesheet_approval, assignment_status, active, created_at, updated_at)
             VALUES (:project_id, :user_id, :talent_id, :role, :start_date, :end_date, :allocation_percent, :weekly_hours, :cost_type, :cost_value, :is_external, :requires_timesheet, :requires_timesheet_approval, :assignment_status, :active, NOW(), NOW())',
            [
                ':project_id' => (int) $payload['project_id'],
                ':user_id' => $userId,
                ':talent_id' => (int) $payload['talent_id'],
                ':role' => $payload['role'],
                ':start_date' => $payload['start_date'] ?: null,
                ':end_date' => $payload['end_date'] ?: null,
                ':allocation_percent' => $payload['allocation_percent'],
                ':weekly_hours' => $payload['weekly_hours'],
                ':cost_type' => $payload['cost_type'],
                ':cost_value' => $payload['cost_value'],
                ':is_external' => (int) $payload['is_external'],
                ':requires_timesheet' => (int) $payload['requires_timesheet'],
                ':requires_timesheet_approval' => (int) $payload['requires_timesheet_approval'],
                ':assignment_status' => $assignmentStatus,
                ':active' => $activeFlag,
            ]
        );
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

            $columns = [
                'client_id',
                'pm_id',
                'name',
                'project_type',
                'methodology',
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

            if ($this->db->columnExists('projects', 'status_code')) {
                $columns[] = 'status_code';
                $placeholders[] = ':status';
                $params[':status'] = $status;
            } elseif ($this->db->columnExists('projects', 'status')) {
                $columns[] = 'status';
                $placeholders[] = ':status';
                $params[':status'] = $status;
            }

            if ($this->db->columnExists('projects', 'health_code')) {
                $columns[] = 'health_code';
                $placeholders[] = ':health';
                $params[':health'] = $health;
            } elseif ($this->db->columnExists('projects', 'health')) {
                $columns[] = 'health';
                $placeholders[] = ':health';
                $params[':health'] = $health;
            }

            if ($this->db->columnExists('projects', 'priority_code')) {
                $columns[] = 'priority_code';
                $placeholders[] = ':priority';
                $params[':priority'] = $payload['priority'];
            } elseif ($this->db->columnExists('projects', 'priority')) {
                $columns[] = 'priority';
                $placeholders[] = ':priority';
                $params[':priority'] = $payload['priority'];
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
                $availableHours += (float) ($assignment['weekly_capacity'] ?? 0);
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

        $openStatuses = ['todo', 'in_progress', 'review', 'blocked'];
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

        $hoursDeviation = $this->deviationPercent((float) ($project['actual_hours'] ?? 0), (float) ($project['planned_hours'] ?? 0));
        if ($hoursDeviation !== null) {
            $hoursRules = $this->signalRules['hours'] ?? [];
            $redHours = (float) ($hoursRules['red_above'] ?? 0.1);
            $yellowHours = (float) ($hoursRules['yellow_above'] ?? 0.05);
            if ($hoursDeviation > $redHours) {
                $severity = 'red';
                $reasons[] = 'Las horas ejecutadas superan el plan en más de ' . (int) round($redHours * 100) . '%.';
            } elseif ($hoursDeviation > $yellowHours) {
                $severity = $this->maxSeverity($severity, 'yellow');
                $reasons[] = 'Las horas ejecutadas superan el plan en más de ' . (int) round($yellowHours * 100) . '%.';
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
