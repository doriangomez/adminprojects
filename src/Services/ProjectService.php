<?php

declare(strict_types=1);

class ProjectService
{
    /** @var array<int, array<string, float|int>> */
    private static array $healthCache = [];

    public function __construct(private Database $db)
    {
    }

    public function calculateProjectHealthScore(int $projectId): array
    {
        if (isset(self::$healthCache[$projectId])) {
            return self::$healthCache[$projectId];
        }

        $project = $this->db->fetchOne('SELECT * FROM projects WHERE id = :id LIMIT 1', [':id' => $projectId]);
        if (!$project) {
            $empty = [
                'total_score' => 0,
                'documental_score' => 0,
                'avance_score' => 0,
                'horas_score' => 0,
                'seguimiento_score' => 0,
                'riesgo_score' => 0,
            ];
            self::$healthCache[$projectId] = $empty;

            return $empty;
        }

        $documentalScore = $this->calculateDocumentalScore($projectId);
        $avanceScore = $this->calculateAvanceScore($project);
        $horasScore = $this->calculateHorasScore($projectId, $project);
        $seguimientoScore = $this->calculateSeguimientoScore($projectId, $project);
        $riesgoScore = $this->calculateRiesgoScore($projectId, $project);

        $total = (int) round(
            ($documentalScore * 0.25)
            + ($avanceScore * 0.25)
            + ($horasScore * 0.20)
            + ($seguimientoScore * 0.15)
            + ($riesgoScore * 0.15)
        );

        $result = [
            'total_score' => $this->clampScore($total),
            'documental_score' => $documentalScore,
            'avance_score' => $avanceScore,
            'horas_score' => $horasScore,
            'seguimiento_score' => $seguimientoScore,
            'riesgo_score' => $riesgoScore,
        ];

        self::$healthCache[$projectId] = $result;

        return $result;
    }

    private function calculateDocumentalScore(int $projectId): int
    {
        if (!$this->db->tableExists('project_nodes')) {
            return 50;
        }

        $row = $this->db->fetchOne(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN document_status IN ('final', 'publicado', 'aprobado') THEN 1 ELSE 0 END) AS approved
             FROM project_nodes
             WHERE project_id = :projectId
             AND node_type = 'file'",
            [':projectId' => $projectId]
        );

        $total = (int) ($row['total'] ?? 0);
        $approved = (int) ($row['approved'] ?? 0);

        if ($total <= 0) {
            return 50;
        }

        return $this->clampScore((int) round(($approved / $total) * 100));
    }

    private function calculateAvanceScore(array $project): int
    {
        $realProgress = max(0, min(100, (float) ($project['progress'] ?? 0)));
        $startDate = $project['start_date'] ?? null;
        $endDate = $project['end_date'] ?? null;

        if (!$startDate || !$endDate) {
            return (int) round($realProgress);
        }

        $startTs = strtotime((string) $startDate);
        $endTs = strtotime((string) $endDate);
        $now = time();

        if (!$startTs || !$endTs || $endTs <= $startTs) {
            return (int) round($realProgress);
        }

        if ($now <= $startTs) {
            $expected = 0.0;
        } elseif ($now >= $endTs) {
            $expected = 100.0;
        } else {
            $expected = (($now - $startTs) / ($endTs - $startTs)) * 100;
        }

        $deviation = abs($realProgress - $expected);

        return $this->clampScore((int) round(100 - $deviation));
    }

    private function calculateHorasScore(int $projectId, array $project): int
    {
        $plannedHours = (float) ($project['planned_hours'] ?? 0);
        $actualHours = $this->projectActualHours($projectId, (float) ($project['actual_hours'] ?? 0));
        $progress = max(0, min(100, (float) ($project['progress'] ?? 0)));

        if ($plannedHours <= 0) {
            return $actualHours <= 0 ? 100 : 60;
        }

        $expectedHours = max(1.0, $plannedHours * ($progress / 100));
        $deviationRatio = abs($actualHours - $expectedHours) / $expectedHours;

        return $this->clampScore((int) round(100 - ($deviationRatio * 100)));
    }

    private function calculateSeguimientoScore(int $projectId, array $project): int
    {
        $updatedAt = $project['updated_at'] ?? null;
        $daysWithoutUpdate = $updatedAt ? (int) floor((time() - strtotime((string) $updatedAt)) / 86400) : 999;

        $recencyScore = match (true) {
            $daysWithoutUpdate <= 3 => 100,
            $daysWithoutUpdate <= 7 => 80,
            $daysWithoutUpdate <= 14 => 60,
            $daysWithoutUpdate <= 21 => 40,
            default => 20,
        };

        $activityScore = 50;
        if ($this->db->tableExists('audit_log')) {
            $activity = $this->db->fetchOne(
                'SELECT COUNT(*) AS total FROM audit_log WHERE entity = :entity AND entity_id = :projectId AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)',
                [':entity' => 'project_progress', ':projectId' => $projectId]
            );
            $events = (int) ($activity['total'] ?? 0);
            $activityScore = min(100, $events * 25);
        }

        return $this->clampScore((int) round(($recencyScore * 0.6) + ($activityScore * 0.4)));
    }

    private function calculateRiesgoScore(int $projectId, array $project): int
    {
        $riskLevel = strtolower(trim((string) ($project['risk_level'] ?? '')));
        if ($riskLevel !== '') {
            return match ($riskLevel) {
                'bajo' => 90,
                'medio' => 60,
                'alto' => 25,
                default => 70,
            };
        }

        $riskScore = isset($project['risk_score']) ? (float) $project['risk_score'] : null;
        if ($riskScore !== null && $riskScore > 0) {
            return $this->clampScore((int) round(100 - $riskScore));
        }

        if (!$this->db->tableExists('project_risk_evaluations')) {
            return 70;
        }

        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM project_risk_evaluations WHERE project_id = :projectId AND selected = 1',
            [':projectId' => $projectId]
        );
        $selectedRisks = (int) ($row['total'] ?? 0);

        if ($selectedRisks <= 0) {
            return 85;
        }

        return $this->clampScore(100 - ($selectedRisks * 10));
    }

    private function projectActualHours(int $projectId, float $fallback): float
    {
        if (!$this->db->tableExists('timesheets')) {
            return $fallback;
        }

        $row = $this->db->fetchOne(
            'SELECT SUM(hours) AS total FROM timesheets WHERE project_id = :projectId',
            [':projectId' => $projectId]
        );

        $hours = (float) ($row['total'] ?? 0);

        return $hours > 0 ? $hours : $fallback;
    }

    private function clampScore(int $score): int
    {
        return max(0, min(100, $score));
    }
}
