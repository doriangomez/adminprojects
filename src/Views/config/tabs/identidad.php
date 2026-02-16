<section id="panel-identidad" class="tab-panel">
    <form method="POST" action="/config/theme" enctype="multipart/form-data">
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
