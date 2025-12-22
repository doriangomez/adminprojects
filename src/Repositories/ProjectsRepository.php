<?php

declare(strict_types=1);

class ProjectsRepository
{
    private const ADMIN_ROLES = ['Administrador', 'PMO'];

    public function __construct(private Database $db)
    {
    }

    public function summary(array $user): array
    {
        $conditions = [];
        $params = [];
        $hasPmColumn = $this->db->columnExists('projects', 'pm_id');
        $hasStatusColumn = $this->db->columnExists('projects', 'status');
        $hasHealthColumn = $this->db->columnExists('projects', 'health');
        $hasPriorityColumn = $this->db->columnExists('projects', 'priority');

        if ($hasPmColumn && !$this->isPrivileged($user)) {
            $conditions[] = 'p.pm_id = :pmId';
            $params[':pmId'] = $user['id'];
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
            'c.name AS client',
        ];

        $joins = [
            'JOIN clients c ON c.id = p.client_id',
        ];

        if ($hasPriorityColumn) {
            $select[] = 'p.priority';
            $select[] = 'pr.label AS priority_label';
            $joins[] = 'LEFT JOIN priorities pr ON pr.code = p.priority';
        } else {
            $select[] = 'NULL AS priority';
            $select[] = 'NULL AS priority_label';
        }

        if ($hasStatusColumn) {
            $select[] = 'p.status';
            $select[] = 'st.label AS status_label';
            $joins[] = 'LEFT JOIN project_status st ON st.code = p.status';
        } else {
            $select[] = "'' AS status";
            $select[] = 'NULL AS status_label';
        }

        if ($hasHealthColumn) {
            $select[] = 'p.health';
            $select[] = 'h.label AS health_label';
            $joins[] = 'LEFT JOIN project_health h ON h.code = p.health';
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

        return $this->db->fetchAll($sql, $params);
    }

    public function portfolioKpis(array $user): array
    {
        $conditions = [];
        $params = [];
        $hasPmColumn = $this->db->columnExists('projects', 'pm_id');
        $hasHealthColumn = $this->db->columnExists('projects', 'health');

        if ($hasPmColumn && !$this->isPrivileged($user)) {
            $conditions[] = 'p.pm_id = :pmId';
            $params[':pmId'] = $user['id'];
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $totals = $this->db->fetchOne(
            'SELECT COUNT(*) AS total, SUM(p.planned_hours) AS planned_hours, SUM(p.actual_hours) AS actual_hours, AVG(p.progress) AS avg_progress
             FROM projects p JOIN clients c ON c.id = p.client_id ' . $whereClause,
            $params
        );
        $atRisk = $hasHealthColumn
            ? $this->db->fetchOne(
                "SELECT COUNT(*) AS total FROM projects p JOIN clients c ON c.id = p.client_id $whereClause AND p.health IN ('at_risk','critical','red','yellow')",
                $params
            )
            : ['total' => 0];

        return [
            'total_projects' => (int) ($totals['total'] ?? 0),
            'avg_progress' => round((float) ($totals['avg_progress'] ?? 0), 1),
            'planned_hours' => (int) ($totals['planned_hours'] ?? 0),
            'actual_hours' => (int) ($totals['actual_hours'] ?? 0),
            'at_risk' => (int) ($atRisk['total'] ?? 0),
        ];
    }

    public function portfolio(array $user): array
    {
        [$conditions, $params] = $this->visibilityConditions($user, 'c', 'p');
        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $clients = $this->db->fetchAll(
            'SELECT DISTINCT c.id, c.name, c.pm_id
             FROM clients c
             JOIN projects p ON p.client_id = c.id
             ' . $whereClause . '
             ORDER BY c.name ASC',
            $params
        );

        $portfolio = [];
        foreach ($clients as $client) {
            $projects = $this->projectsForClient((int) $client['id'], $user);
            $assignments = $this->assignmentsForClient((int) $client['id'], $user);
            $byProject = [];
            foreach ($assignments as $assignment) {
                $byProject[$assignment['project_id']][] = $assignment;
            }
            $portfolio[] = [
                'id' => (int) $client['id'],
                'name' => $client['name'],
                'projects' => $projects,
                'kpis' => $this->clientKpis($projects, $assignments),
                'assignments' => $byProject,
            ];
        }

        return $portfolio;
    }

    public function projectsForClient(int $clientId, array $user): array
    {
        [$conditions, $params] = $this->visibilityConditions($user, 'c', 'p');
        $conditions[] = 'c.id = :clientId';
        $params[':clientId'] = $clientId;
        $hasStatusColumn = $this->db->columnExists('projects', 'status');
        $hasHealthColumn = $this->db->columnExists('projects', 'health');
        $hasPriorityColumn = $this->db->columnExists('projects', 'priority');

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        $select = [
            'p.id',
            'p.name',
            'p.progress',
            'p.project_type',
            'p.pm_id',
            'u.name AS pm_name',
            'p.actual_hours',
            'p.planned_hours',
        ];

        $joins = [
            'JOIN clients c ON c.id = p.client_id',
            'LEFT JOIN users u ON u.id = p.pm_id',
        ];

        if ($hasPriorityColumn) {
            $select[] = 'p.priority';
            $select[] = 'pr.label AS priority_label';
            $joins[] = 'LEFT JOIN priorities pr ON pr.code = p.priority';
        } else {
            $select[] = 'NULL AS priority';
            $select[] = 'NULL AS priority_label';
        }

        if ($hasStatusColumn) {
            $select[] = 'p.status';
            $select[] = 'st.label AS status_label';
            $joins[] = 'LEFT JOIN project_status st ON st.code = p.status';
        } else {
            $select[] = "'' AS status";
            $select[] = 'NULL AS status_label';
        }

        if ($hasHealthColumn) {
            $select[] = 'p.health';
            $select[] = 'h.label AS health_label';
            $joins[] = 'LEFT JOIN project_health h ON h.code = p.health';
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

        return $this->db->fetchAll($sql, $params);
    }

    public function assignmentsForClient(int $clientId, array $user): array
    {
        [$conditions, $params] = $this->visibilityConditions($user, 'c', 'p');
        $conditions[] = 'c.id = :clientId';
        $params[':clientId'] = $clientId;
        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        return $this->db->fetchAll(
            'SELECT a.*, t.name AS talent_name, t.weekly_capacity, p.id AS project_id
             FROM project_talent_assignments a
             JOIN projects p ON p.id = a.project_id
             JOIN clients c ON c.id = p.client_id
             JOIN talents t ON t.id = a.talent_id
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

    private function clientKpis(array $projects, array $assignments): array
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

    private function isPrivileged(array $user): bool
    {
        return in_array($user['role'] ?? '', self::ADMIN_ROLES, true);
    }
}
