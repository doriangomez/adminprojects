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

    /**
     * Computes real weekly capacity after subtracting absences.
     * Returns a detailed breakdown including absent hours by type.
     *
     * @param float              $weeklyCapacity  Base weekly hours (e.g. 40)
     * @param \DateTimeImmutable $weekStart       Monday of the week
     * @param array              $absences        Rows from talent_absences (approved, overlapping with week)
     * @return array{
     *   base_capacity: float,
     *   holiday_hours: float,
     *   absent_hours: float,
     *   real_capacity: float,
     *   daily_capacity: float,
     *   breakdown: array<string,float>,
     *   absence_days: array<string,array>
     * }
     */
    public function weeklyCapacityWithAbsences(
        float $weeklyCapacity,
        \DateTimeImmutable $weekStart,
        array $absences = []
    ): array {
        $weeklyCapacity = max(0.0, $weeklyCapacity);
        $config = $this->getCalendarConfig();
        $baselineWorkingDays = max(1, count($config['working_days']));
        $dailyCapacity = $weeklyCapacity > 0 ? $weeklyCapacity / $baselineWorkingDays : 0.0;

        $weekMap = $this->weekMap($weekStart);
        $weekEnd = $weekStart->modify('+6 days');

        $holidayHours = 0.0;
        $absentHours = 0.0;
        $breakdown = [];
        $absenceDays = [];

        foreach ($weekMap as $dateKey => $dayMeta) {
            if (!in_array((int) $dayMeta['weekday'], $config['working_days'], true)) {
                continue;
            }

            if (!empty($dayMeta['is_holiday'])) {
                $holidayHours += $dailyCapacity;
                $absenceDays[$dateKey] = [
                    'type'     => 'festivo',
                    'label'    => (string) ($dayMeta['name'] ?: 'Festivo'),
                    'color'    => '#6366f1',
                    'hours'    => $dailyCapacity,
                    'full_day' => true,
                ];
                $breakdown['festivo'] = ($breakdown['festivo'] ?? 0.0) + $dailyCapacity;
                continue;
            }

            if (!empty($dayMeta['is_exception']) && !$dayMeta['is_working']) {
                $holidayHours += $dailyCapacity;
                $absenceDays[$dateKey] = [
                    'type'     => 'festivo',
                    'label'    => (string) ($dayMeta['name'] ?: 'No laborable'),
                    'color'    => '#6366f1',
                    'hours'    => $dailyCapacity,
                    'full_day' => true,
                ];
                $breakdown['festivo'] = ($breakdown['festivo'] ?? 0.0) + $dailyCapacity;
                continue;
            }

            foreach ($absences as $absence) {
                if ((string) $absence['date_from'] <= $dateKey && (string) $absence['date_to'] >= $dateKey) {
                    $absHours = ($absence['hours_per_day'] !== null && $absence['hours_per_day'] !== '')
                        ? (float) $absence['hours_per_day']
                        : $dailyCapacity;
                    $type = (string) $absence['type'];
                    $typeLabels = \App\Repositories\AbsencesRepository::ABSENCE_TYPES;
                    $typeColors = \App\Repositories\AbsencesRepository::TYPE_COLORS;
                    $absentHours += $absHours;
                    $breakdown[$type] = ($breakdown[$type] ?? 0.0) + $absHours;
                    $absenceDays[$dateKey] = [
                        'type'       => $type,
                        'label'      => $typeLabels[$type] ?? $type,
                        'color'      => $typeColors[$type] ?? '#6b7280',
                        'hours'      => $absHours,
                        'full_day'   => $absence['hours_per_day'] === null || $absence['hours_per_day'] === '',
                        'absence_id' => (int) $absence['id'],
                    ];
                    break;
                }
            }
        }

        $baseAfterHolidays = max(0.0, $weeklyCapacity - $holidayHours);
        $realCapacity = max(0.0, $baseAfterHolidays - $absentHours);

        return [
            'base_capacity'  => round($weeklyCapacity, 2),
            'holiday_hours'  => round($holidayHours, 2),
            'absent_hours'   => round($absentHours, 2),
            'real_capacity'  => round($realCapacity, 2),
            'daily_capacity' => round($dailyCapacity, 2),
            'breakdown'      => $breakdown,
            'absence_days'   => $absenceDays,
        ];
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
