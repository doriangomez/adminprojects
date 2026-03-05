<?php
$basePath = $basePath ?? '';
$canApprove = !empty($canApprove);
$canManageAdvanced = !empty($canManageAdvanced);
$weekStart = $weekStart ?? new DateTimeImmutable('monday this week');
$weekValue = $weekValue ?? $weekStart->format('o-\\WW');
$periodType = (string) ($periodType ?? 'month');
$periodStart = $periodStart ?? $weekStart->modify('first day of this month')->setTime(0, 0);
$periodEnd = $periodEnd ?? $weekStart->modify('last day of this month')->setTime(0, 0);
$projectFilter = (int) ($projectFilter ?? 0);
$talentSort = (string) ($talentSort ?? 'load_desc');
$talentFilter = (int) ($talentFilter ?? 0);
$projectsForFilter = is_array($projectsForFilter ?? null) ? $projectsForFilter : [];
$executiveSummary = is_array($executiveSummary ?? null) ? $executiveSummary : [];
$approvedWeeks = is_array($approvedWeeks ?? null) ? $approvedWeeks : [];
$talentBreakdown = is_array($talentBreakdown ?? null) ? $talentBreakdown : [];
$projectBreakdown = is_array($projectBreakdown ?? null) ? $projectBreakdown : [];
$activityTypeBreakdown = is_array($activityTypeBreakdown ?? null) ? $activityTypeBreakdown : [];
$phaseBreakdown = is_array($phaseBreakdown ?? null) ? $phaseBreakdown : [];
$talentOptions = is_array($talentOptions ?? null) ? $talentOptions : [];
$managedWeekEntries = is_array($managedWeekEntries ?? null) ? $managedWeekEntries : [];

$statusMap = [
    'approved' => ['label' => 'Aprobada', 'class' => 'approved'],
    'rejected' => ['label' => 'Rechazada', 'class' => 'rejected'],
    'submitted' => ['label' => 'Enviada', 'class' => 'submitted'],
    'draft' => ['label' => 'Borrador', 'class' => 'draft'],
    'partial' => ['label' => 'Parcial', 'class' => 'partial'],
];

$weeksRegistered = count($approvedWeeks);
$weeksApproved = count(array_filter($approvedWeeks, static fn($w): bool => ((int) ($w['status_weight'] ?? 0)) >= 5));
$weeksPending = max(0, $weeksRegistered - $weeksApproved);
$approvedPercent = (float) ($executiveSummary['approved_percent'] ?? 0);
$compliancePercent = (float) ($executiveSummary['compliance_percent'] ?? 0);
?>

