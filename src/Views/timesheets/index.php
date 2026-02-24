<?php
$basePath = $basePath ?? '';
$canReport = !empty($canReport);
$canApprove = !empty($canApprove);
$canManageWorkflow = !empty($canManageWorkflow);
$canDeleteWeek = !empty($canDeleteWeek);
$canManageAdvanced = !empty($canManageAdvanced);
$weekStart = $weekStart ?? new DateTimeImmutable('monday this week');
$weekEnd = $weekEnd ?? $weekStart->modify('+6 days');
$weekValue = $weekValue ?? $weekStart->format('o-\\WW');
$weeklyGrid = is_array($weeklyGrid ?? null) ? $weeklyGrid : [];
$gridDays = is_array($weeklyGrid['days'] ?? null) ? $weeklyGrid['days'] : [];
$gridRows = is_array($weeklyGrid['rows'] ?? null) ? $weeklyGrid['rows'] : [];
$dayTotals = is_array($weeklyGrid['day_totals'] ?? null) ? $weeklyGrid['day_totals'] : [];
$weekTotal = (float) ($weeklyGrid['week_total'] ?? 0);
$weeklyCapacity = (float) ($weeklyGrid['weekly_capacity'] ?? 0);
$requiresFullReport = !empty($weeklyGrid['requires_full_report']);
$weeksHistory = is_array($weeksHistory ?? null) ? $weeksHistory : [];
$selectedWeekSummary = is_array($selectedWeekSummary ?? null) ? $selectedWeekSummary : [];
$weekHistoryLog = is_array($weekHistoryLog ?? null) ? $weekHistoryLog : [];
$monthlySummary = is_array($monthlySummary ?? null) ? $monthlySummary : [];
$periodType = (string) ($periodType ?? 'month');
$periodStart = $periodStart ?? $weekStart->modify('first day of this month')->setTime(0, 0);
$periodEnd = $periodEnd ?? $weekStart->modify('last day of this month')->setTime(0, 0);
$projectFilter = (int) ($projectFilter ?? 0);
$projectsForFilter = is_array($projectsForFilter ?? null) ? $projectsForFilter : [];
$executiveSummary = is_array($executiveSummary ?? null) ? $executiveSummary : [];
$approvedWeeks = is_array($approvedWeeks ?? null) ? $approvedWeeks : [];
$talentBreakdown = is_array($talentBreakdown ?? null) ? $talentBreakdown : [];
$projectBreakdown = is_array($projectBreakdown ?? null) ? $projectBreakdown : [];
$talentSort = (string) ($talentSort ?? 'load_desc');
$managedWeekEntries = is_array($managedWeekEntries ?? null) ? $managedWeekEntries : [];
$talentOptions = is_array($talentOptions ?? null) ? $talentOptions : [];
$talentFilter = (int) ($talentFilter ?? 0);

$statusMap = [
    'approved' => ['label' => 'Aprobada', 'class' => 'approved'],
    'rejected' => ['label' => 'Rechazada', 'class' => 'rejected'],
    'submitted' => ['label' => 'Pendiente', 'class' => 'submitted'],
    'draft' => ['label' => 'Borrador', 'class' => 'draft'],
    'partial' => ['label' => 'Parcial', 'class' => 'partial'],
];
$selectedStatus = (string) ($selectedWeekSummary['status'] ?? 'draft');
if (!isset($statusMap[$selectedStatus])) {
    $selectedStatus = 'draft';
}
$selectedMeta = $statusMap[$selectedStatus];

$weeksRegistered = count($approvedWeeks);
$weeksApproved = count(array_filter($approvedWeeks, static fn($w): bool => ((int) ($w['status_weight'] ?? 0)) >= 5));
$weeksPending = max(0, $weeksRegistered - $weeksApproved);
$approvedPercent = (float) ($executiveSummary['approved_percent'] ?? 0);
$compliancePercent = (float) ($executiveSummary['compliance_percent'] ?? 0);
?>

