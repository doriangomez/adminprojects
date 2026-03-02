<?php

declare(strict_types=1);

class RequirementsRepository
{
    public function __construct(private Database $db)
    {
    }

    public function listByProject(int $projectId): array
    {
        if (!$this->db->tableExists('project_requirements')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT r.*, c.name AS client_name
             FROM project_requirements r
             JOIN clients c ON c.id = r.client_id
             WHERE r.project_id = :project_id
             ORDER BY r.created_at DESC',
            [':project_id' => $projectId]
        );
    }

    public function create(array $payload): int
    {
        return $this->db->insert(
            'INSERT INTO project_requirements
            (project_id, client_id, created_by, name, description, version, delivery_date, status, approved_first_delivery, reprocess_count)
            VALUES (:project_id, :client_id, :created_by, :name, :description, :version, :delivery_date, :status, :approved_first_delivery, 0)',
            [
                ':project_id' => (int) $payload['project_id'],
                ':client_id' => (int) $payload['client_id'],
                ':created_by' => (int) $payload['created_by'],
                ':name' => trim((string) ($payload['name'] ?? '')),
                ':description' => trim((string) ($payload['description'] ?? '')),
                ':version' => trim((string) ($payload['version'] ?? '1.0')),
                ':delivery_date' => $payload['delivery_date'] ?: null,
                ':status' => (string) ($payload['status'] ?? 'borrador'),
                ':approved_first_delivery' => !empty($payload['approved_first_delivery']) ? 1 : 0,
            ]
        );
    }

    public function updateStatus(int $requirementId, string $status, int $updatedBy): void
    {
        $current = $this->db->fetchOne('SELECT * FROM project_requirements WHERE id = :id LIMIT 1', [':id' => $requirementId]);
        if (!$current) {
            throw new \RuntimeException('Requisito no encontrado.');
        }

        $reprocessCount = (int) ($current['reprocess_count'] ?? 0);
        if (($current['status'] ?? '') === 'rechazado' && $status === 'entregado') {
            $reprocessCount++;
        }

        $approvalDate = $current['approval_date'] ?? null;
        if ($status === 'aprobado') {
            $approvalDate = date('Y-m-d');
        }

        $approvedFirstDelivery = (int) ($current['approved_first_delivery'] ?? 0);
        if ($status === 'aprobado' && $reprocessCount === 0) {
            $approvedFirstDelivery = 1;
        }

        $this->db->execute(
            'UPDATE project_requirements
             SET status = :status,
                 approval_date = :approval_date,
                 reprocess_count = :reprocess_count,
                 approved_first_delivery = :approved_first_delivery,
                 updated_at = NOW()
             WHERE id = :id',
            [
                ':status' => $status,
                ':approval_date' => $approvalDate,
                ':reprocess_count' => $reprocessCount,
                ':approved_first_delivery' => $approvedFirstDelivery,
                ':id' => $requirementId,
            ]
        );

        $this->db->insert(
            'INSERT INTO requirement_audit_log (requirement_id, project_id, changed_by, from_status, to_status, notes)
             VALUES (:requirement_id, :project_id, :changed_by, :from_status, :to_status, :notes)',
            [
                ':requirement_id' => $requirementId,
                ':project_id' => (int) ($current['project_id'] ?? 0),
                ':changed_by' => $updatedBy,
                ':from_status' => (string) ($current['status'] ?? ''),
                ':to_status' => $status,
                ':notes' => $reprocessCount > (int) ($current['reprocess_count'] ?? 0) ? 'Se incrementa reproceso' : null,
            ]
        );
    }

    public function delete(int $requirementId): void
    {
        $this->db->execute('DELETE FROM project_requirements WHERE id = :id', [':id' => $requirementId]);
    }

