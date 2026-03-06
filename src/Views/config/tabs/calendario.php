<?php
$configData = $configData ?? [];
$holidays = $calendarHolidays ?? [];
$workCalendar = $configData['operational_rules']['work_calendar'] ?? [];
$workDays = $workCalendar['work_days'] ?? [1, 2, 3, 4, 5];
$adminCanRegisterHolidays = !empty($workCalendar['admin_can_register_holidays']);
$dayNames = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
?>
<section id="panel-calendario" class="tab-panel governance-panel">
    <div class="governance-blocks">
        <div class="card config-card governance-block">
            <div class="card-content">
                <header class="governance-block-header">
                    <div class="governance-block-title-line">
                        <span class="governance-block-icon" aria-hidden="true">📅</span>
                        <h3 class="governance-block-title">Calendario laboral</h3>
                    </div>
                    <p class="governance-block-subtitle">Días laborales, festivos y excepciones para el registro de horas y cálculo de capacidad.</p>
                </header>

                <form method="POST" action="/config/theme" id="work-calendar-config-form" enctype="multipart/form-data">
                    <input type="hidden" name="tab" value="calendario">
                    <div class="governance-card-body">
                        <div class="governance-module">
                            <span class="governance-module-title">Días laborales</span>
                            <p class="governance-module-desc">Selecciona los días de la semana considerados laborales (por defecto Lunes a Viernes).</p>
                            <div class="governance-tag-chips" style="margin-top:8px;">
                                <?php for ($d = 1; $d <= 7; $d++): ?>
                                    <label class="governance-tag-chip" style="cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                                        <input type="checkbox" name="work_days[]" value="<?= $d ?>" <?= in_array($d, $workDays, true) ? 'checked' : '' ?>>
                                        <?= htmlspecialchars($dayNames[$d]) ?>
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="governance-rule" style="margin-top:16px;">
                            <div class="governance-rule-info">
                                <span class="governance-rule-title">Administradores pueden registrar horas en festivos</span>
                                <p class="governance-rule-desc">Permite a administradores registrar horas en días festivos (guardias, soporte, emergencias).</p>
                            </div>
                            <label class="toggle-switch toggle-switch--solo" aria-label="Administradores pueden registrar en festivos">
                                <input type="checkbox" name="admin_can_register_holidays" <?= $adminCanRegisterHolidays ? 'checked' : '' ?>>
                                <span class="toggle-track" aria-hidden="true"></span>
                            </label>
                        </div>

                        <div class="form-footer" style="margin-top:16px;">
                            <button type="submit" class="btn primary">Guardar configuración</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card config-card governance-block">
            <div class="card-content">
                <header class="governance-block-header">
                    <div class="governance-block-title-line">
                        <span class="governance-block-icon" aria-hidden="true">🎉</span>
                        <h3 class="governance-block-title">Festivos</h3>
                    </div>
                    <p class="governance-block-subtitle">Días festivos en los que no se pueden registrar horas (salvo administradores si está habilitado).</p>
                </header>

                <form method="POST" action="/config/calendar-holidays/create" class="governance-card-body" style="display:flex;flex-direction:column;gap:12px;">
                    <div class="config-form-grid" style="grid-template-columns:1fr 1.5fr auto;align-items:end;">
                        <label class="input-stack">
                            <span>Fecha</span>
                            <input type="date" name="holiday_date" required>
                        </label>
                        <label class="input-stack">
                            <span>Nombre del festivo</span>
                            <input type="text" name="holiday_name" maxlength="180" placeholder="Ej. Año Nuevo, Reyes, San José" required>
                        </label>
                        <button type="submit" class="btn primary">Agregar festivo</button>
                    </div>
                </form>

                <div class="governance-card-body" style="margin-top:16px;">
                    <table class="data-table" style="width:100%;font-size:14px;">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Nombre</th>
                                <th style="width:80px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($holidays === []): ?>
                                <tr>
                                    <td colspan="3" class="text-muted">No hay festivos configurados. Agrega festivos como 01-01 (Año Nuevo), 06-01 (Reyes), 19-03 (San José), etc.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($holidays as $h): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) ($h['holiday_date'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars((string) ($h['name'] ?? '')) ?></td>
                                        <td>
                                            <form method="POST" action="/config/calendar-holidays/delete" style="display:inline;">
                                                <input type="hidden" name="id" value="<?= (int) ($h['id'] ?? 0) ?>">
                                                <button type="submit" class="btn ghost btn-xs" onclick="return confirm('¿Eliminar este festivo?');">Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
