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
$weekActivities = is_array($weekActivities ?? null) ? $weekActivities : [];
$activityTypeBreakdown = is_array($activityTypeBreakdown ?? null) ? $activityTypeBreakdown : [];
$projectsForTimesheet = is_array($projectsForTimesheet ?? null) ? $projectsForTimesheet : [];

$statusMap = [
    'approved' => ['label' => 'Aprobada', 'class' => 'approved'],
    'rejected'  => ['label' => 'Rechazada', 'class' => 'rejected'],
    'submitted' => ['label' => 'Pendiente', 'class' => 'submitted'],
    'draft'     => ['label' => 'Borrador', 'class' => 'draft'],
    'partial'   => ['label' => 'Parcial', 'class' => 'partial'],
];
$selectedStatus = (string) ($selectedWeekSummary['status'] ?? 'draft');
if (!isset($statusMap[$selectedStatus])) { $selectedStatus = 'draft'; }
$selectedMeta = $statusMap[$selectedStatus];

$weeksRegistered = count($approvedWeeks);
$weeksApproved = count(array_filter($approvedWeeks, static fn($w): bool => ((int)($w['status_weight'] ?? 0)) >= 5));
$weeksPending = max(0, $weeksRegistered - $weeksApproved);
$approvedPercent = (float)($executiveSummary['approved_percent'] ?? 0);
$compliancePercent = (float)($executiveSummary['compliance_percent'] ?? 0);
$complianceWeek = $weeklyCapacity > 0 ? min(100, round(($weekTotal / $weeklyCapacity) * 100, 1)) : 0;
$weekIsLocked = $selectedStatus === 'approved';
$weekCanWithdraw = in_array($selectedStatus, ['submitted', 'partial'], true);

// Build activity map: date → list of activities
$activitiesByDay = [];
foreach ($weekActivities as $act) {
    $d = (string)($act['date'] ?? '');
    if ($d !== '') { $activitiesByDay[$d][] = $act; }
}

$activityTypeLabels = [
    'desarrollo'     => 'Desarrollo',
    'analisis'       => 'Análisis',
    'reunion'        => 'Reunión',
    'documentacion'  => 'Documentación',
    'soporte'        => 'Soporte',
    'investigacion'  => 'Investigación',
    'pruebas'        => 'Pruebas',
    'gestion_pm'     => 'Gestión PM',
    'sin_clasificar' => 'Sin clasificar',
];
$activityTypeColors = [
    'desarrollo'     => '#2563eb',
    'analisis'       => '#7c3aed',
    'reunion'        => '#0891b2',
    'documentacion'  => '#0d9488',
    'soporte'        => '#ea580c',
    'investigacion'  => '#d97706',
    'pruebas'        => '#16a34a',
    'gestion_pm'     => '#9333ea',
    'sin_clasificar' => '#64748b',
];

$dayNames = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
$dayFullNames = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
$calendarDays = [];
for ($i = 0; $i < 7; $i++) {
    $d = $weekStart->modify("+{$i} days");
    $dateKey = $d->format('Y-m-d');
    $acts = $activitiesByDay[$dateKey] ?? [];
    $dayHours = array_sum(array_column($acts, 'hours'));
    $calendarDays[] = [
        'key'      => $dateKey,
        'label'    => $dayNames[$i],
        'fullname' => $dayFullNames[$i],
        'number'   => $d->format('d'),
        'month'    => $d->format('M'),
        'isToday'  => $d->format('Y-m-d') === date('Y-m-d'),
        'isWeekend'=> $i >= 5,
        'activities'=> $acts,
        'hours'    => $dayHours,
    ];
}

// Activity type totals for donut
$typeTotal = array_sum(array_column($activityTypeBreakdown, 'total_hours'));
?>

