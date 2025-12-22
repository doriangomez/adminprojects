<?php

declare(strict_types=1);

class PortfoliosController extends Controller
{
    public function index(?string $error = null): void
    {
        $this->requirePermission('projects.view');

        $config = (new ConfigService())->getConfig();
        $user = $this->auth->user() ?? [];
        $projectsRepo = new ProjectsRepository($this->db);
        $portfolioRepo = new PortfoliosRepository($this->db, $config['operational_rules']);
        $clientsRepo = new ClientsRepository($this->db);

        $portfolios = [];
        foreach ($portfolioRepo->listWithUsage($user) as $portfolio) {
            $projects = $projectsRepo->projectsForClient((int) $portfolio['client_id'], $user) ?? [];
            $assignments = $projectsRepo->assignmentsForClient((int) $portfolio['client_id'], $user);
            $assignmentsByProject = [];
            foreach ($assignments as $assignment) {
                $assignmentsByProject[$assignment['project_id']][] = $assignment;
            }

            $kpis = $this->defaultKpis($projectsRepo->clientKpis($projects, $assignments));

            $portfolioData = $portfolio;
            $portfolioData['projects'] = $projects;
            $portfolioData['assignments'] = $assignmentsByProject;
            $portfolioData['kpis'] = $kpis;
            $portfolioData['signal'] = $projectsRepo->clientSignal($projects);

            $portfolios[] = $portfolioData;
        }

        $this->render('projects/portfolio', [
            'title' => 'Portafolios de cliente',
            'clients' => $clientsRepo->listForUser($user),
            'portfolios' => $portfolios,
            'error' => $error,
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('projects.manage');

        $user = $this->auth->user() ?? [];
        $config = (new ConfigService())->getConfig();
        $clientsRepo = new ClientsRepository($this->db);
        $projectsRepo = new ProjectsRepository($this->db);

        $clients = $clientsRepo->listForUser($user);
        $projectsByClient = [];
        foreach ($clients as $client) {
            $projectsByClient[$client['id']] = $projectsRepo->projectsForClient((int) $client['id'], $user);
        }

        $this->render('portfolios/create', [
            'title' => 'Nuevo portafolio',
            'clients' => $clients,
            'projectsByClient' => $projectsByClient,
            'operationalRules' => $config['operational_rules'],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('projects.manage');

        $config = (new ConfigService())->getConfig();
        $repo = new PortfoliosRepository($this->db, $config['operational_rules']);

        try {
            $attachment = $repo->storeAttachment($_FILES['attachment'] ?? null);
            $repo->create($this->payload($attachment));
            header('Location: /project/public/portfolio');
        } catch (\Throwable $e) {
            error_log('Error al crear portafolio: ' . $e->getMessage());
            $this->index('No se pudo registrar el portafolio: ' . $e->getMessage());
        }
    }

    public function update(): void
    {
        $this->requirePermission('projects.manage');

        $config = (new ConfigService())->getConfig();
        $repo = new PortfoliosRepository($this->db, $config['operational_rules']);
        $id = (int) ($_POST['id'] ?? 0);

        try {
            $attachment = $repo->storeAttachment($_FILES['attachment'] ?? null) ?: ($_POST['current_attachment'] ?? null);
            $repo->update($id, $this->payload($attachment));
            header('Location: /project/public/portfolio');
        } catch (\Throwable $e) {
            error_log('Error al actualizar portafolio: ' . $e->getMessage());
            $this->index('No se pudo actualizar el portafolio: ' . $e->getMessage());
        }
    }

    private function payload(?string $attachmentPath): array
    {
        $selectedProjects = array_map('intval', $_POST['projects_included'] ?? []);

        return [
            'client_id' => (int) ($_POST['client_id'] ?? 0),
            'name' => trim($_POST['name'] ?? ''),
            'start_date' => $_POST['start_date'] ?? null,
            'end_date' => $_POST['end_date'] ?? null,
            'hours_limit' => $this->nullableFloat($_POST['hours_limit'] ?? ''),
            'budget_limit' => $this->nullableFloat($_POST['budget_limit'] ?? ''),
            'attachment_path' => $attachmentPath,
            'projects_included' => $selectedProjects ? json_encode($selectedProjects) : null,
            'rules_notes' => trim($_POST['rules_notes'] ?? ''),
            'alerting_policy' => trim($_POST['alerting_policy'] ?? ''),
        ];
    }

    private function nullableFloat(string $value): ?float
    {
        $clean = trim($value);
        if ($clean === '') {
            return null;
        }

        return (float) str_replace(',', '.', $clean);
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
            'capacity_used' => 0.0,
            'capacity_available' => 0.0,
            'capacity_percent' => 0.0,
        ];

        return array_merge($defaults, $kpis);
    }
}
