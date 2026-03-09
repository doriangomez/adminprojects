<?php
$project = $project ?? [];
$requirements = $requirements ?? [];
$summary = $requirementsSummary ?? ['total' => 0, 'aprobados' => 0, 'en_revision' => 0, 'rechazados' => 0, 'pendientes' => 0, 'cumplimiento' => 0.0];
$indicator = $requirementsIndicator ?? ['applicable' => false, 'value' => null, 'status' => 'no_aplica'];
$period = $requirementsPeriod ?? ['start_date' => date('Y-m-01'), 'end_date' => date('Y-m-t')];
$audit = $requirementsAudit ?? [];
$statusBadges = [
    'borrador' => '<span class="badge badge-gray">Borrador</span>',
    'definido' => '<span class="badge badge-gray">Definido</span>',
    'en_revision' => '<span class="badge badge-yellow">🟡 En revisión</span>',
    'aprobado' => '<span class="badge badge-green">🟢 Aprobado</span>',
    'rechazado' => '<span class="badge badge-red">🔴 Rechazado</span>',
    'entregado' => '<span class="badge badge-blue">🔵 Entregado</span>',
];
$statusLabels = [
    'borrador' => 'Borrador',
    'definido' => 'Definido',
    'en_revision' => 'En revisión',
    'aprobado' => 'Aprobado',
    'rechazado' => 'Rechazado',
    'entregado' => 'Entregado',
];
$activeTab = 'requisitos';
require __DIR__ . '/_tabs.php';
?>

<section class="card" style="padding:16px;margin-bottom:16px;">
    <h3>Indicadores de cumplimiento</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:16px;">
        <div><strong>Requisitos totales:</strong> <?= (int) $summary['total'] ?></div>
        <div><strong>Aprobados:</strong> <?= (int) $summary['aprobados'] ?></div>
        <div><strong>En revisión:</strong> <?= (int) $summary['en_revision'] ?></div>
        <div><strong>Rechazados:</strong> <?= (int) $summary['rechazados'] ?></div>
        <div><strong>Pendientes:</strong> <?= (int) $summary['pendientes'] ?></div>
    </div>
    <div>
        <strong>Cumplimiento: <?= number_format((float) $summary['cumplimiento'], 1) ?>%</strong>
        <div style="background:#e0e0e0;border-radius:6px;height:12px;margin-top:6px;overflow:hidden;">
            <div style="background:linear-gradient(90deg,#4caf50,#8bc34a);height:100%;width:<?= min(100, (float) $summary['cumplimiento']) ?>%;transition:width 0.3s;"></div>
        </div>
    </div>
    <form method="GET" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-top:16px;">
        <label>Inicio <input type="date" name="start_date" value="<?= htmlspecialchars((string) $period['start_date']) ?>"></label>
        <label>Fin <input type="date" name="end_date" value="<?= htmlspecialchars((string) $period['end_date']) ?>"></label>
        <button class="action-btn">Filtrar período</button>
    </form>
</section>

<section class="card" style="padding:16px;margin-bottom:16px;">
    <h4>Registrar requisito</h4>
    <form method="POST" action="/projects/<?= (int) ($project['id'] ?? 0) ?>/requirements" style="display:grid;grid-template-columns:repeat(4,minmax(120px,1fr));gap:10px;">
        <input name="name" placeholder="Nombre" required>
        <input name="version" placeholder="Versión" value="1.0" required>
        <input type="date" name="delivery_date">
        <select name="status">
            <?php foreach ($statusLabels as $val => $label): ?>
            <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
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
            <?php $st = (string) ($row['status'] ?? 'borrador'); ?>
            <tr>
                <td><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['version'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['delivery_date'] ?? '')) ?></td>
                <td><?= $statusBadges[$st] ?? htmlspecialchars($st) ?></td>
                <td style="max-width:280px;"><?= htmlspecialchars((string) ($row['description'] ?? '')) ?></td>
                <td><?= (int) ($row['reprocess_count'] ?? 0) ?></td>
                <td>
                    <form method="POST" action="/projects/<?= (int) ($project['id'] ?? 0) ?>/requirements/<?= (int) ($row['id'] ?? 0) ?>/status" style="display:inline-flex;gap:6px;">
                        <select name="status">
                            <?php foreach ($statusLabels as $val => $label): ?>
                            <option value="<?= htmlspecialchars($val) ?>" <?= $st === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
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

<style>
.badge { display:inline-block; padding:4px 8px; border-radius:6px; font-size:0.9em; font-weight:500; }
.badge-gray { background:#e0e0e0; color:#424242; }
.badge-yellow { background:#fff9c4; color:#f57f17; }
.badge-green { background:#c8e6c9; color:#2e7d32; }
.badge-red { background:#ffcdd2; color:#c62828; }
.badge-blue { background:#bbdefb; color:#1565c0; }
</style>
