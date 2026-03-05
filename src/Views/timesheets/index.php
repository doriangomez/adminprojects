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
$statusMeta = [
    'draft' => ['label' => 'Borrador', 'class' => 'draft'],
    'submitted' => ['label' => 'Enviada', 'class' => 'submitted'],
    'partial' => ['label' => 'Parcial', 'class' => 'submitted'],
    'approved' => ['label' => 'Aprobada', 'class' => 'approved'],
    'rejected' => ['label' => 'Rechazada', 'class' => 'rejected'],
];
$status = $statusMeta[$weekStatus] ?? $statusMeta['draft'];
$weekLocked = $weekStatus === 'approved';
$weekEditLocked = in_array($weekStatus, ['submitted', 'approved', 'partial'], true);
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
                <span class="pill neutral">Total semana: <strong><?= round((float) ($weekIndicators['week_total'] ?? 0), 2) ?>h</strong></span>
                <span class="pill status <?= htmlspecialchars($status['class']) ?>">Estado: <?= htmlspecialchars($status['label']) ?></span>
            </div>
            <div class="header-actions">
                <?php if (!$weekEditLocked): ?>
                    <button type="button" class="btn primary" id="focus-quick-add">+ Registrar actividad</button>
                    <button type="button" class="btn" id="duplicate-day-trigger">Duplicar día</button>
                <?php endif; ?>
                <form method="POST" action="<?= $basePath ?>/timesheets/submit-week">
                    <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                    <button type="submit" class="btn success" <?= $weekEditLocked ? 'disabled' : '' ?>>Enviar semana</button>
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

        <section class="indicators-grid">
            <article class="card indicator"><span>Horas registradas</span><strong><?= round((float) ($weekIndicators['week_total'] ?? 0), 2) ?>h</strong></article>
            <article class="card indicator"><span>Capacidad semanal</span><strong><?= round((float) ($weekIndicators['weekly_capacity'] ?? 0), 2) ?>h</strong></article>
            <article class="card indicator"><span>% cumplimiento</span><strong><?= round((float) ($weekIndicators['compliance_percent'] ?? 0), 2) ?>%</strong></article>
            <article class="card indicator"><span>Proyecto mayor consumo</span><strong><?= htmlspecialchars((string) ($weekIndicators['top_project'] ?? 'Sin datos')) ?></strong><small><?= round((float) ($weekIndicators['top_project_hours'] ?? 0), 2) ?>h</small></article>
        </section>

        <?php if ($weekEditLocked): ?>
        <div class="week-locked-banner">
            <span class="week-locked-icon">🔒</span>
            <div class="week-locked-text">
                <?php if ($weekStatus === 'approved'): ?>
                    <strong>Semana aprobada.</strong> Los registros están bloqueados y no pueden modificarse.
                <?php else: ?>
                    <strong>Semana enviada. Registros bloqueados.</strong> Retira el envío si necesitas hacer cambios.
                <?php endif; ?>
            </div>
        </div>
        <?php elseif ($weekStatus === 'rejected'): ?>
        <div class="week-rejected-banner">
            <span>⚠️</span>
            <div><strong>Semana rechazada.</strong> Puedes editar y corregir tus registros antes de reenviar.</div>
        </div>
        <?php endif; ?>

        <section class="timesheet-main-layout">
            <div class="calendar-column card">
                <div class="calendar-heading">
                    <h3>Actividades registradas de la semana</h3>
                    <p class="section-muted">Arrastra una actividad a otro día para moverla. Cada chip es editable.</p>
                </div>
                <div class="week-calendar-grid">
                    <?php foreach ($gridDays as $day): ?>
                        <?php
                        $dayDate = (string) ($day['key'] ?? '');
                        $dayLabel = (string) (($day['label'] ?? '') . ' ' . ($day['number'] ?? ''));
                        $items = is_array($activitiesByDay[$dayDate] ?? null) ? $activitiesByDay[$dayDate] : [];
                        ?>
                        <article class="day-card" data-drop-day="<?= htmlspecialchars($dayDate) ?>">
                            <header>
                                <strong><?= htmlspecialchars($dayLabel) ?></strong>
                                <span><?= round((float) ($dayTotals[$dayDate] ?? 0), 2) ?>h</span>
                            </header>
                            <?php if ($items === []): ?>
                                <p class="section-muted">Sin actividades.</p>
                            <?php else: ?>
                                <ul class="activity-list">
                                    <?php foreach ($items as $item): ?>
                                        <?php
                                        $itemId = (int) ($item['id'] ?? 0);
                                        $itemProject = (string) ($item['project'] ?? 'Proyecto');
                                        $itemHours = (float) ($item['hours'] ?? 0);
                                        $itemDesc = trim((string) ($item['activity_description'] ?? '')) ?: trim((string) ($item['activity_type'] ?? 'Actividad'));
                                        $itemComment = (string) ($item['comment'] ?? '');
                                        ?>
                                        <?php
                                        $itemStatus = (string) ($item['status'] ?? 'draft');
                                        $itemEditable = in_array($itemStatus, ['draft', 'rejected'], true);
                                        ?>
                                        <li class="activity-chip <?= $itemEditable ? '' : 'chip-locked' ?>" draggable="<?= $itemEditable ? 'true' : 'false' ?>" data-activity-id="<?= $itemId ?>">
                                            <div class="chip-main">
                                                <div class="chip-title">
                                                    <strong><?= htmlspecialchars($itemProject) ?></strong>
                                                    <span class="chip-desc"><?= htmlspecialchars($itemDesc) ?></span>
                                                </div>
                                                <span class="chip-hours"><?= round($itemHours, 2) ?>h</span>
                                            </div>
                                            <div class="chip-meta">
                                                <?php if (!empty($item['had_blocker'])): ?><span title="Bloqueo">⛔</span><?php endif; ?>
                                                <?php if (!empty($item['generated_deliverable'])): ?><span title="Entregable">📦</span><?php endif; ?>
                                                <?php if (!empty($item['had_significant_progress'])): ?><span title="Avance significativo">📈</span><?php endif; ?>
                                                <small class="chip-comment"><?= htmlspecialchars($itemComment !== '' ? $itemComment : 'Sin comentario') ?></small>
                                            </div>
                                            <div class="chip-actions">
                                                <?php if ($itemEditable): ?>
                                                    <button type="button" class="btn-xs edit-activity" data-payload='<?= htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>'>✏️ Editar</button>
                                                    <button type="button" class="btn-xs move-activity" data-activity-id="<?= $itemId ?>">↔ Mover</button>
                                                    <button type="button" class="btn-xs btn-delete delete-activity" data-activity-id="<?= $itemId ?>" title="Eliminar actividad">🗑 Eliminar</button>
                                                <?php else: ?>
                                                    <span class="chip-lock-badge">🔒 Bloqueado</span>
                                                <?php endif; ?>
                                                <button type="button" class="btn-xs duplicate-activity" data-activity-id="<?= $itemId ?>">⧉ Duplicar</button>
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
                    <?php if ($weekEditLocked): ?>
                        <div class="form-locked-notice">
                            🔒 <strong>Semana bloqueada.</strong>
                            <?php if ($weekStatus === 'approved'): ?>
                                La semana fue aprobada. No se pueden registrar nuevas actividades.
                            <?php else: ?>
                                Retira el envío para poder registrar actividades.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                    <p class="section-muted">Captura mínima para registrar en menos de 10 segundos.</p>
                    <?php endif; ?>
                    <form id="quick-add-form" <?= $weekEditLocked ? 'inert' : '' ?> style="<?= $weekEditLocked ? 'opacity:0.5;pointer-events:none' : '' ?>">
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
                        <div id="qa-task-selector-wrap">
                            <label>Tarea
                                <select name="task_id" id="qa-task">
                                    <option value="0">Registro general</option>
                                    <?php foreach ($tasksForTimesheet as $task): ?>
                                        <option value="<?= (int) ($task['task_id'] ?? 0) ?>" data-project-id="<?= (int) ($task['project_id'] ?? 0) ?>"><?= htmlspecialchars((string) ($task['project'] ?? '')) ?> · <?= htmlspecialchars((string) ($task['task_title'] ?? '')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <fieldset class="task-mgmt-group" id="qa-task-mgmt">
                            <legend>Gestión de tarea</legend>
                            <label class="radio-option">
                                <input type="radio" name="task_management" value="" id="qa-tmgmt-existing" checked>
                                <span>Usar tarea existente</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="task_management" value="completed" id="qa-tmgmt-completed">
                                <span>Registrar actividad finalizada <small>(crea tarea completada)</small></span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="task_management" value="pending" id="qa-tmgmt-pending">
                                <span>Crear tarea pendiente</span>
                            </label>
                        </fieldset>
                        <label class="conditional hidden" id="qa-new-task-title-wrap">Título de la nueva tarea*
                            <input type="text" name="new_task_title" id="qa-new-task-title" maxlength="180" placeholder="Ej: Diseñar arquitectura de integración">
                        </label>
                        <label>Horas*
                            <input type="number" name="hours" step="0.25" min="0.25" max="24" required>
                        </label>
                        <label>Descripción breve*
                            <input type="text" name="activity_description" maxlength="255" required>
                        </label>
                        <label>Tipo actividad
                            <select name="activity_type">
                                <option value="">Sin clasificar</option>
                                <?php foreach ($activityTypes as $type): ?>
                                    <option value="<?= htmlspecialchars((string) $type) ?>"><?= htmlspecialchars((string) ucfirst(str_replace('_', ' ', (string) $type))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Fase (opcional)
                            <input type="text" name="phase_name" maxlength="120">
                        </label>
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
                            <button type="submit" class="btn" data-submit-mode="save_duplicate">Guardar y duplicar</button>
                            <button type="submit" class="btn" data-submit-mode="save_another">Guardar y agregar otra</button>
                            <button type="button" class="btn ghost" id="save-template">Guardar como plantilla</button>
                        </div>
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
                                            data-phase-name="<?= htmlspecialchars((string) ($recent['phase_name'] ?? ''), ENT_QUOTES) ?>"
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
.btn-link,.btn,.btn-xs{border:1px solid var(--border);border-radius:10px;background:var(--surface);padding:8px 10px;cursor:pointer}
.btn.primary{background:var(--primary);border-color:var(--primary)}
.btn.success{background:color-mix(in srgb,var(--success) 24%,var(--surface));border-color:color-mix(in srgb,var(--success) 48%,var(--border))}
.btn.ghost{border-style:dashed}
.btn-xs{font-size:12px;padding:4px 8px}
.btn-xs.danger{border-color:color-mix(in srgb,var(--danger) 48%,var(--border));color:var(--danger)}
.btn-delete{background:color-mix(in srgb,var(--danger) 12%,var(--surface));border-color:color-mix(in srgb,var(--danger) 55%,var(--border));color:var(--danger);font-weight:600}
.btn-delete:hover{background:color-mix(in srgb,var(--danger) 22%,var(--surface))}
.week-locked-banner{display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:12px;background:color-mix(in srgb,#f59e0b 14%,var(--surface));border:1px solid color-mix(in srgb,#f59e0b 45%,var(--border));color:var(--text-primary)}
.week-locked-icon{font-size:20px;flex-shrink:0}
.week-locked-text strong{display:block}
.week-rejected-banner{display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:12px;background:color-mix(in srgb,var(--danger) 10%,var(--surface));border:1px solid color-mix(in srgb,var(--danger) 35%,var(--border))}
.chip-locked{opacity:.82;border-color:color-mix(in srgb,var(--border) 70%,transparent)}
.chip-lock-badge{font-size:11px;color:var(--text-secondary);display:inline-flex;align-items:center;gap:4px;padding:3px 7px;border:1px solid var(--border);border-radius:999px}
.chip-title{display:flex;flex-direction:column;gap:1px;overflow:hidden}
.chip-title strong{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chip-desc{font-size:12px;color:var(--text-secondary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chip-hours{font-weight:700;white-space:nowrap;flex-shrink:0;padding:2px 7px;border-radius:999px;background:color-mix(in srgb,var(--primary) 12%,var(--surface));font-size:13px}
.chip-comment{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.task-mgmt-group{border:1px solid var(--border);border-radius:10px;padding:10px 12px;margin:0}
.task-mgmt-group legend{font-size:12px;font-weight:600;color:var(--text-secondary);padding:0 4px}
.radio-option{display:flex;align-items:center;gap:8px;padding:5px 2px;cursor:pointer;font-size:13px}
.radio-option input[type="radio"]{width:16px;height:16px;accent-color:var(--primary)}
.radio-option small{color:var(--text-secondary);font-size:11px}
.form-locked-notice{padding:10px 12px;border-radius:10px;background:color-mix(in srgb,#f59e0b 12%,var(--surface));border:1px solid color-mix(in srgb,#f59e0b 40%,var(--border));font-size:13px;line-height:1.5}
.header-badges{display:flex;gap:8px;flex-wrap:wrap}
.pill{display:inline-flex;gap:6px;padding:6px 10px;border-radius:999px;border:1px solid var(--border);font-size:12px}
.pill.status.approved{background:#dcfce7}.pill.status.rejected{background:#fee2e2}.pill.status.submitted{background:#fef3c7}.pill.status.draft{background:#e5e7eb}
.header-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
.header-inline-form{display:flex;gap:6px;align-items:center}
.header-inline-form input{min-width:180px}
.indicators-grid{display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:10px}
.indicator{display:flex;flex-direction:column;gap:4px}
.indicator span{color:var(--text-secondary);font-size:12px}
.timesheet-main-layout{display:grid;grid-template-columns:7fr 3fr;gap:14px}
.calendar-column{display:flex;flex-direction:column;gap:10px}
.calendar-heading h3{margin:0 0 4px}
.week-calendar-grid{display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:10px}
.day-card{border:1px solid var(--border);border-radius:12px;padding:10px;min-height:170px;background:color-mix(in srgb,var(--surface) 94%,var(--background))}
.day-card header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.activity-list{margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:8px}
.activity-chip{border:1px solid var(--border);border-radius:10px;padding:8px;background:var(--surface);display:flex;flex-direction:column;gap:6px}
.activity-chip.dragging{opacity:.5}
.chip-main{display:flex;justify-content:space-between;gap:8px}
.chip-meta{display:flex;gap:6px;align-items:center;color:var(--text-secondary)}
.chip-actions{display:flex;gap:6px;flex-wrap:wrap}
.quick-add-column{display:flex;flex-direction:column}
.quick-add-box{position:sticky;top:150px;display:flex;flex-direction:column;gap:10px}
#quick-add-form{display:flex;flex-direction:column;gap:8px}
#quick-add-form label{display:flex;flex-direction:column;gap:4px;font-size:13px}
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
.quick-lists{display:flex;flex-direction:column;gap:10px}
.chip-list{display:flex;flex-wrap:wrap;gap:6px}
.chip-btn{border:1px solid var(--border);border-radius:999px;background:var(--surface);padding:5px 10px;cursor:pointer;font-size:12px}
.day-card.is-drop-target{outline:2px dashed color-mix(in srgb,var(--primary) 55%,var(--border))}
@media (max-width: 1100px){.timesheet-sticky-header{grid-template-columns:1fr}.timesheet-main-layout{grid-template-columns:1fr}.week-calendar-grid{grid-template-columns:1fr}.quick-add-box{position:static}.indicators-grid{grid-template-columns:repeat(2,minmax(120px,1fr))}}
</style>

<script>
(() => {
  const basePath = <?= json_encode($basePath) ?>;
  const weekValue = <?= json_encode($weekValue) ?>;
  const dayLabels = <?= json_encode($daysJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const weekEditLocked = <?= json_encode($weekEditLocked) ?>;
  const form = document.getElementById('quick-add-form');
  const projectInput = document.getElementById('qa-project');
  const taskInput = document.getElementById('qa-task');
  const taskSelectorWrap = document.getElementById('qa-task-selector-wrap');
  const taskMgmtGroup = document.getElementById('qa-task-mgmt');
  const newTaskTitleWrap = document.getElementById('qa-new-task-title-wrap');
  const newTaskTitleInput = document.getElementById('qa-new-task-title');
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

  const getSelectedTaskMgmt = () => {
    const checked = form?.querySelector('[name="task_management"]:checked');
    return checked ? checked.value : '';
  };

  const syncTaskMgmt = (isEditMode = false) => {
    const selected = getSelectedTaskMgmt();
    const needsNewTitle = !isEditMode && (selected === 'completed' || selected === 'pending');
    const useExisting = isEditMode || selected === '';

    if (newTaskTitleWrap) {
      newTaskTitleWrap.classList.toggle('hidden', !needsNewTitle);
    }
    if (newTaskTitleInput) {
      newTaskTitleInput.required = needsNewTitle;
      if (!needsNewTitle) newTaskTitleInput.value = '';
    }
    if (taskSelectorWrap) {
      taskSelectorWrap.classList.toggle('hidden', needsNewTitle);
    }
    if (taskMgmtGroup) {
      taskMgmtGroup.classList.toggle('hidden', isEditMode);
    }
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

  form?.querySelectorAll('[name="task_management"]').forEach((radio) => {
    radio.addEventListener('change', () => syncTaskMgmt(false));
  });

  filterTasksByProject();
  syncToggles();
  syncTaskMgmt(false);

  document.querySelectorAll('[data-submit-mode]').forEach((btn) => {
    btn.addEventListener('click', () => { lastSubmitMode = btn.dataset.submitMode || 'save'; });
  });

  const resetForAnother = () => {
    const keepDate = form.querySelector('[name="date"]')?.value || '';
    const keepProject = form.querySelector('[name="project_id"]')?.value || '';
    const keepTask = form.querySelector('[name="task_id"]')?.value || '0';
    form.reset();
    form.querySelector('[name="activity_id"]').value = '';
    form.querySelector('[name="date"]').value = keepDate;
    form.querySelector('[name="project_id"]').value = keepProject;
    filterTasksByProject();
    form.querySelector('[name="task_id"]').value = keepTask;
    syncToggles();
    syncTaskMgmt(false);
  };

  const fillForm = (data) => {
    form.querySelector('[name="activity_id"]').value = String(data.id || '');
    form.querySelector('[name="date"]').value = data.date || '';
    form.querySelector('[name="project_id"]').value = String(data.project_id || '');
    filterTasksByProject();
    form.querySelector('[name="task_id"]').value = String(data.task_id || 0);
    form.querySelector('[name="phase_name"]').value = data.phase_name || '';
    form.querySelector('[name="hours"]').value = String(data.hours || '');
    form.querySelector('[name="activity_description"]').value = data.activity_description || '';
    form.querySelector('[name="comment"]').value = data.comment || '';
    form.querySelector('[name="activity_type"]').value = data.activity_type || '';
    form.querySelector('[name="had_blocker"]').checked = Boolean(Number(data.had_blocker || 0));
    form.querySelector('[name="blocker_description"]').value = data.blocker_description || '';
    form.querySelector('[name="generated_deliverable"]').checked = Boolean(Number(data.generated_deliverable || 0));
    form.querySelector('[name="had_significant_progress"]').checked = Boolean(Number(data.had_significant_progress || 0));
    const existingRadio = form.querySelector('[name="task_management"][value=""]');
    if (existingRadio) existingRadio.checked = true;
    syncToggles();
    syncTaskMgmt(true);
    document.getElementById('quick-add-box')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(form);
    const raw = Object.fromEntries(formData.entries());
    raw.had_blocker = blockerToggle?.checked ? '1' : '0';
    raw.generated_deliverable = deliverableToggle?.checked ? '1' : '0';
    raw.had_significant_progress = progressToggle?.checked ? '1' : '0';
    const activityId = Number(raw.activity_id || 0);
    const deliverableNote = String(raw.deliverable_note || '').trim();
    const operationalParts = [String(raw.comment || '').trim()].filter(Boolean);
    if (deliverableNote !== '') operationalParts.push(`Entregable: ${deliverableNote}`);
    raw.operational_comment = operationalParts.join(' | ');

    if (activityId > 0) {
      raw.task_management = '';
      raw.new_task_title = '';
    }

    const endpoint = activityId > 0 ? '/timesheets/activities/update' : '/timesheets/activities/create';
    try {
      const response = await post(endpoint, raw);
      const finalActivityId = activityId > 0 ? activityId : Number(response.id || 0);
      if (lastSubmitMode === 'save_duplicate' && finalActivityId > 0) {
        await post('/timesheets/activities/duplicate', {
          activity_id: String(finalActivityId),
          target_date: nextDate(String(raw.date || '')),
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
      const activityId = Number(button.dataset.activityId || 0);
      const lines = Object.entries(dayLabels).map(([key, label]) => `${key}  ${label}`);
      const target = prompt(`Duplicar a (YYYY-MM-DD):\n${lines.join('\n')}`);
      if (!target) return;
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
      const activityId = Number(button.dataset.activityId || 0);
      const lines = Object.entries(dayLabels).map(([key, label]) => `${key}  ${label}`);
      const target = prompt(`Mover a (YYYY-MM-DD):\n${lines.join('\n')}`);
      if (!target) return;
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
      const activityId = Number(button.dataset.activityId || 0);
      const confirmed = confirm('¿Eliminar esta actividad?\n\nEsta acción no se puede deshacer.');
      if (!confirmed) return;
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
    const source = getDateByLabel('Selecciona día origen');
    if (!source) return;
    const target = getDateByLabel('Selecciona día destino');
    if (!target) return;
    try {
      await post('/timesheets/duplicate-day', { source_date: source, target_date: target });
      window.location.href = `${basePath}/timesheets?week=${encodeURIComponent(weekValue)}`;
    } catch (error) {
      alert(error.message || 'No se pudo duplicar el día.');
    }
  });

  document.getElementById('focus-quick-add')?.addEventListener('click', () => {
    document.getElementById('quick-add-box')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  document.querySelectorAll('.recent-fill').forEach((button) => {
    button.addEventListener('click', () => {
      form.querySelector('[name="activity_id"]').value = '';
      form.querySelector('[name="project_id"]').value = button.dataset.projectId || '';
      filterTasksByProject();
      form.querySelector('[name="task_id"]').value = button.dataset.taskId || '0';
      form.querySelector('[name="phase_name"]').value = button.dataset.phaseName || '';
      form.querySelector('[name="activity_type"]').value = button.dataset.activityType || '';
      form.querySelector('[name="activity_description"]').value = button.dataset.activityDescription || '';
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
        form.querySelector('[name="project_id"]').value = String(tpl.project_id || '');
        filterTasksByProject();
        form.querySelector('[name="task_id"]').value = String(tpl.task_id || 0);
        form.querySelector('[name="activity_type"]').value = tpl.activity_type || '';
        form.querySelector('[name="activity_description"]').value = tpl.activity_description || '';
        form.querySelector('[name="phase_name"]').value = tpl.phase_name || '';
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
    const projectId = Number(form.querySelector('[name="project_id"]').value || 0);
    const projectLabel = projectInput?.selectedOptions?.[0]?.textContent || 'Proyecto';
    const template = {
      project_id: projectId,
      project_label: projectLabel,
      task_id: Number(form.querySelector('[name="task_id"]').value || 0),
      phase_name: form.querySelector('[name="phase_name"]').value || '',
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
      event.preventDefault();
      dayCard.classList.add('is-drop-target');
    });
    dayCard.addEventListener('dragleave', () => dayCard.classList.remove('is-drop-target'));
    dayCard.addEventListener('drop', async (event) => {
      event.preventDefault();
      dayCard.classList.remove('is-drop-target');
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
