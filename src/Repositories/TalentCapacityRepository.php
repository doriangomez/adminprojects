<?php

declare(strict_types=1);

class TalentCapacityRepository
{
    private const ADMIN_ROLES = ['Administrador', 'PMO'];

    public function __construct(private Database $db)
    {
    }

    public function dashboard(array $filters, array $user): array
    {
        $range = $this->resolveRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);
        $roleFilter = trim((string) ($filters['role'] ?? ''));
        $projectId = (int) ($filters['project_id'] ?? 0);
        $areaCode = trim((string) ($filters['area'] ?? ''));

        [$visibilitySql, $visibilityParams] = $this->projectVisibility($user);

        $talents = $this->db->fetchAll(
            'SELECT t.id, t.name, t.role, t.capacidad_horaria, t.weekly_capacity, t.availability
             FROM talents t
             WHERE 1=1 ' . ($roleFilter !== '' ? ' AND t.role = :role' : '') . '
             ORDER BY t.name ASC',
            $roleFilter !== '' ? [':role' => $roleFilter] : []
        );

        if (empty($talents)) {
            return [
                'range' => $range,
                'talents' => [],
                'heatmap' => [],
                'summary' => $this->emptySummary(),
                'filter_options' => $this->filterOptions($visibilitySql, $visibilityParams),
            ];
        }

        $talentIds = array_map(static fn (array $talent): int => (int) $talent['id'], $talents);
        $talentIndex = array_fill_keys($talentIds, true);

        $params = array_merge($visibilityParams, [':from' => $range['start'], ':to' => $range['end']]);
        $projectFilterSql = '';
        if ($projectId > 0) {
            $projectFilterSql .= ' AND p.id = :project_id';
            $params[':project_id'] = $projectId;
        }
        if ($areaCode !== '') {
            $projectFilterSql .= ' AND COALESCE(c.area_code, "") = :area_code';
            $params[':area_code'] = $areaCode;
        }

        $assignmentRows = $this->db->fetchAll(
            'SELECT a.talent_id, a.project_id, a.start_date, a.end_date, a.planned_hours, a.weekly_hours,
                    p.name AS project_name, COALESCE(c.area_code, "") AS area_code
             FROM project_talent_assignments a
             JOIN projects p ON p.id = a.project_id
             LEFT JOIN clients c ON c.id = p.client_id
             WHERE a.talent_id IS NOT NULL
               AND a.talent_id IN (' . implode(',', array_map('intval', $talentIds)) . ')
               AND (a.assignment_status = "active" OR (a.assignment_status IS NULL AND a.active = 1))
               AND (a.start_date IS NULL OR a.start_date <= :to)
               AND (a.end_date IS NULL OR a.end_date >= :from)
               ' . $visibilitySql . $projectFilterSql,
            $params
        );

        $timesheetRows = $this->db->fetchAll(
            'SELECT ts.talent_id, ts.date, SUM(ts.hours) AS hours
             FROM timesheets ts
             JOIN projects p ON p.id = COALESCE(ts.project_id, (SELECT t.project_id FROM tasks t WHERE t.id = ts.task_id LIMIT 1))
             LEFT JOIN clients c ON c.id = p.client_id
             WHERE ts.talent_id IN (' . implode(',', array_map('intval', $talentIds)) . ')
               AND ts.date BETWEEN :from AND :to
               ' . $visibilitySql . $projectFilterSql . '
             GROUP BY ts.talent_id, ts.date',
            $params
        );

        $dailyPlanned = [];
        foreach ($assignmentRows as $row) {
            $talentId = (int) ($row['talent_id'] ?? 0);
            if (!isset($talentIndex[$talentId])) {
                continue;
            }
            $start = max($range['start'], (string) ($row['start_date'] ?: $range['start']));
            $end = min($range['end'], (string) ($row['end_date'] ?: $range['end']));
            if ($start > $end) {
                continue;
            }
            $workingDays = $this->businessDays($start, $end);
            if ($workingDays <= 0) {
                continue;
            }

            $weeklyHours = (float) ($row['weekly_hours'] ?? 0);
            $plannedHours = (float) ($row['planned_hours'] ?? 0);
            $hoursPerDay = $weeklyHours > 0 ? ($weeklyHours / 5) : ($plannedHours > 0 ? ($plannedHours / $workingDays) : 0.0);

            $cursor = new DateTimeImmutable($start);
            $limit = new DateTimeImmutable($end);
            while ($cursor <= $limit) {
                $day = $cursor->format('Y-m-d');
                if ((int) $cursor->format('N') <= 5) {
                    $dailyPlanned[$talentId][$day] = ($dailyPlanned[$talentId][$day] ?? 0.0) + $hoursPerDay;
                }
                $cursor = $cursor->modify('+1 day');
            }
        }

