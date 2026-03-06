<?php

declare(strict_types=1);

class TalentAvailabilityService
{
    private ?WorkCalendarService $workCalendarService = null;
    private ?array $absenceRules = null;

    public function __construct(private Database $db)
    {
    }

    public function weeklyCapacityBreakdown(
        int $talentId,
        float $weeklyBaseCapacity,
        \DateTimeImmutable $weekStart,
        float $availabilityPercent = 100.0
    ): array {
        $weekStart = $weekStart->setTime(0, 0);
        $weekEnd = $weekStart->modify('+6 days')->setTime(0, 0);
        $window = $this->buildCapacityWindow($talentId, $weeklyBaseCapacity, $weekStart, $weekEnd, $availabilityPercent);

        return [
            'weekly_base_hours' => round($window['weekly_base_hours'], 2),
            'weekly_real_hours' => round($window['total_real_hours'], 2),
            'holiday_hours' => round($window['holiday_hours'], 2),
            'holiday_days' => (int) $window['holiday_days'],
            'absence_hours' => round($window['absence_hours'], 2),
            'absence_details' => $window['absence_details'],
            'daily' => $window['daily'],
        ];
    }

    public function effectiveCapacityForRange(
        int $talentId,
        float $weeklyBaseCapacity,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        float $availabilityPercent = 100.0
    ): float {
        $window = $this->buildCapacityWindow(
            $talentId,
            $weeklyBaseCapacity,
            $start->setTime(0, 0),
            $end->setTime(0, 0),
            $availabilityPercent
        );

        return round($window['total_real_hours'], 2);
    }

    public function dailyCapacityMapForRange(
        int $talentId,
        float $weeklyBaseCapacity,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        float $availabilityPercent = 100.0
    ): array {
        $window = $this->buildCapacityWindow(
            $talentId,
            $weeklyBaseCapacity,
            $start->setTime(0, 0),
            $end->setTime(0, 0),
            $availabilityPercent
        );

        return $window['daily'];
    }

    public function dayCapacity(
        int $talentId,
        float $weeklyBaseCapacity,
        \DateTimeImmutable $day,
        float $availabilityPercent = 100.0
    ): array {
        $weekStart = $day->setTime(0, 0)->modify('monday this week');
        $weekly = $this->weeklyCapacityBreakdown($talentId, $weeklyBaseCapacity, $weekStart, $availabilityPercent);
        $dayKey = $day->format('Y-m-d');

        return $weekly['daily'][$dayKey] ?? [];
    }

