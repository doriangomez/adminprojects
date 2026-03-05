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
            'area' => trim((string) ($_GET['area'] ?? '')),
            'project_id' => (int) ($_GET['project_id'] ?? 0),
            'role' => trim((string) ($_GET['role'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? date('Y-m-01'))),
            'date_to' => trim((string) ($_GET['date_to'] ?? date('Y-m-t'))),
        ];

        $repo = new TalentCapacityRepository($this->db);
        $dashboard = $repo->dashboard($filters, $user);

        $this->render('talent_capacity/simulation', [
            'title' => 'Simulación de Capacidad',
            'filters' => $filters,
            'dashboard' => $dashboard,
            'simulationResult' => null,
        ]);
    }

    public function runSimulation(): void
    {
        $this->requirePermission('talents.view');

        $user = $this->auth->user() ?? [];
        $projectName = trim((string) ($_POST['project_name'] ?? ''));
        $estimatedHours = (float) ($_POST['estimated_hours'] ?? 0);
        $periodFrom = trim((string) ($_POST['period_from'] ?? date('Y-m-01')));
        $periodTo = trim((string) ($_POST['period_to'] ?? date('Y-m-t')));

        $filters = [
            'area' => trim((string) ($_POST['area'] ?? '')),
            'project_id' => (int) ($_POST['project_id'] ?? 0),
            'role' => trim((string) ($_POST['role'] ?? '')),
            'date_from' => $periodFrom ?: date('Y-m-01'),
            'date_to' => $periodTo ?: date('Y-m-t'),
        ];

        $repo = new TalentCapacityRepository($this->db);
        $dashboard = $repo->dashboard($filters, $user);
        $talents = $dashboard['talents'] ?? [];

        $impact = [];
        $totalCapacity = 0.0;
        $totalAvailable = 0.0;

        foreach ($talents as $talent) {
            $monthly = $talent['monthly'] ?? [];
            $latest = !empty($monthly) ? end($monthly) : ['hours' => 0, 'capacity' => 0, 'utilization' => 0];
            $currentHours = (float) ($latest['hours'] ?? 0);
            $capacity = (float) ($latest['capacity'] ?? 0);
            $available = max(0.0, $capacity - $currentHours);

            $totalCapacity += $capacity;
            $totalAvailable += $available;

            $impact[] = [
                'name' => (string) ($talent['name'] ?? ''),
                'current_hours' => $currentHours,
                'capacity' => $capacity,
                'available' => $available,
            ];
        }

        $simulatedHours = 0.0;
        if ($totalAvailable > 0 && $estimatedHours > 0) {
            foreach ($impact as &$row) {
                $share = ($row['available'] / $totalAvailable) * $estimatedHours;
                $row['simulated_hours'] = $row['current_hours'] + $share;
                $row['simulated_utilization'] = $row['capacity'] > 0
                    ? round(($row['simulated_hours'] / $row['capacity']) * 100, 1)
                    : 0.0;
                $simulatedHours += $row['simulated_hours'];
            }
            unset($row);
        } else {
            foreach ($impact as &$row) {
                $row['simulated_hours'] = $row['current_hours'];
                $row['simulated_utilization'] = $row['capacity'] > 0
                    ? round(($row['current_hours'] / $row['capacity']) * 100, 1)
                    : 0.0;
            }
            unset($row);
        }

        $overloadCount = 0;
        $overloadNames = [];
        foreach ($impact as $row) {
            if ((float) ($row['simulated_utilization'] ?? 0) > 90) {
                $overloadCount++;
                $overloadNames[] = $row['name'];
            }
        }

        $remainingCapacity = max(0.0, $totalCapacity - $simulatedHours);

        $insights = [];
        if ($overloadCount > 0) {
            $insights[] = $overloadCount === 1
                ? '1 talento supera el 90% de capacidad: ' . implode(', ', $overloadNames)
                : $overloadCount . ' talentos superan el 90% de capacidad: ' . implode(', ', $overloadNames);
        } else {
            $insights[] = 'Ningún talento supera el 90% de capacidad';
        }

        if ($remainingCapacity >= $estimatedHours) {
            $insights[] = 'El proyecto es viable con el equipo actual';
        } else {
            $insights[] = 'El proyecto podría requerir ajustes en la distribución o capacidad adicional';
        }

        $insights[] = 'Capacidad disponible restante: ' . number_format($remainingCapacity, 0) . 'h';

        usort($impact, static fn (array $a, array $b): int => ($b['simulated_utilization'] <=> $a['simulated_utilization']) ?: strcmp($a['name'], $b['name']));

        $this->render('talent_capacity/simulation', [
            'title' => 'Simulación de Capacidad',
            'filters' => $filters,
            'dashboard' => $dashboard,
            'simulationResult' => [
                'project_name' => $projectName ?: 'Proyecto simulado',
                'estimated_hours' => $estimatedHours,
                'period_from' => $filters['date_from'],
                'period_to' => $filters['date_to'],
                'impact' => $impact,
                'insights' => $insights,
                'remaining_capacity' => $remainingCapacity,
            ],
        ]);
    }
}
