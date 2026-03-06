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
            $where[] = 'a.talent_id = :talent_id';
            $params[':talent_id'] = (int) $filters['talent_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'a.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['absence_type'])) {
            $where[] = 'a.absence_type = :absence_type';
            $params[':absence_type'] = $filters['absence_type'];
        }

        if (!empty($filters['from_date'])) {
            $where[] = 'a.end_date >= :from_date';
            $params[':from_date'] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $where[] = 'a.start_date <= :to_date';
            $params[':to_date'] = $filters['to_date'];
        }

        $whereClause = implode(' AND ', $where);

        return $this->db->fetchAll(
            "SELECT a.*, t.name AS talent_name, t.role AS talent_role,
                    u.name AS created_by_name,
                    approver.name AS approved_by_name
             FROM talent_absences a
             JOIN talents t ON t.id = a.talent_id
             LEFT JOIN users u ON u.id = a.user_id
             LEFT JOIN users approver ON approver.id = a.approved_by
             WHERE {$whereClause}
             ORDER BY a.start_date DESC, a.created_at DESC",
            $params
        );
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT a.*, t.name AS talent_name, t.role AS talent_role
             FROM talent_absences a
             JOIN talents t ON t.id = a.talent_id
             WHERE a.id = :id',
            [':id' => $id]
        );
    }

    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO talent_absences (talent_id, user_id, absence_type, start_date, end_date, hours, is_full_day, status, reason)
             VALUES (:talent_id, :user_id, :absence_type, :start_date, :end_date, :hours, :is_full_day, :status, :reason)',
            [
                ':talent_id' => (int) $data['talent_id'],
                ':user_id' => $data['user_id'] ? (int) $data['user_id'] : null,
                ':absence_type' => $data['absence_type'],
                ':start_date' => $data['start_date'],
                ':end_date' => $data['end_date'],
                ':hours' => $data['hours'] ?? null,
                ':is_full_day' => (int) ($data['is_full_day'] ?? 1),
                ':status' => $data['status'] ?? 'pendiente',
                ':reason' => $data['reason'] ?? null,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE talent_absences
             SET absence_type = :absence_type,
                 start_date = :start_date,
                 end_date = :end_date,
                 hours = :hours,
                 is_full_day = :is_full_day,
                 reason = :reason,
                 updated_at = NOW()
             WHERE id = :id',
            [
                ':id' => $id,
                ':absence_type' => $data['absence_type'],
                ':start_date' => $data['start_date'],
                ':end_date' => $data['end_date'],
                ':hours' => $data['hours'] ?? null,
                ':is_full_day' => (int) ($data['is_full_day'] ?? 1),
                ':reason' => $data['reason'] ?? null,
            ]
        );
    }

    public function updateStatus(int $id, string $status, ?int $approvedBy = null): void
    {
        $this->db->execute(
            'UPDATE talent_absences
             SET status = :status, approved_by = :approved_by, updated_at = NOW()
             WHERE id = :id',
            [
                ':id' => $id,
                ':status' => $status,
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

        $result = ['pendiente' => 0, 'aprobado' => 0, 'rechazado' => 0];
        foreach ($rows as $row) {
            $result[$row['status']] = (int) $row['total'];
        }

        return $result;
    }

    public function absenceTypes(): array
    {
        return [
            'vacaciones' => 'Vacaciones',
            'incapacidad' => 'Incapacidad',
            'permiso_personal' => 'Permiso personal',
            'permiso_medico' => 'Permiso médico',
            'capacitacion' => 'Capacitación',
            'licencia' => 'Licencia',
            'festivo' => 'Festivo',
            'otro' => 'Otro',
        ];
    }

    public function statusLabels(): array
    {
        return [
            'pendiente' => 'Pendiente',
            'aprobado' => 'Aprobado',
            'rechazado' => 'Rechazado',
        ];
    }

    public function importBulk(array $rows, ?int $userId = null): int
    {
        $imported = 0;
        foreach ($rows as $row) {
            if (empty($row['talent_id']) || empty($row['absence_type']) || empty($row['start_date']) || empty($row['end_date'])) {
                continue;
            }

            $exists = $this->db->fetchOne(
                'SELECT id FROM talent_absences
                 WHERE talent_id = :talent_id AND absence_type = :type AND start_date = :start AND end_date = :end',
                [
                    ':talent_id' => (int) $row['talent_id'],
                    ':type' => $row['absence_type'],
                    ':start' => $row['start_date'],
                    ':end' => $row['end_date'],
                ]
            );

            if ($exists) {
                continue;
            }

            $this->create([
                'talent_id' => (int) $row['talent_id'],
                'user_id' => $userId,
                'absence_type' => $row['absence_type'],
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'],
                'hours' => $row['hours'] ?? null,
                'is_full_day' => $row['is_full_day'] ?? 1,
                'status' => $row['status'] ?? 'aprobado',
                'reason' => $row['reason'] ?? null,
            ]);

            $imported++;
        }

        return $imported;
    }
}
