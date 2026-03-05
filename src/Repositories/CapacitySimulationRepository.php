<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;
use DateTimeImmutable;

class CapacitySimulationRepository
{
    public function __construct(private Database $db)
    {
    }

    public function currentTeamSnapshot(string $periodFrom, string $periodTo): array
    {
        $startDate = $periodFrom . '-01';
        $endDateObj = new DateTimeImmutable($periodTo . '-01');
        $endDate = $endDateObj->format('Y-m-t');

        $talents = $this->db->fetchAll(
            'SELECT id, name, role, capacidad_horaria, weekly_capacity, availability
             FROM talents
             WHERE active = 1
             ORDER BY name ASC'
        );

        if (empty($talents)) {
            return [];
        }

        $talentIds = array_map(static fn(array $t): int => (int) $t['id'], $talents);
        $workingDays = $this->businessDays($startDate, $endDate);

        $assignmentRows = $this->db->fetchAll(
            'SELECT a.talent_id, a.start_date, a.end_date, a.planned_hours, a.weekly_hours
             FROM project_talent_assignments a
             WHERE a.talent_id IN (' . implode(',', array_map('intval', $talentIds)) . ')
               AND (a.assignment_status = "active" OR (a.assignment_status IS NULL AND a.active = 1))
               AND (a.start_date IS NULL OR a.start_date <= :to)
               AND (a.end_date IS NULL OR a.end_date >= :from)',
            [':from' => $startDate, ':to' => $endDate]
        );

        $currentHours = [];
        foreach ($assignmentRows as $row) {
            $talentId = (int) $row['talent_id'];
            $start = max($startDate, (string) ($row['start_date'] ?: $startDate));
            $end = min($endDate, (string) ($row['end_date'] ?: $endDate));
            $overlapDays = $this->businessDays($start, $end);
            if ($overlapDays <= 0) {
                continue;
            }
            $weeklyHours = (float) ($row['weekly_hours'] ?? 0);
            $plannedHours = (float) ($row['planned_hours'] ?? 0);
            $hoursPerDay = $weeklyHours > 0
                ? ($weeklyHours / 5)
                : ($plannedHours > 0 ? ($plannedHours / $overlapDays) : 0.0);
            $currentHours[$talentId] = ($currentHours[$talentId] ?? 0.0) + ($hoursPerDay * $overlapDays);
        }

        $result = [];
        foreach ($talents as $talent) {
            $talentId = (int) $talent['id'];
            $weeklyCapEff = $this->weeklyCapacity($talent);
            $periodCapacity = $weeklyCapEff * ($workingDays / 5);
            $current = round($currentHours[$talentId] ?? 0.0, 1);
            $available = max(0.0, $periodCapacity - $current);
            $utilizationCurrent = $periodCapacity > 0 ? round(($current / $periodCapacity) * 100, 1) : 0.0;

            $result[] = [
                'id'                  => $talentId,
                'name'                => (string) ($talent['name'] ?? ''),
                'role'                => (string) ($talent['role'] ?? ''),
                'period_capacity'     => round($periodCapacity, 1),
                'current_hours'       => $current,
                'available_hours'     => round($available, 1),
                'utilization_current' => $utilizationCurrent,
                'status_current'      => $this->statusForUtilization($utilizationCurrent),
            ];
        }

        return $result;
    }

