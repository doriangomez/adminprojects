<?php
$project = $project ?? [];
$requirements = $requirements ?? [];
$indicator = $requirementsIndicator ?? ['applicable' => false, 'value' => null, 'status' => 'no_aplica'];
$period = $requirementsPeriod ?? ['start_date' => date('Y-m-01'), 'end_date' => date('Y-m-t')];
$audit = $requirementsAudit ?? [];
$target = (int) ($requirementsTarget ?? 90);
$statusMap = ['verde' => '🟢', 'amarillo' => '🟡', 'rojo' => '🔴', 'no_aplica' => '⚪'];
$workflowStatuses = $requirementsStatuses ?? ['borrador', 'en_revision', 'aprobado', 'rechazado', 'entregado'];
$workflowTransitions = [
    'borrador' => ['en_revision'],
    'definido' => ['en_revision'],
    'en_revision' => ['aprobado', 'rechazado'],
    'rechazado' => ['en_revision'],
    'aprobado' => ['entregado'],
    'entregado' => [],
];
$statusLabels = [
    'borrador' => 'Borrador',
    'definido' => 'Definido',
    'en_revision' => 'En revisión',
    'aprobado' => 'Aprobado',
    'rechazado' => 'Rechazado',
    'entregado' => 'Entregado',
];
$statusBadgeStyle = [
    'borrador'    => 'background:#e5e7eb;color:#374151;',
    'definido'    => 'background:#ede9fe;color:#6d28d9;',
    'en_revision' => 'background:#fef3c7;color:#92400e;',
    'aprobado'    => 'background:#d1fae5;color:#065f46;',
    'rechazado'   => 'background:#fee2e2;color:#991b1b;',
    'entregado'   => 'background:#dbeafe;color:#1e40af;',
];
$statusBadgeEmoji = [
    'borrador'    => '⚪',
    'definido'    => '🟣',
    'en_revision' => '🟡',
    'aprobado'    => '🟢',
    'rechazado'   => '🔴',
    'entregado'   => '🔵',
];

$applicable = (bool) ($indicator['applicable'] ?? false);
$compliance = $applicable ? (float) ($indicator['value'] ?? 0) : 0.0;
$total = (int) ($indicator['total_requirements'] ?? 0);
$approved = (int) ($indicator['approved_requirements'] ?? 0);
$inReview = (int) ($indicator['in_review_requirements'] ?? 0);
$rejected = (int) ($indicator['rejected_requirements'] ?? 0);

$saved = isset($_GET['saved']);
$updated = isset($_GET['updated']);
$deleted = isset($_GET['deleted']);
$error = trim((string) ($_GET['error'] ?? ''));
$activeTab = 'requisitos';
require __DIR__ . '/_tabs.php';
?>

<style>
    .req-indicator-card { padding: 20px; margin-bottom: 16px; }
    .req-indicator-header { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
    .req-indicator-header h3 { margin: 0; font-size: 16px; }
    .req-traffic-light { font-size: 22px; line-height: 1; }
    .req-compliance-value { font-size: 28px; font-weight: 800; color: var(--text-primary); }
    .req-meta-label { font-size: 13px; color: var(--text-secondary); margin-left: 4px; }

    .req-kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(120px, 1fr)); gap: 10px; margin-bottom: 16px; }
    .req-kpi { border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; background: color-mix(in srgb, var(--surface) 92%, var(--background)); }
    .req-kpi span { display: block; font-size: 11px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; }
    .req-kpi strong { font-size: 28px; font-weight: 800; color: var(--text-primary); }

    .req-progress-wrap { margin-bottom: 6px; }
    .req-progress-track { position: relative; width: 100%; height: 14px; border-radius: 999px; background: color-mix(in srgb, var(--border) 45%, var(--background)); overflow: visible; }
    .req-progress-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #16a34a, #22c55e); transition: width .4s; max-width: 100%; }
    .req-progress-meta-marker {
        position: absolute; top: -4px; bottom: -4px; width: 2px;
        background: #f59e0b; border-radius: 2px;
    }
    .req-progress-meta-marker::after {
        content: attr(data-label);
        position: absolute; top: -20px; left: 50%; transform: translateX(-50%);
        font-size: 10px; font-weight: 700; color: #92400e;
        white-space: nowrap; background: #fef3c7; border-radius: 4px; padding: 1px 5px;
    }
    .req-progress-labels { display: flex; justify-content: space-between; margin-top: 6px; font-size: 12px; color: var(--text-secondary); }

    .req-secondary { font-size: 12px; color: var(--text-secondary); margin-top: 10px; }

    .req-alert { border-radius: 10px; padding: 10px 12px; margin-bottom: 10px; font-weight: 600; border: 1px solid transparent; }
    .req-alert.ok { color: var(--success); border-color: color-mix(in srgb, var(--success) 35%, var(--border)); background: color-mix(in srgb, var(--success) 10%, var(--surface)); }
    .req-alert.err { color: var(--danger); border-color: color-mix(in srgb, var(--danger) 35%, var(--border)); background: color-mix(in srgb, var(--danger) 10%, var(--surface)); }

    .req-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; white-space: nowrap; }
