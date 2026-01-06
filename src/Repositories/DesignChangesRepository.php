<?php

declare(strict_types=1);

class DesignChangesRepository
{
    private const IMPACT_LEVELS = ['bajo', 'medio', 'alto'];
    private const STATUSES = ['pendiente', 'aprobado'];

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
        $description = trim((string) ($payload['description'] ?? ''));
        $impactScope = $this->normalizeImpact($payload['impact_scope'] ?? '');
        $impactTime = $this->normalizeImpact($payload['impact_time'] ?? '');
        $impactCost = $this->normalizeImpact($payload['impact_cost'] ?? '');
        $impactQuality = $this->normalizeImpact($payload['impact_quality'] ?? '');
        $requiresReviewValidation = (int) ($payload['requires_review_validation'] ?? 0) === 1 ? 1 : 0;

        $this->assertTableExists();
        $this->assertValidProject($projectId);
        $this->assertValidNode($projectId, $projectNodeId);
        $this->assertDescription($description);

        $id = $this->db->insert(
            'INSERT INTO project_design_changes (
                project_id,
                project_node_id,
                description,
                impact_scope,
                impact_time,
                impact_cost,
                impact_quality,
                requires_review_validation,
                status,
                created_by
            ) VALUES (
                :project_id,
                :project_node_id,
                :description,
                :impact_scope,
                :impact_time,
                :impact_cost,
                :impact_quality,
                :requires_review_validation,
                :status,
                :created_by
            )',
            [
                ':project_id' => $projectId,
                ':project_node_id' => $projectNodeId,
                ':description' => $description,
                ':impact_scope' => $impactScope,
                ':impact_time' => $impactTime,
                ':impact_cost' => $impactCost,
                ':impact_quality' => $impactQuality,
                ':requires_review_validation' => $requiresReviewValidation,
                ':status' => 'pendiente',
                ':created_by' => $userId > 0 ? $userId : null,
            ]
        );

        $this->audit('create', $id, $userId, [
            'project_id' => $projectId,
            'impact_scope' => $impactScope,
            'impact_time' => $impactTime,
            'impact_cost' => $impactCost,
            'impact_quality' => $impactQuality,
            'requires_review_validation' => $requiresReviewValidation,
            'project_node_id' => $projectNodeId,
        ]);

        return $id;
    }

    public function approve(int $id, int $projectId, ?int $userId = null): void
    {
        $this->assertTableExists();
        $change = $this->find($id);

        if (!$change || (int) ($change['project_id'] ?? 0) !== $projectId) {
            throw new \InvalidArgumentException('El cambio de diseño no existe.');
        }

        if (($change['status'] ?? '') === 'aprobado') {
            return;
        }

        $approvedAt = date('Y-m-d H:i:s');

        $this->db->execute(
            'UPDATE project_design_changes
             SET status = :status, approved_by = :approved_by, approved_at = :approved_at
             WHERE id = :id AND project_id = :project_id',
            [
                ':status' => 'aprobado',
                ':approved_by' => $userId > 0 ? $userId : null,
                ':approved_at' => $approvedAt,
                ':id' => $id,
                ':project_id' => $projectId,
            ]
        );

        $this->audit('approve', $id, $userId, [
            'project_id' => $projectId,
            'before_status' => $change['status'] ?? 'pendiente',
            'after_status' => 'aprobado',
            'approved_at' => $approvedAt,
        ]);
    }

    public function listByProject(int $projectId): array
    {
        if (!$this->db->tableExists('project_design_changes')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT c.id,
                    c.project_id,
                    c.project_node_id,
                    c.description,
                    c.impact_scope,
                    c.impact_time,
                    c.impact_cost,
                    c.impact_quality,
                    c.requires_review_validation,
                    c.status,
                    c.created_at,
                    c.approved_at,
                    creator.name AS created_by_name,
                    approver.name AS approved_by_name
             FROM project_design_changes c
             LEFT JOIN users creator ON creator.id = c.created_by
             LEFT JOIN users approver ON approver.id = c.approved_by
             WHERE c.project_id = :project
             ORDER BY c.created_at DESC',
            [':project' => $projectId]
        );
    }

    public function pendingCount(int $projectId): int
    {
        if (!$this->db->tableExists('project_design_changes')) {
            return 0;
        }

        $stmt = $this->db->connection()->prepare(
            'SELECT COUNT(*) FROM project_design_changes WHERE project_id = :project AND status = :status'
        );
        $stmt->execute([
            ':project' => $projectId,
            ':status' => 'pendiente',
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function statusSummary(int $projectId): array
    {
        if (!$this->db->tableExists('project_design_changes')) {
            return ['pending' => 0, 'approved' => 0, 'total' => 0];
        }

        $rows = $this->db->fetchAll(
            'SELECT status, COUNT(*) AS total
             FROM project_design_changes
             WHERE project_id = :project
             GROUP BY status',
            [':project' => $projectId]
        );

        $summary = ['pending' => 0, 'approved' => 0, 'total' => 0];
        foreach ($rows as $row) {
            $status = $row['status'] ?? '';
            $count = (int) ($row['total'] ?? 0);
            $summary['total'] += $count;
            if ($status === 'pendiente') {
                $summary['pending'] = $count;
            }
            if ($status === 'aprobado') {
                $summary['approved'] = $count;
            }
        }

        return $summary;
    }

    public function impactLevels(): array
    {
        return self::IMPACT_LEVELS;
    }

    private function find(int $id): ?array
    {
        if (!$this->db->tableExists('project_design_changes')) {
            return null;
        }

        return $this->db->fetchOne(
            'SELECT id, project_id, status FROM project_design_changes WHERE id = :id',
            [':id' => $id]
        );
    }

    private function assertTableExists(): void
    {
        if (!$this->db->tableExists('project_design_changes')) {
            throw new \InvalidArgumentException('El control de cambios no está disponible.');
        }
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
            throw new \InvalidArgumentException('No se pudo vincular el cambio ISO a una carpeta del proyecto.');
        }

        $exists = $this->db->fetchOne(
            'SELECT id FROM project_nodes WHERE id = :id AND project_id = :project LIMIT 1',
            [
                ':id' => $nodeId,
                ':project' => $projectId,
            ]
        );

        if (!$exists) {
            throw new \InvalidArgumentException('La carpeta seleccionada para el cambio ISO no es válida.');
        }
    }

    private function assertDescription(string $description): void
    {
        if ($description === '') {
            throw new \InvalidArgumentException('Describe el cambio de diseño.');
        }
    }

    private function normalizeImpact(string $value): string
    {
        $normalized = strtolower(trim($value));

        if (!in_array($normalized, self::IMPACT_LEVELS, true)) {
            throw new \InvalidArgumentException('Selecciona un impacto válido (bajo, medio o alto).');
        }

        return $normalized;
    }

    private function audit(string $action, int $entityId, ?int $userId, array $payload): void
    {
        try {
            $this->auditLog?->log(
                $userId,
                'project_design_change',
                $entityId,
                $action,
                $payload
            );
        } catch (\Throwable $e) {
            error_log('No se pudo registrar la auditoría del cambio de diseño: ' . $e->getMessage());
        }
    }
}
