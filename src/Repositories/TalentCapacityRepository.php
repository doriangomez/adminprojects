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
                'analytics' => $this->emptyAnalytics(),
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
            'analytics' => $this->buildAnalytics($talents),
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
        try {
            $calendarRepo = new WorkCalendarRepository($this->db);
            return $calendarRepo->countBusinessDays($start, $end);
        } catch (\Throwable $e) {
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

    private function buildAnalytics(array $talents): array
    {
        if ($talents === []) {
            return $this->emptyAnalytics();
        }

        $teamHours = 0.0;
        $teamCapacity = 0.0;
        $weekBuckets = [];
        $talentLoad = [];
        $availableTalents = [];

        foreach ($talents as $talent) {
            $name = (string) ($talent['name'] ?? '');
            $daily = is_array($talent['daily'] ?? null) ? $talent['daily'] : [];
            $weekly = is_array($talent['weekly'] ?? null) ? $talent['weekly'] : [];

            $hours = 0.0;
            $capacity = 0.0;
            foreach ($daily as $day) {
                $hours += (float) ($day['hours'] ?? 0);
                $capacity += (float) ($day['capacity'] ?? 0);
            }

            $utilization = $capacity > 0 ? (($hours / $capacity) * 100) : 0.0;
            $freeHours = max(0.0, $capacity - $hours);

            $teamHours += $hours;
            $teamCapacity += $capacity;

            $talentLoad[] = [
                'name' => $name,
                'utilization' => round($utilization, 1),
                'hours' => round($hours, 1),
                'capacity' => round($capacity, 1),
                'free_hours' => round($freeHours, 1),
            ];

            if ($freeHours > 0.0) {
                $availableTalents[] = [
                    'name' => $name,
                    'free_hours' => round($freeHours, 1),
                    'utilization' => round($utilization, 1),
                ];
            }

            foreach ($weekly as $weekKey => $bucket) {
                $weekBuckets[$weekKey]['hours'] = ($weekBuckets[$weekKey]['hours'] ?? 0.0) + (float) ($bucket['hours'] ?? 0);
                $weekBuckets[$weekKey]['capacity'] = ($weekBuckets[$weekKey]['capacity'] ?? 0.0) + (float) ($bucket['capacity'] ?? 0);
            }
        }

        usort(
            $talentLoad,
            static fn (array $a, array $b): int => ($b['utilization'] <=> $a['utilization']) ?: strcmp($a['name'], $b['name'])
        );
        usort(
            $availableTalents,
            static fn (array $a, array $b): int => ($b['free_hours'] <=> $a['free_hours']) ?: strcmp($a['name'], $b['name'])
        );

        $peakWeeks = [];
        foreach ($weekBuckets as $weekKey => $bucket) {
            $hours = (float) ($bucket['hours'] ?? 0);
            $capacity = (float) ($bucket['capacity'] ?? 0);
            $utilization = $capacity > 0 ? (($hours / $capacity) * 100) : 0.0;
            $peakWeeks[] = [
                'week' => (string) $weekKey,
                'label' => $this->formatWeekLabel((string) $weekKey),
                'utilization' => round($utilization, 1),
                'hours' => round($hours, 1),
                'capacity' => round($capacity, 1),
            ];
        }
        usort(
            $peakWeeks,
            static fn (array $a, array $b): int => ($b['utilization'] <=> $a['utilization']) ?: strcmp($a['week'], $b['week'])
        );

        $teamUtilization = $teamCapacity > 0 ? (($teamHours / $teamCapacity) * 100) : 0.0;
        $freeCapacity = max(0.0, $teamCapacity - $teamHours);
        $freeCapacityPct = $teamCapacity > 0 ? (($freeCapacity / $teamCapacity) * 100) : 0.0;

        $topTalents = array_slice($talentLoad, 0, 5);
        $topAvailableTalents = array_slice($availableTalents, 0, 5);
        $peakWeeks = array_slice($peakWeeks, 0, 5);

        return [
            'team_utilization' => [
                'utilization' => round($teamUtilization, 1),
                'hours' => round($teamHours, 1),
                'capacity' => round($teamCapacity, 1),
                'interpretation' => $this->interpretTeamUtilization($teamUtilization),
            ],
            'peak_weeks' => [
                'items' => $peakWeeks,
                'interpretation' => $this->interpretPeakWeeks($peakWeeks),
            ],
            'top_utilized_talents' => [
                'items' => $topTalents,
                'interpretation' => $this->interpretTopUtilizedTalents($topTalents),
            ],
            'available_talents' => [
                'items' => $topAvailableTalents,
                'interpretation' => $this->interpretAvailableTalents($topAvailableTalents),
            ],
            'free_capacity' => [
                'hours' => round($freeCapacity, 1),
                'percentage' => round($freeCapacityPct, 1),
                'interpretation' => $this->interpretFreeCapacity($freeCapacityPct, $freeCapacity),
            ],
        ];
    }

    private function emptyAnalytics(): array
    {
        return [
            'team_utilization' => [
                'utilization' => 0.0,
                'hours' => 0.0,
                'capacity' => 0.0,
                'interpretation' => 'No hay datos suficientes para estimar la utilización del equipo en el periodo seleccionado.',
            ],
            'peak_weeks' => [
                'items' => [],
                'interpretation' => 'No se detectaron semanas con carga registrada en el rango actual.',
            ],
            'top_utilized_talents' => [
                'items' => [],
                'interpretation' => 'No hay talentos con utilización registrada para priorizar acciones.',
            ],
            'available_talents' => [
                'items' => [],
                'interpretation' => 'No hay talentos con disponibilidad registrada para asignación.',
            ],
            'free_capacity' => [
                'hours' => 0.0,
                'percentage' => 0.0,
                'interpretation' => 'No se pudo calcular capacidad libre del equipo con la información disponible.',
            ],
        ];
    }

    private function formatWeekLabel(string $weekKey): string
    {
        if (preg_match('/^(\d{4})-W(\d{2})$/', $weekKey, $matches) !== 1) {
            return $weekKey;
        }

        return 'Sem ' . $matches[2] . ' (' . $matches[1] . ')';
    }

    private function interpretTeamUtilization(float $utilization): string
    {
        if ($utilization > 100) {
            return 'El equipo está sobreutilizado y supera su capacidad efectiva; conviene redistribuir carga de forma inmediata.';
        }
        if ($utilization >= 90) {
            return 'La utilización del equipo está en zona alta; hay poco margen para nuevas asignaciones sin afectar el balance.';
        }
        if ($utilization >= 70) {
            return 'El equipo mantiene una utilización saludable, con buena ocupación y margen controlado para absorber trabajo.';
        }
        if ($utilization >= 50) {
            return 'La utilización del equipo es moderada; existe capacidad relevante para tomar nuevas asignaciones.';
        }

        return 'La utilización del equipo es baja; hay una oportunidad clara para incrementar asignaciones y mejorar eficiencia.';
    }

    private function interpretPeakWeeks(array $peakWeeks): string
    {
        if ($peakWeeks === []) {
            return 'No se identificaron semanas de mayor carga en el periodo seleccionado.';
        }

        $top = $peakWeeks[0];
        $topUtilization = (float) ($top['utilization'] ?? 0);
        $label = (string) ($top['label'] ?? $top['week'] ?? 'semana sin etiqueta');

        if ($topUtilization >= 100) {
            return 'La ' . $label . ' concentra el mayor pico de carga y supera la capacidad del equipo; se recomienda alivianar esa semana.';
        }
        if ($topUtilization >= 90) {
            return 'La ' . $label . ' marca la mayor presión operativa, con carga cercana al límite de capacidad.';
        }

        return 'La ' . $label . ' es la semana más cargada, pero se mantiene dentro de márgenes manejables para el equipo.';
    }

    private function interpretTopUtilizedTalents(array $topTalents): string
    {
        if ($topTalents === []) {
            return 'No hay datos para identificar talentos con mayor utilización.';
        }

        $top = $topTalents[0];
        $name = (string) ($top['name'] ?? 'Talento');
        $utilization = (float) ($top['utilization'] ?? 0);

        if ($utilization > 100) {
            return $name . ' presenta sobreutilización; conviene reasignar parte de su carga para reducir riesgo operativo.';
        }
        if ($utilization >= 90) {
            return $name . ' lidera la utilización y opera en zona crítica; revisar prioridad de tareas y distribución de horas.';
        }

        return $name . ' concentra la mayor utilización actual, aunque en niveles todavía controlables para ejecución sostenida.';
    }

    private function interpretAvailableTalents(array $availableTalents): string
    {
        if ($availableTalents === []) {
            return 'No se observan talentos disponibles para nuevas asignaciones en el periodo seleccionado.';
        }

        $top = $availableTalents[0];
        $name = (string) ($top['name'] ?? 'Talento');
        $freeHours = (float) ($top['free_hours'] ?? 0);

        return $name . ' es quien más capacidad libre presenta (' . number_format($freeHours, 1) . 'h), ideal para absorber nuevas iniciativas.';
    }

    private function interpretFreeCapacity(float $freeCapacityPct, float $freeCapacityHours): string
    {
        if ($freeCapacityHours <= 0) {
            return 'La capacidad libre del equipo es nula; cualquier nueva demanda requiere mover prioridades o ampliar capacidad.';
        }
        if ($freeCapacityPct < 10) {
            return 'La capacidad libre es muy limitada (<10%); se recomienda asignar nuevas tareas con alta selectividad.';
        }
        if ($freeCapacityPct <= 25) {
            return 'Existe una capacidad libre moderada que permite absorber trabajo incremental con seguimiento cercano.';
        }

        return 'El equipo mantiene una capacidad libre amplia; hay espacio para acelerar nuevas asignaciones sin saturación inmediata.';
    }
}
