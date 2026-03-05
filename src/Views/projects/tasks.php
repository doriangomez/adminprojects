<?php
$basePath = $basePath ?? '';
$project = $project ?? [];
$tasks = is_array($tasks ?? null) ? $tasks : [];
$talents = is_array($talents ?? null) ? $talents : [];
$canManage = !empty($canManage);
$isClosed = !empty($isClosed);
$canAddTask = $canManage && !$isClosed;
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
$compassSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>';
$dollarSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>';
$checkSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
$clockSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
$linkSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';
$alertSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
$riskCategoryIcons = [
    'Alcance' => $compassSvg,
    'Costos' => $dollarSvg,
    'Calidad' => $checkSvg,
    'Tiempo' => $clockSvg,
    'Dependencias' => $linkSvg,
    'Otros' => $alertSvg,
];
$refreshSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-2.636-6.364"/><path d="M21 3v6h-6"/></svg>';
$editSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
$shieldXSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>';
$mapPinSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>';
$taskStatusMeta = [
    'todo' => ['label' => 'Pendiente', 'icon' => $clockSvg, 'class' => 'status-muted'],
    'in_progress' => ['label' => 'En progreso', 'icon' => $refreshSvg, 'class' => 'status-info'],
    'review' => ['label' => 'En revisión', 'icon' => $editSvg, 'class' => 'status-warning'],
    'blocked' => ['label' => 'Bloqueada', 'icon' => $shieldXSvg, 'class' => 'status-danger'],
    'done' => ['label' => 'Completada', 'icon' => $checkSvg, 'class' => 'status-success'],
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
                            <span class="risk-card__icon"><?= $riskCategoryIcons[$category] ?? $riskCategoryIcons['Otros'] ?></span>
                            <strong><?= htmlspecialchars($category) ?></strong>
                        </div>
                        <ul class="risk-checklist">
                            <?php foreach ($risks as $risk): ?>
                                <li>
                                    <span class="risk-check"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></span>
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
        <div>
            <p class="eyebrow">Controles y tareas</p>
            <h4>Checklist operativo del proyecto</h4>
            <small class="section-muted">Evidencias y controles en ejecución.</small>
        </div>

        <?php if ($canAddTask): ?>
            <form class="task-create-form" method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/tasks">
                <div class="task-create-form__header">
                    <strong>Agregar tarea</strong>
                    <span class="section-muted">Disponible para proyectos activos con permisos de gestión.</span>
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
                    <label class="task-field">
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
                    <button type="submit" class="action-btn primary">Agregar tarea</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if (empty($tasks)): ?>
            <p class="section-muted">No hay tareas registradas para este proyecto.</p>
        <?php else: ?>
            <div class="task-list">
                <?php foreach ($tasks as $task): ?>
                    <?php $taskStatus = $taskStatusMeta[$task['status'] ?? ''] ?? ['label' => 'Pendiente', 'icon' => $mapPinSvg, 'class' => 'status-muted']; ?>
                    <div class="task-card">
                        <div>
                            <strong><?= htmlspecialchars($task['title'] ?? '') ?></strong>
                            <small class="section-muted">Responsable: <?= htmlspecialchars($task['assignee'] ?? 'Sin asignar') ?></small>
                        </div>
                        <div class="task-meta">
                            <span class="status-badge <?= htmlspecialchars($taskStatus['class']) ?>">
                                <?= $taskStatus['icon'] ?> <?= htmlspecialchars($taskStatus['label']) ?>
                            </span>
                            <small class="section-muted">Prioridad <?= htmlspecialchars($task['priority'] ?? 'Media') ?></small>
                            <small class="section-muted">Horas <?= htmlspecialchars((string) ($task['actual_hours'] ?? 0)) ?>/<?= htmlspecialchars((string) ($task['estimated_hours'] ?? 0)) ?></small>
                            <small class="section-muted">Vence <?= htmlspecialchars((string) ($task['due_date'] ?? '')) ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>

<style>
    .project-shell { display:flex; flex-direction:column; gap:16px; }
    .project-header { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap; border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); }
    .project-title-block { display:flex; flex-direction:column; gap:8px; }
    .project-title-block h2 { margin:0; color: var(--text-primary); }
    .risk-overview,
    .risk-section,
    .task-section { border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); display:flex; flex-direction:column; gap:12px; }
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
    .task-field input,
    .task-field select { padding:8px 10px; border-radius:10px; border:1px solid var(--border); background: var(--surface); color: var(--text-primary); font-size:13px; }
    .task-create-form__actions { display:flex; justify-content:flex-end; }
</style>
