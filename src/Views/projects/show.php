<?php
$basePath = $basePath ?? '/project/public';
$project = $project ?? [];
$projectNodes = is_array($projectNodes ?? null) ? $projectNodes : [];
$progressPhases = is_array($progressPhases ?? null) ? $progressPhases : [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$currentUser = is_array($currentUser ?? null) ? $currentUser : [];
$canManage = !empty($canManage);
$canUpdateProgress = !empty($canUpdateProgress);
$progressHistory = is_array($progressHistory ?? null) ? $progressHistory : [];
$progressIndicators = is_array($progressIndicators ?? null) ? $progressIndicators : [];

$methodology = strtolower((string) ($project['methodology'] ?? 'cascada'));
if ($methodology === 'convencional' || $methodology === '') {
    $methodology = 'cascada';
}

$badge = $methodology === 'scrum'
    ? ['label' => 'Scrum', 'color' => '#0ea5e9', 'bg' => '#e0f2fe']
    : ['label' => 'Tradicional', 'color' => '#6366f1', 'bg' => '#e0e7ff'];

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
$projectMethodLabel = $badge['label'];
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
?>

<section class="project-shell">
    <header class="project-header">
        <div class="project-title-block">
            <p class="eyebrow">Expediente vivo del proyecto</p>
            <h2><?= htmlspecialchars($project['name'] ?? '') ?></h2>
            <div class="project-meta-grid">
                <div class="meta-card">
                    <span>Cliente</span>
                    <strong><?= htmlspecialchars($projectClient) ?></strong>
                </div>
                <div class="meta-card">
                    <span>Metodología</span>
                    <strong><?= htmlspecialchars($projectMethodLabel) ?></strong>
                </div>
                <div class="meta-card">
                    <span>Estado general</span>
                    <strong><?= htmlspecialchars((string) $projectStatusLabel) ?></strong>
                </div>
            </div>
        </div>
        <div class="project-progress-card">
            <span class="pill" style="background: <?= $badge['bg'] ?>; color: <?= $badge['color'] ?>;"><?= htmlspecialchars($badge['label']) ?></span>
            <div class="project-progress">
                <span class="project-progress__label">Avance global manual</span>
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
            <?php if ($canUpdateProgress): ?>
                <button class="action-btn primary" type="button" data-open-modal="progress-modal">Actualizar avance</button>
            <?php endif; ?>
        </div>
    </header>

    <?php
    $activeTab = 'documents';
    require __DIR__ . '/_tabs.php';
    ?>

    <section class="progress-context">
        <div class="context-card">
            <div>
                <p class="eyebrow">Indicadores de contexto</p>
                <h4>Apoyo a la decisión del avance</h4>
                <small class="section-muted">Informativos, no calculan el porcentaje de avance.</small>
            </div>
            <div class="context-grid">
                <div class="context-item">
                    <span>Documentos aprobados</span>
                    <strong><?= $approvedDocuments ?></strong>
                </div>
                <div class="context-item">
                    <span>Controles pendientes</span>
                    <strong><?= $pendingControls ?></strong>
                </div>
                <div class="context-item">
                    <span>Horas registradas</span>
                    <strong><?= $loggedHours !== null ? number_format((float) $loggedHours, 1) : 'N/A' ?></strong>
                    <small><?= $loggedHours !== null ? 'Timesheets vinculados' : 'Sin timesheets' ?></small>
                </div>
            </div>
        </div>
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
                        $phaseLink = $basePath . '/projects/' . (int) ($project['id'] ?? 0) . '?node=' . (int) ($phase['id'] ?? 0);
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
                                            $sprintLink = $basePath . '/projects/' . (int) ($project['id'] ?? 0) . '?node=' . (int) ($sprint['id'] ?? 0);
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
                            <a href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>">Proyecto</a>
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
                            <?php $tabLink = $basePath . '/projects/' . (int) ($project['id'] ?? 0) . '?node=' . (int) ($subphaseNodes[$suffix]['id'] ?? 0); ?>
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
</section>

<?php if ($canUpdateProgress): ?>
    <div class="modal" id="progress-modal" aria-hidden="true">
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
    const openProgressButtons = document.querySelectorAll('[data-open-modal="progress-modal"]');
    const closeProgressButtons = progressModal ? progressModal.querySelectorAll('[data-close-modal]') : [];

    const toggleProgressModal = (open) => {
        if (!progressModal) return;
        progressModal.classList.toggle('is-visible', open);
        progressModal.setAttribute('aria-hidden', open ? 'false' : 'true');
    };

    openProgressButtons.forEach((button) => {
        button.addEventListener('click', () => toggleProgressModal(true));
    });

    closeProgressButtons.forEach((button) => {
        button.addEventListener('click', () => toggleProgressModal(false));
    });

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
    .project-title-block { display:flex; flex-direction:column; gap:8px; }
    .project-title-block h2 { margin:0; color: var(--text-strong); }
    .project-meta-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px; }
    .meta-card { background:#f8fafc; border:1px solid var(--border); border-radius:12px; padding:10px; display:flex; flex-direction:column; gap:4px; }
    .meta-card span { font-size:12px; text-transform:uppercase; color: var(--muted); font-weight:700; }
    .meta-card strong { font-size:15px; color: var(--text-strong); }
    .project-progress-card { display:flex; flex-direction:column; gap:10px; align-items:flex-start; }
    .action-btn { background: var(--surface); color: var(--text-strong); border:1px solid var(--border); border-radius:8px; padding:8px 10px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
    .action-btn.primary { background: var(--primary); color:#fff; border-color: var(--primary); }
    .action-btn.danger { background:#fee2e2; color:#991b1b; border-color:#fecdd3; }
    .action-btn.small { padding:6px 8px; font-size:13px; }
    .pill { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-weight:700; }
    .folder-meta { display:flex; gap:8px; align-items:center; margin-top:6px; flex-wrap:wrap; }
    .status-badge { font-size:12px; font-weight:700; padding:4px 8px; border-radius:999px; border:1px solid transparent; }
    .status-muted { background:#f3f4f6; color:#374151; border-color:#e5e7eb; }
    .status-info { background:#e0f2fe; color:#075985; border-color:#bae6fd; }
    .status-success { background:#dcfce7; color:#166534; border-color:#bbf7d0; }
    .count-pill { font-size:12px; font-weight:700; color: var(--text-strong); background:#f8fafc; border:1px solid var(--border); border-radius:999px; padding:4px 8px; }
    .breadcrumb { display:flex; flex-wrap:wrap; gap:6px; align-items:center; font-size:13px; color: var(--muted); margin-bottom:8px; }
    .breadcrumb a { color: var(--text-strong); text-decoration:none; font-weight:600; }
    .breadcrumb span { color: var(--muted); }
    .project-progress { display:flex; flex-direction:column; gap:4px; max-width:320px; }
    .project-progress__label { font-size:12px; text-transform:uppercase; color: var(--muted); font-weight:700; }
    .project-progress__bar { background:#e5e7eb; border-radius:999px; overflow:hidden; height:8px; }
    .project-progress__bar div { height:100%; background: var(--primary); border-radius:999px; }
    .project-progress__value { font-size:12px; color: var(--muted); }
    .progress-meta { display:grid; gap:8px; width:100%; }
    .progress-meta__item { display:flex; flex-direction:column; gap:2px; font-size:12px; color: var(--muted); }
    .progress-meta__item.full { grid-column: 1 / -1; }
    .progress-meta__item strong { font-size:13px; color: var(--text-strong); }
    .progress-meta__item.full p { margin:0; font-size:13px; color: var(--text-strong); }
    .progress-context { display:flex; }
    .context-card { border:1px solid var(--border); border-radius:16px; padding:16px; background:#fff; display:flex; flex-direction:column; gap:12px; }
    .context-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; }
    .context-item { border:1px solid var(--border); border-radius:12px; padding:10px; background:#f8fafc; display:flex; flex-direction:column; gap:4px; }
    .context-item span { font-size:12px; text-transform:uppercase; color: var(--muted); font-weight:700; }
    .context-item strong { font-size:16px; color: var(--text-strong); }
    .context-item small { font-size:12px; color: var(--muted); }
    .progress-history { display:flex; }
    .history-card { border:1px solid var(--border); border-radius:16px; padding:16px; background:#fff; display:flex; flex-direction:column; gap:12px; width:100%; }
    .history-header { display:flex; justify-content:space-between; align-items:center; gap:12px; }
    .project-layout { display:grid; grid-template-columns: 280px 1fr; gap:16px; }
    .phase-sidebar { border:1px solid var(--border); border-radius:16px; padding:14px; background:#f8fafc; display:flex; flex-direction:column; gap:12px; max-height:70vh; overflow:auto; }
    .phase-sidebar__header { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; }
    .phase-list, .phase-sublist { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:10px; }
    .phase-item { display:flex; flex-direction:column; gap:6px; }
    .phase-link { display:flex; justify-content:space-between; gap:12px; align-items:center; padding:10px; border-radius:12px; text-decoration:none; color: var(--text-strong); border:1px solid transparent; background:#fff; }
    .phase-link:hover { border-color: var(--border); }
    .phase-link.active { background:#0f172a; color:#fff; border-color:#0f172a; }
    .phase-link.active .section-muted { color: rgba(255,255,255,0.75); }
    .phase-link__title { display:flex; gap:10px; align-items:center; }
    .phase-icon { font-size:18px; }
    .phase-progress-bar { height:6px; background:#e5e7eb; border-radius:999px; overflow:hidden; }
    .phase-progress-bar div { height:100%; background: var(--primary); }
    .phase-group { border:1px solid var(--border); border-radius:12px; padding:8px; background:#fff; }
    .phase-group summary { list-style:none; cursor:pointer; }
    .phase-group summary::-webkit-details-marker { display:none; }
    .phase-sublist { margin-top:8px; padding-left:0; }
    .phase-panel { border:1px solid var(--border); border-radius:16px; padding:16px; background:#fff; min-height:70vh; display:flex; flex-direction:column; gap:16px; }
    .phase-panel__header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; border-bottom:1px solid var(--border); padding-bottom:12px; }
    .phase-panel__header h3 { margin:0; }
    .phase-meta { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .phase-actions { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
    .phase-tabs { display:flex; flex-wrap:wrap; gap:8px; border-bottom:1px solid var(--border); padding-bottom:8px; }
    .phase-tab { padding:8px 12px; border-radius:999px; border:1px solid var(--border); text-decoration:none; color: var(--text-strong); font-weight:700; font-size:13px; background:#f8fafc; }
    .phase-tab.active { background: var(--primary); color:#fff; border-color: var(--primary); }
    .phase-tab.disabled { opacity:0.5; cursor:not-allowed; }
    .phase-tab-panel { display:flex; flex-direction:column; gap:12px; }
    .phase-tab-panel__header { border:1px solid var(--border); border-radius:12px; padding:12px; background:#f8fafc; }
    .phase-warning { color:#7f1d1d; margin:0; }
    .modal { position:fixed; inset:0; display:none; align-items:center; justify-content:center; z-index:50; }
    .modal.is-visible { display:flex; }
    .modal__backdrop { position:absolute; inset:0; background:rgba(15, 23, 42, 0.45); }
    .modal__panel { position:relative; background:#fff; border-radius:16px; padding:16px; width:min(520px, 90vw); box-shadow:0 20px 40px rgba(15, 23, 42, 0.25); display:flex; flex-direction:column; gap:12px; z-index:1; }
    .modal__header { display:flex; justify-content:space-between; align-items:center; }
    .modal__header h3 { margin:0; }
    .modal__body { display:flex; flex-direction:column; gap:12px; }
    .modal__field { display:flex; flex-direction:column; gap:6px; font-weight:600; color: var(--text-strong); }
    .modal__field input,
    .modal__field textarea { padding:10px 12px; border-radius:10px; border:1px solid var(--border); }
    .modal__actions { display:flex; justify-content:flex-end; gap:8px; }
    .icon-btn { border:none; background:transparent; cursor:pointer; font-size:16px; }
    @media (max-width: 960px) {
        .project-layout { grid-template-columns: 1fr; }
        .phase-sidebar { max-height:none; }
    }
</style>
