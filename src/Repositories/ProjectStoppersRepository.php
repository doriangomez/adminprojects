<?php

declare(strict_types=1);

namespace App\Repositories;

class ProjectStoppersRepository
{
    public const STATUS_OPEN = 'abierto';
    public const STATUS_MANAGING = 'en_gestion';
    public const STATUS_ESCALATED = 'escalado';
    public const STATUS_RESOLVED = 'resuelto';
    public const STATUS_CLOSED = 'cerrado';

    private const ACTIVE_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_MANAGING,
        self::STATUS_ESCALATED,
        self::STATUS_RESOLVED,
    ];

    public function __construct(private \Database $db)
    {
    }

    public function forProject(int $projectId): array
    {
        return $this->db->fetchAll(
            'SELECT s.*, u.name AS responsible_name
             FROM project_stoppers s
             LEFT JOIN users u ON u.id = s.responsible_id
             WHERE s.project_id = :project_id
             ORDER BY FIELD(s.impact_level, "critico", "alto", "medio", "bajo"), s.detected_at DESC, s.id DESC',
            [':project_id' => $projectId]
        );
    }

    public function create(int $projectId, array $payload, int $actorId): int
    {
        $columns = [
            'project_id', 'title', 'description', 'stopper_type', 'impact_level', 'affected_area',
            'responsible_id', 'detected_at', 'estimated_resolution_at', 'status', 'closure_comment',
            'created_by', 'updated_by', 'created_at', 'updated_at',
        ];
        $values = [
            ':project_id', ':title', ':description', ':stopper_type', ':impact_level', ':affected_area',
            ':responsible_id', ':detected_at', ':estimated_resolution_at', ':status', 'NULL',
            ':created_by', ':updated_by', 'NOW()', 'NOW()',
        ];
        $params = [
            ':project_id' => $projectId,
            ':title' => $payload['title'],
            ':description' => $payload['description'],
            ':stopper_type' => $payload['stopper_type'],
            ':impact_level' => $payload['impact_level'],
            ':affected_area' => $payload['affected_area'],
            ':responsible_id' => $payload['responsible_id'],
            ':detected_at' => $payload['detected_at'],
            ':estimated_resolution_at' => $payload['estimated_resolution_at'],
            ':status' => $payload['status'],
            ':created_by' => $actorId,
            ':updated_by' => $actorId,
        ];
        if ($this->db->columnExists('project_stoppers', 'task_id')) {
            $columns[] = 'task_id';
            $values[] = ':task_id';
            $params[':task_id'] = isset($payload['task_id']) ? (int) $payload['task_id'] : null;
        }

        return $this->db->insert(
            'INSERT INTO project_stoppers (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $values) . ')',
            $params
        );
    }

    public function update(int $projectId, int $stopperId, array $payload, int $actorId): void
    {
        $this->db->execute(
            'UPDATE project_stoppers
             SET title = :title,
                 description = :description,
                 stopper_type = :stopper_type,
                 impact_level = :impact_level,
                 affected_area = :affected_area,
                 responsible_id = :responsible_id,
                 detected_at = :detected_at,
                 estimated_resolution_at = :estimated_resolution_at,
                 status = :status,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE id = :id AND project_id = :project_id',
            [
                ':id' => $stopperId,
                ':project_id' => $projectId,
                ':title' => $payload['title'],
                ':description' => $payload['description'],
                ':stopper_type' => $payload['stopper_type'],
                ':impact_level' => $payload['impact_level'],
                ':affected_area' => $payload['affected_area'],
                ':responsible_id' => $payload['responsible_id'],
                ':detected_at' => $payload['detected_at'],
                ':estimated_resolution_at' => $payload['estimated_resolution_at'],
                ':status' => $payload['status'],
                ':updated_by' => $actorId,
            ]
        );
    }

    public function close(int $projectId, int $stopperId, string $closureComment, int $actorId): void
    {
        $this->db->execute(
            'UPDATE project_stoppers
             SET status = :status,
                 closure_comment = :closure_comment,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE id = :id AND project_id = :project_id',
            [
                ':status' => self::STATUS_CLOSED,
                ':closure_comment' => $closureComment,
                ':updated_by' => $actorId,
                ':id' => $stopperId,
                ':project_id' => $projectId,
            ]
        );
    }

    public function openCount(int $projectId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM project_stoppers WHERE project_id = :project_id AND status IN ("abierto", "en_gestion", "escalado", "resuelto")',
            [':project_id' => $projectId]
        );

        return (int) ($row['total'] ?? 0);
    }

    public function metricsForProject(int $projectId): array
    {
        $row = $this->db->fetchOne(
            'SELECT
                SUM(CASE WHEN status IN ("abierto", "en_gestion", "escalado", "resuelto") THEN 1 ELSE 0 END) AS open_total,
                SUM(CASE WHEN status IN ("abierto", "en_gestion", "escalado", "resuelto") AND impact_level = "critico" THEN 1 ELSE 0 END) AS critical_open,
                AVG(CASE WHEN status IN ("abierto", "en_gestion", "escalado", "resuelto") THEN DATEDIFF(CURDATE(), detected_at) END) AS avg_days_open,
                SUM(CASE WHEN status IN ("abierto", "en_gestion", "escalado", "resuelto") AND impact_level = "alto" AND DATEDIFF(CURDATE(), detected_at) > 7 THEN 1 ELSE 0 END) AS high_overdue
             FROM project_stoppers
             WHERE project_id = :project_id',
            [':project_id' => $projectId]
        ) ?? [];

        return [
            'open_total' => (int) ($row['open_total'] ?? 0),
            'critical_open' => (int) ($row['critical_open'] ?? 0),
            'avg_days_open' => round((float) ($row['avg_days_open'] ?? 0), 1),
            'high_overdue' => (int) ($row['high_overdue'] ?? 0),
        ];
    }

    public function byImpactOpen(int $projectId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT impact_level, COUNT(*) AS total
             FROM project_stoppers
             WHERE project_id = :project_id AND status IN ("abierto", "en_gestion", "escalado", "resuelto")
             GROUP BY impact_level',
            [':project_id' => $projectId]
        );

        $totals = ['critico' => 0, 'alto' => 0, 'medio' => 0, 'bajo' => 0];
        foreach ($rows as $row) {
            $impact = (string) ($row['impact_level'] ?? '');
            if (isset($totals[$impact])) {
                $totals[$impact] = (int) ($row['total'] ?? 0);
            }
        }

        return $totals;
    }
}
