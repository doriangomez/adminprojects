<?php
$workCalendar = $configData['work_calendar'] ?? [];
$workingDays = $workCalendar['working_days'] ?? [1, 2, 3, 4, 5];
$hoursPerDay = (int) ($workCalendar['hours_per_day'] ?? 8);
$allowAdminOnHolidays = (bool) ($workCalendar['allow_admin_on_holidays'] ?? true);
$allowAdminOnWeekends = (bool) ($workCalendar['allow_admin_on_weekends'] ?? true);
$calendarHolidays = is_array($calendarHolidays ?? null) ? $calendarHolidays : [];

$dayNames = [
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado',
    7 => 'Domingo',
];
?>
<style>
    .calendario-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr);
        gap: 20px;
        align-items: start;
    }
    .calendar-working-days {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .calendar-day-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 14px;
        border-radius: 12px;
        border: 1px solid var(--border);
        background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%);
    }
    .calendar-day-row.is-working {
        border-color: color-mix(in srgb, var(--primary) 30%, var(--border));
        background: color-mix(in srgb, var(--primary) 6%, var(--surface));
    }
    .calendar-day-name {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 14px;
    }
    .calendar-day-badge {
        font-size: 11px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 999px;
        color: var(--text-secondary);
        background: var(--border);
    }
    .calendar-day-badge.working {
        background: color-mix(in srgb, var(--primary) 18%, var(--surface));
        color: var(--primary);
    }
    .holidays-panel {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .holidays-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .holiday-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .holiday-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        border-radius: 12px;
        border: 1px solid var(--border);
        background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%);
        transition: border-color 0.15s;
    }
    .holiday-item:hover {
        border-color: color-mix(in srgb, var(--primary) 35%, var(--border));
    }
    .holiday-icon {
        font-size: 20px;
        flex: 0 0 auto;
    }
    .holiday-info {
        flex: 1;
        min-width: 0;
    }
    .holiday-name {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 14px;
    }
    .holiday-date-label {
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: 2px;
    }
    .holiday-type-badge {
        font-size: 11px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 999px;
        flex: 0 0 auto;
    }
    .holiday-type-badge.holiday {
        background: color-mix(in srgb, #ef4444 15%, var(--surface));
        color: #ef4444;
        border: 1px solid color-mix(in srgb, #ef4444 25%, var(--border));
    }
    .holiday-type-badge.exception {
        background: color-mix(in srgb, var(--warning) 15%, var(--surface));
        color: var(--warning);
        border: 1px solid color-mix(in srgb, var(--warning) 25%, var(--border));
    }
    .holiday-recurring-badge {
        font-size: 10px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--info) 14%, var(--surface));
        color: var(--info);
        border: 1px solid color-mix(in srgb, var(--info) 25%, var(--border));
        flex: 0 0 auto;
    }
    .holiday-actions {
        display: flex;
        gap: 6px;
        flex: 0 0 auto;
    }
    .holiday-empty {
        text-align: center;
        padding: 32px 16px;
        color: var(--text-secondary);
        border: 1.5px dashed var(--border);
        border-radius: 14px;
    }
    .holiday-empty-icon {
        font-size: 32px;
        display: block;
        margin-bottom: 8px;
    }
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }
    .modal-overlay[hidden] {
        display: none;
    }
    .modal-box {
        background: var(--surface);
        border-radius: 18px;
        border: 1px solid var(--border);
        padding: 24px;
        width: 100%;
        max-width: 420px;
        display: flex;
        flex-direction: column;
        gap: 16px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .modal-title {
        font-weight: 700;
        font-size: 18px;
        color: var(--text-primary);
        margin: 0;
    }
    .modal-body {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .capacity-summary {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        padding: 14px;
        border-radius: 12px;
        border: 1px solid color-mix(in srgb, var(--primary) 25%, var(--border));
        background: color-mix(in srgb, var(--primary) 6%, var(--surface));
    }
    .capacity-stat {
        display: flex;
        flex-direction: column;
        gap: 2px;
        align-items: center;
        text-align: center;
    }
    .capacity-stat-value {
        font-size: 22px;
        font-weight: 800;
        color: var(--primary);
    }
    .capacity-stat-label {
        font-size: 11px;
        color: var(--text-secondary);
        font-weight: 600;
    }
    @media (max-width: 860px) {
        .calendario-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<section id="panel-calendario" class="tab-panel">
    <div class="governance-blocks">

        <div class="card config-card governance-block">
            <div class="card-content">
                <header class="governance-block-header">
                    <div class="governance-block-title-line">
                        <span class="governance-block-icon" aria-hidden="true">📅</span>
                        <h3 class="governance-block-title">Calendario laboral</h3>
                    </div>
                    <p class="governance-block-subtitle">Define los días laborales y la jornada diaria. Esto determina la capacidad semanal real del equipo.</p>
                </header>

                <div class="calendario-grid">
                    <div>
                        <form id="work-calendar-form" method="POST" action="/config/calendario">
                            <div style="display:flex;flex-direction:column;gap:14px;">
                                <div>
                                    <p style="font-size:13px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.04em;margin:0 0 10px 0;">Días laborales</p>
                                    <div class="calendar-working-days">
                                        <?php foreach ($dayNames as $num => $label): ?>
                                            <?php $isWorking = in_array($num, $workingDays, true); ?>
                                            <div class="calendar-day-row <?= $isWorking ? 'is-working' : '' ?>" id="day-row-<?= $num ?>">
                                                <span class="calendar-day-name"><?= htmlspecialchars($label) ?></span>
                                                <div style="display:flex;align-items:center;gap:10px;">
                                                    <span class="calendar-day-badge <?= $isWorking ? 'working' : '' ?>" id="day-badge-<?= $num ?>">
                                                        <?= $isWorking ? 'Laboral' : 'No laboral' ?>
                                                    </span>
                                                    <label class="toggle-switch toggle-switch--compact" aria-label="<?= htmlspecialchars($label) ?> laboral">
                                                        <input type="checkbox" name="working_days[]" value="<?= $num ?>"
                                                            <?= $isWorking ? 'checked' : '' ?>
                                                            data-day="<?= $num ?>"
                                                            onchange="updateDayRow(<?= $num ?>, this.checked)">
                                                        <span class="toggle-track" aria-hidden="true"></span>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div>
                                    <p style="font-size:13px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.04em;margin:0 0 10px 0;">Jornada diaria</p>
                                    <label style="display:flex;flex-direction:column;gap:6px;font-weight:600;color:var(--text-secondary);">
                                        Horas por día laboral
                                        <input type="number" name="hours_per_day" value="<?= $hoursPerDay ?>" min="1" max="24" class="input" style="max-width:120px;" id="hours-per-day-input" onchange="updateCapacitySummary()">
                                    </label>
                                </div>

                                <div>
                                    <p style="font-size:13px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.04em;margin:0 0 10px 0;">Permisos excepcionales (Administrador)</p>
                                    <div class="governance-rules" style="gap:8px;">
                                        <div class="governance-rule">
                                            <div class="governance-rule-info">
                                                <span class="governance-rule-title">Registrar horas en festivos</span>
                                                <p class="governance-rule-desc">Solo para guardias, soporte y emergencias.</p>
                                            </div>
                                            <label class="toggle-switch toggle-switch--solo" aria-label="Permitir admin en festivos">
                                                <input type="checkbox" name="allow_admin_on_holidays" <?= $allowAdminOnHolidays ? 'checked' : '' ?>>
                                                <span class="toggle-track" aria-hidden="true"></span>
                                            </label>
                                        </div>
                                        <div class="governance-rule">
                                            <div class="governance-rule-info">
                                                <span class="governance-rule-title">Registrar horas en fines de semana</span>
                                                <p class="governance-rule-desc">Permite horas en Sábado y Domingo para administradores.</p>
                                            </div>
                                            <label class="toggle-switch toggle-switch--solo" aria-label="Permitir admin en fines de semana">
                                                <input type="checkbox" name="allow_admin_on_weekends" <?= $allowAdminOnWeekends ? 'checked' : '' ?>>
                                                <span class="toggle-track" aria-hidden="true"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="capacity-summary" id="capacity-summary">
                                    <div class="capacity-stat">
                                        <span class="capacity-stat-value" id="cap-days"><?= count($workingDays) ?></span>
                                        <span class="capacity-stat-label">días/semana</span>
                                    </div>
                                    <div class="capacity-stat">
                                        <span class="capacity-stat-value" id="cap-hours-day"><?= $hoursPerDay ?></span>
                                        <span class="capacity-stat-label">h/día</span>
                                    </div>
                                    <div class="capacity-stat">
                                        <span class="capacity-stat-value" id="cap-hours-week"><?= count($workingDays) * $hoursPerDay ?></span>
                                        <span class="capacity-stat-label">h/semana máx.</span>
                                    </div>
                                </div>

                                <div style="display:flex;justify-content:flex-end;padding-top:4px;">
                                    <button type="submit" class="btn primary" id="save-calendar-btn">Guardar configuración</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="holidays-panel">
                        <div class="holidays-toolbar">
                            <p style="font-size:13px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.04em;margin:0;">Festivos y excepciones</p>
                            <button type="button" class="btn primary btn-sm" onclick="openHolidayModal()">+ Agregar festivo</button>
                        </div>

                        <div class="holiday-list" id="holidays-list">
                            <?php if (empty($calendarHolidays)): ?>
                                <div class="holiday-empty" id="holidays-empty">
                                    <span class="holiday-empty-icon">🗓️</span>
                                    <strong>Sin festivos configurados</strong>
                                    <p>Agrega los festivos nacionales y excepciones del calendario.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($calendarHolidays as $holiday): ?>
                                    <?php
                                    $hDate = (string) ($holiday['holiday_date'] ?? '');
                                    $hName = (string) ($holiday['name'] ?? '');
                                    $hType = (string) ($holiday['type'] ?? 'holiday');
                                    $hRecurring = (int) ($holiday['recurring'] ?? 0) === 1;
                                    $hId = (int) ($holiday['id'] ?? 0);
                                    $displayDate = $hDate !== '' ? date('d/m/Y', strtotime($hDate)) : '';
                                    $typeLabel = $hType === 'exception' ? 'Excepción' : 'Festivo';
                                    ?>
                                    <div class="holiday-item" id="holiday-<?= $hId ?>">
                                        <span class="holiday-icon"><?= $hType === 'exception' ? '🔄' : '🎉' ?></span>
                                        <div class="holiday-info">
                                            <div class="holiday-name"><?= htmlspecialchars($hName) ?></div>
                                            <div class="holiday-date-label">
                                                <?= htmlspecialchars($displayDate) ?>
                                                <?= $hRecurring ? '· Anual' : '' ?>
                                            </div>
                                        </div>
                                        <span class="holiday-type-badge <?= $hType ?>"><?= $typeLabel ?></span>
                                        <?php if ($hRecurring): ?>
                                            <span class="holiday-recurring-badge">Anual</span>
                                        <?php endif; ?>
                                        <div class="holiday-actions">
                                            <button type="button" class="btn-xs" onclick="openEditHolidayModal(<?= $hId ?>, '<?= htmlspecialchars($hDate, ENT_QUOTES) ?>', <?= json_encode($hName) ?>, '<?= $hType ?>', <?= $hRecurring ? 'true' : 'false' ?>)">Editar</button>
                                            <button type="button" class="btn-xs danger" onclick="deleteHoliday(<?= $hId ?>, <?= json_encode($hName) ?>)">Eliminar</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<div class="modal-overlay" id="holiday-modal" hidden>
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="modal-title-label">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-title-label">Agregar festivo</h3>
            <button type="button" class="btn-xs" onclick="closeHolidayModal()" aria-label="Cerrar">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="modal-holiday-id" value="">
            <label style="display:flex;flex-direction:column;gap:6px;font-weight:600;color:var(--text-secondary);">
                Fecha
                <input type="date" id="modal-holiday-date" class="input" required>
            </label>
            <label style="display:flex;flex-direction:column;gap:6px;font-weight:600;color:var(--text-secondary);">
                Nombre del festivo
                <input type="text" id="modal-holiday-name" class="input" placeholder="Ej: Año Nuevo" required maxlength="200">
            </label>
            <label style="display:flex;flex-direction:column;gap:6px;font-weight:600;color:var(--text-secondary);">
                Tipo
                <select id="modal-holiday-type" class="input">
                    <option value="holiday">Festivo (bloqueado)</option>
                    <option value="exception">Excepción (día especial)</option>
                </select>
            </label>
            <label class="toggle-switch" style="gap:10px;">
                <input type="checkbox" id="modal-holiday-recurring">
                <span class="toggle-track" aria-hidden="true"></span>
                <span class="toggle-label">Se repite cada año</span>
            </label>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button type="button" class="btn" onclick="closeHolidayModal()">Cancelar</button>
            <button type="button" class="btn primary" id="modal-save-btn" onclick="saveHoliday()">Guardar</button>
        </div>
    </div>
</div>

<script>
(function () {
    let activeDays = <?= json_encode(array_values($workingDays)) ?>;

    window.updateDayRow = function (dayNum, isChecked) {
        const row = document.getElementById('day-row-' + dayNum);
        const badge = document.getElementById('day-badge-' + dayNum);
        if (isChecked) {
            row.classList.add('is-working');
            badge.classList.add('working');
            badge.textContent = 'Laboral';
            if (!activeDays.includes(dayNum)) activeDays.push(dayNum);
        } else {
            row.classList.remove('is-working');
            badge.classList.remove('working');
            badge.textContent = 'No laboral';
            activeDays = activeDays.filter(d => d !== dayNum);
        }
        updateCapacitySummary();
    };

    window.updateCapacitySummary = function () {
        const days = activeDays.length;
        const hpd = parseInt(document.getElementById('hours-per-day-input').value, 10) || 8;
        document.getElementById('cap-days').textContent = days;
        document.getElementById('cap-hours-day').textContent = hpd;
        document.getElementById('cap-hours-week').textContent = days * hpd;
    };

    const calendarForm = document.getElementById('work-calendar-form');
    if (calendarForm) {
        calendarForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('save-calendar-btn');
            btn.disabled = true;
            btn.textContent = 'Guardando…';
            try {
                const resp = await fetch('/config/calendario', {
                    method: 'POST',
                    body: new FormData(calendarForm),
                });
                const data = await resp.json();
                if (data && data.ok) {
                    const savedBadge = document.querySelector('[data-theme-saved]');
                    if (savedBadge) { savedBadge.hidden = false; }
                }
            } catch (_) {}
            btn.disabled = false;
            btn.textContent = 'Guardar configuración';
        });
    }

    window.openHolidayModal = function () {
        document.getElementById('modal-title-label').textContent = 'Agregar festivo';
        document.getElementById('modal-holiday-id').value = '';
        document.getElementById('modal-holiday-date').value = '';
        document.getElementById('modal-holiday-name').value = '';
        document.getElementById('modal-holiday-type').value = 'holiday';
        document.getElementById('modal-holiday-recurring').checked = false;
        document.getElementById('modal-save-btn').textContent = 'Guardar';
        document.getElementById('holiday-modal').hidden = false;
    };

    window.openEditHolidayModal = function (id, date, name, type, recurring) {
        document.getElementById('modal-title-label').textContent = 'Editar festivo';
        document.getElementById('modal-holiday-id').value = id;
        document.getElementById('modal-holiday-date').value = date;
        document.getElementById('modal-holiday-name').value = name;
        document.getElementById('modal-holiday-type').value = type;
        document.getElementById('modal-holiday-recurring').checked = recurring;
        document.getElementById('modal-save-btn').textContent = 'Actualizar';
        document.getElementById('holiday-modal').hidden = false;
    };

    window.closeHolidayModal = function () {
        document.getElementById('holiday-modal').hidden = true;
    };

    window.saveHoliday = async function () {
        const id = document.getElementById('modal-holiday-id').value;
        const date = document.getElementById('modal-holiday-date').value;
        const name = document.getElementById('modal-holiday-name').value.trim();
        const type = document.getElementById('modal-holiday-type').value;
        const recurring = document.getElementById('modal-holiday-recurring').checked;

        if (!date || !name) {
            alert('Fecha y nombre son obligatorios.');
            return;
        }

        const btn = document.getElementById('modal-save-btn');
        btn.disabled = true;
        btn.textContent = 'Guardando…';

        try {
            const fd = new FormData();
            fd.append('holiday_date', date);
            fd.append('name', name);
            fd.append('type', type);
            if (recurring) fd.append('recurring', '1');

            let url = '/config/calendario/holidays/create';
            if (id) {
                fd.append('id', id);
                url = '/config/calendario/holidays/update';
            }

            const resp = await fetch(url, { method: 'POST', body: fd });
            const data = await resp.json();

            if (data && data.ok) {
                closeHolidayModal();
                window.location.href = '/config?tab=calendario&saved=1';
            } else {
                alert(data.message || 'No se pudo guardar.');
            }
        } catch (_) {
            alert('Error de red.');
        }

        btn.disabled = false;
        btn.textContent = id ? 'Actualizar' : 'Guardar';
    };

    window.deleteHoliday = async function (id, name) {
        if (!confirm('¿Eliminar el festivo "' + name + '"?')) return;

        try {
            const fd = new FormData();
            fd.append('id', id);
            const resp = await fetch('/config/calendario/holidays/delete', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data && data.ok) {
                const el = document.getElementById('holiday-' + id);
                if (el) el.remove();
                const list = document.getElementById('holidays-list');
                if (list && list.children.length === 0) {
                    list.innerHTML = '<div class="holiday-empty" id="holidays-empty"><span class="holiday-empty-icon">🗓️</span><strong>Sin festivos configurados</strong><p>Agrega los festivos nacionales y excepciones del calendario.</p></div>';
                }
            } else {
                alert(data.message || 'No se pudo eliminar.');
            }
        } catch (_) {
            alert('Error de red.');
        }
    };

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeHolidayModal();
    });
})();
</script>
