<?php
$project = $project ?? [];
$requirements = $requirements ?? [];
$indicator = $requirementsIndicator ?? ['applicable' => false, 'value' => null, 'status' => 'no_aplica'];
$period = $requirementsPeriod ?? ['start_date' => date('Y-m-01'), 'end_date' => date('Y-m-t')];
$target = (int) ($requirementsTarget ?? 95);
$audit = $requirementsAudit ?? [];
$sc = $requirementsStatusCounts ?? ['total' => 0, 'borrador' => 0, 'definido' => 0, 'en_revision' => 0, 'aprobado' => 0, 'rechazado' => 0, 'entregado' => 0, 'cumplimiento' => 0.0];
$activeTab = 'requisitos';
require __DIR__ . '/_tabs.php';

$statusBadge = [
    'borrador'    => ['label' => 'Borrador',    'color' => '#94a3b8', 'bg' => 'rgba(148,163,184,.15)'],
    'definido'    => ['label' => 'Definido',    'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,.15)'],
    'en_revision' => ['label' => 'En revisión', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,.15)'],
    'aprobado'    => ['label' => 'Aprobado',    'color' => '#22c55e', 'bg' => 'rgba(34,197,94,.15)'],
    'rechazado'   => ['label' => 'Rechazado',   'color' => '#ef4444', 'bg' => 'rgba(239,68,68,.15)'],
    'entregado'   => ['label' => 'Entregado',   'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,.15)'],
];

$statusIcon = [
    'borrador'    => '⚪',
    'definido'    => '🟣',
    'en_revision' => '🟡',
    'aprobado'    => '🟢',
    'rechazado'   => '🔴',
    'entregado'   => '🔵',
];

$workflowTransitions = [
    'borrador'    => ['definido'],
    'definido'    => ['en_revision', 'borrador'],
    'en_revision' => ['aprobado', 'rechazado', 'definido'],
    'aprobado'    => ['entregado'],
    'rechazado'   => ['en_revision', 'borrador'],
    'entregado'   => [],
];

$cumplimiento = (float) ($sc['cumplimiento'] ?? 0);
$barColor = $cumplimiento >= 80 ? '#22c55e' : ($cumplimiento >= 50 ? '#f59e0b' : '#ef4444');
$errorMsg = $_GET['error'] ?? '';
?>

<?php if ($errorMsg): ?>
<div style="background:rgba(239,68,68,.12);border:1px solid #ef4444;color:#fca5a5;padding:10px 16px;border-radius:8px;margin-bottom:16px;font-size:.9rem;">
    <?= htmlspecialchars($errorMsg) ?>
</div>
<?php endif; ?>

<section class="card" style="padding:20px;margin-bottom:16px;">
    <h3 style="margin:0 0 16px 0;font-size:1.1rem;">Indicadores de requisitos</h3>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px;">
        <div style="background:rgba(148,163,184,.08);border:1px solid rgba(148,163,184,.2);border-radius:10px;padding:14px;text-align:center;">
            <div style="font-size:1.6rem;font-weight:700;"><?= (int) $sc['total'] ?></div>
            <div style="font-size:.78rem;color:#94a3b8;margin-top:2px;">Total requisitos</div>
        </div>
        <div style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);border-radius:10px;padding:14px;text-align:center;">
            <div style="font-size:1.6rem;font-weight:700;color:#22c55e;"><?= (int) $sc['aprobado'] ?></div>
            <div style="font-size:.78rem;color:#94a3b8;margin-top:2px;">Aprobados</div>
        </div>
        <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:10px;padding:14px;text-align:center;">
            <div style="font-size:1.6rem;font-weight:700;color:#f59e0b;"><?= (int) $sc['en_revision'] ?></div>
            <div style="font-size:.78rem;color:#94a3b8;margin-top:2px;">En revisión</div>
        </div>
        <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:10px;padding:14px;text-align:center;">
            <div style="font-size:1.6rem;font-weight:700;color:#ef4444;"><?= (int) $sc['rechazado'] ?></div>
            <div style="font-size:.78rem;color:#94a3b8;margin-top:2px;">Rechazados</div>
        </div>
        <div style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);border-radius:10px;padding:14px;text-align:center;">
            <div style="font-size:1.6rem;font-weight:700;color:#3b82f6;"><?= (int) $sc['entregado'] ?></div>
            <div style="font-size:.78rem;color:#94a3b8;margin-top:2px;">Entregados</div>
        </div>
    </div>

    <div style="margin-bottom:8px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
            <span style="font-weight:600;font-size:.92rem;">Cumplimiento</span>
            <span style="font-weight:700;font-size:1.05rem;color:<?= $barColor ?>;"><?= number_format($cumplimiento, 1) ?>%</span>
        </div>
        <div style="background:rgba(148,163,184,.15);border-radius:8px;height:14px;overflow:hidden;">
            <div style="width:<?= min($cumplimiento, 100) ?>%;height:100%;background:<?= $barColor ?>;border-radius:8px;transition:width .5s ease;"></div>
        </div>
        <div style="font-size:.75rem;color:#64748b;margin-top:4px;">
            Fórmula: aprobados (<?= (int) $sc['aprobado'] ?>) / total (<?= (int) $sc['total'] ?>) × 100
        </div>
    </div>
