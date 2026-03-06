<?php
$basePath = $basePath ?? '';
$tasks = is_array($tasks ?? null) ? $tasks : [];
$kanbanColumns = is_array($kanbanColumns ?? null) ? $kanbanColumns : [];
$kanbanByProject = is_array($kanbanByProject ?? null) ? $kanbanByProject : [];
$teamLoad = is_array($teamLoad ?? null) ? $teamLoad : [];
$projectOptionsForCreate = is_array($projectOptions ?? null) ? $projectOptions : [];
$talents = is_array($talents ?? null) ? $talents : [];
$canManageTasks = !empty($canManage);
$canCreateTasks = !empty($canCreateTasks);
$canDeleteTasks = !empty($canDeleteTasks);
$isTalentUser = !empty($isTalentUser);

$statusMeta = [
    'todo' => ['label' => 'Pendiente', 'icon' => '⏳', 'class' => 'status-muted'],
    'in_progress' => ['label' => 'En proceso', 'icon' => '🔄', 'class' => 'status-info'],
    'blocked' => ['label' => 'Bloqueado', 'icon' => '⛔', 'class' => 'status-danger'],
    'review' => ['label' => 'En revisión', 'icon' => '📝', 'class' => 'status-warning'],
    'done' => ['label' => 'Completado', 'icon' => '✅', 'class' => 'status-success'],
];

$priorityMeta = [
    'high' => ['label' => 'Alta', 'class' => 'status-danger'],
    'medium' => ['label' => 'Media', 'class' => 'status-warning'],
    'low' => ['label' => 'Baja', 'class' => 'status-success'],
];

$normalizeStatus = static function (string $status): string {
    $status = strtolower(trim($status));
    return match ($status) {
        'pending' => 'todo',
        'completed' => 'done',
        default => $status,
    };
};

$kanbanOrder = ['todo', 'in_progress', 'blocked', 'review', 'done'];
foreach ($kanbanOrder as $status) {
    if (!isset($kanbanColumns[$status])) {
        $kanbanColumns[$status] = [];
    }
}
?>

