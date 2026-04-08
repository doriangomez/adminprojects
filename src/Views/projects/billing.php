<?php
$basePath = $basePath ?? '';
$project = $project ?? [];
$billingConfig = is_array($billingConfig ?? null) ? $billingConfig : [];
$billingTotals = is_array($billingTotals ?? null) ? $billingTotals : [];
$projectInvoices = is_array($projectInvoices ?? null) ? $projectInvoices : [];
$billingPlanItems = is_array($billingPlanItems ?? null) ? $billingPlanItems : [];
$billingFinancialControl = is_array($billingFinancialControl ?? null) ? $billingFinancialControl : [];
$canManageBilling = !empty($canManageBilling);
$canMarkInvoicePaid = !empty($canMarkInvoicePaid);
$canVoidInvoice = !empty($canVoidInvoice);
$canDeleteInvoice = !empty($canDeleteInvoice);
$approvedHoursPendingInvoicing = (float) ($approvedHoursPendingInvoicing ?? 0);
$approvedHoursTotal = (float) ($approvedHoursTotal ?? 0);
$currency = in_array(($billingConfig['currency_code'] ?? 'USD'), ['USD', 'COP'], true) ? $billingConfig['currency_code'] : 'USD';
$locale = $currency === 'COP' ? 'es-CO' : 'en-US';
$fmtMoney = static function (float $amount) use ($currency, $locale): string {
    $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
    return $formatter->formatCurrency($amount, $currency);
};
$billingTypeLabels = ['fixed' => 'Fijo', 'hours' => 'Por horas', 'milestones' => 'Por hitos', 'mixed' => 'Mixto'];
$periodicityLabels = ['monthly' => 'Mensual', 'biweekly' => 'Quincenal', 'deliverable' => 'Por entregable', 'one_time' => 'Único', 'custom' => 'Personalizado'];
$statusLabels = ['issued' => 'Emitida', 'paid' => 'Pagada', 'draft' => 'Borrador', 'cancelled' => 'Anulada'];
$totalInvoiced = (float) ($billingTotals['total_invoiced'] ?? 0);
$hoursBillableAmount = (($billingConfig['billing_type'] ?? '') === 'hours') ? ($approvedHoursTotal * (float) ($billingConfig['hourly_rate'] ?? 0)) : null;
$hoursDelta = $hoursBillableAmount !== null ? ($hoursBillableAmount - $totalInvoiced) : null;
$contractValue = (float) ($billingConfig['contract_value'] ?? 0);
$billingProgressRatio = $contractValue > 0 ? min(1, max(0, $totalInvoiced / $contractValue)) : 0;
$billingProgressPercent = $billingProgressRatio * 100;
$controlStatusLabels = [
    'pendiente' => 'Pendiente',
    'listo_para_facturar' => 'Listo para facturar',
    'facturado' => 'Facturado',
    'pagado' => 'Pagado',
    'atrasado' => 'Atrasado',
];
?>
<section class="project-shell">
    <header class="project-header">
        <div>
            <p class="eyebrow">Facturación</p>
            <h2><?= htmlspecialchars($project['name'] ?? '') ?></h2>
            <small class="section-muted">Gestión financiera contractual y de facturas del proyecto.</small>
        </div>
        <div class="project-actions">
            <?php if ($canManageBilling): ?><button class="action-btn primary" type="button" data-open-modal="invoice-modal">Registrar factura</button><?php endif; ?>
        </div>
    </header>

    <?php $activeTab = 'facturacion'; require __DIR__ . '/_tabs.php'; ?>

    <section class="billing-layout">
        <article class="card billing-card billing-contract-card">
            <h3>A. Configuración contractual</h3>
            <?php if ($canManageBilling): ?>
            <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/billing-config" class="grid-form" id="billing-config-form">
                <div class="contract-switch-row">
                    <span class="contract-title">Contrato</span>
                    <input type="hidden" id="is-billable" name="is_billable" value="<?= ((int) ($billingConfig['is_billable'] ?? 0) === 1) ? '1' : '0' ?>">
                    <button
                        type="button"
                        id="billable-switch"
                        class="toggle-switch <?= ((int) ($billingConfig['is_billable'] ?? 0) === 1) ? 'is-on' : 'is-off' ?>"
                        role="switch"
                        aria-checked="<?= ((int) ($billingConfig['is_billable'] ?? 0) === 1) ? 'true' : 'false' ?>"
                        aria-label="Toggle de facturación"
                    >
                        <svg class="billable-switch-svg" viewBox="0 0 52 30" width="52" height="30" aria-hidden="true">
                            <rect class="billable-switch-track" x="1" y="1" width="50" height="28" rx="14" fill="<?= ((int) ($billingConfig['is_billable'] ?? 0) === 1) ? '#22a35a' : '#b8bec8' ?>" stroke="<?= ((int) ($billingConfig['is_billable'] ?? 0) === 1) ? '#1d8b4c' : 'rgba(0,0,0,.14)' ?>" stroke-width="1"></rect>
                            <circle class="billable-switch-thumb" cx="<?= ((int) ($billingConfig['is_billable'] ?? 0) === 1) ? '36' : '15' ?>" cy="15" r="11" fill="#ffffff"></circle>
                        </svg>
                    </button>
                    <span id="billable-badge" class="billable-badge <?= ((int) ($billingConfig['is_billable'] ?? 0) === 1) ? 'is-on' : 'is-off' ?>">
                        <?= ((int) ($billingConfig['is_billable'] ?? 0) === 1) ? 'Facturable' : 'No facturable' ?>
                    </span>
                </div>
                <p id="billable-off-message" class="section-muted" style="display:none;">Este proyecto no genera facturación.</p>
                <div id="billable-config-fields" class="grid-form-inner" style="display:<?= ((int) ($billingConfig['is_billable'] ?? 0) === 1) ? 'contents' : 'none' ?>;">
                    <label>Tipo de facturación<select name="billing_type" id="billing-type"><?php foreach (($billingTypes ?? []) as $t): ?><option value="<?= htmlspecialchars($t) ?>" <?= (($billingConfig['billing_type'] ?? '') === $t) ? 'selected' : '' ?>><?= htmlspecialchars($billingTypeLabels[$t] ?? $t) ?></option><?php endforeach; ?></select></label>
                    <label>Periodicidad<select name="billing_periodicity"><?php foreach (($billingPeriodicities ?? []) as $p): ?><option value="<?= htmlspecialchars($p) ?>" <?= (($billingConfig['billing_periodicity'] ?? '') === $p) ? 'selected' : '' ?>><?= htmlspecialchars($periodicityLabels[$p] ?? $p) ?></option><?php endforeach; ?></select></label>
                    <label>Valor del contrato<input type="number" step="0.01" min="0" name="contract_value" value="<?= htmlspecialchars((string) ($billingConfig['contract_value'] ?? '0')) ?>"></label>
                    <label>Moneda<select name="currency_code"><option value="USD" <?= $currency === 'USD' ? 'selected' : '' ?>>USD</option><option value="COP" <?= $currency === 'COP' ? 'selected' : '' ?>>COP</option></select></label>
                    <label>Fecha inicio facturación<input type="date" name="billing_start_date" value="<?= htmlspecialchars((string) ($billingConfig['billing_start_date'] ?? '')) ?>"></label>
                    <label>Fecha fin facturación<input type="date" name="billing_end_date" value="<?= htmlspecialchars((string) ($billingConfig['billing_end_date'] ?? '')) ?>"></label>
                    <label id="hourly-rate-field" style="display:<?= (($billingConfig['billing_type'] ?? '') === 'hours') ? 'block' : 'none' ?>;">Tarifa por hora<input type="number" step="0.01" min="0" name="hourly_rate" value="<?= htmlspecialchars((string) ($billingConfig['hourly_rate'] ?? '0')) ?>"></label>
                </div>
            </form>
