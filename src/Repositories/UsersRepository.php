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

    public function create(array $payload): int
    {
        return $this->db->insert(
            'INSERT INTO users (name, email, password_hash, role_id, active, created_at, updated_at)
             VALUES (:name, :email, :password_hash, :role_id, :active, NOW(), NOW())',
            [
                ':name' => $payload['name'],
                ':email' => $payload['email'],
                ':password_hash' => $payload['password_hash'],
                ':role_id' => $payload['role_id'],
                ':active' => $payload['active'] ?? 1,
            ]
        );
    }

    public function update(int $id, array $payload): void
    {
        $fields = [
            'name' => ':name',
            'email' => ':email',
            'role_id' => ':role_id',
            'active' => ':active',
        ];

        $params = [
            ':name' => $payload['name'],
            ':email' => $payload['email'],
            ':role_id' => $payload['role_id'],
            ':active' => $payload['active'] ?? 1,
            ':id' => $id,
        ];

        $setParts = ['name = :name', 'email = :email', 'role_id = :role_id', 'active = :active'];

        if (!empty($payload['password_hash'])) {
            $setParts[] = 'password_hash = :password_hash';
            $params[':password_hash'] = $payload['password_hash'];
        }

        $sql = 'UPDATE users SET ' . implode(', ', $setParts) . ', updated_at = NOW() WHERE id = :id';
        $this->db->execute($sql, $params);
    }

    public function deactivate(int $id): void
    {
        $this->db->execute('UPDATE users SET active = 0, updated_at = NOW() WHERE id = :id', [':id' => $id]);
    }
}
