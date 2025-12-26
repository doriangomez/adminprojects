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
        $config = (new ConfigService())->getConfig();
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

        $config = (new ConfigService())->getConfig();

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

        $payload = $this->projectPayload($project);
        $repo->updateProject($id, $payload);

        header('Location: /project/public/projects/' . $id);
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

    private function projectPayload(array $current): array
    {
        return [
            'name' => trim($_POST['name'] ?? (string) ($current['name'] ?? '')),
            'status' => $_POST['status'] ?? (string) ($current['status'] ?? ''),
            'health' => $_POST['health'] ?? (string) ($current['health'] ?? ''),
            'priority' => $_POST['priority'] ?? (string) ($current['priority'] ?? ''),
            'pm_id' => (int) ($_POST['pm_id'] ?? ($current['pm_id'] ?? 0)),
            'project_type' => $_POST['project_type'] ?? (string) ($current['project_type'] ?? 'convencional'),
            'budget' => (float) ($_POST['budget'] ?? ($current['budget'] ?? 0)),
            'actual_cost' => (float) ($_POST['actual_cost'] ?? ($current['actual_cost'] ?? 0)),
            'planned_hours' => (float) ($_POST['planned_hours'] ?? ($current['planned_hours'] ?? 0)),
            'actual_hours' => (float) ($_POST['actual_hours'] ?? ($current['actual_hours'] ?? 0)),
            'progress' => (float) ($_POST['progress'] ?? ($current['progress'] ?? 0)),
            'start_date' => $_POST['start_date'] ?? ($current['start_date'] ?? null),
            'end_date' => $_POST['end_date'] ?? ($current['end_date'] ?? null),
            'methodology' => $_POST['methodology'] ?? ($current['methodology'] ?? 'scrum'),
            'phase' => $_POST['phase'] ?? ($current['phase'] ?? null),
            'risks' => array_filter($_POST['risks'] ?? []),
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
}
