<?php
$basePath = $basePath ?? '';
$rows = is_array($rows ?? null) ? $rows : [];
$kpis = is_array($kpis ?? null) ? $kpis : [];
$projectsForTimesheet = is_array($projectsForTimesheet ?? null) ? $projectsForTimesheet : [];
$pendingApprovals = is_array($pendingApprovals ?? null) ? $pendingApprovals : [];
$canApprove = !empty($canApprove);
$canReport = !empty($canReport);
$hasReportableProjects = !empty($projectsForTimesheet);
$weekStart = $weekStart ?? new DateTimeImmutable('monday this week');
$weekEnd = $weekEnd ?? $weekStart->modify('+6 days');
$weekValue = $weekValue ?? $weekStart->format('o-\\WW');

$statusMeta = [
    'draft' => ['label' => 'Borrador', 'icon' => '📝', 'class' => 'status-muted'],
    'pending' => ['label' => 'Enviado', 'icon' => '⏳', 'class' => 'status-warning'],
    'approved' => ['label' => 'Aprobado', 'icon' => '✅', 'class' => 'status-success'],
    'rejected' => ['label' => 'Rechazado', 'icon' => '❌', 'class' => 'status-danger'],
];

$projectsForTimesheet = array_values(array_filter($projectsForTimesheet, static function (array $project): bool {
    return (int) ($project['project_id'] ?? 0) > 0;
}));
$projectsForTimesheet = array_values(array_reduce($projectsForTimesheet, static function (array $carry, array $project): array {
    $projectId = (int) ($project['project_id'] ?? 0);
    if ($projectId <= 0 || isset($carry[$projectId])) {
        return $carry;
    }
    $carry[$projectId] = [
        'id' => $projectId,
        'name' => (string) ($project['project'] ?? ''),
    ];
    return $carry;
}, []));
$weekRows = array_values(array_filter($rows, static function (array $row) use ($weekStart, $weekEnd): bool {
    $date = $row['date'] ?? null;
    if (!$date) {
        return false;
    }
    $timestamp = strtotime($date);
    if (!$timestamp) {
        return false;
    }
    return $timestamp >= $weekStart->getTimestamp() && $timestamp <= $weekEnd->getTimestamp();
}));
?>

