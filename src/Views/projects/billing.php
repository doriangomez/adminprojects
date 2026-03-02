<?php
$basePath = $basePath ?? '';
$project = $project ?? [];
$billingConfig = is_array($billingConfig ?? null) ? $billingConfig : [];
$billingTotals = is_array($billingTotals ?? null) ? $billingTotals : [];
$projectInvoices = is_array($projectInvoices ?? null) ? $projectInvoices : [];
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
?>
<section class="project-shell">
    <header class="project-header">
        <div>
            <p class="eyebrow">Facturación</p>
            <h2><?= htmlspecialchars($project['name'] ?? '') ?></h2>
            <small class="section-muted">Gestión financiera contractual y de facturas del proyecto.</small>
        </div>
        <div class="project-actions">
            <a class="action-btn" href="<?= $basePath ?>/projects/billing-report?project_id=<?= (int) ($project['id'] ?? 0) ?>">Exportar CSV</a>
            <?php if ($canManageBilling): ?><button class="action-btn primary" type="button" data-open-modal="invoice-modal">Registrar factura</button><?php endif; ?>
        </div>
    </header>

    <?php $activeTab = 'facturacion'; require __DIR__ . '/_tabs.php'; ?>

    <section class="billing-layout">
        <article class="card">
            <h3>A. Configuración contractual</h3>
            <p class="section-muted">Contrato</p>
            <?php if ($canManageBilling): ?>
            <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/billing-config" class="grid-form">
                <label class="switch-field">¿Facturable?
                    <span class="switch-wrap">
                        <input type="checkbox" id="is-billable" name="is_billable" value="1" <?= ((int) ($billingConfig['is_billable'] ?? 0) === 1) ? 'checked' : '' ?>>
                        <span class="switch-slider" aria-hidden="true"></span>
                        <span id="billable-label"><?= ((int) ($billingConfig['is_billable'] ?? 0) === 1) ? 'ON' : 'OFF' ?></span>
                    </span>
                </label>
                <div id="billable-config-fields" class="grid-form-inner" style="display:<?= ((int) ($billingConfig['is_billable'] ?? 0) === 1) ? 'contents' : 'none' ?>;">
                    <label>Tipo de facturación<select name="billing_type" id="billing-type"><?php foreach (($billingTypes ?? []) as $t): ?><option value="<?= htmlspecialchars($t) ?>" <?= (($billingConfig['billing_type'] ?? '') === $t) ? 'selected' : '' ?>><?= htmlspecialchars($billingTypeLabels[$t] ?? $t) ?></option><?php endforeach; ?></select></label>
                    <label>Periodicidad<select name="billing_periodicity"><?php foreach (($billingPeriodicities ?? []) as $p): ?><option value="<?= htmlspecialchars($p) ?>" <?= (($billingConfig['billing_periodicity'] ?? '') === $p) ? 'selected' : '' ?>><?= htmlspecialchars($periodicityLabels[$p] ?? $p) ?></option><?php endforeach; ?></select></label>
                    <label>Valor del contrato<input type="number" step="0.01" min="0" name="contract_value" value="<?= htmlspecialchars((string) ($billingConfig['contract_value'] ?? '0')) ?>"></label>
                    <label>Moneda<select name="currency_code"><option value="USD" <?= $currency === 'USD' ? 'selected' : '' ?>>USD</option><option value="COP" <?= $currency === 'COP' ? 'selected' : '' ?>>COP</option></select></label>
                    <label>Fecha inicio facturación<input type="date" name="billing_start_date" value="<?= htmlspecialchars((string) ($billingConfig['billing_start_date'] ?? '')) ?>"></label>
                    <label>Fecha fin facturación<input type="date" name="billing_end_date" value="<?= htmlspecialchars((string) ($billingConfig['billing_end_date'] ?? '')) ?>"></label>
                    <label id="hourly-rate-field" style="display:<?= (($billingConfig['billing_type'] ?? '') === 'hours') ? 'block' : 'none' ?>;">Tarifa por hora<input type="number" step="0.01" min="0" name="hourly_rate" value="<?= htmlspecialchars((string) ($billingConfig['hourly_rate'] ?? '0')) ?>"></label>
                </div>
                <div><button class="action-btn primary" type="submit">Guardar configuración</button></div>
            </form>
            <?php else: ?>
                <p class="section-muted">Solo administradores y PM pueden editar la configuración contractual.</p>
            <?php endif; ?>
        </article>

        <article class="card">
            <h3>B. Resumen financiero</h3>
            <div class="kpi-grid">
                <article><span>Total contratado</span><strong><?= $fmtMoney((float) ($billingConfig['contract_value'] ?? 0)) ?></strong></article>
                <article><span>Total facturado</span><strong><?= $fmtMoney($totalInvoiced) ?></strong></article>
                <article><span>Total pagado</span><strong><?= $fmtMoney((float) ($billingTotals['total_paid'] ?? 0)) ?></strong></article>
                <article><span>Saldo por cobrar</span><strong><?= $fmtMoney((float) ($billingTotals['total_due'] ?? 0)) ?></strong></article>
                <article><span>Horas aprobadas sin facturar</span><strong><?= number_format($approvedHoursPendingInvoicing, 2) ?></strong></article>
            </div>
            <?php if (($billingConfig['billing_type'] ?? '') === 'fixed' && $totalInvoiced > (float) ($billingConfig['contract_value'] ?? 0)): ?>
                <p class="alert">⚠ Se superó el valor del contrato para facturación de tipo Fijo.</p>
            <?php endif; ?>
            <?php if ($hoursBillableAmount !== null): ?>
                <p class="section-muted">Facturable por horas: <strong><?= $fmtMoney($hoursBillableAmount) ?></strong> · Diferencia vs facturado: <strong><?= $fmtMoney((float) $hoursDelta) ?></strong>.</p>
            <?php endif; ?>
        </article>

        <article class="card">
            <h3>C. Gestión de facturas</h3>
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
const billableFields = document.getElementById('billable-config-fields');
const billableLabel = document.getElementById('billable-label');
if (billableToggle && billableFields) {
  const syncBillable = () => {
    const on = billableToggle.checked;
    billableFields.style.display = on ? 'contents' : 'none';
    if (billableLabel) {
      billableLabel.textContent = on ? 'ON' : 'OFF';
    }
    billableFields.querySelectorAll('input, select, textarea').forEach((field) => {
      field.disabled = !on;
    });
  };
  billableToggle.addEventListener('change', syncBillable);
  syncBillable();
}
</script>

<style>
.switch-field { grid-column: 1 / -1; display:flex; flex-direction:column; gap:8px; font-weight:600; }
.switch-wrap { display:inline-flex; align-items:center; gap:10px; }
.switch-wrap input { position:absolute; opacity:0; pointer-events:none; }
.switch-slider { width:46px; height:26px; border-radius:999px; background:var(--border); position:relative; transition:all .2s ease; }
.switch-slider::after { content:""; width:20px; height:20px; border-radius:50%; background:#fff; position:absolute; top:3px; left:3px; transition:all .2s ease; }
.switch-wrap input:checked + .switch-slider { background: var(--primary); }
.switch-wrap input:checked + .switch-slider::after { left:23px; }
</style>
