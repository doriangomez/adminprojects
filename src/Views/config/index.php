<section class="section-grid twothirds">
    <div class="card">
        <div class="toolbar">
            <div>
                <p class="badge neutral" style="margin:0;">Tema & identidad</p>
                <h3 style="margin:6px 0 2px 0;">Personaliza la aplicación sin tocar código</h3>
                <small class="text-muted">Logo seguro, paleta viva y tipografía unificada</small>
            </div>
            <?php if(!empty($savedMessage)): ?>
                <span class="badge success">Guardado</span>
            <?php endif; ?>
        </div>
        <form method="POST" action="/project/public/config/theme" enctype="multipart/form-data" class="grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:16px;">
            <div>
                <label>Logo (sube uno nuevo o pega una URL)</label>
                <input type="file" name="logo_file" accept="image/*">
                <input name="logo" value="<?= htmlspecialchars($configData['theme']['logo']) ?>" placeholder="https://...png">
            </div>
            <div>
                <label>Tipografía base</label>
                <input name="font_family" value="<?= htmlspecialchars($configData['theme']['font_family']) ?>" placeholder="'Inter', sans-serif">
                <div style="display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:8px; margin-top:8px;">
                    <label>Color primario<input type="color" name="primary" value="<?= htmlspecialchars($configData['theme']['primary']) ?>"></label>
                    <label>Color secundario<input type="color" name="secondary" value="<?= htmlspecialchars($configData['theme']['secondary']) ?>"></label>
                    <label>Color acento<input type="color" name="accent" value="<?= htmlspecialchars($configData['theme']['accent']) ?>"></label>
                    <label>Color fondo<input type="color" name="background" value="<?= htmlspecialchars($configData['theme']['background']) ?>"></label>
                    <label>Color superficies<input type="color" name="surface" value="<?= htmlspecialchars($configData['theme']['surface']) ?>"></label>
                </div>
            </div>
            <div>
                <label>Mensaje principal de login</label>
                <input name="login_hero" value="<?= htmlspecialchars($configData['theme']['login_hero']) ?>" placeholder="Titular inspirador">
                <label>Copete</label>
                <textarea name="login_message" rows="3"><?= htmlspecialchars($configData['theme']['login_message']) ?></textarea>
            </div>
            <div>
                <label>Archivo de datos principal</label>
                <input name="data_file" value="<?= htmlspecialchars($configData['master_files']['data_file']) ?>" required>
                <label>Archivo de esquema / bootstrap</label>
                <input name="schema_file" value="<?= htmlspecialchars($configData['master_files']['schema_file']) ?>" required>
                <label>Roles permitidos (separados por coma)</label>
                <input name="roles" value="<?= htmlspecialchars(implode(', ', $configData['access']['roles'])) ?>">
                <div style="display:flex; gap:12px; margin-top:10px; align-items:center; flex-wrap:wrap;">
                    <label style="display:flex; gap:6px; align-items:center;">
                        <input type="checkbox" name="allow_self_registration" <?= $configData['access']['user_management']['allow_self_registration'] ? 'checked' : '' ?>>
                        Auto-registro
                    </label>
                    <label style="display:flex; gap:6px; align-items:center;">
                        <input type="checkbox" name="require_approval" <?= $configData['access']['user_management']['require_approval'] ? 'checked' : '' ?>>
                        Requiere aprobación
                    </label>
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; align-items:center; gap:8px; grid-column: 1 / -1;">
                <span class="text-muted">Los cambios se aplican en todo el sistema.</span>
                <button class="btn primary" type="submit">Guardar y aplicar</button>
            </div>
            <?php if(!empty($savedMessage)): ?>
                <div class="alert success" style="grid-column:1 / -1;">
                    <?= htmlspecialchars($savedMessage) ?>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <div class="card card-overlay" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); color: color-mix(in srgb, white 90%, var(--secondary) 10%);">
        <div style="position:relative; display:flex; flex-direction:column; gap:12px;">
            <h3 style="margin:0;">Previsualización en vivo</h3>
            <p style="margin:0; color: color-mix(in srgb, white 78%, transparent);">Así se verá tu login y barra lateral.</p>
            <div style="background: color-mix(in srgb, white 12%, transparent); padding:16px; border-radius:12px; display:flex; flex-direction:column; gap:10px;">
                <?php if(!empty($configData['theme']['logo'])): ?>
                    <div style="display:flex; align-items:center; gap:12px;">
                        <img src="<?= htmlspecialchars($configData['theme']['logo']) ?>" alt="Logo" style="height:42px; background:var(--panel); padding:8px; border-radius:10px; box-shadow:0 8px 20px var(--glow);">
                        <div>
                            <strong><?= htmlspecialchars($configData['theme']['login_hero']) ?></strong>
                            <div style="color: color-mix(in srgb, white 80%, transparent); font-size:13px;">"<?= htmlspecialchars($configData['theme']['login_message']) ?>"</div>
                        </div>
                    </div>
                <?php endif; ?>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <span class="badge" style="background:var(--panel); color: var(--primary);"><?= htmlspecialchars($configData['theme']['primary']) ?></span>
                    <span class="badge" style="background:var(--panel); color: var(--secondary);"><?= htmlspecialchars($configData['theme']['secondary']) ?></span>
                    <span class="badge" style="background:var(--panel); color: var(--accent);"><?= htmlspecialchars($configData['theme']['accent']) ?></span>
                </div>
                <small style="color: color-mix(in srgb, white 75%, transparent);">Roles activos: <?= htmlspecialchars(implode(' · ', $configData['access']['roles'])) ?></small>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <span class="pill" style="background: color-mix(in srgb, white 18%, transparent); color:white;">Fuente: <?= htmlspecialchars($configData['theme']['font_family']) ?></span>
                <span class="pill" style="background: color-mix(in srgb, white 18%, transparent); color:white;">Fondo: <?= htmlspecialchars($configData['theme']['background']) ?></span>
            </div>
        </div>
    </div>
