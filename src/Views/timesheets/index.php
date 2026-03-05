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
$dayTotals = is_array($weeklyGrid['day_totals'] ?? null) ? $weeklyGrid['day_totals'] : [];
$activitiesByDay = is_array($weeklyGrid['activities_by_day'] ?? null) ? $weeklyGrid['activities_by_day'] : [];
$weekTotal = (float) ($weeklyGrid['week_total'] ?? 0);
$weeklyCapacity = (float) ($weeklyGrid['weekly_capacity'] ?? 0);
$activityTypes = is_array($activityTypes ?? null) ? $activityTypes : (is_array($weeklyGrid['activity_types'] ?? null) ? $weeklyGrid['activity_types'] : []);
$tasksForTimesheet = is_array($tasksForTimesheet ?? null) ? $tasksForTimesheet : [];
$recentActivitySuggestions = is_array($recentActivitySuggestions ?? null) ? $recentActivitySuggestions : [];
$projectBreakdown = is_array($projectBreakdown ?? null) ? $projectBreakdown : [];
$selectedWeekSummary = is_array($selectedWeekSummary ?? null) ? $selectedWeekSummary : [];
$weekHistoryLog = is_array($weekHistoryLog ?? null) ? $weekHistoryLog : [];

$statusMap = [
    'approved' => ['label' => 'Aprobada', 'class' => 'approved'],
    'rejected' => ['label' => 'Rechazada', 'class' => 'rejected'],
    'submitted' => ['label' => 'Enviada', 'class' => 'submitted'],
    'draft' => ['label' => 'Borrador', 'class' => 'draft'],
    'partial' => ['label' => 'Parcial', 'class' => 'partial'],
];
$selectedStatus = (string) ($selectedWeekSummary['status'] ?? 'draft');
if (!isset($statusMap[$selectedStatus])) {
    $selectedStatus = 'draft';
}
$selectedMeta = $statusMap[$selectedStatus];

$complianceWeek = $weeklyCapacity > 0 ? min(100, round(($weekTotal / $weeklyCapacity) * 100, 1)) : 0;
$weekIsLocked = $selectedStatus === 'approved';
$weekCanWithdraw = in_array($selectedStatus, ['submitted', 'partial'], true);

$prevWeek = $weekStart->modify('-7 days');
$nextWeek = $weekStart->modify('+7 days');
$todayDate = (new DateTimeImmutable())->format('Y-m-d');

$topProject = !empty($projectBreakdown[0]['project']) ? (string) $projectBreakdown[0]['project'] : null;
$topProjectHours = !empty($projectBreakdown[0]['total_hours']) ? round((float) $projectBreakdown[0]['total_hours'], 1) : 0;
?>

