<?php

declare(strict_types=1);

class ClientsController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('clients.view');
        $repo = new ClientsRepository($this->db);
        $masterRepo = new MasterFilesRepository($this->db);
        $usersRepo = new UsersRepository($this->db);
        $canManage = $this->auth->can('clients.manage');

        $user = $this->auth->user() ?? [];
        $pmCandidates = array_filter(
            $usersRepo->all(),
            fn ($candidate) => ($candidate['active'] ?? 0) == 1 && in_array($candidate['role_name'] ?? '', ['Administrador', 'PMO', 'Líder de Proyecto'], true)
        );

        $this->render('clients/index', [
            'title' => 'Clientes',
            'clients' => $repo->listForUser($user),
            'sectors' => $masterRepo->list('client_sectors'),
            'categories' => $masterRepo->list('client_categories'),
            'priorities' => $masterRepo->list('priorities'),
            'statuses' => $masterRepo->list('client_status'),
            'projectManagers' => $pmCandidates,
            'canManage' => $canManage,
        ]);
    }

    public function show(int $id): void
    {
        $this->requirePermission('clients.view');
        $repo = new ClientsRepository($this->db);
        $masterRepo = new MasterFilesRepository($this->db);
        $usersRepo = new UsersRepository($this->db);
        $canManage = $this->auth->can('clients.manage');

        $user = $this->auth->user() ?? [];
        $client = $repo->findForUser($id, $user);

        if (!$client) {
            http_response_code(404);
            exit('Cliente no encontrado');
        }

        $this->render('clients/show', [
            'title' => 'Detalle de cliente',
            'client' => $client,
            'projects' => $repo->projectsForClient($id),
            'snapshot' => $repo->projectSnapshot($id),
            'sectors' => $masterRepo->list('client_sectors'),
            'categories' => $masterRepo->list('client_categories'),
            'priorities' => $masterRepo->list('priorities'),
            'statuses' => $masterRepo->list('client_status'),
            'projectManagers' => array_filter(
                $usersRepo->all(),
                fn ($candidate) => ($candidate['active'] ?? 0) == 1 && in_array($candidate['role_name'] ?? '', ['Administrador', 'PMO', 'Líder de Proyecto'], true)
            ),
            'canManage' => $canManage,
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('clients.manage');
        $repo = new ClientsRepository($this->db);
        $repo->create($this->collectPayload());
        header('Location: /project/public/clients');
    }

    public function update(): void
    {
        $this->requirePermission('clients.manage');
        $repo = new ClientsRepository($this->db);
        $repo->update((int) $_POST['id'], $this->collectPayload());
        header('Location: /project/public/clients');
    }

    public function destroy(): void
    {
        $this->requirePermission('clients.manage');
        $repo = new ClientsRepository($this->db);
        $repo->delete((int) $_POST['id']);
        header('Location: /project/public/clients');
    }

    private function collectPayload(): array
    {
        return [
            'name' => trim($_POST['name'] ?? ''),
            'sector_code' => $_POST['sector_code'] ?? '',
            'category_code' => $_POST['category_code'] ?? '',
            'priority' => $_POST['priority'] ?? '',
            'status_code' => $_POST['status_code'] ?? '',
            'pm_id' => $_POST['pm_id'] ?? null,
            'satisfaction' => ($_POST['satisfaction'] ?? '') !== '' ? (int) $_POST['satisfaction'] : null,
            'nps' => ($_POST['nps'] ?? '') !== '' ? (int) $_POST['nps'] : null,
            'risk_level' => trim($_POST['risk_level'] ?? ''),
            'tags' => trim($_POST['tags'] ?? ''),
            'area' => trim($_POST['area'] ?? ''),
            'feedback_notes' => trim($_POST['feedback_notes'] ?? ''),
            'feedback_history' => trim($_POST['feedback_history'] ?? ''),
            'operational_context' => trim($_POST['operational_context'] ?? ''),
        ];
    }
}
