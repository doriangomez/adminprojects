<?php
$project = $project ?? [];
$requirements = $requirements ?? [];
$indicator = $requirementsIndicator ?? ['applicable' => false, 'value' => null, 'status' => 'no_aplica'];
$period = $requirementsPeriod ?? ['start_date' => date('Y-m-01'), 'end_date' => date('Y-m-t')];
$target = (int) ($requirementsTarget ?? 95);
$audit = $requirementsAudit ?? [];
$summary = $requirementsSummary ?? ['total' => 0, 'aprobados' => 0, 'en_revision' => 0, 'rechazados' => 0, 'cumplimiento' => null];
$activeTab = 'requisitos';
require __DIR__ . '/_tabs.php';

$allStatuses = [
    'borrador'    => ['label' => 'Borrador',    'badge' => 'badge-gray'],
    'definido'    => ['label' => 'Definido',    'badge' => 'badge-blue'],
    'en_revision' => ['label' => 'En revisión', 'badge' => 'badge-yellow'],
    'aprobado'    => ['label' => 'Aprobado',    'badge' => 'badge-green'],
    'rechazado'   => ['label' => 'Rechazado',   'badge' => 'badge-red'],
    'entregado'   => ['label' => 'Entregado',   'badge' => 'badge-teal'],
];

function reqBadge(string $status, array $map): string {
    $info = $map[$status] ?? ['label' => htmlspecialchars($status), 'badge' => 'badge-gray'];
    return '<span class="req-badge ' . $info['badge'] . '">' . $info['label'] . '</span>';
}

$cumplimiento = $summary['cumplimiento'];
$cumplimientoDisplay = $cumplimiento !== null ? number_format($cumplimiento, 1) . '%' : '—';
$barWidth = $cumplimiento !== null ? min(100, (int) $cumplimiento) : 0;
?>

