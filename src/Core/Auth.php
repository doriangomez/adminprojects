<?php

declare(strict_types=1);

class Auth
{
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

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'role' => $user['rol_nombre'],
            'role_id' => (int) $user['role_id'],
        ];

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
        if (!$user || !$this->db->tableExists('project_talent_assignments')) {
            return false;
        }

        $row = $this->db->fetchOne(
            "SELECT 1 FROM project_talent_assignments
             WHERE user_id = :user
             AND requires_timesheet = 1
             AND (assignment_status = 'active' OR (assignment_status IS NULL AND active = 1))
             LIMIT 1",
            [':user' => (int) $user['id']]
        );

        return $row !== null;
    }
}
