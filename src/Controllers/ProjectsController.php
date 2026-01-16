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
    private const DOCUMENT_STATUSES = [
        'borrador',
        'en_revision',
        'revisado',
        'en_validacion',
        'validado',
        'en_aprobacion',
        'aprobado',
        'rechazado',
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
        $deleteContext = $this->projectDeletionContext($id, $repo);

        $this->render('projects/edit', array_merge([
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
        ], $deleteContext));
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
            $methodologyChanged = strtolower((string) ($project['methodology'] ?? '')) !== strtolower((string) ($payload['methodology'] ?? ($project['methodology'] ?? '')));
            $phaseChanged = trim((string) ($project['phase'] ?? '')) !== trim((string) ($payload['phase'] ?? ($project['phase'] ?? '')));
            $repo->updateProject($id, $payload, (int) ($this->auth->user()['id'] ?? 0));
            $this->logRiskAudit($auditRepo, $project['id'], $previousEvaluations, $payload['risk_evaluations'] ?? []);
            $this->logProjectChange($auditRepo, $project, $payload);

            if ($methodologyChanged || $phaseChanged) {
                (new ProjectNodesRepository($this->db))->markTreeOutdated($id);
            }

            header('Location: /project/public/projects/' . $id);
            return;
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
            return;
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

        try {
            error_log('inicio');
            $payload = $this->validatedProjectPayload($delivery, $this->projectCatalogs($masterRepo), $usersRepo);
            unset($payload['risk_catalog']);
            $projectId = $repo->create($payload);
            error_log('proyecto creado');
            (new ProjectTreeService($this->db))->bootstrapFreshTree(
                $projectId,
                (string) ($payload['methodology'] ?? 'cascada'),
                (int) ($this->auth->user()['id'] ?? 0)
            );
            error_log('estructura creada');
            error_log('retornando respuesta');
            header('Location: /project/public/projects/' . $projectId);
            return;
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            $this->render('projects/create', array_merge($this->projectFormData(), [
                'title' => 'Nuevo proyecto',
                'error' => $e->getMessage(),
                'old' => $_POST,
            ]));
            return;
        } catch (\PDOException $e) {
            error_log('Error al crear proyecto (DB): ' . $e->getMessage());
            http_response_code(500);
            $this->render('projects/create', array_merge($this->projectFormData(), [
                'title' => 'Nuevo proyecto',
                'error' => $this->formatExceptionForDisplay($e),
                'old' => $_POST,
            ]));
            return;
        } catch (\Throwable $e) {
            error_log('Error al crear proyecto: ' . $e->getMessage());
            http_response_code(500);
            $this->render('projects/create', array_merge($this->projectFormData(), [
                'title' => 'Nuevo proyecto',
                'error' => $this->formatExceptionForDisplay($e),
                'old' => $_POST,
            ]));
            return;
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

    public function tasks(int $id): void
    {
        $this->requirePermission('projects.view');
        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $project = $repo->findForUser($id, $user);

        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        $tasks = (new TasksRepository($this->db))->forProject($id, $user);

        $this->render('projects/tasks', [
            'title' => 'Tareas del proyecto',
            'project' => $project,
            'tasks' => $tasks,
        ]);
    }

    public function outsourcing(int $id): void
    {
        $this->requirePermission('projects.view');
        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $project = $repo->findForUser($id, $user);

        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        if (($project['project_type'] ?? '') !== 'outsourcing') {
            http_response_code(404);
            exit('El módulo de outsourcing no aplica para este proyecto.');
        }

        $assignments = $repo->assignmentsForProject($id, $user);
        $talents = (new TalentsRepository($this->db))->summary();
        $users = array_values(array_filter(
            (new UsersRepository($this->db))->all(),
            static fn (array $candidate): bool => (int) ($candidate['active'] ?? 0) === 1
        ));

        $outsourcingRepo = new OutsourcingRepository($this->db);
        $settings = $outsourcingRepo->settingsForProject($id);
        $followups = $outsourcingRepo->followupsForProject($id);
        $indicators = $outsourcingRepo->indicators($id, (string) ($settings['followup_frequency'] ?? 'monthly'));

        $nodesRepo = new ProjectNodesRepository($this->db);
        $projectNodes = $nodesRepo->treeWithFiles($id);
        $nodesById = $this->flattenProjectNodes($projectNodes);

        foreach ($followups as &$followup) {
            $nodeId = (int) ($followup['document_node_id'] ?? 0);
            $followup['document_node'] = $nodeId > 0 ? ($nodesById[$nodeId] ?? null) : null;
        }
        unset($followup);

        $this->render('projects/outsourcing', [
            'title' => 'Control de outsourcing',
            'project' => $project,
            'assignments' => $assignments,
            'talents' => $talents,
            'users' => $users,
            'settings' => $settings,
            'followups' => $followups,
            'indicators' => $indicators,
            'documentFlowConfig' => (new ConfigService($this->db))->getConfig()['document_flow'] ?? [],
            'currentUser' => $this->auth->user() ?? [],
            'canManage' => $this->auth->can('projects.manage'),
        ]);
    }

    public function updateOutsourcingSettings(int $id): void
    {
        $this->requirePermission('projects.manage');
        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $project = $repo->findForUser($id, $user);

        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        if (($project['project_type'] ?? '') !== 'outsourcing') {
            http_response_code(404);
            exit('El módulo de outsourcing no aplica para este proyecto.');
        }

        $frequency = (string) ($_POST['followup_frequency'] ?? 'monthly');

        try {
            $outsourcingRepo = new OutsourcingRepository($this->db);
            $outsourcingRepo->updateSettings($id, $frequency);

            (new AuditLogRepository($this->db))->log(
                (int) ($user['id'] ?? 0),
                'outsourcing_settings',
                $id,
                'updated',
                ['followup_frequency' => $frequency]
            );

            header('Location: /project/public/projects/' . $id . '/outsourcing');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        }
    }

    public function createOutsourcingFollowup(int $id): void
    {
        $this->requirePermission('projects.manage');
        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $project = $repo->findForUser($id, $user);

        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        if (($project['project_type'] ?? '') !== 'outsourcing') {
            http_response_code(404);
            exit('El módulo de outsourcing no aplica para este proyecto.');
        }

        $periodStart = trim((string) ($_POST['period_start'] ?? ''));
        $periodEnd = trim((string) ($_POST['period_end'] ?? ''));
        if ($periodStart === '' || $periodEnd === '') {
            http_response_code(400);
            exit('El periodo del seguimiento es obligatorio.');
        }
        if (strtotime($periodEnd) < strtotime($periodStart)) {
            http_response_code(400);
            exit('El periodo del seguimiento es inválido.');
        }

        $responsibleId = (int) ($_POST['responsible_user_id'] ?? 0);
        if ($responsibleId <= 0) {
            http_response_code(400);
            exit('Selecciona un responsable válido.');
        }

        try {
            $nodesRepo = new ProjectNodesRepository($this->db);
            $rootNode = $nodesRepo->findNodeByCode($id, 'ROOT');
            $rootNodeId = $rootNode ? (int) ($rootNode['id'] ?? 0) : null;

            $outsourcingRootId = $nodesRepo->ensureFolderPath($id, [
                [
                    'code' => 'OUTSOURCING',
                    'title' => 'Outsourcing',
                    'node_type' => ProjectTreeService::NODE_TYPE_FOLDER,
                ],
            ], $rootNodeId);

            $folderCode = 'OUTSOURCING-FU-' . date('YmdHis') . '-' . random_int(100, 999);
            $folderTitle = sprintf('Seguimiento %s a %s', $periodStart, $periodEnd);
            $documentNodeId = $nodesRepo->createNode([
                'project_id' => $id,
                'parent_id' => $outsourcingRootId,
                'code' => $folderCode,
                'title' => $folderTitle,
                'node_type' => ProjectTreeService::NODE_TYPE_FOLDER,
                'iso_clause' => null,
                'description' => 'Documentación de seguimiento outsourcing',
                'sort_order' => 0,
                'created_by' => (int) ($user['id'] ?? 0),
            ]);

            $outsourcingRepo = new OutsourcingRepository($this->db);
            $followupId = $outsourcingRepo->createFollowup([
                'project_id' => $id,
                'document_node_id' => $documentNodeId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'responsible_user_id' => $responsibleId,
                'service_status' => $_POST['service_status'] ?? '',
                'observations' => $_POST['observations'] ?? '',
                'decisions' => $_POST['decisions'] ?? '',
                'created_by' => (int) ($user['id'] ?? 0),
            ]);

            (new AuditLogRepository($this->db))->log(
                (int) ($user['id'] ?? 0),
                'outsourcing_followup',
                $followupId,
                'created',
                [
                    'project_id' => $id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'service_status' => $_POST['service_status'] ?? '',
                    'responsible_user_id' => $responsibleId,
                ]
            );

            header('Location: /project/public/projects/' . $id . '/outsourcing');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        } catch (\Throwable $e) {
            error_log('Error al crear seguimiento outsourcing: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo crear el seguimiento.');
        }
    }

    public function updateOutsourcingAssignmentStatus(int $projectId, int $assignmentId): void
    {
        $this->requirePermission('projects.manage');
        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $project = $repo->findForUser($projectId, $user);

        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        if (($project['project_type'] ?? '') !== 'outsourcing') {
            http_response_code(404);
            exit('El módulo de outsourcing no aplica para este proyecto.');
        }

        $status = (string) ($_POST['assignment_status'] ?? 'active');

        try {
            $repo->updateAssignmentStatus($projectId, $assignmentId, $status);

            (new AuditLogRepository($this->db))->log(
                (int) ($user['id'] ?? 0),
                'outsourcing_assignment',
                $assignmentId,
                'status_updated',
                ['status' => $status]
            );

            header('Location: /project/public/projects/' . $projectId . '/outsourcing');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        }
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
            'progress' => (float) ($project['progress'] ?? 0),
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
            return;
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        }
    }

    public function updateProgress(int $id): void
    {
        if (!$this->userCanUpdateProjectProgress()) {
            http_response_code(403);
            exit('Acción no permitida por permisos');
        }

        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $project = $repo->findForUser($id, $user);

        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        $progressInput = trim((string) ($_POST['progress'] ?? ''));
        $justification = trim((string) ($_POST['justification'] ?? ''));

        if ($progressInput === '' || !is_numeric($progressInput)) {
            http_response_code(400);
            exit('El avance debe ser un número entre 0 y 100.');
        }

        $progress = (float) $progressInput;
        if ($progress < 0 || $progress > 100) {
            http_response_code(400);
            exit('El avance debe estar entre 0 y 100.');
        }

        if ($justification === '') {
            http_response_code(400);
            exit('La justificación es obligatoria.');
        }

        $previousProgress = (float) ($project['progress'] ?? 0);
        $repo->persistProgress($id, $progress);

        $auditRepo = new AuditLogRepository($this->db);
        $auditRepo->log(
            $user['id'] ?? null,
            'project_progress',
            $id,
            'project_progress_updated',
            [
                'previous_progress' => $previousProgress,
                'new_progress' => $progress,
                'justification' => $justification,
            ]
        );

        header('Location: /project/public/projects/' . $id . '?progress=updated');
    }

    public function destroy(): void
    {
        if (!$this->canDeleteProjects()) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $projectId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $project = $repo->findForUser($projectId, $user);
        $dependencies = $repo->dependencySummary($projectId);
        $mathOperator = trim((string) ($_POST['math_operator'] ?? ''));
        $operand1 = isset($_POST['math_operand1']) ? (int) $_POST['math_operand1'] : 0;
        $operand2 = isset($_POST['math_operand2']) ? (int) $_POST['math_operand2'] : 0;
        $mathResult = trim((string) ($_POST['math_result'] ?? ''));
        $forceDelete = filter_var($_POST['force'] ?? $_POST['force_delete'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $isAdmin = $this->canForceDeleteProjects();
        $canInactivate = $this->canDeleteProjects();

        if ($projectId <= 0 || !$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        if (!in_array($mathOperator, ['+', '-'], true) || $operand1 < 1 || $operand1 > 10 || $operand2 < 1 || $operand2 > 10) {
            http_response_code(400);
            exit('La verificación matemática no es válida.');
        }

        $expected = $mathOperator === '+' ? $operand1 + $operand2 : $operand1 - $operand2;

        if ($mathResult === '' || (int) $mathResult !== $expected) {
            http_response_code(400);
            exit('La confirmación matemática es incorrecta.');
        }

        if ($forceDelete && !$isAdmin) {
            http_response_code(403);
            $this->json([
                'success' => false,
                'message' => 'Solo los administradores pueden eliminar proyectos definitivamente.',
            ], 403);
            return;
        }

        $auditRepo = new AuditLogRepository($this->db);

        try {
            $result = $repo->deleteProject($projectId, $forceDelete, $isAdmin, (int) ($user['id'] ?? 0));

            if (($result['success'] ?? false) === true) {
                $actionLabel = $forceDelete ? 'eliminó' : 'inactivó';
                error_log(sprintf(
                    '[audit] Usuario %s (ID: %d) %s proyecto "%s" (ID: %d) con %d tareas a las %s',
                    $user['name'] ?? 'desconocido',
                    (int) ($user['id'] ?? 0),
                    $actionLabel,
                    $project['name'],
                    $projectId,
                    (int) ($dependencies['tasks'] ?? 0),
                    date('c')
                ));

                $this->logProjectDeletion($auditRepo, $user, $projectId, (string) ($project['name'] ?? ''), $forceDelete, $dependencies, $result['deleted'] ?? []);

                $this->json([
                    'success' => true,
                    'message' => $forceDelete ? 'Proyecto eliminado correctamente' : 'Proyecto inactivado correctamente',
                    'deleted' => $result['deleted'] ?? [],
                ]);
                return;
            }

            $errorMessage = (string) ($result['error'] ?? 'Operación fallida al eliminar el proyecto.');

            error_log('Error al eliminar proyecto: ' . $errorMessage);

            $this->json([
                'success' => false,
                'message' => $errorMessage,
                'can_inactivate' => $canInactivate,
                'dependencies' => $dependencies,
                'inactivated' => $result['inactivated'] ?? false,
            ], 500);
            return;
        } catch (\Throwable $e) {
            error_log('Error al eliminar proyecto: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo eliminar el proyecto. Intenta nuevamente o contacta al administrador.');
        }
    }

    public function inactivate(int $id): void
    {
        if (!$this->canDeleteProjects()) {
            http_response_code(403);
            $this->json([
                'success' => false,
                'message' => 'Acción no permitida por permisos',
            ], 403);
            return;
        }

        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $projectId = $id;
        $mathOperator = trim((string) ($_POST['math_operator'] ?? ''));
        $operand1 = isset($_POST['math_operand1']) ? (int) $_POST['math_operand1'] : 0;
        $operand2 = isset($_POST['math_operand2']) ? (int) $_POST['math_operand2'] : 0;
        $mathResult = trim((string) ($_POST['math_result'] ?? ''));
        $project = $repo->findForUser($projectId, $user);

        if ($projectId <= 0 || !$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        if (!in_array($mathOperator, ['+', '-'], true) || $operand1 < 1 || $operand1 > 10 || $operand2 < 1 || $operand2 > 10) {
            http_response_code(400);
            exit('La verificación matemática no es válida.');
        }

        $expected = $mathOperator === '+' ? $operand1 + $operand2 : $operand1 - $operand2;

        if ($mathResult === '' || (int) $mathResult !== $expected) {
            http_response_code(400);
            exit('La confirmación matemática es incorrecta.');
        }

        try {
            $repo->inactivate($projectId);

            error_log(sprintf(
                '[audit] Usuario %s (ID: %d) inactivó proyecto "%s" (ID: %d) a las %s',
                $user['name'] ?? 'desconocido',
                (int) ($user['id'] ?? 0),
                $project['name'],
                $projectId,
                date('c')
            ));

            $this->json([
                'success' => true,
                'message' => 'Proyecto inactivado correctamente',
            ]);
            return;
        } catch (\Throwable $e) {
            error_log('Error al inactivar proyecto: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo inactivar el proyecto. Intenta nuevamente o contacta al administrador.');
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
            $requestedRedirect = trim((string) ($_POST['redirect_to'] ?? ''));
            if ($requestedRedirect !== '') {
                $destination = $requestedRedirect;
            } else {
                $destination = $redirectId > 0 ? '/project/public/projects/' . $redirectId . '/talent' : '/project/public/projects';
            }
            header('Location: ' . $destination);
            return;
        } catch (\Throwable $e) {
            error_log('Error al asignar talento: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo asignar el talento: ' . $e->getMessage());
        }
    }

    public function storeDesignInput(int $projectId): void
    {
        $this->requirePermission('projects.manage');
        http_response_code(410);
        exit('El registro de entradas de diseño fue sustituido por los controles integrados en el árbol de proyecto.');
    }

    public function deleteDesignInput(int $projectId, int $inputId): void
    {
        $this->requirePermission('projects.manage');
        http_response_code(410);
        exit('El registro de entradas de diseño fue sustituido por los controles integrados en el árbol de proyecto.');
    }

    public function storeDesignControl(int $projectId): void
    {
        $this->requirePermission('projects.manage');
        http_response_code(410);
        exit('Los controles de diseño se gestionan ahora desde el árbol de proyecto (03-Controles).');
    }

    public function updateDesignOutputs(int $projectId): void
    {
        $this->requirePermission('projects.manage');
        http_response_code(410);
        exit('Los resultados de diseño se calculan desde los controles del árbol de proyecto.');
    }

    public function storeDesignChange(int $projectId): void
    {
        $this->requirePermission('projects.manage');
        http_response_code(410);
        exit('Los cambios de diseño se controlan desde la carpeta 05-Cambios de cada fase/sprint.');
    }

    public function uploadNodeFile(int $projectId, int $nodeId): void
    {
        $this->requirePermission('projects.manage');

        try {
            $this->ensureDocumentFlowSchema();
            $nodesRepo = new ProjectNodesRepository($this->db);
            $userId = (int) ($this->auth->user()['id'] ?? 0);
            $payloadFiles = $_FILES['node_files'] ?? null;
            $meta = $this->collectDocumentUploadMeta();
            $results = [];
            $startFlow = isset($_POST['start_flow']) && $_POST['start_flow'] !== '';

            if ($startFlow) {
                error_log(sprintf(
                    'Document upload start_flow request: reviewer_id=%s validator_id=%s approver_id=%s',
                    $meta['reviewer_id'] ?? 'null',
                    $meta['validator_id'] ?? 'null',
                    $meta['approver_id'] ?? 'null'
                ));
            }

            if (is_array($payloadFiles) && isset($payloadFiles['name']) && is_array($payloadFiles['name'])) {
                $total = count($payloadFiles['name']);
                for ($i = 0; $i < $total; $i++) {
                    $file = [
                        'name' => $payloadFiles['name'][$i] ?? '',
                        'type' => $payloadFiles['type'][$i] ?? '',
                        'tmp_name' => $payloadFiles['tmp_name'][$i] ?? '',
                        'error' => $payloadFiles['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                        'size' => $payloadFiles['size'][$i] ?? 0,
                    ];

                    $results[] = $nodesRepo->createFileNode($projectId, $nodeId, $file, $userId, $meta);
                }
            } else {
                $results[] = $nodesRepo->createFileNode($projectId, $nodeId, $_FILES['node_file'] ?? [], $userId, $meta);
            }

            $auditRepo = new AuditLogRepository($this->db);
            foreach ($results as $result) {
                if ($startFlow) {
                    error_log(sprintf(
                        'Document upload persisted flow: node_id=%s reviewer_id=%s',
                        $result['id'] ?? 'null',
                        $result['reviewer_id'] ?? 'null'
                    ));
                }
                $auditRepo->log(
                    $userId,
                    'project_node_file',
                    (int) ($result['id'] ?? 0),
                    'document_uploaded',
                    [
                        'document_type' => $meta['document_type'] ?? null,
                        'document_tags' => $meta['document_tags'] ?? null,
                        'document_version' => $meta['document_version'] ?? null,
                        'description' => $meta['description'] ?? null,
                        'document_status' => $meta['document_status'] ?? null,
                    ]
                );
            }

            $firstId = $results[0]['id'] ?? null;
            $message = count($results) > 1 ? 'Documentos guardados.' : 'Documento guardado.';
            $this->json([
                'success' => true,
                'message' => $message,
                'document_id' => $firstId !== null ? (int) $firstId : null,
                'data' => $results,
            ]);
            return;
        } catch (\Throwable $e) {
            error_log('Error al adjuntar archivo en nodo: ' . $e->getMessage());

            $status = $e instanceof \InvalidArgumentException ? 400 : 500;
            $this->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'No se pudo adjuntar el archivo al nodo.',
                'document_id' => null,
            ], $status);
            return;
        }
    }

    public function createFolder(int $projectId): void
    {
        $this->requirePermission('projects.manage');

        try {
            $nodesRepo = new ProjectNodesRepository($this->db);
            $title = trim((string) ($_POST['title'] ?? ''));
            $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int) $_POST['parent_id'] : null;
            $isoClause = isset($_POST['iso_clause']) && $_POST['iso_clause'] !== '' ? (string) $_POST['iso_clause'] : null;
            $description = trim((string) ($_POST['description'] ?? ''));

            if ($title === '') {
                throw new \InvalidArgumentException('El nombre de la carpeta es obligatorio.');
            }

            $nodesRepo->createFolder(
                $projectId,
                $title,
                $parentId,
                $isoClause,
                $description !== '' ? $description : null,
                (int) ($this->auth->user()['id'] ?? 0)
            );
            header('Location: /project/public/projects/' . $projectId);
            return;
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            $this->render('projects/show', array_merge(
                $this->projectDetailData($projectId),
                ['nodeFileError' => $e->getMessage()]
            ));
            return;
        } catch (\Throwable $e) {
            error_log('Error al crear carpeta del proyecto: ' . $e->getMessage());
            http_response_code(500);
            $this->render('projects/show', array_merge(
                $this->projectDetailData($projectId),
                ['nodeFileError' => 'No se pudo crear la carpeta del proyecto.']
            ));
            return;
        }
    }

    public function createSprint(int $projectId): void
    {
        $this->requirePermission('projects.manage');

        $project = (new ProjectsRepository($this->db))->find($projectId);
        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        $methodology = strtolower((string) ($project['methodology'] ?? ''));
        if ($methodology !== 'scrum') {
            http_response_code(400);
            $this->render('projects/show', array_merge(
                $this->projectDetailData($projectId),
                ['nodeFileError' => 'Solo puedes crear sprints en proyectos con metodología Scrum.']
            ));
            return;
        }

        try {
            $treeService = new ProjectTreeService($this->db);
            $nodesRepo = new ProjectNodesRepository($this->db);
            $rootTree = $nodesRepo->treeWithFiles($projectId);
            $root = $rootTree[0] ?? null;
            $sprintsContainer = null;

            if ($root) {
                foreach ($root['children'] ?? [] as $child) {
                    if (($child['code'] ?? '') === '03-SPRINTS') {
                        $sprintsContainer = $child;
                        break;
                    }
                }
            }

            if (!$sprintsContainer) {
                http_response_code(400);
                $this->render('projects/show', array_merge(
                    $this->projectDetailData($projectId),
                    ['nodeFileError' => 'No se encontró el contenedor de sprints.']
                ));
                return;
            }

            $nextNumber = count($sprintsContainer['children'] ?? []) + 1;
            $treeService->createSprintNodes($projectId, (int) ($sprintsContainer['id'] ?? 0), $nextNumber, (int) ($this->auth->user()['id'] ?? 0));
            header('Location: /project/public/projects/' . $projectId);
            return;
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            $this->render('projects/show', array_merge(
                $this->projectDetailData($projectId),
                ['nodeFileError' => $e->getMessage()]
            ));
            return;
        } catch (\Throwable $e) {
            error_log('Error al crear sprint: ' . $e->getMessage());
            http_response_code(500);
            $this->render('projects/show', array_merge(
                $this->projectDetailData($projectId),
                ['nodeFileError' => 'No se pudo crear el sprint del proyecto.']
            ));
            return;
        }
    }

    public function deleteNode(int $projectId, int $nodeId): void
    {
        $this->requirePermission('projects.manage');

        try {
            $nodesRepo = new ProjectNodesRepository($this->db);
            $nodesRepo->deleteNode($projectId, $nodeId, (int) ($this->auth->user()['id'] ?? 0));

            if ($this->wantsJson()) {
                $this->json(['status' => 'ok']);
                return;
            }

            header('Location: /project/public/projects/' . $projectId);
            return;
        } catch (\Throwable $e) {
            error_log('Error al eliminar nodo documental: ' . $e->getMessage());

            if ($this->wantsJson()) {
                $this->json($this->nodeErrorResponse($e, 'No se pudo eliminar el nodo.'), 500);
                return;
            }

            http_response_code(500);
            $this->render('projects/show', array_merge(
                $this->projectDetailData($projectId),
                ['nodeFileError' => $this->isDebugMode() ? $e->getMessage() : 'No se pudo eliminar el nodo.']
            ));
            return;
        }
    }

    public function saveDocumentFlow(int $projectId, int $nodeId): void
    {
        $this->requirePermission('projects.manage');

        try {
            $this->ensureDocumentFlowSchema();
            $payload = [
                'reviewer_id' => $_POST['reviewer_id'] ?? null,
                'validator_id' => $_POST['validator_id'] ?? null,
                'approver_id' => $_POST['approver_id'] ?? null,
            ];

            $repo = new ProjectNodesRepository($this->db);
            $data = $repo->updateDocumentFlow($projectId, $nodeId, $payload);

            (new AuditLogRepository($this->db))->log(
                (int) ($this->auth->user()['id'] ?? 0),
                'project_node_file',
                $nodeId,
                'document_flow_assigned',
                [
                    'reviewer_id' => $payload['reviewer_id'] ?? null,
                    'validator_id' => $payload['validator_id'] ?? null,
                    'approver_id' => $payload['approver_id'] ?? null,
                    'document_status' => $data['document_status'] ?? null,
                ]
            );

            $this->json(['status' => 'ok', 'data' => $data]);
            return;
        } catch (\Throwable $e) {
            $status = $e instanceof \InvalidArgumentException ? 400 : 500;
            $this->json($this->nodeErrorResponse($e, 'No se pudo guardar el flujo documental.'), $status);
            return;
        }
    }

    public function updateDocumentStatus(int $projectId, int $nodeId): void
    {
        $this->requirePermission('projects.view');

        try {
            $action = trim((string) ($_POST['action'] ?? ''));
            $documentStatus = trim((string) ($_POST['document_status'] ?? ''));
            $comment = trim((string) ($_POST['comment'] ?? ''));
            $data = $this->processDocumentStatusUpdate($projectId, $nodeId, $action, $documentStatus);

            (new AuditLogRepository($this->db))->log(
                (int) ($this->auth->user()['id'] ?? 0),
                'project_node_file',
                $nodeId,
                $this->auditActionForDocument($action, $documentStatus),
                [
                    'document_status' => $data['document_status'] ?? null,
                    'comment' => $comment !== '' ? $comment : null,
                ]
            );

            $this->json(['status' => 'ok', 'data' => $data]);
            return;
        } catch (\Throwable $e) {
            $status = $e instanceof \InvalidArgumentException ? 400 : 500;
            $this->json($this->nodeErrorResponse($e, 'No se pudo actualizar el estado documental.'), $status);
            return;
        }
    }

    public function updateDocumentMetadata(int $projectId, int $nodeId): void
    {
        $this->requirePermission('projects.manage');

        try {
            $repo = new ProjectNodesRepository($this->db);
            $tags = $_POST['tags'] ?? null;
            $version = $_POST['version'] ?? null;
            $data = $repo->updateDocumentMetadata($projectId, $nodeId, [
                'tags' => $tags,
                'version' => $version,
            ]);

            (new AuditLogRepository($this->db))->log(
                (int) ($this->auth->user()['id'] ?? 0),
                'project_node_file',
                $nodeId,
                'document_metadata_updated',
                [
                    'document_tags' => $data['document_tags'] ?? null,
                    'document_version' => $data['document_version'] ?? null,
                ]
            );
            $this->json(['status' => 'ok', 'data' => $data]);
            return;
        } catch (\Throwable $e) {
            $status = $e instanceof \InvalidArgumentException ? 400 : 500;
            $this->json($this->nodeErrorResponse($e, 'No se pudo guardar la metadata del documento.'), $status);
            return;
        }
    }

    public function approveDocumentReview(int $projectId, int $nodeId): void
    {
        $this->requirePermission('projects.view');

        try {
            $data = $this->processDocumentStatusUpdate($projectId, $nodeId, 'reviewed', '');
            $this->json(['status' => 'ok', 'data' => $data]);
            return;
        } catch (\Throwable $e) {
            $status = $e instanceof \InvalidArgumentException ? 400 : 500;
            $this->json($this->nodeErrorResponse($e, 'No se pudo aprobar la revisión del documento.'), $status);
            return;
        }
    }

    public function validateDocument(int $projectId, int $nodeId): void
    {
        $this->requirePermission('projects.view');

        try {
            $data = $this->processDocumentStatusUpdate($projectId, $nodeId, 'validated', '');
            $this->json(['status' => 'ok', 'data' => $data]);
            return;
        } catch (\Throwable $e) {
            $status = $e instanceof \InvalidArgumentException ? 400 : 500;
            $this->json($this->nodeErrorResponse($e, 'No se pudo validar el documento.'), $status);
            return;
        }
    }

    public function approveDocumentFinal(int $projectId, int $nodeId): void
    {
        $this->requirePermission('projects.view');

        try {
            $data = $this->processDocumentStatusUpdate($projectId, $nodeId, 'approved', '');
            $this->json(['status' => 'ok', 'data' => $data]);
            return;
        } catch (\Throwable $e) {
            $status = $e instanceof \InvalidArgumentException ? 400 : 500;
            $this->json($this->nodeErrorResponse($e, 'No se pudo aprobar el documento.'), $status);
            return;
        }
    }

    public function approveDesignChange(int $projectId, int $changeId): void
    {
        $this->requirePermission('projects.manage');

        http_response_code(410);
        exit('La aprobación de cambios se gestiona ahora desde los nodos de 05-Cambios en el árbol de proyecto.');
    }

    public function listNodeChildren(int $projectId, ?int $parentId = null): void
    {
        $this->requirePermission('projects.view');

        try {
            $nodesRepo = new ProjectNodesRepository($this->db);
            $children = $nodesRepo->listChildren($projectId, $parentId);
            $this->json(['status' => 'ok', 'data' => $children]);
            return;
        } catch (\Throwable $e) {
            $status = $e instanceof \InvalidArgumentException ? 400 : 500;
            $this->json($this->nodeErrorResponse($e, 'No se pudo obtener el contenido de la carpeta.'), $status);
            return;
        }
    }

    public function documentHistory(int $projectId, int $nodeId): void
    {
        $this->requirePermission('projects.view');

        try {
            $repo = new ProjectNodesRepository($this->db);
            $node = $repo->findById($projectId, $nodeId);
            if (!$node || ($node['node_type'] ?? '') !== 'file') {
                throw new \InvalidArgumentException('Documento no encontrado.');
            }

            $auditRepo = new AuditLogRepository($this->db);
            $entries = $auditRepo->listForEntity('project_node_file', $nodeId, 100);
            $this->json(['status' => 'ok', 'data' => $entries]);
            return;
        } catch (\Throwable $e) {
            $status = $e instanceof \InvalidArgumentException ? 400 : 500;
            $this->json($this->nodeErrorResponse($e, 'No se pudo cargar el historial documental.'), $status);
            return;
        }
    }

    public function downloadNodeFile(int $projectId, int $nodeId): void
    {
        $this->requirePermission('projects.view');

        try {
            $repo = new ProjectNodesRepository($this->db);
            $node = $repo->findById($projectId, $nodeId);
            if (!$node || ($node['node_type'] ?? '') !== 'file') {
                http_response_code(404);
                exit('Archivo no encontrado');
            }

            $publicPath = $node['file_path'] ?? '';
            $physicalPath = $this->physicalPathFromPublic($publicPath);

            if ($physicalPath === null || !is_file($physicalPath)) {
                http_response_code(404);
                exit('Archivo no encontrado');
            }

            $mime = mime_content_type($physicalPath) ?: 'application/octet-stream';
            $downloadName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($node['title'] ?? basename($physicalPath)));
            $downloadName = $downloadName !== '' ? $downloadName : basename($physicalPath);
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($physicalPath));
            header('Content-Disposition: attachment; filename="' . $downloadName . '"');
            readfile($physicalPath);
            return;
        } catch (\Throwable $e) {
            error_log('Error al descargar archivo: ' . $e->getMessage());
            http_response_code(500);
            echo $this->isDebugMode() ? $e->getMessage() : 'No se pudo descargar el archivo.';
            return;
        }
    }

    private function collectAssignmentPayload(?int $projectId): array
    {
        $allocationPercent = $_POST['allocation_percent'] ?? null;
        $weeklyHours = $_POST['weekly_hours'] ?? null;
        $assignmentStatus = strtolower(trim((string) ($_POST['assignment_status'] ?? 'active')));
        $allowedStatuses = ['active', 'paused', 'removed'];
        if (!in_array($assignmentStatus, $allowedStatuses, true)) {
            $assignmentStatus = 'active';
        }

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
            'assignment_status' => $assignmentStatus,
            'created_by' => (int) ($this->auth->user()['id'] ?? 0),
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
        $treeService = new ProjectTreeService($this->db);
        $nodesRepo = new ProjectNodesRepository($this->db);
        $projectNodes = $nodesRepo->treeWithFiles($id);
        $config = (new ConfigService($this->db))->getConfig();
        $progress = $treeService->summarizeProgress(
            $id,
            (string) ($project['methodology'] ?? 'cascada'),
            $config['document_flow']['expected_docs'] ?? []
        );
        $progressHistory = (new AuditLogRepository($this->db))->listForEntity('project_progress', $id, 50);
        $pendingControls = count($nodesRepo->pendingCriticalNodes($id));
        $approvedDocuments = $this->countApprovedDocuments($projectNodes);
        $loggedHours = $repo->timesheetHoursForProject($id);
        $dependencies = $repo->dependencySummary($id);
        $deleteContext = $this->projectDeletionContext($id, $repo);

        return array_merge([
            'title' => 'Detalle de proyecto',
            'project' => $project,
            'assignments' => $assignments,
            'currentUser' => $this->auth->user() ?? [],
            'canManage' => $this->auth->can('projects.manage'),
            'projectNodes' => $projectNodes,
            'progressPhases' => $progress['phases'] ?? [],
            'documentFlowConfig' => $config['document_flow'] ?? [],
            'accessRoles' => $config['access']['roles'] ?? [],
            'canUpdateProgress' => $this->userCanUpdateProjectProgress(),
            'progressHistory' => $progressHistory,
            'progressIndicators' => [
                'approved_documents' => $approvedDocuments,
                'pending_controls' => $pendingControls,
                'logged_hours' => $loggedHours,
            ],
        ], $deleteContext);
    }

    private function userCanUpdateProjectProgress(): bool
    {
        $user = $this->auth->user();
        if (!$user) {
            return false;
        }

        $row = $this->db->fetchOne(
            'SELECT can_update_project_progress FROM users WHERE id = :id LIMIT 1',
            [':id' => (int) $user['id']]
        );

        return ((int) ($row['can_update_project_progress'] ?? 0)) === 1;
    }

    private function countApprovedDocuments(array $nodes): int
    {
        $count = 0;

        foreach ($nodes as $node) {
            $files = $node['files'] ?? [];
            foreach ($files as $file) {
                if (($file['document_status'] ?? '') === 'aprobado') {
                    $count++;
                }
            }

            if (!empty($node['children'])) {
                $count += $this->countApprovedDocuments($node['children']);
            }
        }

        return $count;
    }

    private function collectDocumentUploadMeta(): array
    {
        $documentType = trim((string) ($_POST['document_type'] ?? ''));
        $documentTags = $_POST['document_tags'] ?? null;
        $documentVersion = trim((string) ($_POST['document_version'] ?? ''));
        $description = trim((string) ($_POST['document_description'] ?? ''));
        $startFlow = isset($_POST['start_flow']) && $_POST['start_flow'] !== '';
        $reviewerId = $_POST['reviewer_id'] ?? null;
        $validatorId = $_POST['validator_id'] ?? null;
        $approverId = $_POST['approver_id'] ?? null;

        if ($documentType === '') {
            throw new \InvalidArgumentException('El tipo documental es obligatorio.');
        }
        if ($documentTags === null || $documentTags === '') {
            throw new \InvalidArgumentException('Debes seleccionar al menos un tag.');
        }
        if ($documentVersion === '') {
            throw new \InvalidArgumentException('La versión del documento es obligatoria.');
        }
        if ($description === '') {
            throw new \InvalidArgumentException('La descripción del documento es obligatoria.');
        }
        if ($startFlow && ($reviewerId === null || $reviewerId === '')) {
            throw new \InvalidArgumentException('Selecciona un revisor para iniciar el flujo.');
        }

        return [
            'document_type' => $documentType,
            'document_tags' => $documentTags,
            'document_version' => $documentVersion,
            'description' => $description,
            'document_status' => $startFlow ? 'en_revision' : 'borrador',
            'reviewer_id' => $reviewerId,
            'validator_id' => $validatorId,
            'approver_id' => $approverId,
        ];
    }

    private function auditActionForDocument(string $action, string $documentStatus): string
    {
        return match ($action) {
            'send_review' => 'document_sent_review',
            'reviewed' => 'document_reviewed',
            'send_validation' => 'document_sent_validation',
            'validated' => 'document_validated',
            'send_approval' => 'document_sent_approval',
            'approved' => 'document_approved',
            'rejected' => 'document_rejected',
            default => $documentStatus !== '' ? 'document_status_updated' : 'document_action',
        };
    }

    private function projectDeletionContext(int $projectId, ?ProjectsRepository $repo = null): array
    {
        $repo ??= new ProjectsRepository($this->db);
        $dependencies = $repo->dependencySummary($projectId);

        return [
            'dependencies' => $dependencies,
            'hasDependencies' => $dependencies['has_dependencies'] ?? false,
            'canDelete' => $this->canForceDeleteProjects(),
            'canInactivate' => $this->canDeleteProjects(),
            'isAdmin' => $this->canForceDeleteProjects(),
            'mathOperand1' => random_int(1, 10),
            'mathOperand2' => random_int(1, 10),
            'mathOperator' => random_int(0, 1) === 0 ? '+' : '-',
        ];
    }

    private function wantsJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

        return $isAjax || str_contains($accept, 'application/json');
    }

    private function flattenProjectNodes(array $nodes): array
    {
        $stack = $nodes;
        $map = [];

        while (!empty($stack)) {
            $node = array_pop($stack);
            if (!is_array($node)) {
                continue;
            }
            if (isset($node['id'])) {
                $map[(int) $node['id']] = $node;
            }
            foreach (($node['children'] ?? []) as $child) {
                $stack[] = $child;
            }
        }

        return $map;
    }

    private function canDeleteProjects(): bool
    {
        if ($this->auth->can('projects.manage')) {
            return true;
        }

        if ($this->auth->can('config.manage')) {
            return true;
        }

        return $this->auth->hasRole('Administrador');
    }

    private function canForceDeleteProjects(): bool
    {
        return $this->auth->can('config.manage') || $this->auth->hasRole('Administrador');
    }

    private function ensureDocumentFlowSchema(): void
    {
        if (!$this->db->tableExists('project_nodes')) {
            throw new \InvalidArgumentException('La tabla project_nodes no existe. Ejecuta el script de migración antes de guardar documentos.');
        }

        $requiredColumns = [
            'reviewer_id',
            'validator_id',
            'approver_id',
            'reviewed_by',
            'document_status',
            'document_tags',
            'document_version',
            'document_type',
            'reviewed_at',
            'validated_by',
            'validated_at',
            'approved_by',
            'approved_at',
        ];

        $missing = [];
        foreach ($requiredColumns as $column) {
            if (!$this->db->columnExists('project_nodes', $column)) {
                $missing[] = $column;
            }
        }

        if ($missing !== []) {
            throw new \InvalidArgumentException(
                'Debes aplicar la migración de project_nodes (reviewer_id, validator_id, approver_id, reviewed_by, validated_by, approved_by, reviewed_at, validated_at, approved_at, document_status, document_tags, document_version, document_type) antes de guardar documentos o flujos.'
            );
        }
    }

    private function isLocalEnvironment(): bool
    {
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?? 'production';

        return in_array(strtolower((string) $env), ['local', 'dev', 'development'], true);
    }

    private function isDebugMode(): bool
    {
        if ($this->isLocalEnvironment()) {
            return true;
        }

        $envDebug = strtolower((string) ($_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? ''));
        if (in_array($envDebug, ['1', 'true', 'on', 'yes'], true)) {
            return true;
        }

        try {
            $config = (new ConfigService($this->db))->getConfig();
            if (!empty($config['debug'])) {
                return true;
            }
        } catch (\Throwable) {
            // Ignorar y asumir modo producción
        }

        return false;
    }

    private function nodeErrorResponse(\Throwable $e, string $genericMessage): array
    {
        $response = [
            'status' => 'error',
            'message' => $e->getMessage() ?: $genericMessage,
        ];

        if ($this->isDebugMode()) {
            $sqlState = null;
            $query = null;

            if ($e instanceof \PDOException) {
                $sqlState = $e->errorInfo[0] ?? $e->getCode() ?? null;
                $query = $e->queryString ?? null;
            }

            $response['exception_message'] = $e->getMessage();
            $response['sqlstate'] = $sqlState;
            $response['query'] = $query;
            $response['trace'] = $this->truncateTrace($e->getTraceAsString());
        }

        return $response;
    }

    private function statusFromDocumentAction(string $action): string
    {
        return match ($action) {
            'send_review' => 'en_revision',
            'reviewed' => 'revisado',
            'send_validation' => 'en_validacion',
            'validated' => 'validado',
            'send_approval' => 'en_aprobacion',
            'approved' => 'aprobado',
            'rejected' => 'rechazado',
            default => 'borrador',
        };
    }

    private function actionFromStatusTransition(string $currentStatus, string $nextStatus): string
    {
        $transitions = [
            'borrador' => [
                'en_revision' => 'send_review',
            ],
            'en_revision' => [
                'revisado' => 'reviewed',
                'rechazado' => 'rejected',
            ],
            'revisado' => [
                'en_validacion' => 'send_validation',
                'rechazado' => 'rejected',
            ],
            'en_validacion' => [
                'validado' => 'validated',
                'rechazado' => 'rejected',
            ],
            'validado' => [
                'en_aprobacion' => 'send_approval',
                'rechazado' => 'rejected',
            ],
            'en_aprobacion' => [
                'aprobado' => 'approved',
                'rechazado' => 'rejected',
            ],
        ];

        return $transitions[$currentStatus][$nextStatus] ?? '';
    }

    private function assertDocumentActionAllowed(string $action, string $currentStatus, array $nodeFlow, int $currentUserId): void
    {
        $canManage = $this->auth->can('projects.manage');
        $reviewerId = (int) ($nodeFlow['reviewer_id'] ?? 0);
        $validatorId = (int) ($nodeFlow['validator_id'] ?? 0);
        $approverId = (int) ($nodeFlow['approver_id'] ?? 0);

        if ($action === 'send_review') {
            if (!$canManage) {
                throw new \InvalidArgumentException('No tienes permisos para enviar a revisión.');
            }
            if ($currentStatus !== 'borrador') {
                throw new \InvalidArgumentException('El documento no está disponible para enviar a revisión.');
            }
            if ($reviewerId === 0) {
                throw new \InvalidArgumentException('Debes asignar un revisor antes de enviar.');
            }
            return;
        }

        if ($action === 'reviewed') {
            if ($currentStatus !== 'en_revision' || $reviewerId !== $currentUserId) {
                throw new \InvalidArgumentException('Solo el revisor asignado puede aprobar esta revisión.');
            }
            return;
        }

        if ($action === 'send_validation') {
            if (!$canManage) {
                throw new \InvalidArgumentException('No tienes permisos para enviar a validación.');
            }
            if ($currentStatus !== 'revisado') {
                throw new \InvalidArgumentException('El documento no está listo para validación.');
            }
            if ($validatorId === 0) {
                throw new \InvalidArgumentException('Debes asignar un validador antes de enviar.');
            }
            return;
        }

        if ($action === 'validated') {
            if ($currentStatus !== 'en_validacion' || $validatorId !== $currentUserId) {
                throw new \InvalidArgumentException('Solo el validador asignado puede validar este documento.');
            }
            return;
        }

        if ($action === 'send_approval') {
            if (!$canManage) {
                throw new \InvalidArgumentException('No tienes permisos para enviar a aprobación.');
            }
            if ($currentStatus !== 'validado') {
                throw new \InvalidArgumentException('El documento no está listo para aprobación.');
            }
            if ($approverId === 0) {
                throw new \InvalidArgumentException('Debes asignar un aprobador antes de enviar.');
            }
            return;
        }

        if ($action === 'approved') {
            if ($currentStatus !== 'en_aprobacion' || $approverId !== $currentUserId) {
                throw new \InvalidArgumentException('Solo el aprobador asignado puede aprobar este documento.');
            }
            return;
        }

        if ($action === 'rejected') {
            $allowed = ($currentStatus === 'en_revision' && $reviewerId === $currentUserId)
                || ($currentStatus === 'revisado' && $reviewerId === $currentUserId)
                || ($currentStatus === 'en_validacion' && $validatorId === $currentUserId)
                || ($currentStatus === 'validado' && $validatorId === $currentUserId)
                || ($currentStatus === 'en_aprobacion' && $approverId === $currentUserId);

            if (!$allowed) {
                throw new \InvalidArgumentException('No tienes permisos para rechazar en este estado.');
            }
            return;
        }

        if (!$canManage) {
            throw new \InvalidArgumentException('No tienes permisos para actualizar este documento.');
        }
    }

    private function processDocumentStatusUpdate(int $projectId, int $nodeId, string $action, string $documentStatus): array
    {
        $repo = new ProjectNodesRepository($this->db);
        $node = $repo->findById($projectId, $nodeId);
        if (!$node || ($node['node_type'] ?? '') !== 'file') {
            throw new \InvalidArgumentException('Documento no encontrado.');
        }

        if ($action === '' && $documentStatus === '') {
            throw new \InvalidArgumentException('Acción documental inválida.');
        }

        $currentUserId = (int) ($this->auth->user()['id'] ?? 0);

        $nodeFlow = $repo->documentFlowForNode($projectId, $nodeId);
        $currentStatus = $nodeFlow['document_status'] ?? 'borrador';

        if ($action !== '' && $documentStatus !== '') {
            $expectedStatus = $this->statusFromDocumentAction($action);
            if ($expectedStatus !== $documentStatus) {
                throw new \InvalidArgumentException('La acción no coincide con el estado solicitado.');
            }
        }

        if ($action === '' && $documentStatus !== '' && $documentStatus !== $currentStatus) {
            $action = $this->actionFromStatusTransition($currentStatus, $documentStatus);
            if ($action === '') {
                throw new \InvalidArgumentException('No se permiten cambios de estado fuera del flujo documental.');
            }
        }

        $status = $documentStatus !== '' ? $documentStatus : $this->statusFromDocumentAction($action);

        if (!in_array($status, self::DOCUMENT_STATUSES, true)) {
            throw new \InvalidArgumentException('Estado documental inválido.');
        }

        $payload = [
            'document_status' => $status,
            'reviewed_by' => $nodeFlow['reviewed_by'],
            'reviewed_at' => $nodeFlow['reviewed_at'],
            'validated_by' => $nodeFlow['validated_by'],
            'validated_at' => $nodeFlow['validated_at'],
            'approved_by' => $nodeFlow['approved_by'],
            'approved_at' => $nodeFlow['approved_at'],
        ];

        $now = date('Y-m-d H:i:s');
        $this->assertDocumentActionAllowed($action, $currentStatus, $nodeFlow, $currentUserId);

        switch ($action) {
            case 'send_review':
                $payload['document_status'] = 'en_revision';
                break;
            case 'reviewed':
                $payload['document_status'] = 'revisado';
                $payload['reviewed_by'] = $currentUserId;
                $payload['reviewed_at'] = $now;
                break;
            case 'send_validation':
                $payload['document_status'] = 'en_validacion';
                break;
            case 'validated':
                $payload['document_status'] = 'validado';
                $payload['validated_by'] = $currentUserId;
                $payload['validated_at'] = $now;
                break;
            case 'send_approval':
                $payload['document_status'] = 'en_aprobacion';
                break;
            case 'approved':
                $payload['document_status'] = 'aprobado';
                $payload['approved_by'] = $currentUserId;
                $payload['approved_at'] = $now;
                break;
            case 'rejected':
                $payload['document_status'] = 'rechazado';
                if ($currentStatus === 'en_revision' || $currentStatus === 'revisado') {
                    $payload['reviewed_by'] = $currentUserId;
                    $payload['reviewed_at'] = $now;
                }
                if ($currentStatus === 'en_validacion' || $currentStatus === 'validado') {
                    $payload['validated_by'] = $currentUserId;
                    $payload['validated_at'] = $now;
                }
                if ($currentStatus === 'en_aprobacion') {
                    $payload['approved_by'] = $currentUserId;
                    $payload['approved_at'] = $now;
                }
                break;
            default:
                $payload['document_status'] = $status;
                break;
        }

        return $repo->updateDocumentStatus($projectId, $nodeId, $payload);
    }

    private function logProjectDeletion(
        AuditLogRepository $auditRepo,
        array $user,
        int $projectId,
        string $projectName,
        bool $forceDelete,
        array $dependencies,
        array $deleted
    ): void {
        try {
            $auditRepo->log(
                (int) ($user['id'] ?? 0),
                'project',
                $projectId,
                $forceDelete ? 'project.force_delete' : 'project.inactivate',
                [
                    'project_name' => $projectName,
                    'force_delete' => $forceDelete,
                    'dependencies' => $dependencies,
                    'deleted' => $deleted,
                    'performed_at' => date('c'),
                ]
            );
        } catch (\Throwable $e) {
            error_log('No se pudo registrar la eliminación del proyecto: ' . $e->getMessage());
        }
    }

    private function truncateTrace(string $trace): string
    {
        $lines = explode("\n", $trace);

        return implode("\n", array_slice($lines, 0, 10));
    }

    private function physicalPathFromPublic(string $publicPath): ?string
    {
        $base = realpath(__DIR__ . '/../../public') ?: __DIR__ . '/../../public';
        $fullPath = $base . '/' . ltrim($publicPath, '/');
        $normalizedBase = rtrim(realpath($base) ?: $base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $dir = realpath(dirname($fullPath));
        if ($dir === false) {
            return null;
        }

        $normalizedFull = $dir . DIRECTORY_SEPARATOR . basename($fullPath);

        if (!str_starts_with($normalizedFull, $normalizedBase)) {
            return null;
        }

        return $normalizedFull;
    }

    private function formatExceptionForDisplay(\Throwable $e): string
    {
        $message = trim($e->getMessage());
        $trace = $e->getTraceAsString();

        if ($trace === '') {
            return $message;
        }

        return $message . "\n" . $trace;
    }

    private function projectPayload(array $current, array $deliveryConfig = []): array
    {
        $allowedProjectTypes = ['convencional', 'scrum', 'hibrido', 'outsourcing'];
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
            'progress' => (float) ($current['progress'] ?? 0),
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

    private function isoActionForControl(string $controlType): string
    {
        return match ($controlType) {
            'revision' => 'design_review',
            'verificacion' => 'design_verification',
            'validacion' => 'design_validation',
            default => throw new \InvalidArgumentException('El tipo de control no es válido.'),
        };
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

        $allowedProjectTypes = ['convencional', 'scrum', 'hibrido', 'outsourcing'];
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
        $progress = 0.0;
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
            'outsourcing' => 'cascada',
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
