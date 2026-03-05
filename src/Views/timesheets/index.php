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
$activitiesByDay = is_array($weeklyGrid['activities_by_day'] ?? null) ? $weeklyGrid['activities_by_day'] : [];
$weekTotal = (float) ($weeklyGrid['week_total'] ?? 0);
$weeklyCapacity = (float) ($weeklyGrid['weekly_capacity'] ?? 0);
$activityTypes = is_array($activityTypes ?? null) ? $activityTypes : (is_array($weeklyGrid['activity_types'] ?? null) ? $weeklyGrid['activity_types'] : []);
$tasksForTimesheet = is_array($tasksForTimesheet ?? null) ? $tasksForTimesheet : [];
$recentActivitySuggestions = is_array($recentActivitySuggestions ?? null) ? $recentActivitySuggestions : [];
$projectBreakdown = is_array($projectBreakdown ?? null) ? $projectBreakdown : [];
$selectedWeekSummary = is_array($selectedWeekSummary ?? null) ? $selectedWeekSummary : [];
$weekHistoryLog = is_array($weekHistoryLog ?? null) ? $weekHistoryLog : [];
$userTemplates = is_array($userTemplates ?? null) ? $userTemplates : [];

$statusMap = [
    'approved' => ['label' => 'Aprobada', 'class' => 'ts-approved', 'icon' => '✓'],
    'rejected' => ['label' => 'Rechazada', 'class' => 'ts-rejected', 'icon' => '✗'],
    'submitted' => ['label' => 'Enviada', 'class' => 'ts-submitted', 'icon' => '◷'],
    'draft' => ['label' => 'Borrador', 'class' => 'ts-draft', 'icon' => '✎'],
    'partial' => ['label' => 'Parcial', 'class' => 'ts-partial', 'icon' => '◑'],
];
$selectedStatus = (string) ($selectedWeekSummary['status'] ?? 'draft');
if (!isset($statusMap[$selectedStatus])) {
    $selectedStatus = 'draft';
}
$selectedMeta = $statusMap[$selectedStatus];
$weekIsLocked = $selectedStatus === 'approved';
$weekCanWithdraw = in_array($selectedStatus, ['submitted', 'partial'], true);
$complianceWeek = $weeklyCapacity > 0 ? min(100, round(($weekTotal / $weeklyCapacity) * 100, 2)) : 0;
$topProject = !empty($projectBreakdown) ? $projectBreakdown[0] : null;

$todayStr = (new DateTimeImmutable())->format('Y-m-d');
$prevWeek = $weekStart->modify('-7 days')->format('o-\\WW');
$nextWeek = $weekStart->modify('+7 days')->format('o-\\WW');

$projectsForTimesheet = is_array($projectsForTimesheet ?? null) ? $projectsForTimesheet : [];
?>

