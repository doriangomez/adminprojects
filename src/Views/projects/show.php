<?php
$basePath = $basePath ?? '/project/public';
$project = $project ?? [];
$projectNodes = is_array($projectNodes ?? null) ? $projectNodes : [];
$progressPhases = is_array($progressPhases ?? null) ? $progressPhases : [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
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
            $statusBg = '#e5e7eb';
            $statusColor = '#111827';
            if ($status === 'completado') { $statusBg = '#dcfce7'; $statusColor = '#166534'; }
            elseif ($status === 'en_progreso') { $statusBg = '#e0f2fe'; $statusColor = '#075985'; }
            $statusIcon = '⚪';
            if ($status === 'completado') { $statusIcon = '✅'; }
            elseif ($status === 'en_progreso') { $statusIcon = '🟢'; }
            ?>
            <div style="min-width:180px; border:1px solid var(--border); border-radius:12px; padding:10px; background:#f8fafc;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <strong><?= $statusIcon ?> <?= htmlspecialchars($phase['title'] ?? $phase['code'] ?? '') ?></strong>
                    <span class="pill" style="background: <?= $statusBg ?>; color: <?= $statusColor ?>;"><?= htmlspecialchars($status) ?></span>
                </div>
                <div style="margin-top:6px; height:6px; background:#e5e7eb; border-radius:999px;">
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
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; border-bottom:1px solid var(--border); padding-bottom:8px;">
                    <div>
                        <p class="eyebrow" style="margin:0; color: var(--muted);">Carpeta</p>
                        <h3 style="margin:0;"><?= htmlspecialchars($selectedNode['name'] ?? $selectedNode['title'] ?? '') ?></h3>
                        <small style="color: var(--muted);">Código: <?= htmlspecialchars($selectedNode['code'] ?? '') ?> <?= ($selectedNode['iso_code'] ?? null) ? '· ISO ' . htmlspecialchars((string) $selectedNode['iso_code']) : '' ?></small>
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
                            <div style="border:1px solid var(--border); border-radius:10px; padding:10px; width:220px; background:#f8fafc;">
                                <strong><?= htmlspecialchars($child['name'] ?? $child['title'] ?? '') ?></strong>
                                <p style="margin:4px 0; color: var(--muted); font-size:13px;"><?= htmlspecialchars($child['description'] ?? 'Subcarpeta') ?></p>
                                <a class="action-btn small" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>?node=<?= (int) ($child['id'] ?? 0) ?>">Abrir</a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: var(--muted);">No hay subcarpetas.</p>
                    <?php endif; ?>
                </section>

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
</style>
