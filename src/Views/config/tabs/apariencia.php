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
