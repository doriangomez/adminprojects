<?php

declare(strict_types=1);

use App\Repositories\TalentsRepository;
use App\Repositories\TasksRepository;
use App\Repositories\ProjectsRepository;

class TasksController extends Controller
{
    private const ALLOWED_STATUSES = ['todo', 'pending', 'in_progress', 'review', 'blocked', 'done', 'completed'];
    private const ALLOWED_PRIORITIES = ['low', 'medium', 'high'];
    private const KANBAN_STATUS_ORDER = ['todo', 'in_progress', 'review', 'blocked', 'done'];
    private const KANBAN_STATUS_META = [
        'todo' => ['label' => 'Pendiente', 'icon' => '⏳', 'class' => 'status-muted', 'accent' => 'var(--text-secondary)'],
        'in_progress' => ['label' => 'En progreso', 'icon' => '🔄', 'class' => 'status-info', 'accent' => 'var(--info)'],
        'review' => ['label' => 'En revisión', 'icon' => '📝', 'class' => 'status-warning', 'accent' => 'var(--warning)'],
        'blocked' => ['label' => 'Bloqueada', 'icon' => '⛔', 'class' => 'status-danger', 'accent' => 'var(--danger)'],
        'done' => ['label' => 'Completada', 'icon' => '✅', 'class' => 'status-success', 'accent' => 'var(--success)'],
    ];

    public function index(): void
    {
        $repo = new TasksRepository($this->db);
        $this->requirePermission('tasks.view');
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $canManage = $this->auth->can('projects.manage');
        $isTalent = $this->isTalent($user);
        $tasks = $repo->listAll($user);

        $projectOptions = [];
        if ($canManage) {
            $projectOptions = (new ProjectsRepository($this->db))->summary($user);
        } elseif ($isTalent) {
            $projectOptions = $repo->assignedProjectsForUser($userId);
        }

        $this->render('tasks/index', [
            'title' => $isTalent ? 'Panel de trabajo del talento' : 'Tareas',
            'tasks' => $tasks,
            'projectOptions' => $projectOptions,
            'talents' => $canManage ? (new TalentsRepository($this->db))->assignmentOptions() : [],
            'kanbanColumns' => $this->buildKanbanColumns($tasks),
            'kanbanByProject' => $canManage ? $this->buildKanbanByProject($tasks) : [],
            'teamLoad' => $canManage ? $this->buildTeamLoad($tasks) : [],
            'canManage' => $canManage,
            'canCreateTasks' => $canManage || $isTalent,
            'canDeleteTasks' => $this->auth->hasRole('Administrador'),
            'isTalentUser' => $isTalent,
        ]);
    }

    public function kanban(): void
    {
        $this->requirePermission('tasks.view');
        $repo = new TasksRepository($this->db);
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $canManage = $this->auth->can('projects.manage');
        $isTalent = $this->isTalent($user);
        $tasks = $repo->listAll($user);

        $projectOptions = [];
        if ($canManage) {
            $projectOptions = (new ProjectsRepository($this->db))->summary($user);
        } elseif ($isTalent) {
            $projectOptions = $repo->assignedProjectsForUser($userId);
        }

        $selectedProjectId = (int) ($_GET['project_id'] ?? 0);
        $selectedAssigneeId = (int) ($_GET['assignee_id'] ?? 0);
        $selectedPriority = strtolower(trim((string) ($_GET['priority'] ?? '')));
        if (!in_array($selectedPriority, self::ALLOWED_PRIORITIES, true)) {
            $selectedPriority = '';
        }

        $filteredTasks = array_values(array_filter($tasks, function (array $task) use ($selectedProjectId, $selectedAssigneeId, $selectedPriority): bool {
            if ($selectedProjectId > 0 && (int) ($task['project_id'] ?? 0) !== $selectedProjectId) {
                return false;
            }
            if ($selectedAssigneeId > 0 && (int) ($task['assignee_id'] ?? 0) !== $selectedAssigneeId) {
                return false;
            }
            if ($selectedPriority !== '' && strtolower((string) ($task['priority'] ?? '')) !== $selectedPriority) {
                return false;
            }

            return true;
        }));

        $assigneeOptions = [];
        foreach ($tasks as $task) {
            $assigneeId = (int) ($task['assignee_id'] ?? 0);
            if ($assigneeId <= 0 || isset($assigneeOptions[$assigneeId])) {
                continue;
            }
            $assigneeOptions[$assigneeId] = [
                'id' => $assigneeId,
                'name' => trim((string) ($task['assignee'] ?? '')) !== '' ? (string) $task['assignee'] : 'Sin asignar',
            ];
        }
        ksort($assigneeOptions);

        $this->render('tasks/kanban', [
            'title' => 'Kanban',
            'tasks' => $tasks,
            'kanbanColumns' => $this->buildKanbanColumns($filteredTasks),
            'projectOptions' => $projectOptions,
            'assigneeOptions' => array_values($assigneeOptions),
            'selectedProjectId' => $selectedProjectId,
            'selectedAssigneeId' => $selectedAssigneeId,
            'selectedPriority' => $selectedPriority,
            'kanbanStatusOrder' => self::KANBAN_STATUS_ORDER,
            'kanbanStatusMeta' => self::KANBAN_STATUS_META,
            'canManage' => $canManage,
            'canCreateTasks' => $canManage || $isTalent,
            'isTalentUser' => $isTalent,
            'talents' => $canManage ? (new TalentsRepository($this->db))->assignmentOptions() : [],
        ]);
    }

