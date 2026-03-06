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
    'draft' => ['label' => 'Borrador', 'class' => 'st-draft', 'icon' => '📝'],
    'submitted' => ['label' => 'Enviada', 'class' => 'st-submitted', 'icon' => '📨'],
    'partial' => ['label' => 'Parcial', 'class' => 'st-submitted', 'icon' => '📨'],
    'approved' => ['label' => 'Aprobada', 'class' => 'st-approved', 'icon' => '✅'],
    'rejected' => ['label' => 'Rechazada', 'class' => 'st-rejected', 'icon' => '❌'],
];
$status = $statusMeta[$weekStatus] ?? $statusMeta['draft'];
$weekLocked = in_array($weekStatus, ['submitted', 'approved'], true);
$daysJson = [];
foreach ($gridDays as $day) {
    $daysJson[(string) ($day['key'] ?? '')] = (string) (($day['label'] ?? '') . ' ' . ($day['number'] ?? ''));
}

$typeColorMap = [
    'desarrollo' => 'chip-t-dev',
    'development' => 'chip-t-dev',
    'reunión' => 'chip-t-meeting',
    'reunion' => 'chip-t-meeting',
    'meeting' => 'chip-t-meeting',
    'soporte' => 'chip-t-support',
    'support' => 'chip-t-support',
    'gestion_pm' => 'chip-t-pm',
    'gestión' => 'chip-t-pm',
    'gestión pm' => 'chip-t-pm',
    'gestion' => 'chip-t-pm',
    'management' => 'chip-t-pm',
    'investigacion' => 'chip-t-research',
    'investigación' => 'chip-t-research',
    'research' => 'chip-t-research',
];

