<?php

declare(strict_types=1);

class WorkCalendarService
{
    private ?array $calendarConfig = null;

    public function __construct(private ?Database $db = null)
    {
    }

    public function getCalendarConfig(): array
    {
        if ($this->calendarConfig !== null) {
            return $this->calendarConfig;
        }

        $timesheetRules = ((new ConfigService($this->db))->getConfig()['operational_rules']['timesheets'] ?? []);
        $rawCalendar = is_array($timesheetRules['work_calendar'] ?? null) ? $timesheetRules['work_calendar'] : [];
        $workingDays = $this->normalizeWeekdayList($rawCalendar['working_days'] ?? [1, 2, 3, 4, 5]);
        if ($workingDays === []) {
            $workingDays = [1, 2, 3, 4, 5];
        }

        $holidays = $this->normalizeHolidayRows($rawCalendar['holidays'] ?? []);
        $holidays = $this->mergeTableHolidays($holidays);
        $exceptions = $this->normalizeExceptionRows($rawCalendar['exceptions'] ?? []);

        $holidaysByDate = [];
        foreach ($holidays as $holiday) {
            $holidaysByDate[(string) $holiday['date']] = (string) $holiday['name'];
        }

        $exceptionsByDate = [];
        foreach ($exceptions as $exception) {
            $exceptionsByDate[(string) $exception['date']] = $exception;
        }

        $this->calendarConfig = [
            'country' => strtoupper(trim((string) ($rawCalendar['country'] ?? ''))),
            'working_days' => $workingDays,
            'weekend_days' => array_values(array_diff(range(1, 7), $workingDays)),
            'holidays' => $holidays,
            'holidays_by_date' => $holidaysByDate,
            'exceptions' => $exceptions,
            'exceptions_by_date' => $exceptionsByDate,
            'allow_admin_holiday_logging' => $this->toBool($rawCalendar['allow_admin_holiday_logging'] ?? false),
            'allow_admin_non_working_logging' => $this->toBool($rawCalendar['allow_admin_non_working_logging'] ?? false),
        ];

        return $this->calendarConfig;
    }

    public function classifyDate(\DateTimeImmutable $date): array
    {
        $config = $this->getCalendarConfig();
        $dateKey = $date->format('Y-m-d');
        $weekday = (int) $date->format('N');

        if (isset($config['exceptions_by_date'][$dateKey])) {
            $exception = $config['exceptions_by_date'][$dateKey];
            $isWorking = (bool) ($exception['is_working'] ?? false);
            $name = trim((string) ($exception['name'] ?? ''));

            return [
                'date' => $dateKey,
                'weekday' => $weekday,
                'type' => $isWorking ? 'working' : 'non_working',
                'is_working' => $isWorking,
                'is_holiday' => false,
                'is_exception' => true,
                'name' => $name,
            ];
        }

        $holidayName = trim((string) ($config['holidays_by_date'][$dateKey] ?? ''));
        if ($holidayName !== '') {
            return [
                'date' => $dateKey,
                'weekday' => $weekday,
                'type' => 'holiday',
                'is_working' => false,
                'is_holiday' => true,
                'is_exception' => false,
                'name' => $holidayName,
            ];
        }

        $isWorking = in_array($weekday, $config['working_days'], true);

        return [
            'date' => $dateKey,
            'weekday' => $weekday,
            'type' => $isWorking ? 'working' : 'non_working',
            'is_working' => $isWorking,
            'is_holiday' => false,
            'is_exception' => false,
            'name' => '',
        ];
    }

    public function weekMap(\DateTimeImmutable $weekStart): array
    {
        $map = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->modify('+' . $i . ' days');
            $map[$day->format('Y-m-d')] = $this->classifyDate($day);
        }