        $dailyLogged = [];
        foreach ($timesheetRows as $row) {
            $talentId = (int) ($row['talent_id'] ?? 0);
            $day = (string) ($row['date'] ?? '');
            if (!isset($talentIndex[$talentId]) || $day === '') {
                continue;
            }
            $dailyLogged[$talentId][$day] = (float) ($row['hours'] ?? 0);
        }

        $dailyUtilization = [];
        $weeklyUtilization = [];
        $monthlyUtilization = [];
        $heatmap = [];

        foreach ($talents as &$talent) {
            $talentId = (int) $talent['id'];
            $weeklyCapacity = $this->weeklyCapacity($talent);
            $dailyCapacity = $weeklyCapacity / 5;

            $dailyUtilization[$talentId] = [];
            $weeklyBuckets = [];
            $monthlyBuckets = [];

            $cursor = new DateTimeImmutable($range['start']);
            $limit = new DateTimeImmutable($range['end']);
            while ($cursor <= $limit) {
                if ((int) $cursor->format('N') > 5) {
                    $cursor = $cursor->modify('+1 day');
                    continue;
                }
                $day = $cursor->format('Y-m-d');
                $hours = $dailyLogged[$talentId][$day] ?? ($dailyPlanned[$talentId][$day] ?? 0.0);
                $percentage = $dailyCapacity > 0 ? ($hours / $dailyCapacity) * 100 : 0;
                $dailyUtilization[$talentId][$day] = [
                    'hours' => round($hours, 2),
                    'capacity' => round($dailyCapacity, 2),
                    'utilization' => round($percentage, 1),
                    'status' => $this->statusForUtilization($percentage),
                ];

                $weekKey = $cursor->format('o-\\WW');
                $monthlyKey = $cursor->format('Y-m');
                $weeklyBuckets[$weekKey]['hours'] = ($weeklyBuckets[$weekKey]['hours'] ?? 0) + $hours;
                $weeklyBuckets[$weekKey]['capacity'] = ($weeklyBuckets[$weekKey]['capacity'] ?? 0) + $dailyCapacity;

                $monthlyBuckets[$monthlyKey]['hours'] = ($monthlyBuckets[$monthlyKey]['hours'] ?? 0) + $hours;
                $monthlyBuckets[$monthlyKey]['capacity'] = ($monthlyBuckets[$monthlyKey]['capacity'] ?? 0) + $dailyCapacity;

                $cursor = $cursor->modify('+1 day');
            }

            foreach ($weeklyBuckets as $key => $bucket) {
                $percentage = ($bucket['capacity'] ?? 0) > 0 ? (($bucket['hours'] / $bucket['capacity']) * 100) : 0;
                $weeklyUtilization[$talentId][$key] = [
                    'hours' => round((float) $bucket['hours'], 1),
                    'capacity' => round((float) $bucket['capacity'], 1),
                    'utilization' => round($percentage, 1),
                    'status' => $this->statusForUtilization($percentage),
                ];
                $heatmap[$talentId][$key] = $weeklyUtilization[$talentId][$key];
            }

            foreach ($monthlyBuckets as $key => $bucket) {
                $percentage = ($bucket['capacity'] ?? 0) > 0 ? (($bucket['hours'] / $bucket['capacity']) * 100) : 0;
                $monthlyUtilization[$talentId][$key] = [
                    'hours' => round((float) $bucket['hours'], 1),
                    'capacity' => round((float) $bucket['capacity'], 1),
                    'utilization' => round($percentage, 1),
                    'status' => $this->statusForUtilization($percentage),
                ];
            }

            $talent['weekly_capacity_effective'] = round($weeklyCapacity, 1);
            $talent['daily'] = $dailyUtilization[$talentId];
            $talent['weekly'] = $weeklyUtilization[$talentId] ?? [];
            $talent['monthly'] = $monthlyUtilization[$talentId] ?? [];
        }
        unset($talent);