$weekTotal = round((float) ($weekIndicators['week_total'] ?? 0), 2);
$weeklyCapacity = round((float) ($weekIndicators['weekly_capacity'] ?? 0), 2);
$capacityTarget = $weeklyCapacity > 0 ? $weeklyCapacity : 40;
$remaining = max(0, $capacityTarget - $weekTotal);
$compliance = round((float) ($weekIndicators['compliance_percent'] ?? 0), 2);
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
                <span class="pill neutral">Total: <strong><?= $weekTotal ?>h / <?= $capacityTarget ?>h</strong></span>
                <span class="week-status-badge <?= htmlspecialchars($status['class']) ?>"><?= $status['icon'] ?> Estado semana: <strong><?= htmlspecialchars($status['label']) ?></strong></span>
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
        <?php if ($weekLocked): ?>
            <section class="card week-locked-banner">Semana enviada – registros bloqueados.</section>
        <?php endif; ?>

        <section class="indicators-grid">
            <article class="card indicator">
                <span>Horas registradas</span>
                <strong><?= $weekTotal ?>h <small class="indicator-cap">/ <?= $capacityTarget ?>h</small></strong>
                <div class="indicator-bar"><div class="indicator-bar-fill" style="width:<?= min(100, $capacityTarget > 0 ? ($weekTotal / $capacityTarget) * 100 : 0) ?>%"></div></div>
            </article>
            <article class="card indicator">
                <span>Capacidad restante</span>
                <strong><?= $remaining ?>h</strong>
            </article>
            <article class="card indicator">
                <span>Progreso semanal</span>
                <strong><?= $compliance ?>%</strong>
                <div class="indicator-bar"><div class="indicator-bar-fill <?= $compliance >= 100 ? 'bar-full' : '' ?>" style="width:<?= min(100, $compliance) ?>%"></div></div>
            </article>
            <article class="card indicator">
                <span>Proyecto con mayor carga</span>
                <strong><?= htmlspecialchars((string) ($weekIndicators['top_project'] ?? 'Sin datos')) ?></strong>
                <small><?= round((float) ($weekIndicators['top_project_hours'] ?? 0), 2) ?>h</small>
            </article>
        </section>

        <section class="timesheet-main-layout">

            <aside class="quick-add-column">
                <section class="card quick-add-box" id="quick-add-box">
                    <h3>Quick Add</h3>
                    <p class="section-muted">Captura rápida de actividad.</p>
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

                        <div class="task-mgmt-toggle">
                            <span class="task-management-title">Gestión de tarea</span>
                            <div class="toggle-btn-group">
                                <button type="button" class="toggle-btn active" data-mode="existing">Usar tarea existente</button>
                                <button type="button" class="toggle-btn" data-mode="new">Crear tarea nueva</button>
                            </div>
                            <input type="hidden" name="task_management_mode" value="existing">
                        </div>

                        <div class="task-new-fields hidden" id="qa-new-task-fields">
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

                        <div class="task-management-title" style="margin-top:4px">Contexto operativo</div>

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

                        <div class="quick-actions-main">
                            <button type="submit" class="btn primary" data-submit-mode="save">Guardar</button>
                            <button type="submit" class="btn" data-submit-mode="save_duplicate">Guardar y duplicar día</button>
                            <button type="submit" class="btn" data-submit-mode="save_another">Guardar y continuar</button>
                        </div>
                        <div class="quick-actions-extra">
                            <button type="button" class="btn ghost btn-sm" id="save-template">⭐ Guardar como plantilla</button>
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

            <div class="calendar-column card">
                <div class="calendar-heading">
                    <h3>Actividades de la semana</h3>
                    <p class="section-muted">Arrastra una actividad a otro día para moverla.</p>
                </div>
                <div class="week-calendar-grid">
                    <?php foreach ($gridDays as $day): ?>
                        <?php
                        $dayDate = (string) ($day['key'] ?? '');
                        $dayLabel = (string) ($day['label'] ?? '');
                        $dayNumber = (string) ($day['number'] ?? '');
                        $dayFull = $dayLabel . ' ' . $dayNumber;
                        $items = is_array($activitiesByDay[$dayDate] ?? null) ? $activitiesByDay[$dayDate] : [];
                        $dayOfWeek = $dayDate !== '' ? (int) date('w', strtotime($dayDate)) : 1;
                        $isWeekend = in_array($dayOfWeek, [0, 6], true);
                        ?>
                        <article class="day-card<?= $isWeekend ? ' day-weekend' : '' ?>" data-drop-day="<?= htmlspecialchars($dayDate) ?>" data-is-weekend="<?= $isWeekend ? '1' : '0' ?>">
                            <header class="day-header">
                                <strong><?= htmlspecialchars($dayFull) ?></strong>
                                <?php if ($isWeekend): ?>
                                    <span class="weekend-label">No laboral</span>
                                <?php else: ?>
                                    <span class="day-total"><?= round((float) ($dayTotals[$dayDate] ?? 0), 2) ?>h</span>
                                <?php endif; ?>
                            </header>
                            <?php if ($isWeekend && $items === []): ?>
                                <div class="day-empty weekend-empty">
                                    <span>🚫</span>
                                    <small>Fin de semana</small>
                                </div>
                            <?php elseif ($items === []): ?>
                                <div class="day-empty">
                                    <span>📋</span>
                                    <p>Sin actividades registradas</p>
                                    <small>Registra tu primera actividad</small>
                                </div>
                            <?php else: ?>
                                <ul class="activity-list">
                                    <?php foreach ($items as $item): ?>
                                        <?php
                                        $itemId = (int) ($item['id'] ?? 0);
                                        $itemProject = (string) ($item['project'] ?? 'Proyecto');
                                        $itemHours = (float) ($item['hours'] ?? 0);
                                        $itemType = strtolower(trim((string) ($item['activity_type'] ?? '')));
                                        $itemDesc = trim((string) ($item['activity_description'] ?? '')) ?: ucfirst(str_replace('_', ' ', $itemType ?: 'Actividad'));
                                        $itemComment = (string) ($item['comment'] ?? '');
                                        $chipClass = $typeColorMap[$itemType] ?? 'chip-t-default';
                                        $tooltipText = "Proyecto: {$itemProject}\nTarea: {$itemDesc}\nHoras: " . round($itemHours, 2) . "\nTipo: " . ucfirst(str_replace('_', ' ', $itemType)) . ($itemComment !== '' ? "\nComentario: {$itemComment}" : '');
                                        ?>
                                        <li class="activity-chip <?= $chipClass ?><?= $weekLocked ? ' is-locked' : '' ?>"
                                            <?= $weekLocked ? '' : 'draggable="true"' ?>
                                            data-activity-id="<?= $itemId ?>"
                                            data-tooltip="<?= htmlspecialchars($tooltipText, ENT_QUOTES) ?>">
                                            <div class="chip-main">
                                                <span class="chip-hours"><?= round($itemHours, 2) ?>h</span>
                                                <strong class="chip-desc"><?= htmlspecialchars($itemDesc) ?></strong>
                                            </div>
                                            <small class="chip-project"><?= htmlspecialchars($itemProject) ?></small>
                                            <div class="chip-meta">
                                                <?php if (!empty($item['had_blocker'])): ?><span title="Bloqueo">⛔</span><?php endif; ?>
                                                <?php if (!empty($item['generated_deliverable'])): ?><span title="Entregable">📦</span><?php endif; ?>
                                                <?php if (!empty($item['had_significant_progress'])): ?><span title="Avance">📈</span><?php endif; ?>
                                            </div>
                                            <?php if (!$weekLocked): ?>
                                                <div class="chip-actions">
                                                    <button type="button" class="btn-chip btn-edit edit-activity" data-payload='<?= htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>'>✏️ Editar</button>
                                                    <button type="button" class="btn-chip btn-delete delete-activity" data-activity-id="<?= $itemId ?>" title="Eliminar actividad">🗑️ Eliminar</button>
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

        </section>
    <?php endif; ?>
