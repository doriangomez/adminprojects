<?php

declare(strict_types=1);

use App\Repositories\ClientsRepository;
use App\Repositories\MasterFilesRepository;
use App\Repositories\UsersRepository;

class ClientsController extends Controller
{
    private const CLIENT_CREATE_SQL = 'INSERT INTO clients (name, sector_code, category_code, priority_code, status_code, pm_id, satisfaction, nps, risk_code, tags, area_code, feedback_notes, feedback_history, operational_context, logo_path, created_at, updated_at) VALUES (:name, :sector_code, :category_code, :priority_code, :status_code, :pm_id, :satisfaction, :nps, :risk_code, :tags, :area_code, :feedback_notes, :feedback_history, :operational_context, :logo_path, NOW(), NOW())';

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
        $dependencies = $repo->dependencySummary($id);
        $hasDependencies = $dependencies['has_dependencies'] ?? false;
        $isAdmin = $this->isAdmin();
        $canDelete = $isAdmin;
        $canInactivate = $canManage || $isAdmin;
        $mathOperand1 = random_int(1, 10);
        $mathOperand2 = random_int(1, 10);
        $mathOperator = random_int(0, 1) === 0 ? '+' : '-';

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
            'dependencies' => $dependencies,
            'hasDependencies' => $hasDependencies,
            'canDelete' => $canDelete,
            'canInactivate' => $canInactivate,
            'isAdmin' => $isAdmin,
            'mathOperand1' => $mathOperand1,
            'mathOperand2' => $mathOperand2,
            'mathOperator' => $mathOperator,
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
        error_log('[clients.create] Entré a crear cliente');
        error_log(sprintf(
            '[clients.create] request method=%s uri=%s actionEsperada=/clients/create contentType=%s postCount=%d postKeys=%s fileKeys=%s',
            $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            $_SERVER['REQUEST_URI'] ?? 'unknown',
            $_SERVER['CONTENT_TYPE'] ?? 'unknown',
            count($_POST),
            implode(',', array_keys($_POST)),
            implode(',', array_keys($_FILES))
        ));
        $this->logDatabaseDiagnostics();

        $repo = new ClientsRepository($this->db);
        $requestPayload = [
            'post' => $_POST,
            'files' => $this->filesDiagnostics($_FILES),
        ];