        return [
            'range' => $range,
            'talents' => $talents,
            'heatmap' => $heatmap,
            'summary' => $this->buildSummary($talents),
            'filter_options' => $this->filterOptions($visibilitySql, $visibilityParams),
        ];
    }

    private function buildSummary(array $talents): array
    {
        $teamUtilization = 0.0;
        $teamCount = count($talents);
        $overassignedHours = 0.0;
        $riskTalents = 0;
        $idleCapacity = 0.0;

        foreach ($talents as $talent) {
            $monthly = $talent['monthly'];
            $latest = end($monthly) ?: ['utilization' => 0, 'hours' => 0, 'capacity' => 0];
            $utilization = (float) ($latest['utilization'] ?? 0);
            $hours = (float) ($latest['hours'] ?? 0);
            $capacity = (float) ($latest['capacity'] ?? 0);

            $teamUtilization += $utilization;
            if ($utilization >= 90 && $utilization <= 100) {
                $riskTalents++;
            }
            if ($hours > $capacity) {
                $overassignedHours += ($hours - $capacity);
            }
            if ($capacity > $hours) {
                $idleCapacity += ($capacity - $hours);
            }
        }

        return [
            'avg_team_utilization' => $teamCount > 0 ? round($teamUtilization / $teamCount, 1) : 0,
            'overassigned_hours' => round($overassignedHours, 1),
            'risk_talents' => $riskTalents,
            'idle_capacity' => round($idleCapacity, 1),
        ];
    }

    private function filterOptions(string $visibilitySql, array $visibilityParams): array
    {
        $projects = $this->db->fetchAll(
            'SELECT p.id, p.name
             FROM projects p
             LEFT JOIN clients c ON c.id = p.client_id
             WHERE 1=1 ' . $visibilitySql . '
             ORDER BY p.name ASC',
            $visibilityParams
        );

        $areas = $this->db->fetchAll(
            'SELECT DISTINCT COALESCE(c.area_code, "") AS code
             FROM projects p
             LEFT JOIN clients c ON c.id = p.client_id
             WHERE COALESCE(c.area_code, "") <> "" ' . $visibilitySql . '
             ORDER BY code ASC',
            $visibilityParams
        );

        $roles = $this->db->fetchAll('SELECT DISTINCT role FROM talents WHERE role IS NOT NULL AND role <> "" ORDER BY role ASC');

        return [
            'projects' => $projects,
            'areas' => $areas,
            'roles' => $roles,
        ];
    }

    private function resolveRange(?string $from, ?string $to): array
    {
        $start = $from ?: date('Y-m-01');
        $end = $to ?: date('Y-m-t');
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return ['start' => $start, 'end' => $end];
    }

    private function businessDays(string $start, string $end): int
    {
        $count = 0;
        $cursor = new DateTimeImmutable($start);
        $limit = new DateTimeImmutable($end);
        while ($cursor <= $limit) {
            if ((int) $cursor->format('N') <= 5) {
                $count++;
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $count;
    }

    private function weeklyCapacity(array $talent): float
    {
        $base = (float) ($talent['capacidad_horaria'] ?? 0);
        if ($base <= 0) {
            $base = (float) ($talent['weekly_capacity'] ?? 0);
        }
        if ($base <= 0) {
            $base = 40;
        }
        $availability = (float) ($talent['availability'] ?? 100);

        return $base * max(0, min(100, $availability)) / 100;
    }

    private function statusForUtilization(float $utilization): string
    {
        if ($utilization <= 0) {
            return 'none';
        }
        if ($utilization > 100) {
            return 'overload';
        }
        if ($utilization >= 90) {
            return 'risk';
        }
        if ($utilization >= 70) {
            return 'healthy';
        }
        if ($utilization < 60) {
            return 'under';
        }

        return 'balanced';
    }

    private function projectVisibility(array $user): array
    {
        if (in_array($user['role'] ?? '', self::ADMIN_ROLES, true)) {
            return ['', []];
        }

        if ($this->db->columnExists('projects', 'pm_id')) {
            return [' AND p.pm_id = :pm_id', [':pm_id' => (int) ($user['id'] ?? 0)]];
        }

        return ['', []];
    }

    private function emptySummary(): array
    {
        return [
            'avg_team_utilization' => 0,
            'overassigned_hours' => 0,
            'risk_talents' => 0,
            'idle_capacity' => 0,
        ];
    }
}