    public function indicatorForProject(int $projectId, string $periodStart, string $periodEnd): array
    {
        if (!$this->db->tableExists('project_requirements')) {
            return $this->emptyIndicator();
        }

        $project = $this->db->fetchOne('SELECT status FROM projects WHERE id = :id LIMIT 1', [':id' => $projectId]) ?: [];
        $isClosed = in_array(strtolower((string) ($project['status'] ?? '')), ['closed', 'archived', 'cancelled', 'cerrado'], true);
        if ($isClosed && $this->db->tableExists('requirement_indicator_snapshots')) {
            $snapshot = $this->db->fetchOne(
                'SELECT * FROM requirement_indicator_snapshots WHERE project_id = :project_id AND period_start = :start_date AND period_end = :end_date ORDER BY calculated_at DESC LIMIT 1',
                [':project_id' => $projectId, ':start_date' => $periodStart, ':end_date' => $periodEnd]
            );
            if ($snapshot) {
                return [
                    'applicable' => ((int) ($snapshot['total_requirements'] ?? 0)) > 0,
                    'value' => $snapshot['indicator_value'] !== null ? (float) $snapshot['indicator_value'] : null,
                    'total_requirements' => (int) ($snapshot['total_requirements'] ?? 0),
                    'approved_without_reprocess' => (int) ($snapshot['approved_without_reprocess'] ?? 0),
                    'with_reprocess' => max((int) ($snapshot['total_requirements'] ?? 0) - (int) ($snapshot['approved_without_reprocess'] ?? 0), 0),
                    'status' => (string) ($snapshot['status'] ?? 'no_aplica'),
                    'avg_reprocess_per_requirement' => 0,
                    'avg_days_to_approval' => 0,
                    'percent_over_two_reprocess' => 0,
                ];
            }
        }

        $rows = $this->db->fetchAll(
            'SELECT * FROM project_requirements
             WHERE project_id = :project_id
               AND delivery_date BETWEEN :start_date AND :end_date
               AND is_final_version = 1',
            [':project_id' => $projectId, ':start_date' => $periodStart, ':end_date' => $periodEnd]
        );

        $indicator = $this->buildIndicator($rows);

        if ($isClosed && $this->db->tableExists('requirement_indicator_snapshots')) {
            $this->db->insert(
                'INSERT INTO requirement_indicator_snapshots (project_id, period_start, period_end, total_requirements, approved_without_reprocess, indicator_value, status, frozen_at, calculated_at) VALUES (:project_id, :period_start, :period_end, :total_requirements, :approved_without_reprocess, :indicator_value, :status, NOW(), NOW())',
                [
                    ':project_id' => $projectId,
                    ':period_start' => $periodStart,
                    ':period_end' => $periodEnd,
                    ':total_requirements' => (int) ($indicator['total_requirements'] ?? 0),
                    ':approved_without_reprocess' => (int) ($indicator['approved_without_reprocess'] ?? 0),
                    ':indicator_value' => $indicator['value'],
                    ':status' => (string) ($indicator['status'] ?? 'no_aplica'),
                ]
            );
        }

        return $indicator;
    }

    public function indicatorByProject(array $filters = []): array
    {
        if (!$this->db->tableExists('project_requirements')) {
            return [];
        }

        $where = ['r.is_final_version = 1'];
        $params = [];
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $where[] = 'r.delivery_date BETWEEN :start_date AND :end_date';
            $params[':start_date'] = $filters['start_date'];
            $params[':end_date'] = $filters['end_date'];
        }
        if (!empty($filters['client_id'])) {
            $where[] = 'r.client_id = :client_id';
            $params[':client_id'] = (int) $filters['client_id'];
        }
        if (!empty($filters['pm_id'])) {
            $where[] = 'p.pm_id = :pm_id';
            $params[':pm_id'] = (int) $filters['pm_id'];
        }

