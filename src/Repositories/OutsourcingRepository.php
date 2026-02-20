<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;
use DateTimeImmutable;

class OutsourcingRepository
{
    private const FREQUENCIES = ['weekly', 'biweekly', 'monthly'];
    private const SERVICE_STATUSES = ['green', 'yellow', 'red'];

    public function __construct(private Database $db)
    {
    }

    public function settingsForProject(int $projectId): array
    {
        if (!$this->db->tableExists('project_outsourcing_settings')) {
            return ['followup_frequency' => 'monthly'];
        }

        $row = $this->db->fetchOne(
            'SELECT followup_frequency FROM project_outsourcing_settings WHERE project_id = :project LIMIT 1',
            [':project' => $projectId]
        );

        $frequency = (string) ($row['followup_frequency'] ?? 'monthly');
        if (!in_array($frequency, self::FREQUENCIES, true)) {
            $frequency = 'monthly';
        }

        return ['followup_frequency' => $frequency];
    }

    public function updateSettings(int $projectId, string $frequency): void
    {
        $normalized = strtolower(trim($frequency));
        if (!in_array($normalized, self::FREQUENCIES, true)) {
            throw new \InvalidArgumentException('La frecuencia de seguimiento no es válida.');
        }

        if (!$this->db->tableExists('project_outsourcing_settings')) {
            return;
        }

        $exists = $this->db->fetchOne(
            'SELECT project_id FROM project_outsourcing_settings WHERE project_id = :project LIMIT 1',
            [':project' => $projectId]
        );

        if ($exists) {
            $this->db->execute(
                'UPDATE project_outsourcing_settings SET followup_frequency = :frequency, updated_at = NOW() WHERE project_id = :project',
                [
                    ':frequency' => $normalized,
                    ':project' => $projectId,
                ]
            );
            return;
        }

        $this->db->insert(
            'INSERT INTO project_outsourcing_settings (project_id, followup_frequency, created_at, updated_at)
             VALUES (:project, :frequency, NOW(), NOW())',
            [
                ':project' => $projectId,
                ':frequency' => $normalized,
            ]
        );
    }

    public function followupsForProject(int $projectId): array
    {
        if (!$this->db->tableExists('project_outsourcing_followups')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT f.id, f.project_id, f.document_node_id, f.period_start, f.period_end, f.responsible_user_id,
                    f.service_status, f.observations, f.decisions, f.created_by, f.created_at,
                    ru.name AS responsible_name, cu.name AS created_by_name
             FROM project_outsourcing_followups f
             LEFT JOIN users ru ON ru.id = f.responsible_user_id
             LEFT JOIN users cu ON cu.id = f.created_by
             WHERE f.project_id = :project
             ORDER BY f.period_start DESC, f.created_at DESC',
            [':project' => $projectId]
        );
    }

    public function createFollowup(array $payload): int
    {
        if (!$this->db->tableExists('project_outsourcing_followups')) {
            throw new \RuntimeException('La tabla de seguimientos no está disponible.');
        }

        $status = strtolower(trim((string) ($payload['service_status'] ?? '')));
        if (!in_array($status, self::SERVICE_STATUSES, true)) {
            throw new \InvalidArgumentException('El estado de servicio no es válido.');
        }

        $observations = trim((string) ($payload['observations'] ?? ''));
        if ($observations === '') {
            throw new \InvalidArgumentException('Las observaciones son obligatorias.');
        }

        $decisions = trim((string) ($payload['decisions'] ?? ''));
        if ($decisions === '') {
            throw new \InvalidArgumentException('Las decisiones o acciones tomadas son obligatorias.');
        }

        return $this->db->insert(
            'INSERT INTO project_outsourcing_followups
                (project_id, document_node_id, period_start, period_end, responsible_user_id, service_status, observations, decisions, created_by, created_at)
             VALUES
                (:project_id, :document_node_id, :period_start, :period_end, :responsible_user_id, :service_status, :observations, :decisions, :created_by, NOW())',
            [
                ':project_id' => (int) $payload['project_id'],
                ':document_node_id' => (int) $payload['document_node_id'],
                ':period_start' => $payload['period_start'],
                ':period_end' => $payload['period_end'],
                ':responsible_user_id' => (int) $payload['responsible_user_id'],
                ':service_status' => $status,
                ':observations' => $observations,
                ':decisions' => $decisions,
                ':created_by' => (int) ($payload['created_by'] ?? 0),
            ]
        );
    }

    public function indicators(int $projectId, string $frequency): array
    {
        [$periodStart, $periodEnd] = $this->periodForFrequency($frequency);

        $activeTalents = 0;
        if ($this->db->tableExists('project_talent_assignments')) {
            $activeTalents = (int) ($this->db->fetchOne(
                'SELECT COUNT(*) AS total FROM project_talent_assignments
                 WHERE project_id = :project AND (assignment_status = \'active\' OR (assignment_status IS NULL AND active = 1))',
                [':project' => $projectId]
            )['total'] ?? 0);
        }

        $loggedHours = null;
        if ($this->db->tableExists('timesheets') && $this->db->tableExists('tasks')) {
            $row = $this->db->fetchOne(
                'SELECT SUM(ts.hours) AS total
                 FROM timesheets ts
                 JOIN tasks t ON t.id = ts.task_id
                 WHERE t.project_id = :project
                 AND ts.status = \'approved\'
                 AND ts.date BETWEEN :start AND :end',
                [
                    ':project' => $projectId,
                    ':start' => $periodStart,
                    ':end' => $periodEnd,
                ]
            );
            $loggedHours = $row ? (float) ($row['total'] ?? 0) : 0.0;
        }

        $lastFollowup = null;
        if ($this->db->tableExists('project_outsourcing_followups')) {
            $lastFollowup = $this->db->fetchOne(
                'SELECT service_status, created_at FROM project_outsourcing_followups
                 WHERE project_id = :project
                 ORDER BY period_start DESC, created_at DESC
                 LIMIT 1',
                [':project' => $projectId]
            );
        }

        $openRisks = 0;
        if ($this->db->tableExists('project_risk_evaluations')) {
            $openRisks = (int) ($this->db->fetchOne(
                'SELECT COUNT(*) AS total FROM project_risk_evaluations WHERE project_id = :project AND selected = 1',
                [':project' => $projectId]
            )['total'] ?? 0);
        }

        return [
            'active_talents' => $activeTalents,
            'logged_hours' => $loggedHours,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'last_followup_status' => $lastFollowup['service_status'] ?? null,
            'last_followup_at' => $lastFollowup['created_at'] ?? null,
            'open_risks' => $openRisks,
        ];
    }

    private function periodForFrequency(string $frequency): array
    {
        $normalized = strtolower(trim($frequency));
        $now = new DateTimeImmutable('now');
        if ($normalized === 'weekly') {
            $start = $now->modify('monday this week');
            $end = $start->modify('+6 days');
            return [$start->format('Y-m-d'), $end->format('Y-m-d')];
        }

        if ($normalized === 'biweekly') {
            $year = (int) $now->format('o');
            $week = (int) $now->format('W');
            $startWeek = $week % 2 === 0 ? $week - 1 : $week;
            $start = (new DateTimeImmutable())->setISODate($year, max($startWeek, 1), 1);
            $end = $start->modify('+13 days');
            return [$start->format('Y-m-d'), $end->format('Y-m-d')];
        }

        $start = $now->modify('first day of this month');
        $end = $now->modify('last day of this month');
        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }
}