        try {
            $payload = $this->collectPayload();
            $this->validateRequiredPayloadFields($payload);
            error_log('[clients.create] Voy a insertar | campos recibidos=' . json_encode(array_keys($payload), JSON_UNESCAPED_UNICODE));
            error_log('[clients.create] payload recibido (campos): ' . json_encode($this->payloadDiagnostics($payload), JSON_UNESCAPED_UNICODE));
            error_log('[clients.create] SQL a ejecutar (plantilla): ' . self::CLIENT_CREATE_SQL);
            $newClientId = $repo->create($payload);
            error_log('[clients.create] Resultado INSERT | ok=1 | client_id=' . $newClientId);
            $this->render('clients/create', array_merge($this->formData(), [
                'title' => 'Registrar cliente',
                'diagnostic' => [
                    'insert_ok' => true,
                    'insert_id' => $newClientId,
                    'internal_message' => 'Cliente guardado correctamente.',
                    'request_payload' => $requestPayload,
                    'save_result' => [
                        'status' => 'success',
                        'message' => 'INSERT ejecutado sin errores.',
                    ],
                ],
            ]));
            return;
        } catch (\InvalidArgumentException $e) {
            error_log('[clients.create] Validación fallida: ' . $e->getMessage());
            http_response_code(422);
            $this->render('clients/create', array_merge($this->formData(), [
                'title' => 'Registrar cliente',
                'error' => $e->getMessage(),
                'diagnostic' => [
                    'insert_ok' => false,
                    'insert_id' => null,
                    'internal_message' => $e->getMessage(),
                    'request_payload' => $requestPayload,
                    'save_result' => [
                        'status' => 'validation_error',
                        'message' => 'No se intentó guardar por validación.',
                    ],
                ],
            ]));
            return;
        } catch (\PDOException $e) {
            $errorInfo = $e->errorInfo ?? null;
            error_log('[clients.create] Resultado INSERT | ok=0 | mensaje=' . $e->getMessage() . ' | code=' . (string) $e->getCode() . ' | errorInfo=' . json_encode($errorInfo, JSON_UNESCAPED_UNICODE));
            http_response_code(500);
            $this->render('clients/create', array_merge($this->formData(), [
                'title' => 'Registrar cliente',
                'error' => 'No se pudo guardar el cliente. Intenta nuevamente o contacta al administrador.',
                'diagnostic' => [
                    'insert_ok' => false,
                    'insert_id' => null,
                    'internal_message' => $e->getMessage(),
                    'request_payload' => $requestPayload,
                    'save_result' => [
                        'status' => 'database_error',
                        'message' => 'El INSERT falló en base de datos.',
                        'code' => (string) $e->getCode(),
                        'error_info' => $errorInfo,
                    ],
                ],
            ]));
            return;
        } catch (\Throwable $e) {
            error_log('Error inesperado al crear cliente: ' . $e->getMessage());
            http_response_code(500);
            $this->render('clients/create', array_merge($this->formData(), [
                'title' => 'Registrar cliente',
                'error' => 'Se detectó un error inesperado durante la creación. Revisa el log para más detalle.',
                'diagnostic' => [
                    'insert_ok' => false,
                    'insert_id' => null,
                    'internal_message' => $e->getMessage(),
                    'request_payload' => $requestPayload,
                    'save_result' => [
                        'status' => 'unexpected_error',
                        'message' => 'Se produjo una excepción no controlada.',
                    ],
                ],
            ]));
            return;
        }
    }

    private function filesDiagnostics(array $files): array
    {
        $output = [];

        foreach ($files as $field => $file) {
            $output[$field] = [
                'name' => $file['name'] ?? null,
                'type' => $file['type'] ?? null,
                'size' => $file['size'] ?? null,
                'error' => $file['error'] ?? null,
            ];
        }

        return $output;
    }

    public function update(int $id): void
    {
        $this->requirePermission('clients.manage');
        $repo = new ClientsRepository($this->db);

        try {
            $repo->update($id, $this->collectPayload($_POST['current_logo'] ?? ''));
            header('Location: /clients');
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
        $repo = new ClientsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $clientId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $client = $repo->find($clientId);
        $dependencies = $repo->dependencySummary($clientId);
        $mathOperator = trim((string) ($_POST['math_operator'] ?? ''));
        $operand1 = isset($_POST['math_operand1']) ? (int) $_POST['math_operand1'] : 0;
        $operand2 = isset($_POST['math_operand2']) ? (int) $_POST['math_operand2'] : 0;
        $mathResult = trim((string) ($_POST['math_result'] ?? ''));
        $forceDelete = filter_var($_POST['force'] ?? $_POST['force_delete'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $isAdmin = $this->isAdmin();
        $canInactivate = $this->auth->can('clients.manage') || $isAdmin;

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

        if ($forceDelete && !$isAdmin) {
            http_response_code(403);
            $this->json([
                'success' => false,
                'message' => 'Solo los administradores pueden eliminar clientes definitivamente.',
            ], 403);
            return;
        }

        try {
            $result = $repo->deleteClient($clientId, $client['logo_path'] ?? null, $forceDelete, $isAdmin);

            if (($result['success'] ?? false) === true) {
                $actionLabel = $forceDelete ? 'eliminó' : 'inactivó';
                error_log(sprintf(
                    '[audit] Usuario %s (ID: %d) %s cliente "%s" (ID: %d) con %d proyectos a las %s',
                    $user['name'] ?? 'desconocido',
                    (int) ($user['id'] ?? 0),
                    $actionLabel,
                    $client['name'],
                    $clientId,
                    (int) ($dependencies['projects'] ?? 0),
                    date('c')
                ));

                $this->json([
                    'success' => true,
                    'message' => $forceDelete ? 'Eliminado correctamente' : 'Cliente inactivado correctamente',
                ]);
                return;
            }

            $errorMessage = (string) ($result['error'] ?? 'Operación fallida al eliminar el cliente.');

            error_log('Error al eliminar cliente: ' . $errorMessage);

            $this->json([
                'success' => false,
                'message' => $errorMessage,
                'can_inactivate' => $canInactivate,
                'dependencies' => $dependencies,
                'inactivated' => $result['inactivated'] ?? false,
            ], 500);
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

        $name = trim($_POST['name'] ?? '');

        return [
            'name' => $name,
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
            error_log('[clients.create] Validación fallida: pm_id inválido o ausente.');
            throw new \InvalidArgumentException('Debes seleccionar un PM válido para el cliente.');
        }

        $usersRepo = new UsersRepository($this->db);
        if (!$usersRepo->isValidProjectManager($pmId)) {
            error_log('[clients.create] Validación fallida: pm_id no corresponde a un PM habilitado. pm_id=' . $pmId);
            throw new \InvalidArgumentException('El PM seleccionado no está disponible o no tiene permisos para gestionar clientes.');
        }

        error_log('[clients.create] Validación OK: pm_id=' . $pmId);

        return $pmId;
    }

    private function validatedCatalogValue(string $value, array $catalog, string $fieldLabel, bool $required = true): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            if ($required) {
                error_log('[clients.create] Validación fallida: campo requerido vacío (' . $fieldLabel . ').');
                throw new \InvalidArgumentException('Valor inválido para ' . $fieldLabel . '.');
            }

            error_log('[clients.create] Validación OK: campo opcional vacío permitido (' . $fieldLabel . ').');
            return null;
        }

        $codes = array_column($catalog, 'code');
        if (!in_array($trimmed, $codes, true)) {
            error_log('[clients.create] Validación fallida: código fuera de catálogo para ' . $fieldLabel . ' (' . $trimmed . ').');
            throw new \InvalidArgumentException('Valor inválido para ' . $fieldLabel . '.');
        }

        error_log('[clients.create] Validación OK: ' . $fieldLabel . '=' . $trimmed);

        return $trimmed;
    }


    private function validateRequiredPayloadFields(array $payload): void
    {
        $requiredFields = [
            'name' => 'nombre',
            'sector_code' => 'sector',
            'category_code' => 'categoría',
            'priority_code' => 'prioridad',
            'status_code' => 'estado',
            'pm_id' => 'PM',
        ];

        foreach ($requiredFields as $field => $label) {
            $value = $payload[$field] ?? null;
            $isEmpty = $value === null || $value === '' || (is_int($value) && $value <= 0);
            if ($isEmpty) {
                error_log('[clients.create] Validación bloqueó guardado | regla=required | campo=' . $field);
                throw new \InvalidArgumentException('El campo obligatorio "' . $label . '" no fue enviado correctamente.');
            }
        }

        error_log('[clients.create] Validación OK: campos obligatorios presentes.');
    }

    private function payloadDiagnostics(array $payload): array
    {
        return [
            'keys' => array_keys($payload),
            'name_present' => ($payload['name'] ?? '') !== '',
            'sector_code' => $payload['sector_code'] ?? null,
            'category_code' => $payload['category_code'] ?? null,
            'priority_code' => $payload['priority_code'] ?? null,
            'status_code' => $payload['status_code'] ?? null,
            'pm_id' => $payload['pm_id'] ?? null,
            'has_logo_path' => !empty($payload['logo_path']),
            'optional_present' => [
                'satisfaction' => array_key_exists('satisfaction', $payload) && $payload['satisfaction'] !== null,
                'nps' => array_key_exists('nps', $payload) && $payload['nps'] !== null,
                'risk_code' => !empty($payload['risk_code']),
                'tags' => !empty($payload['tags']),
                'area_code' => !empty($payload['area_code']),
                'feedback_notes' => !empty($payload['feedback_notes']),
                'feedback_history' => !empty($payload['feedback_history']),
                'operational_context' => !empty($payload['operational_context']),
            ],
        ];
    }

    private function isDebugEnabled(): bool
    {
        $flag = strtolower((string) (getenv('APP_DEBUG') ?: getenv('CLIENT_CREATE_DEBUG') ?: ''));
        return in_array($flag, ['1', 'true', 'on', 'yes'], true);
    }

    private function logDatabaseDiagnostics(): void
    {
        try {
            $dbName = $this->db->databaseName();
            $selectedDb = $this->db->fetchOne('SELECT DATABASE() AS db_name');
            $selectedDbName = $selectedDb['db_name'] ?? '(null)';
            $currentUser = $this->db->fetchOne('SELECT CURRENT_USER() AS current_user');
            $currentUserName = $currentUser['current_user'] ?? 'unknown';

            error_log('[clients.create] Diagnóstico conexión | DB configurada=' . $dbName . ' | DB activa=' . $selectedDbName . ' | usuario=' . $currentUserName);

            $insertPrivilege = $this->db->fetchOne(
                'SELECT PRIVILEGE_TYPE
                 FROM information_schema.SCHEMA_PRIVILEGES
                 WHERE TABLE_SCHEMA = :schema
                   AND GRANTEE = CONCAT("\"", SUBSTRING_INDEX(CURRENT_USER(), "@", 1), "\"@\"", SUBSTRING_INDEX(CURRENT_USER(), "@", -1), "\"")
                   AND PRIVILEGE_TYPE = "INSERT"
                LIMIT 1',
                [':schema' => $dbName]
            );

            $tableInsertPrivilege = $this->db->fetchOne(
                'SELECT PRIVILEGE_TYPE
                 FROM information_schema.TABLE_PRIVILEGES
                 WHERE TABLE_SCHEMA = :schema
                   AND TABLE_NAME = "clients"
                   AND GRANTEE = CONCAT("\"", SUBSTRING_INDEX(CURRENT_USER(), "@", 1), "\"@\"", SUBSTRING_INDEX(CURRENT_USER(), "@", -1), "\"")
                   AND PRIVILEGE_TYPE = "INSERT"
                 LIMIT 1',
                [':schema' => $dbName]
            );

            error_log('[clients.create] Diagnóstico permisos INSERT | schema=' . (($insertPrivilege['PRIVILEGE_TYPE'] ?? '') === 'INSERT' ? 'sí' : 'no o no verificable') . ' | table_clients=' . (($tableInsertPrivilege['PRIVILEGE_TYPE'] ?? '') === 'INSERT' ? 'sí' : 'no o no verificable'));
        } catch (\Throwable $e) {
            error_log('[clients.create] Diagnóstico conexión/permisos falló: ' . $e->getMessage());
        }
    }

    private function isAdmin(): bool
    {
        return $this->auth->canDeleteClients();
    }

}