</section>

<div class="delete-modal-overlay hidden" id="delete-modal">
    <div class="delete-modal-box">
        <h4>¿Eliminar actividad?</h4>
        <p>Esta acción no se puede deshacer.</p>
        <div class="delete-modal-actions">
            <button type="button" class="btn" id="delete-cancel">Cancelar</button>
            <button type="button" class="btn btn-danger-solid" id="delete-confirm">Eliminar</button>
        </div>
    </div>
</div>

<style>
.timesheet-ux { display:flex; flex-direction:column; gap:14px; }

.timesheet-tabs { display:flex; gap:8px; flex-wrap:wrap; }
.tab { padding:8px 12px; border:1px solid var(--border); border-radius:999px; text-decoration:none; color:var(--text-primary); }
.tab.active { background:color-mix(in srgb,var(--primary) 18%,var(--surface)); border-color:color-mix(in srgb,var(--primary) 45%,var(--border)); font-weight:700; }

.timesheet-sticky-header { position:sticky; top:70px; z-index:5; display:grid; grid-template-columns:1.2fr 1fr 1.5fr; gap:10px; align-items:end; }
.header-week-form { display:flex; gap:8px; align-items:end; }
.header-week-form label { display:flex; flex-direction:column; gap:4px; font-size:13px; }