</style>

<section class="card req-indicator-card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
        <div>
            <h3 style="margin:0 0 2px;">Cumplimiento de requisitos del cliente</h3>
            <p style="margin:0;font-size:13px;color:var(--text-secondary);">Aprobados ÷ Total × 100</p>
        </div>
        <form method="GET" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
            <label style="font-size:12px;">Inicio<br><input type="date" name="start_date" value="<?= htmlspecialchars((string) $period['start_date']) ?>" style="font-size:12px;"></label>
            <label style="font-size:12px;">Fin<br><input type="date" name="end_date" value="<?= htmlspecialchars((string) $period['end_date']) ?>" style="font-size:12px;"></label>
            <button class="action-btn" style="font-size:12px;">Filtrar</button>
        </form>
    </div>

    <div style="display:flex;align-items:baseline;gap:12px;margin-bottom:16px;">
        <span class="req-traffic-light"><?= $statusMap[$indicator['status'] ?? 'no_aplica'] ?? '⚪' ?></span>
        <span class="req-compliance-value"><?= $applicable ? number_format($compliance, 1) . '%' : '—' ?></span>
        <span class="req-meta-label">Meta: <strong style="color:var(--text-primary);"><?= $target ?>%</strong></span>
    </div>

    <div class="req-kpi-grid">
        <article class="req-kpi">
            <span>Total requisitos</span>
            <strong><?= $total ?></strong>
        </article>
        <article class="req-kpi" style="border-color:color-mix(in srgb,#22c55e 40%,var(--border));">
            <span>Aprobados</span>
            <strong style="color:#16a34a;"><?= $approved ?></strong>
        </article>
        <article class="req-kpi" style="border-color:color-mix(in srgb,#f59e0b 40%,var(--border));">
            <span>En revisión</span>
            <strong style="color:#92400e;"><?= $inReview ?></strong>
        </article>
        <article class="req-kpi" style="border-color:color-mix(in srgb,#ef4444 40%,var(--border));">
            <span>Rechazados</span>
            <strong style="color:#991b1b;"><?= $rejected ?></strong>
        </article>
    </div>

    <div class="req-progress-wrap">
        <div class="req-progress-track">
            <div class="req-progress-fill" style="width:<?= max(0, min(100, $compliance)) ?>%;height:100%;border-radius:999px;"></div>
            <?php if ($target > 0 && $target <= 100): ?>
                <div class="req-progress-meta-marker"
                     data-label="Meta <?= $target ?>%"
                     style="left:<?= $target ?>%;"></div>
            <?php endif; ?>
        </div>
        <div class="req-progress-labels">
            <span>0%</span>
            <span style="color:var(--text-primary);font-weight:700;"><?= number_format($compliance, 1) ?>% actual</span>
            <span>100%</span>
        </div>
    </div>

    <?php if ($applicable): ?>
    <p class="req-secondary">
        Promedio reprocesos: <?= number_format((float) ($indicator['avg_reprocess_per_requirement'] ?? 0), 2) ?>
        &nbsp;·&nbsp; Días promedio aprobación: <?= number_format((float) ($indicator['avg_days_to_approval'] ?? 0), 1) ?>
        &nbsp;·&nbsp; % con más de 2 reprocesos: <?= number_format((float) ($indicator['percent_over_two_reprocess'] ?? 0), 1) ?>%
    </p>
    <?php endif; ?>
</section>

<section class="card" style="padding:16px;margin-bottom:16px;">
    <?php if ($saved): ?>
        <div class="req-alert ok">Requisito registrado correctamente.</div>
    <?php endif; ?>
    <?php if ($updated): ?>
        <div class="req-alert ok">Estado actualizado.</div>
    <?php endif; ?>
    <?php if ($deleted): ?>
        <div class="req-alert ok">Requisito eliminado.</div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="req-alert err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <h4 style="margin-top:0;">Registrar requisito</h4>
    <form method="POST" action="/projects/<?= (int) ($project['id'] ?? 0) ?>/requirements"
          style="display:grid;grid-template-columns:repeat(4,minmax(120px,1fr));gap:10px;">
        <input name="name" placeholder="Nombre del requisito" required>
        <input name="version" placeholder="Versión" value="1.0" required>
        <input type="date" name="delivery_date">
        <select name="status">
            <?php foreach ($workflowStatuses as $status): ?>
                <option value="<?= htmlspecialchars($status) ?>"
                    <?= $status === 'borrador' ? 'selected' : '' ?>>
                    <?= htmlspecialchars($statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status))) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input name="description" style="grid-column:span 3;" placeholder="Descripción (opcional)">
        <button class="action-btn primary" type="submit">Guardar</button>
    </form>