</section>

<section class="card" style="padding:20px;margin-bottom:16px;">
    <details>
        <summary style="cursor:pointer;font-weight:600;font-size:.95rem;">Indicador por período (detalle avanzado)</summary>
        <div style="margin-top:12px;">
            <form method="GET" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:12px;">
                <label style="font-size:.85rem;">Inicio <input type="date" name="start_date" value="<?= htmlspecialchars((string) $period['start_date']) ?>"></label>
                <label style="font-size:.85rem;">Fin <input type="date" name="end_date" value="<?= htmlspecialchars((string) $period['end_date']) ?>"></label>
                <button class="action-btn">Filtrar</button>
            </form>
            <?php $indStatusMap = ['verde' => '🟢', 'amarillo' => '🟡', 'rojo' => '🔴', 'no_aplica' => '⚪']; ?>
            <p><strong><?= $indStatusMap[$indicator['status'] ?? 'no_aplica'] ?? '⚪' ?>
                <?= ($indicator['applicable'] ?? false) ? number_format((float) ($indicator['value'] ?? 0), 2) . '%' : 'No aplica' ?></strong></p>
            <p style="font-size:.85rem;color:#94a3b8;">Total período: <?= (int) ($indicator['total_requirements'] ?? 0) ?> · Aprobados sin reproceso: <?= (int) ($indicator['approved_without_reprocess'] ?? 0) ?> · Con reproceso: <?= (int) ($indicator['with_reprocess'] ?? 0) ?></p>
            <p style="font-size:.85rem;color:#94a3b8;">Prom. reprocesos: <?= number_format((float) ($indicator['avg_reprocess_per_requirement'] ?? 0), 2) ?> · Días prom. aprobación: <?= number_format((float) ($indicator['avg_days_to_approval'] ?? 0), 1) ?> · % &gt;2 reprocesos: <?= number_format((float) ($indicator['percent_over_two_reprocess'] ?? 0), 2) ?>%</p>
        </div>
    </details>
</section>

<section class="card" style="padding:20px;margin-bottom:16px;">
    <h4 style="margin:0 0 12px 0;">Registrar requisito</h4>
    <form method="POST" action="/projects/<?= (int) ($project['id'] ?? 0) ?>/requirements" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;">
        <input name="name" placeholder="Nombre del requisito" required>
        <input name="version" placeholder="Versión" value="1.0" required>
        <input type="date" name="delivery_date">
        <select name="status"><option value="borrador">Borrador</option><option value="definido">Definido</option></select>
        <select name="approved_first_delivery"><option value="1">Aprobado 1ra entrega: Sí</option><option value="0">Aprobado 1ra entrega: No</option></select>
        <textarea name="description" style="grid-column:1/-1;min-height:50px;resize:vertical;" placeholder="Descripción del requisito"></textarea>
        <div style="grid-column:1/-1;text-align:right;">
            <button class="action-btn primary" type="submit">Guardar requisito</button>
        </div>
    </form>
</section>

