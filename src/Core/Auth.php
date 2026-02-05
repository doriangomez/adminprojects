<?php

declare(strict_types=1);

class Auth
{
    private ?array $configCache = null;

    public function __construct(private Database $db)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->db->fetchOne('SELECT u.*, r.nombre AS rol_nombre FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = :email AND u.active = 1', [
            ':email' => $email,
        ]);

        if (!$user || ($user['auth_type'] ?? 'manual') !== 'manual') {
            return false;
        }

        if (!password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            return false;
        }

        $this->hydrateSession($user);

        return true;
    }

    public function attemptGoogle(string $email): bool
    {
        $config = $this->googleWorkspaceConfig();
        if (!(bool) ($config['enabled'] ?? false)) {
            return false;
        }

        $domain = strtolower(trim((string) ($config['corporate_domain'] ?? '')));
        $normalizedEmail = strtolower(trim($email));
        if ($normalizedEmail === '' || $domain === '' || !str_ends_with($normalizedEmail, '@' . $domain)) {
            return false;
        }

        $user = $this->db->fetchOne(
            'SELECT u.*, r.nombre AS rol_nombre
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = :email AND u.active = 1 AND u.auth_type = :auth_type',
            [
                ':email' => $normalizedEmail,
                ':auth_type' => 'google',
            ]
        );

        if (!$user) {
            return false;
        }

        $this->hydrateSession($user);

        return true;
    }

    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public function isImpersonating(): bool
    {
        return !empty($_SESSION['impersonator']) && !empty($_SESSION['impersonation']);
    }

    public function impersonator(): ?array
    {
        return $_SESSION['impersonator'] ?? null;
    }

    public function startImpersonation(int $userId): bool
    {
        $actor = $_SESSION['impersonator'] ?? $this->user();
        if (!$actor || strcasecmp((string) ($actor['role'] ?? ''), 'Administrador') !== 0) {
            return false;
        }

        $target = $this->db->fetchOne(
            'SELECT u.*, r.nombre AS rol_nombre FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id AND u.active = 1',
            [':id' => $userId]
        );

        if (!$target) {
            return false;
        }

        if (empty($_SESSION['impersonator'])) {
            $_SESSION['impersonator'] = $this->user();
        }

        $this->hydrateSession($target);

        $_SESSION['impersonation'] = [
            'user_id' => (int) $target['id'],
            'user_name' => $target['name'],
            'started_at' => date('c'),
        ];

        return true;
    }

    public function stopImpersonation(): void
    {
        if (!empty($_SESSION['impersonator'])) {
            $_SESSION['user'] = $_SESSION['impersonator'];
        }

        unset($_SESSION['impersonator'], $_SESSION['impersonation']);
    }

    public function can(string $permission): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        $result = $this->db->fetchOne(
            'SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = :roleId AND p.code = :code',
            [
                ':roleId' => $user['role_id'],
                ':code' => $permission,
            ]
        );

        return $result !== null;
    }

    public function canAccessOutsourcing(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        $row = $this->db->fetchOne(
            'SELECT can_access_outsourcing FROM users WHERE id = :id LIMIT 1',
            [':id' => (int) $user['id']]
        );

        return ((int) ($row['can_access_outsourcing'] ?? 0)) === 1;
    }

    public function hasRole(string $role): bool
    {
        $user = $this->user();

        if (!$user || empty($user['role'])) {
            return false;
        }

        return strcasecmp((string) $user['role'], $role) === 0;
    }

    public function canDeleteClients(): bool
    {
        if ($this->can('clients.delete')) {
            return true;
        }

        if ($this->can('config.manage')) {
            return true;
        }

        return $this->hasRole('Administrador');
    }

    public function hasTimesheetAssignments(): bool
    {
        $user = $this->user();
        if (!$user || !$this->db->tableExists('talents')) {
            return false;
        }

        $row = $this->db->fetchOne(
            "SELECT requiere_reporte_horas
             FROM talents
             WHERE user_id = :user
             LIMIT 1",
            [':user' => (int) $user['id']]
        );

        return ((int) ($row['requiere_reporte_horas'] ?? 0)) === 1;
    }

    public function isTalentUser(): bool
    {
        return $this->hasRole('Talento');
    }

    public function canAccessTimesheets(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        if (!$this->timesheetsEnabled()) {
            return false;
        }

        if ($this->can('view_timesheet') || $this->can('timesheets.view')) {
            return true;
        }

        $row = $this->db->fetchOne(
            'SELECT can_access_timesheets FROM users WHERE id = :id LIMIT 1',
            [':id' => (int) $user['id']]
        );

        return ((int) ($row['can_access_timesheets'] ?? 0)) === 1;
    }

    public function canApproveTimesheets(): bool
    {
        if (!$this->timesheetsEnabled()) {
            return false;
        }

        if ($this->can('approve_timesheet') || $this->can('timesheets.approve')) {
            return true;
        }

        $user = $this->user();
        if (!$user) {
            return false;
        }

        $row = $this->db->fetchOne(
            'SELECT can_approve_timesheets FROM users WHERE id = :id LIMIT 1',
            [':id' => (int) $user['id']]
        );

        return ((int) ($row['can_approve_timesheets'] ?? 0)) === 1;
    }

    public function isTimesheetsEnabled(): bool
    {
        return $this->timesheetsEnabled();
    }

    private function hydrateSession(array $user): void
    {
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'] ?? null,
            'role' => $user['rol_nombre'],
            'role_id' => (int) $user['role_id'],
            'auth_type' => $user['auth_type'] ?? 'manual',
        ];
    }

    private function googleWorkspaceConfig(): array
    {
        $config = $this->loadConfig();

        return $config['access']['google_workspace'] ?? [];
    }

    private function timesheetsEnabled(): bool
    {
        $config = $this->loadConfig();
        return (bool) ($config['operational_rules']['timesheets']['enabled'] ?? false);
    }

    private function loadConfig(): array
    {
        if ($this->configCache === null) {
            $this->configCache = (new ConfigService($this->db))->getConfig();
        }

        return $this->configCache;
    }
}
