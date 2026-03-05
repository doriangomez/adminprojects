<?php
$basePath = $basePath ?? '';
$tasks = is_array($tasks ?? null) ? $tasks : [];
$canManageTasks = !empty($canManage) || (isset($auth) ? $auth->can('projects.manage') : false);
$projectOptionsForCreate = is_array($projectOptions ?? null) ? $projectOptions : [];
$talents = is_array($talents ?? null) ? $talents : [];

$svgIcons = [
    'hourglass' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2v4a6 6 0 0 0 6 6 6 6 0 0 0 6-6V2"/><path d="M6 22v-4a6 6 0 0 1 6-6 6 6 0 0 1 6 6v4"/><path d="M6 2h12M6 22h12"/></svg>',
    'refresh' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-2.636-6.364"/><path d="M21 3v6h-6"/></svg>',
    'eye' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
    'shield' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>',
    'check_circle' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
    'pin' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
];
$statusMeta = [
    'todo' => ['label' => 'Pendiente', 'icon' => $svgIcons['hourglass'], 'class' => 'status-muted'],
    'in_progress' => ['label' => 'En progreso', 'icon' => $svgIcons['refresh'], 'class' => 'status-info'],
    'review' => ['label' => 'En revisión', 'icon' => $svgIcons['eye'], 'class' => 'status-warning'],
    'blocked' => ['label' => 'Bloqueada', 'icon' => $svgIcons['shield'], 'class' => 'status-danger'],
    'done' => ['label' => 'Completada', 'icon' => $svgIcons['check_circle'], 'class' => 'status-success'],
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

    <?php if ($canManageTasks): ?>
        <form class="task-create-form" method="POST" action="<?= $basePath ?>/tasks/create">
            <div class="task-create-form__header">
                <strong>Crear tarea</strong>
                <span class="section-muted">Puedes crear tareas desde este panel igual que en cada proyecto.</span>
            </div>
            <div class="task-create-form__grid">
                <label>
                    Proyecto
                    <select name="project_id" required>
                        <option value="">Selecciona un proyecto</option>
                        <?php foreach ($projectOptionsForCreate as $project): ?>
                            <option value="<?= (int) ($project['id'] ?? 0) ?>"><?= htmlspecialchars($project['name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Título de la tarea
                    <input type="text" name="title" required maxlength="160" placeholder="Ej. Validar entregable de sprint" />
                </label>
                <label>
                    Prioridad
                    <select name="priority">
                        <option value="medium" selected>Media</option>
                        <option value="high">Alta</option>
                        <option value="low">Baja</option>
                    </select>
                </label>
                <label>
                    Horas estimadas
                    <input type="number" name="estimated_hours" min="0" step="0.5" value="0" />
                </label>
                <label>
                    Fecha límite
                    <input type="date" name="due_date" />
                </label>
                <label>
                    Responsable
                    <select name="assignee_id">
                        <option value="0">Sin asignar</option>
                        <?php foreach ($talents as $talent): ?>
                            <option value="<?= (int) ($talent['id'] ?? 0) ?>"><?= htmlspecialchars($talent['name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="task-create-form__actions">
                <button type="submit" class="action-btn primary">Crear tarea</button>
            </div>
        </form>
    <?php endif; ?>

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
            <span class="empty-state-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 12l2.5 2.5L15 10"/></svg></span>
            <div>
                <strong>No hay tareas registradas.</strong>
                <p class="section-muted">Crea una tarea desde este panel o desde la vista del proyecto para comenzar a reportar horas.</p>
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
                $status = $statusMeta[$statusKey] ?? ['label' => ucfirst(str_replace('_', ' ', (string) $statusKey)), 'icon' => $svgIcons['pin'], 'class' => 'status-muted'];
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
                            <span class="badge-icon"><?= $status['icon'] ?></span> <?= htmlspecialchars($status['label']) ?>
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
    .tasks-shell { display:flex; flex-direction:column; gap:18px; }
    .tasks-header { display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .tasks-header h2 { margin:0; font-weight: 800; letter-spacing: -0.02em; }
    .section-muted { color: var(--text-secondary); margin:0; font-size:13px; }
    .empty-state { border:1px dashed color-mix(in srgb, var(--border) 60%, var(--background)); border-radius:16px; padding:24px 20px; background: color-mix(in srgb, var(--surface) 90%, var(--background) 10%); display:flex; align-items:center; gap:16px; }
    .empty-state-icon { width:48px; height:48px; border-radius:14px; background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 15%, var(--surface)), color-mix(in srgb, var(--primary) 8%, var(--surface))); display:inline-flex; align-items:center; justify-content:center; border:1px solid color-mix(in srgb, var(--primary) 20%, var(--border)); flex-shrink:0; }
    .task-create-form { border:1px solid color-mix(in srgb, var(--primary) 15%, var(--border)); border-radius:16px; padding:18px; display:flex; flex-direction:column; gap:14px; background: color-mix(in srgb, var(--surface) 95%, var(--background) 5%); box-shadow: 0 1px 3px color-mix(in srgb, var(--text-primary) 4%, transparent); }
    .task-create-form__header { display:flex; flex-direction:column; gap:4px; }
    .task-create-form__header strong { font-size: 15px; }
    .task-create-form__grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; }
    .task-create-form label { display:flex; flex-direction:column; gap:6px; font-weight:600; color: var(--text-primary); font-size:12px; }
    .task-create-form input,
    .task-create-form select { padding:9px 12px; border-radius:10px; border:1px solid var(--border); background: var(--surface); color: var(--text-primary); font-size:13px; transition: border-color 0.2s ease, box-shadow 0.2s ease; }
    .task-create-form input:focus,
    .task-create-form select:focus { border-color: color-mix(in srgb, var(--primary) 60%, var(--border)); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 12%, transparent); outline: none; }
    .task-create-form__actions { display:flex; justify-content:flex-end; }
    .filters-bar { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:10px; padding:14px; border:1px solid var(--border); border-radius:14px; background: var(--surface); box-shadow: 0 1px 3px color-mix(in srgb, var(--text-primary) 3%, transparent); }
    .filters-bar label { display:flex; flex-direction:column; gap:6px; font-weight:600; color: var(--text-primary); font-size:12px; }
    .filters-bar select { padding:8px 10px; border-radius:10px; border:1px solid var(--border); background: var(--surface); color: var(--text-primary); font-size:13px; }
    .tasks-table { border:1px solid var(--border); border-radius:16px; overflow:hidden; background: var(--surface); box-shadow: 0 1px 3px color-mix(in srgb, var(--text-primary) 4%, transparent), 0 4px 12px color-mix(in srgb, var(--text-primary) 3%, transparent); }
    .tasks-row { display:grid; grid-template-columns: 2fr 1.4fr 1.2fr 1fr 1fr 1fr 2fr; gap:12px; padding:13px 18px; align-items:center; border-top:1px solid color-mix(in srgb, var(--border) 60%, var(--background)); font-size:13px; line-height:1.4; transition: background-color 0.15s ease; }
    .tasks-row:hover:not(.header) { background: color-mix(in srgb, var(--primary) 3%, var(--surface)); }
    .tasks-row.header { background: color-mix(in srgb, var(--surface) 94%, var(--background) 6%); font-weight:700; border-top:none; text-transform:uppercase; font-size:10px; letter-spacing:0.06em; color: var(--text-secondary); }
    .task-main { display:flex; flex-direction:column; gap:4px; }
    .meta-line { font-size:12px; color: var(--text-secondary); }
    .badge { padding:4px 10px; border-radius:999px; font-size:11px; font-weight:700; display:inline-flex; align-items:center; gap:5px; letter-spacing: 0.01em; }
    .badge-icon { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .badge-icon svg { display: block; }
    .status-muted { background: color-mix(in srgb, var(--neutral) 12%, var(--surface) 88%); color: var(--text-secondary); }
    .status-info { background: color-mix(in srgb, var(--info) 14%, var(--surface) 86%); color: var(--info); }
    .status-warning { background: color-mix(in srgb, var(--warning) 16%, var(--surface) 84%); color: var(--warning); }
    .status-success { background: color-mix(in srgb, var(--success) 14%, var(--surface) 86%); color: var(--success); }
    .status-danger { background: color-mix(in srgb, var(--danger) 14%, var(--surface) 86%); color: var(--danger); }
    .truncate { max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .hours-stack { display:flex; flex-direction:column; gap:2px; font-size:12px; color: var(--text-secondary); }
    .actions { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
    .action-btn { background: var(--surface); color: var(--text-primary); border:1px solid var(--border); border-radius:8px; padding:6px 10px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; font-size:12px; transition: all 0.2s ease; }
    .action-btn:hover { border-color: color-mix(in srgb, var(--primary) 40%, var(--border)); transform: translateY(-1px); }
    .action-btn.primary { background: var(--primary); color: var(--text-primary); border-color: var(--primary); box-shadow: 0 2px 6px color-mix(in srgb, var(--primary) 20%, transparent); }
    .action-btn.primary:hover { box-shadow: 0 3px 10px color-mix(in srgb, var(--primary) 30%, transparent); }
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
