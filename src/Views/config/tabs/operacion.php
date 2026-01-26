<section id="panel-operacion" class="tab-panel">
    <form method="POST" action="/project/public/config/theme" enctype="multipart/form-data">
        <div class="card config-card">
            <div class="card-content">
                <header class="section-header">
                    <h3 style="margin:0;">Operación y ejecución</h3>
                    <p class="text-muted">Umbrales, semaforización y metodologías para controlar la entrega.</p>
                </header>
                <div class="config-form-grid">
                    <div class="form-block">
                        <span class="section-label">Umbrales de avance y semaforización</span>
                        <div class="rules-grid">
                            <label>Avance – umbral amarillo (%)
                                <input type="number" name="progress_yellow" step="0.1" value="<?= htmlspecialchars($configData['operational_rules']['semaforization']['progress']['yellow_below']) ?>">
                            </label>
                            <label>Avance – umbral rojo (%)
                                <input type="number" name="progress_red" step="0.1" value="<?= htmlspecialchars($configData['operational_rules']['semaforization']['progress']['red_below']) ?>">
                            </label>
                            <label>Horas – umbral amarillo (desvío)
                                <input type="number" name="hours_yellow" step="0.01" value="<?= htmlspecialchars($configData['operational_rules']['semaforization']['hours']['yellow_above']) ?>">
                                <small class="subtext">Ej. 0.05 = 5%</small>
                            </label>
                            <label>Horas – umbral rojo (desvío)
                                <input type="number" name="hours_red" step="0.01" value="<?= htmlspecialchars($configData['operational_rules']['semaforization']['hours']['red_above']) ?>">
                            </label>
                            <label>Costo – umbral amarillo (desvío)
                                <input type="number" name="cost_yellow" step="0.01" value="<?= htmlspecialchars($configData['operational_rules']['semaforization']['cost']['yellow_above']) ?>">
                            </label>
                            <label>Costo – umbral rojo (desvío)
                                <input type="number" name="cost_red" step="0.01" value="<?= htmlspecialchars($configData['operational_rules']['semaforization']['cost']['red_above']) ?>">
                            </label>
                        </div>
                    </div>

                    <div class="form-block">
                        <span class="section-label">Metodologías y fases</span>
                        <div class="input-stack">
                            <label>Metodologías habilitadas (separadas por coma)</label>
                            <input name="methodologies" value="<?= htmlspecialchars(implode(', ', $configData['delivery']['methodologies'])) ?>" placeholder="scrum, cascada, kanban">
                        </div>
                        <div class="input-stack">
                            <label>Fases por metodología (JSON)</label>
                            <textarea name="phases_json" rows="4"><?= htmlspecialchars(json_encode($configData['delivery']['phases'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></textarea>
                            <small class="subtext">Estructura: {"scrum": ["descubrimiento", "backlog"], "cascada": [...]}</small>
                        </div>
                        <div class="input-stack">
                            <label>Riesgos</label>
                            <p class="subtext" style="margin:0;">El catálogo maestro se gestiona desde la pestaña Catálogos.</p>
                        </div>
                    </div>

                </div>
                <div class="form-footer">
                    <span class="text-muted">Aplican a todos los proyectos en operación.</span>
                    <button class="btn primary" type="submit">Guardar y aplicar</button>
                </div>
            </div>
        </div>
    </form>
</section>
