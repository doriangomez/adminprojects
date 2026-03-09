<?php
$project = $project ?? [];
$requirements = $requirements ?? [];
$indicator = $requirementsIndicator ?? ['applicable' => false, 'value' => null, 'status' => 'no_aplica'];
$period = $requirementsPeriod ?? ['start_date' => date('Y-m-01'), 'end_date' => date('Y-m-t')];
$audit = $requirementsAudit ?? [];
$metaTarget = $requirementsTarget ?? 95;
$statusMap = ['verde' => '🟢', 'amarillo' => '🟡', 'rojo' => '🔴', 'no_aplica' => '⚪'];
$workflowStatuses = $requirementsStatuses ?? ['borrador', 'en_revision', 'aprobado', 'rechazado', 'entregado'];
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
    'rechazado' => 'Rechazado',
    'entregado' => 'Entregado',
];
$statusBadges = [
    'borrador' => '⚪',
    'en_revision' => '🟡',
    'aprobado' => '🟢',
    'rechazado' => '🔴',
    'entregado' => '🔵',
];

$totalReqs = (int) ($indicator['total_requirements'] ?? 0);
$approvedReqs = (int) ($indicator['approved_requirements'] ?? 0);
$inReviewReqs = (int) ($indicator['in_review_requirements'] ?? 0);
$rejectedReqs = (int) ($indicator['rejected_requirements'] ?? 0);
$compliance = $totalReqs > 0 ? round(($approvedReqs / $totalReqs) * 100, 1) : 0.0;

$saved = isset($_GET['saved']);
$updated = isset($_GET['updated']);
$deleted = isset($_GET['deleted']);
$error = trim((string) ($_GET['error'] ?? ''));
$activeTab = 'requisitos';
require __DIR__ . '/_tabs.php';
?>

