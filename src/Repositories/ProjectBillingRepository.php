<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;

class ProjectBillingRepository
{
    private const CONTRACT_CURRENCIES = ['COP', 'USD', 'EUR', 'MXN'];
    private const PLAN_TYPES = ['anticipo', 'mensualidad_fija', 'hito_entregable', 'porcentaje_avance'];
    private const PLAN_STATUSES = ['pendiente', 'proximo', 'listo_para_emitir', 'emitido', 'pagado', 'atrasado'];

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
            'currency_code' => $this->normalizeCurrency((string) ($row['currency_code'] ?? 'USD')),
            'billing_start_date' => $row['billing_start_date'] ?? null,
            'billing_end_date' => $row['billing_end_date'] ?? null,
            'hourly_rate' => (float) ($row['hourly_rate'] ?? 0),
            'contract_notes' => trim((string) ($row['contract_notes'] ?? '')),
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
                 contract_notes = :contract_notes,
                 updated_at = NOW()
             WHERE id = :project_id',
            [
                ':project_id' => $projectId,
                ':is_billable' => (int) ($payload['is_billable'] ?? 0),
                ':billing_type' => $payload['billing_type'] ?? 'fixed',
                ':billing_periodicity' => $payload['billing_periodicity'] ?? 'monthly',
                ':contract_value' => (float) ($payload['contract_value'] ?? 0),
                ':currency_code' => $this->normalizeCurrency((string) ($payload['currency_code'] ?? 'USD')),
                ':billing_start_date' => $this->normalizeDate($payload['billing_start_date'] ?? null),
                ':billing_end_date' => $this->normalizeDate($payload['billing_end_date'] ?? null),
                ':hourly_rate' => (float) ($payload['hourly_rate'] ?? 0),
                ':contract_notes' => trim((string) ($payload['contract_notes'] ?? '')),
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

        $usesPlanPivot = $this->db->tableExists('project_invoice_plan_items');
        $rows = $this->db->fetchAll(
            $usesPlanPivot
                ? 'SELECT i.*,
                          COALESCE(COUNT(DISTINCT pip.plan_item_id), 0) AS covered_items_count,
                          COALESCE(GROUP_CONCAT(DISTINCT NULLIF(bp.concept, "") ORDER BY bp.id SEPARATOR ", "), "") AS covered_items
                   FROM project_invoices i
                   LEFT JOIN project_invoice_plan_items pip ON pip.invoice_id = i.id
                   LEFT JOIN project_billing_plan bp ON bp.id = pip.plan_item_id
                   WHERE i.project_id = :project_id
                   GROUP BY i.id
                   ORDER BY i.issued_at DESC, i.id DESC'
                : 'SELECT i.*,
                          COALESCE(COUNT(DISTINCT bp.id), 0) AS covered_items_count,
                          COALESCE(GROUP_CONCAT(DISTINCT NULLIF(bp.concept, "") ORDER BY bp.id SEPARATOR ", "), "") AS covered_items
                   FROM project_invoices i
                   LEFT JOIN project_billing_plan bp ON bp.invoice_id = i.id
                   WHERE i.project_id = :project_id
                   GROUP BY i.id
                   ORDER BY i.issued_at DESC, i.id DESC',
            [':project_id' => $projectId]
        );

        $linkedMap = $this->invoicePlanItemMap($projectId);
        foreach ($rows as &$row) {
            $invoiceId = (int) ($row['id'] ?? 0);
            $row['selected_plan_item_ids'] = $linkedMap[$invoiceId] ?? [];
            $row['currency_code'] = $this->normalizeCurrency((string) ($row['currency_code'] ?? 'USD'));
        }
        unset($row);

        return $rows;
    }

    public function findInvoice(int $projectId, int $invoiceId): ?array
    {
        if (!$this->db->tableExists('project_invoices')) {
            return null;
        }

        $row = $this->db->fetchOne(
            'SELECT * FROM project_invoices WHERE id = :id AND project_id = :project_id LIMIT 1',
            [':id' => $invoiceId, ':project_id' => $projectId]
        );
        if (!$row) {
            return null;
        }

        $row['selected_plan_item_ids'] = $this->invoicePlanItemMap($projectId)[$invoiceId] ?? [];
        $row['currency_code'] = $this->normalizeCurrency((string) ($row['currency_code'] ?? 'USD'));

        return $row;
    }

