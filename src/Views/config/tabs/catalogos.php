<?php
$totalBaseItems = 0;
foreach ($masterData as $items) {
    $totalBaseItems += count($items);
}
$catalogDomains = [
    [
        'key' => 'metodologias',
        'title' => 'Metodologías',
        'icon' => '🧩',
        'count' => 0,
        'open' => false,
    ],
    [
        'key' => 'fases',
        'title' => 'Fases',
        'icon' => '🧭',
        'count' => 0,
        'open' => false,
    ],
    [
        'key' => 'subfases',
        'title' => 'Subfases',
        'icon' => '🧷',
        'count' => 0,
        'open' => false,
    ],
    [
        'key' => 'tipos_proyecto',
        'title' => 'Tipos de proyecto',
        'icon' => '🏗️',
        'count' => 0,
        'open' => false,
    ],
    [
        'key' => 'riesgos',
        'title' => 'Riesgos (matriz)',
        'icon' => '⚠️',
        'count' => count($riskCatalog),
        'open' => true,
    ],
    [
        'key' => 'impactos',
        'title' => 'Impactos',
        'icon' => '💥',
        'count' => 0,
        'open' => false,
    ],
    [
        'key' => 'probabilidades',
        'title' => 'Probabilidades',
        'icon' => '🎲',
        'count' => 0,
        'open' => false,
    ],
    [
        'key' => 'niveles',
        'title' => 'Niveles',
        'icon' => '📊',
        'count' => 0,
        'open' => false,
    ],
    [
        'key' => 'roles',
        'title' => 'Roles',
        'icon' => '👤',
        'count' => 0,
        'open' => false,
    ],
    [
        'key' => 'areas',
        'title' => 'Áreas',
        'icon' => '🏢',
        'count' => 0,
        'open' => false,
    ],
    [
        'key' => 'cargos',
        'title' => 'Cargos',
        'icon' => '🪪',
        'count' => 0,
        'open' => false,
    ],
    [
        'key' => 'tipos_talento',
        'title' => 'Tipos de talento',
        'icon' => '🧑‍💼',
        'count' => 0,
        'open' => false,
    ],
    [
        'key' => 'otros',
        'title' => 'Datos base del sistema',
        'icon' => '🗂️',
        'count' => $totalBaseItems,
        'open' => true,
    ],
];
$catalogDomainMap = [];
foreach ($catalogDomains as $domain) {
    $catalogDomainMap[$domain['key']] = $domain;
}
$catalogGroups = [
    [
        'title' => 'Catálogos de Proyecto',
        'icon' => '📁',
        'items' => ['metodologias', 'fases', 'subfases', 'tipos_proyecto'],
    ],
    [
        'title' => 'Catálogos de Riesgo',
        'icon' => '⚠️',
        'items' => ['riesgos', 'impactos', 'probabilidades', 'niveles'],
    ],
    [
        'title' => 'Catálogos Organizacionales',
        'icon' => '👥',
        'items' => ['roles', 'areas', 'cargos', 'tipos_talento', 'otros'],
    ],
];
?>
<section id="panel-catalogos" class="tab-panel">
    <div class="catalog-panel">
        <?php foreach ($catalogGroups as $group): ?>
            <div class="catalog-group-card">
                <div class="catalog-group-header">
                    <div class="catalog-group-title">
                        <span class="catalog-group-icon"><?= $group['icon'] ?></span>
                        <strong><?= htmlspecialchars($group['title']) ?></strong>
                    </div>
                    <span class="badge neutral"><?= count($group['items']) ?> catálogos</span>
                </div>
                <div class="catalog-group-grid">
                    <?php if (!empty($group['items'])): ?>
                        <?php foreach ($group['items'] as $itemKey): ?>
                            <?php if (!isset($catalogDomainMap[$itemKey])) { continue; } ?>
                            <?php $domain = $catalogDomainMap[$itemKey]; ?>
                            <a class="catalog-mini-card" href="#catalog-section-<?= htmlspecialchars($domain['key']) ?>">
                                <span class="catalog-mini-icon"><?= $domain['icon'] ?></span>
                                <span class="catalog-mini-title"><?= htmlspecialchars($domain['title']) ?></span>
                                <span class="badge neutral"><?= $domain['count'] ?> ítems</span>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="catalog-mini-card muted">Sin catálogos asignados</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="catalog-section-list">
        <?php foreach ($catalogDomains as $domain): ?>
            <details id="catalog-section-<?= htmlspecialchars($domain['key']) ?>" class="catalog-section-card" <?= $domain['open'] ? 'open' : '' ?>>
                <summary>
                    <div class="catalog-section-title">
                        <span class="catalog-section-icon"><?= $domain['icon'] ?></span>
                        <strong><?= htmlspecialchars($domain['title']) ?></strong>
                    </div>
                    <span class="badge neutral"><?= $domain['count'] ?> ítems</span>
                </summary>
                <div class="catalog-section-body">
                    <?php if ($domain['key'] === 'riesgos'): ?>
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
                                    <form method="POST" action="/project/public/config/risk-catalog/create" class="config-form-grid compact-grid">
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
                                                <form id="risk-update-<?= htmlspecialchars($risk['code']) ?>" method="POST" action="/project/public/config/risk-catalog/update"></form>
                                                <form id="risk-delete-<?= htmlspecialchars($risk['code']) ?>" method="POST" action="/project/public/config/risk-catalog/delete" onsubmit="return confirm('¿Eliminar riesgo del catálogo? Esta acción no se puede deshacer.');">
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
                    <?php elseif ($domain['key'] === 'otros'): ?>
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
                                    <form method="POST" action="/project/public/config/theme" enctype="multipart/form-data" class="config-form-grid compact-grid">
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
                            <div class="catalog-base-grid">
                                <?php foreach($masterData as $table => $items): ?>
                                    <div class="card subtle-card stretch catalog-block">
                                        <div class="toolbar">
                                            <div>
                                                <p class="badge neutral" style="margin:0;">Catálogo base</p>
                                                <h4 style="margin:4px 0 0 0;"><?= ucwords(str_replace('_', ' ', $table)) ?></h4>
                                            </div>
                                        </div>
                                        <div class="card-content">
                                            <form method="POST" action="/project/public/config/master-files/create" class="config-form-grid compact-grid">
                                                <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                                                <input name="code" placeholder="Código" required>
                                                <input name="label" placeholder="Etiqueta" required>
                                                <div class="form-footer">
                                                    <div></div>
                                                    <button class="btn primary" type="submit">Agregar</button>
                                                </div>
                                            </form>
                                            <div class="catalog-grid">
                                                <?php foreach($items as $item): ?>
                                                    <form id="base-update-<?= htmlspecialchars($table) ?>-<?= (int) $item['id'] ?>" method="POST" action="/project/public/config/master-files/update"></form>
                                                    <form id="base-delete-<?= htmlspecialchars($table) ?>-<?= (int) $item['id'] ?>" method="POST" action="/project/public/config/master-files/delete" onsubmit="return confirm('Eliminar entrada?');">
                                                        <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                                    </form>
                                                    <div class="catalog-card">
                                                        <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>" form="base-update-<?= htmlspecialchars($table) ?>-<?= (int) $item['id'] ?>">
                                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>" form="base-update-<?= htmlspecialchars($table) ?>-<?= (int) $item['id'] ?>">
                                                        <div class="catalog-card-header">
                                                            <div>
                                                                <div class="catalog-title"><?= htmlspecialchars($item['label']) ?></div>
                                                                <div class="catalog-meta">Código: <?= htmlspecialchars($item['code']) ?></div>
                                                            </div>
                                                            <label class="toggle-switch toggle-switch--state">
                                                                <input type="checkbox" checked disabled>
                                                                <span class="toggle-track" aria-hidden="true"></span>
                                                                <span class="toggle-state" aria-hidden="true"></span>
                                                            </label>
                                                        </div>
                                                        <div class="catalog-fields">
                                                            <label class="catalog-field">Código
                                                                <input name="code" value="<?= htmlspecialchars($item['code']) ?>" form="base-update-<?= htmlspecialchars($table) ?>-<?= (int) $item['id'] ?>">
                                                            </label>
                                                            <label class="catalog-field">Etiqueta
                                                                <input name="label" value="<?= htmlspecialchars($item['label']) ?>" form="base-update-<?= htmlspecialchars($table) ?>-<?= (int) $item['id'] ?>">
                                                            </label>
                                                        </div>
                                                        <div class="catalog-card-actions">
                                                            <button class="btn secondary sm" type="submit" form="base-update-<?= htmlspecialchars($table) ?>-<?= (int) $item['id'] ?>">Actualizar</button>
                                                            <button class="btn ghost danger sm" type="submit" form="base-delete-<?= htmlspecialchars($table) ?>-<?= (int) $item['id'] ?>">Borrar</button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="catalog-empty">Sin catálogos configurados en este dominio.</p>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
</section>
