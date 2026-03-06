<?php

declare(strict_types=1);

use App\Repositories\TalentsRepository;
use App\Repositories\TasksRepository;
use App\Repositories\ProjectsRepository;

class TasksController extends Controller
{
    private const ALLOWED_STATUSES = ['todo', 'pending', 'in_progress', 'review', 'blocked', 'done', 'completed'];
    private const ALLOWED_PRIORITIES = ['low', 'medium', 'high'];

    public function index(): void
    {
        $this->requirePermission('tasks.view');
        $repo = new TasksRepository($this->db);
        $user = $this->auth->user() ?? [];
        $isPrivileged = in_array($user['role'] ?? '', ['Administrador', 'PMO'], true);

        $view = trim((string) ($_GET['view'] ?? ''));
        if ($view === 'kanban') {
            $this->kanban();
            return;
        }

        $this->render('tasks/index', [
            'title' => 'Tareas',
            'tasks' => $repo->listAll($user),
            'projectOptions' => (new ProjectsRepository($this->db))->summary($user),
            'talents' => (new TalentsRepository($this->db))->assignmentOptions(),
            'canManage' => $this->auth->can('projects.manage'),
            'isPrivileged' => $isPrivileged,
        ]);
    }

    public function kanban(): void
    {
        $this->requirePermission('tasks.view');
        $user = $this->auth->user() ?? [];
        $repo = new TasksRepository($this->db);
        $isPrivileged = in_array($user['role'] ?? '', ['Administrador', 'PMO'], true);
        $canManage = $this->auth->can('projects.manage');

        if ($isPrivileged) {
            $kanbanColumns = $repo->kanbanAll();
            $workload = $repo->workloadByTalent();
        } else {
            $kanbanColumns = $repo->kanbanForUser((int) ($user['id'] ?? 0));
            $workload = [];
        }

        $this->render('tasks/kanban', [
            'title' => 'Kanban de tareas',
            'kanbanColumns' => $kanbanColumns,
            'workload' => $workload,
            'canManage' => $canManage,
            'isPrivileged' => $isPrivileged,
            'talents' => $canManage ? (new TalentsRepository($this->db))->assignmentOptions() : [],
            'projectOptions' => $canManage ? (new ProjectsRepository($this->db))->summary($user) : [],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('projects.manage');

        $projectId = (int) ($_POST['project_id'] ?? 0);
        if ($projectId <= 0) {
            http_response_code(400);
            exit('Selecciona un proyecto válido.');
        }

        $user = $this->auth->user() ?? [];
        $project = (new ProjectsRepository($this->db))->findForUser($projectId, $user);
        if (!$project) {
            http_response_code(404);
            exit('Proyecto no encontrado.');
        }

        if (strtolower(trim((string) ($project['status'] ?? ''))) === 'closed') {
            http_response_code(400);
            exit('No puedes agregar tareas a un proyecto cerrado.');
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $priority = strtolower(trim((string) ($_POST['priority'] ?? 'medium')));
        $estimatedHours = (float) ($_POST['estimated_hours'] ?? 0);
        $dueDate = trim((string) ($_POST['due_date'] ?? ''));
        $assigneeId = (int) ($_POST['assignee_id'] ?? 0);

        if ($title === '') {
            http_response_code(400);
            exit('El título de la tarea es obligatorio.');
        }

        if (!in_array($priority, self::ALLOWED_PRIORITIES, true)) {
            http_response_code(400);
            exit('Prioridad de tarea inválida.');
        }

        (new TasksRepository($this->db))->createForProject($projectId, [
            'title' => $title,
            'priority' => $priority,
            'estimated_hours' => $estimatedHours,
            'due_date' => $dueDate !== '' ? $dueDate : null,
            'assignee_id' => $assigneeId > 0 ? $assigneeId : null,
        ]);

        $redirect = trim((string) ($_POST['redirect'] ?? ''));
        header('Location: ' . ($redirect !== '' ? $redirect : '/tasks'));
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

        if ($title === '' || !in_array($status, self::ALLOWED_STATUSES, true) || !in_array($priority, self::ALLOWED_PRIORITIES, true)) {
            http_response_code(400);
            exit('Completa los campos requeridos para editar la tarea.');
        }

        $repo->updateTask($taskId, [
            'title' => $title,
            'status' => $status,
            'priority' => $priority,
            'estimated_hours' => $estimatedHours,
            'due_date' => $dueDate !== '' ? $dueDate : null,
            'assignee_id' => $assigneeId > 0 ? $assigneeId : null,
        ]);

        header('Location: /tasks');
    }

    public function updateStatus(int $taskId): void
    {
        $user = $this->auth->user() ?? [];
        $repo = new TasksRepository($this->db);
        $canManage = $this->auth->can('projects.manage');
        $isPrivileged = in_array($user['role'] ?? '', ['Administrador', 'PMO'], true);

        if (!$canManage && !$isPrivileged) {
            $talentId = $repo->talentIdForUser((int) ($user['id'] ?? 0));
            $task = $repo->findById($taskId);
            if (!$task || (int) ($task['assignee_id'] ?? 0) !== (int) $talentId) {
                http_response_code(403);
                exit('No tienes permiso para cambiar el estado de esta tarea.');
            }
        }

        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            http_response_code(400);
            exit('Estado de tarea inválido.');
        }

        $repo->updateStatus($taskId, $status);

        $isJson = str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json')
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

        if ($isJson) {
            $this->json(['ok' => true, 'status' => $status]);
            return;
        }

        $redirect = trim((string) ($_POST['redirect'] ?? ''));
        header('Location: ' . ($redirect !== '' ? $redirect : '/tasks'));
    }
}
