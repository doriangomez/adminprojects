<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;

class CalendarRepository
{
    public function __construct(private Database $db)
    {
    }

    public function listAll(): array
    {
        if (!$this->db->tableExists('calendar_holidays')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT id, holiday_date, name, type, recurring
             FROM calendar_holidays
             ORDER BY holiday_date ASC, name ASC'
        );
    }

    public function listForYear(int $year): array
    {
        if (!$this->db->tableExists('calendar_holidays')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT id, holiday_date, name, type, recurring
             FROM calendar_holidays
             WHERE YEAR(holiday_date) = :year OR recurring = 1
             ORDER BY holiday_date ASC',
            [':year' => $year]
        );
    }

    public function holidaysForRange(string $dateFrom, string $dateTo): array
    {
        if (!$this->db->tableExists('calendar_holidays')) {
            return [];
        }

        $result = [];

        $exactRows = $this->db->fetchAll(
            'SELECT id, holiday_date, name, type, recurring
             FROM calendar_holidays
             WHERE holiday_date BETWEEN :from AND :to AND recurring = 0',
            [':from' => $dateFrom, ':to' => $dateTo]
        );

        foreach ($exactRows as $row) {
            $date = (string) ($row['holiday_date'] ?? '');
            if ($date !== '' && !isset($result[$date])) {
                $result[$date] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'date' => $date,
                    'name' => (string) ($row['name'] ?? ''),
                    'type' => (string) ($row['type'] ?? 'holiday'),
                    'recurring' => false,
                ];
            }
        }

        $recurringRows = $this->db->fetchAll(
            'SELECT id, holiday_date, name, type FROM calendar_holidays WHERE recurring = 1'
        );

        $fromTs = strtotime($dateFrom);
        $toTs = strtotime($dateTo);
        $fromYear = (int) date('Y', $fromTs);
        $toYear = (int) date('Y', $toTs);

        foreach ($recurringRows as $row) {
            $origDate = (string) ($row['holiday_date'] ?? '');
            if ($origDate === '') {
                continue;
            }
            $mmdd = substr($origDate, 5);
            foreach (range($fromYear, $toYear) as $year) {
                $effectiveDate = $year . '-' . $mmdd;
                $ts = strtotime($effectiveDate);
                if ($ts !== false && $ts >= $fromTs && $ts <= $toTs && !isset($result[$effectiveDate])) {
                    $result[$effectiveDate] = [
                        'id' => (int) ($row['id'] ?? 0),
                        'date' => $effectiveDate,
                        'name' => (string) ($row['name'] ?? ''),
                        'type' => (string) ($row['type'] ?? 'holiday'),
                        'recurring' => true,
                    ];
                }
            }
        }

        return $result;
    }

    public function findByDate(string $date): ?array
    {
        if (!$this->db->tableExists('calendar_holidays')) {
            return null;
        }

        $exact = $this->db->fetchOne(
            'SELECT id, holiday_date, name, type, recurring
             FROM calendar_holidays
             WHERE holiday_date = :date AND recurring = 0
             LIMIT 1',
            [':date' => $date]
        );

        if ($exact) {
            return $exact;
        }

        $mmdd = substr($date, 5);
        $recurring = $this->db->fetchOne(
            'SELECT id, holiday_date, name, type, recurring
             FROM calendar_holidays
             WHERE recurring = 1 AND DATE_FORMAT(holiday_date, \'%m-%d\') = :mmdd
             LIMIT 1',
            [':mmdd' => $mmdd]
        );

        return $recurring ?: null;
    }

    public function findById(int $id): ?array
    {
        if (!$this->db->tableExists('calendar_holidays')) {
            return null;
        }

        $row = $this->db->fetchOne(
            'SELECT id, holiday_date, name, type, recurring FROM calendar_holidays WHERE id = :id LIMIT 1',
            [':id' => $id]
        );

        return $row ?: null;
    }

    public function create(string $date, string $name, string $type = 'holiday', bool $recurring = false): int
    {
        $this->db->execute(
            'INSERT INTO calendar_holidays (holiday_date, name, type, recurring)
             VALUES (:date, :name, :type, :recurring)
             ON DUPLICATE KEY UPDATE name = VALUES(name), type = VALUES(type), recurring = VALUES(recurring)',
            [
                ':date' => $date,
                ':name' => $name,
                ':type' => in_array($type, ['holiday', 'exception'], true) ? $type : 'holiday',
                ':recurring' => $recurring ? 1 : 0,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $date, string $name, string $type = 'holiday', bool $recurring = false): bool
    {
        $affected = $this->db->execute(
            'UPDATE calendar_holidays
             SET holiday_date = :date, name = :name, type = :type, recurring = :recurring
             WHERE id = :id',
            [
                ':id' => $id,
                ':date' => $date,
                ':name' => $name,
                ':type' => in_array($type, ['holiday', 'exception'], true) ? $type : 'holiday',
                ':recurring' => $recurring ? 1 : 0,
            ]
        );

        return $affected > 0;
    }

    public function delete(int $id): bool
    {
        $affected = $this->db->execute(
            'DELETE FROM calendar_holidays WHERE id = :id',
            [':id' => $id]
        );

        return $affected > 0;
    }
}
