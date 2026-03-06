<?php
$calendarConfig = $calendarConfig ?? ['working_days' => [1 => true, 2 => true, 3 => true, 4 => true, 5 => true, 6 => false, 7 => false], 'default_daily_hours' => 8, 'admin_can_override_holidays' => true];
$calendarHolidays = is_array($calendarHolidays ?? null) ? $calendarHolidays : [];
$workingDays = $calendarConfig['working_days'] ?? [];
$dayNames = [
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado',
    7 => 'Domingo',
];
$basePath = $basePath ?? '';
?>

<style>
    .calendar-config-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 20px;
    }
    .calendar-days-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 10px;
    }
    .calendar-day-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 12px 14px;
        border-radius: 14px;
        border: 1px solid var(--border);
        background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%);
        transition: border-color 0.2s ease, background 0.2s ease;
    }
    .calendar-day-card.active {
        border-color: color-mix(in srgb, var(--primary) 50%, var(--border));
        background: color-mix(in srgb, var(--primary) 8%, var(--surface));
    }
    .calendar-day-name {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 14px;
    }
    .calendar-day-status {
        font-size: 12px;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 999px;
    }
    .calendar-day-status.laboral {
        background: color-mix(in srgb, var(--success) 18%, var(--surface));
        color: var(--success);
    }
    .calendar-day-status.no-laboral {
        background: color-mix(in srgb, var(--danger) 14%, var(--surface));
        color: var(--danger);
    }
    .holidays-table-wrap {
        overflow-x: auto;
    }
    .holidays-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 14px;
    }
    .holidays-table thead th {
        background: color-mix(in srgb, var(--surface) 90%, var(--background) 10%);
        padding: 10px 12px;
        font-weight: 700;
        color: var(--text-secondary);
        text-align: left;
        border-bottom: 2px solid var(--border);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .holidays-table tbody td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--border);
        color: var(--text-primary);
        vertical-align: middle;
    }
    .holidays-table tbody tr:hover {
        background: color-mix(in srgb, var(--primary) 6%, var(--surface));
    }
    .holiday-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
    }
    .holiday-badge.recurring {
        background: color-mix(in srgb, var(--info) 16%, var(--surface));
        color: var(--info);
    }
    .holiday-badge.single {
        background: color-mix(in srgb, var(--warning) 16%, var(--surface));
        color: var(--warning);
    }
    .holiday-badge.active-yes {
        background: color-mix(in srgb, var(--success) 16%, var(--surface));
        color: var(--success);
    }
    .holiday-badge.active-no {
        background: color-mix(in srgb, var(--danger) 14%, var(--surface));
        color: var(--danger);
    }
    .holiday-actions {
        display: flex;
        gap: 6px;
    }
    .holiday-btn {
        padding: 4px 10px;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: var(--surface);
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        color: var(--text-primary);
        transition: background 0.15s ease;
    }
    .holiday-btn:hover {
        background: color-mix(in srgb, var(--primary) 10%, var(--surface));
    }
    .holiday-btn.danger {
        color: var(--danger);
        border-color: color-mix(in srgb, var(--danger) 30%, var(--border));
    }
    .holiday-btn.danger:hover {
        background: color-mix(in srgb, var(--danger) 10%, var(--surface));
    }
    .holiday-form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        align-items: end;
    }
    .holiday-form-grid label {
        display: flex;
        flex-direction: column;
        gap: 6px;
        font-weight: 600;
        color: var(--text-secondary);
        font-size: 13px;
    }
    .calendar-empty-state {
        text-align: center;
        padding: 32px 16px;
        color: var(--text-secondary);
    }
    .calendar-empty-state .empty-icon {
        font-size: 42px;
        margin-bottom: 10px;
    }
    .calendar-capacity-card {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 16px;
        border-radius: 14px;
        border: 1px solid color-mix(in srgb, var(--primary) 25%, var(--border));
        background: color-mix(in srgb, var(--primary) 8%, var(--surface));
    }
    .calendar-capacity-row {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .calendar-capacity-value {
        font-size: 28px;
        font-weight: 800;
        color: var(--primary);
    }
    .calendar-capacity-label {
        color: var(--text-secondary);
        font-size: 14px;
    }
    .holiday-edit-modal {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 1000;
        background: rgba(0, 0, 0, 0.5);
        align-items: center;
        justify-content: center;
    }
    .holiday-edit-modal.open {
        display: flex;
    }
    .holiday-edit-modal-content {
        background: var(--surface);
        border-radius: 18px;
        padding: 24px;
        max-width: 520px;
        width: 100%;
        box-shadow: 0 16px 48px rgba(0, 0, 0, 0.2);
    }
    @media (max-width: 768px) {
        .calendar-config-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<section id="panel-calendario" class="tab-panel">
    <div class="governance-panel">

        <div class="governance-block card config-card governance-block--critical">
            <div class="card-content">
                <div class="governance-block-header">
                    <div class="governance-block-title-line">
                        <span class="governance-block-icon">📅</span>
                        <h3 class="governance-block-title">Calendario laboral</h3>
                    </div>
                    <p class="governance-block-subtitle">Define los días laborales, festivos y excepciones del sistema.</p>
                </div>

                <div class="calendar-config-grid">
                    <div>
                        <form method="POST" action="<?= $basePath ?>/config/work-calendar">
                            <div class="governance-card-body">
                                <div class="governance-module">
                                    <span class="section-label" style="margin-bottom: 8px;">Días laborales de la semana</span>
                                    <div class="calendar-days-grid">
                                        <?php foreach ($dayNames as $dayNum => $dayName): ?>
                                            <?php $isActive = !empty($workingDays[$dayNum]); ?>
                                            <label class="calendar-day-card <?= $isActive ? 'active' : '' ?>" id="day-card-<?= $dayNum ?>">
                                                <span class="calendar-day-name"><?= htmlspecialchars($dayName) ?></span>
                                                <label class="toggle-switch toggle-switch--compact">
                                                    <input type="checkbox" name="working_day_<?= $dayNum ?>" <?= $isActive ? 'checked' : '' ?>
                                                        onchange="document.getElementById('day-card-<?= $dayNum ?>').classList.toggle('active', this.checked);">
                                                    <span class="toggle-track"></span>
                                                </label>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="governance-module" style="margin-top: 16px;">
                                    <span class="section-label" style="margin-bottom: 8px;">Configuración de jornada</span>
                                    <div class="holiday-form-grid" style="grid-template-columns: 1fr 1fr;">
                                        <label>Horas por día laboral
                                            <input type="number" name="default_daily_hours" value="<?= htmlspecialchars((string) ($calendarConfig['default_daily_hours'] ?? 8)) ?>" min="1" max="24" step="0.5">
                                        </label>
                                        <div style="display: flex; flex-direction: column; gap: 6px;">
                                            <span style="font-weight: 600; color: var(--text-secondary); font-size: 13px;">Permisos especiales</span>
                                            <div class="governance-rule" style="padding: 10px 12px;">
                                                <div class="governance-rule-info">
                                                    <span class="governance-rule-title">Administradores en festivos</span>
                                                    <p class="governance-rule-desc">Permite registrar horas en días festivos (guardias, emergencias)</p>
                                                </div>
                                                <label class="toggle-switch toggle-switch--compact">
                                                    <input type="checkbox" name="admin_can_override_holidays" <?= !empty($calendarConfig['admin_can_override_holidays']) ? 'checked' : '' ?>>
                                                    <span class="toggle-track"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <?php
                                $workingCount = 0;
                                foreach ($workingDays as $w) { if ($w) $workingCount++; }
                                $weeklyHours = $workingCount * ($calendarConfig['default_daily_hours'] ?? 8);
                                ?>
                                <div class="calendar-capacity-card">
                                    <span class="section-label">Capacidad semanal base</span>
                                    <div class="calendar-capacity-row">
                                        <span class="calendar-capacity-value"><?= $weeklyHours ?>h</span>
                                        <span class="calendar-capacity-label"><?= $workingCount ?> días laborales &times; <?= (float) ($calendarConfig['default_daily_hours'] ?? 8) ?>h/día</span>
                                    </div>
                                </div>

                                <div class="form-footer" style="display:flex; justify-content:flex-end; gap: 12px;">
                                    <button type="submit" class="btn primary">Guardar configuración</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div>
                        <div class="info-callout" style="margin-bottom: 16px;">
                            <span class="info-callout-icon">💡</span>
                            <div>
                                <p><strong>Impacto del calendario</strong></p>
                                <p style="font-size: 13px; margin-top: 4px;">
                                    La configuración del calendario laboral afecta:<br>
                                    &bull; Cálculo de capacidad semanal<br>
                                    &bull; Bloqueo de registro de horas<br>
                                    &bull; Cálculo de días hábiles entre fechas<br>
                                    &bull; Reportes de productividad y carga
                                </p>
                            </div>
                        </div>

                        <div class="operacion-card">
                            <div class="operacion-card-header">
                                <span class="operacion-card-icon">📊</span>
                                <span>Resumen semanal</span>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 6px; font-size: 14px;">
                                <?php foreach ($dayNames as $dayNum => $dayName): ?>
                                    <div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid var(--border);">
                                        <span style="font-weight: 600;"><?= htmlspecialchars($dayName) ?></span>
                                        <?php if (!empty($workingDays[$dayNum])): ?>
                                            <span class="calendar-day-status laboral">Laboral</span>
                                        <?php else: ?>
                                            <span class="calendar-day-status no-laboral">No laboral</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="governance-block card config-card governance-block--critical">
            <div class="card-content">
                <div class="governance-block-header">
                    <div class="governance-block-title-line">
                        <span class="governance-block-icon">🎉</span>
                        <h3 class="governance-block-title">Días festivos</h3>
                    </div>
                    <p class="governance-block-subtitle">Registra los festivos oficiales y excepciones del calendario laboral.</p>
                </div>

                <details class="json-collapse" style="margin-bottom: 16px;" id="add-holiday-form">
                    <summary>+ Agregar festivo</summary>
                    <form method="POST" action="<?= $basePath ?>/config/holidays/create" style="margin-top: 14px;">
                        <div class="holiday-form-grid">
                            <label>Fecha
                                <input type="date" name="holiday_date" required>
                            </label>
                            <label>Nombre
                                <input type="text" name="name" placeholder="Ej: Año Nuevo" required>
                            </label>
                            <label>Descripción (opcional)
                                <input type="text" name="description" placeholder="Ej: Festivo nacional">
                            </label>
                            <label style="gap: 10px; flex-direction: row; align-items: center;">
                                <input type="checkbox" name="recurring">
                                <span>Se repite cada año</span>
                            </label>
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 14px;">
                            <button type="submit" class="btn primary">Agregar festivo</button>
                        </div>
                    </form>
                </details>

                <?php if ($calendarHolidays === []): ?>
                    <div class="calendar-empty-state">
                        <div class="empty-icon">📅</div>
                        <h4 style="margin: 0 0 4px;">Sin festivos registrados</h4>
                        <p style="margin: 0;">Agrega los días festivos de tu organización para mejorar la planificación.</p>
                    </div>
                <?php else: ?>
                    <div class="holidays-table-wrap">
                        <table class="holidays-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Nombre</th>
                                    <th>Descripción</th>
                                    <th>Tipo</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($calendarHolidays as $holiday): ?>
                                    <?php
                                    $hDate = new DateTimeImmutable($holiday['holiday_date']);
                                    $isRecurring = (int) ($holiday['recurring'] ?? 0) === 1;
                                    $isActive = (int) ($holiday['active'] ?? 1) === 1;
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($hDate->format('d/m/Y')) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($hDate->format('l')) ?></small>
                                        </td>
                                        <td><strong><?= htmlspecialchars($holiday['name']) ?></strong></td>
                                        <td><span class="text-muted"><?= htmlspecialchars($holiday['description'] ?? '—') ?></span></td>
                                        <td>
                                            <?php if ($isRecurring): ?>
                                                <span class="holiday-badge recurring">🔄 Anual</span>
                                            <?php else: ?>
                                                <span class="holiday-badge single">📌 Único</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($isActive): ?>
                                                <span class="holiday-badge active-yes">Activo</span>
                                            <?php else: ?>
                                                <span class="holiday-badge active-no">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="holiday-actions">
                                                <button type="button" class="holiday-btn" onclick="openEditHoliday(<?= htmlspecialchars(json_encode($holiday, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)">Editar</button>
                                                <form method="POST" action="<?= $basePath ?>/config/holidays/delete" style="display:inline;" onsubmit="return confirm('¿Eliminar este festivo?');">
                                                    <input type="hidden" name="id" value="<?= (int) $holiday['id'] ?>">
                                                    <button type="submit" class="holiday-btn danger">Eliminar</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</section>

<div class="holiday-edit-modal" id="holidayEditModal">
    <div class="holiday-edit-modal-content">
        <h3 style="margin: 0 0 16px;">Editar festivo</h3>
        <form method="POST" action="<?= $basePath ?>/config/holidays/update" id="holidayEditForm">
            <input type="hidden" name="id" id="editHolidayId">
            <div class="holiday-form-grid" style="grid-template-columns: 1fr 1fr;">
                <label>Fecha
                    <input type="date" name="holiday_date" id="editHolidayDate" required>
                </label>
                <label>Nombre
                    <input type="text" name="name" id="editHolidayName" required>
                </label>
                <label>Descripción
                    <input type="text" name="description" id="editHolidayDescription">
                </label>
                <label style="gap: 10px; flex-direction: row; align-items: center;">
                    <input type="checkbox" name="recurring" id="editHolidayRecurring">
                    <span>Se repite cada año</span>
                </label>
                <label style="gap: 10px; flex-direction: row; align-items: center;">
                    <input type="checkbox" name="active" id="editHolidayActive">
                    <span>Activo</span>
                </label>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px;">
                <button type="button" class="btn" onclick="closeEditHoliday()">Cancelar</button>
                <button type="submit" class="btn primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditHoliday(holiday) {
    document.getElementById('editHolidayId').value = holiday.id;
    document.getElementById('editHolidayDate').value = holiday.holiday_date;
    document.getElementById('editHolidayName').value = holiday.name;
    document.getElementById('editHolidayDescription').value = holiday.description || '';
    document.getElementById('editHolidayRecurring').checked = parseInt(holiday.recurring) === 1;
    document.getElementById('editHolidayActive').checked = parseInt(holiday.active) === 1;
    document.getElementById('holidayEditModal').classList.add('open');
}

function closeEditHoliday() {
    document.getElementById('holidayEditModal').classList.remove('open');
}

document.getElementById('holidayEditModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditHoliday();
    }
});
</script>
