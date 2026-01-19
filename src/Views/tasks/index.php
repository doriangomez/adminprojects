<?php
$tasks = is_array($tasks ?? null) ? $tasks : [];

$statusMeta = [
    'todo' => ['label' => 'Pendiente', 'icon' => '⏳', 'class' => 'status-muted'],
    'in_progress' => ['label' => 'En progreso', 'icon' => '🔄', 'class' => 'status-info'],
    'review' => ['label' => 'En revisión', 'icon' => '📝', 'class' => 'status-warning'],
    'blocked' => ['label' => 'Bloqueada', 'icon' => '⛔', 'class' => 'status-danger'],
    'done' => ['label' => 'Completada', 'icon' => '✅', 'class' => 'status-success'],
];

$priorityMeta = [
    'high' => ['label' => 'Alta', 'class' => 'status-danger'],
    'medium' => ['label' => 'Media', 'class' => 'status-warning'],
    'low' => ['label' => 'Baja', 'class' => 'status-success'],
];
?>

<section class="tasks-shell">
    <header class="tasks-header">
        <div>
            <h2>Gestión de tareas</h2>
            <p class="section-muted">Listado operativo con horas estimadas y reales por proyecto.</p>
        </div>
    </header>

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
                <div>Nombre</div>
                <div>Proyecto</div>
                <div>Fase</div>
                <div>Talento</div>
                <div>Estado</div>
                <div>Horas estimadas</div>
                <div>Horas reales</div>
            </div>
            <?php foreach ($tasks as $task): ?>
                <?php
                $status = $statusMeta[$task['status'] ?? ''] ?? ['label' => ucfirst(str_replace('_', ' ', (string) ($task['status'] ?? ''))), 'icon' => '📍', 'class' => 'status-muted'];
                $priority = $priorityMeta[$task['priority'] ?? ''] ?? ['label' => 'Media', 'class' => 'status-muted'];
                ?>
                <div class="tasks-row">
                    <div>
                        <strong><?= htmlspecialchars($task['title'] ?? '') ?></strong>
                        <div class="meta-line">Prioridad: <span class="badge <?= $priority['class'] ?>"><?= htmlspecialchars($priority['label']) ?></span></div>
                    </div>
                    <div><?= htmlspecialchars($task['project'] ?? '') ?></div>
                    <div><?= htmlspecialchars($task['project_phase'] ?? 'Sin fase') ?></div>
                    <div><?= htmlspecialchars($task['assignee'] ?? 'Sin asignar') ?></div>
                    <div>
                        <span class="badge <?= $status['class'] ?>">
                            <?= htmlspecialchars($status['icon']) ?> <?= htmlspecialchars($status['label']) ?>
                        </span>
                    </div>
                    <div><?= htmlspecialchars((string) ($task['estimated_hours'] ?? 0)) ?>h</div>
                    <div><?= htmlspecialchars((string) ($task['actual_hours'] ?? 0)) ?>h</div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<style>
    .tasks-shell { display:flex; flex-direction:column; gap:16px; }
    .tasks-header { display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .tasks-header h2 { margin:0; }
    .section-muted { color: var(--muted); margin:0; font-size:13px; }
    .empty-state { border:1px dashed var(--border); border-radius:12px; padding:16px; background: color-mix(in srgb, var(--surface) 84%, var(--bg-app) 16%); display:flex; align-items:flex-start; gap:12px; }
    .tasks-table { border:1px solid var(--border); border-radius:16px; overflow:hidden; background: var(--surface); }
    .tasks-row { display:grid; grid-template-columns: 2.2fr 1.4fr 1fr 1.2fr 1.1fr 0.9fr 0.9fr; gap:12px; padding:12px 16px; align-items:center; border-top:1px solid var(--border); font-size:14px; }
    .tasks-row.header { background: color-mix(in srgb, var(--surface) 80%, var(--bg-app) 20%); font-weight:700; border-top:none; text-transform:uppercase; font-size:12px; letter-spacing:0.04em; color: var(--muted); }
    .meta-line { margin-top:6px; font-size:12px; color: var(--muted); }
    .badge { padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; display:inline-flex; align-items:center; gap:6px; }
    .status-muted { background: color-mix(in srgb, var(--surface) 80%, var(--border) 20%); color: var(--text); }
    .status-info { background: color-mix(in srgb, var(--accent) 18%, var(--surface) 82%); color: var(--text-strong); }
    .status-warning { background: color-mix(in srgb, var(--warning) 24%, var(--surface) 76%); color: var(--text-strong); }
    .status-success { background: color-mix(in srgb, var(--success) 24%, var(--surface) 76%); color: var(--text-strong); }
    .status-danger { background: color-mix(in srgb, var(--danger) 22%, var(--surface) 78%); color: var(--text-strong); }
    @media (max-width: 1100px) {
        .tasks-row { grid-template-columns: 1.6fr 1fr 1fr; row-gap:8px; }
        .tasks-row.header { display:none; }
        .tasks-row > div:nth-child(n+4) { grid-column: span 1; }
    }
</style>