<section class="card" style="padding:20px;margin-bottom:16px;">
    <h4 style="margin:0 0 12px 0;">Listado de requisitos</h4>
    <div style="overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Versión</th>
                <th>Entrega</th>
                <th>Estado</th>
                <th>Descripción</th>
                <th>Reprocesos</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($requirements)): ?>
            <tr><td colspan="7" style="text-align:center;color:#64748b;padding:24px;">No hay requisitos registrados.</td></tr>
        <?php endif; ?>
        <?php foreach ($requirements as $row): ?>
            <?php
                $rowStatus = (string) ($row['status'] ?? 'borrador');
                $badge = $statusBadge[$rowStatus] ?? $statusBadge['borrador'];
                $icon = $statusIcon[$rowStatus] ?? '⚪';
                $transitions = $workflowTransitions[$rowStatus] ?? [];
            ?>
            <tr>
                <td style="font-weight:500;"><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['version'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['delivery_date'] ?? '—')) ?></td>
                <td>
                    <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.82rem;font-weight:600;background:<?= $badge['bg'] ?>;color:<?= $badge['color'] ?>;white-space:nowrap;">
                        <?= $icon ?> <?= $badge['label'] ?>
                    </span>
                </td>
                <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars((string) ($row['description'] ?? '')) ?>">
                    <?= htmlspecialchars((string) ($row['description'] ?? '—')) ?>
                </td>
                <td style="text-align:center;"><?= (int) ($row['reprocess_count'] ?? 0) ?></td>
                <td>
                    <?php if (!empty($transitions)): ?>
                    <form method="POST" action="/projects/<?= (int) ($project['id'] ?? 0) ?>/requirements/<?= (int) ($row['id'] ?? 0) ?>/status" style="display:inline-flex;gap:6px;align-items:center;">
                        <select name="status" style="font-size:.82rem;padding:3px 6px;">
                            <?php foreach ($transitions as $t): ?>
                                <?php $tb = $statusBadge[$t] ?? $statusBadge['borrador']; ?>
                                <option value="<?= $t ?>"><?= $statusIcon[$t] ?? '' ?> <?= $tb['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="action-btn" style="font-size:.8rem;padding:4px 10px;">Cambiar</button>
                    </form>
                    <?php else: ?>
                        <span style="font-size:.8rem;color:#64748b;">—</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="card" style="padding:20px;margin-bottom:16px;">
    <details>
        <summary style="cursor:pointer;font-weight:600;font-size:.95rem;">Flujo de estados</summary>
        <div style="margin-top:12px;display:flex;flex-wrap:wrap;align-items:center;gap:8px;font-size:.85rem;">
            <?php
            $flowStates = ['borrador', 'definido', 'en_revision', 'aprobado', 'entregado'];
            foreach ($flowStates as $i => $fs):
                $fb = $statusBadge[$fs];
            ?>
                <span style="display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:20px;background:<?= $fb['bg'] ?>;color:<?= $fb['color'] ?>;font-weight:600;">
                    <?= $statusIcon[$fs] ?> <?= $fb['label'] ?>
                </span>
                <?php if ($i < count($flowStates) - 1): ?>
                    <span style="color:#64748b;">→</span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:8px;font-size:.8rem;color:#64748b;">
            🔴 Rechazado puede volver a 🟡 En revisión (se incrementa reproceso) o a ⚪ Borrador
        </div>
    </details>
</section>

<section class="card" style="padding:20px;">
    <h4 style="margin:0 0 12px 0;">Auditoría</h4>
    <div style="overflow-x:auto;">
    <table>
        <thead><tr><th>Fecha</th><th>Requisito</th><th>Cambio</th><th>Usuario</th><th>Notas</th></tr></thead>
        <tbody>
        <?php if (empty($audit)): ?>
            <tr><td colspan="5" style="text-align:center;color:#64748b;padding:16px;">Sin registros de auditoría.</td></tr>
        <?php endif; ?>
        <?php foreach ($audit as $row): ?>
            <?php
                $fromBadge = $statusBadge[(string) ($row['from_status'] ?? '')] ?? null;
                $toBadge = $statusBadge[(string) ($row['to_status'] ?? '')] ?? null;
            ?>
            <tr>
                <td style="white-space:nowrap;"><?= htmlspecialchars((string) ($row['changed_at'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['requirement_name'] ?? '')) ?></td>
                <td>
                    <?php if ($fromBadge): ?>
                        <span style="padding:2px 8px;border-radius:12px;font-size:.78rem;background:<?= $fromBadge['bg'] ?>;color:<?= $fromBadge['color'] ?>;"><?= $fromBadge['label'] ?></span>
                    <?php else: ?>
                        <?= htmlspecialchars((string) ($row['from_status'] ?? '—')) ?>
                    <?php endif; ?>
                    →
                    <?php if ($toBadge): ?>
                        <span style="padding:2px 8px;border-radius:12px;font-size:.78rem;background:<?= $toBadge['bg'] ?>;color:<?= $toBadge['color'] ?>;"><?= $toBadge['label'] ?></span>
                    <?php else: ?>
                        <?= htmlspecialchars((string) ($row['to_status'] ?? '')) ?>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string) ($row['changed_by_name'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['notes'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>
