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
            $methodologyChanged = strtolower((string) ($project['methodology'] ?? '')) !== strtolower((string) ($payload['methodology'] ?? ($project['methodology'] ?? '')));
            $phaseChanged = trim((string) ($project['phase'] ?? '')) !== trim((string) ($payload['phase'] ?? ($project['phase'] ?? '')));
            $repo->updateProject($id, $payload, (int) ($this->auth->user()['id'] ?? 0));
            $this->logRiskAudit($auditRepo, $project['id'], $previousEvaluations, $payload['risk_evaluations'] ?? []);
            $this->logProjectChange($auditRepo, $project, $payload);

            if ($methodologyChanged || $phaseChanged) {
                (new ProjectNodesRepository($this->db))->markTreeOutdated($id);
            }

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
                'error' => $this->formatExceptionForDisplay($e),
                'old' => $_POST,
            ]));
        } catch (\Throwable $e) {
            error_log('Error al crear proyecto: ' . $e->getMessage());
            http_response_code(500);
            $this->render('projects/create', array_merge($this->projectFormData(), [
                'title' => 'Nuevo proyecto',
                'error' => $this->formatExceptionForDisplay($e),
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

        $nodeSnapshot = $this->projectNodesSnapshot((int) $project['id']);
        if (!empty($nodeSnapshot['pending_critical'])) {
            http_response_code(400);
            exit('No puedes cerrar el proyecto: hay nodos críticos pendientes en la estructura ISO 8.3.');
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

        try {
            $result = $repo->deleteProject($projectId, $forceDelete, $isAdmin);

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

                $this->json([
                    'success' => true,
                    'message' => $forceDelete ? 'Proyecto eliminado correctamente' : 'Proyecto inactivado correctamente',
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
        http_response_code(400);
        $this->render('projects/show', array_merge(
            $this->projectDetailData($projectId),
            ['designOutputError' => 'Los indicadores de revisión, verificación y validación se calculan automáticamente a partir de los nodos y controles registrados.']
        ));
    }

    public function storeDesignChange(int $projectId): void
    {
        $this->requirePermission('projects.manage');

        try {
            $repo = new DesignChangesRepository($this->db);
            $repo->create([
                'project_id' => $projectId,
                'description' => $_POST['description'] ?? '',
                'impact_scope' => $_POST['impact_scope'] ?? '',
                'impact_time' => $_POST['impact_time'] ?? '',
                'impact_cost' => $_POST['impact_cost'] ?? '',
                'impact_quality' => $_POST['impact_quality'] ?? '',
                'requires_review_validation' => isset($_POST['requires_review_validation']) ? 1 : 0,
            ], (int) ($this->auth->user()['id'] ?? 0));

            header('Location: /project/public/projects/' . $projectId);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            $this->render('projects/show', array_merge(
                $this->projectDetailData($projectId),
                ['designChangeError' => $e->getMessage()]
            ));
        }
    }

    public function uploadNodeFile(int $projectId, int $nodeId): void
    {
        $this->requirePermission('projects.manage');

        try {
            $nodesRepo = new ProjectNodesRepository($this->db);
            $result = $nodesRepo->createFileNode($projectId, $nodeId, $_FILES['node_file'] ?? [], (int) ($this->auth->user()['id'] ?? 0));

            if ($this->wantsJson()) {
                $this->json(['status' => 'ok', 'data' => $result]);
                return;
            }

            header('Location: /project/public/projects/' . $projectId);
        } catch (\Throwable $e) {
            error_log('Error al adjuntar archivo en nodo: ' . $e->getMessage());

            if ($this->wantsJson()) {
                $status = $e instanceof \InvalidArgumentException ? 400 : 500;
                $this->json($this->nodeErrorResponse($e, 'No se pudo adjuntar el archivo al nodo.'), $status);
                return;
            }

            $statusCode = $e instanceof \InvalidArgumentException ? 400 : 500;
            http_response_code($statusCode);

            $this->render('projects/show', array_merge(
                $this->projectDetailData($projectId),
                ['nodeFileError' => $e->getMessage()]
            ));
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
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            $this->render('projects/show', array_merge(
                $this->projectDetailData($projectId),
                ['nodeFileError' => $e->getMessage()]
            ));
        } catch (\Throwable $e) {
            error_log('Error al crear carpeta del proyecto: ' . $e->getMessage());
            http_response_code(500);
            $this->render('projects/show', array_merge(
                $this->projectDetailData($projectId),
                ['nodeFileError' => 'No se pudo crear la carpeta del proyecto.']
            ));
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

        $this->createProjectNodeTree($projectId, $methodology, $project['phase'] ?? null);

        try {
            $nodesRepo = new ProjectNodesRepository($this->db);
            $nodesRepo->createSprint($projectId, (int) ($this->auth->user()['id'] ?? 0));
            header('Location: /project/public/projects/' . $projectId);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            $this->render('projects/show', array_merge(
                $this->projectDetailData($projectId),
                ['nodeFileError' => $e->getMessage()]
            ));
        } catch (\Throwable $e) {
            error_log('Error al crear sprint: ' . $e->getMessage());
            http_response_code(500);
            $this->render('projects/show', array_merge(
                $this->projectDetailData($projectId),
                ['nodeFileError' => 'No se pudo crear el sprint del proyecto.']
            ));
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
        }
    }

    public function approveDesignChange(int $projectId, int $changeId): void
    {
        $this->requirePermission('projects.manage');

        try {
            $repo = new DesignChangesRepository($this->db);
            $repo->approve($changeId, $projectId, (int) ($this->auth->user()['id'] ?? 0));

            header('Location: /project/public/projects/' . $projectId);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            $this->render('projects/show', array_merge(
                $this->projectDetailData($projectId),
                ['designChangeError' => $e->getMessage()]
            ));
        }
    }

    public function listNodeChildren(int $projectId, ?int $parentId = null): void
    {
        $this->requirePermission('projects.view');

        try {
            $nodesRepo = new ProjectNodesRepository($this->db);
            $children = $nodesRepo->listChildren($projectId, $parentId);
            $this->json(['status' => 'ok', 'data' => $children]);
        } catch (\Throwable $e) {
            $status = $e instanceof \InvalidArgumentException ? 400 : 500;
            $this->json($this->nodeErrorResponse($e, 'No se pudo obtener el contenido de la carpeta.'), $status);
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
        } catch (\Throwable $e) {
            error_log('Error al descargar archivo: ' . $e->getMessage());
            http_response_code(500);
            echo $this->isDebugMode() ? $e->getMessage() : 'No se pudo descargar el archivo.';
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
        $designChangesRepo = new DesignChangesRepository($this->db);
        $users = (new UsersRepository($this->db))->all();
        $this->createProjectNodeTree(
            (int) ($project['id'] ?? 0),
            (string) ($project['methodology'] ?? 'cascada'),
            (string) ($project['phase'] ?? '')
        );
        $nodeSnapshot = $this->projectNodesSnapshot((int) ($project['id'] ?? 0));
        $projectNodes = $nodeSnapshot['nodes'] ?? [];
        $criticalNodes = $nodeSnapshot['pending_critical'] ?? [];
        $dependencies = $repo->dependencySummary($id);
        $mathOperand1 = random_int(1, 10);
        $mathOperand2 = random_int(1, 10);
        $mathOperator = random_int(0, 1) === 0 ? '+' : '-';
        $canDelete = $this->canForceDeleteProjects();
        $canInactivate = $this->canDeleteProjects();

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
            'designChanges' => $designChangesRepo->listByProject($id),
            'designChangeImpactLevels' => $designChangesRepo->impactLevels(),
            'performers' => array_values(array_filter($users, fn ($candidate) => (int) ($candidate['active'] ?? 0) === 1)),
            'projectNodes' => $projectNodes,
            'criticalNodes' => $criticalNodes,
            'dependencies' => $dependencies,
            'hasDependencies' => $dependencies['has_dependencies'] ?? false,
            'canDelete' => $canDelete,
            'canInactivate' => $canInactivate,
            'isAdmin' => $canDelete,
            'mathOperand1' => $mathOperand1,
            'mathOperand2' => $mathOperand2,
            'mathOperator' => $mathOperator,
        ];
    }

    private function wantsJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

        return $isAjax || str_contains($accept, 'application/json');
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

            $nodeSnapshot = $this->projectNodesSnapshot((int) $current['id']);
            if (!empty($nodeSnapshot['pending_critical'])) {
                $pendingNames = array_map(fn ($node) => $node['name'] ?? ($node['code'] ?? ''), $nodeSnapshot['pending_critical']);
                $pendingSummary = implode(', ', array_filter($pendingNames, fn ($name) => $name !== ''));
                throw new \InvalidArgumentException(
                    'No puedes cerrar el proyecto: hay nodos críticos pendientes' . ($pendingSummary !== '' ? " ({$pendingSummary})." : '.')
                );
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

    private function createProjectNodeTree(int $projectId, string $methodology, ?string $phase = null): void
    {
        $nodesRepo = new ProjectNodesRepository($this->db);
        $nodesRepo->synchronizeFromProject($projectId, $methodology, $phase);
    }

    private function projectNodesSnapshot(int $projectId): array
    {
        $nodesRepo = new ProjectNodesRepository($this->db);
        return $nodesRepo->snapshot($projectId);
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
