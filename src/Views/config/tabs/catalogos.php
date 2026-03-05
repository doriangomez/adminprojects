<?php
$folderSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>';
$alertSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
$buildingSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><path d="M3 21h18"/><path d="M10 9h4M10 13h4"/></svg>';
$usersSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
$checkSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
$settingsSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';
$catalogDomains = [
    ['key' => 'proyectos', 'title' => 'Proyectos', 'icon' => $folderSvg, 'open' => true],
    ['key' => 'riesgos', 'title' => 'Riesgos', 'icon' => $alertSvg, 'open' => true],
    ['key' => 'organizacion', 'title' => 'Organización', 'icon' => $buildingSvg, 'open' => false],
    ['key' => 'clientes', 'title' => 'Clientes', 'icon' => $usersSvg, 'open' => false],
    ['key' => 'estados', 'title' => 'Estados', 'icon' => $checkSvg, 'open' => false],
    ['key' => 'sistema', 'title' => 'Sistema', 'icon' => $settingsSvg, 'open' => false],
];
$baseCatalogMeta = [
    'priorities' => ['label' => 'Prioridades', 'domain' => 'proyectos'],
    'project_health' => ['label' => 'Health de proyecto', 'domain' => 'proyectos'],
    'project_status' => ['label' => 'Estados de proyecto', 'domain' => 'estados'],
    'client_status' => ['label' => 'Estados de cliente', 'domain' => 'estados'],
    'client_sectors' => ['label' => 'Sectores de cliente', 'domain' => 'clientes'],
    'client_categories' => ['label' => 'Categorías de cliente', 'domain' => 'clientes'],
    'client_risk' => ['label' => 'Riesgo de cliente', 'domain' => 'clientes'],
    'client_areas' => ['label' => 'Áreas de cliente', 'domain' => 'clientes'],
];
$domainTables = [];
$domainCounts = [];
foreach ($masterData as $table => $items) {
    $meta = $baseCatalogMeta[$table] ?? [
        'label' => ucwords(str_replace('_', ' ', $table)),
        'domain' => 'sistema',
    ];
    $domainTables[$meta['domain']][$table] = [
        'label' => $meta['label'],
        'items' => $items,
    ];
    $domainCounts[$meta['domain']] = ($domainCounts[$meta['domain']] ?? 0) + count($items);
}
$domainCounts['riesgos'] = count($riskCatalog);
$domainCounts['sistema'] = ($domainCounts['sistema'] ?? 0) + 2;
?>
<section id="panel-catalogos" class="tab-panel">
    <div class="catalog-domain-list">
        <?php foreach ($catalogDomains as $domain): ?>
            <?php $domainKey = $domain['key']; ?>
            <details id="catalog-section-<?= htmlspecialchars($domainKey) ?>" class="catalog-domain-card" <?= $domain['open'] ? 'open' : '' ?>>
                <summary>
                    <div class="catalog-domain-title">
                        <span class="catalog-domain-icon"><?= $domain['icon'] ?></span>
                        <strong><?= htmlspecialchars($domain['title']) ?></strong>
                    </div>
                    <span class="badge neutral"><?= $domainCounts[$domainKey] ?? 0 ?> ítems</span>
                </summary>
                <div class="catalog-domain-body">
                    <?php if ($domainKey === 'riesgos'): ?>
                        <div class="catalog-stack">
                            <div class="card subtle-card catalog-block">
                                <div class="toolbar">
                                    <div>
                                        <p class="badge neutral" style="margin:0;">Catálogo maestro</p>
                                        <h3 style="margin:6px 0 2px 0;">Riesgos centralizados</h3>
                                        <small class="text-muted">Checklist único para todos los proyectos (no editable desde proyectos).</small>
                                    </div>
                                </div>
                                <div class="card-content">
                                    <form method="POST" action="/config/risk-catalog/create" class="config-form-grid compact-grid">
                                        <input name="code" placeholder="Código (único)" required>
                                        <input name="category" placeholder="Categoría" required>
                                        <input name="label" placeholder="Nombre del riesgo" required>
                                        <label>Aplica a
                                            <select name="applies_to">
                                                <option value="ambos">Ambos</option>
                                                <option value="convencional">Convencional</option>
                                                <option value="scrum">Scrum</option>
                                            </select>
                                        </label>
                                        <label>Probabilidad (1-5)
                                            <input type="number" name="severity_base" min="1" max="5" value="3" required>
                                        </label>
                                        <div class="impact-switches full-span">
                                            <span class="pillset-title">Impacto</span>
                                            <div class="catalog-impact-grid">
                                                <label class="toggle-switch toggle-switch--compact catalog-toggle">
                                                    <span class="toggle-label">Alcance</span>
                                                    <input type="checkbox" name="impact_scope">
                                                    <span class="toggle-track" aria-hidden="true"></span>
                                                </label>
                                                <label class="toggle-switch toggle-switch--compact catalog-toggle">
                                                    <span class="toggle-label">Tiempo</span>
                                                    <input type="checkbox" name="impact_time">
                                                    <span class="toggle-track" aria-hidden="true"></span>
                                                </label>
                                                <label class="toggle-switch toggle-switch--compact catalog-toggle">
                                                    <span class="toggle-label">Costo</span>
                                                    <input type="checkbox" name="impact_cost">
                                                    <span class="toggle-track" aria-hidden="true"></span>
                                                </label>
                                                <label class="toggle-switch toggle-switch--compact catalog-toggle">
                                                    <span class="toggle-label">Calidad</span>
                                                    <input type="checkbox" name="impact_quality">
                                                    <span class="toggle-track" aria-hidden="true"></span>
                                                </label>
                                                <label class="toggle-switch toggle-switch--compact catalog-toggle">
                                                    <span class="toggle-label">Legal</span>
                                                    <input type="checkbox" name="impact_legal">
                                                    <span class="toggle-track" aria-hidden="true"></span>
                                                </label>
                                            </div>
                                        </div>
                                        <label class="toggle-switch toggle-switch--state">
                                            <span class="toggle-label">Activo</span>
                                            <input type="checkbox" name="active" checked>
                                            <span class="toggle-track" aria-hidden="true"></span>
                                            <span class="toggle-state" aria-hidden="true"></span>
                                        </label>
                                        <div class="form-footer">
                                            <span class="text-muted">Se agrega al catálogo central y queda disponible en proyectos.</span>
                                            <button class="btn primary" type="submit">Agregar riesgo</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="catalog-list">
                                <?php foreach ($risksByCategory as $category => $risks): ?>
                                    <div class="catalog-subgroup">
                                        <div class="catalog-subgroup-header">
                                            <strong><?= htmlspecialchars($category) ?></strong>
                                            <span class="badge neutral"><?= count($risks) ?> riesgos</span>
                                        </div>
                                        <div class="risk-matrix">
                                            <div class="risk-matrix-header">
                                                <span>Riesgo</span>
                                                <span>Probabilidad</span>
                                                <span>Impacto</span>
                                                <span>Nivel</span>
                                                <span>Estado</span>
                                                <span>Acciones</span>
                                            </div>
                                            <?php foreach ($risks as $risk): ?>
                                                <form id="risk-update-<?= htmlspecialchars($risk['code']) ?>" method="POST" action="/config/risk-catalog/update"></form>
                                                <form id="risk-delete-<?= htmlspecialchars($risk['code']) ?>" method="POST" action="/config/risk-catalog/delete" onsubmit="return confirm('¿Eliminar riesgo del catálogo? Esta acción no se puede deshacer.');">
                                                    <input type="hidden" name="code" value="<?= htmlspecialchars($risk['code']) ?>">
                                                </form>
                                                <div class="risk-matrix-row">
                                                    <input type="hidden" name="code" value="<?= htmlspecialchars($risk['code']) ?>" form="risk-update-<?= htmlspecialchars($risk['code']) ?>">
                                                    <div class="risk-main">
                                                        <label class="catalog-field">Riesgo
                                                            <input name="label" value="<?= htmlspecialchars($risk['label']) ?>" form="risk-update-<?= htmlspecialchars($risk['code']) ?>">
                                                        </label>
                                                        <div class="risk-meta-grid">
                                                            <label class="catalog-field">Categoría
                                                                <input name="category" value="<?= htmlspecialchars($risk['category']) ?>" form="risk-update-<?= htmlspecialchars($risk['code']) ?>">
                                                            </label>
                                                            <label class="catalog-field">Aplica a
                                                                <select name="applies_to" form="risk-update-<?= htmlspecialchars($risk['code']) ?>">
                                                                    <option value="ambos" <?= ($risk['applies_to'] ?? '') === 'ambos' ? 'selected' : '' ?>>Ambos</option>
                                                                    <option value="convencional" <?= ($risk['applies_to'] ?? '') === 'convencional' ? 'selected' : '' ?>>Convencional</option>
                                                                    <option value="scrum" <?= ($risk['applies_to'] ?? '') === 'scrum' ? 'selected' : '' ?>>Scrum</option>
                                                                </select>
                                                            </label>
                                                        </div>
                                                        <div class="catalog-meta">Código: <?= htmlspecialchars($risk['code']) ?></div>
                                                    </div>
                                                    <label class="catalog-field">Probabilidad
                                                        <input type="number" name="severity_base" min="1" max="5" value="<?= (int) ($risk['severity_base'] ?? 1) ?>" form="risk-update-<?= htmlspecialchars($risk['code']) ?>">
                                                    </label>
                                                    <div class="catalog-impact compact-impact">
                                                        <div class="catalog-impact-grid">
                                                            <label class="toggle-switch toggle-switch--compact catalog-toggle">
                                                                <span class="toggle-label">Alcance</span>
                                                                <input type="checkbox" name="impact_scope" <?= !empty($risk['impact_scope']) ? 'checked' : '' ?> form="risk-update-<?= htmlspecialchars($risk['code']) ?>">
                                                                <span class="toggle-track" aria-hidden="true"></span>
                                                            </label>
                                                            <label class="toggle-switch toggle-switch--compact catalog-toggle">
                                                                <span class="toggle-label">Tiempo</span>
                                                                <input type="checkbox" name="impact_time" <?= !empty($risk['impact_time']) ? 'checked' : '' ?> form="risk-update-<?= htmlspecialchars($risk['code']) ?>">
                                                                <span class="toggle-track" aria-hidden="true"></span>
                                                            </label>
                                                            <label class="toggle-switch toggle-switch--compact catalog-toggle">
                                                                <span class="toggle-label">Costo</span>
                                                                <input type="checkbox" name="impact_cost" <?= !empty($risk['impact_cost']) ? 'checked' : '' ?> form="risk-update-<?= htmlspecialchars($risk['code']) ?>">
                                                                <span class="toggle-track" aria-hidden="true"></span>
                                                            </label>
                                                            <label class="toggle-switch toggle-switch--compact catalog-toggle">
                                                                <span class="toggle-label">Calidad</span>
                                                                <input type="checkbox" name="impact_quality" <?= !empty($risk['impact_quality']) ? 'checked' : '' ?> form="risk-update-<?= htmlspecialchars($risk['code']) ?>">
                                                                <span class="toggle-track" aria-hidden="true"></span>
                                                            </label>
                                                            <label class="toggle-switch toggle-switch--compact catalog-toggle">
                                                                <span class="toggle-label">Legal</span>
                                                                <input type="checkbox" name="impact_legal" <?= !empty($risk['impact_legal']) ? 'checked' : '' ?> form="risk-update-<?= htmlspecialchars($risk['code']) ?>">
                                                                <span class="toggle-track" aria-hidden="true"></span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <?php
                                                        $impactCount = array_sum([
                                                            !empty($risk['impact_scope']) ? 1 : 0,
                                                            !empty($risk['impact_time']) ? 1 : 0,
                                                            !empty($risk['impact_cost']) ? 1 : 0,
                                                            !empty($risk['impact_quality']) ? 1 : 0,
                                                            !empty($risk['impact_legal']) ? 1 : 0,
                                                        ]);
                                                        $severity = (int) ($risk['severity_base'] ?? 1);
                                                        $riskScore = max(1, $impactCount) * $severity;
                                                        $riskLevel = $riskScore >= 10 ? 'Alto' : ($riskScore >= 5 ? 'Medio' : 'Bajo');
                                                        $riskLevelClass = $riskLevel === 'Alto' ? 'risk-level-high' : ($riskLevel === 'Medio' ? 'risk-level-mid' : 'risk-level-low');
                                                    ?>
                                                    <div class="risk-level">
                                                        <span class="risk-level-pill <?= $riskLevelClass ?>"><?= $riskLevel ?></span>
                                                        <span class="risk-level-meta"><?= $riskScore ?> pts</span>
                                                    </div>
                                                    <label class="toggle-switch toggle-switch--compact toggle-switch--state">
                                                        <input type="checkbox" name="active" <?= !empty($risk['active']) ? 'checked' : '' ?> form="risk-update-<?= htmlspecialchars($risk['code']) ?>">
                                                        <span class="toggle-track" aria-hidden="true"></span>
                                                        <span class="toggle-state" aria-hidden="true"></span>
                                                    </label>
                                                    <div class="catalog-card-actions">
                                                        <button class="btn secondary sm" type="submit" form="risk-update-<?= htmlspecialchars($risk['code']) ?>">Actualizar</button>
                                                        <button class="btn ghost danger sm" type="submit" form="risk-delete-<?= htmlspecialchars($risk['code']) ?>">Borrar</button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($riskCatalog)): ?>
                                    <p class="muted">Aún no hay riesgos en el catálogo.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif ($domainKey === 'sistema'): ?>
                        <div class="catalog-stack">
                            <div class="card config-card stretch">
                                <div class="toolbar">
                                    <div>
                                        <p class="badge neutral" style="margin:0;">Datos maestros</p>
                                        <h3 style="margin:6px 0 2px 0;">Rutas de datos base</h3>
                                    </div>
                                    <small style="color: var(--text-secondary);">Archivos de referencia y esquema base del sistema.</small>
                                </div>
                                <div class="card-content">
                                    <form method="POST" action="/config/theme" enctype="multipart/form-data" class="config-form-grid compact-grid">
                                        <div class="input-stack">
                                            <label>Archivo de datos principal</label>
                                            <input name="data_file" value="<?= htmlspecialchars($configData['master_files']['data_file']) ?>" required>
                                        </div>
                                        <div class="input-stack">
                                            <label>Archivo de esquema / bootstrap</label>
                                            <input name="schema_file" value="<?= htmlspecialchars($configData['master_files']['schema_file']) ?>" required>
                                        </div>
                                        <div class="form-footer full-span">
                                            <div></div>
                                            <button class="btn primary" type="submit">Guardar y aplicar</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php $tables = $domainTables[$domainKey] ?? []; ?>
                        <?php if (!empty($tables)): ?>
                            <div class="catalog-domain-grid">
                                <?php foreach ($tables as $table => $tableData): ?>
                                    <div class="catalog-table-block">
                                        <div class="catalog-table-header">
                                            <div>
                                                <p class="badge neutral" style="margin:0;">Catálogo base</p>
                                                <h4 style="margin:4px 0 0 0;"><?= htmlspecialchars($tableData['label']) ?></h4>
                                            </div>
                                            <span class="badge neutral"><?= count($tableData['items']) ?> ítems</span>
                                        </div>
                                        <form method="POST" action="/config/master-files/create" class="catalog-table-form">
                                            <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                                            <input name="code" placeholder="Código" required>
                                            <input name="label" placeholder="Etiqueta" required>
                                            <button class="btn primary sm" type="submit">Agregar</button>
                                        </form>
                                        <div class="catalog-table">
                                            <div class="catalog-table-row catalog-table-row--header">
                                                <span>Código</span>
                                                <span>Etiqueta</span>
                                                <span>Estado</span>
                                                <span>Acciones</span>
                                            </div>
                                            <?php foreach($tableData['items'] as $item): ?>
                                                <form id="base-update-<?= htmlspecialchars($table) ?>-<?= (int) $item['id'] ?>" method="POST" action="/config/master-files/update"></form>
                                                <form id="base-delete-<?= htmlspecialchars($table) ?>-<?= (int) $item['id'] ?>" method="POST" action="/config/master-files/delete" onsubmit="return confirm('Eliminar entrada?');">
                                                    <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                                                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                                </form>
                                                <div class="catalog-table-row">
                                                    <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>" form="base-update-<?= htmlspecialchars($table) ?>-<?= (int) $item['id'] ?>">
                                                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>" form="base-update-<?= htmlspecialchars($table) ?>-<?= (int) $item['id'] ?>">
                                                    <label class="catalog-field">Código
                                                        <input name="code" value="<?= htmlspecialchars($item['code']) ?>" form="base-update-<?= htmlspecialchars($table) ?>-<?= (int) $item['id'] ?>">
                                                    </label>
                                                    <label class="catalog-field">Etiqueta
                                                        <input name="label" value="<?= htmlspecialchars($item['label']) ?>" form="base-update-<?= htmlspecialchars($table) ?>-<?= (int) $item['id'] ?>">
                                                    </label>
                                                    <label class="toggle-switch toggle-switch--compact toggle-switch--state catalog-table-toggle">
                                                        <span class="toggle-label">Activo</span>
                                                        <input type="checkbox" checked disabled>
                                                        <span class="toggle-track" aria-hidden="true"></span>
                                                        <span class="toggle-state" aria-hidden="true"></span>
                                                    </label>
                                                    <div class="catalog-table-actions">
                                                        <button class="btn secondary sm" type="submit" form="base-update-<?= htmlspecialchars($table) ?>-<?= (int) $item['id'] ?>">Actualizar</button>
                                                        <button class="btn ghost danger sm" type="submit" form="base-delete-<?= htmlspecialchars($table) ?>-<?= (int) $item['id'] ?>">Borrar</button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if (empty($tableData['items'])): ?>
                                                <div class="catalog-table-row catalog-table-row--empty">
                                                    <span class="muted">Sin entradas.</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="catalog-empty">Sin catálogos configurados en este dominio.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
</section>
