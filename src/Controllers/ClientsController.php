<?php

declare(strict_types=1);

class ClientsController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('clients.view');
        $repo = new ClientsRepository($this->db);
        $canManage = $this->auth->can('clients.manage');
        $canDelete = $this->isAdmin();

        $user = $this->auth->user() ?? [];

        $this->render('clients/index', [
            'title' => 'Clientes',
            'clients' => $repo->listForUser($user),
            'canManage' => $canManage,
            'canDelete' => $canDelete,
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
        $isAdmin = $this->isAdmin();

        $user = $this->auth->user() ?? [];
        $client = $repo->findForUser($id, $user);
        $dependencies = $repo->dependencySummary($id);
        $hasDependencies = $dependencies['has_dependencies'] ?? false;
        $canDelete = $isAdmin;
        $canInactivate = $isAdmin;

        if (!$client) {
            http_response_code(404);
            exit('Cliente no encontrado');
        }

        $this->render('clients/show', [
            'title' => 'Detalle de cliente',
            'client' => $client,
            'projects' => $repo->projectsForClient($id, $user),
            'snapshot' => $repo->projectSnapshot($id, $user),
            'canManage' => $canManage,
            'canDelete' => $canDelete,
            'canInactivate' => $canInactivate,
            'isAdmin' => $isAdmin,
            'dependencies' => $dependencies,
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

        try {
            $repo->create($this->collectPayload());
            header('Location: /project/public/clients');
        } catch (\PDOException $e) {
            error_log('Error al crear cliente: ' . $e->getMessage());
            http_response_code(500);
            $this->render('clients/create', array_merge($this->formData(), [
                'title' => 'Registrar cliente',
                'error' => 'No se pudo guardar el cliente. Intenta nuevamente o contacta al administrador.',
            ]));
        }
    }

    public function update(int $id): void
    {
        $this->requirePermission('clients.manage');
        $repo = new ClientsRepository($this->db);

        try {
            $repo->update($id, $this->collectPayload($_POST['current_logo'] ?? ''));
            header('Location: /project/public/clients');
        } catch (\PDOException $e) {
            error_log('Error al actualizar cliente: ' . $e->getMessage());
            http_response_code(500);

            $user = $this->auth->user() ?? [];
            $client = (new ClientsRepository($this->db))->findForUser($id, $user);

            if (!$client) {
                exit('Cliente no encontrado');
            }

            $this->render('clients/edit', array_merge($this->formData(), [
                'title' => 'Editar cliente',
                'client' => $client,
                'error' => 'No se pudo actualizar el cliente. Intenta nuevamente o contacta al administrador.',
            ]));
        }
    }

    public function destroy(): void
    {
        $this->requirePermission('clients.manage');

        $repo = new ClientsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $clientId = (int) ($_POST['id'] ?? 0);
        $mathOperator = trim((string) ($_POST['math_operator'] ?? ''));
        $operand1 = isset($_POST['math_operand1']) ? (int) $_POST['math_operand1'] : 0;
        $operand2 = isset($_POST['math_operand2']) ? (int) $_POST['math_operand2'] : 0;
        $mathResult = trim((string) ($_POST['math_result'] ?? ''));
        $client = $repo->find($clientId);
        $dependencies = $repo->dependencySummary($clientId);

        if ($clientId <= 0 || !$client) {
            http_response_code(404);
            exit('Cliente no encontrado');
        }

        if (!in_array($mathOperator, ['+', '-'], true) || $operand1 < 1 || $operand1 > 10 || $operand2 < 1 || $operand2 > 10) {
            http_response_code(400);
            exit('La verificación matemática no es válida.');
        }

        $expected = $mathOperator === '+' ? $operand1 + $operand2 : $operand1 - $operand2;

        if ($mathResult === '' || (int) $mathResult !== $expected) {
            http_response_code(400);
            exit('La confirmación matemática es incorrecta.');
        }

        try {
            if (!$this->isAdmin()) {
                http_response_code(403);
                $this->json([
                    'success' => false,
                    'message' => 'Acción no permitida por permisos',
                ], 403);
                return;
            }

            if ($dependencies['has_dependencies'] ?? false) {
                $this->json([
                    'success' => false,
                    'can_inactivate' => true,
                    'message' => 'El cliente tiene dependencias activas. Puede inactivarse pero no eliminarse.',
                    'dependencies' => $dependencies,
                ], 409);
                return;
            }

            $result = $repo->deleteWithoutDependencies($clientId, $client['logo_path'] ?? null);

            if (($result['success'] ?? false) === true) {
                error_log(sprintf(
                    '[audit] Usuario %s (ID: %d) eliminó cliente "%s" (ID: %d) con %d portafolios y %d proyectos a las %s',
                    $user['name'] ?? 'desconocido',
                    (int) ($user['id'] ?? 0),
                    $client['name'],
                    $clientId,
                    (int) ($dependencies['portfolios'] ?? 0),
                    (int) ($dependencies['projects'] ?? 0),
                    date('c')
                ));

                $this->json([
                    'success' => true,
                    'message' => 'Eliminado correctamente',
                ]);
                return;
            }

            $errorCode = (string) ($result['error_code'] ?? '');
            $status = $errorCode === '23000' ? 409 : 500;
            $message = $errorCode === 'DEPENDENCIES'
                ? 'El cliente tiene dependencias activas. Puede inactivarse pero no eliminarse.'
                : 'No se pudo eliminar el cliente. Intenta nuevamente o contacta al administrador.';

            error_log('Error al eliminar cliente: ' . ($result['error'] ?? 'operación desconocida'));

            $this->json([
                'success' => false,
                'message' => $message,
            ], $status);
        } catch (\Throwable $e) {
            error_log('Error al eliminar cliente: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo eliminar el cliente. Intenta nuevamente o contacta al administrador.');
        }
    }

    public function inactivate(int $id): void
    {
        $this->requirePermission('clients.manage');

        $repo = new ClientsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $clientId = $id;
        $mathOperator = trim((string) ($_POST['math_operator'] ?? ''));
        $operand1 = isset($_POST['math_operand1']) ? (int) $_POST['math_operand1'] : 0;
        $operand2 = isset($_POST['math_operand2']) ? (int) $_POST['math_operand2'] : 0;
        $mathResult = trim((string) ($_POST['math_result'] ?? ''));
        $client = $repo->find($clientId);

        if ($clientId <= 0 || !$client) {
            http_response_code(404);
            exit('Cliente no encontrado');
        }

        if (!in_array($mathOperator, ['+', '-'], true) || $operand1 < 1 || $operand1 > 10 || $operand2 < 1 || $operand2 > 10) {
            http_response_code(400);
            exit('La verificación matemática no es válida.');
        }

        $expected = $mathOperator === '+' ? $operand1 + $operand2 : $operand1 - $operand2;

        if ($mathResult === '' || (int) $mathResult !== $expected) {
            http_response_code(400);
            exit('La confirmación matemática es incorrecta.');
        }

        if (!$this->isAdmin()) {
            http_response_code(403);
            $this->json([
                'success' => false,
                'message' => 'Acción no permitida por permisos',
            ], 403);
            return;
        }

        try {
            $repo->inactivate($clientId);

            error_log(sprintf(
                '[audit] Usuario %s (ID: %d) inactivó cliente "%s" (ID: %d) a las %s',
                $user['name'] ?? 'desconocido',
                (int) ($user['id'] ?? 0),
                $client['name'],
                $clientId,
                date('c')
            ));

            $this->json([
                'success' => true,
                'message' => 'Cliente inactivado correctamente',
            ]);
        } catch (\Throwable $e) {
            error_log('Error al inactivar cliente: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo inactivar el cliente. Intenta nuevamente o contacta al administrador.');
        }
    }

    private function collectPayload(string $currentLogoPath = ''): array
    {
        $logoPath = $this->handleLogoUpload($_FILES['logo'] ?? null, $currentLogoPath);
        $catalogs = $this->catalogData();
        $sectorCode = $this->validatedCatalogValue($_POST['sector_code'] ?? '', $catalogs['sectors'], 'sector');
        $categoryCode = $this->validatedCatalogValue($_POST['category_code'] ?? '', $catalogs['categories'], 'categoría');
        $priorityCode = $this->validatedCatalogValue($_POST['priority_code'] ?? '', $catalogs['priorities'], 'prioridad');
        $statusCode = $this->validatedCatalogValue($_POST['status_code'] ?? '', $catalogs['statuses'], 'estado');
        $riskCode = $this->validatedCatalogValue($_POST['risk_code'] ?? '', $catalogs['risks'], 'riesgo', false);
        $areaCode = $this->validatedCatalogValue($_POST['area_code'] ?? '', $catalogs['areas'], 'área', false);

        return [
            'name' => trim($_POST['name'] ?? ''),
            'sector_code' => $sectorCode,
            'category_code' => $categoryCode,
            'priority_code' => $priorityCode,
            'status_code' => $statusCode,
            'pm_id' => $this->validatedPmId(),
            'satisfaction' => ($_POST['satisfaction'] ?? '') !== '' ? (int) $_POST['satisfaction'] : null,
            'nps' => ($_POST['nps'] ?? '') !== '' ? (int) $_POST['nps'] : null,
            'risk_code' => $riskCode,
            'tags' => trim($_POST['tags'] ?? ''),
            'area_code' => $areaCode,
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
        $usersRepo = new UsersRepository($this->db);

        return array_merge($this->catalogData(), [
            'projectManagers' => array_filter(
                $usersRepo->all(),
                fn ($candidate) => ($candidate['active'] ?? 0) == 1 && in_array($candidate['role_name'] ?? '', ['Administrador', 'PMO', 'Líder de Proyecto'], true)
            ),
        ]);
    }

    private function catalogData(): array
    {
        $masterRepo = new MasterFilesRepository($this->db);

        return [
            'sectors' => $masterRepo->list('client_sectors'),
            'categories' => $masterRepo->list('client_categories'),
            'priorities' => $masterRepo->list('priorities'),
            'statuses' => $masterRepo->list('client_status'),
            'risks' => $masterRepo->list('client_risk'),
            'areas' => $masterRepo->list('client_areas'),
        ];
    }

    private function validatedPmId(): int
    {
        $pmId = (int) ($_POST['pm_id'] ?? 0);
        if ($pmId <= 0) {
            http_response_code(400);
            exit('Debes seleccionar un PM válido para el cliente.');
        }

        $usersRepo = new UsersRepository($this->db);
        if (!$usersRepo->isValidProjectManager($pmId)) {
            http_response_code(400);
            exit('El PM seleccionado no está disponible o no tiene permisos para gestionar clientes.');
        }

        return $pmId;
    }

    private function validatedCatalogValue(string $value, array $catalog, string $fieldLabel, bool $required = true): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            if ($required) {
                http_response_code(400);
                exit('Valor inválido para ' . $fieldLabel . '.');
            }
            return null;
        }

        $codes = array_column($catalog, 'code');
        if (!in_array($trimmed, $codes, true)) {
            http_response_code(400);
            exit('Valor inválido para ' . $fieldLabel . '.');
        }

        return $trimmed;
    }

    private function isAdmin(): bool
    {
        return ($this->auth->user()['role'] ?? '') === 'Administrador';
    }
}
