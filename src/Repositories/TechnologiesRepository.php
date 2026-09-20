<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;
use InvalidArgumentException;
use RuntimeException;

class TechnologiesRepository
{
    public function __construct(private Database $db)
    {
    }

    public function isAvailable(): bool
    {
        return $this->db->tableExists('technologies');
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

        $sql = 'SELECT * FROM technologies';
        if ($activeOnly === true) {
            $sql .= ' WHERE active = 1';
        } elseif ($activeOnly === false) {
            $sql .= ' WHERE active = 0';
        }
        $sql .= ' ORDER BY category ASC, name ASC, id ASC';

        return $this->db->fetchAll($sql);
    }

    public function find(int $id): ?array
    {
        if (!$this->isAvailable() || $id <= 0) {
            return null;
        }

        return $this->db->fetchOne(
            'SELECT * FROM technologies WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    public function create(array $payload): int
    {
        $this->assertAvailable();
        $data = $this->sanitizePayload($payload);
        $this->assertUniqueNameCategory($data[':name_normalized'], $data[':category']);

        return $this->db->insert(
            'INSERT INTO technologies (
                name, name_normalized, category, description, active, created_at, updated_at
             ) VALUES (
                :name, :name_normalized, :category, :description, :active, NOW(), NOW()
             )',
            $data
        );
    }

    public function update(int $id, array $payload): void
    {
        $this->assertAvailable();
        if ($this->find($id) === null) {
            throw new InvalidArgumentException('Tecnología no encontrada.');
        }

        $data = $this->sanitizePayload($payload);
        $this->assertUniqueNameCategory($data[':name_normalized'], $data[':category'], $id);
        $data[':id'] = $id;

        $this->db->execute(
            'UPDATE technologies SET
                name = :name,
                name_normalized = :name_normalized,
                category = :category,
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
            throw new InvalidArgumentException('Tecnología no encontrada.');
        }

        $this->db->execute(
            'UPDATE technologies SET active = 0, updated_at = NOW() WHERE id = :id',
            [':id' => $id]
        );
    }

    public function linkCounts(int $technologyId): array
    {
        $projectLinks = 0;
        if ($this->db->tableExists('project_technologies')) {
            $row = $this->db->fetchOne(
                'SELECT COUNT(*) AS total FROM project_technologies WHERE technology_id = :id',
                [':id' => $technologyId]
            );
            $projectLinks = (int) ($row['total'] ?? 0);
        }

        return [
            'project_links' => $projectLinks,
            'has_links' => $projectLinks > 0,
        ];
    }

    public function forProject(int $projectId): array
    {
        if (!$this->db->tableExists('project_technologies') || $projectId <= 0) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT pt.id AS link_id, pt.project_id, pt.technology_id, pt.version, pt.notes,
                    pt.created_at AS linked_at, pt.updated_at AS link_updated_at,
                    t.name, t.name_normalized, t.category, t.description, t.active
             FROM project_technologies pt
             INNER JOIN technologies t ON t.id = pt.technology_id
             WHERE pt.project_id = :project_id
             ORDER BY t.category ASC, t.name ASC',
            [':project_id' => $projectId]
        );
    }

