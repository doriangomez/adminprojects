<?php
$basePath = $basePath ?? '';
$talentRecord = is_array($talentRecord ?? null) ? $talentRecord : null;
$talentId = $talentId ?? null;
$kanbanColumns = is_array($kanbanColumns ?? null) ? $kanbanColumns : [];
$taskCounts = is_array($taskCounts ?? null) ? $taskCounts : [];
$totalTasks = (int) ($totalTasks ?? 0);
$totalActiveTasks = (int) ($totalActiveTasks ?? 0);
$weeklyHours = (float) ($weeklyHours ?? 0);
$todayHours = (float) ($todayHours ?? 0);
$weeklyCapacity = (float) ($weeklyCapacity ?? 40);
$activeBlockers = is_array($activeBlockers ?? null) ? $activeBlockers : [];
$canReport = !empty($canReport);
$timesheetsEnabled = !empty($timesheetsEnabled);
$weekStart = $weekStart ?? new DateTimeImmutable('monday this week');
$weekEnd = $weekStart->modify('+6 days');

$capacityPct = $weeklyCapacity > 0 ? min(100, round(($weeklyHours / $weeklyCapacity) * 100)) : 0;
$capacityClass = $capacityPct >= 90 ? 'cap-danger' : ($capacityPct >= 70 ? 'cap-warning' : 'cap-ok');

$columnMeta = [
    'todo'        => ['label' => 'Pendiente',   'accent' => '#64748b', 'icon' => '⏳', 'desc' => 'Por iniciar'],
    'in_progress' => ['label' => 'En proceso',  'accent' => '#0ea5e9', 'icon' => '🔄', 'desc' => 'Trabajando'],
    'review'      => ['label' => 'En revisión', 'accent' => '#f59e0b', 'icon' => '🔍', 'desc' => 'Esperando revisión'],
    'blocked'     => ['label' => 'Bloqueado',   'accent' => '#ef4444', 'icon' => '⛔', 'desc' => 'Necesita atención'],
    'done'        => ['label' => 'Completado',  'accent' => '#22c55e', 'icon' => '✅', 'desc' => 'Terminado'],
];

$priorityMeta = [
    'high'   => ['label' => 'Alta',  'cls' => 'mw-prio-high'],
    'medium' => ['label' => 'Media', 'cls' => 'mw-prio-medium'],
    'low'    => ['label' => 'Baja',  'cls' => 'mw-prio-low'],
];

$impactMeta = [
    'bajo'    => ['label' => 'Bajo',    'cls' => 'mw-impact-low'],
    'medio'   => ['label' => 'Medio',   'cls' => 'mw-impact-med'],
    'alto'    => ['label' => 'Alto',    'cls' => 'mw-impact-high'],
    'critico' => ['label' => 'Crítico', 'cls' => 'mw-impact-crit'],
];

$today = new DateTimeImmutable();
$weekLabel = sprintf('%s – %s', $weekStart->format('d M'), $weekEnd->format('d M Y'));
?>

