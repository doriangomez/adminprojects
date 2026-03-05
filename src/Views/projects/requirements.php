<?php
$project = $project ?? [];
$requirements = $requirements ?? [];
$indicator = $requirementsIndicator ?? ['applicable' => false, 'value' => null, 'status' => 'no_aplica'];
$period = $requirementsPeriod ?? ['start_date' => date('Y-m-01'), 'end_date' => date('Y-m-t')];
$target = (int) ($requirementsTarget ?? 95);
$audit = $requirementsAudit ?? [];
$statusMap = [
    'verde' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>',
    'amarillo' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#eab308" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>',
    'rojo' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>',
    'no_aplica' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>',
];
$activeTab = 'requisitos';
require __DIR__ . '/_tabs.php';
?>

<section class="card" style="padding:16px;margin-bottom:16px;">
    <h3>Indicador: Cumplimiento de requisitos del cliente</h3>
    <form method="GET" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
        <label>Inicio <input type="date" name="start_date" value="<?= htmlspecialchars((string) $period['start_date']) ?>"></label>
        <label>Fin <input type="date" name="end_date" value="<?= htmlspecialchars((string) $period['end_date']) ?>"></label>
        <button class="action-btn">Filtrar</button>
    </form>
    <p><strong><?= $statusMap[$indicator['status'] ?? 'no_aplica'] ?? $statusMap['no_aplica'] ?>
        <?= ($indicator['applicable'] ?? false) ? number_format((float) ($indicator['value'] ?? 0), 2) . '%' : 'No aplica' ?></strong>
        · Meta <?= $target ?>%</p>
    <p>Total: <?= (int) ($indicator['total_requirements'] ?? 0) ?> · Aprobados sin reproceso: <?= (int) ($indicator['approved_without_reprocess'] ?? 0) ?> · Con reproceso: <?= (int) ($indicator['with_reprocess'] ?? 0) ?></p>
    <p>Promedio reprocesos: <?= number_format((float) ($indicator['avg_reprocess_per_requirement'] ?? 0), 2) ?> · Días promedio aprobación: <?= number_format((float) ($indicator['avg_days_to_approval'] ?? 0), 1) ?> · % &gt;2 reprocesos: <?= number_format((float) ($indicator['percent_over_two_reprocess'] ?? 0), 2) ?>%</p>
</section>

<section class="card" style="padding:16px;margin-bottom:16px;">
    <h4>Registrar requisito</h4>
    <form method="POST" action="/projects/<?= (int) ($project['id'] ?? 0) ?>/requirements" style="display:grid;grid-template-columns:repeat(4,minmax(120px,1fr));gap:10px;">
        <input name="name" placeholder="Nombre" required>
        <input name="version" placeholder="Versión" value="1.0" required>
        <input type="date" name="delivery_date">
        <select name="status"><option value="borrador">Borrador</option><option value="entregado">Entregado</option><option value="aprobado">Aprobado</option><option value="rechazado">Rechazado</option></select>
        <select name="approved_first_delivery"><option value="1">Aprobado 1ra entrega: Sí</option><option value="0">Aprobado 1ra entrega: No</option></select>
        <input name="description" style="grid-column: span 3" placeholder="Descripción">
        <button class="action-btn primary" type="submit">Guardar</button>
    </form>
</section>

<section class="card" style="padding:16px;margin-bottom:16px;">
    <h4>Listado de requisitos</h4>
    <table>
        <thead><tr><th>Nombre</th><th>Versión</th><th>Entrega</th><th>Aprobación</th><th>Estado</th><th>1ra</th><th>Reprocesos</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($requirements as $row): ?>
            <tr>
                <td><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['version'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['delivery_date'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['approval_date'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['status'] ?? '')) ?></td>
                <td><?= ((int) ($row['approved_first_delivery'] ?? 0)) === 1 ? 'Sí' : 'No' ?></td>
                <td><?= (int) ($row['reprocess_count'] ?? 0) ?></td>
                <td>
                    <form method="POST" action="/projects/<?= (int) ($project['id'] ?? 0) ?>/requirements/<?= (int) ($row['id'] ?? 0) ?>/status" style="display:inline-flex;gap:6px;">
                        <select name="status"><option>borrador</option><option>entregado</option><option>aprobado</option><option>rechazado</option></select>
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
