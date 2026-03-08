<?php
$basePath = $basePath ?? '';
$canReport = !empty($canReport);
$weekStart = $weekStart ?? new DateTimeImmutable('monday this week');
$weekEnd = $weekEnd ?? $weekStart->modify('+6 days');
$weekValue = $weekValue ?? $weekStart->format('o-\\WW');
$weeklyGrid = is_array($weeklyGrid ?? null) ? $weeklyGrid : [];
$gridDays = is_array($weeklyGrid['days'] ?? null) ? $weeklyGrid['days'] : [];
$dayTotals = is_array($weeklyGrid['day_totals'] ?? null) ? $weeklyGrid['day_totals'] : [];
$activitiesByDay = is_array($weeklyGrid['activities_by_day'] ?? null) ? $weeklyGrid['activities_by_day'] : [];
$projectsForTimesheet = is_array($projectsForTimesheet ?? null) ? $projectsForTimesheet : [];
$clientsForTimesheet = is_array($clientsForTimesheet ?? null) ? $clientsForTimesheet : [];
$tasksForTimesheet = is_array($tasksForTimesheet ?? null) ? $tasksForTimesheet : [];
$recentActivitySuggestions = is_array($recentActivitySuggestions ?? null) ? $recentActivitySuggestions : [];
$activityTypes = is_array($activityTypes ?? null) ? $activityTypes : [];
$canApprove = !empty($canApprove);
$selectedWeekSummary = is_array($selectedWeekSummary ?? null) ? $selectedWeekSummary : [];
$weekIndicators = is_array($weekIndicators ?? null) ? $weekIndicators : [];
$dayStatuses = is_array($weeklyGrid['day_statuses'] ?? null) ? $weeklyGrid['day_statuses'] : [];
$dayDraftEntries = is_array($weeklyGrid['day_draft_entries'] ?? null) ? $weeklyGrid['day_draft_entries'] : [];
$weekStatus = (string) ($selectedWeekSummary['status'] ?? 'draft');
$currentUserName = trim((string) ($currentUserName ?? 'Usuario')) ?: 'Usuario';
$timesheetNotice = trim((string) ($timesheetNotice ?? ''));
$capacityTooltipLines = is_array($weekIndicators['capacity_tooltip_lines'] ?? null) ? $weekIndicators['capacity_tooltip_lines'] : [];
$capacityTooltip = $capacityTooltipLines !== []
    ? implode("\n", array_map(static fn ($line): string => (string) $line, $capacityTooltipLines))
    : 'Sin detalle de capacidad semanal.';
$statusMeta = [
    'draft' => ['label' => 'Borrador', 'class' => 'draft'],
    'submitted' => ['label' => 'Pendiente', 'class' => 'submitted'],
    'partial' => ['label' => 'Pendiente', 'class' => 'submitted'],
    'pending' => ['label' => 'Pendiente', 'class' => 'submitted'],
    'pending_approval' => ['label' => 'Pendiente', 'class' => 'submitted'],
    'approved' => ['label' => 'Aprobado', 'class' => 'approved'],
    'rejected' => ['label' => 'Rechazado', 'class' => 'rejected'],
];
$status = $statusMeta[$weekStatus] ?? $statusMeta['draft'];
$weekFullyLocked = true;
foreach ($gridDays as $dayMeta) {
    $dayKey = (string) ($dayMeta['key'] ?? '');
    $dayStatus = (string) ($dayStatuses[$dayKey] ?? 'draft');
    $isWorkingDay = !empty($dayMeta['is_working']);
    if ($isWorkingDay && !in_array($dayStatus, ['submitted', 'approved'], true)) {
        $weekFullyLocked = false;
        break;
    }
}
$hasDraftEntriesInWeek = false;
foreach ($dayDraftEntries as $draftCount) {
    if ((int) $draftCount > 0) {
        $hasDraftEntriesInWeek = true;
        break;
    }
}
$activityTypeMeta = [
    'desarrollo' => ['label' => 'Desarrollo', 'class' => 'type-dev'],
    'development' => ['label' => 'Desarrollo', 'class' => 'type-dev'],
    'reunion' => ['label' => 'Reunión', 'class' => 'type-meeting'],
    'reunión' => ['label' => 'Reunión', 'class' => 'type-meeting'],
    'meeting' => ['label' => 'Reunión', 'class' => 'type-meeting'],
    'soporte' => ['label' => 'Soporte', 'class' => 'type-support'],
    'support' => ['label' => 'Soporte', 'class' => 'type-support'],
    'gestion_pm' => ['label' => 'Gestión PM', 'class' => 'type-pm'],
    'gestión_pm' => ['label' => 'Gestión PM', 'class' => 'type-pm'],
    'pm_management' => ['label' => 'Gestión PM', 'class' => 'type-pm'],
    'investigacion' => ['label' => 'Investigación', 'class' => 'type-research'],
    'investigación' => ['label' => 'Investigación', 'class' => 'type-research'],
    'research' => ['label' => 'Investigación', 'class' => 'type-research'],
];
$monthShortNames = [
    1 => 'Ene',
    2 => 'Feb',
    3 => 'Mar',
    4 => 'Abr',
    5 => 'May',
    6 => 'Jun',
    7 => 'Jul',
    8 => 'Ago',
    9 => 'Sep',
    10 => 'Oct',
    11 => 'Nov',
    12 => 'Dic',
];
$weekDayShortNames = [
    1 => 'Lun',
    2 => 'Mar',
    3 => 'Mie',
    4 => 'Jue',
    5 => 'Vie',
    6 => 'Sab',
    7 => 'Dom',
];
$daysJson = [];
foreach ($gridDays as $day) {
    $daysJson[(string) ($day['key'] ?? '')] = (string) (($day['label'] ?? '') . ' ' . ($day['number'] ?? ''));
}
?>

