<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;
use DateTimeImmutable;

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
                'insights' => $this->emptyInsights(),
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

        $summary = $this->buildSummary($talents);

        return [
            'range' => $range,
            'talents' => $talents,
            'heatmap' => $heatmap,
            'summary' => $summary,
            'insights' => $this->buildInsights($talents, $summary, $heatmap),
            'filter_options' => $this->filterOptions($visibilitySql, $visibilityParams),
        ];
    }

    private function buildInsights(array $talents, array $summary, array $heatmap): array
    {
        $avgUtilization = (float) ($summary['avg_team_utilization'] ?? 0);
        $idleCapacity = (float) ($summary['idle_capacity'] ?? 0);
        $riskTalents = (int) ($summary['risk_talents'] ?? 0);
        $overassignedHours = (float) ($summary['overassigned_hours'] ?? 0);

        $weeklyAggregate = [];
        foreach ($heatmap as $talentId => $weeks) {
            foreach ($weeks as $weekKey => $bucket) {
                $util = (float) ($bucket['utilization'] ?? 0);
                $weeklyAggregate[$weekKey]['sum'] = ($weeklyAggregate[$weekKey]['sum'] ?? 0) + $util;
                $weeklyAggregate[$weekKey]['count'] = ($weeklyAggregate[$weekKey]['count'] ?? 0) + 1;
            }
        }

        $peakWeeks = [];
        foreach ($weeklyAggregate as $weekKey => $data) {
            $count = (int) ($data['count'] ?? 0);
            $avg = $count > 0 ? ((float) ($data['sum'] ?? 0) / $count) : 0;
            $peakWeeks[] = ['week' => $weekKey, 'utilization' => round($avg, 1)];
        }
        usort($peakWeeks, static fn (array $a, array $b): int => $b['utilization'] <=> $a['utilization']);
        $peakWeeks = array_slice($peakWeeks, 0, 5);

        $ranking = [];
        $available = [];
        foreach ($talents as $talent) {
            $name = (string) ($talent['name'] ?? '');
            $monthly = $talent['monthly'] ?? [];
            $latest = !empty($monthly) ? end($monthly) : ['hours' => 0, 'capacity' => 0, 'utilization' => 0];
            $utilization = (float) ($latest['utilization'] ?? 0);
            $hours = (float) ($latest['hours'] ?? 0);
            $capacity = (float) ($latest['capacity'] ?? 0);
            $free = max(0.0, $capacity - $hours);

            $ranking[] = ['name' => $name, 'utilization' => $utilization, 'hours' => $hours, 'capacity' => $capacity];
            if ($free > 0) {
                $available[] = ['name' => $name, 'available' => $free, 'utilization' => $utilization];
            }
        }
        usort($ranking, static fn (array $a, array $b): int => $b['utilization'] <=> $a['utilization']);
        usort($available, static fn (array $a, array $b): int => $b['available'] <=> $a['available']);

        $topUtilized = array_slice($ranking, 0, 5);
        $availableForAssignment = array_slice($available, 0, 10);

        return [
            'team_utilization' => [
                'value' => $avgUtilization,
                'interpretation' => $this->interpretTeamUtilization($avgUtilization, $riskTalents, $overassignedHours),
            ],
            'peak_weeks' => [
                'weeks' => $peakWeeks,
                'interpretation' => $this->interpretPeakWeeks($peakWeeks),
            ],
            'top_utilized_talents' => [
                'talents' => $topUtilized,
                'interpretation' => $this->interpretTopUtilized($topUtilized),
            ],
            'available_talents' => [
                'talents' => $availableForAssignment,
                'count' => count($available),
                'interpretation' => $this->interpretAvailableTalents($availableForAssignment, count($available)),
            ],
            'free_capacity' => [
                'total_hours' => $idleCapacity,
                'interpretation' => $this->interpretFreeCapacity($idleCapacity, count($talents)),
            ],
        ];
    }

    private function interpretTeamUtilization(float $avg, int $riskCount, float $overassigned): string
    {
        if ($avg <= 0) {
            return 'El equipo no tiene carga asignada en el periodo. Considera distribuir trabajo o revisar las asignaciones.';
        }
        if ($avg > 100 || $overassigned > 0) {
            return sprintf(
                'El equipo está sobrecargado (%.1f%% promedio). Hay %.1f horas sobreasignadas. Prioriza redistribución o ajuste de alcance.',
                $avg,
                $overassigned
            );
        }
        if ($avg >= 90) {
            return sprintf(
                'Utilización muy alta (%.1f%%). %d talento(s) en zona de riesgo. Evita nuevas asignaciones sin liberar carga.',
                $avg,
                $riskCount
            );
        }
        if ($avg >= 70) {
            return sprintf(
                'Utilización saludable (%.1f%%). El equipo está bien balanceado. Hay margen para asignaciones puntuales.',
                $avg
            );
        }
        if ($avg >= 50) {
            return sprintf(
                'Utilización moderada (%.1f%%). Hay capacidad disponible para absorber nuevos proyectos o tareas.',
                $avg
            );
        }

        return sprintf(
            'Utilización baja (%.1f%%). Capacidad ociosa significativa. Ideal para onboarding o proyectos nuevos.',
            $avg
        );
    }

    private function interpretPeakWeeks(array $weeks): string
    {
        if (empty($weeks)) {
            return 'No hay datos semanales en el rango seleccionado.';
        }
        $top = $weeks[0];
        $topUtil = (float) ($top['utilization'] ?? 0);
        $topWeek = (string) ($top['week'] ?? '');
        if ($topUtil >= 90) {
            return sprintf(
                'La semana %s presenta la mayor carga (%.1f%%). Planifica entregas críticas y evita picos adicionales en semanas cercanas.',
                $topWeek,
                $topUtil
            );
        }
        if ($topUtil >= 70) {
            return sprintf(
                'Las semanas con mayor carga son: %s. Niveles manejables; monitorea para evitar sobrecargas futuras.',
                implode(', ', array_map(static fn (array $w): string => $w['week'] . ' (' . $w['utilization'] . '%)', array_slice($weeks, 0, 3)))
            );
        }

        return sprintf(
            'La carga se distribuye de forma equilibrada. Semana más cargada: %s (%.1f%%).',
            $topWeek,
            $topUtil
        );
    }

    private function interpretTopUtilized(array $talents): string
    {
        if (empty($talents)) {
            return 'No hay talentos con carga asignada en el periodo.';
        }
        $top = $talents[0];
        $name = (string) ($top['name'] ?? '');
        $util = (float) ($top['utilization'] ?? 0);
        if ($util > 100) {
            return sprintf(
                '%s está sobrecargado (%.1f%%). Prioriza redistribución inmediata para evitar burnout.',
                $name,
                $util
            );
        }
        if ($util >= 90) {
            $names = array_map(static fn (array $t): string => $t['name'], array_slice($talents, 0, 3));
            return sprintf(
                'Los talentos con mayor carga son: %s. Considera rebalancear antes de nuevas asignaciones.',
                implode(', ', $names)
            );
        }

        return sprintf(
            '%s lidera la utilización (%.1f%%). La distribución actual es manejable.',
            $name,
            $util
        );
    }

    private function interpretAvailableTalents(array $talents, int $totalAvailable): string
    {
        if ($totalAvailable === 0) {
            return 'No hay talentos con capacidad libre. Todas las asignaciones están al límite. Considera incorporar recursos o replanificar.';
        }
        if (empty($talents)) {
            return 'No hay talentos disponibles en el corte actual.';
        }
        $top = $talents[0];
        $name = (string) ($top['name'] ?? '');
        $hours = (float) ($top['available'] ?? 0);
        if ($totalAvailable >= 5) {
            return sprintf(
                'Hay %d talentos disponibles. %s tiene mayor capacidad libre (%.1f h). Ideal para asignar nuevos proyectos.',
                $totalAvailable,
                $name,
                $hours
            );
        }

        return sprintf(
            '%d talento(s) con capacidad libre. %s destaca con %.1f h disponibles para asignación.',
            $totalAvailable,
            $name,
            $hours
        );
    }

    private function interpretFreeCapacity(float $hours, int $teamSize): string
    {
        if ($hours <= 0) {
            return 'No hay capacidad libre en el equipo. Todas las horas están asignadas o sobreasignadas. Revisa prioridades antes de comprometer más trabajo.';
        }
        $avgPerPerson = $teamSize > 0 ? round($hours / $teamSize, 1) : 0;

        return sprintf(
            'El equipo tiene %.1f horas libres (aprox. %.1f h por persona). Esta capacidad puede usarse para nuevos proyectos o contingencia.',
            $hours,
            $avgPerPerson
        );
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
            if ($utilization > 90) {
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

    private function emptyInsights(): array
    {
        return [
            'team_utilization' => [
                'value' => 0,
                'interpretation' => 'No hay talentos en el equipo para analizar. Añade talentos o ajusta los filtros.',
            ],
            'peak_weeks' => [
                'weeks' => [],
                'interpretation' => 'No hay datos semanales disponibles.',
            ],
            'top_utilized_talents' => [
                'talents' => [],
                'interpretation' => 'No hay talentos con carga asignada.',
            ],
            'available_talents' => [
                'talents' => [],
                'count' => 0,
                'interpretation' => 'No hay talentos disponibles para asignación.',
            ],
            'free_capacity' => [
                'total_hours' => 0,
                'interpretation' => 'No hay capacidad libre registrada.',
            ],
        ];
    }
}
