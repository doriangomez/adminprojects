<?php

declare(strict_types=1);

class UsersRepository
{
    public function __construct(private Database $db)
    {
    }

    public function all(): array
    {
        return $this->db->fetchAll('SELECT u.*, r.nombre AS role_name FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.created_at DESC');
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM users WHERE id = :id', [':id' => $id]);
    }

    public function isValidProjectManager(int $id): bool
    {
        $stmt = $this->db->connection()->prepare(
            "SELECT COUNT(*) FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id AND u.active = 1 AND r.nombre IN ('Administrador', 'PMO', 'Líder de Proyecto')"
        );

        $stmt->execute([':id' => $id]);

        return (bool) $stmt->fetchColumn();
    }

    public function firstAvailablePmId(): ?int
    {
        $stmt = $this->db->connection()->prepare(
            "SELECT u.id FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.active = 1 AND r.nombre IN ('Administrador', 'PMO', 'Líder de Proyecto')
             ORDER BY u.id ASC
             LIMIT 1"
        );

        $stmt->execute();
        $user = $stmt->fetch();

        return $user ? (int) ($user['id'] ?? 0) : null;
    }

    public function create(array $payload): int
    {
        return $this->db->insert(
            'INSERT INTO users (name, email, password_hash, auth_type, role_id, active, can_review_documents, can_validate_documents, can_approve_documents, can_update_project_progress, can_access_outsourcing, can_access_timesheets, can_approve_timesheets, created_at, updated_at)
             VALUES (:name, :email, :password_hash, :auth_type, :role_id, :active, :can_review_documents, :can_validate_documents, :can_approve_documents, :can_update_project_progress, :can_access_outsourcing, :can_access_timesheets, :can_approve_timesheets, NOW(), NOW())',
            [
                ':name' => $payload['name'],
                ':email' => $payload['email'],
                ':password_hash' => $payload['password_hash'],
                ':auth_type' => $payload['auth_type'] ?? 'manual',
                ':role_id' => $payload['role_id'],
                ':active' => $payload['active'] ?? 1,
                ':can_review_documents' => $payload['can_review_documents'] ?? 0,
                ':can_validate_documents' => $payload['can_validate_documents'] ?? 0,
                ':can_approve_documents' => $payload['can_approve_documents'] ?? 0,
                ':can_update_project_progress' => $payload['can_update_project_progress'] ?? 0,
                ':can_access_outsourcing' => $payload['can_access_outsourcing'] ?? 0,
                ':can_access_timesheets' => $payload['can_access_timesheets'] ?? 0,
                ':can_approve_timesheets' => $payload['can_approve_timesheets'] ?? 0,
            ]
        );
    }

    public function update(int $id, array $payload): void
    {
        $params = [
            ':name' => $payload['name'],
            ':email' => $payload['email'],
            ':role_id' => $payload['role_id'],
            ':auth_type' => $payload['auth_type'] ?? 'manual',
            ':active' => $payload['active'] ?? 1,
            ':can_review_documents' => $payload['can_review_documents'] ?? 0,
            ':can_validate_documents' => $payload['can_validate_documents'] ?? 0,
            ':can_approve_documents' => $payload['can_approve_documents'] ?? 0,
            ':can_update_project_progress' => $payload['can_update_project_progress'] ?? 0,
            ':can_access_outsourcing' => $payload['can_access_outsourcing'] ?? 0,
            ':can_access_timesheets' => $payload['can_access_timesheets'] ?? 0,
            ':can_approve_timesheets' => $payload['can_approve_timesheets'] ?? 0,
            ':id' => $id,
        ];

        $setParts = [
            'name = :name',
            'email = :email',
            'role_id = :role_id',
            'auth_type = :auth_type',
            'active = :active',
            'can_review_documents = :can_review_documents',
            'can_validate_documents = :can_validate_documents',
            'can_approve_documents = :can_approve_documents',
            'can_update_project_progress = :can_update_project_progress',
            'can_access_outsourcing = :can_access_outsourcing',
            'can_access_timesheets = :can_access_timesheets',
            'can_approve_timesheets = :can_approve_timesheets',
        ];

        if (!empty($payload['password_hash'])) {
            $setParts[] = 'password_hash = :password_hash';
            $params[':password_hash'] = $payload['password_hash'];
        }

        $sql = 'UPDATE users SET ' . implode(', ', $setParts) . ', updated_at = NOW() WHERE id = :id';
        $this->db->execute($sql, $params);
    }


    public function findGoogleEligibleByEmail(string $email): ?array
    {
        return $this->db->fetchOne(
            'SELECT u.*, r.nombre AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = :email AND u.active = 1 AND u.auth_type = :auth_type
             LIMIT 1',
            [
                ':email' => strtolower(trim($email)),
                ':auth_type' => 'google',
            ]
        );
    }

    public function deactivate(int $id): void
    {
        $this->db->execute('UPDATE users SET active = 0, updated_at = NOW() WHERE id = :id', [':id' => $id]);
    }

    public function byDocumentRole(string $role): array
    {
        $map = [
            'reviewer' => 'can_review_documents',
            'validator' => 'can_validate_documents',
            'approver' => 'can_approve_documents',
        ];

        if (!isset($map[$role])) {
            return [];
        }

        $column = $map[$role];

        return $this->db->fetchAll(
            "SELECT id, name FROM users WHERE active = 1 AND {$column} = 1 ORDER BY name ASC"
        );
    }

    public function findByRoleNames(array $roles): array
    {
        $roles = array_values(array_filter(array_map('trim', $roles)));
        if (empty($roles)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($roles), '?'));

        return $this->db->fetchAll(
            "SELECT u.id, u.name, u.email
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.active = 1 AND r.nombre IN ({$placeholders})",
            $roles
        );
    }
}
