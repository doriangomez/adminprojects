<?php

declare(strict_types=1);

class ConfigController extends Controller
{
    public function index(): void
    {
        $this->ensureConfigAccess();

        $service = new ConfigService();
        $config = $service->getConfig();

        $usersRepo = new UsersRepository($this->db);
        $rolesRepo = new RolesRepository($this->db);
        $permissionsRepo = new PermissionsRepository($this->db);
        $masterRepo = new MasterFilesRepository($this->db);

        $roles = $rolesRepo->all();
        $rolesWithPermissions = array_map(
            fn ($role) => [
                ...$role,
                'permissions' => $rolesRepo->permissionsByRole((int) $role['id']),
            ],
            $roles
        );

        $masterData = [];
        foreach ($masterRepo->allowedTables() as $table) {
            $masterData[$table] = $masterRepo->list($table);
        }

        $this->render('config/index', [
            'title' => 'Configuración',
            'configData' => $config,
            'roles' => $rolesWithPermissions,
            'permissions' => $permissionsRepo->all(),
            'users' => $usersRepo->all(),
            'masterData' => $masterData,
            'savedMessage' => !empty($_GET['saved']) ? 'Preferencias actualizadas y aplicadas en la interfaz.' : null,
        ]);
    }

    public function updateTheme(): void
    {
        $this->ensureConfigAccess();

        $configService = new ConfigService();
        $logoFromUpload = $configService->storeLogo($_FILES['logo_file'] ?? null);
        $logoUrl = trim($_POST['logo'] ?? '');
        $logoValue = $logoFromUpload ?: $logoUrl;

        $payload = [
            'theme' => [
                'logo' => $logoValue,
                'primary' => $_POST['primary'] ?? '#2563eb',
                'secondary' => $_POST['secondary'] ?? '#0f172a',
                'accent' => $_POST['accent'] ?? '#f97316',
                'background' => $_POST['background'] ?? '#0b1224',
                'surface' => $_POST['surface'] ?? '#0f172a',
                'font_family' => trim($_POST['font_family'] ?? "'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"),
                'login_hero' => trim($_POST['login_hero'] ?? ''),
                'login_message' => trim($_POST['login_message'] ?? ''),
            ],
            'master_files' => [
                'data_file' => trim($_POST['data_file'] ?? 'data/data.json'),
                'schema_file' => trim($_POST['schema_file'] ?? 'data/schema.sql'),
            ],
            'access' => [
                'roles' => $this->parseList($_POST['roles'] ?? ''),
                'user_management' => [
                    'allow_self_registration' => isset($_POST['allow_self_registration']),
                    'require_approval' => isset($_POST['require_approval']),
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
        $repo->create([
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'role_id' => (int) $_POST['role_id'],
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'active' => isset($_POST['active']) ? 1 : 0,
        ]);

        header('Location: /project/public/config?saved=1');
    }

    public function updateUser(): void
    {
        $this->ensureConfigAccess();

        $repo = new UsersRepository($this->db);
        $password = $_POST['password'] ?? '';
        $repo->update((int) $_POST['id'], [
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'role_id' => (int) $_POST['role_id'],
            'password_hash' => $password ? password_hash($password, PASSWORD_BCRYPT) : null,
            'active' => isset($_POST['active']) ? 1 : 0,
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

    private function parseList(string $value): array
    {
        $parts = array_map('trim', explode(',', $value));
        $parts = array_filter($parts, fn ($part) => $part !== '');

        return array_values($parts);
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
}
