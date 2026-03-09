<?php
$project = $project ?? [];
$requirements = $requirements ?? [];
$indicator = $requirementsIndicator ?? ['applicable' => false, 'value' => null, 'status' => 'no_aplica'];
$period = $requirementsPeriod ?? ['start_date' => date('Y-m-01'), 'end_date' => date('Y-m-t')];
$audit = $requirementsAudit ?? [];
$workflowStatuses = $requirementsStatuses ?? ['borrador', 'en_revision', 'aprobado', 'entregado', 'rechazado'];
$workflowTransitions = [
    'borrador' => ['en_revision'],
    'en_revision' => ['aprobado', 'rechazado'],
    'rechazado' => ['en_revision'],
    'aprobado' => ['entregado'],
    'entregado' => [],
];
$statusLabels = [
    'borrador' => 'Borrador',
    'en_revision' => 'En revisión',
    'aprobado' => 'Aprobado',
    'entregado' => 'Entregado',
    'rechazado' => 'Rechazado',
];
$statusBadgeClass = [
    'borrador' => 'badge-neutral',
    'en_revision' => 'badge-warning',
    'aprobado' => 'badge-success',
    'entregado' => 'badge-info',
    'rechazado' => 'badge-danger',
];
$statusIcons = [
    'borrador' => '○',
    'en_revision' => '◐',
    'aprobado' => '●',
    'entregado' => '✓',
    'rechazado' => '✗',
];
$totalRequirements = (int) ($indicator['total_requirements'] ?? 0);
$approvedRequirements = (int) ($indicator['approved_requirements'] ?? 0);
$inReviewRequirements = (int) ($indicator['in_review_requirements'] ?? 0);
$rejectedRequirements = (int) ($indicator['rejected_requirements'] ?? 0);
$pendingRequirements = (int) ($indicator['pending_requirements'] ?? 0);
$compliance = $totalRequirements > 0 ? (float) ($indicator['value'] ?? 0) : 0.0;
$targetMeta = max(0.0, min(100.0, (float) ($requirementsTargetMeta ?? 95)));
$meetsTarget = $totalRequirements > 0 && $compliance >= $targetMeta;
$saved = isset($_GET['saved']);
$updated = isset($_GET['updated']);
$deleted = isset($_GET['deleted']);
$error = trim((string) ($_GET['error'] ?? ''));
$canDelete = ((int) ($currentUser['can_delete_requirement_history'] ?? 0)) === 1;
$canManage = $canManage ?? false;
$activeTab = 'requisitos';
require __DIR__ . '/_tabs.php';
?>

