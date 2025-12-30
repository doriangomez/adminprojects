<?php

declare(strict_types=1);

class DesignControlsRepository
{
    private const CONTROL_TYPES = [
        'revision',
        'verificacion',
        'validacion',
    ];

    private const RESULTS = ['aprobado', 'observaciones', 'rechazado'];

    public function __construct(
        private Database $db,
        private ?AuditLogRepository $auditLog = null
    ) {
        $this->auditLog ??= new AuditLogRepository($this->db);
    }

    public function create(array $payload, ?int $userId = null): int
    {
        $projectId = (int) ($payload['project_id'] ?? 0);
        $controlType = trim((string) ($payload['control_type'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $result = trim((string) ($payload['result'] ?? ''));
        $correctiveAction = array_key_exists('corrective_action', $payload)
            ? trim((string) $payload['corrective_action'])
            : null;
        $performedBy = (int) ($payload['performed_by'] ?? 0);
        $performedAtInput = $payload['performed_at'] ?? null;
        $performedAt = $performedAtInput !== null && $performedAtInput !== ''
            ? trim((string) $performedAtInput)
            : null;

        $this->assertValidProject($projectId);
        $this->assertValidControlType($controlType);
        $this->assertValidDescription($description);
        $this->assertValidResult($result, $correctiveAction);
        $this->assertValidUser($performedBy);
        $this->assertSequence($projectId, $controlType);

        $columns = [
            'project_id',
            'control_type',
            'description',
            'result',
            'corrective_action',
            'performed_by',
        ];
        $placeholders = [
            ':project_id',
            ':control_type',
            ':description',
            ':result',
            ':corrective_action',
            ':performed_by',
        ];
        $params = [
            ':project_id' => $projectId,
            ':control_type' => $controlType,
            ':description' => $description,
            ':result' => $result,
            ':corrective_action' => $correctiveAction !== '' ? $correctiveAction : null,
            ':performed_by' => $performedBy,
        ];

        if ($performedAt !== null) {
            $columns[] = 'performed_at';
            $placeholders[] = ':performed_at';
            $params[':performed_at'] = $performedAt;
        }

        $id = $this->db->insert(
            sprintf(
                'INSERT INTO project_design_controls (%s) VALUES (%s)',
                implode(', ', $columns),
                implode(', ', $placeholders)
            ),
            $params
        );

        $this->auditCreation($userId, $id, [
            'project_id' => $projectId,
            'control_type' => $controlType,
            'result' => $result,
            'performed_by' => $performedBy,
            'corrective_action' => $correctiveAction !== '' ? $correctiveAction : null,
            'performed_at' => $performedAt,
        ]);

        return $id;
    }

    public function listByProject(int $projectId): array
    {
        $this->assertValidProject($projectId);

        return $this->db->fetchAll(
            'SELECT c.id, c.project_id, c.control_type, c.description, c.result, c.corrective_action, c.performed_by, c.performed_at, u.name AS performer_name
             FROM project_design_controls c
             LEFT JOIN users u ON u.id = c.performed_by
             WHERE c.project_id = :project
             ORDER BY c.performed_at ASC',
            [':project' => $projectId]
        );
    }

    public function allowedTypes(): array
    {
        return self::CONTROL_TYPES;
    }

    public function allowedResults(): array
    {
        return self::RESULTS;
    }

    public function countByType(int $projectId, string $controlType): int
    {
        if (!$this->db->tableExists('project_design_controls')) {
            return 0;
        }

        $normalizedType = trim($controlType);
        if (!in_array($normalizedType, self::CONTROL_TYPES, true)) {
            return 0;
        }

        $stmt = $this->db->connection()->prepare(
            'SELECT COUNT(*) FROM project_design_controls WHERE project_id = :project AND control_type = :type'
        );
        $stmt->execute([
            ':project' => $projectId,
            ':type' => $normalizedType,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function countByTypeAndResult(int $projectId, string $controlType, string $result): int
    {
        if (
            !$this->db->tableExists('project_design_controls')
            || !in_array($controlType, self::CONTROL_TYPES, true)
            || !in_array($result, self::RESULTS, true)
        ) {
            return 0;
        }

        $stmt = $this->db->connection()->prepare(
            'SELECT COUNT(*) FROM project_design_controls WHERE project_id = :project AND control_type = :type AND result = :result'
        );
        $stmt->execute([
            ':project' => $projectId,
            ':type' => $controlType,
            ':result' => $result,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function assertValidProject(int $projectId): void
    {
        if ($projectId <= 0) {
            throw new \InvalidArgumentException('El proyecto es obligatorio.');
        }
    }

    private function assertValidControlType(string $controlType): void
    {
        if (!in_array($controlType, self::CONTROL_TYPES, true)) {
            throw new \InvalidArgumentException('El tipo de control no es válido.');
        }
    }

    private function assertValidDescription(string $description): void
    {
        if ($description === '') {
            throw new \InvalidArgumentException('La descripción del control es obligatoria.');
        }
    }

    private function assertValidResult(string $result, ?string $correctiveAction): void
    {
        if (!in_array($result, self::RESULTS, true)) {
            throw new \InvalidArgumentException('El resultado del control no es válido.');
        }

        if ($result === 'rechazado' && ($correctiveAction === null || trim($correctiveAction) === '')) {
            throw new \InvalidArgumentException('Describe la acción correctiva para los controles rechazados.');
        }
    }

    private function assertValidUser(int $userId): void
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('El responsable del control es obligatorio.');
        }
    }

    private function assertSequence(int $projectId, string $controlType): void
    {
        if (!$this->db->tableExists('project_design_controls')) {
            return;
        }

        if ($controlType === 'verificacion') {
            $revisionCount = $this->countByType($projectId, 'revision');
            if ($revisionCount < 1) {
                throw new \InvalidArgumentException('No puedes registrar una verificación sin revisiones previas.');
            }
        }

        if ($controlType === 'validacion') {
            $verificationCount = $this->countByType($projectId, 'verificacion');
            if ($verificationCount < 1) {
                throw new \InvalidArgumentException('No puedes registrar una validación sin verificaciones previas.');
            }
        }
    }

    private function auditCreation(?int $userId, int $entityId, array $payload): void
    {
        try {
            $this->auditLog?->log(
                $userId,
                'project_design_control',
                $entityId,
                'create',
                $payload
            );
        } catch (\Throwable $e) {
            error_log('No se pudo registrar la auditoría del control: ' . $e->getMessage());
        }
    }
}
