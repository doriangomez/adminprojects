<?php

declare(strict_types=1);

/**
 * Servicio de capacidad real del talento.
 *
 * Calcula la capacidad disponible considerando:
 * - Horas laborales base
 * - Festivos (calendario)
 * - Ausencias aprobadas (vacaciones, permisos, incapacidades, etc.)
 *
 * El talento es la unidad principal de capacidad operativa.
 */
class TalentCapacityService
{
    public const ABSENCE_TYPES = [
        'vacaciones' => ['label' => 'Vacaciones', 'color' => 'lightblue', 'blocks_registration' => true],
        'incapacidad' => ['label' => 'Incapacidad', 'color' => 'lightcoral', 'blocks_registration' => true],
        'permiso_personal' => ['label' => 'Permiso personal', 'color' => 'lightyellow', 'blocks_registration' => false],
        'permiso_medico' => ['label' => 'Permiso médico', 'color' => 'lightyellow', 'blocks_registration' => false],
        'capacitacion' => ['label' => 'Capacitación', 'color' => 'lavender', 'blocks_registration' => false],
        'licencia' => ['label' => 'Licencia', 'color' => 'lightcyan', 'blocks_registration' => true],
        'festivo' => ['label' => 'Festivo personal', 'color' => 'lightgray', 'blocks_registration' => false],
    ];

    public function __construct(
        private Database $db,
        private WorkCalendarService $workCalendar
    ) {
    }

    /**
     * Obtiene la capacidad real para un talento en una semana.
     *
     * @return array{capacity_base: float, capacity_holidays: float, capacity_absences: float, capacity_real: float, breakdown: array, absences_by_day: array}
     */
    public function realCapacityForWeek(?int $talentId, ?int $userId, \DateTimeImmutable $weekStart, float $baseWeeklyCapacity = 40.0): array
    {
        $weekEnd = $weekStart->modify('+6 days');
        $weekStartStr = $weekStart->format('Y-m-d');
        $weekEndStr = $weekEnd->format('Y-m-d');

        $capacityAfterHolidays = $this->workCalendar->weeklyCapacityForWeek($baseWeeklyCapacity, $weekStart);
        $capacityHolidays = round($baseWeeklyCapacity - $capacityAfterHolidays, 2);
        $baselineWorkingDays = max(1, count($this->workCalendar->getCalendarConfig()['working_days']));
        $dailyCapacity = $baseWeeklyCapacity / $baselineWorkingDays;

        $breakdown = [
            'festivos' => max(0.0, $capacityHolidays),
            'vacaciones' => 0.0,
            'incapacidad' => 0.0,
            'permiso_personal' => 0.0,
            'permiso_medico' => 0.0,
            'capacitacion' => 0.0,
            'licencia' => 0.0,
            'festivo_personal' => 0.0,
        ];

        $absencesByDay = [];
        $totalAbsenceHours = 0.0;

        if (($talentId !== null || $userId !== null) && $this->db->tableExists('talent_absences')) {
            $absences = $this->approvedAbsencesForWeek($talentId, $userId, $weekStartStr, $weekEndStr);
            $weekMap = $this->workCalendar->weekMap($weekStart);

            foreach ($absences as $abs) {
                $type = strtolower(trim((string) ($abs['type'] ?? '')));
                $start = (string) ($abs['start_date'] ?? '');
                $end = (string) ($abs['end_date'] ?? '');
                $isFullDay = (int) ($abs['is_full_day'] ?? 1) === 1;
                $hours = isset($abs['hours']) ? (float) $abs['hours'] : null;

                $breakdownKey = $type === 'festivo' ? 'festivo_personal' : $type;
                if (!array_key_exists($breakdownKey, $breakdown)) {
                    $breakdown[$breakdownKey] = 0.0;
                }

                $cursor = new \DateTimeImmutable($start);
                $endDate = new \DateTimeImmutable($end);
                $daysInRange = 0;

                while ($cursor <= $endDate) {
                    $dateKey = $cursor->format('Y-m-d');
                    if ($dateKey >= $weekStartStr && $dateKey <= $weekEndStr) {
                        $dayMeta = $weekMap[$dateKey] ?? ['is_working' => true];
                        if (!empty($dayMeta['is_working'])) {
                            $daysInRange++;
                            $hoursThisDay = 0.0;

                            if ($isFullDay) {
                                $hoursThisDay = $dailyCapacity;
                            } else {
                                if ($hours !== null && $start === $end) {
                                    $hoursThisDay = min($hours, $dailyCapacity);
                                } elseif ($hours !== null) {
                                    $totalDays = (new \DateTimeImmutable($end))->diff(new \DateTimeImmutable($start))->days + 1;
                                    $hoursThisDay = $totalDays > 0 ? min($hours / $totalDays, $dailyCapacity) : 0;
                                }
                            }

                            $totalAbsenceHours += $hoursThisDay;
                            $breakdown[$breakdownKey] = ($breakdown[$breakdownKey] ?? 0) + $hoursThisDay;

                            if (!isset($absencesByDay[$dateKey])) {
                                $absencesByDay[$dateKey] = [];
                            }
                            $typeMeta = self::ABSENCE_TYPES[$type] ?? null;
                            $absencesByDay[$dateKey][] = [
                                'type' => $type,
                                'label' => $typeMeta['label'] ?? ucfirst(str_replace('_', ' ', $type)),
                                'hours' => $hoursThisDay,
                                'color' => $typeMeta['color'] ?? 'lightgray',
                                'blocks_registration' => $typeMeta['blocks_registration'] ?? false,
                            ];
                        }
                    }
                    $cursor = $cursor->modify('+1 day');
                }
            }
        }

        $capacityReal = max(0.0, round($capacityAfterHolidays - $totalAbsenceHours, 2));

        return [
            'capacity_base' => $baseWeeklyCapacity,
            'capacity_after_holidays' => $capacityAfterHolidays,
            'capacity_holidays' => $capacityHolidays,
            'capacity_absences' => $totalAbsenceHours,
            'capacity_real' => $capacityReal,
            'breakdown' => $breakdown,
            'absences_by_day' => $absencesByDay,
        ];
    }

