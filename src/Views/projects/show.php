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
$isInicioPhase = is_array($selectedNode) && ($selectedNode['code'] ?? '') === '01-INICIO';

$assignmentOptions = [];
foreach ($assignments as $assignment) {
    $assignmentOptions[] = [
        'id' => (int) ($assignment['talent_id'] ?? 0),
        'name' => (string) ($assignment['talent_name'] ?? $assignment['name'] ?? 'Usuario'),
        'role' => (string) ($assignment['role'] ?? ''),
    ];
}

$expectedInicioDocs = [
    'Propuesta comercial',
    'Cotización',
    'Alcance técnico inicial',
    'Requerimientos base',
];

$inicioTagOptions = [
    'Propuesta comercial',
    'Cotización',
    'Alcance técnico',
    'Requerimientos',
    'Documento libre',
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

                <?php if ($isInicioPhase): ?>
                    <section class="inicio-phase">
                        <header class="inicio-header">
                            <div>
                                <p class="eyebrow" style="margin:0; color: var(--muted);">FASE 01 · INICIO</p>
                                <h4 style="margin:4px 0 0;">Gestión documental de inicio</h4>
                                <small style="color: var(--muted);">Carga libre, tagueo manual y flujo de revisión por documento.</small>
                            </div>
                        </header>

                        <div class="inicio-grid">
                            <section class="inicio-section">
                                <h5>Documentos esperados en esta fase</h5>
                                <p class="section-muted">Referencia informativa (sin carga ni bloqueo).</p>
                                <ul class="expected-list">
                                    <?php foreach ($expectedInicioDocs as $doc): ?>
                                        <li><?= htmlspecialchars($doc) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>

                            <section class="inicio-section">
                                <div class="section-header">
                                    <div>
                                        <h5>Carga libre de documentos</h5>
                                        <p class="section-muted">PDF, Word, Excel o imágenes. Se almacenan en esta fase.</p>
                                    </div>
                                    <?php if ($canManage): ?>
                                        <form class="upload-form" method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/nodes/<?= (int) ($selectedNode['id'] ?? 0) ?>/files" enctype="multipart/form-data">
                                            <input type="file" name="node_files[]" multiple required accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.bmp,.tiff">
                                            <button type="submit" class="action-btn primary">Subir archivos</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <div class="upload-preview" data-upload-preview hidden>
                                    <strong>Archivos listos para cargar:</strong>
                                    <ul></ul>
                                </div>
                            </section>
                        </div>

                        <section class="inicio-files">
                            <div class="section-header">
                                <div>
                                    <h5>Listado de archivos</h5>
                                    <p class="section-muted">Administra tags, versión y flujo de revisión.</p>
                                </div>
                                <div class="inicio-alert" data-inicio-alert hidden>
                                    <strong>⚠️ Documentos clave pendientes</strong>
                                    <span data-inicio-alert-detail></span>
                                </div>
                            </div>

                            <?php if (!empty($selectedNode['files'])): ?>
                                <div class="inicio-file-table">
                                    <div class="inicio-file-row inicio-file-head">
                                        <span>Archivo</span>
                                        <span>Tags</span>
                                        <span>Versión</span>
                                        <span>Estado</span>
                                        <span>Acciones</span>
                                    </div>
                                    <?php foreach ($selectedNode['files'] as $file): ?>
                                        <div class="inicio-file-row" data-file-row data-file-id="<?= (int) ($file['id'] ?? 0) ?>">
                                            <div>
                                                <strong><?= htmlspecialchars($file['file_name'] ?? $file['title'] ?? '') ?></strong>
                                                <small class="section-muted">Subido: <?= htmlspecialchars((string) ($file['created_at'] ?? '')) ?></small>
                                                <div class="file-trace" data-file-trace>Sin trazabilidad registrada.</div>
                                            </div>
                                            <div class="tag-editor" data-tag-editor>
                                                <div class="tag-pills" data-tag-pills>
                                                    <span class="tag-pill">Documento libre</span>
                                                </div>
                                                <div class="tag-controls">
                                                    <select multiple data-tag-select>
                                                        <?php foreach ($inicioTagOptions as $tag): ?>
                                                            <option value="<?= htmlspecialchars($tag) ?>"><?= htmlspecialchars($tag) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input type="text" placeholder="Otro (texto libre)" data-tag-custom>
                                                </div>
                                            </div>
                                            <div>
                                                <input type="text" class="version-input" placeholder="v1, v2, final" data-version-input>
                                            </div>
                                            <div>
                                                <span class="status-pill status-pending" data-status-label>Pendiente</span>
                                                <button type="button" class="action-btn small" data-send-review>Enviar a revisión</button>
                                                <div class="review-actions" data-review-actions hidden>
                                                    <button type="button" class="action-btn small" data-action="reviewed">Marcar como Revisado</button>
                                                    <button type="button" class="action-btn small" data-action="validated">Marcar como Validado</button>
                                                    <button type="button" class="action-btn small primary" data-action="approved">Marcar como Aprobado</button>
                                                </div>
                                            </div>
                                            <div class="file-actions">
                                                <a class="action-btn small" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/nodes/<?= (int) ($file['id'] ?? 0) ?>/download">Ver</a>
                                                <?php if ($canManage): ?>
                                                    <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/nodes/<?= (int) ($file['id'] ?? 0) ?>/delete" onsubmit="return confirm('¿Eliminar archivo?');">
                                                        <button class="action-btn danger small" type="submit">Eliminar</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($canManage): ?>
                                                    <button type="button" class="action-btn small" data-toggle-flow>Asignar flujo</button>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flow-panel" data-flow-panel hidden>
                                                <div class="flow-grid">
                                                    <label>
                                                        <span>Revisor</span>
                                                        <select data-role-select="reviewer">
                                                            <option value="">Seleccionar</option>
                                                            <?php foreach ($assignmentOptions as $option): ?>
                                                                <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['name']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label>
                                                        <span>Validador</span>
                                                        <select data-role-select="validator">
                                                            <option value="">Seleccionar</option>
                                                            <?php foreach ($assignmentOptions as $option): ?>
                                                                <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['name']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label>
                                                        <span>Aprobador</span>
                                                        <select data-role-select="approver">
                                                            <option value="">Seleccionar</option>
                                                            <?php foreach ($assignmentOptions as $option): ?>
                                                                <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['name']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                </div>
                                                <small class="section-muted">Asigna responsables para habilitar las acciones por rol.</small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="section-muted">Aún no hay archivos cargados en la fase de inicio.</p>
                            <?php endif; ?>
                        </section>
                    </section>
                <?php else: ?>
                    <section>
                        <header style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <h4 style="margin:0;">Archivos y controles</h4>
                            <?php if ($canManage): ?>
                                <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/nodes/<?= (int) ($selectedNode['id'] ?? 0) ?>/files" enctype="multipart/form-data" style="display:flex; gap:8px; align-items:center;">
                                    <input type="file" name="node_files[]" multiple required>
                                    <button type="submit" class="action-btn primary">Subir archivos</button>
                                </form>
                            <?php endif; ?>
                        </header>
                        <?php if (!empty($selectedNode['files'])): ?>
                            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px;">
                                <?php foreach ($selectedNode['files'] as $file): ?>
                                    <div style="border:1px solid var(--border); border-radius:10px; padding:10px; background:#f8fafc; display:flex; flex-direction:column; gap:6px;">
                                        <strong><?= htmlspecialchars($file['file_name'] ?? $file['title'] ?? '') ?></strong>
                                        <small style="color: var(--muted);">ISO: <?= htmlspecialchars((string) ($file['iso_clause'] ?? 'N/A')) ?></small>
                                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                            <a class="action-btn small" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/nodes/<?= (int) ($file['id'] ?? 0) ?>/download">Descargar</a>
                                            <?php if ($canManage): ?>
                                                <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/nodes/<?= (int) ($file['id'] ?? 0) ?>/delete" onsubmit="return confirm('¿Eliminar archivo?');">
                                                    <button class="action-btn danger small" type="submit">Eliminar</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="color: var(--muted);">Sin archivos en esta carpeta.</p>
                        <?php endif; ?>
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
    .inicio-phase { display:flex; flex-direction:column; gap:16px; }
    .inicio-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .inicio-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:12px; }
    .inicio-section { border:1px solid var(--border); border-radius:12px; padding:12px; background:#f8fafc; display:flex; flex-direction:column; gap:8px; }
    .inicio-section h5 { margin:0; }
    .section-muted { color: var(--muted); margin:0; font-size:13px; }
    .expected-list { margin:0; padding-left:18px; color: var(--text-strong); }
    .section-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .upload-form { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .upload-preview { margin-top:6px; background:#fff; border:1px dashed var(--border); padding:8px; border-radius:10px; font-size:13px; }
    .inicio-files { display:flex; flex-direction:column; gap:12px; }
    .inicio-file-table { display:grid; gap:8px; }
    .inicio-file-row { display:grid; grid-template-columns: minmax(180px, 1.4fr) minmax(160px, 1.2fr) minmax(120px, 0.6fr) minmax(160px, 0.8fr) minmax(140px, 0.8fr); gap:10px; padding:10px; border:1px solid var(--border); border-radius:12px; background:#fff; align-items:start; }
    .inicio-file-head { background:#f1f5f9; font-weight:700; }
    .inicio-file-head span { font-size:12px; text-transform:uppercase; color: var(--muted); }
    .tag-editor { display:flex; flex-direction:column; gap:6px; }
    .tag-pills { display:flex; flex-wrap:wrap; gap:6px; }
    .tag-pill { background:#e0f2fe; color:#075985; padding:2px 8px; border-radius:999px; font-size:12px; font-weight:700; }
    .tag-controls { display:flex; flex-direction:column; gap:6px; }
    .tag-controls select, .tag-controls input, .version-input { width:100%; border:1px solid var(--border); border-radius:8px; padding:6px 8px; }
    .version-input { font-size:13px; }
    .status-pill { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; margin-bottom:6px; }
    .status-pending { background:#fef9c3; color:#854d0e; }
    .status-review { background:#e0f2fe; color:#075985; }
    .status-validated { background:#dcfce7; color:#166534; }
    .status-approved { background:#ede9fe; color:#5b21b6; }
    .file-actions { display:flex; flex-wrap:wrap; gap:6px; }
    .flow-panel { margin-top:6px; background:#f8fafc; border:1px solid var(--border); border-radius:10px; padding:8px; grid-column: 1 / -1; }
    .flow-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:8px; margin-bottom:6px; }
    .flow-grid label { display:flex; flex-direction:column; gap:4px; font-size:13px; color: var(--text-strong); }
    .flow-grid select { border:1px solid var(--border); border-radius:8px; padding:6px 8px; }
    .review-actions { display:flex; flex-direction:column; gap:6px; margin-top:6px; }
    .inicio-alert { background:#fef3c7; color:#92400e; padding:8px 10px; border-radius:10px; font-size:13px; display:flex; flex-direction:column; gap:4px; min-width:200px; }
    .file-trace { margin-top:6px; font-size:12px; color: var(--muted); }
    @media (max-width: 900px) {
        .inicio-file-row { grid-template-columns: 1fr; }
        .inicio-file-head { display:none; }
    }
</style>

<?php if ($isInicioPhase): ?>
<script>
    (() => {
        const currentUserId = <?= (int) ($currentUser['id'] ?? 0) ?>;
        const currentUserName = <?= json_encode((string) ($currentUser['name'] ?? 'Usuario')) ?>;
        const keyTags = ['Propuesta comercial', 'Cotización', 'Alcance técnico', 'Requerimientos'];

        const statusConfig = {
            pendiente: { label: 'Pendiente', className: 'status-pending' },
            revision: { label: 'En revisión', className: 'status-review' },
            validado: { label: 'Validado', className: 'status-validated' },
            aprobado: { label: 'Aprobado', className: 'status-approved' }
        };

        const updateAlert = () => {
            const alertBox = document.querySelector('[data-inicio-alert]');
            const alertDetail = document.querySelector('[data-inicio-alert-detail]');
            if (!alertBox || !alertDetail) return;

            const summary = {};
            keyTags.forEach(tag => {
                summary[tag] = { approved: false };
            });

            document.querySelectorAll('[data-file-row]').forEach(row => {
                const tags = row.dataset.tags ? row.dataset.tags.split('|').filter(Boolean) : [];
                const status = row.dataset.status || 'pendiente';
                tags.forEach(tag => {
                    if (summary[tag]) {
                        if (status === 'aprobado') {
                            summary[tag].approved = true;
                        }
                    }
                });
            });

            const pending = keyTags.filter(tag => !summary[tag].approved);
            if (pending.length === 0) {
                alertBox.hidden = true;
                return;
            }

            alertBox.hidden = false;
            alertDetail.textContent = `Pendientes de aprobación: ${pending.join(', ')}.`;
        };

        const updateTagsDisplay = (row, tags) => {
            const pills = row.querySelector('[data-tag-pills]');
            if (!pills) return;
            pills.innerHTML = '';
            const finalTags = tags.length ? tags : ['Documento libre'];
            finalTags.forEach(tag => {
                const pill = document.createElement('span');
                pill.className = 'tag-pill';
                pill.textContent = tag;
                pills.appendChild(pill);
            });
            row.dataset.tags = finalTags.join('|');
            updateAlert();
        };

        const updateStatus = (row, statusKey, traceNote) => {
            const config = statusConfig[statusKey] || statusConfig.pendiente;
            const label = row.querySelector('[data-status-label]');
            if (label) {
                label.textContent = config.label;
                label.className = `status-pill ${config.className}`;
            }
            row.dataset.status = statusKey;
            const trace = row.querySelector('[data-file-trace]');
            if (trace && traceNote) {
                const now = new Date();
                trace.textContent = `${traceNote} · ${currentUserName} · ${now.toLocaleString()}`;
            }
            updateAlert();
        };

        const updateRoleActions = (row) => {
            const actions = row.querySelector('[data-review-actions]');
            if (!actions) return;
            const reviewer = row.dataset.reviewerId;
            const validator = row.dataset.validatorId;
            const approver = row.dataset.approverId;
            const shouldShow = [reviewer, validator, approver].some(id => Number(id) === currentUserId);
            actions.hidden = !shouldShow;
        };

        document.querySelectorAll('[data-file-row]').forEach(row => {
            updateTagsDisplay(row, []);
            updateStatus(row, 'pendiente');
        });

        document.addEventListener('change', (event) => {
            const select = event.target.closest('[data-tag-select]');
            if (select) {
                const row = select.closest('[data-file-row]');
                const customInput = row.querySelector('[data-tag-custom]');
                const tags = Array.from(select.selectedOptions).map(option => option.value);
                if (customInput && customInput.value.trim()) {
                    tags.push(customInput.value.trim());
                }
                updateTagsDisplay(row, tags);
            }

            const customInput = event.target.closest('[data-tag-custom]');
            if (customInput) {
                const row = customInput.closest('[data-file-row]');
                const select = row.querySelector('[data-tag-select]');
                const tags = Array.from(select.selectedOptions).map(option => option.value);
                if (customInput.value.trim()) {
                    tags.push(customInput.value.trim());
                }
                updateTagsDisplay(row, tags);
            }

            const roleSelect = event.target.closest('[data-role-select]');
            if (roleSelect) {
                const row = roleSelect.closest('[data-file-row]');
                const role = roleSelect.dataset.roleSelect;
                row.dataset[`${role}Id`] = roleSelect.value;
                updateRoleActions(row);
            }
        });

        document.addEventListener('click', (event) => {
            const toggleFlow = event.target.closest('[data-toggle-flow]');
            if (toggleFlow) {
                const row = toggleFlow.closest('[data-file-row]');
                const panel = row.querySelector('[data-flow-panel]');
                if (panel) {
                    panel.hidden = !panel.hidden;
                }
            }

            const sendReview = event.target.closest('[data-send-review]');
            if (sendReview) {
                const row = sendReview.closest('[data-file-row]');
                updateStatus(row, 'revision', 'Enviado a revisión');
            }

            const actionBtn = event.target.closest('[data-action]');
            if (actionBtn) {
                const row = actionBtn.closest('[data-file-row]');
                const action = actionBtn.dataset.action;
                if (action === 'reviewed' && row.dataset.reviewerId && Number(row.dataset.reviewerId) === currentUserId) {
                    updateStatus(row, 'revision', 'Revisado');
                }
                if (action === 'validated' && row.dataset.validatorId && Number(row.dataset.validatorId) === currentUserId) {
                    updateStatus(row, 'validado', 'Validado');
                }
                if (action === 'approved' && row.dataset.approverId && Number(row.dataset.approverId) === currentUserId) {
                    updateStatus(row, 'aprobado', 'Aprobado');
                }
            }
        });

        const uploadInput = document.querySelector('.upload-form input[type="file"]');
        const preview = document.querySelector('[data-upload-preview]');
        if (uploadInput && preview) {
            uploadInput.addEventListener('change', () => {
                const list = preview.querySelector('ul');
                list.innerHTML = '';
                Array.from(uploadInput.files || []).forEach(file => {
                    const item = document.createElement('li');
                    item.textContent = `${file.name} (${Math.round(file.size / 1024)} KB)`;
                    list.appendChild(item);
                });
                preview.hidden = list.children.length === 0;
            });
        }

        updateAlert();
    })();
</script>
<?php endif; ?>
