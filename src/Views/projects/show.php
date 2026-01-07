<?php
$basePath = $basePath ?? '/project/public';
$project = $project ?? [];
$projectNodes = is_array($projectNodes ?? null) ? $projectNodes : [];
$progressPhases = is_array($progressPhases ?? null) ? $progressPhases : [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$currentUser = is_array($currentUser ?? null) ? $currentUser : [];
$canManage = !empty($canManage);

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

$firstFolder = null;
foreach ($allNodes as $candidate) {
    if (($candidate['code'] ?? '') !== 'ROOT' && ($candidate['files'] ?? null) !== null) {
        $firstFolder = $candidate;
        break;
    }
}

$selectedNodeId = isset($_GET['node']) ? (int) $_GET['node'] : null;
if ($selectedNodeId) {
    foreach ($allNodes as $candidate) {
        if ((int) ($candidate['id'] ?? 0) === $selectedNodeId) {
            $firstFolder = $candidate;
            break;
        }
    }
}

$selectedNode = $firstFolder;
$rootNodeId = null;
foreach ($allNodes as $node) {
    if (($node['code'] ?? '') === 'ROOT') {
        $rootNodeId = (int) ($node['id'] ?? 0);
        break;
    }
}
$phaseNode = null;
if (is_array($selectedNode)) {
    $cursor = $selectedNode;
    while (is_array($cursor)) {
        $parentId = $cursor['parent_id'] ?? null;
        if ($parentId === $rootNodeId) {
            $phaseNode = $cursor;
            break;
        }
        if ($parentId === null) {
            break;
        }
        $cursor = $nodesById[(int) $parentId] ?? null;
    }
}
$phaseCodeForFlow = (string) ($phaseNode['code'] ?? '');
$standardSubphaseSuffixes = ['01-ENTRADAS', '02-PLANIFICACION', '03-CONTROLES', '04-EVIDENCIAS', '05-CAMBIOS'];
$selectedSuffix = null;
if (is_array($selectedNode)) {
    $parts = explode('-', (string) ($selectedNode['code'] ?? ''));
    if (count($parts) >= 2) {
        $selectedSuffix = implode('-', array_slice($parts, -2));
    }
}
$isSubphase = $selectedSuffix !== null && in_array($selectedSuffix, $standardSubphaseSuffixes, true);

$assignmentOptions = [];
foreach ($assignments as $assignment) {
    $assignmentOptions[] = [
        'id' => (int) ($assignment['talent_id'] ?? 0),
        'name' => (string) ($assignment['talent_name'] ?? $assignment['name'] ?? 'Usuario'),
        'role' => (string) ($assignment['role'] ?? ''),
    ];
}

$documentFlowConfig = is_array($documentFlowConfig ?? null) ? $documentFlowConfig : [];
$accessRoles = is_array($accessRoles ?? null) ? $accessRoles : [];
$documentFlowDefaults = is_array($documentFlowConfig['default'] ?? null) ? $documentFlowConfig['default'] : [];
$documentFlowPhaseOverrides = is_array($documentFlowConfig['phases'] ?? null) ? $documentFlowConfig['phases'] : [];
$documentFlowExpectedDocs = is_array($documentFlowConfig['expected_docs'] ?? null) ? $documentFlowConfig['expected_docs'] : [
    'Propuesta comercial',
    'Cotización',
    'Alcance técnico inicial',
    'Requerimientos base',
];
$documentFlowTagOptions = is_array($documentFlowConfig['tag_options'] ?? null) ? $documentFlowConfig['tag_options'] : [
    'Propuesta comercial',
    'Cotización',
    'Alcance técnico',
    'Requerimientos',
    'Documento libre',
];

$phaseFlowConfig = is_array($documentFlowPhaseOverrides[$phaseCodeForFlow] ?? null) ? $documentFlowPhaseOverrides[$phaseCodeForFlow] : [];
$defaultRoles = !empty($accessRoles) ? $accessRoles : ['Administrador', 'PMO', 'Talento'];
$documentFlowRoles = [
    'reviewer' => array_values((array) ($phaseFlowConfig['reviewer_roles'] ?? $documentFlowDefaults['reviewer_roles'] ?? $defaultRoles)),
    'validator' => array_values((array) ($phaseFlowConfig['validator_roles'] ?? $documentFlowDefaults['validator_roles'] ?? $defaultRoles)),
    'approver' => array_values((array) ($phaseFlowConfig['approver_roles'] ?? $documentFlowDefaults['approver_roles'] ?? $defaultRoles)),
];

$documentRoleOptions = [
    'reviewer' => array_values(array_filter($assignmentOptions, static fn (array $option): bool => in_array($option['role'], $documentFlowRoles['reviewer'], true))),
    'validator' => array_values(array_filter($assignmentOptions, static fn (array $option): bool => in_array($option['role'], $documentFlowRoles['validator'], true))),
    'approver' => array_values(array_filter($assignmentOptions, static fn (array $option): bool => in_array($option['role'], $documentFlowRoles['approver'], true))),
];

$renderTree = static function (array $nodes, int $projectId, ?int $activeId) use (&$renderTree, $basePath): void {
    echo '<ul class="tree-list">';
    foreach ($nodes as $node) {
        $isActive = $activeId && (int) ($node['id'] ?? 0) === $activeId;
        $hasChildren = !empty($node['children']);
        $link = $basePath . '/projects/' . $projectId . '?node=' . (int) ($node['id'] ?? 0);
        echo '<li>';
        echo '<a href="' . htmlspecialchars($link) . '" class="tree-link' . ($isActive ? ' active' : '') . '">';
        echo '<span class="tree-toggle">' . ($hasChildren ? '▾' : '•') . '</span>';
        echo '<span>' . htmlspecialchars($node['name'] ?? $node['title'] ?? $node['code'] ?? '') . '</span>';
        echo '</a>';
        if ($hasChildren) {
            $renderTree($node['children'], $projectId, $activeId);
        }
        echo '</li>';
    }
    echo '</ul>';
};

$phaseCards = $progressPhases;
if (empty($phaseCards)) {
    $phaseCards = [
        ['code' => '01-INICIO', 'title' => 'Inicio', 'progress' => 0, 'status' => 'pendiente'],
        ['code' => '02-PLANIFICACION', 'title' => 'Planificación', 'progress' => 0, 'status' => 'pendiente'],
        ['code' => '03-DISEÑO', 'title' => 'Diseño', 'progress' => 0, 'status' => 'pendiente'],
        ['code' => '04-EJECUCION', 'title' => 'Ejecución', 'progress' => 0, 'status' => 'pendiente'],
        ['code' => '05-SEGUIMIENTO_Y_CONTROL', 'title' => 'Seguimiento y Control', 'progress' => 0, 'status' => 'pendiente'],
        ['code' => '06-CIERRE', 'title' => 'Cierre', 'progress' => 0, 'status' => 'pendiente'],
    ];
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

$folderMetrics = static function (array $node): array {
    $files = $node['files'] ?? [];
    $children = $node['children'] ?? [];
    $controls = array_filter($children, static fn (array $child): bool => ($child['node_type'] ?? '') === 'iso_control');
    $controlTotal = count($controls);
    $controlComplete = count(array_filter($controls, static fn (array $child): bool => ($child['status'] ?? '') === 'completado'));
    $fileTotal = count($files);

    $evidenceTotal = $controlTotal > 0 ? $controlTotal : $fileTotal;
    $evidenceComplete = $controlTotal > 0 ? $controlComplete : $fileTotal;

    $status = 'pendiente';
    if ($evidenceTotal > 0) {
        if ($evidenceComplete >= $evidenceTotal) {
            $status = 'completado';
        } elseif ($evidenceComplete > 0) {
            $status = 'en_progreso';
        }
    }

    return [
        'status' => $status,
        'complete' => $evidenceComplete,
        'total' => $evidenceTotal,
        'files' => $fileTotal,
        'controls' => $controlTotal,
    ];
};

$phaseTooltip = 'Cada subcarpeta estándar vale 20%. Cuenta si tiene al menos 1 archivo o control.';
?>

<section class="card" style="padding:16px; border:1px solid var(--border); border-radius:14px; background: var(--surface); display:flex; flex-direction:column; gap:12px;">
    <header style="display:flex; justify-content:space-between; gap:12px; align-items:center;">
        <div style="display:flex; flex-direction:column; gap:4px;">
            <p class="eyebrow" style="margin:0; color: var(--muted); text-transform: uppercase; font-weight:800;">Proyecto</p>
            <h2 style="margin:0; color: var(--text-strong);"><?= htmlspecialchars($project['name'] ?? '') ?></h2>
            <small style="color: var(--muted);">Cliente: <?= htmlspecialchars($project['client_name'] ?? '') ?></small>
        </div>
        <span class="pill" style="background: <?= $badge['bg'] ?>; color: <?= $badge['color'] ?>; font-weight:700;"><?= htmlspecialchars($badge['label']) ?></span>
    </header>

    <div class="timeline" style="display:flex; gap:8px; overflow-x:auto; padding:8px 0;">
        <?php foreach ($phaseCards as $phase): ?>
            <?php
            $status = $phase['status'] ?? 'pendiente';
            $progress = (float) ($phase['progress'] ?? 0);
            $completed = (int) ($phase['completed'] ?? 0);
            $total = (int) ($phase['total'] ?? 0);
            $statusLabel = $statusLabels[$status] ?? $status;
            $statusBg = '#e5e7eb';
            $statusColor = '#111827';
            if ($status === 'completado') { $statusBg = '#dcfce7'; $statusColor = '#166534'; }
            elseif ($status === 'en_progreso') { $statusBg = '#e0f2fe'; $statusColor = '#075985'; }
            $statusIcon = '⚪';
            if ($status === 'completado') { $statusIcon = '✅'; }
            elseif ($status === 'en_progreso') { $statusIcon = '🟢'; }
            ?>
            <div style="min-width:200px; border:1px solid var(--border); border-radius:12px; padding:10px; background:#f8fafc;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <strong><?= $statusIcon ?> <?= htmlspecialchars($phase['title'] ?? $phase['code'] ?? '') ?></strong>
                    <span class="pill" style="background: <?= $statusBg ?>; color: <?= $statusColor ?>;"><?= htmlspecialchars($statusLabel) ?></span>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:6px;">
                    <small style="color: var(--muted);"><?= $completed ?> de <?= $total ?> subcarpetas</small>
                    <span class="tooltip-pill" title="<?= htmlspecialchars($phaseTooltip) ?>">ⓘ</span>
                </div>
                <div class="phase-progress" role="progressbar" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100" title="<?= htmlspecialchars($phaseTooltip) ?>">
                    <div style="width: <?= $progress ?>%; height:6px; background: var(--primary); border-radius:999px;"></div>
                </div>
                <small style="color: var(--muted);"><?= $progress ?>% completado</small>
            </div>
        <?php endforeach; ?>
    </div>

    <div style="display:grid; grid-template-columns: 280px 1fr; gap:12px;">
        <aside style="border:1px solid var(--border); border-radius:12px; padding:12px; background:#f8fafc; max-height:70vh; overflow:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <strong>Árbol de carpetas</strong>
                <?php if ($canManage && $methodology === 'scrum'): ?>
                    <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/sprints">
                        <button class="action-btn small" type="submit">Nuevo sprint</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php if (empty($tree)): ?>
                <p style="color: var(--muted);">Sin nodos disponibles.</p>
            <?php else: ?>
                <?php $renderTree($tree, (int) ($project['id'] ?? 0), (int) ($selectedNode['id'] ?? 0)); ?>
            <?php endif; ?>
        </aside>

        <main style="border:1px solid var(--border); border-radius:12px; padding:12px; background:#fff; min-height:70vh; display:flex; flex-direction:column; gap:12px;">
            <?php if (!$selectedNode): ?>
                <p style="color: var(--muted);">Selecciona una carpeta para ver su contenido.</p>
            <?php else: ?>
                <?php $selectedMetrics = $folderMetrics($selectedNode); ?>
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; border-bottom:1px solid var(--border); padding-bottom:8px;">
                    <div>
                        <p class="eyebrow" style="margin:0; color: var(--muted);">Carpeta</p>
                        <h3 style="margin:0;"><?= htmlspecialchars($selectedNode['name'] ?? $selectedNode['title'] ?? '') ?></h3>
                        <small style="color: var(--muted);">Código: <?= htmlspecialchars($selectedNode['code'] ?? '') ?> <?= ($selectedNode['iso_code'] ?? null) ? '· ISO ' . htmlspecialchars((string) $selectedNode['iso_code']) : '' ?></small>
                        <div class="folder-meta">
                            <span class="badge status-badge <?= $statusBadgeClass($selectedMetrics['status']) ?>">
                                <?= $statusLabels[$selectedMetrics['status']] ?? $selectedMetrics['status'] ?>
                            </span>
                            <span class="count-pill"><?= $selectedMetrics['complete'] ?> de <?= $selectedMetrics['total'] ?></span>
                        </div>
                    </div>
                    <?php if ($canManage): ?>
                        <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/nodes/<?= (int) ($selectedNode['id'] ?? 0) ?>/delete" onsubmit="return confirm('¿Eliminar este nodo y su contenido?');">
                            <button type="submit" class="action-btn danger">Eliminar carpeta</button>
                        </form>
                    <?php endif; ?>
                </div>

                <section style="display:flex; gap:12px; flex-wrap:wrap;">
                    <?php if (!empty($selectedNode['children'])): ?>
                        <?php foreach ($selectedNode['children'] as $child): ?>
                            <?php
                            $childMetrics = $folderMetrics($child);
                            $childName = $child['name'] ?? $child['title'] ?? '';
                            $isEvidenceFolder = stripos((string) $childName, 'evidencia') !== false || stripos((string) ($child['code'] ?? ''), 'EVIDENCIAS') !== false;
                            $actionLabel = $canManage ? ($isEvidenceFolder ? 'Agregar evidencia' : 'Subir archivo') : 'Abrir';
                            ?>
                            <div style="border:1px solid var(--border); border-radius:10px; padding:10px; width:240px; background:#f8fafc; display:flex; flex-direction:column; gap:6px;">
                                <strong><?= htmlspecialchars($childName) ?></strong>
                                <p style="margin:0; color: var(--muted); font-size:13px;"><?= htmlspecialchars($child['description'] ?? 'Subcarpeta') ?></p>
                                <div class="folder-meta">
                                    <span class="badge status-badge <?= $statusBadgeClass($childMetrics['status']) ?>">
                                        <?= $statusLabels[$childMetrics['status']] ?? $childMetrics['status'] ?>
                                    </span>
                                    <span class="count-pill"><?= $childMetrics['complete'] ?> de <?= $childMetrics['total'] ?></span>
                                </div>
                                <a class="action-btn small primary" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>?node=<?= (int) ($child['id'] ?? 0) ?>">
                                    <?= htmlspecialchars($actionLabel) ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: var(--muted);">No hay subcarpetas.</p>
                    <?php endif; ?>
                </section>

                <?php if ($isSubphase): ?>
                    <?php
                    $documentFlowId = 'document-flow-' . (int) ($selectedNode['id'] ?? 0);
                    $documentNode = $selectedNode;
                    $documentExpectedDocs = $documentFlowExpectedDocs;
                    $documentTagOptions = $documentFlowTagOptions;
                    $documentRoleOptions = $documentRoleOptions;
                    $documentKeyTags = $documentFlowExpectedDocs;
                    $documentCanManage = $canManage;
                    $documentProjectId = (int) ($project['id'] ?? 0);
                    $documentBasePath = $basePath;
                    $documentCurrentUser = $currentUser;
                    require __DIR__ . '/document_flow.php';
                    ?>
                <?php else: ?>
                    <section style="border:1px dashed var(--border); border-radius:12px; padding:12px; background:#f8fafc;">
                        <h4 style="margin:0 0 6px;">Gestión documental por subfase</h4>
                        <p style="color: var(--muted); margin:0;">Esta fase agrupa subfases. Selecciona una subfase para cargar y revisar documentos.</p>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</section>

<style>
    .tree-list { list-style: none; margin: 0; padding-left: 12px; }
    .tree-link { display:flex; gap:6px; align-items:center; padding:6px 8px; border-radius:8px; color: var(--text-strong); text-decoration:none; }
    .tree-link:hover { background: #e5e7eb; }
    .tree-link.active { background: var(--text-strong); color:#fff; }
    .tree-toggle { width:14px; color: var(--muted); }
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
    .phase-progress { margin-top:6px; height:6px; background:#e5e7eb; border-radius:999px; overflow:hidden; }
    .tooltip-pill { font-size:12px; font-weight:700; color: var(--secondary); background:#eef2ff; border-radius:999px; padding:2px 8px; cursor:help; }
</style>
