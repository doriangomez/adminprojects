<?php
$basePath = $basePath ?? '';
$kanban = is_array($kanban ?? null) ? $kanban : [];
$stoppersByTask = is_array($stoppersByTask ?? null) ? $stoppersByTask : [];
$projectStoppers = is_array($projectStoppers ?? null) ? $projectStoppers : [];
$weeklyGrid = is_array($weeklyGrid ?? null) ? $weeklyGrid : [];
$weekIndicators = is_array($weekIndicators ?? null) ? $weekIndicators : [];
$weekStart = $weekStart ?? new DateTimeImmutable('monday this week');
$weekEnd = $weekEnd ?? $weekStart->modify('+6 days');
$weekValue = $weekValue ?? $weekStart->format('o-\\WW');
$projectsForTimesheet = is_array($projectsForTimesheet ?? null) ? $projectsForTimesheet : [];
$tasksForTimesheet = is_array($tasksForTimesheet ?? null) ? $tasksForTimesheet : [];
$activityTypes = is_array($activityTypes ?? null) ? $activityTypes : [];
$workloadByTalent = is_array($workloadByTalent ?? null) ? $workloadByTalent : [];
$projectOptions = is_array($projectOptions ?? null) ? $projectOptions : [];
$talents = is_array($talents ?? null) ? $talents : [];

$statusMeta = [
    'todo' => ['label' => 'Pendiente', 'icon' => '⏳', 'class' => 'status-muted'],
    'pending' => ['label' => 'Pendiente', 'icon' => '⏳', 'class' => 'status-muted'],
    'in_progress' => ['label' => 'En proceso', 'icon' => '🔄', 'class' => 'status-info'],
    'review' => ['label' => 'En revisión', 'icon' => '⚠️', 'class' => 'status-warning'],
    'blocked' => ['label' => 'Bloqueado', 'icon' => '⛔', 'class' => 'status-danger'],
    'done' => ['label' => 'Completado', 'icon' => '✅', 'class' => 'status-success'],
    'completed' => ['label' => 'Completado', 'icon' => '✅', 'class' => 'status-success'],
];

$priorityMeta = [
    'high' => ['label' => 'Alta', 'class' => 'priority-high'],
    'medium' => ['label' => 'Media', 'class' => 'priority-medium'],
    'low' => ['label' => 'Baja', 'class' => 'priority-low'],
];

$redirectUrl = $basePath . '/work-panel';
$redirectParams = [];
if ($isPmo && ($projectFilter ?? 0) > 0) $redirectParams['project_id'] = (int) $projectFilter;
if (!empty($weekValue)) $redirectParams['week'] = $weekValue;
if (!empty($redirectParams)) $redirectUrl .= '?' . http_build_query($redirectParams);

$kanbanColumns = [
    'todo' => ['label' => 'Pendiente', 'icon' => '⏳'],
    'in_progress' => ['label' => 'En proceso', 'icon' => '🔄'],
    'blocked' => ['label' => 'Bloqueado', 'icon' => '⛔'],
    'review' => ['label' => 'En revisión', 'icon' => '⚠️'],
    'done' => ['label' => 'Completado', 'icon' => '✅'],
];
?>

