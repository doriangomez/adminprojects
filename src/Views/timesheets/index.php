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

        <?php
        $weekTotal = round((float) ($weekIndicators['week_total'] ?? 0), 2);
        $weekCap = round((float) ($weekIndicators['weekly_capacity'] ?? 0), 2);
        $compliancePct = round((float) ($weekIndicators['compliance_percent'] ?? 0), 1);
        $remaining = round(max(0, $weekCap - $weekTotal), 2);
        $topProject = htmlspecialchars((string) ($weekIndicators['top_project'] ?? 'Sin datos'));
        $topProjectHours = round((float) ($weekIndicators['top_project_hours'] ?? 0), 2);
        $progressColor = $compliancePct >= 100 ? '#22c55e' : ($compliancePct >= 60 ? '#f97316' : '#ef4444');
        ?>
        <section class="indicators-grid">
            <article class="card indicator">
                <span class="ind-label">Horas registradas</span>
                <div class="ind-value-row">
                    <strong class="ind-value"><?= $weekTotal ?>h</strong>
                    <span class="ind-cap">/ <?= $weekCap ?>h</span>
                </div>
                <div class="ind-bar"><div class="ind-bar-fill" style="width:<?= min(100, $compliancePct) ?>%;background:<?= $progressColor ?>"></div></div>
            </article>
            <article class="card indicator">
                <span class="ind-label">Capacidad restante</span>
                <strong class="ind-value <?= $remaining <= 0 ? 'ind-done' : '' ?>"><?= $remaining <= 0 ? '✓ Completo' : $remaining . 'h' ?></strong>
                <small class="ind-sub">de <?= $weekCap ?>h semanales</small>
            </article>
            <article class="card indicator">
                <span class="ind-label">Progreso semanal</span>
                <strong class="ind-value" style="color:<?= $progressColor ?>"><?= $compliancePct ?>%</strong>
                <div class="ind-bar"><div class="ind-bar-fill" style="width:<?= min(100, $compliancePct) ?>%;background:<?= $progressColor ?>"></div></div>
            </article>
            <article class="card indicator">
                <span class="ind-label">Mayor carga</span>
                <strong class="ind-value"><?= $topProject ?></strong>
                <small class="ind-sub"><?= $topProjectHours ?>h registradas</small>
            </article>
        </section>
        <div class="week-status-bar">
            <span class="week-status-label">Estado de semana:</span>
            <span class="pill status <?= htmlspecialchars($status['class']) ?>"><?= htmlspecialchars($status['label']) ?></span>
        </div>

        <section class="timesheet-main-layout">
            <aside class="quick-add-column">
                <section class="card quick-add-box" id="quick-add-box">
                    <h3>Quick Add</h3>
                    <p class="section-muted">Captura mínima para registrar en menos de 10 segundos.</p>
                    <form id="quick-add-form">
                        <fieldset class="quick-add-fieldset" <?= $weekLocked ? 'disabled' : '' ?>>
                        <input type="hidden" name="activity_id" value="">
                        <input type="hidden" name="submit_mode" value="save">
                        <label>Fecha
                            <input type="date" name="date" value="<?= htmlspecialchars($weekStart->format('Y-m-d')) ?>" required id="qa-date">
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
                        <div class="task-mode-group">
                            <span class="task-management-title">Gestión de tarea</span>
                            <div class="task-mode-buttons">
                                <button type="button" class="task-mode-btn active" data-mode="existing">Usar tarea existente</button>
                                <button type="button" class="task-mode-btn" data-mode="new">Crear tarea nueva</button>
                            </div>
                            <input type="hidden" name="task_management_mode" value="existing" id="task-mode-input">
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
                        <label>Tipo de actividad*
                            <select name="activity_type" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($activityTypes as $type): ?>
                                    <option value="<?= htmlspecialchars((string) $type) ?>"><?= htmlspecialchars((string) ucfirst(str_replace('_', ' ', (string) $type))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="context-section-title">Contexto operativo</div>
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
                            <span class="toggle-caption">Entregable generado</span>
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
                            <strong>Plantillas guardadas</strong>
                            <div class="chip-list" id="template-list"></div>
                            <button type="button" class="btn ghost template-save-btn" id="save-template">+ Guardar plantilla actual</button>
                        </div>
                    </div>
                </section>
            </aside>

            <div class="calendar-column card">
                <div class="calendar-heading">
                    <h3>Actividades de la semana</h3>
                    <p class="section-muted">Arrastra un chip a otro día para moverlo. Pasa el cursor sobre un chip para ver el detalle.</p>
                </div>
                <div class="week-calendar-grid">
                    <?php foreach ($gridDays as $day): ?>
                        <?php
                        $dayDate = (string) ($day['key'] ?? '');
                        $dayLabel = (string) (($day['label'] ?? '') . ' ' . ($day['number'] ?? ''));
                        $items = is_array($activitiesByDay[$dayDate] ?? null) ? $activitiesByDay[$dayDate] : [];
                        $dayDOW = $dayDate ? (int) date('N', strtotime($dayDate)) : 0;
                        $isWeekend = $dayDOW >= 6;
                        ?>
                        <article class="day-card<?= $isWeekend ? ' is-weekend' : '' ?>" data-drop-day="<?= htmlspecialchars($dayDate) ?>" data-is-weekend="<?= $isWeekend ? '1' : '0' ?>">
                            <header>
                                <strong><?= htmlspecialchars($dayLabel) ?></strong>
                                <?php if (!$isWeekend): ?>
                                    <span class="day-total"><?= round((float) ($dayTotals[$dayDate] ?? 0), 2) ?>h</span>
                                <?php endif; ?>
                            </header>
                            <?php if ($isWeekend): ?>
                                <div class="weekend-notice">
                                    <span class="weekend-icon">🚫</span>
                                    <span>No laboral</span>
                                </div>
                            <?php elseif ($items === []): ?>
                                <div class="empty-day">
                                    <span class="empty-day-icon">○</span>
                                    <span class="empty-day-text">Sin actividades</span>
                                    <small>Registra tu primera actividad</small>
                                </div>
                            <?php else: ?>
                                <ul class="activity-list">
                                    <?php foreach ($items as $item): ?>
                                        <?php
                                        $itemId = (int) ($item['id'] ?? 0);
                                        $itemProject = (string) ($item['project'] ?? 'Proyecto');
                                        $itemTask = trim((string) ($item['task_title'] ?? ''));
                                        $itemHours = (float) ($item['hours'] ?? 0);
                                        $itemDesc = trim((string) ($item['activity_description'] ?? '')) ?: trim((string) ($item['activity_type'] ?? 'Actividad'));
                                        $itemComment = (string) ($item['comment'] ?? '');
                                        $itemType = strtolower(trim((string) ($item['activity_type'] ?? '')));
                                        $itemTypeLabel = ucfirst(str_replace('_', ' ', $itemType));
                                        $tooltipParts = array_filter([
                                            'Proyecto: ' . $itemProject,
                                            $itemTask !== '' ? 'Tarea: ' . $itemTask : null,
                                            'Horas: ' . round($itemHours, 2),
                                            $itemType !== '' ? 'Tipo: ' . $itemTypeLabel : null,
                                            $itemComment !== '' ? 'Comentario: ' . $itemComment : null,
                                        ]);
                                        $tooltip = implode("\n", $tooltipParts);
                                        ?>
                                        <li class="activity-chip<?= $itemType !== '' ? ' atype-' . htmlspecialchars($itemType) : '' ?><?= $weekLocked ? ' is-locked' : '' ?>" <?= $weekLocked ? '' : 'draggable="true"' ?> data-activity-id="<?= $itemId ?>" title="<?= htmlspecialchars($tooltip) ?>">
                                            <div class="chip-header">
                                                <span class="chip-hours-badge"><?= round($itemHours, 2) ?>h</span>
                                                <strong class="chip-title"><?= htmlspecialchars($itemDesc) ?></strong>
                                            </div>
                                            <small class="chip-project">📁 <?= htmlspecialchars($itemProject) ?></small>
                                            <?php if ($itemType !== ''): ?>
                                                <span class="chip-type-label"><?= htmlspecialchars($itemTypeLabel) ?></span>
                                            <?php endif; ?>
                                            <div class="chip-meta">
                                                <?php if (!empty($item['had_blocker'])): ?><span title="Bloqueo registrado">⛔</span><?php endif; ?>
                                                <?php if (!empty($item['generated_deliverable'])): ?><span title="Entregable generado">📦</span><?php endif; ?>
                                                <?php if (!empty($item['had_significant_progress'])): ?><span title="Avance significativo">📈</span><?php endif; ?>
                                            </div>
                                            <?php if (!$weekLocked): ?>
                                                <div class="chip-actions">
                                                    <button type="button" class="chip-action-btn edit-activity" data-payload='<?= htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>' title="Editar actividad">✏ Editar</button>
                                                    <button type="button" class="chip-action-btn danger delete-activity" data-activity-id="<?= $itemId ?>" data-desc="<?= htmlspecialchars($itemDesc, ENT_QUOTES) ?>" title="Eliminar actividad">🗑 Eliminar</button>
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

<style>
/* ── Base layout ── */
.timesheet-ux{display:flex;flex-direction:column;gap:14px}
.timesheet-tabs{display:flex;gap:8px;flex-wrap:wrap}
.tab{padding:8px 14px;border:1px solid var(--border);border-radius:999px;text-decoration:none;color:var(--text-primary);font-size:13px;transition:background .15s}
.tab.active{background:color-mix(in srgb,var(--primary) 15%,var(--surface));border-color:color-mix(in srgb,var(--primary) 45%,var(--border));font-weight:700}

/* ── Sticky header ── */
.timesheet-sticky-header{position:sticky;top:70px;z-index:5;display:grid;grid-template-columns:1.2fr 1fr 1.5fr;gap:10px;align-items:end}
.header-week-form{display:flex;gap:8px;align-items:end}
.header-week-form label{display:flex;flex-direction:column;gap:4px;font-size:13px}
.header-badges{display:flex;gap:8px;flex-wrap:wrap}
.header-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
.header-inline-form{display:flex;gap:6px;align-items:center}
.header-inline-form input{min-width:180px}

/* ── Buttons ── */
.btn-link,.btn{border:1px solid var(--border);border-radius:10px;background:var(--surface);padding:8px 12px;cursor:pointer;font-size:13px;transition:background .15s,border-color .15s}
.btn[disabled]{opacity:.55;cursor:not-allowed}
.btn.primary{background:var(--primary);border-color:var(--primary);color:#fff;font-weight:600}
.btn.primary:hover:not([disabled]){filter:brightness(1.08)}
.btn.success{background:color-mix(in srgb,var(--success) 22%,var(--surface));border-color:color-mix(in srgb,var(--success) 50%,var(--border))}
.btn.ghost{border-style:dashed;background:transparent;font-size:12px}
.btn.danger{background:#ef4444;border-color:#ef4444;color:#fff;font-weight:600}
.btn.danger:hover{background:#dc2626;border-color:#dc2626}

/* ── Pills / badges ── */
.pill{display:inline-flex;gap:6px;padding:5px 11px;border-radius:999px;border:1px solid var(--border);font-size:12px;font-weight:500}
.pill.status.approved{background:#dcfce7;border-color:#86efac;color:#166534}
.pill.status.rejected{background:#fee2e2;border-color:#fca5a5;color:#991b1b}
.pill.status.submitted{background:#fef3c7;border-color:#fcd34d;color:#92400e}
.pill.status.draft{background:#e5e7eb;border-color:#d1d5db;color:#374151}
.week-locked-banner{border-color:color-mix(in srgb,var(--warning) 45%,var(--border));background:color-mix(in srgb,var(--warning) 18%,var(--surface));font-weight:700}

/* ── Indicators ── */
.indicators-grid{display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:10px}
.indicator{display:flex;flex-direction:column;gap:5px}
.ind-label{color:var(--text-secondary);font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px}
.ind-value-row{display:flex;align-items:baseline;gap:5px}
.ind-value{font-size:22px;font-weight:700;line-height:1;color:var(--text-primary)}
.ind-cap{font-size:13px;color:var(--text-secondary)}
.ind-sub{font-size:11px;color:var(--text-secondary)}
.ind-done{color:#22c55e !important}
.ind-bar{height:5px;background:var(--border);border-radius:999px;overflow:hidden}
.ind-bar-fill{height:100%;border-radius:999px;transition:width .5s ease}

/* ── Week status bar ── */
.week-status-bar{display:flex;align-items:center;gap:8px;padding:4px 2px}
.week-status-label{font-size:13px;font-weight:600;color:var(--text-secondary)}

/* ── Main layout: Quick Add (3fr) | Calendar (7fr) ── */
.timesheet-main-layout{display:grid;grid-template-columns:3fr 7fr;gap:14px;align-items:start}

/* ── Quick Add panel ── */
.quick-add-column{display:flex;flex-direction:column}
.quick-add-box{position:sticky;top:150px;display:flex;flex-direction:column;gap:12px;max-height:calc(100vh - 180px);overflow-y:auto;scrollbar-width:thin}
#quick-add-form{display:flex;flex-direction:column;gap:8px}
.quick-add-fieldset{border:0;padding:0;margin:0;display:flex;flex-direction:column;gap:8px}
#quick-add-form label{display:flex;flex-direction:column;gap:4px;font-size:13px}

/* ── Task mode button toggle ── */
.task-mode-group{display:flex;flex-direction:column;gap:6px}
.task-mode-buttons{display:flex;gap:6px}
.task-mode-btn{flex:1;padding:7px 6px;border:1.5px solid var(--border);border-radius:10px;background:var(--surface);cursor:pointer;font-size:12px;font-weight:500;text-align:center;transition:all .15s;color:var(--text-primary)}
.task-mode-btn.active{background:color-mix(in srgb,var(--primary) 13%,var(--surface));border-color:var(--primary);color:var(--primary);font-weight:700}
.task-mode-btn:hover:not(.active){background:var(--background)}
.task-management-block{border:1px dashed var(--border);border-radius:10px;padding:8px;display:flex;flex-direction:column;gap:6px}
.task-management-title{font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.4px}
.context-section-title{font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.4px;padding-top:4px;border-top:1px solid var(--border);margin-top:2px}

/* ── Toggle switches ── */
.toggle-field{display:grid !important;grid-template-columns:1fr auto auto;align-items:center;gap:10px !important}
.toggle-caption{font-size:13px;color:var(--text-primary)}
.toggle-state{font-size:11px;font-weight:600;color:var(--text-secondary);min-width:28px;text-align:right}
.toggle-state.is-on{color:var(--primary)}
.switch{position:relative;display:inline-block;width:44px;height:24px}
.switch input{opacity:0;width:0;height:0;position:absolute}
.switch .slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#cbd5e1;border-radius:999px;transition:.2s}
.switch .slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s;box-shadow:0 1px 3px rgba(15,23,42,.25)}
.switch input:checked + .slider{background:var(--primary)}
.switch input:checked + .slider:before{transform:translateX(20px)}
.conditional.hidden{display:none !important}

/* ── Quick action buttons ── */
.quick-actions{display:grid;grid-template-columns:1fr;gap:6px}
.quick-lists{display:flex;flex-direction:column;gap:10px}
.chip-list{display:flex;flex-wrap:wrap;gap:6px}
.chip-btn{border:1px solid var(--border);border-radius:999px;background:var(--surface);padding:5px 10px;cursor:pointer;font-size:12px;transition:background .15s}
.chip-btn:hover{background:var(--background)}
.template-save-btn{width:100%;margin-top:6px;text-align:center}

/* ── Calendar column ── */
.calendar-column{display:flex;flex-direction:column;gap:10px}
.calendar-heading h3{margin:0 0 4px}
.week-calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(130px,1fr));gap:8px}

/* ── Day cards ── */
.day-card{border:1px solid var(--border);border-radius:12px;padding:10px;min-height:180px;background:color-mix(in srgb,var(--surface) 94%,var(--background));display:flex;flex-direction:column;gap:8px;transition:outline .15s}
.day-card header{display:flex;justify-content:space-between;align-items:center}
.day-card header strong{font-size:13px;font-weight:700}
.day-total{font-size:11px;font-weight:700;color:var(--primary);background:color-mix(in srgb,var(--primary) 12%,var(--surface));padding:2px 8px;border-radius:999px}
.day-card.is-drop-target{outline:2px dashed color-mix(in srgb,var(--primary) 55%,var(--border));background:color-mix(in srgb,var(--primary) 5%,var(--surface))}

/* ── Weekend days ── */
.day-card.is-weekend{background:#fff0f0;border:1.5px dashed #fca5a5;cursor:not-allowed}
.day-card.is-weekend header strong{color:#ef4444}
.weekend-notice{display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1;gap:4px;color:#ef4444;font-size:12px;font-weight:500;padding:12px 0;text-align:center}
.weekend-icon{font-size:22px}

/* ── Empty day state ── */
.empty-day{display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1;gap:3px;color:var(--text-secondary);font-size:12px;padding:14px 0;text-align:center}
.empty-day-icon{font-size:18px;color:var(--border);font-weight:300}
.empty-day-text{font-weight:600;font-size:12px}

/* ── Activity list ── */
.activity-list{margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px}

/* ── Activity chips ── */
.activity-chip{border-radius:10px;padding:8px 9px;display:flex;flex-direction:column;gap:4px;border:1px solid var(--border);border-left-width:3px;border-left-color:var(--border);background:var(--surface);transition:box-shadow .15s,transform .1s}
.activity-chip[draggable="true"]{cursor:grab}
.activity-chip[draggable="true"]:hover{box-shadow:0 2px 10px rgba(0,0,0,.10)}
.activity-chip[draggable="true"]:active{cursor:grabbing}
.activity-chip.dragging{opacity:.4;box-shadow:0 10px 30px rgba(0,0,0,.2);transform:rotate(1.5deg)}
.activity-chip.is-locked{opacity:.85;cursor:default}

/* ── Activity type color coding ── */
.atype-desarrollo{border-left-color:#3b82f6;background:rgba(59,130,246,.06)}
.atype-reunion{border-left-color:#8b5cf6;background:rgba(139,92,246,.06)}
.atype-soporte{border-left-color:#f97316;background:rgba(249,115,22,.06)}
.atype-gestion_pm{border-left-color:#22c55e;background:rgba(34,197,94,.06)}
.atype-investigacion{border-left-color:#6b7280;background:rgba(107,114,128,.06)}
.atype-analisis{border-left-color:#06b6d4;background:rgba(6,182,212,.06)}
.atype-documentacion{border-left-color:#6366f1;background:rgba(99,102,241,.06)}
.atype-pruebas{border-left-color:#eab308;background:rgba(234,179,8,.06)}

/* ── Chip internals ── */
.chip-header{display:flex;align-items:flex-start;gap:6px}
.chip-hours-badge{font-size:11px;font-weight:700;background:var(--primary);color:#fff;padding:2px 7px;border-radius:999px;white-space:nowrap;flex-shrink:0;margin-top:1px}
.chip-title{font-size:12px;font-weight:600;line-height:1.35;word-break:break-word}
.chip-project{font-size:11px;color:var(--text-secondary)}
.chip-type-label{font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--text-secondary);opacity:.75}
.chip-meta{display:flex;gap:5px;align-items:center;font-size:13px}

/* ── Chip action buttons ── */
.chip-actions{display:flex;gap:5px;flex-wrap:wrap;margin-top:2px}
.chip-action-btn{font-size:11px;padding:3px 9px;border-radius:8px;border:1.5px solid var(--border);background:var(--surface);cursor:pointer;font-weight:500;transition:all .15s;color:var(--text-primary)}
.chip-action-btn:hover{background:var(--background);border-color:var(--text-secondary)}
.chip-action-btn.danger{border-color:#ef4444;color:#ef4444;background:rgba(239,68,68,.08)}
.chip-action-btn.danger:hover{background:#fee2e2;border-color:#dc2626;color:#dc2626}

/* ── Delete confirmation modal ── */
.confirm-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.48);z-index:9999;display:flex;align-items:center;justify-content:center;animation:fadeIn .15s ease}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.confirm-modal{background:var(--surface);border-radius:16px;padding:28px 24px;max-width:380px;width:calc(100% - 32px);box-shadow:0 24px 64px rgba(0,0,0,.28);display:flex;flex-direction:column;gap:16px;animation:slideUp .15s ease}
@keyframes slideUp{from{transform:translateY(12px);opacity:0}to{transform:translateY(0);opacity:1}}
.confirm-modal h4{margin:0;font-size:16px;font-weight:700}
.confirm-modal p{margin:0;font-size:14px;color:var(--text-secondary);line-height:1.5}
.confirm-modal-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:4px}

/* ── Responsive ── */
@media (max-width:1300px){.timesheet-main-layout{grid-template-columns:4fr 6fr}}
@media (max-width:1000px){.timesheet-sticky-header{grid-template-columns:1fr}.timesheet-main-layout{grid-template-columns:1fr}.quick-add-box{position:static;max-height:none}.indicators-grid{grid-template-columns:repeat(2,minmax(120px,1fr))}}
@media (max-width:700px){.week-calendar-grid{grid-template-columns:1fr}.indicators-grid{grid-template-columns:1fr}}
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
  const taskModeInput = document.getElementById('task-mode-input');
  const newTaskWrap = document.getElementById('qa-new-task-fields');
  const newTaskTitleInput = form ? form.querySelector('[name="new_task_title"]') : null;
  const blockerToggle = document.getElementById('qa-blocker');
  const blockerWrap = document.getElementById('qa-blocker-wrap');
  const deliverableToggle = document.getElementById('qa-deliverable');
  const deliverableWrap = document.getElementById('qa-deliverable-wrap');
  const progressToggle = document.getElementById('qa-progress');
  const dateInput = document.getElementById('qa-date');
  const templatesKey = 'timesheet.quick.templates.v1';
  let lastSubmitMode = 'save';

  /* ── Helpers ── */
  const nextDate = (dateStr) => {
    const d = new Date(`${dateStr}T00:00:00`);
    d.setDate(d.getDate() + 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  };

  const isWeekendDate = (dateStr) => {
    if (!dateStr) return false;
    const dow = new Date(`${dateStr}T00:00:00`).getDay();
    return dow === 0 || dow === 6;
  };

  const post = async (path, payloadObj) => {
    const res = await fetch(`${basePath}${path}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(payloadObj),
    });
    let data = {};
    try { data = await res.json(); } catch (e) {}
    if (!res.ok || data.ok === false) throw new Error(data.message || 'No se pudo completar la acción.');
    return data;
  };

  /* ── Confirm modal (replaces native confirm()) ── */
  const showConfirmModal = (title, message) => new Promise((resolve) => {
    const overlay = document.createElement('div');
    overlay.className = 'confirm-modal-overlay';
    overlay.innerHTML = `
      <div class="confirm-modal">
        <h4>${title}</h4>
        <p>${message}</p>
        <div class="confirm-modal-actions">
          <button type="button" class="btn" id="cm-cancel">Cancelar</button>
          <button type="button" class="btn danger" id="cm-confirm">Eliminar</button>
        </div>
      </div>`;
    document.body.appendChild(overlay);
    const close = (result) => { overlay.remove(); resolve(result); };
    overlay.querySelector('#cm-cancel').addEventListener('click', () => close(false));
    overlay.querySelector('#cm-confirm').addEventListener('click', () => close(true));
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(false); });
  });

  /* ── Task project filter ── */
  const filterTasksByProject = () => {
    if (!taskInput) return;
    const projectId = Number(projectInput?.value || 0);
    taskInput.querySelectorAll('option[data-project-id]').forEach((opt) => {
      opt.hidden = projectId > 0 && Number(opt.dataset.projectId || 0) !== projectId;
    });
    if (taskInput.selectedOptions[0]?.hidden) taskInput.value = '0';
  };

  /* ── Task mode toggle buttons ── */
  const setTaskMode = (mode) => {
    if (taskModeInput) taskModeInput.value = mode;
    document.querySelectorAll('.task-mode-btn').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.mode === mode);
    });
    syncTaskManagementMode();
  };

  const syncTaskManagementMode = () => {
    if (!taskInput) return;
    const useExisting = (taskModeInput?.value || 'existing') === 'existing';
    taskInput.disabled = !useExisting;
    if (!useExisting) taskInput.value = '0';
    newTaskWrap?.classList.toggle('hidden', useExisting);
    if (newTaskTitleInput) newTaskTitleInput.required = !useExisting;
  };

  document.querySelectorAll('.task-mode-btn').forEach((btn) => {
    btn.addEventListener('click', () => setTaskMode(btn.dataset.mode || 'existing'));
  });

  /* ── Toggle switches ── */
  const toggleConditional = (toggle, wrap, requiredWhenOn = false) => {
    wrap?.classList.toggle('hidden', !toggle?.checked);
    const input = wrap?.querySelector('input');
    if (input) {
      input.required = requiredWhenOn && Boolean(toggle?.checked);
      if (!toggle?.checked) input.value = '';
    }
  };

  const syncToggleLabels = () => {
    document.querySelectorAll('[data-toggle-state]').forEach((state) => {
      const input = document.getElementById(state.getAttribute('data-toggle-state') || '');
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

  /* ── Weekend warning on date change ── */
  dateInput?.addEventListener('change', () => {
    if (isWeekendDate(dateInput.value)) {
      showInfoToast('⚠️ Registro no permitido en fines de semana');
    }
  });

  /* ── Toast notification ── */
  const showInfoToast = (message) => {
    const toast = document.createElement('div');
    toast.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#1e293b;color:#fff;padding:10px 20px;border-radius:10px;font-size:13px;z-index:9999;box-shadow:0 4px 16px rgba(0,0,0,.3);animation:fadeIn .2s ease';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
  };

  /* ── Init ── */
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

  /* ── Reset form for "continue" mode ── */
  const resetForAnother = () => {
    const keepDate = form.querySelector('[name="date"]')?.value || '';
    const keepProject = form.querySelector('[name="project_id"]')?.value || '';
    const keepTask = form.querySelector('[name="task_id"]')?.value || '0';
    const keepMode = taskModeInput?.value || 'existing';
    form.reset();
    form.querySelector('[name="activity_id"]').value = '';
    form.querySelector('[name="date"]').value = keepDate;
    form.querySelector('[name="project_id"]').value = keepProject;
    filterTasksByProject();
    form.querySelector('[name="task_id"]').value = keepTask;
    setTaskMode(keepMode);
    syncToggles();
  };

  /* ── Fill form for editing ── */
  const fillForm = (data) => {
    form.querySelector('[name="activity_id"]').value = String(data.id || '');
    form.querySelector('[name="date"]').value = data.date || '';
    form.querySelector('[name="project_id"]').value = String(data.project_id || '');
    filterTasksByProject();
    form.querySelector('[name="task_id"]').value = String(data.task_id || 0);
    setTaskMode('existing');
    form.querySelector('[name="hours"]').value = String(data.hours || '');
    form.querySelector('[name="activity_description"]').value = data.activity_description || '';
    form.querySelector('[name="comment"]').value = data.comment || '';
    form.querySelector('[name="activity_type"]').value = data.activity_type || '';
    if (blockerToggle) blockerToggle.checked = Boolean(Number(data.had_blocker || 0));
    form.querySelector('[name="blocker_description"]').value = data.blocker_description || '';
    if (deliverableToggle) deliverableToggle.checked = Boolean(Number(data.generated_deliverable || 0));
    if (progressToggle) progressToggle.checked = Boolean(Number(data.had_significant_progress || 0));
    syncToggles();
    document.getElementById('quick-add-box')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  /* ── Form submit ── */
  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (weekLocked) { showInfoToast('Semana enviada – registros bloqueados.'); return; }

    const dateVal = form.querySelector('[name="date"]')?.value || '';
    if (isWeekendDate(dateVal)) {
      showInfoToast('⚠️ Registro no permitido en fines de semana');
      return;
    }

    const formData = new FormData(form);
    const raw = Object.fromEntries(formData.entries());
    raw.had_blocker = blockerToggle?.checked ? '1' : '0';
    raw.generated_deliverable = deliverableToggle?.checked ? '1' : '0';
    raw.had_significant_progress = progressToggle?.checked ? '1' : '0';
    const activityId = Number(raw.activity_id || 0);
    const deliverableNote = String(raw.deliverable_note || '').trim();
    const operationalParts = [String(raw.comment || '').trim()].filter(Boolean);
    if (deliverableNote) operationalParts.push(`Entregable: ${deliverableNote}`);
    raw.operational_comment = operationalParts.join(' | ');

    const endpoint = activityId > 0 ? '/timesheets/activities/update' : '/timesheets/activities/create';
    try {
      const response = await post(endpoint, raw);
      const finalId = activityId > 0 ? activityId : Number(response.id || 0);
      if (lastSubmitMode === 'save_duplicate' && finalId > 0) {
        await post('/timesheets/activities/duplicate', { activity_id: String(finalId), target_date: nextDate(String(raw.date || '')) });
        window.location.href = `${basePath}/timesheets?week=${encodeURIComponent(weekValue)}`;
        return;
      }
      if (lastSubmitMode === 'save_another') { resetForAnother(); return; }
      window.location.href = `${basePath}/timesheets?week=${encodeURIComponent(weekValue)}`;
    } catch (err) {
      showInfoToast(err.message || 'No se pudo guardar.');
    }
  });

  /* ── Edit activity ── */
  document.querySelectorAll('.edit-activity').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (weekLocked) return;
      try { fillForm(JSON.parse(btn.dataset.payload || '{}')); }
      catch (e) { showInfoToast('No se pudo cargar la actividad para edición.'); }
    });
  });

  /* ── Delete activity (with modal) ── */
  document.querySelectorAll('.delete-activity').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (weekLocked) return;
      const activityId = Number(btn.dataset.activityId || 0);
      const desc = btn.dataset.desc || 'esta actividad';
      const confirmed = await showConfirmModal('¿Eliminar actividad?', `Se eliminará "<strong>${desc}</strong>" permanentemente. Esta acción no se puede deshacer.`);
      if (!confirmed) return;
      try {
        await post('/timesheets/activities/delete', { activity_id: String(activityId) });
        window.location.href = `${basePath}/timesheets?week=${encodeURIComponent(weekValue)}`;
      } catch (err) {
        showInfoToast(err.message || 'No se pudo eliminar.');
      }
    });
  });

  /* ── Duplicate day ── */
  document.getElementById('duplicate-day-trigger')?.addEventListener('click', async () => {
    if (weekLocked) return;
    const lines = Object.entries(dayLabels).map(([key, label]) => `${key} (${label})`).join('\n');
    const source = prompt(`Día origen:\n${lines}`);
    if (!source) return;
    const target = prompt(`Día destino:\n${lines}`);
    if (!target) return;
    try {
      await post('/timesheets/duplicate-day', { source_date: source, target_date: target });
      window.location.href = `${basePath}/timesheets?week=${encodeURIComponent(weekValue)}`;
    } catch (err) {
      showInfoToast(err.message || 'No se pudo duplicar el día.');
    }
  });

  /* ── Focus Quick Add ── */
  document.getElementById('focus-quick-add')?.addEventListener('click', () => {
    if (weekLocked) return;
    document.getElementById('quick-add-box')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  /* ── Recent fill ── */
  document.querySelectorAll('.recent-fill').forEach((btn) => {
    btn.addEventListener('click', () => {
      form.querySelector('[name="activity_id"]').value = '';
      form.querySelector('[name="project_id"]').value = btn.dataset.projectId || '';
      filterTasksByProject();
      form.querySelector('[name="task_id"]').value = btn.dataset.taskId || '0';
      form.querySelector('[name="activity_type"]').value = btn.dataset.activityType || '';
      form.querySelector('[name="activity_description"]').value = btn.dataset.activityDescription || '';
      form.querySelector('[name="activity_description"]').focus();
    });
  });

  /* ── Templates ── */
  const readTemplates = () => {
    try { const r = localStorage.getItem(templatesKey); const p = r ? JSON.parse(r) : []; return Array.isArray(p) ? p : []; }
    catch (e) { return []; }
  };
  const writeTemplates = (items) => localStorage.setItem(templatesKey, JSON.stringify(items.slice(0, 10)));
  const renderTemplates = () => {
    const list = document.getElementById('template-list');
    if (!list) return;
    const items = readTemplates();
    list.innerHTML = items.length === 0 ? '<small class="section-muted">Sin plantillas guardadas.</small>' : '';
    items.forEach((tpl, idx) => {
      const btn = document.createElement('button');
      btn.type = 'button'; btn.className = 'chip-btn';
      btn.textContent = `${tpl.project_label || 'Proyecto'} · ${tpl.activity_description || 'Actividad'}`;
      btn.addEventListener('click', () => {
        form.querySelector('[name="activity_id"]').value = '';
        form.querySelector('[name="project_id"]').value = String(tpl.project_id || '');
        filterTasksByProject();
        form.querySelector('[name="task_id"]').value = String(tpl.task_id || 0);
        form.querySelector('[name="activity_type"]').value = tpl.activity_type || '';
        form.querySelector('[name="activity_description"]').value = tpl.activity_description || '';
      });
      list.appendChild(btn);

      const rm = document.createElement('button');
      rm.type = 'button'; rm.className = 'chip-btn'; rm.textContent = '✕'; rm.title = 'Eliminar plantilla';
      rm.style.cssText = 'color:#ef4444;border-color:#ef4444;background:rgba(239,68,68,.08)';
      rm.addEventListener('click', () => { writeTemplates(readTemplates().filter((_, i) => i !== idx)); renderTemplates(); });
      list.appendChild(rm);
    });
  };

  document.getElementById('save-template')?.addEventListener('click', () => {
    if (weekLocked) return;
    const projectId = Number(form.querySelector('[name="project_id"]').value || 0);
    const desc = form.querySelector('[name="activity_description"]').value || '';
    if (!projectId || !desc.trim()) { showInfoToast('Completa proyecto y descripción para guardar plantilla.'); return; }
    writeTemplates([{
      project_id: projectId,
      project_label: projectInput?.selectedOptions?.[0]?.textContent || 'Proyecto',
      task_id: Number(form.querySelector('[name="task_id"]').value || 0),
      activity_type: form.querySelector('[name="activity_type"]').value || '',
      activity_description: desc,
    }, ...readTemplates()]);
    renderTemplates();
    showInfoToast('✓ Plantilla guardada');
  });

  /* ── Drag & drop ── */
  let draggingActivityId = null;
  document.querySelectorAll('.activity-chip[draggable="true"]').forEach((chip) => {
    chip.addEventListener('dragstart', (e) => {
      if (weekLocked) { e.preventDefault(); return; }
      draggingActivityId = Number(chip.dataset.activityId || 0);
      chip.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
    });
    chip.addEventListener('dragend', () => { chip.classList.remove('dragging'); draggingActivityId = null; });
  });

  document.querySelectorAll('[data-drop-day]').forEach((dayCard) => {
    const isWeekend = dayCard.dataset.isWeekend === '1';
    dayCard.addEventListener('dragover', (e) => {
      if (weekLocked || isWeekend) return;
      e.preventDefault();
      dayCard.classList.add('is-drop-target');
    });
    dayCard.addEventListener('dragleave', () => dayCard.classList.remove('is-drop-target'));
    dayCard.addEventListener('drop', async (e) => {
      e.preventDefault();
      dayCard.classList.remove('is-drop-target');
      if (weekLocked || !draggingActivityId) return;
      if (isWeekend) { showInfoToast('⚠️ Registro no permitido en fines de semana'); return; }
      try {
        await post('/timesheets/activities/move', { activity_id: String(draggingActivityId), target_date: dayCard.dataset.dropDay || '' });
        window.location.href = `${basePath}/timesheets?week=${encodeURIComponent(weekValue)}`;
      } catch (err) {
        showInfoToast(err.message || 'No se pudo mover la actividad.');
      }
    });
  });

  renderTemplates();
})();
</script>
