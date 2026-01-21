<?php
$basePath = $basePath ?? '/project/public';
$tasks = is_array($tasks ?? null) ? $tasks : [];
$canManageTasks = isset($auth) ? $auth->can('projects.manage') : false;

$statusMeta = [
    'todo' => ['label' => 'Pendiente', 'icon' => '⏳', 'class' => 'status-muted'],
    'in_progress' => ['label' => 'En progreso', 'icon' => '🔄', 'class' => 'status-info'],
    'review' => ['label' => 'En revisión', 'icon' => '⚠️', 'class' => 'status-warning'],
    'blocked' => ['label' => 'Bloqueada', 'icon' => '⛔', 'class' => 'status-danger'],
    'done' => ['label' => 'Completada', 'icon' => '✅', 'class' => 'status-success'],
];

$priorityMeta = [
    'high' => ['label' => 'Alta', 'class' => 'status-danger'],
    'medium' => ['label' => 'Media', 'class' => 'status-warning'],
    'low' => ['label' => 'Baja', 'class' => 'status-success'],
];

$projectOptions = [];
$assigneeOptions = [];
$statusOptions = [];
$priorityOptions = [];
foreach ($tasks as $task) {
    $projectName = (string) ($task['project'] ?? '');
    $assigneeName = (string) ($task['assignee'] ?? '');
    $status = (string) ($task['status'] ?? '');
    $priority = (string) ($task['priority'] ?? '');
    if ($projectName !== '') {
        $projectOptions[$projectName] = $projectName;
    }
    if ($assigneeName === '') {
        $assigneeName = 'Sin asignar';
    }
    $assigneeOptions[$assigneeName] = $assigneeName;
    if ($status !== '') {
        $statusOptions[$status] = $status;
    }
    if ($priority !== '') {
        $priorityOptions[$priority] = $priority;
    }
}
ksort($projectOptions);
ksort($assigneeOptions);
ksort($statusOptions);
ksort($priorityOptions);
?>

