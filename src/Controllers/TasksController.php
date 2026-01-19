<?php

declare(strict_types=1);

class TasksController extends Controller
{
    private const ALLOWED_STATUSES = ['todo', 'in_progress', 'review', 'blocked', 'done'];
    private const ALLOWED_PRIORITIES = ['low', 'medium', 'high'];

    public function index(): void
    {
        $repo = new TasksRepository($this->db);
        $this->requirePermission('tasks.view');
        $this->render('tasks/index', [
            'title' => 'Tareas',
            'tasks' => $repo->listAll($this->auth->user() ?? []),
        ]);
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

        header('Location: /project/public/tasks');
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
        header('Location: /project/public/tasks');
    }
}
