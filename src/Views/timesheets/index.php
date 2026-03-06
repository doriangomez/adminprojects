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
$tasksForTimesheet = is_array($tasksForTimesheet ?? null) ? $tasksForTimesheet : [];
$recentActivitySuggestions = is_array($recentActivitySuggestions ?? null) ? $recentActivitySuggestions : [];
$activityTypes = is_array($activityTypes ?? null) ? $activityTypes : [];
$canApprove = !empty($canApprove);
$selectedWeekSummary = is_array($selectedWeekSummary ?? null) ? $selectedWeekSummary : [];
$weekIndicators = is_array($weekIndicators ?? null) ? $weekIndicators : [];
$weekStatus = (string) ($selectedWeekSummary['status'] ?? 'draft');
$canRegisterWeekend = !empty($canRegisterWeekend);
$currentUserName = trim((string) ($currentUserName ?? 'Usuario')) ?: 'Usuario';
$statusMeta = [
    'draft' => ['label' => 'Borrador', 'class' => 'draft'],
    'submitted' => ['label' => 'Enviada', 'class' => 'submitted'],
    'partial' => ['label' => 'Parcial', 'class' => 'submitted'],
    'approved' => ['label' => 'Aprobada', 'class' => 'approved'],
    'rejected' => ['label' => 'Rechazada', 'class' => 'rejected'],
];
$status = $statusMeta[$weekStatus] ?? $statusMeta['draft'];
$weekLocked = in_array($weekStatus, ['submitted', 'approved'], true);
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
$daysJson = [];
foreach ($gridDays as $day) {
    $daysJson[(string) ($day['key'] ?? '')] = (string) (($day['label'] ?? '') . ' ' . ($day['number'] ?? '') . ' ' . ($day['month'] ?? ''));
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
                <span class="pill neutral">Meta: <strong><?= round((float) ($weekIndicators['weekly_capacity'] ?? 40), 2) ?>h</strong></span>
            </div>
            <div class="header-actions">
                <button type="button" class="btn primary" id="focus-quick-add" <?= $weekLocked ? 'disabled' : '' ?>>+ Registrar actividad</button>
                <button type="button" class="btn" id="duplicate-day-trigger" <?= $weekLocked ? 'disabled' : '' ?>>Duplicar día</button>
                <form method="POST" action="<?= $basePath ?>/timesheets/submit-week">
                    <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                    <button type="submit" class="btn success" <?= $weekLocked ? 'disabled' : '' ?>>Enviar semana</button>
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
        <?php if ($weekLocked): ?>
            <section class="card week-locked-banner">Semana enviada – registros bloqueados.</section>
        <?php endif; ?>

        <section class="indicators-grid">
            <article class="card indicator"><span>Horas registradas / <?= round((float) ($weekIndicators['weekly_capacity'] ?? 40), 2) ?>h</span><strong><?= round((float) ($weekIndicators['week_total'] ?? 0), 2) ?>h</strong></article>
            <article class="card indicator"><span>Capacidad restante</span><strong><?= round((float) ($weekIndicators['remaining_capacity'] ?? 0), 2) ?>h</strong></article>
            <article class="card indicator"><span>Progreso semanal</span><strong><?= round((float) ($weekIndicators['compliance_percent'] ?? 0), 2) ?>%</strong></article>
            <article class="card indicator"><span>Proyecto con mayor carga</span><strong><?= htmlspecialchars((string) ($weekIndicators['top_project'] ?? 'Sin datos')) ?></strong><small><?= round((float) ($weekIndicators['top_project_hours'] ?? 0), 2) ?>h</small></article>
        </section>

        <section class="timesheet-main-layout">
            <div class="calendar-column card">
                <div class="calendar-heading">
                    <h3>Actividades registradas de la semana</h3>
                    <p class="section-muted">Arrastra una actividad a otro día para moverla. Cada chip es editable.</p>
                </div>
                <?php if (round((float) ($weekIndicators['week_total'] ?? 0), 2) <= 0): ?>
                    <div class="calendar-empty-banner">
                        <strong>Aún no hay actividades esta semana</strong>
                        <button type="button" class="btn primary" id="banner-quick-add" <?= $weekLocked ? 'disabled' : '' ?>>+ Registrar primera actividad</button>
                    </div>
                <?php endif; ?>
                <div class="week-calendar-grid">
                    <?php foreach ($gridDays as $day): ?>
                        <?php
                        $dayDate = (string) ($day['key'] ?? '');
                        $dayLabel = ($day['label'] ?? '') . ' ' . ($day['number'] ?? '') . ' ' . ($day['month'] ?? '');
                        $items = is_array($activitiesByDay[$dayDate] ?? null) ? $activitiesByDay[$dayDate] : [];
                        $dayWeekNumber = (int) ((new DateTimeImmutable($dayDate))->format('N'));
                        $isWeekend = $dayWeekNumber >= 6;
                        $isBlockedWeekend = $isWeekend && !$canRegisterWeekend;
                        $dayHoursTotal = round((float) ($dayTotals[$dayDate] ?? 0), 2);
                        ?>
                        <article class="day-card<?= $isBlockedWeekend ? ' non-working' : '' ?>" data-drop-day="<?= htmlspecialchars($dayDate) ?>" data-non-working="<?= $isBlockedWeekend ? '1' : '0' ?>">
                            <header>
                                <strong><?= htmlspecialchars($dayLabel) ?></strong>
                                <?php if ($isBlockedWeekend): ?>
                                    <span class="day-state">No laboral</span>
                                <?php endif; ?>
                            </header>
                            <?php if (!$isBlockedWeekend): ?>
                                <div class="day-hours-total"><?= $dayHoursTotal ?>h registradas</div>
                            <?php endif; ?>
                            <?php if ($isBlockedWeekend): ?>
                                <div class="day-weekend-banner">
                                    <span class="weekend-icon">🚫</span>
                                    <span>Día no laboral</span>
                                </div>
                            <?php elseif ($items === []): ?>
                                <button type="button" class="day-add-activity-btn" data-day-date="<?= htmlspecialchars($dayDate) ?>">
                                    <span class="add-icon">+</span>
                                    <span>Registrar actividad</span>
                                </button>
                            <?php else: ?>
                                <ul class="activity-list">
                                    <?php foreach ($items as $item): ?>
                                        <?php
                                        $itemId = (int) ($item['id'] ?? 0);
                                        $itemProject = (string) ($item['project'] ?? 'Proyecto');
                                        $itemHours = (float) ($item['hours'] ?? 0);
                                        $itemDesc = trim((string) ($item['activity_description'] ?? '')) ?: trim((string) ($item['activity_type'] ?? 'Actividad'));
                                        $itemComment = (string) ($item['comment'] ?? '');
                                        $itemType = strtolower(trim((string) ($item['activity_type'] ?? '')));
                                        $typeMeta = $activityTypeMeta[$itemType] ?? ['label' => 'Investigación', 'class' => 'type-research'];
                                        $taskTooltip = $itemDesc !== '' ? $itemDesc : 'Sin tarea';
                                        $chipTooltip = 'Proyecto: ' . $itemProject
                                            . "\nTarea: " . $taskTooltip
                                            . "\nHoras: " . round($itemHours, 2)
                                            . "\nTipo: " . $typeMeta['label']
                                            . "\nUsuario: " . $currentUserName;
                                        ?>
                                        <li class="activity-chip <?= htmlspecialchars($typeMeta['class']) ?><?= $weekLocked ? ' is-locked' : '' ?>" <?= $weekLocked ? '' : 'draggable="true"' ?> data-activity-id="<?= $itemId ?>" title="<?= htmlspecialchars($chipTooltip) ?>">
                                            <?php if (!$weekLocked): ?>
                                                <span class="chip-drag-handle" aria-label="Arrastrar">⠿</span>
                                            <?php endif; ?>
                                            <div class="chip-body">
                                                <div class="chip-main">
                                                    <span class="chip-type-badge"><?= htmlspecialchars($typeMeta['label']) ?></span>
                                                    <span class="chip-hours"><?= round($itemHours, 2) ?>h</span>
                                                </div>
                                                <strong class="chip-desc"><?= htmlspecialchars($itemDesc) ?></strong>
                                                <small class="chip-project"><?= htmlspecialchars($itemProject) ?></small>
                                                <div class="chip-meta">
                                                    <?php if (!empty($item['had_blocker'])): ?><span title="Bloqueo">⛔</span><?php endif; ?>
                                                    <?php if (!empty($item['generated_deliverable'])): ?><span title="Entregable">📦</span><?php endif; ?>
                                                    <?php if (!empty($item['had_significant_progress'])): ?><span title="Avance">📈</span><?php endif; ?>
                                                    <?php if ($itemComment !== ''): ?><small><?= htmlspecialchars($itemComment) ?></small><?php endif; ?>
                                                </div>
                                                <?php if (!$weekLocked): ?>
                                                    <div class="chip-actions">
                                                        <button type="button" class="chip-action edit-activity" data-payload='<?= htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>'>✏ Editar</button>
                                                        <button type="button" class="chip-action duplicate-activity" data-activity-id="<?= $itemId ?>">⧉ Duplicar</button>
                                                        <button type="button" class="chip-action move-activity" data-activity-id="<?= $itemId ?>">↔ Mover</button>
                                                        <button type="button" class="chip-action danger delete-activity" data-activity-id="<?= $itemId ?>" title="Eliminar actividad">🗑</button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
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
                        <fieldset class="quick-add-fieldset" <?= $weekLocked ? 'disabled' : '' ?>>
                        <input type="hidden" name="activity_id" value="">
                        <input type="hidden" name="submit_mode" value="save">
                        <label>Fecha
                            <input type="date" name="date" value="<?= htmlspecialchars($weekStart->format('Y-m-d')) ?>" required>
                        </label>
                        <label>Proyecto*
                            <select name="project_id" id="qa-project" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($projectsForTimesheet as $project): ?>
                                    <option value="<?= (int) ($project['project_id'] ?? 0) ?>"><?= htmlspecialchars((string) ($project['project'] ?? '')) ?></option>
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
                                    <option value="pending">Pendiente</option>
                                    <option value="completed">Completada</option>
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
.week-locked-banner{border-color:color-mix(in srgb,var(--warning) 45%,var(--border));background:color-mix(in srgb,var(--warning) 18%,var(--surface));font-weight:700}
.indicators-grid{display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:10px}
.indicator{display:flex;flex-direction:column;gap:4px}
.indicator span{color:var(--text-secondary);font-size:12px}
.timesheet-main-layout{display:grid;grid-template-columns:3fr 7fr;gap:14px}
.calendar-column{display:flex;flex-direction:column;gap:10px;order:2}
.calendar-heading h3{margin:0 0 4px}
.calendar-empty-banner{border:1px dashed var(--border);border-radius:12px;padding:18px;display:flex;flex-direction:column;align-items:center;gap:12px;background:color-mix(in srgb,var(--surface) 90%,var(--background));text-align:center}
.calendar-empty-banner strong{color:var(--text-secondary);font-size:14px}
.week-calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(150px,1fr));gap:10px}

