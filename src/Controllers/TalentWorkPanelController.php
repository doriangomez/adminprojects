<?php

declare(strict_types=1);

use App\Repositories\TasksRepository;
use App\Repositories\TalentsRepository;
use App\Repositories\ProjectsRepository;
use App\Repositories\ProjectStoppersRepository;

class TalentWorkPanelController extends Controller
{
    private const ALLOWED_STATUSES = ['todo', 'pending', 'in_progress', 'review', 'blocked', 'done', 'completed'];
    private const ALLOWED_PRIORITIES = ['low', 'medium', 'high'];

    public function index(): void
    {
        $user = $this->auth->user() ?? [];
        $role = $user['role'] ?? '';

        if (in_array($role, ['Administrador', 'PMO'], true)) {
            $this->pmoPanel();
            return;
        }

        $this->talentPanel();
    }

    public function talentPanel(): void
    {
        $user = $this->auth->user() ?? [];
        $talentRepo = new TalentsRepository($this->db);
        $tasksRepo = new TasksRepository($this->db);

        $talent = $talentRepo->findByUserId((int) ($user['id'] ?? 0));
        if (!$talent) {
            $this->render('talent_panel/no_talent', [
                'title' => 'Panel de trabajo',
            ]);
            return;
        }

        $talentId = (int) $talent['id'];
        $kanban = $tasksRepo->kanbanForTalent($talentId);

        $allTaskIds = [];
        foreach ($kanban as $tasks) {
            foreach ($tasks as $task) {
                $allTaskIds[] = (int) $task['id'];
            }
        }

        $stoppers = $tasksRepo->stoppersForTasks($allTaskIds);
        $talentStoppers = $tasksRepo->stoppersForTalent($talentId);

        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekEnd = date('Y-m-d', strtotime('sunday this week'));
        $timesheetSummary = $tasksRepo->timesheetSummaryForTalent($talentId, $weekStart, $weekEnd);
        $weeklyHours = $tasksRepo->weeklyHoursForTalent($talentId, $weekStart, $weekEnd);

        $talentDetail = $talentRepo->find($talentId);
        $weeklyCapacity = (float) ($talentDetail['capacidad_horaria'] ?? 40);

        $projectOptions = (new ProjectsRepository($this->db))->summary($user);

        $this->render('talent_panel/index', [
            'title' => 'Panel de trabajo',
            'talent' => $talent,
            'kanban' => $kanban,
            'stoppers' => $stoppers,
            'talentStoppers' => $talentStoppers,
            'timesheetSummary' => $timesheetSummary,
            'weeklyHours' => $weeklyHours,
            'weeklyCapacity' => $weeklyCapacity,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'projectOptions' => $projectOptions,
            'isTalent' => true,
        ]);
    }

    public function pmoPanel(): void
    {
        $user = $this->auth->user() ?? [];
        $role = $user['role'] ?? '';

        if (!in_array($role, ['Administrador', 'PMO'], true)) {
            $this->denyAccess();
            return;
        }

        $tasksRepo = new TasksRepository($this->db);
        $talentsRepo = new TalentsRepository($this->db);

        $kanban = $tasksRepo->kanbanAll();
        $workload = $tasksRepo->talentWorkloadSummary();
        $allTasks = $tasksRepo->allTasksTable($user);
        $talents = $talentsRepo->assignmentOptions();
        $projectOptions = (new ProjectsRepository($this->db))->summary($user);

        $allTaskIds = [];
        foreach ($kanban as $tasks) {
            foreach ($tasks as $task) {
                $allTaskIds[] = (int) $task['id'];
            }
        }
        $stoppers = $tasksRepo->stoppersForTasks($allTaskIds);

        $filterProject = (int) ($_GET['project_id'] ?? 0);
        $filterTalent = (int) ($_GET['talent_id'] ?? 0);

        $this->render('talent_panel/pmo', [
            'title' => 'Panel PMO — Gestión de tareas',
            'kanban' => $kanban,
            'workload' => $workload,
            'allTasks' => $allTasks,
            'talents' => $talents,
            'projectOptions' => $projectOptions,
            'stoppers' => $stoppers,
            'filterProject' => $filterProject,
            'filterTalent' => $filterTalent,
            'isAdmin' => $role === 'Administrador',
        ]);
    }

    public function updateTaskStatus(int $taskId): void
    {
        $user = $this->auth->user() ?? [];
        $role = $user['role'] ?? '';
        $tasksRepo = new TasksRepository($this->db);

        $status = trim((string) ($_POST['status'] ?? ''));
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            http_response_code(400);
            exit('Estado inválido.');
        }

        if (in_array($role, ['Administrador', 'PMO'], true)) {
            $tasksRepo->updateStatus($taskId, $status);
            $this->redirectBack();
            return;
        }