        $rows = $this->db->fetchAll(
            'SELECT p.id AS project_id, p.name AS project_name, c.name AS client_name,
                    SUM(CASE WHEN r.status IN (\'entregado\',\'aprobado\',\'rechazado\') THEN 1 ELSE 0 END) AS total,
                    SUM(CASE WHEN r.status = \'' . "aprobado" . '\' AND (r.reprocess_count = 0 OR r.approved_first_delivery = 1) THEN 1 ELSE 0 END) AS approved_no_reprocess,
                    AVG(r.reprocess_count) AS avg_reprocess
             FROM project_requirements r
             JOIN projects p ON p.id = r.project_id
             JOIN clients c ON c.id = r.client_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY p.id, p.name, c.name
             ORDER BY project_name ASC',
            $params
        );

        return array_map(function (array $row): array {
            $total = (int) ($row['total'] ?? 0);
            $approved = (int) ($row['approved_no_reprocess'] ?? 0);
            $isApplicable = $total > 0;
            $percent = $isApplicable ? round(($approved / $total) * 100, 2) : null;

            return [
                'project_id' => (int) ($row['project_id'] ?? 0),
                'project' => (string) ($row['project_name'] ?? ''),
                'client' => (string) ($row['client_name'] ?? ''),
                'total' => $total,
                'approved_without_reprocess' => $approved,
                'with_reprocess' => max($total - $approved, 0),
                'indicator' => $percent,
                'status' => $this->statusFromValue($percent, $isApplicable),
                'avg_reprocess' => round((float) ($row['avg_reprocess'] ?? 0), 2),
            ];
        }, $rows);
    }

    public function trendForProject(int $projectId, int $months = 6): array
    {
        if (!$this->db->tableExists('project_requirements')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT DATE_FORMAT(delivery_date, "%Y-%m") AS period,
                    SUM(CASE WHEN status IN (\'entregado\',\'aprobado\',\'rechazado\') THEN 1 ELSE 0 END) AS total,
                    SUM(CASE WHEN status = \'' . "aprobado" . '\' AND (reprocess_count = 0 OR approved_first_delivery = 1) THEN 1 ELSE 0 END) AS approved_no_reprocess
             FROM project_requirements
             WHERE project_id = :project_id
               AND is_final_version = 1
               AND delivery_date >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
             GROUP BY DATE_FORMAT(delivery_date, "%Y-%m")
             ORDER BY period ASC',
            [':project_id' => $projectId, ':months' => max(1, $months)]
        );
    }

    public function auditByProject(int $projectId): array
    {
        if (!$this->db->tableExists('requirement_audit_log')) {
            return [];
        }

        $changedByNameSelect = 'NULL AS changed_by_name';
        $usersJoin = '';
        if ($this->db->tableExists('users')) {
            $usersJoin = 'LEFT JOIN users u ON u.id = l.changed_by';
            $userDisplayColumn = $this->resolveUserDisplayColumn();
            if ($userDisplayColumn !== null) {
                $changedByNameSelect = sprintf('u.%s AS changed_by_name', $userDisplayColumn);
            }
        }

        return $this->db->fetchAll(
            'SELECT l.*, ' . $changedByNameSelect . ', r.name AS requirement_name
             FROM requirement_audit_log l
             ' . $usersJoin . '
             LEFT JOIN project_requirements r ON r.id = l.requirement_id
             WHERE l.project_id = :project_id
             ORDER BY l.changed_at DESC
             LIMIT 100',
            [':project_id' => $projectId]
        );
    }

    private function resolveUserDisplayColumn(): ?string
    {
        foreach (['name', 'nombre', 'full_name', 'username'] as $column) {
            if ($this->db->columnExists('users', $column)) {
                return $column;
            }
        }

        return null;
    }

    private function buildIndicator(array $rows): array
    {
        $total = 0;
        $approved = 0;
        $reprocessTotal = 0;
        $approvalLeadTimes = [];

        foreach ($rows as $row) {
            if (!in_array($row['status'] ?? '', ['entregado', 'aprobado', 'rechazado'], true)) {
                continue;
            }

            $total++;
            $reprocessCount = (int) ($row['reprocess_count'] ?? 0);
            $reprocessTotal += $reprocessCount;
            if (($row['status'] ?? '') === 'aprobado' && ($reprocessCount === 0 || (int) ($row['approved_first_delivery'] ?? 0) === 1)) {
                $approved++;
            }

            if (!empty($row['delivery_date']) && !empty($row['approval_date'])) {
                $start = strtotime((string) $row['delivery_date']);
                $end = strtotime((string) $row['approval_date']);
                if ($start && $end && $end >= $start) {
                    $approvalLeadTimes[] = ($end - $start) / 86400;
                }
            }
        }

        $applicable = $total > 0;
        $value = $applicable ? round(($approved / $total) * 100, 2) : null;
        $moreThanTwo = 0;
        foreach ($rows as $row) {
            if ((int) ($row['reprocess_count'] ?? 0) > 2) {
                $moreThanTwo++;
            }
        }

        return [
            'applicable' => $applicable,
            'value' => $value,
            'total_requirements' => $total,
            'approved_without_reprocess' => $approved,
            'with_reprocess' => max($total - $approved, 0),
            'status' => $this->statusFromValue($value, $applicable),
            'avg_reprocess_per_requirement' => $total > 0 ? round($reprocessTotal / $total, 2) : 0,
            'avg_days_to_approval' => $approvalLeadTimes !== [] ? round(array_sum($approvalLeadTimes) / count($approvalLeadTimes), 1) : 0,
            'percent_over_two_reprocess' => $total > 0 ? round(($moreThanTwo / $total) * 100, 2) : 0,
        ];
    }

    private function statusFromValue(?float $value, bool $applicable): string
    {
        if (!$applicable || $value === null) {
            return 'no_aplica';
        }

        if ($value >= 95) {
            return 'verde';
        }

        if ($value >= 85) {
            return 'amarillo';
        }

        return 'rojo';
    }

    private function emptyIndicator(): array
    {
        return [
            'applicable' => false,
            'value' => null,
            'total_requirements' => 0,
            'approved_without_reprocess' => 0,
            'with_reprocess' => 0,
            'status' => 'no_aplica',
            'avg_reprocess_per_requirement' => 0,
            'avg_days_to_approval' => 0,
            'percent_over_two_reprocess' => 0,
        ];
    }
}
