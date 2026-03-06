<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;

class AbsencesRepository
{
    public function __construct(private Database $db)
    {
    }

    public function listAll(?int $talentId = null, ?string $status = null, ?string $startFrom = null, ?string $endTo = null): array
    {
        if (!$this->db->tableExists('talent_absences')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($talentId !== null && $talentId > 0) {
            $where[] = 'a.talent_id = :talent_id';
            $params[':talent_id'] = $talentId;
        }
        if ($status !== null && $status !== '') {
            $where[] = 'LOWER(a.status) = LOWER(:status)';
            $params[':status'] = $status;
        }
        if ($startFrom !== null && $startFrom !== '') {
            $where[] = 'a.end_date >= :start_from';
            $params[':start_from'] = $startFrom;
        }
        if ($endTo !== null && $endTo !== '') {
            $where[] = 'a.start_date <= :end_to';
            $params[':end_to'] = $endTo;
        }

        $sql = 'SELECT a.*, t.name AS talent_name
                FROM talent_absences a
                INNER JOIN talents t ON t.id = a.talent_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY a.start_date DESC, a.id DESC';

        return $this->db->fetchAll($sql, $params);
    }

    public function find(int $id): ?array
    {
        if (!$this->db->tableExists('talent_absences')) {
            return null;
        }

        $row = $this->db->fetchOne(
            'SELECT a.*, t.name AS talent_name
             FROM talent_absences a
             INNER JOIN talents t ON t.id = a.talent_id
             WHERE a.id = :id LIMIT 1',
            [':id' => $id]
        );

        return $row ?: null;
    }

    public function create(array $payload): int
    {
        $this->db->execute(
            'INSERT INTO talent_absences (talent_id, user_id, absence_type, start_date, end_date, hours, is_full_day, status, reason, approved_by)
             VALUES (:talent_id, :user_id, :absence_type, :start_date, :end_date, :hours, :is_full_day, :status, :reason, :approved_by)',
            [
                ':talent_id' => (int) ($payload['talent_id'] ?? 0),
                ':user_id' => !empty($payload['user_id']) ? (int) $payload['user_id'] : null,
                ':absence_type' => trim((string) ($payload['absence_type'] ?? 'ausencia')),
                ':start_date' => $payload['start_date'] ?? '',
                ':end_date' => $payload['end_date'] ?? '',
                ':hours' => isset($payload['hours']) ? (float) $payload['hours'] : null,
                ':is_full_day' => !empty($payload['is_full_day']) ? 1 : 0,
                ':status' => trim((string) ($payload['status'] ?? 'pendiente')),
                ':reason' => trim((string) ($payload['reason'] ?? '')) ?: null,
                ':approved_by' => !empty($payload['approved_by']) ? (int) $payload['approved_by'] : null,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $payload): bool
    {
        $allowed = ['talent_id', 'user_id', 'absence_type', 'start_date', 'end_date', 'hours', 'is_full_day', 'status', 'reason', 'approved_by'];
        $updates = [];
        $params = [':id' => $id];

        foreach ($allowed as $col) {
            if (!array_key_exists($col, $payload)) {
                continue;
            }
            if (in_array($col, ['talent_id', 'user_id', 'approved_by', 'is_full_day'], true)) {
                $val = $payload[$col];
                $updates[] = $col . ' = ' . ($val === null || $val === '' ? 'NULL' : ':' . $col);
                if ($val !== null && $val !== '') {
                    $params[':' . $col] = (int) $val;
                }
            } elseif ($col === 'hours') {
                $val = $payload[$col];
                $updates[] = $col . ' = ' . ($val === null || $val === '' ? 'NULL' : ':' . $col);
                if ($val !== null && $val !== '') {
                    $params[':' . $col] = (float) $val;
                }
            } else {
                $updates[] = $col . ' = :' . $col;
                $params[':' . $col] = (string) $payload[$col];
            }
        }

        if ($updates === []) {
            return true;
        }

        $this->db->execute(
            'UPDATE talent_absences SET ' . implode(', ', $updates) . ' WHERE id = :id',
            $params
        );

        return true;
    }

    public function delete(int $id): bool
    {
        $this->db->execute('DELETE FROM talent_absences WHERE id = :id', [':id' => $id]);

        return true;
    }

    public function approve(int $id, int $approvedByUserId): bool
    {
        return $this->update($id, [
            'status' => 'aprobado',
            'approved_by' => $approvedByUserId,
        ]);
    }

    public function reject(int $id): bool
    {
        return $this->update($id, ['status' => 'rechazado']);
    }
}
