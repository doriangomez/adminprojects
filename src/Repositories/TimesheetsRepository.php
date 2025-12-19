<?php

declare(strict_types=1);

class TimesheetsRepository
{
    public function __construct(private Database $db)
    {
    }

    public function weekly(): array
    {
        return $this->db->fetchAll(
            'SELECT ts.id, ts.date, ts.hours, ts.status, ts.billable, p.name AS project, t.title AS task, ta.name AS talent
             FROM timesheets ts
             JOIN tasks t ON t.id = ts.task_id
             JOIN projects p ON p.id = t.project_id
             JOIN talents ta ON ta.id = ts.talent_id
             ORDER BY ts.date DESC'
        );
    }

    public function kpis(): array
    {
        $data = $this->db->fetchAll("SELECT status, SUM(hours) AS hours FROM timesheets GROUP BY status");
        $totals = ['draft' => 0, 'submitted' => 0, 'approved' => 0];
        foreach ($data as $row) {
            $totals[$row['status']] = (float) $row['hours'];
        }
        return $totals;
    }
}