<?php else: ?>
                <div class="contract-switch-row">
                    <span class="contract-title">Contrato</span>
                    <button
                        type="button"
                        class="toggle-switch <?= ((int) ($billingConfig['is_billable'] ?? 0) === 1) ? 'is-on' : 'is-off' ?> is-disabled"
                        role="switch"
                        aria-checked="<?= ((int) ($billingConfig['is_billable'] ?? 0) === 1) ? 'true' : 'false' ?>"
                        aria-label="Estado de facturación (solo lectura)"
                        disabled
                    >
                        <svg class="billable-switch-svg" viewBox="0 0 52 30" width="52" height="30" aria-hidden="true">
                            <rect class="billable-switch-track" x="1" y="1" width="50" height="28" rx="14" fill="<?= ((int) ($billingConfig['is_billable'] ?? 0) === 1) ? '#22a35a' : '#b8bec8' ?>" stroke="<?= ((int) ($billingConfig['is_billable'] ?? 0) === 1) ? '#1d8b4c' : 'rgba(0,0,0,.14)' ?>" stroke-width="1"></rect>
                            <circle class="billable-switch-thumb" cx="<?= ((int) ($billingConfig['is_billable'] ?? 0) === 1) ? '36' : '15' ?>" cy="15" r="11" fill="#ffffff"></circle>
                        </svg>
                    </button>
                    <span class="billable-badge <?= ((int) ($billingConfig['is_billable'] ?? 0) === 1) ? 'is-on' : 'is-off' ?>">
                        <?= ((int) ($billingConfig['is_billable'] ?? 0) === 1) ? 'Facturable' : 'No facturable' ?>
                    </span>
                </div>
                <div class="billing-contract-readonly-grid">
                    <div><span>Tipo de facturación</span><strong><?= htmlspecialchars($billingTypeLabels[$billingConfig['billing_type'] ?? ''] ?? (string) ($billingConfig['billing_type'] ?? '-')) ?></strong></div>
                    <div><span>Periodicidad</span><strong><?= htmlspecialchars($periodicityLabels[$billingConfig['billing_periodicity'] ?? ''] ?? (string) ($billingConfig['billing_periodicity'] ?? '-')) ?></strong></div>
                    <div><span>Valor del contrato</span><strong><?= $fmtMoney($contractValue) ?></strong></div>
                    <div><span>Moneda</span><strong><?= htmlspecialchars($currency) ?></strong></div>
                    <div><span>Fecha inicio</span><strong><?= htmlspecialchars((string) ($billingConfig['billing_start_date'] ?? '-')) ?></strong></div>
                    <div><span>Fecha fin</span><strong><?= htmlspecialchars((string) ($billingConfig['billing_end_date'] ?? '-')) ?></strong></div>
                    <div><span>Tarifa por hora</span><strong><?= $fmtMoney((float) ($billingConfig['hourly_rate'] ?? 0)) ?></strong></div>
                </div>
                <p class="section-muted">Solo administradores y PM pueden editar la configuración contractual.</p>
            <?php endif; ?>
        </article>

        <article class="card billing-card">
            <h3>B. Indicadores financieros</h3>
            <div class="kpi-grid finance-kpi-grid">
                <article class="kpi-card"><span>Total contrato</span><strong><?= $fmtMoney($contractValue) ?></strong></article>
                <article class="kpi-card"><span>Total esperado</span><strong><?= $fmtMoney((float) ($billingFinancialControl['expected_billing'] ?? 0)) ?></strong></article>
                <article class="kpi-card"><span>Total facturado</span><strong><?= $fmtMoney($totalInvoiced) ?></strong></article>
                <article class="kpi-card"><span>Total pagado</span><strong><?= $fmtMoney((float) ($billingTotals['total_paid'] ?? 0)) ?></strong></article>
                <article class="kpi-card"><span>Saldo por facturar</span><strong><?= $fmtMoney((float) ($billingFinancialControl['pending_billing'] ?? 0)) ?></strong></article>
                <article class="kpi-card"><span>Facturación atrasada</span><strong><?= $fmtMoney((float) ($billingFinancialControl['overdue_billing'] ?? 0)) ?></strong></article>
                <article class="kpi-card"><span>Revenue forecast</span><strong><?= $fmtMoney((float) ($billingFinancialControl['forecast_revenue'] ?? 0)) ?></strong></article>
            </div>
            <?php if (($billingConfig['billing_type'] ?? '') === 'fixed' && $totalInvoiced > (float) ($billingConfig['contract_value'] ?? 0)): ?>
                <p class="alert">⚠ Se superó el valor del contrato para facturación de tipo Fijo.</p>
            <?php endif; ?>
            <?php if ($hoursBillableAmount !== null): ?>
                <p class="section-muted">Facturable por horas: <strong><?= $fmtMoney($hoursBillableAmount) ?></strong> · Diferencia vs facturado: <strong><?= $fmtMoney((float) $hoursDelta) ?></strong>.</p>
            <?php endif; ?>
            <div style="margin-top:12px;">
                <a class="action-btn" href="<?= $basePath ?>/projects/billing-report?project_id=<?= (int) ($project['id'] ?? 0) ?>">Exportar CSV</a>
            </div>
        </article>

        <article class="card billing-card">
            <h3>C. Avance financiero del contrato</h3>
            <p class="section-muted">Progreso de facturación respecto al valor contractual.</p>
            <div class="billing-progress-wrap">
                <div class="billing-progress-track" role="img" aria-label="Avance de facturación del contrato">
                    <div class="billing-progress-fill" style="width: <?= number_format($billingProgressPercent, 2, '.', '') ?>%;"></div>
                </div>
                <div class="billing-progress-meta">
                    <strong><?= htmlspecialchars(number_format($billingProgressPercent, 1)) ?>%</strong>
                    <span><?= $fmtMoney($totalInvoiced) ?> / <?= $fmtMoney($contractValue) ?></span>
                </div>
            </div>
        </article>

        <article class="card billing-card" style="grid-column:1/-1;">
            <h3>D. Plan de facturación del proyecto</h3>
            <h4 class="billing-subtitle">Matriz de control financiero del contrato</h4>

            <?php if ($canManageBilling): ?>
            <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/billing-plan" class="grid-form billing-model-form" style="margin-bottom:14px;">
                <label>Tipo de facturación
                    <select name="billing_model" required>
                        <option value="milestones">Por hitos</option>
                        <option value="advance_balance">Anticipo + saldo</option>
                        <option value="recurring">Recurrente</option>
                        <option value="consumption">Por consumo</option>
                    </select>
                </label>
                <label>Concepto<input type="text" name="concept" placeholder="Anticipo, soporte, fase 1"></label>
                <label>Nombre hito<input type="text" name="milestone_name" placeholder="Entrega fase 1"></label>
                <label>%<input type="number" name="percentage" step="0.01" min="0" max="100"></label>
                <label>Valor<input type="number" name="amount" step="0.01" min="0"></label>
                <label>Frecuencia
                    <select name="billing_frequency">
                        <option value="">-</option>
                        <option value="monthly">Mensual</option>
                        <option value="quarterly">Trimestral</option>
                        <option value="custom">Personalizada</option>
                    </select>
                </label>
                <label>Condición esperada<input type="text" name="expected_trigger" placeholder="Aprobación cliente"></label>
                <label>Fecha esperada<input type="date" name="expected_date"></label>
                <label>Estado
                    <select name="status" required>
                        <option value="pendiente">Pendiente</option>
                        <option value="listo_para_facturar">Listo para facturar</option>
                        <option value="facturado">Facturado</option>
                        <option value="pagado">Pagado</option>
                        <option value="atrasado">Atrasado</option>
                    </select>
                </label>
                <div><button class="action-btn primary billing-primary-cta" type="submit">Agregar a matriz</button></div>
            </form>
            <?php endif; ?>

            <div class="table-wrapper">
                <table class="billing-matrix-table">
                    <thead><tr><th>Concepto</th><th>Fecha esperada</th><th>Valor</th><th>Estado</th><th>Factura asociada</th></tr></thead>
                    <tbody>
                    <?php foreach ($billingPlanItems as $item): ?>
                        <?php
                            $status = (string) ($item['status'] ?? 'pendiente');
                            $resolved = isset($item['resolved_amount']) ? (float) $item['resolved_amount'] : (float) ($item['amount'] ?? 0);
                            $percentText = isset($item['percentage']) && $item['percentage'] !== null ? rtrim(rtrim(number_format((float) $item['percentage'], 2, '.', ''), '0'), '.') . '%' : '';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($item['concept'] ?: ($item['milestone_name'] ?? '-'))) ?></td>
                            <td><?= htmlspecialchars((string) ($item['expected_date'] ?? '-')) ?></td>
                            <td><?= $percentText !== '' ? htmlspecialchars($percentText) . ' · ' : '' ?><?= $fmtMoney($resolved) ?></td>
                            <td><span class="pill status-badge status-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($controlStatusLabels[$status] ?? str_replace('_', ' ', $status)) ?></span></td>
                            <td><?= !empty($item['invoice_id']) ? '#' . (int) $item['invoice_id'] : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="card billing-card" style="grid-column:1/-1;">
            <h3>Gestión de facturas emitidas</h3>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Número</th><th>Emisión</th><th>Periodo</th><th>Valor</th><th>Estado</th><th>Pago</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($projectInvoices as $inv): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($inv['invoice_number'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($inv['issued_at'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) (($inv['period_start'] ?? '-') . ' a ' . ($inv['period_end'] ?? '-'))) ?></td>
                            <td><?= $fmtMoney((float) ($inv['amount'] ?? 0)) ?></td>
                            <td><?= htmlspecialchars($statusLabels[$inv['status'] ?? ''] ?? (string) ($inv['status'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($inv['paid_at'] ?? '-')) ?></td>
                            <td class="actions">
                                <?php if ($canManageBilling): ?><button class="action-btn small" type="button" data-open-modal="invoice-modal" data-prefill='<?= htmlspecialchars(json_encode($inv), ENT_QUOTES, 'UTF-8') ?>'>Editar</button><?php endif; ?>
                                <?php if ($canMarkInvoicePaid && ($inv['status'] ?? '') !== 'paid'): ?><form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/invoices/<?= (int) ($inv['id'] ?? 0) ?>/mark-paid"><button class="action-btn small" type="submit">Marcar como pagada</button></form><?php endif; ?>
                                <?php if ($canVoidInvoice && ($inv['status'] ?? '') !== 'cancelled'): ?><form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/invoices/<?= (int) ($inv['id'] ?? 0) ?>/cancel"><button class="action-btn small" type="submit">Anular</button></form><?php endif; ?>
                                <?php if ($canDeleteInvoice): ?><form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/invoices/<?= (int) ($inv['id'] ?? 0) ?>/delete" onsubmit="return confirm('¿Eliminar factura?');"><button class="action-btn small danger" type="submit">Eliminar</button></form><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </section>
</section>

<?php if ($canManageBilling): ?>
<div class="modal-backdrop" data-modal="invoice-modal" hidden>
    <div class="modal-card">
        <h3>Registrar factura</h3>
        <form method="POST" id="invoice-form" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/invoices" class="grid-form">
            <input type="hidden" name="_invoice_id" id="invoice-id">
            <label>Número factura<input type="text" name="invoice_number" id="invoice-number" required></label>
            <label>Fecha emisión<input type="date" name="issued_at" id="issued-at" required></label>
            <label>Periodo desde<input type="date" name="period_start" id="period-start"></label>
            <label>Periodo hasta<input type="date" name="period_end" id="period-end"></label>
            <label>Valor<input type="number" step="0.01" min="0" name="amount" id="amount" required></label>
            <label>Estado<select name="status" id="status"><?php foreach (($invoiceStatuses ?? []) as $st): ?><option value="<?= htmlspecialchars($st) ?>"><?= htmlspecialchars($statusLabels[$st] ?? $st) ?></option><?php endforeach; ?></select></label>
            <label id="paid-at-field" style="display:none;">Fecha de pago<input type="date" name="paid_at" id="paid-at"></label>
            <label>Adjuntar archivo<input type="text" name="attachment_path" id="attachment-path"></label>
            <label style="grid-column:1/-1;">Observaciones<textarea name="notes" rows="3" id="notes"></textarea></label>
            <div><button class="action-btn primary" type="submit">Guardar</button></div>
            <div><button class="action-btn" type="button" data-close-modal="invoice-modal">Cerrar</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('click', (e) => {
  const openBtn = e.target.closest('[data-open-modal]');
  if (openBtn) {
    const name = openBtn.getAttribute('data-open-modal');
    const modal = document.querySelector(`[data-modal="${name}"]`);
    if (modal) { modal.hidden = false; }
    const prefill = openBtn.getAttribute('data-prefill');
    const form = document.getElementById('invoice-form');
    if (prefill && form) {
      const inv = JSON.parse(prefill);
      document.getElementById('invoice-id').value = inv.id || '';
      form.action = `<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/invoices/${inv.id}/update`;
      ['invoice_number','issued_at','period_start','period_end','amount','status','paid_at','attachment_path','notes'].forEach((k) => {
        const el = document.getElementById(k.replaceAll('_','-')); if (el) el.value = inv[k] || '';
      });
    }
  }
  const closeBtn = e.target.closest('[data-close-modal]');
  if (closeBtn) {
    const modal = document.querySelector(`[data-modal="${closeBtn.getAttribute('data-close-modal')}"]`);
    if (modal) modal.hidden = true;
  }
});
const statusEl = document.getElementById('status');
if (statusEl) {
  const syncPaid = () => document.getElementById('paid-at-field').style.display = statusEl.value === 'paid' ? 'block' : 'none';
  statusEl.addEventListener('change', syncPaid); syncPaid();
}
const billingType = document.getElementById('billing-type');
if (billingType) {
  const syncRate = () => document.getElementById('hourly-rate-field').style.display = billingType.value === 'hours' ? 'block' : 'none';
  billingType.addEventListener('change', syncRate); syncRate();
}

const billableToggle = document.getElementById('is-billable');
const billableSwitch = document.getElementById('billable-switch');
const billableFields = document.getElementById('billable-config-fields');
const billableBadge = document.getElementById('billable-badge');
const billableOffMessage = document.getElementById('billable-off-message');
const billingForm = document.getElementById('billing-config-form');

if (billableToggle && billableFields) {
  const isBillableOn = () => billableToggle.value === '1';
  const renderBillableSwitch = (on) => {
    if (!billableSwitch) {
      return;
    }
    const track = billableSwitch.querySelector('.billable-switch-track');
    const thumb = billableSwitch.querySelector('.billable-switch-thumb');
    if (track) {
      track.setAttribute('fill', on ? '#22a35a' : '#b8bec8');
      track.setAttribute('stroke', on ? '#1d8b4c' : 'rgba(0,0,0,.14)');
    }
    if (thumb) {
      thumb.setAttribute('cx', on ? '36' : '15');
    }
  };

  const syncBillable = () => {
    const on = isBillableOn();
    billableFields.style.display = on ? 'contents' : 'none';
    if (billableOffMessage) {
      billableOffMessage.style.display = on ? 'none' : 'block';
    }
    if (billableBadge) {
      billableBadge.textContent = on ? 'Facturable' : 'No facturable';
      billableBadge.classList.toggle('is-on', on);
      billableBadge.classList.toggle('is-off', !on);
    }
    if (billableSwitch) {
      billableSwitch.setAttribute('aria-checked', on ? 'true' : 'false');
      billableSwitch.classList.toggle('is-on', on);
      billableSwitch.classList.toggle('is-off', !on);
      renderBillableSwitch(on);
    }
    billableFields.querySelectorAll('input, select, textarea').forEach((field) => {
      field.disabled = !on;
    });
  };

  const autoSaveBillable = async (previousValue) => {
    try {
      const response = await fetch(`<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/billing-toggle`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ is_billable: isBillableOn() ? 1 : 0 }),
      });

      const payload = await response.json();
      if (!response.ok || payload?.status !== 'ok') {
        throw new Error(payload?.message || 'No se pudo actualizar el estado de facturación.');
      }

      const persisted = payload?.is_billable === 1 ? '1' : '0';
      billableToggle.value = persisted;
      syncBillable();
    } catch (error) {
      billableToggle.value = previousValue;
      syncBillable();
      window.alert(error instanceof Error ? error.message : 'No se pudo guardar el estado de facturación. Inténtalo nuevamente.');
      console.error('No fue posible guardar el estado de facturación', error);
    }
  };

  if (billableSwitch) {
    billableSwitch.addEventListener('click', () => {
      const previousValue = billableToggle.value;
      billableToggle.value = isBillableOn() ? '0' : '1';
      syncBillable();
      autoSaveBillable(previousValue);
    });
  }

  syncBillable();
}

if (billingForm) {
  billingForm.addEventListener('change', async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement || target instanceof HTMLSelectElement || target instanceof HTMLTextAreaElement)) {
      return;
    }
    if (target.id === 'is-billable') {
      return;
    }

    const formData = new FormData(billingForm);
    if (billableToggle && billableToggle.value === '1') {
      formData.set('is_billable', '1');
    }

    try {
      await fetch(billingForm.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      });
    } catch (error) {
      console.error('No fue posible autoguardar la configuración', error);
    }
  });
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
.billing-layout > .billing-card + .billing-card { margin-top: 24px; }
.billing-layout .grid-form label { margin-bottom: 10px; }
.billing-contract-card .grid-form { margin-bottom: 0; }

