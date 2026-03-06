<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;

class AbsencesRepository
{
    public function __construct(private Database $db)
    {
    }

    public function listAll(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['talent_id'])) {
            $where[] = 'ta.talent_id = :talent_id';
            $params[':talent_id'] = (int) $filters['talent_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'ta.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['absence_type'])) {
            $where[] = 'ta.absence_type = :absence_type';
            $params[':absence_type'] = $filters['absence_type'];
        }

        if (!empty($filters['from'])) {
            $where[] = 'ta.end_date >= :from';
            $params[':from'] = $filters['from'];
        }

        if (!empty($filters['to'])) {
            $where[] = 'ta.start_date <= :to';
            $params[':to'] = $filters['to'];
        }

        $sql = 'SELECT ta.*,
                       t.name AS talent_name,
                       t.role AS talent_role,
                       approver.name AS approved_by_name
                FROM talent_absences ta
                INNER JOIN talents t ON t.id = ta.talent_id
                LEFT JOIN users approver ON approver.id = ta.approved_by
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ta.start_date DESC, ta.id DESC';

        return $this->db->fetchAll($sql, $params);
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT ta.*,
                    t.name AS talent_name,
                    t.role AS talent_role,
                    approver.name AS approved_by_name
             FROM talent_absences ta
             INNER JOIN talents t ON t.id = ta.talent_id
             LEFT JOIN users approver ON approver.id = ta.approved_by
             WHERE ta.id = :id LIMIT 1',
            [':id' => $id]
        );

        return $row ?: null;
    }

    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO talent_absences
                (talent_id, user_id, absence_type, start_date, end_date, hours, is_full_day, status, reason)
             VALUES
                (:talent_id, :user_id, :absence_type, :start_date, :end_date, :hours, :is_full_day, :status, :reason)',
            [
                ':talent_id'    => (int) $data['talent_id'],
                ':user_id'      => isset($data['user_id']) ? (int) $data['user_id'] : null,
                ':absence_type' => $data['absence_type'],
                ':start_date'   => $data['start_date'],
                ':end_date'     => $data['end_date'],
                ':hours'        => isset($data['hours']) && $data['hours'] !== '' ? (float) $data['hours'] : null,
                ':is_full_day'  => isset($data['is_full_day']) ? (int) (bool) $data['is_full_day'] : 1,
                ':status'       => $data['status'] ?? 'pendiente',
                ':reason'       => $data['reason'] ?? null,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE talent_absences SET
                talent_id    = :talent_id,
                absence_type = :absence_type,
                start_date   = :start_date,
                end_date     = :end_date,
                hours        = :hours,
                is_full_day  = :is_full_day,
                reason       = :reason
             WHERE id = :id',
            [
                ':id'           => $id,
                ':talent_id'    => (int) $data['talent_id'],
                ':absence_type' => $data['absence_type'],
                ':start_date'   => $data['start_date'],
                ':end_date'     => $data['end_date'],
                ':hours'        => isset($data['hours']) && $data['hours'] !== '' ? (float) $data['hours'] : null,
                ':is_full_day'  => isset($data['is_full_day']) ? (int) (bool) $data['is_full_day'] : 1,
                ':reason'       => $data['reason'] ?? null,
            ]
        );
    }

    public function updateStatus(int $id, string $status, ?int $approvedBy = null): void
    {
        $this->db->execute(
            'UPDATE talent_absences SET status = :status, approved_by = :approved_by WHERE id = :id',
            [
                ':id'          => $id,
                ':status'      => $status,
                ':approved_by' => $approvedBy,
            ]
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM talent_absences WHERE id = :id', [':id' => $id]);
    }

    public function countByStatus(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT status, COUNT(*) AS total FROM talent_absences GROUP BY status'
        );

        $counts = ['pendiente' => 0, 'aprobado' => 0, 'rechazado' => 0];
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    public function hasOverlap(int $talentId, string $startDate, string $endDate, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) AS cnt FROM talent_absences
                WHERE talent_id = :talent_id
                  AND status != :rejected
                  AND start_date <= :end_date
                  AND end_date >= :start_date';
        $params = [
            ':talent_id'  => $talentId,
            ':rejected'   => 'rechazado',
            ':start_date' => $startDate,
            ':end_date'   => $endDate,
        ];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        $row = $this->db->fetchOne($sql, $params);

        return (int) ($row['cnt'] ?? 0) > 0;
    }
}
