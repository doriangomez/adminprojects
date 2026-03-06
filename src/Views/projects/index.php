<?php
$projectsList = is_array($projects ?? null) ? $projects : [];
$basePath = $basePath ?? '';
$filters = is_array($filters ?? null) ? $filters : [];
$clientsList = is_array($clients ?? null) ? $clients : [];
$deliveryConfig = is_array($delivery ?? null) ? $delivery : ['methodologies' => [], 'phases' => [], 'risks' => []];
$stageOptions = is_array($stageOptions ?? null) ? $stageOptions : [];

$activeStatuses = ['active', 'activo', 'en_ejecucion', 'en_progreso', 'in_progress', 'running', 'ejecucion'];
$completedStatuses = ['completed', 'completado', 'cerrado', 'closed', 'finalizado', 'archivado', 'archived'];
$riskHealth = ['at_risk', 'riesgo', 'risk', 'yellow', 'red', 'warning', 'critical'];
$riskLabels = [];
foreach ($deliveryConfig['risks'] as $risk) {
    if (isset($risk['code'], $risk['label'])) {
        $riskLabels[$risk['code']] = $risk['label'];
    }
}

$activeProjects = 0;
$riskProjects = 0;
$completedProjects = 0;
$hoursUsed = 0;
$plannedHours = 0;
$budgetTotal = 0;
$actualCostTotal = 0;
$outsourcingCount = 0;

foreach ($projectsList as $project) {
    $status = strtolower((string) ($project['status'] ?? ''));
    $health = strtolower((string) ($project['health'] ?? ''));

    if (in_array($status, $activeStatuses, true)) {
        $activeProjects++;
    }

    if (in_array($status, $completedStatuses, true)) {
        $completedProjects++;
    }

    if (in_array($health, $riskHealth, true)) {
        $riskProjects++;
    }

    if (($project['project_type'] ?? '') === 'outsourcing') {
        $outsourcingCount++;
    }

    $hoursUsed += (float) ($project['actual_hours'] ?? 0);
    $plannedHours += (float) ($project['planned_hours'] ?? 0);
    $budgetTotal += (float) ($project['budget'] ?? 0);
    $actualCostTotal += (float) ($project['actual_cost'] ?? 0);
}

$budgetCoverage = $budgetTotal > 0
    ? min(999, round(($actualCostTotal / $budgetTotal) * 100, 1))
    : 0;
$progressAverage = $projectsList
    ? round(array_sum(array_map(fn ($p) => (float) ($p['progress'] ?? 0), $projectsList)) / count($projectsList), 1)
    : 0;
$availableStatuses = array_values(array_unique(array_filter(array_map(fn ($p) => (string) ($p['status'] ?? ''), $projectsList))));
$viewMode = $_GET['view_mode'] ?? 'table';
$viewMode = in_array($viewMode, ['table', 'cards'], true) ? $viewMode : 'table';
$rawQuery = $_GET;
$rawQuery['view_mode'] = $viewMode;
$queryString = http_build_query(array_filter($rawQuery, static fn ($value) => $value !== null && $value !== ''));
$returnUrl = $basePath . '/projects' . ($queryString ? ('?' . $queryString) : '');

$healthBadgeClass = static function (string $health): string {
    return match ($health) {
        'red', 'critical', 'alto' => 'risk-high',
        'yellow', 'warning', 'at_risk', 'risk', 'riesgo', 'medio' => 'risk-medium',
        default => 'risk-low',
    };
};

$statusPillClass = static function (string $status) use ($activeStatuses, $completedStatuses): string {
    $status = strtolower($status);
    if (in_array($status, $completedStatuses, true)) {
        return 'status-completed';
    }

    if (in_array($status, $activeStatuses, true)) {
        return 'status-active';
    }

    if (in_array($status, ['blocked', 'bloqueado'], true)) {
        return 'status-blocked';
    }

    return 'status-planning';
};


$healthScoreClass = static function (int $score): string {
    if ($score >= 80) {
        return 'score-green';
    }
    if ($score >= 60) {
        return 'score-yellow';
    }

    return 'score-red';
};

$buildQuery = static function (array $overrides) use ($rawQuery): string {
    $params = array_merge($rawQuery, $overrides);
    $params = array_filter($params, static fn ($value) => $value !== null && $value !== '');
    return http_build_query($params);
};

// Agrupar proyectos por cliente (solo vista; sin modificar datos del backend)
$clientsByName = [];
foreach ($clientsList as $c) {
    $name = trim((string) ($c['name'] ?? ''));
    if ($name !== '') {
        $clientsByName[$name] = $c;
    }
}

$projectsByClient = [];
foreach ($projectsList as $project) {
    $clientName = trim((string) ($project['client'] ?? ''));
    if ($clientName === '') {
        $clientName = 'Cliente no registrado';
    }
    if (!isset($projectsByClient[$clientName])) {
        $projectsByClient[$clientName] = [];
    }
    $projectsByClient[$clientName][] = $project;
}

$stopperSeverityLabel = static function (string $impactLevel): string {
    return match (strtolower(trim($impactLevel))) {
        'critico' => 'Crítico',
        'alto' => 'Alto',
        'medio' => 'Medio',
        default => 'Bajo',
    };
};

?>