    private function buildCapacityWindow(
        int $talentId,
        float $weeklyBaseCapacity,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        float $availabilityPercent
    ): array {
        if ($start > $end) {
            return [
                'weekly_base_hours' => 0.0,
                'total_real_hours' => 0.0,
                'holiday_hours' => 0.0,
                'holiday_days' => 0,
                'absence_hours' => 0.0,
                'absence_details' => [],
                'daily' => [],
            ];
        }

        $workingDays = max(1, count($this->workCalendarService()->getCalendarConfig()['working_days'] ?? [1, 2, 3, 4, 5]));
        $availabilityRatio = max(0.0, min(100.0, $availabilityPercent)) / 100;
        $weeklyBase = max(0.0, $weeklyBaseCapacity) * $availabilityRatio;
        $dailyBase = $weeklyBase / $workingDays;

        $daily = [];
        $holidayHours = 0.0;
        $holidayDays = 0;
        $cursor = $start;
        while ($cursor <= $end) {
            $dateKey = $cursor->format('Y-m-d');
            $dayMeta = $this->workCalendarService()->classifyDate($cursor);
            $isWorking = !empty($dayMeta['is_working']);
            $baseHours = $isWorking ? $dailyBase : 0.0;

            if (($dayMeta['type'] ?? '') === 'holiday' && $dailyBase > 0) {
                $holidayHours += $dailyBase;
                $holidayDays++;
            }

            $daily[$dateKey] = [
                'date' => $dateKey,
                'day_type' => (string) ($dayMeta['type'] ?? ($isWorking ? 'working' : 'non_working')),
                'day_name' => (string) ($dayMeta['name'] ?? ''),
                'is_working' => $isWorking,
                'base_hours' => $baseHours,
                'available_hours' => $baseHours,
                'absence_hours' => 0.0,
                'absences' => [],
                'primary_absence_type' => '',
                'primary_absence_label' => '',
                'has_full_day_absence' => false,
                'has_vacation' => false,
            ];

            $cursor = $cursor->modify('+1 day');
        }

        $absenceByType = [];
        foreach ($this->approvedAbsencesForTalent($talentId, $start->format('Y-m-d'), $end->format('Y-m-d')) as $absence) {
            $type = $this->normalizeAbsenceType((string) ($absence['absence_type'] ?? ''));
            $label = $this->absenceTypeLabel($type);
            $startDate = $this->safeDate((string) ($absence['start_date'] ?? ''));
            $endDate = $this->safeDate((string) ($absence['end_date'] ?? ''));
            if (!$startDate || !$endDate) {
                continue;
            }
            if ($endDate < $start || $startDate > $end) {
                continue;
            }

            $hours = max(0.0, (float) ($absence['hours'] ?? 0));
            $isFullDay = (int) ($absence['is_full_day'] ?? 1) === 1 || $hours <= 0;
            $rangeStart = $startDate > $start ? $startDate : $start;
            $rangeEnd = $endDate < $end ? $endDate : $end;
            $rowCursor = $rangeStart;
            while ($rowCursor <= $rangeEnd) {
                $dateKey = $rowCursor->format('Y-m-d');
                if (!isset($daily[$dateKey])) {
                    $rowCursor = $rowCursor->modify('+1 day');
                    continue;
                }

                $availableBefore = (float) ($daily[$dateKey]['available_hours'] ?? 0.0);
                $requestedHours = $isFullDay ? $dailyBase : min($hours, $dailyBase);
                $appliedHours = max(0.0, min($availableBefore, $requestedHours));

                $daily[$dateKey]['available_hours'] = max(0.0, $availableBefore - $appliedHours);
                $daily[$dateKey]['absence_hours'] += $appliedHours;
                $daily[$dateKey]['has_full_day_absence'] = (bool) $daily[$dateKey]['has_full_day_absence'] || $isFullDay;
                $daily[$dateKey]['has_vacation'] = (bool) $daily[$dateKey]['has_vacation'] || $type === 'vacaciones';
                if ($daily[$dateKey]['primary_absence_type'] === '') {
                    $daily[$dateKey]['primary_absence_type'] = $type;
                    $daily[$dateKey]['primary_absence_label'] = $label;
                }
                $daily[$dateKey]['absences'][] = [
                    'type' => $type,
                    'label' => $label,
                    'is_full_day' => $isFullDay,
                    'hours_requested' => round($requestedHours, 2),
                    'hours_applied' => round($appliedHours, 2),
                ];

                if (!isset($absenceByType[$type])) {
                    $absenceByType[$type] = ['type' => $type, 'label' => $label, 'hours' => 0.0, 'days' => 0.0];
                }
                $absenceByType[$type]['hours'] += $appliedHours;
                if ($appliedHours > 0) {
                    if ($isFullDay) {
                        $absenceByType[$type]['days'] += 1.0;
                    } elseif ($dailyBase > 0) {
                        $absenceByType[$type]['days'] += ($appliedHours / $dailyBase);
                    }
                }

                $rowCursor = $rowCursor->modify('+1 day');
            }
        }

        $absenceHours = 0.0;
        foreach ($absenceByType as &$typeRow) {
            $typeRow['hours'] = round((float) ($typeRow['hours'] ?? 0), 2);
            $typeRow['days'] = round((float) ($typeRow['days'] ?? 0), 2);
            $absenceHours += (float) $typeRow['hours'];
        }
        unset($typeRow);

        foreach ($daily as &$dayRow) {
            $dayRow['base_hours'] = round((float) ($dayRow['base_hours'] ?? 0), 2);
            $dayRow['available_hours'] = round((float) ($dayRow['available_hours'] ?? 0), 2);
            $dayRow['absence_hours'] = round((float) ($dayRow['absence_hours'] ?? 0), 2);
        }
        unset($dayRow);

        return [
            'weekly_base_hours' => $weeklyBase,
            'total_real_hours' => array_sum(array_map(static fn (array $day): float => (float) ($day['available_hours'] ?? 0), $daily)),
            'holiday_hours' => $holidayHours,
            'holiday_days' => $holidayDays,
            'absence_hours' => $absenceHours,
            'absence_details' => array_values($absenceByType),
            'daily' => $daily,
        ];
    }