<div class="mw-shell">

    <!-- KPI Row -->
    <div class="mw-kpi-row">
        <div class="mw-kpi">
            <div class="mw-kpi-icon" style="--kpi-color: var(--primary)">
                <svg viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 8h7M9 12h7M9 16h5"/><path d="m6.5 8 .5.5 1-1"/><path d="m6.5 12 .5.5 1-1"/></svg>
            </div>
            <div class="mw-kpi-body">
                <div class="mw-kpi-val"><?= $totalActiveTasks ?></div>
                <div class="mw-kpi-lbl">Tareas activas</div>
                <div class="mw-kpi-sub"><?= $totalTasks ?> en total</div>
            </div>
        </div>
        <div class="mw-kpi">
            <div class="mw-kpi-icon" style="--kpi-color: #0ea5e9">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="8"/><path d="M12 13V9"/><path d="m12 13 3 2"/><path d="M9 3h6M12 3v2"/></svg>
            </div>
            <div class="mw-kpi-body">
                <div class="mw-kpi-val"><?= number_format($todayHours, 1) ?>h</div>
                <div class="mw-kpi-lbl">Horas hoy</div>
                <div class="mw-kpi-sub"><?= $today->format('d M') ?></div>
            </div>
        </div>
        <div class="mw-kpi">
            <div class="mw-kpi-icon <?= $capacityClass ?>" style="--kpi-color: <?= $capacityPct >= 90 ? 'var(--danger)' : ($capacityPct >= 70 ? 'var(--warning)' : 'var(--success)') ?>">
                <svg viewBox="0 0 24 24"><path d="M4 20h16M6 12v8M10 6v14M14 10v10M18 3v17"/></svg>
            </div>
            <div class="mw-kpi-body">
                <div class="mw-kpi-val"><?= number_format($weeklyHours, 1) ?>h</div>
                <div class="mw-kpi-lbl">Horas esta semana</div>
                <div class="mw-kpi-sub">de <?= number_format($weeklyCapacity, 0) ?>h — <?= $capacityPct ?>% capacidad</div>
            </div>
        </div>
        <div class="mw-kpi">
            <div class="mw-kpi-icon" style="--kpi-color: <?= count($activeBlockers) > 0 ? 'var(--danger)' : 'var(--success)' ?>">
                <svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div class="mw-kpi-body">
                <div class="mw-kpi-val"><?= count($activeBlockers) ?></div>
                <div class="mw-kpi-lbl">Bloqueos activos</div>
                <div class="mw-kpi-sub"><?= count($activeBlockers) === 0 ? 'Sin bloqueos' : 'Requiere atención' ?></div>
            </div>
        </div>
    </div>

    <!-- Capacity Bar -->
    <?php if ($weeklyCapacity > 0): ?>
        <div class="mw-cap-bar-card">
            <div class="mw-cap-bar-label">
                <span>Capacidad semanal: <strong><?= $weekLabel ?></strong></span>
                <span class="mw-cap-pct <?= $capacityClass ?>"><?= $capacityPct ?>%</span>
            </div>
            <div class="mw-cap-track">
                <div class="mw-cap-fill <?= $capacityClass ?>" style="width: <?= $capacityPct ?>%"></div>
            </div>
            <div class="mw-cap-legend">
                <span><?= number_format($weeklyHours, 1) ?>h registradas</span>
                <span><?= number_format(max(0, $weeklyCapacity - $weeklyHours), 1) ?>h disponibles</span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Active Blockers -->
    <?php if (!empty($activeBlockers)): ?>
        <div class="mw-blockers-section">
            <div class="mw-section-head">
                <span class="mw-section-icon danger">⛔</span>
                <strong>Bloqueos activos en mis tareas</strong>
                <span class="mw-badge danger"><?= count($activeBlockers) ?></span>
            </div>
            <div class="mw-blockers-list">
                <?php foreach ($activeBlockers as $blocker): ?>
                    <?php $impactKey = (string) ($blocker['impact_level'] ?? 'medio'); ?>
                    <div class="mw-blocker-item">
                        <div class="mw-blocker-left">
                            <span class="mw-impact-badge <?= $impactMeta[$impactKey]['cls'] ?? 'mw-impact-med' ?>">
                                <?= $impactMeta[$impactKey]['label'] ?? ucfirst($impactKey) ?>
                            </span>
                            <div class="mw-blocker-info">
                                <strong><?= htmlspecialchars((string) ($blocker['title'] ?? '')) ?></strong>
                                <span>Tarea: <?= htmlspecialchars((string) ($blocker['task_title'] ?? '')) ?> — <?= htmlspecialchars((string) ($blocker['project_name'] ?? '')) ?></span>
                            </div>
                        </div>
                        <div class="mw-blocker-date">
                            Detectado: <?= !empty($blocker['detected_at']) ? (new DateTimeImmutable($blocker['detected_at']))->format('d M') : '—' ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Layout: Kanban + Sidebar -->
    <div class="mw-main-layout">

        <!-- Kanban Board -->
        <div class="mw-kanban-wrap">
            <div class="mw-section-head">
                <span class="mw-section-icon primary">📋</span>
                <strong>Mis tareas</strong>
                <a href="<?= $basePath ?>/tasks?view=kanban" class="mw-link-sm">Ver kanban completo →</a>
            </div>

            <?php if ($totalTasks === 0): ?>
                <div class="mw-empty">
                    <div class="mw-empty-icon">📌</div>
                    <div>
                        <strong>Sin tareas asignadas</strong>
                        <p>Cuando el PMO te asigne tareas, aparecerán aquí en tu panel de trabajo.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="mw-kb-board">
                    <?php foreach ($columnMeta as $colKey => $colInfo): ?>
                        <?php $tasks = $kanbanColumns[$colKey] ?? []; ?>
                        <div class="mw-kb-col" data-col="<?= $colKey ?>">
                            <div class="mw-kb-col-head" style="--col-accent: <?= $colInfo['accent'] ?>">
                                <span><?= $colInfo['icon'] ?></span>
                                <span class="mw-kb-col-lbl"><?= $colInfo['label'] ?></span>
                                <span class="mw-kb-col-cnt"><?= count($tasks) ?></span>
                            </div>
                            <div class="mw-kb-cards">
                                <?php if (empty($tasks)): ?>
                                    <div class="mw-kb-empty">Sin tareas</div>
                                <?php else: ?>
                                    <?php foreach ($tasks as $task): ?>
                                        <?php
                                        $taskId = (int) ($task['id'] ?? 0);
                                        $priorityKey = (string) ($task['priority'] ?? 'medium');
                                        $prioMeta = $priorityMeta[$priorityKey] ?? ['label' => 'Media', 'cls' => 'mw-prio-medium'];
                                        $hasBlocker = !empty($task['stopper_title']);
                                        $isOverdue = !empty($task['due_date']) && strtotime($task['due_date']) < strtotime('today') && $colKey !== 'done';
                                        ?>
                                        <div class="mw-kb-card <?= $hasBlocker ? 'has-stopper' : '' ?> <?= $isOverdue ? 'is-overdue' : '' ?>" data-task-id="<?= $taskId ?>">
                                            <?php if ($hasBlocker): ?>
                                                <div class="mw-blocker-tag">
                                                    <svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                                    <?= htmlspecialchars(substr((string) $task['stopper_title'], 0, 50)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="mw-kb-card-title"><?= htmlspecialchars((string) ($task['title'] ?? '')) ?></div>
                                            <div class="mw-kb-card-project"><?= htmlspecialchars((string) ($task['project'] ?? '')) ?></div>
                                            <div class="mw-kb-card-foot">
                                                <span class="mw-prio-pill <?= $prioMeta['cls'] ?>"><?= $prioMeta['label'] ?></span>
                                                <?php if (!empty($task['estimated_hours'])): ?>
                                                    <span class="mw-hours-pill">⏱ <?= (float) $task['estimated_hours'] ?>h</span>
                                                <?php endif; ?>
                                                <?php if (!empty($task['due_date'])): ?>
                                                    <span class="mw-due-pill <?= $isOverdue ? 'overdue' : '' ?>">
                                                        <?= (new DateTimeImmutable($task['due_date']))->format('d M') ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mw-kb-card-status">
                                                <select class="mw-status-sel" data-task="<?= $taskId ?>" data-current="<?= htmlspecialchars($colKey) ?>" title="Cambiar estado">
                                                    <?php foreach ($columnMeta as $targetCol => $targetInfo): ?>
                                                        <option value="<?= $targetCol ?>" <?= $targetCol === $colKey ? 'selected' : '' ?>>
                                                            <?= $targetInfo['icon'] ?> <?= $targetInfo['label'] ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="mw-sidebar">

            <!-- Quick Actions -->
            <div class="mw-side-card">
                <div class="mw-side-card-head">Acciones rápidas</div>
                <div class="mw-side-actions">
                    <?php if ($canReport): ?>
                        <a href="<?= $basePath ?>/timesheets" class="mw-side-action-btn primary">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="8"/><path d="M12 13V9"/><path d="m12 13 3 2"/><path d="M9 3h6M12 3v2"/></svg>
                            Registrar horas hoy
                        </a>
                    <?php endif; ?>
                    <a href="<?= $basePath ?>/tasks" class="mw-side-action-btn">
                        <svg viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 8h7M9 12h7M9 16h5"/></svg>
                        Ver todas mis tareas
                    </a>
                </div>
            </div>

            <!-- Task Summary -->
            <div class="mw-side-card">
                <div class="mw-side-card-head">Resumen de tareas</div>
                <div class="mw-task-summary">
                    <?php foreach ($columnMeta as $colKey => $colInfo): ?>
                        <div class="mw-task-summary-row" style="--row-accent: <?= $colInfo['accent'] ?>">
                            <span class="mw-task-summary-icon"><?= $colInfo['icon'] ?></span>
                            <span class="mw-task-summary-lbl"><?= $colInfo['label'] ?></span>
                            <span class="mw-task-summary-cnt"><?= $taskCounts[$colKey] ?? 0 ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Timesheet Status -->
            <?php if ($timesheetsEnabled): ?>
                <div class="mw-side-card">
                    <div class="mw-side-card-head">Timesheet semanal</div>
                    <div class="mw-timesheet-mini">
                        <div class="mw-ts-week-label"><?= $weekLabel ?></div>
                        <div class="mw-ts-hours">
                            <strong><?= number_format($weeklyHours, 1) ?>h</strong>
                            <span>de <?= number_format($weeklyCapacity, 0) ?>h</span>
                        </div>
                        <div class="mw-mini-track">
                            <div class="mw-mini-fill <?= $capacityClass ?>" style="width: <?= $capacityPct ?>%"></div>
                        </div>
                        <?php if ($canReport): ?>
                            <a href="<?= $basePath ?>/timesheets" class="mw-ts-link">Ir al timesheet →</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Status change success toast -->
<div class="mw-toast" id="mw-toast" hidden>Estado actualizado</div>

<style>
    .mw-shell { display:flex; flex-direction:column; gap:20px; }

    /* KPIs */
    .mw-kpi-row { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:14px; }
    .mw-kpi { display:flex; align-items:center; gap:14px; background: var(--surface); border:1px solid var(--border); border-radius:14px; padding:16px; box-shadow: 0 6px 18px color-mix(in srgb, #0f172a 8%, transparent); }
    .mw-kpi-icon { width:48px; height:48px; border-radius:12px; background: color-mix(in srgb, var(--kpi-color) 16%, var(--background)); border:1px solid color-mix(in srgb, var(--kpi-color) 30%, var(--border)); display:inline-flex; align-items:center; justify-content:center; color: var(--kpi-color); flex-shrink:0; }
    .mw-kpi-icon svg { width:24px; height:24px; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
    .mw-kpi-body { display:flex; flex-direction:column; gap:2px; min-width:0; }
    .mw-kpi-val { font-size:26px; font-weight:800; color: var(--text-primary); line-height:1.1; }
    .mw-kpi-lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.05em; font-weight:700; color: var(--text-secondary); }
    .mw-kpi-sub { font-size:12px; color: var(--text-secondary); }

    /* Capacity Bar */
    .mw-cap-bar-card { background: var(--surface); border:1px solid var(--border); border-radius:14px; padding:16px; display:flex; flex-direction:column; gap:10px; }
    .mw-cap-bar-label { display:flex; align-items:center; justify-content:space-between; font-size:13px; font-weight:600; }
    .mw-cap-pct { padding:4px 10px; border-radius:999px; font-size:12px; font-weight:800; }
    .mw-cap-track { height:10px; background: color-mix(in srgb, var(--surface) 80%, var(--background) 20%); border-radius:999px; overflow:hidden; border:1px solid var(--border); }
    .mw-cap-fill { height:100%; border-radius:999px; transition: width 0.5s ease; }
    .cap-ok .mw-cap-fill, .mw-cap-fill.cap-ok { background: var(--success); }
    .cap-warning .mw-cap-fill, .mw-cap-fill.cap-warning { background: var(--warning); }
    .cap-danger .mw-cap-fill, .mw-cap-fill.cap-danger { background: var(--danger); }
    .cap-ok { color: var(--success); background: color-mix(in srgb, var(--success) 14%, var(--surface)); }
    .cap-warning { color: var(--warning); background: color-mix(in srgb, var(--warning) 14%, var(--surface)); }
    .cap-danger { color: var(--danger); background: color-mix(in srgb, var(--danger) 14%, var(--surface)); }
    .mw-cap-legend { display:flex; justify-content:space-between; font-size:12px; color: var(--text-secondary); }

    /* Blockers */
    .mw-blockers-section { background: color-mix(in srgb, var(--danger) 8%, var(--surface)); border:1px solid color-mix(in srgb, var(--danger) 35%, var(--border)); border-radius:14px; padding:16px; display:flex; flex-direction:column; gap:12px; }
    .mw-section-head { display:flex; align-items:center; gap:10px; }
    .mw-section-head strong { font-size:14px; font-weight:800; flex:1; }
    .mw-section-icon { font-size:18px; }
    .mw-badge { padding:3px 9px; border-radius:999px; font-size:11px; font-weight:800; }
    .mw-badge.danger { background: color-mix(in srgb, var(--danger) 18%, var(--surface)); color: var(--danger); border:1px solid color-mix(in srgb, var(--danger) 35%, var(--border)); }
    .mw-blockers-list { display:flex; flex-direction:column; gap:8px; }
    .mw-blocker-item { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 12px; background: var(--surface); border:1px solid var(--border); border-radius:10px; }
    .mw-blocker-left { display:flex; align-items:center; gap:10px; flex:1; min-width:0; }
    .mw-blocker-info { display:flex; flex-direction:column; gap:2px; min-width:0; }
    .mw-blocker-info strong { font-size:13px; font-weight:700; color: var(--text-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .mw-blocker-info span { font-size:12px; color: var(--text-secondary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .mw-blocker-date { font-size:12px; color: var(--text-secondary); white-space:nowrap; }
    .mw-impact-badge { padding:4px 9px; border-radius:7px; font-size:11px; font-weight:700; white-space:nowrap; }
    .mw-impact-low { background: color-mix(in srgb, var(--success) 14%, var(--surface)); color: var(--success); border:1px solid color-mix(in srgb, var(--success) 30%, var(--border)); }
    .mw-impact-med { background: color-mix(in srgb, var(--warning) 14%, var(--surface)); color: var(--warning); border:1px solid color-mix(in srgb, var(--warning) 30%, var(--border)); }
    .mw-impact-high { background: color-mix(in srgb, var(--danger) 14%, var(--surface)); color: var(--danger); border:1px solid color-mix(in srgb, var(--danger) 30%, var(--border)); }
    .mw-impact-crit { background: var(--danger); color: #fff; border:1px solid var(--danger); }

    /* Main Layout */
    .mw-main-layout { display:grid; grid-template-columns: 1fr 280px; gap:20px; align-items:start; }
    @media (max-width: 1100px) { .mw-main-layout { grid-template-columns: 1fr; } .mw-sidebar { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:14px; } }

    /* Kanban */
    .mw-kanban-wrap { display:flex; flex-direction:column; gap:14px; }
    .mw-link-sm { font-size:12px; font-weight:600; color: var(--primary); text-decoration:none; margin-left:auto; }
    .mw-link-sm:hover { text-decoration:underline; }
    .mw-empty { display:flex; align-items:flex-start; gap:14px; padding:20px; background: var(--surface); border:1px dashed var(--border); border-radius:14px; }
    .mw-empty-icon { font-size:32px; }
    .mw-empty p { margin:4px 0 0; font-size:13px; color: var(--text-secondary); }
    .mw-empty strong { font-size:14px; }
    .mw-kb-board { display:grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap:10px; overflow-x:auto; }
    @media (max-width: 1400px) { .mw-kb-board { grid-template-columns: repeat(3, minmax(180px, 1fr)); } }
    @media (max-width: 900px) { .mw-kb-board { grid-template-columns: repeat(2, minmax(160px, 1fr)); } }
    @media (max-width: 560px) { .mw-kb-board { grid-template-columns: 1fr; } }
    .mw-kb-col { background: color-mix(in srgb, var(--surface) 88%, var(--background) 12%); border-radius:12px; border:1px solid var(--border); display:flex; flex-direction:column; min-height:160px; }
    .mw-kb-col-head { display:flex; align-items:center; gap:7px; padding:10px 12px; border-bottom:3px solid var(--col-accent, var(--border)); font-size:11px; }
    .mw-kb-col-lbl { font-weight:800; text-transform:uppercase; letter-spacing:0.06em; color: var(--text-secondary); flex:1; }
    .mw-kb-col-cnt { padding:2px 7px; border-radius:999px; background: color-mix(in srgb, var(--surface) 80%, var(--background) 20%); color: var(--text-secondary); font-size:11px; font-weight:700; border:1px solid var(--border); }
    .mw-kb-cards { display:flex; flex-direction:column; gap:7px; padding:8px; }
    .mw-kb-empty { color: var(--text-secondary); font-size:12px; text-align:center; padding:16px 0; }
    .mw-kb-card { background: var(--surface); border-radius:9px; border:1px solid var(--border); padding:10px; display:flex; flex-direction:column; gap:6px; transition:box-shadow 0.15s, transform 0.15s; }
    .mw-kb-card:hover { box-shadow: 0 4px 14px color-mix(in srgb, var(--primary) 14%, transparent); transform: translateY(-1px); }
    .mw-kb-card.has-stopper { border-color: color-mix(in srgb, var(--danger) 45%, var(--border)); background: color-mix(in srgb, var(--danger) 4%, var(--surface)); }
    .mw-kb-card.is-overdue { border-color: color-mix(in srgb, var(--warning) 50%, var(--border)); }
    .mw-blocker-tag { display:flex; align-items:flex-start; gap:5px; padding:5px 7px; border-radius:6px; background: color-mix(in srgb, var(--danger) 12%, var(--surface)); color: var(--danger); font-size:11px; font-weight:600; }
    .mw-blocker-tag svg { width:12px; height:12px; stroke:currentColor; fill:none; stroke-width:2; flex-shrink:0; margin-top:1px; }
    .mw-kb-card-title { font-size:12px; font-weight:700; color: var(--text-primary); line-height:1.4; }
    .mw-kb-card-project { font-size:11px; color: var(--primary); font-weight:600; background: color-mix(in srgb, var(--primary) 10%, var(--surface)); padding:2px 6px; border-radius:5px; display:inline-block; border:1px solid color-mix(in srgb, var(--primary) 22%, var(--border)); }
    .mw-kb-card-foot { display:flex; flex-wrap:wrap; gap:4px; align-items:center; }
    .mw-prio-pill, .mw-hours-pill, .mw-due-pill { padding:2px 6px; border-radius:5px; font-size:10px; font-weight:700; }
    .mw-prio-high { background: color-mix(in srgb, var(--danger) 14%, var(--surface)); color: var(--danger); border:1px solid color-mix(in srgb, var(--danger) 28%, var(--border)); }
    .mw-prio-medium { background: color-mix(in srgb, var(--warning) 14%, var(--surface)); color: var(--warning); border:1px solid color-mix(in srgb, var(--warning) 28%, var(--border)); }
    .mw-prio-low { background: color-mix(in srgb, var(--success) 12%, var(--surface)); color: var(--success); border:1px solid color-mix(in srgb, var(--success) 25%, var(--border)); }
    .mw-hours-pill { background: color-mix(in srgb, var(--info) 10%, var(--surface)); color: var(--info); border:1px solid color-mix(in srgb, var(--info) 22%, var(--border)); }
    .mw-due-pill { background: color-mix(in srgb, var(--surface) 80%, var(--background) 20%); color: var(--text-secondary); border:1px solid var(--border); }
    .mw-due-pill.overdue { background: color-mix(in srgb, var(--warning) 14%, var(--surface)); color: var(--warning); border-color: color-mix(in srgb, var(--warning) 30%, var(--border)); }
    .mw-kb-card-status { margin-top:2px; }
    .mw-status-sel { width:100%; padding:5px 7px; border-radius:7px; border:1px solid var(--border); background: color-mix(in srgb, var(--surface) 94%, var(--background) 6%); color: var(--text-primary); font-size:11px; font-weight:600; cursor:pointer; }
    .mw-status-sel:focus { outline:none; border-color: var(--primary); }

    /* Sidebar */
    .mw-sidebar { display:flex; flex-direction:column; gap:14px; }
    .mw-side-card { background: var(--surface); border:1px solid var(--border); border-radius:14px; padding:16px; display:flex; flex-direction:column; gap:12px; }
    .mw-side-card-head { font-size:11px; text-transform:uppercase; letter-spacing:0.06em; font-weight:800; color: var(--text-secondary); }
    .mw-side-actions { display:flex; flex-direction:column; gap:8px; }
    .mw-side-action-btn { display:flex; align-items:center; gap:9px; padding:10px 13px; border-radius:10px; border:1px solid var(--border); background: color-mix(in srgb, var(--surface) 90%, var(--background) 10%); color: var(--text-primary); text-decoration:none; font-size:13px; font-weight:600; transition:all 0.15s; }
    .mw-side-action-btn:hover { background: color-mix(in srgb, var(--primary) 12%, var(--surface)); border-color: color-mix(in srgb, var(--primary) 35%, var(--border)); color: var(--primary); transform: translateX(2px); }
    .mw-side-action-btn.primary { background: var(--primary); border-color: var(--primary); color: var(--text-primary); }
    .mw-side-action-btn.primary:hover { background: color-mix(in srgb, var(--primary) 85%, #000 15%); color: var(--text-primary); }
    .mw-side-action-btn svg { width:16px; height:16px; stroke:currentColor; fill:none; stroke-width:2; flex-shrink:0; }
    .mw-task-summary { display:flex; flex-direction:column; gap:2px; }
    .mw-task-summary-row { display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:8px; border-left:3px solid var(--row-accent, var(--border)); background: color-mix(in srgb, var(--surface) 86%, var(--background) 14%); }
    .mw-task-summary-icon { font-size:14px; }
    .mw-task-summary-lbl { flex:1; font-size:12px; font-weight:600; color: var(--text-secondary); }
    .mw-task-summary-cnt { font-size:14px; font-weight:800; color: var(--text-primary); }
    .mw-timesheet-mini { display:flex; flex-direction:column; gap:8px; }
    .mw-ts-week-label { font-size:12px; color: var(--text-secondary); font-weight:600; }
    .mw-ts-hours { display:flex; align-items:baseline; gap:6px; }
    .mw-ts-hours strong { font-size:22px; font-weight:800; color: var(--text-primary); }
    .mw-ts-hours span { font-size:13px; color: var(--text-secondary); }
    .mw-mini-track { height:8px; background: color-mix(in srgb, var(--surface) 80%, var(--background) 20%); border-radius:999px; overflow:hidden; border:1px solid var(--border); }
    .mw-mini-fill { height:100%; border-radius:999px; }
    .mw-mini-fill.cap-ok { background: var(--success); }
    .mw-mini-fill.cap-warning { background: var(--warning); }
    .mw-mini-fill.cap-danger { background: var(--danger); }
    .mw-ts-link { font-size:12px; font-weight:700; color: var(--primary); text-decoration:none; align-self:flex-start; }
    .mw-ts-link:hover { text-decoration:underline; }

    /* Toast */
    .mw-toast { position:fixed; bottom:24px; right:24px; background: var(--success); color: #fff; padding:10px 18px; border-radius:12px; font-size:13px; font-weight:700; box-shadow: 0 8px 24px color-mix(in srgb, var(--success) 40%, transparent); z-index:200; transition:opacity 0.3s; }
</style>

<script>
    (() => {
        const toast = document.getElementById('mw-toast');

        function showToast(msg) {
            if (!toast) return;
            toast.textContent = msg;
            toast.hidden = false;
            setTimeout(() => { toast.hidden = true; }, 2500);
        }

        document.querySelectorAll('.mw-status-sel').forEach(sel => {
            sel.addEventListener('change', async function () {
                const taskId = this.dataset.task;
                const newStatus = this.value;
                const prevStatus = this.dataset.current;

                try {
                    const fd = new FormData();
                    fd.append('status', newStatus);

                    const resp = await fetch(`/tasks/${taskId}/status`, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        body: fd,
                    });

                    if (resp.ok) {
                        const data = await resp.json();
                        if (data.ok) {
                            this.dataset.current = newStatus;
                            const card = this.closest('.mw-kb-card');
                            if (card) {
                                const targetCol = document.querySelector(`.mw-kb-col[data-col="${newStatus}"] .mw-kb-cards`);
                                const currentCol = document.querySelector(`.mw-kb-col[data-col="${prevStatus}"] .mw-kb-cards`);
                                if (targetCol && currentCol && targetCol !== currentCol) {
                                    currentCol.removeChild(card);
                                    targetCol.appendChild(card);
                                    updateColCount(prevStatus);
                                    updateColCount(newStatus);
                                }
                            }
                            showToast('Estado actualizado ✓');
                        } else {
                            this.value = prevStatus;
                            showToast('Error al actualizar estado');
                        }
                    } else {
                        this.value = prevStatus;
                        showToast('Sin permiso para cambiar estado');
                    }
                } catch (e) {
                    this.value = prevStatus;
                    showToast('Error de conexión');
                }
            });
        });

        function updateColCount(colKey) {
            const col = document.querySelector(`.mw-kb-col[data-col="${colKey}"]`);
            if (!col) return;
            const cnt = col.querySelector('.mw-kb-col-cnt');
            const cards = col.querySelectorAll('.mw-kb-card');
            if (cnt) cnt.textContent = cards.length;
        }
    })();
</script>
