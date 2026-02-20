<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;

class AuditLogRepository
{
    public function __construct(private Database $db)
    {
    }

    public function log(?int $userId, string $entity, int $entityId, string $action, array $payload = []): void
    {
        $this->db->execute(
            'INSERT INTO audit_log (user_id, entity, entity_id, action, payload) VALUES (:user_id, :entity, :entity_id, :action, :payload)',
            [
                ':user_id' => $userId > 0 ? $userId : null,
                ':entity' => $entity,
                ':entity_id' => $entityId,
                ':action' => $action,
                ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]
        );
    }

    public function listForEntity(string $entity, int $entityId, int $limit = 50): array
    {
        $rows = $this->db->fetchAll(
            'SELECT audit_log.id, audit_log.user_id, audit_log.action, audit_log.payload, audit_log.created_at, users.name AS user_name
             FROM audit_log
             LEFT JOIN users ON users.id = audit_log.user_id
             WHERE audit_log.entity = :entity AND audit_log.entity_id = :entity_id
             ORDER BY audit_log.created_at DESC, audit_log.id DESC
             LIMIT :limit',
            [
                ':entity' => $entity,
                ':entity_id' => $entityId,
                ':limit' => $limit,
            ]
        );

        return array_map(static function (array $row): array {
            $payload = [];
            if (!empty($row['payload'])) {
                $decoded = json_decode((string) $row['payload'], true);
                $payload = is_array($decoded) ? $decoded : [];
            }

            return [
                'id' => (int) ($row['id'] ?? 0),
                'user_id' => $row['user_id'] !== null ? (int) $row['user_id'] : null,
                'user_name' => $row['user_name'] ?? null,
                'action' => $row['action'] ?? '',
                'payload' => $payload,
                'created_at' => $row['created_at'] ?? null,
            ];
        }, $rows);
    }
}
