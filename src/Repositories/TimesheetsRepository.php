<?php

declare(strict_types=1);

class TimesheetsRepository
{
    private const ADMIN_ROLES = ['Administrador', 'PMO'];

    public function __construct(private Database $db)
    {
    }

    public function weekly(array $user): array
    {
        $conditions = [];
        $params = [];

        if (!$this->isPrivileged($user)) {
            $conditions[] = 'c.pm_id = :pmId';
            $params[':pmId'] = $user['id'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return $this->db->fetchAll(
            'SELECT ts.id, ts.date, ts.hours, ts.status, ts.billable, p.name AS project, t.title AS task, ta.name AS talent
             FROM timesheets ts
             JOIN tasks t ON t.id = ts.task_id
             JOIN projects p ON p.id = t.project_id
             JOIN clients c ON c.id = p.client_id
             JOIN talents ta ON ta.id = ts.talent_id
             ' . $where . '
             ORDER BY ts.date DESC',
            $params
        );
    }

    public function kpis(array $user): array
    {
        $conditions = [];
        $params = [];

        if (!$this->isPrivileged($user)) {
            $conditions[] = 'c.pm_id = :pmId';
            $params[':pmId'] = $user['id'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $data = $this->db->fetchAll(
            'SELECT ts.status, SUM(ts.hours) AS hours
             FROM timesheets ts
             JOIN tasks t ON t.id = ts.task_id
             JOIN projects p ON p.id = t.project_id
             JOIN clients c ON c.id = p.client_id
             ' . $where . '
             GROUP BY ts.status',
            $params
        );
        $totals = ['draft' => 0, 'submitted' => 0, 'approved' => 0];
        foreach ($data as $row) {
            $totals[$row['status']] = (float) $row['hours'];
        }
        return $totals;
    }

    private function isPrivileged(array $user): bool
    {
        return in_array($user['role'] ?? '', self::ADMIN_ROLES, true);
    }
}