<style>
    .req-kpi-grid { display:grid; grid-template-columns:repeat(5,minmax(110px,1fr)); gap:8px; margin-top:10px; }
    .req-kpi { border:1px solid var(--border); border-radius:10px; padding:10px 12px; background:color-mix(in srgb, var(--surface) 92%, var(--background)); }
    .req-kpi .label { font-size:11px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.04em; display:block; }
    .req-kpi .value { font-size:24px; font-weight:900; color:var(--text-primary); display:block; margin-top:2px; }
    .req-kpi.kpi-approved .value { color:var(--success); }
    .req-kpi.kpi-review .value { color:var(--warning); }
    .req-kpi.kpi-rejected .value { color:var(--danger); }
    .req-compliance-bar { margin-top:10px; }
    .req-bar-track { width:100%; height:14px; border-radius:999px; background:color-mix(in srgb, var(--border) 40%, var(--background)); overflow:hidden; }
    .req-bar-fill { height:100%; border-radius:999px; transition:width .4s; }
    .req-bar-fill.verde { background:linear-gradient(90deg,#16a34a,#22c55e); }
    .req-bar-fill.amarillo { background:linear-gradient(90deg,#d97706,#f59e0b); }
    .req-bar-fill.rojo { background:linear-gradient(90deg,#dc2626,#ef4444); }
    .req-bar-fill.no_aplica { background:var(--border); }
    .req-bar-labels { display:flex; justify-content:space-between; align-items:center; margin-top:5px; font-size:12px; color:var(--text-secondary); }
    .req-bar-labels strong { font-size:14px; color:var(--text-primary); }
    .req-target-line { margin-top:4px; font-size:12px; }
    .req-alert { border-radius:8px; padding:9px 12px; margin-bottom:10px; font-weight:600; border:1px solid transparent; font-size:13px; }
    .req-alert.ok { color:var(--success); border-color:color-mix(in srgb,var(--success) 35%,var(--border)); background:color-mix(in srgb,var(--success) 8%,var(--surface)); }
    .req-alert.error { color:var(--danger); border-color:color-mix(in srgb,var(--danger) 35%,var(--border)); background:color-mix(in srgb,var(--danger) 8%,var(--surface)); }
    .req-alert.warn { color:#b45309; border-color:color-mix(in srgb,var(--warning) 35%,var(--border)); background:color-mix(in srgb,var(--warning) 8%,var(--surface)); }
    .status-badge {
        display:inline-flex; align-items:center; gap:5px; padding:3px 9px;
        border-radius:999px; border:1px solid var(--border); font-size:12px; font-weight:700; white-space:nowrap;
    }
    .badge-neutral { background:color-mix(in srgb,var(--text-secondary) 10%,var(--surface)); color:var(--text-secondary); }
    .badge-warning { background:color-mix(in srgb,var(--warning) 12%,var(--surface)); color:#92400e; border-color:color-mix(in srgb,var(--warning) 35%,var(--border)); }
    .badge-success { background:color-mix(in srgb,var(--success) 12%,var(--surface)); color:var(--success); border-color:color-mix(in srgb,var(--success) 35%,var(--border)); }
    .badge-info { background:color-mix(in srgb,#0ea5e9 12%,var(--surface)); color:#0369a1; border-color:color-mix(in srgb,#0ea5e9 35%,var(--border)); }
    .badge-danger { background:color-mix(in srgb,var(--danger) 12%,var(--surface)); color:var(--danger); border-color:color-mix(in srgb,var(--danger) 35%,var(--border)); }
    .req-form-grid { display:grid; grid-template-columns:1.5fr 80px 120px 1fr auto; gap:8px; align-items:end; }
    .req-table-actions { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
    .req-note { font-size:11px; color:var(--text-secondary); font-style:italic; }
    .workflow-hint { font-size:11px; color:var(--text-secondary); margin-top:2px; }
</style>

<section class="card" style="padding:16px;margin-bottom:16px;">
    <h3 style="margin:0 0 10px;">Indicador de cumplimiento</h3>
    <form method="GET" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:12px;">
        <label style="font-size:13px;">Desde <input type="date" name="start_date" value="<?= htmlspecialchars((string) $period['start_date']) ?>" style="margin-left:4px;"></label>
        <label style="font-size:13px;">Hasta <input type="date" name="end_date" value="<?= htmlspecialchars((string) $period['end_date']) ?>" style="margin-left:4px;"></label>
        <button class="action-btn">Filtrar</button>
    </form>

    <div class="req-kpi-grid">
        <article class="req-kpi">
            <span class="label">Total</span>
            <span class="value"><?= $totalRequirements ?></span>
        </article>
        <article class="req-kpi kpi-approved">
            <span class="label">Aprobados / Entregados</span>
            <span class="value"><?= $approvedRequirements ?></span>
        </article>
        <article class="req-kpi kpi-review">
            <span class="label">En revisión</span>
            <span class="value"><?= $inReviewRequirements ?></span>
        </article>
        <article class="req-kpi kpi-rejected">
            <span class="label">Rechazados</span>
            <span class="value"><?= $rejectedRequirements ?></span>
        </article>
        <article class="req-kpi">
            <span class="label">Pendientes</span>
            <span class="value"><?= $pendingRequirements ?></span>
        </article>
    </div>

    <div class="req-compliance-bar">
        <?php $barStatus = (string) ($indicator['status'] ?? 'no_aplica'); ?>
        <div class="req-bar-track">
            <div class="req-bar-fill <?= htmlspecialchars($barStatus) ?>" style="width:<?= max(0, min(100, $compliance)) ?>%;"></div>
        </div>
        <div class="req-bar-labels">
            <span>Cumplimiento del período</span>
            <strong><?= $totalRequirements > 0 ? number_format($compliance, 1) . '%' : 'Sin datos' ?></strong>
        </div>
        <div class="req-target-line">
            Meta: <strong><?= number_format($targetMeta, 0) ?>%</strong>
            <?php if ($totalRequirements > 0): ?>
                &nbsp;—&nbsp;
                <?php if ($meetsTarget): ?>
                    <span style="color:var(--success);font-weight:700;">✓ Se cumple la meta</span>
                <?php else: ?>
                    <span style="color:var(--danger);font-weight:700;">✗ Por debajo de la meta (faltan <?= number_format($targetMeta - $compliance, 1) ?>%)</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($totalRequirements === 0): ?>
        <p class="req-note" style="margin-top:8px;">No hay requisitos con fecha de entrega en el período seleccionado. Registra requisitos o ajusta el rango de fechas.</p>
    <?php endif; ?>
</section>

<section class="card" style="padding:16px;margin-bottom:16px;">
    <?php if ($saved): ?><div class="req-alert ok">Requisito registrado correctamente.</div><?php endif; ?>
    <?php if ($updated): ?><div class="req-alert ok">Estado actualizado correctamente.</div><?php endif; ?>
    <?php if ($deleted): ?><div class="req-alert ok">Requisito eliminado.</div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="req-alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($canManage): ?>
    <h4 style="margin:0 0 10px;">Registrar requisito</h4>
    <div class="req-alert warn" style="margin-bottom:10px;">
        <strong>Flujo de estados:</strong> Borrador → En revisión → Aprobado → Entregado (o Rechazado → En revisión para reprocesos)
    </div>
    <form method="POST" action="/projects/<?= (int) ($project['id'] ?? 0) ?>/requirements">
        <div class="req-form-grid">
            <input name="name" placeholder="Nombre del requisito" required style="padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text-primary);">
            <input name="version" placeholder="Versión" value="1.0" required style="padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text-primary);">
            <input type="date" name="delivery_date" style="padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text-primary);">
            <input name="description" placeholder="Descripción (opcional)" style="padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text-primary);">
            <button class="action-btn primary" type="submit">+ Agregar</button>
        </div>
        <p class="req-note" style="margin-top:5px;">Nuevo requisito inicia siempre en estado <strong>Borrador</strong>.</p>
    </form>
    <?php endif; ?>
</section>

<section class="card" style="padding:16px;margin-bottom:16px;">
    <h4 style="margin:0 0 10px;">Listado de requisitos (<?= count($requirements) ?>)</h4>
    <?php if ($requirements === []): ?>
        <p style="color:var(--text-secondary);">Sin requisitos registrados para este proyecto.</p>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th>Requisito</th>
                <th>Versión</th>
                <th>Estado</th>
                <th>F. Entrega</th>
                <th>Descripción</th>
                <th>Reprocesos</th>
                <?php if ($canManage): ?><th>Actualizar estado</th><?php endif; ?>
                <?php if ($canDelete): ?><th>Eliminar</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($requirements as $row): ?>
            <?php
                $rowStatus = strtolower(trim((string) ($row['status'] ?? 'borrador')));
                if ($rowStatus === 'definido') {
                    $rowStatus = 'en_revision';
                }
                if (!in_array($rowStatus, $workflowStatuses, true)) {
                    $rowStatus = 'borrador';
                }
                $nextStatuses = $workflowTransitions[$rowStatus] ?? [];
                $isTerminal = $nextStatuses === [];
                $statusOptions = array_values(array_unique(array_merge([$rowStatus], $nextStatuses)));
                $badgeClass = $statusBadgeClass[$rowStatus] ?? 'badge-neutral';
                $icon = $statusIcons[$rowStatus] ?? '○';
            ?>
            <tr>
                <td style="font-weight:600;"><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['version'] ?? '1.0')) ?></td>
                <td>
                    <span class="status-badge <?= htmlspecialchars($badgeClass) ?>">
                        <?= $icon ?> <?= htmlspecialchars($statusLabels[$rowStatus] ?? ucfirst($rowStatus)) ?>
                    </span>
                    <?php if (in_array($rowStatus, ['aprobado', 'entregado'], true)): ?>
                        <div class="workflow-hint">✓ Cuenta como aprobado</div>
                    <?php elseif ($rowStatus === 'borrador'): ?>
                        <div class="workflow-hint">→ Siguiente: En revisión</div>
                    <?php elseif ($rowStatus === 'en_revision'): ?>
                        <div class="workflow-hint">→ Siguiente: Aprobar o Rechazar</div>
                    <?php elseif ($rowStatus === 'rechazado'): ?>
                        <div class="workflow-hint">→ Siguiente: Volver a revisión</div>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string) ($row['delivery_date'] ?? '—')) ?></td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars((string) ($row['description'] ?? '')) ?>">
                    <?= htmlspecialchars((string) ($row['description'] ?? '')) ?: '<span style="color:var(--text-secondary);">—</span>' ?>
                </td>
                <td style="text-align:center;">
                    <?php $reprocessCount = (int) ($row['reprocess_count'] ?? 0); ?>
                    <?php if ($reprocessCount > 0): ?>
                        <span style="color:var(--danger);font-weight:700;"><?= $reprocessCount ?></span>
                    <?php else: ?>
                        <span style="color:var(--text-secondary);">0</span>
                    <?php endif; ?>
                </td>
                <?php if ($canManage): ?>
                <td>
                    <?php if ($isTerminal): ?>
                        <span class="req-note">Estado final</span>
                    <?php else: ?>
                        <form method="POST" action="/projects/<?= (int) ($project['id'] ?? 0) ?>/requirements/<?= (int) ($row['id'] ?? 0) ?>/status" style="display:flex;gap:5px;align-items:center;">
                            <select name="status" style="padding:5px 8px;border:1px solid var(--border);border-radius:6px;background:var(--surface);color:var(--text-primary);font-size:13px;">
                                <?php foreach ($nextStatuses as $option): ?>
                                    <option value="<?= htmlspecialchars($option) ?>"><?= htmlspecialchars($statusLabels[$option] ?? ucfirst($option)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="action-btn" type="submit" style="padding:5px 10px;font-size:12px;">Cambiar</button>
                        </form>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
                <?php if ($canDelete): ?>
                <td>
                    <form method="POST" action="/projects/<?= (int) ($project['id'] ?? 0) ?>/requirements/<?= (int) ($row['id'] ?? 0) ?>/delete" onsubmit="return confirm('¿Eliminar este requisito? Esta acción no se puede deshacer.')">
                        <button class="action-btn danger" type="submit" style="padding:4px 10px;font-size:12px;">Eliminar</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>

<?php if ($audit !== []): ?>
<section class="card" style="padding:16px;">
    <h4 style="margin:0 0 10px;">Auditoría de cambios</h4>
    <div style="overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Requisito</th>
                <th>Cambio de estado</th>
                <th>Usuario</th>
                <th>Notas</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($audit as $row): ?>
            <tr>
                <td style="white-space:nowrap;"><?= htmlspecialchars((string) ($row['changed_at'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['requirement_name'] ?? '')) ?></td>
                <td>
                    <?php
                        $from = (string) ($row['from_status'] ?? '');
                        $to = (string) ($row['to_status'] ?? '');
                        $fromLabel = $statusLabels[$from] ?? ucfirst($from);
                        $toLabel = $statusLabels[$to] ?? ucfirst($to);
                    ?>
                    <span class="status-badge <?= htmlspecialchars($statusBadgeClass[$from] ?? 'badge-neutral') ?>">
                        <?= htmlspecialchars($fromLabel) ?>
                    </span>
                    →
                    <span class="status-badge <?= htmlspecialchars($statusBadgeClass[$to] ?? 'badge-neutral') ?>">
                        <?= htmlspecialchars($toLabel) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars((string) ($row['changed_by_name'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['notes'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>
<?php endif; ?>
