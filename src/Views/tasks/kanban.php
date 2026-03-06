<?php
$basePath = $basePath ?? '';
$kanbanColumns = is_array($kanbanColumns ?? null) ? $kanbanColumns : [];
$workload = is_array($workload ?? null) ? $workload : [];
$canManage = !empty($canManage);
$isPrivileged = !empty($isPrivileged);
$projectOptions = is_array($projectOptions ?? null) ? $projectOptions : [];
$talents = is_array($talents ?? null) ? $talents : [];

$columnMeta = [
    'todo'        => ['label' => 'Pendiente',   'color' => 'var(--text-secondary)', 'accent' => '#64748b', 'icon' => '⏳'],
    'in_progress' => ['label' => 'En proceso',  'color' => 'var(--info)',            'accent' => '#0ea5e9', 'icon' => '🔄'],
    'review'      => ['label' => 'En revisión', 'color' => 'var(--warning)',         'accent' => '#f59e0b', 'icon' => '🔍'],
    'blocked'     => ['label' => 'Bloqueado',   'color' => 'var(--danger)',          'accent' => '#ef4444', 'icon' => '⛔'],
    'done'        => ['label' => 'Completado',  'color' => 'var(--success)',         'accent' => '#22c55e', 'icon' => '✅'],
];

$priorityMeta = [
    'high'   => ['label' => 'Alta',  'class' => 'prio-high'],
    'medium' => ['label' => 'Media', 'class' => 'prio-medium'],
    'low'    => ['label' => 'Baja',  'class' => 'prio-low'],
];

$totalTasks = 0;
foreach ($kanbanColumns as $col) {
    $totalTasks += count($col);
}
?>

