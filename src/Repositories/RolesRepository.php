<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;

class RolesRepository
{
    public function __construct(private Database $db)
    {
    }

    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM roles ORDER BY nombre ASC');
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM roles WHERE id = :id', [':id' => $id]);
    }

    public function findByName(string $name): ?array
    {
        return $this->db->fetchOne('SELECT * FROM roles WHERE nombre = :name LIMIT 1', [':name' => $name]) ?: null;
    }

    public function create(array $payload): int
    {
        return $this->db->insert(
            'INSERT INTO roles (nombre, descripcion, created_at, updated_at) VALUES (:nombre, :descripcion, NOW(), NOW())',
            [
                ':nombre' => $payload['nombre'],
                ':descripcion' => $payload['descripcion'] ?? null,
            ]
        );
    }

    public function update(int $id, array $payload): void
    {
        $this->db->execute(
            'UPDATE roles SET nombre = :nombre, descripcion = :descripcion, updated_at = NOW() WHERE id = :id',
            [
                ':nombre' => $payload['nombre'],
                ':descripcion' => $payload['descripcion'] ?? null,
                ':id' => $id,
            ]
        );
    }

    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        $this->db->execute('DELETE FROM role_permissions WHERE role_id = :roleId', [':roleId' => $roleId]);
        foreach ($permissionIds as $permissionId) {
            $this->db->insert(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (:roleId, :permissionId)',
                [
                    ':roleId' => $roleId,
                    ':permissionId' => (int) $permissionId,
                ]
            );
        }
    }

    public function permissionsByRole(int $roleId): array
    {
        return $this->db->fetchAll(
            'SELECT p.* FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id WHERE rp.role_id = :roleId ORDER BY p.name',
            [':roleId' => $roleId]
        );
    }
}