    public function assignToProject(int $projectId, int $technologyId, ?string $version = null, ?string $notes = null): int
    {
        $this->assertAvailable();
        if (!$this->db->tableExists('project_technologies')) {
            throw new RuntimeException('Tabla project_technologies no disponible.');
        }

        $tech = $this->find($technologyId);
        if ($tech === null || (int) ($tech['active'] ?? 0) !== 1) {
            throw new InvalidArgumentException('Tecnología inválida o inactiva.');
        }

        $existing = $this->db->fetchOne(
            'SELECT id FROM project_technologies
             WHERE project_id = :project_id AND technology_id = :technology_id
             LIMIT 1',
            [
                ':project_id' => $projectId,
                ':technology_id' => $technologyId,
            ]
        );
        if ($existing !== null) {
            $this->updateProjectAssignment($projectId, $technologyId, $version, $notes);
            return (int) $existing['id'];
        }

        $versionValue = $this->nullableTrim($version);
        $notesValue = $this->nullableTrim($notes);

        return $this->db->insert(
            'INSERT INTO project_technologies (
                project_id, technology_id, version, notes, created_at, updated_at
             ) VALUES (
                :project_id, :technology_id, :version, :notes, NOW(), NOW()
             )',
            [
                ':project_id' => $projectId,
                ':technology_id' => $technologyId,
                ':version' => $versionValue,
                ':notes' => $notesValue,
            ]
        );
    }

    public function updateProjectAssignment(int $projectId, int $technologyId, ?string $version = null, ?string $notes = null): void
    {
        if (!$this->db->tableExists('project_technologies')) {
            throw new RuntimeException('Tabla project_technologies no disponible.');
        }

        $existing = $this->db->fetchOne(
            'SELECT id FROM project_technologies
             WHERE project_id = :project_id AND technology_id = :technology_id
             LIMIT 1',
            [
                ':project_id' => $projectId,
                ':technology_id' => $technologyId,
            ]
        );
        if ($existing === null) {
            throw new InvalidArgumentException('La tecnología no está asignada al proyecto.');
        }

        $this->db->execute(
            'UPDATE project_technologies
             SET version = :version, notes = :notes, updated_at = NOW()
             WHERE project_id = :project_id AND technology_id = :technology_id',
            [
                ':project_id' => $projectId,
                ':technology_id' => $technologyId,
                ':version' => $this->nullableTrim($version),
                ':notes' => $this->nullableTrim($notes),
            ]
        );
    }

    public function unassignFromProject(int $projectId, int $technologyId): bool
    {
        if (!$this->db->tableExists('project_technologies')) {
            return false;
        }

        $existing = $this->db->fetchOne(
            'SELECT id FROM project_technologies
             WHERE project_id = :project_id AND technology_id = :technology_id
             LIMIT 1',
            [
                ':project_id' => $projectId,
                ':technology_id' => $technologyId,
            ]
        );
        if ($existing === null) {
            return false;
        }

        $this->db->execute(
            'DELETE FROM project_technologies
             WHERE project_id = :project_id AND technology_id = :technology_id',
            [
                ':project_id' => $projectId,
                ':technology_id' => $technologyId,
            ]
        );

        return true;
    }

    private function sanitizePayload(array $payload): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $category = trim((string) ($payload['category'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('El nombre de la tecnología es obligatorio.');
        }
        if ($category === '') {
            throw new InvalidArgumentException('La categoría de la tecnología es obligatoria.');
        }

        $description = trim((string) ($payload['description'] ?? ''));

        return [
            ':name' => $name,
            ':name_normalized' => self::normalizeName($name),
            ':category' => $category,
            ':description' => $description !== '' ? $description : null,
            ':active' => isset($payload['active']) ? ((int) ((bool) $payload['active'])) : 1,
        ];
    }

    private function assertUniqueNameCategory(string $nameNormalized, string $category, ?int $exceptId = null): void
    {
        $sql = 'SELECT id FROM technologies
                WHERE name_normalized = :name_normalized AND category = :category';
        $params = [
            ':name_normalized' => $nameNormalized,
            ':category' => $category,
        ];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :except_id';
            $params[':except_id'] = $exceptId;
        }
        $sql .= ' LIMIT 1';

        $existing = $this->db->fetchOne($sql, $params);
        if ($existing !== null) {
            throw new InvalidArgumentException('Ya existe una tecnología con ese nombre en la misma categoría.');
        }
    }

    private function nullableTrim(?string $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));
        return $trimmed !== '' ? $trimmed : null;
    }

    private function assertAvailable(): void
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('El módulo de tecnologías no está disponible. Aplique la migración de Etapa B.');
        }
    }
}