<div class="ts-shell">

    <!-- Navigation tabs -->
    <nav class="ts-nav-tabs">
        <a href="<?= $basePath ?>/timesheets" class="ts-tab">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Registro de horas
        </a>
        <a href="<?= $basePath ?>/approvals" class="ts-tab">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            Aprobación
        </a>
        <a href="<?= $basePath ?>/timesheets/analytics" class="ts-tab active">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            Analítica
        </a>
    </nav>

    <!-- Filters -->
    <section class="card an-filters">
        <form method="GET" action="<?= $basePath ?>/timesheets/analytics" class="an-filter-form">
            <label>Periodo
                <select name="period" id="period-select" class="ts-input">
                    <option value="month" <?= $periodType === 'month' ? 'selected' : '' ?>>Mes actual</option>
                    <option value="custom" <?= $periodType === 'custom' ? 'selected' : '' ?>>Rango personalizado</option>
                </select>
            </label>
            <label id="range-start-label" <?= $periodType !== 'custom' ? 'style="display:none"' : '' ?>>Desde
                <input type="date" name="range_start" value="<?= htmlspecialchars($periodStart->format('Y-m-d')) ?>" class="ts-input" data-range-input>
            </label>
            <label id="range-end-label" <?= $periodType !== 'custom' ? 'style="display:none"' : '' ?>>Hasta
                <input type="date" name="range_end" value="<?= htmlspecialchars($periodEnd->format('Y-m-d')) ?>" class="ts-input" data-range-input>
            </label>
            <label>Proyecto
                <select name="project_id" class="ts-input">
                    <option value="0">Todos los proyectos</option>
                    <?php foreach ($projectsForFilter as $project): ?>
                        <option value="<?= (int) ($project['project_id'] ?? 0) ?>" <?= $projectFilter === (int) ($project['project_id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($project['project'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Orden talento
                <select name="talent_sort" class="ts-input">
                    <option value="load_desc" <?= $talentSort === 'load_desc' ? 'selected' : '' ?>>Mayor carga</option>
                    <option value="compliance_asc" <?= $talentSort === 'compliance_asc' ? 'selected' : '' ?>>Menor cumplimiento</option>
                </select>
            </label>
            <button type="submit" class="ts-btn ts-btn--primary">Aplicar filtros</button>
            <a href="<?= $basePath ?>/timesheets/analytics" class="ts-btn ts-btn--secondary">Restablecer</a>
        </form>
        <p class="an-period-label">
            Mostrando datos del periodo:
            <strong><?= htmlspecialchars($periodStart->format('d/m/Y')) ?> – <?= htmlspecialchars($periodEnd->format('d/m/Y')) ?></strong>
        </p>
    </section>

    <!-- Executive KPIs -->
    <section class="an-kpi-grid">
        <div class="an-kpi">
            <span class="an-kpi-label">Total registradas</span>
            <strong class="an-kpi-value"><?= round((float) ($executiveSummary['total'] ?? 0), 1) ?>h</strong>
        </div>
        <div class="an-kpi an-kpi--approved">
            <span class="an-kpi-label">Aprobadas</span>
            <strong class="an-kpi-value"><?= round((float) ($executiveSummary['approved'] ?? 0), 1) ?>h</strong>
            <span class="an-kpi-sub"><?= round($approvedPercent, 1) ?>% del total</span>
        </div>
        <div class="an-kpi an-kpi--pending">
            <span class="an-kpi-label">Pendientes</span>
            <strong class="an-kpi-value"><?= round((float) ($executiveSummary['pending'] ?? 0), 1) ?>h</strong>
        </div>
        <div class="an-kpi an-kpi--rejected">
            <span class="an-kpi-label">Rechazadas</span>
            <strong class="an-kpi-value"><?= round((float) ($executiveSummary['rejected'] ?? 0), 1) ?>h</strong>
        </div>
        <div class="an-kpi an-kpi--draft">
            <span class="an-kpi-label">En borrador</span>
            <strong class="an-kpi-value"><?= round((float) ($executiveSummary['draft'] ?? 0), 1) ?>h</strong>
        </div>
        <div class="an-kpi <?= $compliancePercent >= 80 ? 'an-kpi--approved' : ($compliancePercent >= 50 ? 'an-kpi--pending' : 'an-kpi--rejected') ?>">
            <span class="an-kpi-label">Cumplimiento vs capacidad</span>
            <strong class="an-kpi-value"><?= round($compliancePercent, 1) ?>%</strong>
            <span class="an-kpi-sub">Capacidad: <?= round((float) ($executiveSummary['capacity'] ?? 0), 1) ?>h</span>
        </div>
    </section>

    <!-- Weeks summary + approved weeks -->
    <div class="an-two-col">

        <!-- Week summary cards -->
        <section class="card">
            <h3 class="an-section-title">Resumen de semanas del periodo</h3>
            <div class="an-week-summary-stats">
                <div class="an-stat"><span>Semanas registradas</span><strong><?= $weeksRegistered ?></strong></div>
                <div class="an-stat an-stat--good"><span>Semanas aprobadas</span><strong><?= $weeksApproved ?></strong></div>
                <div class="an-stat an-stat--warn"><span>Pendientes de aprobación</span><strong><?= $weeksPending ?></strong></div>
            </div>

            <div class="an-week-cards">
                <?php foreach ($approvedWeeks as $week):
                    $wStart = new DateTimeImmutable((string) ($week['week_start'] ?? 'now'));
                    $wEnd = new DateTimeImmutable((string) ($week['week_end'] ?? 'now'));
                    $weight = (int) ($week['status_weight'] ?? 0);
                    $state = $weight >= 5 ? 'approved' : ($weight >= 4 ? 'rejected' : 'submitted');
                    $meta = $statusMap[$state] ?? $statusMap['draft'];
                ?>
                <a class="an-week-card an-week-card--<?= $meta['class'] ?>"
                   href="<?= $basePath ?>/timesheets?week=<?= urlencode($wStart->format('o-\\WW')) ?>">
                    <div class="an-week-card-top">
                        <strong>Sem <?= htmlspecialchars($wStart->format('W')) ?></strong>
                        <span class="badge-state <?= $meta['class'] ?>"><?= htmlspecialchars($meta['label']) ?></span>
                    </div>
                    <span class="an-week-card-dates"><?= htmlspecialchars($wStart->format('d/m')) ?> – <?= htmlspecialchars($wEnd->format('d/m')) ?></span>
                    <strong class="an-week-card-hours"><?= round((float) ($week['total_hours'] ?? 0), 1) ?>h</strong>
                </a>
                <?php endforeach; ?>
                <?php if (empty($approvedWeeks)): ?>
                    <p class="an-empty">No hay semanas registradas en este periodo.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- Project breakdown -->
        <section class="card">
            <h3 class="an-section-title">Proyectos con mayor consumo</h3>
            <?php if (!empty($projectBreakdown)): ?>
            <div class="an-bar-list">
                <?php
                $maxHours = max(array_column($projectBreakdown, 'total_hours') ?: [1]);
                foreach (array_slice($projectBreakdown, 0, 8) as $row):
                    $pct = $maxHours > 0 ? round((float) ($row['total_hours'] ?? 0) / $maxHours * 100, 0) : 0;
                ?>
                <div class="an-bar-item">
                    <div class="an-bar-label">
                        <span title="<?= htmlspecialchars((string) ($row['project'] ?? '')) ?>"><?= htmlspecialchars(mb_strimwidth((string) ($row['project'] ?? ''), 0, 30, '…')) ?></span>
                        <strong><?= round((float) ($row['total_hours'] ?? 0), 1) ?>h</strong>
                    </div>
                    <div class="an-bar-track">
                        <div class="an-bar-fill" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <p class="an-empty">Sin datos de proyectos en este periodo.</p>
            <?php endif; ?>
        </section>
    </div>

    <!-- Talent breakdown -->
    <section class="card">
        <div class="an-section-header">
            <h3 class="an-section-title">Desglose por talento</h3>
            <form method="GET" action="<?= $basePath ?>/timesheets/analytics" class="an-inline-filter">
                <input type="hidden" name="period" value="<?= htmlspecialchars($periodType) ?>">
                <input type="hidden" name="range_start" value="<?= htmlspecialchars($periodStart->format('Y-m-d')) ?>">
                <input type="hidden" name="range_end" value="<?= htmlspecialchars($periodEnd->format('Y-m-d')) ?>">
                <input type="hidden" name="project_id" value="<?= $projectFilter ?>">
                <select name="talent_sort" class="ts-input ts-input--sm" onchange="this.form.submit()">
                    <option value="load_desc" <?= $talentSort === 'load_desc' ? 'selected' : '' ?>>Mayor carga</option>
                    <option value="compliance_asc" <?= $talentSort === 'compliance_asc' ? 'selected' : '' ?>>Menor cumplimiento</option>
                </select>
            </form>
        </div>
        <?php if (!empty($talentBreakdown)): ?>
        <div class="table-wrap">
            <table class="clean-table">
                <thead>
                    <tr>
                        <th>Talento</th>
                        <th>Total</th>
                        <th>Aprobadas</th>
                        <th>Rechazadas</th>
                        <th>Borrador</th>
                        <th>% Cumplimiento</th>
                        <th>Última semana enviada</th>
                        <th>Última semana aprobada</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($talentBreakdown as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($row['talent_name'] ?? '')) ?></td>
                        <td><strong><?= round((float) ($row['total_hours'] ?? 0), 1) ?>h</strong></td>
                        <td class="an-cell--good"><?= round((float) ($row['approved_hours'] ?? 0), 1) ?>h</td>
                        <td class="an-cell--bad"><?= round((float) ($row['rejected_hours'] ?? 0), 1) ?>h</td>
                        <td><?= round((float) ($row['draft_hours'] ?? 0), 1) ?>h</td>
                        <td>
                            <?php $c = round((float) ($row['compliance_percent'] ?? 0), 1); ?>
                            <span class="an-compliance <?= $c >= 80 ? 'an-compliance--good' : ($c >= 50 ? 'an-compliance--warn' : 'an-compliance--bad') ?>"><?= $c ?>%</span>
                        </td>
                        <td class="an-cell--muted"><?= htmlspecialchars((string) ($row['last_week_submitted'] ?? '—')) ?></td>
                        <td class="an-cell--muted"><?= htmlspecialchars((string) ($row['last_week_approved'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="an-empty">Sin datos de talento para este periodo.</p>
        <?php endif; ?>
    </section>

    <!-- Two-column breakdown -->
    <div class="an-two-col">

        <!-- Activity type breakdown -->
        <section class="card">
            <h3 class="an-section-title">Distribución por tipo de actividad</h3>
            <?php if (!empty($activityTypeBreakdown)): ?>
            <div class="an-donut-list">
                <?php
                $totalActivity = array_sum(array_column($activityTypeBreakdown, 'total_hours')) ?: 1;
                foreach ($activityTypeBreakdown as $row):
                    $pct = round((float) ($row['total_hours'] ?? 0) / $totalActivity * 100, 1);
                ?>
                <div class="an-type-item">
                    <div class="an-type-info">
                        <span><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) ($row['activity_type'] ?? 'sin clasificar')))) ?></span>
                        <strong><?= round((float) ($row['total_hours'] ?? 0), 1) ?>h</strong>
                    </div>
                    <div class="an-bar-track">
                        <div class="an-bar-fill an-bar-fill--accent" style="width:<?= $pct ?>%"></div>
                    </div>
                    <span class="an-type-pct"><?= $pct ?>%</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <p class="an-empty">Sin datos de tipos de actividad.</p>
            <?php endif; ?>
        </section>

        <!-- Phase breakdown -->
        <section class="card">
            <h3 class="an-section-title">Horas por fase / subfase</h3>
            <?php if (!empty($phaseBreakdown)): ?>
            <div class="table-wrap">
                <table class="clean-table">
                    <thead><tr><th>Fase</th><th>Subfase</th><th>Horas</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($phaseBreakdown, 0, 12) as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($row['phase_name'] ?? '—')) ?></td>
                            <td class="an-cell--muted"><?= htmlspecialchars((string) ($row['subphase_name'] ?? '—')) ?></td>
                            <td><strong><?= round((float) ($row['total_hours'] ?? 0), 1) ?>h</strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="an-empty">Sin datos de fases para este periodo.</p>
            <?php endif; ?>
        </section>
    </div>

    <!-- Approved weeks detail table -->
    <section class="card">
        <h3 class="an-section-title">Historial de semanas aprobadas</h3>
        <?php if (!empty($approvedWeeks)): ?>
        <div class="table-wrap">
            <table class="clean-table">
                <thead>
                    <tr>
                        <th>Semana</th>
                        <th>Rango</th>
                        <th>Total horas</th>
                        <th>Estado</th>
                        <th>Fecha aprobación</th>
                        <th>Aprobador</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($approvedWeeks as $week):
                    $wStart = new DateTimeImmutable((string) ($week['week_start'] ?? 'now'));
                    $wEnd = new DateTimeImmutable((string) ($week['week_end'] ?? 'now'));
                    $weight = (int) ($week['status_weight'] ?? 0);
                    $state = $weight >= 5 ? 'approved' : ($weight >= 4 ? 'rejected' : 'submitted');
                    $meta = $statusMap[$state] ?? $statusMap['draft'];
                ?>
                    <tr>
                        <td><a href="<?= $basePath ?>/timesheets?week=<?= urlencode($wStart->format('o-\\WW')) ?>">Semana <?= htmlspecialchars($wStart->format('W')) ?></a></td>
                        <td><?= htmlspecialchars($wStart->format('d/m/Y')) ?> – <?= htmlspecialchars($wEnd->format('d/m/Y')) ?></td>
                        <td><strong><?= round((float) ($week['total_hours'] ?? 0), 1) ?>h</strong></td>
                        <td><span class="badge-state <?= $meta['class'] ?>"><?= htmlspecialchars($meta['label']) ?></span></td>
                        <td class="an-cell--muted"><?= htmlspecialchars((string) ($week['approved_at'] ?? '—')) ?></td>
                        <td class="an-cell--muted"><?= htmlspecialchars((string) ($week['approver_name'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="an-empty">No hay semanas en este periodo.</p>
        <?php endif; ?>
    </section>

    <?php if ($canApprove || $canManageAdvanced): ?>
    <!-- Manager control -->
    <section class="card" id="manager-view">
        <h3 class="an-section-title">Control gerencial de semana</h3>
        <p class="an-empty">Aprobación/rechazo por registro, edición controlada, reapertura y eliminación con auditoría.</p>

        <form method="GET" action="<?= $basePath ?>/timesheets/analytics" class="an-filter-form" style="margin-bottom:12px">
            <input type="hidden" name="period" value="<?= htmlspecialchars($periodType) ?>">
            <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
            <label>Semana
                <input type="week" name="week" value="<?= htmlspecialchars($weekValue) ?>" class="ts-input">
            </label>
            <label>Talento
                <select name="talent_id" class="ts-input">
                    <option value="0">Todos</option>
                    <?php foreach ($talentOptions as $tal): ?>
                        <option value="<?= (int) ($tal['id'] ?? 0) ?>" <?= $talentFilter === (int) ($tal['id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($tal['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="ts-btn ts-btn--secondary">Filtrar</button>
        </form>

        <?php if (!empty($managedWeekEntries)): ?>
        <div class="table-wrap">
            <table class="clean-table">
                <thead><tr><th>Fecha</th><th>Talento</th><th>Proyecto</th><th>Horas</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($managedWeekEntries as $entry): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($entry['date'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($entry['talent_name'] ?? $entry['user_name'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($entry['project_name'] ?? '')) ?></td>
                        <td><?= round((float) ($entry['hours'] ?? 0), 1) ?>h</td>
                        <td><span class="badge-state <?= htmlspecialchars((string) ($entry['status'] ?? 'draft')) ?>"><?= htmlspecialchars((string) ($entry['status'] ?? 'draft')) ?></span></td>
                        <td class="an-actions-cell">
                            <?php if ($canApprove && in_array((string) ($entry['status'] ?? ''), ['submitted','pending','pending_approval'], true)): ?>
                                <form method="POST" action="<?= $basePath ?>/timesheets/<?= (int) ($entry['id'] ?? 0) ?>/approve" class="inline-form">
                                    <button type="submit" class="ts-btn ts-btn--sm ts-btn--submit">Aprobar</button>
                                </form>
                                <form method="POST" action="<?= $basePath ?>/timesheets/<?= (int) ($entry['id'] ?? 0) ?>/reject" class="inline-form an-reject-form">
                                    <input type="text" name="comment" placeholder="Motivo" required class="ts-input ts-input--sm">
                                    <button type="submit" class="ts-btn ts-btn--sm ts-btn--danger">Rechazar</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($canManageAdvanced): ?>
                                <form method="POST" action="<?= $basePath ?>/timesheets/admin-action" class="inline-form">
                                    <input type="hidden" name="admin_action" value="update_hours">
                                    <input type="hidden" name="timesheet_id" value="<?= (int) ($entry['id'] ?? 0) ?>">
                                    <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                                    <input type="number" step="0.25" min="0" max="24" name="hours" value="<?= htmlspecialchars((string) ($entry['hours'] ?? 0)) ?>" required class="ts-input ts-input--sm ts-input--num">
                                    <input type="text" name="reason" placeholder="Motivo auditoría" required class="ts-input ts-input--sm">
                                    <button type="submit" class="ts-btn ts-btn--sm ts-btn--secondary">Editar</button>
                                </form>
                                <form method="POST" action="<?= $basePath ?>/timesheets/admin-action" class="inline-form" data-confirm="¿Eliminar este registro?">
                                    <input type="hidden" name="admin_action" value="delete_entry">
                                    <input type="hidden" name="timesheet_id" value="<?= (int) ($entry['id'] ?? 0) ?>">
                                    <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                                    <input type="text" name="reason" placeholder="Motivo auditoría" required class="ts-input ts-input--sm">
                                    <button type="submit" class="ts-btn ts-btn--sm ts-btn--danger">Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="an-empty">No hay registros para esta semana y filtro seleccionados.</p>
        <?php endif; ?>

        <?php if ($canManageAdvanced): ?>
        <div class="an-admin-actions">
            <div class="an-admin-block">
                <h4>Reapertura controlada</h4>
                <form method="POST" action="<?= $basePath ?>/timesheets/admin-action" class="an-admin-form">
                    <input type="hidden" name="admin_action" value="reopen_week">
                    <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                    <input type="hidden" name="week_start" value="<?= htmlspecialchars($weekStart->format('Y-m-d')) ?>">
                    <select name="talent_id" class="ts-input">
                        <option value="0">Toda la semana</option>
                        <?php foreach ($talentOptions as $tal): ?>
                            <option value="<?= (int) ($tal['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($tal['name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="reason" required placeholder="Motivo de reapertura" class="ts-input">
                    <button type="submit" class="ts-btn ts-btn--secondary">Reabrir semana</button>
                </form>
            </div>
            <div class="an-admin-block an-admin-block--danger">
                <h4>Eliminación masiva</h4>
                <form method="POST" action="<?= $basePath ?>/timesheets/admin-action" class="an-admin-form" data-confirm="¿Confirmar eliminación masiva?">
                    <input type="hidden" name="admin_action" value="delete_week">
                    <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                    <input type="hidden" name="week_start" value="<?= htmlspecialchars($weekStart->format('Y-m-d')) ?>">
                    <select name="talent_id" class="ts-input">
                        <option value="0">Toda la semana</option>
                        <?php foreach ($talentOptions as $tal): ?>
                            <option value="<?= (int) ($tal['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($tal['name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="reason" required placeholder="Motivo" class="ts-input">
                    <input type="text" name="confirm_token" required placeholder='Escribir "ELIMINAR MASIVO"' class="ts-input">
                    <button type="submit" class="ts-btn ts-btn--danger">Eliminar masivo</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <div class="an-export-row">
        <button class="ts-btn ts-btn--secondary" onclick="window.print()">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Exportar / Imprimir informe
        </button>
    </div>

</div>

<style>
/* ===== ANALYTICS VIEW STYLES ===== */
.ts-shell{display:flex;flex-direction:column;gap:12px;max-width:1400px;margin:0 auto}

/* Reuse nav tabs from index */
.ts-nav-tabs{display:flex;gap:0;border-bottom:2px solid var(--border);background:var(--surface);border-radius:10px 10px 0 0;overflow:hidden}
.ts-tab{display:flex;align-items:center;gap:6px;padding:10px 20px;text-decoration:none;color:var(--text-secondary);font-size:13px;font-weight:500;border-bottom:2px solid transparent;margin-bottom:-2px;transition:color .15s,border-color .15s;white-space:nowrap}
.ts-tab:hover{color:var(--primary)}
.ts-tab.active{color:var(--primary);border-bottom-color:var(--primary);font-weight:600}

/* Filters */
.an-filters{padding:14px 18px}
.an-filter-form{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.an-filter-form label{display:flex;flex-direction:column;gap:4px;font-size:13px;font-weight:500}
.an-period-label{font-size:12px;color:var(--text-secondary);margin:8px 0 0}

/* KPIs */
.an-kpi-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:10px}
.an-kpi{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px 16px;display:flex;flex-direction:column;gap:4px}
.an-kpi-label{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--text-secondary)}
.an-kpi-value{font-size:1.5rem;font-weight:700}
.an-kpi-sub{font-size:11px;color:var(--text-secondary)}
.an-kpi--approved{border-top:4px solid var(--success,#16a34a)}
.an-kpi--approved .an-kpi-value{color:var(--success,#16a34a)}
.an-kpi--rejected{border-top:4px solid var(--danger,#dc2626)}
.an-kpi--rejected .an-kpi-value{color:var(--danger,#dc2626)}
.an-kpi--pending{border-top:4px solid var(--warning,#eab308)}
.an-kpi--pending .an-kpi-value{color:var(--warning,#eab308)}
.an-kpi--draft{border-top:4px solid var(--neutral,#6b7280)}

/* Two-column layout */
.an-two-col{display:grid;grid-template-columns:1fr 1fr;gap:12px}

/* Section headers */
.an-section-title{margin:0 0 12px;font-size:1rem;font-weight:600}
.an-section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.an-section-header .an-section-title{margin:0}

/* Week cards */
.an-week-summary-stats{display:flex;gap:10px;margin-bottom:14px}
.an-stat{background:var(--background,#f8fafc);border:1px solid var(--border);border-radius:10px;padding:8px 14px;display:flex;flex-direction:column;gap:2px}
.an-stat span{font-size:11px;color:var(--text-secondary)}
.an-stat strong{font-size:1.2rem;font-weight:700}
.an-stat--good{border-left:3px solid var(--success,#16a34a)}
.an-stat--warn{border-left:3px solid var(--warning,#eab308)}
.an-week-cards{display:flex;gap:8px;flex-wrap:wrap}
.an-week-card{display:flex;flex-direction:column;gap:4px;border:1px solid var(--border);border-radius:10px;padding:10px 12px;text-decoration:none;color:inherit;min-width:100px;transition:box-shadow .15s}
.an-week-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.1)}
.an-week-card-top{display:flex;justify-content:space-between;align-items:center;gap:6px}
.an-week-card-dates{font-size:11px;color:var(--text-secondary)}
.an-week-card-hours{font-size:1.1rem}
.an-week-card--approved{border-left:4px solid var(--success,#16a34a)}
.an-week-card--rejected{border-left:4px solid var(--danger,#dc2626)}
.an-week-card--submitted{border-left:4px solid var(--warning,#eab308)}
.an-week-card--draft{border-left:4px solid var(--neutral,#6b7280)}

/* Bar chart */
.an-bar-list{display:flex;flex-direction:column;gap:10px}
.an-bar-item{display:flex;flex-direction:column;gap:4px}
.an-bar-label{display:flex;justify-content:space-between;font-size:12px}
.an-bar-track{height:8px;background:var(--border);border-radius:999px;overflow:hidden}
.an-bar-fill{height:100%;background:var(--primary);border-radius:999px;transition:width .3s}
.an-bar-fill--accent{background:var(--accent,#8b5cf6)}

/* Activity type */
.an-donut-list{display:flex;flex-direction:column;gap:10px}
.an-type-item{display:flex;flex-direction:column;gap:4px}
.an-type-info{display:flex;justify-content:space-between;font-size:12px}
.an-type-pct{font-size:11px;color:var(--text-secondary);text-align:right}

/* Table */
.table-wrap{overflow:auto}
.clean-table{width:100%;border-collapse:collapse;font-size:13px}
.clean-table th{text-align:left;padding:8px 12px;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--text-secondary);border-bottom:2px solid var(--border);white-space:nowrap}
.clean-table td{padding:8px 12px;border-bottom:1px solid var(--border);vertical-align:middle}
.clean-table tr:last-child td{border-bottom:none}
.an-cell--good{color:var(--success,#16a34a)}
.an-cell--bad{color:var(--danger,#dc2626)}
.an-cell--muted{color:var(--text-secondary);font-size:12px}
.an-compliance{padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}
.an-compliance--good{background:#dcfce7;color:#166534}
.an-compliance--warn{background:#fef3c7;color:#92400e}
.an-compliance--bad{background:#fee2e2;color:#991b1b}

/* Manager actions */
.an-actions-cell{display:flex;flex-wrap:wrap;gap:4px;align-items:center}
.an-reject-form{display:inline-flex;gap:4px;align-items:center}
.ts-btn--sm{padding:4px 10px;font-size:12px}
.ts-btn--danger{background:var(--danger,#dc2626);color:#fff}
.ts-btn--danger:hover{opacity:.9}
.ts-input--sm{padding:4px 8px;font-size:12px}
.ts-input--num{width:70px}
.an-admin-actions{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px;padding-top:14px;border-top:1px solid var(--border)}
.an-admin-block h4{margin:0 0 8px;font-size:13px}
.an-admin-form{display:flex;flex-direction:column;gap:8px}
.an-admin-block--danger h4{color:var(--danger,#dc2626)}
.an-inline-filter{display:inline-flex;align-items:center;gap:6px}

/* Shared */
.ts-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;border:none;cursor:pointer;font-size:13px;font-weight:500;text-decoration:none;transition:background .15s,opacity .15s;white-space:nowrap}
.ts-btn--primary{background:var(--primary);color:#fff}
.ts-btn--primary:hover{opacity:.9}
.ts-btn--secondary{background:var(--surface);color:var(--text-primary);border:1px solid var(--border)}
.ts-btn--secondary:hover{background:var(--border)}
.ts-input{padding:7px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--surface);color:var(--text-primary);transition:border-color .15s;width:100%;box-sizing:border-box}
.ts-input:focus{outline:none;border-color:var(--primary)}
.badge-state{font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px}
.badge-state.approved{background:#dcfce7;color:#166534}
.badge-state.rejected{background:#fee2e2;color:#991b1b}
.badge-state.submitted{background:#fef3c7;color:#92400e}
.badge-state.draft{background:#e5e7eb;color:#374151}
.badge-state.partial{background:#dbeafe;color:#1e40af}
.inline-form{display:inline}
.an-empty{color:var(--text-secondary);font-size:13px;margin:0;padding:8px 0}
.an-export-row{display:flex;justify-content:flex-end;padding:4px 0}

@media(max-width:1100px){.an-kpi-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:800px){.an-two-col,.an-admin-actions{grid-template-columns:1fr}.an-kpi-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:500px){.an-kpi-grid{grid-template-columns:1fr}.an-filter-form{flex-direction:column}}
</style>

<script>
(() => {
  const periodSelect = document.getElementById('period-select');
  const startLabel = document.getElementById('range-start-label');
  const endLabel = document.getElementById('range-end-label');

  periodSelect?.addEventListener('change', () => {
    const isCustom = periodSelect.value === 'custom';
    if (startLabel) startLabel.style.display = isCustom ? '' : 'none';
    if (endLabel) endLabel.style.display = isCustom ? '' : 'none';
  });

  // Confirm dangerous admin actions
  document.querySelectorAll('[data-confirm]').forEach(form => {
    form.addEventListener('submit', e => {
      if (!confirm(form.dataset.confirm || '¿Confirmar?')) e.preventDefault();
    });
  });
})();
</script>
