<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;

class DesignInputsRepository
{
    private const INPUT_TYPES = [
        'requisitos_funcionales',
        'requisitos_desempeno',
        'requisitos_legales',
        'normativa',
        'referencias_previas',
        'input_cliente',
        'otro',
    ];

    public function __construct(
        private Database $db,
        private ?AuditLogRepository $auditLog = null
    ) {
        $this->auditLog ??= new AuditLogRepository($this->db);
    }

    public function create(array $payload, ?int $userId = null): int
    {
        $projectId = (int) ($payload['project_id'] ?? 0);
        $projectNodeId = (int) ($payload['project_node_id'] ?? 0);
        $inputType = trim((string) ($payload['input_type'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $source = isset($payload['source']) ? trim((string) $payload['source']) : null;
        $resolvedConflict = (int) ($payload['resolved_conflict'] ?? 0) === 1 ? 1 : 0;

        $this->assertValidProject($projectId);
        $this->assertValidNode($projectId, $projectNodeId);
        $this->assertValidInputType($inputType);
        $this->assertValidDescription($description);

        $id = $this->db->insert(
            'INSERT INTO project_design_inputs (project_id, project_node_id, input_type, description, source, resolved_conflict)
             VALUES (:project_id, :project_node_id, :input_type, :description, :source, :resolved_conflict)',
            [
                ':project_id' => $projectId,
                ':project_node_id' => $projectNodeId,
                ':input_type' => $inputType,
                ':description' => $description,
                ':source' => $source !== '' ? $source : null,
                ':resolved_conflict' => $resolvedConflict,
            ]
        );

        $this->audit($userId, $id, 'create', [
            'project_id' => $projectId,
            'input_type' => $inputType,
            'resolved_conflict' => $resolvedConflict,
        ]);

        if ($resolvedConflict === 1) {
            $this->auditConflictResolution($userId, $id, $projectId);
        }

        return $id;
    }

    public function delete(int $id, int $projectId, ?int $userId = null): void
    {
        $existing = $this->find($id);
        if (!$existing || (int) ($existing['project_id'] ?? 0) !== $projectId) {
            throw new \InvalidArgumentException('La entrada de diseño no existe.');
        }

        $this->db->execute(
            'DELETE FROM project_design_inputs WHERE id = :id AND project_id = :project',
            [
                ':id' => $id,
                ':project' => $projectId,
            ]
        );

        $this->audit($userId, $id, 'delete', [
            'project_id' => $projectId,
        ]);
    }

    public function update(int $id, array $payload, ?int $userId = null): void
    {
        $existing = $this->find($id);
        if (!$existing) {
            throw new \InvalidArgumentException('La entrada de diseño no existe.');
        }

        $fields = [];
        $params = [':id' => $id];
        $after = $existing;

        if (array_key_exists('input_type', $payload)) {
            $inputType = trim((string) $payload['input_type']);
            $this->assertValidInputType($inputType);
            $fields[] = 'input_type = :input_type';
            $params[':input_type'] = $inputType;
            $after['input_type'] = $inputType;
        }

        if (array_key_exists('description', $payload)) {
            $description = trim((string) $payload['description']);
            $this->assertValidDescription($description);
            $fields[] = 'description = :description';
            $params[':description'] = $description;
            $after['description'] = $description;
        }

        if (array_key_exists('source', $payload)) {
            $fields[] = 'source = :source';
            $params[':source'] = isset($payload['source']) && $payload['source'] !== ''
                ? trim((string) $payload['source'])
                : null;
            $after['source'] = $params[':source'];
        }

        if (array_key_exists('resolved_conflict', $payload)) {
            $resolved = (int) $payload['resolved_conflict'] === 1 ? 1 : 0;
            $fields[] = 'resolved_conflict = :resolved_conflict';
            $params[':resolved_conflict'] = $resolved;
            $after['resolved_conflict'] = $resolved;
        }

        if (empty($fields)) {
            return;
        }

        $this->db->execute(
            'UPDATE project_design_inputs SET ' . implode(', ', $fields) . ' WHERE id = :id',
            $params
        );

        $diff = $this->diff($existing, $after);
        if (!empty($diff)) {
            $this->audit($userId, $id, 'update', [
                'project_id' => (int) $existing['project_id'],
                'changes' => $diff,
            ]);
        }

        if (
            ($existing['resolved_conflict'] ?? 0) != ($after['resolved_conflict'] ?? 0)
            && (int) ($after['resolved_conflict'] ?? 0) === 1
        ) {
            $this->auditConflictResolution($userId, $id, (int) $existing['project_id']);
        }
    }

    public function markConflictResolved(int $id, ?int $userId = null): void
    {
        $existing = $this->find($id);
        if (!$existing) {
            throw new \InvalidArgumentException('La entrada de diseño no existe.');
        }

        if ((int) ($existing['resolved_conflict'] ?? 0) === 1) {
            return;
        }

        $this->db->execute(
            'UPDATE project_design_inputs SET resolved_conflict = 1 WHERE id = :id',
            [':id' => $id]
        );

        $this->audit($userId, $id, 'update', [
            'project_id' => (int) $existing['project_id'],
            'changes' => [
                'resolved_conflict' => [
                    'before' => (int) ($existing['resolved_conflict'] ?? 0),
                    'after' => 1,
                ],
            ],
        ]);
        $this->auditConflictResolution($userId, $id, (int) $existing['project_id']);
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, project_id, project_node_id, input_type, description, source, resolved_conflict, created_at
             FROM project_design_inputs WHERE id = :id',
            [':id' => $id]
        );
    }

    public function listByProject(int $projectId): array
    {
        $this->assertValidProject($projectId);

        return $this->db->fetchAll(
            'SELECT id, project_id, project_node_id, input_type, description, source, resolved_conflict, created_at
             FROM project_design_inputs
             WHERE project_id = :project
             ORDER BY created_at ASC',
            [':project' => $projectId]
        );
    }

    public function allowedTypes(): array
    {
        return self::INPUT_TYPES;
    }

    public function countByProject(int $projectId): int
    {
        $this->assertValidProject($projectId);

        $stmt = $this->db->connection()->prepare(
            'SELECT COUNT(*) FROM project_design_inputs WHERE project_id = :project'
        );
        $stmt->execute([':project' => $projectId]);

        return (int) $stmt->fetchColumn();
    }

    private function assertValidProject(int $projectId): void
    {
        if ($projectId <= 0) {
            throw new \InvalidArgumentException('El proyecto es obligatorio.');
        }
    }

    private function assertValidNode(int $projectId, int $nodeId): void
    {
        if ($nodeId <= 0) {
            throw new \InvalidArgumentException('No se pudo vincular la entrada ISO a una carpeta del proyecto.');
        }

        $exists = $this->db->fetchOne(
            'SELECT id FROM project_nodes WHERE id = :id AND project_id = :project LIMIT 1',
            [
                ':id' => $nodeId,
                ':project' => $projectId,
            ]
        );

        if (!$exists) {
            throw new \InvalidArgumentException('La carpeta seleccionada para la entrada ISO no es válida.');
        }
    }

    private function assertValidInputType(string $inputType): void
    {
        if (!in_array($inputType, self::INPUT_TYPES, true)) {
            throw new \InvalidArgumentException('El tipo de entrada de diseño no es válido.');
        }
    }

    private function assertValidDescription(string $description): void
    {
        if ($description === '') {
            throw new \InvalidArgumentException('La descripción de la entrada de diseño es obligatoria.');
        }
    }

    private function diff(array $before, array $after): array
    {
        $fields = ['input_type', 'description', 'source', 'resolved_conflict'];
        $changes = [];

        foreach ($fields as $field) {
            $beforeValue = $before[$field] ?? null;
            $afterValue = $after[$field] ?? null;

            if ($beforeValue != $afterValue) {
                $changes[$field] = [
                    'before' => $beforeValue,
                    'after' => $afterValue,
                ];
            }
        }

        return $changes;
    }

    private function audit(?int $userId, int $entityId, string $action, array $payload): void
    {
        try {
            $this->auditLog?->log(
                $userId,
                'project_design_input',
                $entityId,
                $action,
                $payload
            );
        } catch (\Throwable $e) {
            error_log('No se pudo registrar en audit_log: ' . $e->getMessage());
        }
    }

    private function auditConflictResolution(?int $userId, int $entityId, int $projectId): void
    {
        $this->audit($userId, $entityId, 'conflict_resolved', [
            'project_id' => $projectId,
            'resolved_conflict' => 1,
        ]);
    }
}
