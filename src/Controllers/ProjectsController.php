<?php

declare(strict_types=1);

class ProjectsController extends Controller
{
    private const FINAL_STATUSES = ['closed', 'finalizado', 'finalized', 'cerrado'];
    private const LIFECYCLE_STATUSES = ['planning', 'execution', 'on_hold', 'closing', 'closed'];
    private const ALLOWED_TRANSITIONS = [
        'planning' => ['planning', 'execution'],
        'execution' => ['execution', 'on_hold', 'closing'],
        'on_hold' => ['on_hold', 'execution', 'closing'],
        'closing' => ['closing', 'closed'],
        'closed' => ['closed'],
    ];

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
        $this->render('projects/show', $this->projectDetailData($id));
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
        $hasTasks = $repo->hasTasks($id);

        $this->render('projects/edit', [
            'title' => 'Editar proyecto',
            'project' => $project,
            'delivery' => array_merge(
                $config['delivery'] ?? [],
                [
                    'risks' => $this->riskCatalogForType(
                        $config['delivery']['risks'] ?? [],
                        (string) ($project['project_type'] ?? 'convencional'),
                        (string) ($project['methodology'] ?? 'cascada')
                    ),
                ]
            ),
            'hasTasks' => $hasTasks,
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
        $delivery = $config['delivery'] ?? [];
        $hasTasks = $repo->hasTasks($id);
        $auditRepo = new AuditLogRepository($this->db);
        $previousEvaluations = $repo->riskEvaluationsForProject($id);

        try {
            $payload = $this->projectPayload($project, $delivery);
            $riskCatalog = $payload['risk_catalog'] ?? $this->riskCatalogForType(
                $delivery['risks'] ?? [],
                (string) ($payload['project_type'] ?? ($project['project_type'] ?? 'convencional')),
                (string) ($payload['methodology'] ?? ($project['methodology'] ?? 'cascada'))
            );
            $payload = $this->applyLifecycleGovernance($project, $payload, $delivery, $repo, $riskCatalog);
            unset($payload['risk_catalog']);
            $repo->updateProject($id, $payload, (int) ($this->auth->user()['id'] ?? 0));
            $this->logRiskAudit($auditRepo, $project['id'], $previousEvaluations, $payload['risk_evaluations'] ?? []);
            $this->logProjectChange($auditRepo, $project, $payload);

            header('Location: /project/public/projects/' . $id);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            $this->render('projects/edit', [
                'title' => 'Editar proyecto',
                'project' => $project,
                'delivery' => array_merge(
                    $delivery ?? [],
                    [
                        'risks' => $this->riskCatalogForType(
                            $delivery['risks'] ?? [],
                            (string) ($project['project_type'] ?? 'convencional'),
                            (string) ($project['methodology'] ?? 'cascada')
                        ),
                    ]
                ),
                'error' => $e->getMessage(),
                'hasTasks' => $hasTasks,
            ]);
        }
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
        $delivery = (new ConfigService($this->db))->getConfig()['delivery'] ?? [];
        $masterRepo = new MasterFilesRepository($this->db);
        $usersRepo = new UsersRepository($this->db);
        $repo = new ProjectsRepository($this->db);
        $auditRepo = new AuditLogRepository($this->db);

        try {
            $payload = $this->validatedProjectPayload($delivery, $this->projectCatalogs($masterRepo), $usersRepo);
            $payload = array_merge($payload, [
                'design_inputs_defined' => 0,
                'design_review_done' => 0,
                'design_verification_done' => 0,
                'design_validation_done' => 0,
                'client_participation' => 0,
                'legal_requirements' => 0,
                'change_control_required' => 0,
            ]);
            unset($payload['risk_catalog']);
            $projectId = $repo->create($payload);
            $this->logRiskAudit($auditRepo, $projectId, [], $payload['risk_evaluations'] ?? []);
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

        $config = (new ConfigService($this->db))->getConfig();
        $delivery = $config['delivery'] ?? [];
        $riskCatalog = $this->riskCatalogForType(
            $delivery['risks'] ?? [],
            (string) ($project['project_type'] ?? 'convencional'),
            (string) ($project['methodology'] ?? 'cascada')
        );
        $closingPayload = [
            'status' => 'closed',
            'progress' => 100.0,
            'actual_cost' => (float) ($project['actual_cost'] ?? 0),
            'budget' => (float) ($project['budget'] ?? 0),
            'actual_hours' => (float) ($project['actual_hours'] ?? 0),
            'planned_hours' => (float) ($project['planned_hours'] ?? 0),
            'project_type' => $project['project_type'] ?? 'convencional',
            'methodology' => $project['methodology'] ?? 'cascada',
            'phase' => $project['phase'] ?? null,
            'end_date' => $project['end_date'] ?? null,
            'start_date' => $project['start_date'] ?? null,
            'risks' => $project['risks'] ?? [],
            'risk_score' => $project['risk_score'] ?? null,
        ];

        try {
            $governed = $this->applyLifecycleGovernance($project, $closingPayload, $delivery, $repo, $riskCatalog);
            $repo->closeProject($id, $governed['health'], $governed['risk_level'] ?? null);
            header('Location: /project/public/projects/' . $id);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        }
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

    public function storeDesignInput(int $projectId): void
    {
        $this->requirePermission('projects.manage');

        try {
            $repo = new DesignInputsRepository($this->db);
            $repo->create([
                'project_id' => $projectId,
                'input_type' => $_POST['input_type'] ?? '',
                'description' => $_POST['description'] ?? '',
                'source' => $_POST['source'] ?? null,
                'resolved_conflict' => isset($_POST['resolved_conflict']) ? 1 : 0,
            ], (int) ($this->auth->user()['id'] ?? 0));

            header('Location: /project/public/projects/' . $projectId);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            $this->render('projects/show', array_merge(
                $this->projectDetailData($projectId),
                ['designInputError' => $e->getMessage()]
            ));
        }
    }

    public function deleteDesignInput(int $projectId, int $inputId): void
    {
        $this->requirePermission('projects.manage');

        try {
            $repo = new DesignInputsRepository($this->db);
            $repo->delete($inputId, $projectId, (int) ($this->auth->user()['id'] ?? 0));
            header('Location: /project/public/projects/' . $projectId);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            $this->render('projects/show', array_merge(
                $this->projectDetailData($projectId),
                ['designInputError' => $e->getMessage()]
            ));
        }
    }

    public function storeDesignControl(int $projectId): void
    {
        $this->requirePermission('projects.manage');

        try {
            $repo = new DesignControlsRepository($this->db);
            $repo->create([
                'project_id' => $projectId,
                'control_type' => $_POST['control_type'] ?? '',
                'description' => $_POST['description'] ?? '',
                'result' => $_POST['result'] ?? '',
                'corrective_action' => $_POST['corrective_action'] ?? null,
                'performed_by' => (int) ($_POST['performed_by'] ?? 0),
                'performed_at' => $_POST['performed_at'] ?? null,
            ], (int) ($this->auth->user()['id'] ?? 0));

            header('Location: /project/public/projects/' . $projectId);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            $this->render('projects/show', array_merge(
                $this->projectDetailData($projectId),
                ['designControlError' => $e->getMessage()]
            ));
        }
    }

    public function updateDesignOutputs(int $projectId): void
    {
        $this->requirePermission('projects.manage');
        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $project = $repo->findForUser($projectId, $user);

        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        try {
            $payload = [
                'name' => $project['name'],
                'status' => $project['status'],
                'health' => $project['health'],
                'priority' => $project['priority'],
                'pm_id' => (int) ($project['pm_id'] ?? 0),
                'project_type' => (string) ($project['project_type'] ?? 'convencional'),
                'methodology' => (string) ($project['methodology'] ?? 'cascada'),
                'phase' => $project['phase'] ?? null,
                'budget' => (float) ($project['budget'] ?? 0),
                'actual_cost' => (float) ($project['actual_cost'] ?? 0),
                'planned_hours' => (float) ($project['planned_hours'] ?? 0),
                'actual_hours' => (float) ($project['actual_hours'] ?? 0),
                'progress' => (float) ($project['progress'] ?? 0),
                'scope' => (string) ($project['scope'] ?? ''),
                'design_inputs' => (string) ($project['design_inputs'] ?? ''),
                'client_participation' => (int) ($project['client_participation'] ?? 0),
                'start_date' => $project['start_date'] ?? null,
                'end_date' => $project['end_date'] ?? null,
                'design_review_done' => isset($_POST['design_review_done']) ? 1 : 0,
                'design_verification_done' => isset($_POST['design_verification_done']) ? 1 : 0,
                'design_validation_done' => isset($_POST['design_validation_done']) ? 1 : 0,
            ];

            $repo->updateProject($projectId, $payload, (int) ($this->auth->user()['id'] ?? 0));
            header('Location: /project/public/projects/' . $projectId);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            $this->render('projects/show', array_merge(
                $this->projectDetailData($projectId),
                ['designOutputError' => $e->getMessage()]
            ));
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

    private function projectDetailData(int $id): array
    {
        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $project = $repo->findForUser($id, $user);

        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        $assignments = $repo->assignmentsForProject($id, $user);
        $designInputsRepo = new DesignInputsRepository($this->db);
        $designControlsRepo = new DesignControlsRepository($this->db);
        $users = (new UsersRepository($this->db))->all();

        return [
            'title' => 'Detalle de proyecto',
            'project' => $project,
            'assignments' => $assignments,
            'canManage' => $this->auth->can('projects.manage'),
            'designInputs' => $designInputsRepo->listByProject($id),
            'designInputTypes' => $designInputsRepo->allowedTypes(),
            'designControls' => $designControlsRepo->listByProject($id),
            'designControlTypes' => $designControlsRepo->allowedTypes(),
            'designControlResults' => $designControlsRepo->allowedResults(),
            'performers' => array_values(array_filter($users, fn ($candidate) => (int) ($candidate['active'] ?? 0) === 1)),
        ];
    }

    private function isLocalEnvironment(): bool
    {
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?? 'production';

        return strtolower((string) $env) === 'local';
    }

    private function projectPayload(array $current, array $deliveryConfig = []): array
    {
        $allowedProjectTypes = ['convencional', 'scrum', 'hibrido'];
        $projectType = $_POST['project_type'] ?? (string) ($current['project_type'] ?? 'convencional');
        $projectType = in_array($projectType, $allowedProjectTypes, true) ? $projectType : 'convencional';

        $delivery = [
            'methodologies' => $deliveryConfig['methodologies'] ?? [],
            'phases' => $deliveryConfig['phases'] ?? [],
            'risks' => [],
        ];

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

        $delivery['risks'] = $this->riskCatalogForType($deliveryConfig['risks'] ?? [], $projectType, $methodology);
        $riskAssessment = $this->assessRisks($_POST['risks'] ?? [], $delivery['risks']);
        $riskScore = $riskAssessment['score'];
        $riskLevel = $this->riskLevelFromScore($riskScore);

        $endDate = $_POST['end_date'] ?? ($current['end_date'] ?? null);
        if ($projectType === 'scrum') {
            $endDate = null;
        }

        $scope = trim((string) ($_POST['scope'] ?? ($current['scope'] ?? '')));

        return [
            'name' => trim($_POST['name'] ?? (string) ($current['name'] ?? '')),
            'status' => $_POST['status'] ?? (string) ($current['status'] ?? ''),
            'health' => (string) ($current['health'] ?? ''),
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
            'scope' => $scope,
            'risks' => $riskAssessment['selected'],
            'risk_evaluations' => $riskAssessment['evaluations'],
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'risk_catalog' => $delivery['risks'],
        ];
    }

    private function applyLifecycleGovernance(array $current, array $payload, array $delivery, ProjectsRepository $repo, array $riskCatalog = []): array
    {
        $payload = $this->applyMethodologyRules($payload, $delivery['phases'] ?? [], $current);

        $requestedStatus = $this->normalizeStatus($payload['status'] ?? ($current['status'] ?? 'planning'));
        $currentStatus = $this->normalizeStatus((string) ($current['status'] ?? 'planning'));
        if ($requestedStatus === '') {
            $requestedStatus = $currentStatus;
        }

        if (!$this->isValidStatus($requestedStatus)) {
            throw new \InvalidArgumentException('Estado de proyecto no permitido.');
        }

        if (!$this->isAllowedTransition($currentStatus, $requestedStatus)) {
            throw new \InvalidArgumentException('Transición de estado no permitida: ' . $currentStatus . ' → ' . $requestedStatus . '.');
        }

        if (in_array($requestedStatus, ['closing', 'closed'], true)) {
            $openTasks = $repo->openTasksCount((int) $current['id']);
            if ($openTasks > 0) {
                throw new \InvalidArgumentException('No puedes cerrar un proyecto con tareas abiertas (' . $openTasks . ').');
            }
        }

        if ($repo->hasTasks((int) $current['id']) && ($payload['methodology'] ?? '') !== ($current['methodology'] ?? '')) {
            throw new \InvalidArgumentException('No puedes cambiar la metodología porque ya existen tareas asociadas.');
        }

        $effectiveRiskCatalog = $riskCatalog ?: $this->riskCatalogForType(
            $delivery['risks'] ?? [],
            (string) ($payload['project_type'] ?? ($current['project_type'] ?? 'convencional')),
            (string) ($payload['methodology'] ?? ($current['methodology'] ?? 'cascada'))
        );
        $riskAssessment = $this->assessRisks($payload['risks'] ?? [], $effectiveRiskCatalog);

        $payload['status'] = $requestedStatus;
        $payload['risks'] = $riskAssessment['selected'];
        $payload['risk_evaluations'] = $riskAssessment['evaluations'];
        $payload['risk_score'] = $riskAssessment['score'];
        $payload['risk_level'] = $this->riskLevelFromScore($payload['risk_score']);
        $payload['health'] = $this->calculateHealthScore([
            'risk_score' => $payload['risk_score'],
            'risk_level' => $payload['risk_level'],
            'progress' => $payload['progress'],
            'actual_hours' => $payload['actual_hours'],
            'planned_hours' => $payload['planned_hours'],
            'actual_cost' => $payload['actual_cost'],
            'budget' => $payload['budget'],
        ]);

        return $payload;
    }

    private function applyMethodologyRules(array $payload, array $phasesByMethodology, array $current = []): array
    {
        $projectType = $payload['project_type'] ?? ($current['project_type'] ?? 'convencional');
        $methodology = $payload['methodology'] ?? ($current['methodology'] ?? 'cascada');
        $phases = is_array($phasesByMethodology[$methodology] ?? null) ? $phasesByMethodology[$methodology] : [];
        $payload['phase'] = $this->validatedPhaseTransition(
            $payload['phase'] ?? ($current['phase'] ?? null),
            $phases,
            $current['phase'] ?? null,
            in_array($projectType, ['convencional', 'hibrido'], true)
        );

        if ($projectType === 'scrum') {
            $payload['end_date'] = null;
        } elseif ($projectType === 'convencional') {
            if ($payload['end_date'] === null || $payload['end_date'] === '') {
                throw new \InvalidArgumentException('Los proyectos convencionales requieren fecha de fin.');
            }
        } else {
            $startDate = $payload['start_date'] ?? null;
            $endDate = $payload['end_date'] ?? null;
            if ($startDate && $endDate && $endDate < $startDate) {
                throw new \InvalidArgumentException('La fecha fin no puede ser anterior a la fecha de inicio.');
            }
        }

        return $payload;
    }

    private function validatedPhaseTransition(?string $requested, array $phases, ?string $current, bool $enforceSequence): ?string
    {
        if ($requested === null || $requested === '') {
            $requested = $current ?? ($phases[0] ?? null);
        }

        if ($requested !== null && !in_array($requested, $phases, true)) {
            return $current ?? ($phases[0] ?? null);
        }

        if ($enforceSequence && $requested !== null && $current !== null) {
            $currentIndex = array_search($current, $phases, true);
            $requestedIndex = array_search($requested, $phases, true);
            if ($currentIndex !== false && $requestedIndex !== false && $requestedIndex < $currentIndex) {
                throw new \InvalidArgumentException('Las fases deben avanzar de forma secuencial.');
            }
        }

        return $requested;
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        if (in_array($normalized, self::FINAL_STATUSES, true)) {
            return 'closed';
        }

        return $normalized;
    }

    private function isValidStatus(string $status): bool
    {
        return in_array($status, self::LIFECYCLE_STATUSES, true);
    }

    private function isAllowedTransition(string $current, string $next): bool
    {
        $from = $this->isValidStatus($current) ? $current : 'planning';
        $allowed = self::ALLOWED_TRANSITIONS[$from] ?? ['planning'];

        return in_array($next, $allowed, true);
    }

    private function calculateHealthScore(array $metrics): string
    {
        $riskLevel = $metrics['risk_level'] ?? $this->riskLevelFromScore((float) ($metrics['risk_score'] ?? 0));
        $progress = (float) ($metrics['progress'] ?? 0);
        $hoursDeviation = $this->deviationPercent((float) ($metrics['actual_hours'] ?? 0), (float) ($metrics['planned_hours'] ?? 0));
        $costDeviation = $this->deviationPercent((float) ($metrics['actual_cost'] ?? 0), (float) ($metrics['budget'] ?? 0));
        $hasTrackingBaselines = ((float) ($metrics['planned_hours'] ?? 0) > 0) || ((float) ($metrics['budget'] ?? 0) > 0);
        $progressCheckEnabled = $hasTrackingBaselines || $progress > 0;
        $criticalDeviation = ($hoursDeviation !== null && $hoursDeviation > 0.15) || ($costDeviation !== null && $costDeviation > 0.15);
        $warningDeviation = ($hoursDeviation !== null && $hoursDeviation > 0.08) || ($costDeviation !== null && $costDeviation > 0.08);

        if ($riskLevel === 'alto') {
            if ($progress < 50 || $criticalDeviation) {
                return 'critical';
            }

            return 'at_risk';
        }

        if ($riskLevel === 'medio') {
            if ($progress < 25 || $criticalDeviation) {
                return 'critical';
            }

            return 'at_risk';
        }

        if ($criticalDeviation) {
            return 'critical';
        }

        if ($progressCheckEnabled) {
            if ($progress < 25) {
                return 'critical';
            }

            if ($progress < 50 || $warningDeviation) {
                return 'at_risk';
            }
        }

        if ($warningDeviation) {
            return 'at_risk';
        }

        return 'on_track';
    }

    private function riskLevelFromScore(float $score): string
    {
        if ($score > 30) {
            return 'alto';
        }

        if ($score >= 16) {
            return 'medio';
        }

        return 'bajo';
    }

    private function assessRisks(array $inputRisks, array $riskCatalog): array
    {
        $severityIndex = $this->riskSeverityIndex($riskCatalog);
        $availableCodes = array_keys($severityIndex);
        $submittedRisks = is_array($inputRisks) ? $inputRisks : [$inputRisks];

        if (empty($availableCodes)) {
            throw new \InvalidArgumentException('No hay riesgos activos para la metodología seleccionada.');
        }

        $selected = array_values(array_unique(array_intersect(array_map('strval', $submittedRisks), $availableCodes)));
        if (empty($selected)) {
            throw new \InvalidArgumentException('Debes seleccionar al menos un riesgo del catálogo.');
        }

        $evaluations = [];
        foreach ($availableCodes as $code) {
            $evaluations[] = [
                'risk_code' => $code,
                'selected' => in_array($code, $selected, true) ? 1 : 0,
            ];
        }

        return [
            'selected' => $selected,
            'evaluations' => $evaluations,
            'score' => $this->calculateRiskScore($selected, $severityIndex),
        ];
    }

    private function calculateRiskScore(array $selectedRisks, array $severityIndex): float
    {
        $score = 0.0;
        $unique = array_values(array_unique(array_filter(array_map('strval', $selectedRisks), fn ($risk) => $risk !== '')));

        foreach ($unique as $risk) {
            $score += (float) ($severityIndex[$risk] ?? 0);
        }

        return $score;
    }

    private function riskSeverityIndex(array $riskCatalog): array
    {
        $index = [];

        foreach ($riskCatalog as $risk) {
            $code = trim((string) ($risk['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $index[$code] = (float) ($risk['severity_base'] ?? 0);
        }

        return $index;
    }

    private function logRiskAudit(AuditLogRepository $auditRepo, int $projectId, array $beforeEvaluations, array $afterEvaluations): void
    {
        $before = $this->normalizedRiskEvaluations($beforeEvaluations);
        $after = $this->normalizedRiskEvaluations($afterEvaluations);

        if ($before === $after) {
            return;
        }

        try {
            $auditRepo->log(
                (int) ($this->auth->user()['id'] ?? 0),
                'project_risk',
                $projectId,
                'update',
                [
                    'before' => $before,
                    'after' => $after,
                ]
            );
        } catch (\Throwable $e) {
            error_log('No se pudo registrar el cambio de riesgos: ' . $e->getMessage());
        }
    }

    private function logProjectChange(AuditLogRepository $auditRepo, array $before, array $after): void
    {
        $fields = ['scope', 'budget', 'start_date', 'end_date', 'methodology', 'phase'];
        $beforePayload = [];
        $afterPayload = [];

        foreach ($fields as $field) {
            if (!array_key_exists($field, $after)) {
                continue;
            }

            $previousValue = $before[$field] ?? null;
            $newValue = $after[$field];

            if ($previousValue != $newValue) {
                $beforePayload[$field] = $previousValue;
                $afterPayload[$field] = $newValue;
            }
        }

        if (empty($beforePayload)) {
            return;
        }

        try {
            $auditRepo->log(
                (int) ($this->auth->user()['id'] ?? 0),
                'project',
                (int) ($before['id'] ?? 0),
                'project.change',
                [
                    'before' => $beforePayload,
                    'after' => $afterPayload,
                ]
            );
        } catch (\Throwable $e) {
            error_log('No se pudo registrar el control de cambios del proyecto: ' . $e->getMessage());
        }
    }

    private function normalizedRiskEvaluations(array $evaluations): array
    {
        $normalized = array_values(array_filter(array_map(function ($evaluation) {
            $code = trim((string) ($evaluation['risk_code'] ?? ($evaluation['code'] ?? '')));
            if ($code === '') {
                return null;
            }

            return [
                'risk_code' => $code,
                'selected' => (int) (($evaluation['selected'] ?? 0) === 1),
            ];
        }, $evaluations), fn ($evaluation) => $evaluation !== null));

        usort($normalized, fn ($a, $b) => strcmp($a['risk_code'], $b['risk_code']));

        return $normalized;
    }

    private function riskAppliesToContext(string $projectType, ?string $methodology = null): string
    {
        $normalizedMethodology = strtolower(trim((string) $methodology));
        if (in_array($normalizedMethodology, ['scrum', 'kanban'], true)) {
            return 'scrum';
        }

        $normalizedType = strtolower(trim($projectType));
        if ($normalizedType === 'scrum') {
            return 'scrum';
        }

        return 'convencional';
    }

    private function deviationPercent(float $actual, float $planned): ?float
    {
        if ($planned <= 0.0) {
            return null;
        }

        return ($actual - $planned) / $planned;
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
        $delivery = (new ConfigService($this->db))->getConfig()['delivery'] ?? ['methodologies' => [], 'phases' => [], 'risks' => []];
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
        $defaultMethodology = $this->methodologyForType('convencional', $delivery['methodologies'] ?? []);
        $defaultPhase = $this->phaseForMethodology($defaultMethodology, $this->initialPhaseForMethodology($defaultMethodology, $delivery['phases'] ?? []));
        $delivery['risks'] = $this->riskCatalogForType($delivery['risks'] ?? [], 'convencional', $defaultMethodology);
        $statusesForCreation = $this->creatableStatuses($catalogs['statuses']) ?: $catalogs['statuses'];
        $initialStatus = 'planning';
        $initialHealth = 'on_track';

        return [
            'delivery' => $delivery,
            'clients' => $clients,
            'projectManagers' => $projectManagers,
            'priorities' => $catalogs['priorities'],
            'statuses' => $statusesForCreation,
            'healthCatalog' => $catalogs['health'],
            'defaults' => [
                'status' => $initialStatus,
                'health' => $initialHealth,
                'priority' => $catalogs['priorities'][0]['code'] ?? 'medium',
                'methodology' => $defaultMethodology,
                'phase' => $defaultPhase,
                'pm_id' => $this->defaultPmId($projectManagers, $usersRepo),
                'project_type' => 'convencional',
                'scope' => '',
                'design_inputs' => '',
                'client_participation' => 'media',
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
                ['code' => 'planning', 'label' => 'Planeación'],
                ['code' => 'execution', 'label' => 'Ejecución'],
                ['code' => 'on_hold', 'label' => 'En pausa'],
                ['code' => 'closing', 'label' => 'Cierre'],
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

        $allowedProjectTypes = ['convencional', 'scrum', 'hibrido'];
        $projectTypeInput = trim((string) ($_POST['project_type'] ?? 'convencional')) ?: 'convencional';
        $projectType = in_array($projectTypeInput, $allowedProjectTypes, true) ? $projectTypeInput : 'convencional';
        $status = 'planning';
        $priority = $this->validatedCatalogValue((string) ($_POST['priority'] ?? ''), $catalogs['priorities'], 'prioridad', $catalogs['priorities'][0]['code'] ?? 'medium');

        $methodology = $this->methodologyForType($projectType, $delivery['methodologies'] ?? []);

        $phase = $this->initialPhaseForMethodology($methodology, $delivery['phases'] ?? []);

        $filteredRisks = $this->riskCatalogForType($delivery['risks'] ?? [], $projectType, $methodology);
        $riskAssessment = $this->assessRisks($_POST['risks'] ?? [], $filteredRisks);

        $scope = trim((string) ($_POST['scope'] ?? ''));
        if ($scope === '') {
            throw new \InvalidArgumentException('El alcance del proyecto es obligatorio desde el paso 1.');
        }

        $designInputs = trim((string) ($_POST['design_inputs'] ?? ''));
        if ($designInputs === '') {
            throw new \InvalidArgumentException('Las entradas de diseño iniciales son obligatorias.');
        }

        $clientParticipation = $this->validatedClientParticipation((string) ($_POST['client_participation'] ?? ''));

        if ($projectType === 'convencional') {
            $endDate = $this->nullableDate($_POST['end_date'] ?? null);
            if ($endDate === null) {
                throw new \InvalidArgumentException('La fecha de fin es obligatoria para proyectos convencionales.');
            }
        } else {
            $endDate = $this->nullableDate($_POST['end_date'] ?? null);
        }

        $budget = $this->floatOrZero($_POST['budget'] ?? null);
        $actualCost = $this->floatOrZero($_POST['actual_cost'] ?? null);
        $plannedHours = $this->floatOrZero($_POST['planned_hours'] ?? null);
        $actualHours = $this->floatOrZero($_POST['actual_hours'] ?? null);
        $progress = $this->floatOrZero($_POST['progress'] ?? null);
        $riskScore = $riskAssessment['score'];
        $riskLevel = $this->riskLevelFromScore($riskScore);
        $health = $this->calculateHealthScore([
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'progress' => $progress,
            'actual_hours' => $actualHours,
            'planned_hours' => $plannedHours,
            'actual_cost' => $actualCost,
            'budget' => $budget,
        ]);

        $payload = [
            'client_id' => $clientId,
            'pm_id' => $pmId,
            'name' => $name,
            'status' => $status,
            'health' => $health,
            'priority' => $priority,
            'project_type' => $projectType,
            'methodology' => $methodology,
            'phase' => $this->phaseForMethodology($methodology, $phase),
            'scope' => $scope,
            'design_inputs' => $designInputs,
            'client_participation' => $clientParticipation,
            'budget' => $budget,
            'actual_cost' => $actualCost,
            'planned_hours' => $plannedHours,
            'actual_hours' => $actualHours,
            'progress' => $progress,
            'start_date' => $this->nullableDate($_POST['start_date'] ?? null),
            'end_date' => $endDate,
            'risks' => $riskAssessment['selected'],
            'risk_evaluations' => $riskAssessment['evaluations'],
            'risk_catalog' => $filteredRisks,
        ];

        $payload = $this->applyMethodologyRules($payload, $delivery['phases'] ?? []);
        $payload['risk_score'] = $riskScore;
        $payload['risk_level'] = $riskLevel;
        $payload['health'] = $health;

        return $payload;
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

    private function riskCatalogForType(array $risks, string $projectType, ?string $methodology = null): array
    {
        $normalizedContext = $this->riskAppliesToContext($projectType, $methodology);

        return array_values(array_filter($risks, function ($risk) use ($normalizedContext) {
            $active = (int) ($risk['active'] ?? 1) === 1;
            if (!$active) {
                return false;
            }
            $appliesTo = strtolower((string) ($risk['applies_to'] ?? 'ambos'));
            return $appliesTo === 'ambos' || $appliesTo === $normalizedContext;
        }));
    }

    private function defaultPmId(array $projectManagers, UsersRepository $usersRepo): ?int
    {
        if (!empty($projectManagers)) {
            return (int) ($projectManagers[0]['id'] ?? null);
        }

        return $usersRepo->firstAvailablePmId();
    }

    private function methodologyForType(string $projectType, array $availableMethodologies): string
    {
        $preferred = match ($projectType) {
            'scrum' => 'scrum',
            'hibrido' => 'kanban',
            default => 'cascada',
        };

        if (in_array($preferred, $availableMethodologies, true)) {
            return $preferred;
        }

        return $availableMethodologies[0] ?? ($preferred ?: 'scrum');
    }

    private function phaseForMethodology(string $methodology, ?string $fallback = null): ?string
    {
        return match ($methodology) {
            'scrum' => 'descubrimiento',
            'convencional' => 'inicio',
            default => $fallback,
        };
    }

    private function initialPhaseForMethodology(string $methodology, array $phasesByMethodology): ?string
    {
        $phases = is_array($phasesByMethodology[$methodology] ?? null) ? $phasesByMethodology[$methodology] : [];

        return $phases[0] ?? null;
    }

    private function creatableStatuses(array $catalog): array
    {
        return array_values(array_filter(
            $catalog,
            fn ($status) => !in_array($status['code'] ?? '', self::FINAL_STATUSES, true)
        ));
    }

    private function initialStatus(array $catalog, string $fallback): string
    {
        if (!empty($catalog)) {
            return (string) ($catalog[0]['code'] ?? $fallback);
        }

        return $fallback;
    }

    private function validatedClientParticipation(string $value): string
    {
        $normalized = strtolower(trim($value));
        $allowed = ['alta', 'media', 'baja'];

        if (in_array($normalized, $allowed, true)) {
            return $normalized;
        }

        return 'media';
    }

    private function calculateInitialHealth(array $healthCatalog, array $context): string
    {
        $healthCodes = array_column($healthCatalog, 'code');
        $default = in_array('on_track', $healthCodes, true)
            ? 'on_track'
            : ($healthCodes[0] ?? 'on_track');

        $riskCount = count($context['risks'] ?? []);
        $progress = (float) ($context['progress'] ?? 0);
        $budget = (float) ($context['budget'] ?? 0);
        $actualCost = (float) ($context['actual_cost'] ?? 0);
        $plannedHours = (float) ($context['planned_hours'] ?? 0);
        $actualHours = (float) ($context['actual_hours'] ?? 0);
        $clientParticipation = $context['client_participation'] ?? 'media';

        $deviation = static function (float $actual, float $planned): float {
            if ($planned <= 0) {
                return 0.0;
            }
            return ($actual - $planned) / $planned;
        };

        $costDeviation = $deviation($actualCost, $budget);
        $hoursDeviation = $deviation($actualHours, $plannedHours);

        if ($riskCount > 2 || $progress < 5 || $costDeviation > 0.1 || $hoursDeviation > 0.1) {
            return in_array('critical', $healthCodes, true) ? 'critical' : ($healthCodes[0] ?? $default);
        }

        if ($riskCount > 0 || $clientParticipation === 'baja') {
            return in_array('at_risk', $healthCodes, true) ? 'at_risk' : $default;
        }

        return $default;
    }
}
