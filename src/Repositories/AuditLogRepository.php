<?php

declare(strict_types=1);

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
}
