<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;
use DateTimeImmutable;

class WorkCalendarRepository
{
    public function __construct(private Database $db)
    {
    }

    public function getWorkingDaysConfig(): array
    {
        $defaults = [
            1 => true, 2 => true, 3 => true, 4 => true, 5 => true,
            6 => false, 7 => false,
        ];

        if (!$this->db->tableExists('config_settings')) {
            return ['working_days' => $defaults, 'default_daily_hours' => 8, 'admin_can_override_holidays' => true];
        }

        $row = $this->db->fetchOne(
            'SELECT config_value FROM config_settings WHERE config_key = :key LIMIT 1',
            [':key' => 'work_calendar']
        );

        if (!$row || !isset($row['config_value'])) {
            return ['working_days' => $defaults, 'default_daily_hours' => 8, 'admin_can_override_holidays' => true];
        }

        $decoded = json_decode((string) $row['config_value'], true);
        if (!is_array($decoded)) {
            return ['working_days' => $defaults, 'default_daily_hours' => 8, 'admin_can_override_holidays' => true];
        }

        $workingDays = $defaults;
        if (isset($decoded['working_days']) && is_array($decoded['working_days'])) {
            foreach ($decoded['working_days'] as $day => $active) {
                $dayInt = (int) $day;
                if ($dayInt >= 1 && $dayInt <= 7) {
                    $workingDays[$dayInt] = (bool) $active;
                }
            }
        }

        return [
            'working_days' => $workingDays,
            'default_daily_hours' => (float) ($decoded['default_daily_hours'] ?? 8),
            'admin_can_override_holidays' => (bool) ($decoded['admin_can_override_holidays'] ?? true),
        ];
    }

    public function saveWorkingDaysConfig(array $config): void
    {
        $payload = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $this->db->execute(
            'INSERT INTO config_settings (config_key, config_value, updated_at)
             VALUES (:key, :value, NOW())
             ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()',
            [':key' => 'work_calendar', ':value' => $payload]
        );
    }

    public function listHolidays(bool $onlyActive = true, ?int $year = null): array
    {
        if (!$this->db->tableExists('calendar_holidays')) {
            return [];
        }

        $sql = 'SELECT * FROM calendar_holidays';
        $params = [];
        $conditions = [];

        if ($onlyActive) {
            $conditions[] = 'active = 1';
        }

        if ($year !== null) {
            $conditions[] = '(YEAR(holiday_date) = :year OR recurring = 1)';
            $params[':year'] = $year;
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY holiday_date ASC';

        return $this->db->fetchAll($sql, $params);
    }

    public function findHoliday(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM calendar_holidays WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    public function findHolidayByDate(string $date): ?array
    {
        if (!$this->db->tableExists('calendar_holidays')) {
            return null;
        }

        $dateObj = new DateTimeImmutable($date);
        $monthDay = $dateObj->format('m-d');

        $result = $this->db->fetchOne(
            'SELECT * FROM calendar_holidays
             WHERE active = 1
               AND (holiday_date = :exact_date OR (recurring = 1 AND DATE_FORMAT(holiday_date, "%m-%d") = :month_day))
             ORDER BY (holiday_date = :exact_date2) DESC
             LIMIT 1',
            [
                ':exact_date' => $date,
                ':month_day' => $monthDay,
                ':exact_date2' => $date,
            ]
        );

        return $result ?: null;
    }

    public function getHolidaysForRange(string $startDate, string $endDate): array
    {
        if (!$this->db->tableExists('calendar_holidays')) {
            return [];
        }

        $startObj = new DateTimeImmutable($startDate);
        $endObj = new DateTimeImmutable($endDate);
        $startMonthDay = $startObj->format('m-d');
        $endMonthDay = $endObj->format('m-d');

        $rows = $this->db->fetchAll(
            'SELECT * FROM calendar_holidays
             WHERE active = 1
               AND (
                   (recurring = 0 AND holiday_date BETWEEN :start AND :end)
                   OR (recurring = 1)
               )
             ORDER BY holiday_date ASC',
            [':start' => $startDate, ':end' => $endDate]
        );

        $holidays = [];
        foreach ($rows as $row) {
            if ((int) $row['recurring'] === 1) {
                $cursor = clone $startObj;
                while ($cursor <= $endObj) {
                    $monthDay = $cursor->format('m-d');
                    $holidayMonthDay = (new DateTimeImmutable($row['holiday_date']))->format('m-d');
                    if ($monthDay === $holidayMonthDay) {
                        $holidays[$cursor->format('Y-m-d')] = $row;
                    }
                    $cursor = $cursor->modify('+1 day');
                }
            } else {
                $holidays[$row['holiday_date']] = $row;
            }
        }

        return $holidays;
    }

    public function createHoliday(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO calendar_holidays (holiday_date, name, description, recurring, active, created_by)
             VALUES (:holiday_date, :name, :description, :recurring, :active, :created_by)',
            [
                ':holiday_date' => $data['holiday_date'],
                ':name' => $data['name'],
                ':description' => $data['description'] ?? null,
                ':recurring' => (int) ($data['recurring'] ?? 0),
                ':active' => (int) ($data['active'] ?? 1),
                ':created_by' => $data['created_by'] ?? null,
            ]
        );
    }

    public function updateHoliday(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE calendar_holidays
             SET holiday_date = :holiday_date, name = :name, description = :description,
                 recurring = :recurring, active = :active
             WHERE id = :id',
            [
                ':id' => $id,
                ':holiday_date' => $data['holiday_date'],
                ':name' => $data['name'],
                ':description' => $data['description'] ?? null,
                ':recurring' => (int) ($data['recurring'] ?? 0),
                ':active' => (int) ($data['active'] ?? 1),
            ]
        );
    }