<div class="kb-shell">

    <div class="kb-topbar">
        <div class="kb-topbar-left">
            <div class="kb-view-switcher">
                <a href="<?= $basePath ?>/tasks" class="kb-switch-btn">
                    <svg viewBox="0 0 24 24"><path d="M8 6h12M8 12h12M8 18h12M3 6h.01M3 12h.01M3 18h.01"/></svg>
                    Lista
                </a>
                <a href="<?= $basePath ?>/tasks?view=kanban" class="kb-switch-btn active">
                    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="5" height="18" rx="1"/><rect x="10" y="3" width="5" height="18" rx="1"/><rect x="17" y="3" width="5" height="18" rx="1"/></svg>
                    Kanban
                </a>
            </div>
            <span class="kb-count-badge"><?= $totalTasks ?> tareas</span>
        </div>
        <div class="kb-topbar-right">
            <?php if ($canManage): ?>
                <button class="kb-create-btn" type="button" data-open-create>
                    <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    Nueva tarea
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isPrivileged && !empty($workload)): ?>
        <div class="kb-workload-bar">
            <span class="kb-workload-title">Carga del equipo</span>
            <?php foreach ($workload as $w): ?>
                <?php
                $taskCount = (int) ($w['task_count'] ?? 0);
                $pendingHours = (float) ($w['pending_hours'] ?? 0);
                $blockedCount = (int) ($w['blocked_count'] ?? 0);
                ?>
                <div class="kb-workload-chip <?= $blockedCount > 0 ? 'has-blockers' : '' ?>">
                    <span class="kb-workload-avatar"><?= strtoupper(substr((string) ($w['talent_name'] ?? '?'), 0, 1)) ?></span>
                    <div class="kb-workload-info">
                        <strong><?= htmlspecialchars((string) ($w['talent_name'] ?? 'Sin nombre')) ?></strong>
                        <span><?= $taskCount ?> tarea<?= $taskCount !== 1 ? 's' : '' ?> · <?= number_format($pendingHours, 1) ?>h pendientes<?= $blockedCount > 0 ? " · ⛔ {$blockedCount} bloq." : '' ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="kb-board">
        <?php foreach ($columnMeta as $colKey => $colInfo): ?>
            <?php $tasks = $kanbanColumns[$colKey] ?? []; ?>
            <div class="kb-column" data-col="<?= $colKey ?>">
                <div class="kb-col-header" style="--col-accent: <?= $colInfo['accent'] ?>">
                    <span class="kb-col-icon"><?= $colInfo['icon'] ?></span>
                    <span class="kb-col-label"><?= $colInfo['label'] ?></span>
                    <span class="kb-col-count"><?= count($tasks) ?></span>
                </div>
                <div class="kb-cards">
                    <?php if (empty($tasks)): ?>
                        <div class="kb-empty-col">Sin tareas</div>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                            <?php
                            $priorityKey = (string) ($task['priority'] ?? 'medium');
                            $prioMeta = $priorityMeta[$priorityKey] ?? ['label' => 'Media', 'class' => 'prio-medium'];
                            $hasBlocker = !empty($task['stopper_title']);
                            $isOverdue = !empty($task['due_date']) && strtotime($task['due_date']) < strtotime('today') && !in_array($colKey, ['done'], true);
                            ?>
                            <div class="kb-card <?= $hasBlocker ? 'has-stopper' : '' ?> <?= $isOverdue ? 'is-overdue' : '' ?>">
                                <?php if ($hasBlocker): ?>
                                    <div class="kb-stopper-bar">
                                        <svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                        Bloqueado: <?= htmlspecialchars(substr((string) $task['stopper_title'], 0, 60)) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="kb-card-title"><?= htmlspecialchars((string) ($task['title'] ?? '')) ?></div>
                                <div class="kb-card-meta">
                                    <span class="kb-project-pill"><?= htmlspecialchars((string) ($task['project'] ?? '')) ?></span>
                                </div>
                                <div class="kb-card-footer">
                                    <span class="kb-prio-pill <?= $prioMeta['class'] ?>"><?= $prioMeta['label'] ?></span>
                                    <?php if ($isPrivileged && !empty($task['assignee'])): ?>
                                        <span class="kb-assignee-pill">
                                            <span class="kb-mini-avatar"><?= strtoupper(substr((string) $task['assignee'], 0, 1)) ?></span>
                                            <?= htmlspecialchars((string) $task['assignee']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($task['estimated_hours'])): ?>
                                        <span class="kb-hours-pill">⏱ <?= (float) $task['estimated_hours'] ?>h</span>
                                    <?php endif; ?>
                                    <?php if ($isOverdue): ?>
                                        <span class="kb-overdue-pill">Vencida</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($canManage): ?>
                                    <div class="kb-card-actions">
                                        <a href="<?= $basePath ?>/tasks/<?= (int) $task['id'] ?>/edit" class="kb-action-link">Editar</a>
                                        <?php foreach ($columnMeta as $targetCol => $targetMeta): ?>
                                            <?php if ($targetCol !== $colKey): ?>
                                                <form method="POST" action="<?= $basePath ?>/tasks/<?= (int) $task['id'] ?>/status" class="kb-status-form">
                                                    <input type="hidden" name="status" value="<?= $targetCol ?>">
                                                    <input type="hidden" name="redirect" value="/tasks?view=kanban">
                                                    <button type="submit" class="kb-action-link" title="Mover a <?= $targetMeta['label'] ?>">→ <?= $targetMeta['icon'] ?></button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($canManage): ?>
    <div class="kb-modal-overlay" id="kb-create-modal" hidden>
        <div class="kb-modal">
            <div class="kb-modal-header">
                <strong>Nueva tarea</strong>
                <button type="button" class="kb-modal-close" data-close-modal>✕</button>
            </div>
            <form method="POST" action="<?= $basePath ?>/tasks/create" class="kb-modal-form">
                <input type="hidden" name="redirect" value="/tasks?view=kanban">
                <label>
                    Proyecto
                    <select name="project_id" required>
                        <option value="">Selecciona un proyecto</option>
                        <?php foreach ($projectOptions as $project): ?>
                            <option value="<?= (int) ($project['id'] ?? 0) ?>"><?= htmlspecialchars($project['name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Título
                    <input type="text" name="title" required maxlength="160" placeholder="Ej. Integrar API de pagos">
                </label>
                <div class="kb-form-row">
                    <label>
                        Prioridad
                        <select name="priority">
                            <option value="medium">Media</option>
                            <option value="high">Alta</option>
                            <option value="low">Baja</option>
                        </select>
                    </label>
                    <label>
                        Horas estimadas
                        <input type="number" name="estimated_hours" min="0" step="0.5" value="0">
                    </label>
                    <label>
                        Fecha límite
                        <input type="date" name="due_date">
                    </label>
                </div>
                <label>
                    Asignar a talento
                    <select name="assignee_id">
                        <option value="0">Sin asignar</option>
                        <?php foreach ($talents as $talent): ?>
                            <option value="<?= (int) ($talent['id'] ?? 0) ?>"><?= htmlspecialchars($talent['name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="kb-form-footer">
                    <button type="button" class="kb-btn ghost" data-close-modal>Cancelar</button>
                    <button type="submit" class="kb-btn primary">Crear tarea</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<style>
    .kb-shell { display:flex; flex-direction:column; gap:16px; }
    .kb-topbar { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .kb-topbar-left, .kb-topbar-right { display:flex; align-items:center; gap:10px; }
    .kb-view-switcher { display:flex; gap:0; border:1px solid var(--border); border-radius:10px; overflow:hidden; }
    .kb-switch-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; text-decoration:none; color: var(--text-secondary); font-size:13px; font-weight:600; background: color-mix(in srgb, var(--surface) 90%, var(--background) 10%); border-right:1px solid var(--border); transition:background 0.15s; }
    .kb-switch-btn:last-child { border-right:none; }
    .kb-switch-btn.active { background: var(--primary); color: var(--text-primary); }
    .kb-switch-btn svg { width:14px; height:14px; stroke:currentColor; fill:none; stroke-width:2; }
    .kb-count-badge { padding:5px 10px; border-radius:999px; background: color-mix(in srgb, var(--surface) 80%, var(--background) 20%); color: var(--text-secondary); font-size:12px; font-weight:700; border:1px solid var(--border); }
    .kb-create-btn { display:inline-flex; align-items:center; gap:8px; padding:9px 14px; border-radius:10px; border:1px solid var(--primary); background: var(--primary); color: var(--text-primary); font-weight:700; font-size:13px; cursor:pointer; transition:all 0.15s; }
    .kb-create-btn:hover { background: color-mix(in srgb, var(--primary) 85%, #000 15%); }
    .kb-create-btn svg { width:15px; height:15px; stroke:currentColor; fill:none; stroke-width:2.5; }
    .kb-workload-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; padding:12px 14px; border:1px solid var(--border); border-radius:12px; background: color-mix(in srgb, var(--surface) 90%, var(--background) 10%); }
    .kb-workload-title { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; color: var(--text-secondary); white-space:nowrap; margin-right:4px; }
    .kb-workload-chip { display:flex; align-items:center; gap:8px; padding:6px 10px; border-radius:10px; background: var(--surface); border:1px solid var(--border); }
    .kb-workload-chip.has-blockers { border-color: color-mix(in srgb, var(--danger) 40%, var(--border)); background: color-mix(in srgb, var(--danger) 8%, var(--surface)); }
    .kb-workload-avatar { width:26px; height:26px; border-radius:50%; background: color-mix(in srgb, var(--primary) 24%, var(--surface)); display:inline-flex; align-items:center; justify-content:center; font-size:11px; font-weight:800; color: var(--text-primary); flex-shrink:0; }
    .kb-workload-info { display:flex; flex-direction:column; gap:1px; }
    .kb-workload-info strong { font-size:12px; font-weight:700; color: var(--text-primary); }
    .kb-workload-info span { font-size:11px; color: var(--text-secondary); }
    .kb-board { display:grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap:12px; align-items:start; overflow-x:auto; }
    @media (max-width: 1280px) { .kb-board { grid-template-columns: repeat(3, minmax(220px, 1fr)); } }
    @media (max-width: 860px) { .kb-board { grid-template-columns: repeat(2, minmax(200px, 1fr)); } }
    @media (max-width: 560px) { .kb-board { grid-template-columns: 1fr; } }
    .kb-column { background: color-mix(in srgb, var(--surface) 88%, var(--background) 12%); border-radius:14px; border:1px solid var(--border); display:flex; flex-direction:column; min-height:200px; }
    .kb-col-header { display:flex; align-items:center; gap:8px; padding:12px 14px; border-bottom:3px solid var(--col-accent, var(--border)); border-radius:13px 13px 0 0; }
    .kb-col-icon { font-size:16px; }
    .kb-col-label { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.07em; color: var(--text-secondary); flex:1; }
    .kb-col-count { min-width:22px; height:22px; border-radius:999px; background: color-mix(in srgb, var(--surface) 80%, var(--background) 20%); color: var(--text-secondary); font-size:11px; font-weight:700; display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--border); }
    .kb-cards { display:flex; flex-direction:column; gap:8px; padding:10px; min-height:80px; }
    .kb-empty-col { color: var(--text-secondary); font-size:12px; text-align:center; padding:20px 0; }
    .kb-card { background: var(--surface); border-radius:10px; border:1px solid var(--border); padding:11px; display:flex; flex-direction:column; gap:7px; transition:box-shadow 0.15s, transform 0.15s; }
    .kb-card:hover { box-shadow: 0 6px 18px color-mix(in srgb, var(--primary) 14%, transparent); transform: translateY(-1px); }
    .kb-card.has-stopper { border-color: color-mix(in srgb, var(--danger) 45%, var(--border)); }
    .kb-card.is-overdue { border-color: color-mix(in srgb, var(--warning) 50%, var(--border)); }
    .kb-stopper-bar { display:flex; align-items:flex-start; gap:6px; padding:6px 8px; border-radius:7px; background: color-mix(in srgb, var(--danger) 12%, var(--surface)); color: var(--danger); font-size:11px; font-weight:600; }
    .kb-stopper-bar svg { width:13px; height:13px; stroke:currentColor; fill:none; stroke-width:2; flex-shrink:0; margin-top:1px; }
    .kb-card-title { font-size:13px; font-weight:700; color: var(--text-primary); line-height:1.4; }
    .kb-card-meta { display:flex; flex-wrap:wrap; gap:5px; }
    .kb-project-pill { padding:3px 7px; border-radius:6px; background: color-mix(in srgb, var(--primary) 12%, var(--surface)); color: var(--primary); font-size:11px; font-weight:600; border:1px solid color-mix(in srgb, var(--primary) 24%, var(--border)); max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .kb-card-footer { display:flex; flex-wrap:wrap; gap:5px; align-items:center; }
    .kb-prio-pill { padding:3px 7px; border-radius:6px; font-size:11px; font-weight:600; }
    .prio-high { background: color-mix(in srgb, var(--danger) 16%, var(--surface)); color: var(--danger); border:1px solid color-mix(in srgb, var(--danger) 30%, var(--border)); }
    .prio-medium { background: color-mix(in srgb, var(--warning) 16%, var(--surface)); color: var(--warning); border:1px solid color-mix(in srgb, var(--warning) 30%, var(--border)); }
    .prio-low { background: color-mix(in srgb, var(--success) 14%, var(--surface)); color: var(--success); border:1px solid color-mix(in srgb, var(--success) 28%, var(--border)); }
    .kb-assignee-pill { display:inline-flex; align-items:center; gap:4px; padding:3px 7px; border-radius:6px; background: color-mix(in srgb, var(--surface) 80%, var(--background) 20%); color: var(--text-secondary); font-size:11px; font-weight:600; border:1px solid var(--border); }
    .kb-mini-avatar { width:14px; height:14px; border-radius:50%; background: var(--primary); color: var(--text-primary); font-size:9px; font-weight:800; display:inline-flex; align-items:center; justify-content:center; }
    .kb-hours-pill { padding:3px 7px; border-radius:6px; background: color-mix(in srgb, var(--info) 12%, var(--surface)); color: var(--info); font-size:11px; font-weight:600; border:1px solid color-mix(in srgb, var(--info) 25%, var(--border)); }
    .kb-overdue-pill { padding:3px 7px; border-radius:6px; background: color-mix(in srgb, var(--warning) 16%, var(--surface)); color: var(--warning); font-size:11px; font-weight:700; border:1px solid color-mix(in srgb, var(--warning) 30%, var(--border)); }
    .kb-card-actions { display:flex; flex-wrap:wrap; gap:4px; border-top:1px solid var(--border); padding-top:7px; }
    .kb-action-link { padding:4px 8px; border-radius:7px; border:1px solid var(--border); background: color-mix(in srgb, var(--surface) 88%, var(--background) 12%); color: var(--text-secondary); font-size:11px; font-weight:600; text-decoration:none; cursor:pointer; transition:all 0.12s; }
    .kb-action-link:hover { background: color-mix(in srgb, var(--primary) 14%, var(--surface)); color: var(--primary); border-color: color-mix(in srgb, var(--primary) 35%, var(--border)); }
    .kb-status-form { margin:0; padding:0; }
    .kb-status-form button { font-family:inherit; }
    .kb-modal-overlay { position:fixed; inset:0; background: color-mix(in srgb, #000 55%, transparent); display:flex; align-items:center; justify-content:center; z-index:100; padding:20px; }
    .kb-modal { background: var(--surface); border:1px solid var(--border); border-radius:18px; padding:24px; width:100%; max-width:540px; display:flex; flex-direction:column; gap:16px; box-shadow: 0 20px 60px color-mix(in srgb, #000 40%, transparent); }
    .kb-modal-header { display:flex; align-items:center; justify-content:space-between; }
    .kb-modal-header strong { font-size:16px; font-weight:800; }
    .kb-modal-close { background:none; border:none; font-size:18px; cursor:pointer; color: var(--text-secondary); padding:4px 8px; border-radius:8px; }
    .kb-modal-close:hover { background: color-mix(in srgb, var(--danger) 12%, var(--surface)); color: var(--danger); }
    .kb-modal-form { display:flex; flex-direction:column; gap:12px; }
    .kb-modal-form label { display:flex; flex-direction:column; gap:6px; font-size:13px; font-weight:600; color: var(--text-primary); }
    .kb-modal-form input, .kb-modal-form select { padding:9px 11px; border-radius:10px; border:1px solid var(--border); background: color-mix(in srgb, var(--surface) 94%, var(--background) 6%); color: var(--text-primary); font-size:13px; }
    .kb-form-row { display:grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap:10px; }
    .kb-form-footer { display:flex; justify-content:flex-end; gap:8px; padding-top:4px; }
    .kb-btn { padding:9px 16px; border-radius:10px; border:1px solid var(--border); font-size:13px; font-weight:700; cursor:pointer; }
    .kb-btn.primary { background: var(--primary); color: var(--text-primary); border-color: var(--primary); }
    .kb-btn.ghost { background: var(--surface); color: var(--text-secondary); }
</style>

<script>
    (() => {
        const btn = document.querySelector('[data-open-create]');
        const modal = document.getElementById('kb-create-modal');
        const closeBtns = modal ? modal.querySelectorAll('[data-close-modal]') : [];

        if (btn && modal) {
            btn.addEventListener('click', () => { modal.hidden = false; });
        }
        closeBtns.forEach(b => b.addEventListener('click', () => { if (modal) modal.hidden = true; }));
        if (modal) {
            modal.addEventListener('click', e => { if (e.target === modal) modal.hidden = true; });
        }
    })();
</script>
