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
    'in_progress' => ['label' => 'En progreso', 'icon' => '🔄', 'class' => 'status-info'],
    'review' => ['label' => 'En revisión', 'icon' => '📝', 'class' => 'status-warning'],
    'blocked' => ['label' => 'Bloqueada', 'icon' => '⛔', 'class' => 'status-danger'],
    'done' => ['label' => 'Completada', 'icon' => '✅', 'class' => 'status-success'],
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
                    <?php $taskStatus = $taskStatusMeta[$task['status'] ?? ''] ?? ['label' => 'Pendiente', 'icon' => '📌', 'class' => 'status-muted']; ?>
                    <div class="task-card">
                        <div>
                            <strong><?= htmlspecialchars($task['title'] ?? '') ?></strong>
                            <small class="section-muted">Responsable: <?= htmlspecialchars($task['assignee'] ?? 'Sin asignar') ?></small>
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
