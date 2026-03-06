<?php
$absenceRules = is_array($configData['operational_rules']['absences'] ?? null) ? $configData['operational_rules']['absences'] : [];
?>
<section id="panel-talento" class="tab-panel">
    <form method="POST" action="/config/theme">
        <input type="hidden" name="config_tab" value="talento">
        <div class="card config-card">
            <div class="card-content">
                <header class="section-header">
                    <h3 style="margin:0;">Gestión de ausencias</h3>
                    <p class="text-muted">Activa o desactiva el módulo de ausencias y define las reglas de bloqueo de registro de horas.</p>
                </header>
                <div class="config-form-grid operacion-grid">
                    <div class="form-block operacion-column">
                        <span class="section-label">Opciones del módulo</span>
                        <div class="operacion-cards">
                            <div class="operacion-card">
                                <div class="operacion-card-header">
                                    <span class="operacion-card-icon">📋</span>
                                    <span class="operacion-card-title">Gestión de ausencias</span>
                                </div>
                                <div class="operacion-card-grid">
                                    <label class="toggle-switch toggle-switch--state">
                                        <span class="toggle-label">Habilitar gestión de ausencias</span>
                                        <input type="checkbox" name="absences_enabled" <?= !empty($absenceRules['enabled']) ? 'checked' : '' ?>>
                                        <span class="toggle-track" aria-hidden="true"></span>
                                        <span class="toggle-state"></span>
                                    </label>
                                    <label class="toggle-switch toggle-switch--state">
                                        <span class="toggle-label">Habilitar vacaciones</span>
                                        <input type="checkbox" name="absences_vacations_enabled" <?= !empty($absenceRules['vacations_enabled']) ? 'checked' : '' ?>>
                                        <span class="toggle-track" aria-hidden="true"></span>
                                        <span class="toggle-state"></span>
                                    </label>
                                    <label class="toggle-switch toggle-switch--state">
                                        <span class="toggle-label">Bloquear registro de horas en ausencias</span>
                                        <input type="checkbox" name="absences_block_timesheet" <?= !empty($absenceRules['block_timesheet_on_absence']) ? 'checked' : '' ?>>
                                        <span class="toggle-track" aria-hidden="true"></span>
                                        <span class="toggle-state"></span>
                                    </label>
                                    <label class="toggle-switch toggle-switch--state">
                                        <span class="toggle-label">Permitir excepciones para administradores</span>
                                        <input type="checkbox" name="absences_allow_admin_exceptions" <?= !empty($absenceRules['allow_admin_exceptions']) ? 'checked' : '' ?>>
                                        <span class="toggle-track" aria-hidden="true"></span>
                                        <span class="toggle-state"></span>
                                    </label>
                                </div>
                                <p class="section-muted" style="margin: 8px 0 0 0; font-size: 13px;">
                                    Si el módulo está deshabilitado, el menú Ausencias no se mostrará y la capacidad no considerará ausencias.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-footer operacion-footer">
                    <span class="text-muted">Las ausencias afectan la capacidad en Carga talento y Simulación.</span>
                    <button class="btn primary" type="submit">Guardar y aplicar</button>
                </div>
            </div>
        </div>
    </form>
</section>