    public function simulate(array $params): array
    {
        $projectName    = trim((string) ($params['project_name'] ?? 'Nuevo proyecto'));
        $estimatedHours = max(1.0, (float) ($params['estimated_hours'] ?? 0));
        $periodFrom     = (string) ($params['period_from'] ?? date('Y-m'));
        $periodTo       = (string) ($params['period_to'] ?? $periodFrom);

        if ($periodFrom > $periodTo) {
            [$periodFrom, $periodTo] = [$periodTo, $periodFrom];
        }

        $startDate    = $periodFrom . '-01';
        $endDateObj   = new DateTimeImmutable($periodTo . '-01');
        $endDate      = $endDateObj->format('Y-m-t');
        $workingDays  = $this->businessDays($startDate, $endDate);

        $talents = $this->db->fetchAll(
            'SELECT id, name, role, capacidad_horaria, weekly_capacity, availability
             FROM talents
             WHERE active = 1
             ORDER BY name ASC'
        );

        if (empty($talents)) {
            return $this->emptyResult($projectName, $estimatedHours, $periodFrom, $periodTo);
        }

        $talentIds = array_map(static fn(array $t): int => (int) $t['id'], $talents);

        $assignmentRows = $this->db->fetchAll(
            'SELECT a.talent_id, a.start_date, a.end_date, a.planned_hours, a.weekly_hours
             FROM project_talent_assignments a
             WHERE a.talent_id IN (' . implode(',', array_map('intval', $talentIds)) . ')
               AND (a.assignment_status = "active" OR (a.assignment_status IS NULL AND a.active = 1))
               AND (a.start_date IS NULL OR a.start_date <= :to)
               AND (a.end_date IS NULL OR a.end_date >= :from)',
            [':from' => $startDate, ':to' => $endDate]
        );

        $currentHours = [];
        foreach ($assignmentRows as $row) {
            $talentId = (int) $row['talent_id'];
            $start = max($startDate, (string) ($row['start_date'] ?: $startDate));
            $end = min($endDate, (string) ($row['end_date'] ?: $endDate));
            $overlapDays = $this->businessDays($start, $end);
            if ($overlapDays <= 0) {
                continue;
            }
            $weeklyHours = (float) ($row['weekly_hours'] ?? 0);
            $plannedHours = (float) ($row['planned_hours'] ?? 0);
            $hoursPerDay = $weeklyHours > 0
                ? ($weeklyHours / 5)
                : ($plannedHours > 0 ? ($plannedHours / $overlapDays) : 0.0);
            $currentHours[$talentId] = ($currentHours[$talentId] ?? 0.0) + ($hoursPerDay * $overlapDays);
        }

        $talentData   = [];
        $totalAvailable = 0.0;

        foreach ($talents as $talent) {
            $talentId = (int) $talent['id'];
            $weeklyCapEff   = $this->weeklyCapacity($talent);
            $periodCapacity = $weeklyCapEff * ($workingDays / 5);
            $current        = round($currentHours[$talentId] ?? 0.0, 1);
            $available      = max(0.0, $periodCapacity - $current);
            $utilizationCurrent = $periodCapacity > 0 ? round(($current / $periodCapacity) * 100, 1) : 0.0;

            $talentData[$talentId] = [
                'id'                  => $talentId,
                'name'                => (string) ($talent['name'] ?? ''),
                'role'                => (string) ($talent['role'] ?? ''),
                'period_capacity'     => round($periodCapacity, 1),
                'current_hours'       => $current,
                'available_hours'     => round($available, 1),
                'simulated_hours'     => 0.0,
                'final_hours'         => $current,
                'utilization_current' => $utilizationCurrent,
                'utilization_final'   => $utilizationCurrent,
                'status_current'      => $this->statusForUtilization($utilizationCurrent),
                'status_final'        => $this->statusForUtilization($utilizationCurrent),
            ];

            $totalAvailable += $available;
        }

        // Distribute hours proportionally to each talent's available capacity
        $distributedTotal = 0.0;
        $talentDataList   = array_values($talentData);
        $talentCount      = count($talentDataList);

        foreach ($talentData as &$data) {
            if ($totalAvailable > 0) {
                $share    = $data['available_hours'] / $totalAvailable;
                $simHours = round($estimatedHours * $share, 1);
            } else {
                $simHours = $talentCount > 0 ? round($estimatedHours / $talentCount, 1) : 0.0;
            }

            $data['simulated_hours']   = $simHours;
            $data['final_hours']       = round($data['current_hours'] + $simHours, 1);
            $cap = $data['period_capacity'];
            $data['utilization_final'] = $cap > 0 ? round(($data['final_hours'] / $cap) * 100, 1) : 0.0;
            $data['status_final']      = $this->statusForUtilization($data['utilization_final']);
            $distributedTotal         += $simHours;
        }
        unset($data);

        $talentsArray       = array_values($talentData);
        $totalCapacity      = (float) array_sum(array_column($talentsArray, 'period_capacity'));
        $totalCurrentHours  = (float) array_sum(array_column($talentsArray, 'current_hours'));
        $totalFinalHours    = (float) array_sum(array_column($talentsArray, 'final_hours'));
        $totalAvailBefore   = max(0.0, $totalCapacity - $totalCurrentHours);
        $totalAvailAfter    = max(0.0, $totalCapacity - $totalFinalHours);
        $overloadedTalents  = array_filter($talentsArray, static fn(array $t): bool => $t['utilization_final'] > 90);

        $summary = [
            'total_capacity'     => round($totalCapacity, 1),
            'committed'          => round($totalCurrentHours, 1),
            'available_before'   => round($totalAvailBefore, 1),
            'remaining_after'    => round($totalAvailAfter, 1),
            'distributed_hours'  => round($distributedTotal, 1),
            'overloaded_count'   => count($overloadedTalents),
            'is_viable'          => count($overloadedTalents) === 0 && $estimatedHours <= $totalAvailBefore,
        ];

        $insights = $this->buildInsights($talentsArray, $summary, $projectName, $estimatedHours);

        return [
            'project_name'    => $projectName,
            'estimated_hours' => $estimatedHours,
            'period_from'     => $periodFrom,
            'period_to'       => $periodTo,
            'period_label'    => $this->formatPeriodLabel($periodFrom, $periodTo),
            'talents'         => $talentsArray,
            'summary'         => $summary,
            'insights'        => $insights,
        ];
    }

