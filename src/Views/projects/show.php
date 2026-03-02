<?php
$basePath = $basePath ?? '';
$project = $project ?? [];
$projectNodes = is_array($projectNodes ?? null) ? $projectNodes : [];
$progressPhases = is_array($progressPhases ?? null) ? $progressPhases : [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$currentUser = is_array($currentUser ?? null) ? $currentUser : [];
$canManage = !empty($canManage);
$canDelete = !empty($canDelete);
$canInactivate = !empty($canInactivate);
$isManualProgress = ($project['progress_mode'] ?? $project['progress_type'] ?? 'manual') !== 'automatic';
$canUpdateProgress = !empty($canUpdateProgress) && $isManualProgress;
$progressHistory = is_array($progressHistory ?? null) ? $progressHistory : [];
$progressIndicators = is_array($progressIndicators ?? null) ? $progressIndicators : [];
$projectNotes = is_array($projectNotes ?? null) ? $projectNotes : [];
$view = $_GET['view'] ?? 'documentos';
$returnUrl = $_GET['return'] ?? ($basePath . '/projects');
$view = in_array($view, ['resumen', 'documentos', 'seguimiento'], true) ? $view : 'documentos';

$methodology = strtolower((string) ($project['methodology'] ?? 'cascada'));
if ($methodology === 'convencional' || $methodology === '') {
    $methodology = 'cascada';
}

$badgeLabel = $methodology === 'scrum' ? 'Scrum' : 'Tradicional';
$badgeClass = $methodology === 'scrum' ? 'pill methodology scrum' : 'pill methodology traditional';

$flatten = static function (array $nodes) use (&$flatten): array {
    $items = [];
    foreach ($nodes as $node) {
        $items[] = $node;
        if (!empty($node['children'])) {
            $items = array_merge($items, $flatten($node['children']));
        }
    }
    return $items;
};

$tree = $projectNodes;
$allNodes = $flatten($tree);
$nodesById = [];
foreach ($allNodes as $node) {
    if (isset($node['id'])) {
        $nodesById[(int) $node['id']] = $node;
    }
}

$rootNodeId = null;
$rootNode = null;
foreach ($allNodes as $node) {
    if (($node['code'] ?? '') === 'ROOT') {
        $rootNodeId = (int) ($node['id'] ?? 0);
        $rootNode = $node;
        break;
    }
}

$selectedNodeId = $selectedNodeId ?? (isset($_GET['node']) ? (int) $_GET['node'] : null);
$selectedNode = $selectedNodeId && isset($nodesById[$selectedNodeId]) ? $nodesById[$selectedNodeId] : null;

$rootChildren = $rootNode['children'] ?? $tree;
$rootNodesByCode = [];
foreach ($rootChildren as $child) {
    if (($child['code'] ?? '') === 'ROOT') {
        continue;
    }
    $rootNodesByCode[(string) ($child['code'] ?? '')] = $child;
}

$traditionalPhaseOrder = [
    '01-INICIO',
    '02-PLANIFICACION',
    '03-DISEÑO',
    '04-EJECUCION',
    '05-SEGUIMIENTO_Y_CONTROL',
    '06-CIERRE',
];
$scrumPhaseOrder = [
    '01-INICIO',
    '02-BACKLOG',
    '03-SPRINTS',
    '04-CIERRE',
];
$phaseOrder = $methodology === 'scrum' ? $scrumPhaseOrder : $traditionalPhaseOrder;

$phaseNodes = [];
foreach ($phaseOrder as $code) {
    if (!empty($rootNodesByCode[$code])) {
        $phaseNodes[] = $rootNodesByCode[$code];
    }
}
if (empty($phaseNodes)) {
    $phaseNodes = $rootChildren;
}

$sprintGroupNode = $rootNodesByCode['03-SPRINTS'] ?? null;
$sprintNodes = $sprintGroupNode['children'] ?? [];

if (!$selectedNode) {
    if ($methodology === 'scrum' && !empty($sprintNodes)) {
        $selectedNode = $sprintNodes[0];
    } else {
        $selectedNode = $phaseNodes[0] ?? null;
    }
}

$resolvePhaseNode = static function (?array $node, ?int $rootId, ?array $sprintGroup, array $nodesById): ?array {
    if (!$node) {
        return null;
    }
    $sprintGroupId = $sprintGroup['id'] ?? null;
    $cursor = $node;
    while (is_array($cursor)) {
        $parentId = $cursor['parent_id'] ?? null;
        if ($parentId === $rootId) {
            return $cursor;
        }
        if ($sprintGroupId !== null && $parentId === $sprintGroupId) {
            return $cursor;
        }
        if ($parentId === null) {
            break;
        }
        $cursor = $nodesById[(int) $parentId] ?? null;
    }
    return $node;
};

$activePhaseNode = $resolvePhaseNode($selectedNode, $rootNodeId, $sprintGroupNode, $nodesById);
$isSprint = $activePhaseNode && $sprintGroupNode && (int) ($activePhaseNode['parent_id'] ?? 0) === (int) ($sprintGroupNode['id'] ?? 0);

$subphaseLabelMap = [
    '01-ENTRADAS' => 'Entradas',
    '04-EVIDENCIAS' => 'Evidencias',
    '05-CAMBIOS' => 'Cambios',
];
$standardSubphaseSuffixes = array_keys($subphaseLabelMap);
$subphaseNodes = [];
if ($activePhaseNode) {
    foreach ($activePhaseNode['children'] ?? [] as $child) {
        $parts = explode('-', (string) ($child['code'] ?? ''));
        if (count($parts) >= 2) {
            $suffix = implode('-', array_slice($parts, -2));
            if (in_array($suffix, $standardSubphaseSuffixes, true)) {
                $subphaseNodes[$suffix] = $child;
            }
        }
    }
}

$selectedSuffix = null;
if (is_array($selectedNode)) {
    $parts = explode('-', (string) ($selectedNode['code'] ?? ''));
    if (count($parts) >= 2) {
        $selectedSuffix = implode('-', array_slice($parts, -2));
    }
}
$activeSubphaseSuffix = $selectedSuffix && isset($subphaseNodes[$selectedSuffix]) ? $selectedSuffix : (array_key_first($subphaseNodes) ?: null);
$activeSubphaseNode = $activeSubphaseSuffix ? ($subphaseNodes[$activeSubphaseSuffix] ?? null) : null;
$isSubphase = $activeSubphaseNode !== null;
$phaseCodeForFlow = (string) ($activePhaseNode['code'] ?? '');

$documentFlowConfig = is_array($documentFlowConfig ?? null) ? $documentFlowConfig : [];
$documentFlowExpectedDocs = is_array($documentFlowConfig['expected_docs'] ?? null) ? $documentFlowConfig['expected_docs'] : [];
$documentFlowTagOptions = is_array($documentFlowConfig['tag_options'] ?? null) ? $documentFlowConfig['tag_options'] : [];
$expectedDocsForSubphase = [];
if ($isSubphase && $activeSubphaseSuffix) {
    $methodDocs = is_array($documentFlowExpectedDocs[$methodology] ?? null) ? $documentFlowExpectedDocs[$methodology] : [];
    $phaseDocs = is_array($methodDocs[$phaseCodeForFlow] ?? null) ? $methodDocs[$phaseCodeForFlow] : ($methodDocs['default'] ?? []);
    $expectedDocsForSubphase = is_array($phaseDocs[$activeSubphaseSuffix] ?? null) ? $phaseDocs[$activeSubphaseSuffix] : [];
}

$statusLabels = [
    'pendiente' => 'Pendiente',
    'en_progreso' => 'En progreso',
    'completado' => 'Completo',
];

$statusBadgeClass = static function (string $status): string {
    return match ($status) {
        'completado' => 'status-success',
        'en_progreso' => 'status-info',
        default => 'status-muted',
    };
};

$normalizeExpectedDoc = static function (mixed $doc): ?array {
    if (is_string($doc)) {
        return [
            'name' => $doc,
            'document_type' => $doc,
            'requires_approval' => true,
        ];
    }
    if (!is_array($doc)) {
        return null;
    }
    $name = trim((string) ($doc['name'] ?? $doc['label'] ?? $doc['document_type'] ?? ''));
    if ($name === '') {
        return null;
    }
    return [
        'name' => $name,
        'document_type' => trim((string) ($doc['document_type'] ?? $name)) ?: $name,
        'requires_approval' => array_key_exists('requires_approval', $doc) ? (bool) $doc['requires_approval'] : true,
    ];
};

$phaseProgressByCode = [];
foreach ($progressPhases as $phaseProgress) {
    if (isset($phaseProgress['code'])) {
        $phaseProgressByCode[$phaseProgress['code']] = $phaseProgress;
    }
}

$computePhaseMetrics = static function (array $phaseNode) use ($documentFlowExpectedDocs, $methodology, $standardSubphaseSuffixes, $normalizeExpectedDoc): array {
    $methodDocs = is_array($documentFlowExpectedDocs[$methodology] ?? null) ? $documentFlowExpectedDocs[$methodology] : [];
    $phaseCode = (string) ($phaseNode['code'] ?? '');
    $phaseDocs = is_array($methodDocs[$phaseCode] ?? null) ? $methodDocs[$phaseCode] : ($methodDocs['default'] ?? []);

    $childrenBySuffix = [];
    foreach ($phaseNode['children'] ?? [] as $child) {
        $parts = explode('-', (string) ($child['code'] ?? ''));
        if (count($parts) >= 2) {
            $suffix = implode('-', array_slice($parts, -2));
            $childrenBySuffix[$suffix] = $child;
        }
    }

    $subphaseKeys = array_keys($phaseDocs);
    $availableSubphases = array_keys($childrenBySuffix);
    if (!empty($availableSubphases)) {
        $subphaseKeys = !empty($subphaseKeys)
            ? array_values(array_intersect($subphaseKeys, $availableSubphases))
            : $availableSubphases;
    }
    if (empty($subphaseKeys)) {
        $subphaseKeys = $standardSubphaseSuffixes;
    }

    $approvedRequired = 0;
    $totalRequired = 0;

    foreach ($subphaseKeys as $suffix) {
        $docs = is_array($phaseDocs[$suffix] ?? null) ? $phaseDocs[$suffix] : [];
        $expectedDocs = array_values(array_filter(array_map($normalizeExpectedDoc, $docs)));
        $requiredDocs = array_values(array_filter($expectedDocs, static fn (array $doc): bool => (bool) ($doc['requires_approval'] ?? false)));
        $totalRequired += count($requiredDocs);
        $files = $childrenBySuffix[$suffix]['files'] ?? [];
        foreach ($requiredDocs as $doc) {
            $expectedType = strtolower(trim((string) ($doc['document_type'] ?? $doc['name'] ?? '')));
            if ($expectedType === '') {
                continue;
            }
            foreach ($files as $file) {
                if (($file['document_status'] ?? '') !== 'aprobado') {
                    continue;
                }
                $type = strtolower(trim((string) ($file['document_type'] ?? '')));
                $tags = array_map(static fn (string $tag) => strtolower(trim($tag)), $file['tags'] ?? []);
                if ($type === $expectedType || in_array($expectedType, $tags, true)) {
                    $approvedRequired++;
                    break;
                }
            }
        }
    }

    $progress = $totalRequired > 0 ? round(($approvedRequired / $totalRequired) * 100, 1) : 0;
    $status = $totalRequired > 0 && $approvedRequired >= $totalRequired ? 'completado' : ($approvedRequired > 0 ? 'en_progreso' : 'pendiente');

    return [
        'status' => $status,
        'progress' => $progress,
        'approved_required' => $approvedRequired,
        'total_required' => $totalRequired,
    ];
};

$projectProgress = (float) ($project['progress'] ?? 0);
$projectStatusLabel = $project['status_label'] ?? $project['status'] ?? 'Estado no registrado';
$projectClient = $project['client_name'] ?? $project['client'] ?? '';
$projectMethodLabel = $badgeLabel;
$projectPmName = $project['pm_name'] ?? 'Sin PM asignado';
$projectStage = (string) ($project['project_stage'] ?? 'Discovery');
$projectRiskLabel = $project['health_label'] ?? $project['health'] ?? 'Sin riesgo';
$projectRiskLevel = strtolower((string) ($project['risk_level'] ?? ''));
$riskClass = match ($projectRiskLevel) {
    'alto' => 'status-danger',
    'medio' => 'status-warning',
    'bajo' => 'status-success',
    default => 'status-muted',
};
$activePhaseName = $activePhaseNode['name'] ?? $activePhaseNode['title'] ?? $activePhaseNode['code'] ?? 'Fase';
$activePhaseMetrics = ['status' => 'pendiente', 'progress' => 0, 'approved_required' => 0, 'total_required' => 0];
if ($activePhaseNode) {
    $phaseCode = (string) ($activePhaseNode['code'] ?? '');
    $activePhaseMetrics = $phaseProgressByCode[$phaseCode] ?? $computePhaseMetrics($activePhaseNode);
}
$activePhaseStatusLabel = $statusLabels[$activePhaseMetrics['status']] ?? $activePhaseMetrics['status'];
$activeSubphaseIso = $activeSubphaseNode['iso_code'] ?? $activePhaseNode['iso_code'] ?? null;
$activeSubphaseName = $activeSubphaseNode['name'] ?? $activeSubphaseNode['title'] ?? '';
$activeTabLabel = $activeSubphaseSuffix ? ($subphaseLabelMap[$activeSubphaseSuffix] ?? 'Subfase') : 'Subfase';
$tabDescriptions = [
    '01-ENTRADAS' => 'Documentos base para iniciar la fase: propuestas, acuerdos y requisitos.',
    '04-EVIDENCIAS' => 'Entregables, actas e informes que evidencian la ejecución.',
    '05-CAMBIOS' => 'Registros de cambios, impacto y aprobaciones asociadas.',
];
$activeTabDescription = $activeSubphaseSuffix ? ($tabDescriptions[$activeSubphaseSuffix] ?? '') : '';
$approvedDocuments = (int) ($progressIndicators['approved_documents'] ?? 0);
$pendingControls = (int) ($progressIndicators['pending_controls'] ?? 0);
$loggedHours = $progressIndicators['logged_hours'] ?? null;
$lastProgressEntry = $progressHistory[0] ?? null;
$lastProgressUser = $lastProgressEntry['user_name'] ?? 'Sistema';
$lastProgressPayload = is_array($lastProgressEntry['payload'] ?? null) ? $lastProgressEntry['payload'] : [];
$lastProgressJustification = trim((string) ($lastProgressPayload['justification'] ?? ''));
$formatTimestamp = static function (?string $value): string {
    if (!$value) {
        return 'Sin registro';
    }

    $timestamp = strtotime($value);
    if (!$timestamp) {
        return 'Sin registro';
    }

    return date('d/m/Y H:i', $timestamp);
};
$lastProgressDate = $lastProgressEntry ? $formatTimestamp($lastProgressEntry['created_at'] ?? null) : 'Sin registro';

$healthScore = is_array($healthScore ?? null) ? $healthScore : [
    'total_score' => 0,
    'documental_score' => 0,
    'avance_score' => 0,
    'horas_score' => 0,
    'seguimiento_score' => 0,
    'riesgo_score' => 0,
    'calidad_requisitos_score' => 0,
];
$healthTotal = (int) ($healthScore['total_score'] ?? 0);
$healthTone = $healthTotal >= 90 ? 'health-green' : ($healthTotal >= 75 ? 'health-yellow' : 'health-red');
$healthLabel = $healthTotal >= 90 ? 'Salud óptima' : ($healthTotal >= 75 ? 'Atención' : 'Crítico');
$healthBreakdown = is_array($healthScore['breakdown'] ?? null) ? $healthScore['breakdown'] : [];
$healthRecommendations = is_array($healthScore['recommendations'] ?? null) ? $healthScore['recommendations'] : [];
$healthHistory = is_array($healthHistory ?? null) ? $healthHistory : [];
$healthLevel = (string) ($healthScore['level'] ?? ($healthTotal >= 90 ? 'optimal' : ($healthTotal >= 75 ? 'attention' : 'critical')));
?>

<section class="project-shell">
    <header class="project-header">
        <div class="project-title-block">
            <p class="eyebrow">Detalle de proyecto</p>
            <h2><?= htmlspecialchars($project['name'] ?? '') ?></h2>
            <div class="project-badges">
                <span class="<?= $badgeClass ?>"><?= htmlspecialchars($badgeLabel) ?></span>
                <span class="pill neutral">Cliente: <?= htmlspecialchars($projectClient) ?></span>
                <span class="pill neutral">Estado: <?= htmlspecialchars((string) $projectStatusLabel) ?></span>
                <span class="pill neutral">Stage-gate: <?= htmlspecialchars($projectStage) ?></span>
            </div>
        </div>

        <div class="project-health-card <?= $healthTone ?>" data-health-popover-root>
            <div class="project-health-score">[ <?= $healthTotal ?> / 100 ]</div>
            <div class="project-health-title">Salud Integral</div>
            <div class="project-health-state"><?= $healthTone === 'health-green' ? '🟢' : ($healthTone === 'health-yellow' ? '🟡' : '🔴') ?> <?= htmlspecialchars($healthLabel) ?></div>
            <button type="button" class="action-btn" data-toggle-health-popover>Ver desglose</button>
            <button type="button" class="action-btn primary" data-open-modal="health-modal">Ver análisis completo</button>
            <div class="health-popover" data-health-popover>
                <h4>📊 Desglose Salud Integral</h4>
                <p><strong>Salud total: <?= $healthTotal ?> / 100</strong></p>
                <?php foreach ($healthBreakdown as $dimension => $item): ?>
                    <?php
                    $percentage = (int) ($item['percentage'] ?? 0);
                    $tone = $percentage >= 85 ? 'dim-green' : ($percentage >= 70 ? 'dim-yellow' : 'dim-red');
                    $label = ucfirst(str_replace('_', ' ', (string) $dimension));
                    $issues = is_array($item['issues'] ?? null) ? $item['issues'] : [];
                    ?>
                    <div class="health-dimension <?= $tone ?>">
                        <strong><?= htmlspecialchars($label) ?>:</strong> <?= $percentage ?>% (<?= (int) ($item['score'] ?? 0) ?>/<?= (int) ($item['max'] ?? 0) ?>)
                        <?php if ($issues): ?><div>⚠ <?= htmlspecialchars((string) $issues[0]) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="project-actions">
            <a class="action-btn" href="<?= htmlspecialchars($returnUrl) ?>">Volver al listado</a>
            <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/edit">Editar proyecto</a>
            <?php if ($canDelete || $canInactivate): ?>
                <a class="action-btn danger" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/edit#zona-critica">
                    <?= $canDelete ? 'Eliminar proyecto' : 'Inactivar proyecto' ?>
                </a>
            <?php endif; ?>
        </div>
    </header>


    <?php if ($canDelete || $canInactivate): ?>
        <section class="danger-shortcut" aria-label="Acción crítica del proyecto">
            <p>
                <?= $canDelete
                    ? '¿Necesitas eliminar el proyecto con todas sus dependencias? Usa el acceso directo a Zona crítica.'
                    : '¿Necesitas inactivar este proyecto? Usa el acceso directo a Zona crítica.' ?>
            </p>
            <a class="action-btn danger" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/edit#zona-critica">
                <?= $canDelete ? 'Ir a eliminar proyecto' : 'Ir a inactivar proyecto' ?>
            </a>
        </section>
    <?php endif; ?>

    <?php
    $activeTab = match ($view) {
        'resumen' => 'resumen',
        'seguimiento' => 'seguimiento',
        default => 'documents',
    };
    require __DIR__ . '/_tabs.php';
    ?>

    <?php if ($view === 'resumen'): ?>
        <section class="summary-layout">
            <article class="info-card">
                <div class="info-card__header">
                    <div>
                        <p class="eyebrow">Información base</p>
                        <h4>Ficha del proyecto</h4>
                    </div>
                </div>
                <div class="info-list">
                    <div>
                        <span>Cliente</span>
                        <strong><?= htmlspecialchars($projectClient ?: 'Sin cliente') ?></strong>
                    </div>
                    <div>
                        <span>Metodología</span>
                        <strong><?= htmlspecialchars($projectMethodLabel) ?></strong>
                    </div>
                    <div>
                        <span>Estado</span>
                        <strong><?= htmlspecialchars((string) $projectStatusLabel) ?></strong>
                    </div>
                    <div>
                        <span>Stage-gate</span>
                        <strong><?= htmlspecialchars($projectStage) ?></strong>
                    </div>
                    <div>
                        <span>PM</span>
                        <strong><?= htmlspecialchars($projectPmName) ?></strong>
                    </div>
                    <div>
                        <span>Fechas</span>
                        <strong><?= htmlspecialchars($project['start_date'] ?? 'Sin inicio') ?> → <?= htmlspecialchars($project['end_date'] ?? 'Sin fin') ?></strong>
                    </div>
                    <div>
                        <span>Riesgo</span>
                        <strong><?= htmlspecialchars($projectRiskLabel) ?></strong>
                        <span class="badge status-badge <?= $riskClass ?>">Nivel <?= htmlspecialchars($projectRiskLevel ?: 'N/A') ?></span>
                    </div>
                </div>
            </article>

            <article class="progress-card">
                <div class="progress-card__header">
                    <div>
                        <p class="eyebrow">Avance global manual</p>
                        <h4>Visión ejecutiva del progreso</h4>
                    </div>
                    <?php if ($canUpdateProgress): ?>
                        <button class="action-btn primary" type="button" data-open-modal="progress-modal">Actualizar avance</button>
                    <?php endif; ?>
                </div>
                <div class="project-progress">
                    <span class="project-progress__label">Avance global</span>
                    <div class="project-progress__bar" role="progressbar" aria-valuenow="<?= $projectProgress ?>" aria-valuemin="0" aria-valuemax="100">
                        <div style="width: <?= $projectProgress ?>%;"></div>
                    </div>
                    <span class="project-progress__value"><?= $projectProgress ?>% completado</span>
                </div>
                <div class="progress-meta">
                    <div class="progress-meta__item">
                        <span>Última actualización</span>
                        <strong><?= htmlspecialchars($lastProgressDate) ?></strong>
                    </div>
                    <div class="progress-meta__item">
                        <span>Actualizado por</span>
                        <strong><?= htmlspecialchars($lastProgressUser ?: 'Sistema') ?></strong>
                    </div>
                    <div class="progress-meta__item full">
                        <span>Justificación</span>
                        <p><?= $lastProgressJustification !== '' ? htmlspecialchars($lastProgressJustification) : 'Sin registro' ?></p>
                    </div>
                </div>
            </article>
        </section>

        <section class="indicator-grid">
            <article class="indicator-card">
                <span class="indicator-icon">📄</span>
                <div>
                    <span>Docs aprobados</span>
                    <strong><?= $approvedDocuments ?></strong>
                </div>
            </article>
            <article class="indicator-card">
                <span class="indicator-icon">⏳</span>
                <div>
                    <span>Controles pendientes</span>
                    <strong><?= $pendingControls ?></strong>
                </div>
            </article>
            <article class="indicator-card">
                <span class="indicator-icon">⏱️</span>
                <div>
                    <span>Horas registradas</span>
                    <strong><?= $loggedHours !== null ? number_format((float) $loggedHours, 1) : 'N/A' ?></strong>
                    <small><?= $loggedHours !== null ? 'Timesheets vinculados' : 'Sin timesheets' ?></small>
                </div>
            </article>
        </section>

        <section class="progress-history">
            <div class="history-card">
                <div class="history-header">
                    <div>
                        <p class="eyebrow">Historial de avance</p>
                        <h4>Bitácora de decisiones</h4>
                    </div>
                </div>
                <?php if (empty($progressHistory)): ?>
                    <p class="section-muted">Aún no hay actualizaciones registradas.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Usuario</th>
                                    <th>Anterior</th>
                                    <th>Nuevo</th>
                                    <th>Justificación</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($progressHistory as $entry): ?>
                                    <?php
                                    $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
                                    $previous = $payload['previous_progress'] ?? null;
                                    $next = $payload['new_progress'] ?? null;
                                    $justification = trim((string) ($payload['justification'] ?? ''));
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($formatTimestamp($entry['created_at'] ?? null)) ?></td>
                                        <td><?= htmlspecialchars($entry['user_name'] ?? 'Sistema') ?></td>
                                        <td><?= $previous !== null ? number_format((float) $previous, 1) . '%' : '-' ?></td>
                                        <td><?= $next !== null ? number_format((float) $next, 1) . '%' : '-' ?></td>
                                        <td><?= $justification !== '' ? htmlspecialchars($justification) : 'Sin registro' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php elseif ($view === 'seguimiento'): ?>
        <section class="notes-grid" id="project-notes">
            <article class="notes-card">
                <div class="notes-card__header">
                    <div>
                        <p class="eyebrow">Notas y seguimiento</p>
                        <h4>Registro operativo del proyecto</h4>
                    </div>
                    <span class="pill neutral">Historial activo</span>
                </div>
                <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/notes" class="notes-form">
                    <label class="notes-field">
                        <span>Nueva nota</span>
                        <textarea name="note" rows="4" placeholder="Registra acuerdos, seguimiento y decisiones clave." required></textarea>
                    </label>
                    <div class="notes-actions">
                        <button type="submit" class="action-btn primary">Guardar nota</button>
                    </div>
                </form>
            </article>

            <article class="notes-card">
                <div class="notes-card__header">
                    <div>
                        <p class="eyebrow">Historial</p>
                        <h4>Seguimientos recientes</h4>
                    </div>
                </div>
                <?php if (empty($projectNotes)): ?>
                    <p class="section-muted">Aún no hay notas registradas para este proyecto.</p>
                <?php else: ?>
                    <div class="notes-timeline">
                        <?php foreach ($projectNotes as $note): ?>
                            <?php
                            $payload = is_array($note['payload'] ?? null) ? $note['payload'] : [];
                            $noteText = trim((string) ($payload['note'] ?? ''));
                            ?>
                            <div class="notes-entry">
                                <div>
                                    <strong><?= htmlspecialchars($note['user_name'] ?? 'Sistema') ?></strong>
                                    <span class="section-muted"><?= htmlspecialchars($formatTimestamp($note['created_at'] ?? null)) ?></span>
                                </div>
                                <p><?= $noteText !== '' ? nl2br(htmlspecialchars($noteText)) : 'Sin detalle registrado.' ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </section>
    <?php else: ?>
        <div class="project-layout">
            <aside class="phase-sidebar">
                <div class="phase-sidebar__header">
                    <div>
                        <strong>Explorador de fases</strong>
                        <p class="section-muted">Accede a cada fase del expediente. Las subfases viven en los tabs centrales.</p>
                    </div>
                    <?php if ($canManage && $methodology === 'scrum'): ?>
                        <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/sprints">
                            <button class="action-btn small" type="submit">Nuevo sprint</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if (empty($phaseNodes)): ?>
                    <p class="section-muted">Sin fases disponibles.</p>
                <?php else: ?>
                    <ul class="phase-list">
                        <?php foreach ($phaseNodes as $phase): ?>
                            <?php
                            $phaseCode = (string) ($phase['code'] ?? '');
                            $phaseMetrics = $phaseProgressByCode[$phaseCode] ?? $computePhaseMetrics($phase);
                            $phaseStatusLabel = $statusLabels[$phaseMetrics['status']] ?? $phaseMetrics['status'];
                            $phaseProgressLabel = ($methodology === 'scrum' && ($phase['code'] ?? '') === '03-SPRINTS')
                                ? count($sprintNodes) . ' sprints activos'
                                : ($phaseMetrics['total_required'] > 0
                                    ? $phaseMetrics['approved_required'] . ' de ' . $phaseMetrics['total_required'] . ' documentos clave'
                                    : 'Sin documentos clave configurados');
                            $isPhaseActive = $activePhaseNode && (int) ($activePhaseNode['id'] ?? 0) === (int) ($phase['id'] ?? 0);
                            $phaseLink = $basePath . '/projects/' . (int) ($project['id'] ?? 0) . '?view=documentos&node=' . (int) ($phase['id'] ?? 0);
                            ?>
                            <?php if ($methodology === 'scrum' && ($phase['code'] ?? '') === '03-SPRINTS'): ?>
                                <li class="phase-item">
                                    <details class="phase-group" <?= $isSprint ? 'open' : '' ?>>
                                        <summary class="phase-link">
                                            <div class="phase-link__title">
                                                <span class="phase-icon">📁</span>
                                                <div>
                                                    <strong><?= htmlspecialchars($phase['name'] ?? $phase['title'] ?? 'Sprints') ?></strong>
                                                    <small class="section-muted"><?= htmlspecialchars($phaseProgressLabel) ?></small>
                                                </div>
                                            </div>
                                            <span class="badge status-badge <?= $statusBadgeClass($phaseMetrics['status']) ?>"><?= htmlspecialchars($phaseStatusLabel) ?></span>
                                        </summary>
                                        <ul class="phase-sublist">
                                            <?php foreach ($sprintNodes as $sprint): ?>
                                                <?php
                                                $sprintMetrics = $computePhaseMetrics($sprint);
                                                $sprintStatusLabel = $statusLabels[$sprintMetrics['status']] ?? $sprintMetrics['status'];
                                                $sprintProgressLabel = $sprintMetrics['total_required'] > 0
                                                    ? $sprintMetrics['approved_required'] . ' de ' . $sprintMetrics['total_required'] . ' documentos clave'
                                                    : 'Sin documentos clave configurados';
                                                $isSprintActive = $activePhaseNode && (int) ($activePhaseNode['id'] ?? 0) === (int) ($sprint['id'] ?? 0);
                                                $sprintLink = $basePath . '/projects/' . (int) ($project['id'] ?? 0) . '?view=documentos&node=' . (int) ($sprint['id'] ?? 0);
                                                ?>
                                                <li>
                                                    <a class="phase-link <?= $isSprintActive ? 'active' : '' ?>" href="<?= htmlspecialchars($sprintLink) ?>">
                                                        <div class="phase-link__title">
                                                            <span class="phase-icon">📁</span>
                                                            <div>
                                                                <strong><?= htmlspecialchars($sprint['name'] ?? $sprint['title'] ?? '') ?></strong>
                                                                <small class="section-muted"><?= htmlspecialchars($sprintProgressLabel) ?></small>
                                                            </div>
                                                        </div>
                                                        <span class="badge status-badge <?= $statusBadgeClass($sprintMetrics['status']) ?>"><?= htmlspecialchars($sprintStatusLabel) ?></span>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </details>
                                </li>
                            <?php else: ?>
                                <li class="phase-item">
                                    <a class="phase-link <?= $isPhaseActive ? 'active' : '' ?>" href="<?= htmlspecialchars($phaseLink) ?>">
                                        <div class="phase-link__title">
                                            <span class="phase-icon">📁</span>
                                            <div>
                                                <strong><?= htmlspecialchars($phase['name'] ?? $phase['title'] ?? $phase['code'] ?? '') ?></strong>
                                                <small class="section-muted"><?= htmlspecialchars($phaseProgressLabel) ?></small>
                                            </div>
                                        </div>
                                        <span class="badge status-badge <?= $statusBadgeClass($phaseMetrics['status']) ?>"><?= htmlspecialchars($phaseStatusLabel) ?></span>
                                    </a>
                                    <div class="phase-progress-bar" role="progressbar" aria-valuenow="<?= $phaseMetrics['progress'] ?>" aria-valuemin="0" aria-valuemax="100">
                                        <div style="width: <?= $phaseMetrics['progress'] ?>%;"></div>
                                    </div>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </aside>

            <main class="phase-panel">
                <?php if (!$activePhaseNode): ?>
                    <p class="section-muted">Selecciona una fase para comenzar.</p>
                <?php else: ?>
                    <header class="phase-panel__header">
                        <div>
                            <nav class="breadcrumb">
                                <a href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>?view=documentos">Proyecto</a>
                                <span>›</span>
                                <?php if ($isSprint && $sprintGroupNode): ?>
                                    <span><?= htmlspecialchars($sprintGroupNode['name'] ?? $sprintGroupNode['title'] ?? 'Sprints') ?></span>
                                    <span>›</span>
                                    <span><?= htmlspecialchars($activePhaseName) ?></span>
                                <?php else: ?>
                                    <span><?= htmlspecialchars($activePhaseName) ?></span>
                                <?php endif; ?>
                            </nav>
                            <h3><?= htmlspecialchars($activePhaseName) ?> <?= $isSprint ? '(Sprint)' : '' ?></h3>
                            <div class="phase-meta">
                                <span class="badge status-badge <?= $statusBadgeClass($activePhaseMetrics['status']) ?>"><?= htmlspecialchars($activePhaseStatusLabel) ?></span>
                                <span class="count-pill">Avance <?= $activePhaseMetrics['progress'] ?>%</span>
                                <?php if ($activeSubphaseIso): ?>
                                    <span class="count-pill">ISO <?= htmlspecialchars((string) $activeSubphaseIso) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($canManage): ?>
                            <div class="phase-actions">
                                <?php if ($activePhaseNode): ?>
                                    <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/nodes/<?= (int) ($activePhaseNode['id'] ?? 0) ?>/delete" onsubmit="return confirm('¿Eliminar esta fase y su contenido?');">
                                        <button type="submit" class="action-btn danger">Eliminar fase</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($activeSubphaseNode): ?>
                                    <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/nodes/<?= (int) ($activeSubphaseNode['id'] ?? 0) ?>/delete" onsubmit="return confirm('¿Eliminar esta subfase y su contenido?');">
                                        <button type="submit" class="action-btn">Eliminar subfase</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </header>

                    <div class="phase-tabs">
                        <?php foreach ($subphaseLabelMap as $suffix => $label): ?>
                            <?php if (isset($subphaseNodes[$suffix])): ?>
                                <?php $tabLink = $basePath . '/projects/' . (int) ($project['id'] ?? 0) . '?view=documentos&node=' . (int) ($subphaseNodes[$suffix]['id'] ?? 0); ?>
                                <a class="phase-tab <?= $activeSubphaseSuffix === $suffix ? 'active' : '' ?>" href="<?= htmlspecialchars($tabLink) ?>">
                                    <?= htmlspecialchars($label) ?>
                                </a>
                            <?php else: ?>
                                <span class="phase-tab disabled"><?= htmlspecialchars($label) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($isSubphase && $activeSubphaseNode): ?>
                        <section class="phase-tab-panel">
                            <div class="phase-tab-panel__header">
                                <div>
                                    <p class="eyebrow">Subfase activa</p>
                                    <h4><?= htmlspecialchars($activeTabLabel) ?> · <?= htmlspecialchars($activeSubphaseName) ?></h4>
                                    <small class="section-muted"><?= htmlspecialchars($activeTabDescription ?: 'Documentación y control ISO 9001 para esta subfase.') ?></small>
                                </div>
                            </div>
                            <?php
                            $documentFlowId = 'document-flow-' . (int) ($activeSubphaseNode['id'] ?? 0);
                            $documentNode = $activeSubphaseNode;
                            $documentExpectedDocs = $expectedDocsForSubphase;
                            $documentTagOptions = $documentFlowTagOptions;
                            $documentKeyTags = $expectedDocsForSubphase;
                            $documentCanManage = $canManage;
                            $documentMode = $activeSubphaseSuffix;
                            $documentProjectId = (int) ($project['id'] ?? 0);
                            $documentBasePath = $basePath;
                            $documentCurrentUser = $currentUser;
                            require __DIR__ . '/document_flow.php';
                            ?>
                        </section>
                    <?php else: ?>
                        <section class="phase-tab-panel">
                            <h4>Gestión documental por subfase</h4>
                            <p class="section-muted">Esta fase aún no tiene subfases configuradas. Selecciona otra fase para continuar.</p>
                            <p class="phase-warning">No se permiten archivos sueltos fuera de subfases.</p>
                            <button class="action-btn primary" type="button" disabled>Subir documento (deshabilitado)</button>
                        </section>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    <?php endif; ?>
</section>

<?php if ($canUpdateProgress): ?>

    <div class="progress-modal" id="health-modal" aria-hidden="true">
        <div class="modal__backdrop" data-close-modal></div>
        <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="health-modal-title">
            <div class="modal__header">
                <h3 id="health-modal-title">Análisis completo de Salud Integral</h3>
                <button type="button" class="icon-btn" data-close-modal aria-label="Cerrar">✕</button>
            </div>
            <div class="modal__body">
                <div style="display:flex; gap:14px; align-items:center; flex-wrap:wrap;">
                    <svg width="120" height="120" viewBox="0 0 120 120" aria-label="Score general">
                        <circle cx="60" cy="60" r="50" stroke="var(--border)" stroke-width="12" fill="none"></circle>
                        <circle cx="60" cy="60" r="50" stroke="var(--primary)" stroke-width="12" fill="none" stroke-linecap="round" stroke-dasharray="314" stroke-dashoffset="<?= 314 - (314 * $healthTotal / 100) ?>" transform="rotate(-90 60 60)"></circle>
                        <text x="60" y="66" text-anchor="middle" font-size="24" font-weight="700"><?= $healthTotal ?></text>
                    </svg>
                    <div>
                        <p style="margin:0;"><strong><?= htmlspecialchars($healthLabel) ?></strong></p>
                        <p class="muted" style="margin:0;">Nivel: <?= htmlspecialchars($healthLevel) ?></p>
                    </div>
                </div>
                <div>
                    <?php foreach ($healthBreakdown as $dimension => $item): ?>
                        <?php $p = (int) ($item['percentage'] ?? 0); ?>
                        <div style="margin-bottom:8px;">
                            <div style="display:flex;justify-content:space-between;"><span><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $dimension))) ?></span><strong><?= $p ?>%</strong></div>
                            <div class="project-progress__bar"><div style="width: <?= $p ?>%;"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div>
                    <h4>Recomendaciones</h4>
                    <ul>
                        <?php foreach ($healthRecommendations as $recommendation): ?>
                            <li><?= htmlspecialchars((string) $recommendation) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div>
                    <h4>Tendencia últimos 30 días</h4>
                    <p class="muted"><?= count($healthHistory) ?> snapshots registrados.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="progress-modal" id="progress-modal" aria-hidden="true">
        <div class="modal__backdrop" data-close-modal></div>
        <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="progress-modal-title">
            <div class="modal__header">
                <h3 id="progress-modal-title">Actualizar avance</h3>
                <button type="button" class="icon-btn" data-close-modal aria-label="Cerrar">✕</button>
            </div>
            <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/progress" class="modal__body">
                <label class="modal__field">
                    <span>Nuevo porcentaje (0–100)</span>
                    <input type="number" name="progress" min="0" max="100" step="0.1" required>
                </label>
                <label class="modal__field">
                    <span>Justificación</span>
                    <textarea name="justification" rows="4" required></textarea>
                </label>
                <div class="modal__actions">
                    <button type="button" class="action-btn" data-close-modal>Cancelar</button>
                    <button type="submit" class="action-btn primary">Guardar avance</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
    const progressModal = document.getElementById('progress-modal');
    const healthModal = document.getElementById('health-modal');
    const openHealthButtons = document.querySelectorAll('[data-open-modal="health-modal"]');
    const closeHealthButtons = healthModal ? healthModal.querySelectorAll('[data-close-modal]') : [];
    const healthPopoverRoot = document.querySelector('[data-health-popover-root]');
    const healthPopover = document.querySelector('[data-health-popover]');
    const toggleHealthPopover = document.querySelector('[data-toggle-health-popover]');
    const openProgressButtons = document.querySelectorAll('[data-open-modal="progress-modal"]');
    const closeProgressButtons = progressModal ? progressModal.querySelectorAll('[data-close-modal]') : [];

    const toggleProgressModal = (open) => {
        if (!progressModal) return;
        progressModal.classList.toggle('is-visible', open);
        progressModal.setAttribute('aria-hidden', open ? 'false' : 'true');
    };

    openProgressButtons.forEach((button) => {
        button.addEventListener('click', () => {
            console.log('Click en Actualizar avance: handler activo.');
            toggleProgressModal(true);
        });
    });

    closeProgressButtons.forEach((button) => {
        button.addEventListener('click', () => toggleProgressModal(false));
    });

    const progressForm = progressModal ? progressModal.querySelector('form') : null;
    if (progressForm) {
        progressForm.addEventListener('submit', () => {
            console.log('Click en Guardar avance: handler activo.');
        });
    }

    if (healthModal) {
        openHealthButtons.forEach((button) => button.addEventListener('click', () => {
            healthModal.classList.add('is-visible');
            healthModal.setAttribute('aria-hidden', 'false');
        }));
        closeHealthButtons.forEach((button) => button.addEventListener('click', () => {
            healthModal.classList.remove('is-visible');
            healthModal.setAttribute('aria-hidden', 'true');
        }));
    }

    if (toggleHealthPopover && healthPopover) {
        toggleHealthPopover.addEventListener('click', () => {
            healthPopover.classList.toggle('is-visible');
        });
        document.addEventListener('click', (event) => {
            if (healthPopoverRoot && !healthPopoverRoot.contains(event.target)) {
                healthPopover.classList.remove('is-visible');
            }
        });
    }

    if (progressModal) {
        progressModal.addEventListener('click', (event) => {
            if (event.target === progressModal) {
                toggleProgressModal(false);
            }
        });
    }
