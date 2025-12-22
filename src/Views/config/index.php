<div class="grid" style="grid-template-columns: 2fr 1fr; gap:16px;">
    <div class="card">
        <div class="toolbar">
            <h3 style="margin:0;">Archivos maestros</h3>
            <span style="color: var(--gray); font-size: 13px;">Ruta centralizada de datos</span>
        </div>
        <form method="POST" action="/project/public/config">
            <label>Archivo de datos principal</label>
            <input name="data_file" value="<?= htmlspecialchars($configData['master_files']['data_file']) ?>" required>
            <label>Archivo de esquema / bootstrap</label>
            <input name="schema_file" value="<?= htmlspecialchars($configData['master_files']['schema_file']) ?>" required>
            <div class="toolbar" style="margin-top:12px;">
                <div>
                    <h3 style="margin:0;">Identidad visual</h3>
                    <p style="margin:0; color: var(--gray);">Logo, paleta y storytelling del login</p>
                </div>
                <div style="display:flex; gap:8px;">
                    <input type="color" name="primary" value="<?= htmlspecialchars($configData['theme']['primary']) ?>">
                    <input type="color" name="secondary" value="<?= htmlspecialchars($configData['theme']['secondary']) ?>">
                    <input type="color" name="accent" value="<?= htmlspecialchars($configData['theme']['accent']) ?>">
                    <input type="color" name="background" value="<?= htmlspecialchars($configData['theme']['background']) ?>">
                </div>
            </div>
            <label>URL del logo (se mostrará en header y login)</label>
            <input name="logo" value="<?= htmlspecialchars($configData['theme']['logo']) ?>" placeholder="https://...png">
            <label>Mensaje principal en login</label>
            <input name="login_hero" value="<?= htmlspecialchars($configData['theme']['login_hero']) ?>" placeholder="Titular inspirador">
            <label>Copete para login</label>
            <textarea name="login_message" rows="2" style="width:100%; padding:10px; border-radius: 10px; border:1px solid #e5e7eb;"><?= htmlspecialchars($configData['theme']['login_message']) ?></textarea>

            <div class="toolbar" style="margin-top:12px;">
                <div>
                    <h3 style="margin:0;">Gobierno de roles y usuarios</h3>
                    <p style="margin:0; color: var(--gray);">Define la lista maestra y cómo se incorporan usuarios</p>
                </div>
            </div>
            <label>Roles permitidos (separados por coma)</label>
            <input name="roles" value="<?= htmlspecialchars(implode(', ', $configData['access']['roles'])) ?>">
            <div style="display:flex; gap:12px; margin-top:10px;">
                <label style="display:flex; gap:6px; align-items:center;">
                    <input type="checkbox" name="allow_self_registration" <?= $configData['access']['user_management']['allow_self_registration'] ? 'checked' : '' ?>>
                    Permitir auto-registro de usuarios
                </label>
                <label style="display:flex; gap:6px; align-items:center;">
                    <input type="checkbox" name="require_approval" <?= $configData['access']['user_management']['require_approval'] ? 'checked' : '' ?>>
                    Requiere aprobación de administrador
                </label>
            </div>

            <div style="margin-top:16px; display:flex; justify-content:flex-end;">
                <button class="btn primary" type="submit">Guardar configuración</button>
            </div>
            <?php if(!empty($savedMessage)): ?>
                <div style="margin-top:10px; padding:10px; background:#ecfdf3; color:#166534; border:1px solid #bbf7d0; border-radius:10px;">
                    <?= htmlspecialchars($savedMessage) ?>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <div class="card" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); color:white;">
        <h3 style="margin:0 0 8px 0;">Previsualización</h3>
        <p style="margin:0 0 12px 0; color: rgba(255,255,255,0.85);">Así se verá tu login con la paleta elegida.</p>
        <div style="background: rgba(255,255,255,0.08); padding:16px; border-radius:12px; display:flex; flex-direction:column; gap:10px;">
            <?php if(!empty($configData['theme']['logo'])): ?>
                <div style="display:flex; align-items:center; gap:10px;">
                    <img src="<?= htmlspecialchars($configData['theme']['logo']) ?>" alt="Logo" style="height:36px; background:white; padding:6px; border-radius:10px;">
                    <strong><?= htmlspecialchars($configData['theme']['login_hero']) ?></strong>
                </div>
            <?php endif; ?>
            <p style="margin:0; color: rgba(255,255,255,0.9); font-size:14px;">"<?= htmlspecialchars($configData['theme']['login_message']) ?>"</p>
            <div style="display:flex; gap:8px;">
                <span class="badge" style="background:white; color: var(--primary);">Administra</span>
                <span class="badge" style="background:white; color: var(--secondary);">Orquesta</span>
                <span class="badge" style="background:white; color: var(--accent);">Escala</span>
            </div>
            <small style="color: rgba(255,255,255,0.75);">Roles activos: <?= htmlspecialchars(implode(' · ', $configData['access']['roles'])) ?></small>
        </div>
    </div>
</div>