        $talent = (new TalentsRepository($this->db))->findByUserId((int) ($user['id'] ?? 0));
        if (!$talent) {
            http_response_code(403);
            exit('No tienes un perfil de talento asociado.');
        }

        $task = $tasksRepo->find($taskId, $user);
        if (!$task || (int) ($task['assignee_id'] ?? 0) !== (int) $talent['id']) {
            http_response_code(403);
            exit('No puedes cambiar el estado de esta tarea.');
        }

        $tasksRepo->updateStatus($taskId, $status);
        $this->redirectBack();
    }

    public function createTask(): void
    {
        $user = $this->auth->user() ?? [];
        $role = $user['role'] ?? '';

        $projectId = (int) ($_POST['project_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $priority = strtolower(trim((string) ($_POST['priority'] ?? 'medium')));
        $estimatedHours = (float) ($_POST['estimated_hours'] ?? 0);
        $dueDate = trim((string) ($_POST['due_date'] ?? ''));
        $assigneeId = (int) ($_POST['assignee_id'] ?? 0);

        if ($projectId <= 0 || $title === '') {
            http_response_code(400);
            exit('Proyecto y título son obligatorios.');
        }

        if (!in_array($priority, self::ALLOWED_PRIORITIES, true)) {
            $priority = 'medium';
        }

        if (in_array($role, ['Administrador', 'PMO'], true)) {
            (new TasksRepository($this->db))->createForProject($projectId, [
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'estimated_hours' => $estimatedHours,
                'due_date' => $dueDate !== '' ? $dueDate : null,
                'assignee_id' => $assigneeId > 0 ? $assigneeId : null,
            ]);
            $this->redirectBack();
            return;
        }

        $talent = (new TalentsRepository($this->db))->findByUserId((int) ($user['id'] ?? 0));
        if (!$talent) {
            http_response_code(403);
            exit('No tienes un perfil de talento asociado.');
        }

        (new TasksRepository($this->db))->createForProject($projectId, [
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'estimated_hours' => $estimatedHours,
            'due_date' => $dueDate !== '' ? $dueDate : null,
            'assignee_id' => (int) $talent['id'],
        ]);

        $this->redirectBack();
    }

    public function reassignTask(int $taskId): void
    {
        $user = $this->auth->user() ?? [];
        $role = $user['role'] ?? '';

        if (!in_array($role, ['Administrador', 'PMO'], true)) {
            http_response_code(403);
            exit('No tienes permisos para reasignar tareas.');
        }

        $assigneeId = (int) ($_POST['assignee_id'] ?? 0);
        $repo = new TasksRepository($this->db);
        $task = $repo->find($taskId, $user);
        if (!$task) {
            http_response_code(404);
            exit('Tarea no encontrada.');
        }

        $repo->updateTask($taskId, [
            'title' => $task['title'],
            'status' => $task['status'],
            'priority' => $task['priority'],
            'estimated_hours' => $task['estimated_hours'],
            'due_date' => $task['due_date'],
            'assignee_id' => $assigneeId > 0 ? $assigneeId : null,
        ]);

        $this->redirectBack();
    }

    public function deleteTask(int $taskId): void
    {
        $user = $this->auth->user() ?? [];
        if (($user['role'] ?? '') !== 'Administrador') {
            http_response_code(403);
            exit('Solo el administrador puede eliminar tareas.');
        }

        (new TasksRepository($this->db))->deleteTask($taskId);
        $this->redirectBack();
    }

    public function updateTaskStatusApi(int $taskId): void
    {
        $user = $this->auth->user() ?? [];
        $role = $user['role'] ?? '';
        $tasksRepo = new TasksRepository($this->db);

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $status = trim((string) ($input['status'] ?? $_POST['status'] ?? ''));

        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            $this->json(['error' => 'Estado inválido'], 400);
            return;
        }

        if (in_array($role, ['Administrador', 'PMO'], true)) {
            $tasksRepo->updateStatus($taskId, $status);
            $this->json(['ok' => true, 'status' => $status]);
            return;
        }

        $talent = (new TalentsRepository($this->db))->findByUserId((int) ($user['id'] ?? 0));
        if (!$talent) {
            $this->json(['error' => 'Sin perfil de talento'], 403);
            return;
        }

        $task = $this->db->fetchOne(
            'SELECT assignee_id FROM tasks WHERE id = :id LIMIT 1',
            [':id' => $taskId]
        );

        if (!$task || (int) ($task['assignee_id'] ?? 0) !== (int) $talent['id']) {
            $this->json(['error' => 'No puedes cambiar esta tarea'], 403);
            return;
        }

        $tasksRepo->updateStatus($taskId, $status);
        $this->json(['ok' => true, 'status' => $status]);
    }

    private function redirectBack(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/talent-panel';
        header('Location: ' . $referer);
    }
}
