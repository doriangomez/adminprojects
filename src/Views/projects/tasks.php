<?php
$basePath = $basePath ?? '/project/public';
$project = $project ?? [];
$tasks = is_array($tasks ?? null) ? $tasks : [];
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

        <?php if (empty($tasks)): ?>
            <p class="section-muted">No hay tareas registradas para este proyecto.</p>
        <?php else: ?>
            <div class="task-list">
                <?php foreach ($tasks as $task): ?>
                    <div class="task-card">
                        <div>
                            <strong><?= htmlspecialchars($task['title'] ?? '') ?></strong>
                            <small class="section-muted">Responsable: <?= htmlspecialchars($task['assignee'] ?? 'Sin asignar') ?></small>
                        </div>
                        <div class="task-meta">
                            <span class="status-badge status-info"><?= htmlspecialchars($task['status'] ?? 'Pendiente') ?></span>
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
    .project-title-block h2 { margin:0; color: var(--text-strong); }
    .risk-overview,
    .risk-section,
    .task-section { border:1px solid var(--border); border-radius:16px; padding:16px; background:#fff; display:flex; flex-direction:column; gap:12px; }
    .risk-summary { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; }
    .risk-summary__item { border:1px solid var(--border); border-radius:12px; padding:10px; background:#f8fafc; display:flex; flex-direction:column; gap:4px; }
    .risk-summary__item span { font-size:12px; text-transform:uppercase; color: var(--muted); font-weight:700; }
    .risk-summary__item strong { font-size:16px; color: var(--text-strong); }
    .risk-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; }
    .risk-card { border:1px solid var(--border); border-radius:12px; padding:12px; background:#f8fafc; display:flex; flex-direction:column; gap:10px; }
    .risk-card__header { display:flex; align-items:center; gap:8px; }
    .risk-card__icon { width:30px; height:30px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background:#e0e7ff; }
    .risk-checklist { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:6px; }
    .risk-checklist li { display:flex; align-items:center; gap:8px; font-weight:600; }
    .risk-check { width:22px; height:22px; border-radius:999px; background:#dcfce7; color:#166534; display:inline-flex; align-items:center; justify-content:center; font-size:12px; }
    .task-list { display:flex; flex-direction:column; gap:10px; }
    .task-card { border:1px solid var(--border); border-radius:12px; padding:12px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; background:#f8fafc; }
    .task-meta { display:flex; flex-direction:column; gap:4px; align-items:flex-end; }
    .status-badge { font-size:12px; font-weight:700; padding:4px 8px; border-radius:999px; border:1px solid transparent; }
    .status-muted { background:#f3f4f6; color:#374151; border-color:#e5e7eb; }
    .status-info { background:#e0f2fe; color:#075985; border-color:#bae6fd; }
    .status-success { background:#dcfce7; color:#166534; border-color:#bbf7d0; }
    .status-warning { background:#fef9c3; color:#854d0e; border-color:#fde047; }
    .status-danger { background:#fee2e2; color:#991b1b; border-color:#fecdd3; }
    .action-btn { background: var(--surface); color: var(--text-strong); border:1px solid var(--border); border-radius:8px; padding:8px 10px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
</style>
