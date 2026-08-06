<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class OrganizationsRepository
{
    public const ORG_TYPES = ['cliente', 'proveedor', 'aliado', 'area_interna', 'equipo'];

    public function __construct(private Database $db)
    {
    }

    public function isAvailable(): bool
    {
        return $this->db->tableExists('organizations');
    }

    public static function normalizeName(string $name): string
    {
        $normalized = mb_strtolower(trim($name), 'UTF-8');
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return $normalized;
    }

    public function all(?bool $activeOnly = null): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $sql = 'SELECT * FROM organizations';
        $params = [];
        if ($activeOnly === true) {
            $sql .= ' WHERE active = 1';
        } elseif ($activeOnly === false) {
            $sql .= ' WHERE active = 0';
        }
        $sql .= ' ORDER BY name ASC, id ASC';

        return $this->db->fetchAll($sql, $params);
    }

    public function find(int $id): ?array
    {
        if (!$this->isAvailable() || $id <= 0) {
            return null;
        }

        return $this->db->fetchOne(
            'SELECT * FROM organizations WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    public function create(array $payload): int
    {
        $this->assertAvailable();
        $data = $this->sanitizePayload($payload);

        return $this->db->insert(
            'INSERT INTO organizations (
                name, name_normalized, legal_name, tax_identifier, org_type,
                description, active, created_at, updated_at
             ) VALUES (
                :name, :name_normalized, :legal_name, :tax_identifier, :org_type,
                :description, :active, NOW(), NOW()
             )',
            $data
        );
    }

    public function update(int $id, array $payload): void
    {
        $this->assertAvailable();
        if ($this->find($id) === null) {
            throw new InvalidArgumentException('Organización no encontrada.');
        }

        $data = $this->sanitizePayload($payload);
        $data[':id'] = $id;

        $this->db->execute(
            'UPDATE organizations SET
                name = :name,
                name_normalized = :name_normalized,
                legal_name = :legal_name,
                tax_identifier = :tax_identifier,
                org_type = :org_type,
                description = :description,
                active = :active,
                updated_at = NOW()
             WHERE id = :id',
            $data
        );
    }

    public function inactivate(int $id): void
    {
        $this->assertAvailable();
        if ($this->find($id) === null) {
            throw new InvalidArgumentException('Organización no encontrada.');
        }

        $this->db->execute(
            'UPDATE organizations SET active = 0, updated_at = NOW() WHERE id = :id',
            [':id' => $id]
        );
    }

    public function linkCounts(int $organizationId): array
    {
        $projectLinks = 0;
        $userLinks = 0;

        if ($this->db->tableExists('project_organizations')) {
            $row = $this->db->fetchOne(
                'SELECT COUNT(*) AS total FROM project_organizations WHERE organization_id = :id',
                [':id' => $organizationId]
            );
            $projectLinks = (int) ($row['total'] ?? 0);
        }

        if ($this->db->tableExists('user_organizations')) {
            $row = $this->db->fetchOne(
                'SELECT COUNT(*) AS total FROM user_organizations WHERE organization_id = :id',
                [':id' => $organizationId]
            );
            $userLinks = (int) ($row['total'] ?? 0);
        }

        return [
            'project_links' => $projectLinks,
            'user_links' => $userLinks,
            'has_links' => ($projectLinks + $userLinks) > 0,
        ];
    }

    public function forProject(int $projectId): array
    {
        if (!$this->db->tableExists('project_organizations') || $projectId <= 0) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT po.id AS link_id, po.project_id, po.organization_id, po.created_at AS linked_at,
                    o.name, o.name_normalized, o.legal_name, o.tax_identifier, o.org_type,
                    o.description, o.active
             FROM project_organizations po
             INNER JOIN organizations o ON o.id = po.organization_id
             WHERE po.project_id = :project_id
             ORDER BY o.name ASC',
            [':project_id' => $projectId]
        );
    }

    public function assignToProject(int $projectId, int $organizationId): int
    {
        $this->assertAvailable();
        if (!$this->db->tableExists('project_organizations')) {
            throw new RuntimeException('Tabla project_organizations no disponible.');
        }

        $org = $this->find($organizationId);
        if ($org === null || (int) ($org['active'] ?? 0) !== 1) {
            throw new InvalidArgumentException('Organización inválida o inactiva.');
        }

        $existing = $this->db->fetchOne(
            'SELECT id FROM project_organizations
             WHERE project_id = :project_id AND organization_id = :organization_id
             LIMIT 1',
            [
                ':project_id' => $projectId,
                ':organization_id' => $organizationId,
            ]
        );
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return $this->db->insert(
            'INSERT INTO project_organizations (project_id, organization_id, created_at, updated_at)
             VALUES (:project_id, :organization_id, NOW(), NOW())',
            [
                ':project_id' => $projectId,
                ':organization_id' => $organizationId,
            ]
        );
    }

    public function unassignFromProject(int $projectId, int $organizationId): bool
    {
        if (!$this->db->tableExists('project_organizations')) {
            return false;
        }

        $existing = $this->db->fetchOne(
            'SELECT id FROM project_organizations
             WHERE project_id = :project_id AND organization_id = :organization_id
             LIMIT 1',
            [
                ':project_id' => $projectId,
                ':organization_id' => $organizationId,
            ]
        );
        if ($existing === null) {
            return false;
        }

        $this->db->execute(
            'DELETE FROM project_organizations
             WHERE project_id = :project_id AND organization_id = :organization_id',
            [
                ':project_id' => $projectId,
                ':organization_id' => $organizationId,
            ]
        );

        return true;
    }

    public function forUser(int $userId): array
    {
        if (!$this->db->tableExists('user_organizations') || $userId <= 0) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT uo.id AS link_id, uo.user_id, uo.organization_id, uo.is_primary,
                    uo.created_at AS linked_at, o.name, o.org_type, o.active
             FROM user_organizations uo
             INNER JOIN organizations o ON o.id = uo.organization_id
             WHERE uo.user_id = :user_id
             ORDER BY uo.is_primary DESC, o.name ASC',
            [':user_id' => $userId]
        );
    }

    public function mapForUsers(array $userIds): array
    {
        if (!$this->db->tableExists('user_organizations') || $userIds === []) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $key = ':u' . $index;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        $rows = $this->db->fetchAll(
            'SELECT uo.user_id, uo.organization_id, uo.is_primary, o.name, o.org_type, o.active
             FROM user_organizations uo
             INNER JOIN organizations o ON o.id = uo.organization_id
             WHERE uo.user_id IN (' . implode(', ', $placeholders) . ')
             ORDER BY uo.is_primary DESC, o.name ASC',
            $params
        );

        $map = [];
        foreach ($rows as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            $map[$uid][] = $row;
        }

        return $map;
    }

    public function assignToUser(int $userId, int $organizationId, bool $isPrimary = false): int
    {
        $this->assertAvailable();
        if (!$this->db->tableExists('user_organizations')) {
            throw new RuntimeException('Tabla user_organizations no disponible.');
        }

        $org = $this->find($organizationId);
        if ($org === null || (int) ($org['active'] ?? 0) !== 1) {
            throw new InvalidArgumentException('Organización inválida o inactiva.');
        }

        $pdo = $this->db->connection();
        $pdo->beginTransaction();

        try {
            $existing = $this->db->fetchOne(
                'SELECT id, is_primary FROM user_organizations
                 WHERE user_id = :user_id AND organization_id = :organization_id
                 LIMIT 1',
                [
                    ':user_id' => $userId,
                    ':organization_id' => $organizationId,
                ]
            );

            if ($existing === null) {
                $linkId = $this->db->insert(
                    'INSERT INTO user_organizations (user_id, organization_id, is_primary, created_at, updated_at)
                     VALUES (:user_id, :organization_id, 0, NOW(), NOW())',
                    [
                        ':user_id' => $userId,
                        ':organization_id' => $organizationId,
                    ]
                );
            } else {
                $linkId = (int) $existing['id'];
            }

            if ($isPrimary) {
                $this->setPrimaryInTransaction($userId, $organizationId);
            }

            $pdo->commit();

            return $linkId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function setPrimaryForUser(int $userId, int $organizationId): void
    {
        $this->assertAvailable();
        if (!$this->db->tableExists('user_organizations')) {
            throw new RuntimeException('Tabla user_organizations no disponible.');
        }

        $pdo = $this->db->connection();
        $pdo->beginTransaction();

        try {
            $existing = $this->db->fetchOne(
                'SELECT id FROM user_organizations
                 WHERE user_id = :user_id AND organization_id = :organization_id
                 LIMIT 1',
                [
                    ':user_id' => $userId,
                    ':organization_id' => $organizationId,
                ]
            );
            if ($existing === null) {
                throw new InvalidArgumentException('El usuario no tiene asignada esa organización.');
            }

            $this->setPrimaryInTransaction($userId, $organizationId);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function unassignFromUser(int $userId, int $organizationId): bool
    {
        if (!$this->db->tableExists('user_organizations')) {
            return false;
        }

        $pdo = $this->db->connection();
        $pdo->beginTransaction();

        try {
            $existing = $this->db->fetchOne(
                'SELECT id, is_primary FROM user_organizations
                 WHERE user_id = :user_id AND organization_id = :organization_id
                 LIMIT 1',
                [
                    ':user_id' => $userId,
                    ':organization_id' => $organizationId,
                ]
            );
            if ($existing === null) {
                $pdo->rollBack();
                return false;
            }

            $this->db->execute(
                'DELETE FROM user_organizations
                 WHERE user_id = :user_id AND organization_id = :organization_id',
                [
                    ':user_id' => $userId,
                    ':organization_id' => $organizationId,
                ]
            );

            if ((int) ($existing['is_primary'] ?? 0) === 1) {
                $next = $this->db->fetchOne(
                    'SELECT organization_id FROM user_organizations
                     WHERE user_id = :user_id
                     ORDER BY id ASC
                     LIMIT 1',
                    [':user_id' => $userId]
                );
                if ($next !== null) {
                    $this->setPrimaryInTransaction($userId, (int) $next['organization_id']);
                }
            }

            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private function setPrimaryInTransaction(int $userId, int $organizationId): void
    {
        $this->db->execute(
            'UPDATE user_organizations
             SET is_primary = 0, updated_at = NOW()
             WHERE user_id = :user_id',
            [':user_id' => $userId]
        );

        $this->db->execute(
            'UPDATE user_organizations
             SET is_primary = 1, updated_at = NOW()
             WHERE user_id = :user_id AND organization_id = :organization_id',
            [
                ':user_id' => $userId,
                ':organization_id' => $organizationId,
            ]
        );
    }

    private function sanitizePayload(array $payload): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('El nombre de la organización es obligatorio.');
        }

        $orgType = trim((string) ($payload['org_type'] ?? ''));
        if (!in_array($orgType, self::ORG_TYPES, true)) {
            throw new InvalidArgumentException('Tipo de organización inválido.');
        }

        $legalName = trim((string) ($payload['legal_name'] ?? ''));
        $taxIdentifier = trim((string) ($payload['tax_identifier'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));

        return [
            ':name' => $name,
            ':name_normalized' => self::normalizeName($name),
            ':legal_name' => $legalName !== '' ? $legalName : null,
            ':tax_identifier' => $taxIdentifier !== '' ? $taxIdentifier : null,
            ':org_type' => $orgType,
            ':description' => $description !== '' ? $description : null,
            ':active' => isset($payload['active']) ? ((int) ((bool) $payload['active'])) : 1,
        ];
    }

    private function assertAvailable(): void
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('El módulo de organizaciones no está disponible. Aplique la migración de Etapa B.');
        }
    }
}
