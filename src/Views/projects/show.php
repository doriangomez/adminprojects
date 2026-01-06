<?php
$basePath = $basePath ?? '/project/public';
$project = $project ?? [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$signal = $project['signal'] ?? ['label' => 'Verde', 'code' => 'green', 'reasons' => []];
$risks = is_array($project['risks'] ?? null) ? $project['risks'] : [];
$riskScore = (float) ($project['risk_score'] ?? 0);
$riskLevel = $project['risk_level'] ?? 'Sin dato';
$designInputs = is_array($designInputs ?? null) ? $designInputs : [];
$designInputTypes = is_array($designInputTypes ?? null) ? $designInputTypes : [];
$designControls = is_array($designControls ?? null) ? $designControls : [];
$designControlTypes = is_array($designControlTypes ?? null) ? $designControlTypes : [];
$designControlResults = is_array($designControlResults ?? null) ? $designControlResults : [];
$designChanges = is_array($designChanges ?? null) ? $designChanges : [];
$designChangeImpactLevels = is_array($designChangeImpactLevels ?? null) ? $designChangeImpactLevels : [];
$designChangeError = $designChangeError ?? null;
$performers = is_array($performers ?? null) ? $performers : [];
$projectNodes = is_array($projectNodes ?? null) ? $projectNodes : [];
$criticalNodes = is_array($criticalNodes ?? null) ? $criticalNodes : [];
$nodeFileError = $nodeFileError ?? null;
$canManage = !empty($canManage);

$designLabels = [
    'requisitos_funcionales' => 'Requisitos funcionales',
    'requisitos_desempeno' => 'Requisitos de desempeño',
    'requisitos_legales' => 'Requisitos legales',
    'normativa' => 'Normativa',
    'referencias_previas' => 'Referencias previas',
    'input_cliente' => 'Input de cliente',
    'otro' => 'Otro',
    'revision' => 'Revisión',
    'verificacion' => 'Verificación',
    'validacion' => 'Validación',
    'aprobado' => 'Aprobado',
    'observaciones' => 'Con observaciones',
    'rechazado' => 'Rechazado',
    'bajo' => 'Bajo',
    'medio' => 'Medio',
    'alto' => 'Alto',
    'pendiente' => 'Pendiente',
    'en_progreso' => 'En progreso',
    'completado' => 'Completado',
    'bloqueado' => 'Bloqueado',
];

$normalizedMethodology = strtolower((string) ($project['methodology'] ?? 'cascada'));
if ($normalizedMethodology === 'convencional' || $normalizedMethodology === '') {
    $normalizedMethodology = 'cascada';
}

$blockDefinitions = [
    ['key' => 'entradas', 'label' => 'Entradas', 'iso' => '8.3.3'],
    ['key' => 'planificacion', 'label' => 'Planificación', 'iso' => '8.3.2'],
    ['key' => 'controles', 'label' => 'Controles', 'iso' => '8.3.4'],
    ['key' => 'evidencias', 'label' => 'Evidencias', 'iso' => '8.3.5'],
    ['key' => 'cambios', 'label' => 'Cambios', 'iso' => '8.3.6'],
];

$statusStyle = static function (?string $status) use ($designLabels): array {
    $normalized = $status ?: 'pendiente';
    $label = $designLabels[$normalized] ?? ucfirst((string) $normalized);
    $background = '#fee2e2';
    $color = '#991b1b';

    if ($normalized === 'completado') {
        $background = '#dcfce7';
        $color = '#166534';
    } elseif ($normalized === 'en_progreso') {
        $background = '#e0f2fe';
        $color = '#075985';
    } elseif ($normalized === 'bloqueado') {
        $background = '#fef9c3';
        $color = '#92400e';
    }

    return [
        'bg' => $background,
        'color' => $color,
        'label' => $label,
    ];
};

$flattenNodes = static function (array $nodes) use (&$flattenNodes): array {
    $result = [];
    foreach ($nodes as $node) {
        $result[] = $node;
        if (!empty($node['children'])) {
            $result = array_merge($result, $flattenNodes($node['children']));
        }
    }

    return $result;
};

$findNodeInTree = static function (array $nodes, callable $matcher) use (&$findNodeInTree) {
    foreach ($nodes as $node) {
        if ($matcher($node)) {
            return $node;
        }
        if (!empty($node['children'])) {
            $found = $findNodeInTree($node['children'], $matcher);
            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
};

$countFiles = static function (?array $node) use (&$countFiles): int {
    if ($node === null) {
        return 0;
    }

    $total = count($node['files'] ?? []);
    foreach ($node['children'] ?? [] as $child) {
        $total += $countFiles($child);
    }

    return $total;
};

$collectNodesByIso = static function (?array $root, string $isoClause) use (&$collectNodesByIso): array {
    if ($root === null) {
        return [];
    }

    $matches = [];
    foreach ($root['children'] ?? [] as $child) {
        if (($child['iso_code'] ?? null) === $isoClause) {
            $matches[] = $child;
        }
        $matches = array_merge($matches, $collectNodesByIso($child, $isoClause));
    }

    return $matches;
};

$phaseCandidates = $flattenNodes($projectNodes);
$findByCodePrefix = static function (string $prefix) use ($phaseCandidates): ?array {
    foreach ($phaseCandidates as $candidate) {
        if (strpos((string) ($candidate['code'] ?? ''), $prefix) === 0) {
            return $candidate;
        }
    }

    return null;
};

if (!empty($lifecyclePhases)) {
    $phases = $lifecyclePhases;
} else {
    $phases = [];
    if ($normalizedMethodology === 'scrum') {
        $backlog = $findNodeInTree($projectNodes, static fn ($node) => ($node['code'] ?? '') === 'SCRUM-BACKLOG');
        $sprintsContainer = $findNodeInTree($projectNodes, static fn ($node) => ($node['code'] ?? '') === 'SCRUM-SPRINTS');
        $artefacts = $findNodeInTree($projectNodes, static fn ($node) => ($node['code'] ?? '') === 'SCRUM-ARTEFACTOS');

        $phases[] = ['key' => 'descubrimiento', 'label' => 'Descubrimiento', 'node' => $backlog];
        $phases[] = ['key' => 'backlog', 'label' => 'Backlog', 'node' => $backlog];

        foreach ($sprintsContainer['children'] ?? [] as $sprint) {
            $phases[] = [
                'key' => 'sprint-' . (int) ($sprint['id'] ?? 0),
                'label' => $sprint['name'] ?? $sprint['title'] ?? $sprint['code'] ?? 'Sprint',
                'node' => $sprint,
            ];
        }

        $phases[] = ['key' => 'release', 'label' => 'Release', 'node' => $artefacts ?: $sprintsContainer ?: $backlog];
    } else {
        $phaseOrder = [
            'inicio' => ['label' => 'Inicio', 'prefix' => '01-'],
            'planificacion' => ['label' => 'Planificación', 'prefix' => '02-'],
            'ejecucion' => ['label' => 'Ejecución', 'prefix' => '03-'],
            'seguimiento' => ['label' => 'Seguimiento', 'prefix' => '04-'],
            'cierre' => ['label' => 'Cierre', 'prefix' => '05-'],
        ];

        foreach ($phaseOrder as $key => $definition) {
            $node = $findByCodePrefix($definition['prefix']);
            $phases[] = [
                'key' => $key,
                'label' => $definition['label'],
                'node' => $node,
            ];
        }
    }
}

$activePhaseKey = $_GET['phase'] ?? ($phases[0]['key'] ?? '');
?>

<section class="card" style="padding:16px; border:1px solid var(--border); border-radius:14px; background: var(--surface); display:flex; flex-direction:column; gap:12px;">
    <header style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
        <div>
            <p class="eyebrow" style="margin:0; color: var(--muted); font-weight:800; text-transform: uppercase; letter-spacing: 0.05em;">Proyecto</p>
            <h3 style="margin:4px 0; color: var(--text-strong);"><?= htmlspecialchars($project['name'] ?? '') ?></h3>
            <p style="margin:0; color: var(--muted); font-weight:600;">Cliente: <?= htmlspecialchars($project['client_name'] ?? '') ?></p>
        </div>
        <span class="pill soft-<?= htmlspecialchars($signal['code'] ?? 'green') ?>" aria-label="Señal">
            <span aria-hidden="true">●</span>
            <?= htmlspecialchars($signal['label'] ?? 'Verde') ?>
        </span>
    </header>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px;">
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>PM</strong>
            <div><?= htmlspecialchars($project['pm_name'] ?? 'No asignado') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Estado</strong>
            <div><?= htmlspecialchars($project['status_label'] ?? $project['status'] ?? 'Pendiente') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Riesgo</strong>
            <div><?= htmlspecialchars($project['health_label'] ?? $project['health'] ?? 'Sin dato') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Prioridad</strong>
            <div><?= htmlspecialchars($project['priority_label'] ?? $project['priority'] ?? 'N/A') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Avance</strong>
            <div><?= (float) ($project['progress'] ?? 0) ?>%</div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Tipo</strong>
            <div><?= htmlspecialchars($project['project_type'] ?? 'convencional') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Metodología</strong>
            <div><?= htmlspecialchars($project['methodology'] ?? 'No definida') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Fase</strong>
            <div><?= htmlspecialchars($project['phase'] ?? 'Sin fase') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Riesgos</strong>
            <div><?= htmlspecialchars($risks ? implode(', ', $risks) : 'Sin riesgos asociados') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Score de riesgo</strong>
            <div><?= number_format($riskScore, 1) ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Nivel de riesgo</strong>
            <div><?= htmlspecialchars(ucfirst((string) $riskLevel)) ?></div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px;">
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Fechas</strong>
            <div><?= htmlspecialchars($project['start_date'] ?? 'N/A') ?> → <?= htmlspecialchars($project['end_date'] ?? 'N/A') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Presupuesto</strong>
            <div>$<?= number_format((float) ($project['budget'] ?? 0), 0, ',', '.') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Costo real</strong>
            <div>$<?= number_format((float) ($project['actual_cost'] ?? 0), 0, ',', '.') ?></div>
        </div>
    </div>
</section>

<section class="card" style="margin-top:16px; padding:16px; border:1px solid var(--border); border-radius:14px; background: var(--surface); display:flex; flex-direction:column; gap:12px;">
    <header style="display:flex; justify-content:space-between; align-items:center;">
        <h4 style="margin:0;">Talento asignado</h4>
        <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/talent">Gestionar talento</a>
    </header>
    <?php if (empty($assignments)): ?>
        <p style="margin:0; color: var(--muted);">Aún no hay asignaciones registradas.</p>
    <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:8px;">
            <?php foreach ($assignments as $assignment): ?>
                <div style="display:flex; justify-content:space-between; border:1px solid var(--border); padding:10px; border-radius:10px; background:#f8fafc;">
                    <div>
                        <strong><?= htmlspecialchars($assignment['talent_name'] ?? 'Talento') ?></strong>
                        <div style="color:var(--muted); font-weight:600;"><?= htmlspecialchars($assignment['role'] ?? '') ?></div>
                    </div>
                    <div style="text-align:right;">
                        <div><?= htmlspecialchars($assignment['start_date'] ?? 'N/A') ?> → <?= htmlspecialchars($assignment['end_date'] ?? 'N/A') ?></div>
                        <small style="color:var(--muted);">Horas semanales: <?= htmlspecialchars((string) ($assignment['weekly_hours'] ?? '0')) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="card" style="margin-top:16px; padding:16px; border:1px solid var(--border); border-radius:14px; background: var(--surface); display:flex; flex-direction:column; gap:12px;">
    <header style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
        <div>
            <p class="eyebrow" style="margin:0; color: var(--muted); font-weight:800; text-transform: uppercase; letter-spacing: 0.05em;">ISO 9001 · 8.3</p>
            <h4 style="margin:4px 0;">Diseño y Desarrollo</h4>
        </div>
    </header>

    <?php if (!empty($nodeFileError)): ?>
        <div style="color:#b91c1c; font-weight:600;"><?= htmlspecialchars($nodeFileError) ?></div>
    <?php endif; ?>

    <div style="border:1px solid var(--border); border-radius:12px; padding:12px; background:#fff; display:flex; flex-direction:column; gap:10px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
            <div>
                <h5 style="margin:0;">Ciclo del proyecto · ISO 8.3</h5>
                <p style="margin:2px 0 0 0; color:var(--muted);">Visualiza las fases y bloques funcionales. Adjunta evidencias en cada etapa.</p>
            </div>
            <?php if (!empty($criticalNodes)): ?>
                <span class="pill" style="background:#fef3c7; color:#92400e;">Controles críticos pendientes</span>
            <?php else: ?>
                <span class="pill" style="background:#dcfce7; color:#166534;">Controles al día</span>
            <?php endif; ?>
        </div>

        <div style="display:flex; gap:8px; overflow-x:auto; padding-bottom:4px;">
            <?php foreach ($phases as $phase): ?>
                <?php $phaseNode = $phase['node'] ?? null; ?>
                <?php $evidenceCount = $countFiles($phaseNode); ?>
                <?php $isActive = $phase['key'] === $activePhaseKey; ?>
                <button type="button" data-phase-select="<?= htmlspecialchars($phase['key']) ?>" aria-pressed="<?= $isActive ? 'true' : 'false' ?>" class="action-btn" style="min-width:140px; display:flex; flex-direction:column; align-items:flex-start; gap:4px; <?= $isActive ? 'background:var(--text-strong); color:#fff;' : '' ?>">
                    <span style="font-weight:700;"><?= htmlspecialchars($phase['label']) ?></span>
                    <small style="color:<?= $isActive ? '#e2e8f0' : 'var(--muted)' ?>;">
                        <?= $phaseNode ? 'Carpetas ISO activas' : 'Fase sin nodos' ?> · <?= $evidenceCount ?> evidencia<?= $evidenceCount === 1 ? '' : 's' ?>
                    </small>
                </button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($phases as $phase): ?>
            <?php $phaseNode = $phase['node'] ?? null; ?>
            <div data-phase-panel="<?= htmlspecialchars($phase['key']) ?>" style="display: <?= $phase['key'] === $activePhaseKey ? 'flex' : 'none' ?>; flex-direction:column; gap:10px; margin-top:4px;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
                    <div>
                        <h5 style="margin:0;"><?= htmlspecialchars($phase['label']) ?></h5>
                        <p style="margin:2px 0 0 0; color:var(--muted);">
                            <?= $phaseNode ? 'Selecciona el bloque y adjunta evidencias para esta fase.' : 'Aún no hay nodos configurados para esta fase.' ?>
                        </p>
                        <?php if (isset($phase['progress'])): ?>
                            <small style="color:var(--muted); display:block;">Avance: <?= htmlspecialchars((string) ($phase['progress'])) ?>%</small>
                        <?php endif; ?>
                    </div>
                    <?php if ($phaseNode): ?>
                        <?php $phaseStatus = $statusStyle($phaseNode['status'] ?? ''); ?>
                        <span class="pill" style="background: <?= $phaseStatus['bg'] ?>; color: <?= $phaseStatus['color'] ?>;">
                            <?= htmlspecialchars($phaseStatus['label']) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (!$phaseNode): ?>
                    <p style="margin:0; color:var(--muted);">No hay nodos de proyecto para esta fase. El backend generará las carpetas cuando corresponda.</p>
                <?php else: ?>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:10px;">
                            <?php foreach ($blockDefinitions as $block): ?>
                            <?php
                                $blockNodes = $collectNodesByIso($phaseNode, $block['iso']);
                                $completedNodes = 0;
                                foreach ($blockNodes as $candidate) {
                                    if (($candidate['status'] ?? '') === 'completado') {
                                        $completedNodes++;
                                    }
                                }
                                $totalNodes = count($blockNodes);
                            ?>
                            <div style="border:1px solid var(--border); border-radius:12px; padding:10px; background:#f8fafc; display:flex; flex-direction:column; gap:8px;">
                                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                                    <div>
                                        <h6 style="margin:0;"><?= htmlspecialchars($block['label']) ?></h6>
                                        <small style="color:var(--muted);">ISO <?= htmlspecialchars($block['iso']) ?></small>
                                    </div>
                                    <span class="pill" style="background:#e2e8f0; color:#0f172a;"><?= $completedNodes ?>/<?= $totalNodes ?> controles</span>
                                </div>
                                <?php if (empty($blockNodes)): ?>
                                    <p style="margin:0; color:var(--muted);">No hay nodos disponibles para este bloque.</p>
                                <?php else: ?>
                                    <div style="display:flex; flex-direction:column; gap:8px;">
                                        <?php foreach ($blockNodes as $node): ?>
                                            <?php $style = $statusStyle($node['status'] ?? ''); ?>
                                            <div style="border:1px solid var(--border); border-radius:10px; padding:8px; background:#fff; display:flex; flex-direction:column; gap:6px;">
                                                <div style="display:flex; justify-content:space-between; gap:8px;">
                                                    <div>
                                                        <strong><?= htmlspecialchars($node['name'] ?? ($node['code'] ?? 'Carpeta')) ?></strong>
                                                        <?php if (!empty($node['iso_code'])): ?>
                                                            <small style="color:var(--muted); display:block;">ISO <?= htmlspecialchars((string) $node['iso_code']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="pill" style="background: <?= $style['bg'] ?>; color: <?= $style['color'] ?>;"><?= htmlspecialchars($style['label']) ?></span>
                                                </div>
                                                <?php if (!empty($node['description'])): ?>
                                                    <p style="margin:0; color:var(--muted);"><?= htmlspecialchars((string) $node['description']) ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($node['files'])): ?>
                                                    <ul style="margin:0; padding-left:16px; display:flex; flex-direction:column; gap:4px;">
                                                        <?php foreach ($node['files'] as $file): ?>
                                                            <li>
                                                                <a href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/nodes/<?= (int) ($file['id'] ?? 0) ?>/download" target="_blank"><?= htmlspecialchars($file['file_name'] ?? 'Archivo') ?></a>
                                                                <small style="color:var(--muted); margin-left:6px;"><?= htmlspecialchars(substr((string) ($file['created_at'] ?? ''), 0, 16)) ?></small>
                                                                <?php if ($canManage): ?>
                                                                    <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/nodes/<?= (int) ($file['id'] ?? 0) ?>/delete" style="display:inline;">
                                                                        <button type="submit" class="action-btn" style="margin-left:6px; background:#fee2e2; color:#b91c1c; border:none;">Eliminar</button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else: ?>
                                                    <p style="margin:0; color:var(--muted);">Aún no hay evidencias en este bloque.</p>
                                                <?php endif; ?>
                                                <?php if ($canManage): ?>
                                                    <form method="POST" enctype="multipart/form-data" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/nodes/<?= (int) ($node['id'] ?? 0) ?>/files" style="margin-top:4px; display:flex; gap:8px; align-items:center;">
                                                        <input type="file" name="node_file" required style="flex:1; border:1px solid var(--border); padding:8px; border-radius:8px;">
                                                        <button type="submit" class="action-btn">Adjuntar</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        (function() {
            const tabs = document.querySelectorAll('[data-phase-select]');
            const panels = document.querySelectorAll('[data-phase-panel]');

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    const phase = tab.getAttribute('data-phase-select');
                    tabs.forEach((other) => {
                        const isActive = other === tab;
                        other.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                        if (isActive) {
                            other.style.background = 'var(--text-strong)';
                            other.style.color = '#fff';
                        } else {
                            other.style.background = '';
                            other.style.color = '';
                        }
                    });

                    panels.forEach((panel) => {
                        panel.style.display = panel.getAttribute('data-phase-panel') === phase ? 'flex' : 'none';
                    });
                });
            });
        })();
    </script>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:12px;">
        <div style="border:1px solid var(--border); border-radius:12px; padding:12px; background:#f8fafc; display:flex; flex-direction:column; gap:10px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h5 style="margin:0;">Entradas del diseño</h5>
                <span class="pill" aria-label="Estatus de entradas" style="background: <?= (int) ($project['design_inputs_defined'] ?? 0) === 1 ? '#dcfce7' : '#fee2e2' ?>; color: <?= (int) ($project['design_inputs_defined'] ?? 0) === 1 ? '#166534' : '#991b1b' ?>;">
                    <?= (int) ($project['design_inputs_defined'] ?? 0) === 1 ? 'Completas' : 'Pendiente' ?>
                </span>
            </div>
            <?php if (!empty($designInputError)): ?>
                <div style="color:#b91c1c; font-weight:600;"><?= htmlspecialchars($designInputError) ?></div>
            <?php endif; ?>
            <?php if (empty($designInputs)): ?>
                <p style="margin:0; color: var(--muted);">No hay entradas registradas.</p>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <?php foreach ($designInputs as $input): ?>
                        <div style="border:1px solid var(--border); padding:10px; border-radius:10px; background:#fff; display:flex; justify-content:space-between; gap:8px; align-items:flex-start;">
                            <div>
                                <strong><?= htmlspecialchars($designLabels[$input['input_type']] ?? $input['input_type']) ?></strong>
                                <div style="color:var(--muted);"><?= nl2br(htmlspecialchars($input['description'] ?? '')) ?></div>
                                <?php if (!empty($input['source'])): ?>
                                    <small style="color:var(--muted); display:block;">Fuente: <?= htmlspecialchars((string) $input['source']) ?></small>
                                <?php endif; ?>
                                <?php if ((int) ($input['resolved_conflict'] ?? 0) === 1): ?>
                                    <small style="color:#15803d; font-weight:700; display:block;">Conflicto resuelto</small>
                                <?php endif; ?>
                                <small style="color:var(--muted); display:block;">Registrado: <?= htmlspecialchars(substr((string) ($input['created_at'] ?? ''), 0, 16)) ?></small>
                            </div>
                            <?php if (!empty($canManage)): ?>
                                <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/design-inputs/<?= (int) ($input['id'] ?? 0) ?>/delete" style="margin:0;">
                                    <button type="submit" class="action-btn" style="background:#fee2e2; color:#b91c1c; border:none;">Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($canManage)): ?>
                <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/design-inputs" style="display:flex; flex-direction:column; gap:8px; margin-top:8px;">
                    <label>Tipo
                        <select name="input_type" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
                            <option value="">Selecciona</option>
                            <?php foreach ($designInputTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($designLabels[$type] ?? $type) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Descripción
                        <textarea name="description" rows="3" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;"></textarea>
                    </label>
                    <label>Fuente
                        <input type="text" name="source" placeholder="Cliente, normativa, referencia" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
                    </label>
                    <label style="display:flex; gap:8px; align-items:center;">
                        <input type="checkbox" name="resolved_conflict" value="1"> Conflicto resuelto
                    </label>
                    <button type="submit" class="primary-button" style="border:none; cursor:pointer;">Agregar entrada</button>
                </form>
            <?php endif; ?>
        </div>

        <div style="border:1px solid var(--border); border-radius:12px; padding:12px; background:#f8fafc; display:flex; flex-direction:column; gap:10px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h5 style="margin:0;">Controles del diseño</h5>
                <span class="pill" style="background:#e0f2fe; color:#075985;">Seguimiento</span>
            </div>
            <?php if (!empty($designControlError)): ?>
                <div style="color:#b91c1c; font-weight:600;"><?= htmlspecialchars($designControlError) ?></div>
            <?php endif; ?>
            <?php if (empty($designControls)): ?>
                <p style="margin:0; color: var(--muted);">Aún no hay controles registrados.</p>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <?php foreach ($designControls as $control): ?>
                        <div style="border:1px solid var(--border); padding:10px; border-radius:10px; background:#fff; display:flex; flex-direction:column; gap:4px;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <strong><?= htmlspecialchars($designLabels[$control['control_type']] ?? $control['control_type']) ?></strong>
                                <span class="pill" style="background: <?= $control['result'] === 'aprobado' ? '#dcfce7' : ($control['result'] === 'rechazado' ? '#fee2e2' : '#e0f2fe') ?>; color: <?= $control['result'] === 'aprobado' ? '#166534' : ($control['result'] === 'rechazado' ? '#991b1b' : '#075985') ?>;">
                                    <?= htmlspecialchars($designLabels[$control['result']] ?? $control['result']) ?>
                                </span>
                            </div>
                            <div style="color:var(--muted);"><?= nl2br(htmlspecialchars($control['description'] ?? '')) ?></div>
                            <?php if (!empty($control['corrective_action'])): ?>
                                <small style="color:#b45309; display:block;">Acción correctiva: <?= htmlspecialchars((string) $control['corrective_action']) ?></small>
                            <?php endif; ?>
                            <small style="color:var(--muted); display:block;">Responsable: <?= htmlspecialchars($control['performer_name'] ?? 'N/D') ?> · <?= htmlspecialchars(substr((string) ($control['performed_at'] ?? ''), 0, 16)) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($canManage)): ?>
                <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/design-controls" style="display:flex; flex-direction:column; gap:8px; margin-top:8px;">
                    <label>Tipo de control
                        <select name="control_type" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
                            <option value="">Selecciona</option>
                            <?php foreach ($designControlTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($designLabels[$type] ?? $type) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Resultado
                        <select name="result" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
                            <option value="">Selecciona</option>
                            <?php foreach ($designControlResults as $result): ?>
                                <option value="<?= htmlspecialchars($result) ?>"><?= htmlspecialchars($designLabels[$result] ?? $result) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Descripción
                        <textarea name="description" rows="3" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;"></textarea>
                    </label>
                    <label>Acción correctiva (solo si aplica)
                        <textarea name="corrective_action" rows="2" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;"></textarea>
                    </label>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:8px;">
                        <label>Responsable
                            <select name="performed_by" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
                                <option value="">Selecciona</option>
                                <?php foreach ($performers as $person): ?>
                                    <option value="<?= (int) ($person['id'] ?? 0) ?>"><?= htmlspecialchars($person['name'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Fecha
                            <input type="datetime-local" name="performed_at" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
                        </label>
                    </div>
                    <button type="submit" class="primary-button" style="border:none; cursor:pointer;">Registrar control</button>
                </form>
            <?php endif; ?>
        </div>

        <div style="border:1px solid var(--border); border-radius:12px; padding:12px; background:#f8fafc; display:flex; flex-direction:column; gap:10px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h5 style="margin:0;">Salidas del diseño</h5>
                <span class="pill" style="background: <?= (int) ($project['design_validation_done'] ?? 0) === 1 ? '#dcfce7' : '#fef9c3' ?>; color: <?= (int) ($project['design_validation_done'] ?? 0) === 1 ? '#166534' : '#92400e' ?>;">
                    <?= (int) ($project['design_validation_done'] ?? 0) === 1 ? 'Aprobadas' : 'Pendiente' ?>
                </span>
            </div>
            <?php if (!empty($designOutputError)): ?>
                <div style="color:#b91c1c; font-weight:600;"><?= htmlspecialchars($designOutputError) ?></div>
            <?php endif; ?>
            <ul style="margin:0; padding-left:16px; color:var(--muted); display:flex; flex-direction:column; gap:4px;">
                <li>Revisión completada: <?= (int) ($project['design_review_done'] ?? 0) === 1 ? 'Sí' : 'No' ?></li>
                <li>Verificación completada: <?= (int) ($project['design_verification_done'] ?? 0) === 1 ? 'Sí' : 'No' ?></li>
                <li>Validación completada: <?= (int) ($project['design_validation_done'] ?? 0) === 1 ? 'Sí' : 'No' ?></li>
            </ul>
            <p style="margin:0; color: var(--muted);">Estos indicadores se calculan automáticamente con los controles registrados y el árbol ISO 8.3.</p>
        </div>

        <div style="border:1px solid var(--border); border-radius:12px; padding:12px; background:#f8fafc; display:flex; flex-direction:column; gap:10px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h5 style="margin:0;">Cambios del diseño</h5>
                <span class="pill" style="background:#f1f5f9; color:#0f172a;">Control de cambios</span>
            </div>
            <?php if (!empty($designChangeError)): ?>
                <div style="color:#b91c1c; font-weight:600;"><?= htmlspecialchars($designChangeError) ?></div>
            <?php endif; ?>
            <?php if (empty($designChanges)): ?>
                <p style="margin:0; color: var(--muted);">No hay cambios registrados.</p>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <?php foreach ($designChanges as $change): ?>
                        <div style="border:1px solid var(--border); padding:10px; border-radius:10px; background:#fff; display:flex; flex-direction:column; gap:6px;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <strong>Cambio #<?= (int) ($change['id'] ?? 0) ?></strong>
                                <span class="pill" style="background: <?= ($change['status'] ?? '') === 'aprobado' ? '#dcfce7' : '#fef9c3' ?>; color: <?= ($change['status'] ?? '') === 'aprobado' ? '#166534' : '#92400e' ?>;">
                                    <?= htmlspecialchars($designLabels[$change['status']] ?? ($change['status'] ?? '')) ?>
                                </span>
                            </div>
                            <div style="color:var(--muted);"><?= nl2br(htmlspecialchars($change['description'] ?? '')) ?></div>
                            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap:6px; font-size:13px;">
                                <div><strong>Alcance:</strong> <?= htmlspecialchars($designLabels[$change['impact_scope']] ?? $change['impact_scope'] ?? '') ?></div>
                                <div><strong>Tiempo:</strong> <?= htmlspecialchars($designLabels[$change['impact_time']] ?? $change['impact_time'] ?? '') ?></div>
                                <div><strong>Costo:</strong> <?= htmlspecialchars($designLabels[$change['impact_cost']] ?? $change['impact_cost'] ?? '') ?></div>
                                <div><strong>Calidad:</strong> <?= htmlspecialchars($designLabels[$change['impact_quality']] ?? $change['impact_quality'] ?? '') ?></div>
                            </div>
                            <small style="color:var(--muted); display:block;">Requiere revisión/validación: <?= (int) ($change['requires_review_validation'] ?? 0) === 1 ? 'Sí' : 'No' ?></small>
                            <small style="color:var(--muted); display:block;">
                                Registrado por <?= htmlspecialchars($change['created_by_name'] ?? 'N/D') ?> · <?= htmlspecialchars(substr((string) ($change['created_at'] ?? ''), 0, 16)) ?>
                            </small>
                            <?php if (!empty($change['approved_at'])): ?>
                                <small style="color:#166534; display:block;">Aprobado por <?= htmlspecialchars($change['approved_by_name'] ?? 'N/D') ?> · <?= htmlspecialchars(substr((string) ($change['approved_at'] ?? ''), 0, 16)) ?></small>
                            <?php endif; ?>
                            <?php if (!empty($canManage) && ($change['status'] ?? '') !== 'aprobado'): ?>
                                <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/design-changes/<?= (int) ($change['id'] ?? 0) ?>/approve" style="margin:0; display:flex; justify-content:flex-end;">
                                    <button type="submit" class="action-btn" style="background:#dcfce7; color:#166534; border:none;">Aprobar cambio</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($canManage)): ?>
                <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/design-changes" style="display:flex; flex-direction:column; gap:8px; margin-top:8px;">
                    <label>Descripción
                        <textarea name="description" rows="3" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;"></textarea>
                    </label>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:8px;">
                        <label>Impacto en alcance
                            <select name="impact_scope" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
                                <option value="">Selecciona</option>
                                <?php foreach ($designChangeImpactLevels as $impact): ?>
                                    <option value="<?= htmlspecialchars($impact) ?>"><?= htmlspecialchars($designLabels[$impact] ?? $impact) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Impacto en tiempo
                            <select name="impact_time" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
                                <option value="">Selecciona</option>
                                <?php foreach ($designChangeImpactLevels as $impact): ?>
                                    <option value="<?= htmlspecialchars($impact) ?>"><?= htmlspecialchars($designLabels[$impact] ?? $impact) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:8px;">
                        <label>Impacto en costo
                            <select name="impact_cost" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
                                <option value="">Selecciona</option>
                                <?php foreach ($designChangeImpactLevels as $impact): ?>
                                    <option value="<?= htmlspecialchars($impact) ?>"><?= htmlspecialchars($designLabels[$impact] ?? $impact) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Impacto en calidad
                            <select name="impact_quality" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
                                <option value="">Selecciona</option>
                                <?php foreach ($designChangeImpactLevels as $impact): ?>
                                    <option value="<?= htmlspecialchars($impact) ?>"><?= htmlspecialchars($designLabels[$impact] ?? $impact) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <label style="display:flex; gap:8px; align-items:center;">
                        <input type="checkbox" name="requires_review_validation" value="1"> Requiere nueva revisión/validación
                    </label>
                    <button type="submit" class="primary-button" style="border:none; cursor:pointer;">Registrar cambio</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<section style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
    <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/edit">Editar</a>
    <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/costs">Costos</a>
    <form action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/close" method="GET" style="margin:0;">
        <button class="action-btn" type="submit">Cerrar proyecto</button>
    </form>
    <?php if (!empty($canInactivate) || !empty($canDelete)): ?>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <?php if (!empty($canInactivate)): ?>
                <button type="button" class="action-btn" data-open-action="inactivate" style="border-color:#fed7aa; color:#9a3412; background:#fffbeb;">Inactivar</button>
            <?php endif; ?>
            <?php if (!empty($canDelete)): ?>
                <button type="button" class="action-btn" data-open-action="delete" style="border-color:#fecaca; color:#b91c1c; background:#fef2f2;">Eliminar</button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php if (!empty($canInactivate) || !empty($canDelete)): ?>
    <div id="delete-modal" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(17,24,39,0.45); align-items:center; justify-content:center; padding:16px; z-index:30;">
        <div class="card" style="max-width:540px; width:100%; border:1px solid #fecaca; box-shadow:0 20px 40px rgba(0,0,0,0.18);">
            <div class="toolbar">
                <div style="display:flex; gap:10px; align-items:flex-start;">
                    <span aria-hidden="true" style="width:36px; height:36px; border-radius:12px; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; display:inline-flex; align-items:center; justify-content:center; font-weight:800;">!</span>
                    <div>
                        <p class="badge danger" style="margin:0;" data-modal-context>Acción crítica</p>
                        <h4 style="margin:4px 0 0 0;" data-modal-title>Eliminar proyecto</h4>
                        <p style="margin:4px 0 0 0; color:var(--muted);" data-modal-subtitle>Esta acción es irreversible y eliminará en cascada tareas, timesheets, evidencias ISO y asignaciones asociadas.</p>
                    </div>
                </div>
                <button type="button" class="btn ghost" data-close-delete aria-label="Cerrar" style="color:var(--muted);">✕</button>
            </div>
            <form method="POST" action="<?= $basePath ?>/projects/delete" class="grid" style="gap:12px;" id="delete-form">
                <input type="hidden" name="id" value="<?= (int) ($project['id'] ?? 0) ?>">
                <input type="hidden" name="math_operand1" id="math_operand1" value="<?= (int) ($mathOperand1 ?? 0) ?>">
                <input type="hidden" name="math_operand2" id="math_operand2" value="<?= (int) ($mathOperand2 ?? 0) ?>">
                <input type="hidden" name="math_operator" id="math_operator" value="<?= htmlspecialchars((string) ($mathOperator ?? '+')) ?>">
                <input type="hidden" name="force_delete" id="force_delete" value="1">
                <div id="dependency-notice" style="<?= !empty($hasDependencies) ? '' : 'display:none;' ?> padding: 10px 12px; border:1px solid #fed7aa; background:#fffbeb; border-radius:10px; color:#9a3412; font-weight:600;">
                    <span data-dependency-text>Si existen dependencias activas, la eliminación permanente las borrará en cascada.</span>
                    <ul style="margin:6px 0 0 14px; padding:0; color:#92400e;">
                        <li><?= (int) ($dependencies['tasks'] ?? 0) ?> tareas</li>
                        <li><?= (int) ($dependencies['timesheets'] ?? 0) ?> timesheets</li>
                        <li><?= (int) ($dependencies['assignments'] ?? 0) ?> asignaciones</li>
                        <li><?= (int) ($dependencies['design_inputs'] ?? 0) ?> entradas de diseño</li>
                        <li><?= (int) ($dependencies['design_controls'] ?? 0) ?> controles de diseño</li>
                        <li><?= (int) ($dependencies['design_changes'] ?? 0) ?> cambios de diseño</li>
                        <li><?= (int) ($dependencies['nodes'] ?? 0) ?> nodos/evidencias ISO</li>
                    </ul>
                </div>
                <div>
                    <p style="margin:0 0 4px 0; color:var(--text); font-weight:600;">Confirmación obligatoria</p>
                    <p style="margin:0 0 8px 0; color:var(--muted);">Resuelve la siguiente operación para confirmar.</p>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="flex:1;">
                            <div style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:rgb(249, 250, 251); font-weight:700;">
                                <?= (int) ($mathOperand1 ?? 0) ?> <?= htmlspecialchars((string) ($mathOperator ?? '+')) ?> <?= (int) ($mathOperand2 ?? 0) ?> =
                            </div>
                        </div>
                        <input type="number" name="math_result" id="math_result" inputmode="numeric" aria-label="Resultado de la operación" placeholder="Resultado" style="width:120px; padding:10px 12px; border-radius:10px; border:1px solid var(--border);">
                    </div>
                </div>
                <div id="action-feedback" style="display:none; padding:10px 12px; border-radius:10px; border:1px solid #fecaca; background:#fef2f2; color:#b91c1c; font-weight:600;"></div>
                <div style="display:flex; justify-content:flex-end; gap:8px;">
                    <button type="button" class="btn ghost" data-close-delete>Cancelar</button>
                    <button type="submit" class="btn ghost" id="confirm-delete-btn" style="color:#b91c1c; border-color:#fecaca; background:#fef2f2;" disabled>Eliminar permanentemente</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            const modal = document.getElementById('delete-modal');
            const openButtons = document.querySelectorAll('[data-open-action]');
            const closeButtons = document.querySelectorAll('[data-close-delete]');
            const resultInput = document.getElementById('math_result');
            const confirmButton = document.getElementById('confirm-delete-btn');
            const form = document.getElementById('delete-form');
            const operand1 = Number(document.getElementById('math_operand1')?.value || 0);
            const operand2 = Number(document.getElementById('math_operand2')?.value || 0);
            const operator = (document.getElementById('math_operator')?.value || '').trim();
            const expected = operator === '+' ? operand1 + operand2 : operand1 - operand2;
            const dependencyNotice = document.getElementById('dependency-notice');
            const dependencyMessageHolder = dependencyNotice?.querySelector('[data-dependency-text]');
            const actionFeedback = document.getElementById('action-feedback');
            const modalTitle = document.querySelector('[data-modal-title]');
            const modalSubtitle = document.querySelector('[data-modal-subtitle]');
            const modalContext = document.querySelector('[data-modal-context]');
            const projectId = <?= (int) ($project['id'] ?? 0) ?>;
            const hasDependencies = <?= !empty($hasDependencies) ? 'true' : 'false' ?>;
            const isAdmin = <?= !empty($canDelete) ? 'true' : 'false' ?>;
            const basePath = '<?= $basePath ?>';
            const forceDeleteInput = document.getElementById('force_delete');
            const dependencyMessage = 'Si existen dependencias activas, la eliminación permanente las borrará en cascada.';

            const actions = {
                delete: {
                    title: 'Eliminar proyecto',
                    subtitle: 'Esta acción elimina definitivamente el proyecto y sus registros asociados.',
                    context: 'Acción crítica',
                    actionUrl: `${basePath}/projects/delete`,
                    buttonLabel: 'Eliminar permanentemente',
                    buttonStyle: 'color:#b91c1c; border-color:#fecaca; background:#fef2f2;'
                },
                inactivate: {
                    title: 'Inactivar proyecto',
                    subtitle: 'Se deshabilita el proyecto y se conserva la información asociada.',
                    context: 'Acción segura',
                    actionUrl: `${basePath}/projects/${projectId}/inactivate`,
                    buttonLabel: 'Inactivar proyecto',
                    buttonStyle: 'color:#9a3412; border-color:#fed7aa; background:#fffbeb;'
                }
            };

            let currentAction = hasDependencies && !isAdmin ? 'inactivate' : 'delete';

            const syncState = () => {
                if (!resultInput || !confirmButton) return;
                const current = Number(resultInput.value.trim());
                const isValid = !Number.isNaN(current) && current === expected;
                confirmButton.disabled = !isValid;
            };

            const setAction = (action) => {
                currentAction = action;
                const config = actions[action];
                if (!config) return;

                if (modalTitle) modalTitle.textContent = config.title;
                if (modalSubtitle) modalSubtitle.textContent = config.subtitle;
                if (modalContext) modalContext.textContent = config.context;
                if (confirmButton) {
                    confirmButton.textContent = config.buttonLabel;
                    confirmButton.style.cssText = `${confirmButton.getAttribute('style') || ''}; ${config.buttonStyle}`;
                }
                if (form) {
                    form.setAttribute('action', config.actionUrl);
                }

                if (dependencyNotice) {
                    if (dependencyMessageHolder) {
                        dependencyMessageHolder.textContent = dependencyMessage;
                    }
                    dependencyNotice.style.display = hasDependencies || action === 'inactivate' ? 'block' : 'none';
                }

                if (forceDeleteInput) {
                    forceDeleteInput.value = action === 'delete' ? '1' : '0';
                }
            };

            openButtons.forEach((btn) => btn.addEventListener('click', (event) => {
                if (!modal) return;
                const action = event.currentTarget?.getAttribute('data-open-action') || currentAction;
                setAction(action);
                modal.style.display = 'flex';
                if (resultInput) {
                    resultInput.value = '';
                }
                actionFeedback.style.display = 'none';
                actionFeedback.textContent = '';
                resultInput?.focus();
                syncState();
            }));

            closeButtons.forEach((btn) => btn.addEventListener('click', () => {
                if (modal) {
                    modal.style.display = 'none';
                }
            }));

            resultInput?.addEventListener('input', syncState);

            form?.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!form) return;
                actionFeedback.style.display = 'none';
                actionFeedback.textContent = '';

                if (currentAction === 'delete' && hasDependencies && !isAdmin) {
                    actionFeedback.textContent = dependencyMessage;
                    actionFeedback.style.display = 'block';
                    setAction('inactivate');
                    return;
                }

                const payload = new FormData(form);
                let responseData;

                try {
                    const response = await fetch(form.getAttribute('action') || '', {
                        method: 'POST',
                        body: payload,
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    responseData = await response.json();
                } catch (error) {
                    actionFeedback.textContent = 'No se pudo completar la acción. Intenta nuevamente.';
                    actionFeedback.style.display = 'block';
                    return;
                }

                if (responseData?.success) {
                    alert(responseData.message || 'Acción completada.');
                    window.location.href = `${basePath}/projects`;
                    return;
                }

                const message = responseData?.message || 'No se pudo completar la acción.';
                actionFeedback.textContent = message;
                actionFeedback.style.display = 'block';

                if (responseData?.can_inactivate) {
                    setAction('inactivate');
                }
            });

            setAction(currentAction);
        })();
    </script>
<?php endif; ?>