        return $map;
    }

    public function weeklyCapacityForWeek(float $weeklyCapacity, \DateTimeImmutable $weekStart): float
    {
        $weeklyCapacity = max(0.0, $weeklyCapacity);
        if ($weeklyCapacity <= 0) {
            return 0.0;
        }

        $baselineWorkingDays = max(1, count($this->getCalendarConfig()['working_days']));
        $dailyCapacity = $weeklyCapacity / $baselineWorkingDays;
        $effectiveWorkingDays = 0;
        foreach ($this->weekMap($weekStart) as $dayMeta) {
            if (!empty($dayMeta['is_working'])) {
                $effectiveWorkingDays++;
            }
        }

        return round($dailyCapacity * $effectiveWorkingDays, 2);
    }

    public function equivalentWorkingWeeks(\DateTimeImmutable $start, \DateTimeImmutable $end): float
    {
        if ($start > $end) {
            return 0.0;
        }

        $baselineWorkingDays = max(1, count($this->getCalendarConfig()['working_days']));
        $effectiveWorkingDays = 0;
        $cursor = $start->setTime(0, 0);
        $endDate = $end->setTime(0, 0);

        while ($cursor <= $endDate) {
            $meta = $this->classifyDate($cursor);
            if (!empty($meta['is_working'])) {
                $effectiveWorkingDays++;
            }
            $cursor = $cursor->modify('+1 day');
        }

        return round($effectiveWorkingDays / $baselineWorkingDays, 4);
    }

    public function allowAdminHolidayLogging(): bool
    {
        return (bool) ($this->getCalendarConfig()['allow_admin_holiday_logging'] ?? false);
    }

    public function allowAdminNonWorkingLogging(): bool
    {
        return (bool) ($this->getCalendarConfig()['allow_admin_non_working_logging'] ?? false);
    }

    private function mergeTableHolidays(array $holidays): array
    {
        if ($this->db === null || !$this->db->tableExists('calendar_holidays')) {
            return $holidays;
        }

        $existingDates = [];
        foreach ($holidays as $h) {
            $existingDates[(string) $h['date']] = true;
        }

        $rows = $this->db->fetchAll(
            'SELECT date, name FROM calendar_holidays WHERE active = 1 ORDER BY date ASC'
        );

        foreach ($rows as $row) {
            $date = (string) ($row['date'] ?? '');
            if ($date === '' || isset($existingDates[$date])) {
                continue;
            }
            $holidays[] = [
                'date' => $date,
                'name' => trim((string) ($row['name'] ?? 'Festivo')),
            ];
        }

        usort($holidays, fn(array $a, array $b) => strcmp((string) $a['date'], (string) $b['date']));

        return $holidays;
    }

    private function normalizeWeekdayList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $days = [];
        foreach ($value as $item) {
            $day = (int) $item;
            if ($day < 1 || $day > 7) {
                continue;
            }
            $days[$day] = $day;
        }
        ksort($days);

        return array_values($days);
    }

    private function normalizeHolidayRows(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $date = trim((string) ($item['date'] ?? ''));
            if (!$this->isIsoDate($date)) {
                continue;
            }
            $name = trim((string) ($item['name'] ?? $item['label'] ?? $item['title'] ?? 'Festivo'));
            if ($name === '') {
                $name = 'Festivo';
            }
            $rows[$date] = ['date' => $date, 'name' => $name];
        }
        ksort($rows);

        return array_values($rows);
    }

    private function normalizeExceptionRows(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $date = trim((string) ($item['date'] ?? ''));
            if (!$this->isIsoDate($date)) {
                continue;
            }
            $isWorking = $this->toBool($item['is_working'] ?? false);
            $name = trim((string) ($item['name'] ?? $item['label'] ?? $item['reason'] ?? ''));
            $rows[$date] = [
                'date' => $date,
                'is_working' => $isWorking,
                'name' => $name,
            ];
        }
        ksort($rows);

        return array_values($rows);
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on', 'si'], true);
    }

    private function isIsoDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        try {
            new \DateTimeImmutable($date);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
