<?php
$project = $project ?? [];
$requirements = $requirements ?? [];
$indicator = $requirementsIndicator ?? ['applicable' => false, 'value' => null, 'status' => 'no_aplica'];
$period = $requirementsPeriod ?? ['start_date' => date('Y-m-01'), 'end_date' => date('Y-m-t')];
$audit = $requirementsAudit ?? [];
$statusMap = ['verde' => '🟢', 'amarillo' => '🟡', 'rojo' => '🔴', 'no_aplica' => '⚪'];
$workflowStatuses = $requirementsStatuses ?? ['borrador', 'definido', 'en_revision', 'aprobado', 'rechazado', 'entregado'];
$workflowTransitions = [
    'borrador' => ['definido'],
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
$statusBadges = [
    'borrador' => '⚪',
    'definido' => '🟣',
    'en_revision' => '🟡',
    'aprobado' => '🟢',
    'rechazado' => '🔴',
    'entregado' => '🔵',
];
$compliance = ($indicator['applicable'] ?? false) ? (float) ($indicator['value'] ?? 0) : 0.0;
$saved = isset($_GET['saved']);
$updated = isset($_GET['updated']);
$deleted = isset($_GET['deleted']);
$error = trim((string) ($_GET['error'] ?? ''));
$activeTab = 'requisitos';
require __DIR__ . '/_tabs.php';
?>

<style>
    .requirements-kpi-grid { display:grid; grid-template-columns:repeat(5,minmax(140px,1fr)); gap:10px; margin-top:12px; }
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
    <h3>Indicador: Cumplimiento de requisitos del cliente</h3>
    <form method="GET" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
        <label>Inicio <input type="date" name="start_date" value="<?= htmlspecialchars((string) $period['start_date']) ?>"></label>
        <label>Fin <input type="date" name="end_date" value="<?= htmlspecialchars((string) $period['end_date']) ?>"></label>
        <button class="action-btn">Filtrar</button>
    </form>
    <p><strong><?= $statusMap[$indicator['status'] ?? 'no_aplica'] ?? '⚪' ?>
        <?= ($indicator['applicable'] ?? false) ? number_format((float) ($indicator['value'] ?? 0), 2) . '%' : 'No aplica' ?></strong></p>
    <div class="requirements-kpi-grid">
        <article class="requirements-kpi"><span>Requisitos totales</span><strong><?= (int) ($indicator['total_requirements'] ?? 0) ?></strong></article>
        <article class="requirements-kpi"><span>Aprobados</span><strong><?= (int) ($indicator['approved_requirements'] ?? 0) ?></strong></article>
        <article class="requirements-kpi"><span>En revisión</span><strong><?= (int) ($indicator['in_review_requirements'] ?? 0) ?></strong></article>
        <article class="requirements-kpi"><span>Rechazados</span><strong><?= (int) ($indicator['rejected_requirements'] ?? 0) ?></strong></article>
        <article class="requirements-kpi"><span>Pendientes</span><strong><?= (int) ($indicator['pending_requirements'] ?? 0) ?></strong></article>
    </div>
    <div class="requirements-progress">
        <div class="requirements-progress-track">
            <div class="requirements-progress-fill" style="width: <?= max(0, min(100, $compliance)) ?>%;"></div>
        </div>
        <div class="requirements-progress-label"><span>Cumplimiento real</span><strong><?= number_format($compliance, 2) ?>%</strong></div>
    </div>
    <p>Promedio reprocesos: <?= number_format((float) ($indicator['avg_reprocess_per_requirement'] ?? 0), 2) ?> · Días promedio aprobación: <?= number_format((float) ($indicator['avg_days_to_approval'] ?? 0), 1) ?> · % &gt;2 reprocesos: <?= number_format((float) ($indicator['percent_over_two_reprocess'] ?? 0), 2) ?>%</p>
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
        <select name="status">
            <?php foreach ($workflowStatuses as $status): ?>
                <option value="<?= htmlspecialchars($status) ?>" <?= $status === 'borrador' ? 'selected' : '' ?>><?= htmlspecialchars($statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status))) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="approved_first_delivery"><option value="1">Aprobado 1ra entrega: Sí</option><option value="0">Aprobado 1ra entrega: No</option></select>
        <input name="description" style="grid-column: span 3" placeholder="Descripción">
        <button class="action-btn primary" type="submit">Guardar</button>
    </form>
</section>

<section class="card" style="padding:16px;margin-bottom:16px;">
    <h4>Listado de requisitos</h4>
    <table>
        <thead><tr><th>Nombre</th><th>Versión</th><th>Entrega</th><th>Estado</th><th>Descripción</th><th>Reprocesos</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($requirements as $row): ?>
            <?php
                $rowStatus = strtolower(trim((string) ($row['status'] ?? 'borrador')));
                $nextStatuses = $workflowTransitions[$rowStatus] ?? [];
                $statusOptions = array_values(array_unique(array_merge([$rowStatus], $nextStatuses)));
            ?>
            <tr>
                <td><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['version'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['delivery_date'] ?? '')) ?></td>
                <td><span class="status-badge"><?= $statusBadges[$rowStatus] ?? '⚪' ?> <?= htmlspecialchars($statusLabels[$rowStatus] ?? ucfirst(str_replace('_', ' ', $rowStatus))) ?></span></td>
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