.btn-link,.btn,.btn-xs { border:1px solid var(--border); border-radius:10px; background:var(--surface); padding:8px 10px; cursor:pointer; font-size:13px; }
.btn[disabled],.btn-xs[disabled] { opacity:.6; cursor:not-allowed; }
.btn.primary { background:var(--primary); border-color:var(--primary); color:#fff; }
.btn.success { background:color-mix(in srgb,var(--success) 24%,var(--surface)); border-color:color-mix(in srgb,var(--success) 48%,var(--border)); }
.btn.ghost { border-style:dashed; }
.btn-sm { font-size:12px; padding:5px 8px; }

.header-badges { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.pill { display:inline-flex; gap:6px; padding:6px 10px; border-radius:999px; border:1px solid var(--border); font-size:12px; align-items:center; }

.week-status-badge {
    display:inline-flex; gap:6px; padding:7px 14px; border-radius:10px;
    font-size:13px; font-weight:600; align-items:center;
}
.week-status-badge.st-draft { background:#e5e7eb; color:#374151; border:1px solid #d1d5db; }
.week-status-badge.st-submitted { background:#dbeafe; color:#1d4ed8; border:1px solid #93c5fd; }
.week-status-badge.st-approved { background:#dcfce7; color:#166534; border:1px solid #86efac; }
.week-status-badge.st-rejected { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }

.header-actions { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
.header-inline-form { display:flex; gap:6px; align-items:center; }
.header-inline-form input { min-width:180px; }

.week-locked-banner { border-color:color-mix(in srgb,var(--warning) 45%,var(--border)); background:color-mix(in srgb,var(--warning) 18%,var(--surface)); font-weight:700; }

.indicators-grid { display:grid; grid-template-columns:repeat(4,minmax(140px,1fr)); gap:10px; }
.indicator { display:flex; flex-direction:column; gap:4px; }
.indicator span { color:var(--text-secondary); font-size:12px; }
.indicator-cap { font-size:12px; font-weight:400; color:var(--text-secondary); }
.indicator-bar { height:6px; background:#e5e7eb; border-radius:999px; overflow:hidden; margin-top:4px; }
.indicator-bar-fill { height:100%; background:var(--primary,#7c3aed); border-radius:999px; transition:width .3s ease; }
.indicator-bar-fill.bar-full { background:#22c55e; }

/* ===== Main layout: Quick Add 30% | Calendar 70% ===== */
.timesheet-main-layout { display:grid; grid-template-columns:3fr 7fr; gap:14px; }

/* ===== Quick Add column ===== */
.quick-add-column { display:flex; flex-direction:column; }
.quick-add-box { position:sticky; top:150px; display:flex; flex-direction:column; gap:10px; }
#quick-add-form { display:flex; flex-direction:column; gap:8px; }
.quick-add-fieldset { border:0; padding:0; margin:0; display:flex; flex-direction:column; gap:8px; }
#quick-add-form label { display:flex; flex-direction:column; gap:4px; font-size:13px; }

.task-mgmt-toggle { display:flex; flex-direction:column; gap:6px; }
.toggle-btn-group { display:flex; gap:0; border:1px solid var(--border); border-radius:10px; overflow:hidden; }
.toggle-btn {
    flex:1; padding:8px 6px; font-size:12px; font-weight:600;
    background:var(--surface); border:none; cursor:pointer;
    color:var(--text-secondary); transition:all .15s;
}
.toggle-btn + .toggle-btn { border-left:1px solid var(--border); }
.toggle-btn.active { background:var(--primary,#7c3aed); color:#fff; }
.toggle-btn:hover:not(.active) { background:color-mix(in srgb,var(--primary) 10%,var(--surface)); }

.task-new-fields { border:1px dashed var(--border); border-radius:10px; padding:8px; display:flex; flex-direction:column; gap:6px; }
.task-new-fields.hidden { display:none !important; }

.task-management-title { font-size:12px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; }

.toggle-field { display:grid !important; grid-template-columns:1fr auto auto; align-items:center; gap:10px !important; }
.toggle-caption { font-size:13px; color:var(--text-primary); }
.toggle-state { font-size:11px; font-weight:600; color:var(--text-secondary); min-width:28px; text-align:right; }
.toggle-state.is-on { color:var(--primary); }
.switch { position:relative; display:inline-block; width:44px; height:24px; }
.switch input { opacity:0; width:0; height:0; position:absolute; }
.switch .slider { position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background:#cbd5e1; border-radius:999px; transition:.2s; }
.switch .slider:before { position:absolute; content:""; height:18px; width:18px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.2s; box-shadow:0 1px 3px rgba(15,23,42,.25); }
.switch input:checked + .slider { background:#7c3aed; }
.switch input:checked + .slider:before { transform:translateX(20px); }
.conditional.hidden { display:none !important; }

.quick-actions-main { display:grid; grid-template-columns:1fr; gap:6px; margin-top:4px; }
.quick-actions-extra { border-top:1px dashed var(--border); padding-top:8px; }

.quick-lists { display:flex; flex-direction:column; gap:10px; }
.chip-list { display:flex; flex-wrap:wrap; gap:6px; }
.chip-btn { border:1px solid var(--border); border-radius:999px; background:var(--surface); padding:5px 10px; cursor:pointer; font-size:12px; }

/* ===== Calendar column ===== */
.calendar-column { display:flex; flex-direction:column; gap:10px; }
.calendar-heading h3 { margin:0 0 4px; }

.week-calendar-grid { display:flex; flex-direction:column; gap:8px; }

.day-card {
    border:1px solid var(--border); border-radius:12px; padding:12px 14px;
    min-height:60px; background:color-mix(in srgb,var(--surface) 94%,var(--background));
    transition:box-shadow .15s;
}
.day-card.is-drop-target { outline:2px dashed color-mix(in srgb,var(--primary) 55%,var(--border)); background:color-mix(in srgb,var(--primary) 6%,var(--surface)); }

.day-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
.day-total { font-size:13px; font-weight:700; color:var(--text-secondary); background:color-mix(in srgb,var(--primary) 10%,var(--surface)); padding:2px 8px; border-radius:999px; }

/* Weekend styling */
.day-weekend {
    background:#fff5f5 !important; border:1px dashed #fca5a5 !important;
    cursor:not-allowed; opacity:.85;
}
.weekend-label {
    font-size:11px; font-weight:700; color:#dc2626; text-transform:uppercase;
    background:#fee2e2; padding:2px 8px; border-radius:999px;
}
.weekend-empty { text-align:center; padding:6px 0; }
.weekend-empty span { font-size:18px; }
.weekend-empty small { color:#dc2626; font-size:11px; display:block; margin-top:2px; }

/* Empty state */
.day-empty { text-align:center; padding:12px 0; color:var(--text-secondary); }
.day-empty span { font-size:24px; display:block; margin-bottom:4px; }
.day-empty p { margin:0 0 2px; font-size:13px; font-weight:600; }
.day-empty small { font-size:11px; color:var(--text-secondary); }

/* Activity chips with type colors */
.activity-list { margin:0; padding:0; list-style:none; display:flex; flex-direction:column; gap:6px; }

.activity-chip {
    border-radius:10px; padding:8px 10px; display:flex; flex-direction:column; gap:4px;
    border-left:4px solid #94a3b8; background:#fff;
    border-top:1px solid #e2e8f0; border-right:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0;
    position:relative; transition:box-shadow .15s,transform .15s;
}
.activity-chip[draggable="true"] { cursor:grab; }
.activity-chip[draggable="true"]:active { cursor:grabbing; }
.activity-chip:hover { box-shadow:0 2px 8px rgba(0,0,0,.08); }
.activity-chip.dragging { opacity:.4; transform:scale(.96); box-shadow:0 4px 16px rgba(0,0,0,.15); }
.activity-chip.is-locked { opacity:.85; cursor:default; }

.chip-t-dev { border-left-color:#3b82f6; }
.chip-t-meeting { border-left-color:#8b5cf6; }
.chip-t-support { border-left-color:#f97316; }
.chip-t-pm { border-left-color:#22c55e; }
.chip-t-research { border-left-color:#6b7280; }
.chip-t-default { border-left-color:#94a3b8; }

.chip-main { display:flex; align-items:center; gap:8px; }
.chip-hours {
    font-size:11px; font-weight:800; white-space:nowrap; padding:2px 6px;
    border-radius:6px; min-width:32px; text-align:center;
}
.chip-t-dev .chip-hours { background:#dbeafe; color:#1d4ed8; }
.chip-t-meeting .chip-hours { background:#ede9fe; color:#6d28d9; }
.chip-t-support .chip-hours { background:#fff7ed; color:#c2410c; }
.chip-t-pm .chip-hours { background:#dcfce7; color:#166534; }
.chip-t-research .chip-hours { background:#f3f4f6; color:#374151; }
.chip-t-default .chip-hours { background:#f1f5f9; color:#475569; }

.chip-desc { font-size:13px; line-height:1.3; }
.chip-project { color:var(--text-secondary); font-size:11px; }
.chip-meta { display:flex; gap:4px; align-items:center; font-size:12px; }

/* Chip action buttons */
.chip-actions { display:flex; gap:6px; margin-top:2px; }
.btn-chip {
    font-size:11px; padding:3px 8px; border-radius:8px; cursor:pointer;
    border:1px solid transparent; font-weight:600; transition:all .12s;
    display:inline-flex; align-items:center; gap:3px;
}
.btn-edit { background:#f0f4ff; color:#3b82f6; border-color:#bfdbfe; }
.btn-edit:hover { background:#3b82f6; color:#fff; }

.btn-delete {
    background:#fee2e2 !important; color:#dc2626 !important;
    border:1px solid #fca5a5 !important; font-weight:700;
}
.btn-delete:hover { background:#dc2626 !important; color:#fff !important; }

/* Tooltip */
.activity-chip[data-tooltip] { position:relative; }
.activity-chip[data-tooltip]:hover::after {
    content:attr(data-tooltip); position:absolute; left:0; bottom:calc(100% + 6px);
    background:#1e293b; color:#f8fafc; font-size:11px; padding:8px 10px;
    border-radius:8px; white-space:pre-line; z-index:20; max-width:280px;
    box-shadow:0 4px 12px rgba(0,0,0,.2); pointer-events:none; line-height:1.5;
}

/* Delete confirmation modal */
.delete-modal-overlay {
    position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:100;
    display:flex; align-items:center; justify-content:center;
}
.delete-modal-overlay.hidden { display:none !important; }
.delete-modal-box {
    background:#fff; border-radius:14px; padding:24px 28px; max-width:380px;
    box-shadow:0 16px 48px rgba(0,0,0,.18); text-align:center;
}
.delete-modal-box h4 { margin:0 0 6px; font-size:16px; color:#1e293b; }
.delete-modal-box p { margin:0 0 18px; font-size:13px; color:#64748b; }
.delete-modal-actions { display:flex; gap:10px; justify-content:center; }
.btn-danger-solid {
    background:#dc2626 !important; color:#fff !important;
    border:1px solid #b91c1c !important; font-weight:700;
}
.btn-danger-solid:hover { background:#b91c1c !important; }

@media (max-width:1100px) {
    .timesheet-sticky-header { grid-template-columns:1fr; }
    .timesheet-main-layout { grid-template-columns:1fr; }
    .quick-add-box { position:static; }
    .indicators-grid { grid-template-columns:repeat(2,minmax(120px,1fr)); }
}
</style>

<script>
(() => {
  const basePath = <?= json_encode($basePath) ?>;
  const weekValue = <?= json_encode($weekValue) ?>;
  const weekLocked = <?= $weekLocked ? 'true' : 'false' ?>;
  const dayLabels = <?= json_encode($daysJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const form = document.getElementById('quick-add-form');
  const projectInput = document.getElementById('qa-project');
  const taskInput = document.getElementById('qa-task');
  const newTaskWrap = document.getElementById('qa-new-task-fields');
  const newTaskTitleInput = form ? form.querySelector('[name="new_task_title"]') : null;
  const taskModeInput = form ? form.querySelector('[name="task_management_mode"]') : null;
  const blockerToggle = document.getElementById('qa-blocker');
  const blockerWrap = document.getElementById('qa-blocker-wrap');
  const deliverableToggle = document.getElementById('qa-deliverable');
  const deliverableWrap = document.getElementById('qa-deliverable-wrap');
  const progressToggle = document.getElementById('qa-progress');
  const templatesKey = 'timesheet.quick.templates.v1';
  let lastSubmitMode = 'save';

  const deleteModal = document.getElementById('delete-modal');
  const deleteConfirmBtn = document.getElementById('delete-confirm');
  const deleteCancelBtn = document.getElementById('delete-cancel');
  let pendingDeleteId = null;

  const isWeekendDate = (dateStr) => {
    if (!dateStr) return false;
    const d = new Date(`${dateStr}T12:00:00`);
    const dow = d.getDay();
    return dow === 0 || dow === 6;
  };

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
    try { data = await res.json(); } catch (e) {}
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

  /* Toggle buttons for task management mode */
  const toggleBtns = document.querySelectorAll('.toggle-btn-group .toggle-btn');
  const syncTaskManagementMode = () => {
    const mode = taskModeInput ? taskModeInput.value : 'existing';
    const useExisting = mode === 'existing';
    if (taskInput) {
      taskInput.disabled = !useExisting;
      if (!useExisting) taskInput.value = '0';
    }
    if (newTaskWrap) newTaskWrap.classList.toggle('hidden', useExisting);
    if (newTaskTitleInput) newTaskTitleInput.required = !useExisting;
    toggleBtns.forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.mode === mode);
    });
  };
  toggleBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      if (taskModeInput) taskModeInput.value = btn.dataset.mode || 'existing';
      syncTaskManagementMode();
    });
  });

  const toggleConditional = (toggle, wrap, requiredWhenOn = false) => {
    wrap?.classList.toggle('hidden', !toggle.checked);
    const input = wrap?.querySelector('input');
    if (input) {
      input.required = requiredWhenOn && Boolean(toggle.checked);
    }
    if (!toggle.checked && input) input.value = '';
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
    const keepMode = taskModeInput ? taskModeInput.value : 'existing';
    form.reset();
    form.querySelector('[name="activity_id"]').value = '';
    form.querySelector('[name="date"]').value = keepDate;
    form.querySelector('[name="project_id"]').value = keepProject;
    filterTasksByProject();
    form.querySelector('[name="task_id"]').value = keepTask;
    if (taskModeInput) taskModeInput.value = keepMode;
    syncToggles();
    syncTaskManagementMode();
  };

  const fillForm = (data) => {
    form.querySelector('[name="activity_id"]').value = String(data.id || '');
    form.querySelector('[name="date"]').value = data.date || '';
    form.querySelector('[name="project_id"]').value = String(data.project_id || '');
    filterTasksByProject();
    form.querySelector('[name="task_id"]').value = String(data.task_id || 0);
    if (taskModeInput) taskModeInput.value = 'existing';
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
    if (weekLocked) { alert('Semana enviada – registros bloqueados.'); return; }
    const formData = new FormData(form);
    const raw = Object.fromEntries(formData.entries());
    const selectedDate = String(raw.date || '');
    if (isWeekendDate(selectedDate)) {
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
      if (weekLocked) return;
      try {
        const payload = JSON.parse(button.dataset.payload || '{}');
        fillForm(payload);
      } catch (e) {
        alert('No se pudo cargar la actividad para edición.');
      }
    });
  });

  /* Delete with custom modal */
  document.querySelectorAll('.delete-activity').forEach((button) => {
    button.addEventListener('click', () => {
      if (weekLocked) return;
      pendingDeleteId = Number(button.dataset.activityId || 0);
      deleteModal?.classList.remove('hidden');
    });
  });

  deleteCancelBtn?.addEventListener('click', () => {
    pendingDeleteId = null;
    deleteModal?.classList.add('hidden');
  });

  deleteConfirmBtn?.addEventListener('click', async () => {
    if (!pendingDeleteId) return;
    deleteModal?.classList.add('hidden');
    try {
      await post('/timesheets/activities/delete', { activity_id: String(pendingDeleteId) });
      window.location.href = `${basePath}/timesheets?week=${encodeURIComponent(weekValue)}`;
    } catch (error) {
      alert(error.message || 'No se pudo eliminar.');
    }
    pendingDeleteId = null;
  });

  deleteModal?.addEventListener('click', (e) => {
    if (e.target === deleteModal) {
      pendingDeleteId = null;
      deleteModal.classList.add('hidden');
    }
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

  document.querySelectorAll('.recent-fill').forEach((button) => {
    button.addEventListener('click', () => {
      form.querySelector('[name="activity_id"]').value = '';
      form.querySelector('[name="project_id"]').value = button.dataset.projectId || '';
      filterTasksByProject();
      form.querySelector('[name="task_id"]').value = button.dataset.taskId || '0';
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
    } catch (e) { return []; }
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

  /* Drag & drop */
  let draggingActivityId = null;
  document.querySelectorAll('.activity-chip[draggable="true"]').forEach((chip) => {
    chip.addEventListener('dragstart', (e) => {
      if (weekLocked) return;
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
      if (weekLocked) return;
      if (dayCard.dataset.isWeekend === '1') {
        event.dataTransfer.dropEffect = 'none';
        return;
      }
      event.preventDefault();
      dayCard.classList.add('is-drop-target');
    });
    dayCard.addEventListener('dragleave', () => dayCard.classList.remove('is-drop-target'));
    dayCard.addEventListener('drop', async (event) => {
      event.preventDefault();
      dayCard.classList.remove('is-drop-target');
      if (weekLocked) return;
      if (dayCard.dataset.isWeekend === '1') {
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