<section class="timesheets-shell">
    <header class="timesheets-header">
        <div>
            <h2>Timesheets</h2>
            <p class="section-muted">Registro diario y semanal de horas por proyecto.</p>
        </div>
        <div class="header-actions">
            <form method="GET" class="week-selector">
                <label>
                    <span class="sr-only">Seleccionar semana</span>
                    <input type="week" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                </label>
                <button type="submit" class="secondary-button small">Ver semana</button>
            </form>
            <div class="week-pill">
                Semana <?= htmlspecialchars($weekStart->format('d/m')) ?> - <?= htmlspecialchars($weekEnd->format('d/m')) ?>
            </div>
        </div>
    </header>

    <div class="kpi-grid">
        <div class="card kpi">
            <span class="label">📝 Borrador</span>
            <span class="value"><?= $kpis['draft'] ?? 0 ?></span>
        </div>
        <div class="card kpi">
            <span class="label">⏳ Enviado</span>
            <span class="value"><?= $kpis['pending'] ?? 0 ?></span>
        </div>
        <div class="card kpi">
            <span class="label">✅ Aprobado</span>
            <span class="value"><?= $kpis['approved'] ?? 0 ?></span>
        </div>
        <div class="card kpi">
            <span class="label">❌ Rechazado</span>
            <span class="value"><?= $kpis['rejected'] ?? 0 ?></span>
        </div>
    </div>

    <?php if (!$canReport): ?>
        <section class="card timesheet-alert" role="status">
            <strong>Tu perfil no tiene habilitado el reporte de horas.</strong>
            <p class="section-muted">Puedes revisar la información disponible, pero no podrás registrar nuevas horas.</p>
        </section>
    <?php endif; ?>

    <?php if ($canReport && $hasReportableProjects): ?>
        <section class="card timesheet-entry">
            <header>
                <h3>Registrar horas</h3>
                <p class="section-muted">El reporte de horas se registra por proyecto asignado.</p>
            </header>
            <form method="POST" action="<?= $basePath ?>/timesheets/create" class="timesheet-form">
                <label>Proyecto
                    <select name="project_id" required>
                        <option value="">Selecciona un proyecto</option>
                        <?php foreach ($projectsForTimesheet as $project): ?>
                            <option value="<?= (int) ($project['id'] ?? 0) ?>"><?= htmlspecialchars($project['name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="form-grid">
                    <label>Fecha
                        <input type="date" name="date" required>
                    </label>
                    <label>Horas
                        <input type="number" name="hours" step="0.25" min="0.25" required>
                    </label>
                </div>
                <label>Comentario
                    <textarea name="comment" rows="2" required placeholder="Describe lo trabajado en el proyecto."></textarea>
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="billable" value="1"> Facturable
                </label>
                <button type="submit" class="primary-button">Registrar horas</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($canReport && !$hasReportableProjects): ?>
        <section class="card timesheet-entry">
            <header>
                <h3>Registrar horas</h3>
            </header>
            <p class="section-muted">No hay proyectos activos asignados para registrar horas.</p>
        </section>
    <?php endif; ?>

    <section class="card timesheet-week">
        <header>
            <h3>Vista semanal</h3>
            <p class="section-muted">Horas reportadas en la semana actual.</p>
        </header>
        <?php if (empty($weekRows)): ?>
            <p class="section-muted">Aún no tienes registros en esta semana.</p>
        <?php else: ?>
            <table class="clean-table">
                <thead>
                    <tr>
                        <th>Día</th>
                        <th>Proyecto</th>
                        <th>Tarea</th>
                        <th>Horas</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($weekRows as $row): ?>
                        <?php
                        $statusKey = $row['status'] === 'submitted' || $row['status'] === 'pending_approval' ? 'pending' : $row['status'];
                        $meta = $statusMeta[$statusKey] ?? ['label' => ucfirst((string) $statusKey), 'icon' => '📌', 'class' => 'status-muted'];
                        ?>
                        <tr>
                            <td class="cell-compact"><?= htmlspecialchars($row['date'] ?? '') ?></td>
                            <td class="cell-compact truncate"><?= htmlspecialchars($row['project'] ?? '') ?></td>
                            <td class="cell-compact truncate"><?= htmlspecialchars($row['task'] ?? '') ?></td>
                            <td class="cell-compact"><?= htmlspecialchars((string) ($row['hours'] ?? 0)) ?>h</td>
                            <td class="cell-compact"><span class="badge <?= $meta['class'] ?>"><?= htmlspecialchars($meta['icon']) ?> <?= htmlspecialchars($meta['label']) ?></span></td>
                            <td class="cell-compact">
                                <button type="button" class="ghost-button small" data-toggle-detail>Ver</button>
                            </td>
                        </tr>
                        <tr class="detail-row" hidden>
                            <td colspan="6">
                                <div class="detail-card">
                                    <div>
                                        <span class="meta-label">Comentario</span>
                                        <p class="detail-text"><?= htmlspecialchars((string) ($row['comment'] ?? '')) ?></p>
                                    </div>
                                    <?php if (!empty($row['approval_comment'])): ?>
                                        <div>
                                            <span class="meta-label">Comentario aprobación</span>
                                            <p class="detail-text"><?= htmlspecialchars((string) ($row['approval_comment'] ?? '')) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <?php if ($canApprove): ?>
        <section class="card timesheet-approvals">
            <header>
                <h3>Horas pendientes de aprobación</h3>
                <p class="section-muted">Aprueba o rechaza con comentario.</p>
            </header>
            <?php if (empty($pendingApprovals)): ?>
                <p class="section-muted">No hay horas pendientes.</p>
            <?php else: ?>
                <table class="clean-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Proyecto</th>
                            <th>Tarea</th>
                            <th>Talento</th>
                            <th>Horas</th>
                            <th>Comentario</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingApprovals as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['date']) ?></td>
                                <td><?= htmlspecialchars($row['project']) ?></td>
                                <td><?= htmlspecialchars($row['task']) ?></td>
                                <td><?= htmlspecialchars($row['talent']) ?></td>
                                <td><?= htmlspecialchars((string) ($row['hours'] ?? 0)) ?>h</td>
                                <td><?= htmlspecialchars((string) ($row['comment'] ?? '')) ?></td>
                                <td>
                                    <div class="action-stack">
                                        <form method="POST" action="<?= $basePath ?>/timesheets/<?= (int) $row['id'] ?>/approve" class="inline-form">
                                            <input type="text" name="comment" placeholder="Comentario (opcional)" aria-label="Comentario aprobación">
                                            <button type="submit" class="primary-button small">✅ Aprobar</button>
                                        </form>
                                        <form method="POST" action="<?= $basePath ?>/timesheets/<?= (int) $row['id'] ?>/reject" class="inline-form">
                                            <input type="text" name="comment" placeholder="Motivo de rechazo" required aria-label="Motivo rechazo">
                                            <button type="submit" class="secondary-button small">❌ Rechazar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="card timesheet-history">
        <header>
            <h3>Historial completo</h3>
            <p class="section-muted">Todos los registros asociados a tus permisos.</p>
        </header>
        <table class="clean-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Proyecto</th>
                    <th>Tarea</th>
                    <th>Talento</th>
                    <th>Horas</th>
                    <th>Estado</th>
                    <th>Comentario</th>
                    <th>Facturable</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $statusKey = $row['status'] === 'submitted' || $row['status'] === 'pending_approval' ? 'pending' : $row['status'];
                    $meta = $statusMeta[$statusKey] ?? ['label' => ucfirst((string) $statusKey), 'icon' => '📌', 'class' => 'status-muted'];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['date'] ?? '') ?></td>
                        <td class="wrap-anywhere"><?= htmlspecialchars($row['project'] ?? '') ?></td>
                        <td class="wrap-anywhere"><?= htmlspecialchars($row['task'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['talent'] ?? '') ?></td>
                        <td><?= htmlspecialchars((string) ($row['hours'] ?? 0)) ?>h</td>
                        <td><span class="badge <?= $meta['class'] ?>"><?= htmlspecialchars($meta['icon']) ?> <?= htmlspecialchars($meta['label']) ?></span></td>
                        <td class="wrap-anywhere"><?= htmlspecialchars((string) ($row['comment'] ?? '')) ?></td>
                        <td><?= !empty($row['billable']) ? 'Sí' : 'No' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</section>

<style>
    .timesheets-shell { display:flex; flex-direction:column; gap:16px; }
    .timesheets-header { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
    .timesheets-header h2 { margin:0; }
    .section-muted { color: var(--text-secondary); margin:0; font-size:13px; }
    .header-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
    .week-selector { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .week-selector input { padding:8px 10px; border-radius:10px; border:1px solid var(--border); background: var(--surface); color: var(--text-primary); font-size:13px; }
    .week-pill { padding:6px 12px; border-radius:999px; background: color-mix(in srgb, var(--accent) 20%, var(--surface) 80%); color: var(--text-primary); font-weight:700; font-size:12px; }
    .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
    .kpi-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:12px; }
    .timesheet-entry header,
    .timesheet-week header,
    .timesheet-approvals header,
    .timesheet-history header { display:flex; flex-direction:column; gap:4px; margin-bottom:8px; }
    .timesheet-form { display:flex; flex-direction:column; gap:12px; }
    .timesheet-form select,
    .timesheet-form input,
    .timesheet-form textarea,
    .inline-form input { width:100%; padding:10px; border:1px solid var(--border); border-radius:10px; background: var(--surface); color: var(--text-primary); }
    .timesheet-alert { display:flex; flex-direction:column; gap:6px; border-left:4px solid var(--accent); }
    .form-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; }
    .checkbox { display:flex; gap:8px; align-items:center; }
    .clean-table { width:100%; border-collapse:collapse; }
    .clean-table th,
    .clean-table td { text-align:left; padding:10px; border-bottom:1px solid var(--border); font-size:14px; line-height:1.4; }
    .clean-table th { text-align:left; padding:10px; border-bottom:1px solid var(--border); font-size:12px; letter-spacing:0.04em; text-transform:uppercase; color: var(--text-secondary); }
    .cell-compact { font-size:13px; line-height:1.4; }
    .truncate { max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .badge { padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; display:inline-flex; align-items:center; gap:6px; }
    .wrap-anywhere { overflow-wrap:anywhere; max-width:260px; }
    .status-muted { background: color-mix(in srgb, var(--surface) 80%, var(--border) 20%); color: var(--text-secondary); }
    .status-info { background: color-mix(in srgb, var(--accent) 18%, var(--surface) 82%); color: var(--text-primary); }
    .status-warning { background: color-mix(in srgb, var(--warning) 24%, var(--surface) 76%); color: var(--text-primary); }
    .status-success { background: color-mix(in srgb, var(--success) 24%, var(--surface) 76%); color: var(--text-primary); }
    .status-danger { background: color-mix(in srgb, var(--danger) 22%, var(--surface) 78%); color: var(--text-primary); }
    .action-stack { display:flex; flex-direction:column; gap:8px; }
    .inline-form { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .primary-button { background: var(--primary); color: var(--text-primary); border:none; cursor:pointer; border-radius:10px; padding:10px 14px; font-weight:700; }
    .secondary-button { background: color-mix(in srgb, var(--surface) 86%, var(--background) 14%); color: var(--text-primary); border:1px solid var(--border); cursor:pointer; border-radius:10px; padding:10px 14px; font-weight:700; }
    .primary-button.small,
    .secondary-button.small { padding:6px 10px; font-size:12px; }
    .ghost-button { border:1px solid var(--border); background: color-mix(in srgb, var(--surface) 85%, var(--background) 15%); color: var(--text-primary); border-radius:10px; padding:6px 10px; font-size:12px; font-weight:600; cursor:pointer; }
    .ghost-button.small { padding:6px 8px; font-size:12px; }
    .detail-row td { padding:0; border-bottom:none; }
    .detail-card { display:grid; gap:10px; padding:12px; border-top:1px solid var(--border); background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%); }
    .detail-card .meta-label { text-transform:uppercase; font-size:11px; letter-spacing:0.04em; color: var(--text-secondary); font-weight:700; }
    .detail-text { margin:4px 0 0; font-size:13px; line-height:1.5; color: var(--text-primary); max-width:100%; overflow-wrap:anywhere; }
    @media (max-width: 900px) {
        .clean-table { display:block; overflow-x:auto; }
        .truncate { max-width:160px; }
    }
</style>

<script>
    (() => {
        document.querySelectorAll('[data-toggle-detail]').forEach(button => {
            button.addEventListener('click', () => {
                const detailRow = button.closest('tr')?.nextElementSibling;
                if (!detailRow || !detailRow.classList.contains('detail-row')) {
                    return;
                }
                detailRow.hidden = !detailRow.hidden;
            });
        });
    })();
</script>
