<?php
$basePath = $basePath ?? '';
$talent = $talent ?? [];
$kanban = is_array($kanban ?? null) ? $kanban : [];
$stoppers = is_array($stoppers ?? null) ? $stoppers : [];
$talentStoppers = is_array($talentStoppers ?? null) ? $talentStoppers : [];
$timesheetSummary = is_array($timesheetSummary ?? null) ? $timesheetSummary : [];
$weeklyHours = (float) ($weeklyHours ?? 0);
$weeklyCapacity = (float) ($weeklyCapacity ?? 40);
$weekStart = $weekStart ?? date('Y-m-d');
$weekEnd = $weekEnd ?? date('Y-m-d');
$projectOptions = is_array($projectOptions ?? null) ? $projectOptions : [];

$totalTasks = 0;
$activeTasks = 0;
foreach ($kanban as $status => $tasks) {
    $totalTasks += count($tasks);
    if (!in_array($status, ['done'], true)) {
        $activeTasks += count($tasks);
    }
}

$capacityPct = $weeklyCapacity > 0 ? min(round(($weeklyHours / $weeklyCapacity) * 100, 1), 100) : 0;
$capacityColor = $capacityPct > 90 ? 'var(--danger)' : ($capacityPct > 70 ? 'var(--warning)' : 'var(--success)');

$statusMeta = [
    'todo'        => ['label' => 'Pendiente',   'icon' => '&#9711;',  'color' => 'var(--text-secondary)'],
    'in_progress' => ['label' => 'En progreso', 'icon' => '&#9654;',  'color' => 'var(--info)'],
    'review'      => ['label' => 'En revisión', 'icon' => '&#9888;',  'color' => 'var(--warning)'],
    'blocked'     => ['label' => 'Bloqueada',   'icon' => '&#9940;',  'color' => 'var(--danger)'],
    'done'        => ['label' => 'Completada',  'icon' => '&#10003;', 'color' => 'var(--success)'],
];

$priorityMeta = [
    'high'   => ['label' => 'Alta',  'class' => 'prio-high'],
    'medium' => ['label' => 'Media', 'class' => 'prio-medium'],
    'low'    => ['label' => 'Baja',  'class' => 'prio-low'],
];

$dayLabels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
$dailyHours = [];
$ts = strtotime($weekStart);
for ($i = 0; $i < 7; $i++) {
    $d = date('Y-m-d', strtotime("+$i days", $ts));
    $dailyHours[$d] = 0;
}
foreach ($timesheetSummary as $entry) {
    $d = $entry['date'] ?? '';
    if (isset($dailyHours[$d])) {
        $dailyHours[$d] += (float) ($entry['total_hours'] ?? 0);
    }
}
?>