.billing-contract-readonly-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(180px, 1fr));
  gap: 12px;
  margin-top: 12px;
}
.billing-contract-readonly-grid div {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.billing-contract-readonly-grid span {
  color: #6b7280;
  font-size: 12px;
}

.billing-progress-wrap {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.billing-progress-track {
  width: 100%;
  height: 14px;
  border-radius: 999px;
  background: #e5e7eb;
  overflow: hidden;
}
.billing-progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #2563eb 0%, #14b8a6 100%);
}
.billing-progress-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}
.billing-progress-meta span { color: #4b5563; }

.finance-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(170px, 1fr));
  gap: 12px;
}
.finance-kpi-grid .kpi-card {
  background: #f9fafb;
  border-radius: 8px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.finance-kpi-grid .kpi-card span {
  color: #6b7280;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: .02em;
}
.finance-kpi-grid .kpi-card strong {
  font-size: 1.25rem;
  line-height: 1.2;
}

.billing-subtitle {
  margin: 24px 0 12px;
  font-size: 1rem;
  color: #1f2937;
}
.billing-subtitle:first-of-type { margin-top: 8px; }

.billing-primary-cta {
  background: var(--primary, #2563eb);
  border-color: var(--primary, #2563eb);
  color: #fff;
  font-weight: 700;
}

.billing-matrix-table {
  width: 100%;
  border-collapse: collapse;
}
.billing-matrix-table thead th {
  text-align: left;
  font-size: 12px;
  text-transform: uppercase;
  color: #6b7280;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}
.billing-matrix-table th,
.billing-matrix-table td {
  padding: 12px;
  border-bottom: 1px solid #e5e7eb;
}

.status-badge { border-radius: 999px; font-weight: 600; }
.status-pendiente { background: #9ca3af22 !important; color: #9ca3af !important; }
.status-listo_para_facturar { background: #f59e0b22 !important; color: #f59e0b !important; }
.status-facturado { background: #22c55e22 !important; color: #22c55e !important; }
.status-pagado { background: #3b82f622 !important; color: #3b82f6 !important; }
.status-atrasado { background: #ef444422 !important; color: #ef4444 !important; }

.contract-switch-row { grid-column: 1 / -1; display:flex; align-items:center; gap:12px; }
.contract-title { font-weight:700; }
.contract-switch-row .toggle-switch { width:52px; height:30px; border-radius:999px; border:1px solid rgba(0,0,0,.14); background:#b8bec8; padding:2px; display:inline-flex; align-items:center; justify-content:flex-start; cursor:pointer; transition:background-color .2s ease, border-color .2s ease; box-sizing:border-box; }
.contract-switch-row .toggle-switch.is-disabled { opacity:.65; cursor:not-allowed; }
.contract-switch-row .billable-switch-svg { display:block; filter: drop-shadow(0 3px 8px rgba(0,0,0,.16)); }
.contract-switch-row .billable-switch-thumb { transition:cx .2s ease; }
.contract-switch-row .toggle-switch.is-on { background:#22a35a; border-color:#1d8b4c; }
.billable-badge { padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; border:1px solid transparent; }
.billable-badge.is-on { background:color-mix(in srgb, var(--success) 18%, var(--background)); color:var(--success); border-color:color-mix(in srgb, var(--success) 35%, var(--background)); }
.billable-badge.is-off { background:color-mix(in srgb, var(--text-secondary) 14%, var(--background)); color:var(--text-secondary); border-color:color-mix(in srgb, var(--text-secondary) 30%, var(--background)); }

@media (max-width: 1024px) {
  .finance-kpi-grid,
  .billing-contract-readonly-grid {
    grid-template-columns: repeat(2, minmax(170px, 1fr));
  }
}

@media (max-width: 640px) {
  .finance-kpi-grid,
  .billing-contract-readonly-grid {
    grid-template-columns: 1fr;
  }
}
</style>
