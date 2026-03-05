<section id="panel-notificaciones" class="tab-panel">
    <?php
    $smtpPasswordConfigured = !empty($configData['notifications']['smtp']['password'] ?? '');
    $notificationStatus = $notificationMessage ?? '';
    $clockSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
    $folderSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>';
    $fileTextSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>';
    $receiptSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 17.5v-11"/></svg>';
    $settingsSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';
    $notificationGroups = [
        'timesheet' => ['label' => 'Timesheets', 'icon' => $clockSvg, 'prefixes' => ['timesheet.']],
        'project' => ['label' => 'Proyectos', 'icon' => $folderSvg, 'prefixes' => ['project.']],
        'document' => ['label' => 'Documentos', 'icon' => $fileTextSvg, 'prefixes' => ['document.']],
        'files' => ['label' => 'Archivos', 'icon' => $receiptSvg, 'prefixes' => ['system.file_']],
        'system' => ['label' => 'Sistema', 'icon' => $settingsSvg, 'prefixes' => ['system.']],
    ];
    $groupedNotifications = [];
    foreach ($notificationGroups as $groupKey => $groupMeta) {
        $groupedNotifications[$groupKey] = [
            'meta' => $groupMeta,
            'events' => [],
        ];
    }

    foreach ($notificationCatalog as $code => $meta) {
        $selectedGroup = null;

        if (strpos($code, 'system.file_') === 0) {
            $selectedGroup = 'files';
        } elseif (strpos($code, 'system.') === 0) {
            $selectedGroup = 'system';
        } elseif (strpos($code, 'timesheet.') === 0) {
            $selectedGroup = 'timesheet';
        } elseif (strpos($code, 'project.') === 0) {
            $selectedGroup = 'project';
        } elseif (strpos($code, 'document.') === 0) {
            $selectedGroup = 'document';
        }

        if ($selectedGroup === null) {
            $selectedGroup = 'system';
        }

        $groupedNotifications[$selectedGroup]['events'][$code] = $meta;
    }
    ?>
    <form method="POST" action="/config/notifications">
        <div class="notification-stack">
            <div class="card config-card notification-smtp-card">
                <div class="card-content">
                    <header class="section-header notification-section-header">
                        <div class="notification-title-row">
                            <span class="notification-icon" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
                            <div>
                                <h3 style="margin:0;">Configuración SMTP</h3>
                                <p class="text-muted">Configura canales, destinatarios por reglas y el correo de salida.</p>
                            </div>
                        </div>
                    </header>

                    <?php if ($notificationStatus === 'sent'): ?>
                        <div class="alert success" style="margin-bottom:12px;">
                            Correo de prueba enviado correctamente.
                        </div>
                    <?php elseif ($notificationStatus === 'failed'): ?>
                        <div class="alert danger" style="margin-bottom:12px;">
                            No se pudo enviar el correo de prueba. Revisa los parámetros SMTP.
                        </div>
                    <?php endif; ?>

                    <div class="notification-smtp-grid">
                        <div class="form-block notification-global-block">
                            <span class="section-label">Estado global</span>
                            <label class="toggle-switch">
                                <span class="toggle-label">Activar notificaciones</span>
                                <input type="checkbox" name="notifications_enabled" <?= !empty($configData['notifications']['enabled']) ? 'checked' : '' ?>>
                                <span class="toggle-track" aria-hidden="true"></span>
                            </label>
                            <small class="text-muted">Si está desactivado, el sistema no envía ninguna notificación.</small>
                        </div>

                        <div class="form-block notification-smtp-block">
                            <span class="section-label">Correo de salida</span>
                            <div class="notification-smtp-fields">
                                <div class="input-stack">
                                    <label>Servidor SMTP</label>
                                    <input name="smtp_host" value="<?= htmlspecialchars($configData['notifications']['smtp']['host'] ?? '') ?>" placeholder="smtp.servidor.com">
                                </div>
                                <div class="input-stack">
                                    <label>Puerto</label>
                                    <input type="number" name="smtp_port" value="<?= htmlspecialchars((string) ($configData['notifications']['smtp']['port'] ?? 587)) ?>">
                                </div>
                                <div class="input-stack">
                                    <label>Seguridad</label>
                                    <select name="smtp_security">
                                        <?php $smtpSecurity = $configData['notifications']['smtp']['security'] ?? 'tls'; ?>
                                        <option value="none" <?= $smtpSecurity === 'none' ? 'selected' : '' ?>>Ninguno</option>
                                        <option value="tls" <?= $smtpSecurity === 'tls' ? 'selected' : '' ?>>TLS</option>
                                        <option value="ssl" <?= $smtpSecurity === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                    </select>
                                </div>
                                <div class="input-stack">
                                    <label>Usuario SMTP</label>
                                    <input name="smtp_username" value="<?= htmlspecialchars($configData['notifications']['smtp']['username'] ?? '') ?>" autocomplete="off">
                                </div>
                                <div class="input-stack">
                                    <label>Contraseña SMTP</label>
                                    <input type="password" name="smtp_password" placeholder="<?= $smtpPasswordConfigured ? '•••••• (configurado)' : 'Sin configurar' ?>" autocomplete="new-password">
                                    <small class="text-muted">Solo se actualizará si escribes una nueva contraseña.</small>
                                </div>
                                <div class="input-stack">
                                    <label>Correo remitente</label>
                                    <input type="email" name="smtp_from_email" value="<?= htmlspecialchars($configData['notifications']['smtp']['from_email'] ?? '') ?>">
                                </div>
                                <div class="input-stack">
                                    <label>Nombre del remitente</label>
                                    <input name="smtp_from_name" value="<?= htmlspecialchars($configData['notifications']['smtp']['from_name'] ?? '') ?>">
                                </div>
                                <div class="input-stack">
                                    <label>Correo de prueba</label>
                                    <input type="email" name="smtp_test_email" value="<?= htmlspecialchars($configData['notifications']['smtp']['test_email'] ?? '') ?>" placeholder="correo@empresa.com">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-footer notification-actions">
                        <span class="text-muted">Configura reglas globales sin tocar código.</span>
                        <div style="display:flex; gap:8px;">
                            <button class="btn secondary" type="submit" name="notifications_action" value="send_test">Enviar prueba</button>
                            <button class="btn primary" type="submit" name="notifications_action" value="save">Guardar configuración</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card config-card notification-rules-card">
                <div class="card-content">
                    <header class="section-header notification-section-header">
                        <div class="notification-title-row">
                            <span class="notification-icon" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></span>
                            <div>
                                <h3 style="margin:0;">Reglas de notificación</h3>
                                <p class="text-muted">Activa eventos, define canales y destinatarios por dominio.</p>
                            </div>
                        </div>
                    </header>

                    <div class="notification-domain-list">
                        <?php foreach ($groupedNotifications as $group): ?>
                            <?php $eventCount = count($group['events']); ?>
                            <details class="notification-domain-card" open>
                                <summary class="notification-domain-summary">
                                    <div class="notification-domain-title">
                                        <span class="notification-domain-icon" aria-hidden="true"><?= $group['meta']['icon'] ?></span>
                                        <span class="notification-domain-name"><?= htmlspecialchars($group['meta']['label']) ?></span>
                                    </div>
                                    <span class="notification-domain-count"><?= $eventCount ?></span>
                                </summary>

                                <div class="notification-event-list">
                                    <?php foreach ($group['events'] as $code => $meta): ?>
                                        <?php
                                        $key = 'notify_' . str_replace(['.', '-'], '_', $code);
                                        $eventConfig = $configData['notifications']['events'][$code] ?? [];
                                        $recipients = $eventConfig['recipients'] ?? [];
                                        $roles = $recipients['roles'] ?? [];
                                        ?>
                                        <div class="notification-event-card">
                                            <div class="notification-event-header">
                                                <div class="notification-event-info">
                                                    <strong><?= htmlspecialchars($meta['label']) ?></strong>
                                                    <div class="text-muted notification-event-description"><?= htmlspecialchars($meta['description'] ?? '') ?></div>
                                                </div>
                                                <label class="toggle-switch toggle-switch--solo">
                                                    <input type="checkbox" name="<?= $key ?>_enabled" <?= !empty($eventConfig['enabled']) ? 'checked' : '' ?>>
                                                    <span class="toggle-track" aria-hidden="true"></span>
                                                </label>
                                            </div>

                                            <details class="notification-event-details">
                                                <summary>Ver reglas</summary>
                                                <div class="notification-event-body">
                                                    <div class="notification-event-row">
                                                        <span class="notification-field-label">Canal</span>
                                                        <label class="toggle-switch toggle-switch--compact">
                                                            <span class="toggle-label">Correo</span>
                                                            <input type="checkbox" name="<?= $key ?>_channel_email" <?= !empty($eventConfig['channels']['email']['enabled']) ? 'checked' : '' ?>>
                                                            <span class="toggle-track" aria-hidden="true"></span>
                                                        </label>
                                                        <small class="text-muted">(Preparado para futuros canales)</small>
                                                    </div>

                                                    <div class="notification-event-row notification-recipient-row">
                                                        <span class="notification-field-label">Destinatarios</span>
                                                        <div class="notification-chip-row">
                                                            <?php if (!empty($roles)): ?>
                                                                <?php foreach ($roles as $role): ?>
                                                                    <span class="notification-chip"><?= htmlspecialchars($role) ?></span>
                                                                <?php endforeach; ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">Sin roles definidos</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="notification-recipient-controls">
                                                            <div class="input-stack">
                                                                <label>Roles</label>
                                                                <input name="<?= $key ?>_roles" value="<?= htmlspecialchars(implode(', ', $roles)) ?>" placeholder="Administrador, PMO">
                                                            </div>
                                                            <div class="notification-recipient-grid">
                                                                <label class="toggle-switch toggle-switch--compact">
                                                                    <span class="toggle-label">Responsable del proyecto</span>
                                                                    <input type="checkbox" name="<?= $key ?>_include_pm" <?= !empty($recipients['include_project_manager']) ? 'checked' : '' ?>>
                                                                    <span class="toggle-track" aria-hidden="true"></span>
                                                                </label>
                                                                <label class="toggle-switch toggle-switch--compact">
                                                                    <span class="toggle-label">Usuario del evento</span>
                                                                    <input type="checkbox" name="<?= $key ?>_include_actor" <?= !empty($recipients['include_actor']) ? 'checked' : '' ?>>
                                                                    <span class="toggle-track" aria-hidden="true"></span>
                                                                </label>
                                                                <label class="toggle-switch toggle-switch--compact">
                                                                    <span class="toggle-label">Usuario afectado</span>
                                                                    <input type="checkbox" name="<?= $key ?>_include_target_user" <?= !empty($recipients['include_target_user']) ? 'checked' : '' ?>>
                                                                    <span class="toggle-track" aria-hidden="true"></span>
                                                                </label>
                                                                <label class="toggle-switch toggle-switch--compact">
                                                                    <span class="toggle-label">Revisor</span>
                                                                    <input type="checkbox" name="<?= $key ?>_include_reviewer" <?= !empty($recipients['include_reviewer']) ? 'checked' : '' ?>>
                                                                    <span class="toggle-track" aria-hidden="true"></span>
                                                                </label>
                                                                <label class="toggle-switch toggle-switch--compact">
                                                                    <span class="toggle-label">Validador</span>
                                                                    <input type="checkbox" name="<?= $key ?>_include_validator" <?= !empty($recipients['include_validator']) ? 'checked' : '' ?>>
                                                                    <span class="toggle-track" aria-hidden="true"></span>
                                                                </label>
                                                                <label class="toggle-switch toggle-switch--compact">
                                                                    <span class="toggle-label">Aprobador</span>
                                                                    <input type="checkbox" name="<?= $key ?>_include_approver" <?= !empty($recipients['include_approver']) ? 'checked' : '' ?>>
                                                                    <span class="toggle-track" aria-hidden="true"></span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </details>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="card config-card notification-log-card" style="margin-top:16px;">
        <div class="card-content">
            <header class="section-header notification-section-header">
                <div class="notification-title-row">
                    <span class="notification-icon" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></span>
                    <div>
                        <h3 style="margin:0;">Log de notificaciones</h3>
                        <p class="text-muted">Registro independiente del log de auditoría.</p>
                    </div>
                </div>
            </header>
            <div class="table-wrapper notification-log-table">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha y hora</th>
                            <th>Evento</th>
                            <th>Canal</th>
                            <th>Destinatario</th>
                            <th>Estado</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notificationLogs as $entry): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($entry['created_at'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string) ($entry['event_type'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string) ($entry['channel'] ?? 'correo')) ?></td>
                                <td><?= htmlspecialchars((string) ($entry['recipient_email'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string) ($entry['status'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string) ($entry['error_message'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($notificationLogs)): ?>
                            <tr>
                                <td colspan="6" class="text-muted">Aún no hay notificaciones registradas.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
