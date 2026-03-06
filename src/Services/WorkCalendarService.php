<?php

declare(strict_types=1);

use App\Repositories\WorkCalendarRepository;

class WorkCalendarService
{
    private WorkCalendarRepository $repo;

    public function __construct(private Database $db)
    {
        $this->repo = new WorkCalendarRepository($db);
    }

    public function getCalendarConfig(): array
    {
        return $this->repo->getWorkingDaysConfig();
    }

    public function saveCalendarConfig(array $config): void
    {
        $this->repo->saveWorkingDaysConfig($config);
    }

    public function listHolidays(?int $year = null): array
    {
        return $this->repo->listHolidays(true, $year);
    }

    public function listAllHolidays(): array
    {
        return $this->repo->listHolidays(false);
    }

    public function createHoliday(array $data): int
    {
        return $this->repo->createHoliday($data);
    }

    public function updateHoliday(int $id, array $data): void
    {
        $this->repo->updateHoliday($id, $data);
    }

    public function deleteHoliday(int $id): void
    {
        $this->repo->deleteHoliday($id);
    }

    public function canRegisterHours(string $date, bool $isAdmin = false): array
    {
        $config = $this->repo->getWorkingDaysConfig();
        $dayInfo = $this->repo->getDayInfo($date);

        if ($dayInfo['type'] === 'holiday') {
            if ($isAdmin && ($config['admin_can_override_holidays'] ?? true)) {
                return [
                    'allowed' => true,
                    'warning' => true,
                    'message' => 'Este día es festivo (' . $dayInfo['holiday_name'] . '). Registro permitido solo para administradores.',
                    'day_type' => 'holiday',
                    'holiday_name' => $dayInfo['holiday_name'],
                ];
            }

            return [
                'allowed' => false,
                'warning' => false,
                'message' => 'Este día es festivo (' . $dayInfo['holiday_name'] . '). No se pueden registrar horas.',
                'day_type' => 'holiday',
                'holiday_name' => $dayInfo['holiday_name'],
            ];
        }

        if ($dayInfo['type'] === 'non_working') {
            if ($isAdmin) {
                return [
                    'allowed' => true,
                    'warning' => true,
                    'message' => 'Este día no es laboral. Registro permitido solo para administradores.',
                    'day_type' => 'non_working',
                    'holiday_name' => null,
                ];
            }

            return [
                'allowed' => false,
                'warning' => false,
                'message' => 'Este día no es laboral. No se pueden registrar horas.',
                'day_type' => 'non_working',
                'holiday_name' => null,
            ];
        }

        return [
            'allowed' => true,
            'warning' => false,
            'message' => null,
            'day_type' => 'working',
            'holiday_name' => null,
        ];
    }

    public function getWeekDaysInfo(string $weekStartDate): array
    {
        $start = new \DateTimeImmutable($weekStartDate);
        $holidays = $this->repo->getHolidaysForRange(
            $start->format('Y-m-d'),
            $start->modify('+6 days')->format('Y-m-d')
        );
        $config = $this->repo->getWorkingDaysConfig();
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $start->modify("+{$i} days");
            $dateStr = $date->format('Y-m-d');
            $dayOfWeek = (int) $date->format('N');
            $isConfiguredWorking = (bool) ($config['working_days'][$dayOfWeek] ?? false);
            $holiday = $holidays[$dateStr] ?? null;

            if ($holiday !== null) {
                $days[$dateStr] = [
                    'type' => 'holiday',
                    'is_working' => false,
                    'holiday_name' => $holiday['name'],
                    'holiday_description' => $holiday['description'] ?? '',
                ];
            } elseif (!$isConfiguredWorking) {
                $days[$dateStr] = [
                    'type' => 'non_working',
                    'is_working' => false,
                    'holiday_name' => null,
                    'holiday_description' => null,
                ];
            } else {
                $days[$dateStr] = [
                    'type' => 'working',
                    'is_working' => true,
                    'holiday_name' => null,
                    'holiday_description' => null,
                ];
            }
        }

        return $days;
    }

    public function businessDays(string $startDate, string $endDate): int
    {
        return $this->repo->countBusinessDays($startDate, $endDate);
    }

    public function weeklyCapacityHours(string $weekStart, string $weekEnd): float
    {
        return $this->repo->getWeekCapacityForRange($weekStart, $weekEnd);
    }

    public function getDefaultWeeklyCapacity(): float
    {
        return $this->repo->getWeeklyCapacityHours();
    }
}
