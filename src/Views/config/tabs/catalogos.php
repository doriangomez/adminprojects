<section id="panel-catalogos" class="tab-panel">
    <div class="section-grid config-columns">
        <div class="card config-card stretch">
            <div class="toolbar">
                <div>
                    <p class="badge neutral" style="margin:0;">Datos maestros</p>
                    <h3 style="margin:6px 0 2px 0;">Rutas de datos base</h3>
                </div>
                <small style="color: var(--text-secondary);">Archivos de referencia y esquema base del sistema.</small>
            </div>
            <div class="card-content">
                <form method="POST" action="/project/public/config/theme" enctype="multipart/form-data" class="config-form-grid">
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
        <div class="card config-card stretch">
            <div class="toolbar">
                <div>
                    <p class="badge neutral" style="margin:0;">Catálogo maestro</p>
                    <h3 style="margin:6px 0 2px 0;">Riesgos centralizados</h3>
                    <small class="text-muted">Checklist único para todos los proyectos (no editable desde proyectos).</small>
                </div>
            </div>
            <div class="card-content">
                <form method="POST" action="/project/public/config/risk-catalog/create" class="config-form-grid">
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
                    <label>Severidad base (1-5)
                        <input type="number" name="severity_base" min="1" max="5" value="3" required>
                    </label>
                    <fieldset class="pillset full-span" style="border:1px solid var(--border); padding:8px; border-radius:10px;">
                        <legend style="font-weight:700; color:var(--text-secondary); margin:0 0 6px 0;">Impacto</legend>
                        <label class="pill"><input type="checkbox" name="impact_scope"> Alcance</label>
                        <label class="pill"><input type="checkbox" name="impact_time"> Tiempo</label>
                        <label class="pill"><input type="checkbox" name="impact_cost"> Costo</label>
                        <label class="pill"><input type="checkbox" name="impact_quality"> Calidad</label>
                        <label class="pill"><input type="checkbox" name="impact_legal"> Legal</label>
                    </fieldset>
                    <label class="option">
                        <input type="checkbox" name="active" checked> Activo
                    </label>
                    <div class="form-footer">
                        <span class="text-muted">Se agrega al catálogo central y queda disponible en proyectos.</span>
                        <button class="btn primary" type="submit">Agregar riesgo</button>
                    </div>
                </form>
                <div style="margin-top:16px; display:flex; flex-direction:column; gap:10px;">
                    <?php foreach ($risksByCategory as $category => $risks): ?>
                        <div style="border:1px solid var(--border); border-radius:12px; padding:12px; background:color-mix(in srgb, var(--surface) 92%, var(--background) 8%);">
                            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:6px;">
                                <strong><?= htmlspecialchars($category) ?></strong>
                                <span class="pill soft-slate"><?= count($risks) ?> riesgos</span>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:8px;">
                                <?php foreach ($risks as $risk): ?>
                                    <form method="POST" action="/project/public/config/risk-catalog/update" class="pill" style="display:flex; flex-wrap:wrap; gap:8px; align-items:center; justify-content:space-between; background:color-mix(in srgb, var(--surface) 90%, var(--background) 10%); border:1px solid var(--border); padding:10px; border-radius:10px;">
                                        <input type="hidden" name="code" value="<?= htmlspecialchars($risk['code']) ?>">
                                        <div style="flex:1 1 180px; min-width:140px;">
                                            <strong><?= htmlspecialchars($risk['label']) ?></strong>
                                            <div class="subtext"><?= htmlspecialchars($risk['code']) ?></div>
                                        </div>
                                        <div style="flex:1 1 160px; min-width:120px;">
                                            <label class="subtext">Categoría
                                                <input name="category" value="<?= htmlspecialchars($risk['category']) ?>">
                                            </label>
                                        </div>
                                        <div style="flex:1 1 200px; min-width:160px;">
                                            <label class="subtext">Descripción
                                                <input name="label" value="<?= htmlspecialchars($risk['label']) ?>">
                                            </label>
                                        </div>
                                        <label class="subtext">Aplica a
                                            <select name="applies_to">
                                                <option value="ambos" <?= ($risk['applies_to'] ?? '') === 'ambos' ? 'selected' : '' ?>>Ambos</option>
                                                <option value="convencional" <?= ($risk['applies_to'] ?? '') === 'convencional' ? 'selected' : '' ?>>Convencional</option>
                                                <option value="scrum" <?= ($risk['applies_to'] ?? '') === 'scrum' ? 'selected' : '' ?>>Scrum</option>
                                            </select>
                                        </label>
                                        <label class="subtext">Severidad
                                            <input type="number" name="severity_base" min="1" max="5" value="<?= (int) ($risk['severity_base'] ?? 1) ?>">
                                        </label>
                                        <div class="pillset" style="gap:6px;">
                                            <label class="pill"><input type="checkbox" name="impact_scope" <?= !empty($risk['impact_scope']) ? 'checked' : '' ?>> Alcance</label>
                                            <label class="pill"><input type="checkbox" name="impact_time" <?= !empty($risk['impact_time']) ? 'checked' : '' ?>> Tiempo</label>
                                            <label class="pill"><input type="checkbox" name="impact_cost" <?= !empty($risk['impact_cost']) ? 'checked' : '' ?>> Costo</label>
                                            <label class="pill"><input type="checkbox" name="impact_quality" <?= !empty($risk['impact_quality']) ? 'checked' : '' ?>> Calidad</label>
                                            <label class="pill"><input type="checkbox" name="impact_legal" <?= !empty($risk['impact_legal']) ? 'checked' : '' ?>> Legal</label>
                                            <label class="pill"><input type="checkbox" name="active" <?= !empty($risk['active']) ? 'checked' : '' ?>> Activo</label>
                                        </div>
                                        <div style="display:flex; gap:6px; align-items:center;">
                                            <button class="btn secondary" type="submit">Actualizar</button>
                                        </div>
                                    </form>
                                    <form method="POST" action="/project/public/config/risk-catalog/delete" onsubmit="return confirm('¿Eliminar riesgo del catálogo? Esta acción no se puede deshacer.');">
                                        <input type="hidden" name="code" value="<?= htmlspecialchars($risk['code']) ?>">
                                        <button class="btn ghost danger" type="submit">Eliminar</button>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($riskCatalog)): ?>
                        <p class="muted">Aún no hay riesgos en el catálogo.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <section class="card config-card stretch">
        <div class="toolbar">
            <div>
                <p class="badge neutral" style="margin:0;">Catálogos maestros</p>
                <h3 style="margin:6px 0 0 0;">CRUD seguro sobre catálogos base</h3>
            </div>
            <small style="color: var(--text-secondary);">Solo altas, ediciones y bajas de catálogos operativos</small>
        </div>
        <div class="config-columns cards-grid">
            <?php foreach($masterData as $table => $items): ?>
                <div class="card subtle-card stretch">
                    <div class="toolbar">
                        <div>
                            <p class="badge neutral" style="margin:0;"><?= htmlspecialchars($table) ?></p>
                            <h4 style="margin:4px 0 0 0;"><?= ucwords(str_replace('_', ' ', $table)) ?></h4>
                        </div>
                    </div>
                    <div class="card-content">
                        <form method="POST" action="/project/public/config/master-files/create" class="config-form-grid tight">
                            <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                            <input name="code" placeholder="Código" required>
                            <input name="label" placeholder="Etiqueta" required>
                            <div class="form-footer">
                                <div></div>
                                <button class="btn primary" type="submit">Agregar</button>
                            </div>
                        </form>
                        <div class="table-wrapper">
                            <table>
                                <thead><tr><th>Código</th><th>Etiqueta</th><th></th></tr></thead>
                                <tbody>
                                    <?php foreach($items as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['code']) ?></td>
                                            <td><?= htmlspecialchars($item['label']) ?></td>
                                            <td>
                                                <div class="table-actions">
                                                    <form method="POST" action="/project/public/config/master-files/update" class="inline">
                                                        <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                                        <input name="code" value="<?= htmlspecialchars($item['code']) ?>" style="width:90px;">
                                                        <input name="label" value="<?= htmlspecialchars($item['label']) ?>" style="width:120px;">
                                                        <button class="btn secondary" type="submit">Actualizar</button>
                                                    </form>
                                                    <form method="POST" action="/project/public/config/master-files/delete" onsubmit="return confirm('Eliminar entrada?');">
                                                        <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                                        <button class="btn ghost" type="submit">Borrar</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</section>