    public function store(): void
    {
        $repo = new TasksRepository($this->db);
        $user = $this->auth->user() ?? [];
        $canManage = $this->auth->can('projects.manage');
        $isTalent = $this->isTalent($user);
        if (!$canManage && !$isTalent) {
            $this->denyAccess();
        }

        $projectId = (int) ($_POST['project_id'] ?? 0);
        if ($projectId <= 0) {
            http_response_code(400);
            exit('Selecciona un proyecto válido.');
        }

        $projectsRepo = new ProjectsRepository($this->db);
        if ($canManage) {
            $project = $projectsRepo->findForUser($projectId, $user);
        } else {
            if (!$repo->userCanCreateTaskInProject($user, $projectId)) {
                http_response_code(403);
                exit('Solo puedes crear tareas en proyectos donde estés asignado.');
            }
            $project = $projectsRepo->find($projectId);
        }

        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado.');
        }

        if ($this->isProjectClosed($project)) {
            http_response_code(400);
            exit('No puedes agregar tareas a un proyecto cerrado.');
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $priority = strtolower(trim((string) ($_POST['priority'] ?? 'medium')));
        $estimatedHours = (float) ($_POST['estimated_hours'] ?? 0);
        $dueDate = trim((string) ($_POST['due_date'] ?? ''));
        $assigneeId = (int) ($_POST['assignee_id'] ?? 0);
        $status = $this->normalizeStatus((string) ($_POST['status'] ?? 'todo'));

        if ($title === '') {
            http_response_code(400);
            exit('El título de la tarea es obligatorio.');
        }

        if (!in_array($priority, self::ALLOWED_PRIORITIES, true)) {
            http_response_code(400);
            exit('Prioridad de tarea inválida.');
        }
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            http_response_code(400);
            exit('Estado de tarea inválido.');
        }

        if ($isTalent && !$canManage) {
            $talentId = $repo->talentIdForUser((int) ($user['id'] ?? 0));
            if ($talentId === null) {
                http_response_code(400);
                exit('Tu usuario no tiene un talento asociado para asignar la tarea.');
            }
            $assigneeId = $talentId;
        }

        $repo->createForProject($projectId, [
            'title' => $title,
            'status' => $status,
            'priority' => $priority,
            'estimated_hours' => $estimatedHours,
            'due_date' => $dueDate !== '' ? $dueDate : null,
            'assignee_id' => $assigneeId > 0 ? $assigneeId : null,
        ]);

        if ($this->wantsJson()) {
            $this->json(['status' => 'ok']);
            return;
        }

