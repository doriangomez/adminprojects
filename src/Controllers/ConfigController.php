<?php

declare(strict_types=1);

class ConfigController extends Controller
{
    public function index(): void
    {
        $this->ensureConfigAccess();

        $service = new ConfigService($this->db);
        $config = $service->getConfig();

        $usersRepo = new UsersRepository($this->db);
        $rolesRepo = new RolesRepository($this->db);
        $permissionsRepo = new PermissionsRepository($this->db);
        $masterRepo = new MasterFilesRepository($this->db);
        $riskRepo = new RiskCatalogRepository($this->db);

        $roles = $rolesRepo->all();
        $rolesWithPermissions = array_map(
            fn ($role) => array_merge(
                $role,
                ['permissions' => $rolesRepo->permissionsByRole((int) $role['id'])]
            ),
            $roles
        );

        $masterData = [];
        foreach ($masterRepo->allowedTables() as $table) {
            $masterData[$table] = $masterRepo->list($table);
        }

        try {
            $riskCatalog = $riskRepo->listAll();
        } catch (\Throwable $e) {
            error_log('No se pudo cargar el catálogo de riesgos: ' . $e->getMessage());
            $riskCatalog = [];
        }
        $risksByCategory = [];
        foreach ($riskCatalog as $risk) {
            $category = $risk['category'] ?? 'Otros';
            $risksByCategory[$category][] = $risk;
        }

        $this->render('config/index', [
            'title' => 'Configuración',
            'configData' => $config,
            'activeTheme' => (new ThemeRepository($this->db))->getActiveTheme(),
            'roles' => $rolesWithPermissions,
            'permissions' => $permissionsRepo->all(),
            'users' => $usersRepo->all(),
            'masterData' => $masterData,
            'riskCatalog' => $riskCatalog,
            'risksByCategory' => $risksByCategory,
            'savedMessage' => !empty($_GET['saved']) ? 'Preferencias actualizadas y aplicadas en la interfaz.' : null,
            'isAdmin' => $this->auth->hasRole('Administrador'),
        ]);
    }

    public function updateTheme(): void
    {
        $this->ensureConfigAccess();

        $configService = new ConfigService($this->db);
        $current = $configService->getConfig();
        $themeDefaults = $configService->getDefaults()['theme'] ?? [];
        $logoFromUpload = $configService->storeLogo($_FILES['logo_file'] ?? null);
        $logoUrl = trim($_POST['logo'] ?? '');
        $logoValue = $logoFromUpload ?: $logoUrl;
        $textSoft = $_POST['text_soft']
            ?? $_POST['text_disabled']
            ?? ($themeDefaults['text_soft'] ?? $themeDefaults['text_disabled'] ?? '#94a3b8');

        $payload = [
            'theme' => [
                'logo' => $logoValue,
                'primary' => $_POST['primary'] ?? ($themeDefaults['primary'] ?? '#2563eb'),
                'secondary' => $_POST['secondary'] ?? ($themeDefaults['secondary'] ?? '#0f172a'),
                'accent' => $_POST['accent'] ?? ($themeDefaults['accent'] ?? '#f97316'),
                'background' => $_POST['background'] ?? ($themeDefaults['background'] ?? '#0b1224'),
                'surface' => $_POST['surface'] ?? ($themeDefaults['surface'] ?? '#0f172a'),
                'text_main' => $_POST['text_main'] ?? ($themeDefaults['text_main'] ?? '#0f172a'),
                'text_muted' => $_POST['text_muted'] ?? ($themeDefaults['text_muted'] ?? '#475569'),
                'text_soft' => $textSoft,
                'text_disabled' => $textSoft,
                'font_family' => trim($_POST['font_family'] ?? ($themeDefaults['font_family'] ?? "'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif")),
                'login_hero' => trim($_POST['login_hero'] ?? ''),
                'login_message' => trim($_POST['login_message'] ?? ''),
            ],
            'master_files' => [
                'data_file' => trim($_POST['data_file'] ?? 'data/data.json'),
                'schema_file' => trim($_POST['schema_file'] ?? 'data/schema.sql'),
            ],
            'delivery' => [
                'methodologies' => $this->parseList($_POST['methodologies'] ?? implode(', ', $current['delivery']['methodologies'] ?? [])),
                'phases' => $this->decodeJson($_POST['phases_json'] ?? '', $current['delivery']['phases'] ?? []),
                'risks' => $current['delivery']['risks'] ?? [],
            ],
            'access' => [
                'roles' => $this->parseList($_POST['roles'] ?? ''),
                'user_management' => [
                    'allow_self_registration' => isset($_POST['allow_self_registration']),
                    'require_approval' => isset($_POST['require_approval']),
                ],
            ],
            'document_flow' => [
                'default' => [
                    'reviewer_roles' => $this->parseList($_POST['document_reviewer_roles'] ?? implode(', ', $current['document_flow']['default']['reviewer_roles'] ?? [])),
                    'validator_roles' => $this->parseList($_POST['document_validator_roles'] ?? implode(', ', $current['document_flow']['default']['validator_roles'] ?? [])),
                    'approver_roles' => $this->parseList($_POST['document_approver_roles'] ?? implode(', ', $current['document_flow']['default']['approver_roles'] ?? [])),
                ],
                'phases' => $current['document_flow']['phases'] ?? [],
                'expected_docs' => $this->decodeJson($_POST['document_expected_docs_json'] ?? '', $current['document_flow']['expected_docs'] ?? []),
                'tag_options' => $this->parseList($_POST['document_tag_options'] ?? implode(', ', $current['document_flow']['tag_options'] ?? [])),
            ],
            'operational_rules' => [
                'semaforization' => [
                    'progress' => [
                        'yellow_below' => $this->toFloat($_POST['progress_yellow'] ?? '50'),
                        'red_below' => $this->toFloat($_POST['progress_red'] ?? '25'),
                    ],
                    'hours' => [
                        'yellow_above' => $this->toFloat($_POST['hours_yellow'] ?? '0.05'),
                        'red_above' => $this->toFloat($_POST['hours_red'] ?? '0.10'),
                    ],
                    'cost' => [
                        'yellow_above' => $this->toFloat($_POST['cost_yellow'] ?? '0.05'),
                        'red_above' => $this->toFloat($_POST['cost_red'] ?? '0.10'),
                    ],
                ],
                'approvals' => [
                    'external_talent_requires_approval' => isset($_POST['external_talent_requires_approval']),
                    'budget_change_requires_approval' => isset($_POST['budget_change_requires_approval']),
                ],
                'timesheets' => [
                    'enabled' => isset($_POST['timesheets_enabled']),
                ],
            ],
        ];

        $configService->updateConfig($payload);

        header('Location: /project/public/config?saved=1');
    }

