<?php

declare(strict_types=1);

class ProjectsController extends Controller
{
    public function index(): void
    {
        $repo = new ProjectsRepository($this->db);
        $this->requirePermission('projects.view');
        $this->render('projects/index', [
            'title' => 'Portafolio de Proyectos',
            'projects' => $repo->summary($this->auth->user() ?? []),
        ]);
    }

    public function portfolio(?string $error = null): void
    {
        $this->requirePermission('projects.view');
        $repo = new ProjectsRepository($this->db);
        $user = $this->auth->user() ?? [];

        $this->render('projects/portfolio', [
            'title' => 'Portafolio por cliente',
            'clients' => $repo->portfolio($user),
            'error' => $error,
        ]);
    }

    public function assignTalent(): void
    {
        $this->requirePermission('projects.manage');
        $repo = new ProjectsRepository($this->db);

        try {
            $repo->assignTalent($this->collectAssignmentPayload());
            header('Location: /project/public/projects/portfolio');
        } catch (\Throwable $e) {
            error_log('Error al asignar talento: ' . $e->getMessage());
            $this->portfolio('No se pudo asignar el talento: ' . $e->getMessage());
        }
    }

    private function collectAssignmentPayload(): array
    {
        $allocationPercent = $_POST['allocation_percent'] ?? null;
        $weeklyHours = $_POST['weekly_hours'] ?? null;

        return [
            'project_id' => (int) ($_POST['project_id'] ?? 0),
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
}
