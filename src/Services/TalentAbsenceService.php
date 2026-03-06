<?php

declare(strict_types=1);

class TalentAbsenceService
{
    public const ABSENCE_TYPES = [
        'vacaciones' => ['label' => 'Vacaciones', 'color' => '#3B82F6', 'icon' => '🏖️', 'default_hours' => 8],
        'incapacidad' => ['label' => 'Incapacidad', 'color' => '#EF4444', 'icon' => '🏥', 'default_hours' => 8],
        'permiso_personal' => ['label' => 'Permiso personal', 'color' => '#8B5CF6', 'icon' => '👤', 'default_hours' => 8],
        'permiso_medico' => ['label' => 'Permiso médico', 'color' => '#F59E0B', 'icon' => '⚕️', 'default_hours' => 4],
        'capacitacion' => ['label' => 'Capacitación', 'color' => '#10B981', 'icon' => '📚', 'default_hours' => 8],
        'licencia' => ['label' => 'Licencia', 'color' => '#6366F1', 'icon' => '📋', 'default_hours' => 8],
        'festivo' => ['label' => 'Festivo', 'color' => '#EC4899', 'icon' => '🎉', 'default_hours' => 8],
    ];

    public function __construct(private Database $db)
    {
    }

    /**
     * Get all approved absences for a talent within a date range.
     */
    public function getAbsencesForTalent(int $userId, string $dateStart, string $dateEnd): array
    {
        if (!$this->db->tableExists('talent_absences')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT ta.*, t.name AS talent_name
             FROM talent_absences ta
             JOIN talents t ON t.id = ta.talent_id
             WHERE ta.user_id = :user_id
               AND ta.status = "aprobado"
               AND ta.date_start <= :date_end
               AND ta.date_end >= :date_start
             ORDER BY ta.date_start ASC',
            [
                ':user_id' => $userId,
                ':date_start' => $dateStart,
                ':date_end' => $dateEnd,
            ]
        );
    }

    /**
     * Get absences for a specific day for a user.
     */
    public function getAbsencesForDay(int $userId, string $date): array
    {
        return $this->getAbsencesForTalent($userId, $date, $date);
    }

    /**
     * Check if a user has an absence on a specific date.
     * Returns the absence record or null.
     */
    public function getBlockingAbsence(int $userId, string $date): ?array
    {
        if (!$this->db->tableExists('talent_absences')) {
            return null;
        }

        $absence = $this->db->fetchOne(
            'SELECT ta.*, t.name AS talent_name
             FROM talent_absences ta
             JOIN talents t ON t.id = ta.talent_id
             WHERE ta.user_id = :user_id
               AND ta.status = "aprobado"
               AND ta.date_start <= :date
               AND ta.date_end >= :date
               AND ta.hours_per_day >= 8
             ORDER BY ta.absence_type ASC
             LIMIT 1',
            [
                ':user_id' => $userId,
                ':date' => $date,
            ]
        );

        return $absence ?: null;
    }

