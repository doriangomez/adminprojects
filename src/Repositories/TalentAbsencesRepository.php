<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;

class TalentAbsencesRepository
{
    public function __construct(private Database $db)
    {
    }

    public function all(): array
    {
        if (!$this->db->tableExists('talent_absences')) {
            return [];
        }

        $approvedBySelect = $this->db->columnExists('talent_absences', 'approved_by')
            ? 'approver.name AS approved_by_name'
            : 'NULL AS approved_by_name';
        $creatorSelect = $this->db->columnExists('talent_absences', 'user_id')
            ? 'creator.name AS created_by_name'
            : 'NULL AS created_by_name';
        $statusSelect = $this->db->columnExists('talent_absences', 'status')
            ? 'ta.status'
            : '"pendiente" AS status';
        $hoursSelect = $this->db->columnExists('talent_absences', 'hours')
            ? 'ta.hours'
            : 'NULL AS hours';
        $isFullDaySelect = $this->db->columnExists('talent_absences', 'is_full_day')
            ? 'ta.is_full_day'
            : '1 AS is_full_day';
        $reasonSelect = $this->db->columnExists('talent_absences', 'reason')
            ? 'ta.reason'
            : 'NULL AS reason';

        return $this->db->fetchAll(
            'SELECT
                ta.id,
                ta.talent_id,
                ta.absence_type,
                ta.start_date,
                ta.end_date,
                ' . $hoursSelect . ',
                ' . $isFullDaySelect . ',
                ' . $statusSelect . ',
                ' . $reasonSelect . ',
                ta.created_at,
                ta.updated_at,
                t.name AS talent_name,
                ' . $creatorSelect . ',
                ' . $approvedBySelect . '
             FROM talent_absences ta
             JOIN talents t ON t.id = ta.talent_id
             LEFT JOIN users creator ON creator.id = ta.user_id
             LEFT JOIN users approver ON approver.id = ta.approved_by
             ORDER BY ta.start_date DESC, ta.id DESC'
        );
    }

    public function talentsCatalog(): array
    {
        if (!$this->db->tableExists('talents')) {
            return [];
        }

        $where = [];
        if ($this->db->columnExists('talents', 'active')) {
            $where[] = 'COALESCE(t.active, 1) = 1';
        }
        $whereSql = $where === [] ? '' : ('WHERE ' . implode(' AND ', $where));

        return $this->db->fetchAll(
            'SELECT t.id, t.name
             FROM talents t
             ' . $whereSql . '
             ORDER BY t.name ASC'
        );
    }

    public function find(int $id): ?array
    {
        if ($id <= 0 || !$this->db->tableExists('talent_absences')) {
            return null;
        }

        $row = $this->db->fetchOne(
            'SELECT *
             FROM talent_absences
             WHERE id = :id
             LIMIT 1',
            [':id' => $id]
        );

        return $row ?: null;
    }

    public function create(array $payload): int
    {
        $columns = ['talent_id', 'absence_type', 'start_date', 'end_date'];
        $params = [
            ':talent_id' => (int) ($payload['talent_id'] ?? 0),
            ':absence_type' => (string) ($payload['absence_type'] ?? ''),
            ':start_date' => (string) ($payload['start_date'] ?? ''),
            ':end_date' => (string) ($payload['end_date'] ?? ''),
        ];

        $optionalColumns = [
            'user_id' => ':user_id',
            'hours' => ':hours',
            'is_full_day' => ':is_full_day',
            'status' => ':status',
            'reason' => ':reason',
            'approved_by' => ':approved_by',
        ];
        foreach ($optionalColumns as $column => $param) {
            if (!$this->db->columnExists('talent_absences', $column)) {
                continue;
            }
            $columns[] = $column;
            $params[$param] = match ($column) {
                'user_id', 'approved_by' => $payload[$column] ?? null,
                'hours' => $payload[$column] ?? null,
                'is_full_day' => (int) ($payload[$column] ?? 1),
                default => $payload[$column] ?? null,
            };
        }

        if ($this->db->columnExists('talent_absences', 'created_at')) {
            $columns[] = 'created_at';
        }
        if ($this->db->columnExists('talent_absences', 'updated_at')) {
            $columns[] = 'updated_at';
        }

        $values = [];
        foreach ($columns as $column) {
            if (in_array($column, ['created_at', 'updated_at'], true)) {
                $values[] = 'NOW()';
                continue;
            }
            $values[] = ':' . $column;
        }

        return $this->db->insert(
            'INSERT INTO talent_absences (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $values) . ')',
            $params
        );
    }

    public function update(int $id, array $payload): bool
    {
        if ($id <= 0 || !$this->db->tableExists('talent_absences')) {
            return false;
        }

        $set = [
            'talent_id = :talent_id',
            'absence_type = :absence_type',
            'start_date = :start_date',
            'end_date = :end_date',
        ];
        $params = [
            ':id' => $id,
            ':talent_id' => (int) ($payload['talent_id'] ?? 0),
            ':absence_type' => (string) ($payload['absence_type'] ?? ''),
            ':start_date' => (string) ($payload['start_date'] ?? ''),
            ':end_date' => (string) ($payload['end_date'] ?? ''),
        ];

        foreach (['hours', 'is_full_day', 'status', 'reason', 'approved_by'] as $column) {
            if (!$this->db->columnExists('talent_absences', $column)) {
                continue;
            }
            $set[] = $column . ' = :' . $column;
            $params[':' . $column] = match ($column) {
                'is_full_day' => (int) ($payload[$column] ?? 1),
                default => $payload[$column] ?? null,
            };
        }
        if ($this->db->columnExists('talent_absences', 'updated_at')) {
            $set[] = 'updated_at = NOW()';
        }

        $this->db->execute(
            'UPDATE talent_absences
             SET ' . implode(', ', $set) . '
             WHERE id = :id',
            $params
        );

        $row = $this->db->fetchOne('SELECT ROW_COUNT() AS total');
        return (int) ($row['total'] ?? 0) > 0;
    }

    public function delete(int $id): bool
    {
        if ($id <= 0 || !$this->db->tableExists('talent_absences')) {
            return false;
        }

        $this->db->execute(
            'DELETE FROM talent_absences WHERE id = :id',
            [':id' => $id]
        );

        $row = $this->db->fetchOne('SELECT ROW_COUNT() AS total');
        return (int) ($row['total'] ?? 0) > 0;
    }

    public function updateStatus(int $id, string $status, ?int $approvedBy): bool
    {
        if ($id <= 0 || !$this->db->tableExists('talent_absences')) {
            return false;
        }

        if (!$this->db->columnExists('talent_absences', 'status')) {
            return false;
        }

        $set = ['status = :status'];
        $params = [
            ':id' => $id,
            ':status' => $status,
        ];
        if ($this->db->columnExists('talent_absences', 'approved_by')) {
            $set[] = 'approved_by = :approved_by';
            $params[':approved_by'] = $approvedBy;
        }
        if ($this->db->columnExists('talent_absences', 'updated_at')) {
            $set[] = 'updated_at = NOW()';
        }

        $this->db->execute(
            'UPDATE talent_absences
             SET ' . implode(', ', $set) . '
             WHERE id = :id',
            $params
        );

        $row = $this->db->fetchOne('SELECT ROW_COUNT() AS total');
        return (int) ($row['total'] ?? 0) > 0;
    }
}
