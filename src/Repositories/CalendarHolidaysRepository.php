<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;

class CalendarHolidaysRepository
{
    public function __construct(private Database $db)
    {
    }

    public function tableExists(): bool
    {
        return $this->db->tableExists('calendar_holidays');
    }

    /**
     * @return array<string, string> Map of date (Y-m-d) => holiday name
     */
    public function getHolidaysForDateRange(string $startDate, string $endDate): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $rows = $this->db->fetchAll(
            'SELECT holiday_date, name
             FROM calendar_holidays
             WHERE holiday_date BETWEEN :start AND :end
             ORDER BY holiday_date ASC',
            [
                ':start' => $startDate,
                ':end' => $endDate,
            ]
        );

        $map = [];
        foreach ($rows as $row) {
            $date = (string) ($row['holiday_date'] ?? '');
            $name = trim((string) ($row['name'] ?? ''));
            if ($date !== '' && $name !== '') {
                $map[$date] = $name;
            }
        }

        return $map;
    }

    public function getHolidayForDate(string $date): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }

        $row = $this->db->fetchOne(
            'SELECT id, holiday_date, name
             FROM calendar_holidays
             WHERE holiday_date = :date
             LIMIT 1',
            [':date' => $date]
        );

        return $row ?: null;
    }

    public function isHoliday(string $date): bool
    {
        return $this->getHolidayForDate($date) !== null;
    }

    public function listAll(?int $year = null): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $sql = 'SELECT id, holiday_date, name, created_at
                FROM calendar_holidays';
        $params = [];

        if ($year !== null) {
            $sql .= ' WHERE holiday_date BETWEEN :start AND :end';
            $params[':start'] = $year . '-01-01';
            $params[':end'] = $year . '-12-31';
        }

        $sql .= ' ORDER BY holiday_date ASC';

        return $this->db->fetchAll($sql, $params);
    }

    public function create(string $date, string $name): int
    {
        if (!$this->tableExists()) {
            throw new \RuntimeException('Tabla calendar_holidays no existe.');
        }

        $this->db->execute(
            'INSERT INTO calendar_holidays (holiday_date, name)
             VALUES (:date, :name)
             ON DUPLICATE KEY UPDATE name = VALUES(name)',
            [
                ':date' => $date,
                ':name' => $name,
            ]
        );

        $row = $this->db->fetchOne('SELECT id FROM calendar_holidays WHERE holiday_date = :date LIMIT 1', [':date' => $date]);

        return (int) ($row['id'] ?? 0);
    }

    public function update(int $id, string $date, string $name): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        $this->db->execute(
            'UPDATE calendar_holidays
             SET holiday_date = :date, name = :name
             WHERE id = :id',
            [
                ':id' => $id,
                ':date' => $date,
                ':name' => $name,
            ]
        );

        return true;
    }

    public function delete(int $id): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        $this->db->execute('DELETE FROM calendar_holidays WHERE id = :id', [':id' => $id]);

        return true;
    }
}