    /**
     * Calculate the real weekly capacity for a talent, deducting absences.
     * Returns detailed breakdown.
     */
    public function weeklyCapacityBreakdown(
        int $userId,
        float $baseWeeklyCapacity,
        \DateTimeImmutable $weekStart,
        array $weekCalendar
    ): array {
        $weekEnd = $weekStart->modify('+6 days');
        $dateStart = $weekStart->format('Y-m-d');
        $dateEnd = $weekEnd->format('Y-m-d');

        $workingDays = array_filter($weekCalendar, fn(array $day) => !empty($day['is_working']));
        $baseWorkingDayCount = count($workingDays);
        $dailyCapacity = $baseWorkingDayCount > 0 ? $baseWeeklyCapacity / $baseWorkingDayCount : 0;

        $holidayHours = 0.0;
        $holidayCount = 0;
        $holidays = [];
        foreach ($weekCalendar as $dayKey => $dayMeta) {
            if (!empty($dayMeta['is_holiday'])) {
                $holidayHours += $dailyCapacity;
                $holidayCount++;
                $holidays[] = [
                    'date' => $dayKey,
                    'name' => (string) ($dayMeta['name'] ?? 'Festivo'),
                    'hours' => $dailyCapacity,
                ];
            }
        }

        $absences = $this->getAbsencesForTalent($userId, $dateStart, $dateEnd);

        $absenceDeductions = [];
        $totalAbsenceHours = 0.0;
        $absencesByDay = [];

        foreach ($absences as $absence) {
            $absStart = new \DateTimeImmutable((string) $absence['date_start']);
            $absEnd = new \DateTimeImmutable((string) $absence['date_end']);
            $hoursPerDay = (float) ($absence['hours_per_day'] ?? 8);
            $type = (string) ($absence['absence_type'] ?? 'permiso_personal');
            $typeMeta = self::ABSENCE_TYPES[$type] ?? self::ABSENCE_TYPES['permiso_personal'];

            $cursor = max($absStart, $weekStart);
            $limit = min($absEnd, $weekEnd);

            while ($cursor <= $limit) {
                $dayKey = $cursor->format('Y-m-d');
                $dayMeta = $weekCalendar[$dayKey] ?? null;

                if ($dayMeta && !empty($dayMeta['is_working']) && empty($dayMeta['is_holiday'])) {
                    $deductHours = min($hoursPerDay, $dailyCapacity);
                    $totalAbsenceHours += $deductHours;

                    if (!isset($absenceDeductions[$type])) {
                        $absenceDeductions[$type] = [
                            'type' => $type,
                            'label' => $typeMeta['label'],
                            'color' => $typeMeta['color'],
                            'icon' => $typeMeta['icon'],
                            'total_hours' => 0.0,
                            'days' => 0,
                        ];
                    }
                    $absenceDeductions[$type]['total_hours'] += $deductHours;
                    $absenceDeductions[$type]['days']++;

                    $absencesByDay[$dayKey][] = [
                        'absence_id' => (int) ($absence['id'] ?? 0),
                        'type' => $type,
                        'label' => $typeMeta['label'],
                        'color' => $typeMeta['color'],
                        'icon' => $typeMeta['icon'],
                        'hours' => $deductHours,
                        'is_full_day' => $hoursPerDay >= $dailyCapacity,
                        'notes' => trim((string) ($absence['notes'] ?? '')),
                    ];
                }

                $cursor = $cursor->modify('+1 day');
            }
        }

        $effectiveCapacity = $baseWeeklyCapacity - $holidayHours;
        $realCapacity = max(0.0, $effectiveCapacity - $totalAbsenceHours);

        return [
            'base_capacity' => round($baseWeeklyCapacity, 2),
            'holiday_hours' => round($holidayHours, 2),
            'holiday_count' => $holidayCount,
            'holidays' => $holidays,
            'absence_hours' => round($totalAbsenceHours, 2),
            'absence_deductions' => array_values($absenceDeductions),
            'absences_by_day' => $absencesByDay,
            'real_capacity' => round($realCapacity, 2),
            'daily_capacity' => round($dailyCapacity, 2),
        ];
    }

    /**
     * Get all absences for a talent (all statuses) for management UI.
     */
    public function listAbsences(int $userId, ?string $status = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        if (!$this->db->tableExists('talent_absences')) {
            return [];
        }

        $conditions = ['ta.user_id = :user_id'];
        $params = [':user_id' => $userId];

        if ($status !== null && $status !== '') {
            $conditions[] = 'ta.status = :status';
            $params[':status'] = $status;
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            $conditions[] = 'ta.date_end >= :date_from';
            $params[':date_from'] = $dateFrom;
        }

        if ($dateTo !== null && $dateTo !== '') {
            $conditions[] = 'ta.date_start <= :date_to';
            $params[':date_to'] = $dateTo;
        }

        $where = implode(' AND ', $conditions);

        return $this->db->fetchAll(
            "SELECT ta.*, t.name AS talent_name,
                    u_approved.name AS approved_by_name,
                    u_created.name AS created_by_name
             FROM talent_absences ta
             JOIN talents t ON t.id = ta.talent_id
             LEFT JOIN users u_approved ON u_approved.id = ta.approved_by
             LEFT JOIN users u_created ON u_created.id = ta.created_by
             WHERE {$where}
             ORDER BY ta.date_start DESC",
            $params
        );
    }

