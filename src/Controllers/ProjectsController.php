<?php

declare(strict_types=1);

class ProjectsController extends Controller
{
    public function index(): void
    {
        $repo = new ProjectsRepository($this->db);
        $this->requirePermission('projects.view');
        $this->render('projects/index', [
            'title' => 'Portafolio de Proyectos',
            'projects' => $repo->summary($this->auth->user() ?? []),
        ]);
    }

    public function portfolio(?string $error = null): void
    {
        $this->requirePermission('projects.view');
        $config = (new ConfigService())->getConfig();
        $user = $this->auth->user() ?? [];
        $projectsRepo = new ProjectsRepository($this->db);
        $portfolioRepo = new PortfoliosRepository($this->db, $config['operational_rules']);
        $clientsRepo = new ClientsRepository($this->db);

        $portfolios = $portfolioRepo->listWithUsage($user) ?? [];
        $clients = $clientsRepo->listForUser($user);
        $portfolioView = [];
        $clientsIndex = [];

        foreach ($clients as $client) {
            $clientsIndex[(int) $client['id']] = $client;
        }

        foreach ($portfolios as $portfolio) {
            $projects = $projectsRepo->projectsForPortfolio((int) $portfolio['id'], $user) ?? [];

            $portfolioView[] = [
                'id' => (int) $portfolio['id'],
                'client_id' => (int) $portfolio['client_id'],
                'client_name' => $portfolio['client_name'],
                'name' => $portfolio['name'],
                'start_date' => $portfolio['start_date'],
                'end_date' => $portfolio['end_date'],
                'budget_total' => $portfolio['budget_total'],
                'risk_level' => $portfolio['risk_level'],
                'attachment_path' => $portfolio['attachment_path'],
                'projects_included' => $portfolio['projects_included'] ?? null,
                'rules_notes' => $portfolio['rules_notes'] ?? null,
                'alerting_policy' => $portfolio['alerting_policy'] ?? null,
                'alerts' => $portfolio['alerts'],
                'budget_ratio' => $portfolio['budget_ratio'],
                'projects' => $projects,
                'kpis' => $this->defaultKpis($projectsRepo->portfolioKpisFromProjects($projects)),
                'signal' => $projectsRepo->clientSignal($projects),
                'client_meta' => $clientsIndex[(int) $portfolio['client_id']] ?? [],
            ];
        }

        $this->render('projects/portfolio', [
            'title' => 'Portafolio por cliente',
            'clients' => $clients,
            'portfolios' => $portfolioView,
            'error' => $error,
        ]);
    }

    public function assignTalent(): void
    {
        $this->requirePermission('projects.manage');
        $repo = new ProjectsRepository($this->db);

        try {
            $repo->assignTalent($this->collectAssignmentPayload());
            header('Location: /project/public/projects/portfolio');
        } catch (\Throwable $e) {
            error_log('Error al asignar talento: ' . $e->getMessage());
            $this->portfolio('No se pudo asignar el talento: ' . $e->getMessage());
        }
    }

    private function collectAssignmentPayload(): array
    {
        $allocationPercent = $_POST['allocation_percent'] ?? null;
        $weeklyHours = $_POST['weekly_hours'] ?? null;

        return [
            'project_id' => (int) ($_POST['project_id'] ?? 0),
            'talent_id' => (int) ($_POST['talent_id'] ?? 0),
            'role' => trim($_POST['role'] ?? ''),
            'start_date' => $_POST['start_date'] ?? null,
            'end_date' => $_POST['end_date'] ?? null,
            'allocation_percent' => $allocationPercent !== '' ? (float) $allocationPercent : null,
            'weekly_hours' => $weeklyHours !== '' ? (float) $weeklyHours : null,
            'cost_type' => $_POST['cost_type'] ?? 'por_horas',
            'cost_value' => (float) ($_POST['cost_value'] ?? 0),
            'is_external' => isset($_POST['is_external']) ? 1 : 0,
            'requires_timesheet' => isset($_POST['requires_timesheet']) ? 1 : 0,
            'requires_approval' => isset($_POST['requires_approval']) ? 1 : 0,
        ];
    }

    private function defaultKpis(array $kpis): array
    {
        $defaults = [
            'projects_total' => 0,
            'projects_active' => 0,
            'progress_avg' => 0.0,
            'risk_level' => 'bajo',
            'avg_progress' => 0.0,
            'active_projects' => 0,
            'total_projects' => 0,
            'budget_used' => 0.0,
            'budget_planned' => 0.0,
        ];

        return array_merge($defaults, $kpis);
    }
}