    public function invoiceTotals(int $projectId): array
    {
        if (!$this->db->tableExists('project_invoices')) {
            return ['total_invoiced' => 0.0];
        }

        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(amount), 0) AS total_invoiced
             FROM project_invoices
             WHERE project_id = :project_id
               AND COALESCE(status, "issued") <> "cancelled"',
            [':project_id' => $projectId]
        ) ?? [];

        return [
            'total_invoiced' => (float) ($row['total_invoiced'] ?? 0),
        ];
    }

    public function createInvoice(int $projectId, array $payload, int $userId): int
    {
        $invoiceId = $this->db->insert(
            'INSERT INTO project_invoices (
                project_id, invoice_number, issued_at, period_start, period_end, amount,
                status, notes, attachment_path, currency_code, created_by
            ) VALUES (
                :project_id, :invoice_number, :issued_at, :period_start, :period_end, :amount,
                :status, :notes, :attachment_path, :currency_code, :created_by
            )',
            [
                ':project_id' => $projectId,
                ':invoice_number' => trim((string) ($payload['invoice_number'] ?? '')),
                ':issued_at' => $this->normalizeDate($payload['issued_at'] ?? null),
                ':period_start' => $this->normalizeDate($payload['period_start'] ?? null),
                ':period_end' => $this->normalizeDate($payload['period_end'] ?? null),
                ':amount' => (float) ($payload['amount'] ?? 0),
                ':status' => 'issued',
                ':notes' => trim((string) ($payload['notes'] ?? '')),
                ':attachment_path' => trim((string) ($payload['attachment_path'] ?? '')) ?: null,
                ':currency_code' => $this->normalizeCurrency((string) ($payload['currency_code'] ?? 'USD')),
                ':created_by' => $userId,
            ]
        );

        $this->syncInvoicePlanItems($projectId, $invoiceId, $this->normalizeArrayOfInts($payload['plan_item_ids'] ?? []));

        return $invoiceId;
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
                 notes = :notes,
                 attachment_path = :attachment_path,
                 currency_code = :currency_code,
                 updated_at = NOW()
             WHERE id = :invoice_id AND project_id = :project_id',
            [
                ':invoice_id' => $invoiceId,
                ':project_id' => $projectId,
                ':invoice_number' => trim((string) ($payload['invoice_number'] ?? '')),
                ':issued_at' => $this->normalizeDate($payload['issued_at'] ?? null),
                ':period_start' => $this->normalizeDate($payload['period_start'] ?? null),
                ':period_end' => $this->normalizeDate($payload['period_end'] ?? null),
                ':amount' => (float) ($payload['amount'] ?? 0),
                ':status' => 'issued',
                ':notes' => trim((string) ($payload['notes'] ?? '')),
                ':attachment_path' => trim((string) ($payload['attachment_path'] ?? '')) ?: null,
                ':currency_code' => $this->normalizeCurrency((string) ($payload['currency_code'] ?? 'USD')),
            ]
        );

        $this->syncInvoicePlanItems($projectId, $invoiceId, $this->normalizeArrayOfInts($payload['plan_item_ids'] ?? []));
    }

    public function updateInvoiceStatus(int $projectId, int $invoiceId, string $status, ?string $paidAt = null): void
    {
        $normalized = strtolower(trim($status));
        if (!in_array($normalized, ['issued', 'cancelled'], true)) {
            $normalized = 'issued';
        }

        $this->db->execute(
            'UPDATE project_invoices
             SET status = :status,
                 updated_at = NOW()
             WHERE id = :invoice_id AND project_id = :project_id',
            [
                ':status' => $normalized,
                ':invoice_id' => $invoiceId,
                ':project_id' => $projectId,
            ]
        );

        if ($normalized === 'cancelled' && $this->db->tableExists('project_billing_plan')) {
            $this->syncInvoicePlanItems($projectId, $invoiceId, []);
        }
    }

    public function deleteInvoice(int $projectId, int $invoiceId): void
    {
        $this->syncInvoicePlanItems($projectId, $invoiceId, []);
        $this->db->execute(
            'DELETE FROM project_invoices WHERE id = :invoice_id AND project_id = :project_id',
            [':invoice_id' => $invoiceId, ':project_id' => $projectId]
        );
    }

    public function planItemsForInvoiceSelection(int $projectId): array
    {
        $items = $this->billingPlan($projectId);
        $rows = [];
        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? 'pendiente');
            $rows[] = [
                'id' => (int) ($item['id'] ?? 0),
                'concept' => (string) ($item['concept'] ?? ''),
                'status' => $status,
                'invoice_id' => isset($item['invoice_id']) && $item['invoice_id'] !== null ? (int) $item['invoice_id'] : null,
                'selectable_for_new' => empty($item['invoice_id']),
            ];
        }

        return $rows;
    }

    public function availablePlanItemsForInvoicing(int $projectId): array
    {
        return array_values(array_filter(
            $this->billingPlan($projectId),
            static fn (array $item): bool => empty($item['invoice_id'])
        ));
    }

    public function billingPlan(int $projectId): array
    {
        if (!$this->db->tableExists('project_billing_plan')) {
            return [];
        }

        $this->syncPlanStatuses($projectId);
        $config = $this->config($projectId);
        $contractValue = (float) ($config['contract_value'] ?? 0);
        $rows = $this->db->fetchAll(
            'SELECT bp.*,
                    i.invoice_number AS linked_invoice_number
             FROM project_billing_plan bp
             LEFT JOIN project_invoices i ON i.id = bp.invoice_id
             WHERE bp.project_id = :project_id
             ORDER BY COALESCE(bp.expected_date, "9999-12-31") ASC, bp.id ASC',
            [':project_id' => $projectId]
        );

        foreach ($rows as &$row) {
            $row['item_type'] = $this->normalizePlanType((string) ($row['item_type'] ?? ''), (string) ($row['billing_model'] ?? ''));
            $row['status'] = $this->mapLegacyStatus((string) ($row['status'] ?? 'pendiente'));
            $row['resolved_amount'] = $this->resolvePlanItemAmount($row, $contractValue);
            $row['type_label'] = $this->planTypeLabel((string) $row['item_type']);
            $row['condition_text'] = trim((string) ($row['condition_text'] ?? $row['expected_trigger'] ?? ''));
            $row['notes'] = trim((string) ($row['notes'] ?? ''));
            $row['progress_required_percentage'] = isset($row['progress_required_percentage']) && $row['progress_required_percentage'] !== null
                ? (float) $row['progress_required_percentage']
                : null;
            $row['percentage'] = isset($row['percentage']) && $row['percentage'] !== null ? (float) $row['percentage'] : null;
            $row['amount'] = isset($row['amount']) && $row['amount'] !== null ? (float) $row['amount'] : null;
            $traffic = $this->billingTrafficLight($row, date('Y-m-d'));
            $row['traffic_light'] = $traffic['light'];
            $row['days_to_issue'] = $traffic['days_to_issue'];
            $row['is_invoice_linked'] = !empty($row['invoice_id']);
        }
        unset($row);

        return $rows;
    }

    public function findPlanItem(int $projectId, int $itemId): ?array
    {
        if (!$this->db->tableExists('project_billing_plan')) {
            return null;
        }

        $this->syncPlanStatuses($projectId);
        $row = $this->db->fetchOne(
            'SELECT * FROM project_billing_plan WHERE id = :id AND project_id = :project_id LIMIT 1',
            [':id' => $itemId, ':project_id' => $projectId]
        );
        if (!$row) {
            return null;
        }

        $row['item_type'] = $this->normalizePlanType((string) ($row['item_type'] ?? ''), (string) ($row['billing_model'] ?? ''));
        $row['status'] = $this->mapLegacyStatus((string) ($row['status'] ?? 'pendiente'));

        return $row;
    }

    public function createPlanItem(int $projectId, array $payload): array
    {
        if (!$this->db->tableExists('project_billing_plan')) {
            return [];
        }

        $itemType = $this->normalizePlanType((string) ($payload['item_type'] ?? ''), null);
        if ($itemType === 'mensualidad_fija') {
            $items = $this->expandMonthlyItems($payload);
            $inserted = [];
            foreach ($items as $item) {
                $inserted[] = $this->insertPlanItem($projectId, $item);
            }
            $this->syncPlanStatuses($projectId);

            return $inserted;
        }

        $id = $this->insertPlanItem($projectId, $payload);
        $this->syncPlanStatuses($projectId);

        return [$id];
    }

    public function updatePlanItem(int $projectId, int $itemId, array $payload): void
    {
        if (!$this->db->tableExists('project_billing_plan')) {
            return;
        }

        $itemType = $this->normalizePlanType((string) ($payload['item_type'] ?? ''), null);
        $percentage = isset($payload['percentage']) && $payload['percentage'] !== '' ? (float) $payload['percentage'] : null;
        $amount = isset($payload['amount']) && $payload['amount'] !== '' ? (float) $payload['amount'] : null;
        $progressRequired = isset($payload['progress_required_percentage']) && $payload['progress_required_percentage'] !== ''
            ? (float) $payload['progress_required_percentage']
            : null;
        $conditionText = trim((string) ($payload['condition_text'] ?? ''));

        $this->db->execute(
            'UPDATE project_billing_plan
             SET billing_model = :billing_model,
                 item_type = :item_type,
                 concept = :concept,
                 milestone_name = :milestone_name,
                 percentage = :percentage,
                 progress_required_percentage = :progress_required_percentage,
                 amount = :amount,
                 billing_frequency = :billing_frequency,
                 expected_trigger = :expected_trigger,
                 expected_date = :expected_date,
                 condition_text = :condition_text,
                 condition_met = :condition_met,
                 notes = :notes,
                 linked_schedule_activity_id = :linked_schedule_activity_id,
                 day_of_month = :day_of_month,
                 updated_at = NOW()
             WHERE id = :id AND project_id = :project_id',
            [
                ':billing_model' => $this->legacyModelForPlanType($itemType),
                ':item_type' => $itemType,
                ':concept' => trim((string) ($payload['concept'] ?? '')),
                ':milestone_name' => trim((string) ($payload['milestone_name'] ?? '')),
                ':percentage' => $percentage,
                ':progress_required_percentage' => $progressRequired,
                ':amount' => $amount,
                ':billing_frequency' => trim((string) ($payload['billing_frequency'] ?? '')) ?: null,
                ':expected_trigger' => $conditionText !== '' ? $conditionText : null,
                ':expected_date' => $this->normalizeDate($payload['expected_date'] ?? null),
                ':condition_text' => $conditionText !== '' ? $conditionText : null,
                ':condition_met' => $this->normalizeConditionMet($payload['condition_met'] ?? 0),
                ':notes' => trim((string) ($payload['notes'] ?? '')),
                ':linked_schedule_activity_id' => isset($payload['linked_schedule_activity_id']) && (int) $payload['linked_schedule_activity_id'] > 0
                    ? (int) $payload['linked_schedule_activity_id']
                    : null,
                ':day_of_month' => isset($payload['day_of_month']) && $payload['day_of_month'] !== ''
                    ? (int) $payload['day_of_month']
                    : null,
                ':id' => $itemId,
                ':project_id' => $projectId,
            ]
        );

        if (($payload['status_override'] ?? '') === 'pagado') {
            $this->db->execute(
                'UPDATE project_billing_plan
                 SET status = "pagado",
                     updated_at = NOW()
                 WHERE id = :id
                   AND project_id = :project_id
                   AND invoice_id IS NOT NULL',
                [
                    ':id' => $itemId,
                    ':project_id' => $projectId,
                ]
            );
        }

        $this->syncPlanStatuses($projectId);
    }

    public function deletePlanItem(int $projectId, int $itemId): void
    {
        if (!$this->db->tableExists('project_billing_plan')) {
            return;
        }

        if ($this->db->tableExists('project_invoice_plan_items')) {
            $this->db->execute(
                'DELETE FROM project_invoice_plan_items WHERE plan_item_id = :plan_item_id',
                [':plan_item_id' => $itemId]
            );
        }

        $this->db->execute(
            'DELETE FROM project_billing_plan WHERE id = :id AND project_id = :project_id',
            [':id' => $itemId, ':project_id' => $projectId]
        );
    }

    public function financialSummary(int $projectId, ?array $billingConfig = null): array
    {
        $config = $billingConfig ?? $this->config($projectId);
        $items = $this->billingPlan($projectId);
        $totals = $this->invoiceTotals($projectId);
        $totalPlan = 0.0;
        $counts = [
            'pendiente' => 0,
            'proximo' => 0,
            'listo_para_emitir' => 0,
            'emitido' => 0,
            'pagado' => 0,
            'atrasado' => 0,
        ];
        $totalPaid = 0.0;
        $control = [
            'on_track' => 0,
            'upcoming' => 0,
            'overdue' => 0,
            'future' => 0,
        ];

        foreach ($items as $item) {
            $totalPlan += (float) ($item['resolved_amount'] ?? 0);
            $status = (string) ($item['status'] ?? 'pendiente');
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
            if ($status === 'pagado') {
                $totalPaid += (float) ($item['resolved_amount'] ?? 0);
            }
            $light = (string) ($item['traffic_light'] ?? 'gray');
            if ($light === 'green') {
                $control['on_track']++;
            } elseif ($light === 'yellow') {
                $control['upcoming']++;
            } elseif ($light === 'red') {
                $control['overdue']++;
            } else {
                $control['future']++;
            }
        }

        $totalContract = (float) ($config['contract_value'] ?? 0);
        $totalInvoiced = (float) ($totals['total_invoiced'] ?? 0);
        $planDifference = $totalContract - $totalPlan;
        $invoicedVsPlanPercent = $totalPlan > 0
            ? round(min(100.0, ($totalInvoiced / $totalPlan) * 100), 2)
            : 0.0;

        return [
            'total_contract' => $totalContract,
            'total_plan' => $totalPlan,
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'balance_to_invoice' => $totalContract - $totalInvoiced,
            'overdue_items_count' => $counts['atrasado'],
            'upcoming_items_count' => $counts['proximo'],
            'attention_items_count' => $counts['proximo'] + $counts['listo_para_emitir'] + $counts['atrasado'],
            'plan_difference' => $planDifference,
            'counts' => $counts,
            'control_summary' => $control,
            'invoiced_vs_plan_percentage' => $invoicedVsPlanPercent,
        ];
    }

    public function exportCsvRows(int $projectId): array
    {
        $plan = $this->billingPlan($projectId);
        $invoices = $this->invoices($projectId);
        $rows = [];

        foreach ($plan as $item) {
            $rows[] = [
                'record_type' => 'plan_item',
                'type_or_invoice' => $item['type_label'] ?? '',
                'concept_or_date' => (string) ($item['concept'] ?? ''),
                'value' => (float) ($item['resolved_amount'] ?? 0),
                'currency' => '',
                'expected_or_issued_date' => (string) ($item['expected_date'] ?? ''),
                'condition_or_items' => (string) ($item['condition_text'] ?? ''),
                'status' => (string) ($item['status'] ?? ''),
                'invoice_number' => (string) ($item['linked_invoice_number'] ?? ''),
                'notes' => (string) ($item['notes'] ?? ''),
            ];
        }

        foreach ($invoices as $invoice) {
            $rows[] = [
                'record_type' => 'invoice',
                'type_or_invoice' => (string) ($invoice['invoice_number'] ?? ''),
                'concept_or_date' => (string) ($invoice['issued_at'] ?? ''),
                'value' => (float) ($invoice['amount'] ?? 0),
                'currency' => $this->normalizeCurrency((string) ($invoice['currency_code'] ?? 'USD')),
                'expected_or_issued_date' => (string) ($invoice['issued_at'] ?? ''),
                'condition_or_items' => (string) ($invoice['covered_items'] ?? ''),
                'status' => 'emitida',
                'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
                'notes' => (string) ($invoice['notes'] ?? ''),
            ];
        }

        return $rows;
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
             WHERE project_id = :project_id
               AND period_start IS NOT NULL
               AND COALESCE(status, "issued") <> "cancelled"',
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

    private function insertPlanItem(int $projectId, array $payload): int
    {
        $itemType = $this->normalizePlanType((string) ($payload['item_type'] ?? ''), null);
        $percentage = isset($payload['percentage']) && $payload['percentage'] !== '' ? (float) $payload['percentage'] : null;
        $amount = isset($payload['amount']) && $payload['amount'] !== '' ? (float) $payload['amount'] : null;
        $progressRequired = isset($payload['progress_required_percentage']) && $payload['progress_required_percentage'] !== ''
            ? (float) $payload['progress_required_percentage']
            : null;
        $conditionText = trim((string) ($payload['condition_text'] ?? ''));
        $dayOfMonth = isset($payload['day_of_month']) && $payload['day_of_month'] !== '' ? (int) $payload['day_of_month'] : null;
        if ($dayOfMonth !== null) {
            $dayOfMonth = max(1, min(28, $dayOfMonth));
        }

        return $this->db->insert(
            'INSERT INTO project_billing_plan (
                project_id, billing_model, item_type, concept, milestone_name, percentage,
                progress_required_percentage, amount, billing_frequency, expected_trigger, expected_date,
                condition_text, condition_met, notes, linked_schedule_activity_id, day_of_month, series_key,
                status, invoice_id
            ) VALUES (
                :project_id, :billing_model, :item_type, :concept, :milestone_name, :percentage,
                :progress_required_percentage, :amount, :billing_frequency, :expected_trigger, :expected_date,
                :condition_text, :condition_met, :notes, :linked_schedule_activity_id, :day_of_month, :series_key,
                :status, NULL
            )',
            [
                ':project_id' => $projectId,
                ':billing_model' => $this->legacyModelForPlanType($itemType),
                ':item_type' => $itemType,
                ':concept' => trim((string) ($payload['concept'] ?? '')),
                ':milestone_name' => trim((string) ($payload['milestone_name'] ?? '')),
                ':percentage' => $percentage,
                ':progress_required_percentage' => $progressRequired,
                ':amount' => $amount,
                ':billing_frequency' => trim((string) ($payload['billing_frequency'] ?? '')) ?: null,
                ':expected_trigger' => $conditionText !== '' ? $conditionText : null,
                ':expected_date' => $this->normalizeDate($payload['expected_date'] ?? null),
                ':condition_text' => $conditionText !== '' ? $conditionText : null,
                ':condition_met' => $this->normalizeConditionMet($payload['condition_met'] ?? 0),
                ':notes' => trim((string) ($payload['notes'] ?? '')),
                ':linked_schedule_activity_id' => isset($payload['linked_schedule_activity_id']) && (int) $payload['linked_schedule_activity_id'] > 0
                    ? (int) $payload['linked_schedule_activity_id']
                    : null,
                ':day_of_month' => $dayOfMonth,
                ':series_key' => trim((string) ($payload['series_key'] ?? '')) ?: null,
                ':status' => 'pendiente',
            ]
        );
    }

    private function expandMonthlyItems(array $payload): array
    {
        $startDateRaw = (string) ($payload['start_date'] ?? '');
        $endDateRaw = (string) ($payload['end_date'] ?? '');
        $startDate = $this->normalizeDate($startDateRaw);
        $endDate = $this->normalizeDate($endDateRaw);
        $dayOfMonth = (int) ($payload['day_of_month'] ?? 1);
        $dayOfMonth = max(1, min(28, $dayOfMonth));
        if ($startDate === null || $endDate === null) {
            throw new \InvalidArgumentException('Debes definir fecha de inicio y fecha de fin para la mensualidad.');
        }
        if (strtotime($endDate) < strtotime($startDate)) {
            throw new \InvalidArgumentException('La fecha de fin de la mensualidad no puede ser anterior al inicio.');
        }

        $seriesKey = 'monthly-' . md5($startDate . $endDate . $dayOfMonth . '-' . microtime(true));
        $start = new \DateTimeImmutable(date('Y-m-01', strtotime($startDate)));
        $end = new \DateTimeImmutable(date('Y-m-01', strtotime($endDate)));
        $items = [];
        $baseConcept = trim((string) ($payload['concept'] ?? 'Mensualidad fija'));
        $amount = isset($payload['amount']) && $payload['amount'] !== '' ? (float) $payload['amount'] : null;

        for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 month')) {
            $expected = $cursor->format(sprintf('Y-m-%02d', $dayOfMonth));
            if ($expected < $startDate) {
                $expected = $startDate;
            }
            if ($expected > $endDate) {
                continue;
            }
            $items[] = [
                'item_type' => 'mensualidad_fija',
                'concept' => $baseConcept . ' · ' . $cursor->format('Y-m'),
                'amount' => $amount,
                'billing_frequency' => 'monthly',
                'expected_date' => $expected,
                'condition_text' => trim((string) ($payload['condition_text'] ?? '')),
                'notes' => trim((string) ($payload['notes'] ?? '')),
                'day_of_month' => $dayOfMonth,
                'series_key' => $seriesKey,
                'condition_met' => 0,
                'milestone_name' => '',
                'percentage' => null,
                'progress_required_percentage' => null,
                'linked_schedule_activity_id' => null,
            ];
        }

        if ($items === []) {
            throw new \InvalidArgumentException('No se pudieron generar mensualidades con las fechas suministradas.');
        }

        return $items;
    }

    private function syncInvoicePlanItems(int $projectId, int $invoiceId, array $planItemIds): void
    {
        if (!$this->db->tableExists('project_billing_plan')) {
            return;
        }

        $this->syncPlanStatuses($projectId);
        $currentIds = $this->invoicePlanItemMap($projectId)[$invoiceId] ?? [];
        if ($currentIds !== []) {
            $inList = implode(',', array_map('intval', $currentIds));
            $this->db->execute(
                'UPDATE project_billing_plan
                 SET invoice_id = NULL,
                    status = CASE
                        WHEN condition_met = 1 THEN "listo_para_emitir"
                        WHEN expected_date IS NULL THEN "pendiente"
                        WHEN expected_date < CURDATE() THEN "atrasado"
                        WHEN expected_date = CURDATE() THEN "listo_para_emitir"
                        ELSE "listo_para_emitir"
                    END,
                     updated_at = NOW()
                 WHERE project_id = :project_id
                   AND id IN (' . $inList . ')
                   AND invoice_id = :invoice_id',
                [':project_id' => $projectId, ':invoice_id' => $invoiceId]
            );
        } else {
            $this->db->execute(
                'UPDATE project_billing_plan
                 SET invoice_id = NULL,
                    status = CASE
                        WHEN condition_met = 1 THEN "listo_para_emitir"
                        WHEN expected_date IS NULL THEN "pendiente"
                        WHEN expected_date < CURDATE() THEN "atrasado"
                        WHEN expected_date = CURDATE() THEN "listo_para_emitir"
                        ELSE "listo_para_emitir"
                    END,
                     updated_at = NOW()
                 WHERE project_id = :project_id
                   AND invoice_id = :invoice_id',
                [':project_id' => $projectId, ':invoice_id' => $invoiceId]
            );
        }

        if ($this->db->tableExists('project_invoice_plan_items')) {
            $this->db->execute(
                'DELETE FROM project_invoice_plan_items WHERE invoice_id = :invoice_id',
                [':invoice_id' => $invoiceId]
            );
        }

        if ($planItemIds === []) {
            $this->syncPlanStatuses($projectId);
            return;
        }

        $idsSql = implode(',', array_map('intval', $planItemIds));
        $eligible = $this->db->fetchAll(
            'SELECT id
             FROM project_billing_plan
             WHERE project_id = :project_id
               AND id IN (' . $idsSql . ')
               AND (invoice_id IS NULL OR invoice_id = :invoice_id)
               AND status IN ("listo_para_emitir", "atrasado", "emitido", "pagado")',
            [':project_id' => $projectId, ':invoice_id' => $invoiceId]
        );
        $eligibleIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $eligible);
        if ($eligibleIds === []) {
            $this->syncPlanStatuses($projectId);
            return;
        }

        foreach ($eligibleIds as $planItemId) {
            if ($this->db->tableExists('project_invoice_plan_items')) {
                $this->db->execute(
                    'INSERT INTO project_invoice_plan_items (invoice_id, plan_item_id)
                     VALUES (:invoice_id, :plan_item_id)',
                    [':invoice_id' => $invoiceId, ':plan_item_id' => $planItemId]
                );
            }
        }

        $eligibleSql = implode(',', $eligibleIds);
        $this->db->execute(
            'UPDATE project_billing_plan
             SET invoice_id = :invoice_id,
                 status = "emitido",
                 updated_at = NOW()
             WHERE project_id = :project_id
               AND id IN (' . $eligibleSql . ')',
            [':invoice_id' => $invoiceId, ':project_id' => $projectId]
        );
        $this->syncPlanStatuses($projectId);
    }

    private function syncPlanStatuses(int $projectId): void
    {
        if (!$this->db->tableExists('project_billing_plan')) {
            return;
        }

        $progress = $this->projectProgress($projectId);
        $today = date('Y-m-d');
        $rows = $this->db->fetchAll(
            'SELECT id, status, invoice_id, expected_date, item_type, billing_model, condition_met, progress_required_percentage
             FROM project_billing_plan
             WHERE project_id = :project_id',
            [':project_id' => $projectId]
        );

        foreach ($rows as $row) {
            $normalizedType = $this->normalizePlanType((string) ($row['item_type'] ?? ''), (string) ($row['billing_model'] ?? ''));
            $normalizedStatus = $this->mapLegacyStatus((string) ($row['status'] ?? 'pendiente'));
            $nextStatus = $this->resolvePlanStatus(
                [
                    'current_status' => $normalizedStatus,
                    'invoice_id' => $row['invoice_id'] ?? null,
                    'expected_date' => $row['expected_date'] ?? null,
                    'condition_met' => $row['condition_met'] ?? 0,
                    'item_type' => $normalizedType,
                    'progress_required_percentage' => $row['progress_required_percentage'] ?? null,
                ],
                $today,
                $progress
            );
            if ($normalizedStatus === $nextStatus) {
                continue;
            }
            $this->db->execute(
                'UPDATE project_billing_plan
                 SET status = :status,
                     item_type = :item_type,
                     updated_at = NOW()
                 WHERE id = :id',
                [
                    ':status' => $nextStatus,
                    ':item_type' => $normalizedType,
                    ':id' => (int) ($row['id'] ?? 0),
                ]
            );
        }
    }

    private function resolvePlanStatus(array $item, string $today, float $projectProgress): string
    {
        if (!empty($item['invoice_id'])) {
            if (($item['current_status'] ?? '') === 'pagado') {
                return 'pagado';
            }
            return 'emitido';
        }

        $itemType = (string) ($item['item_type'] ?? '');
        $conditionMet = $this->normalizeConditionMet($item['condition_met'] ?? 0) === 1;
        if ($itemType === 'porcentaje_avance') {
            $required = isset($item['progress_required_percentage']) && $item['progress_required_percentage'] !== null
                ? (float) $item['progress_required_percentage']
                : 0.0;
            if ($required > 0 && $projectProgress >= $required) {
                $conditionMet = true;
            }
        }

        if ($conditionMet) {
            return 'listo_para_emitir';
        }

        $expectedDate = $this->normalizeDate($item['expected_date'] ?? null);
        if ($expectedDate === null) {
            return 'pendiente';
        }

        $deltaDays = (int) floor((strtotime($expectedDate) - strtotime($today)) / 86400);
        if ($deltaDays > 7) {
            return 'pendiente';
        }
        if ($deltaDays > 0) {
            return 'proximo';
        }
        if ($deltaDays === 0) {
            return 'listo_para_emitir';
        }

        // Para anticipar alertas operativas, consideramos "atrasado" cualquier fecha pasada sin factura.
        return 'atrasado';
    }

    private function resolvePlanItemAmount(array $item, float $contractValue): float
    {
        $amount = isset($item['amount']) && $item['amount'] !== null ? (float) $item['amount'] : 0.0;
        if ($amount > 0) {
            return $amount;
        }

        $percentage = isset($item['percentage']) && $item['percentage'] !== null ? (float) $item['percentage'] : 0.0;
        if ($percentage > 0) {
            return ($percentage / 100) * $contractValue;
        }

        return 0.0;
    }

    private function billingTrafficLight(array $item, string $today): array
    {
        if (!empty($item['invoice_id'])) {
            return ['light' => 'green', 'days_to_issue' => null];
        }

        $expectedDate = $this->normalizeDate($item['expected_date'] ?? null);
        if ($expectedDate === null) {
            return ['light' => 'gray', 'days_to_issue' => null];
        }

        $deltaDays = (int) floor((strtotime($expectedDate) - strtotime($today)) / 86400);
        if ($deltaDays < 0) {
            return ['light' => 'red', 'days_to_issue' => $deltaDays];
        }
        if ($deltaDays <= 7) {
            return ['light' => 'yellow', 'days_to_issue' => $deltaDays];
        }

        return ['light' => 'gray', 'days_to_issue' => $deltaDays];
    }

    private function planTypeLabel(string $type): string
    {
        return match ($type) {
            'anticipo' => 'Anticipo',
            'mensualidad_fija' => 'Mensualidad fija',
            'hito_entregable' => 'Hito / Entregable',
            'porcentaje_avance' => 'Por porcentaje de avance',
            default => 'Hito / Entregable',
        };
    }

    private function normalizePlanType(string $itemType, ?string $legacyModel): string
    {
        $normalized = strtolower(trim($itemType));
        if (in_array($normalized, self::PLAN_TYPES, true)) {
            return $normalized;
        }

        return match (strtolower(trim((string) $legacyModel))) {
            'advance_balance' => 'anticipo',
            'recurring' => 'mensualidad_fija',
            'consumption' => 'porcentaje_avance',
            default => 'hito_entregable',
        };
    }

    private function legacyModelForPlanType(string $type): string
    {
        return match ($type) {
            'anticipo' => 'advance_balance',
            'mensualidad_fija' => 'recurring',
            'porcentaje_avance' => 'consumption',
            default => 'milestones',
        };
    }

    private function mapLegacyStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        return match ($normalized) {
            'listo_para_facturar' => 'listo_para_emitir',
            'facturado' => 'emitido',
            default => in_array($normalized, self::PLAN_STATUSES, true) ? $normalized : 'pendiente',
        };
    }

    private function normalizeCurrency(string $currency): string
    {
        $normalized = strtoupper(trim($currency));
        return in_array($normalized, self::CONTRACT_CURRENCIES, true) ? $normalized : 'USD';
    }

    private function normalizeDate($value): ?string
    {
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeConditionMet($value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'on', 'yes', 'si', 'sí'], true) ? 1 : 0;
    }

    private function projectProgress(int $projectId): float
    {
        $row = $this->db->fetchOne(
            'SELECT progress FROM projects WHERE id = :id LIMIT 1',
            [':id' => $projectId]
        ) ?? [];

        return (float) ($row['progress'] ?? 0);
    }

    private function normalizeArrayOfInts(array $values): array
    {
        $normalized = array_map('intval', $values);
        $normalized = array_values(array_filter($normalized, static fn (int $value): bool => $value > 0));

        return array_values(array_unique($normalized));
    }

    private function invoicePlanItemMap(int $projectId): array
    {
        $map = [];
        if (!$this->db->tableExists('project_billing_plan')) {
            return $map;
        }

        if ($this->db->tableExists('project_invoice_plan_items')) {
            $rows = $this->db->fetchAll(
                'SELECT pip.invoice_id, pip.plan_item_id
                 FROM project_invoice_plan_items pip
                 JOIN project_invoices i ON i.id = pip.invoice_id
                 WHERE i.project_id = :project_id',
                [':project_id' => $projectId]
            );
            foreach ($rows as $row) {
                $invoiceId = (int) ($row['invoice_id'] ?? 0);
                $planItemId = (int) ($row['plan_item_id'] ?? 0);
                if ($invoiceId <= 0 || $planItemId <= 0) {
                    continue;
                }
                $map[$invoiceId][] = $planItemId;
            }

            return $map;
        }

        $legacyRows = $this->db->fetchAll(
            'SELECT invoice_id, id
             FROM project_billing_plan
             WHERE project_id = :project_id
               AND invoice_id IS NOT NULL',
            [':project_id' => $projectId]
        );
        foreach ($legacyRows as $row) {
            $invoiceId = (int) ($row['invoice_id'] ?? 0);
            $planItemId = (int) ($row['id'] ?? 0);
            if ($invoiceId <= 0 || $planItemId <= 0) {
                continue;
            }
            $map[$invoiceId][] = $planItemId;
        }

        return $map;
    }
}