<section class="tasks-shell">
    <header class="tasks-header card-like">
        <div>
            <h2><?= $isTalentUser ? 'Panel de trabajo del talento' : 'Módulo de tareas' ?></h2>
            <p class="section-muted">
                <?= $isTalentUser
                    ? 'Tus tareas asignadas, estado Kanban, bloqueos y trazabilidad de horas.'
                    : 'Vista integral de tareas, talento asignado, bloqueos y carga operativa.' ?>
            </p>
        </div>
    </header>

    <?php if ($canCreateTasks): ?>
        <form class="task-create-form" method="POST" action="<?= $basePath ?>/tasks/create">
            <div class="task-create-form__header">
                <strong>Crear tarea</strong>
                <span class="section-muted">
                    <?= $isTalentUser
                        ? 'Solo podrás crear tareas en proyectos donde estás asignado y quedarán a tu nombre.'
                        : 'Las tareas se asignan por talento y aparecen de inmediato en su Kanban personal.' ?>
                </span>
            </div>
            <div class="task-create-form__grid">
                <label>
                    Proyecto
                    <select name="project_id" required>
                        <option value="">Selecciona un proyecto</option>
                        <?php foreach ($projectOptionsForCreate as $project): ?>
                            <option value="<?= (int) ($project['id'] ?? 0) ?>">
                                <?= htmlspecialchars((string) ($project['name'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Título de la tarea
                    <input type="text" name="title" required maxlength="160" placeholder="Ej. Integrar API de cliente" />
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
                    Fecha compromiso
                    <input type="date" name="due_date" />
                </label>
                <?php if ($canManageTasks): ?>
                    <label>
                        Asignar a talento
                        <select name="assignee_id">
                            <option value="0">Sin asignar</option>
                            <?php foreach ($talents as $talent): ?>
                                <option value="<?= (int) ($talent['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($talent['name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php else: ?>
                    <label>
                        Asignación
                        <input type="text" value="Se asignará automáticamente a tu perfil" disabled />
                    </label>
                <?php endif; ?>
            </div>
            <div class="task-create-form__actions">
                <button type="submit" class="action-btn primary">Crear tarea</button>
            </div>
        </form>
    <?php endif; ?>

    <section class="kanban-block card-like">
        <div class="kanban-heading">
            <h3><?= $isTalentUser ? 'Mis tareas (Kanban personal)' : 'Kanban operativo de tareas' ?></h3>
            <span class="section-muted">Columnas: todo · in_progress · blocked · review · done</span>
        </div>
        <div class="kanban-grid">
            <?php foreach ($kanbanOrder as $status): ?>
                <?php
                $columnMeta = $statusMeta[$status];
                $items = is_array($kanbanColumns[$status] ?? null) ? $kanbanColumns[$status] : [];
                ?>
                <article class="kanban-column">
                    <header>
                        <h4><?= htmlspecialchars($columnMeta['icon'] . ' ' . strtoupper($columnMeta['label'])) ?></h4>
                        <span class="count-pill"><?= count($items) ?></span>
                    </header>
                    <div class="kanban-cards">
                        <?php if ($items === []): ?>
                            <p class="empty-col">Sin tareas</p>
                        <?php else: ?>
                            <?php foreach ($items as $task): ?>
                                <?php
                                $rawStatus = $normalizeStatus((string) ($task['status'] ?? 'todo'));
                                $priorityKey = strtolower(trim((string) ($task['priority'] ?? 'medium')));
                                $priority = $priorityMeta[$priorityKey] ?? $priorityMeta['medium'];
                                $hasStopper = !empty($task['has_stopper']) || (int) ($task['open_stoppers'] ?? 0) > 0;
                                ?>
                                <div class="task-card">
                                    <strong><?= htmlspecialchars((string) ($task['title'] ?? 'Sin título')) ?></strong>
                                    <small class="section-muted">Proyecto: <?= htmlspecialchars((string) ($task['project'] ?? 'Sin proyecto')) ?></small>
                                    <?php if (!$isTalentUser): ?>
                                        <small class="section-muted">Talento: <?= htmlspecialchars((string) ($task['assignee'] ?? 'Sin asignar')) ?></small>
                                    <?php endif; ?>
                                    <div class="pillset">
                                        <span class="badge <?= htmlspecialchars($priority['class']) ?>"><?= htmlspecialchars($priority['label']) ?></span>
                                        <span class="badge status-muted"><?= htmlspecialchars((string) ($task['estimated_hours'] ?? 0)) ?>h est.</span>
                                    </div>
                                    <?php if ($hasStopper): ?>
                                        <div class="stopper-flag">⚠ Bloqueado por stopper activo</div>
                                    <?php endif; ?>
                                    <?php if ($canManageTasks || $isTalentUser): ?>
                                        <form method="POST" action="<?= $basePath ?>/tasks/<?= (int) ($task['id'] ?? 0) ?>/status" class="status-form">
                                            <select name="status">
                                                <?php foreach ($kanbanOrder as $statusValue): ?>
                                                    <option value="<?= htmlspecialchars($statusValue) ?>" <?= $rawStatus === $statusValue ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($statusMeta[$statusValue]['label']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="action-btn small primary">Actualizar</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canManageTasks): ?>
                                        <a class="action-btn small ghost" href="<?= $basePath ?>/tasks/<?= (int) ($task['id'] ?? 0) ?>/edit">Editar</a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($canManageTasks): ?>
        <section class="card-like">
            <div class="kanban-heading">
                <h3>Kanban por proyecto (PMO)</h3>
                <span class="section-muted">Visibilidad de tareas por estado y proyecto.</span>
            </div>
            <?php if ($kanbanByProject === []): ?>
                <p class="section-muted">Sin información de proyectos.</p>
            <?php else: ?>
                <div class="project-kanban-list">
                    <?php foreach ($kanbanByProject as $projectName => $projectColumns): ?>
                        <article class="project-kanban-card">
                            <h4><?= htmlspecialchars((string) $projectName) ?></h4>
                            <div class="project-kanban-columns">
                                <?php foreach ($kanbanOrder as $status): ?>
                                    <?php $items = is_array($projectColumns[$status] ?? null) ? $projectColumns[$status] : []; ?>
                                    <div>
                                        <strong><?= htmlspecialchars($statusMeta[$status]['label']) ?></strong>
                                        <?php if ($items === []): ?>
                                            <small class="section-muted">Sin tareas</small>
                                        <?php else: ?>
                                            <ul>
                                                <?php foreach ($items as $item): ?>
                                                    <li><?= htmlspecialchars((string) ($item['assignee'] ?? 'Sin asignar')) ?> → <?= htmlspecialchars((string) ($item['title'] ?? 'Sin título')) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card-like">
            <div class="kanban-heading">
                <h3>Carga del equipo</h3>
                <span class="section-muted">Cantidad de tareas y horas estimadas por talento.</span>
            </div>
            <?php if ($teamLoad === []): ?>
                <p class="section-muted">Sin carga registrada.</p>
            <?php else: ?>
                <div class="team-load-grid">
                    <?php foreach ($teamLoad as $load): ?>
                        <article class="load-card">
                            <h4><?= htmlspecialchars((string) ($load['assignee'] ?? 'Sin asignar')) ?></h4>
                            <p><strong><?= (int) ($load['tasks_count'] ?? 0) ?></strong> tareas</p>
                            <p><strong><?= number_format((float) ($load['estimated_hours'] ?? 0), 1) ?>h</strong> estimadas</p>
                            <p class="section-muted">Bloqueadas: <?= (int) ($load['blocked_count'] ?? 0) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="card-like">
        <div class="kanban-heading">
            <h3>Vista integrada de tareas</h3>
            <span class="section-muted">Proyecto, tarea, talento, estado y horas para trazabilidad operativa.</span>
        </div>
        <?php if ($tasks === []): ?>
            <div class="empty-state">
                <span>📌</span>
                <div>
                    <strong>No hay tareas registradas.</strong>
                    <p class="section-muted">Crea una tarea para empezar la ejecución y el registro en timesheet.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="tasks-table">
                <div class="tasks-row header">
                    <div>Proyecto</div>
                    <div>Tarea</div>
                    <div>Talento</div>
                    <div>Estado</div>
                    <div>Horas</div>
                    <div>Acciones</div>
                </div>
                <?php foreach ($tasks as $task): ?>
                    <?php
                    $rawStatus = $normalizeStatus((string) ($task['status'] ?? 'todo'));
                    $hasStopper = (int) ($task['open_stoppers'] ?? 0) > 0;
                    $effectiveStatus = $hasStopper && $rawStatus !== 'done' ? 'blocked' : $rawStatus;
                    $status = $statusMeta[$effectiveStatus] ?? $statusMeta['todo'];
                    ?>
                    <div class="tasks-row">
                        <div><?= htmlspecialchars((string) ($task['project'] ?? 'Sin proyecto')) ?></div>
                        <div>
                            <strong><?= htmlspecialchars((string) ($task['title'] ?? 'Sin título')) ?></strong>
                            <?php if ($hasStopper): ?>
                                <div class="stopper-flag">⚠ Bloqueado</div>
                            <?php endif; ?>
                        </div>
                        <div><?= htmlspecialchars((string) ($task['assignee'] ?? 'Sin asignar')) ?></div>
                        <div>
                            <span class="badge <?= htmlspecialchars($status['class']) ?>">
                                <?= htmlspecialchars($status['icon']) ?> <?= htmlspecialchars($status['label']) ?>
                            </span>
                        </div>
                        <div class="hours-stack">
                            <span><?= htmlspecialchars((string) ($task['actual_hours'] ?? 0)) ?>h reales</span>
                            <span><?= htmlspecialchars((string) ($task['estimated_hours'] ?? 0)) ?>h estimadas</span>
                        </div>
                        <div class="actions">
                            <a class="action-btn small" href="<?= $basePath ?>/projects/<?= (int) ($task['project_id'] ?? 0) ?>/tasks">Ver proyecto</a>
                            <?php if ($canManageTasks): ?>
                                <a class="action-btn small ghost" href="<?= $basePath ?>/tasks/<?= (int) ($task['id'] ?? 0) ?>/edit">Editar</a>
                            <?php endif; ?>
                            <?php if ($canManageTasks || $isTalentUser): ?>
                                <form method="POST" action="<?= $basePath ?>/tasks/<?= (int) ($task['id'] ?? 0) ?>/status" class="status-form">
                                    <select name="status">
                                        <?php foreach ($kanbanOrder as $statusValue): ?>
                                            <option value="<?= htmlspecialchars($statusValue) ?>" <?= $rawStatus === $statusValue ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($statusMeta[$statusValue]['label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="action-btn small primary">Actualizar</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($canDeleteTasks): ?>
                                <form method="POST" action="<?= $basePath ?>/tasks/<?= (int) ($task['id'] ?? 0) ?>/delete" onsubmit="return confirm('¿Eliminar esta tarea?');">
                                    <button type="submit" class="action-btn small danger">Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>

<style>
    .tasks-shell { display:flex; flex-direction:column; gap:16px; }
    .card-like { border:1px solid var(--border); border-radius:14px; padding:14px; background: var(--surface); }
    .tasks-header h2 { margin:0 0 6px 0; }
    .section-muted { color: var(--text-secondary); margin:0; font-size:13px; }
    .task-create-form { border:1px dashed var(--border); border-radius:14px; padding:14px; display:flex; flex-direction:column; gap:12px; background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%); }
    .task-create-form__header { display:flex; flex-direction:column; gap:4px; }
    .task-create-form__grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap:12px; }
    .task-create-form label { display:flex; flex-direction:column; gap:6px; font-weight:600; color: var(--text-primary); font-size:12px; }
    .task-create-form input,
    .task-create-form select { padding:8px 10px; border-radius:10px; border:1px solid var(--border); background: var(--surface); color: var(--text-primary); font-size:13px; }
    .task-create-form__actions { display:flex; justify-content:flex-end; }

    .kanban-block { display:flex; flex-direction:column; gap:12px; }
    .kanban-heading { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
    .kanban-heading h3 { margin:0; }
    .kanban-grid { display:grid; grid-template-columns: repeat(5, minmax(170px, 1fr)); gap:10px; }
    .kanban-column { border:1px solid var(--border); border-radius:12px; padding:10px; background: color-mix(in srgb, var(--surface) 90%, var(--background) 10%); display:flex; flex-direction:column; gap:8px; min-height:240px; }
    .kanban-column header { display:flex; justify-content:space-between; align-items:center; gap:8px; }
    .kanban-column h4 { margin:0; font-size:12px; text-transform:uppercase; letter-spacing:0.04em; color: var(--text-secondary); }
    .count-pill { font-size:11px; border:1px solid var(--border); border-radius:999px; padding:2px 8px; background: var(--surface); }
    .kanban-cards { display:flex; flex-direction:column; gap:8px; }
    .task-card { border:1px solid var(--border); border-radius:10px; padding:10px; background: var(--surface); display:flex; flex-direction:column; gap:6px; }
    .pillset { display:flex; flex-wrap:wrap; gap:6px; }
    .stopper-flag { font-size:12px; color: var(--danger); font-weight:700; }
    .empty-col { margin:0; color: var(--text-secondary); font-size:12px; }

    .project-kanban-list { display:flex; flex-direction:column; gap:12px; }
    .project-kanban-card { border:1px solid var(--border); border-radius:12px; padding:12px; background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%); }
    .project-kanban-card h4 { margin:0 0 8px 0; }
    .project-kanban-columns { display:grid; grid-template-columns: repeat(5, minmax(150px, 1fr)); gap:10px; }
    .project-kanban-columns ul { margin:6px 0 0 0; padding-left:18px; display:flex; flex-direction:column; gap:4px; font-size:12px; }
    .project-kanban-columns strong { font-size:12px; color: var(--text-secondary); text-transform:uppercase; }

    .team-load-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap:10px; }
    .load-card { border:1px solid var(--border); border-radius:12px; padding:12px; background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%); }
    .load-card h4 { margin:0 0 8px 0; }
    .load-card p { margin:0 0 6px 0; }

    .empty-state { border:1px dashed var(--border); border-radius:12px; padding:16px; background: color-mix(in srgb, var(--surface) 84%, var(--background) 16%); display:flex; align-items:flex-start; gap:12px; }
    .tasks-table { border:1px solid var(--border); border-radius:14px; overflow:hidden; background: var(--surface); }
    .tasks-row { display:grid; grid-template-columns: 1.3fr 2fr 1.2fr 1fr 1fr 2fr; gap:10px; padding:12px 14px; align-items:center; border-top:1px solid var(--border); font-size:13px; }
    .tasks-row.header { border-top:none; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color: var(--text-secondary); font-weight:700; background: color-mix(in srgb, var(--surface) 85%, var(--background) 15%); }
    .hours-stack { display:flex; flex-direction:column; gap:2px; font-size:12px; color: var(--text-secondary); }
    .actions { display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
    .status-form { display:flex; gap:6px; flex-wrap:wrap; align-items:center; }

    .badge { padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; display:inline-flex; align-items:center; gap:6px; }
    .status-muted { background: color-mix(in srgb, var(--surface) 80%, var(--border) 20%); color: var(--text-secondary); }
    .status-info { background: color-mix(in srgb, var(--accent) 18%, var(--surface) 82%); color: var(--text-primary); }
    .status-warning { background: color-mix(in srgb, var(--warning) 24%, var(--surface) 76%); color: var(--text-primary); }
    .status-success { background: color-mix(in srgb, var(--success) 24%, var(--surface) 76%); color: var(--text-primary); }
    .status-danger { background: color-mix(in srgb, var(--danger) 22%, var(--surface) 78%); color: var(--text-primary); }

    .action-btn { background: var(--surface); color: var(--text-primary); border:1px solid var(--border); border-radius:8px; padding:6px 9px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; font-size:12px; }
    .action-btn.small { padding:5px 8px; font-size:11px; }
    .action-btn.primary { background: var(--primary); color: var(--text-primary); border-color: var(--primary); }
    .action-btn.ghost { background: color-mix(in srgb, var(--surface) 86%, var(--background) 14%); }
    .action-btn.danger { border-color: color-mix(in srgb, var(--danger) 48%, var(--border)); color: var(--danger); }

    @media (max-width: 1260px) {
        .kanban-grid { grid-template-columns: repeat(3, minmax(170px, 1fr)); }
        .project-kanban-columns { grid-template-columns: repeat(3, minmax(150px, 1fr)); }
    }
    @media (max-width: 900px) {
        .kanban-grid,
        .project-kanban-columns { grid-template-columns: 1fr; }
        .tasks-row { grid-template-columns: 1fr; }
        .tasks-row.header { display:none; }
    }
</style>
