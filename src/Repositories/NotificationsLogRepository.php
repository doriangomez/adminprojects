<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;

class NotificationsLogRepository
{
    public function __construct(private Database $db)
    {
    }

    public function log(array $payload): void
    {
        $this->db->execute(
            'INSERT INTO notifications_log (event_type, channel, recipient_email, recipient_user_id, status, error_message, payload)
             VALUES (:event_type, :channel, :recipient_email, :recipient_user_id, :status, :error_message, :payload)',
            [
                ':event_type' => $payload['event_type'] ?? '',
                ':channel' => $payload['channel'] ?? 'email',
                ':recipient_email' => $payload['recipient_email'] ?? '',
                ':recipient_user_id' => $payload['recipient_user_id'] ?? null,
                ':status' => $payload['status'] ?? 'sent',
                ':error_message' => $payload['error_message'] ?? null,
                ':payload' => json_encode($payload['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]
        );
    }

    public function latest(int $limit = 100): array
    {
        if (!$this->db->tableExists('notifications_log')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT id, event_type, channel, recipient_email, recipient_user_id, status, error_message, payload, created_at
             FROM notifications_log
             ORDER BY created_at DESC, id DESC
             LIMIT :limit',
            [':limit' => $limit]
        );
    }
}