    private function approvedAbsencesForTalent(int $talentId, string $start, string $end): array
    {
        if (!$this->absencesEnabled()) {
            return [];
        }

        if ($talentId <= 0 || !$this->db->tableExists('talent_absences')) {
            return [];
        }

        $hoursColumn = $this->db->columnExists('talent_absences', 'hours') ? 'hours' : 'NULL AS hours';
        $isFullDayColumn = $this->db->columnExists('talent_absences', 'is_full_day') ? 'is_full_day' : '1 AS is_full_day';
        $statusWhere = '1=1';
        $params = [
            ':talent' => $talentId,
            ':start' => $start,
            ':end' => $end,
        ];
        if ($this->db->columnExists('talent_absences', 'status')) {
            $statusWhere = 'LOWER(status) IN ("aprobado", "approved")';
        }

        $rows = $this->db->fetchAll(
            'SELECT absence_type, start_date, end_date, ' . $hoursColumn . ', ' . $isFullDayColumn . '
             FROM talent_absences
             WHERE talent_id = :talent
               AND ' . $statusWhere . '
               AND start_date <= :end
               AND end_date >= :start',
            $params
        );

        if ($this->vacationsEnabled()) {
            return $rows;
        }

        $filtered = [];
        foreach ($rows as $row) {
            $type = $this->normalizeAbsenceType((string) ($row['absence_type'] ?? ''));
            if ($type === 'vacaciones') {
                continue;
            }
            $filtered[] = $row;
        }

        return $filtered;
    }

    private function normalizeAbsenceType(string $rawType): string
    {
        $value = strtolower(trim($rawType));
        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
        $value = trim($value, '_');

        $aliases = [
            'vacacion' => 'vacaciones',
            'vacaciones' => 'vacaciones',
            'incapacidad' => 'incapacidad',
            'permiso_personal' => 'permiso_personal',
            'permiso_medico' => 'permiso_medico',
            'capacitacion' => 'capacitacion',
            'licencia' => 'licencia',
            'festivo' => 'festivo',
        ];

        return $aliases[$value] ?? ($value !== '' ? $value : 'ausencia');
    }

    private function absenceTypeLabel(string $type): string
    {
        $labels = [
            'vacaciones' => 'Vacaciones',
            'incapacidad' => 'Incapacidad',
            'permiso_personal' => 'Permiso personal',
            'permiso_medico' => 'Permiso médico',
            'capacitacion' => 'Capacitación',
            'licencia' => 'Licencia',
            'festivo' => 'Festivo',
            'ausencia' => 'Ausencia',
        ];

        return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    private function safeDate(string $date): ?\DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        try {
            return (new \DateTimeImmutable($date))->setTime(0, 0);
        } catch (\Throwable) {
            return null;
        }
    }

    private function workCalendarService(): WorkCalendarService
    {
        if ($this->workCalendarService instanceof WorkCalendarService) {
            return $this->workCalendarService;
        }

        $this->workCalendarService = new WorkCalendarService($this->db);
        return $this->workCalendarService;
    }

    private function absencesEnabled(): bool
    {
        return (bool) ($this->absenceRules()['enabled'] ?? false);
    }

    private function vacationsEnabled(): bool
    {
        return (bool) ($this->absenceRules()['enable_vacations'] ?? true);
    }

    private function absenceRules(): array
    {
        if (is_array($this->absenceRules)) {
            return $this->absenceRules;
        }

        $config = (new ConfigService($this->db))->getConfig();
        $rules = is_array($config['operational_rules']['absences'] ?? null)
            ? $config['operational_rules']['absences']
            : [];

        $this->absenceRules = [
            'enabled' => (bool) ($rules['enabled'] ?? false),
            'enable_vacations' => (bool) ($rules['enable_vacations'] ?? true),
            'block_timesheet_logging' => (bool) ($rules['block_timesheet_logging'] ?? true),
            'allow_admin_exceptions' => (bool) ($rules['allow_admin_exceptions'] ?? false),
        ];

        return $this->absenceRules;
    }
}