<style>
    .projects-hero {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        padding: 18px 20px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        box-shadow: 0 12px 30px color-mix(in srgb, var(--text-primary) 8%, var(--background));
    }

    .projects-hero h1 {
        margin: 0 0 4px;
        font-size: 26px;
        color: var(--text-primary);
    }

    .projects-hero p {
        margin: 0;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .hero-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .button {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 700;
        transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        border: 1px solid var(--border);
        font-size: 14px;
        background: var(--surface);
        color: var(--text-primary);
    }

    .button.primary {
        background: var(--primary);
        color: var(--text-primary);
        border-color: var(--primary);
        box-shadow: 0 10px 24px color-mix(in srgb, var(--primary) 35%, var(--background));
    }

    .button.secondary {
        background: var(--secondary);
        color: var(--surface);
        border-color: var(--secondary);
    }

    .button.ghost {
        background: color-mix(in srgb, var(--text-secondary) 18%, var(--background));
        color: var(--text-primary);
        border-color: color-mix(in srgb, var(--text-secondary) 30%, var(--background));
    }

    .button:hover { transform: translateY(-1px); }

    .hero-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
    }

    .chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--text-secondary) 16%, var(--background));
        color: var(--text-primary);
        font-size: 12px;
        font-weight: 700;
    }

    .filter-shell {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 14px 16px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
    }

    .filter-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
        align-items: end;
    }

    .filter-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .filter-toggle {
        justify-self: start;
    }

    .filter-extra {
        display: none;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
    }

    .filter-shell.is-open .filter-extra { display: grid; }

    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
    }

    .kpi-card {
        padding: 14px;
        border-radius: 14px;
        border: 1px solid var(--border);
        background: var(--surface);
        display: flex;
        gap: 12px;
        align-items: center;
        box-shadow: 0 10px 24px color-mix(in srgb, var(--text-primary) 6%, var(--background));
    }

    .kpi-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: color-mix(in srgb, var(--primary) 14%, var(--background));
        color: var(--primary);
    }

    .kpi-card .label { color: var(--text-secondary); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin: 0; }
    .kpi-card .value { margin: 0; font-size: 22px; color: var(--text-primary); font-weight: 800; }

    .view-toggle {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .view-toggle a {
        padding: 8px 12px;
        border-radius: 10px;
        border: 1px solid var(--border);
        text-decoration: none;
        font-weight: 700;
        font-size: 13px;
        color: var(--text-primary);
        background: var(--surface);
    }

    .view-toggle a.active {
        background: var(--primary);
        color: var(--text-primary);
        border-color: var(--primary);
    }

    .project-table {
        width: 100%;
        table-layout: auto;
        border-collapse: collapse;
        background: var(--surface);
        border-radius: 14px;
        overflow: visible;
        border: 1px solid var(--border);
    }

    .project-table th,
    .project-table td {
        padding: 11px 10px;
        border-bottom: 1px solid var(--border);
        text-align: left;
        vertical-align: middle;
        font-size: 13px;
    }

    .project-table th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--text-secondary);
        background: color-mix(in srgb, var(--text-secondary) 14%, var(--background));
        font-weight: 700;
        white-space: nowrap;
    }

    .project-table td {
        white-space: normal;
    }

    .project-table tbody tr:last-child td {
        border-bottom: none;
    }

    .project-row { cursor: pointer; }
    .project-row:hover { background: color-mix(in srgb, var(--text-secondary) 12%, var(--background)); }

    .project-title { font-weight: 700; color: var(--text-primary); margin: 0; }
    .project-client { color: var(--text-secondary); font-size: 12px; margin: 2px 0 0; }
    .project-cell { min-width: 320px; }
    .project-main { display: flex; flex-direction: column; gap: 2px; }

    .project-context-preview {
        display: flex;
        align-items: flex-start;
        gap: 6px;
        margin-top: 7px;
        padding: 0;
        color: color-mix(in srgb, var(--text-secondary) 86%, var(--background));
        font-size: 12px;
        line-height: 1.35;
        width: 100%;
    }

    .project-context-preview .context-icon {
        font-size: 11px;
        line-height: 1;
        flex-shrink: 0;
    }

    .project-context-preview .context-label {
        font-weight: 600;
        flex-shrink: 0;
    }

    .project-context-preview .context-text {
        white-space: normal;
        word-break: break-word;
        overflow-wrap: anywhere;
        flex: 1;
        min-width: 0;
    }

    .project-context-stats {
        margin-top: 7px;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        font-size: 12px;
        color: var(--text-secondary);
        font-weight: 600;
    }


    .notes-panel-overlay {
        position: fixed;
        inset: 0;
        background: rgba(17, 24, 39, 0.45);
        z-index: 70;
        display: none;
    }

    .notes-panel-overlay.is-open { display: block; }

    .notes-panel {
        position: fixed;
        top: 0;
        right: 0;
        width: min(680px, 94vw);
        height: 100vh;
        background: var(--surface);
        border-left: 1px solid var(--border);
        box-shadow: -10px 0 30px color-mix(in srgb, var(--text-primary) 18%, transparent);
        z-index: 80;
        transform: translateX(102%);
        transition: transform 0.2s ease;
        display: flex;
        flex-direction: column;
    }

    .notes-panel.is-open { transform: translateX(0); }

    .notes-panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        border-bottom: 1px solid var(--border);
    }

    .notes-panel-frame {
        border: 0;
        width: 100%;
        height: 100%;
        background: var(--surface);
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 10px;
        border-radius: 999px;
        font-weight: 700;
        font-size: 12px;
        border: 1px solid var(--background);
    }

    .badge.neutral { background: color-mix(in srgb, var(--text-secondary) 18%, var(--background)); color: var(--text-primary); }
    .badge.status-active { background: color-mix(in srgb, var(--primary) 18%, var(--background)); color: var(--primary); border-color: color-mix(in srgb, var(--primary) 40%, var(--background)); }
    .badge.status-completed { background: color-mix(in srgb, var(--success) 18%, var(--background)); color: var(--success); border-color: color-mix(in srgb, var(--success) 35%, var(--background)); }
    .badge.status-blocked { background: color-mix(in srgb, var(--danger) 18%, var(--background)); color: var(--danger); border-color: color-mix(in srgb, var(--danger) 35%, var(--background)); }
    .badge.status-planning { background: color-mix(in srgb, var(--warning) 18%, var(--background)); color: var(--warning); border-color: color-mix(in srgb, var(--warning) 35%, var(--background)); }
    .badge.risk-low { background: color-mix(in srgb, var(--success) 18%, var(--background)); color: color-mix(in srgb, var(--success) 70%, var(--text-primary)); border-color: color-mix(in srgb, var(--success) 35%, var(--background)); }
    .badge.risk-medium { background: color-mix(in srgb, var(--warning) 18%, var(--background)); color: color-mix(in srgb, var(--warning) 75%, var(--text-primary)); border-color: color-mix(in srgb, var(--warning) 35%, var(--background)); }
    .badge.risk-high { background: color-mix(in srgb, var(--danger) 18%, var(--background)); color: color-mix(in srgb, var(--danger) 75%, var(--text-primary)); border-color: color-mix(in srgb, var(--danger) 35%, var(--background)); }
    .badge.billable-on { background: color-mix(in srgb, var(--success) 18%, var(--background)); color: var(--success); border-color: color-mix(in srgb, var(--success) 35%, var(--background)); }
    .badge.billable-off { background: color-mix(in srgb, var(--text-secondary) 16%, var(--background)); color: var(--text-secondary); border-color: color-mix(in srgb, var(--text-secondary) 35%, var(--background)); }

    .progress-track { width: 112px; height: 6px; background: color-mix(in srgb, var(--text-secondary) 20%, var(--background)); border-radius: 999px; overflow: hidden; }
    .progress-bar { height: 100%; border-radius: 999px; background: linear-gradient(90deg, color-mix(in srgb, var(--primary) 55%, var(--background)), color-mix(in srgb, var(--success) 70%, var(--background))); }
    .progress-compact { display: flex; flex-direction: column; gap: 5px; }
    .progress-value { font-size: 11px; color: var(--text-secondary); font-weight: 600; }
    .pm-cell-text { font-weight: 600; color: var(--text-primary); }

    .signal-list, .note-cell, .stopper-cell { display: flex; flex-direction: column; gap: 4px; }
    .signal-pill { font-size: 11px; font-weight: 700; color: color-mix(in srgb, var(--warning) 80%, var(--text-primary)); }
    .tiny-meta { font-size: 11px; color: var(--text-secondary); }
    .interactive-cell { color: inherit; text-decoration: none; border: 1px solid transparent; border-radius: 8px; padding: 2px 4px; display: inline-block; max-width: 100%; }
    .indicator-cell { display:inline-flex; align-items:center; gap:6px; font-weight:700; }
    .interactive-cell:hover { border-color: var(--border); background: color-mix(in srgb, var(--text-secondary) 9%, var(--background)); }
    .severity-dot { font-weight: 700; }
    .severity-critico { color: var(--danger); }
    .severity-alto { color: #f59e0b; }
    .severity-medio { color: #facc15; }
    .indicator-blue { color: #3b82f6; font-size: 10px; }
    .badge.blocker-critical { background: color-mix(in srgb, var(--danger) 20%, var(--background)); color: var(--danger); border-color: color-mix(in srgb, var(--danger) 35%, var(--background)); }
    .badge.blocker-high { background: color-mix(in srgb, #f59e0b 22%, var(--background)); color: #b45309; border-color: color-mix(in srgb, #f59e0b 36%, var(--background)); }

    .table-actions-menu {
        display: inline-flex;
        position: relative;
    }

    .actions-cell {
        text-align: right;
        width: 1%;
        white-space: nowrap;
    }

    .menu-trigger::-webkit-details-marker { display: none; }

    .icon-button {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: var(--surface);
        color: var(--text-primary);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }

    .icon-button:hover { background: color-mix(in srgb, var(--primary) 12%, var(--background)); color: var(--primary); }

    .project-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 14px;
    }

    .project-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        box-shadow: 0 10px 24px color-mix(in srgb, var(--text-primary) 6%, var(--background));
    }

    .project-card header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 10px;
    }

    .card-metrics {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
    }

    .metric {
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 8px 10px;
        background: color-mix(in srgb, var(--text-secondary) 12%, var(--background));
        font-size: 12px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        text-align: left;
    }

    .metric span { color: var(--text-secondary); font-weight: 600; }
    .metric strong { color: var(--text-primary); font-size: 14px; }

    .card-actions {
        position: relative;
        align-self: flex-end;
    }

    .menu-trigger {
        border: 1px solid var(--border);
        background: var(--surface);
        border-radius: 8px;
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .menu-list {
        position: absolute;
        right: 0;
        top: 36px;
        min-width: 180px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 16px 32px color-mix(in srgb, var(--text-primary) 16%, var(--background));
        padding: 8px;
        display: flex;
        flex-direction: column;
        gap: 6px;
        z-index: 10;
    }

    .menu-list a,
    .menu-list button {
        text-decoration: none;
        color: var(--text-primary);
        padding: 8px 10px;
        border-radius: 8px;
        font-weight: 600;
        display: flex;
        gap: 8px;
        align-items: center;
        border: none;
        background: var(--background);
        cursor: pointer;
    }

    .menu-list a:hover,
    .menu-list button:hover { background: color-mix(in srgb, var(--primary) 12%, var(--background)); color: var(--primary); }

    .menu-list button { width: 100%; text-align: left; }

    .menu-details[open] .menu-trigger { background: color-mix(in srgb, var(--primary) 12%, var(--background)); color: var(--primary); }

    .compact-health { display:inline-flex; align-items:center; gap:4px; font-size:12px; font-weight:800; padding:4px 8px; border-radius:999px; border:1px solid var(--border); }
    .compact-health.score-green { color: var(--success); border-color: color-mix(in srgb, var(--success) 40%, var(--border)); }
    .compact-health.score-yellow { color: var(--warning); border-color: color-mix(in srgb, var(--warning) 40%, var(--border)); }
    .compact-health.score-red { color: var(--danger); border-color: color-mix(in srgb, var(--danger) 40%, var(--border)); }

    .risk-summary { font-size: 12px; color: var(--text-secondary); }

    .empty-state { padding: 18px; border-radius: 14px; background: color-mix(in srgb, var(--text-secondary) 12%, var(--background)); border: 1px solid var(--border); color: var(--text-secondary); font-weight: 600; }

    .client-group { margin-bottom: 24px; }
    .client-group:last-child { margin-bottom: 0; }
    .client-group-header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        margin-bottom: 0;
        background: color-mix(in srgb, var(--text-secondary) 10%, var(--background));
        border: 1px solid var(--border);
        border-radius: 12px 12px 0 0;
        font-weight: 700;
        font-size: 15px;
        color: var(--text-primary);
    }
    .client-group-header + .project-table { border-top-left-radius: 0; border-top-right-radius: 0; margin-top: -1px; }
    .client-group-header + .project-grid { margin-top: 12px; }
    .client-avatar {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        overflow: hidden;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: color-mix(in srgb, var(--primary) 18%, var(--background));
        color: var(--primary);
        font-weight: 800;
        font-size: 16px;
    }
    .client-avatar img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    @media (max-width: 960px) {
        .projects-hero { flex-direction: column; align-items: flex-start; }
        .project-table { font-size: 13px; }
        .project-cell { min-width: 240px; }
        .progress-track { width: 80px; }
    }
</style>

<div class="projects-hero">
    <div>
        <p class="eyebrow" style="margin:0; color: var(--text-secondary); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">Panel</p>
        <h1>Proyectos</h1>
        <p>Ejecución y control operativo</p>
        <div class="hero-chips">
            <span class="chip">Total: <?= count($projectsList) ?></span>
            <span class="chip">Activos: <?= $activeProjects ?></span>
            <span class="chip">En riesgo: <?= $riskProjects ?></span>
            <?php if ($outsourcingCount > 0): ?>
                <span class="chip">Outsourcing: <?= $outsourcingCount ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="hero-actions">
        <a class="button primary" href="<?= $basePath ?>/projects/create">
            <span aria-hidden="true">＋</span>
            Nuevo proyecto
        </a>
        <a class="button secondary" href="<?= $basePath ?>/tasks">
            <span aria-hidden="true">📊</span>
            Tablero
        </a>
    </div>
</div>

<form method="GET" action="<?= $basePath ?>/projects" class="filter-shell" data-filter-shell>
    <input type="hidden" name="view_mode" value="<?= htmlspecialchars($viewMode) ?>">
    <div class="filter-row">
        <label>
            Buscar
            <input type="search" name="search" placeholder="Buscar proyecto o cliente" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
        </label>
        <label>
            Cliente
            <select name="client_id">
                <option value="">Todos</option>
                <?php foreach ($clientsList as $client): ?>
                    <option value="<?= (int) $client['id'] ?>" <?= isset($filters['client_id']) && (int) $filters['client_id'] === (int) $client['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($client['name'] ?? 'Cliente') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Estado
            <select name="status">
                <option value="">Todos</option>
                <?php foreach ($availableStatuses as $statusOption): ?>
                    <option value="<?= htmlspecialchars($statusOption) ?>" <?= ($filters['status'] ?? '') === $statusOption ? 'selected' : '' ?>>
                        <?= htmlspecialchars($statusOption) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Stage-gate
            <select name="project_stage">
                <option value="">Todas</option>
                <?php foreach ($stageOptions as $stageOption): ?>
                    <option value="<?= htmlspecialchars($stageOption) ?>" <?= ($filters['project_stage'] ?? '') === $stageOption ? 'selected' : '' ?>>
                        <?= htmlspecialchars($stageOption) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="button" class="button ghost filter-toggle" data-filter-toggle>
            <span aria-hidden="true">⚙️</span>
            Filtros
        </button>
        <div class="filter-actions">
            <button type="submit" class="button primary" style="border:none;">Aplicar</button>
            <a class="button ghost" href="<?= $basePath ?>/projects">Limpiar</a>
        </div>
    </div>
    <div class="filter-extra">
        <label>
            Metodología
            <select name="methodology">
                <option value="">Todas</option>
                <?php foreach ($deliveryConfig['methodologies'] as $methodology): ?>
                    <option value="<?= htmlspecialchars($methodology) ?>" <?= ($filters['methodology'] ?? '') === $methodology ? 'selected' : '' ?>>
                        <?= htmlspecialchars(ucfirst($methodology)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Inicio desde
            <input type="date" name="start_date" value="<?= htmlspecialchars($filters['start_date'] ?? '') ?>">
        </label>
        <label>
            Fin hasta
            <input type="date" name="end_date" value="<?= htmlspecialchars($filters['end_date'] ?? '') ?>">
        </label>
        <label>
            Facturación
            <select name="billable">
                <option value="">Todos</option>
                <option value="yes" <?= ($filters['billable'] ?? '') === 'yes' ? 'selected' : '' ?>>Solo facturables</option>
                <option value="no" <?= ($filters['billable'] ?? '') === 'no' ? 'selected' : '' ?>>Solo no facturables</option>
            </select>
        </label>
    </div>
</form>

<section class="kpi-grid">
    <div class="kpi-card" title="Activos en ejecución · Promedio <?= $progressAverage ?>%">
        <div class="kpi-icon" aria-hidden="true">🚀</div>
        <div>
            <p class="label">Activos</p>
            <p class="value"><?= $activeProjects ?></p>
        </div>
    </div>
    <div class="kpi-card" title="Monitorea señales tempranas">
        <div class="kpi-icon" aria-hidden="true">⚠️</div>
        <div>
            <p class="label">En riesgo</p>
            <p class="value"><?= $riskProjects ?></p>
        </div>
    </div>
    <div class="kpi-card" title="Proyectos cerrados y archivados">
        <div class="kpi-icon" aria-hidden="true">✅</div>
        <div>
            <p class="label">Completados</p>
            <p class="value"><?= $completedProjects ?></p>
        </div>
    </div>
    <div class="kpi-card" title="Horas reales vs planificadas">
        <div class="kpi-icon" aria-hidden="true">⏱️</div>
        <div>
            <p class="label">Horas registradas</p>
            <p class="value"><?= number_format($hoursUsed, 0, ',', '.') ?>h</p>
        </div>
    </div>
    <div class="kpi-card" title="Cobertura <?= $budgetCoverage ?>%">
        <div class="kpi-icon" aria-hidden="true">💰</div>
        <div>
            <p class="label">Presupuesto vs real</p>
            <p class="value">$<?= number_format($actualCostTotal, 0, ',', '.') ?></p>
        </div>
    </div>
</section>

<div class="toolbar" style="margin-top: 6px;">
    <div>
        <strong style="color: var(--text-primary);">Listado general</strong>
        <p class="muted" style="margin:4px 0 0;">Vista compacta para operación o lectura ejecutiva.</p>
    </div>
    <div class="view-toggle">
        <a href="<?= $basePath ?>/projects?<?= htmlspecialchars($buildQuery(['view_mode' => 'table'])) ?>" class="<?= $viewMode === 'table' ? 'active' : '' ?>">Tabla</a>
        <a href="<?= $basePath ?>/projects?<?= htmlspecialchars($buildQuery(['view_mode' => 'cards'])) ?>" class="<?= $viewMode === 'cards' ? 'active' : '' ?>">Cards</a>
    </div>
</div>

<?php if (empty($projectsList)): ?>
    <div class="empty-state">No hay proyectos con estos filtros.</div>
<?php else: ?>
    <?php if ($viewMode === 'table'): ?>
        <?php foreach ($projectsByClient as $clientName => $clientProjects): ?>
            <?php
                $clientData = $clientsByName[$clientName] ?? null;
                $logoPath = $clientData['logo_path'] ?? null;
                $clientInitial = strtoupper(substr($clientName, 0, 1));
            ?>
            <div class="client-group">
                <div class="client-group-header">
                    <div class="client-avatar" aria-hidden="true">
                        <?php if (!empty($logoPath)): ?>
                            <img src="<?= $basePath . $logoPath ?>" alt="Logo de <?= htmlspecialchars($clientName) ?>">
                        <?php else: ?>
                            <?= htmlspecialchars($clientInitial) ?>
                        <?php endif; ?>
                    </div>
                    <span><?= htmlspecialchars($clientName) ?></span>
                </div>
                <table class="project-table" aria-label="Proyectos de <?= htmlspecialchars($clientName) ?>">
                    <colgroup>
                        <col style="width: 42%;">
                        <col style="width: 14%;">
                        <col style="width: 12%;">
                        <col style="width: 12%;">
                        <col style="width: 10%;">
                        <col style="width: 8%;">
                        <col style="width: 2%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Proyecto</th>
                            <th>PM</th>
                            <th>Estado</th>
                            <th>Salud</th>
                            <th>Avance</th>
                            <th>Facturación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                <?php foreach ($clientProjects as $project): ?>
                    <?php
                        $statusLabel = $project['status_label'] ?? $project['status'] ?? 'Estado no registrado';
                        $healthLabel = $project['health_label'] ?? $project['health'] ?? 'Sin riesgo';
                        $riskClass = $healthBadgeClass(strtolower((string) ($project['health'] ?? '')));
                        $progress = (float) ($project['progress'] ?? 0);
                        $progressLabel = rtrim(rtrim(number_format($progress, 1, '.', ''), '0'), '.');
                        $pmName = $project['pm_name'] ?? 'Sin PM asignado';
                        $riskCodes = is_array($project['risks'] ?? null) ? $project['risks'] : [];
                        $riskCount = count($riskCodes);
                        $noteData = is_array($project['latest_note'] ?? null) ? $project['latest_note'] : [];
                        $stopperData = is_array($project['top_stopper'] ?? null) ? $project['top_stopper'] : [];
                        $projectId = (int) ($project['id'] ?? 0);
                        $rowLink = $basePath . '/projects/' . $projectId . '?return=' . urlencode($returnUrl);
                        $notePreviewText = trim((string) ($noteData['text'] ?? ''));
                        $stopperPreviewText = trim((string) ($stopperData['text'] ?? ''));
                        $noteTimestamp = strtotime((string) ($noteData['created_at'] ?? '')) ?: null;
                        $stopperTimestamp = strtotime((string) ($stopperData['created_at'] ?? '')) ?: null;
                        $previewType = 'note';
                        $previewLabel = 'Nota';
                        $previewIcon = '📝';
                        $previewText = '';

                        if ($notePreviewText !== '' || $stopperPreviewText !== '') {
                            $useStopperPreview = false;
                            if ($stopperPreviewText !== '') {
                                if ($notePreviewText === '') {
                                    $useStopperPreview = true;
                                } elseif ($stopperTimestamp !== null && $noteTimestamp !== null) {
                                    $useStopperPreview = $stopperTimestamp >= $noteTimestamp;
                                } elseif ($stopperTimestamp !== null && $noteTimestamp === null) {
                                    $useStopperPreview = true;
                                }
                            }

                            if ($useStopperPreview) {
                                $previewType = 'stopper';
                                $previewLabel = 'Bloqueo';
                                $previewIcon = '⛔';
                                $previewText = $stopperPreviewText;
                            } else {
                                $previewText = $notePreviewText;
                            }
                        }

                        $previewHref = $basePath . '/projects/' . $projectId
                            . '?view=' . ($previewType === 'stopper' ? 'bloqueos' : 'seguimiento')
                            . '&return=' . urlencode($returnUrl);
                        $blockersCount = (int) ($stopperData['total_count'] ?? 0);
                        $clientName = $project['client'] ?? 'Cliente no registrado';
                        $isBillable = (int) ($project['is_billable'] ?? 0) === 1;
                        $compactHealth = (int) (($project['health_score']['total_score'] ?? 0));
                        $progressHoursAuto = isset($project['progress_hours_auto']) ? (float) $project['progress_hours_auto'] : null;
                        $progressTasksAuto = isset($project['progress_tasks_auto']) ? (float) $project['progress_tasks_auto'] : null;
                        $pmoRiskScore = (int) ($project['pmo_risk_score'] ?? 0);
                        $pmoRiskClass = $pmoRiskScore >= 70 ? 'status-danger' : ($pmoRiskScore >= 40 ? 'status-warning' : 'status-success');
                    ?>
                    <tr class="project-row" data-href="<?= htmlspecialchars($rowLink) ?>">
                        <td class="project-cell">
                            <div class="project-main">
                                <p class="project-title"><?= htmlspecialchars($project['name']) ?></p>
                                <p class="project-client">Cliente: <?= htmlspecialchars($clientName) ?></p>
                            </div>
                            <?php if ($previewText !== ''): ?>
                                <a class="interactive-cell project-context-preview" data-no-row href="<?= htmlspecialchars($previewHref) ?>" title="<?= htmlspecialchars($previewLabel . ': ' . $previewText) ?>">
                                    <span class="context-icon" aria-hidden="true"><?= $previewIcon ?></span>
                                    <span class="context-label"><?= $previewLabel ?>:</span>
                                    <span class="context-text"><?= htmlspecialchars($previewText) ?></span>
                                </a>
                            <?php else: ?>
                                <span class="project-context-preview" title="Sin notas o bloqueos registrados">
                                    <span class="context-icon" aria-hidden="true">📝</span>
                                    <span class="context-label">Nota:</span>
                                    <span class="context-text">Sin notas o bloqueos registrados.</span>
                                </span>
                            <?php endif; ?>
                            <div class="project-context-stats">
                                <span>⚠ Riesgos: <?= $riskCount ?></span>
                                <span>⛔ Bloqueos: <?= $blockersCount ?></span>
                            </div>
                        </td>
                        <td><span class="pm-cell-text"><?= htmlspecialchars($pmName) ?></span></td>
                        <td>
                            <span class="badge <?= $statusPillClass((string) $project['status']) ?>"><?= htmlspecialchars($statusLabel) ?></span>
                        </td>
                        <td>
                            <span class="compact-health <?= $healthScoreClass($compactHealth) ?>">● <?= $compactHealth ?></span>
                            <div><span class="badge <?= $riskClass ?>"><?= htmlspecialchars($healthLabel) ?></span></div>
                        </td>
                        <td>
                            <div class="progress-compact">
                                <div class="progress-track" aria-hidden="true">
                                    <div class="progress-bar" style="width: <?= max(0, min(100, $progress)) ?>%;"></div>
                                </div>
                                <span class="progress-value"><?= $progressLabel ?>%</span>
                                <small class="section-muted">Horas <?= $progressHoursAuto !== null ? number_format($progressHoursAuto, 1) . '%' : 'N/A' ?> · Tareas <?= $progressTasksAuto !== null ? number_format($progressTasksAuto, 1) . '%' : 'N/A' ?></small>
                                <span class="badge <?= $pmoRiskClass ?>">Riesgo PMO <?= $pmoRiskScore ?>/100</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge <?= $isBillable ? 'billable-on' : 'billable-off' ?>"><?= $isBillable ? 'Facturable' : 'No facturable' ?></span>
                        </td>
                        <td class="actions-cell">
                            <details class="menu-details table-actions-menu" data-no-row>
                                <summary class="menu-trigger" aria-label="Acciones" data-no-row>⋯</summary>
                                <div class="menu-list">
                                    <a href="<?= $basePath ?>/projects/<?= $projectId ?>?return=<?= urlencode($returnUrl) ?>" data-no-row>Ver detalle</a>
                                    <a href="<?= $basePath ?>/projects/<?= $projectId ?>/edit?return=<?= urlencode($returnUrl) ?>" data-no-row>Editar</a>
                                    <a href="<?= $basePath ?>/projects/<?= $projectId ?>?view=documentos&return=<?= urlencode($returnUrl) ?>" data-no-row>Documentos</a>
                                    <a href="<?= $basePath ?>/projects/<?= $projectId ?>/talent?return=<?= urlencode($returnUrl) ?>" data-no-row>Talento</a>
                                    <a href="<?= $basePath ?>/projects/<?= $projectId ?>/costs?return=<?= urlencode($returnUrl) ?>" data-no-row>Costos</a>
                                    <form action="<?= $basePath ?>/projects/<?= $projectId ?>/close" method="GET" data-no-row>
                                        <button type="submit" data-no-row>Cerrar proyecto</button>
                                    </form>
                                </div>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <?php foreach ($projectsByClient as $clientName => $clientProjects): ?>
            <?php
                $clientData = $clientsByName[$clientName] ?? null;
                $logoPath = $clientData['logo_path'] ?? null;
                $clientInitial = strtoupper(substr($clientName, 0, 1));
            ?>
            <div class="client-group">
                <div class="client-group-header">
                    <div class="client-avatar" aria-hidden="true">
                        <?php if (!empty($logoPath)): ?>
                            <img src="<?= $basePath . $logoPath ?>" alt="Logo de <?= htmlspecialchars($clientName) ?>">
                        <?php else: ?>
                            <?= htmlspecialchars($clientInitial) ?>
                        <?php endif; ?>
                    </div>
                    <span><?= htmlspecialchars($clientName) ?></span>
                </div>
                <div class="project-grid">
            <?php foreach ($clientProjects as $project): ?>
                <?php
                    $statusLabel = $project['status_label'] ?? $project['status'] ?? 'Estado no registrado';
                    $healthLabel = $project['health_label'] ?? $project['health'] ?? 'Sin riesgo';
                    $riskClass = $healthBadgeClass(strtolower((string) ($project['health'] ?? '')));
                    $progress = (float) ($project['progress'] ?? 0);
                    $methodology = $project['methodology'] ?? 'No definido';
                    $riskCodes = is_array($project['risks'] ?? null) ? $project['risks'] : [];
                    $riskSummary = $riskCodes ? implode(', ', array_map(fn ($code) => $riskLabels[$code] ?? $code, $riskCodes)) : 'Sin riesgos seleccionados';
                    $riskCount = count($riskCodes);
                    $approvedDocs = (int) ($project['approved_documents'] ?? 0);
                    $hoursValue = number_format((float) ($project['actual_hours'] ?? 0), 0, ',', '.');
                    $costValue = number_format((float) ($project['actual_cost'] ?? 0), 0, ',', '.');
                    $progressHoursAuto = isset($project['progress_hours_auto']) ? (float) $project['progress_hours_auto'] : null;
                    $progressTasksAuto = isset($project['progress_tasks_auto']) ? (float) $project['progress_tasks_auto'] : null;
                    $pmoRiskScore = (int) ($project['pmo_risk_score'] ?? 0);
                    $pmoRiskClass = $pmoRiskScore >= 70 ? 'status-danger' : ($pmoRiskScore >= 40 ? 'status-warning' : 'status-success');
                ?>
                <article class="project-card">
                    <header>
                        <div>
                            <h3 class="project-title"><?= htmlspecialchars($project['name']) ?></h3>
                            <p class="project-client"><?= htmlspecialchars($project['client'] ?? 'Cliente no registrado') ?></p>
                        </div>
                        <div class="card-actions">
                            <details class="menu-details">
                                <summary class="menu-trigger" aria-label="Acciones">⋯</summary>
                                <div class="menu-list">
                                    <a href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>?return=<?= urlencode($returnUrl) ?>">Ver detalle</a>
                                    <a href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/edit?return=<?= urlencode($returnUrl) ?>">Editar</a>
                                    <a href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>?view=documentos&return=<?= urlencode($returnUrl) ?>">Documentos</a>
                                    <a href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/talent?return=<?= urlencode($returnUrl) ?>">Talento</a>
                                    <a href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/costs?return=<?= urlencode($returnUrl) ?>">Costos</a>
                                    <form action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/close" method="GET">
                                        <button type="submit">Cerrar proyecto</button>
                                    </form>
                                </div>
                            </details>
                        </div>
                    </header>

                    <div style="display:flex; flex-wrap:wrap; gap:6px;">
                        <span class="badge neutral"><?= htmlspecialchars(ucfirst($methodology)) ?></span>
                        <span class="badge neutral"><?= htmlspecialchars((string) ($project['project_stage'] ?? 'Discovery')) ?></span>
                        <span class="badge <?= $statusPillClass((string) $project['status']) ?>"><?= htmlspecialchars($statusLabel) ?></span>
                        <span class="badge <?= $riskClass ?>"><?= htmlspecialchars($healthLabel) ?></span>
                        <?php $compactHealth = (int) (($project['health_score']['total_score'] ?? 0)); ?>
                        <span class="compact-health <?= $healthScoreClass($compactHealth) ?>">● <?= $compactHealth ?></span>
                    </div>

                    <div class="risk-summary" title="<?= htmlspecialchars($riskSummary) ?>">Riesgos: <?= $riskCount ?></div>

                    <div>
                        <div class="progress-track" aria-hidden="true">
                            <div class="progress-bar" style="width: <?= max(0, min(100, $progress)) ?>%;"></div>
                        </div>
                        <span style="font-size:12px; color: var(--text-secondary);">Avance <?= $progress ?>%</span>
                        <div style="font-size:11px; color: var(--text-secondary); margin-top:4px;">
                            Horas <?= $progressHoursAuto !== null ? number_format($progressHoursAuto, 1) . '%' : 'N/A' ?> ·
                            Tareas <?= $progressTasksAuto !== null ? number_format($progressTasksAuto, 1) . '%' : 'N/A' ?>
                        </div>
                        <span class="badge <?= $pmoRiskClass ?>" style="margin-top:6px; display:inline-flex;">Riesgo PMO <?= $pmoRiskScore ?>/100</span>
                    </div>

                    <div class="card-metrics">
                        <div class="metric">
                            <span>Docs aprobados</span>
                            <strong><?= $approvedDocs ?></strong>
                        </div>
                        <div class="metric">
                            <span>Horas</span>
                            <strong><?= $hoursValue ?>h</strong>
                        </div>
                        <div class="metric">
                            <span>Costos</span>
                            <strong>$<?= $costValue ?></strong>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>


<div class="notes-panel-overlay" data-notes-overlay hidden></div>
<aside class="notes-panel" data-notes-panel aria-hidden="true">
    <div class="notes-panel-header">
        <strong data-notes-title>Notas del proyecto</strong>
        <button type="button" class="icon-button" data-notes-close aria-label="Cerrar panel">✕</button>
    </div>
    <iframe class="notes-panel-frame" data-notes-frame title="Historial de notas del proyecto" loading="lazy"></iframe>
</aside>

<script>
    const filterShell = document.querySelector('[data-filter-shell]');
    const filterToggle = document.querySelector('[data-filter-toggle]');

    if (filterShell && filterToggle) {
        filterToggle.addEventListener('click', () => {
            filterShell.classList.toggle('is-open');
        });
    }



    const notesOverlay = document.querySelector('[data-notes-overlay]');
    const notesPanel = document.querySelector('[data-notes-panel]');
    const notesFrame = document.querySelector('[data-notes-frame]');
    const notesTitle = document.querySelector('[data-notes-title]');

    const closeNotesPanel = () => {
        if (!notesPanel || !notesOverlay) {
            return;
        }
        notesPanel.classList.remove('is-open');
        notesPanel.setAttribute('aria-hidden', 'true');
        notesOverlay.classList.remove('is-open');
        notesOverlay.hidden = true;
    };

    document.querySelectorAll('[data-open-notes-panel]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            if (!notesPanel || !notesOverlay || !notesFrame) {
                return;
            }

            event.preventDefault();
            const targetUrl = trigger.getAttribute('data-project-notes-url') || trigger.getAttribute('href') || '';
            const projectName = trigger.getAttribute('data-project-name') || 'Proyecto';
            notesFrame.src = targetUrl;
            if (notesTitle) {
                notesTitle.textContent = `Notas · ${projectName}`;
            }
            notesOverlay.hidden = false;
            notesOverlay.classList.add('is-open');
            notesPanel.classList.add('is-open');
            notesPanel.setAttribute('aria-hidden', 'false');
        });
    });

    document.querySelector('[data-notes-close]')?.addEventListener('click', closeNotesPanel);
    notesOverlay?.addEventListener('click', closeNotesPanel);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeNotesPanel();
        }
    });

    document.querySelectorAll('.project-row').forEach((row) => {
        row.addEventListener('click', (event) => {
            if (event.target.closest('[data-no-row]')) {
                return;
            }
            const href = row.getAttribute('data-href');
            if (href) {
                window.location.href = href;
            }
        });
    });
</script>