<section class="timesheets-shell">
    <header class="timesheets-header card">
        <div>
            <h2>Timesheets</h2>
            <p class="section-muted">Operativo + Analítico + Gerencial.</p>
        </div>
        <form method="GET" class="filters-grid">
            <label>Semana
                <input type="week" name="week" value="<?= htmlspecialchars($weekValue) ?>">
            </label>
            <label>Periodo
                <select name="period" id="period-select">
                    <option value="month" <?= $periodType === 'month' ? 'selected' : '' ?>>Mes</option>
                    <option value="custom" <?= $periodType === 'custom' ? 'selected' : '' ?>>Rango personalizado</option>
                </select>
            </label>
            <label>Desde
                <input type="date" name="range_start" value="<?= htmlspecialchars($periodStart->format('Y-m-d')) ?>" <?= $periodType !== 'custom' ? 'disabled' : '' ?> data-range-input>
            </label>
            <label>Hasta
                <input type="date" name="range_end" value="<?= htmlspecialchars($periodEnd->format('Y-m-d')) ?>" <?= $periodType !== 'custom' ? 'disabled' : '' ?> data-range-input>
            </label>
            <label>Proyecto
                <select name="project_id">
                    <option value="0">Todos</option>
                    <?php foreach ($projectsForFilter as $project): ?>
                        <option value="<?= (int) ($project['project_id'] ?? 0) ?>" <?= $projectFilter === (int) ($project['project_id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($project['project'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Orden talento
                <select name="talent_sort">
                    <option value="load_desc" <?= $talentSort === 'load_desc' ? 'selected' : '' ?>>Mayor carga</option>
                    <option value="compliance_asc" <?= $talentSort === 'compliance_asc' ? 'selected' : '' ?>>Menor cumplimiento</option>
                </select>
            </label>
            <button type="submit" class="primary-button">Aplicar</button>
        </form>
    </header>

    <section class="kpi-grid">
        <article class="card kpi"><h4>Horas registradas</h4><strong><?= round((float) ($executiveSummary['total'] ?? 0), 2) ?>h</strong></article>
        <article class="card kpi approved"><h4>Aprobadas</h4><strong><?= round((float) ($executiveSummary['approved'] ?? 0), 2) ?>h</strong><small><?= round($approvedPercent, 2) ?>%</small></article>
        <article class="card kpi rejected"><h4>Rechazadas</h4><strong><?= round((float) ($executiveSummary['rejected'] ?? 0), 2) ?>h</strong></article>
        <article class="card kpi draft"><h4>Borrador</h4><strong><?= round((float) ($executiveSummary['draft'] ?? 0), 2) ?>h</strong></article>
        <article class="card kpi pending"><h4>Pendientes</h4><strong><?= round((float) ($executiveSummary['pending'] ?? 0), 2) ?>h</strong></article>
        <article class="card kpi <?= $compliancePercent > 100 ? 'rejected' : ($compliancePercent >= 80 ? 'approved' : 'pending') ?>"><h4>Cumplimiento vs capacidad</h4><strong><?= round($compliancePercent, 2) ?>%</strong><small>Capacidad: <?= round((float) ($executiveSummary['capacity'] ?? 0), 2) ?>h</small></article>
    </section>

    <section class="card">
        <h3>Resumen de semanas del periodo</h3>
        <p><strong>Semanas registradas este periodo:</strong> <?= $weeksRegistered ?> · <strong>Semanas aprobadas:</strong> <?= $weeksApproved ?> · <strong>Semanas pendientes:</strong> <?= $weeksPending ?></p>
    </section>

    <section class="card">
        <h3>Horas aprobadas por semana</h3>
        <div class="table-wrap">
            <table class="clean-table">
                <thead><tr><th>Semana</th><th>Rango</th><th>Total horas</th><th>Estado</th><th>Fecha aprobación</th><th>Aprobador</th></tr></thead>
                <tbody>
                <?php foreach ($approvedWeeks as $week):
                    $start = new DateTimeImmutable((string) ($week['week_start'] ?? 'now'));
                    $end = new DateTimeImmutable((string) ($week['week_end'] ?? 'now'));
                    $weight = (int) ($week['status_weight'] ?? 0);
                    $state = $weight >= 5 ? 'approved' : ($weight >= 4 ? 'rejected' : 'submitted');
                    $meta = $statusMap[$state] ?? $statusMap['draft'];
                ?>
                    <tr>
                        <td><a href="<?= $basePath ?>/timesheets?week=<?= urlencode($start->format('o-\\WW')) ?>">Semana <?= htmlspecialchars($start->format('W')) ?></a></td>
                        <td><?= htmlspecialchars($start->format('d/m/Y')) ?> - <?= htmlspecialchars($end->format('d/m/Y')) ?></td>
                        <td><?= round((float) ($week['total_hours'] ?? 0), 2) ?>h</td>
                        <td><span class="badge-state <?= $meta['class'] ?>"><?= htmlspecialchars($meta['label']) ?></span></td>
                        <td><?= htmlspecialchars((string) ($week['approved_at'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars((string) ($week['approver_name'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h3>Desglose por Talento (gerencial)</h3>
        <div class="table-wrap">
            <table class="clean-table">
                <thead><tr><th>Talento</th><th>Total</th><th>Aprobadas</th><th>Rechazadas</th><th>Borrador</th><th>% Cumplimiento</th><th>Última semana enviada</th><th>Última semana aprobada</th></tr></thead>
                <tbody>
                <?php foreach ($talentBreakdown as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($row['talent_name'] ?? '')) ?></td>
                        <td><?= round((float) ($row['total_hours'] ?? 0), 2) ?>h</td>
                        <td><?= round((float) ($row['approved_hours'] ?? 0), 2) ?>h</td>
                        <td><?= round((float) ($row['rejected_hours'] ?? 0), 2) ?>h</td>
                        <td><?= round((float) ($row['draft_hours'] ?? 0), 2) ?>h</td>
                        <td><?= round((float) ($row['compliance_percent'] ?? 0), 2) ?>%</td>
                        <td><?= htmlspecialchars((string) ($row['last_week_submitted'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['last_week_approved'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="timesheet-layout">
        <section class="main-column">
            <section class="card weeks-history">
                <header><h3>Historial de semanas</h3></header>
                <div class="weeks-row">
                    <?php foreach ($weeksHistory as $week):
                        $start = new DateTimeImmutable((string) ($week['week_start'] ?? 'now'));
                        $end = new DateTimeImmutable((string) ($week['week_end'] ?? 'now'));
                        $statusWeight = (int) ($week['status_weight'] ?? 2);
                        $status = $statusWeight >= 5 ? 'approved' : ($statusWeight >= 4 ? 'rejected' : ($statusWeight >= 3 ? 'submitted' : 'draft'));
                        $isCurrent = $start->format('Y-m-d') === $weekStart->format('Y-m-d');
                        $meta = $statusMap[$status] ?? $statusMap['draft'];
                    ?>
                        <a class="week-card <?= $meta['class'] ?> <?= $isCurrent ? 'active' : '' ?>" href="<?= $basePath ?>/timesheets?week=<?= urlencode($start->format('o-\\WW')) ?>">
                            <strong>Semana <?= htmlspecialchars($start->format('W')) ?></strong>
                            <span><?= htmlspecialchars($start->format('d/m')) ?> - <?= htmlspecialchars($end->format('d/m')) ?></span>
                            <span><?= htmlspecialchars((string) round((float) ($week['total_hours'] ?? 0), 2)) ?>h</span>
                            <span class="badge-state <?= $meta['class'] ?>"><?= htmlspecialchars($meta['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php if (($canApprove || $canManageAdvanced)): ?>
            <section class="card" id="manager-view">
                <h3>Control gerencial operativo</h3>
                <p class="section-muted">Aprobación/rechazo por registro, edición controlada, reapertura y eliminación con auditoría.</p>
                <form method="GET" class="filters-grid" style="grid-template-columns:repeat(4,minmax(120px,1fr));margin-bottom:12px;">
                    <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                    <label>Talento
                        <select name="talent_id">
                            <option value="0">Todos</option>
                            <?php foreach ($talentOptions as $tal): ?>
                                <option value="<?= (int) ($tal['id'] ?? 0) ?>" <?= $talentFilter === (int) ($tal['id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($tal['name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="primary-button">Filtrar gestión</button>
                </form>
                <div class="table-wrap"><table class="clean-table"><thead><tr><th>Fecha</th><th>Talento</th><th>Proyecto</th><th>Horas</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
                <?php foreach ($managedWeekEntries as $entry): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($entry['date'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($entry['talent_name'] ?? $entry['user_name'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($entry['project_name'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) round((float) ($entry['hours'] ?? 0), 2)) ?></td>
                        <td><span class="badge-state <?= htmlspecialchars((string) ($entry['status'] ?? 'draft')) ?>"><?= htmlspecialchars((string) ($entry['status'] ?? 'draft')) ?></span></td>
                        <td>
                            <?php if ($canApprove && in_array((string) ($entry['status'] ?? ''), ['submitted','pending','pending_approval'], true)): ?>
                                <form method="POST" action="<?= $basePath ?>/timesheets/<?= (int) ($entry['id'] ?? 0) ?>/approve" class="inline-form"><button type="submit" class="action-btn small primary">Aprobar</button></form>
                                <form method="POST" action="<?= $basePath ?>/timesheets/<?= (int) ($entry['id'] ?? 0) ?>/reject" class="inline-form"><input type="text" name="comment" placeholder="Motivo" required><button type="submit" class="action-btn small danger">Rechazar</button></form>
                            <?php endif; ?>
                            <?php if ($canManageAdvanced): ?>
                                <form method="POST" action="<?= $basePath ?>/timesheets/admin-action" class="inline-form"><input type="hidden" name="admin_action" value="update_hours"><input type="hidden" name="timesheet_id" value="<?= (int) ($entry['id'] ?? 0) ?>"><input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>"><input type="number" step="0.25" min="0" max="24" name="hours" value="<?= htmlspecialchars((string) ($entry['hours'] ?? 0)) ?>" required><input type="text" name="reason" placeholder="Motivo auditoría" required><button type="submit" class="action-btn small">Editar</button></form>
                                <form method="POST" action="<?= $basePath ?>/timesheets/admin-action" class="inline-form"><input type="hidden" name="admin_action" value="delete_entry"><input type="hidden" name="timesheet_id" value="<?= (int) ($entry['id'] ?? 0) ?>"><input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>"><input type="text" name="reason" placeholder="Motivo auditoría" required><button type="submit" class="action-btn small danger">Eliminar</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>

                <?php if ($canManageAdvanced): ?>
                    <h4>Eliminación masiva controlada</h4>
                    <form method="POST" action="<?= $basePath ?>/timesheets/admin-action" class="filters-grid" style="grid-template-columns:repeat(4,minmax(140px,1fr));">
                        <input type="hidden" name="admin_action" value="delete_week">
                        <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                        <input type="hidden" name="week_start" value="<?= htmlspecialchars($weekStart->format('Y-m-d')) ?>">
                        <label>Talento afectado
                            <select name="talent_id"><option value="0">Toda la semana</option><?php foreach ($talentOptions as $tal): ?><option value="<?= (int) ($tal['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($tal['name'] ?? '')) ?></option><?php endforeach; ?></select>
                        </label>
                        <label>Motivo
                            <input type="text" name="reason" required placeholder="Ej: limpieza de carga masiva">
                        </label>
                        <label>Confirmación explícita
                            <input type="text" name="confirm_token" required placeholder="ELIMINAR MASIVO">
                        </label>
                        <button type="submit" class="action-btn danger">Eliminar masivo</button>
                    </form>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if ($canReport): ?>
            <section class="card" id="table-view">
                <h3>Carga semanal</h3>
                <div class="table-wrap"><table class="clean-table week-grid"><thead><tr><th>Proyecto</th><?php foreach ($gridDays as $day): ?><th><?= htmlspecialchars($day['label']) ?><br><small><?= htmlspecialchars($day['number']) ?></small></th><?php endforeach; ?><th>Total</th></tr></thead><tbody>
                <?php foreach ($gridRows as $row): ?><tr><td><?= htmlspecialchars((string) ($row['project'] ?? '')) ?></td><?php foreach ($gridDays as $day): $date=$day['key']; $cell=$row['cells'][$date] ?? ['hours'=>0,'status'=>'draft','comment'=>'']; $hours=(float)($cell['hours']??0); ?><td class="cell <?= (($cell['status'] ?? 'draft') !== 'draft') ? 'locked' : '' ?>"><input type="number" step="0.25" min="0" max="24" value="<?= htmlspecialchars(rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.')) ?>" data-date="<?= htmlspecialchars($date) ?>" data-project="<?= (int) ($row['project_id'] ?? 0) ?>" data-comment="<?= htmlspecialchars((string) ($cell['comment'] ?? ''), ENT_QUOTES) ?>" class="hour-input" <?= (($cell['status'] ?? 'draft') !== 'draft') ? 'disabled' : '' ?>></td><?php endforeach; ?><td><strong><?= htmlspecialchars((string) round((float) ($row['total'] ?? 0), 2)) ?></strong></td></tr><?php endforeach; ?>
                <tr class="total-row"><td><strong>TOTAL DÍA</strong></td><?php foreach ($gridDays as $day): $total=(float)($dayTotals[$day['key']] ?? 0); ?><td><strong><?= htmlspecialchars((string) round($total, 2)) ?></strong></td><?php endforeach; ?><td><strong><?= htmlspecialchars((string) round($weekTotal, 2)) ?></strong></td></tr>
                </tbody></table></div>
                <?php if ($requiresFullReport): ?><p class="section-muted">* Requiere reporte completo.</p><?php endif; ?>
            </section>
            <?php endif; ?>
        </section>

        <aside class="card side-column">
            <h3>Resumen del mes actual</h3>
            <ul class="summary-list">
                <li>Total horas mes: <strong><?= htmlspecialchars((string) round((float) ($monthlySummary['month_total'] ?? 0), 2)) ?></strong></li>
                <li>Horas aprobadas: <strong><?= htmlspecialchars((string) round((float) ($monthlySummary['approved'] ?? 0), 2)) ?></strong></li>
                <li>Horas rechazadas: <strong><?= htmlspecialchars((string) round((float) ($monthlySummary['rejected'] ?? 0), 2)) ?></strong></li>
                <li>Horas en borrador: <strong><?= htmlspecialchars((string) round((float) ($monthlySummary['draft'] ?? 0), 2)) ?></strong></li>
                <li>Capacidad mes: <strong><?= htmlspecialchars((string) round((float) ($monthlySummary['capacity'] ?? 0), 2)) ?></strong></li>
                <li>Porcentaje cumplimiento: <strong><?= htmlspecialchars((string) ($monthlySummary['compliance'] ?? 0)) ?>%</strong></li>
            </ul>
            <h4>Proyecto con mayor consumo</h4>
            <p><?= htmlspecialchars((string) ($projectBreakdown[0]['project'] ?? 'Sin datos')) ?> (<?= round((float) ($projectBreakdown[0]['total_hours'] ?? 0), 2) ?>h)</p>
            <button class="secondary-button" onclick="window.print()">Descargar informe consolidado</button>
        </aside>
    </div>
</section>

<style>
.timesheets-shell{display:flex;flex-direction:column;gap:16px}.timesheet-layout{display:grid;grid-template-columns:2fr 1fr;gap:16px}.main-column{display:flex;flex-direction:column;gap:16px}.side-column{height:max-content}.filters-grid{display:grid;grid-template-columns:repeat(7,minmax(120px,1fr));gap:10px;align-items:end}.filters-grid label{display:flex;flex-direction:column;gap:4px}.kpi-grid{display:grid;grid-template-columns:repeat(3,minmax(160px,1fr));gap:12px}.kpi strong{font-size:1.4rem}.kpi.approved{border-top:4px solid #16a34a}.kpi.rejected{border-top:4px solid #dc2626}.kpi.draft{border-top:4px solid #6b7280}.kpi.pending{border-top:4px solid #eab308}.weeks-row{display:flex;gap:10px;overflow:auto;padding-bottom:6px}.week-card{min-width:170px;display:flex;flex-direction:column;gap:4px;border:1px solid var(--border);border-radius:12px;padding:10px;text-decoration:none;color:inherit}.week-card.active{outline:2px solid var(--accent)}.week-card.approved{border-left:6px solid #16a34a}.week-card.rejected{border-left:6px solid #dc2626}.week-card.submitted{border-left:6px solid #eab308}.week-card.draft{border-left:6px solid #6b7280}.week-card.partial{border-left:6px solid #2563eb}.badge-state{font-size:12px;font-weight:700;padding:2px 8px;border-radius:999px}.badge-state.approved{background:#dcfce7;color:#166534}.badge-state.rejected{background:#fee2e2;color:#991b1b}.badge-state.submitted{background:#fef3c7;color:#92400e}.badge-state.draft{background:#e5e7eb;color:#374151}.summary-list{margin:0;padding-left:18px;display:flex;flex-direction:column;gap:8px}.table-wrap{overflow:auto}.hour-input{width:64px;padding:4px;border:1px solid var(--border);border-radius:8px}.cell.locked{background:#e0f2fe}
</style>

<script>
(() => {
  const periodSelect = document.getElementById('period-select');
  const rangeInputs = document.querySelectorAll('[data-range-input]');
  periodSelect?.addEventListener('change', () => {
    const custom = periodSelect.value === 'custom';
    rangeInputs.forEach((el) => { el.disabled = !custom; });
  });

  async function saveCell(input) {
    const payload = new URLSearchParams({ project_id: input.dataset.project, date: input.dataset.date, hours: String(Number(input.value || 0)), comment: input.dataset.comment || '' });
    const res = await fetch('<?= $basePath ?>/timesheets/cell', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: payload });
    if (!res.ok) { alert('No se pudo guardar.'); }
  }
  document.querySelectorAll('.hour-input').forEach(input => input.addEventListener('change', () => saveCell(input)));
})();
</script>
