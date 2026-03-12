<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;

class ProjectBillingRepository
{
    private const PLAN_STATUSES = ['pendiente', 'listo_para_facturar', 'facturado', 'pagado', 'atrasado'];

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

    public function updateBillableStatus(int $projectId, int $isBillable): void
    {
        $this->db->execute(
            'UPDATE projects
             SET is_billable = :is_billable,
                 updated_at = NOW()
             WHERE id = :project_id',
            [
                ':project_id' => $projectId,
                ':is_billable' => $isBillable === 1 ? 1 : 0,
            ]
        );
    }

    public function invoices(int $projectId): array
    {
        if (!$this->db->tableExists('project_invoices')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT * FROM project_invoices WHERE project_id = :project_id ORDER BY issued_at DESC, id DESC',
            [':project_id' => $projectId]
        );
    }

    public function approvedHoursNotInvoiced(int $projectId): float
    {
        if (!$this->db->tableExists('timesheets')) {
            return 0.0;
        }

        $usesProjectColumn = $this->db->columnExists('timesheets', 'project_id');
        $canResolveFromTasks = !$usesProjectColumn
            && $this->db->tableExists('tasks')
            && $this->db->columnExists('timesheets', 'task_id')
            && $this->db->columnExists('tasks', 'project_id');
        if (!$usesProjectColumn && !$canResolveFromTasks) {
            return 0.0;
        }

        $hasInvoicePivot = $this->db->tableExists('project_invoice_timesheets');
        $projectFilter = $usesProjectColumn ? 'ts.project_id = :project_id' : 't.project_id = :project_id';
        $taskJoin = $usesProjectColumn ? '' : 'JOIN tasks t ON t.id = ts.task_id';
        $invoiceExclusion = $hasInvoicePivot
            ? 'AND NOT EXISTS (
                    SELECT 1
                    FROM project_invoice_timesheets pit
                    WHERE pit.timesheet_id = ts.id
               )'
            : '';

        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(ts.hours), 0) AS total
             FROM timesheets ts
             ' . $taskJoin . '
             WHERE ' . $projectFilter . '
               AND ts.status = "approved"
               ' . $invoiceExclusion,
            [':project_id' => $projectId]
        );

        return (float) ($row['total'] ?? 0);
    }

    public function approvedHoursTotal(int $projectId): float
    {
        if (!$this->db->tableExists('timesheets')) {
            return 0.0;
        }

        $usesProjectColumn = $this->db->columnExists('timesheets', 'project_id');
        $canResolveFromTasks = !$usesProjectColumn
            && $this->db->tableExists('tasks')
            && $this->db->columnExists('timesheets', 'task_id')
            && $this->db->columnExists('tasks', 'project_id');
        if (!$usesProjectColumn && !$canResolveFromTasks) {
            return 0.0;
        }

        $projectFilter = $usesProjectColumn ? 'ts.project_id = :project_id' : 't.project_id = :project_id';
        $taskJoin = $usesProjectColumn ? '' : 'JOIN tasks t ON t.id = ts.task_id';
        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(ts.hours), 0) AS total
             FROM timesheets ts
             ' . $taskJoin . '
             WHERE ' . $projectFilter . '
               AND ts.status = "approved"',
            [':project_id' => $projectId]
        );

        return (float) ($row['total'] ?? 0);
    }

    public function invoiceTotals(int $projectId): array
    {
        if (!$this->db->tableExists('project_invoices')) {
            return [
                'total_invoiced' => 0.0,
                'total_paid' => 0.0,
                'total_due' => 0.0,
            ];
        }

        $row = $this->db->fetchOne(
            'SELECT
                COALESCE(SUM(amount), 0) AS total_invoiced,
                COALESCE(SUM(CASE WHEN status = "paid" THEN amount ELSE 0 END), 0) AS total_paid,
                COALESCE(SUM(CASE WHEN status = "issued" THEN amount ELSE 0 END), 0) AS total_due
             FROM project_invoices
             WHERE project_id = :project_id
               AND status <> "cancelled"',
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

        $this->matchPlanItemsWithInvoice($projectId, $invoiceId, [
            'amount' => (float) ($payload['amount'] ?? 0),
            'period_start' => $payload['period_start'] ?? null,
            'period_end' => $payload['period_end'] ?? null,
            'status' => $payload['status'] ?? 'issued',
        ]);

        return $invoiceId;
    }

    public function missingMonthlyPeriods(int $projectId, ?string $startDate, ?string $endDate): array
    {
        if (!$startDate || !$this->db->tableExists('project_invoices')) {
            return [];
        }

        $start = new \DateTimeImmutable(date('Y-m-01', strtotime($startDate)));
        $limitDate = $endDate ?: date('Y-m-d');
        $end = new \DateTimeImmutable(date('Y-m-01', strtotime($limitDate)));

        $existing = $this->db->fetchAll(
            'SELECT DISTINCT DATE_FORMAT(period_start, "%Y-%m") AS ym
             FROM project_invoices
             WHERE project_id = :project_id AND period_start IS NOT NULL AND status <> "cancelled"',
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

    public function updateInvoice(int $projectId, int $invoiceId, array $payload): void
    {
        $this->db->execute(
            'UPDATE project_invoices
             SET invoice_number = :invoice_number,
                 issued_at = :issued_at,
                 period_start = :period_start,
                 period_end = :period_end,
                 amount = :amount,
                 status = :status,
                 paid_at = :paid_at,
                 notes = :notes,
                 attachment_path = :attachment_path,
                 updated_at = NOW()
             WHERE id = :invoice_id AND project_id = :project_id',
            [
                ':invoice_id' => $invoiceId,
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
            ]
        );

        $this->matchPlanItemsWithInvoice($projectId, $invoiceId, [
            'amount' => (float) ($payload['amount'] ?? 0),
            'period_start' => $payload['period_start'] ?? null,
            'period_end' => $payload['period_end'] ?? null,
            'status' => $payload['status'] ?? 'issued',
        ]);
    }

    public function updateInvoiceStatus(int $projectId, int $invoiceId, string $status, ?string $paidAt = null): void
    {
        $this->db->execute(
            'UPDATE project_invoices
             SET status = :status,
                 paid_at = :paid_at,
                 updated_at = NOW()
             WHERE id = :invoice_id AND project_id = :project_id',
            [
                ':status' => $status,
                ':paid_at' => $paidAt,
                ':invoice_id' => $invoiceId,
                ':project_id' => $projectId,
            ]
        );

        if ($this->db->tableExists('project_billing_plan') && in_array($status, ['issued', 'paid'], true)) {
            $planStatus = $status === 'paid' ? 'pagado' : 'facturado';
            $this->db->execute(
                'UPDATE project_billing_plan
                 SET status = :plan_status,
                     updated_at = NOW()
                 WHERE project_id = :project_id
                   AND invoice_id = :invoice_id',
                [
                    ':plan_status' => $planStatus,
                    ':project_id' => $projectId,
                    ':invoice_id' => $invoiceId,
                ]
            );
        }
    }

    public function deleteInvoice(int $projectId, int $invoiceId): void
    {
        if ($this->db->tableExists('project_billing_plan')) {
            $this->db->execute(
                'UPDATE project_billing_plan
                 SET status = CASE WHEN status = "pagado" THEN "pagado" ELSE "pendiente" END,
                     invoice_id = NULL,
                     updated_at = NOW()
                 WHERE project_id = :project_id
                   AND invoice_id = :invoice_id',
                [
                    ':project_id' => $projectId,
                    ':invoice_id' => $invoiceId,
                ]
            );
        }

        $this->db->execute('DELETE FROM project_invoices WHERE id = :invoice_id AND project_id = :project_id', [
            ':invoice_id' => $invoiceId,
            ':project_id' => $projectId,
        ]);
    }

    public function billingPlan(int $projectId): array
    {
        if (!$this->db->tableExists('project_billing_plan')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT *
             FROM project_billing_plan
             WHERE project_id = :project_id
             ORDER BY COALESCE(expected_date, DATE(created_at)) ASC, id ASC',
            [':project_id' => $projectId]
        );
    }

    public function createPlanItem(int $projectId, array $payload): int
    {
        if (!$this->db->tableExists('project_billing_plan')) {
            return 0;
        }

        return $this->db->insert(
            'INSERT INTO project_billing_plan (
                project_id, billing_model, concept, milestone_name, percentage, amount,
                billing_frequency, expected_trigger, expected_date, status, invoice_id
            ) VALUES (
                :project_id, :billing_model, :concept, :milestone_name, :percentage, :amount,
                :billing_frequency, :expected_trigger, :expected_date, :status, :invoice_id
            )',
            [
                ':project_id' => $projectId,
                ':billing_model' => $payload['billing_model'] ?? 'milestones',
                ':concept' => trim((string) ($payload['concept'] ?? '')),
                ':milestone_name' => trim((string) ($payload['milestone_name'] ?? '')),
                ':percentage' => isset($payload['percentage']) && $payload['percentage'] !== '' ? (float) $payload['percentage'] : null,
                ':amount' => isset($payload['amount']) && $payload['amount'] !== '' ? (float) $payload['amount'] : null,
                ':billing_frequency' => trim((string) ($payload['billing_frequency'] ?? '')) ?: null,
                ':expected_trigger' => trim((string) ($payload['expected_trigger'] ?? '')) ?: null,
                ':expected_date' => $payload['expected_date'] ?? null,
                ':status' => in_array($payload['status'] ?? '', self::PLAN_STATUSES, true) ? $payload['status'] : 'pendiente',
                ':invoice_id' => isset($payload['invoice_id']) ? (int) $payload['invoice_id'] : null,
            ]
        );
    }

    public function financialControlSummary(int $projectId, array $billingConfig, array $invoiceTotals, float $approvedHoursTotal): array
    {
        $items = $this->billingPlan($projectId);
        $today = date('Y-m-d');
        $contractValue = (float) ($billingConfig['contract_value'] ?? 0);
        $totalIssued = (float) ($invoiceTotals['total_invoiced'] ?? 0);
        $totalPaid = (float) ($invoiceTotals['total_paid'] ?? 0);
        $expected = 0.0;
        $overdue = 0.0;

        foreach ($items as &$item) {
            $itemAmount = $this->resolvePlanItemAmount($item, $contractValue);
            $item['resolved_amount'] = $itemAmount;
            $expectedDate = (string) ($item['expected_date'] ?? '');
            $status = (string) ($item['status'] ?? 'pendiente');
            $isDue = $expectedDate !== '' && $expectedDate <= $today;
            $considerExpected = $isDue || in_array($status, ['listo_para_facturar', 'facturado', 'pagado', 'atrasado'], true);
            if ($considerExpected) {
                $expected += $itemAmount;
            }
            if ($isDue && !in_array($status, ['facturado', 'pagado'], true)) {
                $overdue += $itemAmount;
                if ($status !== 'atrasado') {
                    $item['status'] = 'atrasado';
                }
            }
        }
        unset($item);

        $forecast = (($billingConfig['billing_type'] ?? 'fixed') === 'fixed')
            ? max(0.0, $contractValue - $totalIssued)
            : max(0.0, $approvedHoursTotal * (float) ($billingConfig['hourly_rate'] ?? 0));

        return [
            'items' => $items,
            'expected_billing' => $expected,
            'issued_billing' => $totalIssued,
            'pending_billing' => max(0.0, $expected - $totalIssued),
            'overdue_billing' => $overdue,
            'total_paid' => $totalPaid,
            'forecast_revenue' => $forecast,
        ];
    }

    private function resolvePlanItemAmount(array $item, float $contractValue): float
    {
        $amount = isset($item['amount']) ? (float) $item['amount'] : 0.0;
        $percentage = isset($item['percentage']) ? (float) $item['percentage'] : 0.0;
        if ($amount > 0) {
            return $amount;
        }

        if ($percentage > 0) {
            return ($percentage / 100) * $contractValue;
        }

        return 0.0;
    }

    private function matchPlanItemsWithInvoice(int $projectId, int $invoiceId, array $invoiceData): void
    {
        if (!$this->db->tableExists('project_billing_plan')) {
            return;
        }

        $invoiceAmount = (float) ($invoiceData['amount'] ?? 0);
        $periodStart = $invoiceData['period_start'] ?? null;
        $periodEnd = $invoiceData['period_end'] ?? null;
        $status = (string) ($invoiceData['status'] ?? 'issued');
        $planStatus = $status === 'paid' ? 'pagado' : 'facturado';

        $rows = $this->db->fetchAll(
            'SELECT id, expected_date, amount
             FROM project_billing_plan
             WHERE project_id = :project_id
               AND invoice_id IS NULL',
            [':project_id' => $projectId]
        );

        foreach ($rows as $row) {
            $expectedDate = $row['expected_date'] ?? null;
            $amount = (float) ($row['amount'] ?? 0);
            $dateMatches = $expectedDate === null || $expectedDate === '' || (
                ($periodStart !== null && $periodStart !== '' && $expectedDate >= $periodStart)
                && ($periodEnd === null || $periodEnd === '' || $expectedDate <= $periodEnd)
            );
            $amountMatches = $amount <= 0.0 || abs($amount - $invoiceAmount) <= 0.01;

            if (!$dateMatches || !$amountMatches) {
                continue;
            }

            $this->db->execute(
                'UPDATE project_billing_plan
                 SET status = :status,
                     invoice_id = :invoice_id,
                     updated_at = NOW()
                 WHERE id = :id',
                [
                    ':status' => $planStatus,
                    ':invoice_id' => $invoiceId,
                    ':id' => (int) ($row['id'] ?? 0),
                ]
            );
            break;
        }
    }
}
