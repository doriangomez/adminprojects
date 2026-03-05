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
$projectsForTimesheet = is_array($projectsForTimesheet ?? null) ? $projectsForTimesheet : [];

$activityTypes = is_array($activityTypes ?? null) ? $activityTypes : [];
$weekActivities = is_array($weekActivities ?? null) ? $weekActivities : [];
$capacityUtil = is_array($capacityUtil ?? null) ? $capacityUtil : [];
$activityTypeBreakdown = is_array($activityTypeBreakdown ?? null) ? $activityTypeBreakdown : [];
$recentActivities = is_array($recentActivities ?? null) ? $recentActivities : [];
$talentTopLoaded = is_array($talentTopLoaded ?? null) ? $talentTopLoaded : [];
$projectTopConsuming = is_array($projectTopConsuming ?? null) ? $projectTopConsuming : [];

$statusMap = [
    'approved' => ['label' => 'Aprobada', 'class' => 'approved'],
    'rejected' => ['label' => 'Rechazada', 'class' => 'rejected'],
    'submitted' => ['label' => 'Pendiente', 'class' => 'submitted'],
    'draft' => ['label' => 'Borrador', 'class' => 'draft'],
    'partial' => ['label' => 'Parcial', 'class' => 'partial'],
];
$selectedStatus = (string) ($selectedWeekSummary['status'] ?? 'draft');
if (!isset($statusMap[$selectedStatus])) { $selectedStatus = 'draft'; }
$selectedMeta = $statusMap[$selectedStatus];

$weeksRegistered = count($approvedWeeks);
$weeksApproved = count(array_filter($approvedWeeks, static fn($w): bool => ((int) ($w['status_weight'] ?? 0)) >= 5));
$weeksPending = max(0, $weeksRegistered - $weeksApproved);
$approvedPercent = (float) ($executiveSummary['approved_percent'] ?? 0);
$compliancePercent = (float) ($executiveSummary['compliance_percent'] ?? 0);

$activitiesByDay = [];
foreach ($weekActivities as $act) {
    $d = (string) ($act['date'] ?? '');
    $activitiesByDay[$d][] = $act;
}
$dayHours = [];
foreach ($gridDays as $day) {
    $key = $day['key'];
    $total = 0.0;
    foreach ($activitiesByDay[$key] ?? [] as $a) {
        $total += (float) ($a['hours'] ?? 0);
    }
    $dayHours[$key] = $total;
}

$activityTypeMap = [];
foreach ($activityTypes as $at) {
    $activityTypeMap[(string) $at['code']] = $at;
}

$totalBreakdownHours = 0;
foreach ($activityTypeBreakdown as $bd) {
    $totalBreakdownHours += (float) ($bd['total_hours'] ?? 0);
}

$weekIsLocked = $selectedStatus === 'approved';
$weekCanWithdraw = in_array($selectedStatus, ['submitted', 'partial'], true);

$prevWeek = $weekStart->modify('-7 days');
$nextWeek = $weekStart->modify('+7 days');
?>

