<?php
$project = $project ?? [];
$requirements = $requirements ?? [];
$period = $requirementsPeriod ?? ['start_date' => date('Y-m-01'), 'end_date' => date('Y-m-t')];
$audit = $requirementsAudit ?? [];
$workflowStatuses = $requirementsStatuses ?? ['borrador', 'en_revision', 'aprobado', 'entregado', 'rechazado'];
$workflowTransitions = [
    'borrador' => ['en_revision'],
    'en_revision' => ['aprobado', 'rechazado'],
    'rechazado' => ['en_revision'],
    'aprobado' => ['entregado'],
    'entregado' => ['en_revision'],
];
$statusLabels = [
    'borrador' => 'Borrador',
    'en_revision' => 'En revisión',
    'aprobado' => 'Aprobado',
    'entregado' => 'Entregado',
    'rechazado' => 'Rechazado',
];
$statusBadges = [
    'borrador' => '⚪',
    'en_revision' => '🟡',
    'aprobado' => '🟢',
    'entregado' => '🔵',
    'rechazado' => '🔴',
];

$normalizeRequirementStatus = static function (array $row) use ($workflowStatuses): string {
    $rowStatus = strtolower(trim((string) ($row['status'] ?? 'borrador')));
    if ($rowStatus === 'definido') {
        $rowStatus = 'en_revision';
    }

    if (!in_array($rowStatus, $workflowStatuses, true)) {
        return 'borrador';
    }

    return $rowStatus;
};

$totalRequirements = count($requirements);
$approvedRequirements = 0;
$inReviewRequirements = 0;
$rejectedRequirements = 0;

foreach ($requirements as $requirementRow) {
    $normalizedStatus = $normalizeRequirementStatus((array) $requirementRow);
    if ($normalizedStatus === 'aprobado') {
        $approvedRequirements++;
        continue;
    }

    if ($normalizedStatus === 'rechazado') {
        $rejectedRequirements++;
        continue;
    }

    if (in_array($normalizedStatus, ['borrador', 'en_revision', 'entregado'], true)) {
        $inReviewRequirements++;
    }
}

$compliance = $totalRequirements > 0 ? ($approvedRequirements / $totalRequirements) * 100 : 0.0;
$targetMeta = max(0.0, min(100.0, (float) ($requirementsTargetMeta ?? 95)));
$saved = isset($_GET['saved']);
$updated = isset($_GET['updated']);
$deleted = isset($_GET['deleted']);
$error = trim((string) ($_GET['error'] ?? ''));
$activeTab = 'requisitos';
require __DIR__ . '/_tabs.php';
?>

