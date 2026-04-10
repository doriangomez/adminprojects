<?php
$basePath = $basePath ?? '';
$project = $project ?? [];
$tasks = is_array($tasks ?? null) ? $tasks : [];
$talents = is_array($talents ?? null) ? $talents : [];
$canManage = !empty($canManage);
$canCreateTask = !empty($canCreateTask) || $canManage;
$isClosed = !empty($isClosed);
$canAddTask = $canCreateTask && !$isClosed;
$kanbanColumns = is_array($kanbanColumns ?? null) ? $kanbanColumns : [];
$kanbanStatusOrder = is_array($kanbanStatusOrder ?? null) ? $kanbanStatusOrder : ['todo', 'in_progress', 'review', 'blocked', 'done'];
$kanbanStatusMeta = is_array($kanbanStatusMeta ?? null) ? $kanbanStatusMeta : [
    'todo' => ['label' => 'Pendiente', 'icon' => '⏳', 'class' => 'status-muted', 'accent' => '#94a3b8'],
    'in_progress' => ['label' => 'En progreso', 'icon' => '🔄', 'class' => 'status-info', 'accent' => '#0ea5e9'],
    'review' => ['label' => 'En revisión', 'icon' => '📝', 'class' => 'status-warning', 'accent' => '#f59e0b'],
    'blocked' => ['label' => 'Bloqueada', 'icon' => '⛔', 'class' => 'status-danger', 'accent' => '#ef4444'],
    'done' => ['label' => 'Completada', 'icon' => '✅', 'class' => 'status-success', 'accent' => '#22c55e'],
];
$selectedRisks = is_array($project['risks'] ?? null) ? $project['risks'] : [];
$riskLevel = strtolower((string) ($project['risk_level'] ?? ''));
$riskLabel = $project['health_label'] ?? $project['health'] ?? 'Sin evaluación';
$riskScore = $project['risk_score'] ?? null;
$riskLevelLabel = $riskLevel !== '' ? $riskLevel : 'n/a';
$riskBadgeClass = match ($riskLevel) {
    'alto' => 'status-danger',
    'medio' => 'status-warning',
    'bajo' => 'status-success',
    default => 'status-muted',
};
$riskCategoryMap = [
    'Alcance' => ['alcance', 'scope'],
    'Costos' => ['costo', 'cost', 'presupuesto'],
    'Calidad' => ['calidad', 'quality'],
    'Tiempo' => ['tiempo', 'schedule', 'plazo'],
    'Dependencias' => ['dependencia', 'dependency'],
];
$riskCategoryIcons = [
    'Alcance' => '🧭',
    'Costos' => '💸',
    'Calidad' => '✅',
    'Tiempo' => '⏱️',
    'Dependencias' => '🔗',
    'Otros' => '⚠️',
];
$taskStatusMeta = [
    'todo' => ['label' => 'Pendiente', 'icon' => '⏳', 'class' => 'status-muted'],
    'pending' => ['label' => 'Pendiente', 'icon' => '⏳', 'class' => 'status-muted'],
    'in_progress' => ['label' => 'En progreso', 'icon' => '🔄', 'class' => 'status-info'],
    'review' => ['label' => 'En revisión', 'icon' => '📝', 'class' => 'status-warning'],
    'blocked' => ['label' => 'Bloqueada', 'icon' => '⛔', 'class' => 'status-danger'],
    'done' => ['label' => 'Completada', 'icon' => '✅', 'class' => 'status-success'],
    'completed' => ['label' => 'Completada', 'icon' => '✅', 'class' => 'status-success'],
];
$riskCategories = [];
foreach ($selectedRisks as $riskCode) {
    $normalized = strtolower((string) $riskCode);
    $matched = false;
    foreach ($riskCategoryMap as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (str_contains($normalized, $keyword)) {
                $riskCategories[$category][] = $riskCode;
                $matched = true;
                break;
            }
        }
        if ($matched) {
            break;
        }
    }
    if (!$matched) {
        $riskCategories['Otros'][] = $riskCode;
    }
}
?>

