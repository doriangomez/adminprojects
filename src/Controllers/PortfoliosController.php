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
            $projects = $projectsRepo->projectsForPortfolio((int) $portfolio['id'], $user) ?? [];
            $kpis = $this->defaultKpis($projectsRepo->portfolioKpisFromProjects($projects));

            $portfolioData = $portfolio;
            $portfolioData['projects'] = $projects;
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

    public function wizard(?string $error = null): void
    {
        $this->requirePermission('projects.manage');
        $this->render('portfolios/wizard', $this->wizardData($error));
    }

    public function store(): void
    {
        $this->requirePermission('projects.manage');

        $config = (new ConfigService())->getConfig();
        $repo = new PortfoliosRepository($this->db, $config['operational_rules']);

        try {
            $attachment = $repo->storeAttachment($_FILES['attachment'] ?? null);
            $payload = $this->payload($attachment);
            $portfolioId = $repo->create($payload);
            $repo->syncProjects($portfolioId, $payload['selected_projects']);
            header('Location: /project/public/portfolio');
        } catch (\Throwable $e) {
            error_log('Error al crear portafolio: ' . $e->getMessage());
            $this->index('No se pudo registrar el portafolio: ' . $e->getMessage());
        }
    }

    public function storeWizard(): void
    {
        $this->requirePermission('projects.manage');

        $config = (new ConfigService())->getConfig();
        $portfolioRepo = new PortfoliosRepository($this->db, $config['operational_rules']);
        $clientsRepo = new ClientsRepository($this->db);
        $projectsRepo = new ProjectsRepository($this->db);
        $masterRepo = new MasterFilesRepository($this->db);
        $riskCatalog = $masterRepo->list('client_risk');
        $pdo = $this->db->connection();

        try {
            $pdo->beginTransaction();

            $clientId = $this->resolveClient($clientsRepo);
            $attachment = $portfolioRepo->storeAttachment($_FILES['portfolio_attachment'] ?? null);
            $riskSelection = array_filter($_POST['portfolio_risks'] ?? []);
            $riskRegister = $this->riskRegisterText($riskSelection, $riskCatalog);
            $riskLevel = $this->riskLevelFromSelection($riskSelection);

            $portfolioName = trim($_POST['portfolio_name'] ?? '');
            if ($portfolioName === '') {
                throw new InvalidArgumentException('Asigna un nombre al portafolio.');
            }

            $portfolioId = $portfolioRepo->create([
                'client_id' => $clientId,
                'name' => $portfolioName,
                'objective' => trim($_POST['portfolio_objective'] ?? ''),
                'description' => trim($_POST['portfolio_description'] ?? ''),
                'start_date' => $_POST['portfolio_start'] ?? null,
                'end_date' => $_POST['portfolio_end'] ?? null,
                'budget_limit' => $this->nullableFloat($_POST['portfolio_budget'] ?? ''),
                'attachment_path' => $attachment,
                'risk_register' => $riskRegister,
                'risk_level_text' => $riskLevel,
            ]);

            $projects = $this->collectProjects($masterRepo, $clientId, $portfolioId);
            if (empty($projects)) {
                throw new InvalidArgumentException('Agrega al menos un proyecto para el portafolio.');
            }

            foreach ($projects as $project) {
                $projectsRepo->create($project);
            }

            $pdo->commit();
            header('Location: /project/public/projects/portfolio');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Error en wizard de portafolio: ' . $e->getMessage());
            $this->wizard('No se pudo completar el wizard: ' . $e->getMessage());
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
            $payload = $this->payload($attachment);
            $repo->update($id, $payload);
            $repo->syncProjects($id, $payload['selected_projects']);
            header('Location: /project/public/portfolio');
        } catch (\Throwable $e) {
            error_log('Error al actualizar portafolio: ' . $e->getMessage());
            $this->index('No se pudo actualizar el portafolio: ' . $e->getMessage());
        }
    }

    public function destroy(): void
    {
        $this->requirePermission('projects.manage');

        if (!$this->isAdmin()) {
            http_response_code(403);
            exit('Solo administradores pueden eliminar portafolios.');
        }

        $config = (new ConfigService())->getConfig();
        $repo = new PortfoliosRepository($this->db, $config['operational_rules']);
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            $this->index('Portafolio no encontrado.');
            return;
        }

        $result = $repo->delete($id);
        if (($result['success'] ?? false) === true) {
            header('Location: /project/public/portfolio');
            return;
        }

        $message = ($result['error'] ?? '') === 'PORTFOLIO_NOT_FOUND'
            ? 'El portafolio no existe.'
            : 'No se pudo eliminar el portafolio. Intenta nuevamente o contacta al administrador.';

        $this->index($message);
    }

    public function inactivate(int $id): void
    {
        $this->requirePermission('projects.manage');

        if (!$this->isAdmin()) {
            http_response_code(403);
            exit('Solo administradores pueden inactivar portafolios.');
        }

        $config = (new ConfigService())->getConfig();
        $repo = new PortfoliosRepository($this->db, $config['operational_rules']);

        if ($id <= 0) {
            http_response_code(404);
            $this->index('Portafolio no encontrado.');
            return;
        }

        try {
            if ($repo->inactivate($id)) {
                header('Location: /project/public/portfolio');
                return;
            }

            $this->index('No se pudo inactivar el portafolio.');
        } catch (\Throwable $e) {
            error_log('Error al inactivar portafolio: ' . $e->getMessage());
            $this->index('No se pudo inactivar el portafolio: ' . $e->getMessage());
        }
    }

    private function payload(?string $attachmentPath): array
    {
        $selectedProjects = array_map('intval', $_POST['projects_included'] ?? []);

        return [
            'client_id' => (int) ($_POST['client_id'] ?? 0),
            'name' => trim($_POST['name'] ?? ''),
            'objective' => trim($_POST['objective'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'start_date' => $_POST['start_date'] ?? null,
            'end_date' => $_POST['end_date'] ?? null,
            'budget_total' => $this->nullableFloat($_POST['budget_total'] ?? ''),
            'risk_level' => null,
            'attachment_path' => $attachmentPath,
            'projects_included' => $selectedProjects ? json_encode($selectedProjects) : null,
            'selected_projects' => $selectedProjects,
            'rules_notes' => trim($_POST['rules_notes'] ?? ''),
            'alerting_policy' => trim($_POST['alerting_policy'] ?? ''),
            'risk_register' => isset($_POST['risk_register']) ? trim((string) $_POST['risk_register']) : null,
            'risk_level_text' => isset($_POST['risk_level_text']) ? trim((string) $_POST['risk_level_text']) : null,
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
            'budget_used' => 0.0,
            'budget_planned' => 0.0,
        ];

        return array_merge($defaults, $kpis);
    }

    private function wizardData(?string $error = null): array
    {
        $config = (new ConfigService())->getConfig();
        $clientsRepo = new ClientsRepository($this->db);
        $masterRepo = new MasterFilesRepository($this->db);
        $usersRepo = new UsersRepository($this->db);
        $user = $this->auth->user() ?? [];

        $projectManagers = array_filter(
            $usersRepo->all(),
            fn ($candidate) => ($candidate['active'] ?? 0) == 1 && in_array($candidate['role_name'] ?? '', ['Administrador', 'PMO', 'Líder de Proyecto'], true)
        );

        return [
            'title' => 'Wizard de creación de portafolio',
            'clients' => $clientsRepo->listForUser($user),
            'sectors' => $masterRepo->list('client_sectors'),
            'categories' => $masterRepo->list('client_categories'),
            'priorities' => $masterRepo->list('priorities'),
            'statuses' => $masterRepo->list('client_status'),
            'riskCatalog' => $masterRepo->list('client_risk'),
            'projectManagers' => $projectManagers,
            'projectTypes' => [
                'convencional' => 'Convencional',
                'agil' => 'Ágil',
                'soporte' => 'Soporte continuo',
            ],
            'operationalRules' => $config['operational_rules'],
            'error' => $error,
            'oldInput' => $_POST ?? [],
        ];
    }

    private function resolveClient(ClientsRepository $clientsRepo): int
    {
        $masterRepo = new MasterFilesRepository($this->db);
        $mode = $_POST['client_mode'] ?? 'existing';

        if ($mode === 'new') {
            $payload = [
                'name' => trim($_POST['client_name'] ?? ''),
                'sector_code' => $this->validatedCatalogValue($_POST['client_sector'] ?? '', $masterRepo->list('client_sectors'), 'sector'),
                'category_code' => $this->validatedCatalogValue($_POST['client_category'] ?? '', $masterRepo->list('client_categories'), 'categoría'),
                'priority_code' => $this->validatedCatalogValue($_POST['client_priority'] ?? '', $masterRepo->list('priorities'), 'prioridad'),
                'status_code' => $this->validatedCatalogValue($_POST['client_status'] ?? '', $masterRepo->list('client_status'), 'estado'),
                'pm_id' => $this->validatedPmId((int) ($_POST['client_pm'] ?? 0)),
            ];

            if ($payload['name'] === '') {
                throw new InvalidArgumentException('Define el nombre del cliente.');
            }

            return $clientsRepo->create($payload);
        }

        $clientId = (int) ($_POST['client_id'] ?? 0);
        $user = $this->auth->user() ?? [];

        if ($clientId <= 0) {
            throw new InvalidArgumentException('Selecciona un cliente válido.');
        }

        $client = $clientsRepo->findForUser($clientId, $user);

        if (!$client) {
            throw new InvalidArgumentException('Selecciona un cliente válido.');
        }

        return (int) $client['id'];
    }

    private function collectProjects(MasterFilesRepository $masterRepo, int $clientId, int $portfolioId): array
    {
        $projects = [];
        $projectEntries = $_POST['projects'] ?? [];
        $priorityCatalog = $masterRepo->list('priorities');

        foreach ($projectEntries as $project) {
            $name = trim($project['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $pmId = $this->validatedPmId((int) ($project['pm_id'] ?? 0));
            $projectType = $project['project_type'] ?? 'convencional';
            $allowedTypes = ['convencional', 'agil', 'soporte'];
            if (!in_array($projectType, $allowedTypes, true)) {
                $projectType = 'convencional';
            }
            $projects[] = [
                'client_id' => $clientId,
                'portfolio_id' => $portfolioId,
                'pm_id' => $pmId,
                'name' => $name,
                'status' => 'ideation',
                'health' => 'on_track',
                'priority' => $this->validatedCatalogValue($project['priority'] ?? '', $priorityCatalog, 'prioridad'),
                'project_type' => $projectType,
                'budget' => $this->nullableFloat($project['budget'] ?? '') ?? 0,
                'actual_cost' => 0,
                'planned_hours' => 0,
                'actual_hours' => 0,
                'progress' => 0,
                'start_date' => $project['start_date'] ?? null,
                'end_date' => $project['end_date'] ?? null,
            ];
        }

        return $projects;
    }

    private function validatedCatalogValue(string $value, array $catalog, string $label): string
    {
        $clean = trim($value);
        foreach ($catalog as $item) {
            if (($item['code'] ?? '') === $clean) {
                return $clean;
            }
        }

        throw new InvalidArgumentException("Selecciona un valor válido para {$label}.");
    }

    private function validatedPmId(int $pmId): int
    {
        $usersRepo = new UsersRepository($this->db);
        if ($pmId > 0 && $usersRepo->isValidProjectManager($pmId)) {
            return $pmId;
        }

        throw new InvalidArgumentException('Selecciona un PM válido para el proyecto/cliente.');
    }

    private function riskRegisterText(array $riskCodes, array $catalog): ?string
    {
        if (empty($riskCodes)) {
            return null;
        }

        $labels = [];
        foreach ($catalog as $risk) {
            if (in_array($risk['code'] ?? '', $riskCodes, true)) {
                $labels[] = $risk['label'] ?? $risk['code'];
            }
        }

        return $labels ? implode(', ', $labels) : null;
    }

    private function riskLevelFromSelection(array $riskCodes): string
    {
        if (empty($riskCodes)) {
            return 'bajo';
        }

        $severity = 'bajo';
        foreach ($riskCodes as $risk) {
            if ($risk === 'high') {
                return 'alto';
            }
            if ($risk === 'moderate') {
                $severity = 'medio';
            }
        }

        return $severity;
    }

    private function isAdmin(): bool
    {
        return $this->auth->hasRole('Administrador') || $this->auth->can('config.manage');
    }
}