    /**
     * Ausencias aprobadas que afectan la semana.
     */
    public function approvedAbsencesForWeek(?int $talentId, ?int $userId, string $weekStart, string $weekEnd): array
    {
        if (!$this->db->tableExists('talent_absences')) {
            return [];
        }

        $conditions = ['status = :status'];
        $params = [':status' => 'aprobado', ':start' => $weekStart, ':end' => $weekEnd];

        if ($talentId !== null) {
            $conditions[] = 'talent_id = :talentId';
            $params[':talentId'] = $talentId;
        }
        if ($userId !== null) {
            $conditions[] = 'user_id = :userId';
            $params[':userId'] = $userId;
        }
        if ($talentId === null && $userId === null) {
            return [];
        }

        $where = implode(' AND ', $conditions);

        return $this->db->fetchAll(
            "SELECT id, talent_id, user_id, type, start_date, end_date, hours, is_full_day, status
             FROM talent_absences
             WHERE {$where}
               AND start_date <= :end
               AND end_date >= :start
             ORDER BY start_date ASC",
            $params
        );
    }

    /**
     * Verifica si el talento tiene vacaciones (o ausencia que bloquea registro) en una fecha.
     */
    public function hasBlockingAbsenceOnDate(?int $talentId, ?int $userId, string $date): ?array
    {
        if (!$this->db->tableExists('talent_absences') || ($talentId === null && $userId === null)) {
            return null;
        }

        $conditions = ['status = :status', 'start_date <= :date', 'end_date >= :date'];
        $params = [':status' => 'aprobado', ':date' => $date];

        if ($talentId !== null) {
            $conditions[] = 'talent_id = :talentId';
            $params[':talentId'] = $talentId;
        }
        if ($userId !== null) {
            $conditions[] = 'user_id = :userId';
            $params[':userId'] = $userId;
        }

        $where = implode(' AND ', $conditions);
        $rows = $this->db->fetchAll(
            "SELECT type FROM talent_absences WHERE {$where}",
            $params
        );

        foreach ($rows as $row) {
            $type = strtolower(trim((string) ($row['type'] ?? '')));
            $meta = self::ABSENCE_TYPES[$type] ?? null;
            if ($meta !== null && !empty($meta['blocks_registration'])) {
                return [
                    'type' => $type,
                    'label' => $meta['label'],
                ];
            }
        }

        return null;
    }

    /**
     * Mensaje cuando no se puede registrar por vacaciones/ausencia bloqueante.
     */
    public static function blockingAbsenceMessage(array $absence): string
    {
        $label = $absence['label'] ?? 'ausencia';
        return "No puedes registrar horas. Estás en {$label} este día.";
    }

    public static function getAbsenceTypeLabel(string $type): string
    {
        return self::ABSENCE_TYPES[$type]['label'] ?? ucfirst(str_replace('_', ' ', $type));
    }

    public static function getAbsenceTypeColor(string $type): string
    {
        return self::ABSENCE_TYPES[$type]['color'] ?? 'lightgray';
    }
}