<section class="ts-shell">
    <!-- ═══ Header ═══ -->
    <header class="ts-top-header">
        <div class="ts-title-block">
            <h2>Timesheet</h2>
            <p class="ts-subtitle">Registro estructurado de actividad operativa</p>
        </div>
        <div class="ts-week-nav">
            <a href="<?= $basePath ?>/timesheets?week=<?= urlencode($prevWeek->format('o-\\WW')) ?>" class="ts-nav-btn" title="Semana anterior">&lsaquo;</a>
            <div class="ts-week-label">
                <strong>Semana <?= htmlspecialchars($weekStart->format('W')) ?></strong>
                <span><?= htmlspecialchars($weekStart->format('d M')) ?> – <?= htmlspecialchars($weekEnd->format('d M Y')) ?></span>
            </div>
            <a href="<?= $basePath ?>/timesheets?week=<?= urlencode($nextWeek->format('o-\\WW')) ?>" class="ts-nav-btn" title="Semana siguiente">&rsaquo;</a>
            <form method="GET" class="ts-week-picker">
                <input type="week" name="week" value="<?= htmlspecialchars($weekValue) ?>" onchange="this.form.submit()">
            </form>
        </div>
    </header>

    <!-- ═══ Capacity & Status Bar ═══ -->
    <?php if ($canReport): ?>
    <section class="ts-capacity-bar">
        <div class="ts-cap-item">
            <span class="ts-cap-label">Capacidad</span>
            <strong><?= round((float) ($capacityUtil['weekly_capacity'] ?? 0), 1) ?>h</strong>
        </div>
        <div class="ts-cap-item">
            <span class="ts-cap-label">Reportado</span>
            <strong><?= round((float) ($capacityUtil['hours_reported'] ?? 0), 1) ?>h</strong>
        </div>
        <div class="ts-cap-item ts-cap-progress">
            <span class="ts-cap-label">Utilización</span>
            <div class="ts-progress-wrap">
                <?php $pct = (float) ($capacityUtil['utilization_percent'] ?? 0); $barClass = $pct >= 100 ? 'over' : ($pct >= 80 ? 'good' : ($pct >= 50 ? 'mid' : 'low')); ?>
                <div class="ts-progress-bar <?= $barClass ?>"><div class="ts-progress-fill" style="width:<?= min(100, $pct) ?>%"></div></div>
                <strong><?= round($pct, 1) ?>%</strong>
            </div>
        </div>
        <div class="ts-cap-item">
            <span class="ts-cap-label">Restante</span>
            <strong><?= round((float) ($capacityUtil['remaining'] ?? 0), 1) ?>h</strong>
        </div>
        <div class="ts-cap-item">
            <span class="ts-cap-label">Estado</span>
            <span class="ts-badge <?= htmlspecialchars($selectedMeta['class']) ?>"><?= htmlspecialchars($selectedMeta['label']) ?></span>
        </div>
    </section>
    <?php endif; ?>

    <!-- ═══ KPI Cards ═══ -->
    <section class="ts-kpi-grid">
        <article class="ts-kpi"><div class="ts-kpi-icon">&#128337;</div><div><span class="ts-kpi-label">Registradas</span><strong><?= round((float) ($executiveSummary['total'] ?? 0), 1) ?>h</strong></div></article>
        <article class="ts-kpi approved"><div class="ts-kpi-icon">&#9989;</div><div><span class="ts-kpi-label">Aprobadas</span><strong><?= round((float) ($executiveSummary['approved'] ?? 0), 1) ?>h</strong><small><?= round($approvedPercent, 1) ?>%</small></div></article>
        <article class="ts-kpi pending"><div class="ts-kpi-icon">&#9203;</div><div><span class="ts-kpi-label">Pendientes</span><strong><?= round((float) ($executiveSummary['pending'] ?? 0), 1) ?>h</strong></div></article>
        <article class="ts-kpi rejected"><div class="ts-kpi-icon">&#10060;</div><div><span class="ts-kpi-label">Rechazadas</span><strong><?= round((float) ($executiveSummary['rejected'] ?? 0), 1) ?>h</strong></div></article>
        <article class="ts-kpi draft"><div class="ts-kpi-icon">&#128221;</div><div><span class="ts-kpi-label">Borrador</span><strong><?= round((float) ($executiveSummary['draft'] ?? 0), 1) ?>h</strong></div></article>
        <article class="ts-kpi <?= $compliancePercent >= 80 ? 'approved' : ($compliancePercent >= 50 ? 'pending' : 'rejected') ?>"><div class="ts-kpi-icon">&#128200;</div><div><span class="ts-kpi-label">Cumplimiento</span><strong><?= round($compliancePercent, 1) ?>%</strong><small>Cap: <?= round((float) ($executiveSummary['capacity'] ?? 0), 1) ?>h</small></div></article>
    </section>

    <!-- ═══ Main Layout: Calendar + Sidebar ═══ -->
    <div class="ts-main-layout">
        <div class="ts-main-col">
            <!-- Calendar Weekly View -->
            <?php if ($canReport): ?>
            <section class="ts-calendar-section">
                <header class="ts-calendar-header">
                    <h3>Vista semanal</h3>
                    <div class="ts-calendar-actions">
                        <button type="button" class="ts-btn ts-btn-primary" id="btn-add-activity" title="Registrar actividad">+ Registrar actividad</button>
                        <form method="POST" action="<?= $basePath ?>/timesheets/submit-week" class="ts-inline">
                            <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                            <button type="submit" class="ts-btn ts-btn-success" <?= $weekIsLocked ? 'disabled' : '' ?>>Enviar semana</button>
                        </form>
                        <?php if ($weekCanWithdraw): ?>
                        <form method="POST" action="<?= $basePath ?>/timesheets/cancel-week" class="ts-inline">
                            <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                            <button type="submit" class="ts-btn ts-btn-outline">Retirar envío</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($weekIsLocked && $canManageWorkflow): ?>
                        <form method="POST" action="<?= $basePath ?>/timesheets/reopen-own-week" class="ts-inline ts-reopen-form">
                            <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                            <input type="text" name="comment" placeholder="Motivo" required class="ts-input-sm">
                            <button type="submit" class="ts-btn ts-btn-outline">Reabrir</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </header>
                <div class="ts-calendar-grid">
                    <?php foreach ($gridDays as $day):
                        $dateKey = $day['key'];
                        $isToday = $dateKey === (new DateTimeImmutable())->format('Y-m-d');
                        $dayActivities = $activitiesByDay[$dateKey] ?? [];
                        $dayTotal = $dayHours[$dateKey] ?? 0;
                    ?>
                    <div class="ts-day-col <?= $isToday ? 'ts-today' : '' ?>">
                        <div class="ts-day-header">
                            <span class="ts-day-name"><?= htmlspecialchars($day['label']) ?></span>
                            <span class="ts-day-number"><?= htmlspecialchars($day['number']) ?></span>
                            <span class="ts-day-total"><?= round($dayTotal, 1) ?>h</span>
                        </div>
                        <div class="ts-day-body" data-date="<?= htmlspecialchars($dateKey) ?>">
                            <?php if (empty($dayActivities)): ?>
                                <div class="ts-day-empty">Sin actividades</div>
                            <?php else: ?>
                                <?php foreach ($dayActivities as $act):
                                    $typeCode = (string) ($act['activity_type'] ?? '');
                                    $typeInfo = $activityTypeMap[$typeCode] ?? null;
                                    $typeColor = $typeInfo ? $typeInfo['color'] : '#6b7280';
                                    $typeIcon = $typeInfo ? $typeInfo['icon'] : '';
                                    $typeName = $typeInfo ? $typeInfo['name'] : ($typeCode ?: 'General');
                                    $actStatus = (string) ($act['status'] ?? 'draft');
                                    $actHours = (float) ($act['hours'] ?? 0);
                                    $actDesc = (string) ($act['description'] ?? $act['comment'] ?? '');
                                    $actProject = (string) ($act['project_name'] ?? '');
                                    $actId = (int) ($act['id'] ?? 0);
                                ?>
                                <div class="ts-activity-card status-<?= htmlspecialchars($actStatus) ?>"
                                     data-entry-id="<?= $actId ?>"
                                     data-project-id="<?= (int) ($act['project_id'] ?? 0) ?>"
                                     data-date="<?= htmlspecialchars($dateKey) ?>"
                                     data-hours="<?= $actHours ?>"
                                     data-activity-type="<?= htmlspecialchars($typeCode) ?>"
                                     data-description="<?= htmlspecialchars($actDesc, ENT_QUOTES) ?>"
                                     data-phase="<?= htmlspecialchars((string) ($act['phase'] ?? ''), ENT_QUOTES) ?>"
                                     data-subphase="<?= htmlspecialchars((string) ($act['subphase'] ?? ''), ENT_QUOTES) ?>"
                                     data-has-blocker="<?= !empty($act['has_blocker']) ? '1' : '0' ?>"
                                     data-blocker-description="<?= htmlspecialchars((string) ($act['blocker_description'] ?? ''), ENT_QUOTES) ?>"
                                     data-has-significant-progress="<?= !empty($act['has_significant_progress']) ? '1' : '0' ?>"
                                     data-has-deliverable="<?= !empty($act['has_deliverable']) ? '1' : '0' ?>"
                                     data-operational-comment="<?= htmlspecialchars((string) ($act['operational_comment'] ?? ''), ENT_QUOTES) ?>"
                                     data-comment="<?= htmlspecialchars((string) ($act['comment'] ?? ''), ENT_QUOTES) ?>"
                                     style="border-left-color: <?= htmlspecialchars($typeColor) ?>">
                                    <div class="ts-act-top">
                                        <span class="ts-act-type" style="color:<?= htmlspecialchars($typeColor) ?>"><?= $typeIcon ?> <?= htmlspecialchars($typeName) ?></span>
                                        <span class="ts-act-hours"><?= round($actHours, 1) ?>h</span>
                                    </div>
                                    <div class="ts-act-project"><?= htmlspecialchars($actProject) ?></div>
                                    <?php if ($actDesc !== ''): ?>
                                    <div class="ts-act-desc"><?= htmlspecialchars(mb_strimwidth($actDesc, 0, 80, '…')) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($act['has_blocker'])): ?>
                                    <span class="ts-act-flag blocker">Bloqueo</span>
                                    <?php endif; ?>
                                    <?php if (!empty($act['has_significant_progress'])): ?>
                                    <span class="ts-act-flag progress">Avance</span>
                                    <?php endif; ?>
                                    <?php if (!empty($act['has_deliverable'])): ?>
                                    <span class="ts-act-flag deliverable">Entregable</span>
                                    <?php endif; ?>
                                    <span class="ts-act-status-dot <?= htmlspecialchars($actStatus) ?>"></span>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <button type="button" class="ts-add-day-btn" data-date="<?= htmlspecialchars($dateKey) ?>" title="Agregar actividad">+</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Week History -->
            <section class="ts-card ts-weeks-history">
                <h3>Historial de semanas</h3>
                <div class="ts-weeks-row">
                    <?php foreach ($weeksHistory as $week):
                        $start = new DateTimeImmutable((string) ($week['week_start'] ?? 'now'));
                        $end = new DateTimeImmutable((string) ($week['week_end'] ?? 'now'));
                        $statusWeight = (int) ($week['status_weight'] ?? 2);
                        $status = $statusWeight >= 5 ? 'approved' : ($statusWeight >= 4 ? 'rejected' : ($statusWeight >= 3 ? 'submitted' : 'draft'));
                        $isCurrent = $start->format('Y-m-d') === $weekStart->format('Y-m-d');
                        $meta = $statusMap[$status] ?? $statusMap['draft'];
                    ?>
                    <a class="ts-week-pill <?= $meta['class'] ?> <?= $isCurrent ? 'active' : '' ?>"
                       href="<?= $basePath ?>/timesheets?week=<?= urlencode($start->format('o-\\WW')) ?>">
                        <strong>S<?= htmlspecialchars($start->format('W')) ?></strong>
                        <span><?= htmlspecialchars($start->format('d/m')) ?> – <?= htmlspecialchars($end->format('d/m')) ?></span>
                        <span><?= round((float) ($week['total_hours'] ?? 0), 1) ?>h</span>
                        <span class="ts-badge <?= $meta['class'] ?>"><?= htmlspecialchars($meta['label']) ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Legacy Grid (collapsible) -->
            <?php if ($canReport && !empty($gridRows)): ?>
            <details class="ts-card ts-legacy-grid">
                <summary>Vista de grilla clásica</summary>
                <div class="ts-grid-inner">
                    <div class="ts-grid-actions">
                        <form method="POST" action="<?= $basePath ?>/timesheets/submit-week" class="ts-inline">
                            <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                            <button type="submit" class="ts-btn ts-btn-success" <?= $weekIsLocked ? 'disabled' : '' ?>>Enviar semana</button>
                        </form>
                    </div>
                    <div class="table-wrap"><table class="clean-table week-grid modern-week-grid"><thead><tr><th>Proyecto</th><?php foreach ($gridDays as $day): ?><th><?= htmlspecialchars($day['label']) ?><br><small><?= htmlspecialchars($day['number']) ?></small></th><?php endforeach; ?><th>Total</th></tr></thead><tbody>
                    <?php foreach ($gridRows as $row): ?><tr><td class="project-label"><?= htmlspecialchars((string) ($row['project'] ?? '')) ?></td><?php foreach ($gridDays as $day): $date=$day['key']; $cell=$row['cells'][$date] ?? ['hours'=>0,'status'=>'draft','comment'=>'']; $hours=(float)($cell['hours']??0); $cellStatus=(string)($cell['status']??'draft'); $canEditCell=$cellStatus==='draft'; ?><td class="cell status-<?= htmlspecialchars($cellStatus) ?> <?= $canEditCell ? '' : 'locked' ?>"><div class="cell-editor"><input type="number" step="0.25" min="0" max="24" value="<?= htmlspecialchars(rtrim(rtrim(number_format($hours,2,'.',''),'0'),'.')) ?>" data-date="<?= htmlspecialchars($date) ?>" data-project="<?= (int)($row['project_id']??0) ?>" data-comment="<?= htmlspecialchars((string)($cell['comment']??''),ENT_QUOTES) ?>" data-status="<?= htmlspecialchars($cellStatus) ?>" class="hour-input" <?= $canEditCell ? '' : 'disabled' ?>></div></td><?php endforeach; ?><td><strong class="row-total"><?= round((float)($row['total']??0),2) ?></strong></td></tr><?php endforeach; ?>
                    <tr class="total-row"><td><strong>Total</strong></td><?php foreach ($gridDays as $day): $total=(float)($dayTotals[$day['key']]??0); ?><td><strong class="day-total" data-day-total="<?= htmlspecialchars($day['key']) ?>"><?= round($total,2) ?></strong></td><?php endforeach; ?><td><strong id="week-total-footer"><?= round($weekTotal,2) ?></strong></td></tr>
                    </tbody></table></div>
                </div>
            </details>
            <?php endif; ?>

            <!-- Manager view -->
            <?php if ($canApprove || $canManageAdvanced): ?>
            <section class="ts-card" id="manager-view">
                <h3>Control gerencial operativo</h3>
                <p class="ts-muted">Aprobación, edición controlada y auditoría.</p>
                <form method="GET" class="ts-filter-row" style="margin-bottom:12px;">
                    <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                    <label class="ts-field">Talento
                        <select name="talent_id">
                            <option value="0">Todos</option>
                            <?php foreach ($talentOptions as $tal): ?>
                                <option value="<?= (int)($tal['id']??0) ?>" <?= $talentFilter === (int)($tal['id']??0) ? 'selected' : '' ?>><?= htmlspecialchars((string)($tal['name']??'')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="ts-btn ts-btn-primary">Filtrar</button>
                </form>
                <div class="table-wrap"><table class="clean-table"><thead><tr><th>Fecha</th><th>Talento</th><th>Proyecto</th><th>Tipo</th><th>Horas</th><th>Descripción</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
                <?php foreach ($managedWeekEntries as $entry): ?>
                <tr>
                    <td><?= htmlspecialchars((string)($entry['date']??'')) ?></td>
                    <td><?= htmlspecialchars((string)($entry['talent_name']??$entry['user_name']??'')) ?></td>
                    <td><?= htmlspecialchars((string)($entry['project_name']??'')) ?></td>
                    <td><?= htmlspecialchars((string)($entry['activity_type'] ?? '—')) ?></td>
                    <td><?= round((float)($entry['hours']??0),2) ?></td>
                    <td><?= htmlspecialchars(mb_strimwidth((string)($entry['description'] ?? $entry['comment'] ?? ''), 0, 60, '…')) ?></td>
                    <td><span class="ts-badge <?= htmlspecialchars((string)($entry['status']??'draft')) ?>"><?= htmlspecialchars((string)($entry['status']??'draft')) ?></span></td>
                    <td class="ts-action-cell">
                        <?php if ($canApprove && in_array((string)($entry['status']??''), ['submitted','pending','pending_approval'], true)): ?>
                            <form method="POST" action="<?= $basePath ?>/timesheets/<?= (int)($entry['id']??0) ?>/approve" class="ts-inline"><button type="submit" class="ts-btn ts-btn-sm ts-btn-success">Aprobar</button></form>
                            <form method="POST" action="<?= $basePath ?>/timesheets/<?= (int)($entry['id']??0) ?>/reject" class="ts-inline"><input type="text" name="comment" placeholder="Motivo" required class="ts-input-sm"><button type="submit" class="ts-btn ts-btn-sm ts-btn-danger">Rechazar</button></form>
                        <?php endif; ?>
                        <?php if ($canManageAdvanced): ?>
                            <form method="POST" action="<?= $basePath ?>/timesheets/admin-action" class="ts-inline">
                                <input type="hidden" name="admin_action" value="update_hours"><input type="hidden" name="timesheet_id" value="<?= (int)($entry['id']??0) ?>"><input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                                <input type="number" step="0.25" min="0" max="24" name="hours" value="<?= round((float)($entry['hours']??0),2) ?>" required class="ts-input-sm" style="width:60px;">
                                <input type="text" name="reason" placeholder="Motivo" required class="ts-input-sm">
                                <button type="submit" class="ts-btn ts-btn-sm">Editar</button>
                            </form>
                            <form method="POST" action="<?= $basePath ?>/timesheets/admin-action" class="ts-inline">
                                <input type="hidden" name="admin_action" value="delete_entry"><input type="hidden" name="timesheet_id" value="<?= (int)($entry['id']??0) ?>"><input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                                <input type="text" name="reason" placeholder="Motivo" required class="ts-input-sm">
                                <button type="submit" class="ts-btn ts-btn-sm ts-btn-danger">Eliminar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody></table></div>

                <?php if ($canManageAdvanced): ?>
                <div class="ts-admin-actions">
                    <div class="ts-admin-block">
                        <h4>Reapertura controlada</h4>
                        <form method="POST" action="<?= $basePath ?>/timesheets/admin-action" class="ts-filter-row">
                            <input type="hidden" name="admin_action" value="reopen_week">
                            <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                            <input type="hidden" name="week_start" value="<?= htmlspecialchars($weekStart->format('Y-m-d')) ?>">
                            <label class="ts-field">Talento<select name="talent_id"><option value="0">Toda la semana</option><?php foreach ($talentOptions as $tal): ?><option value="<?= (int)($tal['id']??0) ?>"><?= htmlspecialchars((string)($tal['name']??'')) ?></option><?php endforeach; ?></select></label>
                            <label class="ts-field">Motivo<input type="text" name="reason" required placeholder="Ej: corrección"></label>
                            <button type="submit" class="ts-btn">Reabrir</button>
                        </form>
                    </div>
                    <div class="ts-admin-block">
                        <h4>Eliminación masiva</h4>
                        <form method="POST" action="<?= $basePath ?>/timesheets/admin-action" class="ts-filter-row">
                            <input type="hidden" name="admin_action" value="delete_week">
                            <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                            <input type="hidden" name="week_start" value="<?= htmlspecialchars($weekStart->format('Y-m-d')) ?>">
                            <label class="ts-field">Talento<select name="talent_id"><option value="0">Toda la semana</option><?php foreach ($talentOptions as $tal): ?><option value="<?= (int)($tal['id']??0) ?>"><?= htmlspecialchars((string)($tal['name']??'')) ?></option><?php endforeach; ?></select></label>
                            <label class="ts-field">Motivo<input type="text" name="reason" required></label>
                            <label class="ts-field">Confirmación<input type="text" name="confirm_token" required placeholder="ELIMINAR MASIVO"></label>
                            <button type="submit" class="ts-btn ts-btn-danger">Eliminar</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <!-- Executive sections -->
            <section class="ts-card">
                <h3>Análisis del periodo</h3>
                <form method="GET" class="ts-filter-row" style="margin-bottom:12px;">
                    <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                    <label class="ts-field">Periodo
                        <select name="period" id="period-select">
                            <option value="month" <?= $periodType==='month' ? 'selected' : '' ?>>Mes</option>
                            <option value="custom" <?= $periodType==='custom' ? 'selected' : '' ?>>Rango</option>
                        </select>
                    </label>
                    <label class="ts-field">Desde<input type="date" name="range_start" value="<?= htmlspecialchars($periodStart->format('Y-m-d')) ?>" <?= $periodType!=='custom' ? 'disabled' : '' ?> data-range-input></label>
                    <label class="ts-field">Hasta<input type="date" name="range_end" value="<?= htmlspecialchars($periodEnd->format('Y-m-d')) ?>" <?= $periodType!=='custom' ? 'disabled' : '' ?> data-range-input></label>
                    <label class="ts-field">Proyecto
                        <select name="project_id">
                            <option value="0">Todos</option>
                            <?php foreach ($projectsForFilter as $p): ?>
                                <option value="<?= (int)($p['project_id']??0) ?>" <?= $projectFilter===(int)($p['project_id']??0) ? 'selected' : '' ?>><?= htmlspecialchars((string)($p['project']??'')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="ts-btn ts-btn-primary">Aplicar</button>
                </form>

                <!-- Approved weeks table -->
                <h4>Semanas del periodo</h4>
                <p class="ts-muted">Registradas: <?= $weeksRegistered ?> · Aprobadas: <?= $weeksApproved ?> · Pendientes: <?= $weeksPending ?></p>
                <div class="table-wrap">
                <table class="clean-table"><thead><tr><th>Semana</th><th>Rango</th><th>Horas</th><th>Estado</th><th>Aprobación</th><th>Aprobador</th></tr></thead><tbody>
                <?php foreach ($approvedWeeks as $week):
                    $start = new DateTimeImmutable((string)($week['week_start']??'now'));
                    $end = new DateTimeImmutable((string)($week['week_end']??'now'));
                    $weight = (int)($week['status_weight']??0);
                    $state = $weight>=5 ? 'approved' : ($weight>=4 ? 'rejected' : 'submitted');
                    $meta = $statusMap[$state] ?? $statusMap['draft'];
                ?>
                <tr>
                    <td><a href="<?= $basePath ?>/timesheets?week=<?= urlencode($start->format('o-\\WW')) ?>">S<?= htmlspecialchars($start->format('W')) ?></a></td>
                    <td><?= htmlspecialchars($start->format('d/m')) ?> – <?= htmlspecialchars($end->format('d/m/Y')) ?></td>
                    <td><?= round((float)($week['total_hours']??0),1) ?>h</td>
                    <td><span class="ts-badge <?= $meta['class'] ?>"><?= htmlspecialchars($meta['label']) ?></span></td>
                    <td><?= htmlspecialchars((string)($week['approved_at']??'—')) ?></td>
                    <td><?= htmlspecialchars((string)($week['approver_name']??'—')) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody></table></div>
            </section>

            <!-- Talent breakdown -->
            <section class="ts-card">
                <h3>Desglose por talento</h3>
                <form method="GET" class="ts-inline" style="margin-bottom:8px;">
                    <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                    <input type="hidden" name="period" value="<?= htmlspecialchars($periodType) ?>">
                    <input type="hidden" name="range_start" value="<?= htmlspecialchars($periodStart->format('Y-m-d')) ?>">
                    <input type="hidden" name="range_end" value="<?= htmlspecialchars($periodEnd->format('Y-m-d')) ?>">
                    <input type="hidden" name="project_id" value="<?= $projectFilter ?>">
                    <select name="talent_sort" onchange="this.form.submit()">
                        <option value="load_desc" <?= $talentSort==='load_desc' ? 'selected' : '' ?>>Mayor carga</option>
                        <option value="compliance_asc" <?= $talentSort==='compliance_asc' ? 'selected' : '' ?>>Menor cumplimiento</option>
                    </select>
                </form>
                <div class="table-wrap">
                <table class="clean-table"><thead><tr><th>Talento</th><th>Total</th><th>Aprobadas</th><th>Rechazadas</th><th>Borrador</th><th>Cumplimiento</th></tr></thead><tbody>
                <?php foreach ($talentBreakdown as $r): ?>
                <tr>
                    <td><?= htmlspecialchars((string)($r['talent_name']??'')) ?></td>
                    <td><?= round((float)($r['total_hours']??0),1) ?>h</td>
                    <td><?= round((float)($r['approved_hours']??0),1) ?>h</td>
                    <td><?= round((float)($r['rejected_hours']??0),1) ?>h</td>
                    <td><?= round((float)($r['draft_hours']??0),1) ?>h</td>
                    <td>
                        <?php $cp = (float)($r['compliance_percent']??0); ?>
                        <div class="ts-progress-mini"><div class="ts-progress-fill <?= $cp >= 80 ? 'good' : ($cp >= 50 ? 'mid' : 'low') ?>" style="width:<?= min(100,$cp) ?>%"></div></div>
                        <?= round($cp,1) ?>%
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody></table></div>
            </section>
        </div>

        <!-- ═══ Sidebar ═══ -->
        <aside class="ts-sidebar">
            <!-- Month summary -->
            <div class="ts-card">
                <h4>Resumen mensual</h4>
                <ul class="ts-summary-list">
                    <li>Total mes <strong><?= round((float)($monthlySummary['month_total']??0),1) ?>h</strong></li>
                    <li>Aprobadas <strong><?= round((float)($monthlySummary['approved']??0),1) ?>h</strong></li>
                    <li>Rechazadas <strong><?= round((float)($monthlySummary['rejected']??0),1) ?>h</strong></li>
                    <li>Borrador <strong><?= round((float)($monthlySummary['draft']??0),1) ?>h</strong></li>
                    <li>Capacidad <strong><?= round((float)($monthlySummary['capacity']??0),1) ?>h</strong></li>
                    <li>Cumplimiento <strong><?= (float)($monthlySummary['compliance']??0) ?>%</strong></li>
                </ul>
            </div>

            <!-- Activity Type Distribution -->
            <?php if (!empty($activityTypeBreakdown)): ?>
            <div class="ts-card">
                <h4>Distribución por tipo de actividad</h4>
                <div class="ts-type-dist">
                    <?php foreach ($activityTypeBreakdown as $bd):
                        $typeCode = (string)($bd['activity_type'] ?? 'sin_tipo');
                        $typeInfo = $activityTypeMap[$typeCode] ?? null;
                        $typeName = $typeInfo ? $typeInfo['name'] : ($typeCode === 'sin_tipo' ? 'Sin tipo' : $typeCode);
                        $typeColor = $typeInfo ? $typeInfo['color'] : '#94a3b8';
                        $bdHours = (float)($bd['total_hours'] ?? 0);
                        $bdPct = $totalBreakdownHours > 0 ? round(($bdHours / $totalBreakdownHours) * 100, 1) : 0;
                    ?>
                    <div class="ts-type-row">
                        <div class="ts-type-bar-wrap">
                            <div class="ts-type-bar" style="width:<?= $bdPct ?>%;background:<?= htmlspecialchars($typeColor) ?>"></div>
                        </div>
                        <span class="ts-type-name"><?= htmlspecialchars($typeName) ?></span>
                        <span class="ts-type-val"><?= round($bdHours,1) ?>h (<?= $bdPct ?>%)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top talent & projects -->
            <div class="ts-card">
                <h4>Mayor carga (talento)</h4>
                <?php if (!empty($talentTopLoaded)): ?>
                    <?php foreach ($talentTopLoaded as $tl): ?>
                    <div class="ts-rank-item"><span><?= htmlspecialchars((string)($tl['talent_name']??'')) ?></span><strong><?= round((float)($tl['total_hours']??0),1) ?>h</strong></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="ts-muted">Sin datos</p>
                <?php endif; ?>
            </div>

            <div class="ts-card">
                <h4>Mayor consumo (proyecto)</h4>
                <?php if (!empty($projectTopConsuming)): ?>
                    <?php foreach ($projectTopConsuming as $pc): ?>
                    <div class="ts-rank-item"><span><?= htmlspecialchars((string)($pc['project_name']??'')) ?></span><strong><?= round((float)($pc['total_hours']??0),1) ?>h</strong></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="ts-muted">Sin datos</p>
                <?php endif; ?>
            </div>

            <!-- Recent activities for autocomplete -->
            <?php if ($canReport && !empty($recentActivities)): ?>
            <div class="ts-card">
                <h4>Actividades recientes</h4>
                <div class="ts-recent-list">
                    <?php foreach ($recentActivities as $ra): ?>
                    <button type="button" class="ts-recent-item"
                            data-project-id="<?= (int)($ra['project_id']??0) ?>"
                            data-activity-type="<?= htmlspecialchars((string)($ra['activity_type']??'')) ?>"
                            data-description="<?= htmlspecialchars((string)($ra['description']??''), ENT_QUOTES) ?>">
                        <span class="ts-recent-proj"><?= htmlspecialchars((string)($ra['project_name']??'')) ?></span>
                        <span class="ts-recent-desc"><?= htmlspecialchars(mb_strimwidth((string)($ra['description']??''), 0, 40, '…')) ?></span>
                        <span class="ts-recent-hours"><?= round((float)($ra['hours']??0),1) ?>h</span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Week action history -->
            <div class="ts-card">
                <h4>Historial semana actual</h4>
                <?php if (!empty($weekHistoryLog)): ?>
                <ul class="ts-history-log">
                    <?php foreach ($weekHistoryLog as $ev): ?>
                    <li>
                        <strong><?= htmlspecialchars((string)($ev['action']??'')) ?></strong>
                        <span><?= htmlspecialchars((string)($ev['actor_name']??'Sistema')) ?></span>
                        <small><?= htmlspecialchars((string)($ev['created_at']??'')) ?></small>
                        <?php if (!empty($ev['action_comment'])): ?><em><?= htmlspecialchars((string)$ev['action_comment']) ?></em><?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p class="ts-muted">Sin eventos</p>
                <?php endif; ?>
            </div>

            <button class="ts-btn ts-btn-outline" onclick="window.print()" style="width:100%">Imprimir informe</button>
        </aside>
    </div>
</section>

<!-- ═══ Activity Modal ═══ -->
<dialog id="activity-modal" class="ts-modal">
    <form id="activity-form" class="ts-modal-body">
        <header class="ts-modal-header">
            <h3 id="modal-title">Registrar actividad</h3>
            <button type="button" class="ts-modal-close" id="modal-close">&times;</button>
        </header>

        <div class="ts-modal-grid">
            <div class="ts-modal-col">
                <label class="ts-field">
                    Proyecto *
                    <select name="project_id" id="act-project" required>
                        <option value="">Seleccionar proyecto</option>
                        <?php foreach ($projectsForTimesheet as $p): ?>
                            <option value="<?= (int)($p['project_id']??0) ?>"><?= htmlspecialchars((string)($p['project']??'')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="ts-field">
                    Fecha *
                    <input type="date" name="date" id="act-date" required value="<?= htmlspecialchars((new DateTimeImmutable())->format('Y-m-d')) ?>">
                </label>

                <label class="ts-field">
                    Tipo de actividad *
                    <select name="activity_type" id="act-type" required>
                        <option value="">Seleccionar tipo</option>
                        <?php foreach ($activityTypes as $at): ?>
                            <option value="<?= htmlspecialchars((string)($at['code']??'')) ?>"><?= htmlspecialchars((string)($at['icon']??'')) ?> <?= htmlspecialchars((string)($at['name']??'')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="ts-field">
                    Horas *
                    <input type="number" name="hours" id="act-hours" step="0.25" min="0.25" max="24" required placeholder="2">
                </label>

                <label class="ts-field">
                    Descripción breve *
                    <textarea name="description" id="act-description" rows="2" required placeholder="Ej: Integración endpoint consulta facturas"></textarea>
                </label>
            </div>

            <div class="ts-modal-col">
                <label class="ts-field">
                    Fase del proyecto
                    <select name="phase" id="act-phase"><option value="">Sin fase</option></select>
                </label>

                <label class="ts-field">
                    Subfase
                    <input type="text" name="subphase" id="act-subphase" placeholder="Opcional">
                </label>

                <div class="ts-toggle-group">
                    <label class="ts-toggle">
                        <input type="checkbox" name="has_blocker" id="act-has-blocker">
                        <span>Hubo bloqueo</span>
                    </label>
                    <div id="blocker-detail" class="ts-conditional" style="display:none">
                        <label class="ts-field">
                            Descripción del bloqueo
                            <textarea name="blocker_description" id="act-blocker-desc" rows="2" placeholder="Ej: acceso API cliente pendiente"></textarea>
                        </label>
                    </div>
                </div>

                <div class="ts-toggle-group">
                    <label class="ts-toggle">
                        <input type="checkbox" name="has_significant_progress" id="act-has-progress">
                        <span>Avance significativo</span>
                    </label>
                </div>

                <div class="ts-toggle-group">
                    <label class="ts-toggle">
                        <input type="checkbox" name="has_deliverable" id="act-has-deliverable">
                        <span>Se generó entregable</span>
                    </label>
                </div>

                <label class="ts-field">
                    Comentario operativo
                    <textarea name="operational_comment" id="act-op-comment" rows="2" placeholder="Observaciones adicionales"></textarea>
                </label>
            </div>
        </div>

        <input type="hidden" name="entry_id" id="act-entry-id" value="">

        <footer class="ts-modal-footer">
            <button type="button" class="ts-btn ts-btn-outline" id="modal-cancel">Cancelar</button>
            <button type="button" class="ts-btn ts-btn-danger" id="modal-delete" style="display:none">Eliminar</button>
            <button type="submit" class="ts-btn ts-btn-primary" id="modal-save">Guardar actividad</button>
        </footer>
    </form>
</dialog>

<style>
:root{--ts-bg:#f8fafc;--ts-card:#fff;--ts-border:#e2e8f0;--ts-text:#1e293b;--ts-muted:#64748b;--ts-primary:#2563eb;--ts-success:#16a34a;--ts-warning:#eab308;--ts-danger:#dc2626;--ts-radius:12px}
.ts-shell{display:flex;flex-direction:column;gap:16px;max-width:1440px;margin:0 auto}

/* Header */
.ts-top-header{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;background:var(--ts-card);border:1px solid var(--ts-border);border-radius:var(--ts-radius);padding:16px 20px}
.ts-title-block h2{margin:0;font-size:1.5rem;color:var(--ts-text)}.ts-subtitle{margin:2px 0 0;color:var(--ts-muted);font-size:.85rem}
.ts-week-nav{display:flex;align-items:center;gap:8px}
.ts-nav-btn{width:36px;height:36px;display:flex;align-items:center;justify-content:center;border:1px solid var(--ts-border);border-radius:8px;background:var(--ts-card);color:var(--ts-text);text-decoration:none;font-size:1.4rem;font-weight:700;transition:background .15s}
.ts-nav-btn:hover{background:#f1f5f9}
.ts-week-label{text-align:center}.ts-week-label strong{display:block;font-size:1rem}.ts-week-label span{font-size:.82rem;color:var(--ts-muted)}
.ts-week-picker input{border:1px solid var(--ts-border);border-radius:8px;padding:6px 10px;font-size:.85rem}

/* Capacity Bar */
.ts-capacity-bar{display:flex;gap:12px;flex-wrap:wrap;background:var(--ts-card);border:1px solid var(--ts-border);border-radius:var(--ts-radius);padding:14px 20px;align-items:center}
.ts-cap-item{display:flex;flex-direction:column;gap:2px;min-width:100px}.ts-cap-label{font-size:.75rem;color:var(--ts-muted);text-transform:uppercase;letter-spacing:.5px}.ts-cap-item strong{font-size:1.1rem}
.ts-cap-progress{flex:1;min-width:200px}.ts-progress-wrap{display:flex;align-items:center;gap:8px}
.ts-progress-bar{flex:1;height:8px;background:#e2e8f0;border-radius:99px;overflow:hidden}.ts-progress-fill{height:100%;border-radius:99px;transition:width .3s}
.ts-progress-bar.over .ts-progress-fill{background:var(--ts-danger)}.ts-progress-bar.good .ts-progress-fill{background:var(--ts-success)}.ts-progress-bar.mid .ts-progress-fill{background:var(--ts-warning)}.ts-progress-bar.low .ts-progress-fill{background:#94a3b8}

/* KPI */
.ts-kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}
.ts-kpi{display:flex;align-items:center;gap:12px;background:var(--ts-card);border:1px solid var(--ts-border);border-radius:var(--ts-radius);padding:14px 16px}
.ts-kpi-icon{font-size:1.5rem;opacity:.8}.ts-kpi-label{font-size:.75rem;color:var(--ts-muted);text-transform:uppercase}.ts-kpi strong{font-size:1.2rem;display:block}.ts-kpi small{color:var(--ts-muted);font-size:.78rem}
.ts-kpi.approved{border-left:4px solid var(--ts-success)}.ts-kpi.rejected{border-left:4px solid var(--ts-danger)}.ts-kpi.pending{border-left:4px solid var(--ts-warning)}.ts-kpi.draft{border-left:4px solid #94a3b8}

/* Main layout */
.ts-main-layout{display:grid;grid-template-columns:1fr 320px;gap:16px}
.ts-main-col{display:flex;flex-direction:column;gap:16px}
.ts-sidebar{display:flex;flex-direction:column;gap:12px}

/* Card */
.ts-card{background:var(--ts-card);border:1px solid var(--ts-border);border-radius:var(--ts-radius);padding:16px 20px}
.ts-card h3{margin:0 0 8px;font-size:1.1rem}.ts-card h4{margin:12px 0 6px;font-size:.95rem}
.ts-muted{color:var(--ts-muted);font-size:.85rem;margin:0}

/* Badge */
.ts-badge{font-size:11px;font-weight:700;padding:2px 10px;border-radius:99px;display:inline-block}
.ts-badge.approved{background:#dcfce7;color:#166534}.ts-badge.rejected{background:#fee2e2;color:#991b1b}.ts-badge.submitted,.ts-badge.pending,.ts-badge.pending_approval{background:#fef3c7;color:#92400e}.ts-badge.draft{background:#f1f5f9;color:#475569}.ts-badge.partial{background:#dbeafe;color:#1e40af}

/* Calendar */
.ts-calendar-section{background:var(--ts-card);border:1px solid var(--ts-border);border-radius:var(--ts-radius);padding:16px 20px;overflow:hidden}
.ts-calendar-header{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.ts-calendar-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.ts-calendar-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;min-height:260px}
.ts-day-col{border:1px solid var(--ts-border);border-radius:10px;display:flex;flex-direction:column;min-width:0;background:#fafbfc;transition:background .15s}
.ts-day-col.ts-today{background:#eff6ff;border-color:#93c5fd}
.ts-day-header{display:flex;flex-direction:column;align-items:center;padding:8px 4px;border-bottom:1px solid var(--ts-border);gap:2px}
.ts-day-name{font-size:.75rem;font-weight:700;text-transform:uppercase;color:var(--ts-muted)}.ts-day-number{font-size:1.2rem;font-weight:700;color:var(--ts-text)}.ts-day-total{font-size:.78rem;color:var(--ts-primary);font-weight:600}
.ts-day-body{flex:1;padding:6px;display:flex;flex-direction:column;gap:6px;overflow-y:auto;max-height:400px}
.ts-day-empty{font-size:.78rem;color:#cbd5e1;text-align:center;padding:12px 0}

/* Activity card */
.ts-activity-card{border-left:4px solid #94a3b8;background:var(--ts-card);border-radius:8px;padding:8px 10px;cursor:pointer;transition:box-shadow .15s,transform .1s;position:relative;font-size:.82rem}
.ts-activity-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.08);transform:translateY(-1px)}
.ts-act-top{display:flex;justify-content:space-between;align-items:center;gap:4px}.ts-act-type{font-size:.72rem;font-weight:600}.ts-act-hours{font-weight:700;font-size:.85rem;color:var(--ts-text)}
.ts-act-project{font-weight:600;font-size:.78rem;color:var(--ts-text);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ts-act-desc{font-size:.75rem;color:var(--ts-muted);margin-top:2px;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.ts-act-flag{font-size:.65rem;font-weight:700;padding:1px 6px;border-radius:99px;display:inline-block;margin-top:3px}
.ts-act-flag.blocker{background:#fee2e2;color:#991b1b}.ts-act-flag.progress{background:#dcfce7;color:#166534}.ts-act-flag.deliverable{background:#dbeafe;color:#1e40af}
.ts-act-status-dot{position:absolute;top:6px;right:6px;width:7px;height:7px;border-radius:50%}
.ts-act-status-dot.draft{background:#94a3b8}.ts-act-status-dot.submitted,.ts-act-status-dot.pending{background:var(--ts-warning)}.ts-act-status-dot.approved{background:var(--ts-success)}.ts-act-status-dot.rejected{background:var(--ts-danger)}
.status-approved{opacity:.85}.status-rejected{background:#fff5f5}

.ts-add-day-btn{border:2px dashed #cbd5e1;background:transparent;border-radius:8px;padding:6px;color:#94a3b8;font-size:1.2rem;cursor:pointer;transition:all .15s;margin-top:auto}
.ts-add-day-btn:hover{border-color:var(--ts-primary);color:var(--ts-primary);background:#eff6ff}

/* Weeks history */
.ts-weeks-row{display:flex;gap:8px;overflow-x:auto;padding-bottom:6px}
.ts-week-pill{min-width:140px;display:flex;flex-direction:column;gap:2px;border:1px solid var(--ts-border);border-radius:10px;padding:8px 12px;text-decoration:none;color:inherit;font-size:.82rem;transition:all .15s}
.ts-week-pill:hover{box-shadow:0 1px 4px rgba(0,0,0,.06)}.ts-week-pill.active{outline:2px solid var(--ts-primary);outline-offset:-1px}
.ts-week-pill.approved{border-left:5px solid var(--ts-success)}.ts-week-pill.rejected{border-left:5px solid var(--ts-danger)}.ts-week-pill.submitted{border-left:5px solid var(--ts-warning)}.ts-week-pill.draft{border-left:5px solid #94a3b8}

/* Buttons */
.ts-btn{border:1px solid var(--ts-border);background:var(--ts-card);color:var(--ts-text);border-radius:8px;padding:7px 14px;font-size:.85rem;font-weight:600;cursor:pointer;transition:all .15s;white-space:nowrap}
.ts-btn:hover{background:#f1f5f9}.ts-btn:disabled{opacity:.4;cursor:not-allowed}
.ts-btn-primary{background:var(--ts-primary);color:#fff;border-color:var(--ts-primary)}.ts-btn-primary:hover{background:#1d4ed8}
.ts-btn-success{background:var(--ts-success);color:#fff;border-color:var(--ts-success)}.ts-btn-success:hover{background:#15803d}
.ts-btn-danger{background:var(--ts-danger);color:#fff;border-color:var(--ts-danger)}.ts-btn-danger:hover{background:#b91c1c}
.ts-btn-outline{background:transparent;border:1px solid var(--ts-border)}.ts-btn-outline:hover{background:#f8fafc}
.ts-btn-sm{font-size:.78rem;padding:4px 10px}
.ts-inline{display:inline-flex;align-items:center;gap:6px}
.ts-input-sm{border:1px solid var(--ts-border);border-radius:6px;padding:4px 8px;font-size:.82rem}

/* Filter/field */
.ts-filter-row{display:flex;gap:10px;align-items:end;flex-wrap:wrap}
.ts-field{display:flex;flex-direction:column;gap:3px;font-size:.82rem;color:var(--ts-muted);font-weight:600}
.ts-field select,.ts-field input,.ts-field textarea{border:1px solid var(--ts-border);border-radius:8px;padding:7px 10px;font-size:.85rem;color:var(--ts-text);background:var(--ts-card);width:100%}

/* Summary list */
.ts-summary-list{margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px;font-size:.85rem}
.ts-summary-list li{display:flex;justify-content:space-between}

/* Type distribution */
.ts-type-dist{display:flex;flex-direction:column;gap:8px}
.ts-type-row{display:flex;flex-direction:column;gap:2px;font-size:.82rem}
.ts-type-bar-wrap{height:6px;background:#e2e8f0;border-radius:99px;overflow:hidden}
.ts-type-bar{height:100%;border-radius:99px;transition:width .3s}
.ts-type-name{font-weight:600;color:var(--ts-text)}.ts-type-val{color:var(--ts-muted);font-size:.78rem}

/* Rank items */
.ts-rank-item{display:flex;justify-content:space-between;font-size:.85rem;padding:4px 0;border-bottom:1px solid #f1f5f9}
.ts-rank-item:last-child{border-bottom:none}

/* Recent activities */
.ts-recent-list{display:flex;flex-direction:column;gap:4px}
.ts-recent-item{background:#f8fafc;border:1px solid var(--ts-border);border-radius:8px;padding:8px 10px;cursor:pointer;text-align:left;width:100%;font-size:.8rem;display:flex;flex-direction:column;gap:2px;transition:all .15s}
.ts-recent-item:hover{background:#eff6ff;border-color:#93c5fd}
.ts-recent-proj{font-weight:600;color:var(--ts-text)}.ts-recent-desc{color:var(--ts-muted)}.ts-recent-hours{font-weight:700;color:var(--ts-primary);font-size:.75rem}

/* History log */
.ts-history-log{margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px;font-size:.82rem}
.ts-history-log li{padding:6px 0;border-bottom:1px solid #f1f5f9;display:flex;flex-direction:column;gap:2px}
.ts-history-log li:last-child{border-bottom:none}
.ts-history-log small{color:var(--ts-muted)}.ts-history-log em{color:var(--ts-muted);font-style:normal;font-size:.78rem}

/* Progress mini */
.ts-progress-mini{height:5px;background:#e2e8f0;border-radius:99px;overflow:hidden;width:80px;display:inline-block;vertical-align:middle;margin-right:4px}
.ts-progress-mini .ts-progress-fill{height:100%;border-radius:99px}
.ts-progress-fill.good{background:var(--ts-success)}.ts-progress-fill.mid{background:var(--ts-warning)}.ts-progress-fill.low{background:#94a3b8}

/* Admin actions */
.ts-admin-actions{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px}
.ts-admin-block{background:#fafbfc;border:1px solid var(--ts-border);border-radius:10px;padding:12px}
.ts-admin-block h4{margin:0 0 8px;font-size:.9rem}
.ts-action-cell{display:flex;gap:4px;flex-wrap:wrap;align-items:center}

/* Legacy grid */
.ts-legacy-grid summary{cursor:pointer;font-weight:600;padding:4px 0;color:var(--ts-muted);font-size:.9rem}
.ts-legacy-grid summary:hover{color:var(--ts-text)}
.ts-grid-inner{margin-top:12px}.ts-grid-actions{margin-bottom:10px;display:flex;gap:8px}
.project-label{min-width:180px;font-weight:600;font-size:.85rem}
.cell-editor{display:flex;align-items:center;gap:4px}
.hour-input{width:56px;padding:4px;border:1px solid var(--ts-border);border-radius:6px;font-size:.85rem;text-align:center}
.cell.locked{background:#f0f9ff}.cell{padding:4px}
.status-approved{background:#f0fdf4}.status-rejected{background:#fff5f5}.status-submitted,.status-pending{background:#fefce8}

/* Modal */
.ts-modal{border:none;border-radius:16px;max-width:720px;width:95%;padding:0;box-shadow:0 20px 60px rgba(0,0,0,.18)}
.ts-modal::backdrop{background:rgba(15,23,42,.5)}
.ts-modal-body{display:flex;flex-direction:column;gap:0;padding:0}
.ts-modal-header{display:flex;justify-content:space-between;align-items:center;padding:16px 24px;border-bottom:1px solid var(--ts-border)}
.ts-modal-header h3{margin:0;font-size:1.15rem}
.ts-modal-close{background:none;border:none;font-size:1.6rem;cursor:pointer;color:var(--ts-muted);padding:0 4px}
.ts-modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;padding:20px 24px}
.ts-modal-col{display:flex;flex-direction:column;gap:12px}
.ts-modal-footer{display:flex;justify-content:flex-end;gap:8px;padding:14px 24px;border-top:1px solid var(--ts-border);background:#fafbfc;border-radius:0 0 16px 16px}

/* Toggles */
.ts-toggle-group{display:flex;flex-direction:column;gap:6px}
.ts-toggle{display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.85rem;font-weight:600;color:var(--ts-text)}
.ts-toggle input[type="checkbox"]{width:18px;height:18px;accent-color:var(--ts-primary)}
.ts-conditional{padding-left:26px}
.ts-reopen-form{gap:6px}

/* Responsive */
@media(max-width:1100px){.ts-main-layout{grid-template-columns:1fr}.ts-sidebar{order:-1;display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:12px}}
@media(max-width:768px){
    .ts-calendar-grid{grid-template-columns:1fr;gap:10px}
    .ts-day-col{flex-direction:row;flex-wrap:wrap;min-height:auto}
    .ts-day-header{flex-direction:row;border-bottom:none;border-right:1px solid var(--ts-border);padding:8px 12px;min-width:80px;gap:6px}
    .ts-day-body{flex-direction:row;flex-wrap:wrap;max-height:none;padding:8px}
    .ts-activity-card{min-width:160px;flex:1}
    .ts-kpi-grid{grid-template-columns:repeat(2,1fr)}
    .ts-modal-grid{grid-template-columns:1fr}
    .ts-top-header{flex-direction:column;align-items:flex-start}
    .ts-admin-actions{grid-template-columns:1fr}
}
@media print{.ts-calendar-actions,.ts-week-nav,.ts-btn,.ts-add-day-btn,.ts-recent-list,form,.ts-legacy-grid,.ts-reopen-form{display:none!important}.ts-modal{display:none!important}}
</style>

<script>
(() => {
  const basePath = '<?= $basePath ?>';
  const modal = document.getElementById('activity-modal');
  const form = document.getElementById('activity-form');
  const btnAdd = document.getElementById('btn-add-activity');
  const btnClose = document.getElementById('modal-close');
  const btnCancel = document.getElementById('modal-cancel');
  const btnDelete = document.getElementById('modal-delete');
  const btnSave = document.getElementById('modal-save');
  const modalTitle = document.getElementById('modal-title');
  const entryIdField = document.getElementById('act-entry-id');

  const projectSelect = document.getElementById('act-project');
  const dateInput = document.getElementById('act-date');
  const typeSelect = document.getElementById('act-type');
  const hoursInput = document.getElementById('act-hours');
  const descInput = document.getElementById('act-description');
  const phaseSelect = document.getElementById('act-phase');
  const subphaseInput = document.getElementById('act-subphase');
  const hasBlockerCb = document.getElementById('act-has-blocker');
  const blockerDetail = document.getElementById('blocker-detail');
  const blockerDescInput = document.getElementById('act-blocker-desc');
  const hasProgressCb = document.getElementById('act-has-progress');
  const hasDeliverableCb = document.getElementById('act-has-deliverable');
  const opCommentInput = document.getElementById('act-op-comment');

  if (!modal || !form) return;

  hasBlockerCb?.addEventListener('change', () => {
    blockerDetail.style.display = hasBlockerCb.checked ? 'block' : 'none';
  });

  function resetForm() {
    form.reset();
    entryIdField.value = '';
    blockerDetail.style.display = 'none';
    btnDelete.style.display = 'none';
    modalTitle.textContent = 'Registrar actividad';
    btnSave.textContent = 'Guardar actividad';
    phaseSelect.innerHTML = '<option value="">Sin fase</option>';
  }

  function openModal(defaults = {}) {
    resetForm();
    if (defaults.date) dateInput.value = defaults.date;
    if (defaults.project_id) projectSelect.value = defaults.project_id;
    if (defaults.activity_type) typeSelect.value = defaults.activity_type;
    if (defaults.hours) hoursInput.value = defaults.hours;
    if (defaults.description) descInput.value = defaults.description;
    if (defaults.subphase) subphaseInput.value = defaults.subphase;
    if (defaults.has_blocker) { hasBlockerCb.checked = true; blockerDetail.style.display = 'block'; }
    if (defaults.blocker_description) blockerDescInput.value = defaults.blocker_description;
    if (defaults.has_significant_progress) hasProgressCb.checked = true;
    if (defaults.has_deliverable) hasDeliverableCb.checked = true;
    if (defaults.operational_comment) opCommentInput.value = defaults.operational_comment;
    if (defaults.comment) {
      if (!descInput.value) descInput.value = defaults.comment;
    }

    if (defaults.entry_id) {
      entryIdField.value = defaults.entry_id;
      modalTitle.textContent = 'Editar actividad';
      btnSave.textContent = 'Actualizar actividad';
      btnDelete.style.display = 'inline-flex';
    }

    if (defaults.project_id) loadProjectMeta(defaults.project_id, defaults.phase);

    modal.showModal();
  }

  function closeModal() { modal.close(); }

  btnAdd?.addEventListener('click', () => openModal({ date: dateInput?.value }));
  btnClose?.addEventListener('click', closeModal);
  btnCancel?.addEventListener('click', closeModal);

  document.querySelectorAll('.ts-add-day-btn').forEach(btn => {
    btn.addEventListener('click', () => openModal({ date: btn.dataset.date }));
  });

  document.querySelectorAll('.ts-activity-card').forEach(card => {
    card.addEventListener('click', () => {
      openModal({
        entry_id: card.dataset.entryId,
        project_id: card.dataset.projectId,
        date: card.dataset.date,
        hours: card.dataset.hours,
        activity_type: card.dataset.activityType,
        description: card.dataset.description,
        phase: card.dataset.phase,
        subphase: card.dataset.subphase,
        has_blocker: card.dataset.hasBlocker === '1',
        blocker_description: card.dataset.blockerDescription,
        has_significant_progress: card.dataset.hasSignificantProgress === '1',
        has_deliverable: card.dataset.hasDeliverable === '1',
        operational_comment: card.dataset.operationalComment,
        comment: card.dataset.comment,
      });
    });
  });

  document.querySelectorAll('.ts-recent-item').forEach(item => {
    item.addEventListener('click', () => {
      openModal({
        project_id: item.dataset.projectId,
        activity_type: item.dataset.activityType,
        description: item.dataset.description,
        date: new Date().toISOString().slice(0, 10),
      });
    });
  });

  async function loadProjectMeta(projectId, selectedPhase) {
    try {
      const res = await fetch(`${basePath}/timesheets/api/project-meta?project_id=${projectId}`);
      const data = await res.json();
      if (data.ok && data.phases) {
        phaseSelect.innerHTML = '<option value="">Sin fase</option>';
        data.phases.forEach(p => {
          const opt = document.createElement('option');
          const pName = typeof p === 'string' ? p : (p.name || p.label || p);
          opt.value = pName;
          opt.textContent = pName;
          if (selectedPhase && pName === selectedPhase) opt.selected = true;
          phaseSelect.appendChild(opt);
        });
      }
    } catch (e) { /* silent */ }
  }

  projectSelect?.addEventListener('change', () => {
    if (projectSelect.value) loadProjectMeta(projectSelect.value);
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const isEdit = entryIdField.value !== '';
    const url = isEdit ? `${basePath}/timesheets/activity/update` : `${basePath}/timesheets/activity`;
    const payload = new URLSearchParams();

    if (isEdit) payload.set('entry_id', entryIdField.value);
    payload.set('project_id', projectSelect.value);
    payload.set('date', dateInput.value);
    payload.set('hours', hoursInput.value);
    payload.set('activity_type', typeSelect.value);
    payload.set('description', descInput.value);
    payload.set('comment', descInput.value);
    payload.set('phase', phaseSelect.value);
    payload.set('subphase', subphaseInput.value);
    payload.set('has_blocker', hasBlockerCb.checked ? '1' : '');
    payload.set('blocker_description', blockerDescInput.value);
    payload.set('has_significant_progress', hasProgressCb.checked ? '1' : '');
    payload.set('has_deliverable', hasDeliverableCb.checked ? '1' : '');
    payload.set('operational_comment', opCommentInput.value);

    btnSave.disabled = true;
    btnSave.textContent = 'Guardando…';

    try {
      const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: payload });
      const result = await res.json();
      if (result.ok) {
        closeModal();
        location.reload();
      } else {
        alert(result.message || 'Error al guardar');
      }
    } catch (err) {
      alert('Error de conexión');
    } finally {
      btnSave.disabled = false;
      btnSave.textContent = isEdit ? 'Actualizar actividad' : 'Guardar actividad';
    }
  });

  btnDelete?.addEventListener('click', async () => {
    if (!entryIdField.value) return;
    if (!confirm('¿Eliminar esta actividad?')) return;

    const payload = new URLSearchParams({ entry_id: entryIdField.value });
    try {
      const res = await fetch(`${basePath}/timesheets/activity/delete`, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: payload });
      const result = await res.json();
      if (result.ok) {
        closeModal();
        location.reload();
      } else {
        alert(result.message || 'Error al eliminar');
      }
    } catch (err) {
      alert('Error de conexión');
    }
  });

  // Legacy grid autosave
  const autosave = document.getElementById('autosave-indicator');
  let saveTimer = null;

  async function saveLegacyCell(input) {
    const hours = Number(input.value || 0);
    const comment = String(input.dataset.comment || '').trim();
    const payload = new URLSearchParams({ project_id: input.dataset.project, date: input.dataset.date, hours: String(hours), comment });
    const res = await fetch(`${basePath}/timesheets/cell`, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: payload });
    return res.ok;
  }

  document.querySelectorAll('.hour-input').forEach(input => {
    input.addEventListener('input', () => {
      clearTimeout(saveTimer);
      saveTimer = setTimeout(() => saveLegacyCell(input), 500);
    });
    input.addEventListener('blur', () => saveLegacyCell(input));
  });

  // Period toggle
  const periodSelect = document.getElementById('period-select');
  const rangeInputs = document.querySelectorAll('[data-range-input]');
  periodSelect?.addEventListener('change', () => {
    const custom = periodSelect.value === 'custom';
    rangeInputs.forEach(el => { el.disabled = !custom; });
  });
})();
</script>
