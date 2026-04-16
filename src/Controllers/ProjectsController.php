<?php

declare(strict_types=1);

use App\Repositories\AuditLogRepository;
use App\Repositories\ClientsRepository;
use App\Repositories\MasterFilesRepository;
use App\Repositories\OutsourcingRepository;
use App\Repositories\ProjectBillingRepository;
use App\Repositories\ProjectNodesRepository;
use App\Repositories\ProjectStoppersRepository;
use App\Repositories\ProjectsRepository;
use App\Repositories\ProjectSchedulesRepository;
use App\Repositories\TalentsRepository;
use App\Repositories\TasksRepository;
use App\Repositories\UsersRepository;

class ProjectsController extends Controller
{
    private const ALLOWED_PROJECT_TYPES = ['convencional', 'scrum', 'hibrido', 'outsourcing'];
    private const CLIENT_PARTICIPATION_LEVELS = [
        'baja' => 0,
        'media' => 1,
        'alta' => 2,
    ];
    private const MIN_REQUIRED_RISKS_ON_CREATE = 5;
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
        'final',
        'publicado',
        'en_revision',
        'revisado',
        'en_validacion',
        'validado',
        'en_aprobacion',
        'aprobado',
        'rechazado',
    ];
    private const STAGE_GATES = [
        'Discovery',
        'Ideación',
        'Definición',
        'Prototipo',
        'Prueba/Validación',
        'MVP',
        'Implementación',
        'Scale',
    ];

    private const CONTRACT_CURRENCIES = ['COP', 'USD', 'EUR', 'MXN'];
    private const PLAN_ITEM_TYPES = ['anticipo', 'mensualidad_fija', 'hito_entregable', 'porcentaje_avance'];
    private const INVOICE_STATUSES = ['issued', 'cancelled'];
    private const STOPPER_TYPES = ['cliente', 'tecnico', 'interno', 'proveedor', 'financiero', 'legal'];
    private const STOPPER_IMPACT_LEVELS = ['bajo', 'medio', 'alto', 'critico'];
    private const STOPPER_AFFECTED_AREAS = ['tiempo', 'alcance', 'costo', 'calidad'];
    private const STOPPER_STATUSES = ['abierto', 'en_gestion', 'escalado', 'resuelto', 'cerrado'];
    private const REQUIRED_DOCUMENTS_META_CODE = '99-REQDOCS-META';
    private const REQUIRED_DOCUMENTS_FILES_CODE = '99-REQDOCS-FILES';
    private const REQUIRED_DOCUMENT_UPLOAD_KEYS = [
        'propuesta_aceptada',
        'contrato',
        'acuerdo_confidencialidad',
        'presupuesto',
        'acta_inicio',
        'kickoff',
        'actas_seguimiento',
        'pruebas_funcionales',
        'acta_cierre',
        'lecciones_aprendidas',
        'diagrama_flujo',
        'diagrama_arquitectura',
        'documento_arquitectura',
    ];

    public function index(): void
    {
        $this->requirePermission('projects.view');
        $user = $this->auth->user() ?? [];

        $filters = [
            'client_id' => isset($_GET['client_id']) ? (int) $_GET['client_id'] : null,
            'client_name' => trim((string) ($_GET['client_name'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'project_stage' => trim((string) ($_GET['project_stage'] ?? '')),
            'methodology' => trim((string) ($_GET['methodology'] ?? '')),
            'billable' => trim((string) ($_GET['billable'] ?? '')),
            'start_date' => $_GET['start_date'] ?? '',
            'end_date' => $_GET['end_date'] ?? '',
        ];

        $repo = new ProjectsRepository($this->db);
        $config = (new ConfigService($this->db))->getConfig();
        $clientsRepo = new ClientsRepository($this->db);
        $projectService = new ProjectService($this->db);
        $projects = $repo->summary($user, $filters);
        foreach ($projects as &$project) {
            $project['health_score'] = $projectService->calculateProjectHealthScore((int) ($project['id'] ?? 0));
        }
        unset($project);

        $this->render('projects/index', [
            'title' => 'Panel de Proyectos',
            'projects' => $projects,
            'filters' => $filters,
            'clients' => $clientsRepo->listForUser($user),
            'delivery' => $config['delivery'] ?? [],
            'stageOptions' => self::STAGE_GATES,
        ]);
    }

    public function show(int $id): void
    {
        $this->requirePermission('projects.view');
        try {
            $this->render('projects/show', $this->projectDetailData($id));
            return;
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[projects.show] Error al cargar proyecto %d para user %d: %s',
                $id,
                (int) ($this->auth->user()['id'] ?? 0),
                $e->getMessage()
            ));

            $repo = new ProjectsRepository($this->db);
            $project = $repo->findForUser($id, $this->auth->user() ?? []);
            if (!$project) {
                http_response_code(404);
                exit('Proyecto no encontrado');
            }

            $this->render('projects/show', [
                'title' => 'Detalle de proyecto',
                'project' => $project,
                'currentUser' => $this->auth->user() ?? [],
                'canManage' => $this->auth->can('projects.manage'),
                'detailWarnings' => [
                    'No se pudo cargar toda la información del proyecto en este momento. Revisa los logs del servidor para más detalle.',
                ],
            ]);
        }
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
        $catalogs = $this->projectCatalogs(new MasterFilesRepository($this->db));
        $projectManagers = $this->projectManagersForSelection(new UsersRepository($this->db));
        $hasTasks = $repo->hasTasks($id);
        $deleteContext = $this->projectDeletionContext($id, $repo);

        $this->render('projects/edit', array_merge([
            'title' => 'Editar proyecto',
            'project' => $project,
            'projectManagers' => $projectManagers,
            'priorities' => $catalogs['priorities'],
            'statuses' => $catalogs['statuses'],
            'stageOptions' => self::STAGE_GATES,
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
        $masterRepo = new MasterFilesRepository($this->db);
        $catalogs = $this->projectCatalogs($masterRepo);
        $usersRepo = new UsersRepository($this->db);
        $projectManagers = $this->projectManagersForSelection($usersRepo);
        $hasTasks = $repo->hasTasks($id);
        $auditRepo = new AuditLogRepository($this->db);
        $previousEvaluations = $repo->riskEvaluationsForProject($id);

        try {
            $payload = $this->projectPayload($project, $delivery, $catalogs, $usersRepo);
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
            (new ProjectService($this->db))->recordHealthSnapshot($id);

            if ($methodologyChanged || $phaseChanged) {
                (new ProjectNodesRepository($this->db))->markTreeOutdated($id);
            }

            header('Location: /projects/' . $id);
            return;
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            $this->render('projects/edit', [
                'title' => 'Editar proyecto',
                'project' => $project,
                'projectManagers' => $projectManagers,
                'priorities' => $catalogs['priorities'],
                'statuses' => $catalogs['statuses'],
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
                'stageOptions' => self::STAGE_GATES,
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
        $payloadForDebug = [];

        try {
            $payload = $this->validatedProjectPayload($delivery, $this->projectCatalogs($masterRepo), $usersRepo);
            $payloadForDebug = $this->sanitizePayloadForDebug($payload);
            unset($payload['risk_catalog']);
            $projectId = $repo->create($payload);
            try {
                (new ProjectTreeService($this->db))->bootstrapFreshTree(
                    $projectId,
                    (string) ($payload['methodology'] ?? 'cascada'),
                    (int) ($this->auth->user()['id'] ?? 0)
                );
            } catch (\Throwable $treeException) {
                error_log(sprintf(
                    '[projects.store] Proyecto creado sin árbol documental | project_id=%d | methodology=%s | error=%s',
                    $projectId,
                    (string) ($payload['methodology'] ?? 'cascada'),
                    $treeException->getMessage()
                ));
                throw new \InvalidArgumentException(
                    'El proyecto se registró, pero falló la creación del árbol documental. Revisa los logs del servidor y vuelve a intentarlo.',
                    0,
                    $treeException
                );
            }
            try {
                (new NotificationService($this->db))->notify(
                    'project.created',
                    [
                        'project_id' => $projectId,
                        'project_name' => $payload['name'] ?? null,
                        'pm_id' => $payload['pm_id'] ?? null,
                    ],
                    (int) ($this->auth->user()['id'] ?? 0)
                );
            } catch (\Throwable $e) {
                error_log('Error al notificar creación de proyecto: ' . $e->getMessage());
            }
            header('Location: /projects/' . $projectId);
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
                'error' => $this->buildInsertDebugMessage($e, $payloadForDebug),
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
        $talentIds = array_values(array_unique(array_filter(array_map(
            static fn (array $assignment): int => (int) ($assignment['talent_id'] ?? 0),
            $assignments
        ))));
        $workloadByTalent = $repo->talentWorkloadBreakdown($talentIds, $id);
        foreach ($assignments as &$assignment) {
            $talentId = (int) ($assignment['talent_id'] ?? 0);
            $breakdown = $workloadByTalent[$talentId] ?? [
                'total_weekly_hours' => 0.0,
                'projects' => [],
            ];

            $capacityWeek = (float) ($assignment['capacidad_horaria'] ?? 0);
            if ($capacityWeek <= 0) {
                $capacityWeek = (float) ($assignment['talent_weekly_capacity'] ?? 0);
            }
            if ($capacityWeek <= 0) {
                $capacityWeek = 40.0;
            }

            $totalWeeklyHours = (float) ($breakdown['total_weekly_hours'] ?? 0);
            $totalAllocationPercent = $capacityWeek > 0 ? ($totalWeeklyHours / $capacityWeek) * 100 : 0.0;
            $availableAllocationPercent = 100.0 - $totalAllocationPercent;

            $assignment['workload_breakdown'] = $breakdown['projects'] ?? [];
            $assignment['workload_base_capacity_weekly_hours'] = $capacityWeek;
            $assignment['workload_total_weekly_hours'] = round($totalWeeklyHours, 2);
            $assignment['total_allocation_percent'] = round($totalAllocationPercent, 2);
            $assignment['available_allocation_percent'] = round($availableAllocationPercent, 2);
        }
        unset($assignment);
        $talents = (new TalentsRepository($this->db))->assignmentOptions();

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

        $tasksRepo = new TasksRepository($this->db);
        $tasks = $tasksRepo->forProject($id, $user);
        foreach ($tasks as &$task) {
            $task['in_schedule'] = (int) ($task['schedule_activity_id'] ?? 0) > 0;
        }
        unset($task);
        $canManage = $this->auth->can('projects.manage');
        $isTalent = $this->auth->hasRole('Talento');
        $isClosed = $this->normalizeStatus((string) ($project['status'] ?? '')) === 'closed';
        $canCreateTask = $canManage || ($isTalent && $tasksRepo->userCanCreateTaskInProject($user, $id));
        $talents = [];
        if ($canManage && !$isClosed) {
            $talents = (new TalentsRepository($this->db))->assignmentOptions();
        }

        $kanbanStatusOrder = ['todo', 'in_progress', 'review', 'blocked', 'done'];
        $kanbanStatusMeta = $this->taskStatusMeta();
        $kanbanColumns = [];
        foreach ($kanbanStatusOrder as $statusKey) {
            $kanbanColumns[$statusKey] = [];
        }
        foreach ($tasks as $task) {
            $taskStatus = strtolower(trim((string) ($task['status'] ?? 'todo')));
            $taskStatus = match ($taskStatus) {
                'pending' => 'todo',
                'completed' => 'done',
                default => $taskStatus,
            };
            if (!array_key_exists($taskStatus, $kanbanColumns)) {
                $taskStatus = 'todo';
            }
            $task['kanban_status'] = $taskStatus;
            $task['has_stopper'] = (int) ($task['open_stoppers'] ?? 0) > 0;
            $kanbanColumns[$taskStatus][] = $task;
        }

        $this->render('projects/tasks', [
            'title' => 'Tareas del proyecto',
            'project' => $project,
            'tasks' => $tasks,
            'kanbanColumns' => $kanbanColumns,
            'kanbanStatusOrder' => $kanbanStatusOrder,
            'kanbanStatusMeta' => $kanbanStatusMeta,
            'canManage' => $canManage,
            'canCreateTask' => $canCreateTask,
            'isClosed' => $isClosed,
            'talents' => $talents,
        ]);
    }

    public function updateTaskStatusApi(int $projectId, int $taskId): void
    {
        $this->requirePermission('projects.view');
        $user = $this->auth->user() ?? [];
        $canManage = $this->auth->can('projects.manage');
        $isTalent = $this->auth->hasRole('Talento');
        if (!$canManage && !$isTalent) {
            $this->json(['status' => 'error', 'message' => 'Acceso denegado.'], 403);
            return;
        }

        $projectsRepo = new ProjectsRepository($this->db);
        $project = $projectsRepo->findForUser($projectId, $user);
        if (!$project) {
            $this->json(['status' => 'error', 'message' => 'Proyecto no encontrado.'], 404);
            return;
        }

        $tasksRepo = new TasksRepository($this->db);
        $task = $tasksRepo->find($taskId, $user);
        if (!$task || (int) ($task['project_id'] ?? 0) !== $projectId) {
            $this->json(['status' => 'error', 'message' => 'Tarea no encontrada.'], 404);
            return;
        }

        if ($isTalent && !$canManage) {
            $userId = (int) ($user['id'] ?? 0);
            if (!$tasksRepo->canTalentUpdateTaskStatus($taskId, $userId)) {
                $this->json(['status' => 'error', 'message' => 'No puedes cambiar tareas asignadas a otros usuarios.'], 403);
                return;
            }
        }

        $status = strtolower(trim((string) ($_POST['status'] ?? '')));
        $status = match ($status) {
            'pending' => 'todo',
            'completed' => 'done',
            default => $status,
        };
        if (!in_array($status, ['todo', 'in_progress', 'review', 'blocked', 'done'], true)) {
            $this->json(['status' => 'error', 'message' => 'Estado inválido.'], 400);
            return;
        }

        $tasksRepo->updateStatus($taskId, $status);
        $updated = $tasksRepo->find($taskId, $user);
        $this->json([
            'status' => 'ok',
            'task' => $updated,
        ]);
    }

    public function updateScheduleActivityDatesApi(int $projectId, int $activityId): void
    {
        $this->requirePermission('projects.manage');
        $user = $this->auth->user() ?? [];
        $repo = new ProjectsRepository($this->db);
        $project = $repo->findForUser($projectId, $user);
        if (!$project) {
            $this->json(['status' => 'error', 'message' => 'Proyecto no encontrado.'], 404);
            return;
        }

        $startDate = trim((string) ($_POST['start_date'] ?? ''));
        $endDate = trim((string) ($_POST['end_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $this->json(['status' => 'error', 'message' => 'Formato de fecha inválido.'], 400);
            return;
        }

        try {
            $scheduleRepo = new ProjectSchedulesRepository($this->db);
            $activity = $scheduleRepo->updateActivityDates($projectId, $activityId, $startDate, $endDate);
            if (!$activity) {
                $this->json(['status' => 'error', 'message' => 'Actividad no encontrada.'], 404);
                return;
            }

            $this->json([
                'status' => 'ok',
                'activity' => $activity,
            ]);
            return;
        } catch (\InvalidArgumentException $e) {
            $this->json(['status' => 'error', 'message' => $e->getMessage()], 400);
            return;
        } catch (\Throwable $e) {
            $this->json(['status' => 'error', 'message' => 'No se pudieron actualizar las fechas.'], 500);
            return;
        }
    }

    public function billing(int $id): void
    {
        $this->requirePermission('projects.view');
        if (!$this->canViewBilling()) {
            http_response_code(403);
            exit('No tienes permisos para visualizar facturación.');
        }

        $this->render('projects/billing', $this->projectDetailData($id));
    }

    public function ganttGlobal(): void
    {
        $this->requirePermission('projects.view');
        $user = $this->auth->user() ?? [];
        $projectsRepo = new ProjectsRepository($this->db);
        $scheduleRepo = new ProjectSchedulesRepository($this->db);
        $projects = $projectsRepo->summary($user);

        $activeFilter = strtolower(trim((string) ($_GET['active'] ?? 'active')));
        $activeFilter = $activeFilter === 'all' ? 'all' : 'active';
        $selectedClient = trim((string) ($_GET['client'] ?? ''));
        $selectedPm = trim((string) ($_GET['pm'] ?? ''));
        $zoom = strtolower(trim((string) ($_GET['zoom'] ?? 'month')));
        if (!in_array($zoom, ['week', 'month', 'quarter'], true)) {
            $zoom = 'month';
        }

        $clientOptions = [];
        $pmOptions = [];
        foreach ($projects as $project) {
            $clientName = trim((string) ($project['client'] ?? ''));
            $pmName = trim((string) ($project['pm_name'] ?? ''));
            if ($clientName !== '') {
                $clientOptions[$clientName] = $clientName;
            }
            if ($pmName !== '') {
                $pmOptions[$pmName] = $pmName;
            }
        }
        ksort($clientOptions);
        ksort($pmOptions);

        $filteredProjects = array_values(array_filter($projects, function (array $project) use ($activeFilter, $selectedClient, $selectedPm): bool {
            if ($selectedClient !== '' && trim((string) ($project['client'] ?? '')) !== $selectedClient) {
                return false;
            }
            if ($selectedPm !== '' && trim((string) ($project['pm_name'] ?? '')) !== $selectedPm) {
                return false;
            }
            if ($activeFilter === 'active') {
                $status = strtolower(trim((string) ($project['status'] ?? $project['status_label'] ?? '')));
                if (in_array($status, ['closed', 'cancelled', 'cancelado', 'cerrado', 'finalizado', 'finalized'], true)) {
                    return false;
                }
            }

            return true;
        }));

        $projectIds = array_values(array_filter(array_map(static fn (array $project): int => (int) ($project['id'] ?? 0), $filteredProjects), static fn (int $id): bool => $id > 0));
        $activitiesByProject = $scheduleRepo->activitiesForProjects($projectIds);

        $ganttProjects = [];
        foreach ($filteredProjects as $project) {
            $projectId = (int) ($project['id'] ?? 0);
            if ($projectId <= 0) {
                continue;
            }

            $activities = $activitiesByProject[$projectId] ?? [];
            $projectStart = (string) ($project['start_date'] ?? '');
            $projectEnd = (string) ($project['end_date'] ?? '');
            if (($projectStart === '' || $projectEnd === '') && $activities !== []) {
                $starts = array_values(array_filter(array_map(static fn (array $activity): string => (string) ($activity['start_date'] ?? ''), $activities), static fn (string $value): bool => $value !== ''));
                $ends = array_values(array_filter(array_map(static fn (array $activity): string => (string) ($activity['end_date'] ?? ''), $activities), static fn (string $value): bool => $value !== ''));
                if ($projectStart === '' && $starts !== []) {
                    sort($starts);
                    $projectStart = $starts[0];
                }
                if ($projectEnd === '' && $ends !== []) {
                    sort($ends);
                    $projectEnd = $ends[count($ends) - 1];
                }
            }

            $children = [];
            foreach ($activities as $activity) {
                $children[] = [
                    'id' => (int) ($activity['id'] ?? 0),
                    'name' => (string) ($activity['name'] ?? 'Actividad'),
                    'item_type' => (string) ($activity['item_type'] ?? 'activity'),
                    'start_date' => (string) ($activity['start_date'] ?? ''),
                    'end_date' => (string) ($activity['end_date'] ?? ''),
                    'responsible_name' => (string) ($activity['responsible_name'] ?? ''),
                    'progress_percent' => (float) ($activity['progress_percent'] ?? 0),
                ];
            }

            $ganttProjects[] = [
                'id' => $projectId,
                'name' => (string) ($project['name'] ?? ''),
                'client' => (string) ($project['client'] ?? ''),
                'pm' => (string) ($project['pm_name'] ?? 'Sin PM'),
                'progress_percent' => (float) ($project['progress'] ?? 0),
                'status_label' => (string) ($project['status_label'] ?? $project['status'] ?? 'Sin estado'),
                'health' => $this->normalizeProjectHealth((string) ($project['health'] ?? $project['health_label'] ?? '')),
                'start_date' => $projectStart,
                'end_date' => $projectEnd,
                'children' => $children,
            ];
        }

        $this->render('pmo/gantt_global', [
            'title' => 'Gantt global',
            'projects' => $ganttProjects,
            'zoom' => $zoom,
            'activeFilter' => $activeFilter,
            'selectedClient' => $selectedClient,
            'selectedPm' => $selectedPm,
            'clientOptions' => array_values($clientOptions),
            'pmOptions' => array_values($pmOptions),
        ]);
    }

    private function normalizeProjectHealth(string $health): string
    {
        $normalized = strtolower(trim($health));

        return match ($normalized) {
            'critical', 'red', 'alto', 'high' => 'red',
            'at_risk', 'yellow', 'medio', 'medium' => 'yellow',
            default => 'green',
        };
    }

    private function taskStatusMeta(): array
    {
        return [
            'todo' => ['label' => 'Pendiente', 'icon' => '⏳', 'class' => 'status-muted', 'accent' => '#94a3b8'],
            'in_progress' => ['label' => 'En progreso', 'icon' => '🔄', 'class' => 'status-info', 'accent' => '#0ea5e9'],
            'review' => ['label' => 'En revisión', 'icon' => '📝', 'class' => 'status-warning', 'accent' => '#f59e0b'],
            'blocked' => ['label' => 'Bloqueada', 'icon' => '⛔', 'class' => 'status-danger', 'accent' => '#ef4444'],
            'done' => ['label' => 'Completada', 'icon' => '✅', 'class' => 'status-success', 'accent' => '#22c55e'],
        ];
    }

    public function storeTask(int $id): void
    {
        $user = $this->auth->user() ?? [];
        $canManage = $this->auth->can('projects.manage');
        $isTalent = $this->auth->hasRole('Talento');
        $tasksRepo = new TasksRepository($this->db);
        if (!$canManage && !$isTalent) {
            $this->denyAccess();
        }

        $repo = new ProjectsRepository($this->db);
        if ($canManage) {
            $project = $repo->findForUser($id, $user);
        } else {
            if (!$tasksRepo->userCanCreateTaskInProject($user, $id)) {
                http_response_code(403);
                exit('Solo puedes crear tareas en proyectos donde estás asignado.');
            }
            $project = $repo->find($id);
        }

        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        if ($this->normalizeStatus((string) ($project['status'] ?? '')) === 'closed') {
            http_response_code(400);
            exit('No puedes agregar tareas a un proyecto cerrado.');
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $status = strtolower(trim((string) ($_POST['status'] ?? 'todo')));
        $status = match ($status) {
            'pending' => 'todo',
            'completed' => 'done',
            default => $status,
        };
        $priority = strtolower(trim((string) ($_POST['priority'] ?? 'medium')));
        $estimatedHours = (float) ($_POST['estimated_hours'] ?? 0);
        $dueDate = trim((string) ($_POST['due_date'] ?? ''));
        $assigneeId = (int) ($_POST['assignee_id'] ?? 0);

        if ($title === '') {
            http_response_code(400);
            exit('El título de la tarea es obligatorio.');
        }

        $allowedPriorities = ['low', 'medium', 'high'];
        if (!in_array($priority, $allowedPriorities, true)) {
            http_response_code(400);
            exit('Prioridad de tarea inválida.');
        }
        if (!in_array($status, ['todo', 'pending', 'in_progress', 'review', 'blocked', 'done', 'completed'], true)) {
            http_response_code(400);
            exit('Estado de tarea inválido.');
        }

        if ($isTalent && !$canManage) {
            $talentId = $tasksRepo->talentIdForUser((int) ($user['id'] ?? 0));
            if ($talentId === null) {
                http_response_code(400);
                exit('Tu usuario no tiene un talento asociado para asignar la tarea.');
            }
            $assigneeId = $talentId;
        }

        $tasksRepo->createForProject($id, [
            'title' => $title,
            'status' => $status,
            'priority' => $priority,
            'estimated_hours' => $estimatedHours,
            'due_date' => $dueDate !== '' ? $dueDate : null,
            'assignee_id' => $assigneeId > 0 ? $assigneeId : null,
        ]);

        header('Location: /projects/' . $id . '/tasks');
    }

    public function saveSchedule(int $id): void
    {
        $this->requirePermission('projects.manage');
        $repo = new ProjectsRepository($this->db);
        $project = $repo->findForUser($id, $this->auth->user() ?? []);
        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        $raw = trim((string) ($_POST['activities_json'] ?? ''));
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded)) {
            http_response_code(400);
            exit('No se recibieron actividades válidas.');
        }

        $activities = $this->normalizeScheduleActivities($decoded);
        $scheduleRepo = new ProjectSchedulesRepository($this->db);
        $hadActivities = $scheduleRepo->hasActivities($id);
        $scheduleRepo->replaceActivities($id, $activities);
        (new AuditLogRepository($this->db))->log(
            (int) ($this->auth->user()['id'] ?? 0),
            'project_schedule',
            $id,
            $hadActivities ? 'updated' : 'created',
            [
                'source' => 'manual_editor',
                'activities_count' => count($activities),
            ]
        );
        header('Location: /projects/' . $id . '?view=cronograma');
    }

    public function importSchedulePreview(int $id): void
    {
        $this->requirePermission('projects.manage');
        $repo = new ProjectsRepository($this->db);
        $project = $repo->findForUser($id, $this->auth->user() ?? []);
        if (!$project) {
            $this->json(['status' => 'error', 'message' => 'Proyecto no encontrado.'], 404);
            return;
        }

        if (!isset($_FILES['excel_file']) || (int) ($_FILES['excel_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->json(['status' => 'error', 'message' => 'Debes subir un archivo Excel.'], 400);
            return;
        }

        try {
            $rows = $this->readSpreadsheetRows((string) ($_FILES['excel_file']['tmp_name'] ?? ''), (string) ($_FILES['excel_file']['name'] ?? ''));
            $validated = $this->validateImportedScheduleRows($rows, $id);
            $this->json(['status' => 'ok'] + $validated);
            return;
        } catch (\Throwable $e) {
            $this->json(['status' => 'error', 'message' => $e->getMessage()], 400);
            return;
        }
    }

    public function importScheduleConfirm(int $id): void
    {
        $this->requirePermission('projects.manage');
        $repo = new ProjectsRepository($this->db);
        $project = $repo->findForUser($id, $this->auth->user() ?? []);
        if (!$project) {
            $this->json(['status' => 'error', 'message' => 'Proyecto no encontrado.'], 404);
            return;
        }

        $rawRows = json_decode((string) ($_POST['rows_json'] ?? '[]'), true);
        $mode = strtolower(trim((string) ($_POST['mode'] ?? 'replace')));
        if (!is_array($rawRows) || empty($rawRows)) {
            $this->json(['status' => 'error', 'message' => 'No hay datos para importar.'], 400);
            return;
        }

        $validated = $this->validateImportedScheduleRows($rawRows, $id);
        if (!empty($validated['errors'])) {
            $this->json(['status' => 'error', 'message' => 'Corrige los errores antes de importar.', 'errors' => $validated['errors']], 400);
            return;
        }

        $scheduleRepo = new ProjectSchedulesRepository($this->db);
        $hadActivities = $scheduleRepo->hasActivities($id);
        if ($mode === 'merge') {
            $scheduleRepo->mergeActivities($id, $validated['activities']);
        } else {
            $scheduleRepo->replaceActivities($id, $validated['activities']);
        }
        (new AuditLogRepository($this->db))->log(
            (int) ($this->auth->user()['id'] ?? 0),
            'project_schedule',
            $id,
            $hadActivities ? 'updated' : 'created',
            [
                'source' => 'excel_import',
                'mode' => $mode === 'merge' ? 'merge' : 'replace',
                'activities_count' => count($validated['activities']),
            ]
        );

        $this->json(['status' => 'ok']);
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
        $talents = (new TalentsRepository($this->db))->assignmentOptions();
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

            header('Location: /projects/' . $id . '/outsourcing');
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

            header('Location: /projects/' . $id . '/outsourcing');
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

            header('Location: /projects/' . $projectId . '/outsourcing');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        }
    }


    public function updateTalentAssignmentStatus(int $projectId, int $assignmentId): void
    {
        $this->requirePermission('projects.manage');
        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $project = $repo->findForUser($projectId, $user);

        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        $status = strtolower(trim((string) ($_POST['assignment_status'] ?? '')));

        try {
            $assignment = $repo->findAssignmentById($projectId, $assignmentId);
            if (!$assignment) {
                http_response_code(404);
                exit('Asignación no encontrada.');
            }

            $currentStatus = strtolower((string) ($assignment['assignment_status'] ?? 'active'));
            $allowedTransitions = [
                'active' => ['paused'],
                'paused' => ['removed'],
                'removed' => [],
            ];

            if (!in_array($status, $allowedTransitions[$currentStatus] ?? [], true)) {
                throw new \InvalidArgumentException('Transición de estado no permitida. Usa el flujo Activo → Inactivo → Retirado.');
            }

            $repo->updateAssignmentStatus($projectId, $assignmentId, $status);

            (new AuditLogRepository($this->db))->log(
                (int) ($user['id'] ?? 0),
                'project_talent_assignment',
                $assignmentId,
                'status_updated',
                [
                    'project_id' => $projectId,
                    'previous_status' => $currentStatus,
                    'status' => $status,
                ]
            );

            header('Location: /projects/' . $projectId . '/talent');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        }
    }

    public function updateTalentAssignmentWorkload(int $projectId, int $assignmentId): void
    {
        $this->requirePermission('projects.manage');
        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $project = $repo->findForUser($projectId, $user);

        if (!$project) {
            if ($this->wantsJson()) {
                $this->json(['success' => false, 'message' => 'Proyecto no encontrado.'], 404);
                return;
            }
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        try {
            $allocationPercentRaw = trim((string) ($_POST['allocation_percent'] ?? ''));
            $weeklyHoursRaw = trim((string) ($_POST['weekly_hours'] ?? ''));
            $editedField = strtolower(trim((string) ($_POST['edited_field'] ?? 'allocation_percent')));
            $forceUpdate = (string) ($_POST['force_update'] ?? '0') === '1';

            if (!in_array($editedField, ['allocation_percent', 'weekly_hours'], true)) {
                throw new \InvalidArgumentException('El campo editado no es válido.');
            }

            if ($allocationPercentRaw !== '' && !is_numeric($allocationPercentRaw)) {
                throw new \InvalidArgumentException('El porcentaje de dedicación debe ser numérico.');
            }

            if ($weeklyHoursRaw !== '' && !is_numeric($weeklyHoursRaw)) {
                throw new \InvalidArgumentException('Las horas semanales deben ser numéricas.');
            }

            $result = $repo->updateAssignmentWorkload(
                $projectId,
                $assignmentId,
                $allocationPercentRaw !== '' ? (float) $allocationPercentRaw : null,
                $weeklyHoursRaw !== '' ? (float) $weeklyHoursRaw : null,
                $editedField,
                $forceUpdate
            );

            if (!($result['updated'] ?? false) && ($result['requires_confirmation'] ?? false)) {
                $this->json([
                    'success' => false,
                    'requires_confirmation' => true,
                    'message' => (string) ($result['message'] ?? 'Las horas registradas en timesheet superan la nueva dedicación.'),
                    'data' => $result,
                ], 409);
                return;
            }

            (new AuditLogRepository($this->db))->log(
                (int) ($user['id'] ?? 0),
                'project_talent_assignment',
                $assignmentId,
                'workload_updated',
                [
                    'project_id' => $projectId,
                    'edited_field' => $editedField,
                    'force_update' => $forceUpdate ? 1 : 0,
                    'before' => $result['before'] ?? [],
                    'after' => $result['after'] ?? [],
                    'capacity_week' => $result['capacity_week'] ?? null,
                    'timesheet_conflicts' => $result['timesheet_conflicts'] ?? [],
                ]
            );

            if ($this->wantsJson()) {
                $this->json([
                    'success' => true,
                    'message' => 'Dedicación actualizada correctamente.',
                    'data' => $result,
                ]);
                return;
            }

            header('Location: /projects/' . $projectId . '/talent');
            return;
        } catch (\InvalidArgumentException $e) {
            if ($this->wantsJson()) {
                $this->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
                return;
            }
            http_response_code(400);
            exit($e->getMessage());
        } catch (\Throwable $e) {
            error_log('Error al actualizar dedicación de asignación: ' . $e->getMessage());
            if ($this->wantsJson()) {
                $this->json([
                    'success' => false,
                    'message' => 'No se pudo actualizar la dedicación de la asignación.',
                ], 500);
                return;
            }
            http_response_code(500);
            exit('No se pudo actualizar la dedicación de la asignación.');
        }
    }

    public function deleteTalentAssignment(int $projectId, int $assignmentId): void
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
            $assignment = $repo->findAssignmentById($projectId, $assignmentId);
            if (!$assignment) {
                http_response_code(404);
                exit('Asignación no encontrada.');
            }

            $currentStatus = strtolower((string) ($assignment['assignment_status'] ?? 'active'));
            if ($currentStatus !== 'removed') {
                throw new \InvalidArgumentException('Solo se puede eliminar definitivamente una asignación en estado Retirado.');
            }

            $repo->deleteAssignmentPermanently($projectId, $assignmentId);

            (new AuditLogRepository($this->db))->log(
                (int) ($user['id'] ?? 0),
                'project_talent_assignment',
                $assignmentId,
                'deleted',
                [
                    'project_id' => $projectId,
                    'previous_status' => $currentStatus,
                ]
            );

            header('Location: /projects/' . $projectId . '/talent');
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
            header('Location: /projects/' . $id . '/close');
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
            try {
                (new NotificationService($this->db))->notify(
                    'project.closed',
                    [
                        'project_id' => $id,
                        'project_name' => $project['name'] ?? null,
                        'pm_id' => $project['pm_id'] ?? null,
                    ],
                    (int) ($this->auth->user()['id'] ?? 0)
                );
            } catch (\Throwable $e) {
                error_log('Error al notificar cierre de proyecto: ' . $e->getMessage());
            }
            header('Location: /projects/' . $id);
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

        (new ProjectService($this->db))->recordHealthSnapshot($id);

        header('Location: /projects/' . $id . '?progress=updated');
    }

    public function createNote(int $id): void
    {
        $this->requirePermission('projects.view');

        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $project = $repo->findForUser($id, $user);

        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        $note = trim((string) ($_POST['note'] ?? ''));
        if ($note === '') {
            http_response_code(400);
            exit('La nota no puede estar vacía.');
        }

        $auditRepo = new AuditLogRepository($this->db);
        $auditRepo->log(
            $user['id'] ?? null,
            'project_note',
            $id,
            'project_note_created',
            [
                'note' => $note,
            ]
        );

        header('Location: /projects/' . $id . '?view=seguimiento');
    }

    public function createStopper(int $id): void
    {
        $this->requirePermission('project.stoppers.manage');

        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $project = $repo->findForUser($id, $user);
        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        try {
            $payload = $this->stopperPayloadFromRequest();
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        }

        if ($payload['status'] === 'cerrado') {
            http_response_code(400);
            exit('Un bloqueo nuevo no puede iniciar cerrado.');
        }

        (new ProjectStoppersRepository($this->db))->create($id, $payload, (int) ($user['id'] ?? 0));
        (new ProjectService($this->db))->recordHealthSnapshot($id);

        header('Location: /projects/' . $id . '?view=bloqueos');
    }

    public function updateStopper(int $id, int $stopperId): void
    {
        $this->requirePermission('project.stoppers.manage');

        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $project = $repo->findForUser($id, $user);
        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        try {
            $payload = $this->stopperPayloadFromRequest();
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        }

        if ($payload['status'] === 'cerrado') {
            http_response_code(400);
            exit('Para cerrar un bloqueo utiliza la acción de cierre con comentario obligatorio.');
        }

        (new ProjectStoppersRepository($this->db))->update($id, $stopperId, $payload, (int) ($user['id'] ?? 0));
        (new ProjectService($this->db))->recordHealthSnapshot($id);

        header('Location: /projects/' . $id . '?view=bloqueos');
    }

    public function closeStopper(int $id, int $stopperId): void
    {
        $this->requirePermission('project.stoppers.close');

        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $project = $repo->findForUser($id, $user);
        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        $closureComment = trim((string) ($_POST['closure_comment'] ?? ''));
        if ($closureComment === '') {
            http_response_code(400);
            exit('No se puede cerrar un bloqueo sin comentario de cierre.');
        }

        (new ProjectStoppersRepository($this->db))->close($id, $stopperId, $closureComment, (int) ($user['id'] ?? 0));
        (new ProjectService($this->db))->recordHealthSnapshot($id);

        header('Location: /projects/' . $id . '?view=bloqueos');
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
                $destination = $redirectId > 0 ? '/projects/' . $redirectId . '/talent' : '/projects';
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

    public function saveRequiredGitRepository(int $projectId): void
    {
        $this->requirePermission('projects.manage');

        try {
            $url = trim((string) ($_POST['repository_url'] ?? ''));
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
                throw new \InvalidArgumentException('Ingresa una URL válida');
            }

            $nodesRepo = new ProjectNodesRepository($this->db);
            $tree = $nodesRepo->treeWithFiles($projectId);
            $flatNodes = $this->flattenProjectNodes($tree);
            $rootNode = null;
            foreach ($flatNodes as $node) {
                if (($node['code'] ?? '') === 'ROOT') {
                    $rootNode = $node;
                    break;
                }
            }
            if (!$rootNode) {
                throw new \RuntimeException('No se encontró el nodo raíz del proyecto.');
            }

            $userId = (int) ($this->auth->user()['id'] ?? 0);
            $payload = [
                'git_repository_url' => $url,
                'updated_at' => date('c'),
                'updated_by' => $userId > 0 ? $userId : null,
            ];
            $this->upsertRequiredDocumentsMeta($projectId, (int) ($rootNode['id'] ?? 0), $payload, $userId);

            $this->json([
                'success' => true,
                'message' => 'Repositorio Git guardado.',
                'data' => $payload,
            ]);
            return;
        } catch (\Throwable $e) {
            $status = $e instanceof \InvalidArgumentException ? 400 : 500;
            $this->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'No se pudo guardar el repositorio.',
            ], $status);
            return;
        }
    }

    public function saveRequiredDocumentUpload(int $projectId): void
    {
        $this->requirePermission('projects.manage');

        try {
            $this->ensureDocumentFlowSchema();
            $key = trim((string) ($_POST['required_document_key'] ?? ''));
            if (!in_array($key, self::REQUIRED_DOCUMENT_UPLOAD_KEYS, true)) {
                throw new \InvalidArgumentException('Documento obligatorio inválido.');
            }

            $meta = $this->collectDocumentUploadMeta($key);
            $upload = $_FILES['required_document_file'] ?? null;
            $hasUpload = is_array($upload) && (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);

            $nodesRepo = new ProjectNodesRepository($this->db);
            $tree = $nodesRepo->treeWithFiles($projectId);
            $flatNodes = $this->flattenProjectNodes($tree);
            $rootNode = null;
            foreach ($flatNodes as $node) {
                if (($node['code'] ?? '') === 'ROOT') {
                    $rootNode = $node;
                    break;
                }
            }
            if (!$rootNode) {
                throw new \RuntimeException('No se encontró el nodo raíz del proyecto.');
            }

            $userId = (int) ($this->auth->user()['id'] ?? 0);
            $requiredFilesNode = $nodesRepo->findNodeByCode($projectId, self::REQUIRED_DOCUMENTS_FILES_CODE);
            if (!$requiredFilesNode) {
                $requiredFilesNodeId = $nodesRepo->createNode([
                    'project_id' => $projectId,
                    'parent_id' => (int) ($rootNode['id'] ?? 0),
                    'code' => self::REQUIRED_DOCUMENTS_FILES_CODE,
                    'node_type' => 'folder',
                    'title' => 'Documentos obligatorios del proyecto',
                    'description' => 'Repositorio técnico para documentos obligatorios.',
                    'sort_order' => 998,
                    'created_by' => $userId > 0 ? $userId : null,
                ]);
            } else {
                $requiredFilesNodeId = (int) ($requiredFilesNode['id'] ?? 0);
            }
            if ($requiredFilesNodeId <= 0) {
                throw new \RuntimeException('No se pudo resolver la carpeta de documentos obligatorios.');
            }
            $requiredDocumentFileCode = self::REQUIRED_DOCUMENTS_FILES_CODE . '-FILE-' . strtoupper($key);
            $existingNode = $nodesRepo->findNodeByCode($projectId, $requiredDocumentFileCode);
            $removeExistingFile = isset($_POST['remove_required_document_file']) && (string) ($_POST['remove_required_document_file']) === '1';
            $deletedExistingNodeId = 0;
            if ($removeExistingFile && $existingNode && (int) ($existingNode['id'] ?? 0) > 0) {
                $deletedExistingNodeId = (int) ($existingNode['id'] ?? 0);
                $nodesRepo->deleteNode($projectId, $deletedExistingNodeId, $userId);
                $existingNode = null;
            }
            if (!$hasUpload && !$existingNode && !$removeExistingFile) {
                throw new \InvalidArgumentException('Selecciona un archivo para continuar.');
            }

            $tag = trim((string) ($_POST['required_document_tag'] ?? ''));
            if ($tag !== '') {
                $decodedTags = json_decode((string) ($meta['document_tags'] ?? '[]'), true);
                $normalizedTags = is_array($decodedTags) ? $decodedTags : [];
                $normalizedTags[] = $tag;
                $meta['document_tags'] = json_encode(
                    array_values(
                        array_unique(
                            array_filter(
                                array_map(static fn ($value): string => trim((string) $value), $normalizedTags)
                            )
                        )
                    ),
                    JSON_UNESCAPED_UNICODE
                );
            }

            if ($hasUpload && $existingNode && (int) ($existingNode['id'] ?? 0) > 0) {
                $nodesRepo->deleteNode($projectId, (int) ($existingNode['id'] ?? 0), $userId);
                $existingNode = null;
            }

            $result = [];
            if ($hasUpload) {
                $result = $nodesRepo->createFileNode($projectId, $requiredFilesNodeId, $upload, $userId, $meta);
                $savedNodeId = (int) ($result['id'] ?? 0);
                if ($savedNodeId <= 0) {
                    throw new \RuntimeException('No se pudo guardar el documento obligatorio.');
                }
                $this->db->execute(
                    'UPDATE project_nodes SET code = :code WHERE id = :id AND project_id = :project_id',
                    [
                        ':code' => $requiredDocumentFileCode,
                        ':id' => $savedNodeId,
                        ':project_id' => $projectId,
                    ]
                );
            } elseif ($existingNode) {
                $savedNodeId = (int) ($existingNode['id'] ?? 0);
                if ($savedNodeId <= 0) {
                    throw new \RuntimeException('No se encontró el documento obligatorio para editar.');
                }
                $this->db->execute(
                    'UPDATE project_nodes
                     SET document_type = :document_type,
                         document_tags = :document_tags,
                         document_version = :document_version,
                         description = :description
                     WHERE id = :id AND project_id = :project_id',
                    [
                        ':document_type' => $meta['document_type'] ?? null,
                        ':document_tags' => $meta['document_tags'] ?? null,
                        ':document_version' => $meta['document_version'] ?? null,
                        ':description' => $meta['description'] ?? null,
                        ':id' => $savedNodeId,
                        ':project_id' => $projectId,
                    ]
                );
                $savedNode = $this->db->fetchOne(
                    'SELECT id, title, file_path, created_at
                     FROM project_nodes
                     WHERE id = :id AND project_id = :project_id',
                    [
                        ':id' => $savedNodeId,
                        ':project_id' => $projectId,
                    ]
                ) ?: [];
                $result = [
                    'id' => $savedNodeId,
                    'code' => $requiredDocumentFileCode,
                    'file_name' => (string) ($savedNode['title'] ?? ''),
                    'storage_path' => (string) ($savedNode['file_path'] ?? ''),
                    'created_at' => (string) ($savedNode['created_at'] ?? date('Y-m-d H:i:s')),
                    'description' => (string) ($meta['description'] ?? ''),
                    'document_status' => $meta['document_status'] ?? 'final',
                    'tags' => json_decode((string) ($meta['document_tags'] ?? '[]'), true) ?: [],
                    'version' => (string) ($meta['document_version'] ?? ''),
                    'document_type' => (string) ($meta['document_type'] ?? ''),
                ];
            } else {
                $result = [
                    'id' => null,
                    'code' => $requiredDocumentFileCode,
                    'file_name' => '',
                    'storage_path' => '',
                    'created_at' => date('Y-m-d H:i:s'),
                    'description' => (string) ($meta['description'] ?? ''),
                    'document_status' => $meta['document_status'] ?? 'final',
                    'tags' => json_decode((string) ($meta['document_tags'] ?? '[]'), true) ?: [],
                    'version' => (string) ($meta['document_version'] ?? ''),
                    'document_type' => (string) ($meta['document_type'] ?? ''),
                ];
            }

            $auditRepo = new AuditLogRepository($this->db);
            $auditEntityId = isset($result['id']) ? (int) ($result['id'] ?? 0) : 0;
            if ($auditEntityId <= 0) {
                $auditEntityId = $deletedExistingNodeId;
            }
            if ($auditEntityId > 0) {
                $auditRepo->log(
                    $userId,
                    'project_node_file',
                    $auditEntityId,
                    $hasUpload ? 'document_uploaded' : ($removeExistingFile ? 'document_removed' : 'document_metadata_updated'),
                    [
                        'document_type' => $meta['document_type'] ?? null,
                        'document_tags' => $meta['document_tags'] ?? null,
                        'document_version' => $meta['document_version'] ?? null,
                        'description' => $meta['description'] ?? null,
                        'document_status' => $meta['document_status'] ?? null,
                        'required_document_key' => $key,
                        'required_document_upload' => $hasUpload,
                        'required_document_file_removed' => $removeExistingFile,
                    ]
                );
            }

            $recordedBy = trim((string) (
                $this->auth->user()['name']
                ?? $this->auth->user()['full_name']
                ?? $this->auth->user()['email']
                ?? ('Usuario #' . $userId)
            ));
            $recordedDate = date('d/m/Y');
            $fileId = isset($result['id']) ? (int) ($result['id'] ?? 0) : 0;
            $fileStoragePath = trim((string) ($result['storage_path'] ?? ''));
            $hasFile = $fileId > 0 && $fileStoragePath !== '';
            $fileUrl = $hasFile ? '/projects/' . $projectId . '/nodes/' . $fileId . '/download' : '';
            $message = ($removeExistingFile && !$hasUpload && !$hasFile)
                ? 'Archivo eliminado. El documento obligatorio quedó pendiente.'
                : 'Documento obligatorio guardado.';

            $contractEndDate = null;
            if ($key === 'contrato') {
                $contractEndDate = (string) ($meta['contract_end_date'] ?? '');
                $this->upsertRequiredDocumentsMeta(
                    $projectId,
                    (int) ($rootNode['id'] ?? 0),
                    [
                        'contract_end_date' => $contractEndDate,
                        'contract_notifications_updated_at' => date('c'),
                        'contract_notifications_updated_by' => $userId > 0 ? $userId : null,
                    ],
                    $userId
                );
            }

            $this->json([
                'success' => true,
                'message' => $message,
                'document_id' => $fileId > 0 ? $fileId : null,
                'data' => $result,
                'required_document' => [
                    'key' => $key,
                    'recorded_by' => $recordedBy,
                    'recorded_date' => $recordedDate,
                    'description' => (string) ($meta['description'] ?? ''),
                    'document_type' => (string) ($meta['document_type'] ?? ''),
                    'document_tags' => json_decode((string) ($meta['document_tags'] ?? '[]'), true) ?: [],
                    'document_version' => (string) ($meta['document_version'] ?? ''),
                    'file_id' => $fileId > 0 ? $fileId : null,
                    'file_name' => (string) ($result['file_name'] ?? ''),
                    'file_url' => $fileUrl,
                    'has_file' => $hasFile,
                    'completed' => $hasFile,
                    'contract_end_date' => $contractEndDate,
                ],
            ]);
            return;
        } catch (\Throwable $e) {
            $status = $e instanceof \InvalidArgumentException ? 400 : 500;
            $this->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'No se pudo guardar el documento obligatorio.',
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
            header('Location: /projects/' . $projectId);
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
            header('Location: /projects/' . $projectId);
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

            header('Location: /projects/' . $projectId);
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
        if (!$this->auth->can('projects.view') && !$this->auth->can('projects.manage')) {
            http_response_code(403);
            exit('Acceso denegado');
        }

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
            header('Content-Disposition: inline; filename="' . $downloadName . '"');
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
        $projectNotes = (new AuditLogRepository($this->db))->listForEntity('project_note', $id, 50);
        $pendingControls = count($nodesRepo->pendingCriticalNodes($id));
        $approvedDocuments = $this->countApprovedDocuments($projectNodes);
        $loggedHours = $repo->timesheetHoursForProject($id);
        $timesheetEntries = $repo->timesheetEntriesForProject($id, 300);
        $dependencies = $repo->dependencySummary($id);
        $deleteContext = $this->projectDeletionContext($id, $repo);
        $projectService = new ProjectService($this->db);
        $healthScore = $projectService->calculateProjectHealthReport($id);
        $healthHistory = $projectService->history($id, 30);
        $billingRepo = new ProjectBillingRepository($this->db);
        $billingConfig = $billingRepo->config($id);
        $invoices = $billingRepo->invoices($id);
        $billingPlanItems = $billingRepo->billingPlan($id);
        $billingFinancialSummary = $billingRepo->financialSummary($id, $billingConfig);
        $availablePlanItemsForInvoice = $billingRepo->availablePlanItemsForInvoicing($id);
        $stoppersRepo = new ProjectStoppersRepository($this->db);
        $stopperMetrics = $stoppersRepo->metricsForProject($id);
        $stopperBoard = $stoppersRepo->byImpactOpen($id);
        $tasksForSchedule = (new TasksRepository($this->db))->forProject($id, $user);
        $scheduleRepo = new ProjectSchedulesRepository($this->db);
        $scheduleActivities = $scheduleRepo->activitiesForProject($id);
        $scheduleSummary = $scheduleRepo->summary($id);
        $scheduleAudit = (new AuditLogRepository($this->db))->listForEntity('project_schedule', $id, 200);
        $scheduleCreatedBy = null;
        $scheduleCreatedAt = null;
        if (!empty($scheduleAudit)) {
            $chronological = array_reverse($scheduleAudit);
            foreach ($chronological as $entry) {
                if (($entry['action'] ?? '') !== 'created') {
                    continue;
                }
                $scheduleCreatedBy = isset($entry['user_id']) ? (int) $entry['user_id'] : null;
                $scheduleCreatedAt = (string) ($entry['created_at'] ?? '');
                break;
            }
            if (!$scheduleCreatedAt) {
                $firstAudit = $chronological[0] ?? null;
                if (is_array($firstAudit)) {
                    $scheduleCreatedBy = isset($firstAudit['user_id']) ? (int) $firstAudit['user_id'] : null;
                    $scheduleCreatedAt = (string) ($firstAudit['created_at'] ?? '');
                }
            }
        }
        if (!$scheduleCreatedAt && !empty($scheduleActivities)) {
            $scheduleCreatedAt = (string) ($scheduleActivities[0]['created_at'] ?? $scheduleActivities[0]['updated_at'] ?? '');
        }
        $pmoSnapshot = [];
        $pmoAlerts = [];
        $pmoHoursTrend = [];
        $pmoActiveBlockers = [];
        $detailWarnings = [];
        try {
            $pmoAutomation = new PmoAutomationService($this->db);
            $pmoSnapshot = $pmoAutomation->ensureTodaySnapshotForProject($id);
            $pmoAlerts = $pmoAutomation->latestAlertsForProject($id, 10);
            $pmoHoursTrend = $pmoAutomation->hoursTrendForProject($id, 4);
            $pmoActiveBlockers = $pmoAutomation->activeBlockersForProject($id, 8);
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[projects.detail.pmo] Error al cargar PMO automático para proyecto %d: %s',
                $id,
                $e->getMessage()
            ));
            $detailWarnings[] = 'No se pudo cargar la automatización PMO en este momento.';
        }

        return array_merge([
            'title' => 'Detalle de proyecto',
            'project' => $project,
            'healthScore' => $healthScore,
            'healthHistory' => $healthHistory,
            'assignments' => $assignments,
            'currentUser' => $this->auth->user() ?? [],
            'canManage' => $this->auth->can('projects.manage'),
            'projectNodes' => $projectNodes,
            'progressPhases' => $progress['phases'] ?? [],
            'documentFlowConfig' => $config['document_flow'] ?? [],
            'accessRoles' => $config['access']['roles'] ?? [],
            'canUpdateProgress' => $this->userCanUpdateProjectProgress(),
            'progressHistory' => $progressHistory,
            'projectNotes' => $projectNotes,
            'progressIndicators' => [
                'approved_documents' => $approvedDocuments,
                'pending_controls' => $pendingControls,
                'logged_hours' => $loggedHours,
            ],
            'timesheetEntries' => $timesheetEntries,
            'billingConfig' => $billingConfig,
            'projectInvoices' => $invoices,
            'billingPlanItems' => $billingPlanItems,
            'billingFinancialSummary' => $billingFinancialSummary,
            'availablePlanItemsForInvoice' => $availablePlanItemsForInvoice,
            'canViewBilling' => $this->canViewBilling(),
            'canManageBilling' => $this->canRegisterBilling(),
            'canDeleteInvoice' => $this->auth->hasRole('Administrador'),
            'contractCurrencies' => self::CONTRACT_CURRENCIES,
            'planItemTypes' => self::PLAN_ITEM_TYPES,
            'invoiceStatuses' => self::INVOICE_STATUSES,
            'stoppers' => $stoppersRepo->forProject($id),
            'stopperMetrics' => $stopperMetrics,
            'stopperBoard' => $stopperBoard,
            'stopperTypeOptions' => self::STOPPER_TYPES,
            'stopperImpactOptions' => self::STOPPER_IMPACT_LEVELS,
            'stopperAreaOptions' => self::STOPPER_AFFECTED_AREAS,
            'stopperStatusOptions' => array_values(array_filter(self::STOPPER_STATUSES, static fn (string $status): bool => $status !== 'cerrado')),
            'canCloseStoppers' => $this->auth->can('project.stoppers.close'),
            'responsibleUsers' => (new UsersRepository($this->db))->findByRoleNames(['Administrador', 'PMO', 'Líder de Proyecto', 'Talento']),
            'tasksForSchedule' => $tasksForSchedule,
            'scheduleActivities' => $scheduleActivities,
            'scheduleSummary' => $scheduleSummary,
            'scheduleCreatedBy' => $scheduleCreatedBy,
            'scheduleCreatedAt' => $scheduleCreatedAt ?: null,
            'pmoSnapshot' => $pmoSnapshot,
            'pmoAlerts' => $pmoAlerts,
            'pmoHoursTrend' => $pmoHoursTrend,
            'pmoActiveBlockers' => $pmoActiveBlockers,
            'detailWarnings' => $detailWarnings,
        ], $deleteContext);
    }


    public function toggleBillingConfig(int $projectId): void
    {
        $this->requirePermission('project.billing.manage');
        if (!$this->isBillingModuleEnabled()) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'La facturación de proyectos está deshabilitada en Gobierno.']);
            return;
        }
        $repo = new ProjectsRepository($this->db);
        $project = $repo->findForUser($projectId, $this->auth->user() ?? []);
        if (!$project) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Proyecto no encontrado']);
            return;
        }

        $rawBody = file_get_contents('php://input') ?: '';
        $decoded = json_decode($rawBody, true);
        $isBillable = (int) ($decoded['is_billable'] ?? $_POST['is_billable'] ?? 0) === 1;

        $billingRepo = new ProjectBillingRepository($this->db);

        try {
            $billingRepo->updateBillableStatus($projectId, $isBillable ? 1 : 0);
            $updated = $billingRepo->config($projectId);
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'error',
                'message' => 'No se pudo guardar el estado de facturación.',
            ]);
            return;
        }

        (new AuditLogRepository($this->db))->log(
            (int) ($this->auth->user()['id'] ?? 0),
            'project_billing_toggle',
            $projectId,
            'updated',
            ['is_billable' => (int) ($updated['is_billable'] ?? 0)]
        );

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'ok', 'is_billable' => (int) ($updated['is_billable'] ?? 0)]);
    }

    public function saveBillingConfig(int $projectId): void
    {
        $this->requirePermission('project.billing.manage');
        $repo = new ProjectsRepository($this->db);
        $project = $repo->findForUser($projectId, $this->auth->user() ?? []);
        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        $payload = $this->billingPayloadFromRequest($project);
        (new ProjectBillingRepository($this->db))->updateConfig($projectId, $payload);
        (new AuditLogRepository($this->db))->log((int) ($this->auth->user()['id'] ?? 0), 'project_billing_config', $projectId, 'updated', $payload);

        $isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'ok']);
            return;
        }

        header('Location: /projects/' . $projectId . '/billing');
    }

    public function createInvoice(int $projectId): void
    {
        if (!$this->canRegisterBilling()) {
            http_response_code(403);
            exit('Solo Admin y PM pueden registrar facturas.');
        }
        $repo = new ProjectsRepository($this->db);
        $project = $repo->findForUser($projectId, $this->auth->user() ?? []);
        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        try {
            $payload = $this->invoicePayloadFromRequest($projectId);
            $billingConfig = (new ProjectBillingRepository($this->db))->config($projectId);
            $invoiceTotals = (new ProjectBillingRepository($this->db))->invoiceTotals($projectId);
            $this->validateInvoiceAgainstContract($payload, $billingConfig, $invoiceTotals);

            $invoiceId = (new ProjectBillingRepository($this->db))->createInvoice($projectId, $payload, (int) ($this->auth->user()['id'] ?? 0));
            (new AuditLogRepository($this->db))->log((int) ($this->auth->user()['id'] ?? 0), 'project_invoice', $invoiceId, 'created', ['project_id' => $projectId] + $payload);
            try {
                (new PmoAutomationService($this->db))->syncBillingPlanAlertsForProject($projectId, new DateTimeImmutable('today'));
            } catch (\Throwable $e) {
                error_log(sprintf('[projects.invoice.create] No se pudo sincronizar alertas de facturación (%d): %s', $projectId, $e->getMessage()));
            }
            try {
                (new NotificationService($this->db))->notify(
                    'project.billing_invoice_registered',
                    [
                        'project_id' => $projectId,
                        'project_name' => $project['name'] ?? null,
                        'invoice_id' => $invoiceId,
                        'invoice_number' => $payload['invoice_number'] ?? null,
                        'issued_at' => $payload['issued_at'] ?? null,
                        'amount' => $payload['amount'] ?? null,
                        'currency_code' => $payload['currency_code'] ?? null,
                        'plan_item_ids' => $payload['plan_item_ids'] ?? [],
                    ],
                    (int) ($this->auth->user()['id'] ?? 0)
                );
            } catch (\Throwable $e) {
                error_log(sprintf('[projects.invoice.create] No se pudo notificar factura registrada (%d): %s', $projectId, $e->getMessage()));
            }
            header('Location: /projects/' . $projectId . '/billing');
        } catch (\InvalidArgumentException $e) {
            http_response_code(422);
            exit($e->getMessage());
        }
    }

    public function createBillingPlanItem(int $projectId): void
    {
        $this->requirePermission('project.billing.manage');
        $repo = new ProjectsRepository($this->db);
        $project = $repo->findForUser($projectId, $this->auth->user() ?? []);
        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado.');
        }

        try {
            $payload = $this->planItemPayloadFromRequest();
            $createdIds = (new ProjectBillingRepository($this->db))->createPlanItem($projectId, $payload);
            (new AuditLogRepository($this->db))->log(
                (int) ($this->auth->user()['id'] ?? 0),
                'project_billing_plan',
                $projectId,
                'created',
                ['created_ids' => $createdIds, 'payload' => $payload]
            );
            header('Location: /projects/' . $projectId . '/billing');
        } catch (\InvalidArgumentException $e) {
            http_response_code(422);
            exit($e->getMessage());
        }
    }

    public function updateBillingPlanItem(int $projectId, int $itemId): void
    {
        $this->requirePermission('project.billing.manage');
        $repo = new ProjectsRepository($this->db);
        $project = $repo->findForUser($projectId, $this->auth->user() ?? []);
        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado.');
        }

        try {
            $payload = $this->planItemPayloadFromRequest();
            (new ProjectBillingRepository($this->db))->updatePlanItem($projectId, $itemId, $payload);
            (new AuditLogRepository($this->db))->log(
                (int) ($this->auth->user()['id'] ?? 0),
                'project_billing_plan',
                $itemId,
                'updated',
                $payload
            );
            header('Location: /projects/' . $projectId . '/billing');
        } catch (\InvalidArgumentException $e) {
            http_response_code(422);
            exit($e->getMessage());
        }
    }

    public function deleteBillingPlanItem(int $projectId, int $itemId): void
    {
        $this->requirePermission('project.billing.manage');
        (new ProjectBillingRepository($this->db))->deletePlanItem($projectId, $itemId);
        (new AuditLogRepository($this->db))->log(
            (int) ($this->auth->user()['id'] ?? 0),
            'project_billing_plan',
            $itemId,
            'deleted',
            ['project_id' => $projectId]
        );
        header('Location: /projects/' . $projectId . '/billing');
    }

    public function updateInvoice(int $projectId, int $invoiceId): void
    {
        if (!$this->canRegisterBilling()) {
            http_response_code(403);
            exit('Solo Admin y PM pueden editar facturas.');
        }
        $billingRepo = new ProjectBillingRepository($this->db);
        $currentInvoice = $billingRepo->findInvoice($projectId, $invoiceId);
        if (!$currentInvoice) {
            http_response_code(404);
            exit('Factura no encontrada.');
        }

        $payload = $this->invoicePayloadFromRequest($projectId, $currentInvoice);
        $billingRepo->updateInvoice($projectId, $invoiceId, $payload);
        $this->cleanupOldInvoicePdf(
            $projectId,
            trim((string) ($currentInvoice['attachment_path'] ?? '')),
            trim((string) ($payload['attachment_path'] ?? ''))
        );
        try {
            (new PmoAutomationService($this->db))->syncBillingPlanAlertsForProject($projectId, new DateTimeImmutable('today'));
        } catch (\Throwable $e) {
            error_log(sprintf('[projects.invoice.update] No se pudo sincronizar alertas de facturación (%d): %s', $projectId, $e->getMessage()));
        }
        header('Location: /projects/' . $projectId . '/billing');
    }

    public function downloadInvoicePdf(int $projectId, int $invoiceId): void
    {
        $this->requirePermission('project.billing.view');
        $invoice = (new ProjectBillingRepository($this->db))->findInvoice($projectId, $invoiceId);
        if (!$invoice) {
            http_response_code(404);
            exit('Factura no encontrada.');
        }

        $relativePath = (string) ($invoice['attachment_path'] ?? '');
        if ($relativePath === '') {
            http_response_code(404);
            exit('La factura no tiene PDF adjunto.');
        }
        if (!str_starts_with($relativePath, '/storage/projects/')) {
            http_response_code(400);
            exit('Ruta de PDF inválida.');
        }

        $baseStorage = realpath(__DIR__ . '/../../public/storage/projects');
        if ($baseStorage === false) {
            http_response_code(500);
            exit('No se encontró el almacenamiento de archivos.');
        }
        $resolvedPath = realpath(__DIR__ . '/../../public' . $relativePath);
        if ($resolvedPath === false || !str_starts_with($resolvedPath, $baseStorage . DIRECTORY_SEPARATOR)) {
            http_response_code(404);
            exit('Archivo de factura no encontrado.');
        }
        if (!is_file($resolvedPath) || !is_readable($resolvedPath)) {
            http_response_code(404);
            exit('No se puede leer el PDF de la factura.');
        }

        $downloadName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($invoice['invoice_number'] ?? 'factura')) . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . (string) filesize($resolvedPath));
        readfile($resolvedPath);
    }

    public function deleteInvoice(int $projectId, int $invoiceId): void
    {
        if (!$this->auth->hasRole('Administrador')) {
            http_response_code(403);
            exit('Solo administradores pueden eliminar facturas.');
        }
        (new ProjectBillingRepository($this->db))->deleteInvoice($projectId, $invoiceId);
        try {
            (new PmoAutomationService($this->db))->syncBillingPlanAlertsForProject($projectId, new DateTimeImmutable('today'));
        } catch (\Throwable $e) {
            error_log(sprintf('[projects.invoice.delete] No se pudo sincronizar alertas de facturación (%d): %s', $projectId, $e->getMessage()));
        }
        header('Location: /projects/' . $projectId . '/billing');
    }

    public function billingReport(): void
    {
        $this->requirePermission('project.billing.view');
        $projectId = (int) ($_GET['project_id'] ?? 0);
        if ($projectId <= 0) {
            http_response_code(400);
            exit('project_id es obligatorio');
        }

        $rows = (new ProjectBillingRepository($this->db))->exportCsvRows($projectId);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="project-billing-' . $projectId . '.csv"');
        $out = fopen('php://output', 'wb');
        fputcsv($out, [
            'record_type',
            'type_or_invoice',
            'concept_or_date',
            'value',
            'currency',
            'expected_or_issued_date',
            'condition_or_items',
            'status',
            'invoice_number',
            'notes',
        ]);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['record_type'] ?? '',
                $row['type_or_invoice'] ?? '',
                $row['concept_or_date'] ?? '',
                $row['value'] ?? '',
                $row['currency'] ?? '',
                $row['expected_or_issued_date'] ?? '',
                $row['condition_or_items'] ?? '',
                $row['status'] ?? '',
                $row['invoice_number'] ?? '',
                $row['notes'] ?? '',
            ]);
        }
        fclose($out);
    }

    private function canViewBilling(): bool
    {
        if (!$this->isBillingModuleEnabled()) {
            return false;
        }

        if (!$this->auth->can('project.billing.view')) {
            return false;
        }

        return true;
    }

    private function canRegisterBilling(): bool
    {
        if (!$this->isBillingModuleEnabled()) {
            return false;
        }

        return $this->auth->hasRole('Administrador')
            || $this->auth->hasRole('PMO')
            || $this->auth->hasRole('Líder de Proyecto')
            || $this->auth->hasRole('PM');
    }


    private function isBillingModuleEnabled(): bool
    {
        $config = (new ConfigService($this->db))->getConfig();

        return (bool) ($config['operational_rules']['billing']['enabled'] ?? true);
    }

    private function stopperPayloadFromRequest(): array
    {
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $stopperType = strtolower(trim((string) ($_POST['stopper_type'] ?? '')));
        $impactLevel = strtolower(trim((string) ($_POST['impact_level'] ?? '')));
        $affectedArea = strtolower(trim((string) ($_POST['affected_area'] ?? '')));
        $status = strtolower(trim((string) ($_POST['status'] ?? 'abierto')));
        $responsibleId = (int) ($_POST['responsible_id'] ?? 0);
        $detectedAt = trim((string) ($_POST['detected_at'] ?? ''));
        $estimatedResolutionAt = trim((string) ($_POST['estimated_resolution_at'] ?? ''));

        if ($title === '' || $description === '') {
            throw new \InvalidArgumentException('Título y descripción son obligatorios para registrar un bloqueo.');
        }

        if (!in_array($stopperType, self::STOPPER_TYPES, true)) {
            throw new \InvalidArgumentException('Tipo de bloqueo no permitido.');
        }

        if (!in_array($impactLevel, self::STOPPER_IMPACT_LEVELS, true)) {
            throw new \InvalidArgumentException('Nivel de impacto no permitido.');
        }

        if (!in_array($affectedArea, self::STOPPER_AFFECTED_AREAS, true)) {
            throw new \InvalidArgumentException('Área afectada no permitida.');
        }

        if (!in_array($status, self::STOPPER_STATUSES, true)) {
            throw new \InvalidArgumentException('Estado de bloqueo no permitido.');
        }

        if ($responsibleId <= 0) {
            throw new \InvalidArgumentException('Responsable es obligatorio.');
        }

        if ($detectedAt === '' || $estimatedResolutionAt === '') {
            throw new \InvalidArgumentException('La fecha de detección y la fecha estimada de resolución son obligatorias.');
        }

        if (strtotime($estimatedResolutionAt) < strtotime($detectedAt)) {
            throw new \InvalidArgumentException('La fecha estimada de resolución no puede ser anterior a la detección.');
        }

        return [
            'title' => $title,
            'description' => $description,
            'stopper_type' => $stopperType,
            'impact_level' => $impactLevel,
            'affected_area' => $affectedArea,
            'responsible_id' => $responsibleId,
            'detected_at' => $detectedAt,
            'estimated_resolution_at' => $estimatedResolutionAt,
            'status' => $status,
        ];
    }

    private function billingPayloadFromRequest(array $current): array
    {
        $isBillable = isset($_POST['is_billable']) && $_POST['is_billable'] === '1';
        if (!$isBillable) {
            return [
                'is_billable' => 0,
                'billing_type' => (string) ($current['billing_type'] ?? 'mixed'),
                'billing_periodicity' => (string) ($current['billing_periodicity'] ?? 'custom'),
                'contract_value' => (float) ($_POST['contract_value'] ?? ($current['contract_value'] ?? 0)),
                'currency_code' => 'USD',
                'billing_start_date' => $this->nullableDate($_POST['billing_start_date'] ?? ($current['billing_start_date'] ?? null)),
                'billing_end_date' => $this->nullableDate($_POST['billing_end_date'] ?? ($current['billing_end_date'] ?? null)),
                'hourly_rate' => (float) ($current['hourly_rate'] ?? 0),
                'contract_notes' => trim((string) ($_POST['contract_notes'] ?? '')),
            ];
        }

        $currency = strtoupper(trim((string) ($_POST['currency_code'] ?? ($current['currency_code'] ?? 'USD'))));
        if (!in_array($currency, self::CONTRACT_CURRENCIES, true)) {
            $currency = 'USD';
        }

        return [
            'is_billable' => 1,
            'billing_type' => (string) ($current['billing_type'] ?? 'mixed'),
            'billing_periodicity' => (string) ($current['billing_periodicity'] ?? 'custom'),
            'contract_value' => (float) ($_POST['contract_value'] ?? ($current['contract_value'] ?? 0)),
            'currency_code' => $currency,
            'billing_start_date' => $this->nullableDate($_POST['billing_start_date'] ?? ($current['billing_start_date'] ?? null)),
            'billing_end_date' => $this->nullableDate($_POST['billing_end_date'] ?? ($current['billing_end_date'] ?? null)),
            'hourly_rate' => (float) ($current['hourly_rate'] ?? 0),
            'contract_notes' => trim((string) ($_POST['contract_notes'] ?? '')),
        ];
    }

    private function planItemPayloadFromRequest(): array
    {
        $type = strtolower(trim((string) ($_POST['item_type'] ?? '')));
        if (!in_array($type, self::PLAN_ITEM_TYPES, true)) {
            throw new \InvalidArgumentException('Selecciona un tipo de ítem de facturación válido.');
        }

        $concept = trim((string) ($_POST['concept'] ?? ''));
        $amountRaw = trim((string) ($_POST['amount'] ?? ''));
        $amount = $amountRaw !== '' ? (float) $amountRaw : null;
        $percentageRaw = trim((string) ($_POST['percentage'] ?? ''));
        $percentage = $percentageRaw !== '' ? (float) $percentageRaw : null;
        $expectedDate = $this->nullableDate($_POST['expected_date'] ?? null);
        $conditionText = trim((string) ($_POST['condition_text'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $linkedScheduleActivityId = (int) ($_POST['linked_schedule_activity_id'] ?? 0);

        if ($type === 'anticipo') {
            if ($concept === '' || $amount === null || $expectedDate === null) {
                throw new \InvalidArgumentException('El anticipo requiere concepto, valor y fecha esperada.');
            }
        }

        if ($type === 'mensualidad_fija') {
            $startDate = $this->nullableDate($_POST['start_date'] ?? null);
            $endDate = $this->nullableDate($_POST['end_date'] ?? null);
            $dayOfMonth = (int) ($_POST['day_of_month'] ?? 0);
            if ($concept === '' || $amount === null || $startDate === null || $endDate === null || $dayOfMonth < 1 || $dayOfMonth > 28) {
                throw new \InvalidArgumentException('La mensualidad fija requiere concepto, valor mensual, fechas y día del mes (1-28).');
            }
            return [
                'item_type' => $type,
                'concept' => $concept,
                'amount' => $amount,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'day_of_month' => $dayOfMonth,
                'condition_text' => $conditionText,
                'notes' => $notes,
            ];
        }

        if ($type === 'hito_entregable') {
            $milestoneName = trim((string) ($_POST['milestone_name'] ?? ''));
            if ($milestoneName === '' || ($amount === null && $percentage === null) || $expectedDate === null) {
                throw new \InvalidArgumentException('El hito requiere nombre, valor (monto o porcentaje) y fecha esperada.');
            }
            return [
                'item_type' => $type,
                'concept' => $concept !== '' ? $concept : $milestoneName,
                'milestone_name' => $milestoneName,
                'amount' => $amount,
                'percentage' => $percentage,
                'expected_date' => $expectedDate,
                'condition_text' => $conditionText,
                'notes' => $notes,
                'linked_schedule_activity_id' => $linkedScheduleActivityId > 0 ? $linkedScheduleActivityId : null,
            ];
        }

        $progressRequiredRaw = trim((string) ($_POST['progress_required_percentage'] ?? ''));
        $progressRequired = $progressRequiredRaw !== '' ? (float) $progressRequiredRaw : null;
        if ($concept === '' || $progressRequired === null || $progressRequired < 1 || $progressRequired > 100 || $amount === null) {
            throw new \InvalidArgumentException('El ítem por porcentaje de avance requiere concepto, porcentaje (1-100) y valor.');
        }

        return [
            'item_type' => $type,
            'concept' => $concept,
            'progress_required_percentage' => $progressRequired,
            'amount' => $amount,
            'condition_text' => $conditionText,
            'notes' => $notes,
            'condition_met' => isset($_POST['condition_met']) ? 1 : 0,
            'expected_date' => $expectedDate,
        ];
    }

    private function invoicePayloadFromRequest(int $projectId, ?array $currentInvoice = null): array
    {
        $invoiceNumber = trim((string) ($_POST['invoice_number'] ?? ''));
        $issuedAt = $this->nullableDate($_POST['issued_at'] ?? null);
        $amount = (float) ($_POST['amount'] ?? 0);
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $currency = strtoupper(trim((string) ($_POST['currency_code'] ?? '')));
        if (!in_array($currency, self::CONTRACT_CURRENCIES, true)) {
            $currency = 'USD';
        }

        if ($invoiceNumber === '' || $issuedAt === null || $amount <= 0) {
            throw new \InvalidArgumentException('Número, fecha de emisión y valor de factura son obligatorios.');
        }

        $planItemIds = is_array($_POST['plan_item_ids'] ?? null)
            ? array_values(array_unique(array_map('intval', $_POST['plan_item_ids'])))
            : [];
        $planItemIds = array_values(array_filter($planItemIds, static fn (int $id): bool => $id > 0));

        $uploadedAttachmentPath = $this->invoicePdfPathFromUpload($projectId);
        $removePdf = isset($_POST['remove_invoice_pdf']) && (string) ($_POST['remove_invoice_pdf']) === '1';
        $existingAttachmentPath = trim((string) ($currentInvoice['attachment_path'] ?? ($_POST['existing_attachment_path'] ?? '')));

        if ($uploadedAttachmentPath !== null) {
            $attachmentPath = $uploadedAttachmentPath;
        } elseif ($removePdf) {
            $attachmentPath = null;
        } else {
            $attachmentPath = $existingAttachmentPath !== '' ? $existingAttachmentPath : null;
        }

        return [
            'invoice_number' => $invoiceNumber,
            'issued_at' => $issuedAt,
            'period_start' => null,
            'period_end' => null,
            'amount' => $amount,
            'status' => 'issued',
            'notes' => $notes,
            'attachment_path' => $attachmentPath,
            'currency_code' => $currency,
            'plan_item_ids' => $planItemIds,
        ];
    }

    private function cleanupOldInvoicePdf(int $projectId, string $previousPath, string $nextPath): void
    {
        if ($previousPath === '' || $previousPath === $nextPath) {
            return;
        }

        $allowedPrefix = '/storage/projects/' . $projectId . '/billing/invoices/';
        if (!str_starts_with($previousPath, $allowedPrefix)) {
            return;
        }

        $baseDir = realpath(__DIR__ . '/../../public/storage/projects/' . $projectId . '/billing/invoices');
        $fullPath = realpath(__DIR__ . '/../../public' . $previousPath);
        if ($baseDir === false || $fullPath === false) {
            return;
        }
        if (!str_starts_with($fullPath, $baseDir . DIRECTORY_SEPARATOR)) {
            return;
        }
        if (!is_file($fullPath) || !is_writable($fullPath)) {
            return;
        }

        @unlink($fullPath);
    }

    private function invoicePdfPathFromUpload(int $projectId): ?string
    {
        $file = $_FILES['invoice_pdf'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('No fue posible cargar el PDF de la factura.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath)) {
            throw new \InvalidArgumentException('Archivo PDF inválido.');
        }
        $mime = (string) mime_content_type($tmpPath);
        if ($mime !== 'application/pdf') {
            throw new \InvalidArgumentException('Solo se permite archivo PDF para la factura.');
        }

        $targetDir = __DIR__ . '/../../public/storage/projects/' . $projectId . '/billing/invoices';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('No se pudo preparar el directorio para facturas PDF.');
        }

        $baseName = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo((string) ($file['name'] ?? 'factura.pdf'), PATHINFO_FILENAME));
        $baseName = $baseName !== '' ? $baseName : 'factura';
        $fileName = $baseName . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.pdf';
        $destination = rtrim($targetDir, '/') . '/' . $fileName;
        if (!move_uploaded_file($tmpPath, $destination)) {
            throw new \RuntimeException('No se pudo guardar el PDF de la factura.');
        }

        return '/storage/projects/' . $projectId . '/billing/invoices/' . $fileName;
    }

    private function validateInvoiceAgainstContract(array $payload, array $billingConfig, array $invoiceTotals): void
    {
        $nextAmount = (float) ($payload['amount'] ?? 0);
        $contractValue = (float) ($billingConfig['contract_value'] ?? 0);
        if ($contractValue <= 0) {
            return;
        }

        $alreadyInvoiced = (float) ($invoiceTotals['total_invoiced'] ?? 0);
        if (($alreadyInvoiced + $nextAmount) > $contractValue) {
            http_response_code(422);
            exit('La factura supera el valor total del contrato configurado.');
        }
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

    private function collectDocumentUploadMeta(?string $requiredDocumentKey = null): array
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

        if (!$startFlow) {
            $reviewerId = null;
            $validatorId = null;
            $approverId = null;
        }

        $contractEndDate = null;
        if ($requiredDocumentKey === 'contrato') {
            $contractEndDate = trim((string) ($_POST['contract_end_date'] ?? ''));
            if ($contractEndDate === '') {
                throw new \InvalidArgumentException('La fecha de finalización del contrato es obligatoria.');
            }
            $normalizedContractEndDate = \DateTimeImmutable::createFromFormat('Y-m-d', $contractEndDate);
            if (!$normalizedContractEndDate || $normalizedContractEndDate->format('Y-m-d') !== $contractEndDate) {
                throw new \InvalidArgumentException('Ingresa una fecha de finalización del contrato válida.');
            }
        }

        return [
            'document_type' => $documentType,
            'document_tags' => $documentTags,
            'document_version' => $documentVersion,
            'description' => $description,
            'document_status' => $startFlow ? 'en_revision' : 'final',
            'reviewer_id' => $reviewerId,
            'validator_id' => $validatorId,
            'approver_id' => $approverId,
            'contract_end_date' => $contractEndDate,
        ];
    }

    private function upsertRequiredDocumentsMeta(int $projectId, int $rootNodeId, array $payload, int $userId = 0): void
    {
        if ($projectId <= 0 || $rootNodeId <= 0) {
            return;
        }

        $nodesRepo = new ProjectNodesRepository($this->db);
        $metaNode = $nodesRepo->findNodeByCode($projectId, self::REQUIRED_DOCUMENTS_META_CODE);
        $existingMeta = [];
        if ($metaNode) {
            $decoded = json_decode((string) ($metaNode['description'] ?? ''), true);
            if (is_array($decoded)) {
                $existingMeta = $decoded;
            }
        }

        $merged = array_merge($existingMeta, $payload);
        if ($metaNode) {
            $this->db->execute(
                'UPDATE project_nodes SET description = :description WHERE id = :id AND project_id = :project_id',
                [
                    ':description' => json_encode($merged, JSON_UNESCAPED_UNICODE),
                    ':id' => (int) ($metaNode['id'] ?? 0),
                    ':project_id' => $projectId,
                ]
            );
            return;
        }

        $nodesRepo->createNode([
            'project_id' => $projectId,
            'parent_id' => $rootNodeId,
            'code' => self::REQUIRED_DOCUMENTS_META_CODE,
            'node_type' => 'metadata',
            'title' => 'Documentos obligatorios del proyecto',
            'description' => json_encode($merged, JSON_UNESCAPED_UNICODE),
            'sort_order' => 999,
            'created_by' => $userId > 0 ? $userId : null,
        ]);
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
            default => 'final',
        };
    }

    private function actionFromStatusTransition(string $currentStatus, string $nextStatus): string
    {
        $transitions = [
            'borrador' => [
                'en_revision' => 'send_review',
            ],
            'final' => [
                'en_revision' => 'send_review',
            ],
            'publicado' => [
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
            if (!in_array($currentStatus, ['borrador', 'final', 'publicado'], true)) {
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
        $currentStatus = $nodeFlow['document_status'] ?? 'final';

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

        $updated = $repo->updateDocumentStatus($projectId, $nodeId, $payload);
        $eventType = $this->notificationEventForDocumentAction($action);
        if ($eventType !== '') {
            try {
                (new NotificationService($this->db))->notify(
                    $eventType,
                    [
                        'project_id' => $projectId,
                        'node_id' => $nodeId,
                        'document_status' => $updated['document_status'] ?? $payload['document_status'],
                        'reviewer_id' => $nodeFlow['reviewer_id'] ?? null,
                        'validator_id' => $nodeFlow['validator_id'] ?? null,
                        'approver_id' => $nodeFlow['approver_id'] ?? null,
                        'target_user_id' => $nodeFlow['approver_id'] ?? null,
                    ],
                    $currentUserId
                );
            } catch (\Throwable $e) {
                error_log('Error al notificar flujo documental: ' . $e->getMessage());
            }
        }

        (new ProjectService($this->db))->recordHealthSnapshot($projectId);

        return $updated;
    }

    private function notificationEventForDocumentAction(string $action): string
    {
        return match ($action) {
            'send_approval' => 'document.sent_approval',
            'approved' => 'document.approved',
            'rejected' => 'document.rejected',
            default => '',
        };
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

    private function projectPayload(array $current, array $deliveryConfig = [], array $catalogs = [], ?UsersRepository $usersRepo = null): array
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

        $statusesCatalog = $catalogs['statuses'] ?? [];
        $prioritiesCatalog = $catalogs['priorities'] ?? [];
        $status = $this->validatedCatalogValue(
            (string) ($_POST['status'] ?? ($current['status'] ?? '')),
            $statusesCatalog,
            'estado',
            (string) ($current['status'] ?? ($statusesCatalog[0]['code'] ?? 'planning'))
        );
        $priority = $this->validatedCatalogValue(
            (string) ($_POST['priority'] ?? ($current['priority'] ?? '')),
            $prioritiesCatalog,
            'prioridad',
            (string) ($current['priority'] ?? ($prioritiesCatalog[0]['code'] ?? 'medium'))
        );
        $pmId = (int) ($_POST['pm_id'] ?? ($current['pm_id'] ?? 0));
        if ($pmId <= 0 || $usersRepo === null || !$usersRepo->isValidProjectManager($pmId)) {
            throw new \InvalidArgumentException('Selecciona un PM válido para el proyecto.');
        }

        return [
            'name' => trim($_POST['name'] ?? (string) ($current['name'] ?? '')),
            'status' => $status,
            'health' => (string) ($current['health'] ?? ''),
            'priority' => $priority,
            'client_id' => (int) ($_POST['client_id'] ?? ($current['client_id'] ?? 0)),
            'pm_id' => $pmId,
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
            'project_stage' => $this->validatedProjectStage((string) ($_POST['project_stage'] ?? ($current['project_stage'] ?? 'Discovery'))),
            'scope' => $scope,
            'risks' => $riskAssessment['selected'],
            'risk_evaluations' => $riskAssessment['evaluations'],
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'risk_catalog' => $delivery['risks'],
            'is_billable' => isset($_POST['is_billable']) ? (int) ($_POST['is_billable'] === '1') : (int) ($current['is_billable'] ?? 0),
            'billing_type' => (string) ($_POST['billing_type'] ?? ($current['billing_type'] ?? 'fixed')),
            'billing_periodicity' => (string) ($_POST['billing_periodicity'] ?? ($current['billing_periodicity'] ?? 'monthly')),
            'contract_value' => (float) ($_POST['contract_value'] ?? ($current['contract_value'] ?? 0)),
            'currency_code' => strtoupper((string) ($_POST['currency_code'] ?? ($current['currency_code'] ?? 'USD'))),
            'billing_start_date' => $this->nullableDate($_POST['billing_start_date'] ?? ($current['billing_start_date'] ?? null)),
            'billing_end_date' => $this->nullableDate($_POST['billing_end_date'] ?? ($current['billing_end_date'] ?? null)),
            'hourly_rate' => (float) ($_POST['hourly_rate'] ?? ($current['hourly_rate'] ?? 0)),
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

            $openStoppers = (new ProjectStoppersRepository($this->db))->openCount((int) $current['id']);
            if ($openStoppers > 0) {
                throw new \InvalidArgumentException('No puedes mover el proyecto a cierre con bloqueos abiertos (' . $openStoppers . ').');
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
            if ($currentIndex !== false && $requestedIndex !== false && $requestedIndex > ($currentIndex + 1)) {
                throw new \InvalidArgumentException('No puedes saltar fases. Debes avanzar una fase a la vez.');
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
        $fields = ['scope', 'budget', 'start_date', 'end_date', 'methodology', 'phase', 'project_stage'];
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
        $projectManagers = $this->projectManagersForSelection($usersRepo);
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
                'project_stage' => 'Discovery',
                'priority' => $catalogs['priorities'][0]['code'] ?? 'medium',
                'methodology' => $defaultMethodology,
                'phase' => $defaultPhase,
                'pm_id' => $this->defaultPmId($projectManagers, $usersRepo),
                'project_type' => 'convencional',
                'scope' => '',
                'design_inputs' => '',
                'client_participation' => 'media',
            ],
            'stageOptions' => self::STAGE_GATES,
            'canCreate' => !empty($clients) && !empty($projectManagers),
        ];
    }

    private function projectManagersForSelection(UsersRepository $usersRepo): array
    {
        return array_values(array_filter(
            $usersRepo->all(),
            fn ($candidate) => ($candidate['active'] ?? 0) == 1 && in_array($candidate['role_name'] ?? '', ['Administrador', 'PMO', 'Líder de Proyecto'], true)
        ));
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

        $projectType = $this->validatedProjectType((string) ($_POST['project_type'] ?? 'convencional'));
        $status = 'planning';
        $priority = $this->validatedCatalogValue((string) ($_POST['priority'] ?? ''), $catalogs['priorities'], 'prioridad', $catalogs['priorities'][0]['code'] ?? 'medium');

        $methodology = $this->methodologyForType($projectType, $delivery['methodologies'] ?? []);

        $phase = $this->initialPhaseForMethodology($methodology, $delivery['phases'] ?? []);

        $filteredRisks = $this->riskCatalogForType($delivery['risks'] ?? [], $projectType, $methodology);
        $riskAssessment = $this->assessRisks($_POST['risks'] ?? [], $filteredRisks);
        if (count($riskAssessment['selected']) < self::MIN_REQUIRED_RISKS_ON_CREATE) {
            throw new \InvalidArgumentException('Selecciona al menos ' . self::MIN_REQUIRED_RISKS_ON_CREATE . ' riesgos para crear el proyecto.');
        }

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

        $budget = $this->validatedNonNegativeFloat($_POST['budget'] ?? null, 'El presupuesto');
        $actualCost = $this->validatedNonNegativeFloat($_POST['actual_cost'] ?? null, 'El costo real');
        $plannedHours = $this->validatedNonNegativeFloat($_POST['planned_hours'] ?? null, 'Las horas planificadas');
        $actualHours = $this->validatedNonNegativeFloat($_POST['actual_hours'] ?? null, 'Las horas reales');
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

        $startDate = $this->nullableDate($_POST['start_date'] ?? null);
        if ($startDate === null) {
            throw new \InvalidArgumentException('La fecha de inicio del proyecto es obligatoria.');
        }
        $this->validateDateRange($startDate, $endDate);

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
            'project_stage' => $this->validatedProjectStage((string) ($_POST['project_stage'] ?? 'Discovery')),
            'scope' => $scope,
            'design_inputs' => $designInputs,
            'client_participation' => $this->clientParticipationToDbValue($clientParticipation),
            'budget' => $budget,
            'actual_cost' => $actualCost,
            'planned_hours' => $plannedHours,
            'actual_hours' => $actualHours,
            'progress' => $progress,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'risks' => $riskAssessment['selected'],
            'risk_evaluations' => $riskAssessment['evaluations'],
            'risk_catalog' => $filteredRisks,
            'is_billable' => isset($_POST['is_billable']) ? (int) ($_POST['is_billable'] === '1') : 0,
            'billing_type' => (string) ($_POST['billing_type'] ?? 'fixed'),
            'billing_periodicity' => (string) ($_POST['billing_periodicity'] ?? 'monthly'),
            'contract_value' => (float) ($_POST['contract_value'] ?? 0),
            'currency_code' => strtoupper((string) ($_POST['currency_code'] ?? 'USD')),
            'billing_start_date' => $this->nullableDate($_POST['billing_start_date'] ?? null),
            'billing_end_date' => $this->nullableDate($_POST['billing_end_date'] ?? null),
            'hourly_rate' => (float) ($_POST['hourly_rate'] ?? 0),
        ];

        $payload = $this->applyMethodologyRules($payload, $delivery['phases'] ?? []);
        $payload['risk_score'] = $riskScore;
        $payload['risk_level'] = $riskLevel;
        $payload['health'] = $health;

        return $payload;
    }

    private function validatedProjectStage(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed !== '' && in_array($trimmed, self::STAGE_GATES, true)) {
            return $trimmed;
        }

        return 'Discovery';
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
        if (array_key_exists($normalized, self::CLIENT_PARTICIPATION_LEVELS)) {
            return $normalized;
        }

        return 'media';
    }

    private function clientParticipationToDbValue(string $value): int
    {
        return self::CLIENT_PARTICIPATION_LEVELS[$value] ?? self::CLIENT_PARTICIPATION_LEVELS['media'];
    }

    private function validatedProjectType(string $value): string
    {
        $normalized = strtolower(trim($value));
        return in_array($normalized, self::ALLOWED_PROJECT_TYPES, true) ? $normalized : 'convencional';
    }

    private function validatedNonNegativeFloat(mixed $value, string $label): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException($label . ' debe ser un número válido.');
        }

        $number = (float) $value;
        if ($number < 0) {
            throw new \InvalidArgumentException($label . ' no puede ser negativo.');
        }

        return $number;
    }

    private function validateDateRange(?string $startDate, ?string $endDate): void
    {
        if ($startDate === null || $endDate === null) {
            return;
        }

        if ($endDate < $startDate) {
            throw new \InvalidArgumentException('La fecha de fin no puede ser menor a la fecha de inicio.');
        }
    }


    private function sanitizePayloadForDebug(array $payload): array
    {
        $copy = $payload;
        if (isset($copy['risk_catalog']) && is_array($copy['risk_catalog'])) {
            $copy['risk_catalog'] = 'omitted(' . count($copy['risk_catalog']) . ' items)';
        }

        return $copy;
    }

    private function buildInsertDebugMessage(\PDOException $e, array $payload): string
    {
        $base = $this->formatExceptionForDisplay($e);
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($payloadJson === false) {
            $payloadJson = '{"error":"No se pudo serializar payload"}';
        }

        return $base
            . "

Payload enviado al insert:
"
            . $payloadJson
            . "

POST crudo:
"
            . json_encode($_POST, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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

    public function requirements(int $id): void
    {
        try {
            $data = $this->projectDetailData($id);
            $repo = new RequirementsRepository($this->db);
            $config = (new ConfigService($this->db))->getConfig();

            $start = (string) ($_GET['start_date'] ?? date('Y-m-01'));
            $end = (string) ($_GET['end_date'] ?? date('Y-m-t'));

            $indicator = $repo->indicatorForProject($id, $start, $end);
            $history = $repo->auditByProject($id);

            $data['requirements'] = $repo->listByProject($id);
            $data['requirementsIndicator'] = $indicator;
            $data['requirementsAudit'] = $history;
            $data['requirementsPeriod'] = ['start_date' => $start, 'end_date' => $end];
            $data['requirementsStatuses'] = RequirementsRepository::allowedStatuses();
            $data['requirementsTargetMeta'] = (int) (
                $config['operational_rules']['health_scoring']['meta_cumplimiento_requisitos']
                ?? $config['operational_rules']['health_scoring']['requirements_indicator']['target']
                ?? 95
            );

            $this->render('projects/requirements', $data);
        } catch (\Throwable $e) {
            error_log(sprintf('[projects.requirements] Error en proyecto %d: %s', $id, $e->getMessage()));
            header('Location: /projects/' . $id . '?requirements_error=1');
            exit;
        }
    }

    public function storeRequirement(int $projectId): void
    {
        $user = $this->auth->user() ?? [];
        $project = (new ProjectsRepository($this->db))->find($projectId);
        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado');
        }

        $repo = new RequirementsRepository($this->db);
        $repo->create([
            'project_id' => $projectId,
            'client_id' => (int) ($project['client_id'] ?? 0),
            'created_by' => (int) ($user['id'] ?? 0),
            'name' => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
            'version' => $_POST['version'] ?? '1.0',
            'delivery_date' => $_POST['delivery_date'] ?? null,
            'status' => 'borrador',
            'approved_first_delivery' => ($_POST['approved_first_delivery'] ?? '0') === '1',
        ]);

        header('Location: /projects/' . $projectId . '/requirements?saved=1');
        exit;
    }

    public function updateRequirementStatus(int $projectId, int $requirementId): void
    {
        $user = $this->auth->user() ?? [];
        $status = strtolower(trim((string) ($_POST['status'] ?? 'borrador')));
        $allowed = RequirementsRepository::allowedStatuses();
        if (!in_array($status, $allowed, true)) {
            $status = 'borrador';
        }

        try {
            (new RequirementsRepository($this->db))->updateStatus($requirementId, $status, (int) ($user['id'] ?? 0));
            header('Location: /projects/' . $projectId . '/requirements?updated=1');
        } catch (\RuntimeException $e) {
            header('Location: /projects/' . $projectId . '/requirements?error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    public function deleteRequirement(int $projectId, int $requirementId): void
    {
        $user = $this->auth->user() ?? [];
        if (((int) ($user['can_delete_requirement_history'] ?? 0)) !== 1) {
            $this->denyAccess('Solo administrador puede borrar requisitos históricos.');
        }

        (new RequirementsRepository($this->db))->delete($requirementId);
        header('Location: /projects/' . $projectId . '/requirements?deleted=1');
        exit;
    }

    private function normalizeScheduleActivities(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? $row['nombre'] ?? ''));
            if ($name === '') {
                continue;
            }
            $itemType = strtolower(trim((string) ($row['item_type'] ?? $row['type'] ?? $row['tipo'] ?? 'activity')));
            $itemType = in_array($itemType, ['milestone', 'hito'], true) ? 'milestone' : 'activity';
            $startDate = $this->normalizeDateValue((string) ($row['start_date'] ?? $row['fecha_inicio'] ?? ''));
            $endDate = $this->normalizeDateValue((string) ($row['end_date'] ?? $row['fecha_fin'] ?? ''));
            $duration = (int) ($row['duration_days'] ?? $row['duracion_dias'] ?? 0);
            if ($duration <= 0 && $startDate && $endDate) {
                $duration = max(0, (int) floor(((strtotime($endDate) ?: 0) - (strtotime($startDate) ?: 0)) / 86400) + 1);
            }
            $normalized[] = [
                'sort_order' => (int) ($row['sort_order'] ?? $row['order'] ?? ($index + 1)),
                'name' => $name,
                'item_type' => $itemType,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'duration_days' => $itemType === 'milestone' ? 0 : max(0, $duration),
                'responsible_name' => trim((string) ($row['responsible_name'] ?? $row['responsable'] ?? '')),
                'progress_percent' => max(0, min(100, (float) ($row['progress_percent'] ?? $row['porcentaje_avance'] ?? 0))),
                'linked_task_id' => (int) ($row['linked_task_id'] ?? $row['tarea_vinculada'] ?? 0),
            ];
        }

        return $normalized;
    }

    private function validateImportedScheduleRows(array $rows, int $projectId): array
    {
        $errors = [];
        $activities = [];
        foreach ($rows as $idx => $row) {
            if (!is_array($row)) {
                $errors[] = ['row' => $idx + 1, 'message' => 'Fila inválida.'];
                continue;
            }
            $activity = $this->normalizeScheduleActivities([$row])[0] ?? null;
            if (!$activity) {
                $errors[] = ['row' => $idx + 1, 'message' => 'Nombre de actividad obligatorio.'];
                continue;
            }
            if (!$activity['start_date'] || !$activity['end_date']) {
                $errors[] = ['row' => $idx + 1, 'message' => 'Fecha inicio y fin son obligatorias.'];
            }
            $responsible = mb_strtolower(trim((string) ($activity['responsible_name'] ?? '')));
            if ($responsible !== '') {
                $existsTalent = (bool) $this->db->fetchOne(
                    'SELECT 1 FROM project_talent_assignments a JOIN talents t ON t.id = a.talent_id WHERE a.project_id = :project AND LOWER(t.name) = :name LIMIT 1',
                    [':project' => $projectId, ':name' => $responsible]
                );
                if (!$existsTalent) {
                    $errors[] = ['row' => $idx + 1, 'message' => 'Responsable no existe en Talento para este proyecto.'];
                }
            }
            $activities[] = $activity;
        }

        return ['rows' => $rows, 'activities' => $activities, 'errors' => $errors];
    }

    private function readSpreadsheetRows(string $tmpPath, string $originalName): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === 'csv') {
            $rows = [];
            $handle = fopen($tmpPath, 'rb');
            if (!$handle) {
                return [];
            }
            $headers = null;
            while (($data = fgetcsv($handle, 0, ',')) !== false) {
                if ($headers === null) {
                    $headers = $data;
                    continue;
                }
                $rows[] = array_combine($headers, $data) ?: [];
            }
            fclose($handle);
            return $rows;
        }

        if ($extension !== 'xlsx') {
            throw new \InvalidArgumentException('Formato inválido. Usa .xlsx o .csv.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmpPath) !== true) {
            throw new \InvalidArgumentException('No se pudo leer el archivo XLSX.');
        }
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml') ?: '';
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml') ?: '';
        $zip->close();

        $sharedStrings = [];
        if ($sharedXml !== '') {
            $shared = @simplexml_load_string($sharedXml);
            if ($shared) {
                foreach ($shared->si as $si) {
                    $sharedStrings[] = trim((string) ($si->t ?? ''));
                }
            }
        }
        $sheet = @simplexml_load_string($sheetXml);
        if (!$sheet || !isset($sheet->sheetData)) {
            return [];
        }
        $table = [];
        foreach ($sheet->sheetData->row as $row) {
            $line = [];
            foreach ($row->c as $c) {
                $v = (string) ($c->v ?? '');
                $type = (string) ($c['t'] ?? '');
                $line[] = ($type === 's') ? ($sharedStrings[(int) $v] ?? '') : $v;
            }
            $table[] = $line;
        }
        $headers = array_map('trim', $table[0] ?? []);
        $rows = [];
        foreach (array_slice($table, 1) as $line) {
            $rows[] = array_combine($headers, $line) ?: [];
        }
        return $rows;
    }

    private function normalizeDateValue(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value) === 1) {
            return $value;
        }
        $timestamp = strtotime(str_replace('/', '-', $value));
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

}