    /**
     * List all absences visible to an admin/PMO user.
     */
    public function listAllAbsences(?string $status = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        if (!$this->db->tableExists('talent_absences')) {
            return [];
        }

        $conditions = ['1=1'];
        $params = [];

        if ($status !== null && $status !== '') {
            $conditions[] = 'ta.status = :status';
            $params[':status'] = $status;
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            $conditions[] = 'ta.date_end >= :date_from';
            $params[':date_from'] = $dateFrom;
        }

        if ($dateTo !== null && $dateTo !== '') {
            $conditions[] = 'ta.date_start <= :date_to';
            $params[':date_to'] = $dateTo;
        }

        $where = implode(' AND ', $conditions);

        return $this->db->fetchAll(
            "SELECT ta.*, t.name AS talent_name, u.name AS user_name,
                    u_approved.name AS approved_by_name,
                    u_created.name AS created_by_name
             FROM talent_absences ta
             JOIN talents t ON t.id = ta.talent_id
             JOIN users u ON u.id = ta.user_id
             LEFT JOIN users u_approved ON u_approved.id = ta.approved_by
             LEFT JOIN users u_created ON u_created.id = ta.created_by
             WHERE {$where}
             ORDER BY ta.date_start DESC",
            $params
        );
    }

    /**
     * Create a new absence record.
     */
    public function createAbsence(array $data, int $createdBy): int
    {
        $talentId = (int) ($data['talent_id'] ?? 0);
        $userId = (int) ($data['user_id'] ?? 0);
        $type = trim((string) ($data['absence_type'] ?? ''));
        $dateStart = trim((string) ($data['date_start'] ?? ''));
        $dateEnd = trim((string) ($data['date_end'] ?? ''));
        $hoursPerDay = (float) ($data['hours_per_day'] ?? 0);
        $notes = trim((string) ($data['notes'] ?? ''));
        $status = trim((string) ($data['status'] ?? 'pendiente'));

        if ($talentId <= 0 || $userId <= 0) {
            throw new \InvalidArgumentException('Talento y usuario son requeridos.');
        }

        if (!isset(self::ABSENCE_TYPES[$type])) {
            throw new \InvalidArgumentException('Tipo de ausencia inválido.');
        }

        if ($dateStart === '' || $dateEnd === '') {
            throw new \InvalidArgumentException('Fechas de inicio y fin son requeridas.');
        }

        if ($dateStart > $dateEnd) {
            throw new \InvalidArgumentException('La fecha de inicio no puede ser posterior a la fecha de fin.');
        }

        if ($hoursPerDay <= 0) {
            $hoursPerDay = (float) (self::ABSENCE_TYPES[$type]['default_hours'] ?? 8);
        }

        $totalHours = $this->calculateTotalHours($dateStart, $dateEnd, $hoursPerDay);

        $this->db->execute(
            'INSERT INTO talent_absences (talent_id, user_id, absence_type, date_start, date_end, hours_per_day, total_hours, status, notes, created_by)
             VALUES (:talent_id, :user_id, :absence_type, :date_start, :date_end, :hours_per_day, :total_hours, :status, :notes, :created_by)',
            [
                ':talent_id' => $talentId,
                ':user_id' => $userId,
                ':absence_type' => $type,
                ':date_start' => $dateStart,
                ':date_end' => $dateEnd,
                ':hours_per_day' => $hoursPerDay,
                ':total_hours' => $totalHours,
                ':status' => in_array($status, ['pendiente', 'aprobado'], true) ? $status : 'pendiente',
                ':notes' => $notes !== '' ? $notes : null,
                ':created_by' => $createdBy,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Approve an absence.
     */
    public function approveAbsence(int $absenceId, int $approvedBy): bool
    {
        $this->db->execute(
            'UPDATE talent_absences SET status = "aprobado", approved_by = :approved_by, approved_at = NOW() WHERE id = :id AND status = "pendiente"',
            [':id' => $absenceId, ':approved_by' => $approvedBy]
        );

        return true;
    }

    /**
     * Reject an absence.
     */
    public function rejectAbsence(int $absenceId, int $rejectedBy, ?string $notes = null): bool
    {
        $params = [':id' => $absenceId, ':rejected_by' => $rejectedBy];
        $notesSql = '';
        if ($notes !== null && $notes !== '') {
            $notesSql = ', notes = CONCAT(COALESCE(notes, ""), "\n[Rechazado] ", :rejection_notes)';
            $params[':rejection_notes'] = $notes;
        }

        $this->db->execute(
            'UPDATE talent_absences SET status = "rechazado", approved_by = :rejected_by, approved_at = NOW()' . $notesSql . ' WHERE id = :id AND status = "pendiente"',
            $params
        );

        return true;
    }

    /**
     * Cancel an absence.
     */
    public function cancelAbsence(int $absenceId, int $userId): bool
    {
        $this->db->execute(
            'UPDATE talent_absences SET status = "cancelado" WHERE id = :id AND (user_id = :user_id OR :user_id_check > 0) AND status IN ("pendiente", "aprobado")',
            [':id' => $absenceId, ':user_id' => $userId, ':user_id_check' => $userId]
        );

        return true;
    }

    /**
     * Delete an absence record.
     */
    public function deleteAbsence(int $absenceId): bool
    {
        $this->db->execute(
            'DELETE FROM talent_absences WHERE id = :id',
            [':id' => $absenceId]
        );

        return true;
    }

    /**
     * Get a single absence by ID.
     */
    public function getAbsence(int $absenceId): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT ta.*, t.name AS talent_name
             FROM talent_absences ta
             JOIN talents t ON t.id = ta.talent_id
             WHERE ta.id = :id',
            [':id' => $absenceId]
        );

        return $row ?: null;
    }

    /**
     * Get team capacity overview for a given week.
     */
    public function teamCapacityOverview(\DateTimeImmutable $weekStart, array $weekCalendar): array
    {
        if (!$this->db->tableExists('talents') || !$this->db->tableExists('talent_absences')) {
            return [];
        }

        $weekEnd = $weekStart->modify('+6 days');
        $dateStart = $weekStart->format('Y-m-d');
        $dateEnd = $weekEnd->format('Y-m-d');

        $talents = $this->db->fetchAll(
            'SELECT t.id AS talent_id, t.user_id, t.name, t.role, t.capacidad_horaria, t.weekly_capacity, t.availability
             FROM talents t
             JOIN users u ON u.id = t.user_id AND u.active = 1
             ORDER BY t.name ASC'
        );

        $result = [];
        foreach ($talents as $talent) {
            $userId = (int) ($talent['user_id'] ?? 0);
            $talentId = (int) ($talent['talent_id'] ?? 0);
            $baseCapacity = (float) ($talent['capacidad_horaria'] ?? 0);
            if ($baseCapacity <= 0) {
                $baseCapacity = (float) ($talent['weekly_capacity'] ?? 0);
            }
            if ($baseCapacity <= 0) {
                $baseCapacity = 40.0;
            }

            $breakdown = $this->weeklyCapacityBreakdown($userId, $baseCapacity, $weekStart, $weekCalendar);

            $assignedHours = $this->db->fetchOne(
                'SELECT COALESCE(SUM(ts.hours), 0) AS total
                 FROM timesheets ts
                 WHERE ts.user_id = :user_id
                   AND ts.date BETWEEN :start AND :end',
                [':user_id' => $userId, ':start' => $dateStart, ':end' => $dateEnd]
            );

            $registeredHours = (float) ($assignedHours['total'] ?? 0);
            $realCapacity = (float) $breakdown['real_capacity'];
            $availableCapacity = max(0.0, $realCapacity - $registeredHours);
            $overload = $registeredHours > $realCapacity ? round($registeredHours - $realCapacity, 2) : 0.0;

            $result[] = [
                'talent_id' => $talentId,
                'user_id' => $userId,
                'name' => (string) ($talent['name'] ?? ''),
                'role' => (string) ($talent['role'] ?? ''),
                'base_capacity' => $baseCapacity,
                'real_capacity' => $realCapacity,
                'registered_hours' => round($registeredHours, 2),
                'available_capacity' => round($availableCapacity, 2),
                'overload' => $overload,
                'utilization_percent' => $realCapacity > 0 ? round(($registeredHours / $realCapacity) * 100, 1) : 0.0,
                'breakdown' => $breakdown,
                'risk' => $overload > 0 ? 'sobrecarga' : ($availableCapacity < 4 && $realCapacity > 0 ? 'riesgo' : 'normal'),
            ];
        }

        return $result;
    }

    private function calculateTotalHours(string $dateStart, string $dateEnd, float $hoursPerDay): float
    {
        $start = new \DateTimeImmutable($dateStart);
        $end = new \DateTimeImmutable($dateEnd);
        $total = 0.0;
        $cursor = $start;

        while ($cursor <= $end) {
            $weekday = (int) $cursor->format('N');
            if ($weekday <= 5) {
                $total += $hoursPerDay;
            }
            $cursor = $cursor->modify('+1 day');
        }

        return round($total, 2);
    }
}