<style>
    .req-indicator-card { border-radius:14px; padding:20px 24px; margin-bottom:18px; background:color-mix(in srgb, var(--surface) 94%, var(--background)); border:1px solid var(--border); }
    .req-indicator-header { font-size:15px; font-weight:700; color:var(--text-primary); margin-bottom:14px; display:flex; align-items:center; gap:8px; }
    .req-progress { margin-bottom:14px; }
    .req-progress-track { width:100%; height:14px; border-radius:999px; background:color-mix(in srgb, var(--border) 40%, var(--background)); overflow:hidden; position:relative; }
    .req-progress-fill { height:100%; border-radius:999px; transition:width .4s ease; }
    .req-progress-fill.green { background:linear-gradient(90deg, #16a34a, #22c55e); }
    .req-progress-fill.yellow { background:linear-gradient(90deg, #d97706, #f59e0b); }
    .req-progress-fill.red { background:linear-gradient(90deg, #dc2626, #ef4444); }
    .req-progress-meta { position:absolute; top:0; height:100%; width:2px; background:var(--text-primary); opacity:.45; }
    .req-progress-labels { display:flex; justify-content:space-between; align-items:center; margin-top:6px; font-size:13px; color:var(--text-secondary); }
    .req-progress-labels strong { color:var(--text-primary); font-size:20px; }
    .req-kpi-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); gap:10px; margin-top:10px; }
    .req-kpi { border:1px solid var(--border); border-radius:10px; padding:10px 12px; background:color-mix(in srgb, var(--surface) 90%, var(--background)); text-align:center; }
    .req-kpi-label { display:block; font-size:11px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.04em; margin-bottom:2px; }
    .req-kpi-value { font-size:24px; font-weight:700; color:var(--text-primary); }
    .req-kpi-value.approved { color:var(--success, #22c55e); }
    .req-kpi-value.review { color:var(--warning, #f59e0b); }
    .req-kpi-value.rejected { color:var(--danger, #ef4444); }
    .status-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:600; white-space:nowrap; line-height:1.3; }
    .status-badge.borrador { background:color-mix(in srgb, var(--neutral, #94a3b8) 15%, transparent); color:var(--text-secondary); border:1px solid color-mix(in srgb, var(--neutral, #94a3b8) 30%, transparent); }
    .status-badge.en_revision { background:color-mix(in srgb, var(--warning, #f59e0b) 15%, transparent); color:var(--warning, #f59e0b); border:1px solid color-mix(in srgb, var(--warning, #f59e0b) 30%, transparent); }
    .status-badge.aprobado { background:color-mix(in srgb, var(--success, #22c55e) 15%, transparent); color:var(--success, #22c55e); border:1px solid color-mix(in srgb, var(--success, #22c55e) 30%, transparent); }
    .status-badge.rechazado { background:color-mix(in srgb, var(--danger, #ef4444) 15%, transparent); color:var(--danger, #ef4444); border:1px solid color-mix(in srgb, var(--danger, #ef4444) 30%, transparent); }
    .status-badge.entregado { background:color-mix(in srgb, var(--info, #38bdf8) 15%, transparent); color:var(--info, #38bdf8); border:1px solid color-mix(in srgb, var(--info, #38bdf8) 30%, transparent); }
    .requirements-alert { border-radius:10px; padding:10px 12px; margin-bottom:10px; font-weight:600; border:1px solid transparent; }
    .requirements-alert.ok { color:var(--success); border-color:color-mix(in srgb, var(--success) 35%, var(--border)); background:color-mix(in srgb, var(--success) 10%, var(--surface)); }
    .requirements-alert.error { color:var(--danger); border-color:color-mix(in srgb, var(--danger) 35%, var(--border)); background:color-mix(in srgb, var(--danger) 10%, var(--surface)); }
</style>

<section class="card req-indicator-card">
    <div class="req-indicator-header">
        <?= $statusMap[$indicator['status'] ?? 'no_aplica'] ?? '⚪' ?>
        Cumplimiento requisitos del cliente
    </div>

    <form method="GET" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:14px;">
        <label style="font-size:12px;">Inicio <input type="date" name="start_date" value="<?= htmlspecialchars((string) $period['start_date']) ?>"></label>
        <label style="font-size:12px;">Fin <input type="date" name="end_date" value="<?= htmlspecialchars((string) $period['end_date']) ?>"></label>
        <button class="action-btn">Filtrar</button>
    </form>

    <div class="req-progress">
        <div class="req-progress-track">
            <?php
                $fillClass = 'green';
                if ($compliance < $metaTarget && $compliance >= ($metaTarget * 0.7)) $fillClass = 'yellow';
                if ($compliance < ($metaTarget * 0.7)) $fillClass = 'red';
            ?>
            <div class="req-progress-fill <?= $fillClass ?>" style="width:<?= max(0, min(100, $compliance)) ?>%;"></div>
            <?php if ($metaTarget > 0 && $metaTarget <= 100): ?>
                <div class="req-progress-meta" style="left:<?= $metaTarget ?>%;" title="Meta: <?= $metaTarget ?>%"></div>
            <?php endif; ?>
        </div>
        <div class="req-progress-labels">
            <span>Cumplimiento actual</span>
            <strong><?= number_format($compliance, 1) ?>%</strong>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-secondary);margin-top:2px;">
            <span>Meta: <strong style="color:var(--text-primary);"><?= $metaTarget ?>%</strong></span>
            <span><?= $compliance >= $metaTarget ? '✅ Meta alcanzada' : '⚠️ Por debajo de la meta' ?></span>
        </div>
    </div>

    <div class="req-kpi-grid">
        <article class="req-kpi"><span class="req-kpi-label">Totales</span><span class="req-kpi-value"><?= $totalReqs ?></span></article>
        <article class="req-kpi"><span class="req-kpi-label">Aprobados</span><span class="req-kpi-value approved"><?= $approvedReqs ?></span></article>
        <article class="req-kpi"><span class="req-kpi-label">En revisión</span><span class="req-kpi-value review"><?= $inReviewReqs ?></span></article>
        <article class="req-kpi"><span class="req-kpi-label">Rechazados</span><span class="req-kpi-value rejected"><?= $rejectedReqs ?></span></article>
        <article class="req-kpi"><span class="req-kpi-label">Cumplimiento</span><span class="req-kpi-value"><?= number_format($compliance, 1) ?>%</span></article>
    </div>
</section>

<section class="card" style="padding:16px;margin-bottom:16px;">
    <?php if ($saved): ?>
        <div class="requirements-alert ok">Requisito registrado correctamente.</div>
    <?php endif; ?>
    <?php if ($updated): ?>
        <div class="requirements-alert ok">Estado de requisito actualizado.</div>
    <?php endif; ?>
    <?php if ($deleted): ?>
        <div class="requirements-alert ok">Requisito eliminado correctamente.</div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="requirements-alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <h4>Registrar requisito</h4>
    <form method="POST" action="/projects/<?= (int) ($project['id'] ?? 0) ?>/requirements" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:10px;">
        <input name="name" placeholder="Nombre del requisito" required>
        <input name="version" placeholder="Versión" value="1.0" required>
        <input type="date" name="delivery_date">
        <input name="description" style="grid-column: span 2" placeholder="Descripción">
        <input type="hidden" name="status" value="borrador">
        <button class="action-btn primary" type="submit">Guardar como borrador</button>
    </form>
</section>

<section class="card" style="padding:16px;margin-bottom:16px;">
    <h4>Listado de requisitos</h4>
    <table>
        <thead><tr><th>Requisito</th><th>Versión</th><th>Entrega</th><th>Estado</th><th>Descripción</th><th>Reprocesos</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($requirements as $row): ?>
            <?php
                $rowStatus = strtolower(trim((string) ($row['status'] ?? 'borrador')));
                if ($rowStatus === 'definido') $rowStatus = 'en_revision';
                if (!isset($workflowTransitions[$rowStatus])) $rowStatus = 'borrador';
                $nextStatuses = $workflowTransitions[$rowStatus] ?? [];
                $statusOptions = array_values(array_unique(array_merge([$rowStatus], $nextStatuses)));
            ?>
            <tr>
                <td><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['version'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['delivery_date'] ?? '')) ?></td>
                <td><span class="status-badge <?= htmlspecialchars($rowStatus) ?>"><?= $statusBadges[$rowStatus] ?? '⚪' ?> <?= htmlspecialchars($statusLabels[$rowStatus] ?? ucfirst(str_replace('_', ' ', $rowStatus))) ?></span></td>
                <td><?= htmlspecialchars((string) ($row['description'] ?? '')) ?></td>
                <td><?= (int) ($row['reprocess_count'] ?? 0) ?></td>
                <td>
                    <?php if (!empty($nextStatuses)): ?>
                    <form method="POST" action="/projects/<?= (int) ($project['id'] ?? 0) ?>/requirements/<?= (int) ($row['id'] ?? 0) ?>/status" style="display:inline-flex;gap:6px;">
                        <select name="status">
                            <?php foreach ($statusOptions as $option): ?>
                                <option value="<?= htmlspecialchars($option) ?>" <?= $option === $rowStatus ? 'selected' : '' ?>><?= htmlspecialchars($statusLabels[$option] ?? ucfirst(str_replace('_', ' ', $option))) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="action-btn">Actualizar</button>
                    </form>
                    <?php else: ?>
                        <span style="font-size:12px;color:var(--text-secondary);">—</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($requirements)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--text-secondary);padding:20px;">No hay requisitos registrados.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<section class="card" style="padding:16px;">
    <h4>Auditoría</h4>
    <table>
        <thead><tr><th>Fecha</th><th>Requisito</th><th>Cambio</th><th>Usuario</th><th>Notas</th></tr></thead>
        <tbody>
        <?php foreach ($audit as $row): ?>
            <?php
                $fromStatus = strtolower(trim((string) ($row['from_status'] ?? '')));
                $toStatus = strtolower(trim((string) ($row['to_status'] ?? '')));
            ?>
            <tr>
                <td><?= htmlspecialchars((string) ($row['changed_at'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['requirement_name'] ?? '')) ?></td>
                <td>
                    <span class="status-badge <?= htmlspecialchars($fromStatus) ?>" style="font-size:11px;padding:2px 6px;"><?= $statusBadges[$fromStatus] ?? '⚪' ?> <?= htmlspecialchars($statusLabels[$fromStatus] ?? $fromStatus) ?></span>
                    →
                    <span class="status-badge <?= htmlspecialchars($toStatus) ?>" style="font-size:11px;padding:2px 6px;"><?= $statusBadges[$toStatus] ?? '⚪' ?> <?= htmlspecialchars($statusLabels[$toStatus] ?? $toStatus) ?></span>
                </td>
                <td><?= htmlspecialchars((string) ($row['changed_by_name'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['notes'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($audit)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--text-secondary);padding:20px;">Sin registros de auditoría.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>