/* Day cards – dynamic height */
.day-card{border:1px solid var(--border);border-radius:12px;padding:10px;background:color-mix(in srgb,var(--surface) 94%,var(--background));display:flex;flex-direction:column;gap:6px}
.day-card header{display:flex;justify-content:space-between;align-items:center}
.day-card header strong{font-size:13px}

/* Hours total below header */
.day-hours-total{font-size:11px;font-weight:600;color:var(--text-secondary);background:color-mix(in srgb,var(--primary) 8%,var(--surface));border-radius:6px;padding:3px 8px;text-align:center}

/* Weekend non-working styling */
.day-card.non-working{background:#fff0f0;border:2px dashed #e8aaaa;cursor:not-allowed}
.day-state{font-size:11px;font-weight:700;color:#b91c1c;background:#fecaca;padding:2px 8px;border-radius:999px}
.day-weekend-banner{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;padding:16px 8px;color:#991b1b;font-size:12px;font-weight:600;flex:1}
.weekend-icon{font-size:22px}

/* Empty day – register button */
.day-add-activity-btn{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;border:2px dashed color-mix(in srgb,var(--primary) 35%,var(--border));border-radius:10px;padding:16px 8px;background:color-mix(in srgb,var(--primary) 4%,var(--surface));color:color-mix(in srgb,var(--primary) 80%,var(--text-primary));cursor:pointer;transition:all .15s ease;font-size:12px;font-weight:600;flex:1}
.day-add-activity-btn:hover{background:color-mix(in srgb,var(--primary) 12%,var(--surface));border-color:var(--primary)}
.day-add-activity-btn .add-icon{width:28px;height:28px;border-radius:50%;background:color-mix(in srgb,var(--primary) 15%,var(--surface));display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;line-height:1}

/* Activity list */
.activity-list{margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:8px}

/* Activity chips – colored blocks */
.activity-chip{border-radius:10px;padding:0;background:var(--surface);display:flex;align-items:stretch;gap:0;cursor:grab;overflow:hidden;border:1px solid var(--border);transition:box-shadow .15s ease,transform .1s ease}
.activity-chip:hover{box-shadow:0 2px 8px rgba(15,23,42,.1)}
.activity-chip:active{cursor:grabbing;transform:scale(0.98)}
.activity-chip.is-locked{opacity:.9;cursor:default}
.activity-chip.dragging{opacity:.5;box-shadow:0 12px 24px rgba(15,23,42,.25);transform:rotate(2deg)}

/* Drag handle */
.chip-drag-handle{display:flex;align-items:center;justify-content:center;width:22px;min-height:100%;background:color-mix(in srgb,var(--text-secondary) 8%,var(--surface));color:var(--text-secondary);font-size:14px;cursor:grab;flex-shrink:0;user-select:none;letter-spacing:-1px;transition:background .15s}
.chip-drag-handle:hover{background:color-mix(in srgb,var(--text-secondary) 16%,var(--surface))}
.activity-chip:active .chip-drag-handle{cursor:grabbing;background:color-mix(in srgb,var(--primary) 18%,var(--surface))}

.chip-body{display:flex;flex-direction:column;gap:4px;padding:8px;flex:1;min-width:0}
.chip-main{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.chip-type-badge{font-size:10px;font-weight:700;text-transform:uppercase;padding:2px 6px;border-radius:4px;white-space:nowrap}
.chip-hours{font-size:12px;font-weight:700;color:var(--text-secondary);white-space:nowrap;margin-left:auto}
.chip-desc{font-size:12px;line-height:1.3;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.chip-project{color:var(--text-secondary);font-size:11px}
.chip-meta{display:flex;gap:4px;align-items:center;color:var(--text-secondary);flex-wrap:wrap}
.chip-meta small{font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:120px}
.chip-actions{display:flex;gap:4px;flex-wrap:wrap;padding-top:4px;border-top:1px solid color-mix(in srgb,var(--border) 50%,transparent)}
.chip-action{font-size:11px;padding:3px 6px;border-radius:6px}
.chip-action.danger{border-color:#dc3545;color:#dc3545;background:#fff}
.chip-action.danger:hover{background:#ffe5e5}

/* Type-based chip colors */
.activity-chip.type-dev{background:#eff6ff;border-color:#bfdbfe}
.activity-chip.type-dev .chip-type-badge{background:#2563eb;color:#fff}
.activity-chip.type-dev .chip-drag-handle{background:#dbeafe}

.activity-chip.type-meeting{background:#faf5ff;border-color:#e9d5ff}
.activity-chip.type-meeting .chip-type-badge{background:#9333ea;color:#fff}
.activity-chip.type-meeting .chip-drag-handle{background:#f3e8ff}

.activity-chip.type-support{background:#fff7ed;border-color:#fed7aa}
.activity-chip.type-support .chip-type-badge{background:#ea580c;color:#fff}
.activity-chip.type-support .chip-drag-handle{background:#ffedd5}

.activity-chip.type-pm{background:#f0fdf4;border-color:#bbf7d0}
.activity-chip.type-pm .chip-type-badge{background:#16a34a;color:#fff}
.activity-chip.type-pm .chip-drag-handle{background:#dcfce7}

.activity-chip.type-research{background:#f9fafb;border-color:#d1d5db}
.activity-chip.type-research .chip-type-badge{background:#6b7280;color:#fff}
.activity-chip.type-research .chip-drag-handle{background:#e5e7eb}

/* Drop target */
.day-card.is-drop-target{outline:2px dashed color-mix(in srgb,var(--primary) 55%,var(--border));box-shadow:0 8px 20px rgba(37,99,235,.18);background:color-mix(in srgb,var(--primary) 6%,var(--surface))}

/* Quick add sidebar */
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
@media (max-width: 1100px){.timesheet-sticky-header{grid-template-columns:1fr}.timesheet-main-layout{grid-template-columns:1fr}.week-calendar-grid{grid-template-columns:1fr}.quick-add-box{position:static}.indicators-grid{grid-template-columns:repeat(2,minmax(120px,1fr))}}
</style>

<script>
(() => {
  const basePath = <?= json_encode($basePath) ?>;
  const weekValue = <?= json_encode($weekValue) ?>;
  const weekLocked = <?= $weekLocked ? 'true' : 'false' ?>;
  const canRegisterWeekend = <?= $canRegisterWeekend ? 'true' : 'false' ?>;
  const dayLabels = <?= json_encode($daysJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const form = document.getElementById('quick-add-form');
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

  const isWeekendDate = (dateStr) => {
    if (!dateStr) return false;
    const date = new Date(`${dateStr}T00:00:00`);
    const day = date.getDay();
    return day === 0 || day === 6;
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

  projectInput?.addEventListener('change', filterTasksByProject);
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
    if (!canRegisterWeekend && isWeekendDate(value)) {
      alert('Registro no permitido en fines de semana.');
      event.target.value = '';
    }
  });
  filterTasksByProject();
  syncToggles();
  syncTaskManagementMode();

  document.querySelectorAll('[data-submit-mode]').forEach((btn) => {
    btn.addEventListener('click', () => { lastSubmitMode = btn.dataset.submitMode || 'save'; });
  });

  const resetForAnother = () => {
    const keepDate = form.querySelector('[name="date"]')?.value || '';
    const keepProject = form.querySelector('[name="project_id"]')?.value || '';
    const keepTask = form.querySelector('[name="task_id"]')?.value || '0';
    const keepTaskMode = form.querySelector('[name="task_management_mode"]')?.value || 'existing';
    form.reset();
    form.querySelector('[name="activity_id"]').value = '';
    form.querySelector('[name="date"]').value = keepDate;
    form.querySelector('[name="project_id"]').value = keepProject;
    filterTasksByProject();
    form.querySelector('[name="task_id"]').value = keepTask;
    form.querySelector('[name="task_management_mode"]').value = keepTaskMode;
    syncToggles();
    syncTaskManagementMode();
  };

  const fillForm = (data) => {
    form.querySelector('[name="activity_id"]').value = String(data.id || '');
    form.querySelector('[name="date"]').value = data.date || '';
    form.querySelector('[name="project_id"]').value = String(data.project_id || '');
    filterTasksByProject();
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
    if (weekLocked) {
      alert('Semana enviada – registros bloqueados.');
      return;
    }
    const formData = new FormData(form);
    const raw = Object.fromEntries(formData.entries());
    if (!canRegisterWeekend && isWeekendDate(String(raw.date || ''))) {
      alert('Registro no permitido en fines de semana.');
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
        if (!canRegisterWeekend && isWeekendDate(targetDate)) {
          alert('Registro no permitido en fines de semana.');
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
      if (weekLocked) return;
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
      if (weekLocked) return;
      const activityId = Number(button.dataset.activityId || 0);
      const target = prompt('Fecha destino (YYYY-MM-DD):');
      if (!target) return;
      if (!canRegisterWeekend && isWeekendDate(target)) {
        alert('Registro no permitido en fines de semana.');
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
      if (weekLocked) return;
      const activityId = Number(button.dataset.activityId || 0);
      const target = prompt('Fecha destino (YYYY-MM-DD):');
      if (!target) return;
      if (!canRegisterWeekend && isWeekendDate(target)) {
        alert('Registro no permitido en fines de semana.');
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
      if (weekLocked) return;
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
    if (weekLocked) return;
    const source = getDateByLabel('Selecciona día origen');
    if (!source) return;
    const target = getDateByLabel('Selecciona día destino');
    if (!target) return;
    if (!canRegisterWeekend && isWeekendDate(target)) {
      alert('Registro no permitido en fines de semana.');
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
    if (weekLocked) return;
    document.getElementById('quick-add-box')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  document.getElementById('banner-quick-add')?.addEventListener('click', () => {
    if (weekLocked) return;
    document.getElementById('quick-add-box')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  document.querySelectorAll('.day-add-activity-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (weekLocked) return;
      const dayDate = btn.dataset.dayDate || '';
      const dateInput = form?.querySelector('[name="date"]');
      if (dateInput) dateInput.value = dayDate;
      form?.querySelector('[name="activity_id"]')?.setAttribute('value', '');
      document.getElementById('quick-add-box')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      form?.querySelector('[name="project_id"]')?.focus();
    });
  });

  document.querySelectorAll('.recent-fill').forEach((button) => {
    button.addEventListener('click', () => {
      form.querySelector('[name="activity_id"]').value = '';
      form.querySelector('[name="task_management_mode"]').value = 'existing';
      form.querySelector('[name="project_id"]').value = button.dataset.projectId || '';
      filterTasksByProject();
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
        form.querySelector('[name="project_id"]').value = String(tpl.project_id || '');
        filterTasksByProject();
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
    if (weekLocked) return;
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
      if (weekLocked) return;
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
      if (weekLocked) return;
      if (dayCard.dataset.nonWorking === '1') return;
      event.preventDefault();
      dayCard.classList.add('is-drop-target');
    });
    dayCard.addEventListener('dragleave', () => dayCard.classList.remove('is-drop-target'));
    dayCard.addEventListener('drop', async (event) => {
      event.preventDefault();
      dayCard.classList.remove('is-drop-target');
      if (weekLocked) return;
      if (dayCard.dataset.nonWorking === '1') {
        alert('Registro no permitido en fines de semana.');
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
