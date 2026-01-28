<section id="panel-gobierno" class="tab-panel governance-panel">
    <form id="governance-config-form" method="POST" action="/project/public/config/theme" enctype="multipart/form-data"></form>
    <?php
    $permissionGroups = [
        'Gestión' => ['administrar', 'gestionar', 'config', 'cliente', 'proyecto', 'usuario', 'rol', 'catálogo', 'catalogo', 'timesheet', 'outsourcing', 'aprob', 'avance', 'documento', 'flujo', 'tarea', 'ticket'],
        'Visualización' => ['ver', 'visualizar', 'dashboard', 'reporte', 'report'],
    ];
    $groupPermissions = function(array $permissions) use ($permissionGroups): array {
        $grouped = [];
        foreach ($permissionGroups as $group => $keywords) {
            $grouped[$group] = [];
        }
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
                $grouped['Gestión'][] = $permission;
            }
        }
        return array_filter($grouped);
    };
    ?>
    <div class="governance-blocks">
        <div class="card config-card governance-block">
            <div class="card-content">
                <header class="governance-block-header">
                    <div class="governance-block-title-line">
                        <span class="governance-block-icon" aria-hidden="true">🌐</span>
                        <h3 class="governance-block-title">Reglas globales del sistema</h3>
                    </div>
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
                    <div class="governance-block-title-line">
                        <span class="governance-block-icon" aria-hidden="true">🧩</span>
                        <h3 class="governance-block-title">Activación de módulos</h3>
                    </div>
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
                    <div class="governance-block-title-line">
                        <span class="governance-block-icon" aria-hidden="true">🛡️</span>
                        <h3 class="governance-block-title">Gobernanza de acceso</h3>
                    </div>
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
                            <label class="toggle-switch" aria-label="Auto-registro">
                                <span class="toggle-label">Auto-registro</span>
                                <input type="checkbox" name="allow_self_registration" form="governance-config-form" <?= $configData['access']['user_management']['allow_self_registration'] ? 'checked' : '' ?>>
                                <span class="toggle-track" aria-hidden="true"></span>
                            </label>
                            <label class="toggle-switch" aria-label="Requiere aprobación">
                                <span class="toggle-label">Requiere aprobación</span>
                                <input type="checkbox" name="require_approval" form="governance-config-form" <?= $configData['access']['user_management']['require_approval'] ? 'checked' : '' ?>>
                                <span class="toggle-track" aria-hidden="true"></span>
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
                                                    <span class="permission-name"><?= htmlspecialchars($permission['name']) ?></span>
                                                    <span class="toggle-switch toggle-switch--compact permission-toggle">
                                                        <input type="checkbox" name="permissions[]" value="<?= (int) $permission['id'] ?>">
                                                        <span class="toggle-track"></span>
                                                    </span>
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
                            <?php foreach($roles as $role): ?>
                                <details class="role-panel">
                                    <summary>
                                        <span><?= htmlspecialchars($role['nombre']) ?></span>
                                        <span class="pill soft-slate"><?= count($role['permissions'] ?? []) ?> permisos</span>
                                    </summary>
                                    <div class="role-panel-body">
                                        <form method="POST" action="/project/public/config/roles/update">
                                            <input type="hidden" name="id" value="<?= (int) $role['id'] ?>">
                                            <div class="config-form-grid tight">
                                                <div class="input-stack">
                                                    <label>Nombre</label>
                                                    <input name="nombre" value="<?= htmlspecialchars($role['nombre']) ?>">
                                                </div>
                                                <div class="input-stack">
                                                    <label>Descripción</label>
                                                    <input name="descripcion" value="<?= htmlspecialchars($role['descripcion']) ?>">
                                                </div>
                                                <div class="permission-groups full-span">
                                                    <?php foreach($groupPermissions($permissions) as $groupName => $groupItems): ?>
                                                        <div class="permission-group">
                                                            <p class="permission-group-title"><?= htmlspecialchars($groupName) ?></p>
                                                            <div class="permission-list">
                                                                <?php foreach($groupItems as $permission): ?>
                                                                    <?php $isChecked = in_array((int) $permission['id'], $role['permissions'] ?? [], true); ?>
                                                                    <label class="permission-item">
                                                                        <span class="permission-name"><?= htmlspecialchars($permission['name']) ?></span>
                                                                        <span class="toggle-switch toggle-switch--compact permission-toggle <?= $isChecked ? 'is-checked' : '' ?>">
                                                                            <input type="checkbox" name="permissions[]" value="<?= (int) $permission['id'] ?>" <?= $isChecked ? 'checked' : '' ?>>
                                                                            <span class="toggle-track"></span>
                                                                        </span>
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

        <div class="card config-card governance-block">
            <div class="card-content">
            <header class="governance-block-header">
                <div class="governance-block-title-line">
                    <span class="governance-block-icon" aria-hidden="true">👤</span>
                    <h3 class="governance-block-title">Usuarios</h3>
                </div>
                <p class="governance-block-subtitle">Gestiona altas, roles y permisos puntuales por usuario.</p>
            </header>
            <div class="governance-card-body">
                <div class="governance-access-section">
                    <form method="POST" action="/project/public/config/users/create">
                        <div class="user-section-grid">
                            <div class="user-section-card">
                                <div class="user-section-header">
                                    <h4>Alta de usuarios</h4>
                                    <p>Registra nuevos usuarios con rol y estado inicial.</p>
                                </div>
                                <div class="config-form-grid">
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
                                </div>
                            </div>
                            <div class="user-section-card">
                                <div class="user-section-header">
                                    <h4>Permisos</h4>
                                    <p>Define permisos puntuales y accesos especiales.</p>
                                </div>
                                <div class="config-flow-roles">
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
                                <div class="config-flow-roles">
                                    <strong>Permisos de avance</strong>
                                    <label class="option compact">
                                        <input type="checkbox" name="can_update_project_progress" <?= !$isAdmin ? 'disabled' : '' ?>>
                                        Puede actualizar avance del proyecto
                                    </label>
                                    <?php if (!$isAdmin): ?>
                                        <small class="section-muted">Solo administradores pueden editar estos permisos.</small>
                                    <?php endif; ?>
                                </div>
                                <div class="config-flow-roles">
                                    <strong>Permisos de outsourcing</strong>
                                    <label class="option compact">
                                        <input type="checkbox" name="can_access_outsourcing" <?= !$isAdmin ? 'disabled' : '' ?>>
                                        Acceder a Outsourcing
                                    </label>
                                    <?php if (!$isAdmin): ?>
                                        <small class="section-muted">Solo administradores pueden editar estos permisos.</small>
                                    <?php endif; ?>
                                </div>
                                <div class="config-flow-roles">
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
                            </div>
                        </div>
                        <div class="form-footer">
                            <div></div>
                            <button class="btn primary" type="submit">Crear usuario</button>
                        </div>
                    </form>
                    <div class="user-section-card">
                        <div class="user-section-header">
                            <h4>Listado de usuarios</h4>
                            <p>Consulta y actualiza permisos individuales.</p>
                        </div>
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
        </div>

        <div class="card config-card governance-block">
            <div class="card-content">
                <header class="governance-block-header">
                    <div class="governance-block-title-line">
                        <span class="governance-block-icon" aria-hidden="true">📄</span>
                        <h3 class="governance-block-title">Flujo documental</h3>
                    </div>
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
    </div>
</section>
