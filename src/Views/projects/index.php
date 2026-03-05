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
        table-layout: fixed;
        border-collapse: collapse;
        background: var(--surface);
        border-radius: 14px;
        overflow: hidden;
        border: 1px solid var(--border);
    }

    .project-table th,
    .project-table td {
        padding: 9px 10px;
        border-bottom: 1px solid var(--border);
        text-align: left;
        vertical-align: middle;
        font-size: 14px;
        max-width: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .project-table th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--text-secondary);
        background: color-mix(in srgb, var(--text-secondary) 14%, var(--background));
        font-weight: 700;
    }

    .project-row { cursor: pointer; }
    .project-row:hover { background: color-mix(in srgb, var(--text-secondary) 12%, var(--background)); }

    .project-title { font-weight: 700; color: var(--text-primary); margin: 0; }
    .project-client { color: var(--text-secondary); font-size: 13px; margin: 2px 0 0; }

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

    .progress-track { width: 130px; height: 10px; background: color-mix(in srgb, var(--text-secondary) 20%, var(--background)); border-radius: 999px; overflow: hidden; }
    .progress-bar { height: 100%; border-radius: 999px; background: linear-gradient(90deg, color-mix(in srgb, var(--primary) 55%, var(--background)), color-mix(in srgb, var(--success) 70%, var(--background))); }

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

    .table-actions { display: flex; gap: 6px; flex-wrap: wrap; }

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

    @media (max-width: 960px) {
        .projects-hero { flex-direction: column; align-items: flex-start; }
        .project-table { font-size: 13px; }
        .progress-track { width: 90px; }
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
        <table class="project-table" aria-label="Listado de proyectos">
            <colgroup>
                <col style="width: 15%;">
                <col style="width: 11%;">
                <col style="width: 10%;">
                <col style="width: 8%;">
                <col style="width: 8%;">
                <col style="width: 9%;">
                <col style="width: 8%;">
                <col style="width: 8%;">
                <col style="width: 5%;">
                <col style="width: 6%;">
                <col style="width: 6%;">
                <col style="width: 8%;">
                <col style="width: 8%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Proyecto</th>
                    <th>Cliente</th>
                    <th>PM</th>
                    <th>Metodología</th>
                    <th>Stage-gate</th>
                    <th>Estado</th>
                    <th>Salud</th>
                    <th>Avance</th>
                    <th>Señales</th>
                    <th>Notas</th>
                    <th>Bloqueos</th>
                    <th>Facturación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projectsList as $project): ?>
                    <?php
                        $statusLabel = $project['status_label'] ?? $project['status'] ?? 'Estado no registrado';
                        $healthLabel = $project['health_label'] ?? $project['health'] ?? 'Sin riesgo';
                        $riskClass = $healthBadgeClass(strtolower((string) ($project['health'] ?? '')));
                        $progress = (float) ($project['progress'] ?? 0);
                        $pmName = $project['pm_name'] ?? 'Sin PM asignado';
                        $methodology = $project['methodology'] ?? 'No definido';
                        $riskCodes = is_array($project['risks'] ?? null) ? $project['risks'] : [];
                        $riskSummary = $riskCodes ? implode(', ', array_map(fn ($code) => $riskLabels[$code] ?? $code, $riskCodes)) : 'Sin riesgos seleccionados';
                        $riskCount = count($riskCodes);
                        $signals = is_array($project['signals'] ?? null) ? $project['signals'] : [];
                        $noteData = is_array($project['latest_note'] ?? null) ? $project['latest_note'] : [];
                        $stopperData = is_array($project['top_stopper'] ?? null) ? $project['top_stopper'] : [];
                        $noteCount = (int) ($noteData['count'] ?? 0);
                        $blockerCount = (int) ($stopperData['total_count'] ?? 0);
                        $hasSignal = !empty($signals);
                        $rowLink = $basePath . '/projects/' . (int) ($project['id'] ?? 0) . '?return=' . urlencode($returnUrl);
                    ?>
                    <tr class="project-row" data-href="<?= htmlspecialchars($rowLink) ?>">
                        <td>
                            <p class="project-title"><?= htmlspecialchars($project['name']) ?></p>
                            <div class="risk-summary" title="<?= htmlspecialchars($riskSummary) ?>">Riesgos activos: <?= $riskCount ?></div>
                        </td>
                        <td>
                            <p class="project-client"><?= htmlspecialchars($project['client'] ?? 'Cliente no registrado') ?></p>
                        </td>
                        <td><?= htmlspecialchars($pmName) ?></td>
                        <td>
                            <span class="badge neutral"><?= htmlspecialchars(ucfirst($methodology)) ?></span>
                        </td>
                        <td>
                            <span class="badge neutral"><?= htmlspecialchars((string) ($project['project_stage'] ?? 'Discovery')) ?></span>
                        </td>
                        <td>
                            <span class="badge <?= $statusPillClass((string) $project['status']) ?>"><?= htmlspecialchars($statusLabel) ?></span>
                        </td>
                        <td>
                            <?php $compactHealth = (int) (($project['health_score']['total_score'] ?? 0)); ?>
                            <span class="compact-health <?= $healthScoreClass($compactHealth) ?>">● <?= $compactHealth ?></span>
                            <div><span class="badge <?= $riskClass ?>"><?= htmlspecialchars($healthLabel) ?></span></div>
                        </td>
                        <td>
                            <div style="display:flex; flex-direction:column; gap:6px;">
                                <div class="progress-track" aria-hidden="true">
                                    <div class="progress-bar" style="width: <?= max(0, min(100, $progress)) ?>%;"></div>
                                </div>
                                <span style="font-size:12px; color: var(--text-secondary);"><?= $progress ?>%</span>
                            </div>
                        </td>
                        <td>
                            <span class="indicator-cell" title="<?= $hasSignal ? htmlspecialchars(implode(' · ', $signals)) : 'Sin alertas' ?>"><?= $hasSignal ? '⚠' : '—' ?></span>
                        </td>
                        <td>
                            <a class="interactive-cell indicator-cell" data-no-row href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>?view=seguimiento&return=<?= urlencode($returnUrl) ?>" title="Ver notas del proyecto">📝 <?= $noteCount ?></a>
                        </td>
                        <td>
                            <a class="interactive-cell indicator-cell" data-no-row href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>?view=bloqueos&return=<?= urlencode($returnUrl) ?>" title="Ver bloqueos del proyecto">⛔ <?= $blockerCount ?></a>
                        </td>
                        <td>
                            <?php $isBillable = (int) ($project['is_billable'] ?? 0) === 1; ?>
                            <span class="badge <?= $isBillable ? 'billable-on' : 'billable-off' ?>"><?= $isBillable ? '🟢 Facturable' : '⚪ No facturable' ?></span>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a class="icon-button" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>?return=<?= urlencode($returnUrl) ?>" title="Ver detalle" data-no-row>
                                    👁️
                                </a>
                                <a class="icon-button" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/edit?return=<?= urlencode($returnUrl) ?>" title="Editar" data-no-row>
                                    ✏️
                                </a>
                                <a class="icon-button" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>?view=documentos&return=<?= urlencode($returnUrl) ?>" title="Documentos" data-no-row>
                                    📂
                                </a>
                                <a class="icon-button" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/talent?return=<?= urlencode($returnUrl) ?>" title="Talento" data-no-row>
                                    👥
                                </a>
                                <a class="icon-button" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/costs?return=<?= urlencode($returnUrl) ?>" title="Costos" data-no-row>
                                    💵
                                </a>
                                <form action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/close" method="GET" style="margin:0" data-no-row>
                                    <button class="icon-button" type="submit" title="Cerrar" data-no-row>🛑</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="project-grid">
            <?php foreach ($projectsList as $project): ?>
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
    <?php endif; ?>
<?php endif; ?>

<script>
    const filterShell = document.querySelector('[data-filter-shell]');
    const filterToggle = document.querySelector('[data-filter-toggle]');

    if (filterShell && filterToggle) {
        filterToggle.addEventListener('click', () => {
            filterShell.classList.toggle('is-open');
        });
    }

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