<style>
.req-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 12px;
    margin: 16px 0;
}
.req-kpi-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px 16px;
    text-align: center;
}
.req-kpi-card .kpi-value {
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1;
    color: #1e293b;
}
.req-kpi-card .kpi-label {
    font-size: 0.75rem;
    color: #64748b;
    margin-top: 4px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.req-kpi-card.kpi-green .kpi-value { color: #16a34a; }
.req-kpi-card.kpi-yellow .kpi-value { color: #ca8a04; }
.req-kpi-card.kpi-red .kpi-value { color: #dc2626; }
.req-progress-bar {
    height: 10px;
    background: #e2e8f0;
    border-radius: 9999px;
    overflow: hidden;
    margin: 10px 0 4px;
}
.req-progress-fill {
    height: 100%;
    border-radius: 9999px;
    transition: width 0.4s;
    background: linear-gradient(90deg, #16a34a, #4ade80);
}
.req-progress-fill.low { background: linear-gradient(90deg, #dc2626, #f87171); }
.req-progress-fill.mid { background: linear-gradient(90deg, #ca8a04, #fbbf24); }
.req-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 9999px;
    font-size: 0.78rem;
    font-weight: 600;
    white-space: nowrap;
}
.badge-gray   { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
.badge-blue   { background: #dbeafe; color: #1d4ed8; border: 1px solid #bfdbfe; }
.badge-yellow { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.badge-green  { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.badge-red    { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.badge-teal   { background: #ccfbf1; color: #0f766e; border: 1px solid #99f6e4; }
.req-table th, .req-table td {
    padding: 8px 12px;
    text-align: left;
    vertical-align: middle;
}
.req-table th {
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
}
.req-table td { border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
.req-table tr:last-child td { border-bottom: none; }
.req-description { color: #64748b; font-size: 0.82rem; }
.req-reproceso-badge {
    display: inline-block;
    min-width: 22px;
    text-align: center;
    padding: 1px 7px;
    border-radius: 9999px;
    font-size: 0.78rem;
    font-weight: 700;
}
.reproceso-0 { background: #f1f5f9; color: #94a3b8; }
.reproceso-1 { background: #fef3c7; color: #92400e; }
.reproceso-high { background: #fee2e2; color: #991b1b; }
.workflow-hint {
    font-size: 0.78rem;
    color: #94a3b8;
    margin-top: 6px;
}
.saved-alert {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
    padding: 8px 14px;
    border-radius: 6px;
    margin-bottom: 12px;
    font-size: 0.875rem;
}
</style>

<?php if (isset($_GET['saved'])): ?>
<div class="saved-alert">✓ Requisito guardado correctamente.</div>
<?php elseif (isset($_GET['updated'])): ?>
<div class="saved-alert">✓ Estado actualizado correctamente.</div>
<?php elseif (isset($_GET['deleted'])): ?>
<div class="saved-alert" style="background:#fee2e2;color:#991b1b;border-color:#fecaca;">Requisito eliminado.</div>
<?php endif; ?>

<!-- Indicadores superiores -->
<section class="card" style="padding:20px;margin-bottom:16px;">
    <h3 style="margin:0 0 16px;font-size:1rem;color:#374151;">Cumplimiento de requisitos</h3>

    <div class="req-kpi-grid">
        <div class="req-kpi-card">
            <div class="kpi-value"><?= $summary['total'] ?></div>
            <div class="kpi-label">Total</div>
        </div>
        <div class="req-kpi-card kpi-green">
            <div class="kpi-value"><?= $summary['aprobados'] ?></div>
            <div class="kpi-label">Aprobados</div>
        </div>
        <div class="req-kpi-card kpi-yellow">
            <div class="kpi-value"><?= $summary['en_revision'] ?></div>
            <div class="kpi-label">En revisión</div>
        </div>
        <div class="req-kpi-card kpi-red">
            <div class="kpi-value"><?= $summary['rechazados'] ?></div>
            <div class="kpi-label">Rechazados</div>
        </div>
        <div class="req-kpi-card <?= $cumplimiento !== null ? ($cumplimiento >= 85 ? 'kpi-green' : ($cumplimiento >= 60 ? 'kpi-yellow' : 'kpi-red')) : '' ?>">
            <div class="kpi-value"><?= $cumplimientoDisplay ?></div>
            <div class="kpi-label">Cumplimiento</div>
        </div>
    </div>

    <?php if ($cumplimiento !== null): ?>
    <?php $fillClass = $cumplimiento >= 85 ? '' : ($cumplimiento >= 60 ? 'mid' : 'low'); ?>
    <div class="req-progress-bar">
        <div class="req-progress-fill <?= $fillClass ?>" style="width:<?= $barWidth ?>%"></div>
    </div>
    <div style="font-size:0.78rem;color:#64748b;"><?= $barWidth ?>% de <?= $summary['total'] ?> requisitos aprobados</div>
    <?php endif; ?>

    <details style="margin-top:16px;">
        <summary style="cursor:pointer;font-size:0.82rem;color:#64748b;">Ver indicador por período de entrega</summary>
        <form method="GET" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-top:10px;">
            <label style="font-size:0.85rem;">Inicio <input type="date" name="start_date" value="<?= htmlspecialchars((string) $period['start_date']) ?>"></label>
            <label style="font-size:0.85rem;">Fin <input type="date" name="end_date" value="<?= htmlspecialchars((string) $period['end_date']) ?>"></label>
            <button class="action-btn">Filtrar</button>
        </form>
        <?php
        $kpiEmojis = ['verde' => '🟢', 'amarillo' => '🟡', 'rojo' => '🔴', 'no_aplica' => '⚪'];
        $kpiEmoji = $kpiEmojis[$indicator['status'] ?? 'no_aplica'] ?? '⚪';
        ?>
        <p style="margin:10px 0 4px;font-size:0.88rem;">
            <strong><?= $kpiEmoji ?>
            <?= ($indicator['applicable'] ?? false) ? number_format((float) ($indicator['value'] ?? 0), 2) . '%' : 'No aplica' ?></strong>
            &nbsp;·&nbsp; Meta <?= $target ?>%
        </p>
        <p style="font-size:0.82rem;color:#64748b;">
            Total en período: <?= (int) ($indicator['total_requirements'] ?? 0) ?>
            &nbsp;·&nbsp; Aprobados sin reproceso: <?= (int) ($indicator['approved_without_reprocess'] ?? 0) ?>
            &nbsp;·&nbsp; Con reproceso: <?= (int) ($indicator['with_reprocess'] ?? 0) ?><br>
            Prom. reprocesos: <?= number_format((float) ($indicator['avg_reprocess_per_requirement'] ?? 0), 2) ?>
            &nbsp;·&nbsp; Días prom. aprobación: <?= number_format((float) ($indicator['avg_days_to_approval'] ?? 0), 1) ?>
            &nbsp;·&nbsp; % &gt;2 reprocesos: <?= number_format((float) ($indicator['percent_over_two_reprocess'] ?? 0), 2) ?>%
        </p>
    </details>
</section>

<!-- Registrar requisito -->
<section class="card" style="padding:20px;margin-bottom:16px;">
    <h4 style="margin:0 0 14px;font-size:0.95rem;">Registrar requisito</h4>
    <form method="POST" action="/projects/<?= (int) ($project['id'] ?? 0) ?>/requirements"
          style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;">
        <input name="name" placeholder="Nombre del requisito" required style="grid-column:span 2;">
        <input name="version" placeholder="Versión" value="1.0" required>
        <input type="date" name="delivery_date" placeholder="Fecha entrega">
        <select name="status">
            <?php foreach ($allStatuses as $val => $info): ?>
            <option value="<?= $val ?>"><?= $info['label'] ?></option>
            <?php endforeach; ?>
        </select>
        <input name="description" placeholder="Descripción" style="grid-column:span 2;">
        <button class="action-btn primary" type="submit" style="align-self:end;">Guardar</button>
    </form>
    <p class="workflow-hint">Flujo: Borrador → Definido → En revisión → Aprobado → Entregado</p>
</section>

<!-- Listado de requisitos -->
<section class="card" style="padding:20px;margin-bottom:16px;">
    <h4 style="margin:0 0 14px;font-size:0.95rem;">Listado de requisitos</h4>
    <?php if (empty($requirements)): ?>
    <p style="color:#94a3b8;font-size:0.875rem;">No hay requisitos registrados para este proyecto.</p>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="req-table" style="width:100%;border-collapse:collapse;">
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
        <?php foreach ($requirements as $row): ?>
            <?php
            $reprocesos = (int) ($row['reprocess_count'] ?? 0);
            $reprClass = $reprocesos === 0 ? 'reproceso-0' : ($reprocesos === 1 ? 'reproceso-1' : 'reproceso-high');
            ?>
            <tr>
                <td><strong><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></strong></td>
                <td><?= htmlspecialchars((string) ($row['version'] ?? '')) ?></td>
                <td><?= $row['delivery_date'] ? date('d M', strtotime((string) $row['delivery_date'])) : '—' ?></td>
                <td><?= reqBadge((string) ($row['status'] ?? 'borrador'), $allStatuses) ?></td>
                <td class="req-description"><?= htmlspecialchars((string) ($row['description'] ?? '')) ?: '<span style="color:#cbd5e1;">—</span>' ?></td>
                <td><span class="req-reproceso-badge <?= $reprClass ?>"><?= $reprocesos ?></span></td>
                <td>
                    <form method="POST"
                          action="/projects/<?= (int) ($project['id'] ?? 0) ?>/requirements/<?= (int) ($row['id'] ?? 0) ?>/status"
                          style="display:inline-flex;gap:6px;align-items:center;">
                        <select name="status" style="font-size:0.82rem;padding:3px 6px;">
                            <?php foreach ($allStatuses as $val => $info): ?>
                            <option value="<?= $val ?>" <?= ($row['status'] ?? '') === $val ? 'selected' : '' ?>><?= $info['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="action-btn" type="submit" style="padding:4px 10px;font-size:0.8rem;">→</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>

<!-- Auditoría -->
<section class="card" style="padding:20px;">
    <h4 style="margin:0 0 14px;font-size:0.95rem;">Auditoría de cambios</h4>
    <?php if (empty($audit)): ?>
    <p style="color:#94a3b8;font-size:0.875rem;">Sin cambios registrados aún.</p>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="req-table" style="width:100%;border-collapse:collapse;">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Requisito</th>
                <th>Cambio</th>
                <th>Usuario</th>
                <th>Notas</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($audit as $row): ?>
            <tr>
                <td style="white-space:nowrap;font-size:0.82rem;color:#64748b;"><?= htmlspecialchars((string) ($row['changed_at'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['requirement_name'] ?? '')) ?></td>
                <td>
                    <?= reqBadge((string) ($row['from_status'] ?? ''), $allStatuses) ?>
                    <span style="color:#94a3b8;margin:0 4px;">→</span>
                    <?= reqBadge((string) ($row['to_status'] ?? ''), $allStatuses) ?>
                </td>
                <td><?= htmlspecialchars((string) ($row['changed_by_name'] ?? '')) ?></td>
                <td class="req-description"><?= htmlspecialchars((string) ($row['notes'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>
