<?php

declare(strict_types=1);

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
}
