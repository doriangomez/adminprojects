<?php
$projectsList = is_array($projects ?? null) ? $projects : [];
$basePath = $basePath ?? '';
$filters = is_array($filters ?? null) ? $filters : [];
$clientsList = is_array($clients ?? null) ? $clients : [];
$clientGroupsList = is_array($clientGroups ?? null) ? $clientGroups : [];
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

    $hoursUsed += (float) ($project['timesheet_hours_logged'] ?? $project['actual_hours'] ?? 0);
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
$availableStatuses = is_array($availableStatuses ?? null)
    ? array_values(array_filter(array_map(fn ($status) => trim((string) $status), $availableStatuses)))
    : array_values(array_unique(array_filter(array_map(fn ($p) => (string) ($p['status'] ?? ''), $projectsList))));
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

$normalizeClientKey = static function (string $value): string {
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }

    return function_exists('mb_strtolower')
        ? mb_strtolower($trimmed, 'UTF-8')
        : strtolower($trimmed);
};

$clientMetadataById = [];
foreach ($clientGroupsList as $clientGroup) {
    $id = (int) ($clientGroup['id'] ?? 0);
    if ($id <= 0) {
        continue;
    }

    $clientMetadataById[$id] = [
        'name' => trim((string) ($clientGroup['name'] ?? '')),
        'logo_path' => trim((string) ($clientGroup['logo_path'] ?? '')),
    ];
}

$projectsGroupedByClient = [];
foreach ($projectsList as $project) {
    $clientId = (int) ($project['client_id'] ?? 0);
    $clientName = trim((string) ($project['client_name'] ?? ''));
    if ($clientName === '' && $clientId > 0) {
        $clientName = trim((string) ($clientMetadataById[$clientId]['name'] ?? ''));
    }
    if ($clientName === '') {
        $clientName = 'Cliente sin nombre';
    }

    $groupKey = $clientId > 0 ? 'id_' . $clientId : $normalizeClientKey($clientName);
    if (!isset($projectsGroupedByClient[$groupKey])) {
        $projectsGroupedByClient[$groupKey] = [
            'client_id' => $clientId,
            'client_name' => $clientName,
            'logo_path' => $clientId > 0 ? trim((string) ($clientMetadataById[$clientId]['logo_path'] ?? '')) : '',
            'project_count' => 0,
            'projects' => [],
        ];
    }

    $projectsGroupedByClient[$groupKey]['projects'][] = $project;
    $projectsGroupedByClient[$groupKey]['project_count']++;
}

