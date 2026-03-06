<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;
use DateTimeImmutable;

class AbsencesRepository
{
    public const ABSENCE_TYPES = [
        'vacaciones'       => 'Vacaciones',
        'incapacidad'      => 'Incapacidad',
        'permiso_personal' => 'Permiso personal',
        'permiso_medico'   => 'Permiso médico',
        'capacitacion'     => 'Capacitación',
        'licencia'         => 'Licencia',
        'festivo'          => 'Festivo',
    ];

    public const TYPE_COLORS = [
        'vacaciones'       => '#3b82f6',
        'incapacidad'      => '#ef4444',
        'permiso_personal' => '#8b5cf6',
        'permiso_medico'   => '#f59e0b',
        'capacitacion'     => '#06b6d4',
        'licencia'         => '#14b8a6',
        'festivo'          => '#6366f1',
    ];

    public function __construct(private Database $db)
    {
    }

    public function listForTalent(int $talentId, ?string $from = null, ?string $to = null): array
    {
        $params = [':talent_id' => $talentId];
        $where = 'WHERE a.talent_id = :talent_id';
        if ($from !== null) {
            $where .= ' AND a.date_to >= :from';
            $params[':from'] = $from;
        }
        if ($to !== null) {
            $where .= ' AND a.date_from <= :to';
            $params[':to'] = $to;
        }

        return $this->db->fetchAll(
            'SELECT a.*, u.name AS created_by_name, ua.name AS approved_by_name
             FROM talent_absences a
             LEFT JOIN users u ON u.id = a.created_by
             LEFT JOIN users ua ON ua.id = a.approved_by
             ' . $where . '
             ORDER BY a.date_from DESC',
            $params
        );
    }

    public function listAll(?string $status = null, ?string $from = null, ?string $to = null): array
    {
        $params = [];
        $where = 'WHERE 1=1';
        if ($status !== null && $status !== '') {
            $where .= ' AND a.status = :status';
            $params[':status'] = $status;
        }
        if ($from !== null) {
            $where .= ' AND a.date_to >= :from';
            $params[':from'] = $from;
        }
        if ($to !== null) {
            $where .= ' AND a.date_from <= :to';
            $params[':to'] = $to;
        }

        return $this->db->fetchAll(
            'SELECT a.*, t.name AS talent_name, u.name AS created_by_name, ua.name AS approved_by_name
             FROM talent_absences a
             JOIN talents t ON t.id = a.talent_id
             LEFT JOIN users u ON u.id = a.created_by
             LEFT JOIN users ua ON ua.id = a.approved_by
             ' . $where . '
             ORDER BY a.date_from DESC',
            $params
        );
    }

    /**
     * Returns approved absences for one or multiple talents within a date range.
     * Result keyed by talent_id → array of absence rows.
     */
    public function approvedForTalents(array $talentIds, string $from, string $to): array
    {
        if (empty($talentIds)) {
            return [];
        }

        $ids = implode(',', array_map('intval', $talentIds));
        $rows = $this->db->fetchAll(
            'SELECT * FROM talent_absences
             WHERE talent_id IN (' . $ids . ')
               AND status = "aprobado"
               AND date_from <= :to
               AND date_to >= :from
             ORDER BY talent_id, date_from ASC',
            [':from' => $from, ':to' => $to]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['talent_id']][] = $row;
        }