</section>

<section class="section-grid wide">
    <div class="card">
        <div class="toolbar">
            <div>
                <p class="badge neutral" style="margin:0;">Usuarios</p>
                <h3 style="margin:6px 0 0 0;">Alta y edición</h3>
            </div>
        </div>
        <form method="POST" action="/project/public/config/users/create" class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px;">
            <input name="name" placeholder="Nombre" required>
            <input name="email" type="email" placeholder="Correo" required>
            <input name="password" type="password" placeholder="Contraseña" required>
            <select name="role_id" required>
                <?php foreach($roles as $role): ?>
                    <option value="<?= (int) $role['id'] ?>"><?= htmlspecialchars($role['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <label style="display:flex; gap:6px; align-items:center;">
                <input type="checkbox" name="active" checked>
                Usuario activo
            </label>
            <div style="grid-column: 1 / -1; text-align:right;">
                <button class="btn primary" type="submit">Crear usuario</button>
            </div>
        </form>
        <table>
            <thead>
                <tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php foreach($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['role_name']) ?></td>
                        <td>
                            <?php if((int)$user['active'] === 1): ?>
                                <span class="badge success">Activo</span>
                            <?php else: ?>
                                <span class="badge danger">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td style="display:flex; gap:6px; flex-wrap:wrap;">
                            <form method="POST" action="/project/public/config/users/update" style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                <input name="name" value="<?= htmlspecialchars($user['name']) ?>" style="width:140px;">
                                <input name="email" value="<?= htmlspecialchars($user['email']) ?>" style="width:180px;">
                                <select name="role_id">
                                    <?php foreach($roles as $role): ?>
                                        <option value="<?= (int) $role['id'] ?>" <?= ((int)$role['id'] === (int)$user['role_id']) ? 'selected' : '' ?>><?= htmlspecialchars($role['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input name="password" type="password" placeholder="Nueva contraseña" style="width:150px;">
                                <label style="display:flex; gap:4px; align-items:center;">
                                    <input type="checkbox" name="active" <?= ((int)$user['active'] === 1) ? 'checked' : '' ?>>
                                    Activo
                                </label>
                                <button class="btn secondary" type="submit">Guardar</button>
                            </form>
                            <form method="POST" action="/project/public/config/users/deactivate" onsubmit="return confirm('Desactivar usuario?');">
                                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                <button class="btn ghost" type="submit">Desactivar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="toolbar">
            <div>
                <p class="badge neutral" style="margin:0;">Roles y permisos</p>
                <h3 style="margin:6px 0 0 0;">Orquesta la gobernanza de acceso</h3>
            </div>
        </div>
        <form method="POST" action="/project/public/config/roles/create" class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:10px; margin-bottom:12px;">
            <input name="nombre" placeholder="Nombre del rol" required>
            <input name="descripcion" placeholder="Descripción">
            <div style="grid-column:1 / -1; display:flex; gap:8px; flex-wrap:wrap;">
                <?php foreach($permissions as $permission): ?>
                    <label class="pill" style="background: var(--soft-secondary); border: 1px solid var(--border);">
                        <input type="checkbox" name="permissions[]" value="<?= (int) $permission['id'] ?>">
                        <?= htmlspecialchars($permission['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div style="grid-column:1 / -1; text-align:right;">
                <button class="btn primary" type="submit">Crear rol</button>
            </div>
        </form>
        <div style="display:flex; flex-direction:column; gap:10px;">
            <?php foreach($roles as $role): ?>
                <form method="POST" action="/project/public/config/roles/update" class="card subtle-card">
                    <input type="hidden" name="id" value="<?= (int) $role['id'] ?>">
                    <div class="toolbar">
                        <div>
                            <strong><?= htmlspecialchars($role['nombre']) ?></strong>
                            <p style="margin:0; color: var(--muted); font-size:13px;"><?= htmlspecialchars($role['descripcion'] ?? 'Rol operativo') ?></p>
                        </div>
                        <button class="btn secondary" type="submit">Actualizar rol</button>
                    </div>
                    <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:8px;">
                        <label>Nombre<input name="nombre" value="<?= htmlspecialchars($role['nombre']) ?>"></label>
                        <label>Descripción<input name="descripcion" value="<?= htmlspecialchars($role['descripcion'] ?? '') ?>"></label>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;">
                        <?php foreach($permissions as $permission): ?>
                            <?php $assigned = array_filter($role['permissions'], fn($p) => (int)$p['id'] === (int)$permission['id']); ?>
                            <label class="pill" style="background: var(--panel); border: 1px solid var(--border);">
                                <input type="checkbox" name="permissions[]" value="<?= (int) $permission['id'] ?>" <?= $assigned ? 'checked' : '' ?>>
                                <?= htmlspecialchars($permission['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="card">
    <div class="toolbar">
        <div>
            <p class="badge neutral" style="margin:0;">Archivos maestros</p>
            <h3 style="margin:6px 0 0 0;">Catálogos críticos del sistema</h3>
        </div>
        <small style="color: var(--muted);">CRUD seguro sobre catálogos base</small>
    </div>
    <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:12px;">
        <?php foreach($masterData as $table => $items): ?>
            <div class="card subtle-card">
                <div class="toolbar">
                    <div>
                        <p class="badge neutral" style="margin:0;"><?= htmlspecialchars($table) ?></p>
                        <h4 style="margin:4px 0 0 0;"><?= ucwords(str_replace('_', ' ', $table)) ?></h4>
                    </div>
                </div>
                <form method="POST" action="/project/public/config/master-files/create" class="grid" style="grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap:8px;">
                    <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                    <input name="code" placeholder="Código" required>
                    <input name="label" placeholder="Etiqueta" required>
                    <div style="grid-column:1 / -1; text-align:right;">
                        <button class="btn primary" type="submit">Agregar</button>
                    </div>
                </form>
                <table>
                    <thead><tr><th>Código</th><th>Etiqueta</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach($items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['code']) ?></td>
                                <td><?= htmlspecialchars($item['label']) ?></td>
                                <td style="display:flex; gap:6px;">
                                    <form method="POST" action="/project/public/config/master-files/update" class="inline" style="gap:6px;">
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
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>
</section>