</script>

<style>
    .project-shell { display:flex; flex-direction:column; gap:16px; }
    .project-header { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap; border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); }
    .project-health-card { min-width: 180px; padding: 12px 14px; border-radius: 14px; border: 1px solid var(--border); background: color-mix(in srgb, var(--surface) 90%, var(--background)); }
    .project-health-score { font-size: 24px; font-weight: 800; }
    .project-health-title { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-secondary); }
    .project-health-state { font-size: 13px; font-weight: 700; margin-top: 6px; }
    .project-health-card.health-green { border-color: color-mix(in srgb, var(--success) 40%, var(--border)); }
    .project-health-card.health-yellow { border-color: color-mix(in srgb, var(--warning) 45%, var(--border)); }
    .project-health-card.health-red { border-color: color-mix(in srgb, var(--danger) 45%, var(--border)); }
    .health-popover { display:none; position:absolute; margin-top:8px; right:0; width:min(420px, 90vw); padding:12px; border-radius:12px; background:var(--surface); border:1px solid var(--border); box-shadow:0 10px 24px color-mix(in srgb, var(--text-primary) 20%, var(--background)); z-index:3; }
    .health-popover.is-visible { display:block; }
    .project-health-card { position:relative; }
    .health-dimension { font-size:12px; margin:8px 0; padding:6px 8px; border-radius:8px; }
    .dim-green { background: color-mix(in srgb, var(--success) 12%, var(--background)); }
    .dim-yellow { background: color-mix(in srgb, var(--warning) 12%, var(--background)); }
    .dim-red { background: color-mix(in srgb, var(--danger) 12%, var(--background)); }
    .project-title-block { display:flex; flex-direction:column; gap:8px; }
    .project-title-block h2 { margin:0; color: var(--text-primary); }
    .project-actions { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
    .danger-shortcut { margin-top:12px; border:1px solid color-mix(in srgb, var(--danger) 30%, var(--background)); background: color-mix(in srgb, var(--danger) 10%, var(--surface) 90%); border-radius:12px; padding:12px; display:flex; gap:10px; align-items:center; justify-content:space-between; flex-wrap:wrap; }
    .danger-shortcut p { margin:0; color: var(--text-primary); font-weight:600; }
    .project-badges { display:flex; gap:8px; flex-wrap:wrap; }
    .pill.neutral { background: color-mix(in srgb, var(--text-secondary) 10%, var(--background)); border-color: var(--border); color: var(--text-primary); }
    .summary-layout { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:16px; }
    .info-card { border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); display:flex; flex-direction:column; gap:14px; }
    .info-list { display:grid; gap:10px; }
    .info-list span { font-size:12px; text-transform:uppercase; color: var(--text-secondary); font-weight:700; }
    .info-list strong { font-size:15px; color: var(--text-primary); }
    .progress-card { border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); display:flex; flex-direction:column; gap:12px; }
    .progress-card__header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .action-btn { background: var(--surface); color: var(--text-primary); border:1px solid var(--border); border-radius:8px; padding:8px 10px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
    .action-btn.primary { background: var(--primary); color: var(--text-primary); border-color: var(--primary); }
    .action-btn.danger { background: color-mix(in srgb, var(--danger) 12%, var(--background)); color: var(--danger); border-color: color-mix(in srgb, var(--danger) 35%, var(--background)); }
    .action-btn.small { padding:6px 8px; font-size:13px; }
    .pill { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-weight:700; }
    .pill.methodology { border:1px solid var(--background); }
    .pill.methodology.scrum { background: color-mix(in srgb, var(--info) 18%, var(--background)); color: var(--info); border-color: color-mix(in srgb, var(--info) 40%, var(--background)); }
    .pill.methodology.traditional { background: color-mix(in srgb, var(--primary) 18%, var(--background)); color: var(--primary); border-color: color-mix(in srgb, var(--primary) 40%, var(--background)); }
    .folder-meta { display:flex; gap:8px; align-items:center; margin-top:6px; flex-wrap:wrap; }
    .status-badge { font-size:12px; font-weight:700; padding:4px 8px; border-radius:999px; border:1px solid var(--background); }
    .status-muted { background: color-mix(in srgb, var(--text-secondary) 14%, var(--background)); color: var(--text-primary); border-color: var(--border); }
    .status-info { background: color-mix(in srgb, var(--primary) 14%, var(--background)); color: var(--primary); border-color: color-mix(in srgb, var(--primary) 30%, var(--background)); }
    .status-success { background: color-mix(in srgb, var(--success) 16%, var(--background)); color: var(--success); border-color: color-mix(in srgb, var(--success) 32%, var(--background)); }
    .status-warning { background: color-mix(in srgb, var(--warning) 16%, var(--background)); color: var(--warning); border-color: color-mix(in srgb, var(--warning) 32%, var(--background)); }
    .status-danger { background: color-mix(in srgb, var(--danger) 16%, var(--background)); color: var(--danger); border-color: color-mix(in srgb, var(--danger) 32%, var(--background)); }
    .count-pill { font-size:12px; font-weight:700; color: var(--text-primary); background: color-mix(in srgb, var(--text-secondary) 10%, var(--background)); border:1px solid var(--border); border-radius:999px; padding:4px 8px; }
    .breadcrumb { display:flex; flex-wrap:wrap; gap:6px; align-items:center; font-size:13px; color: var(--text-secondary); margin-bottom:8px; }
    .breadcrumb a { color: var(--text-primary); text-decoration:none; font-weight:600; }
    .breadcrumb span { color: var(--text-secondary); }
    .project-progress { display:flex; flex-direction:column; gap:4px; max-width:320px; }
    .project-progress__label { font-size:12px; text-transform:uppercase; color: var(--text-secondary); font-weight:700; }
    .project-progress__bar { background: color-mix(in srgb, var(--text-secondary) 22%, var(--background)); border-radius:999px; overflow:hidden; height:8px; }
    .project-progress__bar div { height:100%; background: var(--primary); border-radius:999px; }
    .project-progress__value { font-size:12px; color: var(--text-secondary); }
    .progress-meta { display:grid; gap:8px; width:100%; }
    .progress-meta__item { display:flex; flex-direction:column; gap:2px; font-size:12px; color: var(--text-secondary); }
    .progress-meta__item.full { grid-column: 1 / -1; }
    .progress-meta__item strong { font-size:13px; color: var(--text-primary); }
    .progress-meta__item.full p { margin:0; font-size:13px; color: var(--text-primary); }
    .indicator-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; }
    .indicator-card { border:1px solid var(--border); border-radius:14px; padding:12px; background: var(--surface); display:flex; align-items:center; gap:12px; }
    .indicator-card span { font-size:12px; text-transform:uppercase; color: var(--text-secondary); font-weight:700; }
    .indicator-card strong { font-size:18px; color: var(--text-primary); display:block; }
    .indicator-card small { font-size:12px; color: var(--text-secondary); }
    .indicator-icon { width:36px; height:36px; border-radius:10px; background: color-mix(in srgb, var(--primary) 12%, var(--background)); display:inline-flex; align-items:center; justify-content:center; }
    .progress-history { display:flex; }
    .history-card { border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); display:flex; flex-direction:column; gap:12px; width:100%; }
    .history-header { display:flex; justify-content:space-between; align-items:center; gap:12px; }
    .project-layout { display:grid; grid-template-columns: 280px 1fr; gap:16px; }
    .phase-sidebar { border:1px solid var(--border); border-radius:16px; padding:14px; background: color-mix(in srgb, var(--text-secondary) 8%, var(--background)); display:flex; flex-direction:column; gap:12px; max-height:72vh; overflow:auto; }
    .phase-sidebar__header { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; }
    .phase-list, .phase-sublist { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:10px; }
    .phase-item { display:flex; flex-direction:column; gap:6px; }
    .phase-link { display:flex; justify-content:space-between; gap:12px; align-items:center; padding:10px; border-radius:12px; text-decoration:none; color: var(--text-primary); border:1px solid var(--background); background: var(--surface); }
    .phase-link:hover { border-color: var(--border); }
    .phase-link.active { background: var(--secondary); color: var(--text-primary); border-color: var(--secondary); font-weight:700; }
    .phase-link.active .section-muted { color: color-mix(in srgb, var(--text-primary) 82%, var(--background)); }
    .phase-link__title { display:flex; gap:10px; align-items:center; }
    .phase-icon { font-size:18px; }
    .phase-progress-bar { height:6px; background: color-mix(in srgb, var(--text-secondary) 22%, var(--background)); border-radius:999px; overflow:hidden; }
    .phase-progress-bar div { height:100%; background: var(--primary); }
    .phase-group { border:1px solid var(--border); border-radius:12px; padding:8px; background: var(--surface); }
    .phase-group summary { list-style:none; cursor:pointer; }
    .phase-group summary::-webkit-details-marker { display:none; }
    .phase-sublist { margin-top:8px; padding-left:0; }
    .phase-panel { border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); min-height:70vh; display:flex; flex-direction:column; gap:16px; }
    .phase-panel__header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; border-bottom:1px solid var(--border); padding-bottom:12px; }
    .phase-panel__header h3 { margin:0; }
    .phase-meta { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .phase-actions { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
    .phase-tabs { display:flex; flex-wrap:wrap; gap:8px; border-bottom:1px solid var(--border); padding-bottom:8px; }
    .phase-tab { padding:8px 12px; border-radius:999px; border:1px solid var(--border); text-decoration:none; color: var(--text-primary); font-weight:700; font-size:13px; background: color-mix(in srgb, var(--text-secondary) 10%, var(--background)); }
    .phase-tab.active { background: var(--primary); color: var(--text-primary); border-color: var(--primary); }
    .phase-tab.disabled { opacity:0.5; cursor:not-allowed; }
    .phase-tab-panel { display:flex; flex-direction:column; gap:12px; }
    .phase-tab-panel__header { border:1px solid var(--border); border-radius:12px; padding:12px; background: color-mix(in srgb, var(--text-secondary) 12%, var(--background)); }
    .phase-warning { color: var(--danger); margin:0; }
    .progress-modal { position:fixed; inset:0; display:none; align-items:center; justify-content:center; z-index:50; }
    .progress-modal.is-visible { display:flex; }
    .progress-modal .modal__backdrop { position:absolute; inset:0; background: color-mix(in srgb, var(--text-primary) 45%, var(--background)); }
    .progress-modal .modal__panel { position:relative; background: var(--surface); border-radius:16px; padding:16px; width:min(520px, 90vw); box-shadow:0 20px 40px color-mix(in srgb, var(--text-primary) 25%, var(--background)); display:flex; flex-direction:column; gap:12px; z-index:1; }
    .modal__header { display:flex; justify-content:space-between; align-items:center; }
    .modal__header h3 { margin:0; }
    .modal__body { display:flex; flex-direction:column; gap:12px; }
    .modal__field { display:flex; flex-direction:column; gap:6px; font-weight:600; color: var(--text-primary); }
    .modal__field input,
    .modal__field textarea { padding:10px 12px; border-radius:10px; border:1px solid var(--border); }
    .modal__actions { display:flex; justify-content:flex-end; gap:8px; }
    .icon-btn { border:none; background:var(--background); cursor:pointer; font-size:16px; }
    .notes-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:16px; }
    .notes-card { border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); display:flex; flex-direction:column; gap:14px; }
    .notes-card__header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .notes-form { display:flex; flex-direction:column; gap:12px; }
    .notes-field { display:flex; flex-direction:column; gap:6px; font-size:13px; color: var(--text-secondary); }
    .notes-field textarea { width:100%; border-radius:12px; border:1px solid var(--border); padding:10px; font-size:14px; color: var(--text-primary); background: var(--surface); }
    .notes-actions { display:flex; justify-content:flex-end; }
    .notes-timeline { display:flex; flex-direction:column; gap:12px; }
    .notes-entry { padding:12px; border-radius:12px; border:1px solid var(--border); background: color-mix(in srgb, var(--text-secondary) 8%, var(--background)); display:flex; flex-direction:column; gap:8px; }
    .notes-entry strong { color: var(--text-primary); }
    @media (max-width: 960px) {
        .project-layout { grid-template-columns: 1fr; }
        .phase-sidebar { max-height:none; }
    }
</style>
