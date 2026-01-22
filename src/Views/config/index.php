<style>
    .config-tabs {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .config-tabs input[type="radio"] {
        display: none;
    }
    .tab-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 8px;
        border-radius: 14px;
        border: 1px solid var(--border);
        background: var(--surface);
    }
    .tab-nav label {
        cursor: pointer;
        padding: 8px 14px;
        border-radius: 10px;
        font-weight: 600;
        color: var(--text-primary);
        background: var(--surface);
        border: 1px solid var(--background);
    }
    #tab-identidad:checked ~ .tab-nav label[for="tab-identidad"],
    #tab-apariencia:checked ~ .tab-nav label[for="tab-apariencia"],
    #tab-operacion:checked ~ .tab-nav label[for="tab-operacion"],
    #tab-gobierno:checked ~ .tab-nav label[for="tab-gobierno"],
    #tab-catalogos:checked ~ .tab-nav label[for="tab-catalogos"] {
        background: var(--primary);
        color: var(--text-primary);
        border-color: color-mix(in srgb, var(--primary) 85%, var(--secondary) 15%);
    }
    .tab-panels {
        display: block;
    }
    .tab-panel {
        display: none;
    }
    #tab-identidad:checked ~ .tab-panels #panel-identidad,
    #tab-apariencia:checked ~ .tab-panels #panel-apariencia,
    #tab-operacion:checked ~ .tab-panels #panel-operacion,
    #tab-gobierno:checked ~ .tab-panels #panel-gobierno,
    #tab-catalogos:checked ~ .tab-panels #panel-catalogos {
        display: block;
    }
    .config-card {
        background: var(--surface);
        border: 1px solid var(--border);
        box-shadow: none;
    }
    .section-header {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin-bottom: 12px;
    }
    .section-header p {
        margin: 0;
    }
    .section-grid-two {
        display: grid;
        gap: 16px;
        grid-template-columns: minmax(0, 1.4fr) minmax(0, 0.6fr);
        align-items: start;
    }
    .preview-card {
        position: sticky;
        top: 16px;
    }
    .toggle-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .toggle-switch {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
        color: var(--text-primary);
    }
    .toggle-switch input {
        display: none;
    }
    .toggle-switch .toggle-track {
        width: 42px;
        height: 24px;
        background: var(--border);
        border-radius: 999px;
        position: relative;
        transition: background 0.2s ease;
        border: 1px solid color-mix(in srgb, var(--border) 80%, var(--background));
    }
    .toggle-switch .toggle-track::after {
        content: '';
        position: absolute;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: var(--surface);
        border: 1px solid var(--border);
        top: 2px;
        left: 2px;
        transition: transform 0.2s ease;
        box-shadow: 0 1px 2px color-mix(in srgb, var(--text-primary) 18%, var(--background));
    }
    .toggle-switch input:checked + .toggle-track {
        background: var(--primary);
    }
    .toggle-switch input:checked + .toggle-track::after {
        transform: translateX(18px);
    }
    .governance-panel {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }
    .governance-blocks {
        display: flex;
        flex-direction: column;
        gap: 28px;
    }
    .governance-block {
        border-radius: 18px;
    }
    .governance-block-header {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin-bottom: 16px;
    }
    .governance-block-title {
        font-size: 20px;
        margin: 0;
        color: var(--text-primary);
    }
    .governance-block-subtitle {
        margin: 0;
        color: var(--text-secondary);
        font-size: 14px;
    }
    .governance-card-body {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }
    .governance-switches,
    .governance-modules,
    .governance-access-section,
    .governance-document-section {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .governance-rules {
        display: grid;
        gap: 12px;
    }
    .governance-rule {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 12px 14px;
        border-radius: 14px;
        border: 1px solid var(--border);
        background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%);
    }
    .governance-rule-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .governance-rule-title {
        font-weight: 600;
        color: var(--text-primary);
    }
    .governance-rule-desc {
        margin: 0;
        color: var(--text-secondary);
        font-size: 13px;
    }
    .governance-module {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .governance-module-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 12px 14px;
        border-radius: 14px;
        border: 1px solid var(--border);
        background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%);
    }
    .governance-module-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .governance-module-title {
        font-weight: 600;
        color: var(--text-primary);
    }
    .governance-module-desc {
        margin: 0;
        color: var(--text-secondary);
        font-size: 13px;
    }
    .toggle-switch--solo {
        margin-left: auto;
    }
    .toggle-switch--solo .toggle-track {
        width: 50px;
        height: 28px;
    }
    .toggle-switch--solo .toggle-track::after {
        width: 22px;
        height: 22px;
        top: 2px;
        left: 2px;
    }
    .toggle-switch--solo input:checked + .toggle-track::after {
        transform: translateX(22px);
    }
    .governance-block--critical {
        padding: 6px;
    }
    .governance-block--critical .card-content {
        padding: 24px;
    }
    .governance-block--critical .governance-block-title {
        font-size: 22px;
    }
    .governance-panel .form-footer {
        border-top: 1px solid var(--border);
        margin-top: 16px;
        padding-top: 16px;
    }
    .permission-groups {
        display: grid;
        gap: 16px;
    }
    .permission-group {
        border-radius: 14px;
        border: 1px dashed var(--border);
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: color-mix(in srgb, var(--surface) 95%, var(--background) 5%);
    }
    .permission-group-title {
        margin: 0;
        font-size: 14px;
        font-weight: 700;
        color: var(--text-primary);
    }
    .permission-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(220px, 1fr));
        gap: 8px 12px;
        justify-items: start;
    }
    .permission-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 14px;
        color: var(--text-primary);
        margin: 0;
        line-height: 1.2;
        white-space: nowrap;
    }
    .permission-item input {
        margin: 0;
        flex: 0 0 auto;
    }
    .role-accordion {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .role-panel {
        border-radius: 16px;
        border: 1px solid var(--border);
        background: color-mix(in srgb, var(--surface) 96%, var(--background) 4%);
        padding: 0;
        overflow: hidden;
    }
    .role-panel summary {
        list-style: none;
        cursor: pointer;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        font-weight: 700;
    }
    .role-panel summary::-webkit-details-marker {
        display: none;
    }
    .role-panel-body {
        padding: 16px;
        border-top: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .user-accordion {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .user-card {
        border-radius: 16px;
        border: 1px solid var(--border);
        background: var(--surface);
        overflow: hidden;
    }
    .user-card summary {
        list-style: none;
        cursor: pointer;
        padding: 14px 16px;
        display: grid;
        grid-template-columns: minmax(140px, 1.2fr) minmax(180px, 1.4fr) minmax(120px, 1fr) minmax(90px, 0.6fr) auto;
        gap: 12px;
        align-items: center;
    }
    .user-card summary::-webkit-details-marker {
        display: none;
    }
    .user-card summary span {
        font-size: 14px;
        color: var(--text-primary);
    }
    .user-expand {
        font-weight: 600;
        color: var(--primary);
    }
    .user-details {
        border-top: 1px solid var(--border);
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .user-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: flex-end;
    }
    .json-collapse {
        border-radius: 14px;
        border: 1px dashed var(--border);
        background: color-mix(in srgb, var(--surface) 95%, var(--background) 5%);
        padding: 12px 14px;
    }
    .json-collapse summary {
        cursor: pointer;
        font-weight: 600;
        color: var(--text-primary);
        list-style: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .json-collapse summary::-webkit-details-marker {
        display: none;
    }
    .json-collapse .input-stack {
        margin-top: 12px;
    }
    @media (max-width: 980px) {
        .section-grid-two {
            grid-template-columns: 1fr;
        }
        .preview-card {
            position: static;
        }
        .user-card summary {
            grid-template-columns: 1fr;
        }
        .user-expand {
            justify-self: start;
        }
        .permission-list {
            grid-template-columns: 1fr;
        }
    }
</style>

<section class="section-grid">
    <div class="config-tabs">
        <input type="radio" name="config_tab" id="tab-identidad" checked>
        <input type="radio" name="config_tab" id="tab-apariencia">
        <input type="radio" name="config_tab" id="tab-operacion">
        <input type="radio" name="config_tab" id="tab-gobierno">
        <input type="radio" name="config_tab" id="tab-catalogos">

        <div class="toolbar">
            <div>
                <p class="badge neutral" style="margin:0;">Configuración</p>
                <h2 style="margin:6px 0 2px 0;">Ordena la experiencia de administración</h2>
                <small class="text-muted">Identidad, apariencia, operación y gobierno en una sola vista clara.</small>
            </div>
            <?php if(!empty($savedMessage)): ?>
                <span class="badge success" data-theme-saved>Guardado</span>
            <?php else: ?>
                <span class="badge success" data-theme-saved hidden>Guardado</span>
            <?php endif; ?>
        </div>

        <div class="tab-nav">
            <label for="tab-identidad">Identidad</label>
            <label for="tab-apariencia">Apariencia</label>
            <label for="tab-operacion">Operación</label>
            <label for="tab-gobierno">Gobierno</label>
            <label for="tab-catalogos">Catálogos</label>
        </div>

        <div class="tab-panels">
            <section id="panel-identidad" class="tab-panel">
                <form method="POST" action="/project/public/config/theme" enctype="multipart/form-data">
                    <div class="card config-card">
                        <div class="card-content">
                            <header class="section-header">
                                <h3 style="margin:0;">Identidad del sistema</h3>
                                <p class="text-muted">Logo y mensajes institucionales visibles para todos los usuarios.</p>
                            </header>
                            <div class="config-form-grid">
                                <div class="form-block">
                                    <span class="section-label">Logo institucional</span>
                                    <div class="input-stack">
                                        <label>Logo (sube uno nuevo o pega una URL)</label>
                                        <input type="file" name="logo_file" accept="image/*">
                                        <input name="logo" value="<?= htmlspecialchars($configData['theme']['logo']) ?>" placeholder="https://..png">
                                    </div>
                                </div>
                                <div class="form-block">
                                    <span class="section-label">Mensajes de acceso</span>
                                    <div class="input-stack">
                                        <label>Mensaje principal de login</label>
                                        <input name="login_hero" value="<?= htmlspecialchars($configData['theme']['login_hero']) ?>" placeholder="Titular inspirador">
                                    </div>
                                    <div class="input-stack">
                                        <label>Copete</label>
                                        <textarea name="login_message" rows="3"><?= htmlspecialchars($configData['theme']['login_message']) ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="form-footer">
                                <span class="text-muted">Los cambios se aplican en todo el sistema.</span>
                                <button class="btn primary" type="submit">Guardar y aplicar</button>
                            </div>
                        </div>
                    </div>
                </form>
                <?php if(!empty($savedMessage)): ?>
                    <div class="alert success" style="margin-top:12px;">
                        <?= htmlspecialchars($savedMessage) ?>
                    </div>
                <?php endif; ?>
            </section>

            <section id="panel-apariencia" class="tab-panel">
                <form method="POST" action="/project/public/config/theme" enctype="multipart/form-data">
                    <div class="section-grid-two">
                        <div class="card config-card">
                            <div class="card-content">
                                <header class="section-header">
                                    <h3 style="margin:0;">Apariencia visual</h3>
                                    <p class="text-muted">Define tipografía y paleta institucional sin tocar código.</p>
                                </header>
                                <div class="config-form-grid">
                                    <div class="form-block">
                                        <span class="section-label">Logo institucional</span>
                                        <div class="input-stack">
                                            <label>Logo (sube uno nuevo o pega una URL)</label>
                                            <input type="file" name="logo_file" accept="image/*">
                                            <input name="logo" value="<?= htmlspecialchars($configData['theme']['logo']) ?>" placeholder="https://..png">
                                        </div>
                                    </div>
                                    <div class="form-block">
                                        <span class="section-label">Tipografía base</span>
                                        <div class="input-stack">
                                            <label>Tipografía base</label>
                                            <input name="font_family" value="<?= htmlspecialchars($configData['theme']['font_family']) ?>" placeholder="'Inter', sans-serif">
                                        </div>
                                    </div>
                                    <div class="form-block">
                                        <span class="section-label">Colores del sistema</span>
                                        <div class="palette-grid">
                                            <label>Color primario<input type="color" name="primary" value="<?= htmlspecialchars($configData['theme']['primary']) ?>"></label>
                                            <label>Color secundario<input type="color" name="secondary" value="<?= htmlspecialchars($configData['theme']['secondary']) ?>"></label>
                                            <label>Color acento<input type="color" name="accent" value="<?= htmlspecialchars($configData['theme']['accent']) ?>"></label>
                                            <label>Color fondo<input type="color" name="background" value="<?= htmlspecialchars($configData['theme']['background']) ?>"></label>
                                            <label>Color superficies<input type="color" name="surface" value="<?= htmlspecialchars($configData['theme']['surface']) ?>"></label>
                                            <label>Texto principal<input type="color" name="textPrimary" value="<?= htmlspecialchars($configData['theme']['textPrimary'] ?? $configData['theme']['text_main'] ?? '') ?>"></label>
                                            <label>Texto secundario<input type="color" name="textSecondary" value="<?= htmlspecialchars($configData['theme']['textSecondary'] ?? $configData['theme']['text_muted'] ?? '') ?>"></label>
                                            <label>Texto deshabilitado<input type="color" name="disabled" value="<?= htmlspecialchars($configData['theme']['disabled'] ?? $configData['theme']['text_disabled'] ?? $configData['theme']['text_soft'] ?? '') ?>"></label>
                                            <label>Borde<input type="color" name="border" value="<?= htmlspecialchars($configData['theme']['border'] ?? '') ?>"></label>
                                            <label>Éxito<input type="color" name="success" value="<?= htmlspecialchars($configData['theme']['success'] ?? '') ?>"></label>
                                            <label>Advertencia<input type="color" name="warning" value="<?= htmlspecialchars($configData['theme']['warning'] ?? '') ?>"></label>
                                            <label>Peligro<input type="color" name="danger" value="<?= htmlspecialchars($configData['theme']['danger'] ?? '') ?>"></label>
                                            <label>Información<input type="color" name="info" value="<?= htmlspecialchars($configData['theme']['info'] ?? '') ?>"></label>
                                            <label>Neutro<input type="color" name="neutral" value="<?= htmlspecialchars($configData['theme']['neutral'] ?? '') ?>"></label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-footer">
                                    <span class="text-muted">Se actualiza la apariencia en login y navegación.</span>
                                    <button class="btn primary" type="submit">Guardar y aplicar</button>
                                </div>
                            </div>
                        </div>

                        <div class="card config-card preview-card" style="background: color-mix(in srgb, var(--primary) 60%, var(--secondary) 40%); color:color-mix(in srgb, var(--surface) 90%, var(--secondary) 10%);">
                            <div class="card-content" style="position:relative;">
                                <div class="toolbar" style="margin-bottom:4px;">
                                    <h3 style="margin:0;">Previsualización</h3>
                                </div>
                                <p class="text-muted" style="color: color-mix(in srgb, var(--surface) 78%, var(--background)); margin:0;">Vista rápida del login y navegación.</p>
                                <div class="preview-pane">
                                    <?php if(!empty($activeTheme['logo_url'])): ?>
                                        <div class="preview-header">
                                            <img src="<?= htmlspecialchars($activeTheme['logo_url']) ?>" alt="Logo" class="preview-logo">
                                            <div>
                                                <strong><?= htmlspecialchars($activeTheme['login_hero'] ?? '') ?></strong>
                                                <div class="preview-subtitle">"<?= htmlspecialchars($activeTheme['login_message'] ?? '') ?>"</div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <div class="pillset">
                                        <span class="badge" style="background:color-mix(in srgb, var(--surface) 90%, var(--background) 10%); color: var(--primary);">Primario <?= htmlspecialchars($activeTheme['primary'] ?? '') ?></span>
                                        <span class="badge" style="background:color-mix(in srgb, var(--surface) 90%, var(--background) 10%); color: var(--secondary);">Secundario <?= htmlspecialchars($activeTheme['secondary'] ?? '') ?></span>
                                        <span class="badge" style="background:color-mix(in srgb, var(--surface) 90%, var(--background) 10%); color: var(--accent);">Acento <?= htmlspecialchars($activeTheme['accent'] ?? '') ?></span>
                                    </div>
                                    <small style="color: color-mix(in srgb, var(--surface) 75%, var(--background));">Roles activos: <?= htmlspecialchars(implode('· ', $configData['access']['roles'])) ?></small>
                                </div>
                                <div class="pillset">
                                    <span class="pill" style="background: color-mix(in srgb, var(--surface) 18%, var(--background)); color:var(--text-primary);">Fuente: <?= htmlspecialchars($activeTheme['font_family'] ?? '') ?></span>
                                    <span class="pill" style="background: color-mix(in srgb, var(--surface) 18%, var(--background)); color:var(--text-primary);">Fondo: <?= htmlspecialchars($activeTheme['background'] ?? '') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </section>

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

                                <div class="form-block">
                                    <span class="section-label">Rutas de datos base</span>
                                    <div class="input-stack">
                                        <label>Archivo de datos principal</label>
                                        <input name="data_file" value="<?= htmlspecialchars($configData['master_files']['data_file']) ?>" required>
                                    </div>
                                    <div class="input-stack">
                                        <label>Archivo de esquema / bootstrap</label>
                                        <input name="schema_file" value="<?= htmlspecialchars($configData['master_files']['schema_file']) ?>" required>
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

            <section id="panel-gobierno" class="tab-panel governance-panel">
                <form id="governance-config-form" method="POST" action="/project/public/config/theme" enctype="multipart/form-data"></form>
                <?php
                $permissionGroups = [
                    'Gestión' => ['administrar', 'gestionar', 'config', 'cliente', 'proyecto', 'usuario', 'rol', 'catálogo', 'catalogo'],
                    'Visualización' => ['ver', 'visualizar', 'dashboard', 'reporte', 'report'],
                    'Operación' => ['timesheet', 'outsourcing', 'aprob', 'avance', 'documento', 'flujo', 'tarea', 'ticket'],
                ];
                $groupPermissions = function(array $permissions) use ($permissionGroups): array {
                    $grouped = [];
                    foreach ($permissionGroups as $group => $keywords) {
                        $grouped[$group] = [];
                    }
                    $grouped['Otros'] = [];
                    foreach ($permissions as $permission) {
                        $name = mb_strtolower($permission['name'] ?? '');
                        $matched = false;
                        foreach ($permissionGroups as $group => $keywords) {
                            foreach ($keywords as $keyword) {
                                if (mb_strpos($name, $keyword) !== false) {
                                    $grouped[$group][] = $permission;
                                    $matched = true;
                                    break 2;
                                }
                            }
                        }
                        if (!$matched) {
                            $grouped['Otros'][] = $permission;
                        }
                    }
                    return array_filter($grouped);
                };
                ?>
                <div class="governance-blocks">
                    <div class="card config-card governance-block">
                        <div class="card-content">
                            <header class="governance-block-header">
                                <h3 class="governance-block-title">Reglas globales del sistema</h3>
                                <p class="governance-block-subtitle">(impactan a todo el sistema)</p>
                            </header>
                            <div class="governance-rules">
                                <div class="governance-rule">
                                    <div class="governance-rule-info">
                                        <span class="governance-rule-title">Cambios de presupuesto requieren aprobación</span>
                                        <p class="governance-rule-desc">Evita ajustes de presupuesto sin revisión previa.</p>
                                    </div>
                                    <label class="toggle-switch toggle-switch--solo" aria-label="Cambios de presupuesto requieren aprobación">
                                        <input type="checkbox" name="budget_change_requires_approval" form="governance-config-form" <?= $configData['operational_rules']['approvals']['budget_change_requires_approval'] ? 'checked' : '' ?>>
                                        <span class="toggle-track" aria-hidden="true"></span>
                                    </label>
                                </div>
                                <div class="governance-rule">
                                    <div class="governance-rule-info">
                                        <span class="governance-rule-title">Talento externo requiere aprobación</span>
                                        <p class="governance-rule-desc">Asegura control en la incorporación de talento externo.</p>
                                    </div>
                                    <label class="toggle-switch toggle-switch--solo" aria-label="Talento externo requiere aprobación">
                                        <input type="checkbox" name="external_talent_requires_approval" form="governance-config-form" <?= $configData['operational_rules']['approvals']['external_talent_requires_approval'] ? 'checked' : '' ?>>
                                        <span class="toggle-track" aria-hidden="true"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card config-card governance-block">
                        <div class="card-content">
                            <header class="governance-block-header">
                                <h3 class="governance-block-title">Activación de módulos</h3>
                                <p class="governance-block-subtitle">Activa o desactiva módulos del sistema.</p>
                            </header>
                            <div class="governance-modules">
                                <div class="governance-module-row">
                                    <div class="governance-module-info">
                                        <span class="governance-module-title">Timesheets</span>
                                        <p class="governance-module-desc">Activa el módulo y el menú para talentos con reporte requerido.</p>
                                    </div>
                                    <label class="toggle-switch toggle-switch--solo" aria-label="Habilitar reporte de horas (Timesheets)">
                                        <input type="checkbox" name="timesheets_enabled" form="governance-config-form" <?= !empty($configData['operational_rules']['timesheets']['enabled']) ? 'checked' : '' ?>>
                                        <span class="toggle-track" aria-hidden="true"></span>
                                    </label>
                                </div>
                                <div class="governance-module-row">
                                    <div class="governance-module-info">
                                        <span class="governance-module-title">Outsourcing</span>
                                        <p class="governance-module-desc">Disponible para gestión de talento externo y proveedores.</p>
                                    </div>
                                    <label class="toggle-switch toggle-switch--solo" aria-label="Outsourcing">
                                        <input type="checkbox" checked disabled>
                                        <span class="toggle-track" aria-hidden="true"></span>
                                    </label>
                                </div>
                                <div class="governance-module-row">
                                    <div class="governance-module-info">
                                        <span class="governance-module-title">Aprobaciones</span>
                                        <p class="governance-module-desc">Controla la ruta de revisiones críticas del sistema.</p>
                                    </div>
                                    <label class="toggle-switch toggle-switch--solo" aria-label="Aprobaciones">
                                        <input type="checkbox" checked disabled>
                                        <span class="toggle-track" aria-hidden="true"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card config-card governance-block governance-block--critical">
                        <div class="card-content">
                            <header class="governance-block-header">
                                <h3 class="governance-block-title">Gobernanza de acceso</h3>
                                <p class="governance-block-subtitle">(quién puede hacer qué)</p>
                            </header>
                            <div class="governance-card-body">
                                <div class="governance-access-section">
                                    <span class="section-label">Roles del sistema</span>
                                    <div class="input-stack">
                                        <label>Roles permitidos (separados por coma)</label>
                                        <input name="roles" form="governance-config-form" value="<?= htmlspecialchars(implode(', ', $configData['access']['roles'])) ?>">
                                    </div>
                                    <div class="option-row">
                                        <label class="option">
                                            <input type="checkbox" name="allow_self_registration" form="governance-config-form" <?= $configData['access']['user_management']['allow_self_registration'] ? 'checked' : '' ?>>
                                            Auto-registro
                                        </label>
                                        <label class="option">
                                            <input type="checkbox" name="require_approval" form="governance-config-form" <?= $configData['access']['user_management']['require_approval'] ? 'checked' : '' ?>>
                                            Requiere aprobación
                                        </label>
                                    </div>
                                </div>
                                <div class="governance-access-section">
                                    <span class="section-label">Permisos por rol</span>
                                    <form method="POST" action="/project/public/config/roles/create" class="config-form-grid">
                                        <input name="nombre" placeholder="Nombre del rol" required>
                                        <input name="descripcion" placeholder="Descripción">
                                        <div class="permission-groups full-span">
                                            <?php foreach($groupPermissions($permissions) as $groupName => $groupItems): ?>
                                                <div class="permission-group">
                                                    <p class="permission-group-title"><?= htmlspecialchars($groupName) ?></p>
                                                    <div class="permission-list">
                                                        <?php foreach($groupItems as $permission): ?>
                                                            <label class="permission-item">
                                                                <input type="checkbox" name="permissions[]" value="<?= (int) $permission['id'] ?>">
                                                                <?= htmlspecialchars($permission['name']) ?>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="form-footer">
                                            <div></div>
                                            <button class="btn primary" type="submit">Crear rol</button>
                                        </div>
                                    </form>
                                    <div class="role-accordion">
                                        <?php foreach($roles as $index => $role): ?>
                                            <details class="role-panel" <?= $index === 0 ? 'open' : '' ?>>
                                                <summary>
                                                    <span><?= htmlspecialchars($role['nombre']) ?></span>
                                                    <span class="text-muted">Editar permisos</span>
                                                </summary>
                                                <div class="role-panel-body">
                                                    <form method="POST" action="/project/public/config/roles/update">
                                                        <input type="hidden" name="id" value="<?= (int) $role['id'] ?>">
                                                        <div class="config-form-grid tight">
                                                            <label>Nombre<input name="nombre" value="<?= htmlspecialchars($role['nombre']) ?>"></label>
                                                            <label>Descripción<input name="descripcion" value="<?= htmlspecialchars($role['descripcion'] ?? '') ?>"></label>
                                                        </div>
                                                        <div class="permission-groups">
                                                            <?php foreach($groupPermissions($permissions) as $groupName => $groupItems): ?>
                                                                <div class="permission-group">
                                                                    <p class="permission-group-title"><?= htmlspecialchars($groupName) ?></p>
                                                                    <div class="permission-list">
                                                                        <?php foreach($groupItems as $permission): ?>
                                                                            <?php $assigned = array_filter($role['permissions'], fn($p) => (int)$p['id'] === (int)$permission['id']); ?>
                                                                            <label class="permission-item">
                                                                                <input type="checkbox" name="permissions[]" value="<?= (int) $permission['id'] ?>" <?= $assigned ? 'checked' : '' ?>>
                                                                                <?= htmlspecialchars($permission['name']) ?>
                                                                            </label>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <div class="form-footer">
                                                            <div></div>
                                                            <button class="btn secondary" type="submit">Actualizar rol</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </details>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card config-card governance-block">
                    <div class="card-content">
                        <header class="governance-block-header">
                            <h3 class="governance-block-title">Usuarios</h3>
                            <p class="governance-block-subtitle">Gestiona altas, roles y permisos puntuales por usuario.</p>
                        </header>
                        <div class="governance-card-body">
                            <div class="governance-access-section">
                                <span class="section-label">Alta de usuarios</span>
                                <form method="POST" action="/project/public/config/users/create" class="config-form-grid">
                                    <input name="name" placeholder="Nombre" required>
                                    <input name="email" type="email" placeholder="Correo" required>
                                    <input name="password" type="password" placeholder="Contraseña" required>
                                    <select name="role_id" required>
                                        <?php foreach($roles as $role): ?>
                                            <option value="<?= (int) $role['id'] ?>"><?= htmlspecialchars($role['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label class="option">
                                        <input type="checkbox" name="active" checked>
                                        Usuario activo
                                    </label>
                                    <div class="config-flow-roles full-span">
                                        <strong>Roles del flujo documental</strong>
                                        <label class="option compact">
                                            <input type="checkbox" name="can_review_documents" <?= !$isAdmin ? 'disabled' : '' ?>>
                                            Puede ser Revisor
                                        </label>
                                        <label class="option compact">
                                            <input type="checkbox" name="can_validate_documents" <?= !$isAdmin ? 'disabled' : '' ?>>
                                            Puede ser Validador
                                        </label>
                                        <label class="option compact">
                                            <input type="checkbox" name="can_approve_documents" <?= !$isAdmin ? 'disabled' : '' ?>>
                                            Puede ser Aprobador
                                        </label>
                                        <?php if (!$isAdmin): ?>
                                            <small class="section-muted">Solo administradores pueden editar estos permisos.</small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="config-flow-roles full-span">
                                        <strong>Permisos de avance</strong>
                                        <label class="option compact">
                                            <input type="checkbox" name="can_update_project_progress" <?= !$isAdmin ? 'disabled' : '' ?>>
                                            Puede actualizar avance del proyecto
                                        </label>
                                        <?php if (!$isAdmin): ?>
                                            <small class="section-muted">Solo administradores pueden editar estos permisos.</small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="config-flow-roles full-span">
                                        <strong>Permisos de outsourcing</strong>
                                        <label class="option compact">
                                            <input type="checkbox" name="can_access_outsourcing" <?= !$isAdmin ? 'disabled' : '' ?>>
                                            Acceder a Outsourcing
                                        </label>
                                        <?php if (!$isAdmin): ?>
                                            <small class="section-muted">Solo administradores pueden editar estos permisos.</small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="config-flow-roles full-span">
                                        <strong>Permisos de timesheets</strong>
                                        <label class="option compact">
                                            <input type="checkbox" name="can_access_timesheets" <?= !$isAdmin ? 'disabled' : '' ?>>
                                            Acceder a Timesheets
                                        </label>
                                        <label class="option compact">
                                            <input type="checkbox" name="can_approve_timesheets" <?= !$isAdmin ? 'disabled' : '' ?>>
                                            Aprobar Timesheets
                                        </label>
                                        <?php if (!$isAdmin): ?>
                                            <small class="section-muted">Solo administradores pueden editar estos permisos.</small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-footer">
                                        <div></div>
                                        <button class="btn primary" type="submit">Crear usuario</button>
                                    </div>
                                </form>
                                <div class="user-accordion">
                                    <?php foreach($users as $user): ?>
                                        <details class="user-card">
                                            <summary>
                                                <span><?= htmlspecialchars($user['name']) ?></span>
                                                <span><?= htmlspecialchars($user['email']) ?></span>
                                                <span><?= htmlspecialchars($user['role_name']) ?></span>
                                                <span>
                                                    <?php if((int)$user['active'] === 1): ?>
                                                        <span class="badge success">Activo</span>
                                                    <?php else: ?>
                                                        <span class="badge danger">Inactivo</span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="user-expand">Ver permisos</span>
                                            </summary>
                                            <div class="user-details">
                                                <form method="POST" action="/project/public/config/users/update">
                                                    <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                                    <div class="config-form-grid">
                                                        <label>Nombre<input name="name" value="<?= htmlspecialchars($user['name']) ?>"></label>
                                                        <label>Correo<input name="email" value="<?= htmlspecialchars($user['email']) ?>"></label>
                                                        <label>Rol
                                                            <select name="role_id">
                                                                <?php foreach($roles as $role): ?>
                                                                    <option value="<?= (int) $role['id'] ?>" <?= ((int)$role['id'] === (int)$user['role_id']) ? 'selected' : '' ?>><?= htmlspecialchars($role['nombre']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </label>
                                                        <label>Contraseña nueva<input name="password" type="password" placeholder="Nueva contraseña"></label>
                                                        <label class="option">
                                                            <input type="checkbox" name="active" <?= ((int)$user['active'] === 1) ? 'checked' : '' ?>>
                                                            Activo
                                                        </label>
                                                    </div>
                                                    <div class="config-flow-roles">
                                                        <strong>Flujo documental</strong>
                                                        <label class="option compact">
                                                            <input type="checkbox" name="can_review_documents" <?= ((int) ($user['can_review_documents'] ?? 0) === 1) ? 'checked' : '' ?> <?= !$isAdmin ? 'disabled' : '' ?>>
                                                            Revisor
                                                        </label>
                                                        <label class="option compact">
                                                            <input type="checkbox" name="can_validate_documents" <?= ((int) ($user['can_validate_documents'] ?? 0) === 1) ? 'checked' : '' ?> <?= !$isAdmin ? 'disabled' : '' ?>>
                                                            Validador
                                                        </label>
                                                        <label class="option compact">
                                                            <input type="checkbox" name="can_approve_documents" <?= ((int) ($user['can_approve_documents'] ?? 0) === 1) ? 'checked' : '' ?> <?= !$isAdmin ? 'disabled' : '' ?>>
                                                            Aprobador
                                                        </label>
                                                    </div>
                                                    <div class="config-flow-roles">
                                                        <strong>Permisos de avance</strong>
                                                        <label class="option compact">
                                                            <input type="checkbox" name="can_update_project_progress" <?= ((int) ($user['can_update_project_progress'] ?? 0) === 1) ? 'checked' : '' ?> <?= !$isAdmin ? 'disabled' : '' ?>>
                                                            Actualizar avance
                                                        </label>
                                                    </div>
                                                    <div class="config-flow-roles">
                                                        <strong>Permisos de outsourcing</strong>
                                                        <label class="option compact">
                                                            <input type="checkbox" name="can_access_outsourcing" <?= ((int) ($user['can_access_outsourcing'] ?? 0) === 1) ? 'checked' : '' ?> <?= !$isAdmin ? 'disabled' : '' ?>>
                                                            Acceder a Outsourcing
                                                        </label>
                                                    </div>
                                                    <div class="config-flow-roles">
                                                        <strong>Permisos de timesheets</strong>
                                                        <label class="option compact">
                                                            <input type="checkbox" name="can_access_timesheets" <?= ((int) ($user['can_access_timesheets'] ?? 0) === 1) ? 'checked' : '' ?> <?= !$isAdmin ? 'disabled' : '' ?>>
                                                            Acceder a Timesheets
                                                        </label>
                                                        <label class="option compact">
                                                            <input type="checkbox" name="can_approve_timesheets" <?= ((int) ($user['can_approve_timesheets'] ?? 0) === 1) ? 'checked' : '' ?> <?= !$isAdmin ? 'disabled' : '' ?>>
                                                            Aprobar Timesheets
                                                        </label>
                                                    </div>
                                                    <div class="form-footer">
                                                        <div></div>
                                                        <button class="btn secondary" type="submit">Guardar</button>
                                                    </div>
                                                </form>
                                                <div class="user-actions">
                                                    <?php if ($isAdmin): ?>
                                                        <form method="POST" action="/project/public/impersonate/start" class="inline">
                                                            <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                                            <button class="btn secondary" type="submit">Ver como usuario</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" action="/project/public/config/users/deactivate" onsubmit="return confirm('Desactivar usuario?');">
                                                        <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                                        <button class="btn ghost" type="submit">Desactivar</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </details>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                <div class="card config-card governance-block">
                    <div class="card-content">
                        <header class="governance-block-header">
                            <h3 class="governance-block-title">Flujo documental</h3>
                            <p class="governance-block-subtitle">(cómo pasan los documentos por revisión)</p>
                        </header>
                        <div class="governance-document-section">
                            <div class="config-form-grid tight">
                                <div class="input-stack">
                                    <label>Roles habilitados para Revisores (coma)</label>
                                    <input name="document_reviewer_roles" form="governance-config-form" value="<?= htmlspecialchars(implode(', ', $configData['document_flow']['default']['reviewer_roles'] ?? [])) ?>">
                                </div>
                                <div class="input-stack">
                                    <label>Roles habilitados para Validadores (coma)</label>
                                    <input name="document_validator_roles" form="governance-config-form" value="<?= htmlspecialchars(implode(', ', $configData['document_flow']['default']['validator_roles'] ?? [])) ?>">
                                </div>
                                <div class="input-stack">
                                    <label>Roles habilitados para Aprobadores (coma)</label>
                                    <input name="document_approver_roles" form="governance-config-form" value="<?= htmlspecialchars(implode(', ', $configData['document_flow']['default']['approver_roles'] ?? [])) ?>">
                                </div>
                            </div>
                            <details class="json-collapse">
                                <summary>Catálogo de documentos esperados (JSON por metodología/fase/subfase)</summary>
                                <div class="input-stack">
                                    <textarea name="document_expected_docs_json" form="governance-config-form" rows="6"><?= htmlspecialchars(json_encode($configData['document_flow']['expected_docs'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></textarea>
                                    <small class="subtext">Estructura: {"cascada": {"02-PLANIFICACION": {"01-ENTRADAS": ["..."]}}}</small>
                                </div>
                            </details>
                            <div class="input-stack">
                                <label>Tags sugeridos para documentos (separados por coma)</label>
                                <input name="document_tag_options" form="governance-config-form" value="<?= htmlspecialchars(implode(', ', $configData['document_flow']['tag_options'] ?? [])) ?>">
                            </div>
                        </div>
                        <div class="form-footer">
                            <span class="text-muted">Mantén coherencia en permisos y aprobaciones.</span>
                            <button class="btn primary" type="submit" form="governance-config-form">Guardar y aplicar</button>
                        </div>
                    </div>
                </div>
            </section>

            <section id="panel-catalogos" class="tab-panel">
                <div class="section-grid config-columns">
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
        </div>
    </div>
</section>
<script>
    const themeForms = document.querySelectorAll('form[action="/project/public/config/theme"]');
    themeForms.forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(form);
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        Accept: 'application/json',
                    },
                });
                if (!response.ok) {
                    form.submit();
                    return;
                }
                const data = await response.json();
                if (data && data.theme) {
                    window.__APP_THEME__ = data.theme;
                    if (typeof window.applyTheme === 'function') {
                        window.applyTheme(data.theme);
                    }
                }
                const savedBadge = document.querySelector('[data-theme-saved]');
                if (savedBadge) {
                    savedBadge.hidden = false;
                }
            } catch (error) {
                form.submit();
            }
        });
    });
</script>