        $redirectTo = trim((string) ($_POST['redirect_to'] ?? ''));
        header('Location: ' . ($redirectTo !== '' ? $redirectTo : '/tasks'));
    }

    public function edit(int $taskId): void
    {
        $this->requirePermission('projects.manage');
        $user = $this->auth->user() ?? [];
        $repo = new TasksRepository($this->db);
        $task = $repo->find($taskId, $user);

        if (!$task) {
            http_response_code(404);
            exit('Tarea no encontrada.');
        }

        $talents = (new TalentsRepository($this->db))->summary();

        $this->render('tasks/edit', [
            'title' => 'Editar tarea',
            'task' => $task,
            'talents' => $talents,
            'scheduleActivities' => $repo->scheduleActivitiesForProject((int) ($task['project_id'] ?? 0)),
        ]);
    }

    public function update(int $taskId): void
    {
        $this->requirePermission('projects.manage');
        $repo = new TasksRepository($this->db);
        $user = $this->auth->user() ?? [];

        $task = $repo->find($taskId, $user);
        if (!$task) {
            http_response_code(404);
            exit('Tarea no encontrada.');
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $status = (string) ($_POST['status'] ?? '');
        $priority = (string) ($_POST['priority'] ?? '');
        $estimatedHours = (float) ($_POST['estimated_hours'] ?? 0);
        $dueDate = trim((string) ($_POST['due_date'] ?? ''));
        $assigneeId = (int) ($_POST['assignee_id'] ?? 0);
        $scheduleActivityId = (int) ($_POST['schedule_activity_id'] ?? 0);

        if ($title === '' || !in_array($status, self::ALLOWED_STATUSES, true) || !in_array($priority, self::ALLOWED_PRIORITIES, true)) {
            http_response_code(400);
            exit('Completa los campos requeridos para editar la tarea.');
        }
        if ($scheduleActivityId > 0 && !$repo->scheduleActivityBelongsToProject((int) ($task['project_id'] ?? 0), $scheduleActivityId)) {
            http_response_code(400);
            exit('La actividad del cronograma seleccionada no pertenece al proyecto de esta tarea.');
        }

        $repo->updateTask($taskId, [
            'title' => $title,
            'status' => $status,
            'priority' => $priority,
            'estimated_hours' => $estimatedHours,
            'due_date' => $dueDate !== '' ? $dueDate : null,
            'assignee_id' => $assigneeId > 0 ? $assigneeId : null,
            'schedule_activity_id' => $scheduleActivityId > 0 ? $scheduleActivityId : null,
        ]);

        if ($this->wantsJson()) {
            $updatedTask = $repo->find($taskId, $user);
            $this->json([
                'status' => 'ok',
                'task' => $updatedTask,
            ]);
            return;
        }

        $redirectTo = trim((string) ($_POST['redirect_to'] ?? ''));
        header('Location: ' . ($redirectTo !== '' ? $redirectTo : '/tasks'));
    }

    public function updateStatus(int $taskId): void
    {
        $repo = new TasksRepository($this->db);
        $user = $this->auth->user() ?? [];
        $canManage = $this->auth->can('projects.manage');
        $isTalent = $this->isTalent($user);
        if (!$canManage && !$isTalent) {
            $this->denyAccess();
        }

        $status = $this->normalizeStatus((string) ($_POST['status'] ?? ''));
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            http_response_code(400);
            exit('Estado de tarea inválido.');
        }

        if ($isTalent && !$canManage) {
            $userId = (int) ($user['id'] ?? 0);
            if (!$repo->canTalentUpdateTaskStatus($taskId, $userId)) {
                http_response_code(403);
                exit('No puedes cambiar tareas asignadas a otros usuarios.');
            }
        }

        $repo->updateStatus($taskId, $status);
        if ($this->wantsJson()) {
            $this->json([
                'status' => 'ok',
                'task_id' => $taskId,
                'task_status' => $status,
            ]);
            return;
        }

        $redirectTo = trim((string) ($_POST['redirect_to'] ?? ''));
        header('Location: ' . ($redirectTo !== '' ? $redirectTo : '/tasks'));
    }

    public function showApi(int $taskId): void
    {
        $this->requirePermission('tasks.view');
        $repo = new TasksRepository($this->db);
        $user = $this->auth->user() ?? [];
        $task = $repo->find($taskId, $user);
        if (!$task) {
            $this->json(['status' => 'error', 'message' => 'Tarea no encontrada.'], 404);
            return;
        }

        $canManage = $this->auth->can('projects.manage');
        $this->json([
            'status' => 'ok',
            'task' => $task,
            'can_manage' => $canManage,
            'talents' => $canManage ? (new TalentsRepository($this->db))->assignmentOptions() : [],
            'schedule_activities' => $repo->scheduleActivitiesForProject((int) ($task['project_id'] ?? 0)),
            'status_options' => self::KANBAN_STATUS_META,
            'priority_options' => [
                'low' => 'Baja',
                'medium' => 'Media',
                'high' => 'Alta',
            ],
        ]);
    }

    public function updateApi(int $taskId): void
    {
        $this->requirePermission('projects.manage');
        $repo = new TasksRepository($this->db);
        $user = $this->auth->user() ?? [];
        $task = $repo->find($taskId, $user);
        if (!$task) {
            $this->json(['status' => 'error', 'message' => 'Tarea no encontrada.'], 404);
            return;
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $status = $this->normalizeStatus((string) ($_POST['status'] ?? ''));
        $priority = strtolower(trim((string) ($_POST['priority'] ?? '')));
        $estimatedHours = (float) ($_POST['estimated_hours'] ?? 0);
        $dueDate = trim((string) ($_POST['due_date'] ?? ''));
        $assigneeId = (int) ($_POST['assignee_id'] ?? 0);
        $scheduleActivityId = (int) ($_POST['schedule_activity_id'] ?? 0);

        if ($title === '' || !in_array($status, self::ALLOWED_STATUSES, true) || !in_array($priority, self::ALLOWED_PRIORITIES, true)) {
            $this->json(['status' => 'error', 'message' => 'Completa los campos requeridos.'], 400);
            return;
        }
        if ($scheduleActivityId > 0 && !$repo->scheduleActivityBelongsToProject((int) ($task['project_id'] ?? 0), $scheduleActivityId)) {
            $this->json(['status' => 'error', 'message' => 'La actividad seleccionada no pertenece al proyecto.'], 400);
            return;
        }

        $repo->updateTask($taskId, [
            'title' => $title,
            'status' => $status,
            'priority' => $priority,
            'estimated_hours' => $estimatedHours,
            'due_date' => $dueDate !== '' ? $dueDate : null,
            'assignee_id' => $assigneeId > 0 ? $assigneeId : null,
            'schedule_activity_id' => $scheduleActivityId > 0 ? $scheduleActivityId : null,
        ]);

        $updatedTask = $repo->find($taskId, $user);
        $this->json([
            'status' => 'ok',
            'task' => $updatedTask,
        ]);
    }

    public function destroy(int $taskId): void
    {
        if (!$this->auth->hasRole('Administrador')) {
            $this->denyAccess();
        }

        $repo = new TasksRepository($this->db);
        $user = $this->auth->user() ?? [];
        $task = $repo->find($taskId, $user);
        if (!$task) {
            http_response_code(404);
            exit('Tarea no encontrada.');
        }

        $repo->deleteTask($taskId);
        header('Location: /tasks');
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        return match ($normalized) {
            'pending' => 'todo',
            'completed' => 'done',
            default => $normalized,
        };
    }

    private function isTalent(array $user): bool
    {
        return strcasecmp((string) ($user['role'] ?? ''), 'Talento') === 0;
    }

    private function isProjectClosed(array $project): bool
    {
        $status = strtolower(trim((string) ($project['status'] ?? '')));
        return in_array($status, ['closed', 'cerrado'], true);
    }

    private function buildKanbanColumns(array $tasks): array
    {
        $columns = [];
        foreach (self::KANBAN_STATUS_ORDER as $statusKey) {
            $columns[$statusKey] = [];
        }

        foreach ($tasks as $task) {
            $status = $this->normalizeStatus((string) ($task['status'] ?? 'todo'));
            if (!array_key_exists($status, $columns)) {
                $status = 'todo';
            }

            $task['kanban_status'] = $status;
            $task['has_stopper'] = (int) ($task['open_stoppers'] ?? 0) > 0;
            $columns[$status][] = $task;
        }

        return $columns;
    }

    private function buildKanbanByProject(array $tasks): array
    {
        $grouped = [];
        foreach ($tasks as $task) {
            $project = trim((string) ($task['project'] ?? 'Sin proyecto'));
            if (!isset($grouped[$project])) {
                $grouped[$project] = [];
                foreach (self::KANBAN_STATUS_ORDER as $statusKey) {
                    $grouped[$project][$statusKey] = [];
                }
            }

            $status = $this->normalizeStatus((string) ($task['status'] ?? 'todo'));
            if (!isset($grouped[$project][$status])) {
                $status = 'todo';
            }

            $task['kanban_status'] = $status;
            $task['has_stopper'] = (int) ($task['open_stoppers'] ?? 0) > 0;
            $grouped[$project][$status][] = $task;
        }

        ksort($grouped);

        return $grouped;
    }

    private function buildTeamLoad(array $tasks): array
    {
        $summary = [];
        foreach ($tasks as $task) {
            $assignee = trim((string) ($task['assignee'] ?? ''));
            if ($assignee === '') {
                $assignee = 'Sin asignar';
            }

            if (!isset($summary[$assignee])) {
                $summary[$assignee] = [
                    'assignee' => $assignee,
                    'tasks_count' => 0,
                    'estimated_hours' => 0.0,
                    'blocked_count' => 0,
                ];
            }

            $summary[$assignee]['tasks_count']++;
            $summary[$assignee]['estimated_hours'] += (float) ($task['estimated_hours'] ?? 0);

            $status = $this->normalizeStatus((string) ($task['status'] ?? 'todo'));
            if ($status === 'blocked') {
                $summary[$assignee]['blocked_count']++;
            }
        }

        usort($summary, static function (array $left, array $right): int {
            $hoursComparison = (float) ($right['estimated_hours'] ?? 0) <=> (float) ($left['estimated_hours'] ?? 0);
            if ($hoursComparison !== 0) {
                return $hoursComparison;
            }

            return strcmp((string) ($left['assignee'] ?? ''), (string) ($right['assignee'] ?? ''));
        });

        return $summary;
    }

    private function wantsJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

        return str_contains($accept, 'application/json')
            || $requestedWith === 'xmlhttprequest'
            || str_starts_with($path, '/api/');
    }
}
