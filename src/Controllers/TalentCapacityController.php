<?php

declare(strict_types=1);

use App\Repositories\TalentCapacityRepository;

class TalentCapacityController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('talents.view');

        $user = $this->auth->user() ?? [];
        $filters = [
            'area' => trim((string) ($_GET['area'] ?? '')),
            'project_id' => (int) ($_GET['project_id'] ?? 0),
            'role' => trim((string) ($_GET['role'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
            'heatmap_granularity' => in_array($_GET['heatmap_granularity'] ?? 'week', ['day', 'week'], true)
                ? (string) $_GET['heatmap_granularity']
                : 'week',
        ];

        $repo = new TalentCapacityRepository($this->db);
        $dashboard = $repo->dashboard($filters, $user);

        $this->render('talent_capacity/index', [
            'title' => 'Gestión Visual de Carga y Capacidad del Talento',
            'filters' => $filters,
            'dashboard' => $dashboard,
        ]);
    }

    public function simulation(): void
    {
        $this->requirePermission('talents.view');

        $user = $this->auth->user() ?? [];
        $filters = [
            'area' => '',
            'project_id' => 0,
            'role' => '',
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
            'heatmap_granularity' => 'week',
        ];

        $repo = new TalentCapacityRepository($this->db);
        $dashboard = $repo->dashboard($filters, $user);
        $range = is_array($dashboard['range'] ?? null) ? $dashboard['range'] : ['start' => date('Y-m-01'), 'end' => date('Y-m-t')];

        $projectName = trim((string) ($_GET['project_name'] ?? ''));
        $period = trim((string) ($_GET['period'] ?? $this->defaultSimulationPeriod($range)));
        $estimatedRaw = trim((string) ($_GET['estimated_hours'] ?? '200'));
        $estimatedHours = is_numeric($estimatedRaw) ? max(0.0, (float) $estimatedRaw) : 200.0;

        $simulation = $this->buildSimulation($dashboard, $estimatedHours);

        $this->render('talent_capacity/simulation', [
            'title' => 'Gestión de Carga y Capacidad - Simulación de Capacidad',
            'dashboard' => $dashboard,
            'form' => [
                'project_name' => $projectName,
                'period' => $period,
                'estimated_hours' => $estimatedHours,
            ],
            'simulation' => $simulation,
        ]);
    }

    private function buildSimulation(array $dashboard, float $estimatedHours): array
    {
        $talents = is_array($dashboard['talents'] ?? null) ? $dashboard['talents'] : [];
        $rows = [];
        $teamCapacity = 0.0;
        $teamCommitted = 0.0;
        $teamAvailable = 0.0;

        foreach ($talents as $talent) {
            $monthly = is_array($talent['monthly'] ?? null) ? $talent['monthly'] : [];
            $latest = !empty($monthly) ? end($monthly) : ['hours' => 0, 'capacity' => 0, 'utilization' => 0];
            $currentHours = (float) ($latest['hours'] ?? 0);
            $capacity = (float) ($latest['capacity'] ?? 0);
            $available = max(0.0, $capacity - $currentHours);

            $rows[] = [
                'name' => (string) ($talent['name'] ?? 'Talento'),
                'current_hours' => $currentHours,
                'capacity' => $capacity,
                'available' => $available,
                'simulated_extra_hours' => 0.0,
                'simulated_hours' => $currentHours,
                'utilization' => $capacity > 0 ? (($currentHours / $capacity) * 100) : 0.0,
                'risk' => false,
                'traffic_light' => 'green',
            ];

            $teamCapacity += $capacity;
            $teamCommitted += $currentHours;
            $teamAvailable += $available;
        }

        $talentCount = count($rows);
        if ($talentCount > 0 && $estimatedHours > 0) {
            $totalAvailable = array_reduce(
                $rows,
                static fn (float $carry, array $row): float => $carry + (float) ($row['available'] ?? 0),
                0.0
            );

            $remaining = $estimatedHours;
            foreach ($rows as $index => &$row) {
                $isLast = $index === ($talentCount - 1);
                if ($isLast) {
                    $assigned = max(0.0, $remaining);
                } else {
                    $share = $totalAvailable > 0
                        ? ($estimatedHours * ((float) ($row['available'] ?? 0) / $totalAvailable))
                        : ($estimatedHours / $talentCount);
                    $assigned = min(max(0.0, round($share, 4)), max(0.0, $remaining));
                    $remaining -= $assigned;
                }

                $simulatedHours = (float) $row['current_hours'] + $assigned;
                $capacity = (float) $row['capacity'];
                $utilization = $capacity > 0 ? (($simulatedHours / $capacity) * 100) : ($simulatedHours > 0 ? 100.0 : 0.0);
                $risk = $utilization > 90;

                $row['simulated_extra_hours'] = $assigned;
                $row['simulated_hours'] = $simulatedHours;
                $row['utilization'] = $utilization;
                $row['risk'] = $risk;
                $row['traffic_light'] = $this->trafficLightForUtilization($utilization);
            }
            unset($row);
        } else {
            foreach ($rows as &$row) {
                $row['traffic_light'] = $this->trafficLightForUtilization((float) $row['utilization']);
            }
            unset($row);
        }

        usort(
            $rows,
            static fn (array $a, array $b): int => ((float) ($b['utilization'] ?? 0) <=> (float) ($a['utilization'] ?? 0))
                ?: strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
        );

        $simulatedCommitted = $teamCommitted + $estimatedHours;
        $remainingCapacity = $teamCapacity - $simulatedCommitted;
        $riskTalents = array_values(array_filter($rows, static fn (array $row): bool => (bool) ($row['risk'] ?? false)));
        $riskCount = count($riskTalents);
        $highest = $rows[0] ?? null;

        $insights = [];
        if ($riskCount === 0) {
            $insights[] = 'Ningún talento supera el 90% de capacidad.';
            $insights[] = 'El proyecto es viable con el equipo actual.';
        } else {
            $insights[] = sprintf('%d talento(s) superarían el 90%% de capacidad.', $riskCount);
            $insights[] = 'El proyecto requiere redistribuir carga o ajustar capacidad antes de asignarlo.';
        }
        if ($remainingCapacity >= 0) {
            $insights[] = 'Capacidad disponible restante: ' . number_format($remainingCapacity, 1) . 'h.';
        } else {
            $insights[] = 'Capacidad insuficiente: faltan ' . number_format(abs($remainingCapacity), 1) . 'h para absorber el proyecto.';
        }
        if (is_array($highest)) {
            $insights[] = (string) ($highest['name'] ?? 'Talento') . ' quedaría con la mayor utilización ('
                . number_format((float) ($highest['utilization'] ?? 0), 1) . '%).';
        }

        return [
            'rows' => $rows,
            'kpis' => [
                'team_capacity' => round($teamCapacity, 1),
                'available_capacity' => round($teamAvailable, 1),
                'committed_capacity' => round($teamCommitted, 1),
                'remaining_capacity' => round($remainingCapacity, 1),
                'simulated_committed' => round($simulatedCommitted, 1),
            ],
            'risk_count' => $riskCount,
            'insights' => $insights,
        ];
    }

    private function defaultSimulationPeriod(array $range): string
    {
        $months = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];

        $start = (string) ($range['start'] ?? date('Y-m-01'));
        $end = (string) ($range['end'] ?? date('Y-m-t'));
        $startMonth = (int) date('n', strtotime($start));
        $endMonth = (int) date('n', strtotime($end));

        return ($months[$startMonth] ?? 'Mes') . ' - ' . ($months[$endMonth] ?? 'Mes');
    }

    private function trafficLightForUtilization(float $utilization): string
    {
        if ($utilization > 90) {
            return 'red';
        }
        if ($utilization >= 70) {
            return 'yellow';
        }

        return 'green';
    }
}
