<?php
$basePath = $basePath ?? '';
$kanban = is_array($kanban ?? null) ? $kanban : [];
$workload = is_array($workload ?? null) ? $workload : [];
$allTasks = is_array($allTasks ?? null) ? $allTasks : [];
$talents = is_array($talents ?? null) ? $talents : [];
$projectOptions = is_array($projectOptions ?? null) ? $projectOptions : [];
$stoppers = is_array($stoppers ?? null) ? $stoppers : [];
$filterProject = (int) ($filterProject ?? 0);
$filterTalent = (int) ($filterTalent ?? 0);
$isAdmin = (bool) ($isAdmin ?? false);

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

$totalActive = 0;
$totalBlocked = 0;
foreach ($kanban as $status => $tasks) {
    if ($status !== 'done') $totalActive += count($tasks);
    if ($status === 'blocked') $totalBlocked += count($tasks);
}
$totalDone = count($kanban['done'] ?? []);
$totalAll = $totalActive + $totalDone;

$activeTab = $_GET['tab'] ?? 'kanban';
?>

<section class="pmo-shell">
    <!-- KPI Strip -->
    <div class="pmo-kpis">
        <div class="pmo-kpi"><span class="pmo-kpi-val"><?= $totalAll ?></span><span class="pmo-kpi-lbl">Total tareas</span></div>
        <div class="pmo-kpi"><span class="pmo-kpi-val"><?= $totalActive ?></span><span class="pmo-kpi-lbl">Activas</span></div>
        <div class="pmo-kpi"><span class="pmo-kpi-val"><?= $totalBlocked ?></span><span class="pmo-kpi-lbl">Bloqueadas</span></div>
        <div class="pmo-kpi"><span class="pmo-kpi-val"><?= $totalDone ?></span><span class="pmo-kpi-lbl">Completadas</span></div>
        <div class="pmo-kpi"><span class="pmo-kpi-val"><?= count($workload) ?></span><span class="pmo-kpi-lbl">Talentos</span></div>
    </div>

    <!-- Tabs -->
    <div class="pmo-tabs">
        <a href="?tab=kanban" class="pmo-tab <?= $activeTab === 'kanban' ? 'active' : '' ?>">Kanban</a>
        <a href="?tab=table" class="pmo-tab <?= $activeTab === 'table' ? 'active' : '' ?>">Tabla general</a>
        <a href="?tab=workload" class="pmo-tab <?= $activeTab === 'workload' ? 'active' : '' ?>">Carga del equipo</a>
        <button class="btn sm primary" onclick="document.getElementById('pmo-create-form').classList.toggle('tp-hidden')" style="margin-left:auto">+ Nueva tarea</button>
    </div>

    <!-- Create task form -->
    <form id="pmo-create-form" class="pmo-create-form tp-hidden" method="POST" action="<?= $basePath ?>/talent-panel/tasks/create">
        <div class="pmo-form-title"><strong>Nueva tarea</strong></div>
        <div class="pmo-form-grid">
            <label><span>Proyecto</span>
                <select name="project_id" required>
                    <option value="">Selecciona</option>
                    <?php foreach ($projectOptions as $p): ?>
                        <option value="<?= (int) ($p['id'] ?? 0) ?>"><?= htmlspecialchars($p['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><span>Título</span><input type="text" name="title" required maxlength="160" placeholder="Título de la tarea" /></label>
            <label><span>Descripción</span><input type="text" name="description" maxlength="500" placeholder="Descripción breve" /></label>
            <label><span>Prioridad</span>
                <select name="priority">
                    <option value="medium" selected>Media</option>
                    <option value="high">Alta</option>
                    <option value="low">Baja</option>
                </select>
            </label>
            <label><span>Horas estimadas</span><input type="number" name="estimated_hours" min="0" step="0.5" value="0" /></label>
            <label><span>Fecha compromiso</span><input type="date" name="due_date" /></label>
            <label><span>Asignar a talento</span>
                <select name="assignee_id">
                    <option value="0">Sin asignar</option>
                    <?php foreach ($talents as $t): ?>
                        <option value="<?= (int) ($t['id'] ?? 0) ?>"><?= htmlspecialchars($t['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="pmo-form-actions">
            <button type="submit" class="btn primary sm">Crear tarea</button>
            <button type="button" class="btn sm" onclick="document.getElementById('pmo-create-form').classList.add('tp-hidden')">Cancelar</button>
        </div>
    </form>

    <?php if ($activeTab === 'kanban'): ?>
    <!-- Kanban filters -->
    <div class="pmo-filters">
        <label><span>Proyecto</span>
            <select id="pmo-filter-project">
                <option value="">Todos</option>
                <?php foreach ($projectOptions as $p): ?>
                    <option value="<?= htmlspecialchars(strtolower($p['name'] ?? '')) ?>"><?= htmlspecialchars($p['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><span>Talento</span>
            <select id="pmo-filter-talent">
                <option value="">Todos</option>
                <?php foreach ($talents as $t): ?>
                    <option value="<?= htmlspecialchars(strtolower($t['name'] ?? '')) ?>"><?= htmlspecialchars($t['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <!-- Kanban Board -->
    <div class="pmo-kanban">
        <?php foreach ($statusMeta as $statusKey => $meta): ?>
            <div class="pmo-kanban-col">
                <div class="pmo-kanban-col-header" style="border-top: 3px solid <?= $meta['color'] ?>">
                    <span><?= $meta['icon'] ?></span>
                    <span class="pmo-kanban-col-title"><?= htmlspecialchars($meta['label']) ?></span>
                    <span class="pmo-kanban-col-badge"><?= count($kanban[$statusKey] ?? []) ?></span>
                </div>
                <div class="pmo-kanban-col-body" data-status="<?= $statusKey ?>">
                    <?php if (empty($kanban[$statusKey])): ?>
                        <div class="pmo-kanban-empty">Sin tareas</div>
                    <?php else: ?>
                        <?php foreach ($kanban[$statusKey] as $task): ?>
                            <?php
                            $taskId = (int) ($task['id'] ?? 0);
                            $prio = $priorityMeta[$task['priority'] ?? ''] ?? ['label' => 'Media', 'class' => 'prio-medium'];
                            $hasBlocker = !empty($stoppers[$taskId]);
                            $assigneeName = (string) ($task['assignee'] ?? 'Sin asignar');
                            $projectName = (string) ($task['project'] ?? '');
                            ?>
                            <div class="pmo-kanban-card <?= $hasBlocker ? 'has-blocker' : '' ?>"
                                 data-project="<?= htmlspecialchars(strtolower($projectName)) ?>"
                                 data-talent="<?= htmlspecialchars(strtolower($assigneeName)) ?>"
                                 draggable="true" data-task-id="<?= $taskId ?>">
                                <div class="pmo-card-head">
                                    <span class="tp-prio-dot <?= $prio['class'] ?>" title="<?= htmlspecialchars($prio['label']) ?>"></span>
                                    <span class="pmo-card-project"><?= htmlspecialchars($projectName) ?></span>
                                </div>
                                <div class="pmo-card-title"><?= htmlspecialchars($task['title'] ?? '') ?></div>
                                <div class="pmo-card-assignee">
                                    <span class="pmo-avatar-mini"><?= strtoupper(substr($assigneeName, 0, 1)) ?></span>
                                    <?= htmlspecialchars($assigneeName) ?>
                                </div>
                                <?php if ($hasBlocker): ?>
                                    <div class="pmo-card-blocker">
                                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="var(--danger)" stroke-width="2"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                                        Bloqueada
                                    </div>
                                <?php endif; ?>
                                <div class="pmo-card-bottom">
                                    <?php if (!empty($task['due_date'])): ?>
                                        <span><?= date('d M', strtotime($task['due_date'])) ?></span>
                                    <?php endif; ?>
                                    <span><?= (float) ($task['estimated_hours'] ?? 0) ?>h</span>
                                    <form method="POST" action="<?= $basePath ?>/talent-panel/tasks/<?= $taskId ?>/reassign" class="pmo-reassign-form">
                                        <select name="assignee_id" onchange="this.form.submit()" title="Reasignar talento">
                                            <option value="0">Sin asignar</option>
                                            <?php foreach ($talents as $t): ?>
                                                <option value="<?= (int) ($t['id'] ?? 0) ?>" <?= ((int) ($task['assignee_id'] ?? 0)) === ((int) ($t['id'] ?? 0)) ? 'selected' : '' ?>><?= htmlspecialchars($t['name'] ?? '') ?></option>
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
    <?php endif; ?>

    <?php if ($activeTab === 'table'): ?>
    <!-- Table View -->
    <div class="pmo-table-wrap">
        <table class="pmo-tasks-table">
            <thead>
                <tr>
                    <th>Proyecto</th>
                    <th>Tarea</th>
                    <th>Talento</th>
                    <th>Estado</th>
                    <th>Prioridad</th>
                    <th>Horas</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allTasks)): ?>
                    <tr><td colspan="8" style="text-align:center;color:var(--text-secondary);">No hay tareas registradas.</td></tr>
                <?php else: ?>
                    <?php foreach ($allTasks as $task): ?>
                        <?php
                        $sMeta = $statusMeta[$task['status'] ?? ''] ?? ['label' => ucfirst($task['status'] ?? ''), 'color' => 'var(--text-secondary)'];
                        $pMeta = $priorityMeta[$task['priority'] ?? ''] ?? ['label' => 'Media', 'class' => 'prio-medium'];
                        $openStoppers = (int) ($task['open_stoppers'] ?? 0);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($task['project'] ?? '') ?></td>
                            <td>
                                <strong><?= htmlspecialchars($task['title'] ?? '') ?></strong>
                                <?php if ($openStoppers > 0): ?>
                                    <span class="pmo-inline-blocker" title="<?= $openStoppers ?> bloqueo(s) activo(s)">
                                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="var(--danger)" stroke-width="2"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                                        <?= $openStoppers ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($task['assignee'] ?? 'Sin asignar') ?></td>
                            <td><span class="pmo-status-pill" style="color:<?= $sMeta['color'] ?>"><?= htmlspecialchars($sMeta['label']) ?></span></td>
                            <td><span class="tp-prio-dot <?= $pMeta['class'] ?>" style="display:inline-block;vertical-align:middle;margin-right:4px;"></span><?= htmlspecialchars($pMeta['label']) ?></td>
                            <td class="pmo-hours-cell">
                                <span><?= (float) ($task['estimated_hours'] ?? 0) ?>h est.</span>
                                <span><?= (float) ($task['actual_hours'] ?? 0) ?>h real</span>
                            </td>
                            <td><?= !empty($task['due_date']) ? date('d/m/Y', strtotime($task['due_date'])) : '-' ?></td>
                            <td class="pmo-table-actions">
                                <form method="POST" action="<?= $basePath ?>/talent-panel/tasks/<?= (int) $task['id'] ?>/status" style="display:inline-flex;gap:4px;">
                                    <select name="status" style="padding:4px 6px;font-size:11px;border-radius:6px;border:1px solid var(--border);background:var(--surface);color:var(--text-primary);">
                                        <?php foreach ($statusMeta as $sv => $sm): ?>
                                            <option value="<?= $sv ?>" <?= $sv === ($task['status'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars($sm['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn sm">Aplicar</button>
                                </form>
                                <a href="<?= $basePath ?>/tasks/<?= (int) $task['id'] ?>/edit" class="btn sm" title="Editar">Editar</a>
                                <?php if ($isAdmin): ?>
                                    <form method="POST" action="<?= $basePath ?>/talent-panel/tasks/<?= (int) $task['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('¿Eliminar esta tarea y todos sus registros?')">
                                        <button type="submit" class="btn sm danger">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($activeTab === 'workload'): ?>
    <!-- Workload View -->
    <div class="pmo-workload-grid">
        <?php if (empty($workload)): ?>
            <div class="pmo-empty">No hay talentos con tareas asignadas.</div>
        <?php else: ?>
            <?php foreach ($workload as $w): ?>
                <?php
                $wActive = (int) ($w['active_tasks'] ?? 0);
                $wBlocked = (int) ($w['blocked_tasks'] ?? 0);
                $wEstimated = (float) ($w['total_estimated'] ?? 0);
                $wActual = (float) ($w['total_actual'] ?? 0);
                $wCapacity = (float) ($w['weekly_capacity'] ?? 40);
                $loadPct = $wCapacity > 0 ? min(round(($wEstimated / $wCapacity) * 100), 100) : 0;
                $loadColor = $loadPct > 90 ? 'var(--danger)' : ($loadPct > 70 ? 'var(--warning)' : 'var(--success)');
                ?>
                <div class="pmo-wl-card">
                    <div class="pmo-wl-header">
                        <div class="pmo-wl-avatar"><?= strtoupper(substr($w['talent_name'] ?? 'T', 0, 1)) ?></div>
                        <div class="pmo-wl-info">
                            <strong><?= htmlspecialchars($w['talent_name'] ?? '') ?></strong>
                            <span class="pmo-wl-sub"><?= $wActive ?> tareas activas</span>
                        </div>
                    </div>
                    <div class="pmo-wl-stats">
                        <div class="pmo-wl-stat"><span>Estimadas</span><strong><?= $wEstimated ?>h</strong></div>
                        <div class="pmo-wl-stat"><span>Reales</span><strong><?= $wActual ?>h</strong></div>
                        <div class="pmo-wl-stat"><span>Bloqueadas</span><strong style="color:<?= $wBlocked > 0 ? 'var(--danger)' : 'inherit' ?>"><?= $wBlocked ?></strong></div>
                    </div>
                    <div class="pmo-wl-bar-wrap">
                        <div class="pmo-wl-bar-bg">
                            <div class="pmo-wl-bar-fill" style="width:<?= $loadPct ?>%;background:<?= $loadColor ?>"></div>
                        </div>
                        <span class="pmo-wl-pct" style="color:<?= $loadColor ?>"><?= $loadPct ?>% carga</span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</section>

<style>
.pmo-shell { display:flex; flex-direction:column; gap:16px; }
.tp-hidden { display:none !important; }
.pmo-kpis { display:grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap:12px; }
.pmo-kpi { display:flex; flex-direction:column; align-items:center; padding:14px 12px; border-radius:14px; border:1px solid var(--border); background:var(--surface); }
.pmo-kpi-val { font-size:24px; font-weight:800; color:var(--text-primary); }
.pmo-kpi-lbl { font-size:11px; color:var(--text-secondary); font-weight:600; text-transform:uppercase; letter-spacing:0.03em; }

.pmo-tabs { display:flex; gap:6px; align-items:center; border-bottom:1px solid var(--border); padding-bottom:8px; }
.pmo-tab { text-decoration:none; padding:8px 14px; border-radius:10px 10px 0 0; font-size:13px; font-weight:700; color:var(--text-secondary); border:1px solid transparent; border-bottom:none; transition: all .15s; }
.pmo-tab:hover { color:var(--text-primary); background:color-mix(in srgb, var(--surface) 80%, var(--background)); }
.pmo-tab.active { color:var(--text-primary); background:var(--surface); border-color:var(--border); border-bottom:1px solid var(--surface); margin-bottom:-1px; }

.pmo-create-form { border:1px dashed var(--border); border-radius:14px; padding:16px; background:color-mix(in srgb, var(--surface) 90%, var(--background)); }
.pmo-form-title { margin-bottom:10px; }
.pmo-form-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px; }
.pmo-form-grid label { display:flex; flex-direction:column; gap:4px; font-size:12px; font-weight:700; color:var(--text-primary); }
.pmo-form-grid input, .pmo-form-grid select { padding:8px 10px; border-radius:10px; border:1px solid var(--border); background:var(--surface); color:var(--text-primary); font-size:13px; }
.pmo-form-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:10px; }

.pmo-filters { display:flex; gap:12px; flex-wrap:wrap; }
.pmo-filters label { display:flex; flex-direction:column; gap:4px; font-size:12px; font-weight:700; color:var(--text-primary); }
.pmo-filters select { padding:8px 10px; border-radius:10px; border:1px solid var(--border); background:var(--surface); color:var(--text-primary); font-size:13px; min-width:180px; }

.pmo-kanban { display:grid; grid-template-columns: repeat(5, 1fr); gap:12px; }
.pmo-kanban-col { display:flex; flex-direction:column; border-radius:14px; border:1px solid var(--border); background:color-mix(in srgb, var(--surface) 70%, var(--background)); overflow:hidden; }
.pmo-kanban-col-header { display:flex; align-items:center; gap:8px; padding:10px 12px; font-size:12px; font-weight:700; background:color-mix(in srgb, var(--surface) 92%, var(--background)); }
.pmo-kanban-col-title { flex:1; }
.pmo-kanban-col-badge { background:var(--border); border-radius:999px; padding:2px 8px; font-size:10px; font-weight:700; }
.pmo-kanban-col-body { flex:1; display:flex; flex-direction:column; gap:8px; padding:8px; min-height:80px; }
.pmo-kanban-col-body.drag-over { background:color-mix(in srgb, var(--primary) 8%, var(--background)); }
.pmo-kanban-empty { text-align:center; color:var(--text-secondary); font-size:11px; padding:16px 0; }

.pmo-kanban-card { border:1px solid var(--border); border-radius:10px; padding:10px; background:var(--surface); cursor:grab; transition: box-shadow .15s; }
.pmo-kanban-card:hover { box-shadow:0 4px 12px color-mix(in srgb, var(--primary) 10%, transparent); }
.pmo-kanban-card.has-blocker { border-left:3px solid var(--danger); }
.pmo-kanban-card.dragging { opacity:0.5; }
.pmo-card-head { display:flex; align-items:center; gap:6px; margin-bottom:4px; }
.pmo-card-project { font-size:10px; color:var(--text-secondary); font-weight:600; }
.pmo-card-title { font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:4px; }
.pmo-card-assignee { display:flex; align-items:center; gap:4px; font-size:11px; color:var(--text-secondary); margin-bottom:4px; }
.pmo-avatar-mini { width:18px; height:18px; border-radius:50%; background:color-mix(in srgb, var(--primary) 20%, var(--surface)); font-size:9px; font-weight:700; display:inline-flex; align-items:center; justify-content:center; color:var(--text-primary); }
.pmo-card-blocker { display:flex; align-items:center; gap:4px; font-size:10px; font-weight:700; color:var(--danger); margin-bottom:4px; }
.pmo-card-bottom { display:flex; align-items:center; gap:6px; font-size:10px; color:var(--text-secondary); flex-wrap:wrap; }
.pmo-reassign-form select { padding:2px 4px; font-size:10px; border-radius:6px; border:1px solid var(--border); background:var(--surface); color:var(--text-primary); max-width:100px; }

.tp-prio-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
.prio-high { background:var(--danger); }
.prio-medium { background:var(--warning); }
.prio-low { background:var(--success); }

.pmo-table-wrap { overflow-x:auto; }
.pmo-tasks-table { font-size:13px; }
.pmo-tasks-table th { font-size:11px; }
.pmo-hours-cell { display:flex; flex-direction:column; gap:2px; font-size:12px; color:var(--text-secondary); }
.pmo-status-pill { font-weight:700; font-size:12px; }
.pmo-table-actions { display:flex; gap:4px; flex-wrap:wrap; align-items:center; }
.pmo-inline-blocker { display:inline-flex; align-items:center; gap:2px; font-size:10px; font-weight:700; color:var(--danger); margin-left:4px; }

.pmo-workload-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:14px; }
.pmo-wl-card { border:1px solid var(--border); border-radius:14px; padding:16px; background:var(--surface); display:flex; flex-direction:column; gap:12px; }
.pmo-wl-header { display:flex; align-items:center; gap:10px; }
.pmo-wl-avatar { width:36px; height:36px; border-radius:50%; background:color-mix(in srgb, var(--primary) 18%, var(--surface)); display:flex; align-items:center; justify-content:center; font-weight:800; color:var(--text-primary); }
.pmo-wl-info { display:flex; flex-direction:column; gap:2px; }
.pmo-wl-info strong { font-size:14px; }
.pmo-wl-sub { font-size:11px; color:var(--text-secondary); }
.pmo-wl-stats { display:grid; grid-template-columns: repeat(3, 1fr); gap:8px; }
.pmo-wl-stat { display:flex; flex-direction:column; align-items:center; gap:2px; }
.pmo-wl-stat span { font-size:10px; color:var(--text-secondary); text-transform:uppercase; font-weight:600; }
.pmo-wl-stat strong { font-size:16px; font-weight:800; }
.pmo-wl-bar-wrap { display:flex; flex-direction:column; gap:4px; }
.pmo-wl-bar-bg { height:6px; border-radius:4px; background:var(--border); overflow:hidden; }
.pmo-wl-bar-fill { height:100%; border-radius:4px; transition: width .3s; }
.pmo-wl-pct { font-size:11px; font-weight:700; text-align:right; }

.pmo-empty { padding:24px; text-align:center; color:var(--text-secondary); font-size:13px; border:1px dashed var(--border); border-radius:14px; }

@media (max-width: 1200px) { .pmo-kanban { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 900px) { .pmo-kanban { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .pmo-kanban { grid-template-columns: 1fr; } }
</style>

<script>
(() => {
    // Kanban drag-and-drop
    const cols = document.querySelectorAll('.pmo-kanban-col-body');
    const cards = document.querySelectorAll('.pmo-kanban-card[draggable]');

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

    // Kanban filters
    const filterProject = document.getElementById('pmo-filter-project');
    const filterTalent = document.getElementById('pmo-filter-talent');
    if (filterProject && filterTalent) {
        const applyFilters = () => {
            const fp = (filterProject.value || '').toLowerCase();
            const ft = (filterTalent.value || '').toLowerCase();
            document.querySelectorAll('.pmo-kanban-card').forEach(card => {
                const matchP = !fp || (card.dataset.project || '').toLowerCase() === fp;
                const matchT = !ft || (card.dataset.talent || '').toLowerCase() === ft;
                card.style.display = (matchP && matchT) ? '' : 'none';
            });
        };
        filterProject.addEventListener('change', applyFilters);
        filterTalent.addEventListener('change', applyFilters);
    }
})();
</script>
