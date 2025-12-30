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

    public function assignmentsForProject(int $projectId, array $user): array
    {
        [$conditions, $params] = $this->visibilityConditions($user, 'c', 'p');
        $conditions[] = 'p.id = :projectId';
        $params[':projectId'] = $projectId;
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
            'INSERT INTO project_talent_assignments (project_id, talent_id, role, start_date, end_date, allocation_percent, weekly_hours, cost_type, cost_value, is_external, requires_timesheet, requires_approval, created_at, updated_at)
             VALUES (:project_id, :talent_id, :role, :start_date, :end_date, :allocation_percent, :weekly_hours, :cost_type, :cost_value, :is_external, :requires_timesheet, :requires_approval, NOW(), NOW())',
            [
                ':project_id' => (int) $payload['project_id'],
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
                ':requires_approval' => (int) $payload['requires_approval'],
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
                ':progress' => $payload['progress'] ?? 0,
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
                $params[':client_participation'] = $payload['client_participation'] ?? 'media';
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

    public function updateProject(int $id, array $payload): void
    {
        $fields = ['name = :name'];
        $params = [
            ':id' => $id,
            ':name' => $payload['name'],
        ];

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

        if ($this->db->columnExists('projects', 'progress')) {
            $fields[] = 'progress = :progress';
            $params[':progress'] = $payload['progress'];
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
            $params[':client_participation'] = $payload['client_participation'] ?? 'media';
        }

        if ($this->db->columnExists('projects', 'start_date')) {
            $fields[] = 'start_date = :start_date';
            $params[':start_date'] = $payload['start_date'];
        }

        if ($this->db->columnExists('projects', 'end_date')) {
            $fields[] = 'end_date = :end_date';
            $params[':end_date'] = $payload['end_date'];
        }

        $this->db->execute(
            'UPDATE projects SET ' . implode(', ', $fields) . ' WHERE id = :id',
            $params
        );

        $riskEvaluations = $payload['risk_evaluations'] ?? null;
        if ($riskEvaluations === null && array_key_exists('risks', $payload)) {
            $riskEvaluations = $this->buildEvaluationsFromSelection($payload['risks']);
        }

        if (is_array($riskEvaluations)) {
            $this->syncProjectRiskEvaluations($id, $riskEvaluations);
        }
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

        if ($this->db->columnExists('projects', 'progress')) {
            $fields[] = 'progress = :progress';
            $params[':progress'] = 100.0;
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

        if (!$this->isPrivileged($user)) {
            if ($hasClientPm) {
                $conditions[] = $clientAlias . '.pm_id = :pmId';
                $params[':pmId'] = $user['id'];
            }

            if ($hasProjectPm) {
                $conditions[] = $projectAlias . '.pm_id = :pmId';
                $params[':pmId'] = $user['id'];
            }
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
