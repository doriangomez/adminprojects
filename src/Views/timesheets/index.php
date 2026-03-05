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
$weekLocked = in_array($weekStatus, ['submitted', 'approved'], true);
$weekEditable = in_array($weekStatus, ['draft', 'rejected'], true);
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
                <?php if ($weekEditable): ?>
                    <button type="button" class="btn primary" id="focus-quick-add">+ Registrar actividad</button>
                    <button type="button" class="btn" id="duplicate-day-trigger">Duplicar día</button>
                    <form method="POST" action="<?= $basePath ?>/timesheets/submit-week">
                        <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                        <button type="submit" class="btn success">Enviar semana</button>
                    </form>
                <?php endif; ?>
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

        <?php if ($weekLocked): ?>
            <div class="week-locked-banner">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <span>Semana <?= $weekStatus === 'approved' ? 'aprobada' : 'enviada' ?>. Los registros están bloqueados para edición y eliminación.</span>
            </div>
        <?php endif; ?>

        <?php if ($weekStatus === 'rejected'): ?>
            <div class="week-rejected-banner">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                <span>Semana rechazada. Puedes corregir y volver a enviar los registros.
                    <?php if (!empty($selectedWeekSummary['approval_comment'])): ?>
                        <strong>Motivo:</strong> <?= htmlspecialchars((string) $selectedWeekSummary['approval_comment']) ?>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>

        <section class="indicators-grid">
            <article class="card indicator"><span>Horas registradas</span><strong><?= round((float) ($weekIndicators['week_total'] ?? 0), 2) ?>h</strong></article>
            <article class="card indicator"><span>Capacidad semanal</span><strong><?= round((float) ($weekIndicators['weekly_capacity'] ?? 0), 2) ?>h</strong></article>
            <article class="card indicator"><span>% cumplimiento</span><strong><?= round((float) ($weekIndicators['compliance_percent'] ?? 0), 2) ?>%</strong></article>
            <article class="card indicator"><span>Proyecto mayor consumo</span><strong><?= htmlspecialchars((string) ($weekIndicators['top_project'] ?? 'Sin datos')) ?></strong><small><?= round((float) ($weekIndicators['top_project_hours'] ?? 0), 2) ?>h</small></article>
        </section>

        <section class="timesheet-main-layout">
            <div class="calendar-column card">
                <div class="calendar-heading">
                    <h3>Actividades registradas de la semana</h3>
                    <p class="section-muted"><?= $weekEditable ? 'Arrastra una actividad a otro día para moverla. Cada bloque es interactivo.' : 'Vista de lectura — semana bloqueada.' ?></p>
                </div>
                <div class="week-calendar-grid">
                    <?php foreach ($gridDays as $day): ?>
                        <?php
                        $dayDate = (string) ($day['key'] ?? '');
                        $dayLabel = (string) (($day['label'] ?? '') . ' ' . ($day['number'] ?? ''));
                        $items = is_array($activitiesByDay[$dayDate] ?? null) ? $activitiesByDay[$dayDate] : [];
                        $dayTotal = round((float) ($dayTotals[$dayDate] ?? 0), 2);
                        ?>
                        <article class="day-card <?= $weekLocked ? 'is-locked' : '' ?>" data-drop-day="<?= htmlspecialchars($dayDate) ?>">
                            <header>
                                <strong><?= htmlspecialchars($dayLabel) ?></strong>
                                <span class="day-total-badge"><?= $dayTotal ?>h</span>
                            </header>
                            <?php if ($items === []): ?>
                                <p class="section-muted empty-day">Sin actividades</p>
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
                                        ?>
                                        <li class="activity-chip <?= $weekLocked ? 'chip-locked' : '' ?>" <?= $weekEditable ? 'draggable="true"' : '' ?> data-activity-id="<?= $itemId ?>">
                                            <div class="chip-hours-bar">
                                                <span class="chip-hours-value"><?= round($itemHours, 2) ?>h</span>
                                            </div>
                                            <div class="chip-main">
                                                <strong class="chip-title"><?= htmlspecialchars($itemProject) ?></strong>
                                                <span class="chip-desc"><?= htmlspecialchars($itemDesc) ?></span>
                                            </div>
                                            <div class="chip-meta">
                                                <?php if (!empty($item['had_blocker'])): ?><span class="chip-flag flag-blocker" title="Bloqueo">&#9940;</span><?php endif; ?>
                                                <?php if (!empty($item['generated_deliverable'])): ?><span class="chip-flag flag-deliverable" title="Entregable">&#128230;</span><?php endif; ?>
                                                <?php if (!empty($item['had_significant_progress'])): ?><span class="chip-flag flag-progress" title="Avance">&#128200;</span><?php endif; ?>
                                                <?php if ($itemComment !== ''): ?><small class="chip-comment"><?= htmlspecialchars($itemComment) ?></small><?php endif; ?>
                                            </div>
                                            <?php if ($weekEditable): ?>
                                            <div class="chip-actions">
                                                <button type="button" class="chip-action-btn edit-activity" data-payload='<?= htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>' title="Editar">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                </button>
                                                <button type="button" class="chip-action-btn duplicate-activity" data-activity-id="<?= $itemId ?>" title="Duplicar">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                                </button>
                                                <button type="button" class="chip-action-btn move-activity" data-activity-id="<?= $itemId ?>" title="Mover">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 9 2 12 5 15"/><polyline points="9 5 12 2 15 5"/><polyline points="15 19 12 22 9 19"/><polyline points="19 9 22 12 19 15"/><line x1="2" y1="12" x2="22" y2="12"/><line x1="12" y1="2" x2="12" y2="22"/></svg>
                                                </button>
                                                <button type="button" class="chip-action-btn chip-delete-btn delete-activity" data-activity-id="<?= $itemId ?>" title="Eliminar actividad">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                                </button>
                                            </div>
                                            <?php else: ?>
                                            <div class="chip-locked-label">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                                Bloqueado
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
                    <?php if ($weekLocked): ?>
                        <div class="form-locked-overlay">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <p>Semana <?= $weekStatus === 'approved' ? 'aprobada' : 'enviada' ?>. Registros bloqueados.</p>
                        </div>
                    <?php else: ?>
                        <p class="section-muted">Captura mínima para registrar en menos de 10 segundos.</p>
                        <form id="quick-add-form">
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
                            <label>Tarea existente
                                <select name="task_id" id="qa-task">
                                    <option value="0">Registro general (sin tarea)</option>
                                    <?php foreach ($tasksForTimesheet as $task): ?>
                                        <option value="<?= (int) ($task['task_id'] ?? 0) ?>" data-project-id="<?= (int) ($task['project_id'] ?? 0) ?>"><?= htmlspecialchars((string) ($task['project'] ?? '')) ?> · <?= htmlspecialchars((string) ($task['task_title'] ?? '')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <fieldset class="task-management-fieldset" id="task-management-section">
                                <legend>Gestión de tarea</legend>
                                <div class="task-mgmt-options">
                                    <label class="radio-option">
                                        <input type="radio" name="task_management_mode" value="" checked>
                                        <span class="radio-label">
                                            <strong>Usar tarea seleccionada</strong>
                                            <small>Asigna horas a la tarea existente elegida arriba</small>
                                        </span>
                                    </label>
                                    <label class="radio-option">
                                        <input type="radio" name="task_management_mode" value="completed">
                                        <span class="radio-label">
                                            <strong>Registrar actividad finalizada</strong>
                                            <small>Crea una tarea marcada como completada automaticamente</small>
                                        </span>
                                    </label>
                                    <label class="radio-option">
                                        <input type="radio" name="task_management_mode" value="pending">
                                        <span class="radio-label">
                                            <strong>Crear tarea pendiente</strong>
                                            <small>Crea una tarea abierta para gestionar en Proyectos</small>
                                        </span>
                                    </label>
                                </div>
                            </fieldset>

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
                    <?php endif; ?>
                </section>
            </aside>
        </section>
    <?php endif; ?>
</section>

<!-- Delete confirmation modal -->
<div class="delete-modal-overlay" id="delete-modal" style="display:none">
    <div class="delete-modal">
        <div class="delete-modal-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
        </div>
        <h4>¿Eliminar actividad?</h4>
        <p class="section-muted">Esta acción no se puede deshacer. La actividad será eliminada permanentemente.</p>
        <div class="delete-modal-actions">
            <button type="button" class="btn" id="delete-modal-cancel">Cancelar</button>
            <button type="button" class="btn delete-confirm-btn" id="delete-modal-confirm">Eliminar</button>
        </div>
    </div>
</div>

<!-- Move activity modal -->
<div class="delete-modal-overlay" id="move-modal" style="display:none">
    <div class="delete-modal">
        <h4>Mover actividad</h4>
        <p class="section-muted">Selecciona el día destino:</p>
        <div class="move-day-grid" id="move-day-grid">
            <?php foreach ($gridDays as $day): ?>
                <button type="button" class="move-day-btn" data-date="<?= htmlspecialchars((string) ($day['key'] ?? '')) ?>">
                    <?= htmlspecialchars((string) (($day['label'] ?? '') . ' ' . ($day['number'] ?? ''))) ?>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="delete-modal-actions">
            <button type="button" class="btn" id="move-modal-cancel">Cancelar</button>
        </div>
    </div>
</div>

<!-- Duplicate activity modal -->
<div class="delete-modal-overlay" id="dup-modal" style="display:none">
    <div class="delete-modal">
        <h4>Duplicar actividad</h4>
        <p class="section-muted">Selecciona el día destino:</p>
        <div class="move-day-grid" id="dup-day-grid">
            <?php foreach ($gridDays as $day): ?>
                <button type="button" class="dup-day-btn" data-date="<?= htmlspecialchars((string) ($day['key'] ?? '')) ?>">
                    <?= htmlspecialchars((string) (($day['label'] ?? '') . ' ' . ($day['number'] ?? ''))) ?>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="delete-modal-actions">
            <button type="button" class="btn" id="dup-modal-cancel">Cancelar</button>
        </div>
    </div>
</div>

<style>
.timesheet-ux{display:flex;flex-direction:column;gap:14px}
.timesheet-tabs{display:flex;gap:8px;flex-wrap:wrap}
.tab{padding:8px 12px;border:1px solid var(--border);border-radius:999px;text-decoration:none;color:var(--text-primary)}
.tab.active{background:color-mix(in srgb,var(--primary) 18%,var(--surface));border-color:color-mix(in srgb,var(--primary) 45%,var(--border));font-weight:700}
.timesheet-sticky-header{position:sticky;top:70px;z-index:5;display:grid;grid-template-columns:1.2fr 1fr 1.5fr;gap:10px;align-items:end}
.header-week-form{display:flex;gap:8px;align-items:end}
.header-week-form label{display:flex;flex-direction:column;gap:4px;font-size:13px}
.btn-link,.btn,.btn-xs{border:1px solid var(--border);border-radius:10px;background:var(--surface);padding:8px 10px;cursor:pointer;font-size:13px}
.btn.primary{background:var(--primary);border-color:var(--primary);color:#fff}
.btn.success{background:color-mix(in srgb,var(--success) 24%,var(--surface));border-color:color-mix(in srgb,var(--success) 48%,var(--border))}
.btn.ghost{border-style:dashed}
.btn-xs{font-size:12px;padding:4px 8px}
.header-badges{display:flex;gap:8px;flex-wrap:wrap}
.pill{display:inline-flex;gap:6px;padding:6px 10px;border-radius:999px;border:1px solid var(--border);font-size:12px}
.pill.status.approved{background:#dcfce7}.pill.status.rejected{background:#fee2e2}.pill.status.submitted{background:#fef3c7}.pill.status.draft{background:#e5e7eb}
.header-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
.header-inline-form{display:flex;gap:6px;align-items:center}
.header-inline-form input{min-width:180px}

.week-locked-banner{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:12px;background:#fef3c7;border:1px solid #fcd34d;color:#92400e;font-size:13px;font-weight:500}
.week-rejected-banner{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:12px;background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;font-size:13px;font-weight:500}

.indicators-grid{display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:10px}
.indicator{display:flex;flex-direction:column;gap:4px}
.indicator span{color:var(--text-secondary);font-size:12px}
.timesheet-main-layout{display:grid;grid-template-columns:7fr 3fr;gap:14px}
.calendar-column{display:flex;flex-direction:column;gap:10px}
.calendar-heading h3{margin:0 0 4px}
.week-calendar-grid{display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:10px}

.day-card{border:1px solid var(--border);border-radius:12px;padding:12px;min-height:170px;background:color-mix(in srgb,var(--surface) 94%,var(--background));transition:box-shadow .2s}
.day-card.is-locked{opacity:.85}
.day-card header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid var(--border)}
.day-total-badge{font-size:13px;font-weight:700;padding:2px 8px;border-radius:999px;background:color-mix(in srgb,var(--primary) 12%,var(--surface));color:var(--primary)}
.empty-day{font-size:12px;text-align:center;padding:20px 0}
.activity-list{margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:8px}

.activity-chip{border:1px solid var(--border);border-radius:10px;padding:0;background:var(--surface);display:flex;flex-direction:column;overflow:hidden;transition:box-shadow .2s,border-color .2s}
.activity-chip:hover{box-shadow:0 2px 8px rgba(0,0,0,.08);border-color:color-mix(in srgb,var(--primary) 30%,var(--border))}
.activity-chip.dragging{opacity:.5;transform:rotate(2deg)}
.activity-chip.chip-locked{cursor:default}
.activity-chip.chip-locked:hover{box-shadow:none;border-color:var(--border)}

.chip-hours-bar{padding:6px 10px;background:color-mix(in srgb,var(--primary) 8%,var(--surface));border-bottom:1px solid color-mix(in srgb,var(--primary) 12%,var(--border));display:flex;justify-content:flex-end}
.chip-hours-value{font-size:13px;font-weight:700;color:var(--primary)}

.chip-main{padding:8px 10px 4px;display:flex;flex-direction:column;gap:2px}
.chip-title{font-size:12px;font-weight:600;color:var(--text-primary)}
.chip-desc{font-size:11px;color:var(--text-secondary)}

.chip-meta{display:flex;gap:6px;align-items:center;padding:0 10px 6px;flex-wrap:wrap}
.chip-flag{font-size:13px}
.chip-comment{font-size:11px;color:var(--text-secondary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px}

.chip-actions{display:flex;gap:2px;padding:4px 6px;border-top:1px solid var(--border);background:color-mix(in srgb,var(--surface) 96%,var(--background))}
.chip-action-btn{display:flex;align-items:center;justify-content:center;width:28px;height:28px;border:none;background:transparent;border-radius:6px;cursor:pointer;color:var(--text-secondary);transition:background .15s,color .15s}
.chip-action-btn:hover{background:color-mix(in srgb,var(--primary) 12%,var(--surface));color:var(--primary)}
.chip-delete-btn{color:#ef4444 !important}
.chip-delete-btn:hover{background:#fef2f2 !important;color:#dc2626 !important}

.chip-locked-label{display:flex;align-items:center;gap:4px;padding:4px 10px;border-top:1px solid var(--border);background:color-mix(in srgb,var(--surface) 96%,var(--background));font-size:11px;color:var(--text-secondary)}

.day-card.is-drop-target{outline:2px dashed color-mix(in srgb,var(--primary) 55%,var(--border));background:color-mix(in srgb,var(--primary) 5%,var(--surface))}

.quick-add-column{display:flex;flex-direction:column}
.quick-add-box{position:sticky;top:150px;display:flex;flex-direction:column;gap:10px}
.form-locked-overlay{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;padding:40px 20px;text-align:center;color:var(--text-secondary)}
.form-locked-overlay p{font-size:13px;font-weight:500}
#quick-add-form{display:flex;flex-direction:column;gap:8px}
#quick-add-form label{display:flex;flex-direction:column;gap:4px;font-size:13px}

.task-management-fieldset{border:1px solid var(--border);border-radius:10px;padding:10px 12px;margin:0}
.task-management-fieldset legend{font-size:12px;font-weight:600;color:var(--text-primary);padding:0 6px}
.task-mgmt-options{display:flex;flex-direction:column;gap:6px}
.radio-option{display:flex;align-items:flex-start;gap:8px;cursor:pointer;padding:6px 8px;border-radius:8px;border:1px solid transparent;transition:background .15s,border-color .15s}
.radio-option:hover{background:color-mix(in srgb,var(--primary) 5%,var(--surface))}
.radio-option input[type="radio"]{margin-top:3px;accent-color:var(--primary)}
.radio-option input[type="radio"]:checked ~ .radio-label{color:var(--text-primary)}
.radio-label{display:flex;flex-direction:column;gap:1px}
.radio-label strong{font-size:12px;font-weight:600}
.radio-label small{font-size:11px;color:var(--text-secondary)}

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

/* Delete modal */
.delete-modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.45);z-index:1000;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(2px)}
.delete-modal{background:var(--surface,#fff);border-radius:16px;padding:28px;max-width:380px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.2);text-align:center}
.delete-modal-icon{margin-bottom:12px}
.delete-modal h4{margin:0 0 8px;font-size:16px}
.delete-modal .section-muted{font-size:13px;margin-bottom:16px}
.delete-modal-actions{display:flex;gap:10px;justify-content:center}
.delete-confirm-btn{background:#ef4444 !important;border-color:#ef4444 !important;color:#fff !important;font-weight:600}
.delete-confirm-btn:hover{background:#dc2626 !important}

/* Move/Dup modal */
.move-day-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-bottom:16px}
.move-day-btn,.dup-day-btn{padding:10px 8px;border:1px solid var(--border);border-radius:8px;background:var(--surface);cursor:pointer;font-size:12px;font-weight:500;transition:background .15s,border-color .15s}
.move-day-btn:hover,.dup-day-btn:hover{background:color-mix(in srgb,var(--primary) 12%,var(--surface));border-color:var(--primary);color:var(--primary)}

@media (max-width: 1100px){.timesheet-sticky-header{grid-template-columns:1fr}.timesheet-main-layout{grid-template-columns:1fr}.week-calendar-grid{grid-template-columns:1fr}.quick-add-box{position:static}.indicators-grid{grid-template-columns:repeat(2,minmax(120px,1fr))}.move-day-grid{grid-template-columns:repeat(3,1fr)}}
</style>

<script>
(() => {
  const basePath = <?= json_encode($basePath) ?>;
  const weekValue = <?= json_encode($weekValue) ?>;
  const weekLocked = <?= json_encode($weekLocked) ?>;
  const dayLabels = <?= json_encode($daysJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const form = document.getElementById('quick-add-form');
  const projectInput = document.getElementById('qa-project');
  const taskInput = document.getElementById('qa-task');
  const blockerToggle = document.getElementById('qa-blocker');
  const blockerWrap = document.getElementById('qa-blocker-wrap');
  const deliverableToggle = document.getElementById('qa-deliverable');
  const deliverableWrap = document.getElementById('qa-deliverable-wrap');
  const progressToggle = document.getElementById('qa-progress');
  const templatesKey = 'timesheet.quick.templates.v1';
  let lastSubmitMode = 'save';

  if (weekLocked) return;
  if (!form) return;

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

  const reload = () => {
    window.location.href = `${basePath}/timesheets?week=${encodeURIComponent(weekValue)}`;
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

  const syncTaskManagement = () => {
    const mode = form.querySelector('[name="task_management_mode"]:checked')?.value || '';
    if (taskInput) {
      taskInput.closest('label').style.display = (mode === 'completed' || mode === 'pending') ? 'none' : '';
    }
  };

  const toggleConditional = (toggle, wrap, requiredWhenOn = false) => {
    if (!toggle || !wrap) return;
    wrap.classList.toggle('hidden', !toggle.checked);
    const input = wrap.querySelector('input');
    if (input) {
      input.required = requiredWhenOn && Boolean(toggle.checked);
    }
    if (!toggle.checked && input) {
      input.value = '';
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
  form.querySelectorAll('[name="task_management_mode"]').forEach((radio) => {
    radio.addEventListener('change', syncTaskManagement);
  });
  filterTasksByProject();
  syncToggles();
  syncTaskManagement();

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
    syncTaskManagement();
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
    const defaultMode = form.querySelector('[name="task_management_mode"][value=""]');
    if (defaultMode) defaultMode.checked = true;
    syncToggles();
    syncTaskManagement();
    document.getElementById('quick-add-box')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  form.addEventListener('submit', async (event) => {
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

    const endpoint = activityId > 0 ? '/timesheets/activities/update' : '/timesheets/activities/create';
    try {
      const response = await post(endpoint, raw);
      const finalActivityId = activityId > 0 ? activityId : Number(response.id || 0);
      if (lastSubmitMode === 'save_duplicate' && finalActivityId > 0) {
        await post('/timesheets/activities/duplicate', {
          activity_id: String(finalActivityId),
          target_date: nextDate(String(raw.date || '')),
        });
        reload();
        return;
      }
      if (lastSubmitMode === 'save_another') {
        resetForAnother();
        return;
      }
      reload();
    } catch (error) {
      alert(error.message || 'No se pudo guardar.');
    }
  });

  // Edit activity
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

  // Duplicate activity (with modal)
  let pendingDuplicateId = 0;
  const dupModal = document.getElementById('dup-modal');
  document.querySelectorAll('.duplicate-activity').forEach((button) => {
    button.addEventListener('click', () => {
      pendingDuplicateId = Number(button.dataset.activityId || 0);
      if (dupModal) dupModal.style.display = 'flex';
    });
  });
  document.querySelectorAll('.dup-day-btn').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (!pendingDuplicateId) return;
      if (dupModal) dupModal.style.display = 'none';
      try {
        await post('/timesheets/activities/duplicate', {
          activity_id: String(pendingDuplicateId),
          target_date: btn.dataset.date,
        });
        reload();
      } catch (error) {
        alert(error.message || 'No se pudo duplicar.');
      }
    });
  });
  document.getElementById('dup-modal-cancel')?.addEventListener('click', () => {
    if (dupModal) dupModal.style.display = 'none';
    pendingDuplicateId = 0;
  });

  // Move activity (with modal)
  let pendingMoveId = 0;
  const moveModal = document.getElementById('move-modal');
  document.querySelectorAll('.move-activity').forEach((button) => {
    button.addEventListener('click', () => {
      pendingMoveId = Number(button.dataset.activityId || 0);
      if (moveModal) moveModal.style.display = 'flex';
    });
  });
  document.querySelectorAll('.move-day-btn').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (!pendingMoveId) return;
      if (moveModal) moveModal.style.display = 'none';
      try {
        await post('/timesheets/activities/move', {
          activity_id: String(pendingMoveId),
          target_date: btn.dataset.date,
        });
        reload();
      } catch (error) {
        alert(error.message || 'No se pudo mover.');
      }
    });
  });
  document.getElementById('move-modal-cancel')?.addEventListener('click', () => {
    if (moveModal) moveModal.style.display = 'none';
    pendingMoveId = 0;
  });

  // Delete activity (with confirmation modal)
  let pendingDeleteId = 0;
  const deleteModal = document.getElementById('delete-modal');
  document.querySelectorAll('.delete-activity').forEach((button) => {
    button.addEventListener('click', () => {
      pendingDeleteId = Number(button.dataset.activityId || 0);
      if (deleteModal) deleteModal.style.display = 'flex';
    });
  });
  document.getElementById('delete-modal-confirm')?.addEventListener('click', async () => {
    if (!pendingDeleteId) return;
    if (deleteModal) deleteModal.style.display = 'none';
    try {
      await post('/timesheets/activities/delete', { activity_id: String(pendingDeleteId) });
      reload();
    } catch (error) {
      alert(error.message || 'No se pudo eliminar.');
    }
  });
  document.getElementById('delete-modal-cancel')?.addEventListener('click', () => {
    if (deleteModal) deleteModal.style.display = 'none';
    pendingDeleteId = 0;
  });

  // Close modals on overlay click
  [deleteModal, moveModal, dupModal].forEach((modal) => {
    modal?.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.style.display = 'none';
      }
    });
  });

  // Duplicate day
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
      reload();
    } catch (error) {
      alert(error.message || 'No se pudo duplicar el día.');
    }
  });

  document.getElementById('focus-quick-add')?.addEventListener('click', () => {
    document.getElementById('quick-add-box')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  // Recent fills
  document.querySelectorAll('.recent-fill').forEach((button) => {
    button.addEventListener('click', () => {
      form.querySelector('[name="activity_id"]').value = '';
      form.querySelector('[name="project_id"]').value = button.dataset.projectId || '';
      filterTasksByProject();
      form.querySelector('[name="task_id"]').value = button.dataset.taskId || '0';
      form.querySelector('[name="phase_name"]').value = button.dataset.phaseName || '';
      form.querySelector('[name="activity_type"]').value = button.dataset.activityType || '';
      form.querySelector('[name="activity_description"]').value = button.dataset.activityDescription || '';
      const defaultMode = form.querySelector('[name="task_management_mode"][value=""]');
      if (defaultMode) defaultMode.checked = true;
      syncTaskManagement();
      form.querySelector('[name="activity_description"]').focus();
    });
  });

  // Templates
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
      remove.textContent = '\u2715';
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

  // Drag and drop
  let draggingActivityId = null;
  document.querySelectorAll('.activity-chip[draggable="true"]').forEach((chip) => {
    chip.addEventListener('dragstart', (e) => {
      draggingActivityId = Number(chip.dataset.activityId || 0);
      chip.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
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
        reload();
      } catch (error) {
        alert(error.message || 'No se pudo mover la actividad.');
      }
    });
  });

  renderTemplates();
})();
</script>