</section>

<section class="card" style="padding:16px;margin-bottom:16px;">
    <h4 style="margin-top:0;">Listado de requisitos</h4>
    <?php if (empty($requirements)): ?>
        <p style="color:var(--text-secondary);font-size:13px;">No hay requisitos registrados aún.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Requisito</th>
                <th>Versión</th>
                <th>Entrega</th>
                <th>Estado</th>
                <th>Descripción</th>
                <th>Reprocesos</th>
                <th>Cambiar estado</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($requirements as $row): ?>
            <?php
                $rowStatus = strtolower(trim((string) ($row['status'] ?? 'borrador')));
                $nextStatuses = $workflowTransitions[$rowStatus] ?? [];
                $hasTransitions = !empty($nextStatuses);
                $badgeStyle = $statusBadgeStyle[$rowStatus] ?? 'background:#e5e7eb;color:#374151;';
                $badgeEmoji = $statusBadgeEmoji[$rowStatus] ?? '⚪';
                $badgeLabel = $statusLabels[$rowStatus] ?? ucfirst(str_replace('_', ' ', $rowStatus));
            ?>
            <tr>
                <td><strong><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></strong></td>
                <td><?= htmlspecialchars((string) ($row['version'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['delivery_date'] ?? '—')) ?></td>
                <td>
                    <span class="req-badge" style="<?= $badgeStyle ?>">
                        <?= $badgeEmoji ?> <?= htmlspecialchars($badgeLabel) ?>
                    </span>
                </td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                    title="<?= htmlspecialchars((string) ($row['description'] ?? '')) ?>">
                    <?= htmlspecialchars((string) ($row['description'] ?? '')) ?>
                </td>
                <td style="text-align:center;"><?= (int) ($row['reprocess_count'] ?? 0) ?></td>
                <td>
                    <?php if ($hasTransitions): ?>
                    <form method="POST"
                          action="/projects/<?= (int) ($project['id'] ?? 0) ?>/requirements/<?= (int) ($row['id'] ?? 0) ?>/status"
                          style="display:inline-flex;gap:6px;align-items:center;">
                        <select name="status" style="font-size:12px;">
                            <?php foreach ($nextStatuses as $option): ?>
                                <option value="<?= htmlspecialchars($option) ?>">
                                    <?= $statusBadgeEmoji[$option] ?? '' ?> <?= htmlspecialchars($statusLabels[$option] ?? ucfirst(str_replace('_', ' ', $option))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="action-btn" type="submit" style="font-size:12px;">→</button>
                    </form>
                    <?php else: ?>
                        <span style="font-size:12px;color:var(--text-secondary);">—</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<section class="card" style="padding:16px;">
    <h4 style="margin-top:0;">Historial de cambios</h4>
    <?php if (empty($audit)): ?>
        <p style="color:var(--text-secondary);font-size:13px;">Sin cambios registrados.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr><th>Fecha</th><th>Requisito</th><th>Cambio</th><th>Usuario</th><th>Notas</th></tr>
        </thead>
        <tbody>
        <?php foreach ($audit as $row): ?>
            <?php
                $from = (string) ($row['from_status'] ?? '');
                $to   = (string) ($row['to_status'] ?? '');
                $fromLabel = ($statusLabels[$from] ?? ucfirst(str_replace('_', ' ', $from)));
                $fromEmoji = ($statusBadgeEmoji[$from] ?? '⚪');
                $toLabel   = ($statusLabels[$to] ?? ucfirst(str_replace('_', ' ', $to)));
                $toEmoji   = ($statusBadgeEmoji[$to] ?? '⚪');
            ?>
            <tr>
                <td style="white-space:nowrap;font-size:12px;"><?= htmlspecialchars((string) ($row['changed_at'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['requirement_name'] ?? '')) ?></td>
                <td style="white-space:nowrap;">
                    <span class="req-badge" style="<?= $statusBadgeStyle[$from] ?? '' ?>;font-size:11px;"><?= $fromEmoji ?> <?= htmlspecialchars($fromLabel) ?></span>
                    →
                    <span class="req-badge" style="<?= $statusBadgeStyle[$to] ?? '' ?>;font-size:11px;"><?= $toEmoji ?> <?= htmlspecialchars($toLabel) ?></span>
                </td>
                <td><?= htmlspecialchars((string) ($row['changed_by_name'] ?? '')) ?></td>
                <td style="font-size:12px;color:var(--text-secondary);"><?= htmlspecialchars((string) ($row['notes'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>
