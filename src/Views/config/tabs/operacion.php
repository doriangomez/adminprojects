<?php
$timesheetRules = is_array($configData['operational_rules']['timesheets'] ?? null) ? $configData['operational_rules']['timesheets'] : [];
$calendarRules = is_array($timesheetRules['work_calendar'] ?? null) ? $timesheetRules['work_calendar'] : [];
$workingDays = array_values(array_filter(
    array_map('intval', is_array($calendarRules['working_days'] ?? null) ? $calendarRules['working_days'] : [1, 2, 3, 4, 5]),
    static fn (int $value): bool => $value >= 1 && $value <= 7
));
if ($workingDays === []) {
    $workingDays = [1, 2, 3, 4, 5];
}
$holidayLines = [];
foreach (is_array($calendarRules['holidays'] ?? null) ? $calendarRules['holidays'] : [] as $holiday) {
    if (!is_array($holiday)) {
        continue;
    }
    $date = trim((string) ($holiday['date'] ?? ''));
    if ($date === '') {
        continue;
    }
    $name = trim((string) ($holiday['name'] ?? 'Festivo'));
    $holidayLines[] = $date . '|' . ($name !== '' ? $name : 'Festivo');
}
$exceptionLines = [];
foreach (is_array($calendarRules['exceptions'] ?? null) ? $calendarRules['exceptions'] : [] as $exception) {
    if (!is_array($exception)) {
        continue;
    }
    $date = trim((string) ($exception['date'] ?? ''));
    if ($date === '') {
        continue;
    }
    $type = !empty($exception['is_working']) ? 'laboral' : 'no_laboral';
    $name = trim((string) ($exception['name'] ?? ''));
    $exceptionLines[] = $date . '|' . $type . '|' . $name;
}
$weekdayLabels = [
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miercoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sabado',
    7 => 'Domingo',
];
?>
<section id="panel-operacion" class="tab-panel">
    <form method="POST" action="/config/theme" enctype="multipart/form-data">
        <div class="card config-card">
            <div class="card-content">
                <header class="section-header">
                    <h3 style="margin:0;">Operacion y ejecucion</h3>
                    <p class="text-muted">Umbrales, metodologias y calendario laboral global para el registro de horas.</p>
                </header>
                <div class="config-form-grid operacion-grid">
                    <div class="form-block operacion-column">
                        <span class="section-label">Umbrales de avance y semaforizacion</span>
                        <div class="operacion-cards">
                            <div class="operacion-card">
                                <div class="operacion-card-header">
                                    <span class="operacion-card-icon">🟡</span>
                                    <span class="operacion-card-title">Avance (%)</span>
                                </div>
                                <div class="operacion-card-grid">
                                    <label>Amarillo
                                        <input type="number" name="progress_yellow" step="0.1" value="<?= htmlspecialchars($configData['operational_rules']['semaforization']['progress']['yellow_below']) ?>">
                                    </label>
                                    <label>Rojo
                                        <input type="number" name="progress_red" step="0.1" value="<?= htmlspecialchars($configData['operational_rules']['semaforization']['progress']['red_below']) ?>">
                                    </label>
                                </div>
                            </div>
                            <div class="operacion-card">
                                <div class="operacion-card-header">
                                    <span class="operacion-card-icon">⏱️</span>
                                    <span class="operacion-card-title">Horas (desvio)</span>
                                </div>
                                <div class="operacion-card-grid">
                                    <label>Amarillo
                                        <input type="number" name="hours_yellow" step="0.01" value="<?= htmlspecialchars($configData['operational_rules']['semaforization']['hours']['yellow_above']) ?>">
                                        <small class="subtext">Ej. 0.05 = 5%</small>
                                    </label>
                                    <label>Rojo
                                        <input type="number" name="hours_red" step="0.01" value="<?= htmlspecialchars($configData['operational_rules']['semaforization']['hours']['red_above']) ?>">
                                    </label>
                                </div>
                            </div>
                            <div class="operacion-card">
                                <div class="operacion-card-header">
                                    <span class="operacion-card-icon">💰</span>
                                    <span class="operacion-card-title">Costo (desvio)</span>
                                </div>
                                <div class="operacion-card-grid">
                                    <label>Amarillo
                                        <input type="number" name="cost_yellow" step="0.01" value="<?= htmlspecialchars($configData['operational_rules']['semaforization']['cost']['yellow_above']) ?>">
                                    </label>
                                    <label>Rojo
                                        <input type="number" name="cost_red" step="0.01" value="<?= htmlspecialchars($configData['operational_rules']['semaforization']['cost']['red_above']) ?>">
                                    </label>
                                </div>
                            </div>
                            <div class="operacion-card">
                                <div class="operacion-card-header">
                                    <span class="operacion-card-icon">🗓️</span>
                                    <span class="operacion-card-title">Timesheets y calendario laboral</span>
                                </div>
                                <div class="operacion-card-grid">
                                    <label class="toggle-switch toggle-switch--state">
                                        <span class="toggle-label">Modulo de timesheets habilitado</span>
                                        <input type="checkbox" name="timesheets_enabled" <?= !empty($timesheetRules['enabled']) ? 'checked' : '' ?>>
                                        <span class="toggle-track" aria-hidden="true"></span>
                                        <span class="toggle-state"></span>
                                    </label>
                                    <label class="toggle-switch toggle-switch--state">
                                        <span class="toggle-label">Bloquear semana con dias laborales vacios</span>
                                        <input type="checkbox" name="timesheets_lock_incomplete_week" <?= !empty($timesheetRules['lock_incomplete_week']) ? 'checked' : '' ?>>
                                        <span class="toggle-track" aria-hidden="true"></span>
                                        <span class="toggle-state"></span>
                                    </label>
                                    <label>Minimo semanal de horas
                                        <input type="number" name="timesheets_minimum_weekly_hours" min="0" max="168" step="1" value="<?= htmlspecialchars((string) ($timesheetRules['minimum_weekly_hours'] ?? 0)) ?>">
                                    </label>
                                    <label>Pais (opcional)
                                        <input type="text" name="timesheets_holiday_country" maxlength="2" placeholder="CO" value="<?= htmlspecialchars((string) ($calendarRules['country'] ?? '')) ?>">
                                    </label>
                                </div>
                                <div class="input-stack">
                                    <label>Tipos de actividad (separados por coma)</label>
                                    <input name="timesheets_activity_types" value="<?= htmlspecialchars(implode(', ', is_array($timesheetRules['activity_types'] ?? null) ? $timesheetRules['activity_types'] : [])) ?>" placeholder="desarrollo, reunion, soporte">
                                </div>
                                <div class="input-stack">
                                    <label>Dias laborales de la semana</label>
                                    <div class="operacion-chip-row">
                                        <?php foreach ($weekdayLabels as $dayNumber => $dayLabel): ?>
                                            <label class="methodology-chip">
                                                <input type="checkbox" name="timesheets_working_days[]" value="<?= $dayNumber ?>" <?= in_array($dayNumber, $workingDays, true) ? 'checked' : '' ?>>
                                                <span><?= htmlspecialchars($dayLabel) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="operacion-card-grid">
                                    <label class="toggle-switch toggle-switch--state">
                                        <span class="toggle-label">Permitir solo admins en festivos</span>
                                        <input type="checkbox" name="timesheets_allow_admin_holiday_logging" <?= !empty($calendarRules['allow_admin_holiday_logging']) ? 'checked' : '' ?>>
                                        <span class="toggle-track" aria-hidden="true"></span>
                                        <span class="toggle-state"></span>
                                    </label>
                                    <label class="toggle-switch toggle-switch--state">
                                        <span class="toggle-label">Permitir admins en no laborales</span>
                                        <input type="checkbox" name="timesheets_allow_admin_non_working_logging" <?= !empty($calendarRules['allow_admin_non_working_logging']) ? 'checked' : '' ?>>
                                        <span class="toggle-track" aria-hidden="true"></span>
                                        <span class="toggle-state"></span>
                                    </label>
                                </div>
                                <div class="input-stack">
                                    <label>Festivos manuales (uno por linea: YYYY-MM-DD|Nombre)</label>
                                    <textarea name="timesheets_holidays" rows="6" class="operacion-textarea" placeholder="2026-01-01|Ano Nuevo&#10;2026-01-06|Reyes"><?= htmlspecialchars(implode("\n", $holidayLines)) ?></textarea>
                                </div>
                                <div class="input-stack">
                                    <label>Excepciones (uno por linea: YYYY-MM-DD|laboral/no_laboral|Motivo)</label>
                                    <textarea name="timesheets_exceptions" rows="5" class="operacion-textarea" placeholder="2026-03-21|laboral|Guardia de soporte&#10;2026-03-23|no_laboral|Cierre de oficina"><?= htmlspecialchars(implode("\n", $exceptionLines)) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-block operacion-column">
                        <span class="section-label">Metodologias y fases</span>
                        <div class="input-stack">
                            <label>Metodologias habilitadas (separadas por coma)</label>
                            <div class="operacion-chip-row">
                                <?php foreach ($configData['delivery']['methodologies'] as $methodology): ?>
                                    <span class="methodology-chip">
                                        <span class="methodology-icon">🧩</span>
                                        <?= htmlspecialchars(ucfirst($methodology)) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <input name="methodologies" value="<?= htmlspecialchars(implode(', ', $configData['delivery']['methodologies'])) ?>" placeholder="scrum, cascada, kanban">
                        </div>
                        <div class="input-stack">
                            <label>Estructura por metodologia / fase</label>
                            <textarea name="phases_json" rows="7" class="operacion-textarea"><?= htmlspecialchars(json_encode($configData['delivery']['phases'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></textarea>
                            <small class="subtext">Estructura: {"scrum": ["descubrimiento", "backlog"], "cascada": [...]}</small>
                        </div>
                        <div class="input-stack">
                            <label>Riesgos</label>
                            <div class="info-callout">
                                <span class="info-callout-icon">ℹ️</span>
                                <p>El catalogo maestro se gestiona desde Catalogos.</p>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="form-footer operacion-footer">
                    <span class="text-muted">Aplican a todos los proyectos en operacion.</span>
                    <button class="btn primary" type="submit">Guardar y aplicar</button>
                </div>
            </div>
        </div>
    </form>
</section>
