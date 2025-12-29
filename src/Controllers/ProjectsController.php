<?php

declare(strict_types=1);

class ProjectsController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('projects.view');
        $user = $this->auth->user() ?? [];

        $filters = [
            'client_id' => isset($_GET['client_id']) ? (int) $_GET['client_id'] : null,
            'status' => trim((string) ($_GET['status'] ?? '')),
            'methodology' => trim((string) ($_GET['methodology'] ?? '')),
            'start_date' => $_GET['start_date'] ?? '',
            'end_date' => $_GET['end_date'] ?? '',
        ];

        $repo = new ProjectsRepository($this->db);
        $config = (new ConfigService($this->db))->getConfig();
        $clientsRepo = new ClientsRepository($this->db);

        $this->render('projects/index', [
            'title' => 'Panel de Proyectos',
            'projects' => $repo->summary($user, $filters),
            'filters' => $filters,
            'clients' => $clientsRepo->listForUser($user),
            'delivery' => $config['delivery'] ?? [],
        ]);
    }

    public function show(int $id): void
    {
        $this->requirePermission('projects.view');
        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $project = $repo->findForUser($id, $user);

        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        $assignments = $repo->assignmentsForProject($id, $user);

        $this->render('projects/show', [
            'title' => 'Detalle de proyecto',
            'project' => $project,
            'assignments' => $assignments,
            'canManage' => $this->auth->can('projects.manage'),
        ]);
    }

    public function edit(int $id): void
    {
        $this->requirePermission('projects.manage');
        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $project = $repo->findForUser($id, $user);

        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        $config = (new ConfigService($this->db))->getConfig();

        $this->render('projects/edit', [
            'title' => 'Editar proyecto',
            'project' => $project,
            'delivery' => $config['delivery'] ?? [],
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('projects.manage');
        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $project = $repo->findForUser($id, $user);

        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        $config = (new ConfigService($this->db))->getConfig();
        $payload = $this->projectPayload($project, $config['delivery'] ?? []);
        $repo->updateProject($id, $payload);

        header('Location: /project/public/projects/' . $id);
    }

    public function create(): void
    {
        $this->requirePermission('projects.manage');

        $this->render('projects/create', array_merge($this->projectFormData(), [
            'title' => 'Nuevo proyecto',
        ]));
    }

    public function store(): void
    {
        $this->requirePermission('projects.manage');
        $delivery = (new ConfigService())->getConfig()['delivery'] ?? [];
        $masterRepo = new MasterFilesRepository($this->db);
        $usersRepo = new UsersRepository($this->db);
        $repo = new ProjectsRepository($this->db);

        try {
            $payload = $this->validatedProjectPayload($delivery, $this->projectCatalogs($masterRepo), $usersRepo);
            $projectId = $repo->create($payload);
            if (!empty($payload['risks'])) {
                $repo->syncProjectRisks($projectId, $payload['risks']);
            }
            header('Location: /project/public/projects/' . $projectId);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            $this->render('projects/create', array_merge($this->projectFormData(), [
                'title' => 'Nuevo proyecto',
                'error' => $e->getMessage(),
                'old' => $_POST,
            ]));
        } catch (\PDOException $e) {
            error_log('Error al crear proyecto (DB): ' . $e->getMessage());
            http_response_code(500);
            $this->render('projects/create', array_merge($this->projectFormData(), [
                'title' => 'Nuevo proyecto',
                'error' => $this->isLocalEnvironment()
                    ? $e->getMessage()
                    : 'No se pudo crear el proyecto. Intenta nuevamente o contacta al administrador.',
                'old' => $_POST,
            ]));
        } catch (\Throwable $e) {
            error_log('Error al crear proyecto: ' . $e->getMessage());
            http_response_code(500);
            $this->render('projects/create', array_merge($this->projectFormData(), [
                'title' => 'Nuevo proyecto',
                'error' => 'No se pudo crear el proyecto. Intenta nuevamente o contacta al administrador.',
                'old' => $_POST,
            ]));
        }
    }

    public function talent(int $id): void
    {
        $this->requirePermission('projects.manage');
        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $project = $repo->findForUser($id, $user);

        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        $assignments = $repo->assignmentsForProject($id, $user);
        $talents = (new TalentsRepository($this->db))->summary();

        $this->render('projects/talent', [
            'title' => 'Gestionar talento',
            'project' => $project,
            'assignments' => $assignments,
            'talents' => $talents,
        ]);
    }

    public function costs(int $id): void
    {
        $this->requirePermission('projects.view');
        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $project = $repo->findForUser($id, $user);

        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        $this->render('projects/costs', [
            'title' => 'Costos del proyecto',
            'project' => $project,
        ]);
    }

    public function confirmClose(int $id): void
    {
        $this->requirePermission('projects.manage');
        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $project = $repo->findForUser($id, $user);

        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        $this->render('projects/close', [
            'title' => 'Cerrar proyecto',
            'project' => $project,
        ]);
    }

    public function close(int $id): void
    {
        $this->requirePermission('projects.manage');
        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $project = $repo->findForUser($id, $user);

        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        $confirm = (string) ($_POST['confirm'] ?? '');
        if ($confirm !== 'yes') {
            header('Location: /project/public/projects/' . $id . '/close');
            return;
        }

        $repo->closeProject($id);
        header('Location: /project/public/projects/' . $id);
    }

    public function assignTalent(?int $projectId = null): void
    {
        $this->requirePermission('projects.manage');
        $repo = new ProjectsRepository($this->db);

        try {
            $payload = $this->collectAssignmentPayload($projectId);
            $repo->assignTalent($payload);
            $redirectId = (int) ($payload['project_id'] ?? 0);
            $destination = $redirectId > 0 ? '/project/public/projects/' . $redirectId . '/talent' : '/project/public/projects';
            header('Location: ' . $destination);
        } catch (\Throwable $e) {
            error_log('Error al asignar talento: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo asignar el talento: ' . $e->getMessage());
        }
    }

    private function collectAssignmentPayload(?int $projectId): array
    {
        $allocationPercent = $_POST['allocation_percent'] ?? null;
        $weeklyHours = $_POST['weekly_hours'] ?? null;

        return [
            'project_id' => $projectId ?? (int) ($_POST['project_id'] ?? 0),
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

    private function isLocalEnvironment(): bool
    {
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?? 'production';

        return strtolower((string) $env) === 'local';
    }

    private function projectPayload(array $current, array $deliveryConfig = []): array
    {
        $delivery = [
            'methodologies' => $deliveryConfig['methodologies'] ?? [],
            'phases' => $deliveryConfig['phases'] ?? [],
            'risks' => $deliveryConfig['risks'] ?? [],
        ];

        $projectType = $_POST['project_type'] ?? (string) ($current['project_type'] ?? 'convencional');
        $projectType = in_array($projectType, ['convencional', 'scrum'], true) ? $projectType : 'convencional';

        $availableMethodologies = $delivery['methodologies'];
        $defaultMethodology = $projectType === 'scrum'
            ? ($availableMethodologies[0] ?? 'scrum')
            : ($availableMethodologies[0] ?? 'cascada');
        $methodology = $_POST['methodology'] ?? ($current['methodology'] ?? $defaultMethodology);
        if (!empty($availableMethodologies) && !in_array($methodology, $availableMethodologies, true)) {
            $methodology = $availableMethodologies[0];
        }

        $availablePhases = is_array($delivery['phases'][$methodology] ?? null) ? $delivery['phases'][$methodology] : [];
        $phaseInput = $_POST['phase'] ?? ($current['phase'] ?? null);
        $phase = ($phaseInput === '' || !in_array($phaseInput, $availablePhases, true)) ? null : $phaseInput;

        $riskCodes = array_values(array_filter(array_map(fn ($risk) => $risk['code'] ?? '', $delivery['risks'])));
        $risks = array_values(array_intersect(array_filter($_POST['risks'] ?? []), $riskCodes));

        $endDate = $_POST['end_date'] ?? ($current['end_date'] ?? null);
        if ($projectType === 'scrum') {
            $endDate = null;
        }

        return [
            'name' => trim($_POST['name'] ?? (string) ($current['name'] ?? '')),
            'status' => $_POST['status'] ?? (string) ($current['status'] ?? ''),
            'health' => $_POST['health'] ?? (string) ($current['health'] ?? ''),
            'priority' => $_POST['priority'] ?? (string) ($current['priority'] ?? ''),
            'client_id' => (int) ($_POST['client_id'] ?? ($current['client_id'] ?? 0)),
            'pm_id' => (int) ($_POST['pm_id'] ?? ($current['pm_id'] ?? 0)),
            'project_type' => $projectType,
            'budget' => (float) ($_POST['budget'] ?? ($current['budget'] ?? 0)),
            'actual_cost' => (float) ($_POST['actual_cost'] ?? ($current['actual_cost'] ?? 0)),
            'planned_hours' => (float) ($_POST['planned_hours'] ?? ($current['planned_hours'] ?? 0)),
            'actual_hours' => (float) ($_POST['actual_hours'] ?? ($current['actual_hours'] ?? 0)),
            'progress' => (float) ($_POST['progress'] ?? ($current['progress'] ?? 0)),
            'start_date' => $_POST['start_date'] ?? ($current['start_date'] ?? null),
            'end_date' => $endDate,
            'methodology' => $methodology,
            'phase' => $phase,
            'risks' => $risks,
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

    private function projectFormData(): array
    {
        $delivery = (new ConfigService())->getConfig()['delivery'] ?? ['methodologies' => [], 'phases' => [], 'risks' => []];
        $masterRepo = new MasterFilesRepository($this->db);
        $usersRepo = new UsersRepository($this->db);
        $clientsRepo = new ClientsRepository($this->db);
        $user = $this->auth->user() ?? [];

        $catalogs = $this->projectCatalogs($masterRepo);
        $projectManagers = array_filter(
            $usersRepo->all(),
            fn ($candidate) => ($candidate['active'] ?? 0) == 1 && in_array($candidate['role_name'] ?? '', ['Administrador', 'PMO', 'Líder de Proyecto'], true)
        );
        $clients = $clientsRepo->listForUser($user);
        $defaultMethodology = $delivery['methodologies'][0] ?? 'scrum';
        $defaultPhase = $delivery['phases'][$defaultMethodology][0] ?? null;

        return [
            'delivery' => $delivery,
            'clients' => $clients,
            'projectManagers' => $projectManagers,
            'priorities' => $catalogs['priorities'],
            'statuses' => $catalogs['statuses'],
            'healthCatalog' => $catalogs['health'],
            'defaults' => [
                'status' => $catalogs['statuses'][0]['code'] ?? 'ideation',
                'health' => $catalogs['health'][0]['code'] ?? 'on_track',
                'priority' => $catalogs['priorities'][0]['code'] ?? 'medium',
                'methodology' => $defaultMethodology,
                'phase' => $defaultPhase,
                'pm_id' => $this->defaultPmId($projectManagers, $usersRepo),
                'project_type' => 'convencional',
            ],
            'canCreate' => !empty($clients) && !empty($projectManagers),
        ];
    }

    private function projectCatalogs(MasterFilesRepository $masterRepo): array
    {
        $safeList = static function (callable $callback, array $fallback): array {
            try {
                $items = $callback();
                return $items ?: $fallback;
            } catch (\Throwable) {
                return $fallback;
            }
        };

        return [
            'priorities' => $safeList(fn () => $masterRepo->list('priorities'), [
                ['code' => 'high', 'label' => 'Alta'],
                ['code' => 'medium', 'label' => 'Media'],
                ['code' => 'low', 'label' => 'Baja'],
            ]),
            'statuses' => $safeList(fn () => $masterRepo->list('project_status'), [
                ['code' => 'ideation', 'label' => 'Ideación'],
                ['code' => 'execution', 'label' => 'Ejecución'],
                ['code' => 'closed', 'label' => 'Cerrado'],
            ]),
            'health' => $safeList(fn () => $masterRepo->list('project_health'), [
                ['code' => 'on_track', 'label' => 'En curso'],
                ['code' => 'at_risk', 'label' => 'En riesgo'],
                ['code' => 'critical', 'label' => 'Crítico'],
            ]),
        ];
    }

    private function validatedProjectPayload(array $delivery, array $catalogs, UsersRepository $usersRepo): array
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('El nombre del proyecto es obligatorio.');
        }

        $clientId = (int) ($_POST['client_id'] ?? 0);
        if ($clientId <= 0 || !(new ClientsRepository($this->db))->find($clientId)) {
            throw new \InvalidArgumentException('Selecciona un cliente válido para el proyecto.');
        }

        $pmId = (int) ($_POST['pm_id'] ?? 0);
        if (!$usersRepo->isValidProjectManager($pmId)) {
            $pmId = $usersRepo->firstAvailablePmId() ?? 0;
        }
        if ($pmId <= 0) {
            throw new \InvalidArgumentException('Selecciona un PM válido para el proyecto.');
        }

        $projectType = trim((string) ($_POST['project_type'] ?? 'convencional')) ?: 'convencional';
        $status = $this->validatedCatalogValue((string) ($_POST['status'] ?? ''), $catalogs['statuses'], 'estado', $catalogs['statuses'][0]['code'] ?? 'ideation');
        $health = $this->validatedCatalogValue((string) ($_POST['health'] ?? ''), $catalogs['health'], 'salud', $catalogs['health'][0]['code'] ?? 'on_track');
        $priority = $this->validatedCatalogValue((string) ($_POST['priority'] ?? ''), $catalogs['priorities'], 'prioridad', $catalogs['priorities'][0]['code'] ?? 'medium');

        $methodology = trim((string) ($_POST['methodology'] ?? ($delivery['methodologies'][0] ?? 'scrum')));
        $methodology = in_array($methodology, $delivery['methodologies'] ?? [], true)
            ? $methodology
            : ($delivery['methodologies'][0] ?? 'scrum');

        $phases = is_array($delivery['phases'][$methodology] ?? null) ? $delivery['phases'][$methodology] : [];
        $phaseInput = trim((string) ($_POST['phase'] ?? ''));
        $phase = $phaseInput !== '' && in_array($phaseInput, $phases, true)
            ? $phaseInput
            : ($phases[0] ?? null);

        $risksCatalog = array_filter(array_map(fn ($risk) => (string) ($risk['code'] ?? ''), $delivery['risks'] ?? []));
        $risks = array_values(array_filter(
            $_POST['risks'] ?? [],
            fn ($risk) => in_array((string) $risk, $risksCatalog, true)
        ));

        return [
            'client_id' => $clientId,
            'pm_id' => $pmId,
            'name' => $name,
            'status' => $status,
            'health' => $health,
            'priority' => $priority,
            'project_type' => $projectType,
            'methodology' => $methodology,
            'phase' => $phase,
            'budget' => $this->floatOrZero($_POST['budget'] ?? null),
            'actual_cost' => $this->floatOrZero($_POST['actual_cost'] ?? null),
            'planned_hours' => $this->floatOrZero($_POST['planned_hours'] ?? null),
            'actual_hours' => $this->floatOrZero($_POST['actual_hours'] ?? null),
            'progress' => $this->floatOrZero($_POST['progress'] ?? null),
            'start_date' => $this->nullableDate($_POST['start_date'] ?? null),
            'end_date' => $this->nullableDate($_POST['end_date'] ?? null),
            'risks' => $risks,
        ];
    }

    private function validatedCatalogValue(string $value, array $catalog, string $fieldLabel, string $default): string
    {
        $trimmed = trim($value);
        $codes = array_column($catalog, 'code');

        if ($trimmed !== '' && in_array($trimmed, $codes, true)) {
            return $trimmed;
        }

        if ($default !== '' && in_array($default, $codes, true)) {
            return $default;
        }

        throw new \InvalidArgumentException('Selecciona un valor válido para ' . $fieldLabel . '.');
    }

    private function floatOrZero($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    private function nullableDate($value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function defaultPmId(array $projectManagers, UsersRepository $usersRepo): ?int
    {
        if (!empty($projectManagers)) {
            return (int) ($projectManagers[0]['id'] ?? null);
        }

        return $usersRepo->firstAvailablePmId();
    }
}