    public function storeUser(): void
    {
        $this->ensureConfigAccess();

        $repo = new UsersRepository($this->db);
        $password = $_POST['password'] ?? '';
        $isAdmin = $this->auth->hasRole('Administrador');
        $documentRoles = $this->documentRolePayload($isAdmin);
        $progressPermission = $this->progressPermissionPayload($isAdmin);
        $outsourcingPermission = $this->outsourcingPermissionPayload($isAdmin);
        $repo->create([
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'role_id' => (int) $_POST['role_id'],
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'active' => isset($_POST['active']) ? 1 : 0,
            'can_review_documents' => $documentRoles['can_review_documents'],
            'can_validate_documents' => $documentRoles['can_validate_documents'],
            'can_approve_documents' => $documentRoles['can_approve_documents'],
            'can_update_project_progress' => $progressPermission,
            'can_access_outsourcing' => $outsourcingPermission,
        ]);

        header('Location: /project/public/config?saved=1');
    }

    public function updateUser(): void
    {
        $this->ensureConfigAccess();

        $repo = new UsersRepository($this->db);
        $password = $_POST['password'] ?? '';
        $isAdmin = $this->auth->hasRole('Administrador');
        $userId = (int) $_POST['id'];
        $current = $repo->find($userId) ?? [];
        $documentRoles = $this->documentRolePayload($isAdmin, $current);
        $progressPermission = $this->progressPermissionPayload($isAdmin, $current);
        $outsourcingPermission = $this->outsourcingPermissionPayload($isAdmin, $current);
        $repo->update($userId, [
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'role_id' => (int) $_POST['role_id'],
            'password_hash' => $password ? password_hash($password, PASSWORD_BCRYPT) : null,
            'active' => isset($_POST['active']) ? 1 : 0,
            'can_review_documents' => $documentRoles['can_review_documents'],
            'can_validate_documents' => $documentRoles['can_validate_documents'],
            'can_approve_documents' => $documentRoles['can_approve_documents'],
            'can_update_project_progress' => $progressPermission,
            'can_access_outsourcing' => $outsourcingPermission,
        ]);

        header('Location: /project/public/config?saved=1');
    }

    public function deactivateUser(): void
    {
        $this->ensureConfigAccess();

        $repo = new UsersRepository($this->db);
        $repo->deactivate((int) $_POST['id']);

        header('Location: /project/public/config?saved=1');
    }

    public function storeRole(): void
    {
        $this->ensureConfigAccess();

        $repo = new RolesRepository($this->db);
        $roleId = $repo->create([
            'nombre' => $_POST['nombre'],
            'descripcion' => $_POST['descripcion'] ?? null,
        ]);

        $permissionIds = array_map('intval', $_POST['permissions'] ?? []);
        $repo->syncPermissions($roleId, $permissionIds);

        header('Location: /project/public/config?saved=1');
    }

    public function updateRole(): void
    {
        $this->ensureConfigAccess();

        $repo = new RolesRepository($this->db);
        $roleId = (int) $_POST['id'];
        $repo->update($roleId, [
            'nombre' => $_POST['nombre'],
            'descripcion' => $_POST['descripcion'] ?? null,
        ]);

        $permissionIds = array_map('intval', $_POST['permissions'] ?? []);
        $repo->syncPermissions($roleId, $permissionIds);

        header('Location: /project/public/config?saved=1');
    }

