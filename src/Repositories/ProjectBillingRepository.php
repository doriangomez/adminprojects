<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;

class ProjectBillingRepository
{
    public function __construct(private Database $db)
    {
    }

    public function config(int $projectId): array
    {
        $row = $this->db->fetchOne('SELECT * FROM projects WHERE id = :id LIMIT 1', [':id' => $projectId]) ?? [];

        return [
            'is_billable' => (int) ($row['is_billable'] ?? 0),
            'billing_type' => (string) ($row['billing_type'] ?? 'fixed'),
            'billing_periodicity' => (string) ($row['billing_periodicity'] ?? 'monthly'),
            'contract_value' => (float) ($row['contract_value'] ?? 0),
            'currency_code' => (string) ($row['currency_code'] ?? 'USD'),
            'billing_start_date' => $row['billing_start_date'] ?? null,
            'billing_end_date' => $row['billing_end_date'] ?? null,
            'hourly_rate' => (float) ($row['hourly_rate'] ?? 0),
        ];
    }

    public function updateConfig(int $projectId, array $payload): void
    {
        $this->db->execute(
            'UPDATE projects
             SET is_billable = :is_billable,
                 billing_type = :billing_type,
                 billing_periodicity = :billing_periodicity,
                 contract_value = :contract_value,
                 currency_code = :currency_code,
                 billing_start_date = :billing_start_date,
                 billing_end_date = :billing_end_date,
                 hourly_rate = :hourly_rate,
                 updated_at = NOW()
             WHERE id = :project_id',
            [
                ':project_id' => $projectId,
                ':is_billable' => (int) ($payload['is_billable'] ?? 0),
                ':billing_type' => $payload['billing_type'] ?? 'fixed',
                ':billing_periodicity' => $payload['billing_periodicity'] ?? 'monthly',
                ':contract_value' => (float) ($payload['contract_value'] ?? 0),
                ':currency_code' => strtoupper((string) ($payload['currency_code'] ?? 'USD')),
                ':billing_start_date' => $payload['billing_start_date'] ?? null,
                ':billing_end_date' => $payload['billing_end_date'] ?? null,
                ':hourly_rate' => (float) ($payload['hourly_rate'] ?? 0),
            ]
        );
    }

    public function invoices(int $projectId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM project_invoices WHERE project_id = :project_id ORDER BY issued_at DESC, id DESC',
            [':project_id' => $projectId]
        );
    }

    public function approvedHoursNotInvoiced(int $projectId): float
    {
        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(ts.hours), 0) AS total
             FROM timesheets ts
             WHERE ts.project_id = :project_id
               AND ts.status = "approved"
               AND NOT EXISTS (
                    SELECT 1
                    FROM project_invoice_timesheets pit
                    WHERE pit.timesheet_id = ts.id
               )',
            [':project_id' => $projectId]
        );

        return (float) ($row['total'] ?? 0);
    }

    public function invoiceTotals(int $projectId): array
    {
        $row = $this->db->fetchOne(
            'SELECT
                COALESCE(SUM(amount), 0) AS total_invoiced,
                COALESCE(SUM(CASE WHEN status = "paid" THEN amount ELSE 0 END), 0) AS total_paid,
                COALESCE(SUM(CASE WHEN status IN ("issued","sent","overdue") THEN amount ELSE 0 END), 0) AS total_due
             FROM project_invoices
             WHERE project_id = :project_id
               AND status <> "void"',
            [':project_id' => $projectId]
        ) ?? [];

        return [
            'total_invoiced' => (float) ($row['total_invoiced'] ?? 0),
            'total_paid' => (float) ($row['total_paid'] ?? 0),
            'total_due' => (float) ($row['total_due'] ?? 0),
        ];
    }

    public function createInvoice(int $projectId, array $payload, int $userId): int
    {
        $invoiceId = $this->db->insert(
            'INSERT INTO project_invoices (
                project_id, invoice_number, issued_at, period_start, period_end,
                amount, status, paid_at, notes, attachment_path, created_by
            ) VALUES (
                :project_id, :invoice_number, :issued_at, :period_start, :period_end,
                :amount, :status, :paid_at, :notes, :attachment_path, :created_by
            )',
            [
                ':project_id' => $projectId,
                ':invoice_number' => trim((string) ($payload['invoice_number'] ?? '')),
                ':issued_at' => $payload['issued_at'] ?? null,
                ':period_start' => $payload['period_start'] ?? null,
                ':period_end' => $payload['period_end'] ?? null,
                ':amount' => (float) ($payload['amount'] ?? 0),
                ':status' => $payload['status'] ?? 'issued',
                ':paid_at' => $payload['paid_at'] ?? null,
                ':notes' => trim((string) ($payload['notes'] ?? '')),
                ':attachment_path' => trim((string) ($payload['attachment_path'] ?? '')) ?: null,
                ':created_by' => $userId,
            ]
        );

        $timesheetIds = array_values(array_unique(array_map('intval', $payload['timesheet_ids'] ?? [])));
        foreach ($timesheetIds as $timesheetId) {
            $this->db->execute(
                'INSERT INTO project_invoice_timesheets (invoice_id, timesheet_id)
                 SELECT :invoice_id, :timesheet_id
                 WHERE NOT EXISTS (
                    SELECT 1 FROM project_invoice_timesheets WHERE timesheet_id = :timesheet_id_check
                 )',
                [
                    ':invoice_id' => $invoiceId,
                    ':timesheet_id' => $timesheetId,
                    ':timesheet_id_check' => $timesheetId,
                ]
            );
        }

        return $invoiceId;
    }

    public function missingMonthlyPeriods(int $projectId, ?string $startDate, ?string $endDate): array
    {
        if (!$startDate) {
            return [];
        }

        $start = new \DateTimeImmutable(date('Y-m-01', strtotime($startDate)));
        $limitDate = $endDate ?: date('Y-m-d');
        $end = new \DateTimeImmutable(date('Y-m-01', strtotime($limitDate)));

        $existing = $this->db->fetchAll(
            'SELECT DISTINCT DATE_FORMAT(period_start, "%Y-%m") AS ym
             FROM project_invoices
             WHERE project_id = :project_id AND period_start IS NOT NULL AND status <> "void"',
            [':project_id' => $projectId]
        );
        $indexed = [];
        foreach ($existing as $row) {
            $indexed[(string) ($row['ym'] ?? '')] = true;
        }

        $missing = [];
        for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 month')) {
            $key = $cursor->format('Y-m');
            if (!isset($indexed[$key])) {
                $missing[] = $cursor->format('F Y');
            }
        }

        return $missing;
    }
}