<section class="ts-module">
    <nav class="ts-view-tabs">
        <a href="<?= $basePath ?>/timesheets?week=<?= urlencode($weekValue) ?>" class="ts-tab active">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            Registro de horas
        </a>
        <?php if ($canApprove): ?>
        <a href="<?= $basePath ?>/timesheets/approval?week=<?= urlencode($weekValue) ?>" class="ts-tab">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3 5 6v5c0 5.3 3.4 8.8 7 10 3.6-1.2 7-4.7 7-10V6z"/><path d="m9 12 2 2 4-4"/></svg>
            Aprobación
        </a>
        <?php endif; ?>
        <a href="<?= $basePath ?>/timesheets/analytics?week=<?= urlencode($weekValue) ?>" class="ts-tab">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h16"/><rect x="6" y="12" width="3" height="6" rx="1"/><rect x="11" y="8" width="3" height="10" rx="1"/><rect x="16" y="5" width="3" height="13" rx="1"/></svg>
            Analítica
        </a>
    </nav>

    <header class="ts-header">
        <div class="ts-header-left">
            <div class="ts-week-nav">
                <a href="<?= $basePath ?>/timesheets?week=<?= urlencode($prevWeek) ?>" class="ts-nav-btn" title="Semana anterior">‹</a>
                <form method="GET" class="ts-week-form">
                    <input type="week" name="week" value="<?= htmlspecialchars($weekValue) ?>" class="ts-week-input">
                </form>
                <a href="<?= $basePath ?>/timesheets?week=<?= urlencode($nextWeek) ?>" class="ts-nav-btn" title="Semana siguiente">›</a>
            </div>
            <span class="ts-week-range"><?= htmlspecialchars($weekStart->format('d M')) ?> – <?= htmlspecialchars($weekEnd->format('d M, Y')) ?></span>
        </div>
        <div class="ts-header-center">
            <div class="ts-total-badge">
                <strong><?= round($weekTotal, 1) ?>h</strong>
                <small>de <?= round($weeklyCapacity, 0) ?>h</small>
            </div>
            <span class="ts-status-badge <?= $selectedMeta['class'] ?>"><?= $selectedMeta['icon'] ?> <?= htmlspecialchars($selectedMeta['label']) ?></span>
        </div>
        <div class="ts-header-right">
            <?php if ($canReport && !$weekIsLocked): ?>
            <button type="button" class="ts-btn ts-btn-ghost" onclick="document.getElementById('ts-quick-add-project')?.focus()" title="Registrar actividad">+ Actividad</button>
            <button type="button" class="ts-btn ts-btn-ghost" id="ts-duplicate-day-btn" title="Duplicar día">Duplicar día</button>
            <?php endif; ?>
            <?php if ($canReport): ?>
            <form method="POST" action="<?= $basePath ?>/timesheets/submit-week" class="ts-inline">
                <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                <button type="submit" class="ts-btn ts-btn-primary" <?= $weekIsLocked ? 'disabled' : '' ?>>Enviar semana</button>
            </form>
            <?php endif; ?>
            <?php if ($weekCanWithdraw): ?>
            <form method="POST" action="<?= $basePath ?>/timesheets/cancel-week" class="ts-inline">
                <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                <button type="submit" class="ts-btn ts-btn-outline">Retirar envío</button>
            </form>
            <?php endif; ?>
            <?php if ($weekIsLocked && $canManageWorkflow): ?>
            <form method="POST" action="<?= $basePath ?>/timesheets/reopen-own-week" class="ts-inline">
                <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                <input type="text" name="comment" placeholder="Motivo" required class="ts-reopen-input">
                <button type="submit" class="ts-btn ts-btn-outline">Reabrir</button>
            </form>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($canReport): ?>
    <div class="ts-indicators">
        <div class="ts-indicator">
            <div class="ts-indicator-value"><?= round($weekTotal, 1) ?>h</div>
            <div class="ts-indicator-label">Registradas</div>
        </div>
        <div class="ts-indicator">
            <div class="ts-indicator-value"><?= round($weeklyCapacity, 0) ?>h</div>
            <div class="ts-indicator-label">Capacidad</div>
        </div>
        <div class="ts-indicator">
            <div class="ts-indicator-value <?= $complianceWeek >= 80 ? 'ts-good' : ($complianceWeek >= 50 ? 'ts-warn' : 'ts-low') ?>"><?= round($complianceWeek, 0) ?>%</div>
            <div class="ts-indicator-label">Cumplimiento</div>
        </div>
        <div class="ts-indicator">
            <div class="ts-indicator-value ts-indicator-project"><?= htmlspecialchars($topProject ? (string) ($topProject['project'] ?? '—') : '—') ?></div>
            <div class="ts-indicator-label">Mayor consumo <?= $topProject ? '(' . round((float) ($topProject['total_hours'] ?? 0), 1) . 'h)' : '' ?></div>
        </div>
        <div class="ts-indicator">
            <small class="ts-autosave-msg" id="ts-autosave">Sin cambios pendientes</small>
        </div>
    </div>

    <div class="ts-main-layout">
        <div class="ts-calendar-column">
            <div class="ts-calendar-grid">
                <?php foreach ($gridDays as $day):
                    $dayDate = (string) ($day['key'] ?? '');
                    $dayItems = is_array($activitiesByDay[$dayDate] ?? null) ? $activitiesByDay[$dayDate] : [];
                    $totalDayHours = (float) ($dayTotals[$dayDate] ?? 0);
                    $isToday = $dayDate === $todayStr;
                    $isWeekend = in_array($day['label'], ['Sáb', 'Dom'], true);
                ?>
                <div class="ts-day-card <?= $isToday ? 'ts-today' : '' ?> <?= $isWeekend ? 'ts-weekend' : '' ?>" data-date="<?= htmlspecialchars($dayDate) ?>">
                    <div class="ts-day-header">
                        <div class="ts-day-name">
                            <strong><?= htmlspecialchars($day['label']) ?></strong>
                            <span class="ts-day-number"><?= htmlspecialchars($day['number']) ?></span>
                            <?php if ($isToday): ?><span class="ts-today-dot"></span><?php endif; ?>
                        </div>
                        <span class="ts-day-total" data-day-total="<?= htmlspecialchars($dayDate) ?>"><?= round($totalDayHours, 1) ?>h</span>
                    </div>
                    <div class="ts-day-activities" data-day="<?= htmlspecialchars($dayDate) ?>">
                        <?php if (empty($dayItems)): ?>
                            <div class="ts-empty-day">Sin actividad</div>
                        <?php else: ?>
                            <?php foreach ($dayItems as $item):
                                $chipStatus = (string) ($item['status'] ?? 'draft');
                                $canEdit = $chipStatus === 'draft';
                            ?>
                            <div class="ts-activity-chip <?= $canEdit ? '' : 'ts-chip-locked' ?>"
                                 data-entry-id="<?= (int) ($item['id'] ?? 0) ?>"
                                 data-project-id="<?= (int) ($item['project_id'] ?? 0) ?>"
                                 data-task-id="<?= (int) ($item['task_id'] ?? 0) ?>"
                                 data-hours="<?= (float) ($item['hours'] ?? 0) ?>"
                                 data-date="<?= htmlspecialchars($dayDate) ?>"
                                 data-description="<?= htmlspecialchars((string) ($item['activity_description'] ?? ''), ENT_QUOTES) ?>"
                                 data-comment="<?= htmlspecialchars((string) ($item['comment'] ?? ''), ENT_QUOTES) ?>"
                                 data-phase="<?= htmlspecialchars((string) ($item['phase_name'] ?? ''), ENT_QUOTES) ?>"
                                 data-subphase="<?= htmlspecialchars((string) ($item['subphase_name'] ?? ''), ENT_QUOTES) ?>"
                                 data-activity-type="<?= htmlspecialchars((string) ($item['activity_type'] ?? ''), ENT_QUOTES) ?>"
                                 data-blocker="<?= !empty($item['had_blocker']) ? '1' : '0' ?>"
                                 data-blocker-desc="<?= htmlspecialchars((string) ($item['blocker_description'] ?? ''), ENT_QUOTES) ?>"
                                 data-progress="<?= !empty($item['had_significant_progress']) ? '1' : '0' ?>"
                                 data-deliverable="<?= !empty($item['generated_deliverable']) ? '1' : '0' ?>"
                                 data-op-comment="<?= htmlspecialchars((string) ($item['operational_comment'] ?? ''), ENT_QUOTES) ?>"
                                 draggable="<?= $canEdit ? 'true' : 'false' ?>">
                                <div class="ts-chip-main">
                                    <span class="ts-chip-project"><?= htmlspecialchars((string) ($item['project'] ?? 'Proyecto')) ?></span>
                                    <span class="ts-chip-hours"><?= round((float) ($item['hours'] ?? 0), 1) ?>h</span>
                                </div>
                                <div class="ts-chip-desc"><?= htmlspecialchars((string) (($item['activity_description'] ?? '') !== '' ? $item['activity_description'] : ($item['activity_type'] ?? ''))) ?></div>
                                <div class="ts-chip-flags">
                                    <?php if (!empty($item['had_blocker'])): ?><span class="ts-flag ts-flag-blocker" title="Bloqueo">⚠</span><?php endif; ?>
                                    <?php if (!empty($item['generated_deliverable'])): ?><span class="ts-flag ts-flag-deliverable" title="Entregable">📦</span><?php endif; ?>
                                    <?php if (!empty($item['had_significant_progress'])): ?><span class="ts-flag ts-flag-progress" title="Avance significativo">🚀</span><?php endif; ?>
                                    <?php if (!empty($item['comment'])): ?><span class="ts-flag ts-flag-comment" title="<?= htmlspecialchars((string) ($item['comment'] ?? '')) ?>">💬</span><?php endif; ?>
                                </div>
                                <?php if ($canEdit): ?>
                                <div class="ts-chip-actions">
                                    <button type="button" class="ts-chip-btn ts-edit-btn" title="Editar">✏️</button>
                                    <button type="button" class="ts-chip-btn ts-dup-btn" title="Duplicar a otro día">📋</button>
                                    <button type="button" class="ts-chip-btn ts-del-btn" title="Eliminar">🗑️</button>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!$weekIsLocked): ?>
                    <button type="button" class="ts-add-to-day" data-target-date="<?= htmlspecialchars($dayDate) ?>" title="Agregar actividad a este día">+</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($weekHistoryLog)): ?>
            <details class="ts-history-panel">
                <summary>Historial de acciones (<?= count($weekHistoryLog) ?>)</summary>
                <ul class="ts-history-list">
                    <?php foreach ($weekHistoryLog as $event): ?>
                    <li>
                        <strong><?= htmlspecialchars((string) ($event['action'] ?? '')) ?></strong>
                        <span><?= htmlspecialchars((string) ($event['actor_name'] ?? 'Sistema')) ?></span>
                        <time><?= htmlspecialchars((string) ($event['created_at'] ?? '')) ?></time>
                        <?php if (!empty($event['action_comment'])): ?>
                            <em><?= htmlspecialchars((string) $event['action_comment']) ?></em>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endif; ?>
        </div>

        <div class="ts-quickadd-column">
            <div class="ts-quickadd-card" id="ts-quickadd">
                <h3 class="ts-quickadd-title">Registro rápido</h3>
                <form id="ts-quick-form" class="ts-quickadd-form">
                    <input type="hidden" name="sync_operational" value="1">

                    <div class="ts-field">
                        <label for="ts-quick-add-project">Proyecto *</label>
                        <select name="project_id" id="ts-quick-add-project" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($projectsForTimesheet as $project): ?>
                            <option value="<?= (int) ($project['project_id'] ?? 0) ?>"><?= htmlspecialchars((string) ($project['project'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ts-field">
                        <label for="ts-quick-add-task">Tarea *</label>
                        <select name="task_id" id="ts-quick-add-task" required>
                            <option value="0">Registro general</option>
                            <?php foreach ($tasksForTimesheet as $task): ?>
                            <option value="<?= (int) ($task['task_id'] ?? 0) ?>" data-project-id="<?= (int) ($task['project_id'] ?? 0) ?>">
                                <?= htmlspecialchars((string) ($task['task_title'] ?? '')) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ts-field-row">
                        <div class="ts-field ts-field-half">
                            <label for="ts-quick-add-date">Fecha *</label>
                            <input type="date" name="date" id="ts-quick-add-date"
                                   value="<?= htmlspecialchars($todayStr >= $weekStart->format('Y-m-d') && $todayStr <= $weekEnd->format('Y-m-d') ? $todayStr : $weekStart->format('Y-m-d')) ?>"
                                   min="<?= htmlspecialchars($weekStart->format('Y-m-d')) ?>"
                                   max="<?= htmlspecialchars($weekEnd->format('Y-m-d')) ?>" required>
                        </div>
                        <div class="ts-field ts-field-half">
                            <label for="ts-quick-add-hours">Horas *</label>
                            <input type="number" name="hours" id="ts-quick-add-hours" step="0.25" min="0.25" max="24" placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="ts-field">
                        <label for="ts-quick-add-desc">Descripción *</label>
                        <input type="text" name="activity_description" id="ts-quick-add-desc" maxlength="255" placeholder="¿Qué se hizo?" required>
                    </div>

                    <div class="ts-field">
                        <label for="ts-quick-add-comment">Comentario</label>
                        <input type="text" name="comment" id="ts-quick-add-comment" placeholder="Contexto breve (opcional)">
                    </div>

                    <div class="ts-field">
                        <label for="ts-quick-add-phase">Fase</label>
                        <input type="text" name="phase_name" id="ts-quick-add-phase" maxlength="120" placeholder="Ej: Ejecución (opcional)">
                    </div>

                    <div class="ts-field">
                        <label for="ts-quick-add-activity-type">Tipo de actividad</label>
                        <select name="activity_type" id="ts-quick-add-activity-type">
                            <option value="">Sin clasificar</option>
                            <?php foreach ($activityTypes as $type): ?>
                            <option value="<?= htmlspecialchars((string) $type) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $type))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ts-toggles">
                        <label class="ts-toggle-item">
                            <input type="checkbox" name="had_blocker" value="1" id="ts-toggle-blocker">
                            <span class="ts-toggle-label">⚠ Bloqueo</span>
                        </label>
                        <div class="ts-toggle-detail" id="ts-blocker-detail" style="display:none">
                            <input type="text" name="blocker_description" maxlength="500" placeholder="Describe el impedimento">
                        </div>

                        <label class="ts-toggle-item">
                            <input type="checkbox" name="generated_deliverable" value="1" id="ts-toggle-deliverable">
                            <span class="ts-toggle-label">📦 Entregable</span>
                        </label>
                        <div class="ts-toggle-detail" id="ts-deliverable-detail" style="display:none">
                            <input type="text" name="operational_comment" maxlength="500" placeholder="Nombre/descripción del entregable">
                        </div>

                        <label class="ts-toggle-item">
                            <input type="checkbox" name="had_significant_progress" value="1" id="ts-toggle-progress">
                            <span class="ts-toggle-label">🚀 Avance significativo</span>
                        </label>
                    </div>

                    <div class="ts-quickadd-actions">
                        <button type="submit" class="ts-btn ts-btn-primary ts-btn-full" data-action="save">Guardar</button>
                        <div class="ts-quickadd-secondary">
                            <button type="button" class="ts-btn ts-btn-outline ts-btn-sm" data-action="save-duplicate">Guardar y duplicar</button>
                            <button type="button" class="ts-btn ts-btn-outline ts-btn-sm" data-action="save-another">Guardar y otra</button>
                        </div>
                    </div>
                </form>

                <?php if (!empty($recentActivitySuggestions)): ?>
                <div class="ts-recents">
                    <h4>Recientes</h4>
                    <div class="ts-recents-list">
                        <?php foreach ($recentActivitySuggestions as $recent): ?>
                        <button type="button" class="ts-recent-chip"
                            data-project-id="<?= (int) ($recent['project_id'] ?? 0) ?>"
                            data-task-id="<?= (int) ($recent['task_id'] ?? 0) ?>"
                            data-activity-type="<?= htmlspecialchars((string) ($recent['activity_type'] ?? ''), ENT_QUOTES) ?>"
                            data-activity-description="<?= htmlspecialchars((string) ($recent['activity_description'] ?? ''), ENT_QUOTES) ?>"
                            data-phase-name="<?= htmlspecialchars((string) ($recent['phase_name'] ?? ''), ENT_QUOTES) ?>"
                            data-subphase-name="<?= htmlspecialchars((string) ($recent['subphase_name'] ?? ''), ENT_QUOTES) ?>">
                            <span class="ts-recent-project"><?= htmlspecialchars((string) ($recent['project'] ?? '')) ?></span>
                            <span class="ts-recent-desc"><?= htmlspecialchars((string) ($recent['activity_description'] ?? '')) ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($userTemplates)): ?>
                <div class="ts-templates">
                    <h4>Plantillas</h4>
                    <div class="ts-templates-list">
                        <?php foreach ($userTemplates as $tpl): ?>
                        <button type="button" class="ts-template-chip"
                            data-project-id="<?= (int) ($tpl['project_id'] ?? 0) ?>"
                            data-task-id="<?= (int) ($tpl['task_id'] ?? 0) ?>"
                            data-activity-type="<?= htmlspecialchars((string) ($tpl['activity_type'] ?? ''), ENT_QUOTES) ?>"
                            data-activity-description="<?= htmlspecialchars((string) ($tpl['activity_description'] ?? ''), ENT_QUOTES) ?>"
                            data-phase-name="<?= htmlspecialchars((string) ($tpl['phase_name'] ?? ''), ENT_QUOTES) ?>">
                            <span>⭐ <?= htmlspecialchars((string) ($tpl['template_name'] ?? $tpl['activity_description'] ?? '')) ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</section>

<dialog id="ts-edit-modal" class="ts-modal">
    <form method="dialog" class="ts-modal-body" id="ts-edit-form">
        <h3>Editar actividad</h3>
        <input type="hidden" name="entry_id" id="ts-edit-entry-id">
        <div class="ts-field">
            <label>Proyecto</label>
            <select name="project_id" id="ts-edit-project" required>
                <option value="">Seleccionar...</option>
                <?php foreach ($projectsForTimesheet as $project): ?>
                <option value="<?= (int) ($project['project_id'] ?? 0) ?>"><?= htmlspecialchars((string) ($project['project'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ts-field">
            <label>Tarea</label>
            <select name="task_id" id="ts-edit-task">
                <option value="0">Registro general</option>
                <?php foreach ($tasksForTimesheet as $task): ?>
                <option value="<?= (int) ($task['task_id'] ?? 0) ?>" data-project-id="<?= (int) ($task['project_id'] ?? 0) ?>">
                    <?= htmlspecialchars((string) ($task['task_title'] ?? '')) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ts-field-row">
            <div class="ts-field ts-field-half">
                <label>Fecha</label>
                <input type="date" name="date" id="ts-edit-date" required>
            </div>
            <div class="ts-field ts-field-half">
                <label>Horas</label>
                <input type="number" name="hours" id="ts-edit-hours" step="0.25" min="0" max="24" required>
            </div>
        </div>
        <div class="ts-field">
            <label>Descripción</label>
            <input type="text" name="activity_description" id="ts-edit-desc" maxlength="255" required>
        </div>
        <div class="ts-field">
            <label>Comentario</label>
            <input type="text" name="comment" id="ts-edit-comment">
        </div>
        <div class="ts-field">
            <label>Fase</label>
            <input type="text" name="phase_name" id="ts-edit-phase" maxlength="120">
        </div>
        <div class="ts-field">
            <label>Tipo de actividad</label>
            <select name="activity_type" id="ts-edit-activity-type">
                <option value="">Sin clasificar</option>
                <?php foreach ($activityTypes as $type): ?>
                <option value="<?= htmlspecialchars((string) $type) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $type))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ts-toggles">
            <label class="ts-toggle-item"><input type="checkbox" name="had_blocker" value="1" id="ts-edit-blocker"> ⚠ Bloqueo</label>
            <div class="ts-toggle-detail" id="ts-edit-blocker-detail" style="display:none">
                <input type="text" name="blocker_description" id="ts-edit-blocker-desc" maxlength="500" placeholder="Impedimento">
            </div>
            <label class="ts-toggle-item"><input type="checkbox" name="generated_deliverable" value="1" id="ts-edit-deliverable"> 📦 Entregable</label>
            <label class="ts-toggle-item"><input type="checkbox" name="had_significant_progress" value="1" id="ts-edit-progress"> 🚀 Avance</label>
        </div>
        <div class="ts-modal-footer">
            <button type="button" class="ts-btn ts-btn-outline" onclick="document.getElementById('ts-edit-modal').close()">Cancelar</button>
            <button type="submit" class="ts-btn ts-btn-primary">Guardar cambios</button>
        </div>
    </form>
</dialog>

<dialog id="ts-dup-modal" class="ts-modal ts-modal-sm">
    <form method="dialog" class="ts-modal-body" id="ts-dup-form">
        <h3>Duplicar actividad</h3>
        <input type="hidden" name="entry_id" id="ts-dup-entry-id">
        <div class="ts-field">
            <label>Duplicar al día</label>
            <select name="target_date" id="ts-dup-date" required>
                <?php foreach ($gridDays as $day): ?>
                <option value="<?= htmlspecialchars($day['key']) ?>"><?= htmlspecialchars($day['label']) ?> <?= htmlspecialchars($day['number']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ts-modal-footer">
            <button type="button" class="ts-btn ts-btn-outline" onclick="document.getElementById('ts-dup-modal').close()">Cancelar</button>
            <button type="submit" class="ts-btn ts-btn-primary">Duplicar</button>
        </div>
    </form>
</dialog>

<dialog id="ts-dup-day-modal" class="ts-modal ts-modal-sm">
    <form method="dialog" class="ts-modal-body" id="ts-dup-day-form">
        <h3>Duplicar día completo</h3>
        <div class="ts-field">
            <label>Copiar actividades de</label>
            <select name="source_date" id="ts-dup-day-source" required>
                <?php foreach ($gridDays as $day): ?>
                <option value="<?= htmlspecialchars($day['key']) ?>"><?= htmlspecialchars($day['label']) ?> <?= htmlspecialchars($day['number']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ts-field">
            <label>Pegar en</label>
            <select name="target_date" id="ts-dup-day-target" required>
                <?php foreach ($gridDays as $day): ?>
                <option value="<?= htmlspecialchars($day['key']) ?>"><?= htmlspecialchars($day['label']) ?> <?= htmlspecialchars($day['number']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ts-modal-footer">
            <button type="button" class="ts-btn ts-btn-outline" onclick="document.getElementById('ts-dup-day-modal').close()">Cancelar</button>
            <button type="submit" class="ts-btn ts-btn-primary">Duplicar día</button>
        </div>
    </form>
</dialog>

<style>
.ts-module{display:flex;flex-direction:column;gap:0}
.ts-view-tabs{display:flex;gap:2px;border-bottom:2px solid var(--border);margin-bottom:16px}
.ts-tab{display:inline-flex;align-items:center;gap:6px;padding:10px 18px;text-decoration:none;color:var(--text-secondary);font-weight:600;font-size:14px;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .2s}
.ts-tab:hover{color:var(--text-primary);border-color:color-mix(in srgb,var(--primary) 40%,transparent)}
.ts-tab.active{color:var(--primary);border-color:var(--primary);font-weight:700}
.ts-tab svg{opacity:.7}.ts-tab.active svg{opacity:1}

.ts-header{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 18px;background:var(--surface);border:1px solid var(--border);border-radius:14px;flex-wrap:wrap}
.ts-header-left{display:flex;align-items:center;gap:12px}
.ts-header-center{display:flex;align-items:center;gap:12px}
.ts-header-right{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.ts-week-nav{display:flex;align-items:center;gap:4px}
.ts-nav-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text-primary);text-decoration:none;font-size:18px;font-weight:700;transition:all .15s}
.ts-nav-btn:hover{background:var(--primary);color:#fff;border-color:var(--primary)}
.ts-week-form{display:inline-flex}
.ts-week-input{padding:6px 10px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text-primary);font-weight:600;font-size:14px;width:160px}
.ts-week-range{font-size:13px;color:var(--text-secondary);font-weight:500}
.ts-total-badge{display:flex;flex-direction:column;align-items:center;padding:6px 14px;border-radius:10px;background:color-mix(in srgb,var(--primary) 12%,var(--surface));border:1px solid color-mix(in srgb,var(--primary) 30%,var(--border))}
.ts-total-badge strong{font-size:18px;color:var(--text-primary);line-height:1.1}
.ts-total-badge small{font-size:11px;color:var(--text-secondary)}
.ts-status-badge{padding:5px 12px;border-radius:999px;font-size:12px;font-weight:700}
.ts-approved{background:#dcfce7;color:#166534}.ts-rejected{background:#fee2e2;color:#991b1b}.ts-submitted{background:#fef3c7;color:#92400e}.ts-draft{background:#f1f5f9;color:#475569}.ts-partial{background:#dbeafe;color:#1e40af}
.ts-inline{display:inline-flex;align-items:center;gap:6px}
.ts-reopen-input{width:140px;padding:5px 8px;border:1px solid var(--border);border-radius:8px;font-size:12px}
.ts-btn{padding:8px 14px;border-radius:10px;border:1px solid var(--border);cursor:pointer;font-weight:600;font-size:13px;background:var(--surface);color:var(--text-primary);transition:all .15s;white-space:nowrap}
.ts-btn:hover{transform:translateY(-1px)}
.ts-btn:disabled{opacity:.5;cursor:not-allowed;transform:none}
.ts-btn-primary{background:var(--primary);color:#fff;border-color:var(--primary)}.ts-btn-primary:hover{box-shadow:0 4px 12px color-mix(in srgb,var(--primary) 40%,transparent)}
.ts-btn-outline{background:transparent;border-color:var(--border)}.ts-btn-outline:hover{border-color:var(--primary);color:var(--primary)}
.ts-btn-ghost{background:transparent;border:none;color:var(--primary);font-weight:600}.ts-btn-ghost:hover{background:color-mix(in srgb,var(--primary) 8%,transparent)}
.ts-btn-full{width:100%}.ts-btn-sm{font-size:12px;padding:6px 10px}
.ts-btn-danger{color:var(--danger);border-color:color-mix(in srgb,var(--danger) 40%,var(--border))}.ts-btn-danger:hover{background:color-mix(in srgb,var(--danger) 10%,transparent)}

.ts-indicators{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin:12px 0}
.ts-indicator{padding:12px 14px;background:var(--surface);border:1px solid var(--border);border-radius:12px;display:flex;flex-direction:column;gap:2px}
.ts-indicator-value{font-size:20px;font-weight:800;color:var(--text-primary)}
.ts-indicator-value.ts-good{color:#16a34a}.ts-indicator-value.ts-warn{color:#ca8a04}.ts-indicator-value.ts-low{color:#dc2626}
.ts-indicator-project{font-size:14px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ts-indicator-label{font-size:11px;color:var(--text-secondary);font-weight:600;text-transform:uppercase;letter-spacing:.03em}
.ts-autosave-msg{font-size:12px;color:var(--text-secondary);font-style:italic}
.ts-autosave-msg.saving{color:#2563eb}.ts-autosave-msg.saved{color:#16a34a}.ts-autosave-msg.error{color:#dc2626}

.ts-main-layout{display:grid;grid-template-columns:7fr 3fr;gap:16px;align-items:start}
.ts-calendar-column{display:flex;flex-direction:column;gap:12px}
.ts-calendar-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:8px}
.ts-day-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:10px;min-height:180px;display:flex;flex-direction:column;transition:border-color .2s,box-shadow .2s}
.ts-day-card:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));box-shadow:0 4px 12px color-mix(in srgb,var(--primary) 10%,transparent)}
.ts-day-card.ts-today{border-color:var(--primary);box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--primary) 20%,transparent)}
.ts-day-card.ts-weekend{background:color-mix(in srgb,var(--surface) 96%,var(--border) 4%)}
.ts-day-card.ts-drag-over{border-color:var(--primary);background:color-mix(in srgb,var(--primary) 6%,var(--surface));box-shadow:0 0 0 2px color-mix(in srgb,var(--primary) 30%,transparent)}
.ts-day-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent)}
.ts-day-name{display:flex;align-items:center;gap:4px}
.ts-day-name strong{font-size:13px;font-weight:700;color:var(--text-primary)}
.ts-day-number{font-size:12px;color:var(--text-secondary);font-weight:500}
.ts-today-dot{width:6px;height:6px;border-radius:50%;background:var(--primary)}
.ts-day-total{font-size:13px;font-weight:700;color:var(--primary);background:color-mix(in srgb,var(--primary) 10%,transparent);padding:2px 8px;border-radius:6px}
.ts-day-activities{flex:1;display:flex;flex-direction:column;gap:6px;min-height:60px}
.ts-empty-day{font-size:12px;color:var(--text-secondary);text-align:center;padding:16px 0;font-style:italic}
.ts-add-to-day{width:100%;padding:4px;border:1px dashed var(--border);border-radius:8px;background:transparent;color:var(--text-secondary);font-size:16px;cursor:pointer;margin-top:auto;transition:all .15s}
.ts-add-to-day:hover{border-color:var(--primary);color:var(--primary);background:color-mix(in srgb,var(--primary) 6%,transparent)}

.ts-activity-chip{background:color-mix(in srgb,var(--surface) 95%,var(--background) 5%);border:1px solid var(--border);border-radius:8px;padding:6px 8px;cursor:default;position:relative;transition:border-color .15s,box-shadow .15s}
.ts-activity-chip:hover{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));box-shadow:0 2px 8px color-mix(in srgb,var(--primary) 12%,transparent)}
.ts-activity-chip[draggable="true"]{cursor:grab}
.ts-activity-chip.ts-chip-locked{opacity:.75}
.ts-activity-chip.ts-dragging{opacity:.4;transform:rotate(2deg)}
.ts-chip-main{display:flex;justify-content:space-between;align-items:center;gap:4px}
.ts-chip-project{font-size:11px;font-weight:700;color:var(--primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:80%}
.ts-chip-hours{font-size:11px;font-weight:700;color:var(--text-primary);background:color-mix(in srgb,var(--primary) 12%,transparent);padding:1px 5px;border-radius:4px;flex-shrink:0}
.ts-chip-desc{font-size:11px;color:var(--text-secondary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:2px}
.ts-chip-flags{display:flex;gap:3px;margin-top:2px}
.ts-flag{font-size:10px;cursor:help}
.ts-chip-actions{display:none;position:absolute;top:2px;right:2px;gap:2px;background:var(--surface);border-radius:6px;padding:2px;box-shadow:0 2px 8px rgba(0,0,0,.12)}
.ts-activity-chip:hover .ts-chip-actions{display:flex}
.ts-chip-btn{border:none;background:transparent;cursor:pointer;font-size:12px;padding:2px 4px;border-radius:4px;transition:background .15s}
.ts-chip-btn:hover{background:color-mix(in srgb,var(--border) 60%,transparent)}

.ts-quickadd-column{position:sticky;top:90px}
.ts-quickadd-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px;box-shadow:0 8px 24px color-mix(in srgb,#0f172a 8%,transparent)}
.ts-quickadd-title{margin:0 0 12px;font-size:16px;font-weight:800;color:var(--text-primary)}
.ts-quickadd-form{display:flex;flex-direction:column;gap:10px}
.ts-field{display:flex;flex-direction:column;gap:4px}
.ts-field label{font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.03em}
.ts-field input,.ts-field select,.ts-field textarea{padding:8px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--surface);color:var(--text-primary);width:100%}
.ts-field input:focus,.ts-field select:focus{border-color:var(--primary);box-shadow:0 0 0 2px color-mix(in srgb,var(--primary) 16%,transparent);outline:none}
.ts-field-row{display:flex;gap:8px}.ts-field-half{flex:1}
.ts-toggles{display:flex;flex-direction:column;gap:6px;padding:8px 0}
.ts-toggle-item{display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600;color:var(--text-primary)}
.ts-toggle-item input[type="checkbox"]{width:auto;margin:0}
.ts-toggle-label{font-size:13px}
.ts-toggle-detail{padding-left:24px}
.ts-toggle-detail input{padding:6px 8px;font-size:12px}
.ts-quickadd-actions{display:flex;flex-direction:column;gap:6px;margin-top:4px}
.ts-quickadd-secondary{display:flex;gap:6px}
.ts-quickadd-secondary .ts-btn{flex:1}

.ts-recents,.ts-templates{margin-top:14px;padding-top:12px;border-top:1px solid var(--border)}
.ts-recents h4,.ts-templates h4{margin:0 0 8px;font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.04em}
.ts-recents-list,.ts-templates-list{display:flex;flex-direction:column;gap:4px}
.ts-recent-chip,.ts-template-chip{display:flex;flex-direction:column;gap:1px;padding:6px 10px;border:1px solid var(--border);border-radius:8px;background:var(--surface);cursor:pointer;text-align:left;font-size:12px;transition:all .15s;width:100%}
.ts-recent-chip:hover,.ts-template-chip:hover{border-color:var(--primary);background:color-mix(in srgb,var(--primary) 6%,var(--surface))}
.ts-recent-project{font-weight:700;color:var(--primary);font-size:11px}
.ts-recent-desc{color:var(--text-secondary);font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

.ts-history-panel{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:0}
.ts-history-panel summary{padding:12px 14px;cursor:pointer;font-weight:700;font-size:13px;color:var(--text-secondary)}
.ts-history-panel[open] summary{border-bottom:1px solid var(--border)}
.ts-history-list{list-style:none;margin:0;padding:10px 14px;display:flex;flex-direction:column;gap:6px}
.ts-history-list li{display:flex;gap:8px;font-size:12px;color:var(--text-secondary);align-items:center;flex-wrap:wrap}
.ts-history-list li strong{color:var(--text-primary)}
.ts-history-list li em{font-style:italic;color:var(--text-secondary)}

.ts-modal{border:none;border-radius:14px;max-width:520px;width:95%;padding:0;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.ts-modal::backdrop{background:rgba(15,23,42,.5)}
.ts-modal-sm{max-width:380px}
.ts-modal-body{display:flex;flex-direction:column;gap:12px;padding:20px}
.ts-modal-body h3{margin:0;font-size:18px;font-weight:800}
.ts-modal-footer{display:flex;justify-content:flex-end;gap:8px;margin-top:8px}

@media(max-width:1280px){.ts-calendar-grid{grid-template-columns:repeat(4,1fr)}}
@media(max-width:1024px){.ts-main-layout{grid-template-columns:1fr}.ts-quickadd-column{position:static}.ts-indicators{grid-template-columns:repeat(3,1fr)}}
@media(max-width:768px){.ts-calendar-grid{grid-template-columns:repeat(2,1fr)}.ts-header{flex-direction:column;align-items:stretch}.ts-header-left,.ts-header-center,.ts-header-right{justify-content:center}.ts-indicators{grid-template-columns:repeat(2,1fr)}}
@media(max-width:480px){.ts-calendar-grid{grid-template-columns:1fr}.ts-indicators{grid-template-columns:1fr}}
</style>

<script>
(() => {
    const BASE = '<?= $basePath ?>';
    const WEEK_VALUE = '<?= htmlspecialchars($weekValue, ENT_QUOTES) ?>';
    const autosaveEl = document.getElementById('ts-autosave');

    function setAutosave(text, cls = '') {
        if (!autosaveEl) return;
        autosaveEl.textContent = text;
        autosaveEl.className = 'ts-autosave-msg ' + cls;
    }

    const weekInput = document.querySelector('.ts-week-input');
    weekInput?.addEventListener('change', () => {
        window.location.href = BASE + '/timesheets?week=' + encodeURIComponent(weekInput.value);
    });

    const quickForm = document.getElementById('ts-quick-form');
    const quickProject = document.getElementById('ts-quick-add-project');
    const quickTask = document.getElementById('ts-quick-add-task');

    function filterTasks(projectSelect, taskSelect) {
        if (!taskSelect) return;
        const pid = Number(projectSelect?.value || 0);
        taskSelect.querySelectorAll('option[data-project-id]').forEach(opt => {
            opt.hidden = pid > 0 && Number(opt.dataset.projectId || 0) !== pid;
        });
        const sel = taskSelect.selectedOptions[0];
        if (sel && sel.hidden) taskSelect.value = '0';
    }

    quickProject?.addEventListener('change', () => filterTasks(quickProject, quickTask));
    filterTasks(quickProject, quickTask);

    document.getElementById('ts-toggle-blocker')?.addEventListener('change', function() {
        document.getElementById('ts-blocker-detail').style.display = this.checked ? '' : 'none';
    });
    document.getElementById('ts-toggle-deliverable')?.addEventListener('change', function() {
        document.getElementById('ts-deliverable-detail').style.display = this.checked ? '' : 'none';
    });
    document.getElementById('ts-edit-blocker')?.addEventListener('change', function() {
        document.getElementById('ts-edit-blocker-detail').style.display = this.checked ? '' : 'none';
    });

    async function postJSON(url, data) {
        setAutosave('Guardando...', 'saving');
        const body = new URLSearchParams(data);
        const res = await fetch(BASE + url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
        if (!res.ok) {
            const msg = await res.text();
            setAutosave('Error al guardar', 'error');
            alert(msg || 'Error al guardar.');
            throw new Error(msg);
        }
        setAutosave('Guardado', 'saved');
        return res.json().catch(() => ({ ok: true }));
    }

    function collectFormData(form) {
        const fd = new FormData(form);
        const data = {};
        for (const [k, v] of fd.entries()) data[k] = v;
        if (!fd.has('had_blocker')) data.had_blocker = '0';
        if (!fd.has('generated_deliverable')) data.generated_deliverable = '0';
        if (!fd.has('had_significant_progress')) data.had_significant_progress = '0';
        return data;
    }

    async function saveActivity(data, action) {
        data.sync_operational = '1';
        await postJSON('/timesheets/activity', data);
        if (action === 'save-another') {
            quickForm.querySelector('[name="activity_description"]').value = '';
            quickForm.querySelector('[name="hours"]').value = '';
            quickForm.querySelector('[name="comment"]').value = '';
            quickForm.querySelector('[name="hours"]')?.focus();
        } else if (action === 'save-duplicate') {
            const dateInput = quickForm.querySelector('[name="date"]');
            const currentDate = new Date(dateInput.value);
            currentDate.setDate(currentDate.getDate() + 1);
            dateInput.value = currentDate.toISOString().split('T')[0];
        } else {
            quickForm.reset();
        }
        setTimeout(() => location.reload(), 300);
    }

    quickForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = collectFormData(quickForm);
        if (!data.project_id || !data.hours || !data.activity_description) {
            alert('Proyecto, horas y descripción son obligatorios.');
            return;
        }
        await saveActivity(data, 'save');
    });

    document.querySelectorAll('[data-action="save-duplicate"],[data-action="save-another"]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const data = collectFormData(quickForm);
            if (!data.project_id || !data.hours || !data.activity_description) {
                alert('Proyecto, horas y descripción son obligatorios.');
                return;
            }
            await saveActivity(data, btn.dataset.action);
        });
    });

    document.querySelectorAll('.ts-recent-chip, .ts-template-chip').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!quickForm) return;
            const pf = quickForm.querySelector('[name="project_id"]');
            const tf = quickForm.querySelector('[name="task_id"]');
            const at = quickForm.querySelector('[name="activity_type"]');
            const ad = quickForm.querySelector('[name="activity_description"]');
            const ph = quickForm.querySelector('[name="phase_name"]');
            if (pf) pf.value = btn.dataset.projectId || '';
            filterTasks(quickProject, quickTask);
            if (tf) tf.value = btn.dataset.taskId || '0';
            if (at) at.value = btn.dataset.activityType || '';
            if (ad) ad.value = btn.dataset.activityDescription || '';
            if (ph) ph.value = btn.dataset.phaseName || '';
            quickForm.querySelector('[name="hours"]')?.focus();
        });
    });

    document.querySelectorAll('.ts-add-to-day').forEach(btn => {
        btn.addEventListener('click', () => {
            const dateInput = document.getElementById('ts-quick-add-date');
            if (dateInput) dateInput.value = btn.dataset.targetDate;
            document.getElementById('ts-quick-add-project')?.focus();
        });
    });

    document.querySelectorAll('.ts-edit-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const chip = btn.closest('.ts-activity-chip');
            if (!chip) return;
            const modal = document.getElementById('ts-edit-modal');
            document.getElementById('ts-edit-entry-id').value = chip.dataset.entryId;
            document.getElementById('ts-edit-project').value = chip.dataset.projectId;
            const editTask = document.getElementById('ts-edit-task');
            filterTasks(document.getElementById('ts-edit-project'), editTask);
            editTask.value = chip.dataset.taskId || '0';
            document.getElementById('ts-edit-date').value = chip.dataset.date;
            document.getElementById('ts-edit-hours').value = chip.dataset.hours;
            document.getElementById('ts-edit-desc').value = chip.dataset.description;
            document.getElementById('ts-edit-comment').value = chip.dataset.comment;
            document.getElementById('ts-edit-phase').value = chip.dataset.phase;
            document.getElementById('ts-edit-activity-type').value = chip.dataset.activityType;
            const blockerCb = document.getElementById('ts-edit-blocker');
            blockerCb.checked = chip.dataset.blocker === '1';
            document.getElementById('ts-edit-blocker-detail').style.display = blockerCb.checked ? '' : 'none';
            document.getElementById('ts-edit-blocker-desc').value = chip.dataset.blockerDesc;
            document.getElementById('ts-edit-deliverable').checked = chip.dataset.deliverable === '1';
            document.getElementById('ts-edit-progress').checked = chip.dataset.progress === '1';
            document.getElementById('ts-edit-project')?.addEventListener('change', () => filterTasks(document.getElementById('ts-edit-project'), editTask), { once: true });
            modal.showModal();
        });
    });

    document.getElementById('ts-edit-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = collectFormData(e.target);
        data.sync_operational = '1';
        await postJSON('/timesheets/cell', data);
        setTimeout(() => location.reload(), 300);
    });

    document.querySelectorAll('.ts-dup-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const chip = btn.closest('.ts-activity-chip');
            if (!chip) return;
            document.getElementById('ts-dup-entry-id').value = chip.dataset.entryId;
            document.getElementById('ts-dup-modal').showModal();
        });
    });

    document.getElementById('ts-dup-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const entryId = document.getElementById('ts-dup-entry-id').value;
        const targetDate = document.getElementById('ts-dup-date').value;
        await postJSON('/timesheets/duplicate-entry', { entry_id: entryId, target_date: targetDate });
        setTimeout(() => location.reload(), 300);
    });

    document.querySelectorAll('.ts-del-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.stopPropagation();
            const chip = btn.closest('.ts-activity-chip');
            if (!chip) return;
            if (!confirm('¿Eliminar esta actividad?')) return;
            await postJSON('/timesheets/delete-entry', { entry_id: chip.dataset.entryId });
            setTimeout(() => location.reload(), 300);
        });
    });

    document.getElementById('ts-duplicate-day-btn')?.addEventListener('click', () => {
        document.getElementById('ts-dup-day-modal').showModal();
    });

    document.getElementById('ts-dup-day-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const source = document.getElementById('ts-dup-day-source').value;
        const target = document.getElementById('ts-dup-day-target').value;
        if (source === target) { alert('Origen y destino no pueden ser iguales.'); return; }
        await postJSON('/timesheets/duplicate-day', { source_date: source, target_date: target, week: WEEK_VALUE });
        setTimeout(() => location.reload(), 300);
    });

    let dragEntry = null;
    document.querySelectorAll('.ts-activity-chip[draggable="true"]').forEach(chip => {
        chip.addEventListener('dragstart', (e) => {
            dragEntry = chip;
            chip.classList.add('ts-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', chip.dataset.entryId);
        });
        chip.addEventListener('dragend', () => {
            chip.classList.remove('ts-dragging');
            document.querySelectorAll('.ts-drag-over').forEach(el => el.classList.remove('ts-drag-over'));
            dragEntry = null;
        });
    });

    document.querySelectorAll('.ts-day-card').forEach(card => {
        card.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            card.classList.add('ts-drag-over');
        });
        card.addEventListener('dragleave', () => card.classList.remove('ts-drag-over'));
        card.addEventListener('drop', async (e) => {
            e.preventDefault();
            card.classList.remove('ts-drag-over');
            if (!dragEntry) return;
            const entryId = dragEntry.dataset.entryId;
            const targetDate = card.dataset.date;
            const sourceDate = dragEntry.dataset.date;
            if (sourceDate === targetDate) return;
            try {
                await postJSON('/timesheets/move-entry', { entry_id: entryId, target_date: targetDate });
                setTimeout(() => location.reload(), 300);
            } catch (err) {}
        });
    });
})();
</script>