<section class="ts-shell">

    <!-- ─── Top toolbar ─────────────────────────────────────────── -->
    <header class="ts-toolbar card">
        <div class="ts-toolbar-title">
            <h2>Timesheets</h2>
            <p class="ts-muted">Registro de actividad operativa del talento</p>
        </div>
        <nav class="ts-tab-nav" id="ts-tab-nav">
            <button class="ts-tab active" data-tab="calendar">📅 Calendario</button>
            <button class="ts-tab" data-tab="analytics">📊 Analítica</button>
            <?php if ($canApprove || $canManageAdvanced): ?>
            <button class="ts-tab" data-tab="manager">⚙️ Gestión</button>
            <?php endif; ?>
        </nav>
        <div class="ts-toolbar-actions">
            <?php if ($canReport): ?>
            <button class="ts-btn-primary" id="btn-add-activity">＋ Nueva actividad</button>
            <?php endif; ?>
        </div>
    </header>

    <!-- ─── KPI strip ────────────────────────────────────────────── -->
    <div class="ts-kpi-strip">
        <article class="ts-kpi-card">
            <span class="ts-kpi-label">Horas esta semana</span>
            <strong class="ts-kpi-value"><?= round($weekTotal, 1) ?>h</strong>
            <?php if ($weeklyCapacity > 0): ?>
            <div class="ts-capacity-bar">
                <div class="ts-capacity-fill <?= $complianceWeek >= 100 ? 'full' : ($complianceWeek >= 80 ? 'good' : 'low') ?>" style="width:<?= min(100, $complianceWeek) ?>%"></div>
            </div>
            <span class="ts-kpi-sub"><?= $complianceWeek ?>% de <?= round($weeklyCapacity, 0) ?>h capacidad</span>
            <?php endif; ?>
        </article>
        <article class="ts-kpi-card kpi-approved">
            <span class="ts-kpi-label">Aprobadas (periodo)</span>
            <strong class="ts-kpi-value"><?= round((float)($executiveSummary['approved'] ?? 0), 1) ?>h</strong>
            <span class="ts-kpi-sub"><?= round($approvedPercent, 1) ?>% del total</span>
        </article>
        <article class="ts-kpi-card kpi-pending">
            <span class="ts-kpi-label">Pendientes</span>
            <strong class="ts-kpi-value"><?= round((float)($executiveSummary['pending'] ?? 0), 1) ?>h</strong>
            <span class="ts-kpi-sub"><?= $weeksPending ?> sem. pendiente<?= $weeksPending !== 1 ? 's' : '' ?></span>
        </article>
        <article class="ts-kpi-card <?= $compliancePercent > 100 ? 'kpi-danger' : ($compliancePercent >= 80 ? 'kpi-approved' : 'kpi-pending') ?>">
            <span class="ts-kpi-label">Cumplimiento periodo</span>
            <strong class="ts-kpi-value"><?= round($compliancePercent, 1) ?>%</strong>
            <span class="ts-kpi-sub">Cap: <?= round((float)($executiveSummary['capacity'] ?? 0), 0) ?>h</span>
        </article>
        <article class="ts-kpi-card ts-week-status <?= $selectedMeta['class'] ?>">
            <span class="ts-kpi-label">Estado semana</span>
            <strong class="ts-badge <?= $selectedMeta['class'] ?>"><?= $selectedMeta['label'] ?></strong>
            <?php if ($canReport): ?>
            <div class="ts-week-actions">
                <?php if (!$weekIsLocked): ?>
                <form method="POST" action="<?= $basePath ?>/timesheets/submit-week">
                    <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                    <button type="submit" class="ts-btn-xs primary">Enviar semana</button>
                </form>
                <?php endif; ?>
                <?php if ($weekCanWithdraw): ?>
                <form method="POST" action="<?= $basePath ?>/timesheets/cancel-week">
                    <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                    <button type="submit" class="ts-btn-xs">Retirar</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </article>
    </div>

    <!-- ─── Week navigator ──────────────────────────────────────── -->
    <div class="ts-week-nav card">
        <?php
        $prevWeek = $weekStart->modify('-7 days')->format('o-\\WW');
        $nextWeek = $weekStart->modify('+7 days')->format('o-\\WW');
        $todayWeek = (new DateTimeImmutable('monday this week'))->format('o-\\WW');
        ?>
        <a href="<?= $basePath ?>/timesheets?week=<?= urlencode($prevWeek) ?>" class="ts-week-arrow">‹</a>
        <div class="ts-week-label">
            <strong><?= $weekStart->format('d M') ?> – <?= $weekEnd->format('d M Y') ?></strong>
            <span class="ts-muted">Semana <?= $weekStart->format('W') ?></span>
            <?php if ($weekValue !== $todayWeek): ?>
            <a href="<?= $basePath ?>/timesheets?week=<?= urlencode($todayWeek) ?>" class="ts-btn-xs">Hoy</a>
            <?php endif; ?>
        </div>
        <a href="<?= $basePath ?>/timesheets?week=<?= urlencode($nextWeek) ?>" class="ts-week-arrow">›</a>
        <!-- Weeks history pills -->
        <div class="ts-weeks-pills">
            <?php foreach (array_slice($weeksHistory, 0, 8) as $wh):
                $ws = new DateTimeImmutable((string)($wh['week_start'] ?? 'now'));
                $sw = (int)($wh['status_weight'] ?? 2);
                $wpCls = $sw >= 5 ? 'approved' : ($sw >= 4 ? 'rejected' : ($sw >= 3 ? 'submitted' : 'draft'));
                $isCur = $ws->format('Y-m-d') === $weekStart->format('Y-m-d');
            ?>
            <a class="ts-week-pill <?= $wpCls ?> <?= $isCur ? 'active' : '' ?>"
               href="<?= $basePath ?>/timesheets?week=<?= urlencode($ws->format('o-\\WW')) ?>">
                Sem <?= $ws->format('W') ?>
                <em><?= round((float)($wh['total_hours'] ?? 0), 1) ?>h</em>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════ -->
    <!-- TAB: CALENDAR                                               -->
    <!-- ════════════════════════════════════════════════════════════ -->
    <div class="ts-tab-panel active" id="tab-calendar">
        <?php if ($canReport): ?>
        <div class="ts-calendar-grid">
            <?php foreach ($calendarDays as $day): ?>
            <div class="ts-cal-day <?= $day['isToday'] ? 'today' : '' ?> <?= $day['isWeekend'] ? 'weekend' : '' ?> <?= $weekIsLocked ? 'locked' : '' ?>">
                <div class="ts-cal-day-header">
                    <div class="ts-cal-day-info">
                        <span class="ts-cal-dayname"><?= $day['label'] ?></span>
                        <span class="ts-cal-daynum <?= $day['isToday'] ? 'today-badge' : '' ?>"><?= $day['number'] ?></span>
                        <span class="ts-muted ts-cal-month"><?= $day['month'] ?></span>
                    </div>
                    <div class="ts-cal-day-hours">
                        <?php if ($day['hours'] > 0): ?>
                        <span class="ts-hours-badge"><?= round($day['hours'], 1) ?>h</span>
                        <?php endif; ?>
                        <?php if (!$weekIsLocked): ?>
                        <button class="ts-cal-add-btn" title="Agregar actividad"
                                data-date="<?= $day['key'] ?>">＋</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ts-cal-activities" id="acts-<?= $day['key'] ?>">
                    <?php foreach ($day['activities'] as $act): ?>
                    <?php
                        $aType = (string)($act['activity_type'] ?? '');
                        $aColor = $activityTypeColors[$aType] ?? '#64748b';
                        $aLabel = $activityTypeLabels[$aType] ?? ($aType !== '' ? $aType : 'Sin tipo');
                        $aStatus = (string)($act['status'] ?? 'draft');
                        $isDraft = $aStatus === 'draft';
                        $aProject = htmlspecialchars((string)($act['project_name'] ?? ''));
                        $aHours = (float)($act['hours'] ?? 0);
                        $aDesc = htmlspecialchars((string)($act['activity_description'] ?? $act['comment'] ?? ''));
                        $hasBlocker = !empty($act['has_blocker']);
                        $hasAdvance = !empty($act['has_significant_advance']);
                        $hasDeliv   = !empty($act['has_deliverable']);
                    ?>
                    <div class="ts-activity-card status-<?= htmlspecialchars($aStatus) ?>"
                         data-id="<?= (int)($act['id'] ?? 0) ?>"
                         style="--act-color:<?= $aColor ?>">
                        <div class="ts-act-top">
                            <span class="ts-act-type-badge" style="background:<?= $aColor ?>15;color:<?= $aColor ?>;border-color:<?= $aColor ?>30"><?= htmlspecialchars($aLabel) ?></span>
                            <span class="ts-act-hours"><?= round($aHours, 1) ?>h</span>
                        </div>
                        <div class="ts-act-project"><?= $aProject ?></div>
                        <?php if ($aDesc !== ''): ?>
                        <div class="ts-act-desc"><?= $aDesc ?></div>
                        <?php endif; ?>
                        <div class="ts-act-flags">
                            <?php if ($hasBlocker): ?><span class="ts-flag blocker" title="Bloqueo">🔴 Bloqueo</span><?php endif; ?>
                            <?php if ($hasAdvance): ?><span class="ts-flag advance" title="Avance significativo">🟢 Avance</span><?php endif; ?>
                            <?php if ($hasDeliv): ?><span class="ts-flag deliverable" title="Entregable">📦 Entregable</span><?php endif; ?>
                        </div>
                        <?php if ($isDraft && !$weekIsLocked): ?>
                        <button class="ts-act-delete" title="Eliminar actividad"
                                data-id="<?= (int)($act['id'] ?? 0) ?>">×</button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($day['activities'])): ?>
                    <div class="ts-cal-empty <?= $weekIsLocked ? '' : 'clickable' ?>"
                         <?= !$weekIsLocked ? 'data-date="' . $day['key'] . '"' : '' ?>>
                        <?= $weekIsLocked ? '—' : '+ Agregar actividad' ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Week history log -->
        <?php if ($weekHistoryLog !== []): ?>
        <details class="ts-history-log card">
            <summary>Historial de acciones de la semana</summary>
            <ul>
                <?php foreach ($weekHistoryLog as $evt): ?>
                <li>
                    <strong><?= htmlspecialchars((string)($evt['action'] ?? '')) ?></strong>
                    · <?= htmlspecialchars((string)($evt['actor_name'] ?? 'Sistema')) ?>
                    · <?= htmlspecialchars((string)($evt['created_at'] ?? '')) ?>
                    <?= !empty($evt['action_comment']) ? '· ' . htmlspecialchars((string)$evt['action_comment']) : '' ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </details>
        <?php endif; ?>

        <?php else: ?>
        <div class="card ts-no-access">
            <p>No tienes permisos para registrar horas. Contacta a tu administrador.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ════════════════════════════════════════════════════════════ -->
    <!-- TAB: ANALYTICS                                              -->
    <!-- ════════════════════════════════════════════════════════════ -->
    <div class="ts-tab-panel" id="tab-analytics">
        <!-- Period filter -->
        <div class="card ts-analytics-filters">
            <form method="GET" class="ts-filters-row">
                <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                <label>Periodo
                    <select name="period" id="period-select">
                        <option value="month" <?= $periodType === 'month' ? 'selected' : '' ?>>Mes actual</option>
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
                        <?php foreach ($projectsForFilter as $pf): ?>
                        <option value="<?= (int)($pf['project_id'] ?? 0) ?>" <?= $projectFilter === (int)($pf['project_id'] ?? 0) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)($pf['project'] ?? '')) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Orden talento
                    <select name="talent_sort">
                        <option value="load_desc" <?= $talentSort === 'load_desc' ? 'selected' : '' ?>>Mayor carga</option>
                        <option value="compliance_asc" <?= $talentSort === 'compliance_asc' ? 'selected' : '' ?>>Menor cumplimiento</option>
                    </select>
                </label>
                <button type="submit" class="ts-btn-primary">Aplicar</button>
            </form>
        </div>

        <div class="ts-analytics-grid">
            <!-- Activity type breakdown -->
            <?php if (!empty($activityTypeBreakdown)): ?>
            <div class="card ts-analytics-card">
                <h3>Distribución de esfuerzo</h3>
                <div class="ts-donut-wrap">
                    <?php foreach ($activityTypeBreakdown as $item):
                        $iType = (string)($item['activity_type'] ?? 'sin_clasificar');
                        $iHours = (float)($item['total_hours'] ?? 0);
                        $iPct = $typeTotal > 0 ? round(($iHours / $typeTotal) * 100, 1) : 0;
                        $iColor = $activityTypeColors[$iType] ?? '#64748b';
                        $iLabel = $activityTypeLabels[$iType] ?? $iType;
                    ?>
                    <div class="ts-effort-row">
                        <span class="ts-effort-dot" style="background:<?= $iColor ?>"></span>
                        <span class="ts-effort-label"><?= htmlspecialchars($iLabel) ?></span>
                        <div class="ts-effort-bar-wrap">
                            <div class="ts-effort-bar" style="width:<?= $iPct ?>%;background:<?= $iColor ?>"></div>
                        </div>
                        <span class="ts-effort-pct"><?= $iPct ?>%</span>
                        <span class="ts-effort-hours"><?= round($iHours, 1) ?>h</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Approved weeks table -->
            <div class="card ts-analytics-card ts-analytics-wide">
                <h3>Horas aprobadas por semana</h3>
                <div class="ts-table-wrap">
                    <table class="ts-table">
                        <thead><tr><th>Semana</th><th>Rango</th><th>Horas</th><th>Estado</th><th>Aprobado</th><th>Aprobador</th></tr></thead>
                        <tbody>
                        <?php foreach ($approvedWeeks as $aw):
                            $awStart = new DateTimeImmutable((string)($aw['week_start'] ?? 'now'));
                            $awEnd   = new DateTimeImmutable((string)($aw['week_end'] ?? 'now'));
                            $weight  = (int)($aw['status_weight'] ?? 0);
                            $awState = $weight >= 5 ? 'approved' : ($weight >= 4 ? 'rejected' : 'submitted');
                            $awMeta  = $statusMap[$awState] ?? $statusMap['draft'];
                        ?>
                        <tr>
                            <td><a href="<?= $basePath ?>/timesheets?week=<?= urlencode($awStart->format('o-\\WW')) ?>">Sem <?= $awStart->format('W') ?></a></td>
                            <td><?= $awStart->format('d/m') ?> – <?= $awEnd->format('d/m/Y') ?></td>
                            <td><?= round((float)($aw['total_hours'] ?? 0), 1) ?>h</td>
                            <td><span class="ts-badge <?= $awMeta['class'] ?>"><?= $awMeta['label'] ?></span></td>
                            <td><?= htmlspecialchars((string)($aw['approved_at'] ?? '—')) ?></td>
                            <td><?= htmlspecialchars((string)($aw['approver_name'] ?? '—')) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Talent breakdown (managers see all) -->
            <?php if (!empty($talentBreakdown)): ?>
            <div class="card ts-analytics-card ts-analytics-wide">
                <h3>Desglose por Talento</h3>
                <div class="ts-table-wrap">
                    <table class="ts-table">
                        <thead><tr><th>Talento</th><th>Total</th><th>Aprobadas</th><th>Rechazadas</th><th>% Cumplimiento</th><th>Última semana</th></tr></thead>
                        <tbody>
                        <?php foreach ($talentBreakdown as $tb): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($tb['talent_name'] ?? '')) ?></td>
                            <td><?= round((float)($tb['total_hours'] ?? 0), 1) ?>h</td>
                            <td><?= round((float)($tb['approved_hours'] ?? 0), 1) ?>h</td>
                            <td><?= round((float)($tb['rejected_hours'] ?? 0), 1) ?>h</td>
                            <td>
                                <?php $cPct = (float)($tb['compliance_percent'] ?? 0); ?>
                                <span class="ts-compliance-badge <?= $cPct >= 80 ? 'good' : ($cPct >= 50 ? 'mid' : 'low') ?>">
                                    <?= round($cPct, 1) ?>%
                                </span>
                            </td>
                            <td><?= htmlspecialchars((string)($tb['last_week_submitted'] ?? '—')) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Project breakdown -->
            <?php if (!empty($projectBreakdown)): ?>
            <div class="card ts-analytics-card">
                <h3>Consumo por proyecto</h3>
                <?php
                $maxPh = max(array_column($projectBreakdown, 'total_hours') ?: [1]);
                foreach (array_slice($projectBreakdown, 0, 8) as $pb):
                    $pbHours = (float)($pb['total_hours'] ?? 0);
                    $pbPct = $maxPh > 0 ? round(($pbHours / $maxPh) * 100, 0) : 0;
                ?>
                <div class="ts-effort-row">
                    <span class="ts-effort-dot" style="background:#2563eb"></span>
                    <span class="ts-effort-label" title="<?= htmlspecialchars((string)($pb['project'] ?? '')) ?>"><?= htmlspecialchars(mb_substr((string)($pb['project'] ?? ''), 0, 22)) ?></span>
                    <div class="ts-effort-bar-wrap">
                        <div class="ts-effort-bar" style="width:<?= $pbPct ?>%;background:#2563eb"></div>
                    </div>
                    <span class="ts-effort-hours"><?= round($pbHours, 1) ?>h</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Monthly summary sidebar -->
            <div class="card ts-analytics-card">
                <h3>Resumen mes actual</h3>
                <ul class="ts-summary-list">
                    <li><span>Total horas</span><strong><?= round((float)($monthlySummary['month_total'] ?? 0), 1) ?>h</strong></li>
                    <li><span>Aprobadas</span><strong><?= round((float)($monthlySummary['approved'] ?? 0), 1) ?>h</strong></li>
                    <li><span>Rechazadas</span><strong><?= round((float)($monthlySummary['rejected'] ?? 0), 1) ?>h</strong></li>
                    <li><span>Borrador</span><strong><?= round((float)($monthlySummary['draft'] ?? 0), 1) ?>h</strong></li>
                    <li><span>Capacidad</span><strong><?= round((float)($monthlySummary['capacity'] ?? 0), 1) ?>h</strong></li>
                    <li><span>Cumplimiento</span><strong><?= htmlspecialchars((string)($monthlySummary['compliance'] ?? 0)) ?>%</strong></li>
                </ul>
                <button class="ts-btn-secondary" style="margin-top:12px" onclick="window.print()">Imprimir informe</button>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════ -->
    <!-- TAB: MANAGER                                                -->
    <!-- ════════════════════════════════════════════════════════════ -->
    <?php if ($canApprove || $canManageAdvanced): ?>
    <div class="ts-tab-panel" id="tab-manager">
        <div class="card" style="padding:20px">
            <h3>Control gerencial operativo</h3>
            <p class="ts-muted">Aprobación/rechazo por registro, edición controlada, reapertura y eliminación con auditoría.</p>
            <form method="GET" class="ts-filters-row" style="margin-bottom:16px">
                <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                <label>Talento
                    <select name="talent_id">
                        <option value="0">Todos</option>
                        <?php foreach ($talentOptions as $tal): ?>
                        <option value="<?= (int)($tal['id'] ?? 0) ?>" <?= $talentFilter === (int)($tal['id'] ?? 0) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)($tal['name'] ?? '')) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="ts-btn-primary">Filtrar</button>
            </form>

            <div class="ts-table-wrap">
                <table class="ts-table">
                    <thead><tr><th>Fecha</th><th>Talento</th><th>Proyecto</th><th>Tipo</th><th>Horas</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($managedWeekEntries as $entry):
                        $eType = (string)($entry['activity_type'] ?? '');
                        $eTypeLabel = $activityTypeLabels[$eType] ?? ($eType !== '' ? $eType : '—');
                        $eTypeColor = $activityTypeColors[$eType] ?? '#64748b';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars((string)($entry['date'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string)($entry['talent_name'] ?? $entry['user_name'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string)($entry['project_name'] ?? '')) ?></td>
                        <td>
                            <?php if ($eType !== ''): ?>
                            <span class="ts-act-type-badge" style="background:<?= $eTypeColor ?>15;color:<?= $eTypeColor ?>;border-color:<?= $eTypeColor ?>30"><?= htmlspecialchars($eTypeLabel) ?></span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?= round((float)($entry['hours'] ?? 0), 1) ?></td>
                        <td><span class="ts-badge <?= htmlspecialchars((string)($entry['status'] ?? 'draft')) ?>"><?= htmlspecialchars((string)($entry['status'] ?? 'draft')) ?></span></td>
                        <td class="ts-mgr-actions">
                            <?php if ($canApprove && in_array((string)($entry['status'] ?? ''), ['submitted','pending','pending_approval'], true)): ?>
                            <form method="POST" action="<?= $basePath ?>/timesheets/<?= (int)($entry['id'] ?? 0) ?>/approve">
                                <button type="submit" class="ts-btn-xs primary">Aprobar</button>
                            </form>
                            <form method="POST" action="<?= $basePath ?>/timesheets/<?= (int)($entry['id'] ?? 0) ?>/reject" class="ts-reject-form">
                                <input type="text" name="comment" placeholder="Motivo" required class="ts-inline-input">
                                <button type="submit" class="ts-btn-xs danger">Rechazar</button>
                            </form>
                            <?php endif; ?>
                            <?php if ($canManageAdvanced): ?>
                            <form method="POST" action="<?= $basePath ?>/timesheets/admin-action">
                                <input type="hidden" name="admin_action" value="update_hours">
                                <input type="hidden" name="timesheet_id" value="<?= (int)($entry['id'] ?? 0) ?>">
                                <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                                <input type="number" step="0.25" min="0" max="24" name="hours" value="<?= round((float)($entry['hours'] ?? 0), 2) ?>" required class="ts-inline-input small">
                                <input type="text" name="reason" placeholder="Motivo" required class="ts-inline-input">
                                <button type="submit" class="ts-btn-xs">Editar</button>
                            </form>
                            <form method="POST" action="<?= $basePath ?>/timesheets/admin-action">
                                <input type="hidden" name="admin_action" value="delete_entry">
                                <input type="hidden" name="timesheet_id" value="<?= (int)($entry['id'] ?? 0) ?>">
                                <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                                <input type="text" name="reason" placeholder="Motivo auditoría" required class="ts-inline-input">
                                <button type="submit" class="ts-btn-xs danger">Eliminar</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($canManageAdvanced): ?>
            <div class="ts-admin-actions">
                <h4>Reapertura controlada de semana</h4>
                <form method="POST" action="<?= $basePath ?>/timesheets/admin-action" class="ts-filters-row">
                    <input type="hidden" name="admin_action" value="reopen_week">
                    <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                    <input type="hidden" name="week_start" value="<?= htmlspecialchars($weekStart->format('Y-m-d')) ?>">
                    <label>Talento
                        <select name="talent_id"><option value="0">Toda la semana</option><?php foreach ($talentOptions as $tal): ?><option value="<?= (int)($tal['id'] ?? 0) ?>"><?= htmlspecialchars((string)($tal['name'] ?? '')) ?></option><?php endforeach; ?></select>
                    </label>
                    <label>Motivo<input type="text" name="reason" required placeholder="Ej: corrección de horas aprobadas"></label>
                    <button type="submit" class="ts-btn-primary">Reabrir semana</button>
                </form>

                <h4>Eliminación masiva controlada</h4>
                <form method="POST" action="<?= $basePath ?>/timesheets/admin-action" class="ts-filters-row">
                    <input type="hidden" name="admin_action" value="delete_week">
                    <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                    <input type="hidden" name="week_start" value="<?= htmlspecialchars($weekStart->format('Y-m-d')) ?>">
                    <label>Talento
                        <select name="talent_id"><option value="0">Toda la semana</option><?php foreach ($talentOptions as $tal): ?><option value="<?= (int)($tal['id'] ?? 0) ?>"><?= htmlspecialchars((string)($tal['name'] ?? '')) ?></option><?php endforeach; ?></select>
                    </label>
                    <label>Motivo<input type="text" name="reason" required placeholder="Ej: limpieza de carga masiva"></label>
                    <label>Confirmación<input type="text" name="confirm_token" required placeholder="ELIMINAR MASIVO"></label>
                    <button type="submit" class="ts-btn-xs danger">Eliminar masivo</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</section>

<!-- ════════════════════════════════════════════════════════════════ -->
<!-- ACTIVITY MODAL                                                  -->
<!-- ════════════════════════════════════════════════════════════════ -->
<?php if ($canReport): ?>
<dialog id="activity-modal" class="ts-modal">
    <form id="activity-form" class="ts-modal-body" novalidate>
        <div class="ts-modal-header">
            <h3>Registrar actividad</h3>
            <button type="button" class="ts-modal-close" id="modal-close">×</button>
        </div>

        <div class="ts-modal-grid">
            <!-- Row 1 -->
            <label class="ts-field">
                <span>Fecha <em>*</em></span>
                <input type="date" name="date" id="act-date" required>
            </label>
            <label class="ts-field">
                <span>Proyecto <em>*</em></span>
                <select name="project_id" id="act-project" required>
                    <option value="">— Selecciona proyecto —</option>
                    <?php foreach ($projectsForTimesheet as $pj): ?>
                    <option value="<?= (int)($pj['project_id'] ?? 0) ?>"><?= htmlspecialchars((string)($pj['project'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <!-- Row 2 -->
            <label class="ts-field">
                <span>Tipo de actividad <em>*</em></span>
                <select name="activity_type" id="act-type" required>
                    <option value="">— Tipo de actividad —</option>
                    <?php foreach ($activityTypeLabels as $val => $lbl): if ($val === 'sin_clasificar') continue; ?>
                    <option value="<?= $val ?>"><?= htmlspecialchars($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="ts-field">
                <span>Horas dedicadas <em>*</em></span>
                <input type="number" name="hours" id="act-hours" min="0.25" max="24" step="0.25" required placeholder="Ej: 2">
            </label>

            <!-- Row 3 - full width -->
            <label class="ts-field ts-field-full">
                <span>Descripción de la actividad <em>*</em></span>
                <input type="text" name="activity_description" id="act-desc" required
                       placeholder="Ej: Integración endpoint de consulta de facturas" maxlength="300" list="ac-desc-list">
                <datalist id="ac-desc-list"></datalist>
            </label>

            <!-- Row 4 -->
            <label class="ts-field">
                <span>Fase del proyecto</span>
                <input type="text" name="phase_name" id="act-phase" placeholder="Ej: Diseño, Desarrollo, QA">
            </label>
            <label class="ts-field">
                <span>Subfase / Subtarea</span>
                <input type="text" name="subtask_name" id="act-subtask" placeholder="Ej: Módulo de pagos">
            </label>

            <!-- Row 5 - full width - comment -->
            <label class="ts-field ts-field-full">
                <span>Comentario diario</span>
                <textarea name="comment" id="act-comment" rows="2"
                          placeholder="Nota adicional o detalle para el líder"></textarea>
            </label>

            <!-- Operational flags -->
            <div class="ts-field ts-field-full ts-flags-row">
                <label class="ts-checkbox-label">
                    <input type="checkbox" name="has_blocker" id="act-has-blocker" value="1">
                    <span>🔴 ¿Hubo bloqueo?</span>
                </label>
                <label class="ts-checkbox-label">
                    <input type="checkbox" name="has_significant_advance" value="1">
                    <span>🟢 ¿Avance significativo?</span>
                </label>
                <label class="ts-checkbox-label">
                    <input type="checkbox" name="has_deliverable" value="1">
                    <span>📦 ¿Se generó entregable?</span>
                </label>
            </div>

            <!-- Blocker description - hidden until checked -->
            <div class="ts-field ts-field-full" id="blocker-desc-field" style="display:none">
                <label>
                    <span>Descripción del bloqueo</span>
                    <textarea name="blocker_description" id="act-blocker-desc" rows="2"
                              placeholder="Describe el bloqueo encontrado (se creará automáticamente un registro de bloqueo en el proyecto)"></textarea>
                </label>
                <p class="ts-hint">⚡ Al guardar, se creará automáticamente un bloqueo en el proyecto.</p>
            </div>

            <!-- Operational comment -->
            <label class="ts-field ts-field-full">
                <span>Comentario operativo adicional</span>
                <textarea name="operational_comment" id="act-op-comment" rows="2"
                          placeholder="Observaciones operativas para el seguimiento del proyecto"></textarea>
            </label>
        </div>

        <!-- Autocomplete suggestions -->
        <div id="ac-suggestions" class="ts-ac-suggestions" style="display:none">
            <p class="ts-muted ts-ac-title">Registros recientes:</p>
            <div id="ac-suggestions-list"></div>
        </div>

        <div class="ts-modal-footer">
            <button type="button" class="ts-btn-secondary" id="modal-cancel">Cancelar</button>
            <button type="submit" class="ts-btn-primary" id="modal-submit">
                <span id="modal-submit-text">Guardar actividad</span>
                <span id="modal-submit-spinner" style="display:none">Guardando…</span>
            </button>
        </div>
    </form>
</dialog>

<!-- Success toast -->
<div id="ts-toast" class="ts-toast" style="display:none">
    <span id="ts-toast-text"></span>
</div>
<?php endif; ?>

<style>
/* ─── Reset & shell ──────────────────────────────────────────────── */
.ts-shell{display:flex;flex-direction:column;gap:16px;container-type:inline-size}
.ts-muted{color:var(--text-secondary,#64748b);font-size:13px}

/* ─── Toolbar ────────────────────────────────────────────────────── */
.ts-toolbar{display:flex;align-items:center;gap:16px;flex-wrap:wrap;padding:16px 20px}
.ts-toolbar-title{flex:0 0 auto}
.ts-toolbar-title h2{margin:0;font-size:1.25rem}
.ts-tab-nav{display:flex;gap:4px;flex:1;flex-wrap:wrap}
.ts-tab{background:none;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:14px;font-weight:500;color:var(--text-secondary,#64748b);transition:all .15s}
.ts-tab:hover{background:var(--surface,#f8fafc)}
.ts-tab.active{background:var(--accent,#2563eb);color:#fff}
.ts-toolbar-actions{display:flex;gap:8px;margin-left:auto}

/* ─── Buttons ────────────────────────────────────────────────────── */
.ts-btn-primary{background:var(--accent,#2563eb);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:14px;font-weight:600;cursor:pointer;transition:opacity .15s}
.ts-btn-primary:hover{opacity:.9}
.ts-btn-secondary{background:var(--surface,#f8fafc);color:var(--text-primary,#0f172a);border:1px solid var(--border,#e2e8f0);border-radius:8px;padding:8px 16px;font-size:14px;cursor:pointer}
.ts-btn-xs{padding:4px 10px;font-size:12px;font-weight:600;border-radius:6px;cursor:pointer;border:none;background:var(--surface,#f1f5f9);color:var(--text-primary,#0f172a)}
.ts-btn-xs.primary{background:var(--accent,#2563eb);color:#fff}
.ts-btn-xs.danger{background:#fee2e2;color:#991b1b}

/* ─── KPI strip ──────────────────────────────────────────────────── */
.ts-kpi-strip{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
.ts-kpi-card{background:var(--surface,#fff);border:1px solid var(--border,#e2e8f0);border-radius:12px;padding:14px 16px;display:flex;flex-direction:column;gap:4px}
.ts-kpi-card.kpi-approved{border-left:4px solid #16a34a}
.ts-kpi-card.kpi-pending{border-left:4px solid #eab308}
.ts-kpi-card.kpi-danger{border-left:4px solid #dc2626}
.ts-kpi-label{font-size:12px;color:var(--text-secondary,#64748b);font-weight:500}
.ts-kpi-value{font-size:1.6rem;font-weight:700;line-height:1.1}
.ts-kpi-sub{font-size:12px;color:var(--text-secondary,#64748b)}
.ts-capacity-bar{height:6px;background:#e2e8f0;border-radius:99px;overflow:hidden;margin:4px 0}
.ts-capacity-fill{height:100%;border-radius:99px;background:#eab308;transition:width .4s}
.ts-capacity-fill.good{background:#16a34a}
.ts-capacity-fill.full{background:#2563eb}
.ts-capacity-fill.low{background:#dc2626}
.ts-week-status{cursor:default}
.ts-week-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}

/* ─── Badges ──────────────────────────────────────────────────────── */
.ts-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700}
.ts-badge.approved{background:#dcfce7;color:#166534}
.ts-badge.rejected{background:#fee2e2;color:#991b1b}
.ts-badge.submitted{background:#fef3c7;color:#92400e}
.ts-badge.draft{background:#f1f5f9;color:#475569}
.ts-badge.partial{background:#dbeafe;color:#1e40af}

/* ─── Week navigator ─────────────────────────────────────────────── */
.ts-week-nav{display:flex;align-items:center;gap:14px;padding:12px 16px;flex-wrap:wrap}
.ts-week-arrow{font-size:24px;line-height:1;text-decoration:none;color:var(--text-primary,#0f172a);padding:4px 8px;border-radius:6px;border:1px solid var(--border,#e2e8f0);transition:background .15s}
.ts-week-arrow:hover{background:var(--surface,#f8fafc)}
.ts-week-label{display:flex;align-items:center;gap:10px;flex:1;min-width:200px}
.ts-week-label strong{font-size:15px}
.ts-weeks-pills{display:flex;gap:6px;overflow:auto;flex:1;padding:2px}
.ts-week-pill{display:flex;align-items:center;gap:6px;padding:5px 10px;border-radius:8px;font-size:12px;font-weight:500;text-decoration:none;color:var(--text-primary,#0f172a);border:1.5px solid var(--border,#e2e8f0);white-space:nowrap;transition:all .15s}
.ts-week-pill em{font-style:normal;color:var(--text-secondary,#64748b)}
.ts-week-pill.active{border-color:var(--accent,#2563eb);background:#eff6ff}
.ts-week-pill.approved{border-left:3px solid #16a34a}
.ts-week-pill.rejected{border-left:3px solid #dc2626}
.ts-week-pill.submitted{border-left:3px solid #eab308}
.ts-week-pill.draft{border-left:3px solid #94a3b8}

/* ─── Calendar grid ───────────────────────────────────────────────── */
.ts-calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:10px;min-width:0}
@container (max-width:900px){.ts-calendar-grid{grid-template-columns:repeat(3,1fr)}}
@container (max-width:560px){.ts-calendar-grid{grid-template-columns:1fr 1fr}}
.ts-cal-day{background:var(--surface,#fff);border:1.5px solid var(--border,#e2e8f0);border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:8px;min-height:160px;position:relative}
.ts-cal-day.today{border-color:var(--accent,#2563eb);box-shadow:0 0 0 3px #2563eb18}
.ts-cal-day.weekend{background:#fafafa}
.ts-cal-day.locked{opacity:.8}
.ts-cal-day-header{display:flex;justify-content:space-between;align-items:flex-start}
.ts-cal-day-info{display:flex;flex-direction:column;gap:1px}
.ts-cal-dayname{font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text-secondary,#64748b);letter-spacing:.04em}
.ts-cal-daynum{font-size:20px;font-weight:700;line-height:1}
.ts-cal-daynum.today-badge{color:var(--accent,#2563eb)}
.ts-cal-month{font-size:11px}
.ts-cal-day-hours{display:flex;flex-direction:column;align-items:flex-end;gap:4px}
.ts-hours-badge{background:var(--accent,#2563eb);color:#fff;font-size:11px;font-weight:700;padding:2px 7px;border-radius:99px}
.ts-cal-add-btn{width:26px;height:26px;border-radius:50%;border:1.5px dashed var(--border,#cbd5e1);background:none;cursor:pointer;font-size:16px;color:var(--text-secondary,#94a3b8);display:flex;align-items:center;justify-content:center;transition:all .15s}
.ts-cal-add-btn:hover{border-color:var(--accent,#2563eb);color:var(--accent,#2563eb);background:#eff6ff}
.ts-cal-activities{display:flex;flex-direction:column;gap:6px;flex:1}
.ts-cal-empty{font-size:12px;color:var(--text-secondary,#94a3b8);text-align:center;padding:12px 8px;border:1.5px dashed var(--border,#e2e8f0);border-radius:8px;margin-top:4px}
.ts-cal-empty.clickable{cursor:pointer;transition:all .15s}
.ts-cal-empty.clickable:hover{border-color:var(--accent,#2563eb);color:var(--accent,#2563eb);background:#eff6ff}

/* ─── Activity cards ─────────────────────────────────────────────── */
.ts-activity-card{border-left:3px solid var(--act-color,#2563eb);background:var(--surface,#f8fafc);border-radius:0 8px 8px 0;padding:8px 10px;position:relative;display:flex;flex-direction:column;gap:4px;animation:card-in .2s ease}
@keyframes card-in{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}
.ts-activity-card.status-approved{opacity:.75}
.ts-activity-card.status-rejected{border-left-color:#dc2626!important;background:#fff5f5}
.ts-act-top{display:flex;justify-content:space-between;align-items:center;gap:4px}
.ts-act-type-badge{font-size:10px;font-weight:700;padding:2px 6px;border-radius:6px;border:1px solid;letter-spacing:.02em;white-space:nowrap}
.ts-act-hours{font-size:13px;font-weight:700;color:var(--text-primary,#0f172a);white-space:nowrap}
.ts-act-project{font-size:11px;font-weight:600;color:var(--text-primary,#0f172a);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ts-act-desc{font-size:11px;color:var(--text-secondary,#64748b);overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;line-height:1.4}
.ts-act-flags{display:flex;gap:4px;flex-wrap:wrap}
.ts-flag{font-size:10px;padding:1px 5px;border-radius:4px;background:#f1f5f9;color:#475569}
.ts-flag.blocker{background:#fee2e2;color:#991b1b}
.ts-flag.advance{background:#dcfce7;color:#166534}
.ts-flag.deliverable{background:#e0f2fe;color:#0369a1}
.ts-act-delete{position:absolute;top:4px;right:6px;background:none;border:none;cursor:pointer;font-size:16px;color:#94a3b8;opacity:0;transition:opacity .15s;line-height:1;padding:2px}
.ts-activity-card:hover .ts-act-delete{opacity:1}
.ts-act-delete:hover{color:#dc2626}

/* ─── Tab panels ─────────────────────────────────────────────────── */
.ts-tab-panel{display:none}
.ts-tab-panel.active{display:block}

/* ─── Analytics ───────────────────────────────────────────────────── */
.ts-analytics-filters{padding:16px 20px}
.ts-filters-row{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap}
.ts-filters-row label{display:flex;flex-direction:column;gap:4px;font-size:13px;font-weight:500}
.ts-filters-row input,.ts-filters-row select{padding:6px 10px;border:1px solid var(--border,#e2e8f0);border-radius:8px;font-size:13px;background:var(--surface,#fff);color:var(--text-primary,#0f172a)}
.ts-analytics-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-top:16px}
.ts-analytics-card{padding:18px 20px}
.ts-analytics-card h3{margin:0 0 14px;font-size:15px}
.ts-analytics-wide{grid-column:1/-1}
.ts-effort-row{display:flex;align-items:center;gap:8px;margin-bottom:8px}
.ts-effort-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.ts-effort-label{font-size:12px;font-weight:500;width:100px;flex-shrink:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ts-effort-bar-wrap{flex:1;height:8px;background:#f1f5f9;border-radius:99px;overflow:hidden}
.ts-effort-bar{height:100%;border-radius:99px;transition:width .5s}
.ts-effort-pct{font-size:11px;font-weight:600;width:36px;text-align:right;flex-shrink:0}
.ts-effort-hours{font-size:11px;color:var(--text-secondary,#64748b);width:32px;text-align:right;flex-shrink:0}
.ts-compliance-badge{padding:2px 8px;border-radius:99px;font-size:12px;font-weight:700}
.ts-compliance-badge.good{background:#dcfce7;color:#166534}
.ts-compliance-badge.mid{background:#fef3c7;color:#92400e}
.ts-compliance-badge.low{background:#fee2e2;color:#991b1b}
.ts-summary-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px}
.ts-summary-list li{display:flex;justify-content:space-between;align-items:center;font-size:13px;border-bottom:1px solid var(--border,#f1f5f9);padding-bottom:6px}
.ts-summary-list li:last-child{border:none}

/* ─── Tables ─────────────────────────────────────────────────────── */
.ts-table-wrap{overflow:auto}
.ts-table{width:100%;border-collapse:collapse;font-size:13px}
.ts-table th{text-align:left;padding:8px 12px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--text-secondary,#64748b);border-bottom:2px solid var(--border,#e2e8f0);white-space:nowrap}
.ts-table td{padding:8px 12px;border-bottom:1px solid var(--border,#f1f5f9);vertical-align:middle}
.ts-table tr:hover td{background:var(--surface,#f8fafc)}
.ts-table a{color:var(--accent,#2563eb);text-decoration:none}
.ts-table a:hover{text-decoration:underline}
.ts-mgr-actions{display:flex;gap:4px;flex-wrap:wrap;align-items:center}
.ts-reject-form{display:flex;gap:4px;align-items:center}
.ts-inline-input{padding:4px 8px;border:1px solid var(--border,#e2e8f0);border-radius:6px;font-size:12px;width:120px}
.ts-inline-input.small{width:60px}

/* ─── Admin panel ─────────────────────────────────────────────────── */
.ts-admin-actions{margin-top:24px;border-top:1px solid var(--border,#e2e8f0);padding-top:16px;display:flex;flex-direction:column;gap:16px}
.ts-admin-actions h4{margin:0 0 6px;font-size:14px}

/* ─── History log ─────────────────────────────────────────────────── */
.ts-history-log{margin-top:12px}
.ts-history-log summary{cursor:pointer;padding:12px 16px;font-size:14px;font-weight:600}
.ts-history-log ul{margin:0;padding:8px 16px 16px 28px;display:flex;flex-direction:column;gap:6px;font-size:13px}

/* ─── No-access card ──────────────────────────────────────────────── */
.ts-no-access{padding:40px;text-align:center;color:var(--text-secondary,#64748b)}

/* ─── Modal ───────────────────────────────────────────────────────── */
.ts-modal{border:none;border-radius:16px;max-width:680px;width:95%;padding:0;box-shadow:0 20px 60px rgba(0,0,0,.15)}
.ts-modal::backdrop{background:rgba(15,23,42,.5);backdrop-filter:blur(2px)}
.ts-modal-body{display:flex;flex-direction:column;gap:0}
.ts-modal-header{display:flex;justify-content:space-between;align-items:center;padding:18px 22px;border-bottom:1px solid var(--border,#e2e8f0)}
.ts-modal-header h3{margin:0;font-size:17px}
.ts-modal-close{background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-secondary,#64748b);line-height:1;padding:4px 8px;border-radius:6px}
.ts-modal-close:hover{background:var(--surface,#f1f5f9)}
.ts-modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:20px 22px;overflow-y:auto;max-height:60vh}
.ts-field{display:flex;flex-direction:column;gap:5px;font-size:13px;font-weight:500}
.ts-field em{color:#dc2626;font-style:normal}
.ts-field input,.ts-field select,.ts-field textarea{padding:8px 10px;border:1.5px solid var(--border,#e2e8f0);border-radius:8px;font-size:14px;background:var(--surface,#fff);color:var(--text-primary,#0f172a);transition:border-color .15s;width:100%;box-sizing:border-box;font-family:inherit}
.ts-field input:focus,.ts-field select:focus,.ts-field textarea:focus{outline:none;border-color:var(--accent,#2563eb);box-shadow:0 0 0 3px #2563eb18}
.ts-field textarea{resize:vertical}
.ts-field-full{grid-column:1/-1}
.ts-flags-row{display:flex;gap:16px;flex-wrap:wrap}
.ts-checkbox-label{display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:500}
.ts-checkbox-label input[type=checkbox]{width:16px;height:16px;cursor:pointer;accent-color:var(--accent,#2563eb)}
.ts-hint{font-size:11px;color:var(--accent,#2563eb);margin:4px 0 0;font-style:italic}
.ts-modal-footer{display:flex;justify-content:flex-end;gap:10px;padding:16px 22px;border-top:1px solid var(--border,#e2e8f0)}

/* ─── Autocomplete suggestions ────────────────────────────────────── */
.ts-ac-suggestions{padding:10px 22px;border-top:1px dashed var(--border,#e2e8f0)}
.ts-ac-title{margin:0 0 8px;font-size:12px}
.ts-ac-list{display:flex;flex-direction:column;gap:6px}
.ts-ac-item{padding:8px 12px;border:1px solid var(--border,#e2e8f0);border-radius:8px;cursor:pointer;font-size:12px;display:flex;gap:8px;align-items:center;transition:background .15s}
.ts-ac-item:hover{background:var(--surface,#f8fafc)}
.ts-ac-item-type{font-weight:600;font-size:10px;padding:1px 5px;border-radius:4px;background:#eff6ff;color:#2563eb}

/* ─── Toast ────────────────────────────────────────────────────────── */
.ts-toast{position:fixed;bottom:24px;right:24px;background:#0f172a;color:#fff;padding:12px 20px;border-radius:12px;font-size:14px;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,.2);animation:toast-in .3s ease}
@keyframes toast-in{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

/* ─── Responsive ─────────────────────────────────────────────────── */
@media(max-width:768px){
    .ts-toolbar{flex-direction:column;align-items:stretch}
    .ts-tab-nav{justify-content:center}
    .ts-modal-grid{grid-template-columns:1fr}
    .ts-kpi-strip{grid-template-columns:1fr 1fr}
    .ts-analytics-grid{grid-template-columns:1fr}
    .ts-week-nav{flex-wrap:wrap}
    .ts-weeks-pills{display:none}
}
</style>

<script>
(() => {
'use strict';

// ── Tab switching ──────────────────────────────────────────────────
const tabNav = document.getElementById('ts-tab-nav');
const panels = {};
['calendar','analytics','manager'].forEach(t => {
    panels[t] = document.getElementById('tab-' + t);
});
tabNav?.querySelectorAll('.ts-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        tabNav.querySelectorAll('.ts-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        Object.values(panels).forEach(p => p?.classList.remove('active'));
        const key = btn.dataset.tab;
        panels[key]?.classList.add('active');
    });
});

// ── Period filter ──────────────────────────────────────────────────
const periodSel = document.getElementById('period-select');
const rangeInputs = document.querySelectorAll('[data-range-input]');
periodSel?.addEventListener('change', () => {
    const custom = periodSel.value === 'custom';
    rangeInputs.forEach(el => { el.disabled = !custom; });
});

// ── Modal control ──────────────────────────────────────────────────
const modal = document.getElementById('activity-modal');
const form  = document.getElementById('activity-form');
if (!modal || !form) return;

const dateInput    = document.getElementById('act-date');
const projectSel   = document.getElementById('act-project');
const typeSel      = document.getElementById('act-type');
const hoursInput   = document.getElementById('act-hours');
const descInput    = document.getElementById('act-desc');
const hasBlockerCb = document.getElementById('act-has-blocker');
const blockerField = document.getElementById('blocker-desc-field');
const submitBtn    = document.getElementById('modal-submit');
const submitText   = document.getElementById('modal-submit-text');
const submitSpin   = document.getElementById('modal-submit-spinner');
const toast        = document.getElementById('ts-toast');
const toastText    = document.getElementById('ts-toast-text');

let autocompleteData = null;

function openModal(date) {
    if (date && dateInput) dateInput.value = date;
    modal.showModal();
    loadAutocomplete();
    setTimeout(() => projectSel?.focus(), 50);
}

function closeModal() {
    modal.close();
    form.reset();
    if (blockerField) blockerField.style.display = 'none';
    const acBox = document.getElementById('ac-suggestions');
    if (acBox) acBox.style.display = 'none';
}

document.getElementById('btn-add-activity')?.addEventListener('click', () => {
    const today = new Date().toISOString().slice(0, 10);
    openModal(today);
});
document.getElementById('modal-close')?.addEventListener('click', closeModal);
document.getElementById('modal-cancel')?.addEventListener('click', closeModal);
modal?.addEventListener('click', e => { if (e.target === modal) closeModal(); });

// Blocker field toggle
hasBlockerCb?.addEventListener('change', () => {
    if (blockerField) blockerField.style.display = hasBlockerCb.checked ? 'block' : 'none';
});

// Calendar day add buttons
document.querySelectorAll('[data-date]').forEach(el => {
    el.addEventListener('click', () => openModal(el.dataset.date));
});

// ── Autocomplete ───────────────────────────────────────────────────
async function loadAutocomplete() {
    if (autocompleteData) { renderSuggestions(); return; }
    try {
        const res = await fetch('<?= $basePath ?>/timesheets/autocomplete');
        const json = await res.json();
        if (json.ok) { autocompleteData = json.data; renderSuggestions(); }
    } catch(_) {}
}

function renderSuggestions() {
    if (!autocompleteData) return;
    const combos = autocompleteData.recent_combos || [];
    if (!combos.length) return;
    const acBox  = document.getElementById('ac-suggestions');
    const acList = document.getElementById('ac-suggestions-list');
    if (!acBox || !acList) return;

    acList.innerHTML = '';
    acList.className = 'ts-ac-list';

    const seen = new Set();
    combos.slice(0, 5).forEach(c => {
        const key = (c.project_id || '') + '|' + (c.activity_type || '') + '|' + (c.activity_description || '');
        if (seen.has(key)) return;
        seen.add(key);
        const item = document.createElement('div');
        item.className = 'ts-ac-item';
        item.innerHTML = `
            <span class="ts-ac-item-type">${escHtml(c.activity_type || '—')}</span>
            <span><strong>${escHtml(c.project_name || '')}</strong> · ${escHtml(c.activity_description || '')}</span>`;
        item.addEventListener('click', () => {
            if (projectSel) {
                const opt = [...projectSel.options].find(o => +o.value === +c.project_id);
                if (opt) projectSel.value = opt.value;
            }
            if (typeSel && c.activity_type) typeSel.value = c.activity_type;
            if (descInput && c.activity_description) descInput.value = c.activity_description;
            acBox.style.display = 'none';
        });
        acList.appendChild(item);
    });

    // Populate datalist for description
    const dl = document.getElementById('ac-desc-list');
    if (dl) {
        dl.innerHTML = '';
        const descs = [...new Set(combos.map(c => c.activity_description).filter(Boolean))].slice(0, 10);
        descs.forEach(d => {
            const op = document.createElement('option');
            op.value = d;
            dl.appendChild(op);
        });
    }

    if (acList.children.length > 0) acBox.style.display = 'block';
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Form submission ────────────────────────────────────────────────
form?.addEventListener('submit', async (e) => {
    e.preventDefault();

    const date    = form.querySelector('[name=date]')?.value?.trim() || '';
    const project = form.querySelector('[name=project_id]')?.value?.trim() || '';
    const hours   = parseFloat(form.querySelector('[name=hours]')?.value || 0);
    const desc    = form.querySelector('[name=activity_description]')?.value?.trim() || '';

    if (!date || !project || hours <= 0 || !desc) {
        showToast('Completa los campos obligatorios: proyecto, fecha, tipo, horas y descripción.', true);
        return;
    }

    if (submitBtn) submitBtn.disabled = true;
    if (submitText) submitText.style.display = 'none';
    if (submitSpin) submitSpin.style.display = 'inline';

    try {
        const payload = new FormData(form);
        if (hasBlockerCb && !hasBlockerCb.checked) payload.delete('has_blocker');

        const res = await fetch('<?= $basePath ?>/timesheets/activity', { method: 'POST', body: payload });
        const json = await res.json();

        if (json.ok) {
            let msg = '✅ Actividad guardada correctamente.';
            if (json.blocker_created) msg += ' 🔴 Bloqueo registrado en el proyecto.';
            showToast(msg);
            closeModal();
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast(json.message || 'Error al guardar actividad.', true);
        }
    } catch(_) {
        showToast('Error de conexión. Intenta nuevamente.', true);
    } finally {
        if (submitBtn) submitBtn.disabled = false;
        if (submitText) submitText.style.display = 'inline';
        if (submitSpin) submitSpin.style.display = 'none';
    }
});

// ── Delete activity ────────────────────────────────────────────────
document.querySelectorAll('.ts-act-delete').forEach(btn => {
    btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const id = btn.dataset.id;
        if (!id || !confirm('¿Eliminar esta actividad?')) return;
        try {
            const payload = new URLSearchParams({ timesheet_id: id });
            const res = await fetch('<?= $basePath ?>/timesheets/delete-activity', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload
            });
            const json = await res.json();
            if (json.ok) {
                btn.closest('.ts-activity-card')?.remove();
                showToast('Actividad eliminada.');
            } else {
                showToast(json.message || 'No se pudo eliminar.', true);
            }
        } catch(_) {
            showToast('Error de conexión.', true);
        }
    });
});

// ── Toast helper ───────────────────────────────────────────────────
let toastTimer;
function showToast(msg, isError = false) {
    if (!toast || !toastText) return;
    toastText.textContent = msg;
    toast.style.background = isError ? '#dc2626' : '#0f172a';
    toast.style.display = 'block';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.style.display = 'none'; }, 4000);
}

})();
</script>