<section class="tasks-shell">
    <header class="tasks-header">
        <div>
            <h2>Gestión de tareas</h2>
            <p class="section-muted">Listado operativo con horas estimadas y reales por proyecto.</p>
        </div>
    </header>

    <div class="filters-bar">
        <label>Proyecto
            <select data-filter="project">
                <option value="">Todos</option>
                <?php foreach ($projectOptions as $project): ?>
                    <option value="<?= htmlspecialchars($project) ?>"><?= htmlspecialchars($project) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Estado
            <select data-filter="status">
                <option value="">Todos</option>
                <?php foreach ($statusOptions as $statusKey): ?>
                    <option value="<?= htmlspecialchars($statusKey) ?>"><?= htmlspecialchars($statusMeta[$statusKey]['label'] ?? $statusKey) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Asignado
            <select data-filter="assignee">
                <option value="">Todos</option>
                <?php foreach ($assigneeOptions as $assignee): ?>
                    <option value="<?= htmlspecialchars($assignee) ?>"><?= htmlspecialchars($assignee) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Prioridad
            <select data-filter="priority">
                <option value="">Todas</option>
                <?php foreach ($priorityOptions as $priorityKey): ?>
                    <option value="<?= htmlspecialchars($priorityKey) ?>"><?= htmlspecialchars($priorityMeta[$priorityKey]['label'] ?? $priorityKey) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <?php if (empty($tasks)): ?>
        <div class="empty-state">
            <span>📌</span>
            <div>
                <strong>No hay tareas registradas.</strong>
                <p class="section-muted">Crea tareas desde los proyectos para comenzar a reportar horas.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="tasks-table">
            <div class="tasks-row header">
                <div>Tarea</div>
                <div>Proyecto</div>
                <div>Talento</div>
                <div>Estado</div>
                <div>Prioridad</div>
                <div>Horas</div>
                <div>Acciones</div>
            </div>
            <?php foreach ($tasks as $task): ?>
                <?php
                $statusKey = $task['status'] ?? '';
                $status = $statusMeta[$statusKey] ?? ['label' => ucfirst(str_replace('_', ' ', (string) $statusKey)), 'icon' => '📍', 'class' => 'status-muted'];
                $priorityKey = $task['priority'] ?? '';
                $priority = $priorityMeta[$priorityKey] ?? ['label' => 'Media', 'class' => 'status-muted'];
                $projectName = (string) ($task['project'] ?? '');
                $assigneeName = (string) ($task['assignee'] ?? 'Sin asignar');
                ?>
                <div class="tasks-row"
                     data-project="<?= htmlspecialchars(strtolower($projectName)) ?>"
                     data-status="<?= htmlspecialchars(strtolower((string) $statusKey)) ?>"
                     data-assignee="<?= htmlspecialchars(strtolower($assigneeName)) ?>"
                     data-priority="<?= htmlspecialchars(strtolower((string) $priorityKey)) ?>">
                    <div class="task-main">
                        <strong class="truncate"><?= htmlspecialchars($task['title'] ?? '') ?></strong>
                        <div class="meta-line">Fase: <?= htmlspecialchars($task['project_phase'] ?? 'Sin fase') ?></div>
                    </div>
                    <div class="truncate"><?= htmlspecialchars($projectName) ?></div>
                    <div class="truncate"><?= htmlspecialchars($assigneeName) ?></div>
                    <div>
                        <span class="badge <?= $status['class'] ?>">
                            <?= htmlspecialchars($status['icon']) ?> <?= htmlspecialchars($status['label']) ?>
                        </span>
                    </div>
                    <div>
                        <span class="badge <?= $priority['class'] ?>"><?= htmlspecialchars($priority['label']) ?></span>
                    </div>
                    <div class="hours-stack">
                        <span><?= htmlspecialchars((string) ($task['estimated_hours'] ?? 0)) ?>h est.</span>
                        <span><?= htmlspecialchars((string) ($task['actual_hours'] ?? 0)) ?>h real</span>
                    </div>
                    <div class="actions">
                        <a class="action-btn small" href="<?= $basePath ?>/projects/<?= (int) ($task['project_id'] ?? 0) ?>/tasks">Ver</a>
                        <?php if ($canManageTasks): ?>
                            <a class="action-btn small ghost" href="<?= $basePath ?>/tasks/<?= (int) ($task['id'] ?? 0) ?>/edit">Editar</a>
                            <form method="POST" action="<?= $basePath ?>/tasks/<?= (int) ($task['id'] ?? 0) ?>/status" class="status-form">
                                <select name="status" aria-label="Actualizar estado">
                                    <?php foreach ($statusMeta as $value => $meta): ?>
                                        <option value="<?= htmlspecialchars($value) ?>" <?= $value === $statusKey ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($meta['label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="action-btn small primary">Marcar</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<style>
    .tasks-shell { display:flex; flex-direction:column; gap:16px; }
    .tasks-header { display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .tasks-header h2 { margin:0; }
    .section-muted { color: var(--text-secondary); margin:0; font-size:13px; }
    .empty-state { border:1px dashed var(--border); border-radius:12px; padding:16px; background: color-mix(in srgb, var(--surface) 84%, var(--background) 16%); display:flex; align-items:flex-start; gap:12px; }
    .filters-bar { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:10px; padding:12px; border:1px solid var(--border); border-radius:14px; background: var(--surface); }
    .filters-bar label { display:flex; flex-direction:column; gap:6px; font-weight:600; color: var(--text-primary); font-size:12px; }
    .filters-bar select { padding:8px 10px; border-radius:10px; border:1px solid var(--border); background: var(--surface); color: var(--text-primary); font-size:13px; }
    .tasks-table { border:1px solid var(--border); border-radius:16px; overflow:hidden; background: var(--surface); }
    .tasks-row { display:grid; grid-template-columns: 2fr 1.4fr 1.2fr 1fr 1fr 1fr 2fr; gap:12px; padding:12px 16px; align-items:center; border-top:1px solid var(--border); font-size:13px; line-height:1.4; }
    .tasks-row.header { background: color-mix(in srgb, var(--surface) 80%, var(--background) 20%); font-weight:700; border-top:none; text-transform:uppercase; font-size:11px; letter-spacing:0.04em; color: var(--text-secondary); }
    .task-main { display:flex; flex-direction:column; gap:4px; }
    .meta-line { font-size:12px; color: var(--text-secondary); }
    .badge { padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; display:inline-flex; align-items:center; gap:6px; }
    .status-muted { background: color-mix(in srgb, var(--surface) 80%, var(--border) 20%); color: var(--text-secondary); }
    .status-info { background: color-mix(in srgb, var(--accent) 18%, var(--surface) 82%); color: var(--text-primary); }
    .status-warning { background: color-mix(in srgb, var(--warning) 24%, var(--surface) 76%); color: var(--text-primary); }
    .status-success { background: color-mix(in srgb, var(--success) 24%, var(--surface) 76%); color: var(--text-primary); }
    .status-danger { background: color-mix(in srgb, var(--danger) 22%, var(--surface) 78%); color: var(--text-primary); }
    .truncate { max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .hours-stack { display:flex; flex-direction:column; gap:2px; font-size:12px; color: var(--text-secondary); }
    .actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .action-btn { background: var(--surface); color: var(--text-primary); border:1px solid var(--border); border-radius:8px; padding:6px 8px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; font-size:12px; }
    .action-btn.primary { background: var(--primary); color: var(--text-primary); border-color: var(--primary); }
    .action-btn.ghost { background: color-mix(in srgb, var(--surface) 86%, var(--background) 14%); }
    .status-form { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
    .status-form select { padding:6px 8px; border-radius:8px; border:1px solid var(--border); background: var(--surface); color: var(--text-primary); font-size:12px; }
    @media (max-width: 1100px) {
        .tasks-row { grid-template-columns: 1fr; row-gap:8px; }
        .tasks-row.header { display:none; }
        .truncate { max-width:100%; white-space:normal; overflow-wrap:anywhere; }
    }
</style>

<script>
    (() => {
        const filters = document.querySelectorAll('[data-filter]');
        const rows = document.querySelectorAll('.tasks-row:not(.header)');

        const normalize = value => (value || '').toString().toLowerCase();

        const applyFilters = () => {
            const active = {};
            filters.forEach(filter => {
                const key = filter.getAttribute('data-filter');
                active[key] = normalize(filter.value);
            });

            rows.forEach(row => {
                const matches = Object.entries(active).every(([key, value]) => {
                    if (!value) return true;
                    const rowValue = normalize(row.dataset[key]);
                    return rowValue === value;
                });
                row.style.display = matches ? '' : 'none';
            });
        };

        filters.forEach(filter => filter.addEventListener('change', applyFilters));
    })();
</script>
