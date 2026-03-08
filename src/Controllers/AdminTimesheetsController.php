<?php

declare(strict_types=1);

use App\Repositories\ClientsRepository;
use App\Repositories\TimesheetsRepository;

class AdminTimesheetsController extends Controller
{
    public function index(): void
    {
        if (!$this->auth->canManageAdvancedTimesheets() && !$this->auth->hasRole('PMO') && !$this->auth->hasRole('Administrador')) {
            $this->denyAccess('Solo PMO y administradores pueden acceder a la vista administrativa de timesheets.');
        }

        $repo = new TimesheetsRepository($this->db);
        $clientsRepo = new ClientsRepository($this->db);
        $user = $this->auth->user() ?? [];

        $filters = [
            'user_id' => !empty($_GET['user_id']) ? (int) $_GET['user_id'] : null,
            'project_id' => !empty($_GET['project_id']) ? (int) $_GET['project_id'] : null,
            'client_id' => !empty($_GET['client_id']) ? (int) $_GET['client_id'] : null,
            'week_start' => trim((string) ($_GET['week_start'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];

        $entries = $repo->adminList($filters);
        $clients = $clientsRepo->listForUser($user);
        $hasActiveColumn = $this->db->columnExists('projects', 'active');
        $projectsWhere = $hasActiveColumn ? 'WHERE active = 1' : '';
        $projects = $this->db->fetchAll("SELECT id, name FROM projects $projectsWhere ORDER BY name");
        $users = $this->db->fetchAll('SELECT id, name FROM users WHERE active = 1 ORDER BY name');

        $this->render('admin_timesheets/index', [
            'title' => 'Timesheets · Vista administrativa',
            'entries' => $entries,
            'filters' => $filters,
            'clients' => $clients,
            'projects' => $projects,
            'users' => $users,
        ]);
    }
}