<section class="tp-shell">
    <!-- KPI Strip -->
    <div class="tp-kpis">
        <div class="tp-kpi-card">
            <div class="tp-kpi-icon" style="color:var(--info)">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 8h7"/><path d="M9 12h7"/><path d="M9 16h5"/></svg>
            </div>
            <div class="tp-kpi-body">
                <span class="tp-kpi-value"><?= $activeTasks ?></span>
                <span class="tp-kpi-label">Tareas activas</span>
            </div>
        </div>
        <div class="tp-kpi-card">
            <div class="tp-kpi-icon" style="color:var(--warning)">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="13" r="8"/><path d="M12 13V9"/><path d="m12 13 3 2"/><path d="M9 3h6"/></svg>
            </div>
            <div class="tp-kpi-body">
                <span class="tp-kpi-value"><?= $weeklyHours ?>h</span>
                <span class="tp-kpi-label">Horas esta semana</span>
            </div>
        </div>
        <div class="tp-kpi-card">
            <div class="tp-kpi-icon" style="color:<?= $capacityColor ?>">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h16"/><rect x="6" y="12" width="3" height="6" rx="1"/><rect x="11" y="8" width="3" height="10" rx="1"/><rect x="16" y="5" width="3" height="13" rx="1"/></svg>
            </div>
            <div class="tp-kpi-body">
                <span class="tp-kpi-value"><?= $capacityPct ?>%</span>
                <span class="tp-kpi-label">Capacidad usada</span>
            </div>
        </div>
        <div class="tp-kpi-card">
            <div class="tp-kpi-icon" style="color:var(--danger)">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
            </div>
            <div class="tp-kpi-body">
                <span class="tp-kpi-value"><?= count($talentStoppers) ?></span>
                <span class="tp-kpi-label">Bloqueos activos</span>
            </div>
        </div>
    </div>

    <!-- Kanban Board -->
    <div class="tp-section">
        <div class="tp-section-header">
            <h3>Mis tareas</h3>
            <button class="btn sm" onclick="document.getElementById('create-task-form').classList.toggle('tp-hidden')">+ Nueva tarea</button>
        </div>

        <form id="create-task-form" class="tp-create-form tp-hidden" method="POST" action="<?= $basePath ?>/talent-panel/tasks/create">
            <div class="tp-form-grid">
                <label>
                    <span>Proyecto</span>
                    <select name="project_id" required>
                        <option value="">Selecciona</option>
                        <?php foreach ($projectOptions as $p): ?>
                            <option value="<?= (int) ($p['id'] ?? 0) ?>"><?= htmlspecialchars($p['name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Título</span>
                    <input type="text" name="title" required maxlength="160" placeholder="Ej. Integrar API de pagos" />
                </label>
                <label>
                    <span>Prioridad</span>
                    <select name="priority">
                        <option value="medium" selected>Media</option>
                        <option value="high">Alta</option>
                        <option value="low">Baja</option>
                    </select>
                </label>
                <label>
                    <span>Horas estimadas</span>
                    <input type="number" name="estimated_hours" min="0" step="0.5" value="0" />
                </label>
                <label>
                    <span>Fecha límite</span>
                    <input type="date" name="due_date" />
                </label>
            </div>
            <div class="tp-form-actions">
                <button type="submit" class="btn primary sm">Crear tarea</button>
                <button type="button" class="btn sm" onclick="document.getElementById('create-task-form').classList.add('tp-hidden')">Cancelar</button>
            </div>
        </form>

        <div class="tp-kanban">
            <?php foreach ($statusMeta as $statusKey => $meta): ?>
                <div class="tp-kanban-col" data-status="<?= $statusKey ?>">
                    <div class="tp-kanban-col-header" style="border-top: 3px solid <?= $meta['color'] ?>">
                        <span class="tp-kanban-col-icon"><?= $meta['icon'] ?></span>
                        <span class="tp-kanban-col-title"><?= htmlspecialchars($meta['label']) ?></span>
                        <span class="tp-kanban-col-count"><?= count($kanban[$statusKey] ?? []) ?></span>
                    </div>
                    <div class="tp-kanban-col-body" data-status="<?= $statusKey ?>">
                        <?php if (empty($kanban[$statusKey])): ?>
                            <div class="tp-kanban-empty">Sin tareas</div>
                        <?php else: ?>
                            <?php foreach ($kanban[$statusKey] as $task): ?>
                                <?php
                                $taskId = (int) ($task['id'] ?? 0);
                                $prio = $priorityMeta[$task['priority'] ?? ''] ?? ['label' => 'Media', 'class' => 'prio-medium'];
                                $hasBlocker = !empty($stoppers[$taskId]);
                                $overdue = !empty($task['due_date']) && $task['due_date'] < date('Y-m-d') && !in_array($statusKey, ['done'], true);
                                ?>
                                <div class="tp-kanban-card <?= $hasBlocker ? 'has-blocker' : '' ?> <?= $overdue ? 'overdue' : '' ?>"
                                     draggable="true" data-task-id="<?= $taskId ?>">
                                    <div class="tp-kanban-card-top">
                                        <span class="tp-prio-dot <?= $prio['class'] ?>" title="<?= htmlspecialchars($prio['label']) ?>"></span>
                                        <span class="tp-card-project"><?= htmlspecialchars($task['project'] ?? '') ?></span>
                                    </div>
                                    <div class="tp-card-title"><?= htmlspecialchars($task['title'] ?? '') ?></div>
                                    <?php if ($hasBlocker): ?>
                                        <div class="tp-card-blocker">
                                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="var(--danger)" stroke-width="2"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                                            Bloqueada
                                        </div>
                                    <?php endif; ?>
                                    <div class="tp-card-meta">
                                        <?php if (!empty($task['due_date'])): ?>
                                            <span class="<?= $overdue ? 'tp-overdue-text' : '' ?>"><?= date('d M', strtotime($task['due_date'])) ?></span>
                                        <?php endif; ?>
                                        <span><?= (float) ($task['estimated_hours'] ?? 0) ?>h est.</span>
                                    </div>
                                    <div class="tp-card-actions">
                                        <form method="POST" action="<?= $basePath ?>/talent-panel/tasks/<?= $taskId ?>/status" class="tp-status-form">
                                            <select name="status" class="tp-status-select" onchange="this.form.submit()">
                                                <?php foreach ($statusMeta as $sv => $sm): ?>
                                                    <option value="<?= $sv ?>" <?= $sv === $statusKey ? 'selected' : '' ?>><?= htmlspecialchars($sm['label']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Bottom grid: Timesheet + Capacity + Blockers -->
    <div class="tp-bottom-grid">
        <!-- Timesheet summary -->
        <div class="tp-card-section">
            <h4>Timesheet semanal</h4>
            <p class="tp-week-range"><?= date('d M', strtotime($weekStart)) ?> — <?= date('d M', strtotime($weekEnd)) ?></p>
            <div class="tp-daily-chart">
                <?php $i = 0; foreach ($dailyHours as $d => $h): ?>
                    <?php $barPct = $weeklyCapacity > 0 ? min(($h / ($weeklyCapacity / 5)) * 100, 100) : 0; ?>
                    <div class="tp-daily-bar-wrap">
                        <div class="tp-daily-bar" style="height:<?= max($barPct, 4) ?>%; background:<?= $h > 0 ? 'var(--primary)' : 'var(--border)' ?>"></div>
                        <span class="tp-daily-hours"><?= $h > 0 ? $h . 'h' : '-' ?></span>
                        <span class="tp-daily-label"><?= $dayLabels[$i] ?? '' ?></span>
                    </div>
                <?php $i++; endforeach; ?>
            </div>
            <div class="tp-ts-footer">
                <span>Total: <strong><?= $weeklyHours ?>h</strong> / <?= $weeklyCapacity ?>h</span>
                <a href="<?= $basePath ?>/timesheets" class="btn sm">Ir a Timesheet</a>
            </div>
        </div>

        <!-- Capacity -->
        <div class="tp-card-section">
            <h4>Capacidad semanal</h4>
            <div class="tp-capacity-ring-wrap">
                <svg viewBox="0 0 120 120" class="tp-capacity-ring">
                    <circle cx="60" cy="60" r="52" fill="none" stroke="var(--border)" stroke-width="10"/>
                    <circle cx="60" cy="60" r="52" fill="none" stroke="<?= $capacityColor ?>" stroke-width="10"
                        stroke-dasharray="<?= round(326.7 * ($capacityPct / 100), 1) ?> 326.7"
                        stroke-linecap="round" transform="rotate(-90 60 60)"/>
                </svg>
                <div class="tp-capacity-center">
                    <strong><?= $capacityPct ?>%</strong>
                    <span>utilizado</span>
                </div>
            </div>
            <div class="tp-capacity-details">
                <div class="tp-cap-row"><span>Registradas:</span><strong><?= $weeklyHours ?>h</strong></div>
                <div class="tp-cap-row"><span>Capacidad:</span><strong><?= $weeklyCapacity ?>h</strong></div>
                <div class="tp-cap-row"><span>Disponible:</span><strong><?= max(0, $weeklyCapacity - $weeklyHours) ?>h</strong></div>
            </div>
        </div>

        <!-- Blockers -->
        <div class="tp-card-section">
            <h4>Bloqueos activos</h4>
            <?php if (empty($talentStoppers)): ?>
                <div class="tp-no-blockers">
                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="var(--success)" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
                    <span>Sin bloqueos activos</span>
                </div>
            <?php else: ?>
                <div class="tp-blockers-list">
                    <?php foreach ($talentStoppers as $st): ?>
                        <div class="tp-blocker-item">
                            <div class="tp-blocker-top">
                                <span class="tp-impact tp-impact-<?= htmlspecialchars($st['impact_level'] ?? 'medio') ?>">
                                    <?= htmlspecialchars(ucfirst($st['impact_level'] ?? '')) ?>
                                </span>
                                <span class="tp-blocker-type"><?= htmlspecialchars(ucfirst($st['stopper_type'] ?? '')) ?></span>
                            </div>
                            <div class="tp-blocker-title"><?= htmlspecialchars($st['title'] ?? '') ?></div>
                            <div class="tp-blocker-meta">
                                <span><?= htmlspecialchars($st['project_name'] ?? '') ?></span>
                                <?php if (!empty($st['task_title'])): ?>
                                    <span> — <?= htmlspecialchars($st['task_title']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<style>
.tp-shell { display:flex; flex-direction:column; gap:20px; }
.tp-hidden { display:none !important; }
.tp-kpis { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:14px; }
.tp-kpi-card { display:flex; align-items:center; gap:14px; padding:16px 18px; border-radius:14px; border:1px solid var(--border); background:var(--surface); }
.tp-kpi-icon { width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; background: color-mix(in srgb, currentColor 12%, var(--surface)); }
.tp-kpi-body { display:flex; flex-direction:column; gap:2px; }
.tp-kpi-value { font-size:22px; font-weight:800; color:var(--text-primary); }
.tp-kpi-label { font-size:12px; color:var(--text-secondary); font-weight:600; }

.tp-section { display:flex; flex-direction:column; gap:12px; }
.tp-section-header { display:flex; justify-content:space-between; align-items:center; }
.tp-section-header h3 { margin:0; font-size:18px; }

.tp-create-form { border:1px dashed var(--border); border-radius:14px; padding:16px; background:color-mix(in srgb, var(--surface) 90%, var(--background)); }
.tp-form-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; }
.tp-form-grid label { display:flex; flex-direction:column; gap:4px; font-size:12px; font-weight:700; color:var(--text-primary); }
.tp-form-grid input, .tp-form-grid select { padding:8px 10px; border-radius:10px; border:1px solid var(--border); background:var(--surface); color:var(--text-primary); font-size:13px; }
.tp-form-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:10px; }

.tp-kanban { display:grid; grid-template-columns: repeat(5, 1fr); gap:12px; min-height:320px; }
.tp-kanban-col { display:flex; flex-direction:column; border-radius:14px; border:1px solid var(--border); background:color-mix(in srgb, var(--surface) 70%, var(--background)); overflow:hidden; }
.tp-kanban-col-header { display:flex; align-items:center; gap:8px; padding:12px 14px; font-size:13px; font-weight:700; background:color-mix(in srgb, var(--surface) 92%, var(--background)); }
.tp-kanban-col-icon { font-size:14px; }
.tp-kanban-col-title { flex:1; }
.tp-kanban-col-count { background:var(--border); border-radius:999px; padding:2px 8px; font-size:11px; font-weight:700; }
.tp-kanban-col-body { flex:1; display:flex; flex-direction:column; gap:8px; padding:10px; min-height:60px; }
.tp-kanban-col-body.drag-over { background:color-mix(in srgb, var(--primary) 8%, var(--background)); }
.tp-kanban-empty { text-align:center; color:var(--text-secondary); font-size:12px; padding:16px 0; }

.tp-kanban-card { border:1px solid var(--border); border-radius:12px; padding:12px; background:var(--surface); cursor:grab; transition: box-shadow .15s, border-color .15s; }
.tp-kanban-card:hover { border-color: color-mix(in srgb, var(--primary) 40%, var(--border)); box-shadow:0 6px 16px color-mix(in srgb, var(--primary) 12%, transparent); }
.tp-kanban-card.has-blocker { border-left:3px solid var(--danger); }
.tp-kanban-card.overdue { border-color: color-mix(in srgb, var(--danger) 50%, var(--border)); }
.tp-kanban-card.dragging { opacity:0.5; }
.tp-kanban-card-top { display:flex; align-items:center; gap:6px; margin-bottom:6px; }
.tp-prio-dot { width:8px; height:8px; border-radius:50%; }
.prio-high { background:var(--danger); }
.prio-medium { background:var(--warning); }
.prio-low { background:var(--success); }
.tp-card-project { font-size:11px; color:var(--text-secondary); font-weight:600; }
.tp-card-title { font-size:13px; font-weight:700; color:var(--text-primary); margin-bottom:6px; line-height:1.35; }
.tp-card-blocker { display:flex; align-items:center; gap:4px; font-size:11px; font-weight:700; color:var(--danger); margin-bottom:4px; }
.tp-card-meta { display:flex; gap:8px; font-size:11px; color:var(--text-secondary); }
.tp-overdue-text { color:var(--danger); font-weight:700; }
.tp-card-actions { margin-top:8px; }
.tp-status-form { display:flex; }
.tp-status-select { padding:4px 6px; border-radius:8px; border:1px solid var(--border); background:var(--surface); color:var(--text-primary); font-size:11px; width:100%; cursor:pointer; }

.tp-bottom-grid { display:grid; grid-template-columns: repeat(3, 1fr); gap:16px; }
.tp-card-section { border:1px solid var(--border); border-radius:14px; padding:18px; background:var(--surface); display:flex; flex-direction:column; gap:12px; }
.tp-card-section h4 { margin:0; font-size:15px; font-weight:700; }
.tp-week-range { margin:0; font-size:12px; color:var(--text-secondary); }

.tp-daily-chart { display:flex; gap:6px; align-items:flex-end; height:120px; padding:8px 0; }
.tp-daily-bar-wrap { flex:1; display:flex; flex-direction:column; align-items:center; gap:4px; height:100%; justify-content:flex-end; }
.tp-daily-bar { width:100%; max-width:32px; border-radius:6px 6px 2px 2px; transition: height .3s; }
.tp-daily-hours { font-size:10px; font-weight:700; color:var(--text-primary); }
.tp-daily-label { font-size:10px; color:var(--text-secondary); font-weight:600; }
.tp-ts-footer { display:flex; justify-content:space-between; align-items:center; font-size:13px; color:var(--text-secondary); border-top:1px solid var(--border); padding-top:10px; }

.tp-capacity-ring-wrap { position:relative; width:120px; height:120px; margin:0 auto; }
.tp-capacity-ring { width:100%; height:100%; }
.tp-capacity-center { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; }
.tp-capacity-center strong { font-size:20px; font-weight:800; color:var(--text-primary); }
.tp-capacity-center span { font-size:11px; color:var(--text-secondary); }
.tp-capacity-details { display:flex; flex-direction:column; gap:6px; }
.tp-cap-row { display:flex; justify-content:space-between; font-size:13px; color:var(--text-secondary); }
.tp-cap-row strong { color:var(--text-primary); }

.tp-no-blockers { display:flex; flex-direction:column; align-items:center; gap:8px; padding:20px 0; color:var(--success); font-size:13px; font-weight:600; }
.tp-blockers-list { display:flex; flex-direction:column; gap:8px; max-height:280px; overflow-y:auto; }
.tp-blocker-item { border:1px solid var(--border); border-radius:10px; padding:10px 12px; background:color-mix(in srgb, var(--surface) 94%, var(--background)); }
.tp-blocker-top { display:flex; align-items:center; gap:8px; margin-bottom:4px; }
.tp-impact { padding:2px 8px; border-radius:999px; font-size:10px; font-weight:700; }
.tp-impact-critico { background:color-mix(in srgb, var(--danger) 18%, var(--surface)); color:var(--danger); }
.tp-impact-alto { background:color-mix(in srgb, var(--warning) 18%, var(--surface)); color:var(--warning); }
.tp-impact-medio { background:color-mix(in srgb, var(--info) 18%, var(--surface)); color:var(--info); }
.tp-impact-bajo { background:color-mix(in srgb, var(--success) 18%, var(--surface)); color:var(--success); }
.tp-blocker-type { font-size:11px; color:var(--text-secondary); }
.tp-blocker-title { font-size:13px; font-weight:700; color:var(--text-primary); }
.tp-blocker-meta { font-size:11px; color:var(--text-secondary); margin-top:4px; }

@media (max-width: 1200px) {
    .tp-kanban { grid-template-columns: repeat(3, 1fr); }
    .tp-bottom-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 900px) {
    .tp-kanban { grid-template-columns: repeat(2, 1fr); }
    .tp-bottom-grid { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
    .tp-kanban { grid-template-columns: 1fr; }
}
</style>

<script>
(() => {
    const cols = document.querySelectorAll('.tp-kanban-col-body');
    const cards = document.querySelectorAll('.tp-kanban-card[draggable]');

    cards.forEach(card => {
        card.addEventListener('dragstart', e => {
            card.classList.add('dragging');
            e.dataTransfer.setData('text/plain', card.dataset.taskId);
            e.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragend', () => card.classList.remove('dragging'));
    });

    cols.forEach(col => {
        col.addEventListener('dragover', e => { e.preventDefault(); col.classList.add('drag-over'); });
        col.addEventListener('dragleave', () => col.classList.remove('drag-over'));
        col.addEventListener('drop', e => {
            e.preventDefault();
            col.classList.remove('drag-over');
            const taskId = e.dataTransfer.getData('text/plain');
            const newStatus = col.dataset.status;
            if (!taskId || !newStatus) return;

            fetch(`<?= $basePath ?>/api/talent-panel/tasks/${taskId}/status`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ status: newStatus })
            }).then(r => r.json()).then(data => {
                if (data.ok) location.reload();
            });
        });
    });
})();
</script>
