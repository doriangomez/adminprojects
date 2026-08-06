<?php

declare(strict_types=1);

class PmoBoardService
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @param list<array<string, mixed>> $activities
     * @param array<string, mixed>|null $settings
     * @return array<string, mixed>
     */
    public function buildBoard(int $projectId, array $activities, ?array $settings = null): array
    {
        $settings = $settings ?? $this->settingsForProject($projectId);
        $threshold = (float) ($settings['deviation_threshold_pct'] ?? 5.0);
        $mask = (string) ($settings['work_week_mask'] ?? '0111110');
        if (strlen($mask) !== 7) {
            $mask = '0111110';
        }

        $today = new DateTimeImmutable('today');
        $enriched = [];
        $realValues = [];
        $plannedValues = [];
        $counts = [
            'total' => 0,
            'active' => 0,
            'adelantadas' => 0,
            'a_tiempo' => 0,
            'atrasadas' => 0,
            'done' => 0,
            'cancelled' => 0,
            'milestones' => 0,
            'milestones_done' => 0,
            'critical' => 0,
        ];

        foreach ($activities as $activity) {
            $row = $this->enrichActivity($activity, $today, $mask, $threshold);
            $enriched[] = $row;
            $counts['total']++;

            $status = (string) ($row['status'] ?? 'todo');
            $itemType = (string) ($row['item_type'] ?? 'activity');
            if ($itemType === 'milestone') {
                $counts['milestones']++;
                if ($status === 'done' || (float) ($row['progress_percent'] ?? 0) >= 100) {
                    $counts['milestones_done']++;
                }
            }

            if (!empty($row['is_critical'])) {
                $counts['critical']++;
            }

            if ($status === 'cancelled') {
                $counts['cancelled']++;
                continue;
            }

            $counts['active']++;
            $realValues[] = (float) ($row['progress_percent'] ?? 0);
            $plannedValues[] = (float) ($row['planned_percent'] ?? 0);

            if ($status === 'done') {
                $counts['done']++;
            }

            $class = (string) ($row['schedule_class'] ?? 'a_tiempo');
            if ($class === 'adelantada') {
                $counts['adelantadas']++;
            } elseif ($class === 'atrasada') {
                $counts['atrasadas']++;
            } elseif ($class === 'a_tiempo') {
                $counts['a_tiempo']++;
            }
        }

        $avanceReal = $realValues === [] ? 0.0 : round(array_sum($realValues) / count($realValues), 1);
        $avancePlan = $plannedValues === [] ? 0.0 : round(array_sum($plannedValues) / count($plannedValues), 1);
        $desviacion = round($avanceReal - $avancePlan, 1);

        $health = 'verde';
        if ($counts['atrasadas'] > 0 && $desviacion < -$threshold) {
            $health = 'rojo';
        } elseif ($counts['atrasadas'] > 0 || abs($desviacion) > $threshold) {
            $health = 'amarillo';
        }

        $stoppersOpen = $this->openStoppersCount($projectId);
        $risksSelected = $this->selectedRisksCount($projectId);
        $curve = $this->buildCurve($projectId, $enriched, $mask, $avanceReal);

        return [
            'settings' => $settings,
            'threshold_pct' => $threshold,
            'work_week_mask' => $mask,
            'activities' => $enriched,
            'counts' => $counts,
            'avance_real' => $avanceReal,
            'avance_real_method' => 'promedio_simple',
            'avance_planificado' => $avancePlan,
            'desviacion' => $desviacion,
            'health' => $health,
            'stoppers_open' => $stoppersOpen,
            'risks_selected' => $risksSelected,
            'critical_activities' => array_values(array_filter(
                $enriched,
                static fn (array $a): bool => !empty($a['is_critical']) && (($a['status'] ?? '') !== 'cancelled')
            )),
            'curve' => $curve,
            'as_of' => $today->format('Y-m-d'),
        ];
    }

    /**
     * @param array<string, mixed> $activity
     * @return array<string, mixed>
     */
    public function enrichActivity(array $activity, DateTimeImmutable $today, string $mask, float $threshold): array
    {
        $status = strtolower(trim((string) ($activity['status'] ?? 'todo')));
        if (!in_array($status, ['todo', 'in_progress', 'review', 'blocked', 'done', 'cancelled'], true)) {
            $status = 'todo';
        }

        $progress = max(0.0, min(100.0, (float) ($activity['progress_percent'] ?? 0)));
        if ($status === 'done') {
            $progress = 100.0;
        }

        $start = $this->parseDate((string) ($activity['start_date'] ?? ''));
        $end = $this->parseDate((string) ($activity['end_date'] ?? ''));
        $planned = $this->plannedPercent($start, $end, $today, $mask);

        $scheduleClass = 'no_aplica';
        if ($status === 'cancelled') {
            $scheduleClass = 'cancelled';
        } elseif ($status === 'done') {
            $scheduleClass = 'done';
        } else {
            $delta = $progress - $planned;
            if ($delta > $threshold) {
                $scheduleClass = 'adelantada';
            } elseif ($delta < -$threshold) {
                $scheduleClass = 'atrasada';
            } else {
                $scheduleClass = 'a_tiempo';
            }
        }

        $manual = $activity['is_critical_manual'] ?? null;
        $isCritical = false;
        if ($manual !== null && $manual !== '') {
            $isCritical = (int) $manual === 1;
        } else {
            $isCritical = (int) ($activity['is_critical_auto'] ?? 0) === 1;
        }

        $activity['status'] = $status;
        $activity['progress_percent'] = $progress;
        $activity['planned_percent'] = $planned;
        $activity['schedule_class'] = $scheduleClass;
        $activity['is_critical'] = $isCritical;

        return $activity;
    }

    public function plannedPercent(?DateTimeImmutable $start, ?DateTimeImmutable $end, DateTimeImmutable $today, string $mask): float
    {
        if ($start === null || $end === null) {
            return 0.0;
        }
        if ($end < $start) {
            return 0.0;
        }
        if ($today < $start) {
            return 0.0;
        }
        if ($today >= $end) {
            return 100.0;
        }

        $total = $this->countBusinessDays($start, $end, $mask);
        if ($total <= 0) {
            return 0.0;
        }
        $elapsed = $this->countBusinessDays($start, $today, $mask);

        return round(min(100.0, max(0.0, ($elapsed / $total) * 100)), 1);
    }

    public function countBusinessDays(DateTimeImmutable $from, DateTimeImmutable $to, string $mask): int
    {
        if ($to < $from) {
            return 0;
        }

        $count = 0;
        $cursor = $from;
        while ($cursor <= $to) {
            $idx = (int) $cursor->format('w'); // 0=Sunday
            if (($mask[$idx] ?? '0') === '1') {
                $count++;
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsForProject(int $projectId): array
    {
        $defaults = [
            'timezone' => 'America/Bogota',
            'deviation_threshold_pct' => 5.0,
            'work_week_mask' => '0111110',
            'critical_path_mode' => 'hybrid',
        ];

        if (!$this->db->tableExists('project_pmo_settings')) {
            return $defaults;
        }

        $row = $this->db->fetchOne(
            'SELECT * FROM project_pmo_settings WHERE project_id = :project LIMIT 1',
            [':project' => $projectId]
        );

        if ($row === null) {
            return $defaults;
        }

        return array_merge($defaults, [
            'timezone' => (string) ($row['timezone'] ?? $defaults['timezone']),
            'deviation_threshold_pct' => (float) ($row['deviation_threshold_pct'] ?? 5.0),
            'work_week_mask' => (string) ($row['work_week_mask'] ?? '0111110'),
            'critical_path_mode' => (string) ($row['critical_path_mode'] ?? 'hybrid'),
            'target_date' => $row['target_date'] ?? null,
            'board_title' => $row['board_title'] ?? null,
        ]);
    }

    private function openStoppersCount(int $projectId): int
    {
        if (!$this->db->tableExists('project_stoppers')) {
            return 0;
        }

        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM project_stoppers
             WHERE project_id = :project AND status NOT IN ('cerrado', 'resuelto')",
            [':project' => $projectId]
        );

        return (int) ($row['total'] ?? 0);
    }

    private function selectedRisksCount(int $projectId): int
    {
        if (!$this->db->tableExists('project_risk_evaluations')) {
            return 0;
        }

        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM project_risk_evaluations
             WHERE project_id = :project AND selected = 1',
            [':project' => $projectId]
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * @param list<array<string, mixed>> $activities
     * @return array<string, mixed>
     */
    private function buildCurve(int $projectId, array $activities, string $mask, float $avanceReal): array
    {
        $points = $this->plannedCurvePoints($activities, $mask);
        $snapshots = $this->snapshotSeries($projectId);

        $hasRealHistory = false;
        $realSeries = [];
        foreach ($snapshots as $snap) {
            $value = $snap['progress_manual'] ?? $snap['progress_tasks'] ?? $snap['progress_hours'] ?? null;
            if ($value === null) {
                continue;
            }
            $hasRealHistory = true;
            $realSeries[] = [
                'date' => (string) ($snap['snapshot_date'] ?? ''),
                'value' => (float) $value,
            ];
        }

        return [
            'planned' => $points,
            'real_current' => $avanceReal,
            'real_history' => $realSeries,
            'has_real_history' => $hasRealHistory,
            'note' => $hasRealHistory
                ? 'Curva con snapshots disponibles (progreso registrado).'
                : 'Histórico real pendiente. Se muestra plan acumulado y avance actual.',
        ];
    }

    /**
     * @param list<array<string, mixed>> $activities
     * @return list<array{date:string,value:float}>
     */
    private function plannedCurvePoints(array $activities, string $mask): array
    {
        $starts = [];
        $ends = [];
        foreach ($activities as $activity) {
            if (($activity['status'] ?? '') === 'cancelled') {
                continue;
            }
            $s = $this->parseDate((string) ($activity['start_date'] ?? ''));
            $e = $this->parseDate((string) ($activity['end_date'] ?? ''));
            if ($s) {
                $starts[] = $s;
            }
            if ($e) {
                $ends[] = $e;
            }
        }
        if ($starts === [] || $ends === []) {
            return [];
        }

        $min = min($starts);
        $max = max($ends);
        $today = new DateTimeImmutable('today');
        if ($max > $today) {
            // include today for reference
        }

        $points = [];
        $cursor = $min;
        $step = 0;
        while ($cursor <= $max && $step < 60) {
            $values = [];
            foreach ($activities as $activity) {
                if (($activity['status'] ?? '') === 'cancelled') {
                    continue;
                }
                $s = $this->parseDate((string) ($activity['start_date'] ?? ''));
                $e = $this->parseDate((string) ($activity['end_date'] ?? ''));
                $values[] = $this->plannedPercent($s, $e, $cursor, $mask);
            }
            $points[] = [
                'date' => $cursor->format('Y-m-d'),
                'value' => $values === [] ? 0.0 : round(array_sum($values) / count($values), 1),
            ];
            $cursor = $cursor->modify('+7 days');
            $step++;
        }

        if ($points !== [] && end($points)['date'] !== $max->format('Y-m-d')) {
            $values = [];
            foreach ($activities as $activity) {
                if (($activity['status'] ?? '') === 'cancelled') {
                    continue;
                }
                $s = $this->parseDate((string) ($activity['start_date'] ?? ''));
                $e = $this->parseDate((string) ($activity['end_date'] ?? ''));
                $values[] = $this->plannedPercent($s, $e, $max, $mask);
            }
            $points[] = [
                'date' => $max->format('Y-m-d'),
                'value' => $values === [] ? 0.0 : round(array_sum($values) / count($values), 1),
            ];
        }

        return $points;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function snapshotSeries(int $projectId): array
    {
        if (!$this->db->tableExists('project_pmo_snapshots')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT snapshot_date, progress_manual, progress_hours, progress_tasks
             FROM project_pmo_snapshots
             WHERE project_id = :project
             ORDER BY snapshot_date ASC
             LIMIT 90',
            [':project' => $projectId]
        );
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', substr($value, 0, 10));

        return $dt ?: null;
    }
}