$compactStatus = static function (array $project): array {
    $status = strtolower(trim((string) ($project['status'] ?? '')));
    $health = strtolower(trim((string) ($project['health'] ?? '')));

    if (
        in_array($health, ['at_risk', 'riesgo', 'risk', 'yellow', 'red', 'warning', 'critical', 'alto', 'medio'], true)
        || str_contains($status, 'riesgo')
    ) {
        return ['label' => 'En riesgo', 'class' => 'status-risk'];
    }

    if (in_array($status, ['completed', 'completado', 'cerrado', 'closed', 'finalizado', 'archivado', 'archived'], true)) {
        return ['label' => 'Completado', 'class' => 'status-completed'];
    }

    if (in_array($status, ['active', 'activo', 'en_ejecucion', 'en_progreso', 'in_progress', 'running', 'ejecucion'], true)) {
        return ['label' => 'Ejecución', 'class' => 'status-execution'];
    }

    return ['label' => 'Planificación', 'class' => 'status-planning'];
};

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

    .clients-grid {
        margin-top: 14px;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .client-group {
        display: flex;
        flex-direction: column;
        gap: 10px;
        border: 1px solid var(--border);
        border-radius: 14px;
        background: var(--surface);
        padding: 12px;
    }

    .client-group-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .client-brand {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .client-brand-logo,
    .client-brand-avatar {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        border: 1px solid var(--border);
        background: color-mix(in srgb, var(--surface) 92%, var(--background));
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .client-brand-logo {
        object-fit: contain;
        padding: 6px;
    }

    .client-brand-avatar {
        font-size: 16px;
        font-weight: 800;
        color: var(--primary);
    }

    .client-group-title {
        margin: 0;
        font-size: 16px;
        color: var(--text-primary);
        font-weight: 800;
    }

    .client-group-subtitle {
        margin: 2px 0 0;
        color: var(--text-secondary);
        font-size: 12px;
        font-weight: 600;
    }

    .client-project-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .compact-project {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(120px, 1fr) minmax(120px, 1fr) 88px;
        gap: 8px;
        align-items: center;
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 8px 10px;
        background: color-mix(in srgb, var(--text-secondary) 8%, var(--background));
        font-size: 13px;
    }

    .compact-project strong { color: var(--text-primary); }
    .compact-project span { color: var(--text-secondary); }

    .badge.status-execution { background: color-mix(in srgb, var(--primary) 18%, var(--background)); color: var(--primary); border-color: color-mix(in srgb, var(--primary) 40%, var(--background)); }
    .badge.status-risk { background: color-mix(in srgb, var(--danger) 18%, var(--background)); color: var(--danger); border-color: color-mix(in srgb, var(--danger) 35%, var(--background)); }

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
        .project-cell { min-width: 240px; }
        .progress-track { width: 80px; }
        .client-group-header { align-items: flex-start; }
        .clients-grid { grid-template-columns: 1fr; }
        .compact-project { grid-template-columns: minmax(0, 1fr); }
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
        <strong style="color: var(--text-primary);">Listado por cliente</strong>
        <p class="muted" style="margin:4px 0 0;">Proyectos agrupados por cliente con la misma información operativa.</p>
    </div>
    <div class="view-toggle">
        <a href="<?= $basePath ?>/projects?<?= htmlspecialchars($buildQuery(['view_mode' => 'table'])) ?>" class="<?= $viewMode === 'table' ? 'active' : '' ?>">Tabla</a>
        <a href="<?= $basePath ?>/projects?<?= htmlspecialchars($buildQuery(['view_mode' => 'cards'])) ?>" class="<?= $viewMode === 'cards' ? 'active' : '' ?>">Cards</a>
    </div>
</div>

<?php if (empty($projectsGroupedByClient)): ?>
    <div class="empty-state">No hay proyectos con estos filtros.</div>
<?php else: ?>
    <div class="clients-grid">
        <?php foreach ($projectsGroupedByClient as $clientGroup): ?>
            <?php
                $clientNameGroup = (string) ($clientGroup['client_name'] ?? 'Cliente');
                $clientLogoPath = trim((string) ($clientGroup['logo_path'] ?? ''));
                $clientLogoUrl = '';
                if ($clientLogoPath !== '') {
                    $clientLogoUrl = str_starts_with($clientLogoPath, 'http://') || str_starts_with($clientLogoPath, 'https://')
                        ? $clientLogoPath
                        : $basePath . $clientLogoPath;
                }

                $clientInitial = function_exists('mb_substr')
                    ? mb_substr($clientNameGroup, 0, 1, 'UTF-8')
                    : substr($clientNameGroup, 0, 1);
                if ($clientInitial === '' || $clientInitial === false) {
                    $clientInitial = '?';
                }
                $clientInitial = function_exists('mb_strtoupper')
                    ? mb_strtoupper((string) $clientInitial, 'UTF-8')
                    : strtoupper((string) $clientInitial);
            ?>
            <section class="client-group">
                <header class="client-group-header">
                    <div class="client-brand">
                        <?php if ($clientLogoUrl !== ''): ?>
                            <img class="client-brand-logo" src="<?= htmlspecialchars($clientLogoUrl) ?>" alt="Logo de <?= htmlspecialchars($clientNameGroup) ?>">
                        <?php else: ?>
                            <span class="client-brand-avatar" aria-hidden="true"><?= htmlspecialchars($clientInitial) ?></span>
                        <?php endif; ?>
                        <div>
                            <h3 class="client-group-title"><?= htmlspecialchars($clientNameGroup) ?></h3>
                            <p class="client-group-subtitle"><?= (int) ($clientGroup['project_count'] ?? 0) ?> proyecto(s)</p>
                        </div>
                    </div>
                </header>
                <div class="client-project-list">
                    <?php foreach (($clientGroup['projects'] ?? []) as $project): ?>
                        <?php
                            $compact = $compactStatus(is_array($project) ? $project : []);
                            $projectName = (string) ($project['name'] ?? 'Proyecto');
                            $pmName = (string) ($project['pm_name'] ?? 'Sin PM asignado');
                            $progress = max(0, min(100, (int) ($project['progress'] ?? 0)));
                        ?>
                        <a class="compact-project" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>">
                            <strong><?= htmlspecialchars($projectName) ?></strong>
                            <span>PM: <?= htmlspecialchars($pmName) ?></span>
                            <span class="badge <?= htmlspecialchars((string) $compact['class']) ?>"><?= htmlspecialchars((string) $compact['label']) ?></span>
                            <span><?= $progress ?>%</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
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
