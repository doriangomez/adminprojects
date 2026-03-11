<?php
$basePath = $basePath ?? '';
$project = $project ?? [];
$billingConfig = is_array($billingConfig ?? null) ? $billingConfig : [];
$billingTotals = is_array($billingTotals ?? null) ? $billingTotals : [];
$projectInvoices = is_array($projectInvoices ?? null) ? $projectInvoices : [];
$canManageBilling = !empty($canManageBilling);
$currency = in_array(($billingConfig['currency_code'] ?? 'USD'), ['USD', 'COP'], true) ? $billingConfig['currency_code'] : 'USD';
$locale = $currency === 'COP' ? 'es-CO' : 'en-US';
$fmtMoney = static function (float $amount) use ($currency, $locale): string {
    $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
    return $formatter->formatCurrency($amount, $currency);
};

$matrixType = in_array(($billingConfig['billing_matrix_type'] ?? 'unico'), ['unico', 'recurrente'], true)
    ? $billingConfig['billing_matrix_type']
    : 'unico';
$graceMonths = max(0, (int) ($billingConfig['billing_grace_months'] ?? 0));
$statusLabels = ['draft' => 'pendiente', 'issued' => 'facturado', 'paid' => 'pagado', 'cancelled' => 'cancelado'];
$totalFacturado = (float) ($billingTotals['total_invoiced'] ?? 0);
$totalPagado = (float) ($billingTotals['total_paid'] ?? 0);
$totalPendiente = (float) ($billingTotals['total_due'] ?? 0);
?>
<section class="project-shell">
    <header class="project-header">
        <div>
            <p class="eyebrow">Facturación</p>
            <h2><?= htmlspecialchars($project['name'] ?? '') ?></h2>
            <small class="section-muted">Control simple de qué, cuándo y cuánto facturar en el proyecto.</small>
        </div>
    </header>

    <?php $activeTab = 'facturacion'; require __DIR__ . '/_tabs.php'; ?>

    <section class="billing-layout">
        <article class="card">
            <h3>Resumen</h3>
            <div class="kpi-grid" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:12px;">
                <div class="card" style="margin:0;"><small class="section-muted">Total facturado</small><div><strong><?= $fmtMoney($totalFacturado) ?></strong></div></div>
                <div class="card" style="margin:0;"><small class="section-muted">Total pagado</small><div><strong><?= $fmtMoney($totalPagado) ?></strong></div></div>
                <div class="card" style="margin:0;"><small class="section-muted">Total pendiente</small><div><strong><?= $fmtMoney($totalPendiente) ?></strong></div></div>
            </div>
        </article>

        <article class="card">
            <h3>Configuración de facturación</h3>
            <?php if ($canManageBilling): ?>
            <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/billing-config" class="grid-form">
                <input type="hidden" name="is_billable" value="1">
                <label>Tipo de facturación
                    <select name="billing_matrix_type">
                        <option value="unico" <?= $matrixType === 'unico' ? 'selected' : '' ?>>unico</option>
                        <option value="recurrente" <?= $matrixType === 'recurrente' ? 'selected' : '' ?>>recurrente</option>
                    </select>
                </label>
                <label>Valor total a facturar
                    <input type="number" step="0.01" min="0" name="contract_value" value="<?= htmlspecialchars((string) ($billingConfig['contract_value'] ?? '0')) ?>">
                </label>
                <label>Moneda
                    <select name="currency_code"><option value="USD" <?= $currency === 'USD' ? 'selected' : '' ?>>USD</option><option value="COP" <?= $currency === 'COP' ? 'selected' : '' ?>>COP</option></select>
                </label>
                <label>Fecha inicio
                    <input type="date" name="billing_start_date" value="<?= htmlspecialchars((string) ($billingConfig['billing_start_date'] ?? '')) ?>">
                </label>
                <label>Fecha fin
                    <input type="date" name="billing_end_date" value="<?= htmlspecialchars((string) ($billingConfig['billing_end_date'] ?? '')) ?>">
                </label>
                <label>Meses de gracia
                    <input type="number" min="0" step="1" name="billing_grace_months" value="<?= $graceMonths ?>">
                </label>
                <div style="grid-column:1/-1;"><button class="action-btn primary" type="submit">Guardar y generar matriz</button></div>
            </form>
            <?php else: ?>
                <p class="section-muted">No tienes permisos para editar la configuración de facturación.</p>
            <?php endif; ?>
        </article>

        <article class="card">
            <h3>Matriz de facturación</h3>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>periodo</th><th>concepto</th><th>valor</th><th>estado</th><th>numero_factura</th><th>fecha_pago</th></tr></thead>
                    <tbody>
                    <?php foreach ($projectInvoices as $inv): ?>
                        <?php if (($inv['status'] ?? '') === 'cancelled') { continue; } ?>
                        <tr>
                            <td><?= htmlspecialchars(substr((string) ($inv['period_start'] ?? ''), 0, 7)) ?></td>
                            <td><?= htmlspecialchars((string) (($inv['notes'] ?? '') !== '' ? $inv['notes'] : 'Evento de facturación')) ?></td>
                            <td><?= $fmtMoney((float) ($inv['amount'] ?? 0)) ?></td>
                            <td><?= htmlspecialchars($statusLabels[$inv['status'] ?? 'draft'] ?? 'pendiente') ?></td>
                            <td><?= htmlspecialchars((string) ($inv['invoice_number'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($inv['paid_at'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </section>
</section>
