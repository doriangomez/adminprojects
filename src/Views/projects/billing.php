<?php
$basePath = $basePath ?? '';
$project = $project ?? [];
$billingConfig = is_array($billingConfig ?? null) ? $billingConfig : [];
$projectInvoices = is_array($projectInvoices ?? null) ? $projectInvoices : [];
$billingPlanItems = is_array($billingPlanItems ?? null) ? $billingPlanItems : [];
$billingFinancialSummary = is_array($billingFinancialSummary ?? null) ? $billingFinancialSummary : [];
$availablePlanItemsForInvoice = is_array($availablePlanItemsForInvoice ?? null) ? $availablePlanItemsForInvoice : [];
$canManageBilling = !empty($canManageBilling);
$canDeleteInvoice = !empty($canDeleteInvoice);
$contractCurrencies = is_array($contractCurrencies ?? null) ? $contractCurrencies : ['COP', 'USD', 'EUR', 'MXN'];
$planItemTypes = is_array($planItemTypes ?? null) ? $planItemTypes : ['anticipo', 'mensualidad_fija', 'hito_entregable', 'porcentaje_avance'];
$isBillable = (int) ($billingConfig['is_billable'] ?? 0) === 1;
$currency = (string) ($billingConfig['currency_code'] ?? 'USD');
$currency = in_array($currency, $contractCurrencies, true) ? $currency : 'USD';
$locale = in_array($currency, ['COP', 'MXN'], true) ? 'es-CO' : 'en-US';
$fmtMoney = static function (float $amount) use ($currency, $locale): string {
    $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
    return $formatter->formatCurrency($amount, $currency);
};
$statusLabels = [
    'pendiente' => 'Pendiente',
    'proximo' => 'Próximo',
    'listo_para_emitir' => 'Listo para emitir',
    'emitido' => 'Emitido',
    'atrasado' => 'Atrasado',
];
$typeLabels = [
    'anticipo' => 'Anticipo',
    'mensualidad_fija' => 'Mensualidad fija',
    'hito_entregable' => 'Hito / Entregable',
    'porcentaje_avance' => 'Por porcentaje de avance',
];
$attentionCount = (int) ($billingFinancialSummary['attention_items_count'] ?? 0);
$counts = is_array($billingFinancialSummary['counts'] ?? null) ? $billingFinancialSummary['counts'] : [];
$nextItemsCount = (int) ($counts['proximo'] ?? 0);
$readyItemsCount = (int) ($counts['listo_para_emitir'] ?? 0);
$overdueItemsCount = (int) ($counts['atrasado'] ?? 0);
$controlSummary = is_array($billingFinancialSummary['control_summary'] ?? null) ? $billingFinancialSummary['control_summary'] : [];
$onTrackCount = (int) ($controlSummary['on_track'] ?? 0);
$upcomingControlCount = (int) ($controlSummary['upcoming'] ?? 0);
$overdueControlCount = (int) ($controlSummary['overdue'] ?? 0);
$invoicedVsPlanPercentage = (float) ($billingFinancialSummary['invoiced_vs_plan_percentage'] ?? 0);
$trafficLightLabels = [
    'green' => 'Al día',
    'yellow' => 'Próximo a emitir',
    'red' => 'Vencido',
    'gray' => 'Futuro',
];
$invoiceAnchorMap = [];
foreach ($projectInvoices as $invoice) {
    $invoiceAnchorMap[(string) ($invoice['invoice_number'] ?? '')] = 'invoice-row-' . (int) ($invoice['id'] ?? 0);
}
?>
<section class="project-shell">
    <header class="project-header">
        <div>
            <p class="eyebrow">Facturación</p>
            <h2><?= htmlspecialchars($project['name'] ?? '') ?></h2>
            <small class="section-muted">Definición de acuerdos, facturas emitidas y alertas de emisión.</small>
        </div>
    </header>

    <?php $activeTab = 'facturacion'; require __DIR__ . '/_tabs.php'; ?>

    <section class="billing-layout">
        <article class="card billing-card">
            <h3>A. Configuración del contrato</h3>
            <?php if ($canManageBilling): ?>
                <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/billing-config" class="grid-form">
                    <label>Valor total del contrato
                        <input type="number" step="0.01" min="0" name="contract_value" value="<?= htmlspecialchars((string) ($billingConfig['contract_value'] ?? '0')) ?>" required>
                    </label>
                    <label>Moneda
                        <select name="currency_code" required>
                            <?php foreach ($contractCurrencies as $code): ?>
                                <option value="<?= htmlspecialchars((string) $code) ?>" <?= $currency === $code ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $code) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Fecha de inicio del contrato
                        <input type="date" name="billing_start_date" value="<?= htmlspecialchars((string) ($billingConfig['billing_start_date'] ?? '')) ?>">
                    </label>
                    <label>Fecha de fin del contrato
                        <input type="date" name="billing_end_date" value="<?= htmlspecialchars((string) ($billingConfig['billing_end_date'] ?? '')) ?>">
                    </label>
                    <label class="contract-billable-toggle">
                        ¿El proyecto es facturable?
                        <select name="is_billable">
                            <option value="1" <?= $isBillable ? 'selected' : '' ?>>Sí</option>
                            <option value="0" <?= !$isBillable ? 'selected' : '' ?>>No</option>
                        </select>
                    </label>
                    <label style="grid-column:1/-1;">Notas del contrato
                        <textarea name="contract_notes" rows="3" placeholder="Notas opcionales del contrato"><?= htmlspecialchars((string) ($billingConfig['contract_notes'] ?? '')) ?></textarea>
                    </label>
                    <div><button class="action-btn primary" type="submit">Guardar configuración</button></div>
                </form>
            <?php else: ?>
                <div class="readonly-grid">
                    <p><strong>Valor total:</strong> <?= $fmtMoney((float) ($billingConfig['contract_value'] ?? 0)) ?></p>
                    <p><strong>Moneda:</strong> <?= htmlspecialchars($currency) ?></p>
                    <p><strong>Inicio:</strong> <?= htmlspecialchars((string) ($billingConfig['billing_start_date'] ?? '-')) ?></p>
                    <p><strong>Fin:</strong> <?= htmlspecialchars((string) ($billingConfig['billing_end_date'] ?? '-')) ?></p>
                    <p><strong>Facturable:</strong> <?= $isBillable ? 'Sí' : 'No' ?></p>
                    <p style="grid-column:1/-1;"><strong>Notas:</strong> <?= htmlspecialchars((string) ($billingConfig['contract_notes'] ?? '-')) ?></p>
                </div>
            <?php endif; ?>
        </article>

        <?php if (!$isBillable): ?>
            <article class="card billing-card">
                <p class="empty-note">Este proyecto no genera facturación.</p>
            </article>
        <?php else: ?>
            <article class="card billing-card" id="plan-section">
                <div class="billing-section-head">
                    <h3>B. Plan de facturación</h3>
                    <?php if ($canManageBilling): ?>
                        <button class="action-btn primary" type="button" data-toggle-inline-form="new-plan-item">＋ Agregar ítem de facturación</button>
                    <?php endif; ?>
                </div>

                <?php if ($attentionCount > 0): ?>
                    <div class="attention-banner">
                        <span>Tienes <?= $attentionCount ?> ítems que requieren atención.</span>
                        <a href="#plan-items-table">Ver ítems</a>
                    </div>
                <?php endif; ?>

                <div class="kpi-grid billing-control-grid">
                    <article class="kpi-card"><span>Ítems al día</span><strong><?= $onTrackCount ?></strong></article>
                    <article class="kpi-card"><span>Ítems próximos (7 días)</span><strong><?= $upcomingControlCount ?></strong></article>
                    <article class="kpi-card"><span>Ítems vencidos sin factura</span><strong><?= $overdueControlCount ?></strong></article>
                    <article class="kpi-card"><span>Facturado vs plan</span><strong><?= number_format($invoicedVsPlanPercentage, 2) ?>%</strong></article>
                </div>

                <?php if ($canManageBilling): ?>
                    <form id="new-plan-item" method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/billing-plan" class="grid-form inline-form" hidden>
                        <label>Tipo de facturación
                            <select name="item_type" required data-plan-type="true">
                                <?php foreach ($planItemTypes as $planType): ?>
                                    <option value="<?= htmlspecialchars((string) $planType) ?>"><?= htmlspecialchars($typeLabels[$planType] ?? (string) $planType) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <div class="plan-type-fields" data-plan-fields="anticipo">
                            <label>Concepto<input type="text" name="concept"></label>
                            <label>Valor<input type="number" step="0.01" min="0" name="amount"></label>
                            <label>Fecha esperada de emisión<input type="date" name="expected_date"></label>
                            <label>Condición<input type="text" name="condition_text"></label>
                        </div>

                        <div class="plan-type-fields" data-plan-fields="mensualidad_fija" hidden>
                            <label>Concepto<input type="text" name="concept"></label>
                            <label>Valor mensual<input type="number" step="0.01" min="0" name="amount"></label>
                            <label>Fecha de inicio<input type="date" name="start_date"></label>
                            <label>Fecha de fin<input type="date" name="end_date"></label>
                            <label>Día del mes (1-28)<input type="number" min="1" max="28" name="day_of_month"></label>
                        </div>

                        <div class="plan-type-fields" data-plan-fields="hito_entregable" hidden>
                            <label>Nombre del hito<input type="text" name="milestone_name"></label>
                            <label>Concepto<input type="text" name="concept"></label>
                            <label>Valor (monto)<input type="number" step="0.01" min="0" name="amount"></label>
                            <label>Porcentaje del contrato<input type="number" step="0.01" min="0" max="100" name="percentage"></label>
                            <label>Fecha esperada de emisión<input type="date" name="expected_date"></label>
                            <label>Condición de emisión<input type="text" name="condition_text"></label>
                            <label>Hito vinculado al cronograma (opcional)<input type="number" min="1" name="linked_schedule_activity_id"></label>
                        </div>

                        <div class="plan-type-fields" data-plan-fields="porcentaje_avance" hidden>
                            <label>Concepto<input type="text" name="concept"></label>
                            <label>Porcentaje de avance requerido (1-100)<input type="number" min="1" max="100" step="0.01" name="progress_required_percentage"></label>
                            <label>Valor a facturar<input type="number" step="0.01" min="0" name="amount"></label>
                            <label>Condición adicional<input type="text" name="condition_text"></label>
                        </div>

                        <label style="grid-column:1/-1;">Notas<textarea name="notes" rows="2"></textarea></label>
                        <div><button class="action-btn primary" type="submit">Guardar ítem</button></div>
                    </form>
                <?php endif; ?>

                <div class="table-wrapper">
                    <table id="plan-items-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Concepto</th>
                                <th>Valor</th>
                                <th>Fecha esperada emisión</th>
                                <th>Condición</th>
                                <th>Estado</th>
                                <th>Factura asociada</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($billingPlanItems === []): ?>
                                <tr><td colspan="8" class="empty-row">No hay ítems de plan de facturación registrados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($billingPlanItems as $item): ?>
                                    <?php
                                        $itemId = (int) ($item['id'] ?? 0);
                                        $status = (string) ($item['status'] ?? 'pendiente');
                                        $invoiceNumber = (string) ($item['linked_invoice_number'] ?? '');
                                        $invoiceAnchor = $invoiceNumber !== '' ? ($invoiceAnchorMap[$invoiceNumber] ?? null) : null;
                                        $trafficLight = (string) ($item['traffic_light'] ?? 'gray');
                                    ?>
                                    <tr id="plan-item-<?= $itemId ?>">
                                        <td><?= htmlspecialchars((string) ($item['type_label'] ?? ($typeLabels[$item['item_type'] ?? ''] ?? '-'))) ?></td>
                                        <td><?= htmlspecialchars((string) ($item['concept'] ?? '-')) ?></td>
                                        <td><?= $fmtMoney((float) ($item['resolved_amount'] ?? 0)) ?></td>
                                        <td><?= htmlspecialchars((string) ($item['expected_date'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string) ($item['condition_text'] ?? '-')) ?></td>
                                        <td>
                                            <span class="traffic-light traffic-<?= htmlspecialchars($trafficLight) ?>" title="<?= htmlspecialchars($trafficLightLabels[$trafficLight] ?? 'Sin estado') ?>"></span>
                                            <span class="status-pill status-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status))) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($invoiceNumber !== '' && $invoiceAnchor): ?>
                                                <a href="#<?= htmlspecialchars($invoiceAnchor) ?>"><?= htmlspecialchars($invoiceNumber) ?></a>
                                            <?php elseif ($invoiceNumber !== ''): ?>
                                                <?= htmlspecialchars($invoiceNumber) ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="actions">
                                            <?php if ($canManageBilling): ?>
                                                <button class="action-btn small" type="button" data-toggle-inline-form="edit-plan-<?= $itemId ?>">✎</button>
                                                <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/billing-plan/<?= $itemId ?>/delete" onsubmit="return confirm('¿Eliminar ítem de facturación?');">
                                                    <button class="action-btn small danger" type="submit">🗑</button>
                                                </form>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if ($canManageBilling): ?>
                                        <tr class="inline-edit-row" id="edit-plan-<?= $itemId ?>" hidden>
                                            <td colspan="8">
                                                <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/billing-plan/<?= $itemId ?>/update" class="grid-form inline-form">
                                                    <label>Tipo
                                                        <select name="item_type" required>
                                                            <?php foreach ($planItemTypes as $planType): ?>
                                                                <option value="<?= htmlspecialchars((string) $planType) ?>" <?= ($item['item_type'] ?? '') === $planType ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($typeLabels[$planType] ?? (string) $planType) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label>Concepto<input type="text" name="concept" value="<?= htmlspecialchars((string) ($item['concept'] ?? '')) ?>"></label>
                                                    <label>Nombre hito<input type="text" name="milestone_name" value="<?= htmlspecialchars((string) ($item['milestone_name'] ?? '')) ?>"></label>
                                                    <label>Valor<input type="number" step="0.01" min="0" name="amount" value="<?= htmlspecialchars((string) ($item['amount'] ?? '')) ?>"></label>
                                                    <label>Porcentaje<input type="number" step="0.01" min="0" max="100" name="percentage" value="<?= htmlspecialchars((string) ($item['percentage'] ?? '')) ?>"></label>
                                                    <label>% avance requerido<input type="number" step="0.01" min="1" max="100" name="progress_required_percentage" value="<?= htmlspecialchars((string) ($item['progress_required_percentage'] ?? '')) ?>"></label>
                                                    <label>Fecha esperada<input type="date" name="expected_date" value="<?= htmlspecialchars((string) ($item['expected_date'] ?? '')) ?>"></label>
                                                    <label>Condición<input type="text" name="condition_text" value="<?= htmlspecialchars((string) ($item['condition_text'] ?? '')) ?>"></label>
                                                    <label>Inicio mensualidad<input type="date" name="start_date"></label>
                                                    <label>Fin mensualidad<input type="date" name="end_date"></label>
                                                    <label>Día mes (1-28)<input type="number" min="1" max="28" name="day_of_month" value="<?= htmlspecialchars((string) ($item['day_of_month'] ?? '')) ?>"></label>
                                                    <label>Hito cronograma<input type="number" min="1" name="linked_schedule_activity_id" value="<?= htmlspecialchars((string) ($item['linked_schedule_activity_id'] ?? '')) ?>"></label>
                                                    <label style="grid-column:1/-1;">Notas<textarea name="notes" rows="2"><?= htmlspecialchars((string) ($item['notes'] ?? '')) ?></textarea></label>
                                                    <div><button class="action-btn primary" type="submit">Guardar</button></div>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="card billing-card" id="invoices-section">
                <div class="billing-section-head">
                    <h3>C. Facturas emitidas</h3>
                    <?php if ($canManageBilling): ?>
                        <button class="action-btn primary" type="button" data-toggle-inline-form="new-invoice">＋ Registrar factura</button>
                    <?php endif; ?>
                </div>

                <?php if ($canManageBilling): ?>
                    <form id="new-invoice" method="POST" enctype="multipart/form-data" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/invoices" class="grid-form inline-form" hidden>
                        <label>Número de factura
                            <input type="text" name="invoice_number" required>
                        </label>
                        <label>Fecha de emisión
                            <input type="date" name="issued_at" required>
                        </label>
                        <label>Valor de la factura
                            <input type="number" step="0.01" min="0" name="amount" required>
                        </label>
                        <label>Moneda
                            <select name="currency_code">
                                <?php foreach ($contractCurrencies as $code): ?>
                                    <option value="<?= htmlspecialchars((string) $code) ?>" <?= $currency === $code ? 'selected' : '' ?>><?= htmlspecialchars((string) $code) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label style="grid-column:1/-1;">Ítems del plan que cubre esta factura
                            <select name="plan_item_ids[]" multiple size="4">
                                <?php foreach ($availablePlanItemsForInvoice as $planItem): ?>
                                    <option value="<?= (int) ($planItem['id'] ?? 0) ?>">
                                        <?= htmlspecialchars((string) ($planItem['concept'] ?? ('Ítem #' . (int) ($planItem['id'] ?? 0)))) ?>
                                        (<?= htmlspecialchars($statusLabels[(string) ($planItem['status'] ?? '')] ?? (string) ($planItem['status'] ?? '')) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Archivo PDF de la factura
                            <input type="file" name="invoice_pdf" accept="application/pdf">
                        </label>
                        <label style="grid-column:1/-1;">Notas
                            <textarea name="notes" rows="2"></textarea>
                        </label>
                        <div><button class="action-btn primary" type="submit">Guardar factura</button></div>
                    </form>
                <?php endif; ?>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>N° Factura</th>
                                <th>Fecha emisión</th>
                                <th>Valor</th>
                                <th>Ítems cubiertos</th>
                                <th>PDF</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($projectInvoices === []): ?>
                                <tr><td colspan="6" class="empty-row">No hay facturas emitidas registradas.</td></tr>
                            <?php else: ?>
                                <?php foreach ($projectInvoices as $inv): ?>
                                    <?php
                                        $invId = (int) ($inv['id'] ?? 0);
                                        $coveredCount = (int) ($inv['covered_items_count'] ?? 0);
                                        $coveredItems = trim((string) ($inv['covered_items'] ?? ''));
                                        $attachmentPath = trim((string) ($inv['attachment_path'] ?? ''));
                                    ?>
                                    <tr id="invoice-row-<?= $invId ?>">
                                        <td><?= htmlspecialchars((string) ($inv['invoice_number'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars((string) ($inv['issued_at'] ?? '')) ?></td>
                                        <td><?= $fmtMoney((float) ($inv['amount'] ?? 0)) ?></td>
                                        <td>
                                            <?= $coveredCount > 0 ? $coveredCount . ' ítem(s)' : '-' ?>
                                            <?php if ($coveredItems !== ''): ?>
                                                <small class="cell-note"><?= htmlspecialchars($coveredItems) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($attachmentPath !== ''): ?>
                                                <a href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/invoices/<?= $invId ?>/pdf" title="Descargar PDF">⬇</a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="actions">
                                            <?php if ($canManageBilling): ?>
                                                <button class="action-btn small" type="button" data-toggle-inline-form="edit-invoice-<?= $invId ?>">✎</button>
                                            <?php endif; ?>
                                            <?php if ($canDeleteInvoice): ?>
                                                <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/invoices/<?= $invId ?>/delete" onsubmit="return confirm('¿Eliminar factura?');">
                                                    <button class="action-btn small danger" type="submit">🗑</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if ($canManageBilling): ?>
                                        <tr class="inline-edit-row" id="edit-invoice-<?= $invId ?>" hidden>
                                            <td colspan="6">
                                                <form method="POST" enctype="multipart/form-data" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/invoices/<?= $invId ?>/update" class="grid-form inline-form">
                                                    <label>Número de factura<input type="text" name="invoice_number" value="<?= htmlspecialchars((string) ($inv['invoice_number'] ?? '')) ?>" required></label>
                                                    <label>Fecha emisión<input type="date" name="issued_at" value="<?= htmlspecialchars((string) ($inv['issued_at'] ?? '')) ?>" required></label>
                                                    <label>Valor<input type="number" step="0.01" min="0" name="amount" value="<?= htmlspecialchars((string) ($inv['amount'] ?? '')) ?>" required></label>
                                                    <label>Moneda
                                                        <select name="currency_code">
                                                            <?php foreach ($contractCurrencies as $code): ?>
                                                                <option value="<?= htmlspecialchars((string) $code) ?>" <?= (($inv['currency_code'] ?? $currency) === $code) ? 'selected' : '' ?>><?= htmlspecialchars((string) $code) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label style="grid-column:1/-1;">Ítems del plan
                                                        <select name="plan_item_ids[]" multiple size="4">
                                                            <?php
                                                                $selectedIds = is_array($inv['selected_plan_item_ids'] ?? null) ? array_map('intval', $inv['selected_plan_item_ids']) : [];
                                                                foreach ($billingPlanItems as $planItem):
                                                                    $planId = (int) ($planItem['id'] ?? 0);
                                                                    $status = (string) ($planItem['status'] ?? '');
                                                                    $selectable = empty($planItem['invoice_id'])
                                                                        || in_array($planId, $selectedIds, true);
                                                                    if (!$selectable) {
                                                                        continue;
                                                                    }
                                                            ?>
                                                                <option value="<?= $planId ?>" <?= in_array($planId, $selectedIds, true) ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars((string) ($planItem['concept'] ?? ('Ítem #' . $planId))) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label>Archivo PDF<input type="file" name="invoice_pdf" accept="application/pdf"></label>
                                                    <?php if ($attachmentPath !== ''): ?>
                                                        <label class="invoice-pdf-remove-toggle">
                                                            <input type="checkbox" name="remove_invoice_pdf" value="1">
                                                            Eliminar PDF actual
                                                        </label>
                                                    <?php endif; ?>
                                                    <input type="hidden" name="existing_attachment_path" value="<?= htmlspecialchars($attachmentPath) ?>">
                                                    <label style="grid-column:1/-1;">Notas<textarea name="notes" rows="2"><?= htmlspecialchars((string) ($inv['notes'] ?? '')) ?></textarea></label>
                                                    <div><button class="action-btn primary" type="submit">Guardar cambios</button></div>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="card billing-card">
                <div class="billing-section-head">
                    <h3>D. Resumen financiero</h3>
                    <a class="action-btn" href="<?= $basePath ?>/projects/billing-report?project_id=<?= (int) ($project['id'] ?? 0) ?>">Exportar CSV</a>
                </div>
                <div class="kpi-grid finance-kpi-grid">
                    <article class="kpi-card"><span>Total contrato</span><strong><?= $fmtMoney((float) ($billingFinancialSummary['total_contract'] ?? 0)) ?></strong></article>
                    <article class="kpi-card"><span>Total plan</span><strong><?= $fmtMoney((float) ($billingFinancialSummary['total_plan'] ?? 0)) ?></strong></article>
                    <article class="kpi-card"><span>Total facturado</span><strong><?= $fmtMoney((float) ($billingFinancialSummary['total_invoiced'] ?? 0)) ?></strong></article>
                    <article class="kpi-card"><span>Saldo por facturar</span><strong><?= $fmtMoney((float) ($billingFinancialSummary['balance_to_invoice'] ?? 0)) ?></strong></article>
                    <article class="kpi-card"><span>Ítems atrasados</span><strong><?= (int) ($billingFinancialSummary['overdue_items_count'] ?? 0) ?></strong></article>
                    <article class="kpi-card"><span>Ítems próximos</span><strong><?= (int) ($billingFinancialSummary['upcoming_items_count'] ?? 0) ?></strong></article>
                </div>
                <?php if (abs((float) ($billingFinancialSummary['plan_difference'] ?? 0)) > 0.009): ?>
                    <p class="warning-note">
                        El plan no cubre el valor total del contrato. Diferencia:
                        <?= $fmtMoney(abs((float) ($billingFinancialSummary['plan_difference'] ?? 0))) ?>
                    </p>
                <?php endif; ?>
            </article>
        <?php endif; ?>
    </section>
</section>

<script>
document.addEventListener('click', (event) => {
  const trigger = event.target.closest('[data-toggle-inline-form]');
  if (!trigger) {
    return;
  }
  const targetId = trigger.getAttribute('data-toggle-inline-form');
  const target = document.getElementById(targetId);
  if (!target) {
    return;
  }
  target.hidden = !target.hidden;
});

const planTypeSelector = document.querySelector('[data-plan-type="true"]');
if (planTypeSelector) {
  const syncPlanType = () => {
    const value = planTypeSelector.value;
    document.querySelectorAll('[data-plan-fields]').forEach((block) => {
      block.hidden = block.getAttribute('data-plan-fields') !== value;
    });
  };
  planTypeSelector.addEventListener('change', syncPlanType);
  syncPlanType();
}
</script>

<style>
.billing-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 20px;
  margin-bottom: 24px;
}
.billing-section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 12px;
}
.attention-banner {
  margin: 10px 0 14px;
  border: 1px solid #f59e0b;
  background: #fef3c7;
  color: #92400e;
  border-radius: 8px;
  padding: 10px 12px;
  display: flex;
  justify-content: space-between;
  gap: 10px;
}
.attention-banner a {
  color: #92400e;
  font-weight: 700;
}
.inline-form {
  margin-bottom: 14px;
  padding: 12px;
  border: 1px dashed #d1d5db;
  border-radius: 8px;
  background: #f9fafb;
}
.plan-type-fields {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 10px;
}
.billing-control-grid {
  margin-bottom: 14px;
}
.traffic-light {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 999px;
  margin-right: 6px;
  vertical-align: middle;
}
.traffic-green { background: #16a34a; }
.traffic-yellow { background: #f59e0b; }
.traffic-red { background: #dc2626; }
.traffic-gray { background: #9ca3af; }
.status-pill {
  border-radius: 999px;
  padding: 4px 10px;
  font-weight: 700;
  font-size: 12px;
}
.status-pendiente { background: #6b72801f; color: #6b7280; }
.status-proximo { background: #f59e0b1f; color: #b45309; }
.status-listo_para_emitir { background: #2563eb1f; color: #1d4ed8; }
.status-emitido { background: #16a34a1f; color: #15803d; }
.status-atrasado { background: #dc26261f; color: #b91c1c; }
.actions {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.kpi-grid.finance-kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 10px;
}
.kpi-card {
  background: #f9fafb;
  border-radius: 8px;
  padding: 12px;
}
.kpi-card span {
  color: #6b7280;
  font-size: 12px;
  text-transform: uppercase;
}
.kpi-card strong {
  display: block;
  margin-top: 6px;
  font-size: 1.1rem;
}
.warning-note {
  margin-top: 12px;
  border: 1px solid #f59e0b;
  background: #fef3c7;
  color: #92400e;
  border-radius: 8px;
  padding: 10px;
}
.empty-row,
.empty-note {
  text-align: center;
  color: #6b7280;
}
.cell-note {
  display: block;
  color: #6b7280;
}
.readonly-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 8px 12px;
}
</style>
