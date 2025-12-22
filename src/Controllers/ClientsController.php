<?php

declare(strict_types=1);

class ClientsController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('clients.view');
        $repo = new ClientsRepository($this->db);
        $canManage = $this->auth->can('clients.manage');

        $user = $this->auth->user() ?? [];

        $this->render('clients/index', [
            'title' => 'Clientes',
            'clients' => $repo->listForUser($user),
            'canManage' => $canManage,
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('clients.manage');
        $formData = $this->formData();

        $this->render('clients/create', array_merge($formData, [
            'title' => 'Registrar cliente',
        ]));
    }

    public function show(int $id): void
    {
        $this->requirePermission('clients.view');
        $repo = new ClientsRepository($this->db);
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
            'canManage' => $canManage,
        ]);
    }

    public function edit(int $id): void
    {
        $this->requirePermission('clients.manage');

        $repo = new ClientsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $client = $repo->findForUser($id, $user);

        if (!$client) {
            http_response_code(404);
            exit('Cliente no encontrado');
        }

        $formData = $this->formData();

        $this->render('clients/edit', array_merge($formData, [
            'title' => 'Editar cliente',
            'client' => $client,
        ]));
    }

    public function store(): void
    {
        $this->requirePermission('clients.manage');
        $repo = new ClientsRepository($this->db);
        $repo->create($this->collectPayload());
        header('Location: /project/public/clients');
    }

    public function update(int $id): void
    {
        $this->requirePermission('clients.manage');
        $repo = new ClientsRepository($this->db);
        $repo->update($id, $this->collectPayload($_POST['current_logo'] ?? ''));
        header('Location: /project/public/clients');
    }

    public function destroy(): void
    {
        $this->requirePermission('clients.manage');
        $repo = new ClientsRepository($this->db);
        $repo->delete((int) $_POST['id']);
        header('Location: /project/public/clients');
    }

    private function collectPayload(string $currentLogoPath = ''): array
    {
        $logoPath = $this->handleLogoUpload($_FILES['logo'] ?? null, $currentLogoPath);

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
            'logo_path' => $logoPath ?: null,
        ];
    }

    private function handleLogoUpload(?array $file, string $currentPath = ''): string
    {
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $currentPath;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            http_response_code(400);
            exit('Error al subir el archivo de logo.');
        }

        $allowedTypes = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/svg+xml' => 'svg',
        ];

        $mime = mime_content_type($file['tmp_name']);
        if (!isset($allowedTypes[$mime])) {
            http_response_code(400);
            exit('El logo debe ser PNG, JPG o SVG.');
        }

        $extension = $allowedTypes[$mime];
        $uploadDir = __DIR__ . '/../../public/uploads/clients/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            http_response_code(500);
            exit('No se pudo preparar el directorio de subida.');
        }

        $fileName = uniqid('client_', true) . '.' . $extension;
        $destination = $uploadDir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            http_response_code(500);
            exit('No se pudo guardar el logo del cliente.');
        }

        return '/uploads/clients/' . $fileName;
    }

    private function formData(): array
    {
        $masterRepo = new MasterFilesRepository($this->db);
        $usersRepo = new UsersRepository($this->db);

        return [
            'sectors' => $masterRepo->list('client_sectors'),
            'categories' => $masterRepo->list('client_categories'),
            'priorities' => $masterRepo->list('priorities'),
            'statuses' => $masterRepo->list('client_status'),
            'projectManagers' => array_filter(
                $usersRepo->all(),
                fn ($candidate) => ($candidate['active'] ?? 0) == 1 && in_array($candidate['role_name'] ?? '', ['Administrador', 'PMO', 'Líder de Proyecto'], true)
            ),
        ];
    }
}
