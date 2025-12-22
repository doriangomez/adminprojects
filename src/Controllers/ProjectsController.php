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

        $portfolios = $portfolioRepo->listWithUsage($user);
        $clients = $clientsRepo->listForUser($user);
        $portfolioView = [];

        foreach ($portfolios as $portfolio) {
            $projects = $projectsRepo->projectsForClient((int) $portfolio['client_id'], $user);
            $assignments = $projectsRepo->assignmentsForClient((int) $portfolio['client_id'], $user);
            $assignmentsByProject = [];
            foreach ($assignments as $assignment) {
                $assignmentsByProject[$assignment['project_id']][] = $assignment;
            }

            $portfolioView[] = [
                'id' => (int) $portfolio['id'],
                'client_id' => (int) $portfolio['client_id'],
                'client_name' => $portfolio['client_name'],
                'name' => $portfolio['name'],
                'start_date' => $portfolio['start_date'],
                'end_date' => $portfolio['end_date'],
                'hours_limit' => $portfolio['hours_limit'],
                'budget_limit' => $portfolio['budget_limit'],
                'attachment_path' => $portfolio['attachment_path'],
                'alerts' => $portfolio['alerts'],
                'hours_ratio' => $portfolio['hours_ratio'],
                'budget_ratio' => $portfolio['budget_ratio'],
                'projects' => $projects,
                'kpis' => $projectsRepo->clientKpis($projects, $assignments),
                'signal' => $projectsRepo->clientSignal($projects),
                'assignments' => $assignmentsByProject,
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
}