    private function buildInsights(array $talents, array $summary, string $projectName, float $estimatedHours): array
    {
        $insights = [];
        $overloadedCount  = (int) ($summary['overloaded_count'] ?? 0);
        $totalAvailBefore = (float) ($summary['available_before'] ?? 0);
        $remainingAfter   = (float) ($summary['remaining_after'] ?? 0);
        $isViable         = (bool) ($summary['is_viable'] ?? false);

        if ($overloadedCount === 0) {
            $insights[] = ['type' => 'success', 'text' => 'Ningún talento supera el 90% de capacidad con este proyecto.'];
        } else {
            $names = [];
            foreach ($talents as $t) {
                if ($t['utilization_final'] > 90) {
                    $names[] = (string) $t['name'];
                }
            }
            $insights[] = [
                'type' => 'danger',
                'text' => $overloadedCount . ' ' . ($overloadedCount === 1 ? 'talento supera' : 'talentos superan') .
                    ' el 90% de capacidad: ' . implode(', ', $names) . '.',
            ];
        }

        if ($isViable) {
            $insights[] = ['type' => 'success', 'text' => 'El proyecto "' . $projectName . '" es viable con el equipo actual.'];
        } else {
            if ($estimatedHours > $totalAvailBefore) {
                $deficit = round($estimatedHours - $totalAvailBefore, 1);
                $insights[] = [
                    'type' => 'warning',
                    'text' => 'Las horas estimadas (' . $estimatedHours . 'h) superan la capacidad disponible del equipo (' .
                        $totalAvailBefore . 'h). Déficit de ' . $deficit . 'h.',
                ];
            } else {
                $insights[] = [
                    'type' => 'warning',
                    'text' => 'El proyecto generaría sobrecarga en algunos talentos. Se recomienda revisar la distribución o ampliar el equipo.',
                ];
            }
        }

        $insights[] = [
            'type' => 'info',
            'text' => 'Capacidad disponible restante tras la simulación: ' . number_format($remainingAfter, 1) . 'h.',
        ];

        $maxUtil = 0.0;
        $maxName = '';
        foreach ($talents as $t) {
            if ($t['utilization_final'] > $maxUtil) {
                $maxUtil = $t['utilization_final'];
                $maxName = (string) $t['name'];
            }
        }
        if ($maxName !== '') {
            $insights[] = [
                'type' => $maxUtil > 90 ? 'danger' : ($maxUtil >= 70 ? 'warning' : 'success'),
                'text' => $maxName . ' sería el talento con mayor carga simulada: ' . number_format($maxUtil, 1) . '% de utilización.',
            ];
        }

        return $insights;
    }

    private function formatPeriodLabel(string $from, string $to): string
    {
        $months = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
            '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
            '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre',
            '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
        ];
        [$fy, $fm] = explode('-', $from . '-01');
        [$ty, $tm] = explode('-', $to . '-01');
        $fromLabel = ($months[$fm] ?? $fm) . ' ' . $fy;
        $toLabel   = ($months[$tm] ?? $tm) . ' ' . $ty;

        return $from === $to ? $fromLabel : ($fromLabel . ' - ' . $toLabel);
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
        if ($utilization > 90) {
            return 'risk';
        }
        if ($utilization >= 70) {
            return 'warning';
        }

        return 'ok';
    }

    private function businessDays(string $start, string $end): int
    {
        $count  = 0;
        $cursor = new DateTimeImmutable($start);
        $limit  = new DateTimeImmutable($end);
        while ($cursor <= $limit) {
            if ((int) $cursor->format('N') <= 5) {
                $count++;
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $count;
    }

    private function emptyResult(string $projectName, float $estimatedHours, string $periodFrom, string $periodTo): array
    {
        return [
            'project_name'    => $projectName,
            'estimated_hours' => $estimatedHours,
            'period_from'     => $periodFrom,
            'period_to'       => $periodTo,
            'period_label'    => $this->formatPeriodLabel($periodFrom, $periodTo),
            'talents'         => [],
            'summary'         => [
                'total_capacity'    => 0.0,
                'committed'         => 0.0,
                'available_before'  => 0.0,
                'remaining_after'   => 0.0,
                'distributed_hours' => 0.0,
                'overloaded_count'  => 0,
                'is_viable'         => false,
            ],
            'insights' => [
                ['type' => 'warning', 'text' => 'No hay talentos activos registrados en el sistema.'],
            ],
        ];
    }
}
