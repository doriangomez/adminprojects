<?php
$basePath = $basePath ?? '/project/public';
$task = is_array($task ?? null) ? $task : [];
$talents = is_array($talents ?? null) ? $talents : [];
$statusOptions = [
    'todo' => 'Pendiente',
    'in_progress' => 'En progreso',
    'review' => 'En revisión',
    'blocked' => 'Bloqueada',
    'done' => 'Completada',
];
$priorityOptions = [
    'low' => 'Baja',
    'medium' => 'Media',
    'high' => 'Alta',
];
?>

<section class="task-edit-shell">
    <header class="task-edit-header">
        <div>
            <h2>Editar tarea</h2>
            <p class="section-muted">Actualiza los detalles operativos y el estado de la tarea.</p>
        </div>
        <a class="secondary-button" href="<?= $basePath ?>/tasks">Volver a tareas</a>
    </header>

    <section class="card task-edit-card">
        <div class="task-context">
            <div>
                <span class="section-muted">Proyecto</span>
                <strong class="wrap-anywhere"><?= htmlspecialchars($task['project'] ?? '') ?></strong>
            </div>
            <div>
                <span class="section-muted">Fase</span>
                <strong><?= htmlspecialchars($task['project_phase'] ?? 'Sin fase') ?></strong>
            </div>
        </div>

        <form method="POST" action="<?= $basePath ?>/tasks/<?= (int) ($task['id'] ?? 0) ?>/update" class="task-edit-form">
            <label>Título de la tarea
                <input type="text" name="title" required value="<?= htmlspecialchars($task['title'] ?? '') ?>">
            </label>

            <div class="form-grid">
                <label>Estado
                    <select name="status" required>
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>" <?= ($task['status'] ?? '') === $value ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Prioridad
                    <select name="priority" required>
                        <?php foreach ($priorityOptions as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>" <?= ($task['priority'] ?? '') === $value ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="form-grid">
                <label>Horas estimadas
                    <input type="number" name="estimated_hours" step="0.25" min="0" value="<?= htmlspecialchars((string) ($task['estimated_hours'] ?? 0)) ?>">
                </label>
                <label>Fecha compromiso
                    <input type="date" name="due_date" value="<?= htmlspecialchars((string) ($task['due_date'] ?? '')) ?>">
                </label>
            </div>

            <label>Asignado
                <select name="assignee_id">
                    <option value="0">Sin asignar</option>
                    <?php foreach ($talents as $talent): ?>
                        <option value="<?= (int) ($talent['id'] ?? 0) ?>" <?= (int) ($task['assignee_id'] ?? 0) === (int) ($talent['id'] ?? 0) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($talent['name'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <button type="submit" class="primary-button">Guardar cambios</button>
        </form>
    </section>
</section>

<style>
    .task-edit-shell { display:flex; flex-direction:column; gap:16px; }
    .task-edit-header { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .task-edit-header h2 { margin:0; }
    .section-muted { color: var(--text-secondary); margin:0; font-size:13px; }
    .card { border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); }
    .task-edit-card { display:flex; flex-direction:column; gap:16px; }
    .task-context { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; }
    .task-context strong { font-size:15px; color: var(--text-primary); }
    .wrap-anywhere { overflow-wrap:anywhere; max-width:280px; }
    .task-edit-form { display:flex; flex-direction:column; gap:12px; }
    .form-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; }
    label { display:flex; flex-direction:column; gap:6px; font-weight:600; color: var(--text-primary); font-size:13px; }
    input,
    select { padding:10px 12px; border-radius:10px; border:1px solid var(--border); background: var(--surface); color: var(--text-primary); }
    .primary-button { background: var(--primary); color: var(--text-primary); border:none; cursor:pointer; border-radius:10px; padding:10px 14px; font-weight:700; width:fit-content; }
    .secondary-button { background: color-mix(in srgb, var(--surface) 86%, var(--background) 14%); color: var(--text-primary); border:1px solid var(--border); cursor:pointer; border-radius:10px; padding:8px 12px; font-weight:700; text-decoration:none; }
</style>