        return $result;
    }

    /**
     * Returns approved absences for a single talent within a date range (flat array).
     */
    public function approvedForTalent(int $talentId, string $from, string $to): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM talent_absences
             WHERE talent_id = :talent_id
               AND status = "aprobado"
               AND date_from <= :to
               AND date_to >= :from
             ORDER BY date_from ASC',
            [':talent_id' => $talentId, ':from' => $from, ':to' => $to]
        );
    }

    /**
     * Check if a specific date is covered by an approved absence (vacation only) for a user.
     */
    public function hasVacationOnDate(int $userId, string $date): bool
    {
        $talentRow = $this->db->fetchOne(
            'SELECT id FROM talents WHERE user_id = :uid LIMIT 1',
            [':uid' => $userId]
        );
        if (!$talentRow) {
            return false;
        }

        $row = $this->db->fetchOne(
            'SELECT id FROM talent_absences
             WHERE talent_id = :tid
               AND status = "aprobado"
               AND type = "vacaciones"
               AND date_from <= :date
               AND date_to >= :date
             LIMIT 1',
            [':tid' => (int) $talentRow['id'], ':date' => $date]
        );

        return $row !== false;
    }

    /**
     * Returns absence info for a specific date for a talent (any approved absence).
     */
    public function absenceOnDate(int $talentId, string $date): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM talent_absences
             WHERE talent_id = :tid
               AND status = "aprobado"
               AND date_from <= :date
               AND date_to >= :date
             LIMIT 1',
            [':tid' => $talentId, ':date' => $date]
        );

        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT a.*, t.name AS talent_name, u.name AS created_by_name
             FROM talent_absences a
             JOIN talents t ON t.id = a.talent_id
             LEFT JOIN users u ON u.id = a.created_by
             WHERE a.id = :id LIMIT 1',
            [':id' => $id]
        );

        return $row ?: null;
    }

    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO talent_absences
                (talent_id, user_id, type, date_from, date_to, hours_per_day, reason, status, created_by, created_at, updated_at)
             VALUES
                (:talent_id, :user_id, :type, :date_from, :date_to, :hours_per_day, :reason, :status, :created_by, NOW(), NOW())',
            [
                ':talent_id'    => (int) $data['talent_id'],
                ':user_id'      => (int) $data['user_id'],
                ':type'         => (string) $data['type'],
                ':date_from'    => (string) $data['date_from'],
                ':date_to'      => (string) $data['date_to'],
                ':hours_per_day' => isset($data['hours_per_day']) && $data['hours_per_day'] !== '' ? (float) $data['hours_per_day'] : null,
                ':reason'       => isset($data['reason']) ? trim((string) $data['reason']) : null,
                ':status'       => (string) ($data['status'] ?? 'pendiente'),
                ':created_by'   => isset($data['created_by']) ? (int) $data['created_by'] : null,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function approve(int $id, int $approvedBy): void
    {
        $this->db->execute(
            'UPDATE talent_absences
             SET status = "aprobado", approved_by = :by, approved_at = NOW(), updated_at = NOW()
             WHERE id = :id',
            [':by' => $approvedBy, ':id' => $id]
        );
    }

    public function reject(int $id, int $rejectedBy, string $reason = ''): void
    {
        $this->db->execute(
            'UPDATE talent_absences
             SET status = "rechazado", approved_by = :by, approved_at = NOW(),
                 rejection_reason = :reason, updated_at = NOW()
             WHERE id = :id',
            [':by' => $rejectedBy, ':id' => $id, ':reason' => $reason]
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM talent_absences WHERE id = :id', [':id' => $id]);
    }

    /**
     * Compute absence breakdown for a given talent over a week.
     * Returns total absent hours and a breakdown by type.
     * $dailyCapacity: hours per working day for this talent
     */
    public function computeWeekBreakdown(
        int $talentId,
        DateTimeImmutable $weekStart,
        float $dailyCapacity,
        array $workingDayNumbers
    ): array {
        $weekEnd = $weekStart->modify('+6 days');
        $absences = $this->approvedForTalent(
            $talentId,
            $weekStart->format('Y-m-d'),
            $weekEnd->format('Y-m-d')
        );

        $totalAbsentHours = 0.0;
        $breakdown = [];
        $absenceDays = [];

        $cursor = $weekStart;
        $limit = $weekEnd;
        while ($cursor <= $limit) {
            $dateKey = $cursor->format('Y-m-d');
            $weekdayNum = (int) $cursor->format('N');
            if (in_array($weekdayNum, $workingDayNumbers, true)) {
                foreach ($absences as $absence) {
                    if ($absence['date_from'] <= $dateKey && $absence['date_to'] >= $dateKey) {
                        $absHours = $absence['hours_per_day'] !== null
                            ? (float) $absence['hours_per_day']
                            : $dailyCapacity;
                        $type = (string) $absence['type'];
                        $totalAbsentHours += $absHours;
                        $breakdown[$type] = ($breakdown[$type] ?? 0.0) + $absHours;
                        $absenceDays[$dateKey] = [
                            'type'       => $type,
                            'label'      => self::ABSENCE_TYPES[$type] ?? $type,
                            'color'      => self::TYPE_COLORS[$type] ?? '#6b7280',
                            'hours'      => $absHours,
                            'full_day'   => $absence['hours_per_day'] === null,
                            'absence_id' => (int) $absence['id'],
                        ];
                        break;
                    }
                }
            }
            $cursor = $cursor->modify('+1 day');
        }

        return [
            'total_absent_hours' => round($totalAbsentHours, 2),
            'breakdown'          => $breakdown,
            'absence_days'       => $absenceDays,
        ];
    }
}
