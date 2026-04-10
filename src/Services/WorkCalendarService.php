<?php

declare(strict_types=1);

class WorkCalendarService
{
    private ?array $calendarConfig = null;
    private array $colombiaHolidayCache = [];

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
        $country = strtoupper(trim((string) ($rawCalendar['country'] ?? '')));
        if ($country === '') {
            $country = 'CO';
        }
        $workingDays = $this->normalizeWeekdayList($rawCalendar['working_days'] ?? [1, 2, 3, 4, 5]);
        if ($workingDays === []) {
            $workingDays = [1, 2, 3, 4, 5];
        }

        $holidays = $this->normalizeHolidayRows($rawCalendar['holidays'] ?? []);
        $holidays = $this->mergeHolidayRows($holidays, $this->databaseHolidayRows());
        $additionalHolidays = $this->normalizeAdditionalHolidayRows($rawCalendar['additional_holidays'] ?? []);
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
            'country' => $country,
            'working_days' => $workingDays,
            'weekend_days' => array_values(array_diff(range(1, 7), $workingDays)),
            'holidays' => $holidays,
            'holidays_by_date' => $holidaysByDate,
            'additional_holidays' => $additionalHolidays,
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

        $holidayName = trim($this->holidayNameForDate($date));
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

    private function mergeHolidayRows(array $baseRows, array $extraRows): array
    {
        $rows = [];
        foreach ($baseRows as $row) {
            $date = trim((string) ($row['date'] ?? ''));
            if (!$this->isIsoDate($date)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? 'Festivo'));
            $rows[$date] = ['date' => $date, 'name' => $name !== '' ? $name : 'Festivo'];
        }
        foreach ($extraRows as $row) {
            $date = trim((string) ($row['date'] ?? ''));
            if (!$this->isIsoDate($date)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? 'Festivo'));
            $rows[$date] = ['date' => $date, 'name' => $name !== '' ? $name : 'Festivo'];
        }
        ksort($rows);

        return array_values($rows);
    }

    private function databaseHolidayRows(): array
    {
        if (!$this->db instanceof Database || !$this->db->tableExists('calendar_holidays')) {
            return [];
        }

        $hasHolidayDate = $this->db->columnExists('calendar_holidays', 'holiday_date');
        $hasDate = $this->db->columnExists('calendar_holidays', 'date');
        if (!$hasHolidayDate && !$hasDate) {
            return [];
        }
        $dateColumn = $hasHolidayDate ? 'holiday_date' : 'date';
        $nameColumn = $this->db->columnExists('calendar_holidays', 'name') ? 'name' : 'NULL';
        $where = '1=1';
        if ($this->db->columnExists('calendar_holidays', 'is_active')) {
            $where = 'is_active = 1';
        }

        $rows = $this->db->fetchAll(
            'SELECT ' . $dateColumn . ' AS date, ' . $nameColumn . ' AS name
             FROM calendar_holidays
             WHERE ' . $where
        );

        return $this->normalizeHolidayRows($rows);
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

    private function normalizeAdditionalHolidayRows(mixed $value): array
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
            $name = trim((string) ($item['name'] ?? $item['reason'] ?? $item['label'] ?? 'Feriado adicional'));
            if ($name === '') {
                $name = 'Feriado adicional';
            }
            $scopeRaw = strtolower(trim((string) ($item['scope'] ?? 'year_specific')));
            $scope = in_array($scopeRaw, ['all_years', 'all', 'todos'], true) ? 'all_years' : 'year_specific';
            $year = (int) ($item['year'] ?? substr($date, 0, 4));
            if ($scope === 'all_years') {
                $year = 0;
            }

            $key = $date . '::' . $scope . '::' . $year . '::' . $name;
            $rows[$key] = [
                'date' => $date,
                'name' => $name,
                'scope' => $scope,
                'year' => $year > 0 ? $year : null,
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            $dateCompare = strcmp((string) ($left['date'] ?? ''), (string) ($right['date'] ?? ''));
            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        return array_values($rows);
    }

    private function holidayNameForDate(\DateTimeImmutable $date): string
    {
        $config = $this->getCalendarConfig();
        $dateKey = $date->format('Y-m-d');
        $holidayName = trim((string) ($config['holidays_by_date'][$dateKey] ?? ''));
        if ($holidayName !== '') {
            return $holidayName;
        }

        if ($this->shouldApplyColombianOfficialHolidays()) {
            $year = (int) $date->format('Y');
            $official = $this->colombiaHolidaysForYear($year);
            $holidayName = trim((string) ($official[$dateKey] ?? ''));
            if ($holidayName !== '') {
                return $holidayName;
            }
        }

        return $this->additionalHolidayNameForDate($date, $config['additional_holidays'] ?? []);
    }

    private function additionalHolidayNameForDate(\DateTimeImmutable $date, mixed $additionalRows): string
    {
        if (!is_array($additionalRows)) {
            return '';
        }

        $dateKey = $date->format('Y-m-d');
        $year = (int) $date->format('Y');
        $monthDay = $date->format('m-d');
        foreach ($additionalRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $scope = strtolower(trim((string) ($row['scope'] ?? 'year_specific')));
            $name = trim((string) ($row['name'] ?? ''));
            $rowDate = trim((string) ($row['date'] ?? ''));
            if ($name === '' || !$this->isIsoDate($rowDate)) {
                continue;
            }

            if ($scope === 'all_years') {
                if (substr($rowDate, 5, 5) === $monthDay) {
                    return $name;
                }
                continue;
            }

            $rowYear = (int) ($row['year'] ?? substr($rowDate, 0, 4));
            if ($rowYear === $year && $rowDate === $dateKey) {
                return $name;
            }
        }

        return '';
    }

    private function shouldApplyColombianOfficialHolidays(): bool
    {
        $country = strtoupper(trim((string) ($this->getCalendarConfig()['country'] ?? 'CO')));
        return in_array($country, ['CO', 'COL', 'COLOMBIA'], true);
    }

    private function colombiaHolidaysForYear(int $year): array
    {
        if (isset($this->colombiaHolidayCache[$year])) {
            return $this->colombiaHolidayCache[$year];
        }

        $holidays = [];
        $register = static function (array &$target, \DateTimeImmutable $date, string $name): void {
            $target[$date->format('Y-m-d')] = $name;
        };
        $withMondayLaw = static function (\DateTimeImmutable $date): \DateTimeImmutable {
            $weekday = (int) $date->format('N');
            if ($weekday === 1) {
                return $date;
            }
            return $date->modify('next monday');
        };

        $register($holidays, new \DateTimeImmutable(sprintf('%04d-01-01', $year)), 'Año Nuevo');
        $register($holidays, new \DateTimeImmutable(sprintf('%04d-05-01', $year)), 'Día del Trabajo');
        $register($holidays, new \DateTimeImmutable(sprintf('%04d-07-20', $year)), 'Día de la Independencia');
        $register($holidays, new \DateTimeImmutable(sprintf('%04d-08-07', $year)), 'Batalla de Boyacá');
        $register($holidays, new \DateTimeImmutable(sprintf('%04d-12-08', $year)), 'Inmaculada Concepción');
        $register($holidays, new \DateTimeImmutable(sprintf('%04d-12-25', $year)), 'Navidad');

        $register($holidays, $withMondayLaw(new \DateTimeImmutable(sprintf('%04d-01-06', $year))), 'Reyes Magos');
        $register($holidays, $withMondayLaw(new \DateTimeImmutable(sprintf('%04d-03-19', $year))), 'San José');
        $register($holidays, $withMondayLaw(new \DateTimeImmutable(sprintf('%04d-06-29', $year))), 'San Pedro y San Pablo');
        $register($holidays, $withMondayLaw(new \DateTimeImmutable(sprintf('%04d-08-15', $year))), 'Asunción de la Virgen');
        $register($holidays, $withMondayLaw(new \DateTimeImmutable(sprintf('%04d-10-12', $year))), 'Día de la Raza');
        $register($holidays, $withMondayLaw(new \DateTimeImmutable(sprintf('%04d-11-01', $year))), 'Todos los Santos');
        $register($holidays, $withMondayLaw(new \DateTimeImmutable(sprintf('%04d-11-11', $year))), 'Independencia de Cartagena');

        $easterSunday = $this->easterSunday($year);
        $register($holidays, $easterSunday->modify('-3 days'), 'Jueves Santo');
        $register($holidays, $easterSunday->modify('-2 days'), 'Viernes Santo');
        $register($holidays, $withMondayLaw($easterSunday->modify('+40 days')), 'Ascensión del Señor');
        $register($holidays, $withMondayLaw($easterSunday->modify('+60 days')), 'Corpus Christi');
        $register($holidays, $withMondayLaw($easterSunday->modify('+68 days')), 'Sagrado Corazón');

        $this->colombiaHolidayCache[$year] = $holidays;

        return $holidays;
    }

    private function easterSunday(int $year): \DateTimeImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
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