<section class="project-shell">
    <header class="project-header">
        <div class="project-title-block">
            <p class="eyebrow">Tareas y control</p>
            <h2><?= htmlspecialchars($project['name'] ?? '') ?></h2>
            <small class="section-muted">Checklist ejecutivo para auditoría, tareas críticas y riesgos.</small>
        </div>
        <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>?view=resumen">Volver al resumen</a>
    </header>

    <?php
    $activeTab = 'tareas';
    require __DIR__ . '/_tabs.php';
    ?>

    <section class="risk-overview">
        <div>
            <p class="eyebrow">Riesgo general</p>
            <h4>Estado consolidado del proyecto</h4>
        </div>
        <div class="risk-summary">
            <div class="risk-summary__item">
                <span>Nivel de riesgo</span>
                <strong><?= htmlspecialchars($riskLabel) ?></strong>
                <span class="badge status-badge <?= $riskBadgeClass ?>">Nivel <?= htmlspecialchars($riskLevelLabel) ?></span>
            </div>
            <div class="risk-summary__item">
                <span>Score de riesgo</span>
                <strong><?= $riskScore !== null ? number_format((float) $riskScore, 1) : 'N/A' ?></strong>
                <small class="section-muted">Escala agregada por catálogo</small>
            </div>
            <div class="risk-summary__item">
                <span>Riesgos seleccionados</span>
                <strong><?= count($selectedRisks) ?></strong>
                <small class="section-muted">Activos en la evaluación actual</small>
            </div>
        </div>
    </section>

    <section class="risk-section">
        <div>
            <p class="eyebrow">Mapa de riesgos</p>
            <h4>Riesgos agrupados por categoría</h4>
        </div>
        <?php if (empty($selectedRisks)): ?>
            <p class="section-muted">No hay riesgos seleccionados para este proyecto.</p>
        <?php else: ?>
            <div class="risk-grid">
                <?php foreach ($riskCategories as $category => $risks): ?>
                    <div class="risk-card">
                        <div class="risk-card__header">
                            <span class="risk-card__icon"><?= htmlspecialchars($riskCategoryIcons[$category] ?? $riskCategoryIcons['Otros']) ?></span>
                            <strong><?= htmlspecialchars($category) ?></strong>
                        </div>
                        <ul class="risk-checklist">
                            <?php foreach ($risks as $risk): ?>
                                <li>
                                    <span class="risk-check">✓</span>
                                    <span><?= htmlspecialchars($risk) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="task-section">
        <div class="task-section-header">
            <div>
                <p class="eyebrow">Controles y tareas</p>
                <h4>Checklist operativo del proyecto</h4>
                <small class="section-muted">Evidencias y controles en ejecución.</small>
            </div>
            <div class="view-toggle" role="tablist" aria-label="Vista de tareas">
                <button type="button" class="action-btn small view-toggle-btn is-active" data-task-view="list">Lista</button>
                <button type="button" class="action-btn small view-toggle-btn" data-task-view="kanban">Kanban</button>
            </div>
        </div>

        <?php if ($canAddTask): ?>
            <form class="task-create-form" method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/tasks">
                <div class="task-create-form__header">
                    <strong>Agregar tarea</strong>
                    <span class="section-muted">
                        <?= $canManage
                            ? 'Disponible para proyectos activos con permisos de gestión.'
                            : 'Como talento asignado puedes crear tareas que quedarán a tu nombre.' ?>
                    </span>
                </div>
                <div class="task-create-form__grid">
                    <label class="task-field">
                        Título de la tarea
                        <input type="text" name="title" required maxlength="160" placeholder="Ej. Levantar riesgos de fase 02" />
                    </label>
                    <label class="task-field">
                        Prioridad
                        <select name="priority">
                            <option value="medium" selected>Media</option>
                            <option value="high">Alta</option>
                            <option value="low">Baja</option>
                        </select>
                    </label>
                    <label class="task-field">
                        Horas estimadas
                        <input type="number" name="estimated_hours" min="0" step="0.5" value="0" />
                    </label>
                    <label class="task-field">
                        Fecha límite
                        <input type="date" name="due_date" />
                    </label>
                    <?php if ($canManage): ?>
                        <label class="task-field">
                            Responsable
                            <select name="assignee_id">
                                <option value="0">Sin asignar</option>
                                <?php foreach ($talents as $talent): ?>
                                    <option value="<?= (int) ($talent['id'] ?? 0) ?>"><?= htmlspecialchars($talent['name'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php else: ?>
                        <label class="task-field">
                            Asignación
                            <input type="text" value="Se asignará automáticamente a tu perfil" disabled />
                        </label>
                    <?php endif; ?>
                    <input type="hidden" name="status" value="todo" data-project-task-create-status />
                </div>
                <div class="task-create-form__actions">
                    <button type="submit" class="action-btn primary">Agregar tarea</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if (empty($tasks)): ?>
            <p class="section-muted">No hay tareas registradas para este proyecto.</p>
        <?php else: ?>
            <div class="task-list" data-project-task-list>
                <?php foreach ($tasks as $task): ?>
                    <?php $taskStatus = $taskStatusMeta[$task['status'] ?? ''] ?? ['label' => 'Pendiente', 'icon' => '📌', 'class' => 'status-muted']; ?>
                    <div class="task-card">
                        <div>
                            <strong><?= htmlspecialchars($task['title'] ?? '') ?></strong>
                            <small class="section-muted">Responsable: <?= htmlspecialchars($task['assignee'] ?? 'Sin asignar') ?></small>
                            <?php if (!empty($task['in_schedule'])): ?>
                                <span class="status-badge status-info" style="margin-left:6px;">🗓️ En cronograma</span>
                            <?php endif; ?>
                        </div>
                        <div class="task-meta">
                            <span class="status-badge <?= htmlspecialchars($taskStatus['class']) ?>">
                                <?= htmlspecialchars($taskStatus['icon']) ?> <?= htmlspecialchars($taskStatus['label']) ?>
                            </span>
                            <small class="section-muted">Prioridad <?= htmlspecialchars($task['priority'] ?? 'Media') ?></small>
                            <small class="section-muted">Horas <?= htmlspecialchars((string) ($task['actual_hours'] ?? 0)) ?>/<?= htmlspecialchars((string) ($task['estimated_hours'] ?? 0)) ?></small>
                            <small class="section-muted">Vence <?= htmlspecialchars((string) ($task['due_date'] ?? '')) ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="project-kanban" data-project-task-kanban hidden>
                <?php foreach ($kanbanStatusOrder as $statusKey): ?>
                    <?php
                    $meta = $kanbanStatusMeta[$statusKey] ?? ['label' => ucfirst($statusKey), 'icon' => '📌', 'class' => 'status-muted', 'accent' => '#94a3b8'];
                    $items = is_array($kanbanColumns[$statusKey] ?? null) ? $kanbanColumns[$statusKey] : [];
                    ?>
                    <article class="project-kanban-column" data-status-column="<?= htmlspecialchars($statusKey) ?>" style="--status-accent: <?= htmlspecialchars((string) ($meta['accent'] ?? '#94a3b8')) ?>">
                        <header class="project-kanban-column__header">
                            <h5><?= htmlspecialchars((string) ($meta['icon'] ?? '📌')) ?> <?= htmlspecialchars((string) ($meta['label'] ?? $statusKey)) ?></h5>
                            <span class="count-pill" data-status-count><?= count($items) ?></span>
                        </header>
                        <div class="project-kanban-column__body" data-column-cards>
                            <?php if ($items === []): ?>
                                <p class="empty-col">Sin tareas</p>
                            <?php else: ?>
                                <?php foreach ($items as $task): ?>
                                    <?php
                                    $priority = strtolower((string) ($task['priority'] ?? 'medium'));
                                    $priorityDot = match ($priority) {
                                        'high' => '#ef4444',
                                        'low' => '#6b7280',
                                        default => '#f59e0b',
                                    };
                                    $normalizedTaskStatus = strtolower((string) ($task['status'] ?? 'todo'));
                                    $normalizedTaskStatus = match ($normalizedTaskStatus) {
                                        'pending' => 'todo',
                                        'completed' => 'done',
                                        default => $normalizedTaskStatus,
                                    };
                                    $isOverdue = false;
                                    $dueDate = trim((string) ($task['due_date'] ?? ''));
                                    if ($dueDate !== '' && $normalizedTaskStatus !== 'done') {
                                        $dueTs = strtotime($dueDate);
                                        $isOverdue = $dueTs !== false && $dueTs < strtotime(date('Y-m-d'));
                                    }
                                    $assigneeName = trim((string) ($task['assignee'] ?? ''));
                                    $assigneeInitial = $assigneeName !== '' ? strtoupper(substr($assigneeName, 0, 1)) : '·';
                                    ?>
                                    <article class="project-kanban-card" draggable="true" data-task-card data-task-id="<?= (int) ($task['id'] ?? 0) ?>" data-task-status="<?= htmlspecialchars((string) ($task['kanban_status'] ?? $statusKey)) ?>">
                                        <h6><?= htmlspecialchars((string) ($task['title'] ?? 'Sin título')) ?></h6>
                                        <div class="project-kanban-card__meta">
                                            <span class="assignee-pill" title="<?= htmlspecialchars($assigneeName !== '' ? $assigneeName : 'Sin asignar') ?>"><?= htmlspecialchars($assigneeInitial) ?></span>
                                            <span class="priority-dot" style="--priority-color: <?= htmlspecialchars($priorityDot) ?>" title="Prioridad <?= htmlspecialchars($priority) ?>"></span>
                                            <?php if ($dueDate !== ''): ?>
                                                <small class="<?= $isOverdue ? 'due-overdue' : 'section-muted' ?>">Vence <?= htmlspecialchars($dueDate) ?></small>
                                            <?php endif; ?>
                                            <?php if ((int) ($task['subtasks_total'] ?? 0) > 0): ?>
                                                <small class="section-muted"><?= (int) ($task['subtasks_completed'] ?? 0) ?>/<?= (int) ($task['subtasks_total'] ?? 0) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="project-kanban-open" data-open-task="<?= (int) ($task['id'] ?? 0) ?>">Ver / editar</button>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($canAddTask): ?>
                            <button type="button" class="action-btn small" data-add-task-status="<?= htmlspecialchars($statusKey) ?>">＋ Agregar tarea</button>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <aside class="task-drawer" data-task-drawer hidden>
        <div class="task-drawer__backdrop" data-task-drawer-close></div>
        <div class="task-drawer__panel">
            <div class="task-drawer__header">
                <h4>Detalle de tarea</h4>
                <button type="button" class="action-btn small" data-task-drawer-close>Cerrar</button>
            </div>
            <form class="task-drawer__form" data-task-drawer-form>
                <input type="hidden" name="redirect_to" value="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/tasks" />
                <label>Título<input type="text" name="title" required maxlength="160" /></label>
                <label>Estado
                    <select name="status" required>
                        <?php foreach ($kanbanStatusOrder as $statusValue): ?>
                            <?php $statusCfg = $kanbanStatusMeta[$statusValue] ?? ['label' => ucfirst($statusValue)]; ?>
                            <option value="<?= htmlspecialchars($statusValue) ?>"><?= htmlspecialchars((string) ($statusCfg['label'] ?? $statusValue)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Prioridad
                    <select name="priority" required>
                        <option value="high">Alta</option>
                        <option value="medium">Media</option>
                        <option value="low">Baja</option>
                    </select>
                </label>
                <label>Horas estimadas<input type="number" min="0" step="0.5" name="estimated_hours" /></label>
                <label>Fecha límite<input type="date" name="due_date" /></label>
                <label>Responsable
                    <select name="assignee_id">
                        <option value="0">Sin asignar</option>
                        <?php foreach ($talents as $talent): ?>
                            <option value="<?= (int) ($talent['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($talent['name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Actividad cronograma
                    <select name="schedule_activity_id">
                        <option value="0">Sin vincular</option>
                    </select>
                </label>
                <div class="task-drawer__actions">
                    <button type="submit" class="action-btn primary">Guardar</button>
                </div>
            </form>
        </div>
    </aside>
</section>

<style>
    .project-shell { display:flex; flex-direction:column; gap:16px; }
    .project-header { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap; border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); }
    .project-title-block { display:flex; flex-direction:column; gap:8px; }
    .project-title-block h2 { margin:0; color: var(--text-primary); }
    .risk-overview, .risk-section, .task-section { border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); display:flex; flex-direction:column; gap:12px; }
    .risk-summary { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; }
    .risk-summary__item { border:1px solid var(--border); border-radius:12px; padding:10px; background: color-mix(in srgb, var(--surface) 84%, var(--background) 16%); display:flex; flex-direction:column; gap:4px; }
    .risk-summary__item span { font-size:12px; text-transform:uppercase; color: var(--text-secondary); font-weight:700; }
    .risk-summary__item strong { font-size:16px; color: var(--text-primary); }
    .risk-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; }
    .risk-card { border:1px solid var(--border); border-radius:12px; padding:12px; background: color-mix(in srgb, var(--surface) 84%, var(--background) 16%); display:flex; flex-direction:column; gap:10px; }
    .risk-card__header { display:flex; align-items:center; gap:8px; }
    .risk-card__icon { width:30px; height:30px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background: color-mix(in srgb, var(--accent) 20%, var(--surface) 80%); }
    .risk-checklist { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:6px; }
    .risk-checklist li { display:flex; align-items:center; gap:8px; font-weight:600; }
    .risk-check { width:22px; height:22px; border-radius:999px; background: color-mix(in srgb, var(--success) 22%, var(--surface) 78%); color: var(--text-primary); display:inline-flex; align-items:center; justify-content:center; font-size:12px; }
    .task-section-header { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap; }
    .view-toggle { display:flex; gap:6px; }
    .view-toggle-btn.is-active { background: var(--primary); border-color: var(--primary); color: var(--text-primary); }
    .task-list { display:flex; flex-direction:column; gap:10px; }
    .task-card { border:1px solid var(--border); border-radius:12px; padding:12px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; background: color-mix(in srgb, var(--surface) 84%, var(--background) 16%); }
    .task-meta { display:flex; flex-direction:column; gap:4px; align-items:flex-end; }
    .status-badge { font-size:12px; font-weight:700; padding:4px 8px; border-radius:999px; border:1px solid var(--background); display:inline-flex; align-items:center; gap:6px; }
    .status-muted { background: color-mix(in srgb, var(--surface) 80%, var(--border) 20%); color: var(--text-secondary); border-color: var(--border); }
    .status-info { background: color-mix(in srgb, var(--accent) 18%, var(--surface) 82%); color: var(--text-primary); border-color: color-mix(in srgb, var(--accent) 35%, var(--border) 65%); }
    .status-success { background: color-mix(in srgb, var(--success) 24%, var(--surface) 76%); color: var(--text-primary); border-color: color-mix(in srgb, var(--success) 35%, var(--border) 65%); }
    .status-warning { background: color-mix(in srgb, var(--warning) 24%, var(--surface) 76%); color: var(--text-primary); border-color: color-mix(in srgb, var(--warning) 40%, var(--border) 60%); }
    .status-danger { background: color-mix(in srgb, var(--danger) 22%, var(--surface) 78%); color: var(--text-primary); border-color: color-mix(in srgb, var(--danger) 35%, var(--border) 65%); }
    .action-btn { background: var(--surface); color: var(--text-primary); border:1px solid var(--border); border-radius:8px; padding:8px 10px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
    .action-btn.primary { background: var(--primary); color: var(--text-primary); border-color: var(--primary); }
    .task-create-form { border:1px dashed var(--border); border-radius:14px; padding:14px; display:flex; flex-direction:column; gap:12px; background: color-mix(in srgb, var(--surface) 90%, var(--background) 10%); }
    .task-create-form__header { display:flex; flex-direction:column; gap:4px; }
    .task-create-form__grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; }
    .task-field { display:flex; flex-direction:column; gap:6px; font-weight:600; color: var(--text-primary); font-size:12px; }
    .task-field input, .task-field select { padding:8px 10px; border-radius:10px; border:1px solid var(--border); background: var(--surface); color: var(--text-primary); font-size:13px; }
    .task-create-form__actions { display:flex; justify-content:flex-end; }
    .project-kanban { display:grid; grid-template-columns: repeat(5, minmax(180px, 1fr)); gap:10px; }
    .project-kanban-column { border:1px solid var(--border); border-radius:12px; padding:10px; background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%); display:flex; flex-direction:column; gap:8px; min-height:260px; }
    .project-kanban-column__header { display:flex; align-items:center; justify-content:space-between; border-top:3px solid var(--status-accent); padding-top:6px; }
    .project-kanban-column__header h5 { margin:0; font-size:12px; text-transform:uppercase; color: var(--text-secondary); }
    .project-kanban-column__body { display:flex; flex-direction:column; gap:8px; min-height:160px; }
    .project-kanban-card { border:1px solid var(--border); border-radius:10px; background: var(--surface); padding:10px; display:flex; flex-direction:column; gap:8px; cursor:grab; }
    .project-kanban-card.is-dragging { opacity:.5; }
    .project-kanban-card h6 { margin:0; font-size:13px; color: var(--text-primary); }
    .project-kanban-card__meta { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .assignee-pill { width:22px; height:22px; border-radius:999px; background: color-mix(in srgb, var(--primary) 18%, var(--surface)); display:inline-flex; align-items:center; justify-content:center; font-weight:700; font-size:11px; }
    .priority-dot { width:10px; height:10px; border-radius:999px; background: var(--priority-color); display:inline-block; }
    .due-overdue { color: var(--danger); font-weight:700; font-size:11px; }
    .project-kanban-open { border:1px solid var(--border); border-radius:8px; background: color-mix(in srgb, var(--surface) 92%, var(--background)); font-size:12px; font-weight:600; padding:6px 8px; cursor:pointer; text-align:left; }
    .project-kanban-column.is-drop-target { outline:2px dashed var(--primary); outline-offset:2px; }
    .task-drawer { position:fixed; inset:0; z-index:70; display:flex; justify-content:flex-end; }
    .task-drawer[hidden] { display:none; }
    .task-drawer__backdrop { position:absolute; inset:0; background: color-mix(in srgb, var(--text-primary) 28%, transparent); }
    .task-drawer__panel { position:relative; width:min(420px, 100%); height:100%; background:var(--surface); border-left:1px solid var(--border); padding:14px; overflow:auto; display:flex; flex-direction:column; gap:12px; }
    .task-drawer__header { display:flex; justify-content:space-between; align-items:center; }
    .task-drawer__header h4 { margin:0; }
    .task-drawer__form { display:flex; flex-direction:column; gap:10px; }
    .task-drawer__form label { display:flex; flex-direction:column; gap:6px; font-size:12px; font-weight:700; color:var(--text-secondary); }
    .task-drawer__form input, .task-drawer__form select { padding:8px 10px; border:1px solid var(--border); border-radius:8px; background:var(--surface); color:var(--text-primary); }
    .task-drawer__actions { display:flex; justify-content:flex-end; }
    @media (max-width: 1200px) { .project-kanban { grid-template-columns: repeat(2, minmax(180px, 1fr)); } }
    @media (max-width: 800px) { .project-kanban { grid-template-columns: 1fr; } }
</style>

<script>
(() => {
    const projectId = <?= (int) ($project['id'] ?? 0) ?>;
    const listContainer = document.querySelector('[data-project-task-list]');
    const kanbanContainer = document.querySelector('[data-project-task-kanban]');
    const viewButtons = document.querySelectorAll('[data-task-view]');
    const drawer = document.querySelector('[data-task-drawer]');
    const drawerForm = document.querySelector('[data-task-drawer-form]');
    const drawerCloseButtons = document.querySelectorAll('[data-task-drawer-close]');
    const addTaskButtons = document.querySelectorAll('[data-add-task-status]');
    const createStatusInput = document.querySelector('[data-project-task-create-status]');

    if (!kanbanContainer || !listContainer) return;

    const normalizeStatus = (status) => {
        const value = String(status || '').toLowerCase().trim();
        if (value === 'pending') return 'todo';
        if (value === 'completed') return 'done';
        return value;
    };

    const storageKey = `project-task-view-${projectId}`;
    const applyView = (view) => {
        const isKanban = view === 'kanban';
        kanbanContainer.hidden = !isKanban;
        listContainer.hidden = isKanban;
        viewButtons.forEach((button) => button.classList.toggle('is-active', button.dataset.taskView === view));
        sessionStorage.setItem(storageKey, view);
    };
    applyView(sessionStorage.getItem(storageKey) || 'list');
    viewButtons.forEach((button) => button.addEventListener('click', () => applyView(button.dataset.taskView || 'list')));

    const updateCounts = () => {
        document.querySelectorAll('[data-status-column]').forEach((column) => {
            const count = column.querySelectorAll('[data-task-card]').length;
            const badge = column.querySelector('[data-status-count]');
            if (badge) badge.textContent = String(count);
        });
    };

    const showDrawer = () => { if (drawer) drawer.hidden = false; };
    const closeDrawer = () => { if (drawer) drawer.hidden = true; };
    drawerCloseButtons.forEach((button) => button.addEventListener('click', closeDrawer));

    const fillDrawer = (task, scheduleActivities = []) => {
        if (!drawerForm || !task) return;
        drawerForm.action = `/tasks/${task.id}/update`;
        drawerForm.querySelector('input[name="title"]').value = task.title || '';
        drawerForm.querySelector('select[name="status"]').value = normalizeStatus(task.status || 'todo');
        drawerForm.querySelector('select[name="priority"]').value = String(task.priority || 'medium').toLowerCase();
        drawerForm.querySelector('input[name="estimated_hours"]').value = String(task.estimated_hours ?? 0);
        drawerForm.querySelector('input[name="due_date"]').value = task.due_date || '';
        const assigneeSelect = drawerForm.querySelector('select[name="assignee_id"]');
        if (assigneeSelect) assigneeSelect.value = String(task.assignee_id || 0);
        const scheduleSelect = drawerForm.querySelector('select[name="schedule_activity_id"]');
        if (!scheduleSelect) return;
        scheduleSelect.innerHTML = '<option value="0">Sin vincular</option>';
        scheduleActivities.forEach((activity) => {
            const option = document.createElement('option');
            option.value = String(activity.id || 0);
            option.textContent = String(activity.name || 'Actividad');
            scheduleSelect.appendChild(option);
        });
        scheduleSelect.value = String(task.schedule_activity_id || 0);
    };

    document.querySelectorAll('[data-open-task]').forEach((button) => {
        button.addEventListener('click', async () => {
            const taskId = Number(button.getAttribute('data-open-task') || 0);
            if (taskId <= 0) return;
            try {
                const response = await fetch(`/api/tasks/${taskId}`, { headers: { Accept: 'application/json' } });
                const payload = await response.json();
                if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'No se pudo cargar la tarea.');
                fillDrawer(payload.task || {}, payload.schedule_activities || []);
                showDrawer();
            } catch (error) {
                window.alert(error instanceof Error ? error.message : 'No se pudo cargar la tarea.');
            }
        });
    });

    if (drawerForm) {
        drawerForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const taskIdMatch = drawerForm.action.match(/\/tasks\/(\d+)\/update$/);
            const taskId = taskIdMatch ? Number(taskIdMatch[1]) : 0;
            if (taskId <= 0) return;
            const body = new URLSearchParams(new FormData(drawerForm));
            try {
                const response = await fetch(`/api/tasks/${taskId}/update`, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body,
                });
                const payload = await response.json();
                if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'No se pudo guardar la tarea.');
                window.location.reload();
            } catch (error) {
                window.alert(error instanceof Error ? error.message : 'No se pudo guardar la tarea.');
            }
        });
    }

    let draggedTaskId = null;
    document.querySelectorAll('[data-task-card]').forEach((card) => {
        card.addEventListener('dragstart', () => {
            draggedTaskId = card.dataset.taskId || null;
            card.classList.add('is-dragging');
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('is-dragging');
            draggedTaskId = null;
            document.querySelectorAll('[data-status-column]').forEach((column) => column.classList.remove('is-drop-target'));
        });
    });

    document.querySelectorAll('[data-status-column]').forEach((column) => {
        column.addEventListener('dragover', (event) => {
            event.preventDefault();
            column.classList.add('is-drop-target');
        });
        column.addEventListener('dragleave', () => column.classList.remove('is-drop-target'));
        column.addEventListener('drop', async (event) => {
            event.preventDefault();
            column.classList.remove('is-drop-target');
            if (!draggedTaskId) return;
            const nextStatus = normalizeStatus(column.dataset.statusColumn || '');
            const taskCard = document.querySelector(`[data-task-card][data-task-id="${draggedTaskId}"]`);
            if (!taskCard) return;
            const previousStatus = normalizeStatus(taskCard.dataset.taskStatus || '');
            if (nextStatus === '' || nextStatus === previousStatus) return;
            const previousParent = taskCard.parentElement;
            const targetContainer = column.querySelector('[data-column-cards]');
            if (!targetContainer) return;
            targetContainer.appendChild(taskCard);
            taskCard.dataset.taskStatus = nextStatus;
            updateCounts();
            try {
                const body = new URLSearchParams({ status: nextStatus });
                const response = await fetch(`/api/projects/${projectId}/tasks/${draggedTaskId}/status`, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body,
                });
                const payload = await response.json();
                if (!response.ok || payload.status !== 'ok') throw new Error(payload.message || 'No se pudo mover la tarea.');
            } catch (error) {
                if (previousParent) {
                    previousParent.appendChild(taskCard);
                    taskCard.dataset.taskStatus = previousStatus;
                    updateCounts();
                }
                window.alert(error instanceof Error ? error.message : 'No se pudo mover la tarea.');
            }
        });
    });

    addTaskButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const status = normalizeStatus(button.getAttribute('data-add-task-status') || 'todo');
            if (createStatusInput) createStatusInput.value = status;
            const form = document.querySelector('.task-create-form');
            if (!form) return;
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            const titleInput = form.querySelector('input[name="title"]');
            if (titleInput instanceof HTMLElement) titleInput.focus();
        });
    });
})();
</script>