<div class="ts-shell">

    <!-- Navigation tabs -->
    <nav class="ts-nav-tabs">
        <a href="<?= $basePath ?>/timesheets" class="ts-tab active">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Registro de horas
        </a>
        <a href="<?= $basePath ?>/approvals" class="ts-tab">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            Aprobación
        </a>
        <a href="<?= $basePath ?>/timesheets/analytics" class="ts-tab">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            Analítica
        </a>
    </nav>

    <!-- Week header -->
    <header class="ts-week-header card">
        <div class="ts-week-nav">
            <a href="<?= $basePath ?>/timesheets?week=<?= urlencode($prevWeek->format('o-\\WW')) ?>" class="ts-nav-arrow" title="Semana anterior">&#8249;</a>
            <div class="ts-week-info">
                <div class="ts-week-label">
                    <strong>Semana <?= (int) $weekStart->format('W') ?></strong>
                    <span><?= htmlspecialchars($weekStart->format('d M')) ?> – <?= htmlspecialchars($weekEnd->format('d M Y')) ?></span>
                </div>
                <div class="ts-week-badges">
                    <span class="ts-hours-badge"><?= round($weekTotal, 1) ?>h</span>
                    <span class="badge-state <?= htmlspecialchars($selectedMeta['class']) ?>"><?= htmlspecialchars($selectedMeta['label']) ?></span>
                    <small id="autosave-indicator" class="autosave-indicator"></small>
                </div>
            </div>
            <a href="<?= $basePath ?>/timesheets?week=<?= urlencode($nextWeek->format('o-\\WW')) ?>" class="ts-nav-arrow" title="Semana siguiente">&#8250;</a>
        </div>

        <div class="ts-week-kpis">
            <div class="ts-kpi">
                <span>Registradas</span>
                <strong id="week-total-value"><?= round($weekTotal, 1) ?>h</strong>
            </div>
            <div class="ts-kpi">
                <span>Capacidad</span>
                <strong><?= round($weeklyCapacity, 1) ?>h</strong>
            </div>
            <div class="ts-kpi ts-kpi--<?= $complianceWeek >= 80 ? 'good' : ($complianceWeek >= 50 ? 'warn' : 'low') ?>">
                <span>Cumplimiento</span>
                <strong><?= $complianceWeek ?>%</strong>
            </div>
            <?php if ($topProject): ?>
            <div class="ts-kpi">
                <span>Mayor consumo</span>
                <strong title="<?= htmlspecialchars($topProject) ?>"><?= htmlspecialchars(mb_strimwidth($topProject, 0, 20, '…')) ?> (<?= $topProjectHours ?>h)</strong>
            </div>
            <?php endif; ?>
        </div>

        <div class="ts-week-actions">
            <button type="button" class="ts-btn ts-btn--primary" id="quick-add-toggle" title="Añadir actividad (también visible en el panel derecho)">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Registrar actividad
            </button>
            <button type="button" class="ts-btn ts-btn--secondary" id="duplicate-day-btn" title="Duplicar todas las actividades de un día">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                Duplicar día
            </button>
            <?php if (!$weekIsLocked && $canReport): ?>
            <form method="POST" action="<?= $basePath ?>/timesheets/submit-week" class="inline-form">
                <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                <button type="submit" class="ts-btn ts-btn--submit">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    Enviar semana
                </button>
            </form>
            <?php endif; ?>
            <?php if ($weekCanWithdraw): ?>
            <form method="POST" action="<?= $basePath ?>/timesheets/cancel-week" class="inline-form">
                <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                <button type="submit" class="ts-btn ts-btn--secondary">Retirar envío</button>
            </form>
            <?php endif; ?>
            <?php if ($weekIsLocked && $canManageWorkflow): ?>
            <form method="POST" action="<?= $basePath ?>/timesheets/reopen-own-week" class="inline-form ts-reopen-form" id="reopen-form">
                <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                <input type="text" name="comment" placeholder="Motivo de reapertura" class="ts-input-sm" required>
                <button type="submit" class="ts-btn ts-btn--secondary">Solicitar reapertura</button>
            </form>
            <?php endif; ?>
            <!-- Week selector (hidden, activated by label) -->
            <label class="ts-btn ts-btn--ghost" title="Ir a semana específica">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <input type="week" id="week-picker" value="<?= htmlspecialchars($weekValue) ?>" style="position:absolute;opacity:0;width:0;height:0;">
            </label>
        </div>
    </header>

    <?php if ($canReport): ?>
    <!-- Main 2-column layout -->
    <div class="ts-main-layout">

        <!-- Left column: 7-day calendar -->
        <section class="ts-calendar-col">
            <div class="ts-calendar-grid" id="ts-calendar">
                <?php foreach ($gridDays as $day):
                    $dayDate = (string) ($day['key'] ?? '');
                    $dayItems = is_array($activitiesByDay[$dayDate] ?? null) ? $activitiesByDay[$dayDate] : [];
                    $totalDayHours = (float) ($dayTotals[$dayDate] ?? 0);
                    $isToday = $dayDate === $todayDate;
                ?>
                <div class="ts-day-card <?= $isToday ? 'ts-day-card--today' : '' ?>" data-date="<?= htmlspecialchars($dayDate) ?>">
                    <header class="ts-day-header">
                        <div class="ts-day-title">
                            <span class="ts-day-name"><?= htmlspecialchars((string) ($day['label'] ?? '')) ?></span>
                            <span class="ts-day-num <?= $isToday ? 'ts-today-dot' : '' ?>"><?= htmlspecialchars((string) ($day['number'] ?? '')) ?></span>
                        </div>
                        <span class="ts-day-total <?= $totalDayHours > 0 ? 'ts-day-total--has' : '' ?>" data-day-total="<?= htmlspecialchars($dayDate) ?>"><?= $totalDayHours > 0 ? round($totalDayHours, 1) . 'h' : '' ?></span>
                    </header>
                    <div class="ts-chips-list">
                        <?php foreach ($dayItems as $item):
                            $chipStatus = (string) ($item['status'] ?? 'draft');
                            $isDraft = $chipStatus === 'draft';
                            $hasBlocker = !empty($item['had_blocker']);
                            $hasDeliverable = !empty($item['generated_deliverable']);
                            $hasProgress = !empty($item['had_significant_progress']);
                            $desc = (string) ($item['activity_description'] ?? '');
                            $proj = (string) ($item['project'] ?? '');
                            $taskId = (int) ($item['task_id'] ?? 0);
                            $entryId = (int) ($item['id'] ?? 0);
                        ?>
                        <div class="ts-chip ts-chip--<?= htmlspecialchars($chipStatus) ?>"
                             data-id="<?= $entryId ?>"
                             data-date="<?= htmlspecialchars($dayDate) ?>"
                             data-project-id="<?= (int) ($item['project_id'] ?? 0) ?>"
                             data-task-id="<?= $taskId ?>"
                             data-hours="<?= (float) ($item['hours'] ?? 0) ?>"
                             data-desc="<?= htmlspecialchars($desc, ENT_QUOTES) ?>"
                             data-comment="<?= htmlspecialchars((string) ($item['comment'] ?? ''), ENT_QUOTES) ?>"
                             data-phase="<?= htmlspecialchars((string) ($item['phase_name'] ?? ''), ENT_QUOTES) ?>"
                             data-subphase="<?= htmlspecialchars((string) ($item['subphase_name'] ?? ''), ENT_QUOTES) ?>"
                             data-activity-type="<?= htmlspecialchars((string) ($item['activity_type'] ?? ''), ENT_QUOTES) ?>"
                             data-blocker="<?= $hasBlocker ? '1' : '0' ?>"
                             data-blocker-desc="<?= htmlspecialchars((string) ($item['blocker_description'] ?? ''), ENT_QUOTES) ?>"
                             data-progress="<?= $hasProgress ? '1' : '0' ?>"
                             data-deliverable="<?= $hasDeliverable ? '1' : '0' ?>"
                             data-op-comment="<?= htmlspecialchars((string) ($item['operational_comment'] ?? ''), ENT_QUOTES) ?>">
                            <div class="ts-chip-body">
                                <span class="ts-chip-project"><?= htmlspecialchars($proj) ?></span>
                                <span class="ts-chip-desc"><?= htmlspecialchars($desc ?: ((string) ($item['activity_type'] ?? ''))) ?></span>
                            </div>
                            <div class="ts-chip-meta">
                                <span class="ts-chip-hours"><?= round((float) ($item['hours'] ?? 0), 1) ?>h</span>
                                <div class="ts-chip-flags">
                                    <?php if ($hasBlocker): ?><span class="ts-flag ts-flag--blocker" title="Bloqueo registrado">⛔</span><?php endif; ?>
                                    <?php if ($hasDeliverable): ?><span class="ts-flag ts-flag--deliverable" title="Entregable generado">📦</span><?php endif; ?>
                                    <?php if ($hasProgress): ?><span class="ts-flag ts-flag--progress" title="Avance significativo">🚀</span><?php endif; ?>
                                </div>
                                <?php if ($isDraft): ?>
                                <div class="ts-chip-actions">
                                    <button type="button" class="ts-chip-btn ts-chip-edit"
                                            title="Editar actividad"
                                            data-id="<?= $entryId ?>">✏️</button>
                                    <form method="POST" action="<?= $basePath ?>/timesheets/delete-entry" class="inline-form ts-delete-form"
                                          data-confirm="¿Eliminar esta actividad?">
                                        <input type="hidden" name="timesheet_id" value="<?= $entryId ?>">
                                        <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                                        <button type="submit" class="ts-chip-btn ts-chip-delete" title="Eliminar actividad">🗑️</button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($isDraft ?? true): ?>
                    <button type="button" class="ts-add-to-day"
                            data-date="<?= htmlspecialchars($dayDate) ?>"
                            title="Añadir actividad a este día">
                        + actividad
                    </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($weekHistoryLog !== []): ?>
            <div class="ts-history-log card">
                <h4>Historial de semana</h4>
                <ul>
                    <?php foreach ($weekHistoryLog as $event): ?>
                        <li>
                            <strong><?= htmlspecialchars((string) ($event['action'] ?? 'acción')) ?></strong>
                            · <?= htmlspecialchars((string) ($event['actor_name'] ?? 'Sistema')) ?>
                            · <?= htmlspecialchars((string) ($event['created_at'] ?? '')) ?>
                            <?= !empty($event['action_comment']) ? ' · ' . htmlspecialchars((string) $event['action_comment']) : '' ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </section>

        <!-- Right column: Quick Add form -->
        <aside class="ts-quickadd-col" id="quick-add-panel">
            <div class="ts-quickadd-card card">
                <div class="ts-quickadd-header">
                    <h3 id="quickadd-title">Registrar actividad</h3>
                    <span class="ts-quickadd-date-hint" id="quickadd-date-hint"></span>
                </div>

                <!-- Recent suggestions -->
                <?php if ($recentActivitySuggestions !== []): ?>
                <div class="ts-recents">
                    <span class="ts-recents-label">Recientes</span>
                    <div class="ts-recents-list">
                        <?php foreach ($recentActivitySuggestions as $recent): ?>
                        <button type="button" class="ts-recent-chip recent-activity"
                            data-project-id="<?= (int) ($recent['project_id'] ?? 0) ?>"
                            data-task-id="<?= (int) ($recent['task_id'] ?? 0) ?>"
                            data-activity-type="<?= htmlspecialchars((string) ($recent['activity_type'] ?? ''), ENT_QUOTES) ?>"
                            data-activity-description="<?= htmlspecialchars((string) ($recent['activity_description'] ?? ''), ENT_QUOTES) ?>"
                            data-phase-name="<?= htmlspecialchars((string) ($recent['phase_name'] ?? ''), ENT_QUOTES) ?>"
                            data-subphase-name="<?= htmlspecialchars((string) ($recent['subphase_name'] ?? ''), ENT_QUOTES) ?>">
                            <strong><?= htmlspecialchars((string) ($recent['project'] ?? '')) ?></strong>
                            <?= htmlspecialchars((string) ($recent['activity_description'] ?? '')) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <form method="POST" action="<?= $basePath ?>/timesheets/activity" class="ts-quickadd-form" id="quick-activity-form">
                    <input type="hidden" name="sync_operational" value="1">
                    <input type="hidden" name="edit_id" id="qa-edit-id" value="">

                    <div class="ts-field ts-field--full">
                        <label for="qa-date">Fecha <span class="ts-req">*</span></label>
                        <input type="date" id="qa-date" name="date" value="<?= htmlspecialchars($todayDate) ?>" required class="ts-input">
                    </div>

                    <div class="ts-field">
                        <label for="qa-project">Proyecto <span class="ts-req">*</span></label>
                        <select id="qa-project" name="project_id" required class="ts-input">
                            <option value="">Seleccionar…</option>
                            <?php foreach ($projectsForTimesheet as $project): ?>
                                <option value="<?= (int) ($project['project_id'] ?? 0) ?>"><?= htmlspecialchars((string) ($project['project'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ts-field">
                        <label for="qa-task">Tarea <span class="ts-req">*</span></label>
                        <select id="qa-task" name="task_id" class="ts-input">
                            <option value="0">Registro general</option>
                            <?php foreach ($tasksForTimesheet as $task): ?>
                                <option value="<?= (int) ($task['task_id'] ?? 0) ?>" data-project-id="<?= (int) ($task['project_id'] ?? 0) ?>">
                                    <?= htmlspecialchars((string) ($task['task_title'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ts-field">
                        <label for="qa-hours">Horas <span class="ts-req">*</span></label>
                        <input type="number" id="qa-hours" name="hours" step="0.25" min="0.25" max="24" required placeholder="0.00" class="ts-input">
                    </div>

                    <div class="ts-field ts-field--full">
                        <label for="qa-desc">Descripción breve <span class="ts-req">*</span></label>
                        <input type="text" id="qa-desc" name="activity_description" maxlength="255" placeholder="¿Qué hiciste?" required class="ts-input">
                    </div>

                    <div class="ts-field ts-field--full">
                        <label for="qa-comment">Comentario <span class="ts-opt">(opcional)</span></label>
                        <input type="text" id="qa-comment" name="comment" placeholder="Contexto adicional" class="ts-input">
                    </div>

                    <div class="ts-field ts-field--half">
                        <label for="qa-phase">Fase <span class="ts-opt">(opcional)</span></label>
                        <input type="text" id="qa-phase" name="phase_name" maxlength="120" placeholder="Ej: Ejecución" class="ts-input">
                    </div>

                    <div class="ts-field ts-field--half">
                        <label for="qa-type">Tipo de actividad</label>
                        <select id="qa-type" name="activity_type" class="ts-input">
                            <option value="">Sin clasificar</option>
                            <?php foreach ($activityTypes as $type): ?>
                                <option value="<?= htmlspecialchars((string) $type) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $type))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Toggles -->
                    <div class="ts-toggles ts-field--full">
                        <label class="ts-toggle">
                            <input type="checkbox" name="had_blocker" value="1" id="qa-blocker">
                            <span class="ts-toggle-track"></span>
                            <span>⛔ Bloqueo</span>
                        </label>
                        <label class="ts-toggle">
                            <input type="checkbox" name="generated_deliverable" value="1" id="qa-deliverable">
                            <span class="ts-toggle-track"></span>
                            <span>📦 Entregable</span>
                        </label>
                        <label class="ts-toggle">
                            <input type="checkbox" name="had_significant_progress" value="1" id="qa-progress">
                            <span class="ts-toggle-track"></span>
                            <span>🚀 Avance significativo</span>
                        </label>
                    </div>

                    <div class="ts-field ts-field--full" id="qa-blocker-wrap" style="display:none">
                        <label for="qa-blocker-desc">Descripción del bloqueo</label>
                        <input type="text" id="qa-blocker-desc" name="blocker_description" maxlength="500" placeholder="Describe el impedimento" class="ts-input">
                    </div>

                    <div class="ts-field ts-field--full" id="qa-deliverable-wrap" style="display:none">
                        <label for="qa-deliverable-desc">Nombre del entregable</label>
                        <input type="text" id="qa-deliverable-desc" name="operational_comment" maxlength="500" placeholder="Nombre o descripción del entregable" class="ts-input">
                    </div>

                    <div class="ts-quickadd-submit ts-field--full">
                        <button type="submit" class="ts-btn ts-btn--primary ts-btn--full" id="qa-submit-btn">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                            Guardar actividad
                        </button>
                        <button type="submit" name="save_and_add" value="1" class="ts-btn ts-btn--secondary ts-btn--full" id="qa-save-add-btn">
                            + Guardar y agregar otra
                        </button>
                        <button type="button" class="ts-btn ts-btn--ghost ts-btn--full" id="qa-cancel-edit" style="display:none">
                            Cancelar edición
                        </button>
                    </div>
                </form>
            </div>
        </aside>
    </div>

    <!-- Duplicate day modal -->
    <dialog id="duplicate-day-modal" class="ts-modal">
        <div class="ts-modal-body">
            <h4>Duplicar actividades de un día</h4>
            <p class="ts-modal-hint">Copia todas las actividades en borrador de un día al otro.</p>
            <form method="POST" action="<?= $basePath ?>/timesheets/duplicate-day" class="ts-dup-form">
                <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                <div class="ts-field">
                    <label for="dup-source">Día origen</label>
                    <select id="dup-source" name="source_date" class="ts-input" required>
                        <?php foreach ($gridDays as $day): ?>
                            <option value="<?= htmlspecialchars((string) ($day['key'] ?? '')) ?>"><?= htmlspecialchars((string) ($day['label'] ?? '')) ?> <?= htmlspecialchars((string) ($day['number'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ts-field">
                    <label for="dup-target">Día destino</label>
                    <select id="dup-target" name="target_date" class="ts-input" required>
                        <?php foreach ($gridDays as $day): ?>
                            <option value="<?= htmlspecialchars((string) ($day['key'] ?? '')) ?>"><?= htmlspecialchars((string) ($day['label'] ?? '')) ?> <?= htmlspecialchars((string) ($day['number'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ts-modal-actions">
                    <button type="button" class="ts-btn ts-btn--secondary" id="dup-cancel">Cancelar</button>
                    <button type="submit" class="ts-btn ts-btn--primary">Duplicar</button>
                </div>
            </form>
        </div>
    </dialog>

    <?php endif; ?>
</div>

<style>
/* ===== TIMESHEET REDESIGN STYLES ===== */
.ts-shell{display:flex;flex-direction:column;gap:12px;max-width:1400px;margin:0 auto}

/* Nav tabs */
.ts-nav-tabs{display:flex;gap:0;border-bottom:2px solid var(--border);background:var(--surface);border-radius:10px 10px 0 0;overflow:hidden}
.ts-tab{display:flex;align-items:center;gap:6px;padding:10px 20px;text-decoration:none;color:var(--text-secondary);font-size:13px;font-weight:500;border-bottom:2px solid transparent;margin-bottom:-2px;transition:color .15s,border-color .15s;white-space:nowrap}
.ts-tab:hover{color:var(--primary)}
.ts-tab.active{color:var(--primary);border-bottom-color:var(--primary);font-weight:600;background:var(--surface)}
.ts-tab svg{opacity:.7}
.ts-tab.active svg{opacity:1}

/* Week header */
.ts-week-header{padding:14px 18px;display:flex;flex-direction:column;gap:10px}
.ts-week-nav{display:flex;align-items:center;gap:10px}
.ts-nav-arrow{display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;background:var(--surface);border:1px solid var(--border);text-decoration:none;color:var(--text-primary);font-size:20px;line-height:1;transition:background .15s}
.ts-nav-arrow:hover{background:var(--primary);color:#fff}
.ts-week-info{flex:1;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px}
.ts-week-label{display:flex;flex-direction:column;gap:2px}
.ts-week-label strong{font-size:1.05rem}
.ts-week-label span{font-size:12px;color:var(--text-secondary)}
.ts-week-badges{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.ts-hours-badge{background:var(--primary);color:#fff;padding:3px 12px;border-radius:999px;font-size:13px;font-weight:700}
.autosave-indicator{font-size:11px;color:var(--text-secondary)}
.autosave-indicator.saving{color:var(--info,#2563eb)}
.autosave-indicator.saved{color:var(--success,#16a34a)}
.autosave-indicator.error{color:var(--danger,#dc2626)}

/* Week KPIs */
.ts-week-kpis{display:flex;gap:10px;flex-wrap:wrap}
.ts-kpi{background:var(--background,#f8fafc);border:1px solid var(--border);border-radius:10px;padding:8px 14px;display:flex;flex-direction:column;gap:2px;min-width:120px}
.ts-kpi span{font-size:11px;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.04em}
.ts-kpi strong{font-size:1.1rem;font-weight:700}
.ts-kpi--good{border-left:3px solid var(--success,#16a34a)}
.ts-kpi--warn{border-left:3px solid var(--warning,#eab308)}
.ts-kpi--low{border-left:3px solid var(--danger,#dc2626)}

/* Action buttons */
.ts-week-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.ts-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;border:none;cursor:pointer;font-size:13px;font-weight:500;text-decoration:none;transition:background .15s,opacity .15s;white-space:nowrap}
.ts-btn--primary{background:var(--primary);color:#fff}
.ts-btn--primary:hover{opacity:.9}
.ts-btn--submit{background:var(--success,#16a34a);color:#fff}
.ts-btn--submit:hover{opacity:.9}
.ts-btn--secondary{background:var(--surface);color:var(--text-primary);border:1px solid var(--border)}
.ts-btn--secondary:hover{background:var(--border)}
.ts-btn--ghost{background:transparent;color:var(--text-secondary);border:1px solid var(--border)}
.ts-btn--ghost:hover{background:var(--surface)}
.ts-btn--full{width:100%;justify-content:center}
.ts-reopen-form{display:flex;gap:6px;align-items:center}
.ts-input-sm{padding:5px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px}

/* Main layout */
.ts-main-layout{display:grid;grid-template-columns:1fr 320px;gap:14px;align-items:start}

/* Calendar column */
.ts-calendar-col{display:flex;flex-direction:column;gap:12px}
.ts-calendar-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:8px}
.ts-day-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:10px;display:flex;flex-direction:column;gap:6px;min-height:140px;transition:box-shadow .15s}
.ts-day-card--today{border-color:var(--primary);box-shadow:0 0 0 2px color-mix(in srgb,var(--primary) 20%,transparent)}
.ts-day-header{display:flex;justify-content:space-between;align-items:flex-start;gap:4px}
.ts-day-title{display:flex;flex-direction:column;gap:1px}
.ts-day-name{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--text-secondary);font-weight:600}
.ts-day-num{font-size:1.3rem;font-weight:700;line-height:1.1}
.ts-today-dot{color:var(--primary)}
.ts-day-total{font-size:11px;font-weight:600;color:var(--text-secondary);background:var(--background,#f1f5f9);padding:2px 7px;border-radius:999px}
.ts-day-total--has{background:var(--primary);color:#fff}
.ts-chips-list{display:flex;flex-direction:column;gap:5px;flex:1}
.ts-add-to-day{border:1px dashed var(--border);background:transparent;color:var(--text-secondary);border-radius:7px;padding:4px 8px;font-size:11px;cursor:pointer;text-align:left;transition:border-color .15s,color .15s;margin-top:auto}
.ts-add-to-day:hover{border-color:var(--primary);color:var(--primary)}

/* Activity chips */
.ts-chip{background:#fff;border:1px solid var(--border);border-radius:9px;padding:6px 8px;font-size:12px;display:flex;justify-content:space-between;align-items:flex-start;gap:6px;transition:box-shadow .15s}
.ts-chip:hover{box-shadow:0 2px 8px rgba(0,0,0,.08)}
.ts-chip--approved{border-left:3px solid var(--success,#16a34a);background:#f0fdf4}
.ts-chip--rejected{border-left:3px solid var(--danger,#dc2626);background:#fff1f2}
.ts-chip--submitted{border-left:3px solid var(--warning,#eab308);background:#fffbeb}
.ts-chip--draft{border-left:3px solid var(--border)}
.ts-chip-body{display:flex;flex-direction:column;gap:2px;flex:1;min-width:0}
.ts-chip-project{font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ts-chip-desc{color:var(--text-secondary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ts-chip-meta{display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0}
.ts-chip-hours{font-weight:700;font-size:13px;white-space:nowrap}
.ts-chip-flags{display:flex;gap:2px;font-size:11px}
.ts-chip-actions{display:flex;gap:3px;opacity:0;transition:opacity .15s}
.ts-chip:hover .ts-chip-actions{opacity:1}
.ts-chip-btn{background:none;border:none;cursor:pointer;font-size:12px;padding:2px 4px;border-radius:5px;transition:background .1s}
.ts-chip-btn:hover{background:var(--border)}

/* Quick Add panel */
.ts-quickadd-col{position:sticky;top:16px}
.ts-quickadd-card{padding:16px;display:flex;flex-direction:column;gap:12px}
.ts-quickadd-header{display:flex;justify-content:space-between;align-items:center}
.ts-quickadd-header h3{margin:0;font-size:1rem}
.ts-quickadd-date-hint{font-size:11px;color:var(--primary);font-weight:600}

/* Recent suggestions */
.ts-recents{display:flex;flex-direction:column;gap:5px}
.ts-recents-label{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--text-secondary);font-weight:600}
.ts-recents-list{display:flex;flex-wrap:wrap;gap:5px}
.ts-recent-chip{background:var(--background,#f8fafc);border:1px solid var(--border);border-radius:999px;padding:3px 10px;font-size:11px;cursor:pointer;transition:border-color .15s,background .15s;text-align:left;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ts-recent-chip:hover{border-color:var(--primary);background:color-mix(in srgb,var(--primary) 8%,transparent)}
.ts-recent-chip strong{color:var(--text-primary);margin-right:3px}

/* Quick Add form */
.ts-quickadd-form{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.ts-field{display:flex;flex-direction:column;gap:4px;font-size:13px}
.ts-field label{font-weight:500;color:var(--text-primary)}
.ts-field--full{grid-column:1/-1}
.ts-field--half{grid-column:span 1}
.ts-req{color:var(--danger,#dc2626);font-size:10px}
.ts-opt{color:var(--text-secondary);font-size:10px;font-weight:400}
.ts-input{padding:7px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--surface);color:var(--text-primary);transition:border-color .15s;width:100%;box-sizing:border-box}
.ts-input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 2px color-mix(in srgb,var(--primary) 15%,transparent)}

/* Toggles */
.ts-toggles{display:flex;flex-direction:column;gap:7px;padding:10px 0;border-top:1px solid var(--border);border-bottom:1px solid var(--border)}
.ts-toggle{display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px}
.ts-toggle input{position:absolute;opacity:0;width:0;height:0}
.ts-toggle-track{width:34px;height:18px;border-radius:999px;background:var(--border);transition:background .2s;flex-shrink:0;position:relative}
.ts-toggle input:checked ~ .ts-toggle-track{background:var(--primary)}
.ts-toggle-track::after{content:'';position:absolute;top:2px;left:2px;width:14px;height:14px;background:#fff;border-radius:50%;transition:left .2s}
.ts-toggle input:checked ~ .ts-toggle-track::after{left:18px}
.ts-quickadd-submit{display:flex;flex-direction:column;gap:6px;padding-top:4px}

/* History log */
.ts-history-log{padding:12px 16px}
.ts-history-log h4{margin:0 0 8px;font-size:13px;color:var(--text-secondary)}
.ts-history-log ul{margin:0;padding-left:16px;display:flex;flex-direction:column;gap:5px;font-size:12px}

/* Modal */
.ts-modal{border:none;border-radius:14px;max-width:380px;width:95%;padding:0;box-shadow:0 8px 32px rgba(0,0,0,.18)}
.ts-modal::backdrop{background:rgba(15,23,42,.45)}
.ts-modal-body{padding:20px;display:flex;flex-direction:column;gap:12px}
.ts-modal-body h4{margin:0;font-size:1rem}
.ts-modal-hint{font-size:12px;color:var(--text-secondary);margin:0}
.ts-dup-form{display:flex;flex-direction:column;gap:10px}
.ts-modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:4px}

/* Badge state */
.badge-state{font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px}
.badge-state.approved{background:#dcfce7;color:#166534}
.badge-state.rejected{background:#fee2e2;color:#991b1b}
.badge-state.submitted{background:#fef3c7;color:#92400e}
.badge-state.draft{background:#e5e7eb;color:#374151}
.badge-state.partial{background:#dbeafe;color:#1e40af}
.inline-form{display:inline}

/* Responsive */
@media(max-width:1200px){
  .ts-calendar-grid{grid-template-columns:repeat(4,1fr)}
}
@media(max-width:900px){
  .ts-main-layout{grid-template-columns:1fr}
  .ts-quickadd-col{position:static}
  .ts-calendar-grid{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:600px){
  .ts-calendar-grid{grid-template-columns:1fr}
  .ts-week-kpis{gap:6px}
}
</style>

<script>
(() => {
const basePath = <?= json_encode($basePath) ?>;

// Week picker redirect
const weekPicker = document.getElementById('week-picker');
weekPicker?.addEventListener('change', () => {
  if (weekPicker.value) {
    window.location.href = basePath + '/timesheets?week=' + encodeURIComponent(weekPicker.value);
  }
});

// Quick add toggle (mobile helper)
const quickAddToggle = document.getElementById('quick-add-toggle');
const quickAddPanel = document.getElementById('quick-add-panel');
quickAddToggle?.addEventListener('click', () => {
  quickAddPanel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  document.getElementById('qa-desc')?.focus();
});

// Add-to-day buttons: set date and focus form
document.querySelectorAll('.ts-add-to-day').forEach(btn => {
  btn.addEventListener('click', () => {
    const date = btn.dataset.date;
    const dateInput = document.getElementById('qa-date');
    if (dateInput && date) {
      dateInput.value = date;
      updateDateHint(date);
    }
    quickAddPanel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    document.getElementById('qa-desc')?.focus();
  });
});

function updateDateHint(dateStr) {
  const hint = document.getElementById('quickadd-date-hint');
  if (!hint || !dateStr) return;
  try {
    const d = new Date(dateStr + 'T12:00:00');
    hint.textContent = d.toLocaleDateString('es', { weekday: 'long', day: 'numeric', month: 'short' });
  } catch(e) { hint.textContent = ''; }
}
updateDateHint(document.getElementById('qa-date')?.value || '');
document.getElementById('qa-date')?.addEventListener('change', e => updateDateHint(e.target.value));

// Project → task filter
const qaProject = document.getElementById('qa-project');
const qaTask = document.getElementById('qa-task');

function filterTasksByProject() {
  if (!qaTask) return;
  const selectedProject = Number(qaProject?.value || 0);
  qaTask.querySelectorAll('option[data-project-id]').forEach(opt => {
    const visible = selectedProject <= 0 || Number(opt.dataset.projectId || 0) === selectedProject;
    opt.hidden = !visible;
  });
  if (qaTask.selectedOptions[0]?.hidden) qaTask.value = '0';
}
qaProject?.addEventListener('change', filterTasksByProject);
filterTasksByProject();

// Toggles → conditional fields
const blockerToggle = document.getElementById('qa-blocker');
const blockerWrap = document.getElementById('qa-blocker-wrap');
const deliverableToggle = document.getElementById('qa-deliverable');
const deliverableWrap = document.getElementById('qa-deliverable-wrap');

blockerToggle?.addEventListener('change', () => {
  if (blockerWrap) blockerWrap.style.display = blockerToggle.checked ? '' : 'none';
});
deliverableToggle?.addEventListener('change', () => {
  if (deliverableWrap) deliverableWrap.style.display = deliverableToggle.checked ? '' : 'none';
});

// Recent activities autocomplete
document.querySelectorAll('.recent-activity').forEach(btn => {
  btn.addEventListener('click', () => {
    if (qaProject) qaProject.value = btn.dataset.projectId || '';
    filterTasksByProject();
    if (qaTask) qaTask.value = btn.dataset.taskId || '0';
    const typeEl = document.getElementById('qa-type');
    if (typeEl) typeEl.value = btn.dataset.activityType || '';
    const descEl = document.getElementById('qa-desc');
    if (descEl) descEl.value = btn.dataset.activityDescription || '';
    const phaseEl = document.getElementById('qa-phase');
    if (phaseEl) phaseEl.value = btn.dataset.phaseName || '';
    descEl?.focus();
  });
});

// Edit chip → populate form
const qaForm = document.getElementById('quick-activity-form');
const qaEditId = document.getElementById('qa-edit-id');
const qaTitle = document.getElementById('quickadd-title');
const qaCancelEdit = document.getElementById('qa-cancel-edit');
const qaSubmitBtn = document.getElementById('qa-submit-btn');

function resetForm() {
  qaForm?.reset();
  if (qaEditId) qaEditId.value = '';
  qaForm?.setAttribute('action', basePath + '/timesheets/activity');
  if (qaTitle) qaTitle.textContent = 'Registrar actividad';
  if (qaCancelEdit) qaCancelEdit.style.display = 'none';
  if (qaSubmitBtn) qaSubmitBtn.innerHTML = '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Guardar actividad';
  if (blockerWrap) blockerWrap.style.display = 'none';
  if (deliverableWrap) deliverableWrap.style.display = 'none';
  filterTasksByProject();
  updateDateHint(document.getElementById('qa-date')?.value || '');
}

qaCancelEdit?.addEventListener('click', resetForm);

document.querySelectorAll('.ts-chip-edit').forEach(btn => {
  btn.addEventListener('click', () => {
    const chip = btn.closest('.ts-chip');
    if (!chip) return;

    qaForm?.setAttribute('action', basePath + '/timesheets/update-activity');
    if (qaEditId) qaEditId.value = chip.dataset.id || '';
    if (qaTitle) qaTitle.textContent = 'Editar actividad';
    if (qaCancelEdit) qaCancelEdit.style.display = '';
    if (qaSubmitBtn) qaSubmitBtn.innerHTML = '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Actualizar actividad';

    const dateEl = document.getElementById('qa-date');
    if (dateEl) { dateEl.value = chip.dataset.date || ''; updateDateHint(dateEl.value); }
    if (qaProject) { qaProject.value = chip.dataset.projectId || ''; filterTasksByProject(); }
    if (qaTask) qaTask.value = chip.dataset.taskId || '0';
    const descEl = document.getElementById('qa-desc');
    if (descEl) descEl.value = chip.dataset.desc || '';
    const hoursEl = document.getElementById('qa-hours');
    if (hoursEl) hoursEl.value = chip.dataset.hours || '';
    const commentEl = document.getElementById('qa-comment');
    if (commentEl) commentEl.value = chip.dataset.comment || '';
    const phaseEl = document.getElementById('qa-phase');
    if (phaseEl) phaseEl.value = chip.dataset.phase || '';
    const typeEl = document.getElementById('qa-type');
    if (typeEl) typeEl.value = chip.dataset.activityType || '';

    if (blockerToggle) {
      blockerToggle.checked = chip.dataset.blocker === '1';
      if (blockerWrap) blockerWrap.style.display = blockerToggle.checked ? '' : 'none';
    }
    if (deliverableToggle) {
      deliverableToggle.checked = chip.dataset.deliverable === '1';
      if (deliverableWrap) deliverableWrap.style.display = deliverableToggle.checked ? '' : 'none';
    }
    const progressToggle = document.getElementById('qa-progress');
    if (progressToggle) progressToggle.checked = chip.dataset.progress === '1';

    const blockerDescEl = document.getElementById('qa-blocker-desc');
    if (blockerDescEl) blockerDescEl.value = chip.dataset.blockerDesc || '';
    const deliverableDescEl = document.getElementById('qa-deliverable-desc');
    if (deliverableDescEl) deliverableDescEl.value = chip.dataset.opComment || '';

    quickAddPanel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    document.getElementById('qa-desc')?.focus();
  });
});

// Delete form confirmation
document.querySelectorAll('.ts-delete-form').forEach(form => {
  form.addEventListener('submit', e => {
    const msg = form.dataset.confirm || '¿Eliminar esta actividad?';
    if (!confirm(msg)) e.preventDefault();
  });
});

// Duplicate day modal
const dupModal = document.getElementById('duplicate-day-modal');
const dupBtn = document.getElementById('duplicate-day-btn');
const dupCancel = document.getElementById('dup-cancel');

dupBtn?.addEventListener('click', () => dupModal?.showModal());
dupCancel?.addEventListener('click', () => dupModal?.close());

})();
</script>