<section class="timesheet-ux">
    <div class="timesheet-tabs card">
        <a class="tab active" href="<?= $basePath ?>/timesheets?week=<?= urlencode($weekValue) ?>">Registro de horas</a>
        <a class="tab" href="<?= $basePath ?>/approvals">Aprobación de horas</a>
        <?php if ($canApprove): ?>
            <a class="tab" href="<?= $basePath ?>/timesheets/analytics?week=<?= urlencode($weekValue) ?>">Analítica gerencial</a>
        <?php endif; ?>
    </div>

    <?php if (!$canReport): ?>
        <section class="card">
            <h3>Sin permisos de captura</h3>
            <p class="section-muted">Tu usuario no tiene habilitado el registro operativo de horas.</p>
        </section>
    <?php else: ?>
        <header class="timesheet-sticky-header card">
            <form method="GET" class="header-week-form">
                <label>Semana actual
                    <input type="week" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                </label>
                <button type="submit" class="btn-link">Cambiar</button>
            </form>
            <div class="header-badges">
                <span class="pill neutral">Semana: <strong><?= htmlspecialchars($weekStart->format('d/m')) ?> - <?= htmlspecialchars($weekEnd->format('d/m')) ?></strong></span>
                <span class="pill neutral" title="<?= htmlspecialchars($capacityTooltip) ?>">Capacidad semanal: <strong><?= round((float) ($weekIndicators['weekly_capacity'] ?? 40), 2) ?>h</strong></span>
            </div>
            <div class="header-actions">
                <button type="button" class="btn primary" id="focus-quick-add" <?= $weekFullyLocked ? 'disabled' : '' ?>>+ Registrar actividad</button>
                <button type="button" class="btn" id="duplicate-day-trigger" <?= $weekFullyLocked ? 'disabled' : '' ?>>Duplicar día</button>
                <form method="POST" action="<?= $basePath ?>/timesheets/submit-week">
                    <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                    <button type="submit" class="btn success" <?= !$hasDraftEntriesInWeek ? 'disabled' : '' ?>>Enviar semana</button>
                </form>
                <?php if (in_array($weekStatus, ['submitted', 'partial'], true)): ?>
                    <form method="POST" action="<?= $basePath ?>/timesheets/cancel-week">
                        <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                        <button type="submit" class="btn">Retirar envío</button>
                    </form>
                <?php endif; ?>
                <?php if ($weekStatus === 'approved' && !empty($canManageWorkflow)): ?>
                    <form method="POST" action="<?= $basePath ?>/timesheets/reopen-own-week" class="header-inline-form">
                        <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                        <input type="text" name="comment" placeholder="Motivo de reapertura" required>
                        <button type="submit" class="btn">Solicitar reapertura</button>
                    </form>
                <?php endif; ?>
            </div>
        </header>
        <section class="week-status-row card">
            <span class="section-muted">Estado semana:</span>
            <span class="pill status <?= htmlspecialchars($status['class']) ?>"> <?= htmlspecialchars(strtoupper($status['label'])) ?> </span>
        </section>
        <?php if ($timesheetNotice !== ''): ?>
            <section class="card week-notice-banner"><?= htmlspecialchars($timesheetNotice) ?></section>
        <?php endif; ?>
        <?php if ($weekFullyLocked): ?>
            <section class="card week-locked-banner">Todos los días registrados están enviados o aprobados.</section>
        <?php endif; ?>

        <section class="indicators-grid">
            <article class="card indicator" title="<?= htmlspecialchars($capacityTooltip) ?>"><span>Horas registradas / <?= round((float) ($weekIndicators['weekly_capacity'] ?? 40), 2) ?>h</span><strong><?= round((float) ($weekIndicators['week_total'] ?? 0), 2) ?>h</strong></article>
            <article class="card indicator"><span>Capacidad restante</span><strong><?= round((float) ($weekIndicators['remaining_capacity'] ?? 0), 2) ?>h</strong></article>
            <article class="card indicator"><span>Progreso semanal</span><strong><?= round((float) ($weekIndicators['compliance_percent'] ?? 0), 2) ?>%</strong></article>
            <article class="card indicator"><span>Proyecto con mayor carga</span><strong><?= htmlspecialchars((string) ($weekIndicators['top_project'] ?? 'Sin datos')) ?></strong><small><?= round((float) ($weekIndicators['top_project_hours'] ?? 0), 2) ?>h</small></article>
        </section>
        <section class="indicators-grid status-breakdown">
            <article class="card indicator status-approved"><span>Horas aprobadas</span><strong><?= round((float) ($selectedWeekSummary['hours_approved'] ?? 0), 2) ?>h</strong></article>
            <article class="card indicator status-pending"><span>Horas pendientes</span><strong><?= round((float) ($selectedWeekSummary['hours_pending'] ?? 0), 2) ?>h</strong></article>
            <article class="card indicator status-rejected"><span>Horas rechazadas</span><strong><?= round((float) ($selectedWeekSummary['hours_rejected'] ?? 0), 2) ?>h</strong></article>
        </section>

        <section class="timesheet-main-layout">
            <div class="calendar-column card">
                <div class="calendar-heading">
                    <h3>Actividades registradas de la semana</h3>
                    <p class="section-muted">Arrastra una actividad a otro día para moverla. Cada chip es editable.</p>
                </div>
                <?php if (round((float) ($weekIndicators['week_total'] ?? 0), 2) <= 0): ?>
                    <div class="calendar-empty-banner">
                        <strong>Semana sin registros</strong>
                        <small>Comienza el registro de tu semana.</small>
                        <?php if (!$weekFullyLocked): ?>
                            <button type="button" class="btn-xs register-activity-btn">+ Registrar actividad</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="week-calendar-grid">
                    <?php foreach ($gridDays as $day): ?>
                        <?php
                        $dayDate = (string) ($day['key'] ?? '');
                        $items = is_array($activitiesByDay[$dayDate] ?? null) ? $activitiesByDay[$dayDate] : [];
                        $dayDateObj = new DateTimeImmutable($dayDate);
                        $dayWeekNumber = (int) $dayDateObj->format('N');
                        $dayNumber = $dayDateObj->format('d');
                        $monthNumber = (int) $dayDateObj->format('n');
                        $monthShort = $monthShortNames[$monthNumber] ?? $dayDateObj->format('M');
                        $weekDayShort = $weekDayShortNames[$dayWeekNumber] ?? (string) ($day['label'] ?? '');
                        $dayLabel = trim($weekDayShort . ' ' . $dayNumber . ' ' . $monthShort);
                        $dayType = (string) ($day['day_type'] ?? ($dayWeekNumber >= 6 ? 'non_working' : 'working'));
                        $isWorkingDay = !empty($day['is_working']);
                        $availableHours = (float) ($day['available_hours'] ?? 0);
                        $absenceType = trim((string) ($day['absence_type'] ?? ''));
                        $absenceLabel = trim((string) ($day['absence_label'] ?? ''));
                        $isFullDayAbsence = !empty($day['is_full_day_absence']) && $availableHours <= 0.001;
                        $isAbsenceDay = $absenceType !== '';
                        $isBlockedDay = !$isWorkingDay || $isFullDayAbsence;
                        $isHoliday = $dayType === 'holiday';
                        $daySpecialName = trim((string) ($day['day_name'] ?? ''));
                        $dayStatus = (string) ($dayStatuses[$dayDate] ?? 'draft');
                        $dayStatusMeta = $statusMeta[$dayStatus] ?? $statusMeta['draft'];
                        $dayHasDraftEntries = (int) ($dayDraftEntries[$dayDate] ?? 0) > 0;
                        $isWorkflowLocked = in_array($dayStatus, ['submitted', 'approved'], true);
                        $canEditDay = !$isBlockedDay && !$isWorkflowLocked;
                        ?>
                        <article class="day-card<?= $isHoliday ? ' holiday-day' : '' ?><?= !$isHoliday && $isBlockedDay ? ' non-working-day' : '' ?><?= $isBlockedDay ? ' non-working' : '' ?>" data-drop-day="<?= htmlspecialchars($dayDate) ?>" data-non-working="<?= $isBlockedDay ? '1' : '0' ?>" data-day-type="<?= htmlspecialchars($dayType) ?>" data-day-name="<?= htmlspecialchars($daySpecialName) ?>" data-day-status="<?= htmlspecialchars($dayStatus) ?>">
                            <header class="day-card-header">
                                <strong><?= htmlspecialchars($dayLabel) ?></strong>
                                <?php if ($dayType === 'holiday'): ?>
                                    <span class="day-state">🎉 Festivo</span>
                                <?php elseif ($isFullDayAbsence): ?>
                                    <span class="day-state absence"><?= htmlspecialchars($absenceLabel !== '' ? $absenceLabel : 'Ausencia') ?></span>
                                <?php elseif ($isAbsenceDay): ?>
                                    <span class="day-state absence partial">Ausencia parcial</span>
                                <?php elseif ($isBlockedDay): ?>
                                    <span class="day-state">No laboral</span>
                                <?php endif; ?>
                            </header>
                            <?php if ($daySpecialName !== ''): ?>
                                <div class="day-special-name"><?= htmlspecialchars($daySpecialName) ?></div>
                            <?php endif; ?>
                            <div class="day-total">Total: <strong><?= round((float) ($dayTotals[$dayDate] ?? 0), 2) ?>h</strong></div>
                            <div class="day-status-pill pill status <?= htmlspecialchars($dayStatusMeta['class']) ?>"><?= htmlspecialchars(strtoupper($dayStatusMeta['label'])) ?></div>
                            <div class="day-actions">
                                <form method="POST" action="<?= $basePath ?>/timesheets/submit-day">
                                    <input type="hidden" name="date" value="<?= htmlspecialchars($dayDate) ?>">
                                    <button type="submit" class="btn-xs" <?= (!$canEditDay || !$dayHasDraftEntries) ? 'disabled' : '' ?>>Enviar día</button>
                                </form>
                            </div>
                            <?php if ($dayStatus === 'submitted'): ?>
                                <small class="section-muted">Registro enviado para aprobación.</small>
                            <?php endif; ?>
                            <?php if ($canEditDay): ?>
                                <div class="day-drop-hint">Arrastra una actividad y sueltala aqui</div>
                            <?php endif; ?>
                            <?php if ($items === []): ?>
                                <div class="day-empty-state">
                                    <small>No hay actividades para este dia.</small>
                                    <?php if ($canEditDay): ?>
                                        <button type="button" class="btn-xs register-activity-btn" data-prefill-date="<?= htmlspecialchars($dayDate) ?>">+ Registrar actividad</button>
                                    <?php elseif ($isHoliday): ?>
                                        <small>Este dia es festivo. Registro bloqueado.</small>
                                    <?php elseif ($isWorkflowLocked): ?>
                                        <small>Este dia está enviado/aprobado. Registro bloqueado.</small>
                                    <?php elseif ($isBlockedDay): ?>
                                        <small>Este dia es no laboral. Registro bloqueado.</small>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <ul class="activity-list">
                                    <?php foreach ($items as $item): ?>
                                        <?php
                                        $itemId = (int) ($item['id'] ?? 0);
                                        $itemProject = (string) ($item['project'] ?? 'Proyecto');
                                        $itemHours = (float) ($item['hours'] ?? 0);
                                        $itemDesc = trim((string) ($item['activity_description'] ?? '')) ?: trim((string) ($item['activity_type'] ?? 'Actividad'));
                                        $itemComment = (string) ($item['comment'] ?? '');
                                        $itemStatus = (string) ($item['status'] ?? 'draft');
                                        $itemApprovalComment = trim((string) ($item['approval_comment'] ?? ''));
                                        $itemStatusMeta = $statusMeta[$itemStatus] ?? $statusMeta['draft'];
                                        $itemType = strtolower(trim((string) ($item['activity_type'] ?? '')));
                                        $typeMeta = $activityTypeMeta[$itemType] ?? ['label' => 'Investigación', 'class' => 'type-research'];
                                        $taskTooltip = $itemDesc !== '' ? $itemDesc : 'Sin tarea';
                                        $chipTooltip = 'Proyecto: ' . $itemProject
                                            . "\nTarea: " . $taskTooltip
                                            . "\nHoras: " . round($itemHours, 2)
                                            . "\nTipo: " . $typeMeta['label']
                                            . "\nEstado: " . $itemStatusMeta['label']
                                            . "\nUsuario: " . $currentUserName;
                                        ?>
                                        <li class="activity-chip <?= htmlspecialchars($typeMeta['class']) ?><?= $canEditDay ? ' is-draggable' : ' is-locked' ?>" <?= $canEditDay ? 'draggable="true"' : '' ?> data-activity-id="<?= $itemId ?>" title="<?= htmlspecialchars($chipTooltip) ?>">
                                            <div class="chip-main">
                                                <span class="chip-hours">[<?= round($itemHours, 2) ?>h]</span>
                                                <span class="chip-status pill status <?= htmlspecialchars($itemStatusMeta['class']) ?>"><?= htmlspecialchars($itemStatusMeta['label']) ?></span>
                                                <?php if ($canEditDay): ?>
                                                    <span class="chip-drag-hint" aria-hidden="true">⋮⋮ Arrastrar</span>
                                                <?php endif; ?>
                                                <strong><?= htmlspecialchars($itemDesc) ?></strong>
                                            </div>
                                            <small class="chip-project">Proyecto: <?= htmlspecialchars($itemProject) ?></small>
                                            <?php if ($itemStatus === 'rejected' && $itemApprovalComment !== ''): ?>
                                                <div class="chip-rejection-comment" title="Comentario del aprobador">
                                                    <small class="rejection-label">Motivo rechazo:</small> <?= htmlspecialchars($itemApprovalComment) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="chip-meta">
                                                <?php if (!empty($item['had_blocker'])): ?><span title="Bloqueo">⛔</span><?php endif; ?>
                                                <?php if (!empty($item['generated_deliverable'])): ?><span title="Entregable">📦</span><?php endif; ?>
                                                <?php if (!empty($item['had_significant_progress'])): ?><span title="Avance">📈</span><?php endif; ?>
                                                <small><?= htmlspecialchars($itemComment !== '' ? $itemComment : 'Sin comentario') ?></small>
                                            </div>
                                            <?php if ($canEditDay): ?>
                                                <div class="chip-actions">
                                                    <button type="button" class="chip-action edit-activity" data-payload='<?= htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>'>✏ Editar</button>
                                                    <button type="button" class="chip-action duplicate-activity" data-activity-id="<?= $itemId ?>">⧉ Duplicar</button>
                                                    <button type="button" class="chip-action move-activity" data-activity-id="<?= $itemId ?>">↔ Mover</button>
                                                    <button type="button" class="chip-action danger delete-activity" data-activity-id="<?= $itemId ?>" title="Eliminar actividad">🗑 Eliminar</button>
                                                </div>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <aside class="quick-add-column">
                <section class="card quick-add-box" id="quick-add-box">
                    <h3>Quick Add</h3>
                    <p class="section-muted">Captura mínima para registrar en menos de 10 segundos.</p>
                    <form id="quick-add-form">
                        <fieldset class="quick-add-fieldset" <?= $weekFullyLocked ? 'disabled' : '' ?>>
                        <input type="hidden" name="activity_id" value="">
                        <input type="hidden" name="submit_mode" value="save">
                        <label>Fecha
                            <input type="date" name="date" value="<?= htmlspecialchars($weekStart->format('Y-m-d')) ?>" required>
                        </label>
                        <label>Cliente*
                            <select name="client_id" id="qa-client" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($clientsForTimesheet as $client): ?>
                                    <option value="<?= (int) ($client['client_id'] ?? 0) ?>"><?= htmlspecialchars((string) ($client['client'] ?? 'Sin cliente')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Proyecto*
                            <select name="project_id" id="qa-project" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($projectsForTimesheet as $project): ?>
                                    <option
                                        value="<?= (int) ($project['project_id'] ?? 0) ?>"
                                        data-client-id="<?= (int) ($project['client_id'] ?? 0) ?>"
                                    ><?= htmlspecialchars((string) ($project['project'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Tarea
                            <select name="task_id" id="qa-task">
                                <option value="0">Sin tarea seleccionada</option>
                                <?php foreach ($tasksForTimesheet as $task): ?>
                                    <option value="<?= (int) ($task['task_id'] ?? 0) ?>" data-project-id="<?= (int) ($task['project_id'] ?? 0) ?>"><?= htmlspecialchars((string) ($task['project'] ?? '')) ?> · <?= htmlspecialchars((string) ($task['task_title'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="task-management-block">
                            <span class="task-management-title">Gestión de tarea</span>
                            <div class="task-management-toggle" role="group" aria-label="Gestión de tarea">
                                <button type="button" class="task-mode-btn is-active" data-task-mode="existing">Usar tarea existente</button>
                                <button type="button" class="task-mode-btn" data-task-mode="new">Crear tarea nueva</button>
                            </div>
                            <input type="hidden" name="task_management_mode" value="existing" id="qa-task-management-mode">
                        </div>
                        <div class="task-management-block conditional hidden" id="qa-new-task-fields">
                            <span class="task-management-title">Nueva tarea</span>
                            <label>Título de la tarea *
                                <input type="text" name="new_task_title" maxlength="180">
                            </label>
                            <label>Prioridad
                                <select name="new_task_priority">
                                    <option value="medium">Media</option>
                                    <option value="low">Baja</option>
                                    <option value="high">Alta</option>
                                </select>
                            </label>
                            <label>Fecha compromiso
                                <input type="date" name="new_task_due_date">
                            </label>
                            <label>Estado inicial
                                <select name="new_task_status">
                                    <option value="todo">Pendiente</option>
                                    <option value="in_progress">En proceso</option>
                                    <option value="blocked">Bloqueada</option>
                                    <option value="review">En revisión</option>
                                    <option value="done">Completada</option>
                                </select>
                            </label>
                        </div>
                        <label>Horas*
                            <input type="number" name="hours" step="0.25" min="0.25" max="24" required>
                        </label>
                        <label>Descripción breve*
                            <input type="text" name="activity_description" maxlength="255" required>
                        </label>
                        <label>Tipo de actividad *
                            <select name="activity_type" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($activityTypes as $type): ?>
                                    <option value="<?= htmlspecialchars((string) $type) ?>"><?= htmlspecialchars((string) ucfirst(str_replace('_', ' ', (string) $type))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="task-management-title">Bloque de contexto operativo</div>
                        <label class="toggle-field">
                            <span class="toggle-caption">Bloqueo</span>
                            <span class="switch">
                                <input type="checkbox" name="had_blocker" id="qa-blocker" value="1">
                                <span class="slider"></span>
                            </span>
                            <span class="toggle-state" data-toggle-state="qa-blocker">OFF</span>
                        </label>
                        <label class="conditional hidden" id="qa-blocker-wrap">Descripción del bloqueo
                            <input type="text" name="blocker_description" maxlength="500">
                        </label>
                        <label class="toggle-field">
                            <span class="toggle-caption">Entregable</span>
                            <span class="switch">
                                <input type="checkbox" name="generated_deliverable" id="qa-deliverable" value="1">
                                <span class="slider"></span>
                            </span>
                            <span class="toggle-state" data-toggle-state="qa-deliverable">OFF</span>
                        </label>
                        <label class="conditional hidden" id="qa-deliverable-wrap">Nombre / descripción de entregable
                            <input type="text" name="deliverable_note" maxlength="255">
                        </label>
                        <label class="toggle-field">
                            <span class="toggle-caption">Avance significativo</span>
                            <span class="switch">
                                <input type="checkbox" name="had_significant_progress" id="qa-progress" value="1">
                                <span class="slider"></span>
                            </span>
                            <span class="toggle-state" data-toggle-state="qa-progress">OFF</span>
                        </label>
                        <label>Comentario operativo*
                            <input type="text" name="comment" maxlength="255" required>
                        </label>
                        <div class="quick-actions">
                            <button type="submit" class="btn primary" data-submit-mode="save">Guardar</button>
                            <button type="submit" class="btn" data-submit-mode="save_duplicate">Guardar y duplicar día</button>
                            <button type="submit" class="btn" data-submit-mode="save_another">Guardar y continuar</button>
                        </div>
                        <div class="template-actions">
                            <button type="button" class="btn ghost" id="save-template">Guardar como plantilla</button>
                        </div>
                        </fieldset>
                    </form>
                    <div class="quick-lists">
                        <?php if ($recentActivitySuggestions !== []): ?>
                            <div>
                                <strong>Recientes</strong>
                                <div class="chip-list">
                                    <?php foreach ($recentActivitySuggestions as $recent): ?>
                                        <button type="button" class="chip-btn recent-fill"
                                            data-project-id="<?= (int) ($recent['project_id'] ?? 0) ?>"
                                            data-task-id="<?= (int) ($recent['task_id'] ?? 0) ?>"
                                            data-activity-type="<?= htmlspecialchars((string) ($recent['activity_type'] ?? ''), ENT_QUOTES) ?>"
                                            data-activity-description="<?= htmlspecialchars((string) ($recent['activity_description'] ?? ''), ENT_QUOTES) ?>">
                                            <?= htmlspecialchars((string) ($recent['project'] ?? 'Proyecto')) ?> · <?= htmlspecialchars((string) ($recent['activity_description'] ?? 'Actividad')) ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div>
                            <strong>Plantillas</strong>
                            <div class="chip-list" id="template-list"></div>
                        </div>
                    </div>
                </section>
            </aside>
        </section>
    <?php endif; ?>
</section>

<style>
.timesheet-ux{display:flex;flex-direction:column;gap:14px}
.timesheet-tabs{display:flex;gap:8px;flex-wrap:wrap}
.tab{padding:8px 12px;border:1px solid var(--border);border-radius:999px;text-decoration:none;color:var(--text-primary)}
.tab.active{background:color-mix(in srgb,var(--primary) 18%,var(--surface));border-color:color-mix(in srgb,var(--primary) 45%,var(--border));font-weight:700}
.timesheet-sticky-header{position:sticky;top:70px;z-index:5;display:grid;grid-template-columns:1.2fr 1fr 1.5fr;gap:10px;align-items:end}
.header-week-form{display:flex;gap:8px;align-items:end}
.header-week-form label{display:flex;flex-direction:column;gap:4px;font-size:13px}
.btn-link,.btn,.btn-xs,.chip-action{border:1px solid var(--border);border-radius:10px;background:var(--surface);padding:8px 10px;cursor:pointer}
.btn[disabled],.btn-xs[disabled],.chip-action[disabled]{opacity:.6;cursor:not-allowed}
.btn.primary{background:var(--primary);border-color:var(--primary)}
.btn.success{background:color-mix(in srgb,var(--success) 24%,var(--surface));border-color:color-mix(in srgb,var(--success) 48%,var(--border))}
.btn.ghost{border-style:dashed}
.btn-xs{font-size:12px;padding:4px 8px}
.header-badges{display:flex;gap:8px;flex-wrap:wrap}
.pill{display:inline-flex;gap:6px;padding:6px 10px;border-radius:999px;border:1px solid var(--border);font-size:12px}
.pill.status.approved{background:#dcfce7;color:#166534}
.pill.status.rejected{background:#fee2e2;color:#b91c1c}
.pill.status.submitted{background:#dbeafe;color:#1d4ed8}
.pill.status.draft{background:#e5e7eb;color:#374151}
.header-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
.header-inline-form{display:flex;gap:6px;align-items:center}
.header-inline-form input{min-width:180px}
.week-status-row{display:flex;align-items:center;gap:10px}
.week-notice-banner{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 14%,var(--surface));font-weight:700}
.week-locked-banner{border-color:color-mix(in srgb,var(--warning) 45%,var(--border));background:color-mix(in srgb,var(--warning) 18%,var(--surface));font-weight:700}
.indicators-grid{display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:10px}
.indicator{display:flex;flex-direction:column;gap:4px}
.indicator span{color:var(--text-secondary);font-size:12px}
.timesheet-main-layout{display:grid;grid-template-columns:3fr 7fr;gap:14px}
.calendar-column{display:flex;flex-direction:column;gap:10px;order:2}
.calendar-heading h3{margin:0 0 4px}
.calendar-empty-banner{border:1px dashed var(--border);border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:2px;background:color-mix(in srgb,var(--surface) 90%,var(--background))}
.calendar-empty-banner small{color:var(--text-secondary)}
.week-calendar-grid{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-start}
.day-card{flex:1 1 calc((100% - 60px)/7);min-width:150px;border:1px solid var(--border);border-radius:12px;padding:10px;background:color-mix(in srgb,var(--surface) 94%,var(--background));align-self:flex-start}
.day-card-header{display:flex;justify-content:space-between;align-items:center;gap:6px}
.day-total{font-size:12px;color:var(--text-secondary);margin:4px 0 8px}
.day-status-pill{margin:0 0 8px 0}
.day-actions{display:flex;gap:6px;margin:0 0 8px 0}
.day-drop-hint{font-size:11px;color:var(--text-secondary);border:1px dashed var(--border);border-radius:8px;padding:4px 6px;margin-bottom:8px}
.day-card.holiday-day{background:#ffe5e5;border-color:#f2b8b8}
.day-card.non-working-day{background:#f8fafc;border-color:#cbd5e1}
.day-card.absence-day{background:#eff6ff;border-color:#bfdbfe}
.day-card.absence-day.absence-vacaciones{background:#dbeafe;border-color:#93c5fd}
.day-card.absence-day.absence-permiso_medico{background:#fef9c3;border-color:#fde68a}
.day-card.non-working{border-style:dashed;cursor:not-allowed}
.day-state{font-size:11px;font-weight:700;color:#b91c1c}
.day-state.absence{color:#1e3a8a}
.day-state.absence.partial{color:#854d0e}
.day-special-name{font-size:12px;color:#991b1b;font-weight:600;margin:2px 0 6px}
.day-empty-state{border:1px dashed var(--border);border-radius:10px;padding:10px;display:flex;flex-direction:column;gap:2px;color:var(--text-secondary)}
.day-empty-state .btn-xs{margin-top:6px;align-self:flex-start}
.activity-list{margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:8px}
.activity-chip{border:1px solid var(--border);border-radius:10px;padding:8px;background:var(--surface);display:flex;flex-direction:column;gap:6px;cursor:grab}
.activity-chip:active{cursor:grabbing}
.activity-chip.is-locked{opacity:.9}
.activity-chip.is-draggable{box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--primary) 28%,transparent)}
.activity-chip.dragging{opacity:.65;box-shadow:0 8px 18px rgba(15,23,42,.25)}
.activity-chip.type-dev{border-left:4px solid #2563eb;border-color:#bfdbfe;background:#eff6ff}
.activity-chip.type-meeting{border-left:4px solid #9333ea;border-color:#e9d5ff;background:#faf5ff}
.activity-chip.type-support{border-left:4px solid #ea580c;border-color:#fed7aa;background:#fff7ed}
.activity-chip.type-pm{border-left:4px solid #16a34a;border-color:#bbf7d0;background:#f0fdf4}
.activity-chip.type-research{border-left:4px solid #6b7280;border-color:#e5e7eb;background:#f9fafb}
.chip-main{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.chip-hours{font-size:12px;font-weight:700;color:var(--text-secondary);white-space:nowrap}
.chip-status{font-size:11px;padding:2px 6px;flex-shrink:0}
.chip-rejection-comment{margin-top:4px;padding:6px 8px;background:#fee2e2;border-radius:8px;border-left:3px solid #b91c1c;font-size:12px;color:#7f1d1d}
.chip-rejection-comment .rejection-label{font-weight:700;display:inline}
.status-breakdown .status-approved{border-left:4px solid #16a34a}
.status-breakdown .status-pending{border-left:4px solid #2563eb}
.status-breakdown .status-rejected{border-left:4px solid #dc2626}
.chip-drag-hint{font-size:11px;border:1px dashed var(--border);padding:2px 6px;border-radius:999px;color:var(--text-secondary);background:color-mix(in srgb,var(--surface) 75%,var(--background))}
.chip-project{color:var(--text-secondary)}
.chip-meta{display:flex;gap:6px;align-items:center;color:var(--text-secondary)}
.chip-actions{display:flex;gap:6px;flex-wrap:wrap}
.chip-action{font-size:12px;padding:5px 8px;border-radius:8px}
.chip-action.danger{border-color:#dc3545;color:#dc3545;background:#fff}
.chip-action.danger:hover{background:#ffe5e5}
.quick-add-column{display:flex;flex-direction:column;order:1}
.quick-add-box{position:sticky;top:150px;display:flex;flex-direction:column;gap:10px}
#quick-add-form{display:flex;flex-direction:column;gap:8px}
.quick-add-fieldset{border:0;padding:0;margin:0;display:flex;flex-direction:column;gap:8px}
#quick-add-form label{display:flex;flex-direction:column;gap:4px;font-size:13px}
.task-management-block{border:1px dashed var(--border);border-radius:10px;padding:8px;display:flex;flex-direction:column;gap:6px}
.task-management-title{font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase}
.task-management-toggle{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.task-mode-btn{border:1px solid var(--border);background:var(--surface);border-radius:8px;padding:8px;font-size:13px;cursor:pointer}
.task-mode-btn.is-active{border-color:var(--primary);background:color-mix(in srgb,var(--primary) 14%,var(--surface));font-weight:700}
.toggle-field{display:grid !important;grid-template-columns:1fr auto auto;align-items:center;gap:10px !important}
.toggle-caption{font-size:13px;color:var(--text-primary)}
.toggle-state{font-size:11px;font-weight:600;color:var(--text-secondary);min-width:28px;text-align:right}
.toggle-state.is-on{color:var(--primary)}
.switch{position:relative;display:inline-block;width:44px;height:24px}
.switch input{opacity:0;width:0;height:0;position:absolute}
.switch .slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#cbd5e1;border-radius:999px;transition:.2s}
.switch .slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s;box-shadow:0 1px 3px rgba(15,23,42,.25)}
.switch input:checked + .slider{background:#7c3aed}
.switch input:checked + .slider:before{transform:translateX(20px)}
.conditional.hidden{display:none !important}
.quick-actions{display:grid;grid-template-columns:1fr;gap:6px}
.template-actions{display:flex;justify-content:flex-start}
.quick-lists{display:flex;flex-direction:column;gap:10px}
.chip-list{display:flex;flex-wrap:wrap;gap:6px}
.chip-btn{border:1px solid var(--border);border-radius:999px;background:var(--surface);padding:5px 10px;cursor:pointer;font-size:12px}
.day-card.is-drop-target{outline:2px dashed color-mix(in srgb,var(--primary) 55%,var(--border));box-shadow:0 8px 20px rgba(37,99,235,.18);transform:translateY(-2px)}
@media (max-width: 1100px){.timesheet-sticky-header{grid-template-columns:1fr}.timesheet-main-layout{grid-template-columns:1fr}.day-card{flex:1 1 100%}.quick-add-box{position:static}.indicators-grid{grid-template-columns:repeat(2,minmax(120px,1fr))}}
</style>

<script>
(() => {
  const basePath = <?= json_encode($basePath) ?>;
  const weekValue = <?= json_encode($weekValue) ?>;
  const weekFullyLocked = <?= $weekFullyLocked ? 'true' : 'false' ?>;
  const dayLabels = <?= json_encode($daysJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const form = document.getElementById('quick-add-form');
  const clientInput = document.getElementById('qa-client');
  const projectInput = document.getElementById('qa-project');
  const taskInput = document.getElementById('qa-task');
  const taskManagementInput = document.getElementById('qa-task-management-mode');
  const taskModeButtons = form ? form.querySelectorAll('[data-task-mode]') : [];
  const newTaskWrap = document.getElementById('qa-new-task-fields');
  const newTaskTitleInput = form ? form.querySelector('[name="new_task_title"]') : null;
  const blockerToggle = document.getElementById('qa-blocker');
  const blockerWrap = document.getElementById('qa-blocker-wrap');
  const deliverableToggle = document.getElementById('qa-deliverable');
  const deliverableWrap = document.getElementById('qa-deliverable-wrap');
  const progressToggle = document.getElementById('qa-progress');
  const templatesKey = 'timesheet.quick.templates.v1';
  let lastSubmitMode = 'save';

  const nextDate = (dateStr) => {
    const date = new Date(`${dateStr}T00:00:00`);
    date.setDate(date.getDate() + 1);
    const yyyy = date.getFullYear();
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    const dd = String(date.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
  };

  const dayMeta = (dateStr) => {
    if (!dateStr) return { blocked: false, locked: false, status: 'draft', type: '', name: '' };
    const card = document.querySelector(`[data-drop-day="${dateStr}"]`);
    const rawStatus = String(card?.dataset.dayStatus || 'draft').toLowerCase();
    const status = ['pending', 'pending_approval'].includes(rawStatus) ? 'submitted' : rawStatus;
    return {
      blocked: card?.dataset.nonWorking === '1',
      locked: ['submitted', 'approved'].includes(status),
      status,
      type: card?.dataset.dayType || '',
      name: card?.dataset.dayName || '',
    };
  };

  const blockedDayMessage = (type, name = '') => {
    if ((type || '').startsWith('absence_')) {
      const normalized = String(type || '').replace('absence_', '');
      if (normalized === 'vacaciones' || String(name || '').toLowerCase().includes('vacaciones')) {
        return 'No puedes registrar horas. Estás en vacaciones este día.';
      }
      const fallbackLabel = normalized.replaceAll('_', ' ').trim();
      const label = String(name || '').trim() || fallbackLabel || 'una ausencia aprobada';
      return `No puedes registrar horas. Tienes ${label} este día.`;
    }
    if (type === 'holiday') {
      return name
        ? `Este día es festivo (${name}). No se pueden registrar horas.`
        : 'Este día es festivo. No se pueden registrar horas.';
    }
    return name
      ? `Este día es no laboral (${name}). No se pueden registrar horas.`
      : 'Este día es no laboral. No se pueden registrar horas.';
  };

  const workflowLockedDayMessage = (status) => {
    if (status === 'approved') {
      return 'Este día ya está aprobado y no se puede editar.';
    }
    return 'Registro enviado para aprobación: el día está bloqueado.';
  };

  const post = async (path, payloadObj) => {
    const payload = new URLSearchParams(payloadObj);
    const res = await fetch(`${basePath}${path}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: payload,
    });
    let data = {};
    try {
      data = await res.json();
    } catch (e) {}
    if (!res.ok || data.ok === false) {
      throw new Error(data.message || 'No se pudo completar la acción.');
    }
    return data;
  };

  const filterProjectsByClient = () => {
    if (!projectInput) return;
    const selectedClientRaw = String(clientInput?.value || '');
    projectInput.querySelectorAll('option[data-client-id]').forEach((option) => {
      const optionClientId = String(option.dataset.clientId || '0');
      const isVisible = !clientInput || (selectedClientRaw !== '' && optionClientId === selectedClientRaw);
      option.hidden = !isVisible;
    });
    if (projectInput.selectedOptions[0]?.hidden) {
      projectInput.value = '';
    }
  };

  const filterTasksByProject = () => {
    if (!taskInput) return;
    const projectId = Number(projectInput?.value || 0);
    taskInput.querySelectorAll('option[data-project-id]').forEach((option) => {
      const isVisible = projectId <= 0 || Number(option.dataset.projectId || 0) === projectId;
      option.hidden = !isVisible;
    });
    if (taskInput.selectedOptions[0]?.hidden) {
      taskInput.value = '0';
    }
  };

  const setProjectAndClient = (projectIdValue) => {
    const projectId = String(projectIdValue || '');
    const matchingOption = Array.from(projectInput?.options || []).find((option) => option.value === projectId);
    if (clientInput) {
      if (matchingOption) {
        clientInput.value = String(matchingOption.dataset.clientId || '');
      } else if (projectId === '') {
        clientInput.value = '';
      }
    }
    filterProjectsByClient();
    if (projectInput) {
      projectInput.value = projectId;
      if (projectInput.selectedOptions[0]?.hidden) {
        projectInput.value = '';
      }
    }
    filterTasksByProject();
  };

  const syncTaskManagementMode = () => {
    if (!taskInput) return;
    const selectedMode = taskManagementInput?.value || 'existing';
    const useExistingTask = selectedMode === 'existing';
    taskInput.disabled = !useExistingTask;
    if (!useExistingTask) {
      taskInput.value = '0';
    }
    if (newTaskWrap) {
      newTaskWrap.classList.toggle('hidden', useExistingTask);
    }
    if (newTaskTitleInput) {
      newTaskTitleInput.required = !useExistingTask;
    }
    taskModeButtons.forEach((button) => {
      button.classList.toggle('is-active', (button.dataset.taskMode || 'existing') === selectedMode);
    });
  };

  const toggleConditional = (toggle, wrap, requiredWhenOn = false) => {
    wrap?.classList.toggle('hidden', !toggle.checked);
    const input = wrap?.querySelector('input');
    if (input) {
      input.required = requiredWhenOn && Boolean(toggle.checked);
    }
    if (!toggle.checked) {
      if (input) input.value = '';
    }
  };

  const syncToggleLabels = () => {
    document.querySelectorAll('[data-toggle-state]').forEach((state) => {
      const inputId = state.getAttribute('data-toggle-state');
      const input = inputId ? document.getElementById(inputId) : null;
      const isOn = Boolean(input?.checked);
      state.textContent = isOn ? 'ON' : 'OFF';
      state.classList.toggle('is-on', isOn);
    });
  };

  const syncToggles = () => {
    toggleConditional(blockerToggle, blockerWrap, true);
    toggleConditional(deliverableToggle, deliverableWrap);
    syncToggleLabels();
  };

  clientInput?.addEventListener('change', () => {
    filterProjectsByClient();
    filterTasksByProject();
  });
  projectInput?.addEventListener('change', () => {
    const selectedProjectId = String(projectInput?.value || '');
    if (selectedProjectId === '') {
      filterTasksByProject();
      return;
    }
    setProjectAndClient(selectedProjectId);
  });
  blockerToggle?.addEventListener('change', syncToggles);
  deliverableToggle?.addEventListener('change', syncToggles);
  progressToggle?.addEventListener('change', syncToggles);
  taskModeButtons.forEach((button) => {
    button.addEventListener('click', () => {
      if (!taskManagementInput) return;
      taskManagementInput.value = button.dataset.taskMode || 'existing';
      syncTaskManagementMode();
    });
  });
  form?.querySelector('[name="date"]')?.addEventListener('change', (event) => {
    const value = String(event.target?.value || '');
    const meta = dayMeta(value);
    if (meta.blocked) {
      alert(blockedDayMessage(meta.type, meta.name));
      event.target.value = '';
      return;
    }
    if (meta.locked) {
      alert(workflowLockedDayMessage(meta.status));
      event.target.value = '';
    }
  });
  filterProjectsByClient();
  filterTasksByProject();
  syncToggles();
  syncTaskManagementMode();

  document.querySelectorAll('[data-submit-mode]').forEach((btn) => {
    btn.addEventListener('click', () => { lastSubmitMode = btn.dataset.submitMode || 'save'; });
  });

  const resetForAnother = () => {
    const keepDate = form.querySelector('[name="date"]')?.value || '';
    const keepClient = form.querySelector('[name="client_id"]')?.value || '';
    const keepProject = form.querySelector('[name="project_id"]')?.value || '';
    const keepTask = form.querySelector('[name="task_id"]')?.value || '0';
    const keepTaskMode = form.querySelector('[name="task_management_mode"]')?.value || 'existing';
    form.reset();
    form.querySelector('[name="activity_id"]').value = '';
    form.querySelector('[name="date"]').value = keepDate;
    if (clientInput) clientInput.value = keepClient;
    filterProjectsByClient();
    if (keepProject !== '') {
      setProjectAndClient(keepProject);
    } else {
      form.querySelector('[name="project_id"]').value = '';
      filterTasksByProject();
    }
    form.querySelector('[name="task_id"]').value = keepTask;
    form.querySelector('[name="task_management_mode"]').value = keepTaskMode;
    syncToggles();
    syncTaskManagementMode();
  };

  const fillForm = (data) => {
    form.querySelector('[name="activity_id"]').value = String(data.id || '');
    form.querySelector('[name="date"]').value = data.date || '';
    setProjectAndClient(String(data.project_id || ''));
    form.querySelector('[name="task_id"]').value = String(data.task_id || 0);
    form.querySelector('[name="task_management_mode"]').value = 'existing';
    syncTaskManagementMode();
    form.querySelector('[name="hours"]').value = String(data.hours || '');
    form.querySelector('[name="activity_description"]').value = data.activity_description || '';
    form.querySelector('[name="comment"]').value = data.comment || '';
    form.querySelector('[name="activity_type"]').value = data.activity_type || '';
    form.querySelector('[name="had_blocker"]').checked = Boolean(Number(data.had_blocker || 0));
    form.querySelector('[name="blocker_description"]').value = data.blocker_description || '';
    form.querySelector('[name="generated_deliverable"]').checked = Boolean(Number(data.generated_deliverable || 0));
    form.querySelector('[name="had_significant_progress"]').checked = Boolean(Number(data.had_significant_progress || 0));
    syncToggles();
    document.getElementById('quick-add-box')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (weekFullyLocked) {
      alert('Todos los días registrados están enviados o aprobados.');
      return;
    }
    const formData = new FormData(form);
    const raw = Object.fromEntries(formData.entries());
    const selectedMeta = dayMeta(String(raw.date || ''));
    if (selectedMeta.blocked) {
      alert(blockedDayMessage(selectedMeta.type, selectedMeta.name));
      return;
    }
    if (selectedMeta.locked) {
      alert(workflowLockedDayMessage(selectedMeta.status));
      return;
    }
    raw.had_blocker = blockerToggle?.checked ? '1' : '0';
    raw.generated_deliverable = deliverableToggle?.checked ? '1' : '0';
    raw.had_significant_progress = progressToggle?.checked ? '1' : '0';
    const activityId = Number(raw.activity_id || 0);
    const deliverableNote = String(raw.deliverable_note || '').trim();
    const operationalParts = [String(raw.comment || '').trim()].filter(Boolean);
    if (deliverableNote !== '') operationalParts.push(`Entregable: ${deliverableNote}`);
    raw.operational_comment = operationalParts.join(' | ');

    const endpoint = activityId > 0 ? '/timesheets/activities/update' : '/timesheets/activities/create';
    try {
      const response = await post(endpoint, raw);
      const finalActivityId = activityId > 0 ? activityId : Number(response.id || 0);
      if (lastSubmitMode === 'save_duplicate' && finalActivityId > 0) {
        const targetDate = nextDate(String(raw.date || ''));
        const duplicateMeta = dayMeta(targetDate);
        if (duplicateMeta.blocked) {
          alert(blockedDayMessage(duplicateMeta.type, duplicateMeta.name));
          return;
        }
        if (duplicateMeta.locked) {
          alert(workflowLockedDayMessage(duplicateMeta.status));
          return;
        }
        await post('/timesheets/activities/duplicate', {
          activity_id: String(finalActivityId),
          target_date: targetDate,
        });
        window.location.href = `${basePath}/timesheets?week=${encodeURIComponent(weekValue)}`;
        return;
      }
      if (lastSubmitMode === 'save_another') {
        resetForAnother();
        return;
      }
      window.location.href = `${basePath}/timesheets?week=${encodeURIComponent(weekValue)}`;
    } catch (error) {
      alert(error.message || 'No se pudo guardar.');
    }
  });

  document.querySelectorAll('.edit-activity').forEach((button) => {
    button.addEventListener('click', () => {
      if (weekFullyLocked) return;
      try {
        const payload = JSON.parse(button.dataset.payload || '{}');
        fillForm(payload);
      } catch (e) {
        alert('No se pudo cargar la actividad para edición.');
      }
    });
  });

  document.querySelectorAll('.duplicate-activity').forEach((button) => {
    button.addEventListener('click', async () => {
      if (weekFullyLocked) return;
      const activityId = Number(button.dataset.activityId || 0);
      const target = prompt('Fecha destino (YYYY-MM-DD):');
      if (!target) return;
      const duplicateMeta = dayMeta(target);
      if (duplicateMeta.blocked) {
        alert(blockedDayMessage(duplicateMeta.type, duplicateMeta.name));
        return;
      }
      if (duplicateMeta.locked) {
        alert(workflowLockedDayMessage(duplicateMeta.status));
        return;
      }
      try {
        await post('/timesheets/activities/duplicate', { activity_id: String(activityId), target_date: target });
        window.location.href = `${basePath}/timesheets?week=${encodeURIComponent(weekValue)}`;
      } catch (error) {
        alert(error.message || 'No se pudo duplicar.');
      }
    });
  });

  document.querySelectorAll('.move-activity').forEach((button) => {
    button.addEventListener('click', async () => {
      if (weekFullyLocked) return;
      const activityId = Number(button.dataset.activityId || 0);
      const target = prompt('Fecha destino (YYYY-MM-DD):');
      if (!target) return;
      const moveMeta = dayMeta(target);
      if (moveMeta.blocked) {
        alert(blockedDayMessage(moveMeta.type, moveMeta.name));
        return;
      }
      if (moveMeta.locked) {
        alert(workflowLockedDayMessage(moveMeta.status));
        return;
      }
      try {
        await post('/timesheets/activities/move', { activity_id: String(activityId), target_date: target });
        window.location.href = `${basePath}/timesheets?week=${encodeURIComponent(weekValue)}`;
      } catch (error) {
        alert(error.message || 'No se pudo mover.');
      }
    });
  });

  document.querySelectorAll('.delete-activity').forEach((button) => {
    button.addEventListener('click', async () => {
      if (weekFullyLocked) return;
      const activityId = Number(button.dataset.activityId || 0);
      if (!confirm('¿Eliminar actividad?')) return;
      try {
        await post('/timesheets/activities/delete', { activity_id: String(activityId) });
        window.location.href = `${basePath}/timesheets?week=${encodeURIComponent(weekValue)}`;
      } catch (error) {
        alert(error.message || 'No se pudo eliminar.');
      }
    });
  });

  const getDateByLabel = (message) => {
    const lines = Object.entries(dayLabels).map(([key, label]) => `${key} (${label})`);
    return prompt(`${message}\n${lines.join('\n')}`);
  };

  document.getElementById('duplicate-day-trigger')?.addEventListener('click', async () => {
    if (weekFullyLocked) return;
    const source = getDateByLabel('Selecciona día origen');
    if (!source) return;
    const sourceMeta = dayMeta(source);
    if (sourceMeta.blocked) {
      alert(blockedDayMessage(sourceMeta.type, sourceMeta.name));
      return;
    }
    if (sourceMeta.locked) {
      alert(workflowLockedDayMessage(sourceMeta.status));
      return;
    }
    const target = getDateByLabel('Selecciona día destino');
    if (!target) return;
    const duplicateDayMeta = dayMeta(target);
    if (duplicateDayMeta.blocked) {
      alert(blockedDayMessage(duplicateDayMeta.type, duplicateDayMeta.name));
      return;
    }
    if (duplicateDayMeta.locked) {
      alert(workflowLockedDayMessage(duplicateDayMeta.status));
      return;
    }
    try {
      await post('/timesheets/duplicate-day', { source_date: source, target_date: target });
      window.location.href = `${basePath}/timesheets?week=${encodeURIComponent(weekValue)}`;
    } catch (error) {
      alert(error.message || 'No se pudo duplicar el día.');
    }
  });

  document.getElementById('focus-quick-add')?.addEventListener('click', () => {
    if (weekFullyLocked) return;
    document.getElementById('quick-add-box')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  document.querySelectorAll('.register-activity-btn').forEach((button) => {
    button.addEventListener('click', () => {
      if (weekFullyLocked || !form) return;
      const targetDate = button.dataset.prefillDate || '';
      if (targetDate) {
        const targetMeta = dayMeta(targetDate);
        if (targetMeta.blocked) {
          alert(blockedDayMessage(targetMeta.type, targetMeta.name));
          return;
        }
        if (targetMeta.locked) {
          alert(workflowLockedDayMessage(targetMeta.status));
          return;
        }
        const dateInput = form.querySelector('[name="date"]');
        if (dateInput) {
          dateInput.value = targetDate;
        }
      }
      document.getElementById('quick-add-box')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      form.querySelector('[name="client_id"]')?.focus();
    });
  });

  document.querySelectorAll('.recent-fill').forEach((button) => {
    button.addEventListener('click', () => {
      form.querySelector('[name="activity_id"]').value = '';
      form.querySelector('[name="task_management_mode"]').value = 'existing';
      setProjectAndClient(button.dataset.projectId || '');
      form.querySelector('[name="task_id"]').value = button.dataset.taskId || '0';
      form.querySelector('[name="activity_type"]').value = button.dataset.activityType || '';
      form.querySelector('[name="activity_description"]').value = button.dataset.activityDescription || '';
      syncTaskManagementMode();
      form.querySelector('[name="activity_description"]').focus();
    });
  });

  const readTemplates = () => {
    try {
      const raw = localStorage.getItem(templatesKey);
      const parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  };

  const writeTemplates = (items) => localStorage.setItem(templatesKey, JSON.stringify(items.slice(0, 10)));
  const renderTemplates = () => {
    const list = document.getElementById('template-list');
    if (!list) return;
    const items = readTemplates();
    list.innerHTML = '';
    if (items.length === 0) {
      list.innerHTML = '<small class="section-muted">Sin plantillas guardadas.</small>';
      return;
    }
    items.forEach((tpl, index) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'chip-btn';
      button.textContent = `${tpl.project_label || 'Proyecto'} · ${tpl.activity_description || 'Actividad'}`;
      button.addEventListener('click', () => {
        form.querySelector('[name="activity_id"]').value = '';
        form.querySelector('[name="task_management_mode"]').value = 'existing';
        setProjectAndClient(String(tpl.project_id || ''));
        form.querySelector('[name="task_id"]').value = String(tpl.task_id || 0);
        form.querySelector('[name="activity_type"]').value = tpl.activity_type || '';
        form.querySelector('[name="activity_description"]').value = tpl.activity_description || '';
        syncTaskManagementMode();
      });
      list.appendChild(button);

      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'chip-btn';
      remove.textContent = '✕';
      remove.addEventListener('click', () => {
        const next = readTemplates().filter((_, i) => i !== index);
        writeTemplates(next);
        renderTemplates();
      });
      list.appendChild(remove);
    });
  };

  document.getElementById('save-template')?.addEventListener('click', () => {
    if (weekFullyLocked) return;
    const projectId = Number(form.querySelector('[name="project_id"]').value || 0);
    const projectLabel = projectInput?.selectedOptions?.[0]?.textContent || 'Proyecto';
    const template = {
      project_id: projectId,
      project_label: projectLabel,
      task_id: Number(form.querySelector('[name="task_id"]').value || 0),
      activity_type: form.querySelector('[name="activity_type"]').value || '',
      activity_description: form.querySelector('[name="activity_description"]').value || '',
    };
    if (!template.project_id || template.activity_description.trim() === '') {
      alert('Completa proyecto y descripción para guardar plantilla.');
      return;
    }
    const existing = readTemplates();
    writeTemplates([template, ...existing]);
    renderTemplates();
  });

  let draggingActivityId = null;
  document.querySelectorAll('.activity-chip').forEach((chip) => {
    chip.addEventListener('dragstart', () => {
      if (weekFullyLocked) return;
      draggingActivityId = Number(chip.dataset.activityId || 0);
      chip.classList.add('dragging');
    });
    chip.addEventListener('dragend', () => {
      chip.classList.remove('dragging');
      draggingActivityId = null;
    });
  });

  document.querySelectorAll('[data-drop-day]').forEach((dayCard) => {
    dayCard.addEventListener('dragover', (event) => {
      if (weekFullyLocked) return;
      if (dayCard.dataset.nonWorking === '1') return;
      if (['submitted', 'pending', 'pending_approval', 'approved'].includes(String(dayCard.dataset.dayStatus || '').toLowerCase())) return;
      event.preventDefault();
      dayCard.classList.add('is-drop-target');
    });
    dayCard.addEventListener('dragleave', () => dayCard.classList.remove('is-drop-target'));
    dayCard.addEventListener('drop', async (event) => {
      event.preventDefault();
      dayCard.classList.remove('is-drop-target');
      if (weekFullyLocked) return;
      if (dayCard.dataset.nonWorking === '1') {
        alert(blockedDayMessage(dayCard.dataset.dayType || '', dayCard.dataset.dayName || ''));
        return;
      }
      if (['submitted', 'pending', 'pending_approval', 'approved'].includes(String(dayCard.dataset.dayStatus || '').toLowerCase())) {
        alert(workflowLockedDayMessage(String(dayCard.dataset.dayStatus || 'submitted').toLowerCase()));
        return;
      }
      if (!draggingActivityId) return;
      const targetDate = dayCard.dataset.dropDay || '';
      try {
        await post('/timesheets/activities/move', {
          activity_id: String(draggingActivityId),
          target_date: targetDate,
        });
        window.location.href = `${basePath}/timesheets?week=${encodeURIComponent(weekValue)}`;
      } catch (error) {
        alert(error.message || 'No se pudo mover la actividad.');
      }
    });
  });

  renderTemplates();
})();
</script>
