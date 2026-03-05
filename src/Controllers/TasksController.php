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
        $repo = new TasksRepository($this->db);
        $this->requirePermission('tasks.view');
        $user = $this->auth->user() ?? [];

        $this->render('tasks/index', [
            'title' => 'Tareas',
            'tasks' => $repo->listAll($user),
            'projectOptions' => (new ProjectsRepository($this->db))->summary($user),
            'talents' => (new TalentsRepository($this->db))->assignmentOptions(),
            'canManage' => $this->auth->can('projects.manage'),
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

        header('Location: /tasks');
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
        $this->requirePermission('projects.manage');

        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            http_response_code(400);
            exit('Estado de tarea inválido.');
        }

        (new TasksRepository($this->db))->updateStatus($taskId, $status);
        header('Location: /tasks');
    }
}
