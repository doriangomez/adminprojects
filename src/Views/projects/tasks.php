<?php
$basePath = $basePath ?? '/project/public';
$project = $project ?? [];
$tasks = is_array($tasks ?? null) ? $tasks : [];
?>

<section class="project-shell">
    <header class="project-header">
        <div class="project-title-block">
            <p class="eyebrow">Detalle del proyecto</p>
            <h2><?= htmlspecialchars($project['name'] ?? '') ?></h2>
        </div>
        <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>">Volver a documentos</a>
    </header>

    <?php
    $activeTab = 'tasks';
    require __DIR__ . '/_tabs.php';
    ?>

    <section class="task-section">
        <div>
            <p class="eyebrow">Tareas</p>
            <h4>Backlog operativo del proyecto</h4>
            <small class="section-muted">Informativo para proyectos de outsourcing y convencionales.</small>
        </div>

        <?php if (empty($tasks)): ?>
            <p class="section-muted">No hay tareas registradas para este proyecto.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Tarea</th>
                            <th>Responsable</th>
                            <th>Estado</th>
                            <th>Prioridad</th>
                            <th>Horas</th>
                            <th>Vence</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td><?= htmlspecialchars($task['title'] ?? '') ?></td>
                                <td><?= htmlspecialchars($task['assignee'] ?? 'Sin asignar') ?></td>
                                <td><?= htmlspecialchars($task['status'] ?? '') ?></td>
                                <td><?= htmlspecialchars($task['priority'] ?? '') ?></td>
                                <td><?= htmlspecialchars((string) ($task['actual_hours'] ?? 0)) ?>/<?= htmlspecialchars((string) ($task['estimated_hours'] ?? 0)) ?></td>
                                <td><?= htmlspecialchars((string) ($task['due_date'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>

<style>
    .project-shell { display:flex; flex-direction:column; gap:16px; }
    .project-header { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap; border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); }
    .project-title-block { display:flex; flex-direction:column; gap:8px; }
    .project-title-block h2 { margin:0; color: var(--text-strong); }
    .task-section { border:1px solid var(--border); border-radius:16px; padding:16px; background:#fff; display:flex; flex-direction:column; gap:12px; }
    .action-btn { background: var(--surface); color: var(--text-strong); border:1px solid var(--border); border-radius:8px; padding:8px 10px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
</style>