<style>
    .requirements-kpi-grid { display:grid; grid-template-columns:repeat(4,minmax(140px,1fr)); gap:10px; margin-top:12px; }
    .requirements-kpi { border:1px solid var(--border); border-radius:12px; padding:10px; background:color-mix(in srgb, var(--surface) 92%, var(--background)); }
    .requirements-kpi span { display:block; font-size:11px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.04em; }
    .requirements-kpi strong { font-size:26px; color:var(--text-primary); }
    .requirements-progress { margin-top:10px; }
    .requirements-progress-track { width:100%; height:12px; border-radius:999px; background:color-mix(in srgb, var(--border) 45%, var(--background)); overflow:hidden; }
    .requirements-progress-fill { height:100%; background:linear-gradient(90deg, #16a34a, #22c55e); }
    .requirements-progress-label { display:flex; justify-content:space-between; margin-top:6px; font-size:12px; color:var(--text-secondary); }
    .requirements-alert { border-radius:10px; padding:10px 12px; margin-bottom:10px; font-weight:600; border:1px solid transparent; }
    .requirements-alert.ok { color:var(--success); border-color:color-mix(in srgb, var(--success) 35%, var(--border)); background:color-mix(in srgb, var(--success) 10%, var(--surface)); }
    .requirements-alert.error { color:var(--danger); border-color:color-mix(in srgb, var(--danger) 35%, var(--border)); background:color-mix(in srgb, var(--danger) 10%, var(--surface)); }
    .status-badge { display:inline-flex; align-items:center; gap:6px; padding:4px 8px; border-radius:999px; border:1px solid var(--border); font-size:12px; font-weight:700; white-space:nowrap; }
</style>

<section class="card" style="padding:16px;margin-bottom:16px;">
    <h3>Cumplimiento requisitos cliente</h3>
    <form method="GET" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
        <label>Inicio <input type="date" name="start_date" value="<?= htmlspecialchars((string) $period['start_date']) ?>"></label>
        <label>Fin <input type="date" name="end_date" value="<?= htmlspecialchars((string) $period['end_date']) ?>"></label>
        <button class="action-btn">Filtrar</button>
    </form>
    <div class="requirements-kpi-grid">
        <article class="requirements-kpi"><span>Total</span><strong><?= $totalRequirements ?></strong></article>
        <article class="requirements-kpi"><span>Aprobados</span><strong><?= $approvedRequirements ?></strong></article>
        <article class="requirements-kpi"><span>En revisión</span><strong><?= $inReviewRequirements ?></strong></article>
        <article class="requirements-kpi"><span>Rechazados</span><strong><?= $rejectedRequirements ?></strong></article>
    </div>
    <div class="requirements-progress">
        <div class="requirements-progress-track">
            <div class="requirements-progress-fill" style="width: <?= max(0, min(100, $compliance)) ?>%;"></div>
        </div>
        <div class="requirements-progress-label"><span>Cumplimiento actual</span><strong><?= number_format($compliance, 2) ?>%</strong></div>
    </div>
    <p><strong>Meta:</strong> <?= number_format($targetMeta, 0) ?>%</p>
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
    <form method="POST" action="/projects/<?= (int) ($project['id'] ?? 0) ?>/requirements" style="display:grid;grid-template-columns:repeat(4,minmax(120px,1fr));gap:10px;">
        <input name="name" placeholder="Nombre" required>
        <input name="version" placeholder="Versión" value="1.0" required>
        <input type="date" name="delivery_date">
        <input type="hidden" name="status" value="borrador">
        <div style="display:flex;align-items:center;"><span class="status-badge"><?= $statusBadges['borrador'] ?> <?= $statusLabels['borrador'] ?></span></div>
        <select name="approved_first_delivery"><option value="1">Aprobado 1ra entrega: Sí</option><option value="0">Aprobado 1ra entrega: No</option></select>
        <input name="description" style="grid-column: span 3" placeholder="Descripción">
        <button class="action-btn primary" type="submit">Guardar</button>
    </form>
</section>

<section class="card" style="padding:16px;margin-bottom:16px;">
    <h4>Listado de requisitos</h4>
    <table>
        <thead><tr><th>Requisito</th><th>Estado</th><th>Entrega</th><th>Descripción</th><th>Reprocesos</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($requirements as $row): ?>
            <?php
                $rowStatus = $normalizeRequirementStatus((array) $row);
                $nextStatuses = $workflowTransitions[$rowStatus] ?? [];
                $statusOptions = array_values(array_unique(array_merge([$rowStatus], $nextStatuses)));
            ?>
            <tr>
                <td><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></td>
                <td><span class="status-badge"><?= $statusBadges[$rowStatus] ?? '⚪' ?> <?= htmlspecialchars($statusLabels[$rowStatus] ?? ucfirst(str_replace('_', ' ', $rowStatus))) ?></span></td>
                <td><?= htmlspecialchars((string) ($row['delivery_date'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['description'] ?? '')) ?></td>
                <td><?= (int) ($row['reprocess_count'] ?? 0) ?></td>
                <td>
                    <form method="POST" action="/projects/<?= (int) ($project['id'] ?? 0) ?>/requirements/<?= (int) ($row['id'] ?? 0) ?>/status" style="display:inline-flex;gap:6px;">
                        <select name="status">
                            <?php foreach ($statusOptions as $option): ?>
                                <option value="<?= htmlspecialchars($option) ?>" <?= $option === $rowStatus ? 'selected' : '' ?>><?= htmlspecialchars($statusLabels[$option] ?? ucfirst(str_replace('_', ' ', $option))) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="action-btn">Actualizar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($requirements === []): ?>
            <tr><td colspan="6">Sin requisitos registrados.</td></tr>
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
            <tr>
                <td><?= htmlspecialchars((string) ($row['changed_at'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['requirement_name'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['from_status'] ?? '')) ?> → <?= htmlspecialchars((string) ($row['to_status'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['changed_by_name'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['notes'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