    public function deleteHoliday(int $id): void
    {
        $this->db->execute(
            'DELETE FROM calendar_holidays WHERE id = :id',
            [':id' => $id]
        );
    }

    public function isWorkingDay(string $date): bool
    {
        $config = $this->getWorkingDaysConfig();
        $dateObj = new DateTimeImmutable($date);
        $dayOfWeek = (int) $dateObj->format('N');

        if (!($config['working_days'][$dayOfWeek] ?? false)) {
            return false;
        }

        $holiday = $this->findHolidayByDate($date);
        return $holiday === null;
    }

    public function getDayInfo(string $date): array
    {
        $config = $this->getWorkingDaysConfig();
        $dateObj = new DateTimeImmutable($date);
        $dayOfWeek = (int) $dateObj->format('N');
        $isConfiguredWorking = (bool) ($config['working_days'][$dayOfWeek] ?? false);
        $holiday = $this->findHolidayByDate($date);

        if ($holiday !== null) {
            return [
                'type' => 'holiday',
                'is_working' => false,
                'holiday_name' => $holiday['name'],
                'holiday_description' => $holiday['description'] ?? '',
            ];
        }

        if (!$isConfiguredWorking) {
            return [
                'type' => 'non_working',
                'is_working' => false,
                'holiday_name' => null,
                'holiday_description' => null,
            ];
        }

        return [
            'type' => 'working',
            'is_working' => true,
            'holiday_name' => null,
            'holiday_description' => null,
        ];
    }

    public function countBusinessDays(string $startDate, string $endDate): int
    {
        $config = $this->getWorkingDaysConfig();
        $holidays = $this->getHolidaysForRange($startDate, $endDate);
        $count = 0;
        $cursor = new DateTimeImmutable($startDate);
        $limit = new DateTimeImmutable($endDate);

        while ($cursor <= $limit) {
            $dayOfWeek = (int) $cursor->format('N');
            $dateStr = $cursor->format('Y-m-d');

            if (($config['working_days'][$dayOfWeek] ?? false) && !isset($holidays[$dateStr])) {
                $count++;
            }

            $cursor = $cursor->modify('+1 day');
        }

        return $count;
    }

    public function getWeeklyCapacityHours(): float
    {
        $config = $this->getWorkingDaysConfig();
        $workingDaysCount = 0;

        foreach ($config['working_days'] as $isWorking) {
            if ($isWorking) {
                $workingDaysCount++;
            }
        }

        return $workingDaysCount * $config['default_daily_hours'];
    }

    public function getWeekCapacityForRange(string $startDate, string $endDate): float
    {
        $config = $this->getWorkingDaysConfig();
        $businessDays = $this->countBusinessDays($startDate, $endDate);

        return $businessDays * $config['default_daily_hours'];
    }
}
