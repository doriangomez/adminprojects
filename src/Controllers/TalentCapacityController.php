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
            'date_from' => trim((string) ($_GET['date_from'] ?? date('Y-m-01'))),
            'date_to' => trim((string) ($_GET['date_to'] ?? date('Y-m-t'))),
        ];

        $repo = new TalentCapacityRepository($this->db);
        $data = $repo->simulationData($filters, $user);

        $this->render('talent_capacity/simulation', [
            'title' => 'Simulación de Capacidad',
            'filters' => $filters,
            'data' => $data,
        ]);
    }

    public function runSimulation(): void
    {
        $this->requirePermission('talents.view');

        $user = $this->auth->user() ?? [];
        $projectName = trim((string) ($_POST['project_name'] ?? ''));
        $estimatedHours = filter_var($_POST['estimated_hours'] ?? 0, FILTER_VALIDATE_FLOAT) ?: 0.0;
        $periodFrom = trim((string) ($_POST['period_from'] ?? date('Y-m-01')));
        $periodTo = trim((string) ($_POST['period_to'] ?? date('Y-m-t')));

        $repo = new TalentCapacityRepository($this->db);
        $data = $repo->simulationData([
            'date_from' => $periodFrom,
            'date_to' => $periodTo,
        ], $user);

        $result = $repo->runSimulation($data, $projectName, $estimatedHours);

        $this->render('talent_capacity/simulation', [
            'title' => 'Simulación de Capacidad',
            'filters' => ['date_from' => $periodFrom, 'date_to' => $periodTo],
            'data' => $data,
            'simulation' => $result,
        ]);
    }
}