<section class="work-panel">
    <header class="work-panel-header">
        <div>
            <h2>Panel de trabajo del talento</h2>
            <p class="section-muted">Kanban de tareas, timesheet y bloqueos en una sola vista.</p>
        </div>
    </header>

    <div class="work-panel-grid">
        <aside class="work-panel-sidebar">
            <?php if ($timesheetsEnabled && $canReport): ?>
                <article class="card work-panel-card">
                    <h3>Capacidad semanal</h3>
                    <div class="capacity-summary">
                        <div class="capacity-row">
                            <span>Horas registradas</span>
                            <strong><?= round((float) ($weekIndicators['week_total'] ?? 0), 2) ?>h</strong>
                        </div>
                        <div class="capacity-row">
                            <span>Capacidad</span>
                            <strong><?= round((float) ($weekIndicators['weekly_capacity'] ?? 40), 2) ?>h</strong>
                        </div>
                        <div class="capacity-row">
                            <span>Restante</span>
                            <strong><?= round((float) ($weekIndicators['remaining_capacity'] ?? 0), 2) ?>h</strong>
                        </div>
                        <div class="capacity-bar-wrap">
                            <div class="capacity-bar" style="width: <?= min(100, (float) ($weekIndicators['compliance_percent'] ?? 0)) ?>%"></div>
                        </div>
                        <a href="<?= $basePath ?>/timesheets?week=<?= urlencode($weekValue) ?>" class="btn sm">Ver timesheet completo</a>
                    </div>
                </article>

                <article class="card work-panel-card">
                    <h3>Registro rápido</h3>
                    <form method="GET" action="<?= $basePath ?>/work-panel" class="week-form">
                        <label>Semana
                            <input type="week" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                        </label>
                        <?php if ($isPmo && $projectFilter): ?>
                            <input type="hidden" name="project_id" value="<?= (int) $projectFilter ?>">
                        <?php endif; ?>
                        <button type="submit" class="btn sm">Cambiar</button>
                    </form>
                    <p class="section-muted">Semana: <?= htmlspecialchars($weekStart->format('d/m')) ?> - <?= htmlspecialchars($weekEnd->format('d/m')) ?></p>
                    <a href="<?= $basePath ?>/timesheets?week=<?= urlencode($weekValue) ?>" class="btn primary">+ Registrar actividad</a>
                </article>
            <?php endif; ?>

            <?php if ($isPmo && !empty($workloadByTalent)): ?>
                <article class="card work-panel-card">
                    <h3>Carga del equipo</h3>
                    <ul class="workload-list">
                        <?php foreach (array_slice($workloadByTalent, 0, 8) as $w): ?>
                            <li>
                                <strong><?= htmlspecialchars($w['talent_name'] ?? 'Talento') ?></strong>
                                <span><?= (int) ($w['task_count'] ?? 0) ?> tareas · <?= round((float) ($w['estimated_hours'] ?? 0), 1) ?>h est.</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            <?php endif; ?>

            <?php if (!empty($projectStoppers) && !$isPmo): ?>
                <article class="card work-panel-card blockers-card">
                    <h3>⚠ Bloqueos en mis proyectos</h3>
                    <?php
                    $activeStoppers = [];
                    foreach ($projectStoppers as $stoppers) {
                        foreach ($stoppers as $s) {
                            if (!in_array((string) ($s['status'] ?? ''), ['cerrado'], true)) {
                                $activeStoppers[] = $s;
                            }
                        }
                    }
                    $activeStoppers = array_slice($activeStoppers, 0, 5);
                    ?>
                    <?php if (empty($activeStoppers)): ?>
                        <p class="section-muted">Sin bloqueos activos.</p>
                    <?php else: ?>
                        <ul class="blockers-list">
                            <?php foreach ($activeStoppers as $s): ?>
                                <li>
                                    <strong><?= htmlspecialchars($s['title'] ?? 'Bloqueo') ?></strong>
                                    <span class="badge"><?= htmlspecialchars(ucfirst($s['impact_level'] ?? '')) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>
            <?php endif; ?>
        </aside>

        <main class="work-panel-main">
            <?php if ($isPmo && $canManageTasks): ?>
                <div class="create-task-row card">
                    <h4>Nueva tarea</h4>
                    <form method="POST" action="<?= $basePath ?>/tasks/create" class="create-task-form">
                        <label>Proyecto <select name="project_id" required>
                            <option value="">Selecciona proyecto</option>
                            <?php foreach ($projectOptions as $p): ?>
                                <option value="<?= (int) ($p['id'] ?? 0) ?>"><?= htmlspecialchars($p['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select></label>
                        <label>Título <input type="text" name="title" required maxlength="180" placeholder="Ej. Integrar API"></label>
                        <label>Asignar a <select name="assignee_id">
                            <option value="0">Sin asignar</option>
                            <?php foreach ($talents as $t): ?>
                                <option value="<?= (int) ($t['id'] ?? 0) ?>"><?= htmlspecialchars($t['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select></label>
                        <label>Prioridad <select name="priority">
                            <option value="medium">Media</option>
                            <option value="high">Alta</option>
                            <option value="low">Baja</option>
                        </select></label>
                        <label>Horas est. <input type="number" name="estimated_hours" min="0" step="0.5" value="0"></label>
                        <label>Fecha límite <input type="date" name="due_date"></label>
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectUrl) ?>">
                        <button type="submit" class="btn primary">Crear tarea</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($isPmo): ?>
                <div class="filters-row card">
                    <form method="GET" class="filters-form">
                        <label>Proyecto
                            <select name="project_id">
                                <option value="">Todos los proyectos</option>
                                <?php foreach ($projectOptions as $p): ?>
                                    <option value="<?= (int) ($p['id'] ?? 0) ?>" <?= ($projectFilter ?? 0) === (int) ($p['id'] ?? 0) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['name'] ?? '') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                        <button type="submit" class="btn sm">Filtrar</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="kanban-wrapper">
                <h3><?= $isPmo ? 'Kanban de tareas (por proyecto)' : 'Mis tareas' ?></h3>
                <div class="kanban-board">
                    <?php foreach ($kanbanColumns as $colKey => $colMeta): ?>
                        <?php $tasks = $kanban[$colKey] ?? []; ?>
                        <article class="kanban-column" data-column="<?= htmlspecialchars($colKey) ?>">
                            <header class="kanban-column-header">
                                <span><?= $colMeta['icon'] ?> <?= $colMeta['label'] ?></span>
                                <strong><?= count($tasks) ?></strong>
                            </header>
                            <div class="kanban-column-body">
                                <?php if (empty($tasks)): ?>
                                    <p class="kanban-empty">Sin tareas</p>
                                <?php else: ?>
                                    <?php foreach ($tasks as $task): ?>
                                        <?php
                                        $taskId = (int) ($task['id'] ?? 0);
                                        $taskStoppers = $stoppersByTask[$taskId] ?? [];
                                        $hasStopper = !empty($taskStoppers);
                                        $priorityKey = (string) ($task['priority'] ?? 'medium');
                                        $priority = $priorityMeta[$priorityKey] ?? $priorityMeta['medium'];
                                        ?>
                                        <div class="kanban-card<?= $hasStopper ? ' has-stopper' : '' ?>" data-task-id="<?= $taskId ?>">
                                            <?php if ($hasStopper): ?>
                                                <div class="kanban-card-stopper">⚠ Bloqueado</div>
                                            <?php endif; ?>
                                            <div class="kanban-card-title"><?= htmlspecialchars($task['title'] ?? '') ?></div>
                                            <div class="kanban-card-meta">
                                                <span class="project-tag"><?= htmlspecialchars($task['project'] ?? '') ?></span>
                                                <?php if ($isPmo && !empty($task['assignee'])): ?>
                                                    <span class="assignee-tag"><?= htmlspecialchars($task['assignee']) ?></span>
                                                <?php endif; ?>
                                                <span class="priority-badge <?= $priority['class'] ?>"><?= $priority['label'] ?></span>
                                            </div>
                                            <div class="kanban-card-hours">
                                                <?= round((float) ($task['estimated_hours'] ?? 0), 1) ?>h est. · <?= round((float) ($task['actual_hours'] ?? 0), 1) ?>h real
                                            </div>
                                            <?php if (!empty($task['due_date'])): ?>
                                                <div class="kanban-card-due">📅 <?= htmlspecialchars($task['due_date']) ?></div>
                                            <?php endif; ?>
                                            <form method="POST" action="<?= $basePath ?>/tasks/<?= $taskId ?>/status" class="kanban-status-form">
                                                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectUrl) ?>">
                                                <select name="status" aria-label="Cambiar estado">
                                                    <option value="todo" <?= in_array($task['status'] ?? '', ['todo', 'pending']) ? 'selected' : '' ?>>Pendiente</option>
                                                    <option value="in_progress" <?= ($task['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>En proceso</option>
                                                    <option value="blocked" <?= ($task['status'] ?? '') === 'blocked' ? 'selected' : '' ?>>Bloqueado</option>
                                                    <option value="review" <?= ($task['status'] ?? '') === 'review' ? 'selected' : '' ?>>En revisión</option>
                                                    <option value="done" <?= in_array($task['status'] ?? '', ['done', 'completed']) ? 'selected' : '' ?>>Completado</option>
                                                </select>
                                                <button type="submit" class="btn xs">Actualizar</button>
                                            </form>
                                            <?php if ($canManageTasks): ?>
                                                <a href="<?= $basePath ?>/tasks/<?= $taskId ?>/edit" class="kanban-card-edit">Editar</a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
</section>

<style>
.work-panel { display: flex; flex-direction: column; gap: 16px; }
.work-panel-header h2 { margin: 0 0 4px; }
.section-muted { color: var(--text-secondary); margin: 0; font-size: 13px; }
.work-panel-grid { display: grid; grid-template-columns: 280px 1fr; gap: 20px; align-items: start; }
.work-panel-sidebar { display: flex; flex-direction: column; gap: 14px; }
.work-panel-card { padding: 14px; }
.work-panel-card h3 { margin: 0 0 10px; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary); }
.capacity-summary { display: flex; flex-direction: column; gap: 8px; }
.capacity-row { display: flex; justify-content: space-between; }
.capacity-bar-wrap { height: 8px; background: color-mix(in srgb, var(--border) 60%, var(--surface)); border-radius: 999px; overflow: hidden; }
.capacity-bar { height: 100%; background: linear-gradient(90deg, var(--primary), var(--accent)); border-radius: 999px; transition: width 0.3s ease; }
.week-form { display: flex; flex-direction: column; gap: 8px; }
.workload-list { margin: 0; padding: 0; list-style: none; }
.workload-list li { padding: 8px 0; border-bottom: 1px solid var(--border); display: flex; flex-direction: column; gap: 2px; }
.workload-list li:last-child { border-bottom: none; }
.blockers-list { margin: 0; padding: 0; list-style: none; }
.blockers-list li { padding: 8px 0; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.blockers-list li:last-child { border-bottom: none; }
.create-task-row { padding: 14px; margin-bottom: 12px; }
.create-task-row h4 { margin: 0 0 10px; font-size: 14px; }
.create-task-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; align-items: end; }
.create-task-form label { display: flex; flex-direction: column; gap: 4px; font-size: 12px; }
.create-task-form input, .create-task-form select { padding: 8px 10px; }
.filters-row { padding: 12px; }
.filters-form { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
.filters-form label { display: flex; flex-direction: column; gap: 4px; font-size: 12px; }
.filters-form select { min-width: 200px; }
.kanban-wrapper { display: flex; flex-direction: column; gap: 12px; }
.kanban-wrapper h3 { margin: 0; font-size: 16px; }
.kanban-board { display: grid; grid-template-columns: repeat(5, minmax(200px, 1fr)); gap: 12px; overflow-x: auto; padding-bottom: 8px; }
.kanban-column { background: color-mix(in srgb, var(--surface) 96%, var(--background)); border: 1px solid var(--border); border-radius: 12px; min-width: 200px; display: flex; flex-direction: column; }
.kanban-column-header { padding: 10px 12px; background: color-mix(in srgb, var(--surface) 90%, var(--background)); border-bottom: 1px solid var(--border); border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center; font-size: 13px; }
.kanban-column-body { padding: 10px; flex: 1; display: flex; flex-direction: column; gap: 10px; max-height: 70vh; overflow-y: auto; }
.kanban-empty { margin: 0; padding: 12px; color: var(--text-secondary); font-size: 13px; text-align: center; }
.kanban-card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 12px; position: relative; display: flex; flex-direction: column; gap: 8px; }
.kanban-card.has-stopper { border-left: 4px solid var(--danger); }
.kanban-card-stopper { font-size: 11px; font-weight: 700; color: var(--danger); }
.kanban-card-title { font-weight: 700; font-size: 14px; line-height: 1.3; }
.kanban-card-meta { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.project-tag, .assignee-tag { font-size: 11px; color: var(--text-secondary); }
.priority-badge { font-size: 10px; padding: 2px 6px; border-radius: 999px; }
.priority-high { background: color-mix(in srgb, var(--danger) 20%, var(--surface)); color: var(--danger); }
.priority-medium { background: color-mix(in srgb, var(--warning) 20%, var(--surface)); color: var(--warning); }
.priority-low { background: color-mix(in srgb, var(--success) 20%, var(--surface)); color: var(--success); }
.kanban-card-hours { font-size: 12px; color: var(--text-secondary); }
.kanban-card-due { font-size: 11px; color: var(--text-secondary); }
.kanban-status-form { display: flex; gap: 6px; align-items: center; margin-top: 4px; }
.kanban-status-form select { padding: 6px 8px; font-size: 12px; flex: 1; }
.btn { padding: 8px 12px; border-radius: 10px; border: 1px solid var(--border); background: var(--surface); cursor: pointer; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
.btn.sm { padding: 6px 10px; font-size: 12px; }
.btn.xs { padding: 4px 8px; font-size: 11px; }
.btn.primary { background: var(--primary); border-color: var(--primary); color: var(--text-primary); }
.kanban-card-edit { font-size: 11px; color: var(--primary); text-decoration: none; }
.kanban-card-edit:hover { text-decoration: underline; }
@media (max-width: 1100px) {
    .work-panel-grid { grid-template-columns: 1fr; }
    .kanban-board { grid-template-columns: repeat(2, minmax(180px, 1fr)); }
}
@media (max-width: 720px) {
    .kanban-board { grid-template-columns: 1fr; }
}
</style>

<script>
(() => {
    const forms = document.querySelectorAll('.kanban-status-form');
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            const btn = form.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;
        });
    });
})();
</script>
