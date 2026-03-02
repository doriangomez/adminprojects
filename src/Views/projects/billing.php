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
            <?php if ($canManageBilling): ?><button class="action-btn primary" type="button" data-open-modal="invoice-modal">Registrar factura</button><?php endif; ?>
        </div>
    </header>

    <?php $activeTab = 'facturacion'; require __DIR__ . '/_tabs.php'; ?>

    <section class="billing-layout">
        <article class="card">
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
            <div style="margin-top:12px;">
                <a class="action-btn" href="<?= $basePath ?>/projects/billing-report?project_id=<?= (int) ($project['id'] ?? 0) ?>">Exportar CSV</a>
            </div>
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
        method: 'PATCH',
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
</style>