    public function manageMasterFile(string $action): void
    {
        $this->ensureConfigAccess();

        $table = $_POST['table'] ?? '';
        $repo = new MasterFilesRepository($this->db);

        if ($action === 'create') {
            $repo->create($table, [
                'code' => $_POST['code'],
                'label' => $_POST['label'],
            ]);
        }

        if ($action === 'update') {
            $repo->update($table, (int) $_POST['id'], [
                'code' => $_POST['code'],
                'label' => $_POST['label'],
            ]);
        }

        if ($action === 'delete') {
            $repo->delete($table, (int) $_POST['id']);
        }

        header('Location: /project/public/config?saved=1');
    }

    public function storeRisk(): void
    {
        $this->ensureConfigAccess();

        $repo = new RiskCatalogRepository($this->db);
        $code = trim($_POST['code'] ?? '');
        if ($code === '') {
            http_response_code(400);
            exit('Código de riesgo obligatorio.');
        }
        $payload = $this->riskPayload();
        $repo->create(array_merge(['code' => $code], $payload));

        header('Location: /project/public/config?saved=1');
    }

    public function updateRisk(): void
    {
        $this->ensureConfigAccess();

        $code = trim($_POST['code'] ?? '');
        if ($code === '') {
            http_response_code(400);
            exit('Código de riesgo obligatorio.');
        }

        $repo = new RiskCatalogRepository($this->db);
        $repo->update($code, $this->riskPayload());

        header('Location: /project/public/config?saved=1');
    }

    public function deleteRisk(): void
    {
        $this->ensureConfigAccess();

        $code = trim($_POST['code'] ?? '');
        if ($code !== '') {
            $repo = new RiskCatalogRepository($this->db);
            $repo->delete($code);
        }

        header('Location: /project/public/config?saved=1');
    }

    private function parseList(string $value): array
    {
        $parts = array_map('trim', explode(',', $value));
        $parts = array_filter($parts, fn ($part) => $part !== '');

        return array_values($parts);
    }

    private function decodeJson(string $value, array $default = []): array
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $default;
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : $default;
    }

    private function documentRolePayload(bool $isAdmin, array $existing = []): array
    {
        if (!$isAdmin) {
            return [
                'can_review_documents' => (int) ($existing['can_review_documents'] ?? 0),
                'can_validate_documents' => (int) ($existing['can_validate_documents'] ?? 0),
                'can_approve_documents' => (int) ($existing['can_approve_documents'] ?? 0),
            ];
        }

        return [
            'can_review_documents' => $this->checkboxValue(['can_review_documents', 'can_reviewer']) ? 1 : 0,
            'can_validate_documents' => $this->checkboxValue(['can_validate_documents', 'can_validator']) ? 1 : 0,
            'can_approve_documents' => $this->checkboxValue(['can_approve_documents', 'can_approver']) ? 1 : 0,
        ];
    }

    private function progressPermissionPayload(bool $isAdmin, array $existing = []): int
    {
        if (!$isAdmin) {
            return (int) ($existing['can_update_project_progress'] ?? 0);
        }

        return $this->checkboxValue(['can_update_project_progress']) ? 1 : 0;
    }

    private function outsourcingPermissionPayload(bool $isAdmin, array $existing = []): int
    {
        if (!$isAdmin) {
            return (int) ($existing['can_access_outsourcing'] ?? 0);
        }

        return $this->checkboxValue(['can_access_outsourcing']) ? 1 : 0;
    }

    private function checkboxValue(array $keys): bool
    {
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                return true;
            }
        }

        return false;
    }

    private function ensureConfigAccess(): void
    {
        if (!$this->auth->can('config.manage')) {
            $user = $this->auth->user();
            $allowedRoles = ['Administrador', 'PMO'];
            if (!$user || !in_array($user['role'] ?? '', $allowedRoles, true)) {
                http_response_code(403);
                exit('Acceso denegado');
            }
        }
    }

    private function toFloat(string $value): float
    {
        return (float) str_replace(',', '.', $value);
    }

    private function riskPayload(): array
    {
        $severity = (int) ($_POST['severity_base'] ?? 1);
        if ($severity < 1 || $severity > 5) {
            $severity = 1;
        }

        $appliesTo = $_POST['applies_to'] ?? 'ambos';
        $validApplies = ['convencional', 'scrum', 'ambos'];
        if (!in_array($appliesTo, $validApplies, true)) {
            $appliesTo = 'ambos';
        }

        return [
            'category' => trim($_POST['category'] ?? ''),
            'label' => trim($_POST['label'] ?? ''),
            'applies_to' => $appliesTo,
            'impact_scope' => isset($_POST['impact_scope']) ? 1 : 0,
            'impact_time' => isset($_POST['impact_time']) ? 1 : 0,
            'impact_cost' => isset($_POST['impact_cost']) ? 1 : 0,
            'impact_quality' => isset($_POST['impact_quality']) ? 1 : 0,
            'impact_legal' => isset($_POST['impact_legal']) ? 1 : 0,
            'severity_base' => $severity,
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
    }
}
